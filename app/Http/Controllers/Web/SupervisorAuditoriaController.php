<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaCatalogoAction;
use App\Actions\Web\Supervisor\Auditoria\GetAuditoriaRuteoAction;
use App\Actions\Web\Supervisor\Auditoria\ResolverAuditoriaRuteoAction;
use App\Actions\Web\Supervisor\Visitas\GetFotosClienteAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    // ── 1. AUDITORÍA DE CAMBIOS PENDIENTES (CENTRALIZADA) ───────────────────

    public function pendientes(Request $request, GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/Pendientes', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
            'initial_categoria' => $request->query('categoria', 'TODOS'),
        ]);
    }

    public function pendientesConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $params = $request->all();
            if (empty($params['estado_auditoria'])) {
                $params['estado_auditoria'] = 'PENDIENTE';
            }
            return response()->json($action->execute($params));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pendientesResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
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

    // ── 2. HISTÓRICO DE AUDITORÍA Y RESOLUCIONES ────────────────────────────

    public function historico(Request $request, GetAuditoriaCatalogoAction $catalogoAction): Response
    {
        $catalogo = $catalogoAction->execute();

        return Inertia::render('Supervisor/Auditoria/Historico', [
            'canales' => $catalogo['canales'],
            'rutas' => $catalogo['rutas'],
            'archivos_base' => $catalogo['archivos_base'],
            'initial_categoria' => $request->query('categoria', 'TODOS'),
        ]);
    }

    public function historicoConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        try {
            $params = $request->all();
            if (empty($params['estado_auditoria'])) {
                $params['estado_auditoria'] = 'RESUELTOS';
            }
            return response()->json($action->execute($params));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── RETROCOMPATIBILIDAD CON RUTAS ANTERIORES ────────────────────────────

    public function inactivados(): RedirectResponse
    {
        return redirect()->route('supervisor.auditoria.pendientes', ['categoria' => 'BAJAS_INACTIVADOS']);
    }

    public function inactivadosConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        $params = array_merge($request->all(), ['categoria' => 'BAJAS_INACTIVADOS']);
        return response()->json($action->execute($params));
    }

    public function inactivadosResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        return $this->pendientesResolver($request, $action);
    }

    public function cambiosRuta(): RedirectResponse
    {
        return redirect()->route('supervisor.auditoria.pendientes', ['categoria' => 'CAMBIOS_RUTA']);
    }

    public function cambiosRutaConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        $params = array_merge($request->all(), ['categoria' => 'CAMBIOS_RUTA']);
        return response()->json($action->execute($params));
    }

    public function cambiosRutaResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        return $this->pendientesResolver($request, $action);
    }

    public function modificacionDatos(): RedirectResponse
    {
        return redirect()->route('supervisor.auditoria.pendientes', ['categoria' => 'MODIFICACION_DATOS']);
    }

    public function modificacionDatosConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        $params = array_merge($request->all(), ['categoria' => 'MODIFICACION_DATOS']);
        return response()->json($action->execute($params));
    }

    public function modificacionDatosResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        return $this->pendientesResolver($request, $action);
    }

    public function cambiosDia(): RedirectResponse
    {
        return redirect()->route('supervisor.auditoria.pendientes', ['categoria' => 'CAMBIOS_DIA']);
    }

    public function cambiosDiaConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        $params = array_merge($request->all(), ['categoria' => 'CAMBIOS_DIA']);
        return response()->json($action->execute($params));
    }

    public function cambiosDiaResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        return $this->pendientesResolver($request, $action);
    }

    public function altas(): RedirectResponse
    {
        return redirect()->route('supervisor.auditoria.pendientes', ['categoria' => 'ALTAS_REACTIVACIONES']);
    }

    public function altasConsultar(Request $request, GetAuditoriaRuteoAction $action): JsonResponse
    {
        $params = array_merge($request->all(), ['categoria' => 'ALTAS_REACTIVACIONES']);
        return response()->json($action->execute($params));
    }

    public function altasResolver(Request $request, ResolverAuditoriaRuteoAction $action): JsonResponse
    {
        return $this->pendientesResolver($request, $action);
    }
}
