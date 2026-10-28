<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\PedidosRechazados\GetHistorialPedidosRechazadosAction;
use App\Actions\Api\PedidosRechazados\GetPreventasPendientesAction;
use App\Actions\Api\PedidosRechazados\StorePedidoRechazadoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PedidosRechazados\StorePedidoRechazadoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PedidoRechazadoController extends Controller
{
    public const MOTIVOS_RECHAZO = [
        ['id' => 1, 'codigo' => 'TIENDA_CERRADA', 'nombre' => 'Tienda cerrada / No abrió'],
        ['id' => 2, 'codigo' => 'SIN_DINERO', 'nombre' => 'Sin dinero / Falta de liquidez'],
        ['id' => 3, 'codigo' => 'ERROR_PREVENTA', 'nombre' => 'Cliente no realizó el pedido (Error preventa)'],
        ['id' => 4, 'codigo' => 'DUENO_AUSENTE', 'nombre' => 'Encargado o dueño ausente'],
        ['id' => 5, 'codigo' => 'PRECIO_DISCONFORME', 'nombre' => 'Inconformidad con precio o descuento'],
        ['id' => 6, 'codigo' => 'PRODUCTO_DANADO', 'nombre' => 'Producto dañado o en mal estado'],
        ['id' => 7, 'codigo' => 'PEDIDO_DUPLICADO', 'nombre' => 'Pedido duplicado'],
        ['id' => 8, 'codigo' => 'FALTA_ESPACIO', 'nombre' => 'Falta de espacio / Stock suficiente'],
        ['id' => 9, 'codigo' => 'OTRO', 'nombre' => 'Otro motivo (especificar en comentarios)'],
    ];

    /**
     * Catálogo de motivos estandarizados para poblar el selector en Android.
     * GET /api/pedidos-rechazados/motivos o GET /api/pedido-rechazado/motivos
     */
    public function motivos(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'motivos' => self::MOTIVOS_RECHAZO,
        ]);
    }

    /**
     * Registra un pedido rechazado con sus ítems y evidencia fotográfica opcional.
     * POST /api/pedidos-rechazados o POST /api/pedido-rechazado
     */
    public function store(
        StorePedidoRechazadoRequest $request,
        StorePedidoRechazadoAction $action
    ): JsonResponse {
        try {
            $user = $request->user();
            $photo = $request->file('photo')
                ?? $request->file('foto')
                ?? $request->file('image')
                ?? $request->file('imagen')
                ?? $request->file('file')
                ?? $request->file('evidence');

            $pedidoRechazado = $action->execute(
                user: $user,
                data: $request->validated(),
                photo: $photo
            );

            return response()->json([
                'success' => true,
                'message' => 'Pedido rechazado registrado exitosamente',
                'id' => $pedidoRechazado->id,
                'uuid' => $pedidoRechazado->uuid,
                'photo_url' => $pedidoRechazado->photo_url,
                'total_items_rechazados' => $pedidoRechazado->total_items_rechazados,
                'monto_total_rechazado' => (float) $pedidoRechazado->monto_total_rechazado,
                'data' => $pedidoRechazado,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error registrando pedido rechazado en servidor: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $request->user()?->id,
                'payload' => $request->except(['photo', 'foto', 'image', 'file', 'items']),
                'items_count' => count((array) $request->input('items', [])),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pedido rechazado en el servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devuelve las preventas pendientes de justificar del vendedor autenticado.
     * GET /api/pedidos-rechazados/preventas-pendientes
     */
    public function preventasPendientes(
        Request $request,
        GetPreventasPendientesAction $action
    ): JsonResponse {
        try {
            $fecha = $request->input('fecha_preventa', $request->input('fecha'));
            $fechaVenta = $request->input('fecha_venta', $request->input('fecha_entrega'));
            $clienteId = $request->input('cliente_id', $request->input('client_id'));
            $ruta = $request->input('ruta', $request->input('route'));

            $preventas = $action->execute(
                user: $request->user(),
                fechaPreventaParam: is_string($fecha) && $fecha !== 'null' && $fecha !== '' ? $fecha : null,
                fechaVentaParam: is_string($fechaVenta) && $fechaVenta !== 'null' && $fechaVenta !== '' ? $fechaVenta : null,
                clienteId: is_string($clienteId) || is_numeric($clienteId) ? $clienteId : null,
                ruta: is_string($ruta) && $ruta !== 'null' && $ruta !== '' ? $ruta : null
            );

            return response()->json([
                'success' => true,
                'total' => count($preventas),
                'data' => $preventas,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error consultando preventas pendientes: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener preventas pendientes: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Consulta el historial de pedidos rechazados del usuario autenticado.
     * GET /api/pedidos-rechazados/historial
     */
    public function historial(
        Request $request,
        GetHistorialPedidosRechazadosAction $action
    ): JsonResponse {
        try {
            $fechaInput = $request->input('fecha', $request->input('fecha_rechazo'));
            $fecha = (is_string($fechaInput) && $fechaInput !== 'null' && $fechaInput !== '') ? $fechaInput : null;
            $clienteId = $request->input('cliente_id', $request->input('client_id'));

            $rechazos = $action->execute(
                user: $request->user(),
                fecha: $fecha,
                clienteId: is_string($clienteId) || is_numeric($clienteId) ? $clienteId : null
            );

            return response()->json([
                'success' => true,
                'fecha' => $fecha,
                'total' => $rechazos->count(),
                'data' => $rechazos,
                'rechazos' => $rechazos,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error consultando historial de pedidos rechazados: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de pedidos rechazados: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
