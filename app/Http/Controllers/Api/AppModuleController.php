<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\GetAppModulesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppModuleController extends Controller
{
    /**
     * Retorna el listado de módulos móviles y su configuración para el vendedor autenticado.
     * GET /api/app/modulos
     */
    public function index(Request $request, GetAppModulesAction $action): JsonResponse
    {
        $result = $action->execute($request->user());

        return response()->json([
            'success' => true,
            'ruta' => $result['ruta'],
            'data' => $result['modules'],
        ], 200);
    }
}
