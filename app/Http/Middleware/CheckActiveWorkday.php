<?php

namespace App\Http\Middleware;

use App\Services\WorkdayService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveWorkday
{
    protected WorkdayService $workdayService;

    public function __construct(WorkdayService $workdayService)
    {
        $this->workdayService = $workdayService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $workday = $this->workdayService->getActiveWorkday($user->id);

        if (!$workday || $workday->status !== 'OPEN') {
            return response()->json([
                'status'  => 'WORKDAY_CLOSED',
                'action'  => 'STOP_TRACKING',
                'message' => 'La jornada laboral está inactiva o finalizada. Se prohíbe la ingesta de ubicación.',
                'reason'  => $workday ? $workday->close_reason : 'Sin jornada registrada para hoy'
            ], 403);
        }

        return $next($request);
    }
}