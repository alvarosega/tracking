<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceParetoDataAction
{
    private const CACHE_TTL_SECS = 300;

    /**
     * Ejecuta el análisis Pareto y scoring multicriterio de clientes.
     */
    public function execute(?string $fechaInicio = null, ?string $fechaCorte = null): array
    {
        // 1. Resolver fechas por defecto si no vienen provistas
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')
                ->where('monto_final', '>', 0)
                ->max('fecha_norm');
            $fechaCorte = $maxFecha ? (string) $maxFecha : date('Y-m-d');
        }

        $corteCarbon = Carbon::parse($fechaCorte);
        $mesYm = $corteCarbon->format('Y-m');

        if (!$fechaInicio) {
            $fechaInicio = $corteCarbon->copy()->startOfMonth()->format('Y-m-d');
        }

        $cacheKey = "avance_pareto_cartera_{$fechaInicio}_{$fechaCorte}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($fechaInicio, $fechaCorte, $corteCarbon, $mesYm) {
            return $this->procesarAnalisisPareto($fechaInicio, $fechaCorte, $corteCarbon, $mesYm);
        });
    }

    private function procesarAnalisisPareto(string $fechaInicio, string $fechaCorte, Carbon $corteCarbon, string $mesYm): array
    {
        // 2. Ventana actual
        $fActInicio = $fechaInicio;
        $fActFin = $fechaCorte;

        // 3. Referencias históricas por meses calendario
        $lmCarbon = $corteCarbon->copy()->subMonthNoOverflow();
        $fLmInicio = $lmCarbon->copy()->startOfMonth()->format('Y-m-d');
        $fLmFin = $lmCarbon->copy()->endOfMonth()->format('Y-m-d');

        $m2Carbon = $corteCarbon->copy()->subMonthsNoOverflow(2);
        $fM2Inicio = $m2Carbon->copy()->startOfMonth()->format('Y-m-d');
        $fM2Fin = $m2Carbon->copy()->endOfMonth()->format('Y-m-d');

        $m3Carbon = $corteCarbon->copy()->subMonthsNoOverflow(3);
        $fM3Inicio = $m3Carbon->copy()->startOfMonth()->format('Y-m-d');
        $fM3Fin = $m3Carbon->copy()->endOfMonth()->format('Y-m-d');

        $lyCarbon = $corteCarbon->copy()->subYear();
        $fLyInicio = $lyCarbon->copy()->startOfMonth()->format('Y-m-d');
        $fLyFin = $lyCarbon->copy()->endOfMonth()->format('Y-m-d');

        // Ventanas de Consistencia Histórica (6M y 12M hasta fecha de corte)
        $c6mCarbon = $corteCarbon->copy()->subMonthsNoOverflow(5);
        $f6mInicio = $c6mCarbon->copy()->startOfMonth()->format('Y-m-d');

        $c12mCarbon = $corteCarbon->copy()->subMonthsNoOverflow(11);
        $f12mInicio = $c12mCarbon->copy()->startOfMonth()->format('Y-m-d');

        $fechaMinimaGlobal = min($fActInicio, $fLmInicio, $fM2Inicio, $fM3Inicio, $fLyInicio, $f6mInicio, $f12mInicio);
        $fechaMaximaGlobal = max($fActFin, $fLmFin, $fM2Fin, $fM3Fin, $fLyFin);

        // 4. Clientes con fotos
        $clientesConFotos = [];
        try {
            $clientesConFotos = DB::connection('mysql')->table('visitas')
                ->whereNotNull('photo_path')
                ->whereRaw("TRIM(photo_path) != ''")
                ->distinct()
                ->pluck('client_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->toArray();
        } catch (\Throwable $e) {
            // Silencioso si no está disponible la tabla en la BD local
        }

        // 5. Maestro pan_ruteo
        $panRows = DB::connection('supervisor')->table('pan_ruteo')
            ->where('cliente_id', '>', 0)
            ->groupBy('cliente_id')
            ->selectRaw("
                cliente_id,
                COALESCE(MAX(NULLIF(TRIM(UPPER(ruta)), '')), 'SIN RUTA') AS ruta_oficial,
                COALESCE(MAX(NULLIF(TRIM(cliente), '')), CONCAT('Cliente ', cliente_id)) AS cliente,
                COALESCE(GROUP_CONCAT(DISTINCT COALESCE(dia_norm, dia) SEPARATOR ', '), 'Sin Día') AS dias_visita,
                COALESCE(MAX(NULLIF(TRIM(direccion), '')), 'Sin Dirección') AS direccion,
                COALESCE(MAX(NULLIF(TRIM(referencia), '')), '') AS referencia,
                COALESCE(MAX(latitud), 0.0) AS latitud,
                COALESCE(MAX(longitud), 0.0) AS longitud,
                COALESCE(UPPER(TRIM(MAX(estado))), 'ACTIVO') AS estado_ruteo
            ")->get();

        $panMap = [];
        foreach ($panRows as $r) {
            $panMap[(int) $r->cliente_id] = (array) $r;
        }

        // 6. Dimensión Rutas
        $dimRutasRows = DB::connection('supervisor')->table('dim_rutas')
            ->selectRaw("
                TRIM(UPPER(ruta)) AS ruta_norm,
                COALESCE(TRIM(canal), 'SIN CANAL') AS canal,
                COALESCE(TRIM(vendedor), 'No Asignado') AS vendedor
            ")->get();

        $dimRutasMap = [];
        foreach ($dimRutasRows as $r) {
            $dimRutasMap[(string) $r->ruta_norm] = (array) $r;
        }

        // 7. Transacciones agrupadas por cliente_id en fact_ventas
        $ventasRows = DB::connection('supervisor')->table('fact_ventas as fv')
            ->where(function ($q): void {
                $q->whereNull('fv.revertida')->orWhereRaw("LOWER(TRIM(fv.revertida)) != 'si'");
            })
            ->where('fv.monto_final', '>', 0)
            ->where('fv.fecha_norm', '>=', $fechaMinimaGlobal)
            ->where('fv.fecha_norm', '<=', $fechaMaximaGlobal)
            ->groupBy('fv.cliente_id')
            ->selectRaw("
                fv.cliente_id,
                COALESCE(MAX(NULLIF(TRIM(fv.cliente), '')), '') AS cliente_fv,
                COALESCE(MAX(NULLIF(TRIM(fv.canal), '')), 'SIN CANAL') AS canal_fv,
                COALESCE(MAX(NULLIF(TRIM(UPPER(fv.ruta)), '')), 'SIN RUTA') AS ruta_fv,
                COALESCE(MAX(NULLIF(TRIM(fv.vendedor), '')), 'No Asignado') AS vendedor_fv,
                COALESCE(MAX(NULLIF(TRIM(fv.tipo_negocio), '')), 'SIN TIPO') AS tipo_negocio_fv,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.monto_final ELSE 0 END) AS venta_actual,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.cantidad ELSE 0 END) AS unidades_actual,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN COALESCE(fv.cantidad_cajas, 0) ELSE 0 END) AS cajas_actual,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.venta_id ELSE NULL END) AS facturas_efectivas,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.fecha_norm ELSE NULL END) AS frecuencia_dias,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.producto_id ELSE NULL END) AS skus_distintos,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? AND fv.jerarquia3 IS NOT NULL AND TRIM(fv.jerarquia3) != '' THEN TRIM(fv.jerarquia3) ELSE NULL END) AS marcas_distintas,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? AND fv.jerarquia1 IS NOT NULL AND TRIM(fv.jerarquia1) != '' THEN TRIM(fv.jerarquia1) ELSE NULL END) AS j1_distintos,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? AND fv.jerarquia2 IS NOT NULL AND TRIM(fv.jerarquia2) != '' THEN TRIM(fv.jerarquia2) ELSE NULL END) AS j2_distintos,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.monto_final ELSE 0 END) AS venta_lm,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.monto_final ELSE 0 END) AS venta_m2,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.monto_final ELSE 0 END) AS venta_m3,
                SUM(CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN fv.monto_final ELSE 0 END) AS venta_ly,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN DATE_FORMAT(fv.fecha_norm, '%Y-%m') ELSE NULL END) AS meses_activos_6m,
                COUNT(DISTINCT CASE WHEN fv.fecha_norm >= ? AND fv.fecha_norm <= ? THEN DATE_FORMAT(fv.fecha_norm, '%Y-%m') ELSE NULL END) AS meses_activos_12m
            ", [
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fActInicio, $fActFin,
                $fLmInicio, $fLmFin,
                $fM2Inicio, $fM2Fin,
                $fM3Inicio, $fM3Fin,
                $fLyInicio, $fLyFin,
                $f6mInicio, $fActFin,
                $f12mInicio, $fActFin,
            ])->get();

        $ventasMap = [];
        foreach ($ventasRows as $r) {
            $ventasMap[(int) $r->cliente_id] = (array) $r;
        }

        // 8. Universo combinado de clientes
        $todosCids = array_unique(array_merge(array_keys($panMap), array_keys($ventasMap)));
        $clientes = [];

        $canalesSet = [];
        $rutasSet = [];
        $tiposNegocioSet = [];

        foreach ($todosCids as $cid) {
            $p = $panMap[$cid] ?? null;
            $v = $ventasMap[$cid] ?? null;

            if ($p) {
                $ruta = strtoupper(trim((string) ($p['ruta_oficial'] ?? 'SIN RUTA')));
                $estado = strtoupper(trim((string) ($p['estado_ruteo'] ?? 'ACTIVO')));
                $nombre = (string) ($p['cliente'] ?? "Cliente {$cid}");
                $direccion = (string) ($p['direccion'] ?? 'Sin Dirección');
                $referencia = (string) ($p['referencia'] ?? '');
                $diasVisita = (string) ($p['dias_visita'] ?? 'Sin Día');
                $lat = (float) ($p['latitud'] ?? 0.0);
                $lon = (float) ($p['longitud'] ?? 0.0);
            } else {
                $ruta = strtoupper(trim((string) ($v['ruta_fv'] ?? 'SIN RUTA')));
                $estado = 'INACTIVO';
                $nombre = (string) ($v['cliente_fv'] ?: "Cliente {$cid}");
                $direccion = 'Sin Dirección';
                $referencia = '';
                $diasVisita = 'Sin Día';
                $lat = 0.0;
                $lon = 0.0;
            }

            $dimInfo = $dimRutasMap[$ruta] ?? [
                'canal' => $v['canal_fv'] ?? 'SIN CANAL',
                'vendedor' => $v['vendedor_fv'] ?? 'No Asignado',
            ];

            $canal = (string) ($dimInfo['canal'] ?? 'SIN CANAL');
            $vendedor = (string) ($dimInfo['vendedor'] ?? 'No Asignado');
            $tipoNegocio = (string) ($v['tipo_negocio_fv'] ?? 'SIN TIPO');

            if ($canal !== '' && $canal !== 'SIN CANAL') {
                $canalesSet[$canal] = true;
            }
            if ($ruta !== '' && $ruta !== 'SIN RUTA') {
                $rutasSet[$ruta] = true;
            }
            if ($tipoNegocio !== '' && $tipoNegocio !== 'SIN TIPO') {
                $tiposNegocioSet[$tipoNegocio] = true;
            }

            $vAct = $v ? round((float) ($v['venta_actual'] ?? 0.0), 2) : 0.0;
            $vLm = $v ? round((float) ($v['venta_lm'] ?? 0.0), 2) : 0.0;
            $vM2 = $v ? round((float) ($v['venta_m2'] ?? 0.0), 2) : 0.0;
            $vM3 = $v ? round((float) ($v['venta_m3'] ?? 0.0), 2) : 0.0;
            $vLy = $v ? round((float) ($v['venta_ly'] ?? 0.0), 2) : 0.0;

            $frecDias = $v ? (int) ($v['frecuencia_dias'] ?? 0) : 0;
            $facturas = $v ? (int) ($v['facturas_efectivas'] ?? 0) : 0;
            $unidades = $v ? (int) ($v['unidades_actual'] ?? 0) : 0;
            $cajas = $v ? round((float) ($v['cajas_actual'] ?? 0.0), 2) : 0.0;
            $skus = $v ? (int) ($v['skus_distintos'] ?? 0) : 0;
            $marcas = $v ? (int) ($v['marcas_distintas'] ?? 0) : 0;
            $meses6m = $v ? (int) ($v['meses_activos_6m'] ?? 0) : 0;
            $meses12m = $v ? (int) ($v['meses_activos_12m'] ?? 0) : 0;

            $divisorDrop = $facturas > 0 ? $facturas : ($frecDias > 0 ? $frecDias : 1);
            $drop = $vAct > 0 ? round($vAct / $divisorDrop, 2) : 0.0;

            $promL2m = round(($vLm + $vM2) / 2.0, 2);
            $promL3m = round(($vLm + $vM2 + $vM3) / 3.0, 2);

            $clientes[] = [
                'cliente_id' => $cid,
                'cliente' => $nombre,
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'dia' => $diasVisita,
                'direccion' => $direccion,
                'referencia' => $referencia,
                'tipo_negocio' => $tipoNegocio,
                'latitud' => $lat,
                'longitud' => $lon,
                'estado_cliente' => $estado,
                'venta_mtd' => $vAct,
                'unidades' => $unidades,
                'cajas' => $cajas,
                'facturas_efectivas' => $facturas,
                'frecuencia_dias' => $frecDias,
                'drop_size' => $drop,
                'skus_distintos' => $skus,
                'marcas_distintas' => $marcas,
                'j1_distintos' => $v ? (int) ($v['j1_distintos'] ?? 0) : 0,
                'j2_distintos' => $v ? (int) ($v['j2_distintos'] ?? 0) : 0,
                'meses_activos_6m' => $meses6m,
                'meses_activos_12m' => $meses12m,
                'venta_lm' => $vLm,
                'venta_l2m_prom' => $promL2m,
                'venta_l3m_prom' => $promL3m,
                'venta_ly' => $vLy,
                'var_lm' => $this->calcDesvio($vAct, $vLm),
                'var_l2m' => $this->calcDesvio($vAct, $promL2m),
                'var_l3m' => $this->calcDesvio($vAct, $promL3m),
                'var_ly' => $this->calcDesvio($vAct, $vLy),
                'tiene_fotos' => isset($clientesConFotos[$cid]),
            ];
        }

        // 9. Ordenar clientes por venta_mtd DESC para calcular curva de Pareto
        usort($clientes, fn ($a, $b) => $b['venta_mtd'] <=> $a['venta_mtd']);

        $totalVolumen = array_sum(array_column($clientes, 'venta_mtd'));
        $acum = 0.0;
        $cntA = 0; $cntB = 0; $cntC = 0;
        $volA = 0.0; $volB = 0.0; $volC = 0.0;

        foreach ($clientes as &$c) {
            $v = $c['venta_mtd'];
            if ($v > 0 && $totalVolumen > 0) {
                $acum += $v;
                $pctAcum = round(($acum / $totalVolumen) * 100.0, 2);
                $c['pct_acumulado'] = $pctAcum;
                if ($pctAcum <= 80.001) {
                    $cl = 'A';
                    $cntA++;
                    $volA += $v;
                } elseif ($pctAcum <= 95.001) {
                    $cl = 'B';
                    $cntB++;
                    $volB += $v;
                } else {
                    $cl = 'C';
                    $cntC++;
                    $volC += $v;
                }
            } else {
                $c['pct_acumulado'] = 100.0;
                $cl = 'C';
                $cntC++;
                $volC += $v;
            }
            $c['clasificacion'] = $cl;
            $c['_clase_monto'] = $cl;

            // Micro-clasificaciones estándar
            $fact = $c['facturas_efectivas'] ?? 0;
            $c['_clase_frec'] = $fact >= 4 ? 'A' : ($fact >= 2 ? 'B' : 'C');

            $c6m = $c['meses_activos_6m'] ?? 0;
            $c['_clase_consist_6m'] = $c6m >= 5 ? 'A' : ($c6m >= 3 ? 'B' : 'C');

            $c12m = $c['meses_activos_12m'] ?? 0;
            $c['_clase_consist_12m'] = $c12m >= 9 ? 'A' : ($c12m >= 5 ? 'B' : 'C');

            $marcas = $c['marcas_distintas'] ?? 0;
            $c['_clase_marcas'] = $marcas >= 5 ? 'A' : ($marcas >= 2 ? 'B' : 'C');
        }
        unset($c);

        // 10. Clasificación percentilar relativa para SKUs, Drop Size, Unidades y Cajas
        $n = count($clientes);
        if ($n > 0) {
            // SKUs
            $idxSkus = array_keys($clientes);
            usort($idxSkus, fn ($a, $b) => ($clientes[$b]['skus_distintos'] ?? 0) <=> ($clientes[$a]['skus_distintos'] ?? 0));
            foreach ($idxSkus as $rank => $origIdx) {
                $skus = $clientes[$origIdx]['skus_distintos'] ?? 0;
                $pct = $rank / $n;
                $clientes[$origIdx]['_clase_skus'] = ($skus > 0) ? ($pct <= 0.20 ? 'A' : ($pct <= 0.80 ? 'B' : 'C')) : 'C';
            }

            // Drop Size
            $idxDrop = array_keys($clientes);
            usort($idxDrop, fn ($a, $b) => ($clientes[$b]['drop_size'] ?? 0.0) <=> ($clientes[$a]['drop_size'] ?? 0.0));
            foreach ($idxDrop as $rank => $origIdx) {
                $ds = $clientes[$origIdx]['drop_size'] ?? 0.0;
                $pct = $rank / $n;
                $clientes[$origIdx]['_clase_drop'] = ($ds > 0) ? ($pct <= 0.20 ? 'A' : ($pct <= 0.80 ? 'B' : 'C')) : 'C';
            }

            // Unidades
            $idxUnid = array_keys($clientes);
            usort($idxUnid, fn ($a, $b) => ($clientes[$b]['unidades'] ?? 0) <=> ($clientes[$a]['unidades'] ?? 0));
            foreach ($idxUnid as $rank => $origIdx) {
                $u = $clientes[$origIdx]['unidades'] ?? 0;
                $pct = $rank / $n;
                $clientes[$origIdx]['_clase_unidades'] = ($u > 0) ? ($pct <= 0.20 ? 'A' : ($pct <= 0.80 ? 'B' : 'C')) : 'C';
            }

            // Cajas
            $idxCajas = array_keys($clientes);
            usort($idxCajas, fn ($a, $b) => ($clientes[$b]['cajas'] ?? 0.0) <=> ($clientes[$a]['cajas'] ?? 0.0));
            foreach ($idxCajas as $rank => $origIdx) {
                $cj = $clientes[$origIdx]['cajas'] ?? 0.0;
                $pct = $rank / $n;
                $clientes[$origIdx]['_clase_cajas'] = ($cj > 0) ? ($pct <= 0.20 ? 'A' : ($pct <= 0.80 ? 'B' : 'C')) : 'C';
            }
        }

        $resumen = [
            'total_clientes' => count($clientes),
            'clientes_a' => $cntA,
            'clientes_b' => $cntB,
            'clientes_c' => $cntC,
            'volumen_total' => round($totalVolumen, 2),
            'volumen_a' => round($volA, 2),
            'volumen_b' => round($volB, 2),
            'volumen_c' => round($volC, 2),
        ];

        ksort($canalesSet);
        ksort($rutasSet);
        ksort($tiposNegocioSet);

        return [
            'mes_seleccionado' => $mesYm,
            'fecha_inicio_activa' => $fechaInicio,
            'fecha_corte_activa' => $fechaCorte,
            'canales_disponibles' => array_keys($canalesSet),
            'rutas_disponibles' => array_keys($rutasSet),
            'tipos_negocio_disponibles' => array_keys($tiposNegocioSet),
            'resumen' => $resumen,
            'clientes' => $clientes,
        ];
    }

    private function calcDesvio(float $vAct, float $vRef): array
    {
        if ($vRef == 0.0 && $vAct > 0.0) {
            return ['var_bs' => $vAct, 'var_pct' => 100.0, 'tendencia' => 'up', 'estado' => 'NUEVO'];
        }
        if ($vRef > 0.0 && $vAct == 0.0) {
            return ['var_bs' => -$vRef, 'var_pct' => -100.0, 'tendencia' => 'down', 'estado' => 'DESERCIÓN'];
        }
        if ($vRef == 0.0 && $vAct == 0.0) {
            return ['var_bs' => 0.0, 'var_pct' => 0.0, 'tendencia' => 'flat', 'estado' => 'SIN VENTA'];
        }

        $difBs = round($vAct - $vRef, 2);
        $difPct = round(($difBs / $vRef) * 100.0, 1);
        $tendencia = $difPct >= 5.0 ? 'up' : ($difPct <= -5.0 ? 'down' : 'flat');

        return ['var_bs' => $difBs, 'var_pct' => $difPct, 'tendencia' => $tendencia, 'estado' => 'ACTIVO'];
    }
}

