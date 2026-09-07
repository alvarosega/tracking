<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferenceClient extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_cliente';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id_cliente',
        'ruta',
        'dia',
        'direccion',
        'latitud',
        'longitud',
        'is_audited',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'is_audited' => 'boolean',
    ];

    public function audits(): HasMany
    {
        return $this->hasMany(ClientAudit::class, 'client_id', 'id_cliente');
    }
}