<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Horarios;

use App\Models\UserWorkday;
use App\Services\WorkdayService;

class ForceCloseWorkdayAction
{
    public function __construct(
        private readonly WorkdayService $workdayService
    ) {}

    /**
     * Cierre remoto forzado de jornada desde el módulo de horarios.
     *
     * @param int $userId
     * @param int|null $supervisorId
     * @param string|null $reason
     * @return UserWorkday|null
     */
    public function execute(int $userId, ?int $supervisorId, ?string $reason = null): ?UserWorkday
    {
        $closeReason = $reason ?: 'Cierre manual ejecutado por el supervisor desde el panel de horarios';

        return $this->workdayService->closeWorkdayBySupervisor(
            $userId,
            $supervisorId,
            $closeReason
        );
    }
}

