<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestVendedorSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now('America/La_Paz');

        // 1. Asegurar rol vendedor
        $roleVendedor = Role::firstOrCreate(
            ['name' => 'vendedor'],
            ['description' => 'Vendedor de ruta en terreno', 'created_at' => $now, 'updated_at' => $now]
        );

        // 2. Crear o actualizar usuario TDB 99
        $user = User::updateOrCreate(
            ['username' => 'TDB 99'],
            [
                'role_id' => $roleVendedor->id,
                'password' => Hash::make('123'),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->command->info("Usuario de prueba TDB 99 activo con password: 123");

        // Coordenada base (Planta / Terreno de pruebas)
        $baseLat = -16.4856202;
        $baseLon = -68.2090439;

        // Desplazamiento métrico calibrado para -16.48°
        $dLat5m = 0.0000450; // ~5.0 metros Norte/Sur
        $dLon5m = 0.0000468; // ~5.0 metros Este/Oeste

        // Desplazamientos en pasos de 5m: [multiplicador_lat, multiplicador_lon]
        $offsets = [
            0 => [0, 0],   // 0m (Punto Base)
            1 => [1, 0],   // 5m Norte
            2 => [0, 1],   // 5m Este
            3 => [-1, 0],  // 5m Sur
            4 => [0, -1],  // 5m Oeste
            5 => [1, 1],   // ~7m Noreste
        ];

        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $clientCounter = 990001;

        foreach ($dias as $diaIndex => $dia) {
            foreach ($offsets as $puntoIndex => $offset) {
                // Leve micro-variación por día (~2.5m) para que los días no queden exactamente idénticos en el historial
                $diaShiftLat = ($diaIndex * 0.5) * $dLat5m;
                $diaShiftLon = ($diaIndex * 0.5) * $dLon5m;

                $lat = round($baseLat + ($offset[0] * $dLat5m) + $diaShiftLat, 7);
                $lon = round($baseLon + ($offset[1] * $dLon5m) + $diaShiftLon, 7);

                DB::table('plan_ruteo')->updateOrInsert(
                    ['client_id' => $clientCounter],
                    [
                        'client_name' => "Punto Test {$dia} #" . ($puntoIndex + 1) . " (5m offset)",
                        'seller_name' => 'Vendedor Pruebas TDB 99',
                        'business_type' => 'Prueba Terreno',
                        'territory' => 'Zona Base Pruebas',
                        'address' => "Área de Pruebas Coord Offset #{$puntoIndex}",
                        'reference' => "A " . (abs($offset[0]) + abs($offset[1])) * 5 . "m del centro base",
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'status' => 'Activo',
                        'route' => 'TDB 99',
                        'day' => $dia,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $clientCounter++;
            }
        }

        $this->command->info("Se generaron 36 clientes de prueba separados cada 5 metros respecto al punto base.");
    }
}