<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Horarios;

use App\Models\UserWorkday;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class GetActiveWorkdaysAction
{
    /**
     * Retorna la lista en vivo de usuarios con jornada de trabajo activa el día de hoy.
     *
     * @return Collection
     */
    public function execute(): Collection
    {
        $today = Carbon::now(WorkdayService::TIMEZONE)->toDateString();

        return UserWorkday::with(['user:id,username'])
            ->where('work_date', $today)
            ->where('status', 'OPEN')
            ->orderBy('started_at', 'desc')
            ->get();
    }
}

