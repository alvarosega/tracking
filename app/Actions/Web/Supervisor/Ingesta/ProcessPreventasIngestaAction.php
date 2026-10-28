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

class ProcessPreventasIngestaAction
{
    /**
     * Columnas canónicas de inserción de fact_preventas (excluyendo la clave autoincremental 'id').
     *
     * @var array<int, string>
     */
    private const CANONICAL_PREVENTAS_COLUMNS = [
        'fecha', 'fecha_norm', 'nro_preventa', 'cliente_id', 'codigo_cliente',
        'cliente', 'almacen', 'vendedor', 'username_vendedor', 'zona',
        'producto_id', 'codigo_producto', 'producto', 'categoria', 'cantidad',
        'monto', 'descuento', 'monto_final', 'proveedor', 'tipo_negocio',
        'mes', 'ruta', 'dia_visita', 'precio_lista', 'facturar', 'nro_carga',
    ];

    /**
     * Tamaño del chunk para inserciones masivas en MariaDB.
     */
    private const CHUNK_SIZE = 500;

    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa la sincronización diferencial del archivo de Preventas (FACT_PREVENTAS).
     *
     * @return array<string, mixed>
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $fileHash = @hash_file('sha256', $filePath) ?: md5($filename);

        $conn = DB::connection('supervisor');

        $yaProcesado = $conn->table('sys_cargas')
            ->where('sha256', $fileHash)
            ->where('tipo_entidad', 'FACT_PREVENTAS')
            ->exists();

