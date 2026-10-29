<template>
  <SupervisorLayout title="Saneamiento | Días Desalineados">
    <div class="space-y-4">
      <SaneamientoSubTabs active-tab="dias" />

      <!-- Barra de Filtros y Configuración de Ruta y Buffer -->
      <div class="apple-glass-card rounded-[16px] p-2.5 sm:p-3 mb-3 select-none font-sans">
        <div class="flex flex-wrap items-center justify-between gap-2.5 text-[11px]">
          
          <div class="flex flex-wrap items-center gap-2">
            <!-- Canal -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
              <select
                v-model="canalSeleccionado"
                @change="alCambiarCanal"
                class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in canalesDisponibles" :key="c" :value="c">
                  {{ c }}
                </option>
              </select>
            </div>

            <!-- Selector de Ruta Individual -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
              <select
                v-model="rutaSeleccionada"
                @change="consultarDias"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
              >
                <option v-for="r in rutasDisponibles" :key="r.ruta" :value="r.ruta">
                  {{ r.ruta }} ({{ r.canal }})
                </option>
              </select>
            </div>

            <!-- Selector de Buffer en Metros -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Buffer:</span>
              <select
                v-model="bufferMetros"
                @change="consultarDias"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
              >
                <option :value="15">15 metros</option>
                <option :value="25">25 metros</option>
                <option :value="35">35 metros (Estándar)</option>
                <option :value="50">50 metros</option>
                <option :value="100">100 metros</option>
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="consultarDias"
              :disabled="cargando"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer disabled:opacity-75"
            >
              <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <span>{{ cargando ? 'Consultando...' : 'Consultar' }}</span>
            </button>

            <!-- Conmutador de Modo de Vista -->
            <div class="apple-segmented rounded-[10px] p-0.5 flex items-center text-[10px]">
              <button
                type="button"
                @click="modoVista = 'REASIGNAR'"
                class="px-2.5 py-1 text-[11px] rounded-[7px] transition-all cursor-pointer"
                :class="modoVista === 'REASIGNAR'
                  ? 'apple-segmented-active text-[#1D1D1F] font-bold shadow-2xs'
                  : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              >
                Reasignación
              </button>
              <button
                type="button"
                @click="modoVista = 'FRONTERAS'"
                class="px-2.5 py-1 text-[11px] rounded-[7px] transition-all cursor-pointer"
                :class="modoVista === 'FRONTERAS'
                  ? 'apple-segmented-active text-[#1D1D1F] font-bold shadow-2xs'
                  : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              >
                Fronteras por Día
              </button>
            </div>
          </div>

          <!-- Badges de la Ruta -->
          <div class="flex items-center gap-2 text-[10px] font-mono sm:ml-auto">
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-blue-700 font-bold">
              Total PDVs: {{ totalClientesRuta }}
            </span>
            <span
              v-if="clientesMultidia.length > 0"
              class="px-2.5 py-1 rounded-[10px] apple-control-pill text-rose-700 font-bold bg-rose-50 border border-rose-200"
            >
              Multi-Día: {{ clientesMultidia.length }}
            </span>
          </div>

        </div>
      </div>

      <!-- Contenedor Principal: Mapa y Panel Lateral -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        <!-- Mapa (2 columnas) -->
        <div class="lg:col-span-2 relative glass-panel rounded-card overflow-hidden shadow-subtle min-h-[580px] flex flex-col border border-black/[0.06] bg-white/70 backdrop-blur-md">
          <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              <span class="font-bold text-[12px] text-ink">Mapa Territorial por Día de Visita</span>
              <span class="text-[10px] font-mono text-[#86868B]">Ruta: {{ rutaSeleccionada }}</span>
            </div>
            <!-- Herramienta de modo Fronteras por Día -->
            <div v-if="modoVista === 'FRONTERAS'" class="flex items-center gap-1.5 text-[10px]">
              <span class="font-bold text-[#6E6E73]">Día a Editar:</span>
              <select
                v-model="diaParaEditarFrontera"
                class="bg-white border border-neutral-200 rounded px-2 py-0.5 font-bold text-ink"
              >
                <option v-for="d in DIAS_SEMANA" :key="d.val" :value="d.val">
                  {{ d.label }}
                </option>
              </select>
              <button
                type="button"
                @click="guardarFronteraDiaActual"
                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-bold cursor-pointer shadow-2xs"
              >
                Guardar Polígono
              </button>
            </div>
          </div>

          <div id="mapaDias" class="flex-1 w-full min-h-[500px] relative z-0"></div>

          <!-- Leyenda de Días -->
          <div class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-md border border-neutral-200/80 rounded-card shadow-float px-3 py-2 text-[10px] z-20 space-y-1 max-w-[280px]">
            <div class="font-bold text-ink uppercase tracking-wider text-[9px] border-b pb-1">
              Días de Visita (Envolvente Convexa)
            </div>
            <div class="grid grid-cols-2 gap-x-2 gap-y-1 font-mono text-[9px]">
              <div v-for="d in DIAS_SEMANA" :key="d.val" class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: d.color }"></span>
                <span>{{ d.label }} ({{ conteoPorDia[d.val] || 0 }})</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel Lateral (1 columna) -->
        <div class="glass-panel rounded-card shadow-subtle flex flex-col overflow-hidden min-h-[580px] border border-black/[0.06] bg-white/70 backdrop-blur-md">
          <div class="px-3 py-2 border-b border-neutral-100 flex items-center justify-between bg-white/80 shrink-0">
            <span class="font-bold text-[11px] text-ink">Distribución de PDVs por Jornada</span>
            <span class="text-[10px] font-mono text-ink-secondary">{{ clientesRuta.length }} clientes</span>
          </div>

          <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-3 text-[11px]">
            
            <!-- Resumen de PDVs por Día -->
            <div class="grid grid-cols-2 gap-1.5">
              <div
                v-for="d in DIAS_SEMANA"
                :key="d.val"
                class="p-2 rounded-card border transition-all"
                :style="{
                  backgroundColor: d.color + '10',
                  borderColor: d.color + '33',
                }"
              >
                <div class="flex items-center justify-between">
                  <span class="font-bold text-[10px]" :style="{ color: d.color }">{{ d.label }}</span>
                  <span class="font-mono font-bold text-[12px]" :style="{ color: d.color }">
                    {{ conteoPorDia[d.val] || 0 }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Alerta Multi-Día si existe -->
            <div v-if="clientesMultidia.length > 0" class="p-2.5 rounded-card bg-rose-50 border border-rose-200 text-rose-900 space-y-1.5">
              <div class="flex items-center gap-1.5 font-bold text-[11px] text-rose-700">
                <span>⚠️ {{ clientesMultidia.length }} Clientes con Visitas Múltiples</span>
              </div>
              <p class="text-[10px] text-rose-600">
                Clientes asignados a 2 o más días en la misma semana. Se recomienda consolidar la frecuencia.
              </p>
              <div class="space-y-1 max-h-36 overflow-y-auto custom-scroll pt-1">
                <div
                  v-for="mCli in clientesMultidia"
                  :key="mCli.cliente_id"
                  class="p-1.5 rounded bg-white/80 border border-rose-200/60 flex items-center justify-between text-[10px]"
                >
                  <span class="font-semibold truncate max-w-[140px]">{{ mCli.cliente }}</span>
                  <span class="font-mono text-rose-700 font-bold">{{ mCli.dias_asignados.join(', ') }}</span>
                </div>
              </div>
            </div>

            <!-- Lista Rápida de Clientes de la Ruta con Reasignación Rápida -->
            <div class="space-y-1.5 pt-2 border-t border-neutral-100">
              <div class="flex items-center justify-between">
                <span class="font-bold text-ink uppercase tracking-wider text-[10px]">Reasignación Rápida</span>
                <input
                  v-model="filtroClienteTexto"
                  type="text"
                  placeholder="Filtrar cliente..."
                  class="text-[10px] bg-white rounded px-2 py-0.5 border border-neutral-200 focus:outline-none w-36 font-mono"
                />
              </div>

              <div class="space-y-1 max-h-64 overflow-y-auto custom-scroll">
                <div
                  v-for="cli in clientesListaFiltrada"
                  :key="cli.cliente_id + '_' + cli.dia"
                  class="p-2 rounded bg-white border border-neutral-200/80 hover:shadow-2xs transition-all space-y-1"
                >
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-ink truncate pr-1">{{ cli.cliente }}</span>
                    <span class="font-mono text-[9px] text-[#86868B] shrink-0">ID: {{ cli.cliente_id }}</span>
                  </div>
                  <div class="flex items-center justify-between text-[10px] font-mono">
                    <span class="text-neutral-500 truncate max-w-[120px]">{{ cli.direccion }}</span>
                    <div class="flex items-center gap-1">
                      <select
                        :value="cli.dia"
                        @change="solicitarCambioDia(cli, $event.target.value)"
                        class="px-1.5 py-0.5 rounded text-white text-[9px] font-bold border border-white/30 focus:outline-none cursor-pointer"
                        :style="{ backgroundColor: getColorDia(cli.dia) }"
                      >
                        <option
                          v-for="d in DIAS_SEMANA"
                          :key="d.val"
                          :value="d.val"
                          class="bg-white text-ink font-bold"
                        >
                          {{ d.label }}
                        </option>
                      </select>
                      <button
                        type="button"
                        @click="abrirModalFotos(cli)"
                        class="px-1 py-0.5 bg-neutral-100 hover:bg-neutral-200 text-ink rounded font-bold border border-neutral-200"
                        title="Fotos"
                      >
                        📷
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

      <!-- Tabla Inferior: Propuestas Pendientes de Cambio de Día -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Propuestas de Cambio de Día</span>
            <span class="text-[10px] font-mono text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-bold">
              {{ propuestasPendientes.length }} pendientes
            </span>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll max-h-72">
          <table class="w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
              <tr>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">ID</th>
                <th class="py-2.5 px-3">Ruta</th>
                <th class="py-2.5 px-3">Día Actual</th>
                <th class="py-2.5 px-3">Día Propuesto</th>
                <th class="py-2.5 px-3 text-center">Estado</th>
                <th class="py-2.5 px-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="propuestasPendientes.length === 0">
                <td colspan="7" class="py-8 text-center text-[#86868B] font-medium">
                  No hay propuestas pendientes de cambio de día para la ruta {{ rutaSeleccionada }}.
                </td>
              </tr>
              <tr
                v-for="p in propuestasPendientes"
                :key="p.id"
                class="hover:bg-neutral-50/70 transition-colors"
              >
                <td class="py-2 px-3 font-bold text-ink">{{ p.cliente_nombre }}</td>
                <td class="py-2 px-3 font-mono text-ink-secondary">{{ p.cliente_id_origen }}</td>
                <td class="py-2 px-3 font-mono">{{ p.ruta_actual }}</td>
                <td class="py-2 px-3 font-mono">
                  <span class="px-2 py-0.5 rounded text-white text-[9px] font-bold" :style="{ backgroundColor: getColorDia(p.dia_actual) }">
                    {{ p.dia_actual }}
                  </span>
                </td>
                <td class="py-2 px-3 font-mono">
                  <span class="px-2 py-0.5 rounded text-white text-[9px] font-bold" :style="{ backgroundColor: getColorDia(p.dia_propuesto) }">
                    {{ p.dia_propuesto }}
                  </span>
                </td>
                <td class="py-2 px-3 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-50 text-amber-800 border border-amber-200">
                    {{ p.estado }}
                  </span>
                </td>
                <td class="py-2 px-3 text-right space-x-1 whitespace-nowrap">
                  <button
                    type="button"
                    @click="cambiarEstadoPropuesta(p.id, 'APLICADO')"
                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                  >
                    Aprobar
                  </button>
                  <button
                    type="button"
                    @click="cambiarEstadoPropuesta(p.id, 'DESCARTADO')"
                    class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                  >
                    Descartar
                  </button>
                  <button
                    type="button"
                    @click="revertirPropuesta(p.id)"
                    class="px-2 py-1 bg-neutral-200 hover:bg-neutral-300 text-ink rounded text-[10px] font-bold cursor-pointer"
                  >
                    Revertir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Fotos -->
      <ClienteFotosModal
        :visible="modalFotosVisible"
        :cliente-id="clienteSeleccionadoFotos?.cliente_id"
        :cliente-nombre="clienteSeleccionadoFotos?.cliente || clienteSeleccionadoFotos?.nombre"
        :ruta="clienteSeleccionadoFotos?.ruta"
        @close="modalFotosVisible = false"
      />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SaneamientoSubTabs from './Components/SaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const DIAS_SEMANA = [
  { val: 'LUNES', label: 'Lunes', corto: 'L', color: '#2563EB' },
  { val: 'MARTES', label: 'Martes', corto: 'M', color: '#16A34A' },
  { val: 'MIERCOLES', label: 'Miércoles', corto: 'X', color: '#EA580C' },
  { val: 'JUEVES', label: 'Jueves', corto: 'J', color: '#9333EA' },
  { val: 'VIERNES', label: 'Viernes', corto: 'V', color: '#CA8A04' },
  { val: 'SABADO', label: 'Sábado', corto: 'S', color: '#E11D48' },
  { val: 'DOMINGO', label: 'Domingo', corto: 'D', color: '#64748B' },
]

