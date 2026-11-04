<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto y Parámetros -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 8: Covers (Productos con Descuento)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            5 pts Base · Criterio &ge; 50%
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: De los clientes compradores de la ruta, al menos el 50.0% debe tener algún producto con descuento en fact_preventas para sumar los 5 puntos.
        </p>
      </div>

      <div class="flex items-center gap-2.5 text-[11px]">
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Rutas:
          <strong class="text-[#1D1D1F] font-mono ml-0.5">{{ store.loadingRanking ? '…' : rutasOrdenadas.length }}</strong>
        </span>
      </div>
    </div>

    <!-- Tabla Principal de Rutas -->
    <div class="overflow-x-auto custom-scroll sm:max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[620px] sm:min-w-[840px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <!-- Pos -->
            <th
              @click="toggleSort('pos')"
              class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px] cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Ordenar por Posición"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>Pos</span>
                <span v-if="sortCol === 'pos'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- Ruta / Preventista -->
            <th
              @click="toggleSort('ruta')"
              class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] text-[10px] sm:text-[11px] truncate cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Ordenar por Ruta"
            >
              <div class="flex items-center gap-1">
                <span>Ruta / Preventista</span>
                <span v-if="sortCol === 'ruta'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- Compradores -->
            <th
              @click="toggleSort('compradores')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Total de clientes con preventa hoy"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>Compradores</span>
                <span v-if="sortCol === 'compradores'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- Con Descuento -->
            <th
              @click="toggleSort('con_descuento')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Clientes compradores con al menos un producto con descuento"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>Con Descuento</span>
                <span v-if="sortCol === 'con_descuento'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- Sin Descuento -->
            <th
              @click="toggleSort('sin_descuento')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Clientes compradores sin ningún producto con descuento"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>Sin Descuento</span>
                <span v-if="sortCol === 'sin_descuento'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- % Covers -->
            <th
              @click="toggleSort('pct_covers')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Porcentaje de clientes compradores con descuento (Umbral: ≥ 50.0%)"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>% Covers</span>
                <span v-if="sortCol === 'pct_covers'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- Estatus -->
            <th
              @click="toggleSort('cumple')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Cumple si % Covers ≥ 50.0%"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>Estatus</span>
                <span v-if="sortCol === 'cumple'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>

            <!-- Puntos (5) -->
            <th
              @click="toggleSort('puntos')"
              class="px-3 py-2 text-right cursor-pointer hover:bg-black/[0.03] transition-colors"
              title="Puntos asignados al KPI (5 pts)"
            >
              <div class="flex items-center justify-end gap-0.5">
                <span>Puntos (5)</span>
                <span v-if="sortCol === 'puntos'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </div>
            </th>
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
                @click="toggleCoversAcordeon(r)"
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
                    <span class="font-bold text-[#1D1D1F] text-[11px] sm:text-[12px] leading-tight truncate">{{ r.ruta }}</span>
                  </div>
                  <div class="text-[9.5px] sm:text-[11px] text-[#6E6E73] font-medium leading-tight truncate pl-4 sm:pl-5">
                    {{ r.vendedor || 'No Asignado' }}
                  </div>
                </td>

                <!-- Compradores -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ getCompradores(r) }}
                </td>

                <!-- Con Descuento -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]">
                  {{ getClientesConDescuento(r) }}
                </td>

                <!-- Sin Descuento -->
                <td class="px-3 py-1.5 text-center font-mono text-[#86868B] border-r border-black/[0.04]">
                  {{ getClientesSinDescuento(r) }}
                </td>

                <!-- % Covers -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span
                    v-if="getPctCovers(r) !== null"
                    :class="getPctCovers(r) >= 50.0 ? 'text-[#1E6B37]' : 'text-[#C9342C]'"
                  >
                    {{ Number(getPctCovers(r)).toFixed(1) }}%
                  </span>
                  <span v-else class="text-[#86868B]">—</span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-2 text-center border-r border-black/[0.04]">
                  <span
                    v-if="getPctCovers(r) !== null"
                    class="inline-flex items-center justify-center w-6 h-6 rounded-[6px] text-[12px] font-bold leading-none select-none"
                    :class="getCumpleKpi(r)
                      ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                      : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                    :title="getCumpleKpi(r) ? 'Cumple Covers (≥ 50%)' : 'Déficit (< 50%)'"
                  >
                    {{ getCumpleKpi(r) ? '✓' : '✕' }}
                  </span>
                  <span v-else class="text-[#86868B] text-[11px] font-mono">—</span>
                </td>

                <!-- Puntos (5) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="getPuntosObtenidos(r) > 0"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >
                    5
                  </span>
                  <span v-else class="text-[#86868B] text-[11px]">—</span>
                </td>
              </tr>

              <!-- ═══════════════════════════════════════════════════════════ -->
              <!-- Fila Acordeón: Detalle de Tiendas Compradoras               -->
              <!-- ═══════════════════════════════════════════════════════════ -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="8" class="p-3 bg-white/70 border-b border-black/[0.06]">
                  <!-- Estado de carga -->
                  <div v-if="cargandoDetalle[r.ruta]" class="p-6 text-center text-[#6E6E73] text-[11px] font-medium space-y-2">
                    <div class="h-4 w-48 mx-auto skeleton"></div>
                    <div>Auditando productos con descuento para {{ r.ruta }}...</div>
                  </div>

                  <!-- Estado de error -->
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold bg-[#FDF0EF] rounded-[8px] border border-[#FADCD5]">
                    {{ errorDetalle[r.ruta] }}
                  </div>

                  <!-- Detalle auditado en tiendas -->
                  <div v-else-if="detallePorRuta[r.ruta]" class="space-y-1.5">
                    <!-- Botones de expansión rápida -->
                    <div v-if="(detallePorRuta[r.ruta].tiendas || []).length > 0" class="flex justify-between items-center gap-1.5 pb-0.5 px-0.5">
                      <div class="text-[11px] text-[#6E6E73] font-medium">
                        Total PDVs con compra hoy: <strong class="text-[#1D1D1F] font-mono">{{ (detallePorRuta[r.ruta].tiendas || []).length }}</strong>
                        · Clientes con producto con descuento:
                        <strong class="text-[#1E6B37] font-mono">{{ detallePorRuta[r.ruta].totales?.clientes_con_descuento || 0 }}</strong>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <button
                          type="button"
                          @click="expandirTodasTiendas(r.ruta, detallePorRuta[r.ruta].tiendas, true)"
                          class="px-2 py-0.5 rounded-[5px] bg-white hover:bg-slate-100 text-[#0071E3] font-sans font-semibold text-[10px] border border-black/[0.08] cursor-pointer"
                        >
                          Expandir Todo
                        </button>
                        <button
                          type="button"
                          @click="expandirTodasTiendas(r.ruta, detallePorRuta[r.ruta].tiendas, false)"
                          class="px-2 py-0.5 rounded-[5px] bg-white hover:bg-slate-100 text-[#86868B] font-sans font-semibold text-[10px] border border-black/[0.08] cursor-pointer"
                        >
                          Colapsar
                        </button>
                      </div>
                    </div>

                    <!-- Sin tiendas -->
                    <div v-if="(detallePorRuta[r.ruta].tiendas || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px] apple-glass-card rounded-[12px]">
                      No se encontraron tiendas compradoras en la fecha.
                    </div>

                    <!-- Lista de Tiendas Compradoras -->
                    <div v-else class="space-y-1.5">
                      <div
                        v-for="(t, tIdx) in detallePorRuta[r.ruta].tiendas"
                        :key="t.cliente_id"
                        class="apple-glass-card rounded-[9px] py-1.5 px-2.5 border border-black/[0.06] bg-white space-y-1.5 transition-all shadow-xs hover:border-black/[0.12]"
                      >
                        <!-- Cabecera de la Tienda -->
                        <div class="flex flex-wrap items-center justify-between gap-1.5 pb-1 border-b border-black/[0.05] text-[11px]">
                          <div class="flex items-center gap-1.5 min-w-0">
                            <button
                              type="button"
                              @click="toggleCliente(r.ruta, t.cliente_id, tIdx)"
                              class="font-bold text-[#1D1D1F] hover:text-[#0071E3] text-[12px] flex items-center gap-1.5 cursor-pointer truncate"
                            >
                              <svg
                                class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                                :class="isClienteAbierto(r.ruta, t.cliente_id, tIdx) ? 'rotate-90 text-[#0071E3]' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                              </svg>
                              <span class="truncate">{{ t.cliente }}</span>
                            </button>
                            <span class="text-[9.5px] text-[#86868B] font-mono px-1.5 py-0.2 rounded bg-[#F5F5F7] border border-[#E5E5EA] shrink-0">
                              #{{ t.codigo_cliente || t.cliente_id }}
                            </span>
                          </div>

                          <div class="flex items-center gap-2.5 text-[10.5px] font-mono flex-wrap">
                            <span class="text-[#6E6E73]">
                              Total Items: <strong class="text-[#1D1D1F] font-bold">{{ t.total_items_hoy }}</strong>
                            </span>
                            <span class="text-[#6E6E73]">
                              Con Descuento:
                              <strong :class="t.items_con_descuento > 0 ? 'text-[#1E6B37]' : 'text-[#86868B]'" class="font-bold">
                                {{ t.items_con_descuento }}
                              </strong>
                            </span>
                            <span
                              class="inline-flex px-1.5 py-0.2 rounded text-[9.5px] font-bold"
                              :class="t.cumple ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]' : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                            >
                              {{ t.cumple ? '✓ Con Descuento' : '✕ Sin Descuento' }}
                            </span>
                            <span v-if="t.descuento_total_bs > 0" class="text-[#1E6B37]">
                              Descuento Total: <strong class="font-bold">Bs {{ formatBs(t.descuento_total_bs) }}</strong>
                            </span>
                            <span class="text-[#6E6E73]">
                              Total Pedido: <strong class="text-[#0071E3] font-bold">Bs {{ formatBs(t.total_bs_hoy) }}</strong>
                            </span>
                          </div>
                        </div>

                        <!-- Contenido Desplegable: Tabla de Productos Comprados -->
                        <div
                          v-if="isClienteAbierto(r.ruta, t.cliente_id, tIdx)"
                          class="pt-1"
                        >
                          <div class="overflow-x-auto max-h-64 custom-scroll rounded-[8px] border border-black/[0.06] bg-white">
                            <table class="w-full text-left border-collapse text-[11px]">
                              <thead class="sticky top-0 bg-[#F8F8FA] border-b border-black/[0.06] text-[9.5px] uppercase font-bold text-[#6E6E73]">
                                <tr>
                                  <th class="px-2.5 py-1.5 w-16">Código</th>
                                  <th class="px-2.5 py-1.5">Producto</th>
                                  <th class="px-2.5 py-1.5 text-center w-12">Cant</th>
                                  <th class="px-2.5 py-1.5 text-right w-20">P. Lista</th>
                                  <th class="px-2.5 py-1.5 text-right w-24">Descuento</th>
                                  <th class="px-2.5 py-1.5 text-right w-20">Total Bs</th>
                                </tr>
                              </thead>
                              <tbody class="divide-y divide-black/[0.03]">
                                <tr
                                  v-for="it in t.items"
                                  :key="it.producto_id || it.codigo_producto"
                                  :class="it.con_descuento ? 'bg-emerald-50/40 font-medium' : 'hover:bg-black/[0.02]'"
                                >
                                  <td class="px-2.5 py-1 font-mono text-[9.5px] text-[#86868B]">
                                    {{ it.codigo_producto || it.producto_id }}
                                  </td>
                                  <td class="px-2.5 py-1 text-[#1D1D1F]">
                                    <div class="flex items-center gap-1.5">
                                      <span
                                        v-if="it.con_descuento"
                                        class="inline-block px-1 py-0.2 rounded text-[8.5px] font-bold bg-[#1E6B37] text-white shrink-0"
                                      >
                                        DESC
                                      </span>
                                      <span class="truncate max-w-[280px]" :title="it.producto">
                                        {{ it.producto }}
                                      </span>
                                    </div>
                                  </td>
                                  <td class="px-2.5 py-1 text-center font-mono">
                                    {{ it.cantidad }}
                                  </td>
                                  <td class="px-2.5 py-1 text-right font-mono text-[#6E6E73]">
                                    Bs {{ formatBs(it.precio_lista) }}
                                  </td>
                                  <td class="px-2.5 py-1 text-right font-mono font-bold" :class="it.con_descuento ? 'text-[#1E6B37]' : 'text-[#86868B]'">
                                    {{ it.con_descuento ? `Bs ${formatBs(it.descuento)}` : '—' }}
                                  </td>
                                  <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                    Bs {{ formatBs(it.monto_final) }}
                                  </td>
                                </tr>
                              </tbody>
                            </table>
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

const sortCol = ref('pos')
const sortDir = ref('asc')

const acordeonAbierto = reactive({})
const cargandoDetalle = reactive({})
const errorDetalle = reactive({})
const detallePorRuta = reactive({})
const clientesAbiertos = reactive({})

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
    Object.keys(clientesAbiertos).forEach(k => delete clientesAbiertos[k])
  }
)

