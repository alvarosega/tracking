<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Fronteras\GetFronteraRutaAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FronteraController extends Controller
{
    /**
     * Retorna el polígono de frontera de la ruta comercial para Android.
     * GET /api/fronteras/ruta
     */
    public function ruta(Request $request, GetFronteraRutaAction $action): JsonResponse
    {
        $user = $request->user();
        $rutaParam = $request->query('ruta');

        $frontera = $action->execute(
            user: $user,
            rutaParam: is_string($rutaParam) ? $rutaParam : null
        );

        if (!$frontera) {
            return response()->json([
                'message' => 'No se encontró un polígono de frontera activo para la ruta solicitada.',
                'ruta' => $rutaParam ?: ($user->ruta ?: $user->username),
            ], 404);
        }

        return response()->json($frontera, 200);
    }
}

