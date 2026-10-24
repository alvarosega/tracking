<?php

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
}

