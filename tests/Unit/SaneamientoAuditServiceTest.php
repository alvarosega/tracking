<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SaneamientoAuditService;
use PHPUnit\Framework\TestCase;

class SaneamientoAuditServiceTest extends TestCase
{
    private SaneamientoAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SaneamientoAuditService();
    }

    public function test_detects_clean_client_correctly(): void
    {
        $cleanClient = [
            'cliente' => 'JUAN CARLOS PEREZ',
            'celular' => '71234567',
            'telefono' => '22812345',
            'direccion' => 'AV. 6 DE MARZO NRO 1500',
            'latitud' => -16.512345,
            'longitud' => -68.123456,
            'nit' => '4820192015',
        ];

        $result = $this->service->audit($cleanClient);

        $this->assertFalse($result['requiere_saneamiento']);
        $this->assertFalse($result['saneamiento_obligatorio']);
        $this->assertEmpty($result['alertas']);
        $this->assertEquals(100, $result['calidad_score']);
    }

    public function test_detects_single_name_without_surname(): void
    {
        $client = [
            'cliente' => 'JUAN',
            'celular' => '71234567',
            'direccion' => 'CALLE REAL 100',
            'latitud' => -16.5,
            'longitud' => -68.1,
            'nit' => '12345',
        ];

        $result = $this->service->audit($client);

        $this->assertTrue($result['requiere_saneamiento']);
        $this->assertTrue($result['saneamiento_obligatorio']);
        $this->assertContains('apellido', $result['campos_incompletos']);
    }

    public function test_detects_numbers_and_dots_in_name(): void
    {
        $client = [
            'cliente' => 'TIENDA DÑA. ROSA 2',
            'celular' => '71234567',
            'direccion' => 'CALLE REAL 100',
            'latitud' => -16.5,
            'longitud' => -68.1,
            'nit' => '12345',
        ];

        $result = $this->service->audit($client);

        $this->assertTrue($result['requiere_saneamiento']);
        $this->assertContains('cliente', $result['campos_incompletos']);
    }

    public function test_detects_missing_phones_and_bad_directions(): void
    {
        $client = [
            'cliente' => 'MARIA MAMANI',
            'celular' => '',
            'telefono' => null,
            'direccion' => 'S/D',
            'latitud' => 0.0,
            'longitud' => 0.0,
            'nit' => '0',
        ];

        $result = $this->service->audit($client);

        $this->assertTrue($result['requiere_saneamiento']);
        $this->assertTrue($result['saneamiento_obligatorio']);
        $this->assertContains('celular', $result['campos_incompletos']);
        $this->assertContains('direccion', $result['campos_incompletos']);
        $this->assertContains('gps', $result['campos_incompletos']);
        $this->assertContains('nit', $result['campos_incompletos']);
    }
}

