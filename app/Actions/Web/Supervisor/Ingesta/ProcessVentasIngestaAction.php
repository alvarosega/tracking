<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use App\Services\Ingesta\ExcelCsvParser;
use App\Services\Ingesta\IngestaValidator;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProcessVentasIngestaAction
{
    /**
     * Columnas canónicas de inserción de fact_ventas (excluyendo la clave autoincremental 'id').
     *
     * @var array<int, string>
     */
    private const CANONICAL_FACT_VENTAS_COLUMNS = [
        'fecha', 'fecha_norm', 'hora', 'cliente_id', 'cliente', 'cliente_norm',
        'oficina', 'vendedor', 'vendedor_id', 'vendedor_norm', 'vendedor_username',
        'distribuidor', 'distribuidor_id', 'distribuidor_username', 'zona', 'tipo_pago',
        'producto_id', 'codigo', 'producto', 'categoria', 'dp_group', 'cantidad',
        'cantidad_cajas', 'precio_unitario', 'precio_paquete', 'monto', 'descuento',
        'descuento2', 'descuento_socio', 'descuento_cliente', 'monto_final', 'precio',
        'proveedor', 'tipo_negocio', 'nro_factura', 'cuf', 'razon_social', 'nit', 'mes',
        'supervisor', 'venta_id', 'pre_venta_id', 'ruta', 'dia_visita', 'precio_lista',
        'canal', 'tipo_objeto', 'mercado', 'empresa', 'jerarquia1', 'jerarquia2',
        'jerarquia3', 'escala_descuento', 'escala_producto', 'cluster_t', 'cluster_c',
        'descripcion_unificada', 'marca', 'revertida', 'origen',
    ];

    /**
     * Columnas decimales con precisión de alta escala (6 decimales).
     *
     * @var array<int, string>
     */
    private const DECIMAL_COLUMNS = [
        'monto',
        'monto_final',
        'precio_unitario',
        'precio_paquete',
        'descuento',
        'descuento2',
        'descuento_socio',
        'descuento_cliente',
        'precio',
        'cantidad_cajas',
    ];

    /**
     * Límites de longitud VARCHAR en MariaDB para truncado de seguridad.
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
     * Tamaño de lote para inserción transaccional masiva.
     */
    private const CHUNK_SIZE = 500;

    public function __construct(
        protected ExcelCsvParser $parser,
        protected IngestaValidator $validator
    ) {}

    /**
     * Procesa la ingesta de archivos de Ventas (FACT_VENTAS) de cortes diarios/parciales.
     * Modelo estrictamente aditivo con reconciliación por multiconjuntos (huella digital MD5):
     * - NO deduplica intra-archivo (se preservan todas las líneas comerciales válidas).
     * - NO ejecuta DELETE ni UPDATE en fact_ventas (tabla de hechos inmutable).
     * - Reconcilia duplicados mediante mapa de frecuencias de huellas MD5 contra MariaDB.
     * - Inserta registros nuevos en bloques atómicos de 500.
     * - Compila registros no encontrados en el archivo como alertas de discrepancia.
     *
     * @param UploadedFile|string $file
     * @param string|null $originalFilename
     * @return array<string, mixed>
     * @throws Exception
     */
    public function execute(UploadedFile|string $file, ?string $originalFilename = null): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(900);

        $filePath = is_string($file) ? $file : $file->getRealPath();
        $filename = $originalFilename ?? (is_string($file) ? basename($file) : $file->getClientOriginalName());

        $headers = $this->parser->extractHeaders($filePath, $filename);
        $this->validator->validateSchema($headers, 'FACT_VENTAS');

        $fileHash = hash_file('sha256', $filePath) ?: md5($filename);

        /** @var ConnectionInterface $conn */
        $conn = DB::connection('supervisor');
        $this->configurarTimeoutsSesion($conn);

        // Columnas existentes en fact_ventas (excluyendo id)
        $colsDb = $this->obtenerColumnasFactVentas($conn);
        $colsDbFlip = array_flip($colsDb);

        // 1. Lectura, normalización completa y recolección de huellas del archivo (sin deduplicación destructiva)
        $filasArchivo = [];
        $fechasValidasSet = [];
        $totalLeidas = 0;
        $montoTotalArchivo = 0.0;

        foreach ($this->parser->streamRows($filePath, $filename) as $r) {
            $totalLeidas++;
            $mapped = $this->validator->mapRow($r, 'FACT_VENTAS');

            $ventaId = isset($mapped['venta_id']) ? (int) $mapped['venta_id'] : 0;
            $productoId = isset($mapped['producto_id']) ? (int) $mapped['producto_id'] : 0;

            if ($ventaId <= 0 || $productoId <= 0) {
                continue;
            }

            // Normalizar fecha_norm y fecha (YYYY-MM-DD)
            $fechaRaw = $mapped['fecha_norm'] ?? ($mapped['fecha'] ?? null);
            $fechaNorm = $this->parseFechaNorm($fechaRaw);
            if (!$fechaNorm) {
                continue;
            }

            $mapped['fecha_norm'] = $fechaNorm;
            $mapped['fecha'] = $fechaNorm;
            $fechasValidasSet[$fechaNorm] = true;

            // Sanitizar cliente_id (columna obligatoria NOT NULL en BD)
            $mapped['cliente_id'] = isset($mapped['cliente_id']) && is_numeric($mapped['cliente_id']) ? (int) $mapped['cliente_id'] : 0;

            // Normalización analítica de texto (mayúsculas, sin acentos ni espacios redundantes)
            $mapped['cliente_norm'] = $this->normalizarTextoLimpio($mapped['cliente_norm'] ?? ($mapped['cliente'] ?? ''));
            $mapped['vendedor_norm'] = $this->normalizarTextoLimpio($mapped['vendedor_norm'] ?? ($mapped['vendedor'] ?? ''));

            // Normalización monetaria y decimal exacta (round a 6 decimales, defecto 0.000000, nunca null)
            foreach (self::DECIMAL_COLUMNS as $dc) {
                $mapped[$dc] = array_key_exists($dc, $mapped)
                    ? $this->parseMonetary($mapped[$dc])
                    : 0.0;
            }

            // Normalizar cantidad
            $mapped['cantidad'] = isset($mapped['cantidad']) && is_numeric($mapped['cantidad']) ? (int) $mapped['cantidad'] : 0;

            // Normalizar revertida
            $rev = isset($mapped['revertida']) ? trim((string) $mapped['revertida']) : '';
            $mapped['revertida'] = in_array(strtolower($rev), ['si', 'sí', 'true', '1', 'anulada', 'revertida', 'yes'], true) ? 'Si' : 'No';

            // Normalizar hora (convierte número fraccionario de Excel o string a HH:MM:SS)
            $mapped['hora'] = $this->parseHora($mapped['hora'] ?? null);

            // Derivar mes a partir de fecha_norm si viene vacío
            if (empty($mapped['mes'])) {
                $mapped['mes'] = (int) date('m', strtotime($fechaNorm));
            }

            // Asignar origen por defecto
            if (empty($mapped['origen'])) {
                $mapped['origen'] = 'VEN';
            }

            // Truncado de seguridad contra límites VARCHAR
            foreach (self::COLUMN_LIMITS as $colName => $maxLen) {
                if (isset($mapped[$colName]) && is_string($mapped[$colName]) && mb_strlen($mapped[$colName], 'UTF-8') > $maxLen) {
                    $mapped[$colName] = mb_substr($mapped[$colName], 0, $maxLen, 'UTF-8');
                }
            }

            // Armonizar precio y precio_unitario
            $pUnit = (float) $mapped['precio_unitario'];
            $pVal = (float) $mapped['precio'];
            if ($pUnit <= 0.0 && $pVal > 0.0) {
                $mapped['precio_unitario'] = $pVal;
            } elseif ($pVal <= 0.0 && $pUnit > 0.0) {
                $mapped['precio'] = $pUnit;
            } elseif ($pUnit <= 0.0 && $pVal <= 0.0 && (int) $mapped['cantidad'] > 0 && (float) $mapped['monto_final'] > 0.0) {
                $mapped['precio_unitario'] = round((float) $mapped['monto_final'] / (int) $mapped['cantidad'], 6);
                $mapped['precio'] = $mapped['precio_unitario'];
            }

            // Generar huella digital invariable para reconciliación
            $cantidadInt = (int) $mapped['cantidad'];
            $montoFinalFloat = (float) $mapped['monto_final'];

            $hash = $this->generarHashFila($fechaNorm, $ventaId, $productoId, $cantidadInt, $montoFinalFloat);

            // Filtrar exclusivamente columnas presentes en fact_ventas
            $dbRow = [];
            foreach ($mapped as $colName => $colVal) {
                if (isset($colsDbFlip[$colName])) {
                    $dbRow[$colName] = $colVal;
                }
            }

            $filasArchivo[] = [
                'row' => $dbRow,
                'hash' => $hash,
                'monto_final' => $montoFinalFloat,
            ];

            $montoTotalArchivo += $montoFinalFloat;
        }

        if (empty($filasArchivo)) {
            $this->parser->clearCache();
            return [
                'status' => 'success',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => 0,
                'filas_insertadas' => 0,
                'filas_nuevas' => 0,
                'nuevos' => 0,
                'filas_actualizadas' => 0,
                'filas_alteradas' => 0,
                'modificados' => 0,
                'filas_eliminadas' => 0,
                'eliminadas' => 0,
                'cancelados' => 0,
                'filas_con_discrepancia' => 0,
                'filas_identicas' => 0,
                'sin_cambios' => 0,
                'filas_ignoradas' => 0,
                'monto_total' => 0.0,
                'volumen_total' => 0.0,
                'monto_nuevas' => 0.0,
                'rango_fechas' => '-',
                'fecha_operativa' => '-',
                'alertas' => [],
            ];
        }

        $fechasValidas = array_keys($fechasValidasSet);
        sort($fechasValidas);
        $fechaMin = $fechasValidas[0];
        $fechaMax = end($fechasValidas);

        // 2. Consulta a MariaDB de las ventas existentes para la ventana de fechas operativas
        if (count($fechasValidas) <= 200) {
            $placeholders = implode(',', array_fill(0, count($fechasValidas), '?'));
            $filasDb = $conn->select("
                SELECT id, fecha_norm, venta_id, producto_id, cantidad, monto_final, precio_unitario, precio
                FROM fact_ventas
                WHERE fecha_norm IN ({$placeholders})
            ", $fechasValidas);
        } else {
            $rawDb = $conn->select("
                SELECT id, fecha_norm, venta_id, producto_id, cantidad, monto_final, precio_unitario, precio
                FROM fact_ventas
                WHERE fecha_norm >= ? AND fecha_norm <= ?
            ", [$fechaMin, $fechaMax]);
            $fechasFlip = array_flip($fechasValidas);
            $filasDb = array_filter($rawDb, fn($r) => isset($fechasFlip[substr((string) $r->fecha_norm, 0, 10)]));
        }

        // 3. Construir mapa de frecuencias de multiconjunto en memoria
        $dbFrecuencias = [];
        $dbRecordsPorHash = [];

        foreach ($filasDb as $r) {
            $fNorm = substr((string) $r->fecha_norm, 0, 10);
            $vId = (int) $r->venta_id;
            $pId = (int) $r->producto_id;
            $cant = (int) $r->cantidad;
            $mFinal = (float) $r->monto_final;

            $h = $this->generarHashFila($fNorm, $vId, $pId, $cant, $mFinal);
            $dbFrecuencias[$h] = ($dbFrecuencias[$h] ?? 0) + 1;
            $dbRecordsPorHash[$h][] = $r;
        }
        unset($filasDb);

        // 4. Clasificar filas leídas (Multiset reconciliation)
        $filasAInsertar = [];
        $filasIgnoradas = 0;
        $montoNuevas = 0.0;

        foreach ($filasArchivo as $item) {
            $h = $item['hash'];
            if (($dbFrecuencias[$h] ?? 0) > 0) {
                // Duplicado exacto ya persistido en BD: decrementar contador de multiconjunto
                $dbFrecuencias[$h]--;
                $filasIgnoradas++;
            } else {
                // Venta nueva legítima
                $filasAInsertar[] = $item['row'];
                $montoNuevas += (float) $item['monto_final'];
            }
        }

        // 5. Compilar alertas de registros en BD ausentes en el archivo (sin borrarlos ni modificarlos)
        $alertas = [];
        foreach ($dbFrecuencias as $h => $remainingCount) {
            if ($remainingCount > 0 && isset($dbRecordsPorHash[$h])) {
                $unmatched = array_slice($dbRecordsPorHash[$h], 0, $remainingCount);
                foreach ($unmatched as $r) {
                    $pUnit = (float) ($r->precio_unitario ?? 0);
                    if ($pUnit <= 0.0 && !empty($r->precio)) {
                        $pUnit = (float) $r->precio;
                    }
                    $alertas[] = [
                        'venta_id' => (int) $r->venta_id,
                        'producto_id' => (int) $r->producto_id,
                        'fecha' => substr((string) $r->fecha_norm, 0, 10),
                        'cantidad' => (int) $r->cantidad,
                        'monto_final' => round((float) $r->monto_final, 6),
                        'precio_unitario' => round($pUnit, 6),
                        'motivo' => 'Registro presente en base de datos pero ausente en el archivo cargado',
                    ];
                }
            }
        }
        $totalAlertas = count($alertas);

        // 6. Inserción transaccional atómica en bloques de 500
        $totalNuevas = count($filasAInsertar);
        if ($totalNuevas > 0) {
            $conn->transaction(function () use ($conn, $filasAInsertar, $colsDb) {
                $uniformRecords = [];
                foreach ($filasAInsertar as $rec) {
                    $uRow = [];
                    foreach ($colsDb as $ck) {
                        if (array_key_exists($ck, $rec)) {
                            $uRow[$ck] = $rec[$ck];
                        } elseif (in_array($ck, self::DECIMAL_COLUMNS, true)) {
                            $uRow[$ck] = 0.0;
                        } else {
                            $uRow[$ck] = null;
                        }
                    }
                    $uniformRecords[] = $uRow;
                }

                $chunks = array_chunk($uniformRecords, self::CHUNK_SIZE);
                foreach ($chunks as $chunk) {
                    $conn->table('fact_ventas')->insert($chunk);
                }
            });
        }

        $fechaMinStr = $fechaMin ?? date('Y-m-d');
        $fechaMaxStr = $fechaMax ?? date('Y-m-d');
        $rangoStr = "{$fechaMinStr} al {$fechaMaxStr}";

        // Reconectar la BD tras la transacción para mantener viva la sesión
        try {
            DB::connection('supervisor')->reconnect();
            DB::reconnect();
        } catch (\Throwable) {}

        // 7. Registro de bitácora en sys_cargas
        try {
            $maxId = $conn->table('sys_cargas')->max('id');
            $nextId = $maxId ? ((int) $maxId + 1) : 1;

            $cargaData = [
                'id' => $nextId,
                'archivo_original' => $filename,
                'archivo_almacenado' => $filePath,
                'sha256' => $fileHash,
                'tipo_entidad' => 'FACT_VENTAS',
                'filas_leidas' => $totalLeidas,
                'filas_afectadas' => $totalNuevas,
                'filas_nuevas' => $totalNuevas,
                'filas_actualizadas' => 0,
                'filas_ignoradas' => $filasIgnoradas,
                'filas_con_discrepancia' => $totalAlertas,
                'rango_inicio' => $fechaMinStr,
                'rango_fin' => $fechaMaxStr,
                'fecha_ingesta' => now(),
            ];

            if (Schema::connection('supervisor')->hasColumn('sys_cargas', 'estado')) {
                $cargaData['estado'] = 'COMPLETADO';
            }

            $conn->table('sys_cargas')->insert($cargaData);
        } catch (\Throwable $eSys) {
            Log::warning("No se pudo registrar en sys_cargas: " . $eSys->getMessage());
        }

        $this->parser->clearCache();

        // 8. Payload de respuesta compatible con Upload.vue
        return [
            'status' => 'success',
            'filas_leidas' => $totalLeidas,
            'filas_afectadas' => $totalNuevas,
            'filas_insertadas' => $totalNuevas,
            'filas_nuevas' => $totalNuevas,
            'nuevos' => $totalNuevas,
            'filas_actualizadas' => 0,
            'filas_alteradas' => 0,
            'modificados' => 0,
            'filas_eliminadas' => 0,
            'eliminadas' => 0,
            'cancelados' => $totalAlertas,
            'filas_con_discrepancia' => $totalAlertas,
            'filas_identicas' => $filasIgnoradas,
            'sin_cambios' => $filasIgnoradas,
            'filas_ignoradas' => $filasIgnoradas,
            'monto_total' => round($montoTotalArchivo, 2),
            'volumen_total' => round($montoTotalArchivo, 2),
            'monto_nuevas' => round($montoNuevas, 2),
            'rango_fechas' => $rangoStr,
            'fecha_operativa' => $rangoStr,
            'alertas' => $alertas,
        ];
    }

    /**
     * Genera la huella digital invariable de una fila para reconciliación por multiconjuntos.
     */
    protected function generarHashFila(string $fechaNorm, int $ventaId, int $productoId, int $cantidad, float $montoFinal): string
    {
        $montoStr = sprintf('%.4f', $montoFinal);
        return md5("{$fechaNorm}|{$ventaId}|{$productoId}|{$cantidad}|{$montoStr}");
    }

    /**
     * Parsea fechas hacia formato 'Y-m-d'.
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

        // Formato dd/mm/YYYY o dd-mm-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Formato YYYY-mm-dd o YYYY/mm/dd
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Serial de fecha de Excel
        if (is_numeric($str)) {
            $days = (float) $str;
            if ($days > 30000 && $days < 60000) {
                $unixTime = ($days - 25569) * 86400;
                return gmdate('Y-m-d', (int) $unixTime);
            }
        }

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

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $trimmed);
        if ($translit === false) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $trimmed);
            if ($translit === false) {
                $translit = $trimmed;
            }
        }

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

        if (is_numeric($str) && (float) $str >= 0 && (float) $str < 1) {
            $totalSeconds = (int) round((float) $str * 86400);
            $h = floor($totalSeconds / 3600);
            $m = floor(($totalSeconds % 3600) / 60);
            $s = $totalSeconds % 60;
            return sprintf('%02d:%02d:%02d', (int) $h, (int) $m, (int) $s);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $str, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
        }

        return mb_substr($str, 0, 10, 'UTF-8');
    }

    /**
     * Obtiene el listado de columnas reales de fact_ventas excluyendo 'id'.
     *
     * @param ConnectionInterface $conn
     * @return array<int, string>
     */
    protected function obtenerColumnasFactVentas(ConnectionInterface $conn): array
    {
        try {
            $cols = Schema::connection('supervisor')->getColumnListing('fact_ventas');
        } catch (\Throwable) {
            $cols = [];
        }

        if (empty($cols)) {
            try {
                $colDbRows = $conn->select("
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fact_ventas'
                ");
                $cols = array_map(fn($c) => $c->COLUMN_NAME ?? $c->column_name, $colDbRows);
            } catch (\Throwable) {
                $cols = [];
            }
        }

        if (empty($cols)) {
            $cols = self::CANONICAL_FACT_VENTAS_COLUMNS;
        }

        $filtered = array_filter($cols, fn($c) => strtolower((string) $c) !== 'id');

        return array_values($filtered);
    }

    /**
     * Configura timeouts de sesión en MariaDB para operaciones masivas.
     */
    protected function configurarTimeoutsSesion(ConnectionInterface $conn): void
    {
        try {
            $conn->statement("SET SESSION wait_timeout = 600");
            $conn->statement("SET SESSION net_read_timeout = 600");
            $conn->statement("SET SESSION net_write_timeout = 600");
        } catch (\Throwable) {}
    }
}
