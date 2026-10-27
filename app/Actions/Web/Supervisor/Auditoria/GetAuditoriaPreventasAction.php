<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use Illuminate\Support\Facades\DB;

class GetAuditoriaPreventasAction
{
    public function __construct(
        protected EnriquecerFotosAction $enriquecerFotos
    ) {}

    /**
     * Consulta las novedades de preventas auditadas (auditoria_preventas_cambios).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function execute(array $params = []): array
    {
        $tipoEvento = strtoupper(trim((string) ($params['tipo_evento'] ?? 'TODOS')));
        $fechaInicio = trim((string) ($params['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($params['fecha_fin'] ?? ''));
        $busqueda = trim((string) ($params['busqueda'] ?? ''));
        $limit = isset($params['limit']) && is_numeric($params['limit']) && (int) $params['limit'] > 0
            ? min((int) $params['limit'], 2000)
            : 500;

        $conn = DB::connection('supervisor');
        $query = $conn->table('auditoria_preventas_cambios')
            ->select([
                'id',
                'archivo_origen',
                'tipo_evento',
                'fecha_preventa',
                'nro_preventa',
                'producto_id',
                'cliente_id',
                DB::raw("COALESCE(cliente, '') AS cliente"),
                DB::raw("COALESCE(vendedor, '') AS vendedor"),
                'cantidad_anterior',
                'cantidad_nueva',
                'diferencia_cantidad',
                'monto_anterior',
                'monto_nuevo',
                'diferencia_monto',
                'estado_autorizacion',
                'detectado_en',
            ]);

        if ($tipoEvento !== '' && $tipoEvento !== 'TODOS') {
            $query->whereRaw('UPPER(tipo_evento) = ?', [$tipoEvento]);
        }

        if ($fechaInicio !== '') {
            $query->whereDate('fecha_preventa', '>=', $fechaInicio);
        }

        if ($fechaFin !== '') {
            $query->whereDate('fecha_preventa', '<=', $fechaFin);
        }

        if ($busqueda !== '') {
            $term = '%' . strtolower($busqueda) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(cliente) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(vendedor) LIKE ?', [$term])
                  ->orWhereRaw('CAST(nro_preventa AS CHAR) LIKE ?', [$term])
                  ->orWhereRaw('CAST(cliente_id AS CHAR) LIKE ?', [$term])
                  ->orWhereRaw('CAST(producto_id AS CHAR) LIKE ?', [$term]);
            });
        }

        $rawRegistros = $query->orderByDesc('id')
            ->limit($limit)
            ->get();

        $totalEventos = $rawRegistros->count();
        $totalCancelados = 0;
        $totalModificados = 0;
        $montoCanceladoTotal = 0.0;
        $montoDiferenciaModificados = 0.0;

        $registrosFormateados = [];
        foreach ($rawRegistros as $r) {
            $ev = strtoupper(trim((string) ($r->tipo_evento ?? '')));
            $montoAnt = (float) ($r->monto_anterior ?? 0.0);
            $difMonto = (float) ($r->diferencia_monto ?? 0.0);

            if ($ev === 'CANCELADO') {
                $totalCancelados++;
                $montoCanceladoTotal += $montoAnt;
            } elseif ($ev === 'MODIFICADO') {
                $totalModificados++;
                $montoDiferenciaModificados += $difMonto;
            }

            $dtStr = (string) ($r->detectado_en ?? '');
            $detectadoFmt = $dtStr !== '' ? substr(str_replace('T', ' ', $dtStr), 0, 19) : '-';

            $registrosFormateados[] = [
                'id' => (int) $r->id,
                'archivo_origen' => (string) ($r->archivo_origen ?? ''),
                'tipo_evento' => $ev,
                'fecha_preventa' => (string) ($r->fecha_preventa ?? '-'),
                'nro_preventa' => $r->nro_preventa,
                'producto_id' => $r->producto_id,
                'cliente_id' => $r->cliente_id,
                'cliente' => (string) ($r->cliente ?? ''),
                'vendedor' => (string) ($r->vendedor ?? ''),
                'cantidad_anterior' => $r->cantidad_anterior,
                'cantidad_nueva' => $r->cantidad_nueva,
                'diferencia_cantidad' => $r->diferencia_cantidad,
                'monto_anterior' => $montoAnt,
                'monto_nuevo' => (float) ($r->monto_nuevo ?? 0.0),
                'diferencia_monto' => $difMonto,
                'estado_autorizacion' => $r->estado_autorizacion,
                'detectado_en' => $detectadoFmt,
            ];
        }

        $registrosFormateados = $this->enriquecerFotos->execute($registrosFormateados, 'cliente_id');

        return [
            'status' => 'success',
            'resumen' => [
                'total_eventos' => $totalEventos,
                'total_cancelados' => $totalCancelados,
                'total_modificados' => $totalModificados,
                'monto_cancelado_total' => round($montoCanceladoTotal, 2),
                'monto_diferencia_modificados' => round($montoDiferenciaModificados, 2),
            ],
            'total' => count($registrosFormateados),
            'registros' => $registrosFormateados,
        ];
    }
}

