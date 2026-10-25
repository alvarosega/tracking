<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\AppModule;

class UpdateGlobalModuleConfigAction
{
    /**
     * Actualiza la configuración global por defecto de un módulo móvil.
     *
     * @param int $id
     * @param array<string, mixed> $config
     * @return AppModule
     */
    public function execute(int $id, array $config): AppModule
    {
        $module = AppModule::findOrFail($id);
        $module->config = array_merge($module->config ?? [], $config);
        $module->save();

        return $module;
    }
}

