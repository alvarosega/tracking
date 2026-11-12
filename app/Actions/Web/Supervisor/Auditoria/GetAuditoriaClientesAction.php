<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use Illuminate\Support\Facades\DB;

class GetAuditoriaClientesAction
{
    /**
     * Consulta agrupada de auditoría de ruteo por código de cliente.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function execute(array $params = []): array
    {
        $conn = DB::connection('supervisor');

        $canal = trim((string) ($params['canal'] ?? ''));
        $rutas = (array) ($params['rutas'] ?? []);
        $archivoOrigen = trim((string) ($params['archivo_origen'] ?? ''));
        $estadoFiltro = strtoupper(trim((string) ($params['estado_filtro'] ?? 'TODOS'))); // TODOS | CON_PENDIENTES | 100_RESUELTOS | SOLO_PENDIENTES
        $categoria = strtoupper(trim((string) ($params['categoria'] ?? 'TODOS')));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $busqueda = trim((string) ($params['busqueda'] ?? ''));

        $page = isset($params['page']) && is_numeric($params['page']) && (int) $params['page'] > 0
            ? (int) $params['page']
            : 1;

        $perPage = isset($params['per_page']) && is_numeric($params['per_page']) && (int) $params['per_page'] > 0
            ? min((int) $params['per_page'], 200)
            : 25;

        $offset = ($page - 1) * $perPage;

        // Subquery de pan_ruteo para consolidar datos vivos actuales de clientes
        $subPan = $conn->table('pan_ruteo')
            ->selectRaw('cliente_id, MAX(cliente) AS cliente, MAX(ruta) AS ruta, MAX(dia_norm) AS dia_norm, MAX(dia) AS dia, MAX(direccion) AS direccion, MAX(vendedor) AS vendedor, MAX(latitud) AS latitud, MAX(longitud) AS longitud, MAX(tipo_negocio) AS tipo_negocio, MAX(cluster_t) AS cluster_t')
            ->groupBy('cliente_id');

        // Subquery deduplicado de dim_rutas para evitar producto cartesiano
        $subRutas = $conn->table('dim_rutas')
            ->selectRaw("TRIM(UPPER(ruta)) AS ruta_clean, MAX(TRIM(canal)) AS canal, MAX(TRIM(vendedor)) AS vendedor")
            ->whereNotNull('ruta')
            ->whereRaw("TRIM(ruta) != ''")
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"));

        // Query base para auditoria_ruteo_cambios
        $baseQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->leftJoinSub($subPan, 'p', 'a.cliente_id', '=', 'p.cliente_id')
            ->leftJoinSub($subRutas, 'r_ant', function ($join) {
                $join->on(DB::raw("
                    TRIM(UPPER(
                        CASE 
                            WHEN a.valor_anterior LIKE '%Ruta: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_anterior, 'Ruta: ', -1), ' |', 1)
                            WHEN a.tipo_evento = 'MODIFICADO_RUTA' THEN a.valor_anterior
                            ELSE COALESCE(NULLIF(p.ruta, ''), a.valor_anterior, '')
                        END
                    ))
                "), '=', 'r_ant.ruta_clean');
            })
            ->leftJoinSub($subRutas, 'r_nue', function ($join) {
                $join->on(DB::raw("
                    TRIM(UPPER(
                        CASE 
                            WHEN a.valor_nuevo LIKE '%Ruta: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_nuevo, 'Ruta: ', -1), ' |', 1)
                            WHEN a.tipo_evento = 'MODIFICADO_RUTA' THEN a.valor_nuevo
                            ELSE COALESCE(NULLIF(a.valor_nuevo, ''), p.ruta, '')
                        END
                    ))
                "), '=', 'r_nue.ruta_clean');
            });

        // 1. Filtro de Canal
        if ($canal !== '' && strtoupper($canal) !== 'TODOS') {
            $baseQuery->where(function ($q) use ($canal) {
                $q->whereRaw('TRIM(r_ant.canal) = ?', [$canal])
                  ->orWhereRaw('TRIM(r_nue.canal) = ?', [$canal]);
            });
        }

        // 2. Filtro de Rutas
        $rutasValidas = array_filter(array_map('trim', $rutas));
        if (!empty($rutasValidas)) {
            $rutasUpper = array_map('strtoupper', $rutasValidas);
            $baseQuery->where(function ($q) use ($rutasUpper) {
                $q->whereIn(DB::raw("TRIM(UPPER(COALESCE(p.ruta, '')))"), $rutasUpper)
                  ->orWhereIn(DB::raw("
                      TRIM(UPPER(
                          CASE 
                              WHEN a.valor_anterior LIKE '%Ruta: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_anterior, 'Ruta: ', -1), ' |', 1)
                              WHEN a.tipo_evento = 'MODIFICADO_RUTA' THEN a.valor_anterior
                              ELSE COALESCE(a.valor_anterior, '')
                          END
                      ))
                  "), $rutasUpper)
                  ->orWhereIn(DB::raw("
                      TRIM(UPPER(
                          CASE 
                              WHEN a.valor_nuevo LIKE '%Ruta: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_nuevo, 'Ruta: ', -1), ' |', 1)
                              WHEN a.tipo_evento = 'MODIFICADO_RUTA' THEN a.valor_nuevo
                              ELSE COALESCE(a.valor_nuevo, '')
                          END
                      ))
                  "), $rutasUpper);
            });
        }

        // 3. Archivo Origen
        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $baseQuery->where('a.archivo_origen', $archivoOrigen);
        }

        // 4. Rango de Fechas
        if ($fechaInicio !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) >= ?', [$fechaInicio]);
        }
        if ($fechaFin !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) <= ?', [$fechaFin]);
        }

        // 5. Categoría de Cambio
        if ($categoria !== '' && $categoria !== 'TODOS') {
            switch ($categoria) {
                case 'ALTAS_REACTIVACIONES':
                    $baseQuery->where('a.campo_afectado', '!=', 'frecuencia_visita')
                              ->where(function ($q) {
                                  $q->whereIn('a.tipo_evento', ['NUEVO', 'REACTIVADO'])
                                    ->orWhere('a.campo_afectado', 'cliente_alta_total');
                              });
                    break;
                case 'BAJAS_INACTIVADOS':
                    $baseQuery->where('a.campo_afectado', '!=', 'frecuencia_visita')
                              ->where(function ($q) {
                                  $q->whereIn('a.tipo_evento', ['ELIMINADO', 'INACTIVADO'])
                                    ->orWhere('a.campo_afectado', 'cliente_baja_total');
                              });
                    break;
                case 'CAMBIOS_RUTA':
                    $baseQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_RUTA')
                          ->orWhere('a.campo_afectado', 'ruta');
                    });
                    break;
                case 'CAMBIOS_DIA':
                    $baseQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_DIA')
                          ->orWhere('a.campo_afectado', 'dia');
                    });
                    break;
                case 'CAMBIO_DE_CLUSTER':
                    $baseQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'CAMBIO_DE_CLUSTER')
                          ->orWhereIn('a.campo_afectado', ['cluster', 'cluster_t', 'tipo_negocio']);
                    });
                    break;
                case 'MODIFICADO_VENDEDOR':
                    $baseQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_VENDEDOR')
                          ->orWhere('a.campo_afectado', 'vendedor');
                    });
                    break;
                case 'MODIFICADO_COORDENADAS':
                    $baseQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_COORDENADAS')
                          ->orWhere('a.campo_afectado', 'coordenadas');
                    });
                    break;
                case 'FRECUENCIA_VISITA':
                    $baseQuery->where('a.campo_afectado', 'frecuencia_visita');
                    break;
                case 'MODIFICACION_DATOS':
                    $baseQuery->where('a.tipo_evento', 'MODIFICADO')
                              ->whereNotIn('a.campo_afectado', ['frecuencia_visita', 'ruta', 'dia', 'cluster', 'cluster_t', 'tipo_negocio', 'vendedor', 'coordenadas']);
                    break;
            }
        }

        // 6. Buscador General
        if ($busqueda !== '') {
            $baseQuery->where(function ($q) use ($busqueda) {
                if (is_numeric($busqueda)) {
                    $q->where('a.cliente_id', (int) $busqueda)
                      ->orWhere('a.id', (int) $busqueda);
                }
                $term = "%{$busqueda}%";
                $q->orWhere('a.cliente', 'LIKE', $term)
                  ->orWhere('p.cliente', 'LIKE', $term)
                  ->orWhere('p.vendedor', 'LIKE', $term)
                  ->orWhere('p.direccion', 'LIKE', $term)
                  ->orWhere('a.valor_anterior', 'LIKE', $term)
                  ->orWhere('a.valor_nuevo', 'LIKE', $term)
                  ->orWhere('a.observaciones', 'LIKE', $term)
                  ->orWhere('a.auditado_por', 'LIKE', $term);
            });
        }

        // Agrupación por cliente_id para identificar los clientes y sus conteos
        $clientesGroupQuery = (clone $baseQuery)
            ->select([
                'a.cliente_id',
                DB::raw("COALESCE(NULLIF(TRIM(MAX(p.cliente)), ''), NULLIF(TRIM(MAX(a.cliente)), ''), 'Sin Nombre') AS cliente"),
                DB::raw("
                    COALESCE(
                        NULLIF(TRIM(UPPER(MAX(p.ruta))), ''),
                        NULLIF(TRIM(UPPER(MAX(CASE WHEN a.valor_nuevo LIKE '%Ruta: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_nuevo, 'Ruta: ', -1), ' |', 1) ELSE NULL END))), ''),
                        NULLIF(TRIM(UPPER(MAX(CASE WHEN a.valor_anterior LIKE '%Ruta: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_anterior, 'Ruta: ', -1), ' |', 1) ELSE NULL END))), ''),
                        NULLIF(TRIM(UPPER(MAX(r_nue.ruta_clean))), ''),
                        NULLIF(TRIM(UPPER(MAX(r_ant.ruta_clean))), ''),
                        'SIN RUTA'
                    ) AS ruta
                "),
                DB::raw("COALESCE(NULLIF(TRIM(MAX(r_nue.canal)), ''), NULLIF(TRIM(MAX(r_ant.canal)), ''), 'SIN CANAL') AS canal"),
                DB::raw("
                    COALESCE(
                        NULLIF(TRIM(MAX(p.dia_norm)), ''),
                        NULLIF(TRIM(MAX(p.dia)), ''),
                        NULLIF(TRIM(MAX(CASE WHEN a.valor_nuevo LIKE '%Dia: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_nuevo, 'Dia: ', -1), ' |', 1) ELSE NULL END))), ''),
                        NULLIF(TRIM(MAX(CASE WHEN a.valor_anterior LIKE '%Dia: %' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(a.valor_anterior, 'Dia: ', -1), ' |', 1) ELSE NULL END))), ''),
                        'SIN DIA'
                    ) AS dia
                "),
                DB::raw("COALESCE(MAX(p.direccion), 'Sin Dirección') AS direccion"),
                DB::raw("COALESCE(NULLIF(TRIM(MAX(p.vendedor)), ''), NULLIF(TRIM(MAX(r_nue.vendedor)), ''), NULLIF(TRIM(MAX(r_ant.vendedor)), ''), 'Sin Asignar') AS vendedor"),
                DB::raw("MAX(p.latitud) AS lat_actual"),
                DB::raw("MAX(p.longitud) AS lng_actual"),
                DB::raw("COUNT(*) AS total_cambios"),
                DB::raw("COALESCE(SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS pendientes"),
                DB::raw("COALESCE(SUM(CASE WHEN a.estado_auditoria = 'APROBADO' THEN 1 ELSE 0 END), 0) AS aprobados"),
                DB::raw("COALESCE(SUM(CASE WHEN a.estado_auditoria = 'RECHAZADO' THEN 1 ELSE 0 END), 0) AS rechazados"),
                DB::raw("COALESCE(SUM(CASE WHEN a.estado_auditoria = 'DESCARTADO' THEN 1 ELSE 0 END), 0) AS descartados"),
                DB::raw("MAX(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) AS ultima_fecha"),
            ])
            ->groupBy('a.cliente_id');

        // Filtro de estado sobre el grupo de clientes
        if ($estadoFiltro === 'CON_PENDIENTES') {
            $clientesGroupQuery->havingRaw("SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END) > 0");
        } elseif ($estadoFiltro === '100_RESUELTOS') {
            $clientesGroupQuery->havingRaw("SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END) = 0");
        } elseif ($estadoFiltro === 'SOLO_PENDIENTES') {
            $clientesGroupQuery->havingRaw("SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END) = COUNT(*)");
        }

        // Subquery para calcular métricas globales y paginación
        $countQuery = $conn->table(DB::raw("({$clientesGroupQuery->toSql()}) AS c"))
            ->mergeBindings($clientesGroupQuery);

        $totalesRow = $countQuery->selectRaw("
            COUNT(*) AS total_clientes,
            COALESCE(SUM(c.total_cambios), 0) AS total_eventos,
            COALESCE(SUM(CASE WHEN c.pendientes > 0 THEN 1 ELSE 0 END), 0) AS clientes_con_pendientes,
            COALESCE(SUM(CASE WHEN c.pendientes = 0 THEN 1 ELSE 0 END), 0) AS clientes_resueltos
        ")->first();

        $totalClientes = (int) ($totalesRow->total_clientes ?? 0);
        $totalEventos = (int) ($totalesRow->total_eventos ?? 0);
        $clientesConPendientes = (int) ($totalesRow->clientes_con_pendientes ?? 0);
        $clientesResueltos = (int) ($totalesRow->clientes_resueltos ?? 0);

        if ($totalClientes === 0) {
            return [
                'clientes' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => 1,
                'from' => 0,
                'to' => 0,
                'metricas' => [
                    'total_clientes' => 0,
                    'total_eventos' => 0,
                    'clientes_con_pendientes' => 0,
                    'clientes_resueltos' => 0,
                ],
            ];
        }

        // Paginación de clientes
        $clientesPaginados = $clientesGroupQuery
            ->orderByRaw("SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END) DESC")
            ->orderByDesc(DB::raw("MAX(COALESCE(a.fecha_resolucion, a.fecha_auditoria))"))
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $clienteIds = $clientesPaginados->pluck('cliente_id')->all();

        // Obtener todos los cambios individuales de los clientes de la página actual
        $cambiosQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->whereIn('a.cliente_id', $clienteIds)
            ->select([
                'a.id',
                'a.cliente_id',
                'a.archivo_origen',
                'a.tipo_evento',
                'a.campo_afectado',
                DB::raw("COALESCE(a.valor_anterior, '-') AS valor_anterior"),
                DB::raw("COALESCE(a.valor_nuevo, '-') AS valor_nuevo"),
                'a.fecha_auditoria',
                DB::raw("DATE_FORMAT(a.fecha_auditoria, '%Y-%m-%d %H:%i') AS fecha_auditoria_fmt"),
                'a.estado_auditoria',
                DB::raw("COALESCE(a.observaciones, '') AS observaciones"),
                DB::raw("COALESCE(a.auditado_por, '') AS auditado_por"),
                DB::raw("DATE_FORMAT(a.fecha_resolucion, '%Y-%m-%d %H:%i') AS fecha_resolucion"),
            ])
            ->orderByRaw("COALESCE(a.fecha_resolucion, a.fecha_auditoria) DESC")
            ->orderByDesc('a.id')
            ->get();

        $cambiosPorCliente = [];
        foreach ($cambiosQuery as $cambio) {
            $cambiosPorCliente[$cambio->cliente_id][] = [
                'id' => (int) $cambio->id,
                'cliente_id' => (int) $cambio->cliente_id,
                'archivo_origen' => (string) $cambio->archivo_origen,
                'tipo_evento' => (string) $cambio->tipo_evento,
                'campo_afectado' => (string) $cambio->campo_afectado,
                'valor_anterior' => (string) $cambio->valor_anterior,
                'valor_nuevo' => (string) $cambio->valor_nuevo,
                'fecha_auditoria' => (string) $cambio->fecha_auditoria,
                'fecha_auditoria_fmt' => (string) $cambio->fecha_auditoria_fmt,
                'estado_auditoria' => (string) $cambio->estado_auditoria,
                'observaciones' => (string) $cambio->observaciones,
                'auditado_por' => (string) $cambio->auditado_por,
                'fecha_resolucion' => (string) ($cambio->fecha_resolucion ?? ''),
            ];
        }

        // Armar resultado consolidado
        $clientesResultados = [];
        foreach ($clientesPaginados as $c) {
            $cid = (int) $c->cliente_id;
            $cambios = $cambiosPorCliente[$cid] ?? [];

            // Extraer tipos de eventos únicos para badges
            $tiposSet = [];
            foreach ($cambios as $ev) {
                $tiposSet[$ev['tipo_evento']] = true;
            }

            $clientesResultados[] = [
                'cliente_id' => $cid,
                'cliente' => (string) $c->cliente,
                'ruta' => (string) $c->ruta,
                'canal' => (string) $c->canal,
                'dia' => (string) $c->dia,
                'direccion' => (string) $c->direccion,
                'vendedor' => (string) $c->vendedor,
                'lat_actual' => $c->lat_actual !== null ? (float) $c->lat_actual : null,
                'lng_actual' => $c->lng_actual !== null ? (float) $c->lng_actual : null,
                'total_cambios' => (int) $c->total_cambios,
                'pendientes' => (int) $c->pendientes,
                'aprobados' => (int) $c->aprobados,
                'rechazados' => (int) $c->rechazados,
                'descartados' => (int) $c->descartados,
                'ultima_fecha' => (string) ($c->ultima_fecha ?? ''),
                'tipos_eventos' => array_keys($tiposSet),
                'cambios' => $cambios,
            ];
        }

        $lastPage = max(1, (int) ceil($totalClientes / $perPage));

        return [
            'clientes' => $clientesResultados,
            'total' => $totalClientes,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'from' => $totalClientes > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $totalClientes),
            'metricas' => [
                'total_clientes' => $totalClientes,
                'total_eventos' => $totalEventos,
                'clientes_con_pendientes' => $clientesConPendientes,
                'clientes_resueltos' => $clientesResueltos,
            ],
        ];
    }
}
