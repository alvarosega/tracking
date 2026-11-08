<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use App\Actions\Web\Supervisor\Preventas\GetCumplimientoDataAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvancePizarraUnileverAction
{
    public function __construct(
        private readonly GetCumplimientoDataAction $cumplimientoAction,
        private readonly GetAvanceInitDataAction $initAction
    ) {}

    /**
     * Calcula el acumulado mensual y promedio de los 8 KPIs de la Pizarra Modelo Unilever
     * por ruta a partir de los días hábiles transcurridos del mes en curso.
     * Reutiliza exactamente la lógica de GetCumplimientoDataAction para evitar duplicar reglas de negocio.
     *
     * @param string|null $fechaInicio YYYY-MM-DD
     * @param string|null $fechaCorte YYYY-MM-DD
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param bool $estrictoHorario
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        array $canales = [],
        array $rutas = [],
        bool $estrictoHorario = false
    ): array {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        // 1. Fechas del Rango Evaluado
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_preventas')
                ->where('monto_final', '>', 0)
                ->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);

        if (!$fechaInicio || Carbon::parse($fechaInicio)->format('Y-m') !== $carbonCorte->format('Y-m')) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        $mesYm = $carbonCorte->format('Y-m');
        $hoyStr = Carbon::today()->format('Y-m-d');

        // 2. Identificar días hábiles con actividad en el rango
        $fechasConVenta = DB::connection('supervisor')->table('fact_preventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '>', 0)
            ->distinct()
            ->orderBy('fecha_norm', 'asc')
            ->pluck('fecha_norm')
            ->map(fn ($f) => (string) $f)
            ->toArray();

        // Si no hay preventas (ej. inicio de mes), evaluar días hábiles de lunes a sábado
        if (empty($fechasConVenta)) {
            $curr = Carbon::parse($fechaInicio);
            $end = Carbon::parse($fechaCorte);
            while ($curr->lte($end)) {
                if ($curr->dayOfWeekIso <= 6) {
                    $fechasConVenta[] = $curr->format('Y-m-d');
                }
                $curr->addDay();
            }
        }

        $diasEvaluados = $fechasConVenta;
        $totalDiasMes = count($diasEvaluados);

        $diasSemanaAbrev = [
            1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue',
            5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
        ];

        // 3. Evaluar cada día hábil reutilizando GetCumplimientoDataAction (con caché por día cerrado)
        $evaluacionesPorDia = [];
        foreach ($diasEvaluados as $fechaDia) {
            $esHoy = ($fechaDia === $hoyStr);
            $cacheKey = "pizarra_eval_dia_{$fechaDia}_" . ($estrictoHorario ? 'estricto' : 'normal');

            if ($esHoy) {
                // Para el día actual, caché breve de 60 segundos
                $diaData = Cache::remember($cacheKey, 60, fn () => $this->cumplimientoAction->execute(
                    fecha: $fechaDia,
                    canales: [],
                    rutas: [],
                    estrictoHorario: $estrictoHorario,
                    semanasHistoria: 13
                ));
            } else {
                // Para días pasados cerrados, caché permanente de 24 horas
                $diaData = Cache::remember($cacheKey, 86400, fn () => $this->cumplimientoAction->execute(
                    fecha: $fechaDia,
                    canales: [],
                    rutas: [],
                    estrictoHorario: $estrictoHorario,
                    semanasHistoria: 13
                ));
            }

            $evaluacionesPorDia[$fechaDia] = $diaData;
        }

        // 4. Catálogo de Rutas Oficiales desde dim_rutas y pan_ruteo
        $rutasDimRaw = DB::connection('supervisor')->table('dim_rutas')
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(canal)), ''), 'SC') as canal"),
                DB::raw("COALESCE(NULLIF(TRIM(vendedor), ''), 'No Asignado') as vendedor"),
            ])
            ->get();

        $rutasCatalogo = [];
        foreach ($rutasDimRaw as $rd) {
            $r = (string) $rd->ruta_clean;
            if ($r !== '' && !isset($rutasCatalogo[$r])) {
                $rutasCatalogo[$r] = [
                    'canal' => (string) $rd->canal,
                    'vendedor' => (string) $rd->vendedor,
                ];
            }
        }

        // Universo de rutas a filtrar
        $canalesFiltroSet = array_flip(array_map('strtoupper', array_map('trim', array_filter($canales, fn ($c) => $c && !in_array(strtoupper(trim($c)), ['TODOS', 'TODAS'], true)))));
        $rutasFiltroSet = array_flip(array_map('strtoupper', array_map('trim', array_filter($rutas, fn ($r) => $r && !in_array(strtoupper(trim($r)), ['TODAS', 'TODOS'], true)))));

        // Recolectar todas las rutas que aparecen en cualquier día evaluado
        $rutasPresentes = [];
        foreach ($evaluacionesPorDia as $f => $dData) {
            foreach ($dData['rutas'] ?? [] as $rRow) {
                $r = strtoupper(trim((string) ($rRow['ruta'] ?? '')));
                if ($r !== '') {
                    $rutasPresentes[$r] = true;
                    if (!isset($rutasCatalogo[$r])) {
                        $rutasCatalogo[$r] = [
                            'canal' => (string) ($rRow['canal'] ?? 'SC'),
                            'vendedor' => (string) ($rRow['vendedor'] ?? 'No Asignado'),
                        ];
                    }
                }
            }
        }

        $universoRutas = [];
        foreach ($rutasCatalogo as $rName => $rMeta) {
            $rNameUpper = strtoupper(trim($rName));
            $rCanalUpper = strtoupper(trim((string) ($rMeta['canal'] ?? 'SC')));

            if (!empty($canalesFiltroSet) && !isset($canalesFiltroSet[$rCanalUpper])) {
                continue;
            }
            if (!empty($rutasFiltroSet) && !isset($rutasFiltroSet[$rNameUpper])) {
                continue;
            }
            if (!isset($rutasPresentes[$rNameUpper])) {
                continue;
            }
            $universoRutas[] = $rName;
        }

        // 5. Consolidación de Métricas Acumuladas por Ruta y Tabla Cronológica Día a Día
        $rutasConsolidadas = [];

        foreach ($universoRutas as $ruta) {
            $infoDim = $rutasCatalogo[$ruta] ?? ['canal' => 'SC', 'vendedor' => 'No Asignado'];
            $vendedor = $infoDim['vendedor'];
            $canal = $infoDim['canal'];

            $diasDetalle = [];
            $scoresList = [];
            $kpi1List = [];
            $kpi2List = [];
            $kpi3List = [];
            $kpi4List = [];
            $kpi5List = [];
            $kpi6List = [];
            $kpi7List = [];
            $kpi8List = [];

            // Tasas / porcentajes auxiliares
            $kpi2CobPctList = [];
            $kpi3AlcancePctList = [];
            $kpi4EfectPctList = [];
            $kpi6TasaPctList = [];
            $kpi7TasaPctList = [];
            $kpi8TasaPctList = [];

            $diasAprobados80 = 0;
            $diasPerfectos100 = 0;

            foreach ($diasEvaluados as $fechaDia) {
                $carbonD = Carbon::parse($fechaDia);
                $abrev = $diasSemanaAbrev[$carbonD->dayOfWeekIso] ?? 'Otro';
                $diaFormateado = "{$abrev} " . $carbonD->format('d/m');

                $diaData = $evaluacionesPorDia[$fechaDia]['rutas'] ?? [];
                $evalDiaRuta = null;
                foreach ($diaData as $rObj) {
                    if (strtoupper(trim((string) ($rObj['ruta'] ?? ''))) === strtoupper(trim($ruta))) {
                        $evalDiaRuta = $rObj;
                        break;
                    }
                }

                if (!$evalDiaRuta) {
                    continue;
                }

                if ($vendedor === 'No Asignado' && !empty($evalDiaRuta['vendedor']) && $evalDiaRuta['vendedor'] !== 'No Asignado') {
                    $vendedor = $evalDiaRuta['vendedor'];
                }

                $score = (int) ($evalDiaRuta['score_total'] ?? 0);
                $scoresList[] = $score;
                if ($score >= 80) {
                    $diasAprobados80++;
                }
                if ($score >= 100) {
                    $diasPerfectos100++;
                }

                // Extracción de puntos de cada KPI
                $p1 = (float) ($evalDiaRuta['kpi1_horario']['puntos'] ?? 0);
                $p2 = (float) ($evalDiaRuta['kpi2_visitas']['puntos'] ?? 0);
                $p3 = (float) ($evalDiaRuta['kpi3_objetivo']['puntos'] ?? 0);
                $p4 = (float) ($evalDiaRuta['kpi4_efectividad']['puntos'] ?? 0);
                $p5 = (float) ($evalDiaRuta['kpi5_compra_minima']['puntos'] ?? 0);
                $p6 = (float) ($evalDiaRuta['kpi6_reposicion']['puntos'] ?? 0);
                $p7 = (float) ($evalDiaRuta['kpi7_ampliacion']['puntos'] ?? 0);
                $p8 = (float) ($evalDiaRuta['kpi8_covers']['puntos'] ?? 0);

                $kpi1List[] = $p1;
                $kpi2List[] = $p2;
                $kpi3List[] = $p3;
                $kpi4List[] = $p4;
                $kpi5List[] = $p5;
                $kpi6List[] = $p6;
                $kpi7List[] = $p7;
                $kpi8List[] = $p8;

                $cobPct = (float) ($evalDiaRuta['kpi2_visitas']['cobertura_pct'] ?? 0);
                $alcPct = (float) ($evalDiaRuta['kpi3_objetivo']['alcance_pct'] ?? 0);
                $efectPct = (float) ($evalDiaRuta['kpi4_efectividad']['efectividad_pct'] ?? 0);
                $repoPct = (float) ($evalDiaRuta['kpi6_reposicion']['tasa_pct'] ?? 0);
                $ampPct = (float) ($evalDiaRuta['kpi7_ampliacion']['tasa_pct'] ?? 0);
                $covPct = (float) ($evalDiaRuta['kpi8_covers']['tasa_pct'] ?? 0);

                $kpi2CobPctList[] = $cobPct;
                $kpi3AlcancePctList[] = $alcPct;
                $kpi4EfectPctList[] = $efectPct;
                $kpi6TasaPctList[] = $repoPct;
                $kpi7TasaPctList[] = $ampPct;
                $kpi8TasaPctList[] = $covPct;

                $diasDetalle[] = [
                    'fecha' => $fechaDia,
                    'dia_semana' => $abrev,
                    'dia_formateado' => $diaFormateado,
                    'score_total' => $score,
                    'aprobado' => ($score >= 80),
                    'perfecto' => ($score >= 100),
                    'kpi1_horario' => [
                        'pts' => $p1,
                        'puntos' => $p1,
                        'cumple' => (bool) ($evalDiaRuta['kpi1_horario']['cumple'] ?? false),
                        'hora' => (string) ($evalDiaRuta['kpi1_horario']['primer_checkin'] ?? '-'),
                        'retraso' => (int) ($evalDiaRuta['kpi1_horario']['retraso_minutos'] ?? 0),
                    ],
                    'kpi2_visitas' => [
                        'pts' => $p2,
                        'puntos' => $p2,
                        'cumple' => (bool) ($evalDiaRuta['kpi2_visitas']['cumple'] ?? false),
                        'cartera' => (int) ($evalDiaRuta['kpi2_visitas']['cartera_plan'] ?? 0),
                        'visitados' => (int) ($evalDiaRuta['kpi2_visitas']['total_visitados'] ?? 0),
                        'cobertura_pct' => $cobPct,
                    ],
                    'kpi3_objetivo' => [
                        'pts' => $p3,
                        'puntos' => $p3,
                        'cumple' => (bool) ($evalDiaRuta['kpi3_objetivo']['cumple'] ?? false),
                        'cuota_diaria' => (float) ($evalDiaRuta['kpi3_objetivo']['cuota_diaria'] ?? 0),
                        'venta_hoy' => (float) ($evalDiaRuta['kpi3_objetivo']['venta_hoy'] ?? 0),
                        'alcance_pct' => $alcPct,
                    ],
                    'kpi4_efectividad' => [
                        'pts' => $p4,
                        'puntos' => $p4,
                        'cumple' => (bool) ($evalDiaRuta['kpi4_efectividad']['cumple'] ?? false),
                        'compradores' => (int) ($evalDiaRuta['kpi4_efectividad']['compradores'] ?? 0),
                        'efectividad_pct' => $efectPct,
                    ],
                    'kpi5_compra_minima' => [
                        'pts' => $p5,
                        'puntos' => $p5,
                        'cumple' => (bool) ($evalDiaRuta['kpi5_compra_minima']['cumple'] ?? false),
                    ],
                    'kpi6_reposicion' => [
                        'pts' => $p6,
                        'puntos' => $p6,
                        'cumple' => (bool) ($evalDiaRuta['kpi6_reposicion']['cumple'] ?? false),
                        'tasa_pct' => $repoPct,
                    ],
                    'kpi7_ampliacion' => [
                        'pts' => $p7,
                        'puntos' => $p7,
                        'cumple' => (bool) ($evalDiaRuta['kpi7_ampliacion']['cumple'] ?? false),
                        'tasa_pct' => $ampPct,
                    ],
                    'kpi8_covers' => [
                        'pts' => $p8,
                        'puntos' => $p8,
                        'cumple' => (bool) ($evalDiaRuta['kpi8_covers']['cumple'] ?? false),
                        'tasa_pct' => $covPct,
                    ],
                ];
            }

            $diasCont = count($scoresList);
            $scorePromedio = $diasCont > 0 ? round(array_sum($scoresList) / $diasCont, 1) : 0.0;
            $pctAprobacion = $diasCont > 0 ? round(($diasAprobados80 / $diasCont) * 100, 1) : 0.0;

            $rutasConsolidadas[] = [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'canal' => $canal,
                'dias_evaluados' => $diasCont,
                'dias_aprobados' => $diasAprobados80,
                'dias_perfectos' => $diasPerfectos100,
                'pct_aprobacion' => $pctAprobacion,
                'score_promedio' => $scorePromedio,
                'score_total' => $scorePromedio,

                // Promedios por KPI (Puntos sobre el máximo)
                'prom_kpi1_horario' => $diasCont > 0 ? round(array_sum($kpi1List) / $diasCont, 1) : 0.0,
                'prom_kpi2_visitas' => $diasCont > 0 ? round(array_sum($kpi2List) / $diasCont, 1) : 0.0,
                'prom_kpi3_objetivo' => $diasCont > 0 ? round(array_sum($kpi3List) / $diasCont, 1) : 0.0,
                'prom_kpi4_efectividad' => $diasCont > 0 ? round(array_sum($kpi4List) / $diasCont, 1) : 0.0,
                'prom_kpi5_compra_minima' => $diasCont > 0 ? round(array_sum($kpi5List) / $diasCont, 1) : 0.0,
                'prom_kpi6_reposicion' => $diasCont > 0 ? round(array_sum($kpi6List) / $diasCont, 1) : 0.0,
                'prom_kpi7_ampliacion' => $diasCont > 0 ? round(array_sum($kpi7List) / $diasCont, 1) : 0.0,
                'prom_kpi8_covers' => $diasCont > 0 ? round(array_sum($kpi8List) / $diasCont, 1) : 0.0,

                // Tasas Promedio (%)
                'prom_cobertura_visitas_pct' => $diasCont > 0 ? round(array_sum($kpi2CobPctList) / $diasCont, 1) : 0.0,
                'prom_alcance_objetivo_pct' => $diasCont > 0 ? round(array_sum($kpi3AlcancePctList) / $diasCont, 1) : 0.0,
                'prom_efectividad_pct' => $diasCont > 0 ? round(array_sum($kpi4EfectPctList) / $diasCont, 1) : 0.0,
                'prom_reposicion_tasa_pct' => $diasCont > 0 ? round(array_sum($kpi6TasaPctList) / $diasCont, 1) : 0.0,
                'prom_ampliacion_tasa_pct' => $diasCont > 0 ? round(array_sum($kpi7TasaPctList) / $diasCont, 1) : 0.0,
                'prom_covers_tasa_pct' => $diasCont > 0 ? round(array_sum($kpi8TasaPctList) / $diasCont, 1) : 0.0,

                // Desglose cronológico día a día para el acordeón de la ruta
                'dias' => $diasDetalle,
            ];
        }

        // Ordenar rutas por Score Promedio Descendente (Ranking)
        usort($rutasConsolidadas, fn ($a, $b) => ($b['score_promedio'] <=> $a['score_promedio']) ?: strcmp($a['ruta'], $b['ruta']));

        foreach ($rutasConsolidadas as $idx => &$rItem) {
            $rItem['rank'] = $idx + 1;
        }
        unset($rItem);

        // 6. Totales y Promedios Consolidados Globales
        $totalRutas = count($rutasConsolidadas);
        $totales = [
            'total_rutas' => $totalRutas,
            'total_dias_evaluados' => $totalDiasMes,
            'score_promedio' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'score_promedio')) / $totalRutas, 1) : 0.0,
            'dias_aprobados' => array_sum(array_column($rutasConsolidadas, 'dias_aprobados')),
            'pct_aprobacion' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'pct_aprobacion')) / $totalRutas, 1) : 0.0,
            'prom_kpi1_horario' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi1_horario')) / $totalRutas, 1) : 0.0,
            'prom_kpi2_visitas' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi2_visitas')) / $totalRutas, 1) : 0.0,
            'prom_kpi3_objetivo' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi3_objetivo')) / $totalRutas, 1) : 0.0,
            'prom_kpi4_efectividad' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi4_efectividad')) / $totalRutas, 1) : 0.0,
            'prom_kpi5_compra_minima' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi5_compra_minima')) / $totalRutas, 1) : 0.0,
            'prom_kpi6_reposicion' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi6_reposicion')) / $totalRutas, 1) : 0.0,
            'prom_kpi7_ampliacion' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi7_ampliacion')) / $totalRutas, 1) : 0.0,
            'prom_kpi8_covers' => $totalRutas > 0 ? round(array_sum(array_column($rutasConsolidadas, 'prom_kpi8_covers')) / $totalRutas, 1) : 0.0,
        ];

        return [
            'modo' => 'kpi',
            'kpi' => 'pizarra_unilever',
            'kpi_nombre' => '8. Pizarra Unilever',
            'kpi_tipo' => 'SCORE',
            'kpi_unidad' => 'Pts',
            'modo_temporal' => 'acumulado',
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'mes_ym' => $mesYm,
            'total_dias_mes' => $totalDiasMes,
            'fechas_evaluadas' => $diasEvaluados,
            'canales' => $canales,
            'totales' => $totales,
            'rutas' => $rutasConsolidadas,
        ];
    }
}
