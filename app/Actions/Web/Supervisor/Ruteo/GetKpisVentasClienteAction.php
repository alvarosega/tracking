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
        try {
            $maxFecha = $conn->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            if ($maxFecha) {
                $now = Carbon::parse($maxFecha);
            }
        } catch (\Throwable $e) {
            // Silenciar si fact_ventas falla temporalmente
        }

        $baseDate = $now->copy()->startOfMonth();
        $inicioHistorico = $baseDate->copy()->subMonths(10)->toDateString();
        $finMesActual = $now->copy()->endOfMonth()->toDateString();

        $ventas = $conn->table('fact_ventas')
            ->where('cliente_id', (int) $clienteId)
            ->where('cliente_id', '>', 0)
            ->whereBetween('fecha_norm', [$inicioHistorico, $finMesActual])
            ->where('monto_final', '>', 0)
            ->where(function ($q) {
                $q->whereNull('revertida')
                  ->orWhereNotIn(DB::raw("LOWER(TRIM(revertida))"), ['si', 'sí', 'true', '1', 'anulada', 'revertida', 'yes', 'verdadero']);
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

        $mesesEspanol = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        $mesesCorto = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
        ];

        $periodos = [];
        $periodosLista = [];
        for ($i = 0; $i <= 10; $i++) {
            $dt = $baseDate->copy()->subMonths($i);
            $pKey = $dt->format('Y-m');
            $mNum = $dt->format('m');
            $v = $ventas->get($pKey);

            $item = [
                'periodo' => $pKey,
                'nombre' => ($mesesEspanol[$mNum] ?? 'Mes') . ' ' . $dt->format('Y'),
                'corto' => ($mesesCorto[$mNum] ?? '') . ' ' . $dt->format('y'),
                'monto_acumulado' => round((float) ($v->monto_acumulado ?? 0.0), 2),
                'frecuencia_compra' => (int) ($v->frecuencia_compra ?? 0),
                'dias_compra' => (int) ($v->dias_compra ?? 0),
                'es_actual' => ($i === 0),
                'offset' => $i,
            ];

            $periodos[$pKey] = $item;
            $periodosLista[] = $item;
        }

        $montoActual = $periodosLista[0]['monto_acumulado'];
        $montoAnterior = $periodosLista[1]['monto_acumulado'] ?? 0.0;
        $frecActual = $periodosLista[0]['frecuencia_compra'];
        $frecAnterior = $periodosLista[1]['frecuencia_compra'] ?? 0;

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
            'periodos' => $periodos,
            'periodos_lista' => $periodosLista,
            'mes_actual' => $periodosLista[0],
            'mes_anterior' => $periodosLista[1] ?? null,
            'mes_anterior_2' => $periodosLista[2] ?? null,
            'variacion' => [
                'monto_pct' => $varMonto,
                'frecuencia_pct' => $varFrec,
            ],
        ];
    }
}

