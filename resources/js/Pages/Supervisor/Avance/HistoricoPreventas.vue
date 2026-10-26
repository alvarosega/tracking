<template>
  <SupervisorLayout title="Avance > Preventas Históricas">
    <div class="space-y-2.5 font-sans select-none">
      <!-- Subnavegación de Módulo Avance -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- Barra Superior de Filtros Transversales -->
      <AvanceFiltrosBar @consultar="consultarHistorico" />

      <!-- Barra de Control de Vistas y Umbrales -->
      <div class="apple-glass-card rounded-[14px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-3 shadow-xs border border-white/60">
        <!-- Título e Indicador de Modo -->
        <div class="flex items-center gap-2">
          <span
            class="w-2.5 h-2.5 rounded-full"
            :class="vistaActiva === 'efectividad' ? 'bg-[#0071E3]' : 'bg-[#34C759]'"
          ></span>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase">
                {{ vistaActiva === 'efectividad' ? 'Histórico de Disciplina & Efectividad' : 'Histórico de Volumen de Preventa (Bs)' }}
              </span>
              <span v-if="rangoLabel" class="text-[10px] font-mono text-[#0071E3] font-semibold bg-blue-50/80 px-2 py-0.5 rounded-full border border-blue-200/60">
                {{ rangoLabel }}
              </span>
            </div>
            <p class="text-[10px] text-[#86868B] font-medium hidden sm:block">
              Umbral de efectividad comercial: compras acumuladas &ge; 70 Bs/día por cliente
            </p>
          </div>
        </div>

        <!-- Acciones y Selector de Vista -->
        <div class="flex items-center gap-2 ml-auto">
          <!-- Toggle Solo Efectividad (Solo en vista de efectividad) -->
          <button
            v-if="vistaActiva === 'efectividad'"
            type="button"
            @click="soloEfectividad = !soloEfectividad"
            class="px-2.5 py-1 text-[11px] font-semibold rounded-[8px] transition-all cursor-pointer shadow-xs"
            :class="soloEfectividad
              ? 'bg-[#1D1D1F] text-white shadow-sm'
              : 'border border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F5F5F7]'"
          >
            {{ soloEfectividad ? 'Ver Completo' : 'Solo Efectividad' }}
          </button>

          <!-- Segmented View Pill -->
          <div class="inline-flex bg-[#E5E5EA]/70 p-0.5 rounded-[9px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="vistaActiva = 'efectividad'"
              class="px-3 py-1 text-[11px] font-bold rounded-[7px] transition-all cursor-pointer"
              :class="vistaActiva === 'efectividad'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] font-medium'"
            >
              Disciplina & Efectividad
            </button>
            <button
              type="button"
              @click="vistaActiva = 'volumen'"
              class="px-3 py-1 text-[11px] font-bold rounded-[7px] transition-all cursor-pointer"
              :class="vistaActiva === 'volumen'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] font-medium'"
            >
              Volumen de Preventa
            </button>
          </div>
        </div>
      </div>

      <!-- Ribbon de KPIs Resumen Global del Rango -->
      <div v-if="kpisGlobales" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
        <div class="apple-glass-card rounded-[12px] p-2.5 border border-white/60">
          <div class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Cartera Plan</div>
          <div class="text-sm font-bold text-[#1D1D1F] font-num mt-0.5">{{ fmt(kpisGlobales.total_plan) }}</div>
        </div>
        <div class="apple-glass-card rounded-[12px] p-2.5 border border-white/60">
          <div class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Clientes Visitados</div>
          <div class="text-sm font-bold text-[#1D1D1F] font-num mt-0.5">{{ fmt(kpisGlobales.total_visitados) }}</div>
        </div>
        <div class="apple-glass-card rounded-[12px] p-2.5 border border-white/60">
          <div class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Con Preventa</div>
          <div class="text-sm font-bold text-[#1D1D1F] font-num mt-0.5">{{ fmt(kpisGlobales.total_preventa) }}</div>
        </div>
        <div class="apple-glass-card rounded-[12px] p-2.5 border border-white/60">
          <div class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Efectividad Global</div>
          <div class="text-sm font-bold font-num mt-0.5" :class="kpisGlobales.efectividad_global_pct >= 50 ? 'text-[#248A3D]' : 'text-[#B25E00]'">
            {{ fmtPct(kpisGlobales.efectividad_global_pct) }}
          </div>
        </div>
        <div class="apple-glass-card rounded-[12px] p-2.5 border border-white/60">
          <div class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Efectivos &ge; 70 Bs</div>
          <div class="text-sm font-bold text-[#248A3D] font-num mt-0.5">{{ fmt(kpisGlobales.total_sobre_umbral) }}</div>
        </div>
        <div class="apple-glass-card rounded-[12px] p-2.5 border border-white/60">
          <div class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Volumen Facturado</div>
          <div class="text-sm font-bold text-[#0071E3] font-num mt-0.5">{{ fmtBs(kpisGlobales.total_volumen_bs) }}</div>
        </div>
      </div>

      <!-- SKELETON LOADER -->
      <div v-if="cargando" class="apple-glass-card rounded-[14px] p-4 animate-pulse space-y-2">
        <div class="h-8 bg-black/[0.04] rounded-[8px]"></div>
        <div v-for="i in 8" :key="i" class="h-9 bg-black/[0.02] rounded-[8px] flex items-center px-3 gap-3">
          <div class="h-3 bg-black/[0.06] rounded w-6"></div>
          <div class="h-3 bg-black/[0.06] rounded w-20"></div>
          <div class="h-3 bg-black/[0.06] rounded w-16"></div>
          <div class="h-3 bg-black/[0.06] rounded w-28"></div>
          <div class="h-3 bg-black/[0.06] rounded flex-1"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="errorMsg" class="p-4 rounded-[12px] bg-red-50/80 backdrop-blur-md text-red-700 border border-red-200/80 text-[12px] font-semibold text-center">
        {{ errorMsg }}
      </div>

      <!-- TABLA PRINCIPAL DE MATRIZ HISTÓRICA -->
      <div
        v-else
        class="apple-glass-card rounded-[14px] shadow-float overflow-hidden border border-white/60 flex flex-col"
        style="max-height: calc(100vh - 17rem);"
      >
        <div class="overflow-x-auto custom-scroll relative flex-1">
          <table class="w-full text-left text-xs border-separate border-spacing-0 font-num">
            <!-- THEAD MULTI-NIVEL -->
            <thead class="bg-[#F5F5F7] text-[#1D1D1F] font-bold uppercase text-[10px] tracking-tight sticky top-0 z-30 shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)]">
              <!-- Nivel 1: Cabeceras de Día y Rango -->
              <tr>
                <th rowspan="2" class="p-2.5 border-r border-[#E5E5EA] border-b border-[#E5E5EA] w-8 text-center bg-[#F5F5F7] sticky left-0 z-40">#</th>
                <th rowspan="2" class="p-2.5 border-r border-[#E5E5EA] border-b border-[#E5E5EA] w-8 text-center bg-[#F5F5F7] sticky left-8 z-40"></th>
                <th rowspan="2" class="p-2.5 border-r border-[#E5E5EA] border-b border-[#E5E5EA] min-w-[90px] bg-[#F5F5F7] sticky left-16 z-40">Ruta</th>
                <th rowspan="2" class="p-2.5 border-r border-[#E5E5EA] border-b border-[#E5E5EA] min-w-[70px] bg-[#F5F5F7]">Canal</th>
                <th rowspan="2" class="p-2.5 border-r-2 border-[#D1D1D6] border-b border-[#E5E5EA] min-w-[150px] bg-[#F5F5F7]">Preventista</th>

                <!-- Días del Rango -->
                <template v-for="d in diasColumnas" :key="d.fecha">
                  <th
                    :colspan="vistaActiva === 'efectividad' ? (soloEfectividad ? 1 : 4) : 2"
                    class="p-2 text-center border-r-2 border-[#D1D1D6] border-b border-[#E5E5EA] bg-[#EFEFF4]/70 font-bold whitespace-nowrap"
                  >
                    {{ d.dia_label }}
                  </th>
                </template>

                <!-- Total Rango -->
                <th
                  :colspan="vistaActiva === 'efectividad' ? (soloEfectividad ? 1 : 4) : 2"
                  class="p-2 text-center bg-[#E5E5EA]/80 border-b border-[#E5E5EA] text-[#1D1D1F] font-bold"
                >
                  Total Rango
                </th>
              </tr>

              <!-- Nivel 2: Sub-columnas Métricas -->
              <tr>
                <template v-for="d in diasColumnas" :key="'sub_' + d.fecha">
                  <!-- Efectividad Mode -->
                  <template v-if="vistaActiva === 'efectividad'">
                    <template v-if="soloEfectividad">
                      <th class="p-1.5 text-center text-[9px] text-[#6E6E73] border-r-2 border-[#D1D1D6] border-b border-[#E5E5EA]">Efect.</th>
                    </template>
                    <template v-else>
                      <th class="p-1.5 text-center text-[9px] text-[#6E6E73] border-b border-[#E5E5EA]">Plan</th>
                      <th class="p-1.5 text-center text-[9px] text-[#6E6E73] border-b border-[#E5E5EA]">Vis.</th>
                      <th class="p-1.5 text-center text-[9px] text-[#6E6E73] border-b border-[#E5E5EA]">Prev.</th>
                      <th class="p-1.5 text-center text-[9px] text-[#6E6E73] border-r-2 border-[#D1D1D6] border-b border-[#E5E5EA]">Efect.</th>
                    </template>
                  </template>

                  <!-- Volumen Mode -->
                  <template v-else>
                    <th class="p-1.5 text-center text-[9px] text-[#6E6E73] border-b border-[#E5E5EA]">Prev &ge;70</th>
                    <th class="p-1.5 text-right text-[9px] text-[#6E6E73] border-r-2 border-[#D1D1D6] border-b border-[#E5E5EA] pr-2.5">Vol. Total</th>
                  </template>
                </template>

                <!-- Sub-columnas Total Rango -->
                <template v-if="vistaActiva === 'efectividad'">
                  <template v-if="soloEfectividad">
                    <th class="p-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA]">Efect.</th>
                  </template>
                  <template v-else>
                    <th class="p-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA]">Plan</th>
                    <th class="p-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA]">Vis.</th>
                    <th class="p-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA]">Prev.</th>
                    <th class="p-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA]">Efect.</th>
                  </template>
                </template>
                <template v-else>
                  <th class="p-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA]">Prev &ge;70</th>
                  <th class="p-1.5 text-right text-[9px] text-[#1D1D1F] bg-[#E5E5EA]/80 border-b border-[#E5E5EA] pr-2.5">Vol. Total</th>
                </template>
              </tr>
            </thead>

            <!-- TBODY -->
            <tbody class="divide-y divide-[#E5E5EA] bg-white font-medium">
              <tr v-if="rutasFiltradas.length === 0">
                <td :colspan="calcularTotalColumnas()" class="p-8 text-center text-[#86868B] font-medium">
                  No se encontraron rutas con los filtros aplicados.
                </td>
              </tr>

              <template v-for="(r, idx) in rutasFiltradas" :key="r.ruta">
                <!-- Fila de la Ruta -->
                <tr
                  class="hover:bg-[#F5F5F7] transition-colors cursor-pointer group"
                  @click="toggleAcordeon(r.ruta)"
                >
                  <!-- # -->
                  <td class="p-2 text-center text-[#86868B] text-[10px] border-r border-[#E5E5EA] bg-white group-hover:bg-[#F5F5F7] sticky left-0 z-20">
                    {{ idx + 1 }}
                  </td>

                  <!-- Chevron -->
                  <td class="p-1.5 text-center border-r border-[#E5E5EA] bg-white group-hover:bg-[#F5F5F7] sticky left-8 z-20">
                    <button
                      type="button"
                      class="p-1 rounded-[4px] hover:bg-black/[0.06] text-[#86868B] group-hover:text-[#1D1D1F] transition-all cursor-pointer"
                      @click.stop="toggleAcordeon(r.ruta)"
                    >
                      <svg
                        class="w-3.5 h-3.5 transition-transform duration-200"
                        :class="filasExpandidas.has(r.ruta) ? 'rotate-90 text-[#0071E3]' : ''"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                    </button>
                  </td>

                  <!-- Ruta -->
                  <td class="p-2.5 font-bold text-[#1D1D1F] border-r border-[#E5E5EA] bg-white group-hover:bg-[#F5F5F7] sticky left-16 z-20 whitespace-nowrap">
                    {{ r.ruta }}
                  </td>

                  <!-- Canal -->
                  <td class="p-2.5 border-r border-[#E5E5EA] whitespace-nowrap">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-[#F5F5F7] text-[#6E6E73] border border-[#E5E5EA]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Preventista -->
                  <td class="p-2.5 border-r-2 border-[#D1D1D6] text-[#1D1D1F] text-[11px] truncate max-w-[180px]">
                    {{ r.vendedor }}
                  </td>

                  <!-- Días Columnas -->
                  <template v-for="d in diasColumnas" :key="r.ruta + '_' + d.fecha">
                    <template v-if="vistaActiva === 'efectividad'">
                      <!-- Solo Efectividad -->
                      <template v-if="soloEfectividad">
                        <td class="p-2 text-center border-r-2 border-[#D1D1D6]">
                          <span
                            class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-bold"
                            :class="obtenerClaseEfectividad(r.dias[d.fecha]?.efectividad_pct)"
                          >
                            {{ fmtPct(r.dias[d.fecha]?.efectividad_pct) }}
                          </span>
                        </td>
                      </template>
                      <!-- Completo -->
                      <template v-else>
                        <td class="p-1.5 text-center text-[#1D1D1F] tabular-nums">{{ r.dias[d.fecha]?.plan ?? 0 }}</td>
                        <td class="p-1.5 text-center text-[#1D1D1F] tabular-nums">{{ r.dias[d.fecha]?.visitados ?? 0 }}</td>
                        <td class="p-1.5 text-center text-[#1D1D1F] tabular-nums font-semibold">{{ r.dias[d.fecha]?.con_preventa ?? 0 }}</td>
                        <td class="p-1.5 text-center border-r-2 border-[#D1D1D6]">
                          <span
                            class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-bold"
                            :class="obtenerClaseEfectividad(r.dias[d.fecha]?.efectividad_pct)"
                          >
                            {{ fmtPct(r.dias[d.fecha]?.efectividad_pct) }}
                          </span>
                        </td>
                      </template>
                    </template>

                    <!-- Volumen Mode -->
                    <template v-else>
                      <td class="p-2 text-center text-[#1D1D1F] font-semibold tabular-nums">
                        {{ r.dias[d.fecha]?.con_preventa ?? 0 }}
                      </td>
                      <td class="p-2 text-right font-bold text-[#1D1D1F] tabular-nums border-r-2 border-[#D1D1D6] pr-2.5">
                        {{ fmtBs(r.dias[d.fecha]?.volumen_bs) }}
                      </td>
                    </template>
                  </template>

                  <!-- Total Rango de la Fila -->
                  <template v-if="vistaActiva === 'efectividad'">
                    <template v-if="soloEfectividad">
                      <td class="p-2 text-center bg-[#FBFBFD] font-bold">
                        <span
                          class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                          :class="obtenerClaseEfectividad(r.efectividad_promedio_pct)"
                        >
                          {{ fmtPct(r.efectividad_promedio_pct) }}
                        </span>
                      </td>
                    </template>
                    <template v-else>
                      <td class="p-1.5 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD]">{{ fmt(r.tot_plan) }}</td>
                      <td class="p-1.5 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD]">{{ fmt(r.tot_visitados) }}</td>
                      <td class="p-1.5 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD]">{{ fmt(r.tot_preventa) }}</td>
                      <td class="p-1.5 text-center bg-[#FBFBFD]">
                        <span
                          class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                          :class="obtenerClaseEfectividad(r.efectividad_promedio_pct)"
                        >
                          {{ fmtPct(r.efectividad_promedio_pct) }}
                        </span>
                      </td>
                    </template>
                  </template>
                  <template v-else>
                    <td class="p-2 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD]">
                      {{ fmt(r.tot_preventa) }}
                    </td>
                    <td class="p-2 text-right font-bold text-[#0071E3] tabular-nums bg-[#FBFBFD] pr-2.5">
                      {{ fmtBs(r.tot_volumen_bs) }}
                    </td>
                  </template>
                </tr>

                <!-- FILA DE ACORDEÓN (DETALLE DE CLIENTES DE LA RUTA) -->
                <tr v-if="filasExpandidas.has(r.ruta)" class="bg-[#FBFBFD]">
                  <td :colspan="calcularTotalColumnas()" class="p-0 border-b border-[#E5E5EA]">
                    <div class="p-3 sm:p-4 bg-[#FBFBFD] border-t border-[#E5E5EA] space-y-3">
                      <!-- Loader del acordeón -->
                      <div v-if="cargandoDetalles[r.ruta]" class="py-6 text-center text-xs text-[#86868B] flex items-center justify-center gap-2">
                        <div class="w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                        <span>Consultando clientes de la ruta {{ r.ruta }} en el rango seleccionado...</span>
                      </div>

                      <!-- Error del acordeón -->
                      <div v-else-if="erroresDetalles[r.ruta]" class="p-3 bg-red-50 text-red-600 rounded-[8px] text-xs font-semibold">
                        {{ erroresDetalles[r.ruta] }}
                      </div>

                      <!-- Split 2 Paneles (Efectividad Válida >= 70 Bs vs Bajo Umbral < 70 Bs) -->
                      <div v-else-if="cacheDetalles[r.ruta]" class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <!-- Panel Izquierdo: Efectividad Válida (>= 70 Bs) -->
                        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-xs">
                          <div class="flex items-center justify-between pb-2 mb-2 border-b border-[#E5E5EA]">
                            <span class="font-bold text-[#1D1D1F] flex items-center gap-1.5">
                              <span class="w-2.5 h-2.5 rounded-full bg-[#248A3D]"></span>
                              Efectividad Comercial Válida (&ge; 70 Bs)
                            </span>
                            <span class="px-2 py-0.5 rounded-[4px] bg-[#EBF9EF] text-[#248A3D] font-bold font-mono text-[10px]">
                              {{ cacheDetalles[r.ruta].total_ok }} clientes
                            </span>
                          </div>

                          <div class="max-h-56 overflow-y-auto custom-scroll space-y-1.5 pr-1">
                            <div v-if="cacheDetalles[r.ruta].clientes_ok.length === 0" class="text-[#86868B] py-4 text-center text-xs">
                              Sin compras calificadas sobre el umbral
                            </div>
                            <div
                              v-for="c in cacheDetalles[r.ruta].clientes_ok"
                              :key="'ok_' + c.cliente_id + '_' + c.fecha"
                              class="flex items-center justify-between py-1 px-2 hover:bg-[#F5F5F7] rounded-[6px] border border-[#F5F5F7]"
                            >
                              <div class="truncate max-w-[240px]">
                                <span class="font-semibold text-[#1D1D1F] block truncate text-[11px]">
                                  {{ c.cliente_nombre }}
                                </span>
                                <span class="text-[9px] text-[#86868B] font-mono">{{ c.fecha }}</span>
                              </div>
                              <span class="font-bold text-[#248A3D] font-mono text-[11px]">
                                {{ fmtBs(c.total_bs) }}
                              </span>
                            </div>
                          </div>
                        </div>

                        <!-- Panel Derecho: Bajo Umbral (< 70 Bs) -->
                        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-xs">
                          <div class="flex items-center justify-between pb-2 mb-2 border-b border-[#E5E5EA]">
                            <span class="font-bold text-[#1D1D1F] flex items-center gap-1.5">
                              <span class="w-2.5 h-2.5 rounded-full bg-[#B25E00]"></span>
                              Compras Bajo Umbral (&lt; 70 Bs)
                            </span>
                            <span class="px-2 py-0.5 rounded-[4px] bg-[#FFF5E5] text-[#B25E00] font-bold font-mono text-[10px]">
                              {{ cacheDetalles[r.ruta].total_bajo }} clientes
                            </span>
                          </div>

                          <div class="max-h-56 overflow-y-auto custom-scroll space-y-1.5 pr-1">
                            <div v-if="cacheDetalles[r.ruta].clientes_bajo.length === 0" class="text-[#86868B] py-4 text-center text-xs">
                              Sin compras menores a 70 Bs
                            </div>
                            <div
                              v-for="c in cacheDetalles[r.ruta].clientes_bajo"
                              :key="'bajo_' + c.cliente_id + '_' + c.fecha"
                              class="flex items-center justify-between py-1 px-2 hover:bg-[#F5F5F7] rounded-[6px] border border-[#F5F5F7]"
                            >
                              <div class="truncate max-w-[220px]">
                                <span class="font-semibold text-[#1D1D1F] block truncate text-[11px]">
                                  {{ c.cliente_nombre }}
                                </span>
                                <div class="flex items-center gap-1.5 text-[9px]">
                                  <span class="text-[#86868B] font-mono">{{ c.fecha }}</span>
                                  <span class="text-[#C9342C] font-semibold">· Falta {{ fmtBs(c.brecha_70) }}</span>
                                </div>
                              </div>
                              <span class="font-semibold text-[#B25E00] font-mono text-[11px]">
                                {{ fmtBs(c.total_bs) }}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>

            <!-- TFOOT (TOTALES GLOBALES) -->
            <tfoot class="bg-[#FAFAFC] border-t-2 border-[#D1D1D6] font-bold text-[#1D1D1F] sticky bottom-0 z-20">
              <tr>
                <td colspan="5" class="p-2.5 text-right uppercase text-[10px] tracking-wider border-r-2 border-[#D1D1D6]">
                  Total General
                </td>

                <!-- Totales por Día -->
                <template v-for="d in diasColumnas" :key="'foot_' + d.fecha">
                  <template v-if="vistaActiva === 'efectividad'">
                    <template v-if="soloEfectividad">
                      <td class="p-2 text-center border-r-2 border-[#D1D1D6]">
                        <span
                          class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                          :class="obtenerClaseEfectividad(calcularTotalesDia(d.fecha).efectividad_pct)"
                        >
                          {{ fmtPct(calcularTotalesDia(d.fecha).efectividad_pct) }}
                        </span>
                      </td>
                    </template>
                    <template v-else>
                      <td class="p-1.5 text-center tabular-nums">{{ fmt(calcularTotalesDia(d.fecha).plan) }}</td>
                      <td class="p-1.5 text-center tabular-nums">{{ fmt(calcularTotalesDia(d.fecha).visitados) }}</td>
                      <td class="p-1.5 text-center tabular-nums">{{ fmt(calcularTotalesDia(d.fecha).con_preventa) }}</td>
                      <td class="p-1.5 text-center border-r-2 border-[#D1D1D6]">
                        <span
                          class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-bold"
                          :class="obtenerClaseEfectividad(calcularTotalesDia(d.fecha).efectividad_pct)"
                        >
                          {{ fmtPct(calcularTotalesDia(d.fecha).efectividad_pct) }}
                        </span>
                      </td>
                    </template>
                  </template>
                  <template v-else>
                    <td class="p-2 text-center tabular-nums font-bold">
                      {{ fmt(calcularTotalesDia(d.fecha).con_preventa) }}
                    </td>
                    <td class="p-2 text-right tabular-nums font-bold border-r-2 border-[#D1D1D6] pr-2.5">
                      {{ fmtBs(calcularTotalesDia(d.fecha).volumen_bs) }}
                    </td>
                  </template>
                </template>

                <!-- Total Rango General -->
                <template v-if="vistaActiva === 'efectividad'">
                  <template v-if="soloEfectividad">
                    <td class="p-2 text-center bg-[#EFEFF4] font-bold">
                      <span
                        class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                        :class="obtenerClaseEfectividad(kpisGlobales?.efectividad_global_pct)"
                      >
                        {{ fmtPct(kpisGlobales?.efectividad_global_pct) }}
                      </span>
                    </td>
                  </template>
                  <template v-else>
                    <td class="p-1.5 text-center tabular-nums bg-[#EFEFF4]">{{ fmt(kpisGlobales?.total_plan) }}</td>
                    <td class="p-1.5 text-center tabular-nums bg-[#EFEFF4]">{{ fmt(kpisGlobales?.total_visitados) }}</td>
                    <td class="p-1.5 text-center tabular-nums bg-[#EFEFF4]">{{ fmt(kpisGlobales?.total_preventa) }}</td>
                    <td class="p-1.5 text-center bg-[#EFEFF4]">
                      <span
                        class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-bold"
                        :class="obtenerClaseEfectividad(kpisGlobales?.efectividad_global_pct)"
                      >
                        {{ fmtPct(kpisGlobales?.efectividad_global_pct) }}
                      </span>
                    </td>
                  </template>
                </template>
                <template v-else>
                  <td class="p-2 text-center tabular-nums bg-[#EFEFF4] font-bold">
                    {{ fmt(kpisGlobales?.total_preventa) }}
                  </td>
                  <td class="p-2 text-right tabular-nums bg-[#EFEFF4] font-bold pr-2.5 text-[#0071E3]">
                    {{ fmtBs(kpisGlobales?.total_volumen_bs) }}
                  </td>
                </template>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SubNavTabs from '@/Components/UI/SubNavTabs.vue'
import AvanceFiltrosBar from './AvanceFiltrosBar.vue'
import { useAvanceStore } from '@/Stores/avanceStore'
import { navigationConfig } from '@/Config/navigation'

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  meses: { type: Array, default: () => [] },
  fecha_inicio_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
  mes_default: { type: String, default: '' },
  dias_calendario: { type: Object, default: () => ({}) },
})

