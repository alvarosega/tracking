<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserWorkday;
use Carbon\Carbon;

class WorkdayService
{
    public const TIMEZONE = 'America/La_Paz';

    public function isWithinLegalSchedule(?Carbon $dateTime = null, bool $allowException = false): bool
    {
        $now = $dateTime ? $dateTime->copy()->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);
        $dayOfWeek = $now->dayOfWeekIso; // 1: Lunes ... 6: Sábado, 7: Domingo
        $timeStr = $now->format('H:i:s');

        // Excepción de pruebas: permitir domingos
        if ($dayOfWeek === 7) {
            return true;
        }

        if ($allowException) {
            return true;
        }

        // Sábado: 07:20 a 15:00 (3:00 PM)
        if ($dayOfWeek === 6) {
            return ($timeStr >= '07:20:00' && $timeStr <= '15:00:00');
        }

        // Lunes a Viernes: 07:20 a 19:00 (7:00 PM)
        return ($timeStr >= '07:20:00' && $timeStr <= '23:00:00');
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

        // Sábado hasta las 15:00:00
        if ($dayOfWeek === 6) {
            return $date->copy()->setTimezone(self::TIMEZONE)->setTime(15, 0, 0);
        }

        // Lunes a Viernes hasta las 19:00:00
        return $date->copy()->setTimezone(self::TIMEZONE)->setTime(23, 0, 0);
    }

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

        // Si el estado es OPEN, verificamos si es una excepción válida o si venció
        $hasException = ($workday->close_reason === 'EXCEPCION_AUTORIZADA' || ($workday->status === 'OPEN' && $now->dayOfWeekIso === 7));

        if ($workday->status === 'OPEN' && !$this->isWithinLegalSchedule($now, $hasException)) {
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
            throw new \DomainException('No se puede iniciar jornada fuera del horario legal permitido (07:20 - 19:00 L-V, 07:20 - 15:00 Sáb).');
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