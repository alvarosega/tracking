<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supervisor\Ruta;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\FronteraDia;
use App\Models\Supervisor\FronteraRuta;
use App\Models\Supervisor\FactVenta;
use App\Models\Supervisor\FactPreventa;
use App\Models\Supervisor\Visita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ResponseFactory;

class SupervisorRuteoController extends Controller
{
    // ... index() y getData() se mantienen exactamente iguales ...

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
    public function getVisitasCliente($clienteId): JsonResponse
    {
        $visitas = Visita::query()
            ->where('client_id', $clienteId)
            ->orderByDesc('visited_at')
            ->get();

        $resultado = $visitas->map(function ($v) {
            return [
                'id' => $v->id,
                'client_id' => $v->client_id,
                'route' => $v->route,
                'status' => $v->status,
                'comments' => $v->comments,
                'latitude' => $v->latitude,
                'longitude' => $v->longitude,
                'accuracy' => $v->accuracy,
                'visited_at' => $v->visited_at ? $v->visited_at->format('Y-m-d H:i:s') : null,
                'fecha' => $v->visited_at ? $v->visited_at->format('d/m/Y') : null,
                'hora' => $v->visited_at ? $v->visited_at->format('H:i') : null,
                'photo_url' => $v->photo_url,
            ];
        });

        return response()->json([
            'cliente_id' => $clienteId,
            'total_visitas' => $resultado->count(),
            'visitas' => $resultado,
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

        // Extraer los últimos 3 meses que realmente tienen compras registradas
        $ultimosMesesConDatos = FactVenta::query()
            ->validas()
            ->whereNotNull('fecha_norm')
            ->whereNotNull('mes')
            ->selectRaw('YEAR(fecha_norm) as anio, mes')
            ->distinct()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->limit(3)
            ->get();

        $anioPorDefecto = !empty($ultimosMesesConDatos) && $ultimosMesesConDatos->isNotEmpty()
            ? (int)$ultimosMesesConDatos->first()->anio
            : (int)date('Y');

        $mesesPorDefecto = !empty($ultimosMesesConDatos) && $ultimosMesesConDatos->isNotEmpty()
            ? $ultimosMesesConDatos->pluck('mes')->map(fn($m) => (int)$m)->values()->toArray()
            : [1, 2, 3];

        // Todos los años registrados para el selector
        $aniosDisponibles = FactVenta::query()
            ->validas()
            ->whereNotNull('fecha_norm')
            ->selectRaw('DISTINCT YEAR(fecha_norm) as anio')
            ->orderByDesc('anio')
            ->pluck('anio')
            ->toArray();

        return Inertia::render('Supervisor/Ruteo/Cercanos', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'anios_disponibles' => !empty($aniosDisponibles) ? $aniosDisponibles : [$anioPorDefecto],
            'anio_default' => $anioPorDefecto,
            'meses_default' => $mesesPorDefecto,
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
            'meses' => ['nullable', 'array'],
            'meses.*' => ['integer'],
            'anio' => ['nullable', 'integer'],
        ]);

        $lat = (float)$validated['latitud'];
        $lng = (float)$validated['longitud'];
        $radio = (int)$validated['radio'];
        $canales = $validated['canales'] ?? [];
        $rutas = $validated['rutas'] ?? [];
        $meses = $validated['meses'] ?? [];
        $anio = (int)($validated['anio'] ?? date('Y'));

        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        $deltaLat = ($radio + 150) / 111139.0;
        $deltaLng = ($radio + 150) / (111139.0 * cos(deg2rad($lat)));

        $haversine = "(6371000 * 2 * ASIN(SQRT(
            POWER(SIN(RADIANS(latitud - {$lat}) / 2), 2) +
            COS(RADIANS({$lat})) * COS(RADIANS(latitud)) *
            POWER(SIN(RADIANS(longitud - {$lng}) / 2), 2)
        )))";

        $query = PanRuteo::query()
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->where('latitud', '!=', 0)
            ->whereBetween('latitud', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('longitud', [$lng - $deltaLng, $lng + $deltaLng]);

        if (!empty($rutas)) {
            $query->whereIn('ruta', $rutas);
        }

        $clientes = $query
            ->select([
                'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
                'direccion', 'contacto', 'celular', 'latitud', 'longitud',
                'ruta', 'dia_norm', 'nit', 'nombre_factura',
                DB::raw("ROUND({$haversine}) AS distancia_metros")
            ])
            ->having('distancia_metros', '<=', $radio)
            ->orderBy('distancia_metros', 'ASC')
            ->get();

        $clienteIds = $clientes->pluck('cliente_id')->filter()->unique()->toArray();
        $comprasMap = [];

        if (!empty($clienteIds) && !empty($meses)) {
            $queryVentas = FactVenta::query()
                ->validas()
                ->whereIn('cliente_id', $clienteIds)
                ->whereBetween('fecha_norm', ["{$anio}-01-01", "{$anio}-12-31"])
                ->whereIn('mes', $meses);

            $comprasAgregadas = $queryVentas
                ->groupBy('cliente_id')
                ->select([
                    'cliente_id',
                    DB::raw('ROUND(SUM(monto_final), 2) as total_monto'),
                    DB::raw('COUNT(DISTINCT venta_id) as total_pedidos')
                ])
                ->get();

            foreach ($comprasAgregadas as $ca) {
                $comprasMap[$ca->cliente_id] = [
                    'total_monto' => (float)$ca->total_monto,
                    'total_pedidos' => (int)$ca->total_pedidos,
                ];
            }
        }

        $resultado = $clientes->map(function ($c) use ($comprasMap) {
            $compra = $comprasMap[$c->cliente_id] ?? ['total_monto' => 0.0, 'total_pedidos' => 0];
            return [
                'id' => $c->id,
                'cliente_id' => $c->cliente_id,
                'cliente' => $c->cliente,
                'vendedor' => $c->vendedor,
                'tipo_negocio' => $c->tipo_negocio,
                'direccion' => $c->direccion,
                'contacto' => $c->contacto,
                'celular' => $c->celular,
                'latitud' => (float)$c->latitud,
                'longitud' => (float)$c->longitud,
                'ruta' => $c->ruta,
                'dia_norm' => $c->dia_norm,
                'nit' => $c->nit,
                'nombre_factura' => $c->nombre_factura,
                'distancia_metros' => (int)$c->distancia_metros,
                'total_compras' => $compra['total_monto'],
                'total_pedidos' => $compra['total_pedidos'],
            ];
        });

        return response()->json([
            'clientes' => $resultado,
            'total' => $resultado->count(),
            'centro' => ['latitud' => $lat, 'longitud' => $lng],
            'radio' => $radio,
            'anio' => $anio,
        ]);
    }

    public function getVentasCliente(Request $request, $clienteId): JsonResponse
    {
        $validated = $request->validate([
            'meses' => ['nullable', 'array'],
            'meses.*' => ['integer'],
            'anio' => ['nullable', 'integer'],
        ]);

        $meses = $validated['meses'] ?? [];
        $anio = (int)($validated['anio'] ?? date('Y'));

        $query = FactVenta::query()
            ->validas()
            ->where('cliente_id', $clienteId)
            ->whereBetween('fecha_norm', ["{$anio}-01-01", "{$anio}-12-31"]);

        if (!empty($meses)) {
            $query->whereIn('mes', $meses);
        }

        $filas = $query
            ->select([
                'id', 'venta_id', 'producto_id', 'fecha_norm', 'hora',
                'cliente_id', 'cliente', 'vendedor', 'tipo_pago', 'nro_factura',
                'codigo', 'producto', 'categoria', 'cantidad', 'precio_unitario',
                'descuento', 'monto_final', 'mes'
            ])
            ->orderByDesc('fecha_norm')
            ->orderByDesc('hora')
            ->get();

        $ventasAgrupadas = [];
        $totalGeneral = 0.0;

        foreach ($filas as $f) {
            $vId = $f->venta_id;
            if (!isset($ventasAgrupadas[$vId])) {
                $ventasAgrupadas[$vId] = [
                    'venta_id' => $vId,
                    'fecha' => $f->fecha_norm,
                    'hora' => $f->hora,
                    'mes' => $f->mes,
                    'nro_factura' => $f->nro_factura,
                    'vendedor' => $f->vendedor,
                    'tipo_pago' => $f->tipo_pago,
                    'monto_ticket' => 0.0,
                    'productos' => [],
                ];
            }

            $ventasAgrupadas[$vId]['monto_ticket'] += (float)$f->monto_final;
            $totalGeneral += (float)$f->monto_final;

            $ventasAgrupadas[$vId]['productos'][] = [
                'id' => $f->id,
                'codigo' => $f->codigo,
                'producto' => $f->producto,
                'categoria' => $f->categoria,
                'cantidad' => $f->cantidad,
                'precio_unitario' => (float)$f->precio_unitario,
                'descuento' => (float)$f->descuento,
                'monto_final' => (float)$f->monto_final,
            ];
        }

        return response()->json([
            'cliente_id' => $clienteId,
            'anio' => $anio,
            'total_general' => round($totalGeneral, 2),
            'total_ventas' => count($ventasAgrupadas),
            'ventas' => array_values($ventasAgrupadas),
        ]);
    }
