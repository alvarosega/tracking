<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleCompraMinimaAction
{
    private const MONTO_MINIMO_COMPRA = 70.0;

    /**
     * Obtiene el detalle de compras mínimas de una ruta (cumplen >= 70 Bs vs bajo mínimo < 70 Bs).
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

            if ($dimInfo && $dimInfo->vendedor) {
                $vendedor = trim($dimInfo->vendedor);
            }

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
                        'total_acumulado_bs' => 0.0,
                        'notas_preventa' => [],
                        'total_items' => 0,
                        'detalles' => [],
                    ];
                }

                $clientesDict[$cid]['total_acumulado_bs'] = round(
                    $clientesDict[$cid]['total_acumulado_bs'] + $monto,
                    2
                );
                $clientesDict[$cid]['notas_preventa'][$f->nro_preventa] = true;
                $clientesDict[$cid]['total_items'] += $cant;
                $clientesDict[$cid]['detalles'][] = [
                    'nro_preventa' => $f->nro_preventa,
                    'producto' => $f->producto,
                    'categoria' => $f->categoria,
                    'cantidad' => $cant,
                    'monto' => $monto,
                ];
            }

            $bajoMinimo = [];
            $cumplenMinimo = [];

            foreach ($clientesDict as $c) {
                $c['notas_preventa'] = implode(', ', array_keys($c['notas_preventa']));
                $c['brecha_70'] = round(max(0.0, self::MONTO_MINIMO_COMPRA - $c['total_acumulado_bs']), 2);
                $c['brecha_80'] = $c['brecha_70'];

                if ($c['total_acumulado_bs'] < self::MONTO_MINIMO_COMPRA) {
                    $bajoMinimo[] = $c;
                } else {
                    $cumplenMinimo[] = $c;
                }
            }

            usort($bajoMinimo, fn ($a, $b) => $a['total_acumulado_bs'] <=> $b['total_acumulado_bs']);
            usort($cumplenMinimo, fn ($a, $b) => $b['total_acumulado_bs'] <=> $a['total_acumulado_bs']);

            $cumple = count($bajoMinimo) === 0;

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'totales' => [
                    'total_compradores' => count($clientesDict),
                    'cumplen_minimo' => count($cumplenMinimo),
                    'bajo_minimo' => count($bajoMinimo),
                    'cumple' => $cumple,
                    'penalizacion' => $cumple ? 0.0 : -5.0,
                ],
                'clientes_bajo_minimo' => $bajoMinimo,
                'clientes_cumplen' => $cumplenMinimo,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleCompraMinimaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'totales' => [
                    'total_compradores' => 0,
                    'cumplen_minimo' => 0,
                    'bajo_minimo' => 0,
                    'cumple' => false,
                    'penalizacion' => 0.0,
                ],
                'clientes_bajo_minimo' => [],
                'clientes_cumplen' => [],
            ];
        }
    }
}

