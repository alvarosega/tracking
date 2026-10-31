<template>
  <SupervisorLayout title="Saneamiento | Clientes Duplicados">
    <div class="space-y-4">
      <SaneamientoSubTabs active-tab="duplicados" />

      <!-- Barra de Filtros y Configuración de Radio -->
      <div class="apple-glass-card rounded-[16px] p-2.5 sm:p-3 mb-3 select-none font-sans">
        <div class="flex flex-wrap items-center justify-between gap-2.5 text-[11px]">
          
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector Canal -->
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

            <!-- Selector de Radio en Metros -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Radio:</span>
              <select
                v-model="radioMetros"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
              >
                <option :value="10">10 metros</option>
                <option :value="20">20 metros (Recomendado)</option>
                <option :value="50">50 metros</option>
                <option :value="100">100 metros</option>
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="ejecutarAuditoriaDuplicados"
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
              <span>{{ cargando ? 'Auditando...' : 'Auditar Duplicados' }}</span>
            </button>
          </div>

          <!-- Badges Resumen -->
          <div class="flex items-center gap-2 text-[10px] font-mono sm:ml-auto">
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-[#C9342C] font-bold">
              Grupos: {{ clusters.length }}
            </span>
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-[#6E6E73]">
              Clientes Implicados: {{ totalClientesImplicados }}
            </span>
          </div>

        </div>
      </div>

      <!-- Contenedor Principal: Mapa y Lista de Grupos -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        <!-- Mapa (2 columnas) -->
        <div class="lg:col-span-2 relative glass-panel rounded-card overflow-hidden shadow-subtle min-h-[580px] flex flex-col border border-black/[0.06] bg-white/70 backdrop-blur-md">
          <div class="px-4 py-2 border-b border-neutral-100 flex flex-wrap items-center justify-between gap-2 bg-white/80 shrink-0">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
              <span class="font-bold text-[12px] text-ink">Geolocalización de Colisiones Espaciales & Datos</span>
            </div>

            <div class="flex items-center gap-2 text-[10px]">
              <!-- Selector de Capa Calles / Satélite -->
              <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
                <button
                  type="button"
                  @click="cambiarCapaMapa('openStreetMap')"
                  class="px-2 py-0.5 rounded font-medium transition-all cursor-pointer"
                  :class="capaActual === 'openStreetMap' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
                >
                  Calles
                </button>
                <button
                  type="button"
                  @click="cambiarCapaMapa('satellite')"
                  class="px-2 py-0.5 rounded font-medium transition-all cursor-pointer"
                  :class="capaActual === 'satellite' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
                >
                  Satélite
                </button>
              </div>

              <!-- Botón Recentrar -->
              <button
                type="button"
                @click="recentrarMapa"
                class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded font-semibold text-slate-700 cursor-pointer shadow-2xs"
                title="Recentrar mapa en los duplicados encontrados"
              >
                Recentrar
              </button>

              <span class="text-[10px] text-ink-secondary font-mono border-l border-slate-200 pl-2">Radio: {{ radioMetros }}m</span>
            </div>
          </div>

          <div id="mapaDuplicados" class="flex-1 w-full min-h-[520px] h-[520px] relative z-0"></div>
        </div>

        <!-- Panel Lateral de Grupos (1 columna) -->
        <div class="glass-panel rounded-card shadow-subtle flex flex-col overflow-hidden min-h-[580px] max-h-[600px] border border-black/[0.06] bg-white/70 backdrop-blur-md">
          <div class="px-3 py-2 border-b border-neutral-100 flex items-center justify-between bg-white/80 shrink-0">
            <span class="font-bold text-[11px] text-ink">Colisiones Detectadas</span>
            <span class="text-[10px] font-mono text-ink-secondary">{{ clusters.length }} grupos</span>
          </div>

          <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-3 text-[11px]">
            <div v-if="clusters.length === 0" class="text-center py-20 text-[#86868B]">
              {{ cargando ? 'Ejecutando auditoría de proximidad...' : `No se encontraron clientes duplicados dentro del radio de ${radioMetros} metros.` }}
            </div>

            <!-- Tarjeta de Grupo de Duplicados -->
            <div
              v-for="grupo in clusters"
              :key="grupo.cluster_id"
              class="p-3 rounded-card border bg-white/80 hover:bg-white shadow-2xs space-y-2 transition-all cursor-pointer"
              :class="grupoSeleccionadoId === grupo.cluster_id ? 'border-rose-400 ring-2 ring-rose-200' : 'border-rose-200'"
              @click="enfocarGrupoEnMapa(grupo)"
            >
              <div class="flex items-center justify-between pb-1.5 border-b border-neutral-100">
                <div class="flex items-center gap-1.5">
                  <span class="font-bold font-mono text-[11px]" :class="grupo.clasificacion === 'DOBLE_CODIGO' ? 'text-rose-700' : 'text-blue-700'">
                    Grupo #{{ grupo.cluster_id }}
                  </span>
                  <span
                    class="px-1.5 py-0.2 rounded text-[9px] font-bold font-mono uppercase"
                    :class="grupo.clasificacion === 'DOBLE_CODIGO' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800'"
                  >
                    {{ grupo.clasificacion === 'DOBLE_CODIGO' ? 'Doble Código' : 'Misma Ubicación' }}
                  </span>
                </div>
                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-rose-50 text-rose-600 font-bold">
                  {{ grupo.distancia_minima_m.toFixed(1) }}m
                </span>
              </div>

              <!-- Criterios Compartidos -->
              <div v-if="grupo.criterios && grupo.criterios.length > 0" class="flex flex-wrap gap-1">
                <span
                  v-for="crit in grupo.criterios"
                  :key="crit.campo"
                  class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-neutral-100 text-neutral-700 border border-neutral-200"
                >
                  {{ crit.campo }}: {{ crit.valores.join(', ') }}
                </span>
              </div>

              <!-- Clientes del Grupo -->
              <div class="space-y-1.5">
                <div
                  v-for="cli in grupo.clientes"
                  :key="cli.cliente_id"
                  class="p-2 rounded bg-neutral-50/70 border border-neutral-200/60 text-[10px] space-y-1"
                >
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-ink leading-tight truncate pr-2">{{ cli.cliente }}</span>
                    <span class="font-mono text-ink-secondary shrink-0">ID: {{ cli.cliente_id }}</span>
                  </div>
                  <div class="flex items-center justify-between text-ink-secondary font-mono text-[9px]">
                    <span>Ruta: <strong class="text-ink">{{ cli.ruta }}</strong> ({{ cli.dia }})</span>
                    <span class="text-emerald-700 font-bold">MTD: Bs. {{ cli.venta_mtd }}</span>
                  </div>
                  <div class="flex items-center justify-between pt-1 border-t border-neutral-200/60 text-[9px]">
                    <span class="text-neutral-500 truncate max-w-[140px]">{{ cli.direccion }}</span>
                    <button
                      type="button"
                      @click.stop="abrirModalFotos(cli)"
                      class="px-1.5 py-0.2 bg-white hover:bg-neutral-100 text-ink rounded font-bold border border-neutral-200 shadow-2xs"
                    >
                      Fotos
                    </button>
                  </div>
                </div>
              </div>

              <!-- Venta total del grupo -->
              <div class="pt-1 flex items-center justify-between text-[10px] font-mono text-neutral-600">
                <span>Venta Total MTD:</span>
                <span class="font-bold text-ink">Bs. {{ grupo.venta_mtd_total }}</span>
              </div>

              <!-- Acciones de Resolución -->
              <div class="pt-2 border-t border-neutral-100 flex flex-wrap items-center justify-end gap-1.5">
                <button
                  type="button"
                  @click.stop="proponerInactivar(grupo)"
                  class="px-2 py-1 bg-neutral-900 hover:bg-black text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                >
                  Inactivar Duplicado
                </button>
                <button
                  type="button"
                  @click.stop="proponerFusion(grupo)"
                  class="px-2 py-1 bg-[#0071E3] hover:bg-[#0077ED] text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                >
                  Fusionar
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Tabla Inferior: Propuestas Pendientes de Duplicados -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex flex-wrap items-center justify-between gap-2 bg-white/80 shrink-0">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Propuestas de Resolución de Duplicados</span>
            <span class="text-[10px] font-mono text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-bold">
              {{ propuestasFiltradas.length }} pendientes
            </span>
          </div>

          <!-- Buscador rápido para la tabla si hay varias filas -->
          <div v-if="propuestasPendientes.length > 3" class="w-64">
            <input
              v-model="busquedaTabla"
              type="text"
              placeholder="Buscar por cliente, ID o ruta..."
              class="w-full h-7 px-2.5 text-[11px] bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
            />
          </div>
        </div>

        <!-- Contenedor con Scroll Vertical y Horizontal -->
        <div class="overflow-x-auto overflow-y-auto max-h-[380px] custom-scroll relative">
          <table class="w-full text-left text-[11px] border-collapse">
            <thead class="sticky top-0 bg-slate-100 text-slate-700 uppercase text-[9.5px] font-mono font-bold tracking-wider border-b border-slate-200 z-10 shadow-2xs">
              <tr>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Cliente Origen</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">ID Origen</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Cliente Vinculado</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Ruta</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Acción Propuesta</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Observaciones</th>
                <th class="py-2.5 px-3 bg-slate-100 text-center whitespace-nowrap">Estado</th>
                <th class="py-2.5 px-3 bg-slate-100 text-right whitespace-nowrap">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="propuestasFiltradas.length === 0">
                <td colspan="8" class="py-8 text-center text-[#86868B] font-medium">
                  {{ busquedaTabla ? 'No se encontraron propuestas coincidentes con la búsqueda.' : 'No hay propuestas pendientes de resolución de duplicados.' }}
                </td>
              </tr>
              <tr
                v-for="p in propuestasFiltradas"
                :key="p.id"
                class="hover:bg-neutral-50/70 transition-colors"
              >
                <td class="py-2 px-3 font-bold text-ink">{{ p.cliente_nombre }}</td>
                <td class="py-2 px-3 font-mono text-ink-secondary">{{ p.cliente_id_origen }}</td>
                <td class="py-2 px-3 font-mono text-blue-700 font-bold">{{ p.cliente_id_vinculado || '-' }}</td>
                <td class="py-2 px-3 font-mono">{{ p.ruta_actual }}</td>
                <td class="py-2 px-3 font-mono">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-100 text-neutral-800 border border-neutral-200">
                    {{ p.accion_propuesta }}
                  </span>
                </td>
                <td class="py-2 px-3 text-neutral-500 truncate max-w-xs">{{ p.observaciones }}</td>
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { mapsConfig } from '@/Config/maps'
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
const radioMetros = ref(20)
const clusters = ref([])
const propuestasPendientes = ref([])
const grupoSeleccionadoId = ref(null)
const busquedaTabla = ref('')

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap')
let map = null
let currentTileLayer = null
let layerGroupMarkers = null
let mapResizeObserver = null

const canalesDisponibles = computed(() => props.catalogo.canales || [])

const totalClientesImplicados = computed(() => {
  let count = 0
  clusters.value.forEach(cl => {
    count += cl.cantidad_clientes || 0
  })
  return count
})

const propuestasFiltradas = computed(() => {
  if (!busquedaTabla.value.trim()) return propuestasPendientes.value
  const q = busquedaTabla.value.toLowerCase().trim()
  return propuestasPendientes.value.filter(p =>
    (p.cliente_nombre && p.cliente_nombre.toLowerCase().includes(q)) ||
    (p.cliente_id_origen && String(p.cliente_id_origen).includes(q)) ||
    (p.cliente_id_vinculado && String(p.cliente_id_vinculado).includes(q)) ||
    (p.ruta_actual && p.ruta_actual.toLowerCase().includes(q)) ||
    (p.accion_propuesta && p.accion_propuesta.toLowerCase().includes(q)) ||
    (p.observaciones && p.observaciones.toLowerCase().includes(q))
  )
})

onMounted(() => {
  nextTick(() => {
    inicializarMapa()
    ejecutarAuditoriaDuplicados()

    const container = document.getElementById('mapaDuplicados')
    if (container && window.ResizeObserver) {
      mapResizeObserver = new ResizeObserver(() => {
        if (map) map.invalidateSize()
      })
      mapResizeObserver.observe(container)
    }
  })
})

onUnmounted(() => {
  if (mapResizeObserver) {
    mapResizeObserver.disconnect()
    mapResizeObserver = null
  }
  if (map) {
    map.remove()
    map = null
  }
})

function cambiarCapaMapa(nombreCapa) {
  if (!map) return
  const layerConf = mapsConfig.tileLayers[nombreCapa] || mapsConfig.tileLayers.openStreetMap
  capaActual.value = nombreCapa

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer)
  }

  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map)
}

