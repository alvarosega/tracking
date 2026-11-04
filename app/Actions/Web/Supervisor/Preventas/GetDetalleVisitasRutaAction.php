<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleVisitasRutaAction
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

    /**
     * Obtiene el desglose auditado de cobertura de cartera por cliente para una ruta.
     * En la app y en la web son los mismos clientes, todos extraídos de pan_ruteo.
     * El cruce se realiza estrictamente por CLIENTE y se agrupa por RUTA (sin tomar en cuenta el vendedor).
     *
     * @param string $ruta
     * @param string|null $fecha
     * @param bool $estricto
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null, bool $estricto = false): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes', 'Lunes'];
        $diasFiltro = array_values(array_unique(array_merge($tuplaDias, array_map('strtolower', $tuplaDias))));
        $rutaClean = strtoupper(trim($ruta));

        try {
            // 1. Cartera de la ruta para el día según pan_ruteo (Plan de hoy para la ruta)
            $carteraTotalRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($diasFiltro): void {
                    $q->whereIn('dia_norm', $diasFiltro)
                      ->orWhereIn('dia', $diasFiltro);
                })
                ->where('cliente_id', '>', 0)
                ->whereRaw("TRIM(UPPER(COALESCE(estado, ''))) = 'ACTIVO'")
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), NULLIF(TRIM(cliente_norm), ''), CONCAT('Cliente #', cliente_id))) as cliente"),
                    DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente_norm), ''), NULLIF(TRIM(cliente), ''), CONCAT('Cliente #', cliente_id))) as cliente_norm"),
                    DB::raw("MAX(COALESCE(TRIM(estado), 'ACTIVO')) as estado"),
                    DB::raw("MAX(COALESCE(TRIM(territorio), '-')) as zona_venta"),
                    DB::raw("MAX(COALESCE(TRIM(territorio), '-')) as territorio"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
                    DB::raw("MAX(COALESCE(TRIM(referencia), '-')) as referencia"),
                    DB::raw("MAX(latitud) as latitud"),
                    DB::raw("MAX(longitud) as longitud"),
                    DB::raw("MAX(vendedor) as vendedor"),
                ])
                ->groupBy('cliente_id')
                ->get()
                ->keyBy('cliente_id');

            // Catálogo auxiliar de todos los clientes de la ruta para nombres fuera de plan (todos los días)
            $otrosClientesRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
                ->whereRaw("TRIM(UPPER(COALESCE(estado, ''))) = 'ACTIVO'")
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), NULLIF(TRIM(cliente_norm), ''), CONCAT('Cliente #', cliente_id))) as cliente"),
                    DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente_norm), ''), NULLIF(TRIM(cliente), ''), CONCAT('Cliente #', cliente_id))) as cliente_norm"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '')) as direccion"),
                    DB::raw("MAX(COALESCE(TRIM(estado), 'ACTIVO')) as estado"),
                ])
                ->groupBy('cliente_id')
                ->get()
                ->keyBy('cliente_id');

            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();
            $vendedor = $dimInfo && !empty($dimInfo->vendedor)
                ? trim((string) $dimInfo->vendedor)
                : trim((string) ($carteraTotalRuta->firstWhere('vendedor', '!=', null)->vendedor ?? 'No Asignado'));

            // 2. Diccionarios de Cruce por Cliente basados en el Plan del Día y en Cartera Total
            $planIdPorNombreNorm = [];
            $planIdPorNombreAlfa = [];

            foreach ($carteraTotalRuta as $cid => $c) {
                $cidInt = (int) $cid;
                $nom = trim((string) $c->cliente);
                $nomNorm = trim((string) $c->cliente_norm);

                if ($nom !== '') {
                    $planIdPorNombreNorm[$this->normTxt($nom)] = $cidInt;
                    $planIdPorNombreAlfa[$this->normalizarAlfanumerico($nom)] = $cidInt;
                }
                if ($nomNorm !== '') {
                    $planIdPorNombreNorm[$this->normTxt($nomNorm)] = $cidInt;
                    $planIdPorNombreAlfa[$this->normalizarAlfanumerico($nomNorm)] = $cidInt;
                }
            }

            // Diccionario de todos los clientes de esta ruta en pan_ruteo (para identificar visitas fuera de plan)
            $todosClientesNorm = [];
            $todosClientesAlfa = [];
            foreach ($otrosClientesRuta as $cid => $c) {
                $cidInt = (int) $cid;
                $nom = trim((string) $c->cliente);
                $nomNorm = trim((string) $c->cliente_norm);

                if ($nom !== '') {
                    $todosClientesNorm[$this->normTxt($nom)] = $cidInt;
                    $todosClientesAlfa[$this->normalizarAlfanumerico($nom)] = $cidInt;
                }
                if ($nomNorm !== '') {
                    $todosClientesNorm[$this->normTxt($nomNorm)] = $cidInt;
                    $todosClientesAlfa[$this->normalizarAlfanumerico($nomNorm)] = $cidInt;
                }
            }

            // 3. Preventas del día para montos oficiales
            $preventasMontoPorCid = [];
            try {
                $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                    ->where('fecha_norm', $fechaNorm)
                    ->where('cliente_id', '>', 0)
                    ->select('cliente_id', DB::raw("SUM(monto_final) as total_monto"))
                    ->groupBy('cliente_id')
                    ->get();

                foreach ($preventasHoy as $ph) {
                    $preventasMontoPorCid[(int) $ph->cliente_id] = (float) $ph->total_monto;
                }
            } catch (\Throwable $e) {
                Log::debug("Error consultando preventas: " . $e->getMessage());
            }

            // 4. Marcajes del sistema central (fact_visitas - Web ERP)
            // Se cruzan ÚNICAMENTE contra los clientes del plan del día de esta ruta (sin filtrar por empleado)
            $visitasSistema = DB::connection('supervisor')->table('fact_visitas as fv')
                ->where('fv.fecha_norm', $fechaNorm)
                ->select([
                    DB::raw("COALESCE(fv.cliente, 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(fv.cliente_norm, '') as cliente_norm"),
                    DB::raw("COALESCE(fv.tipo, '') as tipo"),
                    DB::raw("COALESCE(fv.motivo, '') as motivo"),
                    DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                    DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
                    DB::raw("COALESCE(fv.tiempo_pdv, '00:00:00') as tiempo_pdv"),
                    DB::raw("CAST(COALESCE(fv.monto, 0.0) AS DECIMAL(12, 2)) as monto"),
                ])
                ->orderBy('fv.check_in', 'asc')
                ->get();

            $visitasWebPorCid = [];
            $factVisitasPorNombreAlfa = [];
            $visitasFueraPlan = [];

            foreach ($visitasSistema as $fs) {
                $n1 = $this->normTxt((string) $fs->cliente);
                $n2 = $this->normTxt((string) $fs->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $fs->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $fs->cliente_norm);

                $hIn = trim((string) $fs->check_in);
                if (strlen($hIn) === 5) {
                    $hIn .= ':00';
                }
                $hInValido = ($hIn !== '' && $hIn !== '00:00:00' && $hIn !== '00:00');

                $tipoV = trim((string) $fs->tipo);
                $motivoV = trim((string) $fs->motivo);

                $itemWeb = [
                    'check_in' => $hInValido ? $hIn : null,
                    'check_out' => trim((string) $fs->check_out) ?: null,
                    'tiempo_pdv' => trim((string) $fs->tiempo_pdv) ?: null,
                    'tipo' => $tipoV,
                    'motivo' => $motivoV,
                    'monto' => (float) ($fs->monto ?: 0.0),
                    'visitas_web_count' => 1,
                    'marcajes_web' => $hInValido ? [$hIn] : [],
                ];

                foreach (array_unique(array_filter([$a1, $a2])) as $alfaKey) {
                    if (!isset($factVisitasPorNombreAlfa[$alfaKey])) {
                        $factVisitasPorNombreAlfa[$alfaKey] = $itemWeb;
                    } else {
                        $factVisitasPorNombreAlfa[$alfaKey]['visitas_web_count']++;
                        if ($hInValido) {
                            $factVisitasPorNombreAlfa[$alfaKey]['marcajes_web'][] = $hIn;
                            if (empty($factVisitasPorNombreAlfa[$alfaKey]['check_in']) || strcmp($hIn, $factVisitasPorNombreAlfa[$alfaKey]['check_in']) < 0) {
                                $factVisitasPorNombreAlfa[$alfaKey]['check_in'] = $hIn;
                            }
                        }
                        $factVisitasPorNombreAlfa[$alfaKey]['monto'] += (float) ($fs->monto ?: 0.0);
                    }
                }

                $cid = $planIdPorNombreNorm[$n1]
                    ?? $planIdPorNombreNorm[$n2]
                    ?? $planIdPorNombreAlfa[$a1]
                    ?? $planIdPorNombreAlfa[$a2]
                    ?? null;

                if ($cid) {
                    if (!isset($visitasWebPorCid[$cid])) {
                        $visitasWebPorCid[$cid] = $itemWeb;
                    } else {
                        $visitasWebPorCid[$cid]['visitas_web_count']++;
                        if ($hInValido) {
                            $visitasWebPorCid[$cid]['marcajes_web'][] = $hIn;
                            if (empty($visitasWebPorCid[$cid]['check_in']) || strcmp($hIn, $visitasWebPorCid[$cid]['check_in']) < 0) {
                                $visitasWebPorCid[$cid]['check_in'] = $hIn;
                            }
                        }
                        $visitasWebPorCid[$cid]['monto'] += (float) ($fs->monto ?: 0.0);
                        if (!empty($itemWeb['motivo']) && empty($visitasWebPorCid[$cid]['motivo'])) {
                            $visitasWebPorCid[$cid]['motivo'] = $itemWeb['motivo'];
                        }
                        if (strtoupper($itemWeb['tipo']) === 'PREVENTA') {
                            $visitasWebPorCid[$cid]['tipo'] = 'PreVenta';
                        }
                    }
                } else {
                    // ¿Es un cliente de esta ruta pero de otro día en pan_ruteo (visita fuera del plan de hoy)?
                    $cidOtro = $todosClientesNorm[$n1]
                        ?? $todosClientesNorm[$n2]
                        ?? $todosClientesAlfa[$a1]
                        ?? $todosClientesAlfa[$a2]
                        ?? null;

                    if ($cidOtro) {
                        $pOtro = $otrosClientesRuta[$cidOtro] ?? null;
                        $fpKey = "cid_{$cidOtro}";

                        if (!isset($visitasFueraPlan[$fpKey])) {
                            $visitasFueraPlan[$fpKey] = [
                                'cliente_id' => $cidOtro,
                                'cliente' => $pOtro ? trim((string) $pOtro->cliente) : trim((string) $fs->cliente),
                                'check_in' => $itemWeb['check_in'],
                                'hora_app' => null,
                                'visitas_web_count' => 1,
                                'visitas_app_count' => 0,
                                'marcajes_web' => $itemWeb['marcajes_web'],
                                'marcajes_app' => [],
                                'tiempo_pdv' => $itemWeb['tiempo_pdv'],
                                'monto' => $itemWeb['monto'],
                                'tipo' => $itemWeb['tipo'] ?: 'Visita',
                                'motivo' => $itemWeb['motivo'] ?: 'Visita',
                                'foto_url' => null,
                                'direccion' => $pOtro ? trim((string) ($pOtro->direccion ?? '')) : '-',
                                'en_fact_visitas' => true,
                                'en_visitas_app' => false,
                                'comments_app' => null,
                                'status_app' => null,
                                'es_inactivo' => false,
                            ];
                        } else {
                            $visitasFueraPlan[$fpKey]['visitas_web_count']++;
                            if ($hInValido) {
                                $visitasFueraPlan[$fpKey]['marcajes_web'][] = $hIn;
                                if (empty($visitasFueraPlan[$fpKey]['check_in']) || strcmp($hIn, $visitasFueraPlan[$fpKey]['check_in']) < 0) {
                                    $visitasFueraPlan[$fpKey]['check_in'] = $hIn;
                                }
                            }
                            $visitasFueraPlan[$fpKey]['monto'] += $itemWeb['monto'];
                            $visitasFueraPlan[$fpKey]['en_fact_visitas'] = true;
                        }
                    }
                }
            }

            // 5. Visitas de la App Móvil (visitas)
            $cidsPlan = array_map('intval', array_keys($carteraTotalRuta->toArray()));
            $filasMovil = Visita::query()
                ->whereBetween('visited_at', ["{$fechaNorm} 00:00:00", "{$fechaNorm} 23:59:59"])
                ->where(function ($q) use ($rutaClean, $cidsPlan): void {
                    $q->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean]);
                    if (!empty($cidsPlan)) {
                        $q->orWhereIn('client_id', $cidsPlan);
                    }
                })
                ->orderBy('visited_at', 'asc')
                ->get();

            $visitasMovilPorCid = [];

            foreach ($filasMovil as $fm) {
                $cid = (int) ($fm->client_id ?? 0);
                if ($cid <= 0 && !empty($fm->opportunity_client_name)) {
                    $a = $this->normalizarAlfanumerico((string) $fm->opportunity_client_name);
                    $cid = $planIdPorNombreAlfa[$a] ?? 0;
                }

                $horaM = $fm->visited_at ? $fm->visited_at->format('H:i:s') : null;
                if ($horaM === '00:00:00' || $horaM === '00:00') {
                    $horaM = null;
                }
                $fotoUrl = null;
                $fotoRel = ltrim(str_replace('\\', '/', (string) $fm->photo_path), '/');
                if ($fotoRel !== '') {
                    if (str_starts_with($fotoRel, 'http://') || str_starts_with($fotoRel, 'https://')) {
                        $fotoUrl = $fotoRel;
                    } elseif (str_starts_with($fotoRel, 'storage/')) {
                        $fotoUrl = '/' . $fotoRel;
                    } else {
                        $fotoUrl = '/storage/' . $fotoRel;
                    }
                }

                if ($cid > 0 && isset($carteraTotalRuta[$cid])) {
                    if (!isset($visitasMovilPorCid[$cid])) {
                        $visitasMovilPorCid[$cid] = [
                            'hora_app' => $horaM,
                            'visitas_app_count' => 1,
                            'marcajes_app' => $horaM ? [$horaM] : [],
                            'foto_url' => $fotoUrl,
                            'status_app' => (string) ($fm->status ?? ''),
                            'comments_app' => (string) ($fm->comments ?? ''),
                        ];
                    } else {
                        $visitasMovilPorCid[$cid]['visitas_app_count']++;
                        if ($horaM) {
                            $visitasMovilPorCid[$cid]['marcajes_app'][] = $horaM;
                            if (empty($visitasMovilPorCid[$cid]['hora_app']) || strcmp($horaM, $visitasMovilPorCid[$cid]['hora_app']) < 0) {
                                $visitasMovilPorCid[$cid]['hora_app'] = $horaM;
                            }
                        }
                        if ($fotoUrl && empty($visitasMovilPorCid[$cid]['foto_url'])) {
                            $visitasMovilPorCid[$cid]['foto_url'] = $fotoUrl;
                        }
                        if (!empty($fm->status) && empty($visitasMovilPorCid[$cid]['status_app'])) {
                            $visitasMovilPorCid[$cid]['status_app'] = (string) $fm->status;
                        }
                    }
                } else {
                    $fpKey = $cid > 0 ? "cid_{$cid}" : 'opp_' . md5(trim((string) $fm->opportunity_client_name) ?: (string) $fm->id);

                    if (!isset($visitasFueraPlan[$fpKey])) {
                        $pOtro = $cid > 0 ? ($otrosClientesRuta[$cid] ?? null) : null;
                        $nombreFp = $pOtro
                            ? trim((string) $pOtro->cliente)
                            : trim((string) ($fm->opportunity_client_name ?: ($cid > 0 ? "Cliente #{$cid}" : "Cliente Oportunidad")));

                        $direccionFp = $pOtro
                            ? trim((string) ($pOtro->direccion ?? ''))
                            : '-';

                        $stFp = $pOtro ? strtoupper(trim((string) ($pOtro->estado ?: 'ACTIVO'))) : 'ACTIVO';
                        $esInactivo = $pOtro && !in_array($stFp, ['ACTIVO', 'ACTIVA', 'VIGENTE'], true);

                        $alfaFp = $this->normalizarAlfanumerico($nombreFp);
                        $webFp = $factVisitasPorNombreAlfa[$alfaFp] ?? null;
                        $montoFp = max((float) ($webFp['monto'] ?? 0.0), (float) ($preventasMontoPorCid[$cid] ?? 0.0));
                        $tipoFp = $this->determinarTipoVisita(
                            true,
                            $montoFp,
                            $webFp['tipo'] ?? '',
                            $webFp['motivo'] ?? '',
                            (string) ($fm->status ?? ''),
                            (bool) $fm->is_opportunity
                        );

                        $visitasFueraPlan[$fpKey] = [
                            'cliente_id' => $cid > 0 ? $cid : null,
                            'cliente' => $nombreFp,
                            'check_in' => $webFp['check_in'] ?? null,
                            'hora_app' => $horaM,
                            'visitas_web_count' => $webFp['visitas_web_count'] ?? ($webFp ? 1 : 0),
                            'visitas_app_count' => 1,
                            'marcajes_web' => $webFp['marcajes_web'] ?? ($webFp['check_in'] ? [$webFp['check_in']] : []),
                            'marcajes_app' => $horaM ? [$horaM] : [],
                            'tiempo_pdv' => $webFp['tiempo_pdv'] ?? null,
                            'monto' => $montoFp,
                            'tipo' => $tipoFp,
                            'motivo' => $tipoFp,
                            'foto_url' => $fotoUrl,
                            'direccion' => $direccionFp,
                            'en_fact_visitas' => $webFp !== null,
                            'en_visitas_app' => true,
                            'comments_app' => (string) ($fm->comments ?? ''),
                            'status_app' => (string) ($fm->status ?? ''),
                            'es_inactivo' => $esInactivo,
                        ];
                    } else {
                        $visitasFueraPlan[$fpKey]['visitas_app_count']++;
                        if ($horaM) {
                            $visitasFueraPlan[$fpKey]['marcajes_app'][] = $horaM;
                            if (empty($visitasFueraPlan[$fpKey]['hora_app']) || strcmp($horaM, $visitasFueraPlan[$fpKey]['hora_app']) < 0) {
                                $visitasFueraPlan[$fpKey]['hora_app'] = $horaM;
                            }
                        }
                        if ($fotoUrl && empty($visitasFueraPlan[$fpKey]['foto_url'])) {
                            $visitasFueraPlan[$fpKey]['foto_url'] = $fotoUrl;
                        }
                    }
                }
            }

            // 6. Clasificación Auditada de Cartera (Activos, Inactivos, Visitados, No Visitados)
            $visitadosActivos = [];
            $noVisitadosActivos = [];
            $fueraPlan = [];

            $totalPlanActivo = count($carteraTotalRuta);

            foreach ($carteraTotalRuta as $cid => $p) {
                $cidInt = (int) $cid;
                $st = strtoupper(trim((string) ($p->estado ?: 'ACTIVO')));

                $web = $visitasWebPorCid[$cidInt] ?? null;
                $app = $visitasMovilPorCid[$cidInt] ?? null;
                $montoPrev = $preventasMontoPorCid[$cidInt] ?? 0.0;

                $enFactVisitas = ($web !== null);
                $enVisitasApp = ($app !== null);
                $visitado = $enFactVisitas; // Fuente oficial: fact_visitas

                $montoFinal = max((float) ($web['monto'] ?? 0.0), $montoPrev);
                $hWeb = $web['check_in'] ?? null;
                if ($hWeb === '00:00:00' || $hWeb === '00:00' || empty($hWeb)) {
                    $hWeb = null;
                }
                $hApp = $app['hora_app'] ?? null;
                if ($hApp === '00:00:00' || $hApp === '00:00' || empty($hApp)) {
                    $hApp = null;
                }

                $diffInfo = $this->calcularDiferencia($hWeb, $hApp);

                $tipoFinal = $this->determinarTipoVisita(
                    $visitado,
                    $montoFinal,
                    $web['tipo'] ?? '',
                    $web['motivo'] ?? '',
                    $app['status_app'] ?? '',
                    false
                );
                $motivoFinal = $tipoFinal;

                $visitasWebCount = (int) ($web['visitas_web_count'] ?? ($enFactVisitas ? 1 : 0));
                $visitasAppCount = (int) ($app['visitas_app_count'] ?? ($enVisitasApp ? 1 : 0));
                $marcajesWeb = array_values(array_unique($web['marcajes_web'] ?? ($hWeb ? [$hWeb] : [])));
                $marcajesApp = array_values(array_unique($app['marcajes_app'] ?? ($hApp ? [$hApp] : [])));
                sort($marcajesWeb);
                sort($marcajesApp);

                $hOrden = '99:99:99';
                if ($hWeb && $hApp) {
                    $hOrden = strcmp($hWeb, $hApp) < 0 ? $hWeb : $hApp;
                } elseif ($hWeb) {
                    $hOrden = $hWeb;
                } elseif ($hApp) {
                    $hOrden = $hApp;
                }

                $cRow = [
                    'cliente_id' => $cidInt,
                    'cliente' => trim((string) $p->cliente) ?: "Cliente #{$cidInt}",
                    'zona_venta' => $p->zona_venta ?: '-',
                    'direccion' => $p->direccion ?: '-',
                    'referencia' => $p->referencia ?: '-',
                    'latitud' => $p->latitud ? (float) $p->latitud : null,
                    'longitud' => $p->longitud ? (float) $p->longitud : null,
                    'check_in' => $hWeb,
                    'hora_app' => $hApp,
                    'hora_orden' => $hOrden,
                    'visitas_count' => max($visitasWebCount, $visitasAppCount),
                    'visitas_web_count' => $visitasWebCount,
                    'visitas_app_count' => $visitasAppCount,
                    'marcajes_web' => $marcajesWeb,
                    'marcajes_app' => $marcajesApp,
                    'diferencia_segundos' => $diffInfo['segundos'],
                    'diferencia_texto' => $diffInfo['texto'],
                    'tiempo_pdv' => $web['tiempo_pdv'] ?? null,
                    'monto' => $montoFinal,
                    'tipo' => $tipoFinal,
                    'motivo' => $motivoFinal,
                    'foto_url' => $app['foto_url'] ?? null,
                    'en_fact_visitas' => $enFactVisitas,
                    'en_visitas_app' => $enVisitasApp,
                    'comments_app' => $app['comments_app'] ?? null,
                    'status_app' => $app['status_app'] ?? null,
                    'estado_ruteo' => $st,
                ];

                $pasaCriterio = $visitado;
                if ($pasaCriterio) {
                    $visitadosActivos[] = $cRow;
                } else {
                    $noVisitadosActivos[] = $cRow;
                }
            }

            // Orden Cronológico de Visitados Activos (por hora de visita ascendente)
            usort($visitadosActivos, function ($a, $b) {
                $cmp = strcmp((string) ($a['hora_orden'] ?? '99:99:99'), (string) ($b['hora_orden'] ?? '99:99:99'));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp((string) ($a['cliente'] ?? ''), (string) ($b['cliente'] ?? ''));
            });

            foreach ($visitadosActivos as $idx => &$va) {
                $va['orden'] = $idx + 1;
            }
            unset($va);

            // Orden de no visitados por nombre de cliente
            usort($noVisitadosActivos, fn ($a, $b) => strcmp((string) ($a['cliente'] ?? ''), (string) ($b['cliente'] ?? '')));

            // Visitas fuera de plan
            foreach ($visitasFueraPlan as &$fp) {
                $hWeb = $fp['check_in'] ?? null;
                if ($hWeb === '00:00:00' || $hWeb === '00:00' || empty($hWeb)) {
                    $hWeb = null;
                }
                $hApp = $fp['hora_app'] ?? null;
                if ($hApp === '00:00:00' || $hApp === '00:00' || empty($hApp)) {
                    $hApp = null;
                }
                $hOrden = '99:99:99';
                if ($hWeb && $hApp) {
                    $hOrden = strcmp($hWeb, $hApp) < 0 ? $hWeb : $hApp;
                } elseif ($hWeb) {
                    $hOrden = $hWeb;
                } elseif ($hApp) {
                    $hOrden = $hApp;
                }
                $fp['hora_orden'] = $hOrden;

                $visitasWebCount = (int) ($fp['visitas_web_count'] ?? ($hWeb !== null ? 1 : 0));
                $visitasAppCount = (int) ($fp['visitas_app_count'] ?? ($hApp !== null ? 1 : 0));
                $marcajesWeb = array_values(array_unique($fp['marcajes_web'] ?? ($hWeb ? [$hWeb] : [])));
                $marcajesApp = array_values(array_unique($fp['marcajes_app'] ?? ($hApp ? [$hApp] : [])));
                sort($marcajesWeb);
                sort($marcajesApp);

                $fp['visitas_count'] = max($visitasWebCount, $visitasAppCount);
                $fp['visitas_web_count'] = $visitasWebCount;
                $fp['visitas_app_count'] = $visitasAppCount;
                $fp['marcajes_web'] = $marcajesWeb;
                $fp['marcajes_app'] = $marcajesApp;

                $diffInfo = $this->calcularDiferencia($fp['check_in'], $fp['hora_app']);
                $fp['diferencia_segundos'] = $diffInfo['segundos'];
                $fp['diferencia_texto'] = $diffInfo['texto'];
                $fueraPlan[] = $fp;
            }
            unset($fp);

            // Orden Cronológico de Fuera de Plan
            usort($fueraPlan, function ($a, $b) {
                $cmp = strcmp((string) ($a['hora_orden'] ?? '99:99:99'), (string) ($b['hora_orden'] ?? '99:99:99'));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp((string) ($a['cliente'] ?? ''), (string) ($b['cliente'] ?? ''));
            });

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'estricto' => $estricto,
                'totales' => [
                    'cartera_plan' => $totalPlanActivo,
                    'visitados' => count($visitadosActivos) + count($fueraPlan),
                    'visitados_plan' => count($visitadosActivos),
                    'no_visitados' => count($noVisitadosActivos),
                    'fuera_plan' => count($fueraPlan),
                    'diferencia' => (count($visitadosActivos) + count($fueraPlan)) - $totalPlanActivo,
                ],
                'no_visitados' => $noVisitadosActivos,
                'visitados' => $visitadosActivos,
                'fuera_plan' => $fueraPlan,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleVisitasRutaAction para {$ruta}: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'estricto' => $estricto,
                'totales' => [
                    'cartera_plan' => 0,
                    'visitados' => 0,
                    'no_visitados' => 0,
                    'visitados_inactivos' => 0,
                    'fuera_plan' => 0,
                    'diferencia' => 0,
                ],
                'no_visitados' => [],
                'visitados' => [],
                'visitados_inactivos' => [],
                'fuera_plan' => [],
            ];
        }
    }

    /**
     * Calcula la diferencia de tiempo entre marcaje Web y marcaje App.
     */
    private function calcularDiferencia(?string $hWeb, ?string $hApp): array
    {
        if (!$hWeb && !$hApp) {
            return ['segundos' => null, 'texto' => '—'];
        }
        if ($hWeb && !$hApp) {
            return ['segundos' => null, 'texto' => 'Solo Web'];
        }
        if (!$hWeb && $hApp) {
            return ['segundos' => null, 'texto' => 'Solo App'];
        }

        $tWeb = strtotime($hWeb);
        $tApp = strtotime($hApp);
        if ($tWeb === false || $tApp === false) {
            return ['segundos' => null, 'texto' => '—'];
        }

        $diff = $tApp - $tWeb;
        $abs = abs($diff);
        $sign = $diff >= 0 ? '+' : '-';

        if ($abs === 0) {
            $txt = '0s';
        } elseif ($abs < 60) {
            $txt = "{$sign}{$abs}s";
        } elseif ($abs < 3600) {
            $m = (int) floor($abs / 60);
            $s = $abs % 60;
            $txt = $s > 0 ? "{$sign}{$m}m {$s}s" : "{$sign}{$m}m";
        } else {
            $h = (int) floor($abs / 3600);
            $m = (int) floor(($abs % 3600) / 60);
            $txt = $m > 0 ? "{$sign}{$h}h {$m}m" : "{$sign}{$h}h";
        }

        return ['segundos' => $diff, 'texto' => $txt];
    }

    /**
     * Normaliza cadenas de texto para búsqueda (minúsculas, sin tildes ni diacríticos).
     */
    private function normTxt(?string $texto): string
    {
        if (!$texto) {
            return '';
        }
        $s = mb_strtolower(trim($texto), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'Ü' => 'u', 'Ñ' => 'n',
        ]);
        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    /**
     * Normalización estricta alfanumérica eliminando cualquier puntuación o espacio.
     */
    private function normalizarAlfanumerico(?string $str): string
    {
        if (!$str) {
            return '';
        }
        $s = $this->normTxt($str);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    /**
     * Determina el tipo / motivo normalizado de visita combinando Web (fact_visitas) y App (visitas).
     */
    private function determinarTipoVisita(
        bool $visitado,
        float $monto,
        ?string $webTipo,
        ?string $webMotivo,
        ?string $appStatus,
        bool $isOpportunity = false
    ): string {
        if (!$visitado) {
            return 'No Visitado';
        }

        $wTipo = strtoupper(trim(str_replace('_', ' ', (string) $webTipo)));
        $wMotivo = strtoupper(trim(str_replace('_', ' ', (string) $webMotivo)));
        $aStatus = strtoupper(trim(str_replace('_', ' ', (string) $appStatus)));

        // 1. Si generó venta o monto > 0, o está marcado explícitamente como preventa
        if ($monto > 0 || $wTipo === 'PREVENTA' || $aStatus === 'PREVENTA') {
            return 'PreVenta';
        }

        // 2. Revisión de motivos específicos (Web y App)
        $candidatos = array_values(array_filter([$wMotivo, $aStatus, $wTipo], fn ($v) => $v !== ''));

        foreach ($candidatos as $cand) {
            if (str_contains($cand, 'TIENDA CERRADA') || str_contains($cand, 'CERRAD')) {
                return 'Tienda Cerrada';
            }
            if (str_contains($cand, 'SIN DINERO') || str_contains($cand, 'NO DINERO') || str_contains($cand, 'FALTA DINERO')) {
                return 'Sin Dinero';
            }
            if (str_contains($cand, 'CON STOCK') || str_contains($cand, 'TIENE STOCK') || str_contains($cand, 'STOCK')) {
                return 'Con Stock';
            }
            if (str_contains($cand, 'AUSENTE') || str_contains($cand, 'NO ESTA')) {
                return 'Ausente';
            }
            if (str_contains($cand, 'RECHAZ')) {
                return 'Rechazado';
            }
            if (str_contains($cand, 'COBRANZA')) {
                return 'Cobranza';
            }
        }

        if ($isOpportunity || in_array('OPORTUNIDAD', $candidatos, true)) {
            return 'Oportunidad';
        }

        // Si hay un motivo de texto libre que no sea genérico
        if ($wMotivo !== '' && !in_array($wMotivo, ['NO ATENDIDO', 'NO ATENDIDA', 'VISITA'], true)) {
            return ucwords(mb_strtolower($wMotivo));
        }

        if ($aStatus !== '' && !in_array($aStatus, ['VISITADO', 'VISITA'], true)) {
            return ucwords(mb_strtolower($aStatus));
        }

        if ($wTipo === 'NO ATENDIDO' || $wTipo === 'NO ATENDIDA') {
            return 'No Atendido';
        }

        return 'Visita';
    }
}
