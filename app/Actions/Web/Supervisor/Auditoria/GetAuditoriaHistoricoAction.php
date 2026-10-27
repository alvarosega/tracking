<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use App\Services\Auditoria\EvaluadorReglasAuditoriaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAuditoriaHistoricoAction
{
    public function __construct(
        protected EvaluadorReglasAuditoriaService $evaluadorReglas,
        protected EnriquecerFotosAction $enriquecerFotos
    ) {}

    /**
     * Consulta el histórico de eventos resueltos en auditoria_ruteo_cambios.
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
        $tipoEvento = strtoupper(trim((string) ($params['tipo_evento'] ?? '')));
        $estadoAuditoria = strtoupper(trim((string) ($params['estado_auditoria'] ?? 'APROBADO')));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $limit = isset($params['limit']) && is_numeric($params['limit']) && (int) $params['limit'] > 0
            ? min((int) $params['limit'], 2000)
            : 400;

        $subPan = $conn->table('pan_ruteo')
            ->selectRaw('cliente_id, MAX(cliente) AS cliente, MAX(ruta) AS ruta, MAX(dia_norm) AS dia_norm, MAX(dia) AS dia, MAX(direccion) AS direccion, MAX(vendedor) AS vendedor')
            ->groupBy('cliente_id');

        $query = $conn->table('auditoria_ruteo_cambios as a')
            ->leftJoinSub($subPan, 'p', 'a.cliente_id', '=', 'p.cliente_id')
            ->leftJoin('dim_rutas as r', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(COALESCE(p.ruta, a.valor_nuevo, a.valor_anterior, '')))"), '=', DB::raw("TRIM(UPPER(r.ruta))"));
            })
            ->leftJoin('dim_rutas as r_ant', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(a.valor_anterior))"), '=', DB::raw("TRIM(UPPER(r_ant.ruta))"));
            })
            ->leftJoin('dim_rutas as r_nue', function ($join) {
                $join->on(DB::raw("TRIM(UPPER(a.valor_nuevo))"), '=', DB::raw("TRIM(UPPER(r_nue.ruta))"));
            });

        if ($estadoAuditoria !== '' && $estadoAuditoria !== 'TODOS') {
            $query->where('a.estado_auditoria', $estadoAuditoria);
        }

        if ($canal !== '' && strtoupper($canal) !== 'TODOS') {
            $query->where(function ($q) use ($canal) {
                $q->whereRaw('TRIM(r.canal) = ?', [$canal])
                  ->orWhereRaw('TRIM(r_ant.canal) = ?', [$canal])
                  ->orWhereRaw('TRIM(r_nue.canal) = ?', [$canal]);
            });
        }

        $rutasValidas = array_filter(array_map('trim', $rutas));
        if (!empty($rutasValidas)) {
            $rutasUpper = array_map('strtoupper', $rutasValidas);
            $query->where(function ($q) use ($rutasUpper) {
                $q->whereIn(DB::raw("TRIM(UPPER(COALESCE(p.ruta, '')))"), $rutasUpper)
                  ->orWhereIn(DB::raw("TRIM(UPPER(COALESCE(a.valor_anterior, '')))"), $rutasUpper)
                  ->orWhereIn(DB::raw("TRIM(UPPER(COALESCE(a.valor_nuevo, '')))"), $rutasUpper);
            });
        }

        if ($archivoOrigen !== '' && strtoupper($archivoOrigen) !== 'TODOS') {
            $query->where('a.archivo_origen', $archivoOrigen);
        }

        if ($tipoEvento !== '' && $tipoEvento !== 'TODOS') {
            $query->where('a.tipo_evento', $tipoEvento);
        }

        if ($fechaInicio !== '') {
            $query->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) >= ?', [$fechaInicio]);
        }

        if ($fechaFin !== '') {
            $query->whereRaw('DATE(COALESCE(a.fecha_resolucion, a.fecha_auditoria)) <= ?', [$fechaFin]);
        }

        $rawRows = $query->select([
            'a.id',
            'a.archivo_origen',
            'a.cliente_id',
            DB::raw("COALESCE(a.cliente, p.cliente, 'Sin Nombre') AS cliente"),
            'a.tipo_evento',
            'a.campo_afectado',
            DB::raw("COALESCE(a.valor_anterior, '-') AS valor_anterior"),
            DB::raw("COALESCE(a.valor_nuevo, '-') AS valor_nuevo"),
            DB::raw("DATE_FORMAT(a.fecha_auditoria, '%Y-%m-%d %H:%i') AS fecha_auditoria_fmt"),
            DB::raw("DATE(a.fecha_auditoria) AS fecha_evento_date"),
            'a.estado_auditoria',
            DB::raw("COALESCE(a.observaciones, '') AS observaciones"),
            DB::raw("COALESCE(a.auditado_por, '') AS auditado_por"),
            DB::raw("DATE_FORMAT(a.fecha_resolucion, '%Y-%m-%d %H:%i') AS fecha_resolucion"),
            DB::raw("COALESCE(TRIM(UPPER(p.ruta)), TRIM(UPPER(a.valor_nuevo)), TRIM(UPPER(a.valor_anterior)), 'SIN RUTA') AS ruta"),
            DB::raw("COALESCE(TRIM(r.canal), 'SIN CANAL') AS canal"),
            DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') AS dia"),
            DB::raw("COALESCE(p.direccion, 'Sin Dirección') AS direccion"),
            DB::raw("COALESCE(p.vendedor, r.vendedor, 'Sin Asignar') AS vendedor"),
            DB::raw("COALESCE(TRIM(UPPER(a.valor_anterior)), 'SIN RUTA') AS ruta_anterior"),
            DB::raw("COALESCE(TRIM(r_ant.canal), 'SIN CANAL') AS canal_anterior"),
            DB::raw("COALESCE(TRIM(r_ant.vendedor), 'Sin Asignar') AS vendedor_anterior"),
            DB::raw("COALESCE(TRIM(UPPER(a.valor_nuevo)), 'SIN RUTA') AS ruta_nueva"),
            DB::raw("COALESCE(TRIM(r_nue.canal), 'SIN CANAL') AS canal_nuevo"),
            DB::raw("COALESCE(TRIM(r_nue.vendedor), 'Sin Asignar') AS vendedor_nuevo"),
        ])
        ->orderByRaw('COALESCE(a.fecha_resolucion, a.fecha_auditoria) DESC')
        ->orderByDesc('a.id')
        ->limit($limit)
        ->get();

        if ($rawRows->isEmpty()) {
            return [
                'registros' => [],
                'total' => 0,
            ];
        }

        // Ventas de 5 meses previos
        $clienteIds = $rawRows->pluck('cliente_id')->filter()->unique()->values()->all();
        $fechasValidas = $rawRows->pluck('fecha_evento_date')->filter()->all();
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
        foreach ($rawRows as $r) {
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

            $tipoEv = strtoupper(trim((string) ($r->tipo_evento ?? '')));
            $item = [
                'id' => (int) $r->id,
                'archivo_origen' => (string) ($r->archivo_origen ?? ''),
                'cliente_id' => $cid,
                'cliente' => (string) ($r->cliente ?? ''),
                'tipo_evento' => $tipoEv,
                'campo_afectado' => (string) ($r->campo_afectado ?? ''),
                'valor_anterior' => (string) ($r->valor_anterior ?? '-'),
                'valor_nuevo' => (string) ($r->valor_nuevo ?? '-'),
                'fecha_auditoria' => (string) ($r->fecha_auditoria_fmt ?? ''),
                'estado_auditoria' => (string) ($r->estado_auditoria ?? 'APROBADO'),
                'observaciones' => (string) ($r->observaciones ?? ''),
                'auditado_por' => (string) ($r->auditado_por ?? ''),
                'fecha_resolucion' => (string) ($r->fecha_resolucion ?? ''),
                'ruta' => (string) ($r->ruta ?? ''),
                'canal' => (string) ($r->canal ?? ''),
                'dia' => (string) ($r->dia ?? ''),
                'direccion' => (string) ($r->direccion ?? ''),
                'vendedor' => (string) ($r->vendedor ?? ''),
                'ruta_anterior' => (string) ($r->ruta_anterior ?? 'SIN RUTA'),
                'canal_anterior' => (string) ($r->canal_anterior ?? 'SIN CANAL'),
                'vendedor_anterior' => (string) ($r->vendedor_anterior ?? 'Sin Asignar'),
                'ruta_nueva' => (string) ($r->ruta_nueva ?? 'SIN RUTA'),
                'canal_nuevo' => (string) ($r->canal_nuevo ?? 'SIN CANAL'),
                'vendedor_nuevo' => (string) ($r->vendedor_nuevo ?? 'Sin Asignar'),
                'historico_ventas' => [
                    'meses' => $historial5m,
                    'total_5m' => round($total5m, 2),
                    'promedio_mensual' => round($total5m / 5.0, 2),
                ],
            ];

            // Evaluar según tipo de evento
            if (in_array($tipoEv, ['INACTIVADO', 'ELIMINADO'], true)) {
                $item['evaluacion_riesgo'] = $this->evaluadorReglas->evaluarInactivacion($item);
            } elseif ($tipoEv === 'MODIFICADO_RUTA') {
                $item['evaluacion_riesgo'] = $this->evaluadorReglas->evaluarCambioRuta($item);
            } elseif ($tipoEv === 'MODIFICADO_DIA') {
                $item['dia_anterior'] = $item['valor_anterior'];
                $item['dia_nuevo'] = $item['valor_nuevo'];
                $item['evaluacion_riesgo'] = $this->evaluadorReglas->evaluarCambioDia($item);
            } elseif (in_array($tipoEv, ['NUEVO', 'REACTIVADO'], true)) {
                $item['evaluacion_riesgo'] = $this->evaluadorReglas->evaluarAlta($item);
            } else {
                $item['evaluacion_riesgo'] = [
                    'nivel_riesgo' => 'BAJO',
                    'codigo_evaluacion' => 'EVENTO_RESOLUCION',
                    'mensaje_evaluacion' => "Evento: {$tipoEv}",
                    'badge_class' => 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]',
                ];
            }

            $resultados[] = $item;
        }

        $resultados = $this->enriquecerFotos->execute($resultados, 'cliente_id');

        return [
            'registros' => $resultados,
            'total' => count($resultados),
        ];
    }
}

