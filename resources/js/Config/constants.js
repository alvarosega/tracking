/**
 * Constantes y parámetros operativos globales.
 */
export const constantsConfig = {
  // Intervalos de actualización (milisegundos)
  polling: {
    liveTracking: 15000,    // 15 segundos
    activeWorkdays: 30000,  // 30 segundos
    clockUpdate: 1000       // 1 segundo
  },

  // Días de la semana para matrices y filtros
  diasSemana: [
    { id: 1, name: 'Lunes', short: 'LUN', num: 1 },
    { id: 2, name: 'Martes', short: 'MAR', num: 2 },
    { id: 3, name: 'Miércoles', short: 'MIÉ', num: 3 },
    { id: 4, name: 'Jueves', short: 'JUE', num: 4 },
    { id: 5, name: 'Viernes', short: 'VIE', num: 5 },
    { id: 6, name: 'Sábado', short: 'SÁB', num: 6 },
    { id: 7, name: 'Domingo', short: 'DOM', num: 7 }
  ],

  // Formateadores de fecha y hora (Localización Bolivia)
  locale: 'es-BO',
  timeZone: 'America/La_Paz',

  // Configuración de paginación y tablas
  pagination: {
    defaultPerPage: 25,
    pageSizeOptions: [10, 25, 50, 100]
  }
};

