<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-[#F5F5F7]">
      <!-- Barra Superior de Filtros y Capas -->
      <div class="bg-white border-b border-[#E5E5EA] px-4 py-2 flex flex-wrap items-center justify-between gap-3 shrink-0 z-30">
        <div class="flex flex-wrap items-center gap-2">
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
                  <input type="checkbox" :value="c" v-model="filtroCanales" @change="onCanalesChange" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0" />
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
                  <input type="checkbox" :value="r.ruta" v-model="filtroRutas" @change="cargarDatos" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0" />
                  <div class="truncate flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-black/20" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                    <span class="font-medium">{{ r.ruta }}</span>
                    <span class="text-[#86868B] text-[10px]">({{ r.canal }})</span>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <!-- Filtro Segmentado: Días -->
          <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <button
              v-for="d in diasSemana"
              :key="d.key"
              type="button"
              @click="toggleDia(d.key)"
              class="px-2 py-0.5 text-[11px] font-medium rounded-[4px] transition-all flex items-center gap-1"
              :class="filtroDias.includes(d.key) ? 'bg-white text-[#1D1D1F] shadow-2xs font-semibold' : 'text-[#86868B] hover:text-[#1D1D1F]'"
              :title="d.label"
            >
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: d.color }"></span>
              {{ d.corto }}
            </button>
            <button type="button" @click="toggleTodosDias" class="px-1.5 py-0.5 text-[10px] text-[#6E6E73] hover:text-[#1D1D1F] font-mono border-l border-[#E5E5EA] ml-0.5">
              {{ filtroDias.length === 6 ? 'Reset' : 'Todos' }}
            </button>
          </div>

          <!-- Checkboxes de Fronteras -->
          <div class="flex items-center space-x-3 text-xs pl-2 border-l border-[#E5E5EA]">
            <label class="flex items-center gap-1.5 cursor-pointer text-[#1D1D1F]">
              <input type="checkbox" v-model="verFronteraRutas" @change="cargarDatos" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0" />
              <span>Frontera Rutas</span>
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer text-[#1D1D1F]">
              <input type="checkbox" v-model="verFronteraDias" @change="cargarDatos" class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0" />
              <span>Frontera Días</span>
            </label>
          </div>
        </div>

        <!-- Indicador de Carga y KPIs -->
        <div class="flex items-center space-x-2 text-xs font-mono">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] flex items-center gap-1 animate-pulse mr-2">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Consultando...
          </span>
          <div class="px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1">
            <span class="text-[#86868B]">Total:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.total }}</span>
          </div>
          <div class="px-2 py-0.5 bg-[#EBF9EF] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
            <span class="text-[#248A3D] font-medium tabular-nums">{{ kpis.con_gps }}</span>
          </div>
          <div v-if="kpis.sin_gps > 0" class="px-2 py-0.5 bg-[#FFF5E5] text-[#B25E00] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#B25E00]"></span>
            <span class="font-medium tabular-nums">{{ kpis.sin_gps }} sin GPS</span>
          </div>
        </div>
      </div>

      <!-- Split Screen -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative">
        <div class="w-full md:w-[360px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0">
          <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD]">
            <input
              v-model="busquedaCliente"
              type="text"
              placeholder="Buscar cliente en mapa..."
              class="w-full h-7 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#86868B]"
            />
          </div>

          <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
            <div v-if="clientes.length === 0 && !cargando" class="p-8 text-center text-xs text-[#86868B]">
              Selecciona al menos un canal o ruta comercial para inspeccionar los puntos.
            </div>

            <button
              v-for="c in clientesFiltradosLista"
              :key="c.id"
              type="button"
              @click="enfocarCliente(c)"
              class="w-full text-left p-2.5 hover:bg-[#FBFBFD] transition-colors focus:outline-none"
              :class="clienteActivoId === c.id ? 'bg-[#F2F2F7]' : ''"
            >
              <div class="flex items-start justify-between gap-1.5">
                <span class="text-xs font-semibold text-[#1D1D1F] line-clamp-1">{{ c.nombre }}</span>
                <span
                  class="text-[9px] font-mono px-1 py-0.2 rounded shrink-0 border border-[#E5E5EA]"
                  :class="c.tiene_gps ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                >
                  {{ c.tiene_gps ? 'GPS' : 'PENDIENTE' }}
                </span>
              </div>
              <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ c.direccion }}</p>
              <div class="flex items-center space-x-2 mt-1 text-[10px] text-[#86868B] font-mono">
                <span class="flex items-center gap-1">
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                  {{ c.ruta }}
                </span>
                <span>•</span>
                <span class="font-medium" :style="{ color: getDiaColor(c.dia_norm) }">{{ c.dia_norm }}</span>
                <span>•</span>
                <span>ID: {{ c.cliente_id }}</span>
              </div>
            </button>
          </div>
        </div>

        <div class="flex-1 relative min-h-[350px] md:min-h-0 bg-[#E5E5EA]">
          <div id="map-ruteo" class="absolute inset-0 w-full h-full"></div>

          <div class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-400 text-[11px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Codificación</p>
            <div class="space-y-1">
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] border border-black/30"></span>
                <span class="text-[#1D1D1F]">Relleno = Día Semanal</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-white border-2 border-[#1D1D1F]"></span>
                <span class="text-[#1D1D1F]">Borde = Ruta Asignada</span>
              </div>
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

const diasSemana = [
  { key: 'LUNES', corto: 'Lun', label: 'Lunes', color: '#0071E3' },
  { key: 'MARTES', corto: 'Mar', label: 'Martes', color: '#34C759' },
  { key: 'MIERCOLES', corto: 'Mié', label: 'Miércoles', color: '#FF9500' },
  { key: 'JUEVES', corto: 'Jue', label: 'Jueves', color: '#AF52DE' },
  { key: 'VIERNES', corto: 'Vie', label: 'Viernes', color: '#FF2D55' },
  { key: 'SABADO', corto: 'Sáb', label: 'Sábado', color: '#5856D6' },
];

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

function getDiaColor(dia) {
  if (!dia) return '#8E8E93';
  const cleanDia = dia.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim();
  const found = diasSemana.find(d => d.key === cleanDia);
  return found ? found.color : '#8E8E93';
}

const filtroCanales = ref([]);
const filtroRutas = ref([]);
const filtroDias = ref([]);
const verFronteraRutas = ref(true);
const verFronteraDias = ref(true);
const cargando = ref(false);

const activeDropdown = ref(null);
const busquedaRuta = ref('');
const busquedaCliente = ref('');
const clienteActivoId = ref(null);

const clientes = ref([]);
const fronterasRutas = ref([]);
const fronterasDias = ref([]);
const kpis = ref({ total: 0, con_gps: 0, sin_gps: 0 });

let map = null;
let markersLayer = null;
let fronterasRutasLayer = null;
let fronterasDiasLayer = null;
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
  if (filtroCanales.value.length === 0) return 'Todos';
  if (filtroCanales.value.length === 1) return filtroCanales.value[0];
  return `${filtroCanales.value.length} seleccionados`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === 0) return 'Ninguna';
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 1) return filtroRutas.value[0];
  return `${filtroRutas.value.length} seleccionadas`;
});

