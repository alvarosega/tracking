<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\DiasRuteo;

use App\Models\RouteVisitDay;
use Illuminate\Support\Facades\DB;

class UpdateRouteVisitDaysAction
{
    public const DIAS_MAP = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    /**
     * Guarda o actualiza los días de visita de pan_ruteo para un día laboral de una ruta.
     *
     * @param string $route
     * @param int $dayOfWeek
     * @param array<int, string> $diasVisita
     * @param bool $isActive
     * @param int|null $userId
     * @return RouteVisitDay
     */
    public function executeSingle(
        string $route,
        int $dayOfWeek,
        array $diasVisita,
        bool $isActive = true,
        ?int $userId = null
    ): RouteVisitDay {
        $diaNombre = self::DIAS_MAP[$dayOfWeek] ?? 'Lunes';
        $diasFiltrados = array_values(array_filter(
            $diasVisita,
            fn($d) => !empty(trim((string) $d))
        ));

        // Si se vacía, restablecer al día natural
        if (empty($diasFiltrados)) {
            $diasFiltrados = [$diaNombre];
        }

        return RouteVisitDay::updateOrCreate(
            [
                'route' => trim($route),
                'day_of_week' => $dayOfWeek,
            ],
            [
                'dia_nombre' => $diaNombre,
                'dias_visita' => $diasFiltrados,
                'is_active' => $isActive,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Guarda o actualiza en bloque los 6 días laborales para una ruta comercial.
     *
     * @param string $route
     * @param array<int, array{day_of_week: int, dias_visita: array<int, string>, is_active?: bool}> $diasConfig
     * @param int|null $userId
     * @return array<int, RouteVisitDay>
     */
    public function executeBatch(
        string $route,
        array $diasConfig,
        ?int $userId = null
    ): array {
        $resultado = [];

        DB::transaction(function () use ($route, $diasConfig, $userId, &$resultado) {
            foreach ($diasConfig as $conf) {
                $dow = (int) ($conf['day_of_week'] ?? 0);
                if ($dow < 1 || $dow > 6) {
                    continue;
                }

                $diasVisita = (array) ($conf['dias_visita'] ?? []);
                $isActive = isset($conf['is_active']) ? (bool) $conf['is_active'] : true;

                $resultado[] = $this->executeSingle(
                    route: $route,
                    dayOfWeek: $dow,
                    diasVisita: $diasVisita,
                    isActive: $isActive,
                    userId: $userId
                );
            }
        });

        return $resultado;
    }
}

