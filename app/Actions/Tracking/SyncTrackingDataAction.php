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
            $now = now(); // Momento exacto de recepción en servidor

            if (!empty($locations)) {
                $locationRecords = array_map(function ($loc) use ($userId, $now, &$syncedLocations) {
                    $syncedLocations[] = (int) $loc['client_id'];
                    return [
                        'user_id' => $userId,
                        'client_id' => (int) $loc['client_id'],
                        'latitude' => $loc['latitude'],
                        'longitude' => $loc['longitude'],
                        'accuracy' => $loc['accuracy'] ?? null,
                        'speed' => $loc['speed'] ?? null,
                        'battery_level' => $loc['battery_level'] ?? null,
                        'is_mock' => (bool) $loc['is_mock'],
                        
                        // Mapeo de métricas del Pilar 3
                        'is_moving' => (bool) $loc['is_moving'],
                        'step_count' => (int) $loc['step_count'],
                        'motion_variance' => (float) $loc['motion_variance'],
                        
                        'recorded_at' => $loc['recorded_at'], // Sello inmutable de Bolivia
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $locations);

                // Inserción en bloques de 200 para blindar el driver PDO
                foreach (array_chunk($locationRecords, 200) as $chunk) {
                    Location::insert($chunk);
                }
            }

            if (!empty($events)) {
                $eventRecords = array_map(function ($evt) use ($userId, $now, &$syncedEvents) {
                    $syncedEvents[] = (int) $evt['client_id'];
                    return [
                        'user_id' => $userId,
                        'client_id' => (int) $evt['client_id'],
                        'event_type' => $evt['event_type'],
                        'details' => $evt['details'] ?? null,
                        'recorded_at' => $evt['recorded_at'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $events);

                foreach (array_chunk($eventRecords, 200) as $chunk) {
                    DeviceEvent::insert($chunk);
                }
            }

            return [
                'synced_locations' => $syncedLocations,
                'synced_events' => $syncedEvents,
            ];
        });
    }
}