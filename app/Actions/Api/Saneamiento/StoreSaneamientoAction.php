<?php

declare(strict_types=1);

namespace App\Actions\Api\Saneamiento;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreSaneamientoAction
{
    /**
     * Procesa y almacena un registro de saneamiento (ALTA o EDICION) en la conexión supervisor.
     *
     * @param array<string, mixed> $validated
     * @return array{id: int, tipo_registro: string, photo_url: string|null}
     */
    public function execute(User $user, array $validated, ?UploadedFile $photo): array
    {
        $tipoRegistro = strtoupper(trim((string) $validated['tipo_registro']));
        $esAlta = ($tipoRegistro === 'ALTA');

        $photoPath = null;
        if ($photo) {
            $photoPath = $photo->store('saneamiento', 'public');
        }

        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $fechaCreacion = $validated['created_at'] ?? $nowBolivia;
        $zona = !empty($validated['territorio'])
            ? trim((string) $validated['territorio'])
            : (!empty($validated['zona_comercial'])
                ? trim((string) $validated['zona_comercial'])
                : (!empty($validated['zona']) ? trim((string) $validated['zona']) : 'SIN ZONA'));

        $nombres = !empty($validated['nombres']) ? trim((string) $validated['nombres']) : null;
        $apellidoPaterno = !empty($validated['apellido_paterno']) ? trim((string) $validated['apellido_paterno']) : null;
        $apellidoMaterno = !empty($validated['apellido_materno']) ? trim((string) $validated['apellido_materno']) : null;

        $clienteFinal = !empty($validated['cliente'])
            ? trim((string) $validated['cliente'])
            : trim("{$nombres} {$apellidoPaterno} {$apellidoMaterno}");

        $clienteId = $esAlta ? null : (isset($validated['cliente_id']) ? (int) $validated['cliente_id'] : null);

        $latitudVisita = isset($validated['latitud_visita']) && is_numeric($validated['latitud_visita'])
            ? (float) $validated['latitud_visita']
            : null;
        $longitudVisita = isset($validated['longitud_visita']) && is_numeric($validated['longitud_visita'])
            ? (float) $validated['longitud_visita']
            : null;
        $direccionVisita = !empty($validated['direccion_visita']) ? trim((string) $validated['direccion_visita']) : null;
        $referenciaVisita = !empty($validated['referencia_visita']) ? trim((string) $validated['referencia_visita']) : null;

        $insertData = [
            'user_id' => $user->id,
            'cliente_id' => $clienteId,
            'tipo_registro' => $tipoRegistro,
            'route' => trim((string) $validated['route']),
            'cliente' => $clienteFinal,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'nombres' => $nombres,
            'tipo_negocio' => trim((string) $validated['tipo_negocio']),
            'zona' => $zona,
            'lista_precios' => !empty($validated['lista_precios']) ? trim((string) $validated['lista_precios']) : null,
            'contacto' => !empty($validated['contacto']) ? trim((string) $validated['contacto']) : null,
            'telefono' => !empty($validated['telefono']) ? trim((string) $validated['telefono']) : null,
            'celular' => !empty($validated['celular']) ? trim((string) $validated['celular']) : null,
            'direccion' => !empty($validated['direccion']) ? trim((string) $validated['direccion']) : null,
            'referencia' => !empty($validated['referencia']) ? trim((string) $validated['referencia']) : null,
            'documento_identidad' => !empty($validated['documento_identidad']) ? trim((string) $validated['documento_identidad']) : null,
            'expedido' => !empty($validated['expedido']) ? trim((string) $validated['expedido']) : null,
            'complemento' => !empty($validated['complemento']) ? trim((string) $validated['complemento']) : null,
            'tipo_documento' => !empty($validated['tipo_documento']) ? trim((string) $validated['tipo_documento']) : null,
            'tipo_nit' => !empty($validated['tipo_nit']) ? trim((string) $validated['tipo_nit']) : null,
            'nombre_factura' => !empty($validated['nombre_factura']) ? trim((string) $validated['nombre_factura']) : null,
            'nit' => !empty($validated['nit']) ? trim((string) $validated['nit']) : null,
            'razon_social' => !empty($validated['razon_social']) ? trim((string) $validated['razon_social']) : null,
            'latitude' => isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            'longitude' => isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            'latitud_visita' => $latitudVisita,
            'longitud_visita' => $longitudVisita,
            'direccion_visita' => $direccionVisita,
            'referencia_visita' => $referenciaVisita,
            'accuracy' => isset($validated['accuracy']) ? (float) $validated['accuracy'] : 0.0,
            'photo_path' => $photoPath,
            'estado_revision' => 'PENDIENTE',
            'created_at' => $fechaCreacion,
            'updated_at' => $nowBolivia,
        ];

        try {
            $saneamientoId = (int) DB::connection('supervisor')
                ->table('saneamiento_base')
                ->insertGetId($insertData);

            // Sincronizar en caliente cliente_ubicaciones_visita si corresponde a EDICION
            if (!$esAlta && $clienteId) {
                $rawTieneDiferente = $validated['tiene_ubicacion_visita_diferente'] ?? null;
                $tieneVisitaDiferente = ($rawTieneDiferente === true || $rawTieneDiferente === 'true' || $rawTieneDiferente === 1 || $rawTieneDiferente === '1')
                    ? true
                    : (($rawTieneDiferente === false || $rawTieneDiferente === 'false' || $rawTieneDiferente === 0 || $rawTieneDiferente === '0') ? false : null);

                if ($latitudVisita !== null && $longitudVisita !== null) {
                    DB::connection('supervisor')->table('cliente_ubicaciones_visita')->updateOrInsert(
                        ['cliente_id' => $clienteId],
                        [
                            'latitud' => $latitudVisita,
                            'longitud' => $longitudVisita,
                            'accuracy' => (float) ($validated['accuracy'] ?? 0.0),
                            'direccion_visita' => $direccionVisita,
                            'referencia_visita' => $referenciaVisita,
                            'user_id' => $user->id,
                            'updated_at' => $nowBolivia,
                        ]
                    );
                } elseif ($tieneVisitaDiferente === false) {
                    // El usuario desactivó explícitamente la visita separada (vuelven a unificarse)
                    DB::connection('supervisor')->table('cliente_ubicaciones_visita')
                        ->where('cliente_id', $clienteId)
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            if ($photoPath) {
                Storage::disk('public')->delete((string) $photoPath);
            }
            throw $e;
        }

        return [
            'id' => $saneamientoId,
            'cliente_id' => $clienteId,
            'tipo_registro' => $tipoRegistro,
            'cliente' => $clienteFinal,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'documento_identidad' => $insertData['documento_identidad'],
            'expedido' => $insertData['expedido'],
            'complemento' => $insertData['complemento'],
            'tipo_documento' => $insertData['tipo_documento'],
            'tipo_nit' => $insertData['tipo_nit'],
            'razon_social' => $insertData['razon_social'],
            'zona' => $zona,
            'zona_comercial' => $zona,
            'territorio' => $zona,
            'latitude' => $insertData['latitude'],
            'longitude' => $insertData['longitude'],
            'latitud_visita' => $latitudVisita,
            'longitud_visita' => $longitudVisita,
            'direccion_visita' => $direccionVisita,
            'referencia_visita' => $referenciaVisita,
            'tiene_ubicacion_visita_diferente' => ($latitudVisita !== null && $longitudVisita !== null),
            'photo_url' => $photoPath ? Storage::disk('public')->url($photoPath) : null,
        ];
    }
}

