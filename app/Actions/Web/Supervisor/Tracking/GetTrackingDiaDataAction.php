<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Tracking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetTrackingDiaDataAction
{
    /**
     * Consulta la telemetría en vivo y recorrido de un vendedor durante una jornada,
     * calculando tramos por velocidad/estado, correlacionando visitas geolocalizadas
     * tanto de Android (app móvil) como de Web (padrón pan_ruteo y fact_visitas).
     *
     * @param string $fecha
     * @param string|null $ruta
     * @return array<string, mixed>
     */
    public function execute(string $fecha, ?string $ruta): array
    {
        $emptyResult = [
            'puntos_telemetria' => [],
            'visitas_android' => [],
            'puntos_web' => [],
            'lineas_desviacion' => [],
            'tramos' => [],
            'kpis' => [
                'total_visitas_android' => 0,
                'total_visitas_web' => 0,
                'visitas_con_venta' => 0,
                'monto_total_preventa' => 0.0,
                'desviacion_promedio_metros' => 0.0,
                'visitas_fuera_rango' => 0,
                'primera_visita' => null,
                'ultima_visita' => null,
                'alertas_mock' => 0,
                'bateria_actual' => null,
                'velocidad_actual' => null,
                'pasos_actual' => 0,
                'ultimo_reporte' => null,
                'en_movimiento' => false,
            ],
            // Compatibilidad hacia atrás
            'puntos' => [],
            'visitas' => [],
        ];

        if (!$ruta) {
            return $emptyResult;
        }

        // 1. Obtener vendedor asociado a la ruta
        $dimRuta = DB::connection('supervisor')->table('dim_rutas')
            ->where('ruta', $ruta)
            ->first();
        $vendedor = $dimRuta ? trim((string)$dimRuta->vendedor) : null;
        $vendedorNorm = $dimRuta ? trim((string)$dimRuta->vendedor_norm) : null;

        // 2. Obtener user si existe
        $user = DB::table('users')
            ->where('username', $ruta)
            ->orWhere('ruta', $ruta)
            ->first();

        // 3. Telemetría de locations (si existe)
        $locations = collect();
        if ($user) {
            $locations = DB::table('locations')
                ->where('user_id', $user->id)
                ->whereDate('recorded_at', $fecha)
                ->select([
                    'id', 'latitude', 'longitude', 'accuracy', 'speed',
                    'battery_level', 'is_mock', 'is_moving', 'step_count',
                    'motion_variance', 'recorded_at'
                ])
                ->orderBy('recorded_at', 'asc')
                ->get();
        }

        // 4. Visitas registradas en Android (tabla visitas)
        $visitasAndroidRaw = DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->where('v.route', $ruta)
            ->whereDate('v.visited_at', $fecha)
            ->select([
                'v.id',
                'v.client_id',
                'v.route',
                'v.status',
                'v.comments',
                'v.latitude as visita_lat',
                'v.longitude as visita_lng',
                'v.distance_to_client',
                'v.is_mock',
                'v.photo_path',
                'v.visited_at',
                'u.username as vendedor_usuario',
            ])
            ->orderBy('v.visited_at', 'asc')
            ->get();

        // 5. Visitas registradas en Web (tabla fact_visitas de supervisor)
        $factVisitasRaw = collect();
        if ($vendedor) {
            $factVisitasRaw = DB::connection('supervisor')->table('fact_visitas')
                ->whereDate('fecha', $fecha)
                ->where(function ($q) use ($vendedor, $vendedorNorm) {
                    $q->where('empleado', $vendedor);
                    if ($vendedorNorm) {
                        $q->orWhere('empleado_norm', $vendedorNorm);
                    }
                })
                ->orderBy('check_in', 'asc')
                ->get();
        }

        // Indexar fact_visitas por cliente normalizado
        $factVisitasMap = $factVisitasRaw->keyBy(function ($fv) {
            return strtoupper(trim((string)($fv->cliente_norm ?: $fv->cliente)));
        });

        // 6. Consultar clientes del padrón (pan_ruteo)
        $clientIds = $visitasAndroidRaw->pluck('client_id')->filter()->unique()->toArray();
        $nombresClientesWeb = $factVisitasRaw->pluck('cliente')->filter()->unique()->toArray();

        $padronClientes = DB::connection('supervisor')->table('pan_ruteo')
            ->where(function ($q) use ($clientIds, $nombresClientesWeb, $ruta) {
                $hasCond = false;
                if (!empty($clientIds)) {
                    $q->whereIn('cliente_id', $clientIds);
                    $hasCond = true;
                }
                if (!empty($nombresClientesWeb)) {
                    if ($hasCond) {
                        $q->orWhereIn('cliente', $nombresClientesWeb);
                    } else {
                        $q->whereIn('cliente', $nombresClientesWeb);
                        $hasCond = true;
                    }
                }
                if ($hasCond) {
                    $q->orWhere('ruta', $ruta);
                } else {
                    $q->where('ruta', $ruta);
                }
            })
            ->select([
                'cliente_id', 'cliente', 'cliente_norm', 'ruta', 'dia',
                'latitud', 'longitud', 'direccion', 'referencia', 'tipo_negocio'
            ])
            ->get();

        $padronById = $padronClientes->keyBy('cliente_id');
        $padronByName = $padronClientes->keyBy(function ($c) {
            return strtoupper(trim((string)($c->cliente_norm ?: $c->cliente)));
        });

        // 7. Formatear Visitas Android y enlazar con su Pin Web
        $visitasAndroidFormateadas = [];
        $puntosWeb = [];
        $lineasDesviacion = [];
        $puntosParaRecorrido = [];
        $desviacionesMetros = [];
        $alertasMock = 0;
        $visitasConVenta = 0;
        $montoTotalPreventa = 0.0;
        $clientesWebProcesados = [];

        foreach ($visitasAndroidRaw as $i => $va) {
            $orden = $i + 1;
            $cid = (int)$va->client_id;
            $andLat = (float)$va->visita_lat;
            $andLng = (float)$va->visita_lng;
            $isMock = (bool)$va->is_mock;
            if ($isMock) {
                $alertasMock++;
            }

            // Buscar en padrón
            $wc = $padronById->get($cid);
            $nombreCli = $wc ? $wc->cliente : "Cliente #{$cid}";
            $nombreCliNorm = strtoupper(trim((string)($wc ? ($wc->cliente_norm ?: $wc->cliente) : '')));

            // Buscar en fact_visitas
            $fv = $factVisitasMap->get($nombreCliNorm);
            $esVenta = strtoupper(trim((string)$va->status)) === 'PREVENTA' || ($fv && str_contains(strtoupper((string)$fv->tipo), 'PREVENTA'));
            if ($esVenta) {
                $visitasConVenta++;
            }
            if ($fv && (float)$fv->monto > 0) {
                $montoTotalPreventa += (float)$fv->monto;
            }

            // Coordenadas Web oficiales
            $webLat = ($wc && (float)$wc->latitud != 0) ? (float)$wc->latitud : null;
            $webLng = ($wc && (float)$wc->longitud != 0) ? (float)$wc->longitud : null;

            // Desviación en metros
            $distMetros = 0.0;
            if ($webLat !== null && $webLng !== null && $andLat != 0.0 && $andLng != 0.0) {
                $distMetros = $this->calcularDistanciaMetros($andLat, $andLng, $webLat, $webLng);
            } elseif ($va->distance_to_client !== null) {
                $distMetros = round((float)$va->distance_to_client, 1);
            }
            $desviacionesMetros[] = $distMetros;

            $horaStr = date('H:i', strtotime($va->visited_at));
            $horaSegundosStr = date('H:i:s', strtotime($va->visited_at));

            $itemAndroid = [
                'id' => $va->id,
                'orden' => $orden,
                'cliente_id' => $cid,
                'cliente' => $nombreCli,
                'status' => $va->status ?: ($fv ? $fv->tipo : 'VISITA'),
                'es_venta' => $esVenta,
                'monto' => $fv ? (float)$fv->monto : 0.0,
                'comments' => $va->comments,
                'hora' => $horaStr,
                'hora_completa' => $horaSegundosStr,
                'fecha_hora' => $va->visited_at,
                'lat' => $andLat,
                'lng' => $andLng,
                'web_lat' => $webLat,
                'web_lng' => $webLng,
                'desviacion_metros' => $distMetros,
                'is_mock' => $isMock,
                'photo_url' => $va->photo_path ? Storage::url($va->photo_path) : null,
                'check_in_web' => $fv ? (string)$fv->check_in : null,
                'check_out_web' => $fv ? (string)$fv->check_out : null,
                'tiempo_pdv_web' => $fv ? (string)$fv->tiempo_pdv : null,
            ];

            $visitasAndroidFormateadas[] = $itemAndroid;

            // Punto para trazado cronológico
            if ($andLat != 0.0 && $andLng != 0.0) {
                $puntosParaRecorrido[] = [
                    'lat' => $andLat,
                    'lng' => $andLng,
                    'hora' => $horaSegundosStr,
                    'orden' => $orden,
                    'es_venta' => $esVenta,
                    'is_mock' => $isMock,
                ];
            }

            // Pin Web (si tiene coordenadas)
            if ($webLat !== null && $webLng !== null && !isset($clientesWebProcesados[$cid])) {
                $clientesWebProcesados[$cid] = true;
                $puntosWeb[] = [
                    'cliente_id' => $cid,
                    'cliente' => $nombreCli,
                    'lat' => $webLat,
                    'lng' => $webLng,
                    'direccion' => $wc ? (string)$wc->direccion : '',
                    'referencia' => $wc ? (string)$wc->referencia : '',
                    'ruta' => $wc ? (string)$wc->ruta : $ruta,
                    'dia' => $wc ? (string)$wc->dia : '',
                    'tipo_negocio' => $wc ? (string)$wc->tipo_negocio : '',
                    'visitado_android' => true,
                    'orden_visita' => $orden,
                    'desviacion_metros' => $distMetros,
                ];
            }

            // Línea conector/desviación entre Android y Web
            if ($webLat !== null && $webLng !== null && $andLat != 0.0 && $andLng != 0.0) {
                $lineasDesviacion[] = [
                    'cliente_id' => $cid,
                    'cliente' => $nombreCli,
                    'from' => [$andLat, $andLng], // Android
                    'to' => [$webLat, $webLng],   // Web Padrón
                    'distancia_metros' => $distMetros,
                    'orden' => $orden,
                    'es_venta' => $esVenta,
                ];
            }
        }

        // Agregar clientes de fact_visitas (Web) que no estuvieron en Android
        foreach ($factVisitasRaw as $fv) {
            $nombreNorm = strtoupper(trim((string)($fv->cliente_norm ?: $fv->cliente)));
            $wc = $padronByName->get($nombreNorm);
            if ($wc && (float)$wc->latitud != 0 && (float)$wc->longitud != 0) {
                $cid = (int)$wc->cliente_id;
                if (!isset($clientesWebProcesados[$cid])) {
                    $clientesWebProcesados[$cid] = true;
                    $puntosWeb[] = [
                        'cliente_id' => $cid,
                        'cliente' => $wc->cliente,
                        'lat' => (float)$wc->latitud,
                        'lng' => (float)$wc->longitud,
                        'direccion' => (string)$wc->direccion,
                        'referencia' => (string)$wc->referencia,
                        'ruta' => (string)$wc->ruta,
                        'dia' => (string)$wc->dia,
                        'tipo_negocio' => (string)$wc->tipo_negocio,
                        'visitado_android' => false,
                        'orden_visita' => null,
                        'desviacion_metros' => null,
                        'check_in_web' => (string)$fv->check_in,
                        'monto_web' => (float)$fv->monto,
                        'tipo_web' => (string)$fv->tipo,
                    ];
                }
            }
        }

        // 8. Trazado del Recorrido (Polilínea cronológica)
        $tramos = [];
        if ($locations->count() > 1) {
            // Caso A: Usar telemetría de locations si está disponible
            for ($i = 0; $i < $locations->count() - 1; $i++) {
                $locA = $locations[$i];
                $locB = $locations[$i + 1];
                $color = '#0071E3'; // Movimiento regular
                if ($locA->is_mock || $locB->is_mock) {
                    $color = '#FF3B30'; // Mock
                } elseif (!$locA->is_moving && (float)$locA->speed <= 1.5) {
                    $color = '#FF9500'; // Parada
                }
                $tramos[] = [
                    'from' => [(float)$locA->latitude, (float)$locA->longitude],
                    'to' => [(float)$locB->latitude, (float)$locB->longitude],
                    'color' => $color,
                    'tipo' => 'telemetria',
                ];
            }
        } elseif (count($puntosParaRecorrido) > 1) {
            // Caso B: Conectar cronológicamente las visitas Android registradas
            for ($i = 0; $i < count($puntosParaRecorrido) - 1; $i++) {
                $pA = $puntosParaRecorrido[$i];
                $pB = $puntosParaRecorrido[$i + 1];
                $color = '#0071E3'; // Azul por defecto
                if ($pB['es_venta']) {
                    $color = '#248A3D'; // Verde hacia visita con venta
                }
                if ($pA['is_mock'] || $pB['is_mock']) {
                    $color = '#FF3B30'; // Rojo mock
                }
                $tramos[] = [
                    'from' => [$pA['lat'], $pA['lng']],
                    'to' => [$pB['lat'], $pB['lng']],
                    'color' => $color,
                    'tipo' => 'visitas',
                    'orden_destino' => $pB['orden'],
                ];
            }
        }

        // Puntos de telemetría formateados
        $puntosTelemetria = $locations->map(function ($loc) {
            return [
                'id' => $loc->id,
                'lat' => (float)$loc->latitude,
                'lng' => (float)$loc->longitude,
                'speed' => $loc->speed !== null ? round((float)$loc->speed, 1) : 0.0,
                'battery' => $loc->battery_level,
                'is_mock' => (bool)$loc->is_mock,
                'is_moving' => (bool)$loc->is_moving,
                'step_count' => $loc->step_count,
                'hora' => date('H:i:s', strtotime($loc->recorded_at)),
            ];
        })->toArray();

        // 9. KPIs y Métricas del Comportamiento
        $totalAndroid = count($visitasAndroidFormateadas);
        $totalWeb = count($puntosWeb);
        $desvProm = !empty($desviacionesMetros) ? round(array_sum($desviacionesMetros) / count($desviacionesMetros), 1) : 0.0;
        $fueraRango = count(array_filter($desviacionesMetros, fn($d) => $d > 100));

        $primeraVisita = null;
        $ultimaVisita = null;
        if (!empty($visitasAndroidFormateadas)) {
            $primeraVisita = $visitasAndroidFormateadas[0]['hora'];
            $ultimaVisita = $visitasAndroidFormateadas[$totalAndroid - 1]['hora'];
        } elseif ($factVisitasRaw->isNotEmpty()) {
            $primeraVisita = substr((string)$factVisitasRaw->first()->check_in, 0, 5);
            $ultimaVisita = substr((string)$factVisitasRaw->last()->check_out, 0, 5);
        }

        $ultimoLoc = $locations->last();

        $kpis = [
            'total_visitas_android' => $totalAndroid,
            'total_visitas_web' => $totalWeb,
            'visitas_con_venta' => $visitasConVenta,
            'monto_total_preventa' => round($montoTotalPreventa, 2),
            'desviacion_promedio_metros' => $desvProm,
            'visitas_fuera_rango' => $fueraRango,
            'primera_visita' => $primeraVisita,
            'ultima_visita' => $ultimaVisita,
            'alertas_mock' => $alertasMock,
            'bateria_actual' => $ultimoLoc ? $ultimoLoc->battery_level : null,
            'velocidad_actual' => $ultimoLoc && $ultimoLoc->speed !== null ? round((float)$ultimoLoc->speed, 1) : null,
            'pasos_actual' => $ultimoLoc ? (int)$ultimoLoc->step_count : 0,
            'ultimo_reporte' => $ultimoLoc ? date('H:i:s', strtotime($ultimoLoc->recorded_at)) : null,
            'en_movimiento' => $ultimoLoc ? (bool)$ultimoLoc->is_moving : false,
        ];

        return [
            'puntos_telemetria' => $puntosTelemetria,
            'visitas_android' => $visitasAndroidFormateadas,
            'puntos_web' => $puntosWeb,
            'lineas_desviacion' => $lineasDesviacion,
            'tramos' => $tramos,
            'kpis' => $kpis,
            'vendedor' => $vendedor,
            // Campos de compatibilidad con versión anterior
            'puntos' => $puntosTelemetria,
            'visitas' => $visitasAndroidFormateadas,
        ];
    }

    private function calcularDistanciaMetros(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if ($lat1 == 0.0 || $lon1 == 0.0 || $lat2 == 0.0 || $lon2 == 0.0) {
            return 0.0;
        }
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos(max(-1.0, min(1.0, $dist)));
        $dist = rad2deg($dist);
        return round($dist * 60 * 1.1515 * 1609.344, 1);
    }
}
