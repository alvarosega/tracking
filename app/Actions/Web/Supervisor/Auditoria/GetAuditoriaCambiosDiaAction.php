<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use App\Services\Auditoria\EvaluadorReglasAuditoriaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAuditoriaCambiosDiaAction
{
    public function __construct(
        protected EvaluadorReglasAuditoriaService $evaluadorReglas,
        protected EnriquecerFotosAction $enriquecerFotos
    ) {}

    /**
     * Consulta cambios de día de visita con histórico de ventas.
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
        $diaFiltro = trim((string) ($params['dia_filtro'] ?? ''));
        $estadoAuditoria = strtoupper(trim((string) ($params['estado_auditoria'] ?? '')));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $limit = isset($params['limit']) && is_numeric($params['limit']) && (int) $params['limit'] > 0
            ? min((int) $params['limit'], 20000)
            : null;
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $subPan = $conn->table('pan_ruteo')
            ->selectRaw('cliente_id, MAX(cliente) AS cliente, MAX(ruta) AS ruta, MAX(direccion) AS direccion, MAX(vendedor) AS vendedor')
            ->groupBy('cliente_id');

        $baseQuery = $conn->table('auditoria_ruteo_cambios as a')
            ->leftJoinSub($subPan, 'p', 'a.cliente_id', '=', 'p.cliente_id')
            ->leftJoin('dim_rutas as r', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(COALESCE(p.ruta, '')))"), '=', DB::raw("TRIM(UPPER(r.ruta))"));
            })
            ->where('a.tipo_evento', 'MODIFICADO_DIA');

        if ($canal !== '' && strtoupper($canal) !== 'TODOS') {
            $baseQuery->whereRaw('TRIM(r.canal) = ?', [$canal]);
        }

        $rutasValidas = array_filter(array_map('trim', $rutas));
        if (!empty($rutasValidas)) {
            $rutasUpper = array_map('strtoupper', $rutasValidas);
            $baseQuery->whereIn(DB::raw("TRIM(UPPER(COALESCE(p.ruta, '')))"), $rutasUpper);
        }

        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $baseQuery->where('a.archivo_origen', $archivoOrigen);
        }

        if ($diaFiltro !== '' && strtoupper($diaFiltro) !== 'TODOS') {
            $dTerm = '%' . strtolower($diaFiltro) . '%';
            $baseQuery->where(function ($q) use ($dTerm) {
                $q->whereRaw('LOWER(a.valor_anterior) LIKE ?', [$dTerm])
                  ->orWhereRaw('LOWER(a.valor_nuevo) LIKE ?', [$dTerm]);
            });
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
            DB::raw("COALESCE(a.valor_anterior, 'SIN DIA') AS dia_anterior"),
            DB::raw("COALESCE(a.valor_nuevo, 'SIN DIA') AS dia_nuevo"),
            DB::raw("DATE_FORMAT(a.fecha_auditoria, '%Y-%m-%d %H:%i') AS fecha_auditoria_fmt"),
            DB::raw("DATE(a.fecha_auditoria) AS fecha_evento_date"),
            'a.estado_auditoria',
            DB::raw("COALESCE(a.observaciones, '') AS observaciones"),
            DB::raw("COALESCE(a.auditado_por, '') AS auditado_por"),
            DB::raw("DATE_FORMAT(a.fecha_resolucion, '%Y-%m-%d %H:%i') AS fecha_resolucion"),
            DB::raw("COALESCE(TRIM(UPPER(p.ruta)), 'SIN RUTA') AS ruta"),
            DB::raw("COALESCE(TRIM(r.canal), 'SIN CANAL') AS canal"),
            DB::raw("COALESCE(p.vendedor, r.vendedor, 'Sin Asignar') AS vendedor"),
            DB::raw("COALESCE(p.direccion, 'Sin Dirección') AS direccion"),
        ])
        ->orderByRaw('COALESCE(a.fecha_resolucion, a.fecha_auditoria) DESC')
        ->orderByDesc('a.id');

        if ($limit !== null) {
            $dataQuery->offset($offset)->limit($limit);
        }

        $cambios = $dataQuery->get();
        if ($cambios->isEmpty()) {
            return [
                'registros' => [],
                'total' => $totalFiltrados,
                'metricas' => $metricas,
            ];
        }

        // 3. Ventas de 5 meses previos
        $clienteIds = $cambios->pluck('cliente_id')->filter()->unique()->values()->all();
        $fechasValidas = $cambios->pluck('fecha_evento_date')->filter()->all();
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

        // 4. Formatear y evaluar
        $resultados = [];
        foreach ($cambios as $r) {
            $cid = (int) $r->cliente_id;
            $fEvento = $r->fecha_evento_date ? Carbon::parse($r->fecha_evento_date) : null;
            $ventasCliente = $ventasMap[$cid] ?? [];

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
                'id' => (int) $r->id,
                'archivo_origen' => (string) ($r->archivo_origen ?? ''),
                'cliente_id' => $cid,
                'cliente' => (string) ($r->cliente ?? ''),
                'dia_anterior' => (string) ($r->dia_anterior ?? 'SIN DIA'),
                'dia_nuevo' => (string) ($r->dia_nuevo ?? 'SIN DIA'),
                'fecha_auditoria' => (string) ($r->fecha_auditoria_fmt ?? ''),
                'estado_auditoria' => (string) ($r->estado_auditoria ?? 'PENDIENTE'),
                'observaciones' => (string) ($r->observaciones ?? ''),
                'auditado_por' => (string) ($r->auditado_por ?? ''),
                'fecha_resolucion' => (string) ($r->fecha_resolucion ?? ''),
                'ruta' => (string) ($r->ruta ?? ''),
                'canal' => (string) ($r->canal ?? ''),
                'vendedor' => (string) ($r->vendedor ?? ''),
                'direccion' => (string) ($r->direccion ?? ''),
                'historico_ventas' => [
                    'meses' => $historial5m,
                    'total_5m' => round($total5m, 2),
                    'promedio_mensual' => round($total5m / 5.0, 2),
                ],
            ];

            $item['evaluacion_riesgo'] = $this->evaluadorReglas->evaluarCambioDia($item);
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

