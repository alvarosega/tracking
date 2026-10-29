<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ingesta\ExcelCsvParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SplFileObject;
use Throwable;

class IngestPreventasHistoricoCommand extends Command
{
    /**
     * Firma del comando Artisan CLI.
     *
     * @var string
     */
    protected $signature = 'ingesta:historico-preventas 
                            {path=storage/app/historicos/preventas.csv : Ruta al archivo CSV histórico} 
                            {--truncate : Vaciar fact_preventas y auditoria_preventas_cambios antes de procesar}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Carga masiva en streaming del histórico de preventas desde archivo CSV.';

    /**
     * Tamaño del lote para inserción masiva en MariaDB.
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Columnas canónicas de inserción de fact_preventas (sin autoincremental id).
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
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $path = (string) $this->argument('path');
        $resolvedPath = file_exists($path) ? realpath($path) : (file_exists(base_path($path)) ? realpath(base_path($path)) : null);

        if (!$resolvedPath || !is_file($resolvedPath)) {
            $this->error("El archivo no existe en la ruta especificada: {$path}");
            return Command::FAILURE;
        }

        $this->info("Iniciando ingesta histórica de preventas desde: {$resolvedPath}");

        $conn = DB::connection('supervisor');

        // Manejo de opción --truncate
        if ($this->option('truncate')) {
            $this->warn('Vaciando tablas fact_preventas y auditoria_preventas_cambios...');
            $conn->statement('TRUNCATE TABLE fact_preventas');
            $conn->statement('TRUNCATE TABLE auditoria_preventas_cambios');
            $conn->table('sys_cargas')->where('tipo_entidad', 'FACT_PREVENTAS')->delete();
            $this->warn('Tablas fact_preventas y auditoria_preventas_cambios vaciadas.');
        }

        // Conteo rápido de líneas para la barra de progreso
        $totalLineas = 0;
        $fp = fopen($resolvedPath, 'r');
        if ($fp) {
            while (!feof($fp)) {
                $totalLineas += substr_count((string) fread($fp, 65536), "\n");
            }
            fclose($fp);
        }

        $progressBar = $this->output->createProgressBar(max(0, $totalLineas - 1));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $file = new SplFileObject($resolvedPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');

        $headersProcesados = false;
        $headerMap = [];
        $batch = [];
        $totalLeidas = 0;
        $totalInsertadas = 0;
        $fechaMin = null;
        $fechaMax = null;

        try {
            foreach ($file as $row) {
                if (!is_array($row) || empty($row) || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }

                if (!$headersProcesados) {
                    if (isset($row[0])) {
                        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                    }
                    $headerMap = $this->construirMapaCabeceras($row);
                    $headersProcesados = true;
                    continue;
                }

                $totalLeidas++;

                $mapped = $this->mapearFila($row, $headerMap);
                $normalizada = $this->normalizarFila($mapped);

                if ($normalizada === null) {
                    $progressBar->advance();
                    continue;
                }

                $fechaNorm = (string) $normalizada['fecha_norm'];
                if ($fechaMin === null || $fechaNorm < $fechaMin) {
                    $fechaMin = $fechaNorm;
                }
                if ($fechaMax === null || $fechaNorm > $fechaMax) {
                    $fechaMax = $fechaNorm;
                }

                $batch[] = $normalizada;

                if (count($batch) >= self::CHUNK_SIZE) {
                    $conn->table('fact_preventas')->insert($batch);
                    $totalInsertadas += count($batch);
                    $progressBar->advance(count($batch));
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $conn->table('fact_preventas')->insert($batch);
                $totalInsertadas += count($batch);
                $progressBar->advance(count($batch));
                $batch = [];
            }

            $progressBar->finish();
            $this->newLine(2);

        } catch (Throwable $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("Error durante la ingesta: " . $e->getMessage());
            Log::error("Fallo crítico en ingesta:historico-preventas", [
                'archivo' => $resolvedPath,
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
            ]);
            return Command::FAILURE;
        }

        // Registrar en sys_cargas
        try {
            $fileHash = hash_file('sha256', $resolvedPath) ?: '';
            $rangoIni = $fechaMin ?? date('Y-m-d');
            $rangoFin = $fechaMax ?? date('Y-m-d');

            $maxId = $conn->table('sys_cargas')->max('id');
            $nextId = $maxId ? ((int) $maxId + 1) : 1;

            $cargaData = [
                'id' => $nextId,
                'archivo_original' => basename($resolvedPath),
                'archivo_almacenado' => $resolvedPath,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_PREVENTAS_HISTORICO',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalInsertadas,
                'filas_nuevas' => $totalInsertadas,
                'filas_actualizadas' => 0,
                'filas_ignoradas' => max(0, $totalLeidas - $totalInsertadas),
                'filas_con_discrepancia' => 0,
                'rango_inicio' => $rangoIni,
                'rango_fin' => $rangoFin,
                'fecha_ingesta' => now(),
            ];

            if (Schema::connection('supervisor')->hasColumn('sys_cargas', 'estado')) {
                $cargaData['estado'] = 'COMPLETADO';
            }

            $conn->table('sys_cargas')->insert($cargaData);
        } catch (Throwable $eSys) {
            $this->warn("Advertencia: No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        $this->info("Ingesta histórica de preventas finalizada con éxito.");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Archivo', basename($resolvedPath)],
                ['Filas Leídas', number_format($totalLeidas)],
                ['Filas Insertadas', number_format($totalInsertadas)],
                ['Filas Omitidas', number_format(max(0, $totalLeidas - $totalInsertadas))],
                ['Rango Fechas', ($fechaMin ?? '-') . ' al ' . ($fechaMax ?? '-')],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Construye un mapa entre el nombre canónico y el índice de columna en el CSV.
     *
     * @param array<int, mixed> $headers
     * @return array<string, int>
     */
    protected function construirMapaCabeceras(array $headers): array
    {
        $map = [];
        $synonyms = [
            'fecha' => ['fecha', 'fecha venta', 'fechaventa'],
            'fecha_norm' => ['fecha_norm', 'fechanorm', 'fecha norm'],
            'nro_preventa' => ['nro_preventa', 'nro preventa', 'nropreventa', 'preventa_id', 'preventaid', 'preventa id', 'nro'],
            'cliente_id' => ['cliente_id', 'clienteid', 'cliente id', 'id_cliente', 'idcliente', 'id cliente', 'cod_cliente', 'codcliente', 'codigo_cliente', 'codigocliente'],
            'codigo_cliente' => ['codigo_cliente', 'codigocliente', 'codigo cliente', 'cod_cliente', 'codcliente', 'cod cliente'],
            'cliente' => ['cliente', 'nombre_cliente', 'nombre cliente', 'razon_social', 'razon social'],
            'almacen' => ['almacen', 'almacén'],
            'vendedor' => ['vendedor', 'preventista', 'nombre_vendedor', 'nombre vendedor'],
            'username_vendedor' => ['username_vendedor', 'username vendedor', 'usernamevendedor', 'vendedor_username', 'vendedor username'],
            'zona' => ['zona'],
            'producto_id' => ['producto_id', 'productoid', 'producto id', 'id_producto', 'idproducto', 'id producto'],
            'codigo_producto' => ['codigo_producto', 'codigoproducto', 'codigo producto', 'cod_producto', 'codproducto'],
            'producto' => ['producto', 'descripcion', 'descripcion_producto'],
            'categoria' => ['categoria', 'categoría'],
            'cantidad' => ['cantidad', 'cant', 'unidades'],
            'monto' => ['monto', 'subtotal', 'importe'],
            'descuento' => ['descuento', 'desc', 'descuentos'],
            'monto_final' => ['monto_final', 'monto final', 'montofinal', 'total', 'monto total'],
            'proveedor' => ['proveedor'],
            'tipo_negocio' => ['tipo_negocio', 'tipo negocio', 'tiponegocio', 'negocio'],
            'mes' => ['mes'],
            'ruta' => ['ruta', 'cod_ruta', 'codigo_ruta'],
            'dia_visita' => ['dia_visita', 'dia visita', 'diavisita', 'dia'],
            'precio_lista' => ['precio_lista', 'precio lista', 'preciolista'],
            'facturar' => ['facturar'],
            'nro_carga' => ['nro_carga', 'nro carga', 'nrocarga', 'carga'],
        ];

        foreach ($headers as $idx => $rawHeader) {
            $h = trim(strtolower((string) $rawHeader));
            $h = preg_replace('/\s+/', ' ', $h);

            foreach ($synonyms as $canonical => $synList) {
                if (in_array($h, $synList, true)) {
                    if (!isset($map[$canonical])) {
                        $map[$canonical] = $idx;
                    }
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Mapea un array posicional de fila a un array asociativo con claves canónicas.
     *
     * @param array<int, mixed> $row
     * @param array<string, int> $headerMap
     * @return array<string, mixed>
     */
    protected function mapearFila(array $row, array $headerMap): array
    {
        $mapped = [];
        foreach ($headerMap as $col => $idx) {
            $mapped[$col] = $row[$idx] ?? null;
        }

        return $mapped;
    }

    /**
     * Normaliza los datos de la fila según las reglas de negocio para fact_preventas.
     *
     * @param array<string, mixed> $mapped
     * @return array<string, mixed>|null
     */
    protected function normalizarFila(array $mapped): ?array
    {
        $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
        $fechaNorm = ExcelCsvParser::normalizeDate($fechaRaw);

        if ($fechaNorm === null) {
            return null;
        }

        $nroPrev = isset($mapped['nro_preventa']) && is_numeric($mapped['nro_preventa']) ? (int) $mapped['nro_preventa'] : 0;
        $prodId = isset($mapped['producto_id']) && is_numeric($mapped['producto_id']) ? (int) $mapped['producto_id'] : 0;

        if ($nroPrev <= 0 || $prodId <= 0) {
            return null;
        }

        $mes = isset($mapped['mes']) && is_numeric($mapped['mes'])
            ? (int) $mapped['mes']
            : (int) date('m', strtotime($fechaNorm));

        return [
            'fecha' => $fechaNorm,
            'fecha_norm' => $fechaNorm,
            'nro_preventa' => $nroPrev,
            'cliente_id' => isset($mapped['cliente_id']) && is_numeric($mapped['cliente_id']) ? (int) $mapped['cliente_id'] : 0,
            'codigo_cliente' => $this->cleanString($mapped['codigo_cliente'] ?? null, 50),
            'cliente' => $this->cleanString($mapped['cliente'] ?? null, 255),
            'almacen' => $this->cleanString($mapped['almacen'] ?? null, 100),
            'vendedor' => $this->cleanString($mapped['vendedor'] ?? null, 255),
            'username_vendedor' => $this->cleanString($mapped['username_vendedor'] ?? null, 100),
            'zona' => $this->cleanString($mapped['zona'] ?? null, 100),
            'producto_id' => $prodId,
            'codigo_producto' => $this->cleanString($mapped['codigo_producto'] ?? null, 100),
            'producto' => $this->cleanString($mapped['producto'] ?? null, 255),
            'categoria' => $this->cleanString($mapped['categoria'] ?? null, 100),
            'cantidad' => isset($mapped['cantidad']) && is_numeric($mapped['cantidad']) ? (int) $mapped['cantidad'] : 0,
            'monto' => $this->parseDecimal($mapped['monto'] ?? 0.0),
            'descuento' => $this->parseDecimal($mapped['descuento'] ?? 0.0),
            'monto_final' => $this->parseDecimal($mapped['monto_final'] ?? 0.0),
            'proveedor' => $this->cleanString($mapped['proveedor'] ?? null, 100),
            'tipo_negocio' => $this->cleanString($mapped['tipo_negocio'] ?? null, 100),
            'mes' => $mes,
            'ruta' => $this->cleanString($mapped['ruta'] ?? null, 100),
            'dia_visita' => $this->cleanString($mapped['dia_visita'] ?? null, 50),
            'precio_lista' => $this->cleanString($mapped['precio_lista'] ?? null, 50),
            'facturar' => $this->cleanString($mapped['facturar'] ?? null, 50),
            'nro_carga' => $this->cleanString($mapped['nro_carga'] ?? null, 50),
        ];
    }

    /**
     * Limpia y trunca texto.
     */
    protected function cleanString(mixed $val, int $maxLen = 255): ?string
    {
        if ($val === null) {
            return null;
        }

        $str = trim((string) $val);
        if ($str === '' || strcasecmp($str, 'null') === 0) {
            return null;
        }

        return mb_substr($str, 0, $maxLen, 'UTF-8');
    }

    /**
     * Parsea montos decimales con precisión a 6 dígitos con default 0.000000.
     */
    protected function parseDecimal(mixed $val): float
    {
        if ($val === null) {
            return 0.0;
        }

        $str = trim((string) $val);
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
}

