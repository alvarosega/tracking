<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleHorarioRutaAction
{
    /**
     * Obtiene el recorrido cronológico detallado de una ruta comparando paradas en sistema ERP y app móvil.
     * La columna 'tipo' se extrae directamente de la tabla central fact_visitas (ERP).
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

        $rutaClean = strtoupper(trim($ruta));
        $vendedor = 'No Asignado';

        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && !empty($dimInfo->vendedor)) {
                $vendedor = trim((string) $dimInfo->vendedor);
            }

            // Cartera asignada a la ruta en pan_ruteo para resolver nombres y client_ids
            $carteraRuta = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->select(['cliente_id', 'cliente', 'vendedor'])
                ->get();

            if ($vendedor === 'No Asignado') {
                $firstVend = $carteraRuta->firstWhere('vendedor', '!=', null);
                if ($firstVend && !empty($firstVend->vendedor)) {
                    $vendedor = trim((string) $firstVend->vendedor);
                }
            }

            $nombrePorClienteId = [];
            $clienteNormToId = [];

            foreach ($carteraRuta as $cRow) {
                if ($cRow->cliente_id) {
                    $cid = (int) $cRow->cliente_id;
                    $nombrePorClienteId[$cid] = trim((string) $cRow->cliente);
                    $cn = $this->normalizarTexto((string) $cRow->cliente);
                    if ($cn !== '' && !isset($clienteNormToId[$cn])) {
                        $clienteNormToId[$cn] = $cid;
                    }
                }
            }

            // Ventas y pedidos del día para enriquecer cliente_id y montos
            $preventasHoyPorCliente = [];
            try {
                $preventasHoy = DB::connection('supervisor')->table('fact_preventas')
                    ->where('fecha_norm', $fechaNorm)
                    ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                    ->select('cliente_id', 'cliente', DB::raw("SUM(monto_final) as total_monto"))
                    ->groupBy('cliente_id', 'cliente')
                    ->get();

                foreach ($preventasHoy as $ph) {
                    $totMonto = (float) ($ph->total_monto ?: 0.0);
                    if ($ph->cliente_id) {
                        $cid = (int) $ph->cliente_id;
                        $preventasHoyPorCliente[$cid] = $totMonto;
                        if (!isset($nombrePorClienteId[$cid]) && !empty($ph->cliente)) {
                            $nombrePorClienteId[$cid] = trim((string) $ph->cliente);
                        }
                        $cn = $this->normalizarTexto((string) $ph->cliente);
                        if ($cn !== '' && !isset($clienteNormToId[$cn])) {
                            $clienteNormToId[$cn] = $cid;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug("No se consultaron preventas en GetDetalleHorarioRutaAction: " . $e->getMessage());
            }

            $paradas = [];

            // 1. Marcajes del sistema central (fact_visitas) - ERP / Web
            // Consultamos las visitas del día en el sistema central para extraer el 'tipo' y check_in
            $todasFilasSistema = DB::connection('supervisor')->table('fact_visitas as fv')
                ->where('fv.fecha_norm', $fechaNorm)
                ->select([
                    DB::raw("COALESCE(fv.cliente, 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(fv.cliente_norm, '') as cliente_norm"),
                    'fv.empleado_norm',
                    'fv.empleado',
                    DB::raw("COALESCE(NULLIF(TRIM(fv.tipo), ''), NULLIF(TRIM(fv.motivo), ''), 'Visita') as tipo"),
                    DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                    DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
                    DB::raw("COALESCE(fv.tiempo_pdv, '00:00:00') as tiempo_pdv"),
                    DB::raw("CAST(COALESCE(fv.monto, 0.0) AS DECIMAL(12, 2)) as monto"),
                ])
                ->orderBy('fv.check_in', 'asc')
                ->get();

            $vendedorNorm = $this->normalizarTexto($vendedor);

            // Filtramos las visitas que pertenecen a la ruta (por vendedor O por cliente de cartera)
            $filasSistemaRuta = $todasFilasSistema->filter(function ($fs) use ($vendedorNorm, $clienteNormToId) {
                // Coincidencia de vendedor normalizado
                if ($vendedorNorm !== 'no asignado') {
                    $empNorm1 = $this->normalizarTexto((string) $fs->empleado_norm);
                    $empNorm2 = $this->normalizarTexto((string) $fs->empleado);
                    if ($empNorm1 === $vendedorNorm || $empNorm2 === $vendedorNorm) {
                        return true;
                    }
                }

                // Coincidencia de cliente asignado a la ruta
                $cn = $this->normalizarTexto((string) ($fs->cliente_norm ?: $fs->cliente));
                if ($cn !== '' && isset($clienteNormToId[$cn])) {
                    return true;
                }

                return false;
            });

            foreach ($filasSistemaRuta as $fs) {
                $hIn = trim((string) $fs->check_in);
                if (strlen($hIn) === 5) {
                    $hIn .= ':00';
                }

                $normNombre = $this->normalizarTexto((string) ($fs->cliente_norm ?: $fs->cliente));
                $cId = $clienteNormToId[$normNombre] ?? null;
                $key = $cId ? "id_{$cId}" : "norm_{$normNombre}";

                $nombreCliente = ($cId && isset($nombrePorClienteId[$cId]))
                    ? $nombrePorClienteId[$cId]
                    : trim((string) $fs->cliente);

                $montoFs = (float) ($fs->monto ?: 0.0);
                if ($cId && isset($preventasHoyPorCliente[$cId]) && $preventasHoyPorCliente[$cId] > $montoFs) {
                    $montoFs = $preventasHoyPorCliente[$cId];
                }

                // Tipo extraído directamente de la tabla fact_visitas
                $tipoFact = trim((string) $fs->tipo);
                if ($montoFs > 0 || mb_strtolower($tipoFact) === 'preventa') {
                    $tipoFinal = 'PreVenta';
                } else {
                    $tipoFinal = $tipoFact ?: 'Visita';
                }

                if (!isset($paradas[$key])) {
                    $paradas[$key] = [
                        'cliente' => $nombreCliente,
                        'client_id' => $cId,
                        'hora_web' => $hIn,
                        'hora_app' => null,
                        'hora_orden' => $hIn,
                        'tipo' => $tipoFinal,
                        'monto' => $montoFs,
                        'check_out_web' => trim((string) $fs->check_out) ?: null,
                        'tiempo_pdv_web' => trim((string) $fs->tiempo_pdv) ?: null,
                        'foto_url' => null,
                        'fotos_count' => 0,
                        'tiene_fotos_historicas' => false,
                    ];
                } else {
                    if (strcmp($hIn, (string) $paradas[$key]['hora_web']) < 0) {
                        $paradas[$key]['hora_web'] = $hIn;
                        $paradas[$key]['hora_orden'] = $hIn;
                    }
                    $paradas[$key]['monto'] += $montoFs;
                    if ($tipoFinal === 'PreVenta') {
                        $paradas[$key]['tipo'] = 'PreVenta';
                    }
                }
            }

            // 2. Marcajes de la app móvil (tabla local visitas)
            $fInicioDia = "{$fechaNorm} 00:00:00";
            $fFinDia = "{$fechaNorm} 23:59:59";

            $filasMovil = Visita::query()
                ->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean])
                ->whereBetween('visited_at', [$fInicioDia, $fFinDia])
                ->orderBy('visited_at', 'asc')
                ->get();

            foreach ($filasMovil as $fm) {
                $cid = $fm->client_id ? (int) $fm->client_id : null;
                $horaM = $fm->visited_at ? $fm->visited_at->format('H:i:s') : '00:00:00';

                $fotoUrl = null;
                $fotoRel = ltrim(str_replace('\\', '/', (string) $fm->photo_path), '/');
                if ($fotoRel !== '') {
                    if (str_starts_with($fotoRel, 'http://') || str_starts_with($fotoRel, 'https://')) {
                        $fotoUrl = $fotoRel;
                    } elseif (str_starts_with($fotoRel, 'storage/')) {
                        $fotoUrl = '/' . $fotoRel;
                    } else {
                        $fotoUrl = '/storage/' . $fotoRel;
                    }
                }

                $key = null;
                if ($cid) {
                    $key = "id_{$cid}";
                } elseif ($fm->is_opportunity || empty($fm->opportunity_client_name)) {
                    $key = "opp_{$fm->id}";
                } else {
                    $normOpp = $this->normalizarTexto((string) $fm->opportunity_client_name);
                    $key = "norm_{$normOpp}";
                }

                if (isset($paradas[$key])) {
                    // Parada ya existente (registrada en fact_visitas): sincronizamos hora app y fotos
                    // NOTA: Mantenemos el 'tipo' extraído de fact_visitas
                    if ($paradas[$key]['hora_app'] === null || strcmp($horaM, (string) $paradas[$key]['hora_app']) < 0) {
                        $paradas[$key]['hora_app'] = $horaM;
                    }
                    if (strcmp($horaM, (string) $paradas[$key]['hora_orden']) < 0) {
                        $paradas[$key]['hora_orden'] = $horaM;
                    }
                    if ($fotoUrl && empty($paradas[$key]['foto_url'])) {
                        $paradas[$key]['foto_url'] = $fotoUrl;
                        $paradas[$key]['fotos_count']++;
                    }
                    if ($cid && empty($paradas[$key]['client_id'])) {
                        $paradas[$key]['client_id'] = $cid;
                    }
                } else {
                    // Parada registrada en la app móvil que no coincidió en la primera pasada
                    // Buscamos si el cliente tiene registro en fact_visitas
                    $normCli = ($cid && isset($nombrePorClienteId[$cid]))
                        ? $this->normalizarTexto($nombrePorClienteId[$cid])
                        : $this->normalizarTexto((string) $fm->opportunity_client_name);

                    $coincidenciaFact = $todasFilasSistema->first(function ($fs) use ($normCli, $cid, $clienteNormToId) {
                        $cn = $this->normalizarTexto((string) ($fs->cliente_norm ?: $fs->cliente));
                        if ($normCli !== '' && $cn === $normCli) {
                            return true;
                        }
                        if ($cid && isset($clienteNormToId[$cn]) && $clienteNormToId[$cn] === $cid) {
                            return true;
                        }
                        return false;
                    });

                    $nombreCliente = ($cid && isset($nombrePorClienteId[$cid]))
                        ? $nombrePorClienteId[$cid]
                        : null;

                    if (!$nombreCliente) {
                        $nombreCliente = $fm->opportunity_client_name
                            ?: ($cid ? "CLIENTE #{$cid}" : 'CLIENTE APP S/N');
                    }

                    // Extraer tipo desde fact_visitas si existe
                    if ($coincidenciaFact) {
                        $tipoFinal = trim((string) $coincidenciaFact->tipo) ?: 'Visita';
                        $hWeb = trim((string) $coincidenciaFact->check_in);
                        if (strlen($hWeb) === 5) {
                            $hWeb .= ':00';
                        }
                        $montoReal = (float) ($coincidenciaFact->monto ?: 0.0);
                        $checkOutWeb = trim((string) $coincidenciaFact->check_out) ?: null;
                        $tiempoPdvWeb = trim((string) $coincidenciaFact->tiempo_pdv) ?: null;
                    } else {
                        $tipoFinal = $fm->is_opportunity ? 'Oportunidad' : 'Visita';
                        $hWeb = null;
                        $montoReal = 0.0;
                        $checkOutWeb = null;
                        $tiempoPdvWeb = null;
                    }

                    $paradas[$key] = [
                        'cliente' => $nombreCliente,
                        'client_id' => $cid,
                        'hora_web' => $hWeb,
                        'hora_app' => $horaM,
                        'hora_orden' => ($hWeb && strcmp($hWeb, $horaM) < 0) ? $hWeb : $horaM,
                        'tipo' => $tipoFinal,
                        'monto' => $montoReal,
                        'check_out_web' => $checkOutWeb,
                        'tiempo_pdv_web' => $tiempoPdvWeb,
                        'foto_url' => $fotoUrl,
                        'fotos_count' => $fotoUrl ? 1 : 0,
                        'tiene_fotos_historicas' => false,
                    ];
                }
            }

            // 3. Detectar qué clientes tienen fotos históricas en la base de datos local
            $todosClientIds = array_values(array_filter(
                array_column($paradas, 'client_id'),
                fn ($id) => !empty($id)
            ));

            if (!empty($todosClientIds)) {
                try {
                    $cidsConHistorial = Visita::query()
                        ->whereIn('client_id', $todosClientIds)
                        ->whereNotNull('photo_path')
                        ->where('photo_path', '!=', '')
                        ->select('client_id')
                        ->distinct()
                        ->pluck('client_id')
                        ->map(fn ($id) => (int) $id)
                        ->flip()
                        ->toArray();

                    foreach ($paradas as &$p) {
                        if (!empty($p['client_id']) && isset($cidsConHistorial[(int) $p['client_id']])) {
                            $p['tiene_fotos_historicas'] = true;
                        }
                    }
                    unset($p);
                } catch (\Throwable $e) {
                    Log::debug("Error verificando fotos históricas: " . $e->getMessage());
                }
            }

            // 4. Ordenar cronológicamente por la hora más temprana registrada
            $eventos = array_values($paradas);
            usort($eventos, fn ($a, $b) => strcmp((string) ($a['hora_orden'] ?? '99:99:99'), (string) ($b['hora_orden'] ?? '99:99:99')));

            foreach ($eventos as $i => &$ev) {
                $ev['orden'] = $i + 1;
            }
            unset($ev);

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'total_eventos' => count($eventos),
                'eventos' => $eventos,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleHorarioRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'total_eventos' => 0,
                'eventos' => [],
            ];
        }
    }

    /**
     * Normaliza cadenas de texto para emparejamiento fonético/alfanumérico estricto.
     */
    private function normalizarTexto(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $str = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $str
        );
        return preg_replace('/\s+/', ' ', $str) ?? $str;
    }
}
