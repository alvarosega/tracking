<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorIndexController;
use App\Http\Controllers\Web\SupervisorRuteoController;
use App\Http\Controllers\Web\SupervisorTrackingController;
use App\Http\Controllers\Web\SupervisorVisitasController;
use App\Http\Controllers\Web\SupervisorJornadasController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn() => redirect()->route('login'));

Route::middleware(['auth', 'supervisor'])->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/', [SupervisorIndexController::class, 'index'])->name('index');

    // Módulo Ruteo
    Route::get('/ruteo', [SupervisorRuteoController::class, 'index'])->name('ruteo.index');
    Route::post('/ruteo/data', [SupervisorRuteoController::class, 'getData'])->name('ruteo.data');
    Route::get('/ruteo/cercanos', [SupervisorRuteoController::class, 'cercanos'])->name('ruteo.cercanos');
    Route::post('/ruteo/cercanos/data', [SupervisorRuteoController::class, 'getCercanosData'])->name('ruteo.cercanos.data');
    Route::post('/ruteo/cercanos/cliente/{clienteId}/ventas', [SupervisorRuteoController::class, 'getVentasCliente'])->name('ruteo.cercanos.cliente.ventas');
    Route::post('/ruteo/cercanos/cliente/{clienteId}/visitas', [SupervisorRuteoController::class, 'getVisitasCliente'])->name('ruteo.cercanos.cliente.visitas');

    
    // Módulo Tracking
    Route::get('/tracking', [SupervisorTrackingController::class, 'index'])->name('tracking.index');
    Route::post('/tracking/workday/{userId}/close', [SupervisorTrackingController::class, 'closeWorkday'])->name('tracking.workday.close');

    // Módulo Visitas
    Route::get('/visitas', [SupervisorVisitasController::class, 'index'])->name('visitas.index');

    // Módulo Jornadas
    Route::get('/jornadas', [SupervisorJornadasController::class, 'index'])->name('jornadas.index');
    Route::post('/jornadas/{userId}/close', [SupervisorJornadasController::class, 'closeSingle'])->name('jornadas.close.single');
    Route::post('/jornadas/close-all', [SupervisorJornadasController::class, 'closeAllActive'])->name('jornadas.close.all');
});