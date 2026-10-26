<?php

declare(strict_types=1);

namespace App\Actions\Api\Ruteo;

use App\Models\User;
use App\Services\SaneamientoAuditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetPlanRuteoAction
{
    private const DIAS_MAP = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function __construct(
        protected SaneamientoAuditService $saneamientoAuditService
    ) {}

    /**
     * Obtiene el plan de ruteo del día para el vendedor, unificado con ediciones pendientes,
     * visitas realizadas hoy y diagnóstico de calidad de datos para saneamiento.
     *
     * @return Collection<int, object>
     */
    public function execute(User $user): Collection
    {
        // 1. Ruta comercial del usuario autenticado (priorizando columna ruta)
        $rutaUsuario = trim((string) ($user->ruta ?: $user->username));

        // 2. Determinar día actual y fecha en Bolivia (UTC-4)
        $nowBolivia = Carbon::now('America/La_Paz');
        $diaActual = self::DIAS_MAP[$nowBolivia->dayOfWeekIso];
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
            ->leftJoinSub($latestPendingEdits, 'latest_sb', function ($join): void {
                $join->on('p.cliente_id', '=', 'latest_sb.cliente_id');
            })
            ->leftJoin('saneamiento_base as sb', 'sb.id', '=', 'latest_sb.max_id')
            ->where('p.estado', 'Activo')
            ->where(function ($q) use ($rutaUsuario, $user): void {
                $q->where('p.ruta', $rutaUsuario);
                if (!empty($user->ruta) && $user->ruta !== $user->username) {
                    $q->orWhere('p.ruta', trim((string) $user->username));
                }
            });

        $query->where(function ($q) use ($diaActual): void {
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

        // 5. Clientes obtenidos del plan maestro en supervisor
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
                'p.latitud',
                'p.longitud',
                DB::raw("COALESCE(p.estado, 'Activo') as estado"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN sb.tipo_negocio ELSE COALESCE(p.tipo_negocio, '') END as tipo_negocio"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL THEN COALESCE(sb.zona, '') 
                    ELSE COALESCE(NULLIF(TRIM(p.zona_venta), ''), NULLIF(TRIM(p.territorio), ''), NULLIF(TRIM(p.mercado), ''), '') 
                END as zona"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL THEN COALESCE(sb.zona, '') 
                    ELSE COALESCE(NULLIF(TRIM(p.zona_venta), ''), NULLIF(TRIM(p.territorio), ''), NULLIF(TRIM(p.mercado), ''), '') 
                END as zona_comercial"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL THEN COALESCE(sb.zona, '') 
                    ELSE COALESCE(NULLIF(TRIM(p.zona_venta), ''), NULLIF(TRIM(p.territorio), ''), NULLIF(TRIM(p.mercado), ''), '') 
                END as zona_venta"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.contacto, '') ELSE COALESCE(p.contacto, '') END as contacto"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.telefono, '') ELSE COALESCE(p.telefono, '') END as telefono"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.celular, '') ELSE COALESCE(p.celular, '') END as celular"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.referencia, '') ELSE COALESCE(p.referencia, '') END as referencia"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.nombre_factura, '') ELSE COALESCE(p.nombre_factura, '') END as nombre_factura"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.nit, '') ELSE COALESCE(p.nit, '') END as nit"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN 1 ELSE 0 END as tiene_edicion_pendiente"),
            ])
            ->get();

        // 6. Consultar visitas realizadas hoy por este usuario desde la conexión mysql
        $visitasHoy = DB::table('visitas')
            ->where('user_id', $user->id)
            ->whereDate('visited_at', $fechaHoy)
            ->get(['client_id', 'status'])
            ->keyBy('client_id');

        // 7. Cruzar estado de visita y diagnosticar calidad de datos para saneamiento
        return $planRuteo->map(function ($item) use ($visitasHoy): object {
            $haSidoVisitado = $visitasHoy->has($item->cliente_id);
            $visita = $visitasHoy->get($item->cliente_id);

            $item->tiene_edicion_pendiente = (bool) $item->tiene_edicion_pendiente;
            $item->is_visited = $haSidoVisitado;
            $item->visita_estado = $haSidoVisitado ? $visita->status : 'PENDIENTE';

            // Diagnóstico de Saneamiento
            $audit = $this->saneamientoAuditService->audit($item);
            $item->requiere_saneamiento = $audit['requiere_saneamiento'];
            // Si ya tiene una edición pendiente de aprobación, no bloquear
            $item->saneamiento_obligatorio = $audit['saneamiento_obligatorio'] && !$item->tiene_edicion_pendiente;
            $item->saneamiento_alertas = $audit['alertas'];
            $item->saneamiento_campos_incompletos = $audit['campos_incompletos'];
            $item->calidad_score = $audit['calidad_score'];

            return $item;
        });
    }
}
