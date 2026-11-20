<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class AuditarDuplicadosAction
{
    /**
     * Audita clientes duplicados evaluando:
     * 1. Misma ubicación (colisión espacial <= radioMetros)
     * 2. Mismos datos (mismo nombre, NIT, CI, teléfono o factura)
     * 3. Misma ubicación y mismos datos
     *
     * @param array<string>|null $rutas
     * @param float $radioMetros
     * @param string|null $tipoGrupo 'TODOS', 'MISMA_UBICACION', 'MISMOS_DATOS', 'MISMA_UBICACION_Y_DATOS', 'MISMOS_DATOS_DISTANTE', 'MISMA_UBICACION_SOLO'
     * @param array<string>|null $canales
     * @return array<string, mixed>
     */
    public function execute(
        ?array $rutas = null,
        float $radioMetros = 20.0,
        ?string $tipoGrupo = null,
        ?array $canales = null
    ): array {
        $conn = DB::connection('supervisor');

        // 1. Catálogo de canales por ruta
        $rutasDim = $conn->table('dim_rutas')
            ->select(['ruta', 'canal'])
            ->get();
        $mapaCanalRuta = [];
        foreach ($rutasDim as $rd) {
            $rKey = strtoupper(trim((string) $rd->ruta));
            if ($rKey !== '') {
                $mapaCanalRuta[$rKey] = trim((string) $rd->canal);
            }
        }

        // 2. Obtener candidatos con coordenadas válidas de pan_ruteo
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
                'p.fecha_servidor',
                'p.fecha_dispositivo',
                'p.vendedor',
                DB::raw("TRIM(UPPER(p.ruta)) as ruta"),
                DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') as dia"),
                DB::raw("COALESCE(TRIM(p.tipo_negocio), '-') as tipo_negocio"),
            ])
            ->get();

        if ($puntosRaw->isEmpty()) {
            return [
                'rutas_auditadas' => $rutas ?: [],
                'canales_auditados' => $canales ?: [],
                'radio_metros_evaluado' => $radioMetros,
                'tipo_grupo_evaluado' => $tipoGrupo ?: 'TODOS',
                'total_puntos_evaluados' => 0,
                'total_clusters' => 0,
                'conteo_misma_ubicacion' => 0,
                'conteo_mismos_datos' => 0,
                'conteo_misma_ubicacion_y_datos' => 0,
                'clusters' => [],
            ];
        }

        // 3. Pre-normalización y limpieza
        $puntos = [];
        $puntosMap = [];
        foreach ($puntosRaw as $row) {
            $cid = (int) $row->cliente_id;
            $tel = (string) $row->telefono;
            $cel = (string) $row->celular;
            $fact = (string) $row->nombre_factura;
            $nit = trim((string) $row->nit);
            $ci = preg_replace('/\D/', '', (string) $row->ci);
            $rutaStr = (string) $row->ruta;
            $canalStr = $mapaCanalRuta[$rutaStr] ?? 'SIN CANAL';

            $cNorm = $this->normalizarTexto((string) $row->cliente_norm ?: (string) $row->cliente);
            $nitClean = (!in_array($nit, ['', '0', '00', '000', '1', '123', '1234', '12345'], true) && strlen($nit) >= 5) ? $nit : '';
            $ciClean = (!in_array($ci, ['', '0', '00', '000', '1', '123', '1234', '12345'], true) && strlen($ci) >= 5) ? $ci : '';
            $telNorm = $this->normalizarTelefono($tel);
            $celNorm = $this->normalizarTelefono($cel);
            $factNorm = $this->normalizarTexto($fact);

            $p = [
                'cliente_id' => $cid,
                'cliente' => trim((string) $row->cliente),
                'cliente_norm' => $cNorm,
                'nombre_factura' => trim($fact),
                'nit' => $nitClean,
                'ci' => $ciClean,
                'telefono' => $tel,
                'celular' => $cel,
                'direccion' => (string) $row->direccion,
                'latitud' => (float) $row->latitud,
                'longitud' => (float) $row->longitud,
                'ruta' => $rutaStr,
                'canal' => $canalStr,
                'dia' => (string) $row->dia,
                'vendedor' => (string) ($row->vendedor ?? ''),
                'fecha_servidor' => (string) ($row->fecha_servidor ?? ''),
                'fecha_dispositivo' => (string) ($row->fecha_dispositivo ?? ''),
                'tipo_negocio' => (string) $row->tipo_negocio,
                '_tel_norm' => $telNorm,
                '_cel_norm' => $celNorm,
                '_factura_norm' => $factNorm,
                '_nit_clean' => $nitClean,
                '_ci_clean' => $ciClean,
            ];

            $puntos[] = $p;
            $puntosMap[$cid] = $p;
        }

        // 4. Índices para "MISMOS DATOS" (Nombre, NIT, CI, Teléfono/Celular, Factura)
        $nombresGenericos = [
            '', 'tienda', 'almacen', 'venta', 'ventas', 'sin nombre', 'sn', 's n', 's/n',
            'cliente', 'pension', 'micromercado', 'kiosko', 'sn nombre', 'comercial',
            'restaurante', 'farmacia', 'ferreteria', 'panaderia', 'pasteleria', 'snack',
            'variedades', 'bazar', 'licoreria', 'minimarket', 'abarrotes', 'doña', 'don',
        ];
        $genericosMap = array_fill_keys($nombresGenericos, true);

        $indexNombre = [];
        $indexNit = [];
        $indexCi = [];
        $indexTel = [];
        $indexFactura = [];

        foreach ($puntos as $p) {
            $cid = $p['cliente_id'];

            if ($p['cliente_norm'] !== '' && mb_strlen($p['cliente_norm']) >= 4 && !isset($genericosMap[$p['cliente_norm']])) {
                $indexNombre[$p['cliente_norm']][] = $cid;
            }
            if ($p['_nit_clean'] !== '') {
                $indexNit[$p['_nit_clean']][] = $cid;
            }
            if ($p['_ci_clean'] !== '') {
                $indexCi[$p['_ci_clean']][] = $cid;
            }
            if ($p['_tel_norm'] !== '') {
                $indexTel[$p['_tel_norm']][] = $cid;
            }
            if ($p['_cel_norm'] !== '') {
                $indexTel[$p['_cel_norm']][] = $cid;
            }
            if ($p['_factura_norm'] !== '' && mb_strlen($p['_factura_norm']) >= 4 && !isset($genericosMap[$p['_factura_norm']])) {
                $indexFactura[$p['_factura_norm']][] = $cid;
            }
        }

        // Matriz de adyacencia de MISMOS DATOS
        $adjDatos = [];
        $distanciasDatos = [];
        $criteriosDatos = [];

        $agregarCoincidenciaDato = function (int $id1, int $id2, string $campo, string $valor) use (
            &$adjDatos, &$distanciasDatos, &$criteriosDatos, $puntosMap
        ): void {
            if ($id1 === $id2) {
                return;
            }
            $minId = min($id1, $id2);
            $maxId = max($id1, $id2);
            $par = "{$minId}_{$maxId}";

            if (!isset($criteriosDatos[$par])) {
                $criteriosDatos[$par] = [];
                $adjDatos[$minId][] = $maxId;
                $adjDatos[$maxId][] = $minId;

                $p1 = $puntosMap[$minId];
                $p2 = $puntosMap[$maxId];
                $dM = GeometryHelper::haversineDistance($p1['latitud'], $p1['longitud'], $p2['latitud'], $p2['longitud']);
                $distanciasDatos[$par] = round($dM, 1);
            }

            $criteriosDatos[$par][] = ['campo' => $campo, 'valor' => $valor];
        };

        // Procesar coincidencias de datos con límite defensivo por bucket para evitar explosiones
        $procesarBucket = function (array $index, string $campo, int $maxBucket = 20) use ($agregarCoincidenciaDato, $puntosMap) {
            foreach ($index as $valor => $cids) {
                $cnt = count($cids);
                if ($cnt >= 2 && $cnt <= $maxBucket) {
                    for ($i = 0; $i < $cnt; $i++) {
                        for ($j = $i + 1; $j < $cnt; $j++) {
                            $valMostrado = $campo === 'Nombre' ? $puntosMap[$cids[$i]]['cliente'] : (string) $valor;
                            $agregarCoincidenciaDato($cids[$i], $cids[$j], $campo, $valMostrado);
                        }
                    }
                }
            }
        };

        $procesarBucket($indexNombre, 'Nombre');
        $procesarBucket($indexNit, 'NIT');
        $procesarBucket($indexCi, 'CI');
        $procesarBucket($indexTel, 'Teléfono');
        $procesarBucket($indexFactura, 'Factura');

        // 5. Grid espacial para "MISMA UBICACIÓN" (proximidad <= radioMetros)
        $tamanoCelda = max(0.0003, $radioMetros / 100000.0);
        $grid = [];
        foreach ($puntos as $p) {
            $kLat = (int) floor($p['latitud'] / $tamanoCelda);
            $kLon = (int) floor($p['longitud'] / $tamanoCelda);
            $grid["{$kLat}_{$kLon}"][] = $p;
        }

        $adjProx = [];
        $distanciasProx = [];
        $criteriosProx = [];

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

                    $dM = GeometryHelper::haversineDistance($p1['latitud'], $p1['longitud'], $p2['latitud'], $p2['longitud']);
                    if ($dM <= $radioMetros) {
                        $par = "{$id1}_{$id2}";
                        $adjProx[$id1][] = $id2;
                        $adjProx[$id2][] = $id1;
                        $distanciasProx[$par] = round($dM, 1);
                        $criteriosProx[$par] = [['campo' => 'Proximidad', 'valor' => round($dM, 1) . ' m']];
                    }
                }
            }
        }

        // 6. Detección de Componentes Conexos
        $detectarClusters = function (array $adj): array {
            $visitados = [];
            $clusters = [];
            foreach (array_keys($adj) as $cid) {
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

        // Componentes conexos de datos
        $clustersDatosRaw = $detectarClusters($adjDatos);

        // Mapear qué clientes ya están cubiertos en clusters de datos
        $mapaClienteAClusterDato = [];
        foreach ($clustersDatosRaw as $idxDato => $clIds) {
            foreach ($clIds as $cId) {
                $mapaClienteAClusterDato[$cId] = $idxDato;
            }
        }

        // Filtrar adjProx para extraer colisiones espaciales PURAS (donde no pertenecen al mismo cluster de datos)
        $adjProxPuro = [];
        foreach ($adjProx as $id1 => $vecinos) {
            foreach ($vecinos as $id2) {
                $cd1 = $mapaClienteAClusterDato[$id1] ?? null;
                $cd2 = $mapaClienteAClusterDato[$id2] ?? null;
                // Si ambos no están en el mismo cluster de datos, es una colisión de ubicación independiente
                if ($cd1 === null || $cd2 === null || $cd1 !== $cd2) {
                    $adjProxPuro[$id1][] = $id2;
                }
            }
        }
        $clustersProxRaw = $detectarClusters($adjProxPuro);

        // 7. Estructurar clusters sin duplicación
        // A) Clusters de datos (pueden tener o no misma ubicación)
        $clustersConsolidados = [];

        foreach ($clustersDatosRaw as $clIds) {
            $minDist = 999999.0;
            $countIds = count($clIds);
            $criteriosAgrupados = [];

            for ($i = 0; $i < $countIds; $i++) {
                for ($j = $i + 1; $j < $countIds; $j++) {
                    $par = min($clIds[$i], $clIds[$j]) . '_' . max($clIds[$i], $clIds[$j]);
                    if (isset($distanciasDatos[$par]) && $distanciasDatos[$par] < $minDist) {
                        $minDist = $distanciasDatos[$par];
                    }
                    if (isset($criteriosDatos[$par])) {
                        foreach ($criteriosDatos[$par] as $crit) {
                            $criteriosAgrupados[$crit['campo']][(string) $crit['valor']] = true;
                        }
                    }
                }
            }

            $tieneMismaUbicacion = ($minDist <= $radioMetros);
            $tipo = $tieneMismaUbicacion ? 'MISMA_UBICACION_Y_DATOS' : 'MISMOS_DATOS';

            $clustersConsolidados[] = [
                'clasificacion' => $tipo,
                'tiene_misma_ubicacion' => $tieneMismaUbicacion,
                'tiene_mismos_datos' => true,
                'clientes_ids' => $clIds,
                'distancia_minima_m' => $minDist !== 999999.0 ? $minDist : 0.0,
                'criterios_raw' => $criteriosAgrupados,
            ];
        }

        // B) Clusters de ubicación pura (misma ubicación, diferentes datos)
        foreach ($clustersProxRaw as $clIds) {
            $minDist = 999999.0;
            $countIds = count($clIds);
            $criteriosAgrupados = [];

            for ($i = 0; $i < $countIds; $i++) {
                for ($j = $i + 1; $j < $countIds; $j++) {
                    $par = min($clIds[$i], $clIds[$j]) . '_' . max($clIds[$i], $clIds[$j]);
                    if (isset($distanciasProx[$par]) && $distanciasProx[$par] < $minDist) {
                        $minDist = $distanciasProx[$par];
                    }
                    if (isset($criteriosProx[$par])) {
                        foreach ($criteriosProx[$par] as $crit) {
                            $criteriosAgrupados[$crit['campo']][(string) $crit['valor']] = true;
                        }
                    }
                }
            }

            $clustersConsolidados[] = [
                'clasificacion' => 'MISMA_UBICACION',
                'tiene_misma_ubicacion' => true,
                'tiene_mismos_datos' => false,
                'clientes_ids' => $clIds,
                'distancia_minima_m' => $minDist !== 999999.0 ? $minDist : 0.0,
                'criterios_raw' => $criteriosAgrupados,
            ];
        }

        // 8. Ventas MTD y Propuestas Pendientes
        $todosCids = [];
        foreach ($clustersConsolidados as $item) {
            foreach ($item['clientes_ids'] as $cid) {
                $todosCids[] = $cid;
            }
        }
        $todosCids = array_values(array_unique($todosCids));

        $mapaVentas = $this->obtenerVentasMtd($todosCids);

        // Consultar historial de alta/creación e ingreso al padrón en auditoria_ruteo_cambios
        $mapaAltasRuteo = [];
        if (!empty($todosCids)) {
            $altasDb = $conn->table('auditoria_ruteo_cambios')
                ->whereIn('cliente_id', $todosCids)
                ->where(function ($w) {
                    $w->where('tipo_evento', 'NUEVO')
                      ->orWhere('campo_afectado', 'cliente_alta_total');
                })
                ->selectRaw('cliente_id, MIN(fecha_auditoria) as fecha_alta, MIN(archivo_origen) as archivo_origen')
                ->groupBy('cliente_id')
                ->get();

            foreach ($altasDb as $a) {
                $mapaAltasRuteo[(int) $a->cliente_id] = [
                    'fecha' => (string) $a->fecha_alta,
                    'origen' => 'Alta en ' . (string) ($a->archivo_origen ?? 'Ruteo'),
                ];
            }

            // Para los clientes que no tuvieron evento explícito 'NUEVO', buscar el primer evento registrado
            $cidsRestantes = array_diff($todosCids, array_keys($mapaAltasRuteo));
            if (!empty($cidsRestantes)) {
                $primerEventoDb = $conn->table('auditoria_ruteo_cambios')
                    ->whereIn('cliente_id', $cidsRestantes)
                    ->selectRaw('cliente_id, MIN(fecha_auditoria) as primera_fecha, MIN(archivo_origen) as primer_archivo')
                    ->groupBy('cliente_id')
                    ->get();

                foreach ($primerEventoDb as $pe) {
                    if (!empty($pe->primera_fecha)) {
                        $mapaAltasRuteo[(int) $pe->cliente_id] = [
                            'fecha' => (string) $pe->primera_fecha,
                            'origen' => 'Histórico en ' . (string) ($pe->primer_archivo ?? 'Ruteo'),
                        ];
                    }
                }
            }
        }

        $listAction = new ListPropuestasAction();
        $propuestasPendientes = $listAction->execute(
            tipoHallazgo: 'DUPLICADO',
            rutas: null,
            estado: 'PENDIENTE'
        );
        $mapaPendientes = [];
        foreach ($propuestasPendientes as $pr) {
            $mapaPendientes[(int) $pr['cliente_id_origen']] = $pr;
        }

        // 9. Construir respuesta final enriquecida
        $resultadoClusters = [];
        foreach ($clustersConsolidados as $cl) {
            $items = [];
            $rutasSet = [];
            $canalesSet = [];
            $totMtd = 0.0;

            foreach ($cl['clientes_ids'] as $cId) {
                $pt = $puntosMap[$cId];
                $vMtd = $mapaVentas[$cId] ?? 0.0;
                $totMtd += $vMtd;
                $rutasSet[] = $pt['ruta'];
                $canalesSet[] = $pt['canal'];

                $prop = $mapaPendientes[$cId] ?? null;
                $infoAlta = $mapaAltasRuteo[$cId] ?? null;
                $fechaIngreso = null;
                $origenIngreso = null;

                if ($infoAlta && !empty($infoAlta['fecha'])) {
                    $fechaIngreso = $infoAlta['fecha'];
                    $origenIngreso = $infoAlta['origen'];
                } elseif (!empty($pt['fecha_servidor'])) {
                    $fechaIngreso = (string) $pt['fecha_servidor'];
                    $origenIngreso = 'Servidor Ruteo';
                } elseif (!empty($pt['fecha_dispositivo'])) {
                    $fechaIngreso = (string) $pt['fecha_dispositivo'];
                    $origenIngreso = 'Dispositivo';
                }

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
                    'canal' => $pt['canal'],
                    'dia' => $pt['dia'],
                    'vendedor' => $pt['vendedor'] ?? '',
                    'tipo_negocio' => $pt['tipo_negocio'],
                    'venta_mtd' => round($vMtd, 2),
                    'fecha_ingreso' => $fechaIngreso,
                    'origen_ingreso' => $origenIngreso,
                    'tiene_propuesta_pendiente' => $prop !== null,
                    'propuesta_id' => $prop ? (int) $prop['id'] : null,
                    'accion_propuesta_pendiente' => $prop ? $prop['accion_propuesta'] : null,
                ];
            }

            // Ordenar clientes dentro del grupo: 1° mayor venta MTD, 2° más antiguo en ruteo si hay fecha, 3° menor ID
            usort($items, function ($a, $b) {
                if ($b['venta_mtd'] !== $a['venta_mtd']) {
                    return $b['venta_mtd'] <=> $a['venta_mtd'];
                }
                if (!empty($a['fecha_ingreso']) && !empty($b['fecha_ingreso'])) {
                    $cmp = strcmp($a['fecha_ingreso'], $b['fecha_ingreso']);
                    if ($cmp !== 0) return $cmp;
                }
                return $a['cliente_id'] <=> $b['cliente_id'];
            });

            foreach ($items as $idx => &$cliItem) {
                $cliItem['es_master_sugerido'] = ($idx === 0);
            }
            unset($cliItem);

            $criteriosFinales = [];
            foreach ($cl['criterios_raw'] as $campo => $valsMap) {
                $vals = array_keys($valsMap);
                sort($vals);
                $criteriosFinales[] = [
                    'campo' => $campo,
                    'valores' => $vals,
                ];
            }

            $rutasUnicas = array_values(array_unique(array_filter($rutasSet)));
            sort($rutasUnicas);

            $canalesUnicos = array_values(array_unique(array_filter($canalesSet)));
            sort($canalesUnicos);

            $resultadoClusters[] = [
                'cluster_id' => 0, // Se reasigna tras filtrar y ordenar
                'clasificacion' => $cl['clasificacion'],
                'tiene_misma_ubicacion' => $cl['tiene_misma_ubicacion'],
                'tiene_mismos_datos' => $cl['tiene_mismos_datos'],
                'criterios' => $criteriosFinales,
                'cantidad_clientes' => count($items),
                'distancia_minima_m' => round($cl['distancia_minima_m'], 1),
                'rutas' => $rutasUnicas,
                'canales' => $canalesUnicos,
                'venta_mtd_total' => round($totMtd, 2),
                'clientes' => $items,
            ];
        }

        // 10. Filtrar por canales y/o rutas si se especificaron
        if (!empty($canales)) {
            $canalesNorm = array_map(fn($c) => strtoupper(trim((string) $c)), array_filter($canales));
            $resultadoClusters = array_values(array_filter($resultadoClusters, function ($cl) use ($canalesNorm) {
                return count(array_intersect($cl['canales'], $canalesNorm)) > 0;
            }));
        }
        if (!empty($rutas)) {
            $rutasNorm = array_map(fn($r) => strtoupper(trim((string) $r)), array_filter($rutas));
            $resultadoClusters = array_values(array_filter($resultadoClusters, function ($cl) use ($rutasNorm) {
                return count(array_intersect($cl['rutas'], $rutasNorm)) > 0;
            }));
        }

        // 11. Filtrar por tipo de grupo si se solicitó en el backend
        if (!empty($tipoGrupo) && strtoupper(trim($tipoGrupo)) !== 'TODOS') {
            $tipoUpper = strtoupper(trim($tipoGrupo));
            $resultadoClusters = array_values(array_filter($resultadoClusters, function ($cl) use ($tipoUpper) {
                return match ($tipoUpper) {
                    'UBICACION', 'MISMA_UBICACION' => $cl['tiene_misma_ubicacion'] === true,
                    'DATOS', 'MISMOS_DATOS' => $cl['tiene_mismos_datos'] === true,
                    'CRITICOS', 'MISMA_UBICACION_Y_DATOS' => $cl['clasificacion'] === 'MISMA_UBICACION_Y_DATOS',
                    'MISMOS_DATOS_DISTANTE' => $cl['clasificacion'] === 'MISMOS_DATOS',
                    'MISMA_UBICACION_SOLO' => $cl['clasificacion'] === 'MISMA_UBICACION',
                    default => true,
                };
            }));
        }

        // 12. Ordenar: 1° Misma Ubicación y Mismos Datos, 2° Mismos Datos, 3° Misma Ubicación
        usort($resultadoClusters, function ($a, $b) {
            $ordenTipo = [
                'MISMA_UBICACION_Y_DATOS' => 1,
                'MISMOS_DATOS' => 2,
                'MISMA_UBICACION' => 3,
            ];

            $pesoA = $ordenTipo[$a['clasificacion']] ?? 9;
            $pesoB = $ordenTipo[$b['clasificacion']] ?? 9;

            if ($pesoA !== $pesoB) {
                return $pesoA <=> $pesoB;
            }

            return $a['distancia_minima_m'] <=> $b['distancia_minima_m'];
        });

        foreach ($resultadoClusters as $i => &$cl) {
            $cl['cluster_id'] = $i + 1;
        }
        unset($cl);

        $conteoMismaUbicacion = count(array_filter($resultadoClusters, fn($c) => $c['tiene_misma_ubicacion']));
        $conteoMismosDatos = count(array_filter($resultadoClusters, fn($c) => $c['tiene_mismos_datos']));
        $conteoMismaUbicacionYDatos = count(array_filter($resultadoClusters, fn($c) => $c['clasificacion'] === 'MISMA_UBICACION_Y_DATOS'));

        return [
            'rutas_auditadas' => $rutas ?: [],
            'canales_auditados' => $canales ?: [],
            'radio_metros_evaluado' => $radioMetros,
            'tipo_grupo_evaluado' => $tipoGrupo ?: 'TODOS',
            'total_puntos_evaluados' => count($puntos),
            'total_clusters' => count($resultadoClusters),
            'conteo_misma_ubicacion' => $conteoMismaUbicacion,
            'conteo_mismos_datos' => $conteoMismosDatos,
            'conteo_misma_ubicacion_y_datos' => $conteoMismaUbicacionYDatos,
            'clusters' => $resultadoClusters,
        ];
    }

    protected function normalizarTelefono(?string $t): string
    {
        if (empty($t)) {
            return '';
        }
        $digitos = preg_replace('/\D/', '', (string) $t);
        if (strlen($digitos) >= 8) {
            $sub = substr($digitos, -8);
            if (!in_array($sub, ['00000000', '11111111', '12345678', '77777777'], true)) {
                return $sub;
            }
        }
        return '';
    }

    protected function normalizarTexto(?string $t): string
    {
        if (empty($t)) {
            return '';
        }
        $txt = mb_strtolower(trim((string) $t), 'UTF-8');
        $txt = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'ü', 'Ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n', 'u', 'u'],
            $txt
        );
        $txt = preg_replace('/[^a-z0-9\s]/', ' ', $txt);
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
