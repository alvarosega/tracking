<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorAltasEdicionesController;
use App\Http\Controllers\Web\SupervisorAvanceController;
use App\Http\Controllers\Web\SupervisorHorariosController;
use App\Http\Controllers\Web\SupervisorIndexController;
use App\Http\Controllers\Web\SupervisorJornadasController;
use App\Http\Controllers\Web\SupervisorModulosController;
use App\Http\Controllers\Web\SupervisorPreventasController;
use App\Http\Controllers\Web\SupervisorRuteoController;
use App\Http\Controllers\Web\SupervisorTrackingController;
use App\Http\Controllers\Web\SupervisorVisitasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Panel de Supervisión
|--------------------------------------------------------------------------
*/

// Autenticación de Supervisor
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Redirección raíz
Route::get('/', fn() => redirect()->route('login'));

// Panel Protegido para Rol Supervisor
Route::middleware(['auth', 'supervisor'])->prefix('supervisor')->name('supervisor.')->group(function () {
    // Dashboard General
    Route::get('/', [SupervisorIndexController::class, 'index'])->name('index');

    // Módulo Ruteo & Cobertura GIS
    Route::prefix('ruteo')->name('ruteo.')->group(function () {
        Route::get('/', [SupervisorRuteoController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorRuteoController::class, 'getData'])->name('data');
        Route::get('/cercanos', [SupervisorRuteoController::class, 'cercanos'])->name('cercanos');
        Route::post('/cercanos/data', [SupervisorRuteoController::class, 'getCercanosData'])->name('cercanos.data');
        Route::post('/cercanos/resolver-enlace', [SupervisorRuteoController::class, 'resolverEnlaceMaps'])->name('cercanos.resolver-enlace');
        Route::post('/cercanos/cliente/{clienteId}/ventas', [SupervisorRuteoController::class, 'getVentasCliente'])->name('cercanos.cliente.ventas');
        Route::post('/cercanos/cliente/{clienteId}/visitas', [SupervisorRuteoController::class, 'getVisitasCliente'])->name('cercanos.cliente.visitas');
    });

    // Módulo Avance Mensual & Cumplimiento Estratégico (KPIs)
    Route::prefix('avance')->name('avance.')->group(function () {
        Route::get('/cumplimiento', [SupervisorAvanceController::class, 'cumplimiento'])->name('cumplimiento');
        Route::get('/historico-preventas', [SupervisorAvanceController::class, 'historicoPreventas'])->name('historico-preventas');
        Route::get('/cluster', [SupervisorAvanceController::class, 'cluster'])->name('cluster');
        Route::get('/', fn() => redirect()->route('supervisor.avance.cumplimiento'))->name('index');
        Route::post('/cumplimiento/data', [SupervisorAvanceController::class, 'cumplimientoData'])->name('cumplimiento.data');
        Route::post('/cumplimiento/clientes-ruta', [SupervisorAvanceController::class, 'clientesRuta'])->name('cumplimiento.clientes-ruta');
        Route::post('/cumplimiento/cliente-detalle-fechas', [SupervisorAvanceController::class, 'clienteDetalleFechas'])->name('cumplimiento.cliente-detalle-fechas');
        Route::post('/historico-preventas/data', [SupervisorAvanceController::class, 'historicoPreventasData'])->name('historico-preventas.data');
        Route::post('/historico-preventas/detalle-ruta', [SupervisorAvanceController::class, 'historicoPreventasDetalleRuta'])->name('historico-preventas.detalle-ruta');
        Route::post('/cluster/matriz', [SupervisorAvanceController::class, 'clusterMatriz'])->name('cluster.matriz');
        Route::get('/cluster/catalogo', [SupervisorAvanceController::class, 'clusterCatalogo'])->name('cluster.catalogo');
        Route::post('/dias-habiles', [SupervisorAvanceController::class, 'diasHabiles'])->name('dias-habiles');
    });

    // Módulo Auditoría Preventas & Cumplimiento Unilever
    Route::prefix('preventas')->name('preventas.')->group(function () {
        Route::get('/', [SupervisorPreventasController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorPreventasController::class, 'data'])->name('data');
        Route::post('/resolver-enlace', [SupervisorPreventasController::class, 'resolverEnlaceMaps'])->name('resolver-enlace');
        Route::post('/rechazos/data', [SupervisorPreventasController::class, 'rechazosData'])->name('rechazos.data');

        Route::get('/cumplimiento', [SupervisorPreventasController::class, 'cumplimiento'])->name('cumplimiento');
        Route::post('/cumplimiento/data', [SupervisorPreventasController::class, 'cumplimientoData'])->name('cumplimiento.data');
        Route::post('/cumplimiento/fotos/{clienteId}', [SupervisorPreventasController::class, 'fotosCliente'])->name('cumplimiento.fotos');
        Route::post('/cumplimiento/horario/detalle', [SupervisorPreventasController::class, 'cumplimientoHorarioDetalle'])->name('cumplimiento.horario.detalle');
        Route::post('/cumplimiento/visitas/detalle', [SupervisorPreventasController::class, 'cumplimientoVisitasDetalle'])->name('cumplimiento.visitas.detalle');
        Route::post('/cumplimiento/objetivo/detalle', [SupervisorPreventasController::class, 'cumplimientoObjetivoDetalle'])->name('cumplimiento.objetivo.detalle');
        Route::post('/cumplimiento/efectividad/detalle', [SupervisorPreventasController::class, 'cumplimientoEfectividadDetalle'])->name('cumplimiento.efectividad.detalle');
        Route::post('/cumplimiento/compra-minima/detalle', [SupervisorPreventasController::class, 'cumplimientoCompraMinimaDetalle'])->name('cumplimiento.compra-minima.detalle');
        Route::post('/cumplimiento/reposicion/detalle', [SupervisorPreventasController::class, 'cumplimientoReposicionDetalle'])->name('cumplimiento.reposicion.detalle');
        Route::post('/cumplimiento/ampliacion/detalle', [SupervisorPreventasController::class, 'cumplimientoAmpliacionDetalle'])->name('cumplimiento.ampliacion.detalle');

        Route::get('/diaria', [SupervisorPreventasController::class, 'diaria'])->name('diaria');
        Route::post('/diaria/data', [SupervisorPreventasController::class, 'diariaData'])->name('diaria.data');
        Route::post('/diaria/ruta/{ruta}', [SupervisorPreventasController::class, 'rutaDetalle'])->name('diaria.ruta');

        Route::get('/productos', [SupervisorPreventasController::class, 'productos'])->name('productos');
        Route::post('/productos/categorias', [SupervisorPreventasController::class, 'productosCategorias'])->name('productos.categorias');
        Route::post('/productos/ranking', [SupervisorPreventasController::class, 'productosRanking'])->name('productos.ranking');
        Route::post('/productos/detalle', [SupervisorPreventasController::class, 'productosDetalleRutas'])->name('productos.detalle');
        Route::post('/productos/heatmap', [SupervisorPreventasController::class, 'productosHeatmap'])->name('productos.heatmap');

        Route::get('/auditoria', [SupervisorPreventasController::class, 'auditoria'])->name('auditoria');
        Route::get('/comparativos', [SupervisorPreventasController::class, 'comparativos'])->name('comparativos');
    });

    // Módulo Tracking en Vivo y Patrones Históricos
    Route::prefix('tracking')->name('tracking.')->group(function () {
        Route::get('/historico', [SupervisorTrackingController::class, 'historico'])->name('historico');
        Route::post('/historico/data', [SupervisorTrackingController::class, 'historicoData'])->name('historico.data');
        Route::get('/', [SupervisorTrackingController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorTrackingController::class, 'data'])->name('data');
        Route::post('/workday/{userId}/close', [SupervisorTrackingController::class, 'closeWorkday'])->name('workday.close');
    });

    // Módulo Altas & Ediciones
    Route::prefix('altas-ediciones')->name('altas-ediciones.')->group(function () {
        Route::get('/', [SupervisorAltasEdicionesController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorAltasEdicionesController::class, 'data'])->name('data');
        Route::post('/cercanos', [SupervisorAltasEdicionesController::class, 'cercanos'])->name('cercanos');
        Route::post('/{id}/estado', [SupervisorAltasEdicionesController::class, 'cambiarEstado'])->name('cambiar-estado');
    });

    // Módulo Auditoría de Visitas & Cobertura Fotográfica
    Route::prefix('visitas')->name('visitas.')->group(function () {
        Route::get('/', [SupervisorVisitasController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorVisitasController::class, 'data'])->name('data');
        Route::post('/fotos/{clienteId}', [SupervisorVisitasController::class, 'fotosCliente'])->name('fotos');
        Route::get('/avance', [SupervisorVisitasController::class, 'avance'])->name('avance');
        Route::post('/avance/data', [SupervisorVisitasController::class, 'avanceData'])->name('avance.data');
    });

    // Módulo Control de Jornadas
    Route::prefix('jornadas')->name('jornadas.')->group(function () {
        Route::get('/', [SupervisorJornadasController::class, 'index'])->name('index');
        Route::post('/{userId}/close', [SupervisorJornadasController::class, 'closeSingle'])->name('close.single');
        Route::post('/close-all', [SupervisorJornadasController::class, 'closeAllActive'])->name('close.all');
    });

    // Módulo Matriz de Horarios
    Route::prefix('horarios')->name('horarios.')->group(function () {
        Route::get('/', [SupervisorHorariosController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorHorariosController::class, 'data'])->name('data');
        Route::post('/update', [SupervisorHorariosController::class, 'update'])->name('update');
        Route::post('/active-workdays', [SupervisorHorariosController::class, 'activeWorkdays'])->name('active-workdays');
        Route::post('/close/{userId}', [SupervisorHorariosController::class, 'forceCloseWorkday'])->name('close');
    });

    // Módulo Gestión de Módulos Móviles y Parámetros
    Route::prefix('modulos')->name('modulos.')->group(function () {
        Route::get('/', [SupervisorModulosController::class, 'index'])->name('index');
        Route::post('/global/{id}/toggle', [SupervisorModulosController::class, 'toggleGlobal'])->name('toggle.global');
        Route::post('/global/{id}/config', [SupervisorModulosController::class, 'updateGlobalConfig'])->name('config.global');
        Route::post('/route/toggle', [SupervisorModulosController::class, 'toggleRoute'])->name('toggle.route');
        Route::post('/route/config', [SupervisorModulosController::class, 'updateRouteConfig'])->name('config.route');
        Route::post('/route/apply-all', [SupervisorModulosController::class, 'applyToAllRoutes'])->name('apply.all');
        Route::post('/inventarios/data', [SupervisorModulosController::class, 'inventariosData'])->name('inventarios.data');
    });
});
