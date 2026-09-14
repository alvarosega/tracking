<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'battery_level',
        'is_mock',
        'is_moving',
        'step_count',
        'motion_variance',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'speed' => 'float',
        'battery_level' => 'integer',
        'is_mock' => 'boolean',
        'is_moving' => 'boolean',
        'step_count' => 'integer',
        'motion_variance' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}