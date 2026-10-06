/**
 * Configuración centralizada de mapas (Leaflet / GIS) para el panel de supervisión.
 * Ubicación por defecto: El Alto, Bolivia.
 */
export const mapsConfig = {
  // Coordenadas base por defecto: El Alto, Bolivia
  defaultLocation: {
    lat: -16.5050,
    lng: -68.1650,
    zoom: 13,
    minZoom: 10,
    maxZoom: 19
  },

  // Capas de mapas soportadas (Tiles)
  tileLayers: {
    minimalLight: {
      name: 'Futurista Minimal (CartoDB)',
      url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
      attribution: '&copy; <a href="https://carto.com/">CARTO</a> &copy; OpenStreetMap',
      subdomains: 'abcd',
      maxZoom: 20
    },
    cleanPositron: {
      name: 'Clean Slate (Positron)',
      url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
      attribution: '&copy; <a href="https://carto.com/">CARTO</a> &copy; OpenStreetMap',
      subdomains: 'abcd',
      maxZoom: 20
    },
    openStreetMap: {
      name: 'OpenStreetMap Estándar',
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    },
    satellite: {
      name: 'Satélite HD (Esri)',
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
      maxZoom: 19
    }
  },

  // Capa por defecto seleccionada
  defaultTileLayer: 'minimalLight',

  // Configuración de marcadores y colores
  markers: {
    vendedor: {
      color: '#0284C7', // Cyan / Sky Blue futurista
      pulseColor: 'rgba(2, 132, 199, 0.4)',
      size: [32, 32],
      iconAnchor: [16, 32]
    },
    clienteEnRuta: {
      color: '#10B981', // Esmeralda / Green
      size: [10, 10]
    },
    clienteFueraRuta: {
      color: '#F59E0B', // Amber / Naranja
      size: [10, 10]
    },
    visitaRealizada: {
      color: '#059669', // Emerald Dark
      size: [12, 12]
    },
    visitaPendiente: {
      color: '#64748B', // Slate Muted
      size: [8, 8]
    },
    alertaSinGps: {
      color: '#EF4444', // Red
      size: [14, 14]
    }
  },

  // Estilos de geometrías (Polígonos de zonas y líneas de ruta)
  polygons: {
    boundary: {
      color: '#2563EB',
      weight: 2,
      fillColor: '#3B82F6',
      fillOpacity: 0.08,
      dashArray: '4, 6'
    },
    activePath: {
      color: '#0284C7',
      weight: 3.5,
      opacity: 0.85,
      smoothFactor: 1.2
    },
    historicalPath: {
      color: '#64748B',
      weight: 2.5,
      opacity: 0.6,
      dashArray: '3, 5'
    }
  }
};

