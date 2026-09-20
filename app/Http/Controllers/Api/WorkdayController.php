<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserWorkday;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkdayController extends Controller
{
    protected WorkdayService $workdayService;

    public function __construct(WorkdayService $workdayService)
    {
        $this->workdayService = $workdayService;
    }

    /**
     * GET /api/workday/status
     * Polling ligero que consulta Android para saber si debe apagar el GPS.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $workday = $this->workdayService->getActiveWorkday($user->id);
        $isWithinSchedule = $this->workdayService->isWithinLegalSchedule();

        $isActive = ($workday && $workday->status === 'OPEN' && $isWithinSchedule);

        return response()->json([
            'is_active'       => $isActive,
            'status'          => $workday ? $workday->status : 'NOT_STARTED',
            'action'          => $isActive ? 'KEEP_TRACKING' : 'STOP_TRACKING',
            'work_date'       => Carbon::now(WorkdayService::TIMEZONE)->toDateString(),
            'started_at'      => $workday?->started_at?->toIso8601String(),
            'ended_at'        => $workday?->ended_at?->toIso8601String(),
            'close_reason'    => $workday?->close_reason,
            'within_schedule' => $isWithinSchedule
        ], 200);
    }

    /**
     * POST /api/workday/start
     * Inicia la jornada desde la app del vendedor.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $workday = $this->workdayService->startWorkday($user);

            return response()->json([
                'message' => 'Jornada iniciada correctamente.',
                'workday' => $workday
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'action'  => 'STOP_TRACKING'
            ], 422);
        }
    }

    /**
     * POST /api/workday/stop
     * El vendedor detiene voluntariamente su jornada.
     */
    public function stop(Request $request): JsonResponse
    {
        $user = $request->user();
        $workday = $this->workdayService->closeWorkdayBySeller($user->id);

        return response()->json([
            'message' => 'Jornada finalizada por el vendedor.',
            'workday' => $workday
        ], 200);
    }

    /**
     * POST /api/supervisor/workdays/{userId}/close
     * El supervisor finaliza remotamente la jornada de un vendedor.
     */
    public function forceCloseBySupervisor(Request $request, int $userId): JsonResponse
    {
        $supervisor = $request->user();

        $request->validate([
            'reason' => 'nullable|string|max:255'
        ]);

        $targetUser = User::find($userId);
        if (!$targetUser) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $workday = $this->workdayService->closeWorkdayBySupervisor(
            $targetUser->id,
            $supervisor->id,
            $request->input('reason')
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
     * GET /api/supervisor/workdays/today
     * Lista para el panel web del supervisor con el estado de todas las rutas de hoy.
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