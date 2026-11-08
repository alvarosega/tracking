<template>
  <SupervisorLayout title="Avance > Cumplimiento MTD">
    <div class="space-y-2.5 font-sans select-none">
      <!-- Subnavegación de Módulo Avance -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- Barra Superior de Filtros Transversales -->
      <AvanceFiltrosBar @consultar="ejecutarConsulta" />

      <!-- Segmented Control de KPIs Oficiales -->
      <div class="apple-glass-card rounded-[14px] p-1.5 flex items-center gap-1 w-full overflow-x-auto custom-scroll select-none">
        <button
          type="button"
          @click="cambiarModo('resumen')"
          class="px-3 py-1.5 rounded-[9px] text-[11px] transition-all whitespace-nowrap cursor-pointer shrink-0 font-bold"
          :class="modoActivo === 'resumen'
            ? 'glass-active-pill font-bold shadow-xs'
            : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white/60 font-semibold'"
        >
          Resumen Multi-KPI
        </button>

        <div class="h-4 w-px bg-black/[0.08] mx-0.5 shrink-0"></div>

        <div
          v-for="kpi in kpisLista"
          :key="kpi.id"
          class="inline-flex items-center gap-0.5 shrink-0"
        >
          <button
            type="button"
            @click="cambiarModo(kpi.id)"
            class="px-2.5 py-1.5 rounded-[9px] text-[11px] transition-all whitespace-nowrap cursor-pointer font-bold"
            :class="modoActivo === kpi.id
              ? 'glass-active-pill font-bold shadow-xs'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white/60 font-semibold'"
          >
            {{ kpi.nombre }}
          </button>
          <button
            type="button"
            @click.stop="abrirModalInfoKpi(kpi)"
            class="text-[10px] text-[#86868B] hover:text-[#0071E3] font-bold px-1 py-0.5 rounded hover:bg-white/60 transition-colors cursor-pointer"
            title="Ver criterio y reglas de este KPI"
          >
            ?
          </button>
        </div>
      </div>

      <!-- SKELETON LOADER -->
      <div v-if="store.cargandoCumplimiento" class="h-[calc(100vh-12rem)] apple-glass-card rounded-[16px] p-4 animate-pulse space-y-2 flex flex-col justify-between">
        <div class="h-8 bg-black/[0.04] rounded-[8px]"></div>
        <div v-for="row in 12" :key="row" class="h-8 bg-black/[0.02] rounded-[8px] flex items-center px-3 gap-3">
          <div class="h-3 bg-black/[0.06] rounded w-6"></div>
          <div class="h-3 bg-black/[0.06] rounded w-32"></div>
          <div class="h-3 bg-black/[0.06] rounded flex-1"></div>
          <div class="h-3 bg-black/[0.06] rounded flex-1"></div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="store.errorCumplimiento" class="p-4 rounded-[12px] bg-red-50/80 backdrop-blur-md text-red-700 border border-red-200/80 text-[12px] font-semibold text-center">
        {{ store.errorCumplimiento }}
      </div>

      <!-- ── VISTA 1: RESUMEN MULTI-KPI ── -->
      <section
        v-else-if="modoActivo === 'resumen' && rutasFiltradas.length > 0"
        class="h-[calc(100vh-12rem)] flex flex-col rounded-[16px] apple-glass-card overflow-hidden select-none border border-white/80 shadow-[0_12px_36px_-6px_rgba(15,23,42,0.06)]"
      >
        <!-- Sub-header de la tabla -->
        <div class="shrink-0 px-4 py-2.5 bg-white/40 backdrop-blur-md border-b border-black/[0.05] flex justify-between items-center gap-3">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
            <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase">
              Resumen Multi-KPI de Cumplimiento
            </span>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-[11px] text-[#6E6E73] font-medium font-mono">
              Orden: <strong class="text-[#0071E3] font-bold">{{ kpisLista.find(k => k.id === sortResumenCol)?.corto || (sortResumenCol === 'ruta' ? 'Ruta' : sortResumenCol) }}</strong>
              <span class="text-[#86868B] ml-1">({{ sortResumenDir === 'desc' ? 'Mayor a menor' : 'Menor a mayor' }})</span>
            </span>
            <span class="text-[11px] text-[#6E6E73] font-medium font-mono">
              Rutas Activas: <strong class="text-[#1D1D1F] font-bold">{{ rutasFiltradas.length }}</strong>
            </span>
          </div>
        </div>

        <!-- Contenedor con SCROLL INTERNO (Vertical para filas + Horizontal para KPIs) -->
        <div class="flex-1 min-h-0 overflow-auto custom-scroll relative">
          <table class="w-full text-left border-separate border-spacing-0 text-[11px]">
            <thead class="text-[#1D1D1F] font-bold text-[10px] uppercase tracking-tight">
              <tr>
                <!-- Columna # congelada -->
                <th class="sticky top-0 left-0 z-40 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] w-10 text-center shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)]">
                  #
                </th>
                <!-- Columna Ruta congelada -->
                <th
                  @click="toggleSortResumen('ruta')"
                  class="sticky top-0 left-10 z-40 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] min-w-[160px] shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08),inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none group/th"
                  title="Ordenar por Ruta"
                >
                  <div class="flex items-center gap-1.5">
                    <span :class="sortResumenCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''">Ruta / Preventista</span>
                    <svg v-if="sortResumenCol === 'ruta'" class="w-3 h-3 text-[#0071E3] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="sortResumenDir === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                    </svg>
                  </div>
                </th>
                <!-- Columnas de KPIs congeladas arriba -->
                <th
                  v-for="kpi in kpisLista"
                  :key="kpi.id"
                  @click="toggleSortResumen(kpi.id)"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-center whitespace-nowrap cursor-pointer hover:bg-black/[0.04] transition-colors shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] select-none group/th"
                  :class="sortResumenCol === kpi.id ? 'bg-blue-50/70' : ''"
                  :title="`Ordenar por ${kpi.nombre}`"
                >
                  <div class="flex items-center justify-center gap-1.5">
                    <span :class="sortResumenCol === kpi.id ? 'text-[#0071E3] font-bold' : ''">{{ kpi.corto }}</span>
                    <svg v-if="sortResumenCol === kpi.id" class="w-3 h-3 text-[#0071E3] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="sortResumenDir === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                    </svg>
                    <button
                      type="button"
                      @click.stop="cambiarModo(kpi.id)"
                      class="p-0.5 rounded hover:bg-black/[0.08] text-[#86868B] hover:text-[#0071E3] transition-colors ml-0.5"
                      :title="`Ver detalle monográfico de ${kpi.nombre}`"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                      </svg>
                    </button>
                  </div>
                </th>
                <!-- Columna Score Promedio -->
                <th
                  @click="toggleSortResumen('score_promedio')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-center whitespace-nowrap cursor-pointer hover:bg-black/[0.04] transition-colors shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] select-none"
                  title="Ordenar por Score Promedio"
                >
                  <div class="flex items-center justify-center gap-1">
                    <span :class="sortResumenCol === 'score_promedio' ? 'text-[#0071E3] font-bold' : ''">Score</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/70">
              <tr
                v-for="(r, idx) in rutasFiltradas"
                :key="r.ruta"
                class="hover:bg-blue-50/40 transition-colors group"
              >
                <td class="py-2 px-3 text-center font-mono font-bold text-[#86868B] text-[10px] sticky left-0 z-20 bg-white group-hover:bg-blue-50/40">
                  {{ idx + 1 }}
                </td>
                <td class="py-2 px-3 font-sans sticky left-10 z-20 bg-white group-hover:bg-blue-50/40 shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]">
                  <div class="font-bold text-[#1D1D1F] hover:text-[#0071E3] cursor-pointer inline-flex items-center gap-1.5" @click="cambiarModo('vol_general')">
                    <span>{{ r.ruta }}</span>
                    <span v-if="r.canal" class="text-[9px] font-mono px-1 py-0.2 rounded bg-black/[0.04] text-[#6E6E73]">{{ r.canal }}</span>
                  </div>
                  <div class="text-[10px] text-[#6E6E73] truncate max-w-[150px]">{{ r.vendedor || 'No Asignado' }}</div>
                </td>
                <td
                  v-for="kpi in kpisLista"
                  :key="kpi.id"
                  class="py-2 px-3 text-center whitespace-nowrap"
                  :class="sortResumenCol === kpi.id ? 'bg-blue-50/20' : ''"
                >
                  <div class="flex flex-col items-center gap-0.5">
                    <template v-if="kpi.id === 'cob_core'">
                      <div class="flex items-center justify-center font-mono">
                        <span class="font-bold text-[12px] text-[#1D1D1F]">
                          {{ r.kpis?.[kpi.id]?.logrado ?? 0 }} Cli
                        </span>
                      </div>
                      <div class="text-[9px] text-[#86868B] font-mono">
                        Histórico 3M
                      </div>
                    </template>
                    <template v-else>
                      <div class="flex items-center justify-center font-mono">
                        <span
                          class="font-bold text-[12px]"
                          :class="colorKpiResumen(kpi.id, r.kpis?.[kpi.id])"
                          :title="r.kpis?.[kpi.id]?.pct_ritmo !== undefined ? `Ritmo esperado: ${formatPct(r.kpis?.[kpi.id]?.pct_ritmo)}` : undefined"
                        >
                          {{ formatPct(r.kpis?.[kpi.id]?.pct) }}
                        </span>
                      </div>
                      <div class="text-[9px] text-[#86868B] font-mono">
                        {{ formatLogroResumen(r.kpis?.[kpi.id]?.logrado, kpi.tipo) }} / {{ formatLogroResumen(r.kpis?.[kpi.id]?.meta, kpi.tipo) }}
                      </div>
                    </template>
                  </div>
                </td>
                <!-- Score Promedio -->
                <td class="py-2 px-3 text-center font-mono font-bold text-[12px]" :class="colorPct(r.score_promedio)">
                  {{ formatPct(r.score_promedio) }}
                </td>
              </tr>
            </tbody>

            <!-- Totales Fijos Abajo -->
            <tfoot v-if="totalesResumen" class="bg-[#F8F8FA] text-[#1D1D1F] font-bold sticky bottom-0 z-30 font-mono tabular-nums shadow-[inset_0_1px_0_rgba(0,0,0,0.08)]">
              <tr>
                <td class="py-2.5 px-3 text-center text-[10px] text-[#6E6E73] sticky bottom-0 left-0 z-40 bg-[#F8F8FA] font-sans">TOTAL</td>
                <td class="py-2.5 px-3 text-[10px] text-[#6E6E73] sticky bottom-0 left-10 z-40 bg-[#F8F8FA] font-sans shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]">
                  CONSOLIDADO ({{ rutasFiltradas.length }})
                </td>
                <td
                  v-for="kpi in kpisLista"
                  :key="kpi.id"
                  class="py-2.5 px-3 text-center sticky bottom-0 z-30 bg-[#F8F8FA]"
                >
                  <div class="flex flex-col items-center gap-0.5">
                    <template v-if="kpi.id === 'cob_core'">
                      <span class="font-bold text-[12px] text-[#1D1D1F]">
                        {{ totalesResumen[kpi.id]?.logrado ?? 0 }} Cli
                      </span>
                      <span class="text-[9px] text-[#6E6E73]">
                        Sin Meta
                      </span>
                    </template>
                    <template v-else>
                      <span
                        class="font-bold text-[12px]"
                        :class="colorKpiResumen(kpi.id, totalesResumen[kpi.id])"
                        :title="totalesResumen[kpi.id]?.pct_ritmo !== undefined ? `Ritmo consolidado: ${formatPct(totalesResumen[kpi.id]?.pct_ritmo)}` : undefined"
                      >
                        {{ formatPct(totalesResumen[kpi.id]?.pct) }}
                      </span>
                      <span class="text-[9px] text-[#6E6E73]">
                        {{ formatLogroResumen(totalesResumen[kpi.id]?.logrado, kpi.tipo) }} / {{ formatLogroResumen(totalesResumen[kpi.id]?.meta, kpi.tipo) }}
                      </span>
                    </template>
                  </div>
                </td>
                <td class="py-2.5 px-3 text-center sticky bottom-0 z-30 bg-[#F8F8FA] text-[12px]" :class="colorPct(promedioGlobalScore)">
                  {{ formatPct(promedioGlobalScore) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- ── VISTA 2: DETALLE MONOGRÁFICO POR KPI (1 al 6) ── -->
      <section
        v-else-if="modoActivo !== 'resumen' && rutasFiltradas.length > 0"
        class="h-[calc(100vh-12rem)] flex flex-col rounded-[16px] apple-glass-card overflow-hidden select-none border border-white/80 shadow-[0_12px_36px_-6px_rgba(15,23,42,0.06)]"
      >
        <div class="shrink-0 px-4 py-2.5 bg-white/40 backdrop-blur-md border-b border-black/[0.05] flex justify-between items-center gap-3">
          <div class="flex items-center gap-2.5 flex-wrap">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase">
                {{ kpiActualObj?.nombre }} · Detalle Monográfico
              </span>
            </div>

            <!-- Selector para elegir la cantidad mínima acumulada en el histórico -->
            <div
              v-if="modoActivo === 'cob_core'"
              class="ml-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[8px] bg-purple-50/80 border border-purple-200 text-purple-900 font-sans shadow-2xs select-none"
              title="Cantidad mínima de compra: exigir un mínimo de unidades acumuladas en la ventana histórica para incluir el producto en el sugerido"
            >
              <svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
              <span class="text-[11px] font-bold">Cant. Mínima:</span>
              <select
                v-model.number="store.cantidadMinima"
                @change="onCambioCantidadMinima"
                class="text-[11px] font-bold text-purple-900 bg-white rounded-[6px] border border-purple-300/80 px-2 py-0.5 cursor-pointer focus:outline-none focus:ring-1 focus:ring-purple-500"
              >
                <option :value="1">≥ 1 unidad</option>
                <option :value="2">≥ 2 unidades</option>
                <option :value="3">≥ 3 unidades</option>
                <option :value="4">≥ 4 unidades</option>
                <option :value="5">≥ 5 unidades</option>
                <option :value="6">≥ 6 unidades</option>
                <option :value="10">≥ 10 unidades</option>
                <option :value="12">≥ 12 unidades</option>
                <option :value="24">≥ 24 unidades</option>
              </select>
            </div>

            <!-- Selector para elegir los meses a evaluar (hasta 8 meses atrás) -->
            <div
              v-if="modoActivo === 'cob_core'"
              class="ml-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[8px] bg-blue-50/80 border border-blue-200 text-blue-900 font-sans shadow-2xs select-none"
              title="Seleccionar la cantidad de meses cerrados a evaluar en el histórico (hasta 8 meses atrás)"
            >
              <svg class="w-3.5 h-3.5 text-[#0071E3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span class="text-[11px] font-bold">Ventana:</span>
              <select
                v-model.number="store.mesesHistorico"
                @change="onCambioMesesHistorico"
                class="text-[11px] font-bold text-[#0071E3] bg-white rounded-[6px] border border-blue-300/80 px-2 py-0.5 cursor-pointer focus:outline-none focus:ring-1 focus:ring-[#0071E3]"
              >
                <option :value="1">1 mes (LM)</option>
                <option :value="2">2 meses (L1M, LM)</option>
                <option :value="3">3 meses (L2M, L1M, LM)</option>
                <option :value="4">4 meses (L3M a LM)</option>
                <option :value="5">5 meses (L4M a LM)</option>
                <option :value="6">6 meses (L5M a LM)</option>
                <option :value="7">7 meses (L6M a LM)</option>
                <option :value="8">8 meses (L7M a LM)</option>
              </select>
            </div>

            <!-- Selector para elegir la frecuencia mínima de compra en el histórico -->
            <div
              v-if="modoActivo === 'cob_core'"
              class="ml-1 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[8px] bg-amber-50/80 border border-amber-200 text-amber-900 font-sans shadow-2xs select-none"
              title="Frecuencia de compra: exigir un número mínimo de compras/facturas en la ventana histórica para incluir el producto en el sugerido"
            >
              <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-[11px] font-bold">Frecuencia:</span>
              <select
                v-model.number="store.frecuenciaMinima"
                @change="onCambioFrecuenciaMinima"
                class="text-[11px] font-bold text-amber-900 bg-white rounded-[6px] border border-amber-300/80 px-2 py-0.5 cursor-pointer focus:outline-none focus:ring-1 focus:ring-amber-500"
              >
                <option :value="1">≥ 1 compra</option>
                <option :value="2">≥ 2 compras</option>
                <option :value="3">≥ 3 compras</option>
                <option :value="4">≥ 4 compras</option>
                <option :value="5">≥ 5 compras</option>
                <option :value="6">≥ 6 compras</option>
                <option :value="8">≥ 8 compras</option>
                <option :value="10">≥ 10 compras</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-[11px] text-[#6E6E73] font-medium font-mono">
              Orden: <strong class="text-[#0071E3] font-bold">{{ sortLabelsKpi[sortKpiCol] || sortKpiCol }}</strong>
              <span class="text-[#86868B] ml-1">({{ sortKpiDir === 'desc' ? 'Mayor a menor' : 'Menor a mayor' }})</span>
            </span>
            <span class="text-[11px] text-[#6E6E73] font-medium font-mono">
              Rutas: <strong class="text-[#1D1D1F] font-bold">{{ rutasFiltradas.length }}</strong>
            </span>
          </div>
        </div>

        <div class="flex-1 min-h-0 overflow-auto custom-scroll relative">
          <table class="w-full text-left border-separate border-spacing-0 text-[11px]">
            <thead class="text-[#1D1D1F] font-bold text-[10px] uppercase tracking-tight">
              <tr>
                <th class="sticky top-0 left-0 z-40 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] w-10 text-center shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)]">#</th>
                <th
                  @click="toggleSortKpi('ruta')"
                  class="sticky top-0 left-10 z-40 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] min-w-[170px] shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08),inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none group/th"
                  title="Ordenar por Ruta"
                >
                  <div class="flex items-center gap-1.5">
                    <span :class="sortKpiCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''">Ruta / Preventista</span>
                    <svg v-if="sortKpiCol === 'ruta'" class="w-3 h-3 text-[#0071E3] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="sortKpiDir === 'desc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                    </svg>
                  </div>
                </th>
                <template v-if="modoActivo === 'cob_core'">
                  <th
                    @click="toggleSortKpi('cartera')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'cartera' ? 'bg-blue-50/70' : ''"
                  >
                    Cartera Oficial
                  </th>
                  <th
                    v-for="(mesObj, mIdx) in mesesCerradosGlobal"
                    :key="mesObj.ym"
                    @click="toggleSortKpi(`mes_${mesObj.ym}`)"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === `mes_${mesObj.ym}` ? 'bg-blue-50/70' : ''"
                  >
                    <div>{{ labelMesHistorico(mIdx, mesesCerradosGlobal.length) }}</div>
                    <div class="text-[8px] text-[#86868B] lowercase font-normal">{{ mesObj.corto }}</div>
                  </th>
                  <th
                    @click="toggleSortKpi('total_3m_bs')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'total_3m_bs' ? 'bg-blue-50/70' : ''"
                  >
                    Total {{ mesesCerradosGlobal.length }}M (Bs)
                  </th>
                  <th
                    @click="toggleSortKpi('total_sugerido')"
                    class="sticky top-0 z-30 bg-purple-50/70 text-purple-900 py-2.5 px-3 border-b border-purple-100 text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-purple-100/70 transition-colors select-none"
                    :class="sortKpiCol === 'total_sugerido' ? 'bg-purple-100' : ''"
                    title="Compra sugerida consolidada de la ruta calculada por hábitos de clientes"
                  >
                    Compra Sugerida
                  </th>
                  <th
                    @click="toggleSortKpi('objetivo_mes')"
                    class="sticky top-0 z-30 bg-purple-50/50 text-[#6B21A8] py-2.5 px-3 border-b border-purple-100 text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-purple-100/50 transition-colors select-none"
                    :class="sortKpiCol === 'objetivo_mes' ? 'bg-purple-100' : ''"
                    title="Cuota de objetivo mensual asignada a la ruta"
                  >
                    Objetivo
                  </th>
                  <th
                    @click="toggleSortKpi('venta_mtd')"
                    class="sticky top-0 z-30 bg-emerald-50/50 text-emerald-900 py-2.5 px-3 border-b border-emerald-100 text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-emerald-100/50 transition-colors select-none"
                    :class="sortKpiCol === 'venta_mtd' ? 'bg-emerald-100' : ''"
                    title="Venta acumulada del mes en curso de los clientes de la ruta"
                  >
                    Venta MTD
                  </th>
                  <th
                    @click="toggleSortKpi('pct')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'pct' ? 'bg-blue-50/70' : ''"
                    title="Porcentaje de clientes de la cartera que ya compraron este mes"
                  >
                    % Cobertura
                  </th>
                </template>
                <template v-else>
                  <th
                    @click="toggleSortKpi('meta')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'meta' ? 'bg-blue-50/70' : ''"
                  >
                    Meta Mes
                  </th>
                  <th
                    @click="toggleSortKpi('logrado')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'logrado' ? 'bg-blue-50/70' : ''"
                  >
                    Logrado
                  </th>
                  <th
                    @click="toggleSortKpi('pct')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'pct' ? 'bg-blue-50/70' : ''"
                  >
                    % Avance
                  </th>
                  <th
                    @click="toggleSortKpi('brecha')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    :class="sortKpiCol === 'brecha' ? 'bg-blue-50/70' : ''"
                  >
                    Brecha
                  </th>
                  <!-- Columnas exclusivas de Cobertura: Venta Apertura vs Venta Repetición -->
                  <th
                    v-if="esCobertura"
                    @click="toggleSortKpi('venta_apertura')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  >
                    Venta Apertura
                  </th>
                  <th
                    v-if="esCobertura"
                    @click="toggleSortKpi('venta_repeticion')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  >
                    Venta Repetición
                  </th>
                  <th
                    @click="toggleSortKpi('cuota_dia')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  >
                    Cuota Diaria
                  </th>
                  <th
                    @click="toggleSortKpi('proyeccion_cierre')"
                    class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  >
                    Proyección
                  </th>
                </template>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/70 font-mono tabular-nums">
              <template v-for="(r, idx) in rutasFiltradas" :key="r.ruta">
                <tr class="hover:bg-blue-50/40 transition-colors group">
                  <td class="py-2 px-3 text-center font-bold text-[#86868B] text-[10px] sticky left-0 z-20 bg-white group-hover:bg-blue-50/40">
                    {{ idx + 1 }}
                  </td>
                  <td class="py-2 px-3 font-sans sticky left-10 z-20 bg-white group-hover:bg-blue-50/40 shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]">
                    <div class="flex items-center gap-1.5">
                      <button
                        type="button"
                        @click="toggleDetalleRuta(r.ruta)"
                        class="w-5 h-5 rounded hover:bg-black/[0.05] inline-flex items-center justify-center text-[#86868B] hover:text-[#0071E3] transition-transform cursor-pointer shrink-0"
                        :class="rutaExpandida === r.ruta ? 'rotate-90 text-[#0071E3] font-bold' : ''"
                        title="Ver desglose de clientes y días"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                      </button>
                      <div class="min-w-0">
                        <div
                          class="font-bold text-[#1D1D1F] hover:text-[#0071E3] cursor-pointer inline-flex items-center gap-1"
                          @click="toggleDetalleRuta(r.ruta)"
                        >
                          <span>{{ r.ruta }}</span>
                        </div>
                        <div class="text-[10px] text-[#6E6E73] truncate max-w-[140px]">{{ r.vendedor || 'No Asignado' }}</div>
                      </div>
                    </div>
                  </td>
                  <template v-if="modoActivo === 'cob_core'">
                    <td class="py-2 px-3 text-right font-medium text-[#1D1D1F]">{{ r.cartera ?? 0 }} Cli</td>
                    <td
                      v-for="mesObj in mesesCerradosGlobal"
                      :key="mesObj.ym"
                      class="py-2 px-3 text-right font-mono text-[#1D1D1F]"
                    >
                      {{ (r.meses_bs?.[mesObj.ym] || 0) > 0 ? `Bs ${formatNum(r.meses_bs[mesObj.ym], 2)}` : '-' }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono font-bold text-[#1D1D1F]">Bs {{ formatNum(r.total_3m_bs, 2) }}</td>
                    <td class="py-2 px-3 text-right font-mono font-bold text-purple-900 bg-purple-50/20">
                      Bs {{ formatNum(r.total_sugerido, 2) }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono font-bold text-[#6B21A8] bg-purple-50/10">
                      {{ (r.objetivo_mes || 0) > 0 ? `Bs ${formatNum(r.objetivo_mes, 2)}` : '-' }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700 bg-emerald-50/10">
                      Bs {{ formatNum(r.venta_mtd ?? r.logrado, 2) }}
                    </td>
                    <td class="py-2 px-3 text-right font-bold text-[12px]" :class="colorPct(r.pct)">
                      {{ formatPct(r.pct) }}
                    </td>
                  </template>
                  <template v-else>
                    <td class="py-2 px-3 text-right font-medium text-[#1D1D1F]">{{ formatValorKpi(r.meta) }}</td>
                    <td class="py-2 px-3 text-right font-bold text-[#1D1D1F]">{{ formatValorKpi(r.logrado) }}</td>
                    <td
                      class="py-2 px-3 text-right font-bold text-[12px]"
                      :class="colorPct(r.pct_proyeccion ?? r.pct)"
                      :title="r.pct_proyeccion !== undefined ? `Proyección al cierre: ${formatPct(r.pct_proyeccion)}` : undefined"
                    >
                      {{ formatPct(r.pct) }}
                    </td>
                    <td class="py-2 px-3 text-right font-medium" :class="r.brecha > 0 ? 'text-red-600' : 'text-emerald-600'">
                      {{ formatValorKpi(r.brecha) }}
                    </td>
                    <!-- Cobertura: Venta Apertura vs Venta Repetición -->
                    <td v-if="esCobertura" class="py-2 px-3 text-right">
                      <div class="font-bold text-[#1D1D1F]">Bs {{ formatNum(r.venta_apertura ?? r.venta_cobertura, 1) }}</div>
                      <div class="text-[9px] text-[#6E6E73] font-sans">{{ formatPct(r.pct_apertura) }}</div>
                    </td>
                    <td v-if="esCobertura" class="py-2 px-3 text-right">
                      <div class="font-bold" :class="(r.venta_repeticion || 0) > 0 ? 'text-[#0071E3]' : 'text-[#86868B]'">
                        Bs {{ formatNum(r.venta_repeticion, 1) }}
                      </div>
                      <div class="text-[9px] text-[#86868B] font-sans">
                        <span v-if="(r.clientes_repeticion || 0) > 0" class="text-[#0071E3] font-semibold">{{ r.clientes_repeticion }} cli ({{ formatPct(r.pct_repeticion) }})</span>
                        <span v-else>0 cli (0%)</span>
                      </div>
                    </td>
                    <td class="py-2 px-3 text-right font-bold text-[#1D1D1F]">{{ formatValorKpi(r.cuota_dia ?? r.cuota_diaria_req) }}</td>
                    <td class="py-2 px-3 text-right">
                      <div class="font-bold text-[#1D1D1F]">{{ formatValorKpi(r.proyeccion_cierre) }}</div>
                      <div class="text-[9px]" :class="colorPct(r.pct_proyeccion)">{{ formatPct(r.pct_proyeccion) }}</div>
                    </td>
                  </template>
                </tr>

                <!-- FILA EXPANDIBLE: Desglose de la Ruta (Venta por Día vs Por Clientes) -->
                <tr v-if="rutaExpandida === r.ruta" class="bg-black/[0.02]">
                  <td :colspan="modoActivo === 'cob_core' ? (8 + mesesCerradosGlobal.length) : (esCobertura ? 10 : 8)" class="p-3.5 border-y border-black/[0.08] shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)] font-sans">
                    <div class="bg-white/95 backdrop-blur-xl rounded-[12px] border border-black/[0.08] p-3 shadow-md space-y-2.5">
                      <!-- Cabecera del Desglose de Ruta con Switch Principal -->
                      <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-black/[0.06]">
                        <div class="flex items-center gap-2">
                          <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3]"></span>
                          <div>
                            <div class="text-[12px] font-bold text-[#1D1D1F] flex items-center gap-1.5">
                              <span>Desglose de Ruta: {{ r.ruta }}</span>
                              <span class="text-[10px] text-[#86868B] font-normal">({{ r.vendedor || 'Preventista no asignado' }})</span>
                            </div>
                            <div class="text-[10px] text-[#6E6E73]">
                              <template v-if="modoDetalleRuta === 'dias'">
                                <strong>{{ diasRuta.length }} días</strong> con ventas registradas · Total acumulado: <strong class="text-[#0071E3]">Bs {{ formatNum(totalVentaDiasRuta, 2) }}</strong>
                              </template>
                              <template v-else-if="modoActivo === 'cob_core'">
                                {{ carteraClientes.length }} clientes registrados · 
                                <strong class="text-blue-600 font-bold">{{ clientesConHistorico.length }} con histórico 3M</strong> ·
                                <strong class="text-emerald-600 font-bold">{{ clientesConCompra.length }} compradores MTD</strong> ·
                                Acumulado 3M: <strong class="text-[#0071E3] font-bold">Bs {{ formatNum(totales3mRuta.total_3m, 2) }}</strong> ·
                                Objetivo Mes: <strong class="text-purple-700 font-bold">Bs {{ formatNum(totales3mRuta.objetivo_mes, 2) }}</strong>
                              </template>
                              <template v-else>
                                {{ carteraClientes.length }} clientes registrados · 
                                <strong class="text-emerald-600 font-bold">{{ clientesConCompra.length }} compradores únicos</strong>
                              </template>
                            </div>
                          </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                          <!-- Switch Principal: Por Día (Acumulado) vs Por Clientes -->
                          <div class="inline-flex apple-control-pill p-0.5 rounded-[8px] text-[10px]">
                            <button
                              type="button"
                              @click="modoDetalleRuta = 'dias'"
                              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
                              :class="modoDetalleRuta === 'dias' ? 'glass-active-pill text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                              title="Ver venta por día y acumulado progresivo"
                            >
                              <span>Venta por Día (Acumulado)</span>
                            </button>
                            <button
                              type="button"
                              @click="modoDetalleRuta = 'clientes'"
                              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
                              :class="modoDetalleRuta === 'clientes' ? 'glass-active-pill text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                              title="Ver listado de clientes individuales"
                            >
                              <span>Por Clientes ({{ carteraClientes.length }})</span>
                            </button>
                          </div>

                          <!-- Controles secundarios para clientes -->
                          <template v-if="modoDetalleRuta === 'clientes'">
                            <div class="inline-flex apple-control-pill p-0.5 rounded-[8px] text-[10px]">
                              <button
                                v-if="modoActivo === 'cob_core'"
                                type="button"
                                @click="filtroClientesTab = 'con_historico'"
                                class="px-2 py-0.5 rounded-[6px] transition-all cursor-pointer font-bold"
                                :class="filtroClientesTab === 'con_historico' ? 'glass-active-pill text-[#0071E3]' : 'text-[#6E6E73]'"
                              >
                                Con Histórico ({{ clientesConHistorico.length }})
                              </button>
                              <button
                                type="button"
                                @click="filtroClientesTab = 'compradores'"
                                class="px-2 py-0.5 rounded-[6px] transition-all cursor-pointer font-bold"
                                :class="filtroClientesTab === 'compradores' ? 'glass-active-pill text-[#0071E3]' : 'text-[#6E6E73]'"
                              >
                                {{ modoActivo === 'cob_core' ? 'Compraron MTD' : 'Compradores' }} ({{ clientesConCompra.length }})
                              </button>
                              <button
                                type="button"
                                @click="filtroClientesTab = 'todos'"
                                class="px-2 py-0.5 rounded-[6px] transition-all cursor-pointer font-bold"
                                :class="filtroClientesTab === 'todos' ? 'glass-active-pill text-[#0071E3]' : 'text-[#6E6E73]'"
                              >
                                Todos ({{ carteraClientes.length }})
                              </button>
                            </div>

                            <input
                              v-model="busquedaClienteTexto"
                              type="text"
                              placeholder="Buscar cliente..."
                              class="text-[10px] bg-black/[0.03] border border-black/[0.06] rounded-[7px] px-2 py-1 w-32 focus:outline-none font-medium"
                            />
                          </template>
                        </div>
                      </div>

                      <!-- Estado de Carga -->
                      <div v-if="cargandoClientes" class="py-6 text-center text-[11px] text-[#86868B] flex items-center justify-center gap-2 font-medium">
                        <div class="w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                        Cargando desglose de la ruta {{ r.ruta }}...
                      </div>

                      <!-- TABLA A: VENTAS POR DÍA Y ACUMULADO PROGRESIVO -->
                      <div
                        v-else-if="modoDetalleRuta === 'dias' && diasRuta.length > 0"
                        class="overflow-y-auto custom-scroll border border-black/[0.06] rounded-[8px] max-h-[450px]"
                      >
                        <table class="w-full text-left border-collapse text-[10px]">
                          <thead class="bg-[#F8F8FA] text-[#6E6E73] font-bold uppercase sticky top-0 z-10 text-[9px] tracking-wider border-b border-black/[0.06] select-none">
                            <tr>
                              <th class="py-1.5 px-2.5 w-12 text-center">#</th>
                              <th
                                class="py-1.5 px-2.5 w-28 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortDiasCol === 'fecha' ? 'text-[#0071E3]' : ''"
                                @click="ordenarDias('fecha')"
                                title="Ordenar por Fecha"
                              >
                                <div class="flex items-center gap-1">
                                  <span>Fecha</span>
                                  <span v-if="sortDiasCol === 'fecha'" class="text-[10px]">{{ sortDiasDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                              <th
                                class="py-1.5 px-2.5 w-28 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortDiasCol === 'dia_semana' ? 'text-[#0071E3]' : ''"
                                @click="ordenarDias('dia_semana')"
                                title="Ordenar por Día Semana"
                              >
                                <div class="flex items-center gap-1">
                                  <span>Día Semana</span>
                                  <span v-if="sortDiasCol === 'dia_semana'" class="text-[10px]">{{ sortDiasDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                              <th
                                class="py-1.5 px-2.5 text-center w-28 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortDiasCol === 'total_clientes' ? 'text-[#0071E3]' : ''"
                                @click="ordenarDias('total_clientes')"
                                title="Ordenar por Compradores"
                              >
                                <div class="flex items-center justify-center gap-1">
                                  <span>Compradores</span>
                                  <span v-if="sortDiasCol === 'total_clientes'" class="text-[10px]">{{ sortDiasDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                              <th
                                class="py-1.5 px-2.5 text-right w-32 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortDiasCol === 'venta_total' ? 'text-[#0071E3]' : ''"
                                @click="ordenarDias('venta_total')"
                                title="Ordenar por Venta Día"
                              >
                                <div class="flex items-center justify-end gap-1">
                                  <span>Venta Día (Bs)</span>
                                  <span v-if="sortDiasCol === 'venta_total'" class="text-[10px]">{{ sortDiasDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                              <th
                                class="py-1.5 px-2.5 text-right w-36 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortDiasCol === 'venta_acumulada' ? 'text-[#0071E3]' : ''"
                                @click="ordenarDias('venta_acumulada')"
                                title="Ordenar por Acumulado"
                              >
                                <div class="flex items-center justify-end gap-1">
                                  <span>Acumulado (Bs)</span>
                                  <span v-if="sortDiasCol === 'venta_acumulada'" class="text-[10px]">{{ sortDiasDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.03] bg-white font-mono tabular-nums">
                            <template v-for="(dia, didx) in diasRutaOrdenados" :key="dia.fecha">
                              <tr class="hover:bg-blue-50/30 transition-colors">
                                <td class="py-1.5 px-2.5 text-center text-[#86868B] whitespace-nowrap">
                                  <button
                                    type="button"
                                    @click="toggleDiaInline(dia.fecha)"
                                    class="inline-flex items-center justify-center w-4 h-4 mr-1 rounded hover:bg-black/10 transition-transform duration-200 cursor-pointer text-[#6E6E73] hover:text-[#0071E3]"
                                    :class="diaSeleccionadoInline === dia.fecha ? 'rotate-90 text-[#0071E3]' : ''"
                                    title="Desglosar clientes del día"
                                  >
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                  </button>
                                  <span>{{ didx + 1 }}</span>
                                </td>
                                <td class="py-1.5 px-2.5 font-bold text-[#1D1D1F]">{{ dia.fecha }}</td>
                                <td class="py-1.5 px-2.5 font-sans text-[#6E6E73]">{{ dia.dia_semana }}</td>
                                <td class="py-1.5 px-2.5 text-center font-bold text-[#1D1D1F]">{{ dia.total_clientes }} cli</td>
                                <td class="py-1.5 px-2.5 text-right font-bold text-[#1D1D1F]">Bs {{ formatNum(dia.venta_total, 2) }}</td>
                                <td class="py-1.5 px-2.5 text-right font-bold text-[#0071E3]">Bs {{ formatNum(dia.venta_acumulada, 2) }}</td>
                              </tr>
                              <!-- Detalle inline de clientes del día en acordeón -->
                              <tr v-if="diaSeleccionadoInline === dia.fecha" class="bg-blue-50/20">
                                <td colspan="6" class="p-2.5 border-y border-black/[0.05]">
                                  <div class="apple-glass-card rounded-[10px] p-2.5 space-y-1.5 bg-white/90 border border-black/[0.05]">
                                    <div class="flex items-center justify-between text-[10px] pb-1 border-b border-black/[0.04]">
                                      <span class="font-bold text-[#1D1D1F]">
                                        Compradores del {{ dia.fecha }} ({{ dia.clientes?.length || 0 }} clientes)
                                      </span>
                                      <span class="font-bold text-[#0071E3]">
                                        Total Día: Bs {{ formatNum(dia.venta_total, 2) }}
                                      </span>
                                    </div>
                                    <div class="space-y-1 max-h-[220px] overflow-y-auto custom-scroll pr-1">
                                      <div
                                        v-for="cDia in dia.clientes"
                                        :key="cDia.cliente_id"
                                        class="flex items-center justify-between text-[9px] px-2.5 py-1.5 rounded-[6px] bg-white border border-black/[0.04] hover:border-black/[0.1] transition-all"
                                      >
                                        <div class="flex items-center gap-2">
                                          <span class="font-mono font-bold text-[#0071E3]">ID {{ cDia.cliente_id }}</span>
                                          <span class="font-sans font-bold text-[#1D1D1F]">{{ cDia.cliente }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                          <span class="text-[#6E6E73] font-sans">Preventista: {{ cDia.vendedor }}</span>
                                          <span class="font-bold font-mono text-[#0071E3]">Bs {{ formatNum(cDia.monto_bs, 2) }}</span>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </td>
                              </tr>
                            </template>
                          </tbody>
                        </table>
                      </div>

                      <!-- TABLA B: DESGLOSE POR CLIENTES INDIVIDUALES -->
                      <div
                        v-else-if="modoDetalleRuta === 'clientes' && clientesRutaFiltrados.length > 0"
                        class="overflow-y-auto custom-scroll border border-black/[0.06] rounded-[8px] max-h-[450px]"
                      >
                        <table class="w-full text-left border-collapse text-[10px]">
                          <thead class="bg-[#F8F8FA] text-[#6E6E73] font-bold uppercase sticky top-0 z-10 text-[9px] tracking-wider border-b border-black/[0.06] select-none">
                            <tr>
                              <th class="py-1.5 px-2.5 w-12 text-center">#</th>
                              <th
                                class="py-1.5 px-2.5 w-20 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortClientesCol === 'cliente_id' ? 'text-[#0071E3]' : ''"
                                @click="ordenarClientes('cliente_id')"
                                title="Ordenar por ID"
                              >
                                <div class="flex items-center gap-1">
                                  <span>ID</span>
                                  <span v-if="sortClientesCol === 'cliente_id'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                              <th
                                class="py-1.5 px-2.5 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortClientesCol === 'cliente' ? 'text-[#0071E3]' : ''"
                                @click="ordenarClientes('cliente')"
                                title="Ordenar por Nombre de Cliente"
                              >
                                <div class="flex items-center gap-1">
                                  <span>Cliente</span>
                                  <span v-if="sortClientesCol === 'cliente'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>
                              <th
                                class="py-1.5 px-2.5 w-20 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                :class="sortClientesCol === 'dia_visita' ? 'text-[#0071E3]' : ''"
                                @click="ordenarClientes('dia_visita')"
                                title="Ordenar por Día de Visita"
                              >
                                <div class="flex items-center gap-1">
                                  <span>Día</span>
                                  <span v-if="sortClientesCol === 'dia_visita'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                </div>
                              </th>

                              <template v-if="modoActivo === 'cob_core'">
                                <th
                                  v-for="mesObj in mesesCerradosRuta"
                                  :key="mesObj.ym"
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === `mes_${mesObj.ym}` ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes(`mes_${mesObj.ym}`)"
                                  :title="`Ordenar por ${mesObj.nombre}`"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>{{ mesObj.corto }} (Bs)</span>
                                    <span v-if="sortClientesCol === `mes_${mesObj.ym}`" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-28 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'total_monto_3m' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('total_monto_3m')"
                                  title="Ordenar por Total Histórico"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Total {{ mesesCerradosRuta.length }}M (Bs)</span>
                                    <span v-if="sortClientesCol === 'total_monto_3m'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors bg-blue-50/50"
                                  :class="sortClientesCol === 'peso_3m' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('peso_3m')"
                                  title="Ordenar por Peso en Acumulado Histórico de la Ruta"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Peso {{ mesesCerradosRuta.length }}M (%)</span>
                                    <span v-if="sortClientesCol === 'peso_3m'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors bg-purple-50/40"
                                  :class="sortClientesCol === 'cuota_objetivo' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('cuota_objetivo')"
                                  title="Ordenar por Objetivo correspondiente al Cliente según su Peso (Bs)"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Objetivo (Bs)</span>
                                    <span v-if="sortClientesCol === 'cuota_objetivo'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'monto_bs' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('monto_bs')"
                                  title="Ordenar por Venta Mes Actual"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>{{ nombreMesActualCorto }} (Bs)</span>
                                    <span v-if="sortClientesCol === 'monto_bs'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'tiene_logro' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('tiene_logro')"
                                  title="Ordenar por Estado de Compra Mes Actual"
                                >
                                  <div class="flex items-center justify-center gap-1">
                                    <span>Estado MTD</span>
                                    <span v-if="sortClientesCol === 'tiene_logro'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                              </template>
                              <template v-else>
                                <th
                                  class="py-1.5 px-2.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'tiene_logro' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('tiene_logro')"
                                  title="Ordenar por Estado KPI"
                                >
                                  <div class="flex items-center justify-center gap-1">
                                    <span>Estado KPI</span>
                                    <span v-if="sortClientesCol === 'tiene_logro'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-28 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'monto_bs' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('monto_bs')"
                                  title="Ordenar por Monto Bs"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Monto Bs</span>
                                    <span v-if="sortClientesCol === 'monto_bs'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'pct_ruta' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('pct_ruta')"
                                  title="Ordenar por % Aporte"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>% Aporte</span>
                                    <span v-if="sortClientesCol === 'pct_ruta'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                              </template>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.03] bg-white font-mono tabular-nums">
                            <template v-for="(cli, cidx) in clientesRutaFiltrados" :key="cli.cliente_id">
                              <tr class="hover:bg-blue-50/30 transition-colors">
                                <td class="py-1.5 px-2.5 text-center text-[#86868B] whitespace-nowrap">
                                  <button
                                    v-if="cli.tiene_logro || (cli.monto_bs || 0) > 0 || (cli.total_monto_3m || 0) > 0"
                                    type="button"
                                    @click="toggleClienteAccordion(cli, r.ruta)"
                                    class="inline-flex items-center justify-center w-4 h-4 mr-1 rounded hover:bg-black/10 transition-transform duration-200 cursor-pointer text-[#6E6E73] hover:text-[#0071E3]"
                                    :class="clienteExpandidoId === cli.cliente_id ? 'rotate-90 text-[#0071E3]' : ''"
                                    title="Desglosar historial de compras"
                                  >
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                  </button>
                                  <span v-else class="inline-block w-4 mr-1"></span>
                                  <span>{{ cidx + 1 }}</span>
                                </td>
                                <td class="py-1.5 px-2.5 font-bold text-[#6E6E73]">{{ cli.cliente_id }}</td>
                                <td class="py-1.5 px-2.5 font-sans font-medium text-[#1D1D1F]">
                                  {{ cli.cliente }}
                                  <div class="text-[9px] text-[#86868B]">{{ cli.vendedor }}</div>
                                </td>
                                <td class="py-1.5 px-2.5 font-sans text-[#6E6E73]">{{ cli.dia_visita || cli.dia }}</td>

                                <template v-if="modoActivo === 'cob_core'">
                                  <td
                                    v-for="mesObj in mesesCerradosRuta"
                                    :key="mesObj.ym"
                                    class="py-1.5 px-2.5 text-right font-mono text-[#48484A]"
                                  >
                                    {{ (cli.meses_bs?.[mesObj.ym] || 0) > 0 ? `Bs ${formatNum(cli.meses_bs[mesObj.ym], 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#1D1D1F]">
                                    Bs {{ formatNum(cli.total_monto_3m, 2) }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#0071E3] bg-blue-50/20">
                                    {{ formatPct(cli.peso_3m) }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#6B21A8] bg-purple-50/20">
                                    {{ cli.cuota_objetivo > 0 ? `Bs ${formatNum(cli.cuota_objetivo, 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-medium" :class="cli.monto_bs > 0 ? 'text-emerald-700 font-bold' : 'text-[#86868B]'">
                                    {{ cli.monto_bs > 0 ? `Bs ${formatNum(cli.monto_bs, 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-center">
                                    <span
                                      v-if="cli.tiene_logro"
                                      class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                      Compró
                                    </span>
                                    <span
                                      v-else
                                      class="inline-block px-1.5 py-0.5 rounded text-[9px] font-medium bg-black/[0.04] text-[#86868B]"
                                    >
                                      Sin compra
                                    </span>
                                  </td>
                                </template>
                                <template v-else>
                                  <td class="py-1.5 px-2.5 text-center">
                                    <span
                                      v-if="cli.tiene_logro"
                                      class="px-1.5 py-0.2 rounded font-bold text-[9px] bg-emerald-50 text-emerald-700 border border-emerald-200"
                                    >
                                      CUMPLE
                                    </span>
                                    <span
                                      v-else
                                      class="px-1.5 py-0.2 rounded font-medium text-[9px] bg-black/[0.04] text-[#86868B]"
                                    >
                                      PENDIENTE
                                    </span>
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-bold text-[#1D1D1F]">
                                    Bs {{ formatNum(cli.monto_bs, 2) }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right text-[#6E6E73]">
                                    {{ formatPct(cli.pct_ruta) }}
                                  </td>
                                </template>
                              </tr>
                              <!-- Detalle acordeón de productos, sugeridos y compras del cliente -->
                              <tr v-if="clienteExpandidoId === cli.cliente_id" class="bg-blue-50/20">
                                <td :colspan="modoActivo === 'cob_core' ? 12 : 7" class="p-3 border-y border-black/[0.05]">
                                  <div class="apple-glass-card rounded-[12px] p-3 space-y-3 bg-white/95 border border-black/[0.06] shadow-sm">
                                    <!-- Indicador de carga -->
                                    <div v-if="cargandoAccordionCliente && !detalleClienteFechasMap[cli.cliente_id]" class="py-6 text-center text-[11px] text-[#86868B] flex items-center justify-center gap-2 font-sans font-medium">
                                      <div class="w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                                      <span>Cargando productos y sugeridos de {{ cli.cliente }}...</span>
                                    </div>

                                    <template v-else-if="detalleClienteFechasMap[cli.cliente_id]">
                                      <!-- Barra Sobria Superior de Resumen y Contraste Clave -->
                                      <div class="flex flex-wrap items-center justify-between gap-3 p-2.5 rounded-[10px] bg-[#F8F8FA] border border-black/[0.05]">
                                        <div class="flex items-center gap-2">
                                          <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] shrink-0"></span>
                                          <span class="font-sans font-bold text-[#1D1D1F] text-[12px]">{{ cli.cliente }}</span>
                                          <span class="text-[#86868B] font-mono text-[10px]">ID: {{ cli.cliente_id }}</span>
                                          <span class="text-[#86868B] font-sans text-[10px]">· Ruta {{ r.ruta }}</span>
                                        </div>

                                        <!-- Métricas Clave Sobrias en línea -->
                                        <div class="flex flex-wrap items-center gap-4 text-[10px] font-mono">
                                          <div>
                                            <span class="text-[#86868B] font-sans">Cuota Objetivo: </span>
                                            <strong class="text-[#6B21A8] font-bold">{{ cli.cuota_objetivo > 0 ? `Bs ${formatNum(cli.cuota_objetivo, 2)}` : '-' }}</strong>
                                            <span class="text-[9px] text-[#86868B] font-sans ml-1">({{ formatPct(cli.peso_3m) }})</span>
                                          </div>
                                          <div>
                                            <span class="text-[#86868B] font-sans">Total Sugerido: </span>
                                            <strong class="text-purple-900 font-bold">Bs {{ formatNum(totalClienteSugeridoBs(cli.cliente_id), 2) }}</strong>
                                            <span class="text-[9px] text-purple-700 font-sans ml-1">({{ totalClienteSugeridoCant(cli.cliente_id) }} u)</span>
                                          </div>
                                          <div>
                                            <span class="text-[#86868B] font-sans">Comprado MTD: </span>
                                            <strong :class="totalClienteMtdBs(cli.cliente_id) > 0 ? 'text-emerald-700 font-bold' : 'text-[#86868B]'">
                                              Bs {{ formatNum(totalClienteMtdBs(cli.cliente_id), 2) }}
                                            </strong>
                                            <span v-if="totalClienteMtdCant(cli.cliente_id) > 0" class="text-[9px] text-emerald-600 font-sans ml-1">({{ totalClienteMtdCant(cli.cliente_id) }} u)</span>
                                          </div>
                                        </div>

                                        <!-- Buscador interno de productos -->
                                        <div class="relative">
                                          <input
                                            v-model="busquedaHistorialCliente"
                                            type="text"
                                            placeholder="Buscar producto o código..."
                                            class="apple-control-pill rounded-[8px] pl-6 pr-2.5 py-1 text-[10px] w-48 text-[#1D1D1F] focus:outline-none focus:ring-1 focus:ring-[#0071E3] font-sans"
                                          />
                                          <svg class="w-3 h-3 text-[#86868B] absolute left-2 top-1.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                          </svg>
                                        </div>
                                      </div>

                                      <!-- TABLA PRINCIPAL DEL CLIENTE: PRODUCTO, TOTAL 3M, SUGERIDO, COMPRA MTD -->
                                      <div class="overflow-x-auto custom-scroll border border-black/[0.06] rounded-[10px]">
                                        <table class="w-full text-left border-collapse text-[10px] font-mono whitespace-nowrap">
                                          <thead class="bg-[#F8F8FA] text-[#86868B] uppercase text-[9px] tracking-wider sticky top-0 z-10 border-b border-black/[0.06] select-none">
                                            <tr>
                                              <!-- Columna 1: Producto -->
                                              <th class="py-2 px-3 text-left font-sans cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('producto')">
                                                <div class="flex items-center gap-1.5">
                                                  <span class="text-[#1D1D1F] font-bold">Producto</span>
                                                  <span v-if="sortProdCol === 'producto'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                  <span class="text-[8px] text-[#86868B] lowercase font-normal">(clic fila para fechas)</span>
                                                </div>
                                              </th>

                                              <!-- Columna 2: Total Compra 3M -->
                                              <th class="py-2 px-3 text-right font-sans cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('total_bs_3m')">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span class="text-[#1D1D1F] font-bold">Total Compra {{ mesesCerradosRuta.length || store.mesesHistorico }}M</span>
                                                  <span v-if="sortProdCol === 'total_bs_3m'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>

                                              <!-- Columna 3: Sugerido a Comprar -->
                                              <th class="py-2 px-3 text-right bg-purple-50/60 font-sans cursor-pointer hover:bg-purple-100/60" @click="ordenarProductos('monto_sugerido')" title="Sugerido = ceil(Cant 3M / 3) x Precio Últ. Compra">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span class="text-purple-900 font-bold">Sugerido a Comprar</span>
                                                  <span v-if="sortProdCol === 'monto_sugerido'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>

                                              <!-- Columna 4: Compra MTD -->
                                              <th class="py-2 px-3 text-right bg-emerald-50/40 font-sans cursor-pointer hover:bg-emerald-100/40" @click="ordenarProductos('total_bs_mes_actual')" title="Compra acumulada del mes en curso">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span class="text-emerald-900 font-bold">Compra MTD</span>
                                                  <span v-if="sortProdCol === 'total_bs_mes_actual'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                            </tr>
                                          </thead>
                                          <tbody class="divide-y divide-black/[0.03] bg-white">
                                            <template v-for="(prod, pidx) in productosHistorialFiltrados(cli.cliente_id)" :key="prod.producto || prod.codigo || pidx">
                                              <!-- Fila Principal del Producto -->
                                              <tr 
                                                @click="toggleProductoExpandido(prod.producto || prod.codigo)"
                                                class="hover:bg-blue-50/30 transition-colors cursor-pointer select-none"
                                                :class="productoExpandidoKey === (prod.producto || prod.codigo) ? 'bg-blue-50/25' : ''"
                                              >
                                                <!-- 1. Producto -->
                                                <td class="py-2 px-3 font-sans">
                                                  <div class="flex items-center gap-2">
                                                    <button
                                                      type="button"
                                                      class="w-4 h-4 inline-flex items-center justify-center rounded hover:bg-black/10 text-[#6E6E73] transition-transform duration-150"
                                                      :class="productoExpandidoKey === (prod.producto || prod.codigo) ? 'rotate-90 text-[#0071E3]' : ''"
                                                      title="Ver fechas de compra"
                                                    >
                                                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                      </svg>
                                                    </button>
                                                    <span class="font-bold text-[#6E6E73] font-mono text-[9px]">[{{ prod.codigo }}]</span>
                                                    <span class="font-medium text-[#1D1D1F]">{{ prod.producto }}</span>
                                                    <span v-if="prod.es_core_pareto" class="text-[8px] font-bold px-1.5 py-0.2 rounded bg-purple-100 text-purple-800 border border-purple-200 font-sans shadow-2xs">
                                                      Core Pareto
                                                    </span>
                                                    <span v-if="prod.categoria" class="text-[8px] text-[#86868B] px-1 py-0.2 rounded bg-black/[0.03]">{{ prod.categoria }}</span>
                                                  </div>
                                                </td>

                                                <!-- 2. Total Compra 3M -->
                                                <td class="py-2 px-3 text-right">
                                                  <div class="font-bold text-[#1D1D1F]">
                                                    {{ (prod.total_bs_3m || 0) > 0 ? `Bs ${formatNum(prod.total_bs_3m, 2)}` : '-' }}
                                                  </div>
                                                  <div class="text-[9px] text-[#86868B] flex items-center justify-end gap-1">
                                                    <span>{{ (prod.total_cantidad_3m || 0) > 0 ? `${prod.total_cantidad_3m} u` : '0 u' }}</span>
                                                    <span v-if="(prod.frecuencia_compra || 0) > 0" class="text-amber-700 font-semibold">
                                                      · {{ prod.frecuencia_compra }} {{ prod.frecuencia_compra === 1 ? 'compra' : 'compras' }}
                                                    </span>
                                                  </div>
                                                </td>

                                                <!-- 3. Sugerido a Comprar -->
                                                <td class="py-2 px-3 text-right bg-purple-50/20">
                                                  <div class="font-bold text-purple-900">
                                                    {{ (prod.monto_sugerido || 0) > 0 ? `Bs ${formatNum(prod.monto_sugerido, 2)}` : '-' }}
                                                  </div>
                                                  <div class="text-[9px] text-purple-700 flex items-center justify-end gap-1.5">
                                                    <span v-if="prod.cant_sugerida > 0" class="font-bold">{{ prod.cant_sugerida }} u</span>
                                                    <span v-else-if="prod.cant_sugerida === 0" class="text-[8px] text-[#86868B] italic">No sugerido</span>
                                                    <span v-if="prod.precio_ultima_compra > 0" class="text-[#86868B]">(P. Últ: Bs {{ formatNum(prod.precio_ultima_compra, 2) }})</span>
                                                  </div>
                                                </td>

                                                <!-- 4. Compra MTD -->
                                                <td class="py-2 px-3 text-right bg-emerald-50/20">
                                                  <div class="flex items-center justify-end gap-2">
                                                    <span 
                                                      class="text-[8px] px-1.5 py-0.2 rounded font-sans font-bold"
                                                      :class="(prod.total_cantidad_mes_actual || 0) > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-[#86868B]'"
                                                    >
                                                      {{ (prod.total_cantidad_mes_actual || 0) > 0 ? 'Compró' : 'Sin compra' }}
                                                    </span>
                                                    <div class="text-right">
                                                      <div class="font-bold" :class="(prod.total_cantidad_mes_actual || 0) > 0 ? 'text-emerald-700' : 'text-[#86868B]'">
                                                        {{ (prod.total_bs_mes_actual || 0) > 0 ? `Bs ${formatNum(prod.total_bs_mes_actual, 2)}` : '-' }}
                                                      </div>
                                                      <div v-if="(prod.total_cantidad_mes_actual || 0) > 0" class="text-[9px] text-emerald-600">
                                                        {{ prod.total_cantidad_mes_actual }} u
                                                      </div>
                                                    </div>
                                                  </div>
                                                </td>
                                              </tr>

                                              <!-- Sub-fila de Detalle de Fechas: 2 Tablas Paralelas (3M vs MTD) -->
                                              <tr v-if="productoExpandidoKey === (prod.producto || prod.codigo)" class="bg-[#F8F8FA]/60">
                                                <td colspan="4" class="p-3 border-y border-black/[0.06]">
                                                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <!-- TABLA 1: Compras 3 Meses Cerrados -->
                                                    <div class="bg-white rounded-[8px] border border-black/[0.06] overflow-hidden shadow-2xs">
                                                      <div class="px-2.5 py-1.5 bg-[#F0F2F5] border-b border-black/[0.05] flex items-center justify-between text-[10px]">
                                                        <div class="flex items-center gap-1.5 font-sans font-bold text-[#1D1D1F]">
                                                          <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
                                                          <span>Histórico 3 Meses Cerrados</span>
                                                        </div>
                                                        <span class="text-[9px] text-[#6E6E73] font-mono">{{ (prod.compras_3m || []).length }} compra(s)</span>
                                                      </div>
                                                      <div class="max-h-[180px] overflow-y-auto custom-scroll">
                                                        <table class="w-full text-left text-[9px] font-mono">
                                                          <thead class="bg-[#FAFAFA] text-[#86868B] uppercase text-[8px] sticky top-0 border-b border-black/[0.04]">
                                                            <tr>
                                                              <th class="py-1 px-2">Fecha</th>
                                                              <th class="py-1 px-2">Factura</th>
                                                              <th class="py-1 px-2 text-right">Cant</th>
                                                              <th class="py-1 px-2 text-right">P. Unit</th>
                                                              <th class="py-1 px-2 text-right">Total Bs</th>
                                                              <th class="py-1 px-2 font-sans">Preventista</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody class="divide-y divide-black/[0.02]">
                                                            <tr v-for="(it, itidx) in (prod.compras_3m || [])" :key="itidx" class="hover:bg-blue-50/20">
                                                              <td class="py-1 px-2 font-bold text-[#1D1D1F]">{{ it.fecha }}</td>
                                                              <td class="py-1 px-2 text-[#6E6E73]">{{ it.nro_factura }}</td>
                                                              <td class="py-1 px-2 text-right font-bold text-[#0071E3]">{{ it.cantidad }} u</td>
                                                              <td class="py-1 px-2 text-right text-[#48484A]">Bs {{ formatNum(it.precio_unitario, 2) }}</td>
                                                              <td class="py-1 px-2 text-right font-bold text-[#1D1D1F]">Bs {{ formatNum(it.monto_bs, 2) }}</td>
                                                              <td class="py-1 px-2 font-sans text-[#6E6E73] truncate max-w-[100px]">{{ it.vendedor }}</td>
                                                            </tr>
                                                            <tr v-if="!(prod.compras_3m && prod.compras_3m.length > 0)">
                                                              <td colspan="6" class="py-3 text-center text-[#86868B] font-sans">Sin registros en los 3 meses cerrados</td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>

                                                    <!-- TABLA 2: Compras Mes Actual (MTD) -->
                                                    <div class="bg-white rounded-[8px] border border-black/[0.06] overflow-hidden shadow-2xs">
                                                      <div class="px-2.5 py-1.5 bg-emerald-50/70 border-b border-emerald-100 flex items-center justify-between text-[10px]">
                                                        <div class="flex items-center gap-1.5 font-sans font-bold text-emerald-900">
                                                          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                                          <span>Compras Mes Actual (MTD)</span>
                                                        </div>
                                                        <span class="text-[9px] text-emerald-800 font-mono font-bold">{{ (prod.compras_mes_actual || []).length }} compra(s)</span>
                                                      </div>
                                                      <div class="max-h-[180px] overflow-y-auto custom-scroll">
                                                        <table class="w-full text-left text-[9px] font-mono">
                                                          <thead class="bg-emerald-50/30 text-emerald-900 uppercase text-[8px] sticky top-0 border-b border-emerald-100">
                                                            <tr>
                                                              <th class="py-1 px-2">Fecha</th>
                                                              <th class="py-1 px-2">Factura</th>
                                                              <th class="py-1 px-2 text-right">Cant</th>
                                                              <th class="py-1 px-2 text-right">P. Unit</th>
                                                              <th class="py-1 px-2 text-right">Total Bs</th>
                                                              <th class="py-1 px-2 font-sans">Preventista</th>
                                                            </tr>
                                                          </thead>
                                                          <tbody class="divide-y divide-black/[0.02]">
                                                            <tr v-for="(it, itidx) in (prod.compras_mes_actual || [])" :key="itidx" class="hover:bg-emerald-50/20">
                                                              <td class="py-1 px-2 font-bold text-emerald-900">{{ it.fecha }}</td>
                                                              <td class="py-1 px-2 text-[#6E6E73]">{{ it.nro_factura }}</td>
                                                              <td class="py-1 px-2 text-right font-bold text-emerald-700">{{ it.cantidad }} u</td>
                                                              <td class="py-1 px-2 text-right text-[#48484A]">Bs {{ formatNum(it.precio_unitario, 2) }}</td>
                                                              <td class="py-1 px-2 text-right font-bold text-emerald-900">Bs {{ formatNum(it.monto_bs, 2) }}</td>
                                                              <td class="py-1 px-2 font-sans text-[#6E6E73] truncate max-w-[100px]">{{ it.vendedor }}</td>
                                                            </tr>
                                                            <tr v-if="!(prod.compras_mes_actual && prod.compras_mes_actual.length > 0)">
                                                              <td colspan="6" class="py-3 text-center text-[#86868B] font-sans">Sin compras registradas en el mes actual</td>
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </div>
                                                    </div>
                                                  </div>
                                                </td>
                                              </tr>
                                            </template>
                                          </tbody>
                                          <!-- Pie de Tabla: TOTALES DEL CLIENTE -->
                                          <tfoot class="bg-[#F8F8FA] border-t-2 border-black/[0.08] text-[10px] font-mono font-bold">
                                            <tr>
                                              <td class="py-2 px-3 font-sans text-[#1D1D1F]">
                                                TOTAL CLIENTE ({{ productosHistorialFiltrados(cli.cliente_id).length }} productos)
                                              </td>
                                              <td class="py-2 px-3 text-right text-[#1D1D1F]">
                                                <div>Bs {{ formatNum(totalCliente3mBs(cli.cliente_id), 2) }}</div>
                                                <div class="text-[9px] text-[#86868B] font-normal">{{ totalCliente3mCant(cli.cliente_id) }} u</div>
                                              </td>
                                              <td class="py-2 px-3 text-right text-purple-900 bg-purple-50/30">
                                                <div>Bs {{ formatNum(totalClienteSugeridoBs(cli.cliente_id), 2) }}</div>
                                                <div class="text-[9px] text-purple-700 font-normal">{{ totalClienteSugeridoCant(cli.cliente_id) }} u</div>
                                              </td>
                                              <td class="py-2 px-3 text-right bg-emerald-50/30">
                                                <div :class="totalClienteMtdBs(cli.cliente_id) > 0 ? 'text-emerald-700' : 'text-[#86868B]'">
                                                  Bs {{ formatNum(totalClienteMtdBs(cli.cliente_id), 2) }}
                                                </div>
                                                <div class="text-[9px] text-emerald-600 font-normal">{{ totalClienteMtdCant(cli.cliente_id) }} u</div>
                                              </td>
                                            </tr>
                                          </tfoot>
                                        </table>
                                        <div v-if="productosHistorialFiltrados(cli.cliente_id).length === 0" class="text-center py-6 text-[11px] text-[#86868B] font-sans">
                                          No se encontraron productos registrados para este cliente.
                                        </div>
                                      </div>
                                    </template>
                                  </div>
                                </td>
                              </tr>
                            </template>
                          </tbody>
                          <tfoot v-if="modoActivo === 'cob_core'" class="bg-[#F8F8FA] text-[#1D1D1F] font-bold sticky bottom-0 z-20 font-mono tabular-nums border-t border-black/[0.08]">
                            <tr>
                              <td colspan="4" class="py-1.5 px-2.5 text-center text-[10px] text-[#6E6E73] font-sans">
                                TOTAL RUTA {{ r.ruta }} ({{ carteraClientes.length }} Clientes)
                              </td>
                              <td
                                v-for="mesObj in mesesCerradosRuta"
                                :key="mesObj.ym"
                                class="py-1.5 px-2.5 text-right font-mono"
                              >
                                {{ (totales3mRuta.meses_bs?.[mesObj.ym] || 0) > 0 ? `Bs ${formatNum(totales3mRuta.meses_bs[mesObj.ym], 2)}` : '-' }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#1D1D1F]">
                                Bs {{ formatNum(totales3mRuta.total_3m || 0, 2) }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#0071E3] bg-blue-50/30">
                                100.0%
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#6B21A8] bg-purple-50/30">
                                Bs {{ formatNum(totales3mRuta.objetivo_mes || totalObjetivoClientes, 2) }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-emerald-700">
                                Bs {{ formatNum(totalVentaCartera, 2) }}
                              </td>
                              <td class="py-1.5 px-2.5 text-center text-[10px] text-emerald-800 font-sans">
                                {{ clientesConCompra.length }} comp.
                              </td>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>

            <!-- Totales Fijos Monográfico -->
            <tfoot v-if="totalesKpi" class="bg-[#F8F8FA] text-[#1D1D1F] font-bold sticky bottom-0 z-30 font-mono tabular-nums shadow-[inset_0_1px_0_rgba(0,0,0,0.08)]">
              <tr>
                <td class="py-2.5 px-3 text-center text-[10px] text-[#6E6E73] sticky bottom-0 left-0 z-40 bg-[#F8F8FA] font-sans">TOTAL</td>
                <td class="py-2.5 px-3 text-[10px] text-[#6E6E73] sticky bottom-0 left-10 z-40 bg-[#F8F8FA] font-sans shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]">
                  CONSOLIDADO ({{ rutasFiltradas.length }})
                </td>
                <template v-if="modoActivo === 'cob_core'">
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ totalesKpi?.cartera ?? 0 }} Cli</td>
                  <td
                    v-for="mesObj in mesesCerradosGlobal"
                    :key="mesObj.ym"
                    class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]"
                  >
                    {{ (totalesKpi?.meses_bs?.[mesObj.ym] || 0) > 0 ? `Bs ${formatNum(totalesKpi.meses_bs[mesObj.ym], 2)}` : '-' }}
                  </td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA] font-bold text-[#1D1D1F]">
                    Bs {{ formatNum(totalesKpi?.total_3m_bs, 2) }}
                  </td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-purple-50/70 text-purple-900 font-bold">Bs {{ formatNum(totalesKpi?.total_sugerido, 2) }}</td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-purple-50/50 text-[#6B21A8] font-bold">Bs {{ formatNum(totalesKpi?.objetivo_mes, 2) }}</td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-emerald-50/50 text-emerald-700 font-bold">Bs {{ formatNum(totalesKpi?.venta_mtd ?? totalesKpi?.logrado, 2) }}</td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA] font-bold text-[12px]" :class="colorPct(totalesKpi?.pct)">
                    {{ formatPct(totalesKpi?.pct) }}
                  </td>
                </template>
                <template v-else>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.meta) }}</td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.logrado) }}</td>
                  <td
                    class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA] font-bold text-[12px]"
                    :class="colorPct(totalesKpi.pct_proyeccion ?? totalesKpi.pct)"
                    :title="totalesKpi.pct_proyeccion !== undefined ? `Proyección consolidada: ${formatPct(totalesKpi.pct_proyeccion)}` : undefined"
                  >
                    {{ formatPct(totalesKpi.pct) }}
                  </td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.brecha) }}</td>
                  <td v-if="esCobertura" class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">
                    <div class="text-[#1D1D1F]">Bs {{ formatNum(totalesKpi.venta_apertura ?? totalesKpi.venta_cobertura, 1) }}</div>
                    <div class="text-[9px] text-[#6E6E73] font-sans font-normal">{{ formatPct(totalesKpi.pct_apertura) }}</div>
                  </td>
                  <td v-if="esCobertura" class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">
                    <div class="text-[#0071E3]">Bs {{ formatNum(totalesKpi.venta_repeticion, 1) }}</div>
                    <div class="text-[9px] text-[#6E6E73] font-sans font-normal">{{ formatPct(totalesKpi.pct_repeticion) }}</div>
                  </td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.cuota_dia) }}</td>
                  <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.proyeccion_cierre) }}</td>
                </template>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- Estado Vacío -->
      <div v-else class="h-64 apple-glass-card rounded-[14px] p-10 flex items-center justify-center text-[#86868B] text-[12px]">
        No se encontraron datos para los filtros especificados.
      </div>

      <!-- MODAL INFO KPI (?) -->
      <div
        v-if="modalInfoKpiVisible"
        class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-slate-900/30 backdrop-blur-md"
        @click.self="cerrarModalInfoKpi"
      >
        <div class="glass-dropdown rounded-[20px] max-w-md w-full p-5 space-y-3.5 font-sans border border-white/80 shadow-float">
          <div class="flex items-center justify-between pb-2.5 border-b border-black/[0.06]">
            <h3 class="text-[13px] font-bold text-[#1D1D1F]">{{ kpiInfoActivo?.nombre }} ({{ kpiInfoActivo?.unidad }})</h3>
            <button type="button" @click="cerrarModalInfoKpi" class="w-6 h-6 rounded-full hover:bg-black/[0.05] text-[#86868B] flex items-center justify-center cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="space-y-2 text-[11px]">
            <div>
              <span class="font-bold text-[#86868B] uppercase text-[9px] block">Tipo de KPI:</span>
              <span class="font-semibold text-[#1D1D1F]">{{ kpiInfoActivo?.tipo }} (Unidad: {{ kpiInfoActivo?.unidad }})</span>
            </div>
            <div>
              <span class="font-bold text-[#86868B] uppercase text-[9px] block">Objetivo Comercial:</span>
              <p class="text-[#6E6E73]">{{ kpiInfoActivo?.objetivo_comercial || 'Garantizar el cumplimiento del plan estratégico.' }}</p>
            </div>
            <div>
              <span class="font-bold text-[#86868B] uppercase text-[9px] block">Criterio de Inclusión:</span>
              <p class="text-[#6E6E73]">{{ kpiInfoActivo?.criterio_productos || 'Todas las ventas que califiquen para la categoría.' }}</p>
            </div>
            <div>
              <span class="font-bold text-[#86868B] uppercase text-[9px] block">Origen de Meta:</span>
              <span class="font-mono text-[#0071E3] font-bold">{{ kpiInfoActivo?.campo_meta || 'fact_metas / pan_ruteo' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SubNavTabs from '@/Components/UI/SubNavTabs.vue'
import AvanceFiltrosBar from './AvanceFiltrosBar.vue'
import { useAvanceStore } from '@/Stores/avanceStore'
import { navigationConfig } from '@/Config/navigation'

const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance')
const avanceTabs = avanceMenu ? avanceMenu.submodules : []

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  meses: { type: Array, default: () => [] },
  fecha_inicio_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
  mes_default: { type: String, default: '' },
  dias_calendario: { type: Object, default: () => ({}) }
})

const store = useAvanceStore()

const modoActivo = ref(sessionStorage.getItem('avance_cumplimiento_submodo') || 'resumen')

// Estado de ordenamiento
const sortResumenCol = ref('vol_general')
const sortResumenDir = ref('desc')

const sortKpiCol = ref('pct')
const sortKpiDir = ref('desc')

const sortLabelsKpi = computed(() => {
  const labels = {
    ruta: 'Ruta',
    meta: 'Meta Mes',
    logrado: 'Logrado / Compradores',
    pct: '% Cobertura',
    brecha: 'Brecha',
    venta_apertura: 'Venta Apertura',
    venta_repeticion: 'Venta Repetición',
    cuota_dia: 'Cuota Diaria',
    proyeccion_cierre: 'Proyección',
    cartera: 'Cartera Oficial',
    mes_1_bs: 'L2M',
    mes_2_bs: 'L1M',
    mes_3_bs: 'LM',
    total_3m_bs: `Total ${mesesCerradosGlobal.value?.length || 3}M`,
    total_sugerido: 'Compra Sugerida',
    objetivo_mes: 'Objetivo',
    venta_mtd: 'Venta MTD'
  }
  ;(mesesCerradosGlobal.value || []).forEach((m, idx) => {
    labels[`mes_${m.ym}`] = `${labelMesHistorico(idx, (mesesCerradosGlobal.value || []).length)} (${m.corto})`
  })
  return labels
})

function toggleSortResumen(col) {
  if (sortResumenCol.value === col) {
    sortResumenDir.value = sortResumenDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortResumenCol.value = col
    sortResumenDir.value = 'desc'
  }
}

function toggleSortKpi(col) {
  if (sortKpiCol.value === col) {
    sortKpiDir.value = sortKpiDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortKpiCol.value = col
    sortKpiDir.value = 'desc'
  }
}

const kpisLista = [
  { id: 'vol_general', nombre: '1. Volumen General', corto: 'Vol. General', tipo: 'VOLUMEN', unidad: 'Bs' },
  { id: 'cob_general', nombre: '2. Cobertura 100%', corto: 'Cob. General', tipo: 'COBERTURA', unidad: 'Cli' },
  { id: 'vol_seda', nombre: '3. Vol. Toque de Seda', corto: 'Vol. Seda', tipo: 'VOLUMEN', unidad: 'Bs' },
  { id: 'cob_seda', nombre: '4. Cob. Toque de Seda', corto: 'Cob. Seda', tipo: 'COBERTURA', unidad: 'Cli' },
  { id: 'vol_clinical', nombre: '5. Vol. Clinical', corto: 'Vol. Clinical', tipo: 'VOLUMEN', unidad: 'Bs' },
  { id: 'cob_clinical', nombre: '6. Cob. Clinical', corto: 'Cob. Clinical', tipo: 'COBERTURA', unidad: 'Cli' },
  { id: 'cob_core', nombre: '7. Cobertura Core', corto: 'Cob. Core', tipo: 'COBERTURA', unidad: 'Cli' }
]

const rutaExpandida = ref(null)
const cargandoClientes = ref(false)
const carteraClientes = ref([])
const diasRuta = ref([])
const modoDetalleRuta = ref('dias') // 'dias' | 'clientes'
const diaSeleccionadoInline = ref(null)

const mesesCerradosGlobal = computed(() => {
  return store.datosCumplimiento?.meses_cerrados || []
})
const mesesCerradosRuta = ref([])
const totales3mRuta = ref({})

// Ordenamiento de días
const sortDiasCol = ref('fecha')
const sortDiasDir = ref('asc')

function ordenarDias(col) {
  if (sortDiasCol.value === col) {
    sortDiasDir.value = sortDiasDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortDiasCol.value = col
    sortDiasDir.value = (col === 'fecha' || col === 'dia_semana') ? 'asc' : 'desc'
  }
}

function toggleDiaInline(fecha) {
  diaSeleccionadoInline.value = diaSeleccionadoInline.value === fecha ? null : fecha
}

const diasRutaOrdenados = computed(() => {
  const col = sortDiasCol.value
  const dir = sortDiasDir.value === 'asc' ? 1 : -1
  return [...diasRuta.value].sort((a, b) => {
    if (col === 'fecha') {
      return dir * String(a.fecha || '').localeCompare(String(b.fecha || ''))
    }
    if (col === 'dia_semana') {
      return dir * String(a.dia_semana || '').localeCompare(String(b.dia_semana || ''))
    }
    if (col === 'total_clientes') {
      return dir * (Number(a.total_clientes || 0) - Number(b.total_clientes || 0))
    }
    if (col === 'venta_total') {
      return dir * (Number(a.venta_total || 0) - Number(b.venta_total || 0))
    }
    if (col === 'venta_acumulada') {
      return dir * (Number(a.venta_acumulada || 0) - Number(b.venta_acumulada || 0))
    }
    return 0
  })
})

const totalVentaDiasRuta = computed(() => {
  return diasRuta.value.reduce((acc, d) => acc + Number(d.venta_total || 0), 0)
})

const totalVentaCartera = computed(() => {
  return carteraClientes.value.reduce((acc, c) => acc + Number(c.monto_bs || 0), 0)
})

const totalObjetivoClientes = computed(() => {
  return carteraClientes.value.reduce((acc, c) => acc + Number(c.cuota_objetivo || 0), 0)
})

const nombreMesActualCorto = computed(() => {
  const fc = store.filtrosAplicados?.fechaCorte || store.fechaCorte
  if (!fc) return 'Mes Actual'
  try {
    const d = new Date(fc + 'T00:00:00')
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
    const m = meses[d.getMonth()] || ''
    const yy = String(d.getFullYear()).slice(-2)
    return `${m} ${yy}`
  } catch (e) {
    return 'Mes Actual'
  }
})

const filtroClientesTab = ref('compradores')
const busquedaClienteTexto = ref('')

// Ordenamiento de clientes
const sortClientesCol = ref('monto_bs')
const sortClientesDir = ref('desc')

function ordenarClientes(col) {
  if (sortClientesCol.value === col) {
    sortClientesDir.value = sortClientesDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortClientesCol.value = col
    sortClientesDir.value = (col === 'cliente' || col === 'dia_visita') ? 'asc' : 'desc'
  }
}

// Acordeón de clientes
const clienteExpandidoId = ref(null)
const cargandoAccordionCliente = ref(false)
const productoExpandidoKey = ref(null)

function toggleProductoExpandido(key) {
  productoExpandidoKey.value = productoExpandidoKey.value === key ? null : key
}

function totalCliente3mCant(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.total_cantidad_3m) || 0), 0)
}

function totalCliente3mBs(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.total_bs_3m) || 0), 0)
}

function totalClienteSugeridoCant(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.cant_sugerida) || 0), 0)
}

function totalClienteSugeridoBs(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.monto_sugerido) || 0), 0)
}

function totalClienteMtdCant(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.total_cantidad_mes_actual) || 0), 0)
}

function totalClienteMtdBs(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.total_bs_mes_actual) || 0), 0)
}
const detalleClienteFechasMap = reactive({})
const modoHistorialCliente = ref('fechas') // 'fechas' | 'productos'
const busquedaHistorialCliente = ref('')

function fechasHistorialFiltradas(clienteId) {
  const datos = detalleClienteFechasMap[clienteId]?.fechas || []
  if (!busquedaHistorialCliente.value.trim()) {
    return datos
  }
  const q = busquedaHistorialCliente.value.toLowerCase().trim()
  return datos.filter(d => {
    if (d.fecha.includes(q) || (d.facturas || '').toLowerCase().includes(q) || (d.vendedor || '').toLowerCase().includes(q)) {
      return true
    }
    return (d.items || []).some(item =>
      (item.producto || '').toLowerCase().includes(q) ||
      (item.codigo || '').toLowerCase().includes(q) ||
      (item.categoria || '').toLowerCase().includes(q)
    )
  })
}

const sortProdCol = ref('monto_sugerido')
const sortProdDir = ref('desc')

function ordenarProductos(col) {
  if (sortProdCol.value === col) {
    sortProdDir.value = sortProdDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortProdCol.value = col
    sortProdDir.value = (col === 'codigo' || col === 'producto' || col === 'categoria' || col === 'ultima_fecha') ? 'asc' : 'desc'
  }
}

function productosHistorialFiltrados(clienteId) {
  const datos = detalleClienteFechasMap[clienteId]?.productos || []
  let lista = datos

  // Filtros reactivos exclusivos de Cobertura Core
  if (modoActivo.value === 'cob_core') {
    const minCant = Number(store.cantidadMinima) || 1
    if (minCant > 1) {
      lista = lista.filter(p => {
        if (p.es_core_pareto) return true
        const cHist = Number(p.total_cantidad_3m) || 0
        return cHist >= minCant || (cHist === 0 && (Number(p.total_cantidad_mes_actual) || 0) > 0)
      })
    }

    const minFrec = Number(store.frecuenciaMinima) || 1
    if (minFrec > 1) {
      lista = lista.filter(p => {
        if (p.es_core_pareto) return true
        const frec = Number(p.frecuencia_compra) || 0
        return frec >= minFrec || (frec === 0 && (Number(p.total_cantidad_mes_actual) || 0) > 0)
      })
    }
  }

  if (busquedaHistorialCliente.value.trim()) {
    const q = busquedaHistorialCliente.value.toLowerCase().trim()
    lista = lista.filter(p =>
      (p.producto || '').toLowerCase().includes(q) ||
      (p.codigo || '').toLowerCase().includes(q) ||
      (p.categoria || '').toLowerCase().includes(q) ||
      (p.facturas || '').toLowerCase().includes(q)
    )
  }
  const col = sortProdCol.value
  const dir = sortProdDir.value === 'asc' ? 1 : -1
  return [...lista].sort((a, b) => {
    if (col === 'producto' || col === 'codigo' || col === 'categoria' || col === 'ultima_fecha') {
      return dir * String(a[col] || '').localeCompare(String(b[col] || ''))
    }
    if (col === 'estado_cobertura') {
      const rank = p => (p.cumple_sugerido ? 3 : (p.compro_mes_actual ? 2 : 1))
      return dir * (rank(a) - rank(b))
    }
    return dir * (Number(a[col] || 0) - Number(b[col] || 0))
  })
}

function totalProdCol(clienteId, col) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p[col]) || 0), 0)
}

function totalProdMes(clienteId, ym) {
  if (!ym) return 0
  const lista = productosHistorialFiltrados(clienteId)
  return lista.reduce((sum, p) => sum + (Number(p.por_mes?.[ym]) || 0), 0)
}

function totalSkusCubiertosFiltrados(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.filter(p => (p.cant_sugerida || 0) > 0 && p.compro_mes_actual).length
}

function totalSkusSugeridosFiltrados(clienteId) {
  const lista = productosHistorialFiltrados(clienteId)
  return lista.filter(p => (p.cant_sugerida || 0) > 0).length
}

async function toggleClienteAccordion(cliente, ruta) {
  if (clienteExpandidoId.value === cliente.cliente_id) {
    clienteExpandidoId.value = null
    return
  }

  clienteExpandidoId.value = cliente.cliente_id
  busquedaHistorialCliente.value = ''

  if (detalleClienteFechasMap[cliente.cliente_id]) {
    return
  }

  cargandoAccordionCliente.value = true
  try {
    const kpiParaDetalle = modoActivo.value !== 'resumen' ? modoActivo.value : (store.kpiActivo || 'vol_general')
    const res = await axios.post('/supervisor/avance/cumplimiento/cliente-detalle-fechas', {
      cliente_id: cliente.cliente_id,
      ruta,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      kpi: kpiParaDetalle,
      omitir_unitarios_3m: Boolean(store.omitirUnitarios3m),
      meses_historico: Number(store.mesesHistorico) || 3,
      frecuencia_minima: Number(store.frecuenciaMinima) || 1,
      cantidad_minima: Number(store.cantidadMinima) || 1,
      cuota_objetivo: Number(cliente.cuota_objetivo) || 0,
      dia_visita: cliente.dia_visita || cliente.dia || ''
    })
    detalleClienteFechasMap[cliente.cliente_id] = res.data
  } catch (err) {
    console.error('Error al cargar detalle fechas cliente:', err)
  } finally {
    cargandoAccordionCliente.value = false
  }
}

const clientesConCompra = computed(() => {
  return carteraClientes.value.filter(c => c.tiene_logro || (c.cantidad || 0) > 0 || (c.monto_bs || 0) > 0)
})

const clientesConHistorico = computed(() => {
  return carteraClientes.value.filter(c => (c.total_monto_3m || 0) > 0)
})

const clientesRutaFiltrados = computed(() => {
  let lista = carteraClientes.value
  if (filtroClientesTab.value === 'compradores') {
    lista = clientesConCompra.value
  } else if (filtroClientesTab.value === 'con_historico') {
    lista = clientesConHistorico.value
  }
  if (busquedaClienteTexto.value.trim()) {
    const q = busquedaClienteTexto.value.toLowerCase().trim()
    lista = lista.filter(c =>
      String(c.cliente_id || '').includes(q) ||
      String(c.cliente || '').toLowerCase().includes(q) ||
      String(c.vendedor || '').toLowerCase().includes(q) ||
      String(c.dia || c.dia_visita || '').toLowerCase().includes(q)
    )
  }

  const copia = [...lista]
  const col = sortClientesCol.value
  const dir = sortClientesDir.value === 'asc' ? 1 : -1

  copia.sort((a, b) => {
    if (col === 'cliente_id') {
      return dir * (Number(a.cliente_id || 0) - Number(b.cliente_id || 0))
    }
    if (col === 'cliente') {
      return dir * String(a.cliente || '').localeCompare(String(b.cliente || ''))
    }
    if (col === 'dia_visita') {
      const diaA = String(a.dia_visita || a.dia || '')
      const diaB = String(b.dia_visita || b.dia || '')
      return dir * diaA.localeCompare(diaB)
    }
    if (col === 'tiene_logro') {
      const logroA = a.tiene_logro ? 1 : 0
      const logroB = b.tiene_logro ? 1 : 0
      if (logroA !== logroB) {
        return dir * (logroA - logroB)
      }
      return Number(b.monto_bs || 0) - Number(a.monto_bs || 0)
    }
    if (col === 'monto_bs') {
      return dir * (Number(a.monto_bs || 0) - Number(b.monto_bs || 0))
    }
    if (col === 'pct_ruta') {
      return dir * (Number(a.pct_ruta || 0) - Number(b.pct_ruta || 0))
    }
    if (col.startsWith('mes_')) {
      const ym = col.slice(4)
      const valA = Number(a.meses_bs?.[ym] ?? a[col] ?? 0)
      const valB = Number(b.meses_bs?.[ym] ?? b[col] ?? 0)
      if (valA !== valB) return dir * (valA - valB)
    }
    if (col === 'monto_mes_1') {
      return dir * (Number(a.monto_mes_1 || 0) - Number(b.monto_mes_1 || 0))
    }
    if (col === 'monto_mes_2') {
      return dir * (Number(a.monto_mes_2 || 0) - Number(b.monto_mes_2 || 0))
    }
    if (col === 'monto_mes_3') {
      return dir * (Number(a.monto_mes_3 || 0) - Number(b.monto_mes_3 || 0))
    }
    if (col === 'total_monto_3m') {
      return dir * (Number(a.total_monto_3m || 0) - Number(b.total_monto_3m || 0))
    }
    if (col === 'peso_3m') {
      return dir * (Number(a.peso_3m || 0) - Number(b.peso_3m || 0))
    }
    if (col === 'cuota_objetivo') {
      return dir * (Number(a.cuota_objetivo || 0) - Number(b.cuota_objetivo || 0))
    }
    return 0
  })

  return copia
})

const modalInfoKpiVisible = ref(false)
const kpiInfoActivo = ref(null)

const kpiActualObj = computed(() => {
  return kpisLista.find(k => k.id === modoActivo.value) || kpisLista[0]
})

const esCobertura = computed(() => {
  return kpiActualObj.value?.tipo === 'COBERTURA'
})

const rutasFiltradas = computed(() => {
  let lista = store.datosCumplimiento?.rutas || []
  const q = String(store.filtrosAplicados?.busqueda || '').toLowerCase().trim()

  if (q) {
    lista = lista.filter(r =>
      String(r.ruta || '').toLowerCase().includes(q) ||
      String(r.vendedor || '').toLowerCase().includes(q) ||
      String(r.canal || '').toLowerCase().includes(q)
    )
  }

  const copia = [...lista]

  if (modoActivo.value === 'resumen') {
    const col = sortResumenCol.value || 'vol_general'
    const dir = sortResumenDir.value === 'asc' ? 1 : -1

    copia.sort((a, b) => {
      if (col === 'ruta') {
        return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
      }
      if (col === 'score_promedio') {
        return dir * (Number(a.score_promedio || 0) - Number(b.score_promedio || 0))
      }
      const pctA = Number(a.kpis?.[col]?.pct ?? 0)
      const pctB = Number(b.kpis?.[col]?.pct ?? 0)
      if (pctA !== pctB) {
        return dir * (pctA - pctB)
      }
      const logA = Number(a.kpis?.[col]?.logrado ?? 0)
      const logB = Number(b.kpis?.[col]?.logrado ?? 0)
      if (logA !== logB) {
        return dir * (logA - logB)
      }
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    })
  } else {
    const col = sortKpiCol.value || 'pct'
    const dir = sortKpiDir.value === 'asc' ? 1 : -1

    copia.sort((a, b) => {
      if (col === 'ruta') {
        return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
      }
      if (col.startsWith('mes_')) {
        const ym = col.slice(4)
        const valA = Number(a.meses_bs?.[ym] ?? a[col] ?? 0)
        const valB = Number(b.meses_bs?.[ym] ?? b[col] ?? 0)
        if (valA !== valB) return dir * (valA - valB)
        return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
      }
      let valA = Number(a[col] ?? 0)
      let valB = Number(b[col] ?? 0)
      if (valA !== valB) {
        return dir * (valA - valB)
      }
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    })
  }

  return copia
})

const totalesResumen = computed(() => {
  return store.datosCumplimiento?.totales_columnas || {}
})

const promedioGlobalScore = computed(() => {
  const rutas = rutasFiltradas.value
  if (rutas.length === 0) return 0.0
  const suma = rutas.reduce((acc, r) => acc + Number(r.score_promedio || 0), 0)
  return suma / rutas.length
})

const totalesKpi = computed(() => {
  return store.datosCumplimiento?.totales || {}
})

function cambiarModo(modo) {
  if (modoActivo.value === modo) return
  modoActivo.value = modo
  sessionStorage.setItem('avance_cumplimiento_submodo', modo)
  rutaExpandida.value = null
  diaSeleccionadoInline.value = null
  clienteExpandidoId.value = null

  if (modo !== 'resumen') {
    sortKpiCol.value = modo === 'cob_core' ? 'total_3m_bs' : 'pct'
    sortKpiDir.value = 'desc'
  }

  if (modo === 'resumen') {
    store.vistaCumplimiento = 'resumen'
  } else {
    store.vistaCumplimiento = 'kpi'
    store.kpiActivo = modo
  }
  ejecutarConsulta()
}

function ejecutarConsulta() {
  store.aplicarFiltros()
  if (modoActivo.value === 'resumen') {
    store.vistaCumplimiento = 'resumen'
  } else {
    store.vistaCumplimiento = 'kpi'
    store.kpiActivo = modoActivo.value
  }
  return store.cargarCumplimiento()
}

function labelMesHistorico(idx, total) {
  const k = total - 1 - idx
  if (k === 0) return 'LM'
  return `L${k}M`
}

async function onCambioMesesHistorico() {
  store.guardarEstado()
  const cliId = clienteExpandidoId.value
  const rExp = rutaExpandida.value
  Object.keys(detalleClienteFechasMap).forEach(k => delete detalleClienteFechasMap[k])
  await ejecutarConsulta()
  if (rExp) {
    rutaExpandida.value = null
    await toggleDetalleRuta(rExp)
    if (cliId) {
      const cliObj = carteraClientes.value.find(c => c.cliente_id === cliId)
      if (cliObj) {
        await toggleClienteAccordion(cliObj, rExp)
      }
    }
  }
}

async function onCambioCantidadMinima() {
  store.guardarEstado()
  const cliId = clienteExpandidoId.value
  const rExp = rutaExpandida.value
  Object.keys(detalleClienteFechasMap).forEach(k => delete detalleClienteFechasMap[k])
  await ejecutarConsulta()
  if (rExp) {
    rutaExpandida.value = null
    await toggleDetalleRuta(rExp)
    if (cliId) {
      const cliObj = carteraClientes.value.find(c => c.cliente_id === cliId)
      if (cliObj) {
        await toggleClienteAccordion(cliObj, rExp)
      }
    }
  }
}

async function onToggleOmitirUnitarios() {
  return onCambioCantidadMinima()
}

async function onCambioFrecuenciaMinima() {
  store.guardarEstado()
  const cliId = clienteExpandidoId.value
  const rExp = rutaExpandida.value
  Object.keys(detalleClienteFechasMap).forEach(k => delete detalleClienteFechasMap[k])
  await ejecutarConsulta()
  if (rExp) {
    rutaExpandida.value = null
    await toggleDetalleRuta(rExp)
    if (cliId) {
      const cliObj = carteraClientes.value.find(c => c.cliente_id === cliId)
      if (cliObj) {
        await toggleClienteAccordion(cliObj, rExp)
      }
    }
  }
}

async function toggleDetalleRuta(ruta) {
  if (rutaExpandida.value === ruta) {
    rutaExpandida.value = null
    diaSeleccionadoInline.value = null
    clienteExpandidoId.value = null
    return
  }
  rutaExpandida.value = ruta
  cargandoClientes.value = true
  carteraClientes.value = []
  diasRuta.value = []
  diaSeleccionadoInline.value = null
  clienteExpandidoId.value = null
  for (const k in detalleClienteFechasMap) delete detalleClienteFechasMap[k]

  try {
    const kpiParaDetalle = modoActivo.value !== 'resumen' ? modoActivo.value : (store.kpiActivo || 'vol_general')
    const res = await axios.post('/supervisor/avance/cumplimiento/clientes-ruta', {
      ruta,
      kpi: kpiParaDetalle,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      omitir_unitarios_3m: Boolean(store.omitirUnitarios3m),
      meses_historico: Number(store.mesesHistorico) || 3,
      frecuencia_minima: Number(store.frecuenciaMinima) || 1,
      cantidad_minima: Number(store.cantidadMinima) || 1
    })
    carteraClientes.value = res.data.clientes || []
    diasRuta.value = res.data.por_dia || []
    mesesCerradosRuta.value = res.data.meses_cerrados || mesesCerradosGlobal.value || []
    totales3mRuta.value = res.data.totales_3m_ruta || {}
    diaSeleccionadoInline.value = null
    clienteExpandidoId.value = null

    if (modoActivo.value === 'cob_core') {
      modoDetalleRuta.value = 'clientes'
      filtroClientesTab.value = 'todos'
      sortClientesCol.value = 'peso_3m'
      sortClientesDir.value = 'desc'
    } else {
      const tienenCompra = carteraClientes.value.filter(c => c.tiene_logro || (c.cantidad || 0) > 0 || (c.monto_bs || 0) > 0)
      filtroClientesTab.value = tienenCompra.length > 0 ? 'compradores' : 'todos'
    }
  } catch (err) {
    console.error('Error al cargar clientes de la ruta:', err)
  } finally {
    cargandoClientes.value = false
  }
}

function abrirModalInfoKpi(kpi) {
  const metas = store.datosCumplimiento?.metadata_kpis || []
  const encontrado = metas.find(m => m.clave === kpi.id || m.id === kpi.id)
  kpiInfoActivo.value = encontrado || kpi
  modalInfoKpiVisible.value = true
}

function cerrarModalInfoKpi() {
  modalInfoKpiVisible.value = false
  kpiInfoActivo.value = null
}

function formatNum(val, decimals = 1) {
  if (val === null || val === undefined) return '0'
  return Number(val).toLocaleString('es-BO', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  })
}

function formatValorKpi(val) {
  if (val === null || val === undefined) return '0'
  if (esCobertura.value) {
    return `${Number(val).toLocaleString('es-BO', { maximumFractionDigits: 0 })} Cli`
  }
  return `Bs ${Number(val).toLocaleString('es-BO', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}`
}

function formatLogroResumen(val, tipo) {
  if (val === null || val === undefined) return '0'
  if (tipo === 'COBERTURA') {
    return `${Number(val).toFixed(0)}c`
  }
  return `Bs ${Number(val).toLocaleString('es-BO', { maximumFractionDigits: 0 })}`
}

function formatPct(val) {
  if (val === null || val === undefined) return '0.0%'
  return `${Number(val).toFixed(1)}%`
}

function colorPct(pct) {
  const n = Number(pct || 0)
  if (n >= 100) return 'text-emerald-600'
  if (n >= 85) return 'text-amber-600'
  return 'text-red-600'
}

function colorKpiResumen(kpiId, kpiObj) {
  if (!kpiObj || Number(kpiObj.meta || 0) <= 0) return 'text-[#86868B]'
  const esVolumen = ['vol_general', 'vol_seda', 'vol_clinical'].includes(kpiId)
  // El color se evalúa según el ritmo/predicción (pct_ritmo) o porcentaje según sea el caso
  const evalPct = Number(kpiObj.pct_ritmo !== undefined ? kpiObj.pct_ritmo : (kpiObj.pct ?? 0))

  if (esVolumen) {
    if (evalPct >= 100) return 'text-emerald-600'
    if (evalPct >= 85) return 'text-amber-600'
    return 'text-red-600'
  } else {
    if (evalPct >= 85) return 'text-emerald-600'
    if (evalPct >= 70) return 'text-amber-600'
    return 'text-red-600'
  }
}

function badgeRitmo(pctRitmo) {
  const n = Number(pctRitmo || 0)
  if (n >= 100) return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
  if (n >= 90) return 'bg-amber-50 text-amber-700 border border-amber-200'
  return 'bg-rose-50 text-rose-700 border border-rose-200'
}

onMounted(async () => {
  store.inicializarDesdeStorage(props)
  if (modoActivo.value === 'resumen') {
    store.vistaCumplimiento = 'resumen'
  } else {
    store.vistaCumplimiento = 'kpi'
    store.kpiActivo = modoActivo.value
  }
  await store.cargarCumplimiento()
})
</script>

