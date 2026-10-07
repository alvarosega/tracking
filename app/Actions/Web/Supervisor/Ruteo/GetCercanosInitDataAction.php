<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FactVenta;
use App\Models\Supervisor\Ruta;

class GetCercanosInitDataAction
{
    /**
     * Obtiene los metadatos y catálogos iniciales para la vista de Clientes Cercanos.
     *
     * @return array{catalogo_rutas: \Illuminate\Database\Eloquent\Collection, canales: array, anios_disponibles: array, anio_default: int, meses_default: array}
     */
    public function execute(): array
    {
        $catalogo = Ruta::query()
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Extraer los últimos 3 meses que realmente tienen compras registradas
        $ultimosMesesConDatos = FactVenta::query()
            ->validas()
            ->whereNotNull('fecha_norm')
            ->whereNotNull('mes')
            ->selectRaw('YEAR(fecha_norm) as anio, mes')
            ->distinct()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->limit(3)
            ->get();

        $anioPorDefecto = !empty($ultimosMesesConDatos) && $ultimosMesesConDatos->isNotEmpty()
            ? (int) $ultimosMesesConDatos->first()->anio
            : (int) date('Y');

        $mesesPorDefecto = !empty($ultimosMesesConDatos) && $ultimosMesesConDatos->isNotEmpty()
            ? $ultimosMesesConDatos->pluck('mes')->map(fn($m) => (int) $m)->values()->toArray()
            : [1, 2, 3];

        // Todos los años registrados para el selector
        $aniosDisponibles = FactVenta::query()
            ->validas()
            ->whereNotNull('fecha_norm')
            ->selectRaw('DISTINCT YEAR(fecha_norm) as anio')
            ->orderByDesc('anio')
            ->pluck('anio')
            ->toArray();

        return [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'anios_disponibles' => !empty($aniosDisponibles) ? $aniosDisponibles : [$anioPorDefecto],
            'anio_default' => $anioPorDefecto,
            'meses_default' => $mesesPorDefecto,
        ];
    }
}

