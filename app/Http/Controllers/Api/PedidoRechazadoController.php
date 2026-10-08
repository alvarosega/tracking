<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\StorePedidoRechazadoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePedidoRechazadoRequest;
use App\Models\PedidoRechazado;
use App\Models\Supervisor\FactPreventa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoRechazadoController extends Controller
{
    /**
     * Catálogo maestro de motivos de rechazo estandarizados.
     */
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
     * Devuelve el catálogo de motivos de rechazo para poblar el selector en la app Android.
     */
    public function motivos(): JsonResponse
    {
        return response()->json([
            'motivos' => self::MOTIVOS_RECHAZO,
        ]);
    }

    /**
     * Registra un pedido rechazado con sus ítems y evidencia fotográfica opcional.
     */
    public function store(StorePedidoRechazadoRequest $request, StorePedidoRechazadoAction $action): JsonResponse
    {
        $user = $request->user();
        $photo = $request->file('photo');

        $pedidoRechazado = $action->execute(
            user: $user,
            data: $request->validated(),
            photo: $photo
        );

        return response()->json([
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
     * Permite a la app Android consultar las preventas y sus productos asociados para auditar el rechazo.
     */
    public function preventasPendientes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'ruta' => ['nullable', 'string', 'max:50'],
            'fecha' => ['nullable', 'date'],
        ]);

        $query = FactPreventa::query();

        if (!empty($validated['cliente_id'])) {
            $query->where('cliente_id', $validated['cliente_id']);
        }

        if (!empty($validated['ruta'])) {
            $query->where('ruta', $validated['ruta']);
        }

        if (!empty($validated['fecha'])) {
            $query->where('fecha_norm', $validated['fecha']);
        } else {
            // Por defecto, buscar la fecha más reciente con preventas
            $maxFecha = FactPreventa::query()->max('fecha_norm');
            if ($maxFecha) {
                $query->where('fecha_norm', $maxFecha);
            }
        }

        $items = $query->select([
            'id', 'nro_preventa', 'cliente_id', 'codigo_cliente', 'cliente',
            'vendedor', 'ruta', 'producto_id', 'codigo_producto', 'producto',
            'categoria', 'cantidad', 'precio_lista', 'monto', 'descuento', 'monto_final',
            'fecha_norm', 'facturar', 'nro_carga'
        ])->get();

        // Agrupar por nro_preventa
        $preventasAgrupadas = [];
        foreach ($items as $item) {
            $nro = $item->nro_preventa ?: $item->id;
            if (!isset($preventasAgrupadas[$nro])) {
                $preventasAgrupadas[$nro] = [
                    'nro_preventa' => $nro,
                    'cliente_id' => $item->cliente_id,
                    'codigo_cliente' => $item->codigo_cliente,
                    'cliente' => $item->cliente,
                    'vendedor' => $item->vendedor,
                    'ruta' => $item->ruta,
                    'fecha_preventa' => $item->fecha_norm,
                    'monto_total' => 0.0,
                    'total_items' => 0,
                    'items' => [],
                ];
            }

            $preventasAgrupadas[$nro]['monto_total'] += (float) $item->monto_final;
            $preventasAgrupadas[$nro]['total_items'] += (int) $item->cantidad;

            $preventasAgrupadas[$nro]['items'][] = [
                'preventa_item_id' => $item->id,
                'producto_id' => $item->producto_id,
                'codigo_producto' => $item->codigo_producto,
                'producto_nombre' => $item->producto,
                'categoria' => $item->categoria,
                'cantidad_preventa' => (int) $item->cantidad,
                'precio_unitario' => (float) ($item->precio_lista ?: ($item->cantidad > 0 ? $item->monto_final / $item->cantidad : 0.0)),
                'monto_final' => (float) $item->monto_final,
            ];
        }

        return response()->json([
            'total_preventas' => count($preventasAgrupadas),
            'preventas' => array_values($preventasAgrupadas),
        ]);
    }

    /**
     * Consulta el historial de pedidos rechazados registrados por el usuario autenticado.
     */
    public function historial(Request $request): JsonResponse
    {
        $user = $request->user();
        $fecha = $request->input('fecha', date('Y-m-d'));

        $rechazos = PedidoRechazado::with('items')
            ->where('user_id', $user->id)
            ->whereDate('fecha_rechazo', $fecha)
            ->orderByDesc('fecha_rechazo')
            ->get();

        return response()->json([
            'fecha' => $fecha,
            'total' => $rechazos->count(),
            'rechazos' => $rechazos,
        ]);
    }
}

