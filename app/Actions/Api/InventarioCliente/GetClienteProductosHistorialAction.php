<?php

declare(strict_types=1);

namespace App\Actions\Api\InventarioCliente;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\ClienteInventario;
use App\Models\Supervisor\FactVenta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetClienteProductosHistorialAction
{
    /**
     * Obtiene el listado completo de productos del cliente para la toma de inventario físico en PDV:
     * - Historial de compras (ERP / fact_ventas).
     * - Jerarquías de producto (jerarquia1, jerarquia2, jerarquia3, categoría) para ordenamiento y filtros en Android.
     * - Historial de tomas físicas previas (cliente_inventarios / cliente_inventario_items).
     * - Cálculo del disponible teórico y compras posteriores al último conteo físico.
     *
     * @return array<string, mixed>
     */
    public function execute(int|string $clienteId, ?string $ruta = null): array
    {
        // 1. Enlace con pan_ruteo: Verificar existencia del cliente y obtener su ruta oficial
        $clientePlan = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->where('cliente_id', $clienteId)
            ->when(!empty($ruta), fn($q) => $q->where('ruta', $ruta))
            ->first(['cliente_id', 'cliente', 'codigo_cliente', 'ruta']);

        if (!$clientePlan) {
            $clientePlan = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('cliente_id', $clienteId)
                ->first(['cliente_id', 'cliente', 'codigo_cliente', 'ruta']);
        }

        $clienteNombre = $clientePlan?->cliente ? trim((string) $clientePlan->cliente) : null;
        $codigoCliente = $clientePlan?->codigo_cliente ? trim((string) $clientePlan->codigo_cliente) : null;
        $rutaCliente = $clientePlan?->ruta ? trim((string) $clientePlan->ruta) : $ruta;

        // 2. Obtener los días de historial configurados para la ruta o por defecto global (default 30)
        $diasHistorial = null;

        if (!empty($rutaCliente)) {
            $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $rutaCliente)));
            $routeOverride = AppModuleRoute::whereRaw("TRIM(UPPER(ruta)) = ?", [$cleanRuta])
                ->where('module_code', 'inventario_cliente')
                ->first();

            if ($routeOverride && isset($routeOverride->config['dias_historial']) && is_numeric($routeOverride->config['dias_historial'])) {
                $diasHistorial = max(1, (int) $routeOverride->config['dias_historial']);
            }
        }

        if ($diasHistorial === null) {
            $modulo = AppModule::where('code', 'inventario_cliente')->first();
            if ($modulo && isset($modulo->config['dias_historial']) && is_numeric($modulo->config['dias_historial'])) {
                $diasHistorial = max(1, (int) $modulo->config['dias_historial']);
            }
        }

        $diasHistorial = $diasHistorial ?? 30;

        $hoy = Carbon::now();
        $fechaDesde = $hoy->copy()->subDays($diasHistorial)->format('Y-m-d');

        // 3. Consultar tomas de inventarios físicos previos del cliente (en base mysql)
        $stocksPorCodigo = [];
        $prevProductosInfo = [];

        try {
            $inventariosPrevios = ClienteInventario::with(['items'])
                ->where('cliente_id', $clienteId)
                ->orderByDesc('fecha_registro')
                ->take(5)
                ->get();

            foreach ($inventariosPrevios as $inv) {
                $fechaRegistro = $inv->fecha_registro?->format('Y-m-d H:i:s') ?? (string) $inv->fecha_registro;
                if (empty($fechaRegistro)) {
                    continue;
                }

                foreach ($inv->items as $item) {
                    $cod = trim((string) ($item->codigo_producto ?? ''));
                    if ($cod === '') {
                        continue;
                    }

                    if (!isset($stocksPorCodigo[$cod])) {
                        $stocksPorCodigo[$cod] = [];
                    }

                    $stocksPorCodigo[$cod][] = [
                        'fecha' => $fechaRegistro,
                        'stock_fisico' => (int) $item->stock_fisico,
                    ];

                    if (!isset($prevProductosInfo[$cod])) {
                        $prevProductosInfo[$cod] = [
                            'producto_id' => $item->producto_id ? (int) $item->producto_id : null,
                            'producto' => $item->producto_nombre ? trim((string) $item->producto_nombre) : null,
                            'categoria' => $item->categoria ? trim((string) $item->categoria) : null,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo consultar historial de inventarios previos: ' . $e->getMessage());
        }

        // 4. Consultar compras válidas del cliente en el rango en fact_ventas (supervisor ERP)
        $ventas = FactVenta::query()
            ->validas()
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $fechaDesde)
            ->orderByDesc('fecha_norm')
            ->orderByDesc('hora')
            ->get([
                'producto_id',
                'codigo',
                'producto',
                'categoria',
                'jerarquia1',
                'jerarquia2',
                'jerarquia3',
                'fecha_norm',
                'hora',
                'cantidad',
                'cliente',
                'codigo_cliente',
            ]);

        $productosMap = [];

        foreach ($ventas as $v) {
            if (!$clienteNombre && !empty($v->cliente)) {
                $clienteNombre = trim((string) $v->cliente);
            }
            if (!$codigoCliente && !empty($v->codigo_cliente)) {
                $codigoCliente = trim((string) $v->codigo_cliente);
            }

            $cod = trim((string) ($v->codigo ?? ''));
            if ($cod === '') {
                continue;
            }

            $cant = (int) $v->cantidad;
            $fecha = (string) $v->fecha_norm;
            $hora = $v->hora ? trim((string) $v->hora) : '';

            if (!isset($productosMap[$cod])) {
                $j1 = trim((string) ($v->jerarquia1 ?? ''));
                $j2 = trim((string) ($v->jerarquia2 ?? ''));
                $j3 = trim((string) ($v->jerarquia3 ?? ''));
                $cat = trim((string) ($v->categoria ?? ''));

                $productosMap[$cod] = [
                    'producto_id' => $v->producto_id ? (int) $v->producto_id : null,
                    'codigo' => $cod,
                    'producto' => trim((string) ($v->producto ?? '')),
                    'categoria' => $cat !== '' ? $cat : 'GENERAL',
                    'jerarquia1' => $j1 !== '' ? $j1 : 'GENERAL',
                    'jerarquia2' => $j2 !== '' ? $j2 : 'GENERAL',
                    'jerarquia3' => $j3 !== '' ? $j3 : 'GENERAL',
                    'total_comprado' => 0,
                    '_fechas' => [],
                    '_compras' => [],
                ];
            }

            $productosMap[$cod]['total_comprado'] += $cant;

            if (!isset($productosMap[$cod]['_fechas'][$fecha])) {
                $productosMap[$cod]['_fechas'][$fecha] = 0;
            }
            $productosMap[$cod]['_fechas'][$fecha] += $cant;

            $productosMap[$cod]['_compras'][] = [
                'fecha' => $fecha,
                'hora' => $hora,
                'cantidad' => $cant,
            ];
        }

        // 5. Incluir productos que se censaron previamente pero no tuvieron compras en los últimos X días
        $missingCodigos = array_values(array_diff(array_keys($stocksPorCodigo), array_keys($productosMap)));

        if (!empty($missingCodigos)) {
            $maestroMap = [];
            try {
                $maestros = FactVenta::query()
                    ->validas()
                    ->whereIn('codigo', $missingCodigos)
                    ->select([
                        'codigo',
                        'producto_id',
                        'producto',
                        'categoria',
                        'jerarquia1',
                        'jerarquia2',
                        'jerarquia3',
                    ])
                    ->groupBy([
                        'codigo',
                        'producto_id',
                        'producto',
                        'categoria',
                        'jerarquia1',
                        'jerarquia2',
                        'jerarquia3',
                    ])
                    ->get();

                foreach ($maestros as $m) {
                    $c = trim((string) $m->codigo);
                    if (!isset($maestroMap[$c])) {
                        $maestroMap[$c] = $m;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo consultar catálogo maestro para productos censados sin venta reciente: ' . $e->getMessage());
            }

            foreach ($missingCodigos as $missingCod) {
                $m = $maestroMap[$missingCod] ?? null;
                $prev = $prevProductosInfo[$missingCod] ?? null;

                $j1 = trim((string) ($m?->jerarquia1 ?? ''));
                $j2 = trim((string) ($m?->jerarquia2 ?? ''));
                $j3 = trim((string) ($m?->jerarquia3 ?? ''));
                $cat = trim((string) ($m?->categoria ?? ($prev['categoria'] ?? '')));

                $productosMap[$missingCod] = [
                    'producto_id' => $m?->producto_id ? (int) $m->producto_id : ($prev['producto_id'] ?? null),
                    'codigo' => $missingCod,
                    'producto' => trim((string) ($m?->producto ?? ($prev['producto'] ?? "Producto {$missingCod}"))),
                    'categoria' => $cat !== '' ? $cat : 'GENERAL',
                    'jerarquia1' => $j1 !== '' ? $j1 : 'GENERAL',
                    'jerarquia2' => $j2 !== '' ? $j2 : 'GENERAL',
                    'jerarquia3' => $j3 !== '' ? $j3 : 'GENERAL',
                    'total_comprado' => 0,
                    '_fechas' => [],
                    '_compras' => [],
                ];
            }
        }

        // 6. Formatear historial, compras, stocks y balances por producto
        $productosList = [];
        $jerarquias1 = [];
        $jerarquias2 = [];
        $jerarquias3 = [];
        $categorias = [];

        foreach ($productosMap as $cod => $item) {
            // 6.1 Historial de compras agrupado por fecha
            $historialCompras = [];
            krsort($item['_fechas']);
            foreach ($item['_fechas'] as $f => $c) {
                $historialCompras[] = [
                    'fecha' => (string) $f,
                    'cantidad' => (int) $c,
                ];
            }

            $ultimaCompra = $historialCompras[0] ?? null;

            // 6.2 Historial de stocks físicos previos
            $historicoStocks = $stocksPorCodigo[$cod] ?? [];
            $ultimoStock = $historicoStocks[0] ?? null;

            // 6.3 Compras ocurridas DESPUÉS del último stock contado
            $comprasDesdeUltimoStock = 0;
            if ($ultimoStock !== null && !empty($ultimoStock['fecha'])) {
                $fechaUltimoStock = (string) $ultimoStock['fecha'];
                $datePart = substr($fechaUltimoStock, 0, 10);
                $timePart = strlen($fechaUltimoStock) >= 19 ? substr($fechaUltimoStock, 11, 8) : '00:00:00';

                foreach ($item['_compras'] as $compra) {
                    $cFecha = $compra['fecha'];
                    $cHora = $compra['hora'];

                    if ($cFecha > $datePart) {
                        $comprasDesdeUltimoStock += $compra['cantidad'];
                    } elseif ($cFecha === $datePart) {
                        if (!empty($cHora) && $cHora > $timePart) {
                            $comprasDesdeUltimoStock += $compra['cantidad'];
                        }
                    }
                }
            }

            // 6.4 Stock Teórico Disponible = Último conteo físico + Compras recibidas desde entonces
            $stockTeorico = $ultimoStock !== null
                ? ($ultimoStock['stock_fisico'] + $comprasDesdeUltimoStock)
                : null;

            // Guardar para filtros globales
            $jerarquias1[] = $item['jerarquia1'];
            $jerarquias2[] = $item['jerarquia2'];
            $jerarquias3[] = $item['jerarquia3'];
            $categorias[] = $item['categoria'];

            $productosList[] = [
                'producto_id' => $item['producto_id'],
                'codigo' => $item['codigo'],
                'codigo_producto' => $item['codigo'],
                'producto' => $item['producto'],
                'producto_nombre' => $item['producto'],
                'categoria' => $item['categoria'],
                'jerarquia1' => $item['jerarquia1'],
                'jerarquia2' => $item['jerarquia2'],
                'jerarquia3' => $item['jerarquia3'],
                'total_comprado' => $item['total_comprado'],
                'ultima_compra' => $ultimaCompra,
                'historial' => $historialCompras, // Retrocompatibilidad con la app actual
                'historial_compras' => $historialCompras,
                'ultimo_stock' => $ultimoStock,
                'compras_desde_ultimo_stock' => $comprasDesdeUltimoStock,
                'stock_teorico_disponible' => $stockTeorico,
                'historico_stocks' => $historicoStocks,
            ];
        }

        // 7. Ordenar por jerarquías (jerarquia1, jerarquia2, jerarquia3), mayor compra y producto
        usort($productosList, function (array $a, array $b): int {
            $cmpJ1 = strcmp((string) $a['jerarquia1'], (string) $b['jerarquia1']);
            if ($cmpJ1 !== 0) {
                return $cmpJ1;
            }

            $cmpJ2 = strcmp((string) $a['jerarquia2'], (string) $b['jerarquia2']);
            if ($cmpJ2 !== 0) {
                return $cmpJ2;
            }

            $cmpJ3 = strcmp((string) $a['jerarquia3'], (string) $b['jerarquia3']);
            if ($cmpJ3 !== 0) {
                return $cmpJ3;
            }

            $cmpVentas = $b['total_comprado'] <=> $a['total_comprado'];
            if ($cmpVentas !== 0) {
                return $cmpVentas;
            }

            return strcmp((string) $a['producto'], (string) $b['producto']);
        });

        // 8. Compilar filtros únicos ordenados
        $uniqueJ1 = array_values(array_unique(array_filter($jerarquias1)));
        sort($uniqueJ1, SORT_STRING | SORT_FLAG_CASE);

        $uniqueJ2 = array_values(array_unique(array_filter($jerarquias2)));
        sort($uniqueJ2, SORT_STRING | SORT_FLAG_CASE);

        $uniqueJ3 = array_values(array_unique(array_filter($jerarquias3)));
        sort($uniqueJ3, SORT_STRING | SORT_FLAG_CASE);

        $uniqueCat = array_values(array_unique(array_filter($categorias)));
        sort($uniqueCat, SORT_STRING | SORT_FLAG_CASE);

        return [
            'success' => true,
            'cliente_id' => is_numeric($clienteId) ? (int) $clienteId : $clienteId,
            'cliente' => $clienteNombre,
            'codigo_cliente' => $codigoCliente,
            'ruta' => $rutaCliente,
            'dias_historial' => $diasHistorial,
            'total_productos' => count($productosList),
            'filtros' => [
                'jerarquia1' => $uniqueJ1,
                'jerarquia2' => $uniqueJ2,
                'jerarquia3' => $uniqueJ3,
                'categorias' => $uniqueCat,
            ],
            'productos' => $productosList,
        ];
    }
}
