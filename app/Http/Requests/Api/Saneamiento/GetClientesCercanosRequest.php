<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Saneamiento;

use Illuminate\Foundation\Http\FormRequest;

class GetClientesCercanosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radio' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'cliente' => ['nullable', 'string', 'max:255'],
            'celular' => ['nullable', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'nit' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'La latitud es obligatoria para calcular la proximidad geográfica.',
            'longitude.required' => 'La longitud es obligatoria para calcular la proximidad geográfica.',
            'latitude.between' => 'La latitud debe estar en un rango válido entre -90 y 90.',
            'longitude.between' => 'La longitud debe estar en un rango válido entre -180 y 180.',
            'radio.min' => 'El radio de búsqueda debe ser de al menos 10 metros.',
            'radio.max' => 'El radio de búsqueda no puede superar los 1000 metros.',
        ];
    }
}

