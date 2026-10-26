<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Illuminate\Support\Facades\DB;

class GetAvanceHorariosTimelineAction
{
    /**
     * Obtiene el timeline cronológico detallado de la jornada para una ruta y fecha específica,
     * combinando los eventos registrados en el Sistema Web (fact_visitas) y la App Móvil (visitas).
     *
     * @param string $fecha YYYY-MM-DD
     * @param string $ruta
     * @return array<string, mixed>
     */
    public function execute(string $fecha, string $ruta): array
    {
        $rutaClean = strtoupper(trim($ruta));

        // 1. Obtener datos del vendedor y canal de la ruta
        $rutaInfo = DB::connection('supervisor')->table('dim_rutas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->select([
                DB::raw("COALESCE(TRIM(vendedor), 'No Asignado') as vendedor"),
                DB::raw("COALESCE(TRIM(vendedor_norm), '') as vendedor_norm"),
                DB::raw("COALESCE(TRIM(canal), 'GENERAL') as canal"),
            ])
            ->first();

        $vendedorNombre = $rutaInfo ? (string) $rutaInfo->vendedor : 'No Asignado';
        $vendedorNorm = $rutaInfo ? $this->normalizarTexto((string) ($rutaInfo->vendedor_norm ?: $rutaInfo->vendedor)) : '';

        // 2. Obtener marcajes de fact_visitas (Sistema Web)
        $querySis = DB::connection('supervisor')->table('fact_visitas as fv')
            ->where('fv.fecha_norm', $fecha);

        if ($vendedorNorm !== '') {
            $querySis->where(function ($q) use ($vendedorNorm, $vendedorNombre) {
                $q->where(DB::raw("LOWER(TRIM(fv.empleado))"), mb_strtolower($vendedorNombre, 'UTF-8'))
                  ->orWhere(DB::raw("LOWER(TRIM(fv.empleado_norm))"), $vendedorNorm);
            });
        }

        $filasSistema = $querySis->select([
            'fv.id',
            DB::raw("COALESCE(fv.cliente, 'Cliente S/N') as cliente"),
            DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
            DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
            DB::raw("COALESCE(fv.tiempo_pdv, '-') as tiempo_pdv"),
            DB::raw("COALESCE(fv.monto, 0.0) as monto"),
            DB::raw("COALESCE(fv.motivo, '') as motivo"),
        ])
        ->orderBy('fv.check_in')
        ->orderBy('fv.id')
        ->get();

        // 3. Obtener marcajes de visitas (App Móvil en MySQL)
        $fInicioTime = "{$fecha} 00:00:00";
        $fFinTime = "{$fecha} 23:59:59";

        $filasMovil = collect();
        try {
            $filasMovil = DB::connection('mysql')->table('visitas')
                ->where(DB::raw("TRIM(UPPER(route))"), $rutaClean)
                ->where('visited_at', '>=', $fInicioTime)
                ->where('visited_at', '<=', $fFinTime)
                ->select([
                    'id',
                    'client_id',
                    'opportunity_client_name',
                    DB::raw("COALESCE(status, 'VISITADO') as status"),
                    DB::raw("COALESCE(comments, '') as comments"),
                    'photo_path',
                    'visited_at',
                    'latitude',
                    'longitude',
                ])
                ->orderBy('visited_at')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            // Silencioso si la conexión de visitas no responde
        }

        // Resolver nombres de clientes para visitas móviles si falta el nombre
        $clientIds = $filasMovil->pluck('client_id')->filter()->unique()->values()->all();
        $nombresClientes = [];
        if (!empty($clientIds)) {
            try {
                $nombresClientes = DB::connection('supervisor')->table('pan_ruteo')
                    ->whereIn('cliente_id', $clientIds)
                    ->select('cliente_id', DB::raw("MAX(cliente) as nombre"))
                    ->groupBy('cliente_id')
                    ->pluck('nombre', 'cliente_id')
                    ->all();
            } catch (\Throwable $e) {
                // Silencioso
            }
        }

        // 4. Armar timeline unificado
        $timeline = [];
        $validSisIn = [];
        $validSisOut = [];

