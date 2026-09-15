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
        $selectedAuditoria = $request->input('auditoria', 'TODAS'); // TODAS, DENTRO, FUERA, OPORTUNIDAD, NO_VISITADOS

        // Determinar día de la semana de la fecha consultada
        $dateCarbon = Carbon::parse($selectedDate);
        $diaSemana = $diasMap[$dateCarbon->dayOfWeekIso] ?? 'Lunes';

        // 1. Clientes Teóricos del Plan de Ruteo
        $planQuery = PlanRuteo::query()->select([
            'client_id',
            'client_name',
            'latitude',
            'longitude',
            'address',
            'route',
            'day'
        ]);

        if ($selectedRoute) {
            $planQuery->where('route', $selectedRoute);
        }

        // Normalización de tildes para días
        $planQuery->where(function ($q) use ($diaSemana) {
            if ($diaSemana === 'Miércoles') $q->whereIn('day', ['Miércoles', 'Miercoles']);
            elseif ($diaSemana === 'Sábado') $q->whereIn('day', ['Sábado', 'Sabado']);
            else $q->where('day', $diaSemana);
        });

        $clientesPlan = $planQuery->get();
        $totalPlan = $clientesPlan->count();

        // 2. Visitas Reales Registradas
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
            ->orderBy('v.visited_at', 'desc');

        if ($selectedRoute) {
            $visitasQuery->where('v.route', $selectedRoute);
        }

        $visitedClientIds = [];

        $visitas = $visitasQuery->get()->map(function ($row) use (&$visitedClientIds) {
            $distanciaMetros = null;

            if (!$row->is_opportunity && $row->official_lat && $row->official_lon) {
                $distanciaMetros = $this->calculateHaversine(
                    (float)$row->visita_lat, (float)$row->visita_lon,
                    (float)$row->official_lat, (float)$row->official_lon
                );
            }

            if ($row->client_id) {
                $visitedClientIds[] = $row->client_id;
            }

            $enRango = $distanciaMetros !== null ? ($distanciaMetros <= 50.0) : null;

            return [
                'id' => $row->id,
                'vendedor' => $row->vendedor,
                'route' => $row->route,
                'status' => $row->status,
                'is_opportunity' => (bool)$row->is_opportunity,
                'client_name' => $row->is_opportunity ? $row->opportunity_client_name : $row->official_client_name,
                'client_id' => $row->client_id,
                'address' => $row->official_address,
                'visita_lat' => (float)$row->visita_lat,
                'visita_lon' => (float)$row->visita_lon,
                'official_lat' => $row->official_lat ? (float)$row->official_lat : null,
                'official_lon' => $row->official_lon ? (float)$row->official_lon : null,
                'accuracy' => (float)$row->accuracy,
                'photo_url' => $row->photo_path ? Storage::url($row->photo_path) : null,
                'comments' => $row->comments,
                'visited_at' => $row->visited_at,
                'distancia_metros' => $distanciaMetros !== null ? round($distanciaMetros, 1) : null,
                'en_rango' => $enRango,
            ];
        });

        // 3. Determinar Clientes No Visitados
        $visitedClientIds = array_unique($visitedClientIds);
        $clientesNoVisitados = $clientesPlan->filter(fn($c) => !in_array($c->client_id, $visitedClientIds))->values()->map(function($c) {
            return [
                'client_id' => $c->client_id,
                'client_name' => $c->client_name,
                'address' => $c->address,
                'route' => $c->route,
                'latitude' => (float)$c->latitude,
                'longitude' => (float)$c->longitude,
            ];
        });

        // 4. Métricas de Resumen
        $totalVisitas = $visitas->count();
        $totalEnRango = $visitas->where('en_rango', true)->count();
        $totalFueraRango = $visitas->where('en_rango', false)->where('is_opportunity', false)->count();
        $totalOportunidades = $visitas->where('is_opportunity', true)->count();
        $totalPlanVisitados = count(array_intersect($visitedClientIds, $clientesPlan->pluck('client_id')->toArray()));

        // Filtrar según criterio de auditoría
        $visitasFiltradas = $visitas;
        if ($selectedAuditoria === 'FUERA') {
            $visitasFiltradas = $visitas->filter(fn($v) => !$v['is_opportunity'] && $v['en_rango'] === false)->values();
        } elseif ($selectedAuditoria === 'DENTRO') {
            $visitasFiltradas = $visitas->filter(fn($v) => !$v['is_opportunity'] && $v['en_rango'] === true)->values();
        } elseif ($selectedAuditoria === 'OPORTUNIDAD') {
            $visitasFiltradas = $visitas->filter(fn($v) => $v['is_opportunity'])->values();
        }

        return Inertia::render('Supervisor/Visitas', [
            'vendedores' => $vendedores,
            'selected_date' => $selectedDate,
            'selected_route' => $selectedRoute,
            'selected_auditoria' => $selectedAuditoria,
            'visitas' => $visitasFiltradas,
            'no_visitados' => $clientesNoVisitados,
            'metrics' => [
                'total_plan' => $totalPlan,
                'total_visitados_plan' => $totalPlanVisitados,
                'cobertura_pct' => $totalPlan > 0 ? round(($totalPlanVisitados / $totalPlan) * 100, 1) : 0,
                'total_visitas' => $totalVisitas,
                'en_rango' => $totalEnRango,
                'fuera_rango' => $totalFueraRango,
                'oportunidades' => $totalOportunidades,
                'efectividad_geocerca_pct' => ($totalEnRango + $totalFueraRango) > 0 
                    ? round(($totalEnRango / ($totalEnRango + $totalFueraRango)) * 100, 1) 
                    : 0,
            ]
        ]);
    }

    private function calculateHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // metros
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}