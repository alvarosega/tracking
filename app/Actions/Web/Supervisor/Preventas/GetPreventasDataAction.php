<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\PedidoRechazado;
use App\Models\Supervisor\FactPreventa;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\Ruta;

class GetPreventasDataAction
{
    /**
     * Consulta y agrupa las preventas del día por cliente, calculando métricas geográficas,
     * KPIs y cruzando en tiempo real con los rechazos registrados en campo.
     *
     * @param string $fecha YYYY-MM-DD
     * @param array<string> $canales
     * @param array<string> $rutas
     * @param float|null $lat
     * @param float|null $lng
     * @param int $radio Metros
     * @return array{clientes: array, kpis: array}
     */
    public function execute(
        string $fecha,
        array $canales = [],
        array $rutas = [],
        ?float $lat = null,
        ?float $lng = null,
        int $radio = 300
    ): array {
        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        // Consulta de ítems de preventa para la fecha solicitada
        $query = FactPreventa::query()->where('fecha_norm', $fecha);

        if (!empty($rutas)) {
            $query->whereIn('ruta', $rutas);
        }

        $preventasRaw = $query->select([
            'id', 'nro_preventa', 'cliente_id', 'codigo_cliente', 'cliente',
            'vendedor', 'zona', 'producto_id', 'codigo_producto', 'producto',
            'categoria', 'cantidad', 'monto_final', 'ruta', 'dia_visita',
            'facturar', 'nro_carga'
        ])->get();

        if ($preventasRaw->isEmpty()) {
            return [
                'clientes' => [],
                'kpis' => [
                    'total_preventas' => 0,
                    'total_clientes' => 0,
                    'monto_total' => 0.0,
                    'total_rechazados' => 0,
                    'monto_total_rechazado' => 0.0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                    'en_radio_conteo' => 0,
                    'en_radio_monto' => 0.0,
                ],
            ];
        }

        // Coordenadas y datos maestros desde pan_ruteo
        $clienteIds = $preventasRaw->pluck('cliente_id')->filter()->unique()->toArray();
        $clientesGeo = PanRuteo::query()
            ->whereIn('cliente_id', $clienteIds)
            ->select(['cliente_id', 'latitud', 'longitud', 'direccion', 'contacto', 'celular', 'tipo_negocio', 'estado'])
            ->get()
            ->keyBy('cliente_id');

        // Cruzar con rechazos registrados en MySQL local
        $nrosPreventaUnicos = $preventasRaw->pluck('nro_preventa')->filter()->unique()->toArray();
        $rechazosPorPreventa = PedidoRechazado::with('items')
            ->whereIn('nro_preventa', $nrosPreventaUnicos)
            ->get()
            ->keyBy('nro_preventa');

        // Agrupar jerárquicamente: Cliente -> nro_preventa -> productos
        $clientesAgrupados = [];
        $totalGeneralMonto = 0.0;
        $totalGeneralRechazado = 0.0;
        $conteoTotalRechazos = 0;

        foreach ($preventasRaw as $item) {
            $cId = $item->cliente_id;
            $nroPrev = $item->nro_preventa;
            $totalGeneralMonto += (float) $item->monto_final;

            if (!isset($clientesAgrupados[$cId])) {
                $geo = $clientesGeo->get($cId);
                $hasGps = ($geo && !empty($geo->latitud) && !empty($geo->longitud) && (float) $geo->latitud != 0.0);

                $distancia = null;
                if ($hasGps && $lat !== null && $lng !== null) {
                    $distancia = (int) round(6371000 * 2 * asin(sqrt(
                        pow(sin(deg2rad($geo->latitud - $lat) / 2), 2) +
                        cos(deg2rad($lat)) * cos(deg2rad($geo->latitud)) *
                        pow(sin(deg2rad($geo->longitud - $lng) / 2), 2)
                    )));
                }

                $clientesAgrupados[$cId] = [
                    'cliente_id' => $cId,
                    'codigo_cliente' => $item->codigo_cliente,
                    'cliente' => $item->cliente,
                    'zona' => $item->zona,
                    'ruta' => $item->ruta,
                    'vendedor' => $item->vendedor,
                    'direccion' => $geo ? $geo->direccion : 'Sin dirección',
                    'contacto' => $geo ? $geo->contacto : null,
                    'celular' => $geo ? $geo->celular : null,
                    'tipo_negocio' => $geo ? $geo->tipo_negocio : 'General',
                    'estado' => $geo ? ($geo->estado ?? 'Activo') : 'Activo',
                    'latitud' => $hasGps ? (float) $geo->latitud : null,
                    'longitud' => $hasGps ? (float) $geo->longitud : null,
                    'tiene_gps' => $hasGps,
                    'distancia_metros' => $distancia,
                    'en_radio' => ($distancia !== null && $distancia <= $radio),
                    'total_monto' => 0.0,
                    'total_monto_rechazado' => 0.0,
                    'tiene_rechazos' => false,
                    'pedidos' => [],
                ];
            }

            $clientesAgrupados[$cId]['total_monto'] += (float) $item->monto_final;

            if (!isset($clientesAgrupados[$cId]['pedidos'][$nroPrev])) {
                $rechazo = $rechazosPorPreventa->get($nroPrev);
                $rechazoData = null;

                if ($rechazo) {
                    $rechazoMonto = (float) $rechazo->monto_total_rechazado;
                    $clientesAgrupados[$cId]['tiene_rechazos'] = true;
                    $clientesAgrupados[$cId]['total_monto_rechazado'] += $rechazoMonto;
                    $totalGeneralRechazado += $rechazoMonto;
                    $conteoTotalRechazos++;

                    $rechazoData = [
                        'id' => $rechazo->id,
                        'uuid' => $rechazo->uuid,
                        'motivo' => $rechazo->motivo,
                        'tipo_rechazo' => $rechazo->tipo_rechazo,
                        'comentarios' => $rechazo->comentarios,
                        'photo_url' => $rechazo->photo_url,
                        'fecha_rechazo' => $rechazo->fecha_rechazo ? $rechazo->fecha_rechazo->format('d/m/Y H:i') : null,
                        'total_items_rechazados' => (int) $rechazo->total_items_rechazados,
                        'monto_total_rechazado' => round($rechazoMonto, 2),
                        'items' => $rechazo->items->map(fn($it) => [
                            'codigo_producto' => $it->codigo_producto,
                            'producto_nombre' => $it->producto_nombre,
                            'cantidad_preventa' => $it->cantidad_preventa,
                            'cantidad_rechazada' => $it->cantidad_rechazada,
                            'precio_unitario' => (float) $it->precio_unitario,
                            'monto_rechazado' => (float) $it->monto_rechazado,
                            'motivo_especifico' => $it->motivo_especifico,
                        ])->toArray(),
                    ];
                }

                $clientesAgrupados[$cId]['pedidos'][$nroPrev] = [
                    'nro_preventa' => $nroPrev,
                    'facturar' => $item->facturar,
                    'nro_carga' => $item->nro_carga,
                    'monto_pedido' => 0.0,
                    'rechazo' => $rechazoData,
                    'items' => [],
                ];
            }

            $clientesAgrupados[$cId]['pedidos'][$nroPrev]['monto_pedido'] += (float) $item->monto_final;
            $clientesAgrupados[$cId]['pedidos'][$nroPrev]['items'][] = [
                'id' => $item->id,
                'codigo_producto' => $item->codigo_producto,
                'producto' => $item->producto,
                'categoria' => $item->categoria,
                'cantidad' => $item->cantidad,
                'monto_final' => (float) $item->monto_final,
            ];
        }

        $listaClientes = array_values($clientesAgrupados);

        // Aplanar los pedidos a arreglo indexado
        foreach ($listaClientes as &$cli) {
            $cli['total_monto'] = round($cli['total_monto'], 2);
            $cli['total_monto_rechazado'] = round($cli['total_monto_rechazado'], 2);
            $cli['pedidos'] = array_values($cli['pedidos']);
            foreach ($cli['pedidos'] as &$p) {
                $p['monto_pedido'] = round($p['monto_pedido'], 2);
            }
        }
        unset($cli);

        // Si hay coordenadas del supervisor, ordenar por proximidad
        if ($lat !== null && $lng !== null) {
            usort($listaClientes, function ($a, $b) {
                if ($a['distancia_metros'] === null) return 1;
                if ($b['distancia_metros'] === null) return -1;
                return $a['distancia_metros'] <=> $b['distancia_metros'];
            });
        }

        $kpis = [
            'total_preventas' => count($nrosPreventaUnicos),
            'total_clientes' => count($listaClientes),
            'monto_total' => round($totalGeneralMonto, 2),
            'total_rechazados' => $conteoTotalRechazos,
            'monto_total_rechazado' => round($totalGeneralRechazado, 2),
            'con_gps' => count(array_filter($listaClientes, fn($c) => $c['tiene_gps'])),
            'sin_gps' => count(array_filter($listaClientes, fn($c) => !$c['tiene_gps'])),
            'en_radio_conteo' => count(array_filter($listaClientes, fn($c) => $c['en_radio'])),
            'en_radio_monto' => round(array_sum(array_map(fn($c) => $c['en_radio'] ? $c['total_monto'] : 0, $listaClientes)), 2),
        ];

        return [
            'clientes' => $listaClientes,
            'kpis' => $kpis,
        ];
    }
}
