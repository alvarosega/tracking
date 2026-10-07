<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FronteraDia;
use App\Models\Supervisor\FronteraRuta;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\Ruta;
use Illuminate\Support\Facades\DB;

class GetRuteoDataAction
{
    /**
     * Consulta y estructura los puntos de clientes y polígonos fronterizos para el Visor GIS.
     *
     * @param array<string> $canales
     * @param array<string> $rutas
     * @param array<string> $dias
     * @param bool $verFronteraRutas
     * @param bool $verFronteraDias
     * @param bool $soloActivos
     * @return array{clientes: array, fronteras_rutas: array, fronteras_dias: array, kpis: array}
     */
    public function execute(
        array $canales = [],
        array $rutas = [],
        array $dias = [],
        bool $verFronteraRutas = false,
        bool $verFronteraDias = false,
        bool $soloActivos = true
    ): array {
        if (empty($rutas) && empty($canales)) {
            return [
                'clientes' => [],
                'fronteras_rutas' => [],
                'fronteras_dias' => [],
                'kpis' => [
                    'total' => 0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                    'activos' => 0,
                    'inactivos' => 0,
                ],
            ];
        }

        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        $queryClientes = PanRuteo::query()->whereIn('ruta', $rutas);

        if ($soloActivos) {
            $queryClientes->where(function ($q) {
                $q->where('estado', 'Activo')
                  ->orWhereNull('estado');
            });
        }

        if (!empty($dias)) {
            $queryClientes->whereIn('dia_norm', $dias);
        }

        $clientesRaw = $queryClientes->select([
            'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
            'direccion', 'contacto', 'celular', 'latitud', 'longitud',
            'estado', 'ruta', 'dia_norm'
        ])->get();

        $kpis = [
            'total' => $clientesRaw->count(),
            'con_gps' => 0,
            'sin_gps' => 0,
            'activos' => 0,
            'inactivos' => 0,
        ];
        $clientes = [];

        foreach ($clientesRaw as $c) {
            $esActivo = (strtolower((string)$c->estado) === 'activo' || empty($c->estado));
            if ($esActivo) {
                $kpis['activos']++;
            } else {
                $kpis['inactivos']++;
            }

            $tieneGps = (!empty($c->latitud) && !empty($c->longitud) && (float)$c->latitud != 0.0);
            if ($tieneGps) {
                $kpis['con_gps']++;
            } else {
                $kpis['sin_gps']++;
            }

            $clientes[] = [
                'id' => $c->id,
                'cliente_id' => $c->cliente_id,
                'nombre' => $c->cliente,
                'tipo_negocio' => $c->tipo_negocio ?? 'General',
                'direccion' => $c->direccion ?? 'Sin dirección registrada',
                'contacto' => $c->contacto,
                'celular' => $c->celular,
                'latitud' => (float)$c->latitud,
                'longitud' => (float)$c->longitud,
                'ruta' => $c->ruta,
                'dia_norm' => strtoupper(trim((string)$c->dia_norm)),
                'estado' => $c->estado ?? 'Activo',
                'tiene_gps' => $tieneGps,
            ];
        }

        // Consulta de Polígonos de Rutas
        $fronterasRutas = [];
        if ($verFronteraRutas && !empty($rutas)) {
            $poligonosRutas = FronteraRuta::query()
                ->activos()
                ->whereIn('ruta', $rutas)
                ->select(['ruta', 'canal', DB::raw('ST_AsGeoJSON(poligono) as geojson')])
                ->get();

            foreach ($poligonosRutas as $fr) {
                if (!empty($fr->geojson)) {
                    $fronterasRutas[] = [
                        'ruta' => $fr->ruta,
                        'canal' => $fr->canal,
                        'geojson' => json_decode($fr->geojson, true),
                    ];
                }
            }
        }

        // Consulta de Polígonos de Días
        $fronterasDias = [];
        if ($verFronteraDias && !empty($rutas)) {
            $queryFronterasDias = FronteraDia::query()
                ->activos()
                ->whereIn('ruta', $rutas);

            if (!empty($dias)) {
                $queryFronterasDias->whereIn('dia', $dias);
            }

            $poligonosDias = $queryFronterasDias
                ->select(['ruta', 'dia', DB::raw('ST_AsGeoJSON(poligono) as geojson')])
                ->get();

            foreach ($poligonosDias as $fd) {
                if (!empty($fd->geojson)) {
                    $fronterasDias[] = [
                        'ruta' => $fd->ruta,
                        'dia' => strtoupper(trim((string)$fd->dia)),
                        'geojson' => json_decode($fd->geojson, true),
                    ];
                }
            }
        }

        return [
            'clientes' => $clientes,
            'fronteras_rutas' => $fronterasRutas,
            'fronteras_dias' => $fronterasDias,
            'kpis' => $kpis,
        ];
    }
}

