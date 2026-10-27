<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'id',
        'role_id',
        'username',
        'ruta',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Asegura que username nunca retorne null a los clientes API / Android.
     * Si username es null o vacío en BD, utiliza la ruta como fallback.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?: ($this->attributes['ruta'] ?? ''),
        );
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === 'superadministrador' || (int)$this->role_id === 3;
    }

    public function isSupervisor(): bool
    {
        return $this->role?->name === 'supervisor' || (int)$this->role_id === 1;
    }

    public function isVendedor(): bool
    {
        return $this->role?->name === 'vendedor' || (int)$this->role_id === 2;
    }

    public function canAccessWebPanel(): bool
    {
        return in_array($this->role?->name, ['supervisor', 'superadministrador', 'administrador', 'admin'], true)
            || in_array((int)$this->role_id, [1, 3], true);
    }

    public function canHaveCustomModulePermissions(): bool
    {
        return !$this->isSuperAdmin() && (
            $this->isSupervisor() || in_array($this->role?->name, ['administrador', 'admin', 'supervisor'], true)
        );
    }

    public function modulePermissions()
    {
        return $this->hasMany(SupervisorModulePermission::class);
    }

    /**
     * Determina si el usuario tiene permiso para acceder a un módulo (y opcionalmente submódulo).
     * Soporta verificación de expiración temporal (expires_at) y permiso de escritura (can_write).
     */
    public function hasModuleAccess(string $moduleKey, ?string $submoduleKey = null, bool $requireWrite = false): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->canHaveCustomModulePermissions()) {
            return false;
        }

        try {
            $permissions = $this->relationLoaded('modulePermissions')
                ? $this->modulePermissions
                : $this->modulePermissions()->get();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo consultar supervisor_module_permissions (posible tabla pendiente de creacion): " . $e->getMessage());
            return true;
        }

        // Por defecto: Si no tiene registros configurados, NO TIENE PERMISOS
        if ($permissions->isEmpty()) {
            return false;
        }

        $subKey = !empty($submoduleKey) ? (string)$submoduleKey : '';

        // 1. Verificar módulo principal
        $mainPerm = $permissions->first(function ($p) use ($moduleKey) {
            $pSub = (string)($p->submodule_key ?? '');
            return $p->module_key === $moduleKey && $pSub === '';
        });

        if (!$mainPerm || !$mainPerm->is_allowed) {
            return false;
        }

        // Si el permiso principal expiró por fecha, revocar acceso
        if ($mainPerm->expires_at && $mainPerm->expires_at->isPast()) {
            return false;
        }

        if ($requireWrite && !$mainPerm->can_write) {
            return false;
        }

        // 2. Verificar submódulo si fue provisto
        if ($subKey !== '') {
            $subPerm = $permissions->first(function ($p) use ($moduleKey, $subKey) {
                return $p->module_key === $moduleKey && (string)($p->submodule_key ?? '') === $subKey;
            });

            // Si el submódulo no está explícitamente concedido o está deshabilitado, denegar acceso
            if (!$subPerm || !$subPerm->is_allowed) {
                return false;
            }

            if ($subPerm->expires_at && $subPerm->expires_at->isPast()) {
                return false;
            }

            if ($requireWrite && !$subPerm->can_write) {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Retorna el mapa completo de permisos indexado por módulo y submódulo,
     * incluyendo estado de vigencia, permisos de escritura y fechas de caducidad.
     *
     * @return array<string, mixed>
     */
    public function getModulePermissionsMap(): array
    {
        if ($this->isSuperAdmin()) {
            return [
                'is_all_allowed' => true,
                'modules' => [],
                'submodules' => [],
                'can_write' => [],
                'expires_at' => [],
            ];
        }

        try {
            $permissions = $this->relationLoaded('modulePermissions')
                ? $this->modulePermissions
                : $this->modulePermissions()->get();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo consultar supervisor_module_permissions (posible tabla pendiente de creacion): " . $e->getMessage());
            return [
                'is_all_allowed' => true,
                'modules' => [],
                'submodules' => [],
                'can_write' => [],
                'expires_at' => [],
            ];
        }

        // Por defecto: no tiene permisos asignados
        if ($permissions->isEmpty()) {
            return [
                'is_all_allowed' => false,
                'modules' => [],
                'submodules' => [],
                'can_write' => [],
                'expires_at' => [],
            ];
        }

        $modulesMap = [];
        $submodulesMap = [];
        $canWriteMap = [];
        $expiresAtMap = [];

        foreach ($permissions as $p) {
            $isActive = (bool)$p->is_allowed && (!$p->expires_at || $p->expires_at->isFuture());
            $subKey = (string)($p->submodule_key ?? '');

            if ($subKey === '') {
                $modulesMap[$p->module_key] = $isActive;
                $canWriteMap[$p->module_key] = (bool)$p->can_write;
                $expiresAtMap[$p->module_key] = $p->expires_at?->toIso8601String();
            } else {
                $compositeKey = "{$p->module_key}.{$subKey}";
                $submodulesMap[$compositeKey] = $isActive;
                $canWriteMap[$compositeKey] = (bool)$p->can_write;
                $expiresAtMap[$compositeKey] = $p->expires_at?->toIso8601String();
            }
        }

        return [
            'is_all_allowed' => false,
            'modules' => $modulesMap,
            'submodules' => $submodulesMap,
            'can_write' => $canWriteMap,
            'expires_at' => $expiresAtMap,
        ];
    }
}