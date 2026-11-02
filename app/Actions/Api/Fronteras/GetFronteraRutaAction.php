<?php

declare(strict_types=1);

namespace App\Actions\Api\Fronteras;

use App\Actions\Web\Supervisor\Saneamiento\GeometryHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetFronteraRutaAction
{
    /**
     * Retorna el polígono de frontera de la ruta comercial para la aplicación móvil Android.
     *
     * @param User $user
     * @param string|null $rutaParam Ruta opcional a consultar; si es null, toma la ruta del vendedor autenticado
     * @return array<string, mixed>|null
     */
    public function execute(User $user, ?string $rutaParam = null): ?array
    {
        $ruta = !empty($rutaParam) ? trim($rutaParam) : trim((string) ($user->ruta ?: $user->username));
        if ($ruta === '') {
            return null;
        }

        $cleanRuta = strtoupper(trim(preg_replace('/\s+/', ' ', $ruta)));

        // 1. Consultar polígono activo en dim_fronteras_rutas
        try {
            $row = DB::connection('supervisor')
                ->table('dim_fronteras_rutas as f')
                ->leftJoin('dim_rutas as r', DB::raw("TRIM(UPPER(f.ruta))"), '=', DB::raw("TRIM(UPPER(r.ruta))"))
                ->whereRaw("TRIM(UPPER(f.ruta)) = ?", [$cleanRuta])
                ->where('f.estado', 'ACTIVO')
                ->select([
                    'f.id',
                    'f.ruta',
                    DB::raw("COALESCE(TRIM(r.canal), TRIM(f.canal), 'TDB') as canal"),
                    'f.version',
                    'f.origen',
                    DB::raw('ST_AsGeoJSON(f.poligono) as geojson_str'),
                ])
                ->first();

            if ($row && !empty($row->geojson_str)) {
                return $this->formatFronteraResponse(
                    ruta: (string) $row->ruta,
                    canal: (string) $row->canal,
                    version: (int) $row->version,
                    origen: (string) $row->origen,
                    geojsonStr: (string) $row->geojson_str
                );
            }
        } catch (\Throwable $e) {
            Log::error("GetFronteraRutaAction dim_fronteras_rutas error for {$cleanRuta}: " . $e->getMessage());
        }

        // 2. Si no tiene polígono explícito registrado, generar Convex Hull dinámico con los clientes de pan_ruteo
        return $this->generarFronteraFallback($cleanRuta);
    }

    /**
     * Formatea la estructura completa para Retrofit y Google Maps en Android.
     *
     * @return array<string, mixed>|null
     */
    protected function formatFronteraResponse(
        string $ruta,
        string $canal,
        int $version,
        string $origen,
        string $geojsonStr
    ): ?array {
        $gj = json_decode($geojsonStr, true);
        if (!$gj || empty($gj['coordinates'][0])) {
            return null;
        }

        $coordsLonLat = $gj['coordinates'][0];
        $puntos = [];
        $poligonoLatLon = [];

        foreach ($coordsLonLat as $pt) {
            $lat = (float) $pt[1];
            $lon = (float) $pt[0];
            $puntos[] = [
                'latitud' => $lat,
                'longitud' => $lon,
            ];
            $poligonoLatLon[] = [$lat, $lon];
        }

        return [
            'ruta' => $ruta,
            'canal' => $canal,
            'version' => $version,
            'origen' => $origen,
            'total_puntos' => count($puntos),
            'puntos' => $puntos,
            'poligono_latlon' => $poligonoLatLon,
            'geojson' => $gj,
        ];
    }

    /**
     * Calcula la envolvente convexa con margen de seguridad a partir de los clientes activos de pan_ruteo.
     *
     * @return array<string, mixed>|null
     */
    protected function generarFronteraFallback(string $cleanRuta): ?array
    {
        try {
            $pts = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->whereRaw("TRIM(UPPER(ruta)) = ?", [$cleanRuta])
                ->where(function ($w): void {
                    $w->whereNull('estado')
                      ->orWhere('estado', '')
                      ->orWhere('estado', 'Activo')
                      ->orWhereRaw("UPPER(TRIM(estado)) IN ('ACTIVO', 'ACTIVA', 'VIGENTE')");
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

                    // Cerrar el polígono si no está cerrado
                    if ($polyOffset[0] !== end($polyOffset)) {
                        $polyOffset[] = $polyOffset[0];
                    }

                    $puntos = [];
                    $poligonoLatLon = [];
                    $coordsGeoJson = [];

                    foreach ($polyOffset as $pt) {
                        $lat = (float) $pt[0];
                        $lon = (float) $pt[1];
                        $puntos[] = ['latitud' => $lat, 'longitud' => $lon];
                        $poligonoLatLon[] = [$lat, $lon];
                        $coordsGeoJson[] = [$lon, $lat];
                    }

                    return [
                        'ruta' => $cleanRuta,
                        'canal' => 'TDB',
                        'version' => 1,
                        'origen' => 'AUTOMATICO_FALLBACK',
                        'total_puntos' => count($puntos),
                        'puntos' => $puntos,
                        'poligono_latlon' => $poligonoLatLon,
                        'geojson' => [
                            'type' => 'Polygon',
                            'coordinates' => [$coordsGeoJson],
                        ],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("GetFronteraRutaAction fallback error for {$cleanRuta}: " . $e->getMessage());
        }

        return null;
    }
}