const DIAS_LUNES_A_SABADO = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO']

function getColorDia(dia) {
  const dUpper = String(dia || '').trim().toUpperCase()
  const found = DIAS_SEMANA.find(d => d.val === dUpper)
  return found ? found.color : '#6B7280'
}

const cargando = ref(false)
const canalSeleccionado = ref('TDB')
const rutaSeleccionada = ref('')
const bufferMetros = ref(35)
const modoVista = ref('REASIGNAR') // 'REASIGNAR' | 'FRONTERAS'
const diaParaEditarFrontera = ref('LUNES')

const clientesRuta = ref([])
const clientesMultidia = ref([])
const diasData = ref({})
const propuestasPendientes = ref([])
const filtroClienteTexto = ref('')

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

let map = null
let layerGroupPoligonos = null
let layerGroupPuntos = null

const canalesDisponibles = computed(() => props.catalogo.canales || [])

const rutasDisponibles = computed(() => {
  const rutas = props.catalogo.rutas || []
  if (canalSeleccionado.value === 'TODOS') return rutas
  return rutas.filter(r => r.canal === canalSeleccionado.value)
})

const totalClientesRuta = computed(() => {
  return clientesRuta.value.filter(c => {
    const d = String(c.dia || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase()
    return DIAS_LUNES_A_SABADO.includes(d)
  }).length
})

const conteoPorDia = computed(() => {
  const conteo = {}
  clientesRuta.value.forEach(c => {
    const d = String(c.dia || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toUpperCase()
    conteo[d] = (conteo[d] || 0) + 1
  })
  return conteo
})

const clientesListaFiltrada = computed(() => {
  if (!filtroClienteTexto.value.trim()) return clientesRuta.value
  const q = filtroClienteTexto.value.trim().toLowerCase()
  return clientesRuta.value.filter(c =>
    c.cliente.toLowerCase().includes(q) ||
    String(c.cliente_id).includes(q) ||
    c.direccion.toLowerCase().includes(q)
  )
})

onMounted(() => {
  if (rutasDisponibles.value.length > 0) {
    rutaSeleccionada.value = rutasDisponibles.value[0].ruta
  }
  inicializarMapa()
  consultarDias()
})

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
})

