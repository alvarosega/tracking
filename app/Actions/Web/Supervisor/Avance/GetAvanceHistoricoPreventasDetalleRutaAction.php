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
        $sql = "
        SELECT 
            p.fecha_norm,
            p.cliente_id,
            COALESCE(p.cliente, CONCAT('Cliente #', p.cliente_id)) AS cliente_nombre,
            ROUND(SUM(p.monto_final), 2) AS total_dia,
            CASE WHEN SUM(p.monto_final) >= 70.0 THEN 1 ELSE 0 END AS cumple_umbral
        FROM fact_preventas p
        WHERE TRIM(UPPER(p.ruta)) = TRIM(UPPER(?))
          AND p.fecha_norm >= ? AND p.fecha_norm <= ?
          AND p.monto_final > 0
        GROUP BY p.fecha_norm, p.cliente_id, p.cliente
        ORDER BY p.fecha_norm DESC, total_dia DESC
        ";

        $registros = DB::connection('supervisor')->select($sql, [
            trim($ruta),
            $fechaInicio,
            $fechaCorte,
        ]);

        $okLista = [];
        $bajoLista = [];

        foreach ($registros as $r) {
            $totalBs = (float) $r->total_dia;
            $item = [
                'fecha' => (string) $r->fecha_norm,
                'cliente_id' => (int) $r->cliente_id,
                'cliente_nombre' => (string) $r->cliente_nombre,
                'total_bs' => $totalBs,
                'brecha_70' => round(max(0.0, 70.0 - $totalBs), 2),
            ];

            if ((int) $r->cumple_umbral === 1) {
                $okLista[] = $item;
            } else {
                $bajoLista[] = $item;
            }
        }

        return [
            'ruta' => $ruta,
            'total_efectivos' => count($okLista) + count($bajoLista),
            'total_ok' => count($okLista),
            'total_bajo' => count($bajoLista),
            'clientes_ok' => $okLista,
            'clientes_bajo' => $bajoLista,
        ];
    }
}

