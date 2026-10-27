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
            // 1. Cartera Oficial Completa de pan_ruteo asignada a esta RUTA
            // Todos los clientes de la ruta tienen su cliente_id y ruta definidos en pan_ruteo
            $clientesRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
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
                ->get();

            // Vendedor únicamente para etiqueta descriptiva de la ruta
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();
            $vendedor = $dimInfo && !empty($dimInfo->vendedor)
                ? trim((string) $dimInfo->vendedor)
                : trim((string) ($clientesRuta->firstWhere('vendedor', '!=', null)->vendedor ?? 'No Asignado'));

            // 2. Clientes que se debían visitar hoy según pan_ruteo (Plan de Ruteo del Día)
            $carteraPlanDia = $clientesRuta->filter(function ($c) use ($diasFiltro) {
                $d1 = strtolower(trim((string) ($c->dia_norm ?? '')));
                $d2 = strtolower(trim((string) ($c->dia ?? '')));
                return in_array($d1, $diasFiltro, true) || in_array($d2, $diasFiltro, true);
            })->keyBy('cliente_id');

            // 3. Diccionarios de Cruce por Cliente (Nombre Exacto, Normalizado y Alfanumérico)
            $clientePorId = [];
            $idPorNombreNorm = [];
            $idPorNombreAlfa = [];
            $nombreOficialPorId = [];

            foreach ($clientesRuta as $c) {
                $cid = (int) $c->cliente_id;
                $clientePorId[$cid] = $c;
                $nom = trim((string) $c->cliente);
                $nomNorm = trim((string) $c->cliente_norm);

                $nombreOficialPorId[$cid] = $nom ?: ($nomNorm ?: "Cliente #{$cid}");

                if ($nom !== '') {
                    $idPorNombreNorm[$this->normalizarTexto($nom)] = $cid;
                    $idPorNombreAlfa[$this->normalizarAlfanumerico($nom)] = $cid;
                }
                if ($nomNorm !== '') {
                    $idPorNombreNorm[$this->normalizarTexto($nomNorm)] = $cid;
                    $idPorNombreAlfa[$this->normalizarAlfanumerico($nomNorm)] = $cid;
                }
            }

            // 4. Marcajes del sistema central (fact_visitas - Web ERP)
            // Cruce estrictamente por CLIENTE contra los clientes de pan_ruteo de esta ruta (sin vendedor)
            $visitasSistema = DB::connection('supervisor')->table('fact_visitas as fv')
                ->where('fv.fecha_norm', $fechaNorm)
                ->select([
                    DB::raw("COALESCE(fv.cliente, 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(fv.cliente_norm, '') as cliente_norm"),
                    DB::raw("COALESCE(NULLIF(TRIM(fv.tipo), ''), NULLIF(TRIM(fv.motivo), ''), 'Visita') as tipo"),
                    DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                    DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
                    DB::raw("COALESCE(fv.tiempo_pdv, '00:00:00') as tiempo_pdv"),
                    DB::raw("CAST(COALESCE(fv.monto, 0.0) AS DECIMAL(12, 2)) as monto"),
                ])
                ->orderBy('fv.check_in', 'asc')
                ->get();

            // Mapeo de visitas web acumuladas por cliente_id
            $visitasWebPorCid = [];
            foreach ($visitasSistema as $fs) {
                $n1 = $this->normalizarTexto((string) $fs->cliente);
                $n2 = $this->normalizarTexto((string) $fs->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $fs->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $fs->cliente_norm);

                $cid = $idPorNombreNorm[$n1]
                    ?? $idPorNombreNorm[$n2]
                    ?? $idPorNombreAlfa[$a1]
                    ?? $idPorNombreAlfa[$a2]
                    ?? null;

                if ($cid) {
                    $hIn = trim((string) $fs->check_in);
                    if (strlen($hIn) === 5) {
                        $hIn .= ':00';
                    }

                    if (!isset($visitasWebPorCid[$cid])) {
                        $visitasWebPorCid[$cid] = [
                            'check_in' => $hIn,
                            'check_out' => trim((string) $fs->check_out) ?: null,
                            'tiempo_pdv' => trim((string) $fs->tiempo_pdv) ?: null,
                            'tipo' => trim((string) $fs->tipo) ?: 'Visita',
                            'monto' => (float) ($fs->monto ?: 0.0),
                        ];
                    } else {
                        if (strcmp($hIn, $visitasWebPorCid[$cid]['check_in']) < 0) {
                            $visitasWebPorCid[$cid]['check_in'] = $hIn;
                        }
                        $visitasWebPorCid[$cid]['monto'] += (float) ($fs->monto ?: 0.0);
                    }
                }
            }

            // 5. Ventas / Preventas del día para montos oficiales
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

            // 6. Marcajes de la App Móvil (tabla visitas)
            // Cruce por cliente_id perteneciente a la ruta O por route = $rutaClean
            $cidsRuta = array_keys($clientePorId);
            $filasMovil = Visita::query()
                ->whereBetween('visited_at', ["{$fechaNorm} 00:00:00", "{$fechaNorm} 23:59:59"])
                ->where(function ($q) use ($rutaClean, $cidsRuta): void {
                    $q->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean]);
                    if (!empty($cidsRuta)) {
                        $q->orWhereIn('client_id', $cidsRuta);
                    }
                })
                ->orderBy('visited_at', 'asc')
                ->get();

            $visitasMovilPorCid = [];
            foreach ($filasMovil as $fm) {
                $cid = (int) ($fm->client_id ?? 0);
                if ($cid <= 0 && !empty($fm->opportunity_client_name)) {
                    $a = $this->normalizarAlfanumerico((string) $fm->opportunity_client_name);
                    $cid = $idPorNombreAlfa[$a] ?? 0;
                }

                if ($cid > 0) {
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

                    if (!isset($visitasMovilPorCid[$cid])) {
                        $visitasMovilPorCid[$cid] = [
                            'hora_app' => $horaM,
                            'foto_url' => $fotoUrl,
                            'fotos_count' => $fotoUrl ? 1 : 0,
                            'status' => (string) ($fm->status ?? 'VISITADO'),
                        ];
                    } else {
                        if (strcmp($horaM, $visitasMovilPorCid[$cid]['hora_app']) < 0) {
                            $visitasMovilPorCid[$cid]['hora_app'] = $horaM;
                        }
                        if ($fotoUrl && empty($visitasMovilPorCid[$cid]['foto_url'])) {
                            $visitasMovilPorCid[$cid]['foto_url'] = $fotoUrl;
                            $visitasMovilPorCid[$cid]['fotos_count']++;
                        }
                    }
                }
            }

            // 7. Construcción Unificada de la Pizarra de Paradas:
            // A) Todos los clientes del Plan de Ruteo del Día (estén o no visitados)
            // B) Clientes de esta ruta visitados hoy fuera de su día planificado (Fuera de Plan)
            $paradas = [];
            $todosCidsVisitados = array_values(array_unique(array_merge(
                array_keys($visitasWebPorCid),
                array_keys($visitasMovilPorCid)
            )));

            // Paso A: Procesar clientes del Plan del Día de pan_ruteo
            foreach ($carteraPlanDia as $cid => $pCli) {
                $web = $visitasWebPorCid[$cid] ?? null;
                $app = $visitasMovilPorCid[$cid] ?? null;
                $montoPrev = $preventasMontoPorCid[$cid] ?? 0.0;

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
                $tipoFinal = 'No Visitado';
                if ($visitado) {
                    if ($montoFinal > 0 || (isset($web['tipo']) && mb_strtolower($web['tipo']) === 'preventa')) {
                        $tipoFinal = 'PreVenta';
                    } else {
                        $tipoFinal = $web['tipo'] ?? 'Visita';
                    }
                }

                $paradas["id_{$cid}"] = [
                    'cliente' => trim((string) $pCli->cliente) ?: "Cliente #{$cid}",
                    'client_id' => $cid,
                    'hora_web' => $hWeb,
                    'hora_app' => $hApp,
                    'hora_orden' => $hOrden,
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

            // Paso B: Procesar clientes de la ruta que NO eran del plan de hoy, pero se visitaron hoy
            foreach ($todosCidsVisitados as $cid) {
                if (!isset($paradas["id_{$cid}"])) {
                    $pCli = $clientePorId[$cid] ?? null;
                    $web = $visitasWebPorCid[$cid] ?? null;
                    $app = $visitasMovilPorCid[$cid] ?? null;
                    $montoPrev = $preventasMontoPorCid[$cid] ?? 0.0;

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
                    $tipoFinal = ($montoFinal > 0 || (isset($web['tipo']) && mb_strtolower($web['tipo']) === 'preventa'))
                        ? 'PreVenta'
                        : ($web['tipo'] ?? 'Visita');

                    $nombre = $pCli ? trim((string) $pCli->cliente) : ($nombreOficialPorId[$cid] ?? "Cliente #{$cid}");

                    $paradas["id_{$cid}"] = [
                        'cliente' => $nombre,
                        'client_id' => $cid,
                        'hora_web' => $hWeb,
                        'hora_app' => $hApp,
                        'hora_orden' => $hOrden,
                        'tipo' => $tipoFinal,
                        'monto' => $montoFinal,
                        'check_out_web' => $web['check_out'] ?? null,
                        'tiempo_pdv_web' => $web['tiempo_pdv'] ?? null,
                        'foto_url' => $app['foto_url'] ?? null,
                        'fotos_count' => $app['fotos_count'] ?? 0,
                        'tiene_fotos_historicas' => false,
                        'en_plan' => false, // Fuera del plan programado para hoy
                        'visitado' => true,
                        'direccion' => $pCli ? trim((string) ($pCli->direccion ?? '')) : null,
                    ];
                }
            }

            // 8. Totales y Métricas de Evaluación
            $totalPlanDia = count($carteraPlanDia);
            $visitadosDelPlan = 0;
            $noVisitadosDelPlan = 0;
            $fueraPlan = 0;
            $totalVisitados = 0;

            foreach ($paradas as $p) {
                if (!empty($p['visitado'])) {
                    $totalVisitados++;
                    if (!empty($p['en_plan'])) {
                        $visitadosDelPlan++;
                    } else {
                        $fueraPlan++;
                    }
                } else {
                    $noVisitadosDelPlan++;
                }
            }

            $coberturaPct = $totalPlanDia > 0
                ? round(($visitadosDelPlan / $totalPlanDia) * 100, 1)
                : 0.0;

            // 9. Comprobar si tienen fotos históricas en Visita
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

            // 10. Ordenar Cronológicamente: primero los visitados por hora (1..N), luego los pendientes
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
}
