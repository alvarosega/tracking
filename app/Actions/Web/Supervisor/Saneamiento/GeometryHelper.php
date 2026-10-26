<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

class GeometryHelper
{
    public const RADIO_TIERRA_METROS = 6371000.0;

    /**
     * Calcula la distancia geodésica exacta en metros entre dos coordenadas WGS84.
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lon2 - $lon1);

        $a = sin($deltaPhi / 2.0) ** 2 +
            cos($phi1) * cos($phi2) * (sin($deltaLambda / 2.0) ** 2);
        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return self::RADIO_TIERRA_METROS * $c;
    }

    /**
     * Calcula la envolvente convexa mínima 2D (Monotone Chain Algorithm - O(N log N)).
     * Entrada: lista de pares [[lat, lon], ...]
     * Salida: lista de vértices ordenados del polígono convexo.
     */
    public static function convexHull(array $puntos): array
    {
        if (count($puntos) <= 2) {
            return $puntos;
        }

        // Eliminar duplicados y ordenar por latitud y luego longitud
        $unique = [];
        foreach ($puntos as $p) {
            $key = round((float) $p[0], 7) . ',' . round((float) $p[1], 7);
            $unique[$key] = [(float) $p[0], (float) $p[1]];
        }
        $pts = array_values($unique);

        usort($pts, function ($a, $b) {
            if ($a[0] == $b[0]) {
                return $a[1] <=> $b[1];
            }
            return $a[0] <=> $b[0];
        });

        if (count($pts) <= 2) {
            return $pts;
        }

        $cross = function ($o, $a, $b) {
            return ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);
        };

        $lower = [];
        foreach ($pts as $p) {
            while (count($lower) >= 2 && $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) {
                array_pop($lower);
            }
            $lower[] = $p;
        }

        $upper = [];
        for ($i = count($pts) - 1; $i >= 0; $i--) {
            $p = $pts[$i];
            while (count($upper) >= 2 && $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) {
                array_pop($upper);
            }
            $upper[] = $p;
        }

        array_pop($lower);
        array_pop($upper);

        return array_merge($lower, $upper);
    }

    /**
     * Expande el polígono exteriormente mediante vectores normales por arista.
     * @param array $poligono Lista de pares [[lat, lon], ...]
     * @param float $bufferMetros Margen de expansión en metros
     * @return array Polígono expandido
     */
    public static function expandPolygonOffset(array $poligono, float $bufferMetros = 35.0): array
    {
        $n = count($poligono);
        if ($n < 3) {
            return $poligono;
        }

        $sumLat = 0.0;
        foreach ($poligono as $p) {
            $sumLat += $p[0];
        }
        $latMedia = $sumLat / $n;
        $escalaLat = 111139.0;
        $escalaLon = 111139.0 * cos(deg2rad($latMedia));
        if ($escalaLon == 0) {
            $escalaLon = 1.0;
        }

        $aristas = [];
        for ($i = 0; $i < $n; $i++) {
            $p1 = $poligono[$i];
            $p2 = $poligono[($i + 1) % $n];
            $dx = ($p2[1] - $p1[1]) * $escalaLon;
            $dy = ($p2[0] - $p1[0]) * $escalaLat;
            $longitud = hypot($dx, $dy);
            if ($longitud > 0) {
                $nx = $dy / $longitud;
                $ny = -$dx / $longitud;
            } else {
                $nx = 0.0;
                $ny = 0.0;
            }
            $aristas[] = [$nx, $ny];
        }

        $poligonoExpandido = [];
        for ($i = 0; $i < $n; $i++) {
            $n1 = $aristas[($i - 1 + $n) % $n];
            $n2 = $aristas[$i];
            $vnx = ($n1[0] + $n2[0]) / 2.0;
            $vny = ($n1[1] + $n2[1]) / 2.0;
            $norma = hypot($vnx, $vny);
            if ($norma > 0) {
                $vnx /= $norma;
                $vny /= $norma;
            }

            $offsetLat = ($vny * $bufferMetros) / $escalaLat;
            $offsetLon = ($vnx * $bufferMetros) / $escalaLon;

            $p = $poligono[$i];
            $poligonoExpandido[] = [
                round($p[0] + $offsetLat, 6),
                round($p[1] + $offsetLon, 6),
            ];
        }

        return $poligonoExpandido;
    }

    /**
     * Determina si un punto (lat, lon) está contenido en un polígono (Ray Casting Algorithm).
     * @param float $lat
     * @param float $lon
     * @param array $poligono Lista de pares [[lat, lon], ...]
     * @return bool
     */
    public static function pointInPolygon(float $lat, float $lon, array $poligono): bool
    {
        $n = count($poligono);
        if ($n < 3) {
            return false;
        }

        $adentro = false;
        $p1Lat = $poligono[0][0];
        $p1Lon = $poligono[0][1];

        for ($i = 1; $i <= $n; $i++) {
            $p2 = $poligono[$i % $n];
            $p2Lat = $p2[0];
            $p2Lon = $p2[1];

            if (min($p1Lon, $p2Lon) < $lon && $lon <= max($p1Lon, $p2Lon)) {
                if ($lat <= max($p1Lat, $p2Lat)) {
                    $xInters = $p1Lat;
                    if ($p1Lon != $p2Lon) {
                        $xInters = ($lon - $p1Lon) * ($p2Lat - $p1Lat) / ($p2Lon - $p1Lon) + $p1Lat;
                    }
                    if ($p1Lat == $p2Lat || $lat <= $xInters) {
                        $adentro = !$adentro;
                    }
                }
            }
            $p1Lat = $p2Lat;
            $p1Lon = $p2Lon;
        }

        return $adentro;
    }
}

