<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceCoversAction
{
    private const UMBRAL_COVERS_PCT = 50.0;

    /**
     * Calcula la matriz consolidada de covers por ruta en el rango mensual o personalizado
     * extrayendo las ventas directamente de la tabla fact_ventas y clasificando por escala_producto:
     * - Cover 1: COD-SHTDB1126 (Sedal SH)
     * - Cover 2: COD-PEPTDB1126 (Pepsodent)
     * - Cover 3: SEDAL-SCHT 3-0323- TDB / SEDAL-SCHT 3-0323 - TDB (Sedal Sachet)
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

        // 1. Fechas del Rango Evaluado desde fact_ventas
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')
                ->where(function ($q): void {
                    $q->where('monto_final', '>', 0)
                      ->orWhere('descuento', '>', 0);
                })
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
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

        // 3. Consulta de ventas en el rango desde fact_ventas
        $query = DB::connection('supervisor')->table('fact_ventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where(function ($q): void {
                $q->where('monto_final', '>', 0)
                  ->orWhere('descuento', '>', 0);
            })
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            })
            ->select([
                'fecha_norm',
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(ruta)), ''), 'SIN RUTA') as ruta"),
                'cliente_id',
                'cliente',
                'codigo',
                'producto',
                'categoria',
                'cantidad',
                'descuento',
                'monto_final',
                'monto',
                'escala_producto',
                'canal',
                'vendedor',
            ]);

        $rutasFilterClean = array_values(array_filter(array_map(fn ($r) => strtoupper(trim((string) $r)), $rutas)));
        if (!empty($rutasFilterClean) && !in_array('TODAS', $rutasFilterClean, true) && !in_array('__NINGUNA__', $rutasFilterClean, true)) {
            $query->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasFilterClean);
        }

        $canalesFilterClean = array_values(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $canales)));
        $filtrarCanales = !empty($canalesFilterClean) && !in_array('TODOS', $canalesFilterClean, true) && !in_array('__NINGUNO__', $canalesFilterClean, true);

        $ventas = $query->orderBy('ruta')->orderBy('cliente_id')->orderBy('fecha_norm')->get();

        // 4. Agrupar por ruta -> cliente_id
        $rutasMap = [];

        foreach ($ventas as $r) {
            $rKey = strtoupper(trim((string) $r->ruta));
            $canalRuta = $dimRutas[$rKey]['canal'] ?? trim((string) ($r->canal ?: 'GENERAL'));
            $vendedorRuta = $dimRutas[$rKey]['vendedor'] ?? trim((string) ($r->vendedor ?: 'No Asignado'));

            if ($filtrarCanales && !in_array(strtoupper($canalRuta), $canalesFilterClean, true)) {
                continue;
            }

            $cid = (int) $r->cliente_id;

            if (!isset($rutasMap[$rKey])) {
                $rutasMap[$rKey] = [
                    'ruta' => $rKey,
                    'vendedor' => $vendedorRuta,
                    'canal' => $canalRuta,
                    'clientes' => [],
                    'descuento_total' => 0.0,
                    'monto_total' => 0.0,
                    'cover1_cantidad' => 0,
                    'cover2_cantidad' => 0,
                    'cover3_cantidad' => 0,
                    'cantidad_total_covers' => 0,
                    'cantidad_total' => 0,
                ];
            }

            if (!isset($rutasMap[$rKey]['clientes'][$cid])) {
                $rutasMap[$rKey]['clientes'][$cid] = [
                    'cliente_id' => $cid,
                    'cliente' => (string) ($r->cliente ?: 'Cliente #' . $cid),
                    'tiene_cover1' => false,
                    'tiene_cover2' => false,
                    'tiene_cover3' => false,
                    'tiene_cover' => false,
                    'cantidad_cover1' => 0,
                    'cantidad_cover2' => 0,
                    'cantidad_cover3' => 0,
                    'cantidad_covers' => 0,
                    'cantidad_total' => 0,
                    'descuento' => 0.0,
                    'monto' => 0.0,
                ];
            }

            $montoRow = (float) ($r->monto_final ?? ($r->monto ?? 0.0));
            $descRow = (float) ($r->descuento ?? 0.0);
            $cantRow = (int) ($r->cantidad ?? 0);

            $rutasMap[$rKey]['monto_total'] += $montoRow;
            $rutasMap[$rKey]['cantidad_total'] += $cantRow;
            $rutasMap[$rKey]['clientes'][$cid]['monto'] += $montoRow;
            $rutasMap[$rKey]['clientes'][$cid]['cantidad_total'] += $cantRow;

            // Clasificación precisa por escala_producto según los códigos solicitados
            $escalaNorm = strtoupper(trim((string) ($r->escala_producto ?? '')));
            $escalaSinEspacios = str_replace(' ', '', $escalaNorm);

            // Cover 1: COD-SHTDB1126
            $esCover1 = str_contains($escalaSinEspacios, 'COD-SHTDB1126');

            // Cover 2: COD-PEPTDB1126
            $esCover2 = str_contains($escalaSinEspacios, 'COD-PEPTDB1126');

            // Cover 3: SEDAL-SCHT 3-0323 - TDB (Sedal Sachet 40ml)
            $esCover3 = str_contains($escalaSinEspacios, 'SEDAL-SCHT3-0323-TDB')
                || (str_contains($escalaSinEspacios, 'SEDAL-SCHT') && str_contains($escalaSinEspacios, '0323'));

            if ($esCover1) {
                $rutasMap[$rKey]['clientes'][$cid]['tiene_cover1'] = true;
                $rutasMap[$rKey]['clientes'][$cid]['cantidad_cover1'] += $cantRow;
                $rutasMap[$rKey]['cover1_cantidad'] += $cantRow;
            }
            if ($esCover2) {
                $rutasMap[$rKey]['clientes'][$cid]['tiene_cover2'] = true;
                $rutasMap[$rKey]['clientes'][$cid]['cantidad_cover2'] += $cantRow;
                $rutasMap[$rKey]['cover2_cantidad'] += $cantRow;
            }
            if ($esCover3) {
                $rutasMap[$rKey]['clientes'][$cid]['tiene_cover3'] = true;
                $rutasMap[$rKey]['clientes'][$cid]['cantidad_cover3'] += $cantRow;
                $rutasMap[$rKey]['cover3_cantidad'] += $cantRow;
            }

            if ($esCover1 || $esCover2 || $esCover3) {
                $rutasMap[$rKey]['clientes'][$cid]['tiene_cover'] = true;
                $rutasMap[$rKey]['clientes'][$cid]['cantidad_covers'] += $cantRow;
                $rutasMap[$rKey]['cantidad_total_covers'] += $cantRow;
                $rutasMap[$rKey]['descuento_total'] += $descRow;
                $rutasMap[$rKey]['clientes'][$cid]['descuento'] += $descRow;
            }
        }

        // 5. Consolidar métricas por ruta y totales globales
        $rutasConsolidadas = [];
        $totalGlobalClientes = 0;
        $totalGlobalConCover = 0;
        $totalGlobalCover1 = 0;
        $totalGlobalCover2 = 0;
        $totalGlobalCover3 = 0;
        $totalGlobalCover1Cantidad = 0;
        $totalGlobalCover2Cantidad = 0;
        $totalGlobalCover3Cantidad = 0;
        $totalGlobalCantidadCovers = 0;
        $totalGlobalCantidad = 0;
        $totalGlobalDescuento = 0.0;
        $totalGlobalMonto = 0.0;

        foreach ($rutasMap as $rKey => $rData) {
            $clientesTotal = count($rData['clientes']);
            $clientesConCover = 0;
            $clientesCover1 = 0;
            $clientesCover2 = 0;
            $clientesCover3 = 0;

            foreach ($rData['clientes'] as $c) {
                if ($c['tiene_cover']) {
                    $clientesConCover++;
                }
                if ($c['tiene_cover1']) {
                    $clientesCover1++;
                }
                if ($c['tiene_cover2']) {
                    $clientesCover2++;
                }
                if ($c['tiene_cover3']) {
                    $clientesCover3++;
                }
            }

            $coberturaPct = $clientesTotal > 0 ? round(($clientesConCover / $clientesTotal) * 100, 1) : 0.0;

            $rutasConsolidadas[] = [
                'ruta' => $rKey,
                'vendedor' => $rData['vendedor'],
                'canal' => $rData['canal'],
                'clientes_total' => $clientesTotal,
                'clientes_con_cover' => $clientesConCover,
                'clientes_sin_cover' => $clientesTotal - $clientesConCover,
                'cover1_count' => $clientesCover1,
                'cover1_cantidad' => $rData['cover1_cantidad'],
                'cover2_count' => $clientesCover2,
                'cover2_cantidad' => $rData['cover2_cantidad'],
                'cover3_count' => $clientesCover3,
                'cover3_cantidad' => $rData['cover3_cantidad'],
                'cantidad_total_covers' => $rData['cantidad_total_covers'],
                'cantidad_total' => $rData['cantidad_total'],
                'cobertura_pct' => $coberturaPct,
                'cumple_mes' => ($coberturaPct >= self::UMBRAL_COVERS_PCT),
                'descuento_total' => round($rData['descuento_total'], 2),
                'monto_total' => round($rData['monto_total'], 2),
            ];

            $totalGlobalClientes += $clientesTotal;
            $totalGlobalConCover += $clientesConCover;
            $totalGlobalCover1 += $clientesCover1;
            $totalGlobalCover2 += $clientesCover2;
            $totalGlobalCover3 += $clientesCover3;
            $totalGlobalCover1Cantidad += $rData['cover1_cantidad'];
            $totalGlobalCover2Cantidad += $rData['cover2_cantidad'];
            $totalGlobalCover3Cantidad += $rData['cover3_cantidad'];
            $totalGlobalCantidadCovers += $rData['cantidad_total_covers'];
            $totalGlobalCantidad += $rData['cantidad_total'];
            $totalGlobalDescuento += $rData['descuento_total'];
            $totalGlobalMonto += $rData['monto_total'];
        }

        // Ordenar alfabéticamente por código de ruta
        usort($rutasConsolidadas, fn ($a, $b) => strcmp($a['ruta'], $b['ruta']));

        $totalRutas = count($rutasConsolidadas);
        $coberturaGlobalPct = $totalGlobalClientes > 0 ? round(($totalGlobalConCover / $totalGlobalClientes) * 100, 1) : 0.0;

        return [
            'rutas' => $rutasConsolidadas,
            'totales' => [
                'total_rutas' => $totalRutas,
                'clientes_total' => $totalGlobalClientes,
                'clientes_con_cover' => $totalGlobalConCover,
                'clientes_sin_cover' => $totalGlobalClientes - $totalGlobalConCover,
                'cover1_count' => $totalGlobalCover1,
                'cover1_cantidad' => $totalGlobalCover1Cantidad,
                'cover2_count' => $totalGlobalCover2,
                'cover2_cantidad' => $totalGlobalCover2Cantidad,
                'cover3_count' => $totalGlobalCover3,
                'cover3_cantidad' => $totalGlobalCover3Cantidad,
                'cantidad_total_covers' => $totalGlobalCantidadCovers,
                'cantidad_total' => $totalGlobalCantidad,
                'cobertura_pct' => $coberturaGlobalPct,
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
