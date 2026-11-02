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
     * Serializa arrays/objetos con UTF-8 literal (sin escapes \uXXXX).
     * Evita que "Miércoles" se guarde como "Mi\u00e9rcoles" en la BD.
     */
    protected function asJson($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Usuario (superadmin) que actualizó la configuración.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

