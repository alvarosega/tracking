<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$dimRutas = $conn->table('dim_rutas')->pluck(DB::raw("TRIM(UPPER(ruta))"))->toArray();
$dimRutasFlip = array_flip($dimRutas);

$allVentasRutas = $conn->table('fact_ventas')
    ->select([DB::raw("TRIM(UPPER(ruta)) as r"), DB::raw("COUNT(*) as c"), DB::raw("SUM(monto_final) as m")])
    ->groupBy(DB::raw("TRIM(UPPER(ruta))"))
    ->get();

echo "Rutas in fact_ventas that are NOT in dim_rutas:\n";
foreach ($allVentasRutas as $vr) {
    if (!isset($dimRutasFlip[$vr->r])) {
        echo "Ruta: " . str_pad($vr->r ?: '(NULL)', 20) . " | Filas: " . str_pad((string)$vr->c, 6) . " | Monto: Bs " . number_format((float)$vr->m, 2) . "\n";
    }
}

