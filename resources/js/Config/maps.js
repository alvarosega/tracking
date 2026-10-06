/**
 * Configuración centralizada de mapas (Leaflet / GIS) para el panel de supervisión.
 * Ubicación por defecto: El Alto, Bolivia.
 * Capas públicas 100% libres sin necesidad de API Key.
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

  // Capas de mapas soportadas (Tiles 100% públicos y sin API key)
  tileLayers: {
    openStreetMap: {
      name: 'Calles',
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      subdomains: 'abc',
      maxZoom: 19
    },
    satellite: {
      name: 'Satélite HD',
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      attribution: 'Tiles &copy; Esri',
      maxZoom: 19
    }
  },

  // Capa por defecto
  defaultTileLayer: 'openStreetMap',

  // Configuración de marcadores
  markers: {
    vendedor: {
      color: '#0284C7',
      pulseColor: 'rgba(2, 132, 199, 0.4)',
      size: [32, 32],
      iconAnchor: [16, 32]
    },
    clienteEnRuta: {
      color: '#059669',
      size: [10, 10]
    },
    clienteFueraRuta: {
      color: '#D97706',
      size: [10, 10]
    },
    visitaRealizada: {
      color: '#059669',
      size: [12, 12]
    },
    visitaPendiente: {
      color: '#64748B',
      size: [8, 8]
    },
    sinGps: {
      color: '#DC2626',
      size: [10, 10]
    }
  }
};
