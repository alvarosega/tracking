<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$dim = $conn->table('dim_rutas')->get();
echo "Total dim_rutas: " . count($dim) . "\n";
$byCanal = [];
foreach ($dim as $d) {
    $c = strtoupper(trim($d->canal ?? 'SIN CANAL'));
    $byCanal[$c] = ($byCanal[$c] ?? 0) + 1;
}
print_r($byCanal);

echo "\nVentas por canal en dim_rutas (2026-10):\n";
$ventas = $conn->table('fact_ventas')
    ->where('fecha_norm', '>=', '2026-10-01')
    ->where('fecha_norm', '<=', '2026-10-27')
    ->where('monto_final', '>', 0)
    ->where(function ($q) {
        $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
    })
    ->select([DB::raw("TRIM(UPPER(ruta)) as ruta_clean"), DB::raw("SUM(monto_final) as total_monto")])
    ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
    ->get();

$dimMap = [];
foreach ($dim as $d) {
    $dimMap[strtoupper(trim($d->ruta))] = strtoupper(trim($d->canal ?? 'SIN CANAL'));
}

$montoPorCanal = [];
foreach ($ventas as $v) {
    $canal = $dimMap[$v->ruta_clean] ?? 'FUERA_DIM';
    $montoPorCanal[$canal] = ($montoPorCanal[$canal] ?? 0.0) + (float)$v->total_monto;
}

foreach ($montoPorCanal as $canal => $monto) {
    echo "Canal [{$canal}]: Bs " . number_format($monto, 2) . "\n";
}

