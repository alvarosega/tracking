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
     * para el control de pedidos rechazados a enviar a la app móvil.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $normKey = static function (?string $r): string {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $r)));
        };

        // 1. Obtener módulo y configuración global
        $modulo = $this->service->getGlobalModule();
        $globalConfig = is_array($modulo->config) ? $modulo->config : [];
        $mergedConfig = array_merge(RechazosVentanaService::DEFAULT_CONFIG, $globalConfig);
        $moduloActivoGlobal = (bool) ($modulo->is_active ?? true);

        $nowBolivia = Carbon::now('America/La_Paz');
        $evalInfo = $this->service->resolverFechasEvaluadas($mergedConfig, $nowBolivia);
        $fechasEvaluadas = $evalInfo['fechas'];
        $fechaMin = $evalInfo['fecha_min'];

        // 2. Obtener catálogo maestro de rutas desde dim_rutas (supervisor)
        $metaPorRutaNorm = [];
        $nombresRutaMap = [];

        try {
            $dimRows = DB::connection('supervisor')
                ->table('dim_rutas')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->select(['ruta', 'canal', 'vendedor'])
                ->get();

            foreach ($dimRows as $row) {
                $rawRuta = trim((string) $row->ruta);
                $nKey = $normKey($rawRuta);
                if ($nKey === '') {
                    continue;
                }
                $metaPorRutaNorm[$nKey] = [
                    'ruta' => $rawRuta,
                    'canal' => trim((string) ($row->canal ?: 'TDB')),
                    'vendedor' => trim((string) ($row->vendedor ?: 'Sin asignar')),
                ];
                $nombresRutaMap[$nKey] = $rawRuta;
            }
        } catch (\Throwable $e) {
            Log::warning('[GetRechazosVentanaDataAction] dim_rutas error: ' . $e->getMessage());
        }

        // 3. Clientes y rutas presentes en pan_ruteo
        $clientesPorRuta = [];
        try {
            $panRows = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('estado', 'Activo')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->where('cliente_id', '>', 0)
                ->select(['cliente_id', 'ruta'])
                ->get();

            foreach ($panRows as $pr) {
                $rawRuta = trim((string) $pr->ruta);
                $nKey = $normKey($rawRuta);
                if ($nKey === '') {
                    continue;
                }
                if (!isset($nombresRutaMap[$nKey])) {
                    $nombresRutaMap[$nKey] = $rawRuta;
                }
                $cid = (int) $pr->cliente_id;
                $clientesPorRuta[$nKey][$cid] = true;
            }
        } catch (\Throwable $e) {
            Log::warning('[GetRechazosVentanaDataAction] pan_ruteo error: ' . $e->getMessage());
        }

        // 4. Usuarios vendedores desde users (mysql)
        $vendedoresUsers = [];
        try {
            $usersRows = DB::connection('mysql')
                ->table('users')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->where('is_active', true)
                ->select(['ruta', 'username', 'name'])
                ->get();

            foreach ($usersRows as $u) {
                $nKey = $normKey($u->ruta);
                if ($nKey !== '' && !isset($vendedoresUsers[$nKey])) {
                    $vendedoresUsers[$nKey] = trim((string) ($u->name ?: $u->username));
                }
            }
        } catch (\Throwable) {
            // Silencioso
        }

        // 5. Overrides por ruta en app_module_routes (mysql)
        $overridesPorRutaNorm = [];
        try {
            $overrides = AppModuleRoute::where('module_code', RechazosVentanaService::MODULE_CODE)->get();
            foreach ($overrides as $ov) {
                $nKey = $normKey($ov->ruta);
                if ($nKey !== '') {
                    $overridesPorRutaNorm[$nKey] = $ov;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[GetRechazosVentanaDataAction] app_module_routes error: ' . $e->getMessage());
        }

        // 6. Resumen de preventas y ventas para las fechas evaluadas por ruta
        $conteoPorRuta = [];
        $totalPreventasEvaluadas = 0;
        $totalPreventasRechazadas = 0;

        try {
            if (!empty($fechasEvaluadas)) {
                // Preventas por ruta
                $prevRows = DB::connection('supervisor')->table('fact_preventas')
                    ->whereIn('fecha_norm', $fechasEvaluadas)
                    ->where('monto_final', '>', 0)
                    ->where('cantidad', '>', 0)
                    ->select([
                        'ruta',
                        'nro_preventa',
                        'producto_id',
                        'cantidad',
                        'monto_final',
                    ])
                    ->get();

                $nrosPorRuta = [];
                $itemsPorRuta = [];
                foreach ($prevRows as $pr) {
                    $rKey = $normKey($pr->ruta);
                    $nro = (int) $pr->nro_preventa;
                    if ($rKey === '' || $nro <= 0) {
                        continue;
                    }
                    $nrosPorRuta[$rKey][$nro] = true;
                    $itemsPorRuta[$rKey][] = $pr;
                }

                // Todas las preventas únicas
                $allNros = array_values(array_unique(array_filter($prevRows->pluck('nro_preventa')->map(fn ($n) => (int) $n)->toArray())));

                // Facturación de esas preventas desde fechaMin en adelante
                $ventasFacturadasMap = [];
                if (!empty($allNros)) {
                    $vFact = DB::connection('supervisor')->table('fact_ventas')
                        ->whereIn('pre_venta_id', $allNros)
                        ->where('fecha_norm', '>=', $fechaMin)
                        ->where('monto_final', '>', 0)
                        ->where('cantidad', '>', 0)
                        ->where(function ($q): void {
                            $q->whereNull('revertida')
                              ->orWhere(function ($sub): void {
                                  $sub->where('revertida', '!=', 'Si')
                                      ->where('revertida', '!=', 'SI')
                                      ->where('revertida', '!=', '1');
                              });
                        })
                        ->select([
                            'pre_venta_id',
                            'producto_id',
                            DB::raw('SUM(cantidad) as total_facturado'),
                        ])
                        ->groupBy('pre_venta_id', 'producto_id')
                        ->get();

                    foreach ($vFact as $vf) {
                        $ventasFacturadasMap[(int) $vf->pre_venta_id . '_' . (int) $vf->producto_id] = (int) $vf->total_facturado;
                    }
                }

                // Justificativos ya registrados
                $justificadosSet = [];
                try {
                    $justRows = DB::connection('mysql')->table('pedidos_rechazados')
                        ->whereIn('nro_preventa', $allNros)
                        ->pluck('nro_preventa')
                        ->map(fn ($n) => (int) $n)
                        ->toArray();
                    $justificadosSet = array_flip($justRows);
                } catch (\Throwable) {
                    // Silencioso
                }

                // Evaluar por ruta
                foreach ($itemsPorRuta as $rKey => $items) {
                    $prevAgrupadas = [];
                    foreach ($items as $it) {
                        $nro = (int) $it->nro_preventa;
                        if (!isset($prevAgrupadas[$nro])) {
                            $prevAgrupadas[$nro] = [
                                'total_prev' => 0,
                                'total_fact' => 0,
                                'monto_prev' => 0.0,
                            ];
                        }
                        $cantPrev = (int) $it->cantidad;
                        $cantFact = $ventasFacturadasMap[$nro . '_' . (int) $it->producto_id] ?? 0;
                        $prevAgrupadas[$nro]['total_prev'] += $cantPrev;
                        $prevAgrupadas[$nro]['total_fact'] += min($cantFact, $cantPrev);
                        $prevAgrupadas[$nro]['monto_prev'] += (float) $it->monto_final;
                    }

                    $totalPrevCount = count($prevAgrupadas);
                    $totalRechazosCount = 0;
                    $montoRechazosTotal = 0.0;

                    foreach ($prevAgrupadas as $nro => $pa) {
                        if (isset($justificadosSet[$nro])) {
                            continue; // Ya justificado
                        }
                        $cantRechazada = max(0, $pa['total_prev'] - $pa['total_fact']);
                        if ($cantRechazada > 0) {
                            $totalRechazosCount++;
                            $ratio = $pa['total_prev'] > 0 ? ($cantRechazada / $pa['total_prev']) : 1.0;
                            $montoRechazosTotal += round($pa['monto_prev'] * $ratio, 2);
                        }
                    }

                    $conteoPorRuta[$rKey] = [
                        'total_preventas' => $totalPrevCount,
                        'total_rechazos' => $totalRechazosCount,
                        'monto_rechazos' => round($montoRechazosTotal, 2),
                    ];

                    $totalPreventasEvaluadas += $totalPrevCount;
                    $totalPreventasRechazadas += $totalRechazosCount;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[GetRechazosVentanaDataAction] conteo de preventas error: ' . $e->getMessage());
        }

        // 7. Unificar y construir matriz de rutas
        $normKeysUnicas = array_keys($nombresRutaMap);
        sort($normKeysUnicas, SORT_NATURAL | SORT_FLAG_CASE);

        $canalesUnicos = [];
        $rutasResultado = [];
        $kpiPersonalizadas = 0;
        $kpiGlobales = 0;

        foreach ($normKeysUnicas as $nKey) {
            $displayRuta = $nombresRutaMap[$nKey];
            $meta = $metaPorRutaNorm[$nKey] ?? null;
            $canal = $meta['canal'] ?? 'TDB';
            $vendedor = $meta['vendedor'] ?? ($vendedoresUsers[$nKey] ?? 'Sin asignar');
            $canalesUnicos[$canal] = true;

            $totalClientes = count($clientesPorRuta[$nKey] ?? []);

            $ov = $overridesPorRutaNorm[$nKey] ?? null;
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

            if ($hasOverrideConfig) {
                $kpiPersonalizadas++;
            } else {
                $kpiGlobales++;
            }

            $conteo = $conteoPorRuta[$nKey] ?? [
                'total_preventas' => 0,
                'total_rechazos' => 0,
                'monto_rechazos' => 0.0,
            ];

            $rutasResultado[] = [
                'ruta' => $displayRuta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'total_clientes' => $totalClientes,
                'es_personalizado' => $hasOverrideConfig,
                'modulo_activo' => $isModuleActive,
                'total_preventas' => $conteo['total_preventas'],
                'total_rechazos' => $conteo['total_rechazos'],
                'monto_rechazos' => $conteo['monto_rechazos'],
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
            'opciones_dias' => $this->service->getOpcionesDiasConFechas($evalInfo['dias_activos'] ?? [], $nowBolivia),
            'dias_semana_catalogo' => RechazosVentanaService::DIAS_SEMANA,
            'canales' => $canalesLista,
            'rutas' => $rutasResultado,
            'kpis' => [
                'total_rutas' => count($rutasResultado),
                'rutas_personalizadas' => $kpiPersonalizadas,
                'rutas_globales' => $kpiGlobales,
                'modo_activo' => $evalInfo['modo_aplicado'],
                'es_excepcion' => $evalInfo['es_excepcion'],
                'descripcion_evaluacion' => $evalInfo['descripcion'],
                'total_preventas_evaluadas' => $totalPreventasEvaluadas,
                'total_preventas_rechazadas' => $totalPreventasRechazadas,
            ],
        ];
    }
}

