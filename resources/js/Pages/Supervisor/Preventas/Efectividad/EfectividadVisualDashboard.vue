<template>
  <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans">
    
    <!-- CUERPO PRINCIPAL: LISTA DE THRESHOLD BARS (IZQUIERDA) Y EMBUDO DE CONVERSIÓN (DERECHA) -->
    <main class="flex-1 flex flex-col lg:flex-row min-h-0 gap-2.5 sm:gap-3 p-2.5 sm:p-3 overflow-hidden">
      
      <!-- SECCIÓN 1: THRESHOLD BULLET BARS (EVALUACIÓN DE EFECTIVIDAD CON META EN 80%) -->
      <section class="flex-1 flex flex-col min-h-0 bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs overflow-hidden">
        
        <!-- Barra de Controles y Filtros Locales -->
        <div class="p-2.5 sm:p-3 border-b border-black/[0.06] bg-slate-50/70 backdrop-blur-sm flex flex-wrap items-center justify-between gap-2 shrink-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-xs sm:text-sm font-extrabold text-[#1D1D1F] uppercase tracking-tight flex items-center gap-1.5">
              <span>Efectividad Comercial de Ruta · Meta 80% (30 Pts)</span>
              <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-slate-200 text-[#1D1D1F]">
                {{ rutasFiltradas.length }}
              </span>
            </h3>

            <!-- Filtro de Estado: Todas / Cumplen / En Déficit -->
            <div class="inline-flex p-0.5 rounded-[9px] bg-black/[0.05] border border-black/[0.04] text-[10px] font-bold">
              <button
                type="button"
                @click="filtroEstado = 'todas'"
                class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer"
                :class="filtroEstado === 'todas' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Todas ({{ rutas.length }})
              </button>
              <button
                type="button"
                @click="filtroEstado = 'cumplen'"
                class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer flex items-center gap-1"
                :class="filtroEstado === 'cumplen' ? 'bg-[#34C759] text-white shadow-2xs' : 'text-emerald-700 hover:bg-white/50'"
              >
                <span>Cumplen &ge; 80% ({{ totales.rutas_cumplen }})</span>
              </button>
              <button
                type="button"
                @click="filtroEstado = 'deficit'"
                class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer flex items-center gap-1"
                :class="filtroEstado === 'deficit' ? 'bg-[#FF3B30] text-white shadow-2xs' : 'text-rose-700 hover:bg-white/50'"
              >
                <span>En Déficit &lt; 80% ({{ totales.rutas_deficit }})</span>
              </button>
            </div>
          </div>

          <!-- Selector de Orden y Buscador -->
          <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1 text-[11px]">
              <span class="text-[#86868B] text-[10px] font-bold uppercase">Ordenar:</span>
              <select
                v-model="ordenarPor"
                class="h-7 px-2 bg-white border border-black/[0.08] rounded-lg text-xs font-semibold text-[#1D1D1F] focus:outline-none focus:border-[#0071E3]"
              >
                <option value="deficit_primero">En Déficit primero (Acción)</option>
                <option value="faltantes_menor">Más cercanas a la meta (Menos faltantes)</option>
                <option value="efectividad_desc">Mayor Efectividad (%)</option>
                <option value="efectividad_asc">Menor Efectividad (%)</option>
                <option value="cartera_desc">Mayor Cartera Plan</option>
                <option value="monto_desc">Mayor Venta (Bs)</option>
              </select>
            </div>

            <!-- Buscador Rápido -->
            <div class="relative w-36 sm:w-44">
              <input
                type="text"
                v-model="busqueda"
                placeholder="Buscar ruta o preventista..."
                class="w-full h-7 pl-2.5 pr-6 bg-white border border-black/[0.08] rounded-lg text-xs text-[#1D1D1F] placeholder:text-gray-400 focus:outline-none focus:border-[#0071E3]"
              />
              <span
                v-if="busqueda"
                @click="busqueda = ''"
                class="absolute right-2 top-1.5 text-gray-400 hover:text-gray-600 cursor-pointer text-xs"
              >✕</span>
            </div>
          </div>
        </div>

        <!-- Lista de Tarjetas Threshold Bars -->
        <div class="flex-1 overflow-y-auto custom-scroll p-2.5 sm:p-3 space-y-2">
          
          <!-- Loader de rutas -->
          <div v-if="cargando" class="flex flex-col items-center justify-center p-12 text-center space-y-2">
            <div class="w-7 h-7 border-3 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs font-semibold text-[#6E6E73]">Calculando efectividad de cartera y umbrales al 80%...</p>
          </div>

          <!-- Estado Vacío -->
          <div v-else-if="rutasFiltradas.length === 0" class="flex flex-col items-center justify-center p-12 text-center">
            <p class="text-sm font-bold text-[#1D1D1F]">No se encontraron rutas con los filtros aplicados</p>
            <p class="text-xs text-[#86868B] mt-1">Prueba seleccionando "Todas" o cambiando la fecha.</p>
          </div>

          <!-- Card de Ruta con Threshold Bar al 80% -->
          <div
            v-else
            v-for="r in rutasFiltradas"
            :key="r.ruta"
            class="p-2.5 sm:p-3 rounded-[13px] border transition-all duration-150 hover:shadow-xs group cursor-pointer"
            :class="r.cumple_umbral ? 'bg-white border-black/[0.06] hover:border-emerald-300' : 'bg-rose-50/20 border-rose-200/70 hover:border-rose-300'"
            @click="abrirDetalleRuta(r)"
          >
            <!-- Cabecera de la Ruta -->
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <div class="flex items-center gap-2 min-w-0">
                <span
                  class="w-3 h-3 rounded-full shrink-0 shadow-2xs"
                  :style="{ backgroundColor: r.color || '#0071E3' }"
                ></span>
                
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs sm:text-sm font-extrabold text-[#1D1D1F] tracking-tight">
                      {{ r.ruta }}
                    </span>
                    <span class="text-[9.5px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-[#6E6E73] uppercase font-mono">
                      {{ r.canal }}
                    </span>
                  </div>
                  <p class="text-[10px] text-[#6E6E73] truncate font-medium">
                    {{ r.vendedor }}
                  </p>
                </div>
              </div>

              <!-- Badges de Estado y Puntos Unilever -->
              <div class="flex items-center gap-1.5 shrink-0">
                <!-- Badge Brecha Táctica -->
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-extrabold font-mono flex items-center gap-1"
                  :class="r.cumple_umbral
                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                    : 'bg-rose-50 text-rose-800 border border-rose-200'"
                >
                  <span v-if="r.cumple_umbral">✓ 30 Pts Asegurados</span>
                  <span v-else>⚠️ Faltan {{ r.clientes_faltantes_80 }} clientes</span>
                </span>

                <!-- Porcentaje de Efectividad Destacado -->
                <div class="text-right">
                  <span
                    class="text-base sm:text-lg font-black font-mono tracking-tight"
                    :class="r.cumple_umbral ? 'text-[#1E6B37]' : 'text-rose-600'"
                  >
                    {{ r.efectividad_pct }}%
                  </span>
                  <span class="text-[9px] block text-[#86868B] font-mono leading-none">
                    Meta: 80%
                  </span>
                </div>
              </div>
            </div>

            <!-- THRESHOLD BULLET BAR (BARRA CON META EN 80%) -->
            <div class="relative w-full py-1">
              <!-- Contenedor de la Barra -->
              <div class="relative h-4 w-full rounded-full bg-slate-100 overflow-hidden shadow-inner border border-black/[0.04]">
                
                <!-- Cuadrícula de referencia 20, 40, 60, 80% -->
                <div class="absolute inset-0 flex justify-between pointer-events-none opacity-25">
                  <span class="w-px h-full bg-black" style="left: 20%;"></span>
                  <span class="w-px h-full bg-black" style="left: 40%;"></span>
                  <span class="w-px h-full bg-black" style="left: 60%;"></span>
                  <span class="w-px h-full bg-black" style="left: 80%;"></span>
                </div>

                <!-- Barra de Progreso Rellena -->
                <div
                  class="h-full transition-all duration-500 rounded-full flex items-center justify-end pr-1 text-[8px] font-mono font-bold text-white shadow-2xs"
                  :class="r.efectividad_pct >= 80 ? 'bg-[#34C759]' : (r.efectividad_pct >= 65 ? 'bg-[#FF9500]' : 'bg-[#FF3B30]')"
                  :style="{ width: `${Math.min(100, r.efectividad_pct)}%` }"
                >
                  <span v-if="r.efectividad_pct >= 25">{{ r.efectividad_pct }}%</span>
                </div>

                <!-- Línea de Meta 80% fija dentro de la barra -->
                <div
                  class="absolute top-0 bottom-0 w-[2px] bg-black/80 z-10 shadow-xs"
                  style="left: 80%;"
                  title="Meta Unilever: 80%"
                ></div>
              </div>

              <!-- Marcador de Meta 80% Exterior -->
              <div
                class="absolute -top-1 w-px flex flex-col items-center pointer-events-none z-20"
                style="left: 80%;"
              >
                <div class="w-1.5 h-1.5 rotate-45 bg-[#1D1D1F]"></div>
              </div>
            </div>

            <!-- Métricas al Pie de la Ruta -->
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-[10px] text-[#6E6E73] border-t border-black/[0.04] mt-1">
              <!-- Compradores vs Cartera -->
              <div class="flex items-center gap-1.5 font-medium">
                <span>Compradores:</span>
                <strong class="font-mono text-[#1D1D1F]">{{ r.total_compradores }}</strong>
                <span class="text-gray-400">/</span>
                <span class="font-mono text-[#86868B]">{{ r.cartera_plan }} plan</span>
                <span class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded bg-slate-100 text-[#1D1D1F]">
                  Meta: {{ r.clientes_meta_80 }} cli
                </span>
              </div>

              <!-- Desglose de Calidad de Compra -->
              <div class="flex items-center gap-2 font-mono text-[9.5px]">
                <span class="text-emerald-700 font-bold flex items-center gap-1" title="Clientes con compra ≥ 70 Bs">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                  {{ r.compradores_sobre_70 }} &ge; 70 Bs
                </span>
                <span v-if="r.compradores_bajo_70 > 0" class="text-amber-600 font-bold flex items-center gap-1" title="Clientes con compra < 70 Bs">
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                  {{ r.compradores_bajo_70 }} &lt; 70 Bs
                </span>
                <span v-if="r.visitados_sin_compra > 0" class="text-[#0071E3] font-semibold flex items-center gap-1" title="Visitados pero sin compra">
                  <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
                  {{ r.visitados_sin_compra }} sin venta
                </span>
              </div>

              <!-- Venta y Acción -->
              <div class="flex items-center gap-2 ml-auto">
                <span class="font-mono font-bold text-[#1D1D1F]">
                  Bs {{ formatMoney(r.monto_total) }}
                </span>
                <button
                  type="button"
                  @click.stop="abrirDetalleRuta(r)"
                  class="px-2 py-0.5 rounded-md text-[9.5px] font-bold text-[#0071E3] bg-sky-50 hover:bg-sky-100 transition-colors cursor-pointer"
                >
                  Ver Clientes &rarr;
                </button>
              </div>
            </div>

          </div>

        </div>

      </section>

      <!-- SECCIÓN 2: PANEL ANALÍTICO - EMBUDO DE CONVERSIÓN & CALIDAD COMERCIAL (DERECHA) -->
      <aside class="w-full lg:w-[380px] xl:w-[410px] flex flex-col gap-2.5 sm:gap-3 shrink-0 overflow-y-auto custom-scroll">
        
        <!-- CARD 1: EMBUDO DE CONVERSIÓN COMERCIAL (FUNNEL) -->
        <div class="bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs p-3 space-y-3">
          <div class="flex items-center justify-between border-b border-black/[0.06] pb-2">
            <div>
              <h4 class="text-xs font-extrabold text-[#1D1D1F] uppercase tracking-tight">
                Embudo de Conversión Comercial
              </h4>
              <p class="text-[10px] text-[#86868B]">Del Plan a la Compra Calificada</p>
            </div>
            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">
              Meta: &ge; 80%
            </span>
          </div>

          <!-- Etapas del Funnel -->
          <div class="space-y-2 pt-1">
            <div
              v-for="(etapa, idx) in funnel.etapas"
              :key="etapa.etapa"
              class="space-y-1"
            >
              <div class="flex items-baseline justify-between text-[11px]">
                <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: etapa.color }"></span>
                  <span class="font-bold text-[#1D1D1F]">{{ etapa.nombre }}</span>
                </div>
                <div class="flex items-baseline gap-1.5 font-mono">
                  <strong class="text-xs text-[#1D1D1F]">{{ etapa.cantidad }}</strong>
                  <span class="text-[10px] text-[#86868B] font-semibold">({{ etapa.pct }}%)</span>
                </div>
              </div>

              <!-- Barra Proporcional del Nivel -->
              <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden shadow-inner">
                <div
                  class="h-full rounded-full transition-all duration-500 shadow-2xs"
                  :style="{
                    width: `${Math.min(100, etapa.pct)}%`,
                    backgroundColor: etapa.color
                  }"
                ></div>
              </div>
            </div>
          </div>

          <!-- Fugas Comerciales Auditadas -->
          <div class="pt-2 border-t border-black/[0.06] space-y-1.5 text-[10px]">
            <span class="font-bold uppercase tracking-wider text-[#86868B] text-[9.5px] block">
              Pérdidas y Fugas en el Embudo
            </span>

            <div class="grid grid-cols-2 gap-1.5">
              <div class="p-2 rounded-lg bg-rose-50/70 border border-rose-100 flex flex-col justify-between">
                <span class="text-[#86868B] text-[9px] font-semibold">Fuga por No Visita</span>
                <span class="text-xs font-black font-mono text-rose-700">
                  {{ funnel.fugas?.por_no_visita || 0 }} clientes
                </span>
                <span class="text-[8.5px] text-rose-600/80">No alcanzados en ruta</span>
              </div>

              <div class="p-2 rounded-lg bg-amber-50/70 border border-amber-100 flex flex-col justify-between">
                <span class="text-[#86868B] text-[9px] font-semibold">Fuga por Rechazo</span>
                <span class="text-xs font-black font-mono text-amber-700">
                  {{ funnel.fugas?.por_rechazo_visita || 0 }} clientes
                </span>
                <span class="text-[8.5px] text-amber-600/80">Visitados sin preventa</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD 2: DISTRIBUCIÓN DE CALIDAD DE COMPRA -->
        <div class="bg-white rounded-[16px] apple-glass-card border border-black/[0.08] shadow-xs p-3 space-y-2.5">
          <div class="flex items-center justify-between border-b border-black/[0.06] pb-2">
            <div>
              <h4 class="text-xs font-extrabold text-[#1D1D1F] uppercase tracking-tight">
                Calidad de la Cartera Total
              </h4>
              <p class="text-[10px] text-[#86868B]">{{ totales.cartera_plan_total }} clientes evaluados</p>
            </div>
            <span class="font-mono text-[10px] font-bold text-[#1D1D1F]">
              Ticket Prom: Bs {{ formatMoney(totales.ticket_promedio_global) }}
            </span>
          </div>

          <!-- Barra Segmentada Global -->
          <div class="h-3 w-full rounded-full bg-slate-100 overflow-hidden flex shadow-inner">
            <div
              v-for="item in distribucionCalidad"
              :key="item.id"
              :style="{
                width: `${item.pct}%`,
                backgroundColor: item.color
              }"
              :title="`${item.label}: ${item.cantidad} (${item.pct}%)`"
              class="transition-all duration-300"
            ></div>
          </div>

          <!-- Leyenda de Calidad -->
          <div class="space-y-1.5 pt-1 text-[10.5px]">
            <div
              v-for="item in distribucionCalidad"
              :key="item.id"
              class="flex items-center justify-between"
            >
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: item.color }"></span>
                <span class="text-[#6E6E73] font-medium">{{ item.label }}</span>
              </div>
              <div class="flex items-baseline gap-1 font-mono text-xs">
                <strong class="text-[#1D1D1F]">{{ item.cantidad }}</strong>
                <span class="text-[9.5px] text-[#86868B]">({{ item.pct }}%)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD 3: CRITERIO DEL MODELO UNILEVER (30 PUNTOS) -->
        <div class="bg-slate-900 text-white rounded-[16px] p-3 shadow-sm space-y-2 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-extrabold tracking-wider text-emerald-400 uppercase">
              Regla Oficial Unilever
            </span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 text-white font-mono">
              30 Pts Base
            </span>
          </div>
          <p class="text-[11px] text-slate-300 leading-snug">
            La ruta obtiene <strong class="text-white">30 puntos</strong> si alcanza o supera el <strong class="text-emerald-400">80.0% de Efectividad Comercial</strong> de cartera. Si queda en 79.9%, obtiene <strong class="text-rose-400">0 puntos</strong>.
          </p>
          <div class="pt-1.5 border-t border-white/10 flex items-center justify-between text-[10px] text-slate-400 font-mono">
            <span>Rutas Cumpliendo:</span>
            <span class="font-bold text-white">{{ totales.rutas_cumplen }} / {{ rutas.length }}</span>
          </div>
        </div>

      </aside>

    </main>

    <!-- MODAL / DRAWER INTERACTIVO DE INSPECCIÓN DE CLIENTES POR RUTA -->
    <div
      v-if="rutaSeleccionada !== null"
      class="fixed inset-0 z-[600] flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-xs transition-opacity"
      @click.self="rutaSeleccionada = null"
    >
      <div class="bg-white rounded-[20px] shadow-2xl border border-black/[0.08] w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Header del Modal -->
        <div class="p-3.5 sm:p-4 border-b border-black/[0.06] bg-slate-50/80 flex items-center justify-between gap-3 shrink-0">
          <div>
            <div class="flex items-center gap-2">
              <span
                class="w-3 h-3 rounded-full shadow-2xs"
                :style="{ backgroundColor: rutaSeleccionada.color || '#0071E3' }"
              ></span>
              <h3 class="text-sm sm:text-base font-extrabold text-[#1D1D1F] tracking-tight">
                {{ rutaSeleccionada.ruta }} · Detalle de Clientes
              </h3>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono"
                :class="rutaSeleccionada.cumple_umbral ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'"
              >
                {{ rutaSeleccionada.efectividad_pct }}% Efectividad
              </span>
            </div>
            <p class="text-xs text-[#6E6E73] font-medium mt-0.5">
              Preventista: <strong class="text-[#1D1D1F]">{{ rutaSeleccionada.vendedor }}</strong> · {{ rutaSeleccionada.canal }}
            </p>
          </div>

          <button
            type="button"
            @click="rutaSeleccionada = null"
            class="w-7 h-7 rounded-full bg-black/[0.06] hover:bg-black/[0.1] text-[#1D1D1F] flex items-center justify-center font-bold text-xs transition-colors cursor-pointer"
          >
            ✕
          </button>
        </div>

        <!-- Filtros Rápidos de Clientes en el Modal -->
        <div class="p-2.5 sm:p-3 border-b border-black/[0.06] bg-white flex flex-wrap items-center justify-between gap-2 shrink-0 text-xs">
          <div class="inline-flex p-0.5 rounded-[9px] bg-black/[0.05] text-[10.5px] font-bold">
            <button
              type="button"
              @click="filtroClienteModal = 'todos'"
              class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer"
              :class="filtroClienteModal === 'todos' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              Todos ({{ clientesRutaSeleccionada.length }})
            </button>
            <button
              type="button"
              @click="filtroClienteModal = 'sobre_70'"
              class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer text-emerald-700"
              :class="filtroClienteModal === 'sobre_70' ? 'bg-[#34C759] text-white shadow-2xs' : ''"
            >
              &ge; 70 Bs ({{ clientesRutaSeleccionada.filter(c => c.estado === 'sobre_70').length }})
            </button>
            <button
              type="button"
              @click="filtroClienteModal = 'bajo_70'"
              class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer text-amber-600"
              :class="filtroClienteModal === 'bajo_70' ? 'bg-[#FF9500] text-white shadow-2xs' : ''"
            >
              &lt; 70 Bs ({{ clientesRutaSeleccionada.filter(c => c.estado === 'bajo_70').length }})
            </button>
            <button
              type="button"
              @click="filtroClienteModal = 'sin_compra'"
              class="px-2 py-0.5 rounded-[7px] transition-all cursor-pointer text-[#0071E3]"
              :class="filtroClienteModal === 'sin_compra' ? 'bg-[#0071E3] text-white shadow-2xs' : ''"
            >
              Sin Compra ({{ clientesRutaSeleccionada.filter(c => c.estado === 'sin_compra').length }})
            </button>
          </div>

          <!-- Buscador en Modal -->
          <div class="relative w-44">
            <input
              type="text"
              v-model="busquedaClienteModal"
              placeholder="Buscar cliente..."
              class="w-full h-7 px-2.5 bg-[#F5F5F7] border border-black/[0.06] rounded-lg text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3]"
            />
          </div>
        </div>

        <!-- Lista de Clientes -->
        <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-1.5 text-xs">
          <div
            v-for="c in clientesModalFiltrados"
            :key="`${c.cliente_id}-${c.cliente}`"
            class="p-2 rounded-xl border border-black/[0.05] bg-white flex items-center justify-between gap-2 hover:bg-slate-50 transition-colors"
          >
            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <span
                  class="w-2 h-2 rounded-full shrink-0"
                  :class="c.estado === 'sobre_70' ? 'bg-emerald-500' : (c.estado === 'bajo_70' ? 'bg-amber-500' : (c.estado === 'sin_compra' ? 'bg-[#0071E3]' : 'bg-rose-500'))"
                ></span>
                <span class="font-extrabold text-[#1D1D1F] truncate text-xs">{{ c.cliente }}</span>
                <span class="text-[9px] font-mono text-[#86868B]">Cód: {{ c.cliente_id }}</span>
              </div>
              <p class="text-[10px] text-[#6E6E73] truncate mt-0.5">
                {{ c.direccion || '-' }}
              </p>
            </div>

            <!-- Estatus y Monto -->
            <div class="text-right shrink-0">
              <span
                class="px-2 py-0.5 rounded text-[9.5px] font-extrabold font-mono block"
                :class="c.estado === 'sobre_70' ? 'bg-emerald-50 text-emerald-800' : (c.estado === 'bajo_70' ? 'bg-amber-50 text-amber-800' : (c.estado === 'sin_compra' ? 'bg-sky-50 text-sky-800' : 'bg-gray-100 text-gray-700'))"
              >
                {{ c.estado_label }}
              </span>
              <span v-if="c.monto > 0" class="text-xs font-black font-mono text-[#1D1D1F] mt-0.5 block">
                Bs {{ formatMoney(c.monto) }}
              </span>
              <span v-else class="text-[9px] text-gray-400 font-mono mt-0.5 block">
                Bs 0.00
              </span>
            </div>
          </div>
        </div>

        <!-- Footer del Modal -->
        <div class="p-3 border-t border-black/[0.06] bg-slate-50 flex items-center justify-between text-xs text-[#6E6E73] font-medium shrink-0">
          <span>Mostrando {{ clientesModalFiltrados.length }} de {{ clientesRutaSeleccionada.length }} clientes</span>
          <button
            type="button"
            @click="rutaSeleccionada = null"
            class="px-3 py-1 rounded-lg bg-[#1D1D1F] text-white font-bold hover:bg-black transition-colors cursor-pointer text-xs"
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

