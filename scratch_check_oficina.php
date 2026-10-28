<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$oficina = $conn->table('fact_ventas')
    ->where('ruta', 'VENTA OFICINA')
    ->select(['ruta', 'canal', 'tipo_negocio', 'vendedor', 'monto_final'])
    ->limit(5)
    ->get();

print_r($oficina->toArray());

