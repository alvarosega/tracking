<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use Illuminate\Support\Facades\DB;
use Throwable;

class GetIngestaHistorialCargasAction
{
    /**
     * Consulta las últimas 10 cargas registradas en la bitácora sys_cargas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array
    {
        try {
            $conn = DB::connection('supervisor');

            $rows = $conn->select("
                SELECT id, archivo_original, tipo_entidad, filas_leidas,
                       COALESCE(filas_nuevas, 0)           AS filas_nuevas,
                       COALESCE(filas_actualizadas, 0)     AS filas_actualizadas,
                       COALESCE(filas_ignoradas, 0)        AS filas_ignoradas,
                       COALESCE(filas_con_discrepancia, 0) AS filas_con_discrepancia,
                       COALESCE(filas_afectadas, 0)        AS filas_afectadas,
                       COALESCE(rango_inicio, '-')         AS rango_inicio,
                       COALESCE(rango_fin, '-')            AS rango_fin,
                       fecha_ingesta
                FROM sys_cargas
                ORDER BY id DESC
                LIMIT 10
            ");

            return array_map(function ($r) {
                $fechaStr = (string)($r->fecha_ingesta ?? '-');
                return [
                    'id' => (int)$r->id,
                    'archivo_original' => (string)($r->archivo_original ?? '-'),
                    'tipo_entidad' => (string)($r->tipo_entidad ?? '-'),
                    'filas_leidas' => (int)($r->filas_leidas ?? 0),
                    'filas_nuevas' => (int)($r->filas_nuevas ?? 0),
                    'filas_actualizadas' => (int)($r->filas_actualizadas ?? 0),
                    'filas_ignoradas' => (int)($r->filas_ignoradas ?? 0),
                    'filas_con_discrepancia' => (int)($r->filas_con_discrepancia ?? 0),
                    'filas_afectadas' => (int)($r->filas_afectadas ?? 0),
                    'rango_inicio' => (string)($r->rango_inicio ?? '-'),
                    'rango_fin' => (string)($r->rango_fin ?? '-'),
                    'fecha_ingesta' => $fechaStr,
                    'creado_en' => $fechaStr,
                    'fecha' => $fechaStr,
                ];
            }, $rows);
        } catch (Throwable) {
            return [];
        }
    }
}

