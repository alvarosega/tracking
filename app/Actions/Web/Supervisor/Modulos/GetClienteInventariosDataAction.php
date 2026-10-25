<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Modulos;

use App\Models\ClienteInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetClienteInventariosDataAction
{
    /**
     * Consulta con paginación los inventarios de clientes tomados en campo.
     *
     * @param string|null $fecha
     * @param string|null $ruta
     * @param string|int|null $clienteId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function execute(
        ?string $fecha = null,
        ?string $ruta = null,
        string|int|null $clienteId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = ClienteInventario::with('items')
            ->orderByDesc('fecha_registro');

        if ($fecha) {
            $query->whereDate('fecha_registro', $fecha);
        }

        if ($ruta) {
            $query->where('ruta', $ruta);
        }

        if ($clienteId) {
            $query->where('cliente_id', $clienteId);
        }

        return $query->paginate($perPage);
    }
}

