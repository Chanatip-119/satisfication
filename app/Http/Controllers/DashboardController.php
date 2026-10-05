<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Evaluation;
use App\Models\Schedule;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period   = $request->query('period', 'today');
        $today    = Carbon::today();
        $todayStr = $today->format('Y-m-d');
        $nowTime  = Carbon::now()->format('H:i:s');

        // 1. เตรียมข้อมูล Counter, CounterSub, Staff และ Schedule ทั้งหมด
        $counters = Counter::with('countersubs')->where('is_active', true)->get();
        $staffMap = Staff::all()->keyBy('staff_id');
        $allSchedules = Schedule::with('staff')->orderBy('schedule_date', 'desc')->get();
        $allSchedulesMap = $allSchedules->keyBy('schedule_id');

        $todaySchedules = Schedule::with('staff')
            ->where('schedule_date', $todayStr)
            ->orderBy('start_time', 'asc')
            ->orderBy('schedule_id', 'asc')
            ->get();

        // สร้าง Map รหัสเคาน์เตอร์ย่อย (เช่น counter_sub_id 1 => 1.1, counter_sub_id 3 => 2.1)
        $subCounterMap = [];
        foreach ($counters as $counter) {
            $sIdx = 1;
            foreach ($counter->countersubs as $sub) {
                $subCounterMap[$sub->counter_sub_id] = [
                    'code'       => $counter->counter_id . '.' . $sIdx++,
                    'location'   => $counter->counter_location,
                    'counter_id' => $counter->counter_id
                ];
            }
        }

        // ดึงข้อมูล Checkin ทั้งหมดมาทำ Map คู่กับ checkin_id
        $allCheckins = Checkin::all();
        $checkinMap = $allCheckins->keyBy('checkin_id');

        // ดึงรายการ Checkin ที่กำลัง Active อยู่ตอนนี้ (checkout_at IS NULL)
        $activeCheckins = $allCheckins->whereNull('checkout_at');

        // 2. ดึงข้อมูล Evaluation และเชื่อมกับ Checkin -> Schedule -> Staff ตามฐานข้อมูลจริง 100%
        $rawEvaluations = class_exists(Evaluation::class)
            ? Evaluation::orderBy('evaluation_id', 'asc')->get()
            : collect();

        $normalizedEvals = collect();

        foreach ($rawEvaluations as $ev) {
            $scoreVal   = (float) ($ev->rating ?? $ev->score ?? 0);
            $commentVal = trim((string) ($ev->comment ?? ''));

            $linkedCheckin = ($ev->checkin_id && isset($checkinMap[$ev->checkin_id]))
                ? $checkinMap[$ev->checkin_id]
                : null;

            $resolvedStaffId    = $ev->staff_id ?? ($linkedCheckin ? $linkedCheckin->staff_id : null);
            $resolvedScheduleId = $ev->schedule_id ?? ($linkedCheckin ? $linkedCheckin->schedule_id : null);
            $resolvedSubId      = $ev->counter_sub_id ?? null;

            // ดึง counter_sub_id จาก Schedule ที่ผูกกับ Checkin
            if (!$resolvedSubId && $resolvedScheduleId && isset($allSchedulesMap[$resolvedScheduleId])) {
                $linkedSch = $allSchedulesMap[$resolvedScheduleId];
                $resolvedSubId = $linkedSch->counter_sub_id;
                if (!$resolvedStaffId) {
                    $resolvedStaffId = $linkedSch->staff_id;
                }
            }

            // กรณี Checkin ไม่ได้ผูก schedule_id ให้หาจากตารางปฏิบัติงานของพนักงานคนนั้น
            if (!$resolvedSubId && $resolvedStaffId) {
                $staffSch = $todaySchedules->firstWhere('staff_id', $resolvedStaffId)
                    ?? $allSchedules->where('staff_id', $resolvedStaffId)->first();
                if ($staffSch) {
                    $resolvedSubId = $staffSch->counter_sub_id;
                }
            }

            // วันเวลาที่ประเมิน (ใช้ created_at ของ evaluation หรือใช้เวลา checkin_at จากตาราง checkin)
            $dateSource = $ev->created_at ?? ($linkedCheckin ? $linkedCheckin->checkin_at : null) ?? now();
            $resolvedDate = Carbon::parse($dateSource);

            $resolvedCounterId = ($resolvedSubId && isset($subCounterMap[$resolvedSubId]))
                ? $subCounterMap[$resolvedSubId]['counter_id']
                : null;

            $normalizedEvals->push((object)[
                'evaluation_id'  => $ev->evaluation_id,
                'score'          => $scoreVal,
                'comment'        => $commentVal,
                'date'           => $resolvedDate,
                'date_str'       => $resolvedDate->format('Y-m-d'),
                'counter_sub_id' => $resolvedSubId,
                'counter_id'     => $resolvedCounterId,
                'staff_id'       => $resolvedStaffId
            ]);
        }

        // กรองตามช่วงเวลาที่เลือก (วันนี้ / 7 วันล่าสุด / เดือนนี้ / ทั้งหมด)
        $filteredEvals = $normalizedEvals->filter(function ($item) use ($period, $today) {
            if ($period === 'all') return true;
            if ($period === 'today') return $item->date->isSameDay($today);
            if ($period === 'week')  return $item->date->between($today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay());
            if ($period === 'month') return $item->date->isSameMonth($today) && $item->date->isSameYear($today);
            return true;
        });

        // ==========================================
        // 3. ข้อมูลการ์ดสรุป 4 ใบด้านบน
        // ==========================================
        $avgScore = $filteredEvals->count() > 0
            ? round($filteredEvals->avg('score'), 1)
            : 0.0;

        $totalEvalCount = $filteredEvals->count();

        $newCommentsList = $filteredEvals->filter(fn($e) => $e->comment !== '')
            ->sortByDesc('evaluation_id')
            ->values();

        $newCommentsCount = $newCommentsList->count();

        // ==========================================
        // 4. กราฟแนวโน้มคะแนนรายวัน 7 วันล่าสุด
        // ==========================================
        $trendLabels = [];
        $trendScores = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $dStr = $d->format('Y-m-d');

            $trendLabels[] = $i === 0
                ? $d->translatedFormat('j M') . ' (วันนี้)'
                : $d->translatedFormat('j M');

            $dayEvals = $normalizedEvals->where('date_str', $dStr);

            $trendScores[] = $dayEvals->count() > 0
                ? round($dayEvals->avg('score'), 2)
                : 0;
        }

        // ==========================================
        // 5. การกระจายคะแนน (5 ดาว - 1 ดาว)
        // ==========================================
        $starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($filteredEvals as $ev) {
            $s = (int) round($ev->score);
            if (isset($starCounts[$s])) {
                $starCounts[$s]++;
            }
        }
        $maxStarCount = max(max($starCounts), 1);

        // ==========================================
        // 6. สถานะเคาน์เตอร์ทั้งหมด ณ ปัจจุบัน & Staff ที่กำลัง Check-in
        // ==========================================
        $counterStatusRows   = [];
        $activeCheckinStaffs = [];
        $counterAvgScores    = [];
        $staffPerformance    = [];

        foreach ($counters as $counter) {
            $subIndex = 1;

            foreach ($counter->countersubs as $sub) {
                $labelCode    = $counter->counter_id . '.' . $subIndex++;
                $subSchedules = $todaySchedules->where('counter_sub_id', $sub->counter_sub_id);
                $subSchIds    = $subSchedules->pluck('schedule_id')->toArray();

                // ตรวจสอบว่ามีรายการ Check-in ที่ยังไม่ Check-out ในเคาน์เตอร์ย่อยนี้หรือไม่
                $activeCk = $activeCheckins->first(function ($ck) use ($subSchIds, $subSchedules) {
                    if ($ck->schedule_id && in_array($ck->schedule_id, $subSchIds)) {
                        return true;
                    }
                    if (!$ck->schedule_id && $subSchedules->where('staff_id', $ck->staff_id)->isNotEmpty()) {
                        return true;
                    }
                    return false;
                });

                $activeSch = null;
                if ($activeCk) {
                    $activeSch = $subSchedules->firstWhere('schedule_id', $activeCk->schedule_id)
                        ?? $subSchedules->firstWhere('staff_id', $activeCk->staff_id);
                }
                if (!$activeSch) {
                    $activeSch = $subSchedules->first(fn($s) => strtolower($s->status ?? '') === 'checkin')
                        ?? $subSchedules->first(fn($s) => $s->start_time <= $nowTime && $s->end_time >= $nowTime && !in_array(strtolower($s->status ?? ''), ['sick', 'close']))
                        ?? $subSchedules->first();
                }

                // ดึงรายการประเมินของเคาน์เตอร์ย่อยนี้ (หรือของพนักงานที่นั่งอยู่เคาน์เตอร์ย่อยนี้)
                $currentStaffId = $activeCk ? $activeCk->staff_id : ($activeSch ? $activeSch->staff_id : null);

                $subEvals = $filteredEvals->filter(function ($e) use ($sub, $currentStaffId) {
                    if ($e->counter_sub_id !== null && (int) $e->counter_sub_id === (int) $sub->counter_sub_id) {
                        return true;
                    }
                    if ($e->counter_sub_id === null && $currentStaffId && (int) $e->staff_id === (int) $currentStaffId) {
                        return true;
                    }
                    return false;
                });

                $subScore = $subEvals->count() > 0
                    ? round($subEvals->avg('score'), 1)
                    : null;

                $statusBadge = 'Close';
                $staffName   = null;
                $timeRange   = '—';

                if ($activeCk) {
                    $statusBadge = 'Active';
                    $ckStaff     = $staffMap[$activeCk->staff_id] ?? null;
                    $staffName   = $ckStaff ? $ckStaff->staff_name : ($activeSch && $activeSch->staff ? $activeSch->staff->staff_name : 'ไม่ระบุ');
                    $timeRange   = $activeSch ? (substr($activeSch->start_time, 0, 5) . '–' . substr($activeSch->end_time, 0, 5)) : 'วันนี้';
                } elseif ($activeSch) {
                    $timeRange = substr($activeSch->start_time, 0, 5) . '–' . substr($activeSch->end_time, 0, 5);
                    $rawSt     = strtolower($activeSch->status ?? 'empty');

                    if ($rawSt === 'checkin') {
                        $statusBadge = 'Active';
                        $staffName   = $activeSch->staff->staff_name ?? 'ไม่ระบุ';
                    } elseif ($rawSt === 'close') {
                        $statusBadge = 'Close';
                    } elseif ($rawSt === 'sick') {
                        $statusBadge = 'Available';
                    } else {
                        $slotEndDT = Carbon::parse($activeSch->schedule_date . ' ' . $activeSch->end_time);
                        if (Carbon::now()->gt($slotEndDT)) {
                            $statusBadge = 'Close';
                        } else {
                            $staffName   = $activeSch->staff ? $activeSch->staff->staff_name : null;
                            $statusBadge = 'Available';
                        }
                    }
                }

                if ($statusBadge === 'Active' && $staffName) {
                    $activeCheckinStaffs[] = [
                        'staff_id'     => $currentStaffId,
                        'staff_name'   => $staffName,
                        'counter_code' => $labelCode,
                        'time_range'   => $timeRange
                    ];
                }

                $counterStatusRows[] = [
                    'counter_code' => $labelCode,
                    'location'     => $counter->counter_location,
                    'staff_name'   => $staffName,
                    'time_range'   => $timeRange,
                    'score'        => $subScore,
                    'eval_count'   => $subEvals->count(),
                    'status'       => $statusBadge
                ];
            }

            // คำนวณคะแนนเฉลี่ยรายเคาน์เตอร์หลัก (เคาน์เตอร์ 1, เคาน์เตอร์ 2, ...)
            $cSubIds = $counter->countersubs->pluck('counter_sub_id')->map(fn($id) => (int) $id)->toArray();
            $cEvals  = $filteredEvals->filter(function ($e) use ($counter, $cSubIds) {
                return ($e->counter_id !== null && (int) $e->counter_id === (int) $counter->counter_id)
                    || ($e->counter_sub_id !== null && in_array((int) $e->counter_sub_id, $cSubIds));
            });

            $cAvg = $cEvals->count() > 0 ? round($cEvals->avg('score'), 1) : 0.0;

            $counterAvgScores[] = [
                'counter_id' => $counter->counter_id,
                'location'   => $counter->counter_location,
                'score'      => $cAvg,
                'count'      => $cEvals->count()
            ];
        }

        // นับจำนวน Staff ที่กำลัง Check-in จริง
        $staffCheckinCount = collect($activeCheckinStaffs)->pluck('staff_id')->unique()->count();

        // ==========================================
        // 7. คำนวณอันดับ "คะแนนสูง & ต้องปรับปรุง" รายบุคคลจากฐานข้อมูลจริง
        // ==========================================
        foreach ($filteredEvals as $ev) {
            if ($ev->staff_id && isset($staffMap[$ev->staff_id])) {
                $sid   = $ev->staff_id;
                $st    = $staffMap[$sid];
                $cCode = ($ev->counter_sub_id && isset($subCounterMap[$ev->counter_sub_id]))
                    ? $subCounterMap[$ev->counter_sub_id]['code']
                    : '-';

                if (!isset($staffPerformance[$sid])) {
                    $staffPerformance[$sid] = [
                        'staff_id'     => $sid,
                        'name'         => $st->staff_name,
                        'counter_code' => $cCode,
                        'scores'       => []
                    ];
                }
                $staffPerformance[$sid]['scores'][] = $ev->score;
            }
        }

        $rankedStaffs = collect($staffPerformance)->map(function ($item) {
            return [
                'staff_id'     => $item['staff_id'],
                'name'         => $item['name'],
                'counter_code' => $item['counter_code'],
                'avg_score'    => round(collect($item['scores'])->avg(), 1)
            ];
        })->sortByDesc('avg_score')->values();

        $highestStaff = $rankedStaffs->first();
        // แสดงช่อง "ต้องปรับปรุง" เฉพาะเมื่อมีบุคลากรที่ถูกประเมินมากกว่า 1 คนขึ้นไป (ไม่เอาคนเดียวกันมาแสดงซ้ำ)
        $lowestStaff  = $rankedStaffs->count() > 1 ? $rankedStaffs->last() : null;

        // ==========================================
        // 8. รายการความคิดเห็นล่าสุด
        // ==========================================
        $recentComments = $newCommentsList->take(6)->map(function ($ev) use ($subCounterMap, $staffMap) {
            $subInfo   = ($ev->counter_sub_id && isset($subCounterMap[$ev->counter_sub_id])) ? $subCounterMap[$ev->counter_sub_id] : null;
            $staffName = ($ev->staff_id && isset($staffMap[$ev->staff_id])) ? $staffMap[$ev->staff_id]->staff_name : null;

            return [
                'comment'      => $ev->comment,
                'score'        => round($ev->score, 1),
                'counter_code' => $subInfo ? 'เคาน์เตอร์ ' . $subInfo['code'] : ($ev->counter_id ? 'เคาน์เตอร์ ' . $ev->counter_id : 'ทั่วไป'),
                'staff_name'   => $staffName,
                'time'         => $ev->date ? $ev->date->diffForHumans() : ''
            ];
        });

        return view('dashboard.index', compact(
            'period',
            'avgScore',
            'totalEvalCount',
            'staffCheckinCount',
            'newCommentsCount',
            'trendLabels',
            'trendScores',
            'starCounts',
            'maxStarCount',
            'counterStatusRows',
            'activeCheckinStaffs',
            'highestStaff',
            'lowestStaff',
            'counterAvgScores',
            'recentComments'
        ));
    }
}