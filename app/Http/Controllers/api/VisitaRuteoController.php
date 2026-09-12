<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanRuteo;
use App\Models\VisitaRuteo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VisitaRuteoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png|max:5120',
            'status' => 'required|in:PREVENTA,SIN_DINERO,TIENDA_CERRADA,AUSENTE',
            'is_new_client' => 'required|boolean',
            'client_id' => 'required_if:is_new_client,false,0|nullable|integer',
            'new_client_name' => 'required_if:is_new_client,true,1|nullable|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'comments' => 'nullable|string|max:1000',
            'visited_at' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $user = $request->user();
        $distanceToTarget = null;

        // Auditoría anti-fraude: Validar distancia contra el cliente de plan_ruteo
        if (!$validated['is_new_client'] && !empty($validated['client_id'])) {
            $targetClient = PlanRuteo::where('client_id', $validated['client_id'])
                ->where('route', $user->username)
                ->first();

            if ($targetClient) {
                $distanceToTarget = $this->calculateHaversineDistance(
                    (float)$validated['latitude'],
                    (float)$validated['longitude'],
                    (float)$targetClient->latitude,
                    (float)$targetClient->longitude
                );
            }
        }

        // Almacenar la fotografía en storage/app/public/visitas
        $photoFile = $request->file('photo');
        $photoPath = $photoFile->store('visitas', 'public');

        $visita = VisitaRuteo::create([
            'user_id' => $user->id,
            'client_id' => $validated['is_new_client'] ? null : $validated['client_id'],
            'status' => $validated['status'],
            'is_new_client' => filter_var($validated['is_new_client'], FILTER_VALIDATE_BOOLEAN),
            'new_client_name' => $validated['is_new_client'] ? $validated['new_client_name'] : null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'],
            'distance_to_target' => $distanceToTarget,
            'photo_path' => $photoPath,
            'comments' => $validated['comments'] ?? null,
            'visited_at' => $validated['visited_at'],
        ]);

        return response()->json([
            'message' => 'Visita registrada con éxito',
            'data' => $visita,
        ], 201);
    }

    /**
     * Calcula la distancia ortodrómica en metros entre dos coordenadas.
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // Radio de la Tierra en metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}