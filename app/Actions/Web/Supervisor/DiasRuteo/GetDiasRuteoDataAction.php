<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\DiasRuteo;

use App\Models\RouteVisitDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDiasRuteoDataAction
{
    public const DIAS_SEMANA = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    /**
     * Retorna la matriz unificada de rutas comerciales con sus días de visita asignados
     * y el conteo de clientes activos por día desde pan_ruteo.
     *
     * @return array{
     *     rutas: array<int, array<string, mixed>>,
     *     canales: array<int, string>,
     *     dias_semana: array<int, array{id: int, name: string}>,
     *     kpis: array{total_rutas: int, rutas_personalizadas: int, rutas_estandar: int}
     * }
     */
    public function execute(): array
    {
        $normKey = static function (?string $r): string {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $r)));
        };

        // 1. Obtener catálogo maestro de rutas desde dim_rutas
        $metaPorRutaNorm = [];
        $nombresRutaMap = []; // normKey => display string preferido

        try {
            $dimRows = DB::connection('supervisor')
                ->table('dim_rutas')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->select('ruta', 'canal', 'vendedor')
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
            Log::error('GetDiasRuteoDataAction dim_rutas error: ' . $e->getMessage());
        }

        // 2. Obtener rutas y conteo de clientes por día en pan_ruteo
        $conteosPorRutaDia = []; // [normKey => [diaCanon => total_clientes]]

        try {
            $panRows = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where(function ($q) {
                    $q->whereNull('estado')
                      ->orWhere('estado', '')
                      ->orWhere('estado', 'Activo')
                      ->orWhereRaw("UPPER(TRIM(estado)) IN ('ACTIVO', 'ACTIVA', 'VIGENTE')")
                      ->orWhereRaw("UPPER(TRIM(estado)) NOT IN ('INACTIVO', 'INACTIVA', 'BAJA', 'ELIMINADO')");
                })
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->select([
                    'ruta',
                    DB::raw("COALESCE(NULLIF(TRIM(dia_norm), ''), TRIM(dia)) as dia_calculado"),
                    DB::raw('COUNT(DISTINCT COALESCE(NULLIF(cliente_id, 0), id)) as total_clientes'),
                ])
                ->groupBy('ruta', DB::raw("COALESCE(NULLIF(TRIM(dia_norm), ''), TRIM(dia))"))
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

                $diaCanon = $this->normalizarDiaNombre((string) $pr->dia_calculado);
                if ($diaCanon) {
                    $conteosPorRutaDia[$nKey][$diaCanon] = ($conteosPorRutaDia[$nKey][$diaCanon] ?? 0) + (int) $pr->total_clientes;
                }
            }
        } catch (\Throwable $e) {
            Log::error('GetDiasRuteoDataAction pan_ruteo error: ' . $e->getMessage());
        }

        // 3. Obtener usuarios vendedores en users (MySQL local/auth)
        $vendedoresUsers = [];
        try {
            $usersRows = DB::table('users')
                ->whereNotNull('ruta')
                ->whereRaw("TRIM(ruta) != ''")
                ->where('is_active', true)
                ->select('ruta', 'username')
                ->get();

            foreach ($usersRows as $u) {
                $nKey = $normKey($u->ruta);
                if ($nKey !== '' && !isset($vendedoresUsers[$nKey])) {
                    $vendedoresUsers[$nKey] = trim((string) $u->username);
                }
            }
        } catch (\Throwable $e) {
            // Silencioso
        }

        // 4. Obtener configuraciones persistidas en route_visit_days
        $configsPorRutaNorm = [];
        try {
            $allConfigs = RouteVisitDay::with('updater:id,username')->get();
            foreach ($allConfigs as $conf) {
                $nKey = $normKey($conf->route);
                if ($nKey === '') {
                    continue;
                }
                if (!isset($nombresRutaMap[$nKey])) {
                    $nombresRutaMap[$nKey] = trim((string) $conf->route);
                }
                $configsPorRutaNorm[$nKey][] = $conf;
            }
        } catch (\Throwable $e) {
            Log::error('GetDiasRuteoDataAction route_visit_days error: ' . $e->getMessage());
        }

        // 5. Unificar rutas únicas preservando orden natural
        $normKeysUnicas = array_keys($nombresRutaMap);
        sort($normKeysUnicas, SORT_NATURAL | SORT_FLAG_CASE);

        $canalesUnicos = [];
        foreach ($metaPorRutaNorm as $m) {
            if (!empty($m['canal'])) {
                $canalesUnicos[$m['canal']] = true;
            }
        }
        $canalesLista = array_keys($canalesUnicos);
        if (empty($canalesLista)) {
            $canalesLista = ['MAY', 'TDB', 'MZO', 'PROV'];
        }

        $rutasResultado = [];
        $kpiPersonalizadas = 0;
        $kpiEstandar = 0;

        foreach ($normKeysUnicas as $nKey) {
            $displayRuta = $nombresRutaMap[$nKey];
            $meta = $metaPorRutaNorm[$nKey] ?? null;
            $canal = $meta['canal'] ?? 'TDB';
            $vendedor = $meta['vendedor'] ?? ($vendedoresUsers[$nKey] ?? 'Sin asignar');

            $conteosDia = $conteosPorRutaDia[$nKey] ?? [];
            $totalClientesRuta = array_sum($conteosDia);

            $configsRuta = collect($configsPorRutaNorm[$nKey] ?? []);
            $diasConfigurados = [];
            $tieneAlgunaPersonalizada = false;

            for ($diaNum = 1; $diaNum <= 6; $diaNum++) {
                $diaNombre = self::DIAS_SEMANA[$diaNum];
                $conf = $configsRuta->firstWhere('day_of_week', $diaNum);

                $diasVisitaAsignados = [$diaNombre];
                $esPersonalizado = false;
                $isActive = true;
                $updatedAt = null;
                $updaterName = null;

                if ($conf && $conf->is_active && is_array($conf->dias_visita) && !empty($conf->dias_visita)) {
                    $diasVisitaAsignados = array_values(array_filter($conf->dias_visita));
                    // Comparar si difiere del día natural estándar
                    $esPersonalizado = (count($diasVisitaAsignados) !== 1 || $diasVisitaAsignados[0] !== $diaNombre);
                    $isActive = (bool) $conf->is_active;
                    $updatedAt = $conf->updated_at ? $conf->updated_at->format('d/m/Y H:i') : null;
                    $updaterName = $conf->updater?->username;
                }

                if ($esPersonalizado) {
                    $tieneAlgunaPersonalizada = true;
                }

                // Calcular suma de clientes para los días asignados
                $clientesCalculados = 0;
                foreach ($diasVisitaAsignados as $dAsig) {
                    $dCanon = $this->normalizarDiaNombre((string) $dAsig);
                    if ($dCanon && isset($conteosDia[$dCanon])) {
                        $clientesCalculados += $conteosDia[$dCanon];
                    }
                }

                $diasConfigurados[$diaNum] = [
                    'day_of_week' => $diaNum,
                    'dia_nombre' => $diaNombre,
                    'dias_visita' => $diasVisitaAsignados,
                    'es_personalizado' => $esPersonalizado,
                    'clientes_dia_natural' => $conteosDia[$diaNombre] ?? 0,
                    'clientes_calculados' => $clientesCalculados,
                    'is_active' => $isActive,
                    'updated_at' => $updatedAt,
                    'updater' => $updaterName,
                ];
            }

            if ($tieneAlgunaPersonalizada) {
                $kpiPersonalizadas++;
            } else {
                $kpiEstandar++;
            }

            $rutasResultado[] = [
                'ruta' => $displayRuta,
                'canal' => $canal,
                'vendedor' => $vendedor,
                'total_clientes_ruta' => $totalClientesRuta,
                'tiene_personalizaciones' => $tieneAlgunaPersonalizada,
                'clientes_por_dia' => $conteosDia,
                'dias' => $diasConfigurados,
            ];
        }

        $diasSemanaLista = [];
        foreach (self::DIAS_SEMANA as $id => $name) {
            $diasSemanaLista[] = ['id' => $id, 'name' => $name];
        }

        return [
            'rutas' => $rutasResultado,
            'canales' => $canalesLista,
            'dias_semana' => $diasSemanaLista,
            'kpis' => [
                'total_rutas' => count($rutasResultado),
                'rutas_personalizadas' => $kpiPersonalizadas,
                'rutas_estandar' => $kpiEstandar,
            ],
        ];
    }

    /**
     * Normaliza cualquier cadena o variante de día al formato canónico (Lunes, Martes, etc.).
     */
    public function normalizarDiaNombre(?string $dia): ?string
    {
        if ($dia === null || trim($dia) === '') {
            return null;
        }

        $limpio = mb_strtolower(trim($dia), 'UTF-8');
        $limpio = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $limpio);

        if (str_starts_with($limpio, 'lun') || $limpio === '1') {
            return 'Lunes';
        }
        if (str_starts_with($limpio, 'mar') || $limpio === '2') {
            return 'Martes';
        }
        if (str_starts_with($limpio, 'mie') || $limpio === '3') {
            return 'Miércoles';
        }
        if (str_starts_with($limpio, 'jue') || $limpio === '4') {
            return 'Jueves';
        }
        if (str_starts_with($limpio, 'vie') || $limpio === '5') {
            return 'Viernes';
        }
        if (str_starts_with($limpio, 'sab') || $limpio === '6') {
            return 'Sábado';
        }
        if (str_starts_with($limpio, 'dom') || $limpio === '7') {
            return 'Domingo';
        }

        return null;
    }
}
