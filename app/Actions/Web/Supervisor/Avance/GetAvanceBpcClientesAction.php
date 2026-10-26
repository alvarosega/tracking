<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceBpcClientesAction
{
    private const CACHE_TTL_SECS = 300;

    public const BPC_JERARQUIA2 = [
        'AIR FRESHENER',
        'DEODORANTS & FRAGANCES',
        'DEODORANTS & FRAGRANCES',
        'DEODORANTS & GROOMING',
        'DIRECT APPLICATIONS AND OTHERS',
        'DISHWASH',
        'FABRIC CLEANERS',
        'FABRIC SENSATIONS',
        'HAIR',
        'HOUSEHOLD CARE',
        'HOUSEHOLD CLEANING',
        'MASS SKIN CARE',
        'MASS SKIN CLEANSING',
        'ORAL (CATEGORY)',
        'SAVOURY',
        'SOLUTION WASH',
    ];

    /**
     * Calcula la cartera de clientes activos totales y paretos BPC por Cluster T.
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
        $cacheKey = "avance_bpc_clientes_" . md5("{$mesEvaluado}_{$canalesKey}_{$rutasKey}_{$clustersKey}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($mesEvaluado, $canales, $rutas, $clusters) {
            return $this->procesarClientes($mesEvaluado, $canales, $rutas, $clusters);
        });
    }

    private function procesarClientes(
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

        $bpcPlaceholders = implode(',', array_fill(0, count(self::BPC_JERARQUIA2), '?'));
        $whereSql = "
            (LOWER(TRIM(fv.revertida)) != 'si' OR fv.revertida IS NULL)
            AND fv.monto_final > 0
            AND TRIM(UPPER(fv.jerarquia2)) IN ({$bpcPlaceholders})
            AND fv.fecha_norm >= ? AND fv.fecha_norm <= ?
        ";

        $params = array_merge(self::BPC_JERARQUIA2, [$minFechaVentana, $maxFechaVentana]);

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

        // 1. Identificar Universo de Clientes Pareto BPC POR CLUSTER (Top 80% volumen acumulado dentro de cada cluster)
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

        // 2. Clientes activos por cluster y mes
        $sqlActivos = "
            SELECT 
                COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') AS cluster,
                DATE_FORMAT(fv.fecha_norm, '%Y-%m') AS anio_mes,
                fv.cliente_id
            FROM fact_ventas fv
            LEFT JOIN dim_rutas dr ON TRIM(UPPER(fv.ruta)) = TRIM(UPPER(dr.ruta))
            WHERE {$whereSql}
            GROUP BY 
                COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER'), 
                DATE_FORMAT(fv.fecha_norm, '%Y-%m'), 
                fv.cliente_id
        ";

        $filasActivos = DB::connection('supervisor')->select($sqlActivos, $params);

        $periodosClave = [$kLM5, $kLM4, $kLM3, $kLM2, $kLM1, $kLM];
        $clustersData = [];

        foreach ($filasActivos as $r) {
            $cl = (string) $r->cluster;
            $mes = (string) $r->anio_mes;
            $cid = (int) $r->cliente_id;

            if (!isset($clustersData[$cl])) {
                $clustersData[$cl] = [
                    'tot' => array_fill_keys($periodosClave, []),
                    'par' => array_fill_keys($periodosClave, []),
                ];
            }

            if (isset($clustersData[$cl]['tot'][$mes])) {
                $clustersData[$cl]['tot'][$mes][$cid] = true;
                if (!empty($paretosCidsPorCluster[$cl][$cid])) {
                    $clustersData[$cl]['par'][$mes][$cid] = true;
                }
            }
        }

        ksort($clustersData);

        $filasTotales = [];
        $filasParetos = [];

        $totTLm345 = 0; $totTLm2 = 0; $totTLm1 = 0; $totTLm = 0; $totTAcm3m = 0;
        $totPLm345 = 0; $totPLm2 = 0; $totPLm1 = 0; $totPLm = 0; $totPAcm3m = 0;

        foreach ($clustersData as $cl => $d) {
            // Tabla 1: Totales
            $cLM5 = count($d['tot'][$kLM5] ?? []);
            $cLM4 = count($d['tot'][$kLM4] ?? []);
            $cLM3 = count($d['tot'][$kLM3] ?? []);
            $cLM345 = $cLM5 + $cLM4 + $cLM3;

            $cLM2 = count($d['tot'][$kLM2] ?? []);
            $cLM1 = count($d['tot'][$kLM1] ?? []);
            $cLM  = count($d['tot'][$kLM] ?? []);
            $cAcm3m = $cLM2 + $cLM1 + $cLM;

            $totTLm345 += $cLM345;
            $totTLm2   += $cLM2;
            $totTLm1   += $cLM1;
            $totTLm    += $cLM;
            $totTAcm3m += $cAcm3m;

            $filasTotales[] = [
                'cluster' => $cl,
                'lm_3_4_5' => $cLM345,
                'lm_2' => $cLM2,
                'lm_1' => $cLM1,
                'lm' => $cLM,
                'acm_3m' => $cAcm3m,
            ];

            // Tabla 2: Paretos
            $pLM5C = count($d['par'][$kLM5] ?? []);
            $pLM4C = count($d['par'][$kLM4] ?? []);
            $pLM3C = count($d['par'][$kLM3] ?? []);
            $pLM345C = $pLM5C + $pLM4C + $pLM3C;

            $pLM2C = count($d['par'][$kLM2] ?? []);
            $pLM1C = count($d['par'][$kLM1] ?? []);
            $pLMC  = count($d['par'][$kLM] ?? []);
            $pAcm3mC = $pLM2C + $pLM1C + $pLMC;

            $totPLm345 += $pLM345C;
            $totPLm2   += $pLM2C;
            $totPLm1   += $pLM1C;
            $totPLm    += $pLMC;
            $totPAcm3m += $pAcm3mC;

            $filasParetos[] = [
                'cluster' => $cl,
                'lm_3_4_5' => $pLM345C,
                'lm_2' => $pLM2C,
                'lm_1' => $pLM1C,
                'lm' => $pLMC,
                'acm_3m' => $pAcm3mC,
            ];
        }

        $mesesAbr = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
        $etiquetas = [
            'lm_3_4_5' => "{$mesesAbr[(int)$pLM5->format('n')]}-{$mesesAbr[(int)$pLM3->format('n')]}",
            'lm_2' => "{$mesesAbr[(int)$pLM2->format('n')]}-{$pLM2->format('y')}",
            'lm_1' => "{$mesesAbr[(int)$pLM1->format('n')]}-{$pLM1->format('y')}",
            'lm' => "{$mesesAbr[(int)$pLM->format('n')]}-{$pLM->format('y')}",
            'acm_3m' => "ACM 3M ({$mesesAbr[(int)$pLM2->format('n')]}-{$mesesAbr[(int)$pLM->format('n')]})",
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
                'titulo' => 'Cartera Clientes Activos Totales (BPC)',
                'filas' => $filasTotales,
                'totales' => [
                    'cluster' => 'TOTAL GENERAL',
                    'lm_3_4_5' => $totTLm345,
                    'lm_2' => $totTLm2,
                    'lm_1' => $totTLm1,
                    'lm' => $totTLm,
                    'acm_3m' => $totTAcm3m,
                ],
            ],
            'tabla_paretos' => [
                'titulo' => 'Cartera Clientes Activos Paretos (BPC)',
                'filas' => $filasParetos,
                'totales' => [
                    'cluster' => 'TOTAL GENERAL',
                    'lm_3_4_5' => $totPLm345,
                    'lm_2' => $totPLm2,
                    'lm_1' => $totPLm1,
                    'lm' => $totPLm,
                    'acm_3m' => $totPAcm3m,
                ],
            ],
        ];
    }
}

