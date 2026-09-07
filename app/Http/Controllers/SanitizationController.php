<?php

namespace App\Http\Controllers;

use App\Actions\Sanitization\StoreClientAuditAction;
use App\Http\Requests\StoreClientAuditRequest;
use App\Models\ReferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanitizationController extends Controller
{
    /**
     * Descarga el catálogo referencial para modo offline
     */
    public function getClients(Request $request): JsonResponse
    {
        $query = ReferenceClient::query();

        if ($request->filled('day')) {
            $query->where('dia', strtoupper($request->query('day')));
        }

        $clients = $query->select([
            'id_cliente',
            'ruta',
            'dia',
            'direccion',
            'latitud',
            'longitud',
        ])->get();

        return response()->json($clients, 200);
    }

    /**
     * Registra la auditoría y almacena las fotos subidas
     */
    public function storeAudit(
        StoreClientAuditRequest $request,
        StoreClientAuditAction $action
    ): JsonResponse {
        $userId = $request->user()?->id;
        $photos = $request->file('photos', []);

        $audit = $action->execute(
            userId: $userId,
            data: $request->validated(),
            photos: is_array($photos) ? $photos : [$photos]
        );

        return response()->json([
            'message' => 'Auditoría registrada correctamente',
            'audit_id' => $audit->id,
        ], 201);
    }
}