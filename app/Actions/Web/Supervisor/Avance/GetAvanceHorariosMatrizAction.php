<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetAvanceHorariosMatrizAction
{
    private const CACHE_TTL_SECS = 300;

    /**
     * Calcula la matriz comparativa de horarios (Sistema Web vs App Móvil) en un rango de fechas.
     *
     * @param string|null $fechaInicio
     * @param string|null $fechaFin
     * @param string|null $canal
     * @return array<string, mixed>
     */
    public function execute(?string $fechaInicio = null, ?string $fechaFin = null, ?string $canal = null): array
    {
        if (!$fechaFin) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaFin = $maxFecha ? (string) $maxFecha : date('Y-m-d');
        }

        if (!$fechaInicio) {
            $fechaInicio = Carbon::parse($fechaFin)->subDays(6)->format('Y-m-d');
        }

        $canalKey = $canal ?: 'ALL';
        $cacheKey = "avance_horarios_matriz_" . md5("{$fechaInicio}_{$fechaFin}_{$canalKey}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECS, function () use ($fechaInicio, $fechaFin, $canal) {
            return $this->procesarMatriz($fechaInicio, $fechaFin, $canal);
        });
    }

    private function procesarMatriz(string $fechaInicio, string $fechaFin, ?string $canalFiltro): array
    {
        // 1. Generar días en rango
        $period = CarbonPeriod::create($fechaInicio, $fechaFin);
        $fechasRango = [];
        foreach ($period as $date) {
            $fechasRango[] = $date->format('Y-m-d');
        }

        // 2. Obtener catálogo maestro de rutas desde dim_rutas
        $dimQuery = DB::connection('supervisor')->table('dim_rutas')
            ->whereNotNull('ruta')
            ->where(DB::raw("TRIM(ruta)"), '!=', '')
            ->where(DB::raw("TRIM(UPPER(ruta))"), '!=', 'TDB 99');

        if ($canalFiltro && strtoupper($canalFiltro) !== 'TODOS' && strtoupper($canalFiltro) !== 'TODAS') {
            $dimQuery->where(DB::raw("TRIM(UPPER(canal))"), strtoupper(trim($canalFiltro)));
        }

        $dimRutasRows = $dimQuery->select([
            DB::raw("TRIM(UPPER(ruta)) as ruta"),
            DB::raw("COALESCE(TRIM(canal), 'GENERAL') as canal"),
            DB::raw("COALESCE(TRIM(vendedor), 'No Asignado') as vendedor"),
            DB::raw("COALESCE(TRIM(vendedor_norm), '') as vendedor_norm"),
        ])->get();

        $dimRutas = [];
        $vendedorMap = [];
        $canalesSet = [];

        foreach ($dimRutasRows as $r) {
            $ruta = (string) $r->ruta;
            $canal = (string) $r->canal;
            $vendedor = (string) $r->vendedor;

            $dimRutas[$ruta] = [
                'ruta' => $ruta,
                'canal' => $canal,
                'vendedor' => $vendedor,
            ];

            if ($canal !== 'SIN CANAL' && $canal !== 'GENERAL') {
                $canalesSet[$canal] = true;
            }

            $vNorm = $this->normalizarTexto($r->vendedor_norm ?: $vendedor);
            if ($vNorm !== '') {
                $vendedorMap[$vNorm] = $dimRutas[$ruta];
            }
        }

        // 3. Obtener marcajes de fact_visitas (Sistema Web)
        $filasSistema = DB::connection('supervisor')->table('fact_visitas as fv')
            ->where('fv.fecha_norm', '>=', $fechaInicio)
            ->where('fv.fecha_norm', '<=', $fechaFin)
            ->select([
                DB::raw("CAST(fv.fecha_norm AS CHAR) as fecha"),
                DB::raw("COALESCE(fv.empleado, '') as empleado"),
                DB::raw("COALESCE(fv.empleado_norm, '') as empleado_norm"),
                DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
            ])
            ->orderBy('fv.fecha_norm')
            ->orderBy('fv.check_in')
            ->get();

        $sisMap = [];
        $cacheResolucion = [];

        foreach ($filasSistema as $row) {
            $empKey = $this->normalizarTexto((string) ($row->empleado_norm ?: $row->empleado));
            if ($empKey === '') {
                continue;
            }

            if (!isset($cacheResolucion[$empKey])) {
                $match = $vendedorMap[$empKey] ?? null;
                if (!$match) {
                    foreach ($vendedorMap as $vKey => $info) {
                        if (str_contains($empKey, $vKey) || str_contains($vKey, $empKey)) {
                            $match = $info;
                            break;
                        }
                    }
                }
                $cacheResolucion[$empKey] = $match;
            }

            $infoRuta = $cacheResolucion[$empKey];
            if (!$infoRuta) {
                continue;
            }

            $rNombre = $infoRuta['ruta'];
            $fFecha = (string) $row->fecha;
            $k = "{$rNombre}|{$fFecha}";

            if (!isset($sisMap[$k])) {
                $sisMap[$k] = [];
            }
            $sisMap[$k][] = [
                'check_in' => (string) $row->check_in,
                'check_out' => (string) $row->check_out,
            ];
        }

        // 4. Obtener marcajes de visitas (App Móvil en MySQL alva)
        $movMap = [];
        try {
            $fInicioTime = "{$fechaInicio} 00:00:00";
            $fFinTime = "{$fechaFin} 23:59:59";

            $filasMovil = DB::connection('mysql')->table('visitas')
                ->where('visited_at', '>=', $fInicioTime)
                ->where('visited_at', '<=', $fFinTime)
                ->select([
                    DB::raw("DATE(visited_at) as fecha"),
                    DB::raw("COALESCE(NULLIF(TRIM(UPPER(route)), ''), 'SIN RUTA') as ruta"),
                    DB::raw("TIME(visited_at) as hora"),
                    DB::raw("photo_path IS NOT NULL AND TRIM(photo_path) != '' as tiene_foto"),
                ])
                ->orderBy('visited_at')
                ->get();

            foreach ($filasMovil as $m) {
                $rNombre = (string) $m->ruta;
                $fFecha = (string) $m->fecha;
                $k = "{$rNombre}|{$fFecha}";

                if (!isset($movMap[$k])) {
                    $movMap[$k] = [];
                }
                $movMap[$k][] = [
                    'hora' => (string) ($m->hora ?: '00:00:00'),
                    'tiene_foto' => (bool) $m->tiene_foto,
                ];
            }
        } catch (\Throwable $e) {
            // Silencioso si la conexión a visitas no está disponible temporalmente
        }

        // 5. Construir Matriz Final
        $rutasSet = array_keys($dimRutas);
        sort($rutasSet);

        $matriz = [];
        foreach ($rutasSet as $ruta) {
            $infoR = $dimRutas[$ruta];
            $diasData = [];

            foreach ($fechasRango as $f) {
                $k = "{$ruta}|{$f}";
                $msSis = $sisMap[$k] ?? [];
                $msMov = $movMap[$k] ?? [];

                // Sistema Web: primer check-in y último check-out
                $validSisIn = array_values(array_filter(array_column($msSis, 'check_in'), fn ($h) => $h !== '00:00:00' && $h !== '0' && $h !== ''));
                $validSisOut = array_values(array_filter(array_column($msSis, 'check_out'), fn ($h) => $h !== '00:00:00' && $h !== '0' && $h !== ''));
                sort($validSisIn);
                sort($validSisOut);

                $ingSis = !empty($validSisIn) ? $validSisIn[0] : '-';
                $salSis = !empty($validSisOut) ? end($validSisOut) : (!empty($validSisIn) ? end($validSisIn) : '-');

                // Móvil: primer visited_at y última foto o marcaje
                $validMov = array_values(array_filter($msMov, fn ($m) => $m['hora'] !== '00:00:00' && $m['hora'] !== '0' && $m['hora'] !== ''));
                $movFotos = array_values(array_filter($validMov, fn ($m) => $m['tiene_foto']));

                $ingMov = !empty($validMov) ? $validMov[0]['hora'] : '-';
                $salMov = !empty($movFotos) ? end($movFotos)['hora'] : (!empty($validMov) ? end($validMov)['hora'] : '-');

                $diasData[$f] = [
                    'ingreso_sistema' => $ingSis,
                    'salida_sistema' => $salSis,
                    'ingreso_movil' => $ingMov,
                    'salida_movil' => $salMov,
                    'eventos_sistema' => count($msSis),
                    'eventos_movil' => count($validMov),
                    'tiene_actividad' => ($ingSis !== '-' || $ingMov !== '-'),
                ];
            }

            $matriz[] = [
                'ruta' => $ruta,
                'vendedor' => $infoR['vendedor'],
                'canal' => $infoR['canal'],
                'dias' => $diasData,
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'fechas' => $fechasRango,
            'canales' => array_merge(['TODOS'], array_keys($canalesSet)),
            'total_rutas' => count($matriz),
            'matriz' => $matriz,
        ];
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

