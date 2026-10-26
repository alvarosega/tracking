<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GetAvanceItinerarioSecuenciaAction
{
    private const DIAS_VALIDOS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    /**
     * Obtiene el itinerario y secuencia cronológica 1..N para una ruta y día determinado.
     *
     * @param string $ruta
     * @param string|null $diaNorm
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $diaNorm = null): array
    {
        $rutaClean = strtoupper(trim($ruta));

        // Obtener días disponibles con clientes para esta ruta
        $diasDisponibles = DB::connection('supervisor')->table('pan_ruteo')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->whereNotNull('cliente_id')
            ->where('cliente_id', '>', 0)
            ->select([
                DB::raw("LOWER(TRIM(COALESCE(dia_norm, dia))) as dia"),
                DB::raw("COUNT(DISTINCT cliente_id) as total_clientes"),
            ])
            ->groupBy(DB::raw("LOWER(TRIM(COALESCE(dia_norm, dia)))"))
            ->get()
            ->filter(fn ($row) => in_array($row->dia, self::DIAS_VALIDOS, true))
            ->sortBy(fn ($row) => array_search($row->dia, self::DIAS_VALIDOS, true))
            ->values();

        // Si no se proporcionó día, tomar el primer día con clientes o el día actual
        if (!$diaNorm) {
            $diaHoyMap = [
                1 => 'lunes',
                2 => 'martes',
                3 => 'miercoles',
                4 => 'jueves',
                5 => 'viernes',
                6 => 'sabado',
                7 => 'domingo',
            ];
            $diaHoy = $diaHoyMap[(int) date('N')] ?? 'lunes';

            $coincidenciaHoy = $diasDisponibles->firstWhere('dia', $diaHoy);
            if ($coincidenciaHoy) {
                $diaNorm = $diaHoy;
            } elseif ($diasDisponibles->isNotEmpty()) {
                $diaNorm = (string) $diasDisponibles->first()->dia;
            } else {
                $diaNorm = 'lunes';
            }
        } else {
            $diaNorm = strtolower(trim($diaNorm));
        }

        // Verificar si la tabla de secuencia personalizada pan_ruteo_cronologico existe
        $tieneTablaCron = true;
        try {
            $tieneTablaCron = Schema::connection('supervisor')->hasTable('pan_ruteo_cronologico');
        } catch (\Throwable $e) {
            $tieneTablaCron = false;
        }

        $query = DB::connection('supervisor')->table('pan_ruteo as p')
            ->where(DB::raw("TRIM(UPPER(p.ruta))"), $rutaClean)
            ->where(DB::raw("LOWER(TRIM(COALESCE(p.dia_norm, p.dia)))"), $diaNorm)
            ->where(function ($q) {
                $q->where(DB::raw("LOWER(TRIM(p.estado))"), 'activo')
                  ->orWhereNull('p.estado');
            })
            ->where('p.cliente_id', '>', 0);

        if ($tieneTablaCron) {
            $query->leftJoin('pan_ruteo_cronologico as c', function ($join) {
                $join->on('p.cliente_id', '=', 'c.cliente_id')
                     ->on(DB::raw("TRIM(UPPER(c.ruta))"), '=', DB::raw("TRIM(UPPER(p.ruta))"))
                     ->on(DB::raw("LOWER(TRIM(c.dia_norm))"), '=', DB::raw("LOWER(TRIM(COALESCE(p.dia_norm, p.dia)))"));
            });

            $query->select([
                'p.cliente_id',
                DB::raw("COALESCE(MAX(p.cliente), 'Cliente S/N') as cliente"),
                DB::raw("COALESCE(MAX(p.direccion), 'Sin Dirección') as direccion"),
                DB::raw("COALESCE(MAX(p.tipo_negocio), '-') as tipo_negocio"),
                DB::raw("COALESCE(MAX(p.latitud), 0.0) as latitud"),
                DB::raw("COALESCE(MAX(p.longitud), 0.0) as longitud"),
                DB::raw("COALESCE(MAX(c.orden_visita), 0) as orden_registrado"),
            ])
            ->groupBy('p.cliente_id')
            ->orderByRaw("CASE WHEN COALESCE(MAX(c.orden_visita), 0) > 0 THEN MAX(c.orden_visita) ELSE 999999 END ASC")
            ->orderBy('p.cliente_id');
        } else {
            $query->select([
                'p.cliente_id',
                DB::raw("COALESCE(MAX(p.cliente), 'Cliente S/N') as cliente"),
                DB::raw("COALESCE(MAX(p.direccion), 'Sin Dirección') as direccion"),
                DB::raw("COALESCE(MAX(p.tipo_negocio), '-') as tipo_negocio"),
                DB::raw("COALESCE(MAX(p.latitud), 0.0) as latitud"),
                DB::raw("COALESCE(MAX(p.longitud), 0.0) as longitud"),
                DB::raw("0 as orden_registrado"),
            ])
            ->groupBy('p.cliente_id')
            ->orderBy('p.cliente_id');
        }

        $rows = $query->get();

        $clientes = [];
        $totalConCoords = 0;

        foreach ($rows as $idx => $r) {
            $lat = (float) $r->latitud;
            $lng = (float) $r->longitud;
            $ordenReg = (int) $r->orden_registrado;

            if ($lat != 0.0 && $lng != 0.0) {
                $totalConCoords++;
            }

            $clientes[] = [
                'cliente_id' => (int) $r->cliente_id,
                'cliente' => (string) $r->cliente,
                'direccion' => (string) $r->direccion,
                'tipo_negocio' => (string) $r->tipo_negocio,
                'latitud' => $lat,
                'longitud' => $lng,
                'tiene_coords' => ($lat != 0.0 && $lng != 0.0),
                'orden' => $ordenReg > 0 ? $ordenReg : ($idx + 1),
            ];
        }

        // Re-ordenar y garantizar orden correlativo 1..N
        usort($clientes, fn ($a, $b) => $a['orden'] <=> $b['orden']);
        foreach ($clientes as $i => &$c) {
            $c['orden'] = $i + 1;
        }
        unset($c);

        return [
            'ruta' => $rutaClean,
            'dia' => $diaNorm,
            'dias_disponibles' => $diasDisponibles->pluck('dia')->all(),
            'total_clientes' => count($clientes),
            'total_con_coordenadas' => $totalConCoords,
            'clientes' => $clientes,
        ];
    }
}

