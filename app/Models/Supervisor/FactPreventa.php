<?php

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Model;

class FactPreventa extends Model
{
    protected $connection = 'supervisor';
    protected $table = 'fact_preventas';
    public $timestamps = false;

    protected $casts = [
        'nro_preventa' => 'integer',
        'cliente_id' => 'integer',
        'producto_id' => 'integer',
        'cantidad' => 'integer',
        'monto' => 'float',
        'descuento' => 'float',
        'monto_final' => 'float',
        'mes' => 'integer',
        'fecha' => 'date',
        'fecha_norm' => 'date',
    ];
}