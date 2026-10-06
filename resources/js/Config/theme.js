/**
 * Tokens de diseño y tema visual futurista, limpio y minimalista.
 * Diseñado para alta densidad de datos, responsividad y nitidez.
 */
export const themeConfig = {
  // Paleta de colores semántica
  colors: {
    // Superficies y Fondos
    background: '#F8FAFC',       // Slate 50 ultra limpio
    surface: '#FFFFFF',          // Blanco puro
    surfaceSubtle: '#F1F5F9',    // Slate 100
    surfaceMuted: '#E2E8F0',     // Slate 200

    // Bordes & Separadores (Líneas finas 1px sutiles)
    border: '#E2E8F0',
    borderSubtle: '#F1F5F9',
    borderStrong: '#CBD5E1',

    // Textos
    textPrimary: '#0F172A',      // Slate 900
    textSecondary: '#475569',    // Slate 600
    textMuted: '#94A3B8',        // Slate 400
    textInverse: '#FFFFFF',

    // Acentos Futuristas
    brand: '#0F172A',            // Slate 900 (Minimalist Dark Primary)
    accent: '#0284C7',           // Cyan / Sky
    accentGlow: 'rgba(2, 132, 199, 0.15)',

    // Estados Semánticos
    success: {
      text: '#047857',
      bg: '#ECFDF5',
      border: '#A7F3D0',
      dot: '#10B981'
    },
    warning: {
      text: '#B45309',
      bg: '#FFFBEB',
      border: '#FDE68A',
      dot: '#F59E0B'
    },
    danger: {
      text: '#B91C1C',
      bg: '#FEF2F2',
      border: '#FECACA',
      dot: '#EF4444'
    },
    info: {
      text: '#0369A1',
      bg: '#F0F9FF',
      border: '#BAE6FD',
      dot: '#0EA5E9'
    },
    neutral: {
      text: '#475569',
      bg: '#F8FAFC',
      border: '#E2E8F0',
      dot: '#64748B'
    },
    purple: {
      text: '#6D28D9',
      bg: '#F5F3FF',
      border: '#DDD6FE',
      dot: '#8B5CF6'
    }
  },

  // Clases utilitarias de Glassmorphism & Micro-elevaciones
  effects: {
    glass: 'bg-white/80 backdrop-blur-md border border-slate-200/80 shadow-xs',
    glassDark: 'bg-slate-900/90 backdrop-blur-md border border-slate-800 text-white',
    card: 'bg-white border border-slate-200/90 rounded-xl shadow-xs transition-all hover:border-slate-300',
    statCard: 'bg-white border border-slate-200/90 rounded-xl p-3.5 sm:p-4 shadow-xs relative overflow-hidden',
    subtleRing: 'focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500'
  },

  // Densidad y Espaciado (Optimizado para supervisión compacta)
  density: {
    compact: {
      tableRow: 'py-2 px-3 text-xs',
      tableHeader: 'py-2 px-3 text-[11px] font-semibold uppercase tracking-wider',
      input: 'h-8 text-xs px-2.5 rounded-lg',
      button: 'h-8 px-3 text-xs rounded-lg font-medium'
    }
  }
};

