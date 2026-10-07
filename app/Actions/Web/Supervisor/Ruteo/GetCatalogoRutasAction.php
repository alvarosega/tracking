<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\Ruta;
use Illuminate\Database\Eloquent\Collection;

class GetCatalogoRutasAction
{
    /**
     * Obtiene el catálogo completo de rutas únicas con canal y vendedor asignado.
     *
     * @return array{catalogo: Collection, canales: array<string>}
     */
    public function execute(): array
    {
        $catalogo = Ruta::query()
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return [
            'catalogo' => $catalogo,
            'canales' => $canales,
        ];
    }
}

