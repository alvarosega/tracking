<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlanRuteo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorRuteoController extends Controller
{
    public function index(Request $request): Response
    {
        $diasMap = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];
        $today = Carbon::now('America/La_Paz');
        $diaActual = $diasMap[$today->dayOfWeekIso] ?? 'Lunes';

        // Rutas existentes en el sistema (usuarios vendedores)
        $rutasBase = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->pluck('username')
            ->sort()
            ->values()
            ->all();

        // Opciones de rutas incluyendo 'TODAS'
        $rutas = array_merge(['TODAS'], $rutasBase);

        $selectedRoute = $request->input('route', 'TODAS');
        $selectedDay = $request->input('day', $diaActual);

        $query = PlanRuteo::query()->select([
            'id',
            'client_id',
            'client_name',
            'address',
            'reference',
            'latitude',
            'longitude',
            'status',
            'route',
            'day',
        ]);

        // Filtro de Ruta
        if ($selectedRoute !== 'TODAS') {
            $query->where('route', $selectedRoute);
        }

        // Filtro de Día
        if ($selectedDay !== 'TODOS') {
            $query->where(function ($q) use ($selectedDay) {
                if ($selectedDay === 'Miércoles') {
                    $q->whereIn('day', ['Miércoles', 'Miercoles']);
                } elseif ($selectedDay === 'Sábado') {
                    $q->whereIn('day', ['Sábado', 'Sabado']);
                } else {
                    $q->where('day', $selectedDay);
                }
            });
        }

        $clientes = $query->orderBy('route', 'asc')
            ->orderBy('client_id', 'asc')
            ->get();

        return Inertia::render('Supervisor/Ruteo', [
            'rutas' => $rutas,
            'dias' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo', 'TODOS'],
            'selected_route' => $selectedRoute,
            'selected_day' => $selectedDay,
            'clientes' => $clientes,
        ]);
    }
}