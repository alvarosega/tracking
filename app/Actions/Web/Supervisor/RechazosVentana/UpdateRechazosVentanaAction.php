<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\RechazosVentana;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Services\PedidosRechazados\RechazosVentanaService;
use Illuminate\Support\Facades\DB;

class UpdateRechazosVentanaAction
{
    public function __construct(
        protected RechazosVentanaService $service
    ) {}

    /**
     * Actualiza la configuración global del módulo de pedidos rechazados.
     *
     * @param array<string, mixed> $newConfig
     * @return AppModule
     */
    public function updateGlobal(array $newConfig): AppModule
    {
        $modulo = $this->service->getGlobalModule();
        $currentConfig = is_array($modulo->config) ? $modulo->config : [];

        // Fusión limpia preservando claves
        $updatedConfig = array_merge($currentConfig, $newConfig);

        // Si se limpió la fecha específica, asegurar que quede en null
        if (array_key_exists('fecha_especifica_activa', $newConfig) && empty($newConfig['fecha_especifica_activa'])) {
            $updatedConfig['fecha_especifica_activa'] = null;
            $updatedConfig['fecha_especifica_hasta'] = null;
        }

        $modulo->config = $updatedConfig;
        $modulo->save();

        return $modulo;
    }

    /**
     * Actualiza la configuración específica de una ruta en app_module_routes.
     *
     * @param string $ruta
     * @param array<string, mixed> $routeConfig
     * @return AppModuleRoute
     */
    public function updateRoute(string $ruta, array $routeConfig): AppModuleRoute
    {
        $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));

        $override = AppModuleRoute::firstOrCreate(
            [
                'ruta' => $cleanRuta,
                'module_code' => RechazosVentanaService::MODULE_CODE,
            ],
            [
                'is_active' => true,
                'config' => [],
            ]
        );

        $current = is_array($override->config) ? $override->config : [];
        $updated = array_merge($current, $routeConfig);

        if (array_key_exists('fecha_especifica_activa', $routeConfig) && empty($routeConfig['fecha_especifica_activa'])) {
            $updated['fecha_especifica_activa'] = null;
            $updated['fecha_especifica_hasta'] = null;
        }

        $override->config = $updated;
        $override->save();

        return $override;
    }

    /**
     * Activa o desactiva el módulo para una ruta comercial específica.
     */
    public function toggleRoute(string $ruta, bool $isActive): AppModuleRoute
    {
        $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));

        $override = AppModuleRoute::firstOrCreate(
            [
                'ruta' => $cleanRuta,
                'module_code' => RechazosVentanaService::MODULE_CODE,
            ],
            [
                'is_active' => $isActive,
                'config' => [],
            ]
        );

        $override->is_active = $isActive;
        $override->save();

        return $override;
    }

    /**
     * Restablece la configuración de una ruta para que herede la configuración global.
     */
    public function resetRoute(string $ruta): bool
    {
        $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));

        $override = AppModuleRoute::whereRaw("REPLACE(UPPER(ruta), ' ', '') = ?", [str_replace(' ', '', $cleanRuta)])
            ->where('module_code', RechazosVentanaService::MODULE_CODE)
            ->first();

        if (!$override) {
            return false;
        }

        $override->config = null;
        $override->save();

        return true;
    }

    /**
     * Aplica la misma configuración a un lote de rutas comerciales.
     *
     * @param array<int, string> $rutas
     * @param array<string, mixed> $config
     * @return int
     */
    public function batchUpdate(array $rutas, array $config): int
    {
        $count = 0;
        DB::transaction(function () use ($rutas, $config, &$count) {
            foreach ($rutas as $rawRuta) {
                if (empty(trim((string) $rawRuta))) {
                    continue;
                }
                $this->updateRoute((string) $rawRuta, $config);
                $count++;
            }
        });

        return $count;
    }
}

