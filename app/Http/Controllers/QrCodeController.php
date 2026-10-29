<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CounterSub;
// [แก้ไข ระดับ 4] ลบ use App\Models\Schedule; เพราะไม่ได้ใช้แล้ว

class QrCodeController extends Controller
{
    public function index()
    {
        // ดึงจุดย่อยทั้งหมดพร้อมเคาน์เตอร์หลัก เรียงตามเคาน์เตอร์และลำดับจุดย่อย
        // [แก้ไข ระดับ 4] เพิ่มการเรียงลำดับ ให้การ์ดเรียงเป็น 1.1, 1.2, 2.1 ... ตามชื่อที่แสดง
        $counterSubs = CounterSub::with('counter')
            ->orderBy('counter_id')
            ->orderBy('counter_sub_id')
            ->get();

        // [แก้ไข ระดับ 4] ลบการดึงกะทั้งหมด ($schedules) ออก
        // เดิม: Schedule::whereIn('counter_sub_id', ...)->orderBy('start_time')->get()->groupBy('counter_sub_id')
        // เหตุผล: view ไม่ได้ใช้ $schedules เลย แต่ query นี้ดึงกะทุกแถวตั้งแต่เริ่มใช้ระบบ ทำให้หน้าช้าลงเรื่อย ๆ
        return view('qrcode.index', compact('counterSubs'));
    }
}