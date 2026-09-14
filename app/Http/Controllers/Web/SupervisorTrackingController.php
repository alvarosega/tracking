<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorTrackingController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::now('America/La_Paz')->format('Y-m-d');
        $vendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->select('id', 'username')
            ->get();

        $selectedDate = $request->input('date', $today);
        $selectedUserId = $request->input('user_id'); // null implica Vista General Flota

        $locationsQuery = DB::table('locations as l')
            ->join('users as u', 'l.user_id', '=', 'u.id')
            ->whereDate('l.recorded_at', $selectedDate)
            ->select([
                'l.id',
                'l.user_id',
                'u.username as vendedor_ruta',
                'l.latitude',
                'l.longitude',
                'l.accuracy',
                'l.speed',
                'l.battery_level',
                'l.is_mock',
                'l.is_moving',
                'l.motion_variance',
                'l.recorded_at'
            ])
            ->orderBy('l.recorded_at', 'asc');

        if ($selectedUserId) {
            $locationsQuery->where('l.user_id', $selectedUserId);
        }

        $puntos = $locationsQuery->get();

        return Inertia::render('Supervisor/Tracking', [
            'vendedores' => $vendedores,
            'selected_date' => $selectedDate,
            'selected_user_id' => $selectedUserId ? (int)$selectedUserId : null,
            'puntos' => $puntos,
        ]);
    }
}