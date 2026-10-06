<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-slate-900 font-sans text-slate-900">
      <!-- Barra Superior de Filtros Compacta -->
      <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop invisible -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Scroll Horizontal de Filtros -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          <!-- Selector de Fecha de Preventa -->
          <div class="flex items-center bg-slate-50 p-1 rounded-lg border border-slate-200/90 shrink-0 h-8">
            <span class="text-[11px] text-slate-400 font-mono px-1.5">Fecha:</span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarPreventas"
              class="h-6 px-1.5 text-xs font-mono font-semibold bg-white text-slate-900 border border-slate-200 rounded-md focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Botón Ubicación GPS -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-3 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-semibold text-slate-900 flex items-center gap-1.5 transition-all disabled:opacity-50 cursor-pointer shrink-0 shadow-2xs"
            title="Ubicarme para auditar entregas a mi alrededor"
          >
            <span
              class="w-2 h-2 rounded-full"
              :class="obteniendoGps ? 'bg-amber-500 animate-ping' : (ubicacionSupervisor ? 'bg-emerald-500' : 'bg-sky-500')"
            ></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : (ubicacionSupervisor ? 'Ubicado' : 'Mi Ubicación') }}</span>
          </button>

          <!-- Halo Proximidad -->
          <div v-if="ubicacionSupervisor" class="flex items-center bg-slate-50 p-0.5 rounded-lg border border-slate-200/90 shrink-0 h-8">
            <span class="text-[10px] text-slate-400 font-mono px-1.5">Halo:</span>
            <button
              v-for="r in [150, 300, 500, 1000]"
              :key="r"
              type="button"
              @click="seleccionarRadio(r)"
              class="px-2 py-0.5 text-[11px] font-mono font-medium rounded-md transition-all cursor-pointer"
              :class="radioSupervisor === r ? 'bg-slate-900 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
            >
              {{ r }}m
            </button>
            <button
              type="button"
              @click="limpiarUbicacion"
              class="px-1.5 py-0.5 text-[10px] text-rose-500 hover:underline font-mono border-l border-slate-200 ml-0.5 cursor-pointer"
              title="Quitar punto GPS"
            >
              ✕
            </button>
          </div>

          <!-- Botón Disparador: Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Canal:</span>
            <span class="font-semibold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Botón Disparador: Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Rutas:</span>
            <span class="font-semibold text-slate-900">{{ labelRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
        </div>

        <!-- Indicador de Carga, Control Móvil y Métricas -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-slate-500 hidden sm:flex items-center gap-1.5 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
            Cargando...
          </span>

          <!-- Control Móvil -->
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
              Lista ({{ clientesFiltrados.length }})
            </button>
          </div>

          <!-- KPI Total Preventas -->
          <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg shadow-2xs">
            <span class="text-slate-400">Total:</span>
            <span class="font-bold text-slate-900 tabular-nums">Bs. {{ kpis.monto_total.toFixed(2) }}</span>
            <span class="text-slate-400 font-normal">({{ kpis.total_preventas }} prev.)</span>
          </div>
        </div>
      </header>

      <!-- Menús Desplegables Flotantes -->
      <Teleport to="body">
        <!-- Menú: Canales -->
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
              <span class="truncate font-medium text-slate-700">{{ c }}</span>
            </label>
          </div>
        </div>

        <!-- Menú: Rutas -->
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
            placeholder="Buscar ruta..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="r in rutasFiltradasEnDropdown" :key="r.ruta" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="r.ruta" v-model="filtroRutas" @change="cargarPreventas" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <div class="truncate flex items-center gap-2 flex-1">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-mono font-semibold text-slate-800">{{ r.ruta }}</span>
                <span class="text-slate-400 text-[10px]">({{ r.canal }})</span>
              </div>
            </label>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Lista de Clientes con Preventas -->
        <aside
          class="w-full md:w-[410px] bg-white border-r border-slate-200 flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- Buscador y Filtro Rápido -->
          <div class="p-2.5 border-b border-slate-100 bg-slate-50/60 flex items-center space-x-2">
            <div class="relative flex-1">
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Filtrar cliente, ruta o preventa..."
                class="w-full h-8 pl-8 pr-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-slate-400 transition-all shadow-2xs font-mono"
              />
              <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </div>
          </div>

          <!-- Listado de Preventas -->
          <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
            <div v-if="clientesFiltrados.length === 0 && !cargando" class="p-8 text-center text-xs text-slate-400 space-y-2">
              <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <p class="font-semibold text-slate-700">Sin preventas para esta fecha</p>
              <p class="text-[11px] text-slate-400">No se encontraron pedidos de preventa con los filtros seleccionados.</p>
            </div>

            <div
              v-for="c in clientesFiltrados"
              :key="c.cliente_id"
              class="p-3 hover:bg-slate-50 transition-colors border-l-2"
              :class="clienteActivoId === c.cliente_id ? 'border-sky-600 bg-sky-50/50' : 'border-transparent'"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <button
                    type="button"
                    @click="enfocarCliente(c)"
                    class="text-xs font-bold text-slate-900 hover:text-sky-600 text-left line-clamp-1 cursor-pointer transition-colors"
                  >
                    {{ c.cliente }}
                  </button>
                  <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ c.direccion }}</p>
                </div>

                <div class="text-right shrink-0">
                  <span class="text-xs font-bold font-mono text-slate-900 tabular-nums">
                    Bs. {{ c.total_monto.toFixed(2) }}
                  </span>
                  <div class="text-[10px] text-slate-400 font-mono">
                    {{ c.pedidos.length }} pedido(s)
                  </div>
                </div>
              </div>

              <!-- Metadatos de Ruta y Distancia -->
              <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[10px] font-mono text-slate-500">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                  <span class="font-semibold text-slate-800">{{ c.ruta }}</span>
                  <span>•</span>
                  <span>ID: {{ c.cliente_id }}</span>
                </div>

                <div class="flex items-center gap-1.5">
                  <span v-if="c.distancia_metros !== null" class="font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                    {{ c.distancia_metros }} m
                  </span>
                  <button
                    type="button"
                    @click="toggleExpandirPedidos(c.cliente_id)"
                    class="text-sky-600 font-semibold hover:underline cursor-pointer ml-1"
                  >
                    {{ pedidosExpandidos.has(c.cliente_id) ? 'Ocultar' : 'Ver Detalle' }}
                  </button>
                </div>
              </div>

              <!-- Desglose de Pedidos & Ítems -->
              <div v-if="pedidosExpandidos.has(c.cliente_id)" class="mt-2.5 pt-2 border-t border-dashed border-slate-200 space-y-2">
                <div
                  v-for="p in c.pedidos"
                  :key="p.nro_preventa"
                  class="bg-slate-50/80 rounded-lg p-2 text-[11px] border border-slate-200/80 font-mono"
                >
                  <div class="flex items-center justify-between font-bold text-slate-800 pb-1 border-b border-slate-200/60">
                    <span>Preventa #{{ p.nro_preventa }}</span>
                    <span>Bs. {{ p.monto_pedido.toFixed(2) }}</span>
                  </div>
                  <div class="mt-1 space-y-1 text-[10px] text-slate-600">
                    <div v-for="item in p.items" :key="item.id" class="flex items-center justify-between">
                      <span class="truncate pr-2">{{ item.cantidad }}x {{ item.producto }}</span>
                      <span class="tabular-nums shrink-0">Bs. {{ item.monto_final.toFixed(2) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </aside>

        <!-- Panel del Mapa Leaflet -->
        <div
          class="flex-1 relative min-h-[350px] md:min-h-0 bg-slate-950"
          :class="vistaMovil === 'mapa' ? 'block' : 'hidden md:block'"
        >
          <div id="map-preventas" class="absolute inset-0 w-full h-full z-10"></div>

          <!-- Selector de Capas de Mapa -->
          <div class="absolute top-3 left-3 z-400 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-1 shadow-md flex items-center space-x-1 text-xs">
            <button
              type="button"
              @click="cambiarCapaMapa('minimalLight')"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === 'minimalLight' ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Minimal
            </button>
            <button
              type="button"
              @click="cambiarCapaMapa('openStreetMap')"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === 'openStreetMap' ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Calles
            </button>
            <button
              type="button"
              @click="cambiarCapaMapa('satellite')"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === 'satellite' ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Satélite
            </button>
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
  fecha_default: {
    type: String,
    default: () => new Date().toISOString().split('T')[0],
  },
});

