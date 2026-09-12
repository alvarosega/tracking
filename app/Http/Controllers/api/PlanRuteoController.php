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

        // Si no hay usuario autenticado (token inválido o ausente), denegamos el acceso
        if (!$user) {
            return response()->json([
                'message' => 'No autorizado. Se requiere token de sesión válido.'
            ], 401);
        }

        $route = $user->username; 
        $day = $request->query('day');

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