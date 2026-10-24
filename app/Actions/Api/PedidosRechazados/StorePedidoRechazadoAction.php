<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\PedidoRechazadoItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StorePedidoRechazadoAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(User $user, array $data, ?UploadedFile $photo): PedidoRechazado
    {
        $uuid = trim((string) $data['uuid']);

        // 1. Idempotencia: Si ya existe un registro con ese UUID, retornarlo
        $existente = PedidoRechazado::with('items')->where('uuid', $uuid)->first();
        if ($existente) {
            return $existente;
        }

        // 2. Resolver datos faltantes de cliente/preventa desde fact_preventas si no los envió Android
        $nroPreventa = !empty($data['nro_preventa']) ? (int) $data['nro_preventa'] : null;
        $clienteId = !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null;
        $clienteNombre = $data['cliente_nombre'] ?? null;
        $codigoCliente = $data['codigo_cliente'] ?? null;
        $fechaPreventa = $data['fecha_preventa'] ?? null;
        $ruta = $data['ruta'] ?? ($user->ruta ?? null);
        $itemsData = $data['items'] ?? [];
        $tipoSugerido = $data['tipo_rechazo'] ?? null;

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
                                'preventa_item_id' => $pr->id,
                                'producto_id' => $pr->producto_id,
                                'codigo_producto' => $pr->codigo_producto,
                                'producto_nombre' => $pr->producto,
                                'categoria' => $pr->categoria,
                                'cantidad_preventa' => $cantPrev,
                                'cantidad_rechazada' => $cantRech,
                                'precio_unitario' => round($precioUnit, 2),
                                'motivo_especifico' => null,
                            ];
                        }
                    }

                    if (!$tipoSugerido) {
                        $tipoSugerido = ($totalFact > 0 && $totalRech > 0) ? 'PARCIAL' : 'TOTAL';
                    }
                }
            }
        }

        // 3. Manejo de foto opcional
        $photoPath = null;
        if ($photo) {
            $year = date('Y');
            $month = date('m');
            $photoPath = $photo->store("rechazos/{$year}/{$month}", 'public');
        }

        $fechaRechazo = !empty($data['fecha_rechazo'])
            ? Carbon::parse((string) $data['fecha_rechazo'])
            : Carbon::now();

        // 4. Inserción con transacción en MySQL
        return DB::connection('mysql')->transaction(function () use (
            $data, $user, $uuid, $nroPreventa, $clienteId, $codigoCliente,
            $clienteNombre, $fechaPreventa, $fechaRechazo, $ruta, $tipoSugerido,
            $photoPath, $itemsData
        ): PedidoRechazado {
            $cabecera = PedidoRechazado::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'vendedor' => $user->name ?? $user->username,
                'vendedor_username' => $user->username,
                'cliente_id' => $clienteId ?? 0,
                'codigo_cliente' => $codigoCliente,
                'cliente_nombre' => $clienteNombre,
                'nro_preventa' => $nroPreventa,
                'fecha_preventa' => $fechaPreventa,
                'fecha_rechazo' => $fechaRechazo,
                'ruta' => $ruta,
                'motivo' => $data['motivo'],
                'tipo_rechazo' => $tipoSugerido ?: ($data['tipo_rechazo'] ?? 'TOTAL'),
                'comentarios' => $data['comentarios'] ?? null,
                'photo_path' => $photoPath,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'accuracy' => $data['accuracy'] ?? null,
                'is_mock_location' => (bool) ($data['is_mock_location'] ?? false),
                'total_items_rechazados' => 0,
                'monto_total_rechazado' => 0.00,
            ]);

            $totalCantRechazada = 0;
            $montoTotalRechazado = 0.00;
            $itemsToInsert = [];

            foreach ($itemsData as $it) {
                $cantRech = (int) ($it['cantidad_rechazada'] ?? 0);
                $precioUnit = (float) ($it['precio_unitario'] ?? 0.00);
                $montoItem = round($cantRech * $precioUnit, 2);

                $totalCantRechazada += $cantRech;
                $montoTotalRechazado += $montoItem;

                $itemsToInsert[] = [
                    'pedido_rechazado_id' => $cabecera->id,
                    'preventa_item_id' => isset($it['preventa_item_id']) ? (int) $it['preventa_item_id'] : null,
                    'producto_id' => isset($it['producto_id']) ? (int) $it['producto_id'] : null,
                    'codigo_producto' => $it['codigo_producto'] ?? null,
                    'producto_nombre' => $it['producto_nombre'] ?? null,
                    'categoria' => $it['categoria'] ?? null,
                    'cantidad_preventa' => (int) ($it['cantidad_preventa'] ?? 0),
                    'cantidad_rechazada' => $cantRech,
                    'precio_unitario' => $precioUnit,
                    'monto_rechazado' => $montoItem,
                    'motivo_especifico' => $it['motivo_especifico'] ?? null,
                    'created_at' => now(),
                ];
            }

            if (!empty($itemsToInsert)) {
                PedidoRechazadoItem::insert($itemsToInsert);
            }

            $cabecera->update([
                'total_items_rechazados' => $totalCantRechazada,
                'monto_total_rechazado' => $montoTotalRechazado,
            ]);

            return $cabecera->load('items');
        });
    }
}