        if ($yaProcesado) {
            throw new Exception("El archivo ya fue procesado anteriormente (SHA-256 coincidente). Operación omitida para evitar reprocesamiento innecesario.");
        }

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'FACT_PREVENTAS');

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
                'cancelados' => 0,
                'filas_con_discrepancia' => 0,
                'sin_cambios' => 0,
                'filas_ignoradas' => 0,
                'volumen_total' => 0.0,
                'fecha_operativa' => '-',
                'rango_fechas' => '-',
                'detalles_modificados' => [],
                'detalles_cancelados' => [],
            ];
        }

        $fechasOperativas = [];
        $volumenTotal = '0.000000';
        $itemsEntrantes = [];
        $ocurrenciasEntrantes = [];

        // 1. Normalización y mapeo ordinal intra-archivo
        foreach ($rawRows as $r) {
            $mapped = $this->validator->mapRow($r, 'FACT_PREVENTAS');

            $nroPrev = isset($mapped['nro_preventa']) && is_numeric($mapped['nro_preventa']) ? (int) $mapped['nro_preventa'] : 0;
            $prodId = isset($mapped['producto_id']) && is_numeric($mapped['producto_id']) ? (int) $mapped['producto_id'] : 0;

            if ($nroPrev <= 0 || $prodId <= 0) {
                continue;
            }

            $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
            $fechaNorm = ExcelCsvParser::normalizeDate($fechaRaw) ?? '';

            if ($fechaNorm === '' || $fechaNorm === '1900-01-01') {
                continue;
            }

            $fechasOperativas[$fechaNorm] = true;
            $mapped['fecha_norm'] = $fechaNorm;
            $mapped['fecha'] = $fechaNorm;

            // Valores monetarios exactos: Cero es cero, sin coherencias sintéticas
            $monto = isset($mapped['monto']) ? $this->parseDecimal($mapped['monto']) : 0.0;
            $descuento = isset($mapped['descuento']) ? $this->parseDecimal($mapped['descuento']) : 0.0;
            $montoFinal = isset($mapped['monto_final']) ? $this->parseDecimal($mapped['monto_final']) : 0.0;

            $mapped['monto'] = $monto;
            $mapped['descuento'] = $descuento;
            $mapped['monto_final'] = $montoFinal;

            if (function_exists('bcadd')) {
                $volumenTotal = bcadd($volumenTotal, sprintf('%.6f', $montoFinal), 6);
            } else {
                $volumenTotal = sprintf('%.6f', (float) $volumenTotal + $montoFinal);
            }

            $mapped['cantidad'] = isset($mapped['cantidad']) && is_numeric($mapped['cantidad']) ? (int) $mapped['cantidad'] : 0;
            $mapped['cliente_id'] = isset($mapped['cliente_id']) && is_numeric($mapped['cliente_id']) ? (int) $mapped['cliente_id'] : 0;

            // Indexación ordinal por ítem (permite bonificaciones y múltiples partidas del mismo producto)
            $baseKey = "{$nroPrev}_{$prodId}_{$fechaNorm}";
            $ocurrencia = $ocurrenciasEntrantes[$baseKey] ?? 0;
            $ocurrenciasEntrantes[$baseKey] = $ocurrencia + 1;

            $k = "{$baseKey}_{$ocurrencia}";
            $itemsEntrantes[$k] = $mapped;
        }

        $fechasValidas = array_keys($fechasOperativas);
        sort($fechasValidas);

        if (empty($fechasValidas)) {
            throw new Exception("El archivo no contiene fechas operativas válidas para Preventas.");
        }

        $conn = DB::connection('supervisor');
        $colsDbFlip = $this->obtenerColumnasFactPreventas($conn);

        // 2. Consulta de registros existentes en la BD para las fechas del corte
        $placeholders = implode(',', array_fill(0, count($fechasValidas), '?'));
        $filasDb = $conn->select("
            SELECT id, nro_preventa, producto_id, cliente_id, cliente, vendedor, 
                   cantidad, monto, descuento, monto_final, fecha_norm, fecha
            FROM fact_preventas
            WHERE fecha_norm IN ({$placeholders})
            ORDER BY id ASC
        ", $fechasValidas);

        $existentes = [];
        $ocurrenciasDb = [];
        foreach ($filasDb as $r) {
            $fNorm = (string) ($r->fecha_norm ?? '');
            $baseKey = "{$r->nro_preventa}_{$r->producto_id}_{$fNorm}";
            $ocurrencia = $ocurrenciasDb[$baseKey] ?? 0;
            $ocurrenciasDb[$baseKey] = $ocurrencia + 1;

            $k = "{$baseKey}_{$ocurrencia}";
            $existentes[$k] = $r;
        }

        // 3. Consultar cancelaciones previas en estado PENDIENTE para reactivaciones
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
        } catch (Throwable) {}

        $nuevosRecords = [];
        $modificadosUpdates = [];
        $auditoriaCambios = [];
        $detallesModificados = [];
        $detallesCancelados = [];
        $clavesEntrantes = [];
        $reactivadosUpdates = [];
        $sinCambiosCount = 0;

        // 4. Clasificación diferencial
        foreach ($itemsEntrantes as $k => $row) {
            $clavesEntrantes[$k] = true;
            $nroPrev = (int) $row['nro_preventa'];
            $prodId = (int) $row['producto_id'];
            $fNorm = (string) ($row['fecha_norm'] ?? '');
            $montoNuevo = (float) $row['monto_final'];
            $cantNueva = (int) $row['cantidad'];

            // Detección de Reactivación
            $baseKey = "{$nroPrev}_{$prodId}_{$fNorm}";
            if (isset($prevCanceladosPendientes[$baseKey])) {
                $reactivadosUpdates[$baseKey] = [
                    'nro_preventa' => $nroPrev,
                    'producto_id' => $prodId,
                    'fecha_preventa' => $fNorm,
                ];
            }

            // Filtrar exclusivamente columnas presentes en fact_preventas
            $dbRow = [];
            foreach (self::CANONICAL_PREVENTAS_COLUMNS as $colName) {
                if (isset($colsDbFlip[$colName])) {
                    $dbRow[$colName] = $row[$colName] ?? null;
                }
            }

            if (!isset($existentes[$k])) {
                // Nuevo registro
                $nuevosRecords[] = $dbRow;
            } else {
                // Registro existente: comparar alteraciones
                $prev = $existentes[$k];
                $montoAnt = (float) ($prev->monto_final ?? 0.0);
                $cantAnt = (int) ($prev->cantidad ?? 0);

                $diffMonto = round($montoNuevo - $montoAnt, 6);
                $diffCant = $cantNueva - $cantAnt;

                if (abs($diffMonto) > 0.0001 || $diffCant !== 0) {
                    $cNom = (string) ($row['cliente'] ?? $prev->cliente ?? '');
                    $vNom = (string) ($row['vendedor'] ?? $prev->vendedor ?? '');

                    // Actualización directa por ID primario (preserva integridad referencial)
                    $modificadosUpdates[] = [
                        'id' => (int) $prev->id,
                        'data' => $dbRow,
                    ];

                    $auditoriaCambios[] = [
                        'archivo_origen' => $filename,
                        'tipo_evento' => 'MODIFICADO',
                        'fecha_preventa' => $fNorm,
                        'nro_preventa' => $nroPrev,
                        'producto_id' => $prodId,
                        'cliente_id' => isset($row['cliente_id']) ? (int) $row['cliente_id'] : null,
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

        // 5. Cancelaciones detectadas (en BD pero ausentes en este nuevo corte) -> Cuarentena
        $clavesCanceladas = [];
        foreach ($existentes as $k => $prev) {
            if (!isset($clavesEntrantes[$k])) {
                $clavesCanceladas[] = $prev;
                $cantAnt = (int) ($prev->cantidad ?? 0);
                $montoAnt = (float) ($prev->monto_final ?? 0.0);
                $fReg = (string) ($prev->fecha_norm ?? '');
                $cNom = (string) ($prev->cliente ?? '');
                $vNom = (string) ($prev->vendedor ?? '');

                $baseKey = "{$prev->nro_preventa}_{$prev->producto_id}_{$fReg}";

                if (!isset($prevCanceladosPendientes[$baseKey])) {
                    $auditoriaCambios[] = [
                        'archivo_origen' => $filename,
                        'tipo_evento' => 'CANCELADO',
                        'fecha_preventa' => $fReg,
                        'nro_preventa' => (int) $prev->nro_preventa,
                        'producto_id' => (int) $prev->producto_id,
                        'cliente_id' => isset($prev->cliente_id) ? (int) $prev->cliente_id : null,
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
                    'nro_preventa' => (int) $prev->nro_preventa,
                    'producto_id' => (int) $prev->producto_id,
                    'cliente' => $cNom,
                    'vendedor' => $vNom,
                    'cant_ant' => $cantAnt,
                    'monto_ant' => $montoAnt,
                ];
            }
        }

        // 6. Persistencia Transaccional Atómica
        $conn->transaction(function () use (
            $conn,
            $nuevosRecords,
            $modificadosUpdates,
            $reactivadosUpdates,
            $auditoriaCambios
        ) {
            // A. Inserción de Nuevos en chunks
            if (!empty($nuevosRecords)) {
                $chunks = array_chunk($nuevosRecords, self::CHUNK_SIZE);
                foreach ($chunks as $chunk) {
                    $conn->table('fact_preventas')->insert($chunk);
                }
            }

            // B. Actualización de Modificados directa por ID primario
            foreach ($modificadosUpdates as $item) {
                $conn->table('fact_preventas')
                    ->where('id', $item['id'])
                    ->update($item['data']);
            }

            // C. Reactivación de cancelados que reaparecieron
            foreach ($reactivadosUpdates as $react) {
                $conn->table('auditoria_preventas_cambios')
                    ->where('nro_preventa', $react['nro_preventa'])
                    ->where('producto_id', $react['producto_id'])
                    ->where('fecha_preventa', $react['fecha_preventa'])
                    ->where('estado_autorizacion', 'PENDIENTE')
                    ->update(['estado_autorizacion' => 'REACTIVADO']);
            }

            // D. Registro en bitácora de auditoría
            if (!empty($auditoriaCambios)) {
                $chunksAud = array_chunk($auditoriaCambios, self::CHUNK_SIZE);
                foreach ($chunksAud as $chunk) {
                    $conn->table('auditoria_preventas_cambios')->insert($chunk);
                }
            }
        });

        // 7. Registro en sys_cargas
        $rangoIni = $fechasValidas[0] ?? '-';
        $rangoFin = end($fechasValidas) ?: '-';
        $totalAfectadas = count($nuevosRecords) + count($modificadosUpdates) + count($clavesCanceladas);

        $this->registrarSysCargas(
            $conn,
            $filename,
            $filePath,
            $fileHash,
            $totalLeidas,
            $totalAfectadas,
            count($nuevosRecords),
            count($modificadosUpdates),
            $sinCambiosCount,
            count($clavesCanceladas),
            $rangoIni,
            $rangoFin
        );

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
            'volumen_total' => round((float) $volumenTotal, 2),
            'fecha_operativa' => $rangoStr,
            'rango_fechas' => $rangoStr,
            'detalles_modificados' => $detallesModificados,
            'detalles_cancelados' => $detallesCancelados,
        ];
    }

    /**
     * Parsea montos decimales con precisión a 6 decimales.
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
     * Obtiene columnas físicas de fact_preventas excluyendo 'id'.
     *
     * @return array<string, int>
     */
    protected function obtenerColumnasFactPreventas($conn): array
    {
        try {
            $cols = Schema::connection('supervisor')->getColumnListing('fact_preventas');
            if (!empty($cols)) {
                $cols = array_values(array_diff($cols, ['id']));
                return array_flip($cols);
            }
        } catch (Throwable) {}

        $canonical = array_values(array_diff(self::CANONICAL_PREVENTAS_COLUMNS, ['id']));
        return array_flip($canonical);
    }

    /**
     * Inserta la bitácora en sys_cargas.
     */
    protected function registrarSysCargas(
        $conn,
        string $filename,
        string $filePath,
        string $fileHash,
        int $leidas,
        int $afectadas,
        int $nuevas,
        int $actualizadas,
        int $ignoradas,
        int $discrepancias,
        string $inicio,
        string $fin
    ): void {
        try {
            $data = [
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_PREVENTAS',
                'filas_leidas' => $leidas,
                'filas_afectadas' => $afectadas,
                'filas_nuevas' => $nuevas,
                'filas_actualizadas' => $actualizadas,
                'filas_ignoradas' => $ignoradas,
                'filas_con_discrepancia' => $discrepancias,
                'rango_inicio' => $inicio,
                'rango_fin' => $fin,
                'fecha_ingesta' => now(),
            ];

            if (Schema::connection('supervisor')->hasColumn('sys_cargas', 'estado')) {
                $data['estado'] = 'COMPLETADO';
            }

            $conn->table('sys_cargas')->insert($data);
        } catch (Throwable $e) {
            Log::warning("No se pudo registrar FACT_PREVENTAS en sys_cargas: " . $e->getMessage());
        }
    }
}