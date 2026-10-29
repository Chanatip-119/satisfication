<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Counter;
use App\Models\Evaluation;
use App\Models\Schedule;
use App\Models\Staff;
use Illuminate\Http\Request;
use Carbon\Carbon;
// [แก้ไข ระดับ 4] ลบ use ที่ไม่ได้ใช้แล้ว: CounterSub, DB, Schema (เดิมใช้แค่ใน getMyReviews ที่ถูกลบ)

class DashboardController extends Controller
{
    /**
     * ดึงข้อมูลและแสดงผลหน้า Dashboard หลัก
     */
    public function index(Request $request)
    {
        // [สิทธิ์ตามบทบาท] Employee ดูหน้าแดชบอร์ดไม่ได้ — พาไปหน้าตารางปฏิบัติงานแทน
        // ใช้ redirect แทนหน้า 403 เพราะหลังล็อกอิน / กดโลโก้ / ปุ่มในหน้า 403 ระบบจะพามาที่ /dashboard
        // ถ้าตอบ 403 พนักงานจะติดอยู่หน้าข้อผิดพลาดทุกครั้งที่เข้าระบบ
        if (auth()->user()?->isEmployee()) {
            return redirect()->route('schedule.index');
        }

        $period   = $request->query('period', 'today');
        $nowBkk   = Carbon::now('Asia/Bangkok');
        $todayStr = $nowBkk->format('Y-m-d');
        $nowTime  = $nowBkk->format('H:i:s');

        // [Check-in ฟีเจอร์ใหม่] Check-out อัตโนมัติ + รีเซ็ตสถานะกะ ผ่าน Service กลาง
        // เดิม: วน checkout ทุกคนที่หมดเวลาเองที่นี่ (ซ้ำกับ CheckinController) และ Check-out คนที่ต่อเวลาด้วย
        // ตอนนี้ Scheduler (counters:monitor) ทำงานนี้ทุกนาที ที่นี่เป็นตัวสำรองถ้า Scheduler ยังไม่ได้ตั้ง
        \App\Services\CheckinService::runHousekeeping();

        $counters = Counter::with('countersubs')->where('is_active', true)->get();
        $staffMap = Staff::all()->keyBy('staff_id');
        $allSchedules = Schedule::with('staff')->orderBy('schedule_date', 'desc')->get();
        $allSchedulesMap = $allSchedules->keyBy('schedule_id');

        $todaySchedules = Schedule::with('staff')
            ->where('schedule_date', $todayStr)
            ->orderBy('start_time', 'asc')
            ->orderBy('schedule_id', 'asc')
            ->get();

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

        $allCheckins = class_exists(Checkin::class) ? Checkin::all() : collect();
        $checkinMap = $allCheckins->keyBy('checkin_id');
        $activeCheckins = $allCheckins->whereNull('checkout_at');

        $rawEvaluations = class_exists(Evaluation::class)
            ? Evaluation::orderBy('evaluation_id', 'asc')->get()
            : collect();

        $normalizedEvals = collect();

        foreach ($rawEvaluations as $ev) {
            $scoreVal   = (float) ($ev->rating ?? $ev->score ?? 0);
            $commentVal = trim((string) ($ev->comment ?? ''));

            $linkedCheckin = ($ev->checkin_id && isset($checkinMap[$ev->checkin_id])) ? $checkinMap[$ev->checkin_id] : null;

            $resolvedStaffId    = $ev->staff_id ?? ($linkedCheckin ? $linkedCheckin->staff_id : null);
            $resolvedScheduleId = $ev->schedule_id ?? ($linkedCheckin ? $linkedCheckin->schedule_id : null);
            $resolvedSubId      = $ev->counter_sub_id ?? null;

            if (!$resolvedSubId && $resolvedScheduleId && isset($allSchedulesMap[$resolvedScheduleId])) {
                $linkedSch = $allSchedulesMap[$resolvedScheduleId];
                $resolvedSubId = $linkedSch->counter_sub_id;
                if (!$resolvedStaffId) {
                    $resolvedStaffId = $linkedSch->staff_id;
                }
            }

            if (!$resolvedSubId && $resolvedStaffId) {
                $staffSch = $todaySchedules->firstWhere('staff_id', $resolvedStaffId)
                    ?? $allSchedules->where('staff_id', $resolvedStaffId)->first();
                if ($staffSch) {
                    $resolvedSubId = $staffSch->counter_sub_id;
                }
            }

            // ==========================================
            // ดึงเวลาการประเมินที่แท้จริงจากฐานข้อมูลโดยตรง (แบบเดียวกับหน้า Check-in)
            // ==========================================
            $rawArray = method_exists($ev, 'toArray') ? $ev->toArray() : json_decode(json_encode($ev), true);
            $evalDateRaw = null;
            
            foreach (['created_at', 'updated_at', 'timestamp', 'evaluation_time', 'eval_time', 'evaluation_date', 'date', 'time'] as $col) {
                if (!empty($rawArray[$col])) {
                    $evalDateRaw = $rawArray[$col];
                    break;
                }
            }
            
            if (!$evalDateRaw) {
                foreach ($rawArray as $key => $val) {
                    if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $val) && !in_array($key, ['checkin_at', 'checkout_at'])) {
                        $evalDateRaw = $val;
                        break;
                    }
                }
            }
            
