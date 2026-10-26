<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class AuditarDuplicadosAction
{
    /**
     * Audita clientes duplicados mediante 7 criterios espaciales y de datos.
     *
     * @param array<string>|null $rutas
     * @param float $radioMetros
     * @return array<string, mixed>
     */
    public function execute(?array $rutas = null, float $radioMetros = 20.0): array
    {
        $conn = DB::connection('supervisor');

        // 1. Obtener candidatos con coordenadas válidas
        $puntosRaw = $conn->table('pan_ruteo as p')
            ->where(function ($w) {
                $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                    ->orWhereNull('p.estado')
                    ->orWhereRaw("p.estado = ''");
            })
            ->whereNotNull('p.latitud')
            ->where('p.latitud', '!=', 0.0)
            ->whereNotNull('p.longitud')
            ->where('p.longitud', '!=', 0.0)
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
            ->get();

        if ($puntosRaw->isEmpty()) {
            return [
                'rutas_auditadas' => $rutas ?: [],
                'radio_metros_evaluado' => $radioMetros,
                'total_puntos_evaluados' => 0,
                'total_clusters' => 0,
                'clusters' => [],
            ];
        }

        // 2. Pre-normalizar
        $puntos = [];
        $puntosMap = [];
        foreach ($puntosRaw as $row) {
            $cid = (int) $row->cliente_id;
            $tel = (string) $row->telefono;
            $cel = (string) $row->celular;
            $fact = (string) $row->nombre_factura;
            $nit = trim((string) $row->nit);
            $ci = preg_replace('/\D/', '', (string) $row->ci);

            $p = [
                'cliente_id' => $cid,
                'cliente' => trim((string) $row->cliente),
                'cliente_norm' => strtolower(trim((string) $row->cliente_norm)),
                'nombre_factura' => trim($fact),
                'nit' => $nit,
                'ci' => (string) $row->ci,
                'telefono' => $tel,
                'celular' => $cel,
                'direccion' => (string) $row->direccion,
                'latitud' => (float) $row->latitud,
                'longitud' => (float) $row->longitud,
                'ruta' => (string) $row->ruta,
                'dia' => (string) $row->dia,
                'tipo_negocio' => (string) $row->tipo_negocio,
                '_tel_norm' => $this->normalizarTelefono($tel),
                '_cel_norm' => $this->normalizarTelefono($cel),
                '_factura_norm' => $this->normalizarTexto($fact),
                '_nit_clean' => (!in_array($nit, ['', '0', '00', '000'], true)) ? $nit : '',
                '_ci_clean' => strlen($ci) >= 4 ? $ci : '',
            ];

            $puntos[] = $p;
            $puntosMap[$cid] = $p;
        }

        // 3. Grid espacial (~50m por celda)
        $tamanoCelda = 0.0005;
        $grid = [];
        foreach ($puntos as $p) {
            $kLat = (int) floor($p['latitud'] / $tamanoCelda);
            $kLon = (int) floor($p['longitud'] / $tamanoCelda);
            $key = "{$kLat}_{$kLon}";
            $grid[$key][] = $p;
        }

        $adjDatos = [];
        $adjProx = [];
        foreach ($puntos as $p) {
            $cid = $p['cliente_id'];
            $adjDatos[$cid] = [];
            $adjProx[$cid] = [];
        }

        $distanciasDatos = [];
        $distanciasProx = [];
        $criteriosDatos = [];
        $criteriosProx = [];

        // 4. Comparar pares en celdas vecinas
        foreach ($grid as $key => $celdas) {
            [$kLat, $kLon] = explode('_', $key);
            $kLat = (int) $kLat;
            $kLon = (int) $kLon;

            $candidatos = [];
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    $vKey = ($kLat + $dx) . '_' . ($kLon + $dy);
                    if (isset($grid[$vKey])) {
                        foreach ($grid[$vKey] as $cand) {
                            $candidatos[] = $cand;
                        }
                    }
                }
            }

            foreach ($celdas as $p1) {
                $id1 = $p1['cliente_id'];
                foreach ($candidatos as $p2) {
                    $id2 = $p2['cliente_id'];
                    if ($id1 >= $id2) {
                        continue;
                    }

                    $dM = GeometryHelper::haversineDistance(
                        $p1['latitud'],
                        $p1['longitud'],
                        $p2['latitud'],
                        $p2['longitud']
                    );

                    $criteriosMatch = [];

                    if (!empty($p1['_nit_clean']) && $p1['_nit_clean'] === $p2['_nit_clean']) {
                        $criteriosMatch[] = ['campo' => 'NIT', 'valor' => $p1['_nit_clean']];
                    }
                    if (!empty($p1['_ci_clean']) && $p1['_ci_clean'] === $p2['_ci_clean']) {
                        $criteriosMatch[] = ['campo' => 'CI', 'valor' => $p1['_ci_clean']];
                    }
                    if (!empty($p1['cliente_norm']) && strlen($p1['cliente_norm']) > 4 && $p1['cliente_norm'] === $p2['cliente_norm']) {
                        $criteriosMatch[] = ['campo' => 'Nombre', 'valor' => $p1['cliente']];
                    }

                    if ($dM <= 100.0) {
                        if (!empty($p1['_tel_norm']) && strlen($p1['_tel_norm']) === 8 && $p1['_tel_norm'] === $p2['_tel_norm']) {
                            $criteriosMatch[] = ['campo' => 'Teléfono', 'valor' => $p1['telefono']];
                        }
                        if (!empty($p1['_cel_norm']) && strlen($p1['_cel_norm']) === 8 && $p1['_cel_norm'] === $p2['_cel_norm']) {
                            $criteriosMatch[] = ['campo' => 'Celular', 'valor' => $p1['celular']];
                        }
                        if (!empty($p1['_factura_norm']) && strlen($p1['_factura_norm']) > 4 && $p1['_factura_norm'] === $p2['_factura_norm']) {
                            $criteriosMatch[] = ['campo' => 'Nombre factura', 'valor' => $p1['nombre_factura']];
                        }
                    }

                    $pares = min($id1, $id2) . '_' . max($id1, $id2);

                    if (!empty($criteriosMatch)) {
                        $adjDatos[$id1][] = $id2;
                        $adjDatos[$id2][] = $id1;
                        $distanciasDatos[$pares] = round($dM, 1);
                        $criteriosDatos[$pares] = $criteriosMatch;
                    } elseif ($dM <= $radioMetros) {
                        $adjProx[$id1][] = $id2;
                        $adjProx[$id2][] = $id1;
                        $distanciasProx[$pares] = round($dM, 1);
                        $criteriosProx[$pares] = [['campo' => 'Proximidad', 'valor' => round($dM) . ' m']];
                    }
                }
            }
        }

        // 5. Componentes conexos
        $detectarClusters = function (array $adj) use ($puntosMap): array {
            $visitados = [];
            $clusters = [];
            foreach (array_keys($puntosMap) as $cid) {
                if (!isset($visitados[$cid]) && !empty($adj[$cid])) {
                    $clusterIds = [];
                    $cola = [$cid];
                    $visitados[$cid] = true;
                    while (!empty($cola)) {
                        $curr = array_shift($cola);
                        $clusterIds[] = $curr;
                        foreach ($adj[$curr] as $vecino) {
                            if (!isset($visitados[$vecino])) {
                                $visitados[$vecino] = true;
                                $cola[] = $vecino;
                            }
                        }
                    }
                    if (count($clusterIds) >= 2) {
                        $clusters[] = $clusterIds;
                    }
                }
            }
            return $clusters;
        };

        $clustersDatosRaw = $detectarClusters($adjDatos);
        $clustersProxRaw = $detectarClusters($adjProx);

        $clustersRaw = [];
        foreach ($clustersDatosRaw as $cl) {
            $clustersRaw[] = ['DOBLE_CODIGO', $cl, $distanciasDatos, $criteriosDatos];
        }
        foreach ($clustersProxRaw as $cl) {
            $clustersRaw[] = ['MISMA_UBICACION', $cl, $distanciasProx, $criteriosProx];
        }

        // 6. Ventas MTD y propuestas pendientes
        $todosCids = [];
        foreach ($clustersRaw as $item) {
            foreach ($item[1] as $cid) {
                $todosCids[] = $cid;
            }
        }
        $todosCids = array_values(array_unique($todosCids));

        $mapaVentas = $this->obtenerVentasMtd($todosCids);

        $listAction = new ListPropuestasAction();
        $propuestasPendientes = $listAction->execute(
            tipoHallazgo: 'DUPLICADO',
            rutas: null,
            estado: 'PENDIENTE'
        );
        $mapaPendientes = [];
        foreach ($propuestasPendientes as $p) {
            $mapaPendientes[(int) $p['cliente_id_origen']] = $p;
        }

        // 7. Construir respuesta por cluster
        $resultadoClusters = [];
        foreach ($clustersRaw as $idx => [$tipoCluster, $clIds, $distMap, $critMap]) {
            $items = [];
            $minDist = 999999.0;
            $rutasSet = [];
            $totMtd = 0.0;

            foreach ($clIds as $cId) {
                $pt = $puntosMap[$cId];
                $vMtd = $mapaVentas[$cId] ?? 0.0;
                $totMtd += $vMtd;
                $rutasSet[] = $pt['ruta'];

                $prop = $mapaPendientes[$cId] ?? null;
                $items[] = [
                    'cliente_id' => $pt['cliente_id'],
                    'cliente' => $pt['cliente'],
                    'nit' => $pt['nit'],
                    'ci' => $pt['ci'],
                    'telefono' => $pt['telefono'],
                    'celular' => $pt['celular'],
                    'nombre_factura' => $pt['nombre_factura'],
                    'direccion' => $pt['direccion'],
                    'latitud' => $pt['latitud'],
                    'longitud' => $pt['longitud'],
                    'ruta' => $pt['ruta'],
                    'dia' => $pt['dia'],
                    'tipo_negocio' => $pt['tipo_negocio'],
                    'venta_mtd' => round($vMtd, 2),
                    'tiene_propuesta_pendiente' => $prop !== null,
                    'propuesta_id' => $prop ? (int) $prop['id'] : null,
                    'accion_propuesta_pendiente' => $prop ? $prop['accion_propuesta'] : null,
                ];
            }

            $criteriosAgrupados = [];
            $countIds = count($clIds);
            for ($i = 0; $i < $countIds; $i++) {
                for ($j = $i + 1; $j < $countIds; $j++) {
                    $par = min($clIds[$i], $clIds[$j]) . '_' . max($clIds[$i], $clIds[$j]);
                    if (isset($distMap[$par]) && $distMap[$par] < $minDist) {
                        $minDist = $distMap[$par];
                    }
                    if (isset($critMap[$par])) {
                        foreach ($critMap[$par] as $crit) {
                            $criteriosAgrupados[$crit['campo']][(string) $crit['valor']] = true;
                        }
                    }
                }
            }

            $criteriosFinales = [];
            foreach ($criteriosAgrupados as $campo => $valsMap) {
                $vals = array_keys($valsMap);
                sort($vals);
                $criteriosFinales[] = [
                    'campo' => $campo,
                    'valores' => $vals,
                ];
            }

            $rutasUnicas = array_values(array_unique($rutasSet));
            sort($rutasUnicas);

            $resultadoClusters[] = [
                'cluster_id' => $idx + 1,
                'clasificacion' => $tipoCluster,
                'criterios' => $criteriosFinales,
                'cantidad_clientes' => count($items),
                'distancia_minima_m' => $minDist !== 999999.0 ? $minDist : $radioMetros,
                'rutas' => $rutasUnicas,
                'venta_mtd_total' => round($totMtd, 2),
                'clientes' => $items,
            ];
        }

        // 8. Filtrar por rutas activas si se especificó
        if (!empty($rutas)) {
            $rutasNorm = array_map(fn($r) => strtoupper(trim((string) $r)), array_filter($rutas));
            $resultadoClusters = array_values(array_filter($resultadoClusters, function ($cl) use ($rutasNorm) {
                return count(array_intersect($cl['rutas'], $rutasNorm)) > 0;
            }));
        }

        // 9. Ordenar: Doble código primero, luego por distancia
        usort($resultadoClusters, function ($a, $b) {
            $tipoOrderA = $a['clasificacion'] === 'DOBLE_CODIGO' ? 0 : 1;
            $tipoOrderB = $b['clasificacion'] === 'DOBLE_CODIGO' ? 0 : 1;
            if ($tipoOrderA !== $tipoOrderB) {
                return $tipoOrderA <=> $tipoOrderB;
            }
            return $a['distancia_minima_m'] <=> $b['distancia_minima_m'];
        });

        foreach ($resultadoClusters as $i => &$cl) {
            $cl['cluster_id'] = $i + 1;
        }
        unset($cl);

        return [
            'rutas_auditadas' => $rutas ?: [],
            'radio_metros_evaluado' => $radioMetros,
            'total_puntos_evaluados' => count($puntos),
            'total_clusters' => count($resultadoClusters),
            'clusters' => $resultadoClusters,
        ];
    }

    protected function normalizarTelefono(?string $t): string
    {
        if (empty($t)) {
            return '';
        }
        $digitos = preg_replace('/\D/', '', (string) $t);
        return strlen($digitos) >= 8 ? substr($digitos, -8) : '';
    }

    protected function normalizarTexto(?string $t): string
    {
        if (empty($t)) {
            return '';
        }
        $txt = trim(strtolower((string) $t));
        if ($txt === '') {
            return '';
        }
        $txt = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt) ?: $txt;
        $txt = preg_replace('/\s+/', ' ', $txt);
        return trim($txt);
    }

    protected function obtenerVentasMtd(array $clienteIds): array
    {
        if (empty($clienteIds)) {
            return [];
        }

        $conn = DB::connection('supervisor');

        $reciente = $conn->table('fact_ventas')
            ->where(function ($w) {
                $w->whereNull('revertida')
                    ->orWhereNotIn(DB::raw("LOWER(TRIM(revertida))"), ['si', 'true', '1']);
            })
            ->whereNotNull(DB::raw("COALESCE(fecha_norm, fecha)"))
            ->select([
                DB::raw("YEAR(COALESCE(fecha_norm, fecha)) as anio_max"),
                DB::raw("MONTH(COALESCE(fecha_norm, fecha)) as mes_max"),
            ])
            ->orderByDesc(DB::raw("COALESCE(fecha_norm, fecha)"))
            ->first();

        if (!$reciente || empty($reciente->anio_max)) {
            return [];
        }

        $anioTarget = (int) $reciente->anio_max;
        $mesTarget = (int) $reciente->mes_max;

        $rows = $conn->table('fact_ventas')
            ->whereIn('cliente_id', $clienteIds)
            ->whereRaw("YEAR(COALESCE(fecha_norm, fecha)) = ?", [$anioTarget])
            ->whereRaw("MONTH(COALESCE(fecha_norm, fecha)) = ?", [$mesTarget])
            ->where(function ($w) {
                $w->whereNull('revertida')
                    ->orWhereNotIn(DB::raw("LOWER(TRIM(revertida))"), ['si', 'true', '1']);
            })
            ->groupBy('cliente_id')
            ->select([
                'cliente_id',
                DB::raw("COALESCE(SUM(monto_final), 0.0) as venta_mtd"),
            ])
            ->get();

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[(int) $r->cliente_id] = (float) $r->venta_mtd;
        }

        return $mapa;
    }
}

