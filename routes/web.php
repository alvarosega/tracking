<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\SupervisorAdminPermisosController;
use App\Http\Controllers\Web\SupervisorAdminUsuariosController;
use App\Http\Controllers\Web\SupervisorAltasEdicionesController;
use App\Http\Controllers\Web\SupervisorAuditoriaController;
use App\Http\Controllers\Web\SupervisorAvanceController;
use App\Http\Controllers\Web\SupervisorHorariosController;
use App\Http\Controllers\Web\SupervisorIndexController;
use App\Http\Controllers\Web\SupervisorIngestaController;
use App\Http\Controllers\Web\SupervisorJornadasController;
use App\Http\Controllers\Web\SupervisorModulosController;
use App\Http\Controllers\Web\SupervisorPreventasController;
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

    // Redirección y compatibilidad: Ruteo & Cobertura migrado a Saneamiento Padrón
    Route::prefix('ruteo')->name('ruteo.')->group(function () {
        Route::get('/', fn() => redirect()->route('supervisor.saneamiento.visor'))->name('index');
        Route::post('/data', [SupervisorSaneamientoController::class, 'getVisorData'])->name('data');
        Route::get('/cercanos', fn() => redirect()->route('supervisor.saneamiento.cercanos'))->name('cercanos');
        Route::post('/cercanos/data', [SupervisorSaneamientoController::class, 'getCercanosData'])->name('cercanos.data');
        Route::post('/cercanos/resolver-enlace', [SupervisorSaneamientoController::class, 'resolverEnlaceMaps'])->name('cercanos.resolver-enlace');
        Route::post('/cercanos/cliente/{clienteId}/ventas', [SupervisorSaneamientoController::class, 'getVentasCliente'])->name('cercanos.cliente.ventas');
        Route::post('/cercanos/cliente/{clienteId}/visitas', [SupervisorSaneamientoController::class, 'getVisitasCliente'])->name('cercanos.cliente.visitas');
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
            Route::post('/horarios/detalle-ruta', [SupervisorAvanceController::class, 'horariosDetalleRuta'])->name('horarios.detalle-ruta');
            Route::post('/horarios/timeline', [SupervisorAvanceController::class, 'horariosTimeline'])->name('horarios.timeline');
            Route::post('/itinerario/secuencia', [SupervisorAvanceController::class, 'itinerarioSecuencia'])->name('itinerario.secuencia');
            Route::post('/itinerario/guardar', [SupervisorAvanceController::class, 'itinerarioGuardar'])->name('itinerario.guardar');
        });

        Route::post('/dias-habiles', [SupervisorAvanceController::class, 'diasHabiles'])->name('dias-habiles');
    });

    // Módulo Preventas & Cumplimiento Unilever
    Route::prefix('preventas')->name('preventas.')->middleware('module.access:preventas')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $submodules = ['cumplimiento', 'productos'];
            foreach ($submodules as $sub) {
                if ($user->hasModuleAccess('preventas', $sub)) {
                    return redirect()->route("supervisor.preventas.{$sub}");
                }
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Preventas.');
        })->name('index');

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

        // Productos
        Route::middleware('module.access:preventas,productos')->group(function () {
            Route::get('/productos', [SupervisorPreventasController::class, 'productos'])->name('productos');
            Route::post('/productos/categorias', [SupervisorPreventasController::class, 'productosCategorias'])->name('productos.categorias');
            Route::post('/productos/ranking', [SupervisorPreventasController::class, 'productosRanking'])->name('productos.ranking');
            Route::post('/productos/detalle', [SupervisorPreventasController::class, 'productosDetalleRutas'])->name('productos.detalle');
            Route::post('/productos/heatmap', [SupervisorPreventasController::class, 'productosHeatmap'])->name('productos.heatmap');
        });

        // Redirecciones de submódulos obsoletos / migrados
        Route::get('/diaria', function () {
            return redirect()->route('supervisor.preventas.cumplimiento');
        })->name('diaria');

        Route::get('/mapa', function () {
            return redirect()->route('supervisor.preventas.cumplimiento');
        })->name('mapa');

        Route::get('/comparativos', function () {
            return redirect()->route('supervisor.preventas.cumplimiento');
        })->name('comparativos');

        Route::get('/auditoria', function () {
            return redirect()->route('supervisor.auditoria.pendientes');
        })->name('auditoria');
    });

    // Módulo Auditoría de Ruteo (Centralizado: Pendientes e Histórico)
    Route::prefix('auditoria')->name('auditoria.')->middleware('module.access:auditoria')->group(function () {
        Route::get('/', function () {
            return redirect()->route('supervisor.auditoria.pendientes');
        })->name('index');

        // Catálogo maestro y fotos
        Route::get('/catalogo', [SupervisorAuditoriaController::class, 'catalogo'])->name('catalogo');
        Route::get('/fotos-cliente/{clienteId}', [SupervisorAuditoriaController::class, 'fotosCliente'])->name('fotos-cliente');

        // 1. Cambios Pendientes (Bandeja centralizada de auditoría)
        Route::get('/pendientes', [SupervisorAuditoriaController::class, 'pendientes'])->name('pendientes');
        Route::match(['get', 'post'], '/pendientes/consultar', [SupervisorAuditoriaController::class, 'pendientesConsultar'])->name('pendientes.consultar');
        Route::post('/pendientes/resolver', [SupervisorAuditoriaController::class, 'pendientesResolver'])->name('pendientes.resolver');

        // 2. Histórico de Resoluciones
        Route::get('/historico', [SupervisorAuditoriaController::class, 'historico'])->name('historico');
        Route::match(['get', 'post'], '/historico/consultar', [SupervisorAuditoriaController::class, 'historicoConsultar'])->name('historico.consultar');

        // Rutas de compatibilidad con submódulos previos (redirigen a pendientes con categoría)
        Route::get('/inactivados', [SupervisorAuditoriaController::class, 'inactivados'])->name('inactivados');
        Route::match(['get', 'post'], '/inactivados/consultar', [SupervisorAuditoriaController::class, 'inactivadosConsultar'])->name('inactivados.consultar');
        Route::post('/inactivados/resolver', [SupervisorAuditoriaController::class, 'inactivadosResolver'])->name('inactivados.resolver');

        Route::get('/cambios-ruta', [SupervisorAuditoriaController::class, 'cambiosRuta'])->name('cambios-ruta');
        Route::match(['get', 'post'], '/cambios-ruta/consultar', [SupervisorAuditoriaController::class, 'cambiosRutaConsultar'])->name('cambios-ruta.consultar');
        Route::post('/cambios-ruta/resolver', [SupervisorAuditoriaController::class, 'cambiosRutaResolver'])->name('cambios-ruta.resolver');

        Route::get('/modificacion-datos', [SupervisorAuditoriaController::class, 'modificacionDatos'])->name('modificacion-datos');
        Route::match(['get', 'post'], '/modificacion-datos/consultar', [SupervisorAuditoriaController::class, 'modificacionDatosConsultar'])->name('modificacion-datos.consultar');
        Route::post('/modificacion-datos/resolver', [SupervisorAuditoriaController::class, 'modificacionDatosResolver'])->name('modificacion-datos.resolver');

        Route::get('/cambios-dia', [SupervisorAuditoriaController::class, 'cambiosDia'])->name('cambios-dia');
        Route::match(['get', 'post'], '/cambios-dia/consultar', [SupervisorAuditoriaController::class, 'cambiosDiaConsultar'])->name('cambios-dia.consultar');
        Route::post('/cambios-dia/resolver', [SupervisorAuditoriaController::class, 'cambiosDiaResolver'])->name('cambios-dia.resolver');

        Route::get('/altas', [SupervisorAuditoriaController::class, 'altas'])->name('altas');
        Route::match(['get', 'post'], '/altas/consultar', [SupervisorAuditoriaController::class, 'altasConsultar'])->name('altas.consultar');
        Route::post('/altas/resolver', [SupervisorAuditoriaController::class, 'altasResolver'])->name('altas.resolver');
    });

    // Módulo Tracking en Vivo y Patrones Históricos
    Route::prefix('tracking')->name('tracking.')->middleware('module.access:tracking')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('tracking', 'vivo')) {
                return app()->call([app(SupervisorTrackingController::class), 'index']);
            }
            if ($user->hasModuleAccess('tracking', 'historico')) {
                return redirect()->route('supervisor.tracking.historico');
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Tracking.');
        })->name('index');

        // En Vivo
        Route::middleware('module.access:tracking,vivo')->group(function () {
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
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('altas-ediciones', 'listado') || $user->hasModuleAccess('altas-ediciones', 'cercanos')) {
                return app()->call([app(SupervisorAltasEdicionesController::class), 'index']);
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Altas & Ediciones.');
        })->name('index');

        Route::middleware('module.access:altas-ediciones,listado')->group(function () {
            Route::post('/data', [SupervisorAltasEdicionesController::class, 'data'])->name('data');
            Route::post('/{id}/estado', [SupervisorAltasEdicionesController::class, 'cambiarEstado'])->name('cambiar-estado');
        });

        Route::middleware('module.access:altas-ediciones,cercanos')->group(function () {
            Route::post('/cercanos', [SupervisorAltasEdicionesController::class, 'cercanos'])->name('cercanos');
        });
    });

    // Módulo Auditoría de Visitas & Cobertura Fotográfica
    Route::prefix('visitas')->name('visitas.')->middleware('module.access:visitas')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('visitas', 'galeria')) {
                return app()->call([app(SupervisorVisitasController::class), 'index']);
            }
            if ($user->hasModuleAccess('visitas', 'avance')) {
                return redirect()->route('supervisor.visitas.avance');
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Visitas.');
        })->name('index');

        // Galería
        Route::middleware('module.access:visitas,galeria')->group(function () {
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
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('jornadas', 'control') || $user->hasModuleAccess('jornadas', 'cierre-masivo')) {
                return app()->call([app(SupervisorJornadasController::class), 'index']);
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Control de Jornadas.');
        })->name('index');

        Route::middleware('module.access:jornadas,control')->group(function () {
            Route::post('/{userId}/close', [SupervisorJornadasController::class, 'closeSingle'])->name('close.single');
        });

        Route::middleware('module.access:jornadas,cierre-masivo')->group(function () {
            Route::post('/close-all', [SupervisorJornadasController::class, 'closeAllActive'])->name('close.all');
        });
    });

    // Módulo Matriz de Horarios
    Route::prefix('horarios')->name('horarios.')->middleware('module.access:horarios')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('horarios', 'matriz') || $user->hasModuleAccess('horarios', 'activos')) {
                return app()->call([app(SupervisorHorariosController::class), 'index']);
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Matriz de Horarios.');
        })->name('index');

        Route::middleware('module.access:horarios,matriz')->group(function () {
            Route::post('/data', [SupervisorHorariosController::class, 'data'])->name('data');
            Route::post('/update', [SupervisorHorariosController::class, 'update'])->name('update');
        });

        Route::middleware('module.access:horarios,activos')->group(function () {
            Route::post('/active-workdays', [SupervisorHorariosController::class, 'activeWorkdays'])->name('active-workdays');
            Route::post('/close/{userId}', [SupervisorHorariosController::class, 'forceCloseWorkday'])->name('close');
        });
    });

    // Módulo Gestión de Módulos Móviles y Parámetros
    Route::prefix('modulos')->name('modulos.')->middleware('module.access:modulos')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('modulos', 'matriz') || $user->hasModuleAccess('modulos', 'global') || $user->hasModuleAccess('modulos', 'inventarios')) {
                return app()->call([app(SupervisorModulosController::class), 'index']);
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Módulos & Ajustes.');
        })->name('index');

        Route::middleware('module.access:modulos,matriz')->group(function () {
            Route::post('/route/toggle', [SupervisorModulosController::class, 'toggleRoute'])->name('toggle.route');
            Route::post('/route/config', [SupervisorModulosController::class, 'updateRouteConfig'])->name('config.route');
            Route::post('/route/apply-all', [SupervisorModulosController::class, 'applyToAllRoutes'])->name('apply.all');
        });

        Route::middleware('module.access:modulos,global')->group(function () {
            Route::post('/global/{id}/toggle', [SupervisorModulosController::class, 'toggleGlobal'])->name('toggle.global');
            Route::post('/global/{id}/config', [SupervisorModulosController::class, 'updateGlobalConfig'])->name('config.global');
        });

        Route::middleware('module.access:modulos,inventarios')->group(function () {
            Route::post('/inventarios/data', [SupervisorModulosController::class, 'inventariosData'])->name('inventarios.data');
        });
    });

    // Módulo Saneamiento Territorial & Calidad de Padrón
    Route::prefix('saneamiento')->name('saneamiento.')->middleware('module.access:saneamiento')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            $submodules = ['visor', 'cercanos', 'duplicados', 'dias', 'calidad', 'bd', 'propuestas', 'intrusos'];
            foreach ($submodules as $sub) {
                if ($user->hasModuleAccess('saneamiento', $sub)) {
                    return redirect()->route("supervisor.saneamiento.{$sub}");
                }
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Saneamiento.');
        })->name('index');

        // Submódulo 1: Visor y Fronteras (Mosaico Territorial y Puntos)
        Route::middleware('module.access:saneamiento,visor')->group(function () {
            Route::get('/visor', [SupervisorSaneamientoController::class, 'visor'])->name('visor');
            Route::post('/visor/data', [SupervisorSaneamientoController::class, 'getVisorData'])->name('visor.data');
            Route::post('/visor/ajustar-rtm', [SupervisorSaneamientoController::class, 'ajustarRtm'])->name('visor.ajustar-rtm');
            Route::post('/fronteras/guardar', [SupervisorSaneamientoController::class, 'guardarFronteraRuta'])->name('fronteras.guardar');
        });

        // Submódulo 2: Clientes Cercanos (Radar Geográfico Espacial)
        Route::middleware('module.access:saneamiento,cercanos')->group(function () {
            Route::get('/cercanos', [SupervisorSaneamientoController::class, 'cercanos'])->name('cercanos');
            Route::post('/cercanos/data', [SupervisorSaneamientoController::class, 'getCercanosData'])->name('cercanos.data');
            Route::post('/cercanos/resolver-enlace', [SupervisorSaneamientoController::class, 'resolverEnlaceMaps'])->name('cercanos.resolver-enlace');
            Route::post('/cercanos/cliente/{clienteId}/ventas', [SupervisorSaneamientoController::class, 'getVentasCliente'])->name('cercanos.cliente.ventas');
            Route::post('/cercanos/cliente/{clienteId}/visitas', [SupervisorSaneamientoController::class, 'getVisitasCliente'])->name('cercanos.cliente.visitas');
        });

        // Intrusos y Fronteras (compatibilidad / auditoría)
        Route::middleware('module.access:saneamiento,intrusos')->group(function () {
            Route::get('/intrusos', [SupervisorSaneamientoController::class, 'visor'])->name('intrusos');
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
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if ($user->hasModuleAccess('ingesta', 'dashboard')) {
                return app()->call([app(SupervisorIngestaController::class), 'index']);
            }
            $submodules = ['ventas', 'preventas', 'ruteo', 'visitas', 'inventario', 'cancelados', 'snapshots'];
            foreach ($submodules as $sub) {
                if ($user->hasModuleAccess('ingesta', $sub)) {
                    return redirect()->route("supervisor.ingesta.{$sub}");
                }
            }
            return redirect()->route('supervisor.index')->with('warning', 'No tienes submódulos habilitados en Ingesta.');
        })->name('index');

        // Centro / Dashboard Ingesta
        Route::middleware('module.access:ingesta,dashboard')->group(function () {
            Route::get('/tablas-estado', [SupervisorIngestaController::class, 'tablasEstado'])->name('tablas-estado');
            Route::get('/cargas-historial', [SupervisorIngestaController::class, 'cargasHistorial'])->name('cargas-historial');
        });

        // Ventas
        Route::middleware('module.access:ingesta,ventas')->group(function () {
            Route::get('/ventas', [SupervisorIngestaController::class, 'ventas'])->name('ventas');
            Route::post('/ventas', [SupervisorIngestaController::class, 'uploadVentas'])->middleware('module.access:ingesta,ventas,write')->name('upload.ventas');
        });

        // Preventas
        Route::middleware('module.access:ingesta,preventas')->group(function () {
            Route::get('/preventas', [SupervisorIngestaController::class, 'preventas'])->name('preventas');
            Route::post('/preventas', [SupervisorIngestaController::class, 'uploadPreventas'])->middleware('module.access:ingesta,preventas,write')->name('upload.preventas');
        });

        // Ruteo
        Route::middleware('module.access:ingesta,ruteo')->group(function () {
            Route::get('/ruteo', [SupervisorIngestaController::class, 'ruteo'])->name('ruteo');
            Route::post('/ruteo', [SupervisorIngestaController::class, 'uploadRuteo'])->middleware('module.access:ingesta,ruteo,write')->name('upload.ruteo');
        });

        // Visitas
        Route::middleware('module.access:ingesta,visitas')->group(function () {
            Route::get('/visitas', [SupervisorIngestaController::class, 'visitas'])->name('visitas');
            Route::post('/visitas', [SupervisorIngestaController::class, 'uploadVisitas'])->middleware('module.access:ingesta,visitas,write')->name('upload.visitas');
        });

        // Inventario
        Route::middleware('module.access:ingesta,inventario')->group(function () {
            Route::get('/inventario', [SupervisorIngestaController::class, 'inventario'])->name('inventario');
            Route::post('/inventario', [SupervisorIngestaController::class, 'uploadInventario'])->middleware('module.access:ingesta,inventario,write')->name('upload.inventario');
        });

        // Cancelados
        Route::middleware('module.access:ingesta,cancelados')->group(function () {
            Route::get('/cancelados', [SupervisorIngestaController::class, 'cancelados'])->name('cancelados');
            Route::get('/cancelados/pendientes', [SupervisorIngestaController::class, 'canceladosPendientes'])->name('cancelados.pendientes');
            Route::post('/preventas/autorizar-cancelados', [SupervisorIngestaController::class, 'autorizarCancelados'])->middleware('module.access:ingesta,cancelados,write')->name('preventas.autorizar-cancelados');
        });

        // Snapshots
        Route::middleware('module.access:ingesta,snapshots')->group(function () {
            Route::get('/snapshots', [SupervisorIngestaController::class, 'snapshots'])->name('snapshots');
            Route::get('/ruteo/snapshots', [SupervisorIngestaController::class, 'ruteoSnapshots'])->name('ruteo.snapshots');
            Route::post('/ruteo/rollback', [SupervisorIngestaController::class, 'rollbackRuteo'])->middleware('module.access:ingesta,snapshots,write')->name('ruteo.rollback');
        });
    });

    // Módulo Exclusivo Superadministrador: Permisos & Administración de Usuarios
    Route::prefix('admin')->name('admin.')->middleware(['superadmin'])->group(function () {
        // Submódulo 1: Gestión de Permisos de Supervisores
        Route::prefix('permisos')->name('permisos.')->group(function () {
            Route::get('/', [SupervisorAdminPermisosController::class, 'index'])->name('index');
            Route::post('/user/{userId}/toggle', [SupervisorAdminPermisosController::class, 'toggle'])->name('toggle');
            Route::post('/user/{userId}/batch', [SupervisorAdminPermisosController::class, 'batchSave'])->name('batch');
            Route::post('/user/{userId}/preset', [SupervisorAdminPermisosController::class, 'preset'])->name('preset');
            Route::post('/clone', [SupervisorAdminPermisosController::class, 'clone'])->name('clone');
        });

        // Submódulo 2: Administración de Usuarios (Creación, Edición y Estado)
        Route::prefix('usuarios')->name('usuarios.')->group(function () {
            Route::get('/', [SupervisorAdminUsuariosController::class, 'index'])->name('index');
            Route::post('/', [SupervisorAdminUsuariosController::class, 'store'])->name('store');
            Route::put('/{userId}', [SupervisorAdminUsuariosController::class, 'update'])->name('update');
            Route::patch('/{userId}/toggle-status', [SupervisorAdminUsuariosController::class, 'toggleStatus'])->name('toggle-status');
        });
    });
});
