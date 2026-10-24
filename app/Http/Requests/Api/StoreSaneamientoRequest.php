<?php

namespace App\Http\Requests\Api;

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

    public function rules(): array
    {
        $tipoRegistro = strtoupper(trim((string) $this->input('tipo_registro', '')));
        $esAlta = ($tipoRegistro === 'ALTA');

        return [
            'tipo_registro' => ['required', Rule::in(['ALTA', 'EDICION'])],
            'route' => ['required', 'string', 'max:50'],
            'cliente' => ['required', 'string', 'max:255'],
            'tipo_negocio' => ['required', 'string', Rule::in(self::TIPOS_NEGOCIO)],
            'cliente_id' => [
                Rule::requiredIf(!$esAlta),
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($esAlta) {
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
            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'celular' => [$esAlta ? 'nullable' : 'required', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'nombre_factura' => [$esAlta ? 'nullable' : 'required', 'string', 'max:255'],
            'nit' => [$esAlta ? 'nullable' : 'required', 'string', 'max:50'],
            'latitude' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'numeric',
                'between:-90,90'
            ],
            'longitude' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'numeric',
                'between:-180,180'
            ],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => [
                Rule::requiredIf($esAlta),
                'nullable',
                'image',
                'max:10240'
            ],
            'created_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ];
    }
}

