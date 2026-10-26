<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeDiaPropuestaAction
{
    /**
     * Actualiza el día propuesto en una propuesta existente.
     *
     * @param int $propuestaId
     * @param string $nuevoDia
     * @return bool
     */
    public function execute(int $propuestaId, string $nuevoDia): bool
    {
        $dia = strtoupper(trim($nuevoDia));
        if ($propuestaId <= 0 || empty($dia)) {
            throw new InvalidArgumentException('Propuesta ID y día propuesto son requeridos.');
        }

        $affected = DB::connection('supervisor')
            ->table('auditoria_saneamiento_propuestas')
            ->where('id', $propuestaId)
            ->update(['dia_propuesto' => $dia]);

        return $affected > 0;
    }
}

