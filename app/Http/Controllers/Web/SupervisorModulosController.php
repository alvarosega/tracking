<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Modulos\ApplyModuleToAllRoutesAction;
use App\Actions\Web\Supervisor\Modulos\GetClienteInventariosDataAction;
use App\Actions\Web\Supervisor\Modulos\GetModulosInitDataAction;
use App\Actions\Web\Supervisor\Modulos\ToggleGlobalModuleAction;
use App\Actions\Web\Supervisor\Modulos\ToggleRouteModuleAction;
use App\Actions\Web\Supervisor\Modulos\UpdateGlobalModuleConfigAction;
use App\Actions\Web\Supervisor\Modulos\UpdateRouteModuleConfigAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorModulosController extends Controller
{
    /**
     * Muestra la interfaz de gestión de módulos por RUTA comercial y parámetros.
     */
    public function index(GetModulosInitDataAction $action): Response
    {
        $data = $action->execute();

        return Inertia::render('Supervisor/Modulos/Index', [
            'modules' => $data['modules'],
            'rutas' => $data['rutas'],
            'routeOverrides' => $data['routeOverrides'],
        ]);
    }

    /**
     * Activa o desactiva un módulo móvil para una RUTA específica.
     */
    public function toggleRoute(Request $request, ToggleRouteModuleAction $action): JsonResponse
    {
        $validated = $request->validate([
            'ruta' => ['required', 'string'],
            'module_code' => ['required', 'string', 'exists:app_modules,code'],
            'is_active' => ['required', 'boolean'],
        ]);

        $record = $action->execute(
            ruta: (string) $validated['ruta'],
            moduleCode: (string) $validated['module_code'],
            isActive: (bool) $validated['is_active']
        );

        $estadoStr = $record->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'message' => "Módulo '{$record->module_code}' {$estadoStr} para la ruta '{$record->ruta}'.",
            'override' => $record,
        ]);
    }

    /**
     * Actualiza la configuración específica de un módulo para una RUTA (ej: días de historial).
     */
    public function updateRouteConfig(Request $request, UpdateRouteModuleConfigAction $action): JsonResponse
    {
        $validated = $request->validate([
            'ruta' => ['required', 'string'],
            'module_code' => ['required', 'string', 'exists:app_modules,code'],
            'config' => ['required', 'array'],
        ]);

        $record = $action->execute(
            ruta: (string) $validated['ruta'],
            moduleCode: (string) $validated['module_code'],
            config: (array) $validated['config']
        );

        return response()->json([
            'success' => true,
            'message' => "Configuración para la ruta '{$record->ruta}' guardada correctamente.",
            'override' => $record,
        ]);
    }

    /**
     * Aplica el estado de un módulo a TODAS las rutas comerciales masivamente.
     */
    public function applyToAllRoutes(Request $request, ApplyModuleToAllRoutesAction $action): JsonResponse
    {
        $validated = $request->validate([
            'module_code' => ['required', 'string', 'exists:app_modules,code'],
            'is_active' => ['required', 'boolean'],
            'rutas' => ['required', 'array'],
        ]);

        $action->execute(
            moduleCode: (string) $validated['module_code'],
            isActive: (bool) $validated['is_active'],
            rutas: (array) $validated['rutas']
        );

        $estadoStr = $validated['is_active'] ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'message' => "Módulo '{$validated['module_code']}' {$estadoStr} para todas las rutas seleccionadas.",
        ]);
    }

    /**
     * Activa o desactiva un módulo móvil a nivel GLOBAL por defecto.
     */
    public function toggleGlobal(int $id, ToggleGlobalModuleAction $action): JsonResponse
    {
        $module = $action->execute($id);
        $estadoStr = $module->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'message' => "Módulo global '{$module->name}' {$estadoStr} por defecto.",
            'module' => $module,
        ]);
    }

    /**
     * Actualiza la configuración global por defecto de un módulo.
     */
    public function updateGlobalConfig(Request $request, int $id, UpdateGlobalModuleConfigAction $action): JsonResponse
    {
        $validated = $request->validate([
            'config' => ['required', 'array'],
        ]);

        $module = $action->execute($id, (array) $validated['config']);

        return response()->json([
            'success' => true,
            'message' => "Configuración global de '{$module->name}' actualizada.",
            'module' => $module,
        ]);
    }

    /**
     * Consulta de inventarios tomados en campo por los vendedores.
     */
    public function inventariosData(Request $request, GetClienteInventariosDataAction $action): JsonResponse
    {
        $inventarios = $action->execute(
            fecha: $request->query('fecha'),
            ruta: $request->query('ruta'),
            clienteId: $request->query('cliente_id'),
            perPage: 20
        );

        return response()->json([
            'success' => true,
            'data' => $inventarios,
        ]);
    }
}
