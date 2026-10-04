<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorAltasEdicionesController extends Controller
{
    public function index(): Response
    {
        // 1. Catálogo de Rutas y Canales desde supervisor.dim_rutas
        $catalogo = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        // 2. Extraer dinámicamente los tipos de registro existentes en la tabla
        $tiposRegistro = DB::connection('supervisor')->table('saneamiento_base')
            ->select('tipo_registro')
            ->distinct()
            ->whereNotNull('tipo_registro')
            ->where('tipo_registro', '!=', '')
            ->pluck('tipo_registro')
            ->values()
            ->toArray();

        // 3. Extraer dinámicamente los estados de revisión existentes en la tabla
        $estadosRevision = DB::connection('supervisor')->table('saneamiento_base')
            ->select('estado_revision')
            ->distinct()
            ->whereNotNull('estado_revision')
            ->where('estado_revision', '!=', '')
            ->pluck('estado_revision')
            ->values()
            ->toArray();

        return Inertia::render('Supervisor/AltasEdiciones/Index', [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'tipos_registro' => $tiposRegistro,
            'estados_revision' => $estadosRevision,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $canales = $request->input('canales', []);
        $rutas = $request->input('rutas', []);
        $tipos = $request->input('tipos', []);
        $estados = $request->input('estados', []);

        // 1. Rutas válidas por Canal
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

        // 2. Consulta en supervisor.saneamiento_base
        $query = DB::connection('supervisor')->table('saneamiento_base as sb')
            ->leftJoin('dim_rutas as dr', 'sb.route', '=', 'dr.ruta')
            ->select([
                'sb.id',
                'sb.user_id',
                'sb.cliente_id',
                'sb.tipo_registro',
                'sb.route as ruta',
                DB::raw("COALESCE(dr.canal, 'PRT') as canal"),
                DB::raw("COALESCE(dr.vendedor, 'Sin asignar') as vendedor"),
                'sb.cliente',
                'sb.tipo_negocio',
                'sb.zona',
                'sb.lista_precios',
                'sb.contacto',
                'sb.telefono',
                'sb.celular',
                'sb.direccion',
                'sb.referencia',
                'sb.nombre_factura',
                'sb.nit',
                'sb.latitude as latitud',
                'sb.longitude as longitud',
                'sb.accuracy',
                'sb.photo_path',
                'sb.estado_revision',
                'sb.revisado_at',
                'sb.created_at',
            ])
            ->orderBy('sb.created_at', 'desc');

        if (!empty($rutasFiltradas)) {
            $query->whereIn('sb.route', $rutasFiltradas);
        }

        if (!empty($tipos)) {
            $query->whereIn('sb.tipo_registro', $tipos);
        }

        if (!empty($estados)) {
            $query->whereIn('sb.estado_revision', $estados);
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween(DB::raw('DATE(sb.created_at)'), [$fechaInicio, $fechaFin]);
        }

        $registrosRaw = $query->get();

        if ($registrosRaw->isEmpty()) {
            return response()->json([
                'registros' => [],
                'kpis' => [
                    'total' => 0,
                    'altas' => 0,
                    'ediciones' => 0,
                    'pendientes' => 0,
                    'aprobados' => 0,
                ],
            ]);
        }

        // 3. Si hay ediciones con cliente_id, buscar datos originales en supervisor.pan_ruteo para comparar
        $clienteIds = $registrosRaw->whereNotNull('cliente_id')->pluck('cliente_id')->unique()->toArray();
        $clientesOriginales = [];

        if (!empty($clienteIds)) {
            $clientesOriginales = DB::connection('supervisor')->table('pan_ruteo')
                ->whereIn('cliente_id', $clienteIds)
                ->select([
                    'cliente_id',
                    DB::raw("COALESCE(NULLIF(cliente, ''), cliente_norm) as cliente_orig"),
                    'tipo_negocio as tipo_negocio_orig',
                    'direccion as direccion_orig',
                    'referencia as referencia_orig',
                    'telefono as telefono_orig',
                    'celular as celular_orig',
                    'contacto as contacto_orig',
                    'nit as nit_orig',
                    'latitud as latitud_orig',
                    'longitud as longitud_orig',
                ])
                ->get()
                ->keyBy('cliente_id');
        }

        // 4. Mapear datos con cálculo de desplazamiento si hubo cambio de coordenadas
        $registros = [];
        $kpiAltas = 0;
        $kpiEdiciones = 0;
        $kpiPendientes = 0;
        $kpiAprobados = 0;

        foreach ($registrosRaw as $r) {
            $tipoUpper = strtoupper($r->tipo_registro);
            if (str_contains($tipoUpper, 'ALTA')) {
                $kpiAltas++;
            } elseif (str_contains($tipoUpper, 'EDIC')) {
                $kpiEdiciones++;
            }

            if (strtoupper($r->estado_revision) === 'PENDIENTE') {
                $kpiPendientes++;
            } elseif (strtoupper($r->estado_revision) === 'APROBADO') {
                $kpiAprobados++;
            }

            $lat = (float) $r->latitud;
            $lng = (float) $r->longitud;
            $tieneGps = ($lat != 0.0 && $lng != 0.0 && $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0);

            // Datos originales si es edición
            $orig = ($r->cliente_id && isset($clientesOriginales[$r->cliente_id]))
                ? $clientesOriginales[$r->cliente_id]
                : null;

            $latOrig = $orig ? (float) $orig->latitud_orig : null;
            $lngOrig = $orig ? (float) $orig->longitud_orig : null;
            $tieneGpsOrig = ($latOrig && $lngOrig && $latOrig != 0.0 && $lngOrig != 0.0);

            $distanciaMetros = null;
            if ($tieneGps && $tieneGpsOrig) {
                $distanciaMetros = round($this->haversine($lat, $lng, $latOrig, $lngOrig), 1);
            }

            $registros[] = [
                'id' => $r->id,
                'cliente_id' => $r->cliente_id,
                'tipo_registro' => $r->tipo_registro,
                'ruta' => $r->ruta,
                'canal' => $r->canal,
                'vendedor' => $r->vendedor,
                'cliente' => $r->cliente,
                'tipo_negocio' => $r->tipo_negocio,
                'zona' => $r->zona,
                'lista_precios' => $r->lista_precios,
                'contacto' => $r->contacto,
                'telefono' => $r->telefono ?: $r->celular,
                'direccion' => $r->direccion,
                'referencia' => $r->referencia,
                'nombre_factura' => $r->nombre_factura,
                'nit' => $r->nit,
                'latitud' => $tieneGps ? $lat : null,
                'longitud' => $tieneGps ? $lng : null,
                'accuracy' => $r->accuracy ? round((float) $r->accuracy, 1) : null,
                'photo_url' => $r->photo_path ? Storage::url($r->photo_path) : null,
                'estado_revision' => $r->estado_revision,
                'revisado_at' => $r->revisado_at ? date('d/m/Y H:i', strtotime($r->revisado_at)) : null,
                'fecha_registro' => date('d/m/Y H:i', strtotime($r->created_at)),
                'tiene_gps' => $tieneGps,
                // Datos de la base original (para ediciones)
                'es_edicion' => !empty($orig),
                'original' => $orig ? [
                    'cliente' => $orig->cliente_orig,
                    'tipo_negocio' => $orig->tipo_negocio_orig,
                    'direccion' => $orig->direccion_orig,
                    'referencia' => $orig->referencia_orig,
                    'telefono' => $orig->telefono_orig ?: $orig->celular_orig,
                    'contacto' => $orig->contacto_orig,
                    'nit' => $orig->nit_orig,
                    'latitud' => $tieneGpsOrig ? $latOrig : null,
                    'longitud' => $tieneGpsOrig ? $lngOrig : null,
                ] : null,
                'distancia_desplazamiento_m' => $distanciaMetros,
            ];
        }

        return response()->json([
            'registros' => $registros,
            'kpis' => [
                'total' => count($registros),
                'altas' => $kpiAltas,
                'ediciones' => $kpiEdiciones,
                'pendientes' => $kpiPendientes,
                'aprobados' => $kpiAprobados,
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
}