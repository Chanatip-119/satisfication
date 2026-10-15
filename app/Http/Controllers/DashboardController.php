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
    /**
     * ดึงข้อมูลและแสดงผลหน้า Dashboard หลัก
     */
    public function index(Request $request)
    {
        $period   = $request->query('period', 'today');
        $nowBkk   = Carbon::now('Asia/Bangkok');
        $todayStr = $nowBkk->format('Y-m-d');
        $nowTime  = $nowBkk->format('H:i:s');

        try {
            if (class_exists(Checkin::class)) {
                $expiredCheckins = Checkin::whereNull('checkout_at')->get();
                foreach ($expiredCheckins as $ck) {
                    try {
                        $sch = $ck->schedule_id ? Schedule::find($ck->schedule_id) : null;
                        
                        if (!$sch && $ck->staff_id) {
                            $sch = Schedule::where('staff_id', $ck->staff_id)
                                           ->whereDate('schedule_date', '<=', $todayStr)
                                           ->orderBy('schedule_date', 'desc')
                                           ->first();
                        }

                        if ($sch) {
                            $schDate = Carbon::parse($sch->schedule_date)->format('Y-m-d');
                            $endStr = Carbon::parse($sch->end_time)->format('H:i:s');
                            $schEndDT = Carbon::parse($schDate . ' ' . $endStr, 'Asia/Bangkok');
                            
                            if ($nowBkk->greaterThanOrEqualTo($schEndDT)) {
                                $ck->checkout_at = $nowBkk;
                                $checkinAt = $ck->checkin_at ? Carbon::parse($ck->checkin_at) : $schEndDT->copy()->subMinutes(30);
                                $ck->duration_min = $nowBkk->diffInMinutes($checkinAt);
                                $ck->is_kicked = 1;
                                $ck->save();
                                
                                $sch->status = 'empty';
                                $sch->save();
                            }
                        }
                    } catch (\Exception $e) { continue; }
                }
            }
            
            $schedulesToClear = Schedule::whereDate('schedule_date', '<=', $todayStr)
                                        ->whereNotIn('status', ['empty', 'close', 'sick'])
                                        ->get();
                                        
            foreach ($schedulesToClear as $stc) {
                try {
                    $schDate = Carbon::parse($stc->schedule_date)->format('Y-m-d');
                    $endStr = Carbon::parse($stc->end_time)->format('H:i:s');
                    $schEndDT = Carbon::parse($schDate . ' ' . $endStr, 'Asia/Bangkok');
                    
                    if ($nowBkk->greaterThanOrEqualTo($schEndDT)) {
                        $stc->status = 'empty';
                        $stc->save();
                    }
                } catch (\Exception $e) { continue; }
            }
        } catch (\Exception $e) {}

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
        for ($i = 6; $i >= 0; $i--) {
            $d = $nowBkk->copy()->subDays($i);
            $dStr = $d->format('Y-m-d');

            $trendLabels[] = $i === 0
                ? $d->translatedFormat('j M') . ' (วันนี้)'
                : $d->translatedFormat('j M');

            $dayEvals = $normalizedEvals->where('date_str', $dStr);
            $trendScores[] = $dayEvals->count() > 0 ? round($dayEvals->avg('score'), 2) : 0;
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

    /**
     * API สำหรับหน้า Check-in
     */
    public function getMyReviews(Request $request)
    {
        $staffId   = $request->query('staff_id');
        $staffName = trim((string) $request->query('staff_name', ''));
        $subCode   = trim((string) $request->query('counter_code', ''));

        $nowBkk   = Carbon::now('Asia/Bangkok');
        $todayStr = $nowBkk->format('Y-m-d');

        try {
            if (class_exists(Checkin::class)) {
                $expiredCheckins = Checkin::whereNull('checkout_at')->get();
                foreach ($expiredCheckins as $ck) {
                    try {
                        $sch = $ck->schedule_id ? Schedule::find($ck->schedule_id) : null;
                        if (!$sch && $ck->staff_id) {
                            $sch = Schedule::where('staff_id', $ck->staff_id)
                                           ->whereDate('schedule_date', '<=', $todayStr)
                                           ->orderBy('schedule_date', 'desc')
                                           ->first();
                        }

                        if ($sch) {
                            $schDate = Carbon::parse($sch->schedule_date)->format('Y-m-d');
                            $endStr = Carbon::parse($sch->end_time)->format('H:i:s');
                            $schEndDT = Carbon::parse($schDate . ' ' . $endStr, 'Asia/Bangkok');
                            
                            if ($nowBkk->greaterThanOrEqualTo($schEndDT)) {
                                $ck->checkout_at = $nowBkk;
                                $checkinAt = $ck->checkin_at ? Carbon::parse($ck->checkin_at) : $schEndDT->copy()->subMinutes(30);
                                $ck->duration_min = $nowBkk->diffInMinutes($checkinAt);
                                $ck->is_kicked = 1;
                                $ck->save();
                                
                                $sch->status = 'empty';
                                $sch->save();
                            }
                        }
                    } catch (\Exception $e) { continue; }
                }
            }
            
            $schedulesToClear = Schedule::whereDate('schedule_date', '<=', $todayStr)
                                        ->whereNotIn('status', ['empty', 'close', 'sick'])
                                        ->get();
            foreach ($schedulesToClear as $stc) {
                try {
                    $schDate = Carbon::parse($stc->schedule_date)->format('Y-m-d');
                    $endStr = Carbon::parse($stc->end_time)->format('H:i:s');
                    $schEndDT = Carbon::parse($schDate . ' ' . $endStr, 'Asia/Bangkok');
                    if ($nowBkk->greaterThanOrEqualTo($schEndDT)) {
                        $stc->status = 'empty';
                        $stc->save();
                    }
                } catch (\Exception $e) { continue; }
            }
        } catch (\Exception $e) {}

        $staff = null;
        if ($staffId) {
            $staff = Staff::find($staffId);
        } elseif ($staffName !== '') {
            $staff = Staff::where('staff_name', $staffName)->first();
        }
        if ($staff) {
            $staffId = $staff->staff_id;
        }

        $counters = Counter::with('countersubs')->where('is_active', true)->get();
        $subCounterMap = [];
        $codeToSubIdMap = [];

        foreach ($counters as $counter) {
            $sIdx = 1;
            foreach ($counter->countersubs as $sub) {
                $code = $counter->counter_id . '.' . $sIdx++;
                $subCounterMap[$sub->counter_sub_id] = [
                    'code'       => $code,
                    'location'   => $counter->counter_location,
                    'counter_id' => $counter->counter_id
                ];
                $codeToSubIdMap[$code] = $sub->counter_sub_id;
            }
        }

        $targetSubId = $codeToSubIdMap[$subCode] ?? null;

        $allSchedules = Schedule::with('staff')->orderBy('schedule_date', 'desc')->get();
        $allSchedulesMap = $allSchedules->keyBy('schedule_id');
        $todaySchedules = Schedule::with('staff')->where('schedule_date', $todayStr)->get();

        if (!$targetSubId && $staffId) {
            $mySch = $todaySchedules->where('staff_id', $staffId)->first()
                ?? $allSchedules->where('staff_id', $staffId)->first();
            if ($mySch) {
                $targetSubId = $mySch->counter_sub_id;
                $subCode = $subCounterMap[$targetSubId]['code'] ?? $subCode;
            }
        }

        $allCheckins = class_exists(Checkin::class) ? Checkin::all() : collect();
        $checkinMap = $allCheckins->keyBy('checkin_id');

        $evalTable = null;
        foreach (['evaluation', 'evaluations', 'satisfaction', 'satisfactions', 'review', 'reviews'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                $evalTable = $tbl;
                break;
            }
        }

        $myReviews = collect();

        if ($evalTable) {
            $rawEvals = DB::table($evalTable)->orderBy('evaluation_id', 'asc')->get();

            foreach ($rawEvals as $ev) {
                $scoreVal   = (float) ($ev->rating ?? $ev->score ?? 0);
                $commentVal = trim((string) ($ev->comment ?? ''));

                $linkedCheckin = ($ev->checkin_id && isset($checkinMap[$ev->checkin_id])) ? $checkinMap[$ev->checkin_id] : null;

                $resolvedStaffId    = $ev->staff_id ?? ($linkedCheckin ? $linkedCheckin->staff_id : null);
                $resolvedScheduleId = $ev->schedule_id ?? ($linkedCheckin ? $linkedCheckin->schedule_id : null);
                $resolvedSubId      = $ev->counter_sub_id ?? null;

                if (!$resolvedSubId && $resolvedScheduleId && isset($allSchedulesMap[$resolvedScheduleId])) {
                    $linkedSch = $allSchedulesMap[$resolvedScheduleId];
                    $resolvedSubId = $linkedSch->counter_sub_id;
                    if (!$resolvedStaffId) $resolvedStaffId = $linkedSch->staff_id;
                }

                if (!$resolvedSubId && $resolvedStaffId) {
                    $staffSch = $todaySchedules->where('staff_id', $resolvedStaffId)->first()
                        ?? $allSchedules->where('staff_id', $resolvedStaffId)->first();
                    if ($staffSch) {
                        $resolvedSubId = $staffSch->counter_sub_id;
                    }
                }

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

                $dateSource = $evalDateRaw ?: (($linkedCheckin && !empty($linkedCheckin->checkin_at)) ? $linkedCheckin->checkin_at : clone $nowBkk);
                $resolvedDate = Carbon::parse($dateSource);

                $isMatch = false;
                if ($staffId && $resolvedStaffId && (int) $resolvedStaffId === (int) $staffId) {
                    $isMatch = true;
                } elseif ($targetSubId && $resolvedSubId && (int) $resolvedSubId === (int) $targetSubId) {
                    $isMatch = true;
                }

                if ($isMatch) {
                    $timeDisplay = $resolvedDate->format('H:i');
                    $myReviews->push([
                        'score'       => (int) round($scoreVal),
                        'is_negative' => $scoreVal <= 2.0,
                        'comment'     => $commentVal !== '' ? $commentVal : 'ไม่มีข้อเสนอแนะ',
                        'date_time'   => $resolvedDate->translatedFormat('j M.') . ' - ' . $timeDisplay . ' น.'
                    ]);
                }
            }
        }

        $reversedReviews = $myReviews->reverse()->values();

        return response()->json([
            'ok'      => true,
            'reviews' => $reversedReviews
        ]);
    }
}