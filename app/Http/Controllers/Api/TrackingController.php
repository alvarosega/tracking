<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Tracking\SyncTrackingDataAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tracking\SyncTrackingRequest;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    public function sync(
        SyncTrackingRequest $request,
        SyncTrackingDataAction $action
    ): JsonResponse {
        $userId = $request->user()?->id;

        $result = $action->execute(
            userId: $userId,
            locations: $request->validated('locations'),
            events: $request->validated('events')
        );

        return response()->json([
            'message' => 'Datos sincronizados correctamente',
            'synced_locations' => $result['synced_locations'],
            'synced_events' => $result['synced_events'],
        ], 200);
    }
}
