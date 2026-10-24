<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppModule;
use Illuminate\Http\JsonResponse;

class AppModuleController extends Controller
{
    /**
     * Retorna el listado de módulos de la aplicación móvil con su estado de activación y configuración.
     * GET /api/app/modulos
     */
    public function index(): JsonResponse
    {
        $modules = AppModule::orderBy('order')->get([
            'id',
            'code',
            'name',
            'description',
            'is_active',
            'order',
            'config',
        ]);

        return response()->json([
            'success' => true,
            'data' => $modules,
        ], 200);
    }
}

