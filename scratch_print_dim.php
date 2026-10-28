<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = DB::connection('supervisor');

$dim = $conn->table('dim_rutas')->orderBy('ruta')->get();
foreach ($dim as $d) {
    echo "Ruta: " . str_pad($d->ruta, 15) . " | Canal: " . str_pad($d->canal ?? '', 8) . " | Vendedor: " . ($d->vendedor ?? '') . "\n";
}

