<?php

namespace App\Http\Requests\Web\Ruteo;

use Illuminate\Foundation\Http\FormRequest;

class GetCercanosDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'radio' => ['required', 'integer', 'min:10', 'max:10000'],
            'canales' => ['nullable', 'array'],
            'canales.*' => ['string', 'max:50'],
            'rutas' => ['nullable', 'array'],
            'rutas.*' => ['string', 'max:50'],
            'meses' => ['nullable', 'array'],
            'meses.*' => ['integer', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'solo_activos' => ['nullable', 'boolean'],
        ];
    }

    public function latitud(): float
    {
        return (float) $this->input('latitud');
    }

    public function longitud(): float
    {
        return (float) $this->input('longitud');
    }

    public function radio(): int
    {
        return (int) $this->input('radio');
    }

    public function canales(): array
    {
        return (array) $this->input('canales', []);
    }

    public function rutas(): array
    {
        return (array) $this->input('rutas', []);
    }

    public function meses(): array
    {
        return (array) $this->input('meses', []);
    }

    public function anio(): int
    {
        return (int) ($this->input('anio') ?: date('Y'));
    }

    public function soloActivos(): bool
    {
        return (bool) $this->input('solo_activos', true);
    }
}

