<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceClusterMatrizAction
{
    private const MESES_NOMBRES = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    private const ORDEN_ESTANDAR_CLUSTERS = [
        'TDB', 'MZO', 'MAY', 'MICROMERCADO', 'FARMACIAS', 'OTROS', 'PROVINCIA',
    ];

    public function __construct(
        private readonly GetAvanceClusterCatalogoAction $catalogoAction
    ) {}

    /**
     * Genera la matriz de avance y comparativo LY multinivel por cluster / ruta / jerarquías.
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        array $canales = [],
        array $rutas = [],
        array $clusters = [],
        string $metrica = 'monto',
        string $agrupacion = 'cluster_jerarquia'
    ): array {
        $catalogo = $this->catalogoAction->execute();

        $dMax = $catalogo['max_fecha'] ? Carbon::parse($catalogo['max_fecha']) : Carbon::today();
        $dDefIni = $catalogo['fecha_inicio_default'] ? Carbon::parse($catalogo['fecha_inicio_default']) : $dMax->copy()->startOfYear();

        $fIni = $fechaInicio ? Carbon::parse($fechaInicio) : $dDefIni;
        $fCorte = $fechaCorte ? Carbon::parse($fechaCorte) : $dMax;

        if ($fIni->greaterThan($fCorte)) {
            $tmp = $fIni;
            $fIni = $fCorte;
            $fCorte = $tmp;
        }

        $mesesMeta = $this->generarMesesRango($fIni, $fCorte);
        if (empty($mesesMeta)) {
            return [
                'meses' => [],
                'filas' => [],
                'totales' => [],
                'metrica' => $metrica,
                'agrupacion' => $agrupacion,
                'unidad' => 'Bs',
                'fecha_inicio' => $fIni->format('Y-m-d'),
                'fecha_corte' => $fCorte->format('Y-m-d'),
            ];
        }

        $minTy = min(array_column($mesesMeta, 'ty_ini'));
        $maxTy = max(array_column($mesesMeta, 'ty_fin'));
        $minLy = min(array_column($mesesMeta, 'ly_ini'));
        $maxLy = max(array_column($mesesMeta, 'ly_fin_full'));

        // Configuración de métrica (fija a Monto Final Bs)
        $metrica = 'monto';
        $colExpr = 'fv.monto_final';
        $unidadLabel = 'Bs';
        $decimales = 2;

        // Filtros de Canales, Rutas y Clusters
        $canalesValidos = array_values(array_filter(array_map('trim', array_map('strtoupper', $canales)), fn ($c) => $c !== '' && $c !== 'TODOS' && $c !== 'TODAS'));
        $rutasValidas = array_values(array_filter(array_map('trim', array_map('strtoupper', $rutas)), fn ($r) => $r !== '' && $r !== 'TODOS' && $r !== 'TODAS'));
        $clustersValidos = array_values(array_filter(array_map('trim', array_map('strtoupper', $clusters)), fn ($cl) => $cl !== '' && $cl !== 'TODOS' && $cl !== 'TODAS'));

        $agrupacion = strtolower(trim($agrupacion));
        if (!in_array($agrupacion, ['cluster_jerarquia', 'cluster_ruta_jerarquia', 'ruta_jerarquia', 'cluster_cliente', 'cluster'], true)) {
            $agrupacion = 'cluster_jerarquia';
        }

        // Construir Query
        $query = DB::connection('supervisor')->table('fact_ventas as fv')
            ->where(function ($q): void {
                $q->whereNull('fv.revertida')->orWhereRaw("LOWER(TRIM(fv.revertida)) != 'si'");
            })
            ->where('fv.monto_final', '>', 0)
            ->where(function ($q) use ($minTy, $maxTy, $minLy, $maxLy): void {
                $q->whereBetween('fv.fecha_norm', [$minTy, $maxTy])
                  ->orWhereBetween('fv.fecha_norm', [$minLy, $maxLy]);
            });

        if (!empty($canalesValidos)) {
            $query->leftJoin('dim_rutas as dr', 'dr.ruta', '=', 'fv.ruta')
                ->whereIn(DB::raw("TRIM(UPPER(COALESCE(dr.canal, fv.canal, '')))"), $canalesValidos);
        }

        if (!empty($rutasValidas)) {
            $query->whereIn(DB::raw("TRIM(UPPER(fv.ruta))"), $rutasValidas);
        }

        if (!empty($clustersValidos)) {
            $query->whereIn(DB::raw("TRIM(UPPER(COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER')))"), $clustersValidos);
        }

        // Columnas a seleccionar y agrupar
        if ($agrupacion === 'cluster') {
            $query->select([
                DB::raw("COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') as cluster"),
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha_norm"),
                DB::raw("SUM({$colExpr}) as valor"),
            ])->groupByRaw("cluster, fecha_norm");
        } elseif ($agrupacion === 'cluster_cliente') {
            $query->select([
                DB::raw("COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') as cluster"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.ruta), ''), 'SIN RUTA') as ruta"),
                DB::raw("COALESCE(fv.cliente_id, 0) as cliente_id"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.cliente), ''), 'CLIENTE S/N') as cliente"),
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha_norm"),
                DB::raw("SUM({$colExpr}) as valor"),
            ])->groupByRaw("cluster, ruta, cliente_id, cliente, fecha_norm");
        } elseif ($agrupacion === 'cluster_jerarquia') {
            $query->select([
                DB::raw("COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') as cluster"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia1), ''), 'SIN J1') as j1"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia2), ''), 'SIN J2') as j2"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia3), ''), 'SIN J3') as j3"),
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha_norm"),
                DB::raw("SUM({$colExpr}) as valor"),
            ])->groupByRaw("cluster, j1, j2, j3, fecha_norm");
        } elseif ($agrupacion === 'cluster_ruta_jerarquia') {
            $query->select([
                DB::raw("COALESCE(NULLIF(TRIM(fv.cluster_t), ''), 'SIN CLUSTER') as cluster"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.ruta), ''), 'SIN RUTA') as ruta"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia1), ''), 'SIN J1') as j1"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia2), ''), 'SIN J2') as j2"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia3), ''), 'SIN J3') as j3"),
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha_norm"),
                DB::raw("SUM({$colExpr}) as valor"),
            ])->groupByRaw("cluster, ruta, j1, j2, j3, fecha_norm");
        } else { // ruta_jerarquia
            $query->select([
                DB::raw("COALESCE(NULLIF(TRIM(fv.ruta), ''), 'SIN RUTA') as ruta"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia1), ''), 'SIN J1') as j1"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia2), ''), 'SIN J2') as j2"),
                DB::raw("COALESCE(NULLIF(TRIM(fv.jerarquia3), ''), 'SIN J3') as j3"),
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha_norm"),
                DB::raw("SUM({$colExpr}) as valor"),
            ])->groupByRaw("ruta, j1, j2, j3, fecha_norm");
        }

        $rawRows = $query->get();

        // Fechas globales para la fila TOTAL GENERAL
        $fechasEmpresa = [];
        foreach ($rawRows as $r) {
            $fVal = substr((string) $r->fecha_norm, 0, 10);
            $vVal = (float) ($r->valor ?? 0.0);
            $fechasEmpresa[$fVal] = ($fechasEmpresa[$fVal] ?? 0.0) + $vVal;
        }

        $totalesConsolidado = $this->calcMetricasNodo($fechasEmpresa, $mesesMeta, $decimales);
        $totalesConsolidado['share_pct'] = 100.0;
        $totalesConsolidado['share_total_pct'] = 100.0;
        $totalGeneralTy = (float) $totalesConsolidado['total_ty'];

        $filasArbol = [];

        // Construcción de la jerarquía
        if ($agrupacion === 'cluster') {
            $porCluster = [];
            foreach ($rawRows as $r) {
                $cl = trim((string) $r->cluster);
                $f = substr((string) $r->fecha_norm, 0, 10);
                $v = (float) ($r->valor ?? 0.0);
                $porCluster[$cl][$f] = ($porCluster[$cl][$f] ?? 0.0) + $v;
            }

            $clustersOrdenados = $this->ordenarClusters(array_keys($porCluster));
            foreach ($clustersOrdenados as $cl) {
                $fDict = $porCluster[$cl] ?? [];
                $mCl = $this->calcMetricasNodo($fDict, $mesesMeta, $decimales);
                $sh = $totalGeneralTy > 0 ? round(($mCl['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0;
                $filasArbol[] = array_merge([
                    'id' => "c_{$cl}",
                    'label' => $cl,
                    'tipo' => 'cluster',
                    'nivel' => 0,
                    'share_pct' => $sh,
                    'share_total_pct' => $sh,
                    'children' => [],
                ], $mCl);
            }
        } elseif ($agrupacion === 'cluster_cliente') {
            // Cluster (Nivel 0) -> Ruta (Nivel 1) -> Cliente (Nivel 2)
            $rawTree = [];
            foreach ($rawRows as $r) {
                $cl = trim((string) $r->cluster);
                $rt = trim((string) $r->ruta);
                $cId = (int) ($r->cliente_id ?? 0);
                $cNom = trim((string) $r->cliente);
                $f = substr((string) $r->fecha_norm, 0, 10);
                $v = (float) ($r->valor ?? 0.0);

                $rawTree[$cl][$rt][$cId]['nombre'] = $cNom;
                $rawTree[$cl][$rt][$cId]['fechas'][$f] = ($rawTree[$cl][$rt][$cId]['fechas'][$f] ?? 0.0) + $v;
            }

            $clustersOrdenados = $this->ordenarClusters(array_keys($rawTree));
            foreach ($clustersOrdenados as $cl) {
                $rutasDict = $rawTree[$cl] ?? [];
                $fechasCl = [];
                $rutaNodes = [];

                foreach ($rutasDict as $rt => $clientesDict) {
                    $fechasRt = [];
                    $clienteNodes = [];

                    foreach ($clientesDict as $cId => $cData) {
                        $cNom = $cData['nombre'];
                        $fCli = $cData['fechas'] ?? [];
                        $this->fusionarFechas($fCli, $fechasRt);

                        $mCli = $this->calcMetricasNodo($fCli, $mesesMeta, $decimales);
                        $clienteNodes[] = array_merge([
                            'id' => "node_{$cl}__{$rt}__cli_{$cId}",
                            'label' => $cId > 0 ? "{$cNom} ({$cId})" : $cNom,
                            'tipo' => 'cliente',
                            'nivel' => 2,
                            'cliente_id' => $cId,
                            'cliente_nombre' => $cNom,
                            'share_pct' => 0.0,
                            'share_total_pct' => $totalGeneralTy > 0 ? round(($mCli['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                            'children' => [],
                        ], $mCli);
                    }

                    $this->fusionarFechas($fechasRt, $fechasCl);
                    $mRt = $this->calcMetricasNodo($fechasRt, $mesesMeta, $decimales);

                    foreach ($clienteNodes as &$cN) {
                        $cN['share_pct'] = $mRt['total_ty'] > 0 ? round(($cN['total_ty'] / $mRt['total_ty']) * 100.0, 1) : 0.0;
                    }
                    unset($cN);

                    usort($clienteNodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);

                    $rutaNodes[] = array_merge([
                        'id' => "node_{$cl}__{$rt}",
                        'label' => $rt,
                        'tipo' => 'ruta',
                        'nivel' => 1,
                        'share_pct' => 0.0,
                        'share_total_pct' => $totalGeneralTy > 0 ? round(($mRt['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                        'children' => $clienteNodes,
                    ], $mRt);
                }

                $mCl = $this->calcMetricasNodo($fechasCl, $mesesMeta, $decimales);
                foreach ($rutaNodes as &$rtN) {
                    $rtN['share_pct'] = $mCl['total_ty'] > 0 ? round(($rtN['total_ty'] / $mCl['total_ty']) * 100.0, 1) : 0.0;
                }
                unset($rtN);

                usort($rutaNodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);

                $shCl = $totalGeneralTy > 0 ? round(($mCl['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0;
                $filasArbol[] = array_merge([
                    'id' => "node_{$cl}",
                    'label' => $cl,
                    'tipo' => 'cluster',
                    'nivel' => 0,
                    'share_pct' => $shCl,
                    'share_total_pct' => $shCl,
                    'children' => $rutaNodes,
                ], $mCl);
            }
        } elseif ($agrupacion === 'cluster_jerarquia') {
            // Cluster (0) -> J1 (1) -> J2 (2) -> J3 (3)
            $rawTree = [];
            foreach ($rawRows as $r) {
                $cl = trim((string) $r->cluster);
                $j1 = trim((string) $r->j1);
                $j2 = trim((string) $r->j2);
                $j3 = trim((string) $r->j3);
                $f = substr((string) $r->fecha_norm, 0, 10);
                $v = (float) ($r->valor ?? 0.0);

                $rawTree[$cl][$j1][$j2][$j3][$f] = ($rawTree[$cl][$j1][$j2][$j3][$f] ?? 0.0) + $v;
            }

            $clustersOrdenados = $this->ordenarClusters(array_keys($rawTree));
            foreach ($clustersOrdenados as $cl) {
                $j1Dict = $rawTree[$cl] ?? [];
                $fechasCl = [];
                $j1Nodes = [];

                foreach ($j1Dict as $j1 => $j2Dict) {
                    $fechasJ1 = [];
                    $j2Nodes = [];

                    foreach ($j2Dict as $j2 => $j3Dict) {
                        $fechasJ2 = [];
                        $j3Nodes = [];

                        foreach ($j3Dict as $j3 => $fJ3) {
                            $this->fusionarFechas($fJ3, $fechasJ2);
                            $mJ3 = $this->calcMetricasNodo($fJ3, $mesesMeta, $decimales);
                            $j3Nodes[] = array_merge([
                                'id' => "node_{$cl}__{$j1}__{$j2}__{$j3}",
                                'label' => $j3,
                                'tipo' => 'j3',
                                'nivel' => 3,
                                'share_pct' => 0.0,
                                'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ3['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                                'children' => [],
                            ], $mJ3);
                        }

                        $this->fusionarFechas($fechasJ2, $fechasJ1);
                        $mJ2 = $this->calcMetricasNodo($fechasJ2, $mesesMeta, $decimales);
                        foreach ($j3Nodes as &$j3N) {
                            $j3N['share_pct'] = $mJ2['total_ty'] > 0 ? round(($j3N['total_ty'] / $mJ2['total_ty']) * 100.0, 1) : 0.0;
                        }
                        unset($j3N);

                        usort($j3Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                        $j2Nodes[] = array_merge([
                            'id' => "node_{$cl}__{$j1}__{$j2}",
                            'label' => $j2,
                            'tipo' => 'j2',
                            'nivel' => 2,
                            'share_pct' => 0.0,
                            'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ2['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                            'children' => $j3Nodes,
                        ], $mJ2);
                    }

                    $this->fusionarFechas($fechasJ1, $fechasCl);
                    $mJ1 = $this->calcMetricasNodo($fechasJ1, $mesesMeta, $decimales);
                    foreach ($j2Nodes as &$j2N) {
                        $j2N['share_pct'] = $mJ1['total_ty'] > 0 ? round(($j2N['total_ty'] / $mJ1['total_ty']) * 100.0, 1) : 0.0;
                    }
                    unset($j2N);

                    usort($j2Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                    $j1Nodes[] = array_merge([
                        'id' => "node_{$cl}__{$j1}",
                        'label' => $j1,
                        'tipo' => 'j1',
                        'nivel' => 1,
                        'share_pct' => 0.0,
                        'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ1['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                        'children' => $j2Nodes,
                    ], $mJ1);
                }

                $mCl = $this->calcMetricasNodo($fechasCl, $mesesMeta, $decimales);
                foreach ($j1Nodes as &$j1N) {
                    $j1N['share_pct'] = $mCl['total_ty'] > 0 ? round(($j1N['total_ty'] / $mCl['total_ty']) * 100.0, 1) : 0.0;
                }
                unset($j1N);

                usort($j1Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                $shCl = $totalGeneralTy > 0 ? round(($mCl['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0;
                $filasArbol[] = array_merge([
                    'id' => "node_{$cl}",
                    'label' => $cl,
                    'tipo' => 'cluster',
                    'nivel' => 0,
                    'share_pct' => $shCl,
                    'share_total_pct' => $shCl,
                    'children' => $j1Nodes,
                ], $mCl);
            }
        } elseif ($agrupacion === 'cluster_ruta_jerarquia') {
            // Cluster (0) -> Ruta (1) -> J1 (2) -> J2 (3) -> J3 (4)
            $rawTree = [];
            foreach ($rawRows as $r) {
                $cl = trim((string) $r->cluster);
                $rt = trim((string) $r->ruta);
                $j1 = trim((string) $r->j1);
                $j2 = trim((string) $r->j2);
                $j3 = trim((string) $r->j3);
                $f = substr((string) $r->fecha_norm, 0, 10);
                $v = (float) ($r->valor ?? 0.0);

                $rawTree[$cl][$rt][$j1][$j2][$j3][$f] = ($rawTree[$cl][$rt][$j1][$j2][$j3][$f] ?? 0.0) + $v;
            }

            $clustersOrdenados = $this->ordenarClusters(array_keys($rawTree));
            foreach ($clustersOrdenados as $cl) {
                $rutasDict = $rawTree[$cl] ?? [];
                $fechasCl = [];
                $rutaNodes = [];

                foreach ($rutasDict as $rt => $j1Dict) {
                    $fechasRt = [];
                    $j1Nodes = [];

                    foreach ($j1Dict as $j1 => $j2Dict) {
                        $fechasJ1 = [];
                        $j2Nodes = [];

                        foreach ($j2Dict as $j2 => $j3Dict) {
                            $fechasJ2 = [];
                            $j3Nodes = [];

                            foreach ($j3Dict as $j3 => $fJ3) {
                                $this->fusionarFechas($fJ3, $fechasJ2);
                                $mJ3 = $this->calcMetricasNodo($fJ3, $mesesMeta, $decimales);
                                $j3Nodes[] = array_merge([
                                    'id' => "node_{$cl}__{$rt}__{$j1}__{$j2}__{$j3}",
                                    'label' => $j3,
                                    'tipo' => 'j3',
                                    'nivel' => 4,
                                    'share_pct' => 0.0,
                                    'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ3['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                                    'children' => [],
                                ], $mJ3);
                            }

                            $this->fusionarFechas($fechasJ2, $fechasJ1);
                            $mJ2 = $this->calcMetricasNodo($fechasJ2, $mesesMeta, $decimales);
                            foreach ($j3Nodes as &$j3N) {
                                $j3N['share_pct'] = $mJ2['total_ty'] > 0 ? round(($j3N['total_ty'] / $mJ2['total_ty']) * 100.0, 1) : 0.0;
                            }
                            unset($j3N);

                            usort($j3Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                            $j2Nodes[] = array_merge([
                                'id' => "node_{$cl}__{$rt}__{$j1}__{$j2}",
                                'label' => $j2,
                                'tipo' => 'j2',
                                'nivel' => 3,
                                'share_pct' => 0.0,
                                'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ2['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                                'children' => $j3Nodes,
                            ], $mJ2);
                        }

                        $this->fusionarFechas($fechasJ1, $fechasRt);
                        $mJ1 = $this->calcMetricasNodo($fechasJ1, $mesesMeta, $decimales);
                        foreach ($j2Nodes as &$j2N) {
                            $j2N['share_pct'] = $mJ1['total_ty'] > 0 ? round(($j2N['total_ty'] / $mJ1['total_ty']) * 100.0, 1) : 0.0;
                        }
                        unset($j2N);

                        usort($j2Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                        $j1Nodes[] = array_merge([
                            'id' => "node_{$cl}__{$rt}__{$j1}",
                            'label' => $j1,
                            'tipo' => 'j1',
                            'nivel' => 2,
                            'share_pct' => 0.0,
                            'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ1['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                            'children' => $j2Nodes,
                        ], $mJ1);
                    }

                    $this->fusionarFechas($fechasRt, $fechasCl);
                    $mRt = $this->calcMetricasNodo($fechasRt, $mesesMeta, $decimales);
                    foreach ($j1Nodes as &$j1N) {
                        $j1N['share_pct'] = $mRt['total_ty'] > 0 ? round(($j1N['total_ty'] / $mRt['total_ty']) * 100.0, 1) : 0.0;
                    }
                    unset($j1N);

                    usort($j1Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                    $rutaNodes[] = array_merge([
                        'id' => "node_{$cl}__{$rt}",
                        'label' => $rt,
                        'tipo' => 'ruta',
                        'nivel' => 1,
                        'share_pct' => 0.0,
                        'share_total_pct' => $totalGeneralTy > 0 ? round(($mRt['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                        'children' => $j1Nodes,
                    ], $mRt);
                }

                $mCl = $this->calcMetricasNodo($fechasCl, $mesesMeta, $decimales);
                foreach ($rutaNodes as &$rtN) {
                    $rtN['share_pct'] = $mCl['total_ty'] > 0 ? round(($rtN['total_ty'] / $mCl['total_ty']) * 100.0, 1) : 0.0;
                }
                unset($rtN);

                usort($rutaNodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                $shCl = $totalGeneralTy > 0 ? round(($mCl['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0;
                $filasArbol[] = array_merge([
                    'id' => "node_{$cl}",
                    'label' => $cl,
                    'tipo' => 'cluster',
                    'nivel' => 0,
                    'share_pct' => $shCl,
                    'share_total_pct' => $shCl,
                    'children' => $rutaNodes,
                ], $mCl);
            }
        } else { // ruta_jerarquia: Ruta (0) -> J1 (1) -> J2 (2) -> J3 (3)
            $rawTree = [];
            foreach ($rawRows as $r) {
                $rt = trim((string) $r->ruta);
                $j1 = trim((string) $r->j1);
                $j2 = trim((string) $r->j2);
                $j3 = trim((string) $r->j3);
                $f = substr((string) $r->fecha_norm, 0, 10);
                $v = (float) ($r->valor ?? 0.0);

                $rawTree[$rt][$j1][$j2][$j3][$f] = ($rawTree[$rt][$j1][$j2][$j3][$f] ?? 0.0) + $v;
            }

            ksort($rawTree);
            foreach ($rawTree as $rt => $j1Dict) {
                $fechasRt = [];
                $j1Nodes = [];

                foreach ($j1Dict as $j1 => $j2Dict) {
                    $fechasJ1 = [];
                    $j2Nodes = [];

                    foreach ($j2Dict as $j2 => $j3Dict) {
                        $fechasJ2 = [];
                        $j3Nodes = [];

                        foreach ($j3Dict as $j3 => $fJ3) {
                            $this->fusionarFechas($fJ3, $fechasJ2);
                            $mJ3 = $this->calcMetricasNodo($fJ3, $mesesMeta, $decimales);
                            $j3Nodes[] = array_merge([
                                'id' => "node_{$rt}__{$j1}__{$j2}__{$j3}",
                                'label' => $j3,
                                'tipo' => 'j3',
                                'nivel' => 3,
                                'share_pct' => 0.0,
                                'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ3['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                                'children' => [],
                            ], $mJ3);
                        }

                        $this->fusionarFechas($fechasJ2, $fechasJ1);
                        $mJ2 = $this->calcMetricasNodo($fechasJ2, $mesesMeta, $decimales);
                        foreach ($j3Nodes as &$j3N) {
                            $j3N['share_pct'] = $mJ2['total_ty'] > 0 ? round(($j3N['total_ty'] / $mJ2['total_ty']) * 100.0, 1) : 0.0;
                        }
                        unset($j3N);

                        usort($j3Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                        $j2Nodes[] = array_merge([
                            'id' => "node_{$rt}__{$j1}__{$j2}",
                            'label' => $j2,
                            'tipo' => 'j2',
                            'nivel' => 2,
                            'share_pct' => 0.0,
                            'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ2['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                            'children' => $j3Nodes,
                        ], $mJ2);
                    }

                    $this->fusionarFechas($fechasJ1, $fechasRt);
                    $mJ1 = $this->calcMetricasNodo($fechasJ1, $mesesMeta, $decimales);
                    foreach ($j2Nodes as &$j2N) {
                        $j2N['share_pct'] = $mJ1['total_ty'] > 0 ? round(($j2N['total_ty'] / $mJ1['total_ty']) * 100.0, 1) : 0.0;
                    }
                    unset($j2N);

                    usort($j2Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                    $j1Nodes[] = array_merge([
                        'id' => "node_{$rt}__{$j1}",
                        'label' => $j1,
                        'tipo' => 'j1',
                        'nivel' => 1,
                        'share_pct' => 0.0,
                        'share_total_pct' => $totalGeneralTy > 0 ? round(($mJ1['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0,
                        'children' => $j2Nodes,
                    ], $mJ1);
                }

                $mRt = $this->calcMetricasNodo($fechasRt, $mesesMeta, $decimales);
                foreach ($j1Nodes as &$j1N) {
                    $j1N['share_pct'] = $mRt['total_ty'] > 0 ? round(($j1N['total_ty'] / $mRt['total_ty']) * 100.0, 1) : 0.0;
                }
                unset($j1N);

                usort($j1Nodes, fn ($a, $b) => $b['total_ty'] <=> $a['total_ty']);
                $shRt = $totalGeneralTy > 0 ? round(($mRt['total_ty'] / $totalGeneralTy) * 100.0, 1) : 0.0;
                $filasArbol[] = array_merge([
                    'id' => "node_{$rt}",
                    'label' => $rt,
                    'tipo' => 'ruta',
                    'nivel' => 0,
                    'share_pct' => $shRt,
                    'share_total_pct' => $shRt,
                    'children' => $j1Nodes,
                ], $mRt);
            }
        }

        return [
            'meses' => $mesesMeta,
            'filas' => $filasArbol,
            'totales' => $totalesConsolidado,
            'metrica' => $metrica,
            'unidad' => $unidadLabel,
            'agrupacion' => $agrupacion,
            'fecha_inicio' => $fIni->format('Y-m-d'),
            'fecha_corte' => $fCorte->format('Y-m-d'),
        ];
    }

    /**
     * Construye la lista de meses comprendidos entre fIni y fCorte con sus límites TY y comparables LY.
     */
    private function generarMesesRango(Carbon $fIni, Carbon $fCorte): array
    {
        $resultado = [];
        $cur = $fIni->copy()->startOfMonth();
        $corteLimit = $fCorte->copy()->startOfDay();

        while ($cur->lessThanOrEqualTo($corteLimit)) {
            $y = $cur->year;
            $m = $cur->month;
            $uDiaTy = $cur->daysInMonth;

            $esMesCorte = ($y === $fCorte->year && $m === $fCorte->month);
            $esCerrado = !($esMesCorte && $fCorte->day < $uDiaTy);

            $tyDiaIni = ($y === $fIni->year && $m === $fIni->month) ? $fIni->day : 1;
            $tyDiaFin = $esMesCorte ? $fCorte->day : $uDiaTy;

            $tyFIni = Carbon::createFromDate($y, $m, $tyDiaIni)->format('Y-m-d');
            $tyFFin = Carbon::createFromDate($y, $m, $tyDiaFin)->format('Y-m-d');

            $lyY = $y - 1;
            $lyUDia = Carbon::createFromDate($lyY, $m, 1)->daysInMonth;

            $lyDiaIni = min($tyDiaIni, $lyUDia);
            $lyDiaFinCorte = min($tyDiaFin, $lyUDia);
            $lyDiaFinFull = $lyUDia;

            $lyFIni = Carbon::createFromDate($lyY, $m, $lyDiaIni)->format('Y-m-d');
            $lyFFinCorte = Carbon::createFromDate($lyY, $m, $lyDiaFinCorte)->format('Y-m-d');
            $lyFFinFull = Carbon::createFromDate($lyY, $m, $lyDiaFinFull)->format('Y-m-d');

            $nombreMes = self::MESES_NOMBRES[$m] ?? "M{$m}";
            $etiquetaTy = $esCerrado ? "{$nombreMes} {$y}" : "{$nombreMes} (al {$tyDiaFin})";
            $etiquetaLy = $esCerrado ? "{$nombreMes} {$lyY}" : "{$nombreMes} {$lyY} (al {$lyDiaFinCorte})";

            $resultado[] = [
                'key' => sprintf('%04d-%02d', $y, $m),
                'anio' => $y,
                'mes' => $m,
                'nombre' => $nombreMes,
                'es_cerrado' => $esCerrado,
                'etiqueta' => $etiquetaTy,
                'etiqueta_ly' => $etiquetaLy,
                'etiqueta_ly_full' => "{$nombreMes} {$lyY} (Cerrado)",
                'dias_evaluados' => $tyDiaFin - $tyDiaIni + 1,
                'dias_totales_mes' => $uDiaTy,
                'ty_ini' => $tyFIni,
                'ty_fin' => $tyFFin,
                'ly_ini' => $lyFIni,
                'ly_fin_corte' => $lyFFinCorte,
                'ly_fin_full' => $lyFFinFull,
            ];

            $cur->addMonthNoOverflow()->startOfMonth();
        }

        return $resultado;
    }

    /**
     * Calcula métricas TY, LY corte, LY full, Dif y % Crec para cada mes y consolidado total.
     */
    private function calcMetricasNodo(array $fechasDict, array $mesesMeta, int $decimales = 2): array
    {
        $valoresMes = [];
        $totalTy = 0.0;
        $totalLy = 0.0;
        $totalLyFull = 0.0;

        foreach ($mesesMeta as $m) {
            $mKey = $m['key'];
            $tyIni = $m['ty_ini'];
            $tyFin = $m['ty_fin'];
            $lyIni = $m['ly_ini'];
            $lyFinCorte = $m['ly_fin_corte'];
            $lyFinFull = $m['ly_fin_full'];

            $valTy = 0.0;
            $valLy = 0.0;
            $valLyFull = 0.0;

            foreach ($fechasDict as $f => $v) {
                if ($f >= $tyIni && $f <= $tyFin) {
                    $valTy += $v;
                }
                if ($f >= $lyIni && $f <= $lyFinCorte) {
                    $valLy += $v;
                }
                if ($f >= $lyIni && $f <= $lyFinFull) {
                    $valLyFull += $v;
                }
            }

            $dif = $valTy - $valLy;
            $crecPct = $valLy > 0 ? (($valTy - $valLy) / $valLy) * 100.0 : ($valTy > 0 ? 100.0 : 0.0);

            $valoresMes[$mKey] = [
                'ty' => round($valTy, $decimales),
                'ly' => round($valLy, $decimales),
                'ly_full' => round($valLyFull, $decimales),
                'dif' => round($dif, $decimales),
                'crec_pct' => round($crecPct, 1),
                'es_cerrado' => $m['es_cerrado'],
            ];

            $totalTy += $valTy;
            $totalLy += $valLy;
            $totalLyFull += $valLyFull;
        }

        $difTotal = $totalTy - $totalLy;
        $crecTotalPct = $totalLy > 0 ? (($totalTy - $totalLy) / $totalLy) * 100.0 : ($totalTy > 0 ? 100.0 : 0.0);

        return [
            'valores_mes' => $valoresMes,
            'total_ty' => round($totalTy, $decimales),
            'total_ly' => round($totalLy, $decimales),
            'total_ly_full' => round($totalLyFull, $decimales),
            'dif_total' => round($difTotal, $decimales),
            'crec_total_pct' => round($crecTotalPct, 1),
        ];
    }

    /**
     * Fusiona arreglos de fechas asociadas a valores numéricos.
     */
    private function fusionarFechas(array $origen, array &$destino): void
    {
        foreach ($origen as $f => $v) {
            $destino[$f] = ($destino[$f] ?? 0.0) + $v;
        }
    }

    /**
     * Ordena los clusters según el estándar comercial conocido.
     */
    private function ordenarClusters(array $clusters): array
    {
        $ordenados = [];
        foreach (self::ORDEN_ESTANDAR_CLUSTERS as $c) {
            if (in_array($c, $clusters, true)) {
                $ordenados[] = $c;
            }
        }
        sort($clusters);
        foreach ($clusters as $c) {
            if (!in_array($c, $ordenados, true)) {
                $ordenados[] = $c;
            }
        }
        return $ordenados;
    }
}
