<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\AppModuleRoute;

class ApplyModuleToAllRoutesAction
{
    /**
     * Aplica el estado de un módulo a un listado masivo de rutas comerciales.
     *
     * @param string $moduleCode
     * @param bool $isActive
     * @param array<int, string> $rutas
     * @return int Cantidad de registros procesados
     */
    public function execute(string $moduleCode, bool $isActive, array $rutas): int
    {
        $now = now();
        $records = [];

        foreach ($rutas as $r) {
            $r = trim((string) $r);
            if (!empty($r)) {
                $records[] = [
                    'ruta' => $r,
                    'module_code' => $moduleCode,
                    'is_active' => $isActive ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($records)) {
            AppModuleRoute::upsert(
                $records,
                ['module_code', 'ruta'],
                ['is_active', 'updated_at']
            );
        }

        return count($records);
    }
}

