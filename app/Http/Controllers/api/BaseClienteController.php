<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BaseCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $route = $user->username; 
        $day = $request->query('day');

        $query = BaseCliente::query()
            ->where('route', $route);

        if ($day) {
            $query->where('day', $day);
        }

        $clients = $query->select([
            'client_id',
            'client_name',
            'address',
            'reference',
            'latitude',
            'longitude',
            'status',
            'route',
            'day',
        ])->get();

        return response()->json($clients, 200);
    }
}