const store = useAvanceStore()

// Pestañas del submódulo Avance
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance')
const avanceTabs = avanceMenu ? avanceMenu.submodules : []

// Estado local
const cargando = ref(false)
const errorMsg = ref(null)
const vistaActiva = ref('efectividad') // 'efectividad' o 'volumen'
const soloEfectividad = ref(false)

const diasColumnas = ref([])
const matrizOriginal = ref([])
const kpisGlobales = ref(null)
const rangoLabel = ref('')

// Acordeón y caché de clientes
const filasExpandidas = reactive(new Set())
const cacheDetalles = reactive({})
const cargandoDetalles = reactive({})
const erroresDetalles = reactive({})

// Formatters
const fmt = (v) => (Number(v) || 0).toLocaleString('es-BO')
const fmtBs = (v) => `Bs ${(Number(v) || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const fmtPct = (v) => `${(Number(v) || 0).toFixed(1)}%`

const obtenerClaseEfectividad = (pct) => {
  const val = Number(pct) || 0
  if (val >= 55) return 'text-[#248A3D] bg-[#EBF9EF]'
  if (val >= 35) return 'text-[#B25E00] bg-[#FFF5E5]'
  return 'text-[#C9342C] bg-[#FDF0EF]'
}

const calcularTotalColumnas = () => {
  const base = 5 // # + chevron + ruta + canal + preventista
  const numDias = diasColumnas.value.length
  let subPorDia = 1
  let subTotal = 1

  if (vistaActiva.value === 'efectividad') {
    subPorDia = soloEfectividad.value ? 1 : 4
    subTotal = soloEfectividad.value ? 1 : 4
  } else {
    subPorDia = 2
    subTotal = 2
  }

  return base + (numDias * subPorDia) + subTotal
}

// Filtro reactivo en cliente (canales, rutas seleccionadas, texto de búsqueda)
const rutasFiltradas = computed(() => {
  let list = matrizOriginal.value || []

  // Filtro por canales seleccionados
  const cSel = store.canalesSeleccionados || []
  if (cSel.length > 0 && !cSel.includes('TODOS')) {
    const setC = new Set(cSel.map(c => String(c).trim().toUpperCase()))
    list = list.filter(r => setC.has(String(r.canal || '').trim().toUpperCase()))
  }

  // Filtro por rutas marcadas
  const rSel = store.rutasSeleccionadas || []
  if (rSel.length > 0) {
    const setR = new Set(rSel.map(r => String(r).trim().toUpperCase()))
    list = list.filter(r => setR.has(String(r.ruta || '').trim().toUpperCase()))
  }

  // Filtro por texto de búsqueda
  const q = String(store.busqueda || '').trim().toUpperCase()
  if (q) {
    list = list.filter(r =>
      String(r.ruta || '').toUpperCase().includes(q) ||
      String(r.vendedor || '').toUpperCase().includes(q) ||
      String(r.canal || '').toUpperCase().includes(q)
    )
  }

  return list
})

// Totales de un día particular sumando las rutas filtradas
const calcularTotalesDia = (fecha) => {
  let plan = 0
  let visitados = 0
  let conPrev = 0
  let volBs = 0.0

  rutasFiltradas.value.forEach(r => {
    const d = r.dias[fecha]
    if (d) {
      plan += (d.plan || 0)
      visitados += (d.visitados || 0)
      conPrev += (d.con_preventa || 0)
      volBs += (d.volumen_bs || 0)
    }
  })

  const ef = plan > 0 ? (conPrev / plan) * 100 : 0.0

  return {
    plan,
    visitados,
    con_preventa: conPrev,
    volumen_bs: volBs,
    efectividad_pct: ef,
  }
}

// Consulta de datos al backend
const consultarHistorico = async () => {
  cargando.value = true
  errorMsg.value = null

  try {
    const res = await axios.post('/supervisor/avance/historico-preventas/data', {
      fecha_inicio: store.fechaInicio || props.fecha_inicio_default,
      fecha_corte: store.fechaCorte || props.fecha_corte_default,
      canales: store.canalesSeleccionados || [],
      rutas: store.rutasSeleccionadas || [],
    })

    const data = res.data
    diasColumnas.value = data.dias_columnas || []
    matrizOriginal.value = data.matriz || []
    kpisGlobales.value = data.kpis || null
    rangoLabel.value = data.rango_label || ''

    if (data.dias_calendario) {
      store.diasCalendario = data.dias_calendario
    }
    if (data.fecha_inicio) {
      store.fechaInicio = data.fecha_inicio
    }
    if (data.fecha_corte) {
      store.fechaCorte = data.fecha_corte
    }
  } catch (err) {
    console.error('[HistoricoPreventas] Error al cargar histórico:', err)
    errorMsg.value = err?.response?.data?.message || err?.message || 'Error al consultar matriz histórica de preventas'
  } finally {
    cargando.value = false
  }
}

// Toggle acordeón y consulta de desglose de clientes
const toggleAcordeon = async (rutaKey) => {
  if (filasExpandidas.has(rutaKey)) {
    filasExpandidas.delete(rutaKey)
    return
  }

  filasExpandidas.add(rutaKey)

  // Si ya está en caché, no volvemos a consultar
  const fIni = store.fechaInicio || props.fecha_inicio_default
  const fCorte = store.fechaCorte || props.fecha_corte_default
  const cacheKey = `${rutaKey}_${fIni}_${fCorte}`

  if (cacheDetalles[rutaKey] && cacheDetalles[rutaKey]._key === cacheKey) {
    return
  }

  cargandoDetalles[rutaKey] = true
  erroresDetalles[rutaKey] = null

  try {
    const res = await axios.post('/supervisor/avance/historico-preventas/detalle-ruta', {
      ruta: rutaKey,
      fecha_inicio: fIni,
      fecha_corte: fCorte,
    })

    cacheDetalles[rutaKey] = {
      ...res.data,
      _key: cacheKey,
    }
  } catch (err) {
    console.error(`[HistoricoPreventas] Error al consultar clientes de ${rutaKey}:`, err)
    erroresDetalles[rutaKey] = err?.response?.data?.message || 'No se pudo cargar el detalle de clientes'
  } finally {
    cargandoDetalles[rutaKey] = false
  }
}

onMounted(() => {
  store.inicializarDesdeStorage(props)
  consultarHistorico()
})
</script>

