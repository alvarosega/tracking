<?php

namespace App\Actions\Api;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreSaneamientoAction
{
    /**
     * Procesa y almacena un registro de saneamiento (ALTA o EDICION) en la conexión supervisor.
     */
    public function execute(User $user, array $validated, ?UploadedFile $photo): array
    {
        $tipoRegistro = strtoupper(trim($validated['tipo_registro']));
        $esAlta = ($tipoRegistro === 'ALTA');

        $photoPath = null;
        if ($photo) {
            $photoPath = $photo->store('saneamiento', 'public');
        }

        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $fechaCreacion = $validated['created_at'] ?? $nowBolivia;
        $zona = !empty($validated['zona']) ? trim($validated['zona']) : 'SIN ZONA';

        $insertData = [
            'user_id' => $user->id,
            'cliente_id' => $esAlta ? null : (int) $validated['cliente_id'],
            'tipo_registro' => $tipoRegistro,
            'route' => trim($validated['route']),
            'cliente' => trim($validated['cliente']),
            'tipo_negocio' => trim($validated['tipo_negocio']),
            'zona' => $zona,
            'contacto' => !empty($validated['contacto']) ? trim($validated['contacto']) : null,
            'telefono' => !empty($validated['telefono']) ? trim($validated['telefono']) : null,
            'celular' => !empty($validated['celular']) ? trim($validated['celular']) : null,
            'direccion' => !empty($validated['direccion']) ? trim($validated['direccion']) : null,
            'referencia' => !empty($validated['referencia']) ? trim($validated['referencia']) : null,
            'nombre_factura' => !empty($validated['nombre_factura']) ? trim($validated['nombre_factura']) : null,
            'nit' => !empty($validated['nit']) ? trim($validated['nit']) : null,
            'latitude' => $esAlta ? ($validated['latitude'] ?? null) : null,
            'longitude' => $esAlta ? ($validated['longitude'] ?? null) : null,
            'accuracy' => $esAlta ? ($validated['accuracy'] ?? 0.0) : 0.0,
            'photo_path' => $photoPath,
            'estado_revision' => 'PENDIENTE',
            'created_at' => $fechaCreacion,
            'updated_at' => $nowBolivia,
        ];

        try {
            $saneamientoId = DB::connection('supervisor')
                ->table('saneamiento_base')
                ->insertGetId($insertData);
        } catch (\Throwable $e) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
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

