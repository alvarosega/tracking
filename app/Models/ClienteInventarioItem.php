<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteInventarioItem extends Model
{
    use HasFactory;

    protected $table = 'cliente_inventario_items';

    public $timestamps = false;

    protected $fillable = [
        'cliente_inventario_id',
        'producto_id',
        'codigo_producto',
        'producto_nombre',
        'categoria',
        'stock_fisico',
        'cantidad_historica',
        'ultima_compra_fecha',
        'ultima_compra_cantidad',
    ];

    protected $casts = [
        'stock_fisico' => 'integer',
        'cantidad_historica' => 'integer',
        'ultima_compra_fecha' => 'date',
        'ultima_compra_cantidad' => 'integer',
        'producto_id' => 'integer',
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(ClienteInventario::class, 'cliente_inventario_id');
    }
}

