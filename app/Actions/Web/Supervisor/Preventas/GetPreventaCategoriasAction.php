<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventaCategoriasAction
{
    /**
     * Obtiene las categorías de productos disponibles para la fecha y canal seleccionados.
     *
     * @param string|null $fecha
     * @param string|null $canal
     * @return array<int, string>
     */
    public function execute(?string $fecha = null, ?string $canal = null): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        try {
            $query = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->whereNotNull('categoria')
                ->whereRaw("TRIM(categoria) != ''");

            if ($canal && $canal !== 'TODOS') {
                $canalesList = array_filter(array_map('trim', explode(',', strtoupper($canal))));
                if (!empty($canalesList)) {
                    $query->whereIn(DB::raw("UPPER(TRIM(ruta))"), function ($sub) use ($canalesList): void {
                        $sub->select(DB::raw("UPPER(TRIM(ruta))"))
                            ->from('dim_rutas')
                            ->whereIn(DB::raw("UPPER(TRIM(canal))"), $canalesList);
                    });
                }
            }

            return $query->select(DB::raw("DISTINCT TRIM(categoria) as cat"))
                ->orderBy('cat', 'asc')
                ->pluck('cat')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo categorías de productos en preventa: ' . $e->getMessage());
            return [];
        }
    }
}

