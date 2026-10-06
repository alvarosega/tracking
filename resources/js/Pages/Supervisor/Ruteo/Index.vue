<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900 text-slate-900 font-sans">
      <!-- Barra Superior de Filtros y Capas (Con Backdrop Invisible para cerrar dropdowns) -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 flex flex-wrap items-center justify-between gap-2.5 shrink-0 relative z-30">
        
        <!-- Backdrop invisible para cerrar menú al hacer click afuera -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <div class="flex flex-wrap items-center gap-2">
          
          <!-- Filtro Dropdown: Canales (Teleport al Body para estar encima de todo) -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none"
          >
            <span class="text-slate-400 font-normal">Canal:</span>
            <span class="font-semibold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Rutas (Teleport al Body) -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none"
          >
            <span class="text-slate-400 font-normal">Rutas:</span>
            <span class="font-semibold text-slate-900">{{ labelRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Segmentado: Días de Visita -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80">
            <button
              v-for="d in diasSemana"
              :key="d.key"
              type="button"
              @click="toggleDia(d.key)"
              class="px-2 py-1 text-[11px] font-medium rounded-md transition-all flex items-center gap-1 cursor-pointer select-none"
              :class="filtroDias.includes(d.key) ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
              :title="d.label"
            >
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: d.color }"></span>
              {{ d.corto }}
            </button>
            <button type="button" @click="toggleTodosDias" class="px-1.5 py-1 text-[10px] text-slate-500 hover:text-slate-900 font-mono border-l border-slate-200 ml-0.5 cursor-pointer">
              {{ filtroDias.length === 6 ? 'Reset' : 'Todos' }}
            </button>
          </div>

          <!-- Checkboxes de Fronteras GIS -->
          <div class="hidden lg:flex items-center space-x-3 text-xs pl-2 border-l border-slate-200 font-medium text-slate-700">
            <label class="flex items-center gap-1.5 cursor-pointer select-none">
              <input type="checkbox" v-model="verFronteraRutas" @change="cargarDatos" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span>Polígono Rutas</span>
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer select-none">
              <input type="checkbox" v-model="verFronteraDias" @change="cargarDatos" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span>Polígono Días</span>
            </label>
          </div>
        </div>

        <!-- Indicador de Carga, Selector Móvil y KPIs -->
        <div class="flex items-center space-x-2 text-xs font-mono">
          <!-- Control Segmentado para Móviles -->
          <div class="flex md:hidden items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-500'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'lista'"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="vistaMovil === 'lista' ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-500'"
            >
              Lista ({{ clientes.length }})
            </button>
          </div>

          <!-- Spinner cargando -->
          <span v-if="cargando" class="text-[11px] text-slate-500 hidden sm:flex items-center gap-1.5 animate-pulse mr-1">
            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
            Consultando...
          </span>

          <div class="px-2 py-0.5 bg-slate-50 border border-slate-200 rounded-md flex items-center gap-1.5 shadow-2xs">
            <span class="text-slate-400">Total:</span>
            <span class="font-bold text-slate-900 tabular-nums">{{ kpis.total }}</span>
          </div>

          <div class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span class="font-semibold tabular-nums">{{ kpis.con_gps }} GPS</span>
          </div>

          <div v-if="kpis.sin_gps > 0" class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-md flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            <span class="font-semibold tabular-nums">{{ kpis.sin_gps }} sin GPS</span>
          </div>
        </div>
      </div>

      <!-- Menús Desplegables Flotantes con Teleport (Z-INDEX 99999 absoluto sobre el mapa) -->
      <Teleport to="body">
        <!-- Menú Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-56 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarCanales" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <div class="max-h-48 overflow-y-auto space-y-1">
            <label v-for="c in canales" :key="c" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="c" v-model="filtroCanales" @change="onCanalesChange" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
              <span class="truncate font-medium text-slate-700">{{ c }}</span>
            </label>
          </div>
        </div>

        <!-- Menú Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todas ({{ rutasDisponibles.length }})</button>
            <button type="button" @click="limpiarRutas" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta o preventa..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="r in rutasFiltradasEnDropdown" :key="r.ruta" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="r.ruta" v-model="filtroRutas" @change="cargarDatos" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <div class="truncate flex items-center gap-2 flex-1">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-mono font-semibold text-slate-800">{{ formatRuta(r.ruta) }}</span>
                <span class="text-slate-400 text-[10px]">({{ r.canal }})</span>
              </div>
            </label>
          </div>
        </div>
      </Teleport>

      <!-- Contenedor Principal: Split Screen (Lista de Clientes + Mapa Leaflet) -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative">
        
        <!-- Panel Lateral: Lista de Clientes -->
        <div
          class="w-full md:w-[350px] bg-white border-r border-slate-200 flex flex-col shrink-0 transition-all z-20"
          :class="vistaMovil === 'lista' ? 'flex' : 'hidden md:flex'"
        >
          <!-- Buscador de Clientes -->
          <div class="p-2.5 border-b border-slate-100 bg-slate-50/60 flex items-center space-x-2">
            <div class="relative flex-1">
              <input
                v-model="busquedaCliente"
                type="text"
                placeholder="Filtrar por nombre o ID..."
                class="w-full h-8 pl-8 pr-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-slate-400 transition-all shadow-2xs"
              />
              <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </div>
          </div>

          <!-- Lista Scrollable de Clientes -->
          <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
            <div v-if="clientes.length === 0 && !cargando" class="p-8 text-center text-xs text-slate-400 space-y-2">
              <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
                </svg>
              </div>
              <p class="font-medium text-slate-600">Sin datos de ruteo seleccionados</p>
              <p class="text-[11px] text-slate-400">Selecciona al menos un canal o ruta comercial arriba para proyectar los puntos en El Alto.</p>
            </div>

            <button
              v-for="c in clientesFiltradosLista"
              :key="c.id"
              type="button"
              @click="enfocarCliente(c)"
              class="w-full text-left p-3 hover:bg-slate-50 transition-colors focus:outline-none group cursor-pointer"
              :class="clienteActivoId === c.id ? 'bg-sky-50/70 border-l-2 border-sky-600' : ''"
            >
              <div class="flex items-start justify-between gap-2">
                <span class="text-xs font-semibold text-slate-900 group-hover:text-sky-600 transition-colors line-clamp-1">
                  {{ c.nombre }}
                </span>
                <span
                  class="text-[9px] font-mono px-1.5 py-0.2 rounded shrink-0 border"
                  :class="c.tiene_gps ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                >
                  {{ c.tiene_gps ? 'GPS' : 'SIN GPS' }}
                </span>
              </div>
              <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ c.direccion }}</p>
              <div class="flex items-center space-x-2 mt-1.5 text-[10px] text-slate-400 font-mono">
                <span class="flex items-center gap-1 font-medium text-slate-700">
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                  {{ formatRuta(c.ruta) }}
                </span>
                <span>•</span>
                <span class="font-semibold" :style="{ color: getDiaColor(c.dia_norm) }">{{ c.dia_norm || 'Sin Día' }}</span>
                <span>•</span>
                <span>ID: {{ c.cliente_id }}</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Panel del Mapa Leaflet -->
        <div
          class="flex-1 relative min-h-[350px] md:min-h-0 bg-slate-950"
          :class="vistaMovil === 'mapa' ? 'block' : 'hidden md:block'"
        >
          <div id="map-ruteo" class="absolute inset-0 w-full h-full z-10"></div>

          <!-- Selector de Capas de Mapa (Minimalist Tile Switcher Libre de API Key) -->
          <div class="absolute top-3 left-3 z-30 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-1 shadow-md flex items-center space-x-1 text-xs">
            <button
              v-for="(layer, key) in mapsConfig.tileLayers"
              :key="key"
              type="button"
              @click="cambiarCapaMapa(key)"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === key ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              {{ layer.name }}
            </button>
          </div>

          <!-- Leyenda Flotante Minimalista -->
          <div class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-3 shadow-lg z-30 text-xs">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono mb-2">Convención Visual</p>
            <div class="space-y-1.5 text-[11px]">
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-sky-500 border border-slate-300"></span>
                <span class="text-slate-700 font-medium">Color Relleno = Día de Visita</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-white border-2 border-slate-900"></span>
                <span class="text-slate-700 font-medium">Borde = Ruta Asignada</span>
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
import { mapsConfig } from '@/Config/maps';
import { getCanalColor, getRutaColor, formatRuta, getDiaColor, DIAS_SEMANA_CONFIG } from '@/Config/colors';
import L from 'leaflet';

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => [],
  },
  canales: {
    type: Array,
    default: () => [],
  },
});

