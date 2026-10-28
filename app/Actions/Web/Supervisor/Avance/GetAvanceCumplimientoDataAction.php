<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetAvanceCumplimientoDataAction
{
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
    ];

    public function __construct(
        private readonly GetAvanceInitDataAction $initAction
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
        string $modoTemporal = 'acumulado'
    ): array {
        $modoTemporal = in_array(strtolower($modoTemporal), ['acumulado', 'diario'], true) ? strtolower($modoTemporal) : 'acumulado';

        // 1. Fechas y Días Hábiles
        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        $mesYm = $carbonCorte->format('Y-m');
        $infoDias = $this->initAction->calcularDiasHabiles($mesYm, $fechaCorte);
        $dRest = max(1, $infoDias['restantes']);
        $dTrans = max(1, $infoDias['transcurridos']);
        $dTot = max(1, $infoDias['totales']);
        $dTransEval = max(1, $dTrans - 1);
        $dRestCuota = max(1, $dRest - 1);

        // 2. Metas de las Rutas (Cartera pan_ruteo, fact_metas, fact_metas_kpi)
        $metasTodas = $this->obtenerMetasKpis($mesYm);

        // 3. Catálogo Base de Rutas y Canales (dim_rutas)
        $rutasDimRaw = DB::connection('supervisor')->table('dim_rutas')
            ->select([
                DB::raw("TRIM(UPPER(ruta)) as ruta_clean"),
                DB::raw("COALESCE(TRIM(canal), 'SIN CANAL') as canal"),
                DB::raw("COALESCE(TRIM(vendedor), 'No Asignado') as vendedor"),
            ])
            ->get();

        $rutasCatalogo = [];
        foreach ($rutasDimRaw as $rd) {
            $rutasCatalogo[$rd->ruta_clean] = [
                'canal' => $rd->canal,
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
                DB::raw("COALESCE(NULLIF(TRIM(UPPER(canal)), ''), 'SIN CANAL') as canal_factura"),
                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                'cliente_id',
                DB::raw("TRIM(codigo) as codigo"),
                DB::raw("LOWER(TRIM(COALESCE(producto, ''))) as producto"),
                DB::raw("LOWER(TRIM(COALESCE(jerarquia3, ''))) as jerarquia3"),
                DB::raw("UPPER(TRIM(COALESCE(categoria, ''))) as categoria"),
                DB::raw("COALESCE(monto_final, 0.0) as monto"),
                DB::raw("COALESCE(cantidad, 0) as cantidad"),
                DB::raw("COALESCE(TRIM(vendedor), 'Sin Asignar') as vendedor_factura"),
            ]);

        $ventasRaw = $ventasQuery->get();

        // Enriquecer catálogo dinámico con rutas y canales reales presentes en las ventas
        $ventasRutaCount = [];
        $canalesPorRuta = [];
        foreach ($ventasRaw as $v) {
            $rc = $v->ruta_clean;
            $ventasRutaCount[$rc] = ($ventasRutaCount[$rc] ?? 0) + 1;

            $cFact = strtoupper(trim((string) ($v->canal_factura ?? '')));
            $vFact = trim((string) ($v->vendedor_factura ?? ''));

            if ($cFact !== '' && $cFact !== 'SIN CANAL') {
                $canalesPorRuta[$rc][$cFact] = ($canalesPorRuta[$rc][$cFact] ?? 0) + 1;
            }

            if (!isset($rutasCatalogo[$rc])) {
                $rutasCatalogo[$rc] = [
                    'canal' => ($cFact !== '' && $cFact !== 'SIN CANAL') ? $cFact : 'SIN CANAL',
                    'vendedor' => ($vFact !== '' && $vFact !== 'Sin Asignar') ? $vFact : ($rc === 'SIN RUTA' ? 'Venta Oficina / Sin Asignar' : 'No Asignado'),
                ];
            } else {
                if (($rutasCatalogo[$rc]['canal'] === 'SIN CANAL' || $rutasCatalogo[$rc]['canal'] === '') && $cFact !== '' && $cFact !== 'SIN CANAL') {
                    $rutasCatalogo[$rc]['canal'] = $cFact;
                }
                if (($rutasCatalogo[$rc]['vendedor'] === 'No Asignado' || $rutasCatalogo[$rc]['vendedor'] === 'Sin Asignar') && $vFact !== '' && $vFact !== 'Sin Asignar') {
                    $rutasCatalogo[$rc]['vendedor'] = $vFact;
                }
            }
        }

        // Si una ruta tenía canal sin definir, asignar el canal más frecuente en sus facturas
        foreach ($canalesPorRuta as $rc => $cCounts) {
            if ($rutasCatalogo[$rc]['canal'] === 'SIN CANAL') {
                arsort($cCounts);
                $rutasCatalogo[$rc]['canal'] = (string) array_key_first($cCounts);
            }
        }

        // Asegurar rutas presentes en metas oficiales
        foreach ($metasTodas as $rName => $_) {
            if (!isset($rutasCatalogo[$rName])) {
                $rutasCatalogo[$rName] = [
                    'canal' => 'SIN CANAL',
                    'vendedor' => 'No Asignado',
                ];
            }
        }

        // 5. Definición del Universo de Rutas con Filtros Transparentes
        $canalesFiltroSet = array_flip(array_map('strtoupper', array_map('trim', array_filter($canales, fn ($c) => $c && !in_array(strtoupper(trim($c)), ['TODOS', 'TODAS'], true)))));
        $rutasFiltroSet = array_flip(array_map('strtoupper', array_map('trim', array_filter($rutas, fn ($r) => $r && !in_array(strtoupper(trim($r)), ['TODAS', 'TODOS'], true)))));

        $universo = [];
        foreach ($rutasCatalogo as $rName => $rMeta) {
            $rNameUpper = strtoupper(trim($rName));
            $rCanalUpper = strtoupper(trim((string) ($rMeta['canal'] ?? '')));

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
                // Coincidencia directa por el canal asignado a la ruta
                if (isset($canalesFiltroSet[$rCanalUpper])) {
                    $universo[$rName] = true;
                    continue;
                }

                // Si la ruta tuvo facturas registradas bajo el canal filtrado
                if (isset($canalesPorRuta[$rName])) {
                    $tieneCanal = false;
                    foreach ($canalesPorRuta[$rName] as $cNombre => $cnt) {
                        if (isset($canalesFiltroSet[strtoupper(trim($cNombre))])) {
                            $tieneCanal = true;
                            break;
                        }
                    }
                    if ($tieneCanal) {
                        $universo[$rName] = true;
                        continue;
                    }
                }

                // No cumple con el filtro de canal
                continue;
            }

            // Sin filtro de canal: se incluyen todas las rutas activas
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

        $kpisOrden = array_column(self::KPIS_LISTA, 'id');
        $kpisMap = [];
        foreach (self::KPIS_LISTA as $k) {
            $kpisMap[$k['id']] = $k;
        }

        foreach ($universoRutas as $r) {
            $acumVol[$r] = ['vol_general' => 0.0, 'vol_seda' => 0.0, 'vol_clinical' => 0.0];
            $acumCob[$r] = ['cob_general' => [], 'cob_seda' => [], 'cob_clinical' => []];
            $comprasCob[$r] = ['cob_general' => [], 'cob_seda' => [], 'cob_clinical' => []];
            $acumVolDia[$r] = ['vol_general' => 0.0, 'vol_seda' => 0.0, 'vol_clinical' => 0.0];
            $acumCobDia[$r] = ['cob_general' => [], 'cob_seda' => [], 'cob_clinical' => []];
        }

        foreach ($ventasRaw as $v) {
            $targetRuta = $v->ruta_clean;
            if (!isset($universo[$targetRuta])) {
                continue;
            }

            // Si hay filtro de canales activo, validar que la transacción individual corresponda al canal
            if (!empty($canalesFiltroSet)) {
                $cFactUpper = strtoupper(trim((string) ($v->canal_factura ?? '')));
                $rCanalUpper = strtoupper(trim((string) ($rutasCatalogo[$targetRuta]['canal'] ?? '')));
                $canalCoincide = ($cFactUpper !== '' && $cFactUpper !== 'SIN CANAL')
                    ? isset($canalesFiltroSet[$cFactUpper])
                    : isset($canalesFiltroSet[$rCanalUpper]);

                if (!$canalCoincide) {
                    continue;
                }
            }

            $cid = (int) ($v->cliente_id ?: 0);
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
                        $comprasCob[$targetRuta][$kId][$cid][] = [$fVStr, $monto];
                        if ($esDiaCorte) {
                            $acumCobDia[$targetRuta][$kId][$cid] = true;
                        }
                    }
                }
            }
        }

        // Lista consolidada de canales disponibles en el catálogo y ventas
        $canalesSet = [];
        foreach ($rutasCatalogo as $r => $meta) {
            $c = trim((string) ($meta['canal'] ?? ''));
            if ($c !== '' && $c !== 'SIN CANAL') {
                $canalesSet[$c] = true;
            }
        }
        foreach ($canalesPorRuta as $r => $cMap) {
            foreach ($cMap as $cNombre => $cnt) {
                if ($cNombre !== '' && $cNombre !== 'SIN CANAL') {
                    $canalesSet[$cNombre] = true;
                }
            }
        }
        $canalesDisponibles = array_merge(['TODOS'], array_keys($canalesSet));
        sort($canalesDisponibles);

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
                'metadata_kpis' => $this->obtenerMetadataKpis(),
            ];
        }

        // 8. VISTA MONOGRÁFICA POR KPI
        $clave = isset($kpisMap[$kpiClave]) ? $kpiClave : 'vol_general';
        $objKpi = $kpisMap[$clave];
        $esCobertura = ($objKpi['tipo'] === 'COBERTURA');

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
            ];
        }

        usort($filas, fn ($a, $b) => $b['pct'] <=> $a['pct']);

        $totCuotaCalc = ($esCobertura && !$infoDias['cerrado'] && $dRest > 0)
            ? (int) ceil($totBrecha / $dRest)
            : round($totCuota, 2);

        $totProyCalc = ($dTrans > 0) ? round(($totLogrado / $dTrans) * $dTot, 2) : 0.0;
        $totPct = $this->calcPct($totLogrado, $totMeta);
        $totalVentaCobGlobal = $totVApertura + $totVRepeticion;

        $totales = [
            'meta' => round($totMeta, 2),
            'logrado' => round($totLogrado, 2),
            'brecha' => round($totBrecha, 2),
            'cuota_dia' => $totCuotaCalc,
            'cuota_diaria_req' => $totCuotaCalc,
            'pct' => $totPct,
            'pct_avance_mtd' => $totPct,
            'proyeccion_cierre' => $totProyCalc,
            'venta_apertura' => round($totVApertura, 2),
            'venta_cobertura' => round($totVApertura, 2),
            'venta_repeticion' => round($totVRepeticion, 2),
            'clientes_repeticion' => $totCRepeticion,
            'pct_apertura' => ($totalVentaCobGlobal > 0) ? round(($totVApertura / $totalVentaCobGlobal) * 100.0, 1) : 0.0,
            'pct_repeticion' => ($totalVentaCobGlobal > 0) ? round(($totVRepeticion / $totalVentaCobGlobal) * 100.0, 1) : 0.0,
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
            'vol_general', 'cob_general' => true,
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
            ];
        }

        return $metas;
    }

    private function construirPatronesMeta(int $year, int $month): array
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
        ];
    }
}

