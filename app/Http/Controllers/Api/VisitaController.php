<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Visitas\GetVisitasClientesAction;
use App\Actions\Api\Visitas\StoreVisitaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClienteDetalleFechasAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClientesRutaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Visitas\StoreVisitaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitaController extends Controller
{
    /**
     * Retorna el listado ligero de clientes de la ruta para el submódulo de Visitas en Android.
     * Marca con 'visited' a los que corresponden al día asignado hoy en días de ruteo, y 'unvisited' al resto.
     * GET /api/visitas/clientes
     */
    public function clientes(Request $request, GetVisitasClientesAction $action): JsonResponse
    {
        $user = $request->user();
        $diaParam = $request->query('dia');

        $clientes = $action->execute(
            user: $user,
            diaParam: is_string($diaParam) ? $diaParam : null
        );

        return response()->json($clientes, 200);
    }

    /**
     * Procesa y registra una visita en campo.
     * POST /api/visitas (y /api/visitas/store)
     */
    public function store(StoreVisitaRequest $request, StoreVisitaAction $action): JsonResponse
    {
        $user = $request->user();
        $result = $action->execute(
            user: $user,
            data: $request->validated(),
            photo: $request->file('photo')
        );

        return response()->json([
            'message' => $result['message'],
            'id' => $result['id'],
            'uuid' => $result['uuid'],
            'photo_url' => $result['photo_url'],
        ], $result['status_code']);
    }

    /**
     * Retorna la compra sugerida y detalle de productos para un cliente en el Módulo de Visitas.
     * Reutiliza exactamente las mismas Actions del módulo Web (GetAvanceClienteDetalleFechasAction y GetAvanceClientesRutaAction)
     * para garantizar sincronía total y cero duplicidad de lógica.
     *
     * GET/POST /api/visitas/clientes/{clienteId}/sugerido
     */
    public function sugeridoCliente(
        Request $request,
        int|string $clienteId,
        GetAvanceClienteDetalleFechasAction $webDetalleAction,
        GetAvanceClientesRutaAction $webRutaAction
    ): JsonResponse {
        $cid = (int) $clienteId;
        if ($cid <= 0) {
            return response()->json(['error' => 'ID de cliente inválido'], 400);
        }

        $user = $request->user();

        $clienteRow = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->where('cliente_id', $cid)
            ->select(['cliente', 'ruta', 'dia'])
            ->first();

        $clienteNombre = $clienteRow && !empty($clienteRow->cliente) ? trim((string) $clienteRow->cliente) : null;
        if (empty($clienteNombre)) {
            $clienteNombre = DB::connection('supervisor')
                ->table('fact_ventas')
                ->where('cliente_id', $cid)
                ->value('cliente') ?: "Cliente #{$cid}";
        }

        // 1. Resolver ruta del cliente (priorizando la ruta oficial de cartera en pan_ruteo)
        $rutaOficial = ($clienteRow && !empty($clienteRow->ruta)) ? strtoupper(trim((string) $clienteRow->ruta)) : null;
        $rutaReq = $request->input('ruta');
        if (empty($rutaReq) && $user) {
            $rutaReq = $user->ruta ?: $user->username;
        }
        $rutaClean = $rutaOficial ?: strtoupper(trim((string) $rutaReq));

        // 2. Parámetros de filtro idénticos al módulo Web (Avance -> Cobertura Core: frec=2, cant=4, meses=3)
        $kpiClave = (string) ($request->input('kpi') ?: 'cob_core');
        $esCobCore = ($kpiClave === 'cob_core' || $kpiClave === 'cobertura_core');

        $cantidadMinima = $request->has('cantidad_minima')
            ? (int) $request->input('cantidad_minima')
            : ($esCobCore ? 4 : 1);

        $frecuenciaMinima = $request->has('frecuencia_minima')
            ? (int) $request->input('frecuencia_minima')
            : ($esCobCore ? 2 : 1);

        $mesesHistorico = (int) ($request->input('meses_historico') ?: 3);
        $omitirUnitarios = filter_var($request->input('omitir_unitarios', false), FILTER_VALIDATE_BOOLEAN);

        // 3. Resolver día de visita
        $diaVisita = $request->input('dia');
        if (empty($diaVisita) && $clienteRow) {
            $diaVisita = $clienteRow->dia;
        }

        // 4. Resolver cuota objetivo del cliente según la nivelación de cartera de la ruta en Web
        $cuotaObjetivo = null;
        if ($request->has('cuota_objetivo')) {
            $cuotaObjetivo = (float) $request->input('cuota_objetivo');
        } else {
            try {
                if (!empty($rutaClean)) {
                    $rutaData = $webRutaAction->execute(
                        ruta: $rutaClean,
                        kpiClave: $kpiClave,
                        omitirUnitarios3m: $omitirUnitarios,
                        mesesHistorico: $mesesHistorico,
                        frecuenciaMinima: $frecuenciaMinima,
                        cantidadMinima: $cantidadMinima
                    );

                    foreach ($rutaData['clientes'] ?? [] as $cli) {
                        if ((int) ($cli['cliente_id'] ?? 0) === $cid) {
                            $cuotaObjetivo = (float) ($cli['cuota_objetivo'] ?? 0.0);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("sugeridoCliente: error obteniendo cuota de ruta {$rutaClean}: " . $e->getMessage());
            }

            if ($cuotaObjetivo === null || $cuotaObjetivo <= 0.0) {
                $cuotaObjetivo = ($kpiClave === 'cob_core') ? 150.0 : 0.0;
            }
        }

        // 5. Ejecutar la acción Web oficial de cálculo sugerido
        try {
            $detalle = $webDetalleAction->execute(
                clienteId: $cid,
                ruta: $rutaClean,
                fechaInicio: null,
                fechaCorte: null,
                kpiClave: $kpiClave,
                omitirUnitarios3m: $omitirUnitarios,
                mesesHistorico: $mesesHistorico,
                frecuenciaMinima: $frecuenciaMinima,
                cantidadMinima: $cantidadMinima,
                cuotaObjetivo: $cuotaObjetivo,
                diaVisita: is_string($diaVisita) ? $diaVisita : null
            );
        } catch (\Throwable $e) {
            Log::error("sugeridoCliente error ejecutando Web Action para cliente {$cid}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'error' => 'Error al calcular sugerido de compra: ' . $e->getMessage(),
            ], 500);
        }

        // 6. Filtrar únicamente los productos que se sugiere comprar (cant_sugerida > 0)
        $sugeridos = [];
        $totalSugeridoBs = 0.0;

        foreach ($detalle['productos'] ?? [] as $prod) {
            $cantSugerida = (int) ($prod['cant_sugerida'] ?? 0);
            if ($cantSugerida > 0) {
                $precioUnit = round((float) ($prod['precio_unitario'] ?? $prod['precio_ultima_compra'] ?? 0.0), 2);
                $montoSugerido = round((float) ($prod['monto_sugerido'] ?? $prod['monto_sugerido_bs'] ?? ($cantSugerida * $precioUnit)), 2);

                $sugeridos[] = [
                    'codigo' => (string) ($prod['codigo'] ?? ''),
                    'producto' => (string) ($prod['producto'] ?? ''),
                    'jerarquia_1' => (string) ($prod['jerarquia1'] ?? ''),
                    'cant_sugerida' => $cantSugerida,
                    'compro_mes_actual' => (bool) ($prod['compro_mes_actual'] ?? false),
                    'cantidad_comprada_mes_actual' => (int) ($prod['total_cantidad_mes_actual'] ?? 0),
                ];

                $totalSugeridoBs += $montoSugerido;
            }
        }

        return response()->json([
            'status' => 'success',
            'cliente' => $clienteNombre,
            'cliente_id' => $cid,
            'ruta' => $rutaClean,
            'dia_visita' => $diaVisita ?: 'Sin Día',
            'total_sugerido' => round($totalSugeridoBs, 2),
            'total_skus_sugeridos' => count($sugeridos),
            'sugeridos' => $sugeridos,
        ], 200);
    }
}
