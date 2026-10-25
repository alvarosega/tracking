<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Visitas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetVisitasInitDataAction
{
    /**
     * Catálogo de rutas comerciales y canales para el módulo de Visitas.
     *
     * @return array{
     *     catalogo: Collection,
     *     canales: array<int, string>,
     *     dias_disponibles: array<int, string>
     * }
     */
    public function execute(): array
    {
        $catalogo = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();
        $diasDisponibles = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        return [
            'catalogo' => $catalogo,
            'canales' => $canales,
            'dias_disponibles' => $diasDisponibles,
        ];
    }
}

