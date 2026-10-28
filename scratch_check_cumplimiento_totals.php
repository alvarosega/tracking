<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$fIni = '2026-10-01';
$fFin = '2026-10-27';

echo "Analysis of fact_ventas between {$fIni} and {$fFin}:\n";

// 1. Total absolute in fact_ventas
$totalAbs = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', $fIni)
    ->where('fecha_norm', '<=', $fFin)
    ->sum('monto_final');

echo "1. Total raw monto_final: Bs " . number_format((float)$totalAbs, 2) . "\n";

// 2. Revertida = 'Si'
$totalRevertida = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', $fIni)
    ->where('fecha_norm', '<=', $fFin)
    ->where('revertida', 'Si')
    ->sum('monto_final');

echo "2. Revertida = 'Si': Bs " . number_format((float)$totalRevertida, 2) . "\n";

// 3. Monto final <= 0
$totalCero = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', $fIni)
    ->where('fecha_norm', '<=', $fFin)
    ->where('monto_final', '<=', 0)
    ->count();

echo "3. Rows with monto_final <= 0: {$totalCero}\n";

// 4. Sales without ruta (NULL or empty)
$totalSinRuta = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', $fIni)
    ->where('fecha_norm', '<=', $fFin)
    ->where(function ($q) {
        $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
    })
    ->where('monto_final', '>', 0)
    ->where(function ($q) {
        $q->whereNull('ruta')->orWhere(DB::raw("TRIM(ruta)"), '');
    })
    ->sum('monto_final');

echo "4. Sales with NULL or empty ruta: Bs " . number_format((float)$totalSinRuta, 2) . "\n";

// 5. Sales with ruta NOT in dim_rutas
$rutasDim = $conn->table('dim_rutas')->pluck(DB::raw("TRIM(UPPER(ruta))"))->toArray();
$rutasDimFlip = array_flip($rutasDim);

$ventasConRuta = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', $fIni)
    ->where('fecha_norm', '<=', $fFin)
    ->where(function ($q) {
        $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
    })
    ->where('monto_final', '>', 0)
    ->whereNotNull('ruta')
    ->where(DB::raw("TRIM(ruta)"), '!=', '')
    ->select([DB::raw("TRIM(UPPER(ruta)) as ruta_clean"), DB::raw("SUM(monto_final) as total_monto")])
    ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
    ->get();

$montoEnDim = 0.0;
$montoFueraDim = 0.0;
$rutasFuera = [];

foreach ($ventasConRuta as $v) {
    if (isset($rutasDimFlip[$v->ruta_clean])) {
        $montoEnDim += (float)$v->total_monto;
    } else {
        $montoFueraDim += (float)$v->total_monto;
        $rutasFuera[$v->ruta_clean] = (float)$v->total_monto;
    }
}

echo "5. Sales in dim_rutas: Bs " . number_format($montoEnDim, 2) . "\n";
echo "6. Sales with ruta NOT in dim_rutas: Bs " . number_format($montoFueraDim, 2) . "\n";
if (!empty($rutasFuera)) {
    echo "   Rutas fuera de dim_rutas: " . print_r($rutasFuera, true) . "\n";
}

// 7. Now let's run GetAvanceCumplimientoDataAction::execute() and see what it returns!
$action = app(\App\Actions\Web\Supervisor\Avance\GetAvanceCumplimientoDataAction::class);
$resumen = $action->execute(vista: 'resumen', kpiClave: 'vol_general', fechaInicio: $fIni, fechaCorte: $fFin);
$totalesResumen = $resumen['totales_columnas']['vol_general'] ?? [];
echo "7. GetAvanceCumplimientoDataAction (vista=resumen, vol_general):\n";
echo "   Logrado: Bs " . number_format((float)($totalesResumen['logrado'] ?? 0), 2) . "\n";
echo "   Meta: Bs " . number_format((float)($totalesResumen['meta'] ?? 0), 2) . "\n";
echo "   Total rutas devueltas: " . count($resumen['rutas'] ?? []) . "\n";

$kpiData = $action->execute(vista: 'kpi', kpiClave: 'vol_general', fechaInicio: $fIni, fechaCorte: $fFin);
echo "8. GetAvanceCumplimientoDataAction (vista=kpi, vol_general):\n";
echo "   Logrado: Bs " . number_format((float)($kpiData['totales']['logrado'] ?? 0), 2) . "\n";
echo "   Total rutas devueltas: " . count($kpiData['rutas'] ?? []) . "\n";

