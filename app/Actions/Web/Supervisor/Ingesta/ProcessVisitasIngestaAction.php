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

class ProcessVisitasIngestaAction
{
    /**
     * Tamaño de bloque para inserciones masivas en MariaDB.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Columnas canónicas de fallback para fact_visitas.
     *
     * @var array<int, string>
     */
    private const CANONICAL_VISITAS_COLUMNS = [
        'id', 'tipo', 'motivo', 'cliente', 'cliente_norm', 'empleado',
        'empleado_norm', 'fecha', 'fecha_norm', 'monto', 'check_in',
        'check_out', 'tiempo_pdv', 'zona', 'tipo_pago', 'tipo_negocio', 'cluster',
    ];

    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa la ingesta de archivo de Visitas Operativas (FACT_VISITAS).
     *
     * @param UploadedFile|string $file
     * @param string|null $originalFilename
     * @return array<string, mixed>
     * @throws Exception
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        // 1. Idempotencia temprana por hash SHA-256
        $fileHash = @hash_file('sha256', $filePath) ?: md5($filename);

        $conn = DB::connection('supervisor');

        $yaProcesado = $conn->table('sys_cargas')
            ->where('sha256', $fileHash)
            ->where('tipo_entidad', 'FACT_VISITAS')
            ->exists();

