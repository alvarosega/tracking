<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\SanitizationController;
use App\Http\Controllers\Api\BaseClienteController;
use App\Http\Controllers\Api\BaseOportunidadesController;
use App\Http\Controllers\Api\PlanRuteoController;
use App\Http\Controllers\Api\VisitaRuteoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Tracking periódico
Route::post('/tracking/sync', [TrackingController::class, 'sync']);

// Sanitización (Lectura y Auditoría previa)
Route::get('/sanitization/clients', [SanitizationController::class, 'getClients']);
Route::post('/sanitization/audit', [SanitizationController::class, 'storeAudit']);

// Endpoints protegidos por token Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/reference-clients', [BaseClienteController::class, 'index']);
    Route::get('/opportunities', [BaseOportunidadesController::class, 'index']);
    Route::get('/plan-ruteo', [PlanRuteoController::class, 'index']);
    
    // Registro de Visitas en Campo con Fotografía
    Route::post('/visitas/store', [VisitaController::class, 'store']);
});