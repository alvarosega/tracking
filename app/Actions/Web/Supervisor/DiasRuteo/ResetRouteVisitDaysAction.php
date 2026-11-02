<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\DiasRuteo;

use App\Models\RouteVisitDay;

class ResetRouteVisitDaysAction
{
    /**
     * Restablece la configuración de una ruta a sus días naturales de calendario,
     * eliminando las asignaciones personalizadas de route_visit_days.
     *
     * @param string $route
     * @return int Cantidad de registros eliminados/restablecidos
     */
    public function execute(string $route): int
    {
        $cleanRoute = strtoupper(trim(preg_replace('/\s+/', ' ', $route)));

        return RouteVisitDay::whereRaw("TRIM(UPPER(route)) = ?", [$cleanRoute])->delete();
    }
}
