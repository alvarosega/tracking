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
            COALESCE(SUM(CASE WHEN a.campo_afectado != 'frecuencia_visita' AND (a.tipo_evento IN ('NUEVO', 'REACTIVADO') OR a.campo_afectado = 'cliente_alta_total') THEN 1 ELSE 0 END), 0) AS cat_altas,
            COALESCE(SUM(CASE WHEN a.campo_afectado != 'frecuencia_visita' AND (a.tipo_evento IN ('ELIMINADO', 'INACTIVADO') OR a.campo_afectado = 'cliente_baja_total') THEN 1 ELSE 0 END), 0) AS cat_bajas,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_RUTA' OR a.campo_afectado = 'ruta' THEN 1 ELSE 0 END), 0) AS cat_ruta,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_DIA' OR a.campo_afectado = 'dia' THEN 1 ELSE 0 END), 0) AS cat_dia,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'CAMBIO_DE_CLUSTER' OR a.campo_afectado IN ('cluster', 'cluster_t', 'tipo_negocio') THEN 1 ELSE 0 END), 0) AS cat_cluster,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_VENDEDOR' OR a.campo_afectado = 'vendedor' THEN 1 ELSE 0 END), 0) AS cat_vendedor,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO_COORDENADAS' OR a.campo_afectado = 'coordenadas' THEN 1 ELSE 0 END), 0) AS cat_coordenadas,
            COALESCE(SUM(CASE WHEN a.campo_afectado = 'frecuencia_visita' THEN 1 ELSE 0 END), 0) AS cat_frecuencia,
            COALESCE(SUM(CASE WHEN a.tipo_evento = 'MODIFICADO' AND a.campo_afectado NOT IN ('frecuencia_visita', 'ruta', 'dia', 'cluster', 'cluster_t', 'tipo_negocio', 'vendedor', 'coordenadas') THEN 1 ELSE 0 END), 0) AS cat_datos
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
                    $dataQuery->where('a.campo_afectado', '!=', 'frecuencia_visita')
                              ->where(function ($q) {
                                  $q->whereIn('a.tipo_evento', ['NUEVO', 'REACTIVADO'])
                                    ->orWhere('a.campo_afectado', 'cliente_alta_total');
                              });
                    break;
                case 'BAJAS_INACTIVADOS':
                    $dataQuery->where('a.campo_afectado', '!=', 'frecuencia_visita')
                              ->where(function ($q) {
                                  $q->whereIn('a.tipo_evento', ['ELIMINADO', 'INACTIVADO'])
                                    ->orWhere('a.campo_afectado', 'cliente_baja_total');
                              });
                    break;
                case 'CAMBIOS_RUTA':
                    $dataQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_RUTA')
                          ->orWhere('a.campo_afectado', 'ruta');
                    });
                    break;
                case 'CAMBIOS_DIA':
                    $dataQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_DIA')
                          ->orWhere('a.campo_afectado', 'dia');
                    });
                    break;
                case 'CAMBIO_DE_CLUSTER':
                    $dataQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'CAMBIO_DE_CLUSTER')
                          ->orWhereIn('a.campo_afectado', ['cluster', 'cluster_t', 'tipo_negocio']);
                    });
                    break;
                case 'MODIFICADO_VENDEDOR':
                    $dataQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_VENDEDOR')
                          ->orWhere('a.campo_afectado', 'vendedor');
                    });
                    break;
                case 'MODIFICADO_COORDENADAS':
                    $dataQuery->where(function ($q) {
                        $q->where('a.tipo_evento', 'MODIFICADO_COORDENADAS')
                          ->orWhere('a.campo_afectado', 'coordenadas');
                    });
                    break;
                case 'FRECUENCIA_VISITA':
                    $dataQuery->where('a.campo_afectado', 'frecuencia_visita');
                    break;
                case 'MODIFICACION_DATOS':
                    $dataQuery->where('a.tipo_evento', 'MODIFICADO')
                              ->whereNotIn('a.campo_afectado', ['frecuencia_visita', 'ruta', 'dia', 'cluster', 'cluster_t', 'tipo_negocio', 'vendedor', 'coordenadas']);
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
            if ($campo !== 'frecuencia_visita' && (in_array($tipo, ['ELIMINADO', 'INACTIVADO'], true) || $campo === 'cliente_baja_total')) {
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

        // Formateo y clasificación enriquecida
        $resultados = [];
        foreach ($rows as $r) {
            $cid = (int) $r->cliente_id;
            $tipoEvento = (string) ($r->tipo_evento ?? '');
            $campoAfectado = (string) ($r->campo_afectado ?? '');
            $valorAnterior = (string) ($r->valor_anterior ?? '-');
            $valorNuevo = (string) ($r->valor_nuevo ?? '-');

            // Determinar categoría clave y etiqueta legible (evaluando con prioridad frecuencia_visita)
            $catClave = 'MODIFICACION_DATOS';
            $etiqueta = 'Modificación de Datos';

            if ($campoAfectado === 'frecuencia_visita') {
                $catClave = 'FRECUENCIA_VISITA';
                $etiqueta = $tipoEvento === 'ELIMINADO' ? 'Reducción Frecuencia' : 'Ampliación Frecuencia';
            } elseif ($campoAfectado === 'cliente_alta_total' || in_array($tipoEvento, ['NUEVO', 'REACTIVADO'], true)) {
                $catClave = 'ALTAS_REACTIVACIONES';
                $etiqueta = $tipoEvento === 'REACTIVADO' ? 'Cliente Reactivado' : 'Alta de Cliente';
            } elseif ($campoAfectado === 'cliente_baja_total' || in_array($tipoEvento, ['ELIMINADO', 'INACTIVADO'], true)) {
                $catClave = 'BAJAS_INACTIVADOS';
                $etiqueta = $tipoEvento === 'INACTIVADO' ? 'Cliente Inactivado' : 'Baja de Cartera';
            } elseif ($tipoEvento === 'MODIFICADO_RUTA' || $campoAfectado === 'ruta') {
                $catClave = 'CAMBIOS_RUTA';
                $etiqueta = 'Cambio de Ruta';
            } elseif ($tipoEvento === 'MODIFICADO_DIA' || $campoAfectado === 'dia') {
                $catClave = 'CAMBIOS_DIA';
                $etiqueta = 'Cambio de Día';
            } elseif ($tipoEvento === 'CAMBIO_DE_CLUSTER' || in_array($campoAfectado, ['cluster', 'cluster_t', 'tipo_negocio'], true)) {
                $catClave = 'CAMBIO_DE_CLUSTER';
                $etiqueta = 'Cambio de Clúster';
            } elseif ($tipoEvento === 'MODIFICADO_VENDEDOR' || $campoAfectado === 'vendedor') {
                $catClave = 'MODIFICADO_VENDEDOR';
                $etiqueta = 'Cambio de Vendedor';
            } elseif ($tipoEvento === 'MODIFICADO_COORDENADAS' || $campoAfectado === 'coordenadas') {
                $catClave = 'MODIFICADO_COORDENADAS';
                $etiqueta = 'Desplazamiento GPS';
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

            // Generación de etiquetas claras, antes/después y motivo estructurado
            $campoLabel = $this->formatCampoLabel($campoAfectado, $tipoEvento);
            $antesDespues = $this->resolverAntesYDespues($r, $catClave);
            $porQueCambio = $this->resolverPorQueCambio($r, $catClave, $historialVentas, $evaluacionRiesgo);

            $item = [
                'id' => (int) $r->id,
                'archivo_origen' => (string) ($r->archivo_origen ?? ''),
                'cliente_id' => $cid,
                'cliente' => (string) ($r->cliente ?? ''),
                'tipo_evento' => $tipoEvento,
                'campo_afectado' => $campoAfectado,
                'campo_label' => $campoLabel,
                'categoria_clave' => $catClave,
                'etiqueta_evento' => $etiqueta,
                'valor_anterior' => $valorAnterior,
                'valor_nuevo' => $valorNuevo,
                'antes_fmt' => $antesDespues['antes'],
                'despues_fmt' => $antesDespues['despues'],
                'motivo_cambio' => $porQueCambio,
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
                'lat_actual' => $r->lat_actual ? (float) $r->lat_actual : null,
                'lng_actual' => $r->lng_actual ? (float) $r->lng_actual : null,
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

    /**
     * Mapea el nombre técnico de base de datos a una etiqueta de negocio amigable.
     */
    public function formatCampoLabel(string $campo, string $tipoEvento): string
    {
        $campo = strtolower(trim($campo));
        return match ($campo) {
            'cliente_alta_total' => 'Alta en Cartera',
            'cliente_baja_total' => 'Baja de Cartera',
            'ruta' => 'Ruta de Atención',
            'dia' => 'Día de Visita Semanal',
            'vendedor' => 'Vendedor / Preventista',
            'coordenadas' => 'Ubicación GPS (Coordenadas)',
            'cluster' => 'Clúster y Tipo de Negocio',
            'cluster_t' => 'Clúster Comercial',
            'tipo_negocio' => 'Tipo de Negocio',
            'frecuencia_visita' => 'Frecuencia Semanal de Visita',
            'precio' => 'Lista de Precios / Tarifa',
            'estado' => $tipoEvento === 'REACTIVADO' ? 'Reactivación de Estado' : 'Inactivación de Estado',
            'cliente' => 'Nombre Comercial / Razón Social',
            'celular' => 'Teléfono Celular',
            'telefono' => 'Teléfono Fijo',
            'direccion' => 'Dirección de Entrega',
            'referencia' => 'Referencia de Ubicación',
            'nombre_factura' => 'Razón Social para Facturación',
            'ci' => 'Cédula de Identidad / NIT',
            'tipo_pago' => 'Condición de Pago',
            'contacto' => 'Persona de Contacto',
            'territorio' => 'Territorio Comercial',
            default => ucwords(str_replace('_', ' ', $campo ?: 'Modificación')),
        };
    }

    /**
     * Resuelve y da formato legible al valor anterior y valor nuevo propuesto.
     *
     * @return array{antes: string, despues: string}
     */
    public function resolverAntesYDespues(object $r, string $catClave): array
    {
        $ant = trim((string) ($r->valor_anterior ?? ''));
        $nue = trim((string) ($r->valor_nuevo ?? ''));
        $tipo = (string) ($r->tipo_evento ?? '');
        $campo = (string) ($r->campo_afectado ?? '');

        $antesFmt = $ant !== '' && $ant !== '-' ? $ant : 'Sin dato previo';
        $despuesFmt = $nue !== '' && $nue !== '-' ? $nue : 'Sin dato nuevo';

        switch ($catClave) {
            case 'ALTAS_REACTIVACIONES':
                if ($tipo === 'REACTIVADO') {
                    $antesFmt = 'Estado previo: Inactivo / Fuera de Cartera';
                    $despuesFmt = 'Reactivado en Cartera: ' . ($nue ?: 'Activo');
                } else {
                    $antesFmt = 'Sin registro previo en padrón activo';
                    $despuesFmt = $nue !== '' && $nue !== '-' ? $nue : "Ruta: {$r->ruta} | Día: {$r->dia}";
                }
                break;

            case 'BAJAS_INACTIVADOS':
                $rutaInfo = trim(($r->ruta ?? '') . ' (' . ($r->dia ?? '') . ')');
                $antesFmt = 'Activo en Cartera' . ($rutaInfo !== '()' && $rutaInfo !== '' ? ' • ' . $rutaInfo : '');
                if ($ant !== '' && $ant !== '-' && $ant !== 'Activo') {
                    $antesFmt .= " [{$ant}]";
                }
                $despuesFmt = $tipo === 'INACTIVADO' ? 'Inactivación Solicitada (Estado: Inactivo)' : 'Baja Definitiva de Cartera';
                break;

            case 'CAMBIOS_RUTA':
                $antesFmt = 'Ruta previa: ' . ($ant ?: 'Sin ruta');
                $despuesFmt = 'Ruta nueva: ' . ($nue ?: 'Sin asignar');
                break;

            case 'CAMBIOS_DIA':
                $antesFmt = 'Día previo: ' . ($ant ?: 'Sin día');
                $despuesFmt = 'Día nuevo: ' . ($nue ?: 'Sin asignar');
                break;

            case 'CAMBIO_DE_CLUSTER':
                $antesFmt = str_ireplace(['tipo_negocio:', 'cluster_t:'], ['Tipo Negocio:', 'Clúster:'], $ant);
                $despuesFmt = str_ireplace(['tipo_negocio:', 'cluster_t:'], ['Tipo Negocio:', 'Clúster:'], $nue);
                break;

            case 'MODIFICADO_VENDEDOR':
                $antesFmt = 'Vendedor previo: ' . ($ant ?: 'Sin asignar');
                $despuesFmt = 'Vendedor nuevo: ' . ($nue ?: 'Sin asignar');
                break;

            case 'MODIFICADO_COORDENADAS':
                if (str_contains($ant, ',')) {
                    $parts = explode(',', $ant);
                    $antesFmt = sprintf('Lat: %s, Lng: %s', trim($parts[0] ?? ''), trim($parts[1] ?? ''));
                }
                if (str_contains($nue, ',')) {
                    $partsN = explode(',', $nue);
                    $despuesFmt = sprintf('Lat: %s, Lng: %s', trim($partsN[0] ?? ''), trim($partsN[1] ?? ''));
                }
                break;

            case 'FRECUENCIA_VISITA':
                if ($tipo === 'NUEVO') {
                    $antesFmt = 'Frecuencia previa estándar (1 visita/semana)';
                    $despuesFmt = '+1 Visita agregada: ' . ($nue ?: 'Multivisita');
                } else {
                    $antesFmt = 'Visita activa previa: ' . ($ant ?: 'Multivisita');
                    $despuesFmt = 'Visita suprimida (Retorna a Monovisita)';
                }
                break;

            case 'MODIFICACION_DATOS':
                if ($campo === 'precio') {
                    $antesFmt = 'Tarifa anterior: ' . ($ant ?: 'Sin tarifa');
                    $despuesFmt = 'Tarifa nueva: ' . ($nue ?: 'Sin tarifa');
                }
                break;
        }

        return [
            'antes' => $antesFmt,
            'despues' => $despuesFmt,
        ];
    }

    /**
     * Resuelve la justificación de negocio ("Por qué cambió") y su impacto comercial o logístico.
     *
     * @return array{motivo: string, impacto: string, observacion_origen: string, archivo_origen: string, nivel: string}
     */
    public function resolverPorQueCambio(
        object $r,
        string $catClave,
        ?array $historialVentas,
        ?array $evaluacionRiesgo
    ): array {
        $obs = trim((string) ($r->observaciones ?? ''));
        $archivo = trim((string) ($r->archivo_origen ?? ''));
        $tipo = (string) ($r->tipo_evento ?? '');
        $campo = (string) ($r->campo_afectado ?? '');

        $motivoCausa = '';
        $contextoImpacto = '';
        $nivel = 'INFO'; // INFO, WARNING, DANGER, SUCCESS

        switch ($catClave) {
            case 'BAJAS_INACTIVADOS':
                $nivel = 'WARNING';
                $motivoCausa = $tipo === 'INACTIVADO'
                    ? 'Solicitud de inactivación en cartera comercial.'
                    : 'Punto de venta suprimido de la base de ruteo.';

                if ($historialVentas && ($historialVentas['total_5m'] ?? 0) > 0) {
                    $prom = number_format((float) ($historialVentas['promedio_mensual'] ?? 0), 2, '.', ',');
                    $tot = number_format((float) ($historialVentas['total_5m'] ?? 0), 2, '.', ',');
                    $contextoImpacto = "Impacto comercial en riesgo: Promedio de Bs {$prom}/mes (Total 5 meses: Bs {$tot}).";
                    if (($historialVentas['promedio_mensual'] ?? 0) > 2000) {
                        $nivel = 'DANGER';
                    }
                } else {
                    $contextoImpacto = 'Sin facturación registrada en los últimos 5 meses (Riesgo comercial bajo).';
                }
                break;

            case 'ALTAS_REACTIVACIONES':
                $nivel = 'SUCCESS';
                if ($tipo === 'REACTIVADO') {
                    $motivoCausa = 'Cliente reactivado para reanudar visitas y toma de pedidos comerciales.';
                    $contextoImpacto = 'Reincorporación de punto de venta previo.';
                } else {
                    $motivoCausa = 'Nuevo punto de venta censado e incorporado a la cartera comercial activa.';
                    $contextoImpacto = 'Ampliación de cobertura en ruta.';
                }
                break;

            case 'CAMBIOS_RUTA':
                $nivel = 'INFO';
                $motivoCausa = 'Rebalanceo de sectorización comercial o reasignación entre rutas de preventa.';
                $contextoImpacto = "Transferencia de ruta: {$r->valor_anterior} → {$r->valor_nuevo}.";
                break;

            case 'CAMBIOS_DIA':
                $nivel = 'INFO';
                $motivoCausa = 'Reprogramación logística del día de visita para optimizar la secuencia y entrega.';
                $contextoImpacto = "Cambio de día de atención: {$r->valor_anterior} → {$r->valor_nuevo}.";
                break;

            case 'MODIFICADO_VENDEDOR':
                $nivel = 'INFO';
                $motivoCausa = 'Transferencia de cartera de clientes o sustitución de preventista asignado.';
                $contextoImpacto = 'Reasignación de preventista responsable del cliente.';
                break;

            case 'MODIFICADO_COORDENADAS':
                $distM = $evaluacionRiesgo['distancia_metros'] ?? null;
                if ($distM !== null) {
                    $motivoCausa = "Actualización de coordenadas GPS. Desplazamiento detectado de {$distM} metros.";
                    if ($distM > 300) {
                        $nivel = 'DANGER';
                        $contextoImpacto = 'Alerta: Desplazamiento significativo (> 300m). Verificar posible cambio de local o captura errónea.';
                    } elseif ($distM > 50) {
                        $nivel = 'WARNING';
                        $contextoImpacto = 'Desplazamiento moderado. Posible ajuste de precisión en punto de venta.';
                    } else {
                        $nivel = 'INFO';
                        $contextoImpacto = 'Ajuste fino de precisión GPS (< 50m).';
                    }
                } else {
                    $motivoCausa = 'Actualización de geolocalización satelital del punto de venta.';
                    $contextoImpacto = 'Ajuste de coordenadas geográficas.';
                }
                break;

            case 'FRECUENCIA_VISITA':
                $nivel = 'INFO';
                if ($tipo === 'NUEVO') {
                    $motivoCausa = 'Adición de frecuencia logística: ampliación a multivisita semanal.';
                    $contextoImpacto = 'Incremento de días de visita para atender alta rotación o volumen.';
                } else {
                    $motivoCausa = 'Reducción de frecuencia logística: cliente consolidado en monovisita.';
                    $contextoImpacto = 'Supresión de segundo día de atención por baja rotación.';
                }
                break;

            case 'CAMBIO_DE_CLUSTER':
                $nivel = 'INFO';
                $motivoCausa = 'Recategorización comercial o tipología de negocio en padrón maestro.';
                $contextoImpacto = 'Ajuste de tipología o clúster de asignación comercial.';
                break;

            case 'MODIFICACION_DATOS':
            default:
                $nivel = 'INFO';
                if ($campo === 'precio') {
                    $motivoCausa = 'Actualización de lista tarifaria asignada al cliente en base de ruteo.';
                    $contextoImpacto = "Cambio de tarifa: {$r->valor_anterior} → {$r->valor_nuevo}.";
                } elseif (in_array($campo, ['celular', 'telefono'], true)) {
                    $motivoCausa = 'Actualización de datos telefónicos de contacto comercial.';
                    $contextoImpacto = 'Saneamiento de contacto.';
                } elseif (in_array($campo, ['direccion', 'referencia'], true)) {
                    $motivoCausa = 'Actualización de dirección física o referencia de entrega para logística.';
                    $contextoImpacto = 'Facilidad de despacho y ubicación en terreno.';
                } elseif (in_array($campo, ['nombre_factura', 'ci'], true)) {
                    $motivoCausa = 'Saneamiento de datos fiscales para emisión correcta de facturación.';
                    $contextoImpacto = 'Cumplimiento y consistencia tributaria.';
                } else {
                    $motivoCausa = "Actualización del atributo '{$campo}' en padrón maestro.";
                    $contextoImpacto = $obs ?: 'Modificación registrada en la importación de ruteo.';
                }
                break;
        }

        $obsLimpia = '';
        if ($obs !== '' && !str_starts_with($obs, 'Actualización del atributo') && !str_starts_with($obs, 'Snapshot inicial')) {
            $obsLimpia = $obs;
        }

        return [
            'motivo' => $motivoCausa,
            'impacto' => $contextoImpacto,
            'observacion_origen' => $obsLimpia,
            'archivo_origen' => $archivo,
            'nivel' => $nivel,
        ];
    }
}
