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
     * Procesa la ingesta de archivo de Ventas (FACT_VENTAS).
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'FACT_VENTAS');

        $rawRows = $this->parser->parseToAssociativeRows($filePath, 0, $filename);
        $totalLeidas = count($rawRows);

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

        $fileHash = hash_file('sha256', $filePath) ?: md5($filename);

        // Mapeo, sanitización y deduplicación intra-archivo
        $dedupMap = [];
        $fechasOperativas = [];

        $decimalCols = [
            'monto', 'monto_final', 'descuento', 'descuento2', 'descuento_socio',
            'descuento_cliente', 'descuento_grupo', 'descuento_volumen',
            'cantidad_cajas', 'precio_unitario', 'precio_paquete'
        ];

        foreach ($rawRows as $r) {
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
            $fechasOperativas[$fechaNorm] = true;

            // Sanitizar cliente_id (columna obligatoria NOT NULL en BD)
            $clienteId = isset($mapped['cliente_id']) && is_numeric($mapped['cliente_id']) ? (int)$mapped['cliente_id'] : 0;
            $mapped['cliente_id'] = $clienteId;

            // Sanitizar columnas decimales (evitar error 1366 Incorrect decimal value: '' en MySQL strict mode)
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

            // Garantizar coherencia entre monto y monto_final si alguno no viene especificado
            $monto = (float)($mapped['monto'] ?? 0.0);
            $montoFinal = (float)($mapped['monto_final'] ?? 0.0);
            if ($montoFinal === 0.0 && $monto > 0.0) {
                $mapped['monto_final'] = $monto;
            } elseif ($monto === 0.0 && $montoFinal > 0.0) {
                $mapped['monto'] = $montoFinal;
            }

            // Normalizar cantidad
            $mapped['cantidad'] = isset($mapped['cantidad']) && is_numeric($mapped['cantidad']) ? (int)$mapped['cantidad'] : 0;

            // Normalizar revertida
            $rev = isset($mapped['revertida']) ? trim((string)$mapped['revertida']) : '';
            $mapped['revertida'] = in_array(strtolower($rev), ['si', 'sí', 'true', '1', 'anulada', 'revertida', 'yes'], true) ? 'Si' : 'No';

            // Derivar cliente_norm y vendedor_norm si no vienen
            if (!empty($mapped['cliente']) && empty($mapped['cliente_norm'])) {
                $mapped['cliente_norm'] = mb_strtoupper(trim((string)$mapped['cliente']), 'UTF-8');
            }
            if (!empty($mapped['vendedor']) && empty($mapped['vendedor_norm'])) {
                $mapped['vendedor_norm'] = mb_strtoupper(trim((string)$mapped['vendedor']), 'UTF-8');
            }

            // Derivar mes a partir de fecha_norm si no viene
            if (empty($mapped['mes'])) {
                $mapped['mes'] = (int)date('m', strtotime($fechaNorm));
            }

            // Asignar origen por defecto
            if (empty($mapped['origen'])) {
                $mapped['origen'] = 'VEN';
            }

            $k = "{$ventaId}_{$productoId}";
            $dedupMap[$k] = $mapped;
        }

        $fechasValidas = array_keys($fechasOperativas);
        sort($fechasValidas);

        if (empty($fechasValidas)) {
            throw new Exception("El archivo no contiene registros con fechas y claves válidas (ventaId, productoId, fecha).");
        }

        // Calcular monto total exacto deduplicado
        $montoTotal = 0.0;
        foreach ($dedupMap as $item) {
            $montoTotal += (float)($item['monto_final'] ?? 0.0);
        }

        $fechaMin = $fechasValidas[0];
        $fechaMax = end($fechasValidas);

        $conn = DB::connection('supervisor');

        // Asegurar tabla auditoria_ventas_discrepancias sin ejecutar DDL innecesario en cada ingesta
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

        // 1. Consultar registros existentes en BD para las fechas en batches de 100
        // (Aprovecha idx_fv_fecha_norm y evita traer cientos de miles de registros de fechas no presentes)
        $filasDb = [];
        $chunksFechas = array_chunk($fechasValidas, 100);
        foreach ($chunksFechas as $chunkF) {
            $placeholders = implode(',', array_fill(0, count($chunkF), '?'));
            $rowsChunk = $conn->select("
                SELECT id, venta_id, producto_id, monto_final, cantidad, revertida, fecha_norm, cliente_id
                FROM fact_ventas
                WHERE fecha_norm IN ({$placeholders})
            ", $chunkF);
            foreach ($rowsChunk as $r) {
                $filasDb[] = $r;
            }
        }

        $existentes = [];
        foreach ($filasDb as $r) {
            $k = "{$r->venta_id}_{$r->producto_id}";
            $existentes[$k] = $r;
        }

        $nuevasRecords = [];
        $modificadasUpdates = [];
        $alteradasAuditoria = [];
        $identicasCount = 0;
        $clavesArchivo = [];

        // 2. Clasificar filas entrantes
        foreach ($dedupMap as $k => $row) {
            $clavesArchivo[$k] = true;
            $vid = (int)$row['venta_id'];
            $pid = (int)$row['producto_id'];
            $montoNuevo = (float)($row['monto_final'] ?? 0.0);
            $cantNueva = (int)($row['cantidad'] ?? 0);
            $revNueva = (string)($row['revertida'] ?? 'No');

            // Filtrar solo columnas presentes en la tabla fact_ventas
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
                        'venta_id' => $vid,
                        'producto_id' => $pid,
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
                    $identicasCount++;
                }
            }
        }

        // 3. Normalizar estructura de columnas para inserción en chunks
        // Evita el error "Column count doesn't match value count at row X" y desalineación de columnas en Laravel PDO
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

        // 4. Operaciones en Transacción Atómica
        $chunkSize = 300;
        $conn->transaction(function () use ($conn, $nuevasRecords, $modificadasUpdates, $alteradasAuditoria, $chunkSize) {
            // A. Inserción de Nuevos en Chunks
            if (!empty($nuevasRecords)) {
                $chunks = array_chunk($nuevasRecords, $chunkSize);
                foreach ($chunks as $chunk) {
                    $conn->table('fact_ventas')->insert($chunk);
                }
            }

            // B. Actualización explícita de Modificados por ID Primario
            foreach ($modificadasUpdates as $item) {
                $updateData = $item['row'];
                unset($updateData['id'], $updateData['venta_id'], $updateData['producto_id']);
                if (!empty($updateData)) {
                    $conn->table('fact_ventas')
                        ->where('id', $item['id'])
                        ->update($updateData);
                }
            }

            // C. Insertar auditoría de discrepancias
            if (!empty($alteradasAuditoria)) {
                $chunksAud = array_chunk($alteradasAuditoria, $chunkSize);
                foreach ($chunksAud as $chunk) {
                    $conn->table('auditoria_ventas_discrepancias')->insert($chunk);
                }
            }
        });

        // 5. Bitácora en sys_cargas con ID incremental seguro
        $totalAfectadas = count($nuevasRecords) + count($modificadasUpdates);
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
                'filas_nuevas' => count($nuevasRecords),
                'filas_actualizadas' => count($modificadasUpdates),
                'filas_ignoradas' => $identicasCount,
                'filas_con_discrepancia' => count($alteradasAuditoria),
                'rango_inicio' => $fechaMin,
                'rango_fin' => $fechaMax,
                'fecha_ingesta' => now(),
            ]);
        } catch (\Throwable $eSys) {
            Log::warning("No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        $rangoStr = "{$fechaMin} al {$fechaMax}";

        return [
            'status' => 'success',
            'filas_leidas' => $totalLeidas,
            'filas_afectadas' => $totalAfectadas,
            'filas_insertadas' => count($nuevasRecords),
            'filas_nuevas' => count($nuevasRecords),
            'nuevos' => count($nuevasRecords),
            'filas_alteradas' => count($modificadasUpdates),
            'filas_actualizadas' => count($modificadasUpdates),
            'modificados' => count($modificadasUpdates),
            'filas_eliminadas' => 0,
            'eliminadas' => 0,
            'cancelados' => count($alteradasAuditoria),
            'filas_identicas' => $identicasCount,
            'sin_cambios' => $identicasCount,
            'filas_ignoradas' => $identicasCount,
            'filas_con_discrepancia' => count($alteradasAuditoria),
            'monto_total' => round($montoTotal, 2),
            'volumen_total' => round($montoTotal, 2),
            'rango_fechas' => $rangoStr,
            'fecha_operativa' => $rangoStr,
        ];
    }
}
