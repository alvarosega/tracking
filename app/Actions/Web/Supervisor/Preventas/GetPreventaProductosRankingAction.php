<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventaProductosRankingAction
{
    /**
     * Obtiene el ranking de SKUs más vendidos con filtros por fecha, canal, rutas y categoría.
     *
     * @param string|null $fecha
     * @param string|null $canal
     * @param array<int, string>|string|null $rutas
     * @param string|null $categoria
     * @param string|null $busqueda
     * @param int $limit
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fecha = null,
        ?string $canal = null,
        array|string|null $rutas = null,
        ?string $categoria = null,
        ?string $busqueda = null,
        int $limit = 150
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
            // 1. Canales y rutas disponibles para filtros
            $rutasDim = DB::connection('supervisor')->table('dim_rutas')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->select(['ruta', 'canal', 'vendedor'])
                ->orderBy('canal', 'asc')
                ->orderBy('ruta', 'asc')
                ->get();

            $canalesDisponibles = $rutasDim->pluck('canal')->filter()->unique()->sort()->values()->toArray();
            $rutasDisponibles = $rutasDim->map(fn ($r) => [
                'ruta' => $r->ruta,
                'canal' => $r->canal,
                'vendedor' => $r->vendedor,
            ])->toArray();

            // 2. Query de ranking en fact_preventas
            $query = DB::connection('supervisor')->table('fact_preventas as p')
                ->where('p.fecha_norm', $fechaNorm)
                ->where('p.monto_final', '>', 0);

            if ($canal && $canal !== 'TODOS') {
                $canalesList = array_filter(array_map('trim', explode(',', strtoupper($canal))));
                if (!empty($canalesList)) {
                    $query->whereIn(DB::raw("UPPER(TRIM(p.ruta))"), function ($sub) use ($canalesList): void {
                        $sub->select(DB::raw("UPPER(TRIM(ruta))"))
                            ->from('dim_rutas')
                            ->whereIn(DB::raw("UPPER(TRIM(canal))"), $canalesList);
                    });
                }
            }

            if (!empty($rutasList)) {
                $query->whereIn(DB::raw("UPPER(TRIM(p.ruta))"), array_map('strtoupper', $rutasList));
            }

            if ($categoria && $categoria !== 'TODAS') {
                $query->whereRaw("UPPER(TRIM(p.categoria)) = ?", [strtoupper(trim($categoria))]);
            }

            if ($busqueda && trim($busqueda) !== '') {
                $b = '%' . strtoupper(trim($busqueda)) . '%';
                $query->where(function ($sub) use ($b): void {
                    $sub->whereRaw("UPPER(p.producto) LIKE ?", [$b])
                        ->orWhereRaw("UPPER(p.categoria) LIKE ?", [$b])
                        ->orWhereRaw("CAST(p.producto_id AS CHAR) LIKE ?", [$b]);
                });
            }

            $filas = $query
                ->select([
                    'p.producto_id',
                    DB::raw("MAX(TRIM(p.producto)) as producto"),
                    DB::raw("MAX(TRIM(COALESCE(p.categoria, 'GENERAL'))) as categoria"),
                    DB::raw("COALESCE(SUM(p.monto_final), 0) as monto_total"),
                    DB::raw("COALESCE(SUM(p.cantidad), 0) as cantidad_total"),
                    DB::raw("COUNT(DISTINCT p.cliente_id) as pdvs_compradores"),
                    DB::raw("COUNT(DISTINCT p.ruta) as rutas_cobertura"),
                ])
                ->groupBy('p.producto_id')
                ->orderByDesc('monto_total')
                ->limit($limit)
                ->get();

            $totalVenta = (float) $filas->sum('monto_total');
            $totalCantidad = (float) $filas->sum('cantidad_total');

            $ranking = [];
            foreach ($filas as $f) {
                $m = (float) $f->monto_total;
                $pct = $totalVenta > 0 ? round(($m / $totalVenta) * 100.0, 2) : 0.0;
                $ranking[] = [
                    'producto_id' => (int) $f->producto_id,
                    'producto' => $f->producto,
                    'categoria' => $f->categoria,
                    'monto_total' => round($m, 2),
                    'cantidad_total' => round((float) $f->cantidad_total, 2),
                    'pdvs_compradores' => (int) $f->pdvs_compradores,
                    'rutas_cobertura' => (int) $f->rutas_cobertura,
                    'pct_participacion' => $pct,
                ];
            }

            return [
                'fecha' => $fechaNorm,
                'canales_disponibles' => $canalesDisponibles,
                'rutas_disponibles' => $rutasDisponibles,
                'total_venta' => round($totalVenta, 2),
                'total_cantidad' => round($totalCantidad, 2),
                'total_skus' => count($ranking),
                'productos' => $ranking,
            ];
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo ranking de productos: ' . $e->getMessage());
            return [
                'fecha' => $fechaNorm,
                'canales_disponibles' => [],
                'rutas_disponibles' => [],
                'total_venta' => 0.0,
                'total_cantidad' => 0.0,
                'total_skus' => 0,
                'productos' => [],
            ];
        }
    }
}

