<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 8: Covers / Cobertura de Líneas Clave
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#86868B] bg-white/80 border border-black/[0.08]">
            5 pts Base · Módulo en Calibración
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Penetración y presencia de líneas foco del fabricante en los pedidos levantados de la jornada.
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
    <div class="overflow-x-auto custom-scroll sm:max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[560px] sm:min-w-[760px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Pos</th>
            <th class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] text-[10px] sm:text-[11px] truncate">Ruta / Preventista</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Compradores</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Líneas Evaluadas</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Cobertura Media</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Estatus</th>
            <th class="px-3 py-2 text-right">Puntos (5)</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-cov-${i}`">
              <td class="px-1 sm:px-2 py-2 sticky left-0 w-7 sm:w-9"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 w-24 sm:w-44 md:w-52 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                <div class="h-3.5 w-12 sm:w-16 skeleton mb-1"></div>
                <div class="h-3 w-16 sm:w-28 skeleton"></div>
              </td>
              <td v-for="c in 4" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="7" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas para los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <tr
              v-for="(r, idx) in rutasOrdenadas"
              :key="r.ruta || idx"
              class="hover:bg-black/[0.02] transition-colors"
            >
              <td class="px-1 sm:px-2 py-1.5 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-7 sm:w-9 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                {{ idx + 1 }}
              </td>
              <td
                class="px-1.5 sm:px-2.5 py-1.5 sticky left-7 sm:left-9 z-10 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate"
                :title="`${r.ruta} - ${r.vendedor || 'No Asignado'}`"
              >
                <div class="font-bold text-[#1D1D1F] text-[11px] sm:text-[12px] leading-tight truncate">
                  {{ r.ruta }}
                </div>
                <div class="text-[9.5px] sm:text-[11px] text-[#6E6E73] font-medium leading-tight truncate mt-0.5">
                  {{ r.vendedor || 'No Asignado' }}
                </div>
              </td>
              <td class="px-3 py-2 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                {{ r.kpi8_covers?.total_compradores ?? r.kpis?.covers?.metricas?.total_compradores ?? '—' }}
              </td>
              <td class="px-3 py-2 text-center font-mono text-[#6E6E73] border-r border-black/[0.04]">
                —
              </td>
              <td class="px-3 py-2 text-center font-mono font-bold text-[#6E6E73] border-r border-black/[0.04]">
                —
              </td>
              <td class="px-3 py-2 text-center border-r border-black/[0.04]">
                <span class="inline-flex px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-300">
                  CALIBRACIÓN
                </span>
              </td>
              <td class="px-3 py-2 text-right font-mono font-bold text-[12px]">
                <span class="text-[#86868B]">—</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePreventaStore } from '@/Stores/preventaStore'

const store = usePreventaStore()

const rutasOrdenadas = computed(() => {
  return [...(store.rutasFiltradas || [])]
})
</script>

