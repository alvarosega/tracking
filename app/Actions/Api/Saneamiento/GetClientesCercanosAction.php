<?php

declare(strict_types=1);

namespace App\Actions\Api\Saneamiento;

use Illuminate\Support\Facades\DB;

class GetClientesCercanosAction
{
    private const DEFAULT_RADIO_METROS = 200;
    private const DISTANCIA_PROXIMIDAD_CRITICA = 20.0;

    /**
     * Busca todos los clientes existentes y altas pendientes a un radio determinado (por defecto 200m)
     * sin importar la ruta a la que pertenezcan, y diagnostica duplicados respecto a los datos del alta.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $lat = (float) $params['latitude'];
        $lng = (float) $params['longitude'];
        $radio = isset($params['radio']) ? (int) $params['radio'] : self::DEFAULT_RADIO_METROS;

        $candidatoNombre = isset($params['cliente']) ? trim((string) $params['cliente']) : '';
        $candidatoCelular = $this->cleanDigits($params['celular'] ?? null);
        $candidatoTelefono = $this->cleanDigits($params['telefono'] ?? null);
        $candidatoNit = $this->cleanDigits($params['nit'] ?? null);

        // 1. Optimización por Caja Delimitadora (Bounding Box) antes de Haversine
        $deltaLat = ($radio + 150) / 111139.0;
        $cosLat = cos(deg2rad($lat));
        $deltaLng = ($radio + 150) / (111139.0 * ($cosLat != 0 ? abs($cosLat) : 1));

        $haversineFormula = "(6371000 * 2 * ASIN(SQRT(
            POWER(SIN(RADIANS(latitud - {$lat}) / 2), 2) +
            COS(RADIANS({$lat})) * COS(RADIANS(latitud)) *
            POWER(SIN(RADIANS(longitud - {$lng}) / 2), 2)
        )))";

        // 2. Consulta de clientes en pan_ruteo (Todas las rutas)
        $clientesQuery = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->where('latitud', '!=', 0)
            ->whereBetween('latitud', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('longitud', [$lng - $deltaLng, $lng + $deltaLng])
            ->select([
                'cliente_id',
                DB::raw("MAX(COALESCE(NULLIF(cliente, ''), cliente_norm, 'Cliente Sin Nombre')) as cliente"),
                DB::raw("MAX(ruta) as ruta"),
                DB::raw("MAX(vendedor) as vendedor"),
                DB::raw("MAX(direccion) as direccion"),
                DB::raw("MAX(referencia) as referencia"),
                DB::raw("MAX(tipo_negocio) as tipo_negocio"),
                DB::raw("COALESCE(MAX(NULLIF(celular, '')), MAX(telefono)) as celular"),
                DB::raw("MAX(telefono) as telefono"),
                DB::raw("MAX(nit) as nit"),
                DB::raw("MAX(latitud) as latitud"),
                DB::raw("MAX(longitud) as longitud"),
                DB::raw("ROUND({$haversineFormula}) AS distancia_metros"),
            ])
            ->groupBy('cliente_id')
            ->having('distancia_metros', '<=', $radio)
            ->orderBy('distancia_metros', 'ASC')
            ->get();

        // 3. Consulta de Altas pendientes en saneamiento_base (para evitar que dos vendedores registren el mismo punto nuevo)
        $haversineAltas = "(6371000 * 2 * ASIN(SQRT(
            POWER(SIN(RADIANS(latitude - {$lat}) / 2), 2) +
            COS(RADIANS({$lat})) * COS(RADIANS(latitude)) *
            POWER(SIN(RADIANS(longitude - {$lng}) / 2), 2)
        )))";

        $altasPendientes = DB::connection('supervisor')
            ->table('saneamiento_base')
            ->where('tipo_registro', 'ALTA')
            ->where('estado_revision', 'PENDIENTE')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->whereBetween('latitude', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('longitude', [$lng - $deltaLng, $lng + $deltaLng])
            ->select([
                DB::raw("NULL as cliente_id"),
                'id as saneamiento_id',
                'cliente',
                'route as ruta',
                'direccion',
                'referencia',
                'tipo_negocio',
                'celular',
                'telefono',
                'nit',
                'latitude as latitud',
                'longitude as longitud',
                DB::raw("ROUND({$haversineAltas}) AS distancia_metros"),
            ])
            ->having('distancia_metros', '<=', $radio)
            ->orderBy('distancia_metros', 'ASC')
            ->get();

        // 4. Unificar y procesar detección de duplicados y coincidencias
        $clientesCercanos = [];
        $alertasGlobales = [];

        // Procesar clientes oficiales de pan_ruteo
        foreach ($clientesQuery as $c) {
            $analisis = $this->analizarCoincidencias(
                candidatoNombre: $candidatoNombre,
                candidatoCelular: $candidatoCelular,
                candidatoTelefono: $candidatoTelefono,
                candidatoNit: $candidatoNit,
                clienteNombre: (string) $c->cliente,
                clienteCelular: (string) ($c->celular ?? ''),
                clienteTelefono: (string) ($c->telefono ?? ''),
                clienteNit: (string) ($c->nit ?? ''),
                distanciaMetros: (float) $c->distancia_metros,
                ruta: (string) $c->ruta
            );

            foreach ($analisis['alertas'] as $alerta) {
                $alertasGlobales[] = $alerta;
            }

            $clientesCercanos[] = [
                'cliente_id' => (int) $c->cliente_id,
                'es_alta_pendiente' => false,
                'cliente' => trim((string) $c->cliente),
                'ruta' => trim((string) $c->ruta),
                'vendedor' => trim((string) ($c->vendedor ?? '')),
                'tipo_negocio' => trim((string) ($c->tipo_negocio ?? '')),
                'direccion' => trim((string) ($c->direccion ?? '')),
                'referencia' => trim((string) ($c->referencia ?? '')),
                'celular' => trim((string) ($c->celular ?? '')),
                'telefono' => trim((string) ($c->telefono ?? '')),
                'nit' => trim((string) ($c->nit ?? '')),
                'latitud' => (float) $c->latitud,
                'longitud' => (float) $c->longitud,
                'distancia_metros' => (int) $c->distancia_metros,
                'tiene_duplicidad' => !empty($analisis['coincidencias']),
                'coincidencias' => $analisis['coincidencias'],
                'alertas' => $analisis['alertas'],
            ];
        }

        // Procesar altas pendientes de revisión
        foreach ($altasPendientes as $a) {
            $analisis = $this->analizarCoincidencias(
                candidatoNombre: $candidatoNombre,
                candidatoCelular: $candidatoCelular,
                candidatoTelefono: $candidatoTelefono,
                candidatoNit: $candidatoNit,
                clienteNombre: (string) $a->cliente,
                clienteCelular: (string) ($a->celular ?? ''),
                clienteTelefono: (string) ($a->telefono ?? ''),
                clienteNit: (string) ($a->nit ?? ''),
                distanciaMetros: (float) $a->distancia_metros,
                ruta: (string) $a->ruta,
                esAltaPendiente: true
            );

            foreach ($analisis['alertas'] as $alerta) {
                $alertasGlobales[] = $alerta;
            }

            $clientesCercanos[] = [
                'cliente_id' => null,
                'saneamiento_id' => (int) $a->saneamiento_id,
                'es_alta_pendiente' => true,
                'cliente' => trim((string) $a->cliente) . ' (ALTA EN REVISIÓN)',
                'ruta' => trim((string) $a->ruta),
                'vendedor' => '',
                'tipo_negocio' => trim((string) ($a->tipo_negocio ?? '')),
                'direccion' => trim((string) ($a->direccion ?? '')),
                'referencia' => trim((string) ($a->referencia ?? '')),
                'celular' => trim((string) ($a->celular ?? '')),
                'telefono' => trim((string) ($a->telefono ?? '')),
                'nit' => trim((string) ($a->nit ?? '')),
                'latitud' => (float) $a->latitud,
                'longitud' => (float) $a->longitud,
                'distancia_metros' => (int) $a->distancia_metros,
                'tiene_duplicidad' => !empty($analisis['coincidencias']),
                'coincidencias' => $analisis['coincidencias'],
                'alertas' => $analisis['alertas'],
            ];
        }

        // Ordenar todos los puntos unificados por distancia ascendente
        usort($clientesCercanos, fn($x, $y) => $x['distancia_metros'] <=> $y['distancia_metros']);

        return [
            'success' => true,
            'coordenadas_consulta' => [
                'latitude' => $lat,
                'longitude' => $lng,
                'radio_metros' => $radio,
            ],
            'total_cercanos' => count($clientesCercanos),
            'tiene_duplicados_detectados' => !empty($alertasGlobales),
            'alertas_duplicidad' => array_values(array_unique($alertasGlobales)),
            'clientes_cercanos' => $clientesCercanos,
        ];
    }

    /**
     * Analiza si el punto evaluado coincide con los datos propuestos para el alta.
     *
     * @return array{coincidencias: array<int, string>, alertas: array<int, string>}
     */
    public function analizarCoincidencias(
        string $candidatoNombre,
        ?string $candidatoCelular,
        ?string $candidatoTelefono,
        ?string $candidatoNit,
        string $clienteNombre,
        string $clienteCelular,
        string $clienteTelefono,
        string $clienteNit,
        float $distanciaMetros,
        string $ruta,
        bool $esAltaPendiente = false
    ): array {
        $coincidencias = [];
        $alertas = [];
        $etiquetaTipo = $esAltaPendiente ? 'un alta pendiente' : 'el cliente';

        // 1. Proximidad extrema (menos de 20 metros)
        if ($distanciaMetros <= self::DISTANCIA_PROXIMIDAD_CRITICA) {
            $coincidencias[] = 'proximidad_extrema';
            $alertas[] = "Proximidad crítica: Ya existe {$etiquetaTipo} '{$clienteNombre}' (Ruta {$ruta}) a solo " . round($distanciaMetros) . "m.";
        }

        // 2. Coincidencia de Nombre (similitud fonética/textual)
        if ($candidatoNombre !== '') {
            $similitud = $this->calcularSimilitudNombres($candidatoNombre, $clienteNombre);
            if ($similitud >= 80.0) {
                $coincidencias[] = 'nombre_similar';
                $alertas[] = "Nombre repetido o similar: Coincide en " . round($similitud) . "% con '{$clienteNombre}' (Ruta {$ruta}).";
            }
        }

        // 3. Coincidencia de Celular o Teléfono
        $targetCelular = $this->cleanDigits($clienteCelular);
        $targetTelefono = $this->cleanDigits($clienteTelefono);

        $matchCelular = false;
        if ($candidatoCelular && strlen($candidatoCelular) >= 7) {
            if ($candidatoCelular === $targetCelular || $candidatoCelular === $targetTelefono) {
                $matchCelular = true;
            }
        }
        if ($candidatoTelefono && strlen($candidatoTelefono) >= 7) {
            if ($candidatoTelefono === $targetCelular || $candidatoTelefono === $targetTelefono) {
                $matchCelular = true;
            }
        }

        if ($matchCelular) {
            $coincidencias[] = 'telefono_duplicado';
            $alertas[] = "Teléfono duplicado: El número ya pertenece a '{$clienteNombre}' (Ruta {$ruta}).";
        }

        // 4. Coincidencia de NIT
        $targetNit = $this->cleanDigits($clienteNit);
        if ($candidatoNit && strlen($candidatoNit) >= 5 && $targetNit && strlen($targetNit) >= 5) {
            if ($candidatoNit === $targetNit && $candidatoNit !== '0') {
                $coincidencias[] = 'nit_duplicado';
                $alertas[] = "NIT duplicado: El NIT {$candidatoNit} ya está registrado para '{$clienteNombre}' (Ruta {$ruta}).";
            }
        }

        return [
            'coincidencias' => $coincidencias,
            'alertas' => $alertas,
        ];
    }

