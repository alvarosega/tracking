<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use App\Services\Auditoria\EvaluadorReglasAuditoriaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAuditoriaRuteoAction
{
    public function __construct(
        protected EvaluadorReglasAuditoriaService $evaluadorReglas,
        protected EnriquecerFotosAction $enriquecerFotos
    ) {}

    /**
     * Consulta unificada y flexible para la auditoría de ruteo (Pendientes e Histórico).
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
        $estadoAuditoria = strtoupper(trim((string) ($params['estado_auditoria'] ?? 'PENDIENTE')));
        $categoria = strtoupper(trim((string) ($params['categoria'] ?? 'TODOS')));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $busqueda = trim((string) ($params['busqueda'] ?? ''));
        $limit = isset($params['limit']) && is_numeric($params['limit']) && (int) $params['limit'] > 0
            ? min((int) $params['limit'], 20000)
            : 100;
        $offset = max(0, (int) ($params['offset'] ?? 0));

        // Subquery de pan_ruteo para consolidar datos vivos actuales de clientes
        $subPan = $conn->table('pan_ruteo')
            ->selectRaw('cliente_id, MAX(cliente) AS cliente, MAX(ruta) AS ruta, MAX(dia_norm) AS dia_norm, MAX(dia) AS dia, MAX(direccion) AS direccion, MAX(vendedor) AS vendedor, MAX(latitud) AS latitud, MAX(longitud) AS longitud, MAX(tipo_negocio) AS tipo_negocio, MAX(cluster_t) AS cluster_t')
            ->groupBy('cliente_id');

        // Query base
        $baseQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->leftJoinSub($subPan, 'p', 'a.cliente_id', '=', 'p.cliente_id')
            ->leftJoin('dim_rutas as r', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(COALESCE(p.ruta, a.valor_anterior, '')))"), '=', DB::raw("TRIM(UPPER(r.ruta))"));
            })
            ->leftJoin('dim_rutas as r_nue', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(a.valor_nuevo))"), '=', DB::raw("TRIM(UPPER(r_nue.ruta))"));
            });

        // 1. Filtro de Estado de Auditoría
        if ($estadoAuditoria !== '' && $estadoAuditoria !== 'TODOS') {
            if ($estadoAuditoria === 'RESUELTOS') {
                $baseQuery->where('a.estado_auditoria', '!=', 'PENDIENTE');
            } else {
                $baseQuery->where('a.estado_auditoria', $estadoAuditoria);
            }
        }

        // 2. Filtro de Canal
        if ($canal !== '' && strtoupper($canal) !== 'TODOS') {
            $baseQuery->where(function ($q) use ($canal) {
                $q->whereRaw('TRIM(r.canal) = ?', [$canal])
                  ->orWhereRaw('TRIM(r_nue.canal) = ?', [$canal]);
            });
        }

        // 3. Filtro de Rutas
        $rutasValidas = array_filter(array_map('trim', $rutas));
        if (!empty($rutasValidas)) {
            $rutasUpper = array_map('strtoupper', $rutasValidas);
            $baseQuery->where(function ($q) use ($rutasUpper) {
                $q->whereIn(DB::raw("TRIM(UPPER(COALESCE(p.ruta, '')))"), $rutasUpper)
                  ->orWhereIn(DB::raw("TRIM(UPPER(COALESCE(a.valor_anterior, '')))"), $rutasUpper)
                  ->orWhereIn(DB::raw("TRIM(UPPER(COALESCE(a.valor_nuevo, '')))"), $rutasUpper);
            });
        }

        // 4. Archivo Origen
        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $baseQuery->where('a.archivo_origen', $archivoOrigen);
        }

        // 5. Rango de Fechas
        if ($fechaInicio !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) >= ?', [$fechaInicio]);
        }
        if ($fechaFin !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) <= ?', [$fechaFin]);
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

        // ── MÉTRICAS DE CATEGORÍA (Calculadas sobre el universo filtrado actual) ──
        $metricsRow = (clone $baseQuery)->selectRaw("
            COUNT(*) AS total_filtrado,
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS pendientes,
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'APROBADO' THEN 1 ELSE 0 END), 0) AS aprobados,
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'RECHAZADO' THEN 1 ELSE 0 END), 0) AS rechazados,
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'DESCARTADO' THEN 1 ELSE 0 END), 0) AS descartados,
            COALESCE(SUM(CASE WHEN a.tipo_evento IN ('NUEVO', 'REACTIVADO') OR a.campo_afectado = 'cliente_alta_total' THEN 1 ELSE 0 END), 0) AS cat_altas,
            COALESCE(SUM(CASE WHEN a.tipo_evento IN ('ELIMINADO', 'INACTIVADO') OR a.campo_afectado = 'cliente_baja_total' THEN 1 ELSE 0 END), 0) AS cat_bajas,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_RUTA' THEN 1 ELSE 0 END), 0) AS cat_ruta,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_DIA' THEN 1 ELSE 0 END), 0) AS cat_dia,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'CAMBIO_DE_CLUSTER' THEN 1 ELSE 0 END), 0) AS cat_cluster,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_VENDEDOR' THEN 1 ELSE 0 END), 0) AS cat_vendedor,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_COORDENADAS' THEN 1 ELSE 0 END), 0) AS cat_coordenadas,
            COALESCE(SUM(CASE WHEN a.campo_afectado = 'frecuencia_visita' THEN 1 ELSE 0 END), 0) AS cat_frecuencia,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO' AND a.campo_afectado != 'frecuencia_visita' THEN 1 ELSE 0 END), 0) AS cat_datos
        ")->first();

        $conteos = [
            'total' => (int) ($metricsRow->total_filtrado ?? 0),
            'pendientes' => (int) ($metricsRow->pendientes ?? 0),
            'aprobados' => (int) ($metricsRow->aprobados ?? 0),
            'rechazados' => (int) ($metricsRow->rechazados ?? 0),
            'descartados' => (int) ($metricsRow->descartados ?? 0),
            'categorias' => [
                'TODOS' => (int) ($metricsRow->total_filtrado ?? 0),
                'ALTAS_REACTIVACIONES' => (int) ($metricsRow->cat_altas ?? 0),
                'BAJAS_INACTIVADOS' => (int) ($metricsRow->cat_bajas ?? 0),
                'CAMBIOS_RUTA' => (int) ($metricsRow->cat_ruta ?? 0),
                'CAMBIOS_DIA' => (int) ($metricsRow->cat_dia ?? 0),
                'CAMBIO_DE_CLUSTER' => (int) ($metricsRow->cat_cluster ?? 0),
                'MODIFICADO_VENDEDOR' => (int) ($metricsRow->cat_vendedor ?? 0),
                'MODIFICADO_COORDENADAS' => (int) ($metricsRow->cat_coordenadas ?? 0),
                'FRECUENCIA_VISITA' => (int) ($metricsRow->cat_frecuencia ?? 0),
                'MODIFICACION_DATOS' => (int) ($metricsRow->cat_datos ?? 0),
            ],
        ];

        // 7. Aplicar filtro específico de categoría sobre la consulta de filas
        $dataQuery = clone $baseQuery;
        if ($categoria !== '' && $categoria !== 'TODOS') {
            switch ($categoria) {
                case 'ALTAS_REACTIVACIONES':
                    $dataQuery->where(function ($q) {
                        $q->whereIn('a.tipo_evento', ['NUEVO', 'REACTIVADO'])
                          ->orWhere('a.campo_afectado', 'cliente_alta_total');
                    });
                    break;
                case 'BAJAS_INACTIVADOS':
                    $dataQuery->where(function ($q) {
                        $q->whereIn('a.tipo_evento', ['ELIMINADO', 'INACTIVADO'])
                          ->orWhere('a.campo_afectado', 'cliente_baja_total');
                    });
                    break;
                case 'CAMBIOS_RUTA':
                    $dataQuery->where('a.tipo_evento', 'MODIFICADO_RUTA');
                    break;
                case 'CAMBIOS_DIA':
                    $dataQuery->where('a.tipo_evento', 'MODIFICADO_DIA');
                    break;
                case 'CAMBIO_DE_CLUSTER':
                    $dataQuery->where('a.tipo_evento', 'CAMBIO_DE_CLUSTER');
                    break;
                case 'MODIFICADO_VENDEDOR':
                    $dataQuery->where('a.tipo_evento', 'MODIFICADO_VENDEDOR');
                    break;
                case 'MODIFICADO_COORDENADAS':
                    $dataQuery->where('a.tipo_evento', 'MODIFICADO_COORDENADAS');
                    break;
                case 'FRECUENCIA_VISITA':
                    $dataQuery->where('a.campo_afectado', 'frecuencia_visita');
                    break;
                case 'MODIFICACION_DATOS':
                    $dataQuery->where('a.tipo_evento', 'MODIFICADO')
                              ->where('a.campo_afectado', '!=', 'frecuencia_visita');
                    break;
            }
        }

        $totalCategoria = $categoria === 'TODOS'
            ? $conteos['total']
            : ($conteos['categorias'][$categoria] ?? 0);

        // Selección de campos y ordenamiento
        $rows = $dataQuery->select([
            'a.id',
            'a.archivo_origen',
            'a.cliente_id',
            DB::raw("COALESCE(a.cliente, p.cliente, 'Sin Nombre') AS cliente"),
            'a.tipo_evento',
            'a.campo_afectado',
            DB::raw("COALESCE(a.valor_anterior, '-') AS valor_anterior"),
            DB::raw("COALESCE(a.valor_nuevo, '-') AS valor_nuevo"),
            'a.fecha_auditoria',
            DB::raw("DATE_FORMAT(a.fecha_auditoria, '%Y-%m-%d %H:%i') AS fecha_auditoria_fmt"),
            DB::raw("DATE(a.fecha_auditoria) AS fecha_auditoria_date"),
            'a.estado_auditoria',
            DB::raw("COALESCE(a.observaciones, '') AS observaciones"),
            DB::raw("COALESCE(a.auditado_por, '') AS auditado_por"),
            DB::raw("DATE_FORMAT(a.fecha_resolucion, '%Y-%m-%d %H:%i') AS fecha_resolucion"),
            DB::raw("COALESCE(TRIM(UPPER(p.ruta)), TRIM(UPPER(a.valor_anterior)), 'SIN RUTA') AS ruta"),
            DB::raw("COALESCE(TRIM(r.canal), TRIM(r_nue.canal), 'SIN CANAL') AS canal"),
            DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') AS dia"),
            DB::raw("COALESCE(p.direccion, 'Sin Dirección') AS direccion"),
            DB::raw("COALESCE(p.vendedor, r.vendedor, 'Sin Asignar') AS vendedor"),
            'p.latitud as lat_actual',
            'p.longitud as lng_actual',
        ])
        ->orderByRaw('COALESCE(a.fecha_resolucion, a.fecha_auditoria) DESC')
        ->orderByDesc('a.id')
        ->offset($offset)
        ->limit($limit)
        ->get();

        if ($rows->isEmpty()) {
            return [
                'registros' => [],
                'total' => $totalCategoria,
                'conteos' => $conteos,
            ];
        }

        // Cargar historial de ventas (5 meses) para clientes con eventos de baja / inactivación
        $bajaIds = [];
        $minFecha = null;
        foreach ($rows as $r) {
            $tipo = (string) $r->tipo_evento;
            $campo = (string) $r->campo_afectado;
            if (in_array($tipo, ['ELIMINADO', 'INACTIVADO'], true) || $campo === 'cliente_baja_total') {
                $cid = (int) $r->cliente_id;
                if ($cid > 0) {
                    $bajaIds[] = $cid;
                    if ($r->fecha_auditoria_date) {
                        if ($minFecha === null || $r->fecha_auditoria_date < $minFecha) {
                            $minFecha = $r->fecha_auditoria_date;
                        }
                    }
                }
            }
        }

        $bajaIds = array_values(array_unique($bajaIds));
        $ventasMap = [];
        if (!empty($bajaIds) && $minFecha) {
            try {
                $ventas = $conn->table('fact_ventas')
                    ->whereIn('cliente_id', $bajaIds)
                    ->whereDate('fecha_norm', '>=', Carbon::parse($minFecha)->subMonths(6)->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('revertida')
                          ->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
                    })
                    ->select([
                        'cliente_id',
                        DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m') AS anio_mes"),
                        DB::raw('SUM(monto_final) AS venta_mes'),
                    ])
                    ->groupBy('cliente_id', DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m')"))
                    ->get();

                foreach ($ventas as $v) {
                    $ventasMap[(int) $v->cliente_id][(string) $v->anio_mes] = (float) ($v->venta_mes ?? 0.0);
                }
            } catch (\Throwable $e) {
                // Silenciar si fact_ventas no está accesible en el momento
            }
        }

        // Formateo y clasificación amigable
        $resultados = [];
        foreach ($rows as $r) {
            $cid = (int) $r->cliente_id;
            $tipoEvento = (string) ($r->tipo_evento ?? '');
            $campoAfectado = (string) ($r->campo_afectado ?? '');
            $valorAnterior = (string) ($r->valor_anterior ?? '-');
            $valorNuevo = (string) ($r->valor_nuevo ?? '-');

            // Determinar categoría clave y etiqueta legible
            $catClave = 'MODIFICACION_DATOS';
            $etiqueta = 'Modificación de Datos';

            if (in_array($tipoEvento, ['NUEVO', 'REACTIVADO'], true) || $campoAfectado === 'cliente_alta_total') {
                $catClave = 'ALTAS_REACTIVACIONES';
                $etiqueta = $tipoEvento === 'REACTIVADO' ? 'Cliente Reactivado' : 'Alta de Cliente';
            } elseif (in_array($tipoEvento, ['ELIMINADO', 'INACTIVADO'], true) || $campoAfectado === 'cliente_baja_total') {
                $catClave = 'BAJAS_INACTIVADOS';
                $etiqueta = $tipoEvento === 'INACTIVADO' ? 'Cliente Inactivado' : 'Baja Total de Cartera';
            } elseif ($tipoEvento === 'MODIFICADO_RUTA') {
                $catClave = 'CAMBIOS_RUTA';
                $etiqueta = 'Cambio de Ruta';
            } elseif ($tipoEvento === 'MODIFICADO_DIA') {
                $catClave = 'CAMBIOS_DIA';
                $etiqueta = 'Cambio de Día';
            } elseif ($tipoEvento === 'CAMBIO_DE_CLUSTER') {
                $catClave = 'CAMBIO_DE_CLUSTER';
                $etiqueta = 'Cambio de Clúster';
            } elseif ($tipoEvento === 'MODIFICADO_VENDEDOR') {
                $catClave = 'MODIFICADO_VENDEDOR';
                $etiqueta = 'Cambio de Vendedor';
            } elseif ($tipoEvento === 'MODIFICADO_COORDENADAS') {
                $catClave = 'MODIFICADO_COORDENADAS';
                $etiqueta = 'Desplazamiento Coordenadas';
            } elseif ($campoAfectado === 'frecuencia_visita') {
                $catClave = 'FRECUENCIA_VISITA';
                $etiqueta = 'Variación Frecuencia Logística';
            }

            // Cálculo de ventas 5 meses para bajas
            $historialVentas = null;
            $evaluacionRiesgo = null;

            if ($catClave === 'BAJAS_INACTIVADOS') {
                $fBaja = $r->fecha_auditoria_date ? Carbon::parse($r->fecha_auditoria_date) : Carbon::now();
                $ventasCliente = $ventasMap[$cid] ?? [];
                $historial5m = [];
                $total5m = 0.0;

                $año = $fBaja->year;
                $mes = $fBaja->month;
                for ($i = 5; $i >= 1; $i--) {
                    $m = $mes - $i;
                    $a = $año;
                    while ($m <= 0) {
                        $m += 12;
                        $a -= 1;
                    }
                    $ym = sprintf('%04d-%02d', $a, $m);
                    $montoMes = round($ventasCliente[$ym] ?? 0.0, 2);
                    $total5m += $montoMes;
                    $historial5m[] = ['periodo' => $ym, 'monto' => $montoMes];
                }

                $historialVentas = [
                    'meses' => $historial5m,
                    'total_5m' => round($total5m, 2),
                    'promedio_mensual' => round($total5m / 5.0, 2),
                ];

                $evaluacionRiesgo = $this->evaluadorReglas->evaluarInactivacion([
                    'historico_ventas' => $historialVentas,
                ]);
            } elseif ($catClave === 'MODIFICADO_COORDENADAS') {
                // Parsear coordenadas para distancia si es posible
                $geoDistancia = null;
                if (str_contains($valorAnterior, ',') && str_contains($valorNuevo, ',')) {
                    $p1 = array_map('floatval', explode(',', $valorAnterior));
                    $p2 = array_map('floatval', explode(',', $valorNuevo));
                    if (count($p1) >= 2 && count($p2) >= 2) {
                        $geoDistancia = $this->evaluadorReglas->calcularDistanciaHaversineMetros($p1[0], $p1[1], $p2[0], $p2[1]);
                    }
                }
                if ($geoDistancia !== null) {
                    $evaluacionRiesgo = [
                        'distancia_metros' => $geoDistancia,
                        'nivel_riesgo' => $geoDistancia > 300 ? 'ALTO' : ($geoDistancia > 50 ? 'MEDIO' : 'BAJO'),
                        'mensaje' => "Desplazamiento geográfico estimado: {$geoDistancia} metros",
                    ];
                }
            }

            $item = [
                'id' => (int) $r->id,
                'archivo_origen' => (string) ($r->archivo_origen ?? ''),
                'cliente_id' => $cid,
                'cliente' => (string) ($r->cliente ?? ''),
                'tipo_evento' => $tipoEvento,
                'campo_afectado' => $campoAfectado,
                'categoria_clave' => $catClave,
                'etiqueta_evento' => $etiqueta,
                'valor_anterior' => $valorAnterior,
                'valor_nuevo' => $valorNuevo,
                'fecha_auditoria' => (string) ($r->fecha_auditoria_fmt ?? ''),
                'estado_auditoria' => (string) ($r->estado_auditoria ?? 'PENDIENTE'),
                'observaciones' => (string) ($r->observaciones ?? ''),
                'auditado_por' => (string) ($r->auditado_por ?? ''),
                'fecha_resolucion' => (string) ($r->fecha_resolucion ?? ''),
                'ruta' => (string) ($r->ruta ?? ''),
                'canal' => (string) ($r->canal ?? ''),
                'dia' => (string) ($r->dia ?? ''),
                'direccion' => (string) ($r->direccion ?? ''),
                'vendedor' => (string) ($r->vendedor ?? ''),
                'historico_ventas' => $historialVentas,
                'evaluacion_riesgo' => $evaluacionRiesgo,
            ];

            $resultados[] = $item;
        }

        // Enriquecer con evidencias fotográficas
        $resultados = $this->enriquecerFotos->execute($resultados, 'cliente_id');

        return [
            'registros' => $resultados,
            'total' => $totalCategoria,
            'conteos' => $conteos,
        ];
    }
}

