<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use App\Models\Supervisor\FactPreventa;

class GetPreventasInitDataAction
{
    public function __construct(
        private readonly GetCatalogoRutasAction $catalogoAction
    ) {}

    /**
     * Obtiene el catálogo de rutas, canales y fecha por defecto para preventas.
     *
     * @return array{
     *     catalogo_rutas: \Illuminate\Support\Collection,
     *     canales: array<int, string>,
     *     fecha_default: string
     * }
     */
    public function execute(): array
    {
        $catalogoData = $this->catalogoAction->execute();
        $ultimaFecha = FactPreventa::query()->max('fecha_norm');

        return [
            'catalogo_rutas' => $catalogoData['catalogo'],
            'canales' => $catalogoData['canales'],
            'fecha_default' => $ultimaFecha ?: date('Y-m-d'),
        ];
    }
}

