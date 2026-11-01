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
                'sb.updated_at',
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

        // 3. Mapear usuarios creadores desde users
        $userIds = $registrosRaw->pluck('user_id')->filter()->unique()->toArray();
        $userMap = [];
        if (!empty($userIds)) {
            try {
                $userMap = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('username', 'id')
                    ->toArray();
            } catch (\Throwable $e) {
                // Silencioso ante contingencia
            }
        }

        // 4. Obtener clientes originales para cotejo de ediciones
        $clienteIds = $registrosRaw->whereNotNull('cliente_id')->pluck('cliente_id')->unique()->toArray();
        $clientesOriginales = [];

        if (!empty($clienteIds)) {
            $panRows = DB::connection('supervisor')->table('pan_ruteo')
                ->whereIn('cliente_id', $clienteIds)
                ->get();

            // Indexar por "cliente_id_ruta" y fallback por "cliente_id"
            foreach ($panRows as $pr) {
                $cid = (int) $pr->cliente_id;
                $rutaClave = strtoupper(trim((string) ($pr->ruta ?? '')));
                $clientesOriginales["{$cid}_{$rutaClave}"] = $pr;
                if (!isset($clientesOriginales[(string) $cid])) {
                    $clientesOriginales[(string) $cid] = $pr;
                }
            }
        }

        // 5. Mapear datos con cálculo de desplazamiento si hubo cambio de coordenadas
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

            // Datos originales si es edición (priorizando coincidencia por ruta)
            $cidStr = (string) $r->cliente_id;
            $rutaClave = strtoupper(trim((string) ($r->ruta ?? '')));
            $orig = $r->cliente_id
                ? ($clientesOriginales["{$cidStr}_{$rutaClave}"] ?? $clientesOriginales[$cidStr] ?? null)
                : null;

            $latOrig = ($orig && isset($orig->latitud) && is_numeric($orig->latitud)) ? (float) $orig->latitud : null;
            $lngOrig = ($orig && isset($orig->longitud) && is_numeric($orig->longitud)) ? (float) $orig->longitud : null;
            $tieneGpsOrig = GeoSpatialService::isValidCoordinates($latOrig, $lngOrig);

            $distanciaMetros = null;
            if ($tieneGps && $tieneGpsOrig) {
                $distanciaMetros = round(GeoSpatialService::haversine($lat, $lng, (float) $latOrig, (float) $lngOrig), 1);
            }

            $usuarioNombre = null;
            if ($r->user_id) {
                $usuarioNombre = $userMap[$r->user_id] ?? ("Usuario #{$r->user_id}");
            }

            $registros[] = [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'usuario' => $usuarioNombre,
                'cliente_id' => $r->cliente_id,
                'tipo_registro' => $r->tipo_registro,
                'ruta' => $r->ruta,
                'canal' => $r->canal,
                'vendedor' => $r->vendedor,
                'cliente' => $r->cliente,
                'tipo_negocio' => $r->tipo_negocio,
                'zona' => $r->zona,
                'zona_comercial' => $r->zona_comercial ?? $r->zona,
                'lista_precios' => $r->lista_precios,
                'contacto' => $r->contacto,
                'telefono' => $r->telefono,
                'celular' => $r->celular,
                'direccion' => $r->direccion,
                'referencia' => $r->referencia,
                'nombre_factura' => $r->nombre_factura,
                'nit' => $r->nit,
                'latitud' => $tieneGps ? $lat : null,
                'longitud' => $tieneGps ? $lng : null,
                'accuracy' => $r->accuracy ? round((float) $r->accuracy, 1) : null,
                'photo_path' => $r->photo_path,
                'photo_url' => $r->photo_path ? Storage::url($r->photo_path) : null,
                'estado_revision' => $r->estado_revision,
                'revisado_at' => $r->revisado_at ? date('d/m/Y H:i', strtotime($r->revisado_at)) : null,
                'fecha_registro' => date('d/m/Y H:i', strtotime($r->created_at)),
                'fecha_actualizacion' => $r->updated_at ? date('d/m/Y H:i', strtotime($r->updated_at)) : null,
                'created_at_raw' => $r->created_at,
                'updated_at_raw' => $r->updated_at,
                'tiene_gps' => $tieneGps,
                // Datos de la base original (para ediciones)
                'es_edicion' => !empty($orig),
                'original' => $orig ? [
                    'cliente_id' => $orig->cliente_id ?? $r->cliente_id,
                    'nodo_id' => $orig->nodo_id ?? null,
                    'nodo' => $orig->nodo ?? null,
                    'cliente' => !empty($orig->cliente) ? $orig->cliente : ($orig->cliente_norm ?? null),
                    'tipo_negocio' => $orig->tipo_negocio ?? null,
                    'vendedor' => $orig->vendedor ?? null,
                    'cod_vendedor' => $orig->cod_vendedor ?? null,
                    'nombre_factura' => $orig->nombre_factura ?? null,
                    'nit' => $orig->nit ?? null,
                    'contacto' => $orig->contacto ?? null,
                    'ci' => $orig->ci ?? null,
                    'telefono' => $orig->telefono ?? null,
                    'celular' => $orig->celular ?? null,
                    'direccion' => $orig->direccion ?? null,
                    'nro' => $orig->nro ?? null,
                    'referencia' => $orig->referencia ?? null,
                    'territorio' => $orig->territorio ?? null,
                    'zona_venta' => $orig->zona_venta ?? null,
                    'zona' => $orig->territorio ?: ($orig->zona_venta ?? null),
                    'mercado' => $orig->mercado ?? null,
                    'lista_precios' => $orig->precio ?? null,
                    'precio' => $orig->precio ?? null,
                    'cluster_t' => $orig->cluster_t ?? null,
                    'cluster_c' => $orig->cluster_c ?? null,
                    'ruta' => $orig->ruta ?? null,
                    'dia' => $orig->dia ?? null,
                    'dia_norm' => $orig->dia_norm ?? null,
                    'secuencia_venta' => $orig->secuencia_venta ?? null,
                    'secuencia_distribuidor' => $orig->secuencia_distribuidor ?? null,
                    'tipo_pago' => $orig->tipo_pago ?? null,
                    'dias_credito' => $orig->dias_credito ?? null,
                    'limite' => $orig->limite ?? null,
                    'latitud' => $tieneGpsOrig ? $latOrig : null,
                    'longitud' => $tieneGpsOrig ? $lngOrig : null,
                    'intensidad' => $orig->intensidad ?? null,
                    'estado' => $orig->estado ?? 'Activo',
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

