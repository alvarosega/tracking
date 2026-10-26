<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Saneamiento;

use Illuminate\Support\Facades\DB;

class AuditarSaneamientoBdAction
{
    protected const SIMBOLOS_INVALIDOS = '/[?\*#@$%!~\^]/';
    protected const PLACEHOLDERS_NOMBRE = [
        'SN', 'S/N', 'SIN NOMBRE', 'PRUEBA', 'CLIENTE', 'N/N', 'NN',
        'NO TIENE', 'DESCONOCIDO', 'SIN DATOS', 'CLIENTE NUEVO', 'NN NN',
        'SN SN', 'S/N S/N', 'SIN NOMBRE SIN APELLIDO', 'X', 'XX', 'XXX',
        'SIN APELLIDO', 'SOLO NOMBRE',
    ];
    protected const VALORES_NULOS = [
        '', '0', '00', '000', '0000', '00000', 'S/N', 'SN', 'SIN NUMERO',
        'NULL', 'NONE', 'N/A', '123', '1234', '12345', '123456', 'SIN DATO',
        'NO TIENE', 'NINGUNO', '-',
    ];
    protected const DIAS_ORDEN = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];

    /**
     * Ejecuta la auditoría exhaustiva de saneamiento de base de datos.
     *
     * @param string|null $canal
     * @param array<string>|null $rutas
     * @param string|null $dia
     * @param string|null $tipoError
     * @param string|null $busqueda
     * @return array<string, mixed>
     */
    public function execute(
        ?string $canal = null,
        ?array $rutas = null,
        ?string $dia = null,
        ?string $tipoError = null,
        ?string $busqueda = null
    ): array {
        $conn = DB::connection('supervisor');

        $query = $conn->table('pan_ruteo as p')
            ->leftJoin('dim_rutas as r', DB::raw("TRIM(UPPER(p.ruta))"), '=', DB::raw("TRIM(UPPER(r.ruta))"))
            ->where(function ($w) {
                $w->whereRaw("LOWER(TRIM(p.estado)) = 'activo'")
                    ->orWhereNull('p.estado')
                    ->orWhereRaw("p.estado = ''");
            });

        if ($canal && strtoupper(trim($canal)) !== 'TODOS') {
            $query->where(DB::raw("TRIM(r.canal)"), trim($canal));
        }

        if (!empty($rutas)) {
            $rutasNorm = array_values(array_unique(array_filter(array_map(fn($ru) => strtoupper(trim((string) $ru)), $rutas))));
            if (!empty($rutasNorm)) {
                $query->whereIn(DB::raw("TRIM(UPPER(p.ruta))"), $rutasNorm);
            }
        }

        if ($dia && strtoupper(trim($dia)) !== 'TODOS') {
            $query->where(DB::raw("LOWER(TRIM(COALESCE(p.dia_norm, p.dia)))"), strtolower(trim($dia)));
        }

        $rows = $query->select([
            'p.cliente_id',
            DB::raw("COALESCE(p.cliente, '') as cliente"),
            DB::raw("COALESCE(p.cliente_norm, '') as cliente_norm"),
            DB::raw("COALESCE(p.nombre_factura, '') as nombre_factura"),
            DB::raw("COALESCE(p.nit, '') as nit"),
            DB::raw("COALESCE(p.ci, '') as ci"),
            DB::raw("COALESCE(p.telefono, '') as telefono"),
            DB::raw("COALESCE(p.celular, '') as celular"),
            DB::raw("COALESCE(p.direccion, '') as direccion"),
            DB::raw("COALESCE(p.nro, '') as nro"),
            DB::raw("COALESCE(p.referencia, '') as referencia"),
            DB::raw("COALESCE(p.tipo_negocio, '-') as tipo_negocio"),
            'p.latitud',
            'p.longitud',
            DB::raw("COALESCE(TRIM(UPPER(p.ruta)), 'SIN RUTA') as ruta"),
            DB::raw("COALESCE(TRIM(p.dia_norm), TRIM(p.dia), 'SIN DIA') as dia"),
            DB::raw("COALESCE(TRIM(r.canal), 'SIN CANAL') as canal"),
            DB::raw("COALESCE(TRIM(r.vendedor), TRIM(p.vendedor), 'Sin Asignar') as vendedor"),
        ])
        ->orderBy('p.ruta')
        ->orderBy('p.dia')
        ->orderBy('p.cliente_id')
        ->get();

        $clientesProcesados = [];
        $matrizMap = [];
        $totalesAnomalias = [
            'falta_apellido' => 0,
            'sin_telefono' => 0,
            'sin_nit_ci' => 0,
            'sin_direccion' => 0,
            'sin_coordenadas' => 0,
            'sin_dia' => 0,
        ];

        foreach ($rows as $c) {
            $cRuta = (string) $c->ruta;
            $cCanal = (string) $c->canal;
            $cVendedor = (string) $c->vendedor;
            $cDiaRaw = strtolower(trim((string) $c->dia));

            $faltaApellido = $this->esFaltaApellido((string) $c->cliente);
            $sinTelefono = $this->esTelefonoInvalido((string) $c->telefono, (string) $c->celular);
            $sinNitCi = $this->esDocumentoInvalido((string) $c->nit, (string) $c->ci);
            $sinDireccion = $this->esDireccionInvalida((string) $c->direccion, (string) $c->nro);
            $sinCoordenadas = $this->esCoordenadaInvalida($c->latitud, $c->longitud);
            $sinDia = $this->esDiaInvalido($cDiaRaw);

            $anomalias = [];
            if ($faltaApellido) {
                $anomalias[] = 'FALTA_APELLIDO';
                $totalesAnomalias['falta_apellido']++;
            }
            if ($sinTelefono) {
                $anomalias[] = 'SIN_TELEFONO';
                $totalesAnomalias['sin_telefono']++;
            }
            if ($sinNitCi) {
                $anomalias[] = 'SIN_NIT_CI';
                $totalesAnomalias['sin_nit_ci']++;
            }
            if ($sinDireccion) {
                $anomalias[] = 'SIN_DIRECCION';
                $totalesAnomalias['sin_direccion']++;
            }
            if ($sinCoordenadas) {
                $anomalias[] = 'SIN_COORDENADAS';
                $totalesAnomalias['sin_coordenadas']++;
            }
            if ($sinDia) {
                $anomalias[] = 'SIN_DIA';
                $totalesAnomalias['sin_dia']++;
            }

            $cantAnomalias = count($anomalias);
            $esDefectuoso = $cantAnomalias > 0;
            $severidad = $cantAnomalias >= 3 ? 'CRITICO' : ($cantAnomalias === 2 ? 'MEDIO' : ($cantAnomalias === 1 ? 'LEVE' : 'OK'));

            if (!isset($matrizMap[$cRuta])) {
                $diasBucket = [];
                foreach (self::DIAS_ORDEN as $d) {
                    $diasBucket[$d] = [
                        'total' => 0,
                        'defectuosos' => 0,
                        'correctos' => 0,
                        'falta_apellido' => 0,
                        'sin_telefono' => 0,
                        'sin_nit_ci' => 0,
                        'sin_direccion' => 0,
                        'sin_coordenadas' => 0,
                    ];
                }

                $matrizMap[$cRuta] = [
                    'ruta' => $cRuta,
                    'canal' => $cCanal,
                    'vendedor' => $cVendedor,
                    'total_clientes' => 0,
                    'total_defectuosos' => 0,
                    'total_correctos' => 0,
                    'porcentaje_defectuoso' => 0.0,
                    'falta_apellido' => 0,
                    'sin_telefono' => 0,
                    'sin_nit_ci' => 0,
                    'sin_direccion' => 0,
                    'sin_coordenadas' => 0,
                    'sin_dia' => 0,
                    'dias' => $diasBucket,
                ];
            }

            $fila = &$matrizMap[$cRuta];
            $fila['total_clientes']++;
            if ($esDefectuoso) {
                $fila['total_defectuosos']++;
            } else {
                $fila['total_correctos']++;
            }

            if ($faltaApellido) {
                $fila['falta_apellido']++;
            }
            if ($sinTelefono) {
                $fila['sin_telefono']++;
            }
            if ($sinNitCi) {
                $fila['sin_nit_ci']++;
            }
            if ($sinDireccion) {
                $fila['sin_direccion']++;
            }
            if ($sinCoordenadas) {
                $fila['sin_coordenadas']++;
            }
            if ($sinDia) {
                $fila['sin_dia']++;
            }

            if (isset($fila['dias'][$cDiaRaw])) {
                $dBucket = &$fila['dias'][$cDiaRaw];
                $dBucket['total']++;
                if ($esDefectuoso) {
                    $dBucket['defectuosos']++;
                } else {
                    $dBucket['correctos']++;
                }
                if ($faltaApellido) {
                    $dBucket['falta_apellido']++;
                }
                if ($sinTelefono) {
                    $dBucket['sin_telefono']++;
                }
                if ($sinNitCi) {
                    $dBucket['sin_nit_ci']++;
                }
                if ($sinDireccion) {
                    $dBucket['sin_direccion']++;
                }
                if ($sinCoordenadas) {
                    $dBucket['sin_coordenadas']++;
                }
                unset($dBucket);
            }
            unset($fila);

            $clientesProcesados[] = [
                'cliente_id' => (int) $c->cliente_id,
                'cliente' => (string) $c->cliente,
                'nombre_factura' => (string) $c->nombre_factura,
                'nit' => (string) $c->nit,
                'ci' => (string) $c->ci,
                'telefono' => (string) $c->telefono,
                'celular' => (string) $c->celular,
                'direccion' => (string) $c->direccion,
                'nro' => (string) $c->nro,
                'referencia' => (string) $c->referencia,
                'tipo_negocio' => (string) $c->tipo_negocio,
                'latitud' => $c->latitud !== null ? (float) $c->latitud : null,
                'longitud' => $c->longitud !== null ? (float) $c->longitud : null,
                'ruta' => $cRuta,
                'canal' => $cCanal,
                'vendedor' => $cVendedor,
                'dia' => $cDiaRaw,
                'es_defectuoso' => $esDefectuoso,
                'cant_anomalias' => $cantAnomalias,
                'anomalias' => $anomalias,
                'severidad' => $severidad,
                'falta_apellido' => $faltaApellido,
                'sin_telefono' => $sinTelefono,
                'sin_nit_ci' => $sinNitCi,
                'sin_direccion' => $sinDireccion,
                'sin_coordenadas' => $sinCoordenadas,
                'sin_dia' => $sinDia,
            ];
        }

        $matrizRutas = [];
        foreach ($matrizMap as $rData) {
            $tot = $rData['total_clientes'];
            $defec = $rData['total_defectuosos'];
            $rData['porcentaje_defectuoso'] = $tot > 0 ? round(($defec / $tot) * 100, 1) : 0.0;
            $matrizRutas[] = $rData;
        }

        usort($matrizRutas, function ($a, $b) {
            if ($a['porcentaje_defectuoso'] == $b['porcentaje_defectuoso']) {
                return $b['total_defectuosos'] <=> $a['total_defectuosos'];
            }
            return $b['porcentaje_defectuoso'] <=> $a['porcentaje_defectuoso'];
        });

        // Filtrado secundario de clientes
        $filtroErr = strtoupper(trim((string) ($tipoError ?: 'TODOS')));
        $clientesFiltrados = $clientesProcesados;

        if ($filtroErr === 'SOLO_DEFECTUOSOS') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['es_defectuoso']));
        } elseif ($filtroErr === 'CORRECTOS') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => !$c['es_defectuoso']));
        } elseif ($filtroErr === 'FALTA_APELLIDO') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['falta_apellido']));
        } elseif ($filtroErr === 'SIN_TELEFONO') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['sin_telefono']));
        } elseif ($filtroErr === 'SIN_NIT_CI') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['sin_nit_ci']));
        } elseif ($filtroErr === 'SIN_DIRECCION') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['sin_direccion']));
        } elseif ($filtroErr === 'SIN_COORDENADAS') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['sin_coordenadas']));
        } elseif ($filtroErr === 'SIN_DIA') {
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, fn($c) => $c['sin_dia']));
        }

        if ($busqueda && trim($busqueda) !== '') {
            $tokens = explode(' ', strtolower(trim($busqueda)));
            $clientesFiltrados = array_values(array_filter($clientesFiltrados, function ($c) use ($tokens) {
                $target = strtolower("{$c['cliente_id']} {$c['cliente']} {$c['direccion']} {$c['ruta']} {$c['vendedor']}");
                foreach ($tokens as $t) {
                    if (!str_contains($target, $t)) {
                        return false;
                    }
                }
                return true;
            }));
        }

        $totalGeneral = count($clientesProcesados);
        $totalDefectuosos = 0;
        foreach ($clientesProcesados as $c) {
            if ($c['es_defectuoso']) {
                $totalDefectuosos++;
            }
        }
        $totalCorrectos = $totalGeneral - $totalDefectuosos;
        $pctGlobal = $totalGeneral > 0 ? round(($totalDefectuosos / $totalGeneral) * 100, 1) : 0.0;

        return [
            'resumen_general' => [
                'total_rutas' => count($matrizRutas),
                'total_clientes' => $totalGeneral,
                'total_defectuosos' => $totalDefectuosos,
                'total_correctos' => $totalCorrectos,
                'porcentaje_defectuoso' => $pctGlobal,
                'totales_anomalias' => $totalesAnomalias,
            ],
            'matriz_rutas' => $matrizRutas,
            'clientes' => $clientesFiltrados,
        ];
    }

    protected function esFaltaApellido(?string $nombre): bool
    {
        if (empty($nombre)) {
            return true;
        }
        $clean = strtoupper(trim((string) $nombre));
        if (in_array($clean, self::PLACEHOLDERS_NOMBRE, true) || in_array($clean, self::VALORES_NULOS, true)) {
            return true;
        }
        if (mb_strlen($clean) < 4) {
            return true;
        }
        if (preg_match(self::SIMBOLOS_INVALIDOS, $clean)) {
            return true;
        }

        $palabras = array_filter(
            preg_split('/[\s,\.\-_\\/]+/', $clean),
            fn($p) => mb_strlen($p) >= 2 && !ctype_digit($p)
        );

        return count($palabras) < 2;
    }

    protected function esTelefonoInvalido(?string $tel, ?string $cel): bool
    {
        $digTel = preg_replace('/\D/', '', (string) $tel);
        $digCel = preg_replace('/\D/', '', (string) $cel);

        $telValido = strlen($digTel) >= 7 && !$this->esSecuenciaRepetida($digTel);
        $celValido = strlen($digCel) >= 7 && !$this->esSecuenciaRepetida($digCel);

        return !($telValido || $celValido);
    }

    protected function esDocumentoInvalido(?string $nit, ?string $ci): bool
    {
        $digNit = preg_replace('/\D/', '', (string) $nit);
        $digCi = preg_replace('/\D/', '', (string) $ci);

        $nitValido = strlen($digNit) >= 4 && !$this->esSecuenciaRepetida($digNit);
        $ciValido = strlen($digCi) >= 4 && !$this->esSecuenciaRepetida($digCi);

        return !($nitValido || $ciValido);
    }

    protected function esDireccionInvalida(?string $direccion, ?string $nro): bool
    {
        if ($this->esTextoVacioOPlaceholder($direccion)) {
            return true;
        }
        $cleanDir = strtoupper(trim((string) $direccion));
        if (mb_strlen($cleanDir) < 5) {
            return true;
        }

        $nroInvalido = $this->esTextoVacioOPlaceholder($nro);
        $tieneDigitoEnDir = (bool) preg_match('/\d/', $cleanDir);

        if ($nroInvalido && !$tieneDigitoEnDir && mb_strlen($cleanDir) < 15) {
            return true;
        }

        return false;
    }

    protected function esCoordenadaInvalida($lat, $lon): bool
    {
        if ($lat === null || $lon === null) {
            return true;
        }
        $fLat = (float) $lat;
        $fLon = (float) $lon;
        if ($fLat == 0.0 || $fLon == 0.0) {
            return true;
        }
        // Bolivia: Lat [-23.0, -9.0], Lon [-70.0, -57.0]
        if (!($fLat >= -23.0 && $fLat <= -9.0 && $fLon >= -70.0 && $fLon <= -57.0)) {
            return true;
        }
        return false;
    }

    protected function esDiaInvalido(?string $dia): bool
    {
        if (empty($dia)) {
            return true;
        }
        $dClean = strtolower(trim((string) $dia));
        return in_array($dClean, ['', 'sin dia', 'none', 'null', 'nan'], true);
    }

    protected function esTextoVacioOPlaceholder(?string $val): bool
    {
        if ($val === null) {
            return true;
        }
        $clean = strtoupper(trim((string) $val));
        return in_array($clean, self::VALORES_NULOS, true);
    }

    protected function esSecuenciaRepetida(string $cadena): bool
    {
        if (empty($cadena)) {
            return true;
        }
        return count(array_unique(str_split($cadena))) <= 1;
    }
}

