<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessRuteoIngestaAction
{
    public const COLUMNAS_AUDITORIA = [
        'nodo_id', 'nodo', 'cliente', 'vendedor', 'tipo_negocio', 'territorio',
        'mercado', 'fecha_servidor', 'fecha_dispositivo', 'contacto', 'ci',
        'telefono', 'celular', 'direccion', 'nro', 'referencia', 'nombre_factura',
        'nit', 'tipo_pago', 'latitud', 'longitud', 'intensidad', 'estado',
        'zona_venta', 'cod_vendedor', 'dias_credito', 'limite', 'ruta', 'dia',
        'precio', 'cluster_t', 'cluster_c', 'cliente_norm', 'vendedor_norm', 'dia_norm',
    ];

    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa el reemplazo maestro de Ruteo (PAN_RUTEO) con snapshot inmutable y auditoría de eventos.
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null, ?string $fechaArchivo = null): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'PAN_RUTEO');

        $rawRows = $this->parser->parseToAssociativeRows($filePath, 0, $filename);
        $totalLeidas = count($rawRows);

        if ($totalLeidas === 0) {
            throw new Exception("El archivo de ruteo está vacío.");
        }

        $snapshotId = 'snap_' . substr(bin2hex(random_bytes(8)), 0, 12);

        $fechaCustom = null;
        if (!empty($fechaArchivo)) {
            $fTrim = trim($fechaArchivo);
            if (strlen($fTrim) === 10 && $fTrim[4] === '-' && $fTrim[7] === '-') {
                $fechaCustom = "{$fTrim} 12:00:00";
            } else {
                $fechaCustom = $fTrim;
            }
        }

        $clientesEnArchivo = [];
        $registrosSnapshot = [];
        $registrosVivos = [];
        $visitasUnicasArchivo = [];

        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'PAN_RUTEO');

            $cid = isset($mapped['cliente_id']) ? (int)$mapped['cliente_id'] : 0;
            if ($cid <= 0) {
                continue;
            }

            $diaRaw = (string)($mapped['dia_norm'] ?? $mapped['dia'] ?? '');
            $diaNorm = IngestaValidator::sanitizeDia($diaRaw);
            $mapped['dia_norm'] = $diaNorm;
            $mapped['cliente_id'] = $cid;

            // Deduplicación intra-archivo: evitar filas repetidas idénticas para (cliente_id, dia_norm, ruta)
            $rutaNorm = strtoupper(trim((string)($mapped['ruta'] ?? '')));
            $claveVisita = "{$cid}_{$diaNorm}_{$rutaNorm}";
            if (isset($visitasUnicasArchivo[$claveVisita])) {
                continue;
            }
            $visitasUnicasArchivo[$claveVisita] = true;

            if (!isset($clientesEnArchivo[$cid])) {
                $clientesEnArchivo[$cid] = [];
            }
            $clientesEnArchivo[$cid][] = $mapped;

            $snapRow = [
                'snapshot_id' => $snapshotId,
                'archivo_origen' => $filename,
                'cliente_id' => $cid,
            ];
            if ($fechaCustom) {
                $snapRow['fecha_carga'] = $fechaCustom;
            }

            $vivoRow = ['cliente_id' => $cid];

            foreach (self::COLUMNAS_AUDITORIA as $col) {
                $val = $mapped[$col] ?? null;
                $snapRow[$col] = $val;
                $vivoRow[$col] = $val;
            }

            $registrosSnapshot[] = $snapRow;
            $registrosVivos[] = $vivoRow;
        }

        $conn = DB::connection('supervisor');

        // Asegurar tablas pan_ruteo_snapshots y auditoria_ruteo_cambios
        $this->asegurarTablas($conn);

        // Columnas existentes en pan_ruteo y pan_ruteo_snapshots
        try {
            $colsSnapDb = array_flip(\Illuminate\Support\Facades\Schema::connection('supervisor')->getColumnListing('pan_ruteo_snapshots'));
            $colsVivoDb = array_flip(\Illuminate\Support\Facades\Schema::connection('supervisor')->getColumnListing('pan_ruteo'));
        } catch (\Throwable) {
            $colsSnapDb = [];
            $colsVivoDb = [];
        }

        // Consultar clientes existentes en pan_ruteo
        $filasBd = $conn->select("
            SELECT cliente_id, dia_norm, dia, ruta, estado, cliente, vendedor, cod_vendedor,
                   nodo_id, nodo, tipo_negocio, territorio, mercado, latitud, longitud
            FROM pan_ruteo
        ");

        $clientesEnBd = [];
        foreach ($filasBd as $r) {
            $cid = (int)$r->cliente_id;
            if ($cid <= 0) continue;
            if (!isset($clientesEnBd[$cid])) {
                $clientesEnBd[$cid] = [];
            }
            $clientesEnBd[$cid][] = (array)$r;
        }

        $cidsArchivo = array_keys($clientesEnArchivo);
        $cidsBd = array_keys($clientesEnBd);

        $setArch = array_flip($cidsArchivo);
        $setBd = array_flip($cidsBd);

        $registrosAuditoria = [];
        $altasCnt = 0;
        $modificadosCnt = 0;
        $eliminadosCnt = 0;
        $sinCambiosCnt = 0;

        // 1. Altas Totales (Nuevos)
        foreach ($cidsArchivo as $cid) {
            if (!isset($setBd[$cid])) {
                $altasCnt++;
                $primer = $clientesEnArchivo[$cid][0];
                $registrosAuditoria[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => $primer['cliente'] ?? '',
                    'tipo_evento' => 'NUEVO',
                    'campo_afectado' => 'cliente_id',
                    'valor_anterior' => null,
                    'valor_nuevo' => (string)($primer['ruta'] ?? 'SIN RUTA'),
                    'fecha_auditoria' => $fechaCustom ?: now()->toDateTimeString(),
                ];
            }
        }

        // 2. Bajas Totales (Eliminados)
        foreach ($cidsBd as $cid) {
            if (!isset($setArch[$cid])) {
                $eliminadosCnt++;
                $primerBd = $clientesEnBd[$cid][0];
                $registrosAuditoria[] = [
                    'archivo_origen' => $filename,
                    'cliente_id' => $cid,
                    'cliente' => $primerBd['cliente'] ?? '',
                    'tipo_evento' => 'ELIMINADO',
                    'campo_afectado' => 'cliente_id',
                    'valor_anterior' => (string)($primerBd['ruta'] ?? 'SIN RUTA'),
                    'valor_nuevo' => 'Desaparecido de Ruteo',
                    'fecha_auditoria' => $fechaCustom ?: now()->toDateTimeString(),
                ];
            }
        }

        // 3. Modificaciones en clientes existentes en ambos
        foreach ($cidsArchivo as $cid) {
            if (isset($setBd[$cid])) {
                $regsArch = $clientesEnArchivo[$cid];
                $regsBd = $clientesEnBd[$cid];

                $clienteNom = $regsArch[0]['cliente'] ?? ($regsBd[0]['cliente'] ?? '');
                $huboCambio = false;

                // Auditoría de Días
                $diasArch = [];
                foreach ($regsArch as $ra) {
                    $d = IngestaValidator::sanitizeDia($ra['dia_norm'] ?? $ra['dia'] ?? '');
                    if ($d !== '' && $d !== 'sin dia') $diasArch[$d] = true;
                }
                $diasBd = [];
                foreach ($regsBd as $rb) {
                    $d = IngestaValidator::sanitizeDia($rb['dia_norm'] ?? $rb['dia'] ?? '');
                    if ($d !== '' && $d !== 'sin dia') $diasBd[$d] = true;
                }

                $keysArch = array_keys($diasArch);
                $keysBd = array_keys($diasBd);
                sort($keysArch);
                sort($keysBd);

                if ($keysArch !== $keysBd) {
                    $huboCambio = true;
                    $registrosAuditoria[] = [
                        'archivo_origen' => $filename,
                        'cliente_id' => $cid,
                        'cliente' => $clienteNom,
                        'tipo_evento' => 'MODIFICADO_DIA',
                        'campo_afectado' => 'dia',
                        'valor_anterior' => implode(', ', $keysBd) ?: 'Sin Día',
                        'valor_nuevo' => implode(', ', $keysArch) ?: 'Sin Día',
                        'fecha_auditoria' => $fechaCustom ?: now()->toDateTimeString(),
                    ];
                }

                // Auditoría de Rutas
                $rutasArch = [];
                foreach ($regsArch as $ra) {
                    $rt = strtoupper(trim((string)($ra['ruta'] ?? '')));
                    if ($rt !== '' && $rt !== 'SIN RUTA') $rutasArch[$rt] = true;
                }
                $rutasBd = [];
                foreach ($regsBd as $rb) {
                    $rt = strtoupper(trim((string)($rb['ruta'] ?? '')));
                    if ($rt !== '' && $rt !== 'SIN RUTA') $rutasBd[$rt] = true;
                }

                $kRutasArch = array_keys($rutasArch);
                $kRutasBd = array_keys($rutasBd);
                sort($kRutasArch);
                sort($kRutasBd);

                if ($kRutasArch !== $kRutasBd) {
                    $huboCambio = true;
                    $registrosAuditoria[] = [
                        'archivo_origen' => $filename,
                        'cliente_id' => $cid,
                        'cliente' => $clienteNom,
                        'tipo_evento' => 'MODIFICADO_RUTA',
                        'campo_afectado' => 'ruta',
                        'valor_anterior' => implode(', ', $kRutasBd) ?: 'Sin Ruta',
                        'valor_nuevo' => implode(', ', $kRutasArch) ?: 'Sin Ruta',
                        'fecha_auditoria' => $fechaCustom ?: now()->toDateTimeString(),
                    ];
                }

                // Auditoría de Estado
                $stAnt = trim((string)($regsBd[0]['estado'] ?? ''));
                $stNuev = trim((string)($regsArch[0]['estado'] ?? ''));
                if (strtolower($stAnt) !== strtolower($stNuev) && $stAnt !== '' && $stNuev !== '') {
                    $huboCambio = true;
                    $tipoEv = 'MODIFICADO_ESTADO';
                    if (in_array(strtolower($stNuev), ['inactivo', 'baja', 'anulado'], true)) {
                        $tipoEv = 'INACTIVADO';
                    } elseif (in_array(strtolower($stNuev), ['activo', 'habilitado', 'normal'], true)) {
                        $tipoEv = 'REACTIVADO';
                    }

                    $registrosAuditoria[] = [
                        'archivo_origen' => $filename,
                        'cliente_id' => $cid,
                        'cliente' => $clienteNom,
                        'tipo_evento' => $tipoEv,
                        'campo_afectado' => 'estado',
                        'valor_anterior' => $stAnt,
                        'valor_nuevo' => $stNuev,
                        'fecha_auditoria' => $fechaCustom ?: now()->toDateTimeString(),
                    ];
                }

                if ($huboCambio) {
                    $modificadosCnt++;
                } else {
                    $sinCambiosCnt++;
                }
            }
        }

        $chunkSize = 300;

        // 1. Guardar Snapshot Inmutable
        if (!empty($registrosSnapshot)) {
            if (!empty($colsSnapDb)) {
                $registrosSnapshot = array_map(function ($row) use ($colsSnapDb) {
                    return array_intersect_key($row, $colsSnapDb);
                }, $registrosSnapshot);
            }
            $chunksSnap = array_chunk($registrosSnapshot, $chunkSize);
            foreach ($chunksSnap as $chunk) {
                $conn->table('pan_ruteo_snapshots')->insert($chunk);
            }
        }

        // 2. Guardar Auditoría
        if (!empty($registrosAuditoria)) {
            $chunksAud = array_chunk($registrosAuditoria, $chunkSize);
            foreach ($chunksAud as $chunk) {
                $conn->table('auditoria_ruteo_cambios')->insert($chunk);
            }
        }

        // 3. Reemplazo Atómico Transaccional de pan_ruteo
        // Se ejecuta dentro de una transacción para garantizar que si ocurre algún fallo
        // durante la inserción en chunks, el padrón anterior se restaura íntegro.
        if (!empty($colsVivoDb)) {
            $registrosVivos = array_map(function ($row) use ($colsVivoDb) {
                return array_intersect_key($row, $colsVivoDb);
            }, $registrosVivos);
        }

        $conn->transaction(function () use ($conn, $registrosVivos, $chunkSize) {
            $conn->table('pan_ruteo')->delete();

            if (!empty($registrosVivos)) {
                $chunksVivos = array_chunk($registrosVivos, $chunkSize);
                foreach ($chunksVivos as $chunk) {
                    $conn->table('pan_ruteo')->insert($chunk);
                }
            }
        });

        // 4. Registrar en sys_cargas
        $totalVivos = count($registrosVivos);
        try {
            $conn->table('sys_cargas')->insert([
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $snapshotId,
                'tipo_entidad' => 'PAN_RUTEO',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalVivos,
                'filas_nuevas' => $altasCnt,
                'filas_actualizadas' => $modificadosCnt,
                'filas_ignoradas' => $sinCambiosCnt,
                'filas_con_discrepancia' => $eliminadosCnt,
                'rango_inicio' => '-',
                'rango_fin' => '-',
                'fecha_ingesta' => $fechaCustom ?: now(),
            ]);
        } catch (\Throwable $eSys) {
            \Illuminate\Support\Facades\Log::warning("No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        return [
            'status' => 'success',
            'archivo' => $filename,
            'snapshot_id' => $snapshotId,
            'fecha_registrada' => $fechaCustom ?: 'Hoy (Automático)',
            'total_registros_auditoria' => count($registrosAuditoria),
            'filas_leidas' => $totalLeidas,
            'filas_afectadas' => $totalVivos,
            'filas_nuevas' => $altasCnt,
            'nuevos' => $altasCnt,
            'filas_actualizadas' => $modificadosCnt,
            'modificados' => $modificadosCnt,
            'filas_con_discrepancia' => $eliminadosCnt,
            'cancelados' => $eliminadosCnt,
            'eliminadas' => $eliminadosCnt,
            'filas_ignoradas' => $sinCambiosCnt,
            'sin_cambios' => $sinCambiosCnt,
            'metricas' => [
                'total_filas_archivo' => $totalLeidas,
                'clientes_unicos_archivo' => count($clientesEnArchivo),
                'total_bd_anterior' => count($filasBd),
                'total_nuevos' => $altasCnt,
                'total_modificados' => $modificadosCnt,
                'total_eliminados' => $eliminadosCnt,
                'total_sin_cambios' => $sinCambiosCnt,
            ],
        ];
    }

    protected function asegurarTablas($conn): void
    {
        try {
            $conn->statement("
                CREATE TABLE IF NOT EXISTS `pan_ruteo_snapshots` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `snapshot_id` VARCHAR(50) NOT NULL,
                    `archivo_origen` VARCHAR(255) NULL,
                    `fecha_carga` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `cliente_id` BIGINT NOT NULL,
                    `nodo_id` VARCHAR(50) NULL,
                    `nodo` VARCHAR(150) NULL,
                    `cliente` VARCHAR(255) NULL,
                    `vendedor` VARCHAR(255) NULL,
                    `tipo_negocio` VARCHAR(100) NULL,
                    `territorio` VARCHAR(100) NULL,
                    `mercado` VARCHAR(100) NULL,
                    `fecha_servidor` VARCHAR(50) NULL,
                    `fecha_dispositivo` VARCHAR(50) NULL,
                    `contacto` VARCHAR(150) NULL,
                    `ci` VARCHAR(50) NULL,
                    `telefono` VARCHAR(50) NULL,
                    `celular` VARCHAR(50) NULL,
                    `direccion` TEXT NULL,
                    `nro` VARCHAR(50) NULL,
                    `referencia` TEXT NULL,
                    `nombre_factura` VARCHAR(255) NULL,
                    `nit` VARCHAR(50) NULL,
                    `tipo_pago` VARCHAR(50) NULL,
                    `latitud` DECIMAL(12, 8) NULL,
                    `longitud` DECIMAL(12, 8) NULL,
                    `intensidad` VARCHAR(50) NULL,
                    `estado` VARCHAR(50) NULL,
                    `zona_venta` VARCHAR(100) NULL,
                    `cod_vendedor` VARCHAR(50) NULL,
                    `dias_credito` VARCHAR(50) NULL,
                    `limite` DECIMAL(12, 2) NULL,
                    `ruta` VARCHAR(50) NULL,
                    `dia` VARCHAR(50) NULL,
                    `precio` DECIMAL(12, 2) NULL,
                    `cluster_t` VARCHAR(50) NULL,
                    `cluster_c` VARCHAR(50) NULL,
                    `cliente_norm` VARCHAR(255) NULL,
                    `vendedor_norm` VARCHAR(255) NULL,
                    `dia_norm` VARCHAR(50) NULL,
                    INDEX `idx_prs_snapshot` (`snapshot_id`),
                    INDEX `idx_prs_cli` (`cliente_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $conn->statement("
                CREATE TABLE IF NOT EXISTS `auditoria_ruteo_cambios` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `archivo_origen` VARCHAR(255) NOT NULL,
                    `cliente_id` BIGINT NOT NULL,
                    `cliente` VARCHAR(255) NULL,
                    `tipo_evento` VARCHAR(50) NOT NULL,
                    `campo_afectado` VARCHAR(50) NOT NULL,
                    `valor_anterior` TEXT NULL,
                    `valor_nuevo` TEXT NULL,
                    `fecha_auditoria` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_arc_cli` (`cliente_id`),
                    INDEX `idx_arc_tipo` (`tipo_evento`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable) {}
    }
}

