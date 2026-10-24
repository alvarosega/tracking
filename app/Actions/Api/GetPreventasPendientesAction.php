<?php

namespace App\Actions\Api;

use App\Models\PedidoRechazado;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetPreventasPendientesAction
{
    /**
     * Calcula y devuelve las preventas tomadas (D-2) pendientes de justificar frente a las ventas facturadas (D-1).
     */
    public function execute(User $user, ?string $fechaPreventaParam = null, ?string $clienteId = null, ?string $ruta = null): array
    {
        // 1. Cálculo de fechas operativas por defecto: D-2 (preventa) vs D-1 (facturación)
        $now = now('America/La_Paz');

        if ($now->isMonday()) {
            $defaultFechaPreventa = $now->copy()->subDays(3)->toDateString(); // Viernes
        } else {
            $defaultFechaPreventa = $now->copy()->subDays(2)->toDateString(); // Anteayer (D-2)
        }

        $fechaPreventa = $fechaPreventaParam ?: $defaultFechaPreventa;

        // 2. Preventas ya justificadas por el vendedor en la BD local
        $preventasYaJustificadas = PedidoRechazado::query()
            ->where('user_id', $user->id)
            ->whereNotNull('nro_preventa')
            ->pluck('nro_preventa')
            ->toArray();

        // 3. Consultar las preventas tomadas en fecha D-2 asignadas al vendedor en ERP
        $query = DB::connection('supervisor')->table('fact_preventas as p');

        if (!empty($ruta)) {
            $query->where('p.ruta', $ruta);
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('p.vendedor', $user->name ?? $user->username)
                  ->orWhere('p.username_vendedor', $user->username)
                  ->orWhere('p.ruta', $user->username)
                  ->orWhere('p.ruta', $user->ruta ?? null);
            });
        }

        if (!empty($fechaPreventa)) {
            $query->where('p.fecha_norm', $fechaPreventa);
        }

        if (!empty($clienteId)) {
            $query->where('p.cliente_id', $clienteId);
        }

        if (!empty($preventasYaJustificadas)) {
            $query->whereNotIn('p.nro_preventa', $preventasYaJustificadas);
        }

        $items = $query->select([
            'p.id as preventa_item_id',
            'p.nro_preventa',
            'p.fecha_norm as fecha_preventa',
            'p.cliente_id',
            'p.codigo_cliente',
            'p.cliente as cliente_nombre',
            'p.ruta',
            'p.vendedor',
            'p.producto_id',
            'p.codigo_producto',
            'p.producto as producto_nombre',
            'p.categoria',
            'p.cantidad as cantidad_preventa',
            'p.precio_lista',
            'p.monto_final'
        ])->get();

        if ($items->isEmpty()) {
            return [];
        }

        // 4. Obtener cantidades facturadas en fact_ventas (pre_venta_id = nro_preventa + producto_id)
        $nrosPreventa = $items->pluck('nro_preventa')->filter()->unique()->toArray();
        $ventasPorItem = [];

        if (!empty($nrosPreventa)) {
            $ventasFacturadas = DB::connection('supervisor')
                ->table('fact_ventas')
                ->where('revertida', '!=', 'Si')
                ->whereIn('pre_venta_id', $nrosPreventa)
                ->select('pre_venta_id', 'producto_id', DB::raw('SUM(cantidad) as total_facturado'))
                ->groupBy('pre_venta_id', 'producto_id')
                ->get();

            foreach ($ventasFacturadas as $v) {
                $key = trim($v->pre_venta_id) . '_' . trim($v->producto_id);
                $ventasPorItem[$key] = ($ventasPorItem[$key] ?? 0) + (int) $v->total_facturado;
            }
        }

        // 5. Agrupar ítems por nro_preventa y calcular rechazos totales vs parciales
        $preventasTemp = [];
        foreach ($items as $item) {
            $nro = (string) ($item->nro_preventa ?: $item->preventa_item_id);

            if (!isset($preventasTemp[$nro])) {
                $preventasTemp[$nro] = [
                    'nro_preventa' => $nro,
                    'fecha_preventa' => $item->fecha_preventa,
                    'cliente_id' => (string) $item->cliente_id,
                    'codigo_cliente' => $item->codigo_cliente,
                    'cliente_nombre' => $item->cliente_nombre,
                    'ruta' => $item->ruta,
                    'vendedor' => $item->vendedor,
                    'tipo_sugerido' => 'TOTAL',
                    'monto_total_preventa' => 0.0,
                    'monto_total_facturado' => 0.0,
                    'monto_total_rechazado' => 0.0,
                    'total_items_preventa' => 0,
                    'total_items_facturados' => 0,
                    'total_items_rechazados' => 0,
                    'items_rechazados' => [],
                    'items' => []
                ];
            }

            $cantPreventa = (int) $item->cantidad_preventa;
            $montoItemPreventa = (float) $item->monto_final;
            $precioUnit = ($cantPreventa > 0 && $montoItemPreventa > 0) ? ($montoItemPreventa / $cantPreventa) : 0.0;

            $itemKey = trim($item->nro_preventa) . '_' . trim($item->producto_id);
            $cantFacturada = isset($ventasPorItem[$itemKey]) ? (int) $ventasPorItem[$itemKey] : 0;
            $cantRechazada = max(0, $cantPreventa - $cantFacturada);

            $montoRechazado = round($cantRechazada * $precioUnit, 2);
            $montoFacturado = round($cantFacturada * $precioUnit, 2);

            $preventasTemp[$nro]['monto_total_preventa'] += $montoItemPreventa;
            $preventasTemp[$nro]['monto_total_facturado'] += $montoFacturado;
            $preventasTemp[$nro]['monto_total_rechazado'] += $montoRechazado;
            $preventasTemp[$nro]['total_items_preventa'] += $cantPreventa;
            $preventasTemp[$nro]['total_items_facturados'] += $cantFacturada;
            $preventasTemp[$nro]['total_items_rechazados'] += $cantRechazada;

            $itemData = [
                'preventa_item_id' => $item->preventa_item_id,
                'producto_id' => $item->producto_id,
                'codigo_producto' => $item->codigo_producto,
                'producto_nombre' => $item->producto_nombre,
                'categoria' => $item->categoria,
                'cantidad_preventa' => $cantPreventa,
                'cantidad_facturada' => $cantFacturada,
                'cantidad_rechazada' => $cantRechazada,
                'precio_unitario' => round($precioUnit, 2),
                'monto_preventa' => round($montoItemPreventa, 2),
                'monto_facturado' => round($montoFacturado, 2),
                'monto_rechazado' => round($montoRechazado, 2),
                'es_parcial' => ($cantFacturada > 0 && $cantRechazada > 0),
                'es_totalmente_rechazado' => ($cantFacturada == 0 && $cantRechazada > 0),
            ];

            $preventasTemp[$nro]['items'][] = $itemData;

            if ($cantRechazada > 0) {
                $preventasTemp[$nro]['items_rechazados'][] = $itemData;
            }
        }

        // 6. Filtrar solo preventas que tengan AL MENOS un ítem con cantidad rechazada > 0
        $preventasPendientes = [];
        foreach ($preventasTemp as $nro => $prev) {
            if ($prev['total_items_rechazados'] > 0) {
                $prev['tipo_sugerido'] = ($prev['total_items_facturados'] > 0) ? 'PARCIAL' : 'TOTAL';
                $prev['monto_total_preventa'] = round($prev['monto_total_preventa'], 2);
                $prev['monto_total_facturado'] = round($prev['monto_total_facturado'], 2);
                $prev['monto_total_rechazado'] = round($prev['monto_total_rechazado'], 2);

                $preventasPendientes[] = $prev;
            }
        }

        return $preventasPendientes;
    }
}

