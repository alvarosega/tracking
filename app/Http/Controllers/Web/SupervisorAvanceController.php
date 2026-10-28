<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Avance\GetAvanceBpcCatalogoAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceBpcClientesAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceBpcSellOutAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClienteDetalleFechasAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClientesRutaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClusterCatalogoAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClusterMatrizAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceCumplimientoDataAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceEntregasDetalleRutaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceEntregasHistoricoAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceEntregasTableroAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceHorariosDetalleRutaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceHorariosMatrizAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceHorariosTimelineAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceItinerarioSecuenciaAction;
use App\Actions\Web\Supervisor\Avance\SaveAvanceItinerarioSecuenciaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceHistoricoPreventasAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceHistoricoPreventasDetalleRutaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceInitDataAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceParetoAmplitudAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceParetoDataAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceParetoFrecuenciaAction;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorAvanceController extends Controller
{
    /**
     * Muestra la vista principal de Avance > Cumplimiento (Resumen Multi-KPI y Detalle Monográfico).
     */
    public function cumplimiento(GetAvanceInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Avance/Cumplimiento', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'meses' => $initData['meses'],
            'fecha_inicio_default' => $initData['fecha_inicio'],
            'fecha_corte_default' => $initData['fecha_corte'],
            'mes_default' => $initData['mes'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve la matriz de datos de cumplimiento (Resumen Multi-KPI o Detalle Monográfico por KPI).
     */
    public function cumplimientoData(Request $request, GetAvanceCumplimientoDataAction $action): JsonResponse
    {
        $vista = (string) $request->input('vista', 'resumen');
        $kpi = (string) $request->input('kpi', 'vol_general');
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
        $canales = (array) $request->input('canales', []);
        $rutas = (array) $request->input('rutas', []);
        $modoTemporal = (string) $request->input('modo_temporal', 'acumulado');

        $data = $action->execute(
            vista: $vista,
            kpiClave: $kpi,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte,
            canales: $canales,
            rutas: $rutas,
            modoTemporal: $modoTemporal
        );

        return response()->json($data);
    }

    /**
     * Devuelve los clientes individuales y ventas diarias acumuladas de una ruta para el acordeón.
     */
    public function clientesRuta(Request $request, GetAvanceClientesRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta');
        $kpi = (string) $request->input('kpi', 'vol_general');
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->execute(
            ruta: $ruta,
            kpiClave: $kpi,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte
        );

        return response()->json($data);
    }

    /**
     * Devuelve el historial de compras y facturas de un cliente para el modal de auditoría.
     */
    public function clienteDetalleFechas(Request $request, GetAvanceClienteDetalleFechasAction $action): JsonResponse
    {
        $clienteId = (int) $request->input('cliente_id');
        $ruta = (string) $request->input('ruta');
        $kpi = (string) $request->input('kpi', 'vol_general');
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->execute(
            clienteId: $clienteId,
            ruta: $ruta,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte,
            kpiClave: $kpi
        );

        return response()->json($data);
    }

    /**
     * Devuelve el cálculo de días hábiles transcurridos y restantes para el período seleccionado.
     */
    public function diasHabiles(Request $request, GetAvanceInitDataAction $action): JsonResponse
    {
        $mes = (string) ($request->input('mes') ?: date('Y-m'));
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->calcularDiasHabiles($mes, $fechaCorte);

        return response()->json($data);
    }

    /**
     * Muestra la vista principal de Avance > Preventas Históricas por Rango de Fechas.
     */
    public function historicoPreventas(GetAvanceInitDataAction $action): Response
    {
        $initData = $action->execute();

        // Para histórico de preventas, el rango por defecto óptimo es los últimos 7 días hasta la fecha de corte
        $corte = Carbon::parse($initData['fecha_corte']);
        $ini7Dias = $corte->copy()->subDays(6)->format('Y-m-d');

        return Inertia::render('Supervisor/Avance/HistoricoPreventas', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'meses' => $initData['meses'],
            'fecha_inicio_default' => $ini7Dias,
            'fecha_corte_default' => $initData['fecha_corte'],
            'mes_default' => $initData['mes'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve la matriz consolidada de preventas por ruta y días hábiles.
     */
    public function historicoPreventasData(Request $request, GetAvanceHistoricoPreventasAction $action): JsonResponse
    {
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
        $canales = (array) $request->input('canales', []);
        $rutas = (array) $request->input('rutas', []);

        $data = $action->execute(
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte,
            canales: $canales,
            rutas: $rutas
        );

        return response()->json($data);
    }

    /**
     * Devuelve el desglose detallado de clientes que compraron en la ruta para el acordeón.
     */
    public function historicoPreventasDetalleRuta(Request $request, GetAvanceHistoricoPreventasDetalleRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta');
        $fechaInicio = (string) $request->input('fecha_inicio');
        $fechaCorte = (string) $request->input('fecha_corte');

        $data = $action->execute(
            ruta: $ruta,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte
        );

        return response()->json($data);
    }

    /**
     * Muestra la vista principal de Avance > Cluster vs LY (Análisis multianual comparativo).
     */
    public function cluster(GetAvanceClusterCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Avance/Cluster', [
            'catalogo' => $catalogo,
        ]);
    }

    /**
     * Devuelve la matriz comparativa de ventas por cluster vs LY y desglose multinivel.
     */
    public function clusterMatriz(Request $request, GetAvanceClusterMatrizAction $action): JsonResponse
    {
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
        $canales = (array) $request->input('canales', []);
        $rutas = (array) $request->input('rutas', []);
        $clusters = (array) $request->input('clusters', []);
        $metrica = (string) $request->input('metrica', 'monto');
        $agrupacion = (string) $request->input('agrupacion', 'cluster_jerarquia');

        $data = $action->execute(
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte,
            canales: $canales,
            rutas: $rutas,
            clusters: $clusters,
            metrica: $metrica,
            agrupacion: $agrupacion
        );

        return response()->json($data);
    }

    /**
     * Devuelve el catálogo de filtros disponible para Cluster vs LY.
     */
    public function clusterCatalogo(GetAvanceClusterCatalogoAction $action): JsonResponse
    {
        return response()->json($action->execute());
    }

    /**
     * Muestra la vista principal de Avance > Paretos & Scoring Multicriterio.
     */
    public function paretos(GetAvanceInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Avance/Paretos', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'meses' => $initData['meses'],
            'fecha_inicio_default' => $initData['fecha_inicio'],
            'fecha_corte_default' => $initData['fecha_corte'],
            'mes_default' => $initData['mes'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve el análisis Pareto consolidado y métricas de clientes.
     */
    public function paretosData(Request $request, GetAvanceParetoDataAction $action): JsonResponse
    {
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->execute(
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte
        );

        return response()->json($data);
    }

    /**
     * Devuelve el desglose de SKUs y marcas compradas por un cliente para el modal de amplitud.
     */
    public function paretoDetalleAmplitud(Request $request, GetAvanceParetoAmplitudAction $action): JsonResponse
    {
        $clienteId = (int) $request->input('cliente_id');
        $fechaInicio = (string) $request->input('fecha_inicio');
        $fechaCorte = (string) $request->input('fecha_corte');

        $items = $action->execute(
            clienteId: $clienteId,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte
        );

        return response()->json(['items' => $items]);
    }

    /**
     * Devuelve el historial de facturas efectivas del cliente para el modal de frecuencia.
     */
    public function paretoDetalleFrecuencia(Request $request, GetAvanceParetoFrecuenciaAction $action): JsonResponse
    {
        $clienteId = (int) $request->input('cliente_id');
        $fechaInicio = (string) $request->input('fecha_inicio');
        $fechaCorte = (string) $request->input('fecha_corte');

        $compras = $action->execute(
            clienteId: $clienteId,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte
        );

        return response()->json(['compras' => $compras]);
    }

    /**
     * Muestra la vista principal de Avance > Avance de Entregas (Cruce Preventa vs Facturación).
     */
    public function entregas(GetAvanceInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Avance/Entregas', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'meses' => $initData['meses'],
            'fecha_inicio_default' => $initData['fecha_inicio'],
            'fecha_corte_default' => $initData['fecha_corte'],
            'mes_default' => $initData['mes'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve el cruce diario D-1 de preventas vs ventas facturadas por ruta.
     */
    public function entregasTablero(Request $request, GetAvanceEntregasTableroAction $action): JsonResponse
    {
        try {
            $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
            $canal = $request->input('canal') ? (string) $request->input('canal') : null;
            $ruta = $request->input('ruta') ? (string) $request->input('ruta') : null;

            $data = $action->execute(
                fechaCorte: $fechaCorte,
                canal: $canal,
                ruta: $ruta
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en entregasTablero: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar entregas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve el consolidado histórico de entregas por ruta en un rango de fechas.
     */
    public function entregasHistorico(Request $request, GetAvanceEntregasHistoricoAction $action): JsonResponse
    {
        try {
            $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
            $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

            $data = $action->execute(
                fechaInicio: $fechaInicio,
                fechaCorte: $fechaCorte
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en entregasHistorico: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar histórico de entregas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve el detalle completo de pedidos e ítems de una ruta específica en el histórico.
     */
    public function entregasHistoricoDetalleRuta(Request $request, GetAvanceEntregasDetalleRutaAction $action): JsonResponse
    {
        try {
            $ruta = (string) $request->input('ruta');
            $fechaInicio = (string) $request->input('fecha_inicio');
            $fechaCorte = (string) $request->input('fecha_corte');

            $data = $action->execute(
                ruta: $ruta,
                fechaInicio: $fechaInicio,
                fechaCorte: $fechaCorte
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en entregasHistoricoDetalleRuta: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar detalle de ruta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra la vista principal de Avance > Sell Out y BPC.
     */
    public function bpc(GetAvanceInitDataAction $action, GetAvanceBpcCatalogoAction $catalogoAction): Response
    {
        $initData = $action->execute();
        $bpcCat = $catalogoAction->execute();

        return Inertia::render('Supervisor/Avance/Bpc', [
            'catalogo_rutas' => $bpcCat['rutas'] ?: $initData['catalogo_rutas'],
            'canales' => $bpcCat['canales'] ?: $initData['canales'],
            'clusters' => $bpcCat['clusters'],
            'meses' => $bpcCat['meses'] ?: $initData['meses'],
            'mes_default' => $bpcCat['mes_evaluado_defecto'] ?: $initData['mes'],
            'fecha_corte_default' => $bpcCat['fecha_corte_defecto'] ?: $initData['fecha_corte'],
            'fecha_inicio_default' => $initData['fecha_inicio'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve las dos tablas paralelas de Clientes Activos Totales y Paretos BPC.
     */
    public function bpcClientes(Request $request, GetAvanceBpcClientesAction $action): JsonResponse
    {
        try {
            $mesEvaluado = $request->input('mes_evaluado') ? (string) $request->input('mes_evaluado') : null;
            $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
            $canales = (array) $request->input('canales', []);
            $rutas = (array) $request->input('rutas', []);
            $clusters = (array) $request->input('clusters', []);

            $data = $action->execute(
                mesEvaluado: $mesEvaluado,
                fechaCorte: $fechaCorte,
                canales: $canales,
                rutas: $rutas,
                clusters: $clusters
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en bpcClientes: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar clientes BPC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve las dos tablas paralelas de Sell Out Totales y Paretos BPC en Bs.
     */
    public function bpcSellOut(Request $request, GetAvanceBpcSellOutAction $action): JsonResponse
    {
        try {
            $mesEvaluado = $request->input('mes_evaluado') ? (string) $request->input('mes_evaluado') : null;
            $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
            $canales = (array) $request->input('canales', []);
            $rutas = (array) $request->input('rutas', []);
            $clusters = (array) $request->input('clusters', []);

            $data = $action->execute(
                mesEvaluado: $mesEvaluado,
                fechaCorte: $fechaCorte,
                canales: $canales,
                rutas: $rutas,
                clusters: $clusters
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en bpcSellOut: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar Sell Out BPC: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra la vista principal de Avance > Horarios e Itinerario.
     */
    public function horarios(GetAvanceInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Avance/Horarios', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'meses' => $initData['meses'],
            'fecha_corte_default' => $initData['fecha_corte'],
            'fecha_inicio_default' => Carbon::parse($initData['fecha_corte'])->subDays(6)->format('Y-m-d'),
            'mes_default' => $initData['mes'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve la matriz comparativa de horarios (Sistema Web vs Móvil).
     */
    public function horariosMatriz(Request $request, GetAvanceHorariosMatrizAction $action): JsonResponse
    {
        try {
            $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
            $fechaFin = $request->input('fecha_fin') ? (string) $request->input('fecha_fin') : null;
            $canal = $request->input('canales') ?: $request->input('canal');
            $rutas = $request->input('rutas');
            $busqueda = $request->input('busqueda');

            $data = $action->execute(
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin,
                canal: $canal,
                rutas: $rutas,
                busqueda: $busqueda ? (string) $busqueda : null
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en horariosMatriz: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar matriz de horarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve el desglose cronológico e histórico completo de una ruta en el rango seleccionado.
     */
    public function horariosDetalleRuta(Request $request, GetAvanceHorariosDetalleRutaAction $action): JsonResponse
    {
        try {
            $ruta = (string) $request->input('ruta');
            $fechaInicio = (string) $request->input('fecha_inicio');
            $fechaFin = (string) ($request->input('fecha_fin') ?: $request->input('fecha_corte'));

            if (!$ruta || !$fechaInicio || !$fechaFin) {
                return response()->json([
                    'message' => 'Se requiere ruta, fecha de inicio y fecha de fin'
                ], 422);
            }

            $data = $action->execute(
                ruta: $ruta,
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin
            );

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en horariosDetalleRuta: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar desglose histórico de la ruta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve el timeline cronológico detallado de una jornada para una ruta y fecha.
     */
    public function horariosTimeline(Request $request, GetAvanceHorariosTimelineAction $action): JsonResponse
    {
        try {
            $fecha = (string) $request->input('fecha');
            $ruta = (string) $request->input('ruta');

            if (!$fecha || !$ruta) {
                return response()->json([
                    'message' => 'Se requiere fecha y ruta'
                ], 422);
            }

            $data = $action->execute(fecha: $fecha, ruta: $ruta);

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en horariosTimeline: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar timeline de horarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Devuelve la secuencia 1..N de clientes y coordenadas para el mapa del itinerario.
     */
    public function itinerarioSecuencia(Request $request, GetAvanceItinerarioSecuenciaAction $action): JsonResponse
    {
        try {
            $ruta = (string) $request->input('ruta');
            $dia = $request->input('dia') ? (string) $request->input('dia') : null;

            if (!$ruta) {
                return response()->json([
                    'message' => 'Se requiere la ruta'
                ], 422);
            }

            $data = $action->execute(ruta: $ruta, diaNorm: $dia);

            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en itinerarioSecuencia: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al consultar secuencia de itinerario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guarda el nuevo orden secuencial 1..N de clientes para una ruta y día.
     */
    public function itinerarioGuardar(Request $request, SaveAvanceItinerarioSecuenciaAction $action): JsonResponse
    {
        try {
            $ruta = (string) $request->input('ruta');
            $dia = (string) $request->input('dia');
            $secuencia = (array) $request->input('secuencia', []);

            if (!$ruta || !$dia) {
                return response()->json([
                    'message' => 'Se requiere ruta y día'
                ], 422);
            }

            $afectados = $action->execute(ruta: $ruta, diaNorm: $dia, secuencia: $secuencia);

            return response()->json([
                'status' => 'success',
                'message' => 'Secuencia guardada exitosamente',
                'total_guardados' => $afectados,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en itinerarioGuardar: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error al guardar secuencia de itinerario: ' . $e->getMessage()
            ], 500);
        }
    }
}

