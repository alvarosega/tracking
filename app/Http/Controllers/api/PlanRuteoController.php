<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanRuteo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanRuteoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $route = $user->username; // El username coincide con la ruta (ej. TDB 6A)
        $day = $request->query('day'); // Opcional: filtrar por día específico

        $query = PlanRuteo::query()
            ->where('route', $route);

        if ($day) {
            $query->where('day', $day);
        }

        $planRuteo = $query->select([
            'client_id',
            'client_name',
            'seller_name',
            'business_type',
            'territory',
            'address',
            'reference',
            'latitude',
            'longitude',
            'status',
            'route',
            'day',
        ])->get();

        return response()->json($planRuteo, 200);
    }
}