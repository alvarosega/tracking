<?php

declare(strict_types=1);

namespace App\Models\Supervisor;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SaneamientoPropuesta extends Model
{
    protected $connection = 'supervisor';
    protected $table = 'auditoria_saneamiento_propuestas';

    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'tipo_hallazgo',
        'cliente_id_origen',
        'cliente_id_vinculado',
        'cliente_nombre',
        'ruta_actual',
        'dia_actual',
        'ruta_propuesta',
        'dia_propuesto',
        'accion_propuesta',
        'distancia_metros',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'cliente_id_origen' => 'integer',
        'cliente_id_vinculado' => 'integer',
        'distancia_metros' => 'float',
        'creado_en' => 'datetime',
    ];

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopePorTipo(Builder $query, ?string $tipo): Builder
    {
        if ($tipo && strtoupper($tipo) !== 'TODOS') {
            return $query->where('tipo_hallazgo', strtoupper($tipo));
        }

        return $query;
    }
}

