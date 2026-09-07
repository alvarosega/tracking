<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Nota: Sin middleware auth:sanctum por ahora, dado que el OkHttp actual envía la sincronización sin Bearer Token
Route::post('/tracking/sync', [TrackingController::class, 'sync']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});