<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class AuditarDiasDesalineadosAction
{
    /**
     * Audita la distribución espacial de días de visita para una ruta.
     *
     * @param string $ruta
     * @param float $bufferMetros
     * @return array<string, mixed>
     */
    public function execute(string $ruta, float $bufferMetros = 35.0): array
    {
        $rutaClean = strtoupper(trim($ruta));
        $conn = DB::connection('supervisor');

        // 1. Clientes base de la ruta
        $clientesBaseRaw = $conn->table('pan_ruteo as p')
            ->where(function ($w) {
                $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                    ->orWhereNull('p.estado')
                    ->orWhereRaw("p.estado = ''");
            })
            ->whereNotNull('p.latitud')
            ->where('p.latitud', '!=', 0.0)
            ->whereNotNull('p.longitud')
            ->where('p.longitud', '!=', 0.0)
            ->where(DB::raw("TRIM(UPPER(p.ruta))"), $rutaClean)
            ->select([
                'p.cliente_id',
                'p.cliente',
                DB::raw("COALESCE(p.cliente_norm, '') as cliente_norm"),
                DB::raw("COALESCE(p.nombre_factura, '') as nombre_factura"),
                DB::raw("COALESCE(p.nit, '') as nit"),
                DB::raw("COALESCE(p.ci, '') as ci"),
                DB::raw("COALESCE(p.telefono, '') as telefono"),
                DB::raw("COALESCE(p.celular, '') as celular"),
                DB::raw("COALESCE(p.direccion, 'Sin Dirección') as direccion"),
                'p.latitud',
                'p.longitud',
                DB::raw("TRIM(UPPER(p.ruta)) as ruta"),
                DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') as dia_base"),
                DB::raw("COALESCE(TRIM(p.tipo_negocio), '-') as tipo_negocio"),
            ])
            ->orderBy('p.cliente_id')
            ->get();

        if ($clientesBaseRaw->isEmpty()) {
            return [
                'ruta' => $rutaClean,
                'total_clientes' => 0,
                'total_propuestas_pendientes' => 0,
                'total_multidia' => 0,
                'dias' => (object) [],
                'clientes_multidia' => [],
                'propuestas_pendientes' => [],
            ];
        }

        // 2. Propuestas pendientes de la ruta
        $listAction = new ListPropuestasAction();
        $propuestasPendientes = $listAction->execute(
            tipoHallazgo: 'DIA_DESALINEADO',
            rutas: [$rutaClean],
            estado: 'PENDIENTE'
        );

        $mapaPropuestas = [];
        foreach ($propuestasPendientes as $p) {
            $clave = $p['cliente_id_origen'] . '_' . strtoupper(trim((string) $p['dia_actual']));
            $mapaPropuestas[$clave] = $p;
        }

        // 3. Conteo de días por cliente_id para multi-día
        $diasPorCliente = [];
        foreach ($clientesBaseRaw as $c) {
            $cid = (int) $c->cliente_id;
            $diaB = strtoupper(trim((string) $c->dia_base));
            $clave = "{$cid}_{$diaB}";
            $diaEfectivo = isset($mapaPropuestas[$clave])
                ? strtoupper(trim((string) $mapaPropuestas[$clave]['dia_propuesto']))
                : $diaB;
            $diasPorCliente[$cid][] = $diaEfectivo;
        }

        // 4. Inyectar proyección en memoria
        $clientesProyectados = [];
        $agrupadoDias = [];
        $clientesMultidiaDict = [];

        foreach ($clientesBaseRaw as $c) {
            $cid = (int) $c->cliente_id;
            $diaBase = strtoupper(trim((string) $c->dia_base));
            $clave = "{$cid}_{$diaBase}";
            $diasAsignados = array_values(array_unique($diasPorCliente[$cid] ?? []));
            $esMultidia = count($diasAsignados) >= 2;

            $item = [
                'cliente_id' => $cid,
                'cliente' => trim((string) $c->cliente),
                'cliente_norm' => trim((string) $c->cliente_norm),
                'nombre_factura' => trim((string) $c->nombre_factura),
                'nit' => (string) $c->nit,
                'ci' => (string) $c->ci,
                'telefono' => (string) $c->telefono,
                'celular' => (string) $c->celular,
                'direccion' => (string) $c->direccion,
                'latitud' => (float) $c->latitud,
                'longitud' => (float) $c->longitud,
                'ruta' => (string) $c->ruta,
                'dia_original' => $diaBase,
                'tipo_negocio' => (string) $c->tipo_negocio,
                'es_multidia' => $esMultidia,
                'dias_asignados' => $diasAsignados,
            ];

            if (isset($mapaPropuestas[$clave])) {
                $prop = $mapaPropuestas[$clave];
                $diaEfectivo = strtoupper(trim((string) $prop['dia_propuesto']));
                $item['dia'] = $diaEfectivo;
                $item['tiene_propuesta'] = true;
                $item['propuesta_id'] = (int) $prop['id'];
            } else {
                $item['dia'] = $diaBase;
                $item['tiene_propuesta'] = false;
                $item['propuesta_id'] = null;
            }

            $clientesProyectados[] = $item;
            $agrupadoDias[$item['dia']][] = $item;

            if ($esMultidia && !isset($clientesMultidiaDict[$cid])) {
                $clientesMultidiaDict[$cid] = $item;
            }
        }

        // 5. Fronteras fijas de días en dim_fronteras_dias
        $fronterasGuardadas = $this->obtenerFronterasGuardadasDiasRuta($rutaClean);

        $resultadoDias = [];
        foreach ($agrupadoDias as $dia => $clientes) {
            $diaUpper = strtoupper($dia);
            if (isset($fronterasGuardadas[$diaUpper])) {
                $poligono = $fronterasGuardadas[$diaUpper]['poligono'];
                $origen = $fronterasGuardadas[$diaUpper]['origen'];
            } else {
                $coords = array_map(fn($cli) => [$cli['latitud'], $cli['longitud']], $clientes);
                $hull = GeometryHelper::convexHull($coords);
                $poligono = count($hull) >= 3 ? GeometryHelper::expandPolygonOffset($hull, $bufferMetros) : $hull;
                $origen = 'AUTOMATICO_PUNTOS';
            }

            $resultadoDias[$diaUpper] = [
                'total_pdvs' => count($clientes),
                'poligono' => $poligono,
                'origen_frontera' => $origen,
                'clientes' => $clientes,
            ];
        }

        return [
            'ruta' => $rutaClean,
            'total_clientes' => count($clientesProyectados),
            'total_propuestas_pendientes' => count($propuestasPendientes),
            'total_multidia' => count($clientesMultidiaDict),
            'clientes_multidia' => array_values($clientesMultidiaDict),
            'dias' => $resultadoDias,
            'propuestas_pendientes' => $propuestasPendientes,
        ];
    }

    protected function obtenerFronterasGuardadasDiasRuta(string $ruta): array
    {
        $conn = DB::connection('supervisor');

        $rows = $conn->table('dim_fronteras_dias')
            ->where(DB::raw("TRIM(UPPER(ruta))"), strtoupper(trim($ruta)))
            ->where('estado', 'ACTIVO')
            ->select([
                DB::raw("TRIM(UPPER(dia)) as dia"),
                'origen',
                DB::raw("ST_AsGeoJSON(poligono) as geojson_str"),
            ])
            ->get();

        $fronteras = [];
        foreach ($rows as $r) {
            if (empty($r->geojson_str)) {
                continue;
            }
            try {
                $gj = json_decode((string) $r->geojson_str, true);
                $coordsLonLat = $gj['coordinates'][0] ?? [];
                $coordsLatLon = [];
                foreach ($coordsLonLat as $pt) {
                    $coordsLatLon[] = [(float) $pt[1], (float) $pt[0]];
                }
                $fronteras[(string) $r->dia] = [
                    'poligono' => $coordsLatLon,
                    'origen' => (string) $r->origen,
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $fronteras;
    }
}

