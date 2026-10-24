<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Workday\ForceCloseWorkdayRequest;
use App\Models\User;
use App\Models\UserWorkday;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkdayController extends Controller
{
    public function __construct(
        protected WorkdayService $workdayService
    ) {}

    /**
     * Consulta el estado de la jornada laboral del usuario autenticado.
     * GET /api/workday/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now(WorkdayService::TIMEZONE);
        
        $workday = $this->workdayService->getActiveWorkday($user);
        $isWithinSchedule = $this->workdayService->isWithinLegalSchedule((string) $user->username, $now);
        $limitTime = $this->workdayService->getLimitTimeForDate((string) $user->username, $now);

        $isActive = ($workday && $workday->status === 'OPEN' && $isWithinSchedule);

        return response()->json([
            'is_active' => $isActive,
            'status' => $workday ? $workday->status : 'NOT_STARTED',
            'action' => $isActive ? 'KEEP_TRACKING' : 'STOP_TRACKING',
            'work_date' => $now->toDateString(),
            'started_at' => $workday?->started_at?->toIso8601String(),
            'ended_at' => $workday?->ended_at?->toIso8601String(),
            'close_reason' => $workday?->close_reason,
            'limit_time' => $limitTime ? $limitTime->format('H:i:s') : null,
            'within_schedule' => $isWithinSchedule
        ], 200);
    }

    /**
     * Inicia la jornada laboral diaria del vendedor.
     * POST /api/workday/start
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $workday = $this->workdayService->startWorkday($user);
            $limitTime = $this->workdayService->getLimitTimeForDate((string) $user->username, Carbon::now(WorkdayService::TIMEZONE));

            return response()->json([
                'message' => 'Jornada iniciada correctamente.',
                'is_active' => true,
                'status' => $workday->status,
                'action' => 'START_TRACKING',
                'limit_time' => $limitTime ? $limitTime->format('H:i:s') : null,
                'workday' => $workday
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'is_active' => false,
                'status' => 'CLOSED',
                'action' => 'STOP_TRACKING'
            ], 422);
        }
    }

    /**
     * Cierre voluntario de jornada por parte del vendedor.
     * POST /api/workday/stop
     */
    public function stop(Request $request): JsonResponse
    {
        $user = $request->user();
        $workday = $this->workdayService->closeWorkdayBySeller($user);

        return response()->json([
            'message' => 'Jornada finalizada por el vendedor.',
            'is_active' => false,
            'status' => $workday ? $workday->status : 'CLOSED_SELLER',
            'action' => 'STOP_TRACKING',
            'workday' => $workday
        ], 200);
    }

    /**
     * Cierre forzado de jornada ejecutado por el supervisor.
     * POST /api/supervisor/workdays/{userId}/close
     */
    public function forceCloseBySupervisor(ForceCloseWorkdayRequest $request, int $userId): JsonResponse
    {
        $supervisor = $request->user();
        $targetUser = User::find($userId);

        if (!$targetUser) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $workday = $this->workdayService->closeWorkdayBySupervisor(
            $targetUser->id,
            $supervisor->id,
            $request->validated('reason')
        );

        if (!$workday) {
            return response()->json([
                'message' => 'El usuario no tiene una jornada abierta para hoy.'
            ], 404);
        }

        return response()->json([
            'message' => "Jornada del vendedor {$targetUser->username} finalizada correctamente por el supervisor.",
            'workday' => $workday
        ], 200);
    }

    /**
     * Lista de jornadas del día actual para monitoreo de supervisión.
     * GET /api/supervisor/workdays/today
     */
    public function todayWorkdays(Request $request): JsonResponse
    {
        $today = Carbon::now(WorkdayService::TIMEZONE)->toDateString();

        $workdays = UserWorkday::with(['user:id,username,role_id', 'supervisor:id,username'])
            ->where('work_date', $today)
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json($workdays, 200);
    }
}
