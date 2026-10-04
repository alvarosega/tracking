<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7]">
      <!-- Barra Superior de Filtros (sin overflow-hidden para permitir despliegue de popups) -->
      <header class="bg-white border-b border-[#E5E5EA] px-4 py-2 flex flex-wrap items-center justify-between gap-3 shrink-0 relative z-[1001]">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Selector de Fecha de Preventa (Ultima fecha con datos por defecto) -->
          <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <span class="text-[10px] text-[#86868B] font-mono px-1.5">Fecha:</span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarPreventas"
              class="h-6 px-1.5 text-xs font-mono font-semibold bg-white text-[#1D1D1F] border-0 rounded-[4px] focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Botón Ubicación GPS -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-7 px-2.5 bg-[#F2F2F7] hover:bg-[#E5E5EA] border border-[#E5E5EA] rounded-[6px] text-xs font-semibold text-[#1D1D1F] flex items-center gap-1.5 transition-colors disabled:opacity-50 cursor-pointer"
            title="Ubicarme para ver entregas a mi alrededor"
          >
            <span
              class="w-2 h-2 rounded-full"
              :class="obteniendoGps ? 'bg-[#FF9500] animate-ping' : (ubicacionSupervisor ? 'bg-[#248A3D]' : 'bg-[#0071E3]')"
            ></span>
            <span>{{ obteniendoGps ? 'Localizando...' : (ubicacionSupervisor ? 'Ubicado' : 'Mi Ubicación') }}</span>
          </button>

          <!-- Selector de Radio de Auditoría -->
          <div v-if="ubicacionSupervisor" class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <span class="text-[10px] text-[#86868B] font-mono px-1.5">Halo:</span>
            <button
              v-for="r in [150, 300, 500, 1000]"
              :key="r"
              type="button"
              @click="seleccionarRadio(r)"
              class="px-1.5 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer"
              :class="radioSupervisor === r ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              {{ r }}m
            </button>
            <button
              type="button"
              @click="limpiarUbicacion"
              class="px-1.5 py-0.5 text-[10px] text-[#FF3B30] hover:underline font-mono border-l border-[#E5E5EA] ml-0.5 cursor-pointer"
              title="Quitar punto de ubicación"
            >
              ✕
            </button>
          </div>

          <!-- Filtro Dropdown: Canales -->
          <div class="relative dropdown-root">
            <button
              type="button"
              @click.stop="toggleMenu('canales')"
              class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <span>Canal:</span>
              <span class="font-semibold">{{ labelCanales }}</span>
              <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Menú Flotante Canales -->
            <div
              v-show="menuAbierto === 'canales'"
              @click.stop
              class="absolute left-0 top-full mt-1 w-52 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[100] text-xs"
            >
              <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
                <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">
                  Todos
                </button>
                <button type="button" @click="limpiarCanales" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">
                  Ninguno
                </button>
              </div>
              <div class="max-h-48 overflow-y-auto space-y-1">
                <label
                  v-for="c in canales"
                  :key="c"
                  class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
                >
                  <input
                    type="checkbox"
                    :value="c"
                    v-model="filtroCanales"
                    @change="onCanalesModificados"
                    class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
                  />
                  <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
                  <span class="truncate">{{ c }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Filtro Dropdown: Rutas -->
          <div class="relative dropdown-root">
            <button
              type="button"
              @click.stop="toggleMenu('rutas')"
              class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <span>Rutas:</span>
              <span class="font-semibold">{{ labelRutas }}</span>
              <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Menú Flotante Rutas -->
            <div
              v-show="menuAbierto === 'rutas'"
              @click.stop
              class="absolute left-0 top-full mt-1 w-64 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[100] text-xs"
            >
              <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
                <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">
                  Todas ({{ rutasDisponibles.length }})
                </button>
                <button type="button" @click="limpiarRutas" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">
                  Ninguna
                </button>
              </div>

              <input
                v-model="busquedaRuta"
                type="text"
                placeholder="Filtrar ruta..."
                class="w-full h-6 px-2 mb-1.5 text-xs bg-[#FBFBFD] border border-[#E5E5EA] rounded-[4px] focus:outline-none focus:border-[#86868B]"
              />

              <div class="max-h-56 overflow-y-auto space-y-1">
                <div v-if="rutasFiltradasEnDropdown.length === 0" class="text-center text-[11px] text-[#86868B] py-2">
                  No hay rutas disponibles
                </div>
                <label
                  v-for="r in rutasFiltradasEnDropdown"
                  :key="r.ruta"
                  class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
                >
                  <input
                    type="checkbox"
                    :value="r.ruta"
                    v-model="filtroRutas"
                    @change="cargarPreventas"
                    class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
                  />
                  <div class="truncate flex items-center gap-1.5">
                    <span
                      class="w-2.5 h-2.5 rounded-full shrink-0 border border-black/20"
                      :style="{ backgroundColor: getRutaColor(r.ruta) }"
                    ></span>
                    <span class="font-medium">{{ r.ruta }}</span>
                    <span class="text-[#86868B] text-[10px]">({{ r.canal }})</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- KPIs y Métricas en Línea -->
        <div class="flex items-center space-x-2 text-xs font-mono">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Cargando...
          </span>

          <div
            v-if="ubicacionSupervisor && kpis.en_radio_conteo > 0"
            class="px-2 py-0.5 bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
            <span class="font-bold tabular-nums">En radio: {{ kpis.en_radio_conteo }} (Bs. {{ kpis.en_radio_monto.toFixed(2) }})</span>
          </div>

          <div class="px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1">
            <span class="text-[#86868B]">Preventas:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.total_preventas }}</span>
          </div>

          <div class="px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] flex items-center gap-1">
            <span class="text-[#86868B]">Monto:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">Bs. {{ kpis.monto_total.toFixed(2) }}</span>
          </div>

          <div v-if="kpis.sin_gps > 0" class="px-2 py-0.5 bg-[#FFF5E5] text-[#B25E00] border border-[#E5E5EA] rounded-[6px]">
            <span class="tabular-nums">{{ kpis.sin_gps }} sin GPS</span>
          </div>
        </div>
      </header>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative">
        <!-- Panel Izquierdo: Lista de Preventas / Entregas -->
        <aside class="w-full md:w-[420px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20">
          <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center gap-2">
            <input
              v-model="busquedaTexto"
              type="text"
              placeholder="Buscar cliente, pedido o producto..."
              class="flex-1 h-7 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#86868B]"
            />
            <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA] shrink-0">
              <button
                type="button"
                @click="pestañaLista = 'gps'"
                class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer"
                :class="pestañaLista === 'gps' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73]'"
              >
                Con GPS ({{ clientesConGps.length }})
              </button>
              <button
                type="button"
                @click="pestañaLista = 'sin_gps'"
                class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer"
                :class="pestañaLista === 'sin_gps' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73]'"
              >
                Sin GPS ({{ clientesSinGps.length }})
              </button>
            </div>
          </div>

          <!-- Listado Scrollable de Entregas -->
          <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
            <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
              Consultando preventas de la fecha...
            </div>

            <div v-else-if="clientesFiltrados.length === 0" class="p-8 text-center text-xs text-[#86868B]">
              No se encontraron preventas para el día y rutas seleccionadas.
            </div>

            <div
              v-for="c in clientesFiltrados"
              :key="c.cliente_id"
              class="p-3 hover:bg-[#FBFBFD] transition-colors"
              :class="clienteActivoId === c.cliente_id ? 'bg-[#F2F2F7]' : (c.en_radio ? 'bg-[#EBF9EF]/30' : '')"
            >
              <div class="flex items-start justify-between gap-1.5 cursor-pointer" @click="enfocarEnMapa(c)">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1">{{ c.cliente }}</span>
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white shrink-0"
                      :style="{ backgroundColor: getCanalColor(rutaToCanalMap[c.ruta]) }"
                    >
                      {{ rutaToCanalMap[c.ruta] || 'PRT' }}
                    </span>
                  </div>
                  <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ c.direccion }}</p>
                </div>

                <div class="text-right shrink-0">
                  <span class="text-xs font-bold font-mono text-[#1D1D1F] tabular-nums block">
                    Bs. {{ c.total_monto.toFixed(2) }}
                  </span>
                  <span
                    v-if="c.distancia_metros !== null"
                    class="text-[10px] font-mono font-semibold px-1 py-0.2 rounded inline-block"
                    :class="c.en_radio ? 'bg-[#EBF9EF] text-[#248A3D]' : 'text-[#86868B]'"
                  >
                    a {{ c.distancia_metros }} m
                  </span>
                </div>
              </div>

              <!-- Metadatos de la Entrega -->
              <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-[#E5E5EA]/70 text-[10px] font-mono text-[#86868B]">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                  <span class="text-[#1D1D1F] font-medium">{{ c.ruta }}</span>
                  <span>•</span>
                  <span>{{ c.pedidos.length }} pedido(s)</span>
                </div>
                <button
                  type="button"
                  @click="toggleDetalleCliente(c.cliente_id)"
                  class="text-[#0071E3] hover:underline font-sans text-[11px] font-medium cursor-pointer"
                >
                  {{ clienteDetalleAbiertoId === c.cliente_id ? 'Ocultar ítems ▲' : 'Ver productos ▼' }}
                </button>
              </div>

              <!-- Acordeón Desplegable: Pedidos y Productos -->
              <div v-if="clienteDetalleAbiertoId === c.cliente_id" class="mt-2 space-y-2 border-t border-[#E5E5EA] pt-2">
                <div
                  v-for="p in c.pedidos"
                  :key="p.nro_preventa"
                  class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] p-2"
                >
                  <div class="flex items-center justify-between text-[11px] font-mono pb-1 border-b border-[#E5E5EA]/60 mb-1">
                    <span class="font-bold text-[#0071E3]">Preventa #{{ p.nro_preventa }}</span>
                    <div class="flex items-center gap-1.5 text-[10px]">
                      <span v-if="p.nro_carga" class="px-1 py-0.2 bg-[#E5E5EA] rounded">Carga: {{ p.nro_carga }}</span>
                      <span class="font-bold text-[#1D1D1F]">Bs. {{ p.monto_pedido.toFixed(2) }}</span>
                    </div>
                  </div>

                  <div class="divide-y divide-[#E5E5EA]/40">
                    <div
                      v-for="item in p.items"
                      :key="item.id"
                      class="py-1 flex items-start justify-between text-[10px] gap-2"
                    >
                      <span class="text-[#1D1D1F] line-clamp-1 flex-1">{{ item.producto }}</span>
                      <div class="text-right font-mono shrink-0">
                        <span class="text-[#86868B]">{{ item.cantidad }} u. ×</span>
                        <span class="font-semibold text-[#1D1D1F] ml-1">Bs. {{ item.monto_final.toFixed(2) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </aside>

        <!-- Panel Derecho: Visor Cartográfico Leaflet -->
        <main class="flex-1 relative min-h-[350px] md:min-h-0 bg-[#E5E5EA]">
          <div id="map-preventas" class="absolute inset-0 w-full h-full"></div>

          <div class="absolute top-3 left-3 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] px-3 py-1.5 shadow-md z-400 text-[11px] pointer-events-none">
            <span class="text-[#1D1D1F] font-medium">Auditoría:</span>
            <span class="text-[#6E6E73] ml-1">Haz clic en el mapa para situar tu punto físico o usa "Mi Ubicación".</span>
          </div>

          <div class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-400 text-[11px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Canal de Preventa</p>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1">
              <div v-for="c in canales" :key="c" class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
                <span class="text-[#1D1D1F] font-mono text-[10px]">{{ c }}</span>
              </div>
            </div>
            <div class="border-t border-[#E5E5EA] mt-1.5 pt-1.5 text-[10px] text-[#86868B]">
              Borde = Ruta | Halo azul = Ubicación supervisor
            </div>
          </div>
        </main>
      </div>
    </div>
  </RuteoLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import RuteoLayout from '@/Pages/Supervisor/Ruteo/Layout.vue';
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
    default: '',
  },
});

