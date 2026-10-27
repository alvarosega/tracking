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

// Paleta Armonizada de Alto Contraste para Rutas (50 colores de máxima diferenciación perceptual)
export const RUTA_PALETTE = [
  '#E11D48', // 0: Rose Crimson
  '#2563EB', // 1: Royal Blue
  '#059669', // 2: Emerald Green
  '#D97706', // 3: Amber Orange
  '#7C3AED', // 4: Deep Violet
  '#06B6D4', // 5: Electric Cyan
  '#DB2777', // 6: Deep Pink
  '#65A30D', // 7: Lime Green
  '#EA580C', // 8: Vivid Orange
  '#4F46E5', // 9: Indigo
  '#0D9488', // 10: Teal
  '#CA8A04', // 11: Yellow Ochre
  '#9333EA', // 12: Bright Purple
  '#0284C7', // 13: Light Blue
  '#16A34A', // 14: Grass Green
  '#DC2626', // 15: Crimson Red
  '#8B5CF6', // 16: Lavender
  '#0891B2', // 17: Ocean Cyan
  '#D946EF', // 18: Magenta / Fuchsia
  '#84CC16', // 19: Chartreuse
  '#F97316', // 20: Coral
  '#4338CA', // 21: Midnight Indigo
  '#14B8A6', // 22: Mint
  '#EAB308', // 23: Pure Yellow
  '#A855F7', // 24: Orchid
  '#3B82F6', // 25: Cobalt
  '#15803D', // 26: Forest Green
  '#BE123C', // 27: Ruby
  '#0E7490', // 28: Deep Cyan
  '#C026D3', // 29: Berry Purple
  '#4D7C0F', // 30: Olive Green
  '#C2410C', // 31: Rust / Sienna
  '#312E81', // 32: Deep Navy
  '#047857', // 33: Dark Pine
  '#A16207', // 34: Bronze Gold
  '#701A75', // 35: Plum
  '#1E40AF', // 36: Dark Cobalt
  '#065F46', // 37: Jungle Green
  '#991B1B', // 38: Burgundy
  '#0F172A', // 39: Slate Charcoal
  '#EC4899', // 40: Hot Pink
  '#0369A1', // 41: Steel Blue
  '#10B981', // 42: Sea Green
  '#F59E0B', // 43: Warm Amber
  '#6366F1', // 44: Cornflower
  '#0F766E', // 45: Deep Teal
  '#F43F5E', // 46: Coral Red
  '#854D0E', // 47: Caramel Brown
  '#6D28D9', // 48: Dark Violet
  '#064E3B', // 49: Dark Evergreen
];

const rutaColorCache = new Map();

/**
 * Registra un catálogo de rutas ordenadas asignando colores únicos y no colisionantes
 * mediante dispersión coprima (salto de 19 posiciones).
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
      const index = (i * 19) % RUTA_PALETTE.length;
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
 * Retorna el color asignado a una ruta con máxima dispersión cromática.
 */
export function getRutaColor(ruta) {
  if (!ruta || String(ruta).trim() === '' || String(ruta).toLowerCase() === 'null') {
    return '#64748B'; // Slate 500 para Sin Ruta
  }
  const cleanRuta = String(ruta).toUpperCase().trim();
  if (!rutaColorCache.has(cleanRuta)) {
    // Hash Murmur3 con mixer fmix32 para evitar colisiones en nombres contiguos (ej. 6A y 6B)
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

