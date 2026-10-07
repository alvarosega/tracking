<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\Visita;

class GetVisitasClienteAction
{
    /**
     * Obtiene el historial de visitas realizadas a un cliente con evidencias fotográficas.
     *
     * @param string|int $clienteId
     * @return array{cliente_id: string|int, total_visitas: int, visitas: array}
     */
    public function execute(string|int $clienteId): array
    {
        $visitas = Visita::query()
            ->where('client_id', $clienteId)
            ->orderByDesc('visited_at')
            ->get();

        $resultado = $visitas->map(function ($v) {
            return [
                'id' => $v->id,
                'client_id' => $v->client_id,
                'route' => $v->route,
                'status' => $v->status,
                'comments' => $v->comments,
                'latitude' => $v->latitude !== null ? (float) $v->latitude : null,
                'longitude' => $v->longitude !== null ? (float) $v->longitude : null,
                'accuracy' => $v->accuracy !== null ? (float) $v->accuracy : null,
                'visited_at' => $v->visited_at ? $v->visited_at->format('Y-m-d H:i:s') : null,
                'fecha' => $v->visited_at ? $v->visited_at->format('d/m/Y') : null,
                'hora' => $v->visited_at ? $v->visited_at->format('H:i') : null,
                'photo_url' => $v->photo_url,
            ];
        });

        return [
            'cliente_id' => $clienteId,
            'total_visitas' => $resultado->count(),
            'visitas' => $resultado->values()->toArray(),
        ];
    }
}

