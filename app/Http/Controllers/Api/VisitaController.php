<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class VisitaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autorizado. Se requiere token de sesión válido.'
            ], 401);
        }

        $userRoute = $user->username; // ej: "TDB 6A"
        $isOpportunity = filter_var($request->input('is_opportunity'), FILTER_VALIDATE_BOOLEAN);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
            'status' => ['required', 'string', 'max:50'],
            'is_opportunity' => ['required'],
            'client_id' => [
                Rule::requiredIf(!$isOpportunity),
                'nullable',
                'integer',
                // 'supervisor.pan_ruteo' indica la conexión 'supervisor' y la tabla 'pan_ruteo'
                Rule::exists('supervisor.pan_ruteo', 'cliente_id'),
            ],
            'opportunity_client_name' => [
                Rule::requiredIf($isOpportunity),
                'nullable',
                'string',
                'max:255',
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string'],
            'visited_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ]);

        $photoPath = $request->file('photo')->store('visitas', 'public');
        $serverReceiptTime = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');

        $visitaId = DB::table('visitas')->insertGetId([
            'user_id' => $user->id,
            'client_id' => $isOpportunity ? null : (int) $validated['client_id'],
            'route' => $userRoute,
            'status' => $validated['status'],
            'is_opportunity' => $isOpportunity ? 1 : 0,
            'opportunity_client_name' => $isOpportunity ? trim($validated['opportunity_client_name']) : null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'] ?? 0.0,
            'photo_path' => $photoPath,
            'comments' => $validated['comments'] ?? null,
            'visited_at' => $validated['visited_at'],
            'created_at' => $serverReceiptTime,
            'updated_at' => $serverReceiptTime,
        ]);

        return response()->json([
            'message' => 'Visita guardada exitosamente',
            'visita_id' => $visitaId,
            'photo_url' => Storage::url($photoPath),
        ], 201);
    }
}