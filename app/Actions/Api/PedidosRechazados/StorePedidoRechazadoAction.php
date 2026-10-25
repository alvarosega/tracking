<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\PedidoRechazadoItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorePedidoRechazadoAction
{
    /**
     * Guarda el registro de pedido rechazado con sus ítems y evidencia con garantía de idempotencia y resiliencia.
     *
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

        // 2. Resolver datos faltantes de cliente/preventa desde supervisor DB si no los envió Android
        $nroPreventa = !empty($data['nro_preventa']) ? (int) $data['nro_preventa'] : null;
        $clienteId = !empty($data['cliente_id']) && is_numeric($data['cliente_id']) ? (int) $data['cliente_id'] : null;
        $clienteNombre = !empty($data['cliente_nombre']) && $data['cliente_nombre'] !== 'null' ? (string) $data['cliente_nombre'] : null;
        $codigoCliente = !empty($data['codigo_cliente']) && $data['codigo_cliente'] !== 'null' ? (string) $data['codigo_cliente'] : null;
        $fechaPreventa = !empty($data['fecha_preventa']) && $data['fecha_preventa'] !== 'null' ? (string) $data['fecha_preventa'] : null;
        $ruta = !empty($data['ruta']) && $data['ruta'] !== 'null' ? (string) $data['ruta'] : ($user->ruta ?? null);
        $itemsData = $data['items'] ?? [];
        $tipoSugerido = $data['tipo_rechazo'] ?? null;

        if ($nroPreventa) {
            try {
                $prevRows = DB::connection('supervisor')
                    ->table('fact_preventas')
                    ->where('nro_preventa', $nroPreventa)
                    ->get();

                if ($prevRows->isNotEmpty()) {
                    $firstRow = $prevRows->first();
                    $clienteId = $clienteId ?: (int) $firstRow->cliente_id;
                    $clienteNombre = $clienteNombre ?: ($firstRow->cliente ?? null);
                    $codigoCliente = $codigoCliente ?: ($firstRow->codigo_cliente ?? null);
                    $fechaPreventa = $fechaPreventa ?: ($firstRow->fecha_norm ?? null);
                    $ruta = $ruta ?: ($firstRow->ruta ?? null);

                    if (empty($itemsData)) {
                        $ventasRows = DB::connection('supervisor')
                            ->table('fact_ventas')
                            ->where(function ($q): void {
                                $q->whereNull('revertida')
                                  ->orWhere(function ($sub): void {
                                      $sub->where('revertida', '!=', 'Si')
                                          ->where('revertida', '!=', 'SI');
                                  });
                            })
                            ->where('pre_venta_id', $nroPreventa)
                            ->select('producto_id', DB::raw('SUM(cantidad) as total_facturado'))
                            ->groupBy('producto_id')
                            ->pluck('total_facturado', 'producto_id')
                            ->toArray();

                        $totalFact = 0;
                        $totalRech = 0;

                        foreach ($prevRows as $pr) {
                            $cantPrev = (int) ($pr->cantidad ?? 0);
                            $cantFact = isset($ventasRows[$pr->producto_id]) ? (int) $ventasRows[$pr->producto_id] : 0;
                            $cantRech = max(0, $cantPrev - $cantFact);

                            $montoItem = (float) ($pr->monto_final ?? 0.0);
                            $precioUnit = ($cantPrev > 0 && $montoItem > 0) ? ($montoItem / $cantPrev) : 0.0;

                            $totalFact += $cantFact;
                            $totalRech += $cantRech;

                            if ($cantRech > 0) {
                                $itemsData[] = [
                                    'preventa_item_id' => $pr->id ?? null,
                                    'producto_id' => $pr->producto_id ?? null,
                                    'codigo_producto' => $pr->codigo_producto ?? null,
                                    'producto_nombre' => $pr->producto ?? null,
                                    'categoria' => $pr->categoria ?? 'General',
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
            } catch (\Throwable $e) {
                Log::warning('No se pudo enriquecer datos de preventa desde supervisor DB: ' . $e->getMessage());
            }
        }

        // 3. Manejo de foto opcional
        $photoPath = null;
        if ($photo) {
            $year = date('Y');
            $month = date('m');
            $photoPath = $photo->store("rechazos/{$year}/{$month}", 'public');
        }

        $fechaRechazo = !empty($data['fecha_rechazo']) && $data['fecha_rechazo'] !== 'null'
            ? Carbon::parse((string) $data['fecha_rechazo'])
            : Carbon::now();

        // 4. Precalcular totales de detalle para optimizar en un único INSERT de cabecera
        $totalCantRechazada = 0;
        $montoTotalRechazado = 0.00;
        $sanitizedItems = [];

        foreach ($itemsData as $it) {
            $cantRech = isset($it['cantidad_rechazada']) && is_numeric($it['cantidad_rechazada'])
                ? max(0, (int) round((float) $it['cantidad_rechazada']))
                : 0;

            $precioUnit = isset($it['precio_unitario']) && is_numeric($it['precio_unitario'])
                ? max(0.0, round((float) $it['precio_unitario'], 2))
                : 0.00;

            $montoItem = round($cantRech * $precioUnit, 2);

            $totalCantRechazada += $cantRech;
            $montoTotalRechazado += $montoItem;

            $prodId = null;
            if (!empty($it['producto_id']) && is_numeric($it['producto_id']) && (int) $it['producto_id'] > 0) {
                $prodId = (int) $it['producto_id'];
            }

            $prevItemId = null;
            if (!empty($it['preventa_item_id']) && is_numeric($it['preventa_item_id']) && (int) $it['preventa_item_id'] > 0) {
                $prevItemId = (int) $it['preventa_item_id'];
            }

            $sanitizedItems[] = [
                'preventa_item_id' => $prevItemId,
                'producto_id' => $prodId,
                'codigo_producto' => !empty($it['codigo_producto']) && $it['codigo_producto'] !== 'null' ? (string) $it['codigo_producto'] : null,
                'producto_nombre' => !empty($it['producto_nombre']) && $it['producto_nombre'] !== 'null' ? (string) $it['producto_nombre'] : null,
                'categoria' => !empty($it['categoria']) && $it['categoria'] !== 'null' ? (string) $it['categoria'] : 'General',
                'cantidad_preventa' => isset($it['cantidad_preventa']) && is_numeric($it['cantidad_preventa']) ? max(0, (int) round((float) $it['cantidad_preventa'])) : 0,
                'cantidad_rechazada' => $cantRech,
                'precio_unitario' => $precioUnit,
                'monto_rechazado' => $montoItem,
                'motivo_especifico' => !empty($it['motivo_especifico']) && $it['motivo_especifico'] !== 'null' ? (string) $it['motivo_especifico'] : null,
                'created_at' => now(),
            ];
        }

        // 5. Inserción con transacción en MySQL local
        try {
            return DB::connection('mysql')->transaction(function () use (
                $data, $user, $uuid, $nroPreventa, $clienteId, $codigoCliente,
                $clienteNombre, $fechaPreventa, $fechaRechazo, $ruta, $tipoSugerido,
                $photoPath, $sanitizedItems, $totalCantRechazada, $montoTotalRechazado
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
                    'motivo' => $data['motivo'] ?? 'OTRO',
                    'tipo_rechazo' => $tipoSugerido ?: ($data['tipo_rechazo'] ?? 'TOTAL'),
                    'comentarios' => !empty($data['comentarios']) && $data['comentarios'] !== 'null' ? (string) $data['comentarios'] : null,
                    'photo_path' => $photoPath,
                    'latitude' => isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null,
                    'longitude' => isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null,
                    'accuracy' => isset($data['accuracy']) && is_numeric($data['accuracy']) ? (float) $data['accuracy'] : null,
                    'is_mock_location' => filter_var($data['is_mock_location'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'total_items_rechazados' => $totalCantRechazada,
                    'monto_total_rechazado' => round($montoTotalRechazado, 2),
                ]);

                if (!empty($sanitizedItems)) {
                    foreach ($sanitizedItems as &$it) {
                        $it['pedido_rechazado_id'] = $cabecera->id;
                    }
                    unset($it);

                    PedidoRechazadoItem::insert($sanitizedItems);
                }

                return $cabecera->load('items');
            });
        } catch (\Throwable $e) {
            // Limpiar foto si ocurrió error en la BD para evitar basura en el disco
            if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            throw $e;
        }
    }
}
