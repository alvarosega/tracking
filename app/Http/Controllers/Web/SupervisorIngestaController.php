<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Ingesta\AutorizarCancelacionesAction;
use App\Actions\Web\Supervisor\Ingesta\GetCanceladosPendientesAction;
use App\Actions\Web\Supervisor\Ingesta\GetIngestaHistorialCargasAction;
use App\Actions\Web\Supervisor\Ingesta\GetIngestaTablasEstadoAction;
use App\Actions\Web\Supervisor\Ingesta\GetRuteoSnapshotsAction;
use App\Actions\Web\Supervisor\Ingesta\ProcessInventarioIngestaAction;
use App\Actions\Web\Supervisor\Ingesta\ProcessPreventasIngestaAction;
use App\Actions\Web\Supervisor\Ingesta\ProcessRuteoIngestaAction;
use App\Actions\Web\Supervisor\Ingesta\ProcessVentasIngestaAction;
use App\Actions\Web\Supervisor\Ingesta\ProcessVisitasIngestaAction;
use App\Actions\Web\Supervisor\Ingesta\RollbackSnapshotRuteoAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SupervisorIngestaController extends Controller
{
    /**
     * Vista 1: Centro de Ingesta (Dashboard y Matriz de Tablas).
     */
    public function index(
        GetIngestaTablasEstadoAction $tablasAction,
        GetIngestaHistorialCargasAction $historialAction
    ): Response {
        $tablasData = $tablasAction->execute();
        $historialData = $historialAction->execute();

        return Inertia::render('Supervisor/Ingesta/Index', [
            'tablas' => $tablasData['tablas'] ?? [],
            'sistemaListo' => $tablasData['sistema_listo'] ?? false,
            'tablasCargadas' => $tablasData['tablas_cargadas'] ?? 0,
            'totalTablas' => $tablasData['total_tablas'] ?? 0,
            'historial' => $historialData,
            'submoduloActivo' => 'dashboard',
        ]);
    }

    /**
     * Vista 2: Carga de Ventas Diarias.
     */
    public function ventas(): Response
    {
        return Inertia::render('Supervisor/Ingesta/Upload', [
            'entidad' => 'ventas',
            'titulo' => 'Carga de Ventas Diarias',
            'tabla' => 'fact_ventas',
            'subtitulo' => 'Carga acumulativa de facturas y líneas de venta con control de discrepancias y duplicados.',
            'submoduloActivo' => 'ventas',
        ]);
    }

    /**
     * Vista 3: Carga de Preventas Intra-día.
     */
    public function preventas(): Response
    {
        return Inertia::render('Supervisor/Ingesta/Upload', [
            'entidad' => 'preventas',
            'titulo' => 'Carga de Preventas Intra-día',
            'tabla' => 'fact_preventas',
            'subtitulo' => 'Cortes periódicos intra-día con detección de pedidos cancelados y modificaciones.',
            'submoduloActivo' => 'preventas',
        ]);
    }

    /**
     * Vista 4: Carga de Plan de Ruteo Maestro.
     */
    public function ruteo(): Response
    {
        return Inertia::render('Supervisor/Ingesta/Upload', [
            'entidad' => 'ruteo',
            'titulo' => 'Carga de Plan de Ruteo',
            'tabla' => 'pan_ruteo',
            'subtitulo' => 'Reemplazo atómico del padrón con preservación de snapshots inmutables y auditoría granular.',
            'submoduloActivo' => 'ruteo',
        ]);
    }

    /**
     * Vista 5: Carga de Visitas Operativas.
     */
    public function visitas(): Response
    {
        return Inertia::render('Supervisor/Ingesta/Upload', [
            'entidad' => 'visitas',
            'titulo' => 'Carga de Visitas Operativas',
            'tabla' => 'fact_visitas',
            'subtitulo' => 'Marcajes en punto de venta, tiempos de atención, geocercas y detección de anulaciones.',
            'submoduloActivo' => 'visitas',
        ]);
    }

    /**
     * Vista 6: Carga de Stock / Inventario.
     */
    public function inventario(): Response
    {
        return Inertia::render('Supervisor/Ingesta/Upload', [
            'entidad' => 'inventario',
            'titulo' => 'Carga de Stock Actual',
            'tabla' => 'inv_stock',
            'subtitulo' => 'Existencias físicas y saldos por almacén con registro de snapshots históricos.',
            'submoduloActivo' => 'inventario',
        ]);
    }

    /**
     * Vista 7: Auditoría y Autorización de Preventas Canceladas.
     */
    public function cancelados(GetCanceladosPendientesAction $action): Response
    {
        $pendientes = $action->execute();

        return Inertia::render('Supervisor/Ingesta/Cancelados', [
            'pendientes' => $pendientes,
            'submoduloActivo' => 'cancelados',
        ]);
    }

    /**
     * Vista 8: Historial y Rollback de Snapshots de Ruteo.
     */
    public function snapshots(GetRuteoSnapshotsAction $action): Response
    {
        $snapshots = $action->execute();

        return Inertia::render('Supervisor/Ingesta/Snapshots', [
            'snapshots' => $snapshots,
            'submoduloActivo' => 'snapshots',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENDPOINTS API / AJAX (Consumidos por las vistas y DropZone)
    // ─────────────────────────────────────────────────────────────────────────

    public function tablasEstado(GetIngestaTablasEstadoAction $action): JsonResponse
    {
        try {
            $data = $action->execute();
            return response()->json($data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cargasHistorial(GetIngestaHistorialCargasAction $action): JsonResponse
    {
        try {
            $cargas = $action->execute();
            return response()->json(['cargas' => $cargas]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadVentas(Request $request, ProcessVentasIngestaAction $action): JsonResponse
    {
        try {
            $this->validarArchivoIngesta($request);
            $file = $request->file('file');
            $resultado = $action->execute($file);
            return response()->json(array_merge(['status' => 'success', 'detalle' => $resultado], $resultado));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Error de validación: ' . implode(', ', $ve->validator->errors()->all())], 422);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en uploadVentas: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage(), 'detail' => $e->getMessage()], 400);
        }
    }

    public function uploadPreventas(Request $request, ProcessPreventasIngestaAction $action): JsonResponse
    {
        try {
            $this->validarArchivoIngesta($request);
            $file = $request->file('file');
            $resultado = $action->execute($file);
            return response()->json(array_merge(['status' => 'success', 'detalle' => $resultado], $resultado));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Error de validación: ' . implode(', ', $ve->validator->errors()->all())], 422);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en uploadPreventas: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage(), 'detail' => $e->getMessage()], 400);
        }
    }

    public function uploadRuteo(Request $request, ProcessRuteoIngestaAction $action): JsonResponse
    {
        try {
            $this->validarArchivoIngesta($request, ['fecha_ruteo' => 'nullable|string']);
            $file = $request->file('file');
            $fechaRuteo = $request->input('fecha_ruteo');
            $resultado = $action->execute($file, null, $fechaRuteo);
            return response()->json(array_merge(['status' => 'success', 'detalle' => $resultado], $resultado));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Error de validación: ' . implode(', ', $ve->validator->errors()->all())], 422);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en uploadRuteo: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage(), 'detail' => $e->getMessage()], 400);
        }
    }

    public function uploadVisitas(Request $request, ProcessVisitasIngestaAction $action): JsonResponse
    {
        try {
            $this->validarArchivoIngesta($request);
            $file = $request->file('file');
            $resultado = $action->execute($file);
            return response()->json(array_merge(['status' => 'success', 'detalle' => $resultado], $resultado));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Error de validación: ' . implode(', ', $ve->validator->errors()->all())], 422);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en uploadVisitas: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage(), 'detail' => $e->getMessage()], 400);
        }
    }

    public function uploadInventario(Request $request, ProcessInventarioIngestaAction $action): JsonResponse
    {
        try {
            $this->validarArchivoIngesta($request);
            $file = $request->file('file');
            $resultado = $action->execute($file);
            return response()->json(array_merge(['status' => 'success', 'detalle' => $resultado], $resultado));
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['message' => 'Error de validación: ' . implode(', ', $ve->validator->errors()->all())], 422);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en uploadInventario: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage(), 'detail' => $e->getMessage()], 400);
        }
    }

    /**
     * Valida la presencia y extensión de archivo de forma robusta e independiente de MIME types del servidor.
     *
     * @param array<string, mixed> $extraRules
     */
    protected function validarArchivoIngesta(Request $request, array $extraRules = []): void
    {
        $rules = array_merge([
            'file' => [
                'required',
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    if (!$value || !$value->isValid()) {
                        $fail("El archivo no se subió correctamente o está corrupto.");
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['xlsx', 'csv', 'txt', 'xls', 'xlsm'], true)) {
                        $fail("El archivo debe ser de formato .xlsx o .csv (extensión detectada: .{$ext})");
                    }
                },
            ],
        ], $extraRules);

        $request->validate($rules);
    }

    public function canceladosPendientes(GetCanceladosPendientesAction $action): JsonResponse
    {
        try {
            $pendientes = $action->execute();
            return response()->json(['pendientes' => $pendientes]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function autorizarCancelados(Request $request, AutorizarCancelacionesAction $action): JsonResponse
    {
        $items = $request->input('items', []);

        try {
            $res = $action->execute($items);
            return response()->json($res);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function ruteoSnapshots(GetRuteoSnapshotsAction $action): JsonResponse
    {
        try {
            $snapshots = $action->execute();
            return response()->json(['snapshots' => $snapshots]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function rollbackRuteo(Request $request, RollbackSnapshotRuteoAction $action): JsonResponse
    {
        $snapshotId = (string)$request->input('snapshot_id', '');

        try {
            $res = $action->execute($snapshotId);
            return response()->json($res);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}

