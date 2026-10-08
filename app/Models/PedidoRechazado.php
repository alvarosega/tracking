<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PedidoRechazado extends Model
{
    use HasFactory;

    protected $table = 'pedidos_rechazados';

    protected $fillable = [
        'uuid',
        'user_id',
        'vendedor',
        'vendedor_username',
        'cliente_id',
        'codigo_cliente',
        'cliente_nombre',
        'nro_preventa',
        'fecha_preventa',
        'fecha_rechazo',
        'ruta',
        'motivo',
        'tipo_rechazo',
        'comentarios',
        'photo_path',
        'latitude',
        'longitude',
        'accuracy',
        'is_mock_location',
        'total_items_rechazados',
        'monto_total_rechazado',
    ];

    protected $casts = [
        'cliente_id' => 'integer',
        'nro_preventa' => 'integer',
        'fecha_preventa' => 'date',
        'fecha_rechazo' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'is_mock_location' => 'boolean',
        'total_items_rechazados' => 'integer',
        'monto_total_rechazado' => 'float',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PedidoRechazadoItem::class, 'pedido_rechazado_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }
}

