<?php

declare(strict_types=1);

namespace App\Actions\Api\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * @param array{username: string, password: string} $data
     * @return array{user: User, token: string}
     */
    public function execute(array $data): array
    {
        $user = User::where('ruta', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Credenciales incorrectas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['El usuario está inactivo.'],
            ]);
        }

        // Elimina tokens previos (Evita sesiones concurrentes)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->load('role');

        if (empty($user->username)) {
            $user->username = $user->ruta ?? '';
        }

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}

