<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 7: Ampliación de Portafolio (SKUs Nuevos 60%)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#86868B] bg-white/80 border border-black/[0.08]">
            20 pts Base · Módulo en Calibración
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Incorporación de al menos 2 SKUs no comprados en las últimas 13 semanas. Umbral exigido: &ge; 60.0% de los compradores.
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
      <table class="w-full text-left border-collapse min-w-[680px] sm:min-w-[880px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Pos</th>
            <th class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-14 sm:w-20 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Ruta</th>
            <th class="px-1.5 sm:px-2.5 py-2 sticky left-[84px] sm:left-[116px] z-30 w-20 sm:w-36 md:w-44 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] text-[10px] sm:text-[11px] truncate">Preventista</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Compradores</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">PDVs con Ampliación</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">% Ampliación</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Estatus</th>
            <th class="px-3 py-2 text-right">Puntos (20)</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-amp-${i}`">
              <td class="px-1 sm:px-2 py-2 sticky left-0 w-7 sm:w-9"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 w-14 sm:w-20"><div class="h-3.5 w-10 sm:w-14 skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-[84px] sm:left-[116px] w-20 sm:w-36 md:w-44"><div class="h-3.5 w-16 sm:w-28 skeleton"></div></td>
              <td v-for="c in 4" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="8" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas para los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <template v-for="(r, idx) in rutasOrdenadas" :key="r.ruta || idx">
              <tr
                @click="toggleAmpliacionAcordeon(r)"
                class="cursor-pointer transition-colors"
                :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-black/[0.02]'"
              >
                <!-- Pos -->
                <td class="px-1 sm:px-2 py-1.5 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-7 sm:w-9 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                  {{ idx + 1 }}
                </td>

                <!-- Ruta -->
                <td class="px-1.5 sm:px-2.5 py-1.5 font-bold text-[#1D1D1F] text-[10px] sm:text-[12px] sticky left-7 sm:left-9 z-10 w-14 sm:w-20 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm truncate">
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <svg
                      class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                      :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="truncate">{{ r.ruta }}</span>
                  </div>
                </td>

                <!-- Preventista -->
                <td class="px-1.5 sm:px-2.5 py-1.5 text-[#6E6E73] font-medium text-[10px] sm:text-[11px] sticky left-[84px] sm:left-[116px] z-10 w-20 sm:w-36 md:w-44 border-r border-black/[0.05] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate" :title="r.vendedor">
                  {{ r.vendedor || 'No Asignado' }}
                </td>

                <!-- Compradores -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ r.kpi7_ampliacion?.total_compradores ?? r.kpis?.ampliacion_portafolio?.metricas?.total_compradores ?? '—' }}
                </td>

                <!-- PDVs con Ampliación -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]">
                  {{ detallePorRuta[r.ruta]?.totales?.clientes_con_ampliacion ?? '—' }}
                </td>

                <!-- % Ampliación -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span v-if="detallePorRuta[r.ruta]?.totales?.porcentaje_ampliacion_ruta !== undefined">
                    {{ Number(detallePorRuta[r.ruta].totales.porcentaje_ampliacion_ruta).toFixed(1) }}%
                  </span>
                  <span v-else class="text-[#86868B]">—</span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-2 text-center border-r border-black/[0.04]">
                  <span class="inline-flex px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-300">
                    CALIBRACIÓN
                  </span>
                </td>

                <!-- Puntos (20) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span class="text-[#86868B]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Detalle de SKUs Nuevos -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="8" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Auditando ampliación de portafolio para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="space-y-3">
                    <div class="apple-glass-card rounded-[12px] overflow-hidden">
                      <div class="px-3 py-2 border-b border-black/[0.06] flex items-center justify-between bg-white/50 text-[11px]">
                        <span class="font-bold text-[#1D1D1F]">
                          Auditoría de SKUs Nuevos por Tienda: {{ r.ruta }}
                        </span>
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px] font-mono text-[10px] text-[#6E6E73]">
                          Total PDVs Auditados: <strong>{{ (detallePorRuta[r.ruta].tiendas || []).length }}</strong>
                        </span>
                      </div>

                      <div v-if="(detallePorRuta[r.ruta].tiendas || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px]">
                        No se encontraron tiendas compradoras en la fecha.
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
                              <span
                                class="inline-flex px-1.5 py-0.2 rounded font-bold"
                                :class="t.cumple ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-neutral-100 text-neutral-600'"
                              >
                                {{ t.skus_nuevos_count }} SKUs nuevos {{ t.cumple ? '(Cumple ≥ 2)' : '' }}
                              </span>
                            </div>
                          </div>

                          <div class="flex flex-wrap gap-1 mt-1">
                            <span
                              v-for="sku in (t.skus_nuevos || [])"
                              :key="sku.codigo_producto"
                              class="px-2 py-0.5 rounded text-[9px] font-medium bg-[#0071E3]/10 text-[#0071E3] border border-[#0071E3]/20"
                            >
                              + {{ sku.descripcion || sku.codigo_producto }} ({{ sku.cantidad }} uds · Bs {{ Number(sku.monto_final).toFixed(2) }})
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

async function toggleAmpliacionAcordeon(r) {
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
    const res = await axios.post('/supervisor/preventas/cumplimiento/ampliacion/detalle', {
      fecha: store.fecha,
      ruta,
      semanas_historia: 13
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar auditoría de ampliación'
  } finally {
    cargandoDetalle[ruta] = false
  }
}
</script>

