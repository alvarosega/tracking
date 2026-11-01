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
        $catalogoRaw = Ruta::query()
            ->whereNotNull('ruta')
            ->select(['ruta', 'canal', 'vendedor'])
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $catalogo = $catalogoRaw->map(function ($r) {
            return [
                'ruta' => trim((string) $r->ruta),
                'canal' => trim((string) $r->canal),
                'vendedor' => trim((string) $r->vendedor),
            ];
        })->filter(fn($r) => !empty($r['ruta']))->values();

        $canales = $catalogo->pluck('canal')
            ->filter(fn($c) => !empty($c))
            ->unique()
            ->values()
            ->toArray();

        return [
            'catalogo' => $catalogo,
            'canales' => $canales,
        ];
    }
}

