<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventasPendientesAction
{
    /**
     * Calcula y devuelve las preventas tomadas pendientes de justificar frente a las ventas facturadas (entregas).
     *
     * Reglas del módulo dinámico:
     * 1. Elimina la restricción estática de facturación al día siguiente (X+1). Si existe en fact_ventas
     *    en cualquier fecha posterior (X+1, X+2, X+3...), se reconoce como entregada.
     * 2. Ventana dinámica: en lugar de evaluar únicamente el día D-2, evalúa un rango móvil de días
     *    (por defecto últimos 7 días hasta ayer D-1), permitiendo visibilidad completa.
     * 3. Excluye preventas completamente entregadas y preventas ya justificadas en pedidos_rechazados.
     * 4. Detecta rechazos parciales (cuando se facturó menos de lo pedido) y totales (facturado = 0).
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(
        User $user,
        ?string $fechaPreventaParam = null,
        ?string $fechaVentaParam = null,
        int|string|null $clienteId = null,
        ?string $ruta = null,
        int|string|null $diasParam = null,
        ?string $fechaDesdeParam = null,
        ?string $fechaHastaParam = null,
        bool $incluirHoy = false
    ): array {
        $now = Carbon::now('America/La_Paz');

        // 1. Determinar el rango de fechas de preventa a evaluar según el ciclo operativo comercial:
        // Reglas clave del ciclo:
        // - Preventa Viernes -> Entrega Sábado.
        // - Domingo NO se opera.
        // - Preventa Sábado -> Entrega Lunes.
        // - Por tanto, en Lunes, las entregas concluidas son las del Sábado (Preventas del Viernes).
        if (!empty($fechaPreventaParam)) {
            $parsed = Carbon::parse($fechaPreventaParam);
            // Si el cliente pide fecha de Domingo (ej. calculó 'ayer' en lunes),
            // en domingo no hubo preventas: resolver al Viernes operativo previo (D-2 de domingo)
            if ($parsed->isSunday()) {
                $parsed = $parsed->subDays(2);
            }
            $fechaInicio = $parsed->toDateString();
            $fechaFin = $parsed->toDateString();
        } elseif (!empty($fechaVentaParam)) {
            // Si el cliente envía fecha_entrega / fecha_venta (facturación):
            // Sábado o Domingo de entrega -> Preventa del Viernes
            // Lunes de entrega -> Preventa del Sábado
            $parsedVenta = Carbon::parse($fechaVentaParam);
            if ($parsedVenta->isSunday()) {
                $fechaPrev = $parsedVenta->subDays(2)->toDateString(); // Viernes
            } elseif ($parsedVenta->isMonday()) {
                $fechaPrev = $parsedVenta->subDays(2)->toDateString(); // Sábado
            } else {
                $fechaPrev = $parsedVenta->subDay()->toDateString();
            }
            $fechaInicio = $fechaPrev;
            $fechaFin = $fechaPrev;
        } elseif (!empty($fechaDesdeParam)) {
            // Filtro por rango explícito
            $fechaInicio = $fechaDesdeParam;
            $fechaFin = !empty($fechaHastaParam) ? $fechaHastaParam : ($incluirHoy ? $now->toDateString() : $now->copy()->subDay()->toDateString());
        } else {
            // Ventana móvil por defecto (últimos 7 días)
            // Si hoy es Lunes y no se fuerza incluirHoy, la fecha de preventa más reciente
            // cuyo reparto concluyó es el Viernes (D-3). Las del Sábado (D-2) se reparten hoy Lunes.
            $dias = is_numeric($diasParam) ? max(1, min(90, (int) $diasParam)) : 7;
            if ($now->isMonday() && !$incluirHoy) {
                $fechaFin = $now->copy()->subDays(3)->toDateString(); // Viernes previo
            } elseif ($now->isSunday()) {
                $fechaFin = $now->copy()->subDays(2)->toDateString(); // Viernes previo
            } else {
                $fechaFin = $incluirHoy ? $now->toDateString() : $now->copy()->subDay()->toDateString();
            }
            $fechaInicio = Carbon::parse($fechaFin)->subDays($dias - 1)->toDateString();
        }

        try {
            // 2. Preventas ya justificadas por el vendedor en la BD local (pedidos_rechazados)
            $preventasYaJustificadas = PedidoRechazado::query()
                ->whereNotNull('nro_preventa')
                ->where(function ($q) use ($user): void {
                    $q->where('user_id', $user->id)
                      ->orWhere('vendedor_username', $user->username);
                })
                ->pluck('nro_preventa')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->toArray();

            // 3. Consultar fact_preventas en supervisor DB
            $query = DB::connection('supervisor')->table('fact_preventas as p');

            if (!empty($ruta)) {
                $query->where(DB::raw('TRIM(UPPER(p.ruta))'), trim(strtoupper($ruta)));
            } else {
                $query->where(function ($q) use ($user): void {
                    $hasCondition = false;
                    $username = trim((string) ($user->username ?? ''));
                    $userRuta = trim((string) ($user->ruta ?? ''));
                    $userName = trim((string) ($user->name ?? ''));

                    if (!empty($userName)) {
                        $q->where(DB::raw('TRIM(UPPER(p.vendedor))'), strtoupper($userName));
                        $hasCondition = true;
                    }
                    if (!empty($username)) {
                        $upperUser = strtoupper($username);
                        $q->orWhere(DB::raw('TRIM(UPPER(p.username_vendedor))'), $upperUser)
                          ->orWhere(DB::raw('TRIM(UPPER(p.ruta))'), $upperUser)
                          ->orWhere(DB::raw('TRIM(UPPER(p.vendedor))'), $upperUser);
                        $hasCondition = true;
                    }
                    if (!empty($userRuta)) {
                        $upperRuta = strtoupper($userRuta);
                        $q->orWhere(DB::raw('TRIM(UPPER(p.ruta))'), $upperRuta);
                        $hasCondition = true;
                    }
                    if (!$hasCondition) {
                        $q->whereRaw('1 = 0');
                    }
                });
            }

            if ($fechaInicio === $fechaFin) {
                $query->where('p.fecha_norm', $fechaInicio);
            } else {
                $query->whereBetween('p.fecha_norm', [$fechaInicio, $fechaFin]);
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
            ])
            ->where('p.monto_final', '>', 0)
            ->where('p.cantidad', '>', 0)
            ->orderByDesc('p.fecha_norm')
            ->orderBy('p.nro_preventa')
            ->get();

            if ($items->isEmpty()) {
                return [];
            }

            // 4. Obtener cantidades facturadas en fact_ventas (entregas)
            // Se elimina la restricción estricta de fecha_norm = fecha_venta (X+1).
            // Si la preventa se facturó en X+1, X+2, X+3 o posterior, se reconoce como entregada.
            $nrosPreventa = $items->pluck('nro_preventa')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->toArray();

            $ventasPorItem = [];

            if (!empty($nrosPreventa)) {
                $ventasQuery = DB::connection('supervisor')
                    ->table('fact_ventas')
                    ->where(function ($q): void {
                        $q->whereNull('revertida')
                          ->orWhere(function ($sub): void {
                              $sub->where('revertida', '!=', 'Si')
                                  ->where('revertida', '!=', 'SI')
                                  ->where('revertida', '!=', '1');
                          });
                    })
                    ->whereIn('pre_venta_id', $nrosPreventa)
                    ->where('monto_final', '>', 0)
                    ->where('cantidad', '>', 0);

                // Si excepcionalmente se pasó fechaVentaParam fija, se respeta; de lo contrario se evalúa toda factura válida
                if (!empty($fechaVentaParam)) {
                    $ventasQuery->where('fecha_norm', $fechaVentaParam);
                }

                $ventasFacturadas = $ventasQuery->select([
                    'pre_venta_id',
                    'producto_id',
                    DB::raw('SUM(cantidad) as total_facturado'),
                    DB::raw('SUM(monto_final) as monto_facturado'),
                    DB::raw('MAX(fecha_norm) as fecha_facturada'),
                    DB::raw('MAX(nro_factura) as nro_factura'),
                ])
                ->groupBy('pre_venta_id', 'producto_id')
                ->get();

                foreach ($ventasFacturadas as $v) {
                    $key = trim((string) $v->pre_venta_id) . '_' . trim((string) $v->producto_id);
                    $ventasPorItem[$key] = [
                        'total_facturado' => (int) $v->total_facturado,
                        'monto_facturado' => (float) $v->monto_facturado,
                        'fecha_facturada' => $v->fecha_facturada ? (string) $v->fecha_facturada : null,
                        'nro_factura' => $v->nro_factura ? (string) $v->nro_factura : null,
                    ];
                }
            }

            // 5. Agrupar ítems por nro_preventa y calcular rechazos totales vs parciales
            $preventasTemp = [];
            foreach ($items as $item) {
                $nro = (string) ($item->nro_preventa ?: $item->preventa_item_id);

                if (!isset($preventasTemp[$nro])) {
                    $fechaPrev = (string) $item->fecha_preventa;
                    $diasTranscurridos = (int) Carbon::parse($fechaPrev)->diffInDays($now, false);

                    $preventasTemp[$nro] = [
                        'nro_preventa' => (int) ($item->nro_preventa ?: 0),
                        'pre_venta_id' => (int) ($item->nro_preventa ?: 0),
                        'fecha_preventa' => $fechaPrev,
                        'fecha' => $fechaPrev,
                        'dias_transcurridos' => max(0, $diasTranscurridos),
                        'cliente_id' => (int) $item->cliente_id,
                        'codigo_cliente' => $item->codigo_cliente ?: (string) $item->cliente_id,
                        'cliente_nombre' => $item->cliente_nombre,
                        'cliente' => $item->cliente_nombre,
                        'ruta' => $item->ruta,
                        'vendedor' => $item->vendedor,
                        'tipo_sugerido' => 'TOTAL',
                        'monto_total_preventa' => 0.0,
                        'monto_total_facturado' => 0.0,
                        'monto_total_rechazado' => 0.0,
                        'total_items_preventa' => 0,
                        'total_items_facturados' => 0,
                        'total_items_rechazados' => 0,
                        'fecha_facturada' => null,
                        'nro_factura' => null,
                        'items_rechazados' => [],
                        'items' => [],
                    ];
                }

                $cantPreventa = (int) $item->cantidad_preventa;
                $montoItemPreventa = (float) $item->monto_final;
                $precioUnit = ($cantPreventa > 0 && $montoItemPreventa > 0) ? ($montoItemPreventa / $cantPreventa) : 0.0;

                $itemKey = trim((string) $item->nro_preventa) . '_' . trim((string) $item->producto_id);
                $facturadoInfo = $ventasPorItem[$itemKey] ?? null;

                $cantFacturada = $facturadoInfo ? (int) $facturadoInfo['total_facturado'] : 0;
                $cantRechazada = max(0, $cantPreventa - $cantFacturada);

                $montoRechazado = round($cantRechazada * $precioUnit, 2);
                $montoFacturado = round(min($cantFacturada, $cantPreventa) * $precioUnit, 2);

                if ($facturadoInfo && !empty($facturadoInfo['fecha_facturada'])) {
                    $preventasTemp[$nro]['fecha_facturada'] = $facturadoInfo['fecha_facturada'];
                }
                if ($facturadoInfo && !empty($facturadoInfo['nro_factura'])) {
                    $preventasTemp[$nro]['nro_factura'] = $facturadoInfo['nro_factura'];
                }

                $preventasTemp[$nro]['monto_total_preventa'] += $montoItemPreventa;
                $preventasTemp[$nro]['monto_total_facturado'] += $montoFacturado;
                $preventasTemp[$nro]['monto_total_rechazado'] += $montoRechazado;
                $preventasTemp[$nro]['total_items_preventa'] += $cantPreventa;
                $preventasTemp[$nro]['total_items_facturados'] += $cantFacturada;
                $preventasTemp[$nro]['total_items_rechazados'] += $cantRechazada;

                $itemData = [
                    'preventa_item_id' => $item->preventa_item_id,
                    'producto_id' => (int) $item->producto_id,
                    'codigo_producto' => $item->codigo_producto,
                    'producto_nombre' => $item->producto_nombre,
                    'producto' => $item->producto_nombre,
                    'categoria' => $item->categoria,
                    'cantidad_preventa' => $cantPreventa,
                    'cantidad_facturada' => $cantFacturada,
                    'cantidad_rechazada' => $cantRechazada,
                    'precio_unitario' => round($precioUnit, 2),
                    'monto_preventa' => round($montoItemPreventa, 2),
                    'monto_facturado' => round($montoFacturado, 2),
                    'monto_rechazado' => round($montoRechazado, 2),
                    'es_parcial' => ($cantFacturada > 0 && $cantRechazada > 0),
                    'es_totalmente_rechazado' => ($cantFacturada === 0 && $cantRechazada > 0),
                    'fecha_facturada' => $facturadoInfo['fecha_facturada'] ?? null,
                    'nro_factura' => $facturadoInfo['nro_factura'] ?? null,
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
