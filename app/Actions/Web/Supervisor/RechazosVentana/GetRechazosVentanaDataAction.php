<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\RechazosVentana;

use App\Models\AppModuleRoute;
use App\Services\PedidosRechazados\RechazosVentanaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetRechazosVentanaDataAction
{
    public function __construct(
        protected RechazosVentanaService $service
    ) {}

    /**
     * Retorna la matriz de rutas, la configuración operativa y estadísticas clave
     * calculadas con el motor oficial de Avance Entregas.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        // 1. Obtener módulo y configuración global
        $modulo = $this->service->getGlobalModule();
        $globalConfig = is_array($modulo->config) ? $modulo->config : [];
        $mergedConfig = array_merge(RechazosVentanaService::DEFAULT_CONFIG, $globalConfig);
        $moduloActivoGlobal = (bool) ($modulo->is_active ?? true);

        $nowBolivia = Carbon::now('America/La_Paz');
        $evalInfo = $this->service->resolverFechasEvaluadas($mergedConfig, $nowBolivia);
        $fechasEvaluadas = $evalInfo['fechas'];

        $fechaPrevInicio = $evalInfo['fecha_min'];
        $fechaPrevFin = $evalInfo['fecha_max'];

        // Se evalúan ventas/entregas facturadas desde la fecha de preventa hacia adelante
        $fechaFactInicio = $fechaPrevInicio;
        $fechaMaxFact = max(Carbon::parse($fechaPrevFin)->addDays(30)->toDateString(), $nowBolivia->toDateString());

        // 2. Overrides por ruta en app_module_routes (mysql)
        $overridesPorRuta = [];
        try {
            $overrides = AppModuleRoute::where('module_code', RechazosVentanaService::MODULE_CODE)->get();
            foreach ($overrides as $ov) {
                $rClean = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $ov->ruta)));
                if ($rClean !== '') {
                    $overridesPorRuta[$rClean] = $ov;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[GetRechazosVentanaDataAction] app_module_routes error: ' . $e->getMessage());
        }

        // 3. Consulta de preventas y ventas (Motor oficial idéntico a Avance Entregas)
        $placeholders = implode(',', array_fill(0, count($fechasEvaluadas), '?'));
        $query = "
            SELECT 
                TRIM(UPPER(COALESCE(fp.ruta, 'SIN RUTA'))) AS ruta,
                MAX(COALESCE(dr.canal, 'GENERAL')) AS canal,
                MAX(COALESCE(NULLIF(TRIM(dr.vendedor), ''), NULLIF(TRIM(fp.vendedor), ''), 'No Asignado')) AS vendedor,
                fp.nro_preventa,
                SUM(fp.cantidad) AS cant_prev,
                ROUND(SUM(fp.monto_final), 2) AS monto_prev,
                COALESCE(SUM(fv_agregada.cantidad_facturada), 0) AS cant_fact,
                ROUND(COALESCE(SUM(fv_agregada.monto_facturado), 0.0), 2) AS monto_fact
            FROM fact_preventas fp
            LEFT JOIN dim_rutas dr ON TRIM(UPPER(fp.ruta)) = TRIM(UPPER(dr.ruta))
            LEFT JOIN (
                SELECT 
                    pre_venta_id,
                    producto_id,
                    SUM(cantidad) AS cantidad_facturada,
                    SUM(monto_final) AS monto_facturado
                FROM fact_ventas
                WHERE fecha_norm >= ? AND fecha_norm <= ?
                  AND monto_final > 0
                  AND cantidad > 0
                  AND (revertida IS NULL OR (LOWER(TRIM(revertida)) != 'si' AND revertida != '1'))
                GROUP BY pre_venta_id, producto_id
            ) fv_agregada ON fp.nro_preventa = fv_agregada.pre_venta_id AND fp.producto_id = fv_agregada.producto_id
            WHERE fp.fecha_norm IN ({$placeholders})
              AND fp.monto_final > 0 AND fp.cantidad > 0
            GROUP BY 
                fp.ruta,
                fp.nro_preventa
        ";

        $bindings = array_merge([$fechaFactInicio, $fechaMaxFact], $fechasEvaluadas);
        $filas = [];
        try {
            $filas = DB::connection('supervisor')->select($query, $bindings);
        } catch (\Throwable $e) {
            Log::error('[GetRechazosVentanaDataAction] Error en consulta SQL entregas: ' . $e->getMessage());
        }

        // 4. Justificativos ya registrados en pedidos_rechazados
        $todosNros = [];
        foreach ($filas as $row) {
            if ($row->nro_preventa !== null) {
                $todosNros[] = (int) $row->nro_preventa;
            }
        }

        $justificadosMap = [];
        if (!empty($todosNros)) {
            try {
                $justRows = DB::connection('mysql')->table('pedidos_rechazados')
                    ->whereIn('nro_preventa', array_unique($todosNros))
                    ->pluck('nro_preventa')
                    ->map(fn ($n) => (int) $n)
                    ->toArray();
                $justificadosMap = array_flip($justRows);
            } catch (\Throwable) {
                // Silencioso
            }
        }

        // 5. Totalizar por ruta
        $rutasMap = [];
        $canalesUnicos = [];

        foreach ($filas as $row) {
            $rNombre = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $row->ruta)));
            $canal = strtoupper(trim((string) ($row->canal ?: 'TDB')));
            $vendedor = trim((string) ($row->vendedor ?: 'Sin asignar'));
            $canalesUnicos[$canal] = true;

            if (!isset($rutasMap[$rNombre])) {
                $rutasMap[$rNombre] = [
                    'ruta' => $rNombre,
                    'canal' => $canal,
                    'vendedor' => $vendedor,
                    'total_pedidos' => 0,
                    'pedidos_entregados' => 0,
                    'pedidos_parciales' => 0,
                    'pedidos_caidos' => 0,
                    'pedidos_justificados' => 0,
                    'monto_preventa' => 0.0,
                    'monto_facturado' => 0.0,
                    'monto_rechazos' => 0.0,
                ];
            }

            $r = &$rutasMap[$rNombre];
            $r['total_pedidos']++;

            $nroPrev = (int) $row->nro_preventa;
            $esJustificado = isset($justificadosMap[$nroPrev]);

            $cPrev = (int) ($row->cant_prev ?? 0);
            $cFact = (int) ($row->cant_fact ?? 0);
            $mPrev = (float) ($row->monto_prev ?? 0.0);
            $mFact = (float) ($row->monto_fact ?? 0.0);

            $r['monto_preventa'] = round($r['monto_preventa'] + $mPrev, 2);
            $r['monto_facturado'] = round($r['monto_facturado'] + $mFact, 2);

            if ($cFact === 0) {
                $r['pedidos_caidos']++;
                if ($esJustificado) {
                    $r['pedidos_justificados']++;
                } else {
                    $r['monto_rechazos'] = round($r['monto_rechazos'] + $mPrev, 2);
                }
            } elseif ($cFact >= $cPrev) {
                $r['pedidos_entregados']++;
            } else {
                $r['pedidos_parciales']++;
                if ($esJustificado) {
                    $r['pedidos_justificados']++;
                } else {
                    $r['monto_rechazos'] = round($r['monto_rechazos'] + max(0.0, $mPrev - $mFact), 2);
                }
            }
            unset($r);
        }

        // 6. Construir lista de rutas con sus overrides
        ksort($rutasMap, SORT_NATURAL | SORT_FLAG_CASE);

        $rutasResultado = [];
        foreach ($rutasMap as $rNombre => $r) {
            $ov = $overridesPorRuta[$rNombre] ?? null;
            $hasOverrideConfig = false;
            $isModuleActive = $moduloActivoGlobal;
            $configRuta = $mergedConfig;

            if ($ov) {
                if (is_array($ov->config) && !empty($ov->config)) {
                    $hasOverrideConfig = true;
                    $configRuta = array_merge($mergedConfig, $ov->config);
                }
                if ($ov->is_active !== null) {
                    $isModuleActive = (bool) $ov->is_active;
                }
            }

            // Total de pedidos con rechazo pendientes de justificar
            $rechazosTotales = $r['pedidos_caidos'] + $r['pedidos_parciales'];
            $rechazosPendientes = max(0, $rechazosTotales - $r['pedidos_justificados']);

            $rutasResultado[] = [
                'ruta' => $r['ruta'],
                'canal' => $r['canal'],
                'vendedor' => $r['vendedor'],
                'total_preventas' => $r['total_pedidos'],
                'pedidos_entregados' => $r['pedidos_entregados'],
                'pedidos_parciales' => $r['pedidos_parciales'],
                'pedidos_caidos' => $r['pedidos_caidos'],
                'pedidos_justificados' => $r['pedidos_justificados'],
                'total_rechazos' => $rechazosPendientes,
                'monto_rechazos' => $r['monto_rechazos'],
                'monto_preventa' => $r['monto_preventa'],
                'monto_facturado' => $r['monto_facturado'],
                'es_personalizado' => $hasOverrideConfig,
                'modulo_activo' => $isModuleActive,
                'config' => $configRuta,
                'updated_at' => $ov?->updated_at ? $ov->updated_at->format('d/m/Y H:i') : null,
            ];
        }

        $canalesLista = array_keys($canalesUnicos);
        if (empty($canalesLista)) {
            $canalesLista = ['TDB', 'MAY', 'MZO', 'PROV'];
        }

        return [
            'modulo_activo_global' => $moduloActivoGlobal,
            'config_global' => $mergedConfig,
            'eval_info' => $evalInfo,
            'fecha_fact_inicio' => $fechaFactInicio,
            'fecha_fact_corte' => $fechaPrevFin,
            'opciones_dias' => $this->service->getOpcionesDiasConFechas($evalInfo['dias_activos'] ?? [], $nowBolivia),
            'dias_semana_catalogo' => RechazosVentanaService::DIAS_SEMANA,
            'canales' => $canalesLista,
            'rutas' => $rutasResultado,
        ];
    }
}