const CANAL_COLORS = {
  'MAY': '#0071E3',
  'TDB': '#34C759',
  'MZO': '#FF9500',
  'PROV': '#AF52DE',
  'PRT': '#8E8E93',
};

function getCanalColor(canal) {
  if (!canal) return '#8E8E93';
  return CANAL_COLORS[canal] || '#5856D6';
}

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

const rutaToCanalMap = computed(() => {
  const map = {};
  if (props.catalogo_rutas) {
    props.catalogo_rutas.forEach(r => {
      map[r.ruta] = r.canal;
    });
  }
  return map;
});

// Filtros Reactivos
const filtroFecha = ref(props.fecha_default || new Date().toISOString().slice(0, 10));
const filtroCanales = ref([...props.canales]);
const filtroRutas = ref(props.catalogo_rutas.map(r => r.ruta));
const busquedaRuta = ref('');
const busquedaTexto = ref('');
const menuAbierto = ref(null);
const pestañaLista = ref('gps');

// Ubicación del Supervisor
const ubicacionSupervisor = ref(null);
const radioSupervisor = ref(300);
const obteniendoGps = ref(false);

// Datos del Servidor
const cargando = ref(false);
const clientes = ref([]);
const kpis = ref({
  total_preventas: 0,
  total_clientes: 0,
  monto_total: 0,
  con_gps: 0,
  sin_gps: 0,
  en_radio_conteo: 0,
  en_radio_monto: 0,
});

