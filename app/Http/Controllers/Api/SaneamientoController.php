<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Saneamiento\StoreSaneamientoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Saneamiento\StoreSaneamientoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SaneamientoController extends Controller
{
    /**
     * Registra una alta o edición de cliente en campo para saneamiento de base.
     * POST /api/saneamiento (y /api/saneamiento-base)
     */
    public function store(
        StoreSaneamientoRequest $request,
        StoreSaneamientoAction $action
    ): JsonResponse {
        $user = $request->user();
        $result = $action->execute(
            user: $user,
            validated: $request->validated(),
            photo: $request->file('photo')
        );

        Log::info("Saneamiento API: {$result['tipo_registro']} #{$result['id']} procesada con éxito", [
            'user_id' => $user?->id,
            'cliente_id' => $request->input('cliente_id'),
            'route' => $request->input('route'),
            'cliente' => $request->input('cliente'),
        ]);

        return response()->json([
            'message' => 'Registro de saneamiento procesado correctamente.',
            'id' => $result['id'],
            'tipo_registro' => $result['tipo_registro'],
            'zona' => $result['zona'],
            'zona_comercial' => $result['zona_comercial'],
            'territorio' => $result['territorio'],
            'photo_url' => $result['photo_url'],
        ], 201);
    }
}