function inicializarMapa() {
  const container = document.getElementById('mapaDias')
  if (!container || map) return

  map = L.map('mapaDias', {
    zoomControl: true,
    preferCanvas: true,
  }).setView([-17.7833, -63.1821], 13)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '&copy; CartoDB &copy; OpenStreetMap',
  }).addTo(map)

  layerGroupPoligonos = L.layerGroup().addTo(map)
  layerGroupPuntos = L.layerGroup().addTo(map)
}

function alCambiarCanal() {
  if (rutasDisponibles.value.length > 0) {
    rutaSeleccionada.value = rutasDisponibles.value[0].ruta
    consultarDias()
  }
}

async function consultarDias() {
  if (!rutaSeleccionada.value) return
  cargando.value = true
  try {
    const { data } = await axios.get(route('supervisor.saneamiento.dias-desalineados'), {
      params: {
        ruta: rutaSeleccionada.value,
        buffer: bufferMetros.value,
      },
    })

    diasData.value = data?.dias || {}
    clientesMultidia.value = data?.clientes_multidia || []
    propuestasPendientes.value = data?.propuestas_pendientes || []

    // Aplanar clientes
    const todos = []
    Object.keys(diasData.value).forEach(dia => {
      const items = diasData.value[dia]?.clientes || []
      items.forEach(it => todos.push(it))
    })
    clientesRuta.value = todos

    renderizarDistribucionEnMapa()
  } catch (err) {
    console.error('Error al auditar días:', err)
  } finally {
    cargando.value = false
  }
}

