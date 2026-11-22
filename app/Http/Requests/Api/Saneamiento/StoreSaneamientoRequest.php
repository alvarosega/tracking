<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Saneamiento;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreSaneamientoRequest extends FormRequest
{
    public const TIPOS_NEGOCIO = [
        'TDB A', 'TDB B', 'TDB C', 'MINORISTA', 'CASETA', 'FERIA',
        'FRIAL O CARNICERIA', 'ABARROTES', 'MAYORISTA', 'SUPERMERCADO',
        'MICROMERCADO', 'ALMACEN', 'ALMACEN DE LIMPIEZA', 'FARMACIA ZONAL',
        'CADENA DE FARMACIAS', 'HOTEL, MOTEL, ALOJAMIENTO', 'HOSPITAL, POSTA MEDICA',
        'UNIVERSIDAD O COLEGIO', 'EMPRESAS, BANCOS, COOPERATIVAS, SINDICATOS',
        'RESTAURANTE, PENSIÓN O CAFETERIA', 'PELUQUERIA', 'LAVANDERIA',
        'LICORERIA', 'ESTACION DE SERVICIO', 'BALNEARIO O PISCINA',
        'VENTA OFICINA', 'FRIAL Y CARNICERIA - TDB', 'FRIAL Y CARNICERIA - MZO',
        'CASETA - TDB', 'CASETA - MZO', 'PROVINCIA', 'Prov. MAY', 'Prov MZO',
    ];

    public const EXPEDIDOS = [
        'BEN', 'CBBA', 'LP', 'OR', 'PAN', 'PT', 'SC', 'SUC', 'TJA',
    ];

    public const TIPOS_DOCUMENTO = [
        'CI - CÉDULA DE IDENTIDAD',
        'CEX - CÉDULA DE IDENTIDAD DE EXTRANJERO',
        'PAS - PASAPORTE',
        'OD - OTRO DOCUMENTO DE IDENTIDAD',
        'NIT - NÚMERO DE IDENTIFICACIÓN TRIBUTARIA',
    ];

    public const TIPOS_NIT = [
        'CI',
        'RG',
        'RTS',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza los datos provenientes de la app móvil Android antes de validar.
     * Satisface Retrofit con nombres alternativos (client_id, ruta, tildes, teléfonos con guiones, etc.)
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // 1. Normalizar tipo_registro: soportar alta, edicion, edición, EDICIÓN, etc.
        $rawTipo = trim((string) ($this->input('tipo_registro') ?? $this->input('tipo') ?? ''));
        $normTipo = strtoupper(str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'], $rawTipo));
        if (in_array($normTipo, ['EDICION', 'EDIT', 'MODIFICACION'])) {
            $merge['tipo_registro'] = 'EDICION';
        } elseif (in_array($normTipo, ['ALTA', 'NUEVO', 'NEW'])) {
            $merge['tipo_registro'] = 'ALTA';
        }

        // 2. Normalizar cliente_id: soportar cliente_id, client_id, clienteId, clientId, id_cliente
        $rawClienteId = $this->input('cliente_id') ?? $this->input('client_id') ?? $this->input('clienteId') ?? $this->input('clientId') ?? $this->input('id_cliente');
        if ($rawClienteId !== null && $rawClienteId !== '' && $rawClienteId !== 'null') {
            $merge['cliente_id'] = is_numeric($rawClienteId) ? (int) $rawClienteId : $rawClienteId;
        }

        // 3. Normalizar route: soportar route, ruta, route_id
        $rawRoute = $this->input('route') ?? $this->input('ruta') ?? $this->input('route_id');
        if (!empty($rawRoute) && $rawRoute !== 'null') {
            $merge['route'] = trim((string) $rawRoute);
        }

        // 4. Normalizar celular y teléfono (limpiar guiones, espacios, prefijos)
        $rawCelular = $this->input('celular');
        if ($rawCelular !== null) {
            $cleanCel = preg_replace('/[^0-9]/', '', (string) $rawCelular);
            $merge['celular'] = ($cleanCel !== '' && $cleanCel !== '0') ? $cleanCel : null;
        }

        $rawTelefono = $this->input('telefono');
        if ($rawTelefono !== null) {
            $cleanTel = preg_replace('/[^0-9]/', '', (string) $rawTelefono);
            $merge['telefono'] = ($cleanTel !== '' && $cleanTel !== '0') ? $cleanTel : null;
        }

        // 5. Normalizar nombre_factura y nit
        $rawFactura = $this->input('nombre_factura') ?? $this->input('razon_social');
        if ($rawFactura !== null) {
            $strFac = trim((string) $rawFactura);
            $merge['nombre_factura'] = ($strFac !== '' && $strFac !== 'null') ? $strFac : null;
        }

        $rawNit = $this->input('nit');
        if ($rawNit !== null) {
            $cleanNit = preg_replace('/[^0-9]/', '', (string) $rawNit);
            $merge['nit'] = ($cleanNit !== '' && $cleanNit !== 'null' && $cleanNit !== '0') ? $cleanNit : null;
        }

        // 6. Normalizar coordenadas GPS
        $lat = $this->input('latitude') ?? $this->input('latitud') ?? $this->input('lat');
        if ($lat !== null && $lat !== '' && $lat !== 'null') {
            $merge['latitude'] = (float) $lat;
        }

        $lng = $this->input('longitude') ?? $this->input('longitud') ?? $this->input('lng') ?? $this->input('lon');
        if ($lng !== null && $lng !== '' && $lng !== 'null') {
            $merge['longitude'] = (float) $lng;
        }

        // 6.1 Normalizar coordenadas de Visita (Preventa separada de Entrega)
        $latVisita = $this->input('latitud_visita') ?? $this->input('latitude_visita') ?? $this->input('lat_visita');
        if ($latVisita !== null && $latVisita !== '' && $latVisita !== 'null') {
            $merge['latitud_visita'] = (float) $latVisita;
        }

        $lngVisita = $this->input('longitud_visita') ?? $this->input('longitude_visita') ?? $this->input('lng_visita') ?? $this->input('lon_visita');
        if ($lngVisita !== null && $lngVisita !== '' && $lngVisita !== 'null') {
            $merge['longitud_visita'] = (float) $lngVisita;
        }

        $dirVisita = $this->input('direccion_visita') ?? $this->input('address_visita');
        if ($dirVisita !== null && $dirVisita !== '' && $dirVisita !== 'null') {
            $merge['direccion_visita'] = trim((string) $dirVisita);
        }

        $refVisita = $this->input('referencia_visita') ?? $this->input('reference_visita');
        if ($refVisita !== null && $refVisita !== '' && $refVisita !== 'null') {
            $merge['referencia_visita'] = trim((string) $refVisita);
        }

        // 7. Normalizar zona comercial: soportar territorio, zona_comercial, zona, etc.
        $rawZona = $this->input('territorio')
            ?? $this->input('zona_comercial')
            ?? $this->input('zona')
            ?? $this->input('zonaComercial')
            ?? $this->input('zona_venta');
        if ($rawZona !== null && $rawZona !== '' && $rawZona !== 'null') {
            $cleanZona = trim((string) $rawZona);
            $merge['zona'] = $cleanZona;
            $merge['zona_comercial'] = $cleanZona;
            $merge['territorio'] = $cleanZona;
        }

        // 8. Normalizar Nombres y Apellidos desglosados
        $rawNombres = $this->input('nombres') ?? $this->input('nombre');
        if ($rawNombres !== null) {
            $merge['nombres'] = trim((string) $rawNombres);
        }
        $rawPaterno = $this->input('apellido_paterno') ?? $this->input('apellidoPaterno') ?? $this->input('paterno');
        if ($rawPaterno !== null) {
            $merge['apellido_paterno'] = trim((string) $rawPaterno);
        }
        $rawMaterno = $this->input('apellido_materno') ?? $this->input('apellidoMaterno') ?? $this->input('materno');
        if ($rawMaterno !== null) {
            $merge['apellido_materno'] = trim((string) $rawMaterno);
        }
        if (!empty($merge['nombres']) || !empty($merge['apellido_paterno']) || !empty($merge['apellido_materno'])) {
            $merge['cliente'] = trim(
                ($merge['nombres'] ?? '') . ' ' .
                ($merge['apellido_paterno'] ?? '') . ' ' .
                ($merge['apellido_materno'] ?? '')
            );
        }

        // 9. Normalizar Documento de Identidad y Expedición
        $rawDoc = $this->input('documento_identidad') ?? $this->input('ci') ?? $this->input('documentoIdentidad') ?? $this->input('documento');
        if ($rawDoc !== null) {
            $strDoc = trim((string) $rawDoc);
            $merge['documento_identidad'] = ($strDoc !== '' && $strDoc !== 'null' && $strDoc !== '0') ? $strDoc : null;
        }

        $rawExp = $this->input('expedido') ?? $this->input('exp');
        if ($rawExp !== null) {
            $cleanExp = strtoupper(trim((string) $rawExp));
            $expMap = [
                'BEN' => 'BEN', 'BENI' => 'BEN',
                'CBBA' => 'CBBA', 'COCHABAMBA' => 'CBBA', 'CB' => 'CBBA',
                'LP' => 'LP', 'LA PAZ' => 'LP',
                'OR' => 'OR', 'ORURO' => 'OR',
                'PAN' => 'PAN', 'PANDO' => 'PAN',
                'PT' => 'PT', 'POTOSI' => 'PT', 'POTOSÍ' => 'PT',
                'SC' => 'SC', 'SANTA CRUZ' => 'SC',
                'SUC' => 'SUC', 'SUCRE' => 'SUC', 'CH' => 'SUC', 'CHUQUISACA' => 'SUC',
                'TJA' => 'TJA', 'TARIJA' => 'TJA', 'TJ' => 'TJA',
            ];
            $merge['expedido'] = $expMap[$cleanExp] ?? $cleanExp;
        }

        $rawComp = $this->input('complemento') ?? $this->input('comp');
        if ($rawComp !== null) {
            $strComp = strtoupper(trim((string) $rawComp));
            $merge['complemento'] = ($strComp !== '' && $strComp !== 'null') ? $strComp : null;
        }

        // 10. Normalizar Tipo de Documento
        $rawTipoDoc = $this->input('tipo_documento') ?? $this->input('tipoDocumento');
        if ($rawTipoDoc !== null) {
            $cleanTD = strtoupper(trim((string) $rawTipoDoc));
            $tdMap = [
                'CI' => 'CI - CÉDULA DE IDENTIDAD',
                'CI - CÉDULA DE IDENTIDAD' => 'CI - CÉDULA DE IDENTIDAD',
                'CEX' => 'CEX - CÉDULA DE IDENTIDAD DE EXTRANJERO',
                'CEX - CÉDULA DE IDENTIDAD DE EXTRANJERO' => 'CEX - CÉDULA DE IDENTIDAD DE EXTRANJERO',
                'PAS' => 'PAS - PASAPORTE',
                'PASAPORTE' => 'PAS - PASAPORTE',
                'PAS - PASAPORTE' => 'PAS - PASAPORTE',
                'OD' => 'OD - OTRO DOCUMENTO DE IDENTIDAD',
                'OD - OTRO DOCUMENTO DE IDENTIDAD' => 'OD - OTRO DOCUMENTO DE IDENTIDAD',
                'NIT' => 'NIT - NÚMERO DE IDENTIFICACIÓN TRIBUTARIA',
                'NIT - NÚMERO DE IDENTIFICACIÓN TRIBUTARIA' => 'NIT - NÚMERO DE IDENTIFICACIÓN TRIBUTARIA',
            ];
            $merge['tipo_documento'] = $tdMap[$cleanTD] ?? $cleanTD;
        }

        // 11. Normalizar Tipo de NIT
        $rawTipoNit = $this->input('tipo_nit') ?? $this->input('tipoNit');
        if ($rawTipoNit !== null) {
            $cleanTN = strtoupper(trim((string) $rawTipoNit));
            $merge['tipo_nit'] = ($cleanTN !== '' && $cleanTN !== 'null') ? $cleanTN : null;
        }

        // 12. Normalizar Razón Social
        $rawRazon = $this->input('razon_social') ?? $this->input('razonSocial');
        if ($rawRazon !== null) {
            $strRazon = trim((string) $rawRazon);
            $merge['razon_social'] = ($strRazon !== '' && $strRazon !== 'null') ? $strRazon : null;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipoRegistro = strtoupper(trim((string) $this->input('tipo_registro', '')));
        $esAlta = ($tipoRegistro === 'ALTA');

        return [
            'tipo_registro' => ['required', Rule::in(['ALTA', 'EDICION'])],
            'route' => ['required', 'string', 'max:50'],

            // Nombres y Apellidos desglosados (ambos apellidos y nombres obligatorios en ALTA)
            'nombres' => [Rule::requiredIf($esAlta), 'nullable', 'string', 'min:2', 'max:150'],
            'apellido_paterno' => [Rule::requiredIf($esAlta), 'nullable', 'string', 'min:2', 'max:100'],
            'apellido_materno' => [Rule::requiredIf($esAlta), 'nullable', 'string', 'min:2', 'max:100'],

            'cliente' => [$esAlta ? 'nullable' : 'required', 'nullable', 'string', 'min:2', 'max:255'],
            'tipo_negocio' => ['required', 'string'],

            'cliente_id' => [
                $esAlta ? 'nullable' : 'required',
                'nullable',
                'integer',
                'min:1',
            ],

            'zona' => ['required', 'string', 'max:100'],
            'zona_comercial' => ['nullable', 'string', 'max:100'],
            'territorio' => ['nullable', 'string', 'max:100'],

            // Al menos teléfono o celular debe ser obligatorio en ALTA
            'telefono' => [
                Rule::requiredIf($esAlta && empty($this->input('celular'))),
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]{6,15}$/',
            ],
            'celular' => [
                Rule::requiredIf($esAlta && empty($this->input('telefono'))),
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]{7,15}$/',
            ],

            // Dirección y Referencia obligatorias en ALTA
            'direccion' => [Rule::requiredIf($esAlta), 'nullable', 'string', 'min:2', 'max:255'],
            'referencia' => [Rule::requiredIf($esAlta), 'nullable', 'string', 'min:2', 'max:255'],

            // Documento de Identidad y Expedición (Opcionales para el alta)
            'documento_identidad' => ['nullable', 'string', 'max:50'],
            'expedido' => ['nullable', 'string', Rule::in(self::EXPEDIDOS)],
            'complemento' => ['nullable', 'string', 'max:20'],
            'tipo_documento' => ['nullable', 'string', Rule::in(self::TIPOS_DOCUMENTO)],
            'tipo_nit' => ['nullable', 'string', Rule::in(self::TIPOS_NIT)],

            // Datos de facturación (Opcionales)
            'nombre_factura' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'lista_precios' => ['nullable', 'string', 'max:50'],

            'latitude' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            // Coordenadas de Visita (Preventa separada de Entrega)
            'latitud_visita' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud_visita' => ['nullable', 'numeric', 'between:-180,180'],
            'direccion_visita' => ['nullable', 'string', 'max:255'],
            'referencia_visita' => ['nullable', 'string', 'max:255'],
            'tiene_ubicacion_visita_diferente' => ['nullable'],

            'accuracy' => ['nullable', 'numeric', 'min:0'],

            'photo' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'image',
                'max:15360',
            ],

            'created_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_registro.in' => 'El tipo de registro debe ser ALTA o EDICION.',
            'cliente_id.required' => 'El identificador del cliente es obligatorio para registrar una edición.',
            'route.required' => 'La ruta es obligatoria.',
            'nombres.required' => 'Los nombres del cliente son obligatorios para registrar un alta.',
            'apellido_paterno.required' => 'El apellido paterno es obligatorio para registrar un alta.',
            'apellido_materno.required' => 'El apellido materno es obligatorio para registrar un alta.',
            'cliente.required' => 'El nombre del cliente o negocio es obligatorio.',
            'zona.required' => 'La zona es obligatoria.',
            'direccion.required' => 'La dirección es obligatoria para registrar un alta.',
            'referencia.required' => 'La referencia de ubicación es obligatoria para registrar un alta.',
            'celular.required' => 'Debe ingresar al menos un número de celular o teléfono de contacto.',
            'telefono.required' => 'Debe ingresar al menos un número de teléfono o celular de contacto.',
            'celular.regex' => 'El celular debe contener únicamente números (entre 7 y 15 dígitos).',
            'expedido.in' => 'El expedido debe ser uno de: BEN, CBBA, LP, OR, PAN, PT, SC, SUC, TJA.',
            'tipo_documento.in' => 'El tipo de documento seleccionado no es válido.',
            'tipo_nit.in' => 'El tipo de NIT debe ser CI, RG o RTS.',
            'latitude.required' => 'Las coordenadas GPS son obligatorias para registrar un alta nueva.',
            'longitude.required' => 'Las coordenadas GPS son obligatorias para registrar un alta nueva.',
            'photo.required' => 'La fotografía de la fachada es obligatoria para registrar un alta nueva.',
        ];
    }

    /**
     * Registra en el log de Laravel los detalles de cualquier fallo de validación
     * para auditoría y diagnóstico remoto inmediato con la app Android.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Fallo de validación en StoreSaneamientoRequest (API Android):', [
            'errores' => $validator->errors()->toArray(),
            'payload' => $this->except(['photo']),
            'user_id' => $this->user()?->id,
            'ip' => $this->ip(),
        ]);

        parent::failedValidation($validator);
    }
}
