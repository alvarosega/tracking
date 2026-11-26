<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPizarraVisualHorarioDataAction
{
    private const DIAS_MAP = [
        1 => ['lunes', 'Lunes'],
        2 => ['martes', 'Martes'],
        3 => ['miercoles', 'miércoles', 'Miercoles', 'Miércoles'],
        4 => ['jueves', 'Jueves'],
        5 => ['viernes', 'Viernes'],
        6 => ['sabado', 'sábado', 'Sabado', 'Sábado'],
        7 => ['domingo', 'Domingo'],
    ];

    private const ROUTE_PALETTE = [
        '#0071E3', // Apple Blue
        '#34C759', // Apple Green
        '#FF9500', // Apple Orange
        '#AF52DE', // Apple Purple
        '#FF2D55', // Apple Pink
        '#5856D6', // Apple Indigo
        '#00C7BE', // Apple Teal
        '#FF3B30', // Apple Red
        '#A2845E', // Apple Brown
        '#64D2FF', // Apple Sky
        '#30B0C7', // Apple Cyan
        '#32ADE6', // Apple Ocean
        '#5E5CE6', // Apple Violet
        '#BF5AF2', // Apple Magenta
        '#FF375F', // Apple Rose
        '#FFD60A', // Apple Yellow
    ];

    public const HORA_LIMITE_DEFAULT = '08:10:00';

    /**
     * Consulta consolidada analítica y temporal del KPI 1 (Horario / Puntualidad)
     * con DOBLE CHECK auditado entre Web ERP (fact_visitas) y App Móvil (visitas + user_workdays en BD alva).
     *
     * @param string|null $fecha
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param string $horaLimite
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fecha = null,
        array $canales = [],
        array $rutas = [],
        string $horaLimite = self::HORA_LIMITE_DEFAULT
    ): array {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes', 'Lunes'];
        $diasFiltro = array_values(array_unique(array_merge($tuplaDias, array_map('strtolower', $tuplaDias))));

        $canalesClean = array_values(array_filter(array_map(fn($c) => strtoupper(trim((string)$c)), $canales)));
        $rutasClean = array_values(array_filter(array_map(fn($r) => strtoupper(trim((string)$r)), $rutas)));

        // 1. Catálogo de Rutas Objetivo
        $rutasQuery = DB::connection('supervisor')->table('dim_rutas')->select([
            'ruta',
            'canal',
            'vendedor',
            'vendedor_norm'
        ]);

        if (!empty($canalesClean)) {
            $rutasQuery->whereIn(DB::raw("TRIM(UPPER(canal))"), $canalesClean);
        }

        if (!empty($rutasClean)) {
            $rutasQuery->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasClean);
        }

        $catalogoRutas = $rutasQuery->get()->keyBy(fn($r) => strtoupper(trim((string)$r->ruta)));
        $rutasObjetivo = $catalogoRutas->keys()->all();

        if (empty($rutasObjetivo)) {
            return $this->generarRespuestaVacia($fechaNorm, $horaLimite);
        }

        $coloresRuta = [];
        foreach ($rutasObjetivo as $idx => $r) {
            $coloresRuta[$r] = self::ROUTE_PALETTE[$idx % count(self::ROUTE_PALETTE)];
        }

        // 2. Diccionario de Vendedores a Ruta
        $vendedorARuta = [];
        foreach ($catalogoRutas as $dim) {
            $r = strtoupper(trim((string)$dim->ruta));
            $v1 = $this->normalizarTexto((string)$dim->vendedor);
            $v2 = $this->normalizarTexto((string)$dim->vendedor_norm);
            if ($v1 !== '') $vendedorARuta[$v1] = $r;
            if ($v2 !== '') $vendedorARuta[$v2] = $r;
        }

        // 3. Clientes en Cartera Plan desde pan_ruteo
        $carteraPlan = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
            ->where(function ($q) use ($diasFiltro): void {
                $q->whereIn('dia_norm', $diasFiltro)
                  ->orWhereIn('dia', $diasFiltro);
            })
            ->where('cliente_id', '>', 0)
            ->whereRaw("TRIM(UPPER(COALESCE(estado, ''))) = 'ACTIVO'")
            ->select([
                'cliente_id',
                'ruta',
                DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), NULLIF(TRIM(cliente_norm), ''), CONCAT('Cliente #', cliente_id))) as cliente"),
                DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente_norm), ''), NULLIF(TRIM(cliente), ''), CONCAT('Cliente #', cliente_id))) as cliente_norm"),
                DB::raw("MAX(COALESCE(TRIM(territorio), '-')) as territorio"),
                DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
                DB::raw("MAX(COALESCE(TRIM(referencia), '-')) as referencia"),
                DB::raw("MAX(latitud) as latitud"),
                DB::raw("MAX(longitud) as longitud"),
                DB::raw("MAX(vendedor) as vendedor"),
            ])
            ->groupBy('cliente_id', 'ruta')
            ->get();

        $planPorRutaCliente = [];
        $planClientesPorNombreAlfa = [];
        $clienteIdARuta = [];
        foreach ($carteraPlan as $p) {
            $rUpper = strtoupper(trim((string)$p->ruta));
            $cid = (int)$p->cliente_id;
            $planPorRutaCliente["{$rUpper}_{$cid}"] = $p;
            $clienteIdARuta[$cid] = $rUpper;

            $alfa1 = $this->normalizarAlfanumerico((string)$p->cliente);
            $alfa2 = $this->normalizarAlfanumerico((string)$p->cliente_norm);
            if ($alfa1 !== '') $planClientesPorNombreAlfa[$rUpper][$alfa1] = $cid;
            if ($alfa2 !== '') $planClientesPorNombreAlfa[$rUpper][$alfa2] = $cid;
        }

        // 4. Montos de preventa del día (fact_preventas)
        $preventasMontoPorClave = [];
        try {
            $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->where('cliente_id', '>', 0)
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta"),
                    'cliente_id',
                    DB::raw("SUM(monto_final) as total_monto")
                ])
                ->groupBy(DB::raw("TRIM(UPPER(ruta))"), 'cliente_id')
                ->get();

            foreach ($preventasHoy as $ph) {
                $k = strtoupper(trim((string)$ph->ruta)) . '_' . (int)$ph->cliente_id;
                $preventasMontoPorClave[$k] = (float)$ph->total_monto;
            }
        } catch (\Throwable $e) {
            Log::debug('[PizarraVisualHorario] Error consultando fact_preventas: ' . $e->getMessage());
        }

        // 5. Marcajes Web ERP (fact_visitas en conexión supervisor)
        $visitasWebPorRuta = [];
        try {
            $visitasWeb = DB::connection('supervisor')->table('fact_visitas')
                ->where('fecha_norm', $fechaNorm)
                ->orderBy('check_in', 'asc')
                ->get();

            foreach ($visitasWeb as $vw) {
                $e1 = $this->normalizarTexto((string)($vw->empleado ?? ''));
                $e2 = $this->normalizarTexto((string)($vw->empleado_norm ?? ''));
                $rutaAsignada = $vendedorARuta[$e1] ?? ($vendedorARuta[$e2] ?? null);

                // Si no se asignó por vendedor, cruzar por cliente alfanumérico
                if (!$rutaAsignada) {
                    $alfaCli = $this->normalizarAlfanumerico((string)$vw->cliente);
                    foreach ($rutasObjetivo as $rObj) {
                        if (isset($planClientesPorNombreAlfa[$rObj][$alfaCli])) {
                            $rutaAsignada = $rObj;
                            break;
                        }
                    }
                }

                if ($rutaAsignada && in_array($rutaAsignada, $rutasObjetivo, true)) {
                    $hIn = trim((string)$vw->check_in);
                    if (strlen($hIn) === 5) $hIn .= ':00';
                    $hOut = trim((string)$vw->check_out);
                    if (strlen($hOut) === 5) $hOut .= ':00';

                    $visitasWebPorRuta[$rutaAsignada][] = [
                        'cliente' => (string)$vw->cliente,
                        'cliente_norm' => (string)$vw->cliente_norm,
                        'check_in' => ($hIn !== '' && $hIn !== '00:00:00' && $hIn !== '00:00') ? $hIn : null,
                        'check_out' => ($hOut !== '' && $hOut !== '00:00:00' && $hOut !== '00:00') ? $hOut : null,
                        'tiempo_pdv' => (string)($vw->tiempo_pdv ?: '00:00:00'),
                        'tipo' => (string)$vw->tipo,
                        'motivo' => (string)$vw->motivo,
                        'monto' => (float)($vw->monto ?: 0.0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[PizarraVisualHorario] Error consultando fact_visitas: ' . $e->getMessage());
        }

        // 6. DOBLE CHECK: Marcajes App Móvil (BD alva / conexión mysql)
        // 6.A Inicio de Jornada Laboral en App (user_workdays)
        $workdaysMovilPorRuta = [];
        try {
            $workdaysRaw = DB::connection('mysql')->table('user_workdays as uw')
                ->join('users as u', 'uw.user_id', '=', 'u.id')
                ->whereDate('uw.work_date', $fechaNorm)
                ->whereNotNull('uw.started_at')
                ->select([
                    'uw.id',
                    'uw.user_id',
                    'uw.started_at',
                    'u.username',
                    'u.ruta',
                ])
                ->orderBy('uw.started_at', 'asc')
                ->get();

            foreach ($workdaysRaw as $w) {
                $rUser = strtoupper(trim((string)($w->ruta ?: ($w->username ?? ''))));
                if (in_array($rUser, $rutasObjetivo, true)) {
                    $hStr = null;
                    if ($w->started_at) {
                        $hStr = is_string($w->started_at)
                            ? substr($w->started_at, 11, 8)
                            : ($w->started_at instanceof \DateTimeInterface ? $w->started_at->format('H:i:s') : (string)$w->started_at);
                    }
                    if ($hStr && $hStr !== '00:00:00' && $hStr !== '00:00') {
                        if (!isset($workdaysMovilPorRuta[$rUser]) || strcmp($hStr, $workdaysMovilPorRuta[$rUser]) < 0) {
                            $workdaysMovilPorRuta[$rUser] = $hStr;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[PizarraVisualHorario] Error consultando user_workdays en BD alva: ' . $e->getMessage());
        }

        // 6.B Visitas a Clientes en App Móvil (visitas en BD alva)
        $visitasMovilPorRuta = [];
        try {
            $visitasMovilRaw = DB::connection('mysql')->table('visitas as v')
                ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
                ->whereBetween('v.visited_at', ["{$fechaNorm} 00:00:00", "{$fechaNorm} 23:59:59"])
                ->select([
                    'v.id',
                    'v.client_id',
                    'v.route',
                    'u.ruta as user_ruta',
                    'u.username',
                    'v.status',
                    'v.comments',
                    'v.latitude',
                    'v.longitude',
                    'v.photo_path',
                    'v.visited_at',
                    'v.is_opportunity',
                    'v.opportunity_client_name',
                ])
                ->orderBy('v.visited_at', 'asc')
                ->get();

            foreach ($visitasMovilRaw as $vm) {
                // Asignar ruta: por v.route, por u.ruta/u.username, o por client_id en la cartera del día
                $rUpper = strtoupper(trim((string)($vm->route ?: ($vm->user_ruta ?: ($vm->username ?? '')))));
                $cid = (int)($vm->client_id ?? 0);

                if (!in_array($rUpper, $rutasObjetivo, true) && $cid > 0 && isset($clienteIdARuta[$cid])) {
                    $rUpper = $clienteIdARuta[$cid];
                }

                if (!in_array($rUpper, $rutasObjetivo, true)) {
                    continue;
                }

                $horaStr = null;
                if ($vm->visited_at) {
                    $horaStr = is_string($vm->visited_at)
                        ? substr($vm->visited_at, 11, 8)
                        : ($vm->visited_at instanceof \DateTimeInterface ? $vm->visited_at->format('H:i:s') : (string)$vm->visited_at);
                }

                $visitasMovilPorRuta[$rUpper][] = [
                    'id' => $vm->id,
                    'client_id' => $cid > 0 ? $cid : null,
                    'cliente_nombre' => (string)($vm->opportunity_client_name ?: ''),
                    'status' => (string)($vm->status ?? ''),
                    'comments' => (string)($vm->comments ?? ''),
                    'latitude' => $vm->latitude ? (float)$vm->latitude : null,
                    'longitude' => $vm->longitude ? (float)$vm->longitude : null,
                    'hora' => ($horaStr !== '' && $horaStr !== '00:00:00') ? $horaStr : null,
                    'foto_url' => $this->formatearUrlFoto($vm->photo_path),
                    'is_opportunity' => (bool)$vm->is_opportunity,
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('[PizarraVisualHorario] Error consultando visitas móvil en BD alva: ' . $e->getMessage());
        }

        // 7. Consolidar cronograma de paradas, Doble Check y métricas por ruta
        $rutasHorario = [];
        $segundosLimite = $this->horaASegundos($horaLimite);

        $totalesGlobales = [
            'total_rutas' => count($rutasObjetivo),
            'rutas_puntuales' => 0,
            'rutas_retraso' => 0,
            'rutas_sin_inicio' => 0,
            'puntualidad_pct' => 0.0,
            'puntos_totales' => 0,
            'puntos_maximos' => count($rutasObjetivo) * 10,
            'tiempo_promedio_pdv_seg' => 0,
            'tiempo_promedio_traslado_seg' => 0,
            'total_paradas' => 0,
            'total_pausas_largas' => 0,
            'hora_limite' => substr($horaLimite, 0, 5),
            'auditados_ambos' => 0,
            'salvados_por_app' => 0,
            'salvados_por_web' => 0,
        ];

        $acumPdvSegTotal = 0;
        $acumPdvConteoTotal = 0;
        $acumTrasladoSegTotal = 0;
        $acumTrasladoConteoTotal = 0;

        foreach ($rutasObjetivo as $r) {
            $dim = $catalogoRutas[$r] ?? null;
            $canalRuta = $dim ? (string)$dim->canal : '-';
            $vendedorRuta = $dim ? (string)$dim->vendedor : 'No Asignado';

            $movilList = $visitasMovilPorRuta[$r] ?? [];
            $webList = $visitasWebPorRuta[$r] ?? [];
            $workdayAppInicio = $workdaysMovilPorRuta[$r] ?? null;

            // 7.A DOBLE CHECK A NIVEL DE RUTA:
            // 1. Primer check-in Web ERP
            $primerCheckinWeb = null;
            $primerClienteWeb = null;
            $segundosWeb = null;
            foreach ($webList as $w) {
                if (!empty($w['check_in'])) {
                    $sec = $this->horaASegundos($w['check_in']);
                    if ($sec >= 18000) { // >= 05:00:00
                        if ($segundosWeb === null || $sec < $segundosWeb) {
                            $segundosWeb = $sec;
                            $primerCheckinWeb = $w['check_in'];
                            $primerClienteWeb = $w['cliente'];
                        }
                    }
                }
            }

            // 2. Primer marcaje App Móvil (visitas + user_workdays en BD alva)
            $primerCheckinApp = null;
            $primerClienteApp = null;
            $segundosApp = null;
            $appOrigenDetalle = null;

            // Revisar inicio de jornada en app
            if ($workdayAppInicio) {
                $secWd = $this->horaASegundos($workdayAppInicio);
                if ($secWd >= 18000) {
                    $segundosApp = $secWd;
                    $primerCheckinApp = $workdayAppInicio;
                    $primerClienteApp = 'Inicio de Jornada en App Móvil';
                    $appOrigenDetalle = 'Inicio Jornada';
                }
            }

            // Revisar visitas registradas en la app
            foreach ($movilList as $m) {
                if (!empty($m['hora'])) {
                    $secM = $this->horaASegundos($m['hora']);
                    if ($secM >= 18000) {
                        if ($segundosApp === null || $secM < $segundosApp) {
                            $segundosApp = $secM;
                            $primerCheckinApp = $m['hora'];
                            $cid = $m['client_id'];
                            $pItem = $cid ? ($planPorRutaCliente["{$r}_{$cid}"] ?? null) : null;
                            $primerClienteApp = $pItem ? (string)$pItem->cliente : ($m['cliente_nombre'] ?: ($cid ? "Cliente #{$cid}" : "Primer PDV App"));
                            $appOrigenDetalle = 'Primera Visita';
                        }
                    }
                }
            }

            // 3. Fusión y arbitraje Doble Check
            $primerContacto = null;
            $primerClienteNombre = null;
            $origenPrimerContacto = 'SIN_INICIO';
            $fuenteGanadora = 'NINGUNO';
            $diferenciaMinutos = null;
            $salvadoPorApp = false;
            $salvadoPorWeb = false;

            if ($segundosWeb !== null && $segundosApp !== null) {
                $origenPrimerContacto = 'AMBOS';
                $totalesGlobales['auditados_ambos']++;
                $diferenciaMinutos = (int)round(($segundosWeb - $segundosApp) / 60);

                if ($segundosApp <= $segundosWeb) {
                    $primerContacto = $primerCheckinApp;
                    $primerClienteNombre = $primerClienteApp;
                    $fuenteGanadora = ($segundosApp === $segundosWeb) ? 'AMBOS_IGUAL' : 'APP';
                } else {
                    $primerContacto = $primerCheckinWeb;
                    $primerClienteNombre = $primerClienteWeb;
                    $fuenteGanadora = 'WEB';
                }
            } elseif ($segundosApp !== null) {
                $primerContacto = $primerCheckinApp;
                $primerClienteNombre = $primerClienteApp;
                $origenPrimerContacto = 'SOLO_APP';
                $fuenteGanadora = 'APP';
            } elseif ($segundosWeb !== null) {
                $primerContacto = $primerCheckinWeb;
                $primerClienteNombre = $primerClienteWeb;
                $origenPrimerContacto = 'SOLO_WEB';
                $fuenteGanadora = 'WEB';
            }

            // 4. Evaluación de Puntualidad (Hora Límite 08:10:00 · 10 Puntos)
            $cumpleHorario = false;
            $minutosDesviacion = 0;
            $estatusPuntualidad = 'SIN_INICIO';
            $puntos = 0;
            $auditoriaLabel = 'Sin Marcación';

            if ($primerContacto !== null) {
                $segPrimer = $this->horaASegundos($primerContacto);
                $minutosDesviacion = (int)round(($segPrimer - $segundosLimite) / 60);

                if ($segPrimer <= $segundosLimite) {
                    $cumpleHorario = true;
                    $estatusPuntualidad = 'PUNTUAL';
                    $puntos = 10;
                    $totalesGlobales['rutas_puntuales']++;

                    if ($segundosWeb !== null && $segundosApp !== null) {
                        if ($segundosApp <= $segundosLimite && $segundosWeb <= $segundosLimite) {
                            $auditoriaLabel = 'Puntual en Ambos Sistemas';
                        } elseif ($segundosApp <= $segundosLimite && $segundosWeb > $segundosLimite) {
                            $auditoriaLabel = 'Puntual Validado por App Móvil';
                            $salvadoPorApp = true;
                            $totalesGlobales['salvados_por_app']++;
                        } elseif ($segundosWeb <= $segundosLimite && $segundosApp > $segundosLimite) {
                            $auditoriaLabel = 'Puntual Validado por Web ERP';
                            $salvadoPorWeb = true;
                            $totalesGlobales['salvados_por_web']++;
                        }
                    } elseif ($segundosApp !== null) {
                        $auditoriaLabel = 'Puntual (Solo App Móvil)';
                    } else {
                        $auditoriaLabel = 'Puntual (Solo Web ERP)';
                    }
                } else {
                    $cumpleHorario = false;
                    $estatusPuntualidad = 'RETRASO';
                    $puntos = 0;
                    $totalesGlobales['rutas_retraso']++;
                    $auditoriaLabel = 'Retraso en marcación';
                }
            } else {
                $totalesGlobales['rutas_sin_inicio']++;
            }

            // 7.B FUSIÓN DE PARADAS (GANTT Y TIMELINE PASO A PASO)
            $paradasRuta = [];

            // Indexar visitas web por cliente alfanumérico
            $webPorAlfa = [];
            foreach ($webList as $w) {
                $a = $this->normalizarAlfanumerico($w['cliente']);
                if ($a !== '' && !isset($webPorAlfa[$a])) {
                    $webPorAlfa[$a] = $w;
                }
            }

            // Procesar visitas móviles de la ruta
            $cidsProcesados = [];
            $alfasProcesados = [];
            foreach ($movilList as $m) {
                $cid = $m['client_id'];
                $planItem = $cid ? ($planPorRutaCliente["{$r}_{$cid}"] ?? null) : null;
                $nomCliente = $planItem ? (string)$planItem->cliente : ($m['cliente_nombre'] ?: ($cid ? "Cliente #{$cid}" : "Cliente Ad-hoc"));
                $alfaCli = $this->normalizarAlfanumerico($nomCliente);
                $wItem = $webPorAlfa[$alfaCli] ?? null;

                $montoPrev = $cid ? ($preventasMontoPorClave["{$r}_{$cid}"] ?? 0.0) : 0.0;
                $montoWeb = $wItem ? (float)$wItem['monto'] : 0.0;
                $montoFinal = max($montoPrev, $montoWeb);

                $hApp = $m['hora'];
                $hWeb = $wItem['check_in'] ?? null;

                // Doble check en la parada: hora_in es el mínimo real entre Web y App
                $hIn = null;
                if ($hApp && $hWeb) {
                    $hIn = ($this->horaASegundos($hApp) <= $this->horaASegundos($hWeb)) ? $hApp : $hWeb;
                } else {
                    $hIn = $hApp ?: $hWeb;
                }

                $hOut = $wItem['check_out'] ?? null;
                $tiempoPdv = $wItem ? $wItem['tiempo_pdv'] : null;

                $paradasRuta[] = [
                    'cliente_id' => $cid,
                    'cliente' => $nomCliente,
                    'direccion' => $planItem ? (string)$planItem->direccion : '-',
                    'territorio' => $planItem ? (string)$planItem->territorio : '-',
                    'hora_in' => $hIn,
                    'hora_web' => $hWeb,
                    'hora_app' => $hApp,
                    'hora_out' => $hOut,
                    'tiempo_pdv_raw' => $tiempoPdv,
                    'segundos_pdv' => $this->horaASegundos($tiempoPdv ?: '00:03:00'),
                    'monto' => $montoFinal,
                    'tipo' => $this->determinarTipoVisita($montoFinal, $wItem['tipo'] ?? null, $wItem['motivo'] ?? null, $m['status']),
                    'status' => $m['status'],
                    'foto_url' => $m['foto_url'],
                    'en_plan' => ($planItem !== null),
                    'origen' => $wItem ? 'Web + App' : 'Solo App',
                    'origen_key' => $wItem ? 'AMBOS' : 'APP',
                ];

                if ($cid) $cidsProcesados[$cid] = true;
                if ($alfaCli !== '') $alfasProcesados[$alfaCli] = true;
            }

            // Agregar visitas web que no estuvieron en la app móvil
            foreach ($webList as $w) {
                $alfaCli = $this->normalizarAlfanumerico($w['cliente']);
                if (isset($alfasProcesados[$alfaCli])) {
                    continue; // Ya procesado con la app
                }

                $cidEncontrado = $planClientesPorNombreAlfa[$r][$alfaCli] ?? null;
                if ($cidEncontrado && isset($cidsProcesados[$cidEncontrado])) {
                    continue;
                }

                $planItem = $cidEncontrado ? ($planPorRutaCliente["{$r}_{$cidEncontrado}"] ?? null) : null;
                $montoPrev = $cidEncontrado ? ($preventasMontoPorClave["{$r}_{$cidEncontrado}"] ?? 0.0) : 0.0;
                $montoFinal = max($montoPrev, (float)$w['monto']);

                $paradasRuta[] = [
                    'cliente_id' => $cidEncontrado,
                    'cliente' => $planItem ? (string)$planItem->cliente : (string)$w['cliente'],
                    'direccion' => $planItem ? (string)$planItem->direccion : '-',
                    'territorio' => $planItem ? (string)$planItem->territorio : '-',
                    'hora_in' => $w['check_in'],
                    'hora_web' => $w['check_in'],
                    'hora_app' => null,
                    'hora_out' => $w['check_out'],
                    'tiempo_pdv_raw' => $w['tiempo_pdv'],
                    'segundos_pdv' => $this->horaASegundos($w['tiempo_pdv'] ?: '00:03:00'),
                    'monto' => $montoFinal,
                    'tipo' => $this->determinarTipoVisita($montoFinal, $w['tipo'], $w['motivo'], null),
                    'status' => null,
                    'foto_url' => null,
                    'en_plan' => ($planItem !== null),
                    'origen' => 'Solo Web',
                    'origen_key' => 'WEB',
                ];
            }

            // Filtrar y ordenar cronológicamente las paradas con hora_in válida
            $paradasValidas = array_filter($paradasRuta, fn($p) => !empty($p['hora_in']));
            usort($paradasValidas, fn($a, $b) => strcmp((string)$a['hora_in'], (string)$b['hora_in']));
            $paradasValidas = array_values($paradasValidas);

            // Calcular tiempos de traslado y pausas entre paradas consecutivas
            $prevOutSegundos = null;
            $acumPdvRuta = 0;
            $acumTrasladoRuta = 0;
            $conteoTrasladosRuta = 0;
            $pausasLargasRuta = 0;

            foreach ($paradasValidas as $idx => &$pv) {
                $pv['parada_num'] = $idx + 1;
                $segIn = $this->horaASegundos($pv['hora_in']);

                // Tiempo en PDV
                $segPdv = $pv['segundos_pdv'];
                if ($segPdv <= 0) {
                    $segPdv = 180; // default 3 min si no se registró check-out
                }
                $pv['tiempo_pdv_min'] = round($segPdv / 60, 1);
                $pv['tiempo_pdv_fmt'] = $this->segundosAFormato($segPdv);
                $acumPdvRuta += $segPdv;

                // Tiempo de traslado desde la parada anterior
                if ($prevOutSegundos !== null) {
                    $diffTraslado = max(0, $segIn - $prevOutSegundos);
                    $trasladoMin = round($diffTraslado / 60, 1);
                    $pv['traslado_prev_min'] = $trasladoMin;
                    $pv['traslado_prev_fmt'] = $this->segundosAFormato($diffTraslado);
                    $pv['es_pausa_larga'] = ($trasladoMin >= 30.0);
                    if ($pv['es_pausa_larga']) {
                        $pausasLargasRuta++;
                    }
                    $acumTrasladoRuta += $diffTraslado;
                    $conteoTrasladosRuta++;
                } else {
                    $pv['traslado_prev_min'] = 0;
                    $pv['traslado_prev_fmt'] = 'Inicio';
                    $pv['es_pausa_larga'] = false;
                }

                // Salida estimada o registrada
                $segOut = $pv['hora_out'] ? $this->horaASegundos($pv['hora_out']) : ($segIn + $segPdv);
                $prevOutSegundos = max($segIn, $segOut);
            }
            unset($pv);

            $ultimoContacto = !empty($paradasValidas) ? $paradasValidas[count($paradasValidas) - 1]['hora_in'] : null;

            $duracionJornadaMin = 0;
            if ($primerContacto && $ultimoContacto) {
                $duracionJornadaMin = (int)max(0, round(($this->horaASegundos($ultimoContacto) - $this->horaASegundos($primerContacto)) / 60));
            }

            $promPdvRuta = count($paradasValidas) > 0 ? round(($acumPdvRuta / count($paradasValidas)) / 60, 1) : 0.0;
            $promTrasladoRuta = $conteoTrasladosRuta > 0 ? round(($acumTrasladoRuta / $conteoTrasladosRuta) / 60, 1) : 0.0;

            $acumPdvSegTotal += $acumPdvRuta;
            $acumPdvConteoTotal += count($paradasValidas);
            $acumTrasladoSegTotal += $acumTrasladoRuta;
            $acumTrasladoConteoTotal += $conteoTrasladosRuta;
            $totalesGlobales['total_paradas'] += count($paradasValidas);
            $totalesGlobales['total_pausas_largas'] += $pausasLargasRuta;
            $totalesGlobales['puntos_totales'] += $puntos;

            $rutasHorario[] = [
                'ruta' => $r,
                'canal' => $canalRuta,
                'vendedor' => $vendedorRuta,
                'color' => $coloresRuta[$r],
                'primer_contacto' => $primerContacto ? substr($primerContacto, 0, 5) : null,
                'primer_contacto_completo' => $primerContacto,
                'primer_cliente' => $primerClienteNombre ?: (!empty($paradasValidas) ? $paradasValidas[0]['cliente'] : null),
                'ultimo_contacto' => $ultimoContacto ? substr($ultimoContacto, 0, 5) : null,
                'duracion_jornada_min' => $duracionJornadaMin,
                'duracion_jornada_fmt' => $this->minutosAHorasFmt($duracionJornadaMin),
                'cumple' => $cumpleHorario,
                'estatus' => $estatusPuntualidad,
                'desviacion_min' => $minutosDesviacion,
                'puntos' => $puntos,
                'total_paradas' => count($paradasValidas),
                'promedio_pdv_min' => $promPdvRuta,
                'promedio_traslado_min' => $promTrasladoRuta,
                'pausas_largas' => $pausasLargasRuta,
                'doble_check' => [
                    'origen' => $origenPrimerContacto, // 'AMBOS', 'SOLO_APP', 'SOLO_WEB', 'SIN_INICIO'
                    'auditoria_label' => $auditoriaLabel,
                    'fuente_ganadora' => $fuenteGanadora, // 'APP', 'WEB', 'AMBOS_IGUAL', 'NINGUNO'
                    'hora_web' => $primerCheckinWeb ? substr($primerCheckinWeb, 0, 5) : null,
                    'hora_web_completa' => $primerCheckinWeb,
                    'hora_app' => $primerCheckinApp ? substr($primerCheckinApp, 0, 5) : null,
                    'hora_app_completa' => $primerCheckinApp,
                    'hora_workday_app' => $workdayAppInicio ? substr($workdayAppInicio, 0, 5) : null,
                    'hora_visita_app' => $primerCheckinApp && $appOrigenDetalle === 'Primera Visita' ? substr($primerCheckinApp, 0, 5) : null,
                    'app_origen_detalle' => $appOrigenDetalle,
                    'diferencia_min' => $diferenciaMinutos,
                    'salvado_por_app' => $salvadoPorApp,
                    'salvado_por_web' => $salvadoPorWeb,
                ],
                'paradas' => $paradasValidas,
            ];
        }

        // Ordenar rutas: primero evaluadas por hora de inicio ascendente, luego sin inicio
        usort($rutasHorario, function ($a, $b) {
            $hA = $a['primer_contacto_completo'] ?? '99:99:99';
            $hB = $b['primer_contacto_completo'] ?? '99:99:99';
            return strcmp($hA, $hB);
        });

        // Totales consolidados
        $totalR = count($rutasObjetivo);
        $totalesGlobales['puntualidad_pct'] = $totalR > 0
            ? round(($totalesGlobales['rutas_puntuales'] / $totalR) * 100, 1)
            : 0.0;
        $totalesGlobales['tiempo_promedio_pdv_seg'] = $acumPdvConteoTotal > 0
            ? round($acumPdvSegTotal / $acumPdvConteoTotal)
            : 0;
        $totalesGlobales['tiempo_promedio_pdv_min'] = round($totalesGlobales['tiempo_promedio_pdv_seg'] / 60, 1);
        $totalesGlobales['tiempo_promedio_traslado_seg'] = $acumTrasladoConteoTotal > 0
            ? round($acumTrasladoSegTotal / $acumTrasladoConteoTotal)
            : 0;
        $totalesGlobales['tiempo_promedio_traslado_min'] = round($totalesGlobales['tiempo_promedio_traslado_seg'] / 60, 1);

        return [
            'fecha' => $fechaNorm,
            'hora_limite' => substr($horaLimite, 0, 5),
            'totales' => $totalesGlobales,
            'rutas' => $rutasHorario,
        ];
    }

    private function horaASegundos(?string $hora): int
    {
        if (!$hora) return 0;
        $partes = explode(':', trim($hora));
        $h = (int)($partes[0] ?? 0);
        $m = (int)($partes[1] ?? 0);
        $s = (int)($partes[2] ?? 0);
        return ($h * 3600) + ($m * 60) + $s;
    }

    private function segundosAFormato(int|float $segundos): string
    {
        $segundosInt = (int)$segundos;
        $m = (int)floor($segundosInt / 60);
        $s = $segundosInt % 60;
        if ($m >= 60) {
            $h = (int)floor($m / 60);
            $mRest = $m % 60;
            return "{$h}h {$mRest}m";
        }
        return "{$m}m " . str_pad((string)$s, 2, '0', STR_PAD_LEFT) . "s";
    }

    private function minutosAHorasFmt(int|float $minutos): string
    {
        $minutosInt = (int)$minutos;
        if ($minutosInt <= 0) return '0 min';
        $h = (int)floor($minutosInt / 60);
        $m = $minutosInt % 60;
        if ($h > 0) {
            return "{$h}h {$m}m";
        }
        return "{$m} min";
    }

    private function formatearUrlFoto(?string $path): ?string
    {
        if (!$path) return null;
        $clean = ltrim(str_replace('\\', '/', $path), '/');
        if ($clean === '') return null;
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $clean;
        }
        if (str_starts_with($clean, 'storage/')) {
            return '/' . $clean;
        }
        return '/storage/' . $clean;
    }

    private function normalizarTexto(string $str): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $str) ?? ''));
    }

    private function normalizarAlfanumerico(?string $str): string
    {
        if (!$str) return '';
        $s = $this->normalizarTexto($str);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    private function determinarTipoVisita(
        float $monto,
        ?string $webTipo,
        ?string $webMotivo,
        ?string $appStatus
    ): string {
        if ($monto > 0) {
            return 'PreVenta';
        }

        $wTipo = strtoupper(trim((string)$webTipo));
        $wMotivo = strtoupper(trim((string)$webMotivo));
        $aStatus = strtoupper(trim((string)$appStatus));

        if ($wTipo === 'PREVENTA' || $aStatus === 'PREVENTA') {
            return 'PreVenta';
        }

        $cands = array_filter([$wMotivo, $aStatus, $wTipo]);
        foreach ($cands as $c) {
            if (str_contains($c, 'CERRAD')) return 'Tienda Cerrada';
            if (str_contains($c, 'DINERO')) return 'Sin Dinero';
            if (str_contains($c, 'STOCK')) return 'Con Stock';
            if (str_contains($c, 'AUSENTE') || str_contains($c, 'NO ESTA')) return 'Ausente';
            if (str_contains($c, 'RECHAZ')) return 'Rechazado';
            if (str_contains($c, 'COBRANZA')) return 'Cobranza';
        }

        return 'Visita';
    }

    private function generarRespuestaVacia(string $fecha, string $horaLimite): array
    {
        return [
            'fecha' => $fecha,
            'hora_limite' => substr($horaLimite, 0, 5),
            'totales' => [
                'total_rutas' => 0,
                'rutas_puntuales' => 0,
                'rutas_retraso' => 0,
                'rutas_sin_inicio' => 0,
                'puntualidad_pct' => 0.0,
                'puntos_totales' => 0,
                'puntos_maximos' => 0,
                'tiempo_promedio_pdv_seg' => 0,
                'tiempo_promedio_pdv_min' => 0.0,
                'tiempo_promedio_traslado_seg' => 0,
                'tiempo_promedio_traslado_min' => 0.0,
                'total_paradas' => 0,
                'total_pausas_largas' => 0,
                'hora_limite' => substr($horaLimite, 0, 5),
                'auditados_ambos' => 0,
                'salvados_por_app' => 0,
                'salvados_por_web' => 0,
            ],
            'rutas' => [],
        ];
    }
}
