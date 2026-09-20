<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\PlanRuteoController;
use App\Http\Controllers\Api\VisitaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OportunidadesController;
use App\Http\Controllers\Api\WorkdayController;

// Autenticación
Route::post('/login', [AuthController::class, 'login']);

// Endpoints protegidos por token Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Tracking periódico de ubicación
    Route::post('/tracking/sync', [TrackingController::class, 'sync']);

    // Descarga del plan de ruteo maestro unificado
    Route::get('/plan-ruteo', [PlanRuteoController::class, 'index']);
    
    // Registro de Visitas en Campo (ambos alias para compatibilidad total con Retrofit)
    Route::post('/visitas', [VisitaController::class, 'store']);
    Route::post('/visitas/store', [VisitaController::class, 'store']);
    Route::get('/oportunidades', [OportunidadesController::class, 'index']);

    Route::get('/workday/status', [WorkdayController::class, 'status']);
    Route::post('/workday/start', [WorkdayController::class, 'start']);
    Route::post('/workday/stop', [WorkdayController::class, 'stop']);

    // --- Endpoints para el Panel del Supervisor ---
    Route::get('/supervisor/workdays/today', [WorkdayController::class, 'todayWorkdays']);
    Route::post('/supervisor/workdays/{userId}/close', [WorkdayController::class, 'forceCloseBySupervisor']);
});