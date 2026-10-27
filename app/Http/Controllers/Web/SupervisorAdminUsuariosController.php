<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SupervisorAdminUsuariosController extends Controller
{
    /**
     * Muestra la vista de Administración de Usuarios (Exclusivo Superadmin).
     */
    public function index(Request $request): Response
    {
        $roles = Role::orderBy('id')->get(['id', 'name', 'description']);

        $usuarios = User::query()
            ->with(['role', 'modulePermissions'])
            ->orderBy('role_id')
            ->orderBy('username')
            ->get()
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'username' => $u->username,
                    'role_id' => $u->role_id,
                    'role' => $u->role?->name ?? 'sin rol',
                    'ruta' => $u->ruta ?? '',
                    'is_active' => (bool)$u->is_active,
                    'total_custom_rules' => $u->modulePermissions->count(),
                    'created_at' => $u->created_at?->toIso8601String(),
                    'updated_at' => $u->updated_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Supervisor/Admin/Usuarios', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'submoduloActivo' => 'usuarios',
        ]);
    }

    /**
     * Registra un nuevo usuario en el sistema.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'role_id' => 'required|exists:roles,id',
            'ruta' => 'nullable|string|max:30',
            'password' => 'required|string|min:6|max:100',
            'is_active' => 'required|boolean',
        ], [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.unique' => 'El nombre de usuario ya está registrado en el sistema.',
            'username.max' => 'El nombre de usuario no puede exceder 50 caracteres.',
            'role_id.required' => 'Debe seleccionar un rol para el usuario.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        try {
            $user = User::create([
                'username' => trim($validated['username']),
                'role_id' => (int)$validated['role_id'],
                'ruta' => !empty($validated['ruta']) ? trim($validated['ruta']) : null,
                'password' => Hash::make($validated['password']),
                'is_active' => (bool)$validated['is_active'],
            ]);

            $user->load(['role', 'modulePermissions']);

            return response()->json([
                'status' => 'success',
                'message' => "Usuario '{$user->username}' creado exitosamente.",
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role_id' => $user->role_id,
                    'role' => $user->role?->name ?? 'sin rol',
                    'ruta' => $user->ruta ?? '',
                    'is_active' => (bool)$user->is_active,
                    'total_custom_rules' => $user->modulePermissions->count(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza la información de un usuario existente.
     */
    public function update(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'role_id' => 'required|exists:roles,id',
            'ruta' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:6|max:100',
            'is_active' => 'required|boolean',
        ], [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.unique' => 'El nombre de usuario ya se encuentra registrado por otro usuario.',
            'role_id.required' => 'Debe seleccionar un rol válido.',
            'password.min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
        ]);

        $currentUser = $request->user();

        // Salvaguarda: No permitir que el propio Superadmin se inactive o degrade
        if ($currentUser && $currentUser->id === $user->id) {
            if (!(bool)$validated['is_active']) {
                return response()->json([
                    'message' => 'Acción denegada: no puedes desactivar tu propia cuenta de superadministrador.',
                ], 422);
            }

            if ($user->isSuperAdmin()) {
                $newRole = Role::find((int)$validated['role_id']);
                if ($newRole && $newRole->name !== 'superadministrador') {
                    return response()->json([
                        'message' => 'Acción denegada: no puedes revocar tu propio rol de superadministrador.',
                    ], 422);
                }
            }
        }

        try {
            $user->username = trim($validated['username']);
            $user->role_id = (int)$validated['role_id'];
            $user->ruta = !empty($validated['ruta']) ? trim($validated['ruta']) : null;
            $user->is_active = (bool)$validated['is_active'];

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();
            $user->load(['role', 'modulePermissions']);

            return response()->json([
                'status' => 'success',
                'message' => "Usuario '{$user->username}' actualizado correctamente.",
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role_id' => $user->role_id,
                    'role' => $user->role?->name ?? 'sin rol',
                    'ruta' => $user->ruta ?? '',
                    'is_active' => (bool)$user->is_active,
                    'total_custom_rules' => $user->modulePermissions->count(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alterna rápidamente el estado activo/inactivo de un usuario.
     */
    public function toggleStatus(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'Acción denegada: no puedes desactivar tu propia cuenta activa.',
            ], 422);
        }

        try {
            $user->is_active = !$user->is_active;
            $user->save();

            $estadoStr = $user->is_active ? 'activado' : 'inactivado';

            return response()->json([
                'status' => 'success',
                'is_active' => $user->is_active,
                'message' => "Usuario '{$user->username}' {$estadoStr} correctamente.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cambiar estado: ' . $e->getMessage(),
            ], 500);
        }
    }
}

