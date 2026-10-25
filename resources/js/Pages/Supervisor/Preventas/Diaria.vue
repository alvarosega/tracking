<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans text-ink space-y-2.5 p-3 sm:p-4 select-none">
      
      <!-- ==========================================
           1. BARRA SUPERIOR DE FILTROS (APPLE LIQUID GLASS)
           ========================================== -->
      <header class="apple-glass-card rounded-[16px] p-2 sm:p-2.5 border border-white/60 flex flex-wrap items-center justify-between gap-2 shrink-0 z-30">
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
          
          <!-- SubNavTabs (Cumplimiento, Diaria, Productos, Auditoría, Comparativos, Terreno) -->
          <SubNavTabs :tabs="preventasSubTabs" currentTab="diaria" />

          <div class="h-5 w-px bg-black/[0.08] hidden sm:block shrink-0"></div>

          <!-- Selector de Fecha -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Fecha:</span>
            <input
              type="date"
              v-model="fechaSeleccionada"
              @change="cargarDashboardDiario"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-num"
            />
          </div>

          <!-- Selector de Canal -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <select
              v-model="canalSeleccionado"
              @change="alCambiarCanal"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
            >
              <option v-for="c in canalesDisponibles" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Selector de Ruta Activa -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
            <select
              v-model="rutaActiva"
              @change="cargarSecuenciaRuta(rutaActiva)"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer min-w-[100px]"
            >
              <option v-for="r in rutasFiltradas" :key="r.ruta" :value="r.ruta">
                {{ r.ruta }} - {{ r.vendedor }}
              </option>
            </select>
          </div>

          <!-- Botón Consultar Principal -->
          <button
            type="button"
            @click="cargarDashboardDiario"
            :disabled="cargandoDashboard || cargandoDetalle"
            class="flex items-center gap-1.5 px-3 py-1 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed select-none shrink-0"
          >
            <svg
              v-if="cargandoDashboard || cargandoDetalle"
              class="w-3.5 h-3.5 animate-spin text-white shrink-0"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <svg
              v-else
              class="w-3.5 h-3.5 text-white shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>{{ (cargandoDashboard || cargandoDetalle) ? 'Consultando...' : 'Consultar' }}</span>
          </button>
        </div>

        <!-- Información de cabecera (Fecha y Resumen Ruta) -->
        <div class="flex items-center gap-2.5 text-[11px] font-mono text-[#6E6E73]">
          <span v-if="diaSemana" class="font-bold text-[#1D1D1F]">
            {{ diaSemana }} · {{ fechaDisplay }}
          </span>
          <span v-if="infoRutaActual" class="hidden md:inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] bg-black/[0.04] text-[10px]">
            <span>Cartera: <strong>{{ infoRutaActual.plan_visita }}</strong></span>
            <span>·</span>
            <span>Preventa: <strong class="text-emerald-700">{{ infoRutaActual.preventa }}</strong></span>
            <span>·</span>
            <span>Total: <strong class="text-[#0071E3]">{{ fmtBs(infoRutaActual.volumen_bs) }}</strong></span>
          </span>
        </div>
      </header>

      <!-- ==========================================
           2. VISTA DIVIDIDA: MAPA (IZQ) + SECUENCIA (DER)
           ========================================== -->
      <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-3 min-h-0 overflow-hidden">
        
        <!-- PANEL IZQUIERDO: MAPA INTERACTIVO LEAFLET -->
        <section class="lg:col-span-6 apple-glass-card rounded-[16px] border border-white/60 flex flex-col h-full overflow-hidden relative shadow-glass-card">
          <!-- Barra de Controles de Recorrido -->
          <div class="p-2 sm:p-2.5 bg-white/70 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 z-20 shrink-0">
            <div class="inline-flex items-center bg-black/[0.05] rounded-[9px] p-0.5 border border-black/[0.06] text-[10px]">
              <button
                type="button"
                @click="irAParadaIndice(0)"
                :disabled="paradasConCoordenadas.length === 0"
                class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#1D1D1F] disabled:opacity-40 cursor-pointer transition-colors"
              >
                Inicio
              </button>
              <button
                type="button"
                @click="navegarPaso(-1)"
                :disabled="paradasConCoordenadas.length === 0"
                class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#1D1D1F] disabled:opacity-40 cursor-pointer transition-colors"
              >
                Anterior
              </button>
              <span class="px-2 font-mono font-bold text-[#0071E3]">
                {{ indicePasoActual !== null ? `Parada ${indicePasoActual + 1}` : 'Vista Completa' }}
              </span>
              <button
                type="button"
                @click="navegarPaso(1)"
                :disabled="paradasConCoordenadas.length === 0"
                class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#1D1D1F] disabled:opacity-40 cursor-pointer transition-colors"
              >
                Siguiente
              </button>
              <button
                type="button"
                @click="ajustarVistaCompleta"
                class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#0071E3] cursor-pointer transition-colors"
              >
                Ver todo
              </button>
            </div>

            <!-- Toggles de visualización -->
            <div class="flex items-center gap-1.5 text-[10px]">
              <label class="apple-control-pill px-2 py-1 rounded-[8px] flex items-center gap-1 cursor-pointer font-semibold text-[#1D1D1F]">
                <input v-model="mostrarLineaRecorrido" type="checkbox" @change="renderizarMapa(false)" class="rounded text-[#0071E3] focus:ring-0 w-3 h-3" />
                <span>Secuencia</span>
              </label>

              <label class="apple-control-pill px-2 py-1 rounded-[8px] flex items-center gap-1 cursor-pointer font-semibold text-rose-700">
                <input v-model="mostrarNoVisitadosMapa" type="checkbox" @change="renderizarMapa(false)" class="rounded text-rose-600 focus:ring-0 w-3 h-3" />
                <span>No visitados ({{ noVisitadosConCoords.length }})</span>
              </label>
            </div>
          </div>

          <!-- Leyenda de Estados en Mapa -->
          <div class="px-3 py-1.5 bg-[#F5F5F7]/90 border-b border-black/[0.05] flex flex-wrap items-center gap-3 text-[10px] font-semibold text-[#6E6E73] z-10 shrink-0">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span> Con Preventa</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span> Sin Venta</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-600 inline-block"></span> Desvío</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-cyan-600 inline-block"></span> Venta Sin Visita</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-600 inline-block"></span> No Visitado</span>
          </div>

          <!-- Contenedor Leaflet -->
          <div ref="mapaContainerRef" class="w-full flex-1 bg-[#F5F5F7] relative z-0"></div>
        </section>

        <!-- PANEL DERECHO: TABLA DE SECUENCIA E ITINERARIO -->
        <section class="lg:col-span-6 apple-glass-card rounded-[16px] border border-white/60 flex flex-col h-full overflow-hidden shadow-glass-card">
          <!-- Controles Superiores de la Tabla -->
          <div class="p-2 sm:p-2.5 bg-white/70 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 shrink-0">
            <!-- Segmented Control de Modos de Lista -->
            <div class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[10px]">
              <button
                type="button"
                @click="modoLista = 'recorrido'"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="modoLista === 'recorrido' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73]'"
              >
                Recorrido ({{ paradasRecorrido.length }})
              </button>
              <button
                type="button"
                @click="modoLista = 'no_visitados'"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="modoLista === 'no_visitados' ? 'apple-segmented-active text-rose-600' : 'text-[#6E6E73]'"
              >
                No Visitados ({{ clientesNoVisitados.length }})
              </button>
              <button
                type="button"
                @click="modoLista = 'todos'"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="modoLista === 'todos' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73]'"
              >
                Todos ({{ todosLosClientes.length }})
              </button>
            </div>

            <!-- Buscador + Selector de Filtro de Estado -->
            <div class="flex items-center gap-1.5 flex-1 min-w-[200px] justify-end">
              <div class="flex items-center gap-1.5 apple-control-pill rounded-[9px] px-2.5 py-1 flex-1 max-w-[210px]">
                <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                  v-model="busquedaCliente"
                  type="text"
                  placeholder="Buscar cliente..."
                  class="bg-transparent text-[11px] font-medium text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-full"
                />
              </div>

              <select
                v-model="filtroEstado"
                class="apple-control-pill rounded-[9px] px-2 py-1 text-[10px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos</option>
                <option value="CON_VENTA">Con Preventa</option>
                <option value="SIN_VENTA">Sin Venta</option>
                <option value="DESVIOS">Desvíos</option>
                <option value="NO_VISITADO">No Visitados</option>
              </select>
            </div>
          </div>

          <!-- Tabla Scrollable con filas de paradas -->
          <div ref="contenedorListaRef" class="flex-1 overflow-auto custom-scroll">
            <div v-if="cargandoDetalle" class="py-20 text-center text-[#6E6E73] text-xs font-semibold">
              <div class="w-6 h-6 mx-auto mb-2 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin"></div>
              Cargando secuencia de ruta...
            </div>

            <div v-else-if="listaVisible.length === 0" class="py-20 text-center text-[#86868B] text-xs font-medium">
              Sin registros para el filtro seleccionado.
            </div>

            <table v-else class="w-full text-left border-collapse table-apple">
              <thead>
                <tr class="bg-[#F5F5F7]/95 text-[#6E6E73] text-[10px] font-semibold uppercase tracking-wider border-b border-black/[0.08] sticky top-0 z-10 backdrop-blur-md">
                  <th class="p-2 text-center w-11">#</th>
                  <th class="p-2">Cliente</th>
                  <th class="p-2 text-center">Hora</th>
                  <th class="p-2 text-center">PDV</th>
                  <th class="p-2 text-center">Estado</th>
                  <th class="p-2 text-right">Preventa</th>
                  <th class="p-2 text-center">Fotos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.04] text-[11px]">
                <tr
                  v-for="item in listaVisible"
                  :key="item._uid"
                  :id="`parada-card-${item._uid}`"
                  @click="enfocarParada(item)"
                  class="transition-colors cursor-pointer"
                  :class="paradaSeleccionadaUid === item._uid
                    ? 'bg-blue-50/90 font-semibold'
                    : 'hover:bg-black/[0.02] bg-white/50'"
                >
                  <!-- # Secuencia -->
                  <td class="p-2 text-center">
                    <span
                      class="w-6 h-6 rounded-full inline-flex items-center justify-center font-black text-[10px] font-num"
                      :style="estiloCirculoParada(item)"
                    >
                      {{ item.ordenSecuencia ? item.ordenSecuencia : '-' }}
                    </span>
                  </td>

                  <!-- Cliente y Dirección -->
                  <td class="p-2 max-w-[210px]">
                    <div class="font-bold text-[#1D1D1F] truncate" :title="item.cliente">
                      {{ item.cliente }}
                    </div>
                    <div class="text-[10px] text-[#6E6E73] truncate" :title="item.direccion">
                      {{ item.direccion || 'Sin dirección' }}
                    </div>
                  </td>

                  <!-- Hora -->
                  <td class="p-2 text-center font-mono text-[10px] whitespace-nowrap">
                    <span v-if="item.hora_secuencia" class="font-bold text-[#0071E3]">
                      {{ item.hora_secuencia }}
                    </span>
                    <span v-else class="text-[#86868B]">-</span>
                  </td>

                  <!-- Tiempo PDV -->
                  <td class="p-2 text-center font-mono text-[10px] text-[#6E6E73] whitespace-nowrap">
                    {{ item.tiempo_pdv || '-' }}
                  </td>

                  <!-- Estado Operativo -->
                  <td class="p-2 text-center whitespace-nowrap">
                    <span
                      class="px-1.5 py-0.5 rounded-[5px] text-[9px] font-black border"
                      :class="claseBadgeEstado(item.estado_operativo)"
                    >
                      {{ textoBadgeEstado(item.estado_operativo) }}
                    </span>
                  </td>

                  <!-- Preventa Bs -->
                  <td class="p-2 text-right font-num whitespace-nowrap">
                    <span v-if="Number(item.monto_preventa) > 0" class="font-black text-emerald-700">
                      {{ fmtBs(item.monto_preventa) }}
                    </span>
                    <span v-else class="text-[#86868B] text-[10px]">-</span>
                  </td>

                  <!-- Fotos -->
                  <td class="p-2 text-center whitespace-nowrap">
                    <button
                      v-if="item.fotos && item.fotos.length > 0"
                      type="button"
                      @click.stop="abrirFotosCliente(item)"
                      class="px-2 py-0.5 rounded bg-blue-100 hover:bg-blue-200 text-blue-900 border border-blue-300 text-[10px] font-bold cursor-pointer transition-colors"
                    >
                      Ver ({{ item.fotos.length }})
                    </button>
                    <span v-else class="text-[#86868B] text-[10px]">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

      </div>

      <!-- Modal de Evidencias Fotográficas -->
      <ClienteFotosModal
        :show="modalFotos.abierto"
        :cliente="modalFotos.cliente"
        @close="modalFotos.abierto = false"
      />

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import ClienteFotosModal from './ClienteFotosModal.vue';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_default: { type: String, default: () => new Date().toISOString().split('T')[0] },
});

