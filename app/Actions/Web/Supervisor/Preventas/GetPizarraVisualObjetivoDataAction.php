<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPizarraVisualObjetivoDataAction
{
    private const MESES_ESP_ABR = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    private const MESES_ESP_COMPLETO = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * Consulta consolidada analítica y financiera del KPI 3 (Objetivo Diario de Venta).
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
        $year = (int) $carbonFecha->year;
        $month = (int) $carbonFecha->month;
        $fInicioMes = sprintf('%04d-%02d-01', $year, $month);
        $mesStr = sprintf('%04d-%02d', $year, $month);

        $canalesClean = array_values(array_filter(array_map(fn($c) => strtoupper(trim((string)$c)), $canales)));
        $rutasClean = array_values(array_filter(array_map(fn($r) => strtoupper(trim((string)$r)), $rutas)));

        // 1. Catálogo base de rutas y canales
        $rutasQuery = DB::connection('supervisor')->table('dim_rutas')->select([
            'ruta',
            'canal',
            'vendedor',
            'vendedor_norm'
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
            return $this->generarRespuestaVacia($fechaNorm, $mesStr);
        }

        // Vendedores fallback desde pan_ruteo
        $panRuteoVendedores = [];
        try {
            $panRuteoVendedores = DB::connection('supervisor')->table('pan_ruteo')
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->whereNotNull('vendedor')
                ->where('vendedor', '!=', '')
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                    'vendedor'
                ])
                ->distinct()
                ->pluck('vendedor', 'ruta_clean')
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Error consultando pan_ruteo para vendedores en KPI 3: ' . $e->getMessage());
        }

        // 2. Metas mensuales desde fact_metas
        $patronesMes = $this->construirPatronesMes($year, $month);
        $nomMes = self::MESES_ESP_COMPLETO[$month] ?? '';
        $abrMes = self::MESES_ESP_ABR[$month] ?? '';
        $mes2d = sprintf('%02d', $month);

        $metasPorRuta = collect();
        try {
            $metasPorRuta = DB::connection('supervisor')->table('fact_metas')
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->where(function ($q) use ($patronesMes, $nomMes, $abrMes, $year, $mes2d, $fInicioMes): void {
                    $q->where('mes', $fInicioMes)
                      ->orWhereIn(DB::raw("LOWER(TRIM(mes))"), $patronesMes)
                      ->orWhere('mes', 'like', "%{$nomMes}%")
                      ->orWhere('mes', 'like', "%{$year}-{$mes2d}%")
                      ->orWhere('mes', 'like', "%{$abrMes}%");
                })
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                    DB::raw("MAX(CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(14, 2))) as objetivo_de_venta"),
                ])
                ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
                ->get()
                ->keyBy('ruta_clean');
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_metas en KPI 3: ' . $e->getMessage());
        }

        // 3. Facturado previo acumulado en fact_ventas (del 1 al día anterior)
        $facturadoPrevioPorRuta = collect();
        try {
            $facturadoPrevioPorRuta = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->where('fecha_norm', '>=', $fInicioMes)
                ->where('fecha_norm', '<', $fechaNorm)
                ->where(function ($q): void {
                    $q->whereNull('revertida')
                      ->orWhere(function ($sub): void {
                          $sub->where('revertida', '!=', 'Si')
                              ->where('revertida', '!=', 'SI');
                      });
                })
                ->where('monto_final', '>', 0)
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                    DB::raw("SUM(COALESCE(monto_final, 0.0)) as total_previo"),
                ])
                ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
                ->get()
                ->keyBy('ruta_clean');
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_ventas acumulado en KPI 3: ' . $e->getMessage());
        }

        // 4. Calendario mensual y días hábiles
        $diasTotales = 25;
        try {
            $rowCal = DB::connection('supervisor')->table('dim_calendario_mensual')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($rowCal && isset($rowCal->dias_habiles)) {
                $diasTotales = max(1, (int) $rowCal->dias_habiles);
            }
        } catch (\Throwable $e) {
            Log::debug('Calendario mensual no disponible, usando 25: ' . $e->getMessage());
        }

        $diasOperados = 1;
        try {
            $diasOperados = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', '>=', $fInicioMes)
                ->where('fecha_norm', '<=', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->distinct()
                ->count('fecha_norm');
            $diasOperados = max(1, $diasOperados);
        } catch (\Throwable) {
            $diasOperados = 1;
        }

        $diasHabilesRestantes = max(1, $diasTotales - $diasOperados);
        $divisorPreventa = max(1, $diasHabilesRestantes - 2);

        // 5. Preventas de hoy para las rutas seleccionadas
        $preventasHoy = collect();
        try {
            $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->where('monto_final', '>', 0)
                ->select([
                    'id',
                    DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                    DB::raw("COALESCE(TRIM(nro_preventa), '-') as nro_preventa"),
                    'cliente_id',
                    DB::raw("COALESCE(TRIM(codigo_cliente), '') as codigo_cliente"),
                    DB::raw("COALESCE(TRIM(cliente), 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(TRIM(vendedor), '') as vendedor"),
                    DB::raw("COALESCE(TRIM(codigo_producto), '') as codigo_producto"),
                    DB::raw("COALESCE(TRIM(producto), '-') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COALESCE(cantidad, 0) as cantidad"),
                    DB::raw("CAST(COALESCE(precio_lista, 0.0) AS DECIMAL(12, 2)) as precio_lista"),
                    DB::raw("CAST(COALESCE(monto_final, 0.0) AS DECIMAL(12, 2)) as monto_final"),
                ])
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_preventas en KPI 3: ' . $e->getMessage());
        }

        // Agrupación de preventas por ruta
        $preventasPorRuta = $preventasHoy->groupBy('ruta_clean');

        // 6. Evaluación de cada ruta (Bullet Chart & Balance)
        $rutasDetalle = [];
        $totalVentaGlobal = 0.0;
        $totalMetaDiariaGlobal = 0.0;
        $totalBrechaMesGlobal = 0.0;
        $rutasCumplenCount = 0;
        $pedidosGlobalSet = [];
        $compradoresGlobalSet = [];
        $skusGlobalSet = [];

        foreach ($rutasObjetivo as $ruta) {
            $cat = $catalogoRutas->get($ruta);
            $vendedor = ($cat && !empty(trim((string)$cat->vendedor)))
                ? trim((string)$cat->vendedor)
                : ($panRuteoVendedores[$ruta] ?? 'No Asignado');
            $canal = ($cat && !empty(trim((string)$cat->canal))) ? trim((string)$cat->canal) : 'GENERAL';

            // Meta mensual
            $metaRow = $metasPorRuta->get($ruta);
            $metaMes = $metaRow ? (float) ($metaRow->objetivo_de_venta ?? 0.0) : 0.0;

            // Facturado previo
            $prevRow = $facturadoPrevioPorRuta->get($ruta);
            $factPrevio = $prevRow ? (float) ($prevRow->total_previo ?? 0.0) : 0.0;

            // Brecha y Cuota diaria dinámica
            $brechaMes = max(0.0, $metaMes - $factPrevio);
            $cuotaDiaria = ($metaMes > 0) ? round($brechaMes / $divisorPreventa, 2) : 0.0;

            // Preventas de esta ruta hoy
            $preventasRuta = $preventasPorRuta->get($ruta, collect());
            $ventaHoy = round((float) $preventasRuta->sum('monto_final'), 2);

            // Cálculos de cumplimiento Unilever
            $diferencia = round($ventaHoy - $cuotaDiaria, 2);
            $alcancePct = ($cuotaDiaria > 0)
                ? round(($ventaHoy / $cuotaDiaria) * 100, 1)
                : ($ventaHoy > 0 ? 100.0 : 0.0);

            $cumple = ($cuotaDiaria > 0) ? ($ventaHoy >= $cuotaDiaria) : ($ventaHoy > 0);
            $puntos = $cumple ? 10 : 0;

            if ($cumple) {
                $rutasCumplenCount++;
            }

            // Estadísticas adicionales de ruta
            $pedidosRuta = [];
            $compradoresRuta = [];
            $skusRuta = [];
            $categoriasRuta = [];

            foreach ($preventasRuta as $p) {
                $monto = (float) $p->monto_final;
                $nro = (string) $p->nro_preventa;
                $cid = (string) ($p->cliente_id ?: $p->codigo_cliente);
                $sku = (string) $p->codigo_producto;
                $catNom = strtoupper(trim((string)$p->categoria));

                if ($nro !== '-' && $nro !== '') {
                    $pedidosRuta[$nro] = true;
                    $pedidosGlobalSet[$nro] = true;
                }
                if ($cid !== '') {
                    $compradoresRuta[$cid] = true;
                    $compradoresGlobalSet[$cid] = true;
                }
                if ($sku !== '') {
                    $skusRuta[$sku] = true;
                    $skusGlobalSet[$sku] = true;
                }

                if (!isset($categoriasRuta[$catNom])) {
                    $categoriasRuta[$catNom] = 0.0;
                }
                $categoriasRuta[$catNom] = round($categoriasRuta[$catNom] + $monto, 2);
            }

            $pedidosCount = count($pedidosRuta);
            $compradoresCount = count($compradoresRuta);
            $ticketPromedio = $pedidosCount > 0 ? round($ventaHoy / $pedidosCount, 2) : 0.0;

            // Top categorías de la ruta
            arsort($categoriasRuta);
            $topCategoriasRuta = [];
            foreach (array_slice($categoriasRuta, 0, 3, true) as $cName => $cMonto) {
                $topCategoriasRuta[] = [
                    'categoria' => $cName,
                    'monto' => $cMonto,
                    'pct' => $ventaHoy > 0 ? round(($cMonto / $ventaHoy) * 100, 1) : 0.0,
                ];
            }

            $totalVentaGlobal += $ventaHoy;
            $totalMetaDiariaGlobal += $cuotaDiaria;
            $totalBrechaMesGlobal += $brechaMes;

            $rutasDetalle[] = [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'canal' => $canal,
                'meta_mes' => $metaMes,
                'facturado_previo' => $factPrevio,
                'brecha_mes' => $brechaMes,
                'cuota_diaria' => $cuotaDiaria,
                'venta_hoy' => $ventaHoy,
                'diferencia' => $diferencia,
                'alcance_pct' => $alcancePct,
                'cumple' => $cumple,
                'puntos' => $puntos,
                'pedidos_count' => $pedidosCount,
                'compradores_count' => $compradoresCount,
                'ticket_promedio' => $ticketPromedio,
                'skus_count' => count($skusRuta),
                'top_categorias' => $topCategoriasRuta,
            ];
        }

        // Ordenar rutas por defecto por % de alcance desc
        usort($rutasDetalle, fn($a, $b) => $b['alcance_pct'] <=> $a['alcance_pct']);

        // 7. Mix de Categorías Global
        $categoriasGlobal = [];
        $topProductosGlobal = [];
        $pedidosMontos = []; // Para distribución de tickets

        // Agrupación por pedido para distribución de tickets
        $pedidosPorNro = $preventasHoy->groupBy('nro_preventa');
        foreach ($pedidosPorNro as $nro => $items) {
            $subtotalPedido = (float) $items->sum('monto_final');
            if ($subtotalPedido > 0) {
                $pedidosMontos[] = $subtotalPedido;
            }
        }

        // Si no hay nro_preventa diferenciado, agrupar por cliente
        if (empty($pedidosMontos)) {
            $pedidosPorCliente = $preventasHoy->groupBy('cliente_id');
            foreach ($pedidosPorCliente as $items) {
                $subtotalCliente = (float) $items->sum('monto_final');
                if ($subtotalCliente > 0) {
                    $pedidosMontos[] = $subtotalCliente;
                }
            }
        }

        // Segmentación de tickets
        $t1 = 0; $t1Monto = 0.0; // < 100
        $t2 = 0; $t2Monto = 0.0; // 100 - 300
        $t3 = 0; $t3Monto = 0.0; // 300 - 600
        $t4 = 0; $t4Monto = 0.0; // > 600

        foreach ($pedidosMontos as $montoPed) {
            if ($montoPed < 100.0) {
                $t1++;
                $t1Monto += $montoPed;
            } elseif ($montoPed < 300.0) {
                $t2++;
                $t2Monto += $montoPed;
            } elseif ($montoPed < 600.0) {
                $t3++;
                $t3Monto += $montoPed;
            } else {
                $t4++;
                $t4Monto += $montoPed;
            }
        }

        $totalPedidosMontoCount = count($pedidosMontos);
        $distribucionTickets = [
            [
                'rango' => '< 100 Bs',
                'etiqueta' => 'Menor a 100 Bs',
                'pedidos' => $t1,
                'porcentaje' => $totalPedidosMontoCount > 0 ? round(($t1 / $totalPedidosMontoCount) * 100, 1) : 0.0,
                'monto' => round($t1Monto, 2),
                'color' => '#86868B',
            ],
            [
                'rango' => '100 - 300 Bs',
                'etiqueta' => '100 a 300 Bs',
                'pedidos' => $t2,
                'porcentaje' => $totalPedidosMontoCount > 0 ? round(($t2 / $totalPedidosMontoCount) * 100, 1) : 0.0,
                'monto' => round($t2Monto, 2),
                'color' => '#0071E3',
            ],
            [
                'rango' => '300 - 600 Bs',
                'etiqueta' => '300 a 600 Bs',
                'pedidos' => $t3,
                'porcentaje' => $totalPedidosMontoCount > 0 ? round(($t3 / $totalPedidosMontoCount) * 100, 1) : 0.0,
                'monto' => round($t3Monto, 2),
                'color' => '#34C759',
            ],
            [
                'rango' => '> 600 Bs',
                'etiqueta' => 'Mayor a 600 Bs',
                'pedidos' => $t4,
                'porcentaje' => $totalPedidosMontoCount > 0 ? round(($t4 / $totalPedidosMontoCount) * 100, 1) : 0.0,
                'monto' => round($t4Monto, 2),
                'color' => '#AF52DE',
            ],
        ];

        // Agrupación de categorías
        $catAgrupadas = $preventasHoy->groupBy(fn($i) => strtoupper(trim((string)$i->categoria)));
        foreach ($catAgrupadas as $catNom => $items) {
            $catMonto = round((float) $items->sum('monto_final'), 2);
            $catCant = (int) $items->sum('cantidad');
            $rutasConVenta = $items->pluck('ruta_clean')->unique()->count();

            $categoriasGlobal[] = [
                'categoria' => $catNom ?: 'GENERAL',
                'monto' => $catMonto,
                'porcentaje' => $totalVentaGlobal > 0 ? round(($catMonto / $totalVentaGlobal) * 100, 1) : 0.0,
                'cantidad' => $catCant,
                'rutas_con_venta' => $rutasConVenta,
            ];
        }
        usort($categoriasGlobal, fn($a, $b) => $b['monto'] <=> $a['monto']);

        // Top 8 SKUs vendidos
        $prodAgrupados = $preventasHoy->groupBy(function ($i) {
            $sku = trim((string)$i->codigo_producto);
            return $sku !== '' ? $sku : mb_strtolower(trim((string)$i->producto));
        });

        foreach ($prodAgrupados as $items) {
            $first = $items->first();
            $pMonto = round((float) $items->sum('monto_final'), 2);
            $pCant = (int) $items->sum('cantidad');

            $topProductosGlobal[] = [
                'codigo' => trim((string)$first->codigo_producto) ?: 'S/C',
                'producto' => trim((string)$first->producto),
                'categoria' => strtoupper(trim((string)$first->categoria)) ?: 'GENERAL',
                'cantidad' => $pCant,
                'monto' => $pMonto,
            ];
        }
        usort($topProductosGlobal, fn($a, $b) => $b['monto'] <=> $a['monto']);
        $topProductosGlobal = array_slice($topProductosGlobal, 0, 8);

        // 8. Totales y KPIs ejecutivos
        $rutasEvaluadasCount = count($rutasObjetivo);
        $rutasDeficitCount = max(0, $rutasEvaluadasCount - $rutasCumplenCount);
        $alcanceGlobalPct = ($totalMetaDiariaGlobal > 0)
            ? round(($totalVentaGlobal / $totalMetaDiariaGlobal) * 100, 1)
            : ($totalVentaGlobal > 0 ? 100.0 : 0.0);

        $puntosUnilever = $rutasCumplenCount * 10;
        $puntosUnileverMax = $rutasEvaluadasCount * 10;
        $puntosPct = $puntosUnileverMax > 0 ? round(($puntosUnilever / $puntosUnileverMax) * 100, 1) : 0.0;
        $pedidosTotalCount = count($pedidosGlobalSet);
        $ticketPromedioGlobal = $pedidosTotalCount > 0 ? round($totalVentaGlobal / $pedidosTotalCount, 2) : 0.0;

        return [
            'fecha' => $fechaNorm,
            'mes' => $mesStr,
            'dias_habiles_mes' => $diasTotales,
            'dias_operados' => $diasOperados,
            'dias_habiles_restantes' => $diasHabilesRestantes,
            'divisor_preventa' => $divisorPreventa,
            'totales' => [
                'rutas_evaluadas' => $rutasEvaluadasCount,
                'rutas_cumplen' => $rutasCumplenCount,
                'rutas_deficit' => $rutasDeficitCount,
                'cumplimiento_pct' => $rutasEvaluadasCount > 0 ? round(($rutasCumplenCount / $rutasEvaluadasCount) * 100, 1) : 0.0,
                'puntos_unilever' => $puntosUnilever,
                'puntos_unilever_max' => $puntosUnileverMax,
                'puntos_pct' => $puntosPct,
                'meta_diaria_total' => round($totalMetaDiariaGlobal, 2),
                'venta_total' => round($totalVentaGlobal, 2),
                'diferencia_total' => round($totalVentaGlobal - $totalMetaDiariaGlobal, 2),
                'alcance_global_pct' => $alcanceGlobalPct,
                'pedidos_total' => $pedidosTotalCount,
                'compradores_total' => count($compradoresGlobalSet),
                'skus_vendidos_total' => count($skusGlobalSet),
                'ticket_promedio' => $ticketPromedioGlobal,
            ],
            'rutas' => $rutasDetalle,
            'categorias' => $categoriasGlobal,
            'distribucion_tickets' => $distribucionTickets,
            'top_productos' => $topProductosGlobal,
        ];
    }

    /**
     * Construye variantes de texto para coincidir con el campo mes en fact_metas.
     *
     * @param int $year
     * @param int $month
     * @return array<int, string>
     */
    private function construirPatronesMes(int $year, int $month): array
    {
        $abr = self::MESES_ESP_ABR[$month] ?? '';
        $nom = self::MESES_ESP_COMPLETO[$month] ?? '';
        $mes2d = sprintf('%02d', $month);
        $yy = substr((string)$year, -2);

        return array_values(array_unique(array_filter([
            "{$nom} {$year}",
            "{$nom}_{$year}",
            "{$nom}-{$year}",
            "{$year}-{$mes2d}",
            "{$nom} {$yy}",
            "{$abr} {$year}",
            "{$abr}_{$year}",
            "{$abr}-{$year}",
            "{$abr} {$yy}",
            "{$nom}",
            "{$abr}",
        ])));
    }

    /**
     * Genera respuesta vacía estructurada cuando no hay rutas coincidentes.
     *
     * @param string $fechaNorm
     * @param string $mesStr
     * @return array<string, mixed>
     */
    private function generarRespuestaVacia(string $fechaNorm, string $mesStr): array
    {
        return [
            'fecha' => $fechaNorm,
            'mes' => $mesStr,
            'dias_habiles_mes' => 25,
            'dias_operados' => 1,
            'dias_habiles_restantes' => 24,
            'divisor_preventa' => 22,
            'totales' => [
                'rutas_evaluadas' => 0,
                'rutas_cumplen' => 0,
                'rutas_deficit' => 0,
                'cumplimiento_pct' => 0.0,
                'puntos_unilever' => 0,
                'puntos_unilever_max' => 0,
                'puntos_pct' => 0.0,
                'meta_diaria_total' => 0.0,
                'venta_total' => 0.0,
                'diferencia_total' => 0.0,
                'alcance_global_pct' => 0.0,
                'pedidos_total' => 0,
                'compradores_total' => 0,
                'skus_vendidos_total' => 0,
                'ticket_promedio' => 0.0,
            ],
            'rutas' => [],
            'categorias' => [],
            'distribucion_tickets' => [],
            'top_productos' => [],
        ];
    }
}
