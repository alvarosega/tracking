<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class ListPropuestasAction
{
    /**
     * Consulta genérica unificada de propuestas de saneamiento.
     *
     * @param string|null $tipoHallazgo
     * @param array<string>|null $rutas
     * @param string|null $estado
     * @param bool $soloUltimasPorCliente
     * @param int $limit
     * @return array<array<string, mixed>>
     */
    public function execute(
        ?string $tipoHallazgo = null,
        ?array $rutas = null,
        ?string $estado = 'PENDIENTE',
        bool $soloUltimasPorCliente = false,
        int $limit = 300
    ): array {
        $conn = DB::connection('supervisor');

        if ($soloUltimasPorCliente) {
            $latestSub = $conn->table('auditoria_saneamiento_propuestas')
                ->select([
                    'cliente_id_origen',
                    'tipo_hallazgo',
                    DB::raw('MAX(creado_en) as max_creado'),
                ])
                ->groupBy('cliente_id_origen', 'tipo_hallazgo');

            $query = $conn->table('auditoria_saneamiento_propuestas as p')
                ->joinSub($latestSub, 'latest', function ($join) {
                    $join->on('p.cliente_id_origen', '=', 'latest.cliente_id_origen')
                        ->on('p.tipo_hallazgo', '=', 'latest.tipo_hallazgo')
                        ->on('p.creado_en', '=', 'latest.max_creado');
                });
        } else {
            $query = $conn->table('auditoria_saneamiento_propuestas as p');
        }

        $query->select([
            'p.id',
            'p.tipo_hallazgo',
            'p.cliente_id_origen',
            'p.cliente_id_vinculado',
            DB::raw("COALESCE(p.cliente_nombre, 'Sin Nombre') as cliente_nombre"),
            'p.ruta_actual',
            'p.dia_actual',
            'p.ruta_propuesta',
            'p.dia_propuesto',
            'p.accion_propuesta',
            'p.distancia_metros',
            'p.estado',
            'p.observaciones',
            'p.creado_en',
        ]);

        if ($tipoHallazgo && strtoupper($tipoHallazgo) !== 'TODOS') {
            $query->where('p.tipo_hallazgo', strtoupper(trim($tipoHallazgo)));
        }

        if ($estado && strtoupper($estado) !== 'TODOS') {
            $query->where('p.estado', strtoupper(trim($estado)));
        }

        if (!empty($rutas)) {
            $rutasNorm = array_map(fn($r) => strtoupper(trim((string) $r)), array_filter($rutas));
            if (!empty($rutasNorm)) {
                $query->where(function ($w) use ($rutasNorm) {
                    $w->whereIn(DB::raw("TRIM(UPPER(p.ruta_actual))"), $rutasNorm)
                        ->orWhereIn(DB::raw("TRIM(UPPER(p.ruta_propuesta))"), $rutasNorm);
                });
            }
        }

        $query->orderByDesc('p.id')->limit($limit);

        return $query->get()->map(fn($r) => [
            'id' => (int) $r->id,
            'tipo_hallazgo' => (string) $r->tipo_hallazgo,
            'cliente_id_origen' => (int) $r->cliente_id_origen,
            'cliente_id_vinculado' => $r->cliente_id_vinculado ? (int) $r->cliente_id_vinculado : null,
            'cliente_nombre' => (string) $r->cliente_nombre,
            'ruta_actual' => (string) $r->ruta_actual,
            'dia_actual' => (string) $r->dia_actual,
            'ruta_propuesta' => (string) $r->ruta_propuesta,
            'dia_propuesto' => (string) $r->dia_propuesto,
            'accion_propuesta' => (string) $r->accion_propuesta,
            'distancia_metros' => (float) $r->distancia_metros,
            'estado' => (string) $r->estado,
            'observaciones' => (string) $r->observaciones,
            'creado_en' => $r->creado_en ? date('d/m/Y H:i', strtotime((string) $r->creado_en)) : null,
        ])->all();
    }
}

