<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Schedule;
use App\Models\Checkin;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CounterController extends Controller
{
    public function index()
    {
        $counters = Counter::with(['countersubs.schedules' => function ($query) {
            $query->orderBy('schedule_date', 'desc');
        }])->get();
        
        return view('counter.index', compact('counters'));
    }

    public function create()
    {
        return view('counter.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'counter_id' => 'required|string|unique:counter,counter_id',
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $counter = Counter::create($validated);
            // จัดการสร้าง Sub-Counter แบบรวดเดียวตอนสร้างเคาน์เตอร์หลัก
            if ($request->has('new_start_time')) {
                foreach ($request->new_start_time as $index => $startTime) {
                    $endTime = $request->new_end_time[$index] ?? '16:00';
                    $isActive = $request->new_is_active[$index] ?? 1;

                    $newSub = $counter->countersubs()->create([
                        'is_active' => $isActive
                    ]);

                    Schedule::updateOrCreate(
                        [
                            'counter_sub_id' => $newSub->counter_sub_id,
                            'schedule_date' => now()->toDateString(), 
                        ],
                        [
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ]
                    );
                }
            }
        });

        return redirect()->route('counter.index')->with('success', 'เพิ่มข้อมูลเคาน์เตอร์และช่วงเวลาสำเร็จ');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $counter = Counter::findOrFail($id);

        DB::transaction(function () use ($counter, $validated, $request) {
            $counter->update($validated);

            // จัดการเพิ่ม Sub-Counter ตัวใหม่ที่ถูกกดเพิ่มเข้ามาตอนแก้ไข
            if ($request->has('new_start_time')) {
                foreach ($request->new_start_time as $index => $startTime) {
                    $endTime = $request->new_end_time[$index] ?? '16:00';
                    $isActive = $request->new_is_active[$index] ?? 1;

                    $newSub = $counter->countersubs()->create([
                        'is_active' => $isActive
                    ]);

                    Schedule::updateOrCreate(
                        [
                            'counter_sub_id' => $newSub->counter_sub_id,
                            'schedule_date' => now()->toDateString(), 
                        ],
                        [
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ]
                    );
                }
            }

            // จัดการอัปเดตข้อมูล Sub-Counter เดิมที่มีอยู่แล้ว
            if ($request->has('existing_sub_id')) {
                foreach ($request->existing_sub_id as $index => $subId) {
                    $startTime = $request->existing_start_time[$index] ?? '08:00';
                    $endTime = $request->existing_end_time[$index] ?? '16:00';

                    if (isset($request->existing_is_active[$index])) {
                        $counter->countersubs()->where('counter_sub_id', $subId)->update([
                            'is_active' => $request->existing_is_active[$index]
                        ]);
                    }

                    Schedule::updateOrCreate(
                        [
                            'counter_sub_id' => $subId,
                            'schedule_date' => now()->toDateString(), 
                        ],
                        [
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                        ]
                    );
                }
            }
        });

        return redirect()->route('counter.index')->with('success', 'อัปเดตข้อมูลเคาน์เตอร์สำเร็จ');
    }

    public function destroy($id)
    {
        $counter = Counter::findOrFail($id);

        DB::transaction(function () use ($counter) {
            $subIds = $counter->countersubs()->pluck('counter_sub_id');

            if ($subIds->isNotEmpty()) {
                $scheduleIds = Schedule::whereIn('counter_sub_id', $subIds)->pluck('schedule_id');

                if ($scheduleIds->isNotEmpty()) {
                    $checkinIds = Checkin::whereIn('schedule_id', $scheduleIds)->pluck('checkin_id');

                    if ($checkinIds->isNotEmpty()) {
                        Evaluation::whereIn('checkin_id', $checkinIds)->delete();
                        Checkin::whereIn('schedule_id', $scheduleIds)->delete();
                    }

                    Schedule::whereIn('counter_sub_id', $subIds)->delete();
                }

                $counter->countersubs()->delete();
            }

            $counter->delete();
        });

        return redirect()->route('counter.index')->with('success', 'ลบเคาน์เตอร์และข้อมูลที่เกี่ยวข้องทั้งหมดสำเร็จ');
    }

    public function storeSub(Request $request, $counterId)
    {
        // ย้ายการทำงานไปรวมไว้ใน store และ update แล้ว (เว้นไว้เป็น Fallback ได้)
        return redirect()->route('counter.index');
    }

    public function destroySub($counterId, $subId)
    {
        $counterSub = CounterSub::where('counter_id', $counterId)
                                ->where('counter_sub_id', $subId)
                                ->first();

        if ($counterSub) {
            DB::transaction(function () use ($counterSub, $subId) {
                $scheduleIds = Schedule::where('counter_sub_id', $subId)->pluck('schedule_id');
                
                if ($scheduleIds->isNotEmpty()) {
                    $checkinIds = Checkin::whereIn('schedule_id', $scheduleIds)->pluck('checkin_id');
                    if ($checkinIds->isNotEmpty()) {
                        Evaluation::whereIn('checkin_id', $checkinIds)->delete();
                        Checkin::whereIn('schedule_id', $scheduleIds)->delete();
                    }
                    Schedule::where('counter_sub_id', $subId)->delete();
                }

                $counterSub->delete();
            });
        }

        return redirect()->route('counter.index')->with('success', 'ลบเคาน์เตอร์ย่อยสำเร็จ');
    }
}