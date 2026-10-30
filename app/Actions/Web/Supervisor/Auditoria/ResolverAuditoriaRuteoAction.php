<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResolverAuditoriaRuteoAction
{
    /**
     * Actualiza el dictamen de auditoría en auditoria_ruteo_cambios.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload): array
    {
        $rawIds = $payload['ids'] ?? [];
        if (is_numeric($rawIds)) {
            $rawIds = [(int) $rawIds];
        } elseif (empty($rawIds) && isset($payload['id']) && is_numeric($payload['id'])) {
            $rawIds = [(int) $payload['id']];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $rawIds))));
        if (empty($ids)) {
            throw new InvalidArgumentException('Se requiere al menos un ID válido para resolver auditoría.');
        }

        $nuevoEstado = strtoupper(trim((string) ($payload['estado'] ?? 'APROBADO')));
        if ($nuevoEstado === 'DESCARTE') {
            $nuevoEstado = 'DESCARTADO';
        }

        $permitidos = ['APROBADO', 'RECHAZADO', 'DESCARTADO'];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new InvalidArgumentException("Estado inválido '{$nuevoEstado}'. Estados permitidos: " . implode(', ', $permitidos) . '.');
        }

        $observaciones = trim((string) ($payload['observaciones'] ?? ''));
        if ($observaciones === '') {
            $observaciones = "Resolución de auditoría: {$nuevoEstado}";
        }

        $auditadoPor = trim((string) ($payload['auditado_por'] ?? 'SUPERVISOR'));
        if ($auditadoPor === '') {
            $auditadoPor = 'SUPERVISOR';
        }

        $conn = DB::connection('supervisor');
        $afectados = $conn->table('auditoria_ruteo_cambios')
            ->whereIn('id', $ids)
            ->update([
                'estado_auditoria' => $nuevoEstado,
                'observaciones' => $observaciones,
                'auditado_por' => $auditadoPor,
                'fecha_resolucion' => now(),
            ]);

        return [
            'status' => 'success',
            'afectados' => $afectados,
            'estado' => $nuevoEstado,
            'mensaje' => "Se actualizaron {$afectados} registro(s) a estado {$nuevoEstado}.",
        ];
    }
}

