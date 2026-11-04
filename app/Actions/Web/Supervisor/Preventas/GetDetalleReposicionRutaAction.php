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
                        'piso_pct' => 20.0,
                        'tope_pct' => 20.0,
                        'puntos_max' => 15.0,
                    ],
                    'totales' => [
                        'compradores_hoy' => 0,
                        'compradores_evaluables' => 0,
                        'compradores_nuevos' => 0,
                        'puntos_esperados_total' => 0,
                        'puntos_match_total' => 0,
                        'skus_esperados_total' => 0,
                        'skus_con_match_total' => 0,
                        'skus_fuera_canasta_total' => 0,
                        'porcentaje_reposicion_ruta' => 0.0,
                        'cumple_umbral' => false,
                        'puntos_obtenidos' => 0.0,
                        'estatus_ruta' => 'DÉFICIT',
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

            // 2. Historial de compras exclusivamente en fact_preventas (últimas 13 semanas previas)
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
                    DB::raw("COUNT(DISTINCT fecha_norm) as frecuencia_compra"),
                    DB::raw("COUNT(DISTINCT fecha_norm) as dias_con_compra"),
                    DB::raw("SUM(cantidad) as total_unidades"),
                    DB::raw("SUM(monto_final) as total_bs"),
                    DB::raw("SUBSTRING(GROUP_CONCAT(CONCAT(DATE(fecha_norm), ':', cantidad, ':', ROUND(monto_final, 2)) ORDER BY fecha_norm DESC SEPARATOR '|'), 1, 2048) as detalle_compras_raw"),
                ])
                ->groupBy('cliente_id', 'producto_id', DB::raw("COALESCE(TRIM(codigo_producto), '')"), DB::raw("COALESCE(TRIM(producto), 'SKU')"), DB::raw("COALESCE(TRIM(categoria), 'GENERAL')"))
                ->get();

            $historialPorCliente = [];

            foreach ($histPrev as $hv) {
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
                        'frecuencia_compra' => 0,
                        'dias_con_compra' => 0,
                        'total_unidades' => 0,
                        'total_bs' => 0.0,
                        'historial_compras' => [],
                    ];
                }

                $historialPorCliente[$cid][$key]['frecuencia_compra'] = max(
                    $historialPorCliente[$cid][$key]['frecuencia_compra'],
                    (int) $hv->frecuencia_compra
                );
                $historialPorCliente[$cid][$key]['dias_con_compra'] = max(
                    $historialPorCliente[$cid][$key]['dias_con_compra'],
                    (int) $hv->dias_con_compra
                );
                $historialPorCliente[$cid][$key]['total_unidades'] += (int) $hv->total_unidades;
                $historialPorCliente[$cid][$key]['total_bs'] += (float) $hv->total_bs;

                // Parsear desglose de compras históricas para trazabilidad
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

            // 3. Evaluar reposición tienda por tienda (Match Ponderado por semanas con compra f_i)
            $tiendas = [];
            $totalSkusEsperados = 0;
            $totalSkusMatch = 0;
            $totalSkusFueraCanasta = 0;
            $totalPuntosEsperadosRuta = 0;
            $totalPuntosMatchRuta = 0;
            $tiendasEvaluablesCount = 0;
            $tiendasNuevasCount = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $canastaRaw = $historialPorCliente[$cid] ?? [];
                $pedidoHoy = $cData['pedido_hoy_map'];
                $keysHoy = $cData['keys_hoy'];

                $keysUsadasHoy = [];
                $canastaBase = [];
                $matchesCliente = 0;
                $puntosEsperadosTienda = 0;
                $puntosMatchTienda = 0;

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

                    // Peso f_i = frecuencia de compra (fechas distintas con preventa) en el historial de 91 días
                    $freqCompra = max(1, (int) ($hab['frecuencia_compra'] ?? count($hab['historial_compras'] ?? [])));
                    $pesoFi = $freqCompra;
                    $puntosEsperadosTienda += $pesoFi;

                    if ($esMatch) {
                        $matchesCliente++;
                        $keysUsadasHoy[$keyMatch] = true;
                        $puntosMatchTienda += $pesoFi;
                    }

                    // Cantidad de reposición esperada: cantidad comprada sobre las semanas de la ventana (mín 1)
                    $cantRepo = max(1, (int) round($hab['total_unidades'] / max(1, $semanas)));

                    $canastaBase[] = [
                        'producto_id' => $hab['producto_id'],
                        'codigo_producto' => $hab['codigo_producto'],
                        'producto' => $hab['producto'],
                        'descripcion' => $hab['producto'],
                        'categoria' => $hab['categoria'],
                        'tipo_origen' => 'HABITO_CLIENTE',
                        'peso_fi' => $pesoFi,
                        'frecuencia_compra' => $freqCompra,
                        'unidades_acumuladas' => $hab['total_unidades'],
                        'dias_con_compra' => $freqCompra,
                        'semanas_transcurridas' => $semanas,
                        'cantidad_repo' => $cantRepo,
                        'monto_acumulado_bs' => round($hab['total_bs'], 2),
                        'unidades_hoy' => $unidadesHoy,
                        'monto_hoy' => $montoHoy,
                        'recomprado_hoy' => $esMatch,
                        'estatus' => $esMatch ? 'MATCH_SKU' : 'OMITIDO',
                        'historial_compras' => $hab['historial_compras'] ?? [],
                    ];
                }

                // Ordenar canasta habitual: primero recomprados, luego peso f_i descendente
                usort($canastaBase, function ($a, $b) {
                    if ($a['recomprado_hoy'] !== $b['recomprado_hoy']) {
                        return $b['recomprado_hoy'] <=> $a['recomprado_hoy'];
                    }
                    if ($a['peso_fi'] !== $b['peso_fi']) {
                        return $b['peso_fi'] <=> $a['peso_fi'];
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

                // Regla de Cliente Nuevo o Recuperado (Puntos Esperados = 0)
                $esClienteNuevo = ($cntEsperados === 0 || $puntosEsperadosTienda === 0);

                if (!$esClienteNuevo) {
                    $pctReposicionTienda = round(($puntosMatchTienda / $puntosEsperadosTienda) * 100.0, 1);
                    $cumpleTienda = ($pctReposicionTienda >= 20.0);

                    $totalPuntosEsperadosRuta += $puntosEsperadosTienda;
                    $totalPuntosMatchRuta += $puntosMatchTienda;
                    $tiendasEvaluablesCount++;
                } else {
                    // Neutro (Excluido): aporta 0 al numerador y 0 al denominador de la ruta
                    $pctReposicionTienda = null;
                    $cumpleTienda = null;
                    $tiendasNuevasCount++;
                }

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => $cData['codigo_cliente'],
                    'cliente' => $cData['cliente'],
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'total_skus_hoy' => count($pedidoHoy),
                    'es_neutro' => $esClienteNuevo,
                    'puntos_esperados' => $puntosEsperadosTienda,
                    'puntos_match' => $puntosMatchTienda,
                    'skus_esperados' => $cntEsperados,
                    'skus_con_match' => $cntMatch,
                    'skus_sin_match' => max(0, $cntEsperados - $cntMatch),
                    'skus_fuera_canasta_count' => $cntFuera,
                    'porcentaje_reposicion' => $pctReposicionTienda,
                    'cumple' => $cumpleTienda,
                    'skus_habituales' => $canastaBase, // Para Vue template
                    'canasta_base' => $canastaBase,    // Compatibilidad Python
                    'pedidos_hoy' => $pedidosHoyVista, // Compatibilidad Python
                    'skus_fuera_canasta' => $skusFueraCanasta,
                ];
            }

            // Ordenar: clientes evaluables primero por total Bs desc, clientes excluidos (neutros) al final
            usort($tiendas, function ($a, $b) {
                if ($a['es_neutro'] !== $b['es_neutro']) {
                    return $a['es_neutro'] ? 1 : -1;
                }
                return $b['total_bs_hoy'] <=> $a['total_bs_hoy'];
            });

            $cntTiendas = count($tiendas);

            // Paso 2: Porcentaje de Reposición del Día de la Ruta (Ponderado Global de Puntos)
            if ($totalPuntosEsperadosRuta > 0) {
                $pctReposicionRuta = round(($totalPuntosMatchRuta / $totalPuntosEsperadosRuta) * 100.0, 1);
            } elseif ($cntTiendas > 0) {
                // Caso de borde único: 100% de las tiendas compradoras son clientes nuevos sin historial
                $pctReposicionRuta = 100.0;
            } else {
                $pctReposicionRuta = 0.0;
            }

            // Paso 3: Asignación de Puntos (15 Puntos a partir de 20% de reposición)
            if ($cntTiendas === 0 || $pctReposicionRuta < 20.0) {
                $puntosObtenidos = 0.0;
                $cumpleUmbral = false;
                $estatusRuta = 'DÉFICIT';
            } else {
                $puntosObtenidos = 15.0;
                $cumpleUmbral = true;
                $estatusRuta = 'CUMPLE';
            }

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                    'piso_pct' => 20.0,
                    'tope_pct' => 20.0,
                    'puntos_max' => 15.0,
                ],
                'totales' => [
                    'compradores_hoy' => $cntTiendas,
                    'compradores_evaluables' => $tiendasEvaluablesCount,
                    'compradores_nuevos' => $tiendasNuevasCount,
                    'puntos_esperados_total' => $totalPuntosEsperadosRuta,
                    'puntos_match_total' => $totalPuntosMatchRuta,
                    'skus_esperados_total' => $totalSkusEsperados,
                    'skus_con_match_total' => $totalSkusMatch,
                    'skus_fuera_canasta_total' => $totalSkusFueraCanasta,
                    'porcentaje_reposicion_ruta' => $pctReposicionRuta,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $puntosObtenidos,
                    'estatus_ruta' => $estatusRuta,
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
