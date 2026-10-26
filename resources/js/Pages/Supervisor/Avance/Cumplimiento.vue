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
                    <div class="flex items-center gap-1 font-mono">
                      <span class="font-bold text-[12px]" :class="colorPct(r.kpis?.[kpi.id]?.pct)">
                        {{ formatPct(r.kpis?.[kpi.id]?.pct) }}
                      </span>
                      <span
                        v-if="r.kpis?.[kpi.id]?.pct_ritmo !== undefined"
                        class="text-[9px] px-1 py-0.2 rounded font-bold"
                        :class="badgeRitmo(r.kpis?.[kpi.id]?.pct_ritmo)"
                        :title="`Ritmo: ${formatPct(r.kpis?.[kpi.id]?.pct_ritmo)} vs meta esperada a la fecha`"
                      >
                        {{ formatPct(r.kpis?.[kpi.id]?.pct_ritmo) }}
                      </span>
                    </div>
                    <div class="text-[9px] text-[#86868B] font-mono">
                      {{ formatLogroResumen(r.kpis?.[kpi.id]?.logrado, kpi.tipo) }} / {{ formatLogroResumen(r.kpis?.[kpi.id]?.meta, kpi.tipo) }}
                    </div>
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
                    <span class="font-bold text-[12px]" :class="colorPct(totalesResumen[kpi.id]?.pct)">
                      {{ formatPct(totalesResumen[kpi.id]?.pct) }}
                    </span>
                    <span class="text-[9px] text-[#6E6E73]">
                      {{ formatLogroResumen(totalesResumen[kpi.id]?.logrado, kpi.tipo) }} / {{ formatLogroResumen(totalesResumen[kpi.id]?.meta, kpi.tipo) }}
                    </span>
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
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
            <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase">
              {{ kpiActualObj?.nombre }} · Detalle Monográfico
            </span>
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
                  <td class="py-2 px-3 text-right font-medium text-[#1D1D1F]">{{ formatValorKpi(r.meta) }}</td>
                  <td class="py-2 px-3 text-right font-bold text-[#1D1D1F]">{{ formatValorKpi(r.logrado) }}</td>
                  <td class="py-2 px-3 text-right font-bold text-[12px]" :class="colorPct(r.pct)">
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
                </tr>

                <!-- FILA EXPANDIBLE: Desglose de la Ruta (Venta por Día vs Por Clientes) -->
                <tr v-if="rutaExpandida === r.ruta" class="bg-black/[0.02]">
                  <td :colspan="esCobertura ? 10 : 8" class="p-3.5 border-y border-black/[0.08] shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)] font-sans">
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
                                type="button"
                                @click="filtroClientesTab = 'compradores'"
                                class="px-2 py-0.5 rounded-[6px] transition-all cursor-pointer font-bold"
                                :class="filtroClientesTab === 'compradores' ? 'glass-active-pill text-[#0071E3]' : 'text-[#6E6E73]'"
                              >
                                Compradores ({{ clientesConCompra.length }})
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
                          <thead class="bg-[#F8F8FA] text-[#6E6E73] font-bold uppercase sticky top-0 z-10 text-[9px] tracking-wider border-b border-black/[0.06]">
                            <tr>
                              <th class="py-1.5 px-2.5 w-10 text-center">#</th>
                              <th class="py-1.5 px-2.5 w-24">Fecha</th>
                              <th class="py-1.5 px-2.5 w-24">Día Semana</th>
                              <th class="py-1.5 px-2.5 text-center w-28">Compradores</th>
                              <th class="py-1.5 px-2.5 text-right w-28 font-mono">Venta Día (Bs)</th>
                              <th class="py-1.5 px-2.5 text-right w-32 font-mono text-[#0071E3]">Acumulado (Bs)</th>
                              <th class="py-1.5 px-2.5 text-center w-24">Facturas</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.03] bg-white font-mono tabular-nums">
                            <template v-for="(dia, didx) in diasRutaOrdenados" :key="dia.fecha">
                              <tr class="hover:bg-blue-50/30 transition-colors">
                                <td class="py-1.5 px-2.5 text-center text-[#86868B]">{{ didx + 1 }}</td>
                                <td class="py-1.5 px-2.5 font-bold text-[#1D1D1F]">{{ dia.fecha }}</td>
                                <td class="py-1.5 px-2.5 font-sans text-[#6E6E73]">{{ dia.dia_semana }}</td>
                                <td class="py-1.5 px-2.5 text-center font-bold text-[#1D1D1F]">{{ dia.total_clientes }} cli</td>
                                <td class="py-1.5 px-2.5 text-right font-bold text-[#1D1D1F]">Bs {{ formatNum(dia.venta_total, 2) }}</td>
                                <td class="py-1.5 px-2.5 text-right font-bold text-[#0071E3]">Bs {{ formatNum(dia.venta_acumulada, 2) }}</td>
                                <td class="py-1.5 px-2.5 text-center font-sans">
                                  <button
                                    type="button"
                                    @click="toggleDiaInline(dia.fecha)"
                                    class="px-2 py-0.5 text-[9px] font-bold rounded-[6px] transition-colors cursor-pointer"
                                    :class="diaSeleccionadoInline === dia.fecha ? 'bg-[#0071E3] text-white' : 'bg-black/[0.04] text-[#0071E3] hover:bg-black/[0.08]'"
                                  >
                                    {{ diaSeleccionadoInline === dia.fecha ? 'Ocultar' : 'Ver Clientes' }}
                                  </button>
                                </td>
                              </tr>
                              <!-- Detalle inline de clientes del día -->
                              <tr v-if="diaSeleccionadoInline === dia.fecha" class="bg-blue-50/20">
                                <td colspan="7" class="p-2 border-y border-black/[0.04]">
                                  <div class="space-y-1">
                                    <div v-for="cDia in dia.clientes" :key="cDia.cliente_id" class="flex items-center justify-between text-[9px] px-2 py-1 rounded bg-white border border-black/[0.04]">
                                      <span class="font-sans font-bold text-[#1D1D1F]">{{ cDia.cliente }} (ID: {{ cDia.cliente_id }})</span>
                                      <span class="text-[#6E6E73]">Preventista: {{ cDia.vendedor }}</span>
                                      <span class="font-bold text-[#0071E3]">Bs {{ formatNum(cDia.monto_bs, 2) }}</span>
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
                          <thead class="bg-[#F8F8FA] text-[#6E6E73] font-bold uppercase sticky top-0 z-10 text-[9px] tracking-wider border-b border-black/[0.06]">
                            <tr>
                              <th class="py-1.5 px-2.5 w-10 text-center">#</th>
                              <th class="py-1.5 px-2.5 w-20">ID</th>
                              <th class="py-1.5 px-2.5">Cliente</th>
                              <th class="py-1.5 px-2.5 w-20">Día Visita</th>
                              <th class="py-1.5 px-2.5 text-center w-20">Estado KPI</th>
                              <th class="py-1.5 px-2.5 text-right w-24 font-mono">Monto Bs</th>
                              <th class="py-1.5 px-2.5 text-right w-20 font-mono">% Aporte</th>
                              <th class="py-1.5 px-2.5 text-center w-24">Acción</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-black/[0.03] bg-white font-mono tabular-nums">
                            <tr
                              v-for="(cli, cidx) in clientesRutaFiltrados"
                              :key="cli.cliente_id"
                              class="hover:bg-blue-50/30 transition-colors"
                            >
                              <td class="py-1.5 px-2.5 text-center text-[#86868B]">{{ cidx + 1 }}</td>
                              <td class="py-1.5 px-2.5 font-bold text-[#6E6E73]">{{ cli.cliente_id }}</td>
                              <td class="py-1.5 px-2.5 font-sans font-medium text-[#1D1D1F]">
                                {{ cli.cliente }}
                                <div class="text-[9px] text-[#86868B]">{{ cli.vendedor }}</div>
                              </td>
                              <td class="py-1.5 px-2.5 font-sans text-[#6E6E73]">{{ cli.dia_visita || cli.dia }}</td>
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
                              <td class="py-1.5 px-2.5 text-center font-sans">
                                <button
                                  v-if="cli.tiene_logro || (cli.monto_bs || 0) > 0"
                                  type="button"
                                  @click="abrirDetalleFechasCliente(cli, r.ruta)"
                                  class="px-2 py-0.5 text-[9px] font-bold rounded-[6px] bg-[#0071E3]/10 text-[#0071E3] hover:bg-[#0071E3] hover:text-white transition-colors cursor-pointer"
                                >
                                  Ver Detalle
                                </button>
                                <span v-else class="text-[#C7C7CC]">—</span>
                              </td>
                            </tr>
                          </tbody>
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
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.meta) }}</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ formatValorKpi(totalesKpi.logrado) }}</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]" :class="colorPct(totalesKpi.pct)">{{ formatPct(totalesKpi.pct) }}</td>
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
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- Estado Vacío -->
      <div v-else class="h-64 apple-glass-card rounded-[14px] p-10 flex items-center justify-center text-[#86868B] text-[12px]">
        No se encontraron datos para los filtros especificados.
      </div>

      <!-- MODAL HISTORIAL DE COMPRAS POR CLIENTE -->
      <div
        v-if="modalClienteVisible"
        class="fixed inset-0 z-[120] flex items-center justify-center p-3 bg-slate-900/30 backdrop-blur-md"
        @click.self="cerrarModalCliente"
      >
        <div class="glass-dropdown rounded-[20px] max-w-2xl w-full p-5 space-y-3.5 max-h-[85vh] flex flex-col font-sans border border-white/80 shadow-float">
          <div class="flex items-center justify-between pb-2.5 border-b border-black/[0.06]">
            <div>
              <h3 class="text-[13px] font-bold text-[#1D1D1F]">Historial de Compras</h3>
              <p class="text-[10px] text-[#6E6E73]">
                ID: <strong>{{ clienteActivo?.cliente_id }}</strong> - {{ clienteActivo?.cliente }} (Ruta: {{ rutaClienteActivo }})
              </p>
            </div>
            <button type="button" @click="cerrarModalCliente" class="w-6 h-6 rounded-full hover:bg-black/[0.05] text-[#86868B] flex items-center justify-center cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="flex items-center justify-between apple-control-pill p-2.5 rounded-[12px] text-[11px] font-mono tabular-nums">
            <div><span class="text-[#86868B] font-sans">Total: </span><strong class="text-[#1D1D1F]">Bs {{ formatNum(detalleClienteFechas?.total_monto_bs) }}</strong></div>
            <div><span class="text-[#86868B] font-sans">Unidades: </span><strong class="text-[#1D1D1F]">{{ detalleClienteFechas?.total_unidades || 0 }} u</strong></div>
            <div><span class="text-[#86868B] font-sans">KPI: </span><span class="font-bold text-[#0071E3]">{{ detalleClienteFechas?.kpi_nombre }}</span></div>
          </div>

          <div v-if="cargandoDetalleCliente" class="py-8 text-center text-[11px] text-[#86868B]">
            <div class="w-5 h-5 mx-auto mb-2 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin"></div>
            Cargando facturas...
          </div>

          <div v-else-if="detalleClienteFechas?.fechas?.length > 0" class="overflow-y-auto custom-scroll space-y-1.5 flex-1 max-h-[500px] pr-1 font-mono">
            <div
              v-for="dia in detalleClienteFechas.fechas"
              :key="dia.fecha"
              class="p-2.5 rounded-[10px] border border-black/[0.06] bg-white/70 space-y-1"
            >
              <div class="flex items-center justify-between pb-1 border-b border-black/[0.04] text-[11px]">
                <span class="font-bold text-[#1D1D1F]">{{ dia.fecha }}</span>
                <span class="font-bold text-[#1D1D1F]">Bs {{ formatNum(dia.total_bs ?? dia.monto_bs) }}</span>
              </div>
              <div class="text-[10px] text-[#86868B] flex items-center justify-between font-sans">
                <span>Factura(s): {{ dia.facturas }}</span>
                <span>Preventista: {{ dia.vendedor }}</span>
              </div>
              <div class="mt-1 space-y-0.5">
                <div
                  v-for="(item, iidx) in dia.items"
                  :key="iidx"
                  class="flex items-center justify-between text-[9px] py-0.5 px-1 rounded bg-black/[0.02]"
                >
                  <span class="text-[#48484A] truncate max-w-[320px]">{{ item.producto }} ({{ item.codigo }})</span>
                  <span class="font-bold text-[#1D1D1F]">{{ item.cantidad }} u - Bs {{ formatNum(item.monto_bs) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
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
import { ref, computed, onMounted } from 'vue'
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

const sortLabelsKpi = {
  ruta: 'Ruta',
  meta: 'Meta Mes',
  logrado: 'Logrado',
  pct: '% Avance',
  brecha: 'Brecha',
  venta_apertura: 'Venta Apertura',
  venta_repeticion: 'Venta Repetición',
  cuota_dia: 'Cuota Diaria',
  proyeccion_cierre: 'Proyección'
}

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
  { id: 'cob_clinical', nombre: '6. Cob. Clinical', corto: 'Cob. Clinical', tipo: 'COBERTURA', unidad: 'Cli' }
]

const rutaExpandida = ref(null)
const cargandoClientes = ref(false)
const carteraClientes = ref([])
const diasRuta = ref([])
const modoDetalleRuta = ref('dias') // 'dias' | 'clientes'
const diaSeleccionadoInline = ref(null)

function toggleDiaInline(fecha) {
  diaSeleccionadoInline.value = diaSeleccionadoInline.value === fecha ? null : fecha
}

const diasRutaOrdenados = computed(() => {
  return [...diasRuta.value].sort((a, b) => a.fecha.localeCompare(b.fecha))
})

const totalVentaDiasRuta = computed(() => {
  return diasRuta.value.reduce((acc, d) => acc + Number(d.venta_total || 0), 0)
})

const filtroClientesTab = ref('compradores')
const busquedaClienteTexto = ref('')

const clientesConCompra = computed(() => {
  return carteraClientes.value.filter(c => c.tiene_logro || (c.cantidad || 0) > 0 || (c.monto_bs || 0) > 0)
})

const clientesRutaFiltrados = computed(() => {
  let lista = carteraClientes.value
  if (filtroClientesTab.value === 'compradores') {
    lista = clientesConCompra.value
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
  return lista
})

const modalClienteVisible = ref(false)
const clienteActivo = ref(null)
const rutaClienteActivo = ref('')
const cargandoDetalleCliente = ref(false)
const detalleClienteFechas = ref(null)

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

  if (modo !== 'resumen') {
    sortKpiCol.value = 'pct'
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
  if (modoActivo.value === 'resumen') {
    store.vistaCumplimiento = 'resumen'
  } else {
    store.vistaCumplimiento = 'kpi'
    store.kpiActivo = modoActivo.value
  }
  return store.cargarCumplimiento()
}

async function toggleDetalleRuta(ruta) {
  if (rutaExpandida.value === ruta) {
    rutaExpandida.value = null
    return
  }
  rutaExpandida.value = ruta
  cargandoClientes.value = true
  carteraClientes.value = []
  diasRuta.value = []

  try {
    const kpiParaDetalle = modoActivo.value !== 'resumen' ? modoActivo.value : (store.kpiActivo || 'vol_general')
    const res = await axios.post('/supervisor/avance/cumplimiento/clientes-ruta', {
      ruta,
      kpi: kpiParaDetalle,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte
    })
    carteraClientes.value = res.data.clientes || []
    diasRuta.value = res.data.por_dia || []
    diaSeleccionadoInline.value = null
    const tienenCompra = carteraClientes.value.filter(c => c.tiene_logro || (c.cantidad || 0) > 0 || (c.monto_bs || 0) > 0)
    filtroClientesTab.value = tienenCompra.length > 0 ? 'compradores' : 'todos'
  } catch (err) {
    console.error('Error al cargar clientes de la ruta:', err)
  } finally {
    cargandoClientes.value = false
  }
}

async function abrirDetalleFechasCliente(cliente, ruta) {
  clienteActivo.value = cliente
  rutaClienteActivo.value = ruta
  modalClienteVisible.value = true
  cargandoDetalleCliente.value = true
  detalleClienteFechas.value = null

  try {
    const kpiParaDetalle = modoActivo.value !== 'resumen' ? modoActivo.value : (store.kpiActivo || 'vol_general')
    const res = await axios.post('/supervisor/avance/cumplimiento/cliente-detalle-fechas', {
      cliente_id: cliente.cliente_id,
      ruta,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      kpi: kpiParaDetalle
    })
    detalleClienteFechas.value = res.data
  } catch (err) {
    console.error('Error al cargar detalle fechas cliente:', err)
  } finally {
    cargandoDetalleCliente.value = false
  }
}

function cerrarModalCliente() {
  modalClienteVisible.value = false
  clienteActivo.value = null
  detalleClienteFechas.value = null
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
  if (n >= 80) return 'text-amber-600'
  return 'text-red-600'
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

