<?php

declare(strict_types=1);

namespace App\Actions\Api\Tracking;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncTrackingDataAction
{
    /**
     * @param int|null $userId
     * @param array<int, array<string, mixed>>|null $locations
     * @param array<int, array<string, mixed>>|null $events
     * @return array{synced_locations: int, synced_events: int}
     */
    public function execute(?int $userId, ?array $locations, ?array $events): array
    {
        $now = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');
        $locationsCount = 0;
        $eventsCount = 0;

        DB::transaction(function () use ($userId, $locations, $events, $now, &$locationsCount, &$eventsCount): void {
            if (!empty($locations)) {
                $locationsData = [];
                foreach ($locations as $loc) {
                    $locationsData[] = [
                        'user_id' => $userId,
                        'uuid' => $loc['uuid'],
                        'latitude' => $loc['latitude'],
                        'longitude' => $loc['longitude'],
                        'accuracy' => $loc['accuracy'],
                        'speed' => $loc['speed'] ?? null,
                        'bearing' => $loc['bearing'] ?? null,
                        'battery_level' => $loc['battery_level'] ?? null,
                        'is_moving' => isset($loc['is_moving']) ? (bool) $loc['is_moving'] : false,
                        'activity_type' => $loc['activity_type'] ?? null,
                        'is_mock' => isset($loc['is_mock']) ? (bool) $loc['is_mock'] : false,
                        'tracked_at' => $loc['tracked_at'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('locations')->upsert(
                    $locationsData,
                    ['uuid'],
                    ['latitude', 'longitude', 'accuracy', 'speed', 'bearing', 'battery_level', 'is_moving', 'activity_type', 'is_mock', 'updated_at']
                );

                $locationsCount = count($locationsData);
            }

            if (!empty($events)) {
                $eventsData = [];
                foreach ($events as $event) {
                    $eventsData[] = [
                        'user_id' => $userId,
                        'uuid' => $event['uuid'],
                        'event_type' => $event['event_type'],
                        'payload' => isset($event['payload']) ? json_encode($event['payload']) : null,
                        'occurred_at' => $event['occurred_at'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('device_events')->upsert(
                    $eventsData,
                    ['uuid'],
                    ['payload', 'occurred_at', 'updated_at']
                );

                $eventsCount = count($eventsData);
            }
        });

        return [
            'synced_locations' => $locationsCount,
            'synced_events' => $eventsCount,
        ];
    }
}

