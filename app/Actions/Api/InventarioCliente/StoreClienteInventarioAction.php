<?php

declare(strict_types=1);

namespace App\Actions\Api\InventarioCliente;

use App\Models\ClienteInventario;
use App\Models\ClienteInventarioItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreClienteInventarioAction
{
    /**
     * Guarda el inventario físico tomado en el cliente con garantía de idempotencia.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, idempotent: bool, message: string, data: ClienteInventario}
     */
    public function execute(array $data, User $user): array
    {
        $uuid = trim((string) $data['uuid']);

        // 1. Idempotencia: Si ya existe un registro con el mismo UUID, devolverlo sin duplicar
        $existente = ClienteInventario::with('items')->where('uuid', $uuid)->first();
        if ($existente) {
            return [
                'success' => true,
                'idempotent' => true,
                'message' => 'Inventario ya registrado previamente (sync duplicado omitido)',
                'data' => $existente,
            ];
        }

        // 2. Resolver datos del cliente si vinieron nulos de manera resiliente (sin romper si falla supervisor DB)
        $clienteId = is_numeric($data['cliente_id']) ? (int) $data['cliente_id'] : $data['cliente_id'];
        $clienteNombre = !empty($data['cliente_nombre']) && $data['cliente_nombre'] !== 'null' ? (string) $data['cliente_nombre'] : null;
        $codigoCliente = !empty($data['codigo_cliente']) && $data['codigo_cliente'] !== 'null' ? (string) $data['codigo_cliente'] : null;

        if (empty($clienteNombre) || empty($codigoCliente)) {
            try {
                // Intentar primero desde pan_ruteo (si existe) o fact_ventas en supervisor
                $clienteInfo = DB::connection('supervisor')
                    ->table('pan_ruteo')
                    ->where('cliente_id', $clienteId)
                    ->select('nombre_cliente as cliente', 'codigo_cliente')
                    ->first();

                if (!$clienteInfo) {
                    $clienteInfo = DB::connection('supervisor')
                        ->table('fact_ventas')
                        ->where('cliente_id', $clienteId)
                        ->select('cliente', 'codigo_cliente')
                        ->first();
                }

                if ($clienteInfo) {
                    $clienteNombre = $clienteNombre ?: ($clienteInfo->cliente ?? null);
                    $codigoCliente = $codigoCliente ?: ($clienteInfo->codigo_cliente ?? null);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo enriquecer información del cliente desde supervisor DB: ' . $e->getMessage());
            }
        }

        $fechaRegistro = !empty($data['fecha_registro']) && $data['fecha_registro'] !== 'null'
            ? Carbon::parse((string) $data['fecha_registro'])->setTimezone('America/La_Paz')->format('Y-m-d H:i:s')
            : Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');

        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');

        // 3. Crear cabecera y detalle en transacción de BD app (mysql)
        $inventario = DB::connection('mysql')->transaction(function () use ($data, $user, $clienteId, $clienteNombre, $codigoCliente, $fechaRegistro, $nowBolivia, $uuid): ClienteInventario {
            $itemsData = $data['items'] ?? [];

            $cabecera = ClienteInventario::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'ruta' => $user->ruta ?? null,
                'vendedor' => $user->username ?? $user->ruta ?? null,
                'cliente_id' => $clienteId,
                'codigo_cliente' => $codigoCliente,
                'cliente_nombre' => $clienteNombre,
                'fecha_registro' => $fechaRegistro,
                'latitude' => isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null,
                'accuracy' => isset($data['accuracy']) && is_numeric($data['accuracy']) ? (float) $data['accuracy'] : null,
                'is_mock_location' => filter_var($data['is_mock_location'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'total_items_censados' => count($itemsData),
                'observaciones' => !empty($data['observaciones']) && $data['observaciones'] !== 'null' ? (string) $data['observaciones'] : null,
                'created_at' => $nowBolivia,
                'updated_at' => $nowBolivia,
            ]);

            $itemsToInsert = [];
            foreach ($itemsData as $item) {
                // Producto ID: sanear números válidos > 0, si es string "null" o <=0 dejar null
                $prodId = null;
                $rawProdId = $item['producto_id'] ?? null;
                if ($rawProdId !== null && $rawProdId !== '' && $rawProdId !== 'null' && is_numeric($rawProdId)) {
                    $val = (int) $rawProdId;
                    if ($val > 0) {
                        $prodId = $val;
                    }
                }

                $codigo = $item['codigo_producto'] ?? $item['codigo'] ?? null;
                if ($codigo === 'null' || $codigo === '') {
                    $codigo = null;
                }

                $nombre = $item['producto_nombre'] ?? $item['producto'] ?? null;
                if ($nombre === 'null' || $nombre === '') {
                    $nombre = null;
                }

                $categoria = $item['categoria'] ?? 'General';
                if ($categoria === 'null' || empty($categoria)) {
                    $categoria = 'General';
                }

                $stockFisico = isset($item['stock_fisico']) && is_numeric($item['stock_fisico'])
                    ? (int) round((float) $item['stock_fisico'])
                    : 0;

                $cantHistorica = isset($item['cantidad_historica']) && is_numeric($item['cantidad_historica'])
                    ? (int) round((float) $item['cantidad_historica'])
                    : (isset($item['total_comprado']) && is_numeric($item['total_comprado']) ? (int) round((float) $item['total_comprado']) : 0);

                $fechaUltCompra = !empty($item['ultima_compra_fecha']) && $item['ultima_compra_fecha'] !== 'null'
                    ? (string) $item['ultima_compra_fecha']
                    : (!empty($item['ultima_compra']['fecha']) && $item['ultima_compra']['fecha'] !== 'null' ? (string) $item['ultima_compra']['fecha'] : null);

                $cantUltCompra = isset($item['ultima_compra_cantidad']) && is_numeric($item['ultima_compra_cantidad'])
                    ? (int) round((float) $item['ultima_compra_cantidad'])
                    : (isset($item['ultima_compra']['cantidad']) && is_numeric($item['ultima_compra']['cantidad']) ? (int) round((float) $item['ultima_compra']['cantidad']) : 0);

                $itemsToInsert[] = [
                    'cliente_inventario_id' => $cabecera->id,
                    'producto_id' => $prodId,
                    'codigo_producto' => $codigo,
                    'producto_nombre' => $nombre,
                    'categoria' => $categoria,
                    'stock_fisico' => max(0, $stockFisico),
                    'cantidad_historica' => max(0, $cantHistorica),
                    'ultima_compra_fecha' => $fechaUltCompra,
                    'ultima_compra_cantidad' => max(0, $cantUltCompra),
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


