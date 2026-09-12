<?php

namespace Database\Seeders;

use App\Models\PlanRuteo;
use Illuminate\Database\Seeder;

class PlanRuteoSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'client_id' => 1,
                'client_name' => 'Paola Mita',
                'seller_name' => 'Daniel Conde Quispe',
                'business_type' => 'TDB B',
                'territory' => 'VILLA ADELA',
                'address' => 'Av ayopaya',
                'reference' => 'lavandería rey neptuno',
                'latitude' => -16.5170400,
                'longitude' => -68.2023100,
                'status' => 'Activo',
                'route' => 'TDB 6A',
                'day' => 'Sábado',
            ],
            [
                'client_id' => 2,
                'client_name' => 'Constancia Mayta Poma',
                'seller_name' => null,
                'business_type' => 'NN14',
                'territory' => 'VILLA ADELA',
                'address' => 'Av. Bolivia',
                'reference' => null,
                'latitude' => -16.5203800,
                'longitude' => -68.2062600,
                'status' => 'Inactivo',
                'route' => 'TDB 6A',
                'day' => 'Sábado',
            ],
            [
                'client_id' => 3,
                'client_name' => 'Margarita Condori De Silva',
                'seller_name' => null,
                'business_type' => 'NN13',
                'territory' => 'VILLA ADELA',
                'address' => 'Av. Bolivia',
                'reference' => null,
                'latitude' => -16.5203200,
                'longitude' => -68.2063700,
                'status' => 'Inactivo',
                'route' => 'TDB 6A',
                'day' => 'Sábado',
            ],
        ];

        foreach ($planes as $plan) {
            PlanRuteo::updateOrCreate(
                ['client_id' => $plan['client_id'], 'route' => $plan['route']],
                $plan
            );
        }
    }
}