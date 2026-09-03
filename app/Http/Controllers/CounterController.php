<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Schedule;
use Illuminate\Http\Request;

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
            'counter_id' => 'required|integer|unique:counter,counter_id',
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        Counter::create($validated);

        return redirect()->route('counter.index')->with('success', 'เพิ่มข้อมูลเคาน์เตอร์สำเร็จ');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $counter = Counter::findOrFail($id);
        $counter->update($validated);

        $counter->countersubs()->update(['is_active' => $validated['is_active']]);

        return redirect()->route('counter.index')->with('success', 'อัปเดตข้อมูลเคาน์เตอร์สำเร็จ');
    }

    public function destroy($id)
    {
        $counter = Counter::findOrFail($id);
        $counter->delete();

        return redirect()->route('counter.index')->with('success', 'ลบเคาน์เตอร์สำเร็จ');
    }

    public function storeSub(Request $request, $counterId)
    {
        $counter = Counter::findOrFail($counterId);

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

        return redirect()->route('counter.index')->with('success', 'อัปเดตข้อมูลเคาน์เตอร์ย่อยสำเร็จ');
    }

    public function destroySub($counterId, $subId)
    {
        $counterSub = CounterSub::where('counter_id', $counterId)
                                ->where('counter_sub_id', $subId)
                                ->first();

        if ($counterSub) {
            $counterSub->delete();
        }

        return redirect()->route('counter.index')->with('success', 'ลบเคาน์เตอร์ย่อยสำเร็จ');
    }
}