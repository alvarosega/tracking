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

        if (!$user) {
            return response()->json([
                'message' => 'No autorizado. Se requiere token de sesión válido.'
            ], 401);
        }

        $query = PlanRuteo::query();

        // Filtro opcional por ruta vía query param o asignado al usuario
        if ($request->has('route')) {
            $query->where('route', $request->query('route'));
        }

        // Filtro opcional por día de la semana
        if ($request->has('day')) {
            $query->where('day', $request->query('day'));
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