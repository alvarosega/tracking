<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorVisitasController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedDate = $request->input('date', now('America/La_Paz')->format('Y-m-d'));
        $selectedUserId = $request->input('user_id');

        $sellers = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->where('is_active', true)
            ->select('id', 'username')
            ->orderBy('username')
            ->get();

        if (!$selectedUserId && $sellers->isNotEmpty()) {
            $selectedUserId = $sellers->first()->id;
        }

        $visitas = [];

        if ($selectedUserId) {
            $records = DB::table('visitas as v')
                ->leftJoin('reference_clients as rc', 'v.client_id', '=', 'rc.id_cliente')
                ->leftJoin('plan_ruteo as pr', 'v.client_id', '=', 'pr.client_id')
                ->where('v.user_id', $selectedUserId)
                ->whereDate('v.visited_at', $selectedDate)
                ->select([
                    'v.id',
                    'v.client_id',
                    'v.route',
                    'v.status',
                    'v.is_opportunity',
                    'v.opportunity_client_name',
                    'v.latitude as actual_lat',
                    'v.longitude as actual_lng',
                    'v.accuracy',
                    'v.photo_path',
                    'v.comments',
                    'v.visited_at',
                    'v.created_at',
                    DB::raw('COALESCE(rc.client_name, pr.client_name, v.opportunity_client_name, "Cliente Sin Nombre") as resolved_client_name'),
                    DB::raw('COALESCE(rc.latitude, pr.latitude) as target_lat'),
                    DB::raw('COALESCE(rc.longitude, pr.longitude) as target_lng'),
                ])
                ->orderBy('v.visited_at', 'asc')
                ->get();

            $visitas = $records->map(function ($v) {
                $distanceMeters = null;
                if ($v->target_lat && $v->target_lng) {
                    $distanceMeters = $this->haversineDistance(
                        $v->actual_lat,
                        $v->actual_lng,
                        $v->target_lat,
                        $v->target_lng
                    );
                }

                return [
                    'id' => $v->id,
                    'client_id' => $v->client_id,
                    'client_name' => $v->resolved_client_name,
                    'route' => $v->route,
                    'status' => $v->status,
                    'is_opportunity' => (bool) $v->is_opportunity,
                    'actual_lat' => (float) $v->actual_lat,
                    'actual_lng' => (float) $v->actual_lng,
                    'target_lat' => $v->target_lat ? (float) $v->target_lat : null,
                    'target_lng' => $v->target_lng ? (float) $v->target_lng : null,
                    'accuracy' => (float) $v->accuracy,
                    'distance_meters' => $distanceMeters !== null ? round($distanceMeters, 1) : null,
                    'photo_url' => $v->photo_path ? asset('storage/' . $v->photo_path) : null,
                    'comments' => $v->comments,
                    'visited_at' => $v->visited_at,
                    'created_at' => $v->created_at,
                ];
            });
        }

        return Inertia::render('Supervisor/Visitas/Index', [
            'sellers' => $sellers,
            'filters' => [
                'date' => $selectedDate,
                'user_id' => (int) $selectedUserId,
            ],
            'visitas' => $visitas,
        ]);
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}