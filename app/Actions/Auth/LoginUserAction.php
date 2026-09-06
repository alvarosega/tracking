<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function execute(array $data): array
    {
        $user = User::where('username', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
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

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