const clienteActivoId = ref(null);
const clienteDetalleAbiertoId = ref(null);

let map = null;
let markersLayer = null;
let supervisorMarker = null;
let supervisorHalo = null;
const markersMap = new Map();

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
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
  if (!props.canales || props.canales.length === 0) return 'Todos';
  if (filtroCanales.value.length === props.canales.length) return 'Todos';
  if (filtroCanales.value.length === 0) return 'Ninguno';
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (!rutasDisponibles.value || rutasDisponibles.value.length === 0) return 'Ninguna';
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 0) return 'Ninguna';
  return `${filtroRutas.value.length} sel.`;
});

const clientesConGps = computed(() => clientes.value.filter(c => c.tiene_gps));
const clientesSinGps = computed(() => clientes.value.filter(c => !c.tiene_gps));

const clientesFiltrados = computed(() => {
  const base = pestañaLista.value === 'gps' ? clientesConGps.value : clientesSinGps.value;
  if (!busquedaTexto.value.trim()) return base;

  const q = busquedaTexto.value.toLowerCase();
  return base.filter(c =>
    (c.cliente && c.cliente.toLowerCase().includes(q)) ||
    (c.codigo_cliente && String(c.codigo_cliente).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q)) ||
    (c.pedidos && c.pedidos.some(p =>
      String(p.nro_preventa).includes(q) ||
      (p.items && p.items.some(i => i.producto && i.producto.toLowerCase().includes(q)))
    ))
  );
});

