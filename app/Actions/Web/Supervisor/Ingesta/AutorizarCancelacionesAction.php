<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class AutorizarCancelacionesAction
{
    /**
     * Autoriza y purga de fact_preventas una lista de pedidos cancelados.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function execute(array $items): array
    {
        if (empty($items)) {
            throw new Exception("No se enviaron registros de preventa para autorizar.");
        }

        $purgados = 0;
        $conn = DB::connection('supervisor');

        $conn->transaction(function () use ($conn, $items, &$purgados) {
            foreach ($items as $item) {
                $nroPrev = isset($item['nro_preventa']) ? (int)$item['nro_preventa'] : 0;
                $prodId = isset($item['producto_id']) ? (int)$item['producto_id'] : 0;
                $fechaNorm = isset($item['fecha_norm']) ? trim((string)$item['fecha_norm']) : '';

                if ($nroPrev <= 0 || $prodId <= 0) {
                    continue;
                }

                // Purgar de fact_preventas definitivamente tras autorización del supervisor
                $delQuery = $conn->table('fact_preventas')
                    ->where('nro_preventa', $nroPrev)
                    ->where('producto_id', $prodId);

                if ($fechaNorm !== '') {
                    $delQuery->where('fecha_norm', $fechaNorm);
                }
                $delQuery->delete();

                // Actualizar estado en auditoria_preventas_cambios a APROBADO
                $audQuery = $conn->table('auditoria_preventas_cambios')
                    ->where('nro_preventa', $nroPrev)
                    ->where('producto_id', $prodId)
                    ->where('tipo_evento', 'CANCELADO')
                    ->where('estado_autorizacion', 'PENDIENTE');

                if ($fechaNorm !== '') {
                    $audQuery->where('fecha_preventa', $fechaNorm);
                }
                $audQuery->update(['estado_autorizacion' => 'APROBADO']);

                $purgados++;
            }
        });

        return [
            'status' => 'success',
            'purgados' => $purgados,
            'eliminados' => $purgados,
        ];
    }
}

