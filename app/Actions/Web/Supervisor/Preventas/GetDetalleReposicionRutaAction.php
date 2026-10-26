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
     * Normaliza texto para comparaciones seguras de cadenas.
     */
    private function normalizar(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }
        $str = mb_strtolower(trim($texto), 'UTF-8');
        return preg_replace('/\s+/', ' ', $str) ?? $str;
    }

    /**
     * Obtiene la auditoría analítica del KPI 6 (Reposición por Tienda y SKU habitual en 13 semanas).
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
        $fechaFinHist = $carbonFecha->copy()->subDays(1)->toDateString();
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
                    DB::raw("COALESCE(TRIM(codigo_cliente), '') as codigo_cliente"),
                    DB::raw("COALESCE(TRIM(cliente), 'S/N') as cliente"),
                    'producto_id',
                    DB::raw("COALESCE(TRIM(codigo_producto), '') as codigo_producto"),
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
                        'cumple_umbral' => false,
                        'puntos_obtenidos' => 0.0,
                    ],
                    'tiendas' => [],
                ];
            }

            // Agrupar compras de hoy por cliente y por producto
            $clientesCompradores = [];
            foreach ($preventasHoy as $f) {
                $cid = (int) $f->cliente_id;
                $pid = (int) $f->producto_id;
                $cod = trim((string) $f->codigo_producto);
                $prod = trim((string) $f->producto);
                $prodNorm = $this->normalizar($prod);

                $key = $cod !== '' ? "cod_{$cod}" : ($pid > 0 ? "id_{$pid}" : "nom_{$prodNorm}");

                if (!isset($clientesCompradores[$cid])) {
                    $clientesCompradores[$cid] = [
                        'cliente_id' => $cid,
                        'codigo_cliente' => $f->codigo_cliente ?: (string) $cid,
                        'cliente' => $f->cliente,
                        'total_bs_hoy' => 0.0,
                        'pedido_hoy_map' => [],
                        'keys_hoy' => [],
                    ];
                }
                $clientesCompradores[$cid]['total_bs_hoy'] += (float) $f->monto;

                if (!isset($clientesCompradores[$cid]['pedido_hoy_map'][$key])) {
                    $clientesCompradores[$cid]['pedido_hoy_map'][$key] = [
                        'producto_id' => $pid,
                        'codigo_producto' => $cod,
                        'producto' => $prod,
                        'categoria' => $f->categoria,
                        'cantidad' => 0,
                        'monto' => 0.0,
                    ];
                }
                $clientesCompradores[$cid]['pedido_hoy_map'][$key]['cantidad'] += (int) $f->cantidad;
                $clientesCompradores[$cid]['pedido_hoy_map'][$key]['monto'] += (float) $f->monto;

                // Registrar llaves para match flexible
                if ($cod !== '') {
                    $clientesCompradores[$cid]['keys_hoy']["cod_{$cod}"] = $key;
                }
                if ($pid > 0) {
                    $clientesCompradores[$cid]['keys_hoy']["id_{$pid}"] = $key;
                }
                if ($prodNorm !== '') {
                    $clientesCompradores[$cid]['keys_hoy']["nom_{$prodNorm}"] = $key;
                }
            }

            $cids = array_keys($clientesCompradores);

            // 2. Historial de compras en fact_ventas (últimas 13 semanas)
            $histVentas = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn('cliente_id', $cids)
                ->where('fecha_norm', '>=', $fechaInicioHist)
                ->where('fecha_norm', '<=', $fechaFinHist)
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
                    DB::raw("COALESCE(TRIM(codigo), '') as codigo_producto"),
                    DB::raw("COALESCE(TRIM(producto), 'SKU') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COUNT(DISTINCT fecha_norm) as dias_con_compra"),
                    DB::raw("COUNT(DISTINCT YEARWEEK(fecha_norm, 1)) as semanas_con_compra"),
                    DB::raw("SUM(cantidad) as total_unidades"),
                    DB::raw("SUM(monto_final) as total_bs"),
                    DB::raw("SUBSTRING(GROUP_CONCAT(CONCAT(DATE(fecha_norm), ':', cantidad, ':', ROUND(monto_final, 2)) ORDER BY fecha_norm DESC SEPARATOR '|'), 1, 2048) as detalle_compras_raw"),
                ])
                ->groupBy('cliente_id', 'producto_id', DB::raw("COALESCE(TRIM(codigo), '')"), DB::raw("COALESCE(TRIM(producto), 'SKU')"), DB::raw("COALESCE(TRIM(categoria), 'GENERAL')"))
                ->get();

            // También preventas previas en la ventana
            $histPrev = DB::connection('supervisor')->table('fact_preventas')
                ->whereIn('cliente_id', $cids)
                ->where('fecha_norm', '>=', $fechaInicioHist)
                ->where('fecha_norm', '<=', $fechaFinHist)
                ->where('monto_final', '>', 0)
                ->select([
                    'cliente_id',
                    'producto_id',
                    DB::raw("COALESCE(TRIM(codigo_producto), '') as codigo_producto"),
                    DB::raw("COALESCE(TRIM(producto), 'SKU') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COUNT(DISTINCT fecha_norm) as dias_con_compra"),
                    DB::raw("COUNT(DISTINCT YEARWEEK(fecha_norm, 1)) as semanas_con_compra"),
                    DB::raw("SUM(cantidad) as total_unidades"),
                    DB::raw("SUM(monto_final) as total_bs"),
                    DB::raw("SUBSTRING(GROUP_CONCAT(CONCAT(DATE(fecha_norm), ':', cantidad, ':', ROUND(monto_final, 2)) ORDER BY fecha_norm DESC SEPARATOR '|'), 1, 2048) as detalle_compras_raw"),
                ])
                ->groupBy('cliente_id', 'producto_id', DB::raw("COALESCE(TRIM(codigo_producto), '')"), DB::raw("COALESCE(TRIM(producto), 'SKU')"), DB::raw("COALESCE(TRIM(categoria), 'GENERAL')"))
                ->get();

            $historialPorCliente = [];

            $procesarHistorial = function ($coleccion) use (&$historialPorCliente): void {
                foreach ($coleccion as $hv) {
                    $cid = (int) $hv->cliente_id;
                    $pid = (int) $hv->producto_id;
                    $cod = trim((string) $hv->codigo_producto);
                    $prod = trim((string) $hv->producto);
                    $prodNorm = $this->normalizar($prod);

                    $key = $cod !== '' ? "cod_{$cod}" : ($pid > 0 ? "id_{$pid}" : "nom_{$prodNorm}");

                    if (!isset($historialPorCliente[$cid])) {
                        $historialPorCliente[$cid] = [];
                    }

                    if (!isset($historialPorCliente[$cid][$key])) {
                        $historialPorCliente[$cid][$key] = [
                            'producto_id' => $pid,
                            'codigo_producto' => $cod,
                            'producto' => $prod,
                            'categoria' => $hv->categoria,
                            'dias_con_compra' => 0,
                            'semanas_con_compra' => 0,
                            'total_unidades' => 0,
                            'total_bs' => 0.0,
                        ];
                    }

                    $historialPorCliente[$cid][$key]['dias_con_compra'] = max(
                        $historialPorCliente[$cid][$key]['dias_con_compra'],
                        (int) $hv->dias_con_compra
                    );
                    $historialPorCliente[$cid][$key]['semanas_con_compra'] = max(
                        $historialPorCliente[$cid][$key]['semanas_con_compra'],
                        (int) $hv->semanas_con_compra
                    );
                    $historialPorCliente[$cid][$key]['total_unidades'] += (int) $hv->total_unidades;
                    $historialPorCliente[$cid][$key]['total_bs'] += (float) $hv->total_bs;

                    // Parsear desglose de compras históricas para trazabilidad
                    if (!isset($historialPorCliente[$cid][$key]['historial_compras'])) {
                        $historialPorCliente[$cid][$key]['historial_compras'] = [];
                    }
                    $rawTxs = (string) ($hv->detalle_compras_raw ?? '');
                    if ($rawTxs !== '') {
                        foreach (explode('|', $rawTxs) as $b) {
                            $partes = explode(':', trim($b));
                            if (count($partes) >= 3) {
                                $historialPorCliente[$cid][$key]['historial_compras'][] = [
                                    'fecha' => $partes[0],
                                    'unidades' => (int) $partes[1],
                                    'monto_bs' => round((float) $partes[2], 2),
                                ];
                            }
                        }
                    }
                }
            };

            $procesarHistorial($histVentas);
            $procesarHistorial($histPrev);

            // 3. Evaluar reposición tienda por tienda
            $tiendas = [];
            $totalSkusEsperados = 0;
            $totalSkusMatch = 0;
            $totalSkusFueraCanasta = 0;
            $sumaScoresPorcentaje = 0.0;

            foreach ($clientesCompradores as $cid => $cData) {
                $canastaRaw = $historialPorCliente[$cid] ?? [];
                $pedidoHoy = $cData['pedido_hoy_map'];
                $keysHoy = $cData['keys_hoy'];

                $keysUsadasHoy = [];
                $canastaBase = [];
                $matchesCliente = 0;

                foreach ($canastaRaw as $hKey => $hab) {
                    $cod = $hab['codigo_producto'];
                    $pid = $hab['producto_id'];
                    $prodNorm = $this->normalizar($hab['producto']);

                    // Match contra compras de hoy
                    $keyMatch = null;
                    if ($cod !== '' && isset($keysHoy["cod_{$cod}"])) {
                        $keyMatch = $keysHoy["cod_{$cod}"];
                    } elseif ($pid > 0 && isset($keysHoy["id_{$pid}"])) {
                        $keyMatch = $keysHoy["id_{$pid}"];
                    } elseif ($prodNorm !== '' && isset($keysHoy["nom_{$prodNorm}"])) {
                        $keyMatch = $keysHoy["nom_{$prodNorm}"];
                    }

                    $esMatch = ($keyMatch !== null && isset($pedidoHoy[$keyMatch]));
                    $unidadesHoy = $esMatch ? (int) $pedidoHoy[$keyMatch]['cantidad'] : 0;
                    $montoHoy = $esMatch ? round((float) $pedidoHoy[$keyMatch]['monto'], 2) : 0.0;

                    if ($esMatch) {
                        $matchesCliente++;
                        $keysUsadasHoy[$keyMatch] = true;
                    }

                    $cantRepo = max(1, (int) round($hab['total_unidades'] / max(1, $semanas)));

                    $canastaBase[] = [
                        'producto_id' => $hab['producto_id'],
                        'codigo_producto' => $hab['codigo_producto'],
                        'producto' => $hab['producto'],
                        'descripcion' => $hab['producto'],
                        'categoria' => $hab['categoria'],
                        'tipo_origen' => 'HABITO_CLIENTE',
                        'unidades_acumuladas' => $hab['total_unidades'],
                        'semanas_con_compra' => $hab['semanas_con_compra'],
                        'dias_con_compra' => $hab['dias_con_compra'],
                        'cantidad_repo' => $cantRepo,
                        'monto_acumulado_bs' => round($hab['total_bs'], 2),
                        'unidades_hoy' => $unidadesHoy,
                        'monto_hoy' => $montoHoy,
                        'recomprado_hoy' => $esMatch,
                        'estatus' => $esMatch ? 'MATCH_SKU' : 'OMITIDO',
                        'historial_compras' => $hab['historial_compras'] ?? [],
                    ];
                }

                // Ordenar canasta habitual: primero recomprados, luego más semanas y volumen
                usort($canastaBase, function ($a, $b) {
                    if ($a['recomprado_hoy'] !== $b['recomprado_hoy']) {
                        return $b['recomprado_hoy'] <=> $a['recomprado_hoy'];
                    }
                    if ($a['semanas_con_compra'] !== $b['semanas_con_compra']) {
                        return $b['semanas_con_compra'] <=> $a['semanas_con_compra'];
                    }
                    return $b['unidades_acumuladas'] <=> $a['unidades_acumuladas'];
                });

                // Detectar pedidos de hoy fuera de canasta (Ampliación / Nuevos)
                $pedidosHoyVista = [];
                $skusFueraCanasta = [];

                foreach ($pedidoHoy as $pKey => $pItem) {
                    $esMatch = isset($keysUsadasHoy[$pKey]);
                    $itemObj = [
                        'producto_id' => $pItem['producto_id'],
                        'codigo_producto' => $pItem['codigo_producto'],
                        'producto' => $pItem['producto'],
                        'descripcion' => $pItem['producto'],
                        'categoria' => $pItem['categoria'],
                        'unidades' => $pItem['cantidad'],
                        'unidades_hoy' => $pItem['cantidad'],
                        'monto_bs' => round($pItem['monto'], 2),
                        'monto_hoy' => round($pItem['monto'], 2),
                        'recomprado_hoy' => $esMatch,
                        'es_match_canasta' => $esMatch,
                        'estatus' => $esMatch ? 'REPOSICION_SKU' : 'AMPLIACION',
                    ];

                    $pedidosHoyVista[] = $itemObj;
                    if (!$esMatch) {
                        $skusFueraCanasta[] = $itemObj;
                    }
                }

                $cntEsperados = count($canastaBase);
                $cntMatch = $matchesCliente;
                $cntFuera = count($skusFueraCanasta);

                $totalSkusEsperados += $cntEsperados;
                $totalSkusMatch += $cntMatch;
                $totalSkusFueraCanasta += $cntFuera;

                $pctReposicionTienda = ($cntEsperados > 0)
                    ? round(($cntMatch / $cntEsperados) * 100.0, 1)
                    : (count($pedidoHoy) > 0 ? 100.0 : 0.0);

                $sumaScoresPorcentaje += $pctReposicionTienda;

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => $cData['codigo_cliente'],
                    'cliente' => $cData['cliente'],
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'total_skus_hoy' => count($pedidoHoy),
                    'skus_esperados' => $cntEsperados,
                    'skus_con_match' => $cntMatch,
                    'skus_sin_match' => max(0, $cntEsperados - $cntMatch),
                    'skus_fuera_canasta_count' => $cntFuera,
                    'porcentaje_reposicion' => $pctReposicionTienda,
                    'cumple' => $pctReposicionTienda >= 60.0,
                    'skus_habituales' => $canastaBase, // Para Vue template
                    'canasta_base' => $canastaBase,    // Compatibilidad Python
                    'pedidos_hoy' => $pedidosHoyVista, // Compatibilidad Python
                    'skus_fuera_canasta' => $skusFueraCanasta,
                ];
            }

            usort($tiendas, fn ($a, $b) => $b['total_bs_hoy'] <=> $a['total_bs_hoy']);

            $cntTiendas = count($tiendas);
            $pctReposicionRuta = $cntTiendas > 0
                ? round($sumaScoresPorcentaje / $cntTiendas, 1)
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
                    'compradores_hoy' => $cntTiendas,
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
                    'cumple_umbral' => false,
                    'puntos_obtenidos' => 0.0,
                ],
                'tiendas' => [],
            ];
        }
    }
}
