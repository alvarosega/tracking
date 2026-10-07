<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FactVenta;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\Ruta;
use Illuminate\Support\Facades\DB;

class GetClientesCercanosAction
{
    /**
     * Realiza la búsqueda espacial optimizada de clientes por radio geográfico
     * e incorpora el historial consolidado de compras del cliente.
     *
     * @param float $lat
     * @param float $lng
     * @param int $radio Metros
     * @param array<string> $canales
     * @param array<string> $rutas
     * @param array<int> $meses
     * @param int $anio
     * @param bool $soloActivos
     * @return array{clientes: array, total: int, centro: array, radio: int, anio: int}
     */
    public function execute(
        float $lat,
        float $lng,
        int $radio,
        array $canales = [],
        array $rutas = [],
        array $meses = [],
        int $anio = 2026,
        bool $soloActivos = true
    ): array {
        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        // Optimización por caja delimitadora (Bounding Box) antes de calcular Haversine
        $safeLat = (float) $lat;
        $safeLng = (float) $lng;
        $safeRadio = (int) $radio;

        $deltaLat = ($safeRadio + 150) / 111139.0;
        $cosLat = cos(deg2rad($safeLat));
        $deltaLng = ($safeRadio + 150) / (111139.0 * ($cosLat != 0 ? abs($cosLat) : 1));

        $haversineFormula = "(6371000 * 2 * ASIN(SQRT(
            POWER(SIN(RADIANS(latitud - {$safeLat}) / 2), 2) +
            COS(RADIANS({$safeLat})) * COS(RADIANS(latitud)) *
            POWER(SIN(RADIANS(longitud - {$safeLng}) / 2), 2)
        )))";

        $query = PanRuteo::query()
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->where('latitud', '!=', 0)
            ->whereBetween('latitud', [$safeLat - $deltaLat, $safeLat + $deltaLat])
            ->whereBetween('longitud', [$safeLng - $deltaLng, $safeLng + $deltaLng]);

        if ($soloActivos) {
            $query->where(function ($q) {
                $q->where('estado', 'Activo')
                  ->orWhereNull('estado');
            });
        }

        if (!empty($rutas)) {
            $query->whereIn('ruta', $rutas);
        }

        $clientes = $query
            ->select([
                'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
                'direccion', 'contacto', 'celular', 'latitud', 'longitud',
                'ruta', 'dia_norm', 'nit', 'nombre_factura', 'estado',
                DB::raw("ROUND({$haversineFormula}) AS distancia_metros")
            ])
            ->having('distancia_metros', '<=', $safeRadio)
            ->orderBy('distancia_metros', 'ASC')
            ->get();

        $clienteIds = $clientes->pluck('cliente_id')->filter()->unique()->toArray();
        $comprasMap = [];

        // Consolidación de compras históricas para los clientes encontrados
        if (!empty($clienteIds) && !empty($meses)) {
            $queryVentas = FactVenta::query()
                ->validas()
                ->whereIn('cliente_id', $clienteIds)
                ->whereBetween('fecha_norm', ["{$anio}-01-01", "{$anio}-12-31"])
                ->whereIn('mes', $meses);

            $comprasAgregadas = $queryVentas
                ->groupBy('cliente_id')
                ->select([
                    'cliente_id',
                    DB::raw('ROUND(SUM(monto_final), 2) as total_monto'),
                    DB::raw('COUNT(DISTINCT venta_id) as total_pedidos')
                ])
                ->get();

            foreach ($comprasAgregadas as $ca) {
                $comprasMap[$ca->cliente_id] = [
                    'total_monto' => (float) $ca->total_monto,
                    'total_pedidos' => (int) $ca->total_pedidos,
                ];
            }
        }

        $resultado = $clientes->map(function ($c) use ($comprasMap) {
            $compra = $comprasMap[$c->cliente_id] ?? ['total_monto' => 0.0, 'total_pedidos' => 0];
            return [
                'id' => $c->id,
                'cliente_id' => $c->cliente_id,
                'cliente' => $c->cliente,
                'vendedor' => $c->vendedor,
                'tipo_negocio' => $c->tipo_negocio,
                'direccion' => $c->direccion,
                'contacto' => $c->contacto,
                'celular' => $c->celular,
                'latitud' => (float) $c->latitud,
                'longitud' => (float) $c->longitud,
                'ruta' => $c->ruta,
                'dia_norm' => $c->dia_norm,
                'nit' => $c->nit,
                'nombre_factura' => $c->nombre_factura,
                'estado' => $c->estado ?? 'Activo',
                'distancia_metros' => (int) $c->distancia_metros,
                'total_compras' => $compra['total_monto'],
                'total_pedidos' => $compra['total_pedidos'],
            ];
        });

        return [
            'clientes' => $resultado->values()->toArray(),
            'total' => $resultado->count(),
            'centro' => ['latitud' => $safeLat, 'longitud' => $safeLng],
            'radio' => $safeRadio,
            'anio' => $anio,
        ];
    }
}

