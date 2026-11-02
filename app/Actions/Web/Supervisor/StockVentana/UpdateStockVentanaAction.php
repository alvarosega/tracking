<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\StockVentana;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use Illuminate\Support\Facades\DB;

class UpdateStockVentanaAction
{
    /**
     * Actualiza la ventana histórica por defecto global en app_modules.
     */
    public function updateGlobal(int $diasHistorial): AppModule
    {
        $diasHistorial = max(1, min(365, $diasHistorial));

        $modulo = AppModule::firstOrCreate(
            ['code' => 'inventario_cliente'],
            [
                'name' => 'Inventario en PDV',
                'description' => 'Módulo de toma de inventario físico en punto de venta',
                'order' => 6,
                'is_active' => true,
                'config' => ['dias_historial' => $diasHistorial],
            ]
        );

        $currentConfig = is_array($modulo->config) ? $modulo->config : [];
        $currentConfig['dias_historial'] = $diasHistorial;

        $modulo->config = $currentConfig;
        $modulo->save();

        return $modulo;
    }

    /**
     * Actualiza la ventana histórica para una ruta específica en app_module_routes.
     */
    public function updateRoute(string $ruta, int $diasHistorial): AppModuleRoute
    {
        $diasHistorial = max(1, min(365, $diasHistorial));
        $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));

        $override = AppModuleRoute::firstOrCreate(
            [
                'ruta' => $cleanRuta,
                'module_code' => 'inventario_cliente',
            ],
            [
                'is_active' => true,
                'config' => [],
            ]
        );

        $currentConfig = is_array($override->config) ? $override->config : [];
        $currentConfig['dias_historial'] = $diasHistorial;

        $override->config = $currentConfig;
        $override->save();

        return $override;
    }

    /**
     * Restablece la configuración de una ruta para que herede la ventana histórica global.
     */
    public function resetRoute(string $ruta): bool
    {
        $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));

        $override = AppModuleRoute::whereRaw("TRIM(UPPER(ruta)) = ?", [$cleanRuta])
            ->where('module_code', 'inventario_cliente')
            ->first();

        if (!$override) {
            return false;
        }

        $currentConfig = is_array($override->config) ? $override->config : [];
        unset($currentConfig['dias_historial']);

        $override->config = !empty($currentConfig) ? $currentConfig : null;
        $override->save();

        return true;
    }

    /**
     * Aplica la misma ventana histórica a un conjunto de rutas comerciales.
     *
     * @param array<int, string> $rutas
     * @param int $diasHistorial
     * @return int Cantidad de rutas actualizadas
     */
    public function batchUpdate(array $rutas, int $diasHistorial): int
    {
        $diasHistorial = max(1, min(365, $diasHistorial));
        $count = 0;

        DB::transaction(function () use ($rutas, $diasHistorial, &$count) {
            foreach ($rutas as $rawRuta) {
                if (empty(trim((string) $rawRuta))) {
                    continue;
                }
                $this->updateRoute((string) $rawRuta, $diasHistorial);
                $count++;
            }
        });

        return $count;
    }
}

