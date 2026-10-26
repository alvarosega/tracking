<template>
  <SupervisorLayout title="Auditoría de Preventas Canceladas">
    <div class="space-y-4 max-w-7xl mx-auto pb-8 font-sans">
      <!-- Sub-navegación -->
      <IngestaSubTabs active-tab="cancelados" />

      <!-- Encabezado con Botón Volver y Autorizar -->
      <div class="apple-glass-card rounded-[18px] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-black/[0.04]">
        <div class="flex items-center gap-3">
          <Link
            :href="route('supervisor.ingesta.index')"
            class="px-2.5 py-1 text-[11px] font-semibold rounded-[10px] bg-white text-[#6E6E73] border border-black/[0.12] hover:text-[#1D1D1F] hover:bg-black/[0.03] transition-all flex items-center gap-1 shadow-sm cursor-pointer"
          >
            ← Volver
          </Link>
          <div>
            <h1 class="text-[17px] font-bold text-[#1D1D1F] tracking-tight flex items-center gap-2">
              Auditoría de Preventas Canceladas
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200">
                PENDIENTES DE REVISIÓN
              </span>
            </h1>
            <p class="text-[11px] text-[#86868B] mt-0.5">Autorización y purga controlada de pedidos que desaparecieron en cortes posteriores</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="recargar"
            :disabled="cargando"
            class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#1D1D1F] border border-black/[0.12] hover:bg-black/[0.03] transition-all cursor-pointer flex items-center gap-1.5 shadow-sm"
          >
            <span :class="cargando ? 'animate-spin' : ''">↻</span>
            <span>Actualizar</span>
          </button>

          <button
            v-if="seleccionados.length > 0"
            type="button"
            @click="autorizarSeleccionados"
            :disabled="procesando"
            class="px-3.5 py-1.5 text-[11px] font-bold rounded-[10px] bg-red-600 text-white hover:bg-red-700 transition-all shadow-[0_2px_8px_rgba(220,38,38,0.35)] cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
          >
            <span>Autorizar Eliminación ({{ seleccionados.length }})</span>
          </button>
        </div>
      </div>

      <!-- Alertas de Éxito / Error -->
      <div
        v-if="mensajeExito"
        class="p-3.5 rounded-[14px] bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold shadow-sm flex items-center justify-between"
      >
        <span>{{ mensajeExito }}</span>
        <button type="button" @click="mensajeExito = null" class="text-emerald-800 hover:opacity-70 p-0.5">✕</button>
      </div>

      <!-- Tabla de Cancelados Pendientes -->
      <section class="apple-glass-card rounded-[18px] shadow-sm overflow-hidden border border-black/[0.04]">
        <div class="px-4 py-3 border-b border-black/[0.06] flex items-center justify-between bg-white/80 backdrop-blur-sm">
          <div>
            <h3 class="text-[13px] font-bold text-[#1D1D1F]">Pedidos Pendientes de Autorización</h3>
            <p class="text-[10px] text-[#86868B]">Registros de auditoría con evento CANCELADO que requieren aprobación para descarte definitivo</p>
          </div>
          <span class="text-[10px] font-semibold text-[#86868B] font-num bg-black/[0.04] px-2 py-0.5 rounded-full border border-black/[0.05]">
            {{ pendientesList.length }} ítems pendientes
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-separate border-spacing-0 font-num text-[11px]">
            <thead class="bg-black/[0.02] text-[#6E6E73] font-bold text-[9px] uppercase tracking-wider">
              <tr>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-center w-10">
                  <input
                    type="checkbox"
                    :checked="todosSeleccionados"
                    @change="toggleTodos"
                    class="rounded cursor-pointer text-[#0071E3] focus:ring-[#0071E3]"
                  />
                </th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Nro Preventa</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Fecha Preventa</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Ruta / Vendedor</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Cliente</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Producto</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Cantidad Ant.</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Monto Cancelado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/70">
              <tr v-if="cargando && pendientesList.length === 0">
                <td colspan="8" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  Consultando pedidos cancelados pendientes...
                </td>
              </tr>
              <tr v-else-if="pendientesList.length === 0">
                <td colspan="8" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  No hay preventas canceladas pendientes de autorización.
                </td>
              </tr>
              <tr
                v-else
                v-for="item in pendientesList"
                :key="`${item.nro_preventa}-${item.producto_id}`"
                class="hover:bg-[#0071E3]/[0.02] transition-colors"
              >
                <td class="py-2.5 px-3.5 text-center">
                  <input
                    type="checkbox"
                    :value="item"
                    v-model="seleccionados"
                    class="rounded cursor-pointer text-[#0071E3] focus:ring-[#0071E3]"
                  />
                </td>
                <td class="py-2.5 px-3.5 font-bold text-[#1D1D1F]">#{{ item.nro_preventa }}</td>
                <td class="py-2.5 px-3.5 text-[#6E6E73] whitespace-nowrap">{{ item.fecha_norm || item.fecha_preventa }}</td>
                <td class="py-2.5 px-3.5">
                  <div class="font-semibold text-[#1D1D1F]">{{ item.ruta }}</div>
                  <div class="text-[10px] text-[#86868B]">{{ item.vendedor || 'Sin vendedor' }}</div>
                </td>
                <td class="py-2.5 px-3.5 text-[#1D1D1F]">{{ item.cliente }}</td>
                <td class="py-2.5 px-3.5 text-[#1D1D1F]">
                  <div>{{ item.producto }}</div>
                  <div class="text-[9px] text-[#86868B] font-mono">ID: {{ item.producto_id }}</div>
                </td>
                <td class="py-2.5 px-3.5 text-right font-medium text-[#1D1D1F]">
                  {{ Number(item.cantidad_anterior || 0).toLocaleString('es-BO') }}
                </td>
                <td class="py-2.5 px-3.5 text-right font-bold text-red-600">
                  Bs {{ Number(item.monto_final || item.monto_anterior || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import IngestaSubTabs from './Components/IngestaSubTabs.vue'

const props = defineProps({
  pendientes: {
    type: Array,
    default: () => [],
  },
  submoduloActivo: {
    type: String,
    default: 'cancelados',
  },
})

const pendientesList = ref([...props.pendientes])
const seleccionados = ref([])
const procesando = ref(false)
const cargando = ref(false)
const mensajeExito = ref(null)

const todosSeleccionados = computed(() => {
  return pendientesList.value.length > 0 && seleccionados.value.length === pendientesList.value.length
})

function toggleTodos(e) {
  if (e.target.checked) {
    seleccionados.value = [...pendientesList.value]
  } else {
    seleccionados.value = []
  }
}

async function autorizarSeleccionados() {
  if (seleccionados.value.length === 0) return

  const cantidad = seleccionados.value.length
  const confirmar = confirm(`¿Estás seguro de autorizar la eliminación de ${cantidad} pedidos cancelados? Esta acción purgará los registros de forma definitiva.`)
  if (!confirmar) return

  procesando.value = true
  mensajeExito.value = null

  try {
    const payload = seleccionados.value.map(i => ({
      nro_preventa: i.nro_preventa,
      producto_id: i.producto_id,
      fecha_norm: i.fecha_norm || i.fecha_preventa,
    }))

    const res = await axios.post(route('supervisor.ingesta.preventas.autorizar-cancelados'), { items: payload })
    mensajeExito.value = `Se autorizó y eliminó la cantidad de ${res.data?.purgados ?? payload.length} pedidos cancelados.`
    seleccionados.value = []
    await recargar()
  } catch (err) {
    alert(`Error: ${err.response?.data?.message || err.message}`)
  } finally {
    procesando.value = false
  }
}

async function recargar() {
  cargando.value = true
  try {
    const { data } = await axios.get(route('supervisor.ingesta.cancelados.pendientes'))
    pendientesList.value = data.pendientes || []
  } catch (err) {
    console.error('Error al recargar cancelados:', err)
  } finally {
    cargando.value = false
  }
}
</script>

