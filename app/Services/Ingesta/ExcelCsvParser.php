<?php

declare(strict_types=1);

namespace App\Services\Ingesta;

use Exception;
use XMLReader;
use ZipArchive;

class ExcelCsvParser
{
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
     * Parsea un archivo Excel o CSV y retorna todas las filas como arrays asociativos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseToAssociativeRows(string $filePath, int $maxRows = 0, ?string $originalFilename = null): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo no existe en la ruta especificada: {$filePath}");
        }

        $this->verificarNoEsXlsAntiguo($filePath, $originalFilename);

        if ($this->isXlsx($filePath, $originalFilename)) {
            return $this->parseXlsx($filePath, $maxRows);
        }

        return $this->parseCsv($filePath, $maxRows);
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
     * Extrae cabeceras de un archivo .xlsx usando ZipArchive + XMLReader.
     *
     * @return array<int, string>
     */
    protected function extractHeadersXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("No se pudo abrir el archivo .xlsx como archivo ZIP.");
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $sheetXml = $this->getFirstSheetXmlStream($zip);

        if (!$sheetXml) {
            $zip->close();
            throw new Exception("No se encontró la hoja de cálculo en el archivo .xlsx.");
        }

        $reader = new XMLReader();
        $reader->XML($sheetXml);

        $headers = [];
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
                $type = $reader->getAttribute('t');
                $val = $this->readCellValue($reader, $sharedStrings, $type);
                if ($val !== null && trim((string)$val) !== '') {
                    $headers[] = trim((string)$val);
                }
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row' && $inFirstRow) {
                break;
            }
        }

        $reader->close();
        $zip->close();

        return $headers;
    }

    /**
     * Parsea filas de un archivo .xlsx.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseXlsx(string $filePath, int $maxRows = 0): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("No se pudo abrir el archivo .xlsx.");
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $sheetXml = $this->getFirstSheetXmlStream($zip);

        if (!$sheetXml) {
            $zip->close();
            throw new Exception("No se encontró la hoja principal en el archivo .xlsx.");
        }

        $reader = new XMLReader();
        $reader->XML($sheetXml);

        $headers = [];
        $rows = [];
        $currentRowCells = [];
        $isFirstRow = true;
        $rowCount = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $currentRowCells = [];
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $cellRef = $reader->getAttribute('r');
                $colIndex = $this->cellRefToColIndex($cellRef);
                $type = $reader->getAttribute('t');
                $val = $this->readCellValue($reader, $sharedStrings, $type);
                $currentRowCells[$colIndex] = $val;
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row') {
                if ($isFirstRow) {
                    ksort($currentRowCells);
                    foreach ($currentRowCells as $idx => $headerText) {
                        $headers[$idx] = trim((string)$headerText);
                    }
                    $isFirstRow = false;
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

                    if ($hasData) {
                        $rows[] = $assocRow;
                        $rowCount++;
                        if ($maxRows > 0 && $rowCount >= $maxRows) {
                            break;
                        }
                    }
                }
            }
        }

        $reader->close();
        $zip->close();

        return $rows;
    }

    /**
     * Carga el catálogo de cadenas compartidas (sharedStrings.xml) de un .xlsx.
     *
     * @return array<int, string>
     */
    protected function loadSharedStrings(ZipArchive $zip): array
    {
        $sharedStrings = [];
        $xmlContent = $zip->getFromName('xl/sharedStrings.xml');

        if (!$xmlContent) {
            return [];
        }

        $reader = new XMLReader();
        $reader->XML($xmlContent);

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

            if ($inText && ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::SIGNIFICANT_WHITESPACE || $reader->nodeType === XMLReader::CDATA)) {
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
        return $sharedStrings;
    }

    /**
     * Obtiene el stream XML de la primera hoja de cálculo disponible.
     */
    protected function getFirstSheetXmlStream(ZipArchive $zip): ?string
    {
        // Buscar sheet1.xml o sheets en orden
        $candidates = [
            'xl/worksheets/sheet1.xml',
            'xl/worksheets/Sheet1.xml',
            'xl/worksheets/sheet.xml',
        ];

        foreach ($candidates as $cand) {
            $content = $zip->getFromName($cand);
            if ($content !== false) {
                return $content;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $zip->getFromIndex($i);
            }
        }

        return null;
    }

    /**
     * Lee el valor de una celda en XMLReader.
     *
     * @param array<int, string> $sharedStrings
     */
    protected function readCellValue(XMLReader $reader, array &$sharedStrings, ?string $type): mixed
    {
        $val = null;
        $subTree = $reader->readOuterXml();
        if (!$subTree) {
            return null;
        }

        $cellReader = new XMLReader();
        $cellReader->XML($subTree);

        while ($cellReader->read()) {
            if ($cellReader->nodeType === XMLReader::ELEMENT) {
                if ($cellReader->name === 'v') {
                    $raw = $cellReader->readString();
                    if ($type === 's') {
                        $idx = (int)$raw;
                        $val = $sharedStrings[$idx] ?? '';
                    } elseif ($type === 'b') {
                        $val = (bool)$raw;
                    } else {
                        $val = $raw;
                    }
                } elseif ($cellReader->name === 't' && $type === 'inlineStr') {
                    $val = $cellReader->readString();
                }
            }
        }

        $cellReader->close();
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
}
