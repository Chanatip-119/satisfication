<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\EvaluationController;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('staff', StaffController::class);
Route::resource('counter', CounterController::class);

Route::get('/evaluation', function () {
    return view('evaluation.create'); 
})->name('evaluation.create');

Route::post('/evaluation', [EvaluationController::class, 'store'])->name('evaluation.store');

Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin.index');
Route::post('/checkin/step1', [CheckinController::class, 'step1'])->name('checkin.step1');
Route::post('/checkin/step2', [CheckinController::class, 'step2'])->name('checkin.step2');
Route::post('/checkin/step3', [CheckinController::class, 'step3'])->name('checkin.step3');
Route::post('/checkin/checkout', [CheckinController::class, 'checkout'])->name('checkin.checkout');