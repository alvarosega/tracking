<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tracking;

use Illuminate\Foundation\Http\FormRequest;

class SyncTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locations' => ['nullable', 'array'],
            'locations.*.uuid' => ['required', 'string', 'max:255'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy' => ['required', 'numeric', 'min:0'],
            'locations.*.speed' => ['nullable', 'numeric'],
            'locations.*.bearing' => ['nullable', 'numeric'],
            'locations.*.battery_level' => ['nullable', 'integer', 'between:0,100'],
            'locations.*.is_moving' => ['nullable', 'boolean'],
            'locations.*.activity_type' => ['nullable', 'string', 'max:50'],
            'locations.*.is_mock' => ['nullable', 'boolean'],
            'locations.*.tracked_at' => ['required', 'date_format:Y-m-d H:i:s'],

            'events' => ['nullable', 'array'],
            'events.*.uuid' => ['required', 'string', 'max:255'],
            'events.*.event_type' => ['required', 'string', 'max:50'],
            'events.*.payload' => ['nullable', 'array'],
            'events.*.occurred_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }
}

