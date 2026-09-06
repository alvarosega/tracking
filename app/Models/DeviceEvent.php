<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'event_time',
    ];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}