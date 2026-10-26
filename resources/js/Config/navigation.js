/**
 * Configuración centralizada de navegación para el Panel de Supervisión.
 * Define la estructura de menú, rutas, iconos y submódulos.
 */
export const navigationConfig = {
  appName: 'SUPERVISIÓN',
  badge: 'GPS PRO',
  defaultRoute: 'supervisor.index',

  // Menú Principal de la Barra Lateral
  mainMenu: [
    {
      id: 'inicio',
      name: 'Panel General',
      shortName: 'Inicio',
      route: 'supervisor.index',
      componentPrefix: 'Supervisor/Index',
      icon: 'HomeIcon',
      description: 'Métricas clave y estado general'
    },
    {
      id: 'ruteo',
      name: 'Ruteo & Cobertura',
      shortName: 'Ruteo',
      route: 'supervisor.ruteo.index',
      componentPrefix: 'Supervisor/Ruteo',
      icon: 'MapIcon',
      badge: 'GIS',
      description: 'Planes de visita, polígonos y clientes cercanos',
      submodules: [
        {
          id: 'visor',
          name: 'Visor y Fronteras',
          route: 'supervisor.ruteo.index',
          component: 'Supervisor/Ruteo/Index',
          icon: 'MapPinIcon'
        },
        {
          id: 'cercanos',
          name: 'Clientes Cercanos',
          route: 'supervisor.ruteo.cercanos',
          component: 'Supervisor/Ruteo/Cercanos',
          icon: 'RadarIcon'
        }
      ]
    },
    {
      id: 'avance',
      name: 'Módulo Avance',
      shortName: 'Avance',
      route: 'supervisor.avance.cumplimiento',
      componentPrefix: 'Supervisor/Avance',
      icon: 'TrendingUpIcon',
      badge: 'MTD',
      badgeColor: 'blue',
      description: 'Cumplimiento de Metas y los 6 KPIs Estratégicos',
      submodules: [
        {
          id: 'cumplimiento',
          name: 'Cumplimiento KPIs',
          route: 'supervisor.avance.cumplimiento',
          component: 'Supervisor/Avance/Cumplimiento',
          icon: 'ChartBarIcon'
        },
        {
          id: 'historico-preventas',
          name: 'Preventas Históricas',
          route: 'supervisor.avance.historico-preventas',
          component: 'Supervisor/Avance/HistoricoPreventas',
          icon: 'ClockIcon'
        },
        {
          id: 'cluster',
          name: 'Cluster vs LY',
          route: 'supervisor.avance.cluster',
          component: 'Supervisor/Avance/Cluster',
          icon: 'CubeTransparentIcon'
        },
        {
          id: 'paretos',
          name: 'Paretos & Scoring',
          route: 'supervisor.avance.paretos',
          component: 'Supervisor/Avance/Paretos',
          icon: 'ChartPieIcon'
        },
        {
          id: 'entregas',
          name: 'Avance Entregas',
          route: 'supervisor.avance.entregas',
          component: 'Supervisor/Avance/Entregas',
          icon: 'TruckIcon'
        },
        {
          id: 'bpc',
          name: 'Sell Out y BPC',
          route: 'supervisor.avance.bpc',
          component: 'Supervisor/Avance/Bpc',
          icon: 'SparklesIcon'
        },
        {
          id: 'horarios',
          name: 'Horarios e Itinerario',
          route: 'supervisor.avance.horarios',
          component: 'Supervisor/Avance/Horarios',
          icon: 'CalendarIcon'
        }
      ]
    },
    {
      id: 'preventas',
      name: 'Módulo Preventa',
      shortName: 'Preventa',
      route: 'supervisor.preventas.cumplimiento',
      componentPrefix: 'Supervisor/Preventas',
      icon: 'ChartBarIcon',
      badge: '100 PTS',
      badgeColor: 'amber',
      description: 'Pizarra Unilever, Preventa Diaria, Productos y Auditoría',
      submodules: [
        {
          id: 'cumplimiento',
          name: 'Cumplimiento Unilever',
          route: 'supervisor.preventas.cumplimiento',
          component: 'Supervisor/Preventas/Cumplimiento',
          icon: 'ChartBarIcon'
        },
        {
          id: 'diaria',
          name: 'Preventa Diaria',
          route: 'supervisor.preventas.diaria',
          component: 'Supervisor/Preventas/Diaria',
          icon: 'MapPinIcon'
        },
        {
          id: 'productos',
          name: 'Productos & Heatmap',
          route: 'supervisor.preventas.productos',
          component: 'Supervisor/Preventas/Productos',
          icon: 'TagIcon'
        },
        {
          id: 'auditoria',
          name: 'Auditoría',
          route: 'supervisor.preventas.auditoria',
          component: 'Supervisor/Preventas/Auditoria',
          icon: 'ShieldCheckIcon'
        },
        {
          id: 'comparativos',
          name: 'Comparativos',
          route: 'supervisor.preventas.comparativos',
          component: 'Supervisor/Preventas/Comparativos',
          icon: 'ScaleIcon'
        },
        {
          id: 'mapa',
          name: 'Auditoría en Terreno',
          route: 'supervisor.preventas.index',
          component: 'Supervisor/Preventas/Index',
          icon: 'ClipboardCheckIcon'
        }
      ]
    },
    {
      id: 'tracking',
      name: 'Tracking en Vivo',
      shortName: 'Tracking',
      route: 'supervisor.tracking.index',
      componentPrefix: 'Supervisor/Tracking',
      icon: 'CrosshairIcon',
      badge: 'LIVE',
      badgeColor: 'emerald',
      description: 'Telemetría, rutas activas y trayectorias históricas',
      submodules: [
        {
          id: 'vivo',
          name: 'Monitoreo en el Día',
          route: 'supervisor.tracking.index',
          component: 'Supervisor/Tracking/Index',
          icon: 'ActivityIcon'
        },
        {
          id: 'historico',
          name: 'Patrones Semanales',
          route: 'supervisor.tracking.historico',
          component: 'Supervisor/Tracking/Historico',
          icon: 'HistoryIcon'
        }
      ]
    },
    {
      id: 'visitas',
      name: 'Auditoría Visitas',
      shortName: 'Visitas',
      route: 'supervisor.visitas.index',
      componentPrefix: 'Supervisor/Visitas',
      icon: 'CameraIcon',
      description: 'Evidencia fotográfica y avance de cobertura',
      submodules: [
        {
          id: 'galeria',
          name: 'Galería Fotográfica',
          route: 'supervisor.visitas.index',
          component: 'Supervisor/Visitas/Index',
          icon: 'PhotoIcon'
        },
        {
          id: 'avance',
          name: 'Avance en Terreno',
          route: 'supervisor.visitas.avance',
          component: 'Supervisor/Visitas/Avance',
          icon: 'ChartBarIcon'
        }
      ]
    },
    {
      id: 'altas-ediciones',
      name: 'Altas & Ediciones',
      shortName: 'Altas / Modif.',
      route: 'supervisor.altas-ediciones.index',
      componentPrefix: 'Supervisor/AltasEdiciones',
      icon: 'UserPlusIcon',
      description: 'Control de clientes creados o editados en ruta'
    },
    {
      id: 'saneamiento',
      name: 'Saneamiento Padrón',
      shortName: 'Saneamiento',
      route: 'supervisor.saneamiento.intrusos',
      componentPrefix: 'Supervisor/Saneamiento',
      icon: 'ShieldCheckIcon',
      badge: 'GIS',
      badgeColor: 'blue',
      description: 'Auditoría territorial, detección de intrusos, duplicados y calidad',
      submodules: [
        {
          id: 'intrusos',
          name: 'Fronteras e Intrusos',
          route: 'supervisor.saneamiento.intrusos',
          component: 'Supervisor/Saneamiento/Intrusos',
          icon: 'MapPinIcon'
        },
        {
          id: 'duplicados',
          name: 'Clientes Duplicados',
          route: 'supervisor.saneamiento.duplicados',
          component: 'Supervisor/Saneamiento/Duplicados',
          icon: 'UsersIcon'
        },
        {
          id: 'dias',
          name: 'Días Desalineados',
          route: 'supervisor.saneamiento.dias',
          component: 'Supervisor/Saneamiento/Dias',
          icon: 'CalendarIcon'
        },
        {
          id: 'calidad',
          name: 'Calidad de Datos',
          route: 'supervisor.saneamiento.calidad',
          component: 'Supervisor/Saneamiento/Calidad',
          icon: 'CheckBadgeIcon'
        },
        {
          id: 'bd',
          name: 'Matriz Saneamiento BD',
          route: 'supervisor.saneamiento.bd',
          component: 'Supervisor/Saneamiento/Bd',
          icon: 'CircleStackIcon'
        },
        {
          id: 'propuestas',
          name: 'Historial de Propuestas',
          route: 'supervisor.saneamiento.propuestas',
          component: 'Supervisor/Saneamiento/Propuestas',
          icon: 'ClipboardDocumentCheckIcon'
        }
      ]
    },
    {
      id: 'jornadas',
      name: 'Control de Jornadas',
      shortName: 'Jornadas',
      route: 'supervisor.jornadas.index',
      componentPrefix: 'Supervisor/Jornadas',
      icon: 'ClockIcon',
      description: 'Aperturas, cierres remotos y control de turnos'
    },
    {
      id: 'horarios',
      name: 'Matriz de Horarios',
      shortName: 'Horarios',
      route: 'supervisor.horarios.index',
      componentPrefix: 'Supervisor/Horarios',
      icon: 'CalendarIcon',
      description: 'Configuración semanal de turnos por ruta'
    },
    {
      id: 'modulos',
      name: 'Módulos & Ajustes',
      shortName: 'Módulos',
      route: 'supervisor.modulos.index',
      componentPrefix: 'Supervisor/Modulos',
      icon: 'AdjustmentsHorizontalIcon',
      badge: 'APP',
      badgeColor: 'amber',
      description: 'Control de acceso a módulos móviles y parámetros'
    },
    {
      id: 'ingesta',
      name: 'Centro de Ingesta',
      shortName: 'Ingesta',
      route: 'supervisor.ingesta.index',
      componentPrefix: 'Supervisor/Ingesta',
      icon: 'ArrowUpTrayIcon',
      badge: 'ETL',
      badgeColor: 'emerald',
      description: 'Carga, validación y versionado de datos operativos',
      submodules: [
        {
          id: 'dashboard',
          name: 'Centro de Ingesta',
          route: 'supervisor.ingesta.index',
          component: 'Supervisor/Ingesta/Index',
          icon: 'CircleStackIcon'
        },
        {
          id: 'ventas',
          name: 'Ventas Diarias',
          route: 'supervisor.ingesta.ventas',
          component: 'Supervisor/Ingesta/Upload',
          icon: 'ShoppingBagIcon'
        },
        {
          id: 'preventas',
          name: 'Preventas Intra-día',
          route: 'supervisor.ingesta.preventas',
          component: 'Supervisor/Ingesta/Upload',
          icon: 'DocumentTextIcon'
        },
        {
          id: 'ruteo',
          name: 'Plan de Ruteo',
          route: 'supervisor.ingesta.ruteo',
          component: 'Supervisor/Ingesta/Upload',
          icon: 'MapIcon'
        },
        {
          id: 'visitas',
          name: 'Visitas Operativas',
          route: 'supervisor.ingesta.visitas',
          component: 'Supervisor/Ingesta/Upload',
          icon: 'CameraIcon'
        },
        {
          id: 'inventario',
          name: 'Stock Actual',
          route: 'supervisor.ingesta.inventario',
          component: 'Supervisor/Ingesta/Upload',
          icon: 'CubeIcon'
        },
        {
          id: 'cancelados',
          name: 'Preventas Canceladas',
          route: 'supervisor.ingesta.cancelados',
          component: 'Supervisor/Ingesta/Cancelados',
          icon: 'ExclamationTriangleIcon'
        },
        {
          id: 'snapshots',
          name: 'Snapshots Ruteo',
          route: 'supervisor.ingesta.snapshots',
          component: 'Supervisor/Ingesta/Snapshots',
          icon: 'ArchiveBoxIcon'
        }
      ]
    },
    {
      id: 'admin-permisos',
      name: 'Permisos Supervisores',
      shortName: 'Permisos',
      route: 'supervisor.admin.permisos.index',
      componentPrefix: 'Supervisor/Admin',
      icon: 'KeyIcon',
      badge: 'ADMIN',
      badgeColor: 'purple',
      description: 'Gestión y control de acceso a módulos por supervisor',
      superAdminOnly: true
    }
  ],

  // Accesos Rápidos
  quickLinks: [
    { label: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
    { label: 'Tracking Activo', route: 'supervisor.tracking.index' },
    { label: 'Matriz Horarios', route: 'supervisor.horarios.index' },
    { label: 'Cierre de Turnos', route: 'supervisor.jornadas.index' }
  ]
};

export const preventasSubTabs = [
  { id: 'cumplimiento', name: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
  { id: 'diaria', name: 'Preventa Diaria', route: 'supervisor.preventas.diaria' },
  { id: 'productos', name: 'Productos & Heatmap', route: 'supervisor.preventas.productos' },
  { id: 'auditoria', name: 'Auditoría', route: 'supervisor.preventas.auditoria' },
  { id: 'comparativos', name: 'Comparativos', route: 'supervisor.preventas.comparativos' },
  { id: 'mapa', name: 'Auditoría en Mapa', route: 'supervisor.preventas.index' }
];

export const saneamientoSubTabs = [
  { id: 'intrusos', name: 'Fronteras e Intrusos', route: 'supervisor.saneamiento.intrusos' },
  { id: 'duplicados', name: 'Clientes Duplicados', route: 'supervisor.saneamiento.duplicados' },
  { id: 'dias', name: 'Días Desalineados', route: 'supervisor.saneamiento.dias' },
  { id: 'calidad', name: 'Calidad de Datos', route: 'supervisor.saneamiento.calidad' },
  { id: 'bd', name: 'Matriz Saneamiento BD', route: 'supervisor.saneamiento.bd' },
  { id: 'propuestas', name: 'Historial de Propuestas', route: 'supervisor.saneamiento.propuestas' }
];

export const ingestaSubTabs = [
  { id: 'dashboard', name: 'Centro de Ingesta', route: 'supervisor.ingesta.index' },
  { id: 'ventas', name: 'Ventas Diarias', route: 'supervisor.ingesta.ventas' },
  { id: 'preventas', name: 'Preventas Intra-día', route: 'supervisor.ingesta.preventas' },
  { id: 'ruteo', name: 'Plan de Ruteo', route: 'supervisor.ingesta.ruteo' },
  { id: 'visitas', name: 'Visitas Operativas', route: 'supervisor.ingesta.visitas' },
  { id: 'inventario', name: 'Stock Actual', route: 'supervisor.ingesta.inventario' },
  { id: 'cancelados', name: 'Cancelados', route: 'supervisor.ingesta.cancelados' },
  { id: 'snapshots', name: 'Snapshots Ruteo', route: 'supervisor.ingesta.snapshots' }
];

