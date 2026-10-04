<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorTrackingController extends Controller
{
    /**
     * Catálogo de rutas vinculadas con su user_id en alva.users
     */
    private function getCatalogoRutasConUsuarios()
    {
        // 1. Catálogo desde supervisor.dim_rutas
        $rutas = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $usuarios = DB::table('users')
            ->select('id', 'username')
            ->get()
            ->keyBy('username');

        return $rutas->map(function ($r) use ($usuarios) {
            $user = $usuarios->get($r->ruta);
            return [
                'ruta' => $r->ruta,
                'canal' => $r->canal,
                'vendedor' => $r->vendedor,
                'user_id' => $user ? $user->id : null,
            ];
        });
    }

    /**
     * VISTA: Submódulo 1 - Patrones Históricos Semanales
     */
    public function historico(): Response
    {
        $catalogo = $this->getCatalogoRutasConUsuarios();
        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        // Fechas por defecto: Último mes
        $fechaFin = date('Y-m-d');
        $fechaInicio = date('Y-m-d', strtotime('-30 days'));

        return Inertia::render('Supervisor/Tracking/Historico', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'fecha_inicio_default' => $fechaInicio,
            'fecha_fin_default' => $fechaFin,
            'dias_disponibles' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
        ]);
    }

    /**
     * DATA: Submódulo 1 - Patrones Históricos Semanales
     */
    public function historicoData(Request $request): JsonResponse
    {
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $diaSemana = $request->input('dia', 'Lunes'); // Lunes, Martes, etc.
        $rutaSeleccionada = $request->input('ruta');

        if (!$rutaSeleccionada || !$fechaInicio || !$fechaFin) {
            return response()->json([
                'trayectorias_fechas' => [],
                'kpis' => [
                    'total_fechas' => 0,
                    'total_puntos' => 0,
                    'total_visitas' => 0,
                    'alertas_mock' => 0,
                ],
            ]);
        }

        // Obtener user_id asociado a la ruta
        $user = DB::table('users')->where('username', $rutaSeleccionada)->first();
        if (!$user) {
            return response()->json([
                'trayectorias_fechas' => [],
                'kpis' => ['total_fechas' => 0, 'total_puntos' => 0, 'total_visitas' => 0, 'alertas_mock' => 0],
            ]);
        }

        // Mapeo de día de semana en MySQL (1 = Domingo, 2 = Lunes, ..., 7 = Sábado)
        $diasMapMysql = [
            'Domingo' => 1,
            'Lunes' => 2,
            'Martes' => 3,
            'Miércoles' => 4,
            'Jueves' => 5,
            'Viernes' => 6,
            'Sábado' => 7,
        ];
        $mysqlDayOfWeek = $diasMapMysql[$diaSemana] ?? 2;

        // 1. Obtener puntos de telemetría para ese usuario, en ese rango de fechas y que coincidan con el día de la semana
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
            return response()->json([
                'trayectorias_fechas' => [],
                'kpis' => ['total_fechas' => 0, 'total_puntos' => 0, 'total_visitas' => 0, 'alertas_mock' => 0],
            ]);
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

        // Paleta distintiva para contrastar cada fecha en el mapa
        $paletaColores = ['#0071E3', '#34C759', '#FF9500', '#AF52DE', '#FF2D55', '#5856D6', '#00C7BE', '#A2845E'];

        $trayectoriasFechas = [];
        $totalAlertasMock = 0;
        $totalVisitasConteo = 0;
        $colorIdx = 0;

        foreach ($locations->groupBy('fecha') as $fecha => $puntosFecha) {
            $color = $paletaColores[$colorIdx % count($paletaColores)];
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
                    'visita_lng' => (float) $visita_lng = $vis->visita_lng,
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
                'fecha_formato' => date('d/m/Y', strtotime($fecha)),
                'color' => $color,
                'total_puntos' => count($puntosFormateados),
                'total_visitas' => $visitasFecha->count(),
                'puntos' => $puntosFormateados,
                'visitas' => $visitasFecha,
            ];
        }

        return response()->json([
            'trayectorias_fechas' => $trayectoriasFechas,
            'kpis' => [
                'total_fechas' => count($trayectoriasFechas),
                'total_puntos' => $locations->count(),
                'total_visitas' => $totalVisitasConteo,
                'alertas_mock' => $totalAlertasMock,
            ],
        ]);
    }
    /**
     * VISTA: Submódulo 2 - Recorrido del Día
     */
    public function index(): Response
    {
        $catalogo = $this->getCatalogoRutasConUsuarios();
        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        return Inertia::render('Supervisor/Tracking/Index', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'fecha_default' => date('Y-m-d'),
        ]);
    }

    /**
     * DATA: Submódulo 2 - Recorrido del Día + Visitas del Vendedor
     */
    public function data(Request $request): JsonResponse
    {
        $fecha = $request->input('fecha', date('Y-m-d'));
        $ruta = $request->input('ruta');

        if (!$ruta) {
            return response()->json([
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
            ]);
        }

        // Obtener user_id asociado a la ruta
        $user = DB::table('users')->where('username', $ruta)->first();
        if (!$user) {
            return response()->json([
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
            ]);
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

        for ($i = 0; $i < count($locations); $i++) {
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
            if ($i < count($locations) - 1) {
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

        return response()->json([
            'puntos' => $puntosFormateados,
            'visitas' => $visitasFormateadas,
            'tramos' => $tramos,
            'kpis' => [
                'total_puntos' => count($puntosFormateados),
                'total_visitas' => count($visitasFormateadas),
                'bateria_actual' => $ultimoPunto ? $ultimoPunto->battery_level : null,
                'velocidad_actual' => $ultimoPunto && $ultimoPunto->speed !== null ? round((float) $ultimoPunto->speed, 1) : null,
                'pasos_actual' => $ultimoPunto ? $ultimoPunto->step_count : 0,
                'alertas_mock' => $alertasMock,
                'ultimo_reporte' => $ultimoPunto ? date('H:i:s', strtotime($ultimoPunto->recorded_at)) : null,
                'en_movimiento' => $ultimoPunto ? (bool) $ultimoPunto->is_moving : false,
            ],
        ]);
    }
}