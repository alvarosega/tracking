<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\DiasRuteo\GetDiasRuteoDataAction;
use App\Actions\Web\Supervisor\DiasRuteo\ResetRouteVisitDaysAction;
use App\Actions\Web\Supervisor\DiasRuteo\UpdateRouteVisitDaysAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorDiasRuteoController extends Controller
{
    /**
     * Muestra la interfaz de asignación de días de visita por ruta.
     */
    public function index(): Response
    {
        return Inertia::render('Supervisor/DiasRuteo/Index');
    }

    /**
     * Retorna la matriz de rutas, conteo de clientes y días de visita asignados.
     */
    public function data(GetDiasRuteoDataAction $action): JsonResponse
    {
        return response()->json($action->execute(), 200);
    }

    /**
     * Actualiza la asignación de días de visita para una ruta.
     * Solo permitido para Superadministrador o usuarios con permisos de escritura.
     */
    public function update(Request $request, UpdateRouteVisitDaysAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('horarios', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo los usuarios Superadministrador pueden modificar las asignaciones de días de ruteo.',
            ], 403);
        }

        $validated = $request->validate([
            'route' => 'required|string|max:50',
            'day_of_week' => 'nullable|integer|between:1,6',
            'dias_visita' => 'nullable|array',
            'dias_visita.*' => 'string|max:20',
            'is_active' => 'nullable|boolean',
            'dias_config' => 'nullable|array',
            'dias_config.*.day_of_week' => 'required|integer|between:1,6',
            'dias_config.*.dias_visita' => 'required|array',
            'dias_config.*.dias_visita.*' => 'string|max:20',
            'dias_config.*.is_active' => 'nullable|boolean',
        ]);

        $route = (string) $validated['route'];

        if (!empty($validated['dias_config'])) {
            $updated = $action->executeBatch(
                route: $route,
                diasConfig: $validated['dias_config'],
                userId: $user->id
            );

            return response()->json([
                'message' => "Asignación de días para la ruta {$route} actualizada exitosamente.",
                'records' => $updated,
            ], 200);
        }

        if (isset($validated['day_of_week'])) {
            $updated = $action->executeSingle(
                route: $route,
                dayOfWeek: (int) $validated['day_of_week'],
                diasVisita: (array) ($validated['dias_visita'] ?? []),
                isActive: (bool) ($validated['is_active'] ?? true),
                userId: $user->id
            );

            return response()->json([
                'message' => "Día de visita para la ruta {$route} actualizado exitosamente.",
                'record' => $updated,
            ], 200);
        }

        return response()->json([
            'message' => 'No se enviaron datos de configuración válidos.',
        ], 422);
    }

    /**
     * Restablece la configuración de una ruta a sus días naturales de calendario.
     */
    public function reset(Request $request, ResetRouteVisitDaysAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('horarios', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo los usuarios Superadministrador pueden restablecer las asignaciones de días de ruteo.',
            ], 403);
        }

        $validated = $request->validate([
            'route' => 'required|string|max:50',
        ]);

        $route = (string) $validated['route'];
        $deleted = $action->execute($route);

        return response()->json([
            'message' => "La ruta {$route} ha sido restablecida a sus días naturales de calendario ({$deleted} asignaciones eliminadas).",
        ], 200);
    }
}

