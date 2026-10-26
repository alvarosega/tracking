<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class GetSaneamientoCatalogoAction
{
    /**
     * Retorna el catálogo unificado de canales y rutas activas desde dim_rutas.
     *
     * @return array{canales: array<string>, rutas: array<array{ruta: string, canal: string, vendedor: string}>}
     */
    public function execute(): array
    {
        $canales = DB::connection('supervisor')
            ->table('dim_rutas')
            ->whereNotNull('canal')
            ->where(DB::raw("TRIM(canal)"), '!=', '')
            ->distinct()
            ->orderBy('canal')
            ->pluck('canal')
            ->map(fn($c) => trim((string) $c))
            ->values()
            ->all();

        $rutasRaw = DB::connection('supervisor')
            ->table('dim_rutas')
            ->whereNotNull('ruta')
            ->where(DB::raw("TRIM(ruta)"), '!=', '')
            ->whereNotNull('canal')
            ->where(DB::raw("TRIM(canal)"), '!=', '')
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta"),
                DB::raw("TRIM(canal) as canal"),
                DB::raw("COALESCE(TRIM(vendedor), 'Sin Asignar') as vendedor"),
            ])
            ->distinct()
            ->orderBy('canal')
            ->orderBy('ruta')
            ->get();

        $rutas = $rutasRaw->map(fn($r) => [
            'ruta' => (string) $r->ruta,
            'canal' => (string) $r->canal,
            'vendedor' => (string) $r->vendedor,
        ])->values()->all();

        return [
            'canales' => $canales,
            'rutas' => $rutas,
        ];
    }
}

