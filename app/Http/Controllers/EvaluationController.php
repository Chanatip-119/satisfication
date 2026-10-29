<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CounterSub;
use App\Models\Checkin;
use App\Models\Evaluation;
use App\Models\Staff;
// [แก้ไข ระดับ 4] ลบ use App\Models\Qr_Code; — ไม่มี Model นี้ในโปรเจกต์และไม่ได้ถูกใช้
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter; // [แก้ไข ระดับ 1 ข้อ 4] ใช้กันการกดส่งประเมินซ้ำ
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

        // [แก้ไข ระดับ 4] ชื่อเคาน์เตอร์ที่ผู้รับบริการเข้าใจได้ เช่น "เคาน์เตอร์ 8.1 — บริการเคาน์เตอร์ชั้น 6"
        // เดิม: 'เคาน์เตอร์ ' . $counter_sub_id (เช่น "เคาน์เตอร์ 7" ซึ่งไม่ตรงกับชื่อที่ติดไว้หน้าเคาน์เตอร์)
        $counterSub = CounterSub::with('counter')->find($counter_sub_id);
        $counterName = CounterSub::labelFor($counter_sub_id)
            . ($counterSub && $counterSub->counter ? ' — ' . $counterSub->counter->counter_location : '');

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
                'counter_name' => $counterName,
                'counter_sub_id' => $counter_sub_id // ส่งค่ากลับไปด้วยป้องกัน Error Undefined variable
            ]);
        }

        // หากมีพนักงานอยู่ ให้ส่งข้อมูล checkin_id ของคนล่าสุดไปที่หน้าฟอร์มประเมิน
        return view('evaluation.create', [
            'is_closed' => false,
            'checkin_id' => $activeCheckin->checkin_id,
            'counter_sub_id' => $counter_sub_id,
            'counter_name' => $counterName
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            // [แก้ไข ระดับ 2 ข้อ 11] จำกัดความยาวให้ตรงกับคอลัมน์ comment ใน DB (string 255)
            // เดิม: 'nullable|string|max:2500' — พิมพ์เกิน 255 ตัวแล้ว MySQL error เป็นหน้า 500 และคะแนนหาย
            'comment' => 'nullable|string|max:255',
            'checkin_id' => 'required|exists:checkin,checkin_id', // เช็คให้ชัวร์ว่า ID มีอยู่จริง
            'counter_sub_id' => 'required' // รับค่าไว้สำหรับ Redirect กลับไปหน้าเดิม
        ], [
            // [แก้ไข ระดับ 2 ข้อ 11] ข้อความ error ภาษาไทยที่ผู้รับบริการเข้าใจได้
            'rating.required' => 'กรุณาเลือกระดับความพึงพอใจ',
            'comment.max' => 'ข้อเสนอแนะต้องไม่เกิน 255 ตัวอักษร',
            'checkin_id.exists' => 'เจ้าหน้าที่ประจำเคาน์เตอร์เปลี่ยนแล้ว กรุณาประเมินใหม่อีกครั้ง',
        ]);

        // ===== [แก้ไข ระดับ 1 ข้อ 4] กันการกดส่งประเมินซ้ำ / ปั่นคะแนน =====
        // - ต่อเครื่อง (session) ต่อการ Check-in: ส่งได้ 1 ครั้งภายใน 2 นาที
        // - ต่อ IP: ไม่เกิน 60 ครั้งใน 10 นาที (เผื่อผู้ใช้หลายคนใช้ Wi-Fi ห้องสมุดร่วมกัน)
        $deviceKey = 'evaluation:' . $request->session()->getId() . ':' . $request->checkin_id;
        $ipKey = 'evaluation-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($deviceKey, 1) || RateLimiter::tooManyAttempts($ipKey, 60)) {
            return redirect()->route('evaluation.create', ['counter_sub_id' => $request->counter_sub_id])
                ->withErrors(['rating' => 'คุณเพิ่งส่งการประเมินไปแล้ว กรุณารอสักครู่ก่อนส่งอีกครั้ง']);
        }

        RateLimiter::hit($deviceKey, 120);
        RateLimiter::hit($ipKey, 600);
        // ===== [จบ ข้อ 4] =====

        // [แก้ไข ระดับ 1 ข้อ 4] บันทึกเฉพาะฟิลด์ที่ต้องใช้
        // เดิม: Evaluation::create($request->all());
        Evaluation::create($request->only(['rating', 'comment', 'checkin_id']));

        // ส่ง counter_sub_id กลับไปใน Route เพื่อให้หน้าโหลดซ้ำได้ถูกเคาน์เตอร์
        return redirect()->route('evaluation.create', ['counter_sub_id' => $request->counter_sub_id])
                         ->with('success', 'เพิ่มข้อมูลการประเมินสำเร็จ');
    }
}