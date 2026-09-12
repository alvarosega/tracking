<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseOportunidad extends Model
{
    use HasFactory;

    protected $table = 'base_oportunidades';

    protected $fillable = [
        'client_id',
        'trade_name',
        'client_name',
        'address',
        'phone',
        'territory',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}