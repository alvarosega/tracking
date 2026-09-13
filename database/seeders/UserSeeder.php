<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use SplFileObject;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asegurar roles requeridos
        $roles = [
            'vendedor' => Role::firstOrCreate(
                ['name' => 'vendedor'],
                ['description' => 'Ejecutivo de ventas en campo']
            )->id,
            'supervisor' => Role::firstOrCreate(
                ['name' => 'supervisor'],
                ['description' => 'Supervisor de rutas y telemetría']
            )->id,
        ];

        $filePath = database_path('seeders/csv/users.csv');

        if (!file_exists($filePath)) {
            throw new RuntimeException("Archivo no encontrado: {$filePath}");
        }

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );

        $header = $file->fgetcsv();

        if (!$header) {
            throw new RuntimeException("El archivo CSV de usuarios está vacío o sin encabezado.");
        }

        // Limpieza de BOM y espacios en encabezados
        $header = array_map(fn($col) => trim($col, " \t\n\r\0\x0B\xEF\xBB\xBF"), $header);

        $hashedPasswords = [];
        $now = now();

        while (!$file->eof()) {
            $row = $file->fgetcsv();
            
            if (empty($row) || count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            $roleName = strtolower(trim($data['role']));
            if (!isset($roles[$roleName])) {
                continue; // Omite registros con rol no registrado
            }

            $rawPassword = (string) $data['password'];
            if (!isset($hashedPasswords[$rawPassword])) {
                $hashedPasswords[$rawPassword] = Hash::make($rawPassword);
            }

            User::updateOrCreate(
                ['id' => (int) $data['id']],
                [
                    'role_id' => $roles[$roleName],
                    'username' => trim($data['username']),
                    'password' => $hashedPasswords[$rawPassword],
                    'is_active' => filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}