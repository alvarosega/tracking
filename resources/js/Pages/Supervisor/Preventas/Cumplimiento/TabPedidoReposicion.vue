<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 6: Pedido de Reposición (Canasta Habitual 60%)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#86868B] bg-white/80 border border-black/[0.08]">
            15 pts Base · Módulo en Calibración
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Reposición de SKUs habituales comprados en las últimas 13 semanas. Umbral exigido: &ge; 60.0% de coincidencia de canasta.
        </p>
      </div>
      <div class="flex items-center gap-2 text-[11px]">
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Rutas Evaluadas:
          <strong class="text-[#1D1D1F] font-mono ml-0.5">{{ store.loadingRanking ? '…' : rutasOrdenadas.length }}</strong>
        </span>
      </div>
    </div>

    <!-- Tabla Principal -->
    <div class="overflow-x-auto custom-scroll max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[900px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-2.5 py-2 text-center sticky left-0 z-30 w-12 border-r border-black/[0.05] bg-[#F8F8FA]">Pos</th>
            <th class="px-3 py-2 sticky left-12 z-30 w-28 border-r border-black/[0.05] bg-[#F8F8FA]">Ruta</th>
            <th class="px-3 py-2 sticky left-[112px] z-30 min-w-[180px] border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.04)] bg-[#F8F8FA]">Preventista</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Compradores</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">SKUs Esperados</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Match Repo</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Fuera Canasta</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">% Reposición</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Estatus</th>
            <th class="px-3 py-2 text-right">Puntos (15)</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-rep-${i}`">
              <td class="px-2.5 py-2 sticky left-0"><div class="h-3.5 w-5 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 sticky left-12"><div class="h-3.5 w-16 skeleton"></div></td>
              <td class="px-3 py-2 sticky left-[112px]"><div class="h-3.5 w-32 skeleton"></div></td>
              <td v-for="c in 6" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="10" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas para los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <template v-for="(r, idx) in rutasOrdenadas" :key="r.ruta || idx">
              <tr
                @click="toggleReposicionAcordeon(r)"
                class="cursor-pointer transition-colors"
                :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-black/[0.02]'"
              >
                <!-- Pos -->
                <td class="px-2.5 py-1.5 text-center font-mono text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 border-r border-black/[0.04] bg-white/70 backdrop-blur-sm">
                  {{ idx + 1 }}
                </td>

                <!-- Ruta -->
                <td class="px-3 py-1.5 font-bold text-[#1D1D1F] text-[12px] sticky left-12 z-10 border-r border-black/[0.04] bg-white/70 backdrop-blur-sm whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <svg
                      class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                      :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span>{{ r.ruta }}</span>
                  </div>
                </td>

                <!-- Preventista -->
                <td class="px-3 py-1.5 text-[#6E6E73] font-medium text-[11px] sticky left-[112px] z-10 border-r border-black/[0.05] bg-white/70 backdrop-blur-sm truncate max-w-[200px]" :title="r.vendedor">
                  {{ r.vendedor || 'No Asignado' }}
                </td>

                <!-- Compradores -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ r.kpi6_reposicion?.evaluables ?? r.kpis?.pedido_reposicion?.metricas?.evaluables ?? '—' }}
                </td>

                <!-- SKUs Esperados -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ detallePorRuta[r.ruta]?.totales?.skus_esperados_total ?? '—' }}
                </td>

                <!-- Match Repo -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]">
                  {{ detallePorRuta[r.ruta]?.totales?.skus_con_match_total ?? '—' }}
                </td>

                <!-- Fuera Canasta -->
                <td class="px-3 py-1.5 text-center font-mono text-[#6E6E73] border-r border-black/[0.04]">
                  {{ detallePorRuta[r.ruta]?.totales?.skus_fuera_canasta_total ?? '—' }}
                </td>

                <!-- % Reposición -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span v-if="detallePorRuta[r.ruta]?.totales?.porcentaje_reposicion_ruta !== undefined">
                    {{ Number(detallePorRuta[r.ruta].totales.porcentaje_reposicion_ruta).toFixed(1) }}%
                  </span>
                  <span v-else class="text-[#86868B]">—</span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-2 text-center border-r border-black/[0.04]">
                  <span class="inline-flex px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-300">
                    CALIBRACIÓN
                  </span>
                </td>

                <!-- Puntos (15) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span class="text-[#86868B]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Detalle de Tiendas y Canasta Habitual -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="10" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Auditando canastas de reposición para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="space-y-3">
                    <div class="apple-glass-card rounded-[12px] overflow-hidden">
                      <div class="px-3 py-2 border-b border-black/[0.06] flex items-center justify-between bg-white/50 text-[11px]">
                        <span class="font-bold text-[#1D1D1F]">
                          Auditoría de Canastas de Tiendas: {{ r.ruta }}
                        </span>
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px] font-mono text-[10px] text-[#6E6E73]">
                          Total PDVs Compradores: <strong>{{ (detallePorRuta[r.ruta].tiendas || []).length }}</strong>
                        </span>
                      </div>

                      <div v-if="(detallePorRuta[r.ruta].tiendas || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px]">
                        No se encontraron tiendas evaluables para reposición en la fecha.
                      </div>

                      <div v-else class="divide-y divide-black/[0.04] max-h-80 overflow-y-auto custom-scroll">
                        <div
                          v-for="t in detallePorRuta[r.ruta].tiendas"
                          :key="t.cliente_id"
                          class="p-2.5 text-[11px]"
                        >
                          <div class="flex items-center justify-between font-medium text-[#1D1D1F] mb-1">
                            <div>
                              <strong class="font-bold">{{ t.cliente }}</strong>
                              <span class="text-[#6E6E73] font-mono ml-2 text-[10px]">#{{ t.cliente_id }}</span>
                            </div>
                            <div class="flex items-center gap-2 font-mono text-[10px]">
                              <span class="text-[#1E6B37] font-bold">Match: {{ t.skus_con_match }}</span>
                              <span class="text-[#6E6E73]">/ Esperados: {{ t.skus_esperados }}</span>
                              <span class="font-bold">({{ Number(t.porcentaje_reposicion || 0).toFixed(1) }}%)</span>
                            </div>
                          </div>

                          <!-- Lista de SKUs esperados y recomprados -->
                          <div class="flex flex-wrap gap-1 mt-1">
                            <span
                              v-for="sku in (t.skus_habituales || [])"
                              :key="sku.codigo_producto"
                              class="px-2 py-0.5 rounded text-[9px] font-medium"
                              :class="sku.recomprado_hoy ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]' : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                            >
                              {{ sku.descripcion || sku.codigo_producto }} ({{ sku.recomprado_hoy ? 'Recomprado' : 'Faltó' }})
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'
import { usePreventaStore } from '@/Stores/preventaStore'

const store = usePreventaStore()

const rutasOrdenadas = computed(() => {
  return [...(store.rutasFiltradas || [])]
})

const acordeonAbierto = reactive({})
const cargandoDetalle = reactive({})
const errorDetalle = reactive({})
const detallePorRuta = reactive({})

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
  }
)

async function toggleReposicionAcordeon(r) {
  const ruta = r.ruta
  if (acordeonAbierto[ruta]) {
    acordeonAbierto[ruta] = false
    return
  }
  acordeonAbierto[ruta] = true
  if (detallePorRuta[ruta]) return

  cargandoDetalle[ruta] = true
  errorDetalle[ruta] = null
  try {
    const res = await axios.post('/supervisor/preventas/cumplimiento/reposicion/detalle', {
      fecha: store.fecha,
      ruta,
      semanas_historia: 13
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar auditoría de reposición'
  } finally {
    cargandoDetalle[ruta] = false
  }
}
</script>

