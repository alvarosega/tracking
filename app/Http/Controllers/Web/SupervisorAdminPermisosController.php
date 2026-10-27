<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SupervisorModulePermission;
use App\Models\User;
use App\Services\Permisos\ModuleCatalogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SupervisorAdminPermisosController extends Controller
{
    /**
     * Muestra la vista principal de Gestión de Permisos de Supervisores.
     */
    public function index(Request $request): Response
    {
        $supervisores = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'administrador', 'admin']))
            ->with(['role', 'modulePermissions'])
            ->orderBy('username')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'ruta' => $user->ruta ?? '',
                    'role' => $user->role?->name ?? 'supervisor',
                    'is_active' => (bool)$user->is_active,
                    'permissions_map' => $user->getModulePermissionsMap(),
                    'total_custom_rules' => $user->modulePermissions->count(),
                ];
            });

        return Inertia::render('Supervisor/Admin/Permisos', [
            'supervisores' => $supervisores,
            'catalog' => ModuleCatalogService::getCatalog(),
            'submoduloActivo' => 'permisos',
        ]);
    }

    /**
     * Alterna o guarda un permiso individual (módulo o submódulo) para un supervisor.
     */
    public function toggle(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'module_key' => 'required|string|max:50',
            'submodule_key' => 'nullable|string|max:50',
            'is_allowed' => 'required|boolean',
            'can_write' => 'nullable|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $user = User::findOrFail($userId);
        if (!$user->canHaveCustomModulePermissions()) {
            return response()->json(['message' => 'Solo se pueden configurar permisos para supervisores o administradores.'], 400);
        }

        $moduleKey = (string)$request->input('module_key');
        $rawSub = $request->input('submodule_key');
        $submoduleKey = !empty($rawSub) ? (string)$rawSub : '';
        $isAllowed = (bool)$request->input('is_allowed');
        $canWrite = $request->has('can_write') ? (bool)$request->input('can_write') : true;
        $expiresAt = $request->filled('expires_at') ? Carbon::parse($request->input('expires_at')) : null;
        $adminId = $request->user()?->id;

        try {
            SupervisorModulePermission::updateOrInsert(
                [
                    'user_id' => $user->id,
                    'module_key' => $moduleKey,
                    'submodule_key' => $submoduleKey,
                ],
                [
                    'is_allowed' => $isAllowed,
                    'can_write' => $canWrite,
                    'expires_at' => $expiresAt,
                    'assigned_by' => $adminId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // Si se desactiva un módulo principal, desactivar también sus submódulos
            if ($submoduleKey === '' && !$isAllowed) {
                SupervisorModulePermission::where('user_id', $user->id)
                    ->where('module_key', $moduleKey)
                    ->where('submodule_key', '!=', '')
                    ->update([
                        'is_allowed' => false,
                        'assigned_by' => $adminId,
                        'updated_at' => now(),
                    ]);
            }

            $user->refresh()->load('modulePermissions');

            return response()->json([
                'status' => 'success',
                'user_id' => $user->id,
                'permissions_map' => $user->getModulePermissionsMap(),
                'message' => 'Permiso actualizado correctamente.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda en lote toda la configuración de permisos de un supervisor.
     */
    public function batchSave(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.module_key' => 'required|string|max:50',
            'permissions.*.submodule_key' => 'nullable|string|max:50',
            'permissions.*.is_allowed' => 'required|boolean',
            'permissions.*.can_write' => 'nullable|boolean',
            'permissions.*.expires_at' => 'nullable|date',
        ]);

        $user = User::findOrFail($userId);
        if (!$user->canHaveCustomModulePermissions()) {
            return response()->json(['message' => 'Solo se pueden configurar permisos para supervisores o administradores.'], 400);
        }

        $items = $request->input('permissions', []);
        $adminId = $request->user()?->id;

        DB::beginTransaction();
        try {
            // Limpiar permisos previos del supervisor
            SupervisorModulePermission::where('user_id', $user->id)->delete();

            $now = now();
            $insertData = [];
            foreach ($items as $item) {
                $rawSub = $item['submodule_key'] ?? '';
                $submoduleKey = !empty($rawSub) ? (string)$rawSub : '';
                $canWrite = isset($item['can_write']) ? (bool)$item['can_write'] : true;
                $expiresAt = !empty($item['expires_at']) ? Carbon::parse($item['expires_at']) : null;

                $insertData[] = [
                    'user_id' => $user->id,
                    'module_key' => $item['module_key'],
                    'submodule_key' => $submoduleKey,
                    'is_allowed' => (bool)$item['is_allowed'],
                    'can_write' => $canWrite,
                    'expires_at' => $expiresAt,
                    'assigned_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($insertData)) {
                SupervisorModulePermission::insert($insertData);
            }

            DB::commit();

            $user->refresh()->load('modulePermissions');

            return response()->json([
                'status' => 'success',
                'user_id' => $user->id,
                'permissions_map' => $user->getModulePermissionsMap(),
                'message' => 'Matriz de permisos guardada correctamente.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Aplica acciones predefinidas (Habilitar todo, Deshabilitar todo, Restablecer por defecto).
     */
    public function preset(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'preset' => 'required|in:enable_all,disable_all,reset_default',
        ]);

        $user = User::findOrFail($userId);
        if (!$user->canHaveCustomModulePermissions()) {
            return response()->json(['message' => 'Solo se pueden configurar permisos para supervisores o administradores.'], 400);
        }

        $preset = $request->input('preset');
        $catalog = ModuleCatalogService::getCatalog();
        $adminId = $request->user()?->id;
        $now = now();

        DB::beginTransaction();
        try {
            SupervisorModulePermission::where('user_id', $user->id)->delete();

            if ($preset === 'enable_all') {
                $insertData = [];
                foreach ($catalog as $mod) {
                    $insertData[] = [
                        'user_id' => $user->id,
                        'module_key' => $mod['id'],
                        'submodule_key' => '',
                        'is_allowed' => true,
                        'can_write' => true,
                        'expires_at' => null,
                        'assigned_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    foreach ($mod['submodules'] as $sub) {
                        $insertData[] = [
                            'user_id' => $user->id,
                            'module_key' => $mod['id'],
                            'submodule_key' => $sub['id'],
                            'is_allowed' => true,
                            'can_write' => true,
                            'expires_at' => null,
                            'assigned_by' => $adminId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                SupervisorModulePermission::insert($insertData);
            } elseif ($preset === 'disable_all') {
                $insertData = [];
                foreach ($catalog as $mod) {
                    $insertData[] = [
                        'user_id' => $user->id,
                        'module_key' => $mod['id'],
                        'submodule_key' => '',
                        'is_allowed' => false,
                        'can_write' => false,
                        'expires_at' => null,
                        'assigned_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                SupervisorModulePermission::insert($insertData);
            }
            // Si es 'reset_default', al haber borrado los registros, el sistema interpreta que por defecto no tiene permisos

            DB::commit();

            $user->refresh()->load('modulePermissions');

            return response()->json([
                'status' => 'success',
                'user_id' => $user->id,
                'permissions_map' => $user->getModulePermissionsMap(),
                'message' => 'Acción rápida aplicada con éxito.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Clona la configuración de permisos desde un supervisor de origen a otro de destino.
     */
    public function clone(Request $request): JsonResponse
    {
        $request->validate([
            'source_user_id' => 'required|exists:users,id',
            'target_user_id' => 'required|exists:users,id|different:source_user_id',
        ]);

        $sourceUser = User::findOrFail((int)$request->input('source_user_id'));
        $targetUser = User::findOrFail((int)$request->input('target_user_id'));
        $adminId = $request->user()?->id;

        if (!$targetUser->canHaveCustomModulePermissions()) {
            return response()->json(['message' => 'El usuario de destino debe ser un supervisor o administrador.'], 400);
        }

        DB::beginTransaction();
        try {
            SupervisorModulePermission::where('user_id', $targetUser->id)->delete();

            $sourcePerms = SupervisorModulePermission::where('user_id', $sourceUser->id)->get();
            $now = now();
            $insertData = [];

            foreach ($sourcePerms as $sp) {
                $insertData[] = [
                    'user_id' => $targetUser->id,
                    'module_key' => $sp->module_key,
                    'submodule_key' => (string)($sp->submodule_key ?? ''),
                    'is_allowed' => (bool)$sp->is_allowed,
                    'can_write' => (bool)$sp->can_write,
                    'expires_at' => $sp->expires_at,
                    'assigned_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($insertData)) {
                SupervisorModulePermission::insert($insertData);
            }

            DB::commit();

            $targetUser->refresh()->load('modulePermissions');

            return response()->json([
                'status' => 'success',
                'target_user_id' => $targetUser->id,
                'permissions_map' => $targetUser->getModulePermissionsMap(),
                'message' => "Permisos clonados exitosamente desde '{$sourceUser->username}' a '{$targetUser->username}'.",
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}

