<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Jornadas;

use App\Models\UserWorkday;
use App\Services\WorkdayService;

class CloseSingleJornadaAction
{
    public function __construct(
        private readonly WorkdayService $workdayService
    ) {}

    /**
     * Cierra remotamente la jornada de un vendedor específico.
     */
    public function execute(int $userId, ?int $supervisorId, ?string $reason = null): ?UserWorkday
    {
        $closeReason = $reason ?: 'Cierre remoto individual ejecutado desde el panel de jornadas';

        return $this->workdayService->closeWorkdayBySupervisor(
            $userId,
            $supervisorId,
            $closeReason
        );
    }
}

