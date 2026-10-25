<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Web\Supervisor\Ruteo\ResolveMapsUrlAction;
use PHPUnit\Framework\TestCase;

class ResolveMapsUrlActionTest extends TestCase
{
    private ResolveMapsUrlAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResolveMapsUrlAction();
    }

    public function test_extracts_coordinates_from_plain_text(): void
    {
        $res = $this->action->execute('-16.477399, -68.170044');
        $this->assertTrue($res['success']);
        $this->assertEquals(-16.477399, $res['latitud']);
        $this->assertEquals(-68.170044, $res['longitud']);
        $this->assertEquals('coordenadas_directas', $res['tipo']);
    }

    public function test_extracts_from_search_url(): void
    {
        $url = 'https://www.google.com/maps/search/-16.477399,+-68.170044?entry=tts';
        $res = $this->action->execute($url);
        $this->assertTrue($res['success']);
        $this->assertEquals(-16.477399, $res['latitud']);
        $this->assertEquals(-68.170044, $res['longitud']);
    }

    public function test_extracts_from_place_url(): void
    {
        $url = 'https://www.google.com/maps/place/Tienda+El+Sol/@-17.393456,-66.156789,17z/data=!4m5';
        $res = $this->action->execute($url);
        $this->assertTrue($res['success']);
        $this->assertEquals(-17.393456, $res['latitud']);
        $this->assertEquals(-66.156789, $res['longitud']);
    }

    public function test_extracts_from_query_param(): void
    {
        $url = 'https://maps.google.com/?q=-17.41258,-66.15487';
        $res = $this->action->execute($url);
        $this->assertTrue($res['success']);
        $this->assertEquals(-17.41258, $res['latitud']);
        $this->assertEquals(-66.15487, $res['longitud']);
    }

    public function test_fails_gracefully_on_invalid_input(): void
    {
        $res = $this->action->execute('texto que no es url ni coordenadas');
        $this->assertFalse($res['success']);
    }
}

