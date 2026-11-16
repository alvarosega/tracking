<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Illuminate\Support\Facades\DB;

class GetAvanceHistoricoPreventasDetalleRutaAction
{
    /**
     * Devuelve el desglose detallado de clientes que compraron en la ruta para el rango seleccionado,
     * clasificados en efectividad válida (>= 70 Bs) y bajo umbral (< 70 Bs).
     *
     * @param string $ruta
     * @param string $fechaInicio
     * @param string $fechaCorte
     * @return array<string, mixed>
     */
    public function execute(string $ruta, string $fechaInicio, string $fechaCorte): array
    {
        $rutaClean = strtoupper(trim($ruta));
        $vendedor = 'No Asignado';

        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && !empty($dimInfo->vendedor)) {
                $vendedor = trim((string) $dimInfo->vendedor);
            }
        } catch (\Throwable) {
            // Fallback
        }

        $vendedorNorm = $this->normalizarVendedor($vendedor);
        $vendedorValido = ($vendedorNorm !== '' && !in_array($vendedorNorm, ['sin vendedor', 'sin asignar', 'no asignado', 'prueba'], true));

        $filasRaw = DB::connection('supervisor')->table('fact_preventas')
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '>', 0)
            ->select([
                'fecha_norm',
                'cliente_id',
                DB::raw("COALESCE(TRIM(cliente), CONCAT('Cliente #', cliente_id)) AS cliente_nombre"),
                'vendedor',
                'ruta',
                'monto_final',
            ])
            ->get();

        if ($vendedorValido) {
            $filas = $filasRaw->filter(fn ($f) => $this->normalizarVendedor((string) $f->vendedor) === $vendedorNorm);
        } else {
            $filas = $filasRaw->filter(function ($f) use ($rutaClean) {
                $rMatch = strtoupper(trim((string) $f->ruta)) === $rutaClean;
                $vP = $this->normalizarVendedor((string) $f->vendedor);
                $sinV = ($vP === '' || in_array($vP, ['sin vendedor', 'sin asignar', 'no asignado', 'prueba'], true));
                return $rMatch && $sinV;
            });
        }

        // Agrupar por fecha_norm y cliente_id
        $clientesPorDia = [];
        foreach ($filas as $f) {
            $fNorm = (string) $f->fecha_norm;
            $cid = (int) $f->cliente_id;
            $nom = (string) $f->cliente_nombre;
            $monto = (float) $f->monto_final;
            $key = "{$fNorm}_{$cid}";

            if (!isset($clientesPorDia[$key])) {
                $clientesPorDia[$key] = [
                    'fecha_norm' => $fNorm,
                    'cliente_id' => $cid,
                    'cliente_nombre' => $nom,
                    'total_dia' => 0.0,
                ];
            }
            $clientesPorDia[$key]['total_dia'] += $monto;
        }

        $okLista = [];
        $bajoLista = [];

        foreach ($clientesPorDia as $itemData) {
            $totalBs = round((float) $itemData['total_dia'], 2);
            $cumpleUmbral = ($totalBs >= 70.0);

            $item = [
                'fecha' => $itemData['fecha_norm'],
                'cliente_id' => $itemData['cliente_id'],
                'cliente_nombre' => $itemData['cliente_nombre'],
                'total_bs' => $totalBs,
                'brecha_70' => round(max(0.0, 70.0 - $totalBs), 2),
            ];

            if ($cumpleUmbral) {
                $okLista[] = $item;
            } else {
                $bajoLista[] = $item;
            }
        }

        usort($okLista, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']) ?: ($b['total_bs'] <=> $a['total_bs']));
        usort($bajoLista, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']) ?: ($a['total_bs'] <=> $b['total_bs']));

        return [
            'ruta' => $ruta,
            'vendedor' => $vendedor,
            'total_efectivos' => count($okLista) + count($bajoLista),
            'total_ok' => count($okLista),
            'total_bajo' => count($bajoLista),
            'clientes_ok' => $okLista,
            'clientes_bajo' => $bajoLista,
        ];
    }

    /**
     * Normaliza el nombre del vendedor para coincidencia exacta entre dim_rutas y fact_preventas.
     */
    private function normalizarVendedor(?string $vendedor): string
    {
        if (empty($vendedor)) {
            return '';
        }
        $v = trim(preg_replace('/\s+/', ' ', mb_strtolower($vendedor)));
        $v = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $v);
        $aliases = [
            'juan daniel conde quispe' => 'daniel conde quispe',
        ];
        return $aliases[$v] ?? $v;
    }
}

