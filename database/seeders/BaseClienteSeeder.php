<?php

namespace Database\Seeders;

use App\Models\BaseCliente;
use Illuminate\Database\Seeder;

class BaseClienteSeeder extends Seeder
{
    public function run(): void
    {
        $usernames = [
            'TDB 1A',
            'TDB 2A',
            'TDB 3A',
            'TDB 4A',
            'TDB 5A',
            'TDB 6A',
            'TDB 7A',
            'TDB 8 PRT',
            'TDB 8 FARMACIAS',
            'TDB 8A',
            'TDB 9A',
            'TDB 10A',
        ];

        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        
        // Coordenadas aproximadas de referencia dentro de El Alto, Bolivia
        $baseLat = -16.5000;
        $baseLon = -68.1500;

        $globalClientId = 1;

        foreach ($usernames as $route) {
            // Creamos 5 clientes fantasma por cada ruta/usuario
            for ($i = 1; $i <= 5; $i++) {
                // Generamos pequeñas variaciones aleatorias para simular ubicaciones en El Alto
                $latOffset = (rand(-50, 50) / 10000.0);
                $lonOffset = (rand(-50, 50) / 10000.0);

                BaseCliente::updateOrCreate(
                    ['client_id' => $globalClientId],
                    [
                        'client_name' => "Cliente Fantasma $i ($route)",
                        'address' => "Zona El Alto - Calle Principal #$i",
                        'reference' => "Cerca a comercio local $i",
                        'latitude' => $baseLat + $latOffset,
                        'longitude' => $baseLon + $lonOffset,
                        'status' => 'Activo',
                        'route' => $route,
                        'day' => $days[array_rand($days)],
                    ]
                );
                
                $globalClientId++;
            }
        }
    }
}