const clientesFiltradosLista = computed(() => {
  if (!busquedaCliente.value.trim()) return clientes.value;
  const q = busquedaCliente.value.toLowerCase();
  return clientes.value.filter(c =>
    (c.nombre && c.nombre.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q))
  );
});

function toggleDropdown(name) {
  activeDropdown.value = activeDropdown.value === name ? null : name;
}

function onCanalesChange() {
  const validRutas = new Set(rutasDisponibles.value.map(r => r.ruta));
  filtroRutas.value = filtroRutas.value.filter(r => validRutas.has(r));
  cargarDatos();
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  onCanalesChange();
}

function limpiarCanales() {
  filtroCanales.value = [];
  onCanalesChange();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  cargarDatos();
}

function limpiarRutas() {
  filtroRutas.value = [];
  cargarDatos();
}

function toggleDia(diaKey) {
  const index = filtroDias.value.indexOf(diaKey);
  if (index > -1) {
    filtroDias.value.splice(index, 1);
  } else {
    filtroDias.value.push(diaKey);
  }
  cargarDatos();
}

function toggleTodosDias() {
  if (filtroDias.value.length === 6) {
    filtroDias.value = [];
  } else {
    filtroDias.value = diasSemana.map(d => d.key);
  }
  cargarDatos();
}

