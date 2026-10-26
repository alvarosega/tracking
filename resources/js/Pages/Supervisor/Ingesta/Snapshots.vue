<template>
  <SupervisorLayout title="Snapshots del Plan de Ruteo">
    <div class="space-y-4 max-w-7xl mx-auto pb-8 font-sans">
      <!-- Sub-navegación -->
      <IngestaSubTabs active-tab="snapshots" />

      <!-- Encabezado con Botón Volver -->
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
              Snapshots y Versiones del Plan de Ruteo
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                INMUTABLES
              </span>
            </h1>
            <p class="text-[11px] text-[#86868B] mt-0.5">Control de versiones históricas con capacidad de restauración y rollback seguro del padrón activo</p>
          </div>
        </div>

        <button
          type="button"
          @click="recargar"
          :disabled="cargando"
          class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#1D1D1F] border border-black/[0.12] hover:bg-black/[0.03] transition-all cursor-pointer flex items-center gap-1.5 shadow-sm"
        >
          <span :class="cargando ? 'animate-spin' : ''">↻</span>
          <span>Actualizar</span>
        </button>
      </div>

      <!-- Alertas de Éxito / Error -->
      <div
        v-if="mensajeExito"
        class="p-3.5 rounded-[14px] bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold shadow-sm flex items-center justify-between"
      >
        <span>{{ mensajeExito }}</span>
        <button type="button" @click="mensajeExito = null" class="text-emerald-800 hover:opacity-70 p-0.5">✕</button>
      </div>

      <!-- Tabla de Snapshots -->
      <section class="apple-glass-card rounded-[18px] shadow-sm overflow-hidden border border-black/[0.04]">
        <div class="px-4 py-3 border-b border-black/[0.06] flex items-center justify-between bg-white/80 backdrop-blur-sm">
          <div>
            <h3 class="text-[13px] font-bold text-[#1D1D1F]">Historial de Versiones Preservadas</h3>
            <p class="text-[10px] text-[#86868B]">Copias de seguridad automáticas generadas durante cada reemplazo maestro de ruteo</p>
          </div>
          <span class="text-[10px] font-semibold text-[#86868B] font-num bg-black/[0.04] px-2 py-0.5 rounded-full border border-black/[0.05]">
            {{ snapshotsList.length }} snapshots
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-separate border-spacing-0 font-num text-[11px]">
            <thead class="bg-black/[0.02] text-[#6E6E73] font-bold text-[9px] uppercase tracking-wider">
              <tr>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">ID Snapshot</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Fecha Creación</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Archivo Origen</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Total Registros</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Rutas Únicas</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-center">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/70">
              <tr v-if="cargando && snapshotsList.length === 0">
                <td colspan="6" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  Consultando catálogo de versiones históricas...
                </td>
              </tr>
              <tr v-else-if="snapshotsList.length === 0">
                <td colspan="6" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  No se registran snapshots de ruteo previos en pan_ruteo_snapshots.
                </td>
              </tr>
              <tr
                v-else
                v-for="s in snapshotsList"
                :key="s.id"
                class="hover:bg-[#0071E3]/[0.02] transition-colors"
              >
                <td class="py-2.5 px-3.5 font-mono font-bold text-[#1D1D1F]">
                  <span class="bg-black/[0.05] px-1.5 py-0.5 rounded border border-black/[0.08]">#{{ s.id }}</span>
                </td>
                <td class="py-2.5 px-3.5 text-[#6E6E73] whitespace-nowrap">{{ s.creado_en || s.fecha }}</td>
                <td class="py-2.5 px-3.5 text-[#1D1D1F] truncate max-w-xs" :title="s.archivo_original">
                  {{ s.archivo_original || '-' }}
                </td>
                <td class="py-2.5 px-3.5 text-right font-bold text-[#1D1D1F]">
                  {{ Number(s.total_filas || 0).toLocaleString('es-BO') }}
                </td>
                <td class="py-2.5 px-3.5 text-right font-medium text-[#6E6E73]">
                  {{ s.total_rutas || '-' }}
                </td>
                <td class="py-2.5 px-3.5 text-center">
                  <button
                    type="button"
                    @click="revertir(s.id)"
                    :disabled="revertiendo"
                    class="px-3 py-1 text-[10px] font-bold rounded-[8px] bg-white text-[#1D1D1F] border border-black/[0.15] hover:bg-black hover:text-white transition-all cursor-pointer shadow-sm disabled:opacity-50"
                  >
                    Restaurar Snapshot
                  </button>
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
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import IngestaSubTabs from './Components/IngestaSubTabs.vue'

const props = defineProps({
  snapshots: {
    type: Array,
    default: () => [],
  },
  submoduloActivo: {
    type: String,
    default: 'snapshots',
  },
})

const snapshotsList = ref([...props.snapshots])
const revertiendo = ref(false)
const cargando = ref(false)
const mensajeExito = ref(null)

async function revertir(id) {
  const conf = confirm(`¿Estás seguro de restaurar el plan de ruteo al Snapshot #${id}? Esto reemplazará la cartera activa actual de pan_ruteo.`)
  if (!conf) return

  revertiendo.value = true
  mensajeExito.value = null

  try {
    const { data } = await axios.post(route('supervisor.ingesta.ruteo.rollback'), { snapshot_id: id })
    mensajeExito.value = data.mensaje || `El plan de ruteo fue restaurado exitosamente al snapshot #${id}.`
    await recargar()
  } catch (err) {
    alert(`Error al revertir: ${err.response?.data?.message || err.message}`)
  } finally {
    revertiendo.value = false
  }
}

async function recargar() {
  cargando.value = true
  try {
    const { data } = await axios.get(route('supervisor.ingesta.ruteo.snapshots'))
    snapshotsList.value = data.snapshots || []
  } catch (err) {
    console.error('Error al recargar snapshots:', err)
  } finally {
    cargando.value = false
  }
}
</script>

