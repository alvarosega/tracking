<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Saneamiento\StoreSaneamientoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Saneamiento\StoreSaneamientoRequest;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'message' => 'Registro de saneamiento procesado correctamente.',
            'id' => $result['id'],
            'tipo_registro' => $result['tipo_registro'],
            'photo_url' => $result['photo_url'],
        ], 201);
    }
}
