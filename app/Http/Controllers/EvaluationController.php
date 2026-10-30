<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CounterSub;
use App\Models\Checkin;
use App\Models\Evaluation;
use App\Services\CheckinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter; // [แก้ไข ระดับ 1 ข้อ 4] ใช้กันการกดส่งประเมินซ้ำ
// [แก้ไข ระดับ 4] ลบ use App\Models\Qr_Code; — ไม่มี Model นี้ในโปรเจกต์และไม่ได้ถูกใช้
// [รายงาน+Online] ลบ use App\Models\Staff และ Carbon\Carbon ที่ไม่ได้ใช้ (ใช้ CheckinService::now() แทน)

class EvaluationController extends Controller
{
    /**
     * [รายงาน+Online ข้อ 4] ถ้าผู้ให้บริการ Check-out ไปไม่เกินกี่นาที ยังรับการประเมินของรอบนั้นอยู่
     * (ผู้รับบริการเปิดหน้าประเมินค้างไว้ แล้วเจ้าหน้าที่ Check-out ก่อนกดส่ง)
     */
    private const LATE_SUBMIT_MINUTES = 15;

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
        $counterSub = CounterSub::with('counter')->find($counter_sub_id);
        $counterName = CounterSub::labelFor($counter_sub_id)
            . ($counterSub && $counterSub->counter ? ' — ' . $counterSub->counter->counter_location : '');

        // ===== [รายงาน+Online ข้อ 4] เคาน์เตอร์ Online: ประเมินได้ตลอดโดยไม่ต้องมีคน Check-in =====
        if ($counterSub && $counterSub->counter && $counterSub->counter->is_online) {
            $isOpen = (bool) $counterSub->is_active && (bool) $counterSub->counter->is_active;

            return view('evaluation.create', [
                'is_closed'      => !$isOpen,
                'checkin_id'     => null, // ไม่มีคน Check-in — คะแนนเป็นของเคาน์เตอร์
                'counter_sub_id' => $counterSub->counter_sub_id,
                'counter_name'   => $counterName,
            ]);
        }
        // ===== [จบ ข้อ 4: create] =====

