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

        $query = PlanRuteo::query();

        // 1. Mapeo del día en curso oficial de Bolivia (America/La_Paz, UTC-4)
        $diasSemana = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        $nowBolivia = Carbon::now('America/La_Paz');
        $diaActual = $diasSemana[$nowBolivia->dayOfWeekIso] ?? 'Lunes';

        // 2. Control de Acceso según Rol
        $roleName = strtolower($user->role?->name ?? 'vendedor');

        if ($roleName === 'vendedor') {
            // Un vendedor solo ve sus clientes Activos, de su Ruta y del Día actual
            $query->where('status', 'Activo')
                  ->where('route', $user->username);

            // Permite override de día únicamente en modo desarrollo/debug para testing
            $targetDay = (config('app.debug') && $request->filled('day')) 
                ? $request->query('day') 
                : $diaActual;

            $this->applyDayFilter($query, $targetDay);

        } else {
            // Supervisores o administradores: filtros abiertos y opcionales
            if ($request->filled('route')) {
                $query->where('route', $request->query('route'));
            }

            if ($request->filled('day')) {
                $this->applyDayFilter($query, $request->query('day'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
        }

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

    /**
     * Aplica el filtro de día absorbiendo variaciones con y sin tilde del CSV.
     */
    private function applyDayFilter($query, string $day): void
    {
        $day = trim($day);

        $query->where(function ($q) use ($day) {
            if (in_array(mb_strtolower($day, 'UTF-8'), ['miércoles', 'miercoles'])) {
                $q->where('day', 'Miércoles')->orWhere('day', 'Miercoles');
            } elseif (in_array(mb_strtolower($day, 'UTF-8'), ['sábado', 'sabado'])) {
                $q->where('day', 'Sábado')->orWhere('day', 'Sabado');
            } else {
                $q->where('day', $day);
            }
        });
    }
}