<?php

declare(strict_types=1);

namespace App\Actions\Api\PedidosRechazados;

use App\Models\PedidoRechazado;
use App\Models\User;
use App\Services\PedidosRechazados\RechazosVentanaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetPreventasPendientesAction
{
    /**
     * Calcula y devuelve las preventas tomadas pendientes de justificar frente a las ventas facturadas (entregas).
     *
     * Reglas del módulo dinámico:
     * 1. Elimina la restricción estática de facturación al día siguiente (X+1). Si existe en fact_ventas
     *    en cualquier fecha posterior (X+1, X+2, X+3...), se reconoce como entregada.
     * 2. Ventana dinámica: en lugar de evaluar únicamente el día D-2, evalúa un rango móvil de días
     *    (por defecto últimos 7 días hasta ayer D-1), permitiendo visibilidad completa.
     * 3. Excluye preventas completamente entregadas y preventas ya justificadas en pedidos_rechazados.
     * 4. Detecta rechazos parciales (cuando se facturó menos de lo pedido) y totales (facturado = 0).
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(
        User $user,
        ?string $fechaPreventaParam = null,
        ?string $fechaVentaParam = null,
        int|string|null $clienteId = null,
        ?string $ruta = null,
        int|string|null $diasParam = null,
        ?string $fechaDesdeParam = null,
        ?string $fechaHastaParam = null,
        bool $incluirHoy = false,
        int|string|null $nroPreventaParam = null,
        bool $incluirJustificados = false
    ): array {
        $now = Carbon::now('America/La_Paz');
        $service = app(RechazosVentanaService::class);
        $userRuta = $ruta ?: (string) ($user->ruta ?? '');
        $configModulo = $service->getConfig($userRuta);

        $fechasEvaluadas = [];
        $modoEvaluado = 'automatico';

        // 1. Determinar el rango o lista de fechas de preventa a evaluar:
        if (!empty($fechaPreventaParam)) {
            $parsed = Carbon::parse($fechaPreventaParam);
            if ($parsed->isSunday()) {
                $fechasEvaluadas = [
                    $parsed->copy()->subDays(2)->toDateString(), // Viernes
                    $parsed->copy()->subDay()->toDateString(),   // Sábado
                ];
            } else {
                $fechasEvaluadas = [$parsed->toDateString()];
            }
            $modoEvaluado = 'param_fecha_preventa';
        } elseif (!empty($fechaVentaParam)) {
            $parsedVenta = Carbon::parse($fechaVentaParam);
            if ($parsedVenta->isSunday()) {
                $fPrev = $parsedVenta->subDays(2)->toDateString(); // Viernes
            } elseif ($parsedVenta->isMonday()) {
                $fPrev = $parsedVenta->subDays(2)->toDateString(); // Sábado
            } else {
                $fPrev = $parsedVenta->subDay()->toDateString();
            }
            $fechasEvaluadas = [$fPrev];
            $modoEvaluado = 'param_fecha_venta';
        } elseif (!empty($fechaDesdeParam)) {
            $fInicio = $fechaDesdeParam;
            $fFin = !empty($fechaHastaParam) ? $fechaHastaParam : ($incluirHoy ? $now->toDateString() : $now->copy()->subDay()->toDateString());
            $cursor = Carbon::parse($fInicio);
            $cFin = Carbon::parse($fFin);
            while ($cursor->lte($cFin)) {
                $fechasEvaluadas[] = $cursor->toDateString();
                $cursor->addDay();
            }
            $modoEvaluado = 'param_rango_fechas';
        } else {
            // Leer configuración dinámica del módulo de supervisión (Jornadas, Horarios & Módulos)
            $evalInfo = $service->resolverFechasEvaluadas($configModulo, $now);
            $fechasEvaluadas = $evalInfo['fechas'];
            $modoEvaluado = $evalInfo['modo_aplicado'];
        }

        if (empty($fechasEvaluadas)) {
            $fechasEvaluadas = [$now->copy()->subDay()->toDateString()];
        }

        sort($fechasEvaluadas);
        $fechaInicio = reset($fechasEvaluadas);
        $fechaFin = end($fechasEvaluadas);

        Log::info('[RECHAZOS-ENGINE] Evaluando preventas pendientes', [
            'user_id' => $user->id,
            'username' => $user->username,
            'user_ruta' => $user->ruta,
            'role' => $user->role?->name,
            'modo_evaluado' => $modoEvaluado,
            'fechas_evaluadas' => $fechasEvaluadas,
            'fecha_min' => $fechaInicio,
            'fecha_max' => $fechaFin,
            'params' => [
                'fechaPreventaParam' => $fechaPreventaParam,
                'fechaVentaParam' => $fechaVentaParam,
                'clienteId' => $clienteId,
                'ruta' => $ruta,
                'diasParam' => $diasParam,
                'incluirHoy' => $incluirHoy,
            ],
        ]);

        try {
            $preventas = $service->evaluarPreventas(
                fechasEvaluadas: $fechasEvaluadas,
                rutaFiltro: !empty($ruta) ? $ruta : null,
                user: $user,
                incluirJustificados: $incluirJustificados
            );

            // Filtrar clienteId si vino en los parámetros
            if (!empty($clienteId)) {
                $targetCid = (int) $clienteId;
                $preventas = array_values(array_filter($preventas, fn ($p) => (int) ($p['cliente_id'] ?? 0) === $targetCid));
            }

            // Filtrar nroPreventaParam si vino en los parámetros
            if (!empty($nroPreventaParam)) {
                $targetNro = (int) $nroPreventaParam;
                $preventas = array_values(array_filter($preventas, fn ($p) => (int) ($p['nro_preventa'] ?? 0) === $targetNro));
            }

            Log::info('[RECHAZOS-ENGINE] Preventas evaluadas contra fact_ventas', [
                'total_preventas_en_rango' => count($preventas),
                'preventas_con_rechazo' => count($preventas),
                'nros_rechazados' => array_column($preventas, 'nro_preventa'),
            ]);

            return $preventas;
        } catch (\Throwable $e) {
            Log::error('Error consultando preventas pendientes en supervisor DB: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $user->id,
            ]);

            return [];
        }
    }
}
