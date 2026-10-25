<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Visitas;

use App\Services\GeoSpatialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetVisitasAvanceDataAction
{
    /**
     * Umbral de tolerancia de proximidad a la ubicación oficial del cliente en metros.
     */
    private const UMBRAL_EN_RANGO_METROS = 50.0;

    /**
     * Consulta el avance en terreno de las rutas en el día, calculando trayectorias,
     * distancias a coordenadas oficiales de clientes y tiempos entre visitas.
     *
     * @param string $fecha
     * @param array<int, string> $canales
     * @param array<int, string> $rutas
     * @return array{
     *     rutas_trayectorias: array<int, array<string, mixed>>,
     *     kpis: array{
     *         total_fotos: int,
     *         rutas_activas: int,
     *         en_rango: int,
     *         desviadas: int
     *     }
     * }
     */
    public function execute(string $fecha, array $canales = [], array $rutas = []): array
    {
        $emptyResult = [
            'rutas_trayectorias' => [],
            'kpis' => [
                'total_fotos' => 0,
                'rutas_activas' => 0,
                'en_rango' => 0,
                'desviadas' => 0,
            ],
        ];

        if (empty($rutas) && empty($canales)) {
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

        // Consultar visitas del día (is_opportunity = 0 con foto)
        $visitasQuery = DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->whereDate('v.visited_at', $fecha)
            ->where('v.is_opportunity', 0)
            ->whereNotNull('v.photo_path')
            ->where('v.photo_path', '!=', '')
            ->select([
                'v.id',
                'v.client_id',
                'v.route',
                'v.status',
                'v.comments',
                'v.latitude as visita_lat',
                'v.longitude as visita_lng',
                'v.accuracy',
                'v.photo_path',
                'v.visited_at',
                'u.username as vendedor',
            ])
            ->orderBy('v.visited_at', 'asc');

        if (!empty($rutasFiltradas)) {
            $visitasQuery->whereIn('v.route', $rutasFiltradas);
        }

        $visitasRaw = $visitasQuery->get();

        if ($visitasRaw->isEmpty()) {
            return $emptyResult;
        }

        // Extraer datos oficiales del cliente desde supervisor.pan_ruteo
        $clientIds = $visitasRaw->pluck('client_id')->unique()->filter()->toArray();

        $clientesOficiales = DB::connection('supervisor')->table('pan_ruteo as pr')
            ->leftJoin('dim_rutas as dr', 'pr.ruta', '=', 'dr.ruta')
            ->whereIn('pr.cliente_id', $clientIds)
            ->select([
                'pr.cliente_id',
                DB::raw("COALESCE(NULLIF(MAX(pr.cliente), ''), MAX(pr.cliente_norm), 'Cliente #' || pr.cliente_id) as cliente"),
                DB::raw("MAX(pr.direccion) as direccion"),
                DB::raw("MAX(pr.referencia) as referencia"),
                DB::raw("COALESCE(MAX(NULLIF(pr.celular, '')), MAX(pr.telefono)) as telefono"),
                DB::raw("MAX(pr.tipo_negocio) as tipo_negocio"),
                DB::raw("MAX(pr.latitud) as oficial_lat"),
                DB::raw("MAX(pr.longitud) as oficial_lng"),
                DB::raw("COALESCE(MAX(dr.canal), 'PRT') as canal"),
            ])
            ->groupBy('pr.cliente_id')
            ->get()
            ->keyBy('cliente_id');

        $rutasAgrupadas = [];
        $totalEnRango = 0;
        $totalDesviadas = 0;

        foreach ($visitasRaw->groupBy('route') as $ruta => $visitasDeRuta) {
            $puntos = [];
            $orden = 1;
            $visitaAnteriorAt = null;

            foreach ($visitasDeRuta as $v) {
                $oficial = $clientesOficiales->get($v->client_id);
                $visitaLat = (float) $v->visita_lat;
                $visitaLng = (float) $v->visita_lng;

                $oficialLat = $oficial ? (float) $oficial->oficial_lat : null;
                $oficialLng = $oficial ? (float) $oficial->oficial_lng : null;

                $tieneGpsOficial = GeoSpatialService::isValidCoordinates($oficialLat, $oficialLng);

                // Cálculo de distancia entre foto y cliente oficial
                $distanciaOficialMetros = null;
                $enRango = null;

                if ($tieneGpsOficial) {
                    $distanciaOficialMetros = round(
                        GeoSpatialService::haversine($visitaLat, $visitaLng, (float) $oficialLat, (float) $oficialLng),
                        1
                    );
                    $enRango = $distanciaOficialMetros <= self::UMBRAL_EN_RANGO_METROS;
                    if ($enRango) {
                        $totalEnRango++;
                    } else {
                        $totalDesviadas++;
                    }
                }

                // Cálculo de tiempo transcurrido desde la visita anterior
                $deltaMinutos = null;
                if ($visitaAnteriorAt) {
                    $deltaMinutos = round((strtotime((string) $v->visited_at) - strtotime((string) $visitaAnteriorAt)) / 60);
                }
                $visitaAnteriorAt = $v->visited_at;

                $puntos[] = [
                    'id' => $v->id,
                    'orden' => $orden++,
                    'cliente_id' => $v->client_id,
                    'cliente' => $oficial ? $oficial->cliente : 'Cliente #' . $v->client_id,
                    'direccion' => $oficial ? $oficial->direccion : null,
                    'referencia' => $oficial ? $oficial->referencia : null,
                    'telefono' => $oficial ? $oficial->telefono : null,
                    'tipo_negocio' => $oficial ? $oficial->tipo_negocio : null,
                    'canal' => $oficial ? $oficial->canal : 'PRT',
                    'ruta' => $v->route,
                    'vendedor' => $v->vendedor ?: 'Sin asignar',
                    'status' => $v->status,
                    'comments' => $v->comments,
                    'hora' => date('H:i', strtotime((string) $v->visited_at)),
                    'fecha_hora' => $v->visited_at,
                    'delta_minutos' => $deltaMinutos,
                    'photo_url' => Storage::url($v->photo_path),
                    'visita_lat' => $visitaLat,
                    'visita_lng' => $visitaLng,
                    'oficial_lat' => $tieneGpsOficial ? $oficialLat : null,
                    'oficial_lng' => $tieneGpsOficial ? $oficialLng : null,
                    'distancia_oficial_metros' => $distanciaOficialMetros,
                    'en_rango' => $enRango,
                ];
            }

            $rutasAgrupadas[] = [
                'ruta' => $ruta,
                'vendedor' => $visitasDeRuta->first()->vendedor ?: 'Sin asignar',
                'canal' => $puntos[0]['canal'] ?? 'PRT',
                'total_visitas' => count($puntos),
                'puntos' => $puntos,
            ];
        }

        return [
            'rutas_trayectorias' => $rutasAgrupadas,
            'kpis' => [
                'total_fotos' => $visitasRaw->count(),
                'rutas_activas' => count($rutasAgrupadas),
                'en_rango' => $totalEnRango,
                'desviadas' => $totalDesviadas,
            ],
        ];
    }
}

