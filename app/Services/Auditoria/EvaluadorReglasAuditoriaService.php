<?php

declare(strict_types=1);

namespace App\Services\Auditoria;

class EvaluadorReglasAuditoriaService
{
    /**
     * Calcula la distancia geodésica en metros entre dos coordenadas (Haversine).
     */
    public function calcularDistanciaHaversineMetros(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if ($lat1 === 0.0 || $lon1 === 0.0 || $lat2 === 0.0 || $lon2 === 0.0) {
            return 0.0;
        }
        if ($lat1 === $lat2 && $lon1 === $lon2) {
            return 0.0;
        }

        $rTierra = 6371000.0; // metros
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2.0) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLon / 2.0) ** 2);

        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return round($rTierra * $c, 1);
    }

    /**
     * Evalúa si la inactivación de un cliente fue legítima o riesgosa/arbitraria
     * basado en el comportamiento histórico de ventas de los últimos 5 meses.
     */
    public function evaluarInactivacion(array $reg): array
    {
        $ventas = $reg['historico_ventas'] ?? [];
        $total5m = (float) ($ventas['total_5m'] ?? 0.0);
        $promedioMensual = (float) ($ventas['promedio_mensual'] ?? 0.0);
        $meses = $ventas['meses'] ?? [];

        $ventasRecientes = count($meses) >= 2
            ? array_sum(array_map(fn($m) => (float) ($m['monto'] ?? 0), array_slice($meses, -2)))
            : $total5m;

        if ($total5m > 0 && ($ventasRecientes > 0 || $promedioMensual >= 300.0)) {
            $nivelRiesgo = 'ALTO';
            $codigo = 'INACTIVACION_ARBITRARIA';
            $mensaje = 'CRÍTICO · Cliente Comprador Activo Inactivado (Promedio: Bs ' . number_format($promedioMensual, 2, '.', ',') . '/mes)';
            $badgeCls = 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
        } elseif ($total5m > 0) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'INACTIVACION_CON_HISTORIA';
            $mensaje = 'OBSERVACIÓN · Cliente con Venta Histórica Previa (Total 5M: Bs ' . number_format($total5m, 2, '.', ',') . ')';
            $badgeCls = 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
        } else {
            $nivelRiesgo = 'BAJO';
            $codigo = 'INACTIVACION_JUSTIFICADA';
            $mensaje = 'JUSTIFICADO · 0 Bs en Compras en los últimos 5 meses';
            $badgeCls = 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
        }

        return [
            'nivel_riesgo' => $nivelRiesgo,
            'codigo_evaluacion' => $codigo,
            'mensaje_evaluacion' => $mensaje,
            'badge_class' => $badgeCls,
        ];
    }

    /**
     * Evalúa la legitimidad e impacto comercial del cambio de ruta.
     */
    public function evaluarCambioRuta(array $reg): array
    {
        $cAnt = strtoupper(trim((string) ($reg['canal_anterior'] ?? '')));
        $cNue = strtoupper(trim((string) ($reg['canal_nuevo'] ?? '')));
        $ventas = $reg['historico_ventas'] ?? [];
        $promedioMensual = (float) ($ventas['promedio_mensual'] ?? 0.0);

        $esIntercanal = ($cAnt !== $cNue) && $cAnt !== 'SIN CANAL' && $cNue !== 'SIN CANAL';

        if ($esIntercanal && $promedioMensual >= 1000.0) {
            $nivelRiesgo = 'ALTO';
            $codigo = 'INTERCANAL_CLIENTE_CLAVE';
            $mensaje = "ALTO RIESGO · Transferencia Intercanal de Cliente Clave ({$cAnt} → {$cNue})";
            $badgeCls = 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
        } elseif ($esIntercanal) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'CAMBIO_INTERCANAL';
            $mensaje = "ALERTA INTERCANAL · Cambio de Canal ({$cAnt} → {$cNue})";
            $badgeCls = 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
        } elseif ($promedioMensual >= 1000.0) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'TRASLADO_CLIENTE_CLAVE';
            $mensaje = 'CLIENTE CLAVE · Transferencia de Alto Volumen (Bs ' . number_format($promedioMensual, 2, '.', ',') . '/mes)';
            $badgeCls = 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]';
        } else {
            $nivelRiesgo = 'BAJO';
            $codigo = 'CAMBIO_REGULAR';
            $mensaje = 'REGULAR · Reasignación Intracanal de Ruta';
            $badgeCls = 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
        }

        return [
            'nivel_riesgo' => $nivelRiesgo,
            'codigo_evaluacion' => $codigo,
            'mensaje_evaluacion' => $mensaje,
            'badge_class' => $badgeCls,
            'es_intercanal' => $esIntercanal,
        ];
    }

    /**
     * Evalúa modificaciones en datos del cliente (georreferencia, nit, comercial).
     */
    public function evaluarModificacionDatos(array $reg): array
    {
        $geo = $reg['georreferencia'] ?? null;
        $distanciaM = 0.0;
        if ($geo) {
            $distanciaM = $this->calcularDistanciaHaversineMetros(
                (float) ($geo['lat_anterior'] ?? 0),
                (float) ($geo['lng_anterior'] ?? 0),
                (float) ($geo['lat_nueva'] ?? 0),
                (float) ($geo['lng_nueva'] ?? 0)
            );
        }

        $comercial = $reg['comercial'] ?? [];
        $tieneCambioNit = false;
        foreach ($comercial as $c) {
            if (in_array($c['campo'] ?? '', ['nit', 'nombre_factura'], true)) {
                $tieneCambioNit = true;
                break;
            }
        }

        if ($distanciaM >= 300.0) {
            $nivelRiesgo = 'ALTO';
            $codigo = 'DESPLAZAMIENTO_CRITICO';
            $mensaje = "ALTO RIESGO · Desplazamiento Geográfico de {$distanciaM} metros";
            $badgeCls = 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
        } elseif ($tieneCambioNit) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'CAMBIO_FISCAL';
            $mensaje = 'ALERTA COMERCIAL · Modificación de NIT / Razón Social';
            $badgeCls = 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
        } elseif ($distanciaM >= 50.0) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'DESPLAZAMIENTO_MODERADO';
            $mensaje = "OBSERVACIÓN · Ajuste de Ubicación ({$distanciaM} m)";
            $badgeCls = 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]';
        } else {
            $nivelRiesgo = 'BAJO';
            $codigo = 'ACTUALIZACION_MINOR';
            $mensaje = 'REGULAR · Actualización Estándar de Atributos';
            $badgeCls = 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
        }

        return [
            'nivel_riesgo' => $nivelRiesgo,
            'codigo_evaluacion' => $codigo,
            'mensaje_evaluacion' => $mensaje,
            'badge_class' => $badgeCls,
            'distancia_metros' => $distanciaM,
        ];
    }

    /**
     * Evalúa modificaciones de día de visita en la secuencia de ruteo.
     */
    public function evaluarCambioDia(array $reg): array
    {
        $ventas = $reg['historico_ventas'] ?? [];
        $total5m = (float) ($ventas['total_5m'] ?? 0.0);
        $dAnt = trim((string) ($reg['dia_anterior'] ?? ''));
        $dNue = trim((string) ($reg['dia_nuevo'] ?? ''));

        if ($total5m >= 1500.0 && $dAnt !== $dNue) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'CAMBIO_DIA_ALTO_VOLUMEN';
            $mensaje = "ALERTA · Cambio de Día en Cliente Clave ({$dAnt} → {$dNue})";
            $badgeCls = 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
        } elseif ($dAnt !== $dNue) {
            $nivelRiesgo = 'BAJO';
            $codigo = 'CAMBIO_DIA_ESTANDAR';
            $mensaje = "REGULAR · Ajuste de Frecuencia ({$dAnt} → {$dNue})";
            $badgeCls = 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
        } else {
            $nivelRiesgo = 'BAJO';
            $codigo = 'SIN_CAMBIO';
            $mensaje = 'REGULAR · Sin variación relevante';
            $badgeCls = 'bg-[#F5F5F7] text-[#6E6E73] border-[#E5E5EA]';
        }

        return [
            'nivel_riesgo' => $nivelRiesgo,
            'codigo_evaluacion' => $codigo,
            'mensaje_evaluacion' => $mensaje,
            'badge_class' => $badgeCls,
        ];
    }

    /**
     * Evalúa altas y reactivaciones (detección de falsas altas con ventas previas).
     */
    public function evaluarAlta(array $reg): array
    {
        $tipo = strtoupper(trim((string) ($reg['tipo_evento'] ?? '')));
        $ventas = $reg['historico_ventas'] ?? [];
        $total5m = (float) ($ventas['total_5m'] ?? 0.0);

        if ($tipo === 'NUEVO' && $total5m > 0) {
            $nivelRiesgo = 'ALTO';
            $codigo = 'FALSA_ALTA_CON_VENTAS';
            $mensaje = 'ALERTA CRÍTICA · Registrado como NUEVO pero tiene Ventas Históricas (Bs ' . number_format($total5m, 2, '.', ',') . ')';
            $badgeCls = 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
        } elseif ($tipo === 'REACTIVADO' && $total5m > 0) {
            $nivelRiesgo = 'MEDIO';
            $codigo = 'REACTIVADO_CON_VENTAS';
            $mensaje = 'REACTIVADO · Histórico Previo de Bs ' . number_format($total5m, 2, '.', ',') . ' en 5 meses';
            $badgeCls = 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]';
        } elseif ($tipo === 'NUEVO') {
            $nivelRiesgo = 'BAJO';
            $codigo = 'ALTA_NUEVA_GENUINA';
            $mensaje = 'NUEVO CLIENTE · Sin antecedentes previos de compra';
            $badgeCls = 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
        } else {
            $nivelRiesgo = 'BAJO';
            $codigo = 'REACTIVADO_SIN_VENTAS';
            $mensaje = 'REACTIVADO · Sin compras en 5 meses anteriores';
            $badgeCls = 'bg-[#F5F5F7] text-[#6E6E73] border-[#E5E5EA]';
        }

        return [
            'nivel_riesgo' => $nivelRiesgo,
            'codigo_evaluacion' => $codigo,
            'mensaje_evaluacion' => $mensaje,
            'badge_class' => $badgeCls,
        ];
    }
}

