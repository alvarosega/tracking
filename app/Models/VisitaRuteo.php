<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitaRuteo extends Model
{
    use HasFactory;

    protected $table = 'visitas_ruteo';

    protected $fillable = [
        'user_id',
        'client_id',
        'status',
        'is_new_client',
        'new_client_name',
        'latitude',
        'longitude',
        'accuracy',
        'distance_to_target',
        'photo_path',
        'comments',
        'visited_at',
    ];

    protected $casts = [
        'is_new_client' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'distance_to_target' => 'float',
        'visited_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}