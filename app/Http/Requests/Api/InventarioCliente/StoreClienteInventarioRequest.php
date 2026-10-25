<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\InventarioCliente;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StoreClienteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara y normaliza los datos recibidos antes de ejecutar las reglas de validación.
     * Maneja casos comunes de Android/OkHttp como multipart form data, claves entre corchetes,
     * strings "null", o items enviados como JSON serializado.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // 1. Normalizar UUID: si viene vacío, nulo o el literal "null", auto-generar UUID
        $uuid = $this->input('uuid') ?? $this->input('id') ?? $this->input('unique_id');
        if (empty($uuid) || $uuid === 'null') {
            $merge['uuid'] = (string) Str::uuid();
        } else {
            $merge['uuid'] = trim((string) $uuid);
        }

        // 2. Normalizar cliente_id: soportar cliente_id, client_id, clienteId
        $clienteId = $this->input('cliente_id') ?? $this->input('client_id') ?? $this->input('clienteId');
        if ($clienteId !== null && $clienteId !== '' && $clienteId !== 'null') {
            $merge['cliente_id'] = is_numeric($clienteId) ? (int) $clienteId : $clienteId;
        }

        // 3. Normalizar items: Soportar JSON string o array multipart
        $rawItems = $this->input('items');
        if (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (is_array($decoded)) {
                $rawItems = $decoded;
            }
        }

        if (is_array($rawItems)) {
            $sanitizedItems = [];
            foreach ($rawItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                // Normalizar producto_id: si viene "null", "" o no numérico, dejar null
                $prodId = $item['producto_id'] ?? $item['productoId'] ?? null;
                if ($prodId === 'null' || $prodId === '' || $prodId === null || !is_numeric($prodId)) {
                    $prodId = null;
                } else {
                    $prodId = (int) $prodId;
                }

                // Normalizar stock_fisico: aceptar enteros, decimales (ej: "5.0") o strings
                $stockFisico = $item['stock_fisico'] ?? $item['stockFisico'] ?? $item['cantidad'] ?? 0;
                if ($stockFisico === 'null' || $stockFisico === '' || $stockFisico === null || !is_numeric($stockFisico)) {
                    $stockFisico = 0;
                } else {
                    $stockFisico = (int) round((float) $stockFisico);
                }

                $codigo = $item['codigo_producto'] ?? $item['codigo'] ?? $item['codigoProducto'] ?? null;
                if ($codigo === 'null' || $codigo === '') {
                    $codigo = null;
                }

                $nombre = $item['producto_nombre'] ?? $item['producto'] ?? $item['productoNombre'] ?? null;
                if ($nombre === 'null' || $nombre === '') {
                    $nombre = null;
                }

                $categoria = $item['categoria'] ?? 'General';
                if ($categoria === 'null' || empty($categoria)) {
                    $categoria = 'General';
                }

                $sanitizedItems[] = [
                    'producto_id' => $prodId,
                    'codigo_producto' => $codigo,
                    'producto_nombre' => $nombre,
                    'categoria' => $categoria,
                    'stock_fisico' => max(0, $stockFisico),
                    'total_comprado' => isset($item['total_comprado']) && is_numeric($item['total_comprado']) ? (int) $item['total_comprado'] : 0,
                    'cantidad_historica' => isset($item['cantidad_historica']) && is_numeric($item['cantidad_historica']) ? (int) $item['cantidad_historica'] : 0,
                    'ultima_compra_fecha' => !empty($item['ultima_compra_fecha']) && $item['ultima_compra_fecha'] !== 'null'
                        ? $item['ultima_compra_fecha']
                        : (!empty($item['ultima_compra']['fecha']) && $item['ultima_compra']['fecha'] !== 'null' ? $item['ultima_compra']['fecha'] : null),
                    'ultima_compra_cantidad' => isset($item['ultima_compra_cantidad']) && is_numeric($item['ultima_compra_cantidad'])
                        ? (int) $item['ultima_compra_cantidad']
                        : (isset($item['ultima_compra']['cantidad']) && is_numeric($item['ultima_compra']['cantidad']) ? (int) $item['ultima_compra']['cantidad'] : 0),
                ];
            }
            $merge['items'] = $sanitizedItems;
        }

        // 4. Normalizar coordenadas si vienen como lat/lng o strings "null"
        $lat = $this->input('latitude') ?? $this->input('lat');
        if ($lat !== null && $lat !== '' && $lat !== 'null' && is_numeric($lat)) {
            $merge['latitude'] = (float) $lat;
        }

        $lng = $this->input('longitude') ?? $this->input('lng') ?? $this->input('lon');
        if ($lng !== null && $lng !== '' && $lng !== 'null' && is_numeric($lng)) {
            $merge['longitude'] = (float) $lng;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'max:64'],
            'cliente_id' => ['required'],
            'codigo_cliente' => ['nullable', 'string', 'max:50'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'fecha_registro' => ['nullable'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'is_mock_location' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable'],
            'items.*.codigo' => ['nullable', 'string', 'max:50'],
            'items.*.codigo_producto' => ['nullable', 'string', 'max:50'],
            'items.*.producto' => ['nullable', 'string', 'max:255'],
            'items.*.producto_nombre' => ['nullable', 'string', 'max:255'],
            'items.*.categoria' => ['nullable', 'string', 'max:100'],
            'items.*.stock_fisico' => ['required', 'numeric', 'min:0'],
            'items.*.total_comprado' => ['nullable', 'numeric'],
            'items.*.cantidad_historica' => ['nullable', 'numeric'],
            'items.*.ultima_compra' => ['nullable'],
            'items.*.ultima_compra_fecha' => ['nullable'],
            'items.*.ultima_compra_cantidad' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Garantiza siempre una respuesta JSON 422 detallada ante fallos de validación,
     * incluso si la petición Android no incluye encabezados "Accept: application/json".
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación en los datos de inventario.',
                'errors' => $validator->errors(),
            ], 422)
        );
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
