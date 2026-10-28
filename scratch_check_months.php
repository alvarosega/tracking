<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$res = $conn->select("
    SELECT SUBSTRING(fecha_norm, 1, 7) as mes_ym, COUNT(*) as filas, SUM(monto_final) as monto
    FROM fact_ventas
    WHERE fecha_norm IS NOT NULL
    GROUP BY SUBSTRING(fecha_norm, 1, 7)
    ORDER BY mes_ym DESC
    LIMIT 6
");

foreach ($res as $r) {
    echo "Mes: {$r->mes_ym} | Filas: {$r->filas} | Monto: Bs " . number_format((float)$r->monto, 2) . "\n";
}

