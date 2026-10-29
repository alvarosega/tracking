<template>
  <SupervisorLayout title="Avance > Horarios e Itinerario">
    <div class="space-y-2.5 font-sans select-none text-[#1D1D1F]">
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 1. Subnavegación Coherente del Módulo Avance                      -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 2. Barra Superior de Filtros Transversales (Coherente con Avance) -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <AvanceFiltrosBar @consultar="consultarMatriz" />

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 3. Selector de Submódulos: Horarios vs Itinerario GIS           -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="apple-glass-card rounded-[14px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-xs border border-white/60">
        <div class="flex items-center gap-2 min-w-0">
          <span
            class="w-2.5 h-2.5 rounded-full shrink-0"
            :class="submodo === 'horarios' ? 'bg-[#0071E3]' : 'bg-[#34C759]'"
          ></span>
          <div class="truncate">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase truncate">
                {{ submodo === 'horarios' ? 'Matriz & Desglose Histórico de Horarios' : 'Secuencia de Itinerario & Mapa GIS 1..N' }}
              </span>
              <span v-if="rangoTexto" class="text-[10px] font-mono text-[#0071E3] font-semibold bg-blue-50/80 px-2 py-0.5 rounded-full border border-blue-200/60 shrink-0">
                {{ rangoTexto }}
              </span>
              <span v-if="submodo === 'horarios' && rutasFiltradas.length > 0" class="text-[10px] font-mono text-[#6E6E73] bg-black/[0.04] px-1.5 py-0.5 rounded shrink-0">
                {{ rutasFiltradas.length }} rutas visibles
              </span>
            </div>
            <p class="text-[10px] text-[#86868B] font-medium hidden sm:block truncate">
              {{ submodo === 'horarios'
                ? 'Cruce comparativo de primer ingreso y salida (Web vs Móvil) con desglose cronológico por ruta'
                : 'Auditoría secuencial de clientes programados en pan_ruteo con trazado geográfico continuo' }}
            </p>
          </div>
        </div>

        <!-- Segmented Control Apple Glass -->
        <div class="flex items-center gap-1.5 sm:gap-2 ml-auto shrink-0">
          <button
            type="button"
            v-if="submodo === 'horarios'"
            @click="exportarMatrizCsv"
            :disabled="cargandoMatriz || rutasFiltradas.length === 0"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-[8px] border border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F5F5F7] transition-all cursor-pointer shadow-xs disabled:opacity-50"
            title="Exportar matriz completa a CSV"
          >
            <svg class="w-3.5 h-3.5 text-[#1D1D1F]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span>Exportar Matriz</span>
          </button>

          <div class="inline-flex bg-[#E5E5EA]/70 p-0.5 rounded-[9px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="cambiarSubmodo('horarios')"
              class="px-2.5 sm:px-3 py-1 text-[11px] font-bold rounded-[7px] transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
              :class="submodo === 'horarios'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] font-medium'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Matriz de Horarios</span>
            </button>
            <button
              type="button"
              @click="cambiarSubmodo('itinerario')"
              class="px-2.5 sm:px-3 py-1 text-[11px] font-bold rounded-[7px] transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
              :class="submodo === 'itinerario'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] font-medium'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
              </svg>
              <span>Itinerario & Mapa 1..N</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- SUBMODO 1: MATRIZ DE HORARIOS Y DESGLOSE HISTÓRICO              -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <template v-if="submodo === 'horarios'">
        <section class="apple-glass-card rounded-[14px] shadow-float overflow-hidden border border-white/60">
          <!-- Barra Superior de la Matriz -->
          <div class="px-3.5 py-2.5 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span class="font-bold text-[#1D1D1F] uppercase tracking-wider text-[11px]">
                Jornada Laboral: Web ERP (Check-in/Out) vs App Android (Visitas)
              </span>
            </div>

            <div class="flex items-center gap-3 text-[10px]">
              <div class="flex items-center gap-2 text-[#6E6E73]">
                <span class="inline-flex items-center gap-1 font-semibold text-[#0071E3]">
                  <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span> Web: Ingreso - Salida
                </span>
                <span class="text-[#D1D1D6]">|</span>
                <span class="inline-flex items-center gap-1 font-semibold text-[#34C759]">
                  <span class="w-2 h-2 rounded-full bg-[#34C759]"></span> App: Ingreso - Salida
                </span>
              </div>
              <span class="text-[#6E6E73] font-mono font-bold text-[11px]">
                {{ rutasFiltradas.length }} rutas
              </span>
            </div>
          </div>

          <!-- Tabla Matriz con Acordeón de Desglose Integrado -->
          <div class="overflow-x-auto max-h-[660px] custom-scroll relative">
            <table class="w-full text-left text-[11px] text-[#1D1D1F] border-separate border-spacing-0 font-num">
              <thead class="bg-[#F5F5F7] text-[#1D1D1F] uppercase text-[10px] font-bold sticky top-0 z-20 border-b border-[#E5E5EA]">
                <tr>
                  <th
                    @click="toggleSortMatriz('ruta')"
                    class="p-2.5 sticky left-0 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA] min-w-[105px] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    title="Ordenar por Ruta (clic para expandir desglose)"
                  >
                    <div class="flex items-center gap-1">
                      <span>Ruta</span>
                      <span v-if="sortMatrizCol === 'ruta'" class="text-[#0071E3] font-bold">{{ sortMatrizDir === 'asc' ? '↑' : '↓' }}</span>
                    </div>
                  </th>
                  <th
                    @click="toggleSortMatriz('vendedor')"
                    class="p-2.5 sticky left-[105px] z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA] min-w-[160px] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    title="Ordenar por Preventista"
                  >
                    <div class="flex items-center gap-1">
                      <span>Preventista</span>
                      <span v-if="sortMatrizCol === 'vendedor'" class="text-[#0071E3] font-bold">{{ sortMatrizDir === 'asc' ? '↑' : '↓' }}</span>
                    </div>
                  </th>
                  <th
                    @click="toggleSortMatriz('canal')"
                    class="p-2.5 border-b border-r border-[#E5E5EA] text-center min-w-[75px] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                    title="Ordenar por Canal"
                  >
                    <div class="flex items-center justify-center gap-1">
                      <span>Canal</span>
                      <span v-if="sortMatrizCol === 'canal'" class="text-[#0071E3] font-bold">{{ sortMatrizDir === 'asc' ? '↑' : '↓' }}</span>
                    </div>
                  </th>
                  <th
                    v-for="f in fechasRango"
                    :key="f"
                    class="p-2 border-b border-r border-[#E5E5EA] text-center min-w-[145px]"
                  >
                    <div class="font-bold text-[#1D1D1F]">{{ getDiaNombreCorto(f) }}</div>
                    <div class="text-[9px] text-[#6E6E73] font-mono font-medium">{{ formatoFechaCorta(f) }}</div>
                  </th>
                </tr>
              </thead>

              <tbody class="divide-y divide-[#E5E5EA] bg-white text-[11px]">
                <tr v-if="cargandoMatriz">
                  <td :colspan="3 + fechasRango.length" class="p-12 text-center text-[#86868B] font-medium">
                    <div class="flex items-center justify-center gap-2">
                      <svg class="w-4 h-4 animate-spin text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                      </svg>
                      <span>Cargando matriz de horarios y cruce de eventos...</span>
                    </div>
                  </td>
                </tr>

                <tr v-else-if="rutasFiltradas.length === 0">
                  <td :colspan="3 + fechasRango.length" class="p-12 text-center text-[#86868B] font-medium">
                    No se encontraron rutas con los filtros seleccionados.
                  </td>
                </tr>

                <template v-else v-for="r in rutasFiltradas" :key="r.ruta">
                  <!-- Fila Principal de la Ruta -->
                  <tr
                    @click="toggleRutaAcordeon(r.ruta)"
                    class="cursor-pointer transition-colors"
                    :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-slate-50/70'"
                  >
                    <!-- Celda Fija 1: Ruta con Chevron Expandible -->
                    <td class="p-2.5 font-bold font-mono sticky left-0 z-10 border-r border-[#E5E5EA]" :class="acordeonAbierto[r.ruta] ? 'bg-blue-50 text-[#0071E3]' : 'bg-white text-[#0071E3]'">
                      <div class="flex items-center gap-1.5">
                        <svg
                          class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                          :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>{{ r.ruta }}</span>
                      </div>
                    </td>

                    <!-- Celda Fija 2: Preventista -->
                    <td
                      class="p-2.5 font-medium truncate max-w-[160px] sticky left-[105px] z-10 border-r border-[#E5E5EA]"
                      :class="acordeonAbierto[r.ruta] ? 'bg-blue-50 text-[#1D1D1F]' : 'bg-white text-[#1D1D1F]'"
                      :title="r.vendedor"
                    >
                      {{ r.vendedor }}
                    </td>

                    <!-- Canal -->
                    <td class="p-2.5 text-center font-bold text-[10px] text-[#6E6E73] border-r border-[#E5E5EA]">
                      <span class="px-1.5 py-0.5 rounded bg-[#F5F5F7] border border-[#E5E5EA]">
                        {{ r.canal }}
                      </span>
                    </td>

                    <!-- Celdas por Día (Web & App) -->
                    <td
                      v-for="f in fechasRango"
                      :key="f"
                      @click.stop="abrirTimelineModal(r.ruta, f, r.vendedor)"
                      class="p-2 border-r border-[#E5E5EA] cursor-pointer hover:bg-blue-50/60 transition-colors text-center group"
                      :title="`Ver eventos de ${r.ruta} el ${f} (clic para timeline modal)`"
                    >
                      <div
                        v-if="r.dias && r.dias[f] && r.dias[f].tiene_actividad"
                        class="p-1 rounded-[6px] transition-all group-hover:shadow-xs group-hover:bg-white"
                        :class="r.dias[f].eventos_movil > 0 ? 'bg-slate-50/80 border border-black/[0.04]' : 'bg-slate-50/40'"
                      >
                        <!-- Web -->
                        <div class="flex items-center justify-between text-[10px] leading-tight font-mono">
                          <span class="text-[9px] font-bold text-[#0071E3] uppercase">Web</span>
                          <span class="font-semibold text-[#1D1D1F]">
                            {{ formatoHoraCorta(r.dias[f].ingreso_sistema) }} - {{ formatoHoraCorta(r.dias[f].salida_sistema) }}
                          </span>
                        </div>

                        <!-- App -->
                        <div class="flex items-center justify-between text-[10px] leading-tight font-mono mt-1 pt-1 border-t border-black/[0.04]">
                          <span class="text-[9px] font-bold text-[#34C759] uppercase flex items-center gap-0.5">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            App
                          </span>
                          <span class="font-semibold text-[#1D1D1F]">
                            {{ formatoHoraCorta(r.dias[f].ingreso_movil) }} - {{ formatoHoraCorta(r.dias[f].salida_movil) }}
                          </span>
                        </div>
                      </div>

                      <div v-else class="text-center text-[#C7C7CC] font-mono text-[11px] py-1">
                        -
                      </div>
                    </td>
                  </tr>

                  <!-- ═════════════════════════════════════════════════════════ -->
                  <!-- Fila Acordeón: Desglose Histórico Completo de la Ruta    -->
                  <!-- ═════════════════════════════════════════════════════════ -->
                  <tr v-if="acordeonAbierto[r.ruta]">
                    <td :colspan="3 + fechasRango.length" class="p-3 bg-slate-50/90 border-b border-[#E5E5EA]">
                      <!-- Cargando Desglose -->
                      <div v-if="cargandoDetalleRuta[r.ruta]" class="p-6 text-center text-[#6E6E73] text-xs font-medium">
                        <div class="flex items-center justify-center gap-2">
                          <svg class="w-4 h-4 animate-spin text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                          </svg>
                          <span>Consultando histórico total de ingresos y visitas para la ruta {{ r.ruta }}...</span>
                        </div>
                      </div>

                      <!-- Error al cargar -->
                      <div v-else-if="errorDetalleRuta[r.ruta]" class="p-4 text-center text-[#C9342C] text-xs font-semibold">
                        {{ errorDetalleRuta[r.ruta] }}
                      </div>

                      <!-- Desglose Cargado Exitosamente -->
                      <div v-else-if="detallePorRuta[r.ruta]" class="apple-glass-card rounded-[14px] overflow-hidden border border-white/80 shadow-md bg-white">
                        <!-- Barra de Resumen de la Ruta -->
                        <div class="px-4 py-3 bg-[#F8F8FA] border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2.5 text-xs">
                          <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-[#1D1D1F] text-[13px]">
                              Auditoría de Ingresos: {{ r.ruta }} ({{ r.vendedor }})
                            </span>
                            <span class="px-2 py-0.5 rounded-[6px] bg-blue-50 text-[#0071E3] font-mono text-[10px] font-bold border border-blue-200">
                              Canal: {{ r.canal }}
                            </span>
                            <span class="text-[10px] font-mono text-[#6E6E73] bg-black/[0.04] px-2 py-0.5 rounded-[6px]">
                              {{ store.fechaInicio }} ➔ {{ store.fechaCorte }}
                            </span>
                          </div>

                          <!-- Métricas Clave del Desglose -->
                          <div class="flex items-center gap-1.5 flex-wrap text-[10px]">
                            <span class="apple-control-pill px-2.5 py-0.5 rounded-[6px] font-semibold text-[#1D1D1F]">
                              Total Visitas: <strong class="font-mono text-[#0071E3] font-bold">{{ detallePorRuta[r.ruta].total_eventos || 0 }}</strong>
                            </span>
                            <span class="apple-control-pill px-2.5 py-0.5 rounded-[6px] font-semibold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]">
                              Con Preventa: <strong class="font-mono font-bold">{{ detallePorRuta[r.ruta].total_preventas || 0 }}</strong>
                            </span>
                            <span class="apple-control-pill px-2.5 py-0.5 rounded-[6px] font-semibold bg-blue-50 text-[#0071E3] border border-blue-200">
                              Total Facturado: <strong class="font-mono font-bold">Bs {{ Number(detallePorRuta[r.ruta].monto_total || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
                            </span>
                            <button
                              type="button"
                              @click="exportarDesgloseCsv(r.ruta)"
                              class="px-2 py-0.5 rounded-[6px] bg-white border border-[#E5E5EA] text-[#1D1D1F] font-bold hover:bg-slate-50 transition-colors shadow-2xs cursor-pointer ml-1"
                              title="Exportar este desglose a CSV"
                            >
                              CSV
                            </button>
                          </div>
                        </div>

                        <!-- Tira Horizontal de Días (Histórico de Jornadas) -->
                        <div class="p-3 bg-[#F5F5F7]/60 border-b border-black/[0.04]">
                          <div class="text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] mb-1.5 flex items-center justify-between">
                            <span>Resumen por Jornada Diaria (Clic para filtrar tabla):</span>
                            <button
                              v-if="getSubtablaFiltroDia(r.ruta) !== 'todos'"
                              type="button"
                              @click="setSubtablaFiltroDia(r.ruta, 'todos')"
                              class="text-[#0071E3] font-bold hover:underline cursor-pointer normal-case"
                            >
                              Mostrar todos los días ({{ detallePorRuta[r.ruta].total_eventos || 0 }})
                            </button>
                          </div>

                          <div class="flex items-center gap-2 overflow-x-auto custom-scroll pb-1">
                            <div
                              v-for="d in (detallePorRuta[r.ruta].resumen_dias || [])"
                              :key="d.fecha"
                              @click="setSubtablaFiltroDia(r.ruta, d.fecha)"
                              class="p-2 rounded-[10px] border transition-all cursor-pointer min-w-[155px] shrink-0 select-none"
                              :class="getSubtablaFiltroDia(r.ruta) === d.fecha
                                ? 'bg-white border-[#0071E3] shadow-md ring-2 ring-[#0071E3]/20'
                                : (d.tiene_actividad ? 'bg-white border-[#E5E5EA] hover:border-[#0071E3]/50 hover:shadow-xs' : 'bg-slate-50/50 border-black/[0.04] opacity-60')"
                            >
                              <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-[11px] text-[#1D1D1F]">{{ d.dia_nombre }}</span>
                                <span class="font-mono text-[10px] text-[#86868B]">{{ d.fecha_corta }}</span>
                              </div>
                              <div class="space-y-0.5 text-[9.5px] font-mono">
                                <div class="flex justify-between text-[#0071E3]">
                                  <span>Web:</span>
                                  <span class="font-bold">{{ formatoHoraCorta(d.primer_ingreso_web) }} - {{ formatoHoraCorta(d.ultimo_egreso_web) }}</span>
                                </div>
                                <div class="flex justify-between text-[#34C759]">
                                  <span>App:</span>
                                  <span class="font-bold">{{ formatoHoraCorta(d.primer_ingreso_app) }} - {{ formatoHoraCorta(d.ultimo_egreso_app) }}</span>
                                </div>
                              </div>
                              <div class="mt-1 pt-1 border-t border-black/[0.04] flex items-center justify-between text-[9px] text-[#6E6E73]">
                                <span>{{ d.total_visitas }} visitas</span>
                                <span v-if="d.total_preventas > 0" class="font-bold text-[#1E6B37]">{{ d.total_preventas }} prev.</span>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Barra de Filtros y Búsqueda de la Subtabla -->
                        <div class="px-3.5 py-2 bg-[#F8F8FA] border-b border-black/[0.04] flex flex-wrap items-center justify-between gap-2 text-[10px]">
                          <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-bold text-[#6E6E73] uppercase text-[9px]">Filtrar por:</span>
                            <!-- Botón Todos -->
                            <button
                              type="button"
                              @click="setSubtablaFiltroTipo(r.ruta, 'todos')"
                              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer"
                              :class="getSubtablaFiltroTipo(r.ruta) === 'todos' ? 'bg-[#0071E3] text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                            >
                              Todos ({{ (detallePorRuta[r.ruta].eventos || []).length }})
                            </button>

                            <!-- Botón PreVentas -->
                            <button
                              type="button"
                              @click="setSubtablaFiltroTipo(r.ruta, 'preventa')"
                              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer"
                              :class="getSubtablaFiltroTipo(r.ruta) === 'preventa' ? 'bg-[#1E6B37] text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                            >
                              PreVenta ({{ detallePorRuta[r.ruta].total_preventas || 0 }})
                            </button>

                            <!-- Botón Tienda Cerrada -->
                            <button
                              v-if="(detallePorRuta[r.ruta].tipos_conteo?.['Tienda Cerrada'] || 0) > 0"
                              type="button"
                              @click="setSubtablaFiltroTipo(r.ruta, 'tienda cerrada')"
                              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer"
                              :class="getSubtablaFiltroTipo(r.ruta) === 'tienda cerrada' ? 'bg-amber-600 text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                            >
                              Cerrada ({{ detallePorRuta[r.ruta].tipos_conteo?.['Tienda Cerrada'] }})
                            </button>

                            <!-- Botón Sin Dinero -->
                            <button
                              v-if="(detallePorRuta[r.ruta].tipos_conteo?.['Sin Dinero'] || 0) > 0"
                              type="button"
                              @click="setSubtablaFiltroTipo(r.ruta, 'sin dinero')"
                              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer"
                              :class="getSubtablaFiltroTipo(r.ruta) === 'sin dinero' ? 'bg-purple-600 text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                            >
                              Sin Dinero ({{ detallePorRuta[r.ruta].tipos_conteo?.['Sin Dinero'] }})
                            </button>

                            <!-- Botón Con Stock -->
                            <button
                              v-if="(detallePorRuta[r.ruta].tipos_conteo?.['Con Stock'] || 0) > 0"
                              type="button"
                              @click="setSubtablaFiltroTipo(r.ruta, 'con stock')"
                              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer"
                              :class="getSubtablaFiltroTipo(r.ruta) === 'con stock' ? 'bg-cyan-700 text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                            >
                              Con Stock ({{ detallePorRuta[r.ruta].tipos_conteo?.['Con Stock'] }})
                            </button>

                            <!-- Botón Ausente -->
                            <button
                              v-if="(detallePorRuta[r.ruta].tipos_conteo?.['Ausente'] || 0) > 0"
                              type="button"
                              @click="setSubtablaFiltroTipo(r.ruta, 'ausente')"
                              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer"
                              :class="getSubtablaFiltroTipo(r.ruta) === 'ausente' ? 'bg-orange-600 text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                            >
                              Ausente ({{ detallePorRuta[r.ruta].tipos_conteo?.['Ausente'] }})
                            </button>
                          </div>

                          <!-- Buscador Interno y Contador -->
                          <div class="flex items-center gap-2">
                            <input
                              type="text"
                              v-model="subtablaBusqueda[r.ruta]"
                              placeholder="Buscar cliente en desglose..."
                              class="px-2.5 py-1 text-[10px] rounded-[6px] border border-[#E5E5EA] bg-white focus:outline-none focus:border-[#0071E3] w-44 font-medium"
                            />
                            <span class="text-[#86868B] font-mono">
                              Mostrando: <strong>{{ getEventosDesgloseFiltrados(r.ruta).length }}</strong> paradas
                            </span>
                          </div>
                        </div>

                        <!-- Subtabla de Clientes y Horarios de Ingreso -->
                        <div class="overflow-x-auto max-h-96 custom-scroll">
                          <table class="w-full text-left border-collapse text-[11px]">
                            <thead class="sticky top-0 z-10 border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73] bg-[#F8F8FA]">
                              <tr>
                                <th class="px-2.5 py-1.5 text-center w-8">#</th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'fecha')"
                                  class="px-3 py-1.5 cursor-pointer hover:bg-black/[0.04] transition-colors select-none w-28"
                                  :class="getSubtablaSort(r.ruta).col === 'fecha' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Día / Fecha"
                                >
                                  <div class="flex items-center gap-1">
                                    <span>Día / Fecha</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'fecha'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'cliente')"
                                  class="px-3 py-1.5 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                                  :class="getSubtablaSort(r.ruta).col === 'cliente' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Cliente / PDV"
                                >
                                  <div class="flex items-center gap-1">
                                    <span>Cliente / PDV</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'cliente'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'hora_web')"
                                  class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                                  :class="getSubtablaSort(r.ruta).col === 'hora_web' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Check-in Web"
                                >
                                  <div class="flex items-center justify-center gap-0.5">
                                    <span>Check-in Web</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'hora_web'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'hora_app')"
                                  class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                                  :class="getSubtablaSort(r.ruta).col === 'hora_app' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Check-in App"
                                >
                                  <div class="flex items-center justify-center gap-0.5">
                                    <span>Check-in App</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'hora_app'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'diferencia_segundos')"
                                  class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                                  :class="getSubtablaSort(r.ruta).col === 'diferencia_segundos' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Diferencia de Horario (Web vs App)"
                                >
                                  <div class="flex items-center justify-center gap-0.5">
                                    <span>Diferencia</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'diferencia_segundos'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th class="px-2.5 py-1.5 text-center w-20">Tiempo PDV</th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'tipo')"
                                  class="px-2.5 py-1.5 text-center cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                                  :class="getSubtablaSort(r.ruta).col === 'tipo' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Tipo / Motivo"
                                >
                                  <div class="flex items-center justify-center gap-0.5">
                                    <span>Tipo / Motivo</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'tipo'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th
                                  @click.stop="toggleSubtablaSort(r.ruta, 'monto')"
                                  class="px-2.5 py-1.5 text-right font-mono cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                                  :class="getSubtablaSort(r.ruta).col === 'monto' ? 'text-[#0071E3]' : ''"
                                  title="Ordenar por Monto"
                                >
                                  <div class="flex items-center justify-end gap-0.5">
                                    <span>Monto (Bs)</span>
                                    <span v-if="getSubtablaSort(r.ruta).col === 'monto'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                                  </div>
                                </th>
                                <th class="px-2.5 py-1.5 text-center w-12">Foto</th>
                              </tr>
                            </thead>

                            <tbody class="divide-y divide-black/[0.03]">
                              <tr v-if="getEventosDesgloseFiltrados(r.ruta).length === 0">
                                <td colspan="10" class="p-8 text-center text-[#86868B] text-xs">
                                  No se encontraron visitas con los filtros aplicados en esta ruta.
                                </td>
                              </tr>

                              <tr
                                v-else
                                v-for="(ev, evIdx) in getEventosDesgloseFiltrados(r.ruta)"
                                :key="ev.id || evIdx"
                                class="hover:bg-black/[0.02] transition-colors"
                              >
                                <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#86868B]">{{ evIdx + 1 }}</td>

                                <!-- Día / Fecha -->
                                <td class="px-3 py-1 font-mono text-[10.5px] text-[#1D1D1F]">
                                  <span class="font-bold text-[#0071E3]">{{ ev.dia_nombre }}</span>
                                  <span class="text-[9.5px] text-[#86868B] ml-1">{{ ev.fecha_corta }}</span>
                                </td>

                                <!-- Cliente -->
                                <td class="px-3 py-1 font-semibold text-[#1D1D1F] truncate max-w-[260px]" :title="ev.cliente">
                                  <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="truncate">{{ ev.cliente }}</span>
                                    <span v-if="ev.cliente_id" class="px-1.5 py-0.2 rounded font-mono text-[9px] font-bold text-[#0071E3] bg-blue-50 border border-blue-200 shrink-0">
                                      #{{ ev.cliente_id }}
                                    </span>
                                    <span v-else class="text-[9px] text-[#C7C7CC] shrink-0 font-mono">s/código</span>
                                  </div>
                                </td>

                                <!-- Check-in Web -->
                                <td class="px-2.5 py-1 text-center font-mono text-[11px]" :class="ev.hora_web ? 'font-bold text-[#1D1D1F]' : 'text-[#C7C7CC]'">
                                  {{ ev.hora_web || '—' }}
                                </td>

                                <!-- Check-in App -->
                                <td class="px-2.5 py-1 text-center font-mono text-[11px]" :class="ev.hora_app ? 'font-bold text-[#1D1D1F]' : 'text-[#C7C7CC]'">
                                  {{ ev.hora_app || '—' }}
                                </td>

                                <!-- Diferencia -->
                                <td class="px-2.5 py-1 text-center">
                                  <span
                                    v-if="ev.diferencia_texto && ev.diferencia_texto !== '—'"
                                    class="inline-flex px-1.5 py-0.2 rounded font-mono text-[9.5px] font-bold"
                                    :class="badgeColorDiferencia(ev)"
                                    :title="`Diferencia entre App (${ev.hora_app || '—'}) y Web (${ev.hora_web || '—'})`"
                                  >
                                    {{ ev.diferencia_texto }}
                                  </span>
                                  <span v-else class="text-[#C7C7CC] text-[11px] font-mono">—</span>
                                </td>

                                <!-- Tiempo PDV -->
                                <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#6E6E73]">
                                  {{ ev.tiempo_pdv || '—' }}
                                </td>

                                <!-- Tipo / Motivo -->
                                <td class="px-2.5 py-1 text-center">
                                  <span
                                    class="inline-flex px-1.5 py-0.2 rounded text-[9px] font-bold"
                                    :class="badgeColorTipo(ev.tipo)"
                                  >
                                    {{ ev.tipo || 'Visita' }}
                                  </span>
                                </td>

                                <!-- Monto -->
                                <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                  {{ ev.monto > 0 ? Number(ev.monto).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—' }}
                                </td>

                                <!-- Foto -->
                                <td class="px-2.5 py-1 text-center">
                                  <button
                                    v-if="ev.foto_url"
                                    type="button"
                                    @click.stop="abrirModalFoto(ev)"
                                    class="p-1 rounded hover:bg-black/[0.05] text-[#0071E3] transition-colors cursor-pointer"
                                    title="Ver foto de campo"
                                  >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                  </button>
                                  <span v-else class="text-[#C7C7CC] text-[11px]">—</span>
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
            </table>
          </div>
        </section>
      </template>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- SUBMODO 2: SECUENCIA DE ITINERARIO & MAPA LEAFLET 1..N           -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <template v-else-if="submodo === 'itinerario'">
        <!-- Barra de Control Itinerario -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-xs border border-white/60">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Ruta -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <label class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</label>
              <select
                v-model="itinerarioRutaSeleccionada"
                @change="cargarSecuenciaItinerario"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option v-for="r in catalogo_rutas" :key="r" :value="r">{{ r }}</option>
              </select>
            </div>

            <!-- Selector de Día de la Semana -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <label class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Día:</label>
              <select
                v-model="itinerarioDiaSeleccionado"
                @change="cargarSecuenciaItinerario"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer capitalize"
              >
                <option v-for="d in diasSemanaLista" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>

            <!-- Botón Recargar -->
            <button
              type="button"
              @click="cargarSecuenciaItinerario"
              :disabled="cargandoItinerario"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-white hover:bg-[#F5F5F7] text-[#1D1D1F] text-xs font-semibold rounded-[8px] border border-[#E5E5EA] shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            >
              <svg
                class="w-3.5 h-3.5"
                :class="{ 'animate-spin': cargandoItinerario }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              <span>Recargar</span>
            </button>

            <!-- Botón Guardar Secuencia -->
            <button
              type="button"
              @click="guardarSecuenciaManual"
              :disabled="guardandoItinerario || itinerarioClientes.length === 0"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#34C759] hover:bg-[#30B753] text-white text-xs font-semibold rounded-[8px] shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            >
              <svg
                v-if="guardandoItinerario"
                class="w-3.5 h-3.5 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              <span>Guardar Orden</span>
            </button>
          </div>

          <!-- Indicador de Estado y Cobertura -->
          <div class="flex items-center gap-3">
            <span
              v-if="estadoGuardado"
              class="text-xs font-bold font-mono px-2 py-0.5 rounded-[6px]"
              :class="estadoGuardado.includes('✓') ? 'text-[#34C759] bg-[#34C759]/10' : 'text-[#0071E3] bg-[#0071E3]/10'"
            >
              {{ estadoGuardado }}
            </span>
            <div class="text-[10px] text-[#6E6E73] font-mono">
              GPS: <strong class="text-[#1D1D1F]">{{ totalClientesConCoords }}</strong> / {{ itinerarioClientes.length }}
            </div>
          </div>
        </div>

        <!-- Split Screen: 5 Cols Lista Secuencial + 7 Cols Mapa Interactivo -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
          <!-- Columna Izquierda (5 Cols): Lista de Clientes Ordenados 1..N -->
          <div class="lg:col-span-5 apple-glass-card rounded-[14px] shadow-float flex flex-col h-[660px] overflow-hidden border border-white/60">
            <div class="px-4 py-3 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex justify-between items-center">
              <div>
                <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">
                  Orden de Visita ({{ itinerarioClientes.length }})
                </span>
                <p class="text-[10px] text-[#6E6E73]">
                  Usa las flechas o arrastra para reordenar la secuencia
                </p>
              </div>
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white border border-[#E5E5EA] text-[#0071E3]">
                {{ itinerarioRutaSeleccionada }} · {{ itinerarioDiaSeleccionado }}
              </span>
            </div>

            <div class="flex-1 p-3 overflow-y-auto custom-scroll space-y-2">
              <div v-if="cargandoItinerario" class="p-12 text-center text-[#86868B] font-medium text-xs">
                <svg class="w-5 h-5 animate-spin mx-auto text-[#0071E3] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Cargando itinerario y clientes...
              </div>

              <div v-else-if="itinerarioClientes.length === 0" class="p-12 text-center text-[#86868B] font-medium text-xs">
                No hay clientes planificados para la ruta {{ itinerarioRutaSeleccionada }} el día {{ itinerarioDiaSeleccionado }}.
              </div>

              <div
                v-else
                v-for="(c, idx) in itinerarioClientes"
                :key="c.cliente_id"
                draggable="true"
                @dragstart="onDragStart($event, idx)"
                @dragover.prevent
                @drop="onDrop($event, idx)"
                @click="centrarClienteEnMapa(c)"
                class="p-2.5 rounded-[10px] bg-white border border-[#E5E5EA] shadow-xs hover:border-[#0071E3] hover:shadow-md transition-all cursor-pointer flex items-center gap-2.5 group select-none"
              >
                <!-- Badge Numerador 1..N -->
                <div class="w-7 h-7 rounded-full bg-[#0071E3] text-white flex items-center justify-center text-xs font-bold font-mono shrink-0 shadow-xs">
                  {{ idx + 1 }}
                </div>

                <!-- Info Cliente -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-[#1D1D1F] truncate group-hover:text-[#0071E3] transition-colors">
                      {{ c.cliente }}
                    </span>
                    <span class="text-[9px] font-mono text-[#86868B] shrink-0">
                      #{{ c.cliente_id }}
                    </span>
                  </div>
                  <div class="text-[10px] text-[#6E6E73] truncate">
                    {{ c.direccion || 'Sin Dirección' }}
                  </div>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-[#F5F5F7] text-[#6E6E73] font-medium">
                      {{ c.tipo_negocio || 'Cliente' }}
                    </span>
                    <span
                      v-if="c.tiene_coords"
                      class="text-[9px] font-bold text-[#34C759] flex items-center gap-0.5"
                    >
                      <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      </svg>
                      GPS
                    </span>
                    <span v-else class="text-[9px] text-[#FF3B30] font-semibold">
                      Sin GPS
                    </span>
                  </div>
                </div>

                <!-- Flechas Mover Arriba / Abajo -->
                <div class="flex flex-col gap-1 shrink-0" @click.stop>
                  <button
                    type="button"
                    @click="moverCliente(idx, -1)"
                    :disabled="idx === 0"
                    class="w-5 h-5 rounded flex items-center justify-center bg-[#F5F5F7] hover:bg-[#E5E5EA] disabled:opacity-30 disabled:hover:bg-[#F5F5F7] text-[10px] font-bold transition-colors cursor-pointer"
                    title="Mover hacia arriba"
                  >
                    ▲
                  </button>
                  <button
                    type="button"
                    @click="moverCliente(idx, 1)"
                    :disabled="idx === itinerarioClientes.length - 1"
                    class="w-5 h-5 rounded flex items-center justify-center bg-[#F5F5F7] hover:bg-[#E5E5EA] disabled:opacity-30 disabled:hover:bg-[#F5F5F7] text-[10px] font-bold transition-colors cursor-pointer"
                    title="Mover hacia abajo"
                  >
                    ▼
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Columna Derecha (7 Cols): Mapa Leaflet con Trazo y Pines Numerados -->
          <div class="lg:col-span-7 apple-glass-card rounded-[14px] shadow-float flex flex-col h-[660px] overflow-hidden relative border border-white/60">
            <div class="px-4 py-3 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex justify-between items-center z-10">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
                <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">
                  Trazado Geográfico de la Ruta
                </span>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="reajustarMapa"
                  class="px-2.5 py-1 rounded-[6px] bg-white border border-[#E5E5EA] text-[10px] font-bold text-[#1D1D1F] hover:bg-slate-50 transition-colors shadow-xs cursor-pointer"
                >
                  Centrar Ruta
                </button>
                <span class="text-[10px] text-[#0071E3] font-bold font-mono">
                  Línea continua 1 ➔ N
                </span>
              </div>
            </div>

            <div id="mapaItinerarioContainer" class="w-full flex-1 bg-[#F5F5F7] relative z-0"></div>
          </div>
        </div>
      </template>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- MODAL: TIMELINE DETALLADO DE UNA JORNADA ESPECÍFICA (CELDA)     -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div
        v-if="modalTimelineAbierto"
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/40 backdrop-blur-md p-4 animate-in fade-in"
      >
        <div class="apple-glass-card rounded-[18px] border border-white/80 shadow-2xl backdrop-blur-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden bg-white">
          <div class="px-5 py-3.5 flex items-center justify-between border-b border-[#E5E5EA] bg-[#F5F5F7]/80 backdrop-blur-md">
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
                <h3 class="text-sm font-bold text-[#1D1D1F] uppercase tracking-wider">
                  Timeline de la Jornada: {{ timelineRutaActual }}
                </h3>
              </div>
              <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
                {{ timelineVendedorActual }} · Fecha: <strong class="font-mono text-[#1D1D1F]">{{ timelineFechaActual }}</strong>
              </p>
            </div>
            <button
              type="button"
              @click="cerrarTimelineModal"
              class="w-7 h-7 rounded-full bg-[#E5E5EA] hover:bg-[#D1D1D6] flex items-center justify-center text-[#1D1D1F] font-bold text-xs cursor-pointer transition-colors"
            >
              ✕
            </button>
          </div>

          <div v-if="timelineData && timelineData.resumen" class="px-5 py-2.5 bg-white border-b border-[#E5E5EA] grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
            <div class="p-2 rounded-[8px] bg-blue-50/60 border border-blue-100">
              <div class="text-[9px] font-bold text-[#0071E3] uppercase">Ingreso Web</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.ingreso_sistema || '-' }}
              </div>
            </div>
            <div class="p-2 rounded-[8px] bg-blue-50/60 border border-blue-100">
              <div class="text-[9px] font-bold text-[#0071E3] uppercase">Salida Web</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.salida_sistema || '-' }}
              </div>
            </div>
            <div class="p-2 rounded-[8px] bg-emerald-50/60 border border-emerald-100">
              <div class="text-[9px] font-bold text-[#34C759] uppercase">Ingreso App</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.ingreso_movil || '-' }}
              </div>
            </div>
            <div class="p-2 rounded-[8px] bg-emerald-50/60 border border-emerald-100">
              <div class="text-[9px] font-bold text-[#34C759] uppercase">Salida App</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.salida_movil || '-' }}
              </div>
            </div>
          </div>

          <div class="p-5 overflow-y-auto custom-scroll space-y-3 flex-1 bg-[#F5F5F7]/40">
            <div v-if="cargandoTimeline" class="p-12 text-center text-[#86868B] text-xs">
              <svg class="w-5 h-5 animate-spin mx-auto text-[#0071E3] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              Cargando eventos de la jornada...
            </div>

            <div v-else-if="!timelineData || (timelineData.timeline || []).length === 0" class="p-12 text-center text-[#86868B] text-xs">
              No se registraron eventos para esta ruta en la fecha indicada.
            </div>

            <div
              v-else
              v-for="ev in timelineData.timeline"
              :key="ev.id"
              class="p-3 rounded-[12px] bg-white border border-[#E5E5EA] shadow-xs flex items-start gap-3 hover:border-black/[0.12] transition-colors"
            >
              <div class="flex flex-col items-center gap-1 shrink-0 w-16">
                <span class="text-xs font-bold font-mono text-[#1D1D1F]">{{ ev.hora }}</span>
                <span
                  class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase"
                  :class="ev.origen === 'SISTEMA' ? 'bg-blue-100 text-[#0071E3]' : 'bg-emerald-100 text-[#34C759]'"
                >
                  {{ ev.origen === 'SISTEMA' ? 'Web' : 'Móvil' }}
                </span>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-bold text-[#1D1D1F] truncate">{{ ev.cliente }}</span>
                  <span
                    class="text-[10px] font-bold px-1.5 py-0.2 rounded"
                    :class="ev.tipo_evento === 'VENTA' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                  >
                    {{ ev.tipo_evento }}
                  </span>
                </div>

                <div v-if="ev.origen === 'SISTEMA'" class="text-[11px] text-[#6E6E73] mt-1 flex flex-wrap items-center gap-3">
                  <span v-if="ev.monto > 0" class="font-bold text-[#1D1D1F]">
                    Monto: Bs {{ Number(ev.monto).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                  </span>
                  <span>Tiempo PDV: <strong class="font-mono text-[#1D1D1F]">{{ ev.tiempo_pdv }}</strong></span>
                  <span v-if="ev.check_out !== '-'">Salida: <strong class="font-mono text-[#1D1D1F]">{{ ev.check_out }}</strong></span>
                  <span v-if="ev.motivo" class="text-slate-500 italic">Motivo: {{ ev.motivo }}</span>
                </div>

                <div v-else class="text-[11px] text-[#6E6E73] mt-1 flex flex-wrap items-center gap-3">
                  <span v-if="ev.motivo" class="italic text-slate-600">"{{ ev.motivo }}"</span>
                  <a
                    v-if="ev.latitud && ev.longitud"
                    :href="`https://www.google.com/maps?q=${ev.latitud},${ev.longitud}`"
                    target="_blank"
                    class="text-[10px] font-bold text-[#0071E3] hover:underline flex items-center gap-0.5"
                  >
                    Ver en Maps
                  </a>
                </div>
              </div>

              <div v-if="ev.foto_url" class="shrink-0">
                <img
                  :src="ev.foto_url"
                  alt="Evidencia"
                  @click="abrirModalFoto({ cliente: ev.cliente, foto_url: ev.foto_url, fecha_corta: timelineFechaActual })"
                  class="w-12 h-12 object-cover rounded-[8px] border border-[#E5E5EA] shadow-2xs hover:scale-105 transition-transform cursor-pointer"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- MODAL: CARRUSEL DE FOTOS DE EVIDENCIA                            -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <Teleport to="body">
        <div
          v-if="modalFoto.abierto"
          class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
          @click.self="cerrarModalFoto"
        >
          <div class="apple-glass-card rounded-[20px] max-w-xl w-full p-4 overflow-hidden flex flex-col gap-3 shadow-2xl bg-white">
            <div class="flex items-center justify-between border-b border-black/[0.06] pb-2">
              <div>
                <h4 class="font-bold text-sm text-[#1D1D1F]">{{ modalFoto.cliente }}</h4>
                <p class="text-[11px] text-[#6E6E73] font-medium">{{ modalFoto.fecha || 'Evidencia fotográfica' }}</p>
              </div>
              <button
                type="button"
                @click="cerrarModalFoto"
                class="w-7 h-7 rounded-full flex items-center justify-center hover:bg-black/[0.05] text-[#86868B] transition-colors cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div class="relative bg-black/5 rounded-[14px] overflow-hidden min-h-[300px] max-h-[460px] flex items-center justify-center">
              <img
                v-if="modalFoto.fotoUrl"
                :src="modalFoto.fotoUrl"
                class="max-h-[460px] w-auto object-contain select-none"
                alt="Foto evidencia"
              />
              <div v-else class="text-[#86868B] text-xs p-6 text-center">
                Sin fotografía disponible
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SubNavTabs from '@/Components/UI/SubNavTabs.vue'
import AvanceFiltrosBar from './AvanceFiltrosBar.vue'
import { useAvanceStore } from '@/Stores/avanceStore'
import { navigationConfig } from '@/Config/navigation'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// ── Props de Entrada desde Inertia ──────────────────────────────────────────
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

// Pestañas de subnavegación coherentes con Avance
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance')
const avanceTabs = avanceMenu ? avanceMenu.submodules : []

// Submodo Activo ('horarios' | 'itinerario')
const submodo = ref('horarios')

// ── SUBMODO 1: MATRIZ DE HORARIOS ───────────────────────────────────────────
const cargandoMatriz = ref(false)
const matrizData = ref([])
const fechasRango = ref([])
const totalRutas = ref(0)
const listaCanales = ref(props.canales || [])

// Ordenamiento de la Matriz Principal
const sortMatrizCol = ref('ruta')
const sortMatrizDir = ref('asc')

function toggleSortMatriz(col) {
  if (sortMatrizCol.value === col) {
    sortMatrizDir.value = sortMatrizDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortMatrizCol.value = col
    sortMatrizDir.value = 'asc'
  }
}

const rangoTexto = computed(() => {
  if (!store.fechaInicio && !store.fechaCorte) return ''
  return `${formatoFechaCorta(store.fechaInicio)} ➔ ${formatoFechaCorta(store.fechaCorte)}`
})

// Filtrado y Ordenamiento de las Rutas en la Matriz
const rutasFiltradas = computed(() => {
  let res = [...matrizData.value]

  // Filtro de canal por store
  if (store.canalesSeleccionados && store.canalesSeleccionados.length > 0) {
    const selUpper = store.canalesSeleccionados.map(c => String(c).toUpperCase().trim())
    if (!selUpper.includes('TODOS') && !selUpper.includes('TODAS')) {
      res = res.filter(r => selUpper.includes(String(r.canal || '').toUpperCase().trim()))
    }
  }

  // Filtro de rutas por store
  if (store.rutasSeleccionadas && store.rutasSeleccionadas.length > 0) {
    const rUpper = store.rutasSeleccionadas.map(r => String(r).toUpperCase().trim())
    res = res.filter(r => rUpper.includes(String(r.ruta || '').toUpperCase().trim()))
  }

  // Búsqueda rápida
  if (store.busqueda && store.busqueda.trim() !== '') {
    const q = store.busqueda.toLowerCase().trim()
    res = res.filter(r =>
      String(r.ruta || '').toLowerCase().includes(q) ||
      String(r.vendedor || '').toLowerCase().includes(q)
    )
  }

  // Ordenamiento
  const col = sortMatrizCol.value
  const mul = sortMatrizDir.value === 'asc' ? 1 : -1

  return res.sort((a, b) => {
    if (col === 'ruta') {
      return mul * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'vendedor') {
      return mul * String(a.vendedor || '').localeCompare(String(b.vendedor || ''))
    }
    if (col === 'canal') {
      return mul * String(a.canal || '').localeCompare(String(b.canal || ''))
    }
    return 0
  })
})

async function consultarMatriz() {
  if (cargandoMatriz.value) return
  cargandoMatriz.value = true
  store.cargandoCumplimiento = true

  try {
    const res = await axios.post(route('supervisor.avance.horarios.matriz'), {
      fecha_inicio: store.fechaInicio,
      fecha_fin: store.fechaCorte,
      canales: store.canalesSeleccionados,
      rutas: store.rutasSeleccionadas,
      busqueda: store.busqueda,
    })

    matrizData.value = res.data.matriz || []
    fechasRango.value = res.data.fechas || []
    totalRutas.value = res.data.total_rutas || 0

    if (res.data.canales && res.data.canales.length > 0) {
      listaCanales.value = res.data.canales.filter(c => c !== 'TODOS')
    }
  } catch (error) {
    console.error('Error al consultar matriz de horarios:', error)
  } finally {
    cargandoMatriz.value = false
    store.cargandoCumplimiento = false
  }
}

// ── DESGLOSE HISTÓRICO POR RUTA (ACORDEÓN) ──────────────────────────────────
const acordeonAbierto = reactive({})
const detallePorRuta = reactive({})
const cargandoDetalleRuta = reactive({})
const errorDetalleRuta = reactive({})

// Filtros y ordenamiento por ruta en el desglose
const subtablaFiltroDia = reactive({})
const subtablaFiltroTipo = reactive({})
const subtablaBusqueda = reactive({})
const subtablaSort = reactive({})

function toggleRutaAcordeon(ruta) {
  acordeonAbierto[ruta] = !acordeonAbierto[ruta]
  if (acordeonAbierto[ruta] && !detallePorRuta[ruta]) {
    cargarDetalleRuta(ruta)
  }
}

async function cargarDetalleRuta(ruta) {
  cargandoDetalleRuta[ruta] = true
  errorDetalleRuta[ruta] = null

  try {
    const res = await axios.post(route('supervisor.avance.horarios.detalle-ruta'), {
      ruta: ruta,
      fecha_inicio: store.fechaInicio,
      fecha_fin: store.fechaCorte,
    })
    detallePorRuta[ruta] = res.data
  } catch (err) {
    console.error('Error al cargar desglose de ruta:', err)
    errorDetalleRuta[ruta] = err?.response?.data?.message || err.message || 'Error al cargar desglose de la ruta'
  } finally {
    cargandoDetalleRuta[ruta] = false
  }
}

function getSubtablaFiltroDia(ruta) {
  return subtablaFiltroDia[ruta] || 'todos'
}

function setSubtablaFiltroDia(ruta, dia) {
  subtablaFiltroDia[ruta] = dia
}

function getSubtablaFiltroTipo(ruta) {
  return subtablaFiltroTipo[ruta] || 'todos'
}

function setSubtablaFiltroTipo(ruta, tipo) {
  subtablaFiltroTipo[ruta] = tipo
}

function getSubtablaSort(ruta) {
  return subtablaSort[ruta] || { col: 'fecha', dir: 'desc' }
}

function toggleSubtablaSort(ruta, col) {
  const actual = getSubtablaSort(ruta)
  if (actual.col === col) {
    subtablaSort[ruta] = { col, dir: actual.dir === 'asc' ? 'desc' : 'asc' }
  } else {
    subtablaSort[ruta] = { col, dir: 'asc' }
  }
}

function getEventosDesgloseFiltrados(ruta) {
  const d = detallePorRuta[ruta]
  if (!d || !Array.isArray(d.eventos)) return []

  let list = [...d.eventos]

  // Filtro por Día / Fecha
  const fDia = getSubtablaFiltroDia(ruta)
  if (fDia !== 'todos') {
    list = list.filter(ev => ev.fecha === fDia)
  }

  // Filtro por Tipo / Motivo
  const fTipo = getSubtablaFiltroTipo(ruta)
  if (fTipo !== 'todos') {
    list = list.filter(ev => String(ev.tipo || '').toLowerCase().includes(fTipo))
  }

  // Búsqueda de cliente
  const q = String(subtablaBusqueda[ruta] || '').toLowerCase().trim()
  if (q !== '') {
    list = list.filter(ev =>
      String(ev.cliente || '').toLowerCase().includes(q) ||
      String(ev.cliente_id || '').includes(q)
    )
  }

  // Ordenamiento dinámico
  const { col, dir } = getSubtablaSort(ruta)
  const mul = dir === 'asc' ? 1 : -1

  return list.sort((a, b) => {
    if (col === 'fecha') {
      const cmpF = mul * String(a.fecha || '').localeCompare(String(b.fecha || ''))
      if (cmpF !== 0) return cmpF
      return String(a.hora_orden || '').localeCompare(String(b.hora_orden || ''))
    }
    if (col === 'cliente') {
      return mul * String(a.cliente || '').localeCompare(String(b.cliente || ''))
    }
    if (col === 'hora_web') {
      return mul * String(a.hora_web || '99:99:99').localeCompare(String(b.hora_web || '99:99:99'))
    }
    if (col === 'hora_app') {
      return mul * String(a.hora_app || '99:99:99').localeCompare(String(b.hora_app || '99:99:99'))
    }
    if (col === 'diferencia_segundos') {
      const vA = a.diferencia_segundos !== null && a.diferencia_segundos !== undefined ? Number(a.diferencia_segundos) : 999999
      const vB = b.diferencia_segundos !== null && b.diferencia_segundos !== undefined ? Number(b.diferencia_segundos) : 999999
      return mul * (vA - vB)
    }
    if (col === 'tipo') {
      return mul * String(a.tipo || '').localeCompare(String(b.tipo || ''))
    }
    if (col === 'monto') {
      return mul * ((Number(a.monto) || 0) - (Number(b.monto) || 0))
    }
    return 0
  })
}

function exportarDesgloseCsv(ruta) {
  const eventos = getEventosDesgloseFiltrados(ruta)
  if (eventos.length === 0) return

  const headers = ['#', 'Fecha', 'Día', 'ID Cliente', 'Cliente', 'Check-in Web', 'Check-in App', 'Diferencia', 'Tiempo PDV', 'Tipo/Motivo', 'Monto (Bs)']
  const rows = [headers]

  eventos.forEach((ev, idx) => {
    rows.push([
      idx + 1,
      ev.fecha,
      ev.dia_nombre,
      ev.cliente_id || '',
      ev.cliente,
      ev.hora_web || '',
      ev.hora_app || '',
      ev.diferencia_texto || '',
      ev.tiempo_pdv || '',
      ev.tipo || '',
      ev.monto || 0,
    ])
  })

  const csvContent = '\uFEFF' + rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `desglose_${ruta}_${store.fechaInicio}_${store.fechaCorte}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// ── BADGES DE COLORES CONSISTENTES ──────────────────────────────────────────
function badgeColorTipo(tipo) {
  const t = String(tipo || '').toLowerCase()
  if (t.includes('no visitado') || t.includes('pendiente')) {
    return 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'
  }
  if (t.includes('preventa') || t.includes('venta')) {
    return 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
  }
  if (t.includes('tienda cerrada') || t.includes('cerrad')) {
    return 'bg-amber-50 text-amber-700 border border-amber-200'
  }
  if (t.includes('sin dinero')) {
    return 'bg-purple-50 text-purple-700 border border-purple-200'
  }
  if (t.includes('con stock') || t.includes('stock')) {
    return 'bg-cyan-50 text-cyan-700 border border-cyan-200'
  }
  if (t.includes('ausente')) {
    return 'bg-orange-50 text-orange-700 border border-orange-200'
  }
  if (t.includes('cobranza')) {
    return 'bg-blue-50 text-[#0071E3] border border-blue-200'
  }
  if (t.includes('oportunidad')) {
    return 'bg-violet-50 text-violet-700 border border-violet-200'
  }
  if (t.includes('rechaz') || t.includes('no atendido')) {
    return 'bg-rose-50 text-rose-700 border border-rose-200'
  }
  return 'bg-slate-50 text-slate-700 border border-slate-200'
}

function badgeColorDiferencia(ev) {
  if (!ev || ev.diferencia_segundos === null || ev.diferencia_segundos === undefined) {
    if (ev?.diferencia_texto === 'Solo Web' || ev?.diferencia_texto === 'Solo App') {
      return 'bg-slate-100 text-slate-600 border border-slate-200'
    }
    return 'text-[#C7C7CC]'
  }
  const s = Math.abs(ev.diferencia_segundos)
  if (s <= 120) {
    return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
  }
  if (s <= 600) {
    return 'bg-amber-50 text-amber-700 border border-amber-200'
  }
  return 'bg-rose-50 text-rose-700 border border-rose-200'
}

// ── MODAL TIMELINE DE JORNADA ESPECÍFICA (DESDE CELDA) ──────────────────────
const modalTimelineAbierto = ref(false)
const cargandoTimeline = ref(false)
const timelineData = ref(null)
const timelineRutaActual = ref('')
const timelineFechaActual = ref('')
const timelineVendedorActual = ref('')

async function abrirTimelineModal(ruta, fecha, vendedor) {
  timelineRutaActual.value = ruta
  timelineFechaActual.value = fecha
  timelineVendedorActual.value = vendedor || 'No Asignado'
  modalTimelineAbierto.value = true
  cargandoTimeline.value = true
  timelineData.value = null

  try {
    const res = await axios.post(route('supervisor.avance.horarios.timeline'), {
      ruta: ruta,
      fecha: fecha,
    })
    timelineData.value = res.data
  } catch (error) {
    console.error('Error al cargar timeline de horarios:', error)
  } finally {
    cargandoTimeline.value = false
  }
}

function cerrarTimelineModal() {
  modalTimelineAbierto.value = false
  timelineData.value = null
}

// ── MODAL FOTOS EVIDENCIA ───────────────────────────────────────────────────
const modalFoto = reactive({
  abierto: false,
  cliente: '',
  fecha: '',
  fotoUrl: null,
})

function abrirModalFoto(ev) {
  modalFoto.abierto = true
  modalFoto.cliente = ev.cliente || 'Cliente'
  modalFoto.fecha = `${ev.dia_nombre || ''} ${ev.fecha_corta || ev.fecha || ''} ${ev.hora_app || ev.hora_web || ''}`.trim()
  modalFoto.fotoUrl = ev.foto_url
}

function cerrarModalFoto() {
  modalFoto.abierto = false
  modalFoto.fotoUrl = null
}

// ── SUBMODO 2: SECUENCIA DE ITINERARIO & MAPA ───────────────────────────────
const diasSemanaLista = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado']
const itinerarioRutaSeleccionada = ref(props.catalogo_rutas[0] || 'TDB 01')
const itinerarioDiaSeleccionado = ref('lunes')

const cargandoItinerario = ref(false)
const guardandoItinerario = ref(false)
const estadoGuardado = ref('')
const itinerarioClientes = ref([])
const totalClientesConCoords = ref(0)

let mapaInstancia = null
let capaMarcadores = null
let capaPolilinea = null
let timerAutoguardado = null

async function cargarSecuenciaItinerario() {
  if (cargandoItinerario.value) return
  cargandoItinerario.value = true
  estadoGuardado.value = ''

  try {
    const res = await axios.post(route('supervisor.avance.itinerario.secuencia'), {
      ruta: itinerarioRutaSeleccionada.value,
      dia: itinerarioDiaSeleccionado.value,
    })

    itinerarioClientes.value = res.data.clientes || []
    totalClientesConCoords.value = res.data.total_con_coordenadas || 0

    await nextTick()
    renderizarMapaItinerario()
  } catch (error) {
    console.error('Error al cargar secuencia de itinerario:', error)
  } finally {
    cargandoItinerario.value = false
  }
}

function moverCliente(indice, delta) {
  const nuevoIndice = indice + delta
  if (nuevoIndice < 0 || nuevoIndice >= itinerarioClientes.value.length) return

  const item = itinerarioClientes.value.splice(indice, 1)[0]
  itinerarioClientes.value.splice(nuevoIndice, 0, item)

  itinerarioClientes.value.forEach((c, idx) => {
    c.orden = idx + 1
  })

  renderizarMapaItinerario()
  programarAutoguardado()
}

let dragSourceIndex = null

function onDragStart(event, idx) {
  dragSourceIndex = idx
  event.dataTransfer.effectAllowed = 'move'
}

function onDrop(event, targetIdx) {
  if (dragSourceIndex === null || dragSourceIndex === targetIdx) return

  const item = itinerarioClientes.value.splice(dragSourceIndex, 1)[0]
  itinerarioClientes.value.splice(targetIdx, 0, item)

  itinerarioClientes.value.forEach((c, idx) => {
    c.orden = idx + 1
  })

  dragSourceIndex = null
  renderizarMapaItinerario()
  programarAutoguardado()
}

function programarAutoguardado() {
  estadoGuardado.value = 'Guardando cambios...'
  if (timerAutoguardado) clearTimeout(timerAutoguardado)

  timerAutoguardado = setTimeout(() => {
    guardarSecuenciaManual(true)
  }, 1200)
}

async function guardarSecuenciaManual(esAutomatico = false) {
  if (guardandoItinerario.value) return
  guardandoItinerario.value = true

  const secuenciaPayload = itinerarioClientes.value.map(c => ({
    cliente_id: c.cliente_id,
    orden: c.orden,
  }))

  try {
    await axios.post(route('supervisor.avance.itinerario.guardar'), {
      ruta: itinerarioRutaSeleccionada.value,
      dia: itinerarioDiaSeleccionado.value,
      secuencia: secuenciaPayload,
    })

    estadoGuardado.value = 'Guardado ✓'
    setTimeout(() => {
      if (estadoGuardado.value === 'Guardado ✓') {
        estadoGuardado.value = ''
      }
    }, 3000)
  } catch (error) {
    console.error('Error al guardar secuencia:', error)
    estadoGuardado.value = 'Error al guardar'
  } finally {
    guardandoItinerario.value = false
  }
}

// ── LEAFLET MAPA FUNCTIONS ──────────────────────────────────────────────────
function inicializarMapa() {
  const container = document.getElementById('mapaItinerarioContainer')
  if (!container || mapaInstancia) return

  mapaInstancia = L.map(container, {
    zoomControl: false,
    attributionControl: false,
  }).setView([-17.7833, -63.1821], 13)

  L.control.zoom({ position: 'topright' }).addTo(mapaInstancia)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    subdomains: 'abcd',
  }).addTo(mapaInstancia)

  capaMarcadores = L.layerGroup().addTo(mapaInstancia)
  capaPolilinea = L.layerGroup().addTo(mapaInstancia)
}

function renderizarMapaItinerario() {
  if (!mapaInstancia) {
    inicializarMapa()
  }
  if (!mapaInstancia || !capaMarcadores || !capaPolilinea) return

  capaMarcadores.clearLayers()
  capaPolilinea.clearLayers()

  const coords = []
  const bounds = L.latLngBounds([])

  itinerarioClientes.value.forEach((c) => {
    if (c.latitud && c.longitud && c.latitud !== 0 && c.longitud !== 0) {
      const latlng = [c.latitud, c.longitud]
      coords.push(latlng)
      bounds.extend(latlng)

      const pinIcon = L.divIcon({
        className: 'custom-sequence-pin',
        html: `
          <div style="background-color: #0071E3; color: white; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2.5px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-family: ui-monospace, monospace;">
            ${c.orden}
          </div>
        `,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
        popupAnchor: [0, -13],
      })

      const popupHtml = `
        <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; min-width: 180px; padding: 2px;">
          <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 4px;">
            <span style="background: #0071E3; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 10px;">
              # ${c.orden}
            </span>
            <span style="font-weight: bold; color: #6E6E73; font-size: 9px; font-family: ui-monospace, monospace;">
              ID: ${c.cliente_id}
            </span>
          </div>
          <div style="font-weight: 800; color: #1D1D1F; font-size: 12px; margin-bottom: 2px;">
            ${c.cliente}
          </div>
          <div style="color: #6E6E73; font-size: 10px;">
            ${c.direccion || 'Sin Dirección'}
          </div>
        </div>
      `

      const marker = L.marker(latlng, { icon: pinIcon })
      marker.bindPopup(popupHtml)
      c._marker = marker
      capaMarcadores.addLayer(marker)
    }
  })

  if (coords.length > 1) {
    const polyline = L.polyline(coords, {
      color: '#0071E3',
      weight: 3.5,
      opacity: 0.85,
      dashArray: '6, 6',
      lineJoin: 'round',
    })
    capaPolilinea.addLayer(polyline)
  }

  if (bounds.isValid()) {
    mapaInstancia.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
  }
}

function centrarClienteEnMapa(cliente) {
  if (!cliente.latitud || !cliente.longitud || !mapaInstancia) return

  mapaInstancia.flyTo([cliente.latitud, cliente.longitud], 16, {
    animate: true,
    duration: 0.8,
  })

  if (cliente._marker) {
    cliente._marker.openPopup()
  }
}

function reajustarMapa() {
  if (!mapaInstancia) return
  const bounds = L.latLngBounds([])
  itinerarioClientes.value.forEach(c => {
    if (c.latitud && c.longitud && c.latitud !== 0) {
      bounds.extend([c.latitud, c.longitud])
    }
  })
  if (bounds.isValid()) {
    mapaInstancia.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
  }
}

function cambiarSubmodo(modo) {
  submodo.value = modo
  if (modo === 'itinerario') {
    nextTick(() => {
      if (!mapaInstancia) {
        inicializarMapa()
      }
      if (itinerarioClientes.value.length === 0) {
        cargarSecuenciaItinerario()
      } else {
        setTimeout(() => {
          if (mapaInstancia) {
            mapaInstancia.invalidateSize()
            renderizarMapaItinerario()
          }
        }, 150)
      }
    })
  }
}

// ── Helpers de Formato ──────────────────────────────────────────────────────
function formatoHoraCorta(h) {
  if (!h || h === '-' || h === '00:00:00') return '-'
  return h.substring(0, 5)
}

function formatoFechaCorta(f) {
  if (!f) return ''
  const parts = f.split('-')
  if (parts.length < 3) return f
  return `${parts[2]}/${parts[1]}`
}

function getDiaNombreCorto(f) {
  if (!f) return ''
  const d = new Date(f + 'T00:00:00')
  const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
  return dias[d.getDay()] || ''
}

// ── Exportar CSV de Matriz ──────────────────────────────────────────────────
function exportarMatrizCsv() {
  if (rutasFiltradas.value.length === 0) return

  const headers = ['Ruta', 'Preventista', 'Canal']
  fechasRango.value.forEach(f => {
    headers.push(`${f} (Web In)`, `${f} (Web Out)`, `${f} (App In)`, `${f} (App Out)`)
  })

  const rows = [headers]

  rutasFiltradas.value.forEach(r => {
    const row = [r.ruta, r.vendedor, r.canal]
    fechasRango.value.forEach(f => {
      const d = r.dias ? r.dias[f] : null
      row.push(
        d ? d.ingreso_sistema : '-',
        d ? d.salida_sistema : '-',
        d ? d.ingreso_movil : '-',
        d ? d.salida_movil : '-'
      )
    })
    rows.push(row)
  })

  const csvContent = '\uFEFF' + rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `horarios_matriz_${store.fechaInicio}_${store.fechaCorte}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// ── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  store.inicializarDesdeStorage(props)
  await consultarMatriz()
})

onBeforeUnmount(() => {
  if (timerAutoguardado) clearTimeout(timerAutoguardado)
  if (mapaInstancia) {
    mapaInstancia.remove()
    mapaInstancia = null
  }
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
