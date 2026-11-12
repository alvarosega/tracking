<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\AltasEdiciones\CambiarEstadoRevisionAction;
use App\Actions\Web\Supervisor\AltasEdiciones\GetAltasEdicionesDataAction;
use App\Actions\Web\Supervisor\AltasEdiciones\GetAltasEdicionesInitDataAction;
use App\Actions\Web\Supervisor\AltasEdiciones\GetClientesCercanosAltasAction;
use App\Actions\Web\Supervisor\Visitas\GetFotosClienteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AltasEdiciones\CambiarEstadoRevisionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorAltasEdicionesController extends Controller
{
    /**
     * Redirige al submódulo inicial de Nuevas Altas.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('supervisor.altas-ediciones.altas');
    }

    /**
     * Submódulo 1: Nuevas Altas (tipo_registro = ALTA).
     */
    public function altas(GetAltasEdicionesInitDataAction $action): Response
    {
        $data = $action->execute();

        return Inertia::render('Supervisor/AltasEdiciones/Index', [
            'catalogo_rutas' => $data['catalogo_rutas'],
            'canales' => $data['canales'],
            'tipos_registro' => $data['tipos_registro'],
            'estados_revision' => $data['estados_revision'],
            'tipoFijo' => 'ALTA',
            'submoduloActivo' => 'altas',
        ]);
    }

    /**
     * Submódulo 2: Modificaciones de Clientes (tipo_registro = EDICION / MODIFICACION).
     */
    public function modificaciones(GetAltasEdicionesInitDataAction $action): Response
    {
        $data = $action->execute();

        return Inertia::render('Supervisor/AltasEdiciones/Index', [
            'catalogo_rutas' => $data['catalogo_rutas'],
            'canales' => $data['canales'],
            'tipos_registro' => $data['tipos_registro'],
            'estados_revision' => $data['estados_revision'],
            'tipoFijo' => 'EDICION',
            'submoduloActivo' => 'modificaciones',
        ]);
    }

    /**
     * Retorna los registros de altas y modificaciones según los filtros aplicados.
     */
    public function data(Request $request, GetAltasEdicionesDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            fechaInicio: $request->input('fecha_inicio'),
            fechaFin: $request->input('fecha_fin'),
            canales: (array) $request->input('canales', []),
            rutas: (array) $request->input('rutas', []),
            tipos: (array) $request->input('tipos', []),
            estados: (array) $request->input('estados', [])
        );

        return response()->json($resultado);
    }

    /**
     * Consulta clientes cercanos en 200m a la redonda (todas las rutas) y diagnostica duplicados.
     */
    public function cercanos(Request $request, GetClientesCercanosAltasAction $action): JsonResponse
    {
        try {
            $lat = (float) $request->input('latitud');
            $lng = (float) $request->input('longitud');

            if ($lat == 0.0 || $lng == 0.0 || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coordenadas GPS no válidas.',
                    'total_cercanos' => 0,
                    'tiene_duplicados' => false,
                    'alertas_duplicidad' => [],
                    'clientes_cercanos' => [],
                ], 200);
            }

            $params = [
                'latitud' => $lat,
                'longitud' => $lng,
                'radio' => $request->filled('radio') ? (int) $request->input('radio') : 200,
                'cliente' => (string) $request->input('cliente', ''),
                'celular' => $request->input('celular') !== null ? (string) $request->input('celular') : null,
                'telefono' => $request->input('telefono') !== null ? (string) $request->input('telefono') : null,
                'nit' => $request->input('nit') !== null ? (string) $request->input('nit') : null,
                'exclude_id' => $request->filled('exclude_id') ? (int) $request->input('exclude_id') : null,
            ];

            $result = $action->execute($params);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Error en /altas-ediciones/cercanos: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'total_cercanos' => 0,
                'tiene_duplicados' => false,
                'alertas_duplicidad' => ['Error en consulta de cercanos: ' . $e->getMessage()],
                'clientes_cercanos' => [],
            ], 200);
        }
    }

    /**
     * Retorna las fotografías históricas de visitas de un cliente.
     */
    public function fotosCliente(int $clienteId, GetFotosClienteAction $action): JsonResponse
    {
        try {
            $fotos = $action->execute($clienteId);

            return response()->json([
                'success' => true,
                'cliente_id' => $clienteId,
                'total' => $fotos->count(),
                'fotos' => $fotos,
            ]);
        } catch (\Throwable $e) {
            Log::error("Error al obtener fotos del cliente #{$clienteId}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'fotos' => [],
            ], 500);
        }
    }

    /**
     * Actualiza el estado de revisión (APROBADO / RECHAZADO / PENDIENTE) exclusivamente en saneamiento_base.
     * Bajo ninguna circunstancia modifica ni altera la tabla pan_ruteo.
     */
    public function cambiarEstado(
        int $id,
        CambiarEstadoRevisionRequest $request,
        CambiarEstadoRevisionAction $action
    ): JsonResponse {
        try {
            $resultado = $action->execute(
                id: $id,
                nuevoEstado: $request->estado(),
                motivo: $request->motivo()
            );

            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error("Error al cambiar estado de saneamiento_base #{$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
