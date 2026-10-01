<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
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
        // 1. ตัวกรองช่วงเวลา (วันนี้ / 7 วันล่าสุด / เดือนนี้ / ทั้งหมด)
        $period = $request->query('period', 'today');
        $today = Carbon::today();
        $todayStr = $today->format('Y-m-d');

        // ตรวจสอบตารางประเมินในฐานข้อมูลจริง
        $evalTable = null;
        foreach (['evaluation', 'evaluations'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                $evalTable = $tbl;
                break;
            }
        }

        $scoreCol = 'score';
        $commentCol = 'comment';
        $dateCol = 'created_at';
        $subIdCol = 'counter_sub_id';
        $staffIdCol = 'staff_id';

        if ($evalTable) {
            $cols = Schema::getColumnListing($evalTable);
            foreach (['score', 'rating', 'point', 'eval_score', 'satisfaction_score'] as $c) {
                if (in_array($c, $cols)) { $scoreCol = $c; break; }
            }
            foreach (['comment', 'feedback', 'suggestion', 'note', 'detail', 'eval_comment'] as $c) {
                if (in_array($c, $cols)) { $commentCol = $c; break; }
            }
            foreach (['created_at', 'eval_date', 'evaluation_date', 'date'] as $c) {
                if (in_array($c, $cols)) { $dateCol = $c; break; }
            }
            foreach (['counter_sub_id', 'countersub_id', 'sub_id'] as $c) {
                if (in_array($c, $cols)) { $subIdCol = $c; break; }
            }
            foreach (['staff_id', 'user_id', 'emp_id'] as $c) {
                if (in_array($c, $cols)) { $staffIdCol = $c; break; }
            }
        }

        // ดึงข้อมูลการประเมินตามตัวกรอง
        $allEvals = collect();
        $filteredEvals = collect();

        if ($evalTable) {
            $allEvals = collect(DB::table($evalTable)->get());

            $filteredEvals = $allEvals->filter(function ($item) use ($period, $dateCol, $today) {
                if ($period === 'all' || empty($item->{$dateCol})) return true;
                $d = Carbon::parse($item->{$dateCol});
                if ($period === 'today') return $d->isSameDay($today);
                if ($period === 'week')  return $d->between($today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay());
                if ($period === 'month') return $d->isSameMonth($today) && $d->isSameYear($today);
                return true;
            });
        }

        // ==========================================
        // 2. ข้อมูลการ์ดสรุป 4 ใบด้านบน
        // ==========================================
        $avgScore = $filteredEvals->count() > 0
            ? round($filteredEvals->avg(fn($e) => (float) ($e->{$scoreCol} ?? 0)), 1)
            : 0.0;

        $totalEvalCount = $filteredEvals->count();

        // ดึงตารางปฏิบัติงานของวันนี้
        $todaySchedules = Schedule::with('staff')
            ->where('schedule_date', $todayStr)
            ->orderBy('start_time', 'asc')
            ->get();

        $checkinSchedules = $todaySchedules->filter(fn($s) => strtolower($s->status ?? '') === 'checkin');
        $staffCheckinCount = $checkinSchedules->pluck('staff_id')->unique()->count();

        $newCommentsList = $filteredEvals->filter(function ($e) use ($commentCol) {
            return isset($e->{$commentCol}) && trim((string) $e->{$commentCol}) !== '';
        })->sortByDesc($dateCol)->values();

        $newCommentsCount = $newCommentsList->count();

        // ==========================================
        // 3. กราฟแนวโน้มคะแนนรายวัน 7 วันล่าสุด
        // ==========================================
        $trendLabels = [];
        $trendScores = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i);
            $dStr = $d->format('Y-m-d');
            $trendLabels[] = $i === 0 ? 'วันนี้' : ($i === 6 ? $d->translatedFormat('j M') : $d->format('j'));

            $dayEvals = $allEvals->filter(function ($e) use ($dateCol, $dStr) {
                if (empty($e->{$dateCol})) return false;
                return Carbon::parse($e->{$dateCol})->format('Y-m-d') === $dStr;
            });

            $trendScores[] = $dayEvals->count() > 0
                ? round($dayEvals->avg(fn($e) => (float) ($e->{$scoreCol} ?? 0)), 2)
                : 0;
        }

        // ==========================================
        // 4. การกระจายคะแนน (5 ดาว - 1 ดาว)
        // ==========================================
        $starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($filteredEvals as $ev) {
            $s = (int) round((float) ($ev->{$scoreCol} ?? 0));
            if (isset($starCounts[$s])) {
                $starCounts[$s]++;
            }
        }
        $maxStarCount = max(max($starCounts), 1);

        // ==========================================
        // 5. สถานะเคาน์เตอร์ทั้งหมด ณ ปัจจุบัน & Staff ที่กำลัง Check-in
        // ==========================================
        $counters = Counter::with('countersubs')->where('is_active', true)->get();
        $staffMap = Staff::all()->keyBy('staff_id');
        $subCounterMap = [];

        $counterStatusRows = [];
        $activeCheckinStaffs = [];
        $counterAvgScores = [];
        $staffPerformance = [];
        $nowTime = Carbon::now()->format('H:i:s');

        foreach ($counters as $counter) {
            $subIndex = 1;
            $cEvals = collect();

            foreach ($counter->countersubs as $sub) {
                $labelCode = $counter->counter_id . '.' . $subIndex++;
                $subCounterMap[$sub->counter_sub_id] = [
                    'code' => $labelCode,
                    'location' => $counter->counter_location,
                    'counter_id' => $counter->counter_id
                ];

                $subSchedules = $todaySchedules->where('counter_sub_id', $sub->counter_sub_id);

                // หา Slot ที่กำลังทำงานอยู่ หรือ Slot ถัดไปของวันนี้
                $activeSch = $subSchedules->first(fn($s) => strtolower($s->status ?? '') === 'checkin');
                if (!$activeSch) {
                    $activeSch = $subSchedules->first(fn($s) => $s->start_time <= $nowTime && $s->end_time >= $nowTime && strtolower($s->status ?? '') !== 'sick');
                }
                if (!$activeSch) {
                    $activeSch = $subSchedules->first();
                }

                // คะแนนประเมินของจุดบริการย่อยนี้
                $subEvals = $filteredEvals->where($subIdCol, $sub->counter_sub_id);
                $cEvals = $cEvals->merge($subEvals);

                $subScore = $subEvals->count() > 0
                    ? round($subEvals->avg(fn($e) => (float) ($e->{$scoreCol} ?? 0)), 1)
                    : null;

                // กำหนดสถานะ (Active / Available / Close)
                $statusBadge = 'Close';
                $staffName = null;
                $timeRange = '—';

                if ($activeSch) {
                    $timeRange = substr($activeSch->start_time, 0, 5) . '–' . substr($activeSch->end_time, 0, 5);
                    $rawSt = strtolower($activeSch->status ?? 'empty');

                    if ($rawSt === 'checkin') {
                        $statusBadge = 'Active';
                        $staffName = $activeSch->staff->staff_name ?? 'ไม่ระบุ';
                        $activeCheckinStaffs[] = [
                            'staff_name'   => $staffName,
                            'counter_code' => $labelCode,
                            'time_range'   => $timeRange
                        ];
                    } elseif ($rawSt === 'close') {
                        $statusBadge = 'Close';
                    } elseif ($rawSt === 'sick') {
                        $statusBadge = 'Available';
                    } else {
                        if ($activeSch->staff) {
                            $staffName = $activeSch->staff->staff_name;
                            $statusBadge = ($activeSch->start_time <= $nowTime && $activeSch->end_time >= $nowTime) ? 'Active' : 'Available';
                        } else {
                            $statusBadge = 'Available';
                        }
                    }

                    // เก็บสถิติคะแนนรายบุคคลเพื่อหา คะแนนสูงสุด & ต้องปรับปรุง
                    if ($activeSch->staff_id && $subScore !== null) {
                        $sid = $activeSch->staff_id;
                        if (!isset($staffPerformance[$sid])) {
                            $staffPerformance[$sid] = [
                                'name'         => $activeSch->staff->staff_name ?? 'ไม่ระบุ',
                                'counter_code' => $labelCode,
                                'scores'       => []
                            ];
                        }
                        $staffPerformance[$sid]['scores'][] = $subScore;
                    }
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

            $cAvg = $cEvals->count() > 0
                ? round($cEvals->avg(fn($e) => (float) ($e->{$scoreCol} ?? 0)), 1)
                : 0.0;

            $counterAvgScores[] = [
                'counter_id' => $counter->counter_id,
                'location'   => $counter->counter_location,
                'score'      => $cAvg,
                'count'      => $cEvals->count()
            ];
        }

        // ถ้าตาราง evaluation มีคอลัมน์ staff_id โดยตรง ให้นำมาคำนวณคะแนนรายบุคคลด้วย
        foreach ($filteredEvals as $ev) {
            if (!empty($ev->{$staffIdCol}) && isset($staffMap[$ev->{$staffIdCol}])) {
                $sid = $ev->{$staffIdCol};
                $st = $staffMap[$sid];
                $cCode = isset($ev->{$subIdCol}, $subCounterMap[$ev->{$subIdCol}]) ? $subCounterMap[$ev->{$subIdCol}]['code'] : '-';
                if (!isset($staffPerformance[$sid])) {
                    $staffPerformance[$sid] = [
                        'name'         => $st->staff_name,
                        'counter_code' => $cCode,
                        'scores'       => []
                    ];
                }
                $staffPerformance[$sid]['scores'][] = (float) ($ev->{$scoreCol} ?? 0);
            }
        }

        $rankedStaffs = collect($staffPerformance)->map(function ($item) {
            return [
                'name'         => $item['name'],
                'counter_code' => $item['counter_code'],
                'avg_score'    => round(collect($item['scores'])->avg(), 1)
            ];
        })->sortByDesc('avg_score')->values();

        $highestStaff = $rankedStaffs->first();
        $lowestStaff  = $rankedStaffs->count() > 1 ? $rankedStaffs->last() : null;

        // ==========================================
        // 6. รายการความคิดเห็นล่าสุด
        // ==========================================
        $recentComments = $newCommentsList->take(6)->map(function ($ev) use ($commentCol, $scoreCol, $dateCol, $subIdCol, $staffIdCol, $subCounterMap, $staffMap) {
            $subInfo = isset($ev->{$subIdCol}, $subCounterMap[$ev->{$subIdCol}]) ? $subCounterMap[$ev->{$subIdCol}] : null;
            $staffName = isset($ev->{$staffIdCol}, $staffMap[$ev->{$staffIdCol}]) ? $staffMap[$ev->{$staffIdCol}]->staff_name : null;

            return [
                'comment'      => $ev->{$commentCol},
                'score'        => round((float) ($ev->{$scoreCol} ?? 0), 1),
                'counter_code' => $subInfo ? 'เคาน์เตอร์ ' . $subInfo['code'] : 'ทั่วไป',
                'staff_name'   => $staffName,
                'time'         => !empty($ev->{$dateCol}) ? Carbon::parse($ev->{$dateCol})->diffForHumans() : ''
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