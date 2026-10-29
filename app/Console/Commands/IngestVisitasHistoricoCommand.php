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

class IngestVisitasHistoricoCommand extends Command
{
    /**
     * Firma del comando Artisan CLI.
     *
     * @var string
     */
    protected $signature = 'ingesta:historico-visitas 
                            {path=storage/app/historicos/visitas.csv : Ruta al archivo CSV histórico} 
                            {--truncate : Vaciar fact_visitas y auditoria_visitas_discrepancias antes de procesar}';

    /**
     * Descripción del comando en consola.
     *
     * @var string
     */
    protected $description = 'Carga masiva en streaming del histórico de visitas operativas desde archivo CSV.';

    /**
     * Tamaño del lote para inserción masiva en MariaDB.
     */
    private const CHUNK_SIZE = 1000;

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

        $this->info("Iniciando ingesta histórica de visitas operativas desde: {$resolvedPath}");

        $conn = DB::connection('supervisor');

        // Manejo de opción --truncate
        if ($this->option('truncate')) {
            $this->warn('Vaciando tablas fact_visitas y auditoria_visitas_discrepancias...');
            $conn->statement('TRUNCATE TABLE fact_visitas');
            $conn->statement('TRUNCATE TABLE auditoria_visitas_discrepancias');
            $conn->table('sys_cargas')->where('tipo_entidad', 'FACT_VISITAS')->delete();
            $this->warn('Tablas fact_visitas y auditoria_visitas_discrepancias vaciadas.');
        }

        // Conteo de líneas para barra de progreso
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
                    $conn->table('fact_visitas')->insert($batch);
                    $totalInsertadas += count($batch);
                    $progressBar->advance(count($batch));
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $conn->table('fact_visitas')->insert($batch);
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
            Log::error("Fallo crítico en ingesta:historico-visitas", [
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
                'tipo_entidad' => 'FACT_VISITAS_HISTORICO',
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

        $this->info("Ingesta histórica de visitas operativas finalizada con éxito.");
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
            'visita_id' => ['id', 'visita_id', 'visita id', 'visitaid', 'id_visita', 'idvisita'],
            'tipo' => ['tipo', 'tipo_visita', 'tipo visita'],
            'motivo' => ['motivo', 'motivo_visita', 'motivo visita'],
            'cliente' => ['cliente', 'nombre_cliente', 'nombre cliente'],
            'empleado' => ['empleado', 'nombre_empleado', 'nombre empleado', 'vendedor', 'usuario'],
            'fecha' => ['fecha', 'fecha_visita', 'fecha visita'],
            'fecha_norm' => ['fecha_norm', 'fechanorm', 'fecha norm'],
            'monto' => ['monto', 'monto_final', 'monto final', 'montofinal', 'total', 'importe'],
            'check_in' => ['check_in', 'check in', 'checkin', 'hora_inicio', 'hora inicio'],
            'check_out' => ['check_out', 'check out', 'checkout', 'hora_fin', 'hora fin'],
            'tiempo_pdv' => ['tiempo_pdv', 'tiempo pdv', 'tiempopdv', 'duracion', 'tiempo'],
            'zona' => ['zona'],
            'tipo_pago' => ['tipo_pago', 'tipo pago', 'tipopago'],
            'tipo_negocio' => ['tipo_negocio', 'tipo negocio', 'tiponegocio', 'negocio'],
            'cluster' => ['cluster'],
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
     * Normaliza los datos de la fila según las reglas de fact_visitas.
     * NOTA: No asigna 'id'; MariaDB lo autoincrementa automáticamente.
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

        $fechaFull = ExcelCsvParser::normalizeDateTime($fechaRaw) ?? "{$fechaNorm} 00:00:00";

        $visitaId = isset($mapped['visita_id']) && is_numeric($mapped['visita_id'])
            ? (int) $mapped['visita_id']
            : null;

        $checkIn = ExcelCsvParser::normalizeTime($mapped['check_in'] ?? null);
        $checkOut = ExcelCsvParser::normalizeTime($mapped['check_out'] ?? null);
        $tiempoPdv = ExcelCsvParser::normalizeTime($mapped['tiempo_pdv'] ?? null);

        $clienteRaw = $this->cleanString($mapped['cliente'] ?? null, 255);
        $empleadoRaw = $this->cleanString($mapped['empleado'] ?? null, 255);

        return [
            'visita_id' => $visitaId,
            'tipo' => $this->cleanString($mapped['tipo'] ?? null, 50),
            'motivo' => $this->cleanString($mapped['motivo'] ?? null, 100),
            'cliente' => $clienteRaw,
            'cliente_norm' => $this->sanitizeNormString($clienteRaw),
            'empleado' => $empleadoRaw,
            'empleado_norm' => $this->sanitizeNormString($empleadoRaw),
            'fecha' => $fechaFull,
            'fecha_norm' => $fechaNorm,
            'monto' => $this->parseDecimal($mapped['monto'] ?? 0.0),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'tiempo_pdv' => $tiempoPdv,
            'zona' => $this->cleanString($mapped['zona'] ?? null, 100),
            'tipo_pago' => $this->cleanString($mapped['tipo_pago'] ?? null, 50),
            'tipo_negocio' => $this->cleanString($mapped['tipo_negocio'] ?? null, 100),
            'cluster' => $this->cleanString($mapped['cluster'] ?? null, 50),
        ];
    }

    /**
     * Sanitiza y normaliza texto a mayúsculas, sin acentos ni caracteres especiales.
     */
    protected function sanitizeNormString(?string $str): ?string
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

