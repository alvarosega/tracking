<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PedidosRechazados;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StorePedidoRechazadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara y normaliza los datos recibidos antes de validar.
     * Soporta multipart form data de Android (OkHttp/Retrofit), strings "null",
     * items en JSON o arrays indexados, alias de campos y coordenadas.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // 1. Normalizar UUID: si viene vacío, nulo o el literal "null", autogenerar
        $uuid = $this->input('uuid') ?? $this->input('id') ?? $this->input('unique_id');
        if (empty($uuid) || $uuid === 'null') {
            $merge['uuid'] = (string) Str::uuid();
        } else {
            $merge['uuid'] = trim((string) $uuid);
        }

        // 2. Normalizar nro_preventa
        $nroPrev = $this->input('nro_preventa') ?? $this->input('preventa_id') ?? $this->input('nroPreventa');
        if ($nroPrev !== null && $nroPrev !== '' && $nroPrev !== 'null' && is_numeric($nroPrev)) {
            $merge['nro_preventa'] = (int) $nroPrev;
        } else {
            $merge['nro_preventa'] = null;
        }

        // 3. Normalizar cliente_id: soportar cliente_id, client_id, clienteId
        $clienteId = $this->input('cliente_id') ?? $this->input('client_id') ?? $this->input('clienteId');
        if ($clienteId !== null && $clienteId !== '' && $clienteId !== 'null') {
            $merge['cliente_id'] = is_numeric($clienteId) ? (int) $clienteId : $clienteId;
        } else {
            $merge['cliente_id'] = null;
        }

        // 4. Normalizar motivo y tipo_rechazo
        $motivo = $this->input('motivo');
        if (!empty($motivo) && $motivo !== 'null') {
            $merge['motivo'] = trim((string) $motivo);
        }

        $tipoRechazo = $this->input('tipo_rechazo') ?? $this->input('tipo');
        if (!empty($tipoRechazo) && $tipoRechazo !== 'null') {
            $tipoUpper = strtoupper(trim((string) $tipoRechazo));
            $merge['tipo_rechazo'] = in_array($tipoUpper, ['TOTAL', 'PARCIAL'], true) ? $tipoUpper : 'TOTAL';
        } else {
            $merge['tipo_rechazo'] = 'TOTAL';
        }

        // 5. Normalizar items: soportar JSON string o array multipart
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

                // ID producto
                $prodId = $item['producto_id'] ?? $item['productoId'] ?? null;
                if ($prodId === 'null' || $prodId === '' || $prodId === null || !is_numeric($prodId)) {
                    $prodId = null;
                } else {
                    $prodId = (int) $prodId > 0 ? (int) $prodId : null;
                }

                // ID preventa item
                $prevItemId = $item['preventa_item_id'] ?? $item['preventaItemId'] ?? null;
                if ($prevItemId === 'null' || $prevItemId === '' || $prevItemId === null || !is_numeric($prevItemId)) {
                    $prevItemId = null;
                } else {
                    $prevItemId = (int) $prevItemId > 0 ? (int) $prevItemId : null;
                }

                // Cantidades y precio
                $cantPrev = $item['cantidad_preventa'] ?? $item['cantidadPreventa'] ?? 0;
                $cantPrev = (is_numeric($cantPrev) && $cantPrev !== 'null') ? max(0, (int) round((float) $cantPrev)) : 0;

                $cantRech = $item['cantidad_rechazada'] ?? $item['cantidadRechazada'] ?? $item['cantidad'] ?? 0;
                $cantRech = (is_numeric($cantRech) && $cantRech !== 'null') ? max(0, (int) round((float) $cantRech)) : 0;

                $precioUnit = $item['precio_unitario'] ?? $item['precioUnitario'] ?? $item['precio'] ?? 0.0;
                $precioUnit = (is_numeric($precioUnit) && $precioUnit !== 'null') ? max(0.0, round((float) $precioUnit, 2)) : 0.0;

                $codigo = $item['codigo_producto'] ?? $item['codigo'] ?? null;
                if ($codigo === 'null' || $codigo === '') {
                    $codigo = null;
                }

                $nombre = $item['producto_nombre'] ?? $item['producto'] ?? null;
                if ($nombre === 'null' || $nombre === '') {
                    $nombre = null;
                }

                $categoria = $item['categoria'] ?? 'General';
                if ($categoria === 'null' || empty($categoria)) {
                    $categoria = 'General';
                }

                $motivoEsp = $item['motivo_especifico'] ?? $item['motivo'] ?? null;
                if ($motivoEsp === 'null' || $motivoEsp === '') {
                    $motivoEsp = null;
                }

                $sanitizedItems[] = [
                    'preventa_item_id' => $prevItemId,
                    'producto_id' => $prodId,
                    'codigo_producto' => $codigo,
                    'producto_nombre' => $nombre,
                    'categoria' => $categoria,
                    'cantidad_preventa' => $cantPrev,
                    'cantidad_rechazada' => $cantRech,
                    'precio_unitario' => $precioUnit,
                    'motivo_especifico' => $motivoEsp,
                ];
            }
            $merge['items'] = $sanitizedItems;
        }

        // 6. Normalizar coordenadas si vienen como lat/lng o strings "null"
        $lat = $this->input('latitude') ?? $this->input('lat');
        if ($lat !== null && $lat !== '' && $lat !== 'null' && is_numeric($lat)) {
            $merge['latitude'] = (float) $lat;
        }

        $lng = $this->input('longitude') ?? $this->input('lng') ?? $this->input('lon');
        if ($lng !== null && $lng !== '' && $lng !== 'null' && is_numeric($lng)) {
            $merge['longitude'] = (float) $lng;
        }

        $accuracy = $this->input('accuracy');
        if ($accuracy !== null && $accuracy !== '' && $accuracy !== 'null' && is_numeric($accuracy)) {
            $merge['accuracy'] = (float) $accuracy;
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
            'nro_preventa' => ['nullable', 'numeric'],
            'cliente_id' => ['required_without:nro_preventa', 'nullable'],
            'codigo_cliente' => ['nullable', 'string', 'max:50'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'fecha_preventa' => ['nullable'],
            'fecha_rechazo' => ['nullable'],
            'ruta' => ['nullable', 'string', 'max:50'],
            'motivo' => ['required', 'string', 'max:150'],
            'tipo_rechazo' => ['nullable', 'string', 'in:TOTAL,PARCIAL'],
            'comentarios' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:20480'], // Soporta fotos de alta resolución hasta 20MB
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'is_mock_location' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.preventa_item_id' => ['nullable'],
            'items.*.producto_id' => ['nullable'],
            'items.*.codigo_producto' => ['nullable', 'string', 'max:50'],
            'items.*.producto_nombre' => ['nullable', 'string', 'max:255'],
            'items.*.categoria' => ['nullable', 'string', 'max:100'],
            'items.*.cantidad_preventa' => ['nullable', 'numeric', 'min:0'],
            'items.*.cantidad_rechazada' => ['nullable', 'numeric', 'min:0'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'items.*.motivo_especifico' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Garantiza siempre una respuesta JSON 422 detallada ante fallos de validación,
     * incluso si la petición Android multipart no incluye encabezados "Accept: application/json".
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación en los datos del pedido rechazado.',
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
            'cliente_id.required_without' => 'Debe indicar al menos el ID del cliente o el número de preventa.',
            'motivo.required' => 'El motivo del rechazo es obligatorio.',
            'photo.image' => 'El archivo adjunto debe ser una imagen válida.',
            'photo.max' => 'La fotografía de evidencia no puede superar los 20MB.',
        ];
    }
}
