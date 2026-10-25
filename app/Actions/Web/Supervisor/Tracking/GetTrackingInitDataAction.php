<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Tracking;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetTrackingInitDataAction
{
    /**
     * Obtiene el catálogo de rutas comerciales asociadas con su user_id en alva.users.
     *
     * @return array{
     *     catalogo: Collection,
     *     canales: array<int, string>
     * }
     */
    public function execute(): array
    {
        // 1. Catálogo desde supervisor.dim_rutas
        $rutas = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $usuarios = DB::table('users')
            ->select('id', 'username')
            ->get()
            ->keyBy('username');

        $catalogo = $rutas->map(function ($r) use ($usuarios) {
            $user = $usuarios->get($r->ruta);
            return [
                'ruta' => $r->ruta,
                'canal' => $r->canal,
                'vendedor' => $r->vendedor,
                'user_id' => $user ? $user->id : null,
            ];
        });

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        return [
            'catalogo' => $catalogo,
            'canales' => $canales,
        ];
    }
}

