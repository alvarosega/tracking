<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use App\Actions\Web\Supervisor\Ruteo\GetCatalogoRutasAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAvanceInitDataAction
{
    public function __construct(
        private readonly GetCatalogoRutasAction $catalogoAction
    ) {}

    /**
     * Devuelve el catálogo inicial para los filtros del módulo de Avance / Cumplimiento.
     *
     * @return array{
     *     catalogo_rutas: \Illuminate\Support\Collection,
     *     canales: array<int, string>,
     *     meses: array<int, array{valor: string, etiqueta: string}>,
     *     fecha_inicio: string,
     *     fecha_corte: string,
     *     mes: string,
     *     dias_calendario: array<string, mixed>
     * }
     */
    public function execute(): array
    {
        $catalogoData = $this->catalogoAction->execute();
        $catalogoRutas = $catalogoData['catalogo'];
        $canales = $catalogoData['canales'];

        // Enriquecer dinámicamente con rutas y canales reales presentes en fact_ventas
        try {
            $rutasExistentes = $catalogoRutas->pluck('ruta')->map(fn ($r) => strtoupper(trim((string) $r)))->flip()->toArray();

            $rutasFactVentas = DB::connection('supervisor')->table('fact_ventas')
                ->whereNotNull('ruta')
                ->where(DB::raw("TRIM(ruta)"), '!=', '')
                ->select([
                    DB::raw("TRIM(UPPER(ruta)) as ruta"),
                    DB::raw("COALESCE(NULLIF(TRIM(UPPER(canal)), ''), 'SIN CANAL') as canal"),
                    DB::raw("COALESCE(NULLIF(TRIM(vendedor), ''), 'No Asignado') as vendedor"),
                ])
                ->distinct()
                ->get();

            $catalogoArray = $catalogoRutas->toArray();
            $canalesSet = array_flip($canales);

            foreach ($rutasFactVentas as $rf) {
                $rUpper = $rf->ruta;
                if (!isset($rutasExistentes[$rUpper])) {
                    $catalogoArray[] = [
                        'ruta' => $rUpper,
                        'canal' => $rf->canal,
                        'vendedor' => $rf->vendedor,
                    ];
                    $rutasExistentes[$rUpper] = true;
                }
                if ($rf->canal !== 'SIN CANAL' && !isset($canalesSet[$rf->canal])) {
                    $canales[] = $rf->canal;
                    $canalesSet[$rf->canal] = true;
                }
            }

            usort($catalogoArray, fn ($a, $b) => strcmp((string) ($a['ruta'] ?? ''), (string) ($b['ruta'] ?? '')));
            sort($canales);
            $catalogoRutas = collect($catalogoArray);
        } catch (\Throwable $e) {
            // Silencioso en caso de no conexión temporal
        }

        // Última fecha registrada en fact_ventas con venta neta
        $maxFechaRaw = DB::connection('supervisor')->table('fact_ventas')
            ->where('monto_final', '!=', 0)
            ->max('fecha_norm');

        $maxFecha = $maxFechaRaw ?: date('Y-m-d');
        $carbonMax = Carbon::parse($maxFecha);
        $fechaCorte = $carbonMax->format('Y-m-d');
        $fechaInicio = $carbonMax->copy()->startOfMonth()->format('Y-m-d');
        $mesYm = $carbonMax->format('Y-m');

        // Meses disponibles
        $meses = $this->obtenerMesesDisponibles();

        // Días calendario y hábiles
        $diasCalendario = $this->calcularDiasHabiles($mesYm, $fechaCorte);

        return [
            'catalogo_rutas' => $catalogoRutas,
            'canales' => $canales,
            'meses' => $meses,
            'fecha_inicio' => $fechaInicio,
            'fecha_corte' => $fechaCorte,
            'mes' => $mesYm,
            'dias_calendario' => $diasCalendario,
        ];
    }

    /**
     * Obtiene la lista de meses disponibles a partir de fact_ventas y fact_metas.
     *
     * @return array<int, array{valor: string, etiqueta: string}>
     */
    private function obtenerMesesDisponibles(): array
    {
        $mesesNombres = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        try {
            $ymVentas = DB::connection('supervisor')->table('fact_ventas')
                ->whereNotNull('fecha_norm')
                ->where('fecha_norm', '!=', '1900-01-01')
                ->selectRaw("DISTINCT DATE_FORMAT(fecha_norm, '%Y-%m') as ym")
                ->pluck('ym')
                ->filter()
                ->toArray();

            $mesesSet = array_unique($ymVentas);
            rsort($mesesSet);

            $resultado = [];
            foreach ($mesesSet as $ym) {
                $partes = explode('-', (string) $ym);
                if (count($partes) === 2) {
                    $nom = $mesesNombres[$partes[1]] ?? $partes[1];
                    $resultado[] = [
                        'valor' => $ym,
                        'etiqueta' => "{$nom} {$partes[0]}",
                    ];
                }
            }

            return $resultado;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Calcula los días hábiles del mes según dim_calendario_mensual y transcurridos.
     *
     * @param string $mesYm
     * @param string|null $fechaCorte
     * @return array{totales: int, transcurridos: int, restantes: int, cerrado: bool}
     */
    public function calcularDiasHabiles(string $mesYm, ?string $fechaCorte = null): array
    {
        try {
            $partes = explode('-', $mesYm);
            $anio = (int) ($partes[0] ?? date('Y'));
            $mes = (int) ($partes[1] ?? date('m'));
        } catch (\Throwable) {
            $anio = (int) date('Y');
            $mes = (int) date('m');
            $mesYm = sprintf('%04d-%02d', $anio, $mes);
        }

        $hoy = Carbon::today();
        $esMesCerrado = ($anio < $hoy->year) || ($anio === $hoy->year && $mes < $hoy->month);

        $rowCal = DB::connection('supervisor')->table('dim_calendario_mensual')
            ->where('year', $anio)
            ->where('month', $mes)
            ->first();

        $diasTotalesRaw = $rowCal ? (int) ($rowCal->dias_habiles ?? 26) : 26;
        // Regla de negocio estándar del módulo: restar 2 días de contingencia
        $diasTotales = max(1, $diasTotalesRaw - 2);

        $corteFiltro = $fechaCorte ?: $hoy->format('Y-m-d');
        $fInicio = "{$mesYm}-01";

        if ($esMesCerrado && (!$fechaCorte || $fechaCorte >= "{$mesYm}-28")) {
            $diasTranscurridos = $diasTotales;
            $diasRestantes = 0;
            $cerrado = true;
        } else {
            $rowOp = DB::connection('supervisor')->table('fact_ventas')
                ->where('fecha_norm', '>=', $fInicio)
                ->where('fecha_norm', '<=', $corteFiltro)
                ->where('monto_final', '>', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                })
                ->selectRaw('COUNT(DISTINCT fecha_norm) as dias_operados')
                ->first();

            $diasOperados = $rowOp ? (int) ($rowOp->dias_operados ?? 1) : 1;
            $diasTranscurridos = max(1, min($diasTotales, $diasOperados));
            $diasRestantes = max(0, $diasTotales - $diasTranscurridos);
            $cerrado = $esMesCerrado && ($diasRestantes === 0);
        }

        return [
            'totales' => $diasTotales,
            'transcurridos' => $diasTranscurridos,
            'restantes' => $diasRestantes,
            'cerrado' => $cerrado,
        ];
    }
}

