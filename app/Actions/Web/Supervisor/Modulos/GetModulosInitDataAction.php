<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetModulosInitDataAction
{
    /**
     * Obtiene los módulos registrados, el catálogo maestro de rutas enriquecidas y las configuraciones por ruta.
     *
     * @return array{
     *     modules: EloquentCollection,
     *     rutas: Collection,
     *     routeOverrides: EloquentCollection
     * }
     */
    public function execute(): array
    {
        $modules = AppModule::orderBy('order')->get();

        // 1. Obtener catálogo maestro de rutas comerciales (dim_rutas)
        $dimRows = DB::connection('supervisor')
            ->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->get();

        $metaRuta = $dimRows->keyBy(fn($r) => trim((string) $r->ruta));
        $rutasDim = $dimRows->pluck('ruta');

        // 2. Rutas adicionales en pan_ruteo
        $rutasPan = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->distinct()
            ->pluck('ruta');

        // 3. Rutas asignadas en la tabla users
        $rutasUsers = User::whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->distinct()
            ->pluck('ruta');

        // 4. Unificar y ordenar naturalmente
        $rutasUnicas = $rutasDim
            ->concat($rutasPan)
            ->concat($rutasUsers)
            ->map(fn($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // 5. Cargar usuarios vendedores para enriquecer información
        $vendedoresUsers = User::whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->select('ruta', 'username')
            ->get()
            ->keyBy(fn($u) => trim((string) $u->ruta));

        $rutasCatalog = $rutasUnicas->map(function ($r) use ($metaRuta, $vendedoresUsers) {
            $dimInfo = $metaRuta->get($r);
            $userInfo = $vendedoresUsers->get($r);

            return [
                'ruta' => $r,
                'vendedor' => $dimInfo?->vendedor ?? $userInfo?->username ?? 'No asignado',
                'canal' => $dimInfo?->canal ?? 'General',
            ];
        });

        // 6. Obtener todas las configuraciones específicas por ruta
        $routeOverrides = AppModuleRoute::all();

        return [
            'modules' => $modules,
            'rutas' => $rutasCatalog,
            'routeOverrides' => $routeOverrides,
        ];
    }
}

