<template>
  <SupervisorLayout title="Avance > Preventas Históricas">
    <div class="space-y-2.5 font-sans select-none">
      <!-- Subnavegación de Módulo Avance -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- Barra Superior de Filtros Transversales -->
      <AvanceFiltrosBar @consultar="consultarHistorico" />

      <!-- Barra de Control de Vistas y Umbrales (Compacta y Responsiva) -->
      <div class="apple-glass-card rounded-[14px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-xs border border-white/60">
        <!-- Título e Indicador de Modo -->
        <div class="flex items-center gap-2 min-w-0">
          <span
            class="w-2.5 h-2.5 rounded-full shrink-0"
            :class="vistaActiva === 'efectividad' ? 'bg-[#0071E3]' : 'bg-[#34C759]'"
          ></span>
          <div class="truncate">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase truncate">
                {{ vistaActiva === 'efectividad' ? 'Histórico de Disciplina & Efectividad' : 'Histórico de Volumen de Preventa (Bs)' }}
              </span>
              <span v-if="rangoLabel" class="text-[10px] font-mono text-[#0071E3] font-semibold bg-blue-50/80 px-2 py-0.5 rounded-full border border-blue-200/60 shrink-0">
                {{ rangoLabel }}
              </span>
              <span v-if="rutasFiltradas.length > 0" class="text-[10px] font-mono text-[#6E6E73] bg-black/[0.04] px-1.5 py-0.5 rounded shrink-0">
                {{ rutasFiltradas.length }} rutas
              </span>
            </div>
            <p class="text-[10px] text-[#86868B] font-medium hidden sm:block truncate">
              Umbral comercial de efectividad: compras acumuladas &ge; 70 Bs/día por cliente
            </p>
          </div>
        </div>

        <!-- Acciones y Selector de Vista -->
        <div class="flex items-center gap-1.5 sm:gap-2 ml-auto shrink-0">
          <!-- Toggle Solo Efectividad (Solo visible en vista de efectividad) -->
          <button
            v-if="vistaActiva === 'efectividad'"
            type="button"
            @click="soloEfectividad = !soloEfectividad"
            class="px-2.5 py-1 text-[11px] font-semibold rounded-[8px] transition-all cursor-pointer shadow-xs whitespace-nowrap"
            :class="soloEfectividad
              ? 'bg-[#1D1D1F] text-white shadow-sm'
              : 'border border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F5F5F7]'"
          >
            {{ soloEfectividad ? 'Ver Columnas Completas' : 'Solo % Efectividad' }}
          </button>

          <!-- Segmented View Pill -->
          <div class="inline-flex bg-[#E5E5EA]/70 p-0.5 rounded-[9px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="vistaActiva = 'efectividad'"
              class="px-2.5 sm:px-3 py-1 text-[11px] font-bold rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="vistaActiva === 'efectividad'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] font-medium'"
            >
              Disciplina & Efectividad
            </button>
            <button
              type="button"
              @click="vistaActiva = 'volumen'"
              class="px-2.5 sm:px-3 py-1 text-[11px] font-bold rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="vistaActiva === 'volumen'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] font-medium'"
            >
              Volumen (Bs)
            </button>
          </div>
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

      <!-- TABLA PRINCIPAL DE MATRIZ HISTÓRICA (RESPONSIVA) -->
      <div
        v-else
        class="apple-glass-card rounded-[14px] shadow-float overflow-hidden border border-white/60"
      >
        <div class="overflow-x-auto custom-scroll max-h-[calc(100dvh-12rem)] sm:max-h-[calc(100dvh-11.5rem)] overflow-y-auto">
          <table class="w-full text-left text-xs border-collapse font-num">
            <!-- THEAD MULTI-NIVEL (CON STICKY VERTICAL Y SIN COLISIONES HORIZONTALES) -->
            <thead class="bg-[#F5F5F7] text-[#1D1D1F] font-bold uppercase text-[10px] tracking-tight sticky top-0 z-20 shadow-[0_1px_3px_rgba(0,0,0,0.06)]">
              <!-- Nivel 1: Cabeceras de Ruta y Días del Rango -->
              <tr class="border-b border-[#E5E5EA]">
                <th rowspan="2" class="py-2.5 px-3 border-r border-[#E5E5EA] min-w-[130px] whitespace-nowrap bg-[#F5F5F7]">
                  Ruta
                </th>
                <th rowspan="2" class="py-2.5 px-2.5 border-r border-[#E5E5EA] min-w-[65px] text-center whitespace-nowrap bg-[#F5F5F7]">
                  Canal
                </th>
                <th rowspan="2" class="py-2.5 px-3 border-r-2 border-[#D1D1D6] min-w-[150px] whitespace-nowrap bg-[#F5F5F7]">
                  Preventista
                </th>

                <!-- Días del Rango -->
                <template v-for="d in diasColumnas" :key="'col_dia_' + d.fecha">
                  <th
                    :colspan="vistaActiva === 'efectividad' ? (soloEfectividad ? 1 : 4) : 2"
                    class="py-2 px-2 text-center border-r-2 border-[#D1D1D6] bg-[#EFEFF4]/70 font-bold whitespace-nowrap"
                  >
                    {{ d.dia_label }}
                  </th>
                </template>

                <!-- Total Rango General -->
                <th
                  :colspan="vistaActiva === 'efectividad' ? (soloEfectividad ? 1 : 4) : 2"
                  class="py-2 px-2 text-center bg-[#E5E5EA] text-[#1D1D1F] font-bold whitespace-nowrap"
                >
                  Total Rango
                </th>
              </tr>

              <!-- Nivel 2: Sub-columnas Métricas de cada Día -->
              <tr class="border-b border-[#E5E5EA]">
                <template v-for="d in diasColumnas" :key="'sub_' + d.fecha">
                  <!-- Efectividad Mode -->
                  <template v-if="vistaActiva === 'efectividad'">
                    <template v-if="soloEfectividad">
                      <th class="py-1 px-2 text-center text-[9px] text-[#6E6E73] border-r-2 border-[#D1D1D6] min-w-[70px]">
                        Efect.
                      </th>
                    </template>
                    <template v-else>
                      <th class="py-1 px-1.5 text-center text-[9px] text-[#6E6E73] min-w-[42px]">Plan</th>
                      <th class="py-1 px-1.5 text-center text-[9px] text-[#6E6E73] min-w-[42px]">Vis.</th>
                      <th class="py-1 px-1.5 text-center text-[9px] text-[#6E6E73] min-w-[42px]">Prev.</th>
                      <th class="py-1 px-2 text-center text-[9px] text-[#6E6E73] border-r-2 border-[#D1D1D6] min-w-[62px]">Efect.</th>
                    </template>
                  </template>

                  <!-- Volumen Mode -->
                  <template v-else>
                    <th class="py-1 px-2 text-center text-[9px] text-[#6E6E73] min-w-[65px]">Prev &ge;70</th>
                    <th class="py-1 px-2.5 text-right text-[9px] text-[#6E6E73] border-r-2 border-[#D1D1D6] min-w-[90px]">Vol. Total</th>
                  </template>
                </template>

                <!-- Sub-columnas Total Rango -->
                <template v-if="vistaActiva === 'efectividad'">
                  <template v-if="soloEfectividad">
                    <th class="py-1 px-2 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[75px]">Efect.</th>
                  </template>
                  <template v-else>
                    <th class="py-1 px-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[45px]">Plan</th>
                    <th class="py-1 px-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[45px]">Vis.</th>
                    <th class="py-1 px-1.5 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[45px]">Prev.</th>
                    <th class="py-1 px-2 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[65px]">Efect.</th>
                  </template>
                </template>
                <template v-else>
                  <th class="py-1 px-2 text-center text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[65px]">Prev &ge;70</th>
                  <th class="py-1 px-2.5 text-right text-[9px] text-[#1D1D1F] bg-[#E5E5EA] min-w-[95px]">Vol. Total</th>
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
                <!-- Fila Principal de la Ruta -->
                <tr
                  class="hover:bg-[#F5F5F7] transition-colors cursor-pointer group"
                  @click="toggleAcordeon(r.ruta)"
                >
                  <!-- Ruta (con índice y chevron interactivo) -->
                  <td class="py-2 px-3 border-r border-[#E5E5EA] whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                      <span class="text-[10px] text-[#86868B] font-mono w-4 text-center">{{ idx + 1 }}</span>
                      <button
                        type="button"
                        class="p-0.5 rounded hover:bg-black/[0.08] text-[#86868B] group-hover:text-[#1D1D1F] transition-all cursor-pointer"
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
                      <span class="font-bold text-[#1D1D1F]">{{ r.ruta }}</span>
                    </div>
                  </td>

                  <!-- Canal -->
                  <td class="py-2 px-2.5 text-center border-r border-[#E5E5EA] whitespace-nowrap">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-[#F5F5F7] text-[#6E6E73] border border-[#E5E5EA]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Preventista -->
                  <td class="py-2 px-3 border-r-2 border-[#D1D1D6] text-[#1D1D1F] text-[11px] truncate max-w-[180px] whitespace-nowrap">
                    {{ r.vendedor }}
                  </td>

                  <!-- Días Columnas -->
                  <template v-for="d in diasColumnas" :key="r.ruta + '_' + d.fecha">
                    <template v-if="vistaActiva === 'efectividad'">
                      <!-- Solo Efectividad -->
                      <template v-if="soloEfectividad">
                        <td class="py-2 px-2 text-center border-r-2 border-[#D1D1D6] whitespace-nowrap">
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
                        <td class="py-1.5 px-1.5 text-center text-[#1D1D1F] tabular-nums whitespace-nowrap">{{ r.dias[d.fecha]?.plan ?? 0 }}</td>
                        <td class="py-1.5 px-1.5 text-center text-[#1D1D1F] tabular-nums whitespace-nowrap">{{ r.dias[d.fecha]?.visitados ?? 0 }}</td>
                        <td class="py-1.5 px-1.5 text-center text-[#1D1D1F] tabular-nums font-semibold whitespace-nowrap">{{ r.dias[d.fecha]?.con_preventa ?? 0 }}</td>
                        <td class="py-1.5 px-2 text-center border-r-2 border-[#D1D1D6] whitespace-nowrap">
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
                      <td class="py-2 px-2 text-center text-[#1D1D1F] font-semibold tabular-nums whitespace-nowrap">
                        {{ r.dias[d.fecha]?.con_preventa ?? 0 }}
                      </td>
                      <td class="py-2 px-2.5 text-right font-bold text-[#1D1D1F] tabular-nums border-r-2 border-[#D1D1D6] whitespace-nowrap">
                        {{ fmtBs(r.dias[d.fecha]?.volumen_bs) }}
                      </td>
                    </template>
                  </template>

                  <!-- Total Rango de la Fila -->
                  <template v-if="vistaActiva === 'efectividad'">
                    <template v-if="soloEfectividad">
                      <td class="py-2 px-2 text-center bg-[#FBFBFD] font-bold whitespace-nowrap">
                        <span
                          class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                          :class="obtenerClaseEfectividad(r.efectividad_promedio_pct)"
                        >
                          {{ fmtPct(r.efectividad_promedio_pct) }}
                        </span>
                      </td>
                    </template>
                    <template v-else>
                      <td class="py-1.5 px-1.5 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD] whitespace-nowrap">{{ fmt(r.tot_plan) }}</td>
                      <td class="py-1.5 px-1.5 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD] whitespace-nowrap">{{ fmt(r.tot_visitados) }}</td>
                      <td class="py-1.5 px-1.5 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD] whitespace-nowrap">{{ fmt(r.tot_preventa) }}</td>
                      <td class="py-1.5 px-2 text-center bg-[#FBFBFD] whitespace-nowrap">
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
                    <td class="py-2 px-2 text-center font-bold text-[#1D1D1F] tabular-nums bg-[#FBFBFD] whitespace-nowrap">
                      {{ fmt(r.tot_preventa) }}
                    </td>
                    <td class="py-2 px-2.5 text-right font-bold text-[#0071E3] tabular-nums bg-[#FBFBFD] whitespace-nowrap">
                      {{ fmtBs(r.tot_volumen_bs) }}
                    </td>
                  </template>
                </tr>

                <!-- FILA DE ACORDEÓN (DETALLE DE CLIENTES DE LA RUTA - PINNED STICKY EN HORIZONTAL) -->
                <tr v-if="filasExpandidas.has(r.ruta)" class="bg-[#FBFBFD] border-b border-[#E5E5EA]">
                  <td :colspan="calcularTotalColumnas()" class="p-0">
                    <div class="sticky left-0 max-w-[calc(100vw-2rem)] md:max-w-4xl p-3 sm:p-4 space-y-3">
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
                              Efectividad Válida (&ge; 70 Bs)
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

            <!-- TFOOT (TOTALES GLOBALES RESPONSIVOS) -->
            <tfoot class="bg-[#FAFAFC] border-t-2 border-[#D1D1D6] font-bold text-[#1D1D1F] sticky bottom-0 z-20 shadow-[0_-1px_3px_rgba(0,0,0,0.06)]">
              <tr>
                <td colspan="3" class="py-2.5 px-3 text-right uppercase text-[10px] tracking-wider border-r-2 border-[#D1D1D6] whitespace-nowrap bg-[#FAFAFC]">
                  Total General
                </td>

                <!-- Totales por Día -->
                <template v-for="d in diasColumnas" :key="'foot_' + d.fecha">
                  <template v-if="vistaActiva === 'efectividad'">
                    <template v-if="soloEfectividad">
                      <td class="py-2 px-2 text-center border-r-2 border-[#D1D1D6] whitespace-nowrap bg-[#FAFAFC]">
                        <span
                          class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                          :class="obtenerClaseEfectividad(calcularTotalesDia(d.fecha).efectividad_pct)"
                        >
                          {{ fmtPct(calcularTotalesDia(d.fecha).efectividad_pct) }}
                        </span>
                      </td>
                    </template>
                    <template v-else>
                      <td class="py-1.5 px-1.5 text-center tabular-nums whitespace-nowrap bg-[#FAFAFC]">{{ fmt(calcularTotalesDia(d.fecha).plan) }}</td>
                      <td class="py-1.5 px-1.5 text-center tabular-nums whitespace-nowrap bg-[#FAFAFC]">{{ fmt(calcularTotalesDia(d.fecha).visitados) }}</td>
                      <td class="py-1.5 px-1.5 text-center tabular-nums whitespace-nowrap bg-[#FAFAFC]">{{ fmt(calcularTotalesDia(d.fecha).con_preventa) }}</td>
                      <td class="py-1.5 px-2 text-center border-r-2 border-[#D1D1D6] whitespace-nowrap bg-[#FAFAFC]">
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
                    <td class="py-2 px-2 text-center tabular-nums font-bold whitespace-nowrap bg-[#FAFAFC]">
                      {{ fmt(calcularTotalesDia(d.fecha).con_preventa) }}
                    </td>
                    <td class="py-2 px-2.5 text-right tabular-nums font-bold border-r-2 border-[#D1D1D6] whitespace-nowrap bg-[#FAFAFC]">
                      {{ fmtBs(calcularTotalesDia(d.fecha).volumen_bs) }}
                    </td>
                  </template>
                </template>

                <!-- Total Rango General -->
                <template v-if="vistaActiva === 'efectividad'">
                  <template v-if="soloEfectividad">
                    <td class="py-2 px-2 text-center bg-[#EFEFF4] font-bold whitespace-nowrap">
                      <span
                        class="px-2 py-0.5 rounded-[4px] text-[10px] font-bold"
                        :class="obtenerClaseEfectividad(totalesGlobalesFiltrados.efectividad_pct)"
                      >
                        {{ fmtPct(totalesGlobalesFiltrados.efectividad_pct) }}
                      </span>
                    </td>
                  </template>
                  <template v-else>
                    <td class="py-1.5 px-1.5 text-center tabular-nums bg-[#EFEFF4] whitespace-nowrap">{{ fmt(totalesGlobalesFiltrados.plan) }}</td>
                    <td class="py-1.5 px-1.5 text-center tabular-nums bg-[#EFEFF4] whitespace-nowrap">{{ fmt(totalesGlobalesFiltrados.visitados) }}</td>
                    <td class="py-1.5 px-1.5 text-center tabular-nums bg-[#EFEFF4] whitespace-nowrap">{{ fmt(totalesGlobalesFiltrados.con_preventa) }}</td>
                    <td class="py-1.5 px-2 text-center bg-[#EFEFF4] whitespace-nowrap">
                      <span
                        class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-bold"
                        :class="obtenerClaseEfectividad(totalesGlobalesFiltrados.efectividad_pct)"
                      >
                        {{ fmtPct(totalesGlobalesFiltrados.efectividad_pct) }}
                      </span>
                    </td>
                  </template>
                </template>
                <template v-else>
                  <td class="py-2 px-2 text-center tabular-nums bg-[#EFEFF4] font-bold whitespace-nowrap">
                    {{ fmt(totalesGlobalesFiltrados.con_preventa) }}
                  </td>
                  <td class="py-2 px-2.5 text-right tabular-nums bg-[#EFEFF4] font-bold text-[#0071E3] whitespace-nowrap">
                    {{ fmtBs(totalesGlobalesFiltrados.volumen_bs) }}
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
  const base = 3 // Ruta + Canal + Preventista
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

// Totales globales sumando todas las rutas filtradas en el rango completo
const totalesGlobalesFiltrados = computed(() => {
  let plan = 0
  let visitados = 0
  let conPrev = 0
  let volBs = 0.0

  rutasFiltradas.value.forEach(r => {
    plan += (r.tot_plan || 0)
    visitados += (r.tot_visitados || 0)
    conPrev += (r.tot_preventa || 0)
    volBs += (r.tot_volumen_bs || 0)
  })

  const ef = plan > 0 ? (conPrev / plan) * 100 : 0.0

  return {
    plan,
    visitados,
    con_preventa: conPrev,
    volumen_bs: volBs,
    efectividad_pct: ef,
  }
})

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
