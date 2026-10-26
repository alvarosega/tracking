<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorAdminPermisosController;
use App\Http\Controllers\Web\SupervisorAltasEdicionesController;
use App\Http\Controllers\Web\SupervisorAvanceController;
use App\Http\Controllers\Web\SupervisorHorariosController;
use App\Http\Controllers\Web\SupervisorIndexController;
use App\Http\Controllers\Web\SupervisorIngestaController;
use App\Http\Controllers\Web\SupervisorJornadasController;
use App\Http\Controllers\Web\SupervisorModulosController;
use App\Http\Controllers\Web\SupervisorPreventasController;
use App\Http\Controllers\Web\SupervisorRuteoController;
use App\Http\Controllers\Web\SupervisorSaneamientoController;
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
    Route::prefix('ruteo')->name('ruteo.')->middleware('module.access:ruteo')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('ruteo', 'visor')) {
                return app(SupervisorRuteoController::class)->index();
            }
            if ($user->hasModuleAccess('ruteo', 'cercanos')) {
                return redirect()->route('supervisor.ruteo.cercanos');
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Ruteo.');
        })->name('index');

        // Submódulo Visor y Fronteras
        Route::middleware('module.access:ruteo,visor')->group(function () {
            Route::post('/data', [SupervisorRuteoController::class, 'getData'])->name('data');
        });

        // Submódulo Clientes Cercanos
        Route::middleware('module.access:ruteo,cercanos')->group(function () {
            Route::get('/cercanos', [SupervisorRuteoController::class, 'cercanos'])->name('cercanos');
            Route::post('/cercanos/data', [SupervisorRuteoController::class, 'getCercanosData'])->name('cercanos.data');
            Route::post('/cercanos/resolver-enlace', [SupervisorRuteoController::class, 'resolverEnlaceMaps'])->name('cercanos.resolver-enlace');
            Route::post('/cercanos/cliente/{clienteId}/ventas', [SupervisorRuteoController::class, 'getVentasCliente'])->name('cercanos.cliente.ventas');
            Route::post('/cercanos/cliente/{clienteId}/visitas', [SupervisorRuteoController::class, 'getVisitasCliente'])->name('cercanos.cliente.visitas');
        });
    });

    // Módulo Avance Mensual & Cumplimiento Estratégico (KPIs)
    Route::prefix('avance')->name('avance.')->middleware('module.access:avance')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $submodules = ['cumplimiento', 'historico-preventas', 'cluster', 'paretos', 'entregas', 'bpc', 'horarios'];
            foreach ($submodules as $sub) {
                if ($user->hasModuleAccess('avance', $sub)) {
                    return redirect()->route("supervisor.avance.{$sub}");
                }
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Avance.');
        })->name('index');

        // Cumplimiento KPIs
        Route::middleware('module.access:avance,cumplimiento')->group(function () {
            Route::get('/cumplimiento', [SupervisorAvanceController::class, 'cumplimiento'])->name('cumplimiento');
            Route::post('/cumplimiento/data', [SupervisorAvanceController::class, 'cumplimientoData'])->name('cumplimiento.data');
            Route::post('/cumplimiento/clientes-ruta', [SupervisorAvanceController::class, 'clientesRuta'])->name('cumplimiento.clientes-ruta');
            Route::post('/cumplimiento/cliente-detalle-fechas', [SupervisorAvanceController::class, 'clienteDetalleFechas'])->name('cumplimiento.cliente-detalle-fechas');
        });

        // Preventas Históricas
        Route::middleware('module.access:avance,historico-preventas')->group(function () {
            Route::get('/historico-preventas', [SupervisorAvanceController::class, 'historicoPreventas'])->name('historico-preventas');
            Route::post('/historico-preventas/data', [SupervisorAvanceController::class, 'historicoPreventasData'])->name('historico-preventas.data');
            Route::post('/historico-preventas/detalle-ruta', [SupervisorAvanceController::class, 'historicoPreventasDetalleRuta'])->name('historico-preventas.detalle-ruta');
        });

        // Cluster vs LY
        Route::middleware('module.access:avance,cluster')->group(function () {
            Route::get('/cluster', [SupervisorAvanceController::class, 'cluster'])->name('cluster');
            Route::post('/cluster/matriz', [SupervisorAvanceController::class, 'clusterMatriz'])->name('cluster.matriz');
            Route::get('/cluster/catalogo', [SupervisorAvanceController::class, 'clusterCatalogo'])->name('cluster.catalogo');
        });

        // Paretos & Scoring
        Route::middleware('module.access:avance,paretos')->group(function () {
            Route::get('/paretos', [SupervisorAvanceController::class, 'paretos'])->name('paretos');
            Route::post('/paretos/data', [SupervisorAvanceController::class, 'paretosData'])->name('paretos.data');
            Route::post('/paretos/detalle-amplitud', [SupervisorAvanceController::class, 'paretoDetalleAmplitud'])->name('paretos.detalle-amplitud');
            Route::post('/paretos/detalle-frecuencia', [SupervisorAvanceController::class, 'paretoDetalleFrecuencia'])->name('paretos.detalle-frecuencia');
        });

        // Entregas
        Route::middleware('module.access:avance,entregas')->group(function () {
            Route::get('/entregas', [SupervisorAvanceController::class, 'entregas'])->name('entregas');
            Route::post('/entregas/tablero', [SupervisorAvanceController::class, 'entregasTablero'])->name('entregas.tablero');
            Route::post('/entregas/historico', [SupervisorAvanceController::class, 'entregasHistorico'])->name('entregas.historico');
            Route::post('/entregas/historico/detalle-ruta', [SupervisorAvanceController::class, 'entregasHistoricoDetalleRuta'])->name('entregas.historico.detalle-ruta');
        });

        // Sell Out y BPC
        Route::middleware('module.access:avance,bpc')->group(function () {
            Route::get('/bpc', [SupervisorAvanceController::class, 'bpc'])->name('bpc');
            Route::post('/bpc/clientes', [SupervisorAvanceController::class, 'bpcClientes'])->name('bpc.clientes');
            Route::post('/bpc/sellout', [SupervisorAvanceController::class, 'bpcSellOut'])->name('bpc.sellout');
        });

        // Horario e Itinerario
        Route::middleware('module.access:avance,horarios')->group(function () {
            Route::get('/horarios', [SupervisorAvanceController::class, 'horarios'])->name('horarios');
            Route::post('/horarios/matriz', [SupervisorAvanceController::class, 'horariosMatriz'])->name('horarios.matriz');
            Route::post('/horarios/timeline', [SupervisorAvanceController::class, 'horariosTimeline'])->name('horarios.timeline');
            Route::post('/itinerario/secuencia', [SupervisorAvanceController::class, 'itinerarioSecuencia'])->name('itinerario.secuencia');
            Route::post('/itinerario/guardar', [SupervisorAvanceController::class, 'itinerarioGuardar'])->name('itinerario.guardar');
        });

        Route::post('/dias-habiles', [SupervisorAvanceController::class, 'diasHabiles'])->name('dias-habiles');
    });

    // Módulo Auditoría Preventas & Cumplimiento Unilever
    Route::prefix('preventas')->name('preventas.')->middleware('module.access:preventas')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $submodules = ['mapa', 'cumplimiento', 'diaria', 'productos', 'auditoria', 'comparativos'];
            foreach ($submodules as $sub) {
                if ($user->hasModuleAccess('preventas', $sub)) {
                    $target = $sub === 'mapa' ? 'index' : $sub;
                    return redirect()->route("supervisor.preventas.{$target}");
                }
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Preventas.');
        })->name('index');

        // Auditoría en Mapa / Terreno
        Route::middleware('module.access:preventas,mapa')->group(function () {
            Route::get('/mapa', [SupervisorPreventasController::class, 'index'])->name('mapa');
            Route::post('/data', [SupervisorPreventasController::class, 'data'])->name('data');
            Route::post('/resolver-enlace', [SupervisorPreventasController::class, 'resolverEnlaceMaps'])->name('resolver-enlace');
            Route::post('/rechazos/data', [SupervisorPreventasController::class, 'rechazosData'])->name('rechazos.data');
        });

        // Cumplimiento
        Route::middleware('module.access:preventas,cumplimiento')->group(function () {
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
        });

        // Preventa Diaria
        Route::middleware('module.access:preventas,diaria')->group(function () {
            Route::get('/diaria', [SupervisorPreventasController::class, 'diaria'])->name('diaria');
            Route::post('/diaria/data', [SupervisorPreventasController::class, 'diariaData'])->name('diaria.data');
            Route::post('/diaria/ruta/{ruta}', [SupervisorPreventasController::class, 'rutaDetalle'])->name('diaria.ruta');
        });

        // Productos
        Route::middleware('module.access:preventas,productos')->group(function () {
            Route::get('/productos', [SupervisorPreventasController::class, 'productos'])->name('productos');
            Route::post('/productos/categorias', [SupervisorPreventasController::class, 'productosCategorias'])->name('productos.categorias');
            Route::post('/productos/ranking', [SupervisorPreventasController::class, 'productosRanking'])->name('productos.ranking');
            Route::post('/productos/detalle', [SupervisorPreventasController::class, 'productosDetalleRutas'])->name('productos.detalle');
            Route::post('/productos/heatmap', [SupervisorPreventasController::class, 'productosHeatmap'])->name('productos.heatmap');
        });

        // Auditoría
        Route::middleware('module.access:preventas,auditoria')->group(function () {
            Route::get('/auditoria', [SupervisorPreventasController::class, 'auditoria'])->name('auditoria');
        });

        // Comparativos
        Route::middleware('module.access:preventas,comparativos')->group(function () {
            Route::get('/comparativos', [SupervisorPreventasController::class, 'comparativos'])->name('comparativos');
        });
    });

    // Módulo Tracking en Vivo y Patrones Históricos
    Route::prefix('tracking')->name('tracking.')->middleware('module.access:tracking')->group(function () {
        // En Vivo
        Route::middleware('module.access:tracking,vivo')->group(function () {
            Route::get('/', [SupervisorTrackingController::class, 'index'])->name('index');
            Route::post('/data', [SupervisorTrackingController::class, 'data'])->name('data');
            Route::post('/workday/{userId}/close', [SupervisorTrackingController::class, 'closeWorkday'])->name('workday.close');
        });

        // Histórico
        Route::middleware('module.access:tracking,historico')->group(function () {
            Route::get('/historico', [SupervisorTrackingController::class, 'historico'])->name('historico');
            Route::post('/historico/data', [SupervisorTrackingController::class, 'historicoData'])->name('historico.data');
        });
    });

    // Módulo Altas & Ediciones
    Route::prefix('altas-ediciones')->name('altas-ediciones.')->middleware('module.access:altas-ediciones')->group(function () {
        Route::get('/', [SupervisorAltasEdicionesController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorAltasEdicionesController::class, 'data'])->name('data');
        Route::post('/cercanos', [SupervisorAltasEdicionesController::class, 'cercanos'])->name('cercanos');
        Route::post('/{id}/estado', [SupervisorAltasEdicionesController::class, 'cambiarEstado'])->name('cambiar-estado');
    });

    // Módulo Auditoría de Visitas & Cobertura Fotográfica
    Route::prefix('visitas')->name('visitas.')->middleware('module.access:visitas')->group(function () {
        // Galería
        Route::middleware('module.access:visitas,galeria')->group(function () {
            Route::get('/', [SupervisorVisitasController::class, 'index'])->name('index');
            Route::post('/data', [SupervisorVisitasController::class, 'data'])->name('data');
            Route::post('/fotos/{clienteId}', [SupervisorVisitasController::class, 'fotosCliente'])->name('fotos');
        });

        // Avance en Terreno
        Route::middleware('module.access:visitas,avance')->group(function () {
            Route::get('/avance', [SupervisorVisitasController::class, 'avance'])->name('avance');
            Route::post('/avance/data', [SupervisorVisitasController::class, 'avanceData'])->name('avance.data');
        });
    });

    // Módulo Control de Jornadas
    Route::prefix('jornadas')->name('jornadas.')->middleware('module.access:jornadas')->group(function () {
        Route::get('/', [SupervisorJornadasController::class, 'index'])->name('index');
        Route::post('/{userId}/close', [SupervisorJornadasController::class, 'closeSingle'])->name('close.single');
        Route::post('/close-all', [SupervisorJornadasController::class, 'closeAllActive'])->name('close.all');
    });

    // Módulo Matriz de Horarios
    Route::prefix('horarios')->name('horarios.')->middleware('module.access:horarios')->group(function () {
        Route::get('/', [SupervisorHorariosController::class, 'index'])->name('index');
        Route::post('/data', [SupervisorHorariosController::class, 'data'])->name('data');
        Route::post('/update', [SupervisorHorariosController::class, 'update'])->name('update');
        Route::post('/active-workdays', [SupervisorHorariosController::class, 'activeWorkdays'])->name('active-workdays');
        Route::post('/close/{userId}', [SupervisorHorariosController::class, 'forceCloseWorkday'])->name('close');
    });

    // Módulo Gestión de Módulos Móviles y Parámetros
    Route::prefix('modulos')->name('modulos.')->middleware('module.access:modulos')->group(function () {
        Route::get('/', [SupervisorModulosController::class, 'index'])->name('index');
        Route::post('/global/{id}/toggle', [SupervisorModulosController::class, 'toggleGlobal'])->name('toggle.global');
        Route::post('/global/{id}/config', [SupervisorModulosController::class, 'updateGlobalConfig'])->name('config.global');
        Route::post('/route/toggle', [SupervisorModulosController::class, 'toggleRoute'])->name('toggle.route');
        Route::post('/route/config', [SupervisorModulosController::class, 'updateRouteConfig'])->name('config.route');
        Route::post('/route/apply-all', [SupervisorModulosController::class, 'applyToAllRoutes'])->name('apply.all');
        Route::post('/inventarios/data', [SupervisorModulosController::class, 'inventariosData'])->name('inventarios.data');
    });

    // Módulo Saneamiento Territorial & Calidad de Padrón
    Route::prefix('saneamiento')->name('saneamiento.')->middleware('module.access:saneamiento')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $submodules = ['intrusos', 'duplicados', 'dias', 'calidad', 'bd', 'propuestas'];
            foreach ($submodules as $sub) {
                if ($user->hasModuleAccess('saneamiento', $sub)) {
                    return redirect()->route("supervisor.saneamiento.{$sub}");
                }
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Saneamiento.');
        })->name('index');

        // Intrusos y Fronteras
        Route::middleware('module.access:saneamiento,intrusos')->group(function () {
            Route::get('/intrusos', [SupervisorSaneamientoController::class, 'intrusos'])->name('intrusos');
            Route::get('/fronteras/todas', [SupervisorSaneamientoController::class, 'fronterasTodas'])->name('fronteras.todas');
            Route::post('/fronteras/clientes-activos', [SupervisorSaneamientoController::class, 'clientesActivos'])->name('fronteras.clientes-activos');
            Route::get('/fronteras/propuestas-pendientes', [SupervisorSaneamientoController::class, 'propuestasPendientesFronteras'])->name('fronteras.propuestas-pendientes');
            Route::post('/fronteras/propuestas/crear', [SupervisorSaneamientoController::class, 'crearPropuestaIntruso'])->name('fronteras.propuestas.crear');
            Route::post('/fronteras/propuestas/cambiar-estado', [SupervisorSaneamientoController::class, 'cambiarEstadoPropuestaIntruso'])->name('fronteras.propuestas.cambiar-estado');
            Route::post('/fronteras/propuestas/cambiar-dia', [SupervisorSaneamientoController::class, 'cambiarDiaPropuestaIntruso'])->name('fronteras.propuestas.cambiar-dia');
            Route::post('/fronteras/propuestas/revertir', [SupervisorSaneamientoController::class, 'revertirPropuestaIntruso'])->name('fronteras.propuestas.revertir');
            Route::post('/fronteras/guardar', [SupervisorSaneamientoController::class, 'guardarFronteraRuta'])->name('fronteras.guardar');
        });

        // Duplicados
        Route::middleware('module.access:saneamiento,duplicados')->group(function () {
            Route::get('/duplicados', [SupervisorSaneamientoController::class, 'duplicados'])->name('duplicados');
            Route::post('/duplicados/auditar', [SupervisorSaneamientoController::class, 'auditarDuplicados'])->name('duplicados.auditar');
            Route::get('/duplicados/propuestas-pendientes', [SupervisorSaneamientoController::class, 'propuestasPendientesDuplicados'])->name('duplicados.propuestas-pendientes');
            Route::post('/duplicados/propuestas/crear', [SupervisorSaneamientoController::class, 'crearPropuestaDuplicado'])->name('duplicados.propuestas.crear');
            Route::post('/duplicados/propuestas/cambiar-estado', [SupervisorSaneamientoController::class, 'cambiarEstadoPropuestaDuplicado'])->name('duplicados.propuestas.cambiar-estado');
            Route::post('/duplicados/propuestas/revertir', [SupervisorSaneamientoController::class, 'revertirPropuestaDuplicado'])->name('duplicados.propuestas.revertir');
        });

        // Días Desalineados
        Route::middleware('module.access:saneamiento,dias')->group(function () {
            Route::get('/dias', [SupervisorSaneamientoController::class, 'dias'])->name('dias');
            Route::get('/dias-desalineados', [SupervisorSaneamientoController::class, 'auditarDias'])->name('dias-desalineados');
            Route::get('/dias-desalineados/propuestas-pendientes', [SupervisorSaneamientoController::class, 'propuestasPendientesDias'])->name('dias.propuestas-pendientes');
            Route::post('/dias-desalineados/cambiar-dia', [SupervisorSaneamientoController::class, 'cambiarDia'])->name('dias.cambiar-dia');
            Route::post('/dias-desalineados/cambiar-estado', [SupervisorSaneamientoController::class, 'cambiarEstadoDia'])->name('dias.cambiar-estado');
            Route::post('/dias-desalineados/revertir', [SupervisorSaneamientoController::class, 'revertirDia'])->name('dias.revertir');
            Route::post('/dias-desalineados/guardar-frontera', [SupervisorSaneamientoController::class, 'guardarFronteraDia'])->name('dias.guardar-frontera');
        });

        // Calidad de Datos
        Route::middleware('module.access:saneamiento,calidad')->group(function () {
            Route::get('/calidad', [SupervisorSaneamientoController::class, 'calidad'])->name('calidad');
            Route::post('/calidad/auditar', [SupervisorSaneamientoController::class, 'auditarCalidad'])->name('calidad.auditar');
        });

        // Matriz BD
        Route::middleware('module.access:saneamiento,bd')->group(function () {
            Route::get('/bd', [SupervisorSaneamientoController::class, 'bd'])->name('bd');
            Route::post('/bd/matriz', [SupervisorSaneamientoController::class, 'matrizBd'])->name('bd.matriz');
        });

        // Historial de Propuestas
        Route::middleware('module.access:saneamiento,propuestas')->group(function () {
            Route::get('/propuestas', [SupervisorSaneamientoController::class, 'propuestas'])->name('propuestas');
            Route::get('/propuestas/historial', [SupervisorSaneamientoController::class, 'historialPropuestas'])->name('propuestas.historial');
            Route::post('/propuestas/actualizar-estado', [SupervisorSaneamientoController::class, 'actualizarEstadoPropuesta'])->name('propuestas.actualizar-estado');
            Route::post('/propuestas/actualizar-estado-masivo', [SupervisorSaneamientoController::class, 'actualizarEstadoMasivo'])->name('propuestas.actualizar-estado-masivo');
        });

        // Endpoints comunes de saneamiento
        Route::get('/catalogo', [SupervisorSaneamientoController::class, 'catalogo'])->name('catalogo');
        Route::get('/clientes/buscar', [SupervisorSaneamientoController::class, 'buscarCliente'])->name('clientes.buscar');
        Route::get('/clientes/fotos', [SupervisorSaneamientoController::class, 'fotosCliente'])->name('clientes.fotos');
    });

    // Módulo Centro de Ingesta & Monitoreo de Datos
    Route::prefix('ingesta')->name('ingesta.')->middleware('module.access:ingesta')->group(function () {
        // Centro / Dashboard Ingesta
        Route::middleware('module.access:ingesta,dashboard')->group(function () {
            Route::get('/', [SupervisorIngestaController::class, 'index'])->name('index');
            Route::get('/tablas-estado', [SupervisorIngestaController::class, 'tablasEstado'])->name('tablas-estado');
            Route::get('/cargas-historial', [SupervisorIngestaController::class, 'cargasHistorial'])->name('cargas-historial');
        });

        // Ventas
        Route::middleware('module.access:ingesta,ventas')->group(function () {
            Route::get('/ventas', [SupervisorIngestaController::class, 'ventas'])->name('ventas');
            Route::post('/ventas', [SupervisorIngestaController::class, 'uploadVentas'])->name('upload.ventas');
        });

        // Preventas
        Route::middleware('module.access:ingesta,preventas')->group(function () {
            Route::get('/preventas', [SupervisorIngestaController::class, 'preventas'])->name('preventas');
            Route::post('/preventas', [SupervisorIngestaController::class, 'uploadPreventas'])->name('upload.preventas');
        });

        // Ruteo
        Route::middleware('module.access:ingesta,ruteo')->group(function () {
            Route::get('/ruteo', [SupervisorIngestaController::class, 'ruteo'])->name('ruteo');
            Route::post('/ruteo', [SupervisorIngestaController::class, 'uploadRuteo'])->name('upload.ruteo');
        });

        // Visitas
        Route::middleware('module.access:ingesta,visitas')->group(function () {
            Route::get('/visitas', [SupervisorIngestaController::class, 'visitas'])->name('visitas');
            Route::post('/visitas', [SupervisorIngestaController::class, 'uploadVisitas'])->name('upload.visitas');
        });

        // Inventario
        Route::middleware('module.access:ingesta,inventario')->group(function () {
            Route::get('/inventario', [SupervisorIngestaController::class, 'inventario'])->name('inventario');
            Route::post('/inventario', [SupervisorIngestaController::class, 'uploadInventario'])->name('upload.inventario');
        });

        // Cancelados
        Route::middleware('module.access:ingesta,cancelados')->group(function () {
            Route::get('/cancelados', [SupervisorIngestaController::class, 'cancelados'])->name('cancelados');
            Route::get('/cancelados/pendientes', [SupervisorIngestaController::class, 'canceladosPendientes'])->name('cancelados.pendientes');
            Route::post('/preventas/autorizar-cancelados', [SupervisorIngestaController::class, 'autorizarCancelados'])->name('preventas.autorizar-cancelados');
        });

        // Snapshots
        Route::middleware('module.access:ingesta,snapshots')->group(function () {
            Route::get('/snapshots', [SupervisorIngestaController::class, 'snapshots'])->name('snapshots');
            Route::get('/ruteo/snapshots', [SupervisorIngestaController::class, 'ruteoSnapshots'])->name('ruteo.snapshots');
            Route::post('/ruteo/rollback', [SupervisorIngestaController::class, 'rollbackRuteo'])->name('ruteo.rollback');
        });
    });

    // Módulo Exclusivo Superadministrador: Gestión de Permisos de Supervisores
    Route::prefix('admin/permisos')->name('admin.permisos.')->middleware(['superadmin'])->group(function () {
        Route::get('/', [SupervisorAdminPermisosController::class, 'index'])->name('index');
        Route::post('/user/{userId}/toggle', [SupervisorAdminPermisosController::class, 'toggle'])->name('toggle');
        Route::post('/user/{userId}/batch', [SupervisorAdminPermisosController::class, 'batchSave'])->name('batch');
        Route::post('/user/{userId}/preset', [SupervisorAdminPermisosController::class, 'preset'])->name('preset');
        Route::post('/clone', [SupervisorAdminPermisosController::class, 'clone'])->name('clone');
    });
});
