<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Visitas;

use App\Services\GeoSpatialService;
use Illuminate\Support\Facades\DB;

class GetVisitasDataAction
{
    /**
     * Consulta consolidada de clientes con auditoría fotográfica histórica.
     *
     * @param array<int, string> $dias
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @param bool $incluirInactivosConFoto
     * @return array{
     *     clientes: array<int, array<string, mixed>>,
     *     kpis: array{
     *         total: int,
     *         con_foto: int,
     *         sin_foto: int,
     *         cobertura_pct: float,
     *         con_gps: int,
     *         sin_gps: int
     *     }
     * }
     */
    public function execute(
        array $dias = [],
        array $canales = [],
        array $rutas = [],
        bool $incluirInactivosConFoto = false
    ): array {
        $emptyResult = [
            'clientes' => [],
            'kpis' => [
                'total' => 0,
                'con_foto' => 0,
                'sin_foto' => 0,
                'cobertura_pct' => 0.0,
                'con_gps' => 0,
                'sin_gps' => 0,
            ],
        ];

        if (empty($rutas) && empty($canales) && empty($dias)) {
            return $emptyResult;
        }

        $rutasFiltradas = $rutas;
        if (!empty($canales)) {
            $rutasPorCanal = DB::connection('supervisor')->table('dim_rutas')
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->toArray();

            $rutasFiltradas = !empty($rutas)
                ? array_values(array_intersect($rutas, $rutasPorCanal))
                : $rutasPorCanal;
        }

        // Consulta consolidada por cliente_id
        $query = DB::connection('supervisor')->table('pan_ruteo as pr')
            ->leftJoin('dim_rutas as dr', 'pr.ruta', '=', 'dr.ruta')
            ->select([
                'pr.cliente_id',
                DB::raw("COALESCE(NULLIF(MAX(pr.cliente), ''), MAX(pr.cliente_norm), 'Cliente #' || pr.cliente_id) as cliente"),
                DB::raw("MAX(pr.ruta) as ruta"),
                DB::raw("COALESCE(MAX(dr.canal), 'PRT') as canal"),
                DB::raw("MAX(pr.vendedor) as vendedor"),
                DB::raw("GROUP_CONCAT(DISTINCT COALESCE(NULLIF(pr.dia, ''), pr.dia_norm) ORDER BY pr.id SEPARATOR ', ') as dias_visita"),
                DB::raw("MAX(pr.direccion) as direccion"),
                DB::raw("MAX(pr.referencia) as referencia"),
                DB::raw("COALESCE(MAX(NULLIF(pr.celular, '')), MAX(pr.telefono)) as telefono"),
                DB::raw("MAX(pr.contacto) as contacto"),
                DB::raw("MAX(pr.tipo_negocio) as tipo_negocio"),
                DB::raw("MAX(pr.nit) as nit"),
                DB::raw("MAX(pr.latitud) as latitud"),
                DB::raw("MAX(pr.longitud) as longitud"),
                DB::raw("MAX(pr.estado) as estado"),
            ])
            ->groupBy('pr.cliente_id');

        if (!empty($rutasFiltradas)) {
            $query->whereIn('pr.ruta', $rutasFiltradas);
        }

        if (!empty($dias)) {
            $query->where(function ($q) use ($dias) {
                $q->whereIn('pr.dia', $dias)
                  ->orWhereIn('pr.dia_norm', $dias);

                if (in_array('Miércoles', $dias, true)) {
                    $q->orWhereIn('pr.dia', ['Miercoles', 'MIERCOLES'])
                      ->orWhereIn('pr.dia_norm', ['Miercoles', 'MIERCOLES']);
                }
                if (in_array('Sábado', $dias, true)) {
                    $q->orWhereIn('pr.dia', ['Sabado', 'SABADO'])
                      ->orWhereIn('pr.dia_norm', ['Sabado', 'SABADO']);
                }
            });
        }

        if (!$incluirInactivosConFoto) {
            $query->where('pr.estado', 'Activo');
        } else {
            $query->whereIn('pr.estado', ['Activo', 'Inactivo']);
        }

        $clientesRaw = $query->get();

        if ($clientesRaw->isEmpty()) {
            return $emptyResult;
        }

        // Historial de fotos en alva.visitas
        $clientIds = $clientesRaw->pluck('cliente_id')->toArray();

        $visitasResumen = DB::table('visitas')
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', '')
            ->selectRaw('client_id, COUNT(id) as total_fotos, MAX(visited_at) as ultima_foto_at')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        $clientes = [];
        $totalConFoto = 0;
        $totalSinFoto = 0;
        $totalConGps = 0;
        $totalSinGps = 0;

        foreach ($clientesRaw as $c) {
            $visita = $visitasResumen->get($c->cliente_id);
            $tieneFoto = !empty($visita) && $visita->total_fotos > 0;

            if ($c->estado === 'Inactivo' && !$tieneFoto) {
                continue;
            }

            $lat = (float) $c->latitud;
            $lng = (float) $c->longitud;
            $tieneGps = GeoSpatialService::isValidCoordinates($lat, $lng);

            if ($tieneFoto) {
                $totalConFoto++;
            } else {
                $totalSinFoto++;
            }

            if ($tieneGps) {
                $totalConGps++;
            } else {
                $totalSinGps++;
            }

            $clientes[] = [
                'cliente_id' => $c->cliente_id,
                'cliente' => $c->cliente,
                'ruta' => $c->ruta,
                'canal' => $c->canal,
                'vendedor' => $c->vendedor,
                'dias_visita' => $c->dias_visita ?: 'No asignado',
                'direccion' => $c->direccion,
                'referencia' => $c->referencia,
                'telefono' => $c->telefono,
                'contacto' => $c->contacto,
                'tipo_negocio' => $c->tipo_negocio,
                'nit' => $c->nit,
                'estado' => $c->estado,
                'latitud' => $tieneGps ? $lat : null,
                'longitud' => $tieneGps ? $lng : null,
                'tiene_gps' => $tieneGps,
                'tiene_foto' => $tieneFoto,
                'total_fotos' => $tieneFoto ? (int) $visita->total_fotos : 0,
                'ultima_foto_at' => $tieneFoto ? $visita->ultima_foto_at : null,
            ];
        }

        $total = count($clientes);
        $coberturaPct = $total > 0 ? round(($totalConFoto / $total) * 100, 1) : 0.0;

        return [
            'clientes' => $clientes,
            'kpis' => [
                'total' => $total,
                'con_foto' => $totalConFoto,
                'sin_foto' => $totalSinFoto,
                'cobertura_pct' => $coberturaPct,
                'con_gps' => $totalConGps,
                'sin_gps' => $totalSinGps,
            ],
        ];
    }
}