async function cargarDatos() {
  if (filtroRutas.value.length === 0 && filtroCanales.value.length === 0) {
    clientes.value = [];
    fronterasRutas.value = [];
    fronterasDias.value = [];
    kpis.value = { total: 0, con_gps: 0, sin_gps: 0 };
    renderizarEnMapa();
    return;
  }

  cargando.value = true;
  try {
    const res = await axios.post(route('supervisor.ruteo.data'), {
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
      dias: filtroDias.value,
      ver_frontera_rutas: verFronteraRutas.value,
      ver_frontera_dias: verFronteraDias.value,
    });

    clientes.value = res.data.clientes;
    fronterasRutas.value = res.data.fronteras_rutas;
    fronterasDias.value = res.data.fronteras_dias;
    kpis.value = res.data.kpis;

    renderizarEnMapa();
  } catch (error) {
    console.error('Error al cargar datos:', error);
  } finally {
    cargando.value = false;
  }
}

function initMap() {
  map = L.map('map-ruteo', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([-16.5000, -68.1500], 12);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  fronterasRutasLayer = L.featureGroup().addTo(map);
  fronterasDiasLayer = L.featureGroup().addTo(map);
  markersLayer = L.featureGroup().addTo(map);

  map.on('click', () => {
    activeDropdown.value = null;
  });
}

function renderizarEnMapa() {
  if (!map) return;

  markersLayer.clearLayers();
  fronterasRutasLayer.clearLayers();
  fronterasDiasLayer.clearLayers();
  markersMap.clear();

  const boundsList = [];

  if (verFronteraRutas.value && fronterasRutas.value.length > 0) {
    fronterasRutas.value.forEach(fr => {
      const rutaColor = getRutaColor(fr.ruta);
      const layer = L.geoJSON(fr.geojson, {
        style: {
          color: rutaColor,
          weight: 2.5,
          opacity: 0.85,
          fillColor: rutaColor,
          fillOpacity: 0.04,
        }
      }).addTo(fronterasRutasLayer);
      boundsList.push(layer.getBounds());
    });
  }

  if (verFronteraDias.value && fronterasDias.value.length > 0) {
    fronterasDias.value.forEach(fd => {
      const diaColor = getDiaColor(fd.dia);
      const layer = L.geoJSON(fd.geojson, {
        style: {
          color: diaColor,
          weight: 1.5,
          dashArray: '3, 4',
          opacity: 0.9,
          fillColor: diaColor,
          fillOpacity: 0.08,
        }
      }).addTo(fronterasDiasLayer);
      boundsList.push(layer.getBounds());
    });
  }

  const unicaRuta = filtroRutas.value.length === 1;

  clientes.value.forEach(c => {
    if (c.tiene_gps) {
      const fillColor = getDiaColor(c.dia_norm);
      const strokeColor = unicaRuta ? '#FFFFFF' : getRutaColor(c.ruta);

      const marker = L.circleMarker([c.latitud, c.longitud], {
        radius: 5,
        fillColor: fillColor,
        color: strokeColor,
        weight: unicaRuta ? 1.5 : 2.5,
        opacity: 1,
        fillOpacity: 0.95,
      });

      marker.bindPopup(`
        <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; line-height: 1.4; color: #1D1D1F; max-width: 220px;">
          <div style="font-weight: 600; font-size: 12px; margin-bottom: 2px;">${c.nombre}</div>
          <div style="color: #6E6E73; margin-bottom: 4px;">${c.direccion}</div>
          <div style="display: flex; gap: 4px; font-family: monospace; font-size: 10px; border-top: 1px solid #E5E5EA; padding-top: 4px;">
            <span style="font-weight: bold; color: ${getRutaColor(c.ruta)}">${c.ruta}</span>
            <span>•</span>
            <span style="font-weight: bold; color: ${fillColor}">${c.dia_norm}</span>
            <span>•</span>
            <span>ID: ${c.cliente_id}</span>
          </div>
        </div>
      `);

      marker.addTo(markersLayer);
      markersMap.set(c.id, marker);
      boundsList.push(L.latLng(c.latitud, c.longitud));
    }
  });

  if (boundsList.length > 0) {
    try {
      const groupBounds = L.featureGroup([fronterasRutasLayer, fronterasDiasLayer, markersLayer]).getBounds();
      if (groupBounds.isValid()) {
        map.fitBounds(groupBounds, { padding: [35, 35], maxZoom: 16 });
      }
    } catch (e) {
      // Ignorar bounds inválidos
    }
  }
}

function enfocarCliente(c) {
  clienteActivoId.value = c.id;
  if (!c.tiene_gps || !map) return;

  map.flyTo([c.latitud, c.longitud], 17, { duration: 0.7 });
  const marker = markersMap.get(c.id);
  if (marker) {
    marker.openPopup();
  }
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