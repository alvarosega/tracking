<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleVisitasRutaAction
{
    private const DIAS_MAP = [
        1 => ['lunes'],
        2 => ['martes'],
        3 => ['miercoles', 'miércoles'],
        4 => ['jueves'],
        5 => ['viernes'],
        6 => ['sabado', 'sábado'],
        7 => ['domingo'],
    ];

    private function normTxt(?string $texto): string
    {
        if (!$texto) {
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
     * Obtiene el desglose auditado de cobertura de cartera por cliente para una ruta.
     *
     * @param string $ruta
     * @param string|null $fecha
     * @param bool $estricto
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null, bool $estricto = false): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes'];
        $rutaClean = strtoupper(trim($ruta));
        $vendedor = 'No Asignado';

        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && $dimInfo->vendedor) {
                $vendedor = trim($dimInfo->vendedor);
            }

            // 1. Cartera total de la ruta para el día
            $carteraTotalRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($tuplaDias): void {
                    $q->whereIn(DB::raw("LOWER(TRIM(COALESCE(dia_norm, dia)))"), $tuplaDias);
                })
                ->where('cliente_id', '>', 0)
                ->select([
                    'cliente_id',
                    DB::raw("MAX(COALESCE(TRIM(cliente), 'S/N')) as cliente"),
                    DB::raw("MAX(COALESCE(TRIM(cliente_norm), '')) as cliente_norm"),
                    DB::raw("MAX(COALESCE(TRIM(estado), 'ACTIVO')) as estado"),
                    DB::raw("MAX(COALESCE(TRIM(zona_venta), '-')) as zona_venta"),
                    DB::raw("MAX(COALESCE(TRIM(direccion), '-')) as direccion"),
                    DB::raw("MAX(COALESCE(TRIM(referencia), '-')) as referencia"),
                    DB::raw("MAX(latitud) as latitud"),
                    DB::raw("MAX(longitud) as longitud"),
                ])
                ->groupBy('cliente_id')
                ->get();

            // 2. Visitas en fact_visitas
            $todasVisitas = DB::connection('supervisor')->table('fact_visitas as fv')
                ->where('fv.fecha_norm', $fechaNorm)
                ->select([
                    DB::raw("COALESCE(TRIM(fv.cliente_norm), TRIM(fv.cliente)) as cliente_raw"),
                    'fv.cliente',
                    'fv.empleado_norm',
                    'fv.empleado',
                    DB::raw("COALESCE(TRIM(fv.tipo), 'PreVenta') as tipo"),
                    DB::raw("COALESCE(TRIM(fv.motivo), '') as motivo"),
                    'fv.check_in',
                    'fv.check_out',
                    'fv.tiempo_pdv',
                    DB::raw("COALESCE(fv.zona, '-') as zona"),
                    DB::raw("COALESCE(fv.monto, 0.0) as monto"),
                ])
                ->orderBy('fv.check_in', 'asc')
                ->get();

            // 3. Preventas del día
            $preventasClienteList = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->select([
                    'cliente_id',
                    DB::raw("COALESCE(TRIM(cliente), '') as cliente"),
                    DB::raw("SUM(COALESCE(monto_final, 0.0)) as total_preventa"),
                ])
                ->groupBy('cliente_id', DB::raw("COALESCE(TRIM(cliente), '')"))
                ->get();

            $preventasMontoByCid = [];
            $preventasMontoByNorm = [];
            foreach ($preventasClienteList as $p) {
                $cid = (int) $p->cliente_id;
                $tot = (float) $p->total_preventa;
                if ($cid > 0) {
                    $preventasMontoByCid[$cid] = ($preventasMontoByCid[$cid] ?? 0.0) + $tot;
                }
                $cn = $this->normTxt($p->cliente);
                if ($cn !== '') {
                    $preventasMontoByNorm[$cn] = ($preventasMontoByNorm[$cn] ?? 0.0) + $tot;
                }
            }

            $obtenerMontoReal = function (?int $cid, string $cn, float $montoF = 0.0) use ($preventasMontoByCid, $preventasMontoByNorm): float {
                if ($cid && isset($preventasMontoByCid[$cid])) {
                    return round($preventasMontoByCid[$cid], 2);
                }
                if ($cn && isset($preventasMontoByNorm[$cn])) {
                    return round($preventasMontoByNorm[$cn], 2);
                }
                return round($montoF, 2);
            };

            // 4. Visitas App Móvil
            $fInicioDia = "{$fechaNorm} 00:00:00";
            $fFinDia = "{$fechaNorm} 23:59:59";
            $filasMovil = Visita::query()
                ->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean])
                ->whereBetween('visited_at', [$fInicioDia, $fFinDia])
                ->orderBy('visited_at', 'asc')
                ->get();

            $movilByCid = [];
            $movilByNorm = [];
            foreach ($filasMovil as $fm) {
                $cid = (int) $fm->client_id;
                $oppName = (string) ($fm->opportunity_client_name ?? '');
                $normName = $this->normTxt($oppName);

                $fotoRel = ltrim((string) $fm->photo_path, '/\\');
                $fotoUrl = $fotoRel ? (str_starts_with($fotoRel, 'http') ? $fotoRel : "/storage/{$fotoRel}") : null;
                $horaM = $fm->visited_at ? $fm->visited_at->format('H:i:s') : '00:00:00';

                $mItem = [
                    'visita_id' => $fm->id,
                    'client_id' => $cid > 0 ? $cid : null,
                    'hora_app' => $horaM,
                    'foto_url' => $fotoUrl,
                    'comments_app' => (string) ($fm->comments ?? ''),
                    'status_app' => (string) ($fm->status ?? 'VISITADO'),
                    'lat_app' => $fm->latitude ? (float) $fm->latitude : null,
                    'lng_app' => $fm->longitude ? (float) $fm->longitude : null,
                    'is_opportunity' => (bool) $fm->is_opportunity,
                ];

                if ($cid > 0) {
                    $movilByCid[$cid][] = $mItem;
                }
                if ($normName !== '') {
                    $movilByNorm[$normName][] = $mItem;
                }
            }

            // 5. Filtrar visitas del vendedor
            $vendedorNorm = $this->normTxt($vendedor);
            $visitasVendedor = [];
            foreach ($todasVisitas as $v) {
                if ($this->normTxt($v->empleado_norm) === $vendedorNorm || $this->normTxt($v->empleado) === $vendedorNorm) {
                    $visitasVendedor[] = $v;
                }
            }

            $planActivosPorNorm = [];
            $planInactivosPorNorm = [];
            foreach ($carteraTotalRuta as $p) {
                $cn = $this->normTxt($p->cliente_norm ?: $p->cliente);
                $st = strtoupper(trim((string) ($p->estado ?: 'ACTIVO')));
                if (in_array($st, ['ACTIVO', 'ACTIVA', 'VIGENTE'], true)) {
                    $planActivosPorNorm[$cn] = $p;
                } else {
                    $planInactivosPorNorm[$cn] = $p;
                }
            }

            $visitasUnicasDict = [];
            foreach ($visitasVendedor as $v) {
                $cNorm = $this->normTxt($v->cliente_raw);
                if (!$cNorm) {
                    continue;
                }
                $montoF = (float) ($v->monto ?: 0.0);
                $tipoV = trim((string) ($v->tipo ?: 'PreVenta'));
                $motivoRaw = trim((string) ($v->motivo ?: ''));
                $motivoV = $motivoRaw ?: (mb_strtolower($tipoV) === 'preventa' ? 'PreVenta' : 'No Atendido');

                $itemVisita = [
                    'nro' => 1,
                    'tipo' => $tipoV,
                    'motivo' => $motivoV,
                    'check_in' => (string) $v->check_in,
                    'check_out' => (string) $v->check_out,
                    'tiempo_pdv' => (string) $v->tiempo_pdv,
                ];

                if (!isset($visitasUnicasDict[$cNorm])) {
                    $visitasUnicasDict[$cNorm] = [
                        'cliente' => $v->cliente,
                        'check_in' => (string) $v->check_in,
                        'check_out' => (string) $v->check_out,
                        'tiempo_pdv' => (string) $v->tiempo_pdv,
                        'zona' => $v->zona,
                        'monto' => $montoF,
                        'conteo_visitas' => 1,
                        'en_fact_visitas' => true,
                        'historial_visitas' => [$itemVisita],
                    ];
                } else {
                    $visitasUnicasDict[$cNorm]['check_out'] = (string) $v->check_out;
                    $visitasUnicasDict[$cNorm]['monto'] += $montoF;
                    $visitasUnicasDict[$cNorm]['conteo_visitas']++;
                    $itemVisita['nro'] = $visitasUnicasDict[$cNorm]['conteo_visitas'];
                    $visitasUnicasDict[$cNorm]['historial_visitas'][] = $itemVisita;
                }
            }

            $determinarTipoMotivo = function (array $historial, float $montoReal): array {
                if (empty($historial)) {
                    return ['tipo' => 'NO_VISITADO', 'motivo' => 'No Visitado', 'historial_visitas' => []];
                }
                $tienePreventa = ($montoReal > 0) || collect($historial)->contains(fn ($h) => mb_strtolower($h['tipo']) === 'preventa');
                if ($tienePreventa) {
                    return ['tipo' => 'PreVenta', 'motivo' => 'PreVenta', 'historial_visitas' => $historial];
                }
                $ultimo = end($historial);
                return [
                    'tipo' => $ultimo['tipo'] ?: 'No Atendido',
                    'motivo' => $ultimo['motivo'] ?: 'No Atendido',
                    'historial_visitas' => $historial,
                ];
            };

            $asociarInfoApp = function (array &$objCliente, ?int $cid, string $cNorm) use ($movilByCid, $movilByNorm): void {
                $itemsMovil = [];
                if ($cid && isset($movilByCid[$cid])) {
                    $itemsMovil = $movilByCid[$cid];
                } elseif ($cNorm && isset($movilByNorm[$cNorm])) {
                    $itemsMovil = $movilByNorm[$cNorm];
                }

                if (!empty($itemsMovil)) {
                    $primer = $itemsMovil[0];
                    $objCliente['en_visitas_app'] = true;
                    $objCliente['hora_app'] = $primer['hora_app'];
                    $objCliente['foto_url'] = collect($itemsMovil)->pluck('foto_url')->filter()->first();
                    $objCliente['comments_app'] = $primer['comments_app'];
                    $objCliente['status_app'] = $primer['status_app'];
                } else {
                    $objCliente['en_visitas_app'] = false;
                    $objCliente['hora_app'] = null;
                    $objCliente['foto_url'] = null;
                    $objCliente['comments_app'] = null;
                    $objCliente['status_app'] = null;
                }
            };

            $visitadosActivos = [];
            $visitadosInactivos = [];
            $fueraPlan = [];
            $matchActivosVisitadosSet = [];

            foreach ($visitasUnicasDict as $cNorm => $vDatos) {
                if (isset($planActivosPorNorm[$cNorm])) {
                    $pObj = $planActivosPorNorm[$cNorm];
                    $cid = (int) $pObj->cliente_id;
                    $vDatos['cliente_id'] = $cid;
                    $mR = $obtenerMontoReal($cid, $cNorm, (float) ($vDatos['monto'] ?? 0.0));
                    $vDatos['monto'] = $mR;
                    $tm = $determinarTipoMotivo($vDatos['historial_visitas'] ?? [], $mR);
                    $vDatos['tipo'] = $tm['tipo'];
                    $vDatos['motivo'] = $tm['motivo'];
                    $vDatos['historial_visitas'] = $tm['historial_visitas'];
                    $vDatos['latitud'] = $pObj->latitud ? (float) $pObj->latitud : null;
                    $vDatos['longitud'] = $pObj->longitud ? (float) $pObj->longitud : null;
                    $vDatos['direccion'] = $pObj->direccion ?: '-';
                    $asociarInfoApp($vDatos, $cid, $cNorm);

                    if (!$estricto || $vDatos['en_visitas_app']) {
                        $visitadosActivos[] = $vDatos;
                        $matchActivosVisitadosSet[$cNorm] = true;
                    }
                } elseif (isset($planInactivosPorNorm[$cNorm])) {
                    $pObj = $planInactivosPorNorm[$cNorm];
                    $cid = (int) $pObj->cliente_id;
                    $vDatos['cliente_id'] = $cid;
                    $mR = $obtenerMontoReal($cid, $cNorm, (float) ($vDatos['monto'] ?? 0.0));
                    $vDatos['monto'] = $mR;
                    $tm = $determinarTipoMotivo($vDatos['historial_visitas'] ?? [], $mR);
                    $vDatos['tipo'] = $tm['tipo'];
                    $vDatos['motivo'] = $tm['motivo'];
                    $vDatos['historial_visitas'] = $tm['historial_visitas'];
                    $vDatos['estado_ruteo'] = $pObj->estado ?: 'INACTIVO';
                    $vDatos['latitud'] = $pObj->latitud ? (float) $pObj->latitud : null;
                    $vDatos['longitud'] = $pObj->longitud ? (float) $pObj->longitud : null;
                    $vDatos['direccion'] = $pObj->direccion ?: '-';
                    $asociarInfoApp($vDatos, $cid, $cNorm);

                    if (!$estricto || $vDatos['en_visitas_app']) {
                        $visitadosInactivos[] = $vDatos;
                    }
                } else {
                    $vDatos['cliente_id'] = null;
                    $mR = $obtenerMontoReal(null, $cNorm, (float) ($vDatos['monto'] ?? 0.0));
                    $vDatos['monto'] = $mR;
                    $tm = $determinarTipoMotivo($vDatos['historial_visitas'] ?? [], $mR);
                    $vDatos['tipo'] = $tm['tipo'];
                    $vDatos['motivo'] = $tm['motivo'];
                    $vDatos['historial_visitas'] = $tm['historial_visitas'];
                    $asociarInfoApp($vDatos, null, $cNorm);

                    if (!$estricto || $vDatos['en_visitas_app']) {
                        $fueraPlan[] = $vDatos;
                    }
                }
            }

            $noVisitadosActivos = [];
            foreach ($planActivosPorNorm as $cNorm => $p) {
                if (!isset($matchActivosVisitadosSet[$cNorm])) {
                    $cid = (int) $p->cliente_id;
                    $mReal = $obtenerMontoReal($cid, $cNorm, (float) ($visitasUnicasDict[$cNorm]['monto'] ?? 0.0));
                    $histNv = $visitasUnicasDict[$cNorm]['historial_visitas'] ?? [];
                    $tm = $determinarTipoMotivo($histNv, $mReal);

                    $nvObj = [
                        'cliente_id' => $cid,
                        'cliente' => $p->cliente,
                        'zona_venta' => $p->zona_venta,
                        'direccion' => $p->direccion,
                        'referencia' => $p->referencia,
                        'latitud' => $p->latitud ? (float) $p->latitud : null,
                        'longitud' => $p->longitud ? (float) $p->longitud : null,
                        'en_fact_visitas' => isset($visitasUnicasDict[$cNorm]),
                        'check_in' => $visitasUnicasDict[$cNorm]['check_in'] ?? null,
                        'tiempo_pdv' => $visitasUnicasDict[$cNorm]['tiempo_pdv'] ?? null,
                        'monto' => $mReal,
                        'tipo' => $tm['tipo'],
                        'motivo' => $tm['motivo'],
                        'historial_visitas' => $tm['historial_visitas'],
                    ];
                    $asociarInfoApp($nvObj, $cid, $cNorm);
                    $noVisitadosActivos[] = $nvObj;
                }
            }

            $totalCarteraActiva = count($planActivosPorNorm);
            $totalVisitadosActivos = count($visitadosActivos);

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'estricto' => $estricto,
                'totales' => [
                    'cartera_plan' => $totalCarteraActiva,
                    'visitados' => $totalVisitadosActivos,
                    'no_visitados' => count($noVisitadosActivos),
                    'visitados_inactivos' => count($visitadosInactivos),
                    'fuera_plan' => count($fueraPlan),
                    'diferencia' => $totalVisitadosActivos - $totalCarteraActiva,
                ],
                'no_visitados' => $noVisitadosActivos,
                'visitados' => $visitadosActivos,
                'visitados_inactivos' => $visitadosInactivos,
                'fuera_plan' => $fueraPlan,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleVisitasRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'estricto' => $estricto,
                'totales' => [
                    'cartera_plan' => 0,
                    'visitados' => 0,
                    'no_visitados' => 0,
                    'visitados_inactivos' => 0,
                    'fuera_plan' => 0,
                    'diferencia' => 0,
                ],
                'no_visitados' => [],
                'visitados' => [],
                'visitados_inactivos' => [],
                'fuera_plan' => [],
            ];
        }
    }
}

