<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ResolverDuplicadosClienteAction
{
    /**
     * Resuelve de forma atómica las solicitudes duplicadas/múltiples de un cliente:
     * - Opcionalmente aprueba una solicitud específica ($aprobarId).
     * - Opcionalmente rechaza una o varias solicitudes ($rechazarIds).
     * - Opcionalmente restablece a pendiente una o varias solicitudes ($pendienteIds).
     *
     * @param int|null $aprobarId
     * @param int[] $rechazarIds
     * @param int[] $pendienteIds
     * @param string|null $motivo
     * @return array{
     *     success: bool,
     *     aprobar_id: int|null,
     *     rechazados: int[],
     *     pendientes: int[],
     *     revisado_at: string|null,
     *     revisor_id: int|null,
     *     revisor_nombre: string|null,
     *     message: string
     * }
     */
    public function execute(?int $aprobarId = null, array $rechazarIds = [], array $pendienteIds = [], ?string $motivo = null): array
    {
        $conexion = DB::connection('supervisor');
        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $revisorId = auth()->id();
        $revisorNombre = auth()->user()?->username;

        $validRechazarIds = array_values(array_unique(array_filter(
            array_map('intval', $rechazarIds),
            fn(int $id) => $id > 0 && ($aprobarId === null || $id !== $aprobarId)
        )));

        $validPendienteIds = array_values(array_unique(array_filter(
            array_map('intval', $pendienteIds),
            fn(int $id) => $id > 0 && ($aprobarId === null || $id !== $aprobarId) && !in_array($id, $validRechazarIds, true)
        )));

        $conexion->transaction(function () use ($conexion, $aprobarId, $validRechazarIds, $validPendienteIds, $nowBolivia, $revisorId): void {
            if ($aprobarId && $aprobarId > 0) {
                $conexion->table('saneamiento_base')
                    ->where('id', $aprobarId)
                    ->update([
                        'estado_revision' => 'APROBADO',
                        'revisado_at' => $nowBolivia,
                        'revisor_id' => $revisorId,
                        'updated_at' => $nowBolivia,
                    ]);
            }

            if (!empty($validRechazarIds)) {
                $conexion->table('saneamiento_base')
                    ->whereIn('id', $validRechazarIds)
                    ->update([
                        'estado_revision' => 'RECHAZADO',
                        'revisado_at' => $nowBolivia,
                        'revisor_id' => $revisorId,
                        'updated_at' => $nowBolivia,
                    ]);
            }

            if (!empty($validPendienteIds)) {
                $conexion->table('saneamiento_base')
                    ->whereIn('id', $validPendienteIds)
                    ->update([
                        'estado_revision' => 'PENDIENTE',
                        'revisado_at' => null,
                        'revisor_id' => null,
                        'updated_at' => $nowBolivia,
                    ]);
            }
        });

        $mensajes = [];
        if ($aprobarId && $aprobarId > 0) {
            $mensajes[] = "Solicitud #{$aprobarId} APROBADA";
        }
        if (!empty($validRechazarIds)) {
            $mensajes[] = count($validRechazarIds) . " rechazada(s)";
        }
        if (!empty($validPendienteIds)) {
            $mensajes[] = count($validPendienteIds) . " restablecida(s) a pendiente";
        }

        return [
            'success' => true,
            'aprobar_id' => $aprobarId,
            'rechazados' => $validRechazarIds,
            'pendientes' => $validPendienteIds,
            'revisado_at' => $nowBolivia,
            'revisor_id' => $revisorId,
            'revisor_nombre' => $revisorNombre,
            'message' => 'Duplicados resueltos: ' . implode(', ', $mensajes) . '.',
        ];
    }
}
