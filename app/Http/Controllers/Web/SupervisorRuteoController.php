<?php

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use App\Actions\Web\Supervisor\Ruteo\GetCercanosInitDataAction;
use App\Actions\Web\Supervisor\Ruteo\GetClientesCercanosAction;
use App\Actions\Web\Supervisor\Ruteo\GetRuteoDataAction;
use App\Actions\Web\Supervisor\Ruteo\GetVentasClienteAction;
use App\Actions\Web\Supervisor\Ruteo\GetVisitasClienteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Ruteo\GetCercanosDataRequest;
use App\Http\Requests\Web\Ruteo\GetRuteoDataRequest;
use App\Http\Requests\Web\Ruteo\GetVentasClienteRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorRuteoController extends Controller
{
    /**
     * Muestra la vista principal de Visor y Fronteras GIS.
     */
    public function index(GetCatalogoRutasAction $action): Response
    {
        $catalogoData = $action->execute();

        return Inertia::render('Supervisor/Ruteo/Index', [
            'catalogo_rutas' => $catalogoData['catalogo'],
            'canales' => $catalogoData['canales'],
        ]);
    }

    /**
     * Devuelve los puntos geográficos de clientes y fronteras poligonales.
     */
    public function getData(GetRuteoDataRequest $request, GetRuteoDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            canales: $request->canales(),
            rutas: $request->rutas(),
            dias: $request->dias(),
            verFronteraRutas: $request->verFronteraRutas(),
            verFronteraDias: $request->verFronteraDias(),
            soloActivos: $request->soloActivos()
        );

        return response()->json($resultado);
    }

    /**
     * Muestra la vista de análisis espacial de Clientes Cercanos.
     */
    public function cercanos(GetCercanosInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Ruteo/Cercanos', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'anios_disponibles' => $initData['anios_disponibles'],
            'anio_default' => $initData['anio_default'],
            'meses_default' => $initData['meses_default'],
        ]);
    }

    /**
     * Ejecuta la búsqueda geográfica de clientes en el radio especificado con historial de compras.
     */
    public function getCercanosData(GetCercanosDataRequest $request, GetClientesCercanosAction $action): JsonResponse
    {
        $resultado = $action->execute(
            lat: $request->latitud(),
            lng: $request->longitud(),
            radio: $request->radio(),
            canales: $request->canales(),
            rutas: $request->rutas(),
            meses: $request->meses(),
            anio: $request->anio(),
            soloActivos: $request->soloActivos()
        );

        return response()->json($resultado);
    }

    /**
     * Obtiene el desglose histórico de compras de un cliente para el dossier 360°.
     */
    public function getVentasCliente(GetVentasClienteRequest $request, string|int $clienteId, GetVentasClienteAction $action): JsonResponse
    {
        $resultado = $action->execute(
            clienteId: $clienteId,
            anio: $request->anio(),
            meses: $request->meses()
        );

        return response()->json($resultado);
    }

    /**
     * Obtiene el historial de visitas en campo y evidencias fotográficas de un cliente.
     */
    public function getVisitasCliente(string|int $clienteId, GetVisitasClienteAction $action): JsonResponse
    {
        $resultado = $action->execute(clienteId: $clienteId);

        return response()->json($resultado);
    }
}