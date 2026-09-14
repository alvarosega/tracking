<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visita;
use App\Models\PlanRuteo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorIndexController extends Controller
{
    public function index(): Response
    {
        $diasMap = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];
        $today = Carbon::now('America/La_Paz');
        $diaActual = $diasMap[$today->dayOfWeekIso];
        $todayDate = $today->format('Y-m-d');

        // Vendedores registrados
        $vendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->where('is_active', true)
            ->get();

        $vendedorIds = $vendedores->pluck('id');

        // Total clientes programados hoy para las rutas de estos vendedores
        $vendedoresUsernames = $vendedores->pluck('username');
        $totalClientesHoy = PlanRuteo::whereIn('route', $vendedoresUsernames)
            ->where('status', 'Activo')
            ->where(function($q) use ($diaActual) {
                if ($diaActual === 'Miércoles') $q->whereIn('day', ['Miércoles', 'Miercoles']);
                elseif ($diaActual === 'Sábado') $q->whereIn('day', ['Sábado', 'Sabado']);
                else $q->where('day', $diaActual);
            })->count();

        // Visitas realizadas hoy
        $visitasHoy = Visita::whereDate('visited_at', $todayDate)->count();

        // Alertas de fraude hoy (is_mock = true)
        $alertasMockHoy = DB::table('locations')
            ->whereDate('recorded_at', $todayDate)
            ->where('is_mock', true)
            ->count();

        // Última ubicación de cada vendedor hoy
        $latestLocations = DB::table('locations as l1')
            ->join(DB::raw('(SELECT user_id, MAX(recorded_at) as max_rec FROM locations WHERE DATE(recorded_at) = "'.$todayDate.'" GROUP BY user_id) as l2'), function($join) {
                $join->on('l1.user_id', '=', 'l2.user_id')
                     ->on('l1.recorded_at', '=', 'l2.max_rec');
            })
            ->join('users', 'l1.user_id', '=', 'users.id')
            ->select([
                'l1.user_id',
                'users.username as vendedor_ruta',
                'l1.latitude',
                'l1.longitude',
                'l1.battery_level',
                'l1.is_moving',
                'l1.is_mock',
                'l1.accuracy',
                'l1.recorded_at'
            ])
            ->get();

        $vendedoresActivosHoy = $latestLocations->count();

        return Inertia::render('Supervisor/Index', [
            'kpis' => [
                'vendedores_activos' => $vendedoresActivosHoy,
                'vendedores_total' => $vendedores->count(),
                'visitas_hoy' => $visitasHoy,
                'clientes_planificados' => $totalClientesHoy,
                'alertas_mock' => $alertasMockHoy,
                'dia_actual' => $diaActual,
                'fecha_actual' => $todayDate,
            ],
            'vendedores_en_vivo' => $latestLocations,
        ]);
    }
}