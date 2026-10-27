<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPreventasIngestaAction
{
    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa la ingesta de archivo de Preventas (FACT_PREVENTAS).
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'FACT_PREVENTAS');

        $rawRows = $this->parser->parseToAssociativeRows($filePath, 0, $filename);
        $totalLeidas = count($rawRows);

        if ($totalLeidas === 0) {
            return [
                'filas_leidas' => 0,
                'nuevos' => 0,
                'modificados' => 0,
                'cancelados' => 0,
                'sin_cambios' => 0,
                'volumen_total' => 0.0,
                'fecha_operativa' => '-',
                'detalles_modificados' => [],
                'detalles_cancelados' => [],
            ];
        }

        $fileHash = hash_file('sha256', $filePath) ?: md5($filename);

        $dedupMap = [];
        $fechasOperativas = [];
        $volumenTotal = 0.0;

        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'FACT_PREVENTAS');

            $nroPrev = isset($mapped['nro_preventa']) ? (int)$mapped['nro_preventa'] : 0;
            $prodId = isset($mapped['producto_id']) ? (int)$mapped['producto_id'] : 0;

            if ($nroPrev <= 0 || $prodId <= 0) {
                continue;
            }

            $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
            $fechaNorm = ExcelCsvParser::normalizeDate($fechaRaw) ?? '';
            $mapped['fecha_norm'] = $fechaNorm;
            if ($fechaNorm !== '') {
                $fechasOperativas[$fechaNorm] = true;
            }

            $monto = isset($mapped['monto_final']) ? (float)$mapped['monto_final'] : (isset($mapped['monto']) ? (float)$mapped['monto'] : 0.0);
            $mapped['monto_final'] = $monto;
            $volumenTotal += $monto;

            $mapped['cantidad'] = isset($mapped['cantidad']) ? (int)$mapped['cantidad'] : 0;

            $k = "{$nroPrev}_{$prodId}_{$fechaNorm}";
            $dedupMap[$k] = $mapped;
        }

        $fechasValidas = array_keys($fechasOperativas);
        sort($fechasValidas);

        if (empty($fechasValidas)) {
            throw new Exception("El archivo no contiene fechas operativas válidas para Preventas.");
        }

        $conn = DB::connection('supervisor');

        // Asegurar tabla auditoria_preventas_cambios
        try {
            $conn->statement("
                CREATE TABLE IF NOT EXISTS `auditoria_preventas_cambios` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `archivo_origen` VARCHAR(255) NOT NULL,
                    `tipo_evento` VARCHAR(30) NOT NULL,
                    `fecha_preventa` VARCHAR(50) NULL,
                    `nro_preventa` BIGINT NOT NULL,
                    `producto_id` BIGINT NOT NULL,
                    `cliente_id` BIGINT NULL,
                    `cliente` VARCHAR(255) NULL,
                    `vendedor` VARCHAR(255) NULL,
                    `cantidad_anterior` INT NULL,
                    `cantidad_nueva` INT NULL,
                    `diferencia_cantidad` INT NULL,
                    `monto_anterior` DECIMAL(12, 4) NULL,
                    `monto_nuevo` DECIMAL(12, 4) NULL,
                    `diferencia_monto` DECIMAL(12, 4) NULL,
                    `estado_autorizacion` VARCHAR(20) DEFAULT 'PENDIENTE',
                    `detectado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_aud_prev_key` (`nro_preventa`, `producto_id`, `fecha_preventa`),
                    INDEX `idx_aud_prev_estado` (`estado_autorizacion`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable) {}

        // Obtener columnas de fact_preventas
        try {
            $colsDb = \Illuminate\Support\Facades\Schema::connection('supervisor')->getColumnListing('fact_preventas');
        } catch (\Throwable) {
            $colsDb = [];
        }

        if (empty($colsDb)) {
            try {
                $colDbRows = $conn->select("
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fact_preventas'
                ");
                $colsDb = array_map(fn($c) => $c->COLUMN_NAME ?? $c->column_name, $colDbRows);
            } catch (\Throwable) {
                $colsDb = [];
            }
        }
        $colsDbFlip = array_flip($colsDb);

        // Consultar existentes para las fechas del corte
        $placeholders = implode(',', array_fill(0, count($fechasValidas), '?'));
        $filasDb = $conn->select("
            SELECT id, nro_preventa, producto_id, cliente_id, cliente, vendedor, 
                   cantidad, monto_final, fecha_norm, fecha
            FROM fact_preventas
            WHERE fecha_norm IN ({$placeholders})
        ", $fechasValidas);

        $existentes = [];
        foreach ($filasDb as $r) {
            $fNorm = (string)($r->fecha_norm ?? '');
            $k = "{$r->nro_preventa}_{$r->producto_id}_{$fNorm}";
            $existentes[$k] = $r;
        }

        // Consultar cancelaciones que ya se encuentren registradas como PENDIENTES
        $prevCanceladosPendientes = [];
        try {
            $prevCancRows = $conn->table('auditoria_preventas_cambios')
                ->where('tipo_evento', 'CANCELADO')
                ->where('estado_autorizacion', 'PENDIENTE')
                ->whereIn('fecha_preventa', $fechasValidas)
                ->get();
            foreach ($prevCancRows as $pcr) {
                $kCanc = "{$pcr->nro_preventa}_{$pcr->producto_id}_{$pcr->fecha_preventa}";
                $prevCanceladosPendientes[$kCanc] = true;
            }
        } catch (\Throwable) {}

        $nuevosRecords = [];
        $modificadosUpdates = [];
        $auditoriaCambios = [];
        $detallesModificados = [];
        $detallesCancelados = [];
        $clavesEntrantes = [];
        $reactivadosUpdates = [];
        $sinCambiosCount = 0;

        foreach ($dedupMap as $k => $row) {
            $clavesEntrantes[$k] = true;
            $nroPrev = (int)$row['nro_preventa'];
            $prodId = (int)$row['producto_id'];
            $fNorm = (string)($row['fecha_norm'] ?? '');
            $montoNuevo = (float)$row['monto_final'];
            $cantNueva = (int)$row['cantidad'];

            // Si el pedido estaba marcado como pendiente de cancelación y reaparece, se reactiva
            if (isset($prevCanceladosPendientes[$k])) {
                $reactivadosUpdates[] = [
                    'nro_preventa' => $nroPrev,
                    'producto_id' => $prodId,
                    'fecha_preventa' => $fNorm,
                ];
            }

            $dbRow = [];
            foreach ($row as $colName => $colVal) {
                if (isset($colsDbFlip[$colName])) {
                    $dbRow[$colName] = $colVal;
                }
            }

            if (!isset($existentes[$k])) {
                $nuevosRecords[] = $dbRow;
            } else {
                $prev = $existentes[$k];
                $montoAnt = (float)($prev->monto_final ?? 0.0);
                $cantAnt = (int)($prev->cantidad ?? 0);

                $diffMonto = $montoNuevo - $montoAnt;
                $diffCant = $cantNueva - $cantAnt;

                if (abs($diffMonto) > 0.01 || $diffCant !== 0) {
                    $cNom = (string)($row['cliente'] ?? $prev->cliente ?? '');
                    $vNom = (string)($row['vendedor'] ?? $prev->vendedor ?? '');

                    $modificadosUpdates[] = [
                        'row' => $dbRow,
                        'nro_preventa' => $nroPrev,
                        'producto_id' => $prodId,
                        'fecha_norm' => $fNorm,
                    ];

                    $auditoriaCambios[] = [
                        'archivo_origen' => $filename,
                        'tipo_evento' => 'MODIFICADO',
                        'fecha_preventa' => $fNorm,
                        'nro_preventa' => $nroPrev,
                        'producto_id' => $prodId,
                        'cliente_id' => isset($row['cliente_id']) ? (int)$row['cliente_id'] : null,
                        'cliente' => $cNom,
                        'vendedor' => $vNom,
                        'cantidad_anterior' => $cantAnt,
                        'cantidad_nueva' => $cantNueva,
                        'diferencia_cantidad' => $diffCant,
                        'monto_anterior' => $montoAnt,
                        'monto_nuevo' => $montoNuevo,
                        'diferencia_monto' => $diffMonto,
                        'estado_autorizacion' => 'REGISTRADO',
                    ];

                    $detallesModificados[] = [
                        'nro_preventa' => $nroPrev,
                        'producto_id' => $prodId,
                        'cliente' => $cNom,
                        'vendedor' => $vNom,
                        'cant_ant' => $cantAnt,
                        'cant_nueva' => $cantNueva,
                        'monto_ant' => $montoAnt,
                        'monto_nuevo' => $montoNuevo,
                        'diff_monto' => $diffMonto,
                    ];
                } else {
                    $sinCambiosCount++;
                }
            }
        }

        // Cancelaciones detectadas (en BD pero ausentes en este nuevo corte)
        $clavesCanceladas = [];
        foreach ($existentes as $k => $prev) {
            if (!isset($clavesEntrantes[$k])) {
                $clavesCanceladas[] = $prev;
                $cantAnt = (int)($prev->cantidad ?? 0);
                $montoAnt = (float)($prev->monto_final ?? 0.0);
                $fReg = (string)($prev->fecha_norm ?? '');
                $cNom = (string)($prev->cliente ?? '');
                $vNom = (string)($prev->vendedor ?? '');

                // Solo registrar en auditoría si no estaba ya pendiente de autorización
                if (!isset($prevCanceladosPendientes[$k])) {
                    $auditoriaCambios[] = [
                        'archivo_origen' => $filename,
                        'tipo_evento' => 'CANCELADO',
                        'fecha_preventa' => $fReg,
                        'nro_preventa' => (int)$prev->nro_preventa,
                        'producto_id' => (int)$prev->producto_id,
                        'cliente_id' => isset($prev->cliente_id) ? (int)$prev->cliente_id : null,
                        'cliente' => $cNom,
                        'vendedor' => $vNom,
                        'cantidad_anterior' => $cantAnt,
                        'cantidad_nueva' => 0,
                        'diferencia_cantidad' => -$cantAnt,
                        'monto_anterior' => $montoAnt,
                        'monto_nuevo' => 0.0,
                        'diferencia_monto' => -$montoAnt,
                        'estado_autorizacion' => 'PENDIENTE',
                    ];
                }

                $detallesCancelados[] = [
                    'nro_preventa' => (int)$prev->nro_preventa,
                    'producto_id' => (int)$prev->producto_id,
                    'cliente' => $cNom,
                    'vendedor' => $vNom,
                    'cant_ant' => $cantAnt,
                    'monto_ant' => $montoAnt,
                ];
            }
        }

        $chunkSize = 300;

        // Operaciones dentro de Transacción Atómica
        $conn->transaction(function () use ($conn, $nuevosRecords, $modificadosUpdates, $reactivadosUpdates, $auditoriaCambios, $chunkSize) {
            // 1. Inserción de Nuevos
            if (!empty($nuevosRecords)) {
                $chunks = array_chunk($nuevosRecords, $chunkSize);
                foreach ($chunks as $chunk) {
                    $conn->table('fact_preventas')->insert($chunk);
                }
            }

            // 2. Actualización de Modificados
            foreach ($modificadosUpdates as $item) {
                $updateData = $item['row'];
                unset($updateData['nro_preventa'], $updateData['producto_id'], $updateData['fecha_norm']);
                $conn->table('fact_preventas')
                    ->where('nro_preventa', $item['nro_preventa'])
                    ->where('producto_id', $item['producto_id'])
                    ->where('fecha_norm', $item['fecha_norm'])
                    ->update($updateData);
            }

            // 3. Reactivación de cancelados que volvieron a aparecer en el nuevo corte
            foreach ($reactivadosUpdates as $react) {
                $conn->table('auditoria_preventas_cambios')
                    ->where('nro_preventa', $react['nro_preventa'])
                    ->where('producto_id', $react['producto_id'])
                    ->where('fecha_preventa', $react['fecha_preventa'])
                    ->where('estado_autorizacion', 'PENDIENTE')
                    ->update(['estado_autorizacion' => 'REACTIVADO']);
            }

            // 4. Bitácora de Auditoría (Cancelaciones pendientes y modificaciones)
            // NOTA: Los pedidos cancelados NO se purgan inmediatamente de fact_preventas;
            // quedan en CUARENTENA como PENDIENTE para que el supervisor los revise y autorice
            // formalmente en la vista Cancelados.vue antes de su descarte definitivo.
            if (!empty($auditoriaCambios)) {
                $chunksAud = array_chunk($auditoriaCambios, $chunkSize);
                foreach ($chunksAud as $chunk) {
                    $conn->table('auditoria_preventas_cambios')->insert($chunk);
                }
            }
        });

        // 5. Registro en sys_cargas
        $rangoIni = $fechasValidas[0] ?? '-';
        $rangoFin = end($fechasValidas) ?: '-';
        $totalAfectadas = count($nuevosRecords) + count($modificadosUpdates) + count($clavesCanceladas);

        try {
            $conn->table('sys_cargas')->insert([
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_PREVENTAS',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalAfectadas,
                'filas_nuevas' => count($nuevosRecords),
                'filas_actualizadas' => count($modificadosUpdates),
                'filas_ignoradas' => $sinCambiosCount,
                'filas_con_discrepancia' => count($clavesCanceladas),
                'rango_inicio' => $rangoIni,
                'rango_fin' => $rangoFin,
                'fecha_ingesta' => now(),
            ]);
        } catch (\Throwable $eSys) {
            \Illuminate\Support\Facades\Log::warning("No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        $rangoStr = implode(', ', $fechasValidas);

        return [
            'status' => 'success',
            'filas_leidas' => $totalLeidas,
            'nuevos' => count($nuevosRecords),
            'filas_nuevas' => count($nuevosRecords),
            'modificados' => count($modificadosUpdates),
            'filas_actualizadas' => count($modificadosUpdates),
            'cancelados' => count($clavesCanceladas),
            'filas_con_discrepancia' => count($clavesCanceladas),
            'sin_cambios' => $sinCambiosCount,
            'filas_ignoradas' => $sinCambiosCount,
            'volumen_total' => round($volumenTotal, 2),
            'fecha_operativa' => $rangoStr,
            'rango_fechas' => $rangoStr,
            'detalles_modificados' => $detallesModificados,
            'detalles_cancelados' => $detallesCancelados,
        ];
    }
}

