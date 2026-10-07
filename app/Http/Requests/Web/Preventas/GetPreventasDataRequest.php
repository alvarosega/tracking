<?php

namespace App\Http\Requests\Web\Preventas;

use Illuminate\Foundation\Http\FormRequest;

class GetPreventasDataRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d'],
            'canales' => ['nullable', 'array'],
            'canales.*' => ['string', 'max:50'],
            'rutas' => ['nullable', 'array'],
            'rutas.*' => ['string', 'max:50'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'radio' => ['nullable', 'integer', 'min:10', 'max:10000'],
        ];
    }

    public function fecha(): string
    {
        return (string) $this->input('fecha');
    }

    public function canales(): array
    {
        return (array) $this->input('canales', []);
    }

    public function rutas(): array
    {
        return (array) $this->input('rutas', []);
    }

    public function latitud(): ?float
    {
        return $this->filled('latitud') ? (float) $this->input('latitud') : null;
    }

    public function longitud(): ?float
    {
        return $this->filled('longitud') ? (float) $this->input('longitud') : null;
    }

    public function radio(): int
    {
        return (int) ($this->input('radio') ?: 300);
    }
}

