/**
 * Gestor centralizado y estandarizado de colores para Canales, Rutas y Días.
 * Proporciona coherencia visual absoluta en todo el sistema.
 */

// Paleta fija y distintiva para Canales Comerciales
export const CANAL_COLORS = {
  // Canales Reales de Producción (DB u967339252_supervisor)
  'TDB': {
    color: '#0D9488', // Teal 600
    bg: '#CCFBF1',
    text: '#115E59',
    border: '#99F6E4',
    name: 'Tiendas de Barrio (TDB)'
  },
  'MAY': {
    color: '#7C3AED', // Violet 600
    bg: '#EDE9FE',
    text: '#5B21B6',
    border: '#DDD6FE',
    name: 'Mayoristas (MAY)'
  },
  'MZO': {
    color: '#0284C7', // Sky 600
    bg: '#E0F2FE',
    text: '#075985',
    border: '#BAE6FD',
    name: 'Microzonas (MZO)'
  },
  'PROV': {
    color: '#D97706', // Amber 600
    bg: '#FEF3C7',
    text: '#92400E',
    border: '#FDE68A',
    name: 'Provincias (PROV)'
  },
  'PRT': {
    color: '#E11D48', // Rose 600
    bg: '#FFE4E6',
    text: '#9F1239',
    border: '#FECDD3',
    name: 'Preventa (PRT)'
  },

  // Alias y Canales Tradicionales
  'TRADICIONAL': {
    color: '#0D9488',
    bg: '#CCFBF1',
    text: '#115E59',
    border: '#99F6E4',
    name: 'Tradicional'
  },
  'SUPERMERCADOS': {
    color: '#0284C7',
    bg: '#E0F2FE',
    text: '#075985',
    border: '#BAE6FD',
    name: 'Supermercados'
  },
  'MAYORISTAS': {
    color: '#7C3AED',
    bg: '#EDE9FE',
    text: '#5B21B6',
    border: '#DDD6FE',
    name: 'Mayoristas'
  },
  'HORECA': {
    color: '#E11D48',
    bg: '#FFE4E6',
    text: '#9F1239',
    border: '#FECDD3',
    name: 'Horeca'
  },
  'INSTITUCIONAL': {
    color: '#D97706',
    bg: '#FEF3C7',
    text: '#92400E',
    border: '#FDE68A',
    name: 'Institucional'
  },
  'AUTOVENTA': {
    color: '#2563EB',
    bg: '#DBEAFE',
    text: '#1E40AF',
    border: '#BFDBFE',
    name: 'Autoventa'
  },
  'DEFAULT': {
    color: '#475569',
    bg: '#F1F5F9',
    text: '#334155',
    border: '#E2E8F0',
    name: 'General'
  }
};

// Días de la Semana con asignación cromática fija
export const DIAS_SEMANA_CONFIG = [
  { key: 'LUNES', corto: 'Lun', label: 'Lunes', color: '#0284C7', bg: '#E0F2FE', text: '#0369A1' },
  { key: 'MARTES', corto: 'Mar', label: 'Martes', color: '#059669', bg: '#D1FAE5', text: '#047857' },
  { key: 'MIERCOLES', corto: 'Mié', label: 'Miércoles', color: '#D97706', bg: '#FEF3C7', text: '#B45309' },
  { key: 'JUEVES', corto: 'Jue', label: 'Jueves', color: '#7C3AED', bg: '#EDE9FE', text: '#6D28D9' },
  { key: 'VIERNES', corto: 'Vie', label: 'Viernes', color: '#DB2777', bg: '#FCE7F3', text: '#BE185D' },
  { key: 'SABADO', corto: 'Sáb', label: 'Sábado', color: '#4F46E5', bg: '#E0E7FF', text: '#4338CA' },
  { key: 'DOMINGO', corto: 'Dom', label: 'Domingo', color: '#64748B', bg: '#F1F5F9', text: '#475569' },
];

// Paleta Armonizada para Rutas Dinámicas (Alta distinción y elegancia)
export const RUTA_PALETTE = [
  '#0F172A', // Slate 900
  '#0284C7', // Sky 600
  '#059669', // Emerald 600
  '#7C3AED', // Violet 600
  '#D97706', // Amber 600
  '#DC2626', // Red 600
  '#0D9488', // Teal 600
  '#DB2777', // Pink 600
  '#4F46E5', // Indigo 600
  '#CA8A04', // Yellow 600
  '#2563EB', // Blue 600
  '#9333EA', // Purple 600
];

const rutaColorCache = new Map();

/**
 * Retorna el color principal de un canal comercial.
 */
export function getCanalColor(canal) {
  if (!canal) return CANAL_COLORS.DEFAULT.color;
  const key = String(canal).toUpperCase().trim();
  return (CANAL_COLORS[key] || CANAL_COLORS.DEFAULT).color;
}

/**
 * Retorna el objeto de estilos de badge para un canal.
 */
export function getCanalBadge(canal) {
  if (!canal) return CANAL_COLORS.DEFAULT;
  const key = String(canal).toUpperCase().trim();
  return CANAL_COLORS[key] || CANAL_COLORS.DEFAULT;
}

/**
 * Retorna el color asignado a una ruta, manejando valores nulos con gracia.
 */
export function getRutaColor(ruta) {
  if (!ruta || String(ruta).trim() === '' || String(ruta).toLowerCase() === 'null') {
    return '#64748B'; // Slate 500 para Sin Ruta
  }
  const cleanRuta = String(ruta).trim();
  if (!rutaColorCache.has(cleanRuta)) {
    // Generador de índice determinista por hash del nombre de la ruta
    let hash = 0;
    for (let i = 0; i < cleanRuta.length; i++) {
      hash = cleanRuta.charCodeAt(i) + ((hash << 5) - hash);
    }
    const colorIndex = Math.abs(hash) % RUTA_PALETTE.length;
    rutaColorCache.set(cleanRuta, RUTA_PALETTE[colorIndex]);
  }
  return rutaColorCache.get(cleanRuta);
}

/**
 * Retorna el nombre formateado de la ruta (protegiendo null).
 */
export function formatRuta(ruta) {
  if (!ruta || String(ruta).trim() === '' || String(ruta).toLowerCase() === 'null') {
    return 'Sin Ruta';
  }
  return String(ruta).trim();
}

/**
 * Retorna el color de un día de la semana.
 */
export function getDiaColor(dia) {
  if (!dia || String(dia).toLowerCase() === 'null') return '#94A3B8';
  const cleanDia = String(dia).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim();
  const found = DIAS_SEMANA_CONFIG.find(d => d.key === cleanDia);
  return found ? found.color : '#94A3B8';
}

