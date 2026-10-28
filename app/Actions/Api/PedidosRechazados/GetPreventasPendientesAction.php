<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventasPendientesAction
{
    /**
     * Calcula y devuelve las preventas pendientes de justificar frente a las ventas facturadas/reparto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(
        User $user,
        ?string $fechaPreventaParam = null,
        int|string|null $clienteId = null,
        ?string $ruta = null
    ): array {
        $now = now('America/La_Paz');
        $today = $now->toDateString();

        try {
            // 1. Preventas ya justificadas por el vendedor en la BD local
            $preventasYaJustificadas = PedidoRechazado::query()
                ->where('user_id', $user->id)
                ->whereNotNull('nro_preventa')
                ->pluck('nro_preventa')
                ->toArray();

            // 2. Consultar fact_preventas en supervisor DB
            $query = DB::connection('supervisor')->table('fact_preventas as p');

            if (!empty($ruta)) {
                $query->where('p.ruta', $ruta);
            } else {
                $query->where(function ($q) use ($user): void {
                    $hasCondition = false;
                    $username = trim((string) ($user->username ?? ''));
                    $userRuta = trim((string) ($user->ruta ?? ''));
                    $userName = trim((string) ($user->name ?? ''));

                    if (!empty($userName)) {
                        $q->where('p.vendedor', $userName);
                        $hasCondition = true;
                    }
                    if (!empty($username)) {
                        $q->orWhere('p.username_vendedor', $username)
                          ->orWhere('p.ruta', $username);
                        $hasCondition = true;
                    }
                    if (!empty($userRuta)) {
                        $q->orWhere('p.ruta', $userRuta);
                        $hasCondition = true;
                    }
                    if (!$hasCondition) {
                        $q->whereRaw('1 = 0');
                    }
                });
            }

            // 3. Filtro de fecha:
            // Si el cliente envía una fecha explícita (?fecha_preventa=YYYY-MM-DD), respetarla.
            // Si NO envía fecha, consultar preventas recientes pendientes de justificar (últimos 7 días).
            // Si fact_ventas aún no tiene registros de hoy (entregas en curso), se acota hasta ayer para evitar
            // mostrar preventas frescas del día que aún no han salido a reparto.
            if (!empty($fechaPreventaParam)) {
                $query->where('p.fecha_norm', $fechaPreventaParam);
            } else {
                $hasVentasToday = DB::connection('supervisor')
                    ->table('fact_ventas')
                    ->where('fecha_norm', $today)
                    ->exists();

                $hasta = $hasVentasToday ? $today : $now->copy()->subDay()->toDateString();
                $desde = $now->copy()->subDays(7)->toDateString();

                $query->whereBetween('p.fecha_norm', [$desde, $hasta]);
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
                'p.monto_final',
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
                    ->where(function ($q): void {
                        $q->whereNull('revertida')
                          ->orWhere(function ($sub): void {
                              $sub->where('revertida', '!=', 'Si')
                                  ->where('revertida', '!=', 'SI');
                          });
                    })
                    ->whereIn('pre_venta_id', $nrosPreventa)
                    ->select('pre_venta_id', 'producto_id', DB::raw('SUM(cantidad) as total_facturado'))
                    ->groupBy('pre_venta_id', 'producto_id')
                    ->get();

                foreach ($ventasFacturadas as $v) {
                    $key = trim((string) $v->pre_venta_id) . '_' . trim((string) $v->producto_id);
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
                        'items' => [],
                    ];
                }

                $cantPreventa = (int) $item->cantidad_preventa;
                $montoItemPreventa = (float) $item->monto_final;
                $precioUnit = ($cantPreventa > 0 && $montoItemPreventa > 0) ? ($montoItemPreventa / $cantPreventa) : 0.0;

                $itemKey = trim((string) $item->nro_preventa) . '_' . trim((string) $item->producto_id);
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
        } catch (\Throwable $e) {
            Log::error('Error consultando preventas pendientes en supervisor DB: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
            ]);

            return [];
        }
    }
}
