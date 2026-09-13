<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorTrackingController;
use App\Http\Controllers\Web\SupervisorVisitasController;
use Illuminate\Support\Facades\Route;

// Rutas públicas de sesión
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn() => redirect()->route('login'));

// Rutas protegidas del supervisor
Route::middleware(['auth', 'supervisor'])->prefix('supervisor')->group(function () {
    Route::get('/', fn() => redirect()->route('supervisor.visitas.index'));
    Route::get('/visitas', [SupervisorVisitasController::class, 'index'])->name('supervisor.visitas.index');
    Route::get('/tracking', [SupervisorTrackingController::class, 'index'])->name('supervisor.tracking.index');
});