<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportarPadronActualizadoAction
{
    /**
     * Retorna la matriz de datos del padrón de ruteo para las rutas filtradas en la barra superior,
     * reemplazando los campos que hayan sido corregidos en saneamiento_base y manteniendo los de pan_ruteo.
     * Formato oficial de 36 columnas:
     * IdCliente, Codigo, ApellidoPaterno, ApellidoMaterno, Nombres, TipoNegocio, TipoPago, DiasPago,
     * TopeCredito, Latitud, Longitud, Activo, Direccion, UV, Referencia, Zona, Provincia, Mercado,
     * DocumentoIdentidad, Expedido, Telefono, Celular, Email, PaginaWeb, RazonSocial, Cluster,
     * TipoDocumento, TipoNit, NombreFactura, Nit, Complemento, PrecioLista, Ruta, Dia, SecuenciaVenta,
     * SecuenciaDistribuidor.
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaFin
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param array<int, string> $tipos
     * @param array<int, string> $estados
     * @return array{success: bool, total: int, rows: array<int, array<int, mixed>>, message?: string}
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        array $canales = [],
        array $rutas = [],
        array $tipos = [],
        array $estados = []
    ): array {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
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

            // Si no se pasaron rutas específicas ni canales, obtener todas las rutas del catálogo
            if (empty($rutasFiltradas)) {
                $rutasFiltradas = DB::connection('supervisor')->table('dim_rutas')
                    ->pluck('ruta')
                    ->toArray();
            }

            // 2. Consultar solicitudes de saneamiento_base según los filtros activos
            $querySb = DB::connection('supervisor')->table('saneamiento_base as sb')
                ->select([
                    'sb.id',
                    'sb.cliente_id',
                    'sb.tipo_registro',
                    'sb.route',
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
                    'sb.latitude',
                    'sb.longitude',
                    'sb.estado_revision',
                    'sb.created_at',
                ])
                ->whereNotNull('sb.cliente_id')
                ->orderBy('sb.created_at', 'asc'); // La última sobreescribe si hay más de una

            if (!empty($rutasFiltradas)) {
                $querySb->whereIn('sb.route', $rutasFiltradas);
            }

            // Normalización de tipos (EDICION / MODIFICACION)
            if (!empty($tipos)) {
                $tiposNorm = [];
                foreach ($tipos as $t) {
                    $tUp = strtoupper(trim((string) $t));
                    if (str_contains($tUp, 'EDIC') || str_contains($tUp, 'MODIF')) {
                        $tiposNorm[] = 'EDICION';
                        $tiposNorm[] = 'MODIFICACION';
                        $tiposNorm[] = 'EDICIÓN';
                        $tiposNorm[] = 'MODIFICACIÓN';
                    } elseif (str_contains($tUp, 'ALTA')) {
                        $tiposNorm[] = 'ALTA';
                        $tiposNorm[] = 'NUEVA_ALTA';
                    } else {
                        $tiposNorm[] = $t;
                    }
                }
                $tiposNorm = array_values(array_unique($tiposNorm));
                $querySb->where(function ($q) use ($tiposNorm) {
                    $q->whereIn('sb.tipo_registro', $tiposNorm)
                      ->orWhereIn(DB::raw('UPPER(TRIM(sb.tipo_registro))'), $tiposNorm);
                });
            }

            if (!empty($estados)) {
                $querySb->whereIn('sb.estado_revision', $estados);
            }

            if ($fechaInicio && $fechaFin) {
                $querySb->whereBetween(DB::raw('DATE(sb.created_at)'), [$fechaInicio, $fechaFin]);
            }

            $correccionesSb = $querySb->get();

            // Mapa de correcciones indexado por "cliente_id_ruta" y fallback por "cliente_id"
            $correccionesMap = [];
            foreach ($correccionesSb as $c) {
                $cid = (int) $c->cliente_id;
                $rKey = strtoupper(trim((string) $c->route));
                $correccionesMap["{$cid}_{$rKey}"] = $c;
                $correccionesMap[(string) $cid] = $c;
            }

            // 3. Consultar la totalidad de clientes correspondientes a las rutas filtradas en pan_ruteo
            $queryPan = DB::connection('supervisor')->table('pan_ruteo as pr');

            if (!empty($rutasFiltradas)) {
                $queryPan->whereIn('pr.ruta', $rutasFiltradas);
            }

            $clientesPan = $queryPan->orderBy('pr.ruta')->get();

            if ($clientesPan->isEmpty()) {
                return [
                    'success' => false,
                    'total' => 0,
                    'rows' => [],
                    'message' => 'No se encontraron clientes en pan_ruteo para las rutas seleccionadas.',
                ];
            }

            // 4. Encabezados exactos en formato de 36 columnas
            $headers = [
                'IdCliente',
                'Codigo',
                'ApellidoPaterno',
                'ApellidoMaterno',
                'Nombres',
                'TipoNegocio',
                'TipoPago',
                'DiasPago',
                'TopeCredito',
                'Latitud',
                'Longitud',
                'Activo',
                'Direccion',
                'UV',
                'Referencia',
                'Zona',
                'Provincia',
                'Mercado',
                'DocumentoIdentidad',
                'Expedido',
                'Telefono',
                'Celular',
                'Email',
                'PaginaWeb',
                'RazonSocial',
                'Cluster',
                'TipoDocumento',
                'TipoNit',
                'NombreFactura',
                'Nit',
                'Complemento',
                'PrecioLista',
                'Ruta',
                'Dia',
                'SecuenciaVenta',
                'SecuenciaDistribuidor',
            ];

            $rows = [$headers];

            foreach ($clientesPan as $pr) {
                $cid = (int) ($pr->cliente_id ?? 0);
                $rKey = strtoupper(trim((string) ($pr->ruta ?? '')));
                $corr = $correccionesMap["{$cid}_{$rKey}"] ?? ($correccionesMap[(string) $cid] ?? null);

                // Nombres (los corregidos se reemplazan, los no corregidos se mantienen de pan_ruteo)
                $nombres = ($corr && !empty(trim((string) ($corr->cliente ?? ''))))
                    ? trim((string) $corr->cliente)
                    : (!empty($pr->cliente) ? $pr->cliente : ($pr->cliente_norm ?? ''));

                // Tipo de Negocio
                $tipoNegocio = ($corr && !empty(trim((string) ($corr->tipo_negocio ?? ''))))
                    ? trim((string) $corr->tipo_negocio)
                    : ($pr->tipo_negocio ?? '');

                // Dirección
                $direccion = ($corr && !empty(trim((string) ($corr->direccion ?? ''))))
                    ? trim((string) $corr->direccion)
                    : ($pr->direccion ?? '');

                // Referencia
                $referencia = ($corr && !empty(trim((string) ($corr->referencia ?? ''))))
                    ? trim((string) $corr->referencia)
                    : ($pr->referencia ?? '');

                // Zona
                $zona = ($corr && !empty(trim((string) ($corr->zona ?? ''))))
                    ? trim((string) $corr->zona)
                    : ($pr->territorio ?: ($pr->zona_venta ?? ''));

                // Precio de Lista
                $precioLista = ($corr && !empty(trim((string) ($corr->lista_precios ?? ''))))
                    ? trim((string) $corr->lista_precios)
                    : ($pr->precio ?? '');

                // Ruta
                $rutaFinal = ($corr && !empty(trim((string) ($corr->route ?? ''))))
                    ? trim((string) $corr->route)
                    : ($pr->ruta ?? '');

                // Nombre Factura
                $nombreFactura = ($corr && !empty(trim((string) ($corr->nombre_factura ?? ''))))
                    ? trim((string) $corr->nombre_factura)
                    : ($pr->nombre_factura ?? '');

                // NIT
                $nit = ($corr && !empty(trim((string) ($corr->nit ?? ''))))
                    ? trim((string) $corr->nit)
                    : ($pr->nit ?? '');

                // Teléfono
                $telefono = ($corr && !empty(trim((string) ($corr->telefono ?? ''))))
                    ? trim((string) $corr->telefono)
                    : ($pr->telefono ?? '');

                // Celular
                $celular = ($corr && !empty(trim((string) ($corr->celular ?? ''))))
                    ? trim((string) $corr->celular)
                    : ($pr->celular ?? '');

                // Coordenadas GPS
                $latFinal = $pr->latitud ?? '';
                $lngFinal = $pr->longitud ?? '';
                if ($corr && isset($corr->latitude, $corr->longitude) && is_numeric($corr->latitude) && is_numeric($corr->longitude)) {
                    $cLat = (float) $corr->latitude;
                    $cLng = (float) $corr->longitude;
                    if ($cLat != 0.0 && $cLng != 0.0 && $cLat >= -90 && $cLat <= 90 && $cLng >= -180 && $cLng <= 180) {
                        $latFinal = $cLat;
                        $lngFinal = $cLng;
                    }
                }

                $rows[] = [
                    $pr->cliente_id ?? '',
                    $pr->nodo_id ?: ($pr->cliente_id ?? ''),
                    '', // ApellidoPaterno (en blanco)
                    '', // ApellidoMaterno (en blanco)
                    $nombres,
                    $tipoNegocio,
                    $pr->tipo_pago ?? '',
                    $pr->dias_credito ?? '',
                    $pr->limite ?? '',
                    $latFinal,
                    $lngFinal,
                    $pr->estado ?? 'Activo',
                    $direccion,
                    '', // UV (en blanco)
                    $referencia,
                    $zona,
                    '', // Provincia (en blanco)
                    $pr->mercado ?? '',
                    $pr->ci ?? '',
                    '', // Expedido (en blanco)
                    $telefono,
                    $celular,
                    '', // Email (en blanco)
                    '', // PaginaWeb (en blanco)
                    '', // RazonSocial (en blanco)
                    $pr->cluster_t ?: ($pr->cluster_c ?? ''),
                    '', // TipoDocumento (en blanco)
                    '', // TipoNit (en blanco)
                    $nombreFactura,
                    $nit,
                    '', // Complemento (en blanco)
                    $precioLista,
                    $rutaFinal,
                    $pr->dia ?? '',
                    $pr->secuencia_venta ?? '',
                    $pr->secuencia_distribuidor ?? '',
                ];
            }

            return [
                'success' => true,
                'total' => count($rows) - 1,
                'rows' => $rows,
            ];
        } catch (\Throwable $e) {
            Log::error('Error en ExportarPadronActualizadoAction: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'total' => 0,
                'rows' => [],
                'message' => 'Error al generar el padrón actualizado: ' . $e->getMessage(),
            ];
        }
    }
}