        foreach ($filasSistema as $s) {
            $cin = (string) $s->check_in;
            $cout = (string) $s->check_out;
            $monto = (float) $s->monto;

            if ($cin !== '00:00:00' && $cin !== '0' && $cin !== '') {
                $validSisIn[] = $cin;
            }
            if ($cout !== '00:00:00' && $cout !== '0' && $cout !== '') {
                $validSisOut[] = $cout;
            }

            $tipoEvento = $monto > 0 ? 'VENTA' : ($s->motivo ?: 'VISITA');

            $timeline[] = [
                'id' => 'sis_' . $s->id,
                'hora' => $cin,
                'check_in' => ($cin !== '00:00:00' && $cin !== '0') ? $cin : '-',
                'check_out' => ($cout !== '00:00:00' && $cout !== '0') ? $cout : '-',
                'segundos' => $this->horaASegundos($cin),
                'origen' => 'SISTEMA',
                'cliente' => (string) $s->cliente,
                'tipo_evento' => $tipoEvento,
                'monto' => $monto,
                'tiempo_pdv' => (string) $s->tiempo_pdv,
                'motivo' => (string) $s->motivo,
                'foto_url' => null,
                'latitud' => null,
                'longitud' => null,
            ];
        }

        $validMovHoras = [];
        $validMovFotos = [];

        foreach ($filasMovil as $m) {
            $hora = $m->visited_at ? date('H:i:s', strtotime((string) $m->visited_at)) : '00:00:00';
            $cid = $m->client_id ? (int) $m->client_id : null;

            $nombreCli = trim((string) ($m->opportunity_client_name ?? ''));
            if ($nombreCli === '' && $cid && isset($nombresClientes[$cid])) {
                $nombreCli = (string) $nombresClientes[$cid];
            }
            if ($nombreCli === '') {
                $nombreCli = $cid ? "Cliente #{$cid}" : "Cliente S/N";
            }
            if ($cid && !str_contains($nombreCli, "#{$cid}")) {
                $nombreCli .= " (ID: {$cid})";
            }

            $fotoRel = ltrim(str_replace('\\', '/', (string) ($m->photo_path ?? '')), '/');
            $fotoUrl = null;
            if ($fotoRel !== '') {
                if (str_starts_with($fotoRel, 'http://') || str_starts_with($fotoRel, 'https://')) {
                    $fotoUrl = $fotoRel;
                } elseif (str_starts_with($fotoRel, 'storage/')) {
                    $fotoUrl = '/' . $fotoRel;
                } else {
                    $fotoUrl = '/storage/' . $fotoRel;
                }
            }

            if ($hora !== '00:00:00' && $hora !== '0' && $hora !== '') {
                $validMovHoras[] = $hora;
                if ($fotoUrl) {
                    $validMovFotos[] = $hora;
                }
            }

            $lat = (float) ($m->latitude ?? 0.0);
            $lng = (float) ($m->longitude ?? 0.0);

            $timeline[] = [
                'id' => 'mov_' . $m->id,
                'hora' => $hora,
                'check_in' => $hora,
                'check_out' => '-',
                'segundos' => $this->horaASegundos($hora),
                'origen' => 'MOVIL',
                'cliente' => $nombreCli,
                'tipo_evento' => (string) $m->status,
                'monto' => 0.0,
                'tiempo_pdv' => '-',
                'motivo' => (string) $m->comments,
                'foto_url' => $fotoUrl,
                'latitud' => $lat != 0.0 ? $lat : null,
                'longitud' => $lng != 0.0 ? $lng : null,
            ];
        }

        // Ordenar cronológicamente
        usort($timeline, fn ($a, $b) => $a['segundos'] <=> $b['segundos']);

        sort($validSisIn);
        sort($validSisOut);
        sort($validMovHoras);
        sort($validMovFotos);

        $resumen = [
            'ingreso_sistema' => !empty($validSisIn) ? $validSisIn[0] : '-',
            'salida_sistema' => !empty($validSisOut) ? end($validSisOut) : (!empty($validSisIn) ? end($validSisIn) : '-'),
            'ingreso_movil' => !empty($validMovHoras) ? $validMovHoras[0] : '-',
            'salida_movil' => !empty($validMovFotos) ? end($validMovFotos) : (!empty($validMovHoras) ? end($validMovHoras) : '-'),
            'total_sistema' => count($filasSistema),
            'total_movil' => count($filasMovil),
        ];

        return [
            'fecha' => $fecha,
            'ruta' => $rutaClean,
            'vendedor' => $vendedorNombre,
            'canal' => $rutaInfo ? (string) $rutaInfo->canal : 'GENERAL',
            'total_eventos' => count($timeline),
            'resumen' => $resumen,
            'timeline' => $timeline,
        ];
    }

    private function horaASegundos(string $hora): int
    {
        try {
            $parts = explode(':', trim($hora));
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);
            $s = (int) ($parts[2] ?? 0);
            return ($h * 3600) + ($m * 60) + $s;
        } catch (\Throwable $e) {
            return 999999;
        }
    }

    private function normalizarTexto(?string $texto): string
    {
        if (!$texto) {
            return '';
        }
        $s = mb_strtolower(trim($texto), 'UTF-8');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9 ]/', '', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}