function inicializarMapa() {
  const container = document.getElementById('mapaDuplicados')
  if (!container || map) return

  const defaultLoc = mapsConfig.defaultLocation || { lat: -16.5050, lng: -68.1650, zoom: 13 }

  map = L.map('mapaDuplicados', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom)

  L.control.zoom({ position: 'topright' }).addTo(map)
  cambiarCapaMapa(capaActual.value)

  layerGroupMarkers = L.featureGroup().addTo(map)

  setTimeout(() => {
    if (map) {
      map.invalidateSize()
    }
  }, 250)
}

function recentrarMapa() {
  if (!map) return
  const bounds = []
  clusters.value.forEach(grupo => {
    if (!Array.isArray(grupo.clientes)) return
    grupo.clientes.forEach(cli => {
      const lat = parseFloat(cli.latitud)
      const lng = parseFloat(cli.longitud)
      if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
        bounds.push([lat, lng])
      }
    })
  })

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 })
  } else {
    const defaultLoc = mapsConfig.defaultLocation || { lat: -16.5050, lng: -68.1650, zoom: 13 }
    map.setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom)
  }
}

function alCambiarCanal() {
  ejecutarAuditoriaDuplicados()
}

async function ejecutarAuditoriaDuplicados() {
  cargando.value = true
  try {
    const rutasCanal = canalSeleccionado.value === 'TODOS'
      ? []
      : props.catalogo.rutas.filter(r => r.canal === canalSeleccionado.value).map(r => r.ruta)

    const [resAudit, resProps] = await Promise.all([
      axios.post(route('supervisor.saneamiento.duplicados.auditar'), {
        rutas: rutasCanal.length > 0 ? rutasCanal : null,
        radio_metros: radioMetros.value,
      }),
      axios.get(route('supervisor.saneamiento.duplicados.propuestas-pendientes'), {
        params: { rutas: rutasCanal.length > 0 ? rutasCanal : null },
      }),
    ])

    clusters.value = resAudit.data?.clusters || []
    propuestasPendientes.value = resProps.data || []

    renderizarClustersEnMapa()
  } catch (err) {
    console.error('Error al auditar duplicados:', err)
  } finally {
    cargando.value = false
    setTimeout(() => {
      if (map) map.invalidateSize()
    }, 200)
  }
}

