<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Evaluation;
use App\Models\Staff;
use Illuminate\Http\Request;
// [แก้ไข ระดับ 4] ลบ use App\Models\Schedule; เพราะไม่ได้ใช้แล้ว (ดู counterReport)

class ReportController extends Controller
{
    public function counterReport()
    {
        $evaluations = Evaluation::all();
        $totalAvg = $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '0.0';
        $totalEvals = $evaluations->count();
        $totalComments = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->count();
        $totalStaff = Checkin::distinct('staff_id')->count('staff_id');

        $countersList = [];
        $counters = Counter::with('countersubs')->get();

        foreach ($counters as $counter) {
            foreach ($counter->countersubs as $sub) {
                $checkinIds = Checkin::whereHas('schedule', function($q) use ($sub) {
                    $q->where('counter_sub_id', $sub->counter_sub_id);
                })->pluck('checkin_id');

                // [รายงาน+Online ข้อ 4] นับจาก counter_sub_id ด้วย (การประเมิน Online ไม่มี checkin_id)
                // เดิม: Evaluation::whereIn('checkin_id', $checkinIds) — ไม่เห็นคะแนนของเคาน์เตอร์ Online เลย
                // ใช้ query เดียว (นับ + เฉลี่ย) และ CAST rating เป็นตัวเลขเพราะใน DB เป็น ENUM ข้อความ
                $stats = Evaluation::where(function ($q) use ($sub, $checkinIds) {
                        $q->where('counter_sub_id', $sub->counter_sub_id)
                          ->orWhereIn('checkin_id', $checkinIds); // ข้อมูลเก่าที่ยังไม่มี counter_sub_id
                    })
                    ->selectRaw('COUNT(*) AS total, AVG(CAST(rating AS UNSIGNED)) AS average')
                    ->first();

                $count = (int) ($stats->total ?? 0);
                $avg = $count > 0 ? number_format((float) $stats->average, 1) : '0.0';

                $activeCheckin = Checkin::whereHas('schedule', function($q) use ($sub) {
                    $q->where('counter_sub_id', $sub->counter_sub_id);
                })->whereNull('checkout_at')->first();

                $currentStaffName = '— ไม่มี Staff';
                $status = 'Close';
                
                if ($activeCheckin && $activeCheckin->staff) {
                    $currentStaffName = $activeCheckin->staff->staff_name;
                    $status = 'Active';
                }

                // [รายงาน+Online ข้อ 4] เคาน์เตอร์ Online: ไม่มีคนประจำ เปิดรับประเมินตลอด
                if ($counter->is_online) {
                    $currentStaffName = 'ประเมิน Online (ไม่มีผู้ประจำ)';
                    $status = ($sub->is_active && $counter->is_active) ? 'Online' : 'Close';
                }

                // [แก้ไข ระดับ 4] ลบการหาเวลา ($schedule / $timeRange) ออก
                // เดิม: Schedule::where('counter_sub_id', ...)->first() ได้กะแรกสุดในประวัติ (ไม่ใช่ของวันนี้)
                //       และถ้าไม่เจอจะใส่ '08:00–16:00' ทั้งที่ไม่มีข้อมูลจริง — view ก็ไม่ได้แสดงค่านี้อยู่แล้ว

                $countersList[] = (object)[
                    'counter_name' => $counter->counter_location ?? ('เคาน์เตอร์ ' . $counter->counter_id),
                    // [แก้ไข ระดับ 4] รหัสจุดย่อยรูปแบบกลาง เช่น "8.1"
                    // เดิม: $counter->counter_id . '.' . $sub->counter_sub_id (เช่น "1.7")
                    'name' => CounterSub::codeFor($sub->counter_sub_id) ?? ($counter->counter_id . '.?'),
                    'avg_rating' => $avg,
                    'eval_count' => $count,
                    'current_staff' => $currentStaffName,
                    'status' => $status
                ];
            }
        }

        return view('report.counter', compact('totalAvg', 'totalEvals', 'totalComments', 'totalStaff', 'countersList', 'counters'));
    }

    public function staffReport()
    {
        $staffs = Staff::all();
        $staffReportList = [];

        foreach ($staffs as $staff) {
            $checkins = Checkin::where('staff_id', $staff->staff_id)->get();
            $checkinIds = $checkins->pluck('checkin_id');
            
            $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->get();
            
            $avgRating = $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '-';
            $commentCount = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->count();
            $checkinCount = $checkins->count();
            
            // ตรวจสอบว่าพนักงานมีรายการ Check-in ที่ยังไม่ Checkout (checkout_at เป็น null) หรือไม่
            $activeCheckin = $checkins->whereNull('checkout_at')->first();
            $status = $activeCheckin ? 'Active' : 'Offline';

            $staffReportList[] = (object)[
                'staff_id' => $staff->staff_id,
                'name' => $staff->staff_name,
                'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT),
                'avg_rating' => $avgRating,
                'checkin_count' => $checkinCount,
                'comment_count' => $commentCount,
                'status' => $status,
                'raw_rating' => $evaluations->avg('rating')
            ];
        }

        return view('report.staff', compact('staffReportList'));
    }

    // [แก้ไข ระดับ 4] ลบเมธอด show($id) ทั้งเมธอดออก
    // เหตุผล: 1) ไม่มี route ไหนเรียกใช้ — Side Panel ทุกหน้าเรียก /staff/{id} ซึ่งคือ StaffController::show()
    //         2) มีบัคที่ทำให้ error ทุกครั้ง: Staff::where('staff_id', $id)->orWhere('id', $id)
    //            (ตาราง staff ไม่มีคอลัมน์ id) และอ้างคอลัมน์ที่ไม่มีอยู่จริงอีกหลายตัว
    //         การเก็บโค้ดซ้ำที่พังไว้ทำให้คนแก้ครั้งต่อไปสับสนว่าต้องแก้ตัวไหน
}