<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\GetPlanRuteoAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanRuteoController extends Controller
{
    /**
     * Descarga del plan de ruteo maestro unificado con ediciones y visitas del día.
     * GET /api/plan-ruteo
     */
    public function index(Request $request, GetPlanRuteoAction $action): JsonResponse
    {
        $user = $request->user();
        $planRuteo = $action->execute($user);

        return response()->json($planRuteo, 200);
    }
}
