<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Tracking\GetTrackingDiaDataAction;
use App\Actions\Web\Supervisor\Tracking\GetTrackingHistoricoDataAction;
use App\Actions\Web\Supervisor\Tracking\GetTrackingInitDataAction;
use App\Http\Controllers\Controller;
use App\Services\WorkdayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorTrackingController extends Controller
{
    /**
     * VISTA: Submódulo 1 - Patrones Históricos Semanales.
     */
    public function historico(GetTrackingInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Tracking/Historico', [
            'catalogo_rutas' => $initData['catalogo'],
            'canales' => $initData['canales'],
            'fecha_inicio_default' => date('Y-m-d', strtotime('-30 days')),
            'fecha_fin_default' => date('Y-m-d'),
            'dias_disponibles' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
        ]);
    }

    /**
     * DATA: Submódulo 1 - Patrones Históricos Semanales.
     */
    public function historicoData(Request $request, GetTrackingHistoricoDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            fechaInicio: $request->input('fecha_inicio'),
            fechaFin: $request->input('fecha_fin'),
            diaSemana: (string) $request->input('dia', 'Lunes'),
            rutaSeleccionada: $request->input('ruta')
        );

        return response()->json($resultado);
    }

    /**
     * VISTA: Submódulo 2 - Recorrido del Día.
     */
    public function index(GetTrackingInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Tracking/Index', [
            'catalogo_rutas' => $initData['catalogo'],
            'canales' => $initData['canales'],
            'fecha_default' => date('Y-m-d'),
        ]);
    }

    /**
     * DATA: Submódulo 2 - Recorrido del Día + Visitas del Vendedor.
     */
    public function data(Request $request, GetTrackingDiaDataAction $action): JsonResponse
    {
        $resultado = $action->execute(
            fecha: (string) $request->input('fecha', date('Y-m-d')),
            ruta: $request->input('ruta')
        );

        return response()->json($resultado);
    }

    /**
     * Cierre de jornada en vivo desde el panel de telemetría/tracking.
     */
    public function closeWorkday(Request $request, int $userId, WorkdayService $workdayService): JsonResponse
    {
        $supervisor = $request->user();
        $reason = $request->input('reason', 'Cierre remoto ejecutado desde el visor de Tracking');

        $workday = $workdayService->closeWorkdayBySupervisor(
            $userId,
            $supervisor ? $supervisor->id : null,
            $reason
        );

        if (!$workday) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una jornada activa para el día de hoy.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jornada finalizada exitosamente desde Tracking.',
            'workday' => $workday,
        ]);
    }
}
