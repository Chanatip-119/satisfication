<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter; // [แก้ไข ระดับ 1 ข้อ 4] ใช้จำกัดจำนวนครั้งที่กรอกผิด
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * แสดงหน้าฟอร์มขอลืมรหัสผ่าน
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมล
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'กรุณากรอกอีเมลของคุณ',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
        ]);

        $staff = Staff::where('staff_email', $request->email)->first();

        if (!$staff) {
            return back()->withErrors(['email' => 'ไม่พบบัญชีผู้ใช้งานที่ใช้อีเมลนี้ในระบบ'])->withInput();
        }

        // สร้าง Token แบบสุ่มที่มีความปลอดภัยสูง
        $token = Str::random(64);

        // บันทึก Token ลงตาราง password_reset_tokens (hash ก่อนเก็บเพื่อความปลอดภัย)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $staff->staff_email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        // ส่งแจ้งเตือนทางอีเมล
        $staff->sendPasswordResetNotification($token);

        $resetUrl = route('password.reset', ['token' => $token, 'email' => $staff->staff_email]);

        $redirect = back()->with('status', 'ส่งลิงก์สำหรับรีเซ็ตรหัสผ่านไปยังอีเมลของคุณเรียบร้อยแล้ว กรุณาตรวจสอบกล่องข้อความ');

        // ===== [แก้ไข ระดับ 1 ข้อ 5] แสดงลิงก์ตรงบนหน้าจอเฉพาะเครื่องพัฒนาเท่านั้น =====
        // เดิม: if (config('mail.default') === 'log') {
        // ปัญหา: ถ้าเซิร์ฟเวอร์จริงยังใช้ MAIL_MAILER=log (ค่าเริ่มต้นของ Laravel)
        //        ใครก็กรอกอีเมลของคนอื่นแล้วได้ลิงก์ตั้งรหัสผ่านใหม่ทันที
        // หมายเหตุ: บนเซิร์ฟเวอร์จริงต้องตั้ง APP_ENV=production ใน .env
        if (app()->environment('local') && config('mail.default') === 'log') {
            $redirect->with('direct_reset_url', $resetUrl);
        }
        // ===== [จบ ข้อ 5] =====

        return $redirect;
    }

    /**
     * รีเซ็ตรหัสผ่านด้วยตัวเองโดยใช้ Email + รหัสพนักงาน + PIN Code (ไม่ต้องผ่านอีเมล)
     */
    public function resetPasswordWithPin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'staff_id' => ['required', 'string'],
            'pin' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'email.required' => 'กรุณากรอกอีเมลของคุณ',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'staff_id.required' => 'กรุณากรอกรหัสพนักงาน',
            'pin.required' => 'กรุณากรอกรหัส PIN 4 หลัก',
            'password.required' => 'กรุณากรอกรหัสผ่านใหม่',
            'password.confirmed' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
            'password.min' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
        ]);

        // ===== [แก้ไข ระดับ 1 ข้อ 4] จำกัดการเดา PIN: ต่ออีเมล 5 ครั้ง และต่อ IP 15 ครั้ง ภายใน 15 นาที =====
        $emailKey = 'pin-reset:' . mb_strtolower(trim($request->email)) . '|' . $request->ip();
        $ipKey = 'pin-reset-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($emailKey, 5) || RateLimiter::tooManyAttempts($ipKey, 15)) {
            $minutes = ceil(max(RateLimiter::availableIn($emailKey), RateLimiter::availableIn($ipKey), 60) / 60);
            return back()
                ->withErrors(['pin_reset_error' => "ยืนยันตัวตนผิดหลายครั้งเกินไป กรุณารอประมาณ {$minutes} นาทีแล้วลองใหม่"])
                ->withInput($request->only('email', 'staff_id'));
        }

        $staff = Staff::where('staff_email', $request->email)->first();
        $inputStaffIdNum = (int) preg_replace('/[^0-9]/', '', $request->staff_id);
        $inputPin = preg_replace('/[^0-9]/', '', $request->pin);

        // เดิม: ตรวจทีละขั้นและแจ้ง error แยกกัน (ไม่พบอีเมล / รหัสพนักงานไม่ตรง / PIN ไม่ถูกต้อง)
        // ใหม่: ใช้ข้อความเดียวกันทุกกรณี เพื่อไม่ให้ผู้ไม่หวังดีรู้ว่าข้อมูลส่วนไหนถูกแล้ว
        //       และไม่ส่งรหัสผ่านกับ PIN กลับไปใน old() (ใช้ withInput เฉพาะ email, staff_id)
        if (!$staff
            || $inputStaffIdNum <= 0
            || (int) $staff->staff_id !== $inputStaffIdNum
            || !$staff->verifyPinCode($inputPin)) {
            RateLimiter::hit($emailKey, 900);
            RateLimiter::hit($ipKey, 900);

            return back()
                ->withErrors(['pin_reset_error' => 'อีเมล รหัสพนักงาน หรือ PIN ไม่ถูกต้อง'])
                ->withInput($request->only('email', 'staff_id'));
        }

        RateLimiter::clear($emailKey);
        // ===== [จบ ข้อ 4: resetPasswordWithPin] =====

        // อัปเดตรหัสผ่านใหม่
        $staff->password = Hash::make($request->password);
        $staff->save();

        // ลบ token รีเซ็ตที่ค้างอยู่ถ้ามี
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('success', 'รีเซ็ตรหัสผ่านสำเร็จ! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
    }

    /**
     * แสดงหน้าตั้งรหัสผ่านใหม่
     */
    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * อัปเดตรหัสผ่านใหม่
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'password.required' => 'กรุณากรอกรหัสผ่านใหม่',
            'password.confirmed' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
            'password.min' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return back()->withErrors(['email' => 'ไม่พบคำขอรีเซ็ตรหัสผ่าน หรือลิงก์นี้หมดอายุไปแล้ว'])->withInput();
        }

        // ตรวจสอบอายุของลิงก์ (60 นาที)
        if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors(['email' => 'ลิงก์รีเซ็ตรหัสผ่านหมดอายุแล้ว กรุณาส่งคำขอใหม่อีกครั้ง'])->withInput();
        }

        // ตรวจสอบความถูกต้องของ Token
        if (!Hash::check($request->token, $resetRecord->token)) {
            return back()->withErrors(['email' => 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้อง'])->withInput();
        }

        $staff = Staff::where('staff_email', $request->email)->first();
        if (!$staff) {
            return back()->withErrors(['email' => 'ไม่พบบัญชีผู้ใช้งานในระบบ'])->withInput();
        }

        // บันทึกรหัสผ่านใหม่
        $staff->password = Hash::make($request->password);
        $staff->save();

        // ลบ Token ที่ใช้งานแล้ว
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('success', 'เปลี่ยนรหัสผ่านใหม่สำเร็จแล้ว! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่');
    }
    /**
     * บันทึกข้อมูลสมาชิกใหม่
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:staff,staff_email'],
            'staff_id' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'name.required' => 'กรุณากรอกชื่อ-นามสกุล',
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้งานแล้ว',
            'staff_id.required' => 'กรุณากรอกรหัสพนักงาน',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
            'password.confirmed' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
        ]);

        \App\Models\Staff::create([
            'staff_name' => $request->name,
            'staff_email' => $request->email,
            // [แก้ไข ระดับ 1 ข้อ 3] สุ่ม PIN ที่ไม่ซ้ำกับบุคลากรคนอื่น (ในฟอร์มไม่มีให้กรอก)
            // เดิม: (string) rand(1000, 9999) — สุ่มโดยไม่ตรวจว่าซ้ำ ทำให้ Check-in ผิดคนได้
            'staff_pincode' => Staff::generateUniquePin(),
            'password' => Hash::make($request->password),
            'role_id' => Staff::ROLE_EMPLOYEE,
        ]);

        return redirect()->route('login')
            ->with('success', 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required', // can be email, staff_id (e.g. BUU-010 or 10), or username
            'password' => 'required'
        ]);

        $username = trim($request->username);

        // ===== [แก้ไข ระดับ 1 ข้อ 4] จำกัดการเดารหัสผ่าน (นับเฉพาะครั้งที่ผิด) =====
        // - ต่อบัญชี: ผิดได้ 5 ครั้ง/นาที
        // - ต่อ IP: ผิดได้ 20 ครั้ง/5 นาที (กันการไล่ลองรหัส 123456 กับทุกบัญชี)
        $userKey = 'login:' . mb_strtolower($username) . '|' . $request->ip();
        $ipKey = 'login-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($userKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            $seconds = max(RateLimiter::availableIn($userKey), RateLimiter::availableIn($ipKey), 60);
            return back()->withErrors([
                'username' => 'เข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอประมาณ ' . ceil($seconds / 60) . ' นาทีแล้วลองใหม่',
            ])->onlyInput('username');
        }
        // ===== [จบ ข้อ 4: ตรวจก่อนล็อกอิน] =====

        $fieldType = 'staff_name';
        $loginValue = $username;

        // ตรวจสอบว่าเป็นอีเมล, รหัสพนักงาน (ตัวเลข หรือ BUU-xxx), หรือชื่อผู้ใช้งาน
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $fieldType = 'staff_email';
        } elseif (is_numeric($username)) {
            $fieldType = 'staff_id';
            $loginValue = (int) $username;
        } elseif (preg_match('/^buu\-([0-9]+)$/i', $username, $matches)) {
            $fieldType = 'staff_id';
            $loginValue = (int) $matches[1];
        }

        if (\Illuminate\Support\Facades\Auth::attempt([$fieldType => $loginValue, 'password' => $request->password])) {
            RateLimiter::clear($userKey); // [แก้ไข ระดับ 1 ข้อ 4] ล็อกอินสำเร็จ ล้างตัวนับของบัญชีนี้
            $request->session()->regenerate();
            return redirect()->intended('dashboard');
        }

        // [แก้ไข ระดับ 1 ข้อ 4] ล็อกอินไม่สำเร็จ นับครั้งที่ผิด (บัญชี 60 วินาที, IP 5 นาที)
        RateLimiter::hit($userKey, 60);
        RateLimiter::hit($ipKey, 300);

        return back()->withErrors([
            'username' => 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        \Illuminate\Support\Facades\Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}