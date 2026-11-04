<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetCumplimientoDataAction
{
    private const UMBRAL_HORARIO_SEGUNDOS = 29400; // 08:10:00 (8*3600 + 10*60)
    private const UMBRAL_EFECTIVIDAD_PCT = 55.0;
    private const UMBRAL_COMPRA_MINIMA_BS = 70.0;
    private const UMBRAL_REPOSICION_PCT = 20.0;
    private const PISO_REPOSICION_PCT = 20.0;
    private const UMBRAL_AMPLIACION_PCT = 30.0;
    private const UMBRAL_COVERS_PCT = 50.0;
    private const EVALUAR_KPI6_REPOSICION = true; // KPI 6: Pedido de Reposición (Canasta Habitual 60% · 15 pts) activado
    private const EVALUAR_KPI7_AMPLIACION = true; // KPI 7: Ampliación de Portafolio (>= 30% · 20 pts) activado
    private const EVALUAR_KPI8_COVERS = true; // KPI 8: Covers (Descuento >= 50% · 5 pts) activado

    public function __construct(
        private readonly GetCatalogoRutasAction $catalogoAction
    ) {}

    /**
     * Motor analítico de disciplina operativa y ejecución comercial de la fuerza de ventas.
     * Fundamentado en el Modelo Unilever DSD (Criterio Binario "Todo o Nada" sobre 100 Puntos Base).
     *
     * @param string $fecha
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param bool $estrictoHorario
     * @return array<string, mixed>
     */
    public function execute(
        string $fecha,
        array $canales = [],
        array $rutas = [],
        bool $estrictoHorario = false,
        int $semanasHistoria = 13
    ): array {
        // 1. Obtener catálogo y filtrar rutas objetivo
        $catalogoData = $this->catalogoAction->execute();
        $catalogoRutas = $catalogoData['catalogo']; // Colección de objetos ruta con vendedor, canal, etc.

        $rutasFiltradas = $rutas;
        if (!empty($canales)) {
            $rutasPorCanal = $catalogoRutas->whereIn('canal', $canales)->pluck('ruta')->toArray();
            $rutasFiltradas = !empty($rutas)
                ? array_values(array_intersect($rutas, $rutasPorCanal))
                : $rutasPorCanal;
        }

        if (empty($rutasFiltradas)) {
            $rutasFiltradas = $catalogoRutas->pluck('ruta')->toArray();
        }

        // Mapeo rápido de datos de ruta por código
        $metaRutas = $catalogoRutas->keyBy('ruta');

        // 2. Cálculo del día de la semana para pan_ruteo
        $carbonFecha = Carbon::parse($fecha);
        $diasMap = [
            1 => ['lunes', 'Lunes'],
            2 => ['martes', 'Martes'],
            3 => ['miercoles', 'miércoles', 'Miercoles', 'Miércoles'],
            4 => ['jueves', 'Jueves'],
            5 => ['viernes', 'Viernes'],
            6 => ['sabado', 'sábado', 'Sabado', 'Sábado'],
            7 => ['domingo', 'Domingo'],
        ];
        $diaSemana = $diasMap[$carbonFecha->dayOfWeekIso][1] ?? 'Lunes';
        $diasFiltro = $diasMap[$carbonFecha->dayOfWeekIso] ?? ['lunes', 'Lunes'];

        // 3. Extracción de Datos Heterogéneos

        // A. pan_ruteo: Cartera oficial activa asignada al día de visita
        $carteraRaw = collect();
        try {
            $carteraRaw = DB::connection('supervisor')->table('pan_ruteo as pr')
                ->whereIn(DB::raw("TRIM(UPPER(pr.ruta))"), array_map('strtoupper', array_map('trim', $rutasFiltradas)))
                ->where(function ($q) use ($diasFiltro): void {
                    $q->whereIn('pr.dia_norm', $diasFiltro)
                      ->orWhereIn('pr.dia', $diasFiltro);
                })
                ->where('pr.cliente_id', '>', 0)
                ->whereRaw("TRIM(UPPER(COALESCE(pr.estado, ''))) = 'ACTIVO'")
                ->select([
                    'pr.id',
                    'pr.ruta',
                    'pr.cliente_id',
                    'pr.vendedor',
                    'pr.vendedor_norm',
                    DB::raw("COALESCE(NULLIF(pr.cliente, ''), pr.cliente_norm, CONCAT('Cliente #', pr.cliente_id)) as cliente"),
                    DB::raw("COALESCE(pr.cliente_norm, pr.cliente) as cliente_norm"),
                    'pr.estado',
                    'pr.dia_norm',
                    'pr.direccion',
                    'pr.tipo_negocio',
                    'pr.latitud',
                    'pr.longitud',
                ])
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Error consultando pan_ruteo en Cumplimiento: ' . $e->getMessage());
        }

        $carteraPorRuta = $carteraRaw->groupBy(fn ($item) => strtoupper(trim((string) $item->ruta)));

        // Mapeo de cliente a ruta oficial desde pan_ruteo (cruce por cliente, agrupado por ruta, sin tomar en cuenta vendedor)
        // Prioridad 1: Clientes programados para HOY en la cartera de cada ruta
        $clienteARutaNorm = [];
        $clienteARutaAlfa = [];

        foreach ($carteraRaw as $cRow) {
            $r = strtoupper(trim((string) $cRow->ruta));
            $n1 = $this->normalizarTexto((string) $cRow->cliente);
            $n2 = $this->normalizarTexto((string) $cRow->cliente_norm);
            $a1 = $this->normalizarAlfanumerico((string) $cRow->cliente);
            $a2 = $this->normalizarAlfanumerico((string) $cRow->cliente_norm);
            if ($n1 !== '') $clienteARutaNorm[$n1] = $r;
            if ($n2 !== '') $clienteARutaNorm[$n2] = $r;
            if ($a1 !== '') $clienteARutaAlfa[$a1] = $r;
            if ($a2 !== '') $clienteARutaAlfa[$a2] = $r;
        }

        // Prioridad 2: Otros días de la semana para clientes que no estén programados hoy
        try {
            $clientesRutasTodas = DB::connection('supervisor')->table('pan_ruteo')
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), array_map('strtoupper', array_map('trim', $rutasFiltradas)))
                ->where('cliente_id', '>', 0)
                ->whereRaw("TRIM(UPPER(COALESCE(estado, ''))) = 'ACTIVO'")
                ->select(['cliente', 'cliente_norm', 'ruta'])
                ->get();

            foreach ($clientesRutasTodas as $cRow) {
                $r = strtoupper(trim((string) $cRow->ruta));
                $n1 = $this->normalizarTexto((string) $cRow->cliente);
                $n2 = $this->normalizarTexto((string) $cRow->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $cRow->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $cRow->cliente_norm);
                if ($n1 !== '' && !isset($clienteARutaNorm[$n1])) $clienteARutaNorm[$n1] = $r;
                if ($n2 !== '' && !isset($clienteARutaNorm[$n2])) $clienteARutaNorm[$n2] = $r;
                if ($a1 !== '' && !isset($clienteARutaAlfa[$a1])) $clienteARutaAlfa[$a1] = $r;
                if ($a2 !== '' && !isset($clienteARutaAlfa[$a2])) $clienteARutaAlfa[$a2] = $r;
            }
        } catch (\Throwable $e) {
            Log::debug('Error precargando rutas de clientes en Cumplimiento: ' . $e->getMessage());
        }

        // B. fact_visitas: Marcajes del sistema central (web ERP)
        $factVisitasRaw = collect();
        try {
            $rawVisitas = DB::connection('supervisor')->table('fact_visitas')
                ->where('fecha_norm', $fecha)
                ->orderBy('check_in', 'asc')
                ->get();

            $rutasTargetSet = array_flip(array_map('strtoupper', array_map('trim', $rutasFiltradas)));

            $factVisitasRaw = $rawVisitas->map(function ($row) use ($clienteARutaNorm, $clienteARutaAlfa) {
                $n1 = $this->normalizarTexto((string) $row->cliente);
                $n2 = $this->normalizarTexto((string) $row->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $row->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $row->cliente_norm);

                $row->ruta = $clienteARutaNorm[$n1]
                    ?? $clienteARutaNorm[$n2]
                    ?? $clienteARutaAlfa[$a1]
                    ?? $clienteARutaAlfa[$a2]
                    ?? null;
                return $row;
            })->filter(function ($row) use ($rutasTargetSet) {
                $r = strtoupper(trim((string) ($row->ruta ?? '')));
                return $r !== '' && isset($rutasTargetSet[$r]);
            });
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_visitas en Cumplimiento: ' . $e->getMessage());
        }

        $factVisitasPorRuta = $factVisitasRaw->groupBy(fn ($item) => strtoupper(trim((string) $item->ruta)));

        // C. visitas (Local MySQL): Marcajes móviles desde la app Android
        $visitasMovilRaw = collect();
        try {
            $visitasMovilRaw = DB::table('visitas as v')
                ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
                ->whereDate('v.visited_at', $fecha)
                ->whereIn('v.route', $rutasFiltradas)
                ->where('v.is_opportunity', 0)
                ->select([
                    'v.id',
                    'v.client_id',
                    'v.route',
                    'v.status',
                    'v.comments',
                    'v.visited_at',
                    'v.latitude',
                    'v.longitude',
                    'v.photo_path',
                    'u.username as vendedor_movil',
                ])
                ->orderBy('v.visited_at', 'asc')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Error consultando visitas móviles en Cumplimiento: ' . $e->getMessage());
        }

        $visitasMovilPorRuta = $visitasMovilRaw->groupBy('route');

        // D. fact_preventas: Pedidos transaccionados intra-día en tiempo real
        $preventasRaw = collect();
        try {
            $preventasRaw = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fecha)
                ->whereIn('ruta', $rutasFiltradas)
                ->select([
                    'id',
                    'nro_preventa',
                    'ruta',
                    'cliente_id',
                    'codigo_cliente',
                    'cliente',
                    'vendedor',
                    'producto_id',
                    'codigo_producto',
                    'producto',
                    'categoria',
                    'cantidad',
                    'precio_lista',
                    'descuento',
                    'monto_final',
                ])
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_preventas en Cumplimiento: ' . $e->getMessage());
        }

        $preventasPorRuta = $preventasRaw->groupBy('ruta');

        // E. fact_metas: Cuota volumétrica mensual asignada por ruta
        $metasPorRuta = collect();
        try {
            $mes = (int) $carbonFecha->month;
            $anio = (int) $carbonFecha->year;

            try {
                $metasPorRuta = DB::connection('supervisor')->table('fact_metas')
                    ->whereIn('ruta', $rutasFiltradas)
                    ->where('mes', $mes)
                    ->where('anio', $anio)
                    ->get()
                    ->keyBy('ruta');
            } catch (\Throwable) {
                $metasPorRuta = DB::connection('supervisor')->table('fact_metas')
                    ->whereIn('ruta', $rutasFiltradas)
                    ->get()
                    ->keyBy('ruta');
            }
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_metas en Cumplimiento: ' . $e->getMessage());
        }

        // F. fact_ventas: Facturación cerrada acumulada en el mes (para cálculo de Brecha y Cuota Diaria)
        $facturadoPrevioPorRuta = collect();
        try {
            $inicioMes = $carbonFecha->copy()->startOfMonth()->toDateString();
            $facturadoPrevioPorRuta = DB::connection('supervisor')->table('fact_ventas')
                ->whereIn('ruta', $rutasFiltradas)
                ->where('fecha_norm', '>=', $inicioMes)
                ->where('fecha_norm', '<', $fecha)
                ->where(function ($q): void {
                    $q->whereNull('revertida')
                      ->orWhere(function ($sub): void {
                          $sub->where('revertida', '!=', 'Si')
                              ->where('revertida', '!=', 'SI');
                      });
                })
                ->select('ruta', DB::raw('SUM(monto_final) as total_previo'))
                ->groupBy('ruta')
                ->pluck('total_previo', 'ruta');
        } catch (\Throwable $e) {
            Log::warning('Error consultando facturado acumulado en fact_ventas: ' . $e->getMessage());
        }

        // G. Historial de 13 semanas (91 días) para Reposición y Ampliación de Portafolio
        $compradoresIds = $preventasRaw->pluck('cliente_id')->unique()->filter()->toArray();
        $historial90DiasPorCliente = [];

        if ((self::EVALUAR_KPI6_REPOSICION || self::EVALUAR_KPI7_AMPLIACION) && !empty($compradoresIds)) {
            $diasVentana = max(7, ($semanasHistoria > 0 ? $semanasHistoria : 13) * 7);
            $fechaInicio90 = $carbonFecha->copy()->subDays($diasVentana)->toDateString();
            $fechaFin90 = $carbonFecha->copy()->subDays(1)->toDateString();

            try {
                // Historial de compras exclusivamente en fact_preventas (ventana seleccionada de semanas)
                $histPrev = DB::connection('supervisor')->table('fact_preventas')
                    ->whereIn('cliente_id', $compradoresIds)
                    ->where('fecha_norm', '>=', $fechaInicio90)
                    ->where('fecha_norm', '<=', $fechaFin90)
                    ->where('monto_final', '>', 0)
                    ->select([
                        'cliente_id',
                        'producto_id',
                        DB::raw("COALESCE(TRIM(codigo_producto), '') as codigo_producto"),
                        DB::raw("COALESCE(TRIM(producto), '') as producto"),
                        DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                        DB::raw("COUNT(DISTINCT fecha_norm) as frecuencia_compra"),
                    ])
                    ->groupBy('cliente_id', 'producto_id', DB::raw("COALESCE(TRIM(codigo_producto), '')"), DB::raw("COALESCE(TRIM(producto), '')"), DB::raw("COALESCE(TRIM(categoria), 'GENERAL')"))
                    ->get();

                foreach ($histPrev as $hp) {
                    $cId = (int) $hp->cliente_id;
                    if (!isset($historial90DiasPorCliente[$cId])) {
                        $historial90DiasPorCliente[$cId] = [
                            'skus' => [],
                            'keys' => [],
                            'keys_map' => [],
                            'canonical_keys' => [],
                            'categorias' => [],
                            'total_lineas' => 0,
                        ];
                    }
                    $cod = trim((string) $hp->codigo_producto);
                    $pid = (int) $hp->producto_id;
                    $prodNorm = mb_strtolower(trim((string) $hp->producto));
                    $freq = max(1, (int) ($hp->frecuencia_compra ?? 1));

                    $canonicalKey = $cod !== '' ? "cod_{$cod}" : ($pid > 0 ? "id_{$pid}" : ($prodNorm !== '' ? "nom_{$prodNorm}" : ''));
                    if ($canonicalKey !== '') {
                        $historial90DiasPorCliente[$cId]['canonical_keys'][$canonicalKey] = $freq;
                    }

                    if ($cod !== '') {
                        $historial90DiasPorCliente[$cId]['skus'][$cod] = true;
                        $historial90DiasPorCliente[$cId]['keys']["cod_{$cod}"] = true;
                        if ($canonicalKey !== '') {
                            $historial90DiasPorCliente[$cId]['keys_map']["cod_{$cod}"] = $canonicalKey;
                        }
                    }
                    if ($pid > 0) {
                        $historial90DiasPorCliente[$cId]['keys']["id_{$pid}"] = true;
                        if ($canonicalKey !== '') {
                            $historial90DiasPorCliente[$cId]['keys_map']["id_{$pid}"] = $canonicalKey;
                        }
                    }
                    if ($prodNorm !== '') {
                        $historial90DiasPorCliente[$cId]['keys']["nom_{$prodNorm}"] = true;
                        if ($canonicalKey !== '') {
                            $historial90DiasPorCliente[$cId]['keys_map']["nom_{$prodNorm}"] = $canonicalKey;
                        }
                    }
                    if (!empty($hp->categoria)) {
                        $historial90DiasPorCliente[$cId]['categorias'][trim(strtoupper((string) $hp->categoria))] = true;
                    }
                    $historial90DiasPorCliente[$cId]['total_lineas']++;
                }
            } catch (\Throwable $e) {
                Log::warning('Error consultando historial 90 días en Cumplimiento: ' . $e->getMessage());
            }
        }

        // 4. Cálculo de Días Hábiles Restantes y Divisor de Preventa (para KPI 3)
        $diasHabilesRestantes = 0;
        $cursorDia = $carbonFecha->copy();
        $finMes = $carbonFecha->copy()->endOfMonth();
        while ($cursorDia->lte($finMes)) {
            // Lunes (1) a Sábado (6) son días hábiles comerciales
            if ($cursorDia->dayOfWeekIso <= 6) {
                $diasHabilesRestantes++;
            }
            $cursorDia->addDay();
        }
        $divisorPreventa = max(1, $diasHabilesRestantes - 2);

        // 5. Evaluación Exhaustiva de los 8 KPIs por Ruta
        $rutasEvaluadas = [];

        foreach ($rutasFiltradas as $rutaCodigo) {
            $infoRuta = $metaRutas->get($rutaCodigo);
            $vendedor = is_array($infoRuta) ? ($infoRuta['vendedor'] ?? 'Sin Asignar') : ($infoRuta?->vendedor ?? 'Sin Asignar');
            $canal = is_array($infoRuta) ? ($infoRuta['canal'] ?? 'TDB') : ($infoRuta?->canal ?? 'TDB');

            // Colecciones locales de la ruta
            $carteraRuta = $carteraPorRuta->get($rutaCodigo, collect());
            $visitasWebRuta = $factVisitasPorRuta->get($rutaCodigo, collect());
            $visitasMovilRuta = $visitasMovilPorRuta->get($rutaCodigo, collect());
            $preventasRuta = $preventasPorRuta->get($rutaCodigo, collect());

            // ----------------------------------------------------
            // KPI 1: Horario de Ingreso (10 Puntos)
            // ----------------------------------------------------
            $primerCheckinWeb = null;
            $horaWeb = null;
            $segundosWeb = null;

            if ($visitasWebRuta->isNotEmpty()) {
                $firstW = $visitasWebRuta->first();
                $horaWeb = $this->extraerHoraString($firstW->check_in ?? null);
                $segundosWeb = $this->horaASegundos($horaWeb);
                $primerCheckinWeb = $horaWeb;
            }

            $primerCheckinMovil = null;
            $horaMovil = null;
            $segundosMovil = null;

            if ($visitasMovilRuta->isNotEmpty()) {
                $firstM = $visitasMovilRuta->first();
                $horaMovil = $this->extraerHoraString($firstM->visited_at ?? null);
                $segundosMovil = $this->horaASegundos($horaMovil);
                $primerCheckinMovil = $horaMovil;
            }

            $kpi1Cumple = false;
            $kpi1Puntos = 0;
            $kpi1Estado = 'Sin Marcación';
            $kpi1RetrasoMin = 0;

            if (!$estrictoHorario) {
                // Modo Normal: Evalúa primer check-in registrado en sistema central (fact_visitas)
                if ($segundosWeb !== null) {
                    if ($segundosWeb <= self::UMBRAL_HORARIO_SEGUNDOS) {
                        $kpi1Cumple = true;
                        $kpi1Puntos = 10;
                        $kpi1Estado = 'Puntual';
                    } else {
                        $kpi1Cumple = false;
                        $kpi1Puntos = 0;
                        $kpi1Estado = 'Atrasado';
                        $kpi1RetrasoMin = (int) round(($segundosWeb - self::UMBRAL_HORARIO_SEGUNDOS) / 60);
                    }
                } elseif ($segundosMovil !== null) {
                    // Fallback a marcación móvil si la central aún no ha sincronizado
                    if ($segundosMovil <= self::UMBRAL_HORARIO_SEGUNDOS) {
                        $kpi1Cumple = true;
                        $kpi1Puntos = 10;
                        $kpi1Estado = 'Puntual (App)';
                    } else {
                        $kpi1Cumple = false;
                        $kpi1Puntos = 0;
                        $kpi1Estado = 'Atrasado';
                        $kpi1RetrasoMin = (int) round(($segundosMovil - self::UMBRAL_HORARIO_SEGUNDOS) / 60);
                    }
                }
            } else {
                // Modo Estricto: Exige obligatoriamente marcación tanto en web como en app móvil <= 08:10:00
                if ($segundosWeb === null || $segundosMovil === null) {
                    $kpi1Cumple = false;
                    $kpi1Puntos = 0;
                    $kpi1Estado = 'Incompleto (Falta marcación)';
                    $refSec = $segundosWeb ?? $segundosMovil;
                    if ($refSec && $refSec > self::UMBRAL_HORARIO_SEGUNDOS) {
                        $kpi1RetrasoMin = (int) round(($refSec - self::UMBRAL_HORARIO_SEGUNDOS) / 60);
                    }
                } else {
                    $maxSegundos = max($segundosWeb, $segundosMovil);
                    if ($maxSegundos <= self::UMBRAL_HORARIO_SEGUNDOS) {
                        $kpi1Cumple = true;
                        $kpi1Puntos = 10;
                        $kpi1Estado = 'Puntual (Web+App)';
                    } else {
                        $kpi1Cumple = false;
                        $kpi1Puntos = 0;
                        $kpi1Estado = 'Atrasado';
                        $kpi1RetrasoMin = (int) round(($maxSegundos - self::UMBRAL_HORARIO_SEGUNDOS) / 60);
                    }
                }
            }

            // ----------------------------------------------------
            // KPI 2: Clientes Visitados (5 Puntos)
            // Cartera Plan: Clientes de pan_ruteo para la ruta hoy
            // Visitados: Clientes registrados en fact_visitas cruzados por cliente
            // ----------------------------------------------------
            $carteraPlanClientes = $carteraRuta;
            $cPlanCount = $carteraPlanClientes->count();

            // Clientes del plan de hoy indexados por nombre normalizado a su cliente_id
            $planClientesKeys = [];
            foreach ($carteraRuta as $cRow) {
                $cid = (int) $cRow->cliente_id;
                $n1 = $this->normalizarTexto((string) $cRow->cliente);
                $n2 = $this->normalizarTexto((string) $cRow->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $cRow->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $cRow->cliente_norm);
                if ($n1 !== '') $planClientesKeys[$n1] = $cid;
                if ($n2 !== '') $planClientesKeys[$n2] = $cid;
                if ($a1 !== '') $planClientesKeys[$a1] = $cid;
                if ($a2 !== '') $planClientesKeys[$a2] = $cid;
            }

            // Conteo de visitas únicas desde fact_visitas (exclusivamente)
            $visitasDetallePorCliente = [];
            $visitadosPlanUnicos = [];
            $visitadosFueraPlanUnicos = [];

            foreach ($visitasWebRuta as $vw) {
                $n1 = $this->normalizarTexto((string) $vw->cliente);
                $n2 = $this->normalizarTexto((string) $vw->cliente_norm);
                $a1 = $this->normalizarAlfanumerico((string) $vw->cliente);
                $a2 = $this->normalizarAlfanumerico((string) $vw->cliente_norm);

                $keyNom = $n1 ?: ($n2 ?: ($a1 ?: $a2));
                if ($keyNom === '') {
                    continue;
                }

                $cId = $planClientesKeys[$n1]
                    ?? $planClientesKeys[$n2]
                    ?? $planClientesKeys[$a1]
                    ?? $planClientesKeys[$a2]
                    ?? null;

                $detalleKey = $cId ?: 'fp_' . md5($keyNom);

                if (!isset($visitasDetallePorCliente[$detalleKey])) {
                    $visitasDetallePorCliente[$detalleKey] = [
                        'cliente_id' => $cId,
                        'cliente_nombre' => $vw->cliente ?? $keyNom,
                        'check_in' => $vw->check_in,
                        'check_out' => $vw->check_out ?? null,
                        'tiempo_pdv' => $vw->tiempo_pdv ?? null,
                        'origen' => 'WEB',
                        'fotos_count' => 0,
                    ];
                }

                if ($cId !== null) {
                    $visitadosPlanUnicos[$cId] = true;
                } else {
                    $visitadosFueraPlanUnicos[$keyNom] = true;
                }
            }

            $vActivosCount = count($visitadosPlanUnicos);
            $fueraDePlanCount = count($visitadosFueraPlanUnicos);
            $visitadosTotalCount = $vActivosCount + $fueraDePlanCount;
            $inactivosVisitadosCount = 0;

            $kpi2Cumple = ($cPlanCount > 0) ? ($vActivosCount >= $cPlanCount) : ($vActivosCount > 0);
            $kpi2Puntos = $kpi2Cumple ? 5 : 0;
            $coberturaVisitasPct = ($cPlanCount > 0) ? round(($vActivosCount / $cPlanCount) * 100, 1) : 0.0;

            // ----------------------------------------------------
            // KPI 3: Cumplimiento de Objetivo Diario (10 Puntos)
            // ----------------------------------------------------
            $metaRutaObj = $metasPorRuta->get($rutaCodigo);
            $metaMensual = $metaRutaObj ? (float) (is_array($metaRutaObj) ? ($metaRutaObj['objetivo_de_venta'] ?? 0.0) : ($metaRutaObj->objetivo_de_venta ?? 0.0)) : 0.0;
            $facturadoPrevio = (float) ($facturadoPrevioPorRuta[$rutaCodigo] ?? 0.0);
            $brecha = max(0.0, $metaMensual - $facturadoPrevio);
            $cuotaDiaria = ($divisorPreventa > 0) ? round($brecha / $divisorPreventa, 2) : 0.0;

            // Venta Preventa Hoy
            $ventaPreventaHoy = (float) $preventasRuta->sum('monto_final');

            $kpi3Cumple = ($cuotaDiaria > 0) ? ($ventaPreventaHoy >= $cuotaDiaria) : ($ventaPreventaHoy > 0);
            $kpi3Puntos = $kpi3Cumple ? 10 : 0;
            $alcanceObjetivoPct = ($cuotaDiaria > 0) ? round(($ventaPreventaHoy / $cuotaDiaria) * 100, 1) : ($ventaPreventaHoy > 0 ? 100.0 : 0.0);

            // ----------------------------------------------------
            // KPI 4: Efectividad en Ruta (30 Puntos)
            // ----------------------------------------------------
            $preventasPorCliente = $preventasRuta->groupBy('cliente_id');
            $compradoresIdsRuta = $preventasPorCliente->keys()->map(fn ($id) => (int) $id)->toArray();
            $totalCompradores = count($compradoresIdsRuta);

            $efectividadPct = ($cPlanCount > 0) ? round(($totalCompradores / $cPlanCount) * 100, 1) : 0.0;
            $kpi4Cumple = ($efectividadPct >= self::UMBRAL_EFECTIVIDAD_PCT);
            $kpi4Puntos = $kpi4Cumple ? 30 : 0;

            // Desglose de tickets sobre/bajo 70 Bs
            $compradoresSobre70 = 0;
            $compradoresBajo70 = 0;
            $clientesAuditoriaTickets = [];

            foreach ($preventasPorCliente as $cId => $itemsCliente) {
                $montoCliente = (float) $itemsCliente->sum('monto_final');
                $clienteInfo = $carteraRuta->firstWhere('cliente_id', $cId);
                $primerItem = $itemsCliente->first();

                $conforme70 = ($montoCliente >= self::UMBRAL_COMPRA_MINIMA_BS);
                if ($conforme70) {
                    $compradoresSobre70++;
                } else {
                    $compradoresBajo70++;
                }

                $clientesAuditoriaTickets[(int) $cId] = [
                    'cliente_id' => (int) $cId,
                    'codigo_cliente' => $primerItem->codigo_cliente ?? ($clienteInfo->codigo_cliente ?? $cId),
                    'cliente' => $primerItem->cliente ?? ($clienteInfo->cliente ?? "Cliente #{$cId}"),
                    'direccion' => $clienteInfo->direccion ?? 'Sin dirección',
                    'monto_total' => round($montoCliente, 2),
                    'items_count' => $itemsCliente->count(),
                    'conforme_70' => $conforme70,
                    'deficit_70' => $conforme70 ? 0.0 : round(self::UMBRAL_COMPRA_MINIMA_BS - $montoCliente, 2),
                ];
            }

            // ----------------------------------------------------
            // KPI 5: Compra Mínima Diaria de 70 Bs (5 Puntos)
            // ----------------------------------------------------
            // Regla Estricta: La ruta debe tener compradores y el 100% debe acumular >= 70 Bs
            $kpi5Cumple = ($totalCompradores > 0 && $compradoresBajo70 === 0);
            $kpi5Puntos = $kpi5Cumple ? 5 : 0;

            // ----------------------------------------------------
            // KPI 6: Pedido de Reposición (15 Puntos · Match Ponderado f_i)
            // ----------------------------------------------------
            $totalSkusEsperadosRuta = 0;
            $totalSkusMatchRuta = 0;
            $totalSkusFueraCanastaRuta = 0;
            $totalPuntosEsperadosRuta = 0;
            $totalPuntosMatchRuta = 0;
            $tiendasEvaluadasRepo = 0;
            $tiendasNuevasRepo = 0;
            $detalleReposicionTiendas = [];

            if (self::EVALUAR_KPI6_REPOSICION) {
                foreach ($preventasPorCliente as $cId => $itemsCliente) {
                    $cIdInt = (int) $cId;
                    $historial = $historial90DiasPorCliente[$cIdInt] ?? null;
                    $canonicalKeys = $historial['canonical_keys'] ?? []; // [$canonicalKey => $pesoFi]
                    $tieneBase = !empty($canonicalKeys);

                    $skusBaseCount = count($canonicalKeys);
                    $puntosEsperadosTienda = 0;
                    foreach ($canonicalKeys as $cKey => $pesoFi) {
                        $puntosEsperadosTienda += (int) $pesoFi;
                    }

                    $matchesCliente = 0;
                    $fueraCanastaCliente = 0;
                    $puntosMatchTienda = 0;
                    $matchedCanonicalKeys = [];

                    foreach ($itemsCliente as $it) {
                        $cod = trim((string) ($it->codigo_producto ?? ''));
                        $pid = (int) ($it->producto_id ?? 0);
                        $prodNorm = mb_strtolower(trim((string) ($it->producto ?? '')));

                        $matchedCanonicalKey = null;
                        if ($tieneBase) {
                            if ($cod !== '' && isset($historial['keys_map']["cod_{$cod}"])) {
                                $matchedCanonicalKey = $historial['keys_map']["cod_{$cod}"];
                            } elseif ($pid > 0 && isset($historial['keys_map']["id_{$pid}"])) {
                                $matchedCanonicalKey = $historial['keys_map']["id_{$pid}"];
                            } elseif ($prodNorm !== '' && isset($historial['keys_map']["nom_{$prodNorm}"])) {
                                $matchedCanonicalKey = $historial['keys_map']["nom_{$prodNorm}"];
                            }
                        }

                        if ($matchedCanonicalKey !== null && isset($canonicalKeys[$matchedCanonicalKey])) {
                            $matchesCliente++;
                            if (!isset($matchedCanonicalKeys[$matchedCanonicalKey])) {
                                $matchedCanonicalKeys[$matchedCanonicalKey] = true;
                                $puntosMatchTienda += (int) $canonicalKeys[$matchedCanonicalKey];
                            }
                        } else {
                            $fueraCanastaCliente++;
                        }
                    }

                    // Regla de Cliente Nuevo o Recuperado (Puntos Esperados Tienda = 0)
                    $esNeutro = !$tieneBase || $puntosEsperadosTienda === 0;

                    if (!$esNeutro) {
                        $pctTienda = round(($puntosMatchTienda / $puntosEsperadosTienda) * 100.0, 1);
                        $cumpleTienda = ($pctTienda >= 60.0);

                        $totalPuntosEsperadosRuta += $puntosEsperadosTienda;
                        $totalPuntosMatchRuta += $puntosMatchTienda;
                        $tiendasEvaluadasRepo++;
                    } else {
                        // Neutro (Excluido): Aporta 0 al numerador y 0 al denominador de la ruta
                        $pctTienda = null;
                        $cumpleTienda = null;
                        $tiendasNuevasRepo++;
                    }

                    $totalSkusEsperadosRuta += $skusBaseCount;
                    $totalSkusMatchRuta += $matchesCliente;
                    $totalSkusFueraCanastaRuta += $fueraCanastaCliente;

                    $primerItem = $itemsCliente->first();
                    $detalleReposicionTiendas[] = [
                        'cliente_id' => $cIdInt,
                        'codigo_cliente' => $primerItem->codigo_cliente ?? (string) $cIdInt,
                        'cliente' => $primerItem->cliente ?? "Cliente #{$cIdInt}",
                        'tiene_base_historica' => $tieneBase,
                        'es_neutro' => $esNeutro,
                        'puntos_esperados' => $puntosEsperadosTienda,
                        'puntos_match' => $puntosMatchTienda,
                        'skus_esperados' => $skusBaseCount,
                        'skus_con_match' => $matchesCliente,
                        'skus_fuera_canasta' => $fueraCanastaCliente,
                        'porcentaje_reposicion' => $pctTienda,
                        'cumple' => $cumpleTienda,
                    ];
                }

                // Paso 2: Porcentaje de Reposición del Día de la Ruta (Ponderado Global de Puntos)
                if ($totalPuntosEsperadosRuta > 0) {
                    $tasaReposicion = round(($totalPuntosMatchRuta / $totalPuntosEsperadosRuta) * 100.0, 1);
                } elseif ($totalCompradores > 0) {
                    // Caso de borde único: 100% de las tiendas son clientes nuevos sin historial
                    $tasaReposicion = 100.0;
                } else {
                    $tasaReposicion = 0.0;
                }

                // Paso 3: Asignación de Puntos (15 Puntos a partir de 20% de reposición)
                if ($totalCompradores === 0 || $tasaReposicion < self::UMBRAL_REPOSICION_PCT) {
                    $kpi6Puntos = 0.0;
                    $kpi6Cumple = false;
                } else {
                    $kpi6Puntos = 15.0;
                    $kpi6Cumple = true;
                }
            } else {
                $kpi6Cumple = false;
                $kpi6Puntos = 0.0;
                $tasaReposicion = 0.0;
            }

            // ----------------------------------------------------
            // KPI 7: Ampliación de Portafolio (20 Puntos · Criterio >= 30%)
            // ----------------------------------------------------
            $totalSkusPreventaRuta = 0;
            $totalSkusAmpliacionRuta = 0;
            $cumplenAmpliacion = 0;
            $detalleAmpliacionTiendas = [];
            $tasaAmpliacion = 0.0;
            $kpi7Cumple = false;
            $kpi7Puntos = 0.0;

            if (self::EVALUAR_KPI7_AMPLIACION) {
                foreach ($preventasPorCliente as $cId => $itemsCliente) {
                    $cIdInt = (int) $cId;
                    $historial = $historial90DiasPorCliente[$cIdInt] ?? null;
                    $tieneBase = !empty($historial);

                    // Agrupar items comprados hoy por SKU único
                    $skusCompradosHoy = [];
                    foreach ($itemsCliente as $it) {
                        $cod = trim((string) ($it->codigo_producto ?? ''));
                        $pid = (int) ($it->producto_id ?? 0);
                        $prodNorm = mb_strtolower(trim((string) ($it->producto ?? '')));
                        $key = $cod !== '' ? "cod_{$cod}" : ($pid > 0 ? "id_{$pid}" : "nom_{$prodNorm}");

                        if (!isset($skusCompradosHoy[$key])) {
                            $skusCompradosHoy[$key] = [
                                'cod' => $cod,
                                'pid' => $pid,
                                'prodNorm' => $prodNorm,
                                'producto' => trim((string) ($it->producto ?? '')),
                                'cantidad' => 0,
                                'monto' => 0.0,
                            ];
                        }
                        $skusCompradosHoy[$key]['cantidad'] += (int) ($it->cantidad ?? 0);
                        $skusCompradosHoy[$key]['monto'] += (float) ($it->monto_final ?? 0.0);
                    }

                    $totalSkusCliente = count($skusCompradosHoy);
                    $skusNuevosCount = 0;
                    $skusHabitualesCount = 0;

                    foreach ($skusCompradosHoy as $skuData) {
                        $enHistorial = false;
                        if ($tieneBase) {
                            $cod = $skuData['cod'];
                            $pid = $skuData['pid'];
                            $prodNorm = $skuData['prodNorm'];

                            if ($cod !== '' && isset($historial['keys']["cod_{$cod}"])) {
                                $enHistorial = true;
                            } elseif ($pid > 0 && isset($historial['keys']["id_{$pid}"])) {
                                $enHistorial = true;
                            } elseif ($prodNorm !== '' && isset($historial['keys']["nom_{$prodNorm}"])) {
                                $enHistorial = true;
                            }
                        }

                        if ($enHistorial) {
                            $skusHabitualesCount++;
                        } else {
                            $skusNuevosCount++;
                        }
                    }

                    $pctAmpliacionCliente = $totalSkusCliente > 0
                        ? round(($skusNuevosCount / $totalSkusCliente) * 100.0, 1)
                        : 0.0;
                    $cumpleCliente = ($pctAmpliacionCliente >= self::UMBRAL_AMPLIACION_PCT);

                    if ($cumpleCliente) {
                        $cumplenAmpliacion++;
                    }

                    $totalSkusPreventaRuta += $totalSkusCliente;
                    $totalSkusAmpliacionRuta += $skusNuevosCount;

                    $primerItem = $itemsCliente->first();
                    $detalleAmpliacionTiendas[] = [
                        'cliente_id' => $cIdInt,
                        'codigo_cliente' => $primerItem->codigo_cliente ?? (string) $cIdInt,
                        'cliente' => $primerItem->cliente ?? "Cliente #{$cIdInt}",
                        'total_skus' => $totalSkusCliente,
                        'skus_ampliacion' => $skusNuevosCount,
                        'skus_habituales' => $skusHabitualesCount,
                        'porcentaje_ampliacion' => $pctAmpliacionCliente,
                        'cumple' => $cumpleCliente,
                    ];
                }

                $tasaAmpliacion = $totalSkusPreventaRuta > 0
                    ? round(($totalSkusAmpliacionRuta / $totalSkusPreventaRuta) * 100.0, 1)
                    : 0.0;

                $kpi7Cumple = ($totalCompradores > 0 && $tasaAmpliacion >= self::UMBRAL_AMPLIACION_PCT);
                $kpi7Puntos = $kpi7Cumple ? 20.0 : 0.0;
            }

            // ----------------------------------------------------
            // KPI 8: Covers (5 Puntos · Clientes con Descuento >= 50%)
            // ----------------------------------------------------
            $kpi8Cumple = false;
            $kpi8Puntos = 0.0;
            $cumplenCovers = 0;
            $detalleCoversTiendas = [];
            $tasaCovers = 0.0;

            if (self::EVALUAR_KPI8_COVERS) {
                foreach ($preventasPorCliente as $cId => $itemsCliente) {
                    $cIdInt = (int) $cId;
                    $tieneDescuento = false;
                    $totalDescuentoCliente = 0.0;
                    $itemsConDescuentoCount = 0;

                    foreach ($itemsCliente as $it) {
                        $desc = (float) ($it->descuento ?? 0.0);
                        if ($desc > 0) {
                            $tieneDescuento = true;
                            $itemsConDescuentoCount++;
                            $totalDescuentoCliente += $desc;
                        }
                    }

                    if ($tieneDescuento) {
                        $cumplenCovers++;
                    }

                    $primerItem = $itemsCliente->first();
                    $detalleCoversTiendas[] = [
                        'cliente_id' => $cIdInt,
                        'codigo_cliente' => $primerItem->codigo_cliente ?? (string) $cIdInt,
                        'cliente' => $primerItem->cliente ?? "Cliente #{$cIdInt}",
                        'total_items' => $itemsCliente->count(),
                        'items_con_descuento' => $itemsConDescuentoCount,
                        'descuento_total' => round($totalDescuentoCliente, 2),
                        'cumple' => $tieneDescuento,
                    ];
                }

                $tasaCovers = $totalCompradores > 0
                    ? round(($cumplenCovers / $totalCompradores) * 100.0, 1)
                    : 0.0;

                $kpi8Cumple = ($totalCompradores > 0 && $tasaCovers >= self::UMBRAL_COVERS_PCT);
                $kpi8Puntos = $kpi8Cumple ? 5.0 : 0.0;
            }

            // ----------------------------------------------------
            // Score Total Unilever (0 a 100 Puntos)
            // ----------------------------------------------------
            $scoreTotal = $kpi1Puntos + $kpi2Puntos + $kpi3Puntos + $kpi4Puntos + $kpi5Puntos + $kpi6Puntos + $kpi7Puntos + $kpi8Puntos;

            // Construir detalle enriquecido de visitas de la ruta
            $listaClientesVisitas = [];
            foreach ($carteraRuta as $cRow) {
                $cId = (int) $cRow->cliente_id;
                $visInfo = $visitasDetallePorCliente[$cId] ?? null;
                $fueVisitado = ($visInfo !== null);
                $tipoVisita = $fueVisitado ? 'En Plan' : 'Pendiente';

                $listaClientesVisitas[] = [
                    'cliente_id' => $cId,
                    'codigo_cliente' => (string) $cId,
                    'cliente' => $cRow->cliente,
                    'direccion' => $cRow->direccion,
                    'tipo_negocio' => $cRow->tipo_negocio,
                    'estado_maestro' => $cRow->estado,
                    'visitado' => $fueVisitado,
                    'tipo_visita' => $tipoVisita,
                    'hora_check_in' => $visInfo['check_in'] ?? null,
                    'hora_check_out' => $visInfo['check_out'] ?? null,
                    'tiempo_pdv' => $visInfo['tiempo_pdv'] ?? null,
                    'fotos_count' => $visInfo['fotos_count'] ?? 0,
                ];
            }

            // Añadir visitas fuera de plan que no estaban en cartera
            foreach ($visitasDetallePorCliente as $kKey => $vData) {
                $cId = $vData['cliente_id'];
                if (!$cId || !$carteraRuta->contains('cliente_id', $cId)) {
                    $listaClientesVisitas[] = [
                        'cliente_id' => $cId ?: $kKey,
                        'codigo_cliente' => (string) ($cId ?: $kKey),
                        'cliente' => $vData['cliente_nombre'] ?? "Cliente #{$kKey} (Fuera de Plan)",
                        'direccion' => 'Desconocida',
                        'tipo_negocio' => 'General',
                        'estado_maestro' => 'Fuera de Plan',
                        'visitado' => true,
                        'tipo_visita' => 'Fuera de Plan',
                        'hora_check_in' => $vData['check_in'] ?? null,
                        'hora_check_out' => $vData['check_out'] ?? null,
                        'tiempo_pdv' => $vData['tiempo_pdv'] ?? null,
                        'fotos_count' => $vData['fotos_count'] ?? 0,
                    ];
                }
            }

            $rutasEvaluadas[] = [
                'ruta' => $rutaCodigo,
                'vendedor' => $vendedor,
                'canal' => $canal,
                'score_total' => $scoreTotal,

                // Resumen KPI 1: Horario
                'kpi1_horario' => [
                    'puntos' => $kpi1Puntos,
                    'puntos_max' => 10,
                    'cumple' => $kpi1Cumple,
                    'estado' => $kpi1Estado,
                    'hora_web' => $horaWeb,
                    'hora_movil' => $horaMovil,
                    'primer_checkin' => $primerCheckinWeb ?: $primerCheckinMovil,
                    'retraso_minutos' => $kpi1RetrasoMin,
                    'umbral' => '08:10:00',
                ],

                // Resumen KPI 2: Visitas
                'kpi2_visitas' => [
                    'puntos' => $kpi2Puntos,
                    'puntos_max' => 5,
                    'cumple' => $kpi2Cumple,
                    'cartera_plan' => $cPlanCount,
                    'visitados_activos' => $vActivosCount,
                    'inactivos_visitados' => $inactivosVisitadosCount,
                    'fuera_de_plan' => $fueraDePlanCount,
                    'total_visitados' => $visitadosTotalCount,
                    'cobertura_pct' => $coberturaVisitasPct,
                ],

                // Resumen KPI 3: Objetivo Diario
                'kpi3_objetivo' => [
                    'puntos' => $kpi3Puntos,
                    'puntos_max' => 10,
                    'cumple' => $kpi3Cumple,
                    'meta_mensual' => $metaMensual,
                    'facturado_previo' => $facturadoPrevio,
                    'brecha' => $brecha,
                    'dias_habiles_restantes' => $diasHabilesRestantes,
                    'divisor_preventa' => $divisorPreventa,
                    'cuota_diaria' => $cuotaDiaria,
                    'venta_hoy' => $ventaPreventaHoy,
                    'alcance_pct' => $alcanceObjetivoPct,
                ],

                // Resumen KPI 4: Efectividad
                'kpi4_efectividad' => [
                    'puntos' => $kpi4Puntos,
                    'puntos_max' => 30,
                    'cumple' => $kpi4Cumple,
                    'cartera_plan' => $cPlanCount,
                    'compradores' => $totalCompradores,
                    'efectividad_pct' => $efectividadPct,
                    'umbral_pct' => self::UMBRAL_EFECTIVIDAD_PCT,
                    'compradores_sobre_70' => $compradoresSobre70,
                    'compradores_bajo_70' => $compradoresBajo70,
                ],

                // Resumen KPI 5: Compra Mínima
                'kpi5_compra_minima' => [
                    'puntos' => $kpi5Puntos,
                    'puntos_max' => 5,
                    'cumple' => $kpi5Cumple,
                    'compradores' => $totalCompradores,
                    'compradores_bajo_70' => $compradoresBajo70,
                    'monto_minimo_estandar' => self::UMBRAL_COMPRA_MINIMA_BS,
                ],

                // Resumen KPI 6: Reposición (Match Ponderado f_i)
                'kpi6_reposicion' => [
                    'puntos' => $kpi6Puntos,
                    'puntos_max' => 15,
                    'cumple' => $kpi6Cumple,
                    'evaluado' => self::EVALUAR_KPI6_REPOSICION,
                    'compradores' => $totalCompradores,
                    'evaluables' => $tiendasEvaluadasRepo,
                    'nuevos_neutros' => $tiendasNuevasRepo,
                    'puntos_esperados' => $totalPuntosEsperadosRuta,
                    'puntos_match' => $totalPuntosMatchRuta,
                    'skus_esperados' => $totalSkusEsperadosRuta,
                    'skus_match' => $totalSkusMatchRuta,
                    'skus_fuera_canasta' => $totalSkusFueraCanastaRuta,
                    'tasa_pct' => $tasaReposicion,
                    'umbral_pct' => self::UMBRAL_REPOSICION_PCT,
                    'piso_pct' => self::PISO_REPOSICION_PCT,
                ],

                // Resumen KPI 7: Ampliación de Portafolio (20 Puntos)
                'kpi7_ampliacion' => [
                    'puntos' => $kpi7Puntos,
                    'puntos_max' => 20,
                    'cumple' => $kpi7Cumple,
                    'evaluado' => self::EVALUAR_KPI7_AMPLIACION,
                    'total_compradores' => $totalCompradores,
                    'cumplen' => $cumplenAmpliacion,
                    'total_skus' => $totalSkusPreventaRuta,
                    'skus_ampliacion' => $totalSkusAmpliacionRuta,
                    'tasa_pct' => $tasaAmpliacion,
                    'umbral_pct' => self::UMBRAL_AMPLIACION_PCT,
                ],

                // Resumen KPI 8: Covers (5 Puntos · Clientes con Descuento >= 50%)
                'kpi8_covers' => [
                    'puntos' => $kpi8Puntos,
                    'puntos_max' => 5,
                    'cumple' => $kpi8Cumple,
                    'evaluado' => self::EVALUAR_KPI8_COVERS,
                    'total_compradores' => $totalCompradores,
                    'cumplen' => $cumplenCovers,
                    'tasa_pct' => $tasaCovers,
                    'umbral_pct' => self::UMBRAL_COVERS_PCT,
                ],

                // Desgloses monográficos para las pestañas 1-8
                'detalle_visitas' => $listaClientesVisitas,
                'detalle_compra_minima' => array_values($clientesAuditoriaTickets),
                'detalle_reposicion' => $detalleReposicionTiendas,
                'detalle_ampliacion' => $detalleAmpliacionTiendas,
                'detalle_covers' => $detalleCoversTiendas,
            ];
        }

        // 6. Ordenamiento y Asignación de Ranking Unilever (Descendente por Score Total)
        usort($rutasEvaluadas, function ($a, $b) {
            if ($b['score_total'] !== $a['score_total']) {
                return $b['score_total'] <=> $a['score_total'];
            }
            return strcmp($a['ruta'], $b['ruta']);
        });

        foreach ($rutasEvaluadas as $idx => &$rItem) {
            $rItem['rank'] = $idx + 1;
        }
        unset($rItem);

        // 7. KPIs Consolidados Globales de la Pizarra
        $totalRutasCount = count($rutasEvaluadas);
        $sumaScores = array_sum(array_column($rutasEvaluadas, 'score_total'));
        $rutas100 = count(array_filter($rutasEvaluadas, fn ($r) => $r['score_total'] >= 100));
        $rutas80 = count(array_filter($rutasEvaluadas, fn ($r) => $r['score_total'] >= 80));
        $ventaTotalDia = (float) $preventasRaw->sum('monto_final');

        $totalCarteraGlobal = array_sum(array_map(fn ($r) => $r['kpi2_visitas']['cartera_plan'], $rutasEvaluadas));
        $totalCompradoresGlobal = array_sum(array_map(fn ($r) => $r['kpi4_efectividad']['compradores'], $rutasEvaluadas));
        $efectividadGlobal = ($totalCarteraGlobal > 0)
            ? round(($totalCompradoresGlobal / $totalCarteraGlobal) * 100, 1)
            : 0.0;

        return [
            'fecha' => $fecha,
            'dia_semana' => $diaSemana,
            'estricto_horario' => $estrictoHorario,
            'kpis_globales' => [
                'total_rutas' => $totalRutasCount,
                'score_promedio' => $totalRutasCount > 0 ? round($sumaScores / $totalRutasCount, 1) : 0.0,
                'rutas_100_pts' => $rutas100,
                'rutas_aprobadas_80' => $rutas80,
                'efectividad_global' => $efectividadGlobal,
                'facturacion_total_dia' => $ventaTotalDia,
            ],
            'rutas' => $rutasEvaluadas,
        ];
    }

    /**
     * Extrae la hora formateada HH:mm:ss a partir de una fecha/hora cruda.
     */
    private function extraerHoraString(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);
        if (strlen($raw) >= 19 && str_contains($raw, ' ')) {
            return substr($raw, 11, 8);
        }

        if (preg_match('/\b\d{2}:\d{2}(?::\d{2})?\b/', $raw, $m)) {
            return strlen($m[0]) === 5 ? "{$m[0]}:00" : $m[0];
        }

        return $raw;
    }

    /**
     * Convierte una cadena de hora HH:mm:ss a segundos desde medianoche.
     */
    private function horaASegundos(?string $hora): ?int
    {
        if (empty($hora)) {
            return null;
        }

        $parts = explode(':', $hora);
        if (count($parts) < 2) {
            return null;
        }

        $h = (int) $parts[0];
        $m = (int) $parts[1];
        $s = isset($parts[2]) ? (int) $parts[2] : 0;

        return ($h * 3600) + ($m * 60) + $s;
    }

    /**
     * Normaliza un texto removiendo acentos, espacios excesivos y convirtiendo a minúsculas.
     */
    private function normalizarTexto(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }

        $s = mb_strtolower(trim($texto), 'UTF-8');
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ];
        $s = strtr($s, $replacements);
        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    /**
     * Normalización estricta alfanumérica eliminando signos de puntuación, puntos, comas, guiones.
     */
    private function normalizarAlfanumerico(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }
        $s = $this->normalizarTexto($texto);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }
}