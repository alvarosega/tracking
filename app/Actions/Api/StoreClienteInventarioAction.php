<?php

namespace App\Actions\Api;

use App\Models\ClienteInventario;
use App\Models\ClienteInventarioItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreClienteInventarioAction
{
    /**
     * Guarda el inventario físico tomado en el cliente con garantía de idempotencia.
     *
     * @param array $data
     * @param User $user
     * @return array
     */
    public function execute(array $data, User $user): array
    {
        // 1. Idempotencia: Si ya existe un registro con el mismo UUID, devolverlo sin duplicar
        $existente = ClienteInventario::with('items')->where('uuid', $data['uuid'])->first();
        if ($existente) {
            return [
                'success' => true,
                'idempotent' => true,
                'message' => 'Inventario ya registrado previamente (sync duplicado omitido)',
                'data' => $existente,
            ];
        }

        // 2. Resolver datos del cliente si vinieron nulos
        $clienteId = (int) $data['cliente_id'];
        $clienteNombre = $data['cliente_nombre'] ?? null;
        $codigoCliente = $data['codigo_cliente'] ?? null;

        if (empty($clienteNombre) || empty($codigoCliente)) {
            $clienteInfo = DB::connection('supervisor')
                ->table('fact_ventas')
                ->where('cliente_id', $clienteId)
                ->select('cliente', 'codigo_cliente')
                ->first();

            if ($clienteInfo) {
                $clienteNombre = $clienteNombre ?: $clienteInfo->cliente;
                $codigoCliente = $codigoCliente ?: $clienteInfo->codigo_cliente;
            }
        }

        $fechaRegistro = !empty($data['fecha_registro'])
            ? Carbon::parse($data['fecha_registro'])
            : Carbon::now();

        // 3. Crear cabecera y detalle en transacción de BD app (mysql)
        $inventario = DB::connection('mysql')->transaction(function () use ($data, $user, $clienteId, $clienteNombre, $codigoCliente, $fechaRegistro) {
            $itemsData = $data['items'] ?? [];

            $cabecera = ClienteInventario::create([
                'uuid' => $data['uuid'],
                'user_id' => $user->id,
                'ruta' => $user->ruta ?? null,
                'vendedor' => $user->username ?? $user->ruta ?? null,
                'cliente_id' => $clienteId,
                'codigo_cliente' => $codigoCliente,
                'cliente_nombre' => $clienteNombre,
                'fecha_registro' => $fechaRegistro,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'is_mock_location' => (bool) ($data['is_mock_location'] ?? false),
                'total_items_censados' => count($itemsData),
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            $itemsToInsert = [];
            foreach ($itemsData as $item) {
                $itemsToInsert[] = [
                    'cliente_inventario_id' => $cabecera->id,
                    'producto_id' => isset($item['producto_id']) ? (int) $item['producto_id'] : null,
                    'codigo_producto' => $item['codigo_producto'] ?? null,
                    'producto_nombre' => $item['producto_nombre'] ?? null,
                    'categoria' => $item['categoria'] ?? null,
                    'stock_fisico' => (int) ($item['stock_fisico'] ?? 0),
                    'cantidad_historica' => (int) ($item['cantidad_historica'] ?? 0),
                    'ultima_compra_fecha' => !empty($item['ultima_compra_fecha']) ? $item['ultima_compra_fecha'] : null,
                    'ultima_compra_cantidad' => (int) ($item['ultima_compra_cantidad'] ?? 0),
                    'created_at' => now(),
                ];
            }

            if (!empty($itemsToInsert)) {
                ClienteInventarioItem::insert($itemsToInsert);
            }

            return $cabecera->load('items');
        });

        return [
            'success' => true,
            'idempotent' => false,
            'message' => 'Inventario registrado correctamente',
            'data' => $inventario,
        ];
    }
}

