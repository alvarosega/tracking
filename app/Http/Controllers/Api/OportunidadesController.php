<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OportunidadesController extends Controller
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

        // 2. Determinar día actual en Bolivia (UTC-4) o query param opcional
        $diasMap = [
            1 => 'LUNES',
            2 => 'MARTES',
            3 => 'MIERCOLES',
            4 => 'JUEVES',
            5 => 'VIERNES',
            6 => 'SABADO',
            7 => 'DOMINGO',
        ];

        $nowBolivia = Carbon::now('America/La_Paz');
        $diaDefault = $diasMap[$nowBolivia->dayOfWeekIso];

        $diaSolicitado = $request->query('dia') 
            ? mb_strtoupper(trim($request->query('dia')), 'UTF-8') 
            : $diaDefault;

        $diaSolicitado = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $diaSolicitado);

        // 3. Validar frontera activa
        $frontera = DB::connection('supervisor')
            ->table('dim_fronteras_dias')
            ->where('ruta', $rutaUsuario)
            ->where('dia', $diaSolicitado)
            ->where('estado', 'ACTIVO')
            ->select('id')
            ->first();

        if (!$frontera) {
            return response()->json([
                'ruta' => $rutaUsuario,
                'dia' => $diaSolicitado,
                'total' => 0,
                'oportunidades' => []
            ], 200);
        }

        // 4. Consulta espacial compatible con MySQL/MariaDB (ST_GeomFromText con SRID 4326)
        $oportunidades = DB::connection('supervisor')
            ->table('fact_oportunidades as o')
            ->join('dim_fronteras_dias as f', function ($join) use ($rutaUsuario, $diaSolicitado) {
                $join->on('f.ruta', '=', DB::raw("'" . addslashes($rutaUsuario) . "'"))
                     ->where('f.dia', '=', $diaSolicitado)
                     ->where('f.estado', '=', 'ACTIVO');
            })
            ->whereRaw("ST_Contains(f.poligono, ST_GeomFromText(CONCAT('POINT(', o.longitud, ' ', o.latitud, ')'), 4326))")
            ->select([
                'o.cliid as client_id',
                'o.clinom as client_name',
                'o.nombre_fantasia',
                'o.comprador',
                'o.clidom as address',
                'o.telefono as phone',
                'o.categoria as category',
                'o.ramo as sector',
                'o.latitud as latitude',
                'o.longitud as longitude',
            ])
            ->orderBy('o.clinom', 'asc')
            ->get();

        return response()->json([
            'ruta' => $rutaUsuario,
            'dia' => $diaSolicitado,
            'total' => $oportunidades->count(),
            'oportunidades' => $oportunidades
        ], 200);
    }
}