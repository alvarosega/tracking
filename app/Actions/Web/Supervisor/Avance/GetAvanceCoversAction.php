<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use App\Actions\Web\Supervisor\Preventas\CoversClassifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceCoversAction
{
    private const UMBRAL_COVERS_PCT = 50.0;

    /**
     * Calcula la matriz consolidada de covers por ruta en el rango mensual o personalizado.
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaCorte
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        array $canales = [],
        array $rutas = []
    ): array {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        // 1. Fechas del Rango Evaluado
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_preventas')
                ->where(function ($q): void {
                    $q->where('monto_final', '>', 0)
                      ->orWhere('descuento', '>', 0);
                })
                ->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }

        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        if ($fechaInicio > $fechaCorte) {
            $temp = $fechaInicio;
            $fechaInicio = $fechaCorte;
            $fechaCorte = $temp;
        }

        // 2. Catálogo de Rutas desde dim_rutas
        $dimRutasRaw = DB::connection('supervisor')->table('dim_rutas')->get();
        $dimRutas = [];
        foreach ($dimRutasRaw as $dr) {
            $rKey = strtoupper(trim((string) $dr->ruta));
            $dimRutas[$rKey] = [
                'vendedor' => trim((string) ($dr->vendedor ?: 'No Asignado')),
                'canal' => trim((string) ($dr->canal ?: 'GENERAL')),
            ];
        }

        // 3. Consulta de preventas en el rango
        $query = DB::connection('supervisor')->table('fact_preventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where(function ($q): void {
                $q->where('monto_final', '>', 0)
                  ->orWhere('descuento', '>', 0);
            })
            ->select([
                'fecha_norm',
                'ruta',
                'cliente_id',
                'codigo_cliente',
                'cliente',
                'codigo_producto',
                'producto',
                'categoria',
                'cantidad',
                'precio_lista',
                'descuento',
                'monto',
                'monto_final',
            ]);

        $rutasFilterClean = array_values(array_filter(array_map(fn ($r) => strtoupper(trim((string) $r)), $rutas)));
        if (!empty($rutasFilterClean) && !in_array('TODAS', $rutasFilterClean, true) && !in_array('__NINGUNA__', $rutasFilterClean, true)) {
            $query->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasFilterClean);
        }

        $preventas = $query->orderBy('ruta')->orderBy('cliente_id')->orderBy('fecha_norm')->get();

        // 4. Agrupar por ruta -> cliente_id -> fecha_norm
        $rutasMap = [];
        $canalesFilterClean = array_values(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $canales)));
        $filtrarCanales = !empty($canalesFilterClean) && !in_array('TODOS', $canalesFilterClean, true) && !in_array('__NINGUNO__', $canalesFilterClean, true);

        foreach ($preventas as $r) {
            $rKey = strtoupper(trim((string) $r->ruta));
            $canalRuta = $dimRutas[$rKey]['canal'] ?? 'GENERAL';

            if ($filtrarCanales && !in_array(strtoupper($canalRuta), $canalesFilterClean, true)) {
                continue;
            }

            $cid = (int) $r->cliente_id;
            $fNorm = (string) $r->fecha_norm;

            if (!isset($rutasMap[$rKey])) {
                $rutasMap[$rKey] = [
                    'ruta' => $rKey,
                    'vendedor' => $dimRutas[$rKey]['vendedor'] ?? 'No Asignado',
                    'canal' => $canalRuta,
                    'clientes' => [],
                    'dias' => [],
                ];
            }

            if (!isset($rutasMap[$rKey]['dias'][$fNorm])) {
                $rutasMap[$rKey]['dias'][$fNorm] = [
                    'compradores' => [],
                    'con_cover' => [],
                    'cover1' => 0,
                    'cover2' => 0,
                    'cover3' => 0,
                    'descuento' => 0.0,
                ];
            }

            if (!isset($rutasMap[$rKey]['clientes'][$cid])) {
                $rutasMap[$rKey]['clientes'][$cid] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => (string) ($r->codigo_cliente ?: $cid),
                    'cliente' => (string) ($r->cliente ?: 'Cliente #' . $cid),
                    'fechas' => [],
                ];
            }

            if (!isset($rutasMap[$rKey]['clientes'][$cid]['fechas'][$fNorm])) {
                $rutasMap[$rKey]['clientes'][$cid]['fechas'][$fNorm] = [];
            }
            $rutasMap[$rKey]['clientes'][$cid]['fechas'][$fNorm][] = $r;
        }

        // 5. Consolidar métricas por ruta y totales globales
        $rutasConsolidadas = [];
        $totalGlobalClientes = 0;
        $totalGlobalConCover = 0;
        $totalGlobalCover1 = 0;
        $totalGlobalCover2 = 0;
        $totalGlobalCover3 = 0;
        $totalGlobalDescuento = 0.0;
        $totalGlobalMonto = 0.0;
        $totalDiasActivosSuma = 0;
        $totalDiasCumplidosSuma = 0;

        foreach ($rutasMap as $rKey => $rData) {
            $clientesTotal = count($rData['clientes']);
            $clientesConCover = 0;
            $clientesCover1 = 0;
            $clientesCover2 = 0;
            $clientesCover3 = 0;
            $descuentoRuta = 0.0;
            $montoRuta = 0.0;

            foreach ($rData['clientes'] as $cid => $cData) {
                $clienteTieneCoverEnMes = false;
                $clienteTieneCover1 = false;
                $clienteTieneCover2 = false;
                $clienteTieneCover3 = false;

                foreach ($cData['fechas'] as $fNorm => $items) {
                    $rData['dias'][$fNorm]['compradores'][$cid] = true;

                    $clasif = CoversClassifier::clasificar($items);
                    $descuentoRuta += $clasif['descuento_total'];
                    $rData['dias'][$fNorm]['descuento'] += $clasif['descuento_total'];

                    foreach ($items as $it) {
                        $montoRuta += (float) ($it->monto_final ?? 0.0);
                    }

                    if ($clasif['tiene_descuento']) {
                        $clienteTieneCoverEnMes = true;
                        $rData['dias'][$fNorm]['con_cover'][$cid] = true;
                    }
                    if ($clasif['tiene_cover1']) {
                        $clienteTieneCover1 = true;
                        $rData['dias'][$fNorm]['cover1']++;
                    }
                    if ($clasif['tiene_cover2']) {
                        $clienteTieneCover2 = true;
                        $rData['dias'][$fNorm]['cover2']++;
                    }
                    if ($clasif['tiene_cover3']) {
                        $clienteTieneCover3 = true;
                        $rData['dias'][$fNorm]['cover3']++;
                    }
                }

                if ($clienteTieneCoverEnMes) $clientesConCover++;
                if ($clienteTieneCover1) $clientesCover1++;
                if ($clienteTieneCover2) $clientesCover2++;
                if ($clienteTieneCover3) $clientesCover3++;
            }

            $diasActivos = count($rData['dias']);
            $diasCumplidos = 0;
            foreach ($rData['dias'] as $fNorm => $dInfo) {
                $comp = count($dInfo['compradores']);
                $cov = count($dInfo['con_cover']);
                $pct = $comp > 0 ? ($cov / $comp) * 100 : 0;
                if ($pct >= self::UMBRAL_COVERS_PCT) {
                    $diasCumplidos++;
                }
            }

            $coberturaPct = $clientesTotal > 0 ? round(($clientesConCover / $clientesTotal) * 100, 1) : 0.0;
            $pctDiasCumplidos = $diasActivos > 0 ? round(($diasCumplidos / $diasActivos) * 100, 1) : 0.0;

            $rutasConsolidadas[] = [
                'ruta' => $rKey,
                'vendedor' => $rData['vendedor'],
                'canal' => $rData['canal'],
                'clientes_total' => $clientesTotal,
                'clientes_con_cover' => $clientesConCover,
                'clientes_sin_cover' => $clientesTotal - $clientesConCover,
                'cover1_count' => $clientesCover1,
                'cover2_count' => $clientesCover2,
                'cover3_count' => $clientesCover3,
                'cobertura_pct' => $coberturaPct,
                'dias_activos' => $diasActivos,
                'dias_cumplidos' => $diasCumplidos,
                'pct_dias_cumplidos' => $pctDiasCumplidos,
                'cumple_mes' => ($coberturaPct >= self::UMBRAL_COVERS_PCT),
                'descuento_total' => round($descuentoRuta, 2),
                'monto_total' => round($montoRuta, 2),
            ];

            $totalGlobalClientes += $clientesTotal;
            $totalGlobalConCover += $clientesConCover;
            $totalGlobalCover1 += $clientesCover1;
            $totalGlobalCover2 += $clientesCover2;
            $totalGlobalCover3 += $clientesCover3;
            $totalGlobalDescuento += $descuentoRuta;
            $totalGlobalMonto += $montoRuta;
            $totalDiasActivosSuma += $diasActivos;
            $totalDiasCumplidosSuma += $diasCumplidos;
        }

        // Ordenar alfabéticamente por código de ruta
        usort($rutasConsolidadas, fn ($a, $b) => strcmp($a['ruta'], $b['ruta']));

        $totalRutas = count($rutasConsolidadas);
        $coberturaGlobalPct = $totalGlobalClientes > 0 ? round(($totalGlobalConCover / $totalGlobalClientes) * 100, 1) : 0.0;
        $diasActivosPromedio = $totalRutas > 0 ? round($totalDiasActivosSuma / $totalRutas, 1) : 0.0;
        $diasCumplidosPromedio = $totalRutas > 0 ? round($totalDiasCumplidosSuma / $totalRutas, 1) : 0.0;

        return [
            'rutas' => $rutasConsolidadas,
            'totales' => [
                'total_rutas' => $totalRutas,
                'clientes_total' => $totalGlobalClientes,
                'clientes_con_cover' => $totalGlobalConCover,
                'clientes_sin_cover' => $totalGlobalClientes - $totalGlobalConCover,
                'cover1_count' => $totalGlobalCover1,
                'cover2_count' => $totalGlobalCover2,
                'cover3_count' => $totalGlobalCover3,
                'cobertura_pct' => $coberturaGlobalPct,
                'dias_activos_promedio' => $diasActivosPromedio,
                'dias_cumplidos_promedio' => $diasCumplidosPromedio,
                'pct_dias_cumplidos_global' => $totalDiasActivosSuma > 0 ? round(($totalDiasCumplidosSuma / $totalDiasActivosSuma) * 100, 1) : 0.0,
                'descuento_total' => round($totalGlobalDescuento, 2),
                'monto_total' => round($totalGlobalMonto, 2),
            ],
            'rango' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_corte' => $fechaCorte,
                'umbral_covers_pct' => self::UMBRAL_COVERS_PCT,
            ],
        ];
    }
}
