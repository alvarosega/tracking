<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CambiarEstadoRevisionMasivoAction
{
    private const ESTADOS_VALIDOS = ['APROBADO', 'RECHAZADO', 'PENDIENTE'];

    /**
     * Actualiza masivamente y exclusivamente el estado de revisión en supervisor.saneamiento_base.
     * Bajo ninguna circunstancia modifica ni interactúa con la tabla pan_ruteo.
     *
     * @param int[] $ids Array de IDs de registros en saneamiento_base
     * @param string $nuevoEstado APROBADO | RECHAZADO | PENDIENTE
     * @param string|null $motivo Observación o justificación opcional
     * @return array{success: bool, count: int, ids: int[], estado_revision: string, revisado_at: string|null, message: string}
     */
    public function execute(array $ids, string $nuevoEstado, ?string $motivo = null): array
    {
        $estadoUpper = strtoupper(trim($nuevoEstado));

        if (!in_array($estadoUpper, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException("El estado '{$nuevoEstado}' no es válido. Debe ser APROBADO, RECHAZADO o PENDIENTE.");
        }

        // Limpiar y filtrar IDs válidos mayores a cero
        $validIds = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn(int $id) => $id > 0
        )));

        if (empty($validIds)) {
            return [
                'success' => true,
                'count' => 0,
                'ids' => [],
                'estado_revision' => $estadoUpper,
                'revisado_at' => null,
                'message' => 'No se proporcionaron registros válidos para actualizar.',
            ];
        }

        $conexion = DB::connection('supervisor');
        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $revisadoAt = ($estadoUpper !== 'PENDIENTE') ? $nowBolivia : null;

        $updateData = [
            'estado_revision' => $estadoUpper,
            'revisado_at' => $revisadoAt,
            'updated_at' => $nowBolivia,
        ];

        $totalActualizados = 0;

        // Actualizar estrictamente y únicamente en saneamiento_base en bloques de 500
        $conexion->transaction(function () use ($conexion, $validIds, $updateData, &$totalActualizados): void {
            foreach (array_chunk($validIds, 500) as $chunk) {
                $afectados = $conexion->table('saneamiento_base')
                    ->whereIn('id', $chunk)
                    ->update($updateData);

                $totalActualizados += $afectados;
            }
        });

        return [
            'success' => true,
            'count' => $totalActualizados,
            'ids' => $validIds,
            'estado_revision' => $estadoUpper,
            'revisado_at' => $revisadoAt,
            'message' => "Se actualizaron {$totalActualizados} registro(s) a estado {$estadoUpper} correctamente.",
        ];
    }
}
