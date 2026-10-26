<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class GetAvanceHistoricoPreventasAction
{
    private const DIAS_MAP = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        0 => 'domingo',
    ];

    private const NOMBRES_DIAS_ABR = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mié',
        4 => 'Jue',
        5 => 'Vie',
        6 => 'Sáb',
        0 => 'Dom',
    ];

    /**
     * Calcula la matriz histórica de preventas por rango de fechas (estilo Python analisis).
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaCorte
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        array $canales = [],
        array $rutas = []
    ): array {
        // 1. Obtener límites reales de fechas en fact_preventas
        $limites = DB::connection('supervisor')->table('fact_preventas')
            ->whereNotNull('fecha_norm')
            ->where('fecha_norm', '!=', '1900-01-01')
            ->where('monto_final', '>', 0)
            ->selectRaw('MIN(CAST(fecha_norm AS CHAR)) as fecha_min, MAX(CAST(fecha_norm AS CHAR)) as fecha_max')
            ->first();

        $fechaMaxDb = $limites->fecha_max ?? date('Y-m-d');
        $fechaMinDb = $limites->fecha_min ?? date('Y-m-d');

        if (!$fechaCorte) {
            $fechaCorte = $fechaMaxDb;
        }

        if (!$fechaInicio) {
            $carbonCorte = Carbon::parse($fechaCorte);
            $carbonIni = $carbonCorte->copy()->subDays(6);
            if ($carbonIni->lt(Carbon::parse($fechaMinDb))) {
                $carbonIni = Carbon::parse($fechaMinDb);
            }
            $fechaInicio = $carbonIni->format('Y-m-d');
        }

        // Si inicio > corte, invertimos
        if ($fechaInicio > $fechaCorte) {
            $temp = $fechaInicio;
            $fechaInicio = $fechaCorte;
            $fechaCorte = $temp;
        }

        // 2. Construir lista de días (omitiendo domingos, ya que no son días laborables de preventa)
        $diasColumnas = [];
        $unionsFechas = [];
        $paramsFechas = [];

        $period = CarbonPeriod::create($fechaInicio, $fechaCorte);
        foreach ($period as $date) {
            $dayOfWeek = $date->dayOfWeek; // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
            if ($dayOfWeek !== 0) {
                $fIso = $date->format('Y-m-d');
                $diaNorm = self::DIAS_MAP[$dayOfWeek] ?? 'lunes';
                $diaAbr = self::NOMBRES_DIAS_ABR[$dayOfWeek] ?? 'Día';
                $diaLabel = sprintf('%s %02d/%02d', $diaAbr, $date->day, $date->month);

                $diasColumnas[] = [
                    'fecha' => $fIso,
                    'dia_norm' => $diaNorm,
                    'dia_label' => $diaLabel,
                    'weekday' => $dayOfWeek,
                ];

                $unionsFechas[] = 'SELECT ? AS fecha_norm, ? AS dia_norm';
                $paramsFechas[] = $fIso;
                $paramsFechas[] = $diaNorm;
            }
        }

        if (empty($diasColumnas)) {
            return [
                'fecha_inicio' => $fechaInicio,
                'fecha_corte' => $fechaCorte,
                'fecha_min_limite' => $fechaMinDb,
                'fecha_max_limite' => $fechaMaxDb,
                'rango_label' => "{$fechaInicio} al {$fechaCorte} (0 días)",
                'dias_columnas' => [],
                'canales_disponibles' => ['TODOS'],
                'dias_calendario' => null,
                'kpis' => [
                    'total_plan' => 0,
                    'total_visitados' => 0,
                    'total_preventa' => 0,
                    'total_sobre_umbral' => 0,
                    'total_bajo_umbral' => 0,
                    'total_volumen_bs' => 0.0,
                    'efectividad_global_pct' => 0.0,
                ],
                'matriz' => [],
            ];
        }

        $tablaFechasSql = implode(' UNION ALL ', $unionsFechas);

        // 3. Filtros por canal
        $whereCanal = '';
        $paramsCanal = [];
        $canalesLimpios = array_values(array_filter(array_map('trim', $canales), fn ($c) => $c !== '' && strtoupper($c) !== 'TODOS'));
        if (!empty($canalesLimpios)) {
            $placeholders = implode(',', array_fill(0, count($canalesLimpios), '?'));
            $whereCanal = "WHERE TRIM(UPPER(dr.canal)) IN ({$placeholders})";
            $paramsCanal = array_map('strtoupper', $canalesLimpios);
        }

        // 4. Query CTE optimizado exactamente como en Python analisis
        $sql = "
        WITH universo_rutas AS (
            SELECT 
                dr.ruta, 
                TRIM(UPPER(dr.ruta)) AS ruta_clean, 
                dr.canal, 
                dr.vendedor, 
                TRIM(LOWER(COALESCE(dr.vendedor_norm, dr.vendedor))) AS vendedor_norm
            FROM dim_rutas dr
            {$whereCanal}
        ),
        fechas_tabla AS (
            {$tablaFechasSql}
        ),
        plan_dias AS (
            SELECT 
                TRIM(UPPER(p.ruta)) AS ruta_clean, 
                ft.fecha_norm, 
                COUNT(DISTINCT p.cliente_id) AS plan
            FROM pan_ruteo p
            INNER JOIN fechas_tabla ft 
                ON LOWER(TRIM(COALESCE(p.dia_norm, p.dia))) = ft.dia_norm
            WHERE LOWER(TRIM(p.estado)) = 'activo' 
              AND p.cliente_id > 0
            GROUP BY TRIM(UPPER(p.ruta)), ft.fecha_norm
        ),
        visitas_rutas AS (
            SELECT 
                ur.ruta_clean, 
                v.fecha_norm, 
                COUNT(DISTINCT TRIM(LOWER(v.cliente_norm))) AS total_visitados
            FROM fact_visitas v
            INNER JOIN universo_rutas ur 
                ON TRIM(LOWER(COALESCE(v.empleado_norm, v.empleado))) = ur.vendedor_norm
            WHERE v.fecha_norm >= ? AND v.fecha_norm <= ?
              AND v.cliente_norm IS NOT NULL 
              AND TRIM(v.cliente_norm) != ''
            GROUP BY ur.ruta_clean, v.fecha_norm
        ),
        preventas_clientes AS (
            SELECT 
                TRIM(UPPER(p.ruta)) AS ruta_clean, 
                p.fecha_norm, 
                p.cliente_id,
                SUM(p.monto_final) AS monto_acumulado_cliente
            FROM fact_preventas p
            WHERE p.fecha_norm >= ? AND p.fecha_norm <= ?
              AND p.monto_final > 0
            GROUP BY TRIM(UPPER(p.ruta)), p.fecha_norm, p.cliente_id
        ),
        preventas_rutas AS (
            SELECT 
                ruta_clean, 
                fecha_norm, 
                COUNT(DISTINCT cliente_id) AS con_preventa,
                COUNT(DISTINCT CASE WHEN monto_acumulado_cliente >= 70.0 THEN cliente_id END) AS sobre_umbral,
                COUNT(DISTINCT CASE WHEN monto_acumulado_cliente < 70.0 THEN cliente_id END) AS bajo_umbral,
                SUM(monto_acumulado_cliente) AS volumen_bs
            FROM preventas_clientes
            GROUP BY ruta_clean, fecha_norm
        )
        SELECT
            ur.ruta, 
            ur.canal, 
            COALESCE(ur.vendedor, 'Sin asignar') AS vendedor,
            ft.fecha_norm,
            COALESCE(pd.plan, 0)                  AS plan,
            COALESCE(vr.total_visitados, 0)      AS visitados,
            COALESCE(pr.con_preventa, 0)          AS con_preventa,
            COALESCE(pr.sobre_umbral, 0)          AS sobre_umbral,
            COALESCE(pr.bajo_umbral, 0)           AS bajo_umbral,
            COALESCE(pr.volumen_bs, 0.0)          AS volumen_bs
        FROM universo_rutas ur
        CROSS JOIN fechas_tabla ft
        LEFT JOIN plan_dias pd       ON pd.ruta_clean = ur.ruta_clean AND pd.fecha_norm = ft.fecha_norm
        LEFT JOIN visitas_rutas vr   ON vr.ruta_clean = ur.ruta_clean AND vr.fecha_norm = ft.fecha_norm
        LEFT JOIN preventas_rutas pr ON pr.ruta_clean = ur.ruta_clean AND pr.fecha_norm = ft.fecha_norm
        ORDER BY ur.ruta, ft.fecha_norm
        ";

        $allParams = array_merge(
            $paramsCanal,
            $paramsFechas,
            [$fechaInicio, $fechaCorte, $fechaInicio, $fechaCorte]
        );

        $rawRows = DB::connection('supervisor')->select($sql, $allParams);

        // 5. Agrupamiento en matriz por ruta y consolidación de métricas
        $rutasMap = [];
        $canalesSet = [];

        foreach ($rawRows as $r) {
            $ruta = (string) $r->ruta;
            $canal = (string) ($r->canal ?? 'GENERAL');
            if ($canal !== '') {
                $canalesSet[$canal] = true;
            }

            if (!isset($rutasMap[$ruta])) {
                $rutasMap[$ruta] = [
                    'ruta' => $ruta,
                    'canal' => $canal,
                    'vendedor' => (string) $r->vendedor,
                    'dias' => [],
                    'tot_plan' => 0,
                    'tot_visitados' => 0,
                    'tot_preventa' => 0,
                    'tot_sobre_umbral' => 0,
                    'tot_bajo_umbral' => 0,
                    'tot_volumen_bs' => 0.0,
                    'efectividad_promedio_pct' => 0.0,
                ];
            }

            $plan = (int) $r->plan;
            $visitados = (int) $r->visitados;
            $conPrev = (int) $r->con_preventa;
            $sobreU = (int) $r->sobre_umbral;
            $bajoU = (int) $r->bajo_umbral;
            $volBs = round((float) $r->volumen_bs, 2);
            $efect = $plan > 0 ? round(($conPrev / $plan) * 100, 1) : 0.0;

            $fNorm = (string) $r->fecha_norm;
            $rutasMap[$ruta]['dias'][$fNorm] = [
                'plan' => $plan,
                'visitados' => $visitados,
                'con_preventa' => $conPrev,
                'sobre_umbral' => $sobreU,
                'bajo_umbral' => $bajoU,
                'efectividad_pct' => $efect,
                'volumen_bs' => $volBs,
            ];

            $rutasMap[$ruta]['tot_plan'] += $plan;
            $rutasMap[$ruta]['tot_visitados'] += $visitados;
            $rutasMap[$ruta]['tot_preventa'] += $conPrev;
            $rutasMap[$ruta]['tot_sobre_umbral'] += $sobreU;
            $rutasMap[$ruta]['tot_bajo_umbral'] += $bajoU;
            $rutasMap[$ruta]['tot_volumen_bs'] += $volBs;
        }

        $matriz = [];
        $tPlan = 0;
        $tVis = 0;
        $tPrev = 0;
        $tSobre = 0;
        $tBajo = 0;
        $tVol = 0.0;

        foreach ($rutasMap as $info) {
            $info['tot_volumen_bs'] = round($info['tot_volumen_bs'], 2);
            $info['efectividad_promedio_pct'] = $info['tot_plan'] > 0
                ? round(($info['tot_preventa'] / $info['tot_plan']) * 100, 1)
                : 0.0;

            $tPlan += $info['tot_plan'];
            $tVis += $info['tot_visitados'];
            $tPrev += $info['tot_preventa'];
            $tSobre += $info['tot_sobre_umbral'];
            $tBajo += $info['tot_bajo_umbral'];
            $tVol += $info['tot_volumen_bs'];

            $matriz[] = $info;
        }

        usort($matriz, fn ($a, $b) => strcmp($a['ruta'], $b['ruta']));

        // Días hábiles del mes de corte
        $mesCorte = substr($fechaCorte, 0, 7);
        $diasCalendario = $this->calcularDiasHabiles($mesCorte, $fechaCorte);

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'fecha_min_limite' => $fechaMinDb,
            'fecha_max_limite' => $fechaMaxDb,
            'rango_label' => "{$fechaInicio} al {$fechaCorte} (" . count($diasColumnas) . " días)",
            'dias_columnas' => $diasColumnas,
            'canal_seleccionado' => !empty($canalesLimpios) ? implode(', ', $canalesLimpios) : 'TODOS',
            'canales_disponibles' => array_merge(['TODOS'], array_keys($canalesSet)),
            'dias_calendario' => $diasCalendario,
            'kpis' => [
                'total_plan' => $tPlan,
                'total_visitados' => $tVis,
                'total_preventa' => $tPrev,
                'total_sobre_umbral' => $tSobre,
                'total_bajo_umbral' => $tBajo,
                'total_volumen_bs' => round($tVol, 2),
                'efectividad_global_pct' => $tPlan > 0 ? round(($tPrev / $tPlan) * 100, 1) : 0.0,
            ],
            'matriz' => $matriz,
        ];
    }

    /**
     * Calcula días hábiles transcurridos y totales del mes (sin domingos).
     *
     * @return array<string, mixed>
     */
    private function calcularDiasHabiles(string $mesYm, string $fechaCorte): array
    {
        try {
            $inicioMes = Carbon::parse("{$mesYm}-01");
            $finMes = $inicioMes->copy()->endOfMonth();
            $corte = Carbon::parse($fechaCorte);

            $diasHabilesTotales = 0;
            $diasHabilesTranscurridos = 0;

            $cur = $inicioMes->copy();
            while ($cur->lte($finMes)) {
                if ($cur->dayOfWeek !== 0) { // Omitir domingo
                    $diasHabilesTotales++;
                    if ($cur->lte($corte)) {
                        $diasHabilesTranscurridos++;
                    }
                }
                $cur->addDay();
            }

            return [
                'mes' => $mesYm,
                'dias_habiles_totales' => $diasHabilesTotales,
                'dias_habiles_transcurridos' => $diasHabilesTranscurridos,
                'dias_habiles_restantes' => max(0, $diasHabilesTotales - $diasHabilesTranscurridos),
                'progreso_mes_pct' => $diasHabilesTotales > 0 ? round(($diasHabilesTranscurridos / $diasHabilesTotales) * 100, 1) : 0.0,
            ];
        } catch (\Throwable) {
            return [
                'mes' => $mesYm,
                'dias_habiles_totales' => 26,
                'dias_habiles_transcurridos' => 20,
                'dias_habiles_restantes' => 6,
                'progreso_mes_pct' => 76.9,
            ];
        }
    }
}

