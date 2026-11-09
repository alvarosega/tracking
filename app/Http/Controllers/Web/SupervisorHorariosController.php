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
        $route = trim((string) $request->input('route'));
        $dayOfWeek = (int) $request->input('day_of_week');
        $isWorkingDay = filter_var($request->input('is_working_day', true), FILTER_VALIDATE_BOOLEAN);

        if ($route === '' || $dayOfWeek < 1 || $dayOfWeek > 7) {
            return response()->json([
                'message' => 'Ruta y día de la semana válidos son requeridos.',
                'errors' => [
                    'route' => ['Ruta requerida.'],
                    'day_of_week' => ['Día de semana debe estar entre 1 y 7.']
                ]
            ], 422);
        }

        $rawStart = trim((string) $request->input('start_time', ''));
        $rawEnd = trim((string) $request->input('end_time', ''));

        // Normalizar formato H:i
        $startTime = substr($rawStart, 0, 5);
        $endTime = substr($rawEnd, 0, 5);

        if (!$isWorkingDay) {
            // Día no laborable: si no viene horario, default seguro
            $startTime = $startTime !== '' ? $startTime : '00:00';
            $endTime = $endTime !== '' ? $endTime : '00:00';
        } else {
            // Día laborable: validar y asegurar horas coherentes
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime)) {
                $startTime = '07:20';
            }
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)) {
                $endTime = ($dayOfWeek === 6) ? '15:00' : '19:00';
            }
            if ($endTime <= $startTime) {
                // Si la hora de salida no es posterior, asignar salida estándar
                $endTime = ($dayOfWeek === 6) ? '15:00' : '19:00';
                if ($endTime <= $startTime) {
                    $endTime = '23:59';
                }
            }
        }

        $schedule = $action->execute(
            route: $route,
            dayOfWeek: $dayOfWeek,
            startTime: $startTime,
            endTime: $endTime,
            isWorkingDay: $isWorkingDay
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
