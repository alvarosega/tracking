<?php

declare(strict_types=1);

namespace App\Actions\Api\AppModules;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\User;

class GetAppModulesAction
{
    /**
     * Retorna los módulos móviles configurados para el usuario/ruta, aplicando los overrides correspondientes.
     *
     * @return array{ruta: string|null, modules: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    public function execute(?User $user): array
    {
        $ruta = $user ? trim((string) ($user->ruta ?: $user->username)) : null;

        $modules = AppModule::orderBy('order')->get();

        $routeOverrides = collect();
        if (!empty($ruta)) {
            $routeOverrides = AppModuleRoute::where('ruta', $ruta)->get()->keyBy('module_code');
        }

        $result = $modules->map(function ($mod) use ($routeOverrides): array {
            $override = $routeOverrides->get($mod->code);

            $isActive = $override !== null ? (bool) $override->is_active : (bool) $mod->is_active;

            $config = $mod->config ?? [];
            if ($override && !empty($override->config)) {
                $config = array_merge($config, $override->config);
            }

            // Garantiza que en JSON siempre sea un objeto {} y nunca un array []
            $configObject = !empty($config) ? $config : (object) [];

            return [
                'id' => $mod->id,
                'code' => $mod->code,
                'name' => $mod->name,
                'description' => $mod->description,
                'is_active' => $isActive,
                'order' => $mod->order,
                'config' => $configObject,
            ];
        });

        return [
            'ruta' => $ruta,
            'modules' => $result,
        ];
    }
}