// Estado de carga y datos
const cargando = ref(false)
const totales = ref({
  cartera_plan_total: 0,
  visitados_total: 0,
  compradores_total: 0,
  compradores_sobre_70_total: 0,
  compradores_bajo_70_total: 0,
  visitados_sin_compra_total: 0,
  pendientes_total: 0,
  monto_total: 0.0,
  ticket_promedio_global: 0.0,
  efectividad_global_pct: 0.0,
  rutas_cumplen: 0,
  rutas_deficit: 0,
  puntos_totales_ganados: 0.0,
  puntos_maximos_posibles: 0.0,
  porcentaje_puntos_obtenidos: 0.0,
  umbral_efectividad_pct: 80.0,
})

const rutas = ref([])
const funnel = ref({
  etapas: [],
  fugas: { por_no_visita: 0, por_rechazo_visita: 0, por_bajo_minimo: 0 },
})
const distribucionCalidad = ref([])
const clientesPorRuta = ref({})

// Filtros Locales de UI
const filtroEstado = ref('todas') // 'todas' | 'cumplen' | 'deficit'
const ordenarPor = ref('deficit_primero')
const busqueda = ref('')

// Modal de Ruta
const rutaSeleccionada = ref(null)
const filtroClienteModal = ref('todos') // 'todos' | 'sobre_70' | 'bajo_70' | 'sin_compra'
const busquedaClienteModal = ref('')

