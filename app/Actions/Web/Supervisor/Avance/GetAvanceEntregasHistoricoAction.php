<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceEntregasHistoricoAction
{
    private const CACHE_TTL_SECS = 300;

    /**
     * Calcula las métricas de entregas consolidadas por ruta en un rango de fechas.
     */
    public function execute(?string $fechaInicio = null, ?string $fechaCorte = null): array
    {
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')
                ->where('monto_final', '>', 0)
                ->max('fecha_norm');
            $fechaCorte = $maxFecha ? (string) $maxFecha : date('Y-m-d');
        }

        $corteCarbon = Carbon::parse($fechaCorte);
        if (!$fechaInicio) {
            $fechaInicio = $corteCarbon->copy()->startOfMonth()->format('Y-m-d');
        }

        $cacheKey = "avance_entregas_historico_{$fechaInicio}_{$fechaCorte}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($fechaInicio, $fechaCorte) {
            return $this->procesarHistorico($fechaInicio, $fechaCorte);
        });
    }

    private function procesarHistorico(string $fechaInicio, string $fechaCorte): array
    {
        $fechaMaxFact = Carbon::parse($fechaCorte)->addDays(6)->format('Y-m-d');

        $query = "
            SELECT 
                TRIM(UPPER(COALESCE(fp.ruta, 'SIN RUTA'))) AS ruta,
                MAX(COALESCE(dr.canal, 'GENERAL')) AS canal,
                MAX(COALESCE(NULLIF(TRIM(dr.vendedor), ''), NULLIF(TRIM(fp.vendedor), ''), 'No Asignado')) AS vendedor,
                fp.nro_preventa,
                SUM(fp.cantidad) AS cant_prev,
                ROUND(SUM(fp.monto_final), 2) AS monto_prev,
                COALESCE(SUM(fv_agregada.cantidad_facturada), 0) AS cant_fact,
                ROUND(COALESCE(SUM(fv_agregada.monto_facturado), 0.0), 2) AS monto_fact
            FROM fact_preventas fp
            LEFT JOIN dim_rutas dr ON TRIM(UPPER(fp.ruta)) = TRIM(UPPER(dr.ruta))
            LEFT JOIN (
                SELECT 
                    pre_venta_id,
                    producto_id,
                    SUM(cantidad) AS cantidad_facturada,
                    SUM(monto_final) AS monto_facturado
                FROM fact_ventas
                WHERE fecha_norm >= ? AND fecha_norm <= ?
                  AND monto_final > 0
                  AND cantidad > 0
                  AND (revertida IS NULL OR LOWER(TRIM(revertida)) != 'si')
                GROUP BY pre_venta_id, producto_id
            ) fv_agregada ON fp.nro_preventa = fv_agregada.pre_venta_id AND fp.producto_id = fv_agregada.producto_id
            WHERE fp.fecha_norm >= ? AND fp.fecha_norm <= ?
              AND fp.monto_final > 0 AND fp.cantidad > 0
            GROUP BY 
                fp.ruta,
                fp.nro_preventa
        ";

        $filas = DB::connection('supervisor')->select($query, [
            $fechaInicio, $fechaMaxFact,
            $fechaInicio, $fechaCorte,
        ]);

        $todosNros = [];
        foreach ($filas as $row) {
            if ($row->nro_preventa !== null) {
                $todosNros[] = (int) $row->nro_preventa;
            }
        }

        $justificativosMap = $this->obtenerJustificativosSet(array_unique($todosNros));

        $rutasMap = [];

        foreach ($filas as $row) {
            $rNombre = (string) $row->ruta;
            if (!isset($rutasMap[$rNombre])) {
                $rutasMap[$rNombre] = [
                    'ruta' => $rNombre,
                    'canal' => (string) $row->canal,
                    'vendedor' => (string) $row->vendedor,
                    'total_pedidos' => 0,
                    'pedidos_entregados' => 0,
                    'pedidos_parciales' => 0,
                    'pedidos_caidos' => 0,
                    'pedidos_justificados' => 0,
                    'monto_preventa' => 0.0,
                    'monto_facturado' => 0.0,
                ];
            }

            $r = &$rutasMap[$rNombre];
            $r['total_pedidos']++;

            $nroPrev = (int) $row->nro_preventa;
            if (isset($justificativosMap[$nroPrev])) {
                $r['pedidos_justificados']++;
            }

            $cPrev = (int) ($row->cant_prev ?? 0);
            $cFact = (int) ($row->cant_fact ?? 0);
            $mPrev = (float) ($row->monto_prev ?? 0.0);
            $mFact = (float) ($row->monto_fact ?? 0.0);

            $r['monto_preventa'] = round($r['monto_preventa'] + $mPrev, 2);
            $r['monto_facturado'] = round($r['monto_facturado'] + $mFact, 2);

            if ($cFact === 0) {
                $r['pedidos_caidos']++;
            } elseif ($cFact >= $cPrev) {
                $r['pedidos_entregados']++;
            } else {
                $r['pedidos_parciales']++;
            }
            unset($r);
        }

        $rutasResumen = [];
        $totPedidos = 0;
        $totEntregados = 0;
        $totParciales = 0;
        $totCaidos = 0;
        $totPrevBs = 0.0;
        $totFactBs = 0.0;

        ksort($rutasMap);

        foreach ($rutasMap as $rNombre => $r) {
            $mPrev = $r['monto_preventa'];
            $mFact = $r['monto_facturado'];
            $brecha = round($mFact - $mPrev, 2);
            $efec = $mPrev > 0 ? round(($mFact / $mPrev) * 100.0, 1) : 0.0;

            $rutasResumen[] = [
                'ruta' => $rNombre,
                'canal' => $r['canal'],
                'vendedor' => $r['vendedor'],
                'total_pedidos' => $r['total_pedidos'],
                'pedidos_entregados' => $r['pedidos_entregados'],
                'pedidos_parciales' => $r['pedidos_parciales'],
                'pedidos_caidos' => $r['pedidos_caidos'],
                'pedidos_justificados' => $r['pedidos_justificados'],
                'monto_preventa' => $mPrev,
                'monto_facturado' => $mFact,
                'brecha_bs' => $brecha,
                'efectividad_pct' => $efec,
            ];

            $totPedidos += $r['total_pedidos'];
            $totEntregados += $r['pedidos_entregados'];
            $totParciales += $r['pedidos_parciales'];
            $totCaidos += $r['pedidos_caidos'];
            $totPrevBs = round($totPrevBs + $mPrev, 2);
            $totFactBs = round($totFactBs + $mFact, 2);
        }

        $brechaGlobal = round($totFactBs - $totPrevBs, 2);
        $efecGlobal = $totPrevBs > 0 ? round(($totFactBs / $totPrevBs) * 100.0, 1) : 0.0;

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'total_rutas' => count($rutasResumen),
            'totales_generales' => [
                'total_pedidos' => $totPedidos,
                'pedidos_entregados' => $totEntregados,
                'pedidos_parciales' => $totParciales,
                'pedidos_caidos' => $totCaidos,
                'monto_preventa' => $totPrevBs,
                'monto_facturado' => $totFactBs,
                'brecha_bs' => $brechaGlobal,
                'efectividad_global_pct' => $efecGlobal,
            ],
            'rutas' => $rutasResumen,
        ];
    }

    private function obtenerJustificativosSet(array $nrosPreventa): array
    {
        if (empty($nrosPreventa)) {
            return [];
        }

        try {
            return DB::connection('mysql')->table('pedidos_rechazados')
                ->whereIn('nro_preventa', $nrosPreventa)
                ->pluck('nro_preventa')
                ->map(fn ($n) => (int) $n)
                ->flip()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}

