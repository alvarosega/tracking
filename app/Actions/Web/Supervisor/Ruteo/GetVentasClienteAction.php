<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FactVenta;

class GetVentasClienteAction
{
    /**
     * Obtiene el desglose histórico de compras de un cliente para un año y meses determinados.
     *
     * @param string|int $clienteId
     * @param int $anio
     * @param array<int> $meses
     * @return array{cliente_id: string|int, anio: int, total_general: float, total_ventas: int, ventas: array}
     */
    public function execute(string|int $clienteId, int $anio = 2026, array $meses = []): array
    {
        $safeAnio = (int) ($anio ?: date('Y'));

        $query = FactVenta::query()
            ->validas()
            ->where('cliente_id', $clienteId)
            ->whereBetween('fecha_norm', ["{$safeAnio}-01-01", "{$safeAnio}-12-31"]);

        if (!empty($meses)) {
            $query->whereIn('mes', $meses);
        }

        $filas = $query
            ->select([
                'id', 'venta_id', 'producto_id', 'fecha_norm', 'hora',
                'cliente_id', 'cliente', 'vendedor', 'tipo_pago', 'nro_factura',
                'codigo', 'producto', 'categoria', 'cantidad', 'precio_unitario',
                'descuento', 'monto_final', 'mes'
            ])
            ->orderByDesc('fecha_norm')
            ->orderByDesc('hora')
            ->get();

        $ventasAgrupadas = [];
        $totalGeneral = 0.0;

        foreach ($filas as $f) {
            $vId = $f->venta_id;
            if (!isset($ventasAgrupadas[$vId])) {
                $ventasAgrupadas[$vId] = [
                    'venta_id' => $vId,
                    'fecha' => $f->fecha_norm,
                    'hora' => $f->hora,
                    'mes' => $f->mes,
                    'nro_factura' => $f->nro_factura,
                    'vendedor' => $f->vendedor,
                    'tipo_pago' => $f->tipo_pago,
                    'monto_ticket' => 0.0,
                    'productos' => [],
                ];
            }

            $ventasAgrupadas[$vId]['monto_ticket'] += (float) $f->monto_final;
            $totalGeneral += (float) $f->monto_final;

            $ventasAgrupadas[$vId]['productos'][] = [
                'id' => $f->id,
                'codigo' => $f->codigo,
                'producto' => $f->producto,
                'categoria' => $f->categoria,
                'cantidad' => $f->cantidad,
                'precio_unitario' => (float) $f->precio_unitario,
                'descuento' => (float) $f->descuento,
                'monto_final' => (float) $f->monto_final,
            ];
        }

        // Redondear totales de ticket
        foreach ($ventasAgrupadas as &$ticket) {
            $ticket['monto_ticket'] = round($ticket['monto_ticket'], 2);
        }
        unset($ticket);

        return [
            'cliente_id' => $clienteId,
            'anio' => $safeAnio,
            'total_general' => round($totalGeneral, 2),
            'total_ventas' => count($ventasAgrupadas),
            'ventas' => array_values($ventasAgrupadas),
        ];
    }
}

