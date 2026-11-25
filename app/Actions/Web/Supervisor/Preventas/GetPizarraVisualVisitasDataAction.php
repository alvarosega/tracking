<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPizarraVisualVisitasDataAction
{
    private const DIAS_MAP = [
        1 => ['lunes', 'Lunes'],
        2 => ['martes', 'Martes'],
        3 => ['miercoles', 'miércoles', 'Miercoles', 'Miércoles'],
        4 => ['jueves', 'Jueves'],
        5 => ['viernes', 'Viernes'],
        6 => ['sabado', 'sábado', 'Sabado', 'Sábado'],
        7 => ['domingo', 'Domingo'],
    ];

    private const ROUTE_PALETTE = [
        '#0071E3', // Apple Blue
        '#34C759', // Apple Green
        '#FF9500', // Apple Orange
        '#AF52DE', // Apple Purple
        '#FF2D55', // Apple Pink
        '#5856D6', // Apple Indigo
        '#00C7BE', // Apple Teal
        '#FF3B30', // Apple Red
        '#A2845E', // Apple Brown
        '#64D2FF', // Apple Sky
        '#30B0C7', // Apple Cyan
        '#32ADE6', // Apple Ocean
        '#5E5CE6', // Apple Violet
        '#BF5AF2', // Apple Magenta
        '#FF375F', // Apple Rose
        '#FFD60A', // Apple Yellow
    ];

    /**
     * Consulta consolidada geográfica de clientes y visitas para múltiples rutas.
     *
     * @param string|null $fecha
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @return array<string, mixed>
     */
    public function execute(?string $fecha = null, array $canales = [], array $rutas = []): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes', 'Lunes'];
        $diasFiltro = array_values(array_unique(array_merge($tuplaDias, array_map('strtolower', $tuplaDias))));

        $canalesClean = array_values(array_filter(array_map(fn($c) => strtoupper(trim((string)$c)), $canales)));
        $rutasClean = array_values(array_filter(array_map(fn($r) => strtoupper(trim((string)$r)), $rutas)));

        // 1. Obtener catálogo y filtrar rutas objetivo
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

        // Si no se especificaron rutas explícitas ni canales, limitamos a las rutas del catálogo
        $rutasObjetivo = $catalogoRutas->keys()->all();

        if (empty($rutasObjetivo)) {
            return $this->generarRespuestaVacia($fechaNorm);
        }

        // Asignar colores a las rutas
        $coloresRuta = [];
        foreach ($rutasObjetivo as $idx => $r) {
            $coloresRuta[$r] = self::ROUTE_PALETTE[$idx % count(self::ROUTE_PALETTE)];
        }

        // 2. Cartera Plan del día desde pan_ruteo
        $carteraPlan = DB::connection('supervisor')->table('pan_ruteo')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
            ->where(function ($q) use ($diasFiltro): void {
                $q->whereIn('dia_norm', $diasFiltro)
                  ->orWhereIn('dia', $diasFiltro);
            })
            ->where('cliente_id', '>', 0)
            ->whereRaw("TRIM(UPPER(COALESCE(estado, ''))) = 'ACTIVO'")
            ->select([
                'cliente_id',
                'ruta',
                'canal',
                DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente), ''), NULLIF(TRIM(cliente_norm), ''), CONCAT('Cliente #', cliente_id))) as cliente"),
                DB::raw("MAX(COALESCE(NULLIF(TRIM(cliente_norm), ''), NULLIF(TRIM(cliente), ''), CONCAT('Cliente #', cliente_id))) as cliente_norm"),
                DB::raw("MAX(COALESCE(TRIM(territorio), '-')) as territorio"),
                DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
                DB::raw("MAX(COALESCE(TRIM(referencia), '-')) as referencia"),
                DB::raw("MAX(latitud) as latitud"),
                DB::raw("MAX(longitud) as longitud"),
                DB::raw("MAX(vendedor) as vendedor"),
            ])
            ->groupBy('cliente_id', 'ruta', 'canal')
            ->get();

        // Agrupación por clave única ruta + cliente_id
        $planMap = [];
        $planPorRuta = [];
        $clientesPlanIdSet = [];

        foreach ($carteraPlan as $p) {
            $rUpper = strtoupper(trim((string)$p->ruta));
            $cid = (int)$p->cliente_id;
            $planKey = "{$rUpper}_{$cid}";
            $planMap[$planKey] = $p;
            $planPorRuta[$rUpper][$cid] = $p;
            $clientesPlanIdSet[$cid] = true;
        }

        // 3. Montos de preventa del día (fact_preventas)
        $preventasMontoPorClave = [];
        try {
            $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasObjetivo)
                ->where('cliente_id', '>', 0)
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta"),
                    'cliente_id',
                    DB::raw("SUM(monto_final) as total_monto")
                ])
                ->groupBy(DB::raw("TRIM(UPPER(ruta))"), 'cliente_id')
                ->get();

            foreach ($preventasHoy as $ph) {
                $k = strtoupper(trim((string)$ph->ruta)) . '_' . (int)$ph->cliente_id;
                $preventasMontoPorClave[$k] = (float)$ph->total_monto;
            }
        } catch (\Throwable $e) {
            Log::debug('[PizarraVisual] Error consultando fact_preventas: ' . $e->getMessage());
        }

        // 4. Marcajes Web ERP (fact_visitas)
        $vendedoresLista = [];
        foreach ($catalogoRutas as $dim) {
            if (!empty($dim->vendedor)) {
                $vendedoresLista[] = trim((string)$dim->vendedor);
            }
            if (!empty($dim->vendedor_norm)) {
                $vendedoresLista[] = trim((string)$dim->vendedor_norm);
            }
        }
        $vendedoresLista = array_values(array_unique(array_filter($vendedoresLista)));

        $visitasWebPorClave = [];
        if (!empty($vendedoresLista)) {
            try {
                $visitasWeb = DB::connection('supervisor')->table('fact_visitas as fv')
                    ->where('fv.fecha_norm', $fechaNorm)
                    ->where(function ($q) use ($vendedoresLista): void {
                        $q->whereIn('fv.empleado', $vendedoresLista);
                        foreach ($vendedoresLista as $v) {
                            $q->orWhereRaw("TRIM(LOWER(fv.empleado_norm)) = ?", [strtolower($v)]);
                        }
                    })
                    ->select([
                        'fv.cliente',
                        'fv.cliente_norm',
                        'fv.empleado',
                        'fv.tipo',
                        'fv.motivo',
                        'fv.check_in',
                        'fv.check_out',
                        'fv.monto',
                    ])
                    ->get();

                foreach ($visitasWeb as $vw) {
                    $nomNorm = $this->normTxt((string)$vw->cliente);
                    $nomAlfa = $this->normalizarAlfanumerico((string)$vw->cliente);
                    $visitasWebPorClave[] = [
                        'cliente' => (string)$vw->cliente,
                        'norm' => $nomNorm,
                        'alfa' => $nomAlfa,
                        'check_in' => (string)$vw->check_in,
                        'tipo' => (string)$vw->tipo,
                        'motivo' => (string)$vw->motivo,
                        'monto' => (float)($vw->monto ?: 0.0),
                    ];
                }
            } catch (\Throwable $e) {
                Log::debug('[PizarraVisual] Error consultando fact_visitas: ' . $e->getMessage());
            }
        }

        // 5. Visitas de App Móvil (tabla visitas)
        $visitasMovilRaw = Visita::query()
            ->whereBetween('visited_at', ["{$fechaNorm} 00:00:00", "{$fechaNorm} 23:59:59"])
            ->whereIn(DB::raw("TRIM(UPPER(route))"), $rutasObjetivo)
            ->orderBy('visited_at', 'asc')
            ->get();

        $visitasMovilPorRutaCliente = [];
        $visitasMovilFueraPlan = [];

        foreach ($visitasMovilRaw as $vm) {
            $rUpper = strtoupper(trim((string)$vm->route));
            $cid = (int)($vm->client_id ?? 0);

            $horaStr = $vm->visited_at ? $vm->visited_at->format('H:i:s') : null;
            $fotoUrl = $this->formatearUrlFoto($vm->photo_path);

            $item = [
                'id' => $vm->id,
                'ruta' => $rUpper,
                'cliente_id' => $cid,
                'cliente_nombre' => (string)($vm->opportunity_client_name ?: ''),
                'status' => (string)($vm->status ?? ''),
                'comments' => (string)($vm->comments ?? ''),
                'latitude' => $vm->latitude ? (float)$vm->latitude : null,
                'longitude' => $vm->longitude ? (float)$vm->longitude : null,
                'accuracy' => $vm->accuracy ? (float)$vm->accuracy : null,
                'hora' => $horaStr,
                'foto_url' => $fotoUrl,
                'is_opportunity' => (bool)$vm->is_opportunity,
            ];

            if ($cid > 0 && isset($planPorRuta[$rUpper][$cid])) {
                $k = "{$rUpper}_{$cid}";
                if (!isset($visitasMovilPorRutaCliente[$k])) {
                    $visitasMovilPorRutaCliente[$k] = $item;
                }
            } else {
                $visitasMovilFueraPlan[] = $item;
            }
        }

        // 6. Procesar y clasificar clientes
        $clientesResultado = [];
        $recorridosPorRuta = [];
        $distribucionHoras = $this->inicializarDistribucionHoraria();

        // Inicializar métricas por ruta
        $rutasStats = [];
        foreach ($rutasObjetivo as $r) {
            $dim = $catalogoRutas[$r] ?? null;
            $rutasStats[$r] = [
                'ruta' => $r,
                'canal' => $dim ? (string)$dim->canal : '-',
                'vendedor' => $dim ? (string)$dim->vendedor : 'No Asignado',
                'color' => $coloresRuta[$r],
                'cartera_plan' => 0,
                'visitados_con_venta' => 0,
                'visitados_sin_venta' => 0,
                'total_visitados' => 0,
                'pendientes' => 0,
                'fuera_plan' => 0,
                'monto_total' => 0.0,
                'cobertura_pct' => 0.0,
                'efectividad_pct' => 0.0,
            ];
            $recorridosPorRuta[$r] = [
                'ruta' => $r,
                'canal' => $dim ? (string)$dim->canal : '-',
                'vendedor' => $dim ? (string)$dim->vendedor : 'No Asignado',
                'color' => $coloresRuta[$r],
                'puntos' => [],
            ];
        }

        // 6.1 Procesar clientes en Cartera Plan
        foreach ($carteraPlan as $p) {
            $rUpper = strtoupper(trim((string)$p->ruta));
            $cid = (int)$p->cliente_id;
            $planKey = "{$rUpper}_{$cid}";

            if (!isset($rutasStats[$rUpper])) {
                continue;
            }

            $rutasStats[$rUpper]['cartera_plan']++;

            $movil = $visitasMovilPorRutaCliente[$planKey] ?? null;
            $montoPrev = $preventasMontoPorClave[$planKey] ?? 0.0;

            // Revisar cruce con Web ERP
            $webItem = $this->buscarVisitaWebParaCliente($p, $visitasWebPorClave);
            $montoWeb = $webItem ? $webItem['monto'] : 0.0;
            $montoFinal = max($montoPrev, $montoWeb);

            $esVisitado = ($movil !== null || $webItem !== null);
            $tieneVenta = ($montoFinal > 0);

            // Hora de visita
            $horaVisita = $movil['hora'] ?? ($webItem['check_in'] ?? null);
            if ($horaVisita === '00:00:00' || $horaVisita === '00:00') {
                $horaVisita = null;
            }

            // Coordenadas: preferimos las coordenadas reales tomadas en la app móvil; si no, del padrón
            $lat = $movil['latitude'] ?? ($p->latitud ? (float)$p->latitud : null);
            $lng = $movil['longitude'] ?? ($p->longitud ? (float)$p->longitud : null);

            // Clasificación de estado
            if ($esVisitado) {
                if ($tieneVenta) {
                    $estadoVisita = 'visitado_con_venta';
                    $rutasStats[$rUpper]['visitados_con_venta']++;
                } else {
                    $estadoVisita = 'visitado_sin_venta';
                    $rutasStats[$rUpper]['visitados_sin_venta']++;
                }
                $rutasStats[$rUpper]['total_visitados']++;
                $rutasStats[$rUpper]['monto_total'] += $montoFinal;

                // Contabilizar en histograma horario
                if ($horaVisita) {
                    $this->acumularHora($distribucionHoras, $horaVisita, $tieneVenta);
                }

                // Agregar al recorrido cronológico si tiene coordenadas válidas
                if ($lat && $lng && $this->coordenadasValidas($lat, $lng)) {
                    $recorridosPorRuta[$rUpper]['puntos'][] = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'hora' => $horaVisita ?: 'Sin hora',
                        'cliente' => (string)$p->cliente,
                        'cliente_id' => $cid,
                        'con_venta' => $tieneVenta,
                        'monto' => $montoFinal,
                    ];
                }
            } else {
                $estadoVisita = 'pendiente';
                $rutasStats[$rUpper]['pendientes']++;
            }

            $motivoTipo = $this->determinarTipoVisita(
                $esVisitado,
                $montoFinal,
                $webItem['tipo'] ?? null,
                $webItem['motivo'] ?? null,
                $movil['status'] ?? null
            );

            $clientesResultado[] = [
                'id' => "plan_{$planKey}",
                'cliente_id' => $cid,
                'cliente' => (string)$p->cliente,
                'ruta' => $rUpper,
                'canal' => (string)($p->canal ?: ($rutasStats[$rUpper]['canal'] ?? '-')),
                'vendedor' => (string)($p->vendedor ?: ($rutasStats[$rUpper]['vendedor'] ?? '-')),
                'color_ruta' => $coloresRuta[$rUpper] ?? '#0071E3',
                'lat' => $lat,
                'lng' => $lng,
                'tiene_coords' => ($lat && $lng && $this->coordenadasValidas($lat, $lng)),
                'en_plan' => true,
                'estado_visita' => $estadoVisita,
                'tipo_motivo' => $motivoTipo,
                'monto' => $montoFinal,
                'hora_visita' => $horaVisita,
                'foto_url' => $movil['foto_url'] ?? null,
                'direccion' => (string)($p->direccion ?: '-'),
                'referencia' => (string)($p->referencia ?: '-'),
                'territorio' => (string)($p->territorio ?: '-'),
            ];
        }

        // 6.2 Procesar visitas Fuera de Plan
        foreach ($visitasMovilFueraPlan as $idx => $fp) {
            $rUpper = $fp['ruta'];
            if (!isset($rutasStats[$rUpper])) {
                continue;
            }

            $cid = (int)$fp['cliente_id'];
            $planKey = "{$rUpper}_{$cid}";
            $montoPrev = $preventasMontoPorClave[$planKey] ?? 0.0;
            $tieneVenta = ($montoPrev > 0);

            $rutasStats[$rUpper]['fuera_plan']++;
            $rutasStats[$rUpper]['monto_total'] += $montoPrev;

            $lat = $fp['latitude'];
            $lng = $fp['longitude'];
            $horaVisita = $fp['hora'];

            if ($horaVisita) {
                $this->acumularHora($distribucionHoras, $horaVisita, $tieneVenta);
            }

            if ($lat && $lng && $this->coordenadasValidas($lat, $lng)) {
                $recorridosPorRuta[$rUpper]['puntos'][] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'hora' => $horaVisita ?: 'Sin hora',
                    'cliente' => $fp['cliente_nombre'] ?: "Cliente #{$cid}",
                    'cliente_id' => $cid,
                    'con_venta' => $tieneVenta,
                    'monto' => $montoPrev,
                    'fuera_plan' => true,
                ];
            }

            $motivoTipo = $this->determinarTipoVisita(
                true,
                $montoPrev,
                null,
                null,
                $fp['status'],
                $fp['is_opportunity']
            );

            $clientesResultado[] = [
                'id' => "fp_{$rUpper}_{$cid}_{$idx}",
                'cliente_id' => $cid > 0 ? $cid : null,
                'cliente' => $fp['cliente_nombre'] ?: ($cid > 0 ? "Cliente #{$cid}" : "Cliente Fuera de Plan"),
                'ruta' => $rUpper,
                'canal' => $rutasStats[$rUpper]['canal'] ?? '-',
                'vendedor' => $rutasStats[$rUpper]['vendedor'] ?? '-',
                'color_ruta' => $coloresRuta[$rUpper] ?? '#0071E3',
                'lat' => $lat,
                'lng' => $lng,
                'tiene_coords' => ($lat && $lng && $this->coordenadasValidas($lat, $lng)),
                'en_plan' => false,
                'estado_visita' => 'fuera_plan',
                'tipo_motivo' => $motivoTipo,
                'monto' => $montoPrev,
                'hora_visita' => $horaVisita,
                'foto_url' => $fp['foto_url'],
                'direccion' => '-',
                'referencia' => '-',
                'territorio' => '-',
            ];
        }

        // 7. Ordenar cronológicamente puntos de recorrido de cada ruta
        $recorridosFinales = [];
        foreach ($recorridosPorRuta as $r => $rec) {
            if (!empty($rec['puntos'])) {
                usort($rec['puntos'], fn($a, $b) => strcmp((string)$a['hora'], (string)$b['hora']));
                $recorridosFinales[] = $rec;
            }
        }

        // 8. Calcular porcentajes y consolidar métricas globales
        $totales = [
            'total_cartera_plan' => 0,
            'visitados_con_venta' => 0,
            'visitados_sin_venta' => 0,
            'total_visitados' => 0,
            'pendientes' => 0,
            'fuera_plan' => 0,
            'monto_total' => 0.0,
            'total_con_coords' => 0,
            'total_sin_coords' => 0,
            'cobertura_pct' => 0.0,
            'efectividad_pct' => 0.0,
        ];

        foreach ($rutasStats as $r => &$st) {
            $plan = $st['cartera_plan'];
            $vis = $st['total_visitados'];
            $st['cobertura_pct'] = $plan > 0 ? round(($vis / $plan) * 100, 1) : 0.0;
            $st['efectividad_pct'] = $vis > 0 ? round(($st['visitados_con_venta'] / $vis) * 100, 1) : 0.0;

            $totales['total_cartera_plan'] += $st['cartera_plan'];
            $totales['visitados_con_venta'] += $st['visitados_con_venta'];
            $totales['visitados_sin_venta'] += $st['visitados_sin_venta'];
            $totales['total_visitados'] += $st['total_visitados'];
            $totales['pendientes'] += $st['pendientes'];
            $totales['fuera_plan'] += $st['fuera_plan'];
            $totales['monto_total'] += $st['monto_total'];
        }
        unset($st);

        foreach ($clientesResultado as $c) {
            if ($c['tiene_coords']) {
                $totales['total_con_coords']++;
            } else {
                $totales['total_sin_coords']++;
            }
        }

        $planGlobal = $totales['total_cartera_plan'];
        $visGlobal = $totales['total_visitados'];
        $totales['cobertura_pct'] = $planGlobal > 0 ? round(($visGlobal / $planGlobal) * 100, 1) : 0.0;
        $totales['efectividad_pct'] = $visGlobal > 0 ? round(($totales['visitados_con_venta'] / $visGlobal) * 100, 1) : 0.0;

        return [
            'fecha' => $fechaNorm,
            'totales' => $totales,
            'rutas_stats' => array_values($rutasStats),
            'clientes' => $clientesResultado,
            'recorridos' => $recorridosFinales,
            'distribucion_horaria' => $distribucionHoras,
        ];
    }

    private function coordenadasValidas(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) return false;
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) return false;
        // Rango geográfico general de Bolivia / Sudamérica
        return ($lat >= -28.0 && $lat <= -5.0 && $lng >= -75.0 && $lng <= -50.0);
    }

    private function formatearUrlFoto(?string $path): ?string
    {
        if (!$path) return null;
        $clean = ltrim(str_replace('\\', '/', $path), '/');
        if ($clean === '') return null;
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $clean;
        }
        if (str_starts_with($clean, 'storage/')) {
            return '/' . $clean;
        }
        return '/storage/' . $clean;
    }

    private function normTxt(string $str): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $str) ?? ''));
    }

    private function normalizarAlfanumerico(?string $str): string
    {
        if (!$str) return '';
        $s = $this->normTxt($str);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    private function buscarVisitaWebParaCliente(object $p, array $visitasWeb): ?array
    {
        $n1 = $this->normTxt((string)$p->cliente);
        $n2 = $this->normTxt((string)$p->cliente_norm);
        $a1 = $this->normalizarAlfanumerico((string)$p->cliente);
        $a2 = $this->normalizarAlfanumerico((string)$p->cliente_norm);

        foreach ($visitasWeb as $vw) {
            if ($n1 !== '' && $vw['norm'] === $n1) return $vw;
            if ($n2 !== '' && $vw['norm'] === $n2) return $vw;
            if ($a1 !== '' && $vw['alfa'] === $a1) return $vw;
            if ($a2 !== '' && $vw['alfa'] === $a2) return $vw;
        }

        return null;
    }

    private function determinarTipoVisita(
        bool $visitado,
        float $monto,
        ?string $webTipo,
        ?string $webMotivo,
        ?string $appStatus,
        bool $isOpportunity = false
    ): string {
        if (!$visitado) {
            return 'No Visitado';
        }

        if ($monto > 0) {
            return 'PreVenta';
        }

        $wTipo = strtoupper(trim((string)$webTipo));
        $wMotivo = strtoupper(trim((string)$webMotivo));
        $aStatus = strtoupper(trim((string)$appStatus));

        if ($wTipo === 'PREVENTA' || $aStatus === 'PREVENTA') {
            return 'PreVenta';
        }

        $cands = array_filter([$wMotivo, $aStatus, $wTipo]);
        foreach ($cands as $c) {
            if (str_contains($c, 'CERRAD')) return 'Tienda Cerrada';
            if (str_contains($c, 'DINERO')) return 'Sin Dinero';
            if (str_contains($c, 'STOCK')) return 'Con Stock';
            if (str_contains($c, 'AUSENTE') || str_contains($c, 'NO ESTA')) return 'Ausente';
            if (str_contains($c, 'RECHAZ')) return 'Rechazado';
            if (str_contains($c, 'COBRANZA')) return 'Cobranza';
        }

        if ($isOpportunity) return 'Oportunidad';
        if ($wTipo === 'NO ATENDIDO' || $wTipo === 'NO ATENDIDA') return 'No Atendido';

        return 'Visita';
    }

    private function inicializarDistribucionHoraria(): array
    {
        $rangos = [
            '07:00-08:00',
            '08:00-09:00',
            '09:00-10:00',
            '10:00-11:00',
            '11:00-12:00',
            '12:00-13:00',
            '13:00-14:00',
            '14:00-15:00',
            '15:00-16:00',
            '16:00-17:00',
            '17:00-18:00',
            '18:00+',
        ];

        $arr = [];
        foreach ($rangos as $r) {
            $arr[] = [
                'rango' => $r,
                'con_venta' => 0,
                'sin_venta' => 0,
                'total' => 0,
            ];
        }
        return $arr;
    }

    private function acumularHora(array &$dist, string $horaStr, bool $conVenta): void
    {
        $partes = explode(':', $horaStr);
        $horaInt = (int)($partes[0] ?? -1);

        $indice = match (true) {
            $horaInt < 8 => 0,
            $horaInt === 8 => 1,
            $horaInt === 9 => 2,
            $horaInt === 10 => 3,
            $horaInt === 11 => 4,
            $horaInt === 12 => 5,
            $horaInt === 13 => 6,
            $horaInt === 14 => 7,
            $horaInt === 15 => 8,
            $horaInt === 16 => 9,
            $horaInt === 17 => 10,
            default => 11,
        };

        if (isset($dist[$indice])) {
            if ($conVenta) {
                $dist[$indice]['con_venta']++;
            } else {
                $dist[$indice]['sin_venta']++;
            }
            $dist[$indice]['total']++;
        }
    }

    private function generarRespuestaVacia(string $fecha): array
    {
        return [
            'fecha' => $fecha,
            'totales' => [
                'total_cartera_plan' => 0,
                'visitados_con_venta' => 0,
                'visitados_sin_venta' => 0,
                'total_visitados' => 0,
                'pendientes' => 0,
                'fuera_plan' => 0,
                'monto_total' => 0.0,
                'total_con_coords' => 0,
                'total_sin_coords' => 0,
                'cobertura_pct' => 0.0,
                'efectividad_pct' => 0.0,
            ],
            'rutas_stats' => [],
            'clientes' => [],
            'recorridos' => [],
            'distribucion_horaria' => $this->inicializarDistribucionHoraria(),
        ];
    }
}
