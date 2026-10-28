<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SplFileObject;
use Throwable;

class IngestaHistoricoCommand extends Command
{
    /**
     * Firma del comando Artisan CLI.
     *
     * @var string
     */
    protected $signature = 'ingesta:historico 
                            {archivo : Ruta relativa dentro de storage/app/}
                            {--connection= : Conexión de base de datos a utilizar (por defecto supervisor)}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Ingesta masiva de archivo histórico de ventas (FACT_VENTAS) de alto rendimiento para MariaDB';

    /**
     * Tamaño del lote para inserción masiva en base de datos.
     */
    private const CHUNK_SIZE = 1500;

    /**
     * Columnas numéricas con precisión decimal.
     *
     * @var array<int, string>
     */
    private const DECIMAL_COLUMNS = [
        'monto_final',
        'precio_unitario',
        'monto',
        'precio_paquete',
        'descuento',
        'descuento2',
        'descuento_socio',
        'descuento_cliente',
        'descuento_grupo',
        'descuento_volumen',
        'cantidad_cajas',
    ];

    /**
     * Límites de longitud VARCHAR en base de datos para prevenir desbordamientos.
     *
     * @var array<string, int>
     */
    private const COLUMN_LIMITS = [
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

    /**
     * Columnas canónicas por defecto de la tabla fact_ventas.
     *
     * @var array<int, string>
     */
    private const CANONICAL_FACT_VENTAS_COLUMNS = [
        'fecha', 'fecha_norm', 'hora', 'cliente_id', 'cliente', 'cliente_norm',
        'oficina', 'vendedor', 'vendedor_id', 'vendedor_norm', 'vendedor_username',
        'distribuidor', 'distribuidor_id', 'distribuidor_username', 'zona', 'tipo_pago',
        'producto_id', 'codigo', 'producto', 'categoria', 'dp_group', 'cantidad',
        'cantidad_cajas', 'precio_unitario', 'precio_paquete', 'monto', 'descuento',
        'descuento2', 'descuento_socio', 'descuento_cliente', 'monto_final', 'proveedor',
        'tipo_negocio', 'nro_factura', 'cuf', 'razon_social', 'nit', 'mes', 'supervisor',
        'venta_id', 'pre_venta_id', 'ruta', 'dia_visita', 'precio_lista', 'canal',
        'tipo_objeto', 'mercado', 'empresa', 'jerarquia1', 'jerarquia2', 'jerarquia3',
        'descuento_grupo', 'descuento_volumen', 'escala_descuento', 'escala_producto',
        'cluster_t', 'cluster_c', 'descripcion_unificada', 'marca', 'revertida', 'origen',
    ];

    /**
     * Ejecuta el comando de ingesta masiva.
     */
    public function handle(): int
    {
        // Optimización estricta de memoria y timeouts para CloudLinux LVE
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $db = $this->getDatabaseConnection();
        $this->configurarTimeoutsSesion($db);

        $archivoArg = (string) $this->argument('archivo');
        $rutaArchivo = $this->resolverRutaArchivo($archivoArg);

        if (!$rutaArchivo || !file_exists($rutaArchivo)) {
            $this->error("El archivo especificado no existe en el almacenamiento: [storage/app/{$archivoArg}].");
            return Command::FAILURE;
        }

        $nombreArchivo = basename($rutaArchivo);
        $fileHash = @hash_file('sha256', $rutaArchivo) ?: md5($nombreArchivo . filemtime($rutaArchivo));

        $this->info("Iniciando ingesta masiva de archivo histórico: {$nombreArchivo}");
        $this->line("Ruta física: {$rutaArchivo}");

        // Asegurar tablas auxiliares y columnas de esquema
        $this->asegurarTablaDiscrepancias();
        $colsDbFlip = $this->obtenerColumnasFactVentas();
        $canonicalCols = !empty($colsDbFlip) ? array_keys($colsDbFlip) : self::CANONICAL_FACT_VENTAS_COLUMNS;

        // Registrar inicio en bitácora sys_cargas
        $cargaId = $this->registrarInicioSysCargas($db, $nombreArchivo, $rutaArchivo, $fileHash);

        // Inicializar SplFileObject en streaming
        try {
            $file = new SplFileObject($rutaArchivo, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');
        } catch (Throwable $e) {
            $this->error("No se pudo abrir el archivo CSV para lectura en streaming: " . $e->getMessage());
            $this->marcarSysCargasFallido($db, $cargaId);
            return Command::FAILURE;
        }

        // Configuración de la barra de progreso
        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat(" %current% filas procesadas [%bar%] %elapsed% (Mem: %memory%)\n");
        $progressBar->start();

        $totalLeidas = 0;
        $totalInsertadas = 0;
        $totalOmitidos = 0;
        $totalDiscrepancias = 0;
        $montoTotalFinal = '0.000000';
        $fechaMin = null;
        $fechaMax = null;

        $batchRawCount = 0;
        $chunkMap = [];
        $intraChunkDiscrepancias = [];
        $headerMap = [];
        $headersProcesados = false;
        $chunkNumber = 0;

        try {
            foreach ($file as $row) {
                if (!is_array($row) || empty($row) || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }

                // 1. Procesar y validar cabeceras en la línea 1 antes de iterar
                if (!$headersProcesados) {
                    if (isset($row[0])) {
                        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                    }
                    $this->validarCabeceras($row);
                    $headerMap = $this->construirMapaCabeceras($row);
                    $headersProcesados = true;
                    continue;
                }

                $totalLeidas++;

                // 2. Mapeo y Normalización por fila
                $mapped = $this->mapearFila($row, $headerMap);
                $normalizada = $this->normalizarFila($mapped);

                if ($normalizada === null) {
                    continue;
                }

                $ventaId = (int) $normalizada['venta_id'];
                $productoId = (int) $normalizada['producto_id'];
                $fechaNorm = (string) $normalizada['fecha_norm'];
                $montoFinal = (float) ($normalizada['monto_final'] ?? 0.0);

                // Acumulación con precisión exacta de 6 decimales mediante bcadd
                if (function_exists('bcadd')) {
                    $montoTotalFinal = bcadd($montoTotalFinal, sprintf('%.6f', $montoFinal), 6);
                } else {
                    $montoTotalFinal = sprintf('%.6f', (float) $montoTotalFinal + $montoFinal);
                }

                // Actualizar rangos de fecha operativa
                if ($fechaMin === null || $fechaNorm < $fechaMin) {
                    $fechaMin = $fechaNorm;
                }
                if ($fechaMax === null || $fechaNorm > $fechaMax) {
                    $fechaMax = $fechaNorm;
                }

                // Filtrar exclusivamente columnas presentes en tabla fact_ventas
                $dbRow = [];
                foreach ($normalizada as $col => $val) {
                    if (isset($colsDbFlip[$col])) {
                        $dbRow[$col] = $val;
                    }
                }

                // 3. Regla "Prevalece el primero" en colisiones intra-chunk
                $compositeKey = "{$fechaNorm}_{$ventaId}_{$productoId}";
                if (isset($chunkMap[$compositeKey])) {
                    // Prevalece el primer registro en el mapa de inserción. Comprobar discrepancia de monto
                    $firstRow = $chunkMap[$compositeKey];
                    $montoFirst = (float) ($firstRow['monto_final'] ?? 0.0);

                    if (abs($montoFirst - $montoFinal) > 0.000001) {
                        $intraChunkDiscrepancias[] = [
                            'archivo_origen' => $nombreArchivo,
                            'venta_id' => $ventaId,
                            'producto_id' => $productoId,
                            'fecha_norm' => $fechaNorm,
                            'cliente_id' => isset($normalizada['cliente_id']) ? (int) $normalizada['cliente_id'] : null,
                            'monto_anterior' => $montoFirst,
                            'monto_nuevo' => $montoFinal,
                            'cantidad_anterior' => (int) ($firstRow['cantidad'] ?? 0),
                            'cantidad_nueva' => (int) ($normalizada['cantidad'] ?? 0),
                            'revertida_anterior' => (string) ($firstRow['revertida'] ?? 'No'),
                            'revertida_nueva' => (string) ($normalizada['revertida'] ?? 'No'),
                            'estado_revision' => 'REGISTRADO',
                            'detectado_en' => now(),
                        ];
                    }
                } else {
                    // Registrar el primer registro único para este lote
                    $chunkMap[$compositeKey] = $dbRow;
                }

                $batchRawCount++;

                // 4. Ejecución del chunk al alcanzar el límite (1,500 registros leídos)
                if ($batchRawCount >= self::CHUNK_SIZE) {
                    $resChunk = $this->procesarChunk(
                        $db,
                        $batchRawCount,
                        $chunkMap,
                        $intraChunkDiscrepancias,
                        $nombreArchivo,
                        $canonicalCols
                    );

                    $totalInsertadas += $resChunk['insertadas'];
                    $totalOmitidos += $resChunk['omitidas'];
                    $totalDiscrepancias += $resChunk['discrepancias'];

                    $progressBar->advance($batchRawCount);

                    // Limpieza estricta de memoria por lote
                    $batchRawCount = 0;
                    $chunkMap = [];
                    $intraChunkDiscrepancias = [];
                    $chunkNumber++;

                    if ($chunkNumber % 10 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            // Procesar último chunk residual si contiene registros
            if ($batchRawCount > 0) {
                $resChunk = $this->procesarChunk(
                    $db,
                    $batchRawCount,
                    $chunkMap,
                    $intraChunkDiscrepancias,
                    $nombreArchivo,
                    $canonicalCols
                );

                $totalInsertadas += $resChunk['insertadas'];
                $totalOmitidos += $resChunk['omitidas'];
                $totalDiscrepancias += $resChunk['discrepancias'];

                $progressBar->advance($batchRawCount);

                unset($chunkMap, $intraChunkDiscrepancias);
            }

            $progressBar->finish();
            $this->newLine(2);

        } catch (Throwable $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("Error fatal durante la ingesta del archivo: " . $e->getMessage());
            Log::error("Fallo crítico en ingesta:historico", [
                'archivo' => $nombreArchivo,
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo_codigo' => $e->getFile(),
            ]);

            $this->marcarSysCargasFallido($db, $cargaId);
            return Command::FAILURE;
        }

        // 5. Actualizar bitácora sys_cargas con el resumen final
        $this->actualizarSysCargasFinal(
            $db,
            $cargaId,
            $totalLeidas,
            $totalInsertadas,
            $totalOmitidos,
            $totalDiscrepancias,
            $fechaMin,
            $fechaMax
        );

        // 6. Consulta de cuadre y Salida en Consola
        $montoConsolidadoDb = 0.0;
        if ($fechaMin !== null && $fechaMax !== null) {
            try {
                $dbSumRow = $db->selectOne("
                    SELECT SUM(monto_final) as suma_db 
                    FROM fact_ventas 
                    WHERE fecha_norm BETWEEN ? AND ?
                ", [$fechaMin, $fechaMax]);
                $montoConsolidadoDb = (float) ($dbSumRow->suma_db ?? 0.0);
            } catch (Throwable $eSum) {
                Log::warning("No se pudo obtener la sumatoria consolidada en BD: " . $eSum->getMessage());
            }
        }

        $montoBrutoArchivo = (float) $montoTotalFinal;
        $diferenciaCuadre = $montoBrutoArchivo - $montoConsolidadoDb;

        $this->info("Ingesta histórica completada exitosamente.");
        $this->newLine();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total Leídas', number_format($totalLeidas, 0, '.', ',')],
                ['Insertadas', number_format($totalInsertadas, 0, '.', ',')],
                ['Duplicados Omitidos', number_format($totalOmitidos, 0, '.', ',')],
                ['Discrepancias', number_format($totalDiscrepancias, 0, '.', ',')],
                ['Monto Bruto Leído (Archivo)', number_format($montoBrutoArchivo, 2, '.', ',')],
                ['Monto Consolidado en BD (fact_ventas)', number_format($montoConsolidadoDb, 2, '.', ',')],
                ['Diferencia de Cuadre', number_format($diferenciaCuadre, 2, '.', ',')],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Procesa un chunk de registros dentro de una transacción con rollback en caso de fallo.
     *
     * @param int $rawCount
     * @param array<string, array<string, mixed>> $chunkMap
     * @param array<int, array<string, mixed>> $intraChunkDiscrepancias
     * @param array<int, string> $canonicalCols
     * @return array{insertadas: int, omitidas: int, discrepancias: int}
     * @throws Throwable
     */
    protected function procesarChunk(
        ConnectionInterface $db,
        int $rawCount,
        array $chunkMap,
        array $intraChunkDiscrepancias,
        string $nombreArchivo,
        array $canonicalCols
    ): array {
        if (empty($chunkMap)) {
            return [
                'insertadas' => 0,
                'omitidas' => $rawCount,
                'discrepancias' => count($intraChunkDiscrepancias),
            ];
        }

        $discrepanciasParaInsertar = $intraChunkDiscrepancias;
        $totalDiscrepancias = count($intraChunkDiscrepancias);

        // Lista de registros únicos prevaleciendo el primer registro
        $dedupedChunk = array_values($chunkMap);

        // Normalizar estructura del array para garantizar que todos los registros posean idénticas columnas
        $uniformChunk = [];
        foreach ($dedupedChunk as $row) {
            $uniformRow = [];
            foreach ($canonicalCols as $col) {
                $uniformRow[$col] = array_key_exists($col, $row) ? $row[$col] : null;
            }
            $uniformChunk[] = $uniformRow;
        }

        $db->beginTransaction();
        try {
            // Inserción masiva ignorando claves primarias/únicas existentes
            $insertedCount = (int) $db->table('fact_ventas')->insertOrIgnore($uniformChunk);
            $omittedEnDb = count($uniformChunk) - $insertedCount;

            // Si hubo omisión de registros contra la base de datos, validar colisiones de montos mediante tuplas
            if ($omittedEnDb > 0) {
                $tuples = [];
                foreach ($uniformChunk as $row) {
                    $tuples[] = [
                        (string) $row['fecha_norm'],
                        (int) $row['venta_id'],
                        (int) $row['producto_id'],
                    ];
                }

                // Consultas por tuplas en bloques de 500 para evitar desbordes de parámetros
                $tupleChunks = array_chunk($tuples, 500);
                $dbMap = [];

                foreach ($tupleChunks as $tChunk) {
                    $placeholders = implode(',', array_fill(0, count($tChunk), '(?, ?, ?)'));
                    $bindings = [];
                    foreach ($tChunk as $t) {
                        $bindings[] = $t[0]; // fecha_norm
                        $bindings[] = $t[1]; // venta_id
                        $bindings[] = $t[2]; // producto_id
                    }

                    $querySql = "SELECT id, fecha_norm, venta_id, producto_id, monto_final, cantidad, revertida, cliente_id 
                                 FROM fact_ventas 
                                 WHERE (fecha_norm, venta_id, producto_id) IN ({$placeholders})";

                    $dbRows = $db->select($querySql, $bindings);

                    foreach ($dbRows as $dRow) {
                        $key = "{$dRow->fecha_norm}_{$dRow->venta_id}_{$dRow->producto_id}";
                        $dbMap[$key] = $dRow;
                    }
                }

                foreach ($uniformChunk as $row) {
                    $k = "{$row['fecha_norm']}_{$row['venta_id']}_{$row['producto_id']}";
                    if (isset($dbMap[$k])) {
                        $prev = $dbMap[$k];
                        $montoAnt = (float) ($prev->monto_final ?? 0.0);
                        $montoNuevo = (float) ($row['monto_final'] ?? 0.0);

                        // Comparación con precisión exacta de 6 decimales
                        if (abs($montoAnt - $montoNuevo) > 0.000001) {
                            $discrepanciasParaInsertar[] = [
                                'archivo_origen' => $nombreArchivo,
                                'venta_id' => (int) $row['venta_id'],
                                'producto_id' => (int) $row['producto_id'],
                                'fecha_norm' => (string) $row['fecha_norm'],
                                'cliente_id' => isset($row['cliente_id']) ? (int) $row['cliente_id'] : null,
                                'monto_anterior' => $montoAnt,
                                'monto_nuevo' => $montoNuevo,
                                'cantidad_anterior' => (int) ($prev->cantidad ?? 0),
                                'cantidad_nueva' => (int) ($row['cantidad'] ?? 0),
                                'revertida_anterior' => (string) ($prev->revertida ?? 'No'),
                                'revertida_nueva' => (string) ($row['revertida'] ?? 'No'),
                                'estado_revision' => 'REGISTRADO',
                                'detectado_en' => now(),
                            ];
                            $totalDiscrepancias++;
                        }
                    }
                }
            }

            // Inserción de discrepancias detectadas (tanto intra-chunk como contra BD)
            if (!empty($discrepanciasParaInsertar)) {
                $discrepanciasChunks = array_chunk($discrepanciasParaInsertar, 500);
                foreach ($discrepanciasChunks as $dChunk) {
                    $db->table('auditoria_ventas_discrepancias')->insert($dChunk);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $totalOmitidas = $rawCount - $insertedCount;

        return [
            'insertadas' => $insertedCount,
            'omitidas' => $totalOmitidas,
            'discrepancias' => $totalDiscrepancias,
        ];
    }

    /**
     * Valida que las cabeceras en la línea 1 contengan las columnas requeridas para FACT_VENTAS.
     *
     * @param array<int, mixed> $headers
     * @throws RuntimeException
     */
    protected function validarCabeceras(array $headers): void
    {
        if (empty($headers)) {
            throw new RuntimeException("El archivo CSV no contiene cabeceras en la línea 1.");
        }

        $mappings = $this->obtenerCatalogoMapeo();
        $colsEncontradas = [];

        foreach ($headers as $h) {
            $clean = strtolower(trim(str_replace(['_', "\xEF\xBB\xBF"], [' ', ''], (string) $h)));
            $comp = str_replace(' ', '', $clean);

            if (isset($mappings[$clean])) {
                $colsEncontradas[$mappings[$clean]] = true;
            } elseif (isset($mappings[$comp])) {
                $colsEncontradas[$mappings[$comp]] = true;
            } else {
                $colsEncontradas[strtolower(trim((string) $h))] = true;
            }
        }

        $tieneVenta = isset($colsEncontradas['venta_id']);
        $tieneProducto = isset($colsEncontradas['producto_id']);
        $tieneFecha = isset($colsEncontradas['fecha']) || isset($colsEncontradas['fecha_norm']);
        $tieneMonto = isset($colsEncontradas['monto_final']) || isset($colsEncontradas['monto']) || isset($colsEncontradas['precio_unitario']);

        if (!$tieneVenta || !$tieneProducto || !$tieneFecha || !$tieneMonto) {
            $muestra = implode(', ', array_slice(array_map('strval', $headers), 0, 8));
            throw new RuntimeException(
                "Cabeceras inválidas en la línea 1: No se encontraron las columnas esenciales requeridas para FACT_VENTAS (venta_id, producto_id, fecha, monto). Cabeceras detectadas: [{$muestra}]"
            );
        }
    }

    /**
     * Construye un mapa directo [índice => columna_db] para lectura $O(1)$ por fila.
     *
     * @param array<int, mixed> $headers
     * @return array<int, string>
     */
    protected function construirMapaCabeceras(array $headers): array
    {
        $mappings = $this->obtenerCatalogoMapeo();
        $map = [];

        foreach ($headers as $idx => $headerName) {
            $cleanKey = strtolower(trim(str_replace(['_', "\xEF\xBB\xBF"], [' ', ''], (string) $headerName)));
            $compKey = str_replace(' ', '', $cleanKey);

            $target = $mappings[$cleanKey] ?? ($mappings[$compKey] ?? strtolower(trim((string) $headerName)));
            $map[$idx] = $target;
        }

        return $map;
    }

    /**
     * Mapea un array indexado de fila a sus columnas correspondientes.
     *
     * @param array<int, mixed> $row
     * @param array<int, string> $headerMap
     * @return array<string, mixed>
     */
    protected function mapearFila(array $row, array $headerMap): array
    {
        $mapped = [];
        foreach ($row as $colIdx => $val) {
            if (isset($headerMap[$colIdx])) {
                $mapped[$headerMap[$colIdx]] = $val;
            }
        }
        return $mapped;
    }

    /**
     * Normaliza los tipos de datos de una fila según especificaciones técnicas.
     *
     * @param array<string, mixed> $mapped
     * @return array<string, mixed>|null Retorna null si la fila no tiene clave obligatoria válida.
     */
    protected function normalizarFila(array $mapped): ?array
    {
        $ventaId = isset($mapped['venta_id']) && is_numeric($mapped['venta_id']) ? (int) $mapped['venta_id'] : 0;
        $productoId = isset($mapped['producto_id']) && is_numeric($mapped['producto_id']) ? (int) $mapped['producto_id'] : 0;

        if ($ventaId <= 0 || $productoId <= 0) {
            return null;
        }

        // 1. Normalización de fecha_norm ('Y-m-d')
        $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
        $fechaNorm = $this->parseFechaNorm($fechaRaw);
        if (!$fechaNorm) {
            return null;
        }

        $mapped['venta_id'] = $ventaId;
        $mapped['producto_id'] = $productoId;
        $mapped['fecha_norm'] = $fechaNorm;
        $mapped['fecha'] = $fechaNorm;

        // 2. Normalización de cliente_norm y vendedor_norm con transliteración limpia
        $mapped['cliente_norm'] = $this->normalizarTextoLimpio($mapped['cliente_norm'] ?? ($mapped['cliente'] ?? ''));
        $mapped['vendedor_norm'] = $this->normalizarTextoLimpio($mapped['vendedor_norm'] ?? ($mapped['vendedor'] ?? ''));

        // 3. Normalización de columnas monetarias con alta precisión (6 decimales)
        foreach (self::DECIMAL_COLUMNS as $dc) {
            if (array_key_exists($dc, $mapped)) {
                $mapped[$dc] = $this->parseMonetary($mapped[$dc]);
            }
        }

        // Coherencia monto / monto_final
        $monto = (float) ($mapped['monto'] ?? 0.0);
        $montoFinal = (float) ($mapped['monto_final'] ?? 0.0);
        if ($montoFinal === 0.0 && $monto > 0.0) {
            $mapped['monto_final'] = $monto;
        } elseif ($monto === 0.0 && $montoFinal > 0.0) {
            $mapped['monto'] = $montoFinal;
        }

        // 4. Sanitización de enteros y strings operativas
        $mapped['cliente_id'] = isset($mapped['cliente_id']) && is_numeric($mapped['cliente_id']) ? (int) $mapped['cliente_id'] : 0;
        $mapped['cantidad'] = isset($mapped['cantidad']) && is_numeric($mapped['cantidad']) ? (int) $mapped['cantidad'] : 0;

        if (isset($mapped['vendedor_id']) && is_numeric($mapped['vendedor_id'])) {
            $mapped['vendedor_id'] = (int) $mapped['vendedor_id'];
        }

        $rev = isset($mapped['revertida']) ? trim((string) $mapped['revertida']) : '';
        $mapped['revertida'] = in_array(strtolower($rev), ['si', 'sí', 'true', '1', 'anulada', 'revertida', 'yes'], true) ? 'Si' : 'No';

        // Normalización de hora
        if (isset($mapped['hora'])) {
            $mapped['hora'] = $this->parseHora($mapped['hora']);
        }

        // Derivación de mes y origen si no se encuentran explícitos
        if (empty($mapped['mes']) || !is_numeric($mapped['mes'])) {
            $mapped['mes'] = (int) date('m', strtotime($fechaNorm));
        } else {
            $mapped['mes'] = (int) $mapped['mes'];
        }

        if (empty($mapped['origen'])) {
            $mapped['origen'] = 'VEN';
        }

        // Truncado de cadenas para respetar límites VARCHAR de base de datos
        foreach (self::COLUMN_LIMITS as $colName => $maxLen) {
            if (isset($mapped[$colName]) && is_string($mapped[$colName]) && mb_strlen($mapped[$colName], 'UTF-8') > $maxLen) {
                $mapped[$colName] = mb_substr($mapped[$colName], 0, $maxLen, 'UTF-8');
            }
        }

        return $mapped;
    }

    /**
     * Parsea fechas desde formato 'd/m/Y' o 'Y-m-d' nativo hacia 'Y-m-d'.
     */
    protected function parseFechaNorm(mixed $fechaRaw): ?string
    {
        if ($fechaRaw === null) {
            return null;
        }

        $str = trim((string) $fechaRaw);
        if ($str === '' || $str === '1900-01-01' || strcasecmp($str, 'null') === 0 || strcasecmp($str, 'none') === 0) {
            return null;
        }

        // 1. Formato d/m/Y o d-m-Y
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // 2. Formato Y-m-d o Y/m/d
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 3. Serial de número de fecha de Excel (ej: 45212)
        if (is_numeric($str) && (float) $str > 30000 && (float) $str < 60000) {
            $days = (int) floor((float) $str);
            $unixTime = ($days - 25569) * 86400;
            return gmdate('Y-m-d', $unixTime);
        }

        // 4. Fallback con strtotime
        $ts = strtotime($str);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Limpia y translitera texto a mayúsculas sin espacios redundantes.
     */
    protected function normalizarTextoLimpio(mixed $str): string
    {
        if ($str === null) {
            return '';
        }

        $trimmed = trim((string) $str);
        if ($trimmed === '') {
            return '';
        }

        if (!mb_check_encoding($trimmed, 'UTF-8')) {
            $trimmed = mb_convert_encoding($trimmed, 'UTF-8', 'ISO-8859-1');
        }

        // Transliteración nativa según especificación técnica obligatoria
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
        if ($translit === false) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $trimmed);
            if ($translit === false) {
                $translit = $trimmed;
            }
        }

        // Limpiar tildes residuales de iconv y compactar espacios múltiples asegurando texto limpio
        $limpio = preg_replace(["/['`^~]/", '/\s+/'], ['', ' '], (string) $translit);

        return strtoupper(trim((string) $limpio));
    }

    /**
     * Limpia espacios y convierte valores numéricos a float manteniendo 6 decimales.
     */
    protected function parseMonetary(mixed $value): float
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
     * Parsea hora a formato HH:MM:SS.
     */
    protected function parseHora(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }

        $str = trim((string) $val);
        if ($str === '' || strcasecmp($str, 'null') === 0) {
            return null;
        }

        // Fracción decimal de Excel (ej: 0.670138)
        if (is_numeric($str) && (float) $str >= 0 && (float) $str < 1) {
            $totalSeconds = (int) round((float) $str * 86400);
            $h = floor($totalSeconds / 3600);
            $m = floor(($totalSeconds % 3600) / 60);
            $s = $totalSeconds % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $str, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
        }

        return mb_substr($str, 0, 10, 'UTF-8');
    }

    /**
     * Registra el lote inicial en sys_cargas.
     */
    protected function registrarInicioSysCargas(
        ConnectionInterface $db,
        string $nombreArchivo,
        string $rutaArchivo,
        string $fileHash
    ): ?int {
        try {
            $data = [
                'archivo_original' => $nombreArchivo,
                'archivo_almacenado' => $rutaArchivo,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_VENTAS',
                'filas_leidas' => 0,
                'filas_afectadas' => 0,
                'filas_nuevas' => 0,
                'filas_actualizadas' => 0,
                'filas_ignoradas' => 0,
                'filas_con_discrepancia' => 0,
                'rango_inicio' => '-',
                'rango_fin' => '-',
                'fecha_ingesta' => now(),
            ];

            if ($this->hasColumnInTable('sys_cargas', 'estado')) {
                $data['estado'] = 'PROCESANDO';
            }

            try {
                return (int) $db->table('sys_cargas')->insertGetId($data);
            } catch (Throwable) {
                $maxId = (int) $db->table('sys_cargas')->max('id');
                $data['id'] = $maxId + 1;
                $db->table('sys_cargas')->insert($data);
                return $data['id'];
            }
        } catch (Throwable $e) {
            Log::warning("No se pudo registrar inicio en sys_cargas: " . $e->getMessage());
            $this->warn("Aviso: No se pudo registrar la entrada en sys_cargas ({$e->getMessage()}). Continuando ingesta...");
            return null;
        }
    }

    /**
     * Actualiza la bitácora sys_cargas al finalizar exitosamente la ingesta.
     */
    protected function actualizarSysCargasFinal(
        ConnectionInterface $db,
        ?int $cargaId,
        int $totalLeidas,
        int $totalInsertadas,
        int $totalOmitidos,
        int $totalDiscrepancias,
        ?string $fechaMin,
        ?string $fechaMax
    ): void {
        if (!$cargaId) {
            return;
        }

        try {
            $data = [
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalInsertadas,
                'filas_nuevas' => $totalInsertadas,
                'filas_actualizadas' => 0,
                'filas_ignoradas' => $totalOmitidos,
                'filas_con_discrepancia' => $totalDiscrepancias,
                'rango_inicio' => $fechaMin ?? '-',
                'rango_fin' => $fechaMax ?? '-',
            ];

            if ($this->hasColumnInTable('sys_cargas', 'estado')) {
                $data['estado'] = 'COMPLETADO';
            }

            $db->table('sys_cargas')->where('id', $cargaId)->update($data);
        } catch (Throwable $e) {
            Log::warning("No se pudo actualizar sys_cargas al finalizar: " . $e->getMessage());
        }
    }

    /**
     * Marca la bitácora sys_cargas como fallida en caso de error crítico.
     */
    protected function marcarSysCargasFallido(ConnectionInterface $db, ?int $cargaId): void
    {
        if (!$cargaId) {
            return;
        }

        try {
            if ($this->hasColumnInTable('sys_cargas', 'estado')) {
                $db->table('sys_cargas')->where('id', $cargaId)->update(['estado' => 'FALLIDO']);
            }
        } catch (Throwable) {}
    }

    /**
     * Verifica y asegura la existencia de la tabla de auditoría de discrepancias según especificación física.
     */
    protected function asegurarTablaDiscrepancias(): void
    {
        try {
            $connName = $this->getConnectionName();
            $schema = Schema::connection($connName);

            if (!$schema->hasTable('auditoria_ventas_discrepancias')) {
                $schema->create('auditoria_ventas_discrepancias', function (Blueprint $table) {
                    $table->id();
                    $table->string('archivo_origen', 255);
                    $table->bigInteger('venta_id');
                    $table->bigInteger('producto_id');
                    $table->date('fecha_norm');
                    $table->bigInteger('cliente_id')->nullable();
                    $table->decimal('monto_anterior', 16, 6)->nullable();
                    $table->decimal('monto_nuevo', 16, 6)->nullable();
                    $table->bigInteger('cantidad_anterior')->nullable();
                    $table->bigInteger('cantidad_nueva')->nullable();
                    $table->string('revertida_anterior', 10)->nullable();
                    $table->string('revertida_nueva', 10)->nullable();
                    $table->string('estado_revision', 20)->default('REGISTRADO');
                    $table->timestamp('detectado_en')->useCurrent();
                    $table->index(['venta_id', 'producto_id'], 'idx_aud_vta_prod');
                    $table->index('estado_revision', 'idx_aud_vta_estado');
                });
            }
        } catch (Throwable $e) {
            Log::warning("No se pudo verificar o crear tabla auditoria_ventas_discrepancias: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el listado de columnas reales de fact_ventas indexadas para filtrado rápido.
     *
     * @return array<string, int>
     */
    protected function obtenerColumnasFactVentas(): array
    {
        try {
            $connName = $this->getConnectionName();
            $cols = Schema::connection($connName)->getColumnListing('fact_ventas');
            if (!empty($cols)) {
                return array_flip($cols);
            }
        } catch (Throwable) {}

        return array_flip(self::CANONICAL_FACT_VENTAS_COLUMNS);
    }

    /**
     * Resuelve la ruta del archivo relativa a storage/app/.
     */
    protected function resolverRutaArchivo(string $archivoArg): ?string
    {
        $relativa = ltrim($archivoArg, '/\\');
        $rutaEstandar = storage_path('app/' . $relativa);

        if (file_exists($rutaEstandar)) {
            return $rutaEstandar;
        }

        // Soporte para Laravel 11 disk 'local' que apunta por defecto a storage/app/private
        $rutaPrivate = storage_path('app/private/' . $relativa);
        if (file_exists($rutaPrivate)) {
            return $rutaPrivate;
        }

        // Si se suministró ruta absoluta o relativa directa al archivo existente
        if (file_exists($archivoArg)) {
            return realpath($archivoArg) ?: $archivoArg;
        }

        return null;
    }

    /**
     * Obtiene la conexión a base de datos adecuada.
     */
    protected function getDatabaseConnection(): ConnectionInterface
    {
        $connName = $this->getConnectionName();
        return $connName ? DB::connection($connName) : DB::connection();
    }

    /**
     * Obtiene el nombre de la conexión configurada.
     */
    protected function getConnectionName(): ?string
    {
        $connOption = $this->option('connection');
        if ($connOption) {
            return (string) $connOption;
        }

        // En producción y desarrollo el módulo supervisor utiliza la conexión 'supervisor'
        if (!app()->environment('testing') && config('database.connections.supervisor')) {
            return 'supervisor';
        }

        return null;
    }

    /**
     * Verifica si una columna existe en la tabla.
     */
    protected function hasColumnInTable(string $table, string $column): bool
    {
        try {
            return Schema::connection($this->getConnectionName())->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ajusta los timeouts de sesión de MariaDB para operaciones masivas.
     */
    protected function configurarTimeoutsSesion(ConnectionInterface $db): void
    {
        try {
            $db->statement("SET SESSION wait_timeout = 600");
            $db->statement("SET SESSION net_read_timeout = 600");
            $db->statement("SET SESSION net_write_timeout = 600");
        } catch (Throwable) {}
    }

    /**
     * Catálogo exhaustivo de variaciones de cabeceras para FACT_VENTAS.
     *
     * @return array<string, string>
     */
    protected function obtenerCatalogoMapeo(): array
    {
        return [
            'fecha' => 'fecha',
            'fecha venta' => 'fecha',
            'fechaventa' => 'fecha',
            'fechanorm' => 'fecha_norm',
            'fecha norm' => 'fecha_norm',
            'hora' => 'hora',
            'hora venta' => 'hora',
            'horaventa' => 'hora',
            'clienteid' => 'cliente_id',
            'cliente id' => 'cliente_id',
            'id cliente' => 'cliente_id',
            'idcliente' => 'cliente_id',
            'codigo cliente' => 'cliente_id',
            'codigocliente' => 'cliente_id',
            'cod cliente' => 'cliente_id',
            'codcliente' => 'cliente_id',
            'cliente' => 'cliente',
            'cliente norm' => 'cliente_norm',
            'clientenorm' => 'cliente_norm',
            'oficina' => 'oficina',
            'vendedor' => 'vendedor',
            'vendedor norm' => 'vendedor_norm',
            'vendedornorm' => 'vendedor_norm',
            'vendedorid' => 'vendedor_id',
            'vendedor id' => 'vendedor_id',
            'id vendedor' => 'vendedor_id',
            'idvendedor' => 'vendedor_id',
            'cod vendedor' => 'vendedor_id',
            'codvendedor' => 'vendedor_id',
            'vendedorusername' => 'vendedor_username',
            'vendedor username' => 'vendedor_username',
            'username vendedor' => 'vendedor_username',
            'usernamevendedor' => 'vendedor_username',
            'distribuidor' => 'distribuidor',
            'distribuidorid' => 'distribuidor_id',
            'distribuidor id' => 'distribuidor_id',
            'id distribuidor' => 'distribuidor_id',
            'iddistribuidor' => 'distribuidor_id',
            'distribuidorusername' => 'distribuidor_username',
            'distribuidor username' => 'distribuidor_username',
            'username distribuidor' => 'distribuidor_username',
            'usernamedistribuidor' => 'distribuidor_username',
            'zona' => 'zona',
            'tipopago' => 'tipo_pago',
            'tipo pago' => 'tipo_pago',
            'productoid' => 'producto_id',
            'producto id' => 'producto_id',
            'id producto' => 'producto_id',
            'idproducto' => 'producto_id',
            'codigo producto' => 'producto_id',
            'codigoproducto' => 'producto_id',
            'cod producto' => 'producto_id',
            'codproducto' => 'producto_id',
            'codigo' => 'codigo',
            'producto' => 'producto',
            'categoria' => 'categoria',
            'dpgroup' => 'dp_group',
            'dp group' => 'dp_group',
            'cantidad' => 'cantidad',
            'cantidadcajas' => 'cantidad_cajas',
            'cantidad cajas' => 'cantidad_cajas',
            'preciounitario' => 'precio_unitario',
            'precio unitario' => 'precio_unitario',
            'preciopaquete' => 'precio_paquete',
            'precio paquete' => 'precio_paquete',
            'monto' => 'monto',
            'subtotal' => 'monto',
            'descuento' => 'descuento',
            'descuento2' => 'descuento2',
            'descuentosocio' => 'descuento_socio',
            'descuento socio' => 'descuento_socio',
            'descuentocliente' => 'descuento_cliente',
            'descuento cliente' => 'descuento_cliente',
            'montofinal' => 'monto_final',
            'monto final' => 'monto_final',
            'total venta' => 'monto_final',
            'totalventa' => 'monto_final',
            'total' => 'monto_final',
            'proveedor' => 'proveedor',
            'tiponegocio' => 'tipo_negocio',
            'tipo negocio' => 'tipo_negocio',
            'nrofactura' => 'nro_factura',
            'nro factura' => 'nro_factura',
            'factura' => 'nro_factura',
            'cuf' => 'cuf',
            'razonsocial' => 'razon_social',
            'razon social' => 'razon_social',
            'nit' => 'nit',
            'mes' => 'mes',
            'supervisor' => 'supervisor',
            'ventaid' => 'venta_id',
            'venta id' => 'venta_id',
            'nro venta' => 'venta_id',
            'nroventa' => 'venta_id',
            'id venta' => 'venta_id',
            'idventa' => 'venta_id',
            'numero venta' => 'venta_id',
            'numeroventa' => 'venta_id',
            'preventaid' => 'pre_venta_id',
            'preventa id' => 'pre_venta_id',
            'pre venta id' => 'pre_venta_id',
            'nro preventa' => 'pre_venta_id',
            'nropreventa' => 'pre_venta_id',
            'id preventa' => 'pre_venta_id',
            'idpreventa' => 'pre_venta_id',
            'ruta' => 'ruta',
            'diavisita' => 'dia_visita',
            'dia visita' => 'dia_visita',
            'preciolista' => 'precio_lista',
            'precio lista' => 'precio_lista',
            'canal' => 'canal',
            'tipoobjeto' => 'tipo_objeto',
            'tipo objeto' => 'tipo_objeto',
            'mercado' => 'mercado',
            'empresa' => 'empresa',
            'jerarquia1' => 'jerarquia1',
            'jerarquia2' => 'jerarquia2',
            'jerarquia3' => 'jerarquia3',
            'descuentogrupo' => 'descuento_grupo',
            'descuento grupo' => 'descuento_grupo',
            'descuentovolumen' => 'descuento_volumen',
            'descuento volumen' => 'descuento_volumen',
            'escaladescuento' => 'escala_descuento',
            'escala descuento' => 'escala_descuento',
            'escalaproducto' => 'escala_producto',
            'escala producto' => 'escala_producto',
            'clustert' => 'cluster_t',
            'cluster t' => 'cluster_t',
            'clusterc' => 'cluster_c',
            'cluster c' => 'cluster_c',
            'descripcionunificada' => 'descripcion_unificada',
            'descripcion unificada' => 'descripcion_unificada',
            'marca' => 'marca',
            'revertida' => 'revertida',
            'origen' => 'origen',
        ];
    }
}
