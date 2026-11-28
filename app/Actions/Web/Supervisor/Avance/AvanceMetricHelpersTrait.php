<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Avance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait AvanceMetricHelpersTrait
{
    /**
     * Reglas de umbral mínimo facturado en el período para que un cliente cuente como Cobertura según su Canal.
     * Centralizado y editable directamente en el código para su rápida modificación y mantenimiento:
     * - MAY:  monto >= 3000.0 (Mayoristas)
     * - MZO:  monto >= 1500.0 (Mercados / Macrozonas general)
     * - PROV: monto >= 350.0  (Provincias)
     * - PRT:  monto > 50.0    (Porteo / Preventa)
     * - TDB:  monto >= 150.0  (Tiendas de Barrio)
     * - SC:   monto > 0.0     (Sin Canal / General)
     *
     * @var array<string, array{min: float, operador: string, descripcion: string}>
     */
    public static array $reglasCoberturaCanal = [
        'MAY'  => ['min' => 3000.0, 'operador' => '>=', 'descripcion' => 'Mayoristas (>= 3000 Bs)'],
        'MZO'  => ['min' => 1500.0, 'operador' => '>=', 'descripcion' => 'Mercados / Macrozonas (>= 1500 Bs)'],
        'PROV' => ['min' => 350.0,  'operador' => '>=', 'descripcion' => 'Provincias (>= 350 Bs)'],
        'PRT'  => ['min' => 50.0,   'operador' => '>',  'descripcion' => 'Porteo (> 50 Bs)'],
        'TDB'  => ['min' => 150.0,  'operador' => '>=', 'descripcion' => 'Tiendas de Barrio (>= 150 Bs)'],
        'SC'   => ['min' => 0.0,    'operador' => '>',  'descripcion' => 'Sin Canal / General (> 0 Bs)'],
    ];

    /**
     * Reglas de excepción específicas por ruta para cobertura.
     * Permite sobreescribir el umbral del canal para rutas particulares.
     * - TDB 8 MM: monto >= 1000.0 (compra mínima en MZO para esta ruta)
     *
     * @var array<string, array{min: float, operador: string, descripcion: string}>
     */
    public static array $reglasCoberturaRuta = [
        'TDB 8 MM' => ['min' => 1000.0, 'operador' => '>=', 'descripcion' => 'Ruta TDB 8 MM (>= 1000 Bs)'],
    ];

    /**
     * Mapa de alias o sinónimos comunes de canales para asegurar coincidencia exacta con dim_rutas.
     *
     * @var array<string, string>
     */
    public static array $aliasCanales = [
        'MAYORISTA'  => 'MAY',
        'MAYORISTAS' => 'MAY',
        'MERCADO'    => 'MZO',
        'MERCADOS'   => 'MZO',
        'MACROZONA'  => 'MZO',
        'MACROZONAS' => 'MZO',
        'PROVINCIA'  => 'PROV',
        'PROVINCIAS' => 'PROV',
        'PORTEO'     => 'PRT',
        'TIENDA'     => 'TDB',
        'TIENDAS'    => 'TDB',
        'SIN CANAL'  => 'SC',
    ];

    /**
     * Obtiene la regla de umbral de cobertura configurada para un canal o ruta específica.
     *
     * @return array{min: float, operador: string, descripcion: string}
     */
    public function obtenerUmbralCoberturaCanal(?string $canal, ?string $ruta = null): array
    {
        // 1. Prioridad: Excepciones específicas por ruta (insensible a mayúsculas y espacios)
        if ($ruta !== null && trim($ruta) !== '') {
            $rClean = strtoupper(trim((string) preg_replace('/\s+/', ' ', $ruta)));
            $rNoSpaces = str_replace(' ', '', $rClean);

            foreach (self::$reglasCoberturaRuta as $rKey => $reglaRuta) {
                $rKeyClean = strtoupper(trim((string) preg_replace('/\s+/', ' ', $rKey)));
                $rKeyNoSpaces = str_replace(' ', '', $rKeyClean);

                if ($rClean === $rKeyClean || $rNoSpaces === $rKeyNoSpaces) {
                    return $reglaRuta;
                }
            }
        }

        // 2. Regla según canal
        $c = strtoupper(trim((string) $canal));
        if (isset(self::$reglasCoberturaCanal[$c])) {
            return self::$reglasCoberturaCanal[$c];
        }
        if (isset(self::$aliasCanales[$c]) && isset(self::$reglasCoberturaCanal[self::$aliasCanales[$c]])) {
            return self::$reglasCoberturaCanal[self::$aliasCanales[$c]];
        }
        return self::$reglasCoberturaCanal['SC'] ?? ['min' => 0.0, 'operador' => '>', 'descripcion' => 'General (> 0 Bs)'];
    }

    /**
     * Evalúa si un monto facturado acumulado cumple con el umbral de Cobertura para un canal y ruta específicos.
     */
    public function clienteCumpleCoberturaCanal(float $monto, ?string $canal, ?string $ruta = null): bool
    {
        if ($monto <= 0.0001) {
            return false;
        }

        $regla = $this->obtenerUmbralCoberturaCanal($canal, $ruta);
        $min = (float) ($regla['min'] ?? 0.0);
        $op = (string) ($regla['operador'] ?? '>=');

        return match ($op) {
            '>' => $monto > $min,
            '>=' => $monto >= $min,
            default => $monto >= $min,
        };
    }

    protected static array $codigosClinical = [
        '64880313',
        '64819237',
        '64762998',
        '64880312',
        '64880351',
        '69785525',
        '69785524',
        '64795481',
    ];

    /**
     * Evalúa si una transacción califica para un KPI específico.
     */
    public function aplicaTransaccion(string $kpiClave, string $producto, string $j3, string $codigo, string $categoria): bool
    {
        $prod = mb_strtolower(trim($producto));
        $cat = mb_strtolower(trim($j3));
        $categoriastr = mb_strtolower(trim($categoria));
        $cod = trim($codigo);

        return match ($kpiClave) {
            'vol_general', 'cob_general', 'cob_core' => true,
            'vol_seda', 'cob_seda' => (
                str_contains($prod, 'toque de seda') ||
                str_contains($prod, 'toque seda') ||
                str_contains($cat, 'toque de seda') ||
                str_contains($categoriastr, 'toque de seda') ||
                str_contains($prod, 'cr balance') ||
                str_contains($prod, 'crema balance') ||
                str_contains($prod, 'cr. balance') ||
                str_contains($prod, 'cr.balance') ||
                str_contains($cat, 'cr balance') ||
                str_contains($cat, 'crema balance') ||
                str_contains($categoriastr, 'cr balance') ||
                str_contains($categoriastr, 'crema balance')
            ),
            'vol_clinical', 'cob_clinical' => (
                in_array($cod, self::$codigosClinical, true) ||
                str_contains($prod, 'clinical')
            ),
            default => true,
        };
    }

    /**
     * Calcula los últimos N meses cerrados previos a la fecha de corte.
     *
     * @return array<int, array{ym: string, nombre: string, corto: string, inicio: string, fin: string}>
     */
    public function obtenerMesesCerrados(Carbon $carbonRef, int $numMeses = 3): array
    {
        $numMeses = max(1, min(8, $numMeses));
        $mesesEs = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mesesAbr = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $inicioMesCorte = $carbonRef->copy()->startOfMonth()->format('Y-m-d');

        $mesesYm = [];
        try {
            $mesesYm = DB::connection('supervisor')->table('fact_ventas')
                ->whereNotNull('fecha_norm')
                ->where('fecha_norm', '<', $inicioMesCorte)
                ->where('fecha_norm', '!=', '1900-01-01')
                ->where('monto_final', '!=', 0)
                ->where(function ($q): void {
                    $q->whereNull('revertida')->orWhere('revertida', '!=', 'Si');
                })
                ->selectRaw("DISTINCT DATE_FORMAT(fecha_norm, '%Y-%m') as ym")
                ->orderBy('ym', 'desc')
                ->limit($numMeses)
                ->pluck('ym')
                ->toArray();
        } catch (\Throwable $e) {
            $mesesYm = [];
        }

        if (count($mesesYm) < $numMeses) {
            $mesRef = $carbonRef->copy()->startOfMonth();
            for ($i = 1; $i <= $numMeses; $i++) {
                $ym = $mesRef->copy()->subMonthsNoOverflow($i)->format('Y-m');
                if (!in_array($ym, $mesesYm, true)) {
                    $mesesYm[] = $ym;
                }
                if (count($mesesYm) >= $numMeses) {
                    break;
                }
            }
        }

        sort($mesesYm);
        if (count($mesesYm) > $numMeses) {
            $mesesYm = array_slice($mesesYm, -$numMeses);
        }

        $meses = [];
        foreach ($mesesYm as $ym) {
            $dt = Carbon::parse($ym . '-01');
            $mNum = (int) $dt->format('m');
            $anio = $dt->format('Y');
            $meses[] = [
                'ym' => $ym,
                'nombre' => ($mesesEs[$mNum] ?? '') . ' ' . $anio,
                'corto' => ($mesesAbr[$mNum] ?? '') . ' ' . substr($anio, -2),
                'inicio' => $dt->copy()->startOfMonth()->format('Y-m-d'),
                'fin' => $dt->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }

        return $meses;
    }

    /**
     * Obtiene el objetivo de venta mensual de una ruta en fact_metas para un mes dado (YYYY-MM).
     */
    public function obtenerMetaMesRuta(string $ruta, string $mesYm): float
    {
        $partes = explode('-', $mesYm);
        $anio = (int) ($partes[0] ?? date('Y'));
        $mes = (int) ($partes[1] ?? date('m'));
        $patrones = $this->construirPatronesMeta($anio, $mes);
        $rutaClean = strtoupper(trim($ruta));

        $val = DB::connection('supervisor')->table('fact_metas')
            ->where(DB::raw("TRIM(UPPER(ruta))"), $rutaClean)
            ->whereIn(DB::raw("LOWER(TRIM(mes))"), $patrones)
            ->max(DB::raw("CAST(COALESCE(objetivo_de_venta, 0.0) AS DECIMAL(12, 2))"));

        return round((float) ($val ?? 0.0), 2);
    }

    public function construirPatronesMeta(int $year, int $month): array
    {
        $mesesEspAbr = [
            1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
        ];
        $mesesNom = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];

        $yy = substr((string) $year, -2);
        $abr = $mesesEspAbr[$month] ?? '';
        $mes2d = sprintf('%02d', $month);
        $nom = $mesesNom[$month - 1] ?? '';

        return [
            "{$nom} {$year}", "{$nom}_{$year}", "{$year}-{$mes2d}", "{$year}-{$mes2d}-01",
            "{$abr}-{$yy}", "{$abr}.-{$yy}", "{$abr}_{$yy}",
            $nom, $abr, $mes2d, (string) $month,
        ];
    }

    /**
     * ALGORITMO AsignarCuotasMensualesPorCliente (Waterfilling con Piso de 150 Bs)
     */
    public function asignarCuotasMensualesPorCliente(
        float $objetivoRuta,
        float $pisoMinimo,
        array $ventasHistoricasMap
    ): array {
        $totalClientes = count($ventasHistoricasMap);
        if ($totalClientes === 0) {
            return [];
        }

        if ($objetivoRuta <= 0.0) {
            return array_fill_keys(array_keys($ventasHistoricasMap), $pisoMinimo);
        }

        $pisoTotalRequerido = $totalClientes * $pisoMinimo;
        if ($objetivoRuta < $pisoTotalRequerido) {
            return array_fill_keys(array_keys($ventasHistoricasMap), $pisoMinimo);
        }

        $presupuestoRestante = $objetivoRuta;
        $cuotasAsignadas = [];
        $fijados = [];
        $clientesRestantes = array_keys($ventasHistoricasMap);

        foreach ($clientesRestantes as $cid) {
            $cuotasAsignadas[$cid] = 0.0;
            $fijados[$cid] = false;
        }

        do {
            $hayClientesBajoPiso = false;
            $sumaHistoricaRestante = 0.0;
            foreach ($clientesRestantes as $cid) {
                $sumaHistoricaRestante += (float) ($ventasHistoricasMap[$cid] ?? 0.0);
            }

            $nuevosFijadosEnEstaRonda = [];
            $cantRestantes = count($clientesRestantes);

            foreach ($clientesRestantes as $cid) {
                $vHist = (float) ($ventasHistoricasMap[$cid] ?? 0.0);
                if ($sumaHistoricaRestante > 0.0) {
                    $pesoRelativo = $vHist / $sumaHistoricaRestante;
                    $cuotaCalculada = $presupuestoRestante * $pesoRelativo;
                } else {
                    $cuotaCalculada = $cantRestantes > 0 ? ($presupuestoRestante / $cantRestantes) : $pisoMinimo;
                }

                if ($cuotaCalculada < $pisoMinimo) {
                    $cuotasAsignadas[$cid] = $pisoMinimo;
                    $fijados[$cid] = true;
                    $presupuestoRestante -= $pisoMinimo;
                    $hayClientesBajoPiso = true;
                    $nuevosFijadosEnEstaRonda[$cid] = true;
                } else {
                    $cuotasAsignadas[$cid] = $cuotaCalculada;
                }
            }

            $clientesRestantes = array_values(array_filter(
                $clientesRestantes,
                fn ($cid) => empty($nuevosFijadosEnEstaRonda[$cid]) && !$fijados[$cid]
            ));

        } while ($hayClientesBajoPiso && count($clientesRestantes) > 0);

        $sumaFinalCuotas = 0.0;
        foreach ($cuotasAsignadas as $cid => $cuota) {
            $cuotaRedondeada = round($cuota, 2);
            $cuotasAsignadas[$cid] = $cuotaRedondeada;
            $sumaFinalCuotas += $cuotaRedondeada;
        }

        $diferenciaCentavos = round($objetivoRuta - $sumaFinalCuotas, 2);
        if ($diferenciaCentavos != 0.0 && !empty($cuotasAsignadas)) {
            $clienteMayorCuotaId = null;
            $maxCuota = -1.0;
            foreach ($cuotasAsignadas as $cid => $cuota) {
                if ($cuota > $maxCuota) {
                    $maxCuota = $cuota;
                    $clienteMayorCuotaId = $cid;
                }
            }

            if ($clienteMayorCuotaId !== null) {
                $cuotasAsignadas[$clienteMayorCuotaId] = round($cuotasAsignadas[$clienteMayorCuotaId] + $diferenciaCentavos, 2);
            }
        }

        return $cuotasAsignadas;
    }

    /**
     * Normaliza el nombre del vendedor para coincidencia exacta entre dim_rutas y fact_ventas.
     */
    public function normalizarVendedor(?string $vendedor): string
    {
        if (empty($vendedor)) {
            return '';
        }
        $v = trim((string) preg_replace('/\s+/', ' ', mb_strtolower($vendedor)));
        $v = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $v);
        $aliases = [
            'juan daniel conde quispe' => 'daniel conde quispe',
        ];
        return $aliases[$v] ?? $v;
    }
}
