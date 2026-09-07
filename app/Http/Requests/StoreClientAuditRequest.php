<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:reference_clients,id_cliente'],
            'audit_status' => ['required', 'string', 'in:VALIDATED,NOT_FOUND,CLOSED_PERMANENT,DUPLICATE'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'comments' => ['nullable', 'string'],
            'audited_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png', 'max:5120'], // Máx 5MB por foto de respaldo
        ];
    }
}