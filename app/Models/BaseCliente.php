<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseCliente extends Model
{
    use HasFactory;

    protected $table = 'base_cliente';

    protected $fillable = [
        'client_id',
        'client_name',
        'address',
        'reference',
        'latitude',
        'longitude',
        'status',
        'route',
        'day',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}