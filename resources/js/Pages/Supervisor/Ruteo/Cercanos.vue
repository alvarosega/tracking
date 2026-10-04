<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-[#F5F5F7]">
      <!-- Barra Superior de Control de Proximidad -->
      <div class="bg-white border-b border-[#E5E5EA] px-4 py-2 flex flex-wrap items-center justify-between gap-3 shrink-0 z-30">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Botón de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-7 px-2.5 bg-[#F2F2F7] hover:bg-[#E5E5EA] border border-[#E5E5EA] rounded-[6px] text-xs font-semibold text-[#1D1D1F] flex items-center gap-1.5 transition-colors disabled:opacity-50"
            title="Usar ubicación satelital de este dispositivo"
          >
            <span class="w-2 h-2 rounded-full" :class="obteniendoGps ? 'bg-[#FF9500] animate-ping' : 'bg-[#0071E3]'"></span>
            <span>{{ obteniendoGps ? 'Localizando...' : 'Mi Ubicación' }}</span>
          </button>

          <!-- Selector de Radio -->
          <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <span class="text-[10px] text-[#86868B] font-mono px-2">Radio:</span>
            <button
              v-for="r in opcionesRadio"
              :key="r.valor"
              type="button"
              @click="seleccionarRadio(r.valor)"
              class="px-2 py-0.5 text-[11px] font-medium rounded-[4px] transition-all"
              :class="radioSeleccionado === r.valor ? 'bg-white text-[#1D1D1F] shadow-2xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              {{ r.etiqueta }}
            </button>
          </div>

          <!-- Filtro Dropdown: Canales -->
          <div class="relative">
            <button
              type="button"
              @click="toggleDropdown('canales')"
              class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1.5 transition-colors"
            >
              <span>Canal:</span>
              <span class="font-semibold">{{ labelCanales }}</span>
              <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-if="activeDropdown === 'canales'"
              class="absolute left-0 mt-1 w-52 bg-white border border-[#E5E5EA] rounded-[8px] shadow-lg p-2 z-50 text-xs"
            >
              <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
                <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-[#0071E3] font-medium hover:underline">Todos</button>
                <button type="button" @click="limpiarCanales" class="text-[11px] text-[#6E6E73] font-medium hover:underline">Ninguno</button>
              </div>
              <div class="max-h-48 overflow-y-auto space-y-1">
                <label v-for="c in canales" :key="c" class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer">
                  <input type="checkbox" :value="c" v-model="filtroCanales" @change="reconsultar" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0" />
                  <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
                  <span class="truncate">{{ c }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Filtro Dropdown: Rutas -->
          <div class="relative">
            <button
              type="button"
              @click="toggleDropdown('rutas')"
              class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1.5 transition-colors"
            >
              <span>Rutas:</span>
              <span class="font-semibold">{{ labelRutas }}</span>
              <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              v-if="activeDropdown === 'rutas'"
              class="absolute left-0 mt-1 w-64 bg-white border border-[#E5E5EA] rounded-[8px] shadow-lg p-2 z-50 text-xs"
            >
              <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
                <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-[#0071E3] font-medium hover:underline">Todas ({{ rutasDisponibles.length }})</button>
                <button type="button" @click="limpiarRutas" class="text-[11px] text-[#6E6E73] font-medium hover:underline">Ninguna</button>
              </div>
              <input
                v-model="busquedaRuta"
                type="text"
                placeholder="Buscar ruta..."
                class="w-full h-6 px-2 mb-1.5 text-xs bg-[#FBFBFD] border border-[#E5E5EA] rounded-[4px] focus:outline-none focus:border-[#86868B]"
              />
              <div class="max-h-56 overflow-y-auto space-y-1">
                <label v-for="r in rutasFiltradasEnDropdown" :key="r.ruta" class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer">
                  <input type="checkbox" :value="r.ruta" v-model="filtroRutas" @change="reconsultar" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0" />
                  <div class="truncate flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-black/20" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                    <span class="font-medium">{{ r.ruta }}</span>
                    <span class="text-[#86868B] text-[10px]">({{ r.canal }})</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Feedback de Clientes Hallados -->
        <div class="flex items-center space-x-2 text-xs font-mono">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Calculando proximidad...
          </span>
          <div class="px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1">
            <span class="text-[#86868B]">Encontrados:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ clientesCercanos.length }}</span>
          </div>
        </div>
      </div>

      <!-- Cuerpo Split Screen -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative">
        <!-- Panel Izquierdo: Lista Densa Ordenada por Distancia -->
        <div class="w-full md:w-[380px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0">
          <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between">
            <span class="text-[11px] font-semibold text-[#86868B] uppercase tracking-wider">Clientes en Radio</span>
            <span v-if="origenConsulta" class="text-[10px] font-mono text-[#6E6E73]">
              {{ origenConsulta.lat.toFixed(4) }}, {{ origenConsulta.lng.toFixed(4) }}
            </span>
          </div>

          <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
            <div v-if="!origenConsulta" class="p-8 text-center text-xs text-[#86868B] space-y-2">
              <svg class="w-8 h-8 mx-auto text-[#86868B]/60 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
              </svg>
              <p>Haz clic en cualquier punto del mapa o usa el botón <strong>"Mi Ubicación"</strong> para buscar clientes alrededor.</p>
            </div>

            <div v-else-if="clientesCercanos.length === 0 && !cargando" class="p-8 text-center text-xs text-[#86868B]">
              No se encontraron clientes dentro del radio de {{ radioSeleccionado }}m. Intenta ampliar el radio o seleccionar más rutas.
            </div>

            <button
              v-for="c in clientesCercanos"
              :key="c.id"
              type="button"
              @click="enfocarCliente(c)"
              class="w-full text-left p-3 hover:bg-[#FBFBFD] transition-colors focus:outline-none"
              :class="clienteActivoId === c.id ? 'bg-[#F2F2F7]' : ''"
            >
              <div class="flex items-start justify-between gap-1.5">
                <span class="text-xs font-semibold text-[#1D1D1F] line-clamp-1">{{ c.cliente }}</span>
                <div class="flex items-center gap-1 shrink-0">
                  <span
                    class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white"
                    :style="{ backgroundColor: getCanalColor(c.canal_calculado) }"
                  >
                    {{ c.canal_calculado }}
                  </span>
                  <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] tabular-nums">
                    {{ c.distancia_metros }} m
                  </span>
                </div>
              </div>
              <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ c.direccion || 'Sin dirección' }}</p>
              <div class="flex items-center space-x-2 mt-1.5 text-[10px] text-[#86868B] font-mono">
                <span class="flex items-center gap-1 font-medium text-[#1D1D1F]">
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                  {{ c.ruta }}
                </span>
                <span>•</span>
                <span>{{ c.dia_norm }}</span>
                <span>•</span>
                <span>{{ c.tipo_negocio || 'General' }}</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Panel Derecho: Mapa Interactivo Leaflet -->
        <div class="flex-1 relative min-h-[350px] md:min-h-0 bg-[#E5E5EA]">
          <div id="map-cercanos" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda Flotante de Canales -->
          <div class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-400 text-[11px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Canales (Color Pin)</p>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1">
              <div v-for="c in canales" :key="c" class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
                <span class="text-[#1D1D1F] font-mono text-[10px]">{{ c }}</span>
              </div>
            </div>
            <div class="border-t border-[#E5E5EA] mt-1.5 pt-1.5 text-[10px] text-[#86868B]">
              Borde del pin = Ruta asignada
            </div>
          </div>
        </div>
      </div>
    </div>
  </RuteoLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import RuteoLayout from '@/Pages/Supervisor/Ruteo/Layout.vue';
import L from 'leaflet';

const props = defineProps({
  catalogo_rutas: Array,
  canales: Array,
});

// Paleta Oficial de Canales (Estilo Apple Pro)
const CANAL_COLORS = {
  'MAY': '#0071E3',   // Azul corporativo (Mayorista)
  'TDB': '#34C759',   // Verde (Tienda de Barrio)
  'MZO': '#FF9500',   // Ámbar (Mercado / Zona)
  'PROV': '#AF52DE',  // Púrpura (Provincias)
  'PRT': '#8E8E93',   // Gris neutro (Particular / Otros)
};

function getCanalColor(canal) {
  if (!canal) return '#8E8E93';
  return CANAL_COLORS[canal] || '#5856D6';
}

// Paleta de Borde para Rutas
const RUTA_PALETTE = ['#1D1D1F', '#00C7BE', '#A2845E', '#30B0C7', '#6366F1', '#EC4899', '#14B8A6', '#F59E0B', '#8B5CF6', '#10B981'];
const rutaColorCache = new Map();

function getRutaColor(ruta) {
  if (!ruta) return '#1D1D1F';
  if (!rutaColorCache.has(ruta)) {
    const colorIndex = rutaColorCache.size % RUTA_PALETTE.length;
    rutaColorCache.set(ruta, RUTA_PALETTE[colorIndex]);
  }
  return rutaColorCache.get(ruta);
}

// Mapeo ruta -> canal
const rutaToCanalMap = computed(() => {
  const map = {};
  props.catalogo_rutas.forEach(r => {
    map[r.ruta] = r.canal;
  });
  return map;
});

const opcionesRadio = [
  { etiqueta: '100m', valor: 100 },
  { etiqueta: '250m', valor: 250 },
  { etiqueta: '300m', valor: 300 },
  { etiqueta: '500m', valor: 500 },
  { etiqueta: '1km', valor: 1000 },
  { etiqueta: '2km', valor: 2000 },
];

const radioSeleccionado = ref(300);
const filtroCanales = ref([...props.canales]);
const filtroRutas = ref(props.catalogo_rutas.map(r => r.ruta));
const activeDropdown = ref(null);
const busquedaRuta = ref('');

const cargando = ref(false);
const obteniendoGps = ref(false);
const origenConsulta = ref(null);
const clientesCercanos = ref([]);
const clienteActivoId = ref(null);

let map = null;
let centerMarker = null;
let radiusCircle = null;
let markersLayer = null;
const markersMap = new Map();

const rutasDisponibles = computed(() => {
  if (filtroCanales.value.length === 0) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => filtroCanales.value.includes(r.canal));
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === props.canales.length) return 'Todos';
  if (filtroCanales.value.length === 0) return 'Ninguno';
  return `${filtroCanales.value.length} seleccionados`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 0) return 'Ninguna';
  return `${filtroRutas.value.length} seleccionadas`;
});

