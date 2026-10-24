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
     * GET /api/pedidos-rechazados/motivos
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
     * POST /api/pedidos-rechazados
     */
    public function store(
        StorePedidoRechazadoRequest $request,
        StorePedidoRechazadoAction $action
    ): JsonResponse {
        $pedidoRechazado = $action->execute(
            user: $request->user(),
            data: $request->validated(),
            photo: $request->file('photo')
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
    }

    /**
     * Devuelve las preventas pendientes de justificar del vendedor autenticado.
     * GET /api/pedidos-rechazados/preventas-pendientes
     */
    public function preventasPendientes(
        Request $request,
        GetPreventasPendientesAction $action
    ): JsonResponse {
        $fecha = $request->query('fecha_preventa', $request->query('fecha'));
        $clienteId = $request->query('cliente_id');
        $ruta = $request->query('ruta');

        $preventas = $action->execute(
            user: $request->user(),
            fechaPreventaParam: is_string($fecha) ? $fecha : null,
            clienteId: is_string($clienteId) ? $clienteId : null,
            ruta: is_string($ruta) ? $ruta : null
        );

        return response()->json([
            'success' => true,
            'total' => count($preventas),
            'data' => $preventas,
        ], 200);
    }

    /**
     * Consulta el historial de pedidos rechazados del usuario autenticado.
     * GET /api/pedidos-rechazados/historial
     */
    public function historial(
        Request $request,
        GetHistorialPedidosRechazadosAction $action
    ): JsonResponse {
        $fecha = (string) $request->input('fecha', date('Y-m-d'));
        $rechazos = $action->execute($request->user(), $fecha);

        return response()->json([
            'success' => true,
            'fecha' => $fecha,
            'total' => $rechazos->count(),
            'data' => $rechazos,
            'rechazos' => $rechazos,
        ], 200);
    }
}
