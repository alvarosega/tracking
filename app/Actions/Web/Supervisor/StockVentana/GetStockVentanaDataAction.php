<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\StockVentana;

use App\Models\AppModule;
use App\Models\AppModuleRoute;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetStockVentanaDataAction
{
    /**
     * Retorna la matriz de rutas con su ventana histórica configurada para la toma de stock en PDV,
     * junto con los parámetros globales y estadísticas clave.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $normKey = static function (?string $r): string {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $r)));
        };

        // 1. Obtener configuración global del módulo inventario_cliente
        $modulo = AppModule::where('code', 'inventario_cliente')->first();
        $globalDias = (int) ($modulo?->config['dias_historial'] ?? 30);
        if ($globalDias < 1) {
            $globalDias = 30;
        }
        $moduloActivoGlobal = (bool) ($modulo?->is_active ?? true);

        // 2. Obtener catálogo maestro de rutas desde dim_rutas (supervisor)
        $metaPorRutaNorm = [];
        $nombresRutaMap = [];

        try {
            $dimRows = DB::connection('supervisor')
                ->table('dim_rutas')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->select(['ruta', 'canal', 'vendedor'])
                ->get();

            foreach ($dimRows as $row) {
                $rawRuta = trim((string) $row->ruta);
                $nKey = $normKey($rawRuta);
                if ($nKey === '') {
                    continue;
                }
                $metaPorRutaNorm[$nKey] = [
                    'ruta' => $rawRuta,
                    'canal' => trim((string) ($row->canal ?: 'TDB')),
                    'vendedor' => trim((string) ($row->vendedor ?: 'Sin asignar')),
                ];
                $nombresRutaMap[$nKey] = $rawRuta;
            }
        } catch (\Throwable $e) {
            Log::warning('GetStockVentanaDataAction dim_rutas error: ' . $e->getMessage());
        }

        // 3. Clientes activos y rutas presentes en pan_ruteo
        $clientesPorRuta = [];
        try {
            $panRows = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('estado', 'Activo')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->where('cliente_id', '>', 0)
                ->select(['cliente_id', 'ruta'])
                ->get();

            foreach ($panRows as $pr) {
                $rawRuta = trim((string) $pr->ruta);
                $nKey = $normKey($rawRuta);
                if ($nKey === '') {
                    continue;
                }
                if (!isset($nombresRutaMap[$nKey])) {
                    $nombresRutaMap[$nKey] = $rawRuta;
                }
                $cid = (int) $pr->cliente_id;
                $clientesPorRuta[$nKey][$cid] = true;
            }
        } catch (\Throwable $e) {
            Log::warning('GetStockVentanaDataAction pan_ruteo error: ' . $e->getMessage());
        }

        // 4. Usuarios vendedores desde users (mysql)
        $vendedoresUsers = [];
        try {
            $usersRows = DB::connection('mysql')
                ->table('users')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->where('is_active', true)
                ->select(['ruta', 'username'])
                ->get();

            foreach ($usersRows as $u) {
                $nKey = $normKey($u->ruta);
                if ($nKey !== '' && !isset($vendedoresUsers[$nKey])) {
                    $vendedoresUsers[$nKey] = trim((string) $u->username);
                }
            }
        } catch (\Throwable) {
            // Silencioso
        }

        // 5. Overrides por ruta en app_module_routes (mysql)
        $overridesPorRutaNorm = [];
        try {
            $overrides = AppModuleRoute::where('module_code', 'inventario_cliente')->get();
            foreach ($overrides as $ov) {
                $nKey = $normKey($ov->ruta);
                if ($nKey !== '') {
                    $overridesPorRutaNorm[$nKey] = $ov;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GetStockVentanaDataAction app_module_routes error: ' . $e->getMessage());
        }

        // 6. Unificar y construir matriz de rutas
        $normKeysUnicas = array_keys($nombresRutaMap);
        sort($normKeysUnicas, SORT_NATURAL | SORT_FLAG_CASE);

        $nowBolivia = Carbon::now('America/La_Paz');
        $canalesUnicos = [];
        $rutasResultado = [];
        $kpiPersonalizadas = 0;
        $kpiGlobales = 0;

        foreach ($normKeysUnicas as $nKey) {
            $displayRuta = $nombresRutaMap[$nKey];
            $meta = $metaPorRutaNorm[$nKey] ?? null;
            $canal = $meta['canal'] ?? 'TDB';
            $vendedor = $meta['vendedor'] ?? ($vendedoresUsers[$nKey] ?? 'Sin asignar');
            $canalesUnicos[$canal] = true;

            $totalClientes = count($clientesPorRuta[$nKey] ?? []);

            $ov = $overridesPorRutaNorm[$nKey] ?? null;
            $hasOverrideConfig = false;
            $diasHistorialRuta = $globalDias;
            $isModuleActive = $moduloActivoGlobal;

            if ($ov) {
                if (isset($ov->config['dias_historial']) && is_numeric($ov->config['dias_historial'])) {
                    $diasHistorialRuta = max(1, (int) $ov->config['dias_historial']);
                    $hasOverrideConfig = true;
                }
                if ($ov->is_active !== null) {
                    $isModuleActive = (bool) $ov->is_active;
                }
            }

            if ($hasOverrideConfig) {
                $kpiPersonalizadas++;
            } else {
                $kpiGlobales++;
            }

            $fechaDesdeRuta = $nowBolivia->copy()->subDays($diasHistorialRuta);

            $rutasResultado[] = [
                'ruta' => $displayRuta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'total_clientes' => $totalClientes,
                'dias_historial' => $diasHistorialRuta,
                'es_personalizado' => $hasOverrideConfig,
                'modulo_activo' => $isModuleActive,
                'fecha_desde' => $fechaDesdeRuta->format('d/m/Y'),
                'fecha_desde_iso' => $fechaDesdeRuta->format('Y-m-d'),
                'updated_at' => $ov?->updated_at ? $ov->updated_at->format('d/m/Y H:i') : null,
            ];
        }

        $canalesLista = array_keys($canalesUnicos);
        if (empty($canalesLista)) {
            $canalesLista = ['TDB', 'MAY', 'MZO', 'PROV'];
        }

        $fechaGlobalDesde = $nowBolivia->copy()->subDays($globalDias);

        return [
            'global_dias' => $globalDias,
            'modulo_activo_global' => $moduloActivoGlobal,
            'fecha_global_desde' => $fechaGlobalDesde->format('d/m/Y'),
            'fecha_global_desde_iso' => $fechaGlobalDesde->format('Y-m-d'),
            'canales' => $canalesLista,
            'rutas' => $rutasResultado,
            'kpis' => [
                'total_rutas' => count($rutasResultado),
                'rutas_personalizadas' => $kpiPersonalizadas,
                'rutas_globales' => $kpiGlobales,
                'dias_global' => $globalDias,
                'fecha_global_desde' => $fechaGlobalDesde->format('d/m/Y'),
            ],
        ];
    }
}

