<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ExportReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;

// =========================================================================
// 1. เส้นทางสาธารณะสำหรับ Guest (ยังไม่ได้เข้าสู่ระบบ)
// หากล็อกอินอยู่แล้ว จะถูก Redirect ไปหน้า /dashboard อัตโนมัติ
// =========================================================================
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return view('auth.login');
    })->name('login');

    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');

    // Traditional login & register
    // หมายเหตุ: การจำกัดจำนวนครั้งของ login ทำใน AuthController::login() (นับเฉพาะครั้งที่ผิด)
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    // [แก้ไข ระดับ 1 ข้อ 4] จำกัดการสมัครสมาชิก 5 ครั้ง/นาที ต่อ IP
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');

    // Password Reset Routes
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');

    // [แก้ไข ระดับ 1 ข้อ 4] จำกัดการขอลิงก์รีเซ็ตทางอีเมล 5 ครั้ง/นาที ต่อ IP
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');

    // หมายเหตุ: การจำกัดการเดา PIN ทำใน AuthController::resetPasswordWithPin() (นับเฉพาะครั้งที่ผิด)
    Route::post('/forgot-password/pin', [AuthController::class, 'resetPasswordWithPin'])->name('password.reset.pin');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');

    // [แก้ไข ระดับ 1 ข้อ 4] จำกัดการตั้งรหัสผ่านใหม่ 10 ครั้ง/นาที ต่อ IP
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('password.update');
});

// =========================================================================
// 2. เส้นทางสาธารณะสำหรับ Kiosk และหน้าประเมินความพึงพอใจ (ไม่ต้องล็อกอิน)
// ให้ผู้มาใช้บริการสแกน QR Code ประเมิน หรือเจ้าหน้าที่กด Check-in หน้าเคาน์เตอร์ได้
// =========================================================================
Route::get('/evaluation/{counter_sub_id}', [EvaluationController::class, 'create'])->name('evaluation.create');
// หมายเหตุ: การกันส่งประเมินซ้ำทำใน EvaluationController::store() [ระดับ 1 ข้อ 4]
Route::post('/evaluation', [EvaluationController::class, 'store'])->name('evaluation.store');

Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
// [เพิ่มใหม่ ระดับ 3 ข้อ 17] API เช็คสถานะแบบเบา ใช้แทนการดึงหน้า /checkin ทั้งหน้าซ้ำทุก 4 วินาที
Route::get('/checkin/status', [CheckinController::class, 'status'])->name('checkin.status');
Route::post('/checkin/step1', [CheckinController::class, 'step1'])->name('checkin.step1');
// หมายเหตุ: การจำกัดการเดา PIN ทำใน CheckinController::step2() [ระดับ 1 ข้อ 4]
Route::post('/checkin/step2', [CheckinController::class, 'step2'])->name('checkin.step2');
Route::post('/checkin/step3', [CheckinController::class, 'step3'])->name('checkin.step3');
// หมายเหตุ: Check-out ได้เฉพาะเครื่องที่ใช้ Check-in ตรวจใน CheckinController::checkout() [ระดับ 1 ข้อ 7]
Route::post('/checkin/checkout', [CheckinController::class, 'checkout'])->name('checkin.checkout');

// =========================================================================
// 3. เส้นทางที่ต้องผ่านการเข้าสู่ระบบเท่านั้น (Protected Routes)
// หากยังไม่ล็อกอินแล้วพิมพ์ URL เข้ามา จะถูก Redirect ไปหน้า Login (/) ทันที
// =========================================================================
Route::middleware('auth')->group(function () {
    // [แก้ไข ระดับ 1 ข้อ 2 — แนะนำเพิ่มเติม] ออกจากระบบรับเฉพาะ POST
    // เดิม: Route::match(['get', 'post'], '/logout', ...) — GET ไม่ผ่าน CSRF ทำให้ใครก็สั่งให้ผู้ใช้ออกจากระบบได้ด้วยลิงก์
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // หน้าแดชบอร์ด
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // จัดการตารางปฏิบัติงาน (Schedule)
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule/store', [ScheduleController::class, 'store'])->middleware('can:manage-admin')->name('schedule.store');
    Route::post('/schedule/update/{id}', [ScheduleController::class, 'update'])->middleware('can:manage-admin')->name('schedule.update');
    Route::delete('/schedule/delete/{id}', [ScheduleController::class, 'destroy'])->middleware('can:manage-admin')->name('schedule.destroy');
    // [แก้ไข ระดับ 1 ข้อ 2] ลบ route ที่ให้ลบกะผ่าน GET ออกแล้ว
    // เดิม: Route::match(['get', 'delete'], '/schedule/delete/{id}', ...)->name('schedule.destroy');
    // เหตุผล: GET ไม่ผ่านการตรวจ CSRF แค่เปิดลิงก์หรือรูปภาพที่ชี้มา URL นี้ก็ลบกะได้ทันที
    Route::post('/schedule/import', [ScheduleController::class, 'importExcel'])->middleware('can:manage-admin')->name('schedule.import');
    Route::get('/schedule/download-template', [ScheduleController::class, 'downloadTemplate'])->name('schedule.downloadTemplate');

    // จัดการบุคลากร (Staff)
    Route::middleware('can:manage-admin')->group(function () {
        Route::get('/staff/export-csv', [StaffController::class, 'exportCsv'])->name('staff.exportCsv');
        Route::get('/staff/download-template', [StaffController::class, 'downloadTemplate'])->name('staff.downloadTemplate');
        Route::post('/staff/import', [StaffController::class, 'importExcel'])->name('staff.import');
        Route::post('/staff/reset-pins', [StaffController::class, 'resetAllPins'])->name('staff.resetPins');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('/staff/{id}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.resetPassword');
        Route::match(['put', 'patch'], '/staff/{id}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{id}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    // หมายเหตุ: พนักงานดูได้เฉพาะข้อมูลตัวเอง ตรวจใน StaffController::show() [ระดับ 1 ข้อ 6]
    Route::get('/staff/{id}', [StaffController::class, 'show'])->name('staff.show');

    // จัดการเคาน์เตอร์ (Counter)
    Route::get('/counter', [CounterController::class, 'index'])->name('counter.index');
    Route::middleware('can:manage-admin')->group(function () {
        Route::get('/counter/create', [CounterController::class, 'create'])->name('counter.create');
        Route::post('/counter', [CounterController::class, 'store'])->name('counter.store');
        Route::match(['put', 'patch'], '/counter/{id}', [CounterController::class, 'update'])->name('counter.update');
        Route::delete('/counter/{id}', [CounterController::class, 'destroy'])->name('counter.destroy');
        Route::post('/counter/{counterId}/sub', [CounterController::class, 'storeSub'])->name('counter.storeSub');
        Route::delete('/counter/{counterId}/sub/{subId}', [CounterController::class, 'destroySub'])->name('counter.destroySub');
    });

    // รายงาน (Reports)
    Route::get('/report/counter', [ReportController::class, 'counterReport'])->name('report.counter');
    Route::get('/report/staff', [ReportController::class, 'staffReport'])->name('report.staff');

    // ส่งออกรายงาน (Export Report) — จำกัดไว้สำหรับผู้ดูแล
    Route::middleware('can:manage-admin')->group(function () {
        Route::get('/export-report', [ExportReportController::class, 'index'])->name('report.export.index');
        Route::post('/export-report/download', [ExportReportController::class, 'export'])->name('report.export.download');
    });

    // จัดการ QR Code
    Route::get('/qrcode', [\App\Http\Controllers\QrCodeController::class, 'index'])->name('qrcode.index');
});