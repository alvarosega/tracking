<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceGraficosAction
{
    private const DIAS_SEMANA_ABR = [
        1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue',
        5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
    ];

    /**
     * Construye las series temporales de avance de Volumen y Cobertura por ruta y consolidado.
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaCorte
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @return array<string, mixed>
     */
    public function execute(
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        array $canales = [],
        array $rutas = []
    ): array {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        // 1. Fechas del Rango
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')
                ->where('monto_final', '!=', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                })
                ->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }

        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        if ($fechaInicio > $fechaCorte) {
            $temp = $fechaInicio;
            $fechaInicio = $fechaCorte;
            $fechaCorte = $temp;
        }

        $mesYm = $carbonCorte->format('Y-m');

        // Limpieza de filtros
        $canalesFilterClean = array_values(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $canales)));
        $rutasFilterClean = array_values(array_filter(array_map(fn ($r) => strtoupper(trim((string) $r)), $rutas)));

        // Si se seleccionó "Ninguna" ruta o "Ningún" canal explícitamente, devolver estructura vacía
        if (in_array('__NINGUNA__', $rutasFilterClean, true) || in_array('__NINGUNO__', $canalesFilterClean, true)) {
            return [
                'fechas' => [],
                'dias_labels' => [],
                'total_dias' => 0,
                'rango' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_corte' => $fechaCorte,
                    'mes' => $mesYm,
                ],
                'rutas' => [],
                'consolidado' => [
                    'meta_volumen' => 0.0,
                    'logrado_volumen' => 0.0,
                    'pct_volumen' => 0.0,
                    'meta_cobertura' => 0,
                    'logrado_cobertura' => 0,
                    'pct_cobertura' => 0.0,
                    'series' => [
                        'volumen_diario' => [],
                        'volumen_acumulado' => [],
                        'cobertura_diaria' => [],
                        'cobertura_acumulada' => [],
                    ],
                ],
                'ritmo_ideal' => [
                    'volumen_acumulado' => [],
                    'cobertura_acumulada' => [],
                    'volumen_diario' => 0.0,
                    'cobertura_diaria' => 0.0,
                ],
            ];
        }

        // 2. Catálogo de Rutas desde dim_rutas
        $dimRutasRaw = DB::connection('supervisor')->table('dim_rutas')->get();
        $dimRutas = [];
        foreach ($dimRutasRaw as $dr) {
            $rKey = strtoupper(trim((string) $dr->ruta));
            $dimRutas[$rKey] = [
                'vendedor' => trim((string) ($dr->vendedor ?: 'No Asignado')),
                'canal' => trim((string) ($dr->canal ?: 'SC')),
            ];
        }

        // 3. Metas de Volumen desde fact_metas
        $partes = explode('-', $mesYm);
        $anio = (int) ($partes[0] ?? date('Y'));
        $mesNum = (int) ($partes[1] ?? date('m'));
        $nombresMeses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $nombreMes = $nombresMeses[$mesNum] ?? '';
        $patronesMes = [
            $mesYm,
            sprintf('%s-%02d', $anio, $mesNum),
            strtolower($nombreMes),
            sprintf('%s %s', strtolower($nombreMes), $anio),
            sprintf('%s-%s', strtolower($nombreMes), substr((string) $anio, -2)),
            sprintf('%s%s', strtolower($nombreMes), substr((string) $anio, -2)),
        ];

        $volGenRaw = DB::connection('supervisor')->table('fact_metas')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->whereIn(DB::raw("LOWER(TRIM(mes))"), $patronesMes)
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("MAX(CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(12, 2))) as meta_vol"),
            ])
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
            ->pluck('meta_vol', 'ruta_clean')
            ->toArray();

        // 4. Metas de Cobertura desde pan_ruteo (cartera activa)
        $carteraRaw = DB::connection('supervisor')->table('pan_ruteo')
            ->where('cliente_id', '>', 0)
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('estado')
                  ->orWhereIn(DB::raw("UPPER(TRIM(estado))"), ['ACTIVO', 'ACTIVA', 'VIGENTE']);
            })
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COUNT(DISTINCT cliente_id) as total_cartera"),
            ])
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
            ->pluck('total_cartera', 'ruta_clean')
            ->toArray();

        // Enriquecer catálogo dimRutas con rutas de metas y cartera si no existían
        foreach ($volGenRaw as $rKey => $mVol) {
            if (!isset($dimRutas[$rKey])) {
                $dimRutas[$rKey] = ['vendedor' => 'No Asignado', 'canal' => 'SC'];
            }
        }
        foreach ($carteraRaw as $rKey => $mCob) {
            if (!isset($dimRutas[$rKey])) {
                $dimRutas[$rKey] = ['vendedor' => 'No Asignado', 'canal' => 'SC'];
            }
        }

        // Filtro de canales activo
        $filtrarCanales = !empty($canalesFilterClean) && !in_array('TODOS', $canalesFilterClean, true);
        $filtrarRutas = !empty($rutasFilterClean) && !in_array('TODAS', $rutasFilterClean, true);

        // Universo de rutas aplicables
        $universoRutas = [];
        foreach ($dimRutas as $rKey => $info) {
            if ($rKey === 'SIN RUTA' || $rKey === '') {
                continue;
            }

            if ($filtrarRutas && !in_array($rKey, $rutasFilterClean, true)) {
                continue;
            }

            if ($filtrarCanales) {
                $canalUpper = strtoupper($info['canal'] ?? 'SC');
                if (!in_array($canalUpper, $canalesFilterClean, true)) {
                    continue;
                }
            }

            $universoRutas[$rKey] = $info;
        }

        // 5. Ventas en el rango desde fact_ventas
        $queryVentas = DB::connection('supervisor')->table('fact_ventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '!=', 0)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            })
            ->select([
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(ruta)), ''), 'SIN RUTA') as ruta"),
                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                'cliente_id',
                DB::raw("COALESCE(monto_final, 0.0) as monto"),
            ]);

        if ($filtrarRutas) {
            $queryVentas->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasFilterClean);
        }

        $ventas = $queryVentas->orderBy('fecha_norm', 'asc')->get();

        // 6. Extraer lista cronológica de fechas
        $fechasSet = [];
        foreach ($ventas as $v) {
            $f = (string) $v->fecha;
            if ($f !== '') {
                $fechasSet[$f] = true;
            }
        }
        $fechas = array_keys($fechasSet);
        sort($fechas);

        // Si no hay ventas, construir días hábiles del rango
        if (empty($fechas)) {
            $curr = Carbon::parse($fechaInicio);
            $end = Carbon::parse($fechaCorte);
            while ($curr->lte($end)) {
                if ($curr->dayOfWeekIso <= 6) {
                    $fechas[] = $curr->format('Y-m-d');
                }
                $curr->addDay();
            }
        }

        $diasLabels = [];
        foreach ($fechas as $f) {
            $cDate = Carbon::parse($f);
            $abrev = self::DIAS_SEMANA_ABR[$cDate->dayOfWeekIso] ?? 'D';
            $diasLabels[] = sprintf('%s %02d/%02d', $abrev, $cDate->day, $cDate->month);
        }

        $totalDiasHabiles = count($fechas);

        // 7. Agrupar ventas por ruta -> fecha
        $rutasVentas = [];
        foreach ($ventas as $v) {
            $rKey = (string) $v->ruta;
            if ($rKey === '' || $rKey === 'SIN RUTA') {
                continue;
            }

            // Asegurar que si apareció en ventas pero no en dim_rutas, se evalúe canal
            $canalRuta = $dimRutas[$rKey]['canal'] ?? 'SC';
            if ($filtrarCanales && !in_array(strtoupper($canalRuta), $canalesFilterClean, true)) {
                continue;
            }
            if ($filtrarRutas && !in_array($rKey, $rutasFilterClean, true)) {
                continue;
            }

            if (!isset($universoRutas[$rKey])) {
                $universoRutas[$rKey] = [
                    'vendedor' => $dimRutas[$rKey]['vendedor'] ?? 'No Asignado',
                    'canal' => $canalRuta,
                ];
            }

            $f = (string) $v->fecha;
            $cid = (int) $v->cliente_id;
            $m = (float) $v->monto;

            if (!isset($rutasVentas[$rKey])) {
                $rutasVentas[$rKey] = [
                    'dias' => [],
                ];
            }
            if (!isset($rutasVentas[$rKey]['dias'][$f])) {
                $rutasVentas[$rKey]['dias'][$f] = [
                    'volumen' => 0.0,
                    'clientes' => [],
                ];
            }
            $rutasVentas[$rKey]['dias'][$f]['volumen'] += $m;
            $rutasVentas[$rKey]['dias'][$f]['clientes'][$cid] = true;
        }

        // 8. Construir series por ruta para todas las rutas del universo
        $rutasSeries = [];
        $globalMetaVolumen = 0.0;
        $globalMetaCobertura = 0;
        $globalVolumenDiario = array_fill(0, $totalDiasHabiles, 0.0);
        $globalClientesDiarios = array_fill(0, $totalDiasHabiles, 0);
        $globalClientesVistosPorDia = [];

        for ($i = 0; $i < $totalDiasHabiles; $i++) {
            $globalClientesVistosPorDia[$i] = [];
        }

        foreach ($universoRutas as $rKey => $rInfo) {
            $metaVol = (float) ($volGenRaw[$rKey] ?? 0.0);
            $metaCob = (int) ($carteraRaw[$rKey] ?? 0);
            $rDias = $rutasVentas[$rKey]['dias'] ?? [];

            $volAcumulado = 0.0;
            $clientesVistos = [];

            $serieVolDiario = [];
            $serieVolAcum = [];
            $serieCobDiario = [];
            $serieCobAcum = [];

            foreach ($fechas as $idx => $f) {
                $volDia = round((float) ($rDias[$f]['volumen'] ?? 0.0), 2);
                $clisDia = count($rDias[$f]['clientes'] ?? []);

                if (isset($rDias[$f]['clientes'])) {
                    foreach (array_keys($rDias[$f]['clientes']) as $cid) {
                        $clientesVistos[$cid] = true;
                        for ($k = $idx; $k < $totalDiasHabiles; $k++) {
                            $globalClientesVistosPorDia[$k][$cid] = true;
                        }
                    }
                }

                $volAcumulado += $volDia;
                $cobAcumulada = count($clientesVistos);

                $serieVolDiario[] = $volDia;
                $serieVolAcum[] = round($volAcumulado, 2);
                $serieCobDiario[] = $clisDia;
                $serieCobAcum[] = $cobAcumulada;

                $globalVolumenDiario[$idx] += $volDia;
                $globalClientesDiarios[$idx] += $clisDia;
            }

            $pctAvanceVol = $metaVol > 0 ? round(($volAcumulado / $metaVol) * 100, 1) : 0.0;
            $pctAvanceCob = $metaCob > 0 ? round((count($clientesVistos) / $metaCob) * 100, 1) : 0.0;

            $rutasSeries[] = [
                'ruta' => $rKey,
                'vendedor' => $rInfo['vendedor'] ?? 'No Asignado',
                'canal' => $rInfo['canal'] ?? 'SC',
                'meta_volumen' => $metaVol,
                'logrado_volumen' => round($volAcumulado, 2),
                'pct_volumen' => $pctAvanceVol,
                'meta_cobertura' => $metaCob,
                'logrado_cobertura' => count($clientesVistos),
                'pct_cobertura' => $pctAvanceCob,
                'series' => [
                    'volumen_diario' => $serieVolDiario,
                    'volumen_acumulado' => $serieVolAcum,
                    'cobertura_diaria' => $serieCobDiario,
                    'cobertura_acumulada' => $serieCobAcum,
                ],
            ];

            $globalMetaVolumen += $metaVol;
            $globalMetaCobertura += $metaCob;
        }

        // Ordenar alfabéticamente por código de ruta
        usort($rutasSeries, fn ($a, $b) => strcmp($a['ruta'], $b['ruta']));

        // 9. Consolidado Global de Series
        $globalVolumenAcumulado = [];
        $globalCoberturaAcumulada = [];
        $volAcumGlobal = 0.0;

        for ($i = 0; $i < $totalDiasHabiles; $i++) {
            $vDia = round($globalVolumenDiario[$i], 2);
            $volAcumGlobal += $vDia;
            $globalVolumenAcumulado[] = round($volAcumGlobal, 2);
            $globalCoberturaAcumulada[] = count($globalClientesVistosPorDia[$i]);
        }

        $logradoVolumenGlobal = round($volAcumGlobal, 2);
        $logradoCoberturaGlobal = !empty($globalCoberturaAcumulada) ? end($globalCoberturaAcumulada) : 0;

        $pctVolumenGlobal = $globalMetaVolumen > 0 ? round(($logradoVolumenGlobal / $globalMetaVolumen) * 100, 1) : 0.0;
        $pctCoberturaGlobal = $globalMetaCobertura > 0 ? round(($logradoCoberturaGlobal / $globalMetaCobertura) * 100, 1) : 0.0;

        // 10. Línea de Ritmo Ideal (Lineal Progresivo de Meta por Día)
        $ritmoIdealVolumen = [];
        $ritmoIdealCobertura = [];
        $cuotaVolumenDiaria = $totalDiasHabiles > 0 ? $globalMetaVolumen / $totalDiasHabiles : 0.0;
        $cuotaCoberturaDiaria = $totalDiasHabiles > 0 ? $globalMetaCobertura / $totalDiasHabiles : 0.0;

        for ($i = 1; $i <= $totalDiasHabiles; $i++) {
            $ritmoIdealVolumen[] = round($cuotaVolumenDiaria * $i, 2);
            $ritmoIdealCobertura[] = (int) round($cuotaCoberturaDiaria * $i);
        }

        return [
            'fechas' => $fechas,
            'dias_labels' => $diasLabels,
            'total_dias' => $totalDiasHabiles,
            'rango' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_corte' => $fechaCorte,
                'mes' => $mesYm,
            ],
            'rutas' => $rutasSeries,
            'consolidado' => [
                'meta_volumen' => round($globalMetaVolumen, 2),
                'logrado_volumen' => $logradoVolumenGlobal,
                'pct_volumen' => $pctVolumenGlobal,
                'meta_cobertura' => $globalMetaCobertura,
                'logrado_cobertura' => $logradoCoberturaGlobal,
                'pct_cobertura' => $pctCoberturaGlobal,
                'series' => [
                    'volumen_diario' => array_map(fn ($v) => round($v, 2), $globalVolumenDiario),
                    'volumen_acumulado' => $globalVolumenAcumulado,
                    'cobertura_diaria' => $globalClientesDiarios,
                    'cobertura_acumulada' => $globalCoberturaAcumulada,
                ],
            ],
            'ritmo_ideal' => [
                'volumen_acumulado' => $ritmoIdealVolumen,
                'cobertura_acumulada' => $ritmoIdealCobertura,
                'volumen_diario' => round($cuotaVolumenDiaria, 2),
                'cobertura_diaria' => round($cuotaCoberturaDiaria, 1),
            ],
        ];
    }
}
