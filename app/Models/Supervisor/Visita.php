<?php

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    // Conexión a la base de datos de Visitas y Fotos en producción (alva)
    protected $connection = 'mysql';
    protected $table = 'visitas';

    protected $casts = [
        'is_opportunity' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'visited_at' => 'datetime',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        if (str_starts_with($this->photo_path, 'http://') || str_starts_with($this->photo_path, 'https://')) {
            return $this->photo_path;
        }

        $baseUrl = rtrim(env('STORAGE_BASE_URL', 'https://alvarosega.com/storage'), '/');
        $path = ltrim($this->photo_path, '/');

        return "{$baseUrl}/{$path}";
    }
}