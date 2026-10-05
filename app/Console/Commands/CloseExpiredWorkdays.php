<?php

namespace App\Console\Commands;

use App\Models\UserWorkday;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseExpiredWorkdays extends Command
{
    protected $signature = 'workday:close-expired';
    protected $description = 'Cierra automáticamente las jornadas laborales que hayan superado su horario límite según su ruta';

    public function handle(WorkdayService $workdayService): int
    {
        $now = Carbon::now(WorkdayService::TIMEZONE);
        $today = $now->toDateString();

        // 1. Obtener todas las jornadas abiertas para la fecha de hoy con la relación del usuario
        $openWorkdays = UserWorkday::with('user:id,username')
            ->where('work_date', $today)
            ->where('status', 'OPEN')
            ->get();

        if ($openWorkdays->isEmpty()) {
            $this->info('No existen jornadas abiertas pendientes de validación.');
            return Command::SUCCESS;
        }

        $closedCount = 0;

        foreach ($openWorkdays as $workday) {
            $user = $workday->user;
            if (!$user) {
                continue;
            }

            $route = trim($user->username);

            // Validar si la jornada de esta ruta ya venció
            if (!$workdayService->isWithinLegalSchedule($route, $now)) {
                $limitTime = $workdayService->getLimitTimeForDate($route, $now) ?? $now;

                $workday->update([
                    'status'       => 'CLOSED_TIMEOUT',
                    'ended_at'     => $limitTime,
                    'close_reason' => 'Cierre automático programado: límite de jornada alcanzado para la ruta',
                    'updated_at'   => $now,
                ]);

                $closedCount++;
                $this->line("Jornada cerrada para la ruta [{$route}]. Hora límite: {$limitTime->format('H:i:s')}");
            }
        }

        $this->info("Total de jornadas vencidas cerradas: {$closedCount}");
        return Command::SUCCESS;
    }
}