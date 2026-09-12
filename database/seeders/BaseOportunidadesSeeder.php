<?php

namespace Database\Seeders;

use App\Models\BaseOportunidad;
use Illuminate\Database\Seeder;

class BaseOportunidadesSeeder extends Seeder
{
    public function run(): void
    {
        $oportunidades = [
            [
                'client_id' => '32940',
                'trade_name' => 'L. OVEJUYO',
                'client_name' => 'DELMA PAMELA MORALES TUSCO',
                'address' => 'AV. 14 DE SEPT Z. OVEJUYO',
                'phone' => '63124089',
                'territory' => 'DIRECTA ROADSHOW LPZ',
                'latitude' => -16.53956,
                'longitude' => -68.03283,
                'status' => '1',
            ],
            [
                'client_id' => '33372',
                'trade_name' => 'T. Marlene',
                'client_name' => 'BRIGIDA MARLENE SELAEZ FLORES',
                'address' => 'C. LEON DE LABARRA',
                'phone' => '69798513',
                'territory' => 'DIRECTA ROADSHOW LPZ',
                'latitude' => -16.4981156,
                'longitude' => -68.1414035,
                'status' => '1',
            ],
            [
                'client_id' => '33494',
                'trade_name' => 'D. SANTIAGUITO',
                'client_name' => 'ANGELICA CONDORI QUISPE',
                'address' => 'AV. LA FLORIDA C.5 Z.MALLASA',
                'phone' => '67134488',
                'territory' => 'DIRECTA ROADSHOW LPZ',
                'latitude' => -16.569504,
                'longitude' => -68.086784,
                'status' => '1',
            ],
        ];

        foreach ($oportunidades as $op) {
            BaseOportunidad::updateOrCreate(
                ['client_id' => $op['client_id']],
                $op
            );
        }
    }
}