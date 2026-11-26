<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPizarraVisualEfectividadDataAction
{
    public const UMBRAL_EFECTIVIDAD_PCT = 80.0;
    public const PUNTOS_MAXIMOS_KPI = 30.0;
    public const MONTO_MINIMO_COMPRA = 70.0;

    private const DIAS_MAP = [
        1 => ['lunes', 'Lunes'],
        2 => ['martes', 'Martes'],
        3 => ['miercoles', 'miércoles', 'Miercoles', 'Miércoles'],
        4 => ['jueves', 'Jueves'],
        5 => ['viernes', 'Viernes'],
        6 => ['sabado', 'sábado', 'Sabado', 'Sábado'],
        7 => ['domingo', 'Domingo'],
    ];

    private const ROUTE_PALETTE = [
        '#0071E3', // Apple Blue
        '#34C759', // Apple Green
        '#FF9500', // Apple Orange
        '#AF52DE', // Apple Purple
        '#FF2D55', // Apple Pink
        '#5856D6', // Apple Indigo
        '#00C7BE', // Apple Teal
        '#FF3B30', // Apple Red
        '#A2845E', // Apple Brown
        '#64D2FF', // Apple Sky
        '#30B0C7', // Apple Cyan
        '#32ADE6', // Apple Ocean
        '#5E5CE6', // Apple Violet
        '#BF5AF2', // Apple Magenta
        '#FF375F', // Apple Rose
        '#FFD60A', // Apple Yellow
    ];

    /**
     * Consulta consolidada analítica y de conversión comercial para el KPI 4 (Efectividad 80% · 30 Pts).
     *
     * @param string|null $fecha
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fecha = null,
        array $canales = [],
        array $rutas = []
    ): array {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes', 'Lunes'];
        $diasFiltro = array_values(array_unique(array_merge($tuplaDias, array_map('strtolower', $tuplaDias))));

        $canalesClean = array_values(array_filter(array_map(fn($c) => strtoupper(trim((string)$c)), $canales)));
        $rutasClean = array_values(array_filter(array_map(fn($r) => strtoupper(trim((string)$r)), $rutas)));

        // 1. Catálogo base de rutas y canales
        $rutasQuery = DB::connection('supervisor')->table('dim_rutas')->select([
            'ruta',
            'canal',
            'vendedor',
            'vendedor_norm',
        ]);

        if (!empty($canalesClean)) {
            $rutasQuery->whereIn(DB::raw("TRIM(UPPER(canal))"), $canalesClean);
        }

        if (!empty($rutasClean)) {
            $rutasQuery->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasClean);
        }

        $catalogoRutas = $rutasQuery->get()->keyBy(fn($r) => strtoupper(trim((string)$r->ruta)));
        $rutasObjetivo = $catalogoRutas->keys()->all();

        if (empty($rutasObjetivo)) {
            return $this->generarRespuestaVacia($fechaNorm);
        }

        // Asignación de colores consistentes
        $coloresRuta = [];
        foreach ($rutasObjetivo as $idx => $r) {
            $coloresRuta[$r] = self::ROUTE_PALETTE[$idx % count(self::ROUTE_PALETTE)];
        }

        // 2. Cartera Plan activa por ruta del día (pan_ruteo)
        $carteraPlanRaw = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
            ->where(function ($q) use ($diasFiltro): void {
                $q->whereIn('dia_norm', $diasFiltro)
                  ->orWhereIn('dia', $diasFiltro);
            })
            ->where('cliente_id', '>', 0)
            ->whereRaw("TRIM(UPPER(COALESCE(estado, ''))) = 'ACTIVO'")
            ->select([
                'cliente_id',
                'ruta',
                DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), NULLIF(TRIM(cliente_norm), ''), CONCAT('Cliente #', cliente_id))) as cliente"),
                DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
            ])
            ->groupBy('cliente_id', 'ruta')
            ->get();

        $planPorRuta = [];
        foreach ($carteraPlanRaw as $p) {
            $rUpper = strtoupper(trim((string)$p->ruta));
            $cid = (int)$p->cliente_id;
            $planPorRuta[$rUpper][$cid] = [
                'cliente_id' => $cid,
                'cliente' => (string)$p->cliente,
                'direccion' => (string)$p->direccion,
            ];
        }

        // 3. Preventas del día por ruta y cliente (fact_preventas)
        $preventasPorRutaCliente = [];
        try {
            $filasPreventa = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->where('cliente_id', '>', 0)
                ->where('monto_final', '>', 0)
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta"),
                    'cliente_id',
                    DB::raw("COALESCE(TRIM(cliente), 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(TRIM(nro_preventa), '-') as nro_preventa"),
                    DB::raw("COALESCE(TRIM(producto), '-') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("CAST(COALESCE(monto_final, 0.0) AS DECIMAL(12, 2)) as monto_final"),
                    DB::raw("COALESCE(cantidad, 0) as cantidad"),
                ])
                ->get();

            foreach ($filasPreventa as $fp) {
                $rUpper = strtoupper(trim((string)$fp->ruta));
                $cid = (int)$fp->cliente_id;
                $monto = (float)$fp->monto_final;
                $nro = (string)$fp->nro_preventa;

                if (!isset($preventasPorRutaCliente[$rUpper][$cid])) {
                    $preventasPorRutaCliente[$rUpper][$cid] = [
                        'cliente_id' => $cid,
                        'cliente' => (string)$fp->cliente,
                        'monto_total' => 0.0,
                        'notas' => [],
                        'items_count' => 0,
                    ];
                }

                $preventasPorRutaCliente[$rUpper][$cid]['monto_total'] = round(
                    $preventasPorRutaCliente[$rUpper][$cid]['monto_total'] + $monto,
                    2
                );
                $preventasPorRutaCliente[$rUpper][$cid]['notas'][$nro] = true;
                $preventasPorRutaCliente[$rUpper][$cid]['items_count'] += (int)($fp->cantidad ?: 1);
            }
        } catch (\Throwable $e) {
            Log::warning("[PizarraVisual] Error consultando fact_preventas en Efectividad: " . $e->getMessage());
        }

        // 4. Clientes Visitados (fact_visitas y tabla visitas)
        $visitadosPorRuta = [];
        try {
            // Visitas desde app móvil
            $visitasMovil = DB::table('visitas as v')
                ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
                ->whereBetween('v.visited_at', ["{$fechaNorm} 00:00:00", "{$fechaNorm} 23:59:59"])
                ->where(function ($q) use ($rutasObjetivo): void {
                    $q->whereIn(DB::raw("TRIM(UPPER(v.route))"), $rutasObjetivo);
                    $q->orWhereIn(DB::raw("TRIM(UPPER(COALESCE(u.ruta, '')))"), $rutasObjetivo);
                })
                ->where('v.client_id', '>', 0)
                ->select([
                    DB::raw("TRIM(UPPER(COALESCE(NULLIF(v.route, ''), u.ruta, ''))) as ruta"),
                    'v.client_id',
                ])
                ->get();

            foreach ($visitasMovil as $vm) {
                $r = strtoupper(trim((string)$vm->ruta));
                $cid = (int)$vm->client_id;
                if ($r !== '' && $cid > 0) {
                    $visitadosPorRuta[$r][$cid] = true;
                }
            }

            // Visitas desde fact_visitas (ERP)
            $vendedoresLista = [];
            foreach ($catalogoRutas as $dim) {
                if (!empty($dim->vendedor)) {
                    $vendedoresLista[] = trim((string)$dim->vendedor);
                }
                if (!empty($dim->vendedor_norm)) {
                    $vendedoresLista[] = trim((string)$dim->vendedor_norm);
                }
            }
            $vendedoresLista = array_values(array_unique(array_filter($vendedoresLista)));

            if (!empty($vendedoresLista)) {
                $visitasERP = DB::connection('supervisor')->table('fact_visitas')
                    ->where('fecha_norm', $fechaNorm)
                    ->where(function ($q) use ($vendedoresLista): void {
                        $q->whereIn('empleado', $vendedoresLista);
                        foreach ($vendedoresLista as $v) {
                            $q->orWhereRaw("TRIM(LOWER(empleado_norm)) = ?", [strtolower($v)]);
                        }
                    })
                    ->select(['empleado', 'cliente_id', 'cliente'])
                    ->get();

                // Mapeo inverso de vendedor a ruta
                $vendedorToRuta = [];
                foreach ($catalogoRutas as $dim) {
                    if (!empty($dim->vendedor)) {
                        $vendedorToRuta[$this->normalizarTexto((string)$dim->vendedor)] = strtoupper(trim((string)$dim->ruta));
                    }
                    if (!empty($dim->vendedor_norm)) {
                        $vendedorToRuta[$this->normalizarTexto((string)$dim->vendedor_norm)] = strtoupper(trim((string)$dim->ruta));
                    }
                }

                foreach ($visitasERP as $ve) {
                    $empNorm = $this->normalizarTexto((string)$ve->empleado);
                    $r = $vendedorToRuta[$empNorm] ?? null;
                    $cid = (int)($ve->cliente_id ?? 0);
                    if ($r && $cid > 0) {
                        $visitadosPorRuta[$r][$cid] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug("[PizarraVisual] Error consultando visitas en Efectividad: " . $e->getMessage());
        }

        // 5. Consolidación analítica por Ruta
        $rutasAnalizadas = [];
        $clientesDetallePorRuta = [];

        $totalesGlobales = [
            'cartera_plan_total' => 0,
            'visitados_total' => 0,
            'compradores_total' => 0,
            'compradores_sobre_70_total' => 0,
            'compradores_bajo_70_total' => 0,
            'visitados_sin_compra_total' => 0,
            'pendientes_total' => 0,
            'monto_total' => 0.0,
            'rutas_cumplen' => 0,
            'rutas_deficit' => 0,
            'puntos_totales_ganados' => 0.0,
            'puntos_maximos_posibles' => 0.0,
        ];

        foreach ($rutasObjetivo as $rUpper) {
            $dim = $catalogoRutas[$rUpper] ?? null;
            $canal = $dim ? (string)$dim->canal : 'GENERAL';
            $vendedor = $dim ? (string)$dim->vendedor : 'No Asignado';
            $color = $coloresRuta[$rUpper] ?? '#0071E3';

            $clientesPlan = $planPorRuta[$rUpper] ?? [];
            $compradoresMap = $preventasPorRutaCliente[$rUpper] ?? [];
            $visitadosMap = $visitadosPorRuta[$rUpper] ?? [];

            $carteraPlanCount = count($clientesPlan);

            $compradoresSobre70 = [];
            $compradoresBajo70 = [];
            $visitadosSinCompra = [];
            $pendientes = [];

            $montoRuta = 0.0;
            $clientesList = [];

            // Evaluamos clientes de la cartera en plan
            foreach ($clientesPlan as $cid => $cData) {
                $nombre = $cData['cliente'];
                $dir = $cData['direccion'];

                if (isset($compradoresMap[$cid])) {
                    $m = (float)$compradoresMap[$cid]['monto_total'];
                    $montoRuta += $m;
                    $notas = implode(', ', array_keys($compradoresMap[$cid]['notas']));

                    if ($m >= self::MONTO_MINIMO_COMPRA) {
                        $item = [
                            'cliente_id' => $cid,
                            'cliente' => $nombre,
                            'direccion' => $dir,
                            'monto' => $m,
                            'estado' => 'sobre_70',
                            'estado_label' => 'Compra ≥ 70 Bs',
                            'notas' => $notas,
                            'items_count' => $compradoresMap[$cid]['items_count'],
                            'brecha_minima' => 0.0,
                        ];
                        $compradoresSobre70[] = $item;
                        $clientesList[] = $item;
                    } else {
                        $item = [
                            'cliente_id' => $cid,
                            'cliente' => $nombre,
                            'direccion' => $dir,
                            'monto' => $m,
                            'estado' => 'bajo_70',
                            'estado_label' => 'Compra < 70 Bs',
                            'notas' => $notas,
                            'items_count' => $compradoresMap[$cid]['items_count'],
                            'brecha_minima' => round(self::MONTO_MINIMO_COMPRA - $m, 2),
                        ];
                        $compradoresBajo70[] = $item;
                        $clientesList[] = $item;
                    }
                } elseif (isset($visitadosMap[$cid])) {
                    $item = [
                        'cliente_id' => $cid,
                        'cliente' => $nombre,
                        'direccion' => $dir,
                        'monto' => 0.0,
                        'estado' => 'sin_compra',
                        'estado_label' => 'Visitado Sin Compra',
                        'notas' => '-',
                        'items_count' => 0,
                        'brecha_minima' => self::MONTO_MINIMO_COMPRA,
                    ];
                    $visitadosSinCompra[] = $item;
                    $clientesList[] = $item;
                } else {
                    $item = [
                        'cliente_id' => $cid,
                        'cliente' => $nombre,
                        'direccion' => $dir,
                        'monto' => 0.0,
                        'estado' => 'pendiente',
                        'estado_label' => 'No Visitado (Pendiente)',
                        'notas' => '-',
                        'items_count' => 0,
                        'brecha_minima' => self::MONTO_MINIMO_COMPRA,
                    ];
                    $pendientes[] = $item;
                    $clientesList[] = $item;
                }
            }

            // Compradores fuera de plan para esta ruta
            foreach ($compradoresMap as $cid => $cData) {
                if (!isset($clientesPlan[$cid])) {
                    $m = (float)$cData['monto_total'];
                    $montoRuta += $m;
                    $notas = implode(', ', array_keys($cData['notas']));

                    if ($m >= self::MONTO_MINIMO_COMPRA) {
                        $item = [
                            'cliente_id' => $cid,
                            'cliente' => $cData['cliente'] . ' (Fuera Plan)',
                            'direccion' => '-',
                            'monto' => $m,
                            'estado' => 'sobre_70',
                            'estado_label' => 'Fuera Plan ≥ 70 Bs',
                            'notas' => $notas,
                            'items_count' => $cData['items_count'],
                            'brecha_minima' => 0.0,
                        ];
                        $compradoresSobre70[] = $item;
                        $clientesList[] = $item;
                    } else {
                        $item = [
                            'cliente_id' => $cid,
                            'cliente' => $cData['cliente'] . ' (Fuera Plan)',
                            'direccion' => '-',
                            'monto' => $m,
                            'estado' => 'bajo_70',
                            'estado_label' => 'Fuera Plan < 70 Bs',
                            'notas' => $notas,
                            'items_count' => $cData['items_count'],
                            'brecha_minima' => round(self::MONTO_MINIMO_COMPRA - $m, 2),
                        ];
                        $compradoresBajo70[] = $item;
                        $clientesList[] = $item;
                    }
                }
            }

            $totalCompradores = count($compradoresSobre70) + count($compradoresBajo70);
            $totalVisitados = $totalCompradores + count($visitadosSinCompra);

            $efectividadPct = $carteraPlanCount > 0
                ? round(($totalCompradores / $carteraPlanCount) * 100.0, 1)
                : 0.0;

            $cumpleUmbral = $efectividadPct >= self::UMBRAL_EFECTIVIDAD_PCT;
            $puntosObtenidos = $cumpleUmbral ? self::PUNTOS_MAXIMOS_KPI : 0.0;

            $clientesMeta80 = (int)ceil($carteraPlanCount * (self::UMBRAL_EFECTIVIDAD_PCT / 100.0));
            $clientesFaltantes80 = max(0, $clientesMeta80 - $totalCompradores);
            $brechaPct = round($efectividadPct - self::UMBRAL_EFECTIVIDAD_PCT, 1);

            $ticketPromedio = $totalCompradores > 0 ? round($montoRuta / $totalCompradores, 2) : 0.0;
            $tasaConversionVisita = $totalVisitados > 0 ? round(($totalCompradores / $totalVisitados) * 100.0, 1) : 0.0;

            $rutasAnalizadas[] = [
                'ruta' => $rUpper,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'color' => $color,
                'cartera_plan' => $carteraPlanCount,
                'total_visitados' => $totalVisitados,
                'total_compradores' => $totalCompradores,
                'compradores_sobre_70' => count($compradoresSobre70),
                'compradores_bajo_70' => count($compradoresBajo70),
                'visitados_sin_compra' => count($visitadosSinCompra),
                'pendientes' => count($pendientes),
                'monto_total' => round($montoRuta, 2),
                'ticket_promedio' => $ticketPromedio,
                'efectividad_pct' => $efectividadPct,
                'cumple_umbral' => $cumpleUmbral,
                'puntos_obtenidos' => $puntosObtenidos,
                'puntos_maximos' => self::PUNTOS_MAXIMOS_KPI,
                'clientes_meta_80' => $clientesMeta80,
                'clientes_faltantes_80' => $clientesFaltantes80,
                'brecha_pct' => $brechaPct,
                'tasa_conversion_visita_pct' => $tasaConversionVisita,
            ];

            // Guardamos listado para el modal
            usort($clientesList, fn($a, $b) => $b['monto'] <=> $a['monto']);
            $clientesDetallePorRuta[$rUpper] = $clientesList;

            // Acumuladores Globales
            $totalesGlobales['cartera_plan_total'] += $carteraPlanCount;
            $totalesGlobales['visitados_total'] += $totalVisitados;
            $totalesGlobales['compradores_total'] += $totalCompradores;
            $totalesGlobales['compradores_sobre_70_total'] += count($compradoresSobre70);
            $totalesGlobales['compradores_bajo_70_total'] += count($compradoresBajo70);
            $totalesGlobales['visitados_sin_compra_total'] += count($visitadosSinCompra);
            $totalesGlobales['pendientes_total'] += count($pendientes);
            $totalesGlobales['monto_total'] = round($totalesGlobales['monto_total'] + $montoRuta, 2);
            $totalesGlobales['puntos_totales_ganados'] += $puntosObtenidos;
            $totalesGlobales['puntos_maximos_posibles'] += self::PUNTOS_MAXIMOS_KPI;

            if ($cumpleUmbral) {
                $totalesGlobales['rutas_cumplen']++;
            } else {
                $totalesGlobales['rutas_deficit']++;
            }
        }

        // Orden predeterminado: rutas en déficit (menor efectividad primero) para acción inmediata del supervisor
        usort($rutasAnalizadas, fn($a, $b) => $a['efectividad_pct'] <=> $b['efectividad_pct']);

        $carteraTot = max(1, $totalesGlobales['cartera_plan_total']);
        $compradoresTot = $totalesGlobales['compradores_total'];
        $visitadosTot = $totalesGlobales['visitados_total'];
        $sobre70Tot = $totalesGlobales['compradores_sobre_70_total'];
        $montoTot = $totalesGlobales['monto_total'];

        $totalesGlobales['efectividad_global_pct'] = round(($compradoresTot / $carteraTot) * 100.0, 1);
        $totalesGlobales['ticket_promedio_global'] = $compradoresTot > 0 ? round($montoTot / $compradoresTot, 2) : 0.0;
        $totalesGlobales['porcentaje_puntos_obtenidos'] = $totalesGlobales['puntos_maximos_posibles'] > 0
            ? round(($totalesGlobales['puntos_totales_ganados'] / $totalesGlobales['puntos_maximos_posibles']) * 100.0, 1)
            : 0.0;
        $totalesGlobales['umbral_efectividad_pct'] = self::UMBRAL_EFECTIVIDAD_PCT;

        // 6. Funnel Comercial Global (Embudo de 4 etapas)
        $funnelGlobal = [
            'etapas' => [
                [
                    'etapa' => 'cartera',
                    'nombre' => 'Cartera Plan',
                    'subtitulo' => 'Base asignada del día',
                    'cantidad' => $totalesGlobales['cartera_plan_total'],
                    'pct' => 100.0,
                    'color' => '#1D1D1F',
                ],
                [
                    'etapa' => 'visitados',
                    'nombre' => 'Clientes Visitados',
                    'subtitulo' => 'Cobertura física en terreno',
                    'cantidad' => $visitadosTot,
                    'pct' => round(($visitadosTot / $carteraTot) * 100.0, 1),
                    'color' => '#0071E3',
                ],
                [
                    'etapa' => 'compradores',
                    'nombre' => 'Compradores Efectivos',
                    'subtitulo' => 'Con preventa (Meta ≥ 80%)',
                    'cantidad' => $compradoresTot,
                    'pct' => round(($compradoresTot / $carteraTot) * 100.0, 1),
                    'color' => '#34C759',
                ],
                [
                    'etapa' => 'sobre_70',
                    'nombre' => 'Compra Calificada (≥ 70 Bs)',
                    'subtitulo' => 'Cumple compra mínima',
                    'cantidad' => $sobre70Tot,
                    'pct' => round(($sobre70Tot / $carteraTot) * 100.0, 1),
                    'color' => '#1E6B37',
                ],
            ],
            'fugas' => [
                'por_no_visita' => max(0, $totalesGlobales['cartera_plan_total'] - $visitadosTot),
                'por_rechazo_visita' => max(0, $visitadosTot - $compradoresTot),
                'por_bajo_minimo' => max(0, $compradoresTot - $sobre70Tot),
            ],
        ];

        // 7. Distribución de Calidad Global
        $distribucionCalidad = [
            [
                'id' => 'sobre_70',
                'label' => 'Compra ≥ 70 Bs',
                'cantidad' => $sobre70Tot,
                'pct' => round(($sobre70Tot / $carteraTot) * 100.0, 1),
                'color' => '#34C759',
            ],
            [
                'id' => 'bajo_70',
                'label' => 'Compra < 70 Bs',
                'cantidad' => $totalesGlobales['compradores_bajo_70_total'],
                'pct' => round(($totalesGlobales['compradores_bajo_70_total'] / $carteraTot) * 100.0, 1),
                'color' => '#FF9500',
            ],
            [
                'id' => 'sin_compra',
                'label' => 'Visitado Sin Compra',
                'cantidad' => $totalesGlobales['visitados_sin_compra_total'],
                'pct' => round(($totalesGlobales['visitados_sin_compra_total'] / $carteraTot) * 100.0, 1),
                'color' => '#0071E3',
            ],
            [
                'id' => 'pendiente',
                'label' => 'No Visitado (Pendiente)',
                'cantidad' => $totalesGlobales['pendientes_total'],
                'pct' => round(($totalesGlobales['pendientes_total'] / $carteraTot) * 100.0, 1),
                'color' => '#FF3B30',
            ],
        ];

        return [
            'fecha' => $fechaNorm,
            'umbral_pct' => self::UMBRAL_EFECTIVIDAD_PCT,
            'puntos_max_kpi' => self::PUNTOS_MAXIMOS_KPI,
            'totales' => $totalesGlobales,
            'rutas' => $rutasAnalizadas,
            'funnel_global' => $funnelGlobal,
            'distribucion_calidad' => $distribucionCalidad,
            'clientes_detalle_por_ruta' => $clientesDetallePorRuta,
        ];
    }

    /**
     * Genera una respuesta vacía cuando no hay rutas coincidentes.
     *
     * @param string $fechaNorm
     * @return array<string, mixed>
     */
    private function generarRespuestaVacia(string $fechaNorm): array
    {
        return [
            'fecha' => $fechaNorm,
            'umbral_pct' => self::UMBRAL_EFECTIVIDAD_PCT,
            'puntos_max_kpi' => self::PUNTOS_MAXIMOS_KPI,
            'totales' => [
                'cartera_plan_total' => 0,
                'visitados_total' => 0,
                'compradores_total' => 0,
                'compradores_sobre_70_total' => 0,
                'compradores_bajo_70_total' => 0,
                'visitados_sin_compra_total' => 0,
                'pendientes_total' => 0,
                'monto_total' => 0.0,
                'ticket_promedio_global' => 0.0,
                'efectividad_global_pct' => 0.0,
                'rutas_cumplen' => 0,
                'rutas_deficit' => 0,
                'puntos_totales_ganados' => 0.0,
                'puntos_maximos_posibles' => 0.0,
                'porcentaje_puntos_obtenidos' => 0.0,
                'umbral_efectividad_pct' => self::UMBRAL_EFECTIVIDAD_PCT,
            ],
            'rutas' => [],
            'funnel_global' => [
                'etapas' => [],
                'fugas' => ['por_no_visita' => 0, 'por_rechazo_visita' => 0, 'por_bajo_minimo' => 0],
            ],
            'distribucion_calidad' => [],
            'clientes_detalle_por_ruta' => [],
        ];
    }

    /**
     * Normaliza cadenas de texto para comparaciones seguras.
     */
    private function normalizarTexto(?string $str): string
    {
        if (empty($str)) {
            return '';
        }
        $s = trim(preg_replace('/\s+/', ' ', mb_strtolower($str)));
        return str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $s);
    }
}
