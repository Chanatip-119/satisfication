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
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'staff_id'              => ['required', 'string', 'max:50'],
            'password'              => ['required', 'confirmed', Password::min(6)],
        ], [
            'name.required'         => 'กรุณากรอกชื่อ-นามสกุล',
            'email.required'        => 'กรุณากรอกอีเมล',
            'email.email'           => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique'          => 'อีเมลนี้ถูกใช้งานแล้ว',
            'staff_id.required'     => 'กรุณากรอกรหัสพนักงาน',
            'password.required'     => 'กรุณากรอกรหัสผ่าน',
            'password.confirmed'    => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
            'password.min'          => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('login')
            ->with('success', 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ');
    }
}
