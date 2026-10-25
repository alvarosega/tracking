<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\InventarioCliente\GetClienteInventariosHistorialAction;
use App\Actions\Api\InventarioCliente\GetClienteProductosHistorialAction;
use App\Actions\Api\InventarioCliente\StoreClienteInventarioAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InventarioCliente\StoreClienteInventarioRequest;
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
        $ruta = $request->query('ruta') ?: $request->user()?->ruta;
        $result = $action->execute($clienteId, is_string($ruta) ? $ruta : null);

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
    public function historial(
        Request $request,
        GetClienteInventariosHistorialAction $action
    ): JsonResponse {
        $user = $request->user();
        $fecha = $request->query('fecha');
        $clienteId = $request->query('cliente_id') ? (int) $request->query('cliente_id') : null;
        $perPage = (int) $request->query('per_page', 20);

        $inventarios = $action->execute(
            $user,
            is_string($fecha) ? $fecha : null,
            $clienteId,
            $perPage
        );

        return response()->json([
            'success' => true,
            'total' => $inventarios->total(),
            'current_page' => $inventarios->currentPage(),
            'last_page' => $inventarios->lastPage(),
            'data' => $inventarios->items(),
        ], 200);
    }
}
