<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Jornadas;

use App\Models\User;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetJornadasDataAction
{
    /**
     * Obtiene el consolidado de vendedores y estado de sus jornadas de trabajo para una fecha dada.
     *
     * @param string|null $date
     * @param string $status
     * @return array{
     *     vendedores_jornadas: Collection,
     *     selected_date: string,
     *     selected_status: string,
     *     total_abiertas: int,
     *     total_cerradas: int,
     *     total_sin_iniciar: int
     * }
     */
    public function execute(?string $date = null, string $status = 'TODOS'): array
    {
        $selectedDate = $date ?: Carbon::now(WorkdayService::TIMEZONE)->format('Y-m-d');

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

        // Construir tabla consolidada de todos los vendedores
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

        $totalAbiertas = $workdays->where('status', 'OPEN')->count();
        $totalCerradas = $workdays->whereIn('status', ['CLOSED_SELLER', 'CLOSED_SUPERVISOR', 'CLOSED_TIMEOUT'])->count();
        $totalSinIniciar = max(0, $vendedores->count() - $workdays->count());

        if ($status === 'OPEN') {
            $consolidado = $consolidado->where('status', 'OPEN')->values();
        } elseif ($status === 'CLOSED') {
            $consolidado = $consolidado->whereIn('status', ['CLOSED_SELLER', 'CLOSED_SUPERVISOR', 'CLOSED_TIMEOUT'])->values();
        }

        return [
            'vendedores_jornadas' => $consolidado->values(),
            'selected_date'       => $selectedDate,
            'selected_status'     => $status,
            'total_abiertas'      => $totalAbiertas,
            'total_cerradas'      => $totalCerradas,
            'total_sin_iniciar'   => $totalSinIniciar,
        ];
    }
}

