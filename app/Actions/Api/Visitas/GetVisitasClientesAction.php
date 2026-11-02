<?php

declare(strict_types=1);

namespace App\Actions\Api\Visitas;

use App\Models\RouteVisitDay;
use App\Models\User;
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

        // 5. Consultar clientes activos de la ruta en pan_ruteo
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
                DB::raw("CASE 
                    WHEN sb.id IS NOT NULL AND (sb.celular IS NOT NULL OR sb.telefono IS NOT NULL) THEN COALESCE(NULLIF(sb.celular, ''), NULLIF(sb.telefono, ''), '')
                    ELSE COALESCE(NULLIF(p.celular, ''), NULLIF(p.telefono, ''), '') 
                END as telefono"),
                'p.dia',
                'p.dia_norm',
                'p.ruta',
            ])
            ->orderBy('p.cliente_id', 'asc')
            ->get();

        // 6. Deduplicar por cliente_id y estructurar formato ligero para Retrofit
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
            $telefono = trim((string) ($c->telefono ?: ''));
            $diaDisplay = ucfirst($diaNorm);

            $resultado[] = [
                'id' => $cid,
                'id_cliente' => $cid,
                'cliente_id' => $cid,
                'nombre' => $nombre,
                'cliente' => $nombre,
                'telefono' => $telefono,
                'dia' => $diaDisplay,
                'status' => $status,
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

