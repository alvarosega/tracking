<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppModule;
use App\Models\AppModuleRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppModuleController extends Controller
{
    /**
     * Retorna el listado de módulos de la aplicación móvil para el vendedor / ruta autenticado.
     * Considera las habilitaciones y configuraciones específicas por RUTA.
     * GET /api/app/modulos
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ruta = $user ? trim((string) $user->ruta) : null;

        // 1. Obtener módulos base
        $modules = AppModule::orderBy('order')->get();

        // 2. Si el usuario tiene ruta asignada, buscar overrides para su ruta
        $routeOverrides = collect();
        if (!empty($ruta)) {
            $routeOverrides = AppModuleRoute::where('ruta', $ruta)->get()->keyBy('module_code');
        }

        $result = $modules->map(function ($mod) use ($routeOverrides) {
            $override = $routeOverrides->get($mod->code);

            // Si existe regla específica para la ruta, prima sobre la global
            $isActive = $override !== null ? (bool) $override->is_active : (bool) $mod->is_active;

            // La configuración específica de la ruta se mezcla con la base
            $config = $mod->config ?? [];
            if ($override && !empty($override->config)) {
                $config = array_merge($config, $override->config);
            }

            return [
                'id' => $mod->id,
                'code' => $mod->code,
                'name' => $mod->name,
                'description' => $mod->description,
                'is_active' => $isActive,
                'order' => $mod->order,
                'config' => $config,
            ];
        });

        return response()->json([
            'success' => true,
            'ruta' => $ruta,
            'data' => $result,
        ], 200);
    }
}
