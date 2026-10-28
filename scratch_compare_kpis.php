<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Actions\Web\Supervisor\Avance\GetAvanceCumplimientoDataAction;

$action = app(GetAvanceCumplimientoDataAction::class);
$conn = DB::connection('supervisor');

$fIni = '2026-10-01';
$fFin = '2026-10-27';

echo "=== COMPARATIVA DETALLADA EN AVANCE > CUMPLIMIENTO ({$fIni} al {$fFin}) ===\n\n";

// 1. Direct query in fact_ventas for all sales (revertida != 'Si' and monto_final > 0)
$ventas = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', $fIni)
    ->where('fecha_norm', '<=', $fFin)
    ->where('monto_final', '>', 0)
    ->where(function ($q) {
        $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
    })
    ->select([
        'id', 'cliente_id', 'ruta', 'codigo', 'producto', 'jerarquia3', 'categoria', 'monto_final'
    ])
    ->get();

echo "Total registros válidos en fact_ventas: " . count($ventas) . "\n";
$montoTotalDb = $ventas->sum('monto_final');
echo "Monto total ventas válidas en fact_ventas: Bs " . number_format((float)$montoTotalDb, 2) . "\n\n";

// Calculate theoretical for each KPI directly from fact_ventas
$kpis = ['vol_general', 'cob_general', 'vol_seda', 'cob_seda', 'vol_clinical', 'cob_clinical'];
$kpiDirecto = [];

foreach ($kpis as $kId) {
    $vol = 0.0;
    $clientes = [];
    foreach ($ventas as $v) {
        if ($action->aplicaTransaccion($kId, (string)$v->producto, (string)$v->jerarquia3, (string)$v->codigo, (string)$v->categoria)) {
            $vol += (float)$v->monto_final;
            if ($v->cliente_id > 0) {
                $clientes[(int)$v->cliente_id] = true;
            }
        }
    }
    $kpiDirecto[$kId] = [
        'volumen' => $vol,
        'cobertura' => count($clientes),
    ];
}

// 2. Output from GetAvanceCumplimientoDataAction with NO filters (default)
$resumenTodos = $action->execute(vista: 'resumen', fechaInicio: $fIni, fechaCorte: $fFin, canales: [], rutas: []);
$resumenTdb = $action->execute(vista: 'resumen', fechaInicio: $fIni, fechaCorte: $fFin, canales: ['TDB'], rutas: []);

echo "COMPARATIVA POR KPI:\n";
echo str_pad("KPI", 15) . " | " . str_pad("BD Teórico", 20) . " | " . str_pad("Action (TODOS)", 20) . " | " . str_pad("Action (TDB)", 20) . "\n";
echo str_repeat("-", 80) . "\n";

foreach ($kpis as $kId) {
    $esVol = str_starts_with($kId, 'vol_');
    $teorico = $esVol ? $kpiDirecto[$kId]['volumen'] : $kpiDirecto[$kId]['cobertura'];
    $valTodos = $resumenTodos['totales_columnas'][$kId]['logrado'] ?? 0;
    $valTdb = $resumenTdb['totales_columnas'][$kId]['logrado'] ?? 0;

    $strTeorico = $esVol ? "Bs " . number_format((float)$teorico, 2) : number_format((float)$teorico) . " cli";
    $strTodos = $esVol ? "Bs " . number_format((float)$valTodos, 2) : number_format((float)$valTodos) . " cli";
    $strTdb = $esVol ? "Bs " . number_format((float)$valTdb, 2) : number_format((float)$valTdb) . " cli";

    echo str_pad($kId, 15) . " | " . str_pad($strTeorico, 20) . " | " . str_pad($strTodos, 20) . " | " . str_pad($strTdb, 20) . "\n";
}