function renderizarClustersEnMapa() {
  if (!map) return
  if (!layerGroupMarkers) {
    layerGroupMarkers = L.featureGroup().addTo(map)
  }
  layerGroupMarkers.clearLayers()

  const bounds = []

  clusters.value.forEach(grupo => {
    const isDobleCodigo = grupo.clasificacion === 'DOBLE_CODIGO'
    const color = isDobleCodigo ? '#DC2626' : '#2563EB'

    if (!Array.isArray(grupo.clientes)) return

    grupo.clientes.forEach(cli => {
      const lat = parseFloat(cli.latitud)
      const lng = parseFloat(cli.longitud)

      if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) {
        return
      }

      bounds.push([lat, lng])

      const circle = L.circle([lat, lng], {
        radius: radioMetros.value || 20,
        color: color,
        weight: 1.5,
        fillColor: color,
        fillOpacity: 0.12,
      })
      layerGroupMarkers.addLayer(circle)

      const marker = L.circleMarker([lat, lng], {
        radius: 6.5,
        color: '#FFFFFF',
        weight: 2,
        fillColor: color,
        fillOpacity: 0.95,
      })

      marker.bindPopup(`
        <div style="font-family: inherit; font-size: 11px; color: #0F172A; min-width: 190px; padding: 2px;">
          <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">${escapeHtml(cli.cliente)}</div>
          <div style="color: #64748B; font-family: monospace; font-size: 10px;">ID: ${cli.cliente_id} &bull; Ruta: <b>${escapeHtml(cli.ruta)}</b> (${escapeHtml(cli.dia || 'S/D')})</div>
          <div style="color: #64748B; font-size: 10px; margin-top: 2px;">${escapeHtml(cli.direccion || 'Sin dirección')}</div>
          <div style="margin-top: 4px; font-family: monospace; font-weight: 700; color: #059669;">MTD: Bs. ${cli.venta_mtd}</div>
          <div style="margin-top: 4px; padding-top: 4px; border-top: 1px solid #E2E8F0; font-size: 10px; font-weight: 700; color: ${isDobleCodigo ? '#DC2626' : '#2563EB'};">
            ${isDobleCodigo ? 'Doble Código Potencial' : 'Proximidad Extrema'}
          </div>
        </div>
      `, { maxWidth: 260 })

      marker.on('click', () => {
        grupoSeleccionadoId.value = grupo.cluster_id
      })

      layerGroupMarkers.addLayer(marker)
    })
  })

  if (bounds.length > 0) {
    try {
      map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 })
    } catch {
      // ignore
    }
  }

  setTimeout(() => {
    if (map) map.invalidateSize()
  }, 100)
}

