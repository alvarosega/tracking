<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceParetoClienteHistorialAction
{
    private const CACHE_TTL_SECS = 300;

    /**
     * Devuelve el comportamiento mensual de los últimos 6 meses para un cliente específico.
     */
    public function execute(int $clienteId, ?string $fechaCorte = null): array
    {
        if (!$fechaCorte) {
            $fechaCorte = date('Y-m-d');
        }

        $cacheKey = "avance_pareto_historial_6m_{$clienteId}_{$fechaCorte}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($clienteId, $fechaCorte) {
            return $this->calcularHistorial6Meses($clienteId, $fechaCorte);
        });
    }

    private function calcularHistorial6Meses(int $clienteId, string $fechaCorte): array
    {
        $corteCarbon = Carbon::parse($fechaCorte);
        $mesActualYm = $corteCarbon->format('Y-m');

        // Construir la lista de los 6 meses calendario en orden cronológico
        $mesesCalendario = [];
        for ($i = 5; $i >= 0; $i--) {
            $mCarbon = $corteCarbon->copy()->subMonthsNoOverflow($i);
            $ym = $mCarbon->format('Y-m');
            $mesesCalendario[$ym] = [
                'ym' => $ym,
                'mes_nombre' => $this->getNombreMes((int) $mCarbon->format('n')),
                'anio' => $mCarbon->format('Y'),
                'label' => $this->getNombreMesAbrev((int) $mCarbon->format('n')) . ' ' . $mCarbon->format('Y'),
                'es_actual' => ($ym === $mesActualYm),
                'venta_bs' => 0.0,
                'facturas' => 0,
                'unidades' => 0,
                'cajas' => 0.0,
                'skus' => 0,
                'marcas' => 0,
                'drop_size' => 0.0,
                'var_mom_pct' => null,
                'tendencia' => 'flat',
            ];
        }

        $fInicio6m = $corteCarbon->copy()->subMonthsNoOverflow(5)->startOfMonth()->format('Y-m-d');
        $fFin6m = $fechaCorte;

        // 1. Consultar ventas mensuales del cliente en la ventana de 6 meses
        $rowsMensuales = DB::connection('supervisor')->table('fact_ventas')
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $fInicio6m)
            ->where('fecha_norm', '<=', $fFin6m)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
            })
            ->where('monto_final', '>', 0)
            ->groupByRaw("DATE_FORMAT(fecha_norm, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(fecha_norm, '%Y-%m') ASC")
            ->selectRaw("
                DATE_FORMAT(fecha_norm, '%Y-%m') AS mes_ym,
                ROUND(SUM(monto_final), 2) AS venta_bs,
                COUNT(DISTINCT venta_id) AS facturas,
                SUM(cantidad) AS unidades,
                ROUND(SUM(cantidad_cajas), 2) AS cajas,
                COUNT(DISTINCT producto_id) AS skus,
                COUNT(DISTINCT marca) AS marcas,
                ROUND(SUM(monto_final) / NULLIF(COUNT(DISTINCT venta_id), 0), 2) AS drop_size
            ")->get();

        foreach ($rowsMensuales as $row) {
            $ym = (string) $row->mes_ym;
            if (isset($mesesCalendario[$ym])) {
                $mesesCalendario[$ym]['venta_bs'] = (float) $row->venta_bs;
                $mesesCalendario[$ym]['facturas'] = (int) $row->facturas;
                $mesesCalendario[$ym]['unidades'] = (int) $row->unidades;
                $mesesCalendario[$ym]['cajas'] = (float) $row->cajas;
                $mesesCalendario[$ym]['skus'] = (int) $row->skus;
                $mesesCalendario[$ym]['marcas'] = (int) $row->marcas;
                $mesesCalendario[$ym]['drop_size'] = (float) $row->drop_size;
            }
        }

        // 2. Calcular variaciones MoM
        $prevVenta = null;
        foreach ($mesesCalendario as $ym => &$m) {
            if ($prevVenta !== null) {
                if ($prevVenta == 0.0 && $m['venta_bs'] > 0.0) {
                    $m['var_mom_pct'] = 100.0;
                    $m['tendencia'] = 'up';
                } elseif ($prevVenta > 0.0 && $m['venta_bs'] == 0.0) {
                    $m['var_mom_pct'] = -100.0;
                    $m['tendencia'] = 'down';
                } elseif ($prevVenta > 0.0 && $m['venta_bs'] > 0.0) {
                    $difPct = round((($m['venta_bs'] - $prevVenta) / $prevVenta) * 100.0, 1);
                    $m['var_mom_pct'] = $difPct;
                    $m['tendencia'] = $difPct >= 5.0 ? 'up' : ($difPct <= -5.0 ? 'down' : 'flat');
                } else {
                    $m['var_mom_pct'] = 0.0;
                    $m['tendencia'] = 'flat';
                }
            }
            $prevVenta = $m['venta_bs'];
        }
        unset($m);

        $mesesList = array_values($mesesCalendario);

        // 3. Totales y KPIs 6M
        $totalVenta = array_sum(array_column($mesesList, 'venta_bs'));
        $totalFacturas = array_sum(array_column($mesesList, 'facturas'));
        $totalUnidades = array_sum(array_column($mesesList, 'unidades'));
        $totalCajas = array_sum(array_column($mesesList, 'cajas'));
        $mesesConCompra = count(array_filter($mesesList, fn ($m) => $m['venta_bs'] > 0));

        $promedioMensualBs = round($totalVenta / 6.0, 2);
        $dropSizePromedio = $totalFacturas > 0 ? round($totalVenta / $totalFacturas, 2) : 0.0;

        // 4. Top 5 productos comprados en 6M
        $topProductos = DB::connection('supervisor')->table('fact_ventas')
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $fInicio6m)
            ->where('fecha_norm', '<=', $fFin6m)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
            })
            ->where('monto_final', '>', 0)
            ->groupBy('producto_id', 'producto')
            ->selectRaw("
                producto_id,
                TRIM(producto) AS producto,
                COALESCE(NULLIF(TRIM(MAX(categoria)), ''), 'General') AS categoria,
                COALESCE(NULLIF(TRIM(MAX(marca)), ''), '-') AS marca,
                SUM(cantidad) AS unidades,
                ROUND(SUM(cantidad_cajas), 2) AS cajas,
                ROUND(SUM(monto_final), 2) AS total_bs
            ")
            ->orderByDesc('total_bs')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'producto_id' => (int) $p->producto_id,
                'producto' => (string) $p->producto,
                'categoria' => (string) $p->categoria,
                'marca' => (string) $p->marca,
                'unidades' => (int) $p->unidades,
                'cajas' => (float) $p->cajas,
                'total_bs' => (float) $p->total_bs,
                'pct_del_total' => $totalVenta > 0 ? round(($p->total_bs / $totalVenta) * 100.0, 1) : 0.0,
            ])->toArray();

        return [
            'cliente_id' => $clienteId,
            'fecha_corte' => $fechaCorte,
            'kpis' => [
                'total_venta_6m' => round($totalVenta, 2),
                'promedio_mensual_bs' => $promedioMensualBs,
                'meses_con_compra' => $mesesConCompra,
                'total_facturas_6m' => $totalFacturas,
                'total_unidades_6m' => $totalUnidades,
                'total_cajas_6m' => round($totalCajas, 2),
                'drop_size_promedio_6m' => $dropSizePromedio,
            ],
            'meses' => $mesesList,
            'top_productos' => $topProductos,
        ];
    }

    private function getNombreMes(int $m): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return $meses[$m] ?? '';
    }

    private function getNombreMesAbrev(int $m): string
    {
        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];
        return $meses[$m] ?? '';
    }
}
