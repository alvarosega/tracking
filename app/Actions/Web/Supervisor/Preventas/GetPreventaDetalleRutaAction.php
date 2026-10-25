<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventaDetalleRutaAction
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

    /**
     * Normaliza un texto para comparaciones flexibles (sin tildes, minúsculas, sin espacios dobles).
     */
    private function normalizarTexto(?string $texto): string
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
     * Obtiene el itinerario detallado, paradas, estado operativo y evidencias fotográficas de una ruta en el día.
     *
     * @param string $ruta
     * @param string|null $fecha
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $carbonFecha = Carbon::parse($fechaNorm);
        $tuplaDias = self::DIAS_MAP[$carbonFecha->dayOfWeekIso] ?? ['lunes'];
        $diaCapitalizado = ucfirst($tuplaDias[0]);
        $rutaClean = strtoupper(trim($ruta));

        // 1. Preventista oficial
        $rutaInfo = DB::connection('supervisor')->table('dim_rutas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->first();

        $vendedorNombre = $rutaInfo && $rutaInfo->vendedor ? trim($rutaInfo->vendedor) : 'Sin asignar';
        $vendedorNorm = $this->normalizarTexto($vendedorNombre);

        // 2. Clientes en plan para esta ruta y día
        $filasPlan = DB::connection('supervisor')->table('pan_ruteo')
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
                DB::raw("MAX(COALESCE(TRIM(direccion), 'Sin dirección')) as direccion"),
                DB::raw("MAX(COALESCE(TRIM(tipo_negocio), '-')) as tipo_negocio"),
                DB::raw("MAX(COALESCE(TRIM(cluster_t), '-')) as cluster_t"),
                DB::raw("MAX(COALESCE(latitud, 0.0)) as latitud"),
                DB::raw("MAX(COALESCE(longitud, 0.0)) as longitud"),
            ])
            ->groupBy('cliente_id')
            ->get();

        // 3. Visitas del día en el sistema ERP central (fact_visitas)
        $todasVisitas = DB::connection('supervisor')->table('fact_visitas')
            ->where('fecha_norm', $fechaNorm)
            ->select([
                'id as visita_id',
                DB::raw("COALESCE(TRIM(cliente_norm), TRIM(cliente)) as cliente_raw"),
                'cliente as cliente_visita',
                'empleado_norm',
                'empleado',
                DB::raw("COALESCE(check_in, '') as check_in"),
                DB::raw("COALESCE(check_out, '') as check_out"),
                DB::raw("COALESCE(tiempo_pdv, '') as tiempo_pdv"),
                DB::raw("COALESCE(NULLIF(TRIM(motivo), ''), 'Sin motivo') as motivo"),
                DB::raw("COALESCE(zona, '-') as zona"),
                DB::raw("COALESCE(tipo_negocio, '-') as tipo_negocio_visita"),
                DB::raw("COALESCE(cluster, '-') as cluster_visita"),
            ])
            ->orderBy('check_in', 'asc')
            ->get();

        $visitasVendedor = [];
        foreach ($todasVisitas as $v) {
            $empNorm = $this->normalizarTexto($v->empleado_norm ?: $v->empleado);
            if ($empNorm === $vendedorNorm) {
                $visitasVendedor[] = $v;
            }
        }

        // 4. Preventas reales del día para esta ruta
        $filasPreventas = DB::connection('supervisor')->table('fact_preventas')
            ->where('fecha_norm', $fechaNorm)
            ->where('monto_final', '>', 0)
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->select([
                'cliente_id',
                DB::raw("COALESCE(TRIM(cliente), 'S/N') as cliente"),
                DB::raw("SUM(COALESCE(monto_final, 0.0)) as monto_preventa"),
                DB::raw("COUNT(DISTINCT nro_preventa) as cant_pedidos"),
            ])
            ->groupBy('cliente_id', DB::raw("COALESCE(TRIM(cliente), 'S/N')"))
            ->get();

        // 5. Catálogo maestro de pan_ruteo para identificar procedencia de desvíos
        $catalogoMaestro = DB::connection('supervisor')->table('pan_ruteo')
            ->where('cliente_id', '>', 0)
            ->select([
                'cliente_id',
                DB::raw("COALESCE(TRIM(cliente_norm), TRIM(cliente)) as cliente_raw"),
                DB::raw("MAX(cliente) as cliente"),
                DB::raw("MAX(direccion) as direccion"),
                DB::raw("MAX(tipo_negocio) as tipo_negocio"),
                DB::raw("MAX(cluster_t) as cluster_t"),
                DB::raw("MAX(latitud) as latitud"),
                DB::raw("MAX(longitud) as longitud"),
                DB::raw("MAX(TRIM(UPPER(ruta))) as ruta_origen"),
                DB::raw("GROUP_CONCAT(DISTINCT LOWER(TRIM(COALESCE(dia_norm, dia))) SEPARATOR ', ') as dias_origen"),
            ])
            ->groupBy('cliente_id', DB::raw("COALESCE(TRIM(cliente_norm), TRIM(cliente))"))
            ->get();

        // Índices en memoria para cruces rápidos
        $maestroPorId = [];
        $maestroPorNorm = [];
        foreach ($catalogoMaestro as $m) {
            $maestroPorId[(int) $m->cliente_id] = $m;
            $cn = $this->normalizarTexto($m->cliente_raw);
            if ($cn !== '') {
                $maestroPorNorm[$cn] = $m;
            }
        }

        $mapaPreventasId = [];
        $mapaPreventasNorm = [];
        foreach ($filasPreventas as $pr) {
            if ($pr->cliente_id) {
                $mapaPreventasId[(int) $pr->cliente_id] = $pr;
            }
            if ($pr->cliente) {
                $mapaPreventasNorm[$this->normalizarTexto($pr->cliente)] = $pr;
            }
        }

        $visitasUnicasDict = [];
        foreach ($visitasVendedor as $v) {
            $cn = $this->normalizarTexto($v->cliente_raw);
            if (!$cn) {
                continue;
            }
            if (!isset($visitasUnicasDict[$cn])) {
                $visitasUnicasDict[$cn] = (array) $v;
            } elseif (!empty($v->check_out)) {
                $visitasUnicasDict[$cn]['check_out'] = $v->check_out;
            }
        }

        $clientesFinales = [];
        $cidsProcesados = [];
        $normsVisitasProcesadas = [];

        // ── FASE 1: CLIENTES PLANIFICADOS DEL DÍA ──
        foreach ($filasPlan as $p) {
            $cid = (int) $p->cliente_id;
            $cn = $this->normalizarTexto($p->cliente_norm ?: $p->cliente);
            $cidsProcesados[$cid] = true;

            $vInfo = $visitasUnicasDict[$cn] ?? null;
            if ($vInfo) {
                $normsVisitasProcesadas[$cn] = true;
            }

            $prInfo = $mapaPreventasId[$cid] ?? ($mapaPreventasNorm[$cn] ?? null);
            $montoPrev = $prInfo ? (float) $prInfo->monto_preventa : 0.0;
            $cantPed = $prInfo ? (int) $prInfo->cant_pedidos : 0;
            $fueVisitado = $vInfo !== null;

            // Clasificación auditada estricta
            if ($fueVisitado && $montoPrev > 0) {
                $estadoOp = 'PLAN_CON_VENTA';
                $motivoOp = 'Compra confirmada';
            } elseif ($fueVisitado && $montoPrev == 0) {
                $estadoOp = 'PLAN_SIN_VENTA';
                $motivoOp = $vInfo['motivo'] ?? 'Sin motivo registrado';
            } elseif (!$fueVisitado && $montoPrev > 0) {
                $estadoOp = 'VENTA_SIN_VISITA';
                $motivoOp = 'Venta sin registro de visita (remota)';
            } else {
                $estadoOp = 'PLAN_NO_VISITADO';
                $motivoOp = 'Visita no realizada';
            }

            $clientesFinales[] = [
                'cliente_id' => $cid,
                'cliente' => $p->cliente,
                'cliente_norm' => $cn,
                'direccion' => $p->direccion,
                'latitud' => (float) ($p->latitud ?: 0.0),
                'longitud' => (float) ($p->longitud ?: 0.0),
                'tipo_negocio' => $p->tipo_negocio,
                'cluster_t' => $p->cluster_t,
                'fue_visitado' => $fueVisitado ? 1 : 0,
                'check_in' => $vInfo ? ($vInfo['check_in'] ?? '') : '',
                'check_out' => $vInfo ? ($vInfo['check_out'] ?? '') : '',
                'tiempo_pdv' => $vInfo ? ($vInfo['tiempo_pdv'] ?? '') : '',
                'motivo' => $motivoOp,
                'tipo_pago' => 'Contado',
                'monto_preventa' => round($montoPrev, 2),
                'cant_pedidos' => $cantPed,
                'estado_operativo' => $estadoOp,
                'ruta_origen' => $rutaClean,
                'dia_origen' => $diaCapitalizado,
            ];
        }

        // ── FASE 2: VISITAS FUERA DE PLAN ──
        foreach ($visitasUnicasDict as $cn => $vInfo) {
            if (isset($normsVisitasProcesadas[$cn])) {
                continue;
            }
            $normsVisitasProcesadas[$cn] = true;

            $regMaestro = $maestroPorNorm[$cn] ?? null;
            $cid = $regMaestro ? (int) $regMaestro->cliente_id : 0;
            if ($cid > 0) {
                $cidsProcesados[$cid] = true;
            }

            $prInfo = ($cid > 0 && isset($mapaPreventasId[$cid]))
                ? $mapaPreventasId[$cid]
                : ($mapaPreventasNorm[$cn] ?? null);

            $montoPrev = $prInfo ? (float) $prInfo->monto_preventa : 0.0;
            $cantPed = $prInfo ? (int) $prInfo->cant_pedidos : 0;

            if (!$regMaestro) {
                $estadoOp = 'CLIENTE_NUEVO';
                $motivoOp = 'PDV no figura en plan ruteo';
                $rOrig = 'SIN ASIGNAR';
                $dOrig = 'SIN DIA';
                $dirPdv = 'Dirección no registrada';
                $tNeg = $vInfo['tipo_negocio_visita'] ?? '-';
                $clust = $vInfo['cluster_visita'] ?? '-';
                $lat = 0.0;
                $lon = 0.0;
            } else {
                $rOrig = $regMaestro->ruta_origen;
                $dOrig = $regMaestro->dias_origen;
                $dirPdv = $regMaestro->direccion ?: 'Sin dirección';
                $tNeg = $regMaestro->tipo_negocio ?: '-';
                $clust = $regMaestro->cluster_t ?: '-';
                $lat = (float) ($regMaestro->latitud ?: 0.0);
                $lon = (float) ($regMaestro->longitud ?: 0.0);

                if ($rOrig === $rutaClean) {
                    $estadoOp = 'DESVIO_DIA';
                    $motivoOp = 'Día programado: ' . strtoupper((string) $dOrig);
                } else {
                    $estadoOp = 'DESVIO_RUTA';
                    $motivoOp = "Pertenece a {$rOrig} (" . strtoupper((string) $dOrig) . ')';
                }
            }

            $clientesFinales[] = [
                'cliente_id' => $cid,
                'cliente' => $vInfo['cliente_visita'],
                'cliente_norm' => $cn,
                'direccion' => $dirPdv,
                'latitud' => $lat,
                'longitud' => $lon,
                'tipo_negocio' => $tNeg,
                'cluster_t' => $clust,
                'fue_visitado' => 1,
                'check_in' => $vInfo['check_in'] ?? '',
                'check_out' => $vInfo['check_out'] ?? '',
                'tiempo_pdv' => $vInfo['tiempo_pdv'] ?? '',
                'motivo' => $motivoOp,
                'tipo_pago' => 'Contado',
                'monto_preventa' => round($montoPrev, 2),
                'cant_pedidos' => $cantPed,
                'estado_operativo' => $estadoOp,
                'ruta_origen' => $rOrig,
                'dia_origen' => $dOrig,
            ];
        }

        // ── FASE 3: PREVENTAS FUERA DE PLAN SIN VISITA ──
        foreach ($mapaPreventasId as $cid => $prInfo) {
            if (isset($cidsProcesados[$cid])) {
                continue;
            }
            $cidsProcesados[$cid] = true;
            $cn = $this->normalizarTexto($prInfo->cliente);

            $regMaestro = $maestroPorId[$cid] ?? ($maestroPorNorm[$cn] ?? null);
            $montoPrev = (float) $prInfo->monto_preventa;
            $cantPed = (int) $prInfo->cant_pedidos;

            if (!$regMaestro) {
                $estadoOp = 'CLIENTE_NUEVO';
                $motivoOp = 'Venta sin visita · No figura en padrón';
                $rOrig = 'SIN ASIGNAR';
                $dOrig = 'SIN DIA';
                $dirPdv = 'Dirección no registrada';
                $tNeg = '-';
                $clust = '-';
                $lat = 0.0;
                $lon = 0.0;
            } else {
                $rOrig = $regMaestro->ruta_origen;
                $dOrig = $regMaestro->dias_origen;
                $dirPdv = $regMaestro->direccion ?: 'Sin dirección';
                $tNeg = $regMaestro->tipo_negocio ?: '-';
                $clust = $regMaestro->cluster_t ?: '-';
                $lat = (float) ($regMaestro->latitud ?: 0.0);
                $lon = (float) ($regMaestro->longitud ?: 0.0);

                if ($rOrig === $rutaClean) {
                    $estadoOp = 'DESVIO_DIA';
                    $motivoOp = 'Venta sin visita · Programado: ' . strtoupper((string) $dOrig);
                } else {
                    $estadoOp = 'DESVIO_RUTA';
                    $motivoOp = "Venta sin visita · Ruta {$rOrig}";
                }
            }

            $clientesFinales[] = [
                'cliente_id' => $cid,
                'cliente' => $prInfo->cliente,
                'cliente_norm' => $cn,
                'direccion' => $dirPdv,
                'latitud' => $lat,
                'longitud' => $lon,
                'tipo_negocio' => $tNeg,
                'cluster_t' => $clust,
                'fue_visitado' => 0,
                'check_in' => '',
                'check_out' => '',
                'tiempo_pdv' => '',
                'motivo' => $motivoOp,
                'tipo_pago' => 'Contado',
                'monto_preventa' => round($montoPrev, 2),
                'cant_pedidos' => $cantPed,
                'estado_operativo' => $estadoOp,
                'ruta_origen' => $rOrig,
                'dia_origen' => $dOrig,
            ];
        }

        // Prioridad visual estricta
        $ordenPrioridad = [
            'PLAN_CON_VENTA' => 1,
            'VENTA_SIN_VISITA' => 2,
            'PLAN_SIN_VENTA' => 3,
            'PLAN_NO_VISITADO' => 4,
            'DESVIO_DIA' => 5,
            'DESVIO_RUTA' => 6,
            'CLIENTE_NUEVO' => 7,
        ];

        usort($clientesFinales, function ($a, $b) use ($ordenPrioridad): int {
            $prioA = $ordenPrioridad[$a['estado_operativo']] ?? 9;
            $prioB = $ordenPrioridad[$b['estado_operativo']] ?? 9;
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }
            $hA = !empty($a['check_in']) ? $a['check_in'] : '99:99:99';
            $hB = !empty($b['check_in']) ? $b['check_in'] : '99:99:99';
            if ($hA !== $hB) {
                return strcmp($hA, $hB);
            }
            return strcmp((string) $a['cliente'], (string) $b['cliente']);
        });

        // 6. Extracción de fotos desde la base de datos local de Laravel (visitas)
        $clientIdsValidos = array_values(array_filter(
            array_column($clientesFinales, 'cliente_id'),
            fn ($id) => (int) $id > 0
        ));

        $fotosMap = [];
        if (!empty($clientIdsValidos)) {
            try {
                $visitasLar = Visita::query()
                    ->whereIn('client_id', $clientIdsValidos)
                    ->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean])
                    ->whereDate('visited_at', $fechaNorm)
                    ->whereNotNull('photo_path')
                    ->whereRaw("TRIM(photo_path) != ''")
                    ->orderBy('visited_at', 'asc')
                    ->get();

                foreach ($visitasLar as $vLar) {
                    $cid = (int) $vLar->client_id;
                    $pPath = ltrim((string) $vLar->photo_path, '/');
                    $fotoUrl = str_starts_with($pPath, 'http') ? $pPath : "/storage/{$pPath}";

                    $fotosMap[$cid][] = [
                        'url' => $fotoUrl,
                        'hora' => $vLar->visited_at ? $vLar->visited_at->format('H:i:s') : '',
                        'lat' => (float) ($vLar->latitude ?: 0.0),
                        'lng' => (float) ($vLar->longitude ?: 0.0),
                        'comentarios' => (string) ($vLar->comments ?? ''),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Error consultando fotos de visitas en Laravel: ' . $e->getMessage());
            }
        }

        // 7. Enriquecer clientes con fotos y recalcular tiempos
        foreach ($clientesFinales as &$item) {
            $cid = (int) ($item['cliente_id'] ?? 0);
            $cFotos = $fotosMap[$cid] ?? [];
            $item['fotos'] = $cFotos;
            $item['total_fotos'] = count($cFotos);
            $horaApp = !empty($cFotos[0]['hora']) ? $cFotos[0]['hora'] : '';
            $item['hora_app'] = $horaApp;

            // Si no tenía coordenadas en padrón pero la foto tiene GPS válido, usar GPS de foto
            if (($item['latitud'] == 0 || $item['longitud'] == 0) && !empty($cFotos)) {
                foreach ($cFotos as $fGps) {
                    if (!empty($fGps['lat']) && !empty($fGps['lng'])) {
                        $item['latitud'] = (float) $fGps['lat'];
                        $item['longitud'] = (float) $fGps['lng'];
                        break;
                    }
                }
            }

            // Si tenía foto en App Android pero no marcación en Web ERP, considerar como visitado
            if (!empty($cFotos) && !$item['fue_visitado']) {
                $item['fue_visitado'] = 1;
                if ($item['estado_operativo'] === 'PLAN_NO_VISITADO') {
                    $item['estado_operativo'] = $item['monto_preventa'] > 0 ? 'PLAN_CON_VENTA' : 'PLAN_SIN_VENTA';
                    $item['motivo'] = $item['monto_preventa'] > 0
                        ? 'Compra confirmada (App)'
                        : ($cFotos[0]['comentarios'] ?: 'Visita registrada en App');
                } elseif ($item['estado_operativo'] === 'VENTA_SIN_VISITA') {
                    $item['estado_operativo'] = 'PLAN_CON_VENTA';
                    $item['motivo'] = 'Compra confirmada (App)';
                }
            }

            // Hora cronológica efectiva de la parada (menor entre Check-In Web y 1ra Foto App)
            $hWeb = trim((string) ($item['check_in'] ?? ''));
            if (strlen($hWeb) === 5) {
                $hWeb .= ':00';
            }
            $hApp = trim((string) ($horaApp ?? ''));
            if (strlen($hApp) === 5) {
                $hApp .= ':00';
            }

            $candidatos = array_filter([$hWeb, $hApp], fn ($h) => $h !== '' && $h !== '-');
            $item['hora_secuencia'] = !empty($candidatos) ? min($candidatos) : '';
        }
        unset($item);

        return [
            'ruta' => $ruta,
            'vendedor' => $vendedorNombre,
            'fecha' => $fechaNorm,
            'clientes' => $clientesFinales,
        ];
    }
}

