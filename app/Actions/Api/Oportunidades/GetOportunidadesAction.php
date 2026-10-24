<?php

declare(strict_types=1);

namespace App\Actions\Api\Oportunidades;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetOportunidadesAction
{
    private const DIAS_MAP = [
        1 => 'LUNES',
        2 => 'MARTES',
        3 => 'MIERCOLES',
        4 => 'JUEVES',
        5 => 'VIERNES',
        6 => 'SABADO',
        7 => 'DOMINGO',
    ];

    /**
     * Obtiene las oportunidades comerciales georreferenciadas dentro del polígono activo de la ruta.
     *
     * @return array{ruta: string, dia: string, total: int, oportunidades: \Illuminate\Support\Collection<int, object>}
     */
    public function execute(User $user, ?string $diaParam = null): array
    {
        $rutaUsuario = trim((string) ($user->ruta ?: $user->username));

        // 1. Determinar día actual en Bolivia (UTC-4) o query param
        $nowBolivia = Carbon::now('America/La_Paz');
        $diaDefault = self::DIAS_MAP[$nowBolivia->dayOfWeekIso];

        $diaSolicitado = $diaParam
            ? mb_strtoupper(trim($diaParam), 'UTF-8')
            : $diaDefault;

        $diaSolicitado = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $diaSolicitado);

        // 2. Validar frontera activa en supervisor
        $frontera = DB::connection('supervisor')
            ->table('dim_fronteras_dias')
            ->where(function ($q) use ($rutaUsuario, $user): void {
                $q->where('ruta', $rutaUsuario);
                if (!empty($user->ruta) && $user->ruta !== $user->username) {
                    $q->orWhere('ruta', trim((string) $user->username));
                }
            })
            ->where('dia', $diaSolicitado)
            ->where('estado', 'ACTIVO')
            ->select('id')
            ->first();

        if (!$frontera) {
            return [
                'ruta' => $rutaUsuario,
                'dia' => $diaSolicitado,
                'total' => 0,
                'oportunidades' => collect(),
            ];
        }

        // 3. Consulta espacial ligera: solo geometría dentro del polígono
        $oportunidades = DB::connection('supervisor')
            ->table('fact_oportunidades as o')
            ->join('dim_fronteras_dias as f', function ($join) use ($rutaUsuario, $diaSolicitado): void {
                $join->on('f.ruta', '=', DB::raw("'" . addslashes($rutaUsuario) . "'"))
                     ->where('f.dia', '=', $diaSolicitado)
                     ->where('f.estado', '=', 'ACTIVO');
            })
            ->whereRaw("ST_Contains(f.poligono, ST_GeomFromText(CONCAT('POINT(', o.longitud, ' ', o.latitud, ')'), 4326))")
            ->select([
                'o.cliid as client_id',
                'o.latitud as latitude',
                'o.longitud as longitude',
            ])
            ->get();

        return [
            'ruta' => $rutaUsuario,
            'dia' => $diaSolicitado,
            'total' => $oportunidades->count(),
            'oportunidades' => $oportunidades,
        ];
    }
}

