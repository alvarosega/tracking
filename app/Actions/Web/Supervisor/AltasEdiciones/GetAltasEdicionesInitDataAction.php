<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Illuminate\Support\Facades\DB;

class GetAltasEdicionesInitDataAction
{
    /**
     * Obtiene el catálogo maestro de rutas, canales y los metadatos de tipos y estados
     * de saneamiento_base para la inicialización del módulo Altas & Ediciones.
     *
     * @return array{
     *     catalogo_rutas: \Illuminate\Support\Collection,
     *     canales: array<int, string>,
     *     tipos_registro: array<int, string>,
     *     estados_revision: array<int, string>
     * }
     */
    public function execute(): array
    {
        // 1. Catálogo de Rutas y Canales desde supervisor.dim_rutas
        $catalogo = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        // 2. Extraer dinámicamente los tipos de registro existentes
        $tiposRegistro = DB::connection('supervisor')->table('saneamiento_base')
            ->select('tipo_registro')
            ->distinct()
            ->whereNotNull('tipo_registro')
            ->where('tipo_registro', '!=', '')
            ->pluck('tipo_registro')
            ->values()
            ->toArray();

        // 3. Extraer dinámicamente los estados de revisión existentes
        $estadosRevision = DB::connection('supervisor')->table('saneamiento_base')
            ->select('estado_revision')
            ->distinct()
            ->whereNotNull('estado_revision')
            ->where('estado_revision', '!=', '')
            ->pluck('estado_revision')
            ->values()
            ->toArray();

        return [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'tipos_registro' => $tiposRegistro,
            'estados_revision' => $estadosRevision,
        ];
    }
}

