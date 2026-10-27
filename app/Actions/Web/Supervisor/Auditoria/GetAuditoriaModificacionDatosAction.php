<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use App\Services\Auditoria\EvaluadorReglasAuditoriaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAuditoriaModificacionDatosAction
{
    public function __construct(
        protected EvaluadorReglasAuditoriaService $evaluadorReglas,
        protected EnriquecerFotosAction $enriquecerFotos
    ) {}

    /**
     * Consulta modificaciones de datos de clientes con histórico de ventas.
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
        $estadoAuditoria = strtoupper(trim((string) ($params['estado_auditoria'] ?? '')));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $limit = isset($params['limit']) && is_numeric($params['limit']) && (int) $params['limit'] > 0
            ? min((int) $params['limit'], 20000)
            : null;
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $subPan = $conn->table('pan_ruteo')
            ->selectRaw('cliente_id, MAX(cliente) AS cliente, MAX(ruta) AS ruta, MAX(dia_norm) AS dia_norm, MAX(dia) AS dia, MAX(direccion) AS direccion, MAX(latitud) AS latitud, MAX(longitud) AS longitud')
            ->groupBy('cliente_id');

        $baseQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->leftJoinSub($subPan, 'p', 'a.cliente_id', '=', 'p.cliente_id')
            ->leftJoin('dim_rutas as r', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(p.ruta))"), '=', DB::raw("TRIM(UPPER(r.ruta))"));
            })
            ->whereIn('a.tipo_evento', ['MODIFICADO_DATOS', 'MODIFICADO_VENDEDOR']);

        if ($canal !== '' && strtoupper($canal) !== 'TODOS') {
            $baseQuery->whereRaw('TRIM(r.canal) = ?', [$canal]);
        }

        $rutasValidas = array_filter(array_map('trim', $rutas));
        if (!empty($rutasValidas)) {
            $rutasUpper = array_map('strtoupper', $rutasValidas);
            $baseQuery->whereIn(DB::raw("TRIM(UPPER(p.ruta))"), $rutasUpper);
        }

        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $baseQuery->where('a.archivo_origen', $archivoOrigen);
        }

        if ($fechaInicio !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) >= ?', [$fechaInicio]);
        }

        if ($fechaFin !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) <= ?', [$fechaFin]);
        }

        // 1. Métricas Globales
        $metricsRow = (clone $baseQuery)->selectRaw("
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'PENDIENTE' THEN 1 ELSE 0 END), 0) AS pendientes,
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'APROBADO' THEN 1 ELSE 0 END), 0) AS aprobados,
            COALESCE(SUM(CASE WHEN a.estado_auditoria = 'RECHAZADO' THEN 1 ELSE 0 END), 0) AS rechazados,
            COUNT(*) AS total_general
        ")->first();

        $metricas = [
            'pendientes' => (int) ($metricsRow->pendientes ?? 0),
            'aprobados' => (int) ($metricsRow->aprobados ?? 0),
            'rechazados' => (int) ($metricsRow->rechazados ?? 0),
            'total_general' => (int) ($metricsRow->total_general ?? 0),
        ];

        if ($estadoAuditoria === 'PENDIENTE') {
            $totalFiltrados = $metricas['pendientes'];
        } elseif ($estadoAuditoria === 'APROBADO') {
            $totalFiltrados = $metricas['aprobados'];
        } elseif ($estadoAuditoria === 'RECHAZADO') {
            $totalFiltrados = $metricas['rechazados'];
        } else {
            $totalFiltrados = $metricas['total_general'];
        }

        // 2. Consulta de Filas
        $dataQuery = clone $baseQuery;
        if ($estadoAuditoria !== '' && $estadoAuditoria !== 'TODOS') {
            $dataQuery->where('a.estado_auditoria', $estadoAuditoria);
        }

        $dataQuery->select([
            'a.id',
            'a.archivo_origen',
            'a.cliente_id',
            DB::raw("COALESCE(a.cliente, p.cliente, 'Sin Nombre') AS cliente"),
            'a.campo_afectado',
            DB::raw("COALESCE(a.valor_anterior, '') AS valor_anterior"),
            DB::raw("COALESCE(a.valor_nuevo, '') AS valor_nuevo"),
            'a.fecha_auditoria',
            DB::raw("DATE_FORMAT(a.fecha_auditoria, '%Y-%m-%d %H:%i') AS fecha_auditoria_fmt"),
            DB::raw("DATE(a.fecha_auditoria) AS fecha_evento_date"),
            'a.estado_auditoria',
            DB::raw("COALESCE(a.observaciones, '') AS observaciones"),
            DB::raw("COALESCE(a.auditado_por, '') AS auditado_por"),
            DB::raw("DATE_FORMAT(a.fecha_resolucion, '%Y-%m-%d %H:%i') AS fecha_resolucion"),
            DB::raw("COALESCE(TRIM(UPPER(p.ruta)), 'SIN RUTA') AS ruta"),
            DB::raw("COALESCE(TRIM(r.canal), 'SIN CANAL') AS canal"),
            DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') AS dia"),
            DB::raw("COALESCE(p.direccion, 'Sin Dirección') AS direccion_actual"),
            DB::raw("COALESCE(p.latitud, 0.0) AS latitud_actual"),
            DB::raw("COALESCE(p.longitud, 0.0) AS longitud_actual"),
        ])
        ->orderByRaw('COALESCE(a.fecha_resolucion, a.fecha_auditoria) DESC')
        ->orderByDesc('a.id');

        if ($limit !== null) {
            $dataQuery->offset($offset)->limit($limit);
        }

        $cambiosRaw = $dataQuery->get();
        if ($cambiosRaw->isEmpty()) {
            return [
                'registros' => [],
                'total' => $totalFiltrados,
                'metricas' => $metricas,
            ];
        }

        // Agrupar filas individuales por clave: (cliente_id, fecha_evento_date)
        $grupos = [];
        foreach ($cambiosRaw as $r) {
            $cid = (int) $r->cliente_id;
            $fec = (string) $r->fecha_evento_date;
            $clave = "{$cid}_{$fec}";

            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'ids' => [],
                    'archivo_origen' => (string) ($r->archivo_origen ?? ''),
                    'cliente_id' => $cid,
                    'cliente' => (string) ($r->cliente ?? ''),
                    'ruta' => (string) ($r->ruta ?? ''),
                    'canal' => (string) ($r->canal ?? ''),
                    'dia' => (string) ($r->dia ?? ''),
                    'fecha_auditoria' => (string) ($r->fecha_auditoria_fmt ?? ''),
                    'fecha_evento_date' => $fec,
                    'estado_auditoria' => (string) ($r->estado_auditoria ?? 'PENDIENTE'),
                    'observaciones' => (string) ($r->observaciones ?? ''),
                    'auditado_por' => (string) ($r->auditado_por ?? ''),
                    'fecha_resolucion' => (string) ($r->fecha_resolucion ?? ''),
                    'direccion_actual' => (string) ($r->direccion_actual ?? ''),
                    'latitud_actual' => (float) ($r->latitud_actual ?? 0.0),
                    'longitud_actual' => (float) ($r->longitud_actual ?? 0.0),
                    'cambios' => [],
                ];
            }

            $grupos[$clave]['ids'][] = (int) $r->id;
            $grupos[$clave]['cambios'][(string) $r->campo_afectado] = [
                'ant' => (string) ($r->valor_anterior ?? ''),
                'nue' => (string) ($r->valor_nuevo ?? ''),
            ];
        }

        // Ventas de 5 meses previos
        $clienteIds = array_values(array_unique(array_column($grupos, 'cliente_id')));
        $fechasValidas = array_filter(array_column($grupos, 'fecha_evento_date'));
        $minFecha = !empty($fechasValidas) ? min($fechasValidas) : null;

        $ventasMap = [];
        if (!empty($clienteIds) && $minFecha) {
            $ventas = $conn->table('fact_ventas')
                ->whereIn('cliente_id', $clienteIds)
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
        }

        $resultados = [];
        foreach ($grupos as $g) {
            $cid = (int) $g['cliente_id'];
            $fEvento = $g['fecha_evento_date'] ? Carbon::parse($g['fecha_evento_date']) : null;
            $cambios = $g['cambios'];
            $ventasCliente = $ventasMap[$cid] ?? [];

            // 1. Contacto
            $contactoItems = [];
            foreach (['telefono', 'celular'] as $cField) {
                if (isset($cambios[$cField])) {
                    $contactoItems[] = [
                        'campo' => $cField,
                        'ant' => $cambios[$cField]['ant'],
                        'nue' => $cambios[$cField]['nue'],
                    ];
                }
            }

            // 2. Ubicación
            $ubicacionItems = [];
            foreach (['direccion', 'nro', 'referencia'] as $uField) {
                if (isset($cambios[$uField])) {
                    $ubicacionItems[] = [
                        'campo' => $uField,
                        'ant' => $cambios[$uField]['ant'],
                        'nue' => $cambios[$uField]['nue'],
                    ];
                }
            }

            // 3. Georreferencia
            $tieneGeo = isset($cambios['latitud']) || isset($cambios['longitud']);
            $geoInfo = null;
            if ($tieneGeo) {
                $latAnt = isset($cambios['latitud']) && is_numeric($cambios['latitud']['ant']) ? (float) $cambios['latitud']['ant'] : $g['latitud_actual'];
                $latNue = isset($cambios['latitud']) && is_numeric($cambios['latitud']['nue']) ? (float) $cambios['latitud']['nue'] : $g['latitud_actual'];
                $lngAnt = isset($cambios['longitud']) && is_numeric($cambios['longitud']['ant']) ? (float) $cambios['longitud']['ant'] : $g['longitud_actual'];
                $lngNue = isset($cambios['longitud']) && is_numeric($cambios['longitud']['nue']) ? (float) $cambios['longitud']['nue'] : $g['longitud_actual'];

                $distancia = $this->evaluadorReglas->calcularDistanciaHaversineMetros($latAnt, $lngAnt, $latNue, $lngNue);

                $geoInfo = [
                    'lat_anterior' => $latAnt,
                    'lng_anterior' => $lngAnt,
                    'lat_nueva' => $latNue,
                    'lng_nueva' => $lngNue,
                    'cambio_lat' => isset($cambios['latitud']),
                    'cambio_lng' => isset($cambios['longitud']),
                    'distancia_metros' => $distancia,
                ];
            }

            // 4. Comercial
            $comercialItems = [];
            foreach (['cliente', 'cliente_norm', 'tipo_negocio', 'cluster_t', 'nombre_factura', 'nit', 'vendedor', 'cod_vendedor'] as $comField) {
                if (isset($cambios[$comField])) {
                    $comercialItems[] = [
                        'campo' => $comField,
                        'ant' => $cambios[$comField]['ant'],
                        'nue' => $cambios[$comField]['nue'],
                    ];
                }
            }

            // Otros cambios no categorizados
            $camposCategorizados = ['telefono', 'celular', 'direccion', 'nro', 'referencia', 'latitud', 'longitud', 'cliente', 'cliente_norm', 'tipo_negocio', 'cluster_t', 'nombre_factura', 'nit', 'vendedor', 'cod_vendedor'];
            foreach ($cambios as $kField => $val) {
                if (!in_array($kField, $camposCategorizados, true)) {
                    $comercialItems[] = [
                        'campo' => $kField,
                        'ant' => $val['ant'],
                        'nue' => $val['nue'],
                    ];
                }
            }

            $historial5m = [];
            $total5m = 0.0;
            if ($fEvento) {
                $año = $fEvento->year;
                $mes = $fEvento->month;
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
            }

            $item = [
                'id' => $g['ids'][0],
                'ids' => $g['ids'],
                'archivo_origen' => $g['archivo_origen'],
                'cliente_id' => $cid,
                'cliente' => $g['cliente'],
                'ruta' => $g['ruta'],
                'canal' => $g['canal'],
                'dia' => $g['dia'],
                'fecha_auditoria' => $g['fecha_auditoria'],
                'estado_auditoria' => $g['estado_auditoria'],
                'observaciones' => $g['observaciones'],
                'auditado_por' => $g['auditado_por'],
                'fecha_resolucion' => $g['fecha_resolucion'],
                'direccion_actual' => $g['direccion_actual'],
                'contacto' => $contactoItems,
                'ubicacion' => $ubicacionItems,
                'georreferencia' => $geoInfo,
                'comercial' => $comercialItems,
                'total_cambios' => count($cambios),
                'historico_ventas' => [
                    'meses' => $historial5m,
                    'total_5m' => round($total5m, 2),
                    'promedio_mensual' => round($total5m / 5.0, 2),
                ],
            ];

            $item['evaluacion_riesgo'] = $this->evaluadorReglas->evaluarModificacionDatos($item);
            $resultados[] = $item;
        }

        $resultados = $this->enriquecerFotos->execute($resultados, 'cliente_id');

        return [
            'registros' => $resultados,
            'total' => $totalFiltrados,
            'metricas' => $metricas,
        ];
    }
}

