<?php

declare(strict_types=1);

namespace App\Actions\Api\Ruteo;

use App\Models\RouteVisitDay;
use App\Models\User;
use App\Services\SaneamientoAuditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $dayOfWeekIso = (int) $nowBolivia->dayOfWeekIso; // 1 = Lunes, ..., 7 = Domingo
        $diaActual = self::DIAS_MAP[$dayOfWeekIso] ?? 'Lunes';
        $fechaHoy = $nowBolivia->toDateString();

        // 2.1 Resolver días de visita de pan_ruteo asignados para esta ruta en el día actual
        $diasVisitaObjetivo = $this->resolverDiasVisita($rutaUsuario, $dayOfWeekIso, $diaActual);

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
            ->where(function ($q): void {
                $q->whereNull('p.estado')
                  ->orWhere('p.estado', '')
                  ->orWhere('p.estado', 'Activo')
                  ->orWhereRaw("UPPER(TRIM(p.estado)) IN ('ACTIVO', 'ACTIVA', 'VIGENTE')")
                  ->orWhereRaw("UPPER(TRIM(p.estado)) NOT IN ('INACTIVO', 'INACTIVA', 'BAJA', 'ELIMINADO')");
            })
            ->where(function ($q) use ($rutaUsuario, $user): void {
                $q->where('p.ruta', $rutaUsuario);
                if (!empty($user->ruta) && $user->ruta !== $user->username) {
                    $q->orWhere('p.ruta', trim((string) $user->username));
                }
            });

        // 4.1 Filtro por los días de visita asignados (soporta asignaciones múltiples de pan_ruteo)
        $query->where(function ($q) use ($diasVisitaObjetivo): void {
            foreach ($diasVisitaObjetivo as $idx => $dia) {
                $variantes = $this->obtenerVariantesDia((string) $dia);
                if ($idx === 0) {
                    $q->where(function ($sub) use ($variantes) {
                        $sub->whereIn('p.dia_norm', $variantes)
                            ->orWhereIn('p.dia', $variantes);
                    });
                } else {
                    $q->orWhere(function ($sub) use ($variantes) {
                        $sub->whereIn('p.dia_norm', $variantes)
                            ->orWhereIn('p.dia', $variantes);
                    });
                }
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
                    ELSE COALESCE(TRIM(p.territorio), '') 
                END as zona"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL THEN COALESCE(sb.zona, '') 
                    ELSE COALESCE(TRIM(p.territorio), '') 
                END as zona_comercial"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL THEN COALESCE(sb.zona, '') 
                    ELSE COALESCE(TRIM(p.territorio), '') 
                END as territorio"),
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

    /**
     * Resuelve los días de visita de pan_ruteo configurados para la ruta en el día actual.
     * Si existe configuración personalizada activa en route_visit_days, se retornan esos días;
     * de lo contrario, se retorna el día natural actual de calendario.
     *
     * @return array<int, string>
     */
    protected function resolverDiasVisita(string $ruta, int $dayOfWeekIso, string $diaActual): array
    {
        try {
            $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));
            $config = RouteVisitDay::query()
                ->whereRaw("TRIM(UPPER(route)) = ?", [$cleanRuta])
                ->where('day_of_week', $dayOfWeekIso)
                ->where('is_active', true)
                ->first();

            if ($config && !empty($config->dias_visita) && is_array($config->dias_visita)) {
                $diasFiltrados = array_values(array_filter(
                    $config->dias_visita,
                    fn($d) => !empty(trim((string) $d))
                ));

                if (!empty($diasFiltrados)) {
                    return $diasFiltrados;
                }
            }
        } catch (\Throwable $e) {
            // Silencioso ante tablas pendientes o contingencias
            Log::warning("No se pudo consultar route_visit_days para ruta {$ruta}: " . $e->getMessage());
        }

        return [$diaActual];
    }

    /**
     * Retorna las variantes léxicas (con y sin tildes, mayúsculas, minúsculas) para cotejar en pan_ruteo.
     *
     * @return array<int, string>
     */
    protected function obtenerVariantesDia(string $dia): array
    {
        $diaCanonical = ucfirst(mb_strtolower(trim($dia), 'UTF-8'));

        return match ($diaCanonical) {
            'Lunes' => ['Lunes', 'LUNES', 'lunes'],
            'Martes' => ['Martes', 'MARTES', 'martes'],
            'Miércoles', 'Miercoles' => ['Miercoles', 'Miércoles', 'MIERCOLES', 'MIÉRCOLES', 'miercoles', 'miércoles'],
            'Jueves' => ['Jueves', 'JUEVES', 'jueves'],
            'Viernes' => ['Viernes', 'VIERNES', 'viernes'],
            'Sábado', 'Sabado' => ['Sabado', 'Sábado', 'SABADO', 'SÁBADO', 'sabado', 'sábado'],
            'Domingo' => ['Domingo', 'DOMINGO', 'domingo'],
            default => [$dia, mb_strtoupper($dia, 'UTF-8'), mb_strtolower($dia, 'UTF-8')],
        };
    }
}
