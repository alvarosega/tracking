<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // 1. Ruta del usuario autenticado (ej: "TDB 99")
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

        // 3. Consulta contra u967339252_supervisor.pan_ruteo
        $query = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->where('estado', 'Activo')
            ->where('ruta', $rutaUsuario);

        $query->where(function ($q) use ($diaActual) {
            if ($diaActual === 'Miércoles') {
                $q->whereIn('dia_norm', ['Miercoles', 'Miércoles', 'MIERCOLES'])
                  ->orWhereIn('dia', ['Miercoles', 'Miércoles', 'MIERCOLES']);
            } elseif ($diaActual === 'Sábado') {
                $q->whereIn('dia_norm', ['Sabado', 'Sábado', 'SABADO'])
                  ->orWhereIn('dia', ['Sabado', 'Sábado', 'SABADO']);
            } else {
                $q->where('dia_norm', $diaActual)
                  ->orWhere('dia_norm', mb_strtoupper($diaActual, 'UTF-8'))
                  ->orWhere('dia', $diaActual);
            }
        });

        // 4. Mapeo alineado exactamente con PlanRuteoDto en Android
        $planRuteo = $query->orderBy('cliente_id', 'asc')
            ->select([
                'cliente_id',
                DB::raw("COALESCE(NULLIF(cliente, ''), cliente_norm, 'Cliente Sin Nombre') as cliente"),
                'ruta',
                'dia',
                DB::raw("COALESCE(direccion, '') as direccion"),
                'latitud',
                'longitud',
                DB::raw("COALESCE(estado, 'Activo') as estado"),
                DB::raw("COALESCE(tipo_negocio, '') as tipo_negocio"),
                DB::raw("COALESCE(contacto, '') as contacto"),
                DB::raw("COALESCE(telefono, '') as telefono"),
                DB::raw("COALESCE(celular, '') as celular"),
                DB::raw("COALESCE(referencia, '') as referencia"),
                DB::raw("COALESCE(nombre_factura, '') as nombre_factura"),
                DB::raw("COALESCE(nit, '') as nit"),
            ])
            ->get();

        return response()->json($planRuteo, 200);
    }
}