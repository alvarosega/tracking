<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\StoreVisitaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVisitaRequest;
use Illuminate\Http\JsonResponse;

class VisitaController extends Controller
{
    /**
     * Procesa y registra una visita en campo.
     * POST /api/visitas (y /api/visitas/store)
     */
    public function store(StoreVisitaRequest $request, StoreVisitaAction $action): JsonResponse
    {
        $user = $request->user();
        $result = $action->execute(
            user: $user,
            data: $request->validated(),
            photo: $request->file('photo')
        );

        return response()->json([
            'message' => $result['message'],
            'id' => $result['id'],
            'uuid' => $result['uuid'],
            'photo_url' => $result['photo_url'],
        ], $result['status_code']);
    }
}
