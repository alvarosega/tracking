<?php

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Model;

class Ruta extends Model
{
    protected $connection = 'supervisor';
    protected $table = 'dim_rutas';
    public $timestamps = false;

    protected $fillable = [
        'ruta',
        'canal',
        'vendedor',
        'vendedor_norm',
    ];
}