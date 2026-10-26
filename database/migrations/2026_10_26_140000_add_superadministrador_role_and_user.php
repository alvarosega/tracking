<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asegurar rol superadministrador con ID 3
        DB::table('roles')->updateOrInsert(
            ['id' => 3],
            [
                'name' => 'superadministrador',
                'description' => 'Superadministrador con acceso total al sistema',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Crear usuario genérico superadmin si no existe
        $existing = DB::table('users')->where('username', 'superadmin')->first();
        if (!$existing) {
            DB::table('users')->insert([
                'role_id' => 3,
                'username' => 'superadmin',
                'password' => Hash::make('SuperAdmin2026!'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'superadmin')->delete();
        DB::table('roles')->where('id', 3)->orWhere('name', 'superadministrador')->delete();
    }
};

