<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class RollbackSnapshotRuteoAction
{
    /**
     * Restaura el plan de ruteo activo (pan_ruteo) al contenido de un snapshot inmutable.
     *
     * @return array<string, mixed>
     */
    public function execute(string $snapshotId): array
    {
        $conn = DB::connection('supervisor');

        $countSnap = (int)($conn->selectOne("
            SELECT COUNT(*) AS total 
            FROM pan_ruteo_snapshots 
            WHERE snapshot_id = ?
        ", [$snapshotId])->total ?? 0);

        if ($countSnap === 0) {
            throw new Exception("El snapshot '{$snapshotId}' no existe o no contiene registros.");
        }

        $cols = [
            'cliente_id', 'nodo_id', 'nodo', 'cliente', 'vendedor', 'tipo_negocio', 'territorio', 'mercado',
            'fecha_servidor', 'fecha_dispositivo', 'contacto', 'ci', 'telefono', 'celular', 'direccion', 'nro',
            'referencia', 'nombre_factura', 'nit', 'tipo_pago', 'latitud', 'longitud', 'intensidad', 'estado',
            'zona_venta', 'cod_vendedor', 'dias_credito', 'limite', 'ruta', 'dia', 'precio', 'cluster_t', 'cluster_c',
            'cliente_norm', 'vendedor_norm', 'dia_norm'
        ];

        $colsJoined = '`' . implode('`, `', $cols) . '`';

        // 1. Truncate tabla viva
        $conn->statement("TRUNCATE TABLE `pan_ruteo`");

        // 2. Inserción atómica masiva desde la tabla de snapshots
        $conn->statement("
            INSERT INTO `pan_ruteo` ({$colsJoined})
            SELECT {$colsJoined}
            FROM `pan_ruteo_snapshots`
            WHERE `snapshot_id` = ?
        ", [$snapshotId]);

        // 3. Bitácora en sys_cargas
        $conn->table('sys_cargas')->insert([
            'archivo_original' => "Restauración Rollback a {$snapshotId}",
            'archivo_almacenado' => $snapshotId,
            'sha256' => $snapshotId,
            'tipo_entidad' => 'PAN_RUTEO',
            'filas_leidas' => $countSnap,
            'filas_afectadas' => $countSnap,
            'filas_nuevas' => 0,
            'filas_actualizadas' => $countSnap,
            'filas_ignoradas' => 0,
            'filas_con_discrepancia' => 0,
            'rango_inicio' => '-',
            'rango_fin' => '-',
            'fecha_ingesta' => now(),
        ]);

        return [
            'status' => 'success',
            'snapshot_id' => $snapshotId,
            'restaurados' => $countSnap,
            'mensaje' => "El plan de ruteo se restauró exitosamente al snapshot {$snapshotId} ({$countSnap} registros).",
        ];
    }
}

