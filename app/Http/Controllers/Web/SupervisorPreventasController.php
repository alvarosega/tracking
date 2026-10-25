<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Preventas\GetPedidosRechazadosDataAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventasDataAction;
use App\Actions\Web\Supervisor\Preventas\GetPreventasInitDataAction;
use App\Actions\Web\Supervisor\Ruteo\ResolveMapsUrlAction;
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
}
