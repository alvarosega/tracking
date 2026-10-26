<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceBpcCatalogoAction
{
    private const CACHE_TTL_SECS = 3600;

    /**
     * Devuelve el catálogo de filtros disponible para el submódulo BPC.
     *
     * @return array{
     *     canales: array<string>,
     *     rutas: array<array{ruta: string, canal: string}>,
     *     clusters: array<string>,
     *     meses: array<string>,
     *     submodulos: array<string>,
     *     mes_evaluado_defecto: string,
     *     fecha_corte_defecto: string
     * }
     */
    public function execute(): array
    {
        return Cache::remember('avance_bpc_catalogo_filtros', self::CACHE_TTL_SECS, function () {
            // 1. Canales únicos
            $canales = DB::connection('supervisor')
                ->table('dim_rutas')
                ->whereNotNull('canal')
                ->where(DB::raw("TRIM(canal)"), '!=', '')
                ->distinct()
                ->orderBy('canal')
                ->pluck('canal')
                ->map(fn ($c) => trim((string) $c))
                ->values()
                ->toArray();

            // 2. Rutas con Canal
            $rutasRows = DB::connection('supervisor')
                ->table('dim_rutas')
                ->whereNotNull('ruta')
                ->where(DB::raw("TRIM(ruta)"), '!=', '')
                ->select([
                    DB::raw("TRIM(ruta) as ruta"),
                    DB::raw("COALESCE(TRIM(canal), 'SIN CANAL') as canal"),
                ])
                ->orderBy('ruta')
                ->get();

            $rutas = $rutasRows->map(fn ($r) => [
                'ruta' => (string) $r->ruta,
                'canal' => (string) $r->canal,
            ])->toArray();

            // 3. Clusters T únicos
            $clusters = DB::connection('supervisor')
                ->table('fact_ventas')
                ->whereNotNull('cluster_t')
                ->where(DB::raw("TRIM(cluster_t)"), '!=', '')
                ->distinct()
                ->orderBy('cluster_t')
                ->pluck('cluster_t')
                ->map(fn ($cl) => trim((string) $cl))
                ->values()
                ->toArray();

            // 4. Meses y fechas disponibles
            $mesesRows = DB::connection('supervisor')
                ->table('fact_ventas')
                ->whereNotNull('fecha_norm')
                ->select([
                    DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m') as anio_mes"),
                    DB::raw("CAST(MAX(fecha_norm) as CHAR) as max_fecha"),
                ])
                ->groupBy(DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m')"))
                ->orderByDesc('anio_mes')
                ->get();

            $meses = $mesesRows->pluck('anio_mes')->filter()->values()->toArray();
            $maxFecha = $mesesRows->first()?->max_fecha ? (string) $mesesRows->first()->max_fecha : date('Y-m-d');
            $mesDefecto = $meses[0] ?? date('Y-m');

            return [
                'canales' => $canales,
                'rutas' => $rutas,
                'clusters' => $clusters,
                'meses' => $meses,
                'submodulos' => ['cant_clientes', 'sell_out'],
                'mes_evaluado_defecto' => $mesDefecto,
                'fecha_corte_defecto' => $maxFecha,
            ];
        });
    }
}

