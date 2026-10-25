<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Visitas\GetFotosClienteAction;
use App\Actions\Web\Supervisor\Visitas\GetVisitasAvanceDataAction;
use App\Actions\Web\Supervisor\Visitas\GetVisitasDataAction;
use App\Actions\Web\Supervisor\Visitas\GetVisitasInitDataAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorVisitasController extends Controller
{
    /**
     * Muestra la vista principal de Auditoría de Visitas con sus catálogos.
     */
    public function index(GetVisitasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Visitas/Index', [
            'catalogo_rutas' => $initData['catalogo'],
            'canales' => $initData['canales'],
            'dias_disponibles' => $initData['dias_disponibles'],
        ]);
    }

    /**
     * Consulta consolidada de clientes con auditoría fotográfica según filtros.
     */
    public function data(Request $request, GetVisitasDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            dias: (array) $request->input('dias', []),
            canales: (array) $request->input('canales', []),
            rutas: (array) $request->input('rutas', []),
            incluirInactivosConFoto: (bool) $request->input('inactivos_con_foto', false)
        );

        return response()->json($resultado);
    }

    /**
     * Muestra la vista de Avance en Terreno diario.
     */
    public function avance(GetVisitasInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Visitas/Avance', [
            'catalogo_rutas' => $initData['catalogo'],
            'canales' => $initData['canales'],
            'fecha_default' => date('Y-m-d'),
        ]);
    }

    /**
     * Devuelve el avance en vivo de rutas y visitas tomadas durante la jornada.
     */
    public function avanceData(Request $request, GetVisitasAvanceDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            fecha: (string) $request->input('fecha', date('Y-m-d')),
            canales: (array) $request->input('canales', []),
            rutas: (array) $request->input('rutas', [])
        );

        return response()->json($resultado);
    }

    /**
     * Devuelve el historial fotográfico completo de un cliente específico.
     */
    public function fotosCliente(int $clienteId, GetFotosClienteAction $action): JsonResponse
    {
        $visitas = $action->execute($clienteId);

        return response()->json([
            'visitas' => $visitas,
        ]);
    }
}
