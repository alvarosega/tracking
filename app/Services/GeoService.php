<?php

declare(strict_types=1);

namespace App\Services;

class GeoService
{
    public const EARTH_RADIUS_METERS = 6371000;

    /**
     * Calcula la distancia geodésica en metros entre dos coordenadas usando la fórmula de Haversine.
     */
    public function calculateDistanceInMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_METERS * $c, 2);
    }
}