function formatBs(val) {
  const n = Number(val || 0)
  return n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function getCompradores(r) {
  return r.kpi8_covers?.total_compradores
    ?? detallePorRuta[r.ruta]?.totales?.compradores_hoy
    ?? r.kpis?.covers?.metricas?.total_compradores
    ?? '—'
}

function getClientesConDescuento(r) {
  return r.kpi8_covers?.cumplen
    ?? detallePorRuta[r.ruta]?.totales?.clientes_con_descuento
    ?? r.kpis?.covers?.metricas?.cumplen
    ?? '—'
}

function getClientesSinDescuento(r) {
  if (detallePorRuta[r.ruta]?.totales?.clientes_sin_descuento !== undefined) {
    return detallePorRuta[r.ruta].totales.clientes_sin_descuento
  }
  const comp = Number(getCompradores(r))
  const conDesc = Number(getClientesConDescuento(r))
  if (!isNaN(comp) && !isNaN(conDesc)) {
    return Math.max(0, comp - conDesc)
  }
  return '—'
}

function getPctCovers(r) {
  if (r.kpi8_covers?.tasa_pct !== undefined && r.kpi8_covers?.tasa_pct !== null) {
    return Number(r.kpi8_covers.tasa_pct)
  }
  if (detallePorRuta[r.ruta]?.totales?.porcentaje_covers_ruta !== undefined) {
    return Number(detallePorRuta[r.ruta].totales.porcentaje_covers_ruta)
  }
  if (r.kpis?.covers?.metricas?.tasa_pct !== undefined) {
    return Number(r.kpis.covers.metricas.tasa_pct)
  }
  return null
}

function getCumpleKpi(r) {
  if (r.kpi8_covers?.cumple !== undefined) {
    return Boolean(r.kpi8_covers.cumple)
  }
  if (detallePorRuta[r.ruta]?.totales?.cumple_umbral !== undefined) {
    return Boolean(detallePorRuta[r.ruta].totales.cumple_umbral)
  }
  const pct = getPctCovers(r)
  return pct !== null && pct >= 50.0
}

function getPuntosObtenidos(r) {
  if (r.kpi8_covers?.puntos !== undefined) {
    return Number(r.kpi8_covers.puntos)
  }
  if (detallePorRuta[r.ruta]?.totales?.puntos_obtenidos !== undefined) {
    return Number(detallePorRuta[r.ruta].totales.puntos_obtenidos)
  }
  return getCumpleKpi(r) ? 5.0 : 0.0
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = ['ruta', 'pos'].includes(col) ? 'asc' : 'desc'
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
    let valA = 0
    let valB = 0
    if (col === 'pos') {
      valA = a.rank ?? 0
      valB = b.rank ?? 0
    } else if (col === 'compradores') {
      valA = Number(getCompradores(a)) || 0
      valB = Number(getCompradores(b)) || 0
    } else if (col === 'con_descuento') {
      valA = Number(getClientesConDescuento(a)) || 0
      valB = Number(getClientesConDescuento(b)) || 0
    } else if (col === 'sin_descuento') {
      valA = Number(getClientesSinDescuento(a)) || 0
      valB = Number(getClientesSinDescuento(b)) || 0
    } else if (col === 'pct_covers') {
      valA = getPctCovers(a) ?? -1
      valB = getPctCovers(b) ?? -1
    } else if (col === 'cumple') {
      valA = getCumpleKpi(a) ? 1 : 0
      valB = getCumpleKpi(b) ? 1 : 0
    } else if (col === 'puntos') {
      valA = getPuntosObtenidos(a)
      valB = getPuntosObtenidos(b)
    }

    if (valA !== valB) {
      return dir * (valA - valB)
    }
    return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
  })

  return lista
})

