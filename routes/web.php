<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorIndexController;
use App\Http\Controllers\Web\SupervisorJornadasController;
use App\Http\Controllers\Web\SupervisorRuteoController;
use App\Http\Controllers\Web\SupervisorTrackingController;
use App\Http\Controllers\Web\SupervisorVisitasController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\SupervisorAltasEdicionesController;
use App\Http\Controllers\Web\SupervisorHorariosController;

use App\Http\Controllers\Web\SupervisorPreventasController;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', fn() => redirect()->route('login'));

Route::middleware(['auth', 'supervisor'])->prefix('supervisor')->name('supervisor.')->group(function () {
    Route::get('/', [SupervisorIndexController::class, 'index'])->name('index');

    // Módulo Ruteo & Cobertura
    Route::get('/ruteo', [SupervisorRuteoController::class, 'index'])->name('ruteo.index');
    Route::post('/ruteo/data', [SupervisorRuteoController::class, 'getData'])->name('ruteo.data');
    Route::get('/ruteo/cercanos', [SupervisorRuteoController::class, 'cercanos'])->name('ruteo.cercanos');
    Route::post('/ruteo/cercanos/data', [SupervisorRuteoController::class, 'getCercanosData'])->name('ruteo.cercanos.data');
    Route::post('/ruteo/cercanos/cliente/{clienteId}/ventas', [SupervisorRuteoController::class, 'getVentasCliente'])->name('ruteo.cercanos.cliente.ventas');
    Route::post('/ruteo/cercanos/cliente/{clienteId}/visitas', [SupervisorRuteoController::class, 'getVisitasCliente'])->name('ruteo.cercanos.cliente.visitas');

    // Módulo Independiente: Auditoría Preventas
    Route::prefix('preventas')->name('preventas.')->group(function () {
        Route::get('/', [SupervisorPreventasController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorPreventasController::class, 'data'])->name('data');
    });

    // Módulo Tracking
    Route::prefix('tracking')->name('tracking.')->group(function () {
        // Submódulo 1: Histórico de Patrones Semanales
        Route::get('/historico', [SupervisorTrackingController::class, 'historico'])->name('historico');
        Route::post('/historico/data', [SupervisorTrackingController::class, 'historicoData'])->name('historico.data');

        // Submódulo 2: Monitoreo en el Día (Recorrido + Telemetría en Vivo)
        Route::get('/', [SupervisorTrackingController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorTrackingController::class, 'data'])->name('data');
        Route::post('/workday/{userId}/close', [SupervisorTrackingController::class, 'closeWorkday'])->name('workday.close');
    });
    Route::prefix('altas-ediciones')->name('altas-ediciones.')->group(function () {
        Route::get('/', [SupervisorAltasEdicionesController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorAltasEdicionesController::class, 'data'])->name('data');
    });
    // Módulo Visitas
    Route::get('/visitas', [SupervisorVisitasController::class, 'index'])->name('visitas.index');
    Route::post('/visitas/data', [SupervisorVisitasController::class, 'data'])->name('visitas.data');
    Route::post('/visitas/fotos/{clienteId}', [SupervisorVisitasController::class, 'fotosCliente'])->name('visitas.fotos');

    Route::get('/visitas/avance', [SupervisorVisitasController::class, 'avance'])->name('visitas.avance');
    Route::post('/visitas/avance/data', [SupervisorVisitasController::class, 'avanceData'])->name('visitas.avance.data');

    // Módulo Jornadas
    Route::get('/jornadas', [SupervisorJornadasController::class, 'index'])->name('jornadas.index');
    Route::post('/jornadas/{userId}/close', [SupervisorJornadasController::class, 'closeSingle'])->name('jornadas.close.single');
    Route::post('/jornadas/close-all', [SupervisorJornadasController::class, 'closeAllActive'])->name('jornadas.close.all');
    
    
// Submódulo Horarios y Control en Vivo
    Route::get('/horarios', [SupervisorHorariosController::class, 'index'])->name('horarios.index');
    Route::post('/horarios/data', [SupervisorHorariosController::class, 'data'])->name('horarios.data');
    Route::post('/horarios/update', [SupervisorHorariosController::class, 'update'])->name('horarios.update');
    Route::post('/horarios/active-workdays', [SupervisorHorariosController::class, 'activeWorkdays'])->name('horarios.active-workdays');
    Route::post('/horarios/close/{userId}', [SupervisorHorariosController::class, 'forceCloseWorkday'])->name('horarios.close');
});