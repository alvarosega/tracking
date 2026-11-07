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
        string $kpiClave = 'vol_general'
    ): array {
        $rutaClean = strtoupper(trim($ruta));

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        $mesesCerrados = $this->cumplimientoAction->obtenerMesesCerrados($carbonCorte);

        // Rango de evaluación: desde el inicio del 1° mes cerrado hasta la fecha de corte
        $histInicio = $mesesCerrados[0]['inicio'];
        $histFin = $fechaCorte;
        $ymActual = $carbonCorte->format('Y-m');
        $yms3m = array_column($mesesCerrados, 'ym');

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

            // 2. Agrupación por Producto (SKU)
            $prodKey = $cod ?: $prod;
            if (!isset($prodMap[$prodKey])) {
                $prodMap[$prodKey] = [
                    'codigo' => $cod ?: 'S/C',
                    'producto' => $prod,
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
                    'compras_3m' => [],
                    'compras_mes_actual' => [],
                    'por_mes' => [
                        $mesesCerrados[0]['ym'] => 0.0,
                        $mesesCerrados[1]['ym'] => 0.0,
                        $mesesCerrados[2]['ym'] => 0.0,
                        $ymActual => 0.0,
                    ],
                ];
            }

            // Registrar el precio unitario de la última compra registrada
            if ($cant > 0 && $monto > 0) {
                if ($prodMap[$prodKey]['fecha_precio_ultima_compra'] === null || $f >= $prodMap[$prodKey]['fecha_precio_ultima_compra']) {
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

        foreach ($productosResultado as &$p) {
            $p['total_bs'] = round($p['total_bs'], 2);
            $p['total_bs_3m'] = round($p['total_bs_3m'], 2);
            $p['total_bs_mes_actual'] = round($p['total_bs_mes_actual'], 2);
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

            // Hábito sugerido mensual: Total Cantidad 3M / 3 redondeado al entero superior
            $cant3m = (int) ($p['total_cantidad_3m'] ?? 0);
            $cantSugerida = $cant3m > 0 ? (int) ceil($cant3m / 3.0) : 0;
            $montoSugerido = round($cantSugerida * $p['precio_ultima_compra'], 2);

            $cantMtd = (int) ($p['total_cantidad_mes_actual'] ?? 0);
            $p['cant_sugerida'] = $cantSugerida;
            $p['monto_sugerido'] = $montoSugerido;
            $p['cumple_sugerido'] = ($cantSugerida > 0 && $cantMtd >= $cantSugerida);
            $p['compro_mes_actual'] = ($cantMtd > 0);

            if ($cantSugerida > 0) {
                $totalSkus3m++;
                $totalSugeridoUnidades += $cantSugerida;
                $totalSugeridoBs += $montoSugerido;
                if ($p['compro_mes_actual']) {
                    $totalSkusCubiertosMtd++;
                }
            }
        }
        unset($p);

        return [
            'cliente_id' => $clienteId,
            'ruta' => $rutaClean,
            'kpi' => $kpiClave,
            'kpi_nombre' => self::KPIS_NOMBRES[$kpiClave] ?? $kpiClave,
            'fecha_corte' => $fechaCorte,
            'fecha_inicio_3m' => $histInicio,
            'meses_cerrados' => $mesesCerrados,
            'ym_actual' => $ymActual,
            'total_monto_bs' => round($totalMonto, 2),
            'total_unidades' => $totalUnidades,
            'total_monto_3m' => round($totalMonto3m, 2),
            'total_unidades_3m' => $totalUnidades3m,
            'total_monto_mes_actual' => round($totalMontoMesActual, 2),
            'total_unidades_mes_actual' => $totalUnidadesMesActual,
            'total_productos_unicos' => count($prodMap),
            'total_fechas_unicas' => count($diasMap),
            'resumen_cobertura' => [
                'total_skus_3m' => $totalSkus3m,
                'total_skus_cubiertos_mtd' => $totalSkusCubiertosMtd,
                'total_unidades_sugeridas' => $totalSugeridoUnidades,
                'total_monto_sugerido_bs' => round($totalSugeridoBs, 2),
                'pct_cobertura_skus' => $totalSkus3m > 0 ? round(($totalSkusCubiertosMtd / $totalSkus3m) * 100, 1) : 0.0,
            ],
            'fechas' => $fechasResultado,
            'productos' => $productosResultado,
        ];
    }
}

