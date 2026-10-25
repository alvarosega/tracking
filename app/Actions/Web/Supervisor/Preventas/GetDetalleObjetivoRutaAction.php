<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleObjetivoRutaAction
{
    private const MESES_ESP_ABR = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    /**
     * Obtiene el balance detallado del objetivo diario y pedidos desglosados de una ruta.
     *
     * @param string $ruta
     * @param string|null $fecha
     * @param string|null $mes
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null, ?string $mes = null): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $mesNorm = ($mes && strlen(trim($mes)) >= 7) ? trim($mes) : substr($fechaNorm, 0, 7);
        $carbonFecha = Carbon::parse($fechaNorm);
        $rutaClean = strtoupper(trim($ruta));

        $year = (int) $carbonFecha->year;
        $month = (int) $carbonFecha->month;
        $fInicioMes = sprintf('%04d-%02d-01', $year, $month);

        $vendedor = 'No Asignado';
        $metaMes = 0.0;
        $factPrevio = 0.0;

        try {
            // 1. Preventista oficial
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && $dimInfo->vendedor) {
                $vendedor = trim($dimInfo->vendedor);
            }

            // 2. Meta del mes en fact_metas
            $patronesMes = [
                self::MESES_ESP_ABR[$month] ?? '',
                sprintf('%04d-%02d', $year, $month),
                (string) $month,
            ];

            $metaRow = DB::connection('supervisor')->table('fact_metas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->where(function ($q) use ($patronesMes, $fInicioMes, $month): void {
                    $q->where('mes', $fInicioMes)
                      ->orWhereIn(DB::raw("LOWER(TRIM(mes))"), array_filter($patronesMes))
                      ->orWhere('mes', 'like', '%' . (self::MESES_ESP_ABR[$month] ?? 'xxx') . '%');
                })
                ->selectRaw("MAX(CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(14, 2))) as meta_vol")
                ->first();

            if ($metaRow && $metaRow->meta_vol) {
                $metaMes = (float) $metaRow->meta_vol;
            }

            // 3. Facturado previo en fact_ventas
            $prevRow = DB::connection('supervisor')->table('fact_ventas')
                ->where('fecha_norm', '>=', $fInicioMes)
                ->where('fecha_norm', '<', $fechaNorm)
                ->where(function ($q): void {
                    $q->whereNull('revertida')
                      ->orWhere(function ($sub): void {
                          $sub->where('revertida', '!=', 'Si')
                              ->where('revertida', '!=', 'SI');
                      });
                })
                ->where('monto_final', '>', 0)
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->selectRaw("SUM(COALESCE(monto_final, 0.0)) as venta_prev")
                ->first();

            if ($prevRow && $prevRow->venta_prev) {
                $factPrevio = (float) $prevRow->venta_prev;
            }

            // 4. Días hábiles
            $hoy = Carbon::today();
            $esMesCerrado = ($year < $hoy->year) || ($year === $hoy->year && $month < $hoy->month);
            $diasTotales = 25;

            $rowCal = DB::connection('supervisor')->table('dim_calendario_mensual')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($rowCal && isset($rowCal->dias_habiles)) {
                $diasTotales = (int) $rowCal->dias_habiles;
            }

            $diasOperados = 1;
            if (!$esMesCerrado) {
                $diasOperados = DB::connection('supervisor')->table('fact_preventas')
                    ->where('fecha_norm', '>=', $fInicioMes)
                    ->where('fecha_norm', '<=', $fechaNorm)
                    ->where('monto_final', '>', 0)
                    ->distinct()
                    ->count('fecha_norm');
            }

            $diasRestantes = $esMesCerrado ? 0 : max(0, $diasTotales - $diasOperados);
            $divisorPreventa = max(1, $diasRestantes - 2);

            // 5. Preventas del día detalladas
            $preventasDetalle = DB::connection('supervisor')->table('fact_preventas')
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
                ->orderBy('nro_preventa', 'asc')
                ->orderBy('cliente', 'asc')
                ->get();

            $brecha = max(0.0, $metaMes - $factPrevio);
            $metaDia = ($metaMes > 0 && !$esMesCerrado) ? round($brecha / $divisorPreventa, 2) : 0.0;

            $clientesDict = [];
            $totalPreventaHoy = 0.0;

            foreach ($preventasDetalle as $it) {
                $cid = (int) ($it->cliente_id ?: 0);
                $cNombre = (string) $it->cliente;
                $cKey = $cid > 0 ? (string) $cid : mb_strtolower(trim($cNombre));
                $nro = (string) $it->nro_preventa;
                $montoItem = (float) $it->monto_final;
                $cantItem = (int) ($it->cantidad ?: 1);
                $totalPreventaHoy += $montoItem;

                if (!isset($clientesDict[$cKey])) {
                    $clientesDict[$cKey] = [
                        'cliente_id' => $cid,
                        'cliente' => $cNombre,
                        'total_bs' => 0.0,
                        'items_count' => 0,
                        'nros_preventa_set' => [],
                        'preventas_map' => [],
                    ];
                }

                $clientesDict[$cKey]['total_bs'] = round($clientesDict[$cKey]['total_bs'] + $montoItem, 2);
                $clientesDict[$cKey]['items_count'] += $cantItem;
                $clientesDict[$cKey]['nros_preventa_set'][$nro] = true;

                if (!isset($clientesDict[$cKey]['preventas_map'][$nro])) {
                    $clientesDict[$cKey]['preventas_map'][$nro] = [
                        'nro_preventa' => $nro,
                        'total_bs' => 0.0,
                        'items_count' => 0,
                        'productos' => [],
                    ];
                }

                $clientesDict[$cKey]['preventas_map'][$nro]['total_bs'] = round(
                    $clientesDict[$cKey]['preventas_map'][$nro]['total_bs'] + $montoItem,
                    2
                );
                $clientesDict[$cKey]['preventas_map'][$nro]['items_count'] += $cantItem;
                $clientesDict[$cKey]['preventas_map'][$nro]['productos'][] = [
                    'producto' => $it->producto,
                    'cantidad' => $cantItem,
                    'monto' => $montoItem,
                ];
            }

            $clientesLista = [];
            foreach ($clientesDict as $cObj) {
                $cObj['nro_preventas'] = implode(', ', array_keys($cObj['nros_preventa_set']));
                unset($cObj['nros_preventa_set']);

                $cObj['preventas'] = array_values($cObj['preventas_map']);
                unset($cObj['preventas_map']);

                $clientesLista[] = $cObj;
            }

            usort($clientesLista, fn ($a, $b) => $b['total_bs'] <=> $a['total_bs']);

            $totalPreventaHoy = round($totalPreventaHoy, 2);
            $diferenciaDia = round($totalPreventaHoy - $metaDia, 2);
            $porcentaje = $metaDia > 0
                ? round(($totalPreventaHoy / $metaDia) * 100.0, 1)
                : ($totalPreventaHoy > 0 ? 100.0 : 0.0);

            $cumple = $metaDia > 0 ? ($totalPreventaHoy >= $metaDia) : ($totalPreventaHoy > 0);

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'mes' => $mesNorm,
                'fecha' => $fechaNorm,
                'balance' => [
                    'meta_mes' => $metaMes,
                    'facturado_previo' => $factPrevio,
                    'brecha_acumulada' => $brecha,
                    'dias_restantes' => $diasRestantes,
                    'divisor_preventa' => $divisorPreventa,
                    'meta_dia' => $metaDia,
                    'preventa_hoy' => $totalPreventaHoy,
                    'diferencia_dia' => $diferenciaDia,
                    'porcentaje' => $porcentaje,
                    'cumple' => $cumple,
                ],
                'total_clientes' => count($clientesLista),
                'clientes' => $clientesLista,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleObjetivoRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'mes' => $mesNorm,
                'fecha' => $fechaNorm,
                'balance' => [
                    'meta_mes' => 0.0,
                    'facturado_previo' => 0.0,
                    'brecha_acumulada' => 0.0,
                    'dias_restantes' => 0,
                    'divisor_preventa' => 1,
                    'meta_dia' => 0.0,
                    'preventa_hoy' => 0.0,
                    'diferencia_dia' => 0.0,
                    'porcentaje' => 0.0,
                    'cumple' => false,
                ],
                'total_clientes' => 0,
                'clientes' => [],
            ];
        }
    }
}
