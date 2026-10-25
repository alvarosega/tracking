<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\AltasEdiciones;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoRevisionRequest extends FormRequest
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
            'estado' => ['required', 'string', 'in:APROBADO,RECHAZADO,PENDIENTE,Aprobado,Rechazado,Pendiente'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function estado(): string
    {
        return strtoupper(trim((string) $this->input('estado')));
    }

    public function motivo(): ?string
    {
        return $this->filled('motivo') ? trim((string) $this->input('motivo')) : null;
    }
}

