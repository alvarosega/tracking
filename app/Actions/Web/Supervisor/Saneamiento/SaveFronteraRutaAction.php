<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveFronteraRutaAction
{
    /**
     * Persiste una frontera como POLYGON (SRID 4326) versionando el historial.
     *
     * @param string $ruta
     * @param array<array<float>> $coordenadasLatLon [[lat, lon], ...]
     * @param string $origen
     * @param string|null $canal
     * @return int ID de la frontera registrada
     */
    public function execute(
        string $ruta,
        array $coordenadasLatLon,
        string $origen = 'MANUAL_SUPERVISOR',
        ?string $canal = null
    ): int {
        if (count($coordenadasLatLon) < 3) {
            throw new InvalidArgumentException('Un polígono debe tener al menos 3 vértices.');
        }

        $rutaClean = strtoupper(trim($ruta));
        $conn = DB::connection('supervisor');

        // Obtener canal oficial
        $dimRuta = $conn->table('dim_rutas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->first(['canal']);

        $canalOficial = $dimRuta && !empty($dimRuta->canal)
            ? trim((string) $dimRuta->canal)
            : ($canal ? trim($canal) : 'SIN CANAL');

        // Asegurar que el polígono esté cerrado
        $pts = $coordenadasLatLon;
        if ($pts[0] !== $pts[count($pts) - 1]) {
            $pts[] = $pts[0];
        }

        // En WKT: Longitud primero, Latitud después
        $wktPairs = [];
        foreach ($pts as $p) {
            $lat = (float) $p[0];
            $lon = (float) $p[1];
            $wktPairs[] = "{$lon} {$lat}";
        }
        $wktCoords = implode(', ', $wktPairs);
        $wktPolygon = "POLYGON(({$wktCoords}))";

        $actual = $conn->table('dim_fronteras_rutas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->where('estado', 'ACTIVO')
            ->first(['id', 'version']);

        $nuevaVersion = 1;
        if ($actual) {
            $nuevaVersion = ((int) $actual->version) + 1;

            // Limpiar históricos viejos para no sobrecargar
            $conn->table('dim_fronteras_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('estado', 'HISTORICO')
                ->delete();

            // Archivar actual
            $conn->table('dim_fronteras_rutas')
                ->where('id', $actual->id)
                ->update(['estado' => 'HISTORICO']);
        }

        $id = $conn->table('dim_fronteras_rutas')->insertGetId([
            'ruta' => $rutaClean,
            'canal' => $canalOficial,
            'poligono' => DB::raw("ST_GeomFromText('{$wktPolygon}', 4326)"),
            'version' => $nuevaVersion,
            'estado' => 'ACTIVO',
            'origen' => $origen,
        ]);

        return (int) $id;
    }
}

