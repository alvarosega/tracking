<?php

namespace App\Http\Requests\Web\Ruteo;

use Illuminate\Foundation\Http\FormRequest;

class GetRuteoDataRequest extends FormRequest
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
            'canales' => ['nullable', 'array'],
            'canales.*' => ['string', 'max:50'],
            'rutas' => ['nullable', 'array'],
            'rutas.*' => ['string', 'max:50'],
            'dias' => ['nullable', 'array'],
            'dias.*' => ['string', 'max:20'],
            'ver_frontera_rutas' => ['nullable', 'boolean'],
            'ver_frontera_dias' => ['nullable', 'boolean'],
            'solo_activos' => ['nullable', 'boolean'],
        ];
    }

    public function canales(): array
    {
        return (array) $this->input('canales', []);
    }

    public function rutas(): array
    {
        return (array) $this->input('rutas', []);
    }

    public function dias(): array
    {
        return (array) $this->input('dias', []);
    }

    public function verFronteraRutas(): bool
    {
        return (bool) $this->input('ver_frontera_rutas', false);
    }

    public function verFronteraDias(): bool
    {
        return (bool) $this->input('ver_frontera_dias', false);
    }

    public function soloActivos(): bool
    {
        return (bool) $this->input('solo_activos', true);
    }
}

