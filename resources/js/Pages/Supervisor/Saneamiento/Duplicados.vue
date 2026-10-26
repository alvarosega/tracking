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
          <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
              <span class="font-bold text-[12px] text-ink">Geolocalización de Colisiones Espaciales & Datos</span>
            </div>
            <span class="text-[10px] text-ink-secondary font-mono">Radio de Proximidad: {{ radioMetros }}m</span>
          </div>

          <div id="mapaDuplicados" class="flex-1 w-full min-h-[500px] relative z-0"></div>
        </div>

        <!-- Panel Lateral de Grupos (1 columna) -->
        <div class="glass-panel rounded-card shadow-subtle flex flex-col overflow-hidden min-h-[580px] border border-black/[0.06] bg-white/70 backdrop-blur-md">
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
        <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Propuestas de Resolución de Duplicados</span>
            <span class="text-[10px] font-mono text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-bold">
              {{ propuestasPendientes.length }} pendientes
            </span>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll max-h-72">
          <table class="w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
              <tr>
                <th class="py-2.5 px-3">Cliente Origen</th>
                <th class="py-2.5 px-3">ID Origen</th>
                <th class="py-2.5 px-3">Cliente Vinculado</th>
                <th class="py-2.5 px-3">Ruta</th>
                <th class="py-2.5 px-3">Acción Propuesta</th>
                <th class="py-2.5 px-3">Observaciones</th>
                <th class="py-2.5 px-3 text-center">Estado</th>
                <th class="py-2.5 px-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="propuestasPendientes.length === 0">
                <td colspan="8" class="py-8 text-center text-[#86868B] font-medium">
                  No hay propuestas pendientes de resolución de duplicados.
                </td>
              </tr>
              <tr
                v-for="p in propuestasPendientes"
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

const cargando = ref(false)
const canalSeleccionado = ref('TDB')
const radioMetros = ref(20)
const clusters = ref([])
const propuestasPendientes = ref([])
const grupoSeleccionadoId = ref(null)

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

let map = null
let layerGroupMarkers = null

const canalesDisponibles = computed(() => props.catalogo.canales || [])

const totalClientesImplicados = computed(() => {
  let count = 0
  clusters.value.forEach(cl => {
    count += cl.cantidad_clientes || 0
  })
  return count
})

onMounted(() => {
  inicializarMapa()
  ejecutarAuditoriaDuplicados()
})

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
})

function inicializarMapa() {
  const container = document.getElementById('mapaDuplicados')
  if (!container || map) return

  map = L.map('mapaDuplicados', {
    zoomControl: true,
    preferCanvas: true,
  }).setView([-17.7833, -63.1821], 12)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '&copy; CartoDB &copy; OpenStreetMap',
  }).addTo(map)

  layerGroupMarkers = L.layerGroup().addTo(map)
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
  }
}

function renderizarClustersEnMapa() {
  if (!map || !layerGroupMarkers) return
  layerGroupMarkers.clearLayers()

  const bounds = []

  clusters.value.forEach(grupo => {
    const isDobleCodigo = grupo.clasificacion === 'DOBLE_CODIGO'
    const color = isDobleCodigo ? '#DC2626' : '#2563EB'

    grupo.clientes.forEach(cli => {
      bounds.push([cli.latitud, cli.longitud])

      const circle = L.circle([cli.latitud, cli.longitud], {
        radius: radioMetros.value,
        color: color,
        weight: 1,
        fillColor: color,
        fillOpacity: 0.1,
      })
      layerGroupMarkers.addLayer(circle)

      const marker = L.circleMarker([cli.latitud, cli.longitud], {
        radius: 6,
        color: '#FFFFFF',
        weight: 2,
        fillColor: color,
        fillOpacity: 0.95,
      })

      marker.bindPopup(`
        <div class="p-2 font-sans text-xs">
          <div class="font-bold text-slate-900">${cli.cliente}</div>
          <div class="text-[10px] text-slate-500 font-mono">ID: ${cli.cliente_id} • Ruta: ${cli.ruta}</div>
          <div class="text-[10px] text-slate-500">${cli.direccion || ''}</div>
          <div class="mt-1 font-mono font-bold text-emerald-700">MTD: Bs. ${cli.venta_mtd}</div>
          <div class="mt-1 pt-1 border-t text-[10px] font-bold ${isDobleCodigo ? 'text-rose-600' : 'text-blue-600'}">
            ${isDobleCodigo ? 'Doble Código Potencial' : 'Proximidad Extrema'}
          </div>
        </div>
      `)

      marker.on('click', () => {
        grupoSeleccionadoId.value = grupo.cluster_id
      })

      layerGroupMarkers.addLayer(marker)
    })
  })

  if (bounds.length > 0) {
    try {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 })
    } catch {
      // ignore
    }
  }
}

function enfocarGrupoEnMapa(grupo) {
  grupoSeleccionadoId.value = grupo.cluster_id
  if (!map || !grupo.clientes || grupo.clientes.length === 0) return

  const bounds = grupo.clientes.map(c => [c.latitud, c.longitud])
  map.fitBounds(bounds, { padding: [50, 50], maxZoom: 18 })
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

