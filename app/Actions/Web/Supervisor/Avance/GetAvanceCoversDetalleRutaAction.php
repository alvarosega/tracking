<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use App\Actions\Web\Supervisor\Preventas\CoversClassifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceCoversDetalleRutaAction
{
    private const UMBRAL_COVERS_PCT = 50.0;

    private const DIAS_SEMANA_ABR = [
        1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue',
        5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
    ];

    /**
     * Obtiene el detalle monográfico de una ruta para el módulo de Acumulado de Covers en Avance.
     * Retorna las tiendas compradoras (con y sin cover) y la evolución cronológica día a día.
     *
     * @param string $ruta
     * @param string|null $fechaInicio
     * @param string|null $fechaCorte
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fechaInicio = null, ?string $fechaCorte = null): array
    {
        $rutaClean = strtoupper(trim($ruta));

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_preventas')
                ->where('monto_final', '>', 0)
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

        // 1. Info de la Ruta
        $vendedor = 'No Asignado';
        $canal = 'GENERAL';
        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();
            if ($dimInfo) {
                $vendedor = trim((string) ($dimInfo->vendedor ?: 'No Asignado'));
                $canal = trim((string) ($dimInfo->canal ?: 'GENERAL'));
            }
        } catch (\Throwable) {
            // Ignorar y usar defaults
        }

        // 2. Preventas de la ruta en el periodo
        $preventas = DB::connection('supervisor')->table('fact_preventas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where(function ($q): void {
                $q->where('monto_final', '>', 0)
                  ->orWhere('descuento', '>', 0);
            })
            ->select([
                'fecha_norm',
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
            ])
            ->orderBy('cliente_id')
            ->orderBy('fecha_norm')
            ->orderBy('id')
            ->get();

        if ($preventas->isEmpty()) {
            return [
                'ruta' => $rutaClean,
                'vendedor' => $vendedor,
                'canal' => $canal,
                'rango' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_corte' => $fechaCorte,
                    'umbral_covers_pct' => self::UMBRAL_COVERS_PCT,
                ],
                'totales' => [
                    'clientes_total' => 0,
                    'clientes_con_cover' => 0,
                    'clientes_sin_cover' => 0,
                    'cover1_count' => 0,
                    'cover2_count' => 0,
                    'cover3_count' => 0,
                    'cobertura_pct' => 0.0,
                    'dias_activos' => 0,
                    'dias_cumplidos' => 0,
                    'pct_dias_cumplidos' => 0.0,
                    'descuento_total' => 0.0,
                    'monto_total' => 0.0,
                ],
                'tiendas' => [],
                'dias' => [],
            ];
        }

        // 3. Mapear por cliente y por fecha
        $clientesMap = [];
        $diasMap = [];

        foreach ($preventas as $r) {
            $cid = (int) $r->cliente_id;
            $fNorm = (string) $r->fecha_norm;

            if (!isset($diasMap[$fNorm])) {
                $diasMap[$fNorm] = [
                    'fecha' => $fNorm,
                    'compradores' => [],
                    'con_cover' => [],
                    'cover1' => 0,
                    'cover2' => 0,
                    'cover3' => 0,
                    'descuento' => 0.0,
                ];
            }

            if (!isset($clientesMap[$cid])) {
                $clientesMap[$cid] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => (string) ($r->codigo_cliente ?: $cid),
                    'cliente' => (string) ($r->cliente ?: 'Cliente #' . $cid),
                    'transacciones' => [],
                ];
            }

            if (!isset($clientesMap[$cid]['transacciones'][$fNorm])) {
                $clientesMap[$cid]['transacciones'][$fNorm] = [];
            }
            $clientesMap[$cid]['transacciones'][$fNorm][] = $r;
        }

        // 4. Procesar clientes y transacciones
        $tiendas = [];
        $clientesConCoverTotal = 0;
        $cover1Total = 0;
        $cover2Total = 0;
        $cover3Total = 0;
        $descuentoRutaTotal = 0.0;
        $montoRutaTotal = 0.0;

        foreach ($clientesMap as $cid => $cData) {
            $visitasTotal = count($cData['transacciones']);
            $visitasConCover = 0;
            $tieneCover1 = false;
            $tieneCover2 = false;
            $tieneCover3 = false;
            $tieneDescuento = false;
            $descuentoTotalCli = 0.0;
            $montoTotalCli = 0.0;
            $allCovers = [];
            $allResumenes = [];
            $transaccionesList = [];

            foreach ($cData['transacciones'] as $fNorm => $items) {
                $diasMap[$fNorm]['compradores'][$cid] = true;
                $clasif = CoversClassifier::clasificar($items);

                $montoTrans = 0.0;
                foreach ($items as $it) {
                    $montoTrans += (float) ($it->monto_final ?? 0.0);
                }

                $descuentoTotalCli += $clasif['descuento_total'];
                $montoTotalCli += $montoTrans;
                $diasMap[$fNorm]['descuento'] += $clasif['descuento_total'];

                if ($clasif['tiene_descuento']) {
                    $visitasConCover++;
                    $tieneDescuento = true;
                    $diasMap[$fNorm]['con_cover'][$cid] = true;
                }
                if ($clasif['tiene_cover1']) {
                    $tieneCover1 = true;
                    $diasMap[$fNorm]['cover1']++;
                }
                if ($clasif['tiene_cover2']) {
                    $tieneCover2 = true;
                    $diasMap[$fNorm]['cover2']++;
                }
                if ($clasif['tiene_cover3']) {
                    $tieneCover3 = true;
                    $diasMap[$fNorm]['cover3']++;
                }

                foreach ($clasif['covers'] as $cov) {
                    $allCovers[$cov] = true;
                }

                $resumenTx = $clasif['resumen_promo'] ?? '';
                if ($resumenTx !== '' && $resumenTx !== 'Sin promoción aplicada') {
                    $allResumenes[] = $fNorm . ': ' . $resumenTx;
                }

                $transaccionesList[] = [
                    'fecha' => $fNorm,
                    'covers' => $clasif['covers'],
                    'resumen_promo' => $resumenTx,
                    'descuento' => round($clasif['descuento_total'], 2),
                    'monto' => round($montoTrans, 2),
                    'items_etiquetados' => $clasif['items_etiquetados'],
                ];
            }

            if ($tieneDescuento) $clientesConCoverTotal++;
            if ($tieneCover1) $cover1Total++;
            if ($tieneCover2) $cover2Total++;
            if ($tieneCover3) $cover3Total++;
            $descuentoRutaTotal += $descuentoTotalCli;
            $montoRutaTotal += $montoTotalCli;

            $tiendas[] = [
                'cliente_id' => $cid,
                'codigo_cliente' => $cData['codigo_cliente'],
                'cliente' => $cData['cliente'],
                'visitas_total' => $visitasTotal,
                'visitas_con_cover' => $visitasConCover,
                'tiene_descuento' => $tieneDescuento,
                'tiene_cover1' => $tieneCover1,
                'tiene_cover2' => $tieneCover2,
                'tiene_cover3' => $tieneCover3,
                'covers' => array_keys($allCovers),
                'resumen_promo' => !empty($allResumenes)
                    ? implode(' | ', array_slice($allResumenes, 0, 3))
                    : ($tieneDescuento ? 'Descuento en productos regulares' : 'Sin promoción aplicada'),
                'descuento_total' => round($descuentoTotalCli, 2),
                'monto_total' => round($montoTotalCli, 2),
                'transacciones' => $transaccionesList,
            ];
        }

        // Ordenar tiendas: Primero las que tienen cover, luego mayor descuento
        usort($tiendas, function ($a, $b) {
            if ($a['tiene_descuento'] !== $b['tiene_descuento']) {
                return $a['tiene_descuento'] ? -1 : 1;
            }
            return $b['descuento_total'] <=> $a['descuento_total'];
        });

        // 5. Procesar matriz cronológica de días
        ksort($diasMap);
        $diasList = [];
        $diasCumplidosTotal = 0;

        foreach ($diasMap as $fNorm => $dInfo) {
            $carb = Carbon::parse($fNorm);
            $diaSemNum = $carb->dayOfWeekIso;
            $abrev = self::DIAS_SEMANA_ABR[$diaSemNum] ?? 'D';
            $diaNombre = sprintf('%s %02d/%02d', $abrev, $carb->day, $carb->month);

            $comp = count($dInfo['compradores']);
            $cov = count($dInfo['con_cover']);
            $sinCov = $comp - $cov;
            $pct = $comp > 0 ? round(($cov / $comp) * 100, 1) : 0.0;
            $cumple = ($pct >= self::UMBRAL_COVERS_PCT);

            if ($cumple) {
                $diasCumplidosTotal++;
            }

            $diasList[] = [
                'fecha' => $fNorm,
                'dia_nombre' => $diaNombre,
                'compradores' => $comp,
                'con_cover' => $cov,
                'sin_cover' => $sinCov,
                'cover1_count' => $dInfo['cover1'],
                'cover2_count' => $dInfo['cover2'],
                'cover3_count' => $dInfo['cover3'],
                'cobertura_pct' => $pct,
                'cumple' => $cumple,
                'descuento_total' => round($dInfo['descuento'], 2),
            ];
        }

        $totalClientes = count($tiendas);
        $coberturaRutaPct = $totalClientes > 0 ? round(($clientesConCoverTotal / $totalClientes) * 100, 1) : 0.0;
        $diasActivosTotal = count($diasList);

        return [
            'ruta' => $rutaClean,
            'vendedor' => $vendedor,
            'canal' => $canal,
            'rango' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_corte' => $fechaCorte,
                'umbral_covers_pct' => self::UMBRAL_COVERS_PCT,
            ],
            'totales' => [
                'clientes_total' => $totalClientes,
                'clientes_con_cover' => $clientesConCoverTotal,
                'clientes_sin_cover' => $totalClientes - $clientesConCoverTotal,
                'cover1_count' => $cover1Total,
                'cover2_count' => $cover2Total,
                'cover3_count' => $cover3Total,
                'cobertura_pct' => $coberturaRutaPct,
                'dias_activos' => $diasActivosTotal,
                'dias_cumplidos' => $diasCumplidosTotal,
                'pct_dias_cumplidos' => $diasActivosTotal > 0 ? round(($diasCumplidosTotal / $diasActivosTotal) * 100, 1) : 0.0,
                'cumple_mes' => ($coberturaRutaPct >= self::UMBRAL_COVERS_PCT),
                'descuento_total' => round($descuentoRutaTotal, 2),
                'monto_total' => round($montoRutaTotal, 2),
            ],
            'tiendas' => $tiendas,
            'dias' => $diasList,
        ];
    }
}
