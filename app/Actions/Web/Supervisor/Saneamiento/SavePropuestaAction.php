<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class SavePropuestaAction
{
    /**
     * Inserta o actualiza una propuesta en auditoria_saneamiento_propuestas de forma idempotente.
     *
     * @param array $datos
     * @return int ID de la propuesta
     */
    public function execute(array $datos): int
    {
        $conn = DB::connection('supervisor');

        $tipo = strtoupper(trim((string) ($datos['tipo_hallazgo'] ?? 'INTRUSO_FRONTERA')));
        $cidOrigen = (int) ($datos['cliente_id_origen'] ?? $datos['cliente_id'] ?? 0);
        $cidVinculado = !empty($datos['cliente_id_vinculado']) ? (int) $datos['cliente_id_vinculado'] : null;
        $cNom = !empty($datos['cliente_nombre']) ? trim((string) $datos['cliente_nombre']) : (!empty($datos['cliente']) ? trim((string) $datos['cliente']) : null);
        $rAct = strtoupper(trim((string) ($datos['ruta_actual'] ?? $datos['ruta'] ?? '')));
        $dAct = strtoupper(trim((string) ($datos['dia_actual'] ?? $datos['dia'] ?? 'SIN DIA')));
        $rProp = strtoupper(trim((string) ($datos['ruta_propuesta'] ?? $rAct)));
        $dProp = strtoupper(trim((string) ($datos['dia_propuesto'] ?? $dAct)));
        $accion = strtoupper(trim((string) ($datos['accion_propuesta'] ?? 'REASIGNAR')));
        $dist = (float) ($datos['distancia_metros'] ?? 0.0);
        $obs = (string) ($datos['observaciones'] ?? 'Propuesta de saneamiento territorial');

        $queryExistente = $conn->table('auditoria_saneamiento_propuestas')
            ->where('tipo_hallazgo', $tipo)
            ->where('cliente_id_origen', $cidOrigen)
            ->where('estado', 'PENDIENTE');

        if ($tipo === 'DIA_DESALINEADO') {
            $queryExistente->where(DB::raw("TRIM(UPPER(ruta_actual))"), $rAct)
                ->where(DB::raw("TRIM(UPPER(dia_actual))"), $dAct);
        }

        $existente = $queryExistente->first(['id']);

        if ($existente) {
            $propId = (int) $existente->id;
            $conn->table('auditoria_saneamiento_propuestas')
                ->where('id', $propId)
                ->update([
                    'cliente_id_vinculado' => $cidVinculado,
                    'cliente_nombre' => $cNom ?: DB::raw('cliente_nombre'),
                    'ruta_propuesta' => $rProp,
                    'dia_propuesto' => $dProp,
                    'accion_propuesta' => $accion,
                    'distancia_metros' => $dist,
                    'observaciones' => $obs,
                    'creado_en' => now(),
                ]);

            return $propId;
        }

        $id = $conn->table('auditoria_saneamiento_propuestas')->insertGetId([
            'tipo_hallazgo' => $tipo,
            'cliente_id_origen' => $cidOrigen,
            'cliente_id_vinculado' => $cidVinculado,
            'cliente_nombre' => $cNom,
            'ruta_actual' => $rAct,
            'dia_actual' => $dAct,
            'ruta_propuesta' => $rProp,
            'dia_propuesto' => $dProp,
            'accion_propuesta' => $accion,
            'distancia_metros' => $dist,
            'observaciones' => $obs,
            'estado' => 'PENDIENTE',
            'creado_en' => now(),
        ]);

        return (int) $id;
    }
}

