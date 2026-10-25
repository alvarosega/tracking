<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Index;

use App\Models\User;
use Carbon\Carbon;

class GetDashboardContextAction
{
    private const DIAS_MAP = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
    ];

    /**
     * Obtiene el contexto operativo y métricas generales del panel central.
     *
     * @return array{
     *     dia_semana: string,
     *     fecha: string,
     *     hora: string,
     *     total_fuerza_ventas: int
     * }
     */
    public function execute(): array
    {
        $today = Carbon::now('America/La_Paz');
        $diaActual = self::DIAS_MAP[$today->dayOfWeekIso] ?? 'Lunes';

        $totalVendedores = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->where('is_active', true)
            ->count();

        return [
            'dia_semana' => $diaActual,
            'fecha' => $today->format('d/m/Y'),
            'hora' => $today->format('H:i'),
            'total_fuerza_ventas' => $totalVendedores,
        ];
    }
}