// Lista filtrada y ordenada de rutas
const rutasFiltradas = computed(() => {
  let list = rutas.value || []

  // 1. Filtro por Estado
  if (filtroEstado.value === 'cumplen') {
    list = list.filter(r => r.cumple_umbral)
  } else if (filtroEstado.value === 'deficit') {
    list = list.filter(r => !r.cumple_umbral)
  }

  // 2. Filtro por Búsqueda de Texto
  if (busqueda.value.trim() !== '') {
    const q = busqueda.value.trim().toLowerCase()
    list = list.filter(r =>
      (r.ruta && r.ruta.toLowerCase().includes(q)) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(q)) ||
      (r.canal && r.canal.toLowerCase().includes(q))
    )
  }

  // 3. Ordenamiento
  const copia = [...list]
  switch (ordenarPor.value) {
    case 'deficit_primero':
      return copia.sort((a, b) => a.efectividad_pct - b.efectividad_pct)
    case 'faltantes_menor':
      return copia.sort((a, b) => {
        // Primero las que están en déficit pero con menos clientes faltantes
        if (!a.cumple_umbral && b.cumple_umbral) return -1
        if (a.cumple_umbral && !b.cumple_umbral) return 1
        return a.clientes_faltantes_80 - b.clientes_faltantes_80
      })
    case 'efectividad_desc':
      return copia.sort((a, b) => b.efectividad_pct - a.efectividad_pct)
    case 'efectividad_asc':
      return copia.sort((a, b) => a.efectividad_pct - b.efectividad_pct)
    case 'cartera_desc':
      return copia.sort((a, b) => b.cartera_plan - a.cartera_plan)
    case 'monto_desc':
      return copia.sort((a, b) => b.monto_total - a.monto_total)
    default:
      return copia
  }
})

