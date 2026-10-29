/**
 * Gestor centralizado y estandarizado de colores para Canales, Rutas y Días.
 * Proporciona coherencia visual y máxima diferenciación perceptual en todo el sistema.
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

// Días de la Semana con asignación cromática fija y bien definida
export const DIAS_SEMANA_CONFIG = [
  { key: 'LUNES', corto: 'Lun', label: 'Lunes', color: '#0284C7', bg: '#E0F2FE', text: '#0369A1' },        // Azul Cielo nítido
  { key: 'MARTES', corto: 'Mar', label: 'Martes', color: '#16A34A', bg: '#DCFCE7', text: '#15803D' },       // Verde Esmeralda vivo
  { key: 'MIERCOLES', corto: 'Mié', label: 'Miércoles', color: '#EA580C', bg: '#FFEDD5', text: '#C2410C' }, // Naranja cálido
  { key: 'JUEVES', corto: 'Jue', label: 'Jueves', color: '#8B5CF6', bg: '#EDE9FE', text: '#6D28D9' },       // Violeta / Púrpura
  { key: 'VIERNES', corto: 'Vie', label: 'Viernes', color: '#E11D48', bg: '#FFE4E6', text: '#BE123C' },     // Rosa Carmesí intenso
  { key: 'SABADO', corto: 'Sáb', label: 'Sábado', color: '#CA8A04', bg: '#FEF9C3', text: '#854D0E' },       // Amarillo Dorado / Mostaza
  { key: 'DOMINGO', corto: 'Dom', label: 'Domingo', color: '#64748B', bg: '#F1F5F9', text: '#334155' },     // Gris Pizarra neutro
];

// Paleta de Máximo Contraste Perceptual para Rutas
// Diseñada alternando estrictamente familias de color no colisionantes:
// Rojo -> Azul -> Verde -> Naranja -> Púrpura -> Dorado -> Cian -> Magenta -> Oliva -> Marino...
export const RUTA_PALETTE = [
  '#E11D48', // 0: Rojo Carmesí
  '#2563EB', // 1: Azul Real
  '#16A34A', // 2: Verde Pasto
  '#EA580C', // 3: Naranja Vibrante
  '#9333EA', // 4: Púrpura Brillante
  '#CA8A04', // 5: Dorado Ámbar
  '#06B6D4', // 6: Cian Eléctrico
  '#D946EF', // 7: Fucsia / Magenta
  '#4D7C0F', // 8: Verde Oliva Oscuro
  '#1E3A8A', // 9: Azul Marino Profundo
  '#F43F5E', // 10: Rojo Coral
  '#78350F', // 11: Café Chocolate
  '#8B5CF6', // 12: Lavanda Violeta
  '#0D9488', // 13: Verde Azulado / Teal
  '#EAB308', // 14: Amarillo Puro
  '#0F172A', // 15: Carbón Pizarra
  '#84CC16', // 16: Verde Lima
  '#9D174D', // 17: Rosa Frambuesa Oscuro
  '#0284C7', // 18: Azul Celeste
  '#C2410C', // 19: Terracota / Óxido
  '#6D28D9', // 20: Violeta Real
  '#059669', // 21: Esmeralda Oscuro
  '#DB2777', // 22: Rosa Fuerte
  '#A16207', // 23: Bronce Mostaza
  '#4F46E5', // 24: Índigo Brillante
  '#047857', // 25: Verde Bosque
  '#BE123C', // 26: Rubí Intenso
  '#D97706', // 27: Ámbar Cálido
  '#7C3AED', // 28: Púrpura Profundo
  '#0891B2', // 29: Cian Océano
  '#B91C1C', // 30: Rojo Borgoña
  '#15803D', // 31: Verde Selva
  '#C026D3', // 32: Magenta Ciruela
  '#F97316', // 33: Mandarina
  '#312E81', // 34: Azul Noche
  '#65A30D', // 35: Verde Musgo
  '#A21CAF', // 36: Orquídea
  '#0F766E', // 37: Teal Oscuro
  '#DC2626', // 38: Rojo Escarlata
  '#854D0E', // 39: Caramelo Tostado
  '#4338CA', // 40: Índigo Oscuro
  '#14B8A6', // 41: Menta Marina
  '#EC4899', // 42: Rosa Neón
  '#B45309', // 43: Cobre
  '#701A75', // 44: Berenjena
  '#166534', // 45: Verde Pino
  '#991B1B', // 46: Granate
  '#0369A1', // 47: Azul Acero
  '#6B21A8', // 48: Púrpura Imperial
  '#334155', // 49: Grafito
];

const rutaColorCache = new Map();
let nextColorIndex = 0;

/**
 * Registra un catálogo de rutas ordenadas asignando colores únicos y completamente diferentes
 * garantizando que rutas adyacentes o seleccionadas tengan contraste visual máximo.
 */
export function registerRutasCatalogo(catalogo) {
  if (!catalogo) return;
  const list = Array.isArray(catalogo) ? catalogo : Object.values(catalogo);
  const uniqueRutas = Array.from(new Set(
    list.map(item => (typeof item === 'string' ? item : (item?.ruta || ''))).filter(r => String(r).trim() !== '')
  )).sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' }));

  uniqueRutas.forEach((ruta, i) => {
    const clean = String(ruta).toUpperCase().trim();
    if (!rutaColorCache.has(clean)) {
      const index = i % RUTA_PALETTE.length;
      rutaColorCache.set(clean, RUTA_PALETTE[index]);
    }
  });
}

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

function fmix32(h) {
  h ^= h >>> 16;
  h = Math.imul(h, 0x85ebca6b);
  h ^= h >>> 13;
  h = Math.imul(h, 0xc2b2ae35);
  h ^= h >>> 16;
  return (h >>> 0);
}

/**
 * Retorna el color asignado a una ruta con máxima diferenciación cromática.
 */
export function getRutaColor(ruta) {
  if (!ruta || String(ruta).trim() === '' || String(ruta).toLowerCase() === 'null') {
    return '#64748B'; // Slate 500 para Sin Ruta
  }
  const cleanRuta = String(ruta).toUpperCase().trim();
  if (!rutaColorCache.has(cleanRuta)) {
    // Si la ruta no fue registrada en lote, asignarle el siguiente color disponible o por hash disperso
    let h = 0x811c9dc5;
    for (let i = 0; i < cleanRuta.length; i++) {
      h = Math.imul(h ^ cleanRuta.charCodeAt(i), 0x5bd1e995);
      h ^= h >>> 15;
    }
    const index = fmix32(h) % RUTA_PALETTE.length;
    rutaColorCache.set(cleanRuta, RUTA_PALETTE[index]);
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
