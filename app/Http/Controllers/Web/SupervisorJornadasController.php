<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Jornadas\CloseAllActiveJornadasAction;
use App\Actions\Web\Supervisor\Jornadas\CloseSingleJornadaAction;
use App\Actions\Web\Supervisor\Jornadas\GetJornadasDataAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorJornadasController extends Controller
{
    /**
     * Muestra la tabla de control de jornadas con sus filtros y métricas.
     */
    public function index(Request $request, GetJornadasDataAction $action): Response
    {
        $data = $action->execute(
            date: $request->input('date'),
            status: (string) $request->input('status', 'TODOS')
        );

        return Inertia::render('Supervisor/Jornadas', [
            'vendedores_jornadas' => $data['vendedores_jornadas'],
            'selected_date'       => $data['selected_date'],
            'selected_status'     => $data['selected_status'],
            'total_abiertas'      => $data['total_abiertas'],
            'total_cerradas'      => $data['total_cerradas'],
            'total_sin_iniciar'   => $data['total_sin_iniciar'],
        ]);
    }

    /**
     * Finaliza remotamente la jornada de un vendedor específico.
     */
    public function closeSingle(Request $request, int $userId, CloseSingleJornadaAction $action): RedirectResponse
    {
        $supervisor = $request->user();
        $action->execute($userId, $supervisor ? $supervisor->id : null);

        return back()->with('success', 'Jornada finalizada correctamente.');
    }

    /**
     * Finaliza masivamente todas las jornadas abiertas el día de hoy.
     */
    public function closeAllActive(Request $request, CloseAllActiveJornadasAction $action): RedirectResponse
    {
        $supervisor = $request->user();
        $count = $action->execute($supervisor ? $supervisor->id : null);

        return back()->with('success', "Se han finalizado {$count} jornadas activas.");
    }
}
