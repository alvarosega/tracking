<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans text-ink space-y-2.5 p-3 sm:p-4 select-none">
      
      <!-- ==========================================
           1. BARRA SUPERIOR DE FILTROS & NAVEGACIÓN
           ========================================== -->
      <header class="apple-glass-card rounded-[16px] p-2 sm:p-2.5 border border-white/60 flex flex-wrap items-center justify-between gap-2 shrink-0 z-30">
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
          
          <!-- SubNavTabs (Cumplimiento, Diaria, Productos, Auditoría, Comparativos, Terreno) -->
          <SubNavTabs :tabs="preventasSubTabs" currentTab="productos" />

          <div class="h-5 w-px bg-black/[0.08] hidden sm:block shrink-0"></div>

          <!-- Selector de Fecha -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Fecha:</span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="alCambiarFecha"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-num"
            />
          </div>

          <!-- Selector de Canal -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <select
              v-model="canalSeleccionado"
              @change="alCambiarCanal"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
            >
              <option value="TODOS">Todos</option>
              <option v-for="c in canalesDisponibles" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Selector de Categoría -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Categoría:</span>
            <select
              v-model="categoriaSeleccionada"
              @change="cargarVistaActual"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer max-w-[170px] truncate"
            >
              <option value="TODAS">TODAS LAS CATEGORÍAS</option>
              <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
            </select>
          </div>

          <!-- Selector de Modo de Vista (Apple Segmented Control) -->
          <div class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[11px]">
            <button
              type="button"
              @click="cambiarModo('ranking')"
              class="px-3 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
              :class="modoActual === 'ranking' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73]'"
            >
              Ranking &amp; Rutas
            </button>
            <button
              type="button"
              @click="cambiarModo('heatmap')"
              class="px-3 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
              :class="modoActual === 'heatmap' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73]'"
            >
              Mapa de Calor (Heatmap)
            </button>
          </div>

          <!-- Buscador de Producto -->
          <div v-if="modoActual === 'ranking'" class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <svg class="w-3.5 h-3.5 text-[#86868B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              type="text"
              v-model="busqueda"
              @keyup.enter="cargarRankingProductos"
              placeholder="Buscar SKU..."
              class="bg-transparent text-[11px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-28 sm:w-36 font-num"
            />
          </div>

          <!-- Selector de Límite (Top N) -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Top:</span>
            <select
              v-model="limitSeleccionado"
              @change="cargarVistaActual"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
            >
              <option value="50">50</option>
              <option value="100">100</option>
              <option value="150">150</option>
              <option value="500">Todos</option>
            </select>
          </div>

          <!-- Botón Consultar Principal -->
          <button
            type="button"
            @click="alConsultar"
            :disabled="cargandoRanking || cargandoHeatmap"
            class="flex items-center gap-1.5 px-3 py-1 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed select-none shrink-0"
          >
            <svg
              v-if="cargandoRanking || cargandoHeatmap"
              class="w-3.5 h-3.5 animate-spin text-white shrink-0"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <svg
              v-else
              class="w-3.5 h-3.5 text-white shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>{{ (cargandoRanking || cargandoHeatmap) ? 'Consultando...' : 'Consultar' }}</span>
          </button>
        </div>

        <!-- Indicador de Resumen -->
        <div class="flex items-center gap-2.5 text-[11px] font-mono text-[#6E6E73]">
          <span v-if="modoActual === 'ranking' && !cargandoRanking" class="hidden md:inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] bg-black/[0.04]">
            <span>Total SKUs: <strong>{{ productosRanking.length }}</strong></span>
            <span>·</span>
            <span>Venta: <strong class="text-emerald-700">{{ fmtBs(totalVentaRanking) }}</strong></span>
          </span>
        </div>
      </header>

      <!-- ==========================================
           2. CONTENIDO PRINCIPAL: RANKING O HEATMAP
           ========================================== -->
      <main class="flex-1 min-h-0 overflow-hidden flex flex-col">
        
        <!-- MODO 1: TABLA RANKING CON ACORDEÓN POR RUTA -->
        <div v-if="modoActual === 'ranking'" class="apple-glass-card rounded-[16px] overflow-hidden border border-white/60 flex flex-col h-full shadow-glass-card">
          <div class="p-2.5 sm:p-3 bg-white/70 backdrop-blur-md border-b border-black/[0.06] flex justify-between items-center select-none shrink-0">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span class="text-[12px] font-bold text-[#1D1D1F] uppercase tracking-tight">
                Ranking de Productos Más Vendidos &amp; Desglose por Ruta
              </span>
            </div>
            <span class="text-[10px] text-[#6E6E73] font-medium hidden sm:inline">
              Haz clic en cualquier fila para desplegar las rutas vendedoras
            </span>
          </div>

          <div class="flex-1 overflow-auto custom-scroll relative">
            <table class="w-full text-left text-[11px] text-[#1D1D1F] border-separate border-spacing-0 font-num">
              <thead class="bg-[#F5F5F7]/95 text-[#6E6E73] font-bold text-[10px] uppercase tracking-wider sticky top-0 z-20 select-none border-b border-[#E5E5EA] backdrop-blur-md">
                <tr>
                  <th class="py-2.5 px-3 text-center w-12 sticky left-0 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA]">#</th>
                  <th class="py-2.5 px-3.5 min-w-[240px] sticky left-12 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA] shadow-[2px_0_6px_rgba(0,0,0,0.03)]">Producto</th>
                  <th class="py-2.5 px-3 border-b border-r border-[#E5E5EA]/60">Categoría</th>
                  <th class="py-2.5 px-3 text-right border-b border-r border-[#E5E5EA]/60 font-bold">Total Venta (Bs)</th>
                  <th class="py-2.5 px-3 text-center border-b border-r border-[#E5E5EA]/60">Cantidad</th>
                  <th class="py-2.5 px-3 text-center border-b border-r border-[#E5E5EA]/60">PDVs</th>
                  <th class="py-2.5 px-3 text-center border-b border-r border-[#E5E5EA]/60">Cobertura</th>
                  <th class="py-2.5 px-3 text-right border-b border-r border-[#E5E5EA]/60">% Part.</th>
                  <th class="py-2.5 px-3 text-center border-b border-[#E5E5EA] w-12"></th>
                </tr>
              </thead>

              <tbody class="divide-y divide-[#E5E5EA] bg-white/80">
                <!-- Estado Cargando -->
                <tr v-if="cargandoRanking">
                  <td colspan="9" class="py-24 text-center text-[#6E6E73] text-xs font-semibold">
                    <div class="w-6 h-6 mx-auto mb-2 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin"></div>
                    Cargando ranking de productos...
                  </td>
                </tr>

                <!-- Error -->
                <tr v-else-if="errorRanking">
                  <td colspan="9" class="py-16 text-center text-[#C9342C] font-semibold text-[12px]">
                    Error al cargar productos: {{ errorRanking }}
                  </td>
                </tr>

                <!-- Vacío -->
                <tr v-else-if="productosRanking.length === 0">
                  <td colspan="9" class="py-16 text-center text-[#86868B] font-medium text-[12px]">
                    No se encontraron productos con los filtros seleccionados.
                  </td>
                </tr>

                <!-- Lista de Productos con Acordeón -->
                <template v-else v-for="(p, idx) in productosRanking" :key="p.producto_id">
                  <tr
                    @click="toggleProductoDetalleRutas(p.producto_id)"
                    class="hover:bg-[#0071E3]/[0.03] border-b border-[#E5E5EA] font-semibold transition-colors cursor-pointer select-none group"
                    :class="estaExpandido(p.producto_id) ? 'bg-blue-50/70' : 'bg-white/60'"
                  >
                    <td class="py-2 px-3 text-center font-bold sticky left-0 z-10 bg-white group-hover:bg-[#F5F5F7] border-r border-[#E5E5EA] text-[#6E6E73]">
                      {{ idx + 1 }}
                    </td>
                    <td
                      class="py-2 px-3.5 font-bold text-[#1D1D1F] sticky left-12 z-10 bg-white group-hover:bg-[#F5F5F7] border-r border-[#E5E5EA] shadow-[2px_0_6px_rgba(0,0,0,0.03)] truncate max-w-[260px]"
                      :title="p.producto"
                    >
                      <div class="truncate">{{ p.producto }}</div>
                      <div class="text-[9px] text-[#86868B] font-mono">ID: #{{ p.producto_id }}</div>
                    </td>
                    <td class="py-2 px-3 text-[#6E6E73] font-medium truncate max-w-[140px]" :title="p.categoria">
                      {{ p.categoria }}
                    </td>
                    <td class="py-2 px-3 text-right font-black font-num text-[#1D1D1F]">
                      {{ fmtBs(p.monto_total) }}
                    </td>
                    <td class="py-2 px-3 text-center font-bold font-num text-[#1D1D1F]">
                      {{ fmtNum(p.cantidad_total) }}
                    </td>
                    <td class="py-2 px-3 text-center font-num text-[#0071E3] font-bold">
                      {{ p.pdvs_compradores }}
                    </td>
                    <td class="py-2 px-3 text-center font-num text-emerald-700 font-bold">
                      {{ p.rutas_cobertura }} rutas
                    </td>
                    <td class="py-2 px-3 text-right font-num font-bold text-[#6E6E73]">
                      {{ p.pct_participacion }}%
                    </td>
                    <td class="py-2 px-3 text-center text-[#86868B]">
                      <svg
                        class="w-4 h-4 mx-auto transition-transform duration-200"
                        :class="estaExpandido(p.producto_id) ? 'rotate-180 text-[#0071E3]' : 'text-slate-400'"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </td>
                  </tr>

                  <!-- Fila Desplegable del Acordeón (Desglose por Ruta) -->
                  <tr v-if="estaExpandido(p.producto_id)" class="bg-[#F5F5F7]/80">
                    <td colspan="9" class="p-3">
                      <div class="p-3 bg-white rounded-[12px] border border-black/[0.06] shadow-xs">
                        <div class="flex items-center justify-between mb-2">
                          <span class="text-[11px] font-bold text-[#1D1D1F]">
                            Desglose de Rutas Vendedoras · {{ p.producto }}
                          </span>
                          <span class="text-[10px] text-[#6E6E73] font-num">
                            {{ detallesRutasCache[p.producto_id]?.total_rutas || 0 }} rutas transaccionadas
                          </span>
                        </div>

                        <div v-if="cargandoDetalleProducto[p.producto_id]" class="py-4 text-center text-xs text-[#6E6E73]">
                          <span class="inline-block w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin mr-1"></span>
                          Cargando detalle de rutas...
                        </div>

                        <div v-else-if="detallesRutasCache[p.producto_id]?.rutas?.length === 0" class="py-4 text-center text-xs text-[#86868B]">
                          Sin transacciones registradas para este producto.
                        </div>

                        <table v-else class="w-full text-left text-[11px] border-collapse">
                          <thead>
                            <tr class="border-b border-black/[0.06] text-[#86868B] text-[9px] uppercase font-bold">
                              <th class="py-1 px-2">Ruta</th>
                              <th class="py-1 px-2">Preventista</th>
                              <th class="py-1 px-2 text-right">Venta (Bs)</th>
                              <th class="py-1 px-2 text-center">Cantidad</th>
                              <th class="py-1 px-2 text-center">PDVs</th>
                              <th class="py-1 px-2 text-right">% Participación</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.03] font-num">
                            <tr v-for="r in detallesRutasCache[p.producto_id]?.rutas" :key="r.ruta" class="hover:bg-black/[0.01]">
                              <td class="py-1.5 px-2 font-bold text-[#0071E3]">{{ r.ruta }}</td>
                              <td class="py-1.5 px-2 text-[#1D1D1F]">{{ r.vendedor }}</td>
                              <td class="py-1.5 px-2 text-right font-bold text-[#1D1D1F]">{{ fmtBs(r.monto) }}</td>
                              <td class="py-1.5 px-2 text-center">{{ fmtNum(r.cantidad) }}</td>
                              <td class="py-1.5 px-2 text-center font-bold text-[#0071E3]">{{ r.pdvs }}</td>
                              <td class="py-1.5 px-2 text-right font-bold text-emerald-700">{{ r.pct_ruta }}%</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>

              <!-- Totales al pie -->
              <tfoot v-if="!cargandoRanking && productosRanking.length > 0" class="sticky bottom-0 z-20 bg-[#F5F5F7] border-t-2 border-[#E5E5EA] font-bold">
                <tr class="text-[11px]">
                  <td colspan="3" class="py-2.5 px-3 border-r border-[#E5E5EA] uppercase tracking-wider text-[#1D1D1F] sticky left-0 z-30 bg-[#F5F5F7]">
                    TOTAL ({{ productosRanking.length }} PRODUCTOS EN LISTA)
                  </td>
                  <td class="py-2.5 px-3 text-right font-black font-num text-[#1D1D1F] border-r border-[#E5E5EA]">
                    {{ fmtBs(totalVentaRanking) }}
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold font-num text-[#1D1D1F] border-r border-[#E5E5EA]">
                    {{ fmtNum(totalCantidadRanking) }}
                  </td>
                  <td class="py-2.5 px-3 text-center border-r border-[#E5E5EA]">—</td>
                  <td class="py-2.5 px-3 text-center border-r border-[#E5E5EA]">—</td>
                  <td class="py-2.5 px-3 text-right font-bold border-r border-[#E5E5EA]">100.0%</td>
                  <td class="py-2.5 px-3"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- MODO 2: MAPA DE CALOR (HEATMAP 2D PRODUCTOS VS RUTAS) -->
        <div v-else class="apple-glass-card rounded-[16px] overflow-hidden border border-white/60 flex flex-col h-full shadow-glass-card">
          <div class="p-2.5 sm:p-3 bg-white/70 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap justify-between items-center gap-2 select-none shrink-0">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#1D1D1F]"></span>
              <span class="text-[12px] font-bold text-[#1D1D1F] uppercase tracking-tight">
                Mapa de Calor (Heatmap) · Ventas por Producto y Ruta
              </span>
            </div>

            <!-- Leyenda de Intensidades -->
            <div class="flex flex-wrap items-center gap-2.5 text-[10px] font-medium text-[#6E6E73]">
              <span class="font-bold">Intensidad:</span>
              <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-[3px] bg-[#EBF9EF] border border-[#D1F2D9]"></span>
                <span>0 Bs</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-[3px] bg-[#BAE6FD] border border-[#7DD3FC]"></span>
                <span>Baja</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-[3px] bg-[#38BDF8] border border-[#0284C7]"></span>
                <span>Media</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-[3px] bg-[#0284C7] text-white border border-[#0369A1]"></span>
                <span>Alta</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-3 h-3 rounded-[3px] bg-[#1E3A8A] text-white border border-[#1E40AF]"></span>
                <span>Líder</span>
              </div>
            </div>
          </div>

          <!-- Matriz Heatmap Scrollable -->
          <div class="flex-1 overflow-auto custom-scroll relative">
            <div v-if="cargandoHeatmap" class="py-24 text-center text-[#6E6E73] text-xs font-semibold">
              <div class="w-6 h-6 mx-auto mb-2 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin"></div>
              Calculando matriz de calor...
            </div>

            <div v-else-if="errorHeatmap" class="py-16 text-center text-[#C9342C] font-semibold text-[12px]">
              Error al calcular heatmap: {{ errorHeatmap }}
            </div>

            <div v-else-if="heatmapData.productos.length === 0 || heatmapData.rutas.length === 0" class="py-16 text-center text-[#86868B] font-medium text-[12px]">
              Sin datos para generar la matriz con los filtros seleccionados.
            </div>

            <table v-else class="w-full min-w-[980px] text-left text-[11px] border-separate border-spacing-0 font-num">
              <thead class="bg-[#F5F5F7]/95 text-[#1D1D1F] font-bold text-[10px] uppercase sticky top-0 z-20 select-none border-b border-[#E5E5EA] backdrop-blur-md">
                <tr>
                  <th class="py-2.5 px-3 text-left w-64 sticky left-0 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA] shadow-[2px_0_6px_rgba(0,0,0,0.03)]">
                    Producto
                  </th>
                  <th
                    v-for="r in heatmapData.rutas"
                    :key="r.ruta"
                    class="py-2.5 px-2 text-center min-w-[75px] border-b border-r border-[#E5E5EA]/60 truncate"
                    :title="`${r.ruta} - ${r.vendedor}`"
                  >
                    <span class="block text-[10px] font-black text-[#1D1D1F]">{{ r.ruta }}</span>
                  </th>
                </tr>
              </thead>

              <tbody class="divide-y divide-[#E5E5EA] bg-white/85">
                <tr
                  v-for="p in heatmapData.productos"
                  :key="p.producto_id"
                  class="border-b border-[#E5E5EA] hover:bg-[#F5F5F7]/40 transition-colors"
                >
                  <td
                    class="py-2 px-3 font-bold text-[#1D1D1F] sticky left-0 z-10 bg-white border-r border-[#E5E5EA] shadow-[2px_0_6px_rgba(0,0,0,0.03)] truncate max-w-[250px]"
                    :title="`${p.producto} (${p.categoria})`"
                  >
                    <span class="block text-[11px] text-[#1D1D1F] truncate">{{ p.producto }}</span>
                    <span class="block text-[9px] text-[#6E6E73] font-normal truncate">{{ p.categoria }}</span>
                  </td>
                  <td
                    v-for="r in heatmapData.rutas"
                    :key="`${p.producto_id}-${r.ruta}`"
                    class="py-2 px-1 text-center border-r border-[#E5E5EA]/40 text-[10px] font-num transition-all cursor-pointer"
                    :class="claseCeldaHeatmap(p.producto_id, r.ruta)"
                    :title="tooltipCeldaHeatmap(p, r)"
                  >
                    {{ textoCeldaHeatmap(p.producto_id, r.ruta) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </main>

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_default: { type: String, default: () => new Date().toISOString().split('T')[0] },
});

// Submódulos Preventas
const preventasSubTabs = [
  { id: 'cumplimiento', name: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
  { id: 'diaria', name: 'Preventa Diaria', route: 'supervisor.preventas.diaria' },
  { id: 'productos', name: 'Productos', route: 'supervisor.preventas.productos' },
  { id: 'auditoria', name: 'Auditoría', route: 'supervisor.preventas.auditoria' },
  { id: 'comparativos', name: 'Comparativos', route: 'supervisor.preventas.comparativos' },
  { id: 'mapa', name: 'Auditoría en Terreno', route: 'supervisor.preventas.index' },
];

// Estado de Filtros
const filtroFecha = ref(props.fecha_default);
const canalSeleccionado = ref('TDB');
const canalesDisponibles = ref(props.canales || ['TDB', 'MAYORISTA', 'PROVINCIA']);
const categoriaSeleccionada = ref('TODAS');
const categorias = ref([]);
const busqueda = ref('');
const limitSeleccionado = ref('150');
const modoActual = ref('ranking'); // 'ranking' | 'heatmap'

// Estado Ranking
const cargandoRanking = ref(false);
const errorRanking = ref(null);
const productosRanking = ref([]);
const totalVentaRanking = ref(0);
const totalCantidadRanking = ref(0);

// Acordeón de Rutas por Producto
const productosExpandidos = ref(new Set());
const detallesRutasCache = reactive({});
const cargandoDetalleProducto = reactive({});

// Estado Heatmap
const cargandoHeatmap = ref(false);
const errorHeatmap = ref(null);
const heatmapData = ref({
  rutas: [],
  productos: [],
  matrix: {},
  max_monto: 0,
});

function fmtBs(v) {
  return `Bs ${Number(v || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function fmtNum(v) {
  return Number(v || 0).toLocaleString('es-BO');
}

function cambiarModo(modo) {
  if (modoActual.value === modo) return;
  modoActual.value = modo;
  cargarVistaActual();
}

function alCambiarCanal() {
  cargarCategorias();
  cargarVistaActual();
}

function alCambiarFecha() {
  cargarCategorias();
  cargarVistaActual();
}

function alConsultar() {
  cargarCategorias();
  cargarVistaActual();
}

function cargarVistaActual() {
  if (modoActual.value === 'ranking') {
    cargarRankingProductos();
  } else {
    cargarHeatmapProductos();
  }
}

async function cargarCategorias() {
  try {
    const res = await axios.post('/supervisor/preventas/productos/categorias', {
      fecha: filtroFecha.value,
      canal: canalSeleccionado.value,
    });
    categorias.value = res.data.categorias || [];
    if (categoriaSeleccionada.value !== 'TODAS' && !categorias.value.includes(categoriaSeleccionada.value)) {
      categoriaSeleccionada.value = 'TODAS';
    }
  } catch (err) {
    console.warn('Error al cargar categorías de preventa:', err);
  }
}

async function cargarRankingProductos() {
  cargandoRanking.value = true;
  errorRanking.value = null;

  try {
    const res = await axios.post('/supervisor/preventas/productos/ranking', {
      fecha: filtroFecha.value,
      canal: canalSeleccionado.value,
      categoria: categoriaSeleccionada.value,
      busqueda: busqueda.value,
      limit: Number(limitSeleccionado.value),
    });

    const data = res.data;
    productosRanking.value = data.productos || [];
    totalVentaRanking.value = Number(data.total_venta || 0);
    totalCantidadRanking.value = Number(data.total_cantidad || 0);
  } catch (err) {
    errorRanking.value = err.message || 'Error al conectar con el servidor';
  } finally {
    cargandoRanking.value = false;
  }
}

function estaExpandido(productoId) {
  return productosExpandidos.value.has(productoId);
}

async function toggleProductoDetalleRutas(productoId) {
  const nuevoSet = new Set(productosExpandidos.value);
  if (nuevoSet.has(productoId)) {
    nuevoSet.delete(productoId);
    productosExpandidos.value = nuevoSet;
    return;
  }

  nuevoSet.add(productoId);
  productosExpandidos.value = nuevoSet;

  if (detallesRutasCache[productoId]) return;

  cargandoDetalleProducto[productoId] = true;
  try {
    const res = await axios.post('/supervisor/preventas/productos/detalle', {
      fecha: filtroFecha.value,
      producto_id: productoId,
      canal: canalSeleccionado.value,
    });
    detallesRutasCache[productoId] = res.data;
  } catch (err) {
    console.error('Error cargando detalle de rutas para SKU:', err);
  } finally {
    cargandoDetalleProducto[productoId] = false;
  }
}

async function cargarHeatmapProductos() {
  cargandoHeatmap.value = true;
  errorHeatmap.value = null;

  try {
    const res = await axios.post('/supervisor/preventas/productos/heatmap', {
      fecha: filtroFecha.value,
      canal: canalSeleccionado.value,
      categoria: categoriaSeleccionada.value,
      top_skus: Number(limitSeleccionado.value) > 50 ? 50 : Number(limitSeleccionado.value),
    });

    const data = res.data;
    heatmapData.value = {
      rutas: data.rutas || [],
      productos: data.productos || [],
      matrix: data.matrix || {},
      max_monto: Number(data.max_monto || 0),
    };
  } catch (err) {
    errorHeatmap.value = err.message || 'Error al conectar con el servidor';
  } finally {
    cargandoHeatmap.value = false;
  }
}

function obtenerCeldaHeatmap(productoId, ruta) {
  const row = heatmapData.value.matrix[String(productoId)] || {};
  return row[ruta] || { monto: 0, cantidad: 0, pdvs: 0 };
}

function textoCeldaHeatmap(productoId, ruta) {
  const celda = obtenerCeldaHeatmap(productoId, ruta);
  const m = Number(celda.monto || 0);
  return m > 0 ? Math.round(m).toLocaleString('es-BO') : '—';
}

function claseCeldaHeatmap(productoId, ruta) {
  const celda = obtenerCeldaHeatmap(productoId, ruta);
  const m = Number(celda.monto || 0);
  if (m <= 0) return 'bg-[#EBF9EF]/30 text-[#86868B] border-[#E5E5EA]/40';

  const maxMonto = Number(heatmapData.value.max_monto || 0);
  const ratio = maxMonto > 0 ? m / maxMonto : 0;

  if (ratio >= 0.7) return 'bg-[#1E3A8A] text-white font-black border-[#1E40AF]';
  if (ratio >= 0.4) return 'bg-[#0284C7] text-white font-bold border-[#0369A1]';
  if (ratio >= 0.15) return 'bg-[#38BDF8] text-[#0F172A] font-bold border-[#0284C7]';
  return 'bg-[#BAE6FD] text-[#0F172A] font-semibold border-[#7DD3FC]';
}

function tooltipCeldaHeatmap(p, r) {
  const celda = obtenerCeldaHeatmap(p.producto_id, r.ruta);
  const m = Number(celda.monto || 0);
  return `${p.producto} | Ruta: ${r.ruta} (${r.vendedor})\nVenta: ${fmtBs(m)}\nCantidad: ${celda.cantidad}\nPDVs Compradores: ${celda.pdvs}`;
}

onMounted(() => {
  cargarCategorias();
  cargarVistaActual();
});
</script>

