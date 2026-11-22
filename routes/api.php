<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AltaController;
use App\Http\Controllers\Api\AppModuleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteInventarioController;
use App\Http\Controllers\Api\FronteraController;
use App\Http\Controllers\Api\OportunidadesController;
use App\Http\Controllers\Api\PedidoRechazadoController;
use App\Http\Controllers\Api\PizarraController;
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

    // Descarga del plan de ruteo maestro unificado (Submódulo Ruteo / Saneamiento)
    Route::get('/plan-ruteo', [PlanRuteoController::class, 'index']);

    // Submódulo de Visitas: listado ligero de clientes de la ruta con estado visited/unvisited
    Route::get('/visitas/clientes', [VisitaController::class, 'clientes']);
    Route::get('/visitas/plan', [VisitaController::class, 'clientes']);
    Route::get('/visitas', [VisitaController::class, 'clientes']);

    // Submódulo de Visitas: sugerido de compra y productos objetivo por cliente (Android App)
    Route::match(['get', 'post'], '/visitas/clientes/{clienteId}/sugerido', [VisitaController::class, 'sugeridoCliente']);
    Route::match(['get', 'post'], '/visitas/cliente/{clienteId}/sugerido', [VisitaController::class, 'sugeridoCliente']);
    Route::match(['get', 'post'], '/visitas/{clienteId}/sugerido', [VisitaController::class, 'sugeridoCliente']);
    Route::match(['get', 'post'], '/visitas/clientes/{clienteId}/detalle', [VisitaController::class, 'sugeridoCliente']);
    Route::match(['get', 'post'], '/visitas/cliente/{clienteId}/detalle', [VisitaController::class, 'sugeridoCliente']);

    // Polígono de Frontera de la Ruta Comercial para Android (Mapas y Geocercas)
    Route::get('/fronteras/ruta', [FronteraController::class, 'ruta']);
    Route::get('/fronteras/rutas', [FronteraController::class, 'ruta']);
    Route::get('/visitas/frontera', [FronteraController::class, 'ruta']);
    Route::get('/ruteo/frontera', [FronteraController::class, 'ruta']);

    // Registro de Visitas en Campo (ambos alias para compatibilidad total con Retrofit)
    Route::post('/visitas', [VisitaController::class, 'store']);
    Route::post('/visitas/store', [VisitaController::class, 'store']);

    // Pizarra Modelo Unilever (Android App - Resumen de los 8 KPIs en tiempo real)
    Route::match(['get', 'post'], '/pizarra', [PizarraController::class, 'resumen']);
    Route::match(['get', 'post'], '/pizarra/resumen', [PizarraController::class, 'resumen']);

    // Oportunidades comerciales
    Route::get('/oportunidades', [OportunidadesController::class, 'index']);

    // Saneamiento de Clientes en Campo (Altas y Ediciones)
    Route::post('/saneamiento', [SaneamientoController::class, 'store']);
    Route::post('/saneamiento-base', [SaneamientoController::class, 'store']);
    Route::match(['get', 'post'], '/clientes/{clienteId}', [SaneamientoController::class, 'showCliente']);
    Route::match(['get', 'post'], '/saneamiento/cliente/{clienteId}', [SaneamientoController::class, 'showCliente']);

    // Módulo Ediciones de Clientes (Android App)
    foreach (['ediciones', 'edicion'] as $prefix) {
        Route::prefix($prefix)->group(function (): void {
            Route::match(['get', 'post'], '/cliente/{clienteId}', [SaneamientoController::class, 'showCliente']);
            Route::post('/', [SaneamientoController::class, 'store']);
            Route::post('/store', [SaneamientoController::class, 'store']);
        });
    }

    // Módulo Historial de Altas de Clientes (Android App - Opción A: /api/altas/historial y alias)
    foreach (['altas', 'alta'] as $prefix) {
        Route::prefix($prefix)->group(function (): void {
            Route::get('/', [AltaController::class, 'historial']);
            Route::match(['get', 'post'], '/historial', [AltaController::class, 'historial']);
            Route::match(['get', 'post'], '/listar', [AltaController::class, 'historial']);
            Route::match(['get', 'post'], '/catalogos', [AltaController::class, 'catalogos']);
            Route::post('/', [SaneamientoController::class, 'store']);
            Route::post('/store', [SaneamientoController::class, 'store']);
        });
    }

    // Alias bajo saneamiento para compatibilidad Retrofit
    Route::match(['get', 'post'], '/saneamiento/altas', [AltaController::class, 'historial']);
    Route::match(['get', 'post'], '/saneamiento/altas/historial', [AltaController::class, 'historial']);
    Route::match(['get', 'post'], '/saneamiento/catalogos', [AltaController::class, 'catalogos']);

    // Módulo Pedidos Rechazados (Android App - Soporte plural, singular, guiones y guiones bajos para Retrofit)
    foreach (['pedidos-rechazados', 'pedidos_rechazados', 'pedido-rechazado', 'pedido_rechazado', 'rechazos', 'rechazo'] as $prefix) {
        Route::prefix($prefix)->group(function (): void {
            Route::match(['get', 'post'], '/motivos', [PedidoRechazadoController::class, 'motivos']);
            Route::match(['get', 'post'], '/distribuidores', [PedidoRechazadoController::class, 'distribuidores']);
            Route::match(['get', 'post'], '/repartidores', [PedidoRechazadoController::class, 'distribuidores']);
            Route::match(['get', 'post'], '/preventas-pendientes', [PedidoRechazadoController::class, 'preventasPendientes']);
            Route::match(['get', 'post'], '/preventas_pendientes', [PedidoRechazadoController::class, 'preventasPendientes']);
            Route::post('/', [PedidoRechazadoController::class, 'store']);
            Route::post('/store', [PedidoRechazadoController::class, 'store']);
            Route::match(['get', 'post'], '/historial', [PedidoRechazadoController::class, 'historial']);
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
