<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePedidoRechazadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si 'items' viene como un string JSON en peticiones multipart/form-data
        if ($this->has('items') && is_string($this->input('items'))) {
            $decoded = json_decode($this->input('items'), true);
            if (is_array($decoded)) {
                $this->merge(['items' => $decoded]);
            }
        }

        if (empty($this->input('fecha_rechazo'))) {
            $this->merge(['fecha_rechazo' => now('America/La_Paz')->format('Y-m-d H:i:s')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'max:64'],
            'nro_preventa' => ['required_without:cliente_id', 'nullable', 'integer'],
            'cliente_id' => ['required_without:nro_preventa', 'nullable', 'integer'],
            'codigo_cliente' => ['nullable', 'string', 'max:50'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'fecha_preventa' => ['nullable', 'date'],
            'fecha_rechazo' => ['nullable', 'date'],
            'ruta' => ['nullable', 'string', 'max:50'],
            'motivo' => ['required', 'string', 'max:150'],
            'tipo_rechazo' => ['nullable', 'string', 'in:TOTAL,PARCIAL'],
            'comentarios' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:10240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'is_mock_location' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.preventa_item_id' => ['nullable', 'integer'],
            'items.*.producto_id' => ['nullable', 'integer'],
            'items.*.codigo_producto' => ['nullable', 'string', 'max:50'],
            'items.*.producto_nombre' => ['nullable', 'string', 'max:255'],
            'items.*.categoria' => ['nullable', 'string', 'max:100'],
            'items.*.cantidad_preventa' => ['nullable', 'integer', 'min:0'],
            'items.*.cantidad_rechazada' => ['nullable', 'integer', 'min:0'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'items.*.monto_rechazado' => ['nullable', 'numeric', 'min:0'],
            'items.*.motivo_especifico' => ['nullable', 'string', 'max:150'],
        ];
    }
}

