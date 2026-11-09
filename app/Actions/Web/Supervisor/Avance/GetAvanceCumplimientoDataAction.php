<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetAvanceCumplimientoDataAction
{
    use AvanceMetricHelpersTrait;
    private const CODIGOS_CLINICAL = [
        '64880313',
        '64819237',
        '64762998',
        '64880312',
        '64880351',
        '69785525',
        '69785524',
        '64795481',
    ];

    private const KPIS_LISTA = [
        ['id' => 'vol_general', 'nombre' => '1. Volumen General', 'corto' => 'Vol. General', 'tipo' => 'VOLUMEN', 'unidad' => 'Bs'],
        ['id' => 'cob_general', 'nombre' => '2. Cobertura 100%', 'corto' => 'Cob. General', 'tipo' => 'COBERTURA', 'unidad' => 'Cli'],
        ['id' => 'vol_seda', 'nombre' => '3. Vol. Toque de Seda', 'corto' => 'Vol. Seda', 'tipo' => 'VOLUMEN', 'unidad' => 'Bs'],
        ['id' => 'cob_seda', 'nombre' => '4. Cob. Toque de Seda', 'corto' => 'Cob. Seda', 'tipo' => 'COBERTURA', 'unidad' => 'Cli'],
        ['id' => 'vol_clinical', 'nombre' => '5. Vol. Clinical', 'corto' => 'Vol. Clinical', 'tipo' => 'VOLUMEN', 'unidad' => 'Bs'],
        ['id' => 'cob_clinical', 'nombre' => '6. Cob. Clinical', 'corto' => 'Cob. Clinical', 'tipo' => 'COBERTURA', 'unidad' => 'Cli'],
        ['id' => 'cob_core', 'nombre' => '7. Cobertura Core', 'corto' => 'Cob. Core', 'tipo' => 'COBERTURA', 'unidad' => 'Cli'],
        ['id' => 'pizarra_unilever', 'nombre' => '8. Pizarra Unilever', 'corto' => 'Pizarra Unilever', 'tipo' => 'SCORE', 'unidad' => 'Pts'],
    ];

    public function __construct(
        private readonly GetAvanceInitDataAction $initAction,
        private readonly GetAvanceClientesRutaAction $clientesRutaAction,
        private readonly ?GetAvancePizarraUnileverAction $pizarraAction = null
    ) {}

    /**
     * Procesa la matriz de cumplimiento de metas y KPIs para el período especificado.
     *
     * @param string $vista 'resumen' o 'kpi'
     * @param string $kpiClave Clave del KPI activo ('vol_general', 'cob_general', etc.)
     * @param string|null $fechaInicio YYYY-MM-DD
     * @param string|null $fechaCorte YYYY-MM-DD
     * @param array<int, string> $canales Canales seleccionados
     * @param array<int, string> $rutas Rutas seleccionadas
     * @param string $modoTemporal 'acumulado' o 'diario'
     * @return array<string, mixed>
     */
    public function execute(
        string $vista = 'resumen',
        string $kpiClave = 'vol_general',
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        array $canales = [],
        array $rutas = [],
        string $modoTemporal = 'acumulado',
        bool $omitirUnitarios3m = false,
        int $mesesHistorico = 3,
        int $frecuenciaMinima = 1,
        int $cantidadMinima = 1
    ): array {
        $modoTemporal = in_array(strtolower($modoTemporal), ['acumulado', 'diario'], true) ? strtolower($modoTemporal) : 'acumulado';
        $mesesHistorico = max(1, min(8, $mesesHistorico));
        $frecuenciaMinima = max(1, $frecuenciaMinima);
        $cantidadMinima = $cantidadMinima > 1 ? $cantidadMinima : ($omitirUnitarios3m ? 2 : ($kpiClave === 'cob_core' ? 4 : 1));

        @ini_set('memory_limit', '1024M');

        // 1. Fechas y Días Hábiles
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio || Carbon::parse($fechaInicio)->format('Y-m') !== $carbonCorte->format('Y-m')) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        $mesYm = $carbonCorte->format('Y-m');
        $mesesCerrados = $this->obtenerMesesCerrados($carbonCorte, $mesesHistorico);
        $infoDias = $this->initAction->calcularDiasHabiles($mesYm, $fechaCorte);
        $dRest = max(1, $infoDias['restantes']);
        $dTrans = max(1, $infoDias['transcurridos']);
        $dTot = max(1, $infoDias['totales']);
        $dTransEval = max(1, $dTrans - 1);
        $dRestCuota = max(1, $dRest - 1);

        // Si se solicita el KPI 8 (Pizarra Unilever), delegar inmediatamente a GetAvancePizarraUnileverAction
        if ($vista === 'kpi' && $kpiClave === 'pizarra_unilever') {
            $pizarra = $this->pizarraAction ?? app(GetAvancePizarraUnileverAction::class);
            $resultado = $pizarra->execute(
                fechaInicio: $fechaInicio,
                fechaCorte: $fechaCorte,
                canales: $canales,
                rutas: $rutas
            );
            $resultado['dias_calendario'] = $infoDias;
            $resultado['metadata_kpis'] = $this->obtenerMetadataKpis();
            return $resultado;
        }

        // 2. Metas de las Rutas (Cartera pan_ruteo, fact_metas, fact_metas_kpi)
        $metasTodas = $this->obtenerMetasKpis($mesYm);

        // Cartera Oficial Actual desde pan_ruteo
        // Se mapea cada cliente_id a su ruta actual oficial para que toda evaluación
        // en fact_ventas se realice por cliente_id y no por vendedor ni por ruta de la factura.
        $panRuteoRaw = DB::connection('supervisor')->table('pan_ruteo')
            ->where('cliente_id', '>', 0)
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('estado')
                  ->orWhereIn(DB::raw("UPPER(TRIM(estado))"), ['ACTIVO', 'ACTIVA', 'VIGENTE']);
            })
            ->select([
                'cliente_id',
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COALESCE(NULLIF(TRIM(vendedor), ''), 'No Asignado') as vendedor_ruteo"),
            ])
            ->get();

        $clienteRutaOficialMap = [];
        $carteraOficialRutaCount = [];
        $vendedorRuteoMap = [];
        $carteraClientesPorRuta = [];
        foreach ($panRuteoRaw as $pr) {
            $cid = (int) $pr->cliente_id;
            $rClean = (string) $pr->ruta_clean;
            if ($cid > 0 && $rClean !== '') {
                $clienteRutaOficialMap[$cid] = $rClean;
                $carteraOficialRutaCount[$rClean] = ($carteraOficialRutaCount[$rClean] ?? 0) + 1;
                $carteraClientesPorRuta[$rClean][$cid] = true;
                if (!isset($vendedorRuteoMap[$rClean]) && $pr->vendedor_ruteo !== 'No Asignado') {
                    $vendedorRuteoMap[$rClean] = $pr->vendedor_ruteo;
                }
            }
        }
        unset($panRuteoRaw);

        // 3. Catálogo Oficial de Rutas y Canales (dim_rutas)
        $rutasDimRaw = DB::connection('supervisor')->table('dim_rutas')
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(canal)), ''), 'SC') as canal"),
                DB::raw("COALESCE(NULLIF(TRIM(vendedor), ''), 'No Asignado') as vendedor"),
            ])
            ->get();

        $canalesValidosSet = ['SC' => true];
        $rutasCatalogo = [];
        foreach ($rutasDimRaw as $rd) {
            $c = $rd->canal;
            if (!is_numeric($c)) {
                $canalesValidosSet[$c] = true;
            } else {
                $c = 'SC';
            }
            $rutasCatalogo[$rd->ruta_clean] = [
                'canal' => $c,
                'vendedor' => $rd->vendedor,
            ];
        }

        // 4. Extracción de Ventas en el Rango (fact_ventas)
        $ventasQuery = DB::connection('supervisor')->table('fact_ventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '!=', 0)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            })
            ->select([
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(ruta)), ''), 'SIN RUTA') as ruta_clean"),
                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                'cliente_id',
                DB::raw("TRIM(codigo) as codigo"),
                DB::raw("LOWER(TRIM(COALESCE(producto, ''))) as producto"),
                DB::raw("LOWER(TRIM(COALESCE(jerarquia3, ''))) as jerarquia3"),
                DB::raw("UPPER(TRIM(COALESCE(categoria, ''))) as categoria"),
                DB::raw("COALESCE(monto_final, 0.0) as monto"),
                DB::raw("COALESCE(cantidad, 0) as cantidad"),
                DB::raw("COALESCE(NULLIF(TRIM(vendedor), ''), 'Sin Asignar') as vendedor_factura"),
            ]);

        $ventasRaw = $ventasQuery->get();

        // Enriquecer catálogo: cualquier ruta que NO esté en dim_rutas se asigna obligatoriamente al canal 'SC' (Sin Canal)
        $ventasRutaCount = [];
        foreach ($ventasRaw as $v) {
            $rc = $v->ruta_clean;
            $ventasRutaCount[$rc] = ($ventasRutaCount[$rc] ?? 0) + 1;

            if (!isset($rutasCatalogo[$rc])) {
                $vFact = $v->vendedor_factura;
                $rutasCatalogo[$rc] = [
                    'canal' => 'SC',
                    'vendedor' => ($vFact !== '' && $vFact !== 'Sin Asignar') ? $vFact : ($rc === 'SIN RUTA' ? 'Venta Oficina / Sin Asignar' : 'No Asignado'),
                ];
            } elseif ($rutasCatalogo[$rc]['vendedor'] === 'No Asignado' && $v->vendedor_factura !== 'Sin Asignar') {
                $rutasCatalogo[$rc]['vendedor'] = $v->vendedor_factura;
            }
        }

        // Asegurar rutas presentes en metas oficiales y en cartera pan_ruteo
        foreach ($metasTodas as $rName => $_) {
            if (!isset($rutasCatalogo[$rName])) {
                $rutasCatalogo[$rName] = [
                    'canal' => 'SC',
                    'vendedor' => 'No Asignado',
                ];
            }
        }
        foreach ($carteraOficialRutaCount as $rName => $cCount) {
            if (!isset($rutasCatalogo[$rName])) {
                $rutasCatalogo[$rName] = [
                    'canal' => 'SC',
                    'vendedor' => $vendedorRuteoMap[$rName] ?? 'No Asignado',
                ];
            }
        }

        // 5. Definición del Universo de Rutas con Filtros Transparentes
        $canalesFiltroSet = array_flip(array_map('strtoupper', array_map('trim', array_filter($canales, fn ($c) => $c && !in_array(strtoupper(trim($c)), ['TODOS', 'TODAS'], true)))));
        $rutasFiltroSet = array_flip(array_map('strtoupper', array_map('trim', array_filter($rutas, fn ($r) => $r && !in_array(strtoupper(trim($r)), ['TODAS', 'TODOS'], true)))));

        $universo = [];
        foreach ($rutasCatalogo as $rName => $rMeta) {
            $rNameUpper = strtoupper(trim($rName));
            $rCanalUpper = strtoupper(trim((string) ($rMeta['canal'] ?? 'SC')));

            // Si es 'SIN RUTA' y no hubo ventas sin ruta en este período, no incluirla
            if ($rName === 'SIN RUTA' && !isset($ventasRutaCount['SIN RUTA'])) {
                continue;
            }

            // 1. Filtro puntual de Rutas (si el usuario seleccionó rutas en el selector)
            if (!empty($rutasFiltroSet) && !isset($rutasFiltroSet[$rNameUpper])) {
                continue;
            }

            // 2. Filtro de Canales (si el usuario seleccionó uno o más canales)
            if (!empty($canalesFiltroSet)) {
                if (!isset($canalesFiltroSet[$rCanalUpper])) {
                    continue;
                }
            }

            // Cumple los filtros: incluir en el universo
            $universo[$rName] = true;
        }

        $universoRutas = array_keys($universo);
        sort($universoRutas);

        // 6. Acumuladores de Métricas MTD y Diarias por Ruta
        $acumVol = [];
        $acumCob = [];
        $comprasCob = [];
        $acumVolDia = [];
        $acumCobDia = [];

        $kpisComerciales = array_values(array_filter(self::KPIS_LISTA, fn ($k) => $k['tipo'] !== 'SCORE'));
        $kpisOrden = array_column($kpisComerciales, 'id');
        $kpisMap = [];
        foreach (self::KPIS_LISTA as $k) {
            $kpisMap[$k['id']] = $k;
        }

        foreach ($universoRutas as $r) {
            $acumVol[$r] = [];
            $acumCob[$r] = [];
            $comprasCob[$r] = [];
            $acumVolDia[$r] = [];
            $acumCobDia[$r] = [];
            foreach ($kpisComerciales as $k) {
                if ($k['tipo'] === 'VOLUMEN') {
                    $acumVol[$r][$k['id']] = 0.0;
                    $acumVolDia[$r][$k['id']] = 0.0;
                } else {
                    $acumCob[$r][$k['id']] = [];
                    $comprasCob[$r][$k['id']] = [];
                    $acumCobDia[$r][$k['id']] = [];
                }
            }
        }

        $necesitaComprasCob = ($vista === 'kpi' && in_array($kpiClave, ['cob_general', 'cob_seda', 'cob_clinical', 'cob_core'], true));

        foreach ($ventasRaw as $v) {
            $cid = (int) ($v->cliente_id ?: 0);
            $rc = (string) $v->ruta_clean;
            if ($cid > 0 && $rc !== '') {
                $carteraClientesPorRuta[$rc][$cid] = true;
            }

            // La ruta oficial se determina por el cliente (según su asignación actual en pan_ruteo).
            // Si el cliente no existe en pan_ruteo, se usa la ruta de la factura como fallback.
            $targetRuta = ($cid > 0 && isset($clienteRutaOficialMap[$cid]))
                ? $clienteRutaOficialMap[$cid]
                : $rc;

            if (!isset($universo[$targetRuta])) {
                continue;
            }
            $monto = (float) $v->monto;
            $prod = (string) $v->producto;
            $j3 = (string) $v->jerarquia3;
            $cod = (string) $v->codigo;
            $cat = (string) $v->categoria;
            $fVStr = substr((string) $v->fecha, 0, 10);
            $esDiaCorte = ($fVStr === $fechaCorte);

            // Evaluación por cada KPI
            foreach ($kpisOrden as $kId) {
                if ($this->aplicaTransaccion($kId, $prod, $j3, $cod, $cat)) {
                    $tipo = $kpisMap[$kId]['tipo'];
                    if ($tipo === 'VOLUMEN') {
                        $acumVol[$targetRuta][$kId] += $monto;
                        if ($esDiaCorte) {
                            $acumVolDia[$targetRuta][$kId] += $monto;
                        }
                    } elseif ($cid > 0) {
                        $acumCob[$targetRuta][$kId][$cid] = true;
                        if ($necesitaComprasCob && $kId === $kpiClave) {
                            $comprasCob[$targetRuta][$kId][$cid][] = [$fVStr, $monto];
                        }
                        if ($esDiaCorte) {
                            $acumCobDia[$targetRuta][$kId][$cid] = true;
                        }
                    }
                }
            }
        }
        unset($ventasRaw);

        // Canales oficiales disponibles limpios (sin números)
        $canalesValidosDisponibles = array_keys($canalesValidosSet);
        sort($canalesValidosDisponibles);
        $canalesDisponibles = array_merge(['TODOS'], $canalesValidosDisponibles);

        // 7. VISTA RESUMEN MULTI-KPI
        if ($vista === 'resumen') {
            $matrizRutas = [];
            $totalesCol = [];
            foreach ($kpisOrden as $kId) {
                $totalesCol[$kId] = ['meta' => 0.0, 'logrado' => 0.0];
            }

            foreach ($universoRutas as $ruta) {
                $infoDim = $rutasCatalogo[$ruta] ?? ['canal' => 'SIN CANAL', 'vendedor' => 'No Asignado'];
                $metasR = $metasTodas[$ruta] ?? [];
                $kpisInfo = [];
                $pctList = [];

                foreach ($kpisOrden as $kId) {
                    $kObj = $kpisMap[$kId];
                    $esVol = ($kObj['tipo'] === 'VOLUMEN');
                    $metaMes = (float) ($metasR[$kId] ?? 0.0);
                    $metaDia = ($esVol && $dTot > 0)
                        ? round($metaMes / $dTot, 2)
                        : (($metaMes > 0 && $dTot > 0) ? (int) ceil($metaMes / $dTot) : 0);

                    $logradoMtd = $esVol ? (float) $acumVol[$ruta][$kId] : (float) count($acumCob[$ruta][$kId]);
                    $logradoDia = $esVol ? (float) $acumVolDia[$ruta][$kId] : (float) count($acumCobDia[$ruta][$kId]);

                    if ($modoTemporal === 'diario') {
                        $logradoVal = $logradoDia;
                        $metaVal = $metaDia;
                        $metaCorte = $metaDia;
                        $pct = $this->calcPct($logradoVal, $metaVal);
                        $pctRitmo = $pct;
                    } else {
                        $logradoVal = $logradoMtd;
                        $metaVal = $metaMes;
                        if ($esVol) {
                            $metaCorte = round(($metaVal / $dTot) * $dTransEval, 2);
                            $pctRitmo = $this->calcPct($logradoVal, $metaCorte);
                        } else {
                            $metaCorte = $metaVal;
                            $pctRitmo = $this->calcPct($logradoVal, $metaVal);
                        }
                        $pct = $this->calcPct($logradoVal, $metaVal);
                    }

                    $kpisInfo[$kId] = [
                        'logrado' => $esVol ? round($logradoVal, 2) : (int) $logradoVal,
                        'meta' => $esVol ? round((float) $metaVal, 2) : (int) $metaVal,
                        'meta_corte' => $esVol ? round((float) $metaCorte, 2) : (int) $metaCorte,
                        'pct' => $pct,
                        'pct_ritmo' => $pctRitmo,
                        'logrado_dia' => $esVol ? round($logradoDia, 2) : (int) $logradoDia,
                        'meta_dia' => $metaDia,
                        'logrado_mtd' => $esVol ? round($logradoMtd, 2) : (int) $logradoMtd,
                        'meta_mtd' => $esVol ? round($metaMes, 2) : (int) $metaMes,
                    ];

                    if ($metaVal > 0) {
                        $pctList[] = $pctRitmo;
                    }

                    $totalesCol[$kId]['meta'] += (float) $metaVal;
                    $totalesCol[$kId]['logrado'] += $logradoVal;
                }

                $scoreProm = !empty($pctList) ? round(array_sum($pctList) / count($pctList), 1) : 0.0;
                $matrizRutas[] = [
                    'ruta' => $ruta,
                    'canal' => $infoDim['canal'],
                    'vendedor' => $infoDim['vendedor'],
                    'kpis' => $kpisInfo,
                    'score_promedio' => $scoreProm,
                ];
            }

            usort($matrizRutas, fn ($a, $b) => $b['score_promedio'] <=> $a['score_promedio']);

            foreach ($kpisOrden as $kId) {
                $kObj = $kpisMap[$kId];
                $esVol = ($kObj['tipo'] === 'VOLUMEN');
                $m = $totalesCol[$kId]['meta'];
                $l = $totalesCol[$kId]['logrado'];
                $mc = $esVol ? round(($m / $dTot) * $dTransEval, 2) : $m;
                $totalesCol[$kId]['meta_corte'] = $mc;
                $totalesCol[$kId]['pct'] = $this->calcPct($l, $m);
                $totalesCol[$kId]['pct_ritmo'] = $this->calcPct($l, $mc);
            }

            $resumenColumnas = [];
            foreach ($kpisOrden as $kId) {
                $resumenColumnas[$kId] = $totalesCol[$kId]['pct'];
            }

            return [
                'modo' => 'resumen',
                'modo_temporal' => $modoTemporal,
                'fecha_inicio' => $fechaInicio,
                'fecha_corte' => $fechaCorte,
                'dias_calendario' => $infoDias,
                'canales' => $canalesDisponibles,
                'canales_disponibles' => $canalesDisponibles,
                'resumen_columnas' => $resumenColumnas,
                'totales_columnas' => $totalesCol,
                'rutas' => $matrizRutas,
                'meses_cerrados' => $mesesCerrados,
                'metadata_kpis' => $this->obtenerMetadataKpis(),
            ];
        }

        // 8. VISTA MONOGRÁFICA POR KPI
        $clave = isset($kpisMap[$kpiClave]) ? $kpiClave : 'vol_general';
        $objKpi = $kpisMap[$clave];
        $esCobertura = ($objKpi['tipo'] === 'COBERTURA');

        // Histórico de N meses cerrados por ruta y cálculo de Sugerido por Ruta
        $histRutasMap = [];
        $sugeridoRutasMap = [];
        $ventaMtdRutasMap = [];
        if ($clave === 'cob_core') {
            foreach ($universoRutas as $r) {
                $detRuta = $this->clientesRutaAction->execute(
                    ruta: $r,
                    kpiClave: 'cob_core',
                    fechaInicio: $fechaInicio,
                    fechaCorte: $fechaCorte,
                    omitirUnitarios3m: $omitirUnitarios3m,
                    mesesHistorico: $mesesHistorico,
                    frecuenciaMinima: $frecuenciaMinima,
                    cantidadMinima: $cantidadMinima
                );

                $sugeridoRutasMap[$r] = (float) ($detRuta['totales']['total_sugerido'] ?? 0.0);
                $ventaMtdRutasMap[$r] = (float) ($detRuta['totales']['venta_mtd'] ?? 0.0);

                if (!empty($mesesCerrados)) {
                    $histRutasMap[$r][$mesesCerrados[0]['ym']] = (float) ($detRuta['totales']['mes_1'] ?? 0.0);
                    if (isset($mesesCerrados[1])) {
                        $histRutasMap[$r][$mesesCerrados[1]['ym']] = (float) ($detRuta['totales']['mes_2'] ?? 0.0);
                    }
                    if (isset($mesesCerrados[2])) {
                        $histRutasMap[$r][$mesesCerrados[2]['ym']] = (float) ($detRuta['totales']['mes_3'] ?? 0.0);
                    }
                }
                unset($detRuta);
            }
        }

        $filas = [];
        $totMeta = 0.0;
        $totLogrado = 0.0;
        $totBrecha = 0.0;
        $totCuota = 0.0;
        $totVApertura = 0.0;
        $totVRepeticion = 0.0;
        $totCRepeticion = 0;

        foreach ($universoRutas as $ruta) {
            $infoDim = $rutasCatalogo[$ruta] ?? ['canal' => 'SIN CANAL', 'vendedor' => 'No Asignado'];
            $metaMes = round((float) ($metasTodas[$ruta][$clave] ?? 0.0), 2);
            $metaDia = (!$esCobertura && $dTot > 0)
                ? round($metaMes / $dTot, 2)
                : (($metaMes > 0 && $dTot > 0) ? (int) ceil($metaMes / $dTot) : 0);

            $logradoMtd = !$esCobertura ? round($acumVol[$ruta][$clave], 2) : count($acumCob[$ruta][$clave]);
            $logradoDia = !$esCobertura ? round($acumVolDia[$ruta][$clave], 2) : count($acumCobDia[$ruta][$clave]);

            $carteraRuta = (int) ($metasTodas[$ruta]['cob_general'] ?? 0);

            if ($modoTemporal === 'diario') {
                $meta = $metaDia;
                $logrado = $logradoDia;
                $brecha = round(max(0.0, (float) $meta - (float) $logrado), 2);
                $cuota = $metaDia;
                $pctAv = $this->calcPct((float) $logrado, (float) $meta);
                $proy = !$esCobertura ? round($logrado * $dTot, 2) : (int) round($logrado * $dTot);
                $pctProy = $this->calcPct((float) $proy, $metaMes);
            } else {
                $meta = $metaMes;
                $logrado = $logradoMtd;
                $brecha = round(max(0.0, (float) $meta - (float) $logrado), 2);
                if (!$infoDias['cerrado'] && $dRest > 0) {
                    $cuota = $esCobertura ? (int) ceil($brecha / $dRestCuota) : round($brecha / $dRestCuota, 2);
                } else {
                    $cuota = $esCobertura ? 0 : 0.0;
                }
                $pctAv = $this->calcPct((float) $logrado, (float) $meta);
                $proy = ($dTrans > 0) ? round(($logrado / $dTrans) * $dTot, 2) : 0.0;
                $pctProy = $this->calcPct((float) $proy, (float) $meta);
            }

            // Para Cobertura Core: histórico de meses cerrados, objetivo mensual asignado y sugerido de hábitos
            $mesesBsRuta = [];
            $m1Bs = 0.0;
            $m2Bs = 0.0;
            $m3Bs = 0.0;
            $total3mBs = 0.0;
            $objetivoMesRuta = 0.0;
            $sugeridoRuta = 0.0;
            if ($clave === 'cob_core') {
                foreach ($mesesCerrados as $mObj) {
                    $ym = $mObj['ym'];
                    $mesesBsRuta[$ym] = round($histRutasMap[$ruta][$ym] ?? 0.0, 2);
                }
                $m1Bs = round($mesesBsRuta[$mesesCerrados[0]['ym'] ?? ''] ?? 0.0, 2);
                $m2Bs = round($mesesBsRuta[$mesesCerrados[1]['ym'] ?? ''] ?? 0.0, 2);
                $m3Bs = round($mesesBsRuta[$mesesCerrados[2]['ym'] ?? ''] ?? 0.0, 2);
                $total3mBs = round(array_sum($mesesBsRuta), 2);
                $pctAv = ($carteraRuta > 0) ? round(($logrado / $carteraRuta) * 100.0, 1) : 0.0;
                $objetivoMesRuta = $this->obtenerMetaMesRuta($ruta, $carbonCorte->format('Y-m'));
                $sugeridoRuta = round($sugeridoRutasMap[$ruta] ?? 0.0, 2);
                $meta = $objetivoMesRuta;
                $brecha = round(max(0.0, $objetivoMesRuta - (float) ($acumVol[$ruta]['vol_general'] ?? 0.0)), 2);
            }

            // Desglose de Esfuerzo Útil: Venta de Apertura vs Venta de Repetición (Coberturas)
            $vApertura = 0.0;
            $vRepeticion = 0.0;
            $cRepeticion = 0;

            if ($esCobertura) {
                $cobDict = $comprasCob[$ruta][$clave] ?? [];
                foreach ($cobDict as $cid => $cList) {
                    if (empty($cList)) {
                        continue;
                    }
                    usort($cList, fn ($a, $b) => strcmp($a[0], $b[0]));
                    $fPrimera = $cList[0][0];

                    if ($modoTemporal === 'diario') {
                        $mDia = 0.0;
                        foreach ($cList as $item) {
                            if ($item[0] === $fechaCorte) {
                                $mDia += $item[1];
                            }
                        }
                        if ($mDia > 0) {
                            if ($fPrimera === $fechaCorte) {
                                $vApertura += $mDia;
                            } else {
                                $vRepeticion += $mDia;
                                $cRepeticion++;
                            }
                        }
                    } else {
                        $mAp = 0.0;
                        $mRep = 0.0;
                        foreach ($cList as $item) {
                            if ($item[0] === $fPrimera) {
                                $mAp += $item[1];
                            } else {
                                $mRep += $item[1];
                            }
                        }
                        $vApertura += $mAp;
                        $vRepeticion += $mRep;
                        if ($mRep > 0) {
                            $cRepeticion++;
                        }
                    }
                }
                $totVApertura += $vApertura;
                $totVRepeticion += $vRepeticion;
                $totCRepeticion += $cRepeticion;
            }

            $vTotalCob = $vApertura + $vRepeticion;
            $pctAp = ($vTotalCob > 0) ? round(($vApertura / $vTotalCob) * 100.0, 1) : 0.0;
            $pctRep = ($vTotalCob > 0) ? round(($vRepeticion / $vTotalCob) * 100.0, 1) : 0.0;

            $totMeta += (float) $meta;
            $totLogrado += (float) $logrado;
            $totBrecha += $brecha;
            $totCuota += (float) $cuota;

            $filas[] = [
                'ruta' => $ruta,
                'canal' => $infoDim['canal'],
                'vendedor' => $infoDim['vendedor'],
                'meta' => $meta,
                'meta_dia' => $metaDia,
                'meta_mes' => $metaMes,
                'logrado_mtd' => $logradoMtd,
                'logrado_dia' => $logradoDia,
                'logrado' => $logrado,
                'brecha_mes' => $brecha,
                'brecha' => $brecha,
                'cuota_diaria_req' => $cuota,
                'cuota_dia' => $cuota,
                'pct_avance_mtd' => $pctAv,
                'pct' => $pctAv,
                'proyeccion_cierre' => $proy,
                'pct_proyeccion' => $pctProy,
                'venta_apertura' => round($vApertura, 2),
                'venta_cobertura' => round($vApertura, 2),
                'venta_repeticion' => round($vRepeticion, 2),
                'clientes_repeticion' => $cRepeticion,
                'pct_apertura' => $pctAp,
                'pct_repeticion' => $pctRep,
                'cartera' => $carteraRuta,
                'meses_bs' => $mesesBsRuta,
                'mes_1_bs' => $m1Bs,
                'mes_2_bs' => $m2Bs,
                'mes_3_bs' => $m3Bs,
                'total_3m_bs' => $total3mBs,
                'total_hist_bs' => $total3mBs,
                'total_sugerido' => $sugeridoRuta,
                'objetivo_mes' => $objetivoMesRuta,
                'venta_mtd' => $clave === 'cob_core' ? ($ventaMtdRutasMap[$ruta] ?? 0.0) : round($acumVol[$ruta]['vol_general'] ?? 0.0, 2),
                'sin_meta' => false,
            ];
        }

        if ($clave === 'cob_core') {
            usort($filas, fn ($a, $b) => $b['total_3m_bs'] <=> $a['total_3m_bs']);
        } else {
            usort($filas, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        }

        $totCuotaCalc = ($esCobertura && !$infoDias['cerrado'] && $dRest > 0)
            ? (int) ceil($totBrecha / $dRest)
            : round($totCuota, 2);

        $totProyCalc = ($dTrans > 0) ? round(($totLogrado / $dTrans) * $dTot, 2) : 0.0;
        $totPct = $this->calcPct($totLogrado, $totMeta);
        $totalVentaCobGlobal = $totVApertura + $totVRepeticion;

        $totMesesBs = [];
        foreach ($mesesCerrados as $mObj) {
            $ym = $mObj['ym'];
            $totMesesBs[$ym] = round(array_sum(array_map(fn ($f) => (float) ($f['meses_bs'][$ym] ?? 0.0), $filas)), 2);
        }

        $totM1 = array_sum(array_column($filas, 'mes_1_bs'));
        $totM2 = array_sum(array_column($filas, 'mes_2_bs'));
        $totM3 = array_sum(array_column($filas, 'mes_3_bs'));
        $tot3m = !empty($totMesesBs) ? array_sum($totMesesBs) : array_sum(array_column($filas, 'total_3m_bs'));
        $totCartera = array_sum(array_column($filas, 'cartera'));
        $totSugerido = array_sum(array_column($filas, 'total_sugerido'));
        $totObjetivoMes = array_sum(array_column($filas, 'objetivo_mes'));
        $totVentaMtd = array_sum(array_column($filas, 'venta_mtd'));

        $totales = [
            'meta' => round($totMeta, 2),
            'logrado' => round($totLogrado, 2),
            'cartera' => $totCartera,
            'brecha' => round($totBrecha, 2),
            'cuota_dia' => $totCuotaCalc,
            'cuota_diaria_req' => $totCuotaCalc,
            'pct' => ($clave === 'cob_core' && $totCartera > 0) ? round(($totLogrado / $totCartera) * 100.0, 1) : $totPct,
            'pct_avance_mtd' => $totPct,
            'proyeccion_cierre' => $totProyCalc,
            'venta_apertura' => round($totVApertura, 2),
            'venta_cobertura' => round($totVApertura, 2),
            'venta_repeticion' => round($totVRepeticion, 2),
            'clientes_repeticion' => $totCRepeticion,
            'pct_apertura' => ($totalVentaCobGlobal > 0) ? round(($totVApertura / $totalVentaCobGlobal) * 100.0, 1) : 0.0,
            'pct_repeticion' => ($totalVentaCobGlobal > 0) ? round(($totVRepeticion / $totalVentaCobGlobal) * 100.0, 1) : 0.0,
            'meses_bs' => $totMesesBs,
            'mes_1_bs' => round($totM1, 2),
            'mes_2_bs' => round($totM2, 2),
            'mes_3_bs' => round($totM3, 2),
            'total_3m_bs' => round($tot3m, 2),
            'total_hist_bs' => round($tot3m, 2),
            'total_sugerido' => round($totSugerido, 2),
            'objetivo_mes' => round($totObjetivoMes, 2),
            'venta_mtd' => round($totVentaMtd, 2),
        ];

        return [
            'modo' => 'kpi',
            'kpi' => $clave,
            'kpi_nombre' => $objKpi['nombre'],
            'kpi_tipo' => $objKpi['tipo'],
            'kpi_unidad' => $objKpi['unidad'],
            'modo_temporal' => $modoTemporal,
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'dias_calendario' => $infoDias,
            'canales' => $canalesDisponibles,
            'canales_disponibles' => $canalesDisponibles,
            'totales' => $totales,
            'rutas' => $filas,
            'meses_historico' => $mesesHistorico,
            'frecuencia_minima' => $frecuenciaMinima,
            'cantidad_minima' => $cantidadMinima,
            'meses_cerrados' => $mesesCerrados,
            'metadata_kpis' => $this->obtenerMetadataKpis(),
        ];
    }

    /**
     * Evalúa si una transacción en fact_ventas califica para un KPI específico.
     */
    public function aplicaTransaccion(string $kpiClave, string $producto, string $j3, string $codigo, string $categoria): bool
    {
        $prod = mb_strtolower(trim($producto));
        $cat = mb_strtolower(trim($j3));
        $categoriastr = mb_strtolower(trim($categoria));
        $cod = trim($codigo);

        return match ($kpiClave) {
            'vol_general', 'cob_general', 'cob_core' => true,
            'vol_seda', 'cob_seda' => (
                str_contains($prod, 'toque de seda') ||
                str_contains($prod, 'toque seda') ||
                str_contains($cat, 'toque de seda') ||
                str_contains($categoriastr, 'toque de seda') ||
                str_contains($prod, 'cr balance') ||
                str_contains($prod, 'crema balance') ||
                str_contains($prod, 'cr. balance') ||
                str_contains($prod, 'cr.balance') ||
                str_contains($cat, 'cr balance') ||
                str_contains($cat, 'crema balance') ||
                str_contains($categoriastr, 'cr balance') ||
                str_contains($categoriastr, 'crema balance')
            ),
            'vol_clinical', 'cob_clinical' => (
                in_array($cod, self::CODIGOS_CLINICAL, true) ||
                str_contains($prod, 'clinical')
            ),
            default => true,
        };
    }

    /**
     * Obtiene las metas oficiales de todas las rutas (pan_ruteo, fact_metas, fact_metas_kpi).
     *
     * @return array<string, array<string, float|int>>
     */
    private function obtenerMetasKpis(string $mesYm): array
    {
        $metas = [];
        $partes = explode('-', $mesYm);
        $anio = (int) ($partes[0] ?? date('Y'));
        $mes = (int) ($partes[1] ?? date('m'));

        $patrones = $this->construirPatronesMeta($anio, $mes);

        // 1. Cartera Activa de pan_ruteo (Meta de Cobertura General)
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

        // 2. Meta Volumen General en fact_metas
        $volGenRaw = DB::connection('supervisor')->table('fact_metas')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->whereIn(DB::raw("LOWER(TRIM(mes))"), $patrones)
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("MAX(CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(12, 2))) as meta_vol"),
            ])
            ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
            ->pluck('meta_vol', 'ruta_clean')
            ->toArray();

        // 3. Metas de fact_metas_kpi
        $kpisEspRaw = DB::connection('supervisor')->table('fact_metas_kpi')
            ->where(function ($q) use ($mesYm, $patrones): void {
                $q->where('mes', $mesYm)
                  ->orWhereIn(DB::raw("LOWER(TRIM(mes))"), $patrones);
            })
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COALESCE(vol_seda, 0.0) as vol_seda"),
                DB::raw("COALESCE(cob_seda, 0) as cob_seda"),
                DB::raw("COALESCE(vol_clinical, 0.0) as vol_clinical"),
                DB::raw("COALESCE(cob_clinical, 0) as cob_clinical"),
            ])
            ->get()
            ->keyBy('ruta_clean');

        $universoRutas = array_unique(array_merge(
            array_keys($carteraRaw),
            array_keys($volGenRaw),
            $kpisEspRaw->keys()->toArray()
        ));

        foreach ($universoRutas as $ruta) {
            $esp = $kpisEspRaw->get($ruta);
            $metas[$ruta] = [
                'vol_general' => (float) ($volGenRaw[$ruta] ?? 0.0),
                'cob_general' => (int) ($carteraRaw[$ruta] ?? 0),
                'vol_seda' => (float) ($esp ? $esp->vol_seda : 0.0),
                'cob_seda' => (int) ($esp ? $esp->cob_seda : 0),
                'vol_clinical' => (float) ($esp ? $esp->vol_clinical : 0.0),
                'cob_clinical' => (int) ($esp ? $esp->cob_clinical : 0),
                'cob_core' => 0,
            ];
        }

        return $metas;
    }

    /**
     * Calcula los últimos N meses cerrados (por defecto 3, configurable de 1 a 8) previos a la fecha de corte evaluada.
     * Evalúa dinámicamente según la fecha de corte seleccionada (consultando fact_ventas o fallback calendario).
     *
     * @return array<int, array{ym: string, nombre: string, corto: string, inicio: string, fin: string}>
     */
    public function obtenerMesesCerrados(Carbon $carbonRef, int $numMeses = 3): array
    {
        $numMeses = max(1, min(8, $numMeses));
        $mesesEs = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mesesAbr = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $inicioMesCorte = $carbonRef->copy()->startOfMonth()->format('Y-m-d');

        // 1. Obtener los últimos N meses cerrados que tienen ventas registradas en fact_ventas
        $mesesYm = [];
        try {
            $mesesYm = DB::connection('supervisor')->table('fact_ventas')
                ->whereNotNull('fecha_norm')
                ->where('fecha_norm', '<', $inicioMesCorte)
                ->where('fecha_norm', '!=', '1900-01-01')
                ->where('monto_final', '!=', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                })
                ->selectRaw("DISTINCT DATE_FORMAT(fecha_norm, '%Y-%m') as ym")
                ->orderBy('ym', 'desc')
                ->limit($numMeses)
                ->pluck('ym')
                ->toArray();
        } catch (\Throwable $e) {
            $mesesYm = [];
        }

        // 2. Si hay menos de N meses en la base de datos (o la BD no está disponible), completar con meses calendario
        if (count($mesesYm) < $numMeses) {
            $mesRef = $carbonRef->copy()->startOfMonth();
            for ($i = 1; $i <= $numMeses; $i++) {
                $ym = $mesRef->copy()->subMonthsNoOverflow($i)->format('Y-m');
                if (!in_array($ym, $mesesYm, true)) {
                    $mesesYm[] = $ym;
                }
                if (count($mesesYm) >= $numMeses) {
                    break;
                }
            }
        }

        // 3. Ordenar cronológicamente (el más antiguo primero)
        sort($mesesYm);
        if (count($mesesYm) > $numMeses) {
            $mesesYm = array_slice($mesesYm, -$numMeses);
        }

        $meses = [];
        foreach ($mesesYm as $ym) {
            $dt = Carbon::parse($ym . '-01');
            $mNum = (int) $dt->format('m');
            $anio = $dt->format('Y');
            $meses[] = [
                'ym' => $ym,
                'nombre' => ($mesesEs[$mNum] ?? '') . ' ' . $anio,
                'corto' => ($mesesAbr[$mNum] ?? '') . ' ' . substr($anio, -2),
                'inicio' => $dt->copy()->startOfMonth()->format('Y-m-d'),
                'fin' => $dt->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }

        return $meses;
    }

    /**
     * Obtiene el objetivo oficial de venta mensual de una ruta en fact_metas para un mes dado (YYYY-MM).
     */
    public function obtenerMetaMesRuta(string $ruta, string $mesYm): float
    {
        $partes = explode('-', $mesYm);
        $anio = (int) ($partes[0] ?? date('Y'));
        $mes = (int) ($partes[1] ?? date('m'));
        $patrones = $this->construirPatronesMeta($anio, $mes);
        $rutaClean = strtoupper(trim($ruta));

        $val = DB::connection('supervisor')->table('fact_metas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->whereIn(DB::raw("LOWER(TRIM(mes))"), $patrones)
            ->max(DB::raw("CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(12, 2))"));

        return round((float) ($val ?? 0.0), 2);
    }

    public function construirPatronesMeta(int $year, int $month): array
    {
        $mesesEspAbr = [
            1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
        ];
        $mesesNom = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];

        $yy = substr((string) $year, -2);
        $abr = $mesesEspAbr[$month] ?? '';
        $mes2d = sprintf('%02d', $month);
        $nom = $mesesNom[$month - 1] ?? '';

        return [
            "{$nom} {$year}", "{$nom}_{$year}", "{$year}-{$mes2d}", "{$year}-{$mes2d}-01",
            "{$abr}-{$yy}", "{$abr}.-{$yy}", "{$abr}_{$yy}",
            $nom, $abr, $mes2d, (string) $month,
        ];
    }

    private function calcPct(float $num, float $den): float
    {
        return ($den > 0) ? round(($num / $den) * 100.0, 1) : 0.0;
    }

    private function obtenerMetadataKpis(): array
    {
        return [
            [
                'id' => 'vol_general',
                'clave' => 'vol_general',
                'nombre' => '1. Volumen General',
                'tipo' => 'VOLUMEN',
                'unidad' => 'Bs',
                'campo_meta' => 'fact_metas.objetivo_de_venta',
                'objetivo_comercial' => 'Garantizar la meta troncal de ventas. Es el gatillo llave para la remuneración.',
                'criterio_productos' => 'Todas las referencias y líneas vendidas (sin filtro de producto).',
            ],
            [
                'id' => 'cob_general',
                'clave' => 'cob_general',
                'nombre' => '2. Cobertura 100%',
                'tipo' => 'COBERTURA',
                'unidad' => 'Cli',
                'campo_meta' => 'pan_ruteo (Cartera Activa)',
                'objetivo_comercial' => 'Asegurar que cada cliente en plan realice al menos una compra en el mes.',
                'criterio_productos' => 'Cualquier producto del portafolio con factura válida.',
            ],
            [
                'id' => 'vol_seda',
                'clave' => 'vol_seda',
                'nombre' => '3. Vol. Toque de Seda',
                'tipo' => 'VOLUMEN',
                'unidad' => 'Bs',
                'campo_meta' => 'fact_metas_kpi.vol_seda',
                'objetivo_comercial' => 'Impulsar el portafolio estratégico de cuidado personal de alto margen.',
                'criterio_productos' => 'Sedal Toque de Seda y Crema Balance en todas sus presentaciones.',
            ],
            [
                'id' => 'cob_seda',
                'clave' => 'cob_seda',
                'nombre' => '4. Cob. Toque de Seda',
                'tipo' => 'COBERTURA',
                'unidad' => 'Cli',
                'campo_meta' => 'fact_metas_kpi.cob_seda',
                'objetivo_comercial' => 'Asegurar presencia física de Toque de Seda y Crema Balance en el PDV.',
                'criterio_productos' => 'Clientes con compra en Sedal Toque de Seda o Crema Balance.',
            ],
            [
                'id' => 'vol_clinical',
                'clave' => 'vol_clinical',
                'nombre' => '5. Vol. Clinical',
                'tipo' => 'VOLUMEN',
                'unidad' => 'Bs',
                'campo_meta' => 'fact_metas_kpi.vol_clinical',
                'objetivo_comercial' => 'Desarrollar el segmento premium de desodorantes Dove Clinical.',
                'criterio_productos' => 'Línea Dove Clinical (códigos 64880313, 64819237, 64762998, 64880312, 64880351, etc.).',
            ],
            [
                'id' => 'cob_clinical',
                'clave' => 'cob_clinical',
                'nombre' => '6. Cob. Clinical',
                'tipo' => 'COBERTURA',
                'unidad' => 'Cli',
                'campo_meta' => 'fact_metas_kpi.cob_clinical',
                'objetivo_comercial' => 'Garantizar distribución numérica de Dove Clinical.',
                'criterio_productos' => 'Clientes únicos con compra en Dove Clinical.',
            ],
            [
                'id' => 'cob_core',
                'clave' => 'cob_core',
                'nombre' => '7. Cobertura Core',
                'tipo' => 'COBERTURA',
                'unidad' => 'Cli',
                'campo_meta' => 'Histórico 3 Meses Cerrados',
                'objetivo_comercial' => 'Análisis de recurrencia y cobertura histórica en base al comportamiento de los últimos 3 meses cerrados.',
                'criterio_productos' => 'Historial de compras en fact_ventas de los clientes de la ruta en los 3 meses cerrados previos y su peso relativo.',
            ],
            [
                'id' => 'pizarra_unilever',
                'clave' => 'pizarra_unilever',
                'nombre' => '8. Pizarra Unilever',
                'tipo' => 'SCORE',
                'unidad' => 'Pts',
                'campo_meta' => 'Pizarra Modelo Unilever (8 Pilares)',
                'objetivo_comercial' => 'Desempeño acumulado diario MTD sobre días hábiles transcurridos según el modelo de excelencia comercial Unilever.',
                'criterio_productos' => 'Acumulado y promedio MTD por pilar: Horario (10), Visitas (5), Objetivo (10), Efectividad (30), Compra Mínima (5), Reposición (15), Ampliación (20) y Covers (5).',
            ],
        ];
    }

    /**
     * ALGORITMO AsignarCuotasMensualesPorCliente (Waterfilling con Piso de 150 Bs)
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

