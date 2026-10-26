<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use Illuminate\Support\Facades\DB;
use Throwable;

class GetCanceladosPendientesAction
{
    /**
     * Consulta los pedidos de preventa cancelados pendientes de autorización.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array
    {
        try {
            $conn = DB::connection('supervisor');

            $rows = $conn->select("
                SELECT apc.id, apc.archivo_origen, apc.nro_preventa, apc.producto_id,
                       apc.fecha_preventa, apc.cliente_id, apc.cliente, apc.vendedor,
                       apc.cantidad_anterior, apc.monto_anterior, apc.detectado_en,
                       COALESCE(fp.ruta, pr.ruta, 'SIN RUTA') AS ruta,
                       COALESCE(fp.producto, CONCAT('Producto #', apc.producto_id)) AS producto
                FROM auditoria_preventas_cambios apc
                LEFT JOIN fact_preventas fp 
                       ON fp.nro_preventa = apc.nro_preventa 
                      AND fp.producto_id = apc.producto_id
                      AND fp.fecha_norm = apc.fecha_preventa
                LEFT JOIN pan_ruteo pr 
                       ON pr.cliente_id = apc.cliente_id
                WHERE apc.tipo_evento = 'CANCELADO' 
                  AND apc.estado_autorizacion = 'PENDIENTE'
                ORDER BY apc.id DESC
                LIMIT 500
            ");

            return array_map(function ($r) {
                return [
                    'id' => (int)$r->id,
                    'nro_preventa' => (int)$r->nro_preventa,
                    'producto_id' => (int)$r->producto_id,
                    'fecha_norm' => (string)($r->fecha_preventa ?? '-'),
                    'fecha_preventa' => (string)($r->fecha_preventa ?? '-'),
                    'cliente_id' => $r->cliente_id ? (int)$r->cliente_id : null,
                    'cliente' => (string)($r->cliente ?? 'Cliente Desconocido'),
                    'vendedor' => (string)($r->vendedor ?? 'Sin Vendedor'),
                    'ruta' => (string)($r->ruta ?? 'SIN RUTA'),
                    'producto' => (string)($r->producto ?? "Producto #{$r->producto_id}"),
                    'cantidad_anterior' => (int)($r->cantidad_anterior ?? 0),
                    'monto_final' => (float)($r->monto_anterior ?? 0.0),
                    'monto_anterior' => (float)($r->monto_anterior ?? 0.0),
                    'archivo_origen' => (string)($r->archivo_origen ?? '-'),
                    'detectado_en' => (string)($r->detectado_en ?? '-'),
                ];
            }, $rows);
        } catch (Throwable) {
            return [];
        }
    }
}

