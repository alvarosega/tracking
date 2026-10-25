<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppModuleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteInventarioController;
use App\Http\Controllers\Api\OportunidadesController;
use App\Http\Controllers\Api\PedidoRechazadoController;
use App\Http\Controllers\Api\PlanRuteoController;
use App\Http\Controllers\Api\SaneamientoController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\VisitaController;
use App\Http\Controllers\Api\WorkdayController;
use Illuminate\Support\Facades\Route;

// Autenticación pública
Route::post('/login', [AuthController::class, 'login']);

// Endpoints protegidos por token Sanctum
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Control Dinámico de Módulos (Android App)
    Route::get('/app/modulos', [AppModuleController::class, 'index']);

    // Control de Jornada Laboral (Workday)
    Route::get('/workday/status', [WorkdayController::class, 'status']);
    Route::post('/workday/start', [WorkdayController::class, 'start']);
    Route::post('/workday/stop', [WorkdayController::class, 'stop']);

    // Tracking periódico de ubicación (restringido a jornada activa)
    Route::post('/tracking/sync', [TrackingController::class, 'sync'])->middleware('workday.active');

    // Descarga del plan de ruteo maestro unificado
    Route::get('/plan-ruteo', [PlanRuteoController::class, 'index']);

    // Registro de Visitas en Campo (ambos alias para compatibilidad total con Retrofit)
    Route::post('/visitas', [VisitaController::class, 'store']);
    Route::post('/visitas/store', [VisitaController::class, 'store']);

    // Oportunidades comerciales
    Route::get('/oportunidades', [OportunidadesController::class, 'index']);

    // Saneamiento de Clientes en Campo (Altas y Ediciones)
    Route::post('/saneamiento', [SaneamientoController::class, 'store']);
    Route::post('/saneamiento-base', [SaneamientoController::class, 'store']);

    // Módulo Pedidos Rechazados (Android App - Soporte plural y singular para Retrofit)
    foreach (['pedidos-rechazados', 'pedido-rechazado'] as $prefix) {
        Route::prefix($prefix)->group(function (): void {
            Route::get('/motivos', [PedidoRechazadoController::class, 'motivos']);
            Route::get('/preventas-pendientes', [PedidoRechazadoController::class, 'preventasPendientes']);
            Route::post('/', [PedidoRechazadoController::class, 'store']);
            Route::post('/store', [PedidoRechazadoController::class, 'store']);
            Route::get('/historial', [PedidoRechazadoController::class, 'historial']);
        });
    }

    // Módulo Inventario de Clientes en PDV (Android App - Soporte plural y singular para Retrofit)
    foreach (['inventarios-cliente', 'inventario-cliente'] as $prefix) {
        Route::prefix($prefix)->group(function (): void {
            Route::get('/historial-productos/{clienteId}', [ClienteInventarioController::class, 'historialProductos']);
            Route::post('/', [ClienteInventarioController::class, 'store']);
            Route::post('/store', [ClienteInventarioController::class, 'store']);
            Route::get('/historial', [ClienteInventarioController::class, 'historial']);
        });
    }

    // Endpoints operativos de Workday para supervisión
    Route::get('/supervisor/workdays/today', [WorkdayController::class, 'todayWorkdays']);
    Route::post('/supervisor/workdays/{userId}/close', [WorkdayController::class, 'forceCloseBySupervisor']);
});
