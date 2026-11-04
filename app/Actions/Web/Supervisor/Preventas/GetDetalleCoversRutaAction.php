<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleCoversRutaAction
{
    private const UMBRAL_COVERS_PCT = 50.0;

    /**
     * Obtiene la auditoría analítica del KPI 8: Covers (Productos con Descuento).
     * Criterio: De los clientes compradores de la ruta, al menos el 50.0% debe tener
     * algún producto con descuento en la preventa para sumar los 5 puntos base.
     *
     * @param string $ruta
     * @param string|null $fecha
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

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

            // 1. Preventas del día para la ruta
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
                    DB::raw("COALESCE(precio_lista, 0.0) as precio_lista"),
                    DB::raw("COALESCE(descuento, 0.0) as descuento"),
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
                        'umbral_covers_pct' => self::UMBRAL_COVERS_PCT,
                        'puntos_max' => 5.0,
                    ],
                    'totales' => [
                        'compradores_hoy' => 0,
                        'clientes_con_descuento' => 0,
                        'clientes_sin_descuento' => 0,
                        'porcentaje_covers_ruta' => 0.0,
                        'cumple_umbral' => false,
                        'puntos_obtenidos' => 0.0,
                    ],
                    'tiendas' => [],
                ];
            }

            // 2. Agrupar compras de hoy por cliente
            $clientesCompradores = [];
            foreach ($preventasHoy as $f) {
                $cid = (int) $f->cliente_id;
                if (!isset($clientesCompradores[$cid])) {
                    $clientesCompradores[$cid] = [
                        'cliente_id' => $cid,
                        'codigo_cliente' => $f->codigo_cliente ?: (string) $cid,
                        'cliente' => $f->cliente,
                        'total_bs_hoy' => 0.0,
                        'descuento_total_bs' => 0.0,
                        'items' => [],
                    ];
                }

                $monto = (float) $f->monto;
                $descuento = (float) $f->descuento;
                $conDescuento = ($descuento > 0);

                $clientesCompradores[$cid]['total_bs_hoy'] += $monto;
                $clientesCompradores[$cid]['descuento_total_bs'] += $descuento;

                $clientesCompradores[$cid]['items'][] = [
                    'producto_id' => (int) $f->producto_id,
                    'codigo_producto' => $f->codigo_producto,
                    'producto' => $f->producto,
                    'categoria' => $f->categoria,
                    'cantidad' => (int) $f->cantidad,
                    'precio_lista' => round((float) $f->precio_lista, 2),
                    'descuento' => round($descuento, 2),
                    'monto_final' => round($monto, 2),
                    'con_descuento' => $conDescuento,
                ];
            }

            // 3. Evaluar Covers por Tienda
            $tiendas = [];
            $clientesConDescuentoCount = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $items = $cData['items'];
                $itemsConDescuento = 0;
                $itemsSinDescuento = 0;

                foreach ($items as $it) {
                    if ($it['con_descuento']) {
                        $itemsConDescuento++;
                    } else {
                        $itemsSinDescuento++;
                    }
                }

                $cumple = ($itemsConDescuento > 0);
                if ($cumple) {
                    $clientesConDescuentoCount++;
                }

                // Ordenar items: con descuento primero, luego por monto descendente
                usort($items, function ($a, $b) {
                    if ($a['con_descuento'] !== $b['con_descuento']) {
                        return $b['con_descuento'] <=> $a['con_descuento'];
                    }
                    return $b['monto_final'] <=> $a['monto_final'];
                });

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => $cData['codigo_cliente'],
                    'cliente' => $cData['cliente'],
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'descuento_total_bs' => round($cData['descuento_total_bs'], 2),
                    'total_items_hoy' => count($items),
                    'items_con_descuento' => $itemsConDescuento,
                    'items_sin_descuento' => $itemsSinDescuento,
                    'cumple' => $cumple,
                    'items' => $items,
                ];
            }

            // Ordenar tiendas por monto descendente
            usort($tiendas, fn ($a, $b) => $b['total_bs_hoy'] <=> $a['total_bs_hoy']);

            $totalCompradores = count($clientesCompradores);
            $clientesSinDescuentoCount = max(0, $totalCompradores - $clientesConDescuentoCount);

            $pctCoversRuta = $totalCompradores > 0
                ? round(($clientesConDescuentoCount / $totalCompradores) * 100.0, 1)
                : 0.0;

            $cumpleUmbral = ($totalCompradores > 0 && $pctCoversRuta >= self::UMBRAL_COVERS_PCT);
            $puntosObtenidos = $cumpleUmbral ? 5.0 : 0.0;

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'umbral_covers_pct' => self::UMBRAL_COVERS_PCT,
                    'puntos_max' => 5.0,
                ],
                'totales' => [
                    'compradores_hoy' => $totalCompradores,
                    'clientes_con_descuento' => $clientesConDescuentoCount,
                    'clientes_sin_descuento' => $clientesSinDescuentoCount,
                    'porcentaje_covers_ruta' => $pctCoversRuta,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $puntosObtenidos,
                ],
                'tiendas' => $tiendas,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleCoversRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'umbral_covers_pct' => self::UMBRAL_COVERS_PCT,
                    'puntos_max' => 5.0,
                ],
                'totales' => [
                    'compradores_hoy' => 0,
                    'clientes_con_descuento' => 0,
                    'clientes_sin_descuento' => 0,
                    'porcentaje_covers_ruta' => 0.0,
                    'cumple_umbral' => false,
                    'puntos_obtenidos' => 0.0,
                ],
                'tiendas' => [],
            ];
        }
    }
}

