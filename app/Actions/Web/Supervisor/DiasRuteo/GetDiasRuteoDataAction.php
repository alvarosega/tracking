<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\DiasRuteo;

use App\Models\RouteVisitDay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        // 1. Obtener catálogo maestro de rutas desde dim_rutas
        $dimRows = DB::connection('supervisor')
            ->table('dim_rutas')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->select('ruta', 'canal', 'vendedor')
            ->get();

        $metaRuta = $dimRows->keyBy('ruta');
        $rutasDim = $dimRows->pluck('ruta');

        // 2. Obtener rutas activas y conteo de clientes por día en pan_ruteo
        $conteosPorRutaDia = [];
        $rutasPan = collect();

        try {
            $panRows = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('estado', 'Activo')
                ->whereNotNull('ruta')
                ->where('ruta', '!=', '')
                ->select([
                    'ruta',
                    DB::raw("COALESCE(NULLIF(dia_norm, ''), dia) as dia_calculado"),
                    DB::raw('COUNT(DISTINCT cliente_id) as total_clientes'),
                ])
                ->groupBy('ruta', DB::raw("COALESCE(NULLIF(dia_norm, ''), dia)"))
                ->get();

            $rutasPan = $panRows->pluck('ruta')->unique();

            foreach ($panRows as $pr) {
                $ruta = trim((string) $pr->ruta);
                $diaCanon = $this->normalizarDiaNombre((string) $pr->dia_calculado);
                if ($diaCanon) {
                    $conteosPorRutaDia[$ruta][$diaCanon] = (int) $pr->total_clientes;
                }
            }
        } catch (\Throwable $e) {
            // Silencioso si pan_ruteo no responde temporalmente
        }

        // 3. Obtener usuarios vendedores en users
        $vendedoresUsers = [];
        try {
            $vendedoresUsers = DB::table('users')
                ->whereNotNull('ruta')
                ->where('ruta', '!=', '')
                ->where('is_active', true)
                ->pluck('username', 'ruta')
                ->toArray();
        } catch (\Throwable $e) {
            // Silencioso
        }

        // 4. Obtener configuraciones persistidas en route_visit_days
        $configsPorRuta = collect();
        try {
            $configsPorRuta = RouteVisitDay::with('updater:id,username')
                ->get()
                ->groupBy('route');
        } catch (\Throwable $e) {
            // Si la tabla no existe aún, se asumen días estándar
        }

        // 5. Unificar rutas únicas
        $rutasUnicas = $rutasDim
            ->concat($rutasPan)
            ->concat($configsPorRuta->keys())
            ->map(fn($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $canalesUnicos = $dimRows->pluck('canal')->filter()->unique()->values()->toArray();
        if (empty($canalesUnicos)) {
            $canalesUnicos = ['MAY', 'TDB', 'MZO', 'PROV'];
        }

        $rutasResultado = [];
        $kpiPersonalizadas = 0;
        $kpiEstandar = 0;

        foreach ($rutasUnicas as $ruta) {
            $meta = $metaRuta->get($ruta);
            $canal = $meta?->canal ?? 'TDB';
            $vendedor = $meta?->vendedor ?? ($vendedoresUsers[$ruta] ?? 'Sin asignar');

            $conteosDia = $conteosPorRutaDia[$ruta] ?? [];
            $totalClientesRuta = array_sum($conteosDia);

            $configsRuta = $configsPorRuta->get($ruta, collect());
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
                    $dCanon = $this->normalizarDiaNombre($dAsig);
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
                'ruta' => $ruta,
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
            'canales' => $canalesUnicos,
            'dias_semana' => $diasSemanaLista,
            'kpis' => [
                'total_rutas' => count($rutasResultado),
                'rutas_personalizadas' => $kpiPersonalizadas,
                'rutas_estandar' => $kpiEstandar,
            ],
        ];
    }

    /**
     * Normaliza cualquier cadena de día al formato canónico (Lunes, Martes, etc.).
     */
    protected function normalizarDiaNombre(string $dia): ?string
    {
        $limpio = ucfirst(mb_strtolower(trim($dia), 'UTF-8'));
        return match ($limpio) {
            'Lunes' => 'Lunes',
            'Martes' => 'Martes',
            'Miercoles', 'Miércoles' => 'Miércoles',
            'Jueves' => 'Jueves',
            'Viernes' => 'Viernes',
            'Sabado', 'Sábado' => 'Sábado',
            'Domingo' => 'Domingo',
            default => null,
        };
    }
}

