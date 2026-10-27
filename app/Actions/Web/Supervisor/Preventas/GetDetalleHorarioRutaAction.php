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
     * Obtiene el recorrido cronológico detallado de una ruta comparando paradas en sistema ERP y app móvil,
     * integrando la cartera oficial planificada del día desde pan_ruteo para auditar clientes visitados vs pendientes.
     * Cruza fact_visitas con pan_ruteo mediante normalización fonética y alfanumérica por nombre de cliente.
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
        $vendedor = 'No Asignado';

        try {
            // 1. Información de la Ruta y Vendedor Oficial
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && !empty($dimInfo->vendedor)) {
                $vendedor = trim((string) $dimInfo->vendedor);
            }

            // 2. Cartera Planificada de pan_ruteo para el DÍA ESPECÍFICO de la fecha consultada
            $carteraPlanDiaRaw = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($diasFiltro): void {
                    $q->whereIn(DB::raw("LOWER(TRIM(COALESCE(dia_norm, dia)))"), $diasFiltro)
                      ->orWhereIn(DB::raw("LOWER(TRIM(dia))"), $diasFiltro);
                })
                ->where('cliente_id', '>', 0)
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(TRIM(cliente), 'S/N')) as cliente"),
                    DB::raw("MAX(COALESCE(TRIM(cliente_norm), '')) as cliente_norm"),
                    DB::raw("MAX(COALESCE(TRIM(estado), 'ACTIVO')) as estado"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '')) as direccion"),
                    DB::raw("MAX(COALESCE(TRIM(referencia), '')) as referencia"),
                    DB::raw("MAX(latitud) as latitud"),
                    DB::raw("MAX(longitud) as longitud"),
                    DB::raw("MAX(tipo_negocio) as tipo_negocio"),
                ])
                ->groupBy('cliente_id')
                ->get();

            $carteraPlanDia = $carteraPlanDiaRaw->keyBy('cliente_id');

            // 3. Cartera COMPLETA asignada a la ruta (todos los días) para resolver nombres y vendedor
            $carteraTodaRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
                ->select(['cliente_id', 'cliente', 'cliente_norm', 'vendedor'])
                ->get();

            if ($vendedor === 'No Asignado') {
                $firstVend = $carteraTodaRuta->firstWhere('vendedor', '!=', null);
                if ($firstVend && !empty($firstVend->vendedor)) {
                    $vendedor = trim((string) $firstVend->vendedor);
                }
            }

            // 4. Mapeo Multi-Capa de Normalización de Nombres a cliente_id
            $idPorNombreExacto = [];
            $idPorNombreNorm = [];
            $idPorNombreAlfa = [];
            $nombreOficialPorId = [];

            $registrarMapeo = function (?int $cid, ?string $nom1, ?string $nom2 = null) use (&$idPorNombreExacto, &$idPorNombreNorm, &$idPorNombreAlfa, &$nombreOficialPorId): void {
                if (!$cid || $cid <= 0) {
                    return;
                }
                foreach ([$nom1, $nom2] as $nom) {
                    if (!$nom) continue;
                    $t = trim((string) $nom);
                    if ($t === '') continue;

                    if (!isset($nombreOficialPorId[$cid])) {
                        $nombreOficialPorId[$cid] = $t;
                    }

                    $low = mb_strtolower($t, 'UTF-8');
                    $norm = $this->normalizarTexto($t);
                    $alfa = $this->normalizarAlfanumerico($t);

                    if ($low !== '' && !isset($idPorNombreExacto[$low])) {
                        $idPorNombreExacto[$low] = $cid;
                    }
                    if ($norm !== '' && !isset($idPorNombreNorm[$norm])) {
                        $idPorNombreNorm[$norm] = $cid;
                    }
                    if ($alfa !== '' && !isset($idPorNombreAlfa[$alfa])) {
                        $idPorNombreAlfa[$alfa] = $cid;
                    }
                }
            };

            foreach ($carteraTodaRuta as $cRow) {
                $registrarMapeo((int) $cRow->cliente_id, (string) $cRow->cliente, (string) $cRow->cliente_norm);
            }
            foreach ($carteraPlanDiaRaw as $pRow) {
                $registrarMapeo((int) $pRow->cliente_id, (string) $pRow->cliente, (string) $pRow->cliente_norm);
            }

            // 5. Ventas y pedidos del día para enriquecer montos y clientes con compra
            $preventasHoyPorCliente = [];
            try {
                $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                    ->where('fecha_norm', $fechaNorm)
                    ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                    ->where('cliente_id', '>', 0)
                    ->select('cliente_id', 'cliente', DB::raw("SUM(monto_final) as total_monto"))
                    ->groupBy('cliente_id', 'cliente')
                    ->get();

                foreach ($preventasHoy as $ph) {
                    $cid = (int) $ph->cliente_id;
                    $totMonto = (float) ($ph->total_monto ?: 0.0);
                    if ($cid > 0) {
                        $preventasHoyPorCliente[$cid] = $totMonto;
                        $registrarMapeo($cid, (string) $ph->cliente);
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("No se consultaron preventas en GetDetalleHorarioRutaAction: " . $e->getMessage());
            }

            // 6. Marcajes del sistema central (fact_visitas) - ERP / Web
            $todasFilasSistema = DB::connection('supervisor')->table('fact_visitas as fv')
                ->where('fv.fecha_norm', $fechaNorm)
                ->select([
                    DB::raw("COALESCE(fv.cliente, 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(fv.cliente_norm, '') as cliente_norm"),
                    'fv.empleado_norm',
                    'fv.empleado',
                    DB::raw("COALESCE(NULLIF(TRIM(fv.tipo), ''), NULLIF(TRIM(fv.motivo), ''), 'Visita') as tipo"),
                    DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                    DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
                    DB::raw("COALESCE(fv.tiempo_pdv, '00:00:00') as tiempo_pdv"),
                    DB::raw("CAST(COALESCE(fv.monto, 0.0) AS DECIMAL(12, 2)) as monto"),
                ])
                ->orderBy('fv.check_in', 'asc')
                ->get();

            $vendedorNorm = $this->normalizarTexto($vendedor);

            // Filtrar visitas asignadas a la ruta (por empleado O por cliente perteneciente a la cartera)
            $filasSistemaRuta = $todasFilasSistema->filter(function ($fs) use ($vendedorNorm, $idPorNombreAlfa) {
                if ($vendedorNorm !== 'no asignado' && $vendedorNorm !== '') {
                    $empNorm1 = $this->normalizarTexto((string) $fs->empleado_norm);
                    $empNorm2 = $this->normalizarTexto((string) $fs->empleado);
                    if ($empNorm1 === $vendedorNorm || $empNorm2 === $vendedorNorm) {
                        return true;
                    }
                }

                $alfa1 = $this->normalizarAlfanumerico((string) $fs->cliente);
                $alfa2 = $this->normalizarAlfanumerico((string) $fs->cliente_norm);
                if (($alfa1 !== '' && isset($idPorNombreAlfa[$alfa1])) || ($alfa2 !== '' && isset($idPorNombreAlfa[$alfa2]))) {
                    return true;
                }

                return false;
            });

            // 7. Cruce Global: Si alguna visita en fact_visitas no coincide con la cartera de la ruta,
            // buscar su nombre en toda la tabla pan_ruteo para asociarle su cliente_id oficial
            $nombresSinMatch = [];
            foreach ($filasSistemaRuta as $fs) {
                $a1 = $this->normalizarAlfanumerico((string) $fs->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $fs->cliente_norm);
                if (!isset($idPorNombreAlfa[$a1]) && !isset($idPorNombreAlfa[$a2])) {
                    if (!empty($fs->cliente)) $nombresSinMatch[] = trim((string) $fs->cliente);
                    if (!empty($fs->cliente_norm)) $nombresSinMatch[] = trim((string) $fs->cliente_norm);
                }
            }
            $nombresSinMatch = array_values(array_unique(array_filter($nombresSinMatch)));

            if (!empty($nombresSinMatch)) {
                try {
                    $globales = DB::connection('supervisor')->table('pan_ruteo')
                        ->where('cliente_id', '>', 0)
                        ->where(function ($q) use ($nombresSinMatch): void {
                            $q->whereIn('cliente', $nombresSinMatch)
                              ->orWhereIn('cliente_norm', $nombresSinMatch);
                        })
                        ->select(['cliente_id', 'cliente', 'cliente_norm'])
                        ->get();

                    foreach ($globales as $g) {
                        $registrarMapeo((int) $g->cliente_id, (string) $g->cliente, (string) $g->cliente_norm);
                    }
                } catch (\Throwable $e) {
                    Log::debug("Error consultando pan_ruteo global: " . $e->getMessage());
                }
            }

            // Resolver ID con múltiples niveles de tolerancia
            $resolverClienteId = function (?string $nom1, ?string $nom2 = null) use (&$idPorNombreExacto, &$idPorNombreNorm, &$idPorNombreAlfa): ?int {
                foreach ([$nom2, $nom1] as $nom) {
                    if (!$nom) continue;
                    $low = mb_strtolower(trim((string) $nom), 'UTF-8');
                    if (isset($idPorNombreExacto[$low])) return $idPorNombreExacto[$low];

                    $norm = $this->normalizarTexto((string) $nom);
                    if (isset($idPorNombreNorm[$norm])) return $idPorNombreNorm[$norm];

                    $alfa = $this->normalizarAlfanumerico((string) $nom);
                    if (isset($idPorNombreAlfa[$alfa])) return $idPorNombreAlfa[$alfa];
                }
                return null;
            };

            $paradas = [];

            // 8. Procesar Visitas de fact_visitas (ERP / Web)
            foreach ($filasSistemaRuta as $fs) {
                $hIn = trim((string) $fs->check_in);
                if (strlen($hIn) === 5) {
                    $hIn .= ':00';
                }

                $cId = $resolverClienteId((string) $fs->cliente, (string) $fs->cliente_norm);
                $key = $cId ? "id_{$cId}" : "alfa_" . $this->normalizarAlfanumerico((string) ($fs->cliente_norm ?: $fs->cliente));

                $nombreCliente = ($cId && isset($nombreOficialPorId[$cId]))
                    ? $nombreOficialPorId[$cId]
                    : trim((string) $fs->cliente);

                $montoFs = (float) ($fs->monto ?: 0.0);
                if ($cId && isset($preventasHoyPorCliente[$cId]) && $preventasHoyPorCliente[$cId] > $montoFs) {
                    $montoFs = $preventasHoyPorCliente[$cId];
                }

                $tipoFact = trim((string) $fs->tipo);
                $tipoFinal = ($montoFs > 0 || mb_strtolower($tipoFact) === 'preventa') ? 'PreVenta' : ($tipoFact ?: 'Visita');

                $enPlan = $cId ? $carteraPlanDia->has($cId) : false;

                if (!isset($paradas[$key])) {
                    $paradas[$key] = [
                        'cliente' => $nombreCliente,
                        'client_id' => $cId,
                        'hora_web' => $hIn,
                        'hora_app' => null,
                        'hora_orden' => $hIn,
                        'tipo' => $tipoFinal,
                        'monto' => $montoFs,
                        'check_out_web' => trim((string) $fs->check_out) ?: null,
                        'tiempo_pdv_web' => trim((string) $fs->tiempo_pdv) ?: null,
                        'foto_url' => null,
                        'fotos_count' => 0,
                        'tiene_fotos_historicas' => false,
                        'en_plan' => $enPlan,
                        'visitado' => true,
                    ];
                } else {
                    if (strcmp($hIn, (string) $paradas[$key]['hora_web']) < 0) {
                        $paradas[$key]['hora_web'] = $hIn;
                        $paradas[$key]['hora_orden'] = $hIn;
                    }
                    $paradas[$key]['monto'] += $montoFs;
                    if ($tipoFinal === 'PreVenta') {
                        $paradas[$key]['tipo'] = 'PreVenta';
                    }
                    if ($cId && empty($paradas[$key]['client_id'])) {
                        $paradas[$key]['client_id'] = $cId;
                    }
                    if ($enPlan) {
                        $paradas[$key]['en_plan'] = true;
                    }
                }
            }

            // 9. Procesar Marcajes de la App Móvil (tabla visitas)
            $fInicioDia = "{$fechaNorm} 00:00:00";
            $fFinDia = "{$fechaNorm} 23:59:59";

            $filasMovil = Visita::query()
                ->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean])
                ->whereBetween('visited_at', [$fInicioDia, $fFinDia])
                ->orderBy('visited_at', 'asc')
                ->get();

            foreach ($filasMovil as $fm) {
                $cid = $fm->client_id ? (int) $fm->client_id : null;
                if (!$cid && !empty($fm->opportunity_client_name)) {
                    $cid = $resolverClienteId((string) $fm->opportunity_client_name);
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

                $key = null;
                if ($cid) {
                    $key = "id_{$cid}";
                } elseif (!empty($fm->opportunity_client_name)) {
                    $key = "alfa_" . $this->normalizarAlfanumerico((string) $fm->opportunity_client_name);
                } else {
                    $key = "opp_{$fm->id}";
                }

                $enPlan = $cid ? $carteraPlanDia->has($cid) : false;

                if (isset($paradas[$key])) {
                    if ($paradas[$key]['hora_app'] === null || strcmp($horaM, (string) $paradas[$key]['hora_app']) < 0) {
                        $paradas[$key]['hora_app'] = $horaM;
                    }
                    if (strcmp($horaM, (string) $paradas[$key]['hora_orden']) < 0) {
                        $paradas[$key]['hora_orden'] = $horaM;
                    }
                    if ($fotoUrl && empty($paradas[$key]['foto_url'])) {
                        $paradas[$key]['foto_url'] = $fotoUrl;
                        $paradas[$key]['fotos_count']++;
                    }
                    if ($cid && empty($paradas[$key]['client_id'])) {
                        $paradas[$key]['client_id'] = $cid;
                    }
                    if ($enPlan) {
                        $paradas[$key]['en_plan'] = true;
                    }
                } else {
                    $nombreCliente = ($cid && isset($nombreOficialPorId[$cid]))
                        ? $nombreOficialPorId[$cid]
                        : ($fm->opportunity_client_name ?: ($cid ? "CLIENTE #{$cid}" : 'CLIENTE APP S/N'));

                    $paradas[$key] = [
                        'cliente' => $nombreCliente,
                        'client_id' => $cid,
                        'hora_web' => null,
                        'hora_app' => $horaM,
                        'hora_orden' => $horaM,
                        'tipo' => $fm->is_opportunity ? 'Oportunidad' : 'Visita',
                        'monto' => 0.0,
                        'check_out_web' => null,
                        'tiempo_pdv_web' => null,
                        'foto_url' => $fotoUrl,
                        'fotos_count' => $fotoUrl ? 1 : 0,
                        'tiene_fotos_historicas' => false,
                        'en_plan' => $enPlan,
                        'visitado' => true,
                    ];
                }
            }

            // 10. Integrar Clientes de pan_ruteo Programados para Hoy que NO Fueron Visitados
            foreach ($carteraPlanDia as $cid => $planCli) {
                $key = "id_{$cid}";
                if (!isset($paradas[$key])) {
                    $paradas[$key] = [
                        'cliente' => trim((string) $planCli->cliente) ?: "Cliente #{$cid}",
                        'client_id' => $cid,
                        'hora_web' => null,
                        'hora_app' => null,
                        'hora_orden' => '99:99:99', // Se agrupan al final en orden cronológico
                        'tipo' => 'No Visitado',
                        'monto' => 0.0,
                        'check_out_web' => null,
                        'tiempo_pdv_web' => null,
                        'foto_url' => null,
                        'fotos_count' => 0,
                        'tiene_fotos_historicas' => false,
                        'en_plan' => true,
                        'visitado' => false,
                        'direccion' => trim((string) ($planCli->direccion ?? '')) ?: null,
                    ];
                } else {
                    $paradas[$key]['en_plan'] = true;
                }
            }

            // 11. Calcular Totales y Métricas de Auditoría
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

            // 12. Fotos Históricas en la App Móvil
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

            // 13. Ordenar Cronológicamente (Primero los visitados por hora, luego los pendientes)
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
                'vendedor' => $vendedor,
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
     * Normaliza cadenas de texto para búsqueda (minúsculas, sin acentos ni diacríticos).
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
