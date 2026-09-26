<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ExportReportController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/staff/export-csv', [StaffController::class, 'exportCsv'])->name('staff.exportCsv');
Route::get('/staff/download-template', [StaffController::class, 'downloadTemplate'])->name('staff.downloadTemplate');
Route::post('/staff/import', [StaffController::class, 'importExcel'])->name('staff.import');
Route::resource('staff', StaffController::class);
Route::post('/staff/reset-pins', [StaffController::class, 'resetAllPins'])->name('staff.resetPins');

Route::resource('counter', CounterController::class);
Route::post('/counter/{counterId}/sub', [CounterController::class, 'storeSub'])->name('counter.storeSub');
Route::delete('/counter/{counterId}/sub/{subId}', [CounterController::class, 'destroySub'])->name('counter.destroySub');

Route::resource('schedule', ScheduleController::class);

Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
Route::post('/checkin/step1', [CheckinController::class, 'step1'])->name('checkin.step1');
Route::post('/checkin/step2', [CheckinController::class, 'step2'])->name('checkin.step2');
Route::post('/checkin/step3', [CheckinController::class, 'step3'])->name('checkin.step3');
Route::post('/checkin/checkout', [CheckinController::class, 'checkout'])->name('checkin.checkout');

Route::get('/evaluation/{counter_sub_id}', [EvaluationController::class, 'create'])->name('evaluation.create');
Route::post('/evaluation', [EvaluationController::class, 'store'])->name('evaluation.store');

Route::get('/report/counter', [ReportController::class, 'counterReport'])->name('report.counter');
Route::get('/report/staff', [ReportController::class, 'staffReport'])->name('report.staff');

Route::get('/export-report', [ExportReportController::class, 'index'])->name('report.export.index');
Route::post('/export-report/download', [ExportReportController::class, 'export'])->name('report.export.download');

Route::get('/schedule', [App\Http\Controllers\ScheduleController::class, 'index'])->name('schedule.index');
Route::post('/schedule/store', [App\Http\Controllers\ScheduleController::class, 'store'])->name('schedule.store');
Route::post('/schedule/update/{id}', [App\Http\Controllers\ScheduleController::class, 'update'])->name('schedule.update');
Route::get('/schedule/delete/{id}', [App\Http\Controllers\ScheduleController::class, 'destroy'])->name('schedule.destroy');