            $checkinFallbackTime = ($linkedCheckin && !empty($linkedCheckin->checkin_at)) ? $linkedCheckin->checkin_at : clone $nowBkk;
            $resolvedDate = $evalDateRaw ? Carbon::parse($evalDateRaw) : Carbon::parse($checkinFallbackTime);

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

        $filteredEvals = $normalizedEvals->filter(function ($item) use ($period, $nowBkk) {
            if ($period === 'all') return true;
            if ($period === 'today') return $item->date->isSameDay($nowBkk);
            if ($period === 'week')  return $item->date->between($nowBkk->copy()->subDays(6)->startOfDay(), $nowBkk->copy()->endOfDay());
            if ($period === 'month') return $item->date->isSameMonth($nowBkk) && $item->date->isSameYear($nowBkk);
            return true;
        });

        $avgScore = $filteredEvals->count() > 0 ? round($filteredEvals->avg('score'), 1) : 0.0;
        $totalEvalCount = $filteredEvals->count();

        $newCommentsList = $filteredEvals->sortByDesc('evaluation_id')->values();
        $newCommentsCount = $newCommentsList->count();

        $trendLabels = [];
        $trendScores = [];

        if ($period == 'month') {
            // 1. กรณีเลือก "เดือนนี้": วนลูปตั้งแต่วันที่ 1 จนถึงวันปัจจุบันของเดือนนี้
            $startOfMonth = $nowBkk->copy()->startOfMonth();
            $daysInMonthCount = $nowBkk->day; // นับจากวันที่ 1 ถึงวันปัจจุบัน หรือใช้ $nowBkk->daysInMonth ถ้ารวมทั้งเดือน

            for ($i = $daysInMonthCount - 1; $i >= 0; $i--) {
                $d = $nowBkk->copy()->subDays($i);
                $dStr = $d->format('Y-m-d');

                $trendLabels[] = $d->isToday()
                    ? $d->translatedFormat('j M') . ' (วันนี้)'
                    : $d->translatedFormat('j M');

                $dayEvals = $normalizedEvals->where('date_str', $dStr);
                $trendScores[] = $dayEvals->count() > 0 ? round($dayEvals->avg('score'), 2) : 0;
            }
        } elseif ($period == 'all') {
            // 2. กรณีเลือก "ทั้งหมด": ดึงวันที่ทั้งหมดที่มีการประเมินจริงในระบบมาเรียงลำดับ
            $allDates = $normalizedEvals->pluck('date_str')->unique()->sort()->values();

            if ($allDates->isEmpty()) {
                // ถ้ายังไม่มีข้อมูลเลย ให้แสดงของวันนี้กันกราฟพัง
                $trendLabels[] = $nowBkk->translatedFormat('j M') . ' (วันนี้)';
                $trendScores[] = 0;
            } else {
                foreach ($allDates as $dStr) {
                    $d = \Carbon\Carbon::parse($dStr);
                    $trendLabels[] = $d->isToday()
                        ? $d->translatedFormat('j M') . ' (วันนี้)'
                        : $d->translatedFormat('j M');

                    $dayEvals = $normalizedEvals->where('date_str', $dStr);
                    $trendScores[] = $dayEvals->count() > 0 ? round($dayEvals->avg('score'), 2) : 0;
                }
            }
        } else {
            // 3. กรณี "วันนี้" หรือ "7 วันล่าสุด" (ค่าเริ่มต้นเดิมของคุณ)
            for ($i = 6; $i >= 0; $i--) {
                $d = $nowBkk->copy()->subDays($i);
                $dStr = $d->format('Y-m-d');

                $trendLabels[] = $d->isToday()
                    ? $d->translatedFormat('j M') . ' (วันนี้)'
                    : $d->translatedFormat('j M');

                $dayEvals = $normalizedEvals->where('date_str', $dStr);
                $trendScores[] = $dayEvals->count() > 0 ? round($dayEvals->avg('score'), 2) : 0;
            }
        }

