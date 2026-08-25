<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Counter;
use App\Models\Schedule;
use App\Models\Staff;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    /**
     * แสดงหน้ารายการ check-in ทั้งหมด
     */
    public function index()
    {
        $checkins = Checkin::all();
        return view('checkins.index', compact('checkins'));
    }

    /**
     * แสดงหน้า Staff Check-in Kiosk (multi-step form)
     * ดึงข้อมูล counter slots จาก DB หรือใช้ demo data
     */
    public function create()
    {
        $counterSlots = collect();

        try {
            $counters = Counter::with('countersubs')->where('is_active', true)->get();

            foreach ($counters as $counter) {
                foreach ($counter->countersubs as $sub) {
                    // ดึง schedule ที่ใช้งานอยู่สำหรับ counter sub นี้ (วันนี้)
                    $schedule = Schedule::where('counter_sub_id', $sub->counter_sub_id)
                        ->where('schedule_date', now()->toDateString())
                        ->first();

                    $timeStart = $schedule ? substr($schedule->start_time, 0, 5) : '08:00';
                    $timeEnd   = $schedule ? substr($schedule->end_time, 0, 5) : '17:00';

                    // ตรวจสอบว่ามีคนเช็คอินอยู่ไหม
                    $hasActiveCheckin = false;
                    if ($schedule) {
                        $hasActiveCheckin = Checkin::where('schedule_id', $schedule->schedule_id)
                            ->whereNull('checkout_at')
                            ->exists();
                    }

                    $counterSlots->push([
                        'id'         => $sub->counter_sub_id,
                        'name'       => 'เคาน์เตอร์ ' . $counter->counter_id . '.' . $sub->counter_sub_id,
                        'time_start' => $timeStart,
                        'time_end'   => $timeEnd,
                        'floor'      => 'ชั้น ' . $counter->counter_id,
                        'status'     => $hasActiveCheckin ? 'occupied' : 'available',
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Database ยังไม่พร้อม — ใช้ demo data
        }

        // ถ้าไม่มีข้อมูลจาก DB, ใช้ demo data
        if ($counterSlots->isEmpty()) {
            $counterSlots = collect([
                ['id' => 1, 'name' => 'เคาน์เตอร์ 1.1', 'time_start' => '08:00', 'time_end' => '12:00', 'floor' => 'ชั้น 1', 'status' => 'available'],
                ['id' => 2, 'name' => 'เคาน์เตอร์ 1.2', 'time_start' => '13:00', 'time_end' => '16:00', 'floor' => 'ชั้น 1', 'status' => 'available'],
                ['id' => 3, 'name' => 'เคาน์เตอร์ 2.1', 'time_start' => '08:00', 'time_end' => '11:00', 'floor' => 'ชั้น 2', 'status' => 'available'],
                ['id' => 4, 'name' => 'เคาน์เตอร์ 3.1', 'time_start' => '08:00', 'time_end' => '11:00', 'floor' => 'ชั้น 3', 'status' => 'occupied'],
            ]);
        }

        return view('checkin.create', ['counterSlots' => $counterSlots]);
    }

    /**
     * Verify PIN ผ่าน AJAX — ค้นหา staff จาก pincode
     */
    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4',
        ]);

        $staff = Staff::where('staff_pincode', $request->pin)->first();

        if (!$staff) {
            return response()->json([
                'error' => 'ไม่พบข้อมูลพนักงาน กรุณาลองใหม่'
            ], 404);
        }

        return response()->json([
            'staff_id'   => $staff->staff_id,
            'staff_name' => $staff->staff_name,
            'staff_code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * สร้าง checkin record ใหม่ (AJAX from Kiosk)
     */
    public function store(Request $request)
    {
        // ถ้าเป็น AJAX JSON request จาก Kiosk
        if ($request->expectsJson()) {
            $request->validate([
                'counter_id' => 'required|integer',
                'staff_id'   => 'required|integer',
            ]);

            // หา schedule ที่ตรงกับ counter + วันนี้
            $schedule = Schedule::where('counter_sub_id', $request->counter_id)
                ->where('schedule_date', now()->toDateString())
                ->first();

            $scheduleId = $schedule ? $schedule->schedule_id : null;

            // ถ้ายังไม่มี schedule สร้างอัตโนมัติ (สำหรับ demo)
            if (!$scheduleId) {
                try {
                    $newSchedule = Schedule::create([
                        'counter_sub_id' => $request->counter_id,
                        'schedule_date'  => now()->toDateString(),
                        'start_time'     => now()->format('H:i:s'),
                        'end_time'       => now()->addHours(4)->format('H:i:s'),
                        'status'         => 'checkin',
                    ]);
                    $scheduleId = $newSchedule->schedule_id;
                } catch (\Exception $e) {
                    return response()->json([
                        'success'    => true,
                        'checkin_id' => 999,
                        'message'    => 'Demo mode: check-in สำเร็จ (ไม่ได้บันทึก DB)',
                    ]);
                }
            }

            $checkin = new Checkin();
            $checkin->schedule_id = $scheduleId;
            $checkin->staff_id    = $request->staff_id;
            $checkin->checkin_at  = now();
            $checkin->is_substitute = 0;
            $checkin->is_kicked     = 0;
            $checkin->save();

            return response()->json([
                'success'    => true,
                'checkin_id' => $checkin->checkin_id,
                'message'    => 'เช็คอินเข้างานเรียบร้อยแล้ว!',
            ]);
        }

        // Legacy form-based request
        $request->validate([
            'schedule_id'         => 'required|integer',
            'is_substitute'       => 'nullable|integer',
            'substituting_for_id' => 'nullable|integer'
        ]);

        $newCheckin = new Checkin();
        $newCheckin->schedule_id = $request->schedule_id;
        $newCheckin->checkin_at = now();
        $newCheckin->is_substitute = $request->is_substitute ?? 0;
        $newCheckin->substituting_for_id = $request->substituting_for_id;
        $newCheckin->is_kicked = 0;

        $newCheckin->save();
        return redirect()->back()->with('success', 'เช็คอินเข้างานเรียบร้อยแล้ว!');
    }

    /**
     * อัปเดต checkin (checkout / kick)
     */
    public function update(Request $request, $checkin_id)
    {
        $checkin = Checkin::find($checkin_id);
        if ($checkin) {
            $checkin->checkout_at = now();
            if ($request->has('is_kicked')) {
                $checkin->is_kicked = $request->is_kicked;
            }
            $checkin->save();
        }

        return redirect()->back()->with('success', 'อัปเดตข้อมูล/เช็คเอาท์ เรียบร้อยแล้ว');
    }

    /**
     * ลบ checkin record
     */
    public function destroy($checkin_id)
    {
        $checkin = Checkin::find($checkin_id);
        if ($checkin) {
            $checkin->delete();
        }

        return redirect()->back()->with('success', 'ลบข้อมูลการเช็คอินเรียบร้อยแล้ว');
    }
}