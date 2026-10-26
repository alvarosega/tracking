<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use App\Services\GeoSpatialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetAltasEdicionesDataAction
{
    /**
     * Consulta y procesa los registros de saneamiento_base (altas y ediciones),
     * enriqueciéndolos con canales, datos de vendedor, trazabilidad de cambios
     * y cálculo de desplazamiento GPS mediante Haversine.
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaFin
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param array<int, string> $tipos
     * @param array<int, string> $estados
     * @return array{
     *     registros: array<int, array<string, mixed>>,
     *     kpis: array{
     *         total: int,
     *         altas: int,
     *         ediciones: int,
     *         pendientes: int,
     *         aprobados: int
     *     }
     * }
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        array $canales = [],
        array $rutas = [],
        array $tipos = [],
        array $estados = []
    ): array {
        // 1. Filtrar rutas por canal si corresponde
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
                DB::raw("sb.zona as zona_comercial"),
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
            return [
                'registros' => [],
                'kpis' => [
                    'total' => 0,
                    'altas' => 0,
                    'ediciones' => 0,
                    'pendientes' => 0,
                    'aprobados' => 0,
                ],
            ];
        }

        // 3. Obtener clientes originales para cotejo de ediciones
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
            $tipoUpper = strtoupper((string) $r->tipo_registro);
            if (str_contains($tipoUpper, 'ALTA')) {
                $kpiAltas++;
            } elseif (str_contains($tipoUpper, 'EDIC')) {
                $kpiEdiciones++;
            }

            $estadoUpper = strtoupper((string) $r->estado_revision);
            if ($estadoUpper === 'PENDIENTE') {
                $kpiPendientes++;
            } elseif ($estadoUpper === 'APROBADO') {
                $kpiAprobados++;
            }

            $lat = (float) $r->latitud;
            $lng = (float) $r->longitud;
            $tieneGps = GeoSpatialService::isValidCoordinates($lat, $lng);

            // Datos originales si es edición
            $orig = ($r->cliente_id && isset($clientesOriginales[$r->cliente_id]))
                ? $clientesOriginales[$r->cliente_id]
                : null;

            $latOrig = $orig ? (float) $orig->latitud_orig : null;
            $lngOrig = $orig ? (float) $orig->longitud_orig : null;
            $tieneGpsOrig = GeoSpatialService::isValidCoordinates($latOrig, $lngOrig);

            $distanciaMetros = null;
            if ($tieneGps && $tieneGpsOrig) {
                $distanciaMetros = round(GeoSpatialService::haversine($lat, $lng, (float) $latOrig, (float) $lngOrig), 1);
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

        return [
            'registros' => $registros,
            'kpis' => [
                'total' => count($registros),
                'altas' => $kpiAltas,
                'ediciones' => $kpiEdiciones,
                'pendientes' => $kpiPendientes,
                'aprobados' => $kpiAprobados,
            ],
        ];
    }
}