function toggleMenu(nombre) {
  menuAbierto.value = menuAbierto.value === nombre ? null : nombre;
}

function onCanalesModificados() {
  const rutasValidas = new Set(rutasDisponibles.value.map(r => r.ruta));
  // Mantener solo las rutas que aún pertenezcan a los canales seleccionados
  filtroRutas.value = filtroRutas.value.filter(r => rutasValidas.has(r));
  // Si no quedó ninguna pero hay canales activos, seleccionar todas las disponibles
  if (filtroRutas.value.length === 0 && rutasDisponibles.value.length > 0) {
    filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  }
  cargarPreventas();
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  filtroRutas.value = props.catalogo_rutas.map(r => r.ruta);
  cargarPreventas();
}

function limpiarCanales() {
  filtroCanales.value = [];
  filtroRutas.value = [];
  cargarPreventas();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  cargarPreventas();
}

function limpiarRutas() {
  filtroRutas.value = [];
  cargarPreventas();
}

function seleccionarRadio(nuevoRadio) {
  radioSupervisor.value = nuevoRadio;
  if (supervisorHalo) {
    supervisorHalo.setRadius(nuevoRadio);
  }
  cargarPreventas();
}

function limpiarUbicacion() {
  ubicacionSupervisor.value = null;
  if (supervisorMarker) {
    map.removeLayer(supervisorMarker);
    supervisorMarker = null;
  }
  if (supervisorHalo) {
    map.removeLayer(supervisorHalo);
    supervisorHalo = null;
  }
  cargarPreventas();
}

