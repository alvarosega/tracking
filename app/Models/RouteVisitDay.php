<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteVisitDay extends Model
{
    use HasFactory;

    protected $connection = 'supervisor';

    protected $table = 'route_visit_days';

    protected $fillable = [
        'route',
        'day_of_week',
        'dia_nombre',
        'dias_visita',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'dias_visita' => 'array',
        'is_active'   => 'boolean',
        'updated_by'  => 'integer',
    ];

    /**
     * Usuario (superadmin) que actualizó la configuración.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

