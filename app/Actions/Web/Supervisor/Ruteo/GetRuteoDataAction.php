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
     * @return array{clientes: array, fronteras_rutas: array, fronteras_dias: array, kpis: array}
     */
    public function execute(
        array $canales = [],
        array $rutas = [],
        array $dias = [],
        bool $verFronteraRutas = false,
        bool $verFronteraDias = false,
        bool $soloActivos = true,
        string $modoVista = 'original'
    ): array {
        if (empty($rutas) && empty($canales)) {
            return [
                'clientes' => [],
                'fronteras_rutas' => [],
                'fronteras_dias' => [],
                'kpis' => [
                    'total' => 0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                    'activos' => 0,
                    'inactivos' => 0,
                    'con_cambios' => 0,
                    'total_propuestas' => 0,
                ],
            ];
        }

        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        $rutasUpper = array_map(fn($r) => strtoupper(trim((string) $r)), $rutas);

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
            $queryClientes = PanRuteo::query()->whereIn('ruta', $rutas);
        } else {
            // EN MODO CAMBIOS: Consultar los clientes de las rutas seleccionadas más cualquier cliente
            // que tenga propuesta de reasignación vinculada a estas rutas (entrante o saliente)
            $queryClientes = PanRuteo::query()->where(function ($q) use ($rutas, $cidsAfectados) {
                $q->whereIn('ruta', $rutas);
                if (!empty($cidsAfectados)) {
                    $q->orWhereIn('cliente_id', $cidsAfectados);
                }
            });
        }

        if ($soloActivos) {
            $queryClientes->where(function ($q) {
                $q->where('estado', 'Activo')
                  ->orWhereNull('estado');
            });
        }

        $diasMerge = [];
        if (!empty($dias)) {
            $diasLower = array_map(fn($d) => strtolower(trim((string) $d)), $dias);
            $diasUpper = array_map(fn($d) => strtoupper(trim((string) $d)), $dias);
            $diasMerge = array_values(array_unique(array_merge($diasLower, $diasUpper)));
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

        $mesActualPeriodo = $now->format('Y-m');
        $mesAnteriorPeriodo = $now->copy()->subMonth()->format('Y-m');
        $inicioMesAnterior = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $finMesActual = $now->copy()->endOfMonth()->toDateString();

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

        $nombreMesActual = ($mesesEspanol[$now->format('m')] ?? 'Mes Actual') . ' ' . $now->format('Y');
        $mesAntObj = $now->copy()->subMonth();
        $nombreMesAnterior = ($mesesEspanol[$mesAntObj->format('m')] ?? 'Mes Anterior') . ' ' . $mesAntObj->format('Y');

        $cortoMesActual = ($mesesCorto[$now->format('m')] ?? 'Act.') . ' ' . $now->format('y');
        $cortoMesAnterior = ($mesesCorto[$mesAntObj->format('m')] ?? 'Ant.') . ' ' . $mesAntObj->format('y');

        $cidsTodos = $clientesRaw->pluck('cliente_id')->filter()->unique()->values()->all();
        $ventasMap = [];

        if (!empty($cidsTodos)) {
            try {
                foreach (array_chunk($cidsTodos, 1000) as $chunkIds) {
                    $ventasRows = DB::connection('supervisor')->table('fact_ventas')
                        ->whereIn('cliente_id', $chunkIds)
                        ->whereBetween('fecha_norm', [$inicioMesAnterior, $finMesActual])
                        ->where(function ($q) {
                            $q->whereNull('revertida')
                              ->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
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

        $kpis = [
            'total' => 0,
            'con_gps' => 0,
            'sin_gps' => 0,
            'activos' => 0,
            'inactivos' => 0,
            'con_cambios' => 0,
            'total_propuestas' => $totalPropuestasEnRutas,
        ];
        $clientes = [];

        foreach ($clientesRaw as $c) {
            $cid = (int)$c->cliente_id;
            $prop = $propuestasMap[$cid] ?? null;
            $tienePropuesta = ($prop !== null);

            $vActual = $ventasMap[$cid][$mesActualPeriodo] ?? ['monto' => 0.0, 'frecuencia' => 0];
            $vAnterior = $ventasMap[$cid][$mesAnteriorPeriodo] ?? ['monto' => 0.0, 'frecuencia' => 0];

            $rutaOriginal = strtoupper(trim((string)$c->ruta));
            $diaOriginal = strtoupper(trim((string)$c->dia_norm));

            if ($modoVista === 'original') {
                // BASE ORIGINAL PURA:
                // Se proyecta la data original inmutable de pan_ruteo sin alterar rutas, días ni estilos
                $rutaEfectiva = $rutaOriginal;
                $diaEfectivo = $diaOriginal;
                $tieneCambio = false;
            } else {
                // MODO CON CAMBIOS:
                // Se proyectan las propuestas sobre la base de pan_ruteo
                $rutaEfectiva = ($tienePropuesta && !empty($prop->ruta_propuesta))
                    ? strtoupper(trim((string)$prop->ruta_propuesta))
                    : $rutaOriginal;
                $diaEfectivo = ($tienePropuesta && !empty($prop->dia_propuesto))
                    ? strtoupper(trim((string)$prop->dia_propuesto))
                    : $diaOriginal;
                $tieneCambio = $tienePropuesta;

                // Filtrado por días en modo cambios:
                // Se incluye si el día efectivo coincide, O si el día original coincidía (para poder observar el cambio de jornada)
                if (!empty($diasMerge)) {
                    $coincideDiaEfectivo = in_array(strtolower($diaEfectivo), $diasMerge, true) || in_array(strtoupper($diaEfectivo), $diasMerge, true);
                    $coincideDiaOriginal = in_array(strtolower($diaOriginal), $diasMerge, true) || in_array(strtoupper($diaOriginal), $diasMerge, true);
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

                // Métricas históricas de ventas (fact_ventas)
                'venta_mes_actual' => (float) ($vActual['monto'] ?? 0.0),
                'venta_mes_anterior' => (float) ($vAnterior['monto'] ?? 0.0),
                'frecuencia_mes_actual' => (int) ($vActual['frecuencia'] ?? 0),
                'frecuencia_mes_anterior' => (int) ($vAnterior['frecuencia'] ?? 0),
            ];
        }

        // Consulta de Polígonos de Rutas
        $fronterasRutas = [];
        if ($verFronteraRutas && !empty($rutas)) {
            $poligonosRutas = FronteraRuta::query()
                ->activos()
                ->whereIn('ruta', $rutas)
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
        if ($verFronteraDias && !empty($rutas)) {
            $queryFronterasDias = FronteraDia::query()
                ->activos()
                ->whereIn('ruta', $rutas);

            if (!empty($dias)) {
                $diasLower = array_map(fn($d) => strtolower(trim((string) $d)), $dias);
                $diasUpper = array_map(fn($d) => strtoupper(trim((string) $d)), $dias);
                $diasMerge = array_values(array_unique(array_merge($diasLower, $diasUpper)));
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
            'periodos_historicos' => [
                'mes_actual' => [
                    'periodo' => $mesActualPeriodo,
                    'nombre' => $nombreMesActual,
                    'corto' => $cortoMesActual,
                ],
                'mes_anterior' => [
                    'periodo' => $mesAnteriorPeriodo,
                    'nombre' => $nombreMesAnterior,
                    'corto' => $cortoMesAnterior,
                ],
            ],
        ];
    }
}
