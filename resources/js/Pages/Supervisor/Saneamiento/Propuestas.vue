<template>
  <SupervisorLayout title="Saneamiento | Historial de Propuestas">
    <div class="space-y-4">
      <SaneamientoSubTabs active-tab="propuestas" />

      <!-- Barra de Filtros y Acción Masiva -->
      <div class="apple-glass-card rounded-[16px] p-2.5 sm:p-3 mb-3 select-none font-sans border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-2.5 text-[11px]">
          
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
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
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="cargarPropuestas"
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
          </div>

          <!-- Acciones Masivas -->
          <div class="flex items-center gap-2 sm:ml-auto">
            <button
              type="button"
              @click="aprobarSeleccionados"
              :disabled="seleccionados.length === 0"
              class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-[10px] font-bold shadow-2xs transition-all cursor-pointer disabled:opacity-40"
            >
              Aprobar seleccionados ({{ seleccionados.length }})
            </button>

            <button
              type="button"
              @click="descartarSeleccionados"
              :disabled="seleccionados.length === 0"
              class="px-3.5 py-1.5 bg-[#C9342C] hover:bg-[#B3261E] text-white rounded-[10px] font-bold shadow-2xs transition-all cursor-pointer disabled:opacity-40"
            >
              Descartar seleccionados ({{ seleccionados.length }})
            </button>
          </div>

        </div>
      </div>

      <!-- Tabla Principal de Trazabilidad -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Historial General de Propuestas</span>
            <span class="text-[10px] font-mono text-ink-secondary">{{ propuestas.length }} registros totales</span>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll max-h-[calc(100vh-280px)] min-h-[400px]">
          <table class="w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
              <tr>
                <th class="py-2.5 px-3 w-10 text-center">
                  <input
                    type="checkbox"
                    :checked="esTodoSeleccionado"
                    @change="alternarSeleccionarTodo"
                    class="rounded border-neutral-300 text-black accent-black cursor-pointer"
                  />
                </th>
                <th class="py-2.5 px-3">Fecha / Registro</th>
                <th class="py-2.5 px-3">Tipo Hallazgo</th>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">Ruta Actual → Propuesta</th>
                <th class="py-2.5 px-3">Día Actual → Propuesto</th>
                <th class="py-2.5 px-3">Acción</th>
                <th class="py-2.5 px-3 text-center">Estado</th>
                <th class="py-2.5 px-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="propuestas.length === 0">
                <td colspan="9" class="py-16 text-center text-[#86868B] font-medium">
                  {{ cargando ? 'Cargando historial de propuestas...' : 'No hay propuestas registradas.' }}
                </td>
              </tr>
              <tr
                v-for="p in propuestas"
                :key="p.id"
                class="hover:bg-neutral-50/70 transition-colors"
                :class="seleccionados.includes(p.id) ? 'bg-blue-50/40' : ''"
              >
                <td class="py-2 px-3 text-center">
                  <input
                    type="checkbox"
                    :value="p.id"
                    v-model="seleccionados"
                    class="rounded border-neutral-300 text-black accent-black cursor-pointer"
                  />
                </td>
                <td class="py-2 px-3 font-mono text-neutral-500 text-[10px]">{{ p.creado_en || '-' }}</td>
                <td class="py-2 px-3 font-mono">
                  <span
                    class="px-2 py-0.5 rounded text-[9px] font-bold"
                    :class="{
                      'bg-rose-100 text-rose-800': p.tipo_hallazgo === 'INTRUSO_FRONTERA',
                      'bg-blue-100 text-blue-800': p.tipo_hallazgo === 'DIA_DESALINEADO',
                      'bg-purple-100 text-purple-800': p.tipo_hallazgo === 'DUPLICADO'
                    }"
                  >
                    {{ p.tipo_hallazgo }}
                  </span>
                </td>
                <td class="py-2 px-3 font-bold text-ink">
                  <div class="flex items-center gap-1.5">
                    <span>{{ p.cliente_nombre }}</span>
                    <button
                      type="button"
                      @click="abrirModalFotos({ cliente_id: p.cliente_id_origen, cliente: p.cliente_nombre, ruta: p.ruta_actual })"
                      class="px-1.5 py-0.2 bg-white hover:bg-neutral-100 text-ink rounded font-bold border border-neutral-200 shadow-2xs text-[9px] cursor-pointer"
                    >
                      Fotos
                    </button>
                  </div>
                  <div class="text-[9px] font-mono text-[#86868B]">ID: {{ p.cliente_id_origen }}</div>
                </td>
                <td class="py-2 px-3 font-mono">
                  <span class="text-rose-700 font-bold">{{ p.ruta_actual }}</span>
                  <span class="text-neutral-400 mx-1">→</span>
                  <span class="text-emerald-700 font-bold">{{ p.ruta_propuesta }}</span>
                </td>
                <td class="py-2 px-3 font-mono">
                  <span>{{ p.dia_actual }}</span>
                  <span class="text-neutral-400 mx-1">→</span>
                  <span class="font-bold text-ink">{{ p.dia_propuesto }}</span>
                </td>
                <td class="py-2 px-3 font-mono text-neutral-600">{{ p.accion_propuesta }}</td>
                <td class="py-2 px-3 text-center">
                  <span
                    class="px-2 py-0.5 rounded text-[9px] font-mono font-bold"
                    :class="{
                      'bg-amber-100 text-amber-800 border border-amber-200': p.estado === 'PENDIENTE',
                      'bg-emerald-100 text-emerald-800 border border-emerald-200': p.estado === 'APLICADO',
                      'bg-rose-100 text-rose-800 border border-rose-200': p.estado === 'DESCARTADO'
                    }"
                  >
                    {{ p.estado }}
                  </span>
                </td>
                <td class="py-2 px-3 text-right space-x-1 whitespace-nowrap">
                  <template v-if="p.estado === 'PENDIENTE'">
                    <button
                      type="button"
                      @click="cambiarEstado(p.id, 'APLICADO')"
                      class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                    >
                      Aprobar
                    </button>
                    <button
                      type="button"
                      @click="cambiarEstado(p.id, 'DESCARTADO')"
                      class="px-2 py-0.5 bg-rose-600 hover:bg-rose-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                    >
                      Descartar
                    </button>
                  </template>
                  <span v-else class="text-[10px] text-neutral-400 font-mono italic">
                    Finalizado
                  </span>
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
const filtroTipo = ref('TODOS')
const propuestas = ref([])
const seleccionados = ref([])

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

