<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Tracking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetTrackingDiaDataAction
{
    /**
     * Consulta la telemetría en vivo y recorrido de un vendedor durante una jornada,
     * calculando tramos por velocidad/estado y correlacionando visitas geolocalizadas.
     *
     * @param string $fecha
     * @param string|null $ruta
     * @return array{
     *     puntos: array<int, array<string, mixed>>,
     *     visitas: array<int, array<string, mixed>>,
     *     tramos: array<int, array<string, mixed>>,
     *     kpis: array{
     *         total_puntos: int,
     *         total_visitas: int,
     *         bateria_actual: int|null,
     *         velocidad_actual: float|null,
     *         pasos_actual: int,
     *         alertas_mock: int,
     *         ultimo_reporte: string|null,
     *         en_movimiento: bool
     *     }
     * }
     */
    public function execute(string $fecha, ?string $ruta): array
    {
        $emptyResult = [
            'puntos' => [],
            'visitas' => [],
            'tramos' => [],
            'kpis' => [
                'total_puntos' => 0,
                'total_visitas' => 0,
                'bateria_actual' => null,
                'velocidad_actual' => null,
                'pasos_actual' => 0,
                'alertas_mock' => 0,
                'ultimo_reporte' => null,
                'en_movimiento' => false,
            ],
        ];

        if (!$ruta) {
            return $emptyResult;
        }

        // Obtener user_id asociado a la ruta
        $user = DB::table('users')->where('username', $ruta)->first();
        if (!$user) {
            return $emptyResult;
        }

        // 1. Obtener puntos de telemetría de la fecha seleccionada
        $locations = DB::table('locations')
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $fecha)
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
                'motion_variance',
                'recorded_at',
            ])
            ->orderBy('recorded_at', 'asc')
            ->get();

        // 2. Obtener visitas tomadas en esa fecha (is_opportunity = 0 con foto)
        $visitas = DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->where('v.route', $ruta)
            ->whereDate('v.visited_at', $fecha)
            ->where('v.is_opportunity', 0)
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
                'u.username as vendedor',
            ])
            ->orderBy('v.visited_at', 'asc')
            ->get();

        // Cruzar nombres de clientes desde supervisor.pan_ruteo
        $clientIds = $visitas->pluck('client_id')->unique()->filter()->toArray();
        $clientesMap = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn('cliente_id', $clientIds)
            ->select('cliente_id', DB::raw("COALESCE(NULLIF(cliente, ''), cliente_norm) as nombre"))
            ->distinct()
            ->pluck('nombre', 'cliente_id');

        $visitasFormateadas = $visitas->values()->map(function ($vis, $i) use ($clientesMap) {
            return [
                'id' => $vis->id,
                'orden' => $i + 1,
                'cliente_id' => $vis->client_id,
                'cliente' => $clientesMap[$vis->client_id] ?? ('Cliente #' . $vis->client_id),
                'status' => $vis->status,
                'comments' => $vis->comments,
                'hora' => date('H:i', strtotime($vis->visited_at)),
                'fecha_hora' => $vis->visited_at,
                'visita_lat' => (float) $vis->visita_lat,
                'visita_lng' => (float) $vis->visita_lng,
                'photo_url' => Storage::url($vis->photo_path),
            ];
        });

        // 3. Segmentación de polilínea por estado de telemetría (Mock vs Parada vs Movimiento)
        $puntosFormateados = [];
        $tramos = [];
        $alertasMock = 0;
        $totalLocations = count($locations);

        for ($i = 0; $i < $totalLocations; $i++) {
            $loc = $locations[$i];
            $lat = (float) $loc->latitude;
            $lng = (float) $loc->longitude;
            $isMock = (bool) $loc->is_mock;
            $isMoving = (bool) $loc->is_moving;
            $speed = $loc->speed !== null ? round((float) $loc->speed, 1) : 0.0;

            if ($isMock) {
                $alertasMock++;
            }

            $puntosFormateados[] = [
                'id' => $loc->id,
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'battery' => $loc->battery_level,
                'is_mock' => $isMock,
                'is_moving' => $isMoving,
                'step_count' => $loc->step_count,
                'hora' => date('H:i:s', strtotime($loc->recorded_at)),
            ];

            // Trazado de segmento al siguiente punto
            if ($i < $totalLocations - 1) {
                $next = $locations[$i + 1];

                // Color del segmento según telemetría
                $colorSegmento = '#0071E3'; // Movimiento regular
                if ($isMock || $next->is_mock) {
                    $colorSegmento = '#FF3B30'; // GPS Simulado / Mock
                } elseif (!$isMoving && $speed <= 1.5) {
                    $colorSegmento = '#FF9500'; // Detenido / Parada
                }

                $tramos[] = [
                    'from' => [$lat, $lng],
                    'to' => [(float) $next->latitude, (float) $next->longitude],
                    'color' => $colorSegmento,
                    'is_mock' => $isMock,
                ];
            }
        }

        // KPIs del último punto conocido
        $ultimoPunto = $locations->last();

        return [
            'puntos' => $puntosFormateados,
            'visitas' => $visitasFormateadas->toArray(),
            'tramos' => $tramos,
            'kpis' => [
                'total_puntos' => count($puntosFormateados),
                'total_visitas' => count($visitasFormateadas),
                'bateria_actual' => $ultimoPunto ? $ultimoPunto->battery_level : null,
                'velocidad_actual' => $ultimoPunto && $ultimoPunto->speed !== null ? round((float) $ultimoPunto->speed, 1) : null,
                'pasos_actual' => $ultimoPunto ? (int) $ultimoPunto->step_count : 0,
                'alertas_mock' => $alertasMock,
                'ultimo_reporte' => $ultimoPunto ? date('H:i:s', strtotime($ultimoPunto->recorded_at)) : null,
                'en_movimiento' => $ultimoPunto ? (bool) $ultimoPunto->is_moving : false,
            ],
        ];
    }
}

