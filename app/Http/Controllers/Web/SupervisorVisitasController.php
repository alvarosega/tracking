<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visita;
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
        $today = Carbon::now('America/La_Paz')->format('Y-m-d');
        $vendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))->pluck('username');

        $selectedDate = $request->input('date', $today);
        $selectedRoute = $request->input('route');
        $selectedAuditoria = $request->input('auditoria', 'TODAS'); // TODAS, DENTRO, FUERA, OPORTUNIDAD

        $query = DB::table('visitas as v')
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
            $query->where('v.route', $selectedRoute);
        }

        $visitas = $query->get()->map(function ($row) {
            $distanciaMetros = null;

            // Fórmula Haversine server-side si tiene cliente oficial asignado
            if (!$row->is_opportunity && $row->official_lat && $row->official_lon) {
                $distanciaMetros = $this->calculateHaversine(
                    (float)$row->visita_lat, (float)$row->visita_lon,
                    (float)$row->official_lat, (float)$row->official_lon
                );
            }

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
                'accuracy' => (float)$row->accuracy,
                'photo_url' => $row->photo_path ? Storage::url($row->photo_path) : null,
                'comments' => $row->comments,
                'visited_at' => $row->visited_at,
                'distancia_metros' => $distanciaMetros !== null ? round($distanciaMetros, 1) : null,
                'en_rango' => $distanciaMetros !== null ? ($distanciaMetros <= 35.0) : null,
            ];
        });

        // Filtro en memoria para auditoría de geocerca
        if ($selectedAuditoria === 'FUERA') {
            $visitas = $visitas->filter(fn($v) => !$v['is_opportunity'] && $v['distancia_metros'] > 35.0)->values();
        } elseif ($selectedAuditoria === 'DENTRO') {
            $visitas = $visitas->filter(fn($v) => !$v['is_opportunity'] && $v['distancia_metros'] <= 35.0)->values();
        } elseif ($selectedAuditoria === 'OPORTUNIDAD') {
            $visitas = $visitas->filter(fn($v) => $v['is_opportunity'])->values();
        }

        return Inertia::render('Supervisor/Visitas', [
            'vendedores' => $vendedores,
            'selected_date' => $selectedDate,
            'selected_route' => $selectedRoute,
            'selected_auditoria' => $selectedAuditoria,
            'visitas' => $visitas,
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