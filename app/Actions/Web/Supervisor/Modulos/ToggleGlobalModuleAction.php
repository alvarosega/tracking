<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\AppModule;

class ToggleGlobalModuleAction
{
    /**
     * Alterna el estado activo/inactivo por defecto global de un módulo móvil.
     */
    public function execute(int $id): AppModule
    {
        $module = AppModule::findOrFail($id);
        $module->is_active = !$module->is_active;
        $module->save();

        return $module;
    }
}

