<?php

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FactVenta extends Model
{
    protected $connection = 'supervisor';
    protected $table = 'fact_ventas';
    public $timestamps = false;

    protected $casts = [
        'cantidad' => 'integer',
        'cantidad_cajas' => 'float',
        'precio_unitario' => 'float',
        'monto' => 'float',
        'descuento' => 'float',
        'monto_final' => 'float',
        'mes' => 'integer',
    ];

    public function scopeValidas(Builder $query): Builder
    {
        return $query->where('revertida', 'No');
    }
}