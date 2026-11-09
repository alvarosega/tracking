<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\StockVentana\GetStockVentanaDataAction;
use App\Actions\Web\Supervisor\StockVentana\UpdateStockVentanaAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorStockVentanaController extends Controller
{
    /**
     * Muestra la interfaz de configuración de ventana histórica para toma de stock.
     */
    public function index(GetStockVentanaDataAction $action): Response
    {
        $data = $action->execute();

        return Inertia::render('Supervisor/StockVentana/Index', [
            'initialData' => $data,
        ]);
    }

    /**
     * Retorna los datos y matriz en JSON para recargas asíncronas.
     */
    public function data(GetStockVentanaDataAction $action): JsonResponse
    {
        return response()->json($action->execute(), 200);
    }

    /**
     * Actualiza la ventana histórica por defecto global.
     */
    public function updateGlobal(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'global', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Ajustes Globales pueden modificar la ventana global.',
            ], 403);
        }

        $validated = $request->validate([
            'dias_historial' => 'required|integer|between:1,365',
        ]);

        $dias = (int) $validated['dias_historial'];
        $modulo = $action->updateGlobal($dias);

        return response()->json([
            'success' => true,
            'message' => "Ventana histórica global actualizada a {$dias} días exitosamente.",
            'dias_historial' => $dias,
            'config' => $modulo->config,
        ], 200);
    }

    /**
     * Actualiza la ventana histórica para una ruta específica.
     */
    public function updateRoute(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Matriz pueden personalizar rutas.',
            ], 403);
        }

        $validated = $request->validate([
            'ruta' => 'required|string|max:50',
            'dias_historial' => 'required|integer|between:1,365',
        ]);

        $ruta = (string) $validated['ruta'];
        $dias = (int) $validated['dias_historial'];

        $override = $action->updateRoute($ruta, $dias);

        return response()->json([
            'success' => true,
            'message' => "Ventana histórica para la ruta {$ruta} configurada en {$dias} días.",
            'ruta' => $ruta,
            'dias_historial' => $dias,
            'config' => $override->config,
        ], 200);
    }

    /**
     * Restablece la ruta a los días de ventana global.
     */
    public function resetRoute(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Matriz pueden restablecer rutas.',
            ], 403);
        }

        $validated = $request->validate([
            'ruta' => 'required|string|max:50',
        ]);

        $ruta = (string) $validated['ruta'];
        $action->resetRoute($ruta);

        return response()->json([
            'success' => true,
            'message' => "La ruta {$ruta} ahora utiliza la ventana histórica global.",
            'ruta' => $ruta,
        ], 200);
    }

    /**
     * Actualiza masivamente un conjunto de rutas.
     */
    public function batchUpdate(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador pueden realizar modificaciones masivas.',
            ], 403);
        }

        $validated = $request->validate([
            'rutas' => 'required|array|min:1',
            'rutas.*' => 'string|max:50',
            'dias_historial' => 'required|integer|between:1,365',
        ]);

        $rutas = (array) $validated['rutas'];
        $dias = (int) $validated['dias_historial'];

        $total = $action->batchUpdate($rutas, $dias);

        return response()->json([
            'success' => true,
            'message' => "Se actualizaron {$total} rutas comerciales a {$dias} días de historial.",
            'total' => $total,
        ], 200);
    }

    /**
     * Activa o desactiva la toma de stock globalmente en app_modules.
     */
    public function toggleGlobal(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'global', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Ajustes Globales pueden activar o desactivar la toma de stock global.',
            ], 403);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $isActive = (bool) $validated['is_active'];
        $modulo = $action->toggleGlobal($isActive);
        $estadoStr = $isActive ? 'activada' : 'desactivada';

        return response()->json([
            'success' => true,
            'message' => "Toma de stock global {$estadoStr} exitosamente para Android.",
            'modulo_activo_global' => (bool) $modulo->is_active,
        ], 200);
    }

    /**
     * Activa o desactiva la toma de stock para una ruta específica en app_module_routes.
     */
    public function toggleRoute(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Matriz pueden modificar el estado de la ruta.',
            ], 403);
        }

        $validated = $request->validate([
            'ruta' => 'required|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $ruta = (string) $validated['ruta'];
        $isActive = (bool) $validated['is_active'];

        $override = $action->toggleRoute($ruta, $isActive);
        $estadoStr = $isActive ? 'activada' : 'desactivada';

        return response()->json([
            'success' => true,
            'message' => "Toma de stock {$estadoStr} para la ruta {$ruta}.",
            'ruta' => $ruta,
            'modulo_activo' => (bool) $override->is_active,
        ], 200);
    }

    /**
     * Activa o desactiva la toma de stock masivamente para un grupo de rutas.
     */
    public function batchToggle(Request $request, UpdateStockVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Matriz pueden realizar cambios masivos.',
            ], 403);
        }

        $validated = $request->validate([
            'rutas' => 'required|array|min:1',
            'rutas.*' => 'string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $rutas = (array) $validated['rutas'];
        $isActive = (bool) $validated['is_active'];

        $total = $action->batchToggle($rutas, $isActive);
        $estadoStr = $isActive ? 'activada' : 'desactivada';

        return response()->json([
            'success' => true,
            'message' => "Toma de stock {$estadoStr} exitosamente para {$total} rutas.",
            'total' => $total,
            'is_active' => $isActive,
        ], 200);
    }
}

