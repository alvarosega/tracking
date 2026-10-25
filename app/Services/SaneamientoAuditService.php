<?php

declare(strict_types=1);

namespace App\Services;

class SaneamientoAuditService
{
    /**
     * Palabras clave o textos comodines que denotan dirección no válida.
     */
    private const DIRECCIONES_INVALIDAS = [
        'sn', 's/n', 's.n', 'sd', 's/d', 's.d',
        'sin direccion', 'sin direccion.', 'sin dato', 'sin datos',
        'ninguna', 'no tiene', 'desconocido', 'desconocida', 'x', '-', '.',
    ];

    /**
     * Evalúa la calidad de los datos de un cliente y retorna las alertas y campos a sanear.
     *
     * @param object|array<string, mixed> $client
     * @return array{
     *     requiere_saneamiento: bool,
     *     saneamiento_obligatorio: bool,
     *     alertas: array<int, string>,
     *     campos_incompletos: array<int, string>,
     *     calidad_score: int
     * }
     */
    public function audit(object|array $client): array
    {
        $data = is_array($client) ? (object) $client : $client;

        $alertas = [];
        $campos = [];
        $esObligatorio = false;

        $cliente = trim((string) ($data->cliente ?? ''));
        $celular = trim((string) ($data->celular ?? ''));
        $telefono = trim((string) ($data->telefono ?? ''));
        $direccion = trim((string) ($data->direccion ?? ''));
        $nit = trim((string) ($data->nit ?? ''));
        $lat = isset($data->latitud) ? (float) $data->latitud : null;
        $lng = isset($data->longitud) ? (float) $data->longitud : null;

        // 1. Auditoría del Nombre y Apellido
        if ($cliente === '' || strcasecmp($cliente, 'Cliente Sin Nombre') === 0) {
            $alertas[] = 'Nombre de cliente no registrado o vacío';
            $campos[] = 'cliente';
            $esObligatorio = true;
        } else {
            if (preg_match('/[0-9]/', $cliente)) {
                $alertas[] = 'El nombre del cliente no debe contener números';
                $campos[] = 'cliente';
                $esObligatorio = true;
            }

            if (str_contains($cliente, '.')) {
                $alertas[] = 'El nombre no debe contener puntos ni abreviaturas';
                $campos[] = 'cliente';
                $esObligatorio = true;
            }

            $palabras = preg_split('/\s+/', $cliente) ?: [];
            $palabrasValidas = array_filter($palabras, fn(string $p) => mb_strlen($p) >= 2);

            if (count($palabrasValidas) < 2) {
                $alertas[] = 'Falta apellido del cliente (debe incluir al menos un nombre y un apellido)';
                $campos[] = 'apellido';
                $esObligatorio = true;
            }
        }

        // 2. Auditoría de Celular y Teléfono
        $tieneCelular = ($celular !== '' && $celular !== '0');
        $tieneTelefono = ($telefono !== '' && $telefono !== '0');

        if (!$tieneCelular && !$tieneTelefono) {
            $alertas[] = 'Sin número de celular ni teléfono de contacto';
            $campos[] = 'celular';
            $esObligatorio = true;
        } else {
            if ($tieneCelular) {
                if (preg_match('/[a-zA-Z]/', $celular)) {
                    $alertas[] = 'El celular contiene letras o texto inválido';
                    $campos[] = 'celular';
                    $esObligatorio = true;
                }
                $digitsCelular = preg_replace('/[^0-9]/', '', $celular);
                if (strlen((string) $digitsCelular) < 8) {
                    $alertas[] = 'El número de celular debe tener al menos 8 dígitos';
                    $campos[] = 'celular';
                }
            }

            if ($tieneTelefono && preg_match('/[a-zA-Z]/', $telefono)) {
                $alertas[] = 'El teléfono contiene letras o texto inválido';
                $campos[] = 'telefono';
                $esObligatorio = true;
            }
        }

        // 3. Auditoría de Dirección
        $dirLower = mb_strtolower($direccion, 'UTF-8');
        if ($direccion === '' || in_array($dirLower, self::DIRECCIONES_INVALIDAS, true) || mb_strlen($direccion) < 6) {
            $alertas[] = 'Sin dirección física válida o registrada de forma genérica (mínimo calle y referencia)';
            $campos[] = 'direccion';
            $esObligatorio = true;
        }

        // 4. Auditoría de Coordenadas GPS
        if ($lat === null || $lng === null || $lat === 0.0 || $lng === 0.0) {
            $alertas[] = 'Sin coordenadas GPS fijadas en el mapa';
            $campos[] = 'gps';
            $esObligatorio = true;
        } else {
            // Rango geográfico aproximado de Bolivia
            if ($lat < -23.0 || $lat > -9.0 || $lng < -70.0 || $lng > -57.0) {
                $alertas[] = 'Coordenadas GPS fuera del rango geográfico válido';
                $campos[] = 'gps';
                $esObligatorio = true;
            }
        }

        // 5. Auditoría de NIT
        if ($nit === '' || $nit === '0') {
            $alertas[] = 'Sin NIT registrado o registrado en cero';
            $campos[] = 'nit';
        } elseif (preg_match('/[^0-9]/', $nit)) {
            $alertas[] = 'El NIT contiene caracteres no numéricos';
            $campos[] = 'nit';
            $esObligatorio = true;
        }

        $requiereSaneamiento = !empty($alertas);
        $totalChecks = 5;
        $fallas = count(array_unique($campos));
        $calidadScore = max(0, (int) round((($totalChecks - $fallas) / $totalChecks) * 100));

        return [
            'requiere_saneamiento' => $requiereSaneamiento,
            'saneamiento_obligatorio' => $esObligatorio,
            'alertas' => array_values(array_unique($alertas)),
            'campos_incompletos' => array_values(array_unique($campos)),
            'calidad_score' => $calidadScore,
        ];
    }
}

