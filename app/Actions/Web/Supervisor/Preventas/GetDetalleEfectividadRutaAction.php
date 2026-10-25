<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleEfectividadRutaAction
{
    private const MONTO_MINIMO_COMPRA_EFECTIVA = 70.0;
    private const UMBRAL_EFECTIVIDAD_PCT = 55.0;

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
     * Obtiene el desglose auditado de efectividad comercial para una ruta.
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
        $rutaClean = strtoupper(trim($ruta));
        $vendedor = 'No Asignado';
        $carteraPlan = 0;

        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && $dimInfo->vendedor) {
                $vendedor = trim($dimInfo->vendedor);
            }

            // 1. Cartera plan activa
            $rowPlan = DB::connection('supervisor')->table('pan_ruteo')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($tuplaDias): void {
                    $q->whereIn(DB::raw("LOWER(TRIM(dia_norm))"), $tuplaDias)
                      ->orWhereIn(DB::raw("LOWER(TRIM(dia))"), $tuplaDias);
                })
                ->where('cliente_id', '>', 0)
                ->whereIn(DB::raw("UPPER(TRIM(COALESCE(estado, 'ACTIVO')))"), ['ACTIVO', 'ACTIVA', 'VIGENTE'])
                ->selectRaw("COUNT(DISTINCT cliente_id) as total_plan")
                ->first();

            if ($rowPlan && isset($rowPlan->total_plan)) {
                $carteraPlan = (int) $rowPlan->total_plan;
            }

            // 2. Preventas del día
            $filas = DB::connection('supervisor')->table('fact_preventas')
                ->where('fecha_norm', $fechaNorm)
                ->where('monto_final', '>', 0)
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->select([
                    DB::raw("COALESCE(TRIM(nro_preventa), '-') as nro_preventa"),
                    'cliente_id',
                    DB::raw("COALESCE(TRIM(cliente), 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(TRIM(producto), '-') as producto"),
                    DB::raw("COALESCE(TRIM(categoria), 'GENERAL') as categoria"),
                    DB::raw("COALESCE(cantidad, 0) as cantidad"),
                    DB::raw("CAST(COALESCE(monto_final, 0.0) AS DECIMAL(12, 2)) as monto_final"),
                ])
                ->orderBy('cliente', 'asc')
                ->orderBy('nro_preventa', 'asc')
                ->get();

            $clientesDict = [];
            foreach ($filas as $f) {
                $cid = (int) $f->cliente_id;
                $monto = (float) $f->monto_final;
                $cant = (int) ($f->cantidad ?: 1);

                if (!isset($clientesDict[$cid])) {
                    $clientesDict[$cid] = [
                        'cliente_id' => $cid,
                        'cliente' => $f->cliente,
                        'total_bs' => 0.0,
                        'notas_preventa' => [],
                        'total_items' => 0,
                        'productos' => [],
                    ];
                }

                $clientesDict[$cid]['total_bs'] = round($clientesDict[$cid]['total_bs'] + $monto, 2);
                $clientesDict[$cid]['notas_preventa'][$f->nro_preventa] = true;
                $clientesDict[$cid]['total_items'] += $cant;
                $clientesDict[$cid]['productos'][] = [
                    'nro_preventa' => $f->nro_preventa,
                    'producto' => $f->producto,
                    'categoria' => $f->categoria,
                    'cantidad' => $cant,
                    'monto' => $monto,
                ];
            }

            $clientesCumplen = [];
            $clientesBajoMinimo = [];

            foreach ($clientesDict as $c) {
                $c['notas_preventa'] = implode(', ', array_keys($c['notas_preventa']));
                if ($c['total_bs'] >= self::MONTO_MINIMO_COMPRA_EFECTIVA) {
                    $c['cumple_minimo'] = true;
                    $c['brecha_minima'] = 0.0;
                    $clientesCumplen[] = $c;
                } else {
                    $c['cumple_minimo'] = false;
                    $c['brecha_minima'] = round(self::MONTO_MINIMO_COMPRA_EFECTIVA - $c['total_bs'], 2);
                    $clientesBajoMinimo[] = $c;
                }
            }

            usort($clientesCumplen, fn ($a, $b) => $b['total_bs'] <=> $a['total_bs']);
            usort($clientesBajoMinimo, fn ($a, $b) => $a['total_bs'] <=> $b['total_bs']);

            $cantEfectivos = count($clientesDict);
            $efectividadPct = $carteraPlan > 0 ? round(($cantEfectivos / $carteraPlan) * 100.0, 1) : 0.0;
            $cumpleUmbral = $efectividadPct >= self::UMBRAL_EFECTIVIDAD_PCT;
            $puntosObtenidos = $cumpleUmbral ? 30.0 : 0.0;

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'totales' => [
                    'cartera_plan' => $carteraPlan,
                    'total_compradores' => count($clientesDict),
                    'clientes_efectivos' => $cantEfectivos,
                    'clientes_sobre_minimo' => count($clientesCumplen),
                    'clientes_bajo_minimo' => count($clientesBajoMinimo),
                    'efectividad_pct' => $efectividadPct,
                    'cumple_umbral' => $cumpleUmbral,
                    'puntos_obtenidos' => $puntosObtenidos,
                ],
                'clientes_cumplen' => $clientesCumplen,
                'clientes_bajo_minimo' => $clientesBajoMinimo,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleEfectividadRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'totales' => [
                    'cartera_plan' => 0,
                    'total_compradores' => 0,
                    'clientes_efectivos' => 0,
                    'clientes_sobre_minimo' => 0,
                    'clientes_bajo_minimo' => 0,
                    'efectividad_pct' => 0.0,
                    'cumple_umbral' => false,
                    'puntos_obtenidos' => 0.0,
                ],
                'clientes_cumplen' => [],
                'clientes_bajo_minimo' => [],
            ];
        }
    }
}

