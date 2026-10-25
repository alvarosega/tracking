<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClienteInventario extends Model
{
    use HasFactory;

    protected $table = 'cliente_inventarios';

    protected $fillable = [
        'uuid',
        'user_id',
        'ruta',
        'vendedor',
        'cliente_id',
        'codigo_cliente',
        'cliente_nombre',
        'fecha_registro',
        'latitude',
        'longitude',
        'accuracy',
        'is_mock_location',
        'total_items_censados',
        'observaciones',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'is_mock_location' => 'boolean',
        'total_items_censados' => 'integer',
        'cliente_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClienteInventarioItem::class, 'cliente_inventario_id');
    }

    /**
     * Serializa las fechas en formato 'Y-m-d H:i:s' en la zona horaria local de Bolivia (America/La_Paz).
     * Evita que Eloquent las convierta a UTC ISO-8601 con sufijo 'Z', eliminando la discrepancia de 4 horas en Android y Web.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Carbon\Carbon::instance($date)->setTimezone('America/La_Paz')->format('Y-m-d H:i:s');
    }
}

