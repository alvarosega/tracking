<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlanRuteo;
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

        // 1. Clientes Teóricos del Plan de Ruteo para ese día
        $planQuery = PlanRuteo::query()->select([
            'client_id',
            'client_name',
            'latitude',
            'longitude',
            'address',
            'reference',
            'route',
            'day'
        ]);

        if ($selectedRoute) {
            $planQuery->where('route', $selectedRoute);
        }

        $planQuery->where(function ($q) use ($diaSemana) {
            if ($diaSemana === 'Miércoles') $q->whereIn('day', ['Miércoles', 'Miercoles']);
            elseif ($diaSemana === 'Sábado') $q->whereIn('day', ['Sábado', 'Sabado']);
            else $q->where('day', $diaSemana);
        });

        $clientesPlan = $planQuery->get()->keyBy('client_id');

        // 2. Visitas Reales registradas en esa fecha
        $visitasQuery = DB::table('visitas as v')
            ->leftJoin('plan_ruteo as p', 'v.client_id', '=', 'p.client_id')
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
                'p.client_name as official_client_name',
                'p.latitude as official_lat',
                'p.longitude as official_lon',
                'p.address as official_address',
            ])
            ->orderBy('v.visited_at', 'asc');

        if ($selectedRoute) {
            $visitasQuery->where('v.route', $selectedRoute);
        }

        $visitasReales = $visitasQuery->get();
        $visitadosClientIds = [];

        // 3. Procesar las visitas ejecutadas
        $tablaConciliacion = [];

        foreach ($visitasReales as $v) {
            $distanciaMetros = null;

            if (!$v->is_opportunity && $v->official_lat && $v->official_lon) {
                $distanciaMetros = round($this->calculateHaversine(
                    (float)$v->visita_lat, (float)$v->visita_lon,
                    (float)$v->official_lat, (float)$v->official_lon
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
                'client_name' => $v->is_opportunity ? $v->opportunity_client_name : $v->official_client_name,
                'route' => $v->route,
                'vendedor' => $v->vendedor,
                'address' => $v->official_address,
                'visitado' => true,
                'is_opportunity' => (bool)$v->is_opportunity,
                'visited_at' => $v->visited_at,
                'visita_lat' => (float)$v->visita_lat,
                'visita_lon' => (float)$v->visita_lon,
                'official_lat' => $v->official_lat ? (float)$v->official_lat : null,
                'official_lon' => $v->official_lon ? (float)$v->official_lon : null,
                'accuracy' => (float)$v->accuracy,
                'photo_url' => $v->photo_path ? Storage::url($v->photo_path) : null,
                'comments' => $v->comments,
                'distancia_metros' => $distanciaMetros,
                'en_rango' => $enRango,
                'tipo_auditoria' => $tipoAuditoria,
            ];
        }

        // 4. Incorporar los clientes del Plan que NO fueron visitados
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

        // Métricas
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
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}