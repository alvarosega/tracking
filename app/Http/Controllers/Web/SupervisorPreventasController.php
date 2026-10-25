<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Preventas\GetCumplimientoDataAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleAmpliacionRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleCompraMinimaAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleEfectividadRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleHorarioRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleObjetivoRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleReposicionRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetDetalleVisitasRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetPedidosRechazadosDataAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventaCategoriasAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventaDetalleRutaAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventaDiariaDataAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventaProductosDetalleAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventaProductosHeatmapAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventaProductosRankingAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventasDataAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventasInitDataAction;
use App\Actions\Web\Supervisor\Ruteo\ResolveMapsUrlAction;
use App\Actions\Web\Supervisor\Visitas\GetFotosClienteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Preventas\GetPreventasDataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorPreventasController extends Controller
{
    /**
     * Muestra la vista principal de Auditoría de Preventas (Mapa y Pedidos).
     */
    public function index(GetPreventasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Preventas/Index', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'fecha_default' => $initData['fecha_default'],
        ]);
    }

    /**
     * Devuelve las preventas agrupadas por cliente y pedidos para la fecha y filtros seleccionados.
     */
    public function data(GetPreventasDataRequest $request, GetPreventasDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            fecha: $request->fecha(),
            canales: $request->canales(),
            rutas: $request->rutas(),
            lat: $request->latitud(),
            lng: $request->longitud(),
            radio: $request->radio()
        );

        return response()->json($resultado);
    }

    /**
     * Resuelve y traduce un enlace de Google Maps o coordenadas numéricas.
     */
    public function resolverEnlaceMaps(Request $request, ResolveMapsUrlAction $action): JsonResponse
    {
        $input = (string) ($request->input('url') ?? $request->input('input', ''));
        $resultado = $action->execute($input);

        return response()->json($resultado);
    }

    /**
     * Consulta paginada de pedidos rechazados para la auditoría de supervisión.
     */
    public function rechazosData(Request $request, GetPedidosRechazadosDataAction $action): JsonResponse
    {
        $rechazos = $action->execute(
            fecha: $request->input('fecha'),
            ruta: $request->input('ruta'),
            motivo: $request->input('motivo'),
            perPage: (int) $request->input('per_page', 25)
        );

        return response()->json([
            'success' => true,
            'data' => $rechazos,
        ]);
    }

    /**
     * Muestra la vista del motor analítico Preventa > Cumplimiento (Modelo Unilever DSD).
     */
    public function cumplimiento(GetPreventasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Preventas/Cumplimiento', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'fecha_default' => $initData['fecha_default'],
        ]);
    }

    /**
     * Devuelve la evaluación consolidada de los 8 KPIs estratégicos sobre 100 puntos y las 9 pestañas monográficas.
     */
    public function cumplimientoData(Request $request, GetCumplimientoDataAction $action): JsonResponse
    {
        $fecha = (string) ($request->input('fecha') ?: date('Y-m-d'));
        $canales = (array) $request->input('canales', []);
        $rutas = (array) $request->input('rutas', []);
        $estrictoHorario = (bool) $request->boolean('estricto_horario', false);

        $resultado = $action->execute(
            fecha: $fecha,
            canales: $canales,
            rutas: $rutas,
            estrictoHorario: $estrictoHorario
        );

        return response()->json($resultado);
    }

    /**
     * Devuelve el recorrido cronológico detallado de una ruta para el acordeón del KPI 1.
     */
    public function cumplimientoHorarioDetalle(Request $request, GetDetalleHorarioRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha);

        return response()->json($resultado);
    }

    /**
     * Devuelve el desglose auditado de cobertura de cartera por cliente para el acordeón del KPI 2.
     */
    public function cumplimientoVisitasDetalle(Request $request, GetDetalleVisitasRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $estricto = (bool) $request->boolean('estricto', false);

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha, estricto: $estricto);

        return response()->json($resultado);
    }

    /**
     * Devuelve el balance y pedidos detallados para el acordeón del KPI 3 (Objetivo Diario).
     */
    public function cumplimientoObjetivoDetalle(Request $request, GetDetalleObjetivoRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $mes = $request->input('mes') ? (string) $request->input('mes') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha, mes: $mes);

        return response()->json($resultado);
    }

    /**
     * Devuelve el desglose de compradores y tickets para el acordeón del KPI 4 (Efectividad).
     */
    public function cumplimientoEfectividadDetalle(Request $request, GetDetalleEfectividadRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha);

        return response()->json($resultado);
    }

    /**
     * Devuelve el desglose de clientes bajo mínimo vs cumplen para el acordeón del KPI 5 (Mínimo 70 Bs).
     */
    public function cumplimientoCompraMinimaDetalle(Request $request, GetDetalleCompraMinimaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha);

        return response()->json($resultado);
    }

    /**
     * Devuelve la auditoría de reposición de SKUs habituales para el acordeón del KPI 6.
     */
    public function cumplimientoReposicionDetalle(Request $request, GetDetalleReposicionRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $semanas = $request->input('semanas_historia') ? (int) $request->input('semanas_historia') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha, semanasHistoria: $semanas);

        return response()->json($resultado);
    }

    /**
     * Devuelve la auditoría de ampliación de portafolio para el acordeón del KPI 7.
     */
    public function cumplimientoAmpliacionDetalle(Request $request, GetDetalleAmpliacionRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta', '');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $semanas = $request->input('semanas_historia') ? (int) $request->input('semanas_historia') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha, semanasHistoria: $semanas);

        return response()->json($resultado);
    }

    /**
     * Muestra la vista de Preventa Diaria (Recorrido de paradas, mapa interactivo y secuencia).
     */
    public function diaria(GetPreventasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Preventas/Diaria', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'fecha_default' => $initData['fecha_default'],
        ]);
    }

    /**
     * Devuelve el dashboard diario de preventa con matriz y KPIs de cumplimiento de ruta.
     */
    public function diariaData(Request $request, GetPreventaDiariaDataAction $action): JsonResponse
    {
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $canal = $request->input('canal') ? (string) $request->input('canal') : null;

        $resultado = $action->execute(fecha: $fecha, canal: $canal);

        return response()->json($resultado);
    }

    /**
     * Devuelve el itinerario, clientes secuenciados y evidencias fotográficas de una ruta específica.
     */
    public function rutaDetalle(string $ruta, Request $request, GetPreventaDetalleRutaAction $action): JsonResponse
    {
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;

        $resultado = $action->execute(ruta: $ruta, fecha: $fecha);

        return response()->json($resultado);
    }

    /**
     * Muestra la vista analítica de Productos en Preventa (Ranking, Desglose por ruta y Heatmap).
     */
    public function productos(GetPreventasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Preventas/Productos', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'fecha_default' => $initData['fecha_default'],
        ]);
    }

    /**
     * Devuelve la lista de categorías registradas en preventas para los filtros.
     */
    public function productosCategorias(Request $request, GetPreventaCategoriasAction $action): JsonResponse
    {
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $canal = $request->input('canal') ? (string) $request->input('canal') : null;

        $categorias = $action->execute(fecha: $fecha, canal: $canal);

        return response()->json([
            'success' => true,
            'categorias' => $categorias,
        ]);
    }

    /**
     * Devuelve el ranking de productos más vendidos.
     */
    public function productosRanking(Request $request, GetPreventaProductosRankingAction $action): JsonResponse
    {
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $canal = $request->input('canal') ? (string) $request->input('canal') : null;
        $rutas = $request->input('rutas');
        $categoria = $request->input('categoria') ? (string) $request->input('categoria') : null;
        $busqueda = $request->input('busqueda') ? (string) $request->input('busqueda') : null;
        $limit = (int) $request->input('limit', 150);

        $resultado = $action->execute(
            fecha: $fecha,
            canal: $canal,
            rutas: $rutas,
            categoria: $categoria,
            busqueda: $busqueda,
            limit: $limit
        );

        return response()->json($resultado);
    }

    /**
     * Devuelve el detalle de rutas que vendieron un SKU determinado.
     */
    public function productosDetalleRutas(Request $request, GetPreventaProductosDetalleAction $action): JsonResponse
    {
        $productoId = (int) $request->input('producto_id');
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $canal = $request->input('canal') ? (string) $request->input('canal') : null;
        $rutas = $request->input('rutas');

        $resultado = $action->execute(
            productoId: $productoId,
            fecha: $fecha,
            canal: $canal,
            rutas: $rutas
        );

        return response()->json($resultado);
    }

    /**
     * Devuelve la matriz 2D (heatmap) de Top N SKUs vs Rutas.
     */
    public function productosHeatmap(Request $request, GetPreventaProductosHeatmapAction $action): JsonResponse
    {
        $fecha = $request->input('fecha') ? (string) $request->input('fecha') : null;
        $canal = $request->input('canal') ? (string) $request->input('canal') : null;
        $rutas = $request->input('rutas');
        $categoria = $request->input('categoria') ? (string) $request->input('categoria') : null;
        $topSkus = (int) $request->input('top_skus', 30);

        $resultado = $action->execute(
            fecha: $fecha,
            canal: $canal,
            rutas: $rutas,
            categoria: $categoria,
            topSkus: $topSkus
        );

        return response()->json($resultado);
    }

    /**
     * Muestra la vista de Auditoría de Preventa.
     */
    public function auditoria(GetPreventasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Preventas/Auditoria', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'fecha_default' => $initData['fecha_default'],
        ]);
    }

    /**
     * Muestra la vista de Comparativos de Preventa.
     */
    public function comparativos(GetPreventasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Preventas/Comparativos', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'fecha_default' => $initData['fecha_default'],
        ]);
    }

    /**
     * Obtiene el histórico fotográfico de visitas de un cliente para el modal de auditoría en terreno.
     */
    public function fotosCliente(int $clienteId, GetFotosClienteAction $action): JsonResponse
    {
        $fotos = $action->execute($clienteId);

        return response()->json([
            'success' => true,
            'fotos' => $fotos,
        ]);
    }
}
