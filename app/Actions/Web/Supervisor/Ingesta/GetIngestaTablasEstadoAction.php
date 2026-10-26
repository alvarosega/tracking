<?php

declare(strict_types=1);

namespace App\Actions\Web\Supervisor\Ingesta;

use Illuminate\Support\Facades\DB;
use Throwable;

class GetIngestaTablasEstadoAction
{
    /**
     * Consulta el estado de salud, conteo y rango operativo de fechas de las tablas clave del sistema.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $tablasCriticas = ['PAN_RUTEO', 'FACT_VENTAS', 'FACT_PREVENTAS', 'FACT_VISITAS'];

        $tablasEsperadas = [
            ['entidad' => 'PAN_RUTEO', 'tabla' => 'pan_ruteo', 'nombre' => 'Plan de Ruteo'],
            ['entidad' => 'FACT_VENTAS', 'tabla' => 'fact_ventas', 'nombre' => 'Fact Ventas'],
            ['entidad' => 'FACT_PREVENTAS', 'tabla' => 'fact_preventas', 'nombre' => 'Fact Preventas'],
            ['entidad' => 'FACT_VISITAS', 'tabla' => 'fact_visitas', 'nombre' => 'Fact Visitas'],
            ['entidad' => 'INVENTARIO', 'tabla' => 'inv_stock_actual', 'nombre' => 'Stock Actual (Inventario)'],
            ['entidad' => 'INVENTARIO_SNAP', 'tabla' => 'inv_stock_snapshots', 'nombre' => 'Snapshots de Stock'],
            ['entidad' => 'DIM_RUTAS', 'tabla' => 'dim_rutas', 'nombre' => 'Dim Rutas (Estática)'],
            ['entidad' => 'FACT_METAS', 'tabla' => 'fact_metas', 'nombre' => 'Fact Metas (Estática)'],
            ['entidad' => 'DIM_CLIENTES', 'tabla' => 'dim_clientes', 'nombre' => 'Dim Clientes (Pendiente)'],
            ['entidad' => 'DIM_PRODUCTOS', 'tabla' => 'dim_productos', 'nombre' => 'Dim Productos (Pendiente)'],
            ['entidad' => 'DIM_CALENDARIO', 'tabla' => 'dim_calendario_mensual', 'nombre' => 'Calendario Mensual'],
            ['entidad' => 'CLIENTES_NO_ATENDIDOS', 'tabla' => 'ref_clientes_no_atendidos', 'nombre' => 'No Atendidos (Referencia)'],
        ];

        $tablasFechas = [
            'fact_ventas' => 'fecha_norm',
            'fact_preventas' => 'fecha_norm',
            'fact_visitas' => 'fecha_norm',
            'pan_ruteo' => 'fecha_servidor',
            'inv_stock_snapshots' => 'fecha_snapshot',
        ];

        $estados = [];
        $operativasCargadas = 0;

        try {
            $conn = DB::connection('supervisor');

            foreach ($tablasEsperadas as $t) {
                $count = 0;
                try {
                    $resCount = $conn->selectOne("SELECT COUNT(*) as total FROM `{$t['tabla']}`");
                    $count = (int)($resCount->total ?? 0);
                } catch (Throwable) {
                    $count = 0;
                }

                $rangoInicio = '-';
                $rangoFin = '-';
                $rangoDatos = '-';

                $colF = $tablasFechas[$t['tabla']] ?? null;
                if ($count > 0 && $colF) {
                    try {
                        $resF = $conn->selectOne("
                            SELECT MIN(`{$colF}`) as f_min, MAX(`{$colF}`) as f_max 
                            FROM `{$t['tabla']}` 
                            WHERE `{$colF}` IS NOT NULL AND `{$colF}` != '1900-01-01' AND `{$colF}` != ''
                        ");
                        if ($resF && $resF->f_min && $resF->f_max) {
                            $rangoInicio = substr((string)$resF->f_min, 0, 10);
                            $rangoFin = substr((string)$resF->f_max, 0, 10);
                            $rangoDatos = $rangoInicio !== $rangoFin ? "{$rangoInicio} al {$rangoFin}" : $rangoInicio;
                        }
                    } catch (Throwable) {
                        $rangoInicio = '-';
                        $rangoFin = '-';
                        $rangoDatos = '-';
                    }
                }

                $ultimaCarga = null;
                try {
                    $ultimaCarga = $conn->selectOne("
                        SELECT fecha_ingesta, archivo_original
                        FROM sys_cargas
                        WHERE tipo_entidad = ?
                        ORDER BY id DESC LIMIT 1
                    ", [$t['entidad']]);
                } catch (Throwable) {
                    $ultimaCarga = null;
                }

                $cargada = $count > 0;
                if ($cargada && in_array($t['entidad'], $tablasCriticas, true)) {
                    $operativasCargadas++;
                }

                $estados[] = [
                    'entidad' => $t['entidad'],
                    'nombre' => $t['nombre'],
                    'tabla' => $t['tabla'],
                    'cargada' => $cargada,
                    'total_filas' => $count,
                    'total_registros' => $count,
                    'rango_inicio' => $rangoInicio,
                    'rango_fin' => $rangoFin,
                    'rango_datos' => $rangoDatos,
                    'ultima_carga' => $ultimaCarga ? (string)$ultimaCarga->fecha_ingesta : '-',
                    'ultimo_archivo' => $ultimaCarga ? (string)$ultimaCarga->archivo_original : '-',
                    'archivo' => $ultimaCarga ? (string)$ultimaCarga->archivo_original : '-',
                ];
            }
        } catch (Throwable $e) {
            return [
                'sistema_listo' => false,
                'tablas_cargadas' => 0,
                'total_tablas' => count($tablasCriticas),
                'tablas' => [],
                'error' => $e->getMessage(),
            ];
        }

        return [
            'sistema_listo' => $operativasCargadas === count($tablasCriticas),
            'tablas_cargadas' => $operativasCargadas,
            'total_tablas' => count($tablasCriticas),
            'tablas' => $estados,
        ];
    }
}

