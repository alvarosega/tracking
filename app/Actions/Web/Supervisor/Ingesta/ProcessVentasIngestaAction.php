<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProcessVentasIngestaAction
{
    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa la ingesta de archivo de Ventas (FACT_VENTAS) de forma escalable por lotes (chunked streaming),
     * manteniendo el consumo de memoria en < 30 MB incluso con cientos de miles de filas.
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(900);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'FACT_VENTAS');

        $fileHash = hash_file('sha256', $filePath) ?: md5($filename);

        $conn = DB::connection('supervisor');

        // Asegurar tabla auditoria_ventas_discrepancias
        if (!Schema::connection('supervisor')->hasTable('auditoria_ventas_discrepancias')) {
            try {
                $conn->statement("
                    CREATE TABLE IF NOT EXISTS `auditoria_ventas_discrepancias` (
                        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                        `archivo_origen` VARCHAR(255) NOT NULL,
                        `venta_id` BIGINT NOT NULL,
                        `producto_id` BIGINT NOT NULL,
                        `fecha` VARCHAR(50) NULL,
                        `cliente_id` BIGINT NULL,
                        `monto_anterior` DECIMAL(12, 4) NULL,
                        `monto_nuevo` DECIMAL(12, 4) NULL,
                        `cantidad_anterior` BIGINT NULL,
                        `cantidad_nueva` BIGINT NULL,
                        `revertida_anterior` VARCHAR(10) NULL,
                        `revertida_nueva` VARCHAR(10) NULL,
                        `estado_revision` VARCHAR(20) DEFAULT 'PENDIENTE',
                        `detectado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_aud_vta_prod` (`venta_id`, `producto_id`),
                        INDEX `idx_aud_vta_estado` (`estado_revision`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            } catch (\Throwable) {}
        }

        // Obtener columnas existentes en fact_ventas
        try {
            $colsDb = Schema::connection('supervisor')->getColumnListing('fact_ventas');
        } catch (\Throwable) {
            $colsDb = [];
        }

        if (empty($colsDb)) {
            try {
                $colDbRows = $conn->select("
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fact_ventas'
                ");
                $colsDb = array_map(fn($c) => $c->COLUMN_NAME ?? $c->column_name, $colDbRows);
            } catch (\Throwable) {
                $colsDb = [];
            }
        }
        $colsDbFlip = array_flip($colsDb);

        // Configurar timeouts en la sesión de base de datos
        try {
            $conn->statement("SET SESSION wait_timeout = 600");
            $conn->statement("SET SESSION net_read_timeout = 600");
            $conn->statement("SET SESSION net_write_timeout = 600");
        } catch (\Throwable) {}

        $decimalCols = [
            'monto', 'monto_final', 'descuento', 'descuento2', 'descuento_socio',
            'descuento_cliente', 'descuento_grupo', 'descuento_volumen',
            'cantidad_cajas', 'precio_unitario', 'precio_paquete'
        ];

        $totalLeidas = 0;
        $totalNuevas = 0;
        $totalModificadas = 0;
        $totalIdenticas = 0;
        $totalDiscrepancias = 0;
        $montoTotal = 0.0;
        $fechaMin = null;
        $fechaMax = null;

        $batch = [];
        $batchSize = 2000;

        // Callback para procesar cada lote de 2000 filas de forma atómica y liberar RAM
        $processBatch = function (array $rows) use (
            $conn,
            $colsDbFlip,
            $filename,
            &$totalNuevas,
            &$totalModificadas,
            &$totalIdenticas,
            &$totalDiscrepancias
        ) {
            if (empty($rows)) {
                return;
            }

            // Deduplicar dentro del lote por venta_id_producto_id
            $batchDedup = [];
            $ventasIds = [];

            foreach ($rows as $row) {
                $vid = (int)$row['venta_id'];
                $pid = (int)$row['producto_id'];
                $k = "{$vid}_{$pid}";
                $batchDedup[$k] = $row;
                $ventasIds[$vid] = true;
            }

            // Consultar en BD solo los venta_id presentes en este lote
            $ventasList = array_keys($ventasIds);
            $existentes = [];

            if (!empty($ventasList)) {
                $chunksVentas = array_chunk($ventasList, 500);
                foreach ($chunksVentas as $chunkV) {
                    $placeholders = implode(',', array_fill(0, count($chunkV), '?'));
                    $rowsDb = $conn->select("
                        SELECT id, venta_id, producto_id, monto_final, cantidad, revertida
                        FROM fact_ventas
                        WHERE venta_id IN ({$placeholders})
                    ", $chunkV);

                    foreach ($rowsDb as $r) {
                        $k = "{$r->venta_id}_{$r->producto_id}";
                        $existentes[$k] = $r;
                    }
                }
            }

            $nuevasRecords = [];
            $modificadasUpdates = [];
            $alteradasAuditoria = [];

            foreach ($batchDedup as $k => $row) {
                $vid = (int)$row['venta_id'];
                $pid = (int)$row['producto_id'];
                $montoNuevo = (float)($row['monto_final'] ?? 0.0);
                $cantNueva = (int)($row['cantidad'] ?? 0);
                $revNueva = (string)($row['revertida'] ?? 'No');

                // Filtrar solo columnas presentes en tabla fact_ventas
                $dbRow = [];
                foreach ($row as $colName => $colVal) {
                    if (isset($colsDbFlip[$colName])) {
                        $dbRow[$colName] = $colVal;
                    }
                }

                if (!isset($existentes[$k])) {
                    $nuevasRecords[] = $dbRow;
                } else {
                    $prev = $existentes[$k];
                    $montoAnt = (float)($prev->monto_final ?? 0.0);
                    $cantAnt = (int)($prev->cantidad ?? 0);
                    $revAnt = (string)($prev->revertida ?? 'No');

                    $huboCambio = (abs($montoAnt - $montoNuevo) > 0.01) ||
                                  ($cantAnt !== $cantNueva) ||
                                  (strtolower($revAnt) !== strtolower($revNueva));

                    if ($huboCambio) {
                        $modificadasUpdates[] = [
                            'id' => (int)$prev->id,
                            'row' => $dbRow,
                        ];

                        $alteradasAuditoria[] = [
                            'archivo_origen' => $filename,
                            'venta_id' => $vid,
                            'producto_id' => $pid,
                            'fecha' => (string)($row['fecha_norm'] ?? $row['fecha'] ?? ''),
                            'cliente_id' => isset($row['cliente_id']) ? (int)$row['cliente_id'] : null,
                            'monto_anterior' => $montoAnt,
                            'monto_nuevo' => $montoNuevo,
                            'cantidad_anterior' => $cantAnt,
                            'cantidad_nueva' => $cantNueva,
                            'revertida_anterior' => $revAnt,
                            'revertida_nueva' => $revNueva,
                            'estado_revision' => 'PENDIENTE',
                        ];
                    } else {
                        $totalIdenticas++;
                    }
                }
            }

            // Normalizar uniformidad de columnas para inserción en chunks
            if (!empty($nuevasRecords)) {
                $allCols = [];
                foreach ($nuevasRecords as $rec) {
                    foreach (array_keys($rec) as $cKey) {
                        $allCols[$cKey] = true;
                    }
                }
                $canonicalCols = array_keys($allCols);

                $uniformRecords = [];
                foreach ($nuevasRecords as $rec) {
                    $uRow = [];
                    foreach ($canonicalCols as $cKey) {
                        $uRow[$cKey] = array_key_exists($cKey, $rec) ? $rec[$cKey] : null;
                    }
                    $uniformRecords[] = $uRow;
                }
                $nuevasRecords = $uniformRecords;
            }

            // Ejecutar operaciones en BD dentro de una transacción atómica por lote
            $conn->transaction(function () use ($conn, $nuevasRecords, $modificadasUpdates, $alteradasAuditoria) {
                if (!empty($nuevasRecords)) {
                    $chunks = array_chunk($nuevasRecords, 300);
                    foreach ($chunks as $chunk) {
                        $conn->table('fact_ventas')->insert($chunk);
                    }
                }

                foreach ($modificadasUpdates as $item) {
                    $updateData = $item['row'];
                    unset($updateData['id'], $updateData['venta_id'], $updateData['producto_id']);
                    if (!empty($updateData)) {
                        $conn->table('fact_ventas')
                            ->where('id', $item['id'])
                            ->update($updateData);
                    }
                }

                if (!empty($alteradasAuditoria)) {
                    $chunksAud = array_chunk($alteradasAuditoria, 300);
                    foreach ($chunksAud as $chunk) {
                        $conn->table('auditoria_ventas_discrepancias')->insert($chunk);
                    }
                }
            });

            $totalNuevas += count($nuevasRecords);
            $totalModificadas += count($modificadasUpdates);
            $totalDiscrepancias += count($alteradasAuditoria);

            unset($batchDedup, $ventasIds, $existentes, $nuevasRecords, $modificadasUpdates, $alteradasAuditoria);
        };

        // Streaming fila por fila consumiendo generador de bajo consumo
        foreach ($this->parser->streamRows($filePath, $filename) as $r) {
            $totalLeidas++;
            $mapped = $this->validator->mapRow($r, 'FACT_VENTAS');

            $ventaId = isset($mapped['venta_id']) ? (int)$mapped['venta_id'] : 0;
            $productoId = isset($mapped['producto_id']) ? (int)$mapped['producto_id'] : 0;

            if ($ventaId <= 0 || $productoId <= 0) {
                continue;
            }

            // Normalizar fecha_norm y fecha (YYYY-MM-DD)
            $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
            $fechaNorm = ExcelCsvParser::normalizeDate($fechaRaw);
            if (!$fechaNorm || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNorm)) {
                continue;
            }

            $mapped['fecha_norm'] = $fechaNorm;
            $mapped['fecha'] = $fechaNorm;

            if ($fechaMin === null || $fechaNorm < $fechaMin) {
                $fechaMin = $fechaNorm;
            }
            if ($fechaMax === null || $fechaNorm > $fechaMax) {
                $fechaMax = $fechaNorm;
            }

            // Sanitizar cliente_id (columna obligatoria NOT NULL en BD)
            $clienteId = isset($mapped['cliente_id']) && is_numeric($mapped['cliente_id']) ? (int)$mapped['cliente_id'] : 0;
            $mapped['cliente_id'] = $clienteId;

            // Sanitizar columnas decimales
            foreach ($decimalCols as $dc) {
                if (array_key_exists($dc, $mapped)) {
                    $rawVal = $mapped[$dc];
                    if ($rawVal === null || trim((string)$rawVal) === '') {
                        $mapped[$dc] = 0.0;
                    } else {
                        $cleanVal = str_replace([' ', ','], ['', '.'], trim((string)$rawVal));
                        $mapped[$dc] = is_numeric($cleanVal) ? (float)$cleanVal : 0.0;
                    }
                }
            }

            // Coherencia monto / monto_final
            $monto = (float)($mapped['monto'] ?? 0.0);
            $montoFinal = (float)($mapped['monto_final'] ?? 0.0);
            if ($montoFinal === 0.0 && $monto > 0.0) {
                $mapped['monto_final'] = $monto;
                $montoFinal = $monto;
            } elseif ($monto === 0.0 && $montoFinal > 0.0) {
                $mapped['monto'] = $montoFinal;
            }

            $montoTotal += $montoFinal;

            // Normalizar cantidad
            $mapped['cantidad'] = isset($mapped['cantidad']) && is_numeric($mapped['cantidad']) ? (int)$mapped['cantidad'] : 0;

            // Normalizar revertida
            $rev = isset($mapped['revertida']) ? trim((string)$mapped['revertida']) : '';
            $mapped['revertida'] = in_array(strtolower($rev), ['si', 'sí', 'true', '1', 'anulada', 'revertida', 'yes'], true) ? 'Si' : 'No';

            // Normalizar hora (convierte número fraccionario de Excel tipo 0.67013888888888884 a HH:MM:SS)
            if (array_key_exists('hora', $mapped)) {
                $mapped['hora'] = ExcelCsvParser::normalizeTime($mapped['hora']);
            }

            // Derivar cliente_norm y vendedor_norm
            if (!empty($mapped['cliente']) && empty($mapped['cliente_norm'])) {
                $mapped['cliente_norm'] = mb_strtoupper(trim((string)$mapped['cliente']), 'UTF-8');
            }
            if (!empty($mapped['vendedor']) && empty($mapped['vendedor_norm'])) {
                $mapped['vendedor_norm'] = mb_strtoupper(trim((string)$mapped['vendedor']), 'UTF-8');
            }

            // Truncar strings para respetar estrictamente las longitudes VARCHAR de la base de datos
            $colLimits = [
                'hora' => 10,
                'revertida' => 10,
                'nit' => 50,
                'tipo_pago' => 50,
                'dia_visita' => 50,
                'precio_lista' => 50,
                'cluster_t' => 50,
                'cluster_c' => 50,
                'origen' => 50,
                'oficina' => 100,
                'vendedor_username' => 100,
                'distribuidor_username' => 100,
                'zona' => 100,
                'codigo' => 100,
                'categoria' => 100,
                'dp_group' => 100,
                'proveedor' => 100,
                'tipo_negocio' => 100,
                'nro_factura' => 100,
                'ruta' => 100,
                'canal' => 100,
                'tipo_objeto' => 100,
                'mercado' => 100,
                'empresa' => 100,
                'jerarquia1' => 100,
                'jerarquia2' => 100,
                'jerarquia3' => 100,
                'escala_descuento' => 100,
                'escala_producto' => 100,
                'marca' => 100,
                'cliente' => 255,
                'cliente_norm' => 255,
                'vendedor' => 255,
                'vendedor_norm' => 255,
                'distribuidor' => 255,
                'producto' => 255,
                'cuf' => 255,
                'razon_social' => 255,
                'supervisor' => 255,
                'descripcion_unificada' => 255,
            ];
            foreach ($colLimits as $colName => $maxLen) {
                if (isset($mapped[$colName]) && is_string($mapped[$colName]) && mb_strlen($mapped[$colName], 'UTF-8') > $maxLen) {
                    $mapped[$colName] = mb_substr($mapped[$colName], 0, $maxLen, 'UTF-8');
                }
            }

            // Derivar mes a partir de fecha_norm si no viene
            if (empty($mapped['mes'])) {
                $mapped['mes'] = (int)date('m', strtotime($fechaNorm));
            }

            // Asignar origen por defecto
            if (empty($mapped['origen'])) {
                $mapped['origen'] = 'VEN';
            }

            $batch[] = $mapped;

            if (count($batch) >= $batchSize) {
                $processBatch($batch);
                $batch = [];
            }
        }

        // Procesar último remanente de lote
        if (!empty($batch)) {
            $processBatch($batch);
            $batch = [];
        }

        if ($totalLeidas === 0) {
            return [
                'status' => 'success',
                'filas_leidas' => 0,
                'filas_afectadas' => 0,
                'filas_insertadas' => 0,
                'filas_nuevas' => 0,
                'nuevos' => 0,
                'filas_alteradas' => 0,
                'filas_actualizadas' => 0,
                'modificados' => 0,
                'filas_eliminadas' => 0,
                'eliminadas' => 0,
                'cancelados' => 0,
                'filas_identicas' => 0,
                'sin_cambios' => 0,
                'filas_ignoradas' => 0,
                'filas_con_discrepancia' => 0,
                'monto_total' => 0.0,
                'volumen_total' => 0.0,
                'rango_fechas' => '-',
                'fecha_operativa' => '-',
            ];
        }

        $totalAfectadas = $totalNuevas + $totalModificadas;
        $fechaMinStr = $fechaMin ?? date('Y-m-d');
        $fechaMaxStr = $fechaMax ?? date('Y-m-d');
        $rangoStr = "{$fechaMinStr} al {$fechaMaxStr}";

        // Reconectar la BD tras el streaming masivo para asegurar la sesión de Laravel
        try {
            DB::connection('supervisor')->reconnect();
            DB::reconnect();
        } catch (\Throwable) {}

        // Bitácora en sys_cargas con ID incremental seguro
        try {
            $maxId = $conn->table('sys_cargas')->max('id');
            $nextId = $maxId ? ((int)$maxId + 1) : 1;

            $conn->table('sys_cargas')->insert([
                'id' => $nextId,
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_VENTAS',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalAfectadas,
                'filas_nuevas' => $totalNuevas,
                'filas_actualizadas' => $totalModificadas,
                'filas_ignoradas' => $totalIdenticas,
                'filas_con_discrepancia' => $totalDiscrepancias,
                'rango_inicio' => $fechaMinStr,
                'rango_fin' => $fechaMaxStr,
                'fecha_ingesta' => now(),
            ]);
        } catch (\Throwable $eSys) {
            Log::warning("No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        $this->parser->clearCache();

        return [
            'status' => 'success',
            'filas_leidas' => $totalLeidas,
            'filas_afectadas' => $totalAfectadas,
            'filas_insertadas' => $totalNuevas,
            'filas_nuevas' => $totalNuevas,
            'nuevos' => $totalNuevas,
            'filas_alteradas' => $totalModificadas,
            'filas_actualizadas' => $totalModificadas,
            'modificados' => $totalModificadas,
            'filas_eliminadas' => 0,
            'eliminadas' => 0,
            'cancelados' => $totalDiscrepancias,
            'filas_identicas' => $totalIdenticas,
            'sin_cambios' => $totalIdenticas,
            'filas_ignoradas' => $totalIdenticas,
            'filas_con_discrepancia' => $totalDiscrepancias,
            'monto_total' => round($montoTotal, 2),
            'volumen_total' => round($montoTotal, 2),
            'rango_fechas' => $rangoStr,
            'fecha_operativa' => $rangoStr,
        ];
    }
}
