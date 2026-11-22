<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Api\Altas\GetHistorialAltasAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Saneamiento\StoreSaneamientoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AltaController extends Controller
{
    /**
     * Retorna los catálogos oficiales para el registro de altas en Android.
     * GET /api/altas/catalogos
     */
    public function catalogos(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'expedidos' => StoreSaneamientoRequest::EXPEDIDOS,
            'tipos_documento' => StoreSaneamientoRequest::TIPOS_DOCUMENTO,
            'tipos_nit' => StoreSaneamientoRequest::TIPOS_NIT,
            'tipos_negocio' => StoreSaneamientoRequest::TIPOS_NEGOCIO,
        ], 200);
    }

    /**
     * Consulta el historial de altas registradas estrictamente por el vendedor autenticado.
     * GET /api/altas/historial (y alias /api/altas, /api/altas/listar)
     */
    public function historial(Request $request, GetHistorialAltasAction $action): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
                'data' => [],
            ], 401);
        }

        try {
            $fechaDesde = $request->input('fecha_desde', $request->input('desde', $request->input('start_date', $request->input('fecha_inicio'))));
            $fechaHasta = $request->input('fecha_hasta', $request->input('hasta', $request->input('end_date', $request->input('fecha_fin'))));
            $horaDesde = $request->input('hora_desde');
            $horaHasta = $request->input('hora_hasta');

            if ($horaDesde && is_string($fechaDesde) && !str_contains($fechaDesde, ' ')) {
                $fechaDesde = trim($fechaDesde) . ' ' . trim((string) $horaDesde);
            }
            if ($horaHasta && is_string($fechaHasta) && !str_contains($fechaHasta, ' ')) {
                $fechaHasta = trim($fechaHasta) . ' ' . trim((string) $horaHasta);
            }

            $dias = $request->input('dias', $request->input('days', $request->input('ventana_dias')));
            $estado = $request->input('estado_revision', $request->input('estado', $request->input('status')));
            $search = $request->input('search', $request->input('q', $request->input('buscar')));

            $result = $action->execute(
                user: $user,
                fechaDesdeParam: is_string($fechaDesde) && $fechaDesde !== 'null' && $fechaDesde !== '' ? $fechaDesde : null,
                fechaHastaParam: is_string($fechaHasta) && $fechaHasta !== 'null' && $fechaHasta !== '' ? $fechaHasta : null,
                diasParam: is_numeric($dias) ? (int) $dias : null,
                estadoParam: is_string($estado) && $estado !== 'null' && $estado !== '' ? $estado : null,
                searchParam: is_string($search) && $search !== 'null' && $search !== '' ? $search : null
            );

            return response()->json([
                'success' => true,
                'total' => $result['total'],
                'filtros' => $result['filtros'],
                'data' => $result['data'],
                'altas' => $result['data'],
                'items' => $result['data'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error consultando historial de altas en API: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar historial de altas: ' . $e->getMessage(),
                'data' => [],
                'altas' => [],
            ], 500);
        }
    }
}
