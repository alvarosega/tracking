<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 5: Ticket de Compra Mínima (70 Bs)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            5 pts Base · Criterio Binario
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: El 100% de los clientes compradores deben facturar al menos 70.00 Bs en la jornada para sumar 5 puntos (Cero tolerancia a tickets menores).
        </p>
      </div>
      <div class="flex items-center gap-2 text-[11px]">
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Orden: <strong class="text-[#0071E3] font-bold">{{ sortLabels[sortCol] || sortCol }}</strong>
          <span class="text-[#86868B] ml-1">({{ sortDir === 'desc' ? 'Mayor a menor' : 'Menor a mayor' }})</span>
        </span>
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Rutas Evaluadas:
          <strong class="text-[#1D1D1F] font-mono ml-0.5">{{ store.loadingRanking ? '…' : rutasOrdenadas.length }}</strong>
        </span>
      </div>
    </div>

    <!-- Tabla Principal -->
    <div class="overflow-x-auto custom-scroll sm:max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[580px] sm:min-w-[780px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Pos</th>
            <th
              @click="toggleSort('ruta')"
              class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] cursor-pointer hover:bg-black/[0.04] transition-colors select-none text-[10px] sm:text-[11px]"
              title="Ordenar por Ruta"
            >
              <div class="flex items-center gap-1">
                <span :class="sortCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''">Ruta / Preventista</span>
                <span v-if="sortCol === 'ruta'" class="text-[#0071E3] font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            
            <th
              @click="toggleSort('compraron')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'compraron' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Total Compradores"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Compraron</span>
                <span v-if="sortCol === 'compraron'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('aptos')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'aptos' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Clientes ≥ 70 Bs"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Aptos (&ge; 70)</span>
                <span v-if="sortCol === 'aptos'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('bajoMinimo')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'bajoMinimo' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Clientes &lt; 70 Bs"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Bajo Mínimo</span>
                <span v-if="sortCol === 'bajoMinimo'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('pct')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'pct' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por % de Cumplimiento"
            >
              <div class="flex items-center justify-center gap-1">
                <span>% Cumplimiento</span>
                <span v-if="sortCol === 'pct'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('estatus')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'estatus' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Estatus"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Estatus</span>
                <span v-if="sortCol === 'estatus'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('puntos')"
              class="px-3 py-2 text-right cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'puntos' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Puntos (5)"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Puntos (5)</span>
                <span v-if="sortCol === 'puntos'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-min-${i}`">
              <td class="px-1 sm:px-2 py-2 sticky left-0 w-7 sm:w-9"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 w-24 sm:w-44 md:w-52 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                <div class="h-3.5 w-12 sm:w-16 skeleton mb-1"></div>
                <div class="h-3 w-16 sm:w-28 skeleton"></div>
              </td>
              <td v-for="c in 5" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
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
                @click="toggleCompraMinimaAcordeon(r)"
                class="cursor-pointer transition-colors"
                :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-black/[0.02]'"
              >
                <!-- Pos -->
                <td class="px-1 sm:px-2 py-1.5 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-7 sm:w-9 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                  {{ idx + 1 }}
                </td>

                <!-- Ruta / Preventista -->
                <td
                  class="px-1.5 sm:px-2.5 py-1.5 sticky left-7 sm:left-9 z-10 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate"
                  :title="`${r.ruta} - ${r.vendedor || 'No Asignado'}`"
                >
                  <div class="flex items-center gap-1 min-w-0">
                    <svg
                      class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                      :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="font-bold text-[#1D1D1F] text-[11px] sm:text-[12px] leading-tight truncate">{{ r.ruta }}</span>
                  </div>
                  <div class="text-[9.5px] sm:text-[11px] text-[#6E6E73] font-medium leading-tight truncate pl-4">
                    {{ r.vendedor || 'No Asignado' }}
                  </div>
                </td>

                <!-- Compraron -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ metricasMin(r).totalCompraron }}
                </td>

                <!-- Aptos -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]">
                  {{ metricasMin(r).aptos }}
                </td>

                <!-- Bajo Mínimo -->
                <td
                  class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]"
                  :class="metricasMin(r).bajoMinimo > 0 ? 'text-[#C9342C]' : 'text-[#6E6E73]'"
                >
                  {{ metricasMin(r).bajoMinimo }}
                </td>

                <!-- % Cumplimiento -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span :class="metricasMin(r).pct >= 100 ? 'text-[#1E6B37]' : 'text-[#C9342C]'">
                    {{ Number(metricasMin(r).pct).toFixed(1) }}%
                  </span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-1.5 text-center border-r border-black/[0.04]">
                  <span
                    v-if="r.kpis?.compra_minima?.cumple"
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]"
                  >CUMPLE</span>
                  <span
                    v-else
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]"
                  >DÉFICIT</span>
                </td>

                <!-- Puntos (5) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="r.kpis?.compra_minima?.cumple"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >5</span>
                  <span v-else class="text-[#86868B] text-[11px]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Detalle de Tickets Auditados -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="8" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Auditando tickets de compra para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="apple-glass-card rounded-[12px] overflow-hidden">
                    
                    <!-- Resumen -->
                    <div class="px-3 py-2 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/50 text-[11px]">
                      <span class="font-bold text-[#1D1D1F]">
                        Auditoría de Ticket Mínimo: {{ r.ruta }} ({{ r.vendedor }})
                      </span>
                      <div class="flex items-center gap-2 font-mono text-[10px]">
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px]">
                          Compradores: <strong>{{ detallePorRuta[r.ruta].totales?.total_compradores || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]">
                          Cumplen &ge; 70 Bs: <strong>{{ detallePorRuta[r.ruta].totales?.cumplen_minimo || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]">
                          Bajo Mínimo: <strong>{{ detallePorRuta[r.ruta].totales?.bajo_minimo || 0 }}</strong>
                        </span>
                      </div>
                    </div>

                    <!-- Subtabs -->
                    <div class="px-3 py-1.5 bg-black/[0.02] border-b border-black/[0.05] flex items-center gap-1.5 text-[11px]">
                      <button
                        type="button"
                        v-for="st in [
                          { id: 'todos', label: 'Todos los Compradores' },
                          { id: 'bajo_minimo', label: 'Bajo Mínimo (< 70 Bs)' },
                          { id: 'cumplen', label: 'Cumplen (≥ 70 Bs)' }
                        ]"
                        :key="st.id"
                        @click="subTabRuta[r.ruta] = st.id"
                        :class="(subTabRuta[r.ruta] || 'todos') === st.id ? 'glass-active-pill font-bold text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                        class="px-2.5 py-1 rounded-[7px] text-[10px] font-semibold cursor-pointer transition-colors"
                      >
                        {{ st.label }}
                      </button>
                    </div>

                    <!-- Tabla de Clientes -->
                    <div class="overflow-x-auto max-h-80 custom-scroll">
                      <table class="w-full text-left border-collapse text-[11px]">
                        <thead class="sticky top-0 z-10 border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73] bg-[#F8F8FA]">
                          <tr>
                            <th class="px-2.5 py-1.5 text-center w-8">#</th>
                            <th class="px-2.5 py-1.5 w-20">Código</th>
                            <th class="px-3 py-1.5">Cliente / PDV</th>
                            <th class="px-2.5 py-1.5">Zona</th>
                            <th class="px-3 py-1.5 text-right font-mono font-bold">Total Facturado (Bs)</th>
                            <th class="px-3 py-1.5 text-right font-mono">Brecha a 70 Bs</th>
                            <th class="px-2.5 py-1.5 text-center">Estatus</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.03]">
                          <tr
                            v-for="(c, cIdx) in clientesCompraMinimaFiltrados(r.ruta)"
                            :key="c.cliente_id || cIdx"
                            class="hover:bg-black/[0.02] transition-colors"
                          >
                            <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#86868B]">{{ cIdx + 1 }}</td>
                            <td class="px-2.5 py-1 font-mono text-[10px] text-[#6E6E73]">{{ c.cliente_id }}</td>
                            <td class="px-3 py-1 font-semibold text-[#1D1D1F] truncate max-w-[260px]" :title="c.cliente">
                              {{ c.cliente }}
                            </td>
                            <td class="px-2.5 py-1 text-[#6E6E73]">{{ c.zona || '—' }}</td>
                            <td class="px-3 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                              Bs {{ Number(c.total_acumulado_bs || c.total_bs || 0).toFixed(2) }}
                            </td>
                            <td class="px-3 py-1 text-right font-mono font-bold">
                              <span v-if="calcularBrecha(c) > 0" class="text-[#C9342C]">
                                -Bs {{ calcularBrecha(c).toFixed(2) }}
                              </span>
                              <span v-else class="text-[#1E6B37]">0.00</span>
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <span
                                class="inline-flex px-1.5 py-0.2 rounded text-[9px] font-bold"
                                :class="Number(c.total_acumulado_bs || c.total_bs || 0) >= 70 ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                              >
                                {{ Number(c.total_acumulado_bs || c.total_bs || 0) >= 70 ? 'CUMPLE' : 'BAJO MÍNIMO' }}
                              </span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
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

