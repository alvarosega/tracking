<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessVisitasIngestaAction
{
    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa la ingesta de archivo de Visitas (FACT_VISITAS).
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath);
        $this->validator->validateSchema($headers, 'FACT_VISITAS');

        $rawRows = $this->parser->parseToAssociativeRows($filePath);
        $totalLeidas = count($rawRows);

        if ($totalLeidas === 0) {
            return [
                'filas_leidas' => 0,
                'nuevos' => 0,
                'modificados' => 0,
                'eliminados' => 0,
                'sin_cambios' => 0,
                'rango_fechas' => '-',
                'detalles_alterados' => [],
                'detalles_eliminados' => [],
            ];
        }

        $fileHash = hash_file('sha256', $filePath) ?: md5($filename);

        $dedupMap = [];
        $fechasOperativas = [];

        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'FACT_VISITAS');

            $vid = isset($mapped['id']) ? (int)$mapped['id'] : 0;
            if ($vid <= 0) {
                continue;
            }

            $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
            $fechaNorm = ExcelCsvParser::normalizeDate($fechaRaw) ?? '';
            $mapped['fecha_norm'] = $fechaNorm;
            if ($fechaNorm !== '') {
                $fechasOperativas[$fechaNorm] = true;
            }

            $mapped['monto'] = isset($mapped['monto']) ? (float)$mapped['monto'] : 0.0;
            $dedupMap[$vid] = $mapped;
        }

        $fechasValidas = array_keys($fechasOperativas);
        sort($fechasValidas);

        if (empty($fechasValidas)) {
            throw new Exception("El archivo no contiene fechas operativas válidas para Visitas.");
        }

        $conn = DB::connection('supervisor');

        // Asegurar tabla auditoria_visitas_discrepancias
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
        } catch (\Throwable) {}

        // Obtener columnas de fact_visitas
        try {
            $colsDb = \Illuminate\Support\Facades\Schema::connection('supervisor')->getColumnListing('fact_visitas');
        } catch (\Throwable) {
            $colsDb = [];
        }

        if (empty($colsDb)) {
            try {
                $colDbRows = $conn->select("
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fact_visitas'
                ");
                $colsDb = array_map(fn($c) => $c->COLUMN_NAME ?? $c->column_name, $colDbRows);
            } catch (\Throwable) {
                $colsDb = [];
            }
        }
        $colsDbFlip = array_flip($colsDb);

        // 1. Recuperar existentes basándonos en los IDs entrantes
        $idsEntrantes = array_keys($dedupMap);
        $existentes = [];

        $chunkIds = array_chunk($idsEntrantes, 2000);
        foreach ($chunkIds as $subIds) {
            $placeholders = implode(',', array_fill(0, count($subIds), '?'));
            $filas = $conn->select("
                SELECT id, tipo, motivo, cliente, empleado, fecha, fecha_norm, monto, 
                       check_in, check_out, tiempo_pdv, zona, tipo_pago, tipo_negocio, cluster
                FROM fact_visitas
                WHERE id IN ({$placeholders})
            ", $subIds);
            foreach ($filas as $r) {
                $existentes[(int)$r->id] = $r;
            }
        }

        $nuevosRecords = [];
        $modificadosUpdates = [];
        $auditoriaDiscrepancias = [];
        $detallesAlterados = [];
        $detallesEliminados = [];
        $sinCambiosCount = 0;

        foreach ($dedupMap as $vid => $row) {
            $montoNuevo = (float)$row['monto'];

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
                $montoAnt = (float)($prev->monto ?? 0.0);

                // 1. Monto
                if (abs($montoNuevo - $montoAnt) > 0.01) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'MONTO',
                        'ant' => 'Bs ' . number_format($montoAnt, 2, '.', ''),
                        'nuev' => 'Bs ' . number_format($montoNuevo, 2, '.', ''),
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // 2. Tipo
                $tipoAnt = trim((string)($prev->tipo ?? ''));
                $tipoNuevo = trim((string)($row['tipo'] ?? ''));
                if ($tipoAnt !== '' && $tipoNuevo !== '' && strtolower($tipoAnt) !== strtolower($tipoNuevo)) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'TIPO_VISITA',
                        'ant' => $tipoAnt,
                        'nuev' => $tipoNuevo,
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // 3. Tiempos PDV
                $cinAnt = trim((string)($prev->check_in ?? ''));
                $cinNuevo = trim((string)($row['check_in'] ?? ''));
                $coutAnt = trim((string)($prev->check_out ?? ''));
                $coutNuevo = trim((string)($row['check_out'] ?? ''));
                $tpdvAnt = trim((string)($prev->tiempo_pdv ?? ''));
                $tpdvNuevo = trim((string)($row['tiempo_pdv'] ?? ''));
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

                // 4. Motivo
                $motivoAnt = trim((string)($prev->motivo ?? ''));
                $motivoNuevo = trim((string)($row['motivo'] ?? ''));
                if ($motivoAnt !== $motivoNuevo) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'MOTIVO',
                        'ant' => $motivoAnt ?: 'Sin motivo',
                        'nuev' => $motivoNuevo ?: 'Sin motivo',
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                // 5. Asignación
                $empAnt = trim((string)($prev->empleado ?? ''));
                $empNuevo = trim((string)($row['empleado'] ?? ''));
                $cliAnt = trim((string)($prev->cliente ?? ''));
                $cliNuevo = trim((string)($row['cliente'] ?? ''));
                if (($empAnt !== '' && $empNuevo !== '' && strtolower($empAnt) !== strtolower($empNuevo)) ||
                    ($cliAnt !== '' && $cliNuevo !== '' && strtolower($cliAnt) !== strtolower($cliNuevo))) {
                    $alterado = true;
                    $discrepanciasFila[] = [
                        'tipo' => 'ASIGNACION',
                        'ant' => "Emp: {$empAnt} | Cli: {$cliAnt}",
                        'nuev' => "Emp: {$empNuevo} | Cli: {$cliNuevo}",
                        'm_ant' => $montoAnt,
                        'm_nuev' => $montoNuevo,
                    ];
                }

                if ($alterado) {
                    $modificadosUpdates[] = [
                        'row' => $dbRow,
                        'id' => $vid,
                    ];

                    $fReg = (string)($row['fecha_norm'] ?? $row['fecha'] ?? '');
                    $cNom = (string)($row['cliente'] ?? '');
                    $eNom = (string)($row['empleado'] ?? '');

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

        // 3. Visitas Operativas es Carga Acumulativa por Dispositivo/Vendedor:
        // Las visitas previas que no vengan en este archivo NO se borran físicamente para proteger
        // los marcajes históricos y visitas de otros empleados/rutas del mismo día.
        $idsEliminados = [];

        $chunkSize = 300;

        // 4. Operaciones en Transacción Atómica
        $conn->transaction(function () use ($conn, $nuevosRecords, $modificadosUpdates, $auditoriaDiscrepancias, $chunkSize) {
            // A. Inserción de Nuevos
            if (!empty($nuevosRecords)) {
                $chunks = array_chunk($nuevosRecords, $chunkSize);
                foreach ($chunks as $chunk) {
                    $conn->table('fact_visitas')->insert($chunk);
                }
            }

            // B. Actualización de Modificados
            foreach ($modificadosUpdates as $item) {
                $updateData = $item['row'];
                unset($updateData['id']);
                $conn->table('fact_visitas')->where('id', $item['id'])->update($updateData);
            }

            // C. Auditoría de Discrepancias
            if (!empty($auditoriaDiscrepancias)) {
                $chunksAud = array_chunk($auditoriaDiscrepancias, $chunkSize);
                foreach ($chunksAud as $chunk) {
                    $conn->table('auditoria_visitas_discrepancias')->insert($chunk);
                }
            }
        });

        // 8. Bitácora sys_cargas
        $rangoIni = $fechasValidas[0] ?? '-';
        $rangoFin = end($fechasValidas) ?: '-';
        $totalAfectadas = count($nuevosRecords) + count($modificadosUpdates) + count($idsEliminados);

        $conn->table('sys_cargas')->insert([
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
        ]);

        $rangoStr = "{$rangoIni} al {$rangoFin}";

        return [
            'status' => 'success',
            'filas_leidas' => $totalLeidas,
            'nuevos' => count($nuevosRecords),
            'filas_nuevas' => count($nuevosRecords),
            'modificados' => count($modificadosUpdates),
            'filas_actualizadas' => count($modificadosUpdates),
            'eliminados' => count($idsEliminados),
            'cancelados' => count($idsEliminados),
            'filas_con_discrepancia' => count($auditoriaDiscrepancias),
            'sin_cambios' => $sinCambiosCount,
            'filas_ignoradas' => $sinCambiosCount,
            'rango_fechas' => $rangoStr,
            'fecha_operativa' => $rangoStr,
            'detalles_alterados' => $detallesAlterados,
            'detalles_eliminados' => $detallesEliminados,
        ];
    }
}