        if ($yaProcesado) {
            throw new Exception("El archivo de visitas ya fue procesado anteriormente (SHA-256 coincidente). Operación omitida.");
        }

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'FACT_VISITAS');

        $rawRows = $this->parser->parseToAssociativeRows($filePath, 0, $filename);
        $totalLeidas = count($rawRows);

        if ($totalLeidas === 0) {
            return [
                'status' => 'success',
                'filas_leidas' => 0,
                'nuevos' => 0,
                'filas_nuevas' => 0,
                'modificados' => 0,
                'filas_actualizadas' => 0,
                'eliminados' => 0,
                'cancelados' => 0,
                'sin_cambios' => 0,
                'filas_ignoradas' => 0,
                'filas_con_discrepancia' => 0,
                'rango_fechas' => '-',
                'fecha_operativa' => '-',
                'detalles_alterados' => [],
                'detalles_eliminados' => [],
            ];
        }

        $dedupMap = [];
        $fechasOperativas = [];

        // 2. Pipeline de Normalización por Fila
        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'FACT_VISITAS');

            $vid = isset($mapped['id']) && is_numeric($mapped['id']) ? (int) $mapped['id'] : 0;
            if ($vid <= 0) {
                continue;
            }

            // Parseo robusto de fechas
            $fechaRaw = $mapped['fecha'] ?? ($mapped['fecha_norm'] ?? null);
            $fechaNorm = ExcelCsvParser::normalizeDate($fechaRaw) ?? '';
            $fechaFull = ExcelCsvParser::normalizeDateTime($fechaRaw) ?? ($fechaNorm !== '' ? "{$fechaNorm} 00:00:00" : null);

            if ($fechaNorm === '') {
                continue;
            }

            $fechasOperativas[$fechaNorm] = true;
            $mapped['id'] = $vid;
            $mapped['fecha_norm'] = $fechaNorm;
            $mapped['fecha'] = $fechaFull;

            // Parseo de tiempos (evitar insertar números seriales decimales 0.68... crudos)
            $mapped['check_in'] = ExcelCsvParser::normalizeTime($mapped['check_in'] ?? null);
            $mapped['check_out'] = ExcelCsvParser::normalizeTime($mapped['check_out'] ?? null);
            $mapped['tiempo_pdv'] = ExcelCsvParser::normalizeTime($mapped['tiempo_pdv'] ?? null);

            // Generación de columnas normalizadas
            $mapped['cliente_norm'] = $this->sanitizeNormString($mapped['cliente'] ?? null);
            $mapped['empleado_norm'] = $this->sanitizeNormString($mapped['empleado'] ?? null);

            // Parseo de montos a DECIMAL(16,6) con default 0.000000
            $mapped['monto'] = $this->parseDecimal($mapped['monto'] ?? 0.0);

            $dedupMap[$vid] = $mapped;
        }

        $fechasValidas = array_keys($fechasOperativas);
        sort($fechasValidas);

        if (empty($fechasValidas)) {
            throw new Exception("El archivo no contiene fechas operativas válidas para Visitas.");
        }

        // Asegurar tabla de auditoría para discrepancias
        try {
            $conn->statement("
                CREATE TABLE IF NOT EXISTS `auditoria_visitas_discrepancias` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `archivo_origen` VARCHAR(255) NOT NULL,
                    `visita_id` BIGINT NOT NULL,
                    `tipo_evento` VARCHAR(30) NOT NULL,
                    `fecha` VARCHAR(50) NULL,
                    `cliente` VARCHAR(255) NULL,
                    `empleado` VARCHAR(255) NULL,
                    `tipo_discrepancia` VARCHAR(100) NULL,
                    `valor_anterior` TEXT NULL,
                    `valor_nuevo` TEXT NULL,
                    `monto_anterior` DECIMAL(12, 4) NULL,
                    `monto_nuevo` DECIMAL(12, 4) NULL,
                    `detectado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_avd_visita` (`visita_id`),
                    INDEX `idx_avd_evento` (`tipo_evento`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable) {}

        // Obtener columnas existentes en fact_visitas
        $colsDb = $this->obtenerColumnasFactVisitas($conn);
        $colsDbFlip = array_flip($colsDb);

        // 3. Recuperar existentes en la BD basándonos en los IDs entrantes
        $idsEntrantes = array_keys($dedupMap);
        $existentes = [];

        $chunkIds = array_chunk($idsEntrantes, 2000);
        foreach ($chunkIds as $subIds) {
            $placeholders = implode(',', array_fill(0, count($subIds), '?'));
            $filas = $conn->select("
                SELECT id, tipo, motivo, cliente, cliente_norm, empleado, empleado_norm, fecha, fecha_norm, monto, 
                       check_in, check_out, tiempo_pdv, zona, tipo_pago, tipo_negocio, cluster
                FROM fact_visitas
                WHERE id IN ({$placeholders})
            ", $subIds);
            foreach ($filas as $r) {
                $existentes[(int) $r->id] = $r;
            }
        }

        $nuevosRecords = [];
        $modificadosUpdates = [];
        $auditoriaDiscrepancias = [];
        $detallesAlterados = [];
        $sinCambiosCount = 0;

        // 4. Auditoría de Discrepancias Limpia y Clasificación
        foreach ($dedupMap as $vid => $row) {
            $montoNuevo = (float) $row['monto'];

            $dbRow = [];
            foreach ($row as $colName => $colVal) {
                if (isset($colsDbFlip[$colName])) {
                    $dbRow[$colName] = $colVal;
                }
            }

            if (!isset($existentes[$vid])) {
                $nuevosRecords[] = $dbRow;
            } else {
                $prev = $existentes[$vid];
                $alterado = false;
                $discrepanciasFila = [];
                $montoAnt = (float) ($prev->monto ?? 0.0);

                // A. Monto (solo alteración si abs > 0.0001)
                if (abs($montoNuevo - $montoAnt) > 0.0001) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'MONTO',
                        'ant' => 'Bs ' . number_format($montoAnt, 2, '.', ''),
                        'nuev' => 'Bs ' . number_format($montoNuevo, 2, '.', ''),
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // B. Tipo de visita
                $tipoAnt = trim((string) ($prev->tipo ?? ''));
                $tipoNuevo = trim((string) ($row['tipo'] ?? ''));
                if ($tipoAnt !== '' && $tipoNuevo !== '' && strcasecmp($tipoAnt, $tipoNuevo) !== 0) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'TIPO_VISITA',
                        'ant' => $tipoAnt,
                        'nuev' => $tipoNuevo,
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // C. Tiempos PDV (comparar únicamente valores previamente normalizados)
                $cinAnt = ExcelCsvParser::normalizeTime($prev->check_in ?? null) ?? '';
                $cinNuevo = (string) ($row['check_in'] ?? '');
                $coutAnt = ExcelCsvParser::normalizeTime($prev->check_out ?? null) ?? '';
                $coutNuevo = (string) ($row['check_out'] ?? '');
                $tpdvAnt = ExcelCsvParser::normalizeTime($prev->tiempo_pdv ?? null) ?? '';
                $tpdvNuevo = (string) ($row['tiempo_pdv'] ?? '');

                if ($cinAnt !== $cinNuevo || $coutAnt !== $coutNuevo || $tpdvAnt !== $tpdvNuevo) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'TIEMPOS_PDV',
                        'ant' => "In: {$cinAnt} | Out: {$coutAnt} | T: {$tpdvAnt}",
                        'nuev' => "In: {$cinNuevo} | Out: {$coutNuevo} | T: {$tpdvNuevo}",
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // D. Motivo
                $motivoAnt = trim((string) ($prev->motivo ?? ''));
                $motivoNuevo = trim((string) ($row['motivo'] ?? ''));
                if ($motivoAnt !== '' && $motivoNuevo !== '' && strcasecmp($motivoAnt, $motivoNuevo) !== 0) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'MOTIVO',
                        'ant' => $motivoAnt ?: 'Sin motivo',
                        'nuev' => $motivoNuevo ?: 'Sin motivo',
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // E. Asignación (comparar valores normalizados para evitar falsos positivos)
                $empAntNorm = $this->sanitizeNormString($prev->empleado_norm ?? $prev->empleado ?? null) ?? '';
                $empNuevoNorm = (string) ($row['empleado_norm'] ?? '');
                $cliAntNorm = $this->sanitizeNormString($prev->cliente_norm ?? $prev->cliente ?? null) ?? '';
                $cliNuevoNorm = (string) ($row['cliente_norm'] ?? '');

                if (($empAntNorm !== '' && $empNuevoNorm !== '' && $empAntNorm !== $empNuevoNorm) ||
                    ($cliAntNorm !== '' && $cliNuevoNorm !== '' && $cliAntNorm !== $cliNuevoNorm)) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'ASIGNACION',
                        'ant' => "Emp: {$empAntNorm} | Cli: {$cliAntNorm}",
                        'nuev' => "Emp: {$empNuevoNorm} | Cli: {$cliNuevoNorm}",
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                if ($alterado) {
                    $modificadosUpdates[] = [
                        'row' => $dbRow,
                        'id' => $vid,
                    ];

                    $fReg = (string) ($row['fecha_norm'] ?? $row['fecha'] ?? '');
                    $cNom = (string) ($row['cliente'] ?? '');
                    $eNom = (string) ($row['empleado'] ?? '');

                    foreach ($discrepanciasFila as $df) {
                        $auditoriaDiscrepancias[] = [
                            'archivo_origen' => $filename,
                            'visita_id' => $vid,
                            'tipo_evento' => 'MODIFICADO',
                            'fecha' => $fReg,
                            'cliente' => $cNom,
                            'empleado' => $eNom,
                            'tipo_discrepancia' => $df['tipo'],
                            'valor_anterior' => $df['ant'],
                            'valor_nuevo' => $df['nuev'],
                            'monto_anterior' => $df['m_ant'],
                            'monto_nuevo' => $df['m_nuev'],
                        ];
                    }

                    $detallesAlterados[] = [
                        'id' => $vid,
                        'cliente' => $cNom,
                        'empleado' => $eNom,
                        'tipo' => implode(', ', array_column($discrepanciasFila, 'tipo')),
                        'previo' => $discrepanciasFila[0]['ant'] ?? '',
                        'nuevo' => $discrepanciasFila[0]['nuev'] ?? '',
                    ];
                } else {
                    $sinCambiosCount++;
                }
            }
        }

        // 5. Operaciones en Transacción Atómica (sin DELETE en fact_visitas)
        $conn->transaction(function () use ($conn, $nuevosRecords, $modificadosUpdates, $auditoriaDiscrepancias) {
            // A. Inserción de Nuevos en bloques de 500
            if (!empty($nuevosRecords)) {
                $chunks = array_chunk($nuevosRecords, self::CHUNK_SIZE);
                foreach ($chunks as $chunk) {
                    $conn->table('fact_visitas')->insert($chunk);
                }
            }

            // B. Actualización directa por id = $vid
            foreach ($modificadosUpdates as $item) {
                $updateData = $item['row'];
                unset($updateData['id']);
                $conn->table('fact_visitas')->where('id', $item['id'])->update($updateData);
            }

            // C. Auditoría de Discrepancias en bloques de 500
            if (!empty($auditoriaDiscrepancias)) {
                $chunksAud = array_chunk($auditoriaDiscrepancias, self::CHUNK_SIZE);
                foreach ($chunksAud as $chunk) {
                    $conn->table('auditoria_visitas_discrepancias')->insert($chunk);
                }
            }
        });

        // 6. Registro de Bitácora en sys_cargas
        $rangoIni = $fechasValidas[0] ?? '-';
        $rangoFin = end($fechasValidas) ?: '-';
        $totalAfectadas = count($nuevosRecords) + count($modificadosUpdates);

        try {
            $cargaData = [
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_VISITAS',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalAfectadas,
                'filas_nuevas' => count($nuevosRecords),
                'filas_actualizadas' => count($modificadosUpdates),
                'filas_ignoradas' => $sinCambiosCount,
                'filas_con_discrepancia' => count($auditoriaDiscrepancias),
                'rango_inicio' => $rangoIni,
                'rango_fin' => $rangoFin,
                'fecha_ingesta' => now(),
            ];

            if (Schema::connection('supervisor')->hasColumn('sys_cargas', 'estado')) {
                $cargaData['estado'] = 'COMPLETADO';
            }

            $conn->table('sys_cargas')->insert($cargaData);
        } catch (Throwable $eSys) {
            Log::warning("No se pudo registrar FACT_VISITAS en sys_cargas: " . $eSys->getMessage());
        }

        $rangoStr = "{$rangoIni} al {$rangoFin}";

        return [
            'status' => 'success',
            'filas_leidas' => $totalLeidas,
            'nuevos' => count($nuevosRecords),
            'filas_nuevas' => count($nuevosRecords),
            'modificados' => count($modificadosUpdates),
            'filas_actualizadas' => count($modificadosUpdates),
            'eliminados' => 0,
            'cancelados' => 0,
            'filas_con_discrepancia' => count($auditoriaDiscrepancias),
            'sin_cambios' => $sinCambiosCount,
            'filas_ignoradas' => $sinCambiosCount,
            'rango_fechas' => $rangoStr,
            'fecha_operativa' => $rangoStr,
            'detalles_alterados' => $detallesAlterados,
            'detalles_eliminados' => [],
        ];
    }

    /**
     * Sanitiza y normaliza cadenas de texto para análisis:
     * Mayúsculas, sin acentos (translit), sin caracteres especiales y espacios limpios.
     */
    private function sanitizeNormString(?string $str): ?string
    {
        if ($str === null) {
            return null;
        }

        $trimmed = trim($str);
        if ($trimmed === '') {
            return null;
        }

        if (!mb_check_encoding($trimmed, 'UTF-8')) {
            $trimmed = mb_convert_encoding($trimmed, 'UTF-8', 'ISO-8859-1');
        }

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
        if ($translit === false) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $trimmed);
            if ($translit === false) {
                $translit = $trimmed;
            }
        }

        $upper = strtoupper((string) $translit);
        $limpio = preg_replace('/[^A-Z0-9\s]/', '', $upper);
        $limpio = preg_replace('/\s+/', ' ', (string) $limpio);

        $result = trim((string) $limpio);

        return $result !== '' ? $result : null;
    }

    /**
     * Parsea montos a precisión decimal de 6 dígitos con default 0.000000.
     */
    protected function parseDecimal(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $str = trim((string) $value);
        if ($str === '' || strcasecmp($str, 'null') === 0) {
            return 0.0;
        }

        $clean = str_replace(' ', '', $str);

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            if (strrpos($clean, '.') > strrpos($clean, ',')) {
                $clean = str_replace(',', '', $clean);
            } else {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        $clean = preg_replace('/[^0-9.-]/', '', $clean);

        if ($clean === null || $clean === '' || $clean === '-' || !is_numeric($clean)) {
            return 0.0;
        }

        return round((float) $clean, 6);
    }

    /**
     * Obtiene el listado de columnas reales de fact_visitas.
     *
     * @param mixed $conn
     * @return array<int, string>
     */
    protected function obtenerColumnasFactVisitas($conn): array
    {
        try {
            $cols = Schema::connection('supervisor')->getColumnListing('fact_visitas');
            if (!empty($cols)) {
                return array_values($cols);
            }
        } catch (Throwable) {}

        try {
            $colDbRows = $conn->select("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fact_visitas'
            ");
            $cols = array_map(fn($c) => $c->COLUMN_NAME ?? $c->column_name, $colDbRows);
            if (!empty($cols)) {
                return array_values($cols);
            }
        } catch (Throwable) {}

        return self::CANONICAL_VISITAS_COLUMNS;
    }
}
