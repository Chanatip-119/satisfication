<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Counter;
use App\Models\Staff;
use App\Models\Checkin;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * ตรวจสอบจากฐานข้อมูลจริงว่า Slot ตารางปฏิบัติงานนี้มีการ Check-in อยู่หรือไม่
     */
    private function isScheduleCheckedIn($sch, $sub = null)
    {
        $rawStatus = strtolower(trim($sch->status ?? 'empty'));

        if (in_array($rawStatus, ['sick', 'close'])) {
            return false;
        }

        if (in_array($rawStatus, ['checkin', 'check-in', 'checked_in', 'active', 'working'])) {
            return true;
        }

        foreach (['check_in_time', 'checkin_time', 'checked_in_at', 'checkin_at', 'time_in'] as $inCol) {
            if (!empty($sch->{$inCol})) {
                $isCheckedOut = false;
                foreach (['check_out_time', 'checkout_time', 'checked_out_at', 'checkout_at', 'time_out'] as $outCol) {
                    if (!empty($sch->{$outCol})) {
                        $isCheckedOut = true;
                        break;
                    }
                }
                if (!$isCheckedOut) return true;
            }
        }

        // บังคับใช้ Timezone ไทยเพื่อความแม่นยำ 100%
        $todayStr = Carbon::now('Asia/Bangkok')->format('Y-m-d');
        $nowTime  = Carbon::now('Asia/Bangkok')->format('H:i:s');

        foreach (['checkin', 'checkins', 'check_in', 'check_ins', 'staff_checkin', 'staff_checkins', 'attendance', 'work_logs'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                $cols = Schema::getColumnListing($tbl);
                $q = DB::table($tbl);
                $matched = false;

                if (in_array('schedule_id', $cols)) {
                    $q->where('schedule_id', $sch->schedule_id);
                    $matched = true;
                } elseif (in_array('staff_id', $cols)) {
                    $q->where('staff_id', $sch->staff_id);
                    if (in_array('counter_sub_id', $cols)) {
                        $q->where('counter_sub_id', $sch->counter_sub_id);
                    }
                    foreach (['checkin_date', 'check_in_date', 'date', 'schedule_date', 'created_at'] as $dCol) {
                        if (in_array($dCol, $cols)) {
                            $q->whereDate($dCol, $sch->schedule_date);
                            break;
                        }
                    }
                    $matched = true;
                }

                if ($matched) {
                    foreach (['checkout_time', 'check_out_time', 'checked_out_at', 'check_out_at', 'time_out'] as $outCol) {
                        if (in_array($outCol, $cols)) {
                            $q->whereNull($outCol);
                        }
                    }
                    if (in_array('status', $cols)) {
                        $q->whereNotIn('status', ['checkout', 'check-out', 'checked_out', 'completed', 'close']);
                    }
                    if ($q->exists()) {
                        return true;
                    }
                }
            }
        }

        if ($sch->schedule_date === $todayStr && $sch->start_time <= $nowTime && $sch->end_time >= $nowTime) {
            if ($sub) {
                $subStatus = strtolower(trim($sub->status ?? $sub->sub_status ?? ''));
                if (in_array($subStatus, ['checkin', 'active', 'open', 'busy', 'working'])) {
                    return true;
                }
                if (isset($sub->is_checkin) && $sub->is_checkin) {
                    return true;
                }
                if (isset($sub->current_staff_id) && $sub->current_staff_id == $sch->staff_id) {
                    return true;
                }
            }
            if ($rawStatus === 'waiting') {
                return true;
            }
        }

        return false;
    }

    public function index(Request $request)
    {
        // ==========================================
        // ระบบ Auto-Close เปลี่ยนสีเทาอัตโนมัติเมื่อหมดเวลา (บังคับโซนเวลาไทย 'Asia/Bangkok')
        // ==========================================
        $nowAuto   = Carbon::now('Asia/Bangkok');
        $todayAuto = $nowAuto->format('Y-m-d');
        $timeAuto  = $nowAuto->format('H:i:s');

        if (class_exists(Checkin::class)) {
            $expiredCheckins = Checkin::whereNull('checkout_at')->get();
            foreach ($expiredCheckins as $ck) {
                $sch = null;
                if ($ck->schedule_id) {
                    $sch = Schedule::find($ck->schedule_id);
                }
                if ($sch) {
                    // คำนวณวันและเวลาที่สิ้นสุดของตารางเวรแบบแม่นยำ
                    $schEndDT = Carbon::parse($sch->schedule_date . ' ' . $sch->end_time, 'Asia/Bangkok');
                    // ถ้าเวลาปัจจุบัน >= เวลาหมดเวร ให้ Check-out และปิดเคาน์เตอร์ทันที!
                    if ($nowAuto->greaterThanOrEqualTo($schEndDT)) {
                        $ck->checkout_at = $nowAuto;
                        $ck->duration_min = $nowAuto->diffInMinutes(Carbon::parse($ck->checkin_at));
                        $ck->is_kicked = 1; // บันทึกว่าปิดอัตโนมัติ
                        $ck->save();
                        
                        $sch->status = 'empty';
                        $sch->save();
                    }
                }
            }
        }
        
        // เคลียร์คิวอื่นๆ ที่อาจค้างสถานะ checkin แต่เวลาจบไปแล้ว ให้เป็น empty (สีเทา)
        Schedule::whereIn('status', ['checkin', 'active'])
            ->where(function ($q) use ($todayAuto, $timeAuto) {
                $q->where('schedule_date', '<', $todayAuto)
                  ->orWhere(function ($q2) use ($todayAuto, $timeAuto) {
                      $q2->where('schedule_date', $todayAuto)
                         ->where('end_time', '<=', $timeAuto);
                  });
            })->update(['status' => 'empty']);
        // ==========================================

        $staffs = Staff::all();
        $weekOffset = (int) $request->query('week_offset', 0);
        $startOfWeek = Carbon::now('Asia/Bangkok')->addWeeks($weekOffset)->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();
        
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDays[] = $startOfWeek->copy()->addDays($i);
        }

        $weekLabel = $startOfWeek->translatedFormat('j') . ' - ' . $endOfWeek->translatedFormat('j F Y');

        $counters = Counter::with(['countersubs.schedules' => function ($query) use ($startOfWeek, $endOfWeek) {
            $query->with('staff')
                  ->whereBetween('schedule_date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                  ->orderBy('start_time', 'asc')
                  ->orderBy('schedule_id', 'asc'); 
        }])->where('is_active', true)->get();

        $subToCounterMap = [];
        foreach ($counters as $c) {
            foreach ($c->countersubs as $cs) {
                $subToCounterMap[$cs->counter_sub_id] = $c->counter_id;
            }
        }

        $primarySchedules = []; 
        $substitutesData = [];  
        $allSchedulesForOverlap = Schedule::all(); 

        foreach ($counters as $counter) {
            foreach ($counter->countersubs as $sub) {
                foreach ($sub->schedules as $schItem) {
                    if ($this->isScheduleCheckedIn($schItem, $sub)) {
                        $schItem->status = 'checkin';
                    }
                }

                $schedulesByDate = $sub->schedules->groupBy('schedule_date');
                
                foreach ($schedulesByDate as $date => $dailySchedules) {
                    $processedIds = []; 
                    
                    foreach ($dailySchedules as $sch) {
                        if (in_array($sch->schedule_id, $processedIds)) continue; 
                        
                        $primarySchedules[] = $sch;
                        $subsForThisPrimary = [];
                        
                        $pStart = Carbon::parse($sch->start_time);
                        $pEnd   = Carbon::parse($sch->end_time);

                        foreach ($dailySchedules as $potentialSub) {
                            if ($potentialSub->schedule_id == $sch->schedule_id) continue;
                            if (in_array($potentialSub->schedule_id, $processedIds)) continue;

                            $sStart = Carbon::parse($potentialSub->start_time);
                            $sEnd   = Carbon::parse($potentialSub->end_time);

                            if ($sStart->lt($pEnd) && $sEnd->gt($pStart)) {
                                if (strtolower($potentialSub->status ?? '') === 'checkin' && strtolower($sch->status ?? '') !== 'sick') {
                                    $sch->status = 'checkin';
                                }

                                $subsForThisPrimary[] = [
                                    'id'         => $potentialSub->schedule_id,
                                    'name'       => $potentialSub->staff ? $potentialSub->staff->staff_name : 'ไม่ระบุ',
                                    'staff_code' => $potentialSub->staff_id,
                                    'start'      => substr($potentialSub->start_time, 0, 5),
                                    'end'        => substr($potentialSub->end_time, 0, 5),
                                    'status'     => $potentialSub->status,
                                    'created_at' => $potentialSub->created_at ? Carbon::parse($potentialSub->created_at)->format('d/m/Y H:i') : ''
                                ];
                                $processedIds[] = $potentialSub->schedule_id; 
                            }
                        }
                        
                        $substitutesData[$sch->schedule_id] = json_encode($subsForThisPrimary);
                    }
                }
            }
        }

        $filteredSchedulesCollection = collect($primarySchedules);
        
        $staffAvailability = $staffs->map(function($staff) use ($allSchedulesForOverlap, $subToCounterMap) {
            return [
                'id' => $staff->staff_id,
                'name' => $staff->staff_name,
                'schedules' => $allSchedulesForOverlap->where('staff_id', $staff->staff_id)
                    ->filter(fn($s) => strtolower($s->status ?? '') !== 'sick')
                    ->map(function($s) use ($subToCounterMap) {
                        return [
                            'schedule_id'    => $s->schedule_id,
                            'date'           => $s->schedule_date,
                            'start'          => substr($s->start_time, 0, 5),
                            'end'            => substr($s->end_time, 0, 5),
                            'counter_sub_id' => $s->counter_sub_id,
                            'counter_id'     => $subToCounterMap[$s->counter_sub_id] ?? $s->counter_sub_id
                        ];
                    })->values()->toArray()
            ];
        });

        $schedules = Schedule::all();
        return view('schedules.index', compact('counters', 'staffs', 'weekDays', 'weekLabel', 'weekOffset', 'schedules', 'filteredSchedulesCollection', 'substitutesData', 'staffAvailability'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'counter_sub_id'      => 'nullable|integer', 
            'staff_id'            => 'required|integer', 
            'schedule_date'       => 'required|date',   
            'end_date'            => 'nullable|date|after_or_equal:schedule_date',
            'start_time'          => 'required',        
            'end_time'            => 'required|after:start_time',
            'status'              => 'nullable|string',
            'work_days'           => 'nullable|array',
            'substitute_staff_id' => 'nullable|integer'
        ]);

        if ($request->filled('substitute_staff_id') && $request->staff_id == $request->substitute_staff_id) {
            return redirect()->back()->withErrors(['บุคลากรสำรองต้องไม่ใช่คนเดียวกับบุคลากรหลักครับ']);
        }

        $startDate = Carbon::parse($request->schedule_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy();
        $selectedDays = $request->work_days ?? [$startDate->dayOfWeek]; 

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (in_array($date->dayOfWeek, $selectedDays)) {
                $dateStr = $date->format('Y-m-d');

                $primaryConflict = Schedule::with('staff')
                    ->where('staff_id', $request->staff_id)
                    ->where('schedule_date', $dateStr)
                    ->where('status', '!=', 'sick')
                    ->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })->first();

                if ($primaryConflict) {
                    $staffName = $primaryConflict->staff->staff_name ?? 'บุคลากรนี้';
                    $cTime = substr($primaryConflict->start_time, 0, 5) . '–' . substr($primaryConflict->end_time, 0, 5);
                    return redirect()->back()->withErrors(["Conflict! {$staffName} มี slot ช่วงเวลาเดียวกัน ({$cTime}) ในวันที่ {$dateStr}"]);
                }

                if ($request->filled('substitute_staff_id')) {
                    $subConflict = Schedule::with('staff')
                        ->where('staff_id', $request->substitute_staff_id)
                        ->where('schedule_date', $dateStr)
                        ->where('status', '!=', 'sick')
                        ->where(function ($q) use ($request) {
                            $q->where('start_time', '<', $request->end_time)
                              ->where('end_time', '>', $request->start_time);
                        })->first();

                    if ($subConflict) {
                        $subName = $subConflict->staff->staff_name ?? 'บุคลากรสำรองนี้';
                        $cTime = substr($subConflict->start_time, 0, 5) . '–' . substr($subConflict->end_time, 0, 5);
                        return redirect()->back()->withErrors(["Conflict! บุคลากรสำรอง ({$subName}) มี slot ช่วงเวลาเดียวกัน ({$cTime}) ในวันที่ {$dateStr}"]);
                    }
                }
            }
        }

        return DB::transaction(function () use ($request, $startDate, $endDate, $selectedDays) {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if (in_array($date->dayOfWeek, $selectedDays)) {
                    $newSchedule = new Schedule();
                    if ($request->has('counter_sub_id')) { $newSchedule->counter_sub_id = $request->counter_sub_id; }
                    $newSchedule->staff_id      = $request->staff_id;
                    $newSchedule->schedule_date = $date->format('Y-m-d');
                    $newSchedule->start_time    = $request->start_time;
                    $newSchedule->end_time      = $request->end_time;
                    $newSchedule->status        = $request->status ?? 'empty'; 
                    $newSchedule->save();
                    
                    if ($request->filled('substitute_staff_id')) {
                        $subSchedule = new Schedule();
                        if ($request->has('counter_sub_id')) { $subSchedule->counter_sub_id = $request->counter_sub_id; }
                        $subSchedule->staff_id      = $request->substitute_staff_id;
                        $subSchedule->schedule_date = $date->format('Y-m-d');
                        $subSchedule->start_time    = $request->start_time;
                        $subSchedule->end_time      = $request->end_time;
                        $subSchedule->status        = 'empty'; 
                        $subSchedule->save();
                    }
                }
            }
            return redirect()->back()->with('success', 'เพิ่มตารางปฏิบัติงานเรียบร้อย');
        });
    }

    public function update(Request $request, $schedule_id)
    {
        $request->validate([
            'staff_id'      => 'required|integer',
            'schedule_date' => 'required|date',
            'start_time'    => 'required',
            'end_time'      => 'required|after:start_time',
            'status'        => 'nullable|string'
        ]);

        if (strtolower($request->status ?? '') !== 'sick') {
            $conflict = Schedule::with('staff')
                ->where('staff_id', $request->staff_id)
                ->where('schedule_date', $request->schedule_date)
                ->where('schedule_id', '!=', $schedule_id)
                ->where('status', '!=', 'sick')
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>', $request->start_time);
                })->first();

            if ($conflict) {
                $staffName = $conflict->staff->staff_name ?? 'บุคลากรนี้';
                $cTime = substr($conflict->start_time, 0, 5) . '–' . substr($conflict->end_time, 0, 5);
                return redirect()->back()->withErrors(["Conflict! {$staffName} มี slot ช่วงเวลาเดียวกัน ({$cTime}) อยู่แล้ว"]);
            }
        }

        return DB::transaction(function () use ($request, $schedule_id) {
            $schedule = Schedule::findOrFail($schedule_id);
            $schedule->staff_id      = $request->staff_id;
            $schedule->schedule_date = $request->schedule_date;
            $schedule->start_time    = $request->start_time;
            $schedule->end_time      = $request->end_time;
            if ($request->has('status')) { $schedule->status = strtolower($request->status); }
            $schedule->save();

            return redirect()->back()->with('success', 'แก้ไขข้อมูลตารางเรียบร้อยแล้วครับ');
        });
    }

    /**
     * ลบตารางเวรและคนสำรอง โดยป้องกันปัญหารหัสอ้างอิงและรักษาประวัติรีวิวไว้ตลอดกาล
     */
    public function destroy($schedule_id)
    {
        return DB::transaction(function () use ($schedule_id) {
            $schedule = Schedule::findOrFail($schedule_id);

            // 1. ค้นหาคนสำรองทั้งหมดที่มีช่วงเวลาทับซ้อนกับ Slot หลัก
            $substitutes = Schedule::where('counter_sub_id', $schedule->counter_sub_id)
                ->where('schedule_date', $schedule->schedule_date)
                ->where('schedule_id', '!=', $schedule_id)
                ->where(function ($q) use ($schedule) {
                    $q->where('start_time', '<', $schedule->end_time)
                      ->where('end_time', '>', $schedule->start_time);
                })->get();

            // รวบรวม ID ของตารางเวรทั้งหมดที่จะถูกลบทิ้ง
            $scheduleIdsToUnlink = $substitutes->pluck('schedule_id')->toArray();
            $scheduleIdsToUnlink[] = $schedule_id;

            if (class_exists(Checkin::class)) {
                // 2. ดึงข้อมูล Check-in ทั้งหมดที่เคยอ้างอิงถึงตารางเวรกลุ่มนี้
                $oldCheckins = Checkin::whereIn('schedule_id', $scheduleIdsToUnlink)->get();
                $checkinIdsToDelete = $oldCheckins->pluck('checkin_id')->toArray();

                if (!empty($checkinIdsToDelete)) {
                    // 3. ก่อนที่จะลบ Check-in ให้ย้ายประวัติการประเมิน (Evaluation) ไปผูกกับพนักงานแทน เพื่อให้รีวิวไม่หายไป!
                    if (class_exists(Evaluation::class)) {
                        $evals = Evaluation::whereIn('checkin_id', $checkinIdsToDelete)->get();
                        foreach ($evals as $ev) {
                            $ck = $oldCheckins->firstWhere('checkin_id', $ev->checkin_id);
                            if ($ck) {
                                // ก็อปปี้รหัสพนักงานและเคาน์เตอร์ลงไปเก็บในตารางรีวิว
                                if (Schema::hasColumn('evaluation', 'staff_id') && empty($ev->staff_id)) {
                                    $ev->staff_id = $ck->staff_id;
                                }
                                if (Schema::hasColumn('evaluation', 'counter_sub_id') && empty($ev->counter_sub_id)) {
                                    $ev->counter_sub_id = $schedule->counter_sub_id;
                                }
                            }
                            try {
                                // ปลดล็อก checkin_id เพื่อไม่ให้โดนลบตาม
                                $ev->checkin_id = null;
                                $ev->save();
                            } catch (\Exception $e) {}
                        }
                    }

                    // 4. ลบประวัติ Check-in ทิ้งเลย! แก้ปัญหา 1048 Column 'schedule_id' cannot be null ได้ 100%
                    Checkin::whereIn('schedule_id', $scheduleIdsToUnlink)->delete();
                }
            }

            // 5. ลบ Slot ของคนสำรองทั้งหมด
            foreach ($substitutes as $sub) {
                $sub->delete();
            }

            // 6. ลบ Slot หลัก
            $schedule->delete();

            return redirect()->back()->with('success', 'ลบข้อมูลตารางปฏิบัติงานและคนสำรองเรียบร้อยแล้ว');
        });
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|max:5120'
        ]);

        return DB::transaction(function () use ($request) {
            $file = $request->file('import_file');
            $handle = fopen($file->getPathname(), "r");
            fgetcsv($handle, 1000, ","); 
            
            $importedCount = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if(count($data) >= 5 && !empty(trim($data[0])) && !empty(trim($data[1])) && !empty(trim($data[2]))) {
                    try {
                        $newSchedule = new Schedule();
                        $newSchedule->counter_sub_id = trim($data[0]);
                        $newSchedule->staff_id       = trim($data[1]);
                        $newSchedule->schedule_date  = Carbon::parse(trim($data[2]))->format('Y-m-d');
                        $newSchedule->start_time     = trim($data[3]);
                        $newSchedule->end_time       = trim($data[4]);
                        $newSchedule->status         = 'empty';
                        $newSchedule->save();
                        
                        $importedCount++;
                    } catch (\Exception $e) {
                        continue; 
                    }
                }
            }
            fclose($handle);

            if($importedCount > 0) {
                return redirect()->back()->with('success', "นำเข้าตารางปฏิบัติงานสำเร็จจำนวน $importedCount รายการ");
            } else {
                return redirect()->back()->withErrors(['ไม่พบข้อมูลที่ถูกต้องในไฟล์']);
            }
        });
    }
}