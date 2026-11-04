<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleAmpliacionRutaAction
{
    private const UMBRAL_AMPLIACION_PCT = 30.0;
    private const UMBRAL_CLIENTE_AMPLIACION_PCT = 30.0;

    /**
     * Obtiene la auditoría analítica del KPI 7: Ampliación de Portafolio por Tienda y SKUs nuevos.
     * Criterio: En la preventa de una ruta, al menos el 30% de los SKUs deben ser de ampliación
     * (productos no comprados en la ventana de las últimas semanas) para sumar los 20 puntos base.
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

            // 1. Preventas del día
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
                        'umbral_ruta_pct' => self::UMBRAL_AMPLIACION_PCT,
                        'umbral_cliente_pct' => self::UMBRAL_CLIENTE_AMPLIACION_PCT,
                        'puntos_max' => 20.0,
                    ],
                    'totales' => [
                        'compradores_hoy' => 0,
                        'clientes_con_ampliacion' => 0,
                        'total_skus_preventa' => 0,
                        'total_skus_ampliacion' => 0,
                        'porcentaje_ampliacion_ruta' => 0.0,
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
                    ];
                }
                $clientesCompradores[$cid]['total_bs_hoy'] += (float) $f->monto;

                if (!isset($clientesCompradores[$cid]['pedido_hoy_map'][$key])) {
                    $clientesCompradores[$cid]['pedido_hoy_map'][$key] = [
                        'producto_id' => $pid,
                        'codigo_producto' => $cod,
                        'producto' => $prod,
                        'prod_norm' => $prodNorm,
                        'categoria' => $f->categoria,
                        'cantidad' => 0,
                        'monto' => 0.0,
                    ];
                }
                $clientesCompradores[$cid]['pedido_hoy_map'][$key]['cantidad'] += (int) $f->cantidad;
                $clientesCompradores[$cid]['pedido_hoy_map'][$key]['monto'] += (float) $f->monto;
            }

            $cids = array_keys($clientesCompradores);

            // 2. Historial de compras en fact_preventas (ventana configurada de semanas previas)
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
                ])
                ->distinct()
                ->get();

            $historialPorCliente = [];
            foreach ($histPrev as $hv) {
                $cid = (int) $hv->cliente_id;
                $pid = (int) $hv->producto_id;
                $cod = trim((string) $hv->codigo_producto);
                $prodNorm = $this->normalizar(trim((string) $hv->producto));

                if (!isset($historialPorCliente[$cid])) {
                    $historialPorCliente[$cid] = [
                        'cod' => [],
                        'id' => [],
                        'nom' => [],
                    ];
                }

                if ($cod !== '') {
                    $historialPorCliente[$cid]['cod'][$cod] = true;
                }
                if ($pid > 0) {
                    $historialPorCliente[$cid]['id'][$pid] = true;
                }
                if ($prodNorm !== '') {
                    $historialPorCliente[$cid]['nom'][$prodNorm] = true;
                }
            }

            // 3. Evaluar ampliación de portafolio por tienda y totalizar la ruta
            $tiendas = [];
            $clientesConAmpliacionCount = 0;
            $totalSkusPreventaRuta = 0;
            $totalSkusAmpliacionRuta = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $historial = $historialPorCliente[$cid] ?? null;
                $tieneHistorial = !empty($historial);
                $pedidoHoy = $cData['pedido_hoy_map'];

                $skusNuevos = [];
                $skusHabituales = [];

                foreach ($pedidoHoy as $ph) {
                    $cod = $ph['codigo_producto'];
                    $pid = $ph['producto_id'];
                    $prodNorm = $ph['prod_norm'];

                    $enHistorial = false;
                    if ($tieneHistorial) {
                        if ($cod !== '' && isset($historial['cod'][$cod])) {
                            $enHistorial = true;
                        } elseif ($pid > 0 && isset($historial['id'][$pid])) {
                            $enHistorial = true;
                        } elseif ($prodNorm !== '' && isset($historial['nom'][$prodNorm])) {
                            $enHistorial = true;
                        }
                    }

                    $itemAudit = [
                        'producto_id' => $ph['producto_id'],
                        'codigo_producto' => $ph['codigo_producto'],
                        'producto' => $ph['producto'],
                        'categoria' => $ph['categoria'],
                        'cantidad' => $ph['cantidad'],
                        'monto' => round($ph['monto'], 2),
                    ];

                    if ($enHistorial) {
                        $skusHabituales[] = $itemAudit;
                    } else {
                        $skusNuevos[] = $itemAudit;
                    }
                }

                $totalSkusCliente = count($pedidoHoy);
                $cantNuevos = count($skusNuevos);
                $cantHabituales = count($skusHabituales);

                $pctCliente = $totalSkusCliente > 0
                    ? round(($cantNuevos / $totalSkusCliente) * 100.0, 1)
                    : 0.0;
                $cumpleCliente = ($pctCliente >= self::UMBRAL_CLIENTE_AMPLIACION_PCT);

                if ($cumpleCliente) {
                    $clientesConAmpliacionCount++;
                }

                $totalSkusPreventaRuta += $totalSkusCliente;
                $totalSkusAmpliacionRuta += $cantNuevos;

                // Ordenar SKUs de cada lista por monto descendente
                usort($skusNuevos, fn ($a, $b) => $b['monto'] <=> $a['monto']);
                usort($skusHabituales, fn ($a, $b) => $b['monto'] <=> $a['monto']);

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => $cData['codigo_cliente'],
                    'cliente' => $cData['cliente'],
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'total_skus_hoy' => $totalSkusCliente,
                    'skus_nuevos_count' => $cantNuevos,
                    'skus_habituales_count' => $cantHabituales,
                    'porcentaje_ampliacion' => $pctCliente,
                    'cumple' => $cumpleCliente,
                    'tiene_historial' => $tieneHistorial,
                    'skus_nuevos' => $skusNuevos,
                    'skus_habituales' => $skusHabituales,
                ];
            }

            usort($tiendas, fn ($a, $b) => $b['total_bs_hoy'] <=> $a['total_bs_hoy']);

            $totalCompradores = count($clientesCompradores);
            $pctAmpliacionRuta = $totalSkusPreventaRuta > 0
                ? round(($totalSkusAmpliacionRuta / $totalSkusPreventaRuta) * 100.0, 1)
                : 0.0;

            $cumpleUmbral = ($totalCompradores > 0 && $pctAmpliacionRuta >= self::UMBRAL_AMPLIACION_PCT);
            $puntosObtenidos = $cumpleUmbral ? 20.0 : 0.0;

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                    'umbral_ruta_pct' => self::UMBRAL_AMPLIACION_PCT,
                    'umbral_cliente_pct' => self::UMBRAL_CLIENTE_AMPLIACION_PCT,
                    'puntos_max' => 20.0,
                ],
                'totales' => [
                    'compradores_hoy' => $totalCompradores,
                    'clientes_con_ampliacion' => $clientesConAmpliacionCount,
                    'total_skus_preventa' => $totalSkusPreventaRuta,
                    'total_skus_ampliacion' => $totalSkusAmpliacionRuta,
                    'porcentaje_ampliacion_ruta' => $pctAmpliacionRuta,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $puntosObtenidos,
                ],
                'tiendas' => $tiendas,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleAmpliacionRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                    'umbral_ruta_pct' => self::UMBRAL_AMPLIACION_PCT,
                    'umbral_cliente_pct' => self::UMBRAL_CLIENTE_AMPLIACION_PCT,
                    'puntos_max' => 20.0,
                ],
                'totales' => [
                    'compradores_hoy' => 0,
                    'clientes_con_ampliacion' => 0,
                    'total_skus_preventa' => 0,
                    'total_skus_ampliacion' => 0,
                    'porcentaje_ampliacion_ruta' => 0.0,
                    'cumple_umbral' => false,
                    'puntos_obtenidos' => 0.0,
                ],
                'tiendas' => [],
            ];
        }
    }

    /**
     * Normaliza un nombre de producto para match flexible.
     */
    private function normalizar(string $str): string
    {
        $s = mb_strtolower(trim($str));
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $s);
        return preg_replace('/\s+/', ' ', $s);
    }
}
