<?php

namespace App\Actions\Api;

use App\Models\ClienteInventario;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetClienteInventariosHistorialAction
{
    /**
     * Consulta el historial de inventarios registrados por el vendedor o ruta con filtros y paginación.
     */
    public function execute(User $user, ?string $fecha = null, ?int $clienteId = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = ClienteInventario::with('items')->orderByDesc('fecha_registro');

        if ($user && $user->ruta) {
            $query->where('ruta', $user->ruta);
        } elseif ($user) {
            $query->where('user_id', $user->id);
        }

        if (!empty($fecha)) {
            $query->whereDate('fecha_registro', $fecha);
        }

        if (!empty($clienteId)) {
            $query->where('cliente_id', $clienteId);
        }

        $safePerPage = min(50, max(1, $perPage));

        return $query->paginate($safePerPage);
    }
}

