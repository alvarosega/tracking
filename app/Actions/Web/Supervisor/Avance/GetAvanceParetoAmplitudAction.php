<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Illuminate\Support\Facades\DB;

class GetAvanceParetoAmplitudAction
{
    /**
     * Devuelve el desglose de productos, jerarquías y unidades compradas por un cliente en el período.
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
            ->groupByRaw("j1, j2, j3, producto_id, TRIM(producto)")
            ->orderByDesc('total_bs')
            ->selectRaw("
                COALESCE(NULLIF(TRIM(jerarquia1), ''), 'SIN J1') AS j1,
                COALESCE(NULLIF(TRIM(jerarquia2), ''), 'SIN J2') AS j2,
                COALESCE(NULLIF(TRIM(jerarquia3), ''), 'SIN J3') AS j3,
                producto_id,
                TRIM(producto) AS producto,
                SUM(cantidad) AS unidades,
                ROUND(SUM(monto_final), 2) AS total_bs
            ")->get();

        return $rows->map(fn ($r) => [
            'j1' => (string) $r->j1,
            'j2' => (string) $r->j2,
            'j3' => (string) $r->j3,
            'producto_id' => (int) $r->producto_id,
            'producto' => (string) $r->producto,
            'unidades' => (int) ($r->unidades ?? 0),
            'total_bs' => (float) ($r->total_bs ?? 0.0),
        ])->toArray();
    }
}

