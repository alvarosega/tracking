<?php

namespace App\Console\Commands;

use App\Models\UserWorkday;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseExpiredWorkdays extends Command
{
    protected $signature = 'workday:close-expired';
    protected $description = 'Cierra automáticamente todas las jornadas laborales abiertas fuera del horario legal';

    public function handle(WorkdayService $workdayService): int
    {
        $now = Carbon::now(WorkdayService::TIMEZONE);

        // Si estamos en horario laboral válido, no hay nada que forzar
        if ($workdayService->isWithinLegalSchedule($now)) {
            $this->info('Dentro de horario laboral legal. Sin cierres forzados.');
            return Command::SUCCESS;
        }

        $today = $now->toDateString();
        $limitTime = $workdayService->getLimitTimeForDate($now) ?? $now;

        $affected = UserWorkday::where('work_date', $today)
            ->where('status', 'OPEN')
            ->update([
                'status'       => 'CLOSED_TIMEOUT',
                'ended_at'     => $limitTime,
                'close_reason' => 'Cierre automático programado: límite de jornada legal alcanzado',
                'updated_at'   => $now,
            ]);

        $this->info("Jornadas vencidas cerradas automáticamente: {$affected}");
        return Command::SUCCESS;
    }
}