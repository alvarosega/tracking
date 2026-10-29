<?php

namespace App\Actions\Web\Supervisor\Ruteo;

use App\Models\Supervisor\FronteraDia;
use App\Models\Supervisor\FronteraRuta;
use App\Models\Supervisor\PanRuteo;
use App\Models\Supervisor\Ruta;
use Illuminate\Support\Facades\DB;

class GetRuteoDataAction
{
    /**
     * Consulta y estructura los puntos de clientes y polígonos fronterizos para el Visor GIS.
     *
     * @param array<string> $canales
     * @param array<string> $rutas
     * @param array<string> $dias
     * @param bool $verFronteraRutas
     * @param bool $verFronteraDias
     * @param bool $soloActivos
     * @param string $modoVista 'original' | 'cambios'
     * @return array{clientes: array, fronteras_rutas: array, fronteras_dias: array, kpis: array}
     */
    public function execute(
        array $canales = [],
        array $rutas = [],
        array $dias = [],
        bool $verFronteraRutas = false,
        bool $verFronteraDias = false,
        bool $soloActivos = true,
        string $modoVista = 'original'
    ): array {
        if (empty($rutas) && empty($canales)) {
            return [
                'clientes' => [],
                'fronteras_rutas' => [],
                'fronteras_dias' => [],
                'kpis' => [
                    'total' => 0,
                    'con_gps' => 0,
                    'sin_gps' => 0,
                    'activos' => 0,
                    'inactivos' => 0,
                    'con_cambios' => 0,
                ],
            ];
        }

        if (empty($rutas) && !empty($canales)) {
            $rutas = Ruta::query()
                ->whereIn('canal', $canales)
                ->pluck('ruta')
                ->unique()
                ->toArray();
        }

        $rutasUpper = array_map(fn($r) => strtoupper(trim((string) $r)), $rutas);

        // 1. Consultar propuestas activas de auditoria_saneamiento_propuestas
        $propuestasRows = DB::connection('supervisor')
            ->table('auditoria_saneamiento_propuestas')
            ->whereIn('estado', ['PENDIENTE', 'APLICADO'])
            ->orderBy('id', 'desc')
            ->get();

        $propuestasMap = [];
        $cidsConPropuestaARutas = [];
        foreach ($propuestasRows as $p) {
            $cid = (int) $p->cliente_id_origen;
            if (!isset($propuestasMap[$cid])) {
                $propuestasMap[$cid] = $p;
                $rProp = strtoupper(trim((string) $p->ruta_propuesta));
                if (!empty($rutasUpper) && in_array($rProp, $rutasUpper, true)) {
                    $cidsConPropuestaARutas[] = $cid;
                }
            }
        }

        // 2. Consulta de clientes en pan_ruteo (Base Original inmutable)
        if ($modoVista === 'cambios' && !empty($cidsConPropuestaARutas)) {
            $queryClientes = PanRuteo::query()->where(function ($q) use ($rutas, $cidsConPropuestaARutas) {
                $q->whereIn('ruta', $rutas)
                  ->orWhereIn('cliente_id', $cidsConPropuestaARutas);
            });
        } else {
            $queryClientes = PanRuteo::query()->whereIn('ruta', $rutas);
        }

        if ($soloActivos) {
            $queryClientes->where(function ($q) {
                $q->where('estado', 'Activo')
                  ->orWhereNull('estado');
            });
        }

        $diasMerge = [];
        if (!empty($dias)) {
            $diasLower = array_map(fn($d) => strtolower(trim((string) $d)), $dias);
            $diasUpper = array_map(fn($d) => strtoupper(trim((string) $d)), $dias);
            $diasMerge = array_values(array_unique(array_merge($diasLower, $diasUpper)));
            if ($modoVista === 'original') {
                $queryClientes->whereIn('dia_norm', $diasMerge);
            }
        }

        $clientesRaw = $queryClientes->select([
            'id', 'cliente_id', 'cliente', 'vendedor', 'tipo_negocio',
            'direccion', 'contacto', 'celular', 'latitud', 'longitud',
            'estado', 'ruta', 'dia_norm'
        ])->get();

        $kpis = [
            'total' => 0,
            'con_gps' => 0,
            'sin_gps' => 0,
            'activos' => 0,
            'inactivos' => 0,
            'con_cambios' => 0,
        ];
        $clientes = [];

        foreach ($clientesRaw as $c) {
            $cid = (int)$c->cliente_id;
            $prop = $propuestasMap[$cid] ?? null;
            $tieneCambio = ($prop !== null);

            $rutaOriginal = strtoupper(trim((string)$c->ruta));
            $diaOriginal = strtoupper(trim((string)$c->dia_norm));

            $rutaEfectiva = $rutaOriginal;
            $diaEfectivo = $diaOriginal;

            if ($tieneCambio && $modoVista === 'cambios') {
                if (!empty($prop->ruta_propuesta)) {
                    $rutaEfectiva = strtoupper(trim((string)$prop->ruta_propuesta));
                }
                if (!empty($prop->dia_propuesto)) {
                    $diaEfectivo = strtoupper(trim((string)$prop->dia_propuesto));
                }

                // Si en modo cambios el cliente fue movido fuera de las rutas seleccionadas, omitirlo
                if (!empty($rutasUpper) && !in_array($rutaEfectiva, $rutasUpper, true)) {
                    continue;
                }
            }

            // Filtrado por días seleccionados
            if (!empty($diasMerge)) {
                $diaCheck = ($modoVista === 'cambios') ? $diaEfectivo : $diaOriginal;
                if (!in_array(strtolower($diaCheck), $diasMerge, true) && !in_array(strtoupper($diaCheck), $diasMerge, true)) {
                    continue;
                }
            }

            $kpis['total']++;

            $esActivo = (strtolower((string)$c->estado) === 'activo' || empty($c->estado));
            if ($esActivo) {
                $kpis['activos']++;
            } else {
                $kpis['inactivos']++;
            }

            $tieneGps = (!empty($c->latitud) && !empty($c->longitud) && (float)$c->latitud != 0.0);
            if ($tieneGps) {
                $kpis['con_gps']++;
            } else {
                $kpis['sin_gps']++;
            }

            if ($tieneCambio) {
                $kpis['con_cambios']++;
            }

            $clientes[] = [
                'id' => $c->id,
                'cliente_id' => $c->cliente_id,
                'nombre' => $c->cliente,
                'tipo_negocio' => $c->tipo_negocio ?? 'General',
                'direccion' => $c->direccion ?? 'Sin dirección registrada',
                'contacto' => $c->contacto,
                'celular' => $c->celular,
                'latitud' => (float)$c->latitud,
                'longitud' => (float)$c->longitud,
                'ruta' => $rutaEfectiva,
                'dia_norm' => $diaEfectivo,
                'ruta_original' => $rutaOriginal,
                'dia_original' => $diaOriginal,
                'ruta_propuesta' => $prop ? (string)$prop->ruta_propuesta : null,
                'dia_propuesto' => $prop ? (string)$prop->dia_propuesto : null,
                'tiene_cambio' => $tieneCambio,
                'propuesta_id' => $prop ? (int)$prop->id : null,
                'propuesta_tipo' => $prop ? (string)$prop->tipo_hallazgo : null,
                'propuesta_estado' => $prop ? (string)$prop->estado : null,
                'propuesta_fecha' => $prop ? (string)$prop->creado_en : null,
                'observaciones_propuesta' => $prop ? (string)$prop->observaciones : null,
                'estado' => $c->estado ?? 'Activo',
                'tiene_gps' => $tieneGps,
            ];
        }

        // Consulta de Polígonos de Rutas
        $fronterasRutas = [];
        if ($verFronteraRutas && !empty($rutas)) {
            $poligonosRutas = FronteraRuta::query()
                ->activos()
                ->whereIn('ruta', $rutas)
                ->select(['ruta', 'canal', DB::raw('ST_AsGeoJSON(poligono) as geojson')])
                ->get();

            foreach ($poligonosRutas as $fr) {
                if (!empty($fr->geojson)) {
                    $fronterasRutas[] = [
                        'ruta' => $fr->ruta,
                        'canal' => $fr->canal,
                        'geojson' => json_decode($fr->geojson, true),
                    ];
                }
            }
        }

        // Consulta de Polígonos de Días
        $fronterasDias = [];
        if ($verFronteraDias && !empty($rutas)) {
            $queryFronterasDias = FronteraDia::query()
                ->activos()
                ->whereIn('ruta', $rutas);

            if (!empty($dias)) {
                $diasLower = array_map(fn($d) => strtolower(trim((string) $d)), $dias);
                $diasUpper = array_map(fn($d) => strtoupper(trim((string) $d)), $dias);
                $diasMerge = array_values(array_unique(array_merge($diasLower, $diasUpper)));
                $queryFronterasDias->whereIn('dia', $diasMerge);
            }

            $poligonosDias = $queryFronterasDias
                ->select(['ruta', 'dia', DB::raw('ST_AsGeoJSON(poligono) as geojson')])
                ->get();

            foreach ($poligonosDias as $fd) {
                if (!empty($fd->geojson)) {
                    $fronterasDias[] = [
                        'ruta' => $fd->ruta,
                        'dia' => strtoupper(trim((string)$fd->dia)),
                        'geojson' => json_decode($fd->geojson, true),
                    ];
                }
            }
        }

        return [
            'clientes' => $clientes,
            'fronteras_rutas' => $fronterasRutas,
            'fronteras_dias' => $fronterasDias,
            'kpis' => $kpis,
        ];
    }
}

