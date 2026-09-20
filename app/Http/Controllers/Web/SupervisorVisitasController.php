<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorVisitasController extends Controller
{
    public function index(Request $request): Response
    {
        $diasMap = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];

        $today = Carbon::now('America/La_Paz')->format('Y-m-d');
        $vendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->pluck('username')
            ->sort()
            ->values();

        $selectedDate = $request->input('date', $today);
        $selectedRoute = $request->input('route');
        $selectedAuditoria = $request->input('auditoria', 'TODAS'); // TODAS, DENTRO, FUERA, NO_VISITADOS, OPORTUNIDAD

        $dateCarbon = Carbon::parse($selectedDate);
        $diaSemana = $diasMap[$dateCarbon->dayOfWeekIso] ?? 'Lunes';

        // 1. Clientes Teóricos del Plan de Ruteo en la BD supervisor
        $planQuery = DB::connection('supervisor')->table('pan_ruteo')
            ->where('estado', 'Activo')
            ->select([
                'cliente_id as client_id',
                DB::raw("COALESCE(NULLIF(cliente, ''), cliente_norm, 'Cliente Sin Nombre') as client_name"),
                'latitud as latitude',
                'longitud as longitude',
                'direccion as address',
                'referencia as reference',
                'ruta as route',
                'dia as day'
            ]);

        if ($selectedRoute) {
            $planQuery->where('ruta', $selectedRoute);
        }

        $planQuery->where(function ($q) use ($diaSemana) {
            if ($diaSemana === 'Miércoles') {
                $q->whereIn('dia', ['Miércoles', 'Miercoles'])
                  ->orWhereIn('dia_norm', ['Miércoles', 'Miercoles', 'MIERCOLES']);
            } elseif ($diaSemana === 'Sábado') {
                $q->whereIn('dia', ['Sábado', 'Sabado'])
                  ->orWhereIn('dia_norm', ['Sábado', 'Sabado', 'SABADO']);
            } else {
                $q->where('dia', $diaSemana)
                  ->orWhere('dia_norm', $diaSemana);
            }
        });

        $clientesPlan = $planQuery->get()->keyBy('client_id');

        // 2. Visitas Reales registradas en esa fecha (BD principal)
        $visitasQuery = DB::table('visitas as v')
            ->join('users as u', 'v.user_id', '=', 'u.id')
            ->whereDate('v.visited_at', $selectedDate)
            ->select([
                'v.id',
                'v.user_id',
                'u.username as vendedor',
                'v.client_id',
                'v.route',
                'v.status',
                'v.is_opportunity',
                'v.opportunity_client_name',
                'v.latitude as visita_lat',
                'v.longitude as visita_lon',
                'v.accuracy',
                'v.photo_path',
                'v.comments',
                'v.visited_at',
            ])
            ->orderBy('v.visited_at', 'asc');

        if ($selectedRoute) {
            $visitasQuery->where('v.route', $selectedRoute);
        }

        $visitasReales = $visitasQuery->get();
        $visitadosClientIds = [];

        // 3. Procesar y conciliar visitas con las coordenadas teóricas de pan_ruteo
        $tablaConciliacion = [];

        foreach ($visitasReales as $v) {
            $distanciaMetros = null;
            $cpOficial = (!$v->is_opportunity && $v->client_id && isset($clientesPlan[$v->client_id]))
                ? $clientesPlan[$v->client_id]
                : null;

            $officialLat = $cpOficial ? (float)$cpOficial->latitude : null;
            $officialLon = $cpOficial ? (float)$cpOficial->longitude : null;
            $officialName = $cpOficial ? $cpOficial->client_name : null;
            $officialAddress = $cpOficial ? $cpOficial->address : null;

            if (!$v->is_opportunity && $officialLat && $officialLon) {
                $distanciaMetros = round($this->calculateHaversine(
                    (float)$v->visita_lat, (float)$v->visita_lon,
                    $officialLat, $officialLon
                ), 1);
            }

            if ($v->client_id) {
                $visitadosClientIds[] = $v->client_id;
            }

            $enRango = $distanciaMetros !== null ? ($distanciaMetros <= 50.0) : null;

            $tipoAuditoria = 'DENTRO';
            if ($v->is_opportunity) {
                $tipoAuditoria = 'OPORTUNIDAD';
            } elseif ($enRango === false) {
                $tipoAuditoria = 'FUERA';
            }

            $tablaConciliacion[] = [
                'id' => 'visita_' . $v->id,
                'visita_id' => $v->id,
                'client_id' => $v->client_id,
                'client_name' => $v->is_opportunity ? $v->opportunity_client_name : ($officialName ?: 'Cliente #' . $v->client_id),
                'route' => $v->route,
                'vendedor' => $v->vendedor,
                'address' => $officialAddress,
                'visitado' => true,
                'is_opportunity' => (bool)$v->is_opportunity,
                'visited_at' => $v->visited_at,
                'visita_lat' => (float)$v->visita_lat,
                'visita_lon' => (float)$v->visita_lon,
                'official_lat' => $officialLat,
                'official_lon' => $officialLon,
                'accuracy' => (float)$v->accuracy,
                'photo_url' => $v->photo_path ? Storage::url($v->photo_path) : null,
                'comments' => $v->comments,
                'distancia_metros' => $distanciaMetros,
                'en_rango' => $enRango,
                'tipo_auditoria' => $tipoAuditoria,
            ];
        }

        // 4. Incorporar clientes del Plan que NO fueron visitados
        foreach ($clientesPlan as $clientId => $cp) {
            if (!in_array($clientId, $visitadosClientIds)) {
                $tablaConciliacion[] = [
                    'id' => 'plan_' . $cp->client_id,
                    'visita_id' => null,
                    'client_id' => $cp->client_id,
                    'client_name' => $cp->client_name,
                    'route' => $cp->route,
                    'vendedor' => $cp->route,
                    'address' => $cp->address,
                    'visitado' => false,
                    'is_opportunity' => false,
                    'visited_at' => null,
                    'visita_lat' => null,
                    'visita_lon' => null,
                    'official_lat' => (float)$cp->latitude,
                    'official_lon' => (float)$cp->longitude,
                    'accuracy' => null,
                    'photo_url' => null,
                    'comments' => null,
                    'distancia_metros' => null,
                    'en_rango' => null,
                    'tipo_auditoria' => 'NO_VISITADO',
                ];
            }
        }

        // 5. Métricas
        $totalPlan = $clientesPlan->count();
        $totalVisitadosPlan = count(array_unique(array_intersect($visitadosClientIds, $clientesPlan->keys()->toArray())));
        $totalEnRango = collect($tablaConciliacion)->where('tipo_auditoria', 'DENTRO')->count();
        $totalFueraRango = collect($tablaConciliacion)->where('tipo_auditoria', 'FUERA')->count();
        $totalOportunidades = collect($tablaConciliacion)->where('tipo_auditoria', 'OPORTUNIDAD')->count();
        $totalNoVisitados = collect($tablaConciliacion)->where('tipo_auditoria', 'NO_VISITADO')->count();

        // Filtro de auditoría
        $itemsFiltrados = collect($tablaConciliacion);
        if ($selectedAuditoria !== 'TODAS') {
            $itemsFiltrados = $itemsFiltrados->where('tipo_auditoria', $selectedAuditoria)->values();
        }

        return Inertia::render('Supervisor/Visitas', [
            'vendedores' => $vendedores,
            'selected_date' => $selectedDate,
            'selected_route' => $selectedRoute,
            'selected_auditoria' => $selectedAuditoria,
            'items' => $itemsFiltrados->values(),
            'metrics' => [
                'total_plan' => $totalPlan,
                'total_visitados_plan' => $totalVisitadosPlan,
                'cobertura_pct' => $totalPlan > 0 ? round(($totalVisitadosPlan / $totalPlan) * 100, 1) : 0,
                'en_rango' => $totalEnRango,
                'fuera_rango' => $totalFueraRango,
                'oportunidades' => $totalOportunidades,
                'no_visitados' => $totalNoVisitados,
            ]
        ]);
    }

    private function calculateHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - a));
        return $earthRadius * $c;
    }
}