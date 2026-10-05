<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'route' => ['required', 'string', 'max:100'],
            'is_opportunity' => ['required'],
            'client_id' => ['nullable', 'integer'],
            'opportunity_client_name' => ['nullable', 'string', 'max:255'],
            'distance_to_client' => ['nullable', 'numeric'],
            'is_mock_location' => ['nullable'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'max:10240'],
            'comments' => ['nullable', 'string'],
            'visited_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ]);

        $uuid = trim($validated['uuid']);

        // 1. Verificación de Idempotencia por UUID usando la conexión 'alva'
        $existingVisita = DB::connection('alva')
            ->table('visitas')
            ->where('uuid', $uuid)
            ->first();

        if ($existingVisita) {
            return response()->json([
                'message' => 'Visita previamente sincronizada',
                'id' => $existingVisita->id,
                'uuid' => $existingVisita->uuid,
                'photo_url' => Storage::disk('public')->url($existingVisita->photo_path),
            ], 200);
        }

        // 2. Auditoría y cálculo de distancia en servidor contra la conexión 'supervisor' (pan_ruteo)
        $calculatedDistance = null;
        if (!empty($validated['client_id'])) {
            $cliente = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->select('latitud', 'longitud')
                ->where('cliente_id', $validated['client_id'])
                ->first();

            if ($cliente && !is_null($cliente->latitud) && !is_null($cliente->longitud)) {
                $calculatedDistance = $this->calculateDistanceInMeters(
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    (float) $cliente->latitud,
                    (float) $cliente->longitud
                );
            }
        }

        // Fallback al valor reportado por el teléfono si no se pudo calcular en servidor
        if (is_null($calculatedDistance) && isset($validated['distance_to_client'])) {
            $calculatedDistance = (float) $validated['distance_to_client'];
        }

        // 3. Normalizar banderas booleanas
        $isOpportunity = filter_var($validated['is_opportunity'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $isMock = filter_var($request->input('is_mock_location'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        // 4. Guardar archivo físico en disco public
        $photoPath = $request->file('photo')->store('visitas', 'public');
        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');

        try {
            // USAR CONEXIÓN 'alva' PARA INSERTAR EN LA TABLA 'visitas'
            $visitaId = DB::connection('alva')
                ->table('visitas')
                ->insertGetId([
                    'uuid' => $uuid,
                    'user_id' => $user->id,
                    'client_id' => !empty($validated['client_id']) ? (int) $validated['client_id'] : null,
                    'route' => trim($validated['route']),
                    'status' => trim($validated['status']),
                    'is_opportunity' => $isOpportunity,
                    'opportunity_client_name' => $validated['opportunity_client_name'] ?? null,
                    'distance_to_client' => $calculatedDistance,
                    'is_mock_location' => $isMock, // Nombre de columna en la tabla 'visitas' de 'alva'
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'accuracy' => $validated['accuracy'],
                    'photo_path' => $photoPath,
                    'comments' => $validated['comments'] ?? null,
                    'visited_at' => $validated['visited_at'],
                    'created_at' => $nowBolivia,
                    'updated_at' => $nowBolivia,
                ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($photoPath);
            throw $e;
        }

        return response()->json([
            'message' => 'Visita procesada correctamente',
            'id' => $visitaId,
            'uuid' => $uuid,
            'photo_url' => Storage::disk('public')->url($photoPath),
        ], 201);
    }

    /**
     * Cálculo geodésico Haversine en metros.
     */
    private function calculateDistanceInMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}