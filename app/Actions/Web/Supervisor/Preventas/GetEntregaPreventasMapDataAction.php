<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetEntregaPreventasMapDataAction
{
    /**
     * Consulta consolidada geográfica y analítica de Preventas y sus Entregas/Facturación.
     *
     * @param string|null $fecha Fecha seleccionada (por defecto la más reciente en fact_preventas)
     * @param array<int, string> $canales Canales a filtrar
     * @param array<int, string> $rutas Rutas a filtrar
     * @param string $estadoFiltro 'TODOS' | 'ENTREGADO' | 'PARCIAL' | 'RECHAZADO' | 'PENDIENTE'
     * @param string $criterioFecha 'preventa' (fecha de toma del pedido) | 'entrega' (fecha de despacho/facturación)
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fecha = null,
        array $canales = [],
        array $rutas = [],
        string $estadoFiltro = 'TODOS',
        string $criterioFecha = 'preventa'
    ): array {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $canalesClean = array_values(array_filter(array_map(fn($c) => strtoupper(trim((string)$c)), $canales)));
        $rutasClean = array_values(array_filter(array_map(fn($r) => strtoupper(trim((string)$r)), $rutas)));

        // 1. Catálogo de Rutas
        $rutasQuery = DB::connection('supervisor')->table('dim_rutas')->select([
            'ruta',
            'canal',
            'vendedor',
            'vendedor_norm'
        ]);

        if (!empty($canalesClean)) {
            $rutasQuery->whereIn(DB::raw("TRIM(UPPER(canal))"), $canalesClean);
        }

        if (!empty($rutasClean)) {
            $rutasQuery->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasClean);
        }

        $catalogoRutas = $rutasQuery->get()->keyBy(fn($r) => strtoupper(trim((string)$r->ruta)));
        $rutasObjetivo = $catalogoRutas->keys()->all();

        // 2. Consulta de Ítems de Preventa
        $preventasQuery = DB::connection('supervisor')->table('fact_preventas as fp')
            ->where('fp.monto_final', '>', 0)
            ->where('fp.cantidad', '>', 0)
            ->where('fp.cliente_id', '>', 0);

        if ($criterioFecha === 'entrega') {
            // Si el criterio es fecha de entrega, las preventas provienen del día hábil anterior (D-1)
            $fechaPreventaEstimada = $this->calcularFechaOperativaPrevia($fechaNorm);
            $preventasQuery->where('fp.fecha_norm', $fechaPreventaEstimada);
        } else {
            // Criterio preventa: fecha en que se tomó el pedido
            $preventasQuery->where('fp.fecha_norm', $fechaNorm);
        }

        if (!empty($rutasObjetivo)) {
            $preventasQuery->whereIn(DB::raw("TRIM(UPPER(fp.ruta))"), $rutasObjetivo);
        }

        $preventasRaw = $preventasQuery->select([
            'fp.id',
            'fp.nro_preventa',
            'fp.fecha_norm as fecha_preventa',
            'fp.cliente_id',
            'fp.codigo_cliente',
            'fp.cliente',
            'fp.almacen',
            'fp.vendedor',
            'fp.username_vendedor',
            'fp.zona',
            'fp.producto_id',
            'fp.codigo_producto',
            'fp.producto',
            'fp.categoria',
            'fp.cantidad as cantidad_preventa',
            'fp.monto_final as monto_preventa',
            'fp.precio_lista',
            'fp.ruta',
            'fp.nro_carga',
        ])->get();

        if ($preventasRaw->isEmpty()) {
            return $this->generarRespuestaVacia($fechaNorm, $criterioFecha);
        }

        $nroPreventas = $preventasRaw->pluck('nro_preventa')->unique()->values()->all();
        $clienteIds = $preventasRaw->pluck('cliente_id')->unique()->values()->all();

        // 3. Coordenadas de Clientes desde pan_ruteo y dim_clientes
        $coordenadasClientes = [];
        try {
            $panCoords = DB::connection('supervisor')->table('pan_ruteo')
                ->whereIn('cliente_id', $clienteIds)
                ->whereNotNull('latitud')
                ->whereRaw("ABS(latitud) > 0.0001")
                ->select([
                    'cliente_id',
                    DB::raw("MAX(latitud) as latitud"),
                    DB::raw("MAX(longitud) as longitud"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
                    DB::raw("MAX(COALESCE(TRIM(territorio), '-')) as territorio"),
                    DB::raw("MAX(COALESCE(TRIM(referencia), '-')) as referencia"),
                    DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), NULLIF(TRIM(cliente_norm), ''))) as cliente_nombre"),
                ])
                ->groupBy('cliente_id')
                ->get();

            foreach ($panCoords as $pc) {
                $coordenadasClientes[(int)$pc->cliente_id] = [
                    'latitud' => (float)$pc->latitud,
                    'longitud' => (float)$pc->longitud,
                    'direccion' => (string)$pc->direccion,
                    'territorio' => (string)$pc->territorio,
                    'referencia' => (string)$pc->referencia,
                    'cliente_oficial' => (string)$pc->cliente_nombre,
                ];
            }

            // Clientes faltantes: buscar en dim_clientes
            $faltantes = array_diff($clienteIds, array_keys($coordenadasClientes));
            if (!empty($faltantes)) {
                $dimCoords = DB::connection('supervisor')->table('dim_clientes')
                    ->whereIn('cliente_id', $faltantes)
                    ->whereNotNull('latitud')
                    ->whereRaw("ABS(latitud) > 0.0001")
                    ->select([
                        'cliente_id',
                        DB::raw("MAX(latitud) as latitud"),
                        DB::raw("MAX(longitud) as longitud"),
                        DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
                        DB::raw("MAX(COALESCE(TRIM(territorio), '-')) as territorio"),
                        DB::raw("MAX(COALESCE(TRIM(razon_social), '')) as cliente_nombre"),
                    ])
                    ->groupBy('cliente_id')
                    ->get();

                foreach ($dimCoords as $dc) {
                    $coordenadasClientes[(int)$dc->cliente_id] = [
                        'latitud' => (float)$dc->latitud,
                        'longitud' => (float)$dc->longitud,
                        'direccion' => (string)$dc->direccion,
                        'territorio' => (string)$dc->territorio,
                        'referencia' => '-',
                        'cliente_oficial' => (string)$dc->cliente_nombre,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[EntregaPreventas] Error consultando coordenadas: ' . $e->getMessage());
        }

        // 4. Facturación / Entregas Efectivas desde fact_ventas
        $ventasPorPreventaYProducto = [];
        $ventasResumenPorPreventa = [];
        try {
            $ventasChunked = array_chunk($nroPreventas, 500);
            foreach ($ventasChunked as $chunk) {
                $ventasRaw = DB::connection('supervisor')->table('fact_ventas')
                    ->whereIn('pre_venta_id', $chunk)
                    ->where('monto_final', '>', 0)
                    ->where('cantidad', '>', 0)
                    ->select([
                        'pre_venta_id',
                        'producto_id',
                        DB::raw("SUM(cantidad) as cantidad_facturada"),
                        DB::raw("SUM(monto_final) as monto_facturado"),
                        DB::raw("MAX(nro_factura) as nro_factura"),
                        DB::raw("MAX(distribuidor) as distribuidor"),
                        DB::raw("MAX(distribuidor_username) as distribuidor_username"),
                        DB::raw("MAX(fecha_norm) as fecha_facturada"),
                        DB::raw("MAX(hora) as hora_facturada"),
                    ])
                    ->groupBy('pre_venta_id', 'producto_id')
                    ->get();

                foreach ($ventasRaw as $vr) {
                    $pvId = (int)$vr->pre_venta_id;
                    $prodId = (int)$vr->producto_id;
                    $ventasPorPreventaYProducto["{$pvId}_{$prodId}"] = [
                        'cantidad' => (int)$vr->cantidad_facturada,
                        'monto' => (float)$vr->monto_facturado,
                        'nro_factura' => (string)($vr->nro_factura ?: '-'),
                        'distribuidor' => (string)($vr->distribuidor ?: '-'),
                        'fecha_facturada' => (string)($vr->fecha_facturada ?: '-'),
                        'hora_facturada' => (string)($vr->hora_facturada ?: '-'),
                    ];

                    if (!isset($ventasResumenPorPreventa[$pvId])) {
                        $ventasResumenPorPreventa[$pvId] = [
                            'cantidad_total' => 0,
                            'monto_total' => 0.0,
                            'nro_factura' => (string)$vr->nro_factura,
                            'distribuidor' => (string)$vr->distribuidor,
                            'fecha_facturada' => (string)$vr->fecha_facturada,
                        ];
                    }
                    $ventasResumenPorPreventa[$pvId]['cantidad_total'] += (int)$vr->cantidad_facturada;
                    $ventasResumenPorPreventa[$pvId]['monto_total'] += (float)$vr->monto_facturado;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[EntregaPreventas] Error consultando fact_ventas: ' . $e->getMessage());
        }

        // 5. Rechazos en App Móvil desde pedidos_rechazados (BD alva / mysql)
        $rechazosPorPreventa = [];
        $rechazosItemsPorPreventaYProd = [];
        try {
            $rechazosChunked = array_chunk($nroPreventas, 500);
            foreach ($rechazosChunked as $chunk) {
                $rechazosRaw = DB::connection('mysql')->table('pedidos_rechazados as pr')
                    ->whereIn('pr.nro_preventa', $chunk)
                    ->select([
                        'pr.id',
                        'pr.nro_preventa',
                        'pr.cliente_id',
                        'pr.motivo',
                        'pr.tipo_rechazo',
                        'pr.comentarios',
                        'pr.photo_path',
                        'pr.latitude',
                        'pr.longitude',
                        'pr.fecha_rechazo',
                        'pr.distribuidor',
                        'pr.total_items_rechazados',
                        'pr.monto_total_rechazado',
                    ])
                    ->get();

                $rechazoIds = $rechazosRaw->pluck('id')->all();

                foreach ($rechazosRaw as $rr) {
                    $pvId = (int)$rr->nro_preventa;
                    $rechazosPorPreventa[$pvId] = [
                        'id' => $rr->id,
                        'motivo' => (string)($rr->motivo ?: 'Sin motivo'),
                        'tipo_rechazo' => (string)($rr->tipo_rechazo ?: 'TOTAL'),
                        'comentarios' => (string)($rr->comentarios ?: ''),
                        'foto_url' => $this->formatearUrlFoto($rr->photo_path),
                        'latitude' => $rr->latitude ? (float)$rr->latitude : null,
                        'longitude' => $rr->longitude ? (float)$rr->longitude : null,
                        'fecha_rechazo' => (string)($rr->fecha_rechazo ?: ''),
                        'distribuidor' => (string)($rr->distribuidor ?: ''),
                        'monto_rechazado' => (float)($rr->monto_total_rechazado ?: 0),
                    ];
                }

                if (!empty($rechazoIds)) {
                    $itemsRechazados = DB::connection('mysql')->table('pedidos_rechazados_items')
                        ->whereIn('pedido_rechazado_id', $rechazoIds)
                        ->select([
                            'pedido_rechazado_id',
                            'producto_id',
                            'cantidad_rechazada',
                            'monto_rechazado',
                            'motivo_especifico',
                        ])
                        ->get();

                    foreach ($itemsRechazados as $ir) {
                        // Mapear por rechazo id a nro_preventa
                        $parentRechazo = $rechazosRaw->firstWhere('id', $ir->pedido_rechazado_id);
                        if ($parentRechazo) {
                            $pvId = (int)$parentRechazo->nro_preventa;
                            $prId = (int)$ir->producto_id;
                            $rechazosItemsPorPreventaYProd["{$pvId}_{$prId}"] = [
                                'cantidad_rechazada' => (int)$ir->cantidad_rechazada,
                                'monto_rechazado' => (float)$ir->monto_rechazado,
                                'motivo_especifico' => (string)($ir->motivo_especifico ?: ''),
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[EntregaPreventas] Error consultando pedidos_rechazados: ' . $e->getMessage());
        }

        // 6. Agrupación y Consolidación por Preventa
        $preventasGrouped = $preventasRaw->groupBy('nro_preventa');
        $preventasConsolidadas = [];

        $totales = [
            'total_preventas' => $preventasGrouped->count(),
            'total_clientes' => count($clienteIds),
            'monto_total_preventa' => 0.0,
            'monto_total_entregado' => 0.0,
            'unidades_total_preventa' => 0,
            'unidades_total_entregado' => 0,
            'efectividad_entrega_pct' => 0.0,
            'conteo_estados' => [
                'entregado' => 0,
                'parcial' => 0,
                'rechazado' => 0,
                'pendiente' => 0,
            ],
            'con_coordenadas' => 0,
            'sin_coordenadas' => 0,
        ];

        foreach ($preventasGrouped as $nroPv => $items) {
            $nroPvInt = (int)$nroPv;
            $first = $items->first();
            $cid = (int)$first->cliente_id;
            $rutaUpper = strtoupper(trim((string)$first->ruta));

            $coords = $coordenadasClientes[$cid] ?? null;
            $hasCoords = ($coords !== null && !empty($coords['latitud']) && !empty($coords['longitud']));

            if ($hasCoords) {
                $totales['con_coordenadas']++;
            } else {
                $totales['sin_coordenadas']++;
            }

            $montoPreventaTotal = (float)$items->sum('monto_preventa');
            $unidadesPreventaTotal = (int)$items->sum('cantidad_preventa');

            $resumenVenta = $ventasResumenPorPreventa[$nroPvInt] ?? null;
            $montoFacturadoTotal = $resumenVenta ? (float)$resumenVenta['monto_total'] : 0.0;
            $unidadesFacturadasTotal = $resumenVenta ? (int)$resumenVenta['cantidad_total'] : 0;

            $rechazo = $rechazosPorPreventa[$nroPvInt] ?? null;

            // Determinar Estado de la Entrega
            $estado = 'PENDIENTE';
            $estadoLabel = 'Pendiente Despacho';
            $color = '#0071E3'; // Azul Apple

            if ($rechazo && strtoupper($rechazo['tipo_rechazo']) === 'TOTAL') {
                $estado = 'RECHAZADO';
                $estadoLabel = 'Rechazado Total';
                $color = '#FF3B30'; // Rojo
            } elseif ($montoFacturadoTotal >= ($montoPreventaTotal * 0.98)) {
                $estado = 'ENTREGADO';
                $estadoLabel = 'Entregado Completo';
                $color = '#34C759'; // Verde
            } elseif ($montoFacturadoTotal > 0) {
                $estado = 'PARCIAL';
                $estadoLabel = 'Entrega Parcial';
                $color = '#FF9500'; // Ámbar / Naranja
            } elseif ($rechazo) {
                $estado = 'RECHAZADO';
                $estadoLabel = 'Rechazado';
                $color = '#FF3B30';
            } else {
                $estado = 'PENDIENTE';
                $estadoLabel = 'Pendiente';
                $color = '#0071E3';
            }

            // Aplicar filtro de estado si corresponde
            if ($estadoFiltro !== 'TODOS' && $estado !== $estadoFiltro) {
                continue;
            }

            // Acumuladores globales
            $totales['monto_total_preventa'] += $montoPreventaTotal;
            $totales['monto_total_entregado'] += $montoFacturadoTotal;
            $totales['unidades_total_preventa'] += $unidadesPreventaTotal;
            $totales['unidades_total_entregado'] += $unidadesFacturadasTotal;

            if ($estado === 'ENTREGADO') $totales['conteo_estados']['entregado']++;
            elseif ($estado === 'PARCIAL') $totales['conteo_estados']['parcial']++;
            elseif ($estado === 'RECHAZADO') $totales['conteo_estados']['rechazado']++;
            elseif ($estado === 'PENDIENTE') $totales['conteo_estados']['pendiente']++;

            // Desglose de Ítems
            $itemsDetalle = [];
            foreach ($items as $it) {
                $prodId = (int)$it->producto_id;
                $k = "{$nroPvInt}_{$prodId}";
                $vItem = $ventasPorPreventaYProducto[$k] ?? null;
                $rItem = $rechazosItemsPorPreventaYProd[$k] ?? null;

                $cantPedida = (int)$it->cantidad_preventa;
                $montoPedido = (float)$it->monto_preventa;

                $cantEntregada = $vItem ? (int)$vItem['cantidad'] : 0;
                $montoEntregado = $vItem ? (float)$vItem['monto'] : 0.0;

                $cantRechazada = $rItem ? (int)$rItem['cantidad_rechazada'] : ($estado === 'RECHAZADO' ? $cantPedida : 0);

                // Estado por ítem
                $estadoItem = 'PENDIENTE';
                if ($cantEntregada >= $cantPedida) {
                    $estadoItem = 'ENTREGADO';
                } elseif ($cantEntregada > 0) {
                    $estadoItem = 'PARCIAL';
                } elseif ($cantRechazada > 0 || $estado === 'RECHAZADO') {
                    $estadoItem = 'RECHAZADO';
                }

                $itemsDetalle[] = [
                    'producto_id' => $prodId,
                    'codigo' => (string)($it->codigo_producto ?: '-'),
                    'producto' => (string)$it->producto,
                    'categoria' => (string)($it->categoria ?: 'General'),
                    'cantidad_preventa' => $cantPedida,
                    'monto_preventa' => round($montoPedido, 2),
                    'cantidad_entregada' => $cantEntregada,
                    'monto_entregado' => round($montoEntregado, 2),
                    'diferencia_cantidad' => max(0, $cantPedida - $cantEntregada),
                    'diferencia_monto' => round(max(0, $montoPedido - $montoEntregado), 2),
                    'estado_item' => $estadoItem,
                    'motivo_rechazo' => $rItem['motivo_especifico'] ?? ($rechazo['motivo'] ?? null),
                    'nro_factura' => $vItem['nro_factura'] ?? null,
                ];
            }

            // Ordenar ítems: primero con faltantes / rechazados, luego entregados
            usort($itemsDetalle, fn($a, $b) => strcmp($a['estado_item'], $b['estado_item']));

            $pctEntrega = $montoPreventaTotal > 0
                ? round(($montoFacturadoTotal / $montoPreventaTotal) * 100, 1)
                : 0.0;

            $dimRuta = $catalogoRutas[$rutaUpper] ?? null;

            $preventasConsolidadas[] = [
                'nro_preventa' => $nroPvInt,
                'fecha_preventa' => (string)$first->fecha_preventa,
                'fecha_entrega' => $resumenVenta['fecha_facturada'] ?? ($rechazo['fecha_rechazo'] ?? null),
                'cliente_id' => $cid,
                'codigo_cliente' => (string)($first->codigo_cliente ?: $cid),
                'cliente' => $coords['cliente_oficial'] ?? (string)$first->cliente,
                'ruta' => $rutaUpper,
                'canal' => $dimRuta ? (string)$dimRuta->canal : '-',
                'vendedor' => $dimRuta ? (string)$dimRuta->vendedor : ((string)$first->vendedor ?: 'No Asignado'),
                'distribuidor' => $resumenVenta['distribuidor'] ?? ($rechazo['distribuidor'] ?? 'En Reparto'),
                'nro_factura' => $resumenVenta['nro_factura'] ?? null,
                'almacen' => (string)($first->almacen ?: 'CENTRAL'),
                'zona' => (string)($first->zona ?: '-'),
                'direccion' => $coords['direccion'] ?? '-',
                'territorio' => $coords['territorio'] ?? '-',
                'referencia' => $coords['referencia'] ?? '-',
                'latitud' => $hasCoords ? $coords['latitud'] : null,
                'longitud' => $hasCoords ? $coords['longitud'] : null,
                'tiene_coords' => $hasCoords,
                'estado' => $estado,
                'estado_label' => $estadoLabel,
                'color' => $color,
                'total_items' => count($itemsDetalle),
                'unidades_preventa' => $unidadesPreventaTotal,
                'monto_preventa' => round($montoPreventaTotal, 2),
                'unidades_entregadas' => $unidadesFacturadasTotal,
                'monto_entregado' => round($montoFacturadoTotal, 2),
                'porcentaje_entrega' => min(100.0, $pctEntrega),
                'rechazo' => $rechazo,
                'items' => $itemsDetalle,
            ];
        }

        // Ordenar: primero las que tienen coordenadas, luego por monto descendente
        usort($preventasConsolidadas, function ($a, $b) {
            if ($a['tiene_coords'] !== $b['tiene_coords']) {
                return $a['tiene_coords'] ? -1 : 1;
            }
            return $b['monto_preventa'] <=> $a['monto_preventa'];
        });

        // Totales calculados
        $totales['monto_total_preventa'] = round($totales['monto_total_preventa'], 2);
        $totales['monto_total_entregado'] = round($totales['monto_total_entregado'], 2);
        $totales['efectividad_entrega_pct'] = $totales['monto_total_preventa'] > 0
            ? round(($totales['monto_total_entregado'] / $totales['monto_total_preventa']) * 100, 1)
            : 0.0;

        return [
            'fecha' => $fechaNorm,
            'criterio_fecha' => $criterioFecha,
            'estado_filtro' => $estadoFiltro,
            'totales' => $totales,
            'preventas' => $preventasConsolidadas,
        ];
    }

    private function calcularFechaOperativaPrevia(string $fecha): string
    {
        $c = Carbon::parse($fecha);
        // Si la entrega es un lunes (1), la preventa operativa previa fue el sábado anterior (-2 días)
        if ($c->dayOfWeekIso === 1) {
            return $c->subDays(2)->format('Y-m-d');
        }
        // Día previo estándar
        return $c->subDay()->format('Y-m-d');
    }

    private function formatearUrlFoto(?string $path): ?string
    {
        if (!$path) return null;
        $clean = ltrim(str_replace('\\', '/', $path), '/');
        if ($clean === '') return null;
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $clean;
        }
        if (str_starts_with($clean, 'storage/')) {
            return '/' . $clean;
        }
        return '/storage/' . $clean;
    }

    private function generarRespuestaVacia(string $fecha, string $criterioFecha): array
    {
        return [
            'fecha' => $fecha,
            'criterio_fecha' => $criterioFecha,
            'estado_filtro' => 'TODOS',
            'totales' => [
                'total_preventas' => 0,
                'total_clientes' => 0,
                'monto_total_preventa' => 0.0,
                'monto_total_entregado' => 0.0,
                'unidades_total_preventa' => 0,
                'unidades_total_entregado' => 0,
                'efectividad_entrega_pct' => 0.0,
                'conteo_estados' => [
                    'entregado' => 0,
                    'parcial' => 0,
                    'rechazado' => 0,
                    'pendiente' => 0,
                ],
                'con_coordenadas' => 0,
                'sin_coordenadas' => 0,
            ],
            'preventas' => [],
        ];
    }
}
