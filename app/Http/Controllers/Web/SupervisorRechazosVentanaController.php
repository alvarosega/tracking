<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\RechazosVentana\GetRechazosVentanaDataAction;
use App\Actions\Web\Supervisor\RechazosVentana\UpdateRechazosVentanaAction;
use App\Http\Controllers\Controller;
use App\Services\PedidosRechazados\RechazosVentanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorRechazosVentanaController extends Controller
{
    public function __construct(
        protected RechazosVentanaService $service
    ) {}

    /**
     * Muestra la interfaz de configuración de ventana y días para pedidos rechazados.
     */
    public function index(GetRechazosVentanaDataAction $action): Response
    {
        $data = $action->execute();

        return Inertia::render('Supervisor/RechazosVentana/Index', [
            'initialData' => $data,
        ]);
    }

    /**
     * Retorna los datos y matriz en JSON para recargas asíncronas.
     */
    public function data(GetRechazosVentanaDataAction $action): JsonResponse
    {
        return response()->json($action->execute(), 200);
    }

    /**
     * Actualiza la configuración global (modo, fechas de excepción o matriz semanal).
     */
    public function updateGlobal(Request $request, UpdateRechazosVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'global', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Ajustes Globales pueden modificar la configuración global.',
            ], 403);
        }

        $validated = $request->validate([
            'modo' => 'nullable|string|in:semanal,fecha_fija',
            'fecha_especifica_activa' => 'nullable|date',
            'fecha_especifica_hasta' => 'nullable|date',
            'matriz_semanal' => 'nullable|array',
            'dias_atras_fallback' => 'nullable|integer|between:1,90',
            'incluir_hoy' => 'nullable|boolean',
        ]);

        $modulo = $action->updateGlobal($validated);

        return response()->json([
            'success' => true,
            'message' => 'Configuración global de pedidos rechazados actualizada exitosamente.',
            'config' => $modulo->config,
        ], 200);
    }

    /**
     * Actualiza la configuración específica para una ruta comercial.
     */
    public function updateRoute(Request $request, UpdateRechazosVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Solo usuarios Superadministrador o con permisos de Matriz pueden personalizar rutas.',
            ], 403);
        }

        $validated = $request->validate([
            'ruta' => 'required|string|max:50',
            'config' => 'required|array',
        ]);

        $ruta = (string) $validated['ruta'];
        $config = (array) $validated['config'];

        $override = $action->updateRoute($ruta, $config);

        return response()->json([
            'success' => true,
            'message' => "Configuración personalizada guardada para la ruta {$ruta}.",
            'ruta' => $ruta,
            'config' => $override->config,
        ], 200);
    }

    /**
     * Activa o desactiva el módulo para una ruta comercial.
     */
    public function toggleRoute(Request $request, UpdateRechazosVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Permiso denegado para modificar estado de rutas.',
            ], 403);
        }

        $validated = $request->validate([
            'ruta' => 'required|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $override = $action->toggleRoute((string) $validated['ruta'], (bool) $validated['is_active']);

        $estadoStr = $override->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'message' => "Módulo Pedidos Rechazados {$estadoStr} para la ruta {$override->ruta}.",
            'override' => $override,
        ], 200);
    }

    /**
     * Restablece la ruta a los parámetros globales.
     */
    public function resetRoute(Request $request, UpdateRechazosVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Permiso denegado para restablecer configuración de ruta.',
            ], 403);
        }

        $validated = $request->validate([
            'ruta' => 'required|string|max:50',
        ]);

        $ruta = (string) $validated['ruta'];
        $action->resetRoute($ruta);

        return response()->json([
            'success' => true,
            'message' => "La ruta {$ruta} ahora hereda la configuración global.",
            'ruta' => $ruta,
        ], 200);
    }

    /**
     * Aplica masivamente una configuración a un grupo de rutas.
     */
    public function batchUpdate(Request $request, UpdateRechazosVentanaAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasModuleAccess('modulos', 'matriz', true)) {
            return response()->json([
                'message' => 'Permiso denegado para aplicar cambios masivos.',
            ], 403);
        }

        $validated = $request->validate([
            'rutas' => 'required|array|min:1',
            'rutas.*' => 'string|max:50',
            'config' => 'required|array',
        ]);

        $count = $action->batchUpdate((array) $validated['rutas'], (array) $validated['config']);

        return response()->json([
            'success' => true,
            'message' => "Se actualizó la configuración en {$count} rutas seleccionadas.",
            'count' => $count,
        ], 200);
    }

    /**
     * Previsualiza en tiempo real los pedidos rechazados para la ruta seleccionada o todas.
     */
    public function preview(Request $request): JsonResponse
    {
        $ruta = $request->query('ruta') ?: $request->input('ruta');
        $rutaStr = !empty($ruta) ? (string) $ruta : null;

        $config = $this->service->getConfig($rutaStr);
        $evalInfo = $this->service->resolverFechasEvaluadas($config);

        $pedidos = $this->service->evaluarPreventas(
            fechasEvaluadas: $evalInfo['fechas'],
            rutaFiltro: $rutaStr,
            user: null,
            incluirJustificados: false
        );

        return response()->json([
            'success' => true,
            'eval_info' => $evalInfo,
            'ruta' => $rutaStr,
            'total' => count($pedidos),
            'pedidos' => $pedidos,
        ], 200);
    }
}

