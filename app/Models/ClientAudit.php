<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'audit_status',
        'latitude',
        'longitude',
        'accuracy',
        'comments',
        'photo_paths',
        'audited_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'photo_paths' => 'array',
        'audited_at' => 'datetime',
    ];

    public function referenceClient(): BelongsTo
    {
        return $this->belongsTo(ReferenceClient::class, 'client_id', 'id_cliente');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}