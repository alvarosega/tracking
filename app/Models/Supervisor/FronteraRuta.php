<?php

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FronteraRuta extends Model
{
    protected $connection = 'supervisor';
    protected $table = 'dim_fronteras_rutas';
    public $timestamps = false;

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'ACTIVO');
    }
}