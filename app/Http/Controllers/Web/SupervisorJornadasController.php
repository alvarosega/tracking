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

class SupervisorJornadasController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::now(WorkdayService::TIMEZONE)->format('Y-m-d');
        $selectedDate = $request->input('date', $today);
        $selectedStatus = $request->input('status', 'TODOS'); // TODOS, OPEN, CLOSED

        $vendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->select('id', 'username')
            ->orderBy('username')
            ->get();

        $workdays = DB::table('user_workdays as w')
            ->join('users as u', 'w.user_id', '=', 'u.id')
            ->leftJoin('users as sup', 'w.closed_by', '=', 'sup.id')
            ->where('w.work_date', $selectedDate)
            ->select([
                'w.id',
                'w.user_id',
                'u.username as vendedor_ruta',
                'w.work_date',
                'w.started_at',
                'w.ended_at',
                'w.status',
                'w.close_reason',
                'sup.username as supervisor_nombre'
            ])
            ->get()
            ->keyBy('user_id');

        // Construir tabla consolidada de todos los vendedores para esa fecha
        $consolidado = $vendedores->map(function ($v) use ($workdays) {
            $w = $workdays->get($v->id);
            return [
                'user_id'           => $v->id,
                'vendedor_ruta'     => $v->username,
                'workday_id'        => $w?->id,
                'status'            => $w?->status ?? 'NOT_STARTED',
                'started_at'        => $w?->started_at,
                'ended_at'          => $w?->ended_at,
                'close_reason'      => $w?->close_reason,
                'supervisor_nombre' => $w?->supervisor_nombre,
            ];
        });

        if ($selectedStatus === 'OPEN') {
            $consolidado = $consolidado->where('status', 'OPEN')->values();
        } elseif ($selectedStatus === 'CLOSED') {
            $consolidado = $consolidado->whereIn('status', ['CLOSED_SELLER', 'CLOSED_SUPERVISOR', 'CLOSED_TIMEOUT'])->values();
        }

        return Inertia::render('Supervisor/Jornadas', [
            'vendedores_jornadas' => $consolidado->values(),
            'selected_date'       => $selectedDate,
            'selected_status'     => $selectedStatus,
            'total_abiertas'      => $workdays->where('status', 'OPEN')->count(),
            'total_cerradas'      => $workdays->whereIn('status', ['CLOSED_SELLER', 'CLOSED_SUPERVISOR', 'CLOSED_TIMEOUT'])->count(),
            'total_sin_iniciar'   => $vendedores->count() - $workdays->count(),
        ]);
    }

    public function closeSingle(Request $request, int $userId, WorkdayService $workdayService): RedirectResponse
    {
        $supervisor = $request->user();
        $workdayService->closeWorkdayBySupervisor(
            $userId,
            $supervisor->id,
            'Cierre remoto individual ejecutado desde el panel de jornadas'
        );

        return back()->with('success', 'Jornada finalizada correctamente.');
    }

    public function closeAllActive(Request $request, WorkdayService $workdayService): RedirectResponse
    {
        $supervisor = $request->user();
        $today = Carbon::now(WorkdayService::TIMEZONE)->format('Y-m-d');

        $activeWorkdays = DB::table('user_workdays')
            ->where('work_date', $today)
            ->where('status', 'OPEN')
            ->pluck('user_id');

        foreach ($activeWorkdays as $userId) {
            $workdayService->closeWorkdayBySupervisor(
                $userId,
                $supervisor->id,
                'Cierre remoto masivo ejecutado por el supervisor'
            );
        }

        return back()->with('success', 'Todas las jornadas abiertas han sido finalizadas.');
    }
}