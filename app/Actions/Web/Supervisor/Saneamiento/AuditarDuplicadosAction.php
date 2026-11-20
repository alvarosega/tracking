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
                $mapaCanalRuta[$rKey] = strtoupper(trim((string) $rd->canal));
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

        // 3. Pre-normalización y extracción de núcleos inteligentes
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
            $nucleo = $this->obtenerNucleoOrdenado((string) $row->cliente_norm ?: (string) $row->cliente);
            $tokens = $this->extraerTokensSignificativos((string) $row->cliente_norm ?: (string) $row->cliente);

            $factNorm = $this->normalizarTexto($fact);
            $nucleoFact = $this->obtenerNucleoOrdenado($fact);
            $tokensFact = $this->extraerTokensSignificativos($fact);

            $nitDigits = preg_replace('/\D/', '', $nit);
            $nitClean = (!in_array($nitDigits, ['', '0', '00', '000', '1', '123', '1234', '12345'], true) && strlen($nitDigits) >= 5 && !preg_match('/^(\d)\1+$/', $nitDigits)) ? $nitDigits : '';
            $ciClean = (!in_array($ci, ['', '0', '00', '000', '1', '123', '1234', '12345'], true) && strlen($ci) >= 5 && !preg_match('/^(\d)\1+$/', $ci)) ? $ci : '';

            $telNorm = $this->normalizarTelefono($tel);
            $celNorm = $this->normalizarTelefono($cel);

            $p = [
                'cliente_id' => $cid,
                'cliente' => trim((string) $row->cliente),
                'cliente_norm' => $cNorm,
                'nucleo' => $nucleo,
                'tokens' => $tokens,
                'nombre_factura' => trim($fact),
                'nucleo_factura' => $nucleoFact,
                'tokens_factura' => $tokensFact,
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

        // 4. Índices para "MISMOS DATOS" Y "NOMBRES SIMILARES"
        $genericosMap = array_fill_keys(self::$stopWords, true);

        $indexDoc = [];       // NIT, CI y cruce unificado de documentos
        $indexNombre = [];    // Nombre exacto normalizado
        $indexNucleo = [];    // Núcleo de nombre ordenado (sin stop-words)
        $indexFactura = [];   // Razón social / factura
        $indexTel = [];       // Teléfonos y celulares
        $indexTokens = [];    // Tokens significativos individuales para matching difuso/subconjunto

        foreach ($puntos as $p) {
            $cid = $p['cliente_id'];

            // A) Documentos (NIT y CI unificados con etiqueta de origen)
            if ($p['_nit_clean'] !== '') {
                $indexDoc[$p['_nit_clean']][] = ['cid' => $cid, 'tipo' => 'NIT'];
            }
            if ($p['_ci_clean'] !== '') {
                $indexDoc[$p['_ci_clean']][] = ['cid' => $cid, 'tipo' => 'CI'];
            }

            // B) Nombre exacto y Núcleo ordenado
            if ($p['cliente_norm'] !== '' && mb_strlen($p['cliente_norm']) >= 4 && !isset($genericosMap[$p['cliente_norm']])) {
                $indexNombre[$p['cliente_norm']][] = $cid;
            }
            if ($p['nucleo'] !== '' && mb_strlen($p['nucleo']) >= 4 && !isset($genericosMap[$p['nucleo']])) {
                $indexNucleo[$p['nucleo']][] = $cid;
            }

            // C) Factura / Razón Social
            if ($p['_factura_norm'] !== '' && mb_strlen($p['_factura_norm']) >= 4 && !isset($genericosMap[$p['_factura_norm']])) {
                $indexFactura[$p['_factura_norm']][] = $cid;
            }
            if ($p['nucleo_factura'] !== '' && mb_strlen($p['nucleo_factura']) >= 4 && !isset($genericosMap[$p['nucleo_factura']])) {
                $indexFactura[$p['nucleo_factura']][] = $cid;
            }

            // D) Teléfonos
            if ($p['_tel_norm'] !== '') {
                $indexTel[$p['_tel_norm']][] = $cid;
            }
            if ($p['_cel_norm'] !== '') {
                $indexTel[$p['_cel_norm']][] = $cid;
            }

            // E) Tokens significativos (longitud >= 4, no genéricos)
            foreach ($p['tokens'] as $tok) {
                if (mb_strlen($tok) >= 4 && !isset($genericosMap[$tok])) {
                    $indexTokens[$tok][] = $cid;
                }
            }
        }

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
                    }
                }
            }
        }

        // Helper para calcular la distancia mínima entre un conjunto de clientes
        $calcularMinDistancia = function (array $cids) use ($puntosMap, $distanciasProx): float {
            $cnt = count($cids);
            if ($cnt < 2) return 0.0;
            $minDist = 999999.0;
            for ($i = 0; $i < $cnt; $i++) {
                for ($j = $i + 1; $j < $cnt; $j++) {
                    $minId = min($cids[$i], $cids[$j]);
                    $maxId = max($cids[$i], $cids[$j]);
                    $par = "{$minId}_{$maxId}";
                    if (isset($distanciasProx[$par])) {
                        $dM = $distanciasProx[$par];
                    } else {
                        $p1 = $puntosMap[$minId];
                        $p2 = $puntosMap[$maxId];
                        $dM = round(GeometryHelper::haversineDistance($p1['latitud'], $p1['longitud'], $p2['latitud'], $p2['longitud']), 1);
                    }
                    if ($dM < $minDist) $minDist = $dM;
                }
            }
            return $minDist !== 999999.0 ? $minDist : 0.0;
        };

        // 6. Generación de Grupos Atómicos (Cada grupo tiene una identidad real comprobada)
        $gruposPorClave = [];

        $registrarGrupo = function (array $cids, array $criteriosIniciales) use (&$gruposPorClave, $calcularMinDistancia, $radioMetros): void {
            $cids = array_values(array_unique($cids));
            sort($cids);
            if (count($cids) < 2) return;

            $key = implode('_', $cids);
            $minDist = $calcularMinDistancia($cids);
            $esMismaUbicacion = ($minDist <= $radioMetros);

            if (!isset($gruposPorClave[$key])) {
                $gruposPorClave[$key] = [
                    'clientes_ids' => $cids,
                    'distancia_minima_m' => $minDist,
                    'tiene_misma_ubicacion' => $esMismaUbicacion,
                    'tiene_mismos_datos' => false,
                    'criterios_raw' => [],
                ];
            }

            foreach ($criteriosIniciales as $campo => $valor) {
                if ($campo !== 'Ubicación') {
                    $gruposPorClave[$key]['tiene_mismos_datos'] = true;
                }
                $gruposPorClave[$key]['criterios_raw'][$campo][(string) $valor] = true;
            }

            if ($esMismaUbicacion) {
                $gruposPorClave[$key]['tiene_misma_ubicacion'] = true;
                $gruposPorClave[$key]['criterios_raw']['Ubicación'][round($minDist, 1) . ' m'] = true;
            }
        };

        // A) Grupos por Documento de Identidad (NIT / CI y Cruce NIT vs CI)
        foreach ($indexDoc as $docVal => $docItems) {
            $cids = array_values(array_unique(array_column($docItems, 'cid')));
            if (count($cids) >= 2 && count($cids) <= 20) {
                $tipos = array_unique(array_column($docItems, 'tipo'));
                if (count($tipos) === 1 && in_array('NIT', $tipos, true)) {
                    $campoCrit = 'NIT';
                } elseif (count($tipos) === 1 && in_array('CI', $tipos, true)) {
                    $campoCrit = 'CI';
                } else {
                    $campoCrit = 'Documento (NIT/CI)';
                }
                $registrarGrupo($cids, [$campoCrit => (string) $docVal]);
            }
        }

        // A.2) Cruce de NIT con sufijo de sucursal Bolivia (ej: CI 4567890 vs NIT 4567890012)
        foreach ($puntos as $p1) {
            $nit = $p1['_nit_clean'];
            if (strlen($nit) >= 9) {
                $baseCi = substr($nit, 0, strlen($nit) - 3);
                if (strlen($baseCi) >= 6 && isset($indexDoc[$baseCi])) {
                    $cidsMatch = array_values(array_unique(array_column($indexDoc[$baseCi], 'cid')));
                    foreach ($cidsMatch as $cid2) {
                        if ($p1['cliente_id'] !== $cid2) {
                            $registrarGrupo([$p1['cliente_id'], $cid2], [
                                'Documento (NIT/CI)' => "NIT {$nit} ~ CI {$baseCi}"
                            ]);
                        }
                    }
                }
            }
        }

        // B) Grupos por Nombre Comercial Idéntico (Exacto o Núcleo Idéntico)
        foreach ($indexNombre as $nomVal => $cids) {
            $cids = array_values(array_unique($cids));
            if (count($cids) >= 2 && count($cids) <= 20) {
                $p0 = $puntosMap[$cids[0]];
                $registrarGrupo($cids, ['Nombre' => $p0['cliente']]);
            }
        }

        foreach ($indexNucleo as $nucVal => $cids) {
            $cids = array_values(array_unique($cids));
            if (count($cids) >= 2 && count($cids) <= 20 && mb_strlen($nucVal) >= 4) {
                $p0 = $puntosMap[$cids[0]];
                $todosMismoNom = array_reduce($cids, fn($acc, $id) => $acc && ($puntosMap[$id]['cliente_norm'] === $p0['cliente_norm']), true);
                if (!$todosMismoNom) {
                    $registrarGrupo($cids, ['Nombre Similar' => strtoupper($nucVal)]);
                }
            }
        }

        // C) Grupos por Razón Social / Factura Idéntica
        foreach ($indexFactura as $factVal => $cids) {
            $cids = array_values(array_unique($cids));
            if (count($cids) >= 2 && count($cids) <= 20 && mb_strlen($factVal) >= 5) {
                $p0 = $puntosMap[$cids[0]];
                $nombreFact = $p0['nombre_factura'] ?: $factVal;
                $registrarGrupo($cids, ['Factura' => $nombreFact]);
            }
        }

        // D) Cruce Nombre Comercial vs Razón Social (Factura)
        foreach ($puntos as $p1) {
            $cid1 = $p1['cliente_id'];
            $nuc1 = $p1['nucleo'];
            if ($nuc1 !== '' && mb_strlen($nuc1) >= 5 && isset($indexFactura[$nuc1])) {
                foreach ($indexFactura[$nuc1] as $cid2) {
                    if ($cid1 < $cid2) {
                        $p2 = $puntosMap[$cid2];
                        $registrarGrupo([$cid1, $cid2], [
                            'Nombre / Factura' => "{$p1['cliente']} ~ Factura: {$p2['nombre_factura']}"
                        ]);
                    }
                }
            }
        }

        // E) Nombres Similares Inteligentes (Inverted Index de Tokens con Fuzzy Matching)
        $paresSimilares = [];
        foreach ($indexTokens as $token => $cids) {
            $cids = array_values(array_unique($cids));
            $cnt = count($cids);
            if ($cnt < 2 || $cnt > 35) {
                continue;
            }
            for ($i = 0; $i < $cnt; $i++) {
                for ($j = $i + 1; $j < $cnt; $j++) {
                    $idA = min($cids[$i], $cids[$j]);
                    $idB = max($cids[$i], $cids[$j]);
                    $pairKey = "{$idA}_{$idB}";
                    if (isset($paresSimilares[$pairKey])) {
                        continue;
                    }
                    $paresSimilares[$pairKey] = true;

                    $pA = $puntosMap[$idA];
                    $pB = $puntosMap[$idB];
                    $nucA = $pA['nucleo'];
                    $nucB = $pB['nucleo'];
                    if ($nucA === '' || $nucB === '' || $nucA === $nucB) {
                        continue;
                    }

                    $toksA = $pA['tokens'];
                    $toksB = $pB['tokens'];
                    $inter = array_intersect($toksA, $toksB);
                    $cantInter = count($inter);

                    $esSimilar = false;
                    $descSimilar = '';

                    // Caso 1: Comparten 2 o más tokens significativos
                    if ($cantInter >= 2) {
                        $esSimilar = true;
                        $descSimilar = strtoupper(implode(' ', $inter));
                    }
                    // Caso 2: Un nombre de 1 palabra distintiva (>= 6 letras) coincide exactamente
                    elseif ($cantInter === 1 && (count($toksA) === 1 || count($toksB) === 1)) {
                        $tokCommon = reset($inter);
                        if (mb_strlen($tokCommon) >= 6) {
                            $esSimilar = true;
                            $descSimilar = strtoupper($tokCommon);
                        }
                    }
                    // Caso 3: Similitud difusa de caracteres (similar_text >= 85% o Levenshtein <= 2)
                    if (!$esSimilar) {
                        similar_text($nucA, $nucB, $simPct);
                        $lenMin = min(mb_strlen($nucA), mb_strlen($nucB));
                        $lev = ($lenMin >= 6 && abs(mb_strlen($nucA) - mb_strlen($nucB)) <= 2)
                            ? levenshtein(substr($nucA, 0, 255), substr($nucB, 0, 255))
                            : 99;

                        if ($simPct >= 85.0 || ($lev <= 2 && $lenMin >= 7)) {
                            $esSimilar = true;
                            $descSimilar = "Similitud " . round($simPct, 0) . "%: " . strtoupper($pA['cliente']) . " ~ " . strtoupper($pB['cliente']);
                        }
                    }

                    if ($esSimilar) {
                        $registrarGrupo([$idA, $idB], ['Nombre Similar' => $descSimilar]);
                    }
                }
            }
        }

        // F) Grupos Atómicos por Teléfono + Coincidencia de Nombre/Factura/Ubicación
        foreach ($indexTel as $telVal => $cids) {
            $cids = array_values(array_unique($cids));
            $cnt = count($cids);
            if ($cnt >= 2 && $cnt <= 8) {
                for ($i = 0; $i < $cnt; $i++) {
                    for ($j = $i + 1; $j < $cnt; $j++) {
                        $idA = min($cids[$i], $cids[$j]);
                        $idB = max($cids[$i], $cids[$j]);
                        $p1 = $puntosMap[$idA];
                        $p2 = $puntosMap[$idB];

                        $mismoNombre = ($p1['cliente_norm'] !== '' && $p1['cliente_norm'] === $p2['cliente_norm']);
                        $mismoNucleo = ($p1['nucleo'] !== '' && $p1['nucleo'] === $p2['nucleo']);
                        $mismaFactura = ($p1['_factura_norm'] !== '' && $p1['_factura_norm'] === $p2['_factura_norm']);
                        $minD = $calcularMinDistancia([$idA, $idB]);
                        $esCerca = ($minD <= $radioMetros);

                        similar_text($p1['nucleo'], $p2['nucleo'], $simNuc);

                        if ($mismoNombre || $mismoNucleo || $mismaFactura || $esCerca || $simNuc >= 70.0) {
                            $crits = ['Teléfono' => (string) $telVal];
                            if ($mismoNombre) {
                                $crits['Nombre'] = $p1['cliente'];
                            } elseif ($mismoNucleo || $simNuc >= 70.0) {
                                $crits['Nombre Similar'] = strtoupper($p1['nucleo'] ?: $p1['cliente']);
                            }
                            if ($mismaFactura) {
                                $crits['Factura'] = $p1['nombre_factura'] ?: $p1['_factura_norm'];
                            }
                            $registrarGrupo([$idA, $idB], $crits);
                        }
                    }
                }
            }
        }

        // G) Grupos Atómicos por Misma Ubicación Física (Colisión Espacial)
        $visitadosProx = [];
        foreach (array_keys($adjProx) as $cid) {
            if (!isset($visitadosProx[$cid]) && !empty($adjProx[$cid])) {
                $clusterIds = [];
                $cola = [$cid];
                $visitadosProx[$cid] = true;
                while (!empty($cola)) {
                    $curr = array_shift($cola);
                    $clusterIds[] = $curr;
                    foreach ($adjProx[$curr] as $vecino) {
                        if (!isset($visitadosProx[$vecino])) {
                            $visitadosProx[$vecino] = true;
                            $cola[] = $vecino;
                        }
                    }
                }
                if (count($clusterIds) >= 2 && count($clusterIds) <= 15) {
                    $minD = $calcularMinDistancia($clusterIds);
                    $crits = ['Ubicación' => round($minD, 1) . ' m'];
                    
                    // En colisión espacial, comprobar si además tienen nombres idénticos o similares
                    $p0 = $puntosMap[$clusterIds[0]];
                    $nomC = $p0['cliente_norm'];
                    $todosMismoNombre = ($nomC !== '') && array_reduce($clusterIds, fn($acc, $id) => $acc && ($puntosMap[$id]['cliente_norm'] === $nomC), true);
                    if ($todosMismoNombre) {
                        $crits['Nombre'] = $p0['cliente'];
                    } else {
                        // Buscar si al menos dos clientes en la misma ubicación tienen nombres parecidos
                        for ($ci = 0; $ci < count($clusterIds); $ci++) {
                            for ($cj = $ci + 1; $cj < count($clusterIds); $cj++) {
                                $pA = $puntosMap[$clusterIds[$ci]];
                                $pB = $puntosMap[$clusterIds[$cj]];
                                if ($pA['nucleo'] !== '' && $pB['nucleo'] !== '') {
                                    similar_text($pA['nucleo'], $pB['nucleo'], $simSpa);
                                    $tokInter = array_intersect($pA['tokens'], $pB['tokens']);
                                    if ($simSpa >= 65.0 || count($tokInter) >= 1) {
                                        $crits['Nombre Similar'] = strtoupper($pA['cliente']) . ' ~ ' . strtoupper($pB['cliente']);
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    $registrarGrupo($clusterIds, $crits);
                }
            }
        }

        // 7. Estructurar clusters consolidados y filtrar veracidad
        $clustersConsolidados = [];
        foreach ($gruposPorClave as $item) {
            $cids = $item['clientes_ids'];
            $criteriosFiltrados = [];

            foreach ($item['criterios_raw'] as $campo => $valsMap) {
                if ($campo === 'Ubicación') {
                    $criteriosFiltrados['Ubicación'] = $valsMap;
                    continue;
                }

                $valoresUnicos = array_keys($valsMap);
                if (count($valoresUnicos) === 1 || in_array($campo, ['Nombre Similar', 'Documento (NIT/CI)', 'Nombre / Factura'], true)) {
                    $criteriosFiltrados[$campo] = $valsMap;
                }
            }

            $tieneUbic = $item['tiene_misma_ubicacion'] || isset($criteriosFiltrados['Ubicación']);
            $tieneDatos = count(array_diff(array_keys($criteriosFiltrados), ['Ubicación'])) > 0;

            if ($tieneUbic && $tieneDatos) {
                $clasif = 'MISMA_UBICACION_Y_DATOS';
            } elseif ($tieneDatos) {
                $clasif = 'MISMOS_DATOS';
            } else {
                $clasif = 'MISMA_UBICACION';
            }

            $clustersConsolidados[] = [
                'clasificacion' => $clasif,
                'tiene_misma_ubicacion' => $tieneUbic,
                'tiene_mismos_datos' => $tieneDatos,
                'clientes_ids' => $cids,
                'distancia_minima_m' => $item['distancia_minima_m'],
                'criterios_raw' => $criteriosFiltrados,
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

        // Consultar historial completo de modificaciones y altas en auditoria_ruteo_cambios
        $mapaCambios = [];
        $mapaAltasRuteo = [];
        if (!empty($todosCids)) {
            $cambiosDb = $conn->table('auditoria_ruteo_cambios')
                ->whereIn('cliente_id', $todosCids)
                ->select([
                    'id',
                    'cliente_id',
                    'archivo_origen',
                    'tipo_evento',
                    'campo_afectado',
                    DB::raw("COALESCE(valor_anterior, '-') AS valor_anterior"),
                    DB::raw("COALESCE(valor_nuevo, '-') AS valor_nuevo"),
                    'fecha_auditoria',
                    DB::raw("DATE_FORMAT(fecha_auditoria, '%Y-%m-%d %H:%i') AS fecha_auditoria_fmt"),
                    'estado_auditoria',
                    DB::raw("COALESCE(observaciones, '') AS observaciones"),
                    DB::raw("COALESCE(auditado_por, '') AS auditado_por"),
                    DB::raw("DATE_FORMAT(fecha_resolucion, '%Y-%m-%d %H:%i') AS fecha_resolucion"),
                ])
                ->orderByRaw("COALESCE(fecha_resolucion, fecha_auditoria) DESC")
                ->orderByDesc('id')
                ->get();

            foreach ($cambiosDb as $c) {
                $cidInt = (int) $c->cliente_id;
                $mapaCambios[$cidInt][] = [
                    'id' => (int) $c->id,
                    'cliente_id' => $cidInt,
                    'archivo_origen' => (string) ($c->archivo_origen ?? ''),
                    'tipo_evento' => (string) ($c->tipo_evento ?? ''),
                    'campo_afectado' => (string) ($c->campo_afectado ?? ''),
                    'valor_anterior' => (string) ($c->valor_anterior ?? '-'),
                    'valor_nuevo' => (string) ($c->valor_nuevo ?? '-'),
                    'fecha_auditoria' => (string) ($c->fecha_auditoria ?? ''),
                    'fecha_auditoria_fmt' => (string) ($c->fecha_auditoria_fmt ?? ''),
                    'estado_auditoria' => (string) ($c->estado_auditoria ?? 'PENDIENTE'),
                    'observaciones' => (string) ($c->observaciones ?? ''),
                    'auditado_por' => (string) ($c->auditado_por ?? ''),
                    'fecha_resolucion' => (string) ($c->fecha_resolucion ?? ''),
                ];

                // Identificar fecha y origen de alta
                if ($c->tipo_evento === 'NUEVO' || $c->campo_afectado === 'cliente_alta_total') {
                    if (!isset($mapaAltasRuteo[$cidInt]) || strcmp((string) $c->fecha_auditoria, $mapaAltasRuteo[$cidInt]['fecha']) < 0) {
                        $mapaAltasRuteo[$cidInt] = [
                            'fecha' => (string) $c->fecha_auditoria,
                            'origen' => 'Alta en ' . (string) ($c->archivo_origen ?: 'Ruteo'),
                        ];
                    }
                }
            }

            // Para los clientes que no tuvieron evento explícito 'NUEVO', tomar el evento más antiguo disponible
            foreach ($todosCids as $cid) {
                if (!isset($mapaAltasRuteo[$cid]) && !empty($mapaCambios[$cid])) {
                    $lista = $mapaCambios[$cid];
                    $primerEvento = end($lista);
                    if (!empty($primerEvento['fecha_auditoria'])) {
                        $mapaAltasRuteo[$cid] = [
                            'fecha' => (string) $primerEvento['fecha_auditoria'],
                            'origen' => 'Histórico en ' . (string) ($primerEvento['archivo_origen'] ?: 'Ruteo'),
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
                    'cambios' => $mapaCambios[$cId] ?? [],
                    'total_cambios' => count($mapaCambios[$cId] ?? []),
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

            $ordenCampos = [
                'Ubicación' => 1,
                'NIT' => 2,
                'CI' => 3,
                'Documento (NIT/CI)' => 4,
                'Teléfono' => 5,
                'Nombre' => 6,
                'Nombre Similar' => 7,
                'Factura' => 8,
                'Nombre / Factura' => 9,
            ];

            $criteriosFinales = [];
            foreach ($cl['criterios_raw'] as $campo => $valsMap) {
                $vals = array_keys($valsMap);
                sort($vals);
                // En un criterio de duplicidad por datos, un valor solo es idéntico si es único en el grupo, salvo criterios multievaluados controlados
                if ($campo !== 'Ubicación' && !in_array($campo, ['Nombre Similar', 'Documento (NIT/CI)', 'Nombre / Factura'], true) && count($vals) > 1) {
                    continue;
                }
                $criteriosFinales[] = [
                    'campo' => $campo,
                    'valores' => $vals,
                ];
            }

            usort($criteriosFinales, function ($a, $b) use ($ordenCampos) {
                $pA = $ordenCampos[$a['campo']] ?? 99;
                $pB = $ordenCampos[$b['campo']] ?? 99;
                return $pA <=> $pB;
            });

            $rutasUnicas = array_values(array_unique(array_filter(array_map(fn($r) => strtoupper(trim((string) $r)), $rutasSet))));
            sort($rutasUnicas);

            $canalesUnicos = array_values(array_unique(array_filter(array_map(fn($c) => strtoupper(trim((string) $c)), $canalesSet))));
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

        // 10. Filtrar por rutas y/o canales si se especificaron
        // Si se especificó una o más rutas, la condición mandatoria es que la ruta esté involucrada en el cluster
        if (!empty($rutas)) {
            $rutasNorm = array_map(fn($r) => strtoupper(trim((string) $r)), array_filter($rutas));
            $resultadoClusters = array_values(array_filter($resultadoClusters, function ($cl) use ($rutasNorm) {
                $clRutasUpper = array_map(fn($r) => strtoupper(trim((string) $r)), $cl['rutas']);
                return count(array_intersect($clRutasUpper, $rutasNorm)) > 0;
            }));
        } elseif (!empty($canales)) {
            // Si solo se especificaron canales (sin ruta específica), se muestran todos los clusters donde esté involucrado alguno de los canales
            $canalesNorm = array_map(fn($c) => strtoupper(trim((string) $c)), array_filter($canales));
            $resultadoClusters = array_values(array_filter($resultadoClusters, function ($cl) use ($canalesNorm) {
                $clCanalesUpper = array_map(fn($c) => strtoupper(trim((string) $c)), $cl['canales']);
                return count(array_intersect($clCanalesUpper, $canalesNorm)) > 0;
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

    protected static array $stopWords = [
        'tienda', 'tiendita', 'almacen', 'almacenes', 'micromercado', 'minimarket', 'supermercado',
        'kiosko', 'quiosco', 'comercial', 'bazar', 'variedades', 'pension', 'snack',
        'restaurante', 'comedor', 'cafeteria', 'libreria', 'farmacia', 'ferreteria',
        'panaderia', 'pasteleria', 'licoreria', 'distribuidora', 'agencia', 'sucursal',
        'abarrotes', 'fruteria', 'verduderia', 'carniceria', 'pollo', 'pollos',
        'puesto', 'caseta', 'venta', 'ventas', 'cliente', 'sin nombre', 'sn', 's n', 's/n',
        'sn nombre', 'dona', 'dono', 'don', 'doña', 'sr', 'sra', 'de', 'del', 'la', 'las',
        'el', 'los', 'y', 'en', 'para', 'san', 'santa', 'un', 'una', 'mi', 'tu', 'srl',
        'ltda', 'sa', 'sociedad', 'anonima', 'responsabilidad', 'limitada', 'hnos', 'hermanos',
    ];

    protected function extraerTokensSignificativos(string $texto): array
    {
        $norm = $this->normalizarTexto($texto);
        if ($norm === '') {
            return [];
        }
        $palabras = explode(' ', $norm);
        $stopMap = array_fill_keys(self::$stopWords, true);
        $tokens = [];
        foreach ($palabras as $p) {
            $p = trim($p);
            if (mb_strlen($p) >= 3 && !isset($stopMap[$p]) && !is_numeric($p)) {
                $tokens[] = $p;
            }
        }
        return array_values(array_unique($tokens));
    }

    protected function obtenerNucleoOrdenado(string $texto): string
    {
        $tokens = $this->extraerTokensSignificativos($texto);
        if (empty($tokens)) {
            $norm = $this->normalizarTexto($texto);
            return mb_strlen($norm) >= 4 ? $norm : '';
        }
        sort($tokens);
        return implode(' ', $tokens);
    }

    protected function normalizarTelefono(?string $t): string
    {
        if (empty($t)) {
            return '';
        }
        $digitos = preg_replace('/\D/', '', (string) $t);
        if (strlen($digitos) >= 7) {
            $sub = strlen($digitos) >= 8 ? substr($digitos, -8) : $digitos;
            if (!in_array($sub, ['0000000', '00000000', '1111111', '11111111', '1234567', '12345678', '7777777', '77777777'], true)) {
                if (!preg_match('/^(\d)\1+$/', $sub)) {
                    return $sub;
                }
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
