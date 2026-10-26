<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class GetClientesFronterasAction
{
    /**
     * Entrega puntos y evalúa intrusos exclusivamente para las rutas solicitadas.
     *
     * @param array<string> $rutas
     * @return array<string, mixed>
     */
    public function execute(array $rutas): array
    {
        $rutasLimpias = array_values(array_unique(array_filter(array_map(fn($r) => strtoupper(trim((string) $r)), $rutas))));

        if (empty($rutasLimpias)) {
            return [
                'rutas_solicitadas' => [],
                'total_clientes' => 0,
                'total_intrusos' => 0,
                'total_huerfanos' => 0,
                'balance_rutas' => (object) [],
                'clientes' => [],
                'intrusos' => [],
                'huerfanos' => [],
            ];
        }

        $conn = DB::connection('supervisor');

        // 1. Clientes base de pan_ruteo para las rutas solicitadas
        $clientesRaw = $conn->table('pan_ruteo as p')
            ->where(function ($w) {
                $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                    ->orWhereNull('p.estado')
                    ->orWhereRaw("p.estado = ''");
            })
            ->whereNotNull('p.latitud')
            ->where('p.latitud', '!=', 0.0)
            ->whereNotNull('p.longitud')
            ->where('p.longitud', '!=', 0.0)
            ->whereIn(DB::raw("TRIM(UPPER(p.ruta))"), $rutasLimpias)
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
                DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') as dia"),
                DB::raw("COALESCE(TRIM(p.tipo_negocio), '-') as tipo_negocio"),
            ])
            ->orderBy('p.ruta')
            ->orderBy('p.cliente_id')
            ->get();

        // 2. Propuestas PENDIENTES asociadas
        $listAction = new ListPropuestasAction();
        $propuestasPendientes = $listAction->execute(
            tipoHallazgo: 'INTRUSO_FRONTERA',
            rutas: $rutasLimpias,
            estado: 'PENDIENTE'
        );

        $mapaPendientes = [];
        foreach ($propuestasPendientes as $p) {
            $mapaPendientes[(int) $p['cliente_id_origen']] = $p;
        }

        // 3. Proyección en memoria: si tiene propuesta PENDIENTE, adopta ruta/día propuesto
        $clientesAjustados = [];
        foreach ($clientesRaw as $c) {
            $cid = (int) $c->cliente_id;
            $cRuta = (string) $c->ruta;
            $cDia = strtoupper(trim((string) $c->dia));

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
                'tipo_negocio' => (string) $c->tipo_negocio,
            ];

            if (isset($mapaPendientes[$cid])) {
                $prop = $mapaPendientes[$cid];
                $item['ruta_original_base'] = $cRuta;
                $item['dia_original_base'] = $cDia;
                $item['ruta'] = (string) $prop['ruta_propuesta'];
                $item['dia'] = (string) ($prop['dia_propuesto'] ?: $cDia);
                $item['tiene_propuesta_pendiente'] = true;
                $item['propuesta_id'] = (int) $prop['id'];
            } else {
                $item['ruta_original_base'] = $cRuta;
                $item['dia_original_base'] = $cDia;
                $item['ruta'] = $cRuta;
                $item['dia'] = $cDia;
                $item['tiene_propuesta_pendiente'] = false;
                $item['propuesta_id'] = null;
            }

            $clientesAjustados[] = $item;
        }

        // 4. Polígonos activos ÚNICAMENTE para las rutas solicitadas
        $rowsFronteras = $conn->table('dim_fronteras_rutas')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasLimpias)
            ->where('estado', 'ACTIVO')
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta"),
                DB::raw("ST_AsGeoJSON(poligono) as geojson_str"),
            ])
            ->get();

        $mapaFronteras = [];
        foreach ($rowsFronteras as $f) {
            if (empty($f->geojson_str)) {
                continue;
            }
            try {
                $gj = json_decode((string) $f->geojson_str, true);
                $coordsLonLat = $gj['coordinates'][0] ?? [];
                $coordsLatLon = [];
                foreach ($coordsLonLat as $pt) {
                    $coordsLatLon[] = [(float) $pt[1], (float) $pt[0]];
                }
                if (count($coordsLatLon) >= 3) {
                    $mapaFronteras[(string) $f->ruta] = $coordsLatLon;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        // 5. Evaluar pertenencia e intrusos
        $intrusos = [];
        $huerfanos = [];
        $clientesFinales = [];

        foreach ($clientesAjustados as $c) {
            $cLat = $c['latitud'];
            $cLon = $c['longitud'];
            $cRuta = $c['ruta'];

            $poliRutaPropio = $mapaFronteras[$cRuta] ?? null;
            $tieneFronteraPropia = $poliRutaPropio !== null && count($poliRutaPropio) >= 3;

            $enFronteraRuta = false;
            if ($tieneFronteraPropia) {
                $enFronteraRuta = GeometryHelper::pointInPolygon($cLat, $cLon, $poliRutaPropio);
            }

            $fueraDeSuFrontera = !$enFronteraRuta;
            $rutaInvadida = null;

            if ($fueraDeSuFrontera) {
                foreach ($rutasLimpias as $otraRuta) {
                    if ($otraRuta === $cRuta) {
                        continue;
                    }

                    $poliOtro = $mapaFronteras[$otraRuta] ?? null;
                    if ($poliOtro && count($poliOtro) >= 3) {
                        if (GeometryHelper::pointInPolygon($cLat, $cLon, $poliOtro)) {
                            $rutaInvadida = $otraRuta;
                            break;
                        }
                    }
                }
            }

            $esIntruso = $fueraDeSuFrontera && ($rutaInvadida !== null);
            $esHuerfano = $fueraDeSuFrontera && ($rutaInvadida === null);

            $itemCliente = array_merge($c, [
                'es_intruso' => $esIntruso,
                'es_huerfano' => $esHuerfano,
                'ruta_duena_poligono' => $rutaInvadida,
                'fuera_de_su_frontera' => $fueraDeSuFrontera,
                'fuera_de_frontera_ruta' => $fueraDeSuFrontera,
                'fuera_de_frontera_dia' => false,
            ]);
            $clientesFinales[] = $itemCliente;

            if ($esIntruso) {
                $intrusos[] = [
                    'cliente_id' => $c['cliente_id'],
                    'cliente' => $c['cliente'],
                    'direccion' => $c['direccion'],
                    'latitud' => $cLat,
                    'longitud' => $cLon,
                    'ruta' => $cRuta,
                    'ruta_asignada' => $cRuta,
                    'ruta_invadida' => $rutaInvadida,
                    'dia' => $c['dia'],
                    'fuera_de_frontera_ruta' => $fueraDeSuFrontera,
                    'fuera_de_frontera_dia' => false,
                    'tiene_propuesta_pendiente' => $c['tiene_propuesta_pendiente'],
                    'propuesta_id' => $c['propuesta_id'],
                ];
            }

            if ($esHuerfano) {
                $huerfanos[] = [
                    'cliente_id' => $c['cliente_id'],
                    'cliente' => $c['cliente'],
                    'direccion' => $c['direccion'],
                    'latitud' => $cLat,
                    'longitud' => $cLon,
                    'ruta' => $cRuta,
                    'ruta_asignada' => $cRuta,
                    'dia' => $c['dia'],
                    'fuera_de_frontera_ruta' => $fueraDeSuFrontera,
                    'fuera_de_frontera_dia' => false,
                    'tiene_propuesta_pendiente' => $c['tiene_propuesta_pendiente'],
                    'propuesta_id' => $c['propuesta_id'],
                ];
            }
        }

        // 6. Balance de rutas
        $balanceRutas = [];
        foreach ($rutasLimpias as $r) {
            $balanceRutas[$r] = [
                'base' => 0,
                'salientes' => 0,
                'entrantes' => 0,
                'proyectado' => 0,
            ];
        }

        foreach ($clientesRaw as $c) {
            $rOrig = (string) $c->ruta;
            if (isset($balanceRutas[$rOrig])) {
                $balanceRutas[$rOrig]['base']++;
            }
        }

        foreach ($clientesAjustados as $c) {
            if ($c['tiene_propuesta_pendiente']) {
                $rOrig = $c['ruta_original_base'] ?? null;
                $rEfectiva = $c['ruta'];
                if ($rOrig && isset($balanceRutas[$rOrig])) {
                    $balanceRutas[$rOrig]['salientes']++;
                }
                if (isset($balanceRutas[$rEfectiva])) {
                    $balanceRutas[$rEfectiva]['entrantes']++;
                }
            }
        }

        foreach ($balanceRutas as $r => &$b) {
            $b['proyectado'] = $b['base'] - $b['salientes'] + $b['entrantes'];
        }
        unset($b);

        return [
            'rutas_solicitadas' => $rutasLimpias,
            'total_clientes' => count($clientesFinales),
            'total_intrusos' => count($intrusos),
            'total_huerfanos' => count($huerfanos),
            'balance_rutas' => $balanceRutas,
            'clientes' => $clientesFinales,
            'intrusos' => $intrusos,
            'huerfanos' => $huerfanos,
        ];
    }
}

