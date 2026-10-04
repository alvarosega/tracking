<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorIndexController extends Controller
{
    public function index(): Response
    {
        $diasMap = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];
        
        $today = Carbon::now('America/La_Paz');
        $diaActual = $diasMap[$today->dayOfWeekIso];
        $todayDate = $today->format('d/m/Y');
        $horaActual = $today->format('H:i');

        $totalVendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->where('is_active', true)
            ->count();

        return Inertia::render('Supervisor/Index', [
            'contexto' => [
                'dia_semana' => $diaActual,
                'fecha' => $todayDate,
                'hora' => $horaActual,
                'total_fuerza_ventas' => $totalVendedores,
            ],
        ]);
    }
}