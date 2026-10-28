<?php

declare(strict_types=1);

namespace App\Services\Ingesta;

use Exception;
use XMLReader;
use ZipArchive;

class ExcelCsvParser
{
    /**
     * Cache en memoria de sharedStrings para no re-parsear el archivo ZIP múltiples veces por petición.
     *
     * @var array<int, string>|null
     */
    protected ?array $cachedSharedStrings = null;

    /**
     * Limpia la memoria de cadenas compartidas al finalizar la ingesta.
     */
    public function clearCache(): void
    {
        $this->cachedSharedStrings = null;
    }
    /**
     * Extrae las cabeceras (primera fila) de un archivo Excel (.xlsx) o CSV.
     *
     * @return array<int, string>
     */
    public function extractHeaders(string $filePath, ?string $originalFilename = null): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe en la ruta especificada: {$filePath}");
        }

        $this->verificarNoEsXlsAntiguo($filePath, $originalFilename);

        if ($this->isXlsx($filePath, $originalFilename)) {
            return $this->extractHeadersXlsx($filePath);
        }

        return $this->extractHeadersCsv($filePath);
    }

    /**
     * Parsea un archivo Excel o CSV vía streaming Generator sin sobrecargar la memoria.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamRows(string $filePath, ?string $originalFilename = null): \Generator
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe en la ruta especificada: {$filePath}");
        }

        $this->verificarNoEsXlsAntiguo($filePath, $originalFilename);

        if ($this->isXlsx($filePath, $originalFilename)) {
            yield from $this->streamXlsx($filePath);
        } else {
            yield from $this->streamCsv($filePath);
        }
    }

    /**
     * Parsea un archivo Excel o CSV y retorna todas las filas como arrays asociativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseToAssociativeRows(string $filePath, int $maxRows = 0, ?string $originalFilename = null): array
    {
        $rows = [];
        $count = 0;
        foreach ($this->streamRows($filePath, $originalFilename) as $row) {
            $rows[] = $row;
            $count++;
            if ($maxRows > 0 && $count >= $maxRows) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Determina con precisión si el archivo es un libro de Excel moderno (.xlsx / .xlsm).
     * Combina verificación de extensión original/ruta y detección de Magic Bytes (ZIP PK\x03\x04).
     */
    public function isXlsx(string $filePath, ?string $originalFilename = null): bool
    {
        if ($originalFilename) {
            $extOrig = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if (in_array($extOrig, ['xlsx', 'xlsm', 'xltx', 'xltm'], true)) {
                return true;
            }
        }

        $extPath = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extPath, ['xlsx', 'xlsm', 'xltx', 'xltm'], true)) {
            return true;
        }

        if (file_exists($filePath)) {
            $handle = @fopen($filePath, 'rb');
            if ($handle) {
                $magic = fread($handle, 4);
                fclose($handle);
                if (bin2hex($magic) === '504b0304' || $magic === "PK\x03\x04") {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detecta si el archivo es un binario OLE2 (.xls antiguo) para alertar al usuario en lugar de fallar silenciosamente.
     */
    protected function verificarNoEsXlsAntiguo(string $filePath, ?string $originalFilename = null): void
    {
        if (file_exists($filePath)) {
            $handle = @fopen($filePath, 'rb');
            if ($handle) {
                $magic = fread($handle, 8);
                fclose($handle);
                if (bin2hex($magic) === 'd0cf11e0a1b11ae1' || $magic === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                    throw new Exception("El archivo está en formato Excel binario antiguo (.xls). Por favor guárdalo como formato Excel moderno (.xlsx) o .csv.");
                }
            }
        }
    }

    /**
     * Obtiene el nombre de la entrada de la primera hoja de cálculo dentro del ZIP (.xlsx).
     */
    protected function getFirstSheetEntryName(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("No se pudo abrir el archivo .xlsx.");
        }

        $candidates = [
            'xl/worksheets/sheet1.xml',
            'xl/worksheets/Sheet1.xml',
            'xl/worksheets/sheet.xml',
        ];

        foreach ($candidates as $cand) {
            if ($zip->locateName($cand) !== false) {
                $zip->close();
                return $cand;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $zip->close();
                return $name;
            }
        }

        $zip->close();
        throw new Exception("No se encontró la hoja de cálculo en el archivo .xlsx.");
    }

    /**
     * Carga el catálogo de cadenas compartidas (sharedStrings.xml) de un .xlsx vía streaming sin cargar todo en memoria.
     *
     * @return array<int, string>
     */
    protected function loadSharedStrings(string $filePath): array
    {
        if ($this->cachedSharedStrings !== null) {
            return $this->cachedSharedStrings;
        }

        $zipPath = str_replace('\\', '/', realpath($filePath) ?: $filePath);
        $ssUri = "zip://{$zipPath}#xl/sharedStrings.xml";

        $reader = new XMLReader();
        if (!@$reader->open($ssUri)) {
            $this->cachedSharedStrings = [];
            return [];
        }

        $sharedStrings = [];
        $currentIndex = 0;
        $currentText = '';
        $inText = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                $currentText = '';
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 't') {
                $inText = true;
            }

            if ($inText && in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::SIGNIFICANT_WHITESPACE, XMLReader::CDATA])) {
                $currentText .= $reader->value;
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 't') {
                $inText = false;
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'si') {
                $sharedStrings[$currentIndex] = $currentText;
                $currentIndex++;
            }
        }

        $reader->close();
        $this->cachedSharedStrings = $sharedStrings;
        return $sharedStrings;
    }

    /**
     * Extrae cabeceras de un archivo .xlsx usando XMLReader en streaming directo del ZIP.
     *
     * @return array<int, string>
     */
    protected function extractHeadersXlsx(string $filePath): array
    {
        $sharedStrings = $this->loadSharedStrings($filePath);
        $sheetEntry = $this->getFirstSheetEntryName($filePath);
        $zipPath = str_replace('\\', '/', realpath($filePath) ?: $filePath);

        $reader = new XMLReader();
        if (!@$reader->open("zip://{$zipPath}#{$sheetEntry}")) {
            throw new Exception("No se pudo abrir la hoja de cálculo en el archivo .xlsx.");
        }

        $headers = [];
        $currentRowCells = [];
        $inFirstRow = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $rowNum = $reader->getAttribute('r');
                if ($rowNum === '1' || empty($headers)) {
                    $inFirstRow = true;
                } else {
                    break;
                }
            }

            if ($inFirstRow && $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $cellRef = $reader->getAttribute('r');
                $colIndex = $this->cellRefToColIndex($cellRef);
                $val = $this->readCellValInline($reader, $sharedStrings);
                if ($val !== null && trim((string)$val) !== '') {
                    $currentRowCells[$colIndex] = trim((string)$val);
                }
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row' && $inFirstRow) {
                break;
            }
        }

        $reader->close();
        ksort($currentRowCells);
        return array_values($currentRowCells);
    }

    /**
     * Stream de filas de un archivo .xlsx sin cargar todo el documento en RAM.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    protected function streamXlsx(string $filePath): \Generator
    {
        $sharedStrings = $this->loadSharedStrings($filePath);
        $sheetEntry = $this->getFirstSheetEntryName($filePath);
        $zipPath = str_replace('\\', '/', realpath($filePath) ?: $filePath);

        $reader = new XMLReader();
        if (!@$reader->open("zip://{$zipPath}#{$sheetEntry}")) {
            throw new Exception("No se pudo abrir la hoja de cálculo en el archivo .xlsx.");
        }

        $headers = [];
        $currentRowCells = [];
        $isFirstRow = true;
        $yieldedCount = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $currentRowCells = [];
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $cellRef = $reader->getAttribute('r');
                $colIndex = $this->cellRefToColIndex($cellRef);
                $val = $this->readCellValInline($reader, $sharedStrings);
                $currentRowCells[$colIndex] = $val;
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row') {
                if ($isFirstRow) {
                    ksort($currentRowCells);
                    foreach ($currentRowCells as $idx => $headerText) {
                        $headers[$idx] = trim((string)$headerText);
                    }
                    $isFirstRow = false;
                    $currentRowCells = [];
                } else {
                    $assocRow = [];
                    $hasData = false;
                    foreach ($headers as $colIdx => $headerName) {
                        $cellVal = $currentRowCells[$colIdx] ?? null;
                        if ($cellVal !== null && trim((string)$cellVal) !== '') {
                            $hasData = true;
                        }
                        $assocRow[$headerName] = $cellVal;
                    }
                    $currentRowCells = [];

                    if ($hasData) {
                        $yieldedCount++;
                        if ($yieldedCount % 5000 === 0) {
                            gc_collect_cycles();
                        }
                        yield $assocRow;
                    }
                }
            }
        }

        $reader->close();
    }

    /**
     * Parsea filas de un archivo .xlsx (compatibilidad regresiva usando stream).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseXlsx(string $filePath, int $maxRows = 0): array
    {
        $rows = [];
        $count = 0;
        foreach ($this->streamXlsx($filePath) as $row) {
            $rows[] = $row;
            $count++;
            if ($maxRows > 0 && $count >= $maxRows) {
                break;
            }
        }
        return $rows;
    }

    /**
     * Lee el valor de una celda XML sin crear sub-instancias de XMLReader ni alocar memoria extra.
     *
     * @param array<int, string> $sharedStrings
     */
    protected function readCellValInline(XMLReader $reader, array &$sharedStrings): mixed
    {
        $type = $reader->getAttribute('t');
        $isEmpty = $reader->isEmptyElement;
        if ($isEmpty) {
            return null;
        }

        $val = null;
        $depth = $reader->depth;
        while ($reader->read()) {
            if ($reader->depth <= $depth) {
                break;
            }
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->name === 'v') {
                    $raw = $reader->readString();
                    if ($type === 's') {
                        $val = $sharedStrings[(int)$raw] ?? '';
                    } elseif ($type === 'b') {
                        $val = (bool)$raw;
                    } else {
                        $val = $raw;
                    }
                } elseif ($reader->name === 't') {
                    $val = $reader->readString();
                }
            }
        }

        return $val;
    }

    /**
     * Convierte referencias de columna como 'A1' -> 0, 'B1' -> 1, 'AA1' -> 26.
     */
    protected function cellRefToColIndex(?string $ref): int
    {
        if (!$ref) {
            return 0;
        }

        preg_match('/^([A-Z]+)/', strtoupper($ref), $matches);
        if (empty($matches[1])) {
            return 0;
        }

        $letters = $matches[1];
        $len = strlen($letters);
        $index = 0;

        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * Extrae cabeceras de un archivo CSV con detección automática de delimitador.
     *
     * @return array<int, string>
     */
    protected function extractHeadersCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV para lectura.");
        }

        $sample = fgets($handle, 4096);
        fclose($handle);

        if (!$sample) {
            return [];
        }

        // Limpiar BOM UTF-8 (\xEF\xBB\xBF)
        $sample = preg_replace('/^\xEF\xBB\xBF/', '', $sample);

        $delimiter = $this->detectCsvDelimiter($sample);

        $handle = fopen($filePath, 'r');
        // Descartar BOM en el handle
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        fclose($handle);

        if (!$headers) {
            return [];
        }

        return array_map(fn($h) => trim((string)$h), $headers);
    }

    /**
     * Parsea filas de un archivo CSV.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseCsv(string $filePath, int $maxRows = 0): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV.");
        }

        // Muestrear para detectar delimitador y codificación
        $sample = fgets($handle, 4096);
        $encoding = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        $delimiter = $this->detectCsvDelimiter($sample);

        fseek($handle, 0);
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (!$headers) {
            fclose($handle);
            return [];
        }

        $headers = array_map(function ($h) use ($encoding) {
            $txt = $encoding !== 'UTF-8' ? mb_convert_encoding((string)$h, 'UTF-8', $encoding) : (string)$h;
            return trim($txt);
        }, $headers);

        $rows = [];
        $rowCount = 0;

        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (empty($data) || (count($data) === 1 && $data[0] === null)) {
                continue;
            }

            $assoc = [];
            $hasData = false;

            foreach ($headers as $idx => $hName) {
                $val = $data[$idx] ?? null;
                if ($val !== null && $encoding !== 'UTF-8') {
                    $val = mb_convert_encoding((string)$val, 'UTF-8', $encoding);
                }
                if ($val !== null && trim((string)$val) !== '') {
                    $hasData = true;
                }
                $assoc[$hName] = $val !== null ? trim((string)$val) : null;
            }

            if ($hasData) {
                $rows[] = $assoc;
                $rowCount++;
                if ($maxRows > 0 && $rowCount >= $maxRows) {
                    break;
                }
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Stream de filas de un archivo CSV línea por línea vía Generator.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    protected function streamCsv(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("No se pudo abrir el archivo CSV para lectura.");
        }

        // Muestrear para detectar delimitador y codificación
        $sample = fgets($handle, 4096);
        $encoding = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        $delimiter = $this->detectCsvDelimiter($sample);

        fseek($handle, 0);
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (!$headers) {
            fclose($handle);
            return;
        }

        $headers = array_map(function ($h) use ($encoding) {
            $txt = $encoding !== 'UTF-8' ? mb_convert_encoding((string)$h, 'UTF-8', $encoding) : (string)$h;
            return trim($txt);
        }, $headers);

        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (empty($data) || (count($data) === 1 && $data[0] === null)) {
                continue;
            }

            $assoc = [];
            $hasData = false;

            foreach ($headers as $idx => $hName) {
                $val = $data[$idx] ?? null;
                if ($val !== null && $encoding !== 'UTF-8') {
                    $val = mb_convert_encoding((string)$val, 'UTF-8', $encoding);
                }
                if ($val !== null && trim((string)$val) !== '') {
                    $hasData = true;
                }
                $assoc[$hName] = $val !== null ? trim((string)$val) : null;
            }

            if ($hasData) {
                yield $assoc;
            }
        }

        fclose($handle);
    }

    /**
     * Detecta el delimitador de CSV más probable en una línea de muestra.
     */
    protected function detectCsvDelimiter(string $sample): string
    {
        $delimiters = [';', ',', "\t", '|'];
        $bestDelimiter = ';';
        $maxCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($sample, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Convierte número serial de fecha de Excel (ej: 45212) o string a 'YYYY-MM-DD'.
     */
    public static function normalizeDate(mixed $dateValue): ?string
    {
        if ($dateValue === null) {
            return null;
        }

        $str = trim((string)$dateValue);
        if ($str === '' || $str === '1900-01-01' || strtolower($str) === 'none' || strtolower($str) === 'null') {
            return null;
        }

        // Si ya viene con formato YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        // Si viene con formato DD/MM/YYYY o DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        // Si es número serial de Excel (ej: 45000 a 46000)
        if (is_numeric($str) && (float)$str > 30000 && (float)$str < 60000) {
            $days = (int)floor((float)$str);
            // El día 1 de Excel es 1899-12-30 (por el bug bisiesto de Lotus 1-2-3 en 1900)
            // Se usa gmdate() para evitar desfase de 1 día por zona horaria negativa (ej: UTC-4)
            $unixTime = ($days - 25569) * 86400;
            return gmdate('Y-m-d', $unixTime);
        }

        // Intentar strtotime
        $ts = strtotime($str);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return $str;
    }

    /**
     * Convierte valor de fecha y hora a 'YYYY-MM-DD HH:MM:SS' para columnas DATETIME de MySQL.
     */
    public static function normalizeDateTime(mixed $dateTimeValue): ?string
    {
        if ($dateTimeValue === null) {
            return null;
        }

        $str = trim((string)$dateTimeValue);
        if ($str === '' || $str === '1900-01-01' || strtolower($str) === 'none' || strtolower($str) === 'null') {
            return null;
        }

        // Si viene con formato DD/MM/YYYY HH:MM:SS o DD-MM-YYYY HH:MM:SS
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $str, $m)) {
            $sec = isset($m[6]) ? (int)$m[6] : 0;
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int)$m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5], $sec);
        }

        // Si viene con formato YYYY-MM-DD HH:MM:SS
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $str, $m)) {
            $sec = isset($m[6]) ? (int)$m[6] : 0;
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5], $sec);
        }

        // Si solo viene fecha (DD/MM/YYYY o YYYY-MM-DD)
        $dateOnly = self::normalizeDate($str);
        if ($dateOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOnly)) {
            return "{$dateOnly} 00:00:00";
        }

        return $dateOnly;
    }

    /**
     * Convierte valor de hora (número serial de tiempo de Excel, ej: 0.6701388... o string '16:05:00') a 'HH:MM:SS' de máx 10 caracteres.
     */
    public static function normalizeTime(mixed $timeValue): ?string
    {
        if ($timeValue === null) {
            return null;
        }

        $str = trim((string)$timeValue);
        if ($str === '' || strtolower($str) === 'null' || strtolower($str) === 'none') {
            return null;
        }

        // Si es número decimal entre 0 y 1 (serial de tiempo de Excel, ej: 0.67013888888888884)
        if (is_numeric($str) && (float)$str >= 0 && (float)$str < 1.0) {
            $totalSeconds = (int)round((float)$str * 86400);
            $hours = (int)floor($totalSeconds / 3600) % 24;
            $minutes = (int)floor(($totalSeconds % 3600) / 60);
            $seconds = $totalSeconds % 60;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        // Si ya viene con formato HH:MM:SS o HH:MM
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $str, $m)) {
            $hours = (int)$m[1] % 24;
            $minutes = (int)$m[2] % 60;
            $seconds = isset($m[3]) ? ((int)$m[3] % 60) : 0;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        // Si viene con fecha y hora completa, extraer solo hora
        if (preg_match('/\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $str, $m)) {
            $hours = (int)$m[1] % 24;
            $minutes = (int)$m[2] % 60;
            $seconds = isset($m[3]) ? ((int)$m[3] % 60) : 0;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        // Fallback: recortar a 10 caracteres para respetar la longitud de columna VARCHAR(10)
        return substr($str, 0, 10);
    }
}
