/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./resources/**/*.{vue,js,ts,jsx,tsx,blade.php}",
  ],
  theme: {
    extend: {
      colors: {
        'app-accent': '#0071E3', // Azul Royal Apple
        'border-subtle': '#E5E5EA',
        'border-light': '#F2F2F7',
        'ink': {
          DEFAULT: '#1D1D1F',  // Texto primario de alto contraste
          secondary: '#6E6E73',// Texto secundario / subtítulos
          tertiary: '#86868B', // Placeholder y etiquetas menores
          quaternary: '#C7C7CC',
        },
        'surface': {
          DEFAULT: '#FFFFFF',
          canvas: '#F5F5F7',
          subtle: '#FBFBFD',
          hover: '#F2F2F7',
        },
        'badge': {
          'success-bg': '#EBF9EF',
          'success-text': '#248A3D',
          'warning-bg': '#FFF5E5',
          'warning-text': '#B25E00',
          'danger-bg': '#FDF0EF',
          'danger-text': '#C9342C',
          'neutral-bg': '#EFEFF4',
          'neutral-text': '#6E6E73',
        }
      },
      fontFamily: {
        sans: [
          '-apple-system',
          'BlinkMacSystemFont',
          '"SF Pro Text"',
          '"SF Pro Display"',
          '"Inter"',
          '"Segoe UI"',
          'Roboto',
          'sans-serif'
        ],
      },
      borderRadius: {
        'card': '16px',
        'control': '10px',
        'pill': '9999px',
      },
      boxShadow: {
        'subtle': '0 1px 2px rgba(0, 0, 0, 0.03)',
        'float': '0 8px 24px -4px rgba(0, 0, 0, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04)',
        'glass-card': '0 12px 36px -6px rgba(15, 23, 42, 0.06), 0 2px 8px -1px rgba(0, 113, 227, 0.03)',
      },
    },
  },
  plugins: [],
}

