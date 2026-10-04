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

        // 2. Determinar día actual y fecha en Bolivia (UTC-4)
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
        $fechaHoy = $nowBolivia->toDateString();

        // 3. Subconsulta: obtener el ID más reciente de edición pendiente por cliente
        $latestPendingEdits = DB::connection('supervisor')
            ->table('saneamiento_base')
            ->select('cliente_id', DB::raw('MAX(id) as max_id'))
            ->where('tipo_registro', 'EDICION')
            ->where('estado_revision', 'PENDIENTE')
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id');

        // 4. Consulta principal contra pan_ruteo unida a la última edición pendiente
        $query = DB::connection('supervisor')
            ->table('pan_ruteo as p')
            ->leftJoinSub($latestPendingEdits, 'latest_sb', function ($join) {
                $join->on('p.cliente_id', '=', 'latest_sb.cliente_id');
            })
            ->leftJoin('saneamiento_base as sb', 'sb.id', '=', 'latest_sb.max_id')
            ->where('p.estado', 'Activo')
            ->where('p.ruta', $rutaUsuario);

        $query->where(function ($q) use ($diaActual) {
            if ($diaActual === 'Miércoles') {
                $q->whereIn('p.dia_norm', ['Miercoles', 'Miércoles', 'MIERCOLES'])
                  ->orWhereIn('p.dia', ['Miercoles', 'Miércoles', 'MIERCOLES']);
            } elseif ($diaActual === 'Sábado') {
                $q->whereIn('p.dia_norm', ['Sabado', 'Sábado', 'SABADO'])
                  ->orWhereIn('p.dia', ['Sabado', 'Sábado', 'SABADO']);
            } else {
                $q->where('p.dia_norm', $diaActual)
                  ->orWhere('p.dia_norm', mb_strtoupper($diaActual, 'UTF-8'))
                  ->orWhere('p.dia', $diaActual);
            }
        });

        // 5. Clientes obtenidos de supervisor
        $planRuteo = $query->orderBy('p.cliente_id', 'asc')
            ->select([
                'p.cliente_id',
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL THEN sb.cliente 
                    ELSE COALESCE(NULLIF(p.cliente, ''), p.cliente_norm, 'Cliente Sin Nombre') 
                END as cliente"),
                'p.ruta',
                'p.dia',
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.direccion, '') ELSE COALESCE(p.direccion, '') END as direccion"),
                // Inmutabilidad absoluta: coordenadas tomadas únicamente de pan_ruteo
                'p.latitud',
                'p.longitud',
                DB::raw("COALESCE(p.estado, 'Activo') as estado"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN sb.tipo_negocio ELSE COALESCE(p.tipo_negocio, '') END as tipo_negocio"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.zona, '') ELSE COALESCE(p.zona_venta, '') END as zona"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.contacto, '') ELSE COALESCE(p.contacto, '') END as contacto"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.telefono, '') ELSE COALESCE(p.telefono, '') END as telefono"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.celular, '') ELSE COALESCE(p.celular, '') END as celular"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.referencia, '') ELSE COALESCE(p.referencia, '') END as referencia"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.nombre_factura, '') ELSE COALESCE(p.nombre_factura, '') END as nombre_factura"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.nit, '') ELSE COALESCE(p.nit, '') END as nit"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN 1 ELSE 0 END as tiene_edicion_pendiente"),
            ])
            ->get();

        // 6. Consultar las visitas realizadas hoy por este usuario desde la conexión mysql
        $visitasHoy = DB::table('visitas')
            ->where('user_id', $user->id)
            ->whereDate('visited_at', $fechaHoy)
            ->get(['client_id', 'status'])
            ->keyBy('client_id');

        // 7. Mapeo final cruzando con el estado de visita
        $response = $planRuteo->map(function ($item) use ($visitasHoy) {
            $haSidoVisitado = $visitasHoy->has($item->cliente_id);
            $visita = $visitasHoy->get($item->cliente_id);

            $item->tiene_edicion_pendiente = (bool) $item->tiene_edicion_pendiente;
            $item->is_visited = $haSidoVisitado;
            $item->visita_estado = $haSidoVisitado ? $visita->status : 'PENDIENTE';

            return $item;
        });

        return response()->json($response, 200);
    }
}