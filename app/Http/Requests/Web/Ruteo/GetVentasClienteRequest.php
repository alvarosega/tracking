<?php

namespace App\Http\Requests\Web\Ruteo;

use Illuminate\Foundation\Http\FormRequest;

class GetVentasClienteRequest extends FormRequest
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
            'meses' => ['nullable', 'array'],
            'meses.*' => ['integer', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    public function meses(): array
    {
        return (array) $this->input('meses', []);
    }

    public function anio(): int
    {
        return (int) ($this->input('anio') ?: date('Y'));
    }
}

