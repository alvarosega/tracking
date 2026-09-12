<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VisitaController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $request->boolean('is_opportunity') === false),
                'exists:plan_ruteo,client_id'
            ],
            'route' => 'required|string|max:100',
            'status' => 'required|in:PREVENTA,SIN_DINERO,TIENDA_CERRADA,AUSENTE',
            'is_opportunity' => 'required|boolean',
            'opportunity_client_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->boolean('is_opportunity') === true)
            ],
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'required|numeric',
            'comments' => 'nullable|string',
            'visited_at' => 'required|date',
            'photo' => 'required|image|mimes:jpeg,jpg,png|max:10240',
        ]);

        // Guardar la fotografía adjunta en el disco público
        $photoPath = $request->file('photo')->store('visitas_evidence', 'public');

        $visita = Visita::create([
            'user_id' => $request->user()->id,
            'client_id' => $request->boolean('is_opportunity') ? null : $validated['client_id'],
            'route' => $validated['route'],
            'status' => $validated['status'],
            'is_opportunity' => $request->boolean('is_opportunity'),
            'opportunity_client_name' => $request->boolean('is_opportunity') ? $validated['opportunity_client_name'] : null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'],
            'photo_path' => $photoPath,
            'comments' => $validated['comments'] ?? null,
            'visited_at' => $validated['visited_at'],
        ]);

        return response()->json([
            'message' => 'Visita registrada correctamente en el servidor.',
            'data' => $visita
        ], 201);
    }
}