// Pestañas Submódulos Preventas
const preventasSubTabs = [
  { id: 'cumplimiento', name: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
  { id: 'diaria', name: 'Preventa Diaria', route: 'supervisor.preventas.diaria' },
  { id: 'productos', name: 'Productos', route: 'supervisor.preventas.productos' },
  { id: 'auditoria', name: 'Auditoría', route: 'supervisor.preventas.auditoria' },
  { id: 'comparativos', name: 'Comparativos', route: 'supervisor.preventas.comparativos' },
  { id: 'mapa', name: 'Auditoría en Terreno', route: 'supervisor.preventas.index' },
];

// Estado Reactivo
const fechaSeleccionada = ref(props.fecha_default);
const fechaDisplay = ref('');
const diaSemana = ref('');
const canalesDisponibles = ref(['TODOS', ...props.canales]);
const canalSeleccionado = ref('TDB');
const matrizRutas = ref([]);
const rutaActiva = ref('');

const cargandoDashboard = ref(false);
const cargandoDetalle = ref(false);
const detalleRuta = ref({ ruta: '', vendedor: '', clientes: [] });

const modoLista = ref('recorrido'); // 'recorrido' | 'no_visitados' | 'todos'
const busquedaCliente = ref('');
const filtroEstado = ref('TODOS');
const paradaSeleccionadaUid = ref(null);
const indicePasoActual = ref(null);

const mostrarLineaRecorrido = ref(true);
const mostrarNoVisitadosMapa = ref(true);

const contenedorListaRef = ref(null);
const mapaContainerRef = ref(null);

let mapaInstance = null;
let layerGroup = null;
const marcadoresPorUid = new Map();

const modalFotos = ref({
  abierto: false,
  cliente: null,
});

// Formateadores
function fmtBs(val) {
  const n = Number(val || 0);
  return `Bs ${n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Rutas filtradas según canal
const rutasFiltradas = computed(() => {
  if (!canalSeleccionado.value || canalSeleccionado.value === 'TODOS') {
    return matrizRutas.value;
  }
  return matrizRutas.value.filter(
    r => String(r.canal || '').trim().toUpperCase() === canalSeleccionado.value.trim().toUpperCase()
  );
});

const infoRutaActual = computed(() => {
  return matrizRutas.value.find(r => r.ruta === rutaActiva.value) || null;
});

// Procesamiento de clientes y cálculo de secuencia 1..N
const clientesProcesados = computed(() => {
  const raw = detalleRuta.value.clientes || [];
  const activos = [];
  const noVis = [];

  raw.forEach((c, idx) => {
    const item = {
      ...c,
      _uid: `${c.cliente_id || 'x'}_${idx}`,
    };
    if (c.estado_operativo === 'PLAN_NO_VISITADO') {
      noVis.push(item);
    } else {
      activos.push(item);
    }
  });

  activos.sort((a, b) => {
    const hA = a.hora_secuencia || a.check_in || a.hora_app || '99:99:99';
    const hB = b.hora_secuencia || b.check_in || b.hora_app || '99:99:99';
    if (hA !== hB) return hA.localeCompare(hB);
    return String(a.cliente || '').localeCompare(String(b.cliente || ''));
  });

  activos.forEach((item, i) => {
    item.ordenSecuencia = i + 1;
  });

  noVis.forEach(item => {
    item.ordenSecuencia = null;
  });

  return {
    recorrido: activos,
    noVisitados: noVis,
    todos: [...activos, ...noVis],
  };
});

const paradasRecorrido = computed(() => clientesProcesados.value.recorrido);
const clientesNoVisitados = computed(() => clientesProcesados.value.noVisitados);
const todosLosClientes = computed(() => clientesProcesados.value.todos);

const paradasConCoordenadas = computed(() =>
  paradasRecorrido.value.filter(c => Number(c.latitud) !== 0 && Number(c.longitud) !== 0)
);

const noVisitadosConCoords = computed(() =>
  clientesNoVisitados.value.filter(c => Number(c.latitud) !== 0 && Number(c.longitud) !== 0)
);

// Filtrado reactivo de la tabla
const listaVisible = computed(() => {
  let base = [];
  if (modoLista.value === 'recorrido') base = paradasRecorrido.value;
  else if (modoLista.value === 'no_visitados') base = clientesNoVisitados.value;
  else base = todosLosClientes.value;

  const q = busquedaCliente.value.trim().toLowerCase();
  return base.filter(item => {
    if (filtroEstado.value === 'CON_VENTA' && Number(item.monto_preventa) <= 0) return false;
    if (filtroEstado.value === 'SIN_VENTA' && item.estado_operativo !== 'PLAN_SIN_VENTA') return false;
    if (filtroEstado.value === 'DESVIOS' && !['DESVIO_DIA', 'DESVIO_RUTA', 'CLIENTE_NUEVO'].includes(item.estado_operativo)) return false;
    if (filtroEstado.value === 'NO_VISITADO' && item.estado_operativo !== 'PLAN_NO_VISITADO') return false;

    if (!q) return true;
    const nom = String(item.cliente || '').toLowerCase();
    const dir = String(item.direccion || '').toLowerCase();
    return nom.includes(q) || dir.includes(q);
  });
});

// Estilos visuales
function colorHexEstado(est) {
  switch (est) {
    case 'PLAN_CON_VENTA':
      return '#059669'; // Emerald
    case 'VENTA_SIN_VISITA':
      return '#0891b2'; // Cyan
    case 'PLAN_SIN_VENTA':
      return '#d97706'; // Amber
    case 'DESVIO_DIA':
    case 'DESVIO_RUTA':
    case 'CLIENTE_NUEVO':
      return '#4f46e5'; // Indigo
    case 'PLAN_NO_VISITADO':
    default:
      return '#e11d48'; // Rose
  }
}

function estiloCirculoParada(item) {
  const bg = colorHexEstado(item.estado_operativo);
  return {
    backgroundColor: bg,
    color: '#ffffff',
    border: '2px solid #ffffff',
    boxShadow: '0 2px 5px rgba(0,0,0,0.18)',
  };
}

function claseBadgeEstado(est) {
  switch (est) {
    case 'PLAN_CON_VENTA':
      return 'bg-emerald-100 text-emerald-950 border-emerald-300';
    case 'VENTA_SIN_VISITA':
      return 'bg-cyan-100 text-cyan-950 border-cyan-300';
    case 'PLAN_SIN_VENTA':
      return 'bg-amber-100 text-amber-950 border-amber-300';
    case 'PLAN_NO_VISITADO':
      return 'bg-rose-100 text-rose-950 border-rose-300';
    case 'DESVIO_DIA':
      return 'bg-indigo-100 text-indigo-950 border-indigo-300';
    case 'DESVIO_RUTA':
      return 'bg-purple-100 text-purple-950 border-purple-300';
    case 'CLIENTE_NUEVO':
      return 'bg-teal-100 text-teal-950 border-teal-300';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-300';
  }
}

function textoBadgeEstado(est) {
  switch (est) {
    case 'PLAN_CON_VENTA':
      return 'CON PREVENTA';
    case 'VENTA_SIN_VISITA':
      return 'VENTA SIN VISITA';
    case 'PLAN_SIN_VENTA':
      return 'SIN VENTA';
    case 'PLAN_NO_VISITADO':
      return 'NO VISITADO';
    case 'DESVIO_DIA':
      return 'DESVÍO DÍA';
    case 'DESVIO_RUTA':
      return 'DESVÍO RUTA';
    case 'CLIENTE_NUEVO':
      return 'CLIENTE NUEVO';
    default:
      return est;
  }
}

// Carga de API
async function cargarDashboardDiario() {
  cargandoDashboard.value = true;
  try {
    const payload = {
      fecha: fechaSeleccionada.value,
      canal: canalSeleccionado.value,
    };

    const res = await axios.post('/supervisor/preventas/diaria/data', payload);
    const data = res.data;

    if (data.fecha_seleccionada) {
      fechaSeleccionada.value = data.fecha_seleccionada;
    }
    fechaDisplay.value = data.fecha_display || data.fecha_seleccionada || '';
    diaSemana.value = data.dia_semana || '';

    if (Array.isArray(data.canales_disponibles) && data.canales_disponibles.length > 0) {
      canalesDisponibles.value = data.canales_disponibles;
    }

    matrizRutas.value = data.matriz_rutas || [];

    const disponibles = rutasFiltradas.value;
    if (disponibles.length > 0) {
      const existeActual = disponibles.some(r => r.ruta === rutaActiva.value);
      if (!existeActual) {
        const conActividad = disponibles.find(r => Number(r.total_visitados) > 0 || Number(r.preventa) > 0);
        rutaActiva.value = conActividad ? conActividad.ruta : disponibles[0].ruta;
      }
      await cargarSecuenciaRuta(rutaActiva.value);
    } else {
      detalleRuta.value = { ruta: '', vendedor: '', clientes: [] };
      renderizarMapa();
    }
  } catch (err) {
    console.error('Error al cargar dashboard de preventa diaria:', err);
  } finally {
    cargandoDashboard.value = false;
  }
}

function alCambiarCanal() {
  const disponibles = rutasFiltradas.value;
  if (disponibles.length > 0) {
    const conActividad = disponibles.find(r => Number(r.total_visitados) > 0 || Number(r.preventa) > 0);
    rutaActiva.value = conActividad ? conActividad.ruta : disponibles[0].ruta;
    cargarSecuenciaRuta(rutaActiva.value);
  }
}

async function cargarSecuenciaRuta(ruta) {
  if (!ruta) return;
  cargandoDetalle.value = true;
  paradaSeleccionadaUid.value = null;
  indicePasoActual.value = null;

  try {
    const res = await axios.post(`/supervisor/preventas/diaria/ruta/${encodeURIComponent(ruta)}`, {
      fecha: fechaSeleccionada.value,
    });
    detalleRuta.value = res.data || { ruta, vendedor: '', clientes: [] };
    await nextTick();
    renderizarMapa(true);
  } catch (err) {
    console.error('Error al cargar secuencia de ruta:', err);
  } finally {
    cargandoDetalle.value = false;
  }
}

// Mapa Leaflet
function asegurarMapa() {
  if (mapaInstance || !mapaContainerRef.value) return;
  mapaInstance = L.map(mapaContainerRef.value, {
    zoomControl: true,
  }).setView([-16.5, -68.15], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
  }).addTo(mapaInstance);

  layerGroup = L.layerGroup().addTo(mapaInstance);
}

function renderizarMapa(fitBoundsAuto = false) {
  asegurarMapa();
  if (!mapaInstance || !layerGroup) return;

  layerGroup.clearLayers();
  marcadoresPorUid.clear();

  const coordsRecorrido = [];
  const boundsPoints = [];

  // 1. Clientes No Visitados
  if (mostrarNoVisitadosMapa.value) {
    noVisitadosConCoords.value.forEach(c => {
      const lat = Number(c.latitud);
      const lng = Number(c.longitud);
      boundsPoints.push([lat, lng]);

      const iconHtml = `
        <div style="background:#e11d48;color:#fff;border:2px solid #fff;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;box-shadow:0 2px 5px rgba(0,0,0,0.28);opacity:0.88;">
        </div>
      `;
      const marker = L.marker([lat, lng], {
        icon: L.divIcon({
          html: iconHtml,
          className: '',
          iconSize: [18, 18],
          iconAnchor: [9, 9],
        }),
      });

      marker.bindPopup(crearPopupHtml(c));
      marker.on('click', () => {
        paradaSeleccionadaUid.value = c._uid;
        scrollHaciaTarjeta(c._uid);
      });
      marker.addTo(layerGroup);
      marcadoresPorUid.set(c._uid, marker);
    });
  }

  // 2. Recorrido real 1..N
  paradasConCoordenadas.value.forEach(c => {
    const lat = Number(c.latitud);
    const lng = Number(c.longitud);
    coordsRecorrido.push([lat, lng]);
    boundsPoints.push([lat, lng]);

    const bg = colorHexEstado(c.estado_operativo);
    const esSeleccionado = paradaSeleccionadaUid.value === c._uid;
    const size = esSeleccionado ? 30 : 25;

    const iconHtml = `
      <div style="background:${bg};color:#fff;border:2.5px solid #fff;width:${size}px;height:${size}px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;box-shadow:0 3px 8px rgba(0,0,0,0.35);">
        ${c.ordenSecuencia}
      </div>
    `;
    const marker = L.marker([lat, lng], {
      zIndexOffset: 500 + (c.ordenSecuencia || 0),
      icon: L.divIcon({
        html: iconHtml,
        className: '',
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
      }),
    });

    marker.bindPopup(crearPopupHtml(c));
    marker.on('click', () => {
      paradaSeleccionadaUid.value = c._uid;
      scrollHaciaTarjeta(c._uid);
    });
    marker.addTo(layerGroup);
    marcadoresPorUid.set(c._uid, marker);
  });

  // 3. Polilínea de secuencia 1..N
  if (mostrarLineaRecorrido.value && coordsRecorrido.length > 1) {
    L.polyline(coordsRecorrido, {
      color: '#ffffff',
      weight: 6,
      opacity: 0.75,
      lineJoin: 'round',
    }).addTo(layerGroup);

    L.polyline(coordsRecorrido, {
      color: '#0071E3',
      weight: 3.5,
      opacity: 0.92,
      dashArray: '8, 6',
      lineJoin: 'round',
    }).addTo(layerGroup);
  }

  setTimeout(() => {
    if (!mapaInstance) return;
    mapaInstance.invalidateSize();
    if (fitBoundsAuto && boundsPoints.length > 0) {
      const bounds = L.latLngBounds(boundsPoints);
      mapaInstance.fitBounds(bounds.pad(0.14), { maxZoom: 16 });
    }
  }, 100);
}

function crearPopupHtml(c) {
  const ordenTxt = c.ordenSecuencia ? `Parada ${c.ordenSecuencia}` : 'Plan No Visitado';
  const montoTxt = Number(c.monto_preventa) > 0 ? fmtBs(c.monto_preventa) : 'Sin Preventa (Bs 0.00)';
  const horaTxt = c.hora_secuencia || 'Sin marcación';
  const tPdv = c.tiempo_pdv ? ` · PDV: ${c.tiempo_pdv}` : '';

  return `
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;min-width:210px;line-height:1.35;">
      <div style="font-size:10px;font-weight:800;color:#0071E3;text-transform:uppercase;">
        ${ordenTxt} · ${horaTxt}${tPdv}
      </div>
      <div style="font-size:12px;font-weight:800;color:#1D1D1F;margin-top:2px;">
        ${c.cliente}
      </div>
      <div style="font-size:10px;color:#6E6E73;margin-top:2px;">
        ${c.direccion || 'Sin dirección'}
      </div>
      <div style="margin-top:6px;padding-top:5px;border-top:1px solid #E5E5EA;display:flex;justify-content:space-between;align-items:center;font-size:11px;">
        <span style="font-weight:700;color:#1D1D1F;">${textoBadgeEstado(c.estado_operativo)}</span>
        <strong style="color:#059669;">${montoTxt}</strong>
      </div>
    </div>
  `;
}

function enfocarParada(item) {
  paradaSeleccionadaUid.value = item._uid;
  const idxCoord = paradasConCoordenadas.value.findIndex(p => p._uid === item._uid);
  if (idxCoord !== -1) {
    indicePasoActual.value = idxCoord;
  }

  const lat = Number(item.latitud);
  const lng = Number(item.longitud);
  if (mapaInstance && lat !== 0 && lng !== 0) {
    mapaInstance.flyTo([lat, lng], 17, { duration: 0.6 });
    const marker = marcadoresPorUid.get(item._uid);
    if (marker) {
      setTimeout(() => marker.openPopup(), 350);
    }
  }
}

function irAParadaIndice(idx) {
  const lista = paradasConCoordenadas.value;
  if (idx < 0 || idx >= lista.length) return;
  indicePasoActual.value = idx;
  const item = lista[idx];
  modoLista.value = 'recorrido';
  enfocarParada(item);
  scrollHaciaTarjeta(item._uid);
}

function navegarPaso(delta) {
  const lista = paradasConCoordenadas.value;
  if (lista.length === 0) return;
  if (indicePasoActual.value === null) {
    irAParadaIndice(0);
    return;
  }
  let nuevo = indicePasoActual.value + delta;
  if (nuevo < 0) nuevo = lista.length - 1;
  if (nuevo >= lista.length) nuevo = 0;
  irAParadaIndice(nuevo);
}

function ajustarVistaCompleta() {
  indicePasoActual.value = null;
  paradaSeleccionadaUid.value = null;
  renderizarMapa(true);
}

function scrollHaciaTarjeta(uid) {
  nextTick(() => {
    const el = document.getElementById(`parada-card-${uid}`);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  });
}

function abrirFotosCliente(item) {
  modalFotos.value = {
    abierto: true,
    cliente: {
      cliente_id: item.cliente_id,
      cliente: item.cliente,
      direccion: item.direccion,
      tipo_negocio: item.tipo_negocio,
    },
  };
}

onMounted(() => {
  asegurarMapa();
  cargarDashboardDiario();
});

onBeforeUnmount(() => {
  if (mapaInstance) {
    try {
      mapaInstance.remove();
    } catch {}
    mapaInstance = null;
  }
});
</script>

