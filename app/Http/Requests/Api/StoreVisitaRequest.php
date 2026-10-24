<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'route' => ['required', 'string', 'max:100'],
            'is_opportunity' => ['required'],
            'client_id' => ['nullable', 'integer'],
            'opportunity_client_name' => ['nullable', 'string', 'max:255'],
            'distance_to_client' => ['nullable', 'numeric'],
            'is_mock_location' => ['nullable'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'max:10240'],
            'comments' => ['nullable', 'string'],
            'visited_at' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }
}

