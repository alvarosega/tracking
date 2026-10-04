<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorVisitasController extends Controller
{
    public function index(): Response
    {
        // Catálogo de rutas y canales desde supervisor.dim_rutas
        $catalogo = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        $diasDisponibles = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        return Inertia::render('Supervisor/Visitas/Index', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'dias_disponibles' => $diasDisponibles,
        ]);
    }

public function data(Request $request): JsonResponse
    {
        $dias = $request->input('dias', []);
        $canales = $request->input('canales', []);
        $rutas = $request->input('rutas', []);
        $incluirInactivosConFoto = (bool) $request->input('inactivos_con_foto', false);

        if (empty($rutas) && empty($canales) && empty($dias)) {
            return response()->json([
                'clientes' => [],
                'kpis' => [
                    'total' => 0,
                    'con_foto' => 0,
                    'sin_foto' => 0,
                    'cobertura_pct' => 0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                ],
            ]);
        }

        $rutasFiltradas = $rutas;
        if (!empty($canales)) {
            $rutasPorCanal = DB::connection('supervisor')->table('dim_rutas')
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->toArray();

            $rutasFiltradas = !empty($rutas)
                ? array_values(array_intersect($rutas, $rutasPorCanal))
                : $rutasPorCanal;
        }

        // Consulta consolidada por cliente_id para evitar duplicados
        $query = DB::connection('supervisor')->table('pan_ruteo as pr')
            ->leftJoin('dim_rutas as dr', 'pr.ruta', '=', 'dr.ruta')
            ->select([
                'pr.cliente_id',
                DB::raw("COALESCE(NULLIF(MAX(pr.cliente), ''), MAX(pr.cliente_norm), 'Cliente #' || pr.cliente_id) as cliente"),
                DB::raw("MAX(pr.ruta) as ruta"),
                DB::raw("COALESCE(MAX(dr.canal), 'PRT') as canal"),
                DB::raw("MAX(pr.vendedor) as vendedor"),
                DB::raw("GROUP_CONCAT(DISTINCT COALESCE(NULLIF(pr.dia, ''), pr.dia_norm) ORDER BY pr.id SEPARATOR ', ') as dias_visita"),
                DB::raw("MAX(pr.direccion) as direccion"),
                DB::raw("MAX(pr.referencia) as referencia"),
                DB::raw("COALESCE(MAX(NULLIF(pr.celular, '')), MAX(pr.telefono)) as telefono"),
                DB::raw("MAX(pr.contacto) as contacto"),
                DB::raw("MAX(pr.tipo_negocio) as tipo_negocio"),
                DB::raw("MAX(pr.nit) as nit"),
                DB::raw("MAX(pr.latitud) as latitud"),
                DB::raw("MAX(pr.longitud) as longitud"),
                DB::raw("MAX(pr.estado) as estado"),
            ])
            ->groupBy('pr.cliente_id');

        if (!empty($rutasFiltradas)) {
            $query->whereIn('pr.ruta', $rutasFiltradas);
        }

        if (!empty($dias)) {
            $query->where(function ($q) use ($dias) {
                $q->whereIn('pr.dia', $dias)
                  ->orWhereIn('pr.dia_norm', $dias);

                if (in_array('Miércoles', $dias)) {
                    $q->orWhereIn('pr.dia', ['Miercoles', 'MIERCOLES'])
                      ->orWhereIn('pr.dia_norm', ['Miercoles', 'MIERCOLES']);
                }
                if (in_array('Sábado', $dias)) {
                    $q->orWhereIn('pr.dia', ['Sabado', 'SABADO'])
                      ->orWhereIn('pr.dia_norm', ['Sabado', 'SABADO']);
                }
            });
        }

        if (!$incluirInactivosConFoto) {
            $query->where('pr.estado', 'Activo');
        } else {
            $query->whereIn('pr.estado', ['Activo', 'Inactivo']);
        }

        $clientesRaw = $query->get();

        if ($clientesRaw->isEmpty()) {
            return response()->json([
                'clientes' => [],
                'kpis' => [
                    'total' => 0,
                    'con_foto' => 0,
                    'sin_foto' => 0,
                    'cobertura_pct' => 0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                ],
            ]);
        }

        // Historial de fotos en alva.visitas
        $clientIds = $clientesRaw->pluck('cliente_id')->toArray();

        $visitasResumen = DB::table('visitas')
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', '')
            ->selectRaw('client_id, COUNT(id) as total_fotos, MAX(visited_at) as ultima_foto_at')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        $clientes = [];
        $totalConFoto = 0;
        $totalSinFoto = 0;
        $totalConGps = 0;
        $totalSinGps = 0;

        foreach ($clientesRaw as $c) {
            $visita = $visitasResumen->get($c->cliente_id);
            $tieneFoto = !empty($visita) && $visita->total_fotos > 0;

            if ($c->estado === 'Inactivo' && !$tieneFoto) {
                continue;
            }

            $lat = (float) $c->latitud;
            $lng = (float) $c->longitud;
            $tieneGps = ($lat != 0.0 && $lng != 0.0 && $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0);

            if ($tieneFoto) {
                $totalConFoto++;
            } else {
                $totalSinFoto++;
            }

            if ($tieneGps) {
                $totalConGps++;
            } else {
                $totalSinGps++;
            }

            $clientes[] = [
                'cliente_id' => $c->cliente_id,
                'cliente' => $c->cliente,
                'ruta' => $c->ruta,
                'canal' => $c->canal,
                'vendedor' => $c->vendedor,
                'dias_visita' => $c->dias_visita ?: 'No asignado',
                'direccion' => $c->direccion,
                'referencia' => $c->referencia,
                'telefono' => $c->telefono,
                'contacto' => $c->contacto,
                'tipo_negocio' => $c->tipo_negocio,
                'nit' => $c->nit,
                'estado' => $c->estado,
                'latitud' => $tieneGps ? $lat : null,
                'longitud' => $tieneGps ? $lng : null,
                'tiene_gps' => $tieneGps,
                'tiene_foto' => $tieneFoto,
                'total_fotos' => $tieneFoto ? (int) $visita->total_fotos : 0,
                'ultima_foto_at' => $tieneFoto ? $visita->ultima_foto_at : null,
            ];
        }

        $total = count($clientes);
        $coberturaPct = $total > 0 ? round(($totalConFoto / $total) * 100, 1) : 0;

        return response()->json([
            'clientes' => $clientes,
            'kpis' => [
                'total' => $total,
                'con_foto' => $totalConFoto,
                'sin_foto' => $totalSinFoto,
                'cobertura_pct' => $coberturaPct,
                'con_gps' => $totalConGps,
                'sin_gps' => $totalSinGps,
            ],
        ]);
    }

    public function avance(): Response
    {
        $catalogo = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        return Inertia::render('Supervisor/Visitas/Avance', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'fecha_default' => date('Y-m-d'),
        ]);
    }

    public function avanceData(Request $request): JsonResponse
    {
        $fecha = $request->input('fecha', date('Y-m-d'));
        $canales = $request->input('canales', []);
        $rutas = $request->input('rutas', []);

        if (empty($rutas) && empty($canales)) {
            return response()->json([
                'rutas_trayectorias' => [],
                'kpis' => [
                    'total_fotos' => 0,
                    'rutas_activas' => 0,
                    'en_rango' => 0,
                    'desviadas' => 0,
                ],
            ]);
        }

        $rutasFiltradas = $rutas;
        if (!empty($canales)) {
            $rutasPorCanal = DB::connection('supervisor')->table('dim_rutas')
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->toArray();

            $rutasFiltradas = !empty($rutas)
                ? array_values(array_intersect($rutas, $rutasPorCanal))
                : $rutasPorCanal;
        }

        // Consultar visitas del día (is_opportunity = 0 con foto)
        $visitasQuery = DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->whereDate('v.visited_at', $fecha)
            ->where('v.is_opportunity', 0)
            ->whereNotNull('v.photo_path')
            ->where('v.photo_path', '!=', '')
            ->select([
                'v.id',
                'v.client_id',
                'v.route',
                'v.status',
                'v.comments',
                'v.latitude as visita_lat',
                'v.longitude as visita_lng',
                'v.accuracy',
                'v.photo_path',
                'v.visited_at',
                'u.username as vendedor',
            ])
            ->orderBy('v.visited_at', 'asc');

        if (!empty($rutasFiltradas)) {
            $visitasQuery->whereIn('v.route', $rutasFiltradas);
        }

        $visitasRaw = $visitasQuery->get();

        if ($visitasRaw->isEmpty()) {
            return response()->json([
                'rutas_trayectorias' => [],
                'kpis' => [
                    'total_fotos' => 0,
                    'rutas_activas' => 0,
                    'en_rango' => 0,
                    'desviadas' => 0,
                ],
            ]);
        }

        // Extraer datos oficiales del cliente desde supervisor.pan_ruteo
        $clientIds = $visitasRaw->pluck('client_id')->unique()->filter()->toArray();

        $clientesOficiales = DB::connection('supervisor')->table('pan_ruteo as pr')
            ->leftJoin('dim_rutas as dr', 'pr.ruta', '=', 'dr.ruta')
            ->whereIn('pr.cliente_id', $clientIds)
            ->select([
                'pr.cliente_id',
                DB::raw("COALESCE(NULLIF(MAX(pr.cliente), ''), MAX(pr.cliente_norm), 'Cliente #' || pr.cliente_id) as cliente"),
                DB::raw("MAX(pr.direccion) as direccion"),
                DB::raw("MAX(pr.referencia) as referencia"),
                DB::raw("COALESCE(MAX(NULLIF(pr.celular, '')), MAX(pr.telefono)) as telefono"),
                DB::raw("MAX(pr.tipo_negocio) as tipo_negocio"),
                DB::raw("MAX(pr.latitud) as oficial_lat"),
                DB::raw("MAX(pr.longitud) as oficial_lng"),
                DB::raw("COALESCE(MAX(dr.canal), 'PRT') as canal"),
            ])
            ->groupBy('pr.cliente_id')
            ->get()
            ->keyBy('cliente_id');

        $rutasAgrupadas = [];
        $totalEnRango = 0;
        $totalDesviadas = 0;

        foreach ($visitasRaw->groupBy('route') as $ruta => $visitasDeRuta) {
            $puntos = [];
            $orden = 1;
            $visitaAnteriorAt = null;

            foreach ($visitasDeRuta as $v) {
                $oficial = $clientesOficiales->get($v->client_id);
                $visitaLat = (float) $v->visita_lat;
                $visitaLng = (float) $v->visita_lng;

                $oficialLat = $oficial ? (float) $oficial->oficial_lat : null;
                $oficialLng = $oficial ? (float) $oficial->oficial_lng : null;

                $tieneGpsOficial = ($oficialLat && $oficialLng && $oficialLat != 0.0 && $oficialLng != 0.0);

                // Cálculo de distancia entre foto y cliente oficial
                $distanciaOficialMetros = null;
                $enRango = null;

                if ($tieneGpsOficial) {
                    $distanciaOficialMetros = round($this->haversine($visitaLat, $visitaLng, $oficialLat, $oficialLng), 1);
                    $enRango = $distanciaOficialMetros <= 50.0;
                    if ($enRango) {
                        $totalEnRango++;
                    } else {
                        $totalDesviadas++;
                    }
                }

                // Cálculo de tiempo transcurrido desde la visita anterior
                $deltaMinutos = null;
                if ($visitaAnteriorAt) {
                    $deltaMinutos = round((strtotime($v->visited_at) - strtotime($visitaAnteriorAt)) / 60);
                }
                $visitaAnteriorAt = $v->visited_at;

                $puntos[] = [
                    'id' => $v->id,
                    'orden' => $orden++,
                    'cliente_id' => $v->client_id,
                    'cliente' => $oficial ? $oficial->cliente : 'Cliente #' . $v->client_id,
                    'direccion' => $oficial ? $oficial->direccion : null,
                    'referencia' => $oficial ? $oficial->referencia : null,
                    'telefono' => $oficial ? $oficial->telefono : null,
                    'tipo_negocio' => $oficial ? $oficial->tipo_negocio : null,
                    'canal' => $oficial ? $oficial->canal : 'PRT',
                    'ruta' => $v->route,
                    'vendedor' => $v->vendedor ?: 'Sin asignar',
                    'status' => $v->status,
                    'comments' => $v->comments,
                    'hora' => date('H:i', strtotime($v->visited_at)),
                    'fecha_hora' => $v->visited_at,
                    'delta_minutos' => $deltaMinutos,
                    'photo_url' => Storage::url($v->photo_path),
                    'visita_lat' => $visitaLat,
                    'visita_lng' => $visitaLng,
                    'oficial_lat' => $tieneGpsOficial ? $oficialLat : null,
                    'oficial_lng' => $tieneGpsOficial ? $oficialLng : null,
                    'distancia_oficial_metros' => $distanciaOficialMetros,
                    'en_rango' => $enRango,
                ];
            }

            $rutasAgrupadas[] = [
                'ruta' => $ruta,
                'vendedor' => $visitasDeRuta->first()->vendedor ?: 'Sin asignar',
                'canal' => $puntos[0]['canal'] ?? 'PRT',
                'total_visitas' => count($puntos),
                'puntos' => $puntos,
            ];
        }

        return response()->json([
            'rutas_trayectorias' => $rutasAgrupadas,
            'kpis' => [
                'total_fotos' => $visitasRaw->count(),
                'rutas_activas' => count($rutasAgrupadas),
                'en_rango' => $totalEnRango,
                'desviadas' => $totalDesviadas,
            ],
        ]);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }


    public function fotosCliente(int $clienteId): JsonResponse
    {
        $visitas = DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->where('v.client_id', $clienteId)
            ->whereNotNull('v.photo_path')
            ->where('v.photo_path', '!=', '')
            ->select([
                'v.id',
                'v.route',
                'v.status',
                'v.comments',
                'v.visited_at',
                'v.photo_path',
                'u.username as vendedor',
            ])
            ->orderByDesc('v.visited_at')
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'ruta' => $v->route,
                    'status' => $v->status,
                    'comments' => $v->comments,
                    'fecha' => date('d/m/Y', strtotime($v->visited_at)),
                    'hora' => date('H:i', strtotime($v->visited_at)),
                    'vendedor' => $v->vendedor ?: 'Sin asignar',
                    'photo_url' => Storage::url($v->photo_path),
                ];
            });

        return response()->json([
            'visitas' => $visitas,
        ]);
    }
}