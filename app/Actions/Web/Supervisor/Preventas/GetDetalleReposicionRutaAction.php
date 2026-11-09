<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleReposicionRutaAction
{
    private static array $cacheDiasHabiles = [];
    private static array $cacheMetas = [];

    /**
     * Normaliza texto para comparaciones seguras de cadenas.
     */
    private function normalizar(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }
        $str = mb_strtolower(trim($texto), 'UTF-8');
        return preg_replace('/\s+/', ' ', $str) ?? $str;
    }

    /**
     * Calcula los días hábiles del mes y días transcurridos hasta la fecha de corte.
     *
     * @return array{dias_totales: int, dias_transcurridos: int, dias_restantes: int}
     */
    private function calcularDiasHabiles(int $year, int $month, string $fechaCorte): array
    {
        $cacheKey = "{$year}-{$month}-{$fechaCorte}";
        if (isset(self::$cacheDiasHabiles[$cacheKey])) {
            return self::$cacheDiasHabiles[$cacheKey];
        }

        $diasTotales = 25;

        try {
            $rowCal = DB::connection('supervisor')->table('dim_calendario_mensual')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($rowCal && isset($rowCal->dias_habiles)) {
                $diasTotales = (int) $rowCal->dias_habiles;
            }
        } catch (\Throwable) {
            $diasTotales = 25;
        }

        $fInicioMes = sprintf('%04d-%02d-01', $year, $month);
        $diasOperados = 1;

        try {
            $diasOperados = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', '>=', $fInicioMes)
                ->where('fecha_norm', '<=', $fechaCorte)
                ->where('monto_final', '>', 0)
                ->distinct()
                ->count('fecha_norm');
        } catch (\Throwable) {
            $diasOperados = 1;
        }

        $diasOperados = max(1, $diasOperados);
        $diasRestantes = max(1, $diasTotales - $diasOperados);

        return self::$cacheDiasHabiles[$cacheKey] = [
            'dias_totales' => $diasTotales,
            'dias_transcurridos' => $diasOperados,
            'dias_restantes' => $diasRestantes,
        ];
    }

    /**
     * Obtiene el objetivo comercial mensual de la ruta desde fact_metas.
     */
    private function obtenerMetaMesRuta(string $rutaClean, int $year, int $month, string $fInicioMes): float
    {
        $cacheKey = "{$rutaClean}-{$year}-{$month}";
        if (isset(self::$cacheMetas[$cacheKey])) {
            return self::$cacheMetas[$cacheKey];
        }

        try {
            $mesesEspCompleto = [
                1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
            ];
            $nom = $mesesEspCompleto[$month] ?? '';
            $abr = mb_substr($nom, 0, 3);
            $mes2d = sprintf('%02d', $month);
            $patronesMes = [
                $nom,
                "{$nom} {$year}",
                "{$nom}-{$year}",
                "{$nom}_{$year}",
                "{$abr} {$year}",
                "{$abr}-{$year}",
                "{$abr}_{$year}",
                "{$year}-{$mes2d}",
            ];

            $metaRow = DB::connection('supervisor')->table('fact_metas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($patronesMes, $fInicioMes, $year, $mes2d): void {
                    $q->where('mes', $fInicioMes)
                      ->orWhere('mes', 'like', "{$year}-{$mes2d}%")
                      ->orWhereIn(DB::raw("LOWER(TRIM(mes))"), $patronesMes);
                })
                ->selectRaw("MAX(CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(14, 2))) as meta_vol")
                ->first();

            $metaVal = $metaRow && $metaRow->meta_vol ? (float) $metaRow->meta_vol : 0.0;
            return self::$cacheMetas[$cacheKey] = $metaVal;
        } catch (\Throwable) {
            return self::$cacheMetas[$cacheKey] = 0.0;
        }
    }

    /**
     * ALGORITMO AsignarCuotasMensualesPorCliente (Waterfilling con Piso de 150 Bs)
     * Exactamente idéntico a Cobertura Core de Avance.
     *
     * @param float $objetivoRuta
     * @param float $pisoMinimo
     * @param array<int, float> $ventasHistoricasMap
     * @return array<int, float>
     */
    public function asignarCuotasMensualesPorCliente(
        float $objetivoRuta,
        float $pisoMinimo,
        array $ventasHistoricasMap
    ): array {
        $totalClientes = count($ventasHistoricasMap);
        if ($totalClientes === 0) {
            return [];
        }

        // Si el objetivo es 0 o negativo, todos quedan en el piso
        if ($objetivoRuta <= 0.0) {
            return array_fill_keys(array_keys($ventasHistoricasMap), $pisoMinimo);
        }

        // 1. Validar viabilidad matemática
        $pisoTotalRequerido = $totalClientes * $pisoMinimo;
        if ($objetivoRuta < $pisoTotalRequerido) {
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
        do {
            $hayClientesBajoPiso = false;
            $sumaHistoricaRestante = 0.0;
            foreach ($clientesRestantes as $cid) {
                $sumaHistoricaRestante += (float) ($ventasHistoricasMap[$cid] ?? 0.0);
            }

            $nuevosFijadosEnEstaRonda = [];
            $cantRestantes = count($clientesRestantes);

            foreach ($clientesRestantes as $cid) {
                $vHist = (float) ($ventasHistoricasMap[$cid] ?? 0.0);
                if ($sumaHistoricaRestante > 0.0) {
                    $pesoRelativo = $vHist / $sumaHistoricaRestante;
                    $cuotaCalculada = $presupuestoRestante * $pesoRelativo;
                } else {
                    $cuotaCalculada = $cantRestantes > 0 ? ($presupuestoRestante / $cantRestantes) : $pisoMinimo;
                }

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
            $clienteMayorCuotaId = null;
            $maxCuota = -1.0;
            foreach ($cuotasAsignadas as $cid => $cuota) {
                if ($cuota > $maxCuota) {
                    $maxCuota = $cuota;
                    $clienteMayorCuotaId = $cid;
                }
            }
            if ($clienteMayorCuotaId !== null) {
                $cuotasAsignadas[$clienteMayorCuotaId] = round(
                    $cuotasAsignadas[$clienteMayorCuotaId] + $diferenciaCentavos,
                    2
                );
            }
        }

        return $cuotasAsignadas;
    }

    /**
     * Obtiene la auditoría analítica del KPI 6 (Pedido de Reposición Semanal) con la misma
     * lógica de Cobertura Core de Avance dividida entre las semanas hábiles restantes.
     *
     * @param string $ruta
     * @param string|null $fecha
     * @param int|null $semanasHistoria
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null, ?int $semanasHistoria = 13): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $semanas = $semanasHistoria && $semanasHistoria > 0 ? $semanasHistoria : 13;
        $diasHistoria = $semanas * 7;
        $carbonFecha = Carbon::parse($fechaNorm);
        $year = (int) $carbonFecha->year;
        $month = (int) $carbonFecha->month;
        $fInicioMes = sprintf('%04d-%02d-01', $year, $month);

        $fechaInicioHist = $carbonFecha->copy()->subDays($diasHistoria)->toDateString();
        $fechaFinHist = $carbonFecha->copy()->subDays(1)->toDateString();
        $rutaClean = strtoupper(trim($ruta));

        $vendedor = 'No Asignado';
        $canal = 'GENERAL';

        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo) {
                $vendedor = trim((string) ($dimInfo->vendedor ?: 'No Asignado'));
                $canal = trim((string) ($dimInfo->canal ?: 'GENERAL'));
            }

            // 1. Calendario y Semanas Hábiles Restantes del Mes
            // En preventa las visitas son semanales (frecuencia semanal, 6 días de operación por semana)
            $cal = $this->calcularDiasHabiles($year, $month, $fechaNorm);
            $diasTotales = $cal['dias_totales'];
            $diasOperados = $cal['dias_transcurridos'];
            $diasRestantes = $cal['dias_restantes'];
            $semanasHabilesRestantes = max(1, (int) ceil($diasRestantes / 6.0));

            // 2. Meta Comercial Mensual de la Ruta
            $objetivoMesRuta = $this->obtenerMetaMesRuta($rutaClean, $year, $month, $fInicioMes);
            $cuotaSemanalRuta = $semanasHabilesRestantes > 0 ? round($objetivoMesRuta / $semanasHabilesRestantes, 2) : 0.0;

            // 3. Cartera Oficial de la Ruta desde pan_ruteo
            $carteraRutaRaw = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where('cliente_id', '>', 0)
                ->where(function ($q): void {
                    $q->whereNull('estado')
                      ->orWhereIn(DB::raw("UPPER(TRIM(estado))"), ['ACTIVO', 'ACTIVA', 'VIGENTE']);
                })
                ->pluck('cliente_id')
                ->toArray();

            $carteraRutaIds = array_values(array_filter(array_unique(array_map('intval', $carteraRutaRaw)), fn ($id) => $id > 0));

            // 4. Preventas del día de la ruta
            $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->select([
                    'cliente_id',
                    DB::raw("COALESCE(TRIM(codigo_cliente), '') as codigo_cliente"),
                    DB::raw("COALESCE(TRIM(cliente), 'S/N') as cliente"),
                    'producto_id',
                    DB::raw("COALESCE(TRIM(codigo_producto), '') as codigo_producto"),
                    DB::raw("COALESCE(TRIM(producto), 'SKU') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COALESCE(cantidad, 0) as cantidad"),
                    DB::raw("COALESCE(monto_final, 0.0) as monto"),
                ])
                ->get();

            if ($preventasHoy->isEmpty()) {
                return [
                    'ruta' => $ruta,
                    'canal' => $canal,
                    'vendedor' => $vendedor,
                    'fecha' => $fechaNorm,
                    'configuracion' => [
                        'semanas_historia' => $semanas,
                        'dias_historia' => $diasHistoria,
                        'dias_totales' => $diasTotales,
                        'dias_transcurridos' => $diasOperados,
                        'dias_restantes' => $diasRestantes,
                        'semanas_habiles_restantes' => $semanasHabilesRestantes,
                        'objetivo_mes_ruta' => $objetivoMesRuta,
                        'cuota_semanal_ruta' => $cuotaSemanalRuta,
                        'piso_pct' => 20.0,
                        'tope_pct' => 20.0,
                        'puntos_max' => 15.0,
                    ],
                    'totales' => [
                        'compradores_hoy' => 0,
                        'compradores_evaluables' => 0,
                        'compradores_nuevos' => 0,
                        'puntos_esperados_total' => 0,
                        'puntos_match_total' => 0,
                        'skus_esperados_total' => 0,
                        'skus_con_match_total' => 0,
                        'skus_fuera_canasta_total' => 0,
                        'porcentaje_reposicion_ruta' => 0.0,
                        'cumple_umbral' => false,
                        'puntos_obtenidos' => 0.0,
                        'estatus_ruta' => 'DÉFICIT',
                    ],
                    'tiendas' => [],
                ];
            }

            // Agrupar compras de hoy por cliente y por producto
            $clientesCompradores = [];
            foreach ($preventasHoy as $f) {
                $cid = (int) $f->cliente_id;
                $pid = (int) $f->producto_id;
                $cod = trim((string) $f->codigo_producto);
                $prod = trim((string) $f->producto);
                $prodNorm = $this->normalizar($prod);

                $key = $cod !== '' ? "cod_{$cod}" : ($pid > 0 ? "id_{$pid}" : "nom_{$prodNorm}");

                if (!isset($clientesCompradores[$cid])) {
                    $clientesCompradores[$cid] = [
                        'cliente_id' => $cid,
                        'codigo_cliente' => $f->codigo_cliente ?: (string) $cid,
                        'cliente' => $f->cliente,
                        'total_bs_hoy' => 0.0,
                        'pedido_hoy_map' => [],
                        'keys_hoy' => [],
                    ];
                }
                $clientesCompradores[$cid]['total_bs_hoy'] += (float) $f->monto;

                if (!isset($clientesCompradores[$cid]['pedido_hoy_map'][$key])) {
                    $clientesCompradores[$cid]['pedido_hoy_map'][$key] = [
                        'producto_id' => $pid,
                        'codigo_producto' => $cod,
                        'producto' => $prod,
                        'categoria' => $f->categoria,
                        'cantidad' => 0,
                        'monto' => 0.0,
                    ];
                }
                $clientesCompradores[$cid]['pedido_hoy_map'][$key]['cantidad'] += (int) $f->cantidad;
                $clientesCompradores[$cid]['pedido_hoy_map'][$key]['monto'] += (float) $f->monto;

                if ($cod !== '') {
                    $clientesCompradores[$cid]['keys_hoy']["cod_{$cod}"] = $key;
                }
                if ($pid > 0) {
                    $clientesCompradores[$cid]['keys_hoy']["id_{$pid}"] = $key;
                }
                if ($prodNorm !== '') {
                    $clientesCompradores[$cid]['keys_hoy']["nom_{$prodNorm}"] = $key;
                }
            }

            $cidsCompradores = array_keys($clientesCompradores);
            // Universo de clientes para Waterfilling: cartera oficial de la ruta + compradores de hoy
            $universoClientesRuta = array_values(array_unique(array_merge($carteraRutaIds, $cidsCompradores)));

            // 5. Historial de compras en fact_preventas (ventana seleccionada de semanas previas)
            $histPrev = DB::connection('supervisor')->table('fact_preventas')
                ->whereIn('cliente_id', $universoClientesRuta)
                ->where('fecha_norm', '>=', $fechaInicioHist)
                ->where('fecha_norm', '<=', $fechaFinHist)
                ->where('monto_final', '>', 0)
                ->select([
                    'cliente_id',
                    'producto_id',
                    DB::raw("COALESCE(TRIM(codigo_producto), '') as codigo_producto"),
                    DB::raw("COALESCE(TRIM(producto), 'SKU') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COUNT(DISTINCT fecha_norm) as frecuencia_compra"),
                    DB::raw("COUNT(DISTINCT fecha_norm) as dias_con_compra"),
                    DB::raw("SUM(cantidad) as total_unidades"),
                    DB::raw("SUM(monto_final) as total_bs"),
                    DB::raw("SUBSTRING(GROUP_CONCAT(CONCAT(DATE(fecha_norm), ':', cantidad, ':', ROUND(monto_final, 2)) ORDER BY fecha_norm DESC SEPARATOR '|'), 1, 2048) as detalle_compras_raw"),
                ])
                ->groupBy('cliente_id', 'producto_id', DB::raw("COALESCE(TRIM(codigo_producto), '')"), DB::raw("COALESCE(TRIM(producto), 'SKU')"), DB::raw("COALESCE(TRIM(categoria), 'GENERAL')"))
                ->get();

            // Fallback en fact_ventas para clientes de cartera sin preventas en el histórico
            $clientesConPreventaHist = $histPrev->pluck('cliente_id')->unique()->map('intval')->toArray();
            $cidsSinPreventa = array_values(array_diff($universoClientesRuta, $clientesConPreventaHist));
            $histVentas = collect();
            if (!empty($cidsSinPreventa)) {
                $histVentas = DB::connection('supervisor')->table('fact_ventas')
                    ->whereIn('cliente_id', $cidsSinPreventa)
                    ->where('fecha_norm', '>=', $fechaInicioHist)
                    ->where('fecha_norm', '<=', $fechaFinHist)
                    ->where('monto_final', '!=', 0)
                    ->where(function ($q): void {
                        $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                    })
                    ->select([
                        'cliente_id',
                        DB::raw("0 as producto_id"),
                        DB::raw("COALESCE(TRIM(codigo), '') as codigo_producto"),
                        DB::raw("COALESCE(TRIM(producto), 'SKU') as producto"),
                        DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                        DB::raw("COUNT(DISTINCT fecha_norm) as frecuencia_compra"),
                        DB::raw("COUNT(DISTINCT fecha_norm) as dias_con_compra"),
                        DB::raw("SUM(cantidad) as total_unidades"),
                        DB::raw("SUM(monto_final) as total_bs"),
                        DB::raw("SUBSTRING(GROUP_CONCAT(CONCAT(DATE(fecha_norm), ':', cantidad, ':', ROUND(monto_final, 2)) ORDER BY fecha_norm DESC SEPARATOR '|'), 1, 2048) as detalle_compras_raw"),
                    ])
                    ->groupBy('cliente_id', DB::raw("COALESCE(TRIM(codigo), '')"), DB::raw("COALESCE(TRIM(producto), 'SKU')"), DB::raw("COALESCE(TRIM(categoria), 'GENERAL')"))
                    ->get();
            }

            $historialPorCliente = [];
            $ventasHistoricasMap = [];

            // Inicializar ventas históricas del universo para Waterfilling
            foreach ($universoClientesRuta as $cidU) {
                $ventasHistoricasMap[$cidU] = 0.0;
            }

            $procesarFilaHistorial = function ($hv) use (&$historialPorCliente, &$ventasHistoricasMap): void {
                $cid = (int) $hv->cliente_id;
                $pid = (int) $hv->producto_id;
                $cod = trim((string) $hv->codigo_producto);
                $prod = trim((string) $hv->producto);
                $prodNorm = $this->normalizar($prod);

                $key = $cod !== '' ? "cod_{$cod}" : ($pid > 0 ? "id_{$pid}" : "nom_{$prodNorm}");

                if (!isset($historialPorCliente[$cid])) {
                    $historialPorCliente[$cid] = [];
                }

                if (!isset($historialPorCliente[$cid][$key])) {
                    $historialPorCliente[$cid][$key] = [
                        'producto_id' => $pid,
                        'codigo_producto' => $cod,
                        'producto' => $prod,
                        'categoria' => $hv->categoria,
                        'frecuencia_compra' => 0,
                        'dias_con_compra' => 0,
                        'total_unidades' => 0,
                        'total_bs' => 0.0,
                        'historial_compras' => [],
                    ];
                }

                $historialPorCliente[$cid][$key]['frecuencia_compra'] = max(
                    $historialPorCliente[$cid][$key]['frecuencia_compra'],
                    (int) $hv->frecuencia_compra
                );
                $historialPorCliente[$cid][$key]['dias_con_compra'] = max(
                    $historialPorCliente[$cid][$key]['dias_con_compra'],
                    (int) $hv->dias_con_compra
                );
                $historialPorCliente[$cid][$key]['total_unidades'] += (int) $hv->total_unidades;
                $historialPorCliente[$cid][$key]['total_bs'] += (float) $hv->total_bs;

                $ventasHistoricasMap[$cid] = ($ventasHistoricasMap[$cid] ?? 0.0) + (float) $hv->total_bs;

                // Parsear compras individuales
                $rawTxs = (string) ($hv->detalle_compras_raw ?? '');
                if ($rawTxs !== '') {
                    foreach (explode('|', $rawTxs) as $b) {
                        $partes = explode(':', trim($b));
                        if (count($partes) >= 3) {
                            $historialPorCliente[$cid][$key]['historial_compras'][] = [
                                'fecha' => $partes[0],
                                'unidades' => (int) $partes[1],
                                'monto_bs' => round((float) $partes[2], 2),
                            ];
                        }
                    }
                }
            };

            foreach ($histPrev as $hp) {
                $procesarFilaHistorial($hp);
            }
            foreach ($histVentas as $hv) {
                $procesarFilaHistorial($hv);
            }

            // 6. Waterfilling de Cobertura Core: Cuotas Mensuales por Cliente (Piso 150 Bs)
            $cuotasMensualesMap = $this->asignarCuotasMensualesPorCliente(
                $objetivoMesRuta,
                150.0,
                $ventasHistoricasMap
            );

            // 6.1 Catálogo Pareto de la ruta ordenado por mayor rotación física (volumen de unidades compradas)
            $paretoRuta = [];
            foreach ($historialPorCliente as $cIdHist => $prodsHist) {
                foreach ($prodsHist as $pKey => $pData) {
                    if (!isset($paretoRuta[$pKey])) {
                        $paretoRuta[$pKey] = [
                            'producto_id' => $pData['producto_id'],
                            'codigo_producto' => $pData['codigo_producto'],
                            'producto' => $pData['producto'],
                            'categoria' => $pData['categoria'],
                            'total_unidades' => 0,
                            'total_bs' => 0.0,
                            'precio' => 0.0,
                            'frecuencia' => 0,
                            'clientes_count' => 0,
                        ];
                    }
                    $paretoRuta[$pKey]['total_unidades'] += (int) ($pData['total_unidades'] ?? 0);
                    $paretoRuta[$pKey]['total_bs'] += (float) ($pData['total_bs'] ?? 0.0);
                    $paretoRuta[$pKey]['frecuencia'] += (int) ($pData['frecuencia_compra'] ?? 0);
                    $paretoRuta[$pKey]['clientes_count']++;
                    if ($paretoRuta[$pKey]['precio'] <= 0.0 && ($pData['total_unidades'] ?? 0) > 0 && ($pData['total_bs'] ?? 0) > 0) {
                        $paretoRuta[$pKey]['precio'] = round($pData['total_bs'] / $pData['total_unidades'], 2);
                    }
                }
            }

            foreach ($paretoRuta as &$itPareto) {
                if ($itPareto['precio'] <= 0.0 && $itPareto['total_unidades'] > 0 && $itPareto['total_bs'] > 0) {
                    $itPareto['precio'] = round($itPareto['total_bs'] / $itPareto['total_unidades'], 2);
                }
            }
            unset($itPareto);

            // Ordenar Pareto de la ruta por rotación física (unidades vendidas) descendente y frecuencia descendente
            uasort($paretoRuta, function ($a, $b) {
                if ($a['total_unidades'] !== $b['total_unidades']) {
                    return $b['total_unidades'] <=> $a['total_unidades'];
                }
                if ($a['frecuencia'] !== $b['frecuencia']) {
                    return $b['frecuencia'] <=> $a['frecuencia'];
                }
                return $b['clientes_count'] <=> $a['clientes_count'];
            });

            // 7. Evaluar Reposición Semanal Tienda por Tienda con Lógica Cobertura Core
            // La cuota mensual se divide entre las semanas hábiles restantes del mes (las visitas son semanales)
            $tiendas = [];
            $totalSkusEsperados = 0;
            $totalSkusMatch = 0;
            $totalSkusFueraCanasta = 0;
            $totalPuntosEsperadosRuta = 0;
            $totalPuntosMatchRuta = 0;
            $tiendasEvaluablesCount = 0;
            $tiendasNuevasCount = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $canastaRaw = $historialPorCliente[$cid] ?? [];
                $pedidoHoy = $cData['pedido_hoy_map'];
                $keysHoy = $cData['keys_hoy'];

                $cuotaMensualCli = (float) ($cuotasMensualesMap[$cid] ?? 150.0);
                // REGLA CLAVE: Dividir entre semanas hábiles restantes del mes
                $cuotaSemanalCli = $semanasHabilesRestantes > 0
                    ? round($cuotaMensualCli / $semanasHabilesRestantes, 2)
                    : $cuotaMensualCli;
                $cuotaSemanalCli = max(10.0, $cuotaSemanalCli);

                // 1. Filtrar SKUs habituales del cliente (frecuencia >= 1 y unidades acumuladas >= 4)
                $habitualesCli = [];
                foreach ($canastaRaw as $hKey => $hab) {
                    $uHist = (int) ($hab['total_unidades'] ?? 0);
                    $bHist = (float) ($hab['total_bs'] ?? 0.0);
                    $fHist = (int) ($hab['frecuencia_compra'] ?? 0);

                    $pCalc = $uHist > 0 && $bHist > 0 ? round($bHist / $uHist, 2) : 0.0;
                    if ($pCalc <= 0.0) {
                        $pCalc = (float) ($paretoRuta[$hKey]['precio'] ?? 1.0);
                    }
                    if ($pCalc <= 0.0) {
                        $pCalc = 1.0;
                    }

                    if ($fHist >= 1 && $uHist >= 4) {
                        $habitualesCli[$hKey] = array_merge($hab, [
                            'precio' => $pCalc,
                            'total_bs' => $bHist,
                            'tipo_origen' => 'HABITO_CLIENTE',
                        ]);
                    }
                }

                // PASO 1: Participación histórica dentro de la tienda (Mix Propio)
                $ventaTotalHabitual = array_sum(array_column($habitualesCli, 'total_bs'));
                // Ordenar habituales por rotación en Bs descendente para tener SKU #1 primero
                uasort($habitualesCli, fn ($a, $b) => ($b['total_bs'] <=> $a['total_bs']));

                // PASO 2 y 3: Asignación de base monetaria respetando TECHO HISTÓRICO estricto
                $subtotalBase = 0.0;
                foreach ($habitualesCli as $pk => &$hInfo) {
                    $mix_j = $ventaTotalHabitual > 0
                        ? ($hInfo['total_bs'] / $ventaTotalHabitual)
                        : (1.0 / count($habitualesCli));
                    $presupuestoTeorico_j = $cuotaSemanalCli * $mix_j;
                    $pj = $hInfo['precio'] > 0 ? $hInfo['precio'] : 1.0;

                    $uHist = (int) ($hInfo['total_unidades'] ?? 0);
                    $cantCalculada = max(1, (int) floor($presupuestoTeorico_j / $pj));
                    // REGLA: No inflar más de lo que compró en su histórico
                    $cantBase = ($uHist > 0) ? min($cantCalculada, $uHist) : $cantCalculada;
                    $montoBase = round($cantBase * $pj, 2);

                    $hInfo['cant_sugerida'] = $cantBase;
                    $hInfo['monto_sugerido'] = $montoBase;
                    $hInfo['mix_propio'] = $mix_j;
                    $hInfo['presupuesto_teorico'] = round($presupuestoTeorico_j, 2);
                    $subtotalBase = round($subtotalBase + $montoBase, 2);
                }
                unset($hInfo);

                // PASO 4: Nivelación comercial si queda brecha (subtotalBase < cuotaSemanalCli)
                // Prioridad 1: Productos que el cliente ya conoce (del histórico con cantidad < 4 a 4 unidades fijas)
                if ($subtotalBase < $cuotaSemanalCli) {
                    $candidatosHist = [];
                    foreach ($canastaRaw as $hKey => $hab) {
                        if (!isset($habitualesCli[$hKey])) {
                            $uHist = (int) ($hab['total_unidades'] ?? 0);
                            $bHist = (float) ($hab['total_bs'] ?? 0.0);
                            $pCalc = $uHist > 0 && $bHist > 0 ? round($bHist / $uHist, 2) : 0.0;
                            if ($pCalc <= 0.0) {
                                $pCalc = (float) ($paretoRuta[$hKey]['precio'] ?? 1.0);
                            }
                            if ($pCalc <= 0.0) {
                                $pCalc = 1.0;
                            }
                            if ($uHist > 0) {
                                $candidatosHist[] = array_merge($hab, [
                                    'hKey' => $hKey,
                                    'precio' => $pCalc,
                                    'total_bs' => $bHist,
                                    'total_unidades' => $uHist,
                                ]);
                            }
                        }
                    }

                    // Ordenar candidatos del cliente: 1° volumen físico (unidades), 2° monto Bs
                    usort($candidatosHist, function ($a, $b) {
                        if ($a['total_unidades'] !== $b['total_unidades']) {
                            return $b['total_unidades'] <=> $a['total_unidades'];
                        }
                        return $b['total_bs'] <=> $a['total_bs'];
                    });

                    foreach ($candidatosHist as $cHist) {
                        if ($subtotalBase >= $cuotaSemanalCli) {
                            break;
                        }
                        $hk = $cHist['hKey'];
                        $pUlt = $cHist['precio'];
                        $cantNueva = 4;
                        $montoNuevo = round($cantNueva * $pUlt, 2);

                        $habitualesCli[$hk] = array_merge($cHist, [
                            'cant_sugerida' => $cantNueva,
                            'monto_sugerido' => $montoNuevo,
                            'mix_propio' => 0.0,
                            'presupuesto_teorico' => $montoNuevo,
                            'tipo_origen' => 'HISTORIAL_CLIENTE',
                        ]);
                        $subtotalBase = round($subtotalBase + $montoNuevo, 2);
                    }
                }

                // Prioridad 2: Si aún queda brecha, catálogo Pareto de la ruta (a 4 unidades fijas)
                if ($subtotalBase < $cuotaSemanalCli && !empty($paretoRuta)) {
                    foreach ($paretoRuta as $pKey => $pItem) {
                        if ($subtotalBase >= $cuotaSemanalCli) {
                            break;
                        }
                        if (isset($habitualesCli[$pKey])) {
                            continue;
                        }
                        $pUlt = (float) ($pItem['precio'] ?? 0.0);
                        if ($pUlt <= 0.0) {
                            continue;
                        }
                        $cantNueva = 4;
                        $montoNuevo = round($cantNueva * $pUlt, 2);

                        $habitualesCli[$pKey] = [
                            'producto_id' => $pItem['producto_id'],
                            'codigo_producto' => $pItem['codigo_producto'],
                            'producto' => $pItem['producto'],
                            'categoria' => $pItem['categoria'],
                            'frecuencia_compra' => 1,
                            'dias_con_compra' => 1,
                            'total_unidades' => 0,
                            'total_bs' => 0.0,
                            'precio' => $pUlt,
                            'cant_sugerida' => $cantNueva,
                            'monto_sugerido' => $montoNuevo,
                            'mix_propio' => 0.0,
                            'presupuesto_teorico' => $montoNuevo,
                            'tipo_origen' => 'PARETO_RUTA',
                            'es_core_pareto' => true,
                            'historial_compras' => [],
                        ];
                        $subtotalBase = round($subtotalBase + $montoNuevo, 2);
                    }
                }

                $subtotalSugeridoSemanal = $subtotalBase;

                // PASO 5: Match contra el pedido de hoy
                $keysUsadasHoy = [];
                $canastaBase = [];
                $matchesCliente = 0;
                $puntosEsperadosTienda = 0;
                $puntosMatchTienda = 0;
                $montoMatchTienda = 0.0;

                foreach ($habitualesCli as $hKey => $hab) {
                    $cod = $hab['codigo_producto'];
                    $pid = $hab['producto_id'];
                    $prodNorm = $this->normalizar($hab['producto']);

                    $keyMatch = null;
                    if ($cod !== '' && isset($keysHoy["cod_{$cod}"])) {
                        $keyMatch = $keysHoy["cod_{$cod}"];
                    } elseif ($pid > 0 && isset($keysHoy["id_{$pid}"])) {
                        $keyMatch = $keysHoy["id_{$pid}"];
                    } elseif ($prodNorm !== '' && isset($keysHoy["nom_{$prodNorm}"])) {
                        $keyMatch = $keysHoy["nom_{$prodNorm}"];
                    }

                    $esMatch = ($keyMatch !== null && isset($pedidoHoy[$keyMatch]));
                    $unidadesHoy = $esMatch ? (int) $pedidoHoy[$keyMatch]['cantidad'] : 0;
                    $montoHoy = $esMatch ? round((float) $pedidoHoy[$keyMatch]['monto'], 2) : 0.0;

                    $freqCompra = max(1, (int) ($hab['frecuencia_compra'] ?? 1));
                    $pesoFi = $freqCompra;
                    $puntosEsperadosTienda += $pesoFi;

                    $cantRepoSemanal = (int) ($hab['cant_sugerida'] ?? 1);
                    $montoRepoSemanal = (float) ($hab['monto_sugerido'] ?? round($cantRepoSemanal * ($hab['precio'] ?? 1.0), 2));

                    if ($esMatch) {
                        $matchesCliente++;
                        $keysUsadasHoy[$keyMatch] = true;
                        $puntosMatchTienda += $pesoFi;
                        $montoMatchTienda += min($montoHoy, $montoRepoSemanal);
                    }

                    $canastaBase[] = [
                        'producto_id' => $hab['producto_id'],
                        'codigo_producto' => $hab['codigo_producto'],
                        'producto' => $hab['producto'],
                        'descripcion' => $hab['producto'],
                        'categoria' => $hab['categoria'],
                        'tipo_origen' => $hab['tipo_origen'] ?? 'HABITO_CLIENTE',
                        'peso_fi' => $pesoFi,
                        'frecuencia_compra' => $freqCompra,
                        'unidades_acumuladas' => $hab['total_unidades'] ?? 0,
                        'dias_con_compra' => $freqCompra,
                        'semanas_transcurridas' => $semanas,
                        'semanas_habiles_restantes' => $semanasHabilesRestantes,
                        'precio_unitario' => round((float) $hab['precio'], 2),
                        'mix_propio_pct' => round(($hab['mix_propio'] ?? 0.0) * 100, 1),
                        'presupuesto_teorico' => round((float) ($hab['presupuesto_teorico'] ?? 0.0), 2),
                        'cantidad_repo' => $cantRepoSemanal,
                        'cant_sugerida' => $cantRepoSemanal,
                        'monto_sugerido' => $montoRepoSemanal,
                        'monto_acumulado_bs' => round((float) ($hab['total_bs'] ?? 0.0), 2),
                        'unidades_hoy' => $unidadesHoy,
                        'monto_hoy' => $montoHoy,
                        'recomprado_hoy' => $esMatch,
                        'estatus' => $esMatch ? 'MATCH_SKU' : 'OMITIDO',
                        'historial_compras' => $hab['historial_compras'] ?? [],
                    ];
                }

                // Ordenar canasta habitual: primero recomprados, luego peso f_i descendente
                usort($canastaBase, function ($a, $b) {
                    if ($a['recomprado_hoy'] !== $b['recomprado_hoy']) {
                        return $b['recomprado_hoy'] <=> $a['recomprado_hoy'];
                    }
                    if ($a['peso_fi'] !== $b['peso_fi']) {
                        return $b['peso_fi'] <=> $a['peso_fi'];
                    }
                    return $b['monto_sugerido'] <=> $a['monto_sugerido'];
                });

                // Detectar pedidos de hoy fuera de canasta (Ampliación / Nuevos)
                $pedidosHoyVista = [];
                $skusFueraCanasta = [];

                foreach ($pedidoHoy as $pKey => $pItem) {
                    $esMatch = isset($keysUsadasHoy[$pKey]);
                    $itemObj = [
                        'producto_id' => $pItem['producto_id'],
                        'codigo_producto' => $pItem['codigo_producto'],
                        'producto' => $pItem['producto'],
                        'descripcion' => $pItem['producto'],
                        'categoria' => $pItem['categoria'],
                        'unidades' => $pItem['cantidad'],
                        'unidades_hoy' => $pItem['cantidad'],
                        'monto_bs' => round($pItem['monto'], 2),
                        'monto_hoy' => round($pItem['monto'], 2),
                        'recomprado_hoy' => $esMatch,
                        'es_match_canasta' => $esMatch,
                        'estatus' => $esMatch ? 'REPOSICION_SKU' : 'AMPLIACION',
                    ];

                    $pedidosHoyVista[] = $itemObj;
                    if (!$esMatch) {
                        $skusFueraCanasta[] = $itemObj;
                    }
                }

                $cntEsperados = count($canastaBase);
                $cntMatch = $matchesCliente;
                $cntFuera = count($skusFueraCanasta);

                $totalSkusEsperados += $cntEsperados;
                $totalSkusMatch += $cntMatch;
                $totalSkusFueraCanasta += $cntFuera;

                // Regla de Cliente Nuevo o Recuperado (Puntos Esperados = 0)
                $esClienteNuevo = ($cntEsperados === 0 || $puntosEsperadosTienda === 0);

                if (!$esClienteNuevo) {
                    $pctReposicionTienda = round(($puntosMatchTienda / $puntosEsperadosTienda) * 100.0, 1);
                    $cumpleTienda = ($pctReposicionTienda >= 20.0);

                    $totalPuntosEsperadosRuta += $puntosEsperadosTienda;
                    $totalPuntosMatchRuta += $puntosMatchTienda;
                    $tiendasEvaluablesCount++;
                } else {
                    $pctReposicionTienda = null;
                    $cumpleTienda = null;
                    $tiendasNuevasCount++;
                }

                $tiendas[] = [
                    'cliente_id' => $cid,
                    'codigo_cliente' => $cData['codigo_cliente'],
                    'cliente' => $cData['cliente'],
                    'cuota_mensual' => $cuotaMensualCli,
                    'cuota_semanal' => $cuotaSemanalCli,
                    'semanas_habiles_restantes' => $semanasHabilesRestantes,
                    'monto_sugerido_semanal' => round($subtotalSugeridoSemanal, 2),
                    'monto_match_hoy' => round($montoMatchTienda, 2),
                    'total_bs_hoy' => round($cData['total_bs_hoy'], 2),
                    'total_skus_hoy' => count($pedidoHoy),
                    'es_neutro' => $esClienteNuevo,
                    'puntos_esperados' => $puntosEsperadosTienda,
                    'puntos_match' => $puntosMatchTienda,
                    'skus_esperados' => $cntEsperados,
                    'skus_con_match' => $cntMatch,
                    'skus_sin_match' => max(0, $cntEsperados - $cntMatch),
                    'skus_fuera_canasta_count' => $cntFuera,
                    'porcentaje_reposicion' => $pctReposicionTienda,
                    'cumple' => $cumpleTienda,
                    'skus_habituales' => $canastaBase, // Para Vue template
                    'canasta_base' => $canastaBase,    // Compatibilidad
                    'pedidos_hoy' => $pedidosHoyVista, // Compatibilidad
                    'skus_fuera_canasta' => $skusFueraCanasta,
                ];
            }

            // Ordenar: clientes evaluables primero por total Bs desc, clientes neutros al final
            usort($tiendas, function ($a, $b) {
                if ($a['es_neutro'] !== $b['es_neutro']) {
                    return $a['es_neutro'] ? 1 : -1;
                }
                return $b['total_bs_hoy'] <=> $a['total_bs_hoy'];
            });

            $cntTiendas = count($tiendas);

            // Porcentaje de Reposición del Día de la Ruta (Ponderado Global de Puntos)
            if ($totalPuntosEsperadosRuta > 0) {
                $pctReposicionRuta = round(($totalPuntosMatchRuta / $totalPuntosEsperadosRuta) * 100.0, 1);
            } elseif ($cntTiendas > 0) {
                $pctReposicionRuta = 100.0;
            } else {
                $pctReposicionRuta = 0.0;
            }

            // Asignación de Puntos (15 Puntos a partir de 20% de reposición)
            if ($cntTiendas === 0 || $pctReposicionRuta < 20.0) {
                $puntosObtenidos = 0.0;
                $cumpleUmbral = false;
                $estatusRuta = 'DÉFICIT';
            } else {
                $puntosObtenidos = 15.0;
                $cumpleUmbral = true;
                $estatusRuta = 'CUMPLE';
            }

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                    'dias_totales' => $diasTotales,
                    'dias_transcurridos' => $diasOperados,
                    'dias_restantes' => $diasRestantes,
                    'semanas_habiles_restantes' => $semanasHabilesRestantes,
                    'objetivo_mes_ruta' => $objetivoMesRuta,
                    'cuota_semanal_ruta' => $cuotaSemanalRuta,
                    'piso_pct' => 20.0,
                    'tope_pct' => 20.0,
                    'puntos_max' => 15.0,
                ],
                'totales' => [
                    'compradores_hoy' => $cntTiendas,
                    'compradores_evaluables' => $tiendasEvaluablesCount,
                    'compradores_nuevos' => $tiendasNuevasCount,
                    'puntos_esperados_total' => $totalPuntosEsperadosRuta,
                    'puntos_match_total' => $totalPuntosMatchRuta,
                    'skus_esperados_total' => $totalSkusEsperados,
                    'skus_con_match_total' => $totalSkusMatch,
                    'skus_fuera_canasta_total' => $totalSkusFueraCanasta,
                    'porcentaje_reposicion_ruta' => $pctReposicionRuta,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $puntosObtenidos,
                    'estatus_ruta' => $estatusRuta,
                ],
                'tiendas' => $tiendas,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleReposicionRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'totales' => [
                    'compradores_hoy' => 0,
                    'skus_esperados_total' => 0,
                    'skus_con_match_total' => 0,
                    'skus_fuera_canasta_total' => 0,
                    'porcentaje_reposicion_ruta' => 0.0,
                    'cumple_umbral' => false,
                    'puntos_obtenidos' => 0.0,
                ],
                'tiendas' => [],
            ];
        }
    }
}
