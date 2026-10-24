<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\WorkdayService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveWorkday
{
    public function __construct(
        protected WorkdayService $workdayService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!($user instanceof User)) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $workday = $this->workdayService->getActiveWorkday($user);

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
