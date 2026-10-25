<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Horarios\ForceCloseWorkdayAction;
use App\Actions\Web\Supervisor\Horarios\GetActiveWorkdaysAction;
use App\Actions\Web\Supervisor\Horarios\GetHorariosDataAction;
use App\Actions\Web\Supervisor\Horarios\UpdateRouteScheduleAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorHorariosController extends Controller
{
    /**
     * Muestra la interfaz principal de la matriz de horarios de trabajo por ruta.
     */
    public function index(): Response
    {
        return Inertia::render('Supervisor/Horarios/Index');
    }

    /**
     * Retorna la matriz de rutas activas junto a sus horarios por día de la semana.
     */
    public function data(GetHorariosDataAction $action): JsonResponse
    {
        return response()->json($action->execute(), 200);
    }

    /**
     * Guarda o actualiza la configuración de horario para una ruta y día específico.
     */
    public function update(Request $request, UpdateRouteScheduleAction $action): JsonResponse
    {
        $validated = $request->validate([
            'route'          => 'required|string|max:50',
            'day_of_week'    => 'required|integer|between:1,7',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i|after:start_time',
            'is_working_day' => 'required|boolean',
        ]);

        $schedule = $action->execute(
            route: (string) $validated['route'],
            dayOfWeek: (int) $validated['day_of_week'],
            startTime: (string) $validated['start_time'],
            endTime: (string) $validated['end_time'],
            isWorkingDay: (bool) $validated['is_working_day']
        );

        return response()->json([
            'message'  => 'Horario actualizado correctamente.',
            'schedule' => $schedule,
        ], 200);
    }

    /**
     * Retorna la lista en vivo de usuarios con jornada iniciada hoy.
     */
    public function activeWorkdays(GetActiveWorkdaysAction $action): JsonResponse
    {
        return response()->json($action->execute(), 200);
    }

    /**
     * Cierre remoto individual ejecutado desde el panel de horarios.
     */
    public function forceCloseWorkday(Request $request, int $userId, ForceCloseWorkdayAction $action): JsonResponse
    {
        $supervisor = $request->user();
        $reason = $request->input('reason', 'Cierre manual ejecutado por el supervisor desde el panel de horarios');

        $workday = $action->execute(
            userId: $userId,
            supervisorId: $supervisor ? $supervisor->id : null,
            reason: (string) $reason
        );

        if (!$workday) {
            return response()->json(['message' => 'El usuario no tiene una jornada abierta hoy.'], 404);
        }

        return response()->json([
            'message' => 'Jornada finalizada exitosamente.',
            'workday' => $workday,
        ], 200);
    }
}
