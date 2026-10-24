<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppModule;
use App\Models\AppModuleRoute;
use App\Models\ClienteInventario;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorModulosController extends Controller
{
    /**
     * Muestra la interfaz de gestión de módulos por RUTA comercial y parámetros.
     */
    public function index(): Response
    {
        $modules = AppModule::orderBy('order')->get();

        // 1. Obtener catálogo maestro de rutas comerciales (dim_rutas)
        $dimRows = DB::connection('supervisor')
            ->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->get();

        $metaRuta = $dimRows->keyBy(fn($r) => trim($r->ruta));
        $rutasDim = $dimRows->pluck('ruta');

        // 2. Rutas adicionales en pan_ruteo
        $rutasPan = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->distinct()
            ->pluck('ruta');

        // 3. Rutas asignadas en la tabla users
        $rutasUsers = User::whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->distinct()
            ->pluck('ruta');

        // 4. Unificar y ordenar naturalmente
        $rutasUnicas = $rutasDim
            ->concat($rutasPan)
            ->concat($rutasUsers)
            ->map(fn($r) => trim($r))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // 5. Cargar usuarios vendedores para enriquecer información
        $vendedoresUsers = User::whereNotNull('ruta')
            ->where('ruta', '!=', '')
            ->select('ruta', 'username')
            ->get()
            ->keyBy(fn($u) => trim($u->ruta));

        $rutasCatalog = $rutasUnicas->map(function ($r) use ($metaRuta, $vendedoresUsers) {
            $dimInfo = $metaRuta->get($r);
            $userInfo = $vendedoresUsers->get($r);

            return [
                'ruta' => $r,
                'vendedor' => $dimInfo?->vendedor ?? $userInfo?->username ?? 'No asignado',
                'canal' => $dimInfo?->canal ?? 'General',
            ];
        });

        // 6. Obtener todas las configuraciones específicas por ruta
        $routeOverrides = AppModuleRoute::all();

        return Inertia::render('Supervisor/Modulos/Index', [
            'modules' => $modules,
            'rutas' => $rutasCatalog,
            'routeOverrides' => $routeOverrides,
        ]);
    }

    /**
     * Activa o desactiva un módulo móvil para una RUTA específica.
     */
    public function toggleRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ruta' => ['required', 'string'],
            'module_code' => ['required', 'string', 'exists:app_modules,code'],
            'is_active' => ['required', 'boolean'],
        ]);

        $ruta = trim($validated['ruta']);
        $moduleCode = $validated['module_code'];
        $isActive = (bool) $validated['is_active'];

        $record = AppModuleRoute::updateOrCreate(
            ['ruta' => $ruta, 'module_code' => $moduleCode],
            ['is_active' => $isActive]
        );

        return response()->json([
            'success' => true,
            'message' => "Módulo '{$moduleCode}' " . ($isActive ? 'activado' : 'desactivado') . " para la ruta '{$ruta}'.",
            'override' => $record,
        ]);
    }

    /**
     * Actualiza la configuración específica de un módulo para una RUTA (ej: días de historial).
     */
    public function updateRouteConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ruta' => ['required', 'string'],
            'module_code' => ['required', 'string', 'exists:app_modules,code'],
            'config' => ['required', 'array'],
        ]);

        $ruta = trim($validated['ruta']);
        $moduleCode = $validated['module_code'];

        $existing = AppModuleRoute::where('ruta', $ruta)->where('module_code', $moduleCode)->first();
        $mergedConfig = array_merge($existing?->config ?? [], $validated['config']);

        $record = AppModuleRoute::updateOrCreate(
            ['ruta' => $ruta, 'module_code' => $moduleCode],
            ['config' => $mergedConfig]
        );

        return response()->json([
            'success' => true,
            'message' => "Configuración para la ruta '{$ruta}' guardada correctamente.",
            'override' => $record,
        ]);
    }

    /**
     * Aplica el estado de un módulo a TODAS las rutas comerciales masivamente.
     */
    public function applyToAllRoutes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_code' => ['required', 'string', 'exists:app_modules,code'],
            'is_active' => ['required', 'boolean'],
            'rutas' => ['required', 'array'],
        ]);

        $moduleCode = $validated['module_code'];
        $isActive = (bool) $validated['is_active'];
        $rutas = $validated['rutas'];

        $now = now();
        $records = [];
        foreach ($rutas as $r) {
            $r = trim($r);
            if (!empty($r)) {
                $records[] = [
                    'ruta' => $r,
                    'module_code' => $moduleCode,
                    'is_active' => $isActive ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($records)) {
            AppModuleRoute::upsert(
                $records,
                ['module_code', 'ruta'],
                ['is_active', 'updated_at']
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Módulo '{$moduleCode}' " . ($isActive ? 'activado' : 'desactivado') . ' para todas las rutas seleccionadas.',
        ]);
    }

    /**
     * Activa o desactiva un módulo móvil a nivel GLOBAL por defecto.
     */
    public function toggleGlobal(int $id): JsonResponse
    {
        $module = AppModule::findOrFail($id);
        $module->is_active = !$module->is_active;
        $module->save();

        return response()->json([
            'success' => true,
            'message' => "Módulo global '{$module->name}' " . ($module->is_active ? 'activado' : 'desactivado') . ' por defecto.',
            'module' => $module,
        ]);
    }

    /**
     * Actualiza la configuración global por defecto de un módulo.
     */
    public function updateGlobalConfig(Request $request, int $id): JsonResponse
    {
        $module = AppModule::findOrFail($id);

        $validated = $request->validate([
            'config' => ['required', 'array'],
        ]);

        $module->config = array_merge($module->config ?? [], $validated['config']);
        $module->save();

        return response()->json([
            'success' => true,
            'message' => "Configuración global de '{$module->name}' actualizada.",
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
