<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Horarios;

use App\Models\RouteSchedule;

class UpdateRouteScheduleAction
{
    /**
     * Guarda o actualiza la configuración de horario para una ruta y día de la semana.
     *
     * @param string $route
     * @param int $dayOfWeek
     * @param string $startTime
     * @param string $endTime
     * @param bool $isWorkingDay
     * @return RouteSchedule
     */
    public function execute(
        string $route,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $isWorkingDay
    ): RouteSchedule {
        return RouteSchedule::updateOrCreate(
            [
                'route'       => trim($route),
                'day_of_week' => $dayOfWeek,
            ],
            [
                'start_time'     => strlen($startTime) === 5 ? $startTime . ':00' : $startTime,
                'end_time'       => strlen($endTime) === 5 ? $endTime . ':00' : $endTime,
                'is_working_day' => $isWorkingDay,
            ]
        );
    }
}

