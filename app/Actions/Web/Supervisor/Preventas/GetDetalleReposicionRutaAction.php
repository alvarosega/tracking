<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleReposicionRutaAction
{
    /**
     * Obtiene la auditoría analítica del KPI 6 (Reposición por Tienda y SKU habitual).
     *
     * @param string $ruta
     * @param string|null $fecha
     * @param int|null $semanasHistoria
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null, ?int $semanasHistoria = 13): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $semanas = $semanasHistoria && $semanasHistoria > 0 ? $semanasHistoria : 13;
        $diasHistoria = $semanas * 7;
        $carbonFecha = Carbon::parse($fechaNorm);
        $fechaInicioHist = $carbonFecha->copy()->subDays($diasHistoria)->toDateString();
        $rutaClean = strtoupper(trim($ruta));

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

            // 1. Preventas del día de la ruta
            $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->select([
                    'cliente_id',
                    DB::raw("COALESCE(TRIM(cliente), 'S/N') as cliente"),
                    'producto_id',
                    DB::raw("COALESCE(TRIM(producto), 'SKU') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COALESCE(cantidad, 0) as cantidad"),
                    DB::raw("COALESCE(monto_final, 0.0) as monto"),
                ])
                ->get();

            if ($preventasHoy->isEmpty()) {
                return [
                    'ruta' => $ruta,
                    'canal' => $canal,
                    'vendedor' => $vendedor,
                    'fecha' => $fechaNorm,
                    'configuracion' => [
                        'semanas_historia' => $semanas,
                        'dias_historia' => $diasHistoria,
                    ],
                    'totales' => [
                        'compradores_hoy' => 0,
                        'skus_esperados_total' => 0,
                        'skus_con_match_total' => 0,
                        'skus_fuera_canasta_total' => 0,
                        'porcentaje_reposicion_ruta' => 0.0,
                    ],
                    'tiendas' => [],
                ];
            }

            // Agrupar compras de hoy por cliente
            $clientesCompradores = [];
            foreach ($preventasHoy as $f) {
                $cid = (int) $f->cliente_id;
                $pid = (int) $f->producto_id;
                if (!isset($clientesCompradores[$cid])) {
                    $clientesCompradores[$cid] = [
                        'cliente_id' => $cid,
                        'cliente' => $f->cliente,
                        'total_bs_hoy' => 0.0,
                        'pedido_hoy_map' => [],
                    ];
                }
                $clientesCompradores[$cid]['total_bs_hoy'] += (float) $f->monto;
                if (!isset($clientesCompradores[$cid]['pedido_hoy_map'][$pid])) {
                    $clientesCompradores[$cid]['pedido_hoy_map'][$pid] = [
                        'producto_id' => $pid,
                        'producto' => $f->producto,
                        'categoria' => $f->categoria,
                        'cantidad' => 0,
                        'monto' => 0.0,
                    ];
                }
                $clientesCompradores[$cid]['pedido_hoy_map'][$pid]['cantidad'] += (int) $f->cantidad;
                $clientesCompradores[$cid]['pedido_hoy_map'][$pid]['monto'] += (float) $f->monto;
            }

            $cids = array_keys($clientesCompradores);

            // 2. Historial de compras en fact_ventas (últimas 13 semanas)
            $histVentas = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn('cliente_id', $cids)
                ->where('fecha_norm', '>=', $fechaInicioHist)
                ->where('fecha_norm', '<', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')
                      ->orWhere(function ($sub): void {
                          $sub->where('revertida', '!=', 'Si')
                              ->where('revertida', '!=', 'SI');
                      });
                })
                ->select([
                    'cliente_id',
                    'producto_id',
                    DB::raw("MAX(TRIM(producto)) as producto"),
                    DB::raw("MAX(TRIM(categoria)) as categoria"),
                    DB::raw("COUNT(DISTINCT fecha_norm) as frecuencias"),
                    DB::raw("SUM(cantidad) as total_unidades"),
                    DB::raw("SUM(monto_final) as total_bs"),
                ])
                ->groupBy('cliente_id', 'producto_id')
                ->get();

            $historialPorCliente = [];
            foreach ($histVentas as $hv) {
                $cid = (int) $hv->cliente_id;
                $pid = (int) $hv->producto_id;
                $historialPorCliente[$cid][$pid] = [
                    'producto_id' => $pid,
                    'producto' => $hv->producto,
                    'categoria' => $hv->categoria,
                    'frecuencias' => (int) $hv->frecuencias,
                    'total_unidades' => (int) $hv->total_unidades,
                    'total_bs' => round((float) $hv->total_bs, 2),
                ];
            }

            // 3. Evaluar reposición tienda por tienda
            $tiendas = [];
            $totalSkusEsperados = 0;
            $totalSkusMatch = 0;
            $totalSkusFueraCanasta = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $canastaHabitual = $historialPorCliente[$cid] ?? [];
                $pedidoHoy = $cData['pedido_hoy_map'];

                $skusMatch = [];
                $skusEsperadosNoComprados = [];
                $skusFueraCanasta = [];

                foreach ($canastaHabitual as $pid => $hab) {
                    if (isset($pedidoHoy[$pid])) {
                        $skusMatch[] = [
                            'producto_id' => $pid,
                            'producto' => $pedidoHoy[$pid]['producto'],
                            'categoria' => $pedidoHoy[$pid]['categoria'],
                            'cantidad_hoy' => $pedidoHoy[$pid]['cantidad'],
                            'monto_hoy' => round($pedidoHoy[$pid]['monto'], 2),
                            'frecuencia_historica' => $hab['frecuencias'],
                        ];
                    } else {
                        $skusEsperadosNoComprados[] = [
                            'producto_id' => $pid,
                            'producto' => $hab['producto'],
                            'categoria' => $hab['categoria'],
                            'frecuencia_historica' => $hab['frecuencias'],
                        ];
                    }
                }

                foreach ($pedidoHoy as $pid => $ph) {
                    if (!isset($canastaHabitual[$pid])) {
                        $skusFueraCanasta[] = [
                            'producto_id' => $pid,
                            'producto' => $ph['producto'],
                            'categoria' => $ph['categoria'],
                            'cantidad_hoy' => $ph['cantidad'],
                            'monto_hoy' => round($ph['monto'], 2),
                        ];
                    }
                }

                $cntEsperados = count($canastaHabitual);
                $cntMatch = count($skusMatch);
                $cntFuera = count($skusFueraCanasta);

                $totalSkusEsperados += $cntEsperados;
                $totalSkusMatch += $cntMatch;
                $totalSkusFueraCanasta += $cntFuera;

                $pctReposicionTienda = $cntEsperados > 0 ? round(($cntMatch / $cntEsperados) * 100.0, 1) : 100.0;

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'cliente' => $cData['cliente'],
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'total_skus_hoy' => count($pedidoHoy),
                    'skus_esperados_count' => $cntEsperados,
                    'skus_con_match_count' => $cntMatch,
                    'skus_sin_match_count' => count($skusEsperadosNoComprados),
                    'skus_fuera_canasta_count' => $cntFuera,
                    'porcentaje_reposicion' => $pctReposicionTienda,
                    'cumple' => $pctReposicionTienda >= 60.0,
                    'skus_con_match' => $skusMatch,
                    'skus_sin_match' => $skusEsperadosNoComprados,
                    'skus_fuera_canasta' => $skusFueraCanasta,
                ];
            }

            usort($tiendas, fn ($a, $b) => $b['total_bs_hoy'] <=> $a['total_bs_hoy']);

            $pctReposicionRuta = $totalSkusEsperados > 0
                ? round(($totalSkusMatch / $totalSkusEsperados) * 100.0, 1)
                : 0.0;

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                ],
                'totales' => [
                    'compradores_hoy' => count($clientesCompradores),
                    'skus_esperados_total' => $totalSkusEsperados,
                    'skus_con_match_total' => $totalSkusMatch,
                    'skus_fuera_canasta_total' => $totalSkusFueraCanasta,
                    'porcentaje_reposicion_ruta' => $pctReposicionRuta,
                    'cumple_umbral' => $pctReposicionRuta >= 60.0,
                    'puntos_obtenidos' => $pctReposicionRuta >= 60.0 ? 15.0 : 0.0,
                ],
                'tiendas' => $tiendas,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleReposicionRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'totales' => [
                    'compradores_hoy' => 0,
                    'skus_esperados_total' => 0,
                    'skus_con_match_total' => 0,
                    'skus_fuera_canasta_total' => 0,
                    'porcentaje_reposicion_ruta' => 0.0,
                ],
                'tiendas' => [],
            ];
        }
    }
}

