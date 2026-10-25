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
    private const UMBRAL_REPOSICION_PCT = 60.0;
    private const UMBRAL_AMPLIACION_PCT = 60.0;
    private const UMBRAL_COVERS_PCT = 60.0;

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
        bool $estrictoHorario = false
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
        $carbonFecha = Carbon::parse($fecha)->locale('es');
        $diaMap = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
        $diaSemana = $diaMap[$carbonFecha->dayOfWeekIso] ?? 'Lunes';
        $diasFiltro = [$diaSemana];
        if ($diaSemana === 'Miércoles') {
            $diasFiltro[] = 'Miercoles';
        }
        if ($diaSemana === 'Sábado') {
            $diasFiltro[] = 'Sabado';
        }

        // 3. Extracción de Datos Heterogéneos

        // A. pan_ruteo: Cartera oficial activa asignada al día de visita
        $carteraRaw = collect();
        try {
            $carteraRaw = DB::connection('supervisor')->table('pan_ruteo as pr')
                ->whereIn('pr.ruta', $rutasFiltradas)
                ->where(function ($q) use ($diasFiltro): void {
                    $q->whereIn('pr.dia_norm', $diasFiltro)
                      ->orWhereIn('pr.dia', $diasFiltro);
                })
                ->select([
                    'pr.id',
                    'pr.ruta',
                    'pr.cliente_id',
                    'pr.codigo_cliente',
                    DB::raw("COALESCE(NULLIF(pr.cliente, ''), pr.cliente_norm, CONCAT('Cliente #', pr.cliente_id)) as cliente"),
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

        $carteraPorRuta = $carteraRaw->groupBy('ruta');

        // B. fact_visitas: Marcajes del sistema central (web ERP)
        $factVisitasRaw = collect();
        try {
            $baseVisitasQuery = DB::connection('supervisor')->table('fact_visitas')
                ->whereIn('ruta', $rutasFiltradas);

            try {
                $factVisitasRaw = (clone $baseVisitasQuery)
                    ->whereDate('check_in', $fecha)
                    ->orderBy('check_in', 'asc')
                    ->get();
            } catch (\Throwable) {
                $factVisitasRaw = (clone $baseVisitasQuery)
                    ->where('fecha', $fecha)
                    ->orderBy('check_in', 'asc')
                    ->get();
            }
        } catch (\Throwable $e) {
            Log::warning('Error consultando fact_visitas en Cumplimiento: ' . $e->getMessage());
        }

        $factVisitasPorRuta = $factVisitasRaw->groupBy('ruta');

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

        // G. Historial de 90 días (13 semanas) para Reposición y Ampliación de Portafolio
        $compradoresIds = $preventasRaw->pluck('cliente_id')->unique()->filter()->toArray();
        $historial90DiasPorCliente = [];

        if (!empty($compradoresIds)) {
            $fechaInicio90 = $carbonFecha->copy()->subDays(90)->toDateString();
            $fechaFin90 = $carbonFecha->copy()->subDays(1)->toDateString();

            try {
                // Historial de ventas cerradas
                $histVentas = DB::connection('supervisor')->table('fact_ventas')
                    ->whereIn('cliente_id', $compradoresIds)
                    ->whereBetween('fecha_norm', [$fechaInicio90, $fechaFin90])
                    ->select('cliente_id', 'codigo_producto', 'categoria')
                    ->distinct()
                    ->get();

                foreach ($histVentas as $hv) {
                    $cId = (int) $hv->cliente_id;
                    if (!isset($historial90DiasPorCliente[$cId])) {
                        $historial90DiasPorCliente[$cId] = [
                            'skus' => [],
                            'categorias' => [],
                            'total_lineas' => 0,
                        ];
                    }
                    if (!empty($hv->codigo_producto)) {
                        $historial90DiasPorCliente[$cId]['skus'][trim((string) $hv->codigo_producto)] = true;
                    }
                    if (!empty($hv->categoria)) {
                        $historial90DiasPorCliente[$cId]['categorias'][trim(strtoupper((string) $hv->categoria))] = true;
                    }
                    $historial90DiasPorCliente[$cId]['total_lineas']++;
                }

                // Historial de preventas previas
                $histPrev = DB::connection('supervisor')->table('fact_preventas')
                    ->whereIn('cliente_id', $compradoresIds)
                    ->whereBetween('fecha_norm', [$fechaInicio90, $fechaFin90])
                    ->select('cliente_id', 'codigo_producto', 'categoria')
                    ->distinct()
                    ->get();

                foreach ($histPrev as $hp) {
                    $cId = (int) $hp->cliente_id;
                    if (!isset($historial90DiasPorCliente[$cId])) {
                        $historial90DiasPorCliente[$cId] = [
                            'skus' => [],
                            'categorias' => [],
                            'total_lineas' => 0,
                        ];
                    }
                    if (!empty($hp->codigo_producto)) {
                        $historial90DiasPorCliente[$cId]['skus'][trim((string) $hp->codigo_producto)] = true;
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
            $vendedor = $infoRuta ? $infoRuta->vendedor : 'Sin Asignar';
            $canal = $infoRuta ? $infoRuta->canal : 'TDB';

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
            // ----------------------------------------------------
            $carteraPlanClientes = $carteraRuta->where('estado', 'Activo');
            $cPlanCount = $carteraPlanClientes->count();
            $cPlanIds = $carteraPlanClientes->pluck('cliente_id')->map(fn ($id) => (int) $id)->unique()->toArray();

            // Clientes visitados únicos entre web y móvil
            $visitadosIds = [];
            $visitasDetallePorCliente = [];

            foreach ($visitasWebRuta as $vw) {
                $cId = (int) ($vw->cliente_id ?? 0);
                if ($cId > 0) {
                    $visitadosIds[$cId] = true;
                    if (!isset($visitasDetallePorCliente[$cId])) {
                        $visitasDetallePorCliente[$cId] = [
                            'cliente_id' => $cId,
                            'check_in' => $vw->check_in,
                            'check_out' => $vw->check_out ?? null,
                            'tiempo_pdv' => $vw->tiempo_pdv ?? null,
                            'origen' => 'WEB',
                            'fotos_count' => 0,
                        ];
                    }
                }
            }

            foreach ($visitasMovilRuta as $vm) {
                $cId = (int) ($vm->client_id ?? 0);
                if ($cId > 0) {
                    $visitadosIds[$cId] = true;
                    if (!isset($visitasDetallePorCliente[$cId])) {
                        $visitasDetallePorCliente[$cId] = [
                            'cliente_id' => $cId,
                            'check_in' => $vm->visited_at,
                            'check_out' => null,
                            'tiempo_pdv' => null,
                            'origen' => 'APP',
                            'fotos_count' => !empty($vm->photo_path) ? 1 : 0,
                        ];
                    } else {
                        if (!empty($vm->photo_path)) {
                            $visitasDetallePorCliente[$cId]['fotos_count']++;
                        }
                    }
                }
            }

            $visitadosTotalCount = count($visitadosIds);
            $vActivosCount = count(array_intersect(array_keys($visitadosIds), $cPlanIds));

            // Inactivos visitados (clientes en pan_ruteo con estado != 'Activo' pero visitados hoy)
            $inactivosVisitadosCount = $carteraRuta->where('estado', '!=', 'Activo')
                ->whereIn('cliente_id', array_keys($visitadosIds))
                ->count();

            // Fuera de plan (visitados hoy que no pertenecían a la cartera del día de esta ruta)
            $carteraTotalIds = $carteraRuta->pluck('cliente_id')->map(fn ($id) => (int) $id)->toArray();
            $fueraDePlanCount = count(array_diff(array_keys($visitadosIds), $carteraTotalIds));

            $kpi2Cumple = ($cPlanCount > 0) ? ($vActivosCount >= $cPlanCount) : ($vActivosCount > 0);
            $kpi2Puntos = $kpi2Cumple ? 5 : 0;
            $coberturaVisitasPct = ($cPlanCount > 0) ? round(($vActivosCount / $cPlanCount) * 100, 1) : 0.0;

            // ----------------------------------------------------
            // KPI 3: Cumplimiento de Objetivo Diario (10 Puntos)
            // ----------------------------------------------------
            $metaRutaObj = $metasPorRuta->get($rutaCodigo);
            $metaMensual = $metaRutaObj ? (float) ($metaRutaObj->objetivo_de_venta ?? 0.0) : 0.0;
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
            // KPI 6: Pedido de Reposición (15 Puntos)
            // ----------------------------------------------------
            $evaluablesReposicion = 0;
            $sinBaseApertura = 0;
            $cumplenReposicion = 0;
            $detalleReposicionTiendas = [];

            foreach ($preventasPorCliente as $cId => $itemsCliente) {
                $cIdInt = (int) $cId;
                $historial = $historial90DiasPorCliente[$cIdInt] ?? null;
                $tieneBase = ($historial && !empty($historial['skus']));

                $skusHoy = $itemsCliente->pluck('codigo_producto')->filter()->map(fn ($s) => trim((string) $s))->toArray();
                $categoriasHoy = $itemsCliente->pluck('categoria')->filter()->map(fn ($c) => trim(strtoupper((string) $c)))->toArray();

                if (!$tieneBase) {
                    $sinBaseApertura++;
                    $cumpleTienda = false;
                    $estadoTienda = 'Sin Base (Apertura)';
                } else {
                    $evaluablesReposicion++;
                    $skusHabituales = array_keys($historial['skus']);
                    $recomprados = array_intersect($skusHoy, $skusHabituales);
                    $cumpleTienda = count($recomprados) > 0;
                    $estadoTienda = $cumpleTienda ? 'Recompra Exitosa' : 'Omitió Habituales';

                    if ($cumpleTienda) {
                        $cumplenReposicion++;
                    }
                }

                $primerItem = $itemsCliente->first();
                $detalleReposicionTiendas[] = [
                    'cliente_id' => $cIdInt,
                    'codigo_cliente' => $primerItem->codigo_cliente ?? $cId,
                    'cliente' => $primerItem->cliente ?? "Cliente #{$cId}",
                    'tiene_base_historica' => $tieneBase,
                    'estado' => $estadoTienda,
                    'cumple' => $cumpleTienda,
                    'skus_pedidos_hoy' => count($skusHoy),
                    'skus_habituales_historicos' => $tieneBase ? count($historial['skus']) : 0,
                ];
            }

            $tasaReposicion = ($evaluablesReposicion > 0)
                ? round(($cumplenReposicion / $evaluablesReposicion) * 100, 1)
                : ($totalCompradores > 0 ? 100.0 : 0.0);

            $kpi6Cumple = ($tasaReposicion >= self::UMBRAL_REPOSICION_PCT);
            $kpi6Puntos = $kpi6Cumple ? 15 : 0;

            // ----------------------------------------------------
            // KPI 7: Ampliación de Portafolio (20 Puntos)
            // ----------------------------------------------------
            $cumplenAmpliacion = 0;
            $detalleAmpliacionTiendas = [];

            foreach ($preventasPorCliente as $cId => $itemsCliente) {
                $cIdInt = (int) $cId;
                $historial = $historial90DiasPorCliente[$cIdInt] ?? ['skus' => []];
                $skusHistoricos = array_keys($historial['skus']);

                $skusHoyUnicos = $itemsCliente->pluck('codigo_producto')->filter()->map(fn ($s) => trim((string) $s))->unique()->toArray();
                $skusNuevos = array_diff($skusHoyUnicos, $skusHistoricos);
                $cantNuevos = count($skusNuevos);

                $cumpleAmpliacionTienda = ($cantNuevos >= 2);
                if ($cumpleAmpliacionTienda) {
                    $cumplenAmpliacion++;
                }

                $primerItem = $itemsCliente->first();
                $detalleAmpliacionTiendas[] = [
                    'cliente_id' => $cIdInt,
                    'codigo_cliente' => $primerItem->codigo_cliente ?? $cId,
                    'cliente' => $primerItem->cliente ?? "Cliente #{$cId}",
                    'skus_hoy' => count($skusHoyUnicos),
                    'skus_nuevos_count' => $cantNuevos,
                    'skus_nuevos_lista' => array_values($skusNuevos),
                    'cumple' => $cumpleAmpliacionTienda,
                ];
            }

            $tasaAmpliacion = ($totalCompradores > 0)
                ? round(($cumplenAmpliacion / $totalCompradores) * 100, 1)
                : 0.0;

            $kpi7Cumple = ($tasaAmpliacion >= self::UMBRAL_AMPLIACION_PCT);
            $kpi7Puntos = $kpi7Cumple ? 20 : 0;

            // ----------------------------------------------------
            // KPI 8: Covers / Multicategoría (5 Puntos)
            // ----------------------------------------------------
            $cumplenCovers = 0;
            $detalleCoversTiendas = [];

            foreach ($preventasPorCliente as $cId => $itemsCliente) {
                $cIdInt = (int) $cId;
                $categoriasUnicas = $itemsCliente->pluck('categoria')->filter()
                    ->map(fn ($c) => trim((string) $c))
                    ->unique()
                    ->values()
                    ->toArray();

                $cantCategorias = count($categoriasUnicas);
                $cumpleCoversTienda = ($cantCategorias >= 2);
                if ($cumpleCoversTienda) {
                    $cumplenCovers++;
                }

                $primerItem = $itemsCliente->first();
                $detalleCoversTiendas[] = [
                    'cliente_id' => $cIdInt,
                    'codigo_cliente' => $primerItem->codigo_cliente ?? $cId,
                    'cliente' => $primerItem->cliente ?? "Cliente #{$cId}",
                    'categorias' => $categoriasUnicas,
                    'total_categorias' => $cantCategorias,
                    'cumple' => $cumpleCoversTienda,
                ];
            }

            $tasaCovers = ($totalCompradores > 0)
                ? round(($cumplenCovers / $totalCompradores) * 100, 1)
                : 0.0;

            $kpi8Cumple = ($tasaCovers >= self::UMBRAL_COVERS_PCT);
            $kpi8Puntos = $kpi8Cumple
                ? 5
                : (int) round(5 * ($tasaCovers / self::UMBRAL_COVERS_PCT));

            // ----------------------------------------------------
            // Score Total Unilever (0 a 100 Puntos)
            // ----------------------------------------------------
            $scoreTotal = $kpi1Puntos + $kpi2Puntos + $kpi3Puntos + $kpi4Puntos + $kpi5Puntos + $kpi6Puntos + $kpi7Puntos + $kpi8Puntos;

            // Construir detalle enriquecido de visitas de la ruta
            $listaClientesVisitas = [];
            foreach ($carteraRuta as $cRow) {
                $cId = (int) $cRow->cliente_id;
                $visInfo = $visitasDetallePorCliente[$cId] ?? null;
                $esActivo = ($cRow->estado === 'Activo');
                $fueVisitado = ($visInfo !== null);

                $tipoVisita = 'Pendiente';
                if ($fueVisitado) {
                    $tipoVisita = $esActivo ? 'En Plan' : 'Inactivo Visitado';
                }

                $listaClientesVisitas[] = [
                    'cliente_id' => $cId,
                    'codigo_cliente' => $cRow->codigo_cliente,
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
            foreach ($visitasDetallePorCliente as $cId => $vData) {
                if (!$carteraRuta->contains('cliente_id', $cId)) {
                    $listaClientesVisitas[] = [
                        'cliente_id' => $cId,
                        'codigo_cliente' => (string) $cId,
                        'cliente' => "Cliente #{$cId} (Fuera de Plan)",
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

                // Resumen KPI 6: Reposición
                'kpi6_reposicion' => [
                    'puntos' => $kpi6Puntos,
                    'puntos_max' => 15,
                    'cumple' => $kpi6Cumple,
                    'evaluables' => $evaluablesReposicion,
                    'sin_base_apertura' => $sinBaseApertura,
                    'cumplen' => $cumplenReposicion,
                    'tasa_pct' => $tasaReposicion,
                    'umbral_pct' => self::UMBRAL_REPOSICION_PCT,
                ],

                // Resumen KPI 7: Ampliación
                'kpi7_ampliacion' => [
                    'puntos' => $kpi7Puntos,
                    'puntos_max' => 20,
                    'cumple' => $kpi7Cumple,
                    'total_compradores' => $totalCompradores,
                    'cumplen' => $cumplenAmpliacion,
                    'tasa_pct' => $tasaAmpliacion,
                    'umbral_pct' => self::UMBRAL_AMPLIACION_PCT,
                ],

                // Resumen KPI 8: Covers
                'kpi8_covers' => [
                    'puntos' => $kpi8Puntos,
                    'puntos_max' => 5,
                    'cumple' => $kpi8Cumple,
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
}

