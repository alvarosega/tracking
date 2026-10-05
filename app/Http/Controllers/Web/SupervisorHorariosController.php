<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RouteSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorHorariosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Supervisor/Horarios/Index');
    }

    /**
     * Retorna la matriz de rutas activas junto a sus horarios por día de la semana.
     */
    public function data(): JsonResponse
    {
        // 1. Obtener todas las rutas únicas desde pan_ruteo (conexión supervisor)
        $rutas = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->distinct()
            ->orderBy('ruta', 'asc')
            ->pluck('ruta');

        // 2. Obtener todas las configuraciones de route_schedules (conexión supervisor)
        $schedules = RouteSchedule::all()->groupBy('route');

        $result = $rutas->map(function ($ruta) use ($schedules) {
            $dias = [];
            $routeData = $schedules->get($ruta, collect());

            for ($dia = 1; $dia <= 7; $dia++) {
                $sched = $routeData->firstWhere('day_of_week', $dia);
                $isSaturday = ($dia === 6);
                $isSunday = ($dia === 7);

                $dias[$dia] = [
                    'day_of_week'    => $dia,
                    'is_working_day' => $sched ? (bool) $sched->is_working_day : !$isSunday,
                    'start_time'     => $sched ? substr($sched->start_time, 0, 5) : '07:20',
                    'end_time'       => $sched ? substr($sched->end_time, 0, 5) : ($isSaturday ? '15:00' : '19:00'),
                ];
            }

            return [
                'route' => $ruta,
                'days'  => $dias,
            ];
        });

        return response()->json($result, 200);
    }

    /**
     * Guarda o actualiza la configuración de horario para una ruta y día específico.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route'          => 'required|string|max:50',
            'day_of_week'    => 'required|integer|between:1,7',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i|after:start_time',
            'is_working_day' => 'required|boolean',
        ]);

        $schedule = RouteSchedule::updateOrCreate(
            [
                'route'       => trim($validated['route']),
                'day_of_week' => $validated['day_of_week'],
            ],
            [
                'start_time'     => $validated['start_time'] . ':00',
                'end_time'       => $validated['end_time'] . ':00',
                'is_working_day' => $validated['is_working_day'],
            ]
        );

        return response()->json([
            'message'  => 'Horario actualizado correctamente.',
            'schedule' => $schedule,
        ], 200);
    }
}