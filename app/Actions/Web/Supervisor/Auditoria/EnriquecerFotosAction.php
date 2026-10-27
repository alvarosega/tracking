<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Auditoria;

use Illuminate\Support\Facades\DB;

class EnriquecerFotosAction
{
    /**
     * Enriquece un arreglo de registros con conteo y foto más reciente desde visitas.
     *
     * @param array<int, array<string, mixed>> $registros
     * @param string $keyId
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $registros, string $keyId = 'cliente_id'): array
    {
        if (empty($registros)) {
            return $registros;
        }

        $cids = [];
        foreach ($registros as $r) {
            $cid = $r[$keyId] ?? null;
            if ($cid !== null && is_numeric($cid) && (int) $cid > 0) {
                $cids[] = (int) $cid;
            }
        }

        $cids = array_values(array_unique($cids));
        if (empty($cids)) {
            foreach ($registros as &$reg) {
                $reg['cant_fotos'] = 0;
                $reg['foto_reciente_url'] = null;
                $reg['foto_reciente_fecha'] = null;
            }
            unset($reg);
            return $registros;
        }

        $mapaFotos = [];
        try {
            $visitas = DB::table('visitas')
                ->whereIn('client_id', $cids)
                ->whereNotNull('photo_path')
                ->where('photo_path', '!=', '')
                ->select([
                    'client_id',
                    DB::raw('COUNT(*) as cant_fotos'),
                    DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(photo_path ORDER BY visited_at DESC SEPARATOR '###'), '###', 1) as foto_reciente"),
                    DB::raw('MAX(visited_at) as ultima_fecha'),
                ])
                ->groupBy('client_id')
                ->get();

            foreach ($visitas as $v) {
                $cid = (int) $v->client_id;
                $p = ltrim(str_replace('\\', '/', (string) ($v->foto_reciente ?? '')), '/');
                $url = null;
                if ($p !== '') {
                    if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
                        $url = $p;
                    } elseif (str_starts_with($p, 'storage/')) {
                        $url = '/' . $p;
                    } else {
                        $url = '/storage/' . $p;
                    }
                }

                $fec = $v->ultima_fecha ? date('Y-m-d H:i', strtotime((string) $v->ultima_fecha)) : '-';

                $mapaFotos[$cid] = [
                    'cant_fotos' => (int) $v->cant_fotos,
                    'foto_reciente_url' => $url,
                    'foto_reciente_fecha' => $fec,
                ];
            }
        } catch (\Throwable $e) {
            // Silently fallback if error
        }

        foreach ($registros as &$r) {
            $cid = isset($r[$keyId]) && is_numeric($r[$keyId]) ? (int) $r[$keyId] : null;
            if ($cid !== null && isset($mapaFotos[$cid])) {
                $r['cant_fotos'] = $mapaFotos[$cid]['cant_fotos'];
                $r['foto_reciente_url'] = $mapaFotos[$cid]['foto_reciente_url'];
                $r['foto_reciente_fecha'] = $mapaFotos[$cid]['foto_reciente_fecha'];
            } else {
                $r['cant_fotos'] = 0;
                $r['foto_reciente_url'] = null;
                $r['foto_reciente_fecha'] = null;
            }
        }
        unset($r);

        return $registros;
    }
}

