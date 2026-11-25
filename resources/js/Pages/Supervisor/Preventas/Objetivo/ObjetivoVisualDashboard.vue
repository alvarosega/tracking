<template>
  <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans">
    
    <!-- HEADER RESUMEN EJECUTIVO (Apple Glass Cards) -->
    <section class="p-2.5 sm:p-3 pb-2 shrink-0">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        
        <!-- Tarjeta 1: Venta Total vs Meta Diaria -->
        <div class="apple-glass-card rounded-[14px] p-2.5 sm:p-3 bg-white border border-black/[0.08] shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[11px] text-[#86868B]">
            <span class="font-bold uppercase tracking-wider">Avance Global Día</span>
            <span
              class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono"
              :class="totales.alcance_global_pct >= 100 ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-[#0071E3]'"
            >
              {{ totales.alcance_global_pct }}%
            </span>
          </div>
          <div class="mt-1">
            <div class="text-[17px] sm:text-[20px] font-extrabold font-mono text-[#1D1D1F] tracking-tight">
              Bs {{ formatMoney(totales.venta_total) }}
            </div>
            <div class="text-[10px] text-[#6E6E73] flex items-center justify-between mt-0.5 font-medium">
              <span>Meta: <strong class="font-mono text-[#1D1D1F]">Bs {{ formatMoney(totales.meta_diaria_total) }}</strong></span>
              <span
                class="font-mono font-bold"
                :class="totales.diferencia_total >= 0 ? 'text-emerald-600' : 'text-rose-600'"
              >
                {{ totales.diferencia_total >= 0 ? '+' : '' }}Bs {{ formatMoney(totales.diferencia_total) }}
              </span>
            </div>
          </div>
          <!-- Barra de Progreso Global -->
          <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden mt-2">
            <div
              class="h-full transition-all duration-500 rounded-full"
              :class="totales.alcance_global_pct >= 100 ? 'bg-[#34C759]' : 'bg-[#0071E3]'"
              :style="{ width: `${Math.min(100, totales.alcance_global_pct)}%` }"
            ></div>
          </div>
        </div>

        <!-- Tarjeta 2: Puntos Unilever (10 pts por Ruta Cumple) -->
        <div class="apple-glass-card rounded-[14px] p-2.5 sm:p-3 bg-white border border-black/[0.08] shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[11px] text-[#86868B]">
            <span class="font-bold uppercase tracking-wider">Puntos Unilever (KPI 3)</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
              10 pts Base
            </span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <div class="text-[17px] sm:text-[20px] font-extrabold font-mono text-[#1E6B37]">
              {{ totales.puntos_unilever }}
            </div>
            <div class="text-xs font-mono text-[#86868B]">
              / {{ totales.puntos_unilever_max }} pts
            </div>
            <div class="ml-auto text-xs font-bold font-mono text-[#1D1D1F]">
              {{ totales.puntos_pct }}%
            </div>
          </div>
          <div class="text-[10px] text-[#6E6E73] flex items-center justify-between mt-1">
            <span class="flex items-center gap-1 font-semibold text-emerald-700">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              {{ totales.rutas_cumplen }} Cumplen
            </span>
            <span class="flex items-center gap-1 font-semibold text-rose-600">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              {{ totales.rutas_deficit }} En Déficit
            </span>
          </div>
        </div>

        <!-- Tarjeta 3: Ticket Promedio y Pedidos -->
        <div class="apple-glass-card rounded-[14px] p-2.5 sm:p-3 bg-white border border-black/[0.08] shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[11px] text-[#86868B]">
            <span class="font-bold uppercase tracking-wider">Ticket Promedio</span>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700">
              {{ totales.pedidos_total }} Pedidos
            </span>
          </div>
          <div class="mt-1">
            <div class="text-[17px] sm:text-[20px] font-extrabold font-mono text-[#1D1D1F] tracking-tight">
              Bs {{ formatMoney(totales.ticket_promedio) }}
            </div>
            <div class="text-[10px] text-[#6E6E73] flex items-center justify-between mt-0.5 font-medium">
              <span>Compradores: <strong class="font-mono text-[#1D1D1F]">{{ totales.compradores_total }}</strong></span>
              <span>SKUs: <strong class="font-mono text-[#1D1D1F]">{{ totales.skus_vendidos_total }}</strong></span>
            </div>
          </div>
          <div class="text-[9px] text-[#86868B] truncate mt-1">
            Promedio de facturación por pedido levantado
          </div>
        </div>

        <!-- Tarjeta 4: Calendario y Balance del Mes -->
        <div class="apple-glass-card rounded-[14px] p-2.5 sm:p-3 bg-white border border-black/[0.08] shadow-2xs flex flex-col justify-between">
          <div class="flex items-center justify-between text-[11px] text-[#86868B]">
            <span class="font-bold uppercase tracking-wider">Días del Mes</span>
            <span class="font-mono text-[10px] font-bold text-[#1D1D1F]">{{ mes }}</span>
          </div>
          <div class="mt-1 flex items-baseline justify-between">
            <div>
              <div class="text-[17px] sm:text-[20px] font-extrabold font-mono text-[#0071E3]">
                {{ diasHabilesRestantes }} <span class="text-xs font-sans text-[#6E6E73] font-normal">restantes</span>
              </div>
              <div class="text-[10px] text-[#6E6E73]">
                Operados: <strong class="font-mono text-[#1D1D1F]">{{ diasOperados }}</strong> / {{ diasHabilesMes }} hábiles
              </div>
            </div>
            <div class="text-right">
              <span class="text-[9px] font-bold uppercase text-[#86868B] block">Divisor Preventa</span>
              <span class="text-xs font-mono font-bold text-[#1D1D1F]">{{ divisorPreventa }}</span>
            </div>
          </div>
          <div class="text-[9px] text-[#86868B] truncate mt-1">
            Base para el balance dinámico de cuota
          </div>
        </div>

      </div>
    </section>

    <!-- CUERPO PRINCIPAL: BULLET CHARTS (IZQUIERDA/CENTRO) Y ANALÍTICA (DERECHA) -->
    <main class="flex-1 flex flex-col lg:flex-row min-h-0 gap-2.5 sm:gap-3 p-2.5 sm:p-3 pt-0 overflow-hidden">
      
      <!-- SECCIÓN 1: BULLET CHARTS POR RUTA (EVALUACIÓN DE CUOTA) -->
      <section class="flex-1 flex flex-col min-h-0 bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs overflow-hidden">
        
        <!-- Barra de Controles y Filtros Locales -->
        <div class="p-2.5 sm:p-3 border-b border-black/[0.06] bg-slate-50/70 backdrop-blur-sm flex flex-wrap items-center justify-between gap-2 shrink-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-xs sm:text-sm font-extrabold text-[#1D1D1F] uppercase tracking-tight flex items-center gap-1.5">
              <span>Bullet Chart · Cumplimiento de Meta Diaria</span>
              <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-slate-200 text-[#1D1D1F]">
                {{ rutasFiltradas.length }}
              </span>
            </h3>

            <!-- Filtro de Estado: Todos / Cumplen / Déficit -->
            <div class="flex items-center p-0.5 bg-black/[0.04] rounded-lg text-[10px] font-bold">
              <button
                type="button"
                @click="filtroEstado = 'todos'"
                class="px-2 py-1 rounded-[6px] transition-all cursor-pointer"
                :class="filtroEstado === 'todos' ? 'bg-white text-[#1D1D1F] shadow-2xs font-extrabold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Todas ({{ rutas.length }})
              </button>
              <button
                type="button"
                @click="filtroEstado = 'cumple'"
                class="px-2 py-1 rounded-[6px] transition-all cursor-pointer flex items-center gap-1"
                :class="filtroEstado === 'cumple' ? 'bg-emerald-500 text-white shadow-2xs font-extrabold' : 'text-emerald-700 hover:text-emerald-900'"
              >
                Cumplen ({{ totales.rutas_cumplen }})
              </button>
              <button
                type="button"
                @click="filtroEstado = 'deficit'"
                class="px-2 py-1 rounded-[6px] transition-all cursor-pointer flex items-center gap-1"
                :class="filtroEstado === 'deficit' ? 'bg-rose-500 text-white shadow-2xs font-extrabold' : 'text-rose-700 hover:text-rose-900'"
              >
                Déficit ({{ totales.rutas_deficit }})
              </button>
            </div>
          </div>

          <!-- Buscador y Ordenador -->
          <div class="flex items-center gap-2 text-xs">
            <!-- Input de búsqueda -->
            <div class="relative">
              <input
                v-model="busqueda"
                type="text"
                placeholder="Buscar ruta o preventista..."
                class="w-36 sm:w-48 pl-7 pr-2 py-1 rounded-lg text-[11px] bg-white border border-black/[0.1] focus:outline-none focus:border-[#0071E3] transition-colors"
              />
              <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </div>

            <!-- Selector de Orden -->
            <select
              v-model="criterioOrden"
              class="px-2 py-1 rounded-lg text-[11px] font-semibold bg-white border border-black/[0.1] text-[#1D1D1F] focus:outline-none cursor-pointer"
            >
              <option value="alcance_desc">% Alcance (Mayor)</option>
              <option value="alcance_asc">% Alcance (Menor)</option>
              <option value="venta_desc">Venta Hoy (Mayor)</option>
              <option value="meta_desc">Meta Día (Mayor)</option>
              <option value="diferencia_desc">Diferencia Bs (+ a -)</option>
              <option value="ruta_asc">Ruta (A - Z)</option>
            </select>
          </div>
        </div>

        <!-- Lista Scrolleable de Bullet Charts -->
        <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-2.5">
          <div v-if="cargando" class="py-16 text-center">
            <span class="w-6 h-6 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin inline-block"></span>
            <p class="text-xs text-[#86868B] mt-2 font-medium">Calculando avance de metas por ruta...</p>
          </div>

          <div v-else-if="rutasFiltradas.length === 0" class="py-16 text-center text-[#86868B] text-xs">
            No se encontraron rutas con los filtros seleccionados.
          </div>

          <template v-else>
            <div
              v-for="r in rutasFiltradas"
              :key="r.ruta"
              class="p-2.5 sm:p-3 rounded-[12px] bg-slate-50/70 hover:bg-slate-100/80 border border-black/[0.05] transition-all shadow-2xs group"
            >
              <!-- Cabecera de la fila de Ruta -->
              <div class="flex items-center justify-between gap-2 flex-wrap text-xs">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="font-extrabold text-[#1D1D1F] text-xs sm:text-[13px] font-mono tracking-tight">
                    {{ r.ruta }}
                  </span>
                  <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-black/[0.06] text-[#6E6E73]">
                    {{ r.canal }}
                  </span>
                  <span class="text-[#6E6E73] text-[11px] font-medium truncate max-w-[180px] sm:max-w-[240px]">
                    {{ r.vendedor }}
                  </span>
                </div>

                <!-- Estatus y Valores Monetarios -->
                <div class="flex items-center gap-2 sm:gap-3 text-right">
                  <!-- Puntos Unilever Badge -->
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-extrabold font-mono"
                    :class="r.cumple ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300'"
                  >
                    {{ r.cumple ? 'CUMPLE (10 pts)' : 'DÉFICIT (0 pts)' }}
                  </span>

                  <!-- Venta Hoy vs Meta Día -->
                  <div class="text-[11px]">
                    <span class="text-[#6E6E73]">Venta:</span>
                    <strong class="font-mono text-[#1D1D1F] ml-1">Bs {{ formatMoney(r.venta_hoy) }}</strong>
                    <span class="text-[#86868B] mx-1">/</span>
                    <span class="text-[#6E6E73]">Meta:</span>
                    <span class="font-mono text-[#1D1D1F] ml-1">Bs {{ formatMoney(r.cuota_diaria) }}</span>
                  </div>

                  <!-- % de Alcance -->
                  <div
                    class="min-w-[48px] text-right font-extrabold font-mono text-xs sm:text-[13px]"
                    :class="r.alcance_pct >= 100 ? 'text-emerald-600' : 'text-rose-600'"
                  >
                    {{ r.alcance_pct }}%
                  </div>

                  <!-- Botón Ver Desglose -->
                  <button
                    type="button"
                    @click="abrirDetalleRuta(r)"
                    class="px-2 py-1 rounded-[6px] text-[10px] font-bold bg-white hover:bg-[#0071E3] hover:text-white border border-black/[0.1] text-[#1D1D1F] transition-all cursor-pointer shadow-2xs"
                    title="Ver pedidos y cálculo dinámico de cuota"
                  >
                    Detalle
                  </button>
                </div>
              </div>

              <!-- BULLET CHART: Barra de Progreso y Marcador de Meta -->
              <div class="mt-2 relative">
                <!-- Contenedor del Bullet Chart con marcas de referencia -->
                <div class="h-4 sm:h-4.5 w-full bg-[#E5E5EA] rounded-full relative overflow-hidden flex items-center shadow-inner">
                  
                  <!-- Zona de referencia tenue de 0 a 100% -->
                  <div class="absolute inset-y-0 left-0 w-full bg-slate-200/50"></div>

                  <!-- Barra de Preventa Lograda -->
                  <div
                    class="h-full transition-all duration-500 rounded-full relative"
                    :class="obtenerColorBarra(r.alcance_pct)"
                    :style="{ width: `${calcularAnchoBarra(r.alcance_pct)}%` }"
                  ></div>

                  <!-- Marcador de Meta 100% (Línea Vertical Blanca con Sombra) -->
                  <div
                    class="absolute inset-y-0 w-1 bg-black z-10 shadow-xs"
                    style="left: calc(100% * 0.75);"
                    title="Meta Diaria (100% de Cuota Exigida)"
                  ></div>
                </div>

                <!-- Etiquetas de Escala bajo el Bullet Chart -->
                <div class="flex items-center justify-between text-[9px] font-mono text-[#86868B] mt-0.5 px-1">
                  <span>0%</span>
                  <span class="text-rose-600 font-bold">50%</span>
                  <span class="font-extrabold text-[#1D1D1F] flex items-center gap-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-black"></span> Meta (100%)
                  </span>
                  <span class="text-emerald-700 font-bold">133%+</span>
                </div>
              </div>

              <!-- Pie de Tarjeta: Métricas secundarias de la ruta -->
              <div class="mt-1.5 pt-1.5 border-t border-black/[0.04] flex items-center justify-between text-[10px] text-[#6E6E73] flex-wrap gap-2">
                <div class="flex items-center gap-3">
                  <span>Pedidos: <strong class="font-mono text-[#1D1D1F]">{{ r.pedidos_count }}</strong></span>
                  <span>Ticket Promedio: <strong class="font-mono text-[#1D1D1F]">Bs {{ formatMoney(r.ticket_promedio) }}</strong></span>
                  <span>SKUs: <strong class="font-mono text-[#1D1D1F]">{{ r.skus_count }}</strong></span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[9px] text-[#86868B]">Diferencia:</span>
                  <span
                    class="font-mono font-bold"
                    :class="r.diferencia >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                  >
                    {{ r.diferencia >= 0 ? '+' : '' }}Bs {{ formatMoney(r.diferencia) }}
                  </span>
                  <span class="text-black/[0.15]">|</span>
                  <span class="text-[9px] text-[#86868B]">Brecha Mes:</span>
                  <span class="font-mono font-semibold text-[#1D1D1F]">
                    Bs {{ formatMoney(r.brecha_mes) }}
                  </span>
                </div>
              </div>

            </div>
          </template>
        </div>

      </section>

      <!-- SECCIÓN 2: PANEL ANALÍTICO FINANCIERO (DERECHA) -->
      <aside class="w-full lg:w-[380px] xl:w-[420px] flex flex-col min-h-0 gap-2.5 sm:gap-3 shrink-0 overflow-y-auto custom-scroll">
        
        <!-- BLOQUE 1: MIX DE CATEGORÍAS (PRODUCT MIX) -->
        <div class="p-3 bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs space-y-2.5">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-extrabold text-[#1D1D1F] uppercase tracking-tight">
              Mix de Ventas por Categoría
            </h4>
            <span class="text-[10px] text-[#86868B] font-mono">
              {{ categorias.length }} Categorías
            </span>
          </div>

          <!-- Barra Visual Segmentada Proporcional -->
          <div class="h-3 w-full rounded-full bg-slate-100 overflow-hidden flex shadow-inner">
            <div
              v-for="(cat, idx) in categorias"
              :key="cat.categoria"
              :style="{ width: `${cat.porcentaje}%`, backgroundColor: paletaCategorias[idx % paletaCategorias.length] }"
              :title="`${cat.categoria}: Bs ${formatMoney(cat.monto)} (${cat.porcentaje}%)`"
              class="transition-all duration-300"
            ></div>
          </div>

          <!-- Lista Detallada de Categorías -->
          <div class="space-y-1.5 pt-1">
            <div
              v-for="(cat, idx) in categorias"
              :key="cat.categoria"
              class="flex items-center justify-between text-[11px] p-1.5 rounded-lg bg-slate-50/60 hover:bg-slate-100/70 border border-black/[0.03]"
            >
              <div class="flex items-center gap-1.5 min-w-0">
                <span
                  class="w-2.5 h-2.5 rounded-full shrink-0"
                  :style="{ backgroundColor: paletaCategorias[idx % paletaCategorias.length] }"
                ></span>
                <span class="font-bold text-[#1D1D1F] truncate text-[10.5px]">
                  {{ cat.categoria }}
                </span>
              </div>
              <div class="flex items-center gap-2 font-mono shrink-0">
                <span class="text-[#6E6E73] text-[10px]">{{ cat.porcentaje }}%</span>
                <strong class="text-[#1D1D1F] text-xs">Bs {{ formatMoney(cat.monto) }}</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- BLOQUE 2: DISTRIBUCIÓN DE TICKETS (CALIDAD DE VENTA) -->
        <div class="p-3 bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs space-y-2.5">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-extrabold text-[#1D1D1F] uppercase tracking-tight">
              Distribución por Ticket de Compra
            </h4>
            <span class="text-[10px] text-[#86868B] font-mono">
              {{ totales.pedidos_total }} Pedidos
            </span>
          </div>

          <!-- Barras Comparativas de Rangos de Ticket -->
          <div class="space-y-2">
            <div
              v-for="ticket in distribucionTickets"
              :key="ticket.rango"
              class="space-y-0.5 text-xs"
            >
              <div class="flex items-center justify-between text-[10px]">
                <span class="font-bold text-[#1D1D1F] flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: ticket.color }"></span>
                  {{ ticket.etiqueta }}
                </span>
                <span class="font-mono text-[#6E6E73]">
                  <strong>{{ ticket.pedidos }}</strong> pedidos ({{ ticket.porcentaje }}%) · Bs {{ formatMoney(ticket.monto) }}
                </span>
              </div>
              <!-- Barra -->
              <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300"
                  :style="{ width: `${ticket.porcentaje}%`, backgroundColor: ticket.color }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- BLOQUE 3: TOP SKUS MÁS VENDIDOS -->
        <div class="p-3 bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs space-y-2">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-extrabold text-[#1D1D1F] uppercase tracking-tight">
              Top SKUs en el Día
            </h4>
            <span class="text-[10px] text-[#86868B] font-mono">
              Ranking Venta
            </span>
          </div>

          <div class="space-y-1.5">
            <div
              v-for="(prod, pIdx) in topProductos"
              :key="prod.codigo || pIdx"
              class="flex items-center justify-between text-xs p-1.5 rounded-lg bg-slate-50/60 border border-black/[0.03]"
            >
              <div class="min-w-0 pr-2">
                <div class="font-semibold text-[#1D1D1F] text-[10.5px] truncate" :title="prod.producto">
                  {{ prod.producto }}
                </div>
                <div class="text-[9px] text-[#86868B] font-mono">
                  SKU: {{ prod.codigo }} · {{ prod.cantidad }} u.
                </div>
              </div>
              <div class="text-right shrink-0">
                <span class="font-mono font-bold text-[#0071E3] text-xs">
                  Bs {{ formatMoney(prod.monto) }}
                </span>
              </div>
            </div>
          </div>
        </div>

      </aside>

    </main>

    <!-- MODAL DE DETALLE DE RUTA Y BALANCE DE CUOTA -->
    <div
      v-if="modalDetalleVisible && rutaSeleccionada"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/40 backdrop-blur-sm animate-fade-in"
      @click.self="cerrarModalDetalle"
    >
      <div class="bg-white rounded-[20px] shadow-2xl border border-black/[0.1] w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-scale-up">
        
        <!-- Cabecera del Modal -->
        <div class="p-3.5 sm:p-4 border-b border-black/[0.06] bg-slate-50/80 flex items-center justify-between gap-3 shrink-0">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-mono font-black text-sm sm:text-base text-[#1D1D1F]">
                {{ rutaSeleccionada.ruta }}
              </span>
              <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-black/[0.06] text-[#6E6E73]">
                {{ rutaSeleccionada.canal }}
              </span>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-extrabold font-mono"
                :class="rutaSeleccionada.cumple ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
              >
                {{ rutaSeleccionada.cumple ? 'CUMPLE (10 pts)' : 'DÉFICIT (0 pts)' }}
              </span>
            </div>
            <p class="text-xs text-[#6E6E73] font-medium mt-0.5">
              Preventista: <strong class="text-[#1D1D1F]">{{ rutaSeleccionada.vendedor }}</strong> · Fecha: <strong class="font-mono text-[#1D1D1F]">{{ fecha }}</strong>
            </p>
          </div>

          <button
            type="button"
            @click="cerrarModalDetalle"
            class="w-7 h-7 rounded-full bg-black/[0.05] hover:bg-black/[0.1] flex items-center justify-center text-[#6E6E73] hover:text-[#1D1D1F] transition-colors cursor-pointer"
          >
            ✕
          </button>
        </div>

        <!-- Contenido del Modal -->
        <div class="p-3.5 sm:p-4 flex-1 overflow-y-auto custom-scroll space-y-3.5">
          
          <!-- Balance Dinámico de Cuota Mensual -->
          <div class="apple-glass-card rounded-[14px] p-3 bg-slate-50/80 border border-black/[0.06]">
            <div class="text-[10px] font-bold uppercase tracking-wider text-[#86868B] mb-2">
              Balance Dinámico de Cuota Mensual (Unilever)
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-center text-xs">
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <div class="text-[9px] text-[#86868B] uppercase font-bold">Meta Mes</div>
                <div class="font-mono font-bold text-[#1D1D1F]">Bs {{ formatMoney(rutaSeleccionada.meta_mes) }}</div>
              </div>
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <div class="text-[9px] text-[#86868B] uppercase font-bold">Fact. Previo</div>
                <div class="font-mono font-bold text-[#1D1D1F]">Bs {{ formatMoney(rutaSeleccionada.facturado_previo) }}</div>
              </div>
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <div class="text-[9px] text-[#86868B] uppercase font-bold">Brecha Mes</div>
                <div class="font-mono font-bold text-rose-600">Bs {{ formatMoney(rutaSeleccionada.brecha_mes) }}</div>
              </div>
              <div class="p-2 rounded-lg bg-white border border-black/[0.04]">
                <div class="text-[9px] text-[#86868B] uppercase font-bold">Días Restantes</div>
                <div class="font-mono font-bold text-[#1D1D1F]">{{ diasHabilesRestantes }} d. (div: {{ divisorPreventa }})</div>
              </div>
              <div class="p-2 rounded-lg bg-blue-50 border border-blue-200">
                <div class="text-[9px] text-[#0071E3] uppercase font-bold">Cuota Exigida Hoy</div>
                <div class="font-mono font-bold text-[#0071E3]">Bs {{ formatMoney(rutaSeleccionada.cuota_diaria) }}</div>
              </div>
              <div class="p-2 rounded-lg bg-emerald-50 border border-emerald-200">
                <div class="text-[9px] text-emerald-800 uppercase font-bold">Venta Lograda</div>
                <div class="font-mono font-bold text-emerald-700">Bs {{ formatMoney(rutaSeleccionada.venta_hoy) }}</div>
              </div>
            </div>
          </div>

          <!-- Selector de Modo: Clientes vs Productos -->
          <div class="flex items-center justify-between flex-wrap gap-2 pt-1 border-t border-black/[0.06]">
            <div class="flex items-center gap-1.5 p-0.5 bg-black/[0.04] rounded-lg text-xs">
              <button
                type="button"
                @click="modoVistaModal = 'clientes'"
                class="px-3 py-1 rounded-[6px] font-bold transition-all cursor-pointer"
                :class="modoVistaModal === 'clientes' ? 'bg-white text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Por Clientes Compradores ({{ detalleCargado?.total_clientes || 0 }})
              </button>
              <button
                type="button"
                @click="modoVistaModal = 'productos'"
                class="px-3 py-1 rounded-[6px] font-bold transition-all cursor-pointer"
                :class="modoVistaModal === 'productos' ? 'bg-white text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Por Productos SKUs ({{ detalleCargado?.total_productos || 0 }})
              </button>
            </div>

            <span v-if="cargandoModal" class="text-xs text-[#86868B] flex items-center gap-1.5">
              <span class="w-3.5 h-3.5 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></span>
              Cargando pedidos...
            </span>
          </div>

          <!-- TABLA POR CLIENTE -->
          <div v-if="modoVistaModal === 'clientes'" class="border border-black/[0.06] rounded-[12px] overflow-hidden">
            <div v-if="!detalleCargado || (detalleCargado.clientes || []).length === 0" class="p-8 text-center text-xs text-[#86868B]">
              No se registraron pedidos para esta ruta en el día.
            </div>
            <table v-else class="w-full text-left text-xs">
              <thead class="bg-[#F8F8FA] border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73]">
                <tr>
                  <th class="px-3 py-2 w-10 text-center">#</th>
                  <th class="px-3 py-2 w-24">Código</th>
                  <th class="px-3 py-2">Cliente / Negocio</th>
                  <th class="px-3 py-2 text-center">Líneas</th>
                  <th class="px-3 py-2 text-right">Monto (Bs)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.04]">
                <tr v-for="(c, cIdx) in (detalleCargado.clientes || [])" :key="c.cliente_id || cIdx" class="hover:bg-slate-50">
                  <td class="px-3 py-1.5 text-center font-mono text-[#86868B] text-[10px]">{{ cIdx + 1 }}</td>
                  <td class="px-3 py-1.5 font-mono text-[#6E6E73] text-[10px]">{{ c.cliente_id }}</td>
                  <td class="px-3 py-1.5 font-semibold text-[#1D1D1F]">{{ c.cliente }}</td>
                  <td class="px-3 py-1.5 text-center font-mono">
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-[#0071E3]/10 text-[#0071E3]">
                      {{ (c.items || []).length }} SKU
                    </span>
                  </td>
                  <td class="px-3 py-1.5 text-right font-mono font-bold text-[#1D1D1F]">
                    Bs {{ formatMoney(c.total_bs) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TABLA POR PRODUCTO -->
          <div v-else class="border border-black/[0.06] rounded-[12px] overflow-hidden">
            <div v-if="!detalleCargado || (detalleCargado.productos || []).length === 0" class="p-8 text-center text-xs text-[#86868B]">
              No se registraron productos vendidos para esta ruta en el día.
            </div>
            <table v-else class="w-full text-left text-xs">
              <thead class="bg-[#F8F8FA] border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73]">
                <tr>
                  <th class="px-3 py-2 w-10 text-center">#</th>
                  <th class="px-3 py-2 w-28">Código SKU</th>
                  <th class="px-3 py-2">Producto</th>
                  <th class="px-3 py-2 text-center">Categoría</th>
                  <th class="px-3 py-2 text-right">Cantidad</th>
                  <th class="px-3 py-2 text-right">Total (Bs)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.04]">
                <tr v-for="(p, pIdx) in (detalleCargado.productos || [])" :key="p.codigo_producto || pIdx" class="hover:bg-slate-50">
                  <td class="px-3 py-1.5 text-center font-mono text-[#86868B] text-[10px]">{{ pIdx + 1 }}</td>
                  <td class="px-3 py-1.5 font-mono text-[#6E6E73] text-[10px]">{{ p.codigo_producto || '—' }}</td>
                  <td class="px-3 py-1.5 font-semibold text-[#1D1D1F]">{{ p.producto }}</td>
                  <td class="px-3 py-1.5 text-center text-[10px] text-[#6E6E73]">{{ p.categoria || 'GENERAL' }}</td>
                  <td class="px-3 py-1.5 text-right font-mono font-bold text-[#1D1D1F]">{{ p.cantidad_total }}</td>
                  <td class="px-3 py-1.5 text-right font-mono font-bold text-[#0071E3]">
                    Bs {{ formatMoney(p.total_bs) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>

        <!-- Pie del Modal -->
        <div class="p-3 bg-slate-50/80 border-t border-black/[0.06] flex items-center justify-between text-xs">
          <span class="text-[#86868B]">
            Datos calculados contra fact_metas y fact_ventas
          </span>
          <button
            type="button"
            @click="cerrarModalDetalle"
            class="px-4 py-1.5 rounded-lg text-xs font-bold bg-[#0071E3] text-white hover:bg-[#0077ED] transition-colors cursor-pointer shadow-xs"
          >
            Cerrar
          </button>
        </div>

      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  fecha: {
    type: String,
    required: true,
  },
  canales: {
    type: Array,
    default: () => [],
  },
  rutas: {
    type: Array,
    default: () => [],
  },
})

// Estados principales
const cargando = ref(false)
const mes = ref('')
const diasHabilesMes = ref(25)
const diasOperados = ref(1)
const diasHabilesRestantes = ref(24)
const divisorPreventa = ref(22)

const totales = ref({
  rutas_evaluadas: 0,
  rutas_cumplen: 0,
  rutas_deficit: 0,
  cumplimiento_pct: 0.0,
  puntos_unilever: 0,
  puntos_unilever_max: 0,
  puntos_pct: 0.0,
  meta_diaria_total: 0.0,
  venta_total: 0.0,
  diferencia_total: 0.0,
  alcance_global_pct: 0.0,
  pedidos_total: 0,
  compradores_total: 0,
  skus_vendidos_total: 0,
  ticket_promedio: 0.0,
})

const rutas = ref([])
const categorias = ref([])
const distribucionTickets = ref([])
const topProductos = ref([])

// Filtros y orden local
const filtroEstado = ref('todos') // 'todos' | 'cumple' | 'deficit'
const busqueda = ref('')
const criterioOrden = ref('alcance_desc')

// Modal de Detalle
const modalDetalleVisible = ref(false)
const rutaSeleccionada = ref(null)
const modoVistaModal = ref('clientes')
const cargandoModal = ref(false)
const detalleCargado = ref(null)

// Paleta de colores para categorías
const paletaCategorias = [
  '#0071E3', // Azul
  '#34C759', // Verde
  '#FF9500', // Naranja
  '#AF52DE', // Morado
  '#FF2D55', // Rosa
  '#5856D6', // Índigo
  '#00C7BE', // Teal
  '#FF3B30', // Rojo
  '#FFD60A', // Amarillo
]

// Rutas Filtradas y Ordenadas
const rutasFiltradas = computed(() => {
  let list = [...rutas.value]

  // Filtro estado
  if (filtroEstado.value === 'cumple') {
    list = list.filter(r => r.cumple)
  } else if (filtroEstado.value === 'deficit') {
    list = list.filter(r => !r.cumple)
  }

  // Filtro búsqueda
  const term = busqueda.value.trim().toLowerCase()
  if (term !== '') {
    list = list.filter(r =>
      r.ruta.toLowerCase().includes(term) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(term)) ||
      (r.canal && r.canal.toLowerCase().includes(term))
    )
  }

  // Orden
  list.sort((a, b) => {
    switch (criterioOrden.value) {
      case 'alcance_desc':
        return b.alcance_pct - a.alcance_pct
      case 'alcance_asc':
        return a.alcance_pct - b.alcance_pct
      case 'venta_desc':
        return b.venta_hoy - a.venta_hoy
      case 'meta_desc':
        return b.cuota_diaria - a.cuota_diaria
      case 'diferencia_desc':
        return b.diferencia - a.diferencia
      case 'ruta_asc':
        return a.ruta.localeCompare(b.ruta, undefined, { numeric: true })
      default:
        return b.alcance_pct - a.alcance_pct
    }
  })

  return list
})

// Cargar datos del KPI 3
async function cargarDatos() {
  cargando.value = true
  try {
    const res = await axios.post('/supervisor/preventas/pizarra-visual/objetivo-data', {
      fecha: props.fecha,
      canales: props.canales,
      rutas: props.rutas,
    })

    const d = res.data
    mes.value = d.mes || ''
    diasHabilesMes.value = d.dias_habiles_mes || 25
    diasOperados.value = d.dias_operados || 1
    diasHabilesRestantes.value = d.dias_habiles_restantes || 24
    divisorPreventa.value = d.divisor_preventa || 22

    totales.value = d.totales || {}
    rutas.value = d.rutas || []
    categorias.value = d.categorias || []
    distribucionTickets.value = d.distribucion_tickets || []
    topProductos.value = d.top_productos || []
  } catch (err) {
    console.error('Error cargando datos de Objetivo Diario (KPI 3):', err)
  } finally {
    cargando.value = false
  }
}

// Helpers visuales para el Bullet Chart
function formatMoney(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) return '0.00'
  return Number(amount).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

function calcularAnchoBarra(alcancePct) {
  // La línea de la meta está en 75% del ancho del contenedor
  // Si alcance = 100%, ancho = 75%
  // Si alcance = 50%, ancho = 37.5%
  // Si alcance = 133.3%, ancho = 100%
  if (!alcancePct || alcancePct <= 0) return 0
  const ratio = (alcancePct / 100) * 75
  return Math.min(100, Math.max(2, ratio))
}

function obtenerColorBarra(alcancePct) {
  if (alcancePct >= 100) return 'bg-[#34C759]' // Verde esmeralda Unilever (Cumple)
  if (alcancePct >= 70) return 'bg-[#0071E3]'  // Azul Apple (Cerca de la meta)
  if (alcancePct >= 40) return 'bg-[#FF9500]'  // Ámbar Apple (Progreso medio)
  return 'bg-[#FF3B30]'                        // Rojo Apple (Déficit crítico)
}

// Modal de Detalle
async function abrirDetalleRuta(r) {
  rutaSeleccionada.value = r
  modalDetalleVisible.value = true
  cargandoModal.value = true
  detalleCargado.value = null

  try {
    const res = await axios.post('/supervisor/preventas/cumplimiento/objetivo/detalle', {
      fecha: props.fecha,
      ruta: r.ruta,
      mes: props.fecha ? props.fecha.slice(0, 7) : '',
    })
    detalleCargado.value = res.data
  } catch (e) {
    console.error('Error cargando detalle de ruta:', e)
  } finally {
    cargandoModal.value = false
  }
}

function cerrarModalDetalle() {
  modalDetalleVisible.value = false
  rutaSeleccionada.value = null
  detalleCargado.value = null
}

// Reaccionar a cambios de props
watch(
  () => [props.fecha, props.canales, props.rutas],
  () => {
    cargarDatos()
  },
  { deep: true }
)

onMounted(() => {
  cargarDatos()
})
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.12);
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.22);
}
.shadow-2xs {
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes scaleUp {
  from { transform: scale(0.97); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
.animate-fade-in {
  animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.animate-scale-up {
  animation: scaleUp 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
