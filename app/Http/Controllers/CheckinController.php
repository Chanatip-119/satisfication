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
use Illuminate\Support\Facades\RateLimiter; // [แก้ไข ระดับ 1 ข้อ 4] ใช้จำกัดการเดา PIN
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
        // [แก้ไข ระดับ 4] ชื่อเคาน์เตอร์รูปแบบเดียวกันทั้งระบบ (เช่น "เคาน์เตอร์ 8.1")
        // เดิม: 'เคาน์เตอร์ ' . counter_id . '.' . counter_sub_id (เช่น "1.7" ซึ่งเลขหลังจุดเป็น ID ของทั้งระบบ)
        $counterName = CounterSub::labelFor($counterId);
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
            // ===== [แก้ไข ระดับ 1 ข้อ 7] เปิดหน้าขั้นที่ 4 ได้เฉพาะเครื่องที่ใช้ Check-in นั้นเท่านั้น =====
            // เดิม: Checkin::where('checkin_id', $request->active_checkin_id)->whereNull('checkout_at')->first();
            // ปัญหา: เปลี่ยนเลขใน URL ก็เห็นรีวิวของพนักงานคนอื่น และกด Check-out แทนคนนั้นได้
            $kioskCheckinId = (int) $request->session()->get('kiosk_checkin_id', 0);
            $activeCheckin = ($kioskCheckinId > 0 && (int) $request->active_checkin_id === $kioskCheckinId)
                ? Checkin::where('checkin_id', $kioskCheckinId)->whereNull('checkout_at')->first()
                : null;

            // Check-in ของเครื่องนี้จบไปแล้ว (หมดเวลาหรือถูกคนอื่นรับช่วงต่อ) ให้ล้างออกจาก session
            if (!$activeCheckin && $kioskCheckinId > 0 && (int) $request->active_checkin_id === $kioskCheckinId) {
                $request->session()->forget('kiosk_checkin_id');
            }
            // ===== [จบ ข้อ 7: index] =====

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
                    // [แก้ไข ระดับ 4] เดิม: 'เคาน์เตอร์ ' . $counter->counter_id . '.' . $sub->counter_sub_id
                    'name'         => CounterSub::labelFor($sub->counter_sub_id),
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
        $request->session()->forget('checkin_confirmation');
        $request->validate(['counter_id' => 'required']);
        return redirect()->route('checkin.index', ['counter_id' => $request->counter_id]);
    }

    public function step2(Request $request)
    {
        $request->session()->forget('checkin_confirmation');
        $request->validate([
            'counter_id' => 'required|integer|exists:counter_sub,counter_sub_id',
            'pin'        => 'required|size:4'
        ]);

        $counterSub = CounterSub::with('counter')
            ->where('counter_sub_id', $request->counter_id)
            ->where('is_active', true)
            ->whereHas('counter', fn ($query) => $query->where('is_active', true))
            ->first();

        if (!$counterSub) {
            return redirect()->route('checkin.index')
                ->withErrors(['counter_id' => 'ไม่พบเคาน์เตอร์ที่เปิดให้บริการ']);
        }

        // ===== [แก้ไข ระดับ 1 ข้อ 4] จำกัดการเดา PIN: ผิดได้ 10 ครั้งต่อ IP ภายใน 5 นาที =====
        // นับเฉพาะครั้งที่ผิด เพราะแท็บเล็ตหลายเครื่องในห้องสมุดอาจใช้ IP เดียวกัน
        // ตั้งใจ "ไม่ล้างตัวนับเมื่อกรอกถูก" เพราะคนที่รู้ PIN ตัวเองจะใช้ล้างตัวนับระหว่างเดา PIN คนอื่นได้
        $pinThrottleKey = 'checkin-pin:' . $request->ip();

        if (RateLimiter::tooManyAttempts($pinThrottleKey, 10)) {
            $minutes = ceil(max(RateLimiter::availableIn($pinThrottleKey), 60) / 60);
            return back()->withErrors(['pin' => "กรอก PIN ผิดหลายครั้งเกินไป กรุณารอประมาณ {$minutes} นาทีแล้วลองใหม่"]);
        }

        $staff = Staff::where('staff_pincode', $request->pin)->first();

        if (!$staff) {
            RateLimiter::hit($pinThrottleKey, 300);
            return back()->withErrors(['pin' => 'PIN ไม่ถูกต้อง']);
        }
        // ===== [จบ ข้อ 4: step2] =====

        $nowBkk = Carbon::now('Asia/Bangkok');
        $todayAuto = $nowBkk->format('Y-m-d');
        
        $schedules = Schedule::where('counter_sub_id', $counterSub->counter_sub_id)
            ->where('staff_id', $staff->staff_id)
            ->where('schedule_date', $todayAuto)
            ->whereNotIn('status', ['close', 'sick'])
            ->orderBy('start_time')
            ->get();
            
        $schedule = $schedules->first(function ($sch) use ($nowBkk) {
            $endStr = date('H:i:s', strtotime($sch->end_time));
            $schEndDT = Carbon::parse($sch->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
            return $nowBkk->isBefore($schEndDT);
        });

        $activeCheckin = Checkin::with(['schedule', 'staff'])
            ->whereNull('checkout_at')
            ->whereHas('schedule', fn ($query) => $query->where('counter_sub_id', $counterSub->counter_sub_id))
            ->orderByDesc('checkin_at')
            ->first();

        // ถ้าไม่มีตารางเวรของพนักงานคนใหม่ ให้ใช้ช่วงเวรของคนที่กำลังประจำเคาน์เตอร์เป็นช่วงที่รับช่วงต่อ
        $checkinSchedule = $schedule ?: ($activeCheckin ? $activeCheckin->schedule : null);

        if (!$checkinSchedule) {
            return back()->withErrors(['pin' => 'ไม่สามารถ Check-in ได้ เนื่องจากหมดเวลาปฏิบัติงานแล้ว หรือไม่มีตารางเวรในขณะนี้']);
        }

        $currentStaffName = null;
        if ($activeCheckin && (int) $activeCheckin->staff_id !== (int) $staff->staff_id) {
            $currentStaffName = $activeCheckin->staff?->staff_name ?? 'บุคลากรท่านอื่น';
        }

        // [แก้ไข ระดับ 4] เดิม: 'เคาน์เตอร์ ' . counter_id . '.' . counter_sub_id
        $counterName = CounterSub::labelFor($counterSub->counter_sub_id);

        // ผูกขั้นยืนยันกับ PIN และเคาน์เตอร์ที่เลือก ป้องกันการแก้ staff_id ในฟอร์มขั้นถัดไป
        $request->session()->put('checkin_confirmation', [
            'counter_id' => (int) $counterSub->counter_sub_id,
            'staff_id' => (int) $staff->staff_id,
            'schedule_id' => (int) $checkinSchedule->schedule_id,
            'expires_at' => $nowBkk->copy()->addMinutes(5)->timestamp,
        ]);

        $counterObj = (object)[
            'id'                 => $counterSub->counter_sub_id,
            'name'               => $counterName,
            'time_start'         => substr($checkinSchedule->start_time, 0, 5),
            'time_end'           => substr($checkinSchedule->end_time, 0, 5),
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
            'counter_id' => 'required|integer',
            'staff_id'   => 'required|integer'
        ]);

        $confirmation = $request->session()->pull('checkin_confirmation');
        $nowBkk = Carbon::now('Asia/Bangkok');

        if (
            !is_array($confirmation)
            || !isset($confirmation['counter_id'], $confirmation['staff_id'], $confirmation['schedule_id'], $confirmation['expires_at'])
            || (int) $confirmation['expires_at'] < $nowBkk->timestamp
            || (int) $confirmation['counter_id'] !== (int) $request->counter_id
            || (int) $confirmation['staff_id'] !== (int) $request->staff_id
        ) {
            return redirect()->route('checkin.index')
                ->withErrors(['pin' => 'กรุณากรอก PIN ใหม่เพื่อยืนยันการ Check-in']);
        }

        $counterId = (int) $confirmation['counter_id'];
        $staffId = (int) $confirmation['staff_id'];
        $scheduleId = (int) $confirmation['schedule_id'];
        $todayAuto = $nowBkk->format('Y-m-d');

        $newCheckin = DB::transaction(function () use ($counterId, $staffId, $scheduleId, $nowBkk, $todayAuto) {
            // ล็อกเคาน์เตอร์เพื่อไม่ให้การส่งเวรที่เกิดพร้อมกันสร้างผู้ปฏิบัติงานซ้อนกัน
            $counterSub = CounterSub::where('counter_sub_id', $counterId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            $schedule = Schedule::where('schedule_id', $scheduleId)
                ->where('counter_sub_id', $counterId)
                ->lockForUpdate()
                ->first();

            if (!$counterSub || !$schedule) {
                return null;
            }

            $endStr = date('H:i:s', strtotime($schedule->end_time));
            $scheduleEnd = Carbon::parse($schedule->schedule_date . ' ' . $endStr, 'Asia/Bangkok');
            $hasValidOwnSchedule = (int) $schedule->staff_id === $staffId
                && $schedule->schedule_date === $todayAuto
                && $nowBkk->isBefore($scheduleEnd)
                && !in_array(strtolower((string) $schedule->status), ['close', 'sick'], true);

            $activeCheckin = Checkin::whereNull('checkout_at')
                ->whereHas('schedule', fn ($query) => $query->where('counter_sub_id', $counterId))
                ->orderByDesc('checkin_at')
                ->lockForUpdate()
                ->first();

            $isTakingOverActiveShift = $activeCheckin
                && (int) $activeCheckin->schedule_id === $scheduleId
                && $nowBkk->isBefore($scheduleEnd);

            if (!$hasValidOwnSchedule && !$isTakingOverActiveShift) {
                return null;
            }

            $existingSelfCheckin = Checkin::where('staff_id', $staffId)
                ->whereNull('checkout_at')
                ->whereHas('schedule', fn ($query) => $query->where('counter_sub_id', $counterId))
                ->latest('checkin_id')
                ->lockForUpdate()
                ->first();

            if ($existingSelfCheckin) {
                // ===== [แก้ไข ระดับ 2 ข้อ 9] เลิกลบประวัติ Check-in และเลิกย้ายคะแนนประเมิน =====
                // เดิม: หา check-in ทั้งหมดของคนนี้ "ในวันนี้" ที่ is_kicked = 1 แล้ว
                //       1) ย้ายคะแนนประเมินของ check-in เหล่านั้นมาผูกกับ check-in ปัจจุบัน
                //       2) ลบ check-in เหล่านั้นทิ้ง
                // ปัญหา: - ไม่ได้กรองเคาน์เตอร์ จึงลบประวัติของกะอื่น / เคาน์เตอร์อื่นไปด้วย
                //        - การ checkout อัตโนมัติตอนหมดกะก็ตั้ง is_kicked = 1 กะที่ทำงานจบปกติจึงถูกลบด้วย
                //        - คะแนนประเมินถูกย้ายไปผูกกับกะที่ไม่ได้ให้บริการจริง รายงานจึงผิดคนผิดจุด
                // ใหม่: เก็บประวัติทุกแถวไว้ตามจริง แค่คืน check-in ที่ยังเปิดอยู่ของคนนี้กลับไป
                // ===== [จบ ข้อ 9: กรณี Check-in ซ้ำที่เดิม] =====

                if ($existingSelfCheckin->schedule_id) {
                    Schedule::where('schedule_id', $existingSelfCheckin->schedule_id)
                        ->update(['status' => 'checkin']);
                }

                return $existingSelfCheckin;
            }

            $substitutingForId = $activeCheckin && (int) $activeCheckin->staff_id !== $staffId
                ? $activeCheckin->staff_id
                : null;

            $activeOtherCheckins = Checkin::where('staff_id', '!=', $staffId)
                ->whereNull('checkout_at')
                ->whereHas('schedule', fn ($query) => $query->where('counter_sub_id', $counterId))
                ->lockForUpdate()
                ->get();

            foreach ($activeOtherCheckins as $activeOtherCheckin) {
                $activeOtherCheckin->checkout_at = $nowBkk;
                $activeOtherCheckin->duration_min = $nowBkk->diffInMinutes(Carbon::parse($activeOtherCheckin->checkin_at));
                $activeOtherCheckin->is_kicked = 1;
                $activeOtherCheckin->save();

                if ($activeOtherCheckin->schedule_id) {
                    Schedule::where('schedule_id', $activeOtherCheckin->schedule_id)
                        ->where('status', 'checkin')
                        ->update(['status' => 'empty']);
                }
            }

            $checkinSchedule = $schedule;
            if ($substitutingForId !== null && $activeCheckin->schedule_id) {
                $checkinSchedule = Schedule::where('schedule_id', $activeCheckin->schedule_id)
                    ->where('counter_sub_id', $counterId)
                    ->lockForUpdate()
                    ->first();

                if (!$checkinSchedule) {
                    return null;
                }

                // เปลี่ยนผู้รับผิดชอบของเวรที่กำลังถูกส่งต่อให้เป็นคนที่เช็กอินเข้ามาแทน
                $checkinSchedule->staff_id = $staffId;
                $checkinSchedule->status = 'checkin';
                $checkinSchedule->save();

                if ((int) $schedule->schedule_id !== (int) $checkinSchedule->schedule_id && $schedule->status === 'checkin') {
                    $schedule->status = 'empty';
                    $schedule->save();
                }
            }

            $checkin = new Checkin();
            $checkin->schedule_id = $checkinSchedule->schedule_id;
            $checkin->staff_id = $staffId;
            $checkin->checkin_at = $nowBkk;
            $checkin->is_substitute = $substitutingForId !== null;
            $checkin->substituting_for_id = $substitutingForId;
            $checkin->is_kicked = 0;
            $checkin->save();

            // [แก้ไข ระดับ 2 ข้อ 9] ลบโค้ดที่ลบประวัติ Check-in เก่าของคนนี้ในวันนี้ (is_kicked = 1)
            // และย้ายคะแนนประเมินมาผูกกับ check-in ใหม่ออกทั้งก้อน — เหตุผลเดียวกับด้านบน
            // เดิม: $oldDupIds = Checkin::where('staff_id', $staffId)->where('is_kicked', 1)->whereDate('checkin_at', วันนี้)...
            //       Evaluation::whereIn('checkin_id', $oldDupIds)->update([...]); Checkin::whereIn(...)->delete();

            if ($schedule) {
                $schedule->status = 'checkin';
                $schedule->save();
            }

            return $checkin;
        });

        if (!$newCheckin) {
            return redirect()->route('checkin.index')
                ->withErrors(['pin' => 'ตารางเวรหรือสถานะเคาน์เตอร์เปลี่ยนไปแล้ว กรุณาเลือกเคาน์เตอร์และกรอก PIN อีกครั้ง']);
        }

        // [แก้ไข ระดับ 1 ข้อ 7] ผูก Check-in นี้กับ session ของเครื่องที่ใช้ Check-in
        // ใช้ยืนยันสิทธิ์เปิดหน้าขั้นที่ 4 และการ Check-out (ดู index() และ checkout())
        $request->session()->put('kiosk_checkin_id', (int) $newCheckin->checkin_id);

        return redirect()->route('checkin.index', [
            'active_checkin_id' => $newCheckin->checkin_id,
            'c_id'              => $counterId
        ]);
    }

    public function checkout(Request $request)
    {
        // ===== [แก้ไข ระดับ 1 ข้อ 7] Check-out ได้เฉพาะจากเครื่องที่ใช้ Check-in เท่านั้น =====
        // เดิม: รับ checkin_id จากฟอร์มแล้ว checkout ทันที ใครส่งเลขของคนอื่นมาก็ checkout คนนั้นได้
        $request->validate(['checkin_id' => 'required|integer']);

        $kioskCheckinId = (int) $request->session()->get('kiosk_checkin_id', 0);
        if ($kioskCheckinId === 0 || (int) $request->checkin_id !== $kioskCheckinId) {
            return redirect()->route('checkin.index')
                ->withErrors(['pin' => 'ไม่สามารถ Check-out ได้ กรุณา Check-out จากเครื่องที่ใช้ Check-in']);
        }
        // ===== [จบ ข้อ 7: ตรวจสิทธิ์] =====

        $nowBkk = Carbon::now('Asia/Bangkok');
        // [แก้ไข ระดับ 1 ข้อ 7] ดึงเฉพาะ Check-in ที่ยังไม่ checkout (เดิมไม่กรอง ทำให้เขียนทับเวลา checkout เดิมได้)
        $checkin = Checkin::where('checkin_id', $kioskCheckinId)
            ->whereNull('checkout_at')
            ->first();

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

        // [แก้ไข ระดับ 1 ข้อ 7] Check-out แล้ว ล้างสิทธิ์ออกจาก session ของเครื่อง
        $request->session()->forget('kiosk_checkin_id');

        return redirect()->route('checkin.index');
    }

    // ===== [เพิ่มใหม่ ระดับ 3 ข้อ 17] API เช็คสถานะแบบเบาสำหรับหน้า Check-in ขั้นที่ 4 =====
    // เดิม: หน้าเว็บดึง /checkin ทั้งหน้าซ้ำทุก 4 วินาที ซึ่งทุกครั้งต้องวน checkout ทุกคน
    //       สแกนกะทั้งระบบ และโหลดคะแนนประเมินทั้งหมด ทำให้เซิร์ฟเวอร์หนักมาก
    // ใหม่: ตอบกลับ JSON เล็ก ๆ ของ "Check-in ของเครื่องนี้" เท่านั้น
    //       { active: bool, total: จำนวนรีวิว, negative: จำนวนรีวิวเชิงลบ, warning: ใกล้หมดเวลาหรือยัง }
    public function status(Request $request)
    {
        $kioskCheckinId = (int) $request->session()->get('kiosk_checkin_id', 0);

        $checkin = $kioskCheckinId > 0
            ? Checkin::with('schedule')->where('checkin_id', $kioskCheckinId)->whereNull('checkout_at')->first()
            : null;

        // ไม่มี Check-in ของเครื่องนี้ หรือถูกคนอื่นรับช่วงต่อไปแล้ว
        if (!$checkin) {
            return response()->json(['active' => false]);
        }

        $nowBkk = Carbon::now('Asia/Bangkok');
        $showWarning = false;

        if ($checkin->schedule) {
            $endStr = date('H:i:s', strtotime($checkin->schedule->end_time));
            $scheduleEnd = Carbon::parse($checkin->schedule->schedule_date . ' ' . $endStr, 'Asia/Bangkok');

            // หมดเวลากะแล้ว: checkout อัตโนมัติเฉพาะ Check-in นี้ (เงื่อนไขเดียวกับใน index())
            if ($nowBkk->greaterThanOrEqualTo($scheduleEnd)) {
                $checkin->checkout_at = $nowBkk;
                $checkin->duration_min = $nowBkk->diffInMinutes(Carbon::parse($checkin->checkin_at));
                $checkin->is_kicked = 1;
                $checkin->save();

                $checkin->schedule->status = 'empty';
                $checkin->schedule->save();

                $request->session()->forget('kiosk_checkin_id');
                return response()->json(['active' => false]);
            }

            // เงื่อนไขเดียวกับแถบแจ้งเตือนในหน้าขั้นที่ 4 (ภายใน 15 นาทีก่อนหมดเวลา)
            $showWarning = $nowBkk->diffInMinutes($scheduleEnd, false) <= 15;
        }

        // นับรีวิวของพนักงานคนนี้ด้วย query เดียว (ขอบเขตเดียวกับ renderStep4View)
        // rating เป็น enum ข้อความ จึง CAST เป็นตัวเลขก่อนเทียบ
        $stats = Evaluation::whereIn('checkin_id', function ($query) use ($checkin) {
                $query->select('checkin_id')->from('checkin')->where('staff_id', $checkin->staff_id);
            })
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN CAST(rating AS UNSIGNED) <= 2 THEN 1 ELSE 0 END), 0) as negative')
            ->first();

        return response()->json([
            'active'   => true,
            'total'    => (int) ($stats->total ?? 0),
            'negative' => (int) ($stats->negative ?? 0),
            'warning'  => $showWarning,
        ]);
    }
    // ===== [จบ ข้อ 17: status] =====
}