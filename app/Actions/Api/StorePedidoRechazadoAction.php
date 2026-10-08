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

        // 3. Enriquecimiento de datos si faltan nombres de cliente desde pan_ruteo
        $clienteNombre = $data['cliente_nombre'] ?? null;
        $codigoCliente = $data['codigo_cliente'] ?? null;

        if (empty($clienteNombre) && !empty($data['cliente_id'])) {
            $clienteRow = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->select(['cliente', 'codigo_cliente', 'ruta'])
                ->where('cliente_id', $data['cliente_id'])
                ->first();

            if ($clienteRow) {
                $clienteNombre = $clienteRow->cliente;
                $codigoCliente = $codigoCliente ?: $clienteRow->codigo_cliente;
                if (empty($data['ruta'])) {
                    $data['ruta'] = $clienteRow->ruta;
                }
            }
        }

        // 4. Creación Transaccional de Cabecera y Detalle de Ítems
        return DB::transaction(function () use ($user, $data, $uuid, $photoPath, $clienteNombre, $codigoCliente) {
            $itemsData = $data['items'] ?? [];
            $totalMonto = 0.0;
            $totalCantidad = 0;

            $pedidoRechazado = PedidoRechazado::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'vendedor' => $user->name ?? $user->username,
                'vendedor_username' => $user->username,
                'cliente_id' => (int) $data['cliente_id'],
                'codigo_cliente' => $codigoCliente,
                'cliente_nombre' => $clienteNombre,
                'nro_preventa' => !empty($data['nro_preventa']) ? (int) $data['nro_preventa'] : null,
                'fecha_preventa' => !empty($data['fecha_preventa']) ? $data['fecha_preventa'] : null,
                'fecha_rechazo' => $data['fecha_rechazo'],
                'ruta' => $data['ruta'] ?? null,
                'motivo' => $data['motivo'],
                'tipo_rechazo' => $data['tipo_rechazo'] ?? 'TOTAL',
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
                
                // Si viene monto_rechazado directo, usarlo; de lo contrario, calcular: cantidad * precio
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

