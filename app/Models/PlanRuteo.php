<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanRuteo extends Model
{
    use HasFactory;

    protected $table = 'plan_ruteo';

    protected $fillable = [
        'client_id',
        'client_name',
        'seller_name',
        'business_type',
        'territory',
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