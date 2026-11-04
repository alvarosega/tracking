<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Tracking;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetTrackingInitDataAction
{
    /**
     * Obtiene el catálogo de rutas comerciales con su canal y vendedor,
     * fusionando información de dim_rutas, pan_ruteo y visitas.
     *
     * @return array{
     *     catalogo: Collection,
     *     canales: array<int, string>
     * }
     */
    public function execute(): array
    {
        // 1. Catálogo base desde supervisor.dim_rutas
        $rutasDim = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->get();

        // 2. Rutas observadas en pan_ruteo
        $rutasPan = DB::connection('supervisor')->table('pan_ruteo')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->select('ruta', 'vendedor')
            ->distinct()
            ->get();

        // 3. Rutas observadas en visitas de la app
        $rutasVis = DB::table('visitas')
            ->whereNotNull('route')
            ->where('route', '!=', '')
            ->select('route as ruta')
            ->distinct()
            ->get();

        $rutasMap = [];

        foreach ($rutasDim as $r) {
            $rutaKey = trim((string)$r->ruta);
            if ($rutaKey === '') continue;
            $rutasMap[$rutaKey] = [
                'ruta' => $rutaKey,
                'canal' => trim((string)$r->canal) ?: $this->inferirCanal($rutaKey),
                'vendedor' => trim((string)$r->vendedor),
            ];
        }

        foreach ($rutasPan as $r) {
            $rutaKey = trim((string)$r->ruta);
            if ($rutaKey === '') continue;
            if (!isset($rutasMap[$rutaKey])) {
                $rutasMap[$rutaKey] = [
                    'ruta' => $rutaKey,
                    'canal' => $this->inferirCanal($rutaKey),
                    'vendedor' => trim((string)$r->vendedor),
                ];
            } elseif (empty($rutasMap[$rutaKey]['vendedor']) && !empty($r->vendedor)) {
                $rutasMap[$rutaKey]['vendedor'] = trim((string)$r->vendedor);
            }
        }

        foreach ($rutasVis as $r) {
            $rutaKey = trim((string)$r->ruta);
            if ($rutaKey === '') continue;
            if (!isset($rutasMap[$rutaKey])) {
                $rutasMap[$rutaKey] = [
                    'ruta' => $rutaKey,
                    'canal' => $this->inferirCanal($rutaKey),
                    'vendedor' => '',
                ];
            }
        }

        // Obtener users para vincular user_id si existe
        $usuarios = DB::table('users')->get(['id', 'username', 'ruta']);
        $userByUsername = $usuarios->keyBy('username');
        $userByRuta = $usuarios->whereNotNull('ruta')->keyBy('ruta');

        $catalogo = collect($rutasMap)->values()->map(function ($r) use ($userByUsername, $userByRuta) {
            $u = $userByUsername->get($r['ruta']) ?? $userByRuta->get($r['ruta']);
            return [
                'ruta' => $r['ruta'],
                'canal' => $r['canal'],
                'vendedor' => $r['vendedor'],
                'user_id' => $u ? $u->id : null,
            ];
        })->sortBy('ruta', SORT_NATURAL)->values();

        $canales = $catalogo->pluck('canal')->filter()->unique()->sort()->values()->toArray();

        return [
            'catalogo' => $catalogo,
            'canales' => $canales,
        ];
    }

    private function inferirCanal(string $ruta): string
    {
        $r = strtoupper(trim($ruta));
        if (str_starts_with($r, 'TDB')) return 'TDB';
        if (str_starts_with($r, 'MAY')) return 'MAY';
        if (str_starts_with($r, 'MZO')) return 'MZO';
        if (str_starts_with($r, 'ALT')) return 'ALT';
        if (str_starts_with($r, 'DIS')) return 'DIS';
        return 'OTROS';
    }
}
