<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessInventarioIngestaAction
{
    public function __construct(
        protected ExcelCsvParser $parser
    ) {}

    /**
     * Procesa la ingesta de archivo de Stock / Inventario.
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $rawRows = $this->parser->parseToAssociativeRows($filePath);
        if (empty($rawRows)) {
            throw new Exception("El archivo de inventario está vacío.");
        }

        // Inspeccionar cabeceras para mapeo flexible
        $firstRow = $rawRows[0];
        $colMap = [];
        foreach (array_keys($firstRow) as $col) {
            $colLower = strtolower(trim((string)$col));
            if (str_contains($colLower, 'almacen') || str_contains($colLower, 'almacén')) {
                $colMap['almacen'] = $col;
            } elseif (str_contains($colLower, 'producto') || str_contains($colLower, 'articulo') || str_contains($colLower, 'artículo') || str_contains($colLower, 'descrip')) {
                $colMap['producto'] = $col;
            } elseif (str_contains($colLower, 'unitario') || str_contains($colLower, 'unidad')) {
                $colMap['unitario'] = $col;
            } elseif (str_contains($colLower, 'paquete') || str_contains($colLower, 'bulto') || str_contains($colLower, 'caja')) {
                $colMap['paquete'] = $col;
            } elseif (str_contains($colLower, 'lote')) {
                $colMap['lote'] = $col;
            }
        }

        if (empty($colMap['producto'])) {
            throw new Exception("El archivo no contiene la columna obligatoria 'Producto' o 'Descripción'.");
        }

        $loteCargaId = (string)Str::uuid();
        $dedupStock = [];

        foreach ($rawRows as $r) {
            $prodRaw = $r[$colMap['producto']] ?? '';
            $prod = trim((string)$prodRaw);
            if ($prod === '') {
                continue;
            }

            $almacenRaw = isset($colMap['almacen']) ? ($r[$colMap['almacen']] ?? '') : '';
            $almacen = trim((string)$almacenRaw) ?: 'CENTRAL';

            $saldoU = isset($colMap['unitario']) && isset($r[$colMap['unitario']]) ? (int)round((float)$r[$colMap['unitario']]) : 0;
            $saldoP = isset($colMap['paquete']) && isset($r[$colMap['paquete']]) ? (int)round((float)$r[$colMap['paquete']]) : 0;

            $loteRaw = isset($colMap['lote']) ? ($r[$colMap['lote']] ?? '') : '';
            $lote = trim((string)$loteRaw) ?: '1';

            $prodNorm = mb_strtolower($prod, 'UTF-8');
            $kStock = "{$almacen}_{$prodNorm}_{$lote}";

            // Consolidación de saldos intra-archivo para evitar registros duplicados
            if (isset($dedupStock[$kStock])) {
                $dedupStock[$kStock]['saldo_unitario'] += $saldoU;
                $dedupStock[$kStock]['saldo_paquete'] += $saldoP;
            } else {
                $dedupStock[$kStock] = [
                    'almacen' => $almacen,
                    'producto' => $prod,
                    'producto_norm' => $prodNorm,
                    'saldo_unitario' => $saldoU,
                    'saldo_paquete' => $saldoP,
                    'lote' => $lote,
                    'archivo_origen' => $filename,
                ];
            }
        }

        $filasActual = array_values($dedupStock);

        if (empty($filasActual)) {
            throw new Exception("No se encontraron filas con productos válidos para procesar en el inventario.");
        }

        $filasSnapshots = array_map(function ($row) use ($loteCargaId) {
            return array_merge(['lote_carga_id' => $loteCargaId], $row);
        }, $filasActual);

        $conn = DB::connection('supervisor');

        // Asegurar tablas de stock
        $this->asegurarTablas($conn);

        $chunkSize = 300;

        // Transacción Atómica para Snapshot y Reemplazo de Stock Vivo
        $conn->transaction(function () use ($conn, $filasSnapshots, $filasActual, $chunkSize) {
            // 1. Guardar Snapshot Inmutable
            $chunksSnap = array_chunk($filasSnapshots, $chunkSize);
            foreach ($chunksSnap as $chunk) {
                $conn->table('inv_stock_snapshots')->insert($chunk);
            }

            // 2. Reemplazo completo de la tabla viva
            $conn->table('inv_stock_actual')->delete();

            $chunksActual = array_chunk($filasActual, $chunkSize);
            foreach ($chunksActual as $chunk) {
                $conn->table('inv_stock_actual')->insert($chunk);
            }
        });

        // 3. Bitácora en sys_cargas
        $totalProcesados = count($filasActual);
        $conn->table('sys_cargas')->insert([
            'archivo_original' => $filename,
            'archivo_almacenado' => $filePath,
            'sha256' => $loteCargaId,
            'tipo_entidad' => 'INVENTARIO',
            'filas_leidas' => $totalProcesados,
            'filas_afectadas' => $totalProcesados,
            'filas_nuevas' => $totalProcesados,
            'filas_actualizadas' => 0,
            'filas_ignoradas' => 0,
            'filas_con_discrepancia' => 0,
            'rango_inicio' => '-',
            'rango_fin' => '-',
            'fecha_ingesta' => now(),
        ]);

        return [
            'status' => 'success',
            'lote_carga_id' => $loteCargaId,
            'registros_procesados' => $totalProcesados,
            'filas_leidas' => $totalProcesados,
            'filas_afectadas' => $totalProcesados,
            'filas_nuevas' => $totalProcesados,
            'nuevos' => $totalProcesados,
            'modificados' => 0,
            'filas_actualizadas' => 0,
            'filas_con_discrepancia' => 0,
            'cancelados' => 0,
            'filas_ignoradas' => 0,
            'sin_cambios' => 0,
            'archivo' => $filename,
            'fecha_registrada' => now()->toDateTimeString(),
        ];
    }

    protected function asegurarTablas($conn): void
    {
        $conn->statement("
            CREATE TABLE IF NOT EXISTS `inv_stock_actual` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `almacen` VARCHAR(100) NOT NULL DEFAULT 'CENTRAL',
                `producto` VARCHAR(255) NOT NULL,
                `producto_norm` VARCHAR(255) NOT NULL,
                `saldo_unitario` INT DEFAULT 0,
                `saldo_paquete` INT DEFAULT 0,
                `lote` VARCHAR(100) DEFAULT '1',
                `archivo_origen` VARCHAR(255) NULL,
                `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_isa_prod` (`producto_norm`),
                INDEX `idx_isa_alm` (`almacen`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `inv_stock_snapshots` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `lote_carga_id` VARCHAR(50) NOT NULL,
                `almacen` VARCHAR(100) NOT NULL,
                `producto` VARCHAR(255) NOT NULL,
                `producto_norm` VARCHAR(255) NOT NULL,
                `saldo_unitario` INT DEFAULT 0,
                `saldo_paquete` INT DEFAULT 0,
                `lote` VARCHAR(100) DEFAULT '1',
                `archivo_origen` VARCHAR(255) NULL,
                `fecha_snapshot` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_iss_lote` (`lote_carga_id`),
                INDEX `idx_iss_prod` (`producto_norm`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

