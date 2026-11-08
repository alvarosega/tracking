<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceClientesRutaAction
{
    public function __construct(
        private readonly GetAvanceCumplimientoDataAction $cumplimientoAction
    ) {}

    /**
     * Obtiene el desglose auditado de una ruta: por clientes individuales y ventas por día acumuladas.
     *
     * @param string $ruta
     * @param string $kpiClave
     * @param string $fechaInicio
     * @param string $fechaCorte
     * @return array{
     *     ruta: string,
     *     kpi: string,
     *     fecha_inicio: string,
     *     fecha_corte: string,
     *     clientes: array<int, array<string, mixed>>,
     *     por_dia: array<int, array<string, mixed>>,
     *     resumen: array<string, mixed>
     * }
     */
    public function execute(
        string $ruta,
        string $kpiClave = 'vol_general',
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        bool $omitirUnitarios3m = false,
        int $mesesHistorico = 3,
        int $frecuenciaMinima = 1,
        int $cantidadMinima = 1
    ): array {
        $rutaClean = strtoupper(trim($ruta));
        $mesesHistorico = max(1, min(8, $mesesHistorico));
        $frecuenciaMinima = max(1, $frecuenciaMinima);
        $cantidadMinima = $cantidadMinima > 1 ? $cantidadMinima : ($omitirUnitarios3m ? 2 : 1);

        @ini_set('memory_limit', '512M');

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio || Carbon::parse($fechaInicio)->format('Y-m') !== $carbonCorte->format('Y-m')) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        $mesesCerrados = $this->cumplimientoAction->obtenerMesesCerrados($carbonCorte, $mesesHistorico);
        $cantMesesEval = max(1, count($mesesCerrados));

        // 1. Cartera Oficial desde pan_ruteo
        $filasCartera = DB::connection('supervisor')->table('pan_ruteo')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->where('cliente_id', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('estado')
                  ->orWhereIn(DB::raw("UPPER(TRIM(estado))"), ['ACTIVO', 'ACTIVA', 'VIGENTE']);
            })
            ->select([
                'cliente_id',
                DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), CONCAT('Cliente #', cliente_id))) as cliente"),
                DB::raw("MAX(COALESCE(NULLIF(TRIM(vendedor), ''), 'No Asignado')) as vendedor_ruteo"),
                DB::raw("COALESCE(MAX(NULLIF(TRIM(dia_norm), '')), MAX(NULLIF(TRIM(dia), '')), 'Sin Día') as dia_visita"),
            ])
            ->groupBy('cliente_id')
            ->get();

        $cartera = [];
        foreach ($filasCartera as $fc) {
            $cid = (int) $fc->cliente_id;
            $diaNorm = ucfirst(strtolower((string) $fc->dia_visita));
            $cartera[$cid] = [
                'cliente_id' => $cid,
                'cliente' => $fc->cliente,
                'vendedor_ruteo' => $fc->vendedor_ruteo,
                'dia' => $diaNorm ?: 'Sin Día',
                'dia_visita' => $diaNorm ?: 'Sin Día',
                'monto' => 0.0,
                'cantidad' => 0,
                'vendedores_factura' => [],
                'fechas_compra' => [],
            ];
        }

        $carteraClientIds = array_values(array_filter(array_keys($cartera), fn ($id) => is_numeric($id) && (int) $id > 0));

        // 2. Ventas registradas de la cartera oficial en el rango (consultado por cliente_id, no por vendedor ni ruta)
        $ventasRutaQuery = DB::connection('supervisor')->table('fact_ventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '!=', 0)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            });

        if ($rutaClean === 'SIN RUTA') {
            if (!empty($carteraClientIds)) {
                $ventasRutaQuery->whereIn('cliente_id', $carteraClientIds);
            } else {
                $ventasRutaQuery->where(function ($q): void {
                    $q->whereNull('ruta')
                      ->orWhere(DB::raw("TRIM(ruta)"), '')
                      ->orWhere(DB::raw("TRIM(UPPER(ruta))"), 'SIN RUTA');
                });
            }
        } else {
            if (!empty($carteraClientIds)) {
                $ventasRutaQuery->whereIn('cliente_id', $carteraClientIds);
            } else {
                $ventasRutaQuery->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean);
            }
        }

        $ventasRuta = $ventasRutaQuery
            ->select([
                'cliente_id',
                DB::raw("COALESCE(NULLIF(TRIM(cliente), ''), 'CLIENTE S/N') as cliente_factura"),
                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                DB::raw("COALESCE(NULLIF(TRIM(nro_factura), ''), 'S/F') as nro_factura"),
                DB::raw("TRIM(codigo) as codigo"),
                DB::raw("LOWER(TRIM(COALESCE(producto, ''))) as producto"),
                DB::raw("LOWER(TRIM(COALESCE(jerarquia3, ''))) as jerarquia3"),
                DB::raw("UPPER(TRIM(COALESCE(categoria, ''))) as categoria"),
                DB::raw("COALESCE(cantidad, 0) as cantidad"),
                DB::raw("COALESCE(monto_final, 0.0) as monto_final"),
                DB::raw("COALESCE(TRIM(vendedor), 'Sin Asignar') as vendedor_factura"),
            ])
            ->orderBy('fecha_norm', 'asc')
            ->get();

        $diasSemanaEs = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
            5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
        ];

        $comprasPorCliente = [];
        $diasAcum = [];

        foreach ($ventasRuta as $v) {
            $cid = (int) ($v->cliente_id ?: 0);
            if ($cid === 0) {
                $cid = -1 * (abs(crc32((string) $v->cliente_factura)) ?: 1);
            }

            $prod = (string) $v->producto;
            $j3 = (string) $v->jerarquia3;
            $cod = (string) $v->codigo;
            $cat = (string) $v->categoria;

            if ($this->cumplimientoAction->aplicaTransaccion($kpiClave, $prod, $j3, $cod, $cat)) {
                if (!isset($cartera[$cid])) {
                    $cartera[$cid] = [
                        'cliente_id' => $cid,
                        'cliente' => $v->cliente_factura ?: "Cliente #{$cid}",
                        'vendedor_ruteo' => $v->vendedor_factura ?: 'Sin Asignar',
                        'dia' => 'Histórico',
                        'dia_visita' => 'Histórico',
                        'monto' => 0.0,
                        'cantidad' => 0,
                        'vendedores_factura' => [],
                        'fechas_compra' => [],
                    ];
                }

                $mVal = (float) $v->monto_final;
                $cVal = (int) $v->cantidad;
                $fVal = (string) $v->fecha;

                $comprasPorCliente[$cid][] = [$fVal, $mVal];

                $cartera[$cid]['monto'] += $mVal;
                $cartera[$cid]['cantidad'] += $cVal;
                $cartera[$cid]['fechas_compra'][$fVal] = true;
                if ($v->vendedor_factura && $v->vendedor_factura !== 'Sin Asignar') {
                    $cartera[$cid]['vendedores_factura'][$v->vendedor_factura] = true;
                }

                // Agrupación por Día
                if (!isset($diasAcum[$fVal])) {
                    try {
                        $dt = Carbon::parse($fVal);
                        $diaNombre = $diasSemanaEs[$dt->dayOfWeekIso] ?? 'Otro';
                    } catch (\Throwable) {
                        $diaNombre = 'Otro';
                    }

                    $diasAcum[$fVal] = [
                        'fecha' => $fVal,
                        'dia_semana' => $diaNombre,
                        'venta_total' => 0.0,
                        'cantidad_total' => 0,
                        'clientes_map' => [],
                    ];
                }

                $diasAcum[$fVal]['venta_total'] += $mVal;
                $diasAcum[$fVal]['cantidad_total'] += $cVal;

                if (!isset($diasAcum[$fVal]['clientes_map'][$cid])) {
                    $diasAcum[$fVal]['clientes_map'][$cid] = [
                        'cliente_id' => $cid,
                        'cliente' => $cartera[$cid]['cliente'],
                        'vendedor' => $v->vendedor_factura,
                        'dia_visita' => $cartera[$cid]['dia'],
                        'monto' => 0.0,
                        'cantidad' => 0,
                        'facturas' => [],
                    ];
                }
                $diasAcum[$fVal]['clientes_map'][$cid]['monto'] += $mVal;
                $diasAcum[$fVal]['clientes_map'][$cid]['cantidad'] += $cVal;
                if ($v->nro_factura && $v->nro_factura !== 'S/F') {
                    $diasAcum[$fVal]['clientes_map'][$cid]['facturas'][$v->nro_factura] = true;
                }
            }
        }

        // 3. Extracción de Histórico de los últimos N meses cerrados para los clientes de la ruta
        $histInicio = $mesesCerrados[0]['inicio'];
        $histFin = end($mesesCerrados)['fin'];

        $carteraClientIds = array_values(array_filter(array_keys($cartera), fn ($id) => is_numeric($id) && (int) $id > 0));

        $historico3mRaw = [];
        $sugeridoClienteMap = [];
        $histClienteMap = [];
        $ymsCerrados = array_column($mesesCerrados, 'ym');
        $totalesMesesRuta = array_fill_keys($ymsCerrados, 0.0);
        $totalRutaAcum3m = 0.0;
        foreach ($cartera as $cid => $item) {
            $histClienteMap[$cid] = array_fill_keys($ymsCerrados, 0.0);
        }

        if (!empty($carteraClientIds)) {
            $historicoQuery = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn('cliente_id', $carteraClientIds)
                ->where('fecha_norm', '>=', $histInicio)
                ->where('fecha_norm', '<=', $histFin)
                ->where('monto_final', '!=', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                });

            $historico3mRaw = $historicoQuery
                ->select([
                    'cliente_id',
                    DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m') as mes_ym"),
                    DB::raw("SUM(monto_final) as total_monto"),
                ])
                ->groupBy('cliente_id', DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m')"))
                ->get();

            foreach ($historico3mRaw as $h) {
                $cid = (int) $h->cliente_id;
                $ym = (string) $h->mes_ym;
                if (isset($histClienteMap[$cid][$ym])) {
                    $histClienteMap[$cid][$ym] += (float) $h->total_monto;
                }
            }

            // Totales de la ruta para los meses cerrados
            $totalesMesesRuta = array_fill_keys($ymsCerrados, 0.0);
            $totalRutaAcum3m = 0.0;
            foreach ($histClienteMap as $cid => $mMap) {
                foreach ($mMap as $ym => $val) {
                    $totalesMesesRuta[$ym] += $val;
                    $totalRutaAcum3m += $val;
                }
            }

            $mesYm = $carbonCorte->format('Y-m');
            $objetivoMensualRuta = $this->cumplimientoAction->obtenerMetaMesRuta($rutaClean, $mesYm);

            // Cuota objetivo por cliente proporcional a su peso en la ruta
            $cuotaObjetivoMap = [];
            foreach ($cartera as $cid => $item) {
                $totCli = array_sum($histClienteMap[$cid] ?? []);
                $cuotaObjetivoMap[$cid] = ($totalRutaAcum3m > 0 && $objetivoMensualRuta > 0)
                    ? round(($totCli / $totalRutaAcum3m) * $objetivoMensualRuta, 2)
                    : 0.0;
            }

            // Extracción de productos de los meses cerrados para calcular el Total Sugerido por Cliente y Pareto
            $prodsRutaRaw = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn('cliente_id', $carteraClientIds)
                ->where('fecha_norm', '>=', $histInicio)
                ->where('fecha_norm', '<=', $histFin)
                ->where('monto_final', '!=', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                })
                ->select([
                    'cliente_id',
                    DB::raw("TRIM(codigo) as codigo"),
                    DB::raw("COALESCE(producto, 'Sin descripción') as producto"),
                    DB::raw("COALESCE(categoria, '') as categoria"),
                    DB::raw("COALESCE(jerarquia3, '') as jerarquia3"),
                    DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                    DB::raw("COALESCE(cantidad, 0) as cantidad"),
                    DB::raw("COALESCE(monto_final, 0.0) as monto_final"),
                    DB::raw("COALESCE(nro_factura, '') as nro_factura"),
                ])
                ->orderBy('fecha_norm', 'desc')
                ->get();

            $clienteProdsMap = [];
            $paretoPorDia = [];
            $paretoRuta = [];

            foreach ($prodsRutaRaw as $pr) {
                $cId = (int) $pr->cliente_id;
                $diaVisita = $cartera[$cId]['dia_visita'] ?? 'Sin Día';
                $prodNombre = trim((string) $pr->producto);
                $pKey = $prodNombre !== '' ? mb_strtoupper($prodNombre) : trim((string) $pr->codigo);
                $cant = (int) $pr->cantidad;
                $monto = (float) $pr->monto_final;
                $f = (string) $pr->fecha;
                $fact = (string) ($pr->nro_factura ?? '');
                $cod = trim((string) $pr->codigo);

                // 1. Acumulado por cliente
                if (!isset($clienteProdsMap[$cId][$pKey])) {
                    $clienteProdsMap[$cId][$pKey] = [
                        'producto' => $prodNombre ?: $cod,
                        'codigo' => $cod,
                        'cantidad_3m' => 0,
                        'precio_ultima_compra' => 0.0,
                        'fecha_precio' => null,
                        'fechas' => [],
                        'facturas' => [],
                    ];
                }

                $clienteProdsMap[$cId][$pKey]['cantidad_3m'] += $cant;
                if ($f) {
                    $clienteProdsMap[$cId][$pKey]['fechas'][$f] = true;
                }
                if ($fact !== '' && $fact !== 'S/F') {
                    $clienteProdsMap[$cId][$pKey]['facturas'][$fact] = true;
                }

                if ($cant > 0 && $monto > 0) {
                    if ($clienteProdsMap[$cId][$pKey]['fecha_precio'] === null || $f >= $clienteProdsMap[$cId][$pKey]['fecha_precio']) {
                        $clienteProdsMap[$cId][$pKey]['fecha_precio'] = $f;
                        $clienteProdsMap[$cId][$pKey]['precio_ultima_compra'] = round($monto / $cant, 2);
                    }
                }

                // 2. Acumulado Pareto por Día de Visita
                if (!isset($paretoPorDia[$diaVisita][$pKey])) {
                    $paretoPorDia[$diaVisita][$pKey] = [
                        'producto' => $prodNombre ?: $cod,
                        'codigo' => $cod,
                        'total_monto' => 0.0,
                        'total_cantidad' => 0,
                        'precio' => 0.0,
                        'fecha_precio' => null,
                        'fechas' => [],
                        'facturas' => [],
                        'clientes' => [],
                    ];
                }
                $paretoPorDia[$diaVisita][$pKey]['total_monto'] += $monto;
                $paretoPorDia[$diaVisita][$pKey]['total_cantidad'] += $cant;
                if ($f) {
                    $paretoPorDia[$diaVisita][$pKey]['fechas'][$f] = true;
                }
                if ($fact !== '' && $fact !== 'S/F') {
                    $paretoPorDia[$diaVisita][$pKey]['facturas'][$fact] = true;
                }
                if ($cId > 0) {
                    $paretoPorDia[$diaVisita][$pKey]['clientes'][$cId] = true;
                }
                if ($cant > 0 && $monto > 0) {
                    if ($paretoPorDia[$diaVisita][$pKey]['fecha_precio'] === null || $f >= $paretoPorDia[$diaVisita][$pKey]['fecha_precio']) {
                        $paretoPorDia[$diaVisita][$pKey]['fecha_precio'] = $f;
                        $paretoPorDia[$diaVisita][$pKey]['precio'] = round($monto / $cant, 2);
                    }
                }

                // 3. Acumulado Pareto general de la Ruta (fallback)
                if (!isset($paretoRuta[$pKey])) {
                    $paretoRuta[$pKey] = [
                        'producto' => $prodNombre ?: $cod,
                        'codigo' => $cod,
                        'total_monto' => 0.0,
                        'total_cantidad' => 0,
                        'precio' => 0.0,
                        'fecha_precio' => null,
                        'fechas' => [],
                        'facturas' => [],
                        'clientes' => [],
                    ];
                }
                $paretoRuta[$pKey]['total_monto'] += $monto;
                $paretoRuta[$pKey]['total_cantidad'] += $cant;
                if ($f) {
                    $paretoRuta[$pKey]['fechas'][$f] = true;
                }
                if ($fact !== '' && $fact !== 'S/F') {
                    $paretoRuta[$pKey]['facturas'][$fact] = true;
                }
                if ($cId > 0) {
                    $paretoRuta[$pKey]['clientes'][$cId] = true;
                }
                if ($cant > 0 && $monto > 0) {
                    if ($paretoRuta[$pKey]['fecha_precio'] === null || $f >= $paretoRuta[$pKey]['fecha_precio']) {
                        $paretoRuta[$pKey]['fecha_precio'] = $f;
                        $paretoRuta[$pKey]['precio'] = round($monto / $cant, 2);
                    }
                }
            }

            // Ordenar Pareto de cada día y de la ruta por cantidad comprada y frecuencia de compra descendente (no por monto)
            foreach ($paretoPorDia as $diaV => &$itemsDia) {
                foreach ($itemsDia as &$it) {
                    if (($it['precio'] ?? 0) <= 0 && $it['total_cantidad'] > 0 && $it['total_monto'] > 0) {
                        $it['precio'] = round($it['total_monto'] / $it['total_cantidad'], 2);
                    }
                    $it['frecuencia'] = max(count($it['fechas'] ?? []), count($it['facturas'] ?? []));
                    $it['clientes_count'] = count($it['clientes'] ?? []);
                }
                unset($it);
                uasort($itemsDia, function ($a, $b) {
                    if ($a['total_cantidad'] !== $b['total_cantidad']) {
                        return $b['total_cantidad'] <=> $a['total_cantidad'];
                    }
                    if ($a['frecuencia'] !== $b['frecuencia']) {
                        return $b['frecuencia'] <=> $a['frecuencia'];
                    }
                    return ($b['clientes_count'] ?? 0) <=> ($a['clientes_count'] ?? 0);
                });
            }
            unset($itemsDia);

            foreach ($paretoRuta as &$it) {
                if (($it['precio'] ?? 0) <= 0 && $it['total_cantidad'] > 0 && $it['total_monto'] > 0) {
                    $it['precio'] = round($it['total_monto'] / $it['total_cantidad'], 2);
                }
                $it['frecuencia'] = max(count($it['fechas'] ?? []), count($it['facturas'] ?? []));
                $it['clientes_count'] = count($it['clientes'] ?? []);
            }
            unset($it);
            uasort($paretoRuta, function ($a, $b) {
                if ($a['total_cantidad'] !== $b['total_cantidad']) {
                    return $b['total_cantidad'] <=> $a['total_cantidad'];
                }
                if ($a['frecuencia'] !== $b['frecuencia']) {
                    return $b['frecuencia'] <=> $a['frecuencia'];
                }
                return ($b['clientes_count'] ?? 0) <=> ($a['clientes_count'] ?? 0);
            });

            // Cálculo del sugerido por cliente: hábitos base + complemento Core Pareto si no alcanza el objetivo
            foreach ($cartera as $cId => $itemCart) {
                $pList = $clienteProdsMap[$cId] ?? [];
                $totalSug = 0.0;
                $prodsSugeridos = [];

                foreach ($pList as $pKey => $pData) {
                    $frec = max(count($pData['fechas']), count($pData['facturas']));
                    if ($frec < $frecuenciaMinima) {
                        continue;
                    }
                    $c3m = $pData['cantidad_3m'];
                    if ($c3m >= $cantidadMinima) {
                        $cantSug = (int) ceil($c3m / (float) $cantMesesEval);
                        $pUlt = $pData['precio_ultima_compra'];
                        if ($pUlt <= 0 && $c3m > 0) {
                            $pUlt = $paretoRuta[$pKey]['precio'] ?? 0.0;
                        }
                        if ($cantSug > 0 && $pUlt > 0) {
                            $prodsSugeridos[$pKey] = $cantSug;
                            $totalSug += round($cantSug * $pUlt, 2);
                        }
                    }
                }

                // Complemento Core Pareto (solo para Cobertura Core si totalSug < cuotaObjetivo)
                if ($kpiClave === 'cob_core') {
                    $cuotaObj = $cuotaObjetivoMap[$cId] ?? 0.0;
                    if ($cuotaObj > 0 && $totalSug < $cuotaObj) {
                        $diaV = $itemCart['dia_visita'] ?? 'Sin Día';
                        $listaPareto = !empty($paretoPorDia[$diaV]) ? $paretoPorDia[$diaV] : $paretoRuta;
                        if (empty($listaPareto)) {
                            $listaPareto = $paretoRuta;
                        }

                        // Fase 1: Añadir 1 unidad de productos Pareto que el cliente aún no tiene sugeridos
                        foreach ($listaPareto as $pKey => $pInfo) {
                            if (($prodsSugeridos[$pKey] ?? 0) > 0) {
                                continue;
                            }
                            $pUlt = $pList[$pKey]['precio_ultima_compra'] ?? ($pInfo['precio'] ?? 0.0);
                            if ($pUlt <= 0) {
                                continue;
                            }

                            $prodsSugeridos[$pKey] = 1;
                            $totalSug += $pUlt;

                            if ($totalSug >= $cuotaObj) {
                                break;
                            }
                        }

                        // Fase 2: Si aún no alcanza el objetivo, incrementar unidades de los productos Pareto top
                        if ($totalSug < $cuotaObj && !empty($listaPareto)) {
                            $maxIter = 500;
                            $iter = 0;
                            while ($totalSug < $cuotaObj && $iter < $maxIter) {
                                $agregado = false;
                                foreach ($listaPareto as $pKey => $pInfo) {
                                    $pUlt = $pList[$pKey]['precio_ultima_compra'] ?? ($pInfo['precio'] ?? 0.0);
                                    if ($pUlt <= 0) {
                                        continue;
                                    }
                                    $prodsSugeridos[$pKey] = ($prodsSugeridos[$pKey] ?? 0) + 1;
                                    $totalSug += $pUlt;
                                    $agregado = true;
                                    $iter++;

                                    if ($totalSug >= $cuotaObj || $iter >= $maxIter) {
                                        break;
                                    }
                                }
                                if (!$agregado) {
                                    break;
                                }
                            }
                        }
                    }
                }

                $sugeridoClienteMap[$cId] = round($totalSug, 2);
            }
        } else {
            $totalRutaAcum3m = 0.0;
            $mesYm = $carbonCorte->format('Y-m');
            $objetivoMensualRuta = $this->cumplimientoAction->obtenerMetaMesRuta($rutaClean, $mesYm);
            $cuotaObjetivoMap = [];
        }
        $totalVentaRuta = array_sum(array_column($cartera, 'monto'));
        $clientesLista = [];

        foreach ($cartera as $cid => $item) {
            $esCobertura = str_starts_with($kpiClave, 'cob_');
            $tieneLogro = $esCobertura ? ($item['monto'] > 0 || $item['cantidad'] > 0) : ($item['monto'] > 0);
            $valLogro = $esCobertura ? ($tieneLogro ? 1 : 0) : round($item['monto'], 2);

            $vends = !empty($item['vendedores_factura'])
                ? implode(', ', array_keys($item['vendedores_factura']))
                : ($item['vendedor_ruteo'] ?: 'No Asignado');

            $pctRuta = ($totalVentaRuta > 0) ? round(($item['monto'] / $totalVentaRuta) * 100, 1) : 0.0;

            $comprasC = $comprasPorCliente[$cid] ?? [];
            $mAp = 0.0;
            $mRep = 0.0;
            if (!empty($comprasC)) {
                usort($comprasC, fn ($a, $b) => strcmp($a[0], $b[0]));
                $fPrimera = $comprasC[0][0];
                foreach ($comprasC as $c) {
                    if ($c[0] === $fPrimera) {
                        $mAp += $c[1];
                    } else {
                        $mRep += $c[1];
                    }
                }
            }

            // Datos del histórico de meses cerrados y sugerido
            $mesesBsCli = [];
            $mesesConCompra = 0;
            foreach ($mesesCerrados as $mObj) {
                $ym = $mObj['ym'];
                $valM = round($histClienteMap[$cid][$ym] ?? 0.0, 2);
                $mesesBsCli[$ym] = $valM;
                if ($valM > 0) {
                    $mesesConCompra++;
                }
            }
            $m1Val = round($mesesBsCli[$mesesCerrados[0]['ym'] ?? ''] ?? 0.0, 2);
            $m2Val = round($mesesBsCli[$mesesCerrados[1]['ym'] ?? ''] ?? 0.0, 2);
            $m3Val = round($mesesBsCli[$mesesCerrados[2]['ym'] ?? ''] ?? 0.0, 2);
            $totalCli3m = round(array_sum($mesesBsCli), 2);
            $pesoCli3m = ($totalRutaAcum3m > 0) ? round(($totalCli3m / $totalRutaAcum3m) * 100.0, 2) : 0.0;
            $cuotaObjetivo = ($totalRutaAcum3m > 0 && $objetivoMensualRuta > 0)
                ? round(($totalCli3m / $totalRutaAcum3m) * $objetivoMensualRuta, 2)
                : 0.0;
            $totalSugeridoCli = round($sugeridoClienteMap[$cid] ?? 0.0, 2);

            $clientesLista[] = [
                'cliente_id' => $cid,
                'cliente' => $item['cliente'],
                'vendedor' => $vends,
                'dia' => $item['dia'],
                'dia_visita' => $item['dia_visita'],
                'logrado' => $valLogro,
                'monto_bs' => round($item['monto'], 2),
                'cantidad' => $item['cantidad'],
                'monto_apertura' => round($mAp, 2),
                'monto_repeticion' => round($mRep, 2),
                'es_recompra' => ($mRep > 0),
                'total_visitas_compra' => count($item['fechas_compra']),
                'pct_ruta' => $pctRuta,
                'tiene_logro' => $tieneLogro,
                'meses_bs' => $mesesBsCli,
                'monto_mes_1' => $m1Val,
                'monto_mes_2' => $m2Val,
                'monto_mes_3' => $m3Val,
                'total_monto_3m' => $totalCli3m,
                'peso_3m' => $pesoCli3m,
                'total_sugerido' => $totalSugeridoCli,
                'cuota_objetivo' => $cuotaObjetivo,
                'meses_con_compra' => $mesesConCompra,
                'tiene_historico' => ($totalCli3m > 0),
            ];
        }

        // Ordenar clientes: para Cobertura Core por peso/total histórico 3M descendente; para otros por logro
        if ($kpiClave === 'cob_core') {
            usort($clientesLista, function ($a, $b) {
                if ($a['total_monto_3m'] !== $b['total_monto_3m']) {
                    return $b['total_monto_3m'] <=> $a['total_monto_3m'];
                }
                return strcmp((string) $a['cliente'], (string) $b['cliente']);
            });
        } else {
            usort($clientesLista, function ($a, $b) {
                if ($a['tiene_logro'] !== $b['tiene_logro']) {
                    return $b['tiene_logro'] ? 1 : -1;
                }
                if ($a['tiene_logro']) {
                    return $b['monto_bs'] <=> $a['monto_bs'];
                }
                return strcmp((string) $a['cliente'], (string) $b['cliente']);
            });
        }

        // Armar resultado por día cronológico y acumulado progresivo
        ksort($diasAcum);
        $diasOrdenados = [];
        $acumVentaDias = 0.0;

        foreach ($diasAcum as $fK => $dInfo) {
            $vDia = (float) $dInfo['venta_total'];
            $acumVentaDias += $vDia;

            $clientesDia = [];
            foreach ($dInfo['clientes_map'] as $cObj) {
                $clientesDia[] = [
                    'cliente_id' => $cObj['cliente_id'],
                    'cliente' => $cObj['cliente'],
                    'vendedor' => $cObj['vendedor'],
                    'dia_visita' => $cObj['dia_visita'],
                    'monto_bs' => round((float) $cObj['monto'], 2),
                    'cantidad' => $cObj['cantidad'],
                    'facturas' => implode(', ', array_keys($cObj['facturas'])),
                ];
            }
            usort($clientesDia, fn ($a, $b) => $b['monto_bs'] <=> $a['monto_bs']);

            $diasOrdenados[] = [
                'fecha' => $fK,
                'dia_semana' => $dInfo['dia_semana'],
                'total_clientes' => count($dInfo['clientes_map']),
                'venta_total' => round($vDia, 2),
                'venta_acumulada' => round($acumVentaDias, 2),
                'cantidad_total' => $dInfo['cantidad_total'],
                'clientes' => $clientesDia,
            ];
        }

        $totalSugeridoRuta = round(array_sum($sugeridoClienteMap), 2);

        return [
            'ruta' => $rutaClean,
            'kpi' => $kpiClave,
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'frecuencia_minima' => $frecuenciaMinima,
            'cantidad_minima' => $cantidadMinima,
            'meses_cerrados' => $mesesCerrados,
            'totales_3m_ruta' => [
                'meses_bs' => $totalesMesesRuta,
                'mes_1' => round($totalesMesesRuta[$mesesCerrados[0]['ym'] ?? ''] ?? 0.0, 2),
                'mes_2' => round($totalesMesesRuta[$mesesCerrados[1]['ym'] ?? ''] ?? 0.0, 2),
                'mes_3' => round($totalesMesesRuta[$mesesCerrados[2]['ym'] ?? ''] ?? 0.0, 2),
                'total_3m' => round($totalRutaAcum3m, 2),
                'total_sugerido' => $totalSugeridoRuta,
                'objetivo_mes' => round($objetivoMensualRuta, 2),
                'total_objetivo' => round($objetivoMensualRuta, 2),
            ],
            'clientes' => $clientesLista,
            'por_dia' => $diasOrdenados,
            'resumen' => [
                'total_cartera' => count($cartera),
                'compradores_unicos' => count(array_filter($clientesLista, fn ($c) => $c['tiene_logro'])),
                'con_historico_3m' => count(array_filter($clientesLista, fn ($c) => ($c['total_monto_3m'] ?? 0) > 0)),
                'venta_total_bs' => round($totalVentaRuta, 2),
                'venta_historica_3m_bs' => round($totalRutaAcum3m, 2),
                'total_sugerido_ruta' => $totalSugeridoRuta,
                'objetivo_mensual_ruta' => round($objetivoMensualRuta, 2),
                'dias_con_venta' => count($diasOrdenados),
            ],
        ];
    }
}

