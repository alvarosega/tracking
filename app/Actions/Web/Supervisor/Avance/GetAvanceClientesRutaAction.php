<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceClientesRutaAction
{
    use AvanceMetricHelpersTrait;

    public function __construct() {}

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
        $cantidadMinima = $cantidadMinima > 1 ? $cantidadMinima : ($omitirUnitarios3m ? 2 : ($kpiClave === 'cob_core' ? 4 : 1));

        @ini_set('memory_limit', '512M');

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio || Carbon::parse($fechaInicio)->format('Y-m') !== $carbonCorte->format('Y-m')) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        $mesesCerrados = $this->obtenerMesesCerrados($carbonCorte, $mesesHistorico);
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
                DB::raw("COALESCE(producto, 'Sin descripción') as producto"),
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
        $mtdClienteProdsMap = [];
        $mtdClienteCodMap = [];

        foreach ($ventasRuta as $v) {
            $cid = (int) ($v->cliente_id ?: 0);
            if ($cid === 0) {
                $cid = -1 * (abs(crc32((string) $v->cliente_factura)) ?: 1);
            }

            $prod = (string) $v->producto;
            $j3 = (string) $v->jerarquia3;
            $cod = (string) $v->codigo;
            $cat = (string) $v->categoria;

            if ($this->aplicaTransaccion($kpiClave, $prod, $j3, $cod, $cat)) {
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

                // Agrupación MTD por Cliente y SKU para cálculo de match sugerido
                $vProdNombre = trim((string) $v->producto);
                $vKey = $vProdNombre !== '' ? mb_strtoupper($vProdNombre) : trim((string) $v->codigo);
                $vCod = trim((string) $v->codigo);

                if (!isset($mtdClienteProdsMap[$cid][$vKey])) {
                    $mtdClienteProdsMap[$cid][$vKey] = ['monto' => 0.0, 'cantidad' => 0];
                }
                $mtdClienteProdsMap[$cid][$vKey]['monto'] += $mVal;
                $mtdClienteProdsMap[$cid][$vKey]['cantidad'] += $cVal;

                if ($vCod !== '') {
                    if (!isset($mtdClienteCodMap[$cid][$vCod])) {
                        $mtdClienteCodMap[$cid][$vCod] = ['monto' => 0.0, 'cantidad' => 0];
                    }
                    $mtdClienteCodMap[$cid][$vCod]['monto'] += $mVal;
                    $mtdClienteCodMap[$cid][$vCod]['cantidad'] += $cVal;
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

        $mesYm = $carbonCorte->format('Y-m');
        $objetivoMensualRuta = $this->obtenerMetaMesRuta($rutaClean, $mesYm);
        $cuotaObjetivoMap = [];

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
            $objetivoMensualRuta = $this->obtenerMetaMesRuta($rutaClean, $mesYm);

            // Cuota objetivo por cliente:
            // Para Cobertura Core, algoritmo de balanceo iterativo (Waterfilling) con piso de 150 Bs
            // garantizando que la suma final de cuotas sea exactamente igual a ObjetivoRuta
            if ($kpiClave === 'cob_core') {
                $ventasHistMap = [];
                foreach ($cartera as $cid => $item) {
                    $ventasHistMap[$cid] = (float) array_sum($histClienteMap[$cid] ?? []);
                }
                $cuotaObjetivoMap = $this->asignarCuotasMensualesPorCliente(
                    objetivoRuta: (float) $objetivoMensualRuta,
                    pisoMinimo: 150.0,
                    ventasHistoricasMap: $ventasHistMap
                );
            } else {
                $cuotaObjetivoMap = [];
                foreach ($cartera as $cid => $item) {
                    $totCli = array_sum($histClienteMap[$cid] ?? []);
                    $cuota = ($totalRutaAcum3m > 0 && $objetivoMensualRuta > 0)
                        ? round(($totCli / $totalRutaAcum3m) * $objetivoMensualRuta, 2)
                        : 0.0;
                    $cuotaObjetivoMap[$cid] = $cuota;
                }
            }

            // Extracción de productos de los meses cerrados para calcular el Total Sugerido por Cliente y Pareto
            $prodsRutaRaw = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn('cliente_id', $carteraClientIds)
                ->where('fecha_norm', '>=', $histInicio)
                ->where('fecha_norm', '<=', $fechaCorte)
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
                $j3 = (string) ($pr->jerarquia3 ?? '');
                $cat = (string) ($pr->categoria ?? '');

                if (!$this->aplicaTransaccion($kpiClave, $prodNombre, $j3, $cod, $cat)) {
                    continue;
                }

                $es3m = ($f <= $histFin);

                // 1. Acumulado por cliente
                if (!isset($clienteProdsMap[$cId][$pKey])) {
                    $clienteProdsMap[$cId][$pKey] = [
                        'producto' => $prodNombre ?: $cod,
                        'codigo' => $cod,
                        'categoria' => $cat,
                        'jerarquia3' => $j3,
                        'cantidad_3m' => 0,
                        'precio_ultima_compra' => 0.0,
                        'fecha_precio' => null,
                        'fechas' => [],
                        'facturas' => [],
                        'meses' => [],
                    ];
                }

                if ($es3m) {
                    $clienteProdsMap[$cId][$pKey]['cantidad_3m'] += $cant;
                    if ($f) {
                        $clienteProdsMap[$cId][$pKey]['fechas'][$f] = true;
                        $ym = substr($f, 0, 7);
                        $clienteProdsMap[$cId][$pKey]['meses'][$ym] = true;
                    }
                    if ($fact !== '' && $fact !== 'S/F') {
                        $clienteProdsMap[$cId][$pKey]['facturas'][$fact] = true;
                    }
                }

                if ($cant > 0 && $monto > 0) {
                    if (($clienteProdsMap[$cId][$pKey]['precio_ultima_compra'] ?? 0.0) === 0.0) {
                        $clienteProdsMap[$cId][$pKey]['fecha_precio'] = $f;
                        $clienteProdsMap[$cId][$pKey]['precio_ultima_compra'] = round($monto / $cant, 2);
                    }
                }

                if (!$es3m) {
                    continue;
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
                    if (($paretoPorDia[$diaVisita][$pKey]['precio'] ?? 0.0) === 0.0) {
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
                    if (($paretoRuta[$pKey]['precio'] ?? 0.0) === 0.0) {
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

            // MOTOR DE COMPRA SUGERIDA NIVELADA PARA COBERTURA CORE
            if ($kpiClave === 'cob_core') {
                $estadoDesarrolloClienteMap = [];
                $brechaClienteMap = [];
                $excesoClienteMap = [];
                $sugeridosPorClienteMap = [];

            foreach ($cartera as $cId => $itemCart) {
                $pList = $clienteProdsMap[$cId] ?? [];
                $cuotaObj = $cuotaObjetivoMap[$cId] ?? 150.0;

                // 1. Identificar SKUs habituales del cliente según filtros superiores
                $habitualesCli = [];
                foreach ($pList as $pKey => $pData) {
                    $c3m = (int) ($pData['cantidad_3m'] ?? 0);
                    $mesesDistintos = count($pData['meses'] ?? []);
                    $pUlt = (float) ($pData['precio_ultima_compra'] ?? 0.0);
                    if ($pUlt <= 0.0 && $c3m > 0) {
                        $pUlt = (float) ($paretoRuta[$pKey]['precio'] ?? 0.0);
                    }
                    if ($mesesDistintos >= $frecuenciaMinima && $c3m >= $cantidadMinima && $pUlt > 0.0) {
                        $habitualesCli[$pKey] = [
                            'pKey' => $pKey,
                            'pData' => $pData,
                            'producto' => $pData['producto'] ?? $pKey,
                            'codigo_producto' => $pData['codigo'] ?? '',
                            'categoria' => $pData['categoria'] ?? 'GENERAL',
                            'precio' => $pUlt,
                            'total_bs_3m' => round($c3m * $pUlt, 2),
                            'cantidad_3m' => $c3m,
                            'frecuencia_compra' => max(1, count($pData['fechas'] ?? [])),
                            'tipo_origen' => 'HABITO_CLIENTE',
                        ];
                    }
                }

                $estadoDesarrolloClienteMap[$cId] = 'consolidado';

                // PASO 1: Participación histórica dentro de la tienda (Mix Propio)
                $ventaTotalHabitual = array_sum(array_column($habitualesCli, 'total_bs_3m'));

                // Ordenar habituales por rotación en Bs descendente
                uasort($habitualesCli, fn ($a, $b) => ($b['total_bs_3m'] ?? 0.0) <=> ($a['total_bs_3m'] ?? 0.0));

                // PASO 2 y 3: Asignación base respetando TECHO HISTÓRICO estricto
                $subtotalBase = 0.0;
                foreach ($habitualesCli as $pk => &$hInfo) {
                    $mix_j = $ventaTotalHabitual > 0 ? ($hInfo['total_bs_3m'] / $ventaTotalHabitual) : (1.0 / count($habitualesCli));
                    $presupuestoTeorico_j = $cuotaObj * $mix_j;
                    $pj = $hInfo['precio'];

                    $c3m = (int) ($hInfo['cantidad_3m'] ?? 0);
                    $cantCalculada = max(1, (int) floor($presupuestoTeorico_j / $pj));
                    $cantBase = ($c3m > 0) ? min($cantCalculada, $c3m) : $cantCalculada;
                    $montoBase = round($cantBase * $pj, 2);

                    $hInfo['cant_sugerida'] = $cantBase;
                    $hInfo['monto_sugerido'] = $montoBase;
                    $subtotalBase = round($subtotalBase + $montoBase, 2);
                }
                unset($hInfo);

                // PASO 4: Si subtotalBase < cuotaObj (queda brecha):
                // Prioridad 1: Productos que el cliente ya conoce (historial no habitual del cliente, a 4 unidades)
                if ($subtotalBase < $cuotaObj) {
                    $candidatosHist = [];
                    foreach ($pList as $pKey => $pData) {
                        if (!isset($habitualesCli[$pKey])) {
                            $c3m = (int) ($pData['cantidad_3m'] ?? 0);
                            $pUlt = (float) ($pData['precio_ultima_compra'] ?? 0.0);
                            if ($pUlt <= 0.0 && $c3m > 0) {
                                $pUlt = (float) ($paretoRuta[$pKey]['precio'] ?? 0.0);
                            }
                            if ($c3m > 0 && $pUlt > 0.0) {
                                $candidatosHist[] = [
                                    'pKey' => $pKey,
                                    'pData' => $pData,
                                    'precio' => $pUlt,
                                    'cantidad_3m' => $c3m,
                                    'total_bs_3m' => round($c3m * $pUlt, 2),
                                ];
                            }
                        }
                    }
                    usort($candidatosHist, function ($a, $b) {
                        if ($a['cantidad_3m'] !== $b['cantidad_3m']) {
                            return $b['cantidad_3m'] <=> $a['cantidad_3m'];
                        }
                        return $b['total_bs_3m'] <=> $a['total_bs_3m'];
                    });

                    foreach ($candidatosHist as $cHist) {
                        if ($subtotalBase >= $cuotaObj) {
                            break;
                        }
                        $pk = $cHist['pKey'];
                        $pUlt = $cHist['precio'];
                        $cantNueva = 4; // Opción A: 4 unidades estándar Cobertura Core
                        $montoNuevo = round($cantNueva * $pUlt, 2);

                        $habitualesCli[$pk] = [
                            'pKey' => $pk,
                            'pData' => $cHist['pData'],
                            'producto' => $cHist['pData']['producto'] ?? $pk,
                            'codigo_producto' => $cHist['pData']['codigo'] ?? '',
                            'categoria' => $cHist['pData']['categoria'] ?? 'GENERAL',
                            'precio' => $pUlt,
                            'total_bs_3m' => $cHist['total_bs_3m'],
                            'cantidad_3m' => $cHist['cantidad_3m'],
                            'frecuencia_compra' => max(1, count($cHist['pData']['fechas'] ?? [])),
                            'cant_sugerida' => $cantNueva,
                            'monto_sugerido' => $montoNuevo,
                            'tipo_origen' => 'HISTORIAL_CLIENTE',
                        ];
                        $subtotalBase = round($subtotalBase + $montoNuevo, 2);
                    }
                }

                // Prioridad 2: Si aún queda brecha, completar con catálogo Pareto de la ruta (a 4 unidades)
                if ($subtotalBase < $cuotaObj && !empty($paretoRuta)) {
                    foreach ($paretoRuta as $pKey => $pItem) {
                        if ($subtotalBase >= $cuotaObj) {
                            break;
                        }
                        if (isset($habitualesCli[$pKey])) {
                            continue;
                        }
                        $pUlt = (float) ($pItem['precio'] ?? 0.0);
                        if ($pUlt <= 0.0) {
                            continue;
                        }
                        $cantNueva = 4; // Opción A: 4 unidades estándar Cobertura Core
                        $montoNuevo = round($cantNueva * $pUlt, 2);

                        $habitualesCli[$pKey] = [
                            'pKey' => $pKey,
                            'pData' => [
                                'producto' => $pItem['producto'],
                                'codigo' => $pItem['codigo'],
                            ],
                            'producto' => $pItem['producto'],
                            'codigo_producto' => $pItem['codigo'],
                            'categoria' => $pItem['categoria'] ?? 'GENERAL',
                            'precio' => $pUlt,
                            'total_bs_3m' => 0.0,
                            'cantidad_3m' => 0,
                            'frecuencia_compra' => 1,
                            'cant_sugerida' => $cantNueva,
                            'monto_sugerido' => $montoNuevo,
                            'es_core_pareto' => true,
                            'tipo_origen' => 'PARETO_RUTA',
                        ];
                        $subtotalBase = round($subtotalBase + $montoNuevo, 2);
                    }
                }

                $sugeridoClienteMap[$cId] = round($subtotalBase, 2);
                $excesoClienteMap[$cId] = round(max(0.0, $subtotalBase - $cuotaObj), 2);
                $brechaClienteMap[$cId] = round(max(0.0, $cuotaObj - $subtotalBase), 2);
                $sugeridosPorClienteMap[$cId] = $habitualesCli;
            }
        } else {
            $totalRutaAcum3m = 0.0;
            $mesYm = $carbonCorte->format('Y-m');
            $objetivoMensualRuta = $this->obtenerMetaMesRuta($rutaClean, $mesYm);
            $cuotaObjetivoMap = [];
            $estadoDesarrolloClienteMap = [];
            $brechaClienteMap = [];
            $excesoClienteMap = [];

            foreach ($cartera as $cId => $itemCart) {
                $pList = $clienteProdsMap[$cId] ?? [];
                $totalSug = 0.0;
                foreach ($pList as $pKey => $pData) {
                    $frec = max(count($pData['fechas'] ?? []), count($pData['facturas'] ?? []));
                    if ($frec < $frecuenciaMinima) {
                        continue;
                    }
                    $c3m = (int) ($pData['cantidad_3m'] ?? 0);
                    if ($c3m >= $cantidadMinima) {
                        $cantSug = (int) ceil($c3m / (float) max(1, $cantMesesEval));
                        $pUlt = (float) ($pData['precio_ultima_compra'] ?? 0.0);
                        if ($cantSug > 0 && $pUlt > 0.0) {
                            $totalSug += round($cantSug * $pUlt, 2);
                        }
                    }
                }
                $sugeridoClienteMap[$cId] = round($totalSug, 2);
                $estadoDesarrolloClienteMap[$cId] = 'consolidado';
                $brechaClienteMap[$cId] = 0.0;
                $excesoClienteMap[$cId] = 0.0;
            }
        }
        }
        // Evidencia fotográfica del punto de venta (visitas)
        $fotosClientesMap = [];
        if (!empty($carteraClientIds)) {
            $fotosRaw = DB::table('visitas')
                ->whereIn('client_id', $carteraClientIds)
                ->whereNotNull('photo_path')
                ->where('photo_path', '!=', '')
                ->select(['client_id', 'photo_path', 'visited_at', 'status', 'comments'])
                ->orderByDesc('visited_at')
                ->get();

            foreach ($fotosRaw as $fRow) {
                $cId = (int) $fRow->client_id;
                if (!isset($fotosClientesMap[$cId])) {
                    $rawPath = ltrim(str_replace('\\', '/', (string) $fRow->photo_path), '/');
                    $url = '';
                    if ($rawPath !== '') {
                        if (str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
                            $url = $rawPath;
                        } elseif (str_starts_with($rawPath, 'storage/')) {
                            $url = '/' . $rawPath;
                        } else {
                            $url = '/storage/' . $rawPath;
                        }
                    }

                    $fotosClientesMap[$cId] = [
                        'photo_url' => $url,
                        'visited_at' => (string) $fRow->visited_at,
                        'status' => (string) $fRow->status,
                        'comments' => (string) ($fRow->comments ?? ''),
                        'total_fotos' => 0,
                    ];
                }
                $fotosClientesMap[$cId]['total_fotos']++;
            }
        }

        $totalVentaRuta = array_sum(array_column($cartera, 'monto'));
        $clientesLista = [];

        foreach ($cartera as $cid => $item) {
            if ($kpiClave === 'cob_core') {
                $habs = $sugeridosPorClienteMap[$cid] ?? [];
                if (empty($habs)) {
                    // Pendiente de desarrollo (0 sugerido) -> Venta MTD = 0.00
                    $valLogro = 0;
                    $montoBsCli = 0.0;
                    $cantMtdCli = 0;
                    $tieneLogro = false;
                } else {
                    $montoMatchSum = 0.0;
                    $cantMatchSum = 0;
                    foreach ($habs as $pk => $hInfo) {
                        $montoSug = (float) ($hInfo['monto_sugerido'] ?? 0.0);
                        $cantSug = (int) ($hInfo['cant_sugerida'] ?? 0);
                        if ($montoSug <= 0.0) {
                            continue;
                        }

                        $compradoMonto = $mtdClienteProdsMap[$cid][$pk]['monto'] ?? 0.0;
                        $compradoCant = $mtdClienteProdsMap[$cid][$pk]['cantidad'] ?? 0;
                        $cod = $hInfo['pData']['codigo'] ?? '';
                        if ($compradoMonto <= 0.0 && $cod !== '') {
                            $compradoMonto = $mtdClienteCodMap[$cid][$cod]['monto'] ?? 0.0;
                            $compradoCant = $mtdClienteCodMap[$cid][$cod]['cantidad'] ?? 0;
                        }

                        $montoMatchSum += min($compradoMonto, $montoSug);
                        $cantMatchSum += min($compradoCant, $cantSug);
                    }

                    $montoBsCli = round($montoMatchSum, 2);
                    $cantMtdCli = $cantMatchSum;
                    $tieneLogro = ($montoBsCli > 0);
                    $valLogro = $tieneLogro ? 1 : 0;
                }
            } else {
                $esCobertura = str_starts_with($kpiClave, 'cob_');
                $tieneLogro = $esCobertura ? ($item['monto'] > 0 || $item['cantidad'] > 0) : ($item['monto'] > 0);
                $valLogro = $esCobertura ? ($tieneLogro ? 1 : 0) : round($item['monto'], 2);
                $montoBsCli = round($item['monto'], 2);
                $cantMtdCli = $item['cantidad'];
            }

            $vends = !empty($item['vendedores_factura'])
                ? implode(', ', array_keys($item['vendedores_factura']))
                : ($item['vendedor_ruteo'] ?: 'No Asignado');

            $pctRuta = ($totalVentaRuta > 0) ? round(($montoBsCli / $totalVentaRuta) * 100, 1) : 0.0;

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
            $cuotaObjetivo = $cuotaObjetivoMap[$cid] ?? (($totalRutaAcum3m > 0 && $objetivoMensualRuta > 0)
                ? round(($totalCli3m / $totalRutaAcum3m) * $objetivoMensualRuta, 2)
                : ($kpiClave === 'cob_core' ? 150.0 : 0.0));
            $totalSugeridoCli = round($sugeridoClienteMap[$cid] ?? 0.0, 2);

            $clientesLista[] = [
                'cliente_id' => $cid,
                'cliente' => $item['cliente'],
                'vendedor' => $vends,
                'dia' => $item['dia'],
                'dia_visita' => $item['dia_visita'],
                'foto_pdv' => $fotosClientesMap[$cid]['photo_url'] ?? null,
                'total_fotos' => $fotosClientesMap[$cid]['total_fotos'] ?? 0,
                'fecha_ultima_foto' => $fotosClientesMap[$cid]['visited_at'] ?? null,
                'logrado' => $valLogro,
                'monto_bs' => $montoBsCli,
                'cantidad' => $cantMtdCli,
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
                'habito_total' => $totalSugeridoCli,
                'cuota_objetivo' => $cuotaObjetivo,
                'brecha' => $brechaClienteMap[$cid] ?? round(max(0.0, $cuotaObjetivo - $totalSugeridoCli), 2),
                'exceso' => $excesoClienteMap[$cid] ?? round(max(0.0, $totalSugeridoCli - $cuotaObjetivo), 2),
                'estado_desarrollo' => $estadoDesarrolloClienteMap[$cid] ?? ($totalSugeridoCli > 0 ? 'consolidado' : 'pendiente_desarrollo'),
                'meses_con_compra' => $mesesConCompra,
                'tiene_historico' => ($totalCli3m > 0),
                'sugeridos' => array_values($sugeridosPorClienteMap[$cid] ?? []),
            ];
        }

        $totalVentaRuta = array_sum(array_column($clientesLista, 'monto_bs'));
        foreach ($clientesLista as &$cItem) {
            $cItem['pct_ruta'] = ($totalVentaRuta > 0) ? round(($cItem['monto_bs'] / $totalVentaRuta) * 100, 1) : 0.0;
        }
        unset($cItem);

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
            'sugeridos_por_cliente' => $sugeridosPorClienteMap ?? [],
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

    /**
     * Algoritmo AsignarCuotasMensualesPorCliente (Waterfilling / Balanceo Iterativo).
     * Garantiza que ningún cliente quede por debajo del piso de 150 Bs y que la suma de cuotas
     * asignadas coincida exactamente con el ObjetivoRuta, distribuyendo el remanente proporcionalmente.
     *
     * @param float $objetivoRuta Presupuesto total de la ruta (Bs)
     * @param float $pisoMinimo Umbral mínimo obligatorio por cliente (150.00 Bs)
     * @param array<int, float> $ventasHistoricasMap Arreglo de [cliente_id => venta_historica_3m]
     * @return array<int, float> Arreglo de [cliente_id => cuota_asignada]
     */
    public function asignarCuotasMensualesPorCliente(
        float $objetivoRuta,
        float $pisoMinimo,
        array $ventasHistoricasMap
    ): array {
        $totalClientes = count($ventasHistoricasMap);
        if ($totalClientes === 0 || $objetivoRuta <= 0.0) {
            return array_fill_keys(array_keys($ventasHistoricasMap), $pisoMinimo);
        }

        // 1. Validar viabilidad matemática
        $pisoTotalRequerido = $totalClientes * $pisoMinimo;
        if ($objetivoRuta < $pisoTotalRequerido) {
            // El objetivo no cubre el piso total; asignar piso mínimo a todos
            return array_fill_keys(array_keys($ventasHistoricasMap), $pisoMinimo);
        }

        // 2. Inicializar variables
        $presupuestoRestante = $objetivoRuta;
        $cuotasAsignadas = [];
        $fijados = [];
        $clientesRestantes = array_keys($ventasHistoricasMap);

        foreach ($clientesRestantes as $cid) {
            $cuotasAsignadas[$cid] = 0.0;
            $fijados[$cid] = false;
        }

        // 3. Bucle de balanceo iterativo (Waterfilling)
        // Garantiza que nadie quede bajo 150 y la suma sea exactamente igual a ObjetivoRuta
        do {
            $hayClientesBajoPiso = false;

            // Sumar venta histórica de clientes no fijados
            $sumaHistoricaRestante = 0.0;
            foreach ($clientesRestantes as $cid) {
                $sumaHistoricaRestante += (float) ($ventasHistoricasMap[$cid] ?? 0.0);
            }

            $nuevosFijadosEnEstaRonda = [];
            $cantRestantes = count($clientesRestantes);

            // Evaluar cuota proporcional sobre el presupuesto disponible
            foreach ($clientesRestantes as $cid) {
                $vHist = (float) ($ventasHistoricasMap[$cid] ?? 0.0);
                if ($sumaHistoricaRestante > 0.0) {
                    $pesoRelativo = $vHist / $sumaHistoricaRestante;
                    $cuotaCalculada = $presupuestoRestante * $pesoRelativo;
                } else {
                    // Si todos los restantes tienen 0 histórico, se reparte equitativamente
                    $cuotaCalculada = $cantRestantes > 0 ? ($presupuestoRestante / $cantRestantes) : $pisoMinimo;
                }

                // Si no alcanza el piso (o es cliente nuevo/cero ventas), se fija en 150
                if ($cuotaCalculada < $pisoMinimo) {
                    $cuotasAsignadas[$cid] = $pisoMinimo;
                    $fijados[$cid] = true;
                    $presupuestoRestante -= $pisoMinimo;
                    $hayClientesBajoPiso = true;
                    $nuevosFijadosEnEstaRonda[$cid] = true;
                } else {
                    $cuotasAsignadas[$cid] = $cuotaCalculada;
                }
            }

            // Filtrar la lista dejando solo los que no han sido fijados al piso
            $clientesRestantes = array_values(array_filter(
                $clientesRestantes,
                fn ($cid) => empty($nuevosFijadosEnEstaRonda[$cid]) && !$fijados[$cid]
            ));

        } while ($hayClientesBajoPiso && count($clientesRestantes) > 0);

        // 4. Redondeo a 2 decimales y ajuste de centavos
        $sumaFinalCuotas = 0.0;
        foreach ($cuotasAsignadas as $cid => $cuota) {
            $cuotaRedondeada = round($cuota, 2);
            $cuotasAsignadas[$cid] = $cuotaRedondeada;
            $sumaFinalCuotas += $cuotaRedondeada;
        }

        $diferenciaCentavos = round($objetivoRuta - $sumaFinalCuotas, 2);
        if ($diferenciaCentavos != 0.0 && !empty($cuotasAsignadas)) {
            // Asignar o restar la diferencia de centavos al cliente con mayor cuota
            $clienteMayorCuotaId = null;
            $maxCuota = -1.0;
            foreach ($cuotasAsignadas as $cid => $cuota) {
                if ($cuota > $maxCuota) {
                    $maxCuota = $cuota;
                    $clienteMayorCuotaId = $cid;
                }
            }

            if ($clienteMayorCuotaId !== null) {
                $cuotasAsignadas[$clienteMayorCuotaId] = round($cuotasAsignadas[$clienteMayorCuotaId] + $diferenciaCentavos, 2);
            }
        }

        return $cuotasAsignadas;
    }
}

