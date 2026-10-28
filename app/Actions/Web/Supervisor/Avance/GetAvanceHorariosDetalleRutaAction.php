<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetAvanceHorariosDetalleRutaAction
{
    private const DIAS_ESPANOL = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * Obtiene el desglose histórico completo de ingresos, check-ins y visitas de una ruta
     * a lo largo de un rango de fechas (Web ERP fact_visitas vs App Móvil visitas).
     *
     * @param string $ruta
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return array<string, mixed>
     */
    public function execute(string $ruta, string $fechaInicio, string $fechaFin): array
    {
        $rutaClean = strtoupper(trim($ruta));

        // 1. Información de la Ruta y Preventista
        $dimInfo = DB::connection('supervisor')->table('dim_rutas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->first();

        $vendedorNombre = $dimInfo && !empty($dimInfo->vendedor)
            ? trim((string) $dimInfo->vendedor)
            : 'No Asignado';
        $canal = $dimInfo && !empty($dimInfo->canal)
            ? trim((string) $dimInfo->canal)
            : 'GENERAL';

        // 2. Diccionario de Clientes de la Ruta desde pan_ruteo
        $clientesRutaRaw = DB::connection('supervisor')->table('pan_ruteo')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->whereNotNull('cliente_id')
            ->where('cliente_id', '>', 0)
            ->select([
                'cliente_id',
                DB::raw("COALESCE(cliente, '') as cliente"),
                DB::raw("COALESCE(cliente_norm, '') as cliente_norm"),
                DB::raw("COALESCE(direccion, '') as direccion"),
                DB::raw("COALESCE(dia_norm, dia) as dia"),
            ])
            ->get();

        $dictPorId = [];
        $dictPorNorm = [];
        $dictPorAlfa = [];

        foreach ($clientesRutaRaw as $c) {
            $cid = (int) $c->cliente_id;
            $nom = trim((string) $c->cliente);
            $norm = trim((string) $c->cliente_norm);

            $itemCli = [
                'cliente_id' => $cid,
                'cliente' => $nom ?: "Cliente #{$cid}",
                'direccion' => trim((string) $c->direccion),
                'dia_plan' => trim((string) $c->dia),
            ];

            $dictPorId[$cid] = $itemCli;

            if ($nom !== '') {
                $dictPorNorm[$this->normalizarTexto($nom)] = $itemCli;
                $dictPorAlfa[$this->normalizarAlfanumerico($nom)] = $itemCli;
            }
            if ($norm !== '') {
                $dictPorNorm[$this->normalizarTexto($norm)] = $itemCli;
                $dictPorAlfa[$this->normalizarAlfanumerico($norm)] = $itemCli;
            }
        }

        // 3. Obtener marcajes de fact_visitas (Sistema Web ERP) en el rango
        $visitasWebRaw = DB::connection('supervisor')->table('fact_visitas as fv')
            ->where('fv.fecha_norm', '>=', $fechaInicio)
            ->where('fv.fecha_norm', '<=', $fechaFin)
            ->select([
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha"),
                DB::raw("COALESCE(fv.cliente, 'CLIENTE S/N') as cliente"),
                DB::raw("COALESCE(fv.cliente_norm, '') as cliente_norm"),
                DB::raw("COALESCE(fv.tipo, '') as tipo"),
                DB::raw("COALESCE(fv.motivo, '') as motivo"),
                DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
                DB::raw("COALESCE(fv.tiempo_pdv, '-') as tiempo_pdv"),
                DB::raw("CAST(COALESCE(fv.monto, 0.0) AS DECIMAL(12, 2)) as monto"),
            ])
            ->orderBy('fv.fecha_norm', 'asc')
            ->orderBy('fv.check_in', 'asc')
            ->get();

        // Indexar visitas web pertenecientes a esta ruta por [fecha_clienteKey]
        $visitasWebPorDiaCli = [];
        foreach ($visitasWebRaw as $fw) {
            $nom = (string) $fw->cliente;
            $norm = (string) $fw->cliente_norm;

            $n1 = $this->normalizarTexto($nom);
            $n2 = $this->normalizarTexto($norm);
            $a1 = $this->normalizarAlfanumerico($nom);
            $a2 = $this->normalizarAlfanumerico($norm);

            $cliMatch = $dictPorNorm[$n1]
                ?? $dictPorNorm[$n2]
                ?? $dictPorAlfa[$a1]
                ?? $dictPorAlfa[$a2]
                ?? null;

            if (!$cliMatch) {
                continue; // No es cliente de esta ruta
            }

            $cid = (int) $cliMatch['cliente_id'];
            $fecha = (string) $fw->fecha;
            $key = "{$fecha}|{$cid}";

            $hIn = trim((string) $fw->check_in);
            if (strlen($hIn) === 5) {
                $hIn .= ':00';
            }

            $monto = (float) ($fw->monto ?: 0.0);
            $tipoV = trim((string) $fw->tipo);
            $motivoV = trim((string) $fw->motivo);

            if (!isset($visitasWebPorDiaCli[$key])) {
                $visitasWebPorDiaCli[$key] = [
                    'cliente_id' => $cid,
                    'cliente' => $cliMatch['cliente'],
                    'direccion' => $cliMatch['direccion'],
                    'fecha' => $fecha,
                    'check_in' => $hIn,
                    'check_out' => trim((string) $fw->check_out) ?: '-',
                    'tiempo_pdv' => trim((string) $fw->tiempo_pdv) ?: '-',
                    'tipo' => $tipoV,
                    'motivo' => $motivoV,
                    'monto' => $monto,
                ];
            } else {
                if ($hIn !== '00:00:00' && strcmp($hIn, $visitasWebPorDiaCli[$key]['check_in']) < 0) {
                    $visitasWebPorDiaCli[$key]['check_in'] = $hIn;
                }
                $visitasWebPorDiaCli[$key]['monto'] += $monto;
                if (!empty($motivoV) && empty($visitasWebPorDiaCli[$key]['motivo'])) {
                    $visitasWebPorDiaCli[$key]['motivo'] = $motivoV;
                }
                if (strtoupper($tipoV) === 'PREVENTA') {
                    $visitasWebPorDiaCli[$key]['tipo'] = 'PreVenta';
                }
            }
        }

        // 4. Obtener marcajes de visitas (App Móvil en MySQL) en el rango
        $visitasMovPorDiaCli = [];
        try {
            $fInicioTime = "{$fechaInicio} 00:00:00";
            $fFinTime = "{$fechaFin} 23:59:59";

            $filasMovil = DB::connection('mysql')->table('visitas')
                ->where('visited_at', '>=', $fInicioTime)
                ->where('visited_at', '<=', $fFinTime)
                ->where(function ($q) use ($rutaClean, $dictPorId) {
                    $q->where(DB::raw("TRIM(UPPER(route))"), $rutaClean);
                    if (!empty($dictPorId)) {
                        $q->orWhereIn('client_id', array_keys($dictPorId));
                    }
                })
                ->select([
                    'id',
                    'client_id',
                    'opportunity_client_name',
                    'visited_at',
                    'status',
                    'comments',
                    'photo_path',
                    'latitude',
                    'longitude',
                ])
                ->orderBy('visited_at', 'asc')
                ->get();

            foreach ($filasMovil as $fm) {
                $cid = (int) ($fm->client_id ?? 0);
                if ($cid <= 0 && !empty($fm->opportunity_client_name)) {
                    $a = $this->normalizarAlfanumerico((string) $fm->opportunity_client_name);
                    $cid = isset($dictPorAlfa[$a]) ? (int) $dictPorAlfa[$a]['cliente_id'] : 0;
                }

                $fecha = $fm->visited_at ? substr((string) $fm->visited_at, 0, 10) : $fechaInicio;
                $horaM = $fm->visited_at ? substr((string) $fm->visited_at, 11, 8) : '00:00:00';

                $fotoUrl = null;
                $fotoRel = ltrim(str_replace('\\', '/', (string) ($fm->photo_path ?? '')), '/');
                if ($fotoRel !== '') {
                    if (str_starts_with($fotoRel, 'http://') || str_starts_with($fotoRel, 'https://')) {
                        $fotoUrl = $fotoRel;
                    } elseif (str_starts_with($fotoRel, 'storage/')) {
                        $fotoUrl = '/' . $fotoRel;
                    } else {
                        $fotoUrl = '/storage/' . $fotoRel;
                    }
                }

                $cliInfo = $cid > 0 && isset($dictPorId[$cid])
                    ? $dictPorId[$cid]
                    : [
                        'cliente_id' => $cid > 0 ? $cid : null,
                        'cliente' => trim((string) ($fm->opportunity_client_name ?: ($cid > 0 ? "Cliente #{$cid}" : 'Cliente Oportunidad'))),
                        'direccion' => '',
                        'dia_plan' => '',
                    ];

                $key = $cid > 0 ? "{$fecha}|{$cid}" : "{$fecha}|opp_" . md5($cliInfo['cliente']);

                if (!isset($visitasMovPorDiaCli[$key])) {
                    $visitasMovPorDiaCli[$key] = [
                        'cliente_id' => $cliInfo['cliente_id'],
                        'cliente' => $cliInfo['cliente'],
                        'direccion' => $cliInfo['direccion'],
                        'fecha' => $fecha,
                        'hora_app' => $horaM,
                        'status' => (string) ($fm->status ?? ''),
                        'comments' => (string) ($fm->comments ?? ''),
                        'foto_url' => $fotoUrl,
                        'latitud' => $fm->latitude ? (float) $fm->latitude : null,
                        'longitud' => $fm->longitude ? (float) $fm->longitude : null,
                    ];
                } else {
                    if (strcmp($horaM, $visitasMovPorDiaCli[$key]['hora_app']) < 0) {
                        $visitasMovPorDiaCli[$key]['hora_app'] = $horaM;
                    }
                    if ($fotoUrl && empty($visitasMovPorDiaCli[$key]['foto_url'])) {
                        $visitasMovPorDiaCli[$key]['foto_url'] = $fotoUrl;
                    }
                    if (!empty($fm->status) && empty($visitasMovPorDiaCli[$key]['status'])) {
                        $visitasMovPorDiaCli[$key]['status'] = (string) $fm->status;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug("Error consultando visitas móvil en desglose: " . $e->getMessage());
        }

        // 5. Cruzar todas las visitas (Web & App) por [Fecha + Cliente]
        $todasLasClaves = array_unique(array_merge(
            array_keys($visitasWebPorDiaCli),
            array_keys($visitasMovPorDiaCli)
        ));

        $eventos = [];
        $diasMap = [];

        foreach ($todasLasClaves as $k) {
            $web = $visitasWebPorDiaCli[$k] ?? null;
            $app = $visitasMovPorDiaCli[$k] ?? null;

            $fecha = $web ? $web['fecha'] : $app['fecha'];
            $cid = $web ? $web['cliente_id'] : $app['cliente_id'];
            $nomCli = $web ? $web['cliente'] : $app['cliente'];
            $dirCli = $web ? $web['direccion'] : ($app['direccion'] ?? '');

            $hWeb = $web ? ($web['check_in'] !== '00:00:00' ? $web['check_in'] : null) : null;
            $hApp = $app ? ($app['hora_app'] !== '00:00:00' ? $app['hora_app'] : null) : null;

            $hOrden = '99:99:99';
            if ($hWeb && $hApp) {
                $hOrden = strcmp($hWeb, $hApp) < 0 ? $hWeb : $hApp;
            } elseif ($hWeb) {
                $hOrden = $hWeb;
            } elseif ($hApp) {
                $hOrden = $hApp;
            }

            $diffInfo = $this->calcularDiferencia($hWeb, $hApp);

            $monto = $web ? (float) $web['monto'] : 0.0;
            $tipoWeb = $web['tipo'] ?? '';
            $motivoWeb = $web['motivo'] ?? '';
            $statusApp = $app['status'] ?? '';

            $tipoFinal = $this->determinarTipoVisita(
                true,
                $monto,
                $tipoWeb,
                $motivoWeb,
                $statusApp,
                false
            );

            $carbonF = Carbon::parse($fecha);
            $diaSemanaNum = (int) $carbonF->format('N');
            $diaNombre = self::DIAS_ESPANOL[$diaSemanaNum] ?? 'Día';

            $origen = 'Ambos';
            if ($web && !$app) {
                $origen = 'Solo Web';
            } elseif (!$web && $app) {
                $origen = 'Solo App';
            }

            $itemEvento = [
                'id' => "ev_{$k}",
                'fecha' => $fecha,
                'dia_nombre' => $diaNombre,
                'fecha_corta' => $carbonF->format('d/m/Y'),
                'cliente_id' => $cid,
                'cliente' => $nomCli,
                'direccion' => $dirCli ?: 'Sin Dirección',
                'hora_web' => $hWeb,
                'hora_app' => $hApp,
                'hora_orden' => $hOrden,
                'diferencia_segundos' => $diffInfo['segundos'],
                'diferencia_texto' => $diffInfo['texto'],
                'check_out_web' => $web['check_out'] ?? '-',
                'tiempo_pdv' => $web['tiempo_pdv'] ?? '-',
                'tipo' => $tipoFinal,
                'monto' => $monto,
                'foto_url' => $app['foto_url'] ?? null,
                'tiene_foto' => !empty($app['foto_url']),
                'origen' => $origen,
                'latitud' => $app['latitud'] ?? null,
                'longitud' => $app['longitud'] ?? null,
            ];

            $eventos[] = $itemEvento;

            // Agrupación por día para el resumen de jornadas
            if (!isset($diasMap[$fecha])) {
                $diasMap[$fecha] = [
                    'fecha' => $fecha,
                    'dia_nombre' => $diaNombre,
                    'fecha_corta' => $carbonF->format('d/m'),
                    'ingresos_web' => [],
                    'salidas_web' => [],
                    'ingresos_app' => [],
                    'total_visitas' => 0,
                    'total_preventas' => 0,
                    'monto_total' => 0.0,
                ];
            }

            $diasMap[$fecha]['total_visitas']++;
            if ($monto > 0 || $tipoFinal === 'PreVenta') {
                $diasMap[$fecha]['total_preventas']++;
                $diasMap[$fecha]['monto_total'] += $monto;
            }
            if ($hWeb) {
                $diasMap[$fecha]['ingresos_web'][] = $hWeb;
            }
            if ($web && !empty($web['check_out']) && $web['check_out'] !== '-') {
                $diasMap[$fecha]['salidas_web'][] = $web['check_out'];
            }
            if ($hApp) {
                $diasMap[$fecha]['ingresos_app'][] = $hApp;
            }
        }

        // 6. Resumen de cada Día (Primer Ingreso Web, Primer Ingreso App, etc.)
        $resumenDias = [];
        $period = CarbonPeriod::create($fechaInicio, $fechaFin);
        foreach ($period as $dt) {
            $f = $dt->format('Y-m-d');
            $diaNum = (int) $dt->format('N');
            $dNombre = self::DIAS_ESPANOL[$diaNum] ?? 'Día';

            if (isset($diasMap[$f])) {
                $dm = $diasMap[$f];
                sort($dm['ingresos_web']);
                sort($dm['salidas_web']);
                sort($dm['ingresos_app']);

                $resumenDias[] = [
                    'fecha' => $f,
                    'dia_nombre' => $dNombre,
                    'fecha_corta' => $dt->format('d/m'),
                    'primer_ingreso_web' => !empty($dm['ingresos_web']) ? $dm['ingresos_web'][0] : '-',
                    'ultimo_egreso_web' => !empty($dm['salidas_web']) ? end($dm['salidas_web']) : (!empty($dm['ingresos_web']) ? end($dm['ingresos_web']) : '-'),
                    'primer_ingreso_app' => !empty($dm['ingresos_app']) ? $dm['ingresos_app'][0] : '-',
                    'ultimo_egreso_app' => !empty($dm['ingresos_app']) ? end($dm['ingresos_app']) : '-',
                    'total_visitas' => $dm['total_visitas'],
                    'total_preventas' => $dm['total_preventas'],
                    'monto_total' => round($dm['monto_total'], 2),
                    'tiene_actividad' => true,
                ];
            } else {
                $resumenDias[] = [
                    'fecha' => $f,
                    'dia_nombre' => $dNombre,
                    'fecha_corta' => $dt->format('d/m'),
                    'primer_ingreso_web' => '-',
                    'ultimo_egreso_web' => '-',
                    'primer_ingreso_app' => '-',
                    'ultimo_egreso_app' => '-',
                    'total_visitas' => 0,
                    'total_preventas' => 0,
                    'monto_total' => 0.0,
                    'tiene_actividad' => false,
                ];
            }
        }

        // 7. Orden inicial por fecha descendente y hora cronológica ascendente
        usort($eventos, function ($a, $b) {
            $cmpFecha = strcmp((string) $b['fecha'], (string) $a['fecha']);
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }
            return strcmp((string) $a['hora_orden'], (string) $b['hora_orden']);
        });

        // 8. Totales y Métricas Generales
        $totalPreventas = 0;
        $montoTotal = 0.0;
        $tiposConteo = [];

        foreach ($eventos as $ev) {
            if ($ev['monto'] > 0 || $ev['tipo'] === 'PreVenta') {
                $totalPreventas++;
                $montoTotal += (float) $ev['monto'];
            }
            $t = $ev['tipo'];
            $tiposConteo[$t] = ($tiposConteo[$t] ?? 0) + 1;
        }

        return [
            'ruta' => $rutaClean,
            'vendedor' => $vendedorNombre,
            'canal' => $canal,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'total_eventos' => count($eventos),
            'total_preventas' => $totalPreventas,
            'monto_total' => round($montoTotal, 2),
            'tipos_conteo' => $tiposConteo,
            'resumen_dias' => $resumenDias,
            'eventos' => $eventos,
        ];
    }

    /**
     * Determina el tipo / motivo normalizado de visita combinando Web (fact_visitas) y App (visitas).
     */
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

        $wTipo = strtoupper(trim(str_replace('_', ' ', (string) $webTipo)));
        $wMotivo = strtoupper(trim(str_replace('_', ' ', (string) $webMotivo)));
        $aStatus = strtoupper(trim(str_replace('_', ' ', (string) $appStatus)));

        if ($monto > 0 || $wTipo === 'PREVENTA' || $aStatus === 'PREVENTA') {
            return 'PreVenta';
        }

        $candidatos = array_values(array_filter([$wMotivo, $aStatus, $wTipo], fn ($v) => $v !== ''));

        foreach ($candidatos as $cand) {
            if (str_contains($cand, 'TIENDA CERRADA') || str_contains($cand, 'CERRAD')) {
                return 'Tienda Cerrada';
            }
            if (str_contains($cand, 'SIN DINERO') || str_contains($cand, 'NO DINERO') || str_contains($cand, 'FALTA DINERO')) {
                return 'Sin Dinero';
            }
            if (str_contains($cand, 'CON STOCK') || str_contains($cand, 'TIENE STOCK') || str_contains($cand, 'STOCK')) {
                return 'Con Stock';
            }
            if (str_contains($cand, 'AUSENTE') || str_contains($cand, 'NO ESTA')) {
                return 'Ausente';
            }
            if (str_contains($cand, 'RECHAZ')) {
                return 'Rechazado';
            }
            if (str_contains($cand, 'COBRANZA')) {
                return 'Cobranza';
            }
        }

        if ($isOpportunity || in_array('OPORTUNIDAD', $candidatos, true)) {
            return 'Oportunidad';
        }

        if ($wMotivo !== '' && !in_array($wMotivo, ['NO ATENDIDO', 'NO ATENDIDA', 'VISITA'], true)) {
            return ucwords(mb_strtolower($wMotivo));
        }

        if ($aStatus !== '' && !in_array($aStatus, ['VISITADO', 'VISITA'], true)) {
            return ucwords(mb_strtolower($aStatus));
        }

        if ($wTipo === 'NO ATENDIDO' || $wTipo === 'NO ATENDIDA') {
            return 'No Atendido';
        }

        return 'Visita';
    }

    /**
     * Calcula diferencia de horas en formato amigable y segundos.
     */
    private function calcularDiferencia(?string $hWeb, ?string $hApp): array
    {
        if (!$hWeb && !$hApp) {
            return ['segundos' => null, 'texto' => '—'];
        }
        if ($hWeb && !$hApp) {
            return ['segundos' => null, 'texto' => 'Solo Web'];
        }
        if (!$hWeb && $hApp) {
            return ['segundos' => null, 'texto' => 'Solo App'];
        }

        $tWeb = strtotime($hWeb);
        $tApp = strtotime($hApp);
        if ($tWeb === false || $tApp === false) {
            return ['segundos' => null, 'texto' => '—'];
        }

        $diff = $tApp - $tWeb;
        $abs = abs($diff);
        $sign = $diff >= 0 ? '+' : '-';

        if ($abs === 0) {
            $txt = '0s';
        } elseif ($abs < 60) {
            $txt = "{$sign}{$abs}s";
        } elseif ($abs < 3600) {
            $m = (int) floor($abs / 60);
            $s = $abs % 60;
            $txt = $s > 0 ? "{$sign}{$m}m {$s}s" : "{$sign}{$m}m";
        } else {
            $h = (int) floor($abs / 3600);
            $m = (int) floor(($abs % 3600) / 60);
            $txt = $m > 0 ? "{$sign}{$h}h {$m}m" : "{$sign}{$h}h";
        }

        return ['segundos' => $diff, 'texto' => $txt];
    }

    private function normalizarTexto(?string $str): string
    {
        if (!$str) {
            return '';
        }
        $s = mb_strtolower(trim($str), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'Ü' => 'u', 'Ñ' => 'n',
        ]);
        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    private function normalizarAlfanumerico(?string $str): string
    {
        if (!$str) {
            return '';
        }
        $s = $this->normalizarTexto($str);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }
}

