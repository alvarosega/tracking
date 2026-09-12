<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SanitizationController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\Api\BaseClienteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Tracking
Route::post('/tracking/sync', [TrackingController::class, 'sync']);

// Sanitización
Route::get('/sanitization/clients', [SanitizationController::class, 'getClients']);
Route::post('/sanitization/audit', [SanitizationController::class, 'storeAudit']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/reference-clients', [BaseClienteController::class, 'index']);
});