const diasSemana = DIAS_SEMANA_CONFIG.filter(d => d.key !== 'DOMINGO');

const filtroCanales = ref([]);
const filtroRutas = ref([]);
const filtroDias = ref([]);
const verFronteraRutas = ref(true);
const verFronteraDias = ref(true);
const cargando = ref(false);
const vistaMovil = ref('mapa');
const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap');

const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const busquedaRuta = ref('');
const busquedaCliente = ref('');
const clienteActivoId = ref(null);

const clientes = ref([]);
const fronterasRutas = ref([]);
const fronterasDias = ref([]);
const kpis = ref({ total: 0, con_gps: 0, sin_gps: 0 });

let map = null;
let currentTileLayer = null;
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
    (r.ruta && r.ruta.toLowerCase().includes(q)) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === 0) return 'Todos';
  if (filtroCanales.value.length === 1) return filtroCanales.value[0];
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === 0) return 'Ninguna';
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 1) return formatRuta(filtroRutas.value[0]);
  return `${filtroRutas.value.length} sel.`;
});

const clientesFiltradosLista = computed(() => {
  if (!busquedaCliente.value.trim()) return clientes.value;
  const q = busquedaCliente.value.toLowerCase();
  return clientes.value.filter(c =>
    (c.nombre && c.nombre.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q))
  );
});

