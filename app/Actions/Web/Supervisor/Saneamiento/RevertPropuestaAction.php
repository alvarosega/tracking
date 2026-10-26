<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class RevertPropuestaAction
{
    /**
     * Elimina físicamente una propuesta en estado PENDIENTE.
     *
     * @param int $propuestaId
     * @param string|null $tipoHallazgo
     * @return bool
     */
    public function execute(int $propuestaId, ?string $tipoHallazgo = null): bool
    {
        $query = DB::connection('supervisor')
            ->table('auditoria_saneamiento_propuestas')
            ->where('id', $propuestaId)
            ->where('estado', 'PENDIENTE');

        if ($tipoHallazgo && strtoupper($tipoHallazgo) !== 'TODOS') {
            $query->where('tipo_hallazgo', strtoupper(trim($tipoHallazgo)));
        }

        return $query->delete() > 0;
    }
}

