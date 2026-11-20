<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FactVenta;
use App\Models\Supervisor\FronteraDia;
use App\Models\Supervisor\FronteraRuta;
use App\Models\Supervisor\Ruta;
use Illuminate\Support\Facades\DB;

class GetCercanosInitDataAction
{
    /**
     * Obtiene los metadatos y catálogos iniciales para la vista de Clientes Cercanos.
     *
     * @return array{catalogo_rutas: \Illuminate\Database\Eloquent\Collection, canales: array, anios_disponibles: array, anio_default: int, meses_default: array}
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

        // Extraer los últimos 3 meses que realmente tienen compras registradas
        $ultimosMesesConDatos = FactVenta::query()
            ->validas()
            ->whereNotNull('fecha_norm')
            ->whereNotNull('mes')
            ->selectRaw('YEAR(fecha_norm) as anio, mes')
            ->distinct()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->limit(3)
            ->get();

        $anioPorDefecto = !empty($ultimosMesesConDatos) && $ultimosMesesConDatos->isNotEmpty()
            ? (int) $ultimosMesesConDatos->first()->anio
            : (int) date('Y');

        $mesesPorDefecto = !empty($ultimosMesesConDatos) && $ultimosMesesConDatos->isNotEmpty()
            ? $ultimosMesesConDatos->pluck('mes')->map(fn($m) => (int) $m)->values()->toArray()
            : [1, 2, 3];

        // Todos los años registrados para el selector
        $aniosDisponibles = FactVenta::query()
            ->validas()
            ->whereNotNull('fecha_norm')
            ->selectRaw('DISTINCT YEAR(fecha_norm) as anio')
            ->orderByDesc('anio')
            ->pluck('anio')
            ->toArray();

        // Fronteras días ya no se utilizan en clientes cercanos para optimizar la carga
        $fronterasDias = [];

        // Polígonos de fronteras rutas para visualización territorial
        // dim_rutas es la única fuente de la verdad: sólo se toman rutas que existan y estén activas en dim_rutas
        $poligonosRutas = DB::connection('supervisor')
            ->table('dim_fronteras_rutas as f')
            ->join('dim_rutas as r', DB::raw("TRIM(UPPER(f.ruta))"), '=', DB::raw("TRIM(UPPER(r.ruta))"))
            ->where('f.estado', 'ACTIVO')
            ->where('r.estado', 'ACTIVO')
            ->whereNotNull('f.poligono')
            ->select([
                'f.ruta',
                DB::raw("TRIM(r.canal) as canal"),
                DB::raw("ST_AsGeoJSON(f.poligono) as geojson"),
            ])
            ->orderBy('r.canal')
            ->orderBy('f.ruta')
            ->get();

        $fronterasRutas = [];
        foreach ($poligonosRutas as $fr) {
            if (!empty($fr->geojson)) {
                $fronterasRutas[] = [
                    'ruta' => $fr->ruta,
                    'canal' => strtoupper(trim((string) $fr->canal)),
                    'geojson' => json_decode($fr->geojson, true),
                ];
            }
        }

        return [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'anios_disponibles' => !empty($aniosDisponibles) ? $aniosDisponibles : [$anioPorDefecto],
            'anio_default' => $anioPorDefecto,
            'meses_default' => $mesesPorDefecto,
            'fronteras_rutas' => $fronterasRutas,
            'fronteras_dias' => $fronterasDias,
        ];
    }
}

