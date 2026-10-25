<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // หากเป็นโหมด Log Mailer ให้แนบ Direct URL เพื่อความสะดวกในการทดสอบโดยไม่ต้องเปิด log
        if (config('mail.default') === 'log') {
            $redirect->with('direct_reset_url', $resetUrl);
        }

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

        $staff = Staff::where('staff_email', $request->email)->first();

        if (!$staff) {
            return back()->withErrors(['pin_reset_error' => 'ไม่พบบัญชีผู้ใช้งานที่ตรงกับอีเมลนี้'])->withInput();
        }

        // ตรวจสอบ staff_id
        $inputStaffIdNum = (int) preg_replace('/[^0-9]/', '', $request->staff_id);
        if ($inputStaffIdNum <= 0 || (int)$staff->staff_id !== $inputStaffIdNum) {
            return back()->withErrors(['pin_reset_error' => 'รหัสพนักงานไม่ตรงกับข้อมูลในระบบ'])->withInput();
        }

        // ตรวจสอบ PIN Code 4 หลัก
        $inputPin = preg_replace('/[^0-9]/', '', $request->pin);
        if (trim((string)$staff->staff_pincode) !== $inputPin) {
            return back()->withErrors(['pin_reset_error' => 'รหัส PIN 4 หลักไม่ถูกต้อง'])->withInput();
        }

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
            'staff_pincode' => (string) rand(1000, 9999), // กำหนด Pincode เริ่มต้นแบบสุ่มไปก่อน (เพราะในฟอร์มไม่มีให้กรอก)
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
            $request->session()->regenerate();
            return redirect()->intended('dashboard');
        }

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