const RUTA_PALETTE = ['#0F172A', '#0284C7', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6'];
const rutaColorCache = new Map();

function getRutaColor(ruta) {
  if (!ruta) return '#0F172A';
  if (!rutaColorCache.has(ruta)) {
    const idx = rutaColorCache.size % RUTA_PALETTE.length;
    rutaColorCache.set(ruta, RUTA_PALETTE[idx]);
  }
  return rutaColorCache.get(ruta);
}

// Estados
const filtroFecha = ref(props.fecha_default);
const filtroCanales = ref([]);
const filtroRutas = ref([]);
const busquedaRuta = ref('');
const busquedaTexto = ref('');

const ubicacionSupervisor = ref(null);
const radioSupervisor = ref(300);
const obteniendoGps = ref(false);
const cargando = ref(false);
const vistaMovil = ref('mapa');
const capaActual = ref('minimalLight');

const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });

const clientesPreventa = ref([]);
const clienteActivoId = ref(null);
const pedidosExpandidos = ref(new Set());
const kpis = ref({
  total_preventas: 0,
  total_clientes: 0,
  monto_total: 0.0,
  con_gps: 0,
  sin_gps: 0,
  en_radio_conteo: 0,
  en_radio_monto: 0.0,
});

let map = null;
let currentTileLayer = null;
let supervisorMarker = null;
let supervisorCircle = null;
let markersLayer = null;
const clientMarkersMap = new Map();

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
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === 0) return 'Todas';
  if (filtroRutas.value.length === 1) return filtroRutas.value[0];
  return `${filtroRutas.value.length} sel.`;
});

