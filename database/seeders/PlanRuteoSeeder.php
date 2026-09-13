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
        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );

        $header = $file->fgetcsv();

        if (!$header) {
            throw new RuntimeException("El archivo CSV plan_ruteo está vacío o no contiene encabezados.");
        }

        // Limpiar BOM UTF-8 y espacios de los encabezados en español
        $header = array_map(fn($col) => trim($col, " \t\n\r\0\x0B\xEF\xBB\xBF"), $header);

        $batch = [];
        $now = now();

        while (!$file->eof()) {
            $row = $file->fgetcsv();

            if (empty($row) || count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            // Validación de coordenadas mínimas
            $rawLat = trim($data['Latitud'] ?? '');
            $rawLon = trim($data['Longitud'] ?? '');

            if ($rawLat === '' || $rawLon === '' || !is_numeric($rawLat) || !is_numeric($rawLon)) {
                continue; // Omite registros sin geolocalización válida
            }

            $batch[] = [
                'client_id'     => (int) $data['Cliente ID'],
                'client_name'   => trim($data['Cliente']),
                'seller_name'   => !empty(trim($data['Vendedor'])) ? trim($data['Vendedor']) : null,
                'business_type' => !empty(trim($data['Tipo Negocio'])) ? trim($data['Tipo Negocio']) : null,
                'territory'     => !empty(trim($data['Territorio'])) ? trim($data['Territorio']) : null,
                'address'       => !empty(trim($data['Direccion'])) ? trim($data['Direccion']) : null,
                'reference'     => !empty(trim($data['Referencia'])) ? trim($data['Referencia']) : null,
                'latitude'      => (float) $rawLat,
                'longitude'     => (float) $rawLon,
                'status'        => !empty(trim($data['Estado'])) ? trim($data['Estado']) : 'Activo',
                'route'         => !empty(trim($data['Ruta'])) ? trim($data['Ruta']) : null,
                'day'           => !empty(trim($data['Dia'])) ? trim($data['Dia']) : null,
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
}