const esTodoSeleccionado = computed(() => {
  return propuestas.value.length > 0 && seleccionados.value.length === propuestas.value.length
})

onMounted(() => {
  cargarPropuestas()
})

async function cargarPropuestas() {
  cargando.value = true
  seleccionados.value = []
  try {
    const params = filtroTipo.value !== 'TODOS' ? { tipo: filtroTipo.value } : {}
    const { data } = await axios.get(route('supervisor.saneamiento.propuestas.historial'), { params })
    propuestas.value = data || []
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
    seleccionados.value = propuestas.value.map(p => p.id)
  }
}

async function cambiarEstado(propuestaId, nuevoEstado) {
  try {
    await axios.post(route('supervisor.saneamiento.propuestas.actualizar-estado'), {
      propuesta_id: propuestaId,
      estado: nuevoEstado,
    })
    await cargarPropuestas()
  } catch (err) {
    console.error('Error al actualizar estado:', err)
  }
}

async function aprobarSeleccionados() {
  if (seleccionados.value.length === 0) return
  try {
    await axios.post(route('supervisor.saneamiento.propuestas.actualizar-estado-masivo'), {
      ids: seleccionados.value,
      estado: 'APLICADO',
    })
    await cargarPropuestas()
  } catch (err) {
    console.error('Error al aprobar masivamente:', err)
  }
}

async function descartarSeleccionados() {
  if (seleccionados.value.length === 0) return
  try {
    await axios.post(route('supervisor.saneamiento.propuestas.actualizar-estado-masivo'), {
      ids: seleccionados.value,
      estado: 'DESCARTADO',
    })
    await cargarPropuestas()
  } catch (err) {
    console.error('Error al descartar masivamente:', err)
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>

