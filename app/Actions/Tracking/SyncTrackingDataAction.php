<?php

namespace App\Actions\Tracking;

use App\Models\DeviceEvent;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class SyncTrackingDataAction
{
    public function execute(?int $userId, array $locations, array $events): array
    {
        return DB::transaction(function () use ($userId, $locations, $events) {
            $syncedLocations = [];
            $syncedEvents = [];
            
            // Hora oficial del servidor en zona America/La_Paz
            $now = now();

            if (!empty($locations)) {
                $locationRecords = array_map(function ($loc) use ($userId, $now, &$syncedLocations) {
                    $syncedLocations[] = (int) $loc['client_id'];
                    return [
                        'user_id' => $userId,
                        'client_id' => $loc['client_id'],
                        'latitude' => $loc['latitude'],
                        'longitude' => $loc['longitude'],
                        'accuracy' => $loc['accuracy'] ?? null,
                        'speed' => $loc['speed'] ?? null,
                        'battery_level' => $loc['battery_level'] ?? null,
                        'is_mock' => $loc['is_mock'],
                        // BLINDAJE ANTIFRAUDE: Usamos la hora oficial del servidor en lugar de la del cliente
                        'recorded_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $locations);

                Location::insert($locationRecords);
            }

            if (!empty($events)) {
                $eventRecords = array_map(function ($evt) use ($userId, $now, &$syncedEvents) {
                    $syncedEvents[] = (int) $evt['client_id'];
                    return [
                        'user_id' => $userId,
                        'client_id' => $evt['client_id'],
                        'event_type' => $evt['event_type'],
                        'details' => $evt['details'] ?? null,
                        // BLINDAJE ANTIFRAUDE: Hora oficial del servidor para eventos del dispositivo
                        'recorded_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $events);

                DeviceEvent::insert($eventRecords);
            }

            return [
                'synced_locations' => $syncedLocations,
                'synced_events' => $syncedEvents,
            ];
        });
    }
}