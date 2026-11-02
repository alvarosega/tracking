<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Visitas\GetVisitasClientesAction;
use App\Actions\Api\Visitas\StoreVisitaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Visitas\StoreVisitaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitaController extends Controller
{
    /**
     * Retorna el listado ligero de clientes de la ruta para el submódulo de Visitas en Android.
     * Marca con 'visited' a los que corresponden al día asignado hoy en días de ruteo, y 'unvisited' al resto.
     * GET /api/visitas/clientes
     */
    public function clientes(Request $request, GetVisitasClientesAction $action): JsonResponse
    {
        $user = $request->user();
        $diaParam = $request->query('dia');

        $clientes = $action->execute(
            user: $user,
            diaParam: is_string($diaParam) ? $diaParam : null
        );

        return response()->json($clientes, 200);
    }

    /**
     * Procesa y registra una visita en campo.
     * POST /api/visitas (y /api/visitas/store)
     */
    public function store(StoreVisitaRequest $request, StoreVisitaAction $action): JsonResponse
    {
        $user = $request->user();
        $result = $action->execute(
            user: $user,
            data: $request->validated(),
            photo: $request->file('photo')
        );

        return response()->json([
            'message' => $result['message'],
            'id' => $result['id'],
            'uuid' => $result['uuid'],
            'photo_url' => $result['photo_url'],
        ], $result['status_code']);
    }
}
