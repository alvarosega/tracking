<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetHistorialPedidosRechazadosAction
{
    /**
     * Consulta el historial de pedidos rechazados registrados por el usuario para una fecha o cliente.
     *
     * @return Collection<int, PedidoRechazado>
     */
    public function execute(User $user, ?string $fecha = null, int|string|null $clienteId = null): Collection
    {
        $query = PedidoRechazado::with('items')
            ->where('user_id', $user->id);

        if (!empty($fecha) && $fecha !== 'null') {
            $query->whereDate('fecha_rechazo', $fecha);
        }

        if (!empty($clienteId) && $clienteId !== 'null') {
            $query->where('cliente_id', $clienteId);
        }

        return $query->orderByDesc('fecha_rechazo')->get();
    }
}
