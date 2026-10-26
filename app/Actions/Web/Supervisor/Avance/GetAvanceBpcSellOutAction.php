<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceBpcSellOutAction
{
    private const CACHE_TTL_SECS = 300;

    /**
     * Calcula la matriz de Sell Out Totales y Paretos BPC por Cluster T en Bs.
     *
     * @param string|null $mesEvaluado
     * @param string|null $fechaCorte
     * @param array<string>|null $canales
     * @param array<string>|null $rutas
     * @param array<string>|null $clusters
     * @return array<string, mixed>
     */
    public function execute(
        ?string $mesEvaluado = null,
        ?string $fechaCorte = null,
        ?array $canales = null,
        ?array $rutas = null,
        ?array $clusters = null
    ): array {
        if (!$mesEvaluado) {
            if ($fechaCorte && strlen($fechaCorte) >= 7) {
                $mesEvaluado = substr($fechaCorte, 0, 7);
            } else {
                $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
                $mesEvaluado = $maxFecha ? substr((string) $maxFecha, 0, 7) : date('Y-m');
            }
        }

        $canalesKey = !empty($canales) ? implode(',', $canales) : 'ALL';
        $rutasKey = !empty($rutas) ? implode(',', $rutas) : 'ALL';
        $clustersKey = !empty($clusters) ? implode(',', $clusters) : 'ALL';
        $cacheKey = "avance_bpc_sellout_" . md5("{$mesEvaluado}_{$canalesKey}_{$rutasKey}_{$clustersKey}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($mesEvaluado, $canales, $rutas, $clusters) {
            return $this->procesarSellOut($mesEvaluado, $canales, $rutas, $clusters);
        });
    }

    private function procesarSellOut(
        string $mesEvaluado,
        ?array $canales,
        ?array $rutas,
        ?array $clusters
    ): array {
        $cBase = Carbon::createFromFormat('Y-m-d', $mesEvaluado . '-01');

        $pLM  = $cBase->copy()->subMonth();
        $pLM1 = $cBase->copy()->subMonths(2);
        $pLM2 = $cBase->copy()->subMonths(3);
        $pLM3 = $cBase->copy()->subMonths(4);
        $pLM4 = $cBase->copy()->subMonths(5);
        $pLM5 = $cBase->copy()->subMonths(6);

        $kLM  = $pLM->format('Y-m');
        $kLM1 = $pLM1->format('Y-m');
        $kLM2 = $pLM2->format('Y-m');
        $kLM3 = $pLM3->format('Y-m');
        $kLM4 = $pLM4->format('Y-m');
        $kLM5 = $pLM5->format('Y-m');

        $minFechaVentana = $pLM5->copy()->startOfMonth()->format('Y-m-d');
        $maxFechaVentana = $pLM->copy()->endOfMonth()->format('Y-m-d');

        $bpcCategories = GetAvanceBpcClientesAction::BPC_JERARQUIA2;
        $bpcPlaceholders = implode(',', array_fill(0, count($bpcCategories), '?'));
        $whereSql = "
            (LOWER(TRIM(fv.revertida)) != 'si' OR fv.revertida IS NULL)
            AND fv.monto_final > 0
            AND TRIM(UPPER(fv.jerarquia2)) IN ({$bpcPlaceholders})
            AND fv.fecha_norm >= ? AND fv.fecha_norm <= ?
        ";

        $params = array_merge($bpcCategories, [$minFechaVentana, $maxFechaVentana]);

        if (!empty($canales)) {
            $canalesVal = array_values(array_filter(array_map('trim', $canales), fn ($c) => $c !== '' && strtoupper($c) !== 'TODOS' && strtoupper($c) !== 'TODAS'));
            if (!empty($canalesVal)) {
                $ph = implode(',', array_fill(0, count($canalesVal), '?'));
                $whereSql .= " AND TRIM(UPPER(COALESCE(dr.canal, fv.canal, ''))) IN ({$ph})";
                $params = array_merge($params, array_map('strtoupper', $canalesVal));
            }
        }

        if (!empty($rutas)) {
            $rutasVal = array_values(array_filter(array_map('trim', $rutas), fn ($r) => $r !== '' && strtoupper($r) !== 'TODAS' && strtoupper($r) !== 'TODOS'));
            if (!empty($rutasVal)) {
                $ph = implode(',', array_fill(0, count($rutasVal), '?'));
                $whereSql .= " AND TRIM(UPPER(fv.ruta)) IN ({$ph})";
                $params = array_merge($params, array_map('strtoupper', $rutasVal));
            }
        }

        if (!empty($clusters)) {
            $clustersVal = array_values(array_filter(array_map('trim', $clusters), fn ($cl) => $cl !== '' && strtoupper($cl) !== 'TODOS' && strtoupper($cl) !== 'TODAS'));
            if (!empty($clustersVal)) {
                $ph = implode(',', array_fill(0, count($clustersVal), '?'));
                $whereSql .= " AND TRIM(UPPER(COALESCE(fv.cluster_t, 'SIN CLUSTER'))) IN ({$ph})";
                $params = array_merge($params, array_map('strtoupper', $clustersVal));
            }
        }

        // 1. Identificar Universo Pareto BPC por Cluster
        $sqlPareto = "
            SELECT 
                COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') AS cluster,
                fv.cliente_id,
                SUM(fv.monto_final) AS total_bpc
            FROM fact_ventas fv
            LEFT JOIN dim_rutas dr ON TRIM(UPPER(fv.ruta)) = TRIM(UPPER(dr.ruta))
            WHERE {$whereSql}
            GROUP BY 
                COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER'), 
                fv.cliente_id
            ORDER BY 
                cluster, 
                total_bpc DESC
        ";

        $clientesParetoRaw = DB::connection('supervisor')->select($sqlPareto, $params);

        $paretosPorCluster = [];
        foreach ($clientesParetoRaw as $r) {
            $cl = (string) $r->cluster;
            if (!isset($paretosPorCluster[$cl])) {
                $paretosPorCluster[$cl] = [];
            }
            $paretosPorCluster[$cl][] = [
                'cliente_id' => (int) $r->cliente_id,
                'total_bpc' => (float) ($r->total_bpc ?? 0.0),
            ];
        }

        $paretosCidsPorCluster = [];
        foreach ($paretosPorCluster as $cl => $clientesLista) {
            $volCluster = array_sum(array_column($clientesLista, 'total_bpc'));
            $acumVol = 0.0;
            $cidsSet = [];
            foreach ($clientesLista as $item) {
                $acumVol += $item['total_bpc'];
                $cidsSet[$item['cliente_id']] = true;
                if ($volCluster > 0 && ($acumVol / $volCluster) >= 0.80001) {
                    break;
                }
            }
            $paretosCidsPorCluster[$cl] = $cidsSet;
        }

        // 2. Ventas agrupadas por cluster, mes y cliente
        $sqlVentas = "
            SELECT 
                COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') AS cluster,
                DATE_FORMAT(fv.fecha_norm, '%Y-%m') AS anio_mes,
                fv.cliente_id,
                SUM(fv.monto_final) AS monto
            FROM fact_ventas fv
            LEFT JOIN dim_rutas dr ON TRIM(UPPER(fv.ruta)) = TRIM(UPPER(dr.ruta))
            WHERE {$whereSql}
            GROUP BY 
                COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER'), 
                DATE_FORMAT(fv.fecha_norm, '%Y-%m'), 
                fv.cliente_id
        ";

        $filasVentas = DB::connection('supervisor')->select($sqlVentas, $params);

        $periodosClave = [$kLM5, $kLM4, $kLM3, $kLM2, $kLM1, $kLM];
        $clustersData = [];

        foreach ($filasVentas as $r) {
            $cl = (string) $r->cluster;
            $mes = (string) $r->anio_mes;
            $cid = (int) $r->cliente_id;
            $monto = (float) ($r->monto ?? 0.0);

            if (!isset($clustersData[$cl])) {
                $clustersData[$cl] = [
                    'tot' => array_fill_keys($periodosClave, 0.0),
                    'par' => array_fill_keys($periodosClave, 0.0),
                ];
            }

            if (isset($clustersData[$cl]['tot'][$mes])) {
                $clustersData[$cl]['tot'][$mes] += $monto;
                if (!empty($paretosCidsPorCluster[$cl][$cid])) {
                    $clustersData[$cl]['par'][$mes] += $monto;
                }
            }
        }

        ksort($clustersData);

        $filasTotales = [];
        $filasParetos = [];

        $totTLm345 = 0.0; $totTLm2 = 0.0; $totTLm1 = 0.0; $totTLm = 0.0; $totTTot3m = 0.0;
        $totPLm345 = 0.0; $totPLm2 = 0.0; $totPLm1 = 0.0; $totPLm = 0.0; $totPTot3m = 0.0;

        foreach ($clustersData as $cl => $d) {
            // Totales
            $vLM5 = $d['tot'][$kLM5] ?? 0.0;
            $vLM4 = $d['tot'][$kLM4] ?? 0.0;
            $vLM3 = $d['tot'][$kLM3] ?? 0.0;
            $vLM345 = $vLM5 + $vLM4 + $vLM3;

            $vLM2 = $d['tot'][$kLM2] ?? 0.0;
            $vLM1 = $d['tot'][$kLM1] ?? 0.0;
            $vLM  = $d['tot'][$kLM] ?? 0.0;
            $vTot3m = $vLM2 + $vLM1 + $vLM;

            $totTLm345 += $vLM345;
            $totTLm2   += $vLM2;
            $totTLm1   += $vLM1;
            $totTLm    += $vLM;
            $totTTot3m += $vTot3m;

            $filasTotales[] = [
                'cluster' => $cl,
                'lm_3_4_5' => round($vLM345, 2),
                'lm_2' => round($vLM2, 2),
                'lm_1' => round($vLM1, 2),
                'lm' => round($vLM, 2),
                'tot_3m' => round($vTot3m, 2),
            ];

            // Paretos
            $pvLM5 = $d['par'][$kLM5] ?? 0.0;
            $pvLM4 = $d['par'][$kLM4] ?? 0.0;
            $pvLM3 = $d['par'][$kLM3] ?? 0.0;
            $pvLM345 = $pvLM5 + $pvLM4 + $pvLM3;

            $pvLM2 = $d['par'][$kLM2] ?? 0.0;
            $pvLM1 = $d['par'][$kLM1] ?? 0.0;
            $pvLM  = $d['par'][$kLM] ?? 0.0;
            $pvTot3m = $pvLM2 + $pvLM1 + $pvLM;

            $totPLm345 += $pvLM345;
            $totPLm2   += $pvLM2;
            $totPLm1   += $pvLM1;
            $totPLm    += $pvLM;
            $totPTot3m += $pvTot3m;

            $filasParetos[] = [
                'cluster' => $cl,
                'lm_3_4_5' => round($pvLM345, 2),
                'lm_2' => round($pvLM2, 2),
                'lm_1' => round($pvLM1, 2),
                'lm' => round($pvLM, 2),
                'tot_3m' => round($pvTot3m, 2),
            ];
        }

        $mesesAbr = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
        $etiquetas = [
            'lm_3_4_5' => "{$mesesAbr[(int)$pLM5->format('n')]}-{$mesesAbr[(int)$pLM3->format('n')]}",
            'lm_2' => "{$mesesAbr[(int)$pLM2->format('n')]}-{$pLM2->format('y')}",
            'lm_1' => "{$mesesAbr[(int)$pLM1->format('n')]}-{$pLM1->format('y')}",
            'lm' => "{$mesesAbr[(int)$pLM->format('n')]}-{$pLM->format('y')}",
            'tot_3m' => "TOT 3M ({$mesesAbr[(int)$pLM2->format('n')]}-{$mesesAbr[(int)$pLM->format('n')]})",
        ];

        return [
            'mes_evaluado' => $mesEvaluado,
            'periodos' => [
                'lm' => $kLM,
                'lm_1' => $kLM1,
                'lm_2' => $kLM2,
                'lm_3' => $kLM3,
                'lm_4' => $kLM4,
                'lm_5' => $kLM5,
                'etiquetas' => $etiquetas,
            ],
            'tabla_totales' => [
                'titulo' => 'Sell Out Totales BPC (Bs)',
                'filas' => $filasTotales,
                'totales' => [
                    'cluster' => 'TOTAL GENERAL',
                    'lm_3_4_5' => round($totTLm345, 2),
                    'lm_2' => round($totTLm2, 2),
                    'lm_1' => round($totTLm1, 2),
                    'lm' => round($totTLm, 2),
                    'tot_3m' => round($totTTot3m, 2),
                ],
            ],
            'tabla_paretos' => [
                'titulo' => 'Sell Out Paretos BPC (Bs)',
                'filas' => $filasParetos,
                'totales' => [
                    'cluster' => 'TOTAL GENERAL',
                    'lm_3_4_5' => round($totPLm345, 2),
                    'lm_2' => round($totPLm2, 2),
                    'lm_1' => round($totPLm1, 2),
                    'lm' => round($totPLm, 2),
                    'tot_3m' => round($totPTot3m, 2),
                ],
            ],
        ];
    }
}

