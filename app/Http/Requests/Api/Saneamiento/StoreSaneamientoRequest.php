<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Saneamiento;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipoRegistro = strtoupper(trim((string) $this->input('tipo_registro', '')));
        $esAlta = ($tipoRegistro === 'ALTA');

        return [
            'tipo_registro' => ['required', Rule::in(['ALTA', 'EDICION'])],
            'route' => ['required', 'string', 'max:50'],

            // Nombre del cliente: sin puntos, sin números, al menos nombre y apellido
            'cliente' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\s\'\-]+$/u',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $str = trim((string) $value);
                    if (str_contains($str, '.')) {
                        $fail('El nombre del cliente no puede contener puntos ni abreviaturas.');
                        return;
                    }
                    if (preg_match('/[0-9]/', $str)) {
                        $fail('El nombre del cliente no puede contener números.');
                        return;
                    }
                    $palabras = array_filter(preg_split('/\s+/', $str) ?: [], fn(string $p) => mb_strlen($p) >= 2);
                    if (count($palabras) < 2) {
                        $fail('Debe ingresar al menos un nombre y un apellido completo del cliente.');
                    }
                },
            ],

            'tipo_negocio' => ['required', 'string', Rule::in(self::TIPOS_NEGOCIO)],

            'cliente_id' => [
                Rule::requiredIf(!$esAlta),
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($esAlta): void {
                    if (!$esAlta && $value) {
                        $exists = DB::connection('supervisor')
                            ->table('pan_ruteo')
                            ->where('cliente_id', $value)
                            ->exists();

                        if (!$exists) {
                            $fail("El cliente_id {$value} no existe en el plan de ruteo maestro.");
                        }
                    }
                },
            ],

            'zona' => ['required', 'string'],

            // Contacto: si se envía, solo letras y espacios (sin números ni puntos)
            'contacto' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[\pL\s\'\-]+$/u',
            ],

            // Teléfono fijo: solo números (7 a 15 dígitos)
            'telefono' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]{7,15}$/',
            ],

            // Celular: solo números (mínimo 8 dígitos), obligatorio en edición
            'celular' => [
                $esAlta ? 'nullable' : 'required',
                'string',
                'max:50',
                'regex:/^[0-9]{8,15}$/',
            ],

            // Dirección: no vacía, mínimo 6 caracteres, no textos comodines
            'direccion' => [
                'required',
                'string',
                'min:6',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $str = mb_strtolower(trim((string) $value), 'UTF-8');
                    $invalidas = ['sn', 's/n', 's.n', 'sd', 's/d', 's.d', 'sin direccion', 'ninguna', 'no tiene'];
                    if (in_array($str, $invalidas, true)) {
                        $fail('La dirección física no puede ser un texto genérico (ingrese calle, avenida o referencia detallada).');
                    }
                },
            ],

            'referencia' => ['nullable', 'string', 'max:255'],

            'nombre_factura' => [$esAlta ? 'nullable' : 'required', 'string', 'max:255'],

            // NIT: solo números
            'nit' => [
                $esAlta ? 'nullable' : 'required',
                'string',
                'max:50',
                'regex:/^[0-9]+$/',
            ],

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
            'cliente.regex' => 'El nombre del cliente solo debe contener letras y espacios (sin números ni puntos).',
            'contacto.regex' => 'El nombre del contacto solo debe contener letras y espacios (sin números ni puntos).',
            'celular.regex' => 'El celular debe contener únicamente números (sin letras, guiones ni espacios, mínimo 8 dígitos).',
            'telefono.regex' => 'El teléfono debe contener únicamente números (entre 7 y 15 dígitos).',
            'nit.regex' => 'El NIT debe ser estrictamente numérico (solo dígitos).',
            'direccion.min' => 'La dirección física debe tener al menos 6 caracteres descriptivos.',
            'direccion.required' => 'La dirección física es obligatoria.',
        ];
    }
}