function toggleMenu(nombre, e) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = e.currentTarget.getBoundingClientRect();
  posicionMenu.value = {
    top: rect.bottom + 6,
    left: Math.min(rect.left, window.innerWidth - 270),
  };
  menuAbierto.value = nombre;
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

    clientes.value = res.data.clientes || [];
    fronterasRutas.value = res.data.fronteras_rutas || [];
    fronterasDias.value = res.data.fronteras_dias || [];
    kpis.value = res.data.kpis || { total: 0, con_gps: 0, sin_gps: 0 };

    renderizarEnMapa();
  } catch (error) {
    console.error('Error al consultar datos de ruteo:', error);
  } finally {
    cargando.value = false;
  }
}

function cambiarCapaMapa(nombreCapa) {
  if (!map || !mapsConfig.tileLayers[nombreCapa]) return;
  capaActual.value = nombreCapa;

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer);
  }

  const layerConf = mapsConfig.tileLayers[nombreCapa];
  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map);
}

function initMap() {
  const defaultLoc = mapsConfig.defaultLocation;

  map = L.map('map-ruteo', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);

  cambiarCapaMapa(capaActual.value);

  fronterasRutasLayer = L.featureGroup().addTo(map);
  fronterasDiasLayer = L.featureGroup().addTo(map);
  markersLayer = L.featureGroup().addTo(map);

  map.on('click', () => {
    menuAbierto.value = null;
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
          weight: 2,
          opacity: 0.85,
          fillColor: rutaColor,
          fillOpacity: 0.05,
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
          dashArray: '4, 4',
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
        <div style="font-family: inherit; font-size: 11px; line-height: 1.4; color: #0F172A; max-width: 220px;">
          <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">${c.nombre}</div>
          <div style="color: #64748B; margin-bottom: 5px;">${c.direccion}</div>
          <div style="display: flex; gap: 5px; font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 4px;">
            <span style="font-weight: bold; color: ${getRutaColor(c.ruta)}">${formatRuta(c.ruta)}</span>
            <span>•</span>
            <span style="font-weight: bold; color: ${fillColor}">${c.dia_norm || 'S/D'}</span>
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
        map.fitBounds(groupBounds, { padding: [30, 30], maxZoom: 16 });
      }
    } catch (e) {
      // Fallback
    }
  }
}

function enfocarCliente(c) {
  clienteActivoId.value = c.id;
  if (!c.tiene_gps || !map) return;

  vistaMovil.value = 'mapa';
  map.flyTo([c.latitud, c.longitud], 17, { duration: 0.6 });
  const marker = markersMap.get(c.id);
  if (marker) {
    marker.openPopup();
  }
}

onMounted(() => {
  initMap();
});
</script>