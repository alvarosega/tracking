<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Api\Saneamiento\GetClientesCercanosAction;
use PHPUnit\Framework\TestCase;

class GetClientesCercanosActionTest extends TestCase
{
    private GetClientesCercanosAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetClientesCercanosAction();
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
            distanciaMetros: 12.5,
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
        $this->assertStringContainsString('Teléfono duplicado', $result['alertas'][0]);
    }

    public function test_detects_duplicate_nit(): void
    {
        $result = $this->action->analizarCoincidencias(
            candidatoNombre: 'Micromercado El Sol',
            candidatoCelular: '71111111',
            candidatoTelefono: null,
            candidatoNit: '4820192015',
            clienteNombre: 'Comercial El Sol',
            clienteCelular: '72222222',
            clienteTelefono: '',
            clienteNit: '4820192015',
            distanciaMetros: 80.0,
            ruta: 'TDB 4B'
        );

        $this->assertContains('nit_duplicado', $result['coincidencias']);
        $hasNitAlert = false;
        foreach ($result['alertas'] as $alerta) {
            if (str_contains($alerta, 'NIT duplicado')) {
                $hasNitAlert = true;
                break;
            }
        }
        $this->assertTrue($hasNitAlert);
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

    public function test_clean_client_with_no_duplicates(): void
    {
        $result = $this->action->analizarCoincidencias(
            candidatoNombre: 'Libreria Cervantes',
            candidatoCelular: '71234567',
            candidatoTelefono: null,
            candidatoNit: '555666777',
            clienteNombre: 'Frial El Buen Corte',
            clienteCelular: '79998877',
            clienteTelefono: '4455667',
            clienteNit: '111222333',
            distanciaMetros: 120.0,
            ruta: 'TDB 6A'
        );

        $this->assertEmpty($result['coincidencias']);
        $this->assertEmpty($result['alertas']);
    }
}
