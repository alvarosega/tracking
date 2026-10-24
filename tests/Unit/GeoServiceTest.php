<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GeoService;
use PHPUnit\Framework\TestCase;

class GeoServiceTest extends TestCase
{
    public function test_calculates_distance_between_two_coordinates_correctly(): void
    {
        $service = new GeoService();

        // Coordenadas aproximadas en La Paz: Plaza Murillo a San Francisco (~600m)
        $lat1 = -16.4957;
        $lon1 = -68.1336;
        $lat2 = -16.4965;
        $lon2 = -68.1375;

        $distance = $service->calculateDistanceInMeters($lat1, $lon1, $lat2, $lon2);

        $this->assertGreaterThan(300, $distance);
        $this->assertLessThan(600, $distance);
    }

    public function test_distance_is_zero_for_same_point(): void
    {
        $service = new GeoService();

        $lat = -16.5000;
        $lon = -68.1500;

        $distance = $service->calculateDistanceInMeters($lat, $lon, $lat, $lon);

        $this->assertEquals(0.0, $distance);
    }
}

