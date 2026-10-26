<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use Illuminate\Support\Facades\DB;
use Throwable;

class GetRuteoSnapshotsAction
{
    /**
     * Consulta el historial de snapshots inmutables preservados de pan_ruteo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array
    {
        try {
            $conn = DB::connection('supervisor');

            // Agrupar por snapshot_id cumpliendo ONLY_FULL_GROUP_BY de MySQL
            $rows = $conn->select("
                SELECT 
                    snapshot_id AS id,
                    COALESCE(MAX(fecha_carga), NOW()) AS creado_en,
                    COALESCE(MAX(archivo_origen), '-') AS archivo_original,
                    COUNT(*) AS total_filas,
                    COUNT(DISTINCT NULLIF(ruta, '')) AS total_rutas
                FROM pan_ruteo_snapshots
                GROUP BY snapshot_id
                ORDER BY MAX(fecha_carga) DESC, snapshot_id DESC
                LIMIT 50
            ");

            return array_map(function ($r) {
                return [
                    'id' => (string)$r->id,
                    'snapshot_id' => (string)$r->id,
                    'creado_en' => (string)$r->creado_en,
                    'fecha' => (string)$r->creado_en,
                    'archivo_original' => (string)$r->archivo_original,
                    'total_filas' => (int)$r->total_filas,
                    'total_rutas' => (int)$r->total_rutas,
                ];
            }, $rows);
        } catch (Throwable) {
            return [];
        }
    }
}

