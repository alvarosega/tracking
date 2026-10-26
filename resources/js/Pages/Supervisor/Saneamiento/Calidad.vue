<template>
  <SupervisorLayout title="Saneamiento | Calidad de Datos">
    <div class="space-y-4">
      <SaneamientoSubTabs active-tab="calidad" />

      <!-- Barra de Filtros y Selector de Anomalía -->
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

            <!-- Filtro de Anomalía -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Anomalía:</span>
              <select
                v-model="filtroAnomalia"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todas las anomalías</option>
                <option value="NOMBRE_INVALIDO">Nombre Inválido / Incompleto</option>
                <option value="SIN_NUMERO">Sin Número / Dirección</option>
                <option value="SIN_NIT_CI">Sin NIT / CI</option>
                <option value="SIN_TELEFONO">Sin Teléfono / Celular</option>
                <option value="CORRECTOS">Datos Correctos</option>
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="consultarCalidad"
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
              <span>{{ cargando ? 'Auditando...' : 'Consultar' }}</span>
            </button>
          </div>

          <!-- Badges Métricos Rápidos -->
          <div class="flex items-center gap-2 font-mono text-[10px] sm:ml-auto">
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-[#C9342C] font-bold">
              Defectuosos: {{ totalDefectuosos }}
            </span>
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-[#28CD41] font-bold">
              Correctos: {{ totalCorrectos }}
            </span>
          </div>

        </div>
      </div>

      <!-- Píldoras de Conteo por Anomalía -->
      <div class="apple-glass-card p-2.5 rounded-[14px] shadow-subtle flex flex-wrap items-center gap-2 text-[10px] font-mono border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-2.5 py-1 rounded bg-white border border-neutral-200/70 text-ink shadow-2xs">
          Sin Nro/Dir: <strong class="text-rose-600 font-bold">{{ conteos.numero || 0 }}</strong>
        </div>
        <div class="px-2.5 py-1 rounded bg-white border border-neutral-200/70 text-ink shadow-2xs">
          Sin Tel/Cel: <strong class="text-amber-600 font-bold">{{ conteos.telefono || 0 }}</strong>
        </div>
        <div class="px-2.5 py-1 rounded bg-white border border-neutral-200/70 text-ink shadow-2xs">
          Nombre Inválido: <strong class="text-purple-600 font-bold">{{ conteos.nombre || 0 }}</strong>
        </div>
        <div class="px-2.5 py-1 rounded bg-white border border-neutral-200/70 text-ink shadow-2xs">
          Sin NIT/CI: <strong class="text-blue-600 font-bold">{{ conteos.nit || 0 }}</strong>
        </div>
      </div>

      <!-- Contenedor Principal: Tabla de Clientes y Detalle Lateral -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        <!-- Tabla de Clientes (2 columnas) -->
        <div class="lg:col-span-2 relative glass-panel rounded-card shadow-subtle flex flex-col overflow-hidden min-h-[550px] border border-black/[0.06] bg-white/70 backdrop-blur-md">
          <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
            <span class="font-bold text-[12px] text-ink">Registro de Clientes Auditados</span>
            <div class="flex items-center gap-2">
              <input
                v-model="filtroTexto"
                type="text"
                placeholder="Buscar por nombre o ID..."
                class="text-[10px] bg-neutral-100 rounded-[8px] px-2.5 py-1 focus:outline-none font-medium border border-neutral-200 w-48 font-mono"
              />
              <span class="text-[10px] font-mono text-ink-secondary">{{ clientesFiltrados.length }} registros</span>
            </div>
          </div>

          <div class="flex-1 overflow-x-auto overflow-y-auto custom-scroll max-h-[580px]">
            <table class="w-full text-left text-[11px]">
              <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
                <tr>
                  <th class="py-2.5 px-3">Cliente</th>
                  <th class="py-2.5 px-3">ID</th>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Dirección</th>
                  <th class="py-2.5 px-3">Teléfono</th>
                  <th class="py-2.5 px-3">NIT/CI</th>
                  <th class="py-2.5 px-3 text-center">Severidad</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100 bg-white/60">
                <tr v-if="clientesFiltrados.length === 0">
                  <td colspan="7" class="py-12 text-center text-[#86868B] font-medium">
                    No se encontraron clientes bajo el filtro seleccionado.
                  </td>
                </tr>
                <tr
                  v-for="cli in clientesFiltrados"
                  :key="cli.cliente_id"
                  @click="seleccionarCliente(cli)"
                  class="hover:bg-neutral-50/80 cursor-pointer transition-colors"
                  :class="clienteSeleccionado?.cliente_id === cli.cliente_id ? 'bg-blue-50/70 font-semibold' : ''"
                >
                  <td class="py-2 px-3 font-bold text-ink">
                    <div class="truncate max-w-xs">{{ cli.cliente || 'SIN NOMBRE' }}</div>
                  </td>
                  <td class="py-2 px-3 font-mono text-ink-secondary">{{ cli.cliente_id }}</td>
                  <td class="py-2 px-3 font-mono">
                    <span class="font-bold text-ink">{{ cli.ruta }}</span>
                    <span class="text-[9px] text-[#86868B] ml-1">({{ cli.dia }})</span>
                  </td>
                  <td class="py-2 px-3 text-neutral-600 truncate max-w-xs">
                    <span :class="cli.falta_numero ? 'text-rose-600 font-semibold' : ''">
                      {{ cli.direccion || 'Sin dirección' }}
                    </span>
                  </td>
                  <td class="py-2 px-3 font-mono">
                    <span :class="cli.falta_telefono ? 'text-amber-600 font-bold' : 'text-neutral-600'">
                      {{ cli.telefono || cli.celular || 'Sin teléfono' }}
                    </span>
                  </td>
                  <td class="py-2 px-3 font-mono">
                    <span :class="cli.falta_nit_ci ? 'text-blue-600 font-bold' : 'text-neutral-600'">
                      {{ cli.nit || cli.ci || 'Sin NIT/CI' }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[9px] font-mono font-bold"
                      :class="{
                        'bg-rose-100 text-rose-800 border border-rose-200': cli.nivel_severidad === 'CRITICO',
                        'bg-amber-100 text-amber-800 border border-amber-200': cli.nivel_severidad === 'ADVERTENCIA',
                        'bg-emerald-100 text-emerald-800 border border-emerald-200': cli.nivel_severidad === 'OK'
                      }"
                    >
                      {{ cli.nivel_severidad }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Detalle y Mapa Miniatura (1 columna) -->
        <div class="glass-panel rounded-card shadow-subtle flex flex-col overflow-hidden min-h-[550px] border border-black/[0.06] bg-white/70 backdrop-blur-md">
          <div class="px-3 py-2 border-b border-neutral-100 flex items-center justify-between bg-white/80 shrink-0">
            <span class="font-bold text-[11px] text-ink">Inspección de Cliente</span>
            <span v-if="clienteSeleccionado" class="text-[10px] font-mono text-ink-secondary">ID #{{ clienteSeleccionado.cliente_id }}</span>
          </div>

          <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-3 text-[11px]">
            <div v-if="!clienteSeleccionado" class="text-center py-20 text-[#86868B]">
              Selecciona un cliente de la lista para inspeccionar sus datos y ubicación geográfica.
            </div>

            <div v-else class="space-y-3">
              <!-- Datos Clave del Cliente -->
              <div class="p-3 rounded-card bg-white border border-neutral-200/80 shadow-2xs space-y-2">
                <div>
                  <h4 class="font-bold text-ink text-[13px] leading-tight">{{ clienteSeleccionado.cliente }}</h4>
                  <p class="text-[10px] font-mono text-[#86868B] mt-0.5">
                    Factura: {{ clienteSeleccionado.nombre_factura || 'No especificado' }}
                  </p>
                </div>

                <div class="grid grid-cols-2 gap-1.5 pt-1.5 border-t border-neutral-100 text-[10px] font-mono">
                  <div>
                    <span class="text-[#86868B] block text-[9px] uppercase">Ruta / Día:</span>
                    <strong class="text-ink">{{ clienteSeleccionado.ruta }} ({{ clienteSeleccionado.dia }})</strong>
                  </div>
                  <div>
                    <span class="text-[#86868B] block text-[9px] uppercase">NIT / CI:</span>
                    <strong class="text-ink">{{ clienteSeleccionado.nit || clienteSeleccionado.ci || 'Sin NIT/CI' }}</strong>
                  </div>
                  <div>
                    <span class="text-[#86868B] block text-[9px] uppercase">Teléfono:</span>
                    <strong class="text-ink">{{ clienteSeleccionado.telefono || clienteSeleccionado.celular || 'Sin número' }}</strong>
                  </div>
                  <div>
                    <span class="text-[#86868B] block text-[9px] uppercase">Número Domicilio:</span>
                    <strong class="text-ink">{{ clienteSeleccionado.nro || 'S/N' }}</strong>
                  </div>
                </div>

                <div class="pt-1.5 border-t border-neutral-100 text-[10px]">
                  <span class="text-[#86868B] block text-[9px] uppercase font-mono">Dirección:</span>
                  <p class="text-ink mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección registrada' }}</p>
                </div>

                <div class="pt-2 flex justify-end">
                  <button
                    type="button"
                    @click="abrirModalFotos(clienteSeleccionado)"
                    class="px-2.5 py-1 bg-white hover:bg-neutral-100 text-ink rounded-[8px] text-[10px] font-bold border border-neutral-200 shadow-2xs flex items-center gap-1 cursor-pointer"
                  >
                    <svg class="w-3 h-3 text-neutral-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Ver Fotos de Visita</span>
                  </button>
                </div>
              </div>

              <!-- Fallas Detectadas -->
              <div v-if="clienteSeleccionado.anomalias.length > 0" class="p-2.5 rounded-card bg-rose-50 border border-rose-200 space-y-1">
                <span class="text-[10px] font-bold text-rose-800 uppercase tracking-wider block">
                  Anomalías Detectadas ({{ clienteSeleccionado.anomalias.length }})
                </span>
                <ul class="list-disc list-inside text-[10px] text-rose-700 space-y-0.5">
                  <li v-if="clienteSeleccionado.falta_nombre">Nombre inválido, monosilábico o placeholder</li>
                  <li v-if="clienteSeleccionado.falta_numero">Sin número domiciliario o dirección menor a 5 caracteres</li>
                  <li v-if="clienteSeleccionado.falta_nit_ci">Sin número de NIT ni Carnet de Identidad</li>
                  <li v-if="clienteSeleccionado.falta_telefono">Sin número de teléfono o celular de contacto</li>
                </ul>
              </div>

              <!-- Mini Mapa de Ubicación -->
              <div class="rounded-card overflow-hidden border border-neutral-200/80 shadow-2xs">
                <div class="px-3 py-1.5 bg-neutral-50 text-[10px] font-mono text-[#86868B] border-b border-neutral-200">
                  Ubicación GPS: {{ clienteSeleccionado.latitud }}, {{ clienteSeleccionado.longitud }}
                </div>
                <div id="miniMapaCalidad" class="w-full h-44"></div>
              </div>
            </div>

          </div>
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
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

const cargando = ref(false)
const canalSeleccionado = ref('TDB')
const filtroAnomalia = ref('TODOS')
const filtroTexto = ref('')
const clientes = ref([])
const conteos = ref({ nombre: 0, numero: 0, nit: 0, telefono: 0 })
const clienteSeleccionado = ref(null)

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

let miniMap = null
let miniMapMarker = null

const canalesDisponibles = computed(() => props.catalogo.canales || [])

const totalDefectuosos = computed(() => clientes.value.filter(c => c.es_defectuoso).length)
const totalCorrectos = computed(() => clientes.value.filter(c => !c.es_defectuoso).length)

const clientesFiltrados = computed(() => {
  let list = clientes.value

  if (filtroAnomalia.value === 'NOMBRE_INVALIDO') {
    list = list.filter(c => c.falta_nombre)
  } else if (filtroAnomalia.value === 'SIN_NUMERO') {
    list = list.filter(c => c.falta_numero)
  } else if (filtroAnomalia.value === 'SIN_NIT_CI') {
    list = list.filter(c => c.falta_nit_ci)
  } else if (filtroAnomalia.value === 'SIN_TELEFONO') {
    list = list.filter(c => c.falta_telefono)
  } else if (filtroAnomalia.value === 'CORRECTOS') {
    list = list.filter(c => !c.es_defectuoso)
  }

  if (filtroTexto.value.trim()) {
    const q = filtroTexto.value.trim().toLowerCase()
    list = list.filter(c =>
      c.cliente.toLowerCase().includes(q) ||
      String(c.cliente_id).includes(q) ||
      c.direccion.toLowerCase().includes(q) ||
      c.ruta.toLowerCase().includes(q)
    )
  }

  return list
})

onMounted(() => {
  consultarCalidad()
})

onUnmounted(() => {
  if (miniMap) {
    miniMap.remove()
    miniMap = null
  }
})

function alCambiarCanal() {
  consultarCalidad()
}

async function consultarCalidad() {
  cargando.value = true
  try {
    const rutasCanal = canalSeleccionado.value === 'TODOS'
      ? props.catalogo.rutas.map(r => r.ruta)
      : props.catalogo.rutas.filter(r => r.canal === canalSeleccionado.value).map(r => r.ruta)

    const { data } = await axios.post(route('supervisor.saneamiento.calidad.auditar'), {
      rutas: rutasCanal,
    })

    clientes.value = data?.clientes || []
    conteos.value = data?.resumen_anomalias || { nombre: 0, numero: 0, nit: 0, telefono: 0 }

    if (clientes.value.length > 0 && !clienteSeleccionado.value) {
      seleccionarCliente(clientes.value[0])
    }
  } catch (err) {
    console.error('Error al auditar calidad de datos:', err)
  } finally {
    cargando.value = false
  }
}

function seleccionarCliente(cli) {
  clienteSeleccionado.value = cli
  nextTick(() => {
    actualizarMiniMapa(cli)
  })
}

function actualizarMiniMapa(cli) {
  const container = document.getElementById('miniMapaCalidad')
  if (!container || !cli.latitud || !cli.longitud) return

  if (!miniMap) {
    miniMap = L.map('miniMapaCalidad', {
      zoomControl: false,
      attributionControl: false,
    }).setView([cli.latitud, cli.longitud], 16)

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      maxZoom: 19,
    }).addTo(miniMap)

    miniMapMarker = L.circleMarker([cli.latitud, cli.longitud], {
      radius: 7,
      color: '#FFFFFF',
      weight: 2,
      fillColor: cli.es_defectuoso ? '#DC2626' : '#16A34A',
      fillOpacity: 1,
    }).addTo(miniMap)
  } else {
    miniMap.setView([cli.latitud, cli.longitud], 16)
    miniMapMarker.setLatLng([cli.latitud, cli.longitud])
    miniMapMarker.setStyle({
      fillColor: cli.es_defectuoso ? '#DC2626' : '#16A34A',
    })
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>

