<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use App\Services\GeoSpatialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetVersionesClienteAction
{
    /**
     * Consulta todas las versiones históricas y solicitudes registradas en saneamiento_base
     * para un cliente específico, enriquecidas con datos originales y trazabilidad.
     *
     * @param int $clienteId
     * @return array{
     *     cliente_id: int,
     *     original: array<string, mixed>|null,
     *     total_versiones: int,
     *     versiones: array<int, array<string, mixed>>
     * }
     */
    public function execute(int $clienteId): array
    {
        $conexion = DB::connection('supervisor');

        // 1. Obtener todas las solicitudes del cliente en saneamiento_base
        $solicitudes = $conexion->table('saneamiento_base as sb')
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
                'sb.apellido_paterno',
                'sb.apellido_materno',
                'sb.nombres',
                'sb.documento_identidad',
                'sb.expedido',
                'sb.complemento',
                'sb.tipo_documento',
                'sb.tipo_nit',
                'sb.razon_social',
                'sb.tipo_negocio',
                'sb.zona',
                DB::raw("sb.zona as zona_comercial"),
                'sb.lista_precios',
                'sb.contacto',
                'sb.telefono',
                'sb.celular',
                'sb.direccion',
                'sb.referencia',
                'sb.direccion_visita',
                'sb.referencia_visita',
                'sb.nombre_factura',
                'sb.nit',
                'sb.latitude as latitud',
                'sb.longitude as longitud',
                'sb.latitud_visita',
                'sb.longitud_visita',
                'sb.accuracy',
                'sb.photo_path',
                'sb.estado_revision',
                'sb.revisado_at',
                'sb.revisor_id',
                'sb.created_at',
                'sb.updated_at',
            ])
            ->where('sb.cliente_id', $clienteId)
            ->orderBy('sb.id', 'desc')
            ->get();

        // 2. Obtener datos originales del padrón (pan_ruteo)
        $orig = $conexion->table('pan_ruteo')
            ->where('cliente_id', $clienteId)
            ->first();

        $originalData = null;
        $latOrig = null;
        $lngOrig = null;
        $tieneGpsOrig = false;

        if ($orig) {
            $latOrig = ($orig->latitud !== null && is_numeric($orig->latitud)) ? (float) $orig->latitud : null;
            $lngOrig = ($orig->longitud !== null && is_numeric($orig->longitud)) ? (float) $orig->longitud : null;
            $tieneGpsOrig = GeoSpatialService::isValidCoordinates($latOrig, $lngOrig);

            $originalData = [
                'cliente_id' => $orig->cliente_id,
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
            ];
        }

        // 3. Mapear usuarios creadores y revisores
        $userIds = $solicitudes->pluck('user_id')
            ->concat($solicitudes->pluck('revisor_id'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $userMap = [];
        if (!empty($userIds)) {
            try {
                $userMap = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('username', 'id')
                    ->toArray();
            } catch (\Throwable $e) {
                // Silencioso
            }
        }

        $versiones = [];
        foreach ($solicitudes as $r) {
            $lat = (float) $r->latitud;
            $lng = (float) $r->longitud;
            $tieneGps = GeoSpatialService::isValidCoordinates($lat, $lng);

            $distanciaMetros = null;
            if ($tieneGps && $tieneGpsOrig) {
                $distanciaMetros = round(GeoSpatialService::haversine($lat, $lng, (float) $latOrig, (float) $lngOrig), 1);
            }

            $usuarioNombre = null;
            if ($r->user_id) {
                $usuarioNombre = $userMap[$r->user_id] ?? ("Usuario #{$r->user_id}");
            }

            $revisorNombre = null;
            if (!empty($r->revisor_id)) {
                $revisorNombre = $userMap[$r->revisor_id] ?? ("Usuario #{$r->revisor_id}");
            }

            $versiones[] = [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'usuario' => $usuarioNombre,
                'revisor_id' => $r->revisor_id,
                'revisor_nombre' => $revisorNombre,
                'cliente_id' => $r->cliente_id,
                'tipo_registro' => $r->tipo_registro,
                'ruta' => $r->ruta,
                'canal' => $r->canal,
                'vendedor' => $r->vendedor,
                'cliente' => $r->cliente,
                'apellido_paterno' => $r->apellido_paterno,
                'apellido_materno' => $r->apellido_materno,
                'nombres' => $r->nombres,
                'documento_identidad' => $r->documento_identidad,
                'expedido' => $r->expedido,
                'complemento' => $r->complemento,
                'tipo_documento' => $r->tipo_documento,
                'tipo_nit' => $r->tipo_nit,
                'razon_social' => $r->razon_social,
                'tipo_negocio' => $r->tipo_negocio,
                'zona' => $r->zona,
                'zona_comercial' => $r->zona_comercial ?? $r->zona,
                'lista_precios' => $r->lista_precios,
                'contacto' => $r->contacto,
                'telefono' => $r->telefono,
                'celular' => $r->celular,
                'direccion' => $r->direccion,
                'referencia' => $r->referencia,
                'direccion_visita' => $r->direccion_visita,
                'referencia_visita' => $r->referencia_visita,
                'nombre_factura' => $r->nombre_factura,
                'nit' => $r->nit,
                'latitud' => $tieneGps ? $lat : null,
                'longitud' => $tieneGps ? $lng : null,
                'latitud_visita' => $r->latitud_visita !== null ? (float) $r->latitud_visita : null,
                'longitud_visita' => $r->longitud_visita !== null ? (float) $r->longitud_visita : null,
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
                'distancia_desplazamiento_m' => $distanciaMetros,
            ];
        }

        return [
            'cliente_id' => $clienteId,
            'original' => $originalData,
            'total_versiones' => count($versiones),
            'versiones' => $versiones,
        ];
    }
}
