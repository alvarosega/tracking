<?php

declare(strict_types=1);

namespace App\Actions\Api\Visitas;

use App\Models\User;
use App\Services\GeoService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreVisitaAction
{
    public function __construct(
        protected GeoService $geoService
    ) {}

    /**
     * Procesa y almacena la visita en campo con idempotencia y cálculo geodésico de proximidad.
     *
     * @param array<string, mixed> $data
     * @return array{idempotent: bool, status_code: int, message: string, id: int, uuid: string, photo_url: string}
     */
    public function execute(User $user, array $data, UploadedFile $photo): array
    {
        $uuid = trim((string) $data['uuid']);

        // 1. Verificación de Idempotencia por UUID (mysql)
        $existingVisita = DB::table('visitas')->where('uuid', $uuid)->first();
        if ($existingVisita) {
            return [
                'idempotent' => true,
                'status_code' => 200,
                'message' => 'Visita previamente sincronizada',
                'id' => (int) $existingVisita->id,
                'uuid' => (string) $existingVisita->uuid,
                'photo_url' => Storage::disk('public')->url($existingVisita->photo_path),
            ];
        }

        // 2. Auditoría y cálculo de distancia en servidor contra pan_ruteo (supervisor)
        $calculatedDistance = null;
        if (!empty($data['client_id'])) {
            $cliente = DB::connection('supervisor')
                ->table('pan_ruteo')
                ->select('latitud', 'longitud')
                ->where('cliente_id', $data['client_id'])
                ->first();

            if ($cliente && !is_null($cliente->latitud) && !is_null($cliente->longitud)) {
                $calculatedDistance = $this->geoService->calculateDistanceInMeters(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    (float) $cliente->latitud,
                    (float) $cliente->longitud
                );
            }
        }

        if (is_null($calculatedDistance) && isset($data['distance_to_client'])) {
            $calculatedDistance = (float) $data['distance_to_client'];
        }

        $isOpportunity = filter_var($data['is_opportunity'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $isMock = filter_var($data['is_mock_location'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        $photoPath = $photo->store('visitas', 'public');
        $nowBolivia = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');

        try {
            $visitaId = (int) DB::table('visitas')->insertGetId([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'client_id' => !empty($data['client_id']) ? (int) $data['client_id'] : null,
                'route' => trim((string) $data['route']),
                'status' => trim((string) $data['status']),
                'is_opportunity' => $isOpportunity,
                'opportunity_client_name' => $data['opportunity_client_name'] ?? null,
                'distance_to_client' => $calculatedDistance,
                'is_mock' => $isMock,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy' => $data['accuracy'],
                'photo_path' => $photoPath,
                'comments' => $data['comments'] ?? null,
                'visited_at' => $data['visited_at'],
                'created_at' => $nowBolivia,
                'updated_at' => $nowBolivia,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete((string) $photoPath);
            throw $e;
        }

        return [
            'idempotent' => false,
            'status_code' => 201,
            'message' => 'Visita procesada correctamente',
            'id' => $visitaId,
            'uuid' => $uuid,
            'photo_url' => Storage::disk('public')->url($photoPath),
        ];
    }
}