const sortCol = ref('puntos')
const sortDir = ref('desc')

const sortLabels = {
  puntos: 'Puntos (5)',
  compraron: 'Compraron',
  aptos: 'Aptos (≥ 70)',
  bajoMinimo: 'Bajo Mínimo',
  pct: '% Cumplimiento',
  estatus: 'Estatus',
  ruta: 'Ruta'
}

function metricasMin(r) {
  const m = r.kpis?.compra_minima?.metricas || {}
  return {
    totalCompraron: Number(m.total_compraron || 0),
    bajoMinimo: Number(m.bajo_minimo || 0),
    aptos: Number(m.aptos || 0),
    pct: Number(m.pct || 0)
  }
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = col === 'bajoMinimo' ? 'asc' : 'desc'
  }
}

const rutasOrdenadas = computed(() => {
  const lista = [...(store.rutasFiltradas || [])]
  const col = sortCol.value
  const dir = sortDir.value === 'asc' ? 1 : -1

  lista.sort((a, b) => {
    if (col === 'ruta') {
      return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    const mA = metricasMin(a)
    const mB = metricasMin(b)
    let valA = 0
    let valB = 0
    if (col === 'pct') {
      valA = mA.pct
      valB = mB.pct
    } else if (col === 'compraron') {
      valA = mA.totalCompraron
      valB = mB.totalCompraron
    } else if (col === 'aptos') {
      valA = mA.aptos
      valB = mB.aptos
    } else if (col === 'bajoMinimo') {
      valA = mA.bajoMinimo
      valB = mB.bajoMinimo
    } else if (col === 'estatus') {
      valA = a.kpis?.compra_minima?.cumple ? 1 : 0
      valB = b.kpis?.compra_minima?.cumple ? 1 : 0
    } else if (col === 'puntos') {
      valA = a.kpis?.compra_minima?.cumple ? 5 : 0
      valB = b.kpis?.compra_minima?.cumple ? 5 : 0
    }
    if (valA !== valB) {
      return dir * (valA - valB)
    }
    return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
  })

  return lista
})

const acordeonAbierto = reactive({})
const subTabRuta = reactive({})
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

function calcularBrecha(c) {
  if (c.brecha_70 !== undefined) return Number(c.brecha_70)
  const monto = Number(c.total_acumulado_bs || c.total_bs || 0)
  return Math.max(0, 70.0 - monto)
}

async function toggleCompraMinimaAcordeon(r) {
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
    const res = await axios.post('/supervisor/preventas/cumplimiento/compra-minima/detalle', {
      fecha: store.fecha,
      ruta
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar auditoría de compra mínima'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

function clientesCompraMinimaFiltrados(ruta) {
  const d = detallePorRuta[ruta]
  if (!d) return []
  const tab = subTabRuta[ruta] || 'todos'
  const bajoMinimo = d.clientes_bajo_minimo || []
  const cumplen = d.clientes_cumplen || []

  if (tab === 'bajo_minimo') return bajoMinimo
  if (tab === 'cumplen') return cumplen

  return [...bajoMinimo, ...cumplen]
}
</script>

