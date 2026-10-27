<?php

declare(strict_types=1);

namespace App\Services\Permisos;

class ModuleCatalogService
{
    /**
     * Retorna la lista canónica de módulos y submódulos disponibles para control de acceso,
     * organizada fielmente según la arquitectura unificada del panel de supervisión.
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
                'id' => 'auditoria',
                'name' => 'Auditoría & Saneamiento',
                'description' => 'Auditoría de ruteo intra-día, visor territorial y saneamiento del padrón de clientes',
                'permission_modules' => ['auditoria', 'saneamiento'],
                'submodules' => [
                    // Grupo 1: Auditoría de Ruteo
                    ['id' => 'preventas', 'module_key' => 'auditoria', 'name' => 'Preventas Intra-día', 'group' => 'Auditoría de Ruteo'],
                    ['id' => 'inactivados', 'module_key' => 'auditoria', 'name' => 'Bajas e Inactivados', 'group' => 'Auditoría de Ruteo'],
                    ['id' => 'cambios-ruta', 'module_key' => 'auditoria', 'name' => 'Cambios de Ruta', 'group' => 'Auditoría de Ruteo'],
                    ['id' => 'modificacion-datos', 'module_key' => 'auditoria', 'name' => 'Modificación de Datos', 'group' => 'Auditoría de Ruteo'],
                    ['id' => 'cambios-dia', 'module_key' => 'auditoria', 'name' => 'Cambios de Día', 'group' => 'Auditoría de Ruteo'],
                    ['id' => 'altas', 'module_key' => 'auditoria', 'name' => 'Altas y Reactivaciones', 'group' => 'Auditoría de Ruteo'],
                    ['id' => 'historico', 'module_key' => 'auditoria', 'name' => 'Histórico Resoluciones', 'group' => 'Auditoría de Ruteo'],

                    // Grupo 2: Saneamiento Padrón
                    ['id' => 'visor', 'module_key' => 'saneamiento', 'name' => 'Visor y Fronteras', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'cercanos', 'module_key' => 'saneamiento', 'name' => 'Clientes Cercanos', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'intrusos', 'module_key' => 'saneamiento', 'name' => 'Fronteras & Intrusos', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'duplicados', 'module_key' => 'saneamiento', 'name' => 'Clientes Duplicados', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'dias', 'module_key' => 'saneamiento', 'name' => 'Días Desalineados', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'calidad', 'module_key' => 'saneamiento', 'name' => 'Calidad de Datos', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'bd', 'module_key' => 'saneamiento', 'name' => 'Matriz Saneamiento BD', 'group' => 'Saneamiento Padrón'],
                    ['id' => 'propuestas', 'module_key' => 'saneamiento', 'name' => 'Historial de Propuestas', 'group' => 'Saneamiento Padrón'],
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
                'id' => 'tracking',
                'name' => 'Tracking en Vivo & Visitas',
                'description' => 'Telemetría GPS en tiempo real, patrones y auditoría fotográfica de visitas',
                'permission_modules' => ['tracking', 'visitas'],
                'submodules' => [
                    // Grupo 1: Tracking GPS
                    ['id' => 'vivo', 'module_key' => 'tracking', 'name' => 'Monitoreo en el Día', 'group' => 'Tracking GPS'],
                    ['id' => 'historico', 'module_key' => 'tracking', 'name' => 'Patrones Semanales', 'group' => 'Tracking GPS'],

                    // Grupo 2: Auditoría Visitas
                    ['id' => 'galeria', 'module_key' => 'visitas', 'name' => 'Galería Fotográfica', 'group' => 'Auditoría Visitas'],
                    ['id' => 'avance', 'module_key' => 'visitas', 'name' => 'Avance en Terreno', 'group' => 'Auditoría Visitas'],
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
                'id' => 'jornadas',
                'name' => 'Jornadas, Horarios & Módulos',
                'description' => 'Monitoreo de jornadas, configuración de turnos y control de módulos móviles',
                'permission_modules' => ['jornadas', 'horarios', 'modulos'],
                'submodules' => [
                    // Grupo 1: Control de Jornadas
                    ['id' => 'control', 'module_key' => 'jornadas', 'name' => 'Monitoreo de Jornadas', 'group' => 'Control de Jornadas'],
                    ['id' => 'cierre-masivo', 'module_key' => 'jornadas', 'name' => 'Cierre Remoto Masivo', 'group' => 'Control de Jornadas'],

                    // Grupo 2: Matriz de Horarios
                    ['id' => 'matriz', 'module_key' => 'horarios', 'name' => 'Configuración de Turnos', 'group' => 'Matriz de Horarios'],
                    ['id' => 'activos', 'module_key' => 'horarios', 'name' => 'Jornadas en Curso', 'group' => 'Matriz de Horarios'],

                    // Grupo 3: Módulos & Ajustes
                    ['id' => 'matriz', 'module_key' => 'modulos', 'name' => 'Matriz por Ruta', 'group' => 'Módulos & Ajustes'],
                    ['id' => 'global', 'module_key' => 'modulos', 'name' => 'Ajustes Globales', 'group' => 'Módulos & Ajustes'],
                    ['id' => 'inventarios', 'module_key' => 'modulos', 'name' => 'Stock PDV / Auditoría', 'group' => 'Módulos & Ajustes'],
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
