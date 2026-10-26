<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceClienteDetalleFechasAction
{
    private const KPIS_NOMBRES = [
        'vol_general' => '1. Volumen General',
        'cob_general' => '2. Cobertura 100%',
        'vol_seda' => '3. Vol. Toque de Seda',
        'cob_seda' => '4. Cob. Toque de Seda',
        'vol_clinical' => '5. Vol. Clinical',
        'cob_clinical' => '6. Cob. Clinical',
    ];

    public function __construct(
        private readonly GetAvanceCumplimientoDataAction $cumplimientoAction
    ) {}

    /**
     * Obtiene el desglose cronológico de facturas y SKUs para un cliente específico en una ruta.
     *
     * @param int $clienteId
     * @param string $ruta
     * @param string|null $fechaInicio
     * @param string|null $fechaCorte
     * @param string $kpiClave
     * @return array<string, mixed>
     */
    public function execute(
        int $clienteId,
        string $ruta,
        ?string $fechaInicio = null,
        ?string $fechaCorte = null,
        string $kpiClave = 'vol_general'
    ): array {
        $rutaClean = strtoupper(trim($ruta));

        if (!$fechaCorte) {
            $maxFecha = DB::connection('supervisor')->table('fact_ventas')->where('monto_final', '>', 0)->max('fecha_norm');
            $fechaCorte = $maxFecha ?: date('Y-m-d');
        }
        $carbonCorte = Carbon::parse($fechaCorte);
        if (!$fechaInicio) {
            $fechaInicio = $carbonCorte->copy()->startOfMonth()->format('Y-m-d');
        }

        $filas = DB::connection('supervisor')->table('fact_ventas')
            ->where('cliente_id', $clienteId)
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->where('fecha_norm', '>=', $fechaInicio)
            ->where('fecha_norm', '<=', $fechaCorte)
            ->where('monto_final', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
            })
            ->select([
                DB::raw("CAST(fecha_norm AS CHAR) as fecha"),
                DB::raw("COALESCE(NULLIF(TRIM(nro_factura), ''), 'S/F') as nro_factura"),
                DB::raw("TRIM(codigo) as codigo"),
                DB::raw("COALESCE(producto, 'Sin descripción') as producto"),
                DB::raw("COALESCE(jerarquia3, '') as jerarquia3"),
                DB::raw("UPPER(TRIM(COALESCE(categoria, ''))) as categoria"),
                DB::raw("COALESCE(cantidad, 0) as cantidad"),
                DB::raw("COALESCE(monto_final, 0.0) as monto_final"),
                DB::raw("COALESCE(TRIM(vendedor), 'Sin Asignar') as vendedor"),
            ])
            ->orderBy('fecha_norm', 'desc')
            ->orderBy('nro_factura', 'asc')
            ->orderBy('monto_final', 'desc')
            ->get();

        $diasMap = [];
        $totalMonto = 0.0;
        $totalUnidades = 0;

        foreach ($filas as $r) {
            $prod = (string) $r->producto;
            $j3 = (string) $r->jerarquia3;
            $cod = (string) $r->codigo;
            $cat = (string) $r->categoria;

            if (!$this->cumplimientoAction->aplicaTransaccion($kpiClave, $prod, $j3, $cod, $cat)) {
                continue;
            }

            $f = (string) $r->fecha;
            $monto = (float) $r->monto_final;
            $cant = (int) $r->cantidad;

            $totalMonto += $monto;
            $totalUnidades += $cant;

            if (!isset($diasMap[$f])) {
                $diasMap[$f] = [
                    'fecha' => $f,
                    'total_bs' => 0.0,
                    'total_cantidad' => 0,
                    'vendedores' => [],
                    'facturas' => [],
                    'items' => [],
                ];
            }

            $diasMap[$f]['total_bs'] += $monto;
            $diasMap[$f]['total_cantidad'] += $cant;
            if ($r->vendedor && $r->vendedor !== 'Sin Asignar') {
                $diasMap[$f]['vendedores'][$r->vendedor] = true;
            }
            if ($r->nro_factura && $r->nro_factura !== 'S/F') {
                $diasMap[$f]['facturas'][$r->nro_factura] = true;
            }

            $diasMap[$f]['items'][] = [
                'nro_factura' => $r->nro_factura,
                'codigo' => $r->codigo,
                'producto' => $r->producto,
                'jerarquia3' => $r->jerarquia3,
                'cantidad' => $cant,
                'monto_bs' => round($monto, 2),
            ];
        }

        $fechasResultado = [];
        krsort($diasMap);
        foreach ($diasMap as $f => $d) {
            $fechasResultado[] = [
                'fecha' => $f,
                'total_bs' => round($d['total_bs'], 2),
                'monto_bs' => round($d['total_bs'], 2),
                'total_cantidad' => $d['total_cantidad'],
                'vendedor' => !empty($d['vendedores']) ? implode(', ', array_keys($d['vendedores'])) : 'Sin Asignar',
                'facturas' => !empty($d['facturas']) ? implode(', ', array_keys($d['facturas'])) : 'S/F',
                'items' => $d['items'],
            ];
        }

        return [
            'cliente_id' => $clienteId,
            'ruta' => $rutaClean,
            'kpi' => $kpiClave,
            'kpi_nombre' => self::KPIS_NOMBRES[$kpiClave] ?? $kpiClave,
            'total_monto_bs' => round($totalMonto, 2),
            'total_unidades' => $totalUnidades,
            'fechas' => $fechasResultado,
        ];
    }
}

