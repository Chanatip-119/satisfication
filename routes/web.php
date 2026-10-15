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
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');

    // Password Reset Routes
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// =========================================================================
// 2. เส้นทางสาธารณะสำหรับ Kiosk และหน้าประเมินความพึงพอใจ (ไม่ต้องล็อกอิน)
// ให้ผู้มาใช้บริการสแกน QR Code ประเมิน หรือเจ้าหน้าที่กด Check-in หน้าเคาน์เตอร์ได้
// =========================================================================
Route::get('/evaluation/{counter_sub_id}', [EvaluationController::class, 'create'])->name('evaluation.create');
Route::post('/evaluation', [EvaluationController::class, 'store'])->name('evaluation.store');

Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
Route::post('/checkin/step1', [CheckinController::class, 'step1'])->name('checkin.step1');
Route::post('/checkin/step2', [CheckinController::class, 'step2'])->name('checkin.step2');
Route::post('/checkin/step3', [CheckinController::class, 'step3'])->name('checkin.step3');
Route::post('/checkin/checkout', [CheckinController::class, 'checkout'])->name('checkin.checkout');

// =========================================================================
// 3. เส้นทางที่ต้องผ่านการเข้าสู่ระบบเท่านั้น (Protected Routes)
// หากยังไม่ล็อกอินแล้วพิมพ์ URL เข้ามา จะถูก Redirect ไปหน้า Login (/) ทันที
// =========================================================================
Route::middleware('auth')->group(function () {
    // ออกจากระบบ (รองรับทั้ง GET จากปุ่มลิงก์ และ POST)
    Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

    // หน้าแดชบอร์ด
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // จัดการตารางปฏิบัติงาน (Schedule)
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/schedule/store', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::post('/schedule/update/{id}', [ScheduleController::class, 'update'])->name('schedule.update');
    Route::delete('/schedule/delete/{id}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::match(['get', 'delete'], '/schedule/delete/{id}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::post('/schedule/import', [ScheduleController::class, 'importExcel'])->name('schedule.import');
    Route::get('/schedule/download-template', [ScheduleController::class, 'downloadTemplate'])->name('schedule.downloadTemplate');

    // จัดการบุคลากร (Staff)
    Route::get('/staff/export-csv', [StaffController::class, 'exportCsv'])->name('staff.exportCsv');
    Route::get('/staff/download-template', [StaffController::class, 'downloadTemplate'])->name('staff.downloadTemplate');
    Route::post('/staff/import', [StaffController::class, 'importExcel'])->name('staff.import');
    Route::resource('staff', StaffController::class);
    Route::post('/staff/reset-pins', [StaffController::class, 'resetAllPins'])->name('staff.resetPins');

    // จัดการเคาน์เตอร์ (Counter)
    Route::resource('counter', CounterController::class);
    Route::post('/counter/{counterId}/sub', [CounterController::class, 'storeSub'])->name('counter.storeSub');
    Route::delete('/counter/{counterId}/sub/{subId}', [CounterController::class, 'destroySub'])->name('counter.destroySub');

    // รายงาน (Reports)
    Route::get('/report/counter', [ReportController::class, 'counterReport'])->name('report.counter');
    Route::get('/report/staff', [ReportController::class, 'staffReport'])->name('report.staff');

    // ส่งออกรายงาน (Export Report)
    Route::get('/export-report', [ExportReportController::class, 'index'])->name('report.export.index');
    Route::post('/export-report/download', [ExportReportController::class, 'export'])->name('report.export.download');

    // จัดการ QR Code
    Route::get('/qrcode', [\App\Http\Controllers\QrCodeController::class, 'index'])->name('qrcode.index');
});
