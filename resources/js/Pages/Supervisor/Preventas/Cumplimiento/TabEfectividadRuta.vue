<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 4: Efectividad Comercial de Visita (55%)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            30 pts Base · Criterio Binario
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: La efectividad (Compradores sobre cartera activa planificada) debe ser &ge; 55.0% para sumar 30 puntos (Todo o Nada).
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
    <div class="overflow-x-auto custom-scroll max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[940px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-2.5 py-2 text-center sticky left-0 z-30 w-12 border-r border-black/[0.05] bg-[#F8F8FA]">Pos</th>
            <th
              @click="toggleSort('ruta')"
              class="px-3 py-2 sticky left-12 z-30 w-28 border-r border-black/[0.05] bg-[#F8F8FA] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              title="Ordenar por Ruta"
            >
              <div class="flex items-center gap-1">
                <span :class="sortCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''">Ruta</span>
                <span v-if="sortCol === 'ruta'" class="text-[#0071E3] font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th class="px-3 py-2 sticky left-[112px] z-30 min-w-[180px] border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.04)] bg-[#F8F8FA]">Preventista</th>
            
            <th
              @click="toggleSort('plan')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'plan' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Cartera Plan"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Cartera Plan</span>
                <span v-if="sortCol === 'plan'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('compradores')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'compradores' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Compradores"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Compradores</span>
                <span v-if="sortCol === 'compradores'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('sobre70')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'sobre70' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Compradores &ge; 70 Bs"
            >
              <div class="flex items-center justify-center gap-1">
                <span>&ge; 70 Bs</span>
                <span v-if="sortCol === 'sobre70'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('bajo70')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'bajo70' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Compradores &lt; 70 Bs"
            >
              <div class="flex items-center justify-center gap-1">
                <span>&lt; 70 Bs</span>
                <span v-if="sortCol === 'bajo70'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('efectividad')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'efectividad' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por % Efectividad"
            >
              <div class="flex items-center justify-center gap-1">
                <span>% Efectividad</span>
                <span v-if="sortCol === 'efectividad'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
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
              title="Ordenar por Puntos (30)"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Puntos (30)</span>
                <span v-if="sortCol === 'puntos'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-efec-${i}`">
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
                @click="toggleEfectividadAcordeon(r)"
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

                <!-- Cartera Plan -->
                <td class="px-3 py-1.5 text-center font-mono font-medium text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ metricasEfec(r).carteraPlan }}
                </td>

                <!-- Compradores -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ metricasEfec(r).clientesEfectivos }}
                </td>

                <!-- >= 70 Bs -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]">
                  {{ metricasEfec(r).clientesSobreMinimo }}
                </td>

                <!-- < 70 Bs -->
                <td
                  class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]"
                  :class="metricasEfec(r).clientesBajoMinimo > 0 ? 'text-[#C9342C]' : 'text-[#6E6E73]'"
                >
                  {{ metricasEfec(r).clientesBajoMinimo }}
                </td>

                <!-- % Efectividad -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span :class="metricasEfec(r).efectividadPct >= 55.0 ? 'text-[#1E6B37]' : 'text-[#C9342C]'">
                    {{ Number(metricasEfec(r).efectividadPct).toFixed(1) }}%
                  </span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-1.5 text-center border-r border-black/[0.04]">
                  <span
                    v-if="r.kpis?.efectividad_ruta?.cumple"
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]"
                  >CUMPLE</span>
                  <span
                    v-else
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]"
                  >DÉFICIT</span>
                </td>

                <!-- Puntos (30) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="r.kpis?.efectividad_ruta?.cumple"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >30</span>
                  <span v-else class="text-[#86868B] text-[11px]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Detalle de Compradores y Pedidos -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="10" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Auditando efectividad comercial para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="apple-glass-card rounded-[12px] overflow-hidden">
                    
                    <!-- Resumen de Efectividad -->
                    <div class="px-3 py-2 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/50 text-[11px]">
                      <span class="font-bold text-[#1D1D1F]">
                        Detalle de Efectividad Comercial: {{ r.ruta }} ({{ r.vendedor }})
                      </span>
                      <div class="flex items-center gap-2 font-mono text-[10px]">
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px]">
                          Plan: <strong>{{ detallePorRuta[r.ruta].totales?.cartera_plan || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]">
                          Efectivos &ge; 70 Bs: <strong>{{ detallePorRuta[r.ruta].totales?.clientes_sobre_minimo || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]">
                          Bajo Mínimo &lt; 70 Bs: <strong>{{ detallePorRuta[r.ruta].totales?.clientes_bajo_minimo || 0 }}</strong>
                        </span>
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px]">
                          % Efectividad: <strong>{{ Number(detallePorRuta[r.ruta].totales?.efectividad_pct || 0).toFixed(1) }}%</strong>
                        </span>
                      </div>
                    </div>

                    <!-- Subtabs -->
                    <div class="px-3 py-1.5 bg-black/[0.02] border-b border-black/[0.05] flex items-center gap-1.5 text-[11px]">
                      <button
                        type="button"
                        v-for="st in [
                          { id: 'todos', label: 'Todos los Compradores' },
                          { id: 'cumplen', label: 'Ticket ≥ 70 Bs' },
                          { id: 'bajo_minimo', label: 'Ticket < 70 Bs' }
                        ]"
                        :key="st.id"
                        @click="subTabRuta[r.ruta] = st.id"
                        :class="(subTabRuta[r.ruta] || 'todos') === st.id ? 'glass-active-pill font-bold text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                        class="px-2.5 py-1 rounded-[7px] text-[10px] font-semibold cursor-pointer transition-colors"
                      >
                        {{ st.label }}
                      </button>
                    </div>

                    <!-- Tabla de Clientes con Pedido -->
                    <div class="overflow-x-auto max-h-80 custom-scroll">
                      <table class="w-full text-left border-collapse text-[11px]">
                        <thead class="sticky top-0 z-10 border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73] bg-[#F8F8FA]">
                          <tr>
                            <th class="px-2.5 py-1.5 text-center w-8">#</th>
                            <th class="px-2.5 py-1.5 w-20">Código</th>
                            <th class="px-3 py-1.5">Cliente / PDV</th>
                            <th class="px-2.5 py-1.5">Zona</th>
                            <th class="px-2.5 py-1.5 text-center">Ítems SKU</th>
                            <th class="px-3 py-1.5 text-right font-mono font-bold">Monto Venta (Bs)</th>
                            <th class="px-2.5 py-1.5 text-center">Estatus Ticket</th>
                            <th class="px-2.5 py-1.5 text-center w-12">Detalle</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.03]">
                          <template v-for="(c, cIdx) in clientesEfectividadFiltrados(r.ruta)" :key="c.cliente_id || cIdx">
                            <tr
                              @click="toggleSubProd(`${r.ruta}_${c.cliente_id}`)"
                              class="hover:bg-black/[0.02] cursor-pointer transition-colors"
                            >
                              <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#86868B]">{{ cIdx + 1 }}</td>
                              <td class="px-2.5 py-1 font-mono text-[10px] text-[#6E6E73]">{{ c.cliente_id }}</td>
                              <td class="px-3 py-1 font-semibold text-[#1D1D1F] truncate max-w-[260px]" :title="c.cliente">
                                {{ c.cliente }}
                              </td>
                              <td class="px-2.5 py-1 text-[#6E6E73]">{{ c.zona || '—' }}</td>
                              <td class="px-2.5 py-1 text-center font-mono text-[#1D1D1F]">{{ (c.items || []).length }}</td>
                              <td class="px-3 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                Bs {{ Number(c.total_bs || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                              </td>
                              <td class="px-2.5 py-1 text-center">
                                <span
                                  class="inline-flex px-1.5 py-0.2 rounded text-[9px] font-bold"
                                  :class="Number(c.total_bs || 0) >= 70 ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                                >
                                  {{ Number(c.total_bs || 0) >= 70 ? '≥ 70 Bs' : '< 70 Bs' }}
                                </span>
                              </td>
                              <td class="px-2.5 py-1 text-center">
                                <button
                                  type="button"
                                  class="text-[#0071E3] hover:underline font-bold text-[10px] cursor-pointer"
                                >
                                  {{ subProdAbierto[`${r.ruta}_${c.cliente_id}`] ? 'Ocultar' : 'Ver SKU' }}
                                </button>
                              </td>
                            </tr>

                            <!-- Subtabla con ítems SKU -->
                            <tr v-if="subProdAbierto[`${r.ruta}_${c.cliente_id}`]">
                              <td colspan="8" class="p-2.5 bg-blue-50/20 border-b border-black/[0.05]">
                                <div class="apple-glass-card rounded-[8px] overflow-hidden">
                                  <table class="w-full text-left border-collapse text-[10px]">
                                    <thead class="border-b border-black/[0.05] bg-black/[0.02] text-[#86868B] font-bold uppercase">
                                      <tr>
                                        <th class="px-2.5 py-1 w-24">Código SKU</th>
                                        <th class="px-3 py-1">Producto / Presentación</th>
                                        <th class="px-2.5 py-1 text-center">Categoría</th>
                                        <th class="px-2.5 py-1 text-right font-mono">Cantidad</th>
                                        <th class="px-2.5 py-1 text-right font-mono">Precio Unit. (Bs)</th>
                                        <th class="px-3 py-1 text-right font-mono font-bold">Subtotal (Bs)</th>
                                      </tr>
                                    </thead>
                                    <tbody class="divide-y divide-black/[0.03]">
                                      <tr v-for="it in (c.items || [])" :key="it.codigo_producto" class="hover:bg-black/[0.01]">
                                        <td class="px-2.5 py-1 font-mono text-[#6E6E73]">{{ it.codigo_producto }}</td>
                                        <td class="px-3 py-1 font-medium text-[#1D1D1F]">{{ it.producto || it.descripcion }}</td>
                                        <td class="px-2.5 py-1 text-center text-[#86868B]">{{ it.categoria || '—' }}</td>
                                        <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">{{ it.cantidad }}</td>
                                        <td class="px-2.5 py-1 text-right font-mono text-[#6E6E73]">
                                          {{ Number(it.precio_unitario || (it.monto_final / (it.cantidad || 1))).toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                          {{ Number(it.monto_final || 0).toFixed(2) }}
                                        </td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>
                              </td>
                            </tr>
                          </template>
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
  puntos: 'Puntos (30)',
  plan: 'Cartera Plan',
  compradores: 'Compradores',
  sobre70: '≥ 70 Bs',
  bajo70: '< 70 Bs',
  efectividad: '% Efectividad',
  estatus: 'Estatus',
  ruta: 'Ruta'
}

function metricasEfec(r) {
  const m = r.kpis?.efectividad_ruta?.metricas || {}
  return {
    carteraPlan: Number(m.cartera_plan || 0),
    clientesEfectivos: Number(m.clientes_efectivos || 0),
    clientesSobreMinimo: Number(m.clientes_sobre_minimo || 0),
    clientesBajoMinimo: Number(m.clientes_bajo_minimo || 0),
    efectividadPct: Number(m.efectividad_pct || 0)
  }
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = col === 'plan' ? 'asc' : 'desc'
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
    const mA = metricasEfec(a)
    const mB = metricasEfec(b)
    let valA = 0
    let valB = 0
    if (col === 'plan') {
      valA = mA.carteraPlan
      valB = mB.carteraPlan
    } else if (col === 'compradores') {
      valA = mA.clientesEfectivos
      valB = mB.clientesEfectivos
    } else if (col === 'sobre70') {
      valA = mA.clientesSobreMinimo
      valB = mB.clientesSobreMinimo
    } else if (col === 'bajo70') {
      valA = mA.clientesBajoMinimo
      valB = mB.clientesBajoMinimo
    } else if (col === 'efectividad') {
      valA = mA.efectividadPct
      valB = mB.efectividadPct
    } else if (col === 'estatus') {
      valA = a.kpis?.efectividad_ruta?.cumple ? 1 : 0
      valB = b.kpis?.efectividad_ruta?.cumple ? 1 : 0
    } else if (col === 'puntos') {
      valA = a.kpis?.efectividad_ruta?.cumple ? 30 : 0
      valB = b.kpis?.efectividad_ruta?.cumple ? 30 : 0
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
const subProdAbierto = reactive({})
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

async function toggleEfectividadAcordeon(r) {
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
    const res = await axios.post('/supervisor/preventas/cumplimiento/efectividad/detalle', {
      fecha: store.fecha,
      ruta
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar efectividad de ruta'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

function clientesEfectividadFiltrados(ruta) {
  const d = detallePorRuta[ruta]
  if (!d) return []
  const tab = subTabRuta[ruta] || 'todos'
  const cumplen = d.clientes_cumplen || []
  const bajoMinimo = d.clientes_bajo_minimo || []

  if (tab === 'cumplen') return cumplen
  if (tab === 'bajo_minimo') return bajoMinimo

  return [...cumplen, ...bajoMinimo]
}

function toggleSubProd(key) {
  subProdAbierto[key] = !subProdAbierto[key]
}
</script>