function renderizarDistribucionEnMapa() {
  if (!map || !layerGroupPoligonos || !layerGroupPuntos) return
  layerGroupPoligonos.clearLayers()
  layerGroupPuntos.clearLayers()

  const bounds = []

  // 1. Polígonos de días
  Object.keys(diasData.value).forEach(dia => {
    const info = diasData.value[dia]
    const coords = info.poligono || []
    if (coords.length >= 3) {
      const color = getColorDia(dia)
      const poly = L.polygon(coords, {
        color: color,
        weight: 2,
        fillColor: color,
        fillOpacity: 0.15,
      })
      poly.bindTooltip(`<div class="font-bold text-[11px] font-mono">${dia} (${info.total_pdvs} PDVs)</div>`)
      layerGroupPoligonos.addLayer(poly)
    }
  })

  // 2. Puntos de clientes
  clientesRuta.value.forEach(cli => {
    bounds.push([cli.latitud, cli.longitud])
    const color = getColorDia(cli.dia)

    const marker = L.circleMarker([cli.latitud, cli.longitud], {
      radius: cli.es_multidia ? 6.5 : 5,
      color: cli.es_multidia ? '#DC2626' : '#FFFFFF',
      weight: cli.es_multidia ? 2.5 : 1.5,
      fillColor: color,
      fillOpacity: 0.95,
    })

    marker.bindPopup(`
      <div class="p-2 font-sans text-xs">
        <div class="font-bold text-slate-900">${cli.cliente}</div>
        <div class="text-[10px] text-slate-500 font-mono">ID: ${cli.cliente_id} • Ruta: ${cli.ruta}</div>
        <div class="text-[10px] text-slate-500 truncate">${cli.direccion || ''}</div>
        <div class="mt-1 pt-1 border-t flex items-center justify-between text-[10px] font-mono">
          <span>Día asignado:</span>
          <span class="px-1.5 py-0.5 rounded text-white font-bold" style="background-color: ${color}">
            ${cli.dia}
          </span>
        </div>
        ${cli.es_multidia ? `<div class="mt-1 text-[10px] text-rose-600 font-bold font-mono">Visitas Múltiples: ${cli.dias_asignados.join(', ')}</div>` : ''}
      </div>
    `)

    layerGroupPuntos.addLayer(marker)
  })

  if (bounds.length > 0) {
    try {
      map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 })
    } catch {
      // ignore
    }
  }
}

