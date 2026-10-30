<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceEntregasDetalleRutaAction
{
    private const CACHE_TTL_SECS = 300;

    /**
     * Obtiene el detalle completo de preventas y facturación de una ruta en un rango de fechas.
     */
    public function execute(string $ruta, string $fechaInicio, string $fechaCorte): array
    {
        $cacheKey = "avance_entregas_hist_ruta_" . md5("{$ruta}_{$fechaInicio}_{$fechaCorte}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($ruta, $fechaInicio, $fechaCorte) {
            return $this->procesarDetalleRuta($ruta, $fechaInicio, $fechaCorte);
        });
    }

    private function procesarDetalleRuta(string $ruta, string $fechaInicio, string $fechaCorte): array
    {
        $query = "
            SELECT 
                TRIM(UPPER(COALESCE(fp.ruta, 'SIN RUTA'))) AS ruta,
                MAX(COALESCE(dr.canal, 'GENERAL')) AS canal,
                MAX(COALESCE(NULLIF(TRIM(dr.vendedor), ''), NULLIF(TRIM(fp.vendedor), ''), 'No Asignado')) AS vendedor,
                
                -- Preventa
                fp.nro_preventa,
                CAST(fp.fecha_norm AS CHAR) AS fecha_preventa,
                fp.cliente_id,
                MAX(COALESCE(NULLIF(TRIM(fp.cliente), ''), CONCAT('Cliente ', fp.cliente_id))) AS cliente,
                MAX(COALESCE(fp.codigo_cliente, '')) AS codigo_cliente,
                
                -- Ítems Preventa
                fp.producto_id,
                MAX(COALESCE(fp.codigo_producto, '')) AS codigo_producto,
                MAX(TRIM(fp.producto)) AS producto,
                MAX(COALESCE(fp.categoria, 'GENERAL')) AS categoria,
                SUM(fp.cantidad) AS cantidad_preventa,
                ROUND(SUM(fp.monto_final), 2) AS monto_preventa,
                
                -- Ventas en el rango (pre-agregadas)
                COALESCE(MAX(fv_agregada.cantidad_facturada), 0) AS cantidad_facturada,
                ROUND(COALESCE(MAX(fv_agregada.monto_facturado), 0.0), 2) AS monto_facturado,
                CAST(MAX(fv_agregada.fecha_facturada) AS CHAR) AS fecha_facturada,
                MAX(fv_agregada.nro_factura) AS nro_factura,
                MAX(fv_agregada.venta_id) AS venta_id
            FROM fact_preventas fp
            LEFT JOIN dim_rutas dr ON TRIM(UPPER(fp.ruta)) = TRIM(UPPER(dr.ruta))
            LEFT JOIN (
                SELECT 
                    pre_venta_id,
                    producto_id,
                    SUM(cantidad) AS cantidad_facturada,
                    SUM(monto_final) AS monto_facturado,
                    MAX(fecha_norm) AS fecha_facturada,
                    MAX(nro_factura) AS nro_factura,
                    MAX(venta_id) AS venta_id
                FROM fact_ventas
                WHERE fecha_norm >= ? AND fecha_norm <= ?
                  AND monto_final > 0
                  AND cantidad > 0
                  AND (revertida IS NULL OR LOWER(TRIM(revertida)) != 'si')
                GROUP BY pre_venta_id, producto_id
            ) fv_agregada ON fp.nro_preventa = fv_agregada.pre_venta_id AND fp.producto_id = fv_agregada.producto_id
            WHERE fp.fecha_norm >= ? AND fp.fecha_norm <= ?
              AND TRIM(UPPER(fp.ruta)) = TRIM(UPPER(?))
              AND fp.monto_final > 0
              AND fp.cantidad > 0
            GROUP BY 
                fp.ruta,
                fp.nro_preventa,
                fp.fecha_norm,
                fp.cliente_id,
                fp.producto_id
            ORDER BY 
                fp.fecha_norm DESC,
                fp.nro_preventa ASC,
                monto_preventa DESC
        ";

        $filas = DB::connection('supervisor')->select($query, [
            $fechaInicio, $fechaCorte,
            $fechaInicio, $fechaCorte,
            trim($ruta),
        ]);

        $pedidosDict = [];

        foreach ($filas as $row) {
            $nroPrev = (int) $row->nro_preventa;

            if (!isset($pedidosDict[$nroPrev])) {
                $pedidosDict[$nroPrev] = [
                    'ruta' => (string) $row->ruta,
                    'canal' => (string) $row->canal,
                    'vendedor' => (string) $row->vendedor,
                    'nro_preventa' => $nroPrev,
                    'fecha_preventa' => (string) $row->fecha_preventa,
                    'cliente_id' => $row->cliente_id ? (int) $row->cliente_id : null,
                    'cliente' => (string) $row->cliente,
                    'codigo_cliente' => (string) $row->codigo_cliente,
                    'nro_factura' => $row->nro_factura ? (string) $row->nro_factura : null,
                    'items' => [],
                    'total_unidades_prev' => 0,
                    'total_unidades_fact' => 0,
                    'total_bs_prev' => 0.0,
                    'total_bs_fact' => 0.0,
                ];
            }

            $cantP = (int) ($row->cantidad_preventa ?? 0);
            $cantF = (int) ($row->cantidad_facturada ?? 0);
            $montoP = (float) ($row->monto_preventa ?? 0.0);
            $montoF = (float) ($row->monto_facturado ?? 0.0);
            $difMonto = round($montoF - $montoP, 2);

            if ($cantF === 0) {
                $estatusItem = 'NO_ENTREGADO';
            } elseif ($cantF < $cantP) {
                $estatusItem = 'ENTREGA_PARCIAL';
            } else {
                $estatusItem = abs($difMonto) > 0.01 ? 'DIFERENCIA_PRECIO' : 'ENTREGADO_COMPLETO';
            }

            $itemDto = [
                'producto_id' => $row->producto_id ? (int) $row->producto_id : null,
                'codigo_producto' => (string) $row->codigo_producto,
                'producto' => (string) $row->producto,
                'categoria' => (string) $row->categoria,
                'cantidad_preventa' => $cantP,
                'cantidad_facturada' => $cantF,
                'diferencia_unidades' => $cantF - $cantP,
                'monto_preventa' => $montoP,
                'monto_facturado' => $montoF,
                'diferencia_bs' => $difMonto,
                'estatus' => $estatusItem,
            ];

            $pedido = &$pedidosDict[$nroPrev];
            $pedido['items'][] = $itemDto;
            $pedido['total_unidades_prev'] += $cantP;
            $pedido['total_unidades_fact'] += $cantF;
            $pedido['total_bs_prev'] = round($pedido['total_bs_prev'] + $montoP, 2);
            $pedido['total_bs_fact'] = round($pedido['total_bs_fact'] + $montoF, 2);
            unset($pedido);
        }

        $justificativosMap = $this->obtenerJustificativosRechazos(array_keys($pedidosDict));

        $pedidosLista = [];
        $totPedidos = count($pedidosDict);
        $totEntregados = 0;
        $totParciales = 0;
        $totCaidos = 0;
        $totPrevBs = 0.0;
        $totFactBs = 0.0;

        foreach ($pedidosDict as $nroPrev => $p) {
            $totItems = count($p['items']);
            $itemsCompletos = count(array_filter($p['items'], fn ($it) => in_array($it['estatus'], ['ENTREGADO_COMPLETO', 'DIFERENCIA_PRECIO'], true)));
            $itemsCero = count(array_filter($p['items'], fn ($it) => $it['estatus'] === 'NO_ENTREGADO'));

            if ($itemsCompletos === $totItems) {
                $estatusPedido = 'ENTREGADO_TOTAL';
                $totEntregados++;
            } elseif ($itemsCero === $totItems) {
                $estatusPedido = 'CAIDA_TOTAL';
                $totCaidos++;
            } else {
                $estatusPedido = 'PARCIAL';
                $totParciales++;
            }

            $p['estatus_pedido'] = $estatusPedido;
            $p['brecha_bs'] = round($p['total_bs_fact'] - $p['total_bs_prev'], 2);

            $justRaw = $justificativosMap[$nroPrev] ?? null;
            $this->enriquecerPedidoConJustificativo($p, $justRaw);

            $totPrevBs = round($totPrevBs + $p['total_bs_prev'], 2);
            $totFactBs = round($totFactBs + $p['total_bs_fact'], 2);
            $pedidosLista[] = $p;
        }

        $efectividadGlobal = $totPrevBs > 0 ? round(($totFactBs / $totPrevBs) * 100.0, 1) : 0.0;

        return [
            'ruta' => $ruta,
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'totales_generales' => [
                'total_pedidos' => $totPedidos,
                'pedidos_entregados' => $totEntregados,
                'pedidos_parciales' => $totParciales,
                'pedidos_caidos' => $totCaidos,
                'monto_preventa' => $totPrevBs,
                'monto_facturado' => $totFactBs,
                'brecha_bs' => round($totFactBs - $totPrevBs, 2),
                'efectividad_global_pct' => $efectividadGlobal,
            ],
            'pedidos' => $pedidosLista,
        ];
    }

    private function obtenerJustificativosRechazos(array $nrosPreventa): array
    {
        if (empty($nrosPreventa)) {
            return [];
        }

        $resultado = [];

        try {
            $cabeceras = DB::connection('mysql')->table('pedidos_rechazados')
                ->whereIn('nro_preventa', $nrosPreventa)
                ->orderByDesc('id')
                ->get();

            if ($cabeceras->isEmpty()) {
                return [];
            }

            $idsRechazo = [];
            $mapIdToNro = [];

            foreach ($cabeceras as $r) {
                $nroP = (int) $r->nro_preventa;
                if (isset($resultado[$nroP])) {
                    continue;
                }

                $recId = (int) $r->id;
                $idsRechazo[] = $recId;
                $mapIdToNro[$recId] = $nroP;

                $fotoRel = ltrim(str_replace('\\', '/', (string) ($r->photo_path ?? '')), '/');
                $fotoUrl = '';
                if ($fotoRel !== '') {
                    if (str_starts_with($fotoRel, 'http://') || str_starts_with($fotoRel, 'https://')) {
                        $fotoUrl = $fotoRel;
                    } elseif (str_starts_with($fotoRel, 'storage/')) {
                        $fotoUrl = '/' . $fotoRel;
                    } else {
                        $fotoUrl = '/storage/' . $fotoRel;
                    }
                }

                $resultado[$nroP] = [
                    'id' => $recId,
                    'nro_preventa' => $nroP,
                    'motivo' => (string) ($r->motivo ?? 'SIN_MOTIVO'),
                    'tipo_rechazo' => (string) ($r->tipo_rechazo ?? 'TOTAL'),
                    'comentarios' => trim((string) ($r->comentarios ?? '')) ?: null,
                    'fecha_rechazo' => $r->fecha_rechazo ? Carbon::parse($r->fecha_rechazo)->format('Y-m-d H:i') : null,
                    'vendedor_reporta' => (string) ($r->vendedor ?? ''),
                    'photo_path' => $fotoRel ?: null,
                    'photo_url' => $fotoUrl ?: null,
                    'latitude' => $r->latitude !== null ? (float) $r->latitude : null,
                    'longitude' => $r->longitude !== null ? (float) $r->longitude : null,
                    'total_items_rechazados' => (int) ($r->total_items_rechazados ?? 0),
                    'monto_total_rechazado' => (float) ($r->monto_total_rechazado ?? 0.0),
                    'items_por_producto_id' => [],
                    'items_por_codigo' => [],
                    'items_lista' => [],
                ];
            }

            if (!empty($idsRechazo)) {
                $filasItems = DB::connection('mysql')->table('pedidos_rechazados_items')
                    ->whereIn('pedido_rechazado_id', $idsRechazo)
                    ->orderBy('id')
                    ->get();

                foreach ($filasItems as $it) {
                    $recId = (int) $it->pedido_rechazado_id;
                    $nroP = $mapIdToNro[$recId] ?? null;
                    if (!$nroP || !isset($resultado[$nroP])) {
                        continue;
                    }

                    $itemInfo = [
                        'id' => (int) $it->id,
                        'producto_id' => $it->producto_id ? (int) $it->producto_id : null,
                        'codigo_producto' => trim((string) ($it->codigo_producto ?? '')),
                        'producto_nombre' => (string) ($it->producto_nombre ?? ''),
                        'categoria' => (string) ($it->categoria ?? ''),
                        'cantidad_preventa' => (int) ($it->cantidad_preventa ?? 0),
                        'cantidad_rechazada' => (int) ($it->cantidad_rechazada ?? 0),
                        'precio_unitario' => (float) ($it->precio_unitario ?? 0.0),
                        'monto_rechazado' => (float) ($it->monto_rechazado ?? 0.0),
                        'motivo_especifico' => (string) ($it->motivo_especifico ?: $resultado[$nroP]['motivo']),
                    ];

                    $resultado[$nroP]['items_lista'][] = $itemInfo;
                    if ($itemInfo['producto_id'] !== null) {
                        $resultado[$nroP]['items_por_producto_id'][$itemInfo['producto_id']] = $itemInfo;
                    }
                    if ($itemInfo['codigo_producto'] !== '') {
                        $resultado[$nroP]['items_por_codigo'][$itemInfo['codigo_producto']] = $itemInfo;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silencioso
        }

        return $resultado;
    }

    private function enriquecerPedidoConJustificativo(array &$p, ?array $justRaw): void
    {
        if (!$justRaw) {
            $p['justificativo'] = null;
            foreach ($p['items'] as &$it) {
                $it['motivo_rechazo'] = null;
                $it['cantidad_rechazada_rep'] = 0;
                $it['monto_rechazado_rep'] = 0.0;
            }
            return;
        }

        $p['justificativo'] = [
            'id' => $justRaw['id'],
            'motivo' => $justRaw['motivo'],
            'tipo_rechazo' => $justRaw['tipo_rechazo'],
            'comentarios' => $justRaw['comentarios'],
            'fecha_rechazo' => $justRaw['fecha_rechazo'],
            'vendedor_reporta' => $justRaw['vendedor_reporta'],
            'photo_url' => $justRaw['photo_url'],
            'latitude' => $justRaw['latitude'],
            'longitude' => $justRaw['longitude'],
            'total_items_rechazados' => $justRaw['total_items_rechazados'],
            'monto_total_rechazado' => $justRaw['monto_total_rechazado'],
        ];

        $mapProd = $justRaw['items_por_producto_id'] ?? [];
        $mapCod = $justRaw['items_por_codigo'] ?? [];

        foreach ($p['items'] as &$it) {
            $prodId = $it['producto_id'];
            $codProd = $it['codigo_producto'];

            $matchItem = null;
            if ($prodId !== null && isset($mapProd[$prodId])) {
                $matchItem = $mapProd[$prodId];
            } elseif ($codProd !== '' && isset($mapCod[$codProd])) {
                $matchItem = $mapCod[$codProd];
            }

            if ($matchItem) {
                $it['motivo_rechazo'] = $matchItem['motivo_especifico'];
                $it['cantidad_rechazada_rep'] = $matchItem['cantidad_rechazada'];
                $it['monto_rechazado_rep'] = $matchItem['monto_rechazado'];
            } elseif (in_array($it['estatus'], ['NO_ENTREGADO', 'ENTREGA_PARCIAL'], true) && ($justRaw['tipo_rechazo'] ?? '') === 'TOTAL') {
                $it['motivo_rechazo'] = $justRaw['motivo'];
                $it['cantidad_rechazada_rep'] = max(0, (int) $it['cantidad_preventa'] - (int) $it['cantidad_facturada']);
                $it['monto_rechazado_rep'] = round(max(0.0, (float) $it['monto_preventa'] - (float) $it['monto_facturada']), 2);
            } else {
                $it['motivo_rechazo'] = null;
                $it['cantidad_rechazada_rep'] = 0;
                $it['monto_rechazado_rep'] = 0.0;
            }
        }
    }
}

