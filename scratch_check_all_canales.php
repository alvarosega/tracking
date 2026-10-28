<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Actions\Web\Supervisor\Avance\GetAvanceCumplimientoDataAction;

$action = app(GetAvanceCumplimientoDataAction::class);

$allCanales = ['MAY', 'MZO', 'PROV', 'TDB', 'PRT', 'SC'];

$resAllCanales = $action->execute(
    vista: 'resumen',
    fechaInicio: '2026-10-01',
    fechaCorte: '2026-10-27',
    canales: $allCanales,
    rutas: []
);

$logradoAll = $resAllCanales['totales_columnas']['vol_general']['logrado'] ?? 0;
echo "Action with ALL 6 canales selected: Bs " . number_format((float)$logradoAll, 2) . "\n";

$resSinFiltro = $action->execute(
    vista: 'resumen',
    fechaInicio: '2026-10-01',
    fechaCorte: '2026-10-27',
    canales: [], // or ['TODOS']
    rutas: []
);

$logradoSinFiltro = $resSinFiltro['totales_columnas']['vol_general']['logrado'] ?? 0;
echo "Action with NO canal filter (canales=[]): Bs " . number_format((float)$logradoSinFiltro, 2) . "\n";
echo "Diferencia: Bs " . number_format((float)($logradoSinFiltro - $logradoAll), 2) . "\n";

