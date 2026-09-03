<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CounterSub;
use App\Models\Checkin;
use App\Models\Evaluation;
use App\Models\Staff;
use App\Models\Qr_Code;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EvaluationController extends Controller
{
    public function index()
    {
        $evaluations = Evaluation::all();
        return view('evaluation.index', compact('evaluations'));
    }

    // กำหนดค่าเริ่มต้นเป็น null เพื่อรองรับทั้งแบบ /evaluation/1 และ /evaluation?counter_sub_id=1
    public function create($counter_sub_id = null)
    {
        $counter_sub_id = $counter_sub_id ?? request('counter_sub_id');

        if (!$counter_sub_id) {
            abort(404, 'กรุณาสแกน QR Code ประจำเคาน์เตอร์เพื่อทำการประเมิน');
        }

        // ค้นหา Check-in ของเคาน์เตอร์นี้ ที่ยังไม่ Check-out, ต้องเป็นของวันนี้เท่านั้น และดึงคนล่าสุด
        $activeCheckin = Checkin::whereHas('schedule', function($query) use ($counter_sub_id) {
            $query->where('counter_sub_id', $counter_sub_id);
        })
        ->whereNull('checkout_at')
        ->whereDate('checkin_at', Carbon::today()) // ป้องกันพนักงานเมื่อวานลืม Check-out
        ->orderBy('checkin_at', 'desc') // ดึงพนักงานคนที่มา Check-in ล่าสุดเสมอ
        ->first();

        // ดักจับกรณีไม่มีพนักงาน Check-in อยู่ 
        if (!$activeCheckin) {
            return view('evaluation.create', [
                'is_closed' => true,
                'counter_name' => 'เคาน์เตอร์ ' . $counter_sub_id,
                'counter_sub_id' => $counter_sub_id // ส่งค่ากลับไปด้วยป้องกัน Error Undefined variable
            ]);
        }

        // หากมีพนักงานอยู่ ให้ส่งข้อมูล checkin_id ของคนล่าสุดไปที่หน้าฟอร์มประเมิน
        return view('evaluation.create', [
            'is_closed' => false,
            'checkin_id' => $activeCheckin->checkin_id,
            'counter_sub_id' => $counter_sub_id,
            'counter_name' => 'เคาน์เตอร์ ' . $counter_sub_id
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:255',
            'checkin_id' => 'required|exists:checkin,checkin_id', // เช็คให้ชัวร์ว่า ID มีอยู่จริง
            'counter_sub_id' => 'required' // รับค่าไว้สำหรับ Redirect กลับไปหน้าเดิม
        ]);

        Evaluation::create($request->all());

        // ส่ง counter_sub_id กลับไปใน Route เพื่อให้หน้าโหลดซ้ำได้ถูกเคาน์เตอร์
        return redirect()->route('evaluation.create', ['counter_sub_id' => $request->counter_sub_id])
                         ->with('success', 'เพิ่มข้อมูลการประเมินสำเร็จ');
    }
}