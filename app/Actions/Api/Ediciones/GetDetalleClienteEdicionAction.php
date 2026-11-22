<?php

declare(strict_types=1);

namespace App\Actions\Api\Ediciones;

use Illuminate\Support\Facades\DB;

class GetDetalleClienteEdicionAction
{
    private const EXPEDIDOS = [
        'BEN', 'CBBA', 'LP', 'OR', 'PAN', 'PT', 'SC', 'SUC', 'TJA',
    ];

    /**
     * Obtiene el detalle unificado y desglosado de un cliente para la pantalla de Edición en Android.
     * Prioriza la última edición registrada en saneamiento_base y toma pan_ruteo como base.
     *
     * @param int $clienteId
     * @return array<string, mixed>|null
     */
    public function execute(int $clienteId): ?array
    {
        // 1. Consultar registro base en pan_ruteo
        $panRow = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->where('cliente_id', $clienteId)
            ->first();

        // 2. Consultar última edición en saneamiento_base
        $edicionRow = DB::connection('supervisor')
            ->table('saneamiento_base')
            ->where('cliente_id', $clienteId)
            ->where('tipo_registro', 'EDICION')
            ->orderBy('id', 'desc')
            ->first();

        if (!$panRow && !$edicionRow) {
            return null;
        }

        // 3. Consultar ubicación de visita separada en cliente_ubicaciones_visita
        $visitaRow = DB::connection('supervisor')
            ->table('cliente_ubicaciones_visita')
            ->where('cliente_id', $clienteId)
            ->first();

        // 4. Resolver nombres y apellidos desglosados
        $nombres = $edicionRow?->nombres;
        $apellidoPaterno = $edicionRow?->apellido_paterno;
        $apellidoMaterno = $edicionRow?->apellido_materno;
        $clienteNombre = $edicionRow?->cliente ?? trim((string) ($panRow?->cliente ?: ($panRow?->cliente_norm ?? '')));

        if (empty($nombres) && empty($apellidoPaterno) && empty($apellidoMaterno) && !empty($clienteNombre)) {
            [$paternoParsed, $maternoParsed, $nombresParsed] = $this->dividirNombreCompleto($clienteNombre);
            $apellidoPaterno = $paternoParsed;
            $apellidoMaterno = $maternoParsed;
            $nombres = $nombresParsed;
        }

        // 5. Resolver documento de identidad y expedido
        $docIdentidad = $edicionRow?->documento_identidad;
        $expedido = $edicionRow?->expedido;
        $complemento = $edicionRow?->complemento;
        $tipoDoc = $edicionRow?->tipo_documento ?? 'CI - CÉDULA DE IDENTIDAD';
        $tipoNit = $edicionRow?->tipo_nit ?? 'CI';

        if (empty($docIdentidad) && !empty($panRow?->ci)) {
            [$docParsed, $expParsed] = $this->extraerCiYExpedido((string) $panRow->ci);
            $docIdentidad = $docParsed;
            if (empty($expedido) && $expParsed) {
                $expedido = $expParsed;
            }
        }

        // 6. Resolver datos comerciales y facturación
        $tipoNegocio = $edicionRow?->tipo_negocio ?: ($panRow?->tipo_negocio ?? '');
        $zona = $edicionRow?->zona ?: ($panRow?->territorio ?: ($panRow?->zona_venta ?? ''));
        $listaPrecios = $edicionRow?->lista_precios ?: ($panRow?->precio ?? null);
        $contacto = $edicionRow?->contacto ?: ($panRow?->contacto ?? null);
        $telefono = $edicionRow?->telefono ?: ($panRow?->telefono ?? null);
        $celular = $edicionRow?->celular ?: ($panRow?->celular ?? null);
        $nombreFactura = $edicionRow?->nombre_factura ?: ($panRow?->nombre_factura ?? null);
        $razonSocial = $edicionRow?->razon_social ?: ($panRow?->nombre_factura ?? null);
        $nit = $edicionRow?->nit ?: ($panRow?->nit ?? null);
        $ruta = $edicionRow?->route ?: ($panRow?->ruta ?? '');

        // 7. Resolver ubicación de entrega (coordenada principal)
        $latEntrega = $edicionRow?->latitude !== null
            ? (float) $edicionRow->latitude
            : ($panRow?->latitud !== null ? (float) $panRow->latitud : null);

        $lngEntrega = $edicionRow?->longitude !== null
            ? (float) $edicionRow->longitude
            : ($panRow?->longitud !== null ? (float) $panRow->longitud : null);

        $dirEntrega = $edicionRow?->direccion ?: ($panRow?->direccion ?? null);
        $refEntrega = $edicionRow?->referencia ?: ($panRow?->referencia ?? null);

        // 8. Resolver ubicación de visita (preventa)
        $tieneVisitaDiferente = ($visitaRow !== null) || ($edicionRow?->latitud_visita !== null && $edicionRow?->longitud_visita !== null);

        $latVisita = $visitaRow?->latitud !== null
            ? (float) $visitaRow->latitud
            : ($edicionRow?->latitud_visita !== null ? (float) $edicionRow->latitud_visita : null);

        $lngVisita = $visitaRow?->longitud !== null
            ? (float) $visitaRow->longitud
            : ($edicionRow?->longitud_visita !== null ? (float) $edicionRow->longitud_visita : null);

        $dirVisita = $visitaRow?->direccion_visita ?: ($edicionRow?->direccion_visita ?? null);
        $refVisita = $visitaRow?->referencia_visita ?: ($edicionRow?->referencia_visita ?? null);

        return [
            'cliente_id' => $clienteId,
            'cliente' => $clienteNombre,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'tipo_negocio' => (string) $tipoNegocio,
            'zona' => (string) $zona,
            'ruta' => (string) $ruta,
            'route' => (string) $ruta,
            'lista_precios' => $listaPrecios ? (string) $listaPrecios : null,
            'contacto' => $contacto ? (string) $contacto : null,
            'telefono' => $telefono ? (string) $telefono : null,
            'celular' => $celular ? (string) $celular : null,
            'documento_identidad' => $docIdentidad ? (string) $docIdentidad : null,
            'ci' => $docIdentidad ? (string) $docIdentidad : null,
            'expedido' => $expedido ? (string) $expedido : null,
            'complemento' => $complemento ? (string) $complemento : null,
            'tipo_documento' => (string) $tipoDoc,
            'tipo_nit' => (string) $tipoNit,
            'nombre_factura' => $nombreFactura ? (string) $nombreFactura : null,
            'razon_social' => $razonSocial ? (string) $razonSocial : null,
            'nit' => $nit ? (string) $nit : null,
            
            // Ubicación de Entrega
            'direccion' => $dirEntrega ? (string) $dirEntrega : null,
            'referencia' => $refEntrega ? (string) $refEntrega : null,
            'latitude' => $latEntrega,
            'longitude' => $lngEntrega,
            'latitud' => $latEntrega,
            'longitud' => $lngEntrega,

            // Ubicación de Visita
            'tiene_ubicacion_visita_diferente' => (bool) $tieneVisitaDiferente,
            'latitud_visita' => $latVisita,
            'longitud_visita' => $lngVisita,
            'direccion_visita' => $dirVisita ? (string) $dirVisita : null,
            'referencia_visita' => $refVisita ? (string) $refVisita : null,
        ];
    }