        // ค้นหา Check-in ของเคาน์เตอร์นี้ ที่ยังไม่ Check-out, ต้องเป็นของวันนี้เท่านั้น และดึงคนล่าสุด
        // [รายงาน+Online] ใช้วันที่ไทยชัดเจน (เดิม Carbon::today() ขึ้นกับ timezone ของแอป)
        $activeCheckin = Checkin::whereHas('schedule', function($query) use ($counter_sub_id) {
            $query->where('counter_sub_id', $counter_sub_id);
        })
        ->whereNull('checkout_at')
        ->whereDate('checkin_at', CheckinService::now()->toDateString()) // ป้องกันพนักงานเมื่อวานลืม Check-out
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
            // [รายงาน+Online] คืนค่าเป็น 2500 ตามขนาดคอลัมน์จริงใน DB (varchar(2500))
            // [ระดับ 2 ข้อ 11] เคยลดเป็น 255 เพราะเข้าใจว่าคอลัมน์เป็น varchar(255)
            // แต่ DB จริงเป็น 2500 และมีความคิดเห็นจริงยาว 272 ตัวอักษรอยู่แล้ว
            'comment' => 'nullable|string|max:2500',
            // [รายงาน+Online ข้อ 4] checkin_id ว่างได้ (เคาน์เตอร์ Online) — ตรวจความถูกต้องด้านล่าง
            'checkin_id' => 'nullable|integer',
            'counter_sub_id' => 'required|integer',
        ], [
            // [แก้ไข ระดับ 2 ข้อ 11] ข้อความ error ภาษาไทยที่ผู้รับบริการเข้าใจได้
            'rating.required' => 'กรุณาเลือกระดับความพึงพอใจ',
            'comment.max' => 'ข้อเสนอแนะต้องไม่เกิน 2500 ตัวอักษร',
        ]);

        $counterSubId = (int) $request->counter_sub_id;
        $backToForm = fn (string $message) => redirect()
            ->route('evaluation.create', ['counter_sub_id' => $counterSubId])
            ->withErrors(['rating' => $message])
            ->withInput($request->only('comment'));

        // ===== [รายงาน+Online ข้อ 4] ตรวจว่าเป็นการประเมินแบบไหน และข้อมูลถูกต้องจริง =====
        $counterSub = CounterSub::with('counter')->find($counterSubId);
        if (!$counterSub || !$counterSub->counter) {
            return $backToForm('ไม่พบเคาน์เตอร์นี้ กรุณาสแกน QR Code ใหม่');
        }

        $checkinId = null;
        if ($counterSub->counter->is_online) {
            // เคาน์เตอร์ Online: ต้องเปิดใช้งานอยู่ ไม่ผูกกับคน Check-in
            if (!$counterSub->is_active || !$counterSub->counter->is_active) {
                return $backToForm('เคาน์เตอร์นี้ปิดรับการประเมินแล้ว');
            }
            $deviceKeySuffix = 'online-' . $counterSubId;
        } else {
            // เคาน์เตอร์ปกติ: checkin_id ต้องเป็นรอบ Check-in ของเคาน์เตอร์นี้จริง และยังไม่ Check-out
            // (หรือเพิ่ง Check-out ไม่เกิน 15 นาที)
            // เดิม: ตรวจแค่ว่า checkin_id มีอยู่ในระบบ — ส่งเลขของรอบอื่นมาก็ให้คะแนนคนอื่นได้
            $checkin = $request->checkin_id ? Checkin::with('schedule')->find((int) $request->checkin_id) : null;
            $isSameCounter = $checkin && $checkin->schedule && (int) $checkin->schedule->counter_sub_id === $counterSubId;
            $isStillServing = $checkin && (
                $checkin->checkout_at === null
                || CheckinService::parse($checkin->checkout_at)->greaterThanOrEqualTo(CheckinService::now()->subMinutes(self::LATE_SUBMIT_MINUTES))
            );
            if (!$isSameCounter || !$isStillServing) {
                return $backToForm('เจ้าหน้าที่ประจำเคาน์เตอร์เปลี่ยนแล้ว กรุณาประเมินใหม่อีกครั้ง');
            }
            $checkinId = (int) $checkin->checkin_id;
            $deviceKeySuffix = (string) $checkinId;
        }
        // ===== [จบ ข้อ 4: ตรวจข้อมูล] =====

        // ===== [แก้ไข ระดับ 1 ข้อ 4] กันการกดส่งประเมินซ้ำ / ปั่นคะแนน =====
        // - ต่อเครื่อง (session) ต่อการ Check-in (หรือต่อเคาน์เตอร์ Online): ส่งได้ 1 ครั้งภายใน 2 นาที
        // - ต่อ IP: ไม่เกิน 60 ครั้งใน 10 นาที (เผื่อผู้ใช้หลายคนใช้ Wi-Fi ห้องสมุดร่วมกัน)
        $deviceKey = 'evaluation:' . $request->session()->getId() . ':' . $deviceKeySuffix;
        $ipKey = 'evaluation-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($deviceKey, 1) || RateLimiter::tooManyAttempts($ipKey, 60)) {
            return $backToForm('คุณเพิ่งส่งการประเมินไปแล้ว กรุณารอสักครู่ก่อนส่งอีกครั้ง');
        }

        RateLimiter::hit($deviceKey, 120);
        RateLimiter::hit($ipKey, 600);
        // ===== [จบ ข้อ 4 ระดับ 1] =====

        // บันทึกเฉพาะฟิลด์ที่ต้องใช้ ด้วยค่าที่ตรวจแล้ว
        // [รายงาน+Online ข้อ 4] เก็บ counter_sub_id ทุกครั้ง (รายงานใช้คอลัมน์นี้คอลัมน์เดียว)
        Evaluation::create([
            // rating ใน DB เป็น ENUM('1'..'5') จึงส่งเป็นข้อความ
            // (ถ้าส่งเป็นตัวเลข MySQL จะตีความเป็น "ลำดับใน ENUM" แทนค่า — ตอนนี้บังเอิญตรงกัน แต่เปราะบาง)
            'rating'         => (string) (int) $request->rating,
            'comment'        => $request->comment,
            'checkin_id'     => $checkinId,
            'counter_sub_id' => $counterSubId,
        ]);

        // ส่ง counter_sub_id กลับไปใน Route เพื่อให้หน้าโหลดซ้ำได้ถูกเคาน์เตอร์
        return redirect()->route('evaluation.create', ['counter_sub_id' => $counterSubId])
                         ->with('success', 'เพิ่มข้อมูลการประเมินสำเร็จ');
    }
}