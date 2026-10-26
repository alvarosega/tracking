<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class SearchClientesSaneamientoAction
{
    /**
     * Busca clientes activos por coincidencia de ID o nombre.
     *
     * @param string $q
     * @param int $limit
     * @return array<array<string, mixed>>
     */
    public function execute(string $q, int $limit = 10): array
    {
        $clean = trim($q);
        if (mb_strlen($clean) < 2) {
            return [];
        }

        $patron = "%{$clean}%";
        $conn = DB::connection('supervisor');

        $rows = $conn->table('pan_ruteo as p')
            ->where(function ($w) {
                $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                    ->orWhereNull('p.estado')
                    ->orWhereRaw("p.estado = ''");
            })
            ->whereNotNull('p.latitud')
            ->where('p.latitud', '!=', 0.0)
            ->whereNotNull('p.longitud')
            ->where('p.longitud', '!=', 0.0)
            ->where(function ($w) use ($patron) {
                $w->where(DB::raw("CAST(p.cliente_id AS CHAR)"), 'LIKE', $patron)
                    ->orWhere(DB::raw("UPPER(p.cliente)"), 'LIKE', strtoupper($patron));
            })
            ->select([
                'p.cliente_id',
                'p.cliente',
                DB::raw("COALESCE(p.cliente_norm, '') as cliente_norm"),
                DB::raw("COALESCE(p.nombre_factura, '') as nombre_factura"),
                DB::raw("COALESCE(p.nit, '') as nit"),
                DB::raw("COALESCE(p.ci, '') as ci"),
                DB::raw("COALESCE(p.telefono, '') as telefono"),
                DB::raw("COALESCE(p.celular, '') as celular"),
                DB::raw("COALESCE(p.direccion, 'Sin Dirección') as direccion"),
                'p.latitud',
                'p.longitud',
                DB::raw("TRIM(UPPER(p.ruta)) as ruta"),
                DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') as dia"),
                DB::raw("COALESCE(TRIM(p.tipo_negocio), '-') as tipo_negocio"),
            ])
            ->orderBy('p.ruta')
            ->orderBy('p.cliente_id')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => [
            'cliente_id' => (int) $r->cliente_id,
            'cliente' => trim((string) $r->cliente),
            'cliente_norm' => trim((string) $r->cliente_norm),
            'nombre_factura' => trim((string) $r->nombre_factura),
            'nit' => (string) $r->nit,
            'ci' => (string) $r->ci,
            'telefono' => (string) $r->telefono,
            'celular' => (string) $r->celular,
            'direccion' => (string) $r->direccion,
            'latitud' => (float) $r->latitud,
            'longitud' => (float) $r->longitud,
            'ruta' => (string) $r->ruta,
            'dia' => strtoupper(trim((string) $r->dia)),
            'tipo_negocio' => (string) $r->tipo_negocio,
        ])->all();
    }
}

