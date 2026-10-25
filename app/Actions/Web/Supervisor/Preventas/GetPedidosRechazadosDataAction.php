<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\PedidoRechazado;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPedidosRechazadosDataAction
{
    /**
     * Consulta paginada y filtrada de pedidos rechazados para la interfaz del supervisor.
     *
     * @return LengthAwarePaginator
     */
    public function execute(
        ?string $fecha = null,
        ?string $ruta = null,
        ?string $motivo = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = PedidoRechazado::with(['items', 'user'])
            ->orderByDesc('fecha_rechazo');

        if (!empty($fecha)) {
            $query->whereDate('fecha_rechazo', $fecha);
        }

        if (!empty($ruta)) {
            $query->where('ruta', $ruta);
        }

        if (!empty($motivo)) {
            $query->where('motivo', $motivo);
        }

        return $query->paginate($perPage);
    }
}