async function solicitarCambioDia(cli, nuevoDia) {
  if (cli.dia === nuevoDia) return
  try {
    const { data } = await axios.post(route('supervisor.saneamiento.dias.cambiar-dia'), {
      cliente_id: cli.cliente_id,
      cliente: cli.cliente,
      ruta: cli.ruta,
      dia_actual: cli.dia,
      dia_propuesto: nuevoDia,
      buffer_metros: bufferMetros.value,
    })

    if (data?.distribucion_actualizada) {
      diasData.value = data.distribucion_actualizada.dias || {}
      clientesMultidia.value = data.distribucion_actualizada.clientes_multidia || []
      propuestasPendientes.value = data.distribucion_actualizada.propuestas_pendientes || []

      const todos = []
      Object.keys(diasData.value).forEach(dia => {
        const items = diasData.value[dia]?.clientes || []
        items.forEach(it => todos.push(it))
      })
      clientesRuta.value = todos
      renderizarDistribucionEnMapa()
    } else {
      await consultarDias()
    }
  } catch (err) {
    console.error('Error al registrar cambio de día:', err)
  }
}

async function cambiarEstadoPropuesta(propuestaId, estado) {
  try {
    await axios.post(route('supervisor.saneamiento.dias.cambiar-estado'), {
      propuesta_id: propuestaId,
      estado: estado,
    })
    await consultarDias()
  } catch (err) {
    console.error(err)
  }
}

async function revertirPropuesta(propuestaId) {
  try {
    await axios.post(route('supervisor.saneamiento.dias.revertir'), {
      propuesta_id: propuestaId,
      ruta: rutaSeleccionada.value,
    })
    await consultarDias()
  } catch (err) {
    console.error(err)
  }
}

async function guardarFronteraDiaActual() {
  const dia = diaParaEditarFrontera.value
  const info = diasData.value[dia]
  if (!info || !info.poligono || info.poligono.length < 3) return

  try {
    await axios.post(route('supervisor.saneamiento.dias.guardar-frontera'), {
      ruta: rutaSeleccionada.value,
      dia: dia,
      coordenadas: info.poligono,
    })
    await consultarDias()
  } catch (err) {
    console.error('Error al guardar frontera de día:', err)
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>

