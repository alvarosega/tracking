<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\AppModuleRoute;

class UpdateRouteModuleConfigAction
{
    /**
     * Actualiza la configuración específica de un módulo para una ruta (ej: días de historial).
     *
     * @param string $ruta
     * @param string $moduleCode
     * @param array<string, mixed> $config
     * @return AppModuleRoute
     */
    public function execute(string $ruta, string $moduleCode, array $config): AppModuleRoute
    {
        $cleanRuta = trim($ruta);
        $existing = AppModuleRoute::where('ruta', $cleanRuta)
            ->where('module_code', $moduleCode)
            ->first();

        $mergedConfig = array_merge($existing?->config ?? [], $config);

        return AppModuleRoute::updateOrCreate(
            [
                'ruta' => $cleanRuta,
                'module_code' => $moduleCode,
            ],
            [
                'config' => $mergedConfig,
            ]
        );
    }
}

