<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use Illuminate\Support\Facades\DB;

class GetAuditoriaCatalogoAction
{
    /**
     * Retorna canales y rutas oficiales únicos desde dim_rutas y archivos base desde auditoria_ruteo_cambios.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $conn = DB::connection('supervisor');

        $canales = $conn->table('dim_rutas')
            ->selectRaw('DISTINCT TRIM(canal) AS canal')
            ->whereNotNull('canal')
            ->whereRaw("TRIM(canal) != ''")
            ->orderBy('canal', 'asc')
            ->pluck('canal')
            ->toArray();

        $rutas = $conn->table('dim_rutas')
            ->selectRaw("DISTINCT TRIM(UPPER(ruta)) AS ruta, TRIM(canal) AS canal, COALESCE(TRIM(vendedor), 'Sin Asignar') AS vendedor")
            ->whereNotNull('ruta')
            ->whereRaw("TRIM(ruta) != ''")
            ->whereNotNull('canal')
            ->whereRaw("TRIM(canal) != ''")
            ->orderBy('canal', 'asc')
            ->orderBy('ruta', 'asc')
            ->get()
            ->map(fn($r) => [
                'ruta' => (string) $r->ruta,
                'canal' => (string) $r->canal,
                'vendedor' => (string) $r->vendedor,
            ])
            ->toArray();

        $archivosBase = $conn->table('auditoria_ruteo_cambios')
            ->selectRaw('DISTINCT archivo_origen')
            ->whereNotNull('archivo_origen')
            ->whereRaw("TRIM(archivo_origen) != ''")
            ->orderByDesc('fecha_auditoria')
            ->orderByDesc('archivo_origen')
            ->pluck('archivo_origen')
            ->toArray();

        return [
            'canales' => $canales,
            'rutas' => $rutas,
            'archivos_base' => $archivosBase,
        ];
    }
}

