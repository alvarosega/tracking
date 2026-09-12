<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $vendedorRole = Role::firstOrCreate(
            ['name' => 'vendedor'],
            ['description' => 'Ejecutivo de ventas en campo']
        );

        $supervisorRole = Role::firstOrCreate(
            ['name' => 'supervisor'],
            ['description' => 'Supervisor de rutas y tiempos']
        );

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

        $defaultPassword = Hash::make('123*');

        foreach ($usernames as $index => $username) {
            User::updateOrCreate(
                ['username' => $username],
                [
                    'id' => 1001 + $index,
                    'role_id' => $vendedorRole->id,
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );
        }

        User::updateOrCreate(
            ['username' => 'supervisor1'],
            [
                'id' => 2001,
                'role_id' => $supervisorRole->id,
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
    }
}