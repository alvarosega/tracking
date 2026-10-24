<?php

namespace App\Actions\Api;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\Supervisor\FactVenta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetClienteProductosHistorialAction
{
    /**
     * Obtiene el listado de productos que el cliente ha comprado en el periodo histórico configurable.
     * Incluye acumulado, última compra y desglose detallado para el vendedor en Android.
     *
     * @param int|string $clienteId
     * @param string|null $ruta
     * @return array
     */
    public function execute(int|string $clienteId, ?string $ruta = null): array
    {
        // 1. Obtener los días de historial configurados para la ruta o por defecto global (default 30)
        $diasHistorial = 30;

        if (!empty($ruta)) {
            $routeOverride = AppModuleRoute::where('ruta', $ruta)
                ->where('module_code', 'inventario_cliente')
                ->first();

            if ($routeOverride && isset($routeOverride->config['dias_historial'])) {
                $diasHistorial = max(1, (int) $routeOverride->config['dias_historial']);
            }
        }

        if ($diasHistorial === 30) {
            $modulo = AppModule::where('code', 'inventario_cliente')->first();
            if ($modulo && isset($modulo->config['dias_historial'])) {
                $diasHistorial = max(1, (int) $modulo->config['dias_historial']);
            }
        }

        $hoy = Carbon::now();
        $fechaDesde = $hoy->copy()->subDays($diasHistorial)->format('Y-m-d');
        $fechaHasta = $hoy->format('Y-m-d');

        // 2. Consultar compras válidas del cliente en el rango en fact_ventas (ERP)
        $ventas = FactVenta::query()
            ->validas()
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $fechaDesde)
            ->orderByDesc('fecha_norm')
            ->orderByDesc('hora')
            ->get([
                'id',
                'venta_id',
                'pre_venta_id',
                'cliente_id',
                'cliente',
                'producto_id',
                'codigo',
                'producto',
                'categoria',
                'fecha_norm',
                'hora',
                'cantidad',
                'precio_unitario',
                'monto_final',
                'nro_factura',
            ]);

        $clienteNombre = null;
        $productosMap = [];

        foreach ($ventas as $v) {
            if (!$clienteNombre && !empty($v->cliente)) {
                $clienteNombre = trim($v->cliente);
            }

            $prodKey = $v->producto_id ? (string) $v->producto_id : trim((string) $v->codigo);

            if (!isset($productosMap[$prodKey])) {
                $productosMap[$prodKey] = [
                    'producto_id' => $v->producto_id ? (int) $v->producto_id : null,
                    'codigo' => trim((string) $v->codigo),
                    'producto' => trim((string) $v->producto),
                    'categoria' => trim((string) $v->categoria),
                    'cantidad_historica' => 0,
                    'ultima_compra' => [
                        'fecha' => $v->fecha_norm,
                        'hora' => $v->hora,
                        'cantidad' => (int) $v->cantidad,
                        'nro_factura' => $v->nro_factura,
                    ],
                    'detalle_compras' => [],
                    'stock_fisico' => null, // Espacio para captura en Android
                ];
            }

            $productosMap[$prodKey]['cantidad_historica'] += (int) $v->cantidad;

            // Historial detallado de compras para desplegar en el móvil
            $productosMap[$prodKey]['detalle_compras'][] = [
                'id' => $v->id,
                'fecha' => $v->fecha_norm,
                'hora' => $v->hora,
                'cantidad' => (int) $v->cantidad,
                'nro_factura' => $v->nro_factura,
                'monto_final' => (float) $v->monto_final,
            ];
        }

        // Ordenar productos por mayor volumen de compra histórica
        $productosList = array_values($productosMap);
        usort($productosList, fn($a, $b) => $b['cantidad_historica'] <=> $a['cantidad_historica']);

        return [
            'success' => true,
            'cliente_id' => (int) $clienteId,
            'cliente_nombre' => $clienteNombre,
            'configuracion' => [
                'dias_historial' => $diasHistorial,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'total_productos' => count($productosList),
            'productos' => $productosList,
        ];
    }
}

