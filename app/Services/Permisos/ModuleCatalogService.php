<?php

declare(strict_types=1);

namespace App\Services\Permisos;

class ModuleCatalogService
{
    /**
     * Retorna la lista canónica de módulos y submódulos disponibles para control de acceso.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getCatalog(): array
    {
        return [
            [
                'id' => 'inicio',
                'name' => 'Panel General (Inicio)',
                'description' => 'Métricas globales de la operación y resumen ejecutivo',
                'submodules' => [],
            ],
            [
                'id' => 'ruteo',
                'name' => 'Ruteo & Cobertura',
                'description' => 'Planes de visita, polígonos GIS y clientes cercanos',
                'submodules' => [
                    ['id' => 'visor', 'name' => 'Visor y Fronteras'],
                    ['id' => 'cercanos', 'name' => 'Clientes Cercanos'],
                ],
            ],
            [
                'id' => 'avance',
                'name' => 'Módulo Avance',
                'description' => 'Cumplimiento de metas, scoring y los KPIs estratégicos de ventas',
                'submodules' => [
                    ['id' => 'cumplimiento', 'name' => 'Cumplimiento KPIs'],
                    ['id' => 'historico-preventas', 'name' => 'Preventas Históricas'],
                    ['id' => 'cluster', 'name' => 'Cluster vs LY'],
                    ['id' => 'paretos', 'name' => 'Paretos & Scoring'],
                    ['id' => 'entregas', 'name' => 'Avance Entregas'],
                    ['id' => 'bpc', 'name' => 'Sell Out y BPC'],
                    ['id' => 'horarios', 'name' => 'Horario e Itinerario'],
                ],
            ],
            [
                'id' => 'preventas',
                'name' => 'Preventa & Cumplimiento',
                'description' => 'Cumplimiento Unilever y Productos Heatmap',
                'submodules' => [
                    ['id' => 'cumplimiento', 'name' => 'Cumplimiento Unilever'],
                    ['id' => 'productos', 'name' => 'Productos & Heatmap'],
                ],
            ],
            [
                'id' => 'auditoria',
                'name' => 'Auditoría de Ruteo & Preventa',
                'description' => 'Auditoría de novedades intra-día, cambios de ruta, bajas, modificaciones y reactivaciones',
                'submodules' => [
                    ['id' => 'preventas', 'name' => 'Preventas Intra-día'],
                    ['id' => 'inactivados', 'name' => 'Bajas e Inactivados'],
                    ['id' => 'cambios-ruta', 'name' => 'Cambios de Ruta'],
                    ['id' => 'modificacion-datos', 'name' => 'Modificación de Datos'],
                    ['id' => 'cambios-dia', 'name' => 'Cambios de Día'],
                    ['id' => 'altas', 'name' => 'Altas y Reactivaciones'],
                    ['id' => 'historico', 'name' => 'Histórico Resoluciones'],
                ],
            ],
            [
                'id' => 'tracking',
                'name' => 'Tracking en Vivo',
                'description' => 'Telemetría GPS, seguimiento en ruta y trayectorias semanales',
                'submodules' => [
                    ['id' => 'vivo', 'name' => 'Monitoreo en el Día'],
                    ['id' => 'historico', 'name' => 'Patrones Semanales'],
                ],
            ],
            [
                'id' => 'visitas',
                'name' => 'Auditoría Visitas',
                'description' => 'Evidencia fotográfica en PDV y avance de cobertura presencial',
                'submodules' => [
                    ['id' => 'galeria', 'name' => 'Galería Fotográfica'],
                    ['id' => 'avance', 'name' => 'Avance en Terreno'],
                ],
            ],
            [
                'id' => 'altas-ediciones',
                'name' => 'Altas & Ediciones',
                'description' => 'Auditoría y control de clientes creados o editados en campo',
                'submodules' => [
                    ['id' => 'listado', 'name' => 'Bandeja de Aprobación'],
                    ['id' => 'cercanos', 'name' => 'Clientes Cercanos (Radar 200m)'],
                ],
            ],
            [
                'id' => 'saneamiento',
                'name' => 'Saneamiento Padrón',
                'description' => 'Auditoría territorial, detección de intrusos, duplicados y calidad',
                'submodules' => [
                    ['id' => 'intrusos', 'name' => 'Fronteras e Intrusos'],
                    ['id' => 'duplicados', 'name' => 'Clientes Duplicados'],
                    ['id' => 'dias', 'name' => 'Días Desalineados'],
                    ['id' => 'calidad', 'name' => 'Calidad de Datos'],
                    ['id' => 'bd', 'name' => 'Matriz Saneamiento BD'],
                    ['id' => 'propuestas', 'name' => 'Historial de Propuestas'],
                ],
            ],
            [
                'id' => 'jornadas',
                'name' => 'Control de Jornadas',
                'description' => 'Aperturas, cierres remotos y control de turnos de preventa',
                'submodules' => [
                    ['id' => 'control', 'name' => 'Monitoreo de Jornadas'],
                    ['id' => 'cierre-masivo', 'name' => 'Cierre Remoto Masivo'],
                ],
            ],
            [
                'id' => 'horarios',
                'name' => 'Matriz de Horarios',
                'description' => 'Configuración semanal de turnos y franjas horarias por ruta',
                'submodules' => [
                    ['id' => 'matriz', 'name' => 'Configuración de Turnos'],
                    ['id' => 'activos', 'name' => 'Jornadas en Curso'],
                ],
            ],
            [
                'id' => 'modulos',
                'name' => 'Módulos Móviles & Ajustes',
                'description' => 'Control de acceso a funciones de la app Android y parámetros operativos',
                'submodules' => [
                    ['id' => 'matriz', 'name' => 'Matriz por Ruta'],
                    ['id' => 'global', 'name' => 'Ajustes Globales'],
                    ['id' => 'inventarios', 'name' => 'Stock PDV / Auditoría'],
                ],
            ],
            [
                'id' => 'ingesta',
                'name' => 'Centro de Ingesta',
                'description' => 'Carga, validación, auditoría y versionado de datos operativos',
                'submodules' => [
                    ['id' => 'dashboard', 'name' => 'Centro de Ingesta'],
                    ['id' => 'ventas', 'name' => 'Ventas Diarias'],
                    ['id' => 'preventas', 'name' => 'Preventas Intra-día'],
                    ['id' => 'ruteo', 'name' => 'Plan de Ruteo'],
                    ['id' => 'visitas', 'name' => 'Visitas Operativas'],
                    ['id' => 'inventario', 'name' => 'Stock Actual'],
                    ['id' => 'cancelados', 'name' => 'Preventas Canceladas'],
                    ['id' => 'snapshots', 'name' => 'Snapshots Ruteo'],
                ],
            ],
        ];
    }
}

