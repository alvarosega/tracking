<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleHorarioRutaAction
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
     * Obtiene el recorrido cronológico y auditoría detallada de una ruta.
     * En la app y en la web son los mismos clientes, todos extraídos de pan_ruteo.
     * Muestra la cartera planificada del día (ej. ~38 clientes de pan_ruteo)
     * más un máximo de 2 a 3 visitas registradas fuera de plan (ad-hoc / oportunidad).
     * El cruce se realiza estrictamente por CLIENTE y se agrupa por RUTA (sin tomar en cuenta el vendedor).
     *
     * @param string $ruta
     * @param string|null $fecha
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes', 'Lunes'];
        $diaSemanaNombre = $tuplaDias[1] ?? 'Lunes';
        $diasFiltro = array_values(array_unique(array_merge($tuplaDias, array_map('strtolower', $tuplaDias))));

        $rutaClean = strtoupper(trim($ruta));

        try {
            // 1. Clientes que se debían visitar hoy según pan_ruteo (Plan de Ruteo del Día)
            // Se filtran únicamente los clientes ACTIVOS programados para hoy en esta ruta (igual que en Efectividad)
            $carteraPlanDia = DB::connection('supervisor')->table('pan_ruteo')
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
                    DB::raw("MAX(COALESCE(TRIM(dia), '')) as dia"),
                    DB::raw("MAX(COALESCE(TRIM(dia_norm), '')) as dia_norm"),
                    DB::raw("MAX(COALESCE(TRIM(estado), 'ACTIVO')) as estado"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '')) as direccion"),
                    DB::raw("MAX(COALESCE(TRIM(referencia), '')) as referencia"),
                    DB::raw("MAX(latitud) as latitud"),
                    DB::raw("MAX(longitud) as longitud"),
                    DB::raw("MAX(tipo_negocio) as tipo_negocio"),
                    DB::raw("MAX(vendedor) as vendedor"),
                ])
                ->groupBy('cliente_id')
                ->get()
                ->keyBy('cliente_id');

            // Catálogo auxiliar de todos los clientes de esta ruta en pan_ruteo (solo para resolver nombres de visitas fuera de plan)
            $otrosClientesRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(TRIM(cliente), 'S/N')) as cliente"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '')) as direccion"),
                ])
                ->groupBy('cliente_id')
                ->get()
                ->keyBy('cliente_id');

            // Vendedor únicamente para etiqueta descriptiva de la ruta
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();
            $vendedor = $dimInfo && !empty($dimInfo->vendedor)
                ? trim((string) $dimInfo->vendedor)
                : trim((string) ($carteraPlanDia->firstWhere('vendedor', '!=', null)->vendedor ?? 'No Asignado'));

            // 2. Diccionarios de Cruce por Cliente basados EXCLUSIVAMENTE en el Plan del Día
            // Así evitamos que visitas de otros días o de otras rutas coincidan erróneamente por nombres comunes
            $planIdPorNombreNorm = [];
            $planIdPorNombreAlfa = [];

            foreach ($carteraPlanDia as $cid => $c) {
                $cidInt = (int) $cid;
                $nom = trim((string) $c->cliente);
                $nomNorm = trim((string) $c->cliente_norm);

                if ($nom !== '') {
                    $planIdPorNombreNorm[$this->normalizarTexto($nom)] = $cidInt;
                    $planIdPorNombreAlfa[$this->normalizarAlfanumerico($nom)] = $cidInt;
                }
                if ($nomNorm !== '') {
                    $planIdPorNombreNorm[$this->normalizarTexto($nomNorm)] = $cidInt;
                    $planIdPorNombreAlfa[$this->normalizarAlfanumerico($nomNorm)] = $cidInt;
                }
            }

            // 3. Marcajes del sistema central (fact_visitas - Web ERP)
            // Se cruzan únicamente contra los clientes programados para hoy en esta ruta (sin tomar en cuenta vendedor)
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
                $n1 = $this->normalizarTexto((string) $fs->cliente);
                $n2 = $this->normalizarTexto((string) $fs->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $fs->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $fs->cliente_norm);

                $hIn = trim((string) $fs->check_in);
                if (strlen($hIn) === 5) {
                    $hIn .= ':00';
                }

                $itemWeb = [
                    'check_in' => $hIn,
                    'check_out' => trim((string) $fs->check_out) ?: null,
                    'tiempo_pdv' => trim((string) $fs->tiempo_pdv) ?: null,
                    'tipo' => trim((string) $fs->tipo),
                    'motivo' => trim((string) $fs->motivo),
                    'monto' => (float) ($fs->monto ?: 0.0),
                ];

                if ($a1 !== '' && !isset($factVisitasPorNombreAlfa[$a1])) {
                    $factVisitasPorNombreAlfa[$a1] = $itemWeb;
                }
                if ($a2 !== '' && !isset($factVisitasPorNombreAlfa[$a2])) {
                    $factVisitasPorNombreAlfa[$a2] = $itemWeb;
                }

                // El cruce sólo reconoce clientes que forman parte de la cartera planificada de hoy
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

            // 4. Ventas / Preventas del día para montos oficiales
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

            // 5. Marcajes de la App Móvil (tabla visitas)
            // Se consultan las visitas ejecutadas por esta ruta en el día
            $cidsPlan = array_map('intval', array_keys($carteraPlanDia->toArray()));
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

                // Si pertenece a la cartera planificada del día para esta ruta
                if ($cid > 0 && isset($carteraPlanDia[$cid])) {
                    if (!isset($visitasMovilPorCid[$cid])) {
                        $visitasMovilPorCid[$cid] = [
                            'hora_app' => $horaM,
                            'foto_url' => $fotoUrl,
                            'fotos_count' => $fotoUrl ? 1 : 0,
                            'status' => (string) ($fm->status ?? ''),
                            'comments' => (string) ($fm->comments ?? ''),
                        ];
                    } else {
                        if (strcmp($horaM, $visitasMovilPorCid[$cid]['hora_app']) < 0) {
                            $visitasMovilPorCid[$cid]['hora_app'] = $horaM;
                        }
                        if ($fotoUrl && empty($visitasMovilPorCid[$cid]['foto_url'])) {
                            $visitasMovilPorCid[$cid]['foto_url'] = $fotoUrl;
                            $visitasMovilPorCid[$cid]['fotos_count']++;
                        }
                        if (!empty($fm->status) && empty($visitasMovilPorCid[$cid]['status'])) {
                            $visitasMovilPorCid[$cid]['status'] = (string) $fm->status;
                        }
                    }
                } else {
                    // Visita fuera del plan del día realizada por esta ruta (máximo 0 a 3 visitas)
                    $fpKey = $cid > 0 ? "cid_{$cid}" : 'opp_' . md5(trim((string) $fm->opportunity_client_name) ?: (string) $fm->id);

                    if (!isset($visitasFueraPlan[$fpKey])) {
                        $nombreFp = $cid > 0 && isset($otrosClientesRuta[$cid])
                            ? trim((string) $otrosClientesRuta[$cid]->cliente)
                            : trim((string) ($fm->opportunity_client_name ?: ($cid > 0 ? "Cliente #{$cid}" : "Cliente Oportunidad")));

                        $direccionFp = $cid > 0 && isset($otrosClientesRuta[$cid])
                            ? trim((string) ($otrosClientesRuta[$cid]->direccion ?? ''))
                            : null;

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
                            'client_id' => $cid > 0 ? $cid : null,
                            'cliente' => $nombreFp,
                            'hora_app' => $horaM,
                            'hora_web' => $webFp['check_in'] ?? null,
                            'check_out_web' => $webFp['check_out'] ?? null,
                            'tiempo_pdv_web' => $webFp['tiempo_pdv'] ?? null,
                            'monto' => $montoFp,
                            'tipo' => $tipoFp,
                            'foto_url' => $fotoUrl,
                            'fotos_count' => $fotoUrl ? 1 : 0,
                            'direccion' => $direccionFp,
                        ];
                    } else {
                        if (strcmp($horaM, $visitasFueraPlan[$fpKey]['hora_app']) < 0) {
                            $visitasFueraPlan[$fpKey]['hora_app'] = $horaM;
                        }
                        if ($fotoUrl && empty($visitasFueraPlan[$fpKey]['foto_url'])) {
                            $visitasFueraPlan[$fpKey]['foto_url'] = $fotoUrl;
                            $visitasFueraPlan[$fpKey]['fotos_count']++;
                        }
                    }
                }
            }

            // 6. Construcción Unificada de la Pizarra de Paradas:
            // A) Exactamente los clientes del Plan de Ruteo del Día (estén o no visitados, con su cliente_id oficial)
            // B) Clientes visitados hoy en esta ruta fuera de plan (máximo 0 a 3 registros adicionales)
            $paradas = [];

            // Paso A: Cartera planificada del día
            foreach ($carteraPlanDia as $cid => $pCli) {
                $cidInt = (int) $cid;
                $web = $visitasWebPorCid[$cidInt] ?? null;
                $app = $visitasMovilPorCid[$cidInt] ?? null;
                $montoPrev = $preventasMontoPorCid[$cidInt] ?? 0.0;

                $visitado = ($web !== null || $app !== null);

                $hWeb = $web['check_in'] ?? null;
                $hApp = $app['hora_app'] ?? null;

                $hOrden = '99:99:99';
                if ($hWeb && $hApp) {
                    $hOrden = strcmp($hWeb, $hApp) < 0 ? $hWeb : $hApp;
                } elseif ($hWeb) {
                    $hOrden = $hWeb;
                } elseif ($hApp) {
                    $hOrden = $hApp;
                }

                $montoFinal = max((float) ($web['monto'] ?? 0.0), $montoPrev);
                $tipoFinal = $this->determinarTipoVisita(
                    $visitado,
                    $montoFinal,
                    $web['tipo'] ?? '',
                    $web['motivo'] ?? '',
                    $app['status'] ?? '',
                    false
                );

                $diffInfo = $this->calcularDiferencia($hWeb, $hApp);

                $paradas["id_{$cidInt}"] = [
                    'cliente' => trim((string) $pCli->cliente) ?: "Cliente #{$cidInt}",
                    'client_id' => $cidInt,
                    'hora_web' => $hWeb,
                    'hora_app' => $hApp,
                    'hora_orden' => $hOrden,
                    'diferencia_segundos' => $diffInfo['segundos'],
                    'diferencia_texto' => $diffInfo['texto'],
                    'tipo' => $tipoFinal,
                    'monto' => $montoFinal,
                    'check_out_web' => $web['check_out'] ?? null,
                    'tiempo_pdv_web' => $web['tiempo_pdv'] ?? null,
                    'foto_url' => $app['foto_url'] ?? null,
                    'fotos_count' => $app['fotos_count'] ?? 0,
                    'tiene_fotos_historicas' => false,
                    'en_plan' => true,
                    'visitado' => $visitado,
                    'direccion' => trim((string) ($pCli->direccion ?? '')) ?: null,
                ];
            }

            // Paso B: Clientes visitados fuera de plan
            foreach ($visitasFueraPlan as $fpKey => $fp) {
                $hWeb = $fp['hora_web'];
                $hApp = $fp['hora_app'];
                $hOrden = '99:99:99';
                if ($hWeb && $hApp) {
                    $hOrden = strcmp($hWeb, $hApp) < 0 ? $hWeb : $hApp;
                } elseif ($hWeb) {
                    $hOrden = $hWeb;
                } elseif ($hApp) {
                    $hOrden = $hApp;
                }

                $diffInfo = $this->calcularDiferencia($hWeb, $hApp);

                $paradas["fp_{$fpKey}"] = [
                    'cliente' => $fp['cliente'],
                    'client_id' => $fp['client_id'],
                    'hora_web' => $hWeb,
                    'hora_app' => $hApp,
                    'hora_orden' => $hOrden,
                    'diferencia_segundos' => $diffInfo['segundos'],
                    'diferencia_texto' => $diffInfo['texto'],
                    'tipo' => $fp['tipo'],
                    'monto' => (float) $fp['monto'],
                    'check_out_web' => $fp['check_out_web'],
                    'tiempo_pdv_web' => $fp['tiempo_pdv_web'],
                    'foto_url' => $fp['foto_url'],
                    'fotos_count' => $fp['fotos_count'],
                    'tiene_fotos_historicas' => false,
                    'en_plan' => false,
                    'visitado' => true,
                    'direccion' => $fp['direccion'],
                ];
            }

            // 7. Totales y Métricas de Evaluación
            $totalPlanDia = count($carteraPlanDia);
            $visitadosDelPlan = 0;
            $noVisitadosDelPlan = 0;
            $fueraPlan = count($visitasFueraPlan);
            $totalVisitados = 0;

            foreach ($paradas as $p) {
                if (!empty($p['visitado'])) {
                    $totalVisitados++;
                    if (!empty($p['en_plan'])) {
                        $visitadosDelPlan++;
                    }
                } else {
                    $noVisitadosDelPlan++;
                }
            }

            $coberturaPct = $totalPlanDia > 0
                ? round(($visitadosDelPlan / $totalPlanDia) * 100, 1)
                : 0.0;

            // 8. Comprobar si tienen fotos históricas en Visita
            $todosClientIds = array_values(array_filter(
                array_column($paradas, 'client_id'),
                fn ($id) => !empty($id)
            ));

            if (!empty($todosClientIds)) {
                try {
                    $cidsConHistorial = Visita::query()
                        ->whereIn('client_id', $todosClientIds)
                        ->whereNotNull('photo_path')
                        ->where('photo_path', '!=', '')
                        ->select('client_id')
                        ->distinct()
                        ->pluck('client_id')
                        ->map(fn ($id) => (int) $id)
                        ->flip()
                        ->toArray();

                    foreach ($paradas as &$p) {
                        if (!empty($p['client_id']) && isset($cidsConHistorial[(int) $p['client_id']])) {
                            $p['tiene_fotos_historicas'] = true;
                        }
                    }
                    unset($p);
                } catch (\Throwable $e) {
                    Log::debug("Error verificando fotos históricas: " . $e->getMessage());
                }
            }

            // 9. Ordenar Cronológicamente: primero los visitados por hora (1..N), luego los pendientes
            $eventos = array_values($paradas);
            usort($eventos, function ($a, $b) {
                $cmp = strcmp((string) ($a['hora_orden'] ?? '99:99:99'), (string) ($b['hora_orden'] ?? '99:99:99'));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp((string) ($a['cliente'] ?? ''), (string) ($b['cliente'] ?? ''));
            });

            foreach ($eventos as $i => &$ev) {
                $ev['orden'] = $i + 1;
            }
            unset($ev);

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'dia_semana' => $diaSemanaNombre,
                'total_eventos' => count($eventos),
                'totales' => [
                    'plan_dia' => $totalPlanDia,
                    'visitados' => $totalVisitados,
                    'visitados_del_plan' => $visitadosDelPlan,
                    'no_visitados' => $noVisitadosDelPlan,
                    'fuera_plan' => $fueraPlan,
                    'cobertura_pct' => $coberturaPct,
                ],
                'eventos' => $eventos,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleHorarioRutaAction para {$ruta}: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return [
                'ruta' => $ruta,
                'vendedor' => 'No Asignado',
                'fecha' => $fechaNorm,
                'dia_semana' => $diaSemanaNombre ?? 'Lunes',
                'total_eventos' => 0,
                'totales' => [
                    'plan_dia' => 0,
                    'visitados' => 0,
                    'visitados_del_plan' => 0,
                    'no_visitados' => 0,
                    'fuera_plan' => 0,
                    'cobertura_pct' => 0.0,
                ],
                'eventos' => [],
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
    private function normalizarTexto(?string $str): string
    {
        if (!$str) {
            return '';
        }
        $s = mb_strtolower(trim($str), 'UTF-8');
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
        $s = $this->normalizarTexto($str);
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
