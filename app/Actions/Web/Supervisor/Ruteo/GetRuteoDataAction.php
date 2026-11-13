<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FronteraDia;
use App\Models\Supervisor\FronteraRuta;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\Ruta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetRuteoDataAction
{
    /**
     * Consulta y estructura los puntos de clientes y polígonos fronterizos para el Visor GIS.
     *
     * @param array<string> $canales
     * @param array<string> $rutas
     * @param array<string> $dias
     * @param bool $verFronteraRutas
     * @param bool $verFronteraDias
     * @param bool $soloActivos
     * @param string $modoVista 'original' | 'cambios'
     * @param string $estadoCliente 'activos' | 'inactivos' | 'todos'
     * @return array{clientes: array, fronteras_rutas: array, fronteras_dias: array, kpis: array}
     */
    public function execute(
        array $canales = [],
        array $rutas = [],
        array $dias = [],
        bool $verFronteraRutas = false,
        bool $verFronteraDias = false,
        bool $soloActivos = true,
        string $modoVista = 'original',
        string $estadoCliente = ''
    ): array {
        $canalesClean = array_values(array_unique(array_filter(array_map(fn($c) => trim((string) $c), $canales))));

        if (empty($rutas) && empty($canalesClean)) {
            // Si no se especificaron rutas ni canales, obtener todas las rutas disponibles en dim_rutas
            $rutas = Ruta::query()
                ->whereNotNull('ruta')
                ->pluck('ruta')
                ->map(fn($r) => trim((string) $r))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        } elseif (empty($rutas) && !empty($canalesClean)) {
            $rutas = Ruta::query()
                ->whereNotNull('ruta')
                ->where(function ($q) use ($canalesClean) {
                    $q->whereIn('canal', $canalesClean);
                    foreach ($canalesClean as $c) {
                        $q->orWhereRaw('TRIM(canal) = ?', [$c])
                          ->orWhereRaw('LOWER(TRIM(canal)) = ?', [strtolower($c)]);
                    }
                })
                ->pluck('ruta')
                ->map(fn($r) => trim((string) $r))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Respaldo adicional si dim_rutas no arrojó rutas pero pan_ruteo pudiera contenerlas
            if (empty($rutas)) {
                try {
                    $rutas = PanRuteo::query()
                        ->whereNotNull('ruta')
                        ->where(function ($q) use ($canalesClean) {
                            $q->whereIn('canal', $canalesClean);
                            foreach ($canalesClean as $c) {
                                $q->orWhereRaw('TRIM(canal) = ?', [$c])
                                  ->orWhereRaw('LOWER(TRIM(canal)) = ?', [strtolower($c)]);
                            }
                        })
                        ->pluck('ruta')
                        ->map(fn($r) => trim((string) $r))
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();
                } catch (\Throwable $e) {
                    // Ignorar si pan_ruteo no contiene la columna canal
                }
            }
        }

        $rutasUpper = array_values(array_unique(array_map(fn($r) => strtoupper(trim((string) $r)), $rutas)));
        $rutasVariantes = array_values(array_unique(array_filter(array_merge(
            $rutas,
            $rutasUpper,
            array_map('strtolower', $rutasUpper)
        ))));

        if (empty($rutasVariantes)) {
            return [
                'clientes' => [],
                'fronteras_rutas' => [],
                'fronteras_dias' => [],
                'kpis' => [
                    'total' => 0,
                    'activos' => 0,
                    'inactivos' => 0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                    'con_cambios' => 0,
                    'total_propuestas' => 0,
                ],
            ];
        }

        // 1. Consultar propuestas activas de auditoria_saneamiento_propuestas
        $propuestasRows = DB::connection('supervisor')
            ->table('auditoria_saneamiento_propuestas')
            ->whereIn('estado', ['PENDIENTE', 'APLICADO'])
            ->orderBy('id', 'desc')
            ->get();

        $propuestasMap = [];
        $cidsOrigenEnRutas = [];
        $cidsDestinoEnRutas = [];

        foreach ($propuestasRows as $p) {
            $cid = (int) $p->cliente_id_origen;
            if (!isset($propuestasMap[$cid])) {
                $propuestasMap[$cid] = $p;
                $rAct = strtoupper(trim((string)$p->ruta_actual));
                $rProp = strtoupper(trim((string)$p->ruta_propuesta));

                if (in_array($rAct, $rutasUpper, true)) {
                    $cidsOrigenEnRutas[] = $cid;
                }
                if (in_array($rProp, $rutasUpper, true)) {
                    $cidsDestinoEnRutas[] = $cid;
                }
            }
        }

        $cidsAfectados = array_values(array_unique(array_merge($cidsOrigenEnRutas, $cidsDestinoEnRutas)));
        $totalPropuestasEnRutas = count($cidsAfectados);

        // 2. Consulta de clientes en pan_ruteo
        if ($modoVista === 'original') {
            // EN MODO ORIGINAL: Consultar estrictamente la asignación inmutable de pan_ruteo
            $queryClientes = PanRuteo::query()->whereIn('ruta', $rutasVariantes);
        } else {
            // EN MODO CAMBIOS: Consultar los clientes de las rutas seleccionadas más cualquier cliente
            // que tenga propuesta de reasignación vinculada a estas rutas (entrante o saliente)
            $queryClientes = PanRuteo::query()->where(function ($q) use ($rutasVariantes, $cidsAfectados) {
                $q->whereIn('ruta', $rutasVariantes);
                if (!empty($cidsAfectados)) {
                    $q->orWhereIn('cliente_id', $cidsAfectados);
                }
            });
        }

        // Normalizar filtro de estado ('activos' | 'inactivos' | 'todos')
        $estadoTarget = strtolower(trim($estadoCliente));
        if (!in_array($estadoTarget, ['activos', 'inactivos', 'todos'], true)) {
            $estadoTarget = $soloActivos ? 'activos' : 'todos';
        }

        if ($estadoTarget === 'activos') {
            $queryClientes->where(function ($q) {
                $q->where('estado', 'Activo')
                  ->orWhereNull('estado')
                  ->orWhere('estado', '');
            });
        } elseif ($estadoTarget === 'inactivos') {
            $queryClientes->where(function ($q) {
                $q->whereNotNull('estado')
                  ->where('estado', '!=', '')
                  ->whereRaw("LOWER(TRIM(estado)) != 'activo'");
            });
        }

        $diasMerge = [];
        if (!empty($dias)) {
            $diasLower = array_map(fn($d) => strtolower(trim((string) $d)), $dias);
            $diasUpper = array_map(fn($d) => strtoupper(trim((string) $d)), $dias);
            $diasConTildes = [];
            foreach (array_merge($diasLower, $diasUpper) as $d) {
                $diasConTildes[] = $d;
                if (str_contains(strtolower($d), 'miercoles')) {
                    $diasConTildes[] = 'Miércoles';
                    $diasConTildes[] = 'miércoles';
                    $diasConTildes[] = 'MIÉRCOLES';
                }
                if (str_contains(strtolower($d), 'sabado')) {
                    $diasConTildes[] = 'Sábado';
                    $diasConTildes[] = 'sábado';
                    $diasConTildes[] = 'SÁBADO';
                }
            }
            $diasMerge = array_values(array_unique($diasConTildes));
            if ($modoVista === 'original') {
                $queryClientes->whereIn('dia_norm', $diasMerge);
            }
        }

        $clientesRaw = $queryClientes->select([
            'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
            'direccion', 'contacto', 'celular', 'latitud', 'longitud',
            'estado', 'ruta', 'dia_norm'
        ])->get();

        // 3. Consulta agregada de métricas históricas de fact_ventas (Mes actual y mes anterior)
        $now = Carbon::now();
        try {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            if ($maxFecha) {
                $now = Carbon::parse($maxFecha);
            }
        } catch (\Throwable $e) {
            // Silenciar si fact_ventas falla temporalmente
        }

        $baseDate = $now->copy()->startOfMonth();
        $inicioHistorico = $baseDate->copy()->subMonths(10)->toDateString();
        $finHistorico = $now->copy()->endOfMonth()->toDateString();

        $mesesEspanol = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        $mesesCorto = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
        ];

        // 11 Meses históricos exactos (Mes actual y 10 meses atrás) sin desbordamiento de fecha
        $periodosHistoricos = [];
        for ($i = 0; $i <= 10; $i++) {
            $dt = $baseDate->copy()->subMonths($i);
            $pKey = $dt->format('Y-m');
            $mNum = $dt->format('m');
            $periodosHistoricos[] = [
                'periodo' => $pKey,
                'nombre' => ($mesesEspanol[$mNum] ?? 'Mes') . ' ' . $dt->format('Y'),
                'corto' => ($mesesCorto[$mNum] ?? '') . ' ' . $dt->format('y'),
                'es_actual' => ($i === 0),
                'offset' => $i,
            ];
        }

        $mesActualPeriodo = $periodosHistoricos[0]['periodo'];
        $mesAnteriorPeriodo = $periodosHistoricos[1]['periodo'] ?? $mesActualPeriodo;
        $mesAnterior2Periodo = $periodosHistoricos[2]['periodo'] ?? $mesActualPeriodo;

        $cidsTodos = $clientesRaw->pluck('cliente_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $ventasMap = [];

        if (!empty($cidsTodos)) {
            try {
                foreach (array_chunk($cidsTodos, 1000) as $chunkIds) {
                    $ventasRows = DB::connection('supervisor')->table('fact_ventas')
                        ->whereIn('cliente_id', $chunkIds)
                        ->where('cliente_id', '>', 0)
                        ->whereBetween('fecha_norm', [$inicioHistorico, $finHistorico])
                        ->where('monto_final', '>', 0)
                        ->where(function ($q) {
                            $q->whereNull('revertida')
                              ->orWhereNotIn(DB::raw("LOWER(TRIM(revertida))"), ['si', 'sí', 'true', '1', 'anulada', 'revertida', 'yes', 'verdadero']);
                        })
                        ->select([
                            'cliente_id',
                            DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m') as periodo"),
                            DB::raw("COALESCE(SUM(monto_final), 0) as monto_acumulado"),
                            DB::raw("COUNT(DISTINCT COALESCE(NULLIF(nro_factura, ''), venta_id)) as frecuencia_compra"),
                        ])
                        ->groupBy('cliente_id', DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m')"))
                        ->get();

                    foreach ($ventasRows as $vr) {
                        $cid = (int) $vr->cliente_id;
                        $periodo = (string) $vr->periodo;
                        $ventasMap[$cid][$periodo] = [
                            'monto' => round((float) ($vr->monto_acumulado ?? 0.0), 2),
                            'frecuencia' => (int) ($vr->frecuencia_compra ?? 0),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Silenciar si fact_ventas no está accesible
            }
        }

        // Evaluar clientes filtrados contra todo el padrón pan_ruteo (días múltiples y rutas múltiples)
        $diasPorClientePan = [];
        $rutasPorClientePan = [];
        $asignacionesPorClientePan = [];

        if (!empty($cidsTodos)) {
            try {
                foreach (array_chunk($cidsTodos, 1000) as $chunkIds) {
                    $panMultiplesRaw = DB::connection('supervisor')->table('pan_ruteo as p')
                        ->whereIn('p.cliente_id', $chunkIds)
                        ->where(function ($w) {
                            $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                                ->orWhereNull('p.estado')
                                ->orWhereRaw("p.estado = ''");
                        })
                        ->select([
                            'p.cliente_id',
                            DB::raw("TRIM(UPPER(p.ruta)) as ruta"),
                            DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') as dia"),
                        ])
                        ->get();

                    foreach ($panMultiplesRaw as $row) {
                        $cid = (int) $row->cliente_id;
                        $r = strtoupper(trim((string) $row->ruta));
                        $d = strtoupper(trim((string) $row->dia));
                        if ($d === '' || $d === 'SIN DIA') {
                            $d = 'SIN DIA';
                        }

                        if ($d !== 'SIN DIA') {
                            $diasPorClientePan[$cid][$d] = true;
                        }
                        if ($r !== '') {
                            $rutasPorClientePan[$cid][$r] = true;
                            $asignacionesPorClientePan[$cid][$r . '__' . $d] = [
                                'ruta' => $r,
                                'dia' => $d,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silenciar si pan_ruteo no responde temporalmente
            }
        }

        $cidsMultidia = [];
        $cidsMultiruta = [];
        $cidsMultiples = [];

        $kpis = [
            'total' => 0,
            'con_gps' => 0,
            'sin_gps' => 0,
            'activos' => 0,
            'inactivos' => 0,
            'con_cambios' => 0,
            'total_propuestas' => $totalPropuestasEnRutas,
            'total_multidia' => 0,
            'total_multiruta' => 0,
            'total_multiples' => 0,
        ];
        $clientes = [];

        foreach ($clientesRaw as $c) {
            $cid = (int)$c->cliente_id;
            $prop = $propuestasMap[$cid] ?? null;
            $tienePropuesta = ($prop !== null);

            $vActual = $ventasMap[$cid][$mesActualPeriodo] ?? ['monto' => 0.0, 'frecuencia' => 0];
            $vAnterior = $ventasMap[$cid][$mesAnteriorPeriodo] ?? ['monto' => 0.0, 'frecuencia' => 0];
            $vAnterior2 = $ventasMap[$cid][$mesAnterior2Periodo] ?? ['monto' => 0.0, 'frecuencia' => 0];

            $rutaOriginal = strtoupper(trim((string)$c->ruta));
            $diaOriginal = strtoupper(trim((string)$c->dia_norm));

            $diasAsignadosPan = array_values(array_keys($diasPorClientePan[$cid] ?? []));
            if (empty($diasAsignadosPan) && $diaOriginal !== '' && $diaOriginal !== 'SIN DIA') {
                $diasAsignadosPan = [$diaOriginal];
            }
            $rutasAsignadasPan = array_values(array_keys($rutasPorClientePan[$cid] ?? []));
            if (empty($rutasAsignadasPan) && $rutaOriginal !== '') {
                $rutasAsignadasPan = [$rutaOriginal];
            }
            $asignacionesPan = array_values($asignacionesPorClientePan[$cid] ?? []);
            if (empty($asignacionesPan)) {
                $asignacionesPan = [[
                    'ruta' => $rutaOriginal,
                    'dia' => $diaOriginal,
                ]];
            }

            $esMultidia = count($diasAsignadosPan) >= 2;
            $esMultiruta = count($rutasAsignadasPan) >= 2;
            $esMultiple = $esMultidia || $esMultiruta;
            $esMultidiaYMultiruta = $esMultidia && $esMultiruta;

            if ($esMultidia) {
                $cidsMultidia[$cid] = true;
            }
            if ($esMultiruta) {
                $cidsMultiruta[$cid] = true;
            }
            if ($esMultiple) {
                $cidsMultiples[$cid] = true;
            }

            if ($modoVista === 'original') {
                // BASE ORIGINAL PURA:
                // Se proyecta la data original inmutable de pan_ruteo sin alterar rutas, días ni estilos
                $rutaEfectiva = $rutaOriginal;
                $diaEfectivo = $diaOriginal;
                $tieneCambio = false;
            } else {
                // MODO CON CAMBIOS:
                // Se proyectan las propuestas sobre la base de pan_ruteo
                $cleanDia = fn($s) => strtoupper(trim(str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú'], ['a','e','i','o','u','A','E','I','O','U'], (string)$s)));

                $rutaEfectiva = ($tienePropuesta && !empty($prop->ruta_propuesta))
                    ? strtoupper(trim((string)$prop->ruta_propuesta))
                    : $rutaOriginal;
                $diaEfectivo = ($tienePropuesta && !empty($prop->dia_propuesto))
                    ? strtoupper(trim((string)$prop->dia_propuesto))
                    : $diaOriginal;

                // Si la propuesta coincide exactamente con la base original, se descarta el cambio
                if ($tienePropuesta && $rutaEfectiva === $rutaOriginal && $cleanDia($diaEfectivo) === $cleanDia($diaOriginal)) {
                    $tieneCambio = false;
                    $tienePropuesta = false;
                    $prop = null;
                } else {
                    $tieneCambio = $tienePropuesta;
                }

                // Filtrado por días en modo cambios:
                // Se incluye si el día efectivo coincide, O si el día original coincidía (para poder observar el cambio de jornada)
                if (!empty($diasMerge)) {
                    $normEfectivo = $cleanDia($diaEfectivo);
                    $normOriginal = $cleanDia($diaOriginal);
                    $diasNormalizados = array_map($cleanDia, $diasMerge);

                    $coincideDiaEfectivo = in_array($normEfectivo, $diasNormalizados, true);
                    $coincideDiaOriginal = in_array($normOriginal, $diasNormalizados, true);
                    if (!$coincideDiaEfectivo && !$coincideDiaOriginal) {
                        continue;
                    }
                }
            }

            $kpis['total']++;

            $esActivo = (strtolower((string)$c->estado) === 'activo' || empty($c->estado));
            if ($esActivo) {
                $kpis['activos']++;
            } else {
                $kpis['inactivos']++;
            }

            $tieneGps = (!empty($c->latitud) && !empty($c->longitud) && (float)$c->latitud != 0.0);
            if ($tieneGps) {
                $kpis['con_gps']++;
            } else {
                $kpis['sin_gps']++;
            }

            if ($tieneCambio) {
                $kpis['con_cambios']++;
            }

            $clientes[] = [
                'id' => $c->id,
                'cliente_id' => $c->cliente_id,
                'nombre' => $c->cliente,
                'tipo_negocio' => $c->tipo_negocio ?? 'General',
                'direccion' => $c->direccion ?? 'Sin dirección registrada',
                'contacto' => $c->contacto,
                'celular' => $c->celular,
                'latitud' => (float)$c->latitud,
                'longitud' => (float)$c->longitud,
                'ruta' => $rutaEfectiva,
                'dia_norm' => $diaEfectivo,
                'ruta_original' => $rutaOriginal,
                'dia_original' => $diaOriginal,
                'ruta_propuesta' => $prop ? (string)$prop->ruta_propuesta : null,
                'dia_propuesto' => $prop ? (string)$prop->dia_propuesto : null,
                'tiene_cambio' => $tieneCambio,
                'tiene_propuesta' => $tienePropuesta,
                'propuesta_id' => $prop ? (int)$prop->id : null,
                'propuesta_tipo' => $prop ? (string)$prop->tipo_hallazgo : null,
                'propuesta_estado' => $prop ? (string)$prop->estado : null,
                'propuesta_fecha' => $prop ? (string)$prop->creado_en : null,
                'observaciones_propuesta' => $prop ? (string)$prop->observaciones : null,
                'estado' => $c->estado ?? 'Activo',
                'tiene_gps' => $tieneGps,

                // Asignaciones múltiples evaluadas contra todo pan_ruteo
                'es_multidia' => $esMultidia,
                'dias_asignados' => $diasAsignadosPan,
                'es_multiruta' => $esMultiruta,
                'rutas_asignadas' => $rutasAsignadasPan,
                'es_multiple' => $esMultiple,
                'es_multidia_y_multiruta' => $esMultidiaYMultiruta,
                'asignaciones_pan' => $asignacionesPan,

                // Ventas históricas agrupadas por periodo 'YYYY-MM' (últimos 11 meses)
                'ventas_historicas' => $ventasMap[$cid] ?? [],
                'venta_mes_actual' => (float) ($ventasMap[$cid][$mesActualPeriodo]['monto'] ?? 0.0),
                'venta_mes_anterior' => (float) ($ventasMap[$cid][$mesAnteriorPeriodo]['monto'] ?? 0.0),
                'venta_mes_anterior_2' => (float) ($ventasMap[$cid][$mesAnterior2Periodo]['monto'] ?? 0.0),
                'frecuencia_mes_actual' => (int) ($ventasMap[$cid][$mesActualPeriodo]['frecuencia'] ?? 0),
                'frecuencia_mes_anterior' => (int) ($ventasMap[$cid][$mesAnteriorPeriodo]['frecuencia'] ?? 0),
                'frecuencia_mes_anterior_2' => (int) ($ventasMap[$cid][$mesAnterior2Periodo]['frecuencia'] ?? 0),
            ];
        }

        $kpis['total_multidia'] = count($cidsMultidia);
        $kpis['total_multiruta'] = count($cidsMultiruta);
        $kpis['total_multiples'] = count($cidsMultiples);

        // Consulta de Polígonos de Rutas
        $fronterasRutas = [];
        if ($verFronteraRutas && !empty($rutasVariantes)) {
            $poligonosRutas = FronteraRuta::query()
                ->activos()
                ->whereIn('ruta', $rutasVariantes)
                ->select(['ruta', 'canal', DB::raw('ST_AsGeoJSON(poligono) as geojson')])
                ->get();

            foreach ($poligonosRutas as $fr) {
                if (!empty($fr->geojson)) {
                    $fronterasRutas[] = [
                        'ruta' => $fr->ruta,
                        'canal' => $fr->canal,
                        'geojson' => json_decode($fr->geojson, true),
                    ];
                }
            }
        }

        // Consulta de Polígonos de Días
        $fronterasDias = [];
        if ($verFronteraDias && !empty($rutasVariantes)) {
            $queryFronterasDias = FronteraDia::query()
                ->activos()
                ->whereIn('ruta', $rutasVariantes);

            if (!empty($diasMerge)) {
                $queryFronterasDias->whereIn('dia', $diasMerge);
            }

            $poligonosDias = $queryFronterasDias
                ->select(['ruta', 'dia', DB::raw('ST_AsGeoJSON(poligono) as geojson')])
                ->get();

            foreach ($poligonosDias as $fd) {
                if (!empty($fd->geojson)) {
                    $fronterasDias[] = [
                        'ruta' => $fd->ruta,
                        'dia' => strtoupper(trim((string)$fd->dia)),
                        'geojson' => json_decode($fd->geojson, true),
                    ];
                }
            }
        }

        return [
            'clientes' => $clientes,
            'fronteras_rutas' => $fronterasRutas,
            'fronteras_dias' => $fronterasDias,
            'kpis' => $kpis,
            'periodos_historicos' => $periodosHistoricos,
            'periodos_historicos_map' => [
                'mes_actual' => $periodosHistoricos[0] ?? null,
                'mes_anterior' => $periodosHistoricos[1] ?? null,
                'mes_anterior_2' => $periodosHistoricos[2] ?? null,
            ],
        ];
    }
}