    /**
     * Calcula el porcentaje de similitud entre dos nombres de cliente.
     */
    public function calcularSimilitudNombres(string $nombreA, string $nombreB): float
    {
        $normA = $this->normalizarTexto($nombreA);
        $normB = $this->normalizarTexto($nombreB);

        if ($normA === '' || $normB === '') {
            return 0.0;
        }

        if ($normA === $normB) {
            return 100.0;
        }

        // Similar text
        similar_text($normA, $normB, $percent);

        // Chequear también si todas las palabras principales de uno están en el otro
        $palabrasA = array_filter(explode(' ', $normA), fn($p) => mb_strlen($p) >= 3);
        $palabrasB = array_filter(explode(' ', $normB), fn($p) => mb_strlen($p) >= 3);

        if (!empty($palabrasA) && !empty($palabrasB)) {
            $interseccion = array_intersect($palabrasA, $palabrasB);
            $ratioA = count($interseccion) / count($palabrasA);
            $ratioB = count($interseccion) / count($palabrasB);

            if ($ratioA >= 0.75 || $ratioB >= 0.75) {
                $percent = max($percent, 85.0);
            }
        }

        return (float) $percent;
    }

    /**
     * Limpia caracteres especiales y acentos para comparación de texto.
     */
    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ];
        $texto = strtr($texto, $map);
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto) ?? '';
        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Extrae únicamente dígitos numéricos.
     */
    private function cleanDigits(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $val);
        return ($digits !== '' && $digits !== null) ? $digits : null;
    }
}
