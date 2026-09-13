<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DeviceEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorTrackingController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedDate = $request->input('date', now('America/La_Paz')->format('Y-m-d'));
        $selectedUserId = $request->input('user_id');

        $sellers = User::whereHas('role', fn($q) => $q->where('name', 'vendedor'))
            ->where('is_active', true)
            ->select('id', 'username')
            ->orderBy('username')
            ->get();

        if (!$selectedUserId && $sellers->isNotEmpty()) {
            $selectedUserId = $sellers->first()->id;
        }

        $locations = [];
        $events = [];

        if ($selectedUserId) {
            $locations = Location::where('user_id', $selectedUserId)
                ->whereDate('recorded_at', $selectedDate)
                ->orderBy('recorded_at', 'asc')
                ->select(['id', 'latitude', 'longitude', 'accuracy', 'speed', 'battery_level', 'is_mock', 'recorded_at'])
                ->get()
                ->map(fn($loc) => [
                    'id' => $loc->id,
                    'lat' => (float) $loc->latitude,
                    'lng' => (float) $loc->longitude,
                    'accuracy' => (float) $loc->accuracy,
                    'speed' => (float) $loc->speed,
                    'battery' => $loc->battery_level,
                    'is_mock' => (bool) $loc->is_mock,
                    'time' => $loc->recorded_at,
                ]);

            $events = DeviceEvent::where('user_id', $selectedUserId)
                ->whereDate('recorded_at', $selectedDate)
                ->orderBy('recorded_at', 'asc')
                ->select(['id', 'event_type', 'details', 'recorded_at'])
                ->get();
        }

        return Inertia::render('Supervisor/Tracking/Index', [
            'sellers' => $sellers,
            'filters' => [
                'date' => $selectedDate,
                'user_id' => (int) $selectedUserId,
            ],
            'locations' => $locations,
            'events' => $events,
        ]);
    }
}