function toggleDropdown(name) {
  activeDropdown.value = activeDropdown.value === name ? null : name;
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  reconsultar();
}

function limpiarCanales() {
  filtroCanales.value = [];
  filtroRutas.value = [];
  reconsultar();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  reconsultar();
}

function limpiarRutas() {
  filtroRutas.value = [];
  reconsultar();
}

function seleccionarRadio(nuevoRadio) {
  radioSeleccionado.value = nuevoRadio;
  if (radiusCircle) {
    radiusCircle.setRadius(nuevoRadio);
  }
  if (origenConsulta.value) {
    ejecutarBusqueda(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function reconsultar() {
  if (origenConsulta.value) {
    ejecutarBusqueda(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

async function ejecutarBusqueda(lat, lng) {
  origenConsulta.value = { lat, lng };
  cargando.value = true;

  if (centerMarker) {
    centerMarker.setLatLng([lat, lng]);
  } else {
    centerMarker = L.circleMarker([lat, lng], {
      radius: 7,
      fillColor: '#0071E3',
      color: '#FFFFFF',
      weight: 2,
      opacity: 1,
      fillOpacity: 1,
    }).addTo(map);
  }

  if (radiusCircle) {
    radiusCircle.setLatLng([lat, lng]);
    radiusCircle.setRadius(radioSeleccionado.value);
  } else {
    radiusCircle = L.circle([lat, lng], {
      radius: radioSeleccionado.value,
      color: '#0071E3',
      weight: 1.5,
      dashArray: '4, 4',
      fillColor: '#0071E3',
      fillOpacity: 0.08,
    }).addTo(map);
  }

  try {
    const res = await axios.post(route('supervisor.ruteo.cercanos.data'), {
      latitud: lat,
      longitud: lng,
      radio: radioSeleccionado.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
    });

    clientesCercanos.value = res.data.clientes.map(c => ({
      ...c,
      canal_calculado: rutaToCanalMap.value[c.ruta] || 'PRT'
    }));

    renderizarClientesEnMapa();
  } catch (err) {
    console.error('Error calculando proximidad:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarClientesEnMapa() {
  if (!map) return;
  markersLayer.clearLayers();
  markersMap.clear();

  const unicaRuta = filtroRutas.value.length === 1;

  clientesCercanos.value.forEach(c => {
    const fillColor = getCanalColor(c.canal_calculado);
    const strokeColor = unicaRuta ? '#FFFFFF' : getRutaColor(c.ruta);

    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: 5.5,
      fillColor: fillColor,
      color: strokeColor,
      weight: unicaRuta ? 1.5 : 2.5,
      opacity: 1,
      fillOpacity: 0.95,
    });

    marker.bindPopup(`
      <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; line-height: 1.4; color: #1D1D1F; max-width: 220px;">
        <div style="font-weight: 600; font-size: 12px; margin-bottom: 2px;">${c.cliente}</div>
        <div style="color: ${fillColor}; font-weight: bold; margin-bottom: 2px;">
          ${c.canal_calculado} • a ${c.distancia_metros} metros
        </div>
        <div style="color: #6E6E73; margin-bottom: 4px;">${c.direccion || 'Sin dirección'}</div>
        <div style="font-family: monospace; font-size: 10px; color: #86868B; border-top: 1px solid #E5E5EA; padding-top: 4px;">
          Ruta: ${c.ruta} | ${c.dia_norm}
        </div>
      </div>
    `);

    marker.addTo(markersLayer);
    markersMap.set(c.id, marker);
  });
}

function enfocarCliente(c) {
  clienteActivoId.value = c.id;
  if (!map) return;

  map.flyTo([c.latitud, c.longitud], 18, { duration: 0.6 });
  const marker = markersMap.get(c.id);
  if (marker) {
    marker.openPopup();
  }
}

function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Tu navegador no soporta geolocalización satelital.');
    return;
  }

  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (position) => {
      obteniendoGps.value = false;
      const { latitude, longitude } = position.coords;
      map.flyTo([latitude, longitude], 16, { duration: 0.8 });
      ejecutarBusqueda(latitude, longitude);
    },
    (error) => {
      obteniendoGps.value = false;
      alert('No se pudo obtener tu ubicación. Verifica los permisos de ubicación en tu navegador.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function initMap() {
  map = L.map('map-cercanos', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([-16.5000, -68.1500], 13);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  markersLayer = L.featureGroup().addTo(map);

  map.on('click', (e) => {
    activeDropdown.value = null;
    ejecutarBusqueda(e.latlng.lat, e.latlng.lng);
  });
}

onMounted(() => {
  initMap();
  window.addEventListener('click', (e) => {
    if (!e.target.closest('.relative')) {
      activeDropdown.value = null;
    }
  });
});
</script>