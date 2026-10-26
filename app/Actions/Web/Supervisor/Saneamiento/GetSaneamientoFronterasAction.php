<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class GetSaneamientoFronterasAction
{
    /**
     * Garantiza semillas iniciales y recupera todos los polígonos del canal/sistema.
     *
     * @param string|null $canal
     * @return array{canal: string, total_fronteras: int, fronteras: array}
     */
    public function execute(?string $canal = null): array
    {
        $this->asegurarFronterasSemillaFaltantes($canal);

        $query = DB::connection('supervisor')
            ->table('dim_fronteras_rutas as f')
            ->join('dim_rutas as r', DB::raw("TRIM(UPPER(f.ruta))"), '=', DB::raw("TRIM(UPPER(r.ruta))"))
            ->where('f.estado', 'ACTIVO')
            ->select([
                'f.id',
                DB::raw("TRIM(UPPER(f.ruta)) as ruta"),
                DB::raw("COALESCE(TRIM(r.canal), TRIM(f.canal), 'SIN CANAL') as canal"),
                'f.version',
                'f.origen',
                DB::raw("ST_AsGeoJSON(f.poligono) as geojson_str"),
            ])
            ->orderBy('r.canal')
            ->orderBy('f.ruta');

        if ($canal && strtoupper(trim($canal)) !== 'TODOS') {
            $query->where(DB::raw("TRIM(r.canal)"), trim($canal));
        }

        $rows = $query->get();

        $fronteras = [];
        foreach ($rows as $row) {
            if (empty($row->geojson_str)) {
                continue;
            }

            try {
                $gj = json_decode((string) $row->geojson_str, true);
                $coordsLonLat = $gj['coordinates'][0] ?? [];
                $coordsLatLon = [];
                foreach ($coordsLonLat as $pt) {
                    $coordsLatLon[] = [(float) $pt[1], (float) $pt[0]];
                }

                if (count($coordsLatLon) >= 3) {
                    $fronteras[] = [
                        'id' => (int) $row->id,
                        'ruta' => (string) $row->ruta,
                        'canal' => (string) $row->canal,
                        'version' => (int) $row->version,
                        'origen' => (string) $row->origen,
                        'poligono_latlon' => $coordsLatLon,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [
            'canal' => $canal ?: 'TODOS',
            'total_fronteras' => count($fronteras),
            'fronteras' => $fronteras,
        ];
    }

    /**
     * Calcula y persiste el Convex Hull para rutas activas en pan_ruteo sin frontera registrada.
     */
    protected function asegurarFronterasSemillaFaltantes(?string $canal = null): void
    {
        $conn = DB::connection('supervisor');

        $queryFaltantes = $conn->table('pan_ruteo as p')
            ->join('dim_rutas as r', DB::raw("TRIM(UPPER(p.ruta))"), '=', DB::raw("TRIM(UPPER(r.ruta))"))
            ->where(function ($w) {
                $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                    ->orWhereNull('p.estado')
                    ->orWhereRaw("p.estado = ''");
            })
            ->whereNotNull('p.ruta')
            ->where(DB::raw("TRIM(p.ruta)"), '!=', '')
            ->whereNotIn(DB::raw("TRIM(UPPER(p.ruta))"), function ($sub) {
                $sub->select(DB::raw("DISTINCT TRIM(UPPER(ruta))"))
                    ->from('dim_fronteras_rutas')
                    ->where('estado', 'ACTIVO');
            })
            ->select([
                DB::raw("DISTINCT TRIM(UPPER(p.ruta)) as ruta"),
                DB::raw("COALESCE(TRIM(r.canal), 'SIN CANAL') as canal"),
            ]);

        if ($canal && strtoupper(trim($canal)) !== 'TODOS') {
            $queryFaltantes->where(DB::raw("TRIM(r.canal)"), trim($canal));
        }

        $rutasSinFrontera = $queryFaltantes->get();

        $saveAction = new SaveFronteraRutaAction();

        foreach ($rutasSinFrontera as $item) {
            $ruta = (string) $item->ruta;
            $canalRuta = (string) $item->canal;

            $pts = $conn->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $ruta)
                ->where(function ($w) {
                    $w->whereRaw("LOWER(TRIM(estado)) = 'activo'")
                        ->orWhereNull('estado')
                        ->orWhereRaw("estado = ''");
                })
                ->whereNotNull('latitud')
                ->where('latitud', '!=', 0.0)
                ->whereNotNull('longitud')
                ->where('longitud', '!=', 0.0)
                ->select(['latitud', 'longitud'])
                ->get();

            if ($pts->count() >= 3) {
                $coords = $pts->map(fn($p) => [(float) $p->latitud, (float) $p->longitud])->all();
                $hull = GeometryHelper::convexHull($coords);
                if (count($hull) >= 3) {
                    $polyOffset = GeometryHelper::expandPolygonOffset($hull, 35.0);
                    $saveAction->execute($ruta, $polyOffset, 'AUTOMATICO_PUNTOS', $canalRuta);
                }
            }
        }
    }
}

