<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use RuntimeException;

class PlanRuteoSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('seeders/csv/plan_ruteo.csv');

        if (!file_exists($filePath)) {
            throw new RuntimeException("Archivo CSV no encontrado: {$filePath}");
        }

        $file = new SplFileObject($filePath, 'r');
        
        // Detección dinámica de delimitador (, o ;)
        $firstLine = $file->fgets();
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        $file->rewind();

        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );
        $file->setCsvControl($delimiter);

        $rawHeader = $file->fgetcsv();

        if (!$rawHeader) {
            throw new RuntimeException("El archivo CSV está vacío o sin encabezados.");
        }

        // Mapeo canónico de encabezados (ignora mayúsculas, espacios y caracteres invisibles/BOM)
        $headerMap = [];
        foreach ($rawHeader as $idx => $col) {
            $normalizedKey = $this->normalizarClaveColumna($col);
            $headerMap[$normalizedKey] = $idx;
        }

        // Limpieza atómica de la tabla
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('plan_ruteo')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        $batch = [];
        $processedClients = [];
        $now = now();

        while (!$file->eof()) {
            $row = $file->fgetcsv();

            if (empty($row) || count($row) < count($headerMap)) {
                continue;
            }

            // Extracción con fallback tolerante
            $clientId = (int) $this->obtenerValorColumna($row, $headerMap, ['cliente_id', 'clienteid', 'id_cliente']);
            if ($clientId <= 0 || isset($processedClients[$clientId])) {
                continue;
            }

            $rawLat = trim((string) $this->obtenerValorColumna($row, $headerMap, ['latitud', 'latitude']));
            $rawLon = trim((string) $this->obtenerValorColumna($row, $headerMap, ['longitud', 'longitude']));

            // Validación estricta de coordenadas numéricas
            if ($rawLat === '' || $rawLon === '' || !is_numeric($rawLat) || !is_numeric($rawLon)) {
                continue;
            }

            $processedClients[$clientId] = true;

            $batch[] = [
                'client_id'     => $clientId,
                'client_name'   => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['cliente', 'client_name', 'nombre_cliente'])),
                'seller_name'   => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['vendedor', 'seller_name'])),
                'business_type' => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['tipo_negocio', 'tiponegocio', 'business_type'])),
                'territory'     => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['territorio', 'territory'])),
                'address'       => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['direccion', 'address'])),
                'reference'     => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['referencia', 'reference'])),
                'latitude'      => round((float) $rawLat, 7),
                'longitude'     => round((float) $rawLon, 7),
                'status'        => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['estado', 'status'])) ?: 'ACTIVO',
                'route'         => $this->sanitizarTexto($this->obtenerValorColumna($row, $headerMap, ['ruta', 'route'])),
                'day'           => $this->sanitizarDia($this->obtenerValorColumna($row, $headerMap, ['dia', 'day'])),
                'created_at'    => $now,
                'updated_at'    => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('plan_ruteo')->insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('plan_ruteo')->insert($batch);
        }
    }

    private function normalizarClaveColumna(string $col): string
    {
        $clean = trim($col, " \t\n\r\0\x0B\xEF\xBB\xBF");
        $clean = mb_strtolower($clean, 'UTF-8');
        $clean = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $clean);
        return preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', $clean));
    }

    private function obtenerValorColumna(array $row, array $headerMap, array $posiblesNombres): ?string
    {
        foreach ($posiblesNombres as $nombre) {
            $key = $this->normalizarClaveColumna($nombre);
            if (isset($headerMap[$key]) && isset($row[$headerMap[$key]])) {
                $val = trim((string) $row[$headerMap[$key]]);
                return $val !== '' ? $val : null;
            }
        }
        return null;
    }

    private function sanitizarTexto(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $limpio = trim($texto);
        if ($limpio === '') {
            return null;
        }

        // Eliminar múltiples espacios y unificar a mayúsculas limpias
        $limpio = preg_replace('/\s+/', ' ', $limpio);
        return mb_strtoupper($limpio, 'UTF-8');
    }

    private function sanitizarDia(?string $dia): ?string
    {
        if ($dia === null) {
            return null;
        }

        $d = mb_strtolower(trim($dia), 'UTF-8');
        $d = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $d);

        return match (true) {
            str_contains($d, 'lun') => 'LUNES',
            str_contains($d, 'mar') => 'MARTES',
            str_contains($d, 'mie') => 'MIERCOLES',
            str_contains($d, 'jue') => 'JUEVES',
            str_contains($d, 'vie') => 'VIERNES',
            str_contains($d, 'sab') => 'SABADO',
            str_contains($d, 'dom') => 'DOMINGO',
            default                 => $this->sanitizarTexto($dia),
        };
    }
}