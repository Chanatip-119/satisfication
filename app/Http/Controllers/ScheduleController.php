<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::all();
        return view('schedules.index', compact('schedules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'schedule_date' => 'required|date',   
            'start_time'    => 'required',        
            'end_time'      => 'required',        
            'status'        => 'required|string'  
        ]);

        $newSchedule = new Schedule();
        $newSchedule->schedule_date = $request->schedule_date;
        $newSchedule->start_time = $request->start_time;
        $newSchedule->end_time = $request->end_time;
        $newSchedule->status = $request->status;

        $newSchedule->save();
        return redirect()->back()->with('success', 'เพิ่มตารางปฏิบัติงานเรียบร้อยแล้วครับ');
    }

    public function update(Request $request, $schedule_id)
    {
        $request->validate([
            'schedule_date' => 'required|date',
            'start_time'    => 'required',
            'end_time'      => 'required',
            'status'        => 'required|string'
        ]);

        $schedule = Schedule::find($schedule_id);
        if ($schedule) {
            $schedule->schedule_date = $request->schedule_date;
            $schedule->start_time = $request->start_time;
            $schedule->end_time = $request->end_time;
            $schedule->status = $request->status;
            
            $schedule->save();
        }

        return redirect()->back()->with('success', 'แก้ไขข้อมูลตารางเรียบร้อยแล้วครับ');
    }

    public function destroy($schedule_id)
    {
        $schedule = Schedule::find($schedule_id);
        if ($schedule) {
            $schedule->delete();
        }

        return redirect()->back()->with('success', 'ลบข้อมูลตารางปฏิบัติงานเรียบร้อยแล้ว');
    }
}
