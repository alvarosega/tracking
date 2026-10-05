<?php

namespace App\Services;

use App\Models\RouteSchedule;
use App\Models\User;
use App\Models\UserWorkday;
use Carbon\Carbon;

class WorkdayService
{
    public const TIMEZONE = 'America/La_Paz';

    /**
     * Obtiene el horario configurado para una ruta y fecha.
     */
    public function getScheduleForRoute(string $route, ?Carbon $dateTime = null): object
    {
        $now = $dateTime ? $dateTime->copy()->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);
        $dayOfWeek = $now->dayOfWeekIso;

        $dbSchedule = RouteSchedule::where('route', trim($route))
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($dbSchedule) {
            return (object) [
                'is_working_day' => (bool) $dbSchedule->is_working_day,
                'start_time'     => $dbSchedule->start_time,
                'end_time'       => $dbSchedule->end_time,
                'limit_time'     => $dbSchedule->is_working_day 
                    ? Carbon::parse($now->toDateString() . ' ' . $dbSchedule->end_time, self::TIMEZONE)
                    : null
            ];
        }

        // Fallback histórico si la ruta no tiene configuración registrada
        $isSaturday = ($dayOfWeek === 6);
        $isSunday = ($dayOfWeek === 7);

        return (object) [
            'is_working_day' => !$isSunday,
            'start_time'     => '07:20:00',
            'end_time'       => $isSaturday ? '15:00:00' : '19:00:00',
            'limit_time'     => $isSunday ? null : Carbon::parse($now->toDateString() . ' ' . ($isSaturday ? '15:00:00' : '19:00:00'), self::TIMEZONE)
        ];
    }

    /**
     * Valida si el momento actual está dentro del horario laboral de la ruta.
     */
    public function isWithinLegalSchedule(string $route, ?Carbon $dateTime = null, bool $allowException = false): bool
    {
        if ($allowException) {
            return true;
        }

        $now = $dateTime ? $dateTime->copy()->setTimezone(self::TIMEZONE) : Carbon::now(self::TIMEZONE);
        $schedule = $this->getScheduleForRoute($route, $now);

        if (!$schedule->is_working_day) {
            return false;
        }

        $timeStr = $now->format('H:i:s');
        return ($timeStr >= $schedule->start_time && $timeStr <= $schedule->end_time);
    }

    public function getLimitTimeForDate(string $route, Carbon $date): ?Carbon
    {
        return $this->getScheduleForRoute($route, $date)->limit_time;
    }

    public function getActiveWorkday(User $user): ?UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $todayStr = $now->toDateString();

        $workday = UserWorkday::where('user_id', $user->id)
            ->where('work_date', $todayStr)
            ->first();

        if (!$workday) {
            return null;
        }

        $hasException = ($workday->close_reason === 'EXCEPCION_AUTORIZADA');

        if ($workday->status === 'OPEN' && !$this->isWithinLegalSchedule($user->username, $now, $hasException)) {
            $limitTime = $this->getLimitTimeForDate($user->username, $now) ?? $now;
            $workday->update([
                'status'       => 'CLOSED_TIMEOUT',
                'ended_at'     => $limitTime,
                'close_reason' => 'Cierre automático por límite de horario laboral legal',
            ]);
        }

        return $workday;
    }

    public function startWorkday(User $user): UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $todayStr = $now->toDateString();

        if (!$this->isWithinLegalSchedule($user->username, $now)) {
            $schedule = $this->getScheduleForRoute($user->username, $now);
            if (!$schedule->is_working_day) {
                throw new \DomainException("La ruta {$user->username} no tiene programada jornada laboral para hoy.");
            }
            throw new \DomainException("Fuera de horario laboral permitido para la ruta {$user->username} ({$schedule->start_time} - {$schedule->end_time}).");
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

    public function closeWorkdayBySeller(User $user): ?UserWorkday
    {
        $now = Carbon::now(self::TIMEZONE);
        $workday = $this->getActiveWorkday($user);

        if ($workday && $workday->status === 'OPEN') {
            $workday->update([
                'status'       => 'CLOSED_SELLER',
                'ended_at'     => $now,
                'close_reason' => 'Finalizado voluntariamente por el vendedor',
            ]);
        }

        return $workday;
    }

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