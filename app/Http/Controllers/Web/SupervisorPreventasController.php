<?php

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Preventas\GetPreventasDataAction;
use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Preventas\GetPreventasDataRequest;
use App\Models\Supervisor\FactPreventa;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorPreventasController extends Controller
{
    /**
     * Muestra la vista principal de Auditoría de Preventas.
     */
    public function index(GetCatalogoRutasAction $catalogoAction): Response
    {
        $catalogoData = $catalogoAction->execute();

        // Extraer la fecha más reciente con datos reales de preventa
        $ultimaFecha = FactPreventa::query()->max('fecha_norm');

        return Inertia::render('Supervisor/Preventas/Index', [
            'catalogo_rutas' => $catalogoData['catalogo'],
            'canales' => $catalogoData['canales'],
            'fecha_default' => $ultimaFecha ?: date('Y-m-d'),
        ]);
    }

    /**
     * Devuelve las preventas agrupadas por cliente y pedidos para la fecha y filtros seleccionados.
     */
    public function data(GetPreventasDataRequest $request, GetPreventasDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            fecha: $request->fecha(),
            canales: $request->canales(),
            rutas: $request->rutas(),
            lat: $request->latitud(),
            lng: $request->longitud(),
            radio: $request->radio()
        );

        return response()->json($resultado);
    }
}
