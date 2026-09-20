<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWorkday extends Model
{
    use HasFactory;

    protected $table = 'user_workdays';

    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'ended_at',
        'status',
        'closed_by',
        'close_reason',
    ];

    protected $casts = [
        'work_date'  => 'date',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}