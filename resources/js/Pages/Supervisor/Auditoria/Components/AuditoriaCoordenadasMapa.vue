<template>
  <div v-if="esValido" class="inline-block" @click.stop>
    <!-- BOTÓN DESPLEGABLE DE MAPA -->
    <button
      type="button"
      @click="alternarMapa"
      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[10px] font-bold transition-all cursor-pointer shadow-2xs select-none"
      :class="desplegado
        ? 'bg-[#0071E3] text-white border border-[#0071E3]'
        : 'bg-[#0071E3]/10 hover:bg-[#0071E3]/20 text-[#0071E3] border border-[#0071E3]/20'"
      :title="desplegado ? 'Ocultar mapa interactivo' : 'Ver desplazamiento geográfico en mapa interactivo'"
    >
      <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689A1.125 1.125 0 003 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
      </svg>
      <span>{{ desplegado ? 'Ocultar Mapa' : (distanciaTexto ? `Mapa (${distanciaTexto})` : 'Ver Mapa') }}</span>
      <svg
        class="w-2.5 h-2.5 transition-transform duration-200 shrink-0"
        :class="desplegado ? 'rotate-180' : ''"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </button>

    <!-- PANEL DESPLEGABLE DEL MAPA (EXPANSIBLE EN LA MISMA COLUMNA) -->
    <div
      v-if="desplegado"
      class="mt-2 w-full min-w-[300px] max-w-xl p-2.5 rounded-[12px] bg-white border border-black/[0.08] shadow-md space-y-2 text-left"
    >
      <!-- Cabecera de controles e información del visor -->
      <div class="flex items-center justify-between gap-2 flex-wrap text-xs">
        <!-- Puntos A (Anterior) y B (Nuevo) -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <div v-if="coordAnt" class="flex items-center gap-1 font-mono text-[10px] px-1.5 py-0.5 rounded bg-red-50 text-red-700 border border-red-200">
            <span class="w-3.5 h-3.5 rounded-full bg-red-600 text-white font-extrabold flex items-center justify-center text-[8px]">A</span>
            <span class="font-bold">Anterior:</span>
            <span>{{ coordAnt.lat.toFixed(5) }}, {{ coordAnt.lng.toFixed(5) }}</span>
          </div>

          <span v-if="coordAnt && coordNue" class="text-[#86868B] text-[10px]">&rarr;</span>

          <div v-if="coordNue" class="flex items-center gap-1 font-mono text-[10px] px-1.5 py-0.5 rounded bg-sky-50 text-[#0071E3] border border-sky-200">
            <span class="w-3.5 h-3.5 rounded-full bg-[#0071E3] text-white font-extrabold flex items-center justify-center text-[8px]">B</span>
            <span class="font-bold">Actual:</span>
            <span>{{ coordNue.lat.toFixed(5) }}, {{ coordNue.lng.toFixed(5) }}</span>
          </div>
        </div>

        <!-- Acciones del Mapa -->
        <div class="flex items-center gap-1.5 shrink-0">
          <!-- Desplazamiento -->
          <span
            v-if="distanciaTexto"
            class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-mono font-bold text-[10px]"
            title="Distancia geográfica de desplazamiento"
          >
            {{ distanciaTexto }}
          </span>

          <!-- Alternar Satélite / Calles -->
          <button
            type="button"
            @click="alternarCapa"
            class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold bg-[#F5F5F7] hover:bg-[#E5E5EA] text-[#1D1D1F] border border-black/10 cursor-pointer transition-colors"
          >
            {{ capaActual === 'openStreetMap' ? 'Satélite' : 'Calles' }}
          </button>

          <!-- Botón Re-centrar -->
          <button
            type="button"
            @click="centrarVista"
            class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold bg-[#F5F5F7] hover:bg-[#E5E5EA] text-[#0071E3] border border-black/10 cursor-pointer transition-colors"
            title="Re-centrar vista en ambos puntos"
          >
            Centrar
          </button>
        </div>
      </div>

      <!-- Lienzo del Mapa Leaflet -->
      <div
        :id="mapContainerId"
        class="w-full h-56 rounded-[10px] border border-black/[0.08] overflow-hidden relative z-0"
      ></div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { mapsConfig } from '@/Config/maps';

const props = defineProps({
  id: {
    type: [Number, String],
    required: true,
  },
  valorAnterior: {
    type: [String, Number],
    default: '',
  },
  valorNuevo: {
    type: [String, Number],
    default: '',
  },
  cliente: {
    type: String,
    default: '',
  },
  clienteId: {
    type: [Number, String],
    default: '',
  },
});

const desplegado = ref(false);
const capaActual = ref('openStreetMap');
const mapContainerId = computed(() => 'mapa-coord-' + String(props.id).replace(/[^a-zA-Z0-9_-]/g, '_'));

let mapInstance = null;
let currentTileLayer = null;

// Parser robusto de coordenadas
function extraerCoordenadas(texto) {
  if (!texto || typeof texto !== 'string') return null;
  const limpio = texto.trim();
  if (!limpio || limpio === '-' || limpio.toLowerCase() === 'null') return null;

  const regex = /(-?\d{1,3}(?:\.\d+)?)[,\s/|]+(-?\d{1,3}(?:\.\d+)?)/;
  const match = limpio.match(regex);
  if (!match) return null;

  let lat = parseFloat(match[1]);
  let lng = parseFloat(match[2]);

  if (isNaN(lat) || isNaN(lng)) return null;
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;

  // Inversión automática si vinieron invertidas (longitud ~ -68 primero y latitud ~ -16 después)
  if (lat < -50 && lat > -75 && lng > -25 && lng < -8) {
    const tmp = lat;
    lat = lng;
    lng = tmp;
  }

  return { lat, lng };
}

function calcularDistanciaMetros(c1, c2) {
  if (!c1 || !c2) return null;
  const R = 6371000;
  const dLat = (c2.lat - c1.lat) * (Math.PI / 180);
  const dLng = (c2.lng - c1.lng) * (Math.PI / 180);
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(c1.lat * (Math.PI / 180)) *
      Math.cos(c2.lat * (Math.PI / 180)) *
      Math.sin(dLng / 2) * Math.sin(dLng / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return Math.round(R * c);
}

function formatearDistancia(metros) {
  if (metros === null || metros === undefined) return '';
  if (metros >= 1000) {
    return `${(metros / 1000).toFixed(2)} km`;
  }
  return `${metros} m`;
}

const coordAnt = computed(() => extraerCoordenadas(props.valorAnterior));
const coordNue = computed(() => extraerCoordenadas(props.valorNuevo));
const esValido = computed(() => Boolean(coordAnt.value || coordNue.value));

const distanciaMetros = computed(() => {
  if (coordAnt.value && coordNue.value) {
    return calcularDistanciaMetros(coordAnt.value, coordNue.value);
  }
  return null;
});

const distanciaTexto = computed(() => {
  if (distanciaMetros.value === null) return '';
  return formatearDistancia(distanciaMetros.value);
});

function alternarMapa() {
  desplegado.value = !desplegado.value;
  if (desplegado.value) {
    nextTick(() => {
      setTimeout(() => {
        initMap();
      }, 60);
    });
  } else {
    destruirMap();
  }
}

function destruirMap() {
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
    currentTileLayer = null;
  }
}

function alternarCapa() {
  if (!mapInstance) return;
  const proximaCapa = capaActual.value === 'openStreetMap' ? 'satellite' : 'openStreetMap';
  capaActual.value = proximaCapa;

  if (currentTileLayer) {
    mapInstance.removeLayer(currentTileLayer);
  }

  const conf = mapsConfig.tileLayers[proximaCapa];
  currentTileLayer = L.tileLayer(conf.url, {
    maxZoom: conf.maxZoom || 19,
    subdomains: conf.subdomains || 'abc',
    attribution: conf.attribution,
  }).addTo(mapInstance);
}

function centrarVista() {
  if (!mapInstance) return;
  if (coordAnt.value && coordNue.value) {
    mapInstance.fitBounds(
      [
        [coordAnt.value.lat, coordAnt.value.lng],
        [coordNue.value.lat, coordNue.value.lng],
      ],
      { padding: [35, 35], maxZoom: 18 }
    );
  } else if (coordNue.value || coordAnt.value) {
    const pt = coordNue.value || coordAnt.value;
    mapInstance.setView([pt.lat, pt.lng], 16);
  }
}

function initMap() {
  const container = document.getElementById(mapContainerId.value);
  if (!container) return;

  destruirMap();

  const cAnt = coordAnt.value;
  const cNue = coordNue.value;
  const centroInicial = cNue || cAnt || mapsConfig.defaultLocation;

  mapInstance = L.map(container, {
    zoomControl: false,
    preferCanvas: true,
    scrollWheelZoom: false,
  }).setView([centroInicial.lat, centroInicial.lng], 16);

  L.control.zoom({ position: 'topright' }).addTo(mapInstance);

  const conf = mapsConfig.tileLayers[capaActual.value];
  currentTileLayer = L.tileLayer(conf.url, {
    maxZoom: conf.maxZoom || 19,
    subdomains: conf.subdomains || 'abc',
    attribution: conf.attribution,
  }).addTo(mapInstance);

  // Marcador A (Anterior) en Rojo
  if (cAnt) {
    const iconAnterior = L.divIcon({
      className: 'custom-pin-anterior',
      html: `
        <div style="
          background-color: #EF4444;
          color: white;
          font-weight: 800;
          font-size: 10px;
          font-family: monospace;
          width: 22px;
          height: 22px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 2px 6px rgba(0,0,0,0.35);
          border: 2px solid white;
        ">A</div>
      `,
      iconSize: [22, 22],
      iconAnchor: [11, 11],
    });

    const markerA = L.marker([cAnt.lat, cAnt.lng], { icon: iconAnterior }).addTo(mapInstance);
    markerA.bindTooltip(
      `<div style="font-family: monospace; font-size: 10px;"><strong>Ubicación Anterior (A):</strong><br/>${cAnt.lat.toFixed(6)}, ${cAnt.lng.toFixed(6)}</div>`,
      { direction: 'top', offset: [0, -10] }
    );
  }

  // Marcador B (Actual / Nuevo) en Azul
  if (cNue) {
    const iconNuevo = L.divIcon({
      className: 'custom-pin-nuevo',
      html: `
        <div style="
          background-color: #0071E3;
          color: white;
          font-weight: 800;
          font-size: 10px;
          font-family: monospace;
          width: 22px;
          height: 22px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 2px 6px rgba(0,0,0,0.35);
          border: 2px solid white;
        ">B</div>
      `,
      iconSize: [22, 22],
      iconAnchor: [11, 11],
    });

    const markerB = L.marker([cNue.lat, cNue.lng], { icon: iconNuevo }).addTo(mapInstance);
    markerB.bindTooltip(
      `<div style="font-family: monospace; font-size: 10px;"><strong>Ubicación Actual (B):</strong><br/>${cNue.lat.toFixed(6)}, ${cNue.lng.toFixed(6)}</div>`,
      { direction: 'top', offset: [0, -10] }
    );
  }

  // Línea de desplazamiento entre A y B
  if (cAnt && cNue) {
    const latlngs = [
      [cAnt.lat, cAnt.lng],
      [cNue.lat, cNue.lng],
    ];

    L.polyline(latlngs, {
      color: '#0071E3',
      weight: 3,
      dashArray: '6, 6',
      opacity: 0.9,
    }).addTo(mapInstance);

    // Tooltip central con la distancia
    if (distanciaTexto.value) {
      const midLat = (cAnt.lat + cNue.lat) / 2;
      const midLng = (cAnt.lng + cNue.lng) / 2;
      L.tooltip({
        permanent: true,
        direction: 'center',
        className: 'font-mono text-[10px] font-bold px-1.5 py-0.5 rounded shadow-sm bg-slate-900 text-white border-0',
      })
        .setLatLng([midLat, midLng])
        .setContent(distanciaTexto.value)
        .addTo(mapInstance);
    }

    mapInstance.fitBounds(latlngs, { padding: [35, 35], maxZoom: 18 });
  } else {
    mapInstance.setView([centroInicial.lat, centroInicial.lng], 16);
  }

  setTimeout(() => {
    if (mapInstance) mapInstance.invalidateSize();
  }, 120);
}

onBeforeUnmount(() => {
  destruirMap();
});
</script>
