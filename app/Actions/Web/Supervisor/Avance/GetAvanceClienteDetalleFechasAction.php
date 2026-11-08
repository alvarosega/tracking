<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceClienteDetalleFechasAction
{
    private const KPIS_NOMBRES = [
        'vol_general' => '1. Volumen General',
        'cob_general' => '2. Cobertura 100%',
        'vol_seda' => '3. Vol. Toque de Seda',
        'cob_seda' => '4. Cob. Toque de Seda',
        'vol_clinical' => '5. Vol. Clinical',
        'cob_clinical' => '6. Cob. Clinical',
        'cob_core' => '7. Cobertura Core',
    ];

    public function __construct(
        private readonly GetAvanceCumplimientoDataAction $cumplimientoAction
    ) {}

    /**
     * Obtiene el desglose cronológico y por producto de facturas para un cliente específico.
     * Incluye el histórico de los últimos 3 meses cerrados más el mes actual de corte.
     *
     * @param int $clienteId
     * @param string $ruta
     * @param string|null $fechaInicio
     * @param string|null $fechaCorte
     * @param string $kpiClave
     * @return array<string, mixed>
     */
    public function execute(
        int $clienteId,
        string $ruta,
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        string $kpiClave = 'vol_general',
        bool $omitirUnitarios3m = false,
        int $mesesHistorico = 3,
        int $frecuenciaMinima = 1,
        int $cantidadMinima = 1,
        ?float $cuotaObjetivo = null,
        ?string $diaVisita = null
    ): array {
        $rutaClean = strtoupper(trim($ruta));
        $mesesHistorico = max(1, min(8, $mesesHistorico));
        $frecuenciaMinima = max(1, $frecuenciaMinima);
        $cantidadMinima = $cantidadMinima > 1 ? $cantidadMinima : ($omitirUnitarios3m ? 2 : 1);

        @ini_set('memory_limit', '512M');

        if (!$diaVisita) {
            $ruteoRow = DB::connection('supervisor')->table('pan_ruteo')
                ->where('cliente_id', $clienteId)
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();
            if ($ruteoRow) {
                $diaVisita = ucfirst(strtolower((string) ($ruteoRow->dia_norm ?: $ruteoRow->dia)));
            }
        }

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        $mesesCerrados = $this->cumplimientoAction->obtenerMesesCerrados($carbonCorte, $mesesHistorico);
        $cantMesesEval = max(1, count($mesesCerrados));

        // Rango de evaluación: desde el inicio del 1° mes cerrado hasta la fecha de corte
        $histInicio = $mesesCerrados[0]['inicio'];
        $histFin3m = end($mesesCerrados)['fin'];
        $histFin = $fechaCorte;
        $ymActual = $carbonCorte->format('Y-m');
        $yms3m = array_column($mesesCerrados, 'ym');
        $porMesTemplate = array_fill_keys(array_merge($yms3m, [$ymActual]), 0.0);

        $query = DB::connection('supervisor')->table('fact_ventas')
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $histInicio)
            ->where('fecha_norm', '<=', $histFin)
            ->where('monto_final', '!=', 0)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            });

        // La consulta se realiza directamente por cliente_id, sin filtrar por ruta ni vendedor
        // para preservar todo el historial y ventas del cliente independientemente de reasignaciones.

        $filas = $query
            ->select([
                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                DB::raw("COALESCE(NULLIF(TRIM(nro_factura), ''), 'S/F') as nro_factura"),
                DB::raw("TRIM(codigo) as codigo"),
                DB::raw("COALESCE(producto, 'Sin descripción') as producto"),
                DB::raw("COALESCE(jerarquia3, '') as jerarquia3"),
                DB::raw("UPPER(TRIM(COALESCE(categoria, ''))) as categoria"),
                DB::raw("COALESCE(cantidad, 0) as cantidad"),
                DB::raw("COALESCE(monto_final, 0.0) as monto_final"),
                DB::raw("COALESCE(TRIM(vendedor), 'Sin Asignar') as vendedor"),
            ])
            ->orderBy('fecha_norm', 'desc')
            ->orderBy('nro_factura', 'asc')
            ->orderBy('monto_final', 'desc')
            ->get();

        $diasMap = [];
        $prodMap = [];
        $totalMonto = 0.0;
        $totalUnidades = 0;
        $totalMonto3m = 0.0;
        $totalUnidades3m = 0;
        $totalMontoMesActual = 0.0;
        $totalUnidadesMesActual = 0;

        foreach ($filas as $r) {
            $prod = (string) $r->producto;
            $j3 = (string) $r->jerarquia3;
            $cod = (string) $r->codigo;
            $cat = (string) $r->categoria;

            if (!$this->cumplimientoAction->aplicaTransaccion($kpiClave, $prod, $j3, $cod, $cat)) {
                continue;
            }

            $f = (string) $r->fecha;
            $ym = substr($f, 0, 7);
            $monto = (float) $r->monto_final;
            $cant = (int) $r->cantidad;
            $esMesActual = ($ym === $ymActual);
            $es3m = in_array($ym, $yms3m, true);

            $totalMonto += $monto;
            $totalUnidades += $cant;

            if ($es3m) {
                $totalMonto3m += $monto;
                $totalUnidades3m += $cant;
            }
            if ($esMesActual) {
                $totalMontoMesActual += $monto;
                $totalUnidadesMesActual += $cant;
            }

            // 1. Agrupación por Fecha de Compra
            if (!isset($diasMap[$f])) {
                $diasMap[$f] = [
                    'fecha' => $f,
                    'mes_ym' => $ym,
                    'es_mes_actual' => $esMesActual,
                    'es_historico_3m' => $es3m,
                    'total_bs' => 0.0,
                    'total_cantidad' => 0,
                    'vendedores' => [],
                    'facturas' => [],
                    'items' => [],
                ];
            }

            $diasMap[$f]['total_bs'] += $monto;
            $diasMap[$f]['total_cantidad'] += $cant;
            if ($r->vendedor && $r->vendedor !== 'Sin Asignar') {
                $diasMap[$f]['vendedores'][$r->vendedor] = true;
            }
            if ($r->nro_factura && $r->nro_factura !== 'S/F') {
                $diasMap[$f]['facturas'][$r->nro_factura] = true;
            }

            $diasMap[$f]['items'][] = [
                'nro_factura' => $r->nro_factura,
                'codigo' => $cod ?: 'S/C',
                'producto' => $prod,
                'categoria' => $cat,
                'jerarquia3' => $j3,
                'cantidad' => $cant,
                'monto_bs' => round($monto, 2),
                'vendedor' => $r->vendedor ?: 'Sin Asignar',
            ];

            // 2. Agrupación por Nombre de Producto (no por código)
            $prodNombre = trim((string) $prod);
            $prodKey = $prodNombre !== '' ? mb_strtoupper($prodNombre) : trim((string) $cod);
            if (!isset($prodMap[$prodKey])) {
                $prodMap[$prodKey] = [
                    'codigo' => $cod ?: 'S/C',
                    'codigos' => [],
                    'producto' => $prodNombre ?: ($cod ?: 'Sin descripción'),
                    'categoria' => $cat,
                    'jerarquia3' => $j3,
                    'total_bs' => 0.0,
                    'total_cantidad' => 0,
                    'total_bs_3m' => 0.0,
                    'total_cantidad_3m' => 0,
                    'total_bs_mes_actual' => 0.0,
                    'total_cantidad_mes_actual' => 0,
                    'ultima_fecha' => $f,
                    'fecha_precio_ultima_compra' => null,
                    'precio_ultima_compra' => 0.0,
                    'facturas' => [],
                    'fechas' => [],
                    'fechas_3m' => [],
                    'facturas_3m' => [],
                    'meses_3m' => [],
                    'compras_3m' => [],
                    'compras_mes_actual' => [],
                    'por_mes' => $porMesTemplate,
                ];
            }

            if ($cod && $cod !== 'S/C') {
                $prodMap[$prodKey]['codigos'][$cod] = true;
            }
            if ($cat && empty($prodMap[$prodKey]['categoria'])) {
                $prodMap[$prodKey]['categoria'] = $cat;
            }

            // Registrar el precio unitario de la última compra registrada
            if ($cant > 0 && $monto > 0) {
                if (($prodMap[$prodKey]['precio_ultima_compra'] ?? 0.0) === 0.0) {
                    $prodMap[$prodKey]['fecha_precio_ultima_compra'] = $f;
                    $prodMap[$prodKey]['precio_ultima_compra'] = round($monto / $cant, 2);
                }
            }

            $prodMap[$prodKey]['total_bs'] += $monto;
            $prodMap[$prodKey]['total_cantidad'] += $cant;

            $itemDetalle = [
                'fecha' => $f,
                'nro_factura' => $r->nro_factura ?: 'S/F',
                'cantidad' => $cant,
                'precio_unitario' => ($cant > 0 && $monto > 0) ? round($monto / $cant, 2) : 0.0,
                'monto_bs' => round($monto, 2),
                'vendedor' => $r->vendedor ?: 'Sin Asignar',
            ];

            if ($es3m) {
                $prodMap[$prodKey]['total_bs_3m'] += $monto;
                $prodMap[$prodKey]['total_cantidad_3m'] += $cant;
                $prodMap[$prodKey]['compras_3m'][] = $itemDetalle;
                $prodMap[$prodKey]['fechas_3m'][$f] = true;
                $prodMap[$prodKey]['meses_3m'][$ym] = true;
                if ($r->nro_factura && $r->nro_factura !== 'S/F') {
                    $prodMap[$prodKey]['facturas_3m'][$r->nro_factura] = true;
                }
            }
            if ($esMesActual) {
                $prodMap[$prodKey]['total_bs_mes_actual'] += $monto;
                $prodMap[$prodKey]['total_cantidad_mes_actual'] += $cant;
                $prodMap[$prodKey]['compras_mes_actual'][] = $itemDetalle;
            }
            if (isset($prodMap[$prodKey]['por_mes'][$ym])) {
                $prodMap[$prodKey]['por_mes'][$ym] += $monto;
            }
            if ($f > $prodMap[$prodKey]['ultima_fecha']) {
                $prodMap[$prodKey]['ultima_fecha'] = $f;
            }
            if ($r->nro_factura && $r->nro_factura !== 'S/F') {
                $prodMap[$prodKey]['facturas'][$r->nro_factura] = true;
            }
            $prodMap[$prodKey]['fechas'][$f] = true;
        }

        // Estructurar resultado cronológico
        $fechasResultado = [];
        krsort($diasMap);
        foreach ($diasMap as $f => $d) {
            $fechasResultado[] = [
                'fecha' => $f,
                'mes_ym' => $d['mes_ym'],
                'es_mes_actual' => $d['es_mes_actual'],
                'es_historico_3m' => $d['es_historico_3m'],
                'total_bs' => round($d['total_bs'], 2),
                'monto_bs' => round($d['total_bs'], 2),
                'total_cantidad' => $d['total_cantidad'],
                'vendedor' => !empty($d['vendedores']) ? implode(', ', array_keys($d['vendedores'])) : 'Sin Asignar',
                'facturas' => !empty($d['facturas']) ? implode(', ', array_keys($d['facturas'])) : 'S/F',
                'items' => $d['items'],
            ];
        }

        // Estructurar resultado por producto
        $productosResultado = array_values($prodMap);
        usort($productosResultado, fn ($a, $b) => $b['total_bs'] <=> $a['total_bs']);

        $totalSugeridoBs = 0.0;
        $totalSugeridoUnidades = 0;
        $totalSkus3m = 0;
        $totalSkusCubiertosMtd = 0;

        // Identificar SKUs habituales y calcular sugerido base
        $indicesHabituales = [];
        foreach ($productosResultado as $idx => &$p) {
            $cant3m = (int) ($p['total_cantidad_3m'] ?? 0);
            $cantMtd = (int) ($p['total_cantidad_mes_actual'] ?? 0);
            $frecuenciaCompra = max(count($p['fechas_3m'] ?? []), count($p['facturas_3m'] ?? []));
            $mesesDistintos3m = count($p['meses_3m'] ?? []);
            $p['frecuencia_compra'] = $frecuenciaCompra;
            $p['meses_distintos_3m'] = $mesesDistintos3m;

            // Si se exige cantidad mínima histórica (> 1), excluir productos que no alcancen esa cantidad acumulada (KPIs no-core)
            if ($kpiClave !== 'cob_core' && $cantidadMinima > 1 && ($cant3m < $cantidadMinima || ($cant3m === 0 && $cantMtd === 0))) {
                if ($cantMtd === 0) {
                    unset($productosResultado[$idx]);
                    continue;
                }
            }

            // Si se exige una frecuencia mínima, excluir productos que no alcancen dicha frecuencia en el histórico (KPIs no-core)
            if ($kpiClave !== 'cob_core' && $frecuenciaMinima > 1 && $cant3m > 0 && $frecuenciaCompra < $frecuenciaMinima) {
                if ($cantMtd === 0) {
                    unset($productosResultado[$idx]);
                    continue;
                }
            }

            $p['total_bs'] = round($p['total_bs'], 2);
            $p['total_bs_3m'] = round($p['total_bs_3m'], 2);
            $p['total_bs_mes_actual'] = round($p['total_bs_mes_actual'], 2);
            if (!empty($p['codigos'])) {
                $p['codigo'] = implode(', ', array_keys($p['codigos']));
            }
            $p['total_facturas'] = count($p['facturas']);
            $p['total_dias_compra'] = count($p['fechas']);
            $p['facturas'] = implode(', ', array_keys($p['facturas']));
            foreach ($p['por_mes'] as $ym => $val) {
                $p['por_mes'][$ym] = round($val, 2);
            }

            // Fallback precio si no se registró compra con monto y cantidad positiva
            if (($p['precio_ultima_compra'] ?? 0) <= 0) {
                $p['precio_ultima_compra'] = $p['total_cantidad_3m'] > 0
                    ? round($p['total_bs_3m'] / $p['total_cantidad_3m'], 2)
                    : ($p['total_cantidad'] > 0 ? round($p['total_bs'] / $p['total_cantidad'], 2) : 0.0);
            }

            $qTeorica = $cant3m / (float) max(1, $cantMesesEval);
            if ($kpiClave === 'cob_core') {
                // Filtro de Robustez Cobertura Core:
                // Frecuencia temporal: >= $frecuenciaMinima meses distintos de la ventana evaluada
                // Volumen acumulado: >= $cantidadMinima unidades físicas en la ventana evaluada
                $pUlt = (float) ($p['precio_ultima_compra'] ?? 0.0);
                $esHabitual = ($mesesDistintos3m >= $frecuenciaMinima && $cant3m >= $cantidadMinima && $pUlt > 0.0);
                if ($esHabitual) {
                    $indicesHabituales[] = $idx;
                    $p['total_bs_3m'] = round($cant3m * $pUlt, 2);
                }
                $cantSugerida = 0;
            } else {
                $cumpleCantidad = ($cant3m >= $cantidadMinima);
                $cumpleFrecuencia = ($frecuenciaCompra >= $frecuenciaMinima);
                $cantSugerida = ($cumpleFrecuencia && $cumpleCantidad) ? (int) ceil($qTeorica) : 0;
                $esHabitual = ($cantSugerida > 0);
            }

            $montoSugerido = round($cantSugerida * $p['precio_ultima_compra'], 2);

            $p['es_habitual'] = $esHabitual;
            $p['q_teorica'] = round($qTeorica, 2);
            $p['es_unitario_3m'] = ($cant3m === 1);
            $p['cant_sugerida'] = $cantSugerida;
            $p['monto_sugerido'] = $montoSugerido;
            $p['mix_propio'] = 0.0;
            $p['cumple_sugerido'] = ($cantSugerida > 0 && $cantMtd >= $cantSugerida);
            $p['compro_mes_actual'] = ($cantMtd > 0);
            $p['es_core_pareto'] = false;
        }
        unset($p);
        $productosResultado = array_values($productosResultado);

        // MOTOR DE OPTIMIZACIÓN: COMPRA SUGERIDA NIVELADA PARA COBERTURA CORE
        if ($kpiClave === 'cob_core') {
            $cuotaCliente = ($cuotaObjetivo !== null && $cuotaObjetivo > 0)
                ? max(150.0, (float) $cuotaObjetivo)
                : 150.0;

            if (empty($indicesHabituales)) {
                // Sin historial consolidado: Pendiente de desarrollo
                $estadoDesarrollo = 'pendiente_desarrollo';
                $totalSugeridoBs = 0.0;
                $totalSugeridoUnidades = 0;
                $excesoBs = 0.0;
                $pctExceso = 0.0;
                $brechaFinal = $cuotaCliente;
            } else {
                $estadoDesarrollo = 'consolidado';

                // PASO 1: Participación histórica dentro de la tienda (Mix Propio)
                $ventaTotalHabitual = 0.0;
                foreach ($indicesHabituales as $idx) {
                    $ventaTotalHabitual += (float) $productosResultado[$idx]['total_bs_3m'];
                }

                // PASO 2 y 3: Asignación de base monetaria y cálculo de unidades base
                $subtotalActual = 0.0;
                $totalUnidadesBase = 0;

                foreach ($indicesHabituales as $idx) {
                    $vj = (float) $productosResultado[$idx]['total_bs_3m'];
                    $mix_j = $ventaTotalHabitual > 0 ? ($vj / $ventaTotalHabitual) : (1.0 / count($indicesHabituales));
                    $presupuestoTeorico_j = $cuotaCliente * $mix_j;
                    $pj = (float) $productosResultado[$idx]['precio_ultima_compra'];
                    if ($pj <= 0.0) {
                        $pj = 1.0;
                    }

                    // Empaque comercial M_j = 1 unidad
                    $cantidadBase_j = max(1, (int) floor($presupuestoTeorico_j / $pj));
                    $montoBase_j = round($cantidadBase_j * $pj, 2);

                    $productosResultado[$idx]['mix_propio'] = round($mix_j * 100, 1);
                    $productosResultado[$idx]['cant_sugerida'] = $cantidadBase_j;
                    $productosResultado[$idx]['monto_sugerido'] = $montoBase_j;

                    $subtotalActual = round($subtotalActual + $montoBase_j, 2);
                    $totalUnidadesBase += $cantidadBase_j;
                }

                // PASO 4: Evaluación de la brecha residual
                $brecha = round($cuotaCliente - $subtotalActual, 2);

                // PASO 5: Nivelación por Criterio Comercial (Sumar unidades al SKU #1)
                if ($brecha > 0) {
                    // Ordenar índices habituales por volumen en Bs (rotación) descendente para identificar SKU #1
                    usort($indicesHabituales, function ($a, $b) use ($productosResultado) {
                        return ($productosResultado[$b]['total_bs_3m'] ?? 0.0) <=> ($productosResultado[$a]['total_bs_3m'] ?? 0.0);
                    });

                    $topIdx = $indicesHabituales[0];
                    $p1 = (float) $productosResultado[$topIdx]['precio_ultima_compra'];
                    if ($p1 <= 0.0) {
                        $p1 = 1.0;
                    }

                    $unidadesExtra = (int) ceil($brecha / $p1);
                    $productosResultado[$topIdx]['cant_sugerida'] += $unidadesExtra;
                    $productosResultado[$topIdx]['monto_sugerido'] = round($productosResultado[$topIdx]['cant_sugerida'] * $p1, 2);

                    $subtotalActual = round($subtotalActual + ($unidadesExtra * $p1), 2);
                    $totalUnidadesBase += $unidadesExtra;
                }

                $totalSugeridoBs = $subtotalActual;
                $totalSugeridoUnidades = $totalUnidadesBase;
                $excesoBs = round(max(0.0, $totalSugeridoBs - $cuotaCliente), 2);
                $pctExceso = $cuotaCliente > 0 ? round(($excesoBs / $cuotaCliente) * 100, 1) : 0.0;
                $brechaFinal = round(max(0.0, $cuotaCliente - $totalSugeridoBs), 2);

                // Actualizar estado de cumplimiento en mes actual para cada producto con match y tope
                foreach ($productosResultado as &$p) {
                    $cantMtd = (int) ($p['total_cantidad_mes_actual'] ?? 0);
                    $montoMtd = (float) ($p['total_bs_mes_actual'] ?? 0.0);
                    $cantSug = (int) ($p['cant_sugerida'] ?? 0);
                    $montoSug = (float) ($p['monto_sugerido'] ?? 0.0);

                    $p['cumple_sugerido'] = ($cantSug > 0 && $cantMtd >= $cantSug);
                    if ($cantSug > 0) {
                        $totalSkus3m++;
                        if ($p['compro_mes_actual']) {
                            $totalSkusCubiertosMtd++;
                        }
                        $p['monto_match_mtd'] = round(min($montoMtd, $montoSug), 2);
                        $p['cant_match_mtd'] = min($cantMtd, $cantSug);
                    } else {
                        $p['monto_match_mtd'] = 0.0;
                        $p['cant_match_mtd'] = 0;
                    }
                }
                unset($p);
            }
        } else {
            $cuotaCliente = $cuotaObjetivo ?? 0.0;
            $estadoDesarrollo = 'consolidado';
            $excesoBs = 0.0;
            $pctExceso = 0.0;
            $brechaFinal = 0.0;

            foreach ($productosResultado as &$p) {
                $p['monto_match_mtd'] = (float) ($p['total_bs_mes_actual'] ?? 0.0);
                $p['cant_match_mtd'] = (int) ($p['total_cantidad_mes_actual'] ?? 0);
                if ($p['cant_sugerida'] > 0) {
                    $totalSkus3m++;
                    $totalSugeridoUnidades += $p['cant_sugerida'];
                    $totalSugeridoBs += $p['monto_sugerido'];
                    if ($p['compro_mes_actual']) {
                        $totalSkusCubiertosMtd++;
                    }
                }
            }
            unset($p);
        }

        if (false) {
            $filasCarteraRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
                ->where(function ($q): void {
                    $q->whereNull('estado')
                      ->orWhereIn(DB::raw("UPPER(TRIM(estado))"), ['ACTIVO', 'ACTIVA', 'VIGENTE']);
                })
                ->select([
                    'cliente_id',
                    DB::raw("COALESCE(MAX(NULLIF(TRIM(dia_norm), '')), MAX(NULLIF(TRIM(dia), '')), 'Sin Día') as dia_visita"),
                ])
                ->groupBy('cliente_id')
                ->get();

            $cidsDia = [];
            $cidsRuta = [];
            $diaNormTarget = ucfirst(strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'], (string) $diaVisita)));

            foreach ($filasCarteraRuta as $fc) {
                $cid = (int) $fc->cliente_id;
                $cidsRuta[] = $cid;
                $fcDiaNorm = ucfirst(strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'], (string) $fc->dia_visita)));
                if ($diaNormTarget !== '' && !in_array(strtolower($diaNormTarget), ['sin dia', 'historico', 'sin día', 'histórico'])) {
                    if ($fcDiaNorm === $diaNormTarget) {
                        $cidsDia[] = $cid;
                    }
                }
            }

            $cidsPareto = !empty($cidsDia) ? $cidsDia : $cidsRuta;

            $paretoAgrupado = [];
            if (!empty($cidsPareto)) {
                $paretoRows = DB::connection('supervisor')->table('fact_ventas')
                    ->whereIn('cliente_id', $cidsPareto)
                    ->where('fecha_norm', '>=', $histInicio)
                    ->where('fecha_norm', '<=', $histFin3m)
                    ->where('monto_final', '!=', 0)
                    ->where(function ($q): void {
                        $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                    })
                    ->select([
                        DB::raw("TRIM(codigo) as codigo"),
                        DB::raw("COALESCE(producto, 'Sin descripción') as producto"),
                        DB::raw("COALESCE(categoria, '') as categoria"),
                        DB::raw("COALESCE(jerarquia3, '') as jerarquia3"),
                        DB::raw("COALESCE(monto_final, 0.0) as monto_final"),
                        DB::raw("COALESCE(cantidad, 0) as cantidad"),
                        DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                        DB::raw("COALESCE(nro_factura, '') as nro_factura"),
                        'cliente_id',
                    ])
                    ->orderBy('fecha_norm', 'desc')
                    ->get();

                if ($paretoRows->isEmpty()) {
                    $allCidsRuta = DB::connection('supervisor')->table('pan_ruteo')
                        ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                        ->where('cliente_id', '>', 0)
                        ->pluck('cliente_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    if (!empty($allCidsRuta)) {
                        $paretoRows = DB::connection('supervisor')->table('fact_ventas')
                            ->whereIn('cliente_id', $allCidsRuta)
                            ->where('fecha_norm', '>=', $histInicio)
                            ->where('fecha_norm', '<=', $histFin3m)
                            ->where('monto_final', '!=', 0)
                            ->where(function ($q): void {
                                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                            })
                            ->select([
                                DB::raw("TRIM(codigo) as codigo"),
                                DB::raw("COALESCE(producto, 'Sin descripción') as producto"),
                                DB::raw("COALESCE(categoria, '') as categoria"),
                                DB::raw("COALESCE(jerarquia3, '') as jerarquia3"),
                                DB::raw("COALESCE(monto_final, 0.0) as monto_final"),
                                DB::raw("COALESCE(cantidad, 0) as cantidad"),
                                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                                DB::raw("COALESCE(nro_factura, '') as nro_factura"),
                                'cliente_id',
                            ])
                            ->orderBy('fecha_norm', 'desc')
                            ->get();
                    }
                }

                foreach ($paretoRows as $pRow) {
                    $pNom = trim((string) $pRow->producto);
                    $cod = trim((string) $pRow->codigo);
                    $j3 = trim((string) $pRow->jerarquia3);
                    $cat = trim((string) $pRow->categoria);

                    if (!$this->cumplimientoAction->aplicaTransaccion($kpiClave, $pNom, $j3, $cod, $cat)) {
                        continue;
                    }

                    $pk = $pNom !== '' ? mb_strtoupper($pNom) : $cod;
                    if (!isset($paretoAgrupado[$pk])) {
                        $paretoAgrupado[$pk] = [
                            'codigo' => trim((string) $pRow->codigo),
                            'producto' => $pNom ?: trim((string) $pRow->codigo),
                            'categoria' => trim((string) $pRow->categoria),
                            'jerarquia3' => trim((string) $pRow->jerarquia3),
                            'total_monto' => 0.0,
                            'total_cantidad' => 0,
                            'precio_ref' => 0.0,
                            'fechas' => [],
                            'facturas' => [],
                            'clientes' => [],
                        ];
                    }
                    $m = (float) $pRow->monto_final;
                    $c = (int) $pRow->cantidad;
                    $paretoAgrupado[$pk]['total_monto'] += $m;
                    $paretoAgrupado[$pk]['total_cantidad'] += $c;
                    $f = (string) $pRow->fecha;
                    if ($f) {
                        $paretoAgrupado[$pk]['fechas'][$f] = true;
                    }
                    $fact = (string) ($pRow->nro_factura ?? '');
                    if ($fact !== '' && $fact !== 'S/F') {
                        $paretoAgrupado[$pk]['facturas'][$fact] = true;
                    }
                    $cId = (int) ($pRow->cliente_id ?? 0);
                    if ($cId > 0) {
                        $paretoAgrupado[$pk]['clientes'][$cId] = true;
                    }
                    if ($c > 0 && $m > 0 && $paretoAgrupado[$pk]['precio_ref'] === 0.0) {
                        $paretoAgrupado[$pk]['precio_ref'] = round($m / $c, 2);
                    }
                }

                foreach ($paretoAgrupado as $pk => &$pItem) {
                    if ($pItem['precio_ref'] <= 0 && $pItem['total_cantidad'] > 0 && $pItem['total_monto'] > 0) {
                        $pItem['precio_ref'] = round($pItem['total_monto'] / $pItem['total_cantidad'], 2);
                    }
                    $pItem['frecuencia'] = max(count($pItem['fechas'] ?? []), count($pItem['facturas'] ?? []));
                    $pItem['clientes_count'] = count($pItem['clientes'] ?? []);
                }
                unset($pItem);

                uasort($paretoAgrupado, function ($a, $b) {
                    if ($a['total_cantidad'] !== $b['total_cantidad']) {
                        return $b['total_cantidad'] <=> $a['total_cantidad'];
                    }
                    if ($a['frecuencia'] !== $b['frecuencia']) {
                        return $b['frecuencia'] <=> $a['frecuencia'];
                    }
                    return ($b['clientes_count'] ?? 0) <=> ($a['clientes_count'] ?? 0);
                });

                // Indexar productos existentes del cliente por nombre normalizado
                $prodKeyToIndex = [];
                foreach ($productosResultado as $idx => $item) {
                    $pNom = trim((string) $item['producto']);
                    $pk = $pNom !== '' ? mb_strtoupper($pNom) : trim((string) $item['codigo']);
                    $prodKeyToIndex[$pk] = $idx;
                }

                // Fase 1: Añadir 1 unidad de productos Pareto faltantes con selección de mínimo exceso al cruzar objetivo
                while ($totalSugeridoBs < $cuotaObjetivo) {
                    $gap = round($cuotaObjetivo - $totalSugeridoBs, 2);

                    // Buscar productos disponibles de paretoAgrupado que aún no tienen sugerido
                    $disponibles = [];
                    $rank = 0;
                    foreach ($paretoAgrupado as $pk => $pInfo) {
                        $idx = $prodKeyToIndex[$pk] ?? null;
                        $cantSugActual = ($idx !== null) ? ($productosResultado[$idx]['cant_sugerida'] ?? 0) : 0;
                        if ($cantSugActual === 0) {
                            $pUnit = 0.0;
                            if ($idx !== null && ($productosResultado[$idx]['precio_ultima_compra'] ?? 0.0) > 0) {
                                $pUnit = (float) $productosResultado[$idx]['precio_ultima_compra'];
                            } elseif (($pInfo['precio_ref'] ?? 0.0) > 0) {
                                $pUnit = (float) $pInfo['precio_ref'];
                            }
                            if ($pUnit > 0) {
                                $disponibles[] = [
                                    'pk' => $pk,
                                    'info' => $pInfo,
                                    'precio' => $pUnit,
                                    'idx' => $idx,
                                    'rank' => $rank,
                                ];
                            }
                        }
                        $rank++;
                    }

                    if (empty($disponibles)) {
                        break;
                    }

                    $topDisponible = $disponibles[0];
                    if ($topDisponible['precio'] <= $gap) {
                        // El producto de mayor ranking Pareto cabe dentro del gap
                        $elegido = $topDisponible;
                    } else {
                        // El producto top superaría el objetivo. Para evitar excesos desmedidos y que el exceso
                        // sea provocado únicamente por un solo producto, buscamos entre los disponibles con precio >= gap
                        // aquel con el menor exceso posible.
                        $candidatos = [];
                        foreach ($disponibles as $d) {
                            if ($d['precio'] >= $gap) {
                                $candidatos[] = [
                                    'pk' => $d['pk'],
                                    'info' => $d['info'],
                                    'precio' => $d['precio'],
                                    'idx' => $d['idx'],
                                    'exceso' => round($d['precio'] - $gap, 2),
                                    'rank' => $d['rank'],
                                ];
                            }
                        }

                        if (!empty($candidatos)) {
                            usort($candidatos, function ($a, $b) {
                                if ($a['exceso'] !== $b['exceso']) {
                                    return $a['exceso'] <=> $b['exceso'];
                                }
                                return $a['rank'] <=> $b['rank'];
                            });
                            $elegido = $candidatos[0];
                        } else {
                            $elegido = $topDisponible;
                        }
                    }

                    $pk = $elegido['pk'];
                    $pInfo = $elegido['info'];
                    $pUnit = $elegido['precio'];
                    $idx = $elegido['idx'];

                    if ($idx !== null) {
                        $productosResultado[$idx]['cant_sugerida'] = 1;
                        $productosResultado[$idx]['monto_sugerido'] = $pUnit;
                        $productosResultado[$idx]['cumple_sugerido'] = ($productosResultado[$idx]['total_cantidad_mes_actual'] >= 1);
                        $productosResultado[$idx]['es_core_pareto'] = true;

                        $totalSugeridoBs = round($totalSugeridoBs + $pUnit, 2);
                        $totalSugeridoUnidades += 1;
                        $totalSkus3m += 1;
                        if ($productosResultado[$idx]['compro_mes_actual']) {
                            $totalSkusCubiertosMtd += 1;
                        }
                    } else {
                        $nuevoProd = [
                            'codigo' => $pInfo['codigo'] ?: 'S/C',
                            'codigos' => [$pInfo['codigo'] => true],
                            'producto' => $pInfo['producto'],
                            'categoria' => $pInfo['categoria'],
                            'jerarquia3' => $pInfo['jerarquia3'],
                            'total_bs' => 0.0,
                            'total_cantidad' => 0,
                            'total_bs_3m' => 0.0,
                            'total_cantidad_3m' => 0,
                            'total_bs_mes_actual' => 0.0,
                            'total_cantidad_mes_actual' => 0,
                            'ultima_fecha' => null,
                            'fecha_precio_ultima_compra' => null,
                            'precio_ultima_compra' => $pUnit,
                            'facturas' => '',
                            'total_facturas' => 0,
                            'total_dias_compra' => 0,
                            'fechas_3m' => [],
                            'facturas_3m' => [],
                            'compras_3m' => [],
                            'compras_mes_actual' => [],
                            'por_mes' => $porMesTemplate,
                            'frecuencia_compra' => 0,
                            'es_unitario_3m' => false,
                            'cant_sugerida' => 1,
                            'monto_sugerido' => $pUnit,
                            'cumple_sugerido' => false,
                            'compro_mes_actual' => false,
                            'es_core_pareto' => true,
                        ];
                        $productosResultado[] = $nuevoProd;
                        $newIdx = count($productosResultado) - 1;
                        $prodKeyToIndex[$pk] = $newIdx;

                        $totalSugeridoBs = round($totalSugeridoBs + $pUnit, 2);
                        $totalSugeridoUnidades += 1;
                        $totalSkus3m += 1;
                    }
                }

                // Fase 2: Si aún no alcanza el objetivo (catálogo Pareto agotado en Fase 1), incrementar unidades con mínimo exceso
                if ($totalSugeridoBs < $cuotaObjetivo && !empty($paretoAgrupado)) {
                    $maxIter = 500;
                    $iter = 0;
                    while ($totalSugeridoBs < $cuotaObjetivo && $iter < $maxIter) {
                        $gap = round($cuotaObjetivo - $totalSugeridoBs, 2);

                        $candidatosFase2 = [];
                        $rank = 0;
                        foreach ($paretoAgrupado as $pk => $pInfo) {
                            $idx = $prodKeyToIndex[$pk] ?? null;
                            $pUnit = 0.0;
                            if ($idx !== null && ($productosResultado[$idx]['precio_ultima_compra'] ?? 0.0) > 0) {
                                $pUnit = (float) $productosResultado[$idx]['precio_ultima_compra'];
                            } elseif (($pInfo['precio_ref'] ?? 0.0) > 0) {
                                $pUnit = (float) $pInfo['precio_ref'];
                            }
                            if ($pUnit > 0) {
                                $candidatosFase2[] = [
                                    'pk' => $pk,
                                    'info' => $pInfo,
                                    'precio' => $pUnit,
                                    'idx' => $idx,
                                    'rank' => $rank,
                                ];
                            }
                            $rank++;
                        }

                        if (empty($candidatosFase2)) {
                            break;
                        }

                        $topCand = $candidatosFase2[0];
                        if ($topCand['precio'] <= $gap) {
                            $elegido = $topCand;
                        } else {
                            $mayores = [];
                            foreach ($candidatosFase2 as $c) {
                                if ($c['precio'] >= $gap) {
                                    $mayores[] = [
                                        'pk' => $c['pk'],
                                        'info' => $c['info'],
                                        'precio' => $c['precio'],
                                        'idx' => $c['idx'],
                                        'exceso' => round($c['precio'] - $gap, 2),
                                        'rank' => $c['rank'],
                                    ];
                                }
                            }
                            if (!empty($mayores)) {
                                usort($mayores, function ($a, $b) {
                                    if ($a['exceso'] !== $b['exceso']) {
                                        return $a['exceso'] <=> $b['exceso'];
                                    }
                                    return $a['rank'] <=> $b['rank'];
                                });
                                $elegido = $mayores[0];
                            } else {
                                $elegido = $topCand;
                            }
                        }

                        $pk = $elegido['pk'];
                        $pUnit = $elegido['precio'];
                        $idx = $elegido['idx'];

                        if ($idx !== null) {
                            $productosResultado[$idx]['cant_sugerida'] += 1;
                            $productosResultado[$idx]['monto_sugerido'] = round($productosResultado[$idx]['cant_sugerida'] * $pUnit, 2);
                            $productosResultado[$idx]['cumple_sugerido'] = ($productosResultado[$idx]['total_cantidad_mes_actual'] >= $productosResultado[$idx]['cant_sugerida']);
                            $productosResultado[$idx]['es_core_pareto'] = true;

                            $totalSugeridoBs = round($totalSugeridoBs + $pUnit, 2);
                            $totalSugeridoUnidades += 1;
                        }

                        $iter++;
                    }
                }
            }
        }

        if ($cantidadMinima > 1 || $frecuenciaMinima > 1) {
            $totalMonto3m = array_sum(array_column($productosResultado, 'total_bs_3m'));
            $totalUnidades3m = array_sum(array_column($productosResultado, 'total_cantidad_3m'));
        }

        $habitoTotalBs = round($totalSugeridoBs, 2);
        $cuotaObjBs = ($kpiClave === 'cob_core') ? round($cuotaCliente, 2) : (($cuotaObjetivo !== null && $cuotaObjetivo > 0) ? round($cuotaObjetivo, 2) : 0.0);
        $brechaFinalBs = ($kpiClave === 'cob_core') ? round($brechaFinal, 2) : (($cuotaObjBs > 0) ? round(max(0.0, $cuotaObjBs - $habitoTotalBs), 2) : 0.0);
        $excesoFinalBs = ($kpiClave === 'cob_core') ? round($excesoBs, 2) : round(max(0.0, $habitoTotalBs - $cuotaObjBs), 2);
        $pctExcesoFinal = ($kpiClave === 'cob_core') ? $pctExceso : ($cuotaObjBs > 0 ? round(($excesoFinalBs / $cuotaObjBs) * 100, 1) : 0.0);

        $totalMontoMesActualMatch = array_sum(array_column($productosResultado, 'monto_match_mtd'));
        $totalUnidadesMesActualMatch = array_sum(array_column($productosResultado, 'cant_match_mtd'));

        return [
            'cliente_id' => $clienteId,
            'ruta' => $rutaClean,
            'kpi' => $kpiClave,
            'kpi_nombre' => self::KPIS_NOMBRES[$kpiClave] ?? $kpiClave,
            'fecha_corte' => $fechaCorte,
            'frecuencia_minima' => $frecuenciaMinima,
            'cantidad_minima' => $cantidadMinima,
            'fecha_inicio_3m' => $histInicio,
            'meses_historico' => $mesesHistorico,
            'meses_cerrados' => $mesesCerrados,
            'ym_actual' => $ymActual,
            'total_monto_bs' => round($totalMonto, 2),
            'total_unidades' => $totalUnidades,
            'total_monto_3m' => round($totalMonto3m, 2),
            'total_unidades_3m' => $totalUnidades3m,
            'total_monto_mes_actual' => ($kpiClave === 'cob_core') ? round($totalMontoMesActualMatch, 2) : round($totalMontoMesActual, 2),
            'total_unidades_mes_actual' => ($kpiClave === 'cob_core') ? $totalUnidadesMesActualMatch : $totalUnidadesMesActual,
            'total_monto_mes_actual_bruto' => round($totalMontoMesActual, 2),
            'total_unidades_mes_actual_bruto' => $totalUnidadesMesActual,
            'total_monto_mes_actual_match' => round($totalMontoMesActualMatch, 2),
            'total_unidades_mes_actual_match' => $totalUnidadesMesActualMatch,
            'total_productos_unicos' => count($prodMap),
            'total_fechas_unicas' => count($diasMap),
            'resumen_cobertura' => [
                'total_skus_3m' => $totalSkus3m,
                'total_skus_cubiertos_mtd' => $totalSkusCubiertosMtd,
                'total_unidades_sugeridas' => $totalSugeridoUnidades,
                'total_monto_sugerido_bs' => $habitoTotalBs,
                'habito_total_bs' => $habitoTotalBs,
                'cuota_objetivo_bs' => $cuotaObjBs,
                'brecha_bs' => $brechaFinalBs,
                'exceso_bs' => $excesoFinalBs,
                'pct_exceso' => $pctExcesoFinal,
                'estado_desarrollo' => $estadoDesarrollo,
                'pct_cobertura_skus' => $totalSkus3m > 0 ? round(($totalSkusCubiertosMtd / $totalSkus3m) * 100, 1) : 0.0,
                'total_monto_mes_actual' => ($kpiClave === 'cob_core') ? round($totalMontoMesActualMatch, 2) : round($totalMontoMesActual, 2),
                'total_monto_mes_actual_match' => round($totalMontoMesActualMatch, 2),
            ],
            'fechas' => $fechasResultado,
            'productos' => $productosResultado,
        ];
    }
}

