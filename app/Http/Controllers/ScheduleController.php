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
    // แสดงหน้า "ตารางปฏิบัติงาน" หลัก
    public function index(Request $request)
    {
        // 1. ดึงบุคลากรทั้งหมดเพื่อใช้ใน Dropdown หน้าเพิ่มตาราง
        $staffs = Staff::all();

        // 2. คำนวณวันจันทร์-อาทิตย์ ของสัปดาห์ปัจจุบัน
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDays[] = $startOfWeek->copy()->addDays($i);
        }

        // ข้อความแสดงสัปดาห์
        $weekLabel = $startOfWeek->translatedFormat('j') . ' - ' . $endOfWeek->translatedFormat('j F Y');

        // 3. ดึงข้อมูลเคาน์เตอร์ และดึง Schedule ดึงลึกไปถึง Staff โดยกรองเฉพาะสัปดาห์นี้
        $counters = Counter::with(['countersubs.schedules' => function ($query) use ($startOfWeek, $endOfWeek) {
            $query->with('staff')
                  ->whereBetween('schedule_date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                  ->orderBy('start_time', 'asc');
        }])->where('is_active', true)->get();

        // ส่งตัวแปร schedules เดิมไปด้วยเพื่อไม่ให้หน้า "บุคลากร" หรือหน้าเก่าพัง (เผื่อมีคนเรียกใช้)
        $schedules = Schedule::all();

        return view('schedules.index', compact('counters', 'staffs', 'weekDays', 'weekLabel', 'schedules'));
    }

    public function store(Request $request)
    {
        // สังเกตว่า counter_sub_id เปลี่ยนเป็น nullable เพื่อให้หน้า "บุคลากร" สร้างตารางได้แม้ไม่มีเคาน์เตอร์
        $request->validate([
            'counter_sub_id' => 'nullable|integer', 
            'staff_id'       => 'required|integer', 
            'schedule_date'  => 'required|date',   
            'start_time'     => 'required',        
            'end_time'       => 'required|after:start_time',
            'status'         => 'nullable|string' // ทำให้หน้าบุคลากรส่งสถานะได้ หรือใช้ค่าเริ่มต้น
        ]);

        return DB::transaction(function () use ($request) {
            $newSchedule = new Schedule();
            
            // ถ้ามีการส่ง counter_sub_id มา (จากหน้าตารางปฏิบัติงาน) ค่อยบันทึก
            if ($request->has('counter_sub_id')) {
                $newSchedule->counter_sub_id = $request->counter_sub_id;
            }
            
            $newSchedule->staff_id       = $request->staff_id;
            $newSchedule->schedule_date  = $request->schedule_date;
            $newSchedule->start_time     = $request->start_time;
            $newSchedule->end_time       = $request->end_time;
            
            // ถ้าหน้าบุคลากรส่ง status มา ให้ใช้ค่านั้น ถ้าไม่ส่งมาให้ตั้งเป็น Active
            $newSchedule->status         = $request->status ?? 'Active'; 
            
            $newSchedule->save();

            return redirect()->back()->with('success', 'เพิ่มตารางปฏิบัติงานเรียบร้อยแล้วครับ');
        });
    }

    public function update(Request $request, $schedule_id)
    {
        $request->validate([
            'staff_id'       => 'required|integer',
            'schedule_date'  => 'required|date',
            'start_time'     => 'required',
            'end_time'       => 'required|after:start_time',
            'status'         => 'nullable|string'
        ]);

        return DB::transaction(function () use ($request, $schedule_id) {
            $schedule = Schedule::findOrFail($schedule_id);
            
            // อัปเดตข้อมูล
            $schedule->staff_id      = $request->staff_id;
            $schedule->schedule_date = $request->schedule_date;
            $schedule->start_time    = $request->start_time;
            $schedule->end_time      = $request->end_time;
            
            if ($request->has('status')) {
                $schedule->status = $request->status;
            }
            
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
}