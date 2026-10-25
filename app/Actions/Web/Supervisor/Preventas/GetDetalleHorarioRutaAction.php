<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Preventas;

use App\Models\Supervisor\FactPreventa;
use App\Models\Visita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetDetalleHorarioRutaAction
{
    /**
     * Obtiene el recorrido cronológico detallado de una ruta cruzando sistema ERP y app móvil.
     *
     * @param string $ruta
     * @param string|null $fecha
     * @return array<string, mixed>
     */
    public function execute(string $ruta, ?string $fecha = null): array
    {
        $fechaNorm = $fecha && trim($fecha) !== ''
            ? trim($fecha)
            : (FactPreventa::query()->max('fecha_norm') ?: date('Y-m-d'));

        $rutaClean = strtoupper(trim($ruta));
        $vendedor = 'No Asignado';
        $eventos = [];

        try {
            $dimInfo = DB::connection('supervisor')->table('dim_rutas')
                ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
                ->first();

            if ($dimInfo && $dimInfo->vendedor) {
                $vendedor = trim($dimInfo->vendedor);
            }

            // 1. Marcajes del sistema central (fact_visitas)
            $filasSistema = DB::connection('supervisor')->table('fact_visitas as fv')
                ->where('fv.fecha_norm', $fechaNorm)
                ->where(DB::raw("LOWER(TRIM(fv.empleado_norm))"), mb_strtolower(trim($vendedor)))
                ->select([
                    DB::raw("COALESCE(fv.cliente, 'CLIENTE S/N') as cliente"),
                    DB::raw("COALESCE(fv.tipo, 'Visita') as tipo"),
                    DB::raw("COALESCE(fv.check_in, '00:00:00') as check_in"),
                    DB::raw("COALESCE(fv.check_out, '00:00:00') as check_out"),
                    DB::raw("COALESCE(fv.tiempo_pdv, '00:00:00') as tiempo_pdv"),
                    DB::raw("COALESCE(fv.zona, '-') as zona"),
                    DB::raw("CAST(COALESCE(fv.monto, 0.0) AS DECIMAL(12, 2)) as monto"),
                ])
                ->orderBy('fv.check_in', 'asc')
                ->get();

            foreach ($filasSistema as $fs) {
                $hIn = trim((string) $fs->check_in);
                if (strlen($hIn) === 5) {
                    $hIn .= ':00';
                }
                $eventos[] = [
                    'hora_orden' => $hIn,
                    'hora_inicio' => $hIn,
                    'hora_fin' => trim((string) $fs->check_out) ?: '-',
                    'tiempo_pdv' => trim((string) $fs->tiempo_pdv) ?: '-',
                    'cliente' => $fs->cliente,
                    'client_id' => null,
                    'tipo' => $fs->tipo,
                    'zona' => $fs->zona,
                    'monto' => (float) ($fs->monto ?: 0.0),
                    'foto_url' => null,
                    'fuente' => 'SISTEMA',
                ];
            }

            // 2. Marcajes de la app móvil (tabla local visitas)
            $fInicioDia = "{$fechaNorm} 00:00:00";
            $fFinDia = "{$fechaNorm} 23:59:59";

            $filasMovil = Visita::query()
                ->whereRaw("TRIM(UPPER(route)) = ?", [$rutaClean])
                ->whereBetween('visited_at', [$fInicioDia, $fFinDia])
                ->orderBy('visited_at', 'asc')
                ->get();

            $clientIds = $filasMovil->pluck('client_id')->filter()->unique()->toArray();
            $nombresPanMap = [];

            if (!empty($clientIds)) {
                $nombresPanMap = DB::connection('supervisor')->table('pan_ruteo')
                    ->whereIn('cliente_id', $clientIds)
                    ->select('cliente_id', DB::raw("MAX(cliente) as nombre_cliente"))
                    ->groupBy('cliente_id')
                    ->pluck('nombre_cliente', 'cliente_id')
                    ->toArray();
            }

            foreach ($filasMovil as $fm) {
                $cid = $fm->client_id ? (int) $fm->client_id : null;
                $nombreCliente = ($cid && isset($nombresPanMap[$cid])) ? $nombresPanMap[$cid] : null;

                if (!$nombreCliente) {
                    $nombreCliente = $fm->opportunity_client_name ?: ($cid ? "CLIENTE #{$cid}" : 'CLIENTE APP S/N');
                }

                $horaM = $fm->visited_at ? $fm->visited_at->format('H:i:s') : '00:00:00';
                $fotoRel = ltrim((string) $fm->photo_path, '/\\');
                $fotoUrl = $fotoRel ? (str_starts_with($fotoRel, 'http') ? $fotoRel : "/storage/{$fotoRel}") : null;

                $tipoVisita = $fm->is_opportunity ? 'Oportunidad' : 'Visita App';
                if ($fm->comments) {
                    $tipoVisita .= " ({$fm->comments})";
                }

                $eventos[] = [
                    'hora_orden' => $horaM,
                    'hora_inicio' => $horaM,
                    'hora_fin' => '-',
                    'tiempo_pdv' => '-',
                    'cliente' => $nombreCliente,
                    'client_id' => $cid,
                    'tipo' => $tipoVisita,
                    'zona' => '-',
                    'monto' => 0.0,
                    'foto_url' => $fotoUrl,
                    'fuente' => 'APP MÓVIL',
                ];
            }

            usort($eventos, fn ($a, $b) => strcmp($a['hora_orden'], $b['hora_orden']));

            foreach ($eventos as $i => &$ev) {
                $ev['orden'] = $i + 1;
            }
            unset($ev);

            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'total_eventos' => count($eventos),
                'eventos' => $eventos,
            ];
        } catch (\Throwable $e) {
            Log::warning("Error en GetDetalleHorarioRutaAction para {$ruta}: " . $e->getMessage());
            return [
                'ruta' => $ruta,
                'vendedor' => $vendedor,
                'fecha' => $fechaNorm,
                'total_eventos' => 0,
                'eventos' => [],
            ];
        }
    }
}

