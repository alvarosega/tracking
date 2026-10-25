<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Tracking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetTrackingHistoricoDataAction
{
    /**
     * Paleta distintiva para contrastar cada fecha en el mapa de calor histórico.
     */
    private const PALETA_COLORES = [
        '#0071E3', '#34C759', '#FF9500', '#AF52DE',
        '#FF2D55', '#5856D6', '#00C7BE', '#A2845E'
    ];

    /**
     * Mapeo de día de semana en formato DAYOFWEEK de MySQL (1 = Domingo, 2 = Lunes, ..., 7 = Sábado).
     */
    private const DIAS_MAP_MYSQL = [
        'Domingo' => 1,
        'Lunes' => 2,
        'Martes' => 3,
        'Miércoles' => 4,
        'Jueves' => 5,
        'Viernes' => 6,
        'Sábado' => 7,
    ];

    /**
     * Consulta y estructura las trayectorias de un vendedor en un día recurrente de la semana
     * a lo largo de un período de tiempo, correlacionando telemetría GPS y visitas.
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaFin
     * @param string $diaSemana
     * @param string|null $rutaSeleccionada
     * @return array{
     *     trayectorias_fechas: array<int, array<string, mixed>>,
     *     kpis: array{
     *         total_fechas: int,
     *         total_puntos: int,
     *         total_visitas: int,
     *         alertas_mock: int
     *     }
     * }
     */
    public function execute(
        ?string $fechaInicio,
        ?string $fechaFin,
        string $diaSemana = 'Lunes',
        ?string $rutaSeleccionada = null
    ): array {
        $emptyResult = [
            'trayectorias_fechas' => [],
            'kpis' => [
                'total_fechas' => 0,
                'total_puntos' => 0,
                'total_visitas' => 0,
                'alertas_mock' => 0,
            ],
        ];

        if (!$rutaSeleccionada || !$fechaInicio || !$fechaFin) {
            return $emptyResult;
        }

        // Obtener user_id asociado a la ruta
        $user = DB::table('users')->where('username', $rutaSeleccionada)->first();
        if (!$user) {
            return $emptyResult;
        }

        $mysqlDayOfWeek = self::DIAS_MAP_MYSQL[$diaSemana] ?? 2;

        // 1. Obtener puntos de telemetría para ese usuario, en ese rango y día de la semana
        $locations = DB::table('locations')
            ->where('user_id', $user->id)
            ->whereBetween(DB::raw('DATE(recorded_at)'), [$fechaInicio, $fechaFin])
            ->whereRaw('DAYOFWEEK(recorded_at) = ?', [$mysqlDayOfWeek])
            ->select([
                'id',
                'latitude',
                'longitude',
                'accuracy',
                'speed',
                'battery_level',
                'is_mock',
                'is_moving',
                'step_count',
                'recorded_at',
                DB::raw('DATE(recorded_at) as fecha'),
            ])
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($locations->isEmpty()) {
            return $emptyResult;
        }

        // Fechas únicas que tuvieron datos
        $fechasUnicas = $locations->pluck('fecha')->unique()->values()->toArray();

        // 2. Cruce con visitas tomadas en esas mismas fechas por esa ruta (is_opportunity = 0)
        $visitas = DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->where('v.route', $rutaSeleccionada)
            ->where('v.is_opportunity', 0)
            ->whereIn(DB::raw('DATE(v.visited_at)'), $fechasUnicas)
            ->whereNotNull('v.photo_path')
            ->where('v.photo_path', '!=', '')
            ->select([
                'v.id',
                'v.client_id',
                'v.route',
                'v.status',
                'v.comments',
                'v.latitude as visita_lat',
                'v.longitude as visita_lng',
                'v.photo_path',
                'v.visited_at',
                DB::raw('DATE(v.visited_at) as fecha'),
                'u.username as vendedor',
            ])
            ->get();

        // Obtener nombres de clientes desde pan_ruteo
        $clientIds = $visitas->pluck('client_id')->unique()->filter()->toArray();
        $clientesMap = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn('cliente_id', $clientIds)
            ->select('cliente_id', DB::raw("COALESCE(NULLIF(cliente, ''), cliente_norm) as nombre"))
            ->distinct()
            ->pluck('nombre', 'cliente_id');

        $trayectoriasFechas = [];
        $totalAlertasMock = 0;
        $totalVisitasConteo = 0;
        $colorIdx = 0;

        foreach ($locations->groupBy('fecha') as $fecha => $puntosFecha) {
            $color = self::PALETA_COLORES[$colorIdx % count(self::PALETA_COLORES)];
            $colorIdx++;

            $visitasFecha = $visitas->where('fecha', $fecha)->values()->map(function ($vis, $i) use ($clientesMap) {
                return [
                    'id' => $vis->id,
                    'orden' => $i + 1,
                    'cliente_id' => $vis->client_id,
                    'cliente' => $clientesMap[$vis->client_id] ?? ('Cliente #' . $vis->client_id),
                    'status' => $vis->status,
                    'comments' => $vis->comments,
                    'hora' => date('H:i', strtotime($vis->visited_at)),
                    'visita_lat' => (float) $vis->visita_lat,
                    'visita_lng' => (float) $vis->visita_lng,
                    'photo_url' => Storage::url($vis->photo_path),
                ];
            });

            $totalVisitasConteo += $visitasFecha->count();

            $puntosFormateados = [];
            foreach ($puntosFecha as $p) {
                if ($p->is_mock) {
                    $totalAlertasMock++;
                }

                $puntosFormateados[] = [
                    'id' => $p->id,
                    'lat' => (float) $p->latitude,
                    'lng' => (float) $p->longitude,
                    'speed' => $p->speed !== null ? round((float) $p->speed, 1) : null,
                    'battery' => $p->battery_level,
                    'is_mock' => (bool) $p->is_mock,
                    'is_moving' => (bool) $p->is_moving,
                    'step_count' => $p->step_count,
                    'hora' => date('H:i:s', strtotime($p->recorded_at)),
                ];
            }

            $trayectoriasFechas[] = [
                'fecha' => $fecha,
                'fecha_formato' => date('d/m/Y', strtotime((string) $fecha)),
                'color' => $color,
                'total_puntos' => count($puntosFormateados),
                'total_visitas' => $visitasFecha->count(),
                'puntos' => $puntosFormateados,
                'visitas' => $visitasFecha,
            ];
        }

        return [
            'trayectorias_fechas' => $trayectoriasFechas,
            'kpis' => [
                'total_fechas' => count($trayectoriasFechas),
                'total_puntos' => $locations->count(),
                'total_visitas' => $totalVisitasConteo,
                'alertas_mock' => $totalAlertasMock,
            ],
        ];
    }
}

