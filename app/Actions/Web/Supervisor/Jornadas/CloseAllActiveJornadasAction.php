<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Jornadas;

use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CloseAllActiveJornadasAction
{
    public function __construct(
        private readonly WorkdayService $workdayService
    ) {}

    /**
     * Cierra todas las jornadas abiertas activas en la fecha de hoy.
     *
     * @return int Número de jornadas cerradas
     */
    public function execute(?int $supervisorId, ?string $reason = null): int
    {
        $today = Carbon::now(WorkdayService::TIMEZONE)->format('Y-m-d');
        $closeReason = $reason ?: 'Cierre remoto masivo ejecutado por el supervisor';

        $activeWorkdays = DB::table('user_workdays')
            ->where('work_date', $today)
            ->where('status', 'OPEN')
            ->pluck('user_id');

        $closedCount = 0;
        foreach ($activeWorkdays as $userId) {
            $workday = $this->workdayService->closeWorkdayBySupervisor(
                (int) $userId,
                $supervisorId,
                $closeReason
            );

            if ($workday) {
                $closedCount++;
            }
        }

        return $closedCount;
    }
}

