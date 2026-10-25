<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventaProductosHeatmapAction
{
    /**
     * Obtiene la matriz 2D (heatmap) de Top N productos vs Rutas de preventa.
     *
     * @param string|null $fecha
     * @param string|null $canal
     * @param array<int, string>|string|null $rutas
     * @param string|null $categoria
     * @param int $topSkus
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fecha = null,
        ?string $canal = null,
        array|string|null $rutas = null,
        ?string $categoria = null,
        int $topSkus = 30
    ): array {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        // Normalizar lista de rutas
        $rutasList = [];
        if (is_array($rutas)) {
            $rutasList = array_values(array_filter(array_map('trim', $rutas)));
        } elseif (is_string($rutas) && trim($rutas) !== '') {
            $rutasList = array_values(array_filter(array_map('trim', explode(',', $rutas))));
        }

        try {
            // Base query builder
            $applyFilters = function ($q) use ($fechaNorm, $canal, $rutasList, $categoria): void {
                $q->where('p.fecha_norm', $fechaNorm)
                  ->where('p.monto_final', '>', 0);

                if ($canal && $canal !== 'TODOS') {
                    $canalesList = array_filter(array_map('trim', explode(',', strtoupper($canal))));
                    if (!empty($canalesList)) {
                        $q->whereIn(DB::raw("UPPER(TRIM(p.ruta))"), function ($sub) use ($canalesList): void {
                            $sub->select(DB::raw("UPPER(TRIM(ruta))"))
                                ->from('dim_rutas')
                                ->whereIn(DB::raw("UPPER(TRIM(canal))"), $canalesList);
                        });
                    }
                }

                if (!empty($rutasList)) {
                    $q->whereIn(DB::raw("UPPER(TRIM(p.ruta))"), array_map('strtoupper', $rutasList));
                }

                if ($categoria && $categoria !== 'TODAS') {
                    $q->whereRaw("UPPER(TRIM(p.categoria)) = ?", [strtoupper(trim($categoria))]);
                }
            };

            // 1. Top N SKUs
            $topSkusQuery = DB::connection('supervisor')->table('fact_preventas as p');
            $applyFilters($topSkusQuery);

            $topSkusRows = $topSkusQuery
                ->select([
                    'p.producto_id',
                    DB::raw("MAX(TRIM(p.producto)) as producto"),
                    DB::raw("MAX(TRIM(COALESCE(p.categoria, 'GENERAL'))) as categoria"),
                    DB::raw("SUM(p.monto_final) as total_m"),
                ])
                ->groupBy('p.producto_id')
                ->orderByDesc('total_m')
                ->limit($topSkus)
                ->get();

            if ($topSkusRows->isEmpty()) {
                return [
                    'rutas' => [],
                    'productos' => [],
                    'matrix' => (object) [],
                    'max_monto' => 0.0,
                    'max_cantidad' => 0.0,
                ];
            }

            $skuIds = $topSkusRows->pluck('producto_id')->toArray();

            // 2. Rutas activas
            $rutasQuery = DB::connection('supervisor')->table('fact_preventas as p')
                ->leftJoin('dim_rutas as d', DB::raw("UPPER(TRIM(p.ruta))"), '=', DB::raw("UPPER(TRIM(d.ruta))"));
            $applyFilters($rutasQuery);

            $rutasRows = $rutasQuery
                ->select([
                    'p.ruta',
                    DB::raw("COALESCE(d.vendedor, 'NO ASIGNADO') as vendedor"),
                ])
                ->distinct()
                ->orderBy('p.ruta', 'asc')
                ->get();

            // 3. Matriz por producto_id y ruta
            $matrizQuery = DB::connection('supervisor')->table('fact_preventas as p');
            $applyFilters($matrizQuery);

            $matrizRows = $matrizQuery
                ->whereIn('p.producto_id', $skuIds)
                ->select([
                    'p.producto_id',
                    'p.ruta',
                    DB::raw("SUM(p.monto_final) as monto"),
                    DB::raw("SUM(p.cantidad) as cantidad"),
                    DB::raw("COUNT(DISTINCT p.cliente_id) as pdvs"),
                ])
                ->groupBy('p.producto_id', 'p.ruta')
                ->get();

            $matrix = [];
            $maxMonto = 0.0;
            $maxCant = 0.0;

            foreach ($matrizRows as $r) {
                $pid = (string) $r->producto_id;
                $ruta = (string) $r->ruta;
                $monto = (float) ($r->monto ?: 0.0);
                $cant = (float) ($r->cantidad ?: 0.0);
                $pdvs = (int) ($r->pdvs ?: 0);

                if ($monto > $maxMonto) {
                    $maxMonto = $monto;
                }
                if ($cant > $maxCant) {
                    $maxCant = $cant;
                }

                if (!isset($matrix[$pid])) {
                    $matrix[$pid] = [];
                }
                $matrix[$pid][$ruta] = [
                    'monto' => round($monto, 2),
                    'cantidad' => round($cant, 2),
                    'pdvs' => $pdvs,
                ];
            }

            $productosList = [];
            foreach ($topSkusRows as $ts) {
                $productosList[] = [
                    'producto_id' => (int) $ts->producto_id,
                    'producto' => $ts->producto,
                    'categoria' => $ts->categoria,
                    'total_monto' => round((float) $ts->total_m, 2),
                ];
            }

            $rutasListFormatted = [];
            foreach ($rutasRows as $rr) {
                $rutasListFormatted[] = [
                    'ruta' => $rr->ruta,
                    'vendedor' => $rr->vendedor,
                ];
            }

            return [
                'max_monto' => round($maxMonto, 2),
                'max_cantidad' => round($maxCant, 2),
                'rutas' => $rutasListFormatted,
                'productos' => $productosList,
                'matrix' => (object) $matrix,
            ];
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo heatmap de productos: ' . $e->getMessage());
            return [
                'rutas' => [],
                'productos' => [],
                'matrix' => (object) [],
                'max_monto' => 0.0,
                'max_cantidad' => 0.0,
            ];
        }
    }
}

