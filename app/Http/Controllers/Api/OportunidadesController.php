<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Oportunidades\GetOportunidadesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OportunidadesController extends Controller
{
    /**
     * Consulta las oportunidades de venta georreferenciadas dentro de la frontera del día.
     * GET /api/oportunidades
     */
    public function index(Request $request, GetOportunidadesAction $action): JsonResponse
    {
        $user = $request->user();
        $dia = $request->query('dia');

        $result = $action->execute($user, is_string($dia) ? $dia : null);

        return response()->json($result, 200);
    }
}
