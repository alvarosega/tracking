<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaAltasAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaCambiosDiaAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaCambiosRutaAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaCatalogoAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaHistoricoAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaInactivadosAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaModificacionDatosAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaPreventasAction;
use App\Actions\Web\Supervisor\Auditoria\ResolverAuditoriaRuteoAction;
use App\Actions\Web\Supervisor\Visitas\GetFotosClienteAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SupervisorAuditoriaController extends Controller
{
    /**
     * Catálogo maestro de canales, rutas y archivos base.
     */
    public function catalogo(GetAuditoriaCatalogoAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute());
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Galería fotográfica de visitas de un cliente.
     */
    public function fotosCliente(int $clienteId, GetFotosClienteAction $action): JsonResponse
    {
        try {
            $fotos = $action->execute($clienteId);
            return response()->json([
                'cliente_id' => $clienteId,
                'total' => $fotos->count(),
                'fotos' => $fotos,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── 1. PREVENTAS ────────────────────────────────────────────────────────

    public function preventas(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/Preventas', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function preventasConsultar(Request $request, GetAuditoriaPreventasAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── 2. INACTIVADOS ──────────────────────────────────────────────────────

    public function inactivados(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/Inactivados', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function inactivadosConsultar(Request $request, GetAuditoriaInactivadosAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function inactivadosResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload['auditado_por']) && $request->user()) {
                $payload['auditado_por'] = $request->user()->username ?? $request->user()->name ?? 'SUPERVISOR';
            }
            return response()->json($action->execute($payload));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ── 3. CAMBIOS DE RUTA ──────────────────────────────────────────────────

    public function cambiosRuta(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/CambiosRuta', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function cambiosRutaConsultar(Request $request, GetAuditoriaCambiosRutaAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cambiosRutaResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload['auditado_por']) && $request->user()) {
                $payload['auditado_por'] = $request->user()->username ?? $request->user()->name ?? 'SUPERVISOR';
            }
            return response()->json($action->execute($payload));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ── 4. MODIFICACIÓN DE DATOS ────────────────────────────────────────────

    public function modificacionDatos(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/ModificacionDatos', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function modificacionDatosConsultar(Request $request, GetAuditoriaModificacionDatosAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function modificacionDatosResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload['auditado_por']) && $request->user()) {
                $payload['auditado_por'] = $request->user()->username ?? $request->user()->name ?? 'SUPERVISOR';
            }
            return response()->json($action->execute($payload));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ── 5. CAMBIOS DE DÍA ───────────────────────────────────────────────────

    public function cambiosDia(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/CambiosDia', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function cambiosDiaConsultar(Request $request, GetAuditoriaCambiosDiaAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cambiosDiaResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload['auditado_por']) && $request->user()) {
                $payload['auditado_por'] = $request->user()->username ?? $request->user()->name ?? 'SUPERVISOR';
            }
            return response()->json($action->execute($payload));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ── 6. ALTAS Y REACTIVACIONES ───────────────────────────────────────────

    public function altas(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/Altas', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function altasConsultar(Request $request, GetAuditoriaAltasAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function altasResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $payload = $request->all();
            if (empty($payload['auditado_por']) && $request->user()) {
                $payload['auditado_por'] = $request->user()->username ?? $request->user()->name ?? 'SUPERVISOR';
            }
            return response()->json($action->execute($payload));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ── 7. HISTÓRICO RESOLUCIONES ───────────────────────────────────────────

    public function historico(GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/Historico', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
        ]);
    }

    public function historicoConsultar(Request $request, GetAuditoriaHistoricoAction $action): JsonResponse
    {
        try {
            return response()->json($action->execute($request->all()));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

