<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supervisor\Ruta;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\FronteraDia;
use App\Models\Supervisor\FronteraRuta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorRuteoController extends Controller
{
    public function index(): Response
    {
        $catalogo = Ruta::query()
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        return Inertia::render('Supervisor/Ruteo/Index', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
        ]);
    }

    public function getData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'canales' => ['nullable', 'array'],
            'canales.*' => ['string'],
            'rutas' => ['nullable', 'array'],
            'rutas.*' => ['string'],
            'dias' => ['nullable', 'array'],
            'dias.*' => ['string'],
            'ver_frontera_rutas' => ['boolean'],
            'ver_frontera_dias' => ['boolean'],
        ]);

        $canales = $validated['canales'] ?? [];
        $rutas = $validated['rutas'] ?? [];
        $dias = $validated['dias'] ?? [];
        $verFronteraRutas = $validated['ver_frontera_rutas'] ?? false;
        $verFronteraDias = $validated['ver_frontera_dias'] ?? false;

        if (empty($rutas) && empty($canales)) {
            return response()->json([
                'clientes' => [],
                'fronteras_rutas' => [],
                'fronteras_dias' => [],
                'kpis' => ['total' => 0, 'con_gps' => 0, 'sin_gps' => 0],
            ]);
        }

        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        $queryClientes = PanRuteo::query()->whereIn('ruta', $rutas);

        if (!empty($dias)) {
            $queryClientes->whereIn('dia_norm', $dias);
        }

        $clientesRaw = $queryClientes->select([
            'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
            'direccion', 'contacto', 'celular', 'latitud', 'longitud',
            'estado', 'ruta', 'dia_norm'
        ])->get();

        $kpis = ['total' => $clientesRaw->count(), 'con_gps' => 0, 'sin_gps' => 0];
        $clientes = [];

        foreach ($clientesRaw as $c) {
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
                'tiene_gps' => $tieneGps,
            ];
        }

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

        return response()->json([
            'clientes' => $clientes,
            'fronteras_rutas' => $fronterasRutas,
            'fronteras_dias' => $fronterasDias,
            'kpis' => $kpis,
        ]);
    }

    public function cercanos(): Response
    {
        $catalogo = Ruta::query()
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        return Inertia::render('Supervisor/Ruteo/Cercanos', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
        ]);
    }

    public function getCercanosData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitud' => ['required', 'numeric'],
            'longitud' => ['required', 'numeric'],
            'radio' => ['required', 'integer', 'min:50', 'max:5000'],
            'canales' => ['nullable', 'array'],
            'canales.*' => ['string'],
            'rutas' => ['nullable', 'array'],
            'rutas.*' => ['string'],
        ]);

        $lat = (float)$validated['latitud'];
        $lng = (float)$validated['longitud'];
        $radio = (int)$validated['radio'];
        $canales = $validated['canales'] ?? [];
        $rutas = $validated['rutas'] ?? [];

        // Resolver rutas si se filtró por canal
        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        // Bounding box previo en grados (aprox 1 grado latitud = 111,139 m)
        $deltaLat = ($radio + 150) / 111139.0;
        $deltaLng = ($radio + 150) / (111139.0 * cos(deg2rad($lat)));

        $query = PanRuteo::query()
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->where('latitud', '!=', 0)
            ->whereBetween('latitud', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('longitud', [$lng - $deltaLng, $lng + $deltaLng]);

        if (!empty($rutas)) {
            $query->whereIn('ruta', $rutas);
        }

        // Fórmula Haversine en MariaDB
        $haversine = "(6371000 * 2 * ASIN(SQRT(
            POWER(SIN(RADIANS(latitud - {$lat}) / 2), 2) +
            COS(RADIANS({$lat})) * COS(RADIANS(latitud)) *
            POWER(SIN(RADIANS(longitud - {$lng}) / 2), 2)
        )))";

        $clientes = $query
            ->select([
                'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
                'direccion', 'contacto', 'celular', 'latitud', 'longitud',
                'ruta', 'dia_norm',
                DB::raw("ROUND({$haversine}) AS distancia_metros")
            ])
            ->having('distancia_metros', '<=', $radio)
            ->orderBy('distancia_metros', 'ASC')
            ->get();

        return response()->json([
            'clientes' => $clientes,
            'total' => $clientes->count(),
            'centro' => ['latitud' => $lat, 'longitud' => $lng],
            'radio' => $radio,
        ]);
    }
}