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

        // 1. Resolver ruta
        $ruta = $request->input('ruta');
        if (empty($ruta) && $user) {
            $ruta = $user->ruta ?: $user->username;
        }
        if (empty($ruta)) {
            $ruta = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('cliente_id', $cid)
                ->value('ruta');
        }
        $rutaClean = strtoupper(trim((string) $ruta));

        // 2. Parámetros de filtro (con valores por defecto consistentes con el módulo Web)
        $kpiClave = (string) ($request->input('kpi') ?: 'cob_core');
        $cantidadMinima = (int) ($request->input('cantidad_minima') ?: 1);
        $frecuenciaMinima = (int) ($request->input('frecuencia_minima') ?: 1);
        $mesesHistorico = (int) ($request->input('meses_historico') ?: 3);
        $omitirUnitarios = filter_var($request->input('omitir_unitarios', false), FILTER_VALIDATE_BOOLEAN);

        // 3. Resolver día de visita
        $diaVisita = $request->input('dia');
        if (empty($diaVisita)) {
            $diaVisita = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->where('cliente_id', $cid)
                ->value('dia');
        }

        // 4. Resolver cuota objetivo del cliente
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
                Log::warning("sugeridoCliente: error obteniendo cuota de ruta: " . $e->getMessage());
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
        $totalSugeridoUnidades = 0;

        foreach ($detalle['productos'] ?? [] as $prod) {
            $cantSugerida = (int) ($prod['cant_sugerida'] ?? 0);
            if ($cantSugerida > 0) {
                $precioUnit = round((float) ($prod['precio_unitario'] ?? $prod['precio_ultima_compra'] ?? 0.0), 2);
                $montoSugerido = round((float) ($prod['monto_sugerido'] ?? $prod['monto_sugerido_bs'] ?? ($cantSugerida * $precioUnit)), 2);
                if ($precioUnit <= 0.0 && $montoSugerido > 0.0) {
                    $precioUnit = round($montoSugerido / $cantSugerida, 2);
                }
                if ($montoSugerido <= 0.0 && $precioUnit > 0.0) {
                    $montoSugerido = round($cantSugerida * $precioUnit, 2);
                }

                $sugeridos[] = [
                    'codigo' => (string) ($prod['codigo'] ?? ''),
                    'producto' => (string) ($prod['producto'] ?? ''),
                    'categoria' => (string) ($prod['categoria'] ?? ''),
                    'cant_sugerida' => $cantSugerida,
                    'precio_unitario' => $precioUnit,
                    'monto_sugerido' => $montoSugerido,
                    'monto_sugerido_bs' => $montoSugerido,
                    'es_core_pareto' => (bool) ($prod['es_core_pareto'] ?? false),
                    'compro_mes_actual' => (bool) ($prod['compro_mes_actual'] ?? false),
                    'cumple_sugerido' => (bool) ($prod['cumple_sugerido'] ?? false),
                    'total_cantidad_3m' => (int) ($prod['total_cantidad_3m'] ?? 0),
                    'total_bs_3m' => round((float) ($prod['total_bs_3m'] ?? 0.0), 2),
                    'frecuencia_compra_3m' => (int) ($prod['frecuencia_compra'] ?? $prod['frecuencia_compra_3m'] ?? 0),
                ];

                $totalSugeridoBs += $montoSugerido;
                $totalSugeridoUnidades += $cantSugerida;
            }
        }

        return response()->json([
            'status' => 'success',
            'cliente_id' => $cid,
            'ruta' => $rutaClean,
            'dia_visita' => $diaVisita,
            'cuota_objetivo' => round((float) $cuotaObjetivo, 2),
            'total_sugerido' => round($totalSugeridoBs, 2),
            'total_unidades_sugeridas' => $totalSugeridoUnidades,
            'total_skus_sugeridos' => count($sugeridos),
            'sugeridos' => $sugeridos,
            'resumen_cobertura' => $detalle['resumen_cobertura'] ?? [],
            'todos_los_productos' => $detalle['productos'] ?? [],
            'fechas_historial' => $detalle['fechas'] ?? [],
        ], 200);
    }
}
