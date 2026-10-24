<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppModule extends Model
{
    use HasFactory;

    protected $table = 'app_modules';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'order',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'config' => 'array',
    ];

    /**
     * Obtiene el valor de una clave dentro de config con fallback opcional.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Actualiza o inserta un valor dentro de config.
     */
    public function setConfigValue(string $key, mixed $value): self
    {
        $current = $this->config ?? [];
        $current[$key] = $value;
        $this->config = $current;
        return $this;
    }
}

