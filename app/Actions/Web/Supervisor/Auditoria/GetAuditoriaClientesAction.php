<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use Illuminate\Support\Facades\DB;

class GetAuditoriaClientesAction
{
    /**
     * Consulta agrupada de matriz de cambios por Ruta actual (pan_ruteo) y por Cliente.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function execute(array $params = []): array
    {
        $conn = DB::connection('supervisor');

        $canal = trim((string) ($params['canal'] ?? ''));

        // 1. Normalización de Rutas (uno, varios o todos)
        $rutasRaw = $params['rutas'] ?? $params['ruta'] ?? [];
        if (is_string($rutasRaw)) {
            $rutasRaw = ($rutasRaw === 'TODOS' || $rutasRaw === '') ? [] : explode(',', $rutasRaw);
        }
        $rutasValidas = array_values(array_filter(array_map('trim', (array) $rutasRaw), fn($r) => $r !== '' && strtoupper($r) !== 'TODOS'));

        // 2. Normalización de Estados (uno, varios o todos)
        $estadosRaw = $params['estados'] ?? $params['estado'] ?? $params['estado_filtro'] ?? [];
        if (is_string($estadosRaw)) {
            if ($estadosRaw === 'CON_PENDIENTES' || $estadosRaw === 'SOLO_PENDIENTES') {
                $estadosRaw = ['PENDIENTE'];
            } elseif ($estadosRaw === '100_RESUELTOS') {
                $estadosRaw = ['APROBADO', 'RECHAZADO', 'DESCARTADO'];
            } elseif ($estadosRaw === 'TODOS' || $estadosRaw === '') {
                $estadosRaw = [];
            } else {
                $estadosRaw = explode(',', $estadosRaw);
            }
        }
        $estadosValidos = array_values(array_filter(
            array_map(fn($e) => strtoupper(trim((string) $e)), (array) $estadosRaw),
            fn($e) => in_array($e, ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'DESCARTADO'], true)
        ));

        // 3. Normalización de Tipos de Cambio (uno, varios o todos)
        $tiposRaw = $params['tipos'] ?? $params['tipo'] ?? $params['categoria'] ?? [];
        if (is_string($tiposRaw)) {
            $tiposRaw = ($tiposRaw === 'TODOS' || $tiposRaw === '') ? [] : explode(',', $tiposRaw);
        }
        $tiposValidos = array_values(array_filter(
            array_map(fn($t) => strtoupper(trim((string) $t)), (array) $tiposRaw),
            fn($t) => $t !== '' && $t !== 'TODOS'
        ));

        $archivoOrigen = trim((string) ($params['archivo_origen'] ?? ''));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $busqueda = trim((string) ($params['busqueda'] ?? ''));

        // Subquery de pan_ruteo para consolidar datos vivos actuales de clientes
        $subPan = $conn->table('pan_ruteo')
            ->selectRaw('cliente_id, MAX(cliente) AS cliente, MAX(ruta) AS ruta, MAX(dia_norm) AS dia_norm, MAX(dia) AS dia, MAX(direccion) AS direccion, MAX(vendedor) AS vendedor, MAX(latitud) AS latitud, MAX(longitud) AS longitud, MAX(tipo_negocio) AS tipo_negocio, MAX(cluster_t) AS cluster_t')
            ->groupBy('cliente_id');

        // Subquery deduplicado de dim_rutas para obtener canal de la ruta viva
        $subRutas = $conn->table('dim_rutas')
            ->selectRaw("TRIM(UPPER(ruta)) AS ruta_clean, MAX(TRIM(canal)) AS canal, MAX(TRIM(vendedor)) AS vendedor")
            ->whereNotNull('ruta')
            ->whereRaw("TRIM(ruta) != ''")
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"));

        // Query base para auditoria_ruteo_cambios vinculado estrictamente a pan_ruteo actual
        $baseQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->leftJoinSub($subPan, 'p', 'a.cliente_id', '=', 'p.cliente_id')
            ->leftJoinSub($subRutas, 'r_pan', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(COALESCE(p.ruta, '')))"), '=', 'r_pan.ruta_clean');
            });

        // 1. Filtro de Canal (según la ruta viva actual del cliente)
        if ($canal !== '' && strtoupper($canal) !== 'TODOS') {
            $baseQuery->whereRaw('TRIM(r_pan.canal) = ?', [$canal]);
        }

        // 2. Filtro de Rutas (uno, varios o todos)
        if (!empty($rutasValidas)) {
            $rutasUpper = array_map('strtoupper', $rutasValidas);
            $baseQuery->whereIn(DB::raw("COALESCE(NULLIF(TRIM(UPPER(p.ruta)), ''), 'SIN RUTA')"), $rutasUpper);
        }

        // 3. Filtro de Estados de Auditoría (uno, varios o todos)
        if (!empty($estadosValidos) && count($estadosValidos) < 4) {
            $baseQuery->whereIn('a.estado_auditoria', $estadosValidos);
        }

        // 4. Filtro de Tipos de Cambio (uno, varios o todos los 12 tipos)
        if (!empty($tiposValidos) && count($tiposValidos) < 12) {
            $baseQuery->where(function ($q) use ($tiposValidos) {
                foreach ($tiposValidos as $tipo) {
                    $q->orWhere(function ($sub) use ($tipo) {
                        $this->aplicarFiltroTipo($sub, $tipo);
                    });
                }
            });
        }

        // 5. Archivo Origen
        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $baseQuery->where('a.archivo_origen', $archivoOrigen);
        }

        // 6. Rango de Fechas
        if ($fechaInicio !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) >= ?', [$fechaInicio]);
        }
        if ($fechaFin !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) <= ?', [$fechaFin]);
        }

        // 7. Buscador General
        if ($busqueda !== '') {
            $baseQuery->where(function ($q) use ($busqueda) {
                if (is_numeric($busqueda)) {
                    $q->where('a.cliente_id', (int) $busqueda)
                      ->orWhere('a.id', (int) $busqueda);
                }
                $term = "%{$busqueda}%";
                $q->orWhere('a.cliente', 'LIKE', $term)
                  ->orWhere('p.cliente', 'LIKE', $term)
                  ->orWhere('p.ruta', 'LIKE', $term)
                  ->orWhere('p.vendedor', 'LIKE', $term)
                  ->orWhere('p.direccion', 'LIKE', $term)
                  ->orWhere('a.valor_anterior', 'LIKE', $term)
                  ->orWhere('a.valor_nuevo', 'LIKE', $term)
                  ->orWhere('a.observaciones', 'LIKE', $term)
                  ->orWhere('a.auditado_por', 'LIKE', $term);
            });
        }

        // Agrupación por cliente_id tomando como ruta estricta la de pan_ruteo
        $clientesGroupQuery = (clone $baseQuery)
            ->select([
                'a.cliente_id',
                DB::raw("COALESCE(NULLIF(TRIM(MAX(p.cliente)), ''), NULLIF(TRIM(MAX(a.cliente)), ''), 'Sin Nombre') AS cliente"),
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(MAX(p.ruta))), ''), 'SIN RUTA') AS ruta"),
                DB::raw("COALESCE(NULLIF(TRIM(MAX(r_pan.canal)), ''), 'SIN CANAL') AS canal"),
                DB::raw("COALESCE(NULLIF(TRIM(MAX(p.dia_norm)), ''), NULLIF(TRIM(MAX(p.dia)), ''), 'SIN DIA') AS dia"),
                DB::raw("COALESCE(MAX(p.direccion), 'Sin Dirección') AS direccion"),
                DB::raw("COALESCE(NULLIF(TRIM(MAX(p.vendedor)), ''), NULLIF(TRIM(MAX(r_pan.vendedor)), ''), 'Sin Asignar') AS vendedor"),
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

        $clientesData = $clientesGroupQuery->get();

        if ($clientesData->isEmpty()) {
            return [
                'rutas' => [],
                'clientes' => [],
                'total_rutas' => 0,
                'total_clientes' => 0,
                'total_eventos' => 0,
                'metricas' => [
                    'total_rutas' => 0,
                    'total_clientes' => 0,
                    'total_eventos' => 0,
                    'clientes_con_pendientes' => 0,
                    'clientes_resueltos' => 0,
                ],
            ];
        }

        $clienteIds = $clientesData->pluck('cliente_id')->all();

        // Obtener historial completo de cambios individuales de estos clientes
        $cambiosQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->whereIn('a.cliente_id', $clienteIds);

        if (!empty($estadosValidos) && count($estadosValidos) < 4) {
            $cambiosQuery->whereIn('a.estado_auditoria', $estadosValidos);
        }

        if (!empty($tiposValidos) && count($tiposValidos) < 12) {
            $cambiosQuery->where(function ($q) use ($tiposValidos) {
                foreach ($tiposValidos as $tipo) {
                    $q->orWhere(function ($sub) use ($tipo) {
                        $this->aplicarFiltroTipo($sub, $tipo);
                    });
                }
            });
        }

        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $cambiosQuery->where('a.archivo_origen', $archivoOrigen);
        }

        if ($fechaInicio !== '') {
            $cambiosQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) >= ?', [$fechaInicio]);
        }
        if ($fechaFin !== '') {
            $cambiosQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) <= ?', [$fechaFin]);
        }

        $cambiosData = $cambiosQuery
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
        foreach ($cambiosData as $cambio) {
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

        // Armar estructura jerárquica: Ruta (pan_ruteo) -> Cliente -> Cambios
        $rutasMap = [];
        $clientesResultados = [];
        $totalEventos = 0;
        $clientesConPendientes = 0;
        $clientesResueltos = 0;

        foreach ($clientesData as $c) {
            $cid = (int) $c->cliente_id;
            $cambios = $cambiosPorCliente[$cid] ?? [];

            $tiposSet = [];
            foreach ($cambios as $ev) {
                $tiposSet[$ev['tipo_evento']] = true;
            }

            $pendientes = (int) $c->pendientes;
            $aprobados = (int) $c->aprobados;
            $rechazados = (int) $c->rechazados;
            $descartados = (int) $c->descartados;
            $totalCambios = (int) $c->total_cambios;

            $totalEventos += $totalCambios;
            if ($pendientes > 0) {
                $clientesConPendientes++;
            } else {
                $clientesResueltos++;
            }

            $rutaActual = trim((string) $c->ruta);
            if ($rutaActual === '' || $rutaActual === 'SIN RUTA') {
                $rutaKey = 'SIN RUTA';
            } else {
                $rutaKey = $rutaActual;
            }

            $clienteItem = [
                'cliente_id' => $cid,
                'cliente' => (string) $c->cliente,
                'ruta' => $rutaKey,
                'canal' => (string) $c->canal,
                'dia' => (string) $c->dia,
                'direccion' => (string) $c->direccion,
                'vendedor' => (string) $c->vendedor,
                'lat_actual' => $c->lat_actual !== null ? (float) $c->lat_actual : null,
                'lng_actual' => $c->lng_actual !== null ? (float) $c->lng_actual : null,
                'total_cambios' => $totalCambios,
                'pendientes' => $pendientes,
                'aprobados' => $aprobados,
                'rechazados' => $rechazados,
                'descartados' => $descartados,
                'ultima_fecha' => (string) ($c->ultima_fecha ?? ''),
                'tipos_eventos' => array_keys($tiposSet),
                'cambios' => $cambios,
            ];

            $clientesResultados[] = $clienteItem;

            if (!isset($rutasMap[$rutaKey])) {
                $rutasMap[$rutaKey] = [
                    'ruta' => $rutaKey,
                    'canal' => (string) $c->canal,
                    'total_clientes' => 0,
                    'total_cambios' => 0,
                    'pendientes' => 0,
                    'aprobados' => 0,
                    'rechazados' => 0,
                    'descartados' => 0,
                    'clientes' => [],
                ];
            }

            $rutasMap[$rutaKey]['total_clientes']++;
            $rutasMap[$rutaKey]['total_cambios'] += $totalCambios;
            $rutasMap[$rutaKey]['pendientes'] += $pendientes;
            $rutasMap[$rutaKey]['aprobados'] += $aprobados;
            $rutasMap[$rutaKey]['rechazados'] += $rechazados;
            $rutasMap[$rutaKey]['descartados'] += $descartados;
            $rutasMap[$rutaKey]['clientes'][] = $clienteItem;
        }

        // Ordenar clientes dentro de cada ruta: primero los con pendientes, luego por total de cambios descendente
        foreach ($rutasMap as &$rData) {
            usort($rData['clientes'], function ($a, $b) {
                if ($a['pendientes'] > 0 && $b['pendientes'] === 0) return -1;
                if ($a['pendientes'] === 0 && $b['pendientes'] > 0) return 1;
                if ($a['pendientes'] !== $b['pendientes']) {
                    return $b['pendientes'] <=> $a['pendientes'];
                }
                return $b['total_cambios'] <=> $a['total_cambios'];
            });
        }
        unset($rData);

        // Ordenar rutas:
        // Rutas con pendientes primero, luego alfabéticamente.
        // 'SIN RUTA' va estrictamente al final.
        uasort($rutasMap, function ($a, $b) {
            $rA = $a['ruta'];
            $rB = $b['ruta'];

            if ($rA === 'SIN RUTA' && $rB !== 'SIN RUTA') return 1;
            if ($rB === 'SIN RUTA' && $rA !== 'SIN RUTA') return -1;

            if ($a['pendientes'] > 0 && $b['pendientes'] === 0) return -1;
            if ($a['pendientes'] === 0 && $b['pendientes'] > 0) return 1;

            if ($a['pendientes'] !== $b['pendientes']) {
                return $b['pendientes'] <=> $a['pendientes'];
            }

            return strnatcasecmp($rA, $rB);
        });

        $rutasLista = array_values($rutasMap);
        $totalClientes = count($clientesResultados);
        $totalRutas = count($rutasLista);

        return [
            'rutas' => $rutasLista,
            'clientes' => $clientesResultados,
            'total_rutas' => $totalRutas,
            'total_clientes' => $totalClientes,
            'total_eventos' => $totalEventos,
            'metricas' => [
                'total_rutas' => $totalRutas,
                'total_clientes' => $totalClientes,
                'total_eventos' => $totalEventos,
                'clientes_con_pendientes' => $clientesConPendientes,
                'clientes_resueltos' => $clientesResueltos,
            ],
        ];
    }

    /**
     * Aplica la condición WHERE para un tipo específico de cambio de auditoría.
     */
    private function aplicarFiltroTipo($q, string $tipo): void
    {
        switch ($tipo) {
            case 'NUEVO':
                $q->where('a.campo_afectado', '!=', 'frecuencia_visita')
                  ->where(function ($sub) {
                      $sub->where('a.tipo_evento', 'NUEVO')
                          ->orWhere('a.campo_afectado', 'cliente_alta_total');
                  });
                break;
            case 'REACTIVADO':
                $q->where('a.tipo_evento', 'REACTIVADO');
                break;
            case 'ALTAS_REACTIVACIONES':
                $q->where('a.campo_afectado', '!=', 'frecuencia_visita')
                  ->where(function ($sub) {
                      $sub->whereIn('a.tipo_evento', ['NUEVO', 'REACTIVADO'])
                          ->orWhere('a.campo_afectado', 'cliente_alta_total');
                  });
                break;
            case 'ELIMINADO':
                $q->where('a.campo_afectado', '!=', 'frecuencia_visita')
                  ->where(function ($sub) {
                      $sub->where('a.tipo_evento', 'ELIMINADO')
                          ->orWhere('a.campo_afectado', 'cliente_baja_total');
                  });
                break;
            case 'INACTIVADO':
                $q->where('a.tipo_evento', 'INACTIVADO');
                break;
            case 'CAMBIO_CLIENTE':
                $q->where(function ($sub) {
                    $sub->where('a.campo_afectado', 'cliente')
                        ->orWhere('a.tipo_evento', 'MODIFICADO_CLIENTE');
                });
                break;
            case 'BAJAS_INACTIVADOS':
                $q->where('a.campo_afectado', '!=', 'frecuencia_visita')
                  ->where(function ($sub) {
                      $sub->whereIn('a.tipo_evento', ['ELIMINADO', 'INACTIVADO'])
                          ->orWhere('a.campo_afectado', 'cliente_baja_total');
                  });
                break;
            case 'MODIFICADO_RUTA':
            case 'CAMBIOS_RUTA':
                $q->where(function ($sub) {
                    $sub->where('a.tipo_evento', 'MODIFICADO_RUTA')
                        ->orWhere('a.campo_afectado', 'ruta');
                });
                break;
            case 'MODIFICADO_DIA':
            case 'CAMBIOS_DIA':
                $q->where(function ($sub) {
                    $sub->where('a.tipo_evento', 'MODIFICADO_DIA')
                        ->orWhere('a.campo_afectado', 'dia');
                });
                break;
            case 'CAMBIO_DE_CLUSTER':
                $q->where(function ($sub) {
                    $sub->where('a.tipo_evento', 'CAMBIO_DE_CLUSTER')
                        ->orWhereIn('a.campo_afectado', ['cluster', 'cluster_t', 'tipo_negocio']);
                });
                break;
            case 'MODIFICADO_VENDEDOR':
                $q->where(function ($sub) {
                    $sub->where('a.tipo_evento', 'MODIFICADO_VENDEDOR')
                        ->orWhere('a.campo_afectado', 'vendedor');
                });
                break;
            case 'MODIFICADO_COORDENADAS':
                $q->where(function ($sub) {
                    $sub->where('a.tipo_evento', 'MODIFICADO_COORDENADAS')
                        ->orWhere('a.campo_afectado', 'coordenadas');
                });
                break;
            case 'FRECUENCIA_VISITA':
                $q->where('a.campo_afectado', 'frecuencia_visita');
                break;
            case 'MODIFICACION_DATOS':
                $q->where('a.tipo_evento', 'MODIFICADO')
                  ->whereNotIn('a.campo_afectado', ['cliente', 'frecuencia_visita', 'ruta', 'dia', 'cluster', 'cluster_t', 'tipo_negocio', 'vendedor', 'coordenadas']);
                break;
        }
    }
}
