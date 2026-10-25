<?php

declare(strict_types=1);

namespace App\Services;

class GeoSpatialService
{
    /**
     * Radio medio de la Tierra en metros (WGS-84 aproximado).
     */
    public const EARTH_RADIUS_METERS = 6371000;

    /**
     * Calcula la distancia ortodrómica en metros entre dos coordenadas geográficas
     * utilizando la fórmula de Haversine.
     */
    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Determina si un par de coordenadas es geográficamente válido y distinto de (0,0).
     */
    public static function isValidCoordinates(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        return ($lat != 0.0 && $lng != 0.0 && $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0);
    }
}

