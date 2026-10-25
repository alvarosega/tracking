<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\AppModuleRoute;

class ToggleRouteModuleAction
{
    /**
     * Activa o desactiva un módulo móvil para una ruta comercial específica.
     */
    public function execute(string $ruta, string $moduleCode, bool $isActive): AppModuleRoute
    {
        return AppModuleRoute::updateOrCreate(
            [
                'ruta' => trim($ruta),
                'module_code' => $moduleCode,
            ],
            [
                'is_active' => $isActive,
            ]
        );
    }
}

