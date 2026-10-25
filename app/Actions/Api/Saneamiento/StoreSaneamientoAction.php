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
        $zona = !empty($validated['zona']) ? trim((string) $validated['zona']) : 'SIN ZONA';

        $insertData = [
            'user_id' => $user->id,
            'cliente_id' => $esAlta ? null : (int) $validated['cliente_id'],
            'tipo_registro' => $tipoRegistro,
            'route' => trim((string) $validated['route']),
            'cliente' => trim((string) $validated['cliente']),
            'tipo_negocio' => trim((string) $validated['tipo_negocio']),
            'zona' => $zona,
            'contacto' => !empty($validated['contacto']) ? trim((string) $validated['contacto']) : null,
            'telefono' => !empty($validated['telefono']) ? trim((string) $validated['telefono']) : null,
            'celular' => !empty($validated['celular']) ? trim((string) $validated['celular']) : null,
            'direccion' => !empty($validated['direccion']) ? trim((string) $validated['direccion']) : null,
            'referencia' => !empty($validated['referencia']) ? trim((string) $validated['referencia']) : null,
            'nombre_factura' => !empty($validated['nombre_factura']) ? trim((string) $validated['nombre_factura']) : null,
            'nit' => !empty($validated['nit']) ? trim((string) $validated['nit']) : null,
            'latitude' => isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            'longitude' => isset($validated['longitude']) ? (float) $validated['longitude'] : null,
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
        } catch (\Throwable $e) {
            if ($photoPath) {
                Storage::disk('public')->delete((string) $photoPath);
            }
            throw $e;
        }

        return [
            'id' => $saneamientoId,
            'tipo_registro' => $tipoRegistro,
            'photo_url' => $photoPath ? Storage::disk('public')->url($photoPath) : null,
        ];
    }
}

