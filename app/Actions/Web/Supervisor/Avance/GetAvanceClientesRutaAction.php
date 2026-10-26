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
    public function execute(string $ruta, string $kpiClave = 'vol_general', ?string $fechaInicio = null, ?string $fechaCorte = null): array
    {
        $rutaClean = strtoupper(trim($ruta));

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

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

        // 2. Ventas registradas de la ruta en el rango
        $ventasRuta = DB::connection('supervisor')->table('fact_ventas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            })
            ->select([
                'cliente_id',
                DB::raw("COALESCE(cliente, 'CLIENTE S/N') as cliente_factura"),
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
                continue;
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

        // Armar resultado de clientes
        $totalVentaRuta = array_sum(array_column($cartera, 'monto'));
        $clientesLista = [];

        foreach ($cartera as $cid => $item) {
            $esCobertura = str_starts_with($kpiClave, 'cob_');
            $valLogro = $esCobertura ? $item['cantidad'] : round($item['monto'], 2);
            $tieneLogro = $esCobertura ? ($item['cantidad'] > 0) : ($item['monto'] > 0);

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
            ];
        }

        // Ordenar clientes: primero compradores de mayor a menor monto, luego no compradores alfabéticamente
        usort($clientesLista, function ($a, $b) {
            if ($a['tiene_logro'] !== $b['tiene_logro']) {
                return $b['tiene_logro'] ? 1 : -1;
            }
            if ($a['tiene_logro']) {
                return $b['monto_bs'] <=> $a['monto_bs'];
            }
            return strcmp((string) $a['cliente'], (string) $b['cliente']);
        });

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

        return [
            'ruta' => $rutaClean,
            'kpi' => $kpiClave,
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'clientes' => $clientesLista,
            'por_dia' => $diasOrdenados,
            'resumen' => [
                'total_cartera' => count($cartera),
                'compradores_unicos' => count(array_filter($clientesLista, fn ($c) => $c['tiene_logro'])),
                'venta_total_bs' => round($totalVentaRuta, 2),
                'dias_con_venta' => count($diasOrdenados),
            ],
        ];
    }
}

