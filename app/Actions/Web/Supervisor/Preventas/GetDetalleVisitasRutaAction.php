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
            // 1. Cartera de la ruta para el día según pan_ruteo (Clientes ACTIVOS del día, igual que en Efectividad)
            $carteraTotalRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($diasFiltro): void {
                    $q->whereIn(DB::raw("LOWER(TRIM(dia_norm))"), $diasFiltro)
                      ->orWhereIn(DB::raw("LOWER(TRIM(dia))"), $diasFiltro);
                })
                ->where('cliente_id', '>', 0)
                ->whereIn(DB::raw("UPPER(TRIM(COALESCE(estado, 'ACTIVO')))"), ['ACTIVO', 'ACTIVA', 'VIGENTE'])
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(TRIM(cliente), 'S/N')) as cliente"),
                    DB::raw("MAX(COALESCE(TRIM(cliente_norm), '')) as cliente_norm"),
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

            // Catálogo auxiliar de todos los clientes de la ruta para nombres fuera de plan
            $otrosClientesRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(TRIM(cliente), 'S/N')) as cliente"),
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

            // 2. Diccionarios de Cruce por Cliente basados EXCLUSIVAMENTE en el Plan del Día
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

            foreach ($visitasSistema as $fs) {
                $n1 = $this->normTxt((string) $fs->cliente);
                $n2 = $this->normTxt((string) $fs->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $fs->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $fs->cliente_norm);

                $hIn = trim((string) $fs->check_in);
                if (strlen($hIn) === 5) {
                    $hIn .= ':00';
                }

                $tipoV = trim((string) $fs->tipo);
                $motivoV = trim((string) $fs->motivo);

                $itemWeb = [
                    'check_in' => $hIn,
                    'check_out' => trim((string) $fs->check_out) ?: null,
                    'tiempo_pdv' => trim((string) $fs->tiempo_pdv) ?: null,
                    'tipo' => $tipoV,
                    'motivo' => $motivoV,
                    'monto' => (float) ($fs->monto ?: 0.0),
                ];

                if ($a1 !== '' && !isset($factVisitasPorNombreAlfa[$a1])) {
                    $factVisitasPorNombreAlfa[$a1] = $itemWeb;
                }
                if ($a2 !== '' && !isset($factVisitasPorNombreAlfa[$a2])) {
                    $factVisitasPorNombreAlfa[$a2] = $itemWeb;
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
                        if (strcmp($hIn, $visitasWebPorCid[$cid]['check_in']) < 0) {
                            $visitasWebPorCid[$cid]['check_in'] = $hIn;
                        }
                        $visitasWebPorCid[$cid]['monto'] += (float) ($fs->monto ?: 0.0);
                        if (!empty($itemWeb['motivo']) && empty($visitasWebPorCid[$cid]['motivo'])) {
                            $visitasWebPorCid[$cid]['motivo'] = $itemWeb['motivo'];
                        }
                        if (strtoupper($itemWeb['tipo']) === 'PREVENTA') {
                            $visitasWebPorCid[$cid]['tipo'] = 'PreVenta';
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
            $visitasFueraPlan = [];

            foreach ($filasMovil as $fm) {
                $cid = (int) ($fm->client_id ?? 0);
                if ($cid <= 0 && !empty($fm->opportunity_client_name)) {
                    $a = $this->normalizarAlfanumerico((string) $fm->opportunity_client_name);
                    $cid = $planIdPorNombreAlfa[$a] ?? 0;
                }

                $horaM = $fm->visited_at ? $fm->visited_at->format('H:i:s') : '00:00:00';
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
                            'foto_url' => $fotoUrl,
                            'status_app' => (string) ($fm->status ?? ''),
                            'comments_app' => (string) ($fm->comments ?? ''),
                        ];
                    } else {
                        if (strcmp($horaM, $visitasMovilPorCid[$cid]['hora_app']) < 0) {
                            $visitasMovilPorCid[$cid]['hora_app'] = $horaM;
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
                        if (strcmp($horaM, $visitasFueraPlan[$fpKey]['hora_app']) < 0) {
                            $visitasFueraPlan[$fpKey]['hora_app'] = $horaM;
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
            $visitadosInactivos = [];
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
                $visitado = ($enFactVisitas || $enVisitasApp);

                $montoFinal = max((float) ($web['monto'] ?? 0.0), $montoPrev);
                $hWeb = $web['check_in'] ?? null;
                $hApp = $app['hora_app'] ?? null;

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

                $pasaCriterio = $estricto ? $enVisitasApp : $visitado;
                if ($pasaCriterio) {
                    $visitadosActivos[] = $cRow;
                } else {
                    $noVisitadosActivos[] = $cRow;
                }
            }

            // Visitas fuera de plan o a clientes inactivos
            foreach ($visitasFueraPlan as $fp) {
                $diffInfo = $this->calcularDiferencia($fp['check_in'], $fp['hora_app']);
                $fp['diferencia_segundos'] = $diffInfo['segundos'];
                $fp['diferencia_texto'] = $diffInfo['texto'];

                if (!empty($fp['es_inactivo'])) {
                    $visitadosInactivos[] = $fp;
                } else {
                    $fueraPlan[] = $fp;
                }
            }

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'estricto' => $estricto,
                'totales' => [
                    'cartera_plan' => $totalPlanActivo,
                    'visitados' => count($visitadosActivos),
                    'no_visitados' => count($noVisitadosActivos),
                    'visitados_inactivos' => count($visitadosInactivos),
                    'fuera_plan' => count($fueraPlan),
                    'diferencia' => count($visitadosActivos) - $totalPlanActivo,
                ],
                'no_visitados' => $noVisitadosActivos,
                'visitados' => $visitadosActivos,
                'visitados_inactivos' => $visitadosInactivos,
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
