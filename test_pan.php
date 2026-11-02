<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$domingoRows = Illuminate\Support\Facades\DB::connection('supervisor')
    ->table('pan_ruteo')
    ->where('estado', 'Activo')
    ->where('dia_norm', 'domingo')
    ->select('ruta', Illuminate\Support\Facades\DB::raw('count(*) as c'))
    ->groupBy('ruta')
    ->get();

echo "Domingo breakdown by ruta:\n" . json_encode($domingoRows, JSON_PRETTY_PRINT) . PHP_EOL;