// ── Control de acordeón de tiendas ──────────────────────────────────────
function isClienteAbierto(ruta, clienteId, idx) {
  const key = `${ruta}-${clienteId}-${idx}`
  return Boolean(clientesAbiertos[key])
}

function toggleCliente(ruta, clienteId, idx) {
  const key = `${ruta}-${clienteId}-${idx}`
  clientesAbiertos[key] = !isClienteAbierto(ruta, clienteId, idx)
}

function expandirTodasTiendas(ruta, tiendas, abrir = true) {
  (tiendas || []).forEach((t, idx) => {
    const key = `${ruta}-${t.cliente_id}-${idx}`
    clientesAbiertos[key] = abrir
  })
}

// ── Carga y Acordeón de Ruta ─────────────────────────────────────────────
async function cargarDetalleRuta(ruta, force = false) {
  if (!force && detallePorRuta[ruta]) return

  cargandoDetalle[ruta] = true
  errorDetalle[ruta] = null
  try {
    const res = await axios.post('/supervisor/preventas/cumplimiento/covers/detalle', {
      fecha: store.fecha,
      ruta
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar auditoría de covers'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

async function toggleCoversAcordeon(r) {
  const ruta = r.ruta
  if (acordeonAbierto[ruta]) {
    acordeonAbierto[ruta] = false
    return
  }
  acordeonAbierto[ruta] = true
  await cargarDetalleRuta(ruta)
}
</script>