    /**
     * Divide un nombre completo en [ApellidoPaterno, ApellidoMaterno, Nombres].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function dividirNombreCompleto(string $fullName): array
    {
        $tokens = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        $total = count($tokens);

        if ($total === 0) {
            return ['', '', ''];
        }

        if ($total === 1) {
            return ['', '', mb_convert_case($tokens[0], MB_CASE_TITLE, 'UTF-8')];
        }

        if ($total === 2) {
            return [
                mb_convert_case($tokens[0], MB_CASE_TITLE, 'UTF-8'),
                '',
                mb_convert_case($tokens[1], MB_CASE_TITLE, 'UTF-8'),
            ];
        }

        $materno = array_pop($tokens);
        $paterno = array_pop($tokens);
        $nombres = implode(' ', $tokens);

        return [
            mb_convert_case($paterno, MB_CASE_TITLE, 'UTF-8'),
            mb_convert_case($materno, MB_CASE_TITLE, 'UTF-8'),
            mb_convert_case($nombres, MB_CASE_TITLE, 'UTF-8'),
        ];
    }

    /**
     * Extrae el número de CI y el departamento expedidor si viene adjunto.
     *
     * @return array{0: string, 1: string|null}
     */
    private function extraerCiYExpedido(string $ciRaw): array
    {
        $clean = strtoupper(trim($ciRaw));
        if ($clean === '') {
            return ['', null];
        }

        $expedidoEncontrado = null;
        foreach (self::EXPEDIDOS as $exp) {
            if (str_ends_with($clean, ' ' . $exp) || str_ends_with($clean, '-' . $exp) || str_ends_with($clean, $exp)) {
                $expedidoEncontrado = $exp;
                $clean = trim(preg_replace('/[\s\-]*' . preg_quote($exp, '/') . '$/i', '', $clean));
                break;
            }
        }

        return [$clean, $expedidoEncontrado];
    }
}
