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

        // 1. Ruta del usuario autenticado (ej: "TDB 6A" o "TDB 99")
        $rutaUsuario = trim($user->username);

        // 2. Determinar día actual en Bolivia (UTC-4)
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

        // 3. Consulta contra la conexión supervisor.pan_ruteo
        $query = PlanRuteo::query()
            ->where('estado', 'Activo')
            ->where('ruta', $rutaUsuario);

        // Filtrado de día aprovechando 'dia_norm' o 'dia'
        $query->where(function ($q) use ($diaActual) {
            if ($diaActual === 'Miércoles') {
                $q->whereIn('dia_norm', ['Miercoles', 'Miércoles'])
                  ->orWhereIn('dia', ['Miercoles', 'Miércoles']);
            } elseif ($diaActual === 'Sábado') {
                $q->whereIn('dia_norm', ['Sabado', 'Sábado'])
                  ->orWhereIn('dia', ['Sabado', 'Sábado']);
            } else {
                $q->where('dia_norm', $diaActual)
                  ->orWhere('dia', $diaActual);
            }
        });

        // Mapeo con alias para preservar exactamente el contrato de Android
        $planRuteo = $query->orderBy('cliente_id', 'asc')
            ->select([
                'cliente_id as client_id',
                'cliente as client_name',
                'vendedor as seller_name',
                'tipo_negocio as business_type',
                'territorio as territory',
                'direccion as address',
                'referencia as reference',
                'latitud as latitude',
                'longitud as longitude',
                'estado as status',
                'ruta as route',
                'dia as day',
            ])
            ->get();

        return response()->json($planRuteo, 200);
    }
}