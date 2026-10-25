<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Preventas\GetCumplimientoDataAction;
use App\Actions\Web\Supervisor\Preventas\GetPedidosRechazadosDataAction;
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
     * Muestra la vista principal de Auditoría de Preventas.
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
