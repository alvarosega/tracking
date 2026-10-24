<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppModule;
use App\Models\ClienteInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorModulosController extends Controller
{
    /**
     * Muestra la interfaz de gestión de módulos móviles y parámetros del sistema.
     */
    public function index(): Response
    {
        $modules = AppModule::orderBy('order')->get();

        return Inertia::render('Supervisor/Modulos/Index', [
            'modules' => $modules,
        ]);
    }

    /**
     * Activa o desactiva un módulo móvil.
     */
    public function toggle(int $id): JsonResponse
    {
        $module = AppModule::findOrFail($id);
        $module->is_active = !$module->is_active;
        $module->save();

        return response()->json([
            'success' => true,
            'message' => "Módulo '{$module->name}' " . ($module->is_active ? 'activado' : 'desactivado') . ' correctamente.',
            'module' => $module,
        ]);
    }

    /**
     * Actualiza la configuración variable de un módulo (ej: días de historial para inventario).
     */
    public function updateConfig(Request $request, int $id): JsonResponse
    {
        $module = AppModule::findOrFail($id);

        $validated = $request->validate([
            'config' => ['required', 'array'],
        ]);

        $module->config = array_merge($module->config ?? [], $validated['config']);
        $module->save();

        return response()->json([
            'success' => true,
            'message' => "Configuración del módulo '{$module->name}' actualizada correctamente.",
            'module' => $module,
        ]);
    }

    /**
     * Consulta de inventarios tomados en campo por los vendedores.
     */
    public function inventariosData(Request $request): JsonResponse
    {
        $query = ClienteInventario::with('items')
            ->orderByDesc('fecha_registro');

        if ($request->filled('fecha')) {
            $query->whereDate('fecha_registro', $request->query('fecha'));
        }

        if ($request->filled('ruta')) {
            $query->where('ruta', $request->query('ruta'));
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->query('cliente_id'));
        }

        $inventarios = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $inventarios,
        ]);
    }
}

