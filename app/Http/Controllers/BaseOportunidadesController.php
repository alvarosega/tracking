<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BaseOportunidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseOportunidadesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Opcional: filtrar por territorio si se requiere segmentar
        $territory = $request->query('territory');

        $query = BaseOportunidad::query();

        if ($territory) {
            $query->where('territory', 'LIKE', "%{$territory}%");
        }

        $oportunidades = $query->select([
            'client_id',
            'trade_name',
            'client_name',
            'address',
            'phone',
            'territory',
            'latitude',
            'longitude',
            'status',
        ])->get();

        return response()->json($oportunidades, 200);
    }
}