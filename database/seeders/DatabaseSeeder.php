<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $vendedorRole = Role::create([
            'name' => 'vendedor',
            'description' => 'Ejecutivo de ventas en campo'
        ]);

        $supervisorRole = Role::create([
            'name' => 'supervisor',
            'description' => 'Supervisor de rutas y tiempos'
        ]);

        User::create([
            'id' => 1001, // ID simulado del sistema externo
            'role_id' => $vendedorRole->id,
            'username' => 'vendedor1',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'id' => 2001, // ID simulado del sistema externo
            'role_id' => $supervisorRole->id,
            'username' => 'supervisor1',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}