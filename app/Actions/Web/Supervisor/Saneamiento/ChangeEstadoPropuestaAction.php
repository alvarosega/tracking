<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeEstadoPropuestaAction
{
    /**
     * Cambia el estado de una o múltiples propuestas (APLICADO, DESCARTADO, PENDIENTE).
     *
     * @param int|array<int> $propuestaIds
     * @param string $nuevoEstado
     * @return int Cantidad de registros afectados
     */
    public function execute(int|array $propuestaIds, string $nuevoEstado): int
    {
        $estado = strtoupper(trim($nuevoEstado));
        if ($estado === 'RECHAZADO') {
            $estado = 'DESCARTADO';
        }

        if (!in_array($estado, ['APLICADO', 'DESCARTADO', 'PENDIENTE'], true)) {
            throw new InvalidArgumentException("Estado inválido: {$nuevoEstado}");
        }

        $ids = is_array($propuestaIds) ? array_map('intval', $propuestaIds) : [(int) $propuestaIds];
        if (empty($ids)) {
            return 0;
        }

        return DB::connection('supervisor')
            ->table('auditoria_saneamiento_propuestas')
            ->whereIn('id', $ids)
            ->update(['estado' => $estado]);
    }
}

