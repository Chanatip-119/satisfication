<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
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
            'role_id' => 2,
        ]);

        return redirect()->route('login')
            ->with('success', 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required', // can be email or username
            'password' => 'required'
        ]);

        // ตรวจสอบว่าสิ่งที่กรอกมาคืออีเมล หรือ ชื่อผู้ใช้งาน
        $fieldType = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'staff_email' : 'staff_name';

        if (\Illuminate\Support\Facades\Auth::attempt([$fieldType => $request->username, 'password' => $request->password])) {
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
