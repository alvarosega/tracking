<template>
  <SupervisorLayout title="Saneamiento | Historial de Propuestas">
    <div class="space-y-3 font-sans select-none pb-8">
      <SaneamientoSubTabs active-tab="propuestas" />

      <!-- Barra de Filtros, Búsqueda y Acciones Masivas -->
      <div class="apple-glass-card rounded-[14px] p-2.5 sm:p-3 border border-black/[0.06] bg-white/80 shadow-2xs space-y-2.5">
        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
          
          <div class="flex flex-wrap items-center gap-2">
            <!-- Filtro de Tipo de Hallazgo -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[9px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Hallazgo:</span>
              <select
                v-model="filtroTipo"
                @change="cargarPropuestas"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los hallazgos</option>
                <option value="INTRUSO_FRONTERA">Intrusos en frontera</option>
                <option value="DIA_DESALINEADO">Días desalineados</option>
                <option value="DUPLICADO">Clientes duplicados</option>
                <option value="AJUSTE_RTM">Ajustes RTM</option>
                <option value="ACTIVACION_Y_AJUSTE_RTM">Activación & RTM</option>
                <option value="ACTIVACION_CLIENTE">Activación de cliente</option>
              </select>
            </div>

            <!-- Filtro de Estado -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[9px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Estado:</span>
              <select
                v-model="filtroEstado"
                @change="cargarPropuestas"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los estados</option>
                <option value="PENDIENTE">Solo Pendientes</option>
                <option value="APLICADO">Solo Aprobados (Aplicados)</option>
                <option value="DESCARTADO">Solo Descartados</option>
              </select>
            </div>

            <!-- Filtro de Ruta -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[9px] px-2.5 py-1" v-if="rutasDisponibles.length > 0">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
              <select
                v-model="filtroRuta"
                @change="cargarPropuestas"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer max-w-[130px] truncate"
              >
                <option value="TODOS">Todas las rutas</option>
                <option v-for="r in rutasDisponibles" :key="r" :value="r">
                  {{ r }}
                </option>
              </select>
            </div>

            <!-- Buscador General -->
            <div class="relative w-48 sm:w-60">
              <input
                v-model="busqueda"
                @keydown.enter="cargarPropuestas"
                type="text"
                placeholder="Buscar cliente, cód o ID..."
                class="w-full h-7 pl-7 pr-6 text-[11px] rounded-[9px] bg-black/[0.04] border border-black/[0.06] focus:bg-white focus:border-[#0071E3] focus:outline-none transition-all placeholder:text-[#86868B]"
              />
              <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <button
                v-if="busqueda"
                @click="busqueda = ''; cargarPropuestas()"
                class="absolute right-2 top-1.5 text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold"
              >
                &times;
              </button>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="cargarPropuestas"
              :disabled="cargando"
              class="flex items-center gap-1.5 px-3 py-1 rounded-[9px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all shadow-[0_2px_8px_rgba(0,113,227,0.3)] cursor-pointer disabled:opacity-75"
            >
              <svg v-if="cargando" class="w-3 h-3 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <span>{{ cargando ? 'Consultando...' : 'Consultar' }}</span>
            </button>
          </div>

          <!-- Acciones Masivas y Contador de Seleccionados -->
          <div class="flex items-center gap-2 flex-wrap sm:ml-auto">
            <span v-if="seleccionados.length > 0" class="text-[10px] font-mono text-[#6E6E73]">
              {{ seleccionados.length }} pend. seleccionado(s)
            </span>

            <button
              type="button"
              @click="aprobarSeleccionados"
              :disabled="seleccionados.length === 0 || accionProcesando"
              class="px-2.5 py-1 bg-[#1E6B37] hover:bg-[#16562B] text-white rounded-[8px] font-bold text-[11px] shadow-2xs transition-all cursor-pointer disabled:opacity-40"
            >
              Aprobar ({{ seleccionados.length }})
            </button>

            <button
              type="button"
              @click="descartarSeleccionados"
              :disabled="seleccionados.length === 0 || accionProcesando"
              class="px-2.5 py-1 bg-[#C9342C] hover:bg-[#B3261E] text-white rounded-[8px] font-bold text-[11px] shadow-2xs transition-all cursor-pointer disabled:opacity-40"
            >
              Descartar ({{ seleccionados.length }})
            </button>
          </div>

        </div>

        <!-- Barra Resumen de Métricas Rápidas -->
        <div class="pt-2 border-t border-black/[0.04] flex items-center justify-between gap-2 text-[10px] font-medium text-[#6E6E73] overflow-x-auto no-scrollbar">
          <div class="flex items-center gap-3">
            <span>Total propuestas: <strong class="text-[#1D1D1F] font-mono">{{ metricas.total }}</strong></span>
            <span>&bull;</span>
            <span class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#B7791F]"></span>
              Pendientes: <strong class="text-[#B7791F] font-mono">{{ metricas.pendientes }}</strong>
            </span>
            <span>&bull;</span>
            <span class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#1E6B37]"></span>
              Aprobadas: <strong class="text-[#1E6B37] font-mono">{{ metricas.aplicados }}</strong>
            </span>
            <span>&bull;</span>
            <span class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-[#6E6E73]"></span>
              Descartadas: <strong class="text-[#6E6E73] font-mono">{{ metricas.descartados }}</strong>
            </span>
          </div>

          <div v-if="accionMensaje" class="text-[10px] font-semibold text-[#0071E3] bg-[#0071E3]/10 px-2 py-0.5 rounded">
            {{ accionMensaje }}
          </div>
        </div>
      </div>

      <!-- Tabla Principal de Historial de Propuestas -->
      <div class="apple-glass-card rounded-[14px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/80">
        <div class="overflow-x-auto no-scrollbar scroll-touch touch-pan-x custom-scroll max-h-[calc(100vh-270px)] min-h-[350px]">
          <table class="w-full text-left text-[11px] border-collapse">
            <thead class="sticky top-0 bg-[#F5F5F7] text-[#86868B] uppercase text-[9px] font-mono tracking-wider border-b border-black/[0.06] z-10">
              <tr>
                <th class="py-2 px-3 w-10 text-center">
                  <input
                    type="checkbox"
                    :checked="esTodoSeleccionado"
                    @change="alternarSeleccionarTodo"
                    :disabled="propuestasPendientes.length === 0"
                    title="Seleccionar todas las propuestas pendientes"
                    class="rounded border-[#D1D1D6] text-[#0071E3] focus:ring-0 cursor-pointer disabled:opacity-40"
                  />
                </th>
                <th class="py-2 px-3 whitespace-nowrap">Fecha / ID</th>
                <th class="py-2 px-3 whitespace-nowrap">Hallazgo</th>
                <th class="py-2 px-3 min-w-[200px]">Cliente</th>
                <th class="py-2 px-3 whitespace-nowrap">Ruta: Actual &rarr; Propuesta</th>
                <th class="py-2 px-3 whitespace-nowrap">Día: Actual &rarr; Propuesto</th>
                <th class="py-2 px-3 min-w-[150px]">Acción / Observación</th>
                <th class="py-2 px-3 text-center whitespace-nowrap">Estado</th>
                <th class="py-2 px-3 text-right whitespace-nowrap w-28">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/60">
              <tr v-if="propuestas.length === 0">
                <td colspan="9" class="py-16 text-center text-[#86868B] font-medium text-xs">
                  <div class="space-y-1">
                    <p>{{ cargando ? 'Cargando historial de propuestas...' : 'No se encontraron propuestas registradas con los filtros aplicados.' }}</p>
                    <p v-if="!cargando" class="text-[10px] text-[#A1A1A6]">Intenta cambiar el tipo de hallazgo, estado o término de búsqueda.</p>
                  </div>
                </td>
              </tr>
              <tr
                v-for="p in propuestas"
                :key="p.id"
                class="hover:bg-black/[0.02] transition-colors"
                :class="seleccionados.includes(p.id) ? 'bg-[#0071E3]/[0.04]' : ''"
              >
                <!-- Checkbox solo para PENDIENTES -->
                <td class="py-2 px-3 text-center align-middle">
                  <input
                    v-if="p.estado === 'PENDIENTE'"
                    type="checkbox"
                    :value="p.id"
                    v-model="seleccionados"
                    class="rounded border-[#D1D1D6] text-[#0071E3] focus:ring-0 cursor-pointer"
                  />
                  <span v-else class="text-[#D1D1D6] text-[10px]">&mdash;</span>
                </td>

                <!-- Fecha y ID -->
                <td class="py-2 px-3 font-mono text-[10px] text-[#6E6E73] align-middle whitespace-nowrap">
                  <div>{{ p.creado_en || '-' }}</div>
                  <div class="text-[9px] text-[#86868B]">#{{ p.id }}</div>
                </td>

                <!-- Tipo Hallazgo -->
                <td class="py-2 px-3 align-middle whitespace-nowrap">
                  <span
                    class="px-2 py-0.5 rounded text-[9px] font-bold border font-mono inline-block"
                    :class="obtenerBadgeTipoClase(p.tipo_hallazgo)"
                  >
                    {{ formatearTipoHallazgo(p.tipo_hallazgo) }}
                  </span>
                </td>

                <!-- Cliente -->
                <td class="py-2 px-3 align-middle min-w-[200px]">
                  <div class="space-y-0.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="font-bold text-[#1D1D1F] text-xs leading-snug">
                        {{ p.cliente_nombre }}
                      </span>
                      <button
                        type="button"
                        @click="abrirModalFotos({ cliente_id: p.cliente_id_origen, cliente: p.cliente_nombre, ruta: p.ruta_actual })"
                        class="px-1.5 py-0.2 rounded text-[9px] font-semibold text-[#0071E3] bg-[#0071E3]/10 hover:bg-[#0071E3]/20 cursor-pointer transition-colors"
                        title="Ver fotos de visita"
                      >
                        Fotos
                      </button>
                    </div>

                    <div class="text-[10px] font-mono text-[#86868B] flex items-center gap-1.5">
                      <span>Cód. #{{ p.cliente_id_origen }}</span>
                      <span v-if="p.cliente_id_vinculado" class="text-purple-600 font-semibold">
                        &bull; Vinculado: #{{ p.cliente_id_vinculado }}
                      </span>
                      <span v-if="p.distancia_metros > 0" class="text-[#6E6E73]">
                        &bull; {{ Math.round(p.distancia_metros) }} m
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Ruta: Actual -> Propuesta -->
                <td class="py-2 px-3 font-mono text-[11px] align-middle whitespace-nowrap">
                  <span class="text-[#C9342C] font-semibold">{{ p.ruta_actual || '-' }}</span>
                  <span class="text-[#86868B] mx-1">&rarr;</span>
                  <span class="text-[#1E6B37] font-bold">{{ p.ruta_propuesta || '-' }}</span>
                </td>

                <!-- Día: Actual -> Propuesto -->
                <td class="py-2 px-3 font-mono text-[11px] align-middle whitespace-nowrap">
                  <span class="text-[#6E6E73] capitalize">{{ p.dia_actual || '-' }}</span>
                  <span class="text-[#86868B] mx-1">&rarr;</span>
                  <span class="font-bold text-[#1D1D1F] capitalize">{{ p.dia_propuesto || '-' }}</span>
                </td>

                <!-- Acción y Observaciones -->
                <td class="py-2 px-3 align-middle text-[10.5px] leading-snug min-w-[150px]">
                  <div class="font-mono text-[10px] font-semibold text-[#1D1D1F]">
                    {{ p.accion_propuesta || '-' }}
                  </div>
                  <div v-if="p.observaciones" class="text-[#6E6E73] text-[10px] truncate max-w-xs" :title="p.observaciones">
                    {{ p.observaciones }}
                  </div>
                </td>

                <!-- Estado -->
                <td class="py-2 px-3 text-center align-middle whitespace-nowrap">
                  <span
                    class="px-2 py-0.5 rounded text-[9px] font-mono font-bold border"
                    :class="{
                      'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]': p.estado === 'PENDIENTE',
                      'bg-[#EAF7EE] text-[#1E6B37] border-[#C2E8CE]': p.estado === 'APLICADO',
                      'bg-black/[0.04] text-[#6E6E73] border-black/[0.08]': p.estado === 'DESCARTADO'
                    }"
                  >
                    {{ p.estado }}
                  </span>
                </td>

                <!-- Acciones -->
                <td class="py-2 px-3 text-right align-middle whitespace-nowrap w-28">
                  <div class="inline-flex items-center gap-1" v-if="p.estado === 'PENDIENTE'">
                    <button
                      type="button"
                      @click="cambiarEstado(p.id, 'APLICADO')"
                      :disabled="accionProcesando"
                      class="px-2 py-0.5 bg-[#1E6B37] hover:bg-[#16562B] text-white rounded-[6px] text-[10px] font-bold shadow-2xs cursor-pointer transition-all disabled:opacity-50"
                      title="Aprobar y aplicar propuesta"
                    >
                      Aprobar
                    </button>
                    <button
                      type="button"
                      @click="cambiarEstado(p.id, 'DESCARTADO')"
                      :disabled="accionProcesando"
                      class="px-2 py-0.5 bg-[#C9342C] hover:bg-[#B3261E] text-white rounded-[6px] text-[10px] font-bold shadow-2xs cursor-pointer transition-all disabled:opacity-50"
                      title="Descartar propuesta"
                    >
                      Descartar
                    </button>
                  </div>

                  <div v-else class="inline-flex items-center gap-1">
                    <span class="text-[10px] text-[#86868B] font-mono italic">
                      Finalizado
                    </span>
                    <button
                      type="button"
                      @click="cambiarEstado(p.id, 'PENDIENTE')"
                      :disabled="accionProcesando"
                      class="px-1.5 py-0.2 text-[9px] text-[#6E6E73] hover:text-[#0071E3] hover:bg-black/[0.04] rounded transition-all cursor-pointer font-medium"
                      title="Reabrir propuesta como PENDIENTE"
                    >
                      Reabrir
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Fotos del Cliente -->
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
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
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
const accionProcesando = ref(false)
const accionMensaje = ref('')

const filtroTipo = ref('TODOS')
const filtroEstado = ref('TODOS')
const filtroRuta = ref('TODOS')
const busqueda = ref('')

const propuestas = ref([])
const seleccionados = ref([])

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

// Lista de rutas únicas obtenidas del catálogo maestro
const rutasDisponibles = computed(() => {
  const rSet = new Set((props.catalogo?.rutas || []).map(r => r.ruta).filter(Boolean))
  return Array.from(rSet).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
})

// Propuestas pendientes que admiten selección masiva
const propuestasPendientes = computed(() => {
  return propuestas.value.filter(p => p.estado === 'PENDIENTE')
})

const esTodoSeleccionado = computed(() => {
  return (
    propuestasPendientes.value.length > 0 &&
    seleccionados.value.length === propuestasPendientes.value.length
  )
})

// Métricas en tiempo real sobre el dataset visualizado
const metricas = computed(() => {
  const total = propuestas.value.length
  let pendientes = 0
  let aplicados = 0
  let descartados = 0

  for (const p of propuestas.value) {
    if (p.estado === 'PENDIENTE') pendientes++
    else if (p.estado === 'APLICADO') aplicados++
    else if (p.estado === 'DESCARTADO') descartados++
  }

  return { total, pendientes, aplicados, descartados }
})

onMounted(() => {
  cargarPropuestas()
})

async function cargarPropuestas() {
  cargando.value = true
  seleccionados.value = []
  try {
    const params = {}
    if (filtroTipo.value !== 'TODOS') params.tipo = filtroTipo.value
    if (filtroEstado.value !== 'TODOS') params.estado = filtroEstado.value
    if (filtroRuta.value !== 'TODOS') params.ruta = filtroRuta.value
    if (busqueda.value.trim() !== '') params.busqueda = busqueda.value.trim()

    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.propuestas.historial')
      : '/supervisor/saneamiento/propuestas/historial'

    const { data } = await axios.get(url, { params })
    propuestas.value = Array.isArray(data) ? data : []
  } catch (err) {
    console.error('Error al cargar historial de propuestas:', err)
  } finally {
    cargando.value = false
  }
}

function alternarSeleccionarTodo() {
  if (esTodoSeleccionado.value) {
    seleccionados.value = []
  } else {
    seleccionados.value = propuestasPendientes.value.map(p => p.id)
  }
}

function mostrarFeedback(msg) {
  accionMensaje.value = msg
  setTimeout(() => {
    if (accionMensaje.value === msg) {
      accionMensaje.value = ''
    }
  }, 4000)
}

async function cambiarEstado(propuestaId, nuevoEstado) {
  accionProcesando.value = true
  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.propuestas.actualizar-estado')
      : '/supervisor/saneamiento/propuestas/actualizar-estado'

    await axios.post(url, {
      propuesta_id: propuestaId,
      estado: nuevoEstado,
    })

    mostrarFeedback(`Propuesta #${propuestaId} actualizada a ${nuevoEstado}.`)
    await cargarPropuestas()
  } catch (err) {
    console.error('Error al actualizar estado:', err)
  } finally {
    accionProcesando.value = false
  }
}

async function aprobarSeleccionados() {
  if (seleccionados.value.length === 0) return
  accionProcesando.value = true
  const total = seleccionados.value.length
  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.propuestas.actualizar-estado-masivo')
      : '/supervisor/saneamiento/propuestas/actualizar-estado-masivo'

    await axios.post(url, {
      ids: seleccionados.value,
      estado: 'APLICADO',
    })

    mostrarFeedback(`${total} propuesta(s) aprobada(s) exitosamente.`)
    await cargarPropuestas()
  } catch (err) {
    console.error('Error al aprobar masivamente:', err)
  } finally {
    accionProcesando.value = false
  }
}

async function descartarSeleccionados() {
  if (seleccionados.value.length === 0) return
  accionProcesando.value = true
  const total = seleccionados.value.length
  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.propuestas.actualizar-estado-masivo')
      : '/supervisor/saneamiento/propuestas/actualizar-estado-masivo'

    await axios.post(url, {
      ids: seleccionados.value,
      estado: 'DESCARTADO',
    })

    mostrarFeedback(`${total} propuesta(s) descartada(s) exitosamente.`)
    await cargarPropuestas()
  } catch (err) {
    console.error('Error al descartar masivamente:', err)
  } finally {
    accionProcesando.value = false
  }
}

function formatearTipoHallazgo(tipo) {
  switch (tipo) {
    case 'INTRUSO_FRONTERA':
      return 'Intruso Frontera'
    case 'DIA_DESALINEADO':
      return 'Día Desalineado'
    case 'DUPLICADO':
      return 'Duplicado'
    case 'AJUSTE_RTM':
      return 'Ajuste RTM'
    case 'ACTIVACION_Y_AJUSTE_RTM':
      return 'Act. & RTM'
    case 'ACTIVACION_CLIENTE':
      return 'Activación'
    default:
      return tipo || 'Propuesta'
  }
}

function obtenerBadgeTipoClase(tipo) {
  switch (tipo) {
    case 'INTRUSO_FRONTERA':
      return 'bg-rose-50 text-rose-700 border-rose-200'
    case 'DIA_DESALINEADO':
      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'DUPLICADO':
      return 'bg-purple-50 text-purple-700 border-purple-200'
    case 'AJUSTE_RTM':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'ACTIVACION_Y_AJUSTE_RTM':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'ACTIVACION_CLIENTE':
      return 'bg-teal-50 text-teal-700 border-teal-200'
    default:
      return 'bg-gray-50 text-gray-700 border-gray-200'
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>
