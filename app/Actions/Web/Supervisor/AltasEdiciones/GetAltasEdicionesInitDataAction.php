<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\AltasEdiciones;

use Illuminate\Support\Facades\DB;

class GetAltasEdicionesInitDataAction
{
    /**
     * Obtiene el catálogo maestro de rutas, canales y los metadatos de tipos y estados
     * de saneamiento_base para la inicialización del módulo Altas & Ediciones.
     *
     * @return array{
     *     catalogo_rutas: \Illuminate\Support\Collection,
     *     canales: array<int, string>,
     *     tipos_registro: array<int, string>,
     *     estados_revision: array<int, string>
     * }
     */
    public function execute(): array
    {
        // 1. Catálogo de Rutas y Canales desde supervisor.dim_rutas
        $catalogo = DB::connection('supervisor')->table('dim_rutas')
            ->select('ruta', 'canal', 'vendedor')
            ->distinct()
            ->orderBy('ruta')
            ->get();

        $canales = $catalogo->pluck('canal')->filter()->unique()->values()->toArray();

        // Agregar rutas presentes en saneamiento_base que no estén en dim_rutas
        $rutasExistentes = $catalogo->pluck('ruta')->toArray();
        $rutasSb = DB::connection('supervisor')->table('saneamiento_base')
            ->select('route')
            ->distinct()
            ->whereNotNull('route')
            ->where('route', '!=', '')
            ->pluck('route')
            ->toArray();

        $rutasFaltantes = [];
        foreach ($rutasSb as $rsb) {
            if (!in_array($rsb, $rutasExistentes, true)) {
                $canalDeducido = 'TDB';
                foreach ($canales as $cn) {
                    if (str_starts_with(strtoupper(trim((string) $rsb)), strtoupper(trim((string) $cn)))) {
                        $canalDeducido = $cn;
                        break;
                    }
                }
                $rutasFaltantes[] = (object) [
                    'ruta' => $rsb,
                    'canal' => $canalDeducido,
                    'vendedor' => 'Sin asignar',
                ];
            }
        }
        if (!empty($rutasFaltantes)) {
            $catalogo = $catalogo->concat($rutasFaltantes)->sortBy('ruta')->values();
        }

        // 2. Extraer dinámicamente los tipos de registro existentes
        $tiposRegistro = DB::connection('supervisor')->table('saneamiento_base')
            ->select('tipo_registro')
            ->distinct()
            ->whereNotNull('tipo_registro')
            ->where('tipo_registro', '!=', '')
            ->pluck('tipo_registro')
            ->values()
            ->toArray();

        // 3. Extraer dinámicamente los estados de revisión asegurando PENDIENTE, APROBADO, RECHAZADO
        $estadosBase = ['PENDIENTE', 'APROBADO', 'RECHAZADO'];
        $estadosExistentes = DB::connection('supervisor')->table('saneamiento_base')
            ->select('estado_revision')
            ->distinct()
            ->whereNotNull('estado_revision')
            ->where('estado_revision', '!=', '')
            ->pluck('estado_revision')
            ->values()
            ->toArray();

        $estadosRevision = array_values(array_unique(array_merge($estadosBase, $estadosExistentes)));

        return [
            'catalogo_rutas' => $catalogo,
            'canales' => $canales,
            'tipos_registro' => $tiposRegistro,
            'estados_revision' => $estadosRevision,
        ];
    }
}

