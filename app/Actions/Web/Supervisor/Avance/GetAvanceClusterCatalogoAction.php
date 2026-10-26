<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceClusterCatalogoAction
{
    /**
     * Retorna los canales, rutas, clusters y fechas límite para inicializar los filtros de Cluster vs LY.
     */
    public function execute(): array
    {
        // 1. Canales únicos desde dim_rutas
        $canales = DB::connection('supervisor')->table('dim_rutas')
            ->whereNotNull('canal')
            ->where('canal', '!=', '')
            ->selectRaw("DISTINCT TRIM(canal) as canal")
            ->orderBy('canal')
            ->pluck('canal')
            ->toArray();

        // 2. Rutas únicas desde dim_rutas
        $rutas = DB::connection('supervisor')->table('dim_rutas')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->selectRaw("DISTINCT TRIM(ruta) as ruta")
            ->orderBy('ruta')
            ->pluck('ruta')
            ->toArray();

        // 3. Clusters únicos observados en fact_ventas
        $clustersRaw = DB::connection('supervisor')->table('fact_ventas')
            ->whereNotNull('cluster_t')
            ->where('cluster_t', '!=', '')
            ->selectRaw("DISTINCT COALESCE(NULLIF(TRIM(cluster_t), ''), 'SIN CLUSTER') as cluster")
            ->pluck('cluster')
            ->toArray();

        $ordenEstandar = ['TDB', 'MZO', 'MAY', 'MICROMERCADO', 'FARMACIAS', 'OTROS', 'PROVINCIA'];
        $clustersOrdenados = [];
        foreach ($ordenEstandar as $c) {
            if (in_array($c, $clustersRaw, true)) {
                $clustersOrdenados[] = $c;
            }
        }
        foreach ($clustersRaw as $c) {
            if (!in_array($c, $clustersOrdenados, true)) {
                $clustersOrdenados[] = $c;
            }
        }

        // 4. Rango de fechas disponibles en fact_ventas
        $rFechas = DB::connection('supervisor')->table('fact_ventas')
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
            })
            ->where('monto_final', '>', 0)
            ->selectRaw("MIN(fecha_norm) as min_fecha, MAX(fecha_norm) as max_fecha")
            ->first();

        $maxFechaDb = $rFechas?->max_fecha ? Carbon::parse($rFechas->max_fecha) : Carbon::today();
        $minFechaDb = $rFechas?->min_fecha ? Carbon::parse($rFechas->min_fecha) : $maxFechaDb->copy()->subYear()->startOfYear();

        $fechaInicioDefault = $maxFechaDb->copy()->startOfYear();
        $fechaCorteDefault = $maxFechaDb->copy();

        return [
            'canales' => $canales,
            'rutas' => $rutas,
            'clusters' => $clustersOrdenados,
            'min_fecha' => $minFechaDb->format('Y-m-d'),
            'max_fecha' => $maxFechaDb->format('Y-m-d'),
            'fecha_inicio_default' => $fechaInicioDefault->format('Y-m-d'),
            'fecha_corte_default' => $fechaCorteDefault->format('Y-m-d'),
        ];
    }
}

