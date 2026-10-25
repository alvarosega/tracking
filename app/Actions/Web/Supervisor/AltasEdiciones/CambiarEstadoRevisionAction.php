<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CambiarEstadoRevisionAction
{
    private const ESTADOS_VALIDOS = ['APROBADO', 'RECHAZADO', 'PENDIENTE'];

    /**
     * Actualiza exclusivamente el estado de revisión en supervisor.saneamiento_base.
     * Bajo ninguna circunstancia modifica ni interactúa con la tabla pan_ruteo.
     *
     * @param int $id ID del registro en saneamiento_base
     * @param string $nuevoEstado APROBADO | RECHAZADO | PENDIENTE
     * @param string|null $motivo Observación o justificación opcional
     * @return array{success: bool, id: int, estado_revision: string, revisado_at: string|null, message: string}
     */
    public function execute(int $id, string $nuevoEstado, ?string $motivo = null): array
    {
        $estadoUpper = strtoupper(trim($nuevoEstado));

        if (!in_array($estadoUpper, self::ESTADOS_VALIDOS, true)) {
            throw new InvalidArgumentException("El estado '{$nuevoEstado}' no es válido. Debe ser APROBADO, RECHAZADO o PENDIENTE.");
        }

        $conexion = DB::connection('supervisor');

        // Verificar existencia en saneamiento_base
        $registro = $conexion->table('saneamiento_base')->where('id', $id)->first();
        if (!$registro) {
            throw new RuntimeException("No se encontró el registro de saneamiento con ID {$id}.");
        }

        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $revisadoAt = ($estadoUpper !== 'PENDIENTE') ? $nowBolivia : null;

        $updateData = [
            'estado_revision' => $estadoUpper,
            'revisado_at' => $revisadoAt,
            'updated_at' => $nowBolivia,
        ];

        // Actualizar estrictamente y únicamente en saneamiento_base (sin tocar pan_ruteo)
        $conexion->table('saneamiento_base')
            ->where('id', $id)
            ->update($updateData);

        return [
            'success' => true,
            'id' => $id,
            'estado_revision' => $estadoUpper,
            'revisado_at' => $revisadoAt,
            'message' => "Registro #{$id} actualizado a estado {$estadoUpper} correctamente.",
        ];
    }
}

