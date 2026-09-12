<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    use HasFactory;

    protected $table = 'visitas';

    protected $fillable = [
        'user_id',
        'client_id',
        'route',
        'status',
        'is_opportunity',
        'opportunity_client_name',
        'latitude',
        'longitude',
        'accuracy',
        'photo_path',
        'comments',
        'visited_at',
    ];

    protected $casts = [
        'is_opportunity' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'visited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}