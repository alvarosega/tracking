<?php

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Model;

class PanRuteo extends Model
{
    protected $connection = 'supervisor';
    protected $table = 'pan_ruteo';
    public $timestamps = false;

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'limite' => 'float',
    ];
}