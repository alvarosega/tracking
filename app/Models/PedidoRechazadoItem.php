<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoRechazadoItem extends Model
{
    use HasFactory;

    protected $table = 'pedidos_rechazados_items';
    public $timestamps = false;

    protected $fillable = [
        'pedido_rechazado_id',
        'preventa_item_id',
        'producto_id',
        'codigo_producto',
        'producto_nombre',
        'categoria',
        'cantidad_preventa',
        'cantidad_rechazada',
        'precio_unitario',
        'monto_rechazado',
        'motivo_especifico',
        'created_at',
    ];

    protected $casts = [
        'pedido_rechazado_id' => 'integer',
        'preventa_item_id' => 'integer',
        'producto_id' => 'integer',
        'cantidad_preventa' => 'integer',
        'cantidad_rechazada' => 'integer',
        'precio_unitario' => 'float',
        'monto_rechazado' => 'float',
        'created_at' => 'datetime',
    ];

    public function pedidoRechazado(): BelongsTo
    {
        return $this->belongsTo(PedidoRechazado::class, 'pedido_rechazado_id');
    }

    /**
     * Serializa las fechas en formato 'Y-m-d H:i:s' en la zona horaria local de Bolivia.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return \Carbon\Carbon::instance($date)->setTimezone('America/La_Paz')->format('Y-m-d H:i:s');
    }
}

