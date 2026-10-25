<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventaProductosDetalleAction
{
    /**
     * Obtiene el desglose de venta por ruta y preventista para un producto específico.
     *
     * @param int $productoId
     * @param string|null $fecha
     * @param string|null $canal
     * @param array<int, string>|string|null $rutas
     * @return array<string, mixed>
     */
    public function execute(
        int $productoId,
        ?string $fecha = null,
        ?string $canal = null,
        array|string|null $rutas = null
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
            // Nombre del producto
            $prodRow = DB::connection('supervisor')->table('fact_preventas')
                ->where('producto_id', $productoId)
                ->selectRaw("MAX(TRIM(producto)) as prod_nombre")
                ->first();

            $prodNombre = ($prodRow && $prodRow->prod_nombre) ? $prodRow->prod_nombre : "SKU #{$productoId}";

            // Query por ruta
            $query = DB::connection('supervisor')->table('fact_preventas as p')
                ->leftJoin('dim_rutas as d', DB::raw("UPPER(TRIM(p.ruta))"), '=', DB::raw("UPPER(TRIM(d.ruta))"))
                ->where('p.fecha_norm', $fechaNorm)
                ->where('p.producto_id', $productoId)
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

            $filas = $query
                ->select([
                    'p.ruta',
                    DB::raw("COALESCE(d.vendedor, p.vendedor, 'NO ASIGNADO') as vendedor"),
                    DB::raw("COALESCE(SUM(p.monto_final), 0) as monto"),
                    DB::raw("COALESCE(SUM(p.cantidad), 0) as cantidad"),
                    DB::raw("COUNT(DISTINCT p.cliente_id) as pdvs"),
                ])
                ->groupBy('p.ruta', 'd.vendedor', 'p.vendedor')
                ->orderByDesc('monto')
                ->get();

            $totalProdMonto = (float) $filas->sum('monto');
            $rutasDetalle = [];

            foreach ($filas as $r) {
                $m = (float) $r->monto;
                $pctRuta = $totalProdMonto > 0 ? round(($m / $totalProdMonto) * 100.0, 1) : 0.0;
                $rutasDetalle[] = [
                    'ruta' => $r->ruta,
                    'vendedor' => $r->vendedor,
                    'monto' => round($m, 2),
                    'cantidad' => round((float) $r->cantidad, 2),
                    'pdvs' => (int) $r->pdvs,
                    'pct_ruta' => $pctRuta,
                ];
            }

            return [
                'producto_id' => $productoId,
                'producto' => $prodNombre,
                'total_monto' => round($totalProdMonto, 2),
                'total_rutas' => count($rutasDetalle),
                'rutas' => $rutasDetalle,
            ];
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo detalle de rutas por producto: ' . $e->getMessage());
            return [
                'producto_id' => $productoId,
                'producto' => "SKU #{$productoId}",
                'total_monto' => 0.0,
                'total_rutas' => 0,
                'rutas' => [],
            ];
        }
    }
}

