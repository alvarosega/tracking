<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\PedidosRechazados;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
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
     * ausencia de items, alias de motivos ("AUSENTE" -> "DUENO_AUSENTE"), coordenadas en string.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // 1. Normalizar UUID: si viene vacío, nulo o el literal "null", autogenerar
        $uuid = $this->input('uuid') ?? $this->input('id') ?? $this->input('unique_id') ?? $this->input('client_uuid');
        if (empty($uuid) || $uuid === 'null') {
            $merge['uuid'] = (string) Str::uuid();
        } else {
            $merge['uuid'] = trim((string) $uuid);
        }

        // 2. Normalizar nro_preventa: soportar nro_preventa, pre_venta_id, preventa_id, nroPreventa, preventaId
        $nroPrev = $this->input('nro_preventa')
            ?? $this->input('pre_venta_id')
            ?? $this->input('preventa_id')
            ?? $this->input('nroPreventa')
            ?? $this->input('preventaId');

        if ($nroPrev !== null && $nroPrev !== '' && $nroPrev !== 'null' && is_numeric($nroPrev)) {
            $merge['nro_preventa'] = (int) $nroPrev;
        } else {
            $merge['nro_preventa'] = null;
        }

        // 3. Normalizar cliente_id: soportar cliente_id, client_id, clienteId, clientId, id_cliente
        $clienteId = $this->input('cliente_id')
            ?? $this->input('client_id')
            ?? $this->input('clienteId')
            ?? $this->input('clientId')
            ?? $this->input('id_cliente');

        if ($clienteId !== null && $clienteId !== '' && $clienteId !== 'null' && is_numeric($clienteId)) {
            $merge['cliente_id'] = (int) $clienteId;
        } else {
            $merge['cliente_id'] = null;
        }

        // 4. Normalizar cliente_nombre y codigo_cliente
        $clienteNom = $this->input('cliente_nombre')
            ?? $this->input('clienteNombre')
            ?? $this->input('cliente')
            ?? $this->input('client_name')
            ?? $this->input('nombre_cliente');
        if (!empty($clienteNom) && $clienteNom !== 'null') {
            $merge['cliente_nombre'] = trim((string) $clienteNom);
        }

        $codigoCli = $this->input('codigo_cliente')
            ?? $this->input('codigoCliente')
            ?? $this->input('client_code')
            ?? $this->input('codigo');
        if (!empty($codigoCli) && $codigoCli !== 'null') {
            $merge['codigo_cliente'] = trim((string) $codigoCli);
        }

        // 5. Normalizar ruta
        $ruta = $this->input('ruta') ?? $this->input('route');
        if (!empty($ruta) && $ruta !== 'null') {
            $merge['ruta'] = trim((string) $ruta);
        }

        // 6. Normalizar motivo: soportar motivo, motivo_codigo, motivoCodigo, codigo_motivo, motivo_id, "AUSENTE"
        $rawMotivo = $this->input('motivo')
            ?? $this->input('motivo_codigo')
            ?? $this->input('motivoCodigo')
            ?? $this->input('codigo_motivo')
            ?? $this->input('motivo_nombre')
            ?? $this->input('motivoNombre')
            ?? $this->input('reason')
            ?? $this->input('motivo_id')
            ?? $this->input('motivoId');

        if (is_numeric($rawMotivo)) {
            $motivoMap = [
                1 => 'TIENDA_CERRADA',
                2 => 'SIN_DINERO',
                3 => 'ERROR_PREVENTA',
                4 => 'DUENO_AUSENTE',
                5 => 'PRECIO_DISCONFORME',
                6 => 'PRODUCTO_DANADO',
                7 => 'PEDIDO_DUPLICADO',
                8 => 'FALTA_ESPACIO',
                9 => 'OTRO',
            ];
            $rawMotivo = $motivoMap[(int) $rawMotivo] ?? 'OTRO';
        }

        if ($rawMotivo === 'AUSENTE') {
            $rawMotivo = 'DUENO_AUSENTE';
        }

        if (!empty($rawMotivo) && $rawMotivo !== 'null') {
            $merge['motivo'] = trim((string) $rawMotivo);
        } else {
            $merge['motivo'] = 'OTRO';
        }

        // 7. Normalizar tipo_rechazo
        $tipoRechazo = $this->input('tipo_rechazo') ?? $this->input('tipoRechazo') ?? $this->input('tipo') ?? $this->input('type');
        if (!empty($tipoRechazo) && $tipoRechazo !== 'null') {
            $tipoUpper = strtoupper(trim((string) $tipoRechazo));
            $merge['tipo_rechazo'] = in_array($tipoUpper, ['TOTAL', 'PARCIAL'], true) ? $tipoUpper : 'TOTAL';
        } else {
            $merge['tipo_rechazo'] = 'TOTAL';
        }

        // 8. Normalizar comentarios
        $comentarios = $this->input('comentarios')
            ?? $this->input('comentario')
            ?? $this->input('comments')
            ?? $this->input('comment')
            ?? $this->input('observacion')
            ?? $this->input('observaciones');
        if ($comentarios !== null && $comentarios !== 'null') {
            $merge['comentarios'] = trim((string) $comentarios);
        }

        // 9. Normalizar items si vienen enviados
        $rawItems = $this->input('items')
            ?? $this->input('items_rechazados')
            ?? $this->input('itemsRechazados')
            ?? $this->input('productos')
            ?? $this->input('detalles');

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

                $prodId = $item['producto_id'] ?? $item['productoId'] ?? $item['id_producto'] ?? null;
                $prodId = (is_numeric($prodId) && (int) $prodId > 0) ? (int) $prodId : null;

                $prevItemId = $item['preventa_item_id'] ?? $item['preventaItemId'] ?? $item['id'] ?? null;
                $prevItemId = (is_numeric($prevItemId) && (int) $prevItemId > 0) ? (int) $prevItemId : null;

                $cantPrev = $item['cantidad_preventa'] ?? $item['cantidadPreventa'] ?? $item['cantidad_solicitada'] ?? 0;
                $cantPrev = (is_numeric($cantPrev) && $cantPrev !== 'null') ? max(0, (int) round((float) $cantPrev)) : 0;

                $cantRech = $item['cantidad_rechazada'] ?? $item['cantidadRechazada'] ?? $item['cantidad'] ?? $item['cant_rechazada'] ?? $item['cant'] ?? 0;
                $cantRech = (is_numeric($cantRech) && $cantRech !== 'null') ? max(0, (int) round((float) $cantRech)) : 0;

                $precioUnit = $item['precio_unitario'] ?? $item['precioUnitario'] ?? $item['precio'] ?? $item['precio_lista'] ?? 0.0;
                $precioUnit = (is_numeric($precioUnit) && $precioUnit !== 'null') ? max(0.0, round((float) $precioUnit, 2)) : 0.0;

                $codigo = $item['codigo_producto'] ?? $item['codigoProducto'] ?? $item['codigo'] ?? null;
                $codigo = (!empty($codigo) && $codigo !== 'null') ? (string) $codigo : null;

                $nombre = $item['producto_nombre'] ?? $item['productoNombre'] ?? $item['nombre'] ?? $item['producto'] ?? null;
                $nombre = (!empty($nombre) && $nombre !== 'null') ? (string) $nombre : null;

                $categoria = $item['categoria'] ?? 'General';
                $categoria = (!empty($categoria) && $categoria !== 'null') ? (string) $categoria : 'General';

                $motivoEsp = $item['motivo_especifico'] ?? $item['motivoEspecifico'] ?? $item['motivo'] ?? null;
                $motivoEsp = (!empty($motivoEsp) && $motivoEsp !== 'null') ? (string) $motivoEsp : null;

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

        // 10. Normalizar coordenadas
        $lat = $this->input('latitude') ?? $this->input('lat');
        if ($lat !== null && $lat !== '' && $lat !== 'null' && is_numeric($lat)) {
            $merge['latitude'] = (float) $lat;
        } else {
            $merge['latitude'] = null;
        }

        $lng = $this->input('longitude') ?? $this->input('lng') ?? $this->input('lon');
        if ($lng !== null && $lng !== '' && $lng !== 'null' && is_numeric($lng)) {
            $merge['longitude'] = (float) $lng;
        } else {
            $merge['longitude'] = null;
        }

        $accuracy = $this->input('accuracy') ?? $this->input('precision');
        if ($accuracy !== null && $accuracy !== '' && $accuracy !== 'null' && is_numeric($accuracy)) {
            $merge['accuracy'] = (float) $accuracy;
        } else {
            $merge['accuracy'] = null;
        }

        if ($this->has('is_mock_location') || $this->has('isMock') || $this->has('is_mock')) {
            $isMockVal = $this->input('is_mock_location') ?? $this->input('isMock') ?? $this->input('is_mock');
            $merge['is_mock_location'] = filter_var($isMockVal, FILTER_VALIDATE_BOOLEAN);
        }

        // 11. Normalizar fecha_preventa y fecha_rechazo a hora local America/La_Paz ('Y-m-d H:i:s')
        $rawFechaPrev = $this->input('fecha_preventa') ?? $this->input('fechaPreventa');
        if (!empty($rawFechaPrev) && $rawFechaPrev !== 'null') {
            try {
                $merge['fecha_preventa'] = \Carbon\Carbon::parse((string) $rawFechaPrev)->format('Y-m-d');
            } catch (\Throwable) {
                // conservar valor original si falla
            }
        }

        $rawFecha = $this->input('fecha_rechazo') ?? $this->input('fechaRechazo') ?? $this->input('fecha');
        if (!empty($rawFecha) && $rawFecha !== 'null') {
            try {
                $merge['fecha_rechazo'] = \Carbon\Carbon::parse((string) $rawFecha)
                    ->setTimezone('America/La_Paz')
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $merge['fecha_rechazo'] = \Carbon\Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
            }
        } else {
            $merge['fecha_rechazo'] = \Carbon\Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        }

        // 12. Normalizar photo/foto: si viene como string literal "null" o vacío desde Retrofit, removerlo
        foreach (['photo', 'foto', 'image', 'file'] as $fileKey) {
            if ($this->has($fileKey)) {
                $val = $this->input($fileKey);
                if ($val === 'null' || $val === '' || (is_string($val) && !$this->hasFile($fileKey))) {
                    $this->request->remove($fileKey);
                    unset($this[$fileKey]);
                }
            }
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
            'cliente_id' => ['nullable'],
            'codigo_cliente' => ['nullable', 'string', 'max:50'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'fecha_preventa' => ['nullable'],
            'fecha_rechazo' => ['nullable'],
            'ruta' => ['nullable', 'string', 'max:50'],
            'motivo' => ['required', 'string', 'max:150'],
            'tipo_rechazo' => ['nullable', 'string'],
            'comentarios' => ['nullable', 'string'],
            'photo' => ['nullable'],
            'foto' => ['nullable'],
            'image' => ['nullable'],
            'file' => ['nullable'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'is_mock_location' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
        ];
    }

    /**
     * Garantiza siempre una respuesta JSON 422 detallada ante fallos de validación y registra en log.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('StorePedidoRechazadoRequest: Fallo de validación en pedido rechazado', [
            'user_id' => $this->user()?->id,
            'username' => $this->user()?->username,
            'errors' => $validator->errors()->toArray(),
            'payload' => $this->except(['photo', 'foto', 'image', 'file']),
        ]);

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
            'motivo.required' => 'El motivo del rechazo es obligatorio.',
        ];
    }
}
