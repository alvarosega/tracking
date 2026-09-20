<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorIndexController;
use App\Http\Controllers\Web\SupervisorRuteoController;
use App\Http\Controllers\Web\SupervisorTrackingController;
use App\Http\Controllers\Web\SupervisorVisitasController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn() => redirect()->route('login'));

Route::middleware(['auth', 'supervisor'])->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/', [SupervisorIndexController::class, 'index'])->name('index');
    Route::get('/ruteo', [SupervisorRuteoController::class, 'index'])->name('ruteo.index');
    Route::get('/tracking', [SupervisorTrackingController::class, 'index'])->name('tracking.index');
    Route::post('/tracking/workday/{userId}/close', [SupervisorTrackingController::class, 'closeWorkday'])->name('tracking.workday.close');
    Route::get('/visitas', [SupervisorVisitasController::class, 'index'])->name('visitas.index');
});