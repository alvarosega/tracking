<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\AltasEdiciones;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoRevisionMasivoRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
            'estado' => ['required', 'string', 'in:APROBADO,RECHAZADO,PENDIENTE,Aprobado,Rechazado,Pendiente,aprobado,rechazado,pendiente'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return int[]
     */
    public function ids(): array
    {
        $raw = $this->input('ids', []);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $raw),
            fn(int $id) => $id > 0
        )));
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
