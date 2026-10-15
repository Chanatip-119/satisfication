<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckinController extends Controller
{
    private $libraryName = 'สำนักหอสมุด มหาวิทยาลัย';

    private function renderStep4View($checkin, $counterId)
    {
        $nowBkk = Carbon::now('Asia/Bangkok');
        $staff = Staff::where('staff_id', $checkin->staff_id)->first();
        
        $schedule = $checkin->schedule_id
            ? Schedule::where('schedule_id', $checkin->schedule_id)->first()
            : Schedule::where('counter_sub_id', $counterId)
                ->where('staff_id', $checkin->staff_id)
                ->where('schedule_date', $nowBkk->toDateString())
                ->first();

        $counterSub = CounterSub::with('counter')->where('counter_sub_id', $counterId)->first();
        $counterName = $counterSub ? 'เคาน์เตอร์ ' . $counterSub->counter_id . '.' . $counterSub->counter_sub_id : 'เคาน์เตอร์ ' . $counterId;
        $locationName = ($counterSub && $counterSub->counter) ? $counterSub->counter->counter_location : 'ไม่ระบุตำแหน่ง';

        $counterObj = (object)[
            'name'     => $counterName,
            'location' => $locationName,
            'time_end' => $schedule ? substr($schedule->end_time, 0, 5) : '-',
        ];

        $staffObj = (object)[
            'name' => $staff ? $staff->staff_name : '-',
        ];

        $showWarningBar = false;
        if ($schedule) {
            $endStr = date('H:i:s', strtotime($schedule->end_time));
            $timeEnd = Carbon::parse($schedule->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
            $showWarningBar = $nowBkk->diffInMinutes($timeEnd, false) <= 15 && $nowBkk->isBefore($timeEnd);
        }

        $evaluations = collect([]);
        if (class_exists(Evaluation::class) && $staff) {
            $evaluations = Evaluation::whereIn('checkin_id', function ($query) use ($staff) {
                $query->select('checkin_id')->from('checkin')->where('staff_id', $staff->staff_id);
            })->orderByDesc('evaluation_id')->get();
        }

        $avgRating = $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '0.0';
        $totalEval = $evaluations->count();
        $negEval   = $evaluations->where('rating', '<=', 2)->count();

        $reviewStats = (object)[
            'average'                 => $avgRating,
            'total'                   => $totalEval,
            'negative'                => $negEval,
            'current_counter_total'   => $totalEval,
            'current_counter_average' => $avgRating,
        ];

        $checkinFallbackTime = $checkin->checkin_at ?? $nowBkk;

        $reviewsList = $evaluations->map(function ($eval) use ($checkinFallbackTime) {
            // ระบบสแกนหาเวลาประเมินที่แท้จริงจากฐานข้อมูล
            $rawArray = method_exists($eval, 'toArray') ? $eval->toArray() : json_decode(json_encode($eval), true);
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
            
            $resolvedDate = $evalDateRaw ? Carbon::parse($evalDateRaw) : Carbon::parse($checkinFallbackTime);

            return (object)[
                'date'    => $resolvedDate->translatedFormat('j M.'),
                'time'    => $resolvedDate->format('H:i'),
                'rating'  => (int) $eval->rating,
                'comment' => trim($eval->comment) !== '' ? trim($eval->comment) : 'ไม่มีข้อเสนอแนะ'
            ];
        });

        return view('checkin.index', [
            'step'               => 4,
            'library_name'       => $this->libraryName,
            'counter'            => $counterObj,
            'staff'              => $staffObj,
            'checkin_time'       => Carbon::parse($checkin->checkin_at)->format('H:i'),
            'current_checkin_id' => $checkin->checkin_id,
            'show_warning_bar'   => $showWarningBar,
            'review_stats'       => $reviewStats,
            'review_filters'     => collect([]),
            'reviews'            => $reviewsList
        ]);
    }

    public function index(Request $request)
    {
        $nowBkk = Carbon::now('Asia/Bangkok');
        $todayAuto = $nowBkk->format('Y-m-d');

        try {
            if (class_exists(Checkin::class)) {
                $expiredCheckins = Checkin::whereNull('checkout_at')->get();
                foreach ($expiredCheckins as $ck) {
                    $sch = $ck->schedule_id ? Schedule::find($ck->schedule_id) : null;
                    if ($sch) {
                        $endStr = date('H:i:s', strtotime($sch->end_time));
                        $schEndDT = Carbon::parse($sch->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
                        
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
                }
            }

            $schedulesToClear = Schedule::whereDate('schedule_date', '<=', $todayAuto)
                                        ->whereNotIn('status', ['empty', 'close', 'sick'])
                                        ->get();
            foreach ($schedulesToClear as $stc) {
                $endStr = date('H:i:s', strtotime($stc->end_time));
                $schEndDT = Carbon::parse($stc->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
                if ($nowBkk->greaterThanOrEqualTo($schEndDT)) {
                    $stc->status = 'empty';
                    $stc->save();
                }
            }
        } catch (\Exception $e) {}

        if ($request->has('active_checkin_id')) {
            $activeCheckin = Checkin::where('checkin_id', $request->active_checkin_id)
                ->whereNull('checkout_at')
                ->first();

            if ($activeCheckin) {
                $cId = $request->query('c_id');
                if (!$cId && $activeCheckin->schedule_id) {
                    $sch = Schedule::where('schedule_id', $activeCheckin->schedule_id)->first();
                    $cId = $sch ? $sch->counter_sub_id : 1;
                }
                return $this->renderStep4View($activeCheckin, $cId ?: 1);
            }
        }

        if ($request->has('counter_id')) {
            return view('checkin.index', [
                'step'                => 2,
                'library_name'        => $this->libraryName,
                'selected_counter_id' => $request->counter_id
            ]);
        }

        $countersList = collect();
        $counters = Counter::with('countersubs')->where('is_active', true)->get();

        foreach ($counters as $counter) {
            foreach ($counter->countersubs as $sub) {
                $schedules = Schedule::where('counter_sub_id', $sub->counter_sub_id)
                    ->where('schedule_date', $todayAuto)
                    ->orderBy('start_time', 'asc')
                    ->get();

                $scheduleIds = $schedules->pluck('schedule_id');
                $hasActiveCheckin = false;
                $activeCk = null;

                if ($scheduleIds->isNotEmpty()) {
                    $activeCk = Checkin::whereIn('schedule_id', $scheduleIds)
                        ->whereNull('checkout_at')
                        ->first();
                    if ($activeCk) {
                        $hasActiveCheckin = true;
                    }
                }

                $validSchedule = $schedules->first(function ($sch) use ($nowBkk) {
                    $status = strtolower(trim($sch->status ?? 'empty'));
                    $endStr = date('H:i:s', strtotime($sch->end_time));
                    $schEndDT = Carbon::parse($sch->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
                    return $nowBkk->isBefore($schEndDT) && !in_array($status, ['close', 'sick']);
                });

                if (!$hasActiveCheckin && !$validSchedule) {
                    continue; 
                }

                $displaySchedule = null;
                if ($hasActiveCheckin && $activeCk) {
                    $displaySchedule = $schedules->firstWhere('schedule_id', $activeCk->schedule_id);
                }
                if (!$displaySchedule) {
                    $displaySchedule = $validSchedule ?? $schedules->last();
                }

                $countersList->push((object)[
                    'id'           => $sub->counter_sub_id,
                    'name'         => 'เคาน์เตอร์ ' . $counter->counter_id . '.' . $sub->counter_sub_id,
                    'time_start'   => $displaySchedule ? substr($displaySchedule->start_time, 0, 5) : '-',
                    'time_end'     => $displaySchedule ? substr($displaySchedule->end_time, 0, 5) : '-',
                    'is_available' => !$hasActiveCheckin,
                ]);
            }
        }

        return view('checkin.index', [
            'step'         => 1,
            'library_name' => $this->libraryName,
            'counters'     => $countersList
        ]);
    }

    public function step1(Request $request)
    {
        $request->validate(['counter_id' => 'required']);
        return redirect()->route('checkin.index', ['counter_id' => $request->counter_id]);
    }

    public function step2(Request $request)
    {
        $request->validate([
            'counter_id' => 'required',
            'pin'        => 'required|size:4'
        ]);

        $staff = Staff::where('staff_pincode', $request->pin)->first();

        if (!$staff) {
            return back()->withErrors(['pin' => 'PIN ไม่ถูกต้อง']);
        }

        $nowBkk = Carbon::now('Asia/Bangkok');
        $todayAuto = $nowBkk->format('Y-m-d');
        
        $schedules = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('staff_id', $staff->staff_id)
            ->where('schedule_date', $todayAuto)
            ->whereNotIn('status', ['close', 'sick'])
            ->get();
            
        $schedule = $schedules->first(function ($sch) use ($nowBkk) {
            $endStr = date('H:i:s', strtotime($sch->end_time));
            $schEndDT = Carbon::parse($sch->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
            return $nowBkk->isBefore($schEndDT);
        });

        $allSchedulesToday = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('schedule_date', $todayAuto)
            ->pluck('schedule_id');

        $activeCheckin = null;
        if ($allSchedulesToday->isNotEmpty()) {
            $activeCheckin = Checkin::whereIn('schedule_id', $allSchedulesToday)
                ->whereNull('checkout_at')
                ->first();
        }

        if (!$schedule && !($activeCheckin && $activeCheckin->staff_id === $staff->staff_id)) {
            return back()->withErrors(['pin' => 'ไม่สามารถ Check-in ได้ เนื่องจากหมดเวลาปฏิบัติงานแล้ว หรือไม่มีตารางเวรในขณะนี้']);
        }

        $currentStaffName = null;
        if ($activeCheckin) {
            $oldStaff = Staff::where('staff_id', $activeCheckin->staff_id)->first();
            $currentStaffName = $oldStaff ? $oldStaff->staff_name : 'บุคลากรท่านอื่น';
        }

        $counterSub = CounterSub::with('counter')->where('counter_sub_id', $request->counter_id)->first();
        $counterName = $counterSub ? 'เคาน์เตอร์ ' . $counterSub->counter_id . '.' . $counterSub->counter_sub_id : 'เคาน์เตอร์ ' . $request->counter_id;

        $counterObj = (object)[
            'id'                 => $request->counter_id,
            'name'               => $counterName,
            'time_start'         => $schedule ? substr($schedule->start_time, 0, 5) : '-',
            'time_end'           => $schedule ? substr($schedule->end_time, 0, 5) : '-',
            'current_staff_name' => $currentStaffName
        ];

        $staffObj = (object)[
            'id'   => $staff->staff_id,
            'name' => $staff->staff_name,
            'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT)
        ];

        return view('checkin.index', [
            'step'         => 3,
            'library_name' => $this->libraryName,
            'counter'      => $counterObj,
            'staff'        => $staffObj
        ]);
    }

    public function step3(Request $request)
    {
        $request->validate([
            'counter_id' => 'required',
            'staff_id'   => 'required'
        ]);

        $nowBkk = Carbon::now('Asia/Bangkok');
        $todayAuto = $nowBkk->format('Y-m-d');

        $schedules = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('staff_id', $request->staff_id)
            ->where('schedule_date', $todayAuto)
            ->whereNotIn('status', ['close', 'sick'])
            ->get();
            
        $schedule = $schedules->first(function ($sch) use ($nowBkk) {
            $endStr = date('H:i:s', strtotime($sch->end_time));
            $schEndDT = Carbon::parse($sch->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
            return $nowBkk->isBefore($schEndDT);
        });

        $newCheckin = DB::transaction(function () use ($request, $schedule, $nowBkk, $todayAuto) {
            $allSchedulesToday = Schedule::where('counter_sub_id', $request->counter_id)
                ->where('schedule_date', $todayAuto)
                ->pluck('schedule_id');

            $existingSelfCheckin = Checkin::where('staff_id', $request->staff_id)
                ->whereNull('checkout_at')
                ->when($schedule, function ($q) use ($schedule) {
                    return $q->where('schedule_id', $schedule->schedule_id);
                })
                ->latest('checkin_id')
                ->first();

            $targetMainCheckinId = $existingSelfCheckin ? $existingSelfCheckin->checkin_id : null;

            if ($existingSelfCheckin) {
                $duplicateIds = Checkin::where('staff_id', $request->staff_id)
                    ->where('checkin_id', '!=', $targetMainCheckinId)
                    ->where('is_kicked', 1)
                    ->whereDate('checkin_at', $todayAuto)
                    ->pluck('checkin_id');

                if ($duplicateIds->isNotEmpty()) {
                    if (class_exists(Evaluation::class)) {
                        Evaluation::whereIn('checkin_id', $duplicateIds)
                            ->update(['checkin_id' => $targetMainCheckinId]);
                    }
                    Checkin::whereIn('checkin_id', $duplicateIds)->delete();
                }

                if ($schedule && strtolower($schedule->status ?? '') !== 'checkin') {
                    $schedule->status = 'checkin';
                    $schedule->save();
                }

                return $existingSelfCheckin;
            }

            if ($allSchedulesToday->isNotEmpty()) {
                $activeOtherCheckins = Checkin::whereIn('schedule_id', $allSchedulesToday)
                    ->where('staff_id', '!=', $request->staff_id)
                    ->whereNull('checkout_at')
                    ->get();

                foreach ($activeOtherCheckins as $activeCheckin) {
                    $activeCheckin->checkout_at = $nowBkk;
                    $activeCheckin->duration_min = $nowBkk->diffInMinutes(Carbon::parse($activeCheckin->checkin_at));
                    $activeCheckin->is_kicked = 1;
                    $activeCheckin->save();

                    if ($activeCheckin->schedule_id) {
                        Schedule::where('schedule_id', $activeCheckin->schedule_id)
                            ->where('status', 'checkin')
                            ->update(['status' => 'empty']);
                    }
                }
            }

            $checkin = new Checkin();
            $checkin->schedule_id = $schedule ? $schedule->schedule_id : null;
            $checkin->staff_id = $request->staff_id;
            $checkin->checkin_at = $nowBkk;
            $checkin->is_substitute = 0;
            $checkin->is_kicked = 0;
            $checkin->save();

            $oldDupIds = Checkin::where('staff_id', $request->staff_id)
                ->where('checkin_id', '!=', $checkin->checkin_id)
                ->where('is_kicked', 1)
                ->whereDate('checkin_at', $todayAuto)
                ->pluck('checkin_id');

            if ($oldDupIds->isNotEmpty()) {
                if (class_exists(Evaluation::class)) {
                    Evaluation::whereIn('checkin_id', $oldDupIds)
                        ->update(['checkin_id' => $checkin->checkin_id]);
                }
                Checkin::whereIn('checkin_id', $oldDupIds)->delete();
            }

            if ($schedule) {
                $schedule->status = 'checkin';
                $schedule->save();
            }

            return $checkin;
        });

        return redirect()->route('checkin.index', [
            'active_checkin_id' => $newCheckin->checkin_id,
            'c_id'              => $request->counter_id
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate(['checkin_id' => 'required']);

        $nowBkk = Carbon::now('Asia/Bangkok');
        $checkin = Checkin::where('checkin_id', $request->checkin_id)->first();
        if ($checkin) {
            $checkin->checkout_at = $nowBkk;
            $checkin->duration_min = $nowBkk->diffInMinutes(Carbon::parse($checkin->checkin_at));
            $checkin->is_kicked = 0;
            $checkin->save();

            if ($checkin->schedule_id) {
                Schedule::where('schedule_id', $checkin->schedule_id)
                    ->update(['status' => 'empty']);
            }
        }

        return redirect()->route('checkin.index');
    }
}