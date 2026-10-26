<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 3: Cumplimiento de Objetivo Diario de Venta
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            10 pts Base · Criterio Binario
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: La preventa del día debe cubrir el objetivo diario exigido (Brecha acumulada / divisor preventa). Otorga 10 puntos (Todo o Nada).
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
      <table class="w-full text-left border-collapse min-w-[920px] text-[12px]">
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
              @click="toggleSort('meta')"
              class="px-3 py-2 text-right border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'meta' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Meta Día"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Meta Día (Bs)</span>
                <span v-if="sortCol === 'meta'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('venta')"
              class="px-3 py-2 text-right border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'venta' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Preventa Hoy"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Preventa Hoy (Bs)</span>
                <span v-if="sortCol === 'venta'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('dif')"
              class="px-3 py-2 text-right border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'dif' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Diferencia"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Diferencia (Bs)</span>
                <span v-if="sortCol === 'dif'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('pct')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'pct' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por % Alcance"
            >
              <div class="flex items-center justify-center gap-1">
                <span>% Alcance</span>
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
              title="Ordenar por Puntos (10)"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Puntos (10)</span>
                <span v-if="sortCol === 'puntos'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-obj-${i}`">
              <td class="px-2.5 py-2 sticky left-0"><div class="h-3.5 w-5 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 sticky left-12"><div class="h-3.5 w-16 skeleton"></div></td>
              <td class="px-3 py-2 sticky left-[112px]"><div class="h-3.5 w-32 skeleton"></div></td>
              <td v-for="c in 5" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-14 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="9" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas para los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <template v-for="(r, idx) in rutasOrdenadas" :key="r.ruta || idx">
              <tr
                @click="toggleObjetivoAcordeon(r)"
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

                <!-- Meta Día -->
                <td class="px-3 py-1.5 text-right font-mono font-medium text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ Number(metricasObj(r).metaDia).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </td>

                <!-- Preventa Hoy -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ Number(metricasObj(r).ventaHoy).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </td>

                <!-- Diferencia -->
                <td
                  class="px-3 py-1.5 text-right font-mono font-bold border-r border-black/[0.04]"
                  :class="metricasObj(r).dif >= 0 ? 'text-[#1E6B37]' : 'text-[#C9342C]'"
                >
                  {{ (metricasObj(r).dif >= 0 ? '+' : '') + Number(metricasObj(r).dif).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </td>

                <!-- % Alcance -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span :class="metricasObj(r).pct >= 100 ? 'text-[#1E6B37]' : 'text-[#C9342C]'">
                    {{ Number(metricasObj(r).pct).toFixed(1) }}%
                  </span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-1.5 text-center border-r border-black/[0.04]">
                  <span
                    v-if="r.kpis?.cumplimiento_objetivo?.cumple"
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]"
                  >CUMPLE</span>
                  <span
                    v-else
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]"
                  >DÉFICIT</span>
                </td>

                <!-- Puntos (10) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="r.kpis?.cumplimiento_objetivo?.cumple"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >10</span>
                  <span v-else class="text-[#86868B] text-[11px]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Balance Dinámico y Pedidos -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="9" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Calculando balance dinámico de cuota para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="space-y-3">
                    
                    <!-- Tarjeta de Balance Dinámico Mensual -->
                    <div class="apple-glass-card rounded-[12px] p-3 text-[11px] bg-white/70">
                      <div class="text-[10px] font-bold uppercase tracking-wider text-[#86868B] mb-2">
                        Balance Dinámico de Cuota Mensual
                      </div>
                      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                        <div class="apple-control-pill p-2 rounded-[8px]">
                          <div class="text-[9px] text-[#86868B] uppercase">Meta Mensual</div>
                          <div class="font-mono font-bold text-[#1D1D1F]">
                            Bs {{ Number(detallePorRuta[r.ruta].balance?.meta_mes || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                          </div>
                        </div>
                        <div class="apple-control-pill p-2 rounded-[8px]">
                          <div class="text-[9px] text-[#86868B] uppercase">Facturado Previo</div>
                          <div class="font-mono font-bold text-[#1D1D1F]">
                            Bs {{ Number(detallePorRuta[r.ruta].balance?.facturado_previo || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                          </div>
                        </div>
                        <div class="apple-control-pill p-2 rounded-[8px]">
                          <div class="text-[9px] text-[#86868B] uppercase">Brecha Acumulada</div>
                          <div class="font-mono font-bold text-[#1D1D1F]">
                            Bs {{ Number(detallePorRuta[r.ruta].balance?.brecha_acumulada || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                          </div>
                        </div>
                        <div class="apple-control-pill p-2 rounded-[8px]">
                          <div class="text-[9px] text-[#86868B] uppercase">Días Restantes</div>
                          <div class="font-mono font-bold text-[#1D1D1F]">
                            {{ detallePorRuta[r.ruta].balance?.dias_restantes || 1 }} días
                          </div>
                        </div>
                        <div class="apple-control-pill p-2 rounded-[8px]">
                          <div class="text-[9px] text-[#86868B] uppercase">Cuota Exigida Hoy</div>
                          <div class="font-mono font-bold text-[#0071E3]">
                            Bs {{ Number(detallePorRuta[r.ruta].balance?.meta_dia || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                          </div>
                        </div>
                        <div class="apple-control-pill p-2 rounded-[8px]">
                          <div class="text-[9px] text-[#86868B] uppercase">Venta Lograda</div>
                          <div class="font-mono font-bold text-[#1E6B37]">
                            Bs {{ Number(detallePorRuta[r.ruta].balance?.preventa_hoy || 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Tabla de Clientes con Pedido -->
                    <div class="apple-glass-card rounded-[12px] overflow-hidden">
                      <div class="px-3 py-2 border-b border-black/[0.06] flex items-center justify-between bg-white/50 text-[11px]">
                        <span class="font-bold text-[#1D1D1F]">
                          Pedidos Levantados en el Día: {{ r.ruta }} ({{ r.vendedor }})
                        </span>
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px] font-mono text-[10px] text-[#6E6E73]">
                          Total Compradores: <strong>{{ detallePorRuta[r.ruta].total_clientes || 0 }}</strong>
                        </span>
                      </div>

                      <div v-if="(detallePorRuta[r.ruta].clientes || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px]">
                        No se registraron transacciones de preventa para esta ruta en el día.
                      </div>

                      <div v-else class="overflow-x-auto max-h-80 custom-scroll">
                        <table class="w-full text-left border-collapse text-[11px]">
                          <thead class="sticky top-0 z-10 border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73] bg-[#F8F8FA]">
                            <tr>
                              <th class="px-2.5 py-1.5 text-center w-8">#</th>
                              <th class="px-2.5 py-1.5 w-20">Código</th>
                              <th class="px-3 py-1.5">Cliente / PDV</th>
                              <th class="px-2.5 py-1.5 text-center">Ítems SKU</th>
                              <th class="px-3 py-1.5 text-right font-mono font-bold">Monto Venta (Bs)</th>
                              <th class="px-2.5 py-1.5 text-center w-20">Detalle</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.03]">
                            <template v-for="(c, cIdx) in detallePorRuta[r.ruta].clientes" :key="c.cliente_id || cIdx">
                              <tr
                                @click="toggleSubCliente(`${r.ruta}_${c.cliente_id}`)"
                                class="hover:bg-black/[0.02] cursor-pointer transition-colors"
                              >
                                <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#86868B]">{{ cIdx + 1 }}</td>
                                <td class="px-2.5 py-1 font-mono text-[10px] text-[#6E6E73]">{{ c.cliente_id }}</td>
                                <td class="px-3 py-1 font-semibold text-[#1D1D1F] truncate max-w-[280px]" :title="c.cliente">
                                  <span>{{ c.cliente }}</span>
                                  <span v-if="c.nro_preventas" class="text-[9px] font-mono text-[#86868B] ml-1.5">({{ c.nro_preventas }})</span>
                                </td>
                                <td class="px-2.5 py-1 text-center font-mono font-bold text-[#1D1D1F]">
                                  <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-[#0071E3]/10 text-[#0071E3]">
                                    {{ (c.items || []).length }} SKU
                                  </span>
                                </td>
                                <td class="px-3 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                  Bs {{ Number(c.total_bs || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </td>
                                <td class="px-2.5 py-1 text-center">
                                  <button
                                    type="button"
                                    class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold transition-all cursor-pointer"
                                    :class="subClienteAbierto[`${r.ruta}_${c.cliente_id}`]
                                      ? 'bg-black/[0.08] text-[#1D1D1F]'
                                      : 'bg-[#0071E3] text-white hover:bg-[#0077ED] shadow-xs'"
                                  >
                                    {{ subClienteAbierto[`${r.ruta}_${c.cliente_id}`] ? 'Ocultar' : 'Ver SKU' }}
                                  </button>
                                </td>
                              </tr>

                              <!-- Desglose de SKUs del pedido -->
                              <tr v-if="subClienteAbierto[`${r.ruta}_${c.cliente_id}`]">
                                <td colspan="6" class="p-2.5 bg-blue-50/20 border-b border-black/[0.05]">
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
                                        <tr v-for="(it, itIdx) in (c.items || [])" :key="`${it.codigo_producto}_${itIdx}`" class="hover:bg-black/[0.01]">
                                          <td class="px-2.5 py-1 font-mono text-[#6E6E73]">{{ it.codigo_producto || '—' }}</td>
                                          <td class="px-3 py-1 font-medium text-[#1D1D1F]">{{ it.producto }}</td>
                                          <td class="px-2.5 py-1 text-center text-[#86868B]">{{ it.categoria || '—' }}</td>
                                          <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">{{ it.cantidad }}</td>
                                          <td class="px-2.5 py-1 text-right font-mono text-[#6E6E73]">
                                            {{ Number(it.precio_unitario || 0).toFixed(2) }}
                                          </td>
                                          <td class="px-3 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                            {{ Number(it.monto_final || 0).toFixed(2) }}
                                          </td>
                                        </tr>
                                      </tbody>
                                      <tfoot class="border-t border-black/[0.06] bg-black/[0.02] font-bold">
                                        <tr>
                                          <td colspan="3" class="px-2.5 py-1 text-right uppercase text-[9px] text-[#6E6E73]">Total Pedido:</td>
                                          <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                            {{ (c.items || []).reduce((acc, x) => acc + (x.cantidad || 0), 0) }}
                                          </td>
                                          <td></td>
                                          <td class="px-3 py-1 text-right font-mono font-bold text-[#0071E3]">
                                            Bs {{ Number(c.total_bs || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                          </td>
                                        </tr>
                                      </tfoot>
                                    </table>
                                  </div>
                                </td>
                              </tr>
                            </template>
                          </tbody>
                        </table>
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

const sortCol = ref('puntos')
const sortDir = ref('desc')

const sortLabels = {
  puntos: 'Puntos (10)',
  meta: 'Meta Día',
  venta: 'Preventa Hoy',
  dif: 'Diferencia',
  pct: '% Alcance',
  estatus: 'Estatus',
  ruta: 'Ruta'
}

function metricasObj(r) {
  const m = r.kpis?.cumplimiento_objetivo?.metricas || {}
  const meta = Number(m.meta_dia || 0)
  const venta = Number(m.venta_hoy || 0)
  const dif = m.diferencia !== undefined ? Number(m.diferencia) : (venta - meta)
  const pct = m.alcance_pct !== undefined ? Number(m.alcance_pct) : (meta > 0 ? (venta / meta) * 100 : 0)
  return { metaDia: meta, ventaHoy: venta, dif, pct }
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = col === 'meta' ? 'asc' : 'desc'
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
    const mA = metricasObj(a)
    const mB = metricasObj(b)
    let valA = 0
    let valB = 0
    if (col === 'meta') {
      valA = mA.metaDia
      valB = mB.metaDia
    } else if (col === 'venta') {
      valA = mA.ventaHoy
      valB = mB.ventaHoy
    } else if (col === 'dif') {
      valA = mA.dif
      valB = mB.dif
    } else if (col === 'pct') {
      valA = mA.pct
      valB = mB.pct
    } else if (col === 'estatus') {
      valA = a.kpis?.cumplimiento_objetivo?.cumple ? 1 : 0
      valB = b.kpis?.cumplimiento_objetivo?.cumple ? 1 : 0
    } else if (col === 'puntos') {
      valA = a.kpis?.cumplimiento_objetivo?.cumple ? 10 : 0
      valB = b.kpis?.cumplimiento_objetivo?.cumple ? 10 : 0
    }
    if (valA !== valB) {
      return dir * (valA - valB)
    }
    return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
  })

  return lista
})

const acordeonAbierto = reactive({})
const subClienteAbierto = reactive({})
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

async function toggleObjetivoAcordeon(r) {
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
    const res = await axios.post('/supervisor/preventas/cumplimiento/objetivo/detalle', {
      fecha: store.fecha,
      ruta,
      mes: store.fecha ? store.fecha.slice(0, 7) : ''
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar balance de cuota'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

function toggleSubCliente(key) {
  subClienteAbierto[key] = !subClienteAbierto[key]
}
</script>