// Clientes de la ruta seleccionada para el modal
const clientesRutaSeleccionada = computed(() => {
  if (!rutaSeleccionada.value) return []
  return clientesPorRuta.value[rutaSeleccionada.value.ruta] || []
})

const clientesModalFiltrados = computed(() => {
  let list = clientesRutaSeleccionada.value

  if (filtroClienteModal.value !== 'todos') {
    list = list.filter(c => c.estado === filtroClienteModal.value)
  }

  if (busquedaClienteModal.value.trim() !== '') {
    const q = busquedaClienteModal.value.trim().toLowerCase()
    list = list.filter(c =>
      (c.cliente && c.cliente.toLowerCase().includes(q)) ||
      (c.cliente_id && String(c.cliente_id).includes(q)) ||
      (c.direccion && c.direccion.toLowerCase().includes(q))
    )
  }

  return list
})

function abrirDetalleRuta(ruta) {
  rutaSeleccionada.value = ruta
  filtroClienteModal.value = 'todos'
  busquedaClienteModal.value = ''
}

function formatMoney(val) {
  if (val === null || val === undefined || isNaN(val)) return '0.00'
  return Number(val).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Carga de Datos desde el Backend
async function cargarDatos() {
  cargando.value = true
  try {
    const res = await axios.post(route('supervisor.preventas.pizarra-visual.efectividad-data'), {
      fecha: props.fecha,
      canales: props.canales,
      rutas: props.rutas,
    })

    const d = res.data || {}
    totales.value = d.totales || totales.value
    rutas.value = d.rutas || []
    funnel.value = d.funnel_global || funnel.value
    distribucionCalidad.value = d.distribucion_calidad || []
    clientesPorRuta.value = d.clientes_detalle_por_ruta || {}
  } catch (error) {
    console.error('[EfectividadVisualDashboard] Error cargando datos:', error)
  } finally {
    cargando.value = false
  }
}

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
  width: 5px;
  height: 5px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.12);
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.25);
}
</style>
