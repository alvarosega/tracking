<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveFronteraDiaAction
{
    /**
     * Persiste o actualiza la frontera de un día en dim_fronteras_dias.
     *
     * @param string $ruta
     * @param string $dia
     * @param array<array<float>> $coordenadasLatLon
     * @param string $origen
     * @return int
     */
    public function execute(
        string $ruta,
        string $dia,
        array $coordenadasLatLon,
        string $origen = 'MANUAL_SUPERVISOR'
    ): int {
        if (count($coordenadasLatLon) < 3) {
            throw new InvalidArgumentException('Un polígono debe tener al menos 3 vértices.');
        }

        $rutaClean = strtoupper(trim($ruta));
        $diaClean = strtoupper(trim($dia));
        $conn = DB::connection('supervisor');

        $pts = $coordenadasLatLon;
        if ($pts[0] !== $pts[count($pts) - 1]) {
            $pts[] = $pts[0];
        }

        $wktPairs = [];
        foreach ($pts as $p) {
            $lat = (float) $p[0];
            $lon = (float) $p[1];
            $wktPairs[] = "{$lon} {$lat}";
        }
        $wktCoords = implode(', ', $wktPairs);
        $wktPolygon = "POLYGON(({$wktCoords}))";

        $actual = $conn->table('dim_fronteras_dias')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->where(DB::raw("TRIM(UPPER(dia))"), $diaClean)
            ->where('estado', 'ACTIVO')
            ->first(['id', 'version']);

        $nuevaVersion = 1;
        if ($actual) {
            $nuevaVersion = ((int) $actual->version) + 1;

            $conn->table('dim_fronteras_dias')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(DB::raw("TRIM(UPPER(dia))"), $diaClean)
                ->where('estado', 'HISTORICO')
                ->delete();

            $conn->table('dim_fronteras_dias')
                ->where('id', $actual->id)
                ->update(['estado' => 'HISTORICO']);
        }

        $id = $conn->table('dim_fronteras_dias')->insertGetId([
            'ruta' => $rutaClean,
            'dia' => $diaClean,
            'poligono' => DB::raw("ST_GeomFromText('{$wktPolygon}', 4326)"),
            'version' => $nuevaVersion,
            'estado' => 'ACTIVO',
            'origen' => $origen,
        ]);

        return (int) $id;
    }
}

