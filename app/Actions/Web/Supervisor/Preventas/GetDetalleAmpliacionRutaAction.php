<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleAmpliacionRutaAction
{
    private const MINIMO_SKUS_NUEVOS = 2;
    private const UMBRAL_AMPLIACION_PCT = 60.0;

    /**
     * Obtiene la auditoría analítica del KPI 7 (Ampliación de Portafolio por Tienda y SKUs nuevos).
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

            // 1. Preventas del día
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
                    'totales' => [
                        'compradores_hoy' => 0,
                        'clientes_con_ampliacion' => 0,
                        'porcentaje_ampliacion_ruta' => 0.0,
                        'cumple_umbral' => false,
                        'puntos_obtenidos' => 0.0,
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

            // 2. Historial de SKUs comprados por cada cliente (últimas 13 semanas)
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
                ->select('cliente_id', 'producto_id')
                ->distinct()
                ->get();

            $skusHistoricosPorCliente = [];
            foreach ($histVentas as $hv) {
                $cid = (int) $hv->cliente_id;
                $pid = (int) $hv->producto_id;
                $skusHistoricosPorCliente[$cid][$pid] = true;
            }

            // 3. Evaluar ampliación de portafolio por tienda
            $tiendas = [];
            $clientesConAmpliacionCount = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $historialSkus = $skusHistoricosPorCliente[$cid] ?? [];
                $pedidoHoy = $cData['pedido_hoy_map'];

                $skusNuevos = [];
                $skusHabituales = [];

                foreach ($pedidoHoy as $pid => $ph) {
                    if (!isset($historialSkus[$pid])) {
                        $skusNuevos[] = $ph;
                    } else {
                        $skusHabituales[] = $ph;
                    }
                }

                $cantNuevos = count($skusNuevos);
                $tieneAmpliacion = $cantNuevos >= self::MINIMO_SKUS_NUEVOS;
                if ($tieneAmpliacion) {
                    $clientesConAmpliacionCount++;
                }

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'cliente' => $cData['cliente'],
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'total_skus_hoy' => count($pedidoHoy),
                    'skus_nuevos_count' => $cantNuevos,
                    'skus_habituales_count' => count($skusHabituales),
                    'cumple_ampliacion' => $tieneAmpliacion,
                    'skus_nuevos' => $skusNuevos,
                    'skus_habituales' => $skusHabituales,
                ];
            }

            usort($tiendas, fn ($a, $b) => $b['total_bs_hoy'] <=> $a['total_bs_hoy']);

            $totalCompradores = count($clientesCompradores);
            $pctAmpliacionRuta = $totalCompradores > 0
                ? round(($clientesConAmpliacionCount / $totalCompradores) * 100.0, 1)
                : 0.0;

            $cumpleUmbral = $pctAmpliacionRuta >= self::UMBRAL_AMPLIACION_PCT;

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                    'minimo_skus_nuevos_tienda' => self::MINIMO_SKUS_NUEVOS,
                ],
                'totales' => [
                    'compradores_hoy' => $totalCompradores,
                    'clientes_con_ampliacion' => $clientesConAmpliacionCount,
                    'porcentaje_ampliacion_ruta' => $pctAmpliacionRuta,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $cumpleUmbral ? 20.0 : 0.0,
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
                'totales' => [
                    'compradores_hoy' => 0,
                    'clientes_con_ampliacion' => 0,
                    'porcentaje_ampliacion_ruta' => 0.0,
                    'cumple_umbral' => false,
                    'puntos_obtenidos' => 0.0,
                ],
                'tiendas' => [],
            ];
        }
    }
}

