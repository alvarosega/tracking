<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locations' => ['present', 'array'],
            'locations.*.client_id' => ['required', 'integer'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy' => ['nullable', 'numeric'],
            'locations.*.speed' => ['nullable', 'numeric'],
            'locations.*.battery_level' => ['nullable', 'integer', 'between:0,100'],
            'locations.*.is_mock' => ['required', 'boolean'],
            'locations.*.recorded_at' => ['required', 'date_format:Y-m-d H:i:s'],

            'events' => ['present', 'array'],
            'events.*.client_id' => ['required', 'integer'],
            'events.*.event_type' => ['required', 'string', 'max:50'],
            'events.*.details' => ['nullable', 'string'],
            'events.*.recorded_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }
}