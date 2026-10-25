<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ruteo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResolveMapsUrlAction
{
    /**
     * Traduce enlaces de Google Maps (incluyendo acortadores maps.app.goo.gl y goo.gl/maps)
     * o coordenadas directas, extrayendo la latitud y longitud geográficas.
     *
     * @return array{
     *     success: bool,
     *     latitud?: float,
     *     longitud?: float,
     *     url_original?: string,
     *     url_resuelta?: string,
     *     tipo?: string,
     *     message?: string
     * }
     */
    public function execute(string $input): array
    {
        $raw = trim($input);

        if ($raw === '') {
            return [
                'success' => false,
                'message' => 'El enlace o texto de búsqueda está vacío.',
            ];
        }

        // 1. Detección directa de coordenadas numéricas (ej: -16.477399, -68.170044)
        if (preg_match('/^(-?\d{1,2}(?:\.\d+)?)[,\s]+(-?\d{1,3}(?:\.\d+)?)$/', $raw, $matches)) {
            $lat = (float) $matches[1];
            $lng = (float) $matches[2];
            if ($this->areCoordinatesValid($lat, $lng)) {
                return [
                    'success' => true,
                    'latitud' => $lat,
                    'longitud' => $lng,
                    'url_original' => $raw,
                    'tipo' => 'coordenadas_directas',
                    'message' => 'Coordenadas numéricas reconocidas.',
                ];
            }
        }

        // 2. Si es una URL completa que ya incluye coordenadas en los parámetros, extraerlas sin petición HTTP
        $directCoords = $this->extractCoordinatesFromText($raw);
        if ($directCoords) {
            return [
                'success' => true,
                'latitud' => $directCoords['lat'],
                'longitud' => $directCoords['lng'],
                'url_original' => $raw,
                'tipo' => 'url_directa',
                'message' => 'Ubicación identificada directamente del enlace.',
            ];
        }

        // Asegurar esquema HTTP si el usuario pegó el enlace sin https://
        $url = $raw;
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'El formato ingresado no es una URL válida ni coordenadas numéricas.',
            ];
        }

        // 3. Resolución de enlaces cortos y redirecciones de Google Maps
        try {
            // Intento 1: Comprobar el encabezado Location del primer salto 301/302 (Ultra rápido)
            $firstResponse = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->timeout(6)
            ->withOptions(['allow_redirects' => false])
            ->get($url);

            $locationHeader = $firstResponse->header('Location');
            if ($locationHeader) {
                $coordsFromLocation = $this->extractCoordinatesFromText($locationHeader);
                if ($coordsFromLocation) {
                    return [
                        'success' => true,
                        'latitud' => $coordsFromLocation['lat'],
                        'longitud' => $coordsFromLocation['lng'],
                        'url_original' => $raw,
                        'url_resuelta' => $locationHeader,
                        'tipo' => 'redireccion_inmediata',
                        'message' => 'Ubicación extraída exitosamente del enlace corto.',
                    ];
                }
            }

            // Intento 2: Seguir redirecciones completas si no venía en el primer Location
            $fullResponse = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->timeout(8)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => true,
                    'track_redirects' => true,
                ],
            ])
            ->get($url);

            $effectiveUrl = (string) $fullResponse->effectiveUri();
            $coordsFromEffective = $this->extractCoordinatesFromText($effectiveUrl);
            if ($coordsFromEffective) {
                return [
                    'success' => true,
                    'latitud' => $coordsFromEffective['lat'],
                    'longitud' => $coordsFromEffective['lng'],
                    'url_original' => $raw,
                    'url_resuelta' => $effectiveUrl,
                    'tipo' => 'url_expandida',
                    'message' => 'Ubicación resuelta tras seguir la redirección.',
                ];
            }

            // Intento 3: Buscar en el cuerpo HTML (metas refresh o window.APP_INITIALIZATION_STATE)
            $htmlBody = $fullResponse->body();
            $coordsFromBody = $this->extractCoordinatesFromText($htmlBody);
            if ($coordsFromBody) {
                return [
                    'success' => true,
                    'latitud' => $coordsFromBody['lat'],
                    'longitud' => $coordsFromBody['lng'],
                    'url_original' => $raw,
                    'url_resuelta' => $effectiveUrl,
                    'tipo' => 'cuerpo_html',
                    'message' => 'Ubicación identificada desde la ficha de Google Maps.',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Error al resolver enlace de Google Maps: ' . $e->getMessage(), ['url' => $url]);
        }

        return [
            'success' => false,
            'message' => 'No se pudieron extraer las coordenadas de ubicación del enlace proporcionado. Comprueba que sea un enlace válido de Google Maps.',
        ];
    }

    /**
     * Extrae latitud y longitud desde una cadena de texto o URL según los patrones conocidos de Google Maps.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function extractCoordinatesFromText(string $text): ?array
    {
        // Patrón 1: /search/-16.477399,+-68.170044 o /search/-16.477399,-68.170044
        if (preg_match('/search\/(-?\d{1,2}\.\d+)[\s,+]+(-?\d{1,3}\.\d+)/i', $text, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->areCoordinatesValid($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Patrón 2: Parámetro q o query o ll (ej: ?q=-16.477399,-68.170044 o &ll=-16.477,-68.170)
        if (preg_match('/[?&](?:q|query|ll|daddr|saddr|destination)=(-?\d{1,2}\.\d+)[\s,+]+(-?\d{1,3}\.\d+)/i', $text, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->areCoordinatesValid($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Patrón 3: @lat,lng,zoom (ej: /@ -16.477399,-68.170044,17z)
        if (preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/i', $text, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->areCoordinatesValid($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Patrón 4: Protobuf data !3d{lat}!4d{lng} (muy común en URLs internas de Google Maps)
        if (preg_match('/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/i', $text, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->areCoordinatesValid($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Patrón 5: Meta refresh URL en HTML (url=https://www.google.com/maps/...)
        if (preg_match('/content=["\'].*?url=(https?[^"\']+)["\']/i', $text, $m)) {
            $redirectUrl = html_entity_decode($m[1]);
            return $this->extractCoordinatesFromText($redirectUrl);
        }

        // Patrón 6: Búsqueda genérica de par de flotantes con al menos 4 decimales
        if (preg_match('/(-?\d{1,2}\.\d{4,})[,\s+]+(-?\d{1,3}\.\d{4,})/', $text, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->areCoordinatesValid($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return null;
    }

    private function areCoordinatesValid(float $lat, float $lng): bool
    {
        return ($lat >= -90.0 && $lat <= 90.0) &&
               ($lng >= -180.0 && $lng <= 180.0) &&
               !($lat === 0.0 && $lng === 0.0);
    }
}

