<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Horarios;

use App\Models\RouteSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetHorariosDataAction
{
    /**
     * Retorna la matriz unificada de rutas comerciales activas junto a sus horarios por día de la semana.
     *
     * @return Collection
     */
    public function execute(): Collection
    {
        // 1. Obtener catálogo maestro de rutas desde dim_rutas
        $dimRows = DB::connection('supervisor')
            ->table('dim_rutas')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->select('ruta', 'canal', 'vendedor')
            ->get();

        $metaRuta = $dimRows->keyBy('ruta');
        $rutasDim = $dimRows->pluck('ruta');

        // 2. Obtener rutas que existan en pan_ruteo
        $rutasPan = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->distinct()
            ->pluck('ruta');

        // 3. Obtener rutas con horarios ya registrados en route_schedules
        $rutasConfig = RouteSchedule::query()
            ->whereNotNull('route')
            ->where('route', '!=', '')
            ->distinct()
            ->pluck('route');

        // 4. Unificar todas las rutas únicas
        $rutas = $rutasDim
            ->concat($rutasPan)
            ->concat($rutasConfig)
            ->map(fn($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // 5. Obtener todas las configuraciones de route_schedules
        $schedules = RouteSchedule::all()->groupBy('route');

        return $rutas->map(function ($ruta) use ($schedules, $metaRuta) {
            $dias = [];
            $routeData = $schedules->get($ruta, collect());

            for ($dia = 1; $dia <= 7; $dia++) {
                $sched = $routeData->firstWhere('day_of_week', $dia);
                $isSaturday = ($dia === 6);
                $isSunday = ($dia === 7);

                $dias[$dia] = [
                    'day_of_week'    => $dia,
                    'is_working_day' => $sched ? (bool) $sched->is_working_day : !$isSunday,
                    'start_time'     => $sched ? substr((string) $sched->start_time, 0, 5) : '07:20',
                    'end_time'       => $sched ? substr((string) $sched->end_time, 0, 5) : ($isSaturday ? '15:00' : '19:00'),
                ];
            }

            return [
                'route'    => $ruta,
                'canal'    => $metaRuta[$ruta]->canal ?? null,
                'vendedor' => $metaRuta[$ruta]->vendedor ?? null,
                'days'     => $dias,
            ];
        });
    }
}