function enfocarGrupoEnMapa(grupo) {
  grupoSeleccionadoId.value = grupo.cluster_id
  if (!map || !grupo.clientes || grupo.clientes.length === 0) return

  const bounds = []
  grupo.clientes.forEach(c => {
    const lat = parseFloat(c.latitud)
    const lng = parseFloat(c.longitud)
    if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
      bounds.push([lat, lng])
    }
  })

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 18 })
  }
}

function escapeHtml(str) {
  if (!str) return ''
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

async function proponerInactivar(grupo) {
  if (!grupo.clientes || grupo.clientes.length < 2) return
  // El cliente con menor venta se propone inactivar respecto al de mayor venta
  const ordenados = [...grupo.clientes].sort((a, b) => a.venta_mtd - b.venta_mtd)
  const menor = ordenados[0]
  const mayor = ordenados[ordenados.length - 1]

  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: menor.cliente_id,
      cliente_id_vinculado: mayor.cliente_id,
      cliente_nombre: menor.cliente,
      ruta_actual: menor.ruta,
      dia_actual: menor.dia,
      accion_propuesta: 'INACTIVAR_DUPLICADO',
      distancia_metros: grupo.distancia_minima_m,
      observaciones: `Inactivación por doble código respecto a cliente ID #${mayor.cliente_id}`,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error('Error al registrar propuesta:', err)
  }
}

async function proponerFusion(grupo) {
  if (!grupo.clientes || grupo.clientes.length < 2) return
  const c1 = grupo.clientes[0]
  const c2 = grupo.clientes[1]

  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: c1.cliente_id,
      cliente_id_vinculado: c2.cliente_id,
      cliente_nombre: c1.cliente,
      ruta_actual: c1.ruta,
      dia_actual: c1.dia,
      accion_propuesta: 'FUSIONAR_CLIENTE',
      distancia_metros: grupo.distancia_minima_m,
      observaciones: `Fusión de cuentas de cliente ID #${c1.cliente_id} y ID #${c2.cliente_id}`,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error('Error al registrar propuesta de fusión:', err)
  }
}

async function cambiarEstadoPropuesta(propuestaId, estado) {
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.cambiar-estado'), {
      propuesta_id: propuestaId,
      estado: estado,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error(err)
  }
}

async function revertirPropuesta(propuestaId) {
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.revertir'), {
      propuesta_id: propuestaId,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error(err)
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>

