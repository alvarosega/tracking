<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class AuditarCalidadAction
{
    protected const SIMBOLOS_INVALIDOS = '/[?\*#@$%!~\^]/';
    protected const PLACEHOLDERS_NOMBRE = [
        'S/N', 'SN', 'SIN NOMBRE', 'PRUEBA', 'CLIENTE', 'N/N', 'NN',
        'NO TIENE', 'DESCONOCIDO', 'SIN DATOS',
    ];
    protected const VALORES_NULOS = [
        '', '0', '00', '000', 'S/N', 'SN', 'SIN NUMERO', 'NULL', 'NONE', 'N/A', '123', 'SIN DATO',
    ];

    /**
     * Audita la calidad de datos (incompletos/erróneos) para las rutas especificadas.
     *
     * @param array<string> $rutas
     * @return array<string, mixed>
     */
    public function execute(array $rutas): array
    {
        $rutasLimpias = array_values(array_unique(array_filter(array_map(fn($r) => strtoupper(trim((string) $r)), $rutas))));

        if (empty($rutasLimpias)) {
            return [
                'rutas_solicitadas' => [],
                'total_clientes' => 0,
                'total_defectuosos' => 0,
                'total_correctos' => 0,
                'resumen_anomalias' => ['nombre' => 0, 'numero' => 0, 'nit' => 0, 'telefono' => 0],
                'clientes' => [],
                'balance_rutas' => (object) [],
            ];
        }

        $conn = DB::connection('supervisor');

        $rows = $conn->table('pan_ruteo')
            ->whereIn(DB::raw("TRIM(UPPER(ruta))"), $rutasLimpias)
            ->select([
                'cliente_id',
                DB::raw("TRIM(cliente) as cliente"),
                DB::raw("TRIM(nro) as nro"),
                DB::raw("TRIM(direccion) as direccion"),
                DB::raw("TRIM(nit) as nit"),
                DB::raw("TRIM(ci) as ci"),
                DB::raw("TRIM(telefono) as telefono"),
                DB::raw("TRIM(celular) as celular"),
                DB::raw("TRIM(nombre_factura) as nombre_factura"),
                DB::raw("TRIM(UPPER(ruta)) as ruta"),
                DB::raw("TRIM(UPPER(dia)) as dia"),
                'latitud',
                'longitud',
            ])
            ->orderBy('ruta')
            ->orderBy('dia')
            ->orderBy('cliente')
            ->get();

        $clientesAuditados = [];
        $resumenAnomalias = ['nombre' => 0, 'numero' => 0, 'nit' => 0, 'telefono' => 0];
        $totalDefectuosos = 0;

        $balanceRutas = [];
        foreach ($rutasLimpias as $r) {
            $balanceRutas[$r] = ['total' => 0, 'defectuosos' => 0, 'correctos' => 0];
        }

        foreach ($rows as $c) {
            $rNorm = (string) $c->ruta;
            $cNombre = trim((string) $c->cliente);
            $cNro = trim((string) $c->nro);
            $cDir = trim((string) $c->direccion);
            $cNit = trim((string) $c->nit);
            $cCi = trim((string) $c->ci);
            $cTel = trim((string) $c->telefono);
            $cCel = trim((string) $c->celular);

            // Regla 1: Nombre
            $faltaNombre = false;
            if (empty($cNombre) || mb_strlen($cNombre) < 4) {
                $faltaNombre = true;
            } elseif (in_array(strtoupper($cNombre), self::PLACEHOLDERS_NOMBRE, true)) {
                $faltaNombre = true;
            } elseif (preg_match(self::SIMBOLOS_INVALIDOS, $cNombre)) {
                $faltaNombre = true;
            }

            // Regla 2: Sin Número / Dirección
            $faltaNumero = false;
            if ($this->esTextoVacioOPlaceholder($cNro) || mb_strlen($cDir) < 5 || $this->esTextoVacioOPlaceholder($cDir)) {
                $faltaNumero = true;
            }

            // Regla 3: Sin NIT / CI
            $faltaNitCi = $this->esTextoVacioOPlaceholder($cNit) && $this->esTextoVacioOPlaceholder($cCi);

            // Regla 4: Sin Teléfono / Celular
            $faltaTelefono = $this->esTextoVacioOPlaceholder($cTel) && $this->esTextoVacioOPlaceholder($cCel);

            $anomaliasList = [];
            if ($faltaNombre) {
                $anomaliasList[] = 'NOMBRE_INVALIDO';
                $resumenAnomalias['nombre']++;
            }
            if ($faltaNumero) {
                $anomaliasList[] = 'SIN_NUMERO';
                $resumenAnomalias['numero']++;
            }
            if ($faltaNitCi) {
                $anomaliasList[] = 'SIN_NIT_CI';
                $resumenAnomalias['nit']++;
            }
            if ($faltaTelefono) {
                $anomaliasList[] = 'SIN_TELEFONO';
                $resumenAnomalias['telefono']++;
            }

            $esDefectuoso = !empty($anomaliasList);
            if ($esDefectuoso) {
                $totalDefectuosos++;
            }

            if (isset($balanceRutas[$rNorm])) {
                $balanceRutas[$rNorm]['total']++;
                if ($esDefectuoso) {
                    $balanceRutas[$rNorm]['defectuosos']++;
                } else {
                    $balanceRutas[$rNorm]['correctos']++;
                }
            }

            $cant = count($anomaliasList);
            $severidad = $cant >= 2 ? 'CRITICO' : ($cant === 1 ? 'ADVERTENCIA' : 'OK');

            $clientesAuditados[] = [
                'cliente_id' => (int) $c->cliente_id,
                'cliente' => $cNombre,
                'nombre' => $cNombre,
                'nro' => $cNro,
                'direccion' => $cDir,
                'nit' => $cNit,
                'ci' => $cCi,
                'telefono' => $cTel,
                'celular' => $cCel,
                'nombre_factura' => (string) $c->nombre_factura,
                'ruta' => $rNorm,
                'dia' => (string) $c->dia,
                'latitud' => (float) $c->latitud,
                'longitud' => (float) $c->longitud,
                'es_defectuoso' => $esDefectuoso,
                'cant_anomalias' => $cant,
                'anomalias' => $anomaliasList,
                'falta_nombre' => $faltaNombre,
                'falta_numero' => $faltaNumero,
                'falta_nit_ci' => $faltaNitCi,
                'falta_telefono' => $faltaTelefono,
                'nivel_severidad' => $severidad,
            ];
        }

        return [
            'rutas_solicitadas' => $rutasLimpias,
            'total_clientes' => count($clientesAuditados),
            'total_defectuosos' => $totalDefectuosos,
            'total_correctos' => count($clientesAuditados) - $totalDefectuosos,
            'resumen_anomalias' => $resumenAnomalias,
            'clientes' => $clientesAuditados,
            'balance_rutas' => $balanceRutas,
        ];
    }

    protected function esTextoVacioOPlaceholder(?string $val): bool
    {
        if ($val === null) {
            return true;
        }
        $clean = strtoupper(trim((string) $val));
        return in_array($clean, self::VALORES_NULOS, true);
    }
}

