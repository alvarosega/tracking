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
        },
        {
          id: 'preventas',
          name: 'Auditoría Preventas',
          route: 'supervisor.ruteo.preventas',
          component: 'Supervisor/Ruteo/Preventas',
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
    }
  ],

  // Accesos Rápidos
  quickLinks: [
    { label: 'Tracking Activo', route: 'supervisor.tracking.index' },
    { label: 'Matriz Horarios', route: 'supervisor.horarios.index' },
    { label: 'Cierre de Turnos', route: 'supervisor.jornadas.index' }
  ]
};

