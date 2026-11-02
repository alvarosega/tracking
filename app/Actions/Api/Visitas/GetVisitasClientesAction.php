<?php

declare(strict_types=1);

namespace App\Actions\Api\Visitas;

use App\Models\RouteVisitDay;
use App\Models\User;
use App\Services\SaneamientoAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetVisitasClientesAction
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
     * Retorna el listado ligero de todos los clientes de la ruta para el submódulo de Visitas.
     * Marca con status 'visited' a los clientes cuyo día de visita coincide con el día asignado para hoy,
     * y con status 'unvisited' a los clientes de otros días de la ruta (referenciales).
     *
     * @param User $user
     * @param string|null $diaParam Opcional para forzar un día específico
     * @return array<int, array<string, mixed>>
     */
    public function execute(User $user, ?string $diaParam = null): array
    {
        // 1. Resolver ruta comercial del vendedor
        $rutaUsuario = trim((string) ($user->ruta ?: $user->username));

        // 2. Determinar día actual y día de la semana en Bolivia (UTC-4)
        $nowBolivia = Carbon::now('America/La_Paz');
        $dayOfWeekIso = (int) $nowBolivia->dayOfWeekIso; // 1 = Lunes .. 7 = Domingo
        $diaActual = self::DIAS_MAP[$dayOfWeekIso] ?? 'Lunes';

        if (!empty($diaParam)) {
            $diaNormParam = $this->normalizarDia($diaParam);
            foreach (self::DIAS_MAP as $iso => $nombre) {
                if ($this->normalizarDia($nombre) === $diaNormParam) {
                    $dayOfWeekIso = $iso;
                    $diaActual = $nombre;
                    break;
                }
            }
        }

        // 3. Resolver días de visita habilitados para esta ruta hoy (según route_visit_days o día natural)
        $diasHabilitados = $this->resolverDiasHabilitados($rutaUsuario, $dayOfWeekIso, $diaActual);
        $diasHabilitadosNorm = array_map([$this, 'normalizarDia'], $diasHabilitados);

        // 4. Subconsulta de ediciones pendientes en saneamiento_base para nombres/teléfonos actualizados
        $latestPendingEdits = DB::connection('supervisor')
            ->table('saneamiento_base')
            ->select('cliente_id', DB::raw('MAX(id) as max_id'))
            ->where('tipo_registro', 'EDICION')
            ->where('estado_revision', 'PENDIENTE')
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id');

        // 5. Consultar clientes visitados hoy en mysql.visitas para is_visited
        $todayBolivia = $nowBolivia->toDateString();
        $visitedTodayMap = [];
        try {
            $visitedClientIds = DB::connection('mysql')
                ->table('visitas')
                ->whereDate('created_at', $todayBolivia)
                ->where(function ($q) use ($user, $rutaUsuario): void {
                    $q->where('user_id', $user->id)
                      ->orWhere('route', $rutaUsuario);
                })
                ->whereNotNull('client_id')
                ->pluck('client_id')
                ->all();
            $visitedTodayMap = array_flip($visitedClientIds);
        } catch (\Throwable $ve) {
            Log::warning('GetVisitasClientesAction visitas query: ' . $ve->getMessage());
        }

        // 6. Consultar todos los clientes de la ruta en pan_ruteo (incluye referenciales)
        $query = DB::connection('supervisor')
            ->table('pan_ruteo as p')
            ->leftJoinSub($latestPendingEdits, 'latest_sb', function ($join): void {
                $join->on('p.cliente_id', '=', 'latest_sb.cliente_id');
            })
            ->leftJoin('saneamiento_base as sb', 'sb.id', '=', 'latest_sb.max_id')
            ->where(function ($q) use ($rutaUsuario, $user): void {
                $q->whereRaw("TRIM(UPPER(p.ruta)) = ?", [strtoupper($rutaUsuario)]);
                if (!empty($user->ruta) && $user->ruta !== $user->username) {
                    $q->orWhereRaw("TRIM(UPPER(p.ruta)) = ?", [strtoupper(trim((string) $user->username))]);
                }
            })
            ->where('p.cliente_id', '>', 0);

        $clientesRaw = $query
            ->select([
                'p.cliente_id',
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL AND sb.cliente IS NOT NULL AND TRIM(sb.cliente) != '' THEN sb.cliente 
                    ELSE COALESCE(NULLIF(p.cliente, ''), p.cliente_norm, 'Cliente S/N') 
                END as cliente"),
                'p.ruta',
                'p.dia',
                'p.dia_norm',
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL AND sb.direccion IS NOT NULL AND TRIM(sb.direccion) != '' THEN sb.direccion 
                    ELSE COALESCE(p.direccion, '') 
                END as direccion"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL AND sb.latitude IS NOT NULL THEN sb.latitude 
                    ELSE p.latitud 
                END as latitud"),
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL AND sb.longitude IS NOT NULL THEN sb.longitude 
                    ELSE p.longitud 
                END as longitud"),
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
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL AND (sb.celular IS NOT NULL OR sb.telefono IS NOT NULL) THEN COALESCE(NULLIF(sb.celular, ''), NULLIF(sb.telefono, ''), '')
                    ELSE COALESCE(NULLIF(p.celular, ''), NULLIF(p.telefono, ''), '') 
                END as telefono"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.celular, '') ELSE COALESCE(p.celular, '') END as celular"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.referencia, '') ELSE COALESCE(p.referencia, '') END as referencia"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.nombre_factura, '') ELSE COALESCE(p.nombre_factura, '') END as nombre_factura"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN COALESCE(sb.nit, '') ELSE COALESCE(p.nit, '') END as nit"),
                DB::raw("CASE WHEN sb.id IS NOT NULL THEN 1 ELSE 0 END as tiene_edicion_pendiente"),
            ])
            ->orderBy('p.cliente_id', 'asc')
            ->get();

        // 7. Deduplicar por cliente_id y estructurar formato unificado compatible con PlanRuteoDto
        $clientesVistos = [];
        $resultado = [];

        foreach ($clientesRaw as $c) {
            $cid = (int) $c->cliente_id;
            if (isset($clientesVistos[$cid])) {
                continue;
            }
            $clientesVistos[$cid] = true;

            $diaRaw = !empty($c->dia_norm) ? $c->dia_norm : $c->dia;
            $diaNorm = $this->normalizarDia((string) $diaRaw);

            $esHabilitado = in_array($diaNorm, $diasHabilitadosNorm, true);
            $status = $esHabilitado ? 'visited' : 'unvisited';

            $nombre = trim((string) $c->cliente);
            $direccion = trim((string) ($c->direccion ?? ''));
            $telefono = trim((string) ($c->telefono ?: ''));
            $diaDisplay = ucfirst($diaNorm);
            $latitud = $c->latitud !== null && $c->latitud !== '' ? (float) $c->latitud : null;
            $longitud = $c->longitud !== null && $c->longitud !== '' ? (float) $c->longitud : null;
            $isVisited = isset($visitedTodayMap[$cid]);

            // Auditoría de saneamiento para Modo Censo / Calidad de Datos
            $c->tiene_edicion_pendiente = (bool) $c->tiene_edicion_pendiente;
            $audit = $this->saneamientoAuditService->audit($c);

            $resultado[] = [
                'id' => $cid,
                'id_cliente' => $cid,
                'cliente_id' => $cid,
                'nombre' => $nombre,
                'cliente' => $nombre,
                'ruta' => (string) ($c->ruta ?? $rutaUsuario),
                'dia' => $diaDisplay,
                'direccion' => $direccion,
                'latitud' => $latitud,
                'longitud' => $longitud,
                'telefono' => $telefono,
                'celular' => (string) ($c->celular ?? ''),
                'contacto' => (string) ($c->contacto ?? ''),
                'estado' => (string) ($c->estado ?? 'Activo'),
                'tipo_negocio' => (string) ($c->tipo_negocio ?? ''),
                'zona' => (string) ($c->zona ?? ''),
                'zona_comercial' => (string) ($c->zona_comercial ?? ''),
                'territorio' => (string) ($c->territorio ?? ''),
                'referencia' => (string) ($c->referencia ?? ''),
                'nombre_factura' => (string) ($c->nombre_factura ?? ''),
                'nit' => (string) ($c->nit ?? ''),
                'tiene_edicion_pendiente' => (bool) $c->tiene_edicion_pendiente,
                'is_visited' => $isVisited,
                'visita_estado' => $isVisited ? 'VISITADO' : 'PENDIENTE',
                'status' => $status,
                'requiere_saneamiento' => (bool) $audit['requiere_saneamiento'],
                'saneamiento_obligatorio' => (bool) ($audit['saneamiento_obligatorio'] && !$c->tiene_edicion_pendiente),
                'saneamiento_alertas' => $audit['alertas'],
                'saneamiento_campos_incompletos' => $audit['campos_incompletos'],
                'calidad_score' => (int) $audit['calidad_score'],
            ];
        }

        return $resultado;
    }

    /**
     * Resuelve los días de visita habilitados para la ruta en el día actual según route_visit_days.
     *
     * @return array<int, string>
     */
    protected function resolverDiasHabilitados(string $ruta, int $dayOfWeekIso, string $diaActual): array
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
            Log::warning("GetVisitasClientesAction: no se pudo consultar route_visit_days para ruta {$ruta}: " . $e->getMessage());
        }

        return [$diaActual];
    }

    /**
     * Normaliza cualquier representación de día a minúsculas sin acentos.
     */
    protected function normalizarDia(?string $dia): string
    {
        $limpio = mb_strtolower(trim((string) $dia), 'UTF-8');
        $limpio = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $limpio);

        if (str_starts_with($limpio, 'lun') || $limpio === '1') return 'lunes';
        if (str_starts_with($limpio, 'mar') || $limpio === '2') return 'martes';
        if (str_starts_with($limpio, 'mie') || $limpio === '3') return 'miercoles';
        if (str_starts_with($limpio, 'jue') || $limpio === '4') return 'jueves';
        if (str_starts_with($limpio, 'vie') || $limpio === '5') return 'viernes';
        if (str_starts_with($limpio, 'sab') || $limpio === '6') return 'sabado';
        if (str_starts_with($limpio, 'dom') || $limpio === '7') return 'domingo';

        return $limpio;
    }
}

