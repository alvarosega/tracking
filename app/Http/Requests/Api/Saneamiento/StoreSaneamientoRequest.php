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

            'cliente' => ['required', 'string', 'min:2', 'max:255'],
            'tipo_negocio' => ['required', 'string'],

            'cliente_id' => [
                $esAlta ? 'nullable' : 'required',
                'nullable',
                'integer',
                'min:1',
            ],

            'zona' => ['required', 'string', 'max:100'],

            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'celular' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]{7,15}$/',
            ],

            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],

            'nombre_factura' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50'],

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

            'accuracy' => ['nullable', 'numeric', 'min:0'],

            'photo' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'image',
                'max:10240',
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
            'cliente.required' => 'El nombre del cliente o negocio es obligatorio.',
            'zona.required' => 'La zona es obligatoria.',
            'celular.regex' => 'El celular debe contener únicamente números (entre 7 y 15 dígitos).',
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
