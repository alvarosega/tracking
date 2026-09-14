<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanRuteo;
use Carbon\Carbon;
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

        // 1. Identificar la ruta del usuario autenticado (ej: "TDB 6A")
        $rutaUsuario = $user->username;

        // 2. Determinar el día en curso en Bolivia (UTC-4)
        $diasMap = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        $nowBolivia = Carbon::now('America/La_Paz');
        $diaActual = $diasMap[$nowBolivia->dayOfWeekIso];

        // 3. Filtrar estrictamente por la columna "route", "status" y el "day" actual
        $query = PlanRuteo::query()
            ->where('status', 'Activo')
            ->where('route', $rutaUsuario);

        // Absorbe discrepancias con o sin tilde que vengan del CSV
        $query->where(function ($q) use ($diaActual) {
            if ($diaActual === 'Miércoles') {
                $q->where('day', 'Miércoles')->orWhere('day', 'Miercoles');
            } elseif ($diaActual === 'Sábado') {
                $q->where('day', 'Sábado')->orWhere('day', 'Sabado');
            } else {
                $q->where('day', $diaActual);
            }
        });

        $planRuteo = $query->orderBy('client_id', 'asc')
            ->select([
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
            ])
            ->get();

        return response()->json($planRuteo, 200);
    }
}