<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Ediciones\GetDetalleClienteEdicionAction;
use App\Actions\Api\Saneamiento\StoreSaneamientoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Saneamiento\StoreSaneamientoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaneamientoController extends Controller
{
    /**
     * Retorna el detalle unificado y desglosado de un cliente para la pantalla de Edición en Android.
     * GET /api/clientes/{clienteId} o GET /api/ediciones/cliente/{clienteId}
     */
    public function showCliente(
        int|string $clienteId,
        GetDetalleClienteEdicionAction $action
    ): JsonResponse {
        $id = is_numeric($clienteId) ? (int) $clienteId : 0;
        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Identificador de cliente inválido.',
            ], 400);
        }

        $cliente = $action->execute($id);
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => "Cliente #{$id} no encontrado en el padrón.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cliente,
            'cliente' => $cliente,
        ], 200);
    }

    /**
     * Registra una alta o edición de cliente en campo para saneamiento de base.
     * POST /api/saneamiento (y /api/ediciones, /api/ediciones/store, /api/saneamiento-base)
     */
    public function store(
        StoreSaneamientoRequest $request,
        StoreSaneamientoAction $action
    ): JsonResponse {
        $user = $request->user();
        $result = $action->execute(
            user: $user,
            validated: $request->validated(),
            photo: $request->file('photo')
        );

        Log::info("Saneamiento API: {$result['tipo_registro']} #{$result['id']} procesada con éxito", [
            'user_id' => $user?->id,
            'cliente_id' => $request->input('cliente_id'),
            'route' => $request->input('route'),
            'cliente' => $request->input('cliente'),
        ]);

        return response()->json([
            'message' => 'Registro de saneamiento procesado correctamente.',
            'id' => $result['id'],
            'tipo_registro' => $result['tipo_registro'],
            'zona' => $result['zona'],
            'zona_comercial' => $result['zona_comercial'],
            'territorio' => $result['territorio'],
            'photo_url' => $result['photo_url'],
        ], 201);
    }
}
