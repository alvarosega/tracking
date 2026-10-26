<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Illuminate\Support\Facades\DB;

class GetAvanceParetoFrecuenciaAction
{
    /**
     * Devuelve el historial de facturas y tickets efectivos emitidos al cliente en el período.
     */
    public function execute(int $clienteId, string $fechaInicio, string $fechaCorte): array
    {
        $rows = DB::connection('supervisor')->table('fact_ventas')
            ->where('cliente_id', $clienteId)
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
            })
            ->where('monto_final', '>', 0)
            ->groupByRaw("fecha_norm, nro_factura")
            ->orderBy('fecha_norm')
            ->selectRaw("
                CAST(fecha_norm AS CHAR) AS fecha,
                COALESCE(NULLIF(TRIM(nro_factura), ''), '-') AS nro_factura,
                COUNT(DISTINCT producto_id) AS skus_dia,
                SUM(cantidad) AS unidades_dia,
                ROUND(SUM(monto_final), 2) AS total_bs
            ")->get();

        return $rows->map(fn ($r) => [
            'fecha' => (string) $r->fecha,
            'nro_factura' => (string) $r->nro_factura,
            'skus_dia' => (int) ($r->skus_dia ?? 0),
            'unidades_dia' => (int) ($r->unidades_dia ?? 0),
            'total_bs' => (float) ($r->total_bs ?? 0.0),
        ])->toArray();
    }
}

