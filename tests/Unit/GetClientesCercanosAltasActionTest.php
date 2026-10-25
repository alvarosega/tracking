<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Web\Supervisor\AltasEdiciones\GetClientesCercanosAltasAction;
use PHPUnit\Framework\TestCase;

class GetClientesCercanosAltasActionTest extends TestCase
{
    private GetClientesCercanosAltasAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetClientesCercanosAltasAction();
    }

    public function test_detects_extreme_proximity(): void
    {
        $result = $this->action->analizarCoincidencias(
            candidatoNombre: 'Abarrotes Don Pepe',
            candidatoCelular: '71234567',
            candidatoTelefono: null,
            candidatoNit: '123456',
            clienteNombre: 'Farmacia San Antonio',
            clienteCelular: '79999999',
            clienteTelefono: '',
            clienteNit: '999999',
            distanciaMetros: 14.2,
            ruta: 'TDB 6A'
        );

        $this->assertContains('proximidad_extrema', $result['coincidencias']);
        $this->assertCount(1, $result['alertas']);
        $this->assertStringContainsString('Proximidad crítica', $result['alertas'][0]);
    }

    public function test_detects_duplicate_cellphone(): void
    {
        $result = $this->action->analizarCoincidencias(
            candidatoNombre: 'Tienda La Esmeralda',
            candidatoCelular: '70712345',
            candidatoTelefono: null,
            candidatoNit: null,
            clienteNombre: 'Doña Maria',
            clienteCelular: '70712345',
            clienteTelefono: '',
            clienteNit: '',
            distanciaMetros: 150.0,
            ruta: 'MZO 12'
        );

        $this->assertContains('telefono_duplicado', $result['coincidencias']);
        $this->assertStringContainsString('Teléfono repetido', $result['alertas'][0]);
    }

    public function test_detects_duplicate_nit(): void
    {
        $result = $this->action->analizarCoincidencias(
            candidatoNombre: 'Comercial El Carmen',
            candidatoCelular: '71111111',
            candidatoTelefono: null,
            candidatoNit: '4820192015',
            clienteNombre: 'Abarrotes El Carmen',
            clienteCelular: '72222222',
            clienteTelefono: '',
            clienteNit: '4820192015',
            distanciaMetros: 80.0,
            ruta: 'TDB 4B'
        );

        $this->assertContains('nit_duplicado', $result['coincidencias']);
    }

    public function test_detects_similar_name(): void
    {
        $similitud = $this->action->calcularSimilitudNombres('Abarrotes Don Pepe', 'Abarrotes Don Pepe SRL');
        $this->assertGreaterThanOrEqual(80.0, $similitud);

        $result = $this->action->analizarCoincidencias(
            candidatoNombre: 'Abarrotes Doña Carmen',
            candidatoCelular: '71111111',
            candidatoTelefono: null,
            candidatoNit: null,
            clienteNombre: 'Doña Carmen Abarrotes',
            clienteCelular: '72222222',
            clienteTelefono: '',
            clienteNit: '',
            distanciaMetros: 90.0,
            ruta: 'TDB 2A'
        );

        $this->assertContains('nombre_similar', $result['coincidencias']);
    }
}

