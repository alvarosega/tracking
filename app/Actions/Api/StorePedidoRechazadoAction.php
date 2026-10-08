<?php

namespace App\Actions\Api;

use App\Models\PedidoRechazado;
use App\Models\PedidoRechazadoItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StorePedidoRechazadoAction
{
    /**
     * Registra un pedido rechazado con sus ítems de manera transaccional e idempotente.
     *
     * @param User $user
     * @param array $data
     * @param UploadedFile|null $photo
     * @return PedidoRechazado
     */
    public function execute(User $user, array $data, ?UploadedFile $photo = null): PedidoRechazado
    {
        $uuid = trim($data['uuid']);

        // 1. Verificación de Idempotencia por UUID
        $existing = PedidoRechazado::with('items')->where('uuid', $uuid)->first();
        if ($existing) {
            return $existing;
        }

        // 2. Almacenamiento de Fotografía de Evidencia (si fue enviada)
        $photoPath = null;
        if ($photo && $photo->isValid()) {
            $folder = 'rechazos/' . date('Y/m');
            $photoPath = $photo->store($folder, 'public');
        }

        // 3. Enriquecimiento automático de datos desde fact_preventas o pan_ruteo
        $nroPreventa = !empty($data['nro_preventa']) ? (int) $data['nro_preventa'] : null;
        $clienteId = !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null;
        $clienteNombre = $data['cliente_nombre'] ?? null;
        $codigoCliente = $data['codigo_cliente'] ?? null;
        $fechaPreventa = $data['fecha_preventa'] ?? null;
        $ruta = $data['ruta'] ?? null;
        $tipoRechazo = $data['tipo_rechazo'] ?? null;
        $itemsData = $data['items'] ?? [];

        // Si tenemos nro_preventa, consultar fact_preventas para autocompletar cliente, ruta, fecha e items si no vienen
        if ($nroPreventa) {
            $prevRows = DB::connection('supervisor')
                ->table('fact_preventas')
                ->where('nro_preventa', $nroPreventa)
                ->get();

            if ($prevRows->isNotEmpty()) {
                $firstRow = $prevRows->first();
                $clienteId = $clienteId ?: (int) $firstRow->cliente_id;
                $clienteNombre = $clienteNombre ?: $firstRow->cliente;
                $codigoCliente = $codigoCliente ?: $firstRow->codigo_cliente;
                $fechaPreventa = $fechaPreventa ?: $firstRow->fecha_norm;
                $ruta = $ruta ?: $firstRow->ruta;

                // Si Android no envió los items, el backend calcula automáticamente los items rechazados
                if (empty($itemsData)) {
                    $ventasRows = DB::connection('supervisor')
                        ->table('fact_ventas')
                        ->where('revertida', '!=', 'Si')
                        ->where('pre_venta_id', $nroPreventa)
                        ->select('producto_id', DB::raw('SUM(cantidad) as total_facturado'))
                        ->groupBy('producto_id')
                        ->pluck('total_facturado', 'producto_id')
                        ->toArray();

                    $totalFact = 0;
                    $totalRech = 0;

                    foreach ($prevRows as $pr) {
                        $cantPrev = (int) $pr->cantidad;
                        $cantFact = isset($ventasRows[$pr->producto_id]) ? (int) $ventasRows[$pr->producto_id] : 0;
                        $cantRech = max(0, $cantPrev - $cantFact);

                        $montoItem = (float) $pr->monto_final;
                        $precioUnit = ($cantPrev > 0 && $montoItem > 0) ? ($montoItem / $cantPrev) : 0.0;

                        $totalFact += $cantFact;
                        $totalRech += $cantRech;

                        if ($cantRech > 0) {
                            $itemsData[] = [
                                'preventa_item_id'  => $pr->id,
                                'producto_id'       => $pr->producto_id,
                                'codigo_producto'   => $pr->codigo_producto,
                                'producto_nombre'   => $pr->producto,
                                'categoria'         => $pr->categoria,
                                'cantidad_preventa' => $cantPrev,
                                'cantidad_rechazada'=> $cantRech,
                                'precio_unitario'   => round($precioUnit, 2),
                                'monto_rechazado'   => round($cantRech * $precioUnit, 2),
                                'motivo_especifico' => $data['motivo'] ?? null,
                            ];
                        }
                    }

                    if (empty($tipoRechazo)) {
                        $tipoRechazo = ($totalFact > 0) ? 'PARCIAL' : 'TOTAL';
                    }
                }
            }
        }

        if (empty($clienteNombre) && !empty($clienteId)) {
            $clienteRow = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->select(['cliente', 'codigo_cliente', 'ruta'])
                ->where('cliente_id', $clienteId)
                ->first();

            if ($clienteRow) {
                $clienteNombre = $clienteRow->cliente;
                $codigoCliente = $codigoCliente ?: $clienteRow->codigo_cliente;
                $ruta = $ruta ?: $clienteRow->ruta;
            }
        }

        $tipoRechazo = $tipoRechazo ?: 'TOTAL';

        // 4. Creación Transaccional de Cabecera y Detalle de Ítems
        return DB::transaction(function () use ($user, $data, $uuid, $photoPath, $clienteId, $clienteNombre, $codigoCliente, $nroPreventa, $fechaPreventa, $ruta, $tipoRechazo, $itemsData) {
            $totalMonto = 0.0;
            $totalCantidad = 0;

            $pedidoRechazado = PedidoRechazado::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'vendedor' => $user->name ?? $user->username,
                'vendedor_username' => $user->username,
                'cliente_id' => $clienteId,
                'codigo_cliente' => $codigoCliente,
                'cliente_nombre' => $clienteNombre,
                'nro_preventa' => $nroPreventa,
                'fecha_preventa' => $fechaPreventa,
                'fecha_rechazo' => $data['fecha_rechazo'] ?? now('America/La_Paz')->format('Y-m-d H:i:s'),
                'ruta' => $ruta,
                'motivo' => $data['motivo'],
                'tipo_rechazo' => $tipoRechazo,
                'comentarios' => $data['comentarios'] ?? null,
                'photo_path' => $photoPath,
                'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
                'is_mock_location' => (bool) ($data['is_mock_location'] ?? false),
                'total_items_rechazados' => 0,
                'monto_total_rechazado' => 0.00,
            ]);

            foreach ($itemsData as $item) {
                $cantRechazada = (int) ($item['cantidad_rechazada'] ?? 1);
                $precioUnitario = (float) ($item['precio_unitario'] ?? 0.0);
                
                $montoItem = isset($item['monto_rechazado']) && (float) $item['monto_rechazado'] > 0
                    ? (float) $item['monto_rechazado']
                    : round($cantRechazada * $precioUnitario, 2);

                $totalMonto += $montoItem;
                $totalCantidad += $cantRechazada;

                PedidoRechazadoItem::create([
                    'pedido_rechazado_id' => $pedidoRechazado->id,
                    'preventa_item_id' => !empty($item['preventa_item_id']) ? (int) $item['preventa_item_id'] : null,
                    'producto_id' => !empty($item['producto_id']) ? (int) $item['producto_id'] : null,
                    'codigo_producto' => $item['codigo_producto'] ?? null,
                    'producto_nombre' => $item['producto_nombre'] ?? ($item['producto'] ?? null),
                    'categoria' => $item['categoria'] ?? null,
                    'cantidad_preventa' => (int) ($item['cantidad_preventa'] ?? $cantRechazada),
                    'cantidad_rechazada' => $cantRechazada,
                    'precio_unitario' => $precioUnitario,
                    'monto_rechazado' => $montoItem,
                    'motivo_especifico' => $item['motivo_especifico'] ?? null,
                ]);
            }

            // Actualizar totales consolidados en la cabecera
            $pedidoRechazado->update([
                'total_items_rechazados' => $totalCantidad,
                'monto_total_rechazado' => round($totalMonto, 2),
            ]);

            return $pedidoRechazado->load('items');
        });
    }
}

