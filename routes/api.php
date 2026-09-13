<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\PlanRuteoController;
use App\Http\Controllers\Api\VisitaController;
use Illuminate\Support\Facades\Route;

// Autenticación
Route::post('/login', [AuthController::class, 'login']);

// Endpoints protegidos por token Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Tracking periódico de ubicación
    Route::post('/tracking/sync', [TrackingController::class, 'sync']);

    // Descarga del plan de ruteo maestro unificado
    Route::get('/plan-ruteo', [PlanRuteoController::class, 'index']);
    
    // Registro de Visitas en Campo (Ruta y Clientes Oportunidad con Fotografía)
    Route::post('/visitas/store', [VisitaController::class, 'store']);
});