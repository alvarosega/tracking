<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VisitaController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:10240',
            'status' => 'required|string',
            'route' => 'nullable|string',
            'is_opportunity' => 'required',
            'client_id' => 'nullable|integer',
            'opportunity_client_name' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'comments' => 'nullable|string',
            'visited_at' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('visitas', 'public');
        }

        $isOpportunity = filter_var($request->input('is_opportunity'), FILTER_VALIDATE_BOOLEAN);

        // Hora del servidor al recibir la petición (formato literal para evitar conversiones de sesión)
        $serverReceiptTime = Carbon::now('America/La_Paz')->format('Y-m-d H:i:s');

        $visitaId = DB::table('visitas')->insertGetId([
            'client_id' => $isOpportunity ? null : $request->input('client_id'),
            'route' => $request->input('route', 'SIN_RUTA'),
            'status' => $request->input('status'),
            'is_opportunity' => $isOpportunity ? 1 : 0,
            'opportunity_client_name' => $isOpportunity ? $request->input('opportunity_client_name') : null,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy' => $request->input('accuracy', 0.0),
            'photo_path' => $photoPath,
            'comments' => $request->input('comments'),
            'visited_at' => $request->input('visited_at'),
            'user_id' => $request->user() ? $request->user()->id : null,
            'created_at' => $serverReceiptTime,
            'updated_at' => $serverReceiptTime,
        ]);

        return response()->json([
            'message' => 'Visita guardada exitosamente',
            'visita_id' => $visitaId,
            'photo_url' => $photoPath ? Storage::url($photoPath) : null
        ], 201);
    }
}