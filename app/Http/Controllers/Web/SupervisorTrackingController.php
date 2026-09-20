<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorTrackingController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::now('America/La_Paz')->format('Y-m-d');
        $selectedDate = $request->input('date', $today);
        $selectedUserId = $request->input('user_id');

        $vendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->select('id', 'username')
            ->get();

        // Consulta de jornadas del día seleccionado para cada vendedor
        $workdays = DB::table('user_workdays')
            ->where('work_date', $selectedDate)
            ->select([
                'id',
                'user_id',
                'work_date',
                'started_at',
                'ended_at',
                'status',
                'close_reason',
                'closed_by'
            ])
            ->get()
            ->keyBy('user_id');

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
            'vendedores'       => $vendedores,
            'selected_date'    => $selectedDate,
            'selected_user_id' => $selectedUserId ? (int)$selectedUserId : null,
            'puntos'           => $puntos,
            'workdays'         => $workdays,
        ]);
    }

    /**
     * Cierre forzado de jornada por parte del supervisor desde la interfaz Web.
     */
    public function closeWorkday(Request $request, int $userId, WorkdayService $workdayService): RedirectResponse
    {
        $supervisor = $request->user();

        $workdayService->closeWorkdayBySupervisor(
            $userId,
            $supervisor->id,
            'Finalizado remotamente por el supervisor desde el panel de telemetría'
        );

        return back()->with('success', 'Jornada finalizada correctamente.');
    }
}