public function preventas(): Response
    {
        $catalogo = Ruta::query()
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        // Extraer la fecha más reciente con datos reales de preventa
        $ultimaFecha = FactPreventa::query()->max('fecha_norm');

        return Inertia::render('Supervisor/Ruteo/Preventas', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'fecha_default' => $ultimaFecha ?: date('Y-m-d'),
        ]);
    }

    public function getPreventasData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'canales' => ['nullable', 'array'],
            'canales.*' => ['string'],
            'rutas' => ['nullable', 'array'],
            'rutas.*' => ['string'],
            'latitud' => ['nullable', 'numeric'],
            'longitud' => ['nullable', 'numeric'],
            'radio' => ['nullable', 'integer'],
        ]);

        $fecha = $validated['fecha'];
        $canales = $validated['canales'] ?? [];
        $rutas = $validated['rutas'] ?? [];
        $lat = isset($validated['latitud']) ? (float)$validated['latitud'] : null;
        $lng = isset($validated['longitud']) ? (float)$validated['longitud'] : null;
        $radio = isset($validated['radio']) ? (int)$validated['radio'] : 300;

        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        // Consulta de ítems de preventa para esa fecha
        $query = FactPreventa::query()->where('fecha_norm', $fecha);

        if (!empty($rutas)) {
            $query->whereIn('ruta', $rutas);
        }

        $preventasRaw = $query->select([
            'id', 'nro_preventa', 'cliente_id', 'codigo_cliente', 'cliente',
            'vendedor', 'zona', 'producto_id', 'codigo_producto', 'producto',
            'categoria', 'cantidad', 'monto_final', 'ruta', 'dia_visita',
            'facturar', 'nro_carga'
        ])->get();

        if ($preventasRaw->isEmpty()) {
            return response()->json([
                'clientes' => [],
                'kpis' => [
                    'total_preventas' => 0,
                    'total_clientes' => 0,
                    'monto_total' => 0.0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                    'en_radio_conteo' => 0,
                    'en_radio_monto' => 0.0,
                ],
            ]);
        }

        // Obtener coordenadas de los clientes desde pan_ruteo
        $clienteIds = $preventasRaw->pluck('cliente_id')->filter()->unique()->toArray();
        $clientesGeo = PanRuteo::query()
            ->whereIn('cliente_id', $clienteIds)
            ->select(['cliente_id', 'latitud', 'longitud', 'direccion', 'contacto', 'celular', 'tipo_negocio'])
            ->get()
            ->keyBy('cliente_id');

        // Agrupar jerárquicamente: Cliente -> nro_preventa -> productos
        $clientesAgrupados = [];
        $totalGeneralMonto = 0.0;
        $nrosPreventaUnicos = [];

        foreach ($preventasRaw as $item) {
            $cId = $item->cliente_id;
            $nroPrev = $item->nro_preventa;
            $nrosPreventaUnicos[$nroPrev] = true;
            $totalGeneralMonto += (float)$item->monto_final;

            if (!isset($clientesAgrupados[$cId])) {
                $geo = $clientesGeo->get($cId);
                $hasGps = ($geo && !empty($geo->latitud) && !empty($geo->longitud) && (float)$geo->latitud != 0.0);

                $distancia = null;
                if ($hasGps && $lat !== null && $lng !== null) {
                    $distancia = (int)round(6371000 * 2 * asin(sqrt(
                        pow(sin(deg2rad($geo->latitud - $lat) / 2), 2) +
                        cos(deg2rad($lat)) * cos(deg2rad($geo->latitud)) *
                        pow(sin(deg2rad($geo->longitud - $lng) / 2), 2)
                    )));
                }

                $clientesAgrupados[$cId] = [
                    'cliente_id' => $cId,
                    'codigo_cliente' => $item->codigo_cliente,
                    'cliente' => $item->cliente,
                    'zona' => $item->zona,
                    'ruta' => $item->ruta,
                    'vendedor' => $item->vendedor,
                    'direccion' => $geo ? $geo->direccion : 'Sin dirección',
                    'contacto' => $geo ? $geo->contacto : null,
                    'celular' => $geo ? $geo->celular : null,
                    'tipo_negocio' => $geo ? $geo->tipo_negocio : 'General',
                    'latitud' => $hasGps ? (float)$geo->latitud : null,
                    'longitud' => $hasGps ? (float)$geo->longitud : null,
                    'tiene_gps' => $hasGps,
                    'distancia_metros' => $distancia,
                    'en_radio' => ($distancia !== null && $distancia <= $radio),
                    'total_monto' => 0.0,
                    'pedidos' => [],
                ];
            }

            $clientesAgrupados[$cId]['total_monto'] += (float)$item->monto_final;

            if (!isset($clientesAgrupados[$cId]['pedidos'][$nroPrev])) {
                $clientesAgrupados[$cId]['pedidos'][$nroPrev] = [
                    'nro_preventa' => $nroPrev,
                    'facturar' => $item->facturar,
                    'nro_carga' => $item->nro_carga,
                    'monto_pedido' => 0.0,
                    'items' => [],
                ];
            }

            $clientesAgrupados[$cId]['pedidos'][$nroPrev]['monto_pedido'] += (float)$item->monto_final;
            $clientesAgrupados[$cId]['pedidos'][$nroPrev]['items'][] = [
                'id' => $item->id,
                'codigo_producto' => $item->codigo_producto,
                'producto' => $item->producto,
                'categoria' => $item->categoria,
                'cantidad' => $item->cantidad,
                'monto_final' => (float)$item->monto_final,
            ];
        }

        $listaClientes = array_values($clientesAgrupados);

        // Aplanar los pedidos a arreglo indexado
        foreach ($listaClientes as &$cli) {
            $cli['total_monto'] = round($cli['total_monto'], 2);
            $cli['pedidos'] = array_values($cli['pedidos']);
            foreach ($cli['pedidos'] as &$p) {
                $p['monto_pedido'] = round($p['monto_pedido'], 2);
            }
        }
        unset($cli);

        // Si hay coordenadas del supervisor, ordenar por proximidad
        if ($lat !== null && $lng !== null) {
            usort($listaClientes, function ($a, $b) {
                if ($a['distancia_metros'] === null) return 1;
                if ($b['distancia_metros'] === null) return -1;
                return $a['distancia_metros'] <=> $b['distancia_metros'];
            });
        }

        $kpis = [
            'total_preventas' => count($nrosPreventaUnicos),
            'total_clientes' => count($listaClientes),
            'monto_total' => round($totalGeneralMonto, 2),
            'con_gps' => count(array_filter($listaClientes, fn($c) => $c['tiene_gps'])),
            'sin_gps' => count(array_filter($listaClientes, fn($c) => !$c['tiene_gps'])),
            'en_radio_conteo' => count(array_filter($listaClientes, fn($c) => $c['en_radio'])),
            'en_radio_monto' => round(array_sum(array_map(fn($c) => $c['en_radio'] ? $c['total_monto'] : 0, $listaClientes)), 2),
        ];

        return response()->json([
            'clientes' => $listaClientes,
            'kpis' => $kpis,
        ]);
    }
}