<template>
  <SupervisorLayout title="Avance > Cumplimiento MTD">
    <div class="space-y-2.5 font-sans select-none">
      <!-- Subnavegación de Módulo Avance -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- Barra Superior de Filtros Transversales -->
      <AvanceFiltrosBar @consultar="ejecutarConsulta" />

      <!-- SKELETON LOADER -->
      <div v-if="store.cargandoCumplimiento" class="h-[calc(100vh-8rem)] apple-glass-card rounded-[16px] p-4 animate-pulse space-y-2 flex flex-col justify-between">
        <div class="h-8 bg-black/[0.04] rounded-[8px]"></div>
        <div v-for="row in 14" :key="row" class="h-8 bg-black/[0.02] rounded-[8px] flex items-center px-3 gap-3">
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

      <!-- ── VISTA PRINCIPAL: RUTAS, HISTÓRICO 3M, SUGERIDO Y OBJETIVOS ── -->
      <section
        v-else-if="rutasFiltradas.length > 0"
        class="h-[calc(100vh-8rem)] flex flex-col rounded-[16px] apple-glass-card overflow-hidden select-none border border-white/80 shadow-[0_12px_36px_-6px_rgba(15,23,42,0.06)]"
      >
        <div class="shrink-0 px-4 py-2.5 bg-white/40 backdrop-blur-md border-b border-black/[0.05] flex justify-between items-center gap-3">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
            <span class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase">
              Avance por Rutas · Histórico 3M, Sugerido de Hábitos y Objetivos Mensuales
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
                  @click="toggleSortKpi('cartera')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'cartera' ? 'bg-blue-50/70' : ''"
                >
                  Cartera Oficial
                </th>
                <th
                  @click="toggleSortKpi('logrado')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'logrado' ? 'bg-blue-50/70' : ''"
                >
                  Compradores MTD
                </th>
                <th
                  @click="toggleSortKpi('pct')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'pct' ? 'bg-blue-50/70' : ''"
                >
                  % Cobertura
                </th>
                <th
                  @click="toggleSortKpi('mes_1_bs')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'mes_1_bs' ? 'bg-blue-50/70' : ''"
                >
                  {{ mesesCerradosGlobal[0]?.nombre || 'Mes -3' }} (Bs)
                </th>
                <th
                  @click="toggleSortKpi('mes_2_bs')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'mes_2_bs' ? 'bg-blue-50/70' : ''"
                >
                  {{ mesesCerradosGlobal[1]?.nombre || 'Mes -2' }} (Bs)
                </th>
                <th
                  @click="toggleSortKpi('mes_3_bs')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'mes_3_bs' ? 'bg-blue-50/70' : ''"
                >
                  {{ mesesCerradosGlobal[2]?.nombre || 'Mes -1' }} (Bs)
                </th>
                <th
                  @click="toggleSortKpi('total_3m_bs')"
                  class="sticky top-0 z-30 bg-[#F8F8FA] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                  :class="sortKpiCol === 'total_3m_bs' ? 'bg-blue-50/70' : ''"
                >
                  Total 3M (Bs)
                </th>
                <th
                  @click="toggleSortKpi('total_sugerido')"
                  class="sticky top-0 z-30 bg-purple-50/60 text-purple-900 py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-purple-100/60 transition-colors select-none font-bold"
                  :class="sortKpiCol === 'total_sugerido' ? 'bg-purple-100' : ''"
                  title="Suma total del sugerido por hábitos de los clientes de la ruta"
                >
                  Total Sugerido (Bs)
                </th>
                <th
                  @click="toggleSortKpi('objetivo_mes')"
                  class="sticky top-0 z-30 bg-blue-50/60 text-[#0071E3] py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-blue-100/60 transition-colors select-none font-bold"
                  :class="sortKpiCol === 'objetivo_mes' ? 'bg-blue-100' : ''"
                  title="Objetivo mensual asignado a la ruta en fact_metas"
                >
                  Objetivo Mes (Bs)
                </th>
                <th
                  @click="toggleSortKpi('venta_mtd')"
                  class="sticky top-0 z-30 bg-emerald-50/50 text-emerald-900 py-2.5 px-3 border-b border-black/[0.08] text-right font-mono shadow-[inset_0_-1px_0_rgba(0,0,0,0.08)] cursor-pointer hover:bg-emerald-100/50 transition-colors select-none font-bold"
                  :class="sortKpiCol === 'venta_mtd' ? 'bg-emerald-100' : ''"
                  title="Venta total facturada en el mes actual"
                >
                  Venta MTD (Bs)
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
                  <td class="py-2 px-3 text-right font-medium text-[#1D1D1F]">{{ r.cartera ?? 0 }} Cli</td>
                  <td class="py-2 px-3 text-right font-bold text-[#1D1D1F]">{{ r.logrado ?? 0 }} Cli</td>
                  <td class="py-2 px-3 text-right font-bold text-[12px]" :class="colorPct(r.pct)">
                    {{ formatPct(r.pct) }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono text-[#1D1D1F]">Bs {{ formatNum(r.mes_1_bs, 2) }}</td>
                  <td class="py-2 px-3 text-right font-mono text-[#1D1D1F]">Bs {{ formatNum(r.mes_2_bs, 2) }}</td>
                  <td class="py-2 px-3 text-right font-mono text-[#1D1D1F]">Bs {{ formatNum(r.mes_3_bs, 2) }}</td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-[#1D1D1F]">Bs {{ formatNum(r.total_3m_bs, 2) }}</td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-purple-900 bg-purple-50/30">
                    Bs {{ formatNum(r.total_sugerido, 2) }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-[#6B21A8] bg-blue-50/30">
                    Bs {{ formatNum(r.objetivo_mes, 2) }}
                  </td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700 bg-emerald-50/20">
                    Bs {{ formatNum(r.venta_mtd, 2) }}
                  </td>
                </tr>

                <!-- FILA EXPANDIBLE: Desglose de la Ruta (Venta por Día vs Por Clientes) -->
                <tr v-if="rutaExpandida === r.ruta" class="bg-black/[0.02]">
                  <td :colspan="modoActivo === 'cob_core' ? 12 : (esCobertura ? 10 : 8)" class="p-3.5 border-y border-black/[0.08] shadow-[inset_0_2px_4px_rgba(0,0,0,0.02)] font-sans">
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
                                <strong class="text-blue-600 font-bold">{{ clientesConHistorico.length }} con histórico 3M</strong> ·
                                <strong class="text-emerald-600 font-bold">{{ clientesConCompra.length }} compradores MTD</strong> ·
                                Acumulado 3M: <strong class="text-[#0071E3] font-bold">Bs {{ formatNum(totales3mRuta.total_3m, 2) }}</strong> ·
                                Total Sugerido: <strong class="text-purple-900 font-bold">Bs {{ formatNum(totales3mRuta.total_sugerido || totalSugeridoClientes, 2) }}</strong> ·
                                Objetivo Mes: <strong class="text-purple-700 font-bold">Bs {{ formatNum(totales3mRuta.objetivo_mes || totalObjetivoClientes, 2) }}</strong>
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
                                Compraron MTD ({{ clientesConCompra.length }})
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

                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'monto_mes_1' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('monto_mes_1')"
                                  :title="`Ordenar por ${mesesCerradosRuta[0]?.nombre || 'Mes -3'}`"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>{{ mesesCerradosRuta[0]?.corto || 'Mes -3' }} (Bs)</span>
                                    <span v-if="sortClientesCol === 'monto_mes_1'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'monto_mes_2' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('monto_mes_2')"
                                  :title="`Ordenar por ${mesesCerradosRuta[1]?.nombre || 'Mes -2'}`"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>{{ mesesCerradosRuta[1]?.corto || 'Mes -2' }} (Bs)</span>
                                    <span v-if="sortClientesCol === 'monto_mes_2'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'monto_mes_3' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('monto_mes_3')"
                                  :title="`Ordenar por ${mesesCerradosRuta[2]?.nombre || 'Mes -1'}`"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>{{ mesesCerradosRuta[2]?.corto || 'Mes -1' }} (Bs)</span>
                                    <span v-if="sortClientesCol === 'monto_mes_3'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-28 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors"
                                  :class="sortClientesCol === 'total_monto_3m' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('total_monto_3m')"
                                  title="Ordenar por Total Histórico 3M"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Total 3M (Bs)</span>
                                    <span v-if="sortClientesCol === 'total_monto_3m'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors bg-blue-50/50"
                                  :class="sortClientesCol === 'peso_3m' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('peso_3m')"
                                  title="Ordenar por Peso en Acumulado 3M de la Ruta"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Peso 3M (%)</span>
                                    <span v-if="sortClientesCol === 'peso_3m'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors bg-purple-50/50"
                                  :class="sortClientesCol === 'total_sugerido' ? 'text-[#0071E3]' : ''"
                                  @click="ordenarClientes('total_sugerido')"
                                  title="Ordenar por Total Sugerido de Hábitos (Bs)"
                                >
                                  <div class="flex items-center justify-end gap-1">
                                    <span>Sugerido (Bs)</span>
                                    <span v-if="sortClientesCol === 'total_sugerido'" class="text-[10px]">{{ sortClientesDir === 'asc' ? '▲' : '▼' }}</span>
                                  </div>
                                </th>
                                <th
                                  class="py-1.5 px-2.5 text-right w-24 font-mono cursor-pointer hover:bg-black/[0.04] transition-colors bg-blue-50/50"
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

                                  <td class="py-1.5 px-2.5 text-right font-mono text-[#48484A]">
                                    {{ cli.monto_mes_1 > 0 ? `Bs ${formatNum(cli.monto_mes_1, 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono text-[#48484A]">
                                    {{ cli.monto_mes_2 > 0 ? `Bs ${formatNum(cli.monto_mes_2, 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono text-[#48484A]">
                                    {{ cli.monto_mes_3 > 0 ? `Bs ${formatNum(cli.monto_mes_3, 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#1D1D1F]">
                                    Bs {{ formatNum(cli.total_monto_3m, 2) }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#0071E3] bg-blue-50/20">
                                    {{ formatPct(cli.peso_3m) }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-purple-900 bg-purple-50/20">
                                    {{ cli.total_sugerido > 0 ? `Bs ${formatNum(cli.total_sugerido, 2)}` : '-' }}
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#6B21A8] bg-blue-50/20">
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
                                </tr>
                                <!-- Detalle acordeón de facturas y fechas del cliente -->
                                <tr v-if="clienteExpandidoId === cli.cliente_id" class="bg-blue-50/20">
                                  <td colspan="13" class="p-2.5 border-y border-black/[0.05]">
                                  <div class="apple-glass-card rounded-[10px] p-3 space-y-2.5 bg-white/95 border border-black/[0.06] shadow-2xs">
                                    <div v-if="cargandoAccordionCliente && !detalleClienteFechasMap[cli.cliente_id]" class="py-4 text-center text-[10px] text-[#86868B] flex items-center justify-center gap-2 font-sans font-medium">
                                      <div class="w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                                      <span>Cargando histórico facturado de {{ cli.cliente }}...</span>
                                    </div>
                                    <template v-else-if="detalleClienteFechasMap[cli.cliente_id]">
                                      <!-- Barra de Controles: Pestañas y Buscador -->
                                      <div class="flex flex-wrap items-center justify-between gap-2">
                                        <!-- Selector de Pestañas: Acumulado y Sugerido vs Compras Mes Actual vs Fechas -->
                                        <div class="inline-flex apple-control-pill p-0.5 rounded-[8px] text-[10px]">
                                          <button
                                            type="button"
                                            @click="modoHistorialCliente = 'sugerido'"
                                            class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
                                            :class="modoHistorialCliente === 'sugerido' ? 'glass-active-pill text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                                            title="Ver acumulado de productos y sugerido a comprar"
                                          >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <span>Acumulado y Sugerido ({{ productosHistorialFiltrados(cli.cliente_id).length }})</span>
                                          </button>
                                          <button
                                            type="button"
                                            @click="modoHistorialCliente = 'compras_mes'"
                                            class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
                                            :class="modoHistorialCliente === 'compras_mes' ? 'glass-active-pill text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                                            title="Ver si compró o no en el mes actual y la cantidad comprada"
                                          >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                            </svg>
                                            <span>Compras Mes Actual MTD</span>
                                          </button>
                                          <button
                                            type="button"
                                            @click="modoHistorialCliente = 'fechas'"
                                            class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
                                            :class="modoHistorialCliente === 'fechas' ? 'glass-active-pill text-[#0071E3] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                                            title="Ver facturas cronológicas"
                                          >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span>Por Fechas</span>
                                          </button>
                                        </div>

                                        <!-- Buscador rápido dentro del historial del cliente -->
                                        <div class="relative">
                                          <input
                                            v-model="busquedaHistorialCliente"
                                            type="text"
                                            placeholder="Filtrar producto, código..."
                                            class="apple-control-pill rounded-[8px] pl-6 pr-2.5 py-1 text-[10px] w-52 text-[#1D1D1F] focus:outline-none focus:ring-1 focus:ring-[#0071E3] font-sans"
                                          />
                                          <svg class="w-3 h-3 text-[#86868B] absolute left-2 top-1.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                          </svg>
                                        </div>
                                      </div>

                                      <!-- TABLA 1: ACUMULADO DE PRODUCTOS Y LO QUE SE SUGIERE QUE COMPRE -->
                                      <div
                                        v-if="modoHistorialCliente === 'sugerido'"
                                        class="overflow-x-auto custom-scroll border border-black/[0.06] rounded-[8px] max-h-[340px]"
                                      >
                                        <table class="w-full text-left border-collapse text-[10px] font-mono whitespace-nowrap">
                                          <thead class="bg-[#F8F8FA] text-[#86868B] uppercase text-[9px] tracking-wider sticky top-0 z-10 border-b border-black/[0.06] select-none">
                                            <tr>
                                              <th class="py-1.5 px-2.5 text-center w-8">#</th>
                                              <th class="py-1.5 px-2.5 text-left w-20 cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('codigo')">
                                                <div class="flex items-center gap-1">
                                                  <span>Código</span>
                                                  <span v-if="sortProdCol === 'codigo'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-left font-sans cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('producto')">
                                                <div class="flex items-center gap-1">
                                                  <span>Producto</span>
                                                  <span v-if="sortProdCol === 'producto'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-right cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('total_bs_3m')" title="Total de lo que compró en el acumulado de los últimos 3 meses">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span>Total Compra 3M</span>
                                                  <span v-if="sortProdCol === 'total_bs_3m'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-right bg-purple-50/70 text-purple-950 font-bold cursor-pointer hover:bg-purple-100" @click="ordenarProductos('monto_sugerido')" title="Cantidad y monto que se sugiere que compre en el mes">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span>Sugerido a Comprar</span>
                                                  <span v-if="sortProdCol === 'monto_sugerido'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-right bg-emerald-50/70 text-emerald-950 font-bold cursor-pointer hover:bg-emerald-100" @click="ordenarProductos('total_bs_mes_actual')" title="Compra acumulada del mes: en qué cantidad y monto va">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span>Compra Acumulada Mes</span>
                                                  <span v-if="sortProdCol === 'total_bs_mes_actual'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                            </tr>
                                          </thead>
                                          <tbody class="divide-y divide-black/[0.03] bg-white">
                                            <tr
                                              v-for="(prod, pidx) in productosHistorialFiltrados(cli.cliente_id)"
                                              :key="prod.codigo + pidx"
                                              class="hover:bg-purple-50/20 transition-colors"
                                            >
                                              <td class="py-1.5 px-2.5 text-center text-[#86868B]">{{ pidx + 1 }}</td>
                                              <td class="py-1.5 px-2.5 font-bold text-[#6E6E73]">{{ prod.codigo }}</td>
                                              <td class="py-1.5 px-2.5 font-sans font-medium text-[#1D1D1F] max-w-[260px] truncate" :title="prod.producto">
                                                {{ prod.producto }}
                                                <span v-if="prod.categoria" class="text-[8px] text-[#86868B] block truncate">{{ prod.categoria }}</span>
                                              </td>

                                              <!-- Total Compra 3M -->
                                              <td class="py-1.5 px-2.5 text-right font-mono">
                                                <div class="font-bold text-[#1D1D1F]">Bs {{ formatNum(prod.total_bs_3m, 2) }}</div>
                                                <div class="text-[9px] text-[#6E6E73]">{{ prod.total_cantidad_3m }} u</div>
                                              </td>

                                              <!-- Sugerido a Comprar -->
                                              <td class="py-1.5 px-2.5 text-right font-mono bg-purple-50/30">
                                                <div class="font-bold text-purple-900 text-[11px]">Bs {{ formatNum(prod.monto_sugerido, 2) }}</div>
                                                <div class="text-[9px] text-purple-800">
                                                  <span class="inline-block px-1 py-0.2 rounded bg-purple-100 text-purple-900 font-bold">{{ prod.cant_sugerida }} u</span>
                                                  <span v-if="prod.precio_ultima_compra > 0" class="text-[8px] text-purple-700 ml-1">(P.Últ: Bs {{ formatNum(prod.precio_ultima_compra, 2) }})</span>
                                                </div>
                                              </td>

                                              <!-- Compra Acumulada del Mes: en qué cantidad y monto va -->
                                              <td class="py-1.5 px-2.5 text-right font-mono" :class="prod.total_cantidad_mes_actual > 0 ? 'bg-emerald-50/30' : ''">
                                                <template v-if="prod.total_cantidad_mes_actual > 0">
                                                  <div class="font-bold text-emerald-800 text-[11px]">Bs {{ formatNum(prod.total_bs_mes_actual, 2) }}</div>
                                                  <div class="text-[9px] text-emerald-700 flex items-center justify-end gap-1">
                                                   <span class="font-bold">{{ prod.total_cantidad_mes_actual }} u</span>
                                                   <span
                                                     v-if="prod.cumple_sugerido"
                                                     class="inline-block px-1 py-0.2 rounded text-[7px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                                   >
                                                     CUMPLE
                                                   </span>
                                                   <span
                                                     v-else-if="prod.cant_sugerida > 0"
                                                     class="inline-block px-1 py-0.2 rounded text-[7px] font-bold bg-amber-100 text-amber-800 border border-amber-300"
                                                   >
                                                     PARCIAL
                                                   </span>
                                                  </div>
                                                </template>
                                                <template v-else>
                                                  <div class="text-[#86868B]">-</div>
                                                  <div v-if="prod.cant_sugerida > 0" class="text-[8px] text-[#86868B]">0 u · Pendiente</div>
                                                </template>
                                              </td>
                                            </tr>
                                          </tbody>
                                          <!-- PIE DE TABLA CON TOTALES CONSOLIDADOS -->
                                          <tfoot class="bg-[#F8F8FA] text-[#1D1D1F] font-bold sticky bottom-0 z-20 font-mono border-t border-black/[0.08]">
                                            <tr>
                                              <td colspan="3" class="py-2 px-2.5 text-center text-[10px] text-[#6E6E73] font-sans">
                                                TOTAL ({{ productosHistorialFiltrados(cli.cliente_id).length }} SKUs)
                                              </td>
                                              <td class="py-2 px-2.5 text-right font-mono">
                                                <div class="font-bold text-[#1D1D1F] text-[11px]">Bs {{ formatNum(totalProdCol(cli.cliente_id, 'total_bs_3m'), 2) }}</div>
                                                <div class="text-[9px] text-[#6E6E73]">{{ totalProdCol(cli.cliente_id, 'total_cantidad_3m') }} u totales</div>
                                              </td>
                                              <td class="py-2 px-2.5 text-right font-mono bg-purple-100 text-purple-950 font-bold border-x border-purple-200">
                                                <div class="text-[12px] font-black">
                                                  Bs {{ formatNum(totalProdCol(cli.cliente_id, 'monto_sugerido'), 2) }}
                                                </div>
                                                <div class="text-[9px] text-purple-900 font-bold">
                                                  {{ totalProdCol(cli.cliente_id, 'cant_sugerida') }} u sugeridas
                                                </div>
                                              </td>
                                              <td class="py-2 px-2.5 text-right font-mono bg-emerald-100 text-emerald-950 font-bold">
                                                <div class="text-[12px] font-black text-emerald-800">
                                                  Bs {{ formatNum(totalProdCol(cli.cliente_id, 'total_bs_mes_actual'), 2) }}
                                                </div>
                                                <div class="text-[9px] text-emerald-800 font-bold">
                                                  {{ totalProdCol(cli.cliente_id, 'total_cantidad_mes_actual') }} u facturadas
                                                </div>
                                              </td>
                                            </tr>
                                          </tfoot>
                                        </table>
                                        <div v-if="productosHistorialFiltrados(cli.cliente_id).length === 0" class="text-center py-4 text-[10px] text-[#86868B] font-sans">
                                          No se encontraron productos facturados para el criterio de búsqueda.
                                        </div>
                                      </div>

                                      <!-- TABLA 2: SEGUIMIENTO MES ACTUAL (SI SE COMPRÓ O NO Y LA CANTIDAD QUE SE COMPRÓ) -->
                                      <div
                                        v-else-if="modoHistorialCliente === 'compras_mes'"
                                        class="overflow-x-auto custom-scroll border border-black/[0.06] rounded-[8px] max-h-[340px]"
                                      >
                                        <table class="w-full text-left border-collapse text-[10px] font-mono whitespace-nowrap">
                                          <thead class="bg-[#F8F8FA] text-[#86868B] uppercase text-[9px] tracking-wider sticky top-0 z-10 border-b border-black/[0.06] select-none">
                                            <tr>
                                              <th class="py-1.5 px-2.5 text-center w-8">#</th>
                                              <th class="py-1.5 px-2.5 text-left w-20 cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('codigo')">
                                                <div class="flex items-center gap-1">
                                                  <span>Código</span>
                                                  <span v-if="sortProdCol === 'codigo'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-left font-sans cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('producto')">
                                                <div class="flex items-center gap-1">
                                                  <span>Producto</span>
                                                  <span v-if="sortProdCol === 'producto'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-center cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('compro_mes_actual')" title="Muestra si el cliente compró o no este producto en el mes actual">
                                                <div class="flex items-center justify-center gap-1">
                                                  <span>¿Compró en el Mes?</span>
                                                  <span v-if="sortProdCol === 'compro_mes_actual'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-right bg-emerald-50/50 text-emerald-900 font-bold cursor-pointer hover:bg-emerald-100/50" @click="ordenarProductos('total_cantidad_mes_actual')" title="Cantidad total que compró en el mes actual">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span>Cantidad Comprada Mes</span>
                                                  <span v-if="sortProdCol === 'total_cantidad_mes_actual'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-right bg-emerald-50/50 text-emerald-900 font-bold cursor-pointer hover:bg-emerald-100/50" @click="ordenarProductos('total_bs_mes_actual')" title="Monto facturado en el mes actual">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span>Monto Comprado Mes (Bs)</span>
                                                  <span v-if="sortProdCol === 'total_bs_mes_actual'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-right text-purple-900 cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('cant_sugerida')" title="Sugerencia de referencia calculada del histórico">
                                                <div class="flex items-center justify-end gap-1">
                                                  <span>Sugerido (Ref.)</span>
                                                  <span v-if="sortProdCol === 'cant_sugerida'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-center cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('estado_cobertura')">
                                                <div class="flex items-center justify-center gap-1">
                                                  <span>Estado Cobertura</span>
                                                  <span v-if="sortProdCol === 'estado_cobertura'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                              <th class="py-1.5 px-2.5 text-center text-[#86868B] cursor-pointer hover:bg-black/[0.04]" @click="ordenarProductos('ultima_fecha')">
                                                <div class="flex items-center justify-center gap-1">
                                                  <span>Últ. Compra</span>
                                                  <span v-if="sortProdCol === 'ultima_fecha'" class="text-[8px]">{{ sortProdDir === 'asc' ? '▲' : '▼' }}</span>
                                                </div>
                                              </th>
                                            </tr>
                                          </thead>
                                          <tbody class="divide-y divide-black/[0.03] bg-white">
                                            <tr
                                              v-for="(prod, pidx) in productosHistorialFiltrados(cli.cliente_id)"
                                              :key="prod.codigo + pidx"
                                              class="hover:bg-emerald-50/20 transition-colors"
                                            >
                                              <td class="py-1.5 px-2.5 text-center text-[#86868B]">{{ pidx + 1 }}</td>
                                              <td class="py-1.5 px-2.5 font-bold text-[#6E6E73]">{{ prod.codigo }}</td>
                                              <td class="py-1.5 px-2.5 font-sans font-medium text-[#1D1D1F] max-w-[240px] truncate" :title="prod.producto">
                                                {{ prod.producto }}
                                                <span v-if="prod.categoria" class="text-[8px] text-[#86868B] block truncate">{{ prod.categoria }}</span>
                                              </td>
                                              <!-- SI SE COMPRÓ O NO -->
                                              <td class="py-1.5 px-2.5 text-center">
                                                <span
                                                  v-if="prod.compro_mes_actual"
                                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                                >
                                                  <svg class="w-2.5 h-2.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                  COMPRÓ
                                                </span>
                                                <span
                                                  v-else
                                                  class="inline-block px-2 py-0.5 rounded text-[9px] font-medium bg-black/[0.04] text-[#86868B]"
                                                >
                                                  SIN COMPRA
                                                </span>
                                              </td>
                                              <!-- CANTIDAD QUE SE COMPRÓ -->
                                              <td class="py-1.5 px-2.5 text-right font-mono bg-emerald-50/30">
                                                <span v-if="prod.total_cantidad_mes_actual > 0" class="font-bold text-emerald-800 text-[11px]">
                                                  {{ prod.total_cantidad_mes_actual }} u
                                                </span>
                                                <span v-else class="text-[#86868B]">-</span>
                                              </td>
                                              <td class="py-1.5 px-2.5 text-right font-mono bg-emerald-50/30">
                                                <span v-if="prod.total_bs_mes_actual > 0" class="font-bold text-emerald-800">
                                                  Bs {{ formatNum(prod.total_bs_mes_actual, 2) }}
                                                </span>
                                                <span v-else class="text-[#86868B]">-</span>
                                              </td>
                                              <td class="py-1.5 px-2.5 text-right font-mono text-[#6E6E73] text-[9px]">
                                                {{ prod.cant_sugerida }} u · Bs {{ formatNum(prod.monto_sugerido, 2) }}
                                              </td>
                                              <td class="py-1.5 px-2.5 text-center">
                                                <span
                                                  v-if="prod.cumple_sugerido"
                                                  class="inline-block px-1.5 py-0.5 rounded text-[8px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                                  title="Compró igual o más de la cantidad mensual sugerida"
                                                >
                                                  CUMPLE
                                                </span>
                                                <span
                                                  v-else-if="prod.compro_mes_actual"
                                                  class="inline-block px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-100 text-amber-800 border border-amber-300"
                                                  title="Compró en el mes pero no alcanzó la cantidad sugerida"
                                                >
                                                  PARCIAL
                                                </span>
                                                <span
                                                  v-else-if="prod.cant_sugerida > 0"
                                                  class="inline-block px-1.5 py-0.5 rounded text-[8px] font-medium bg-black/[0.04] text-[#86868B]"
                                                >
                                                  PENDIENTE
                                                </span>
                                                <span v-else class="text-[#86868B] text-[8px]">-</span>
                                              </td>
                                              <td class="py-1.5 px-2.5 text-center text-[#86868B] text-[9px] whitespace-nowrap">
                                                {{ prod.ultima_fecha }}
                                              </td>
                                            </tr>
                                          </tbody>
                                          <!-- PIE DE TABLA: Total comprado en el mes y cobertura -->
                                          <tfoot class="bg-[#F8F8FA] text-[#1D1D1F] font-bold sticky bottom-0 z-20 font-mono border-t border-black/[0.08]">
                                            <tr>
                                              <td colspan="3" class="py-2 px-2.5 text-center text-[10px] text-[#6E6E73] font-sans">
                                                TOTAL ({{ productosHistorialFiltrados(cli.cliente_id).length }} SKUs)
                                              </td>
                                              <td class="py-2 px-2.5 text-center text-[10px] text-emerald-800 font-sans font-bold">
                                                {{ totalSkusCubiertosFiltrados(cli.cliente_id) }} SKUs con compra
                                              </td>
                                              <td class="py-2 px-2.5 text-right text-emerald-900 bg-emerald-100/60 font-bold font-mono">
                                                {{ totalProdCol(cli.cliente_id, 'total_cantidad_mes_actual') }} u
                                              </td>
                                              <td class="py-2 px-2.5 text-right text-emerald-900 bg-emerald-100/60 font-bold font-mono text-[11px]">
                                                Bs {{ formatNum(totalProdCol(cli.cliente_id, 'total_bs_mes_actual'), 2) }}
                                              </td>
                                              <td class="py-2 px-2.5 text-right text-purple-900 font-mono text-[9px]">
                                                {{ totalProdCol(cli.cliente_id, 'cant_sugerida') }} u · Bs {{ formatNum(totalProdCol(cli.cliente_id, 'monto_sugerido'), 2) }}
                                              </td>
                                              <td class="py-2 px-2.5 text-center text-[9px] text-[#0071E3] font-sans">
                                                {{ totalSkusCubiertosFiltrados(cli.cliente_id) }}/{{ totalSkusSugeridosFiltrados(cli.cliente_id) }} cub.
                                              </td>
                                              <td class="py-2 px-2.5 text-center text-[#86868B] text-[8px]">-</td>
                                            </tr>
                                          </tfoot>
                                        </table>
                                        <div v-if="productosHistorialFiltrados(cli.cliente_id).length === 0" class="text-center py-4 text-[10px] text-[#86868B] font-sans">
                                          No se encontraron productos facturados para el criterio de búsqueda.
                                        </div>
                                      </div>

                                      <!-- TABLA 3: VISTA CRONOLÓGICA POR FECHA DE COMPRA -->
                                      <div
                                        v-else-if="modoHistorialCliente === 'fechas'"
                                        class="space-y-1.5 max-h-[280px] overflow-y-auto custom-scroll pr-1 font-mono"
                                      >
                                        <div
                                          v-for="dia in fechasHistorialFiltradas(cli.cliente_id)"
                                          :key="dia.fecha"
                                          class="p-2 rounded-[8px] border border-black/[0.04] bg-white space-y-1 text-[10px]"
                                        >
                                          <div class="flex items-center justify-between border-b border-black/[0.04] pb-1">
                                            <div class="flex items-center gap-2">
                                              <span class="font-bold text-[#1D1D1F] text-[11px]">{{ dia.fecha }}</span>
                                              <span
                                                class="text-[9px] px-1.5 py-0.2 rounded font-sans font-bold"
                                                :class="dia.es_mes_actual ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700'"
                                              >
                                                {{ dia.es_mes_actual ? 'Mes Actual' : 'Histórico 3M' }}
                                              </span>
                                              <span class="text-[9px] text-[#86868B] font-sans">Factura(s): {{ dia.facturas }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                              <span class="text-[#86868B] text-[9px]">{{ dia.total_cantidad }} u</span>
                                              <span class="font-bold text-[#0071E3] text-[11px]">Bs {{ formatNum(dia.total_bs ?? dia.monto_bs, 2) }}</span>
                                            </div>
                                          </div>
                                          <div class="text-[9px] text-[#86868B] font-sans flex items-center justify-between">
                                            <span>Preventista: {{ dia.vendedor }}</span>
                                            <span>{{ (dia.items || []).length }} referencias</span>
                                          </div>
                                          <div v-if="dia.items && dia.items.length > 0" class="mt-1 space-y-0.5">
                                            <div
                                              v-for="(item, iidx) in dia.items"
                                              :key="iidx"
                                              class="flex items-center justify-between text-[9px] py-0.5 px-2 rounded bg-black/[0.02]"
                                            >
                                              <div class="flex items-center gap-1.5 truncate max-w-[420px]">
                                                <span class="font-bold text-[#6E6E73] shrink-0 font-mono">[{{ item.codigo }}]</span>
                                                <span class="text-[#1D1D1F] truncate font-sans">{{ item.producto }}</span>
                                              </div>
                                              <span class="font-bold text-[#1D1D1F] font-mono shrink-0 ml-2">
                                                {{ item.cantidad }} u · Bs {{ formatNum(item.monto_bs, 2) }}
                                              </span>
                                            </div>
                                          </div>
                                        </div>
                                        <div v-if="fechasHistorialFiltradas(cli.cliente_id).length === 0" class="text-center py-4 text-[10px] text-[#86868B] font-sans">
                                          No se encontraron fechas de compra para el criterio de búsqueda.
                                        </div>
                                      </div>
                                    </template>
                                  </div>
                                </td>
                              </tr>
                            </template>
                          </tbody>
                          <tfoot class="bg-[#F8F8FA] text-[#1D1D1F] font-bold sticky bottom-0 z-20 font-mono tabular-nums border-t border-black/[0.08]">
                            <tr>
                              <td colspan="4" class="py-1.5 px-2.5 text-center text-[10px] text-[#6E6E73] font-sans">
                                TOTAL RUTA {{ r.ruta }} ({{ carteraClientes.length }} Clientes)
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono">
                                {{ (totales3mRuta.mes_1 || 0) > 0 ? `Bs ${formatNum(totales3mRuta.mes_1, 2)}` : '-' }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono">
                                {{ (totales3mRuta.mes_2 || 0) > 0 ? `Bs ${formatNum(totales3mRuta.mes_2, 2)}` : '-' }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono">
                                {{ (totales3mRuta.mes_3 || 0) > 0 ? `Bs ${formatNum(totales3mRuta.mes_3, 2)}` : '-' }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#1D1D1F]">
                                Bs {{ formatNum(totales3mRuta.total_3m || 0, 2) }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#0071E3] bg-blue-50/30">
                                100.0%
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-purple-900 bg-purple-100/70 text-[11px]">
                                Bs {{ formatNum(totales3mRuta.total_sugerido || totalSugeridoClientes, 2) }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-[#6B21A8] bg-blue-100/70 text-[11px]">
                                Bs {{ formatNum(totales3mRuta.objetivo_mes || totalObjetivoClientes, 2) }}
                              </td>
                              <td class="py-1.5 px-2.5 text-right font-mono font-bold text-emerald-700 bg-emerald-50/30">
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
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ totalesKpi?.cartera ?? 0 }} Cli</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">{{ totalesKpi?.logrado ?? 0 }} Cli</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA] font-bold text-[12px]" :class="colorPct(totalesKpi?.pct)">
                  {{ formatPct(totalesKpi?.pct) }}
                </td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">Bs {{ formatNum(totalesKpi?.mes_1_bs, 2) }}</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">Bs {{ formatNum(totalesKpi?.mes_2_bs, 2) }}</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA]">Bs {{ formatNum(totalesKpi?.mes_3_bs, 2) }}</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-[#F8F8FA] text-[#1D1D1F] font-bold">Bs {{ formatNum(totalesKpi?.total_3m_bs, 2) }}</td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-purple-100/80 text-purple-900 font-bold text-[12px]">
                  Bs {{ formatNum(totalesKpi?.total_sugerido, 2) }}
                </td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-blue-100/80 text-[#0071E3] font-bold text-[12px]">
                  Bs {{ formatNum(totalesKpi?.objetivo_mes, 2) }}
                </td>
                <td class="py-2.5 px-3 text-right sticky bottom-0 z-30 bg-emerald-100/80 text-emerald-800 font-bold text-[12px]">
                  Bs {{ formatNum(totalesKpi?.venta_mtd, 2) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <!-- Estado Vacío -->
      <div v-else class="h-64 apple-glass-card rounded-[14px] p-10 flex items-center justify-center text-[#86868B] text-[12px]">
        No se encontraron datos para los filtros especificados.
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

const modoActivo = ref('cob_core')

// Estado de ordenamiento de rutas
const sortKpiCol = ref('total_3m_bs')
const sortKpiDir = ref('desc')

const sortLabelsKpi = {
  ruta: 'Ruta',
  cartera: 'Cartera Oficial',
  logrado: 'Compradores MTD',
  pct: '% Cobertura',
  mes_1_bs: 'Mes -3',
  mes_2_bs: 'Mes -2',
  mes_3_bs: 'Mes -1',
  total_3m_bs: 'Total Histórico 3M',
  total_sugerido: 'Total Sugerido (Bs)',
  objetivo_mes: 'Objetivo Mes (Bs)',
  venta_mtd: 'Venta MTD (Bs)'
}

function toggleSortKpi(col) {
  if (sortKpiCol.value === col) {
    sortKpiDir.value = sortKpiDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortKpiCol.value = col
    sortKpiDir.value = 'desc'
  }
}

const rutaExpandida = ref(null)
const cargandoClientes = ref(false)
const carteraClientes = ref([])
const diasRuta = ref([])
const modoDetalleRuta = ref('clientes') // 'dias' | 'clientes'
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

const totalSugeridoClientes = computed(() => {
  return carteraClientes.value.reduce((acc, c) => acc + Number(c.total_sugerido || 0), 0)
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

const filtroClientesTab = ref('todos')
const busquedaClienteTexto = ref('')

// Ordenamiento de clientes
const sortClientesCol = ref('peso_3m')
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
const detalleClienteFechasMap = reactive({})
const modoHistorialCliente = ref('sugerido') // 'sugerido' | 'compras_mes' | 'fechas'
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
  if (busquedaHistorialCliente.value.trim()) {
    const q = busquedaHistorialCliente.value.toLowerCase().trim()
    lista = datos.filter(p =>
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
    if (col === 'compro_mes_actual') {
      const cA = a.compro_mes_actual ? 1 : 0
      const cB = b.compro_mes_actual ? 1 : 0
      if (cA !== cB) return dir * (cA - cB)
      return dir * (Number(a.total_cantidad_mes_actual || 0) - Number(b.total_cantidad_mes_actual || 0))
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
    const res = await axios.post('/supervisor/avance/cumplimiento/cliente-detalle-fechas', {
      cliente_id: cliente.cliente_id,
      ruta,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      kpi: 'cob_core'
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
    if (col === 'total_sugerido') {
      return dir * (Number(a.total_sugerido || 0) - Number(b.total_sugerido || 0))
    }
    if (col === 'cuota_objetivo') {
      return dir * (Number(a.cuota_objetivo || 0) - Number(b.cuota_objetivo || 0))
    }
    return 0
  })

  return copia
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
  const col = sortKpiCol.value || 'total_3m_bs'
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

  return copia
})

const totalesKpi = computed(() => {
  return store.datosCumplimiento?.totales || {}
})

function ejecutarConsulta() {
  store.aplicarFiltros()
  store.vistaCumplimiento = 'kpi'
  store.kpiActivo = 'cob_core'
  return store.cargarCumplimiento()
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
    const res = await axios.post('/supervisor/avance/cumplimiento/clientes-ruta', {
      ruta,
      kpi: 'cob_core',
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte
    })
    carteraClientes.value = res.data.clientes || []
    diasRuta.value = res.data.por_dia || []
    mesesCerradosRuta.value = res.data.meses_cerrados || mesesCerradosGlobal.value || []
    totales3mRuta.value = res.data.totales_3m_ruta || {}
    diaSeleccionadoInline.value = null
    clienteExpandidoId.value = null
    modoDetalleRuta.value = 'clientes'
    filtroClientesTab.value = 'todos'
    sortClientesCol.value = 'peso_3m'
    sortClientesDir.value = 'desc'
  } catch (err) {
    console.error('Error al cargar clientes de la ruta:', err)
  } finally {
    cargandoClientes.value = false
  }
}

function formatNum(val, decimals = 1) {
  if (val === null || val === undefined) return '0'
  return Number(val).toLocaleString('es-BO', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  })
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

onMounted(async () => {
  store.inicializarDesdeStorage(props)
  modoActivo.value = 'cob_core'
  store.vistaCumplimiento = 'kpi'
  store.kpiActivo = 'cob_core'
  await store.cargarCumplimiento()
})
</script>