const clientesFiltrados = computed(() => {
  if (!busquedaTexto.value.trim()) return clientesPreventa.value;
  const q = busquedaTexto.value.toLowerCase();
  return clientesPreventa.value.filter(c =>
    (c.cliente && c.cliente.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q)) ||
    (c.pedidos && c.pedidos.some(p => String(p.nro_preventa).includes(q)))
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
  cargarPreventas();
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
  cargarPreventas();
}

function limpiarRutas() {
  filtroRutas.value = [];
  cargarPreventas();
}

function toggleExpandirPedidos(clienteId) {
  if (pedidosExpandidos.value.has(clienteId)) {
    pedidosExpandidos.value.delete(clienteId);
  } else {
    pedidosExpandidos.value.add(clienteId);
  }
}

function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Geolocalización no soportada');
    return;
  }
  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      obteniendoGps.value = false;
      ubicacionSupervisor.value = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
      };
      if (map) map.flyTo([pos.coords.latitude, pos.coords.longitude], 16);
      cargarPreventas();
    },
    (err) => {
      obteniendoGps.value = false;
      alert('Error GPS: ' + err.message);
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

function seleccionarRadio(r) {
  radioSupervisor.value = r;
  cargarPreventas();
}

function limpiarUbicacion() {
  ubicacionSupervisor.value = null;
  cargarPreventas();
}

async function cargarPreventas() {
  cargando.value = true;
  try {
    const payload = {
      fecha: filtroFecha.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
      latitud: ubicacionSupervisor.value ? ubicacionSupervisor.value.lat : null,
      longitud: ubicacionSupervisor.value ? ubicacionSupervisor.value.lng : null,
      radio: radioSupervisor.value,
    };

    const res = await axios.post(route('supervisor.ruteo.preventas.data'), payload);
    clientesPreventa.value = res.data.clientes || [];
    kpis.value = res.data.kpis || kpis.value;

    renderizarEnMapa();
  } catch (error) {
    console.error('Error al cargar preventas:', error);
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

  map = L.map('map-preventas', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);
  cambiarCapaMapa('minimalLight');

  markersLayer = L.featureGroup().addTo(map);

  map.on('click', () => {
    menuAbierto.value = null;
  });
}

function renderizarEnMapa() {
  if (!map) return;

  markersLayer.clearLayers();
  clientMarkersMap.clear();

  if (supervisorMarker) map.removeLayer(supervisorMarker);
  if (supervisorCircle) map.removeLayer(supervisorCircle);

  // Halo del supervisor
  if (ubicacionSupervisor.value) {
    const sPos = [ubicacionSupervisor.value.lat, ubicacionSupervisor.value.lng];
    supervisorMarker = L.circleMarker(sPos, {
      radius: 7,
      fillColor: '#0284C7',
      color: '#FFFFFF',
      weight: 2,
      opacity: 1,
      fillOpacity: 1,
    }).addTo(map);

    supervisorCircle = L.circle(sPos, {
      radius: radioSupervisor.value,
      color: '#0284C7',
      fillColor: '#0284C7',
      fillOpacity: 0.08,
      weight: 1.5,
      dashArray: '4, 6',
    }).addTo(map);
  }

  const boundsList = [];

  clientesPreventa.value.forEach(c => {
    if (c.tiene_gps) {
      const isEnRadio = c.en_radio;
      const marker = L.circleMarker([c.latitud, c.longitud], {
        radius: isEnRadio ? 8 : 5,
        fillColor: isEnRadio ? '#10B981' : getRutaColor(c.ruta),
        color: '#FFFFFF',
        weight: isEnRadio ? 2.5 : 1.5,
        opacity: 1,
        fillOpacity: 0.95,
      });

      marker.bindPopup(`
        <div style="font-family: inherit; font-size: 11px; line-height: 1.4; color: #0F172A; max-width: 220px;">
          <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">${c.cliente}</div>
          <div style="color: #64748B; margin-bottom: 4px;">${c.direccion}</div>
          <div style="font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 4px; display: flex; justify-content: space-between;">
            <span>Ruta: <b>${c.ruta}</b></span>
            <span style="font-weight: bold; color: #0F172A;">Bs. ${c.total_monto.toFixed(2)}</span>
          </div>
        </div>
      `);

      marker.addTo(markersLayer);
      clientMarkersMap.set(c.cliente_id, marker);
      boundsList.push(L.latLng(c.latitud, c.longitud));
    }
  });

  if (boundsList.length > 0 && !ubicacionSupervisor.value) {
    try {
      map.fitBounds(L.featureGroup([markersLayer]).getBounds(), { padding: [30, 30], maxZoom: 16 });
    } catch {
      // Fallback
    }
  }
}

function enfocarCliente(c) {
  clienteActivoId.value = c.cliente_id;
  if (!c.tiene_gps || !map) return;

  vistaMovil.value = 'mapa';
  map.flyTo([c.latitud, c.longitud], 17, { duration: 0.6 });
  const m = clientMarkersMap.get(c.cliente_id);
  if (m) m.openPopup();
}

onMounted(() => {
  initMap();
  cargarPreventas();
});
</script>