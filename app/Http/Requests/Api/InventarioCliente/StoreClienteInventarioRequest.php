<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\InventarioCliente;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteInventarioRequest extends FormRequest
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
            'uuid' => ['required', 'string', 'max:64'],
            'cliente_id' => ['required', 'numeric'],
            'codigo_cliente' => ['nullable', 'string', 'max:50'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'fecha_registro' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'is_mock_location' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'numeric'],
            'items.*.codigo' => ['nullable', 'string', 'max:50'],
            'items.*.codigo_producto' => ['nullable', 'string', 'max:50'],
            'items.*.producto' => ['nullable', 'string', 'max:255'],
            'items.*.producto_nombre' => ['nullable', 'string', 'max:255'],
            'items.*.categoria' => ['nullable', 'string', 'max:100'],
            'items.*.stock_fisico' => ['required', 'integer', 'min:0'],
            'items.*.total_comprado' => ['nullable', 'integer', 'min:0'],
            'items.*.cantidad_historica' => ['nullable', 'integer', 'min:0'],
            'items.*.ultima_compra' => ['nullable', 'array'],
            'items.*.ultima_compra.fecha' => ['nullable', 'date'],
            'items.*.ultima_compra.cantidad' => ['nullable', 'integer', 'min:0'],
            'items.*.ultima_compra_fecha' => ['nullable', 'date'],
            'items.*.ultima_compra_cantidad' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uuid.required' => 'El identificador único (uuid) es obligatorio.',
            'cliente_id.required' => 'El ID del cliente es obligatorio.',
            'items.required' => 'Debe registrar al menos un producto en el inventario.',
            'items.min' => 'Debe registrar al menos un producto en el inventario.',
            'items.*.stock_fisico.required' => 'La cantidad física es requerida para cada ítem.',
            'items.*.stock_fisico.min' => 'El stock físico no puede ser negativo.',
        ];
    }
}

