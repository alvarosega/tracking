<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorModulePermission extends Model
{
    use HasFactory;

    protected $table = 'supervisor_module_permissions';

    protected $fillable = [
        'user_id',
        'module_key',
        'submodule_key',
        'is_allowed',
        'can_write',
        'expires_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
            'can_write' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Determina si el permiso ha caducado por fecha.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Determina si el permiso está activo y vigente.
     */
    public function isActive(): bool
    {
        return $this->is_allowed && !$this->isExpired();
    }
}

