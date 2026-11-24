<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceCoversDetalleRutaAction
{
    private const UMBRAL_COVERS_PCT = 50.0;

    /**
     * Obtiene el detalle de clientes compradores de una ruta para el módulo de Acumulado de Covers en Avance.
     * Consulta directamente fact_ventas y clasifica según los 3 códigos de escala_producto:
     * - Cover 1: COD-SHTDB1126 (Sedal SH)
     * - Cover 2: COD-PEPTDB1126 (Pepsodent)
     * - Cover 3: SEDAL-SCHT 3-0323- TDB / SEDAL-SCHT 3-0323 - TDB (Sedal Sachet)
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

        // 1. Info de la Ruta desde dim_rutas
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

        // 2. Ventas de la ruta en el periodo desde fact_ventas
        $ventas = DB::connection('supervisor')->table('fact_ventas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
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
                'vendedor',
                'canal',
            ])
            ->orderBy('cliente_id')
            ->orderBy('fecha_norm')
            ->orderBy('id')
            ->get();

        if ($ventas->isEmpty()) {
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
                    'descuento_total' => 0.0,
                    'monto_total' => 0.0,
                ],
                'tiendas' => [],
            ];
        }

        // 3. Mapear por cliente y transacciones
        $clientesMap = [];

        foreach ($ventas as $r) {
            $cid = (int) $r->cliente_id;
            $fNorm = (string) $r->fecha_norm;

            if (!isset($clientesMap[$cid])) {
                $clientesMap[$cid] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => (string) $cid,
                    'cliente' => (string) ($r->cliente ?: 'Cliente #' . $cid),
                    'transacciones' => [],
                ];
            }

            if (!isset($clientesMap[$cid]['transacciones'][$fNorm])) {
                $clientesMap[$cid]['transacciones'][$fNorm] = [];
            }
            $clientesMap[$cid]['transacciones'][$fNorm][] = $r;
        }

        // 4. Procesar clientes
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
            $tieneCover = false;
            $descuentoTotalCli = 0.0;
            $montoTotalCli = 0.0;
            $coversDetectados = [];
            $transaccionesList = [];

            foreach ($cData['transacciones'] as $fNorm => $items) {
                $txTieneCover = false;
                $txDescuento = 0.0;
                $txMonto = 0.0;
                $txItemsEtiquetados = [];

                foreach ($items as $it) {
                    $montoRow = (float) ($it->monto_final ?? ($it->monto ?? 0.0));
                    $descRow = (float) ($it->descuento ?? 0.0);
                    $txMonto += $montoRow;

                    $escalaNorm = strtoupper(trim((string) ($it->escala_producto ?? '')));
                    $escalaSinEspacios = str_replace(' ', '', $escalaNorm);

                    $isC1 = str_contains($escalaSinEspacios, 'COD-SHTDB1126');
                    $isC2 = str_contains($escalaSinEspacios, 'COD-PEPTDB1126');
                    $isC3 = str_contains($escalaSinEspacios, 'SEDAL-SCHT3-0323-TDB')
                        || (str_contains($escalaNorm, 'SEDAL-SCHT 3-0323') && str_contains($escalaNorm, 'TDB'));

                    $tag = null;
                    $tagTipo = 'regular';
                    if ($isC1) {
                        $tieneCover1 = true;
                        $coversDetectados['COD-SHTDB1126'] = true;
                        $tag = 'COD-SHTDB1126';
                        $tagTipo = 'cover1';
                    }
                    if ($isC2) {
                        $tieneCover2 = true;
                        $coversDetectados['COD-PEPTDB1126'] = true;
                        $tag = 'COD-PEPTDB1126';
                        $tagTipo = 'cover2';
                    }
                    if ($isC3) {
                        $tieneCover3 = true;
                        $coversDetectados['SEDAL-SCHT 3-0323- TDB'] = true;
                        $tag = 'SEDAL-SCHT 3-0323- TDB';
                        $tagTipo = 'cover3';
                    }

                    if ($isC1 || $isC2 || $isC3) {
                        $txTieneCover = true;
                        $txDescuento += $descRow;
                        $descuentoTotalCli += $descRow;
                    }

                    $txItemsEtiquetados[] = [
                        'codigo' => $it->codigo,
                        'producto' => $it->producto,
                        'cantidad' => $it->cantidad,
                        'descuento' => round($descRow, 2),
                        'monto_final' => round($montoRow, 2),
                        'escala_producto' => $it->escala_producto ?: '-',
                        'tag' => $tag,
                        'tag_tipo' => $tagTipo,
                        'con_descuento' => ($isC1 || $isC2 || $isC3),
                    ];
                }

                if ($txTieneCover) {
                    $visitasConCover++;
                    $tieneCover = true;
                }

                $montoTotalCli += $txMonto;

                $resumenTx = !empty($coversDetectados)
                    ? implode(', ', array_keys($coversDetectados))
                    : 'Sin cover';

                $transaccionesList[] = [
                    'fecha' => $fNorm,
                    'resumen_promo' => $resumenTx,
                    'descuento' => round($txDescuento, 2),
                    'monto' => round($txMonto, 2),
                    'items_etiquetados' => $txItemsEtiquetados,
                ];
            }

            if ($tieneCover) {
                $clientesConCoverTotal++;
            }
            if ($tieneCover1) $cover1Total++;
            if ($tieneCover2) $cover2Total++;
            if ($tieneCover3) $cover3Total++;
            $descuentoRutaTotal += $descuentoTotalCli;
            $montoRutaTotal += $montoTotalCli;

            $tiendas[] = [
                'cliente_id' => $cid,
                'codigo_cliente' => (string) $cid,
                'cliente' => $cData['cliente'],
                'visitas_total' => $visitasTotal,
                'visitas_con_cover' => $visitasConCover,
                'tiene_cover' => $tieneCover,
                'tiene_descuento' => $tieneCover,
                'tiene_cover1' => $tieneCover1,
                'tiene_cover2' => $tieneCover2,
                'tiene_cover3' => $tieneCover3,
                'covers' => array_keys($coversDetectados),
                'resumen_promo' => !empty($coversDetectados)
                    ? implode(', ', array_keys($coversDetectados))
                    : 'Sin cover',
                'descuento_total' => round($descuentoTotalCli, 2),
                'monto_total' => round($montoTotalCli, 2),
                'transacciones' => $transaccionesList,
            ];
        }

        // Ordenar tiendas: primero clientes con cover, luego por nombre
        usort($tiendas, function ($a, $b) {
            if ($a['tiene_cover'] !== $b['tiene_cover']) {
                return $a['tiene_cover'] ? -1 : 1;
            }
            return strcmp($a['cliente'], $b['cliente']);
        });

        $clientesTotal = count($tiendas);
        $coberturaPct = $clientesTotal > 0 ? round(($clientesConCoverTotal / $clientesTotal) * 100, 1) : 0.0;

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
                'clientes_total' => $clientesTotal,
                'clientes_con_cover' => $clientesConCoverTotal,
                'clientes_sin_cover' => $clientesTotal - $clientesConCoverTotal,
                'cover1_count' => $cover1Total,
                'cover2_count' => $cover2Total,
                'cover3_count' => $cover3Total,
                'cobertura_pct' => $coberturaPct,
                'descuento_total' => round($descuentoRutaTotal, 2),
                'monto_total' => round($montoRutaTotal, 2),
            ],
            'tiendas' => $tiendas,
        ];
    }
}
