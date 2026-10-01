<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Counter;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $staffs = Staff::all();
        $weekOffset = (int) $request->query('week_offset', 0);
        $startOfWeek = Carbon::now()->addWeeks($weekOffset)->startOfWeek();
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

        // สร้าง Map จับคู่ counter_sub_id กับ counter_id เพื่อใช้แสดงชื่อเคาน์เตอร์ตอนเกิด Conflict
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
                                $subsForThisPrimary[] = [
                                    'id'         => $potentialSub->schedule_id,
                                    'name'       => $potentialSub->staff ? $potentialSub->staff->staff_name : 'ไม่ระบุ',
                                    'staff_code' => $potentialSub->staff_id,
                                    'start'      => substr($potentialSub->start_time, 0, 5),
                                    'end'        => substr($potentialSub->end_time, 0, 5),
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
        
        // เตรียมข้อมูลตารางงานทั้งหมดของบุคลากรแต่ละคน พร้อมเลขเคาน์เตอร์ เพื่อส่งให้ JS ตรวจ Conflict
        $staffAvailability = $staffs->map(function($staff) use ($allSchedulesForOverlap, $subToCounterMap) {
            return [
                'id' => $staff->staff_id,
                'name' => $staff->staff_name,
                'schedules' => $allSchedulesForOverlap->where('staff_id', $staff->staff_id)
                    ->where('status', '!=', 'Sick')
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

        // ตรวจสอบว่าคนหลักและคนสำรองเป็นคนเดียวกันหรือไม่
        if ($request->filled('substitute_staff_id') && $request->staff_id == $request->substitute_staff_id) {
            return redirect()->back()->withErrors(['บุคลากรสำรองต้องไม่ใช่คนเดียวกับบุคลากรหลักครับ']);
        }

        $startDate = Carbon::parse($request->schedule_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy();
        $selectedDays = $request->work_days ?? [$startDate->dayOfWeek]; 

        // 1. ตรวจสอบ Conflict ในระดับ Backend ก่อนบันทึกจริง
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (in_array($date->dayOfWeek, $selectedDays)) {
                $dateStr = $date->format('Y-m-d');

                // เช็คตารางชนของบุคลากรหลัก
                $primaryConflict = Schedule::with('staff')
                    ->where('staff_id', $request->staff_id)
                    ->where('schedule_date', $dateStr)
                    ->where('status', '!=', 'Sick')
                    ->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })->first();

                if ($primaryConflict) {
                    $staffName = $primaryConflict->staff->staff_name ?? 'บุคลากรนี้';
                    $cTime = substr($primaryConflict->start_time, 0, 5) . '–' . substr($primaryConflict->end_time, 0, 5);
                    return redirect()->back()->withErrors(["Conflict! {$staffName} มี slot ช่วงเวลาเดียวกัน ({$cTime}) ในวันที่ {$dateStr} — กรุณาเปลี่ยนเวลาหรือเลือกคนอื่น"]);
                }

                // เช็คตารางชนของบุคลากรสำรอง (ถ้ามีการเลือก)
                if ($request->filled('substitute_staff_id')) {
                    $subConflict = Schedule::with('staff')
                        ->where('staff_id', $request->substitute_staff_id)
                        ->where('schedule_date', $dateStr)
                        ->where('status', '!=', 'Sick')
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

        // 2. บันทึกข้อมูลด้วย Transaction เมื่อไม่พบตารางชน
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
            return redirect()->back()->with('success', 'เพิ่มตารางปฏิบัติงานเรียบร้อยแล้วครับ');
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

        // ถ้าไม่ได้กดเปลี่ยนเป็นสถานะลาป่วย ให้เช็คว่าเวลาที่แก้ไขไปชนกับตารางอื่นของตัวเองหรือไม่
        if ($request->status !== 'Sick') {
            $conflict = Schedule::with('staff')
                ->where('staff_id', $request->staff_id)
                ->where('schedule_date', $request->schedule_date)
                ->where('schedule_id', '!=', $schedule_id)
                ->where('status', '!=', 'Sick')
                ->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>', $request->start_time);
                })->first();

            if ($conflict) {
                $staffName = $conflict->staff->staff_name ?? 'บุคลากรนี้';
                $cTime = substr($conflict->start_time, 0, 5) . '–' . substr($conflict->end_time, 0, 5);
                return redirect()->back()->withErrors(["Conflict! {$staffName} มี slot ช่วงเวลาเดียวกัน ({$cTime}) อยู่แล้ว ไม่สามารถแก้ไขเวลาทับซ้อนได้"]);
            }
        }

        return DB::transaction(function () use ($request, $schedule_id) {
            $schedule = Schedule::findOrFail($schedule_id);
            $schedule->staff_id      = $request->staff_id;
            $schedule->schedule_date = $request->schedule_date;
            $schedule->start_time    = $request->start_time;
            $schedule->end_time      = $request->end_time;
            if ($request->has('status')) { $schedule->status = $request->status; }
            $schedule->save();

            return redirect()->back()->with('success', 'แก้ไขข้อมูลตารางเรียบร้อยแล้วครับ');
        });
    }

    public function destroy($schedule_id)
    {
        return DB::transaction(function () use ($schedule_id) {
            $schedule = Schedule::findOrFail($schedule_id);
            $schedule->delete();
            return redirect()->back()->with('success', 'ลบข้อมูลตารางปฏิบัติงานเรียบร้อยแล้ว');
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
                return redirect()->back()->with('success', "นำเข้าตารางปฏิบัติงานสำเร็จจำนวน $importedCount รายการ 100%!");
            } else {
                return redirect()->back()->withErrors(['ไม่พบข้อมูลที่ถูกต้องในไฟล์ กรุณาตรวจสอบรูปแบบคอลัมน์']);
            }
        });
    }
}