<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Actions\Web\Supervisor\Avance\GetAvanceClientesRutaAction;
use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleReposicionRutaAction
{
    private static array $cacheDiasHabiles = [];

    public function __construct(
        private readonly GetAvanceClientesRutaAction $avanceClientesRutaAction
    ) {}

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
     * Obtiene la auditoría analítica del KPI 6 (Pedido de Reposición Semanal)
     * utilizando exactamente la misma acción y lógica oficial de Cobertura Core de Avance.
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
            $cal = $this->calcularDiasHabiles($year, $month, $fechaNorm);
            $diasTotales = $cal['dias_totales'];
            $diasOperados = $cal['dias_transcurridos'];
            $diasRestantes = $cal['dias_restantes'];
            $semanasHabilesRestantes = max(1, (int) ceil($diasRestantes / 6.0));

            // 2. Preventas del día de la ruta
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
                        'objetivo_mes_ruta' => 0.0,
                        'cuota_semanal_ruta' => 0.0,
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

            // 3. Ejecutar exactamente la Acción Oficial de Avance Cobertura Core para la ruta
            $avanceRuta = $this->avanceClientesRutaAction->execute(
                ruta: $rutaClean,
                kpiClave: 'cob_core',
                fechaInicio: null,
                fechaCorte: $fechaNorm,
                omitirUnitarios3m: false,
                mesesHistorico: 3,
                frecuenciaMinima: 1,
                cantidadMinima: 4
            );

            $sugeridosPorClienteMap = $avanceRuta['sugeridos_por_cliente'] ?? [];
            $clientesAvance = collect($avanceRuta['clientes'] ?? [])->keyBy('cliente_id');
            $objetivoMesRuta = (float) ($avanceRuta['resumen']['objetivo_mensual_ruta'] ?? 0.0);
            $cuotaSemanalRuta = $semanasHabilesRestantes > 0 ? round($objetivoMesRuta / $semanasHabilesRestantes, 2) : 0.0;

            // 4. Evaluar Reposición Tienda por Tienda con los productos sugeridos de Avance Cobertura Core
            $tiendas = [];
            $totalSkusEsperados = 0;
            $totalSkusMatch = 0;
            $totalSkusFueraCanasta = 0;
            $totalPuntosEsperadosRuta = 0;
            $totalPuntosMatchRuta = 0;
            $tiendasEvaluablesCount = 0;
            $tiendasNuevasCount = 0;

            foreach ($clientesCompradores as $cid => $cData) {
                $pedidoHoy = $cData['pedido_hoy_map'];
                $keysHoy = $cData['keys_hoy'];

                $cliAvance = $clientesAvance[$cid] ?? null;
                $cuotaMensualCli = (float) ($cliAvance['cuota_objetivo'] ?? 150.0);
                $cuotaSemanalCli = $semanasHabilesRestantes > 0
                    ? round($cuotaMensualCli / $semanasHabilesRestantes, 2)
                    : $cuotaMensualCli;
                $cuotaSemanalCli = max(10.0, $cuotaSemanalCli);

                // Productos sugeridos oficiales de Cobertura Core de Avance
                $sugList = $sugeridosPorClienteMap[$cid] ?? [];

                $canastaBase = [];
                $keysUsadasHoy = [];
                $matchesCliente = 0;
                $puntosEsperadosTienda = 0;
                $puntosMatchTienda = 0;
                $montoMatchTienda = 0.0;
                $subtotalSugeridoSemanal = 0.0;

                foreach ($sugList as $sItem) {
                    $cod = trim((string) ($sItem['codigo_producto'] ?? ($sItem['codigo'] ?? ($sItem['pData']['codigo'] ?? ''))));
                    $nom = trim((string) ($sItem['producto'] ?? ($sItem['pData']['producto'] ?? '')));
                    $nomNorm = $this->normalizar($nom);
                    $cat = trim((string) ($sItem['categoria'] ?? ($sItem['pData']['categoria'] ?? 'GENERAL')));

                    // Match contra pedido de hoy
                    $keyMatch = null;
                    if ($cod !== '' && isset($keysHoy["cod_{$cod}"])) {
                        $keyMatch = $keysHoy["cod_{$cod}"];
                    } elseif ($nomNorm !== '' && isset($keysHoy["nom_{$nomNorm}"])) {
                        $keyMatch = $keysHoy["nom_{$nomNorm}"];
                    }

                    $esMatch = ($keyMatch !== null && isset($pedidoHoy[$keyMatch]));
                    $unidadesHoy = $esMatch ? (int) $pedidoHoy[$keyMatch]['cantidad'] : 0;
                    $montoHoy = $esMatch ? round((float) $pedidoHoy[$keyMatch]['monto'], 2) : 0.0;

                    $freqCompra = max(1, (int) ($sItem['frecuencia_compra'] ?? 1));
                    $pesoFi = $freqCompra;
                    $puntosEsperadosTienda += $pesoFi;

                    $cantRepoSemanal = (int) ($sItem['cant_sugerida'] ?? 1);
                    $precioUnit = (float) ($sItem['precio'] ?? 1.0);
                    $montoRepoSemanal = (float) ($sItem['monto_sugerido'] ?? round($cantRepoSemanal * $precioUnit, 2));
                    $subtotalSugeridoSemanal = round($subtotalSugeridoSemanal + $montoRepoSemanal, 2);

                    if ($esMatch) {
                        $matchesCliente++;
                        $keysUsadasHoy[$keyMatch] = true;
                        $puntosMatchTienda += $pesoFi;
                        $montoMatchTienda += min($montoHoy, $montoRepoSemanal);
                    }

                    $tipoOrigen = $sItem['tipo_origen'] ?? (!empty($sItem['es_core_pareto']) ? 'PARETO_RUTA' : 'HABITO_CLIENTE');

                    $canastaBase[] = [
                        'producto_id' => 0,
                        'codigo_producto' => $cod,
                        'producto' => $nom,
                        'descripcion' => $nom,
                        'categoria' => $cat !== '' ? $cat : 'GENERAL',
                        'tipo_origen' => $tipoOrigen,
                        'peso_fi' => $pesoFi,
                        'frecuencia_compra' => $freqCompra,
                        'unidades_acumuladas' => (int) ($sItem['cantidad_3m'] ?? 0),
                        'dias_con_compra' => $freqCompra,
                        'semanas_transcurridas' => $semanas,
                        'semanas_habiles_restantes' => $semanasHabilesRestantes,
                        'precio_unitario' => round($precioUnit, 2),
                        'mix_propio_pct' => round((float) ($sItem['mix_propio'] ?? 0.0), 1),
                        'presupuesto_teorico' => $montoRepoSemanal,
                        'cantidad_repo' => $cantRepoSemanal,
                        'cant_sugerida' => $cantRepoSemanal,
                        'monto_sugerido' => $montoRepoSemanal,
                        'monto_acumulado_bs' => round((float) ($sItem['total_bs_3m'] ?? 0.0), 2),
                        'unidades_hoy' => $unidadesHoy,
                        'monto_hoy' => $montoHoy,
                        'recomprado_hoy' => $esMatch,
                        'estatus' => $esMatch ? 'MATCH_SKU' : 'OMITIDO',
                        'historial_compras' => [],
                    ];
                }

                // Ordenar canasta: primero recomprados, luego peso f_i desc
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
                    'es_cliente_nuevo' => $esClienteNuevo,
                    'tiene_base_historica' => !$esClienteNuevo,
                    'puntos_esperados' => $puntosEsperadosTienda,
                    'puntos_match' => $puntosMatchTienda,
                    'skus_esperados' => $cntEsperados,
                    'total_skus_esperados' => $cntEsperados,
                    'skus_con_match' => $cntMatch,
                    'total_skus_match' => $cntMatch,
                    'skus_sin_match' => max(0, $cntEsperados - $cntMatch),
                    'skus_fuera_canasta_count' => $cntFuera,
                    'total_skus_fuera' => $cntFuera,
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
                $puntosRuta = 0.0;
                $cumpleUmbral = false;
                $estatusRuta = 'DÉFICIT';
            } else {
                $puntosRuta = 15.0;
                $cumpleUmbral = true;
                $estatusRuta = 'CUMPLIDO';
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
                    'compradores_hoy' => count($clientesCompradores),
                    'compradores_evaluables' => $tiendasEvaluablesCount,
                    'compradores_nuevos' => $tiendasNuevasCount,
                    'puntos_esperados_total' => $totalPuntosEsperadosRuta,
                    'puntos_match_total' => $totalPuntosMatchRuta,
                    'skus_esperados_total' => $totalSkusEsperados,
                    'skus_con_match_total' => $totalSkusMatch,
                    'skus_fuera_canasta_total' => $totalSkusFueraCanasta,
                    'porcentaje_reposicion_ruta' => $pctReposicionRuta,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $puntosRuta,
                    'estatus_ruta' => $estatusRuta,
                ],
                'tiendas' => $tiendas,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleReposicionRutaAction para {$ruta}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'configuracion' => [
                    'semanas_historia' => $semanas,
                    'dias_historia' => $diasHistoria,
                    'dias_totales' => 25,
                    'dias_transcurridos' => 1,
                    'dias_restantes' => 24,
                    'semanas_habiles_restantes' => 4,
                    'objetivo_mes_ruta' => 0.0,
                    'cuota_semanal_ruta' => 0.0,
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
    }
}
