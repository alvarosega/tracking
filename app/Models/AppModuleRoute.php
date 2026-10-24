<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppModuleRoute extends Model
{
    use HasFactory;

    protected $table = 'app_module_routes';

    protected $fillable = [
        'module_code',
        'ruta',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];
}

