<?php

declare(strict_types=1);

namespace App\Actions\Api\InventarioCliente;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\Supervisor\FactVenta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetClienteProductosHistorialAction
{
    /**
     * Obtiene el listado simplificado de productos comprados por el cliente en el período configurado.
     * Enlazado directamente al cliente_id y ruta de pan_ruteo.
     * Retorna únicamente: codigo, producto, categoria, total_comprado, ultima_compra, historial.
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
            ->first(['cliente_id', 'cliente', 'ruta']);

        if (!$clientePlan) {
            $clientePlan = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('cliente_id', $clienteId)
                ->first(['cliente_id', 'cliente', 'ruta']);
        }

        $clienteNombre = $clientePlan?->cliente ? trim((string) $clientePlan->cliente) : null;
        $rutaCliente = $clientePlan?->ruta ? trim((string) $clientePlan->ruta) : $ruta;

        // 2. Obtener los días de historial configurados para la ruta o por defecto global (default 30)
        $diasHistorial = 30;

        if (!empty($rutaCliente)) {
            $routeOverride = AppModuleRoute::where('ruta', $rutaCliente)
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

        // 3. Consultar compras válidas del cliente en el rango en fact_ventas (ERP)
        $ventas = FactVenta::query()
            ->validas()
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $fechaDesde)
            ->orderByDesc('fecha_norm')
            ->orderByDesc('hora')
            ->get([
                'codigo',
                'producto',
                'categoria',
                'fecha_norm',
                'cantidad',
                'cliente',
            ]);

        $productosMap = [];

        foreach ($ventas as $v) {
            if (!$clienteNombre && !empty($v->cliente)) {
                $clienteNombre = trim((string) $v->cliente);
            }

            $cod = trim((string) ($v->codigo ?? ''));
            if ($cod === '') {
                continue;
            }

            if (!isset($productosMap[$cod])) {
                $productosMap[$cod] = [
                    'codigo' => $cod,
                    'producto' => trim((string) ($v->producto ?? '')),
                    'categoria' => trim((string) ($v->categoria ?? '')),
                    'total_comprado' => 0,
                    'ultima_compra' => null,
                    '_fechas' => [],
                ];
            }

            $cant = (int) $v->cantidad;
            $fecha = (string) $v->fecha_norm;

            $productosMap[$cod]['total_comprado'] += $cant;

            if (!isset($productosMap[$cod]['_fechas'][$fecha])) {
                $productosMap[$cod]['_fechas'][$fecha] = 0;
            }
            $productosMap[$cod]['_fechas'][$fecha] += $cant;
        }

        // 4. Formatear historial agrupado por fecha y determinar última compra
        $productosList = [];
        foreach ($productosMap as $item) {
            $historial = [];
            krsort($item['_fechas']);

            foreach ($item['_fechas'] as $f => $c) {
                $historial[] = [
                    'fecha' => $f,
                    'cantidad' => $c,
                ];
            }

            $item['ultima_compra'] = $historial[0] ?? null;
            $item['historial'] = $historial;
            unset($item['_fechas']);

            $productosList[] = $item;
        }

        // Ordenar productos de mayor a menor compras acumuladas
        usort($productosList, fn($a, $b) => $b['total_comprado'] <=> $a['total_comprado']);

        return [
            'success' => true,
            'cliente_id' => is_numeric($clienteId) ? (int) $clienteId : $clienteId,
            'cliente' => $clienteNombre,
            'ruta' => $rutaCliente,
            'dias_historial' => $diasHistorial,
            'total_productos' => count($productosList),
            'productos' => $productosList,
        ];
    }
}


