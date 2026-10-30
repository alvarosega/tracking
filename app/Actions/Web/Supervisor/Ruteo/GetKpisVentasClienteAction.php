<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ruteo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetKpisVentasClienteAction
{
    /**
     * Obtiene monto acumulado y frecuencia de compra del mes actual y mes anterior para un cliente desde fact_ventas.
     *
     * @param int|string $clienteId
     * @return array<string, mixed>
     */
    public function execute(int|string $clienteId): array
    {
        $conn = DB::connection('supervisor');
        $now = Carbon::now();

        $mesActualPeriodo = $now->format('Y-m');
        $mesAnteriorPeriodo = $now->copy()->subMonth()->format('Y-m');

        $inicioMesAnterior = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $finMesActual = $now->copy()->endOfMonth()->toDateString();

        $ventas = $conn->table('fact_ventas')
            ->where('cliente_id', (int) $clienteId)
            ->whereBetween('fecha_norm', [$inicioMesAnterior, $finMesActual])
            ->where(function ($q) {
                $q->whereNull('revertida')
                  ->orWhereRaw("LOWER(TRIM(revertida)) != 'si'");
            })
            ->select([
                DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m') as periodo"),
                DB::raw("COALESCE(SUM(monto_final), 0) as monto_acumulado"),
                DB::raw("COUNT(DISTINCT COALESCE(NULLIF(nro_factura, ''), venta_id)) as frecuencia_compra"),
                DB::raw("COUNT(DISTINCT fecha_norm) as dias_compra"),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(fecha_norm, '%Y-%m')"))
            ->get()
            ->keyBy('periodo');

        $actual = $ventas->get($mesActualPeriodo);
        $anterior = $ventas->get($mesAnteriorPeriodo);

        $mesesEspanol = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        $nombreMesActual = ($mesesEspanol[$now->format('m')] ?? 'Mes Actual') . ' ' . $now->format('Y');
        $mesAntObj = $now->copy()->subMonth();
        $nombreMesAnterior = ($mesesEspanol[$mesAntObj->format('m')] ?? 'Mes Anterior') . ' ' . $mesAntObj->format('Y');

        $montoActual = round((float) ($actual->monto_acumulado ?? 0.0), 2);
        $montoAnterior = round((float) ($anterior->monto_acumulado ?? 0.0), 2);
        $frecActual = (int) ($actual->frecuencia_compra ?? 0);
        $frecAnterior = (int) ($anterior->frecuencia_compra ?? 0);
        $diasActual = (int) ($actual->dias_compra ?? 0);
        $diasAnterior = (int) ($anterior->dias_compra ?? 0);

        // Variaciones porcentuales
        $varMonto = null;
        if ($montoAnterior > 0) {
            $varMonto = round((($montoActual - $montoAnterior) / $montoAnterior) * 100, 1);
        }

        $varFrec = null;
        if ($frecAnterior > 0) {
            $varFrec = round((($frecActual - $frecAnterior) / $frecAnterior) * 100, 1);
        }

        return [
            'cliente_id' => (int) $clienteId,
            'mes_actual' => [
                'periodo' => $mesActualPeriodo,
                'nombre' => $nombreMesActual,
                'monto_acumulado' => $montoActual,
                'frecuencia_compra' => $frecActual,
                'dias_compra' => $diasActual,
            ],
            'mes_anterior' => [
                'periodo' => $mesAnteriorPeriodo,
                'nombre' => $nombreMesAnterior,
                'monto_acumulado' => $montoAnterior,
                'frecuencia_compra' => $frecAnterior,
                'dias_compra' => $diasAnterior,
            ],
            'variacion' => [
                'monto_pct' => $varMonto,
                'frecuencia_pct' => $varFrec,
            ],
        ];
    }
}