        $starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($filteredEvals as $ev) {
            $s = (int) round($ev->score);
            if (isset($starCounts[$s])) $starCounts[$s]++;
        }
        $maxStarCount = max(max($starCounts), 1);

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

                $activeCk = $activeCheckins->first(function ($ck) use ($subSchIds, $subSchedules) {
                    if ($ck->schedule_id && in_array($ck->schedule_id, $subSchIds)) return true;
                    if (!$ck->schedule_id && $subSchedules->where('staff_id', $ck->staff_id)->isNotEmpty()) return true;
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

                $currentStaffId = $activeCk ? $activeCk->staff_id : ($activeSch ? $activeSch->staff_id : null);

                $subEvals = $filteredEvals->filter(function ($e) use ($sub, $currentStaffId) {
                    if ($e->counter_sub_id !== null && (int) $e->counter_sub_id === (int) $sub->counter_sub_id) return true;
                    if ($e->counter_sub_id === null && $currentStaffId && (int) $e->staff_id === (int) $currentStaffId) return true;
                    return false;
                });

                $subScore = $subEvals->count() > 0 ? round($subEvals->avg('score'), 1) : null;
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
                        $endStr = Carbon::parse($activeSch->end_time)->format('H:i:s');
                        $slotEndDT = Carbon::parse($activeSch->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
                        if ($nowBkk->gt($slotEndDT)) {
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

        $staffCheckinCount = collect($activeCheckinStaffs)->pluck('staff_id')->unique()->count();

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
        $lowestStaff  = $rankedStaffs->count() > 1 ? $rankedStaffs->last() : null;

        $recentComments = $newCommentsList->take(6)->map(function ($ev) use ($subCounterMap, $staffMap) {
            $subInfo   = ($ev->counter_sub_id && isset($subCounterMap[$ev->counter_sub_id])) ? $subCounterMap[$ev->counter_sub_id] : null;
            $staffName = ($ev->staff_id && isset($staffMap[$ev->staff_id])) ? $staffMap[$ev->staff_id]->staff_name : null;

            $timeStr = '';
            if ($ev->date) {
                if ($ev->date->isToday()) {
                    $timeStr = $ev->date->format('H:i') . ' น.';
                } else {
                    $timeStr = $ev->date->translatedFormat('j M. H:i น.');
                }
            }

            return [
                'comment'      => $ev->comment !== '' ? $ev->comment : 'ไม่มีข้อเสนอแนะ',
                'score'        => round($ev->score, 1),
                'counter_code' => $subInfo ? 'เคาน์เตอร์ ' . $subInfo['code'] : ($ev->counter_id ? 'เคาน์เตอร์ ' . $ev->counter_id : 'ทั่วไป'),
                'staff_name'   => $staffName,
                'time'         => $timeStr
            ];
        });

        return view('dashboard.index', compact(
            'period', 'avgScore', 'totalEvalCount', 'staffCheckinCount', 'newCommentsCount',
            'trendLabels', 'trendScores', 'starCounts', 'maxStarCount', 'counterStatusRows',
            'activeCheckinStaffs', 'highestStaff', 'lowestStaff', 'counterAvgScores', 'recentComments'
        ));
    }

    // [แก้ไข ระดับ 4] ลบเมธอด getMyReviews() ทั้งเมธอด (ประมาณ 170 บรรทัด) ออก
    // เหตุผล: 1) ไม่มี route ไหนเรียกใช้ (หน้า Check-in เคยมีสคริปต์เรียก /api/my-reviews แต่ได้ 404
    //            จนต้องเขียนโค้ดดัก fetch ไว้ ซึ่งลบไปแล้วใน [ระดับ 3 ข้อ 17])
    //         2) ถ้าวันหนึ่งมีคนผูก route ให้โดยไม่ตั้งใจ จะเปิดให้ดึงรีวิวของพนักงานคนไหนก็ได้ผ่าน query string
    //         3) มีโค้ด checkout อัตโนมัติชุดที่ 3 ซ้ำกับ index() และ CheckinController
    // หน้า Check-in ขั้นที่ 4 ดึงรีวิวผ่าน CheckinController (renderStep4View / status) อยู่แล้ว
}