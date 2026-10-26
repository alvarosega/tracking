<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Web\Supervisor\Avance\GetAvanceClienteDetalleFechasAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceClientesRutaAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceCumplimientoDataAction;
use App\Actions\Web\Supervisor\Avance\GetAvanceInitDataAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorAvanceController extends Controller
{
    /**
     * Muestra la vista principal de Avance > Cumplimiento (Resumen Multi-KPI y Detalle Monográfico).
     */
    public function cumplimiento(GetAvanceInitDataAction $action): Response
    {
        $initData = $action->execute();

        return Inertia::render('Supervisor/Avance/Cumplimiento', [
            'catalogo_rutas' => $initData['catalogo_rutas'],
            'canales' => $initData['canales'],
            'meses' => $initData['meses'],
            'fecha_inicio_default' => $initData['fecha_inicio'],
            'fecha_corte_default' => $initData['fecha_corte'],
            'mes_default' => $initData['mes'],
            'dias_calendario' => $initData['dias_calendario'],
        ]);
    }

    /**
     * Devuelve la matriz de datos de cumplimiento (Resumen Multi-KPI o Detalle Monográfico por KPI).
     */
    public function cumplimientoData(Request $request, GetAvanceCumplimientoDataAction $action): JsonResponse
    {
        $vista = (string) $request->input('vista', 'resumen');
        $kpi = (string) $request->input('kpi', 'vol_general');
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;
        $canales = (array) $request->input('canales', []);
        $rutas = (array) $request->input('rutas', []);
        $modoTemporal = (string) $request->input('modo_temporal', 'acumulado');

        $data = $action->execute(
            vista: $vista,
            kpiClave: $kpi,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte,
            canales: $canales,
            rutas: $rutas,
            modoTemporal: $modoTemporal
        );

        return response()->json($data);
    }

    /**
     * Devuelve los clientes individuales y ventas diarias acumuladas de una ruta para el acordeón.
     */
    public function clientesRuta(Request $request, GetAvanceClientesRutaAction $action): JsonResponse
    {
        $ruta = (string) $request->input('ruta');
        $kpi = (string) $request->input('kpi', 'vol_general');
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->execute(
            ruta: $ruta,
            kpiClave: $kpi,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte
        );

        return response()->json($data);
    }

    /**
     * Devuelve el historial de compras y facturas de un cliente para el modal de auditoría.
     */
    public function clienteDetalleFechas(Request $request, GetAvanceClienteDetalleFechasAction $action): JsonResponse
    {
        $clienteId = (int) $request->input('cliente_id');
        $ruta = (string) $request->input('ruta');
        $kpi = (string) $request->input('kpi', 'vol_general');
        $fechaInicio = $request->input('fecha_inicio') ? (string) $request->input('fecha_inicio') : null;
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->execute(
            clienteId: $clienteId,
            ruta: $ruta,
            fechaInicio: $fechaInicio,
            fechaCorte: $fechaCorte,
            kpiClave: $kpi
        );

        return response()->json($data);
    }

    /**
     * Devuelve el cálculo de días hábiles transcurridos y restantes para el período seleccionado.
     */
    public function diasHabiles(Request $request, GetAvanceInitDataAction $action): JsonResponse
    {
        $mes = (string) ($request->input('mes') ?: date('Y-m'));
        $fechaCorte = $request->input('fecha_corte') ? (string) $request->input('fecha_corte') : null;

        $data = $action->calcularDiasHabiles($mes, $fechaCorte);

        return response()->json($data);
    }
}

