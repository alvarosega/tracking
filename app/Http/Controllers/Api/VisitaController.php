<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $validated = $request->validate([
            'uuid' => ['required', 'string', 'size:36'],
            'photo' => ['required', 'image', 'max:10240'],
            'status' => ['required', 'string', 'max:50'],
            'client_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('supervisor')
                        ->table('pan_ruteo')
                        ->where('cliente_id', $value)
                        ->exists();

                    if (!$exists) {
                        $fail("El cliente seleccionado no existe en el plan de ruteo.");
                    }
                },
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'distance_to_client' => ['nullable', 'numeric', 'min:0'],
            'is_mock' => ['nullable'],
            'comments' => ['nullable', 'string'],
            'visited_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ]);

        // 1. Verificación de Idempotencia
        $existingVisita = DB::table('visitas')
            ->where('uuid', $validated['uuid'])
            ->first();

        if ($existingVisita) {
            return response()->json([
                'message' => 'Visita previamente sincronizada',
                'visita_id' => $existingVisita->id,
                'photo_url' => Storage::disk('public')->url($existingVisita->photo_path),
            ], 200);
        }

        // 2. Consulta de coordenadas del cliente en supervisor y cálculo Haversine
        $cliente = DB::connection('supervisor')
            ->table('pan_ruteo')
            ->select('latitud', 'longitud')
            ->where('cliente_id', $validated['client_id'])
            ->first();

        $calculatedDistance = null;

        if ($cliente && !is_null($cliente->latitud) && !is_null($cliente->longitud)) {
            $calculatedDistance = $this->calculateDistanceInMeters(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $cliente->latitud,
                (float) $cliente->longitud
            );
        } else {
            // Fallback al valor reportado por el dispositivo si el cliente no tuviera coordenadas en pan_ruteo
            $calculatedDistance = isset($validated['distance_to_client']) ? (float) $validated['distance_to_client'] : null;
        }

        // 3. Normalizar estado de Mock
        $isMock = filter_var($request->input('is_mock'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        // 4. Almacenamiento seguro del archivo de imagen
        $photoPath = $request->file('photo')->store('visitas', 'public');
        $serverReceiptTime = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $userRoute = $user->username;

        try {
            $visitaId = DB::table('visitas')->insertGetId([
                'uuid' => $validated['uuid'],
                'user_id' => $user->id,
                'client_id' => (int) $validated['client_id'],
                'route' => $userRoute,
                'status' => $validated['status'],
                'is_opportunity' => 0,
                'opportunity_client_name' => null,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'accuracy' => $validated['accuracy'] ?? 0.0,
                'distance_to_client' => $calculatedDistance,
                'is_mock' => $isMock,
                'photo_path' => $photoPath,
                'comments' => $validated['comments'] ?? null,
                'visited_at' => $validated['visited_at'],
                'created_at' => $serverReceiptTime,
                'updated_at' => $serverReceiptTime,
            ]);
        } catch (\Throwable $e) {
            // Evitar archivos huérfanos si la base de datos falla
            Storage::disk('public')->delete($photoPath);
            throw $e;
        }

        return response()->json([
            'message' => 'Visita guardada exitosamente',
            'visita_id' => $visitaId,
            'photo_url' => Storage::disk('public')->url($photoPath),
        ], 201);
    }

    /**
     * Cálculo de distancia geodésica mediante la fórmula de Haversine.
     */
    private function calculateDistanceInMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // Radio terrestre en metros

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}