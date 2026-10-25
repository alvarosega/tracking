<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Visitas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GetFotosClienteAction
{
    /**
     * Obtiene el histórico cronológico de visitas con evidencia fotográfica de un cliente.
     *
     * @param int $clienteId
     * @return Collection
     */
    public function execute(int $clienteId): Collection
    {
        return DB::table('visitas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->where('v.client_id', $clienteId)
            ->whereNotNull('v.photo_path')
            ->where('v.photo_path', '!=', '')
            ->select([
                'v.id',
                'v.route',
                'v.status',
                'v.comments',
                'v.visited_at',
                'v.photo_path',
                'u.username as vendedor',
            ])
            ->orderByDesc('v.visited_at')
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'ruta' => $v->route,
                    'status' => $v->status,
                    'comments' => $v->comments,
                    'fecha' => date('d/m/Y', strtotime((string) $v->visited_at)),
                    'hora' => date('H:i', strtotime((string) $v->visited_at)),
                    'vendedor' => $v->vendedor ?: 'Sin asignar',
                    'photo_url' => Storage::url($v->photo_path),
                ];
            });
    }
}

