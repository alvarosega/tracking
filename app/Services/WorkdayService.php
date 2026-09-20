<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserWorkday;
use Carbon\Carbon;

class WorkdayService
{
    public const TIMEZONE = 'America/La_Paz';

    /**
     * Evalúa si una fecha/hora dada está dentro de la ventana de trabajo legal permitida.
     */
    public function isWithinLegalSchedule(?Carbon $dateTime = null): bool
    {
        $now = $dateTime ? $dateTime->copy()->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);
        $dayOfWeek = $now->dayOfWeekIso; // 1: Lunes ... 6: Sábado, 7: Domingo
        $timeStr = $now->format('H:i:s');

        // Domingo no laborable
        if ($dayOfWeek === 7) {
            return false;
        }

        // Sábado: 08:00 a 12:00
        if ($dayOfWeek === 6) {
            return ($timeStr >= '08:00:00' && $timeStr <= '12:00:00');
        }

        // Lunes a Viernes: 08:00 a 17:00
        return ($timeStr >= '08:00:00' && $timeStr <= '17:00:00');
    }

    /**
     * Retorna la hora límite de finalización para la fecha actual.
     */
    public function getLimitTimeForDate(Carbon $date): ?Carbon
    {
        $dayOfWeek = $date->dayOfWeekIso;

        if ($dayOfWeek === 7) {
            return null;
        }

        if ($dayOfWeek === 6) {
            return $date->copy()->setTimezone(self::TIMEZONE)->setTime(12, 0, 0);
        }

        return $date->copy()->setTimezone(self::TIMEZONE)->setTime(17, 0, 0);
    }

    /**
     * Obtiene la jornada del día para el usuario. Si está vencida por hora, la cierra automáticamente.
     */
    public function getActiveWorkday(int $userId): ?UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $todayStr = $now->toDateString();

        $workday = UserWorkday::where('user_id', $userId)
            ->where('work_date', $todayStr)
            ->first();

        if (!$workday) {
            return null;
        }

        // Si figuraba abierta pero ya pasó el horario permitido, se cierra por TIMEOUT
        if ($workday->status === 'OPEN' && !$this->isWithinLegalSchedule($now)) {
            $limitTime = $this->getLimitTimeForDate($now) ?? $now;
            $workday->update([
                'status'       => 'CLOSED_TIMEOUT',
                'ended_at'     => $limitTime,
                'close_reason' => 'Cierre automático por límite de horario laboral legal',
            ]);
        }

        return $workday;
    }

    /**
     * Inicia una jornada para el usuario si está en horario permitido.
     */
    public function startWorkday(User $user): UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $todayStr = $now->toDateString();

        if (!$this->isWithinLegalSchedule($now)) {
            throw new \DomainException('No se puede iniciar jornada fuera del horario legal permitido.');
        }

        return UserWorkday::updateOrCreate(
            [
                'user_id'   => $user->id,
                'work_date' => $todayStr,
            ],
            [
                'started_at'   => $now,
                'ended_at'     => null,
                'status'       => 'OPEN',
                'closed_by'    => null,
                'close_reason' => null,
            ]
        );
    }

    /**
     * Cierra la jornada iniciada por el propio vendedor.
     */
    public function closeWorkdayBySeller(int $userId): ?UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $workday = $this->getActiveWorkday($userId);

        if ($workday && $workday->status === 'OPEN') {
            $workday->update([
                'status'       => 'CLOSED_SELLER',
                'ended_at'     => $now,
                'close_reason' => 'Finalizado voluntariamente por el vendedor',
            ]);
        }

        return $workday;
    }

    /**
     * Cierre remoto ejecutado por el supervisor.
     */
    public function closeWorkdayBySupervisor(int $targetUserId, int $supervisorId, ?string $reason = null): ?UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $todayStr = $now->toDateString();

        $workday = UserWorkday::where('user_id', $targetUserId)
            ->where('work_date', $todayStr)
            ->where('status', 'OPEN')
            ->first();

        if ($workday) {
            $workday->update([
                'status'       => 'CLOSED_SUPERVISOR',
                'ended_at'     => $now,
                'closed_by'    => $supervisorId,
                'close_reason' => $reason ?: 'Finalizado remotamente por el supervisor',
            ]);
        }

        return $workday;
    }
}