<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventaDiariaDataAction
{
    private const MESES_ESP_ABR = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    private const MESES_ESP_COMPLETO = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const DIAS_MAP = [
        1 => ['lunes'],
        2 => ['martes'],
        3 => ['miercoles', 'miércoles'],
        4 => ['jueves'],
        5 => ['viernes'],
        6 => ['sabado', 'sábado'],
        7 => ['domingo'],
    ];

    public function __construct(
        private readonly GetCatalogoRutasAction $catalogoAction
    ) {}

    /**
     * Devuelve el dashboard diario de preventa con métricas consolidadas y matriz por ruta.
     *
     * @param string|null $fecha
     * @param string|null $canal
     * @return array<string, mixed>
     */
    public function execute(?string $fecha = null, ?string $canal = null): array
    {
        // 1. Fechas y canales disponibles
        $fechasDisponibles = $this->obtenerFechasDisponibles();
        $canalesDisponibles = $this->obtenerCanalesDisponibles();

        if (empty($fechasDisponibles)) {
            return [
                'error' => 'No hay datos de preventas cargados en el repositorio.',
                'fechas_disponibles' => [],
                'canales_disponibles' => ['TODOS'],
            ];
        }

        $fechaNorm = ($fecha && trim($fecha) !== '') ? trim($fecha) : $fechasDisponibles[0];

        $carbonFecha = Carbon::parse($fechaNorm);
        $year = (int) $carbonFecha->year;
        $month = (int) $carbonFecha->month;
        $day = (int) $carbonFecha->day;

        $nombresDias = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ];
        $diaSemanaDisplay = $nombresDias[$carbonFecha->dayOfWeekIso] ?? 'Lunes';

        // 2. Días hábiles y calendario
        $calendario = $this->calcularDiasHabiles($year, $month, $fechaNorm);
        $diasRestantes = (int) ($calendario['dias_restantes'] ?? 1);
        $esMesCerrado = (bool) ($calendario['es_mes_cerrado'] ?? false);

        // Regla Preventa: Desfase 24h (-1 día por preventa no facturable el último día) y -1 día seguro
        $diasHabilesDivisor = max($diasRestantes - 2, 1);

        // 3. Matriz de rutas
        $matriz = $this->obtenerMatrizRutas($fechaNorm, $carbonFecha->dayOfWeekIso, $year, $month, $canal, $esMesCerrado, $diasHabilesDivisor);

        // 4. Totales y KPIs globales
        $planTotal = (int) array_sum(array_column($matriz, 'plan_visita'));
        $vPlanTotal = (int) array_sum(array_column($matriz, 'visitas_plan'));
        $vExtraTotal = (int) array_sum(array_column($matriz, 'visitas_extra'));
        $totalVisitadosGlob = (int) array_sum(array_column($matriz, 'total_visitados'));
        $preventaTotal = (int) array_sum(array_column($matriz, 'preventa'));
        $volumenTotal = round((float) array_sum(array_column($matriz, 'volumen_bs')), 2);
        $objetivoDiaTotal = round((float) array_sum(array_column($matriz, 'objetivo_dia_bs')), 2);

        $dropSizeTotal = $preventaTotal > 0 ? round($volumenTotal / $preventaTotal, 2) : 0.0;
        $cumplimientoDiaPct = $objetivoDiaTotal > 0 ? round(($volumenTotal / $objetivoDiaTotal) * 100, 1) : 0.0;
        $coberturaPct = $planTotal > 0 ? round(($vPlanTotal / $planTotal) * 100, 1) : 0.0;
        $efectividadPct = $planTotal > 0 ? round(($preventaTotal / $planTotal) * 100, 1) : 0.0;

        return [
            'fecha_seleccionada' => $fechaNorm,
            'fecha_display' => sprintf('%02d/%02d/%04d', $day, $month, $year),
            'dia_semana' => $diaSemanaDisplay,
            'canal_seleccionado' => ($canal && $canal !== 'TODOS') ? $canal : 'TODOS',
            'fechas_disponibles' => $fechasDisponibles,
            'canales_disponibles' => array_merge(['TODOS'], $canalesDisponibles),
            'calendario' => $calendario,
            'kpis' => [
                'volumen_bs' => $volumenTotal,
                'objetivo_dia_bs' => $objetivoDiaTotal,
                'cumplimiento_dia_pct' => $cumplimientoDiaPct,
                'preventa' => $preventaTotal,
                'drop_size' => $dropSizeTotal,
                'plan_total' => $planTotal,
                'visitas_plan_total' => $vPlanTotal,
                'visitas_extra_total' => $vExtraTotal,
                'total_visitados' => $totalVisitadosGlob,
                'cobertura_pct' => $coberturaPct,
                'efectividad_pct' => $efectividadPct,
            ],
            'matriz_rutas' => $matriz,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function obtenerFechasDisponibles(): array
    {
        try {
            $rows = DB::connection('supervisor')->table('fact_preventas')
                ->whereNotNull('fecha_norm')
                ->where('fecha_norm', '!=', '1900-01-01')
                ->where('monto_final', '>', 0)
                ->selectRaw('DISTINCT CAST(fecha_norm AS CHAR) AS f_str')
                ->orderByDesc('f_str')
                ->limit(60)
                ->pluck('f_str')
                ->toArray();

            return $rows;
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo fechas disponibles en preventa diaria: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function obtenerCanalesDisponibles(): array
    {
        try {
            $rows = DB::connection('supervisor')->table('dim_rutas')
                ->whereNotNull('canal')
                ->whereRaw("TRIM(canal) != ''")
                ->distinct()
                ->orderBy('canal')
                ->pluck('canal')
                ->toArray();

            return $rows;
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo canales en preventa diaria: ' . $e->getMessage());
            return ['TDB', 'MAYORISTA', 'PROVINCIA'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function calcularDiasHabiles(int $year, int $month, string $fechaCorte): array
    {
        $hoy = Carbon::today();
        $esMesCerrado = ($year < $hoy->year) || ($year === $hoy->year && $month < $hoy->month);
        $diasTotales = 25;

        try {
            $rowCal = DB::connection('supervisor')->table('dim_calendario_mensual')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($rowCal && isset($rowCal->dias_habiles)) {
                $diasTotales = (int) $rowCal->dias_habiles;
            }
        } catch (\Throwable) {
            // Default 25
        }

        if ($esMesCerrado) {
            return [
                'dias_totales' => $diasTotales,
                'dias_transcurridos' => $diasTotales,
                'dias_restantes' => 0,
                'es_mes_cerrado' => true,
            ];
        }

        $fInicioMes = sprintf('%04d-%02d-01', $year, $month);
        $diasOperados = 0;

        try {
            $diasOperados = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', '>=', $fInicioMes)
                ->where('fecha_norm', '<=', $fechaCorte)
                ->where('monto_final', '>', 0)
                ->distinct()
                ->count('fecha_norm');
        } catch (\Throwable) {
            $diasOperados = 1;
        }

        $diasRestantes = max(0, $diasTotales - $diasOperados);

        return [
            'dias_totales' => $diasTotales,
            'dias_transcurridos' => $diasOperados,
            'dias_restantes' => $diasRestantes,
            'es_mes_cerrado' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerMatrizRutas(
        string $fechaNorm,
        int $dayOfWeekIso,
        int $year,
        int $month,
        ?string $canalFiltro,
        bool $esMesCerrado,
        int $diasHabilesDivisor
    ): array {
        $tuplaDias = self::DIAS_MAP[$dayOfWeekIso] ?? ['lunes'];
        $fInicioMes = sprintf('%04d-%02d-01', $year, $month);

        // 1. Universo de rutas
        $rutasQuery = DB::connection('supervisor')->table('dim_rutas')
            ->select([
                'ruta',
                'canal',
                'vendedor',
                DB::raw("TRIM(LOWER(COALESCE(vendedor_norm, vendedor))) as vendedor_norm"),
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
            ]);

        if ($canalFiltro && $canalFiltro !== 'TODOS') {
            $rutasQuery->where('canal', $canalFiltro);
        }

        $universoRutas = $rutasQuery->orderBy('ruta')->get();
        if ($universoRutas->isEmpty()) {
            return [];
        }

        $rutasCleanList = $universoRutas->pluck('ruta_clean')->toArray();
        $rutasOrigList = $universoRutas->pluck('ruta')->toArray();

        // 2. Clientes en plan del día (pan_ruteo)
        $conteoPlan = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasCleanList)
            ->where('cliente_id', '>', 0)
            ->where(function ($q) use ($tuplaDias): void {
                $q->whereIn(DB::raw("LOWER(TRIM(dia_norm))"), $tuplaDias)
                  ->orWhereIn(DB::raw("LOWER(TRIM(dia))"), $tuplaDias);
            })
            ->whereRaw("LOWER(TRIM(estado)) = 'activo'")
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COUNT(DISTINCT cliente_id) as plan"),
            ])
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
            ->pluck('plan', 'ruta_clean')
            ->toArray();

        // 3. Plan hoy detallado para distinguir visitas plan vs extra
        $planHoyClientes = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasCleanList)
            ->where('cliente_id', '>', 0)
            ->where(function ($q) use ($tuplaDias): void {
                $q->whereIn(DB::raw("LOWER(TRIM(dia_norm))"), $tuplaDias)
                  ->orWhereIn(DB::raw("LOWER(TRIM(dia))"), $tuplaDias);
            })
            ->whereRaw("LOWER(TRIM(estado)) = 'activo'")
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("TRIM(LOWER(COALESCE(cliente_norm, cliente))) as cliente_norm"),
            ])
            ->get();

        $planSetPorRuta = [];
        foreach ($planHoyClientes as $ph) {
            $r = $ph->ruta_clean;
            $cn = $ph->cliente_norm;
            if ($r && $cn) {
                $planSetPorRuta[$r][$cn] = true;
            }
        }

        // 4. Visitas registradas en fact_visitas
        $vendedoresNorm = $universoRutas->pluck('vendedor_norm')->filter()->unique()->toArray();
        $vendedorToRutaClean = [];
        foreach ($universoRutas as $ur) {
            if ($ur->vendedor_norm) {
                $vendedorToRutaClean[$ur->vendedor_norm] = $ur->ruta_clean;
            }
        }

        $visitasVendedor = DB::connection('supervisor')->table('fact_visitas')
            ->where('fecha_norm', $fechaNorm)
            ->whereNotNull('cliente')
            ->whereRaw("TRIM(COALESCE(cliente_norm, cliente)) != ''")
            ->whereIn(DB::raw("TRIM(LOWER(COALESCE(empleado_norm, empleado)))"), $vendedoresNorm)
            ->select([
                DB::raw("TRIM(LOWER(COALESCE(empleado_norm, empleado))) as emp_norm"),
                DB::raw("TRIM(LOWER(COALESCE(cliente_norm, cliente))) as cliente_norm"),
            ])
            ->distinct()
            ->get();

        $visitasPlanPorRuta = [];
        $visitasExtraPorRuta = [];
        foreach ($visitasVendedor as $vv) {
            $rClean = $vendedorToRutaClean[$vv->emp_norm] ?? null;
            if (!$rClean) {
                continue;
            }
            $cn = $vv->cliente_norm;
            if (isset($planSetPorRuta[$rClean][$cn])) {
                $visitasPlanPorRuta[$rClean] = ($visitasPlanPorRuta[$rClean] ?? 0) + 1;
            } else {
                $visitasExtraPorRuta[$rClean] = ($visitasExtraPorRuta[$rClean] ?? 0) + 1;
            }
        }

        // 5. Preventa efectiva del día (fact_preventas)
        $preventaEfectiva = DB::connection('supervisor')->table('fact_preventas')
            ->where('fecha_norm', $fechaNorm)
            ->where('monto_final', '>', 0)
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasCleanList)
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COUNT(DISTINCT cliente_id) as pdvs_con_venta"),
                DB::raw("SUM(monto_final) as venta_hoy"),
            ])
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
            ->get()
            ->keyBy('ruta_clean');

        // 6. Ventas acumuladas previas en el mes (fact_ventas)
        $ventasPrevias = DB::connection('supervisor')->table('fact_ventas')
            ->where('fecha_norm', '>=', $fInicioMes)
            ->where('fecha_norm', '<', $fechaNorm)
            ->where(function ($q): void {
                $q->whereNull('revertida')
                  ->orWhere(function ($sub): void {
                      $sub->where('revertida', '!=', 'Si')
                          ->where('revertida', '!=', 'SI');
                  });
            })
            ->where('monto_final', '>', 0)
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasCleanList)
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("SUM(monto_final) as venta_acumulada_previa"),
            ])
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
            ->pluck('venta_acumulada_previa', 'ruta_clean')
            ->toArray();

        // 7. Metas mensuales (fact_metas)
        $patronesMes = [
            self::MESES_ESP_ABR[$month] ?? '',
            self::MESES_ESP_COMPLETO[$month] ?? '',
            sprintf('%04d-%02d', $year, $month),
            (string) $month,
        ];

        $metas = [];
        try {
            $metasRaw = DB::connection('supervisor')->table('fact_metas')
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasCleanList)
                ->where(function ($q) use ($patronesMes, $month): void {
                    $q->whereIn(DB::raw("LOWER(TRIM(mes))"), array_filter($patronesMes))
                      ->orWhere('mes', 'like', '%' . (self::MESES_ESP_ABR[$month] ?? 'xxx') . '%');
                })
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                    DB::raw("MAX(CAST(objetivo_de_venta AS DECIMAL(12,2))) as meta_mes"),
                ])
                ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
                ->pluck('meta_mes', 'ruta_clean')
                ->toArray();

            $metas = $metasRaw;
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_metas en preventa diaria: ' . $e->getMessage());
        }

        // 8. Construcción consolidada de la matriz
        $resultado = [];
        foreach ($universoRutas as $ur) {
            $rc = $ur->ruta_clean;
            $plan = (int) ($conteoPlan[$rc] ?? 0);
            $vPlan = (int) ($visitasPlanPorRuta[$rc] ?? 0);
            $vExtra = (int) ($visitasExtraPorRuta[$rc] ?? 0);
            $totalVisitados = $vPlan + $vExtra;

            $pe = $preventaEfectiva->get($rc);
            $preventa = $pe ? (int) $pe->pdvs_con_venta : 0;
            $volumenBs = $pe ? round((float) $pe->venta_hoy, 2) : 0.0;

            $metaMes = isset($metas[$rc]) ? round((float) $metas[$rc], 2) : 0.0;
            $ventaAcumPrevia = isset($ventasPrevias[$rc]) ? round((float) $ventasPrevias[$rc], 2) : 0.0;

            $efectividadPct = $plan > 0 ? round(($preventa / $plan) * 100, 1) : 0.0;
            $coberturaPct = $plan > 0 ? round(($vPlan / $plan) * 100, 1) : 0.0;

            $saldoObjetivo = max(0.0, $metaMes - $ventaAcumPrevia);
            $objetivoDiaBs = ($metaMes > 0 && !$esMesCerrado) ? round($saldoObjetivo / $diasHabilesDivisor, 2) : 0.0;
            $cumplimientoDiaPct = $objetivoDiaBs > 0 ? round(($volumenBs / $objetivoDiaBs) * 100, 1) : 0.0;
            $dropSize = $preventa > 0 ? round($volumenBs / $preventa, 2) : 0.0;

            $resultado[] = [
                'ruta' => $ur->ruta,
                'canal' => $ur->canal,
                'vendedor' => $ur->vendedor ?: 'Sin asignar',
                'plan_visita' => $plan,
                'visitas_plan' => $vPlan,
                'visitas_extra' => $vExtra,
                'total_visitados' => $totalVisitados,
                'preventa' => $preventa,
                'efectividad_pct' => $efectividadPct,
                'cobertura_pct' => $coberturaPct,
                'alerta_cobertura' => $vPlan < $plan,
                'volumen_bs' => $volumenBs,
                'drop_size' => $dropSize,
                'objetivo_dia_bs' => $objetivoDiaBs,
                'cumplimiento_dia_pct' => $cumplimientoDiaPct,
            ];
        }

        return $resultado;
    }
}

