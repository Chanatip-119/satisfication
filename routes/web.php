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