<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\GetClienteProductosHistorialAction;
use App\Actions\Api\StoreClienteInventarioAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreClienteInventarioRequest;
use App\Models\ClienteInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteInventarioController extends Controller
{
    /**
     * Devuelve el historial de compras y productos del cliente para el periodo configurado.
     * GET /api/inventarios-cliente/historial-productos/{clienteId}
     */
    public function historialProductos(
        Request $request,
        int|string $clienteId,
        GetClienteProductosHistorialAction $action
    ): JsonResponse {
        $ruta = $request->user()?->ruta;
        $result = $action->execute($clienteId, $ruta);

        return response()->json($result, 200);
    }

    /**
     * Registra la toma de inventario físico en punto de venta.
     * POST /api/inventarios-cliente
     */
    public function store(
        StoreClienteInventarioRequest $request,
        StoreClienteInventarioAction $action
    ): JsonResponse {
        $user = $request->user();
        $result = $action->execute($request->validated(), $user);

        return response()->json($result, 201);
    }

    /**
     * Devuelve el historial de inventarios registrados por el vendedor autenticado.
     * GET /api/inventarios-cliente/historial
     */
    public function historial(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ClienteInventario::with('items')
            ->orderByDesc('fecha_registro');

        // Filtrar por vendedor / ruta si no es administrador global
        if ($user && $user->ruta) {
            $query->where('ruta', $user->ruta);
        } elseif ($user) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha_registro', $request->query('fecha'));
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->query('cliente_id'));
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $inventarios = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'total' => $inventarios->total(),
            'current_page' => $inventarios->currentPage(),
            'last_page' => $inventarios->lastPage(),
            'data' => $inventarios->items(),
        ], 200);
    }
}

