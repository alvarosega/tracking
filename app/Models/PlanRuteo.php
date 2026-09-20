<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanRuteo extends Model
{
    use HasFactory;

    // Apunta a la segunda base de datos
    protected $connection = 'supervisor';

    // Nombre exacto de la tabla física
    protected $table = 'pan_ruteo';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'cliente_id' => 'integer',
        'latitud' => 'float',
        'longitud' => 'float',
        'limite' => 'float',
    ];

    /**
     * Mapea y normaliza los atributos para mantener compatibilidad total con Android y las Vistas Web.
     */
    public function getClientIdAttribute()
    {
        return $this->attributes['cliente_id'] ?? null;
    }

    public function getClientNameAttribute()
    {
        return $this->attributes['cliente'] ?? null;
    }

    public function getSellerNameAttribute()
    {
        return $this->attributes['vendedor'] ?? null;
    }

    public function getBusinessTypeAttribute()
    {
        return $this->attributes['tipo_negocio'] ?? null;
    }

    public function getAddressAttribute()
    {
        return $this->attributes['direccion'] ?? null;
    }

    public function getReferenceAttribute()
    {
        return $this->attributes['referencia'] ?? null;
    }

    public function getLatitudeAttribute()
    {
        return isset($this->attributes['latitud']) ? (float)$this->attributes['latitud'] : null;
    }

    public function getLongitudeAttribute()
    {
        return isset($this->attributes['longitud']) ? (float)$this->attributes['longitud'] : null;
    }

    public function getStatusAttribute()
    {
        return $this->attributes['estado'] ?? 'Activo';
    }
}