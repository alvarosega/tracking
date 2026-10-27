<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
          Pizarra Modelo Unilever
        </h3>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Matriz de cumplimiento de preventa · Criterio Binario (Todo o Nada) · 100 Pts Base
        </p>
      </div>
      <div class="flex items-center gap-2 text-[11px]">
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Orden: <strong class="text-[#0071E3] font-bold">{{ sortLabels[sortCol] || sortCol }}</strong>
          <span class="text-[#86868B] ml-1">({{ sortDir === 'desc' ? 'Mayor a menor' : 'Menor a mayor' }})</span>
        </span>
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Rutas en Pizarra:
          <strong class="text-[#1D1D1F] font-mono ml-0.5">{{ store.loadingRanking ? '…' : rutasOrdenadas.length }}</strong>
        </span>
      </div>
    </div>

    <!-- Tabla Principal -->
    <div class="overflow-x-auto custom-scroll max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[760px] sm:min-w-[980px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Pos</th>
            <th
              @click="toggleSort('ruta')"
              class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-14 sm:w-20 border-r border-black/[0.05] bg-[#F8F8FA] cursor-pointer hover:bg-black/[0.04] transition-colors select-none group/th text-[10px] sm:text-[11px]"
              title="Ordenar por Ruta"
            >
              <div class="flex items-center gap-1">
                <span :class="sortCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''">Ruta</span>
                <span v-if="sortCol === 'ruta'" class="text-[#0071E3] font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th class="px-1.5 sm:px-2.5 py-2 sticky left-[84px] sm:left-[116px] z-30 w-20 sm:w-36 md:w-44 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] text-[10px] sm:text-[11px] truncate">Preventista</th>
            
            <th
              @click="toggleSort('k1')"
              class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'k1' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por 1. Ingreso"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>1. INGRESO</span>
                <span v-if="sortCol === 'k1'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(10 PTS)</div>
            </th>

            <th
              @click="toggleSort('k2')"
              class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'k2' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por 2. Visitas"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>2. VISITAS</span>
                <span v-if="sortCol === 'k2'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(5 PTS)</div>
            </th>

            <th
              @click="toggleSort('k3')"
              class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'k3' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por 3. Obj. Diario"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>3. OBJ. DIARIO</span>
                <span v-if="sortCol === 'k3'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(10 PTS)</div>
            </th>

            <th
              @click="toggleSort('k4')"
              class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'k4' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por 4. Efectividad"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>4. EFECT.</span>
                <span v-if="sortCol === 'k4'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(30 PTS)</div>
            </th>

            <th
              @click="toggleSort('k5')"
              class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'k5' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por 5. Mín 70"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>5. MÍN 70</span>
                <span v-if="sortCol === 'k5'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(5 PTS)</div>
            </th>

            <th class="px-2 py-2 text-center border-r border-black/[0.04]">
              <div>6. REPO</div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(15 PTS)</div>
            </th>

            <th class="px-2 py-2 text-center border-r border-black/[0.04]">
              <div>7. AMPL.</div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(20 PTS)</div>
            </th>

            <th class="px-2 py-2 text-center border-r border-black/[0.04]">
              <div>8. COVERS</div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">(5 PTS)</div>
            </th>

            <th
              @click="toggleSort('score')"
              class="px-3 py-2 text-center cursor-pointer hover:bg-black/[0.04] transition-colors select-none bg-[#0071E3]/[0.04] border-l border-black/[0.05]"
              :class="sortCol === 'score' ? 'bg-blue-50 text-[#0071E3]' : ''"
              title="Ordenar por Score Sumatoria"
            >
              <div class="flex items-center justify-center gap-0.5 text-[#0071E3] font-bold">
                <span>SCORE</span>
                <span v-if="sortCol === 'score'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
              <div class="text-[9px] font-mono text-[#86868B] font-normal">SUMATORIA</div>
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.03]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-gen-${i}`">
              <td class="px-1 sm:px-2 py-3 sticky left-0 w-7 sm:w-9"><div class="h-3 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-3 sticky left-7 sm:left-9 w-14 sm:w-20"><div class="h-3 w-10 sm:w-14 skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-3 sticky left-[84px] sm:left-[116px] w-20 sm:w-36 md:w-44 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]"><div class="h-3 w-16 sm:w-28 skeleton"></div></td>
              <td v-for="c in 8" :key="c" class="px-2 py-3 text-center"><div class="h-4 w-7 mx-auto skeleton"></div></td>
              <td class="px-3 py-3 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="12" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas con los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <tr
              v-for="(r, idx) in rutasOrdenadas"
              :key="r.ruta || idx"
              class="hover:bg-black/[0.02] transition-colors"
            >
              <!-- Pos -->
              <td class="px-1 sm:px-2 py-2 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-7 sm:w-9 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                {{ idx + 1 }}
              </td>

              <!-- Ruta -->
              <td class="px-1.5 sm:px-2.5 py-2 font-bold text-[#1D1D1F] text-[10px] sm:text-[12px] sticky left-7 sm:left-9 z-10 w-14 sm:w-20 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm whitespace-nowrap truncate" :title="r.ruta">
                {{ r.ruta }}
              </td>

              <!-- Preventista -->
              <td class="px-1.5 sm:px-2.5 py-2 text-[#1D1D1F] font-normal text-[10px] sm:text-[12px] sticky left-[84px] sm:left-[116px] z-10 w-20 sm:w-36 md:w-44 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate" :title="r.vendedor">
                {{ r.vendedor || 'Sin Vendedor' }}
              </td>

              <!-- KPI 1: Ingreso (10) -->
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'horario_ingreso')">
                <span
                  v-if="r.kpis?.horario_ingreso?.cumple"
                  class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                >10</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>

              <!-- KPI 2: Visitas (5) -->
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'clientes_visitados')">
                <span
                  v-if="r.kpis?.clientes_visitados?.cumple"
                  class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                >5</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>

              <!-- KPI 3: Obj. Diario (10) -->
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'cumplimiento_objetivo')">
                <span
                  v-if="r.kpis?.cumplimiento_objetivo?.cumple"
                  class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                >10</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>

              <!-- KPI 4: Efectividad (30) -->
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'efectividad_ruta')">
                <span
                  v-if="r.kpis?.efectividad_ruta?.cumple"
                  class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                >30</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>

              <!-- KPI 5: Mínimo 70 (5) -->
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'compra_minima')">
                <span
                  v-if="r.kpis?.compra_minima?.cumple"
                  class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                >5</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>

              <!-- KPI 6, 7, 8: Reposición, Ampliación, Covers (Pausados por ahora -> —) -->
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'pedido_reposicion')">
                <span v-if="r.kpis?.pedido_reposicion?.evaluado && r.kpis?.pedido_reposicion?.cumple" class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">15</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'ampliacion_portafolio')">
                <span v-if="r.kpis?.ampliacion_portafolio?.evaluado && r.kpis?.ampliacion_portafolio?.cumple" class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">20</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>
              <td class="px-2 py-2 text-center border-r border-black/[0.04] cursor-pointer" @click="emit('seleccionar-tab', 'covers')">
                <span v-if="r.kpis?.covers?.evaluado && r.kpis?.covers?.cumple" class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">5</span>
                <span v-else class="text-[#86868B] font-mono text-[11px]">—</span>
              </td>

              <!-- Score Sumatoria -->
              <td class="px-3 py-2 text-right font-mono font-bold text-[12px] bg-[#0071E3]/[0.03]">
                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-[6px] bg-white/90 text-[#1D1D1F] border border-black/[0.08] shadow-xs">
                  {{ calcularSumatoria(r) }} pts
                </span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePreventaStore } from '@/Stores/preventaStore'

const store = usePreventaStore()
const emit = defineEmits(['seleccionar-tab'])

const sortCol = ref('score')
const sortDir = ref('desc')

const sortLabels = {
  score: 'Score Sumatoria',
  ruta: 'Ruta',
  k1: '1. Ingreso',
  k2: '2. Visitas',
  k3: '3. Obj. Diario',
  k4: '4. Efectividad',
  k5: '5. Mín 70'
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = 'desc' // Mayor a menor por defecto
  }
}

function calcularSumatoria(r) {
  const k = r.kpis || {}
  const p1 = k.horario_ingreso?.cumple ? 10 : 0
  const p2 = k.clientes_visitados?.cumple ? 5 : 0
  const p3 = k.cumplimiento_objetivo?.cumple ? 10 : 0
  const p4 = k.efectividad_ruta?.cumple ? 30 : 0
  const p5 = k.compra_minima?.cumple ? 5 : 0
  const p6 = (k.pedido_reposicion?.evaluado && k.pedido_reposicion?.cumple) ? 15 : 0
  const p7 = (k.ampliacion_portafolio?.evaluado && k.ampliacion_portafolio?.cumple) ? 20 : 0
  const p8 = (k.covers?.evaluado && k.covers?.cumple) ? 5 : 0
  return p1 + p2 + p3 + p4 + p5 + p6 + p7 + p8
}

const rutasOrdenadas = computed(() => {
  const lista = [...(store.rutasFiltradas || [])]
  const col = sortCol.value
  const dir = sortDir.value === 'asc' ? 1 : -1

  lista.sort((a, b) => {
    if (col === 'ruta') {
      return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    let valA = 0
    let valB = 0
    if (col === 'score') {
      valA = calcularSumatoria(a)
      valB = calcularSumatoria(b)
    } else if (col === 'k1') {
      valA = a.kpis?.horario_ingreso?.cumple ? 10 : 0
      valB = b.kpis?.horario_ingreso?.cumple ? 10 : 0
    } else if (col === 'k2') {
      valA = a.kpis?.clientes_visitados?.cumple ? 5 : 0
      valB = b.kpis?.clientes_visitados?.cumple ? 5 : 0
    } else if (col === 'k3') {
      valA = a.kpis?.cumplimiento_objetivo?.cumple ? 10 : 0
      valB = b.kpis?.cumplimiento_objetivo?.cumple ? 10 : 0
    } else if (col === 'k4') {
      valA = a.kpis?.efectividad_ruta?.cumple ? 30 : 0
      valB = b.kpis?.efectividad_ruta?.cumple ? 30 : 0
    } else if (col === 'k5') {
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
</script>

