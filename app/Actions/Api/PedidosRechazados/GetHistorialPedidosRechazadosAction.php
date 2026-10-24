<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetHistorialPedidosRechazadosAction
{
    /**
     * Consulta el historial de pedidos rechazados registrados por el usuario para una fecha determinada.
     *
     * @return Collection<int, PedidoRechazado>
     */
    public function execute(User $user, string $fecha): Collection
    {
        return PedidoRechazado::with('items')
            ->where('user_id', $user->id)
            ->whereDate('fecha_rechazo', $fecha)
            ->orderByDesc('fecha_rechazo')
            ->get();
    }
}