async function cargarPreventas() {
  cargando.value = true;
  try {
    const payload = {
      fecha: filtroFecha.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
    };

    if (ubicacionSupervisor.value) {
      payload.latitud = ubicacionSupervisor.value.lat;
      payload.longitud = ubicacionSupervisor.value.lng;
      payload.radio = radioSupervisor.value;
    }

    const res = await axios.post(route('supervisor.ruteo.preventas.data'), payload);
    clientes.value = res.data.clientes || [];
    kpis.value = res.data.kpis || {
      total_preventas: 0,
      total_clientes: 0,
      monto_total: 0,
      con_gps: 0,
      sin_gps: 0,
      en_radio_conteo: 0,
      en_radio_monto: 0,
    };

    renderizarPreventasEnMapa();
  } catch (err) {
    console.error('Error cargando preventas:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarPreventasEnMapa() {
  if (!map) return;
  markersLayer.clearLayers();
  markersMap.clear();

  const boundsList = [];
  const unicaRuta = filtroRutas.value.length === 1;

  clientesConGps.value.forEach(c => {
    const canal = rutaToCanalMap.value[c.ruta] || 'PRT';
    const fillColor = getCanalColor(canal);
    const strokeColor = unicaRuta ? '#FFFFFF' : getRutaColor(c.ruta);

    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: c.en_radio ? 7 : 5.5,
      fillColor: fillColor,
      color: c.en_radio ? '#0071E3' : strokeColor,
      weight: c.en_radio ? 3 : (unicaRuta ? 1.5 : 2.5),
      opacity: 1,
      fillOpacity: 0.95,
    });

    marker.bindPopup(`
      <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; line-height: 1.4; color: #1D1D1F; max-width: 230px;">
        <div style="font-weight: 600; font-size: 12px; margin-bottom: 2px;">${c.cliente}</div>
        <div style="color: ${fillColor}; font-weight: bold; margin-bottom: 2px;">
          ${canal} • Total: Bs. ${c.total_monto.toFixed(2)}
        </div>
        <div style="color: #6E6E73; margin-bottom: 4px;">${c.direccion}</div>
        <div style="font-family: monospace; font-size: 10px; color: #1D1D1F; border-top: 1px solid #E5E5EA; padding-top: 4px;">
          Ruta: ${c.ruta} | ${c.pedidos.length} pedido(s)
          ${c.distancia_metros !== null ? `<br><strong style="color: #248A3D;">a ${c.distancia_metros} metros</strong>` : ''}
        </div>
      </div>
    `);

    marker.on('click', () => {
      clienteActivoId.value = c.cliente_id;
    });

    marker.addTo(markersLayer);
    markersMap.set(c.cliente_id, marker);
    boundsList.push(L.latLng(c.latitud, c.longitud));
  });

  if (supervisorMarker) {
    boundsList.push(supervisorMarker.getLatLng());
  }

  if (boundsList.length > 0 && !ubicacionSupervisor.value) {
    try {
      const bounds = L.latLngBounds(boundsList);
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
      }
    } catch (e) {
      // Bounds inválidos
    }
  }
}

function enfocarEnMapa(c) {
  clienteActivoId.value = c.cliente_id;
  if (!c.tiene_gps || !map) return;

  map.flyTo([c.latitud, c.longitud], 18, { duration: 0.6 });
  const marker = markersMap.get(c.cliente_id);
  if (marker) {
    marker.openPopup();
  }
}

function toggleDetalleCliente(clienteId) {
  clienteDetalleAbiertoId.value = clienteDetalleAbiertoId.value === clienteId ? null : clienteId;
}

function situarSupervisor(lat, lng) {
  ubicacionSupervisor.value = { lat, lng };

  if (supervisorMarker) {
    supervisorMarker.setLatLng([lat, lng]);
  } else {
    supervisorMarker = L.circleMarker([lat, lng], {
      radius: 8,
      fillColor: '#0071E3',
      color: '#FFFFFF',
      weight: 2.5,
      opacity: 1,
      fillOpacity: 1,
    }).addTo(map);
  }

  if (supervisorHalo) {
    supervisorHalo.setLatLng([lat, lng]);
    supervisorHalo.setRadius(radioSupervisor.value);
  } else {
    supervisorHalo = L.circle([lat, lng], {
      radius: radioSupervisor.value,
      color: '#0071E3',
      weight: 1.5,
      dashArray: '4, 4',
      fillColor: '#0071E3',
      fillOpacity: 0.08,
    }).addTo(map);
  }

  cargarPreventas();
}

function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Tu dispositivo o navegador no soporta geolocalización satelital.');
    return;
  }

  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      obteniendoGps.value = false;
      const { latitude, longitude } = pos.coords;
      map.flyTo([latitude, longitude], 16, { duration: 0.8 });
      situarSupervisor(latitude, longitude);
    },
    () => {
      obteniendoGps.value = false;
      alert('No se pudo obtener tu ubicación. Verifica los permisos de GPS.');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function initMap() {
  map = L.map('map-preventas', {
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
    menuAbierto.value = null;
    situarSupervisor(e.latlng.lat, e.latlng.lng);
  });
}

function cerrarMenusGlobal(e) {
  if (!e.target.closest('.dropdown-root')) {
    menuAbierto.value = null;
  }
}

onMounted(() => {
  initMap();
  cargarPreventas();
  window.addEventListener('click', cerrarMenusGlobal);
});

onUnmounted(() => {
  window.removeEventListener('click', cerrarMenusGlobal);
});
</script>