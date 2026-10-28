<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$missing = ['MAY 002', 'PROVINCIAS', 'PRV COP', 'TDB 012', 'TDB 9C', 'VENTA OFICINA'];

$samples = $conn->table('fact_ventas')
    ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $missing)
    ->select([
        DB::raw("TRIM(UPPER(ruta)) as r"),
        'canal',
        'tipo_negocio',
        'vendedor',
        DB::raw("COUNT(*) as c")
    ])
    ->groupBy('r', 'canal', 'tipo_negocio', 'vendedor')
    ->get();

foreach ($samples as $s) {
    echo "Ruta: " . str_pad($s->r, 15) . " | Canal: " . str_pad((string)$s->canal, 5) . " | TipoNegocio: " . str_pad($s->tipo_negocio ?? '', 12) . " | Vendedor: " . ($s->vendedor ?? '') . "\n";
}

