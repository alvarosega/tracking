<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Index\GetDashboardContextAction;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorIndexController extends Controller
{
    /**
     * Muestra el panel general de supervisión con métricas y estado del sistema.
     */
    public function index(GetDashboardContextAction $action): Response
    {
        return Inertia::render('Supervisor/Index', [
            'contexto' => $action->execute(),
        ]);
    }
}
