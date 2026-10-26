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
        return $this->role?->name === 'superadministrador';
    }

    public function isSupervisor(): bool
    {
        return $this->role?->name === 'supervisor';
    }

    public function isVendedor(): bool
    {
        return $this->role?->name === 'vendedor';
    }

    public function canAccessWebPanel(): bool
    {
        return in_array($this->role?->name, ['supervisor', 'superadministrador'], true);
    }
}