<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tracking\SyncTrackingDataAction;
use App\Http\Requests\SyncTrackingRequest;
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