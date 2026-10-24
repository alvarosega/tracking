<?php

namespace App\Actions\Api;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\User;
use Illuminate\Support\Collection;

class GetAppModulesAction
{
    /**
     * Retorna los módulos móviles configurados para el usuario/ruta, aplicando los overrides correspondientes.
     */
    public function execute(?User $user): array
    {
        $ruta = $user ? trim((string) $user->ruta) : null;

        $modules = AppModule::orderBy('order')->get();

        $routeOverrides = collect();
        if (!empty($ruta)) {
            $routeOverrides = AppModuleRoute::where('ruta', $ruta)->get()->keyBy('module_code');
        }

        $result = $modules->map(function ($mod) use ($routeOverrides) {
            $override = $routeOverrides->get($mod->code);

            $isActive = $override !== null ? (bool) $override->is_active : (bool) $mod->is_active;

            $config = $mod->config ?? [];
            if ($override && !empty($override->config)) {
                $config = array_merge($config, $override->config);
            }

            return [
                'id' => $mod->id,
                'code' => $mod->code,
                'name' => $mod->name,
                'description' => $mod->description,
                'is_active' => $isActive,
                'order' => $mod->order,
                'config' => $config,
            ];
        });

        return [
            'ruta' => $ruta,
            'modules' => $result,
        ];
    }
}

