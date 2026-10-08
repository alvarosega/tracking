<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\StorePedidoRechazadoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePedidoRechazadoRequest;
use App\Models\PedidoRechazado;
use App\Models\Supervisor\FactPreventa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'success' => true,
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
     * Ciclo operativo:
     * - Si hoy es Día D (ej. 7):
     * - Ventas Facturadas (Reparto) = Día D-1 (ej. 6)
     * - Preventas Tomadas = Día D-2 (ej. 5)
     */
    public function preventasPendientes(Request $request): JsonResponse
    {
        $user = $request->user();
        $clienteId = $request->query('cliente_id');
        $ruta = $request->query('ruta');

        // Cálculo de fechas por defecto considerando ciclo operativo D-2 (preventa) vs D-1 (facturación)
        $now = now('America/La_Paz');
        
        // Manejo de días hábiles si hoy es Lunes
        if ($now->isMonday()) {
            $defaultFechaVenta = $now->copy()->subDays(2)->toDateString();    // Sábado
            $defaultFechaPreventa = $now->copy()->subDays(3)->toDateString(); // Viernes
        } else {
            $defaultFechaVenta = $now->copy()->subDay()->toDateString();      // Ayer (D-1)
            $defaultFechaPreventa = $now->copy()->subDays(2)->toDateString(); // Anteayer (D-2)
        }

        $fechaPreventa = $request->query('fecha_preventa', $request->query('fecha', $defaultFechaPreventa));
        $fechaVenta = $request->query('fecha_venta', $defaultFechaVenta);

        // 1. Obtener preventas ya justificadas en la BD local por este vendedor
        $preventasYaJustificadas = PedidoRechazado::query()
            ->where('user_id', $user->id)
            ->whereNotNull('nro_preventa')
            ->pluck('nro_preventa')
            ->toArray();

        // 2. Obtener preventas que SÍ fueron facturadas en fact_ventas (del día de despacho D-1)
        $ventasQuery = DB::connection('supervisor')
            ->table('fact_ventas')
            ->where('revertida', '!=', 'Si');

        if (!empty($fechaVenta)) {
            $ventasQuery->where('fecha_norm', $fechaVenta);
        }

        $preventasFacturadas = $ventasQuery
            ->whereNotNull('pre_venta_id')
            ->pluck('pre_venta_id')
            ->toArray();

        // 3. Consultar las preventas tomadas en fecha D-2 asignadas al vendedor
        $query = DB::connection('supervisor')
            ->table('fact_preventas as p');

        if (!empty($ruta)) {
            $query->where('p.ruta', $ruta);
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('p.vendedor', $user->name)
                  ->orWhere('p.ruta', $user->username)
                  ->orWhere('p.ruta', $user->ruta ?? null);
            });
        }

        if (!empty($fechaPreventa)) {
            $query->where('p.fecha_norm', $fechaPreventa);
        }

        if (!empty($clienteId)) {
            $query->where('p.cliente_id', $clienteId);
        }

        if (!empty($preventasYaJustificadas)) {
            $query->whereNotIn('p.nro_preventa', $preventasYaJustificadas);
        }

        if (!empty($preventasFacturadas)) {
            $query->whereNotIn('p.nro_preventa', $preventasFacturadas);
        }

        $items = $query->select([
            'p.id as preventa_item_id',
            'p.nro_preventa',
            'p.fecha_norm as fecha_preventa',
            'p.cliente_id',
            'p.codigo_cliente',
            'p.cliente as cliente_nombre',
            'p.ruta',
            'p.vendedor',
            'p.producto_id',
            'p.codigo_producto',
            'p.producto as producto_nombre',
            'p.categoria',
            'p.cantidad as cantidad_preventa',
            'p.precio_lista',
            'p.monto_final'
        ])->get();

        // 4. Agrupar ítems por nro_preventa
        $preventasAgrupadas = [];
        foreach ($items as $item) {
            $nro = (string) ($item->nro_preventa ?: $item->preventa_item_id);

            if (!isset($preventasAgrupadas[$nro])) {
                $preventasAgrupadas[$nro] = [
                    'nro_preventa'   => $nro,
                    'fecha_preventa' => $item->fecha_preventa,
                    'cliente_id'     => (string) $item->cliente_id,
                    'codigo_cliente' => $item->codigo_cliente,
                    'cliente_nombre' => $item->cliente_nombre,
                    'ruta'           => $item->ruta,
                    'vendedor'       => $item->vendedor,
                    'monto_total'    => 0.0,
                    'total_items'    => 0,
                    'items'          => []
                ];
            }

            $preventasAgrupadas[$nro]['monto_total'] += (float) $item->monto_final;
            $preventasAgrupadas[$nro]['total_items'] += (int) $item->cantidad_preventa;

            $precioUnit = (float) ($item->precio_lista ?: ($item->cantidad_preventa > 0 ? $item->monto_final / $item->cantidad_preventa : 0));

            $preventasAgrupadas[$nro]['items'][] = [
                'preventa_item_id'  => $item->preventa_item_id,
                'producto_id'       => $item->producto_id,
                'codigo_producto'   => $item->codigo_producto,
                'producto_nombre'   => $item->producto_nombre,
                'categoria'         => $item->categoria,
                'cantidad_preventa' => (int) $item->cantidad_preventa,
                'precio_unitario'   => round($precioUnit, 2),
                'monto_total'       => (float) $item->monto_final,
            ];
        }

        return response()->json([
            'success' => true,
            'total'   => count($preventasAgrupadas),
            'data'    => array_values($preventasAgrupadas)
        ], 200);
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

