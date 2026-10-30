<template>
  <SupervisorLayout title="Avance > Entregas (Cruce Preventa vs Facturación)">
    <div class="space-y-3 font-sans select-none">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── Barra de Filtros Centralizada ── -->
      <AvanceFiltrosBar @consultar="ejecutarConsulta" />

      <!-- ── Tablero Ejecutivo de Métricas KPI (Cards Liquid Glass) ── -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5">
        <!-- 1. Total Preventas Base -->
        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.08] bg-white shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Preventas Base</span>
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
          </div>
          <div class="mt-2">
            <div class="text-xl sm:text-2xl font-black text-[#1D1D1F] font-mono tabular-nums leading-none">
              {{ cargando ? '—' : (totalesGenerales.total_pedidos || 0).toLocaleString('es-BO') }}
            </div>
            <div class="text-[11px] font-medium text-[#6E6E73] font-mono mt-1">
              Bs {{ cargando ? '—' : fmtBs(totalesGenerales.monto_preventa) }}
            </div>
          </div>
          <div class="text-[9px] font-semibold text-[#86868B] mt-2 pt-1.5 border-t border-black/[0.05] truncate">
            {{ submodo === 'diario' ? `Ref. D-1: ${fechaD1Referencia}` : 'Acumulado en rango' }}
          </div>
        </div>

        <!-- 2. Pedidos Entregados (100%) -->
        <div class="apple-glass-card rounded-[14px] p-3 border border-emerald-500/20 bg-emerald-50/20 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-[#1E6B37] uppercase tracking-wider">100% Entregados</span>
            <span class="w-2 h-2 rounded-full bg-[#1E6B37]"></span>
          </div>
          <div class="mt-2">
            <div class="text-xl sm:text-2xl font-black text-[#1E6B37] font-mono tabular-nums leading-none">
              {{ cargando ? '—' : (totalesGenerales.pedidos_entregados || 0).toLocaleString('es-BO') }}
            </div>
            <div class="text-[11px] font-bold text-[#1E6B37] font-mono mt-1">
              Bs {{ cargando ? '—' : fmtBs(totalesGenerales.monto_facturado) }}
            </div>
          </div>
          <div class="text-[9px] font-bold text-[#1E6B37] mt-2 pt-1.5 border-t border-emerald-500/15">
            {{ pctEntregados }}% de pedidos completos
          </div>
        </div>

        <!-- 3. Entregas Parciales -->
        <div class="apple-glass-card rounded-[14px] p-3 border border-amber-500/20 bg-amber-50/20 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-[#B25E00] uppercase tracking-wider">Parciales</span>
            <span class="w-2 h-2 rounded-full bg-[#B25E00]"></span>
          </div>
          <div class="mt-2">
            <div class="text-xl sm:text-2xl font-black text-[#B25E00] font-mono tabular-nums leading-none">
              {{ cargando ? '—' : (totalesGenerales.pedidos_parciales || 0).toLocaleString('es-BO') }}
            </div>
            <div class="text-[11px] font-semibold text-[#B25E00] font-mono mt-1">
              {{ pctParciales }}% de la cartera
            </div>
          </div>
          <div class="text-[9px] font-medium text-[#86868B] mt-2 pt-1.5 border-t border-amber-500/15">
            Diferencias de volumen/SKUs
          </div>
        </div>

        <!-- 4. Rechazos / Caídas (0%) -->
        <div class="apple-glass-card rounded-[14px] p-3 border border-rose-500/20 bg-rose-50/20 shadow-xs flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-[#C9342C] uppercase tracking-wider">Rechazos (0%)</span>
            <span class="w-2 h-2 rounded-full bg-[#C9342C]"></span>
          </div>
          <div class="mt-2">
            <div class="text-xl sm:text-2xl font-black text-[#C9342C] font-mono tabular-nums leading-none">
              {{ cargando ? '—' : (totalesGenerales.pedidos_caidos || 0).toLocaleString('es-BO') }}
            </div>
            <div class="text-[11px] font-bold text-[#C9342C] font-mono mt-1">
              {{ pctCaidos }}% caídas totales
            </div>
          </div>
          <div class="text-[9px] font-semibold text-[#B25E00] mt-2 pt-1.5 border-t border-rose-500/15 flex items-center justify-between">
            <span>Justificados en app:</span>
            <strong class="font-mono text-[#1D1D1F]">{{ totalJustificados }}</strong>
          </div>
        </div>

        <!-- 5. Efectividad Global & Brecha -->
        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.08] bg-white shadow-xs col-span-2 sm:col-span-1 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Efectividad Global</span>
            <span
              class="w-2 h-2 rounded-full"
              :class="efectividadGlobal >= 95 ? 'bg-[#1E6B37]' : efectividadGlobal >= 85 ? 'bg-[#B25E00]' : 'bg-[#C9342C]'"
            ></span>
          </div>
          <div class="mt-2">
            <div
              class="text-xl sm:text-2xl font-black font-mono tabular-nums leading-none"
              :class="efectividadGlobal >= 95 ? 'text-[#1E6B37]' : efectividadGlobal >= 85 ? 'text-[#B25E00]' : 'text-[#C9342C]'"
            >
              {{ cargando ? '—' : efectividadGlobal.toFixed(1) }}%
            </div>
            <div
              class="text-[11px] font-bold font-mono mt-1"
              :class="totalesGenerales.brecha_bs < 0 ? 'text-[#C9342C]' : 'text-[#1E6B37]'"
            >
              Brecha: {{ totalesGenerales.brecha_bs >= 0 ? '+' : '' }}Bs {{ fmtBs(totalesGenerales.brecha_bs) }}
            </div>
          </div>
          <!-- Barra de Progreso Apple -->
          <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mt-2">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="efectividadGlobal >= 95 ? 'bg-[#1E6B37]' : efectividadGlobal >= 85 ? 'bg-[#B25E00]' : 'bg-[#C9342C]'"
              :style="{ width: `${Math.min(Math.max(efectividadGlobal, 0), 100)}%` }"
            ></div>
          </div>
        </div>
      </div>

      <!-- ── Contenedor Principal: Modos de Vista y Matriz ── -->
      <div class="apple-glass-card rounded-[14px] shadow-xs overflow-hidden border border-black/[0.08] bg-white">
        <!-- ── Barra Superior de Navegación y Vistas ── -->
        <div class="px-4 py-3 bg-slate-50/90 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-3 text-xs">
          <!-- Segmented de Vistas: Matriz de Desglose vs Consolidado por Rutas -->
          <div class="flex items-center gap-2">
            <div class="inline-flex apple-segmented p-0.5 rounded-[9px] bg-slate-200/70">
              <button
                type="button"
                @click="vistaActiva = 'matriz'"
                class="px-3.5 py-1.5 text-[11px] rounded-[7px] transition-all cursor-pointer flex items-center gap-1.5"
                :class="
                  vistaActiva === 'matriz'
                    ? 'apple-segmented-active text-[#1D1D1F] font-bold shadow-xs'
                    : 'font-semibold text-[#6E6E73] hover:text-[#1D1D1F]'
                "
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span>Matriz de Desglose de Pedidos</span>
              </button>
              <button
                type="button"
                @click="vistaActiva = 'rutas'"
                class="px-3.5 py-1.5 text-[11px] rounded-[7px] transition-all cursor-pointer flex items-center gap-1.5"
                :class="
                  vistaActiva === 'rutas'
                    ? 'apple-segmented-active text-[#1D1D1F] font-bold shadow-xs'
                    : 'font-semibold text-[#6E6E73] hover:text-[#1D1D1F]'
                "
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Consolidado por Rutas</span>
              </button>
            </div>
          </div>

          <!-- Selector de Submodo: Cruce Diario D-1 vs Histórico por Rango -->
          <div class="flex items-center gap-3">
            <div class="inline-flex apple-segmented p-0.5 rounded-[8px]">
              <button
                type="button"
                @click="cambiarSubmodo('diario')"
                class="px-3 py-1 text-[11px] rounded-[6px] transition-all cursor-pointer"
                :class="
                  submodo === 'diario'
                    ? 'apple-segmented-active text-[#1D1D1F] font-bold'
                    : 'font-semibold text-[#6E6E73] hover:text-[#1D1D1F]'
                "
              >
                Cruce Diario D-1
              </button>
              <button
                type="button"
                @click="cambiarSubmodo('historico')"
                class="px-3 py-1 text-[11px] rounded-[6px] transition-all cursor-pointer"
                :class="
                  submodo === 'historico'
                    ? 'apple-segmented-active text-[#1D1D1F] font-bold'
                    : 'font-semibold text-[#6E6E73] hover:text-[#1D1D1F]'
                "
              >
                Histórico por Rango
              </button>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <!-- VISTA 1: MATRIZ DE DESGLOSE DE PEDIDOS (ENTREGADOS, PARCIAL, RECHAZOS)    -->
        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <div v-if="vistaActiva === 'matriz'" class="flex flex-col">
          <!-- Barra de Filtros y Búsqueda de la Matriz -->
          <div class="px-4 py-2.5 bg-slate-50/60 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-3 text-xs">
            <!-- Chips de Filtro por Estado de Entrega (Matriz) -->
            <div class="flex flex-wrap items-center gap-1.5">
              <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mr-1">Estado:</span>
              <button
                type="button"
                @click="filtroEstadoMatriz = 'TODOS'"
                class="px-2.5 py-1 rounded-[7px] text-[11px] font-semibold transition-all cursor-pointer border"
                :class="
                  filtroEstadoMatriz === 'TODOS'
                    ? 'bg-[#1D1D1F] text-white border-[#1D1D1F] shadow-xs'
                    : 'bg-white text-[#6E6E73] border-black/[0.08] hover:bg-slate-100'
                "
              >
                Todos ({{ conteoPedidosPorEstado.todos }})
              </button>
              <button
                type="button"
                @click="filtroEstadoMatriz = 'ENTREGADO_TOTAL'"
                class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold transition-all cursor-pointer border flex items-center gap-1.5"
                :class="
                  filtroEstadoMatriz === 'ENTREGADO_TOTAL'
                    ? 'bg-[#1E6B37] text-white border-[#1E6B37] shadow-xs'
                    : 'bg-emerald-50 text-[#1E6B37] border-emerald-200 hover:bg-emerald-100/70'
                "
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="filtroEstadoMatriz === 'ENTREGADO_TOTAL' ? 'bg-white' : 'bg-[#1E6B37]'"></span>
                Entregados ({{ conteoPedidosPorEstado.entregados }})
              </button>
              <button
                type="button"
                @click="filtroEstadoMatriz = 'PARCIAL'"
                class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold transition-all cursor-pointer border flex items-center gap-1.5"
                :class="
                  filtroEstadoMatriz === 'PARCIAL'
                    ? 'bg-[#B25E00] text-white border-[#B25E00] shadow-xs'
                    : 'bg-amber-50 text-[#B25E00] border-amber-200 hover:bg-amber-100/70'
                "
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="filtroEstadoMatriz === 'PARCIAL' ? 'bg-white' : 'bg-[#B25E00]'"></span>
                Parciales ({{ conteoPedidosPorEstado.parciales }})
              </button>
              <button
                type="button"
                @click="filtroEstadoMatriz = 'CAIDA_TOTAL'"
                class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold transition-all cursor-pointer border flex items-center gap-1.5"
                :class="
                  filtroEstadoMatriz === 'CAIDA_TOTAL'
                    ? 'bg-[#C9342C] text-white border-[#C9342C] shadow-xs'
                    : 'bg-rose-50 text-[#C9342C] border-rose-200 hover:bg-rose-100/70'
                "
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="filtroEstadoMatriz === 'CAIDA_TOTAL' ? 'bg-white' : 'bg-[#C9342C]'"></span>
                Rechazos ({{ conteoPedidosPorEstado.caidos }})
              </button>
              <button
                type="button"
                @click="filtroEstadoMatriz = 'JUSTIFICADO'"
                class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold transition-all cursor-pointer border flex items-center gap-1.5"
                :class="
                  filtroEstadoMatriz === 'JUSTIFICADO'
                    ? 'bg-[#7C3AED] text-white border-[#7C3AED] shadow-xs'
                    : 'bg-purple-50 text-[#7C3AED] border-purple-200 hover:bg-purple-100/70'
                "
                title="Pedidos que tienen justificativo de rechazo o evidencia fotográfica"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                </svg>
                Con Justificativo ({{ conteoPedidosPorEstado.justificados }})
              </button>
            </div>

            <!-- Filtros de Ruta Rápida y Buscador -->
            <div class="flex items-center gap-2">
              <!-- Selector de Ruta específico en la matriz -->
              <div class="flex items-center gap-1.5 bg-white border border-black/[0.08] rounded-[9px] px-2.5 py-1">
                <label class="text-[9px] font-bold text-[#86868B] uppercase">Ruta:</label>
                <select
                  v-model="filtroRutaMatriz"
                  class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
                >
                  <option value="TODAS">Todas las Rutas ({{ rutasDisponiblesEnDatos.length }})</option>
                  <option
                    v-for="rt in rutasDisponiblesEnDatos"
                    :key="rt"
                    :value="rt"
                  >
                    {{ rt }}
                  </option>
                </select>
              </div>

              <!-- Buscador Instantáneo -->
              <div class="relative">
                <input
                  v-model="busquedaMatriz"
                  type="text"
                  placeholder="Buscar cliente, preventa, motivo..."
                  class="w-52 sm:w-64 text-[11px] bg-white rounded-[9px] pl-7 pr-2.5 py-1 border border-black/[0.08] focus:outline-none focus:border-[#0071E3] font-medium placeholder-[#86868B]"
                />
                <svg
                  class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <button
                  v-if="busquedaMatriz"
                  type="button"
                  @click="busquedaMatriz = ''"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- Sub-barra Informativa de Resultados Visibles -->
          <div class="px-4 py-1.5 bg-slate-100/70 border-b border-black/[0.05] flex flex-wrap items-center justify-between text-[10px] text-[#6E6E73] font-mono">
            <div>
              Mostrando <strong class="text-[#1D1D1F]">{{ pedidosPaginados.length }}</strong> de <strong class="text-[#1D1D1F]">{{ pedidosFiltradosMatriz.length }}</strong> pedidos
              <span v-if="pedidosFiltradosMatriz.length !== todosLosPedidosPlano.length" class="text-slate-400">
                (filtrados de {{ todosLosPedidosPlano.length }} totales)
              </span>
            </div>
            <div class="flex items-center gap-3">
              <span>Prev: <strong class="text-[#1D1D1F]">Bs {{ fmtBs(sumaPrevVisible) }}</strong></span>
              <span class="text-slate-300">|</span>
              <span>Fact: <strong class="text-[#1E6B37]">Bs {{ fmtBs(sumaFactVisible) }}</strong></span>
              <span class="text-slate-300">|</span>
              <span>
                Brecha:
                <strong :class="sumaBrechaVisible < 0 ? 'text-[#C9342C]' : 'text-[#1E6B37]'">
                  {{ sumaBrechaVisible >= 0 ? '+' : '' }}Bs {{ fmtBs(sumaBrechaVisible) }}
                </strong>
              </span>
            </div>
          </div>

          <!-- ── Tabla Matriz de Pedidos (Scrollable) ── -->
          <div class="overflow-x-auto h-[calc(100vh-340px)] min-h-[460px] custom-scroll relative">
            <table class="w-full text-left text-[11px] sm:text-[12px] text-[#1D1D1F] border-separate border-spacing-0 font-sans">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[10px] uppercase tracking-tight sticky top-0 z-20 select-none border-b border-black/[0.08]">
                <tr>
                  <th class="py-2.5 px-3 text-center w-12 sticky left-0 z-30 bg-slate-100 border-b border-r border-black/[0.06]">
                    #
                  </th>

                  <!-- Fecha (Ordenable) -->
                  <th
                    @click="ordenarMatriz('fecha_preventa')"
                    class="py-2.5 px-3 w-24 sticky left-12 z-30 bg-slate-100 border-b border-r border-black/[0.06] whitespace-nowrap cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Fecha<span class="text-[9px] ml-0.5" :class="colorSortMatriz('fecha_preventa')">{{ iconoSortMatriz('fecha_preventa') }}</span>
                  </th>

                  <!-- Cliente (Ordenable) -->
                  <th
                    @click="ordenarMatriz('cliente')"
                    class="py-2.5 px-3.5 min-w-[200px] sticky left-[112px] z-30 bg-slate-100 border-b border-r border-black/[0.06] shadow-[2px_0_6px_rgba(0,0,0,0.03)] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Cliente / Negocio<span class="text-[9px] ml-0.5" :class="colorSortMatriz('cliente')">{{ iconoSortMatriz('cliente') }}</span>
                  </th>

                  <!-- Ruta / Vendedor (Ordenable) -->
                  <th
                    @click="ordenarMatriz('ruta')"
                    class="py-2.5 px-3 w-28 border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Ruta<span class="text-[9px] ml-0.5" :class="colorSortMatriz('ruta')">{{ iconoSortMatriz('ruta') }}</span>
                  </th>

                  <!-- N° Preventa / Factura (Ordenable) -->
                  <th
                    @click="ordenarMatriz('nro_preventa')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Prev / Fact<span class="text-[9px] ml-0.5" :class="colorSortMatriz('nro_preventa')">{{ iconoSortMatriz('nro_preventa') }}</span>
                  </th>

                  <!-- Estatus de Entrega (Ordenable) -->
                  <th
                    @click="ordenarMatriz('estatus_pedido')"
                    class="py-2.5 px-3 text-center w-32 border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Estatus<span class="text-[9px] ml-0.5" :class="colorSortMatriz('estatus_pedido')">{{ iconoSortMatriz('estatus_pedido') }}</span>
                  </th>

                  <!-- Unidades (Ordenable) -->
                  <th
                    @click="ordenarMatriz('total_unidades_fact')"
                    class="py-2.5 px-3 text-center w-20 border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                    title="Unidades Facturadas vs Preventa"
                  >
                    Uds Fact/Prev<span class="text-[9px] ml-0.5" :class="colorSortMatriz('total_unidades_fact')">{{ iconoSortMatriz('total_unidades_fact') }}</span>
                  </th>

                  <!-- Monto Preventa (Ordenable) -->
                  <th
                    @click="ordenarMatriz('total_bs_prev')"
                    class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Prev (Bs)<span class="text-[9px] ml-0.5" :class="colorSortMatriz('total_bs_prev')">{{ iconoSortMatriz('total_bs_prev') }}</span>
                  </th>

                  <!-- Monto Facturado (Ordenable) -->
                  <th
                    @click="ordenarMatriz('total_bs_fact')"
                    class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Fact (Bs)<span class="text-[9px] ml-0.5" :class="colorSortMatriz('total_bs_fact')">{{ iconoSortMatriz('total_bs_fact') }}</span>
                  </th>

                  <!-- Brecha Bs (Ordenable) -->
                  <th
                    @click="ordenarMatriz('brecha_bs')"
                    class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors font-bold"
                  >
                    Brecha (Bs)<span class="text-[9px] ml-0.5" :class="colorSortMatriz('brecha_bs')">{{ iconoSortMatriz('brecha_bs') }}</span>
                  </th>

                  <!-- Justificativo / Motivo de Rechazo -->
                  <th class="py-2.5 px-3 min-w-[170px] border-b border-r border-black/[0.06]">
                    Justificativo App
                  </th>

                  <!-- Detalle SKUs -->
                  <th class="py-2.5 px-3 text-center w-20 border-b border-black/[0.06]">
                    Detalle
                  </th>
                </tr>
              </thead>

              <!-- SKELETON LOADING -->
              <tbody v-if="cargando" class="divide-y divide-black/[0.04] bg-white">
                <tr v-for="i in 10" :key="'sk-mat-' + i" class="border-b border-black/[0.04]">
                  <td class="py-2.5 px-3 sticky left-0 z-10 bg-white border-r border-black/[0.06]"><div class="h-3.5 bg-slate-200 w-5 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 sticky left-12 z-10 bg-white border-r border-black/[0.06]"><div class="h-3.5 bg-slate-200 w-16 rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3.5 sticky left-[112px] z-10 bg-white border-r border-black/[0.06]"><div class="h-3.5 bg-slate-200 w-36 rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-14 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-20 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-12 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-24 rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-12 mx-auto rounded animate-pulse"></div></td>
                </tr>
              </tbody>

              <!-- ESTADO DE ERROR -->
              <tbody v-else-if="errorEntregas" class="divide-y divide-black/[0.04] bg-white">
                <tr>
                  <td colspan="12" class="p-8 text-center text-[#C9342C] font-semibold text-xs">
                    Error al consultar entregas: {{ errorEntregas }}
                  </td>
                </tr>
              </tbody>

              <!-- SIN DATOS -->
              <tbody v-else-if="pedidosFiltradosMatriz.length === 0" class="divide-y divide-black/[0.04] bg-white">
                <tr>
                  <td colspan="12" class="p-12 text-center text-[#86868B] font-medium text-xs">
                    No se encontraron pedidos con los filtros seleccionados.
                  </td>
                </tr>
              </tbody>

              <!-- FILAS DE PEDIDOS DE LA MATRIZ -->
              <tbody v-else class="divide-y divide-black/[0.04] bg-white">
                <template v-for="(p, idx) in pedidosPaginados" :key="p.nro_preventa + '-' + (p.cliente_id || idx)">
                  <!-- Fila Principal del Pedido -->
                  <tr
                    @click="togglePedidoMatriz(p.nro_preventa)"
                    class="hover:bg-slate-50/80 border-b border-black/[0.04] select-none text-[11px] sm:text-[12px] cursor-pointer group transition-colors"
                    :class="{ 'bg-blue-50/20': pedidosExpandidosMatriz[p.nro_preventa] }"
                  >
                    <!-- # Posición -->
                    <td class="py-2.5 px-3 text-center text-[#86868B] sticky left-0 z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] font-mono tabular-nums">
                      {{ (paginaActualMatriz - 1) * tamanioPaginaMatriz + idx + 1 }}
                    </td>

                    <!-- Fecha Preventa -->
                    <td class="py-2.5 px-3 font-mono text-[#6E6E73] sticky left-12 z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] whitespace-nowrap">
                      {{ p.fecha_preventa }}
                    </td>

                    <!-- Cliente -->
                    <td
                      class="py-2.5 px-3.5 text-[#1D1D1F] font-semibold sticky left-[112px] z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] shadow-[2px_0_6px_rgba(0,0,0,0.03)] truncate max-w-[220px]"
                      :title="p.cliente"
                    >
                      <div class="flex items-center gap-1.5">
                        <svg
                          class="w-3 h-3 text-[#86868B] transition-transform duration-200 shrink-0"
                          :class="{ 'rotate-90 text-[#0071E3]': pedidosExpandidosMatriz[p.nro_preventa] }"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="truncate">{{ p.cliente }}</span>
                      </div>
                      <div class="text-[9px] text-[#86868B] font-mono pl-4.5">
                        ID: {{ p.cliente_id ?? '—' }} <span v-if="p.codigo_cliente">({{ p.codigo_cliente }})</span>
                      </div>
                    </td>

                    <!-- Ruta / Vendedor -->
                    <td class="py-2.5 px-3 border-r border-black/[0.06] whitespace-nowrap">
                      <div class="font-bold text-[#1D1D1F]">{{ p.ruta || '—' }}</div>
                      <div class="text-[9px] text-[#86868B] truncate max-w-[110px]" :title="p.vendedor">{{ p.vendedor }}</div>
                    </td>

                    <!-- N° Preventa y Factura -->
                    <td class="py-2.5 px-3 text-center border-r border-black/[0.06] font-mono whitespace-nowrap">
                      <div class="font-bold text-[#1D1D1F]">#{{ p.nro_preventa }}</div>
                      <div v-if="p.nro_factura" class="text-[9px] text-[#1E6B37] font-semibold">
                        Fact #{{ p.nro_factura }}
                      </div>
                      <div v-else class="text-[9px] text-[#C9342C]">
                        Sin factura
                      </div>
                    </td>

                    <!-- Estatus Badge -->
                    <td class="py-2.5 px-3 text-center border-r border-black/[0.06]">
                      <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[5px] text-[10px] font-bold border font-mono tracking-tight"
                        :class="claseBadgePedido(p.estatus_pedido)"
                      >
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="colorDotEstatus(p.estatus_pedido)"></span>
                        {{ textoBadgePedido(p.estatus_pedido) }}
                      </span>
                    </td>

                    <!-- Unidades (Facturadas / Preventa) -->
                    <td class="py-2.5 px-3 text-center border-r border-black/[0.06] font-mono tabular-nums">
                      <span :class="p.total_unidades_fact === 0 ? 'text-[#C9342C] font-bold' : p.total_unidades_fact < p.total_unidades_prev ? 'text-[#B25E00] font-bold' : 'text-[#1E6B37] font-bold'">
                        {{ p.total_unidades_fact }}
                      </span>
                      <span class="text-[#86868B]"> / {{ p.total_unidades_prev }}</span>
                    </td>

                    <!-- Monto Preventa -->
                    <td class="py-2.5 px-3 text-right font-medium font-mono tabular-nums text-[#6E6E73] border-r border-black/[0.06]">
                      {{ fmtBs(p.total_bs_prev) }}
                    </td>

                    <!-- Monto Facturado -->
                    <td class="py-2.5 px-3 text-right font-bold font-mono tabular-nums text-[#1D1D1F] border-r border-black/[0.06]">
                      {{ fmtBs(p.total_bs_fact) }}
                    </td>

                    <!-- Brecha Bs -->
                    <td
                      class="py-2.5 px-3 text-right font-bold font-mono tabular-nums border-r border-black/[0.06]"
                      :class="Number(p.brecha_bs || 0) < 0 ? 'text-[#C9342C]' : 'text-[#1E6B37]'"
                    >
                      {{ Number(p.brecha_bs || 0) >= 0 ? '+' : '' }}{{ fmtBs(p.brecha_bs) }}
                    </td>

                    <!-- Justificativo / Motivo de Rechazo -->
                    <td class="py-2.5 px-3 border-r border-black/[0.06]">
                      <div v-if="p.justificativo" class="flex items-center gap-1.5 flex-wrap">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#FFF5E5] text-[#B25E00] border border-[#FFE0B2]">
                          {{ formatearMotivo(p.justificativo.motivo) }}
                        </span>
                        <button
                          v-if="p.justificativo.photo_url"
                          type="button"
                          @click.stop="abrirModalEvidencia(p)"
                          class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#0071E3]/10 text-[#0071E3] hover:bg-[#0071E3] hover:text-white border border-[#0071E3]/20 transition-colors cursor-pointer"
                          title="Ver evidencia fotográfica"
                        >
                          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                          </svg>
                          <span>Foto</span>
                        </button>
                      </div>
                      <span v-else-if="p.estatus_pedido !== 'ENTREGADO_TOTAL'" class="text-[9px] text-[#86868B] italic">
                        Sin motivo en app
                      </span>
                      <span v-else class="text-[9px] text-[#1E6B37] font-semibold">
                        Entregado OK
                      </span>
                    </td>

                    <!-- Botón Acción Detalle SKUs -->
                    <td class="py-2.5 px-3 text-center">
                      <button
                        type="button"
                        class="px-2 py-0.5 rounded text-[10px] font-bold transition-colors cursor-pointer"
                        :class="pedidosExpandidosMatriz[p.nro_preventa] ? 'bg-slate-200 text-[#1D1D1F]' : 'bg-slate-100 group-hover:bg-slate-200 text-[#6E6E73]'"
                      >
                        {{ (p.items || []).length }} SKUs
                      </button>
                    </td>
                  </tr>

                  <!-- Fila Expandible con Detalle de Productos (SKUs) del Pedido -->
                  <tr
                    v-if="pedidosExpandidosMatriz[p.nro_preventa]"
                    class="bg-slate-50/70 border-b border-black/[0.08]"
                  >
                    <td colspan="12" class="p-3 pl-8 sm:pl-14">
                      <div class="bg-white rounded-xl border border-black/[0.08] shadow-xs overflow-hidden space-y-2 p-3">
                        <!-- Banner de Justificativo si existe -->
                        <div
                          v-if="p.justificativo"
                          class="p-2.5 rounded-lg bg-[#FFF9F0] border border-[#FFE0B2] flex flex-wrap items-center justify-between gap-2 text-[11px]"
                        >
                          <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                              <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-[#B25E00] text-white">
                                Justificativo Registrado ({{ p.justificativo.tipo_rechazo }})
                              </span>
                              <strong class="text-[#1D1D1F]">Motivo: {{ formatearMotivo(p.justificativo.motivo) }}</strong>
                              <span v-if="p.justificativo.fecha_rechazo" class="text-[10px] text-[#6E6E73] font-mono">
                                · {{ p.justificativo.fecha_rechazo }}
                              </span>
                            </div>
                            <div class="text-[#1D1D1F]">
                              <span class="text-[#86868B] font-medium">Comentario:</span>
                              <span class="italic ml-1">{{ p.justificativo.comentarios || 'Sin comentario adicional.' }}</span>
                            </div>
                          </div>

                          <div class="flex items-center gap-2 shrink-0">
                            <button
                              v-if="p.justificativo.photo_url"
                              type="button"
                              @click.stop="abrirModalEvidencia(p)"
                              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#0071E3] hover:bg-[#0077ED] text-white text-[10px] font-bold shadow-xs transition-colors cursor-pointer"
                            >
                              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                              </svg>
                              <span>Ver Foto Evidencia</span>
                            </button>
                            <a
                              v-if="p.justificativo.latitude && p.justificativo.longitude"
                              :href="`https://www.google.com/maps?q=${p.justificativo.latitude},${p.justificativo.longitude}`"
                              target="_blank"
                              rel="noopener noreferrer"
                              @click.stop
                              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white hover:bg-slate-50 text-[#1D1D1F] border border-black/[0.08] text-[10px] font-semibold transition-colors"
                            >
                              <svg class="w-3 h-3 text-[#C9342C]" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                              </svg>
                              <span>GPS</span>
                            </a>
                          </div>
                        </div>

                        <!-- Tabla de Ítems / SKUs -->
                        <div class="overflow-x-auto max-h-56 custom-scroll">
                          <table class="w-full text-left font-sans text-[11px] border-separate border-spacing-0">
                            <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[9px] uppercase tracking-tight sticky top-0 border-b border-black/[0.06]">
                              <tr>
                                <th class="py-1 px-2 text-center w-8">#</th>
                                <th class="py-1 px-2 w-20">Código</th>
                                <th class="py-1 px-2 min-w-[200px]">Producto</th>
                                <th class="py-1 px-2 text-center w-16">Uds Prev</th>
                                <th class="py-1 px-2 text-center w-16">Uds Fact</th>
                                <th class="py-1 px-2 text-center w-16">Dif Uds</th>
                                <th class="py-1 px-2 text-right w-24">Prev (Bs)</th>
                                <th class="py-1 px-2 text-right w-24">Fact (Bs)</th>
                                <th class="py-1 px-2 text-right w-24">Dif (Bs)</th>
                                <th class="py-1 px-3 text-center w-28">Estatus Ítem</th>
                                <th class="py-1 px-3 text-left min-w-[140px]">Justificativo Ítem</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-black/[0.04]">
                              <tr
                                v-for="(it, itIdx) in (p.items || [])"
                                :key="itIdx"
                                class="hover:bg-slate-50 transition-colors"
                              >
                                <td class="py-1.5 px-2 text-center text-[#86868B] font-mono">{{ itIdx + 1 }}</td>
                                <td class="py-1.5 px-2 text-[#6E6E73] font-mono text-[10px]">{{ it.codigo_producto || '—' }}</td>
                                <td class="py-1.5 px-2 font-semibold text-[#1D1D1F] truncate max-w-[240px]" :title="it.producto">
                                  {{ it.producto }}
                                </td>
                                <td class="py-1.5 px-2 text-center font-mono font-medium">{{ it.cantidad_preventa }}</td>
                                <td
                                  class="py-1.5 px-2 text-center font-mono font-bold"
                                  :class="it.cantidad_facturada === 0 ? 'text-[#C9342C]' : 'text-[#1E6B37]'"
                                >
                                  {{ it.cantidad_facturada }}
                                </td>
                                <td
                                  class="py-1.5 px-2 text-center font-mono"
                                  :class="it.diferencia_unidades < 0 ? 'text-[#C9342C] font-bold' : 'text-[#6E6E73]'"
                                >
                                  {{ it.diferencia_unidades }}
                                </td>
                                <td class="py-1.5 px-2 text-right font-mono text-[#6E6E73]">{{ fmtBs(it.monto_preventa) }}</td>
                                <td class="py-1.5 px-2 text-right font-mono font-bold text-[#1D1D1F]">{{ fmtBs(it.monto_facturado) }}</td>
                                <td
                                  class="py-1.5 px-2 text-right font-mono font-semibold"
                                  :class="
                                    Math.abs(Number(it.diferencia_bs || 0)) > 0.01
                                      ? Number(it.diferencia_bs || 0) < 0
                                        ? 'text-[#C9342C]'
                                        : 'text-[#1E6B37]'
                                      : 'text-[#86868B]'
                                  "
                                >
                                  {{ Number(it.diferencia_bs || 0) >= 0 ? '+' : '' }}{{ fmtBs(it.diferencia_bs) }}
                                </td>
                                <td class="py-1.5 px-3 text-center">
                                  <span
                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold border"
                                    :class="claseBadgeItem(it.estatus)"
                                  >
                                    {{ textoBadgeItem(it.estatus) }}
                                  </span>
                                </td>
                                <td class="py-1.5 px-3 text-left font-sans">
                                  <div v-if="it.motivo_rechazo" class="flex flex-wrap items-center gap-1.5">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#FFF5E5] text-[#B25E00] border border-[#FFE0B2]">
                                      {{ formatearMotivo(it.motivo_rechazo) }}
                                    </span>
                                    <span
                                      v-if="it.cantidad_rechazada_rep > 0"
                                      class="text-[10px] font-mono text-[#C9342C] font-semibold"
                                    >
                                      (-{{ it.cantidad_rechazada_rep }} uds)
                                    </span>
                                  </div>
                                  <span v-else class="text-[#86868B] text-[10px]">—</span>
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

          <!-- Controles de Paginación de la Matriz -->
          <div class="px-4 py-2 bg-slate-50 border-t border-black/[0.06] flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
              <span class="text-[#86868B] text-[11px]">Filas por página:</span>
              <select
                v-model.number="tamanioPaginaMatriz"
                @change="paginaActualMatriz = 1"
                class="bg-white border border-black/[0.08] rounded-md px-2 py-0.5 text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
                <option :value="99999">Todas</option>
              </select>
            </div>

            <div class="flex items-center gap-1 font-mono text-[11px]">
              <button
                type="button"
                @click="paginaActualMatriz = Math.max(1, paginaActualMatriz - 1)"
                :disabled="paginaActualMatriz <= 1"
                class="px-2.5 py-1 rounded bg-white border border-black/[0.08] hover:bg-slate-100 disabled:opacity-40 disabled:pointer-events-none cursor-pointer font-bold text-[#1D1D1F]"
              >
                ← Anterior
              </button>
              <span class="px-2 text-[#6E6E73]">
                Página <strong class="text-[#1D1D1F]">{{ paginaActualMatriz }}</strong> de <strong class="text-[#1D1D1F]">{{ totalPaginasMatriz }}</strong>
              </span>
              <button
                type="button"
                @click="paginaActualMatriz = Math.min(totalPaginasMatriz, paginaActualMatriz + 1)"
                :disabled="paginaActualMatriz >= totalPaginasMatriz"
                class="px-2.5 py-1 rounded bg-white border border-black/[0.08] hover:bg-slate-100 disabled:opacity-40 disabled:pointer-events-none cursor-pointer font-bold text-[#1D1D1F]"
              >
                Siguiente →
              </button>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <!-- VISTA 2: CONSOLIDADO POR RUTAS (TABLA RESUMEN POR RUTA)                   -->
        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <div v-else class="flex flex-col">
          <div class="overflow-x-auto h-[calc(100vh-270px)] min-h-[460px] custom-scroll relative">
            <table class="w-full text-left text-[11px] sm:text-[12px] text-[#1D1D1F] border-separate border-spacing-0 font-sans">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[10px] uppercase tracking-tight sticky top-0 z-20 select-none border-b border-black/[0.08]">
                <tr>
                  <th class="py-2.5 px-3 text-center w-12 sticky left-0 z-30 bg-slate-100 border-b border-r border-black/[0.06]">
                    Pos
                  </th>
                  <th
                    @click="ordenarRutas('ruta')"
                    class="py-2.5 px-3 w-28 sticky left-12 z-30 bg-slate-100 border-b border-r border-black/[0.06] whitespace-nowrap cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Ruta<span class="text-[9px] ml-0.5" :class="colorSortRuta('ruta')">{{ iconoSortRuta('ruta') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('vendedor')"
                    class="py-2.5 px-3.5 min-w-[180px] sticky left-[112px] z-30 bg-slate-100 border-b border-r border-black/[0.06] shadow-[2px_0_6px_rgba(0,0,0,0.03)] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Preventista<span class="text-[9px] ml-0.5" :class="colorSortRuta('vendedor')">{{ iconoSortRuta('vendedor') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('canal')"
                    class="py-2.5 px-2.5 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Canal<span class="text-[9px] ml-0.5" :class="colorSortRuta('canal')">{{ iconoSortRuta('canal') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('total_pedidos')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] font-bold cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Preventas<span class="text-[9px] ml-0.5" :class="colorSortRuta('total_pedidos')">{{ iconoSortRuta('total_pedidos') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_entregados')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Entregadas<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_entregados')">{{ iconoSortRuta('pedidos_entregados') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_parciales')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Parciales<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_parciales')">{{ iconoSortRuta('pedidos_parciales') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_caidos')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Caídas (0%)<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_caidos')">{{ iconoSortRuta('pedidos_caidos') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_justificados')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Justificados<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_justificados')">{{ iconoSortRuta('pedidos_justificados') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('monto_preventa')"
                    class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Prev. (Bs)<span class="text-[9px] ml-0.5" :class="colorSortRuta('monto_preventa')">{{ iconoSortRuta('monto_preventa') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('monto_facturado')"
                    class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Fact. (Bs)<span class="text-[9px] ml-0.5" :class="colorSortRuta('monto_facturado')">{{ iconoSortRuta('monto_facturado') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('brecha_bs')"
                    class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Brecha (Bs)<span class="text-[9px] ml-0.5" :class="colorSortRuta('brecha_bs')">{{ iconoSortRuta('brecha_bs') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('efectividad_pct')"
                    class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] font-bold cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    % Efect.<span class="text-[9px] ml-0.5" :class="colorSortRuta('efectividad_pct')">{{ iconoSortRuta('efectividad_pct') }}</span>
                  </th>
                  <th class="py-2.5 px-4 text-center border-b border-black/[0.06] w-28">
                    Acción
                  </th>
                </tr>
              </thead>

              <!-- SKELETON LOADING -->
              <tbody v-if="cargando" class="divide-y divide-black/[0.04] bg-white">
                <tr v-for="i in 10" :key="'sk-rut-' + i" class="border-b border-black/[0.04]">
                  <td class="py-2.5 px-3 sticky left-0 z-10 bg-white border-r border-black/[0.06]"><div class="h-3.5 bg-slate-200 w-5 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 sticky left-12 z-10 bg-white border-r border-black/[0.06]"><div class="h-3.5 bg-slate-200 w-16 rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3.5 sticky left-[112px] z-10 bg-white border-r border-black/[0.06]"><div class="h-3.5 bg-slate-200 w-32 rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-2.5"><div class="h-3.5 bg-slate-200 w-12 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 bg-slate-200 w-12 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-4"><div class="h-3.5 bg-slate-200 w-16 mx-auto rounded animate-pulse"></div></td>
                </tr>
              </tbody>

              <!-- ESTADO DE ERROR -->
              <tbody v-else-if="errorEntregas" class="divide-y divide-black/[0.04] bg-white">
                <tr>
                  <td colspan="14" class="p-8 text-center text-[#C9342C] font-semibold text-xs">
                    Error al consultar avance de entregas: {{ errorEntregas }}
                  </td>
                </tr>
              </tbody>

              <!-- SIN DATOS -->
              <tbody v-else-if="rutasOrdenadas.length === 0" class="divide-y divide-black/[0.04] bg-white">
                <tr>
                  <td colspan="14" class="p-12 text-center text-[#86868B] font-medium text-xs">
                    No se encontraron rutas con los filtros seleccionados.
                  </td>
                </tr>
              </tbody>

              <!-- FILAS DE RUTAS -->
              <tbody v-else class="divide-y divide-black/[0.04] bg-white">
                <tr
                  v-for="(r, idx) in rutasOrdenadas"
                  :key="r.ruta"
                  class="hover:bg-slate-50 border-b border-black/[0.04] select-none text-[11px] sm:text-[12px] group transition-colors"
                >
                  <td class="py-2.5 px-3 text-center text-[#6E6E73] sticky left-0 z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] font-mono tabular-nums font-bold">
                    {{ idx + 1 }}
                  </td>
                  <td class="py-2.5 px-3 font-bold text-[#1D1D1F] sticky left-12 z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] whitespace-nowrap">
                    {{ r.ruta }}
                  </td>
                  <td
                    class="py-2.5 px-3.5 text-[#1D1D1F] font-semibold sticky left-[112px] z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] shadow-[2px_0_6px_rgba(0,0,0,0.03)] truncate max-w-[190px]"
                    :title="r.vendedor"
                  >
                    {{ r.vendedor }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center text-[#6E6E73] border-r border-black/[0.06]">
                    {{ r.canal }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-bold text-[#1D1D1F] font-mono tabular-nums border-r border-black/[0.06]">
                    {{ r.total_pedidos }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-bold text-[#1E6B37] font-mono tabular-nums border-r border-black/[0.06]">
                    {{ r.pedidos_entregados }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-bold text-[#B25E00] font-mono tabular-nums border-r border-black/[0.06]">
                    {{ r.pedidos_parciales }}
                  </td>
                  <td
                    class="py-2.5 px-2.5 text-center font-bold font-mono tabular-nums border-r border-black/[0.06]"
                    :class="r.pedidos_caidos > 0 ? 'text-[#C9342C]' : 'text-[#6E6E73]'"
                  >
                    {{ r.pedidos_caidos }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-black/[0.06]">
                    <span
                      v-if="(r.pedidos_justificados || 0) > 0"
                      class="inline-flex items-center px-2 py-0.5 rounded-[4px] font-bold text-[11px] bg-[#FFF5E5] text-[#B25E00] border border-[#FFE0B2]"
                    >
                      {{ r.pedidos_justificados }}
                    </span>
                    <span v-else class="text-[#86868B]">0</span>
                  </td>
                  <td class="py-2.5 px-3 text-right font-medium font-mono tabular-nums text-[#6E6E73] border-r border-black/[0.06]">
                    {{ fmtBs(r.monto_preventa) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-bold font-mono tabular-nums text-[#1D1D1F] border-r border-black/[0.06]">
                    {{ fmtBs(r.monto_facturado) }}
                  </td>
                  <td
                    class="py-2.5 px-3 text-right font-bold font-mono tabular-nums border-r border-black/[0.06]"
                    :class="Number(r.brecha_bs || 0) < 0 ? 'text-[#C9342C]' : 'text-[#1E6B37]'"
                  >
                    {{ Number(r.brecha_bs || 0) >= 0 ? '+' : '' }}{{ fmtBs(r.brecha_bs) }}
                  </td>
                  <td class="py-2.5 px-3 text-center border-r border-black/[0.06]">
                    <span
                      class="inline-flex items-center px-1.5 py-0.5 rounded-[4px] text-[11px] font-mono border"
                      :class="claseBadgeEfectividad(r.efectividad_pct)"
                    >
                      {{ Number(r.efectividad_pct || 0).toFixed(1) }}%
                    </span>
                  </td>
                  <td class="py-2.5 px-4 text-center">
                    <button
                      type="button"
                      @click="irAMatrizRuta(r.ruta)"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#0071E3]/10 hover:bg-[#0071E3] text-[#0071E3] hover:text-white text-[10px] font-bold transition-all cursor-pointer border border-[#0071E3]/20"
                    >
                      <span>Ver Pedidos</span>
                      <span class="font-mono">→</span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── Modal de Evidencia Fotográfica y GPS ── -->
      <div
        v-if="pedidoEvidenciaModal"
        @click="cerrarModalEvidencia"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
      >
        <div
          @click.stop
          class="bg-white rounded-2xl shadow-2xl border border-black/[0.08] max-w-lg w-full overflow-hidden"
        >
          <div class="px-4 py-3 bg-slate-100 border-b border-black/[0.06] flex items-center justify-between">
            <div>
              <h3 class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">
                Evidencia de Rechazo · Prev #{{ pedidoEvidenciaModal.nro_preventa }}
              </h3>
              <p class="text-[11px] text-[#6E6E73] font-medium truncate">
                {{ pedidoEvidenciaModal.cliente }} (ID: {{ pedidoEvidenciaModal.cliente_id }})
              </p>
            </div>
            <button
              type="button"
              @click="cerrarModalEvidencia"
              class="w-7 h-7 rounded-full bg-slate-200 hover:bg-slate-300 flex items-center justify-center text-[#1D1D1F] text-sm font-bold cursor-pointer"
            >
              ✕
            </button>
          </div>

          <div class="p-4 space-y-3">
            <div class="rounded-xl overflow-hidden bg-slate-100 border border-black/[0.06] flex items-center justify-center max-h-[380px]">
              <img
                v-if="pedidoEvidenciaModal.justificativo?.photo_url"
                :src="pedidoEvidenciaModal.justificativo.photo_url"
                alt="Evidencia de rechazo"
                class="max-h-[380px] w-auto object-contain"
              />
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-black/[0.06] space-y-1.5 text-xs">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-bold text-[#B25E00]">
                  Motivo: {{ formatearMotivo(pedidoEvidenciaModal.justificativo?.motivo) }}
                </span>
                <span class="font-mono text-[11px] text-[#6E6E73]">
                  {{ pedidoEvidenciaModal.justificativo?.fecha_rechazo || '' }}
                </span>
              </div>
              <p class="text-[#1D1D1F] text-[11px]">
                <strong class="text-[#6E6E73]">Comentario:</strong>
                {{ pedidoEvidenciaModal.justificativo?.comentarios || 'Sin comentarios registrados.' }}
              </p>
              <div
                v-if="pedidoEvidenciaModal.justificativo?.latitude && pedidoEvidenciaModal.justificativo?.longitude"
                class="pt-1 flex justify-end"
              >
                <a
                  :href="`https://www.google.com/maps?q=${pedidoEvidenciaModal.justificativo.latitude},${pedidoEvidenciaModal.justificativo.longitude}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 text-[11px] font-bold text-[#0071E3] hover:underline"
                >
                  Abrir ubicación exacta en Google Maps →
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import AvanceFiltrosBar from './AvanceFiltrosBar.vue';
import { navigationConfig } from '@/Config/navigation.js';
import { useAvanceStore } from '@/Stores/avanceStore';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  meses: { type: Array, default: () => [] },
  fecha_inicio_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
  mes_default: { type: String, default: '' },
  dias_calendario: { type: Object, default: null },
});

// Subnavegación de Módulo Avance
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance');
const avanceTabs = avanceMenu ? avanceMenu.submodules : [];

const store = useAvanceStore();

// Modos de Vista y Submodo
const vistaActiva = ref('matriz'); // 'matriz' | 'rutas'
const submodo = ref('diario'); // 'diario' | 'historico'

// Estado de carga y datos
const cargando = ref(false);
const errorEntregas = ref(null);
const datosEntregas = ref(null);
const datosEntregasHistorico = ref(null);

// Modal de evidencia fotográfica
const pedidoEvidenciaModal = ref(null);

// Caché de detalle de pedidos por ruta para el modo Histórico
const detallePedidosHistorico = reactive({});
const cargandoDetalleHist = reactive({});
const errorDetalleHist = reactive({});

// ── Filtros y Ordenamiento de la MATRIZ DE PEDIDOS ──
const filtroEstadoMatriz = ref('TODOS'); // 'TODOS' | 'ENTREGADO_TOTAL' | 'PARCIAL' | 'CAIDA_TOTAL' | 'JUSTIFICADO'
const filtroRutaMatriz = ref('TODAS');
const busquedaMatriz = ref('');
const pedidosExpandidosMatriz = reactive({});

const ordenMatriz = reactive({
  campo: 'fecha_preventa',
  dir: 'desc',
});

// Paginación de la Matriz
const paginaActualMatriz = ref(1);
const tamanioPaginaMatriz = ref(50);

// ── Ordenamiento de la VISTA CONSOLIDADA DE RUTAS ──
const ordenRuta = reactive({
  campo: 'efectividad_pct',
  dir: 'asc',
});

// Referencia D-1
const fechaD1Referencia = computed(() => {
  return datosEntregas.value?.fecha_d1_referencia || '-';
});

// Totales generales del endpoint activo
const totalesGenerales = computed(() => {
  if (submodo.value === 'diario') {
    return datosEntregas.value?.totales_generales || {};
  }
  return datosEntregasHistorico.value?.totales_generales || {};
});

const efectividadGlobal = computed(() => {
  return Number(totalesGenerales.value?.efectividad_global_pct || 0);
});

const pctEntregados = computed(() => {
  const tot = totalesGenerales.value?.total_pedidos || 0;
  if (tot === 0) return 0;
  return Math.round(((totalesGenerales.value?.pedidos_entregados || 0) / tot) * 100);
});

const pctParciales = computed(() => {
  const tot = totalesGenerales.value?.total_pedidos || 0;
  if (tot === 0) return 0;
  return Math.round(((totalesGenerales.value?.pedidos_parciales || 0) / tot) * 100);
});

const pctCaidos = computed(() => {
  const tot = totalesGenerales.value?.total_pedidos || 0;
  if (tot === 0) return 0;
  return Math.round(((totalesGenerales.value?.pedidos_caidos || 0) / tot) * 100);
});

const totalJustificados = computed(() => {
  const rutas = submodo.value === 'diario'
    ? (datosEntregas.value?.rutas || [])
    : (datosEntregasHistorico.value?.rutas || []);
  return rutas.reduce((acc, r) => acc + (Number(r.pedidos_justificados) || 0), 0);
});

// Lista de rutas disponibles en los datos actuales
const rutasDisponiblesEnDatos = computed(() => {
  const rutas = submodo.value === 'diario'
    ? (datosEntregas.value?.rutas || [])
    : (datosEntregasHistorico.value?.rutas || []);
  return rutas.map(r => r.ruta).filter(Boolean).sort();
});

// ── CONSTRUCCIÓN DE LA LISTA PLANA DE PEDIDOS PARA LA MATRIZ ──
const todosLosPedidosPlano = computed(() => {
  if (submodo.value === 'diario') {
    const rutas = datosEntregas.value?.rutas || [];
    const lista = [];
    rutas.forEach(r => {
      if (Array.isArray(r.pedidos)) {
        r.pedidos.forEach(p => {
          lista.push({
            ...p,
            ruta: p.ruta || r.ruta,
            canal: p.canal || r.canal,
            vendedor: p.vendedor || r.vendedor,
          });
        });
      }
    });
    return lista;
  }

  // En Histórico: si hay una ruta seleccionada en el filtro de la matriz, mostrar sus pedidos
  if (filtroRutaMatriz.value !== 'TODAS') {
    const peds = detallePedidosHistorico[filtroRutaMatriz.value] || [];
    return peds.map(p => ({
      ...p,
      ruta: p.ruta || filtroRutaMatriz.value,
    }));
  }

  // Si no hay ruta seleccionada en histórico, combinar las rutas ya cargadas en memoria
  const lista = [];
  Object.keys(detallePedidosHistorico).forEach(rt => {
    (detallePedidosHistorico[rt] || []).forEach(p => {
      lista.push({
        ...p,
        ruta: p.ruta || rt,
      });
    });
  });
  return lista;
});

// Conteos por estado en la matriz
const conteoPedidosPorEstado = computed(() => {
  const base = todosLosPedidosPlano.value;
  return {
    todos: base.length,
    entregados: base.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL').length,
    parciales: base.filter(p => p.estatus_pedido === 'PARCIAL').length,
    caidos: base.filter(p => p.estatus_pedido === 'CAIDA_TOTAL').length,
    justificados: base.filter(p => Boolean(p.justificativo)).length,
  };
});

// Pedidos filtrados y ordenados en la matriz
const pedidosFiltradosMatriz = computed(() => {
  let resultado = todosLosPedidosPlano.value;

  // 1. Filtro por canal global del store si está activo
  const canalesSel = store.filtrosAplicados?.canalesSeleccionados || [];
  if (canalesSel.length > 0 && !canalesSel.includes('__NINGUNO__')) {
    const setC = new Set(canalesSel.map(c => String(c).trim().toUpperCase()));
    resultado = resultado.filter(p => setC.has(String(p.canal || '').trim().toUpperCase()));
  }

  // 2. Filtro por ruta global del store si está activo
  const rutasSel = store.filtrosAplicados?.rutasSeleccionadas || [];
  if (rutasSel.length > 0 && !rutasSel.includes('__NINGUNA__')) {
    const setR = new Set(rutasSel.map(r => String(r).trim().toUpperCase()));
    resultado = resultado.filter(p => setR.has(String(p.ruta || '').trim().toUpperCase()));
  }

  // 3. Filtro específico de ruta en la matriz
  if (filtroRutaMatriz.value !== 'TODAS') {
    const rNorm = String(filtroRutaMatriz.value).trim().toUpperCase();
    resultado = resultado.filter(p => String(p.ruta || '').trim().toUpperCase() === rNorm);
  }

  // 4. Filtro por estado de entrega
  if (filtroEstadoMatriz.value === 'ENTREGADO_TOTAL') {
    resultado = resultado.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL');
  } else if (filtroEstadoMatriz.value === 'PARCIAL') {
    resultado = resultado.filter(p => p.estatus_pedido === 'PARCIAL');
  } else if (filtroEstadoMatriz.value === 'CAIDA_TOTAL') {
    resultado = resultado.filter(p => p.estatus_pedido === 'CAIDA_TOTAL');
  } else if (filtroEstadoMatriz.value === 'JUSTIFICADO') {
    resultado = resultado.filter(p => Boolean(p.justificativo));
  }

  // 5. Búsqueda por texto (cliente, id, preventa, factura, motivo)
  const q = busquedaMatriz.value.toLowerCase().trim();
  if (q) {
    resultado = resultado.filter(p =>
      String(p.cliente || '').toLowerCase().includes(q) ||
      String(p.codigo_cliente || '').toLowerCase().includes(q) ||
      String(p.cliente_id || '').toLowerCase().includes(q) ||
      String(p.nro_preventa || '').toLowerCase().includes(q) ||
      String(p.nro_factura || '').toLowerCase().includes(q) ||
      String(p.ruta || '').toLowerCase().includes(q) ||
      String(p.vendedor || '').toLowerCase().includes(q) ||
      String(p.justificativo?.motivo || '').toLowerCase().includes(q) ||
      String(p.justificativo?.comentarios || '').toLowerCase().includes(q)
    );
  }

  // 6. Ordenamiento multidimensional (Fecha, Cliente, Monto, Brecha, Estatus, etc.)
  const campo = ordenMatriz.campo;
  const mult = ordenMatriz.dir === 'asc' ? 1 : -1;

  return [...resultado].sort((a, b) => {
    let va = a[campo];
    let vb = b[campo];

    if (campo === 'fecha_preventa') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    if (campo === 'cliente') {
      return String(va || '').localeCompare(String(vb || ''), 'es', { sensitivity: 'base' }) * mult;
    }
    if (campo === 'ruta' || campo === 'estatus_pedido') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }

    const na = Number(va) || 0;
    const nb = Number(vb) || 0;
    return (na - nb) * mult;
  });
});

// Paginación de resultados de la matriz
const totalPaginasMatriz = computed(() => {
  return Math.ceil(pedidosFiltradosMatriz.value.length / tamanioPaginaMatriz.value) || 1;
});

const pedidosPaginados = computed(() => {
  const inicio = (paginaActualMatriz.value - 1) * tamanioPaginaMatriz.value;
  return pedidosFiltradosMatriz.value.slice(inicio, inicio + tamanioPaginaMatriz.value);
});

// Sumas para la barra informativa
const sumaPrevVisible = computed(() => {
  return pedidosFiltradosMatriz.value.reduce((acc, p) => acc + (Number(p.total_bs_prev) || 0), 0);
});

const sumaFactVisible = computed(() => {
  return pedidosFiltradosMatriz.value.reduce((acc, p) => acc + (Number(p.total_bs_fact) || 0), 0);
});

const sumaBrechaVisible = computed(() => {
  return Math.round((sumaFactVisible.value - sumaPrevVisible.value) * 100) / 100;
});

// Funciones de ordenamiento de la Matriz
function ordenarMatriz(campo) {
  if (ordenMatriz.campo === campo) {
    ordenMatriz.dir = ordenMatriz.dir === 'asc' ? 'desc' : 'asc';
  } else {
    ordenMatriz.campo = campo;
    ordenMatriz.dir = (campo === 'fecha_preventa' || campo === 'total_bs_prev' || campo === 'total_bs_fact' || campo === 'brecha_bs')
      ? 'desc'
      : 'asc';
  }
}

function iconoSortMatriz(campo) {
  if (ordenMatriz.campo !== campo) return '';
  return ordenMatriz.dir === 'asc' ? '▲' : '▼';
}

function colorSortMatriz(campo) {
  return ordenMatriz.campo === campo ? 'text-[#0071E3] font-bold' : 'text-[#86868B]';
}

function togglePedidoMatriz(nroPrev) {
  pedidosExpandidosMatriz[nroPrev] = !pedidosExpandidosMatriz[nroPrev];
}

// ── VISTA CONSOLIDADA DE RUTAS ──
function cumpleFiltroRuta(r) {
  if (!r) return false;

  const canalesSel = store.filtrosAplicados?.canalesSeleccionados || [];
  if (canalesSel.length > 0 && !canalesSel.includes('__NINGUNO__')) {
    const canalNorm = String(r.canal || '').trim().toUpperCase();
    const selSet = new Set(canalesSel.map(c => String(c).trim().toUpperCase()));
    if (!selSet.has(canalNorm)) return false;
  }

  const rutasSel = store.filtrosAplicados?.rutasSeleccionadas || [];
  if (rutasSel.length > 0 && !rutasSel.includes('__NINGUNA__')) {
    const rutaNorm = String(r.ruta || '').trim().toUpperCase();
    const selSet = new Set(rutasSel.map(rt => String(rt).trim().toUpperCase()));
    if (!selSet.has(rutaNorm)) return false;
  }

  const busq = String(store.filtrosAplicados?.busqueda || '').toLowerCase().trim();
  if (busq) {
    return (
      String(r.ruta || '').toLowerCase().includes(busq) ||
      String(r.vendedor || '').toLowerCase().includes(busq) ||
      String(r.canal || '').toLowerCase().includes(busq)
    );
  }

  return true;
}

const rutasOrdenadas = computed(() => {
  const fuente = submodo.value === 'diario'
    ? (datosEntregas.value?.rutas || [])
    : (datosEntregasHistorico.value?.rutas || []);

  const filtradas = fuente.filter(cumpleFiltroRuta);

  return [...filtradas].sort((a, b) => {
    let va = a[ordenRuta.campo];
    let vb = b[ordenRuta.campo];
    const mult = ordenRuta.dir === 'asc' ? 1 : -1;

    if (typeof va === 'string' || typeof vb === 'string') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
});

function ordenarRutas(campo) {
  if (ordenRuta.campo === campo) {
    ordenRuta.dir = ordenRuta.dir === 'asc' ? 'desc' : 'asc';
  } else {
    ordenRuta.campo = campo;
    ordenRuta.dir = 'desc';
  }
}

function iconoSortRuta(campo) {
  if (ordenRuta.campo !== campo) return '';
  return ordenRuta.dir === 'asc' ? '▲' : '▼';
}

function colorSortRuta(campo) {
  return ordenRuta.campo === campo ? 'text-[#1D1D1F]' : 'text-[#86868B]';
}

// Cambiar a la vista Matriz pre-filtrando la ruta
async function irAMatrizRuta(ruta) {
  filtroRutaMatriz.value = ruta;
  vistaActiva.value = 'matriz';
  paginaActualMatriz.value = 1;

  if (submodo.value === 'historico' && !detallePedidosHistorico[ruta]) {
    await cargarDetalleRutaHistorico(ruta);
  }
}

async function cargarDetalleRutaHistorico(ruta) {
  cargandoDetalleHist[ruta] = true;
  errorDetalleHist[ruta] = null;
  try {
    const res = await axios.post('/supervisor/avance/entregas/historico/detalle-ruta', {
      ruta: ruta,
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
    });
    detallePedidosHistorico[ruta] = res.data?.pedidos || [];
  } catch (err) {
    errorDetalleHist[ruta] = err?.response?.data?.message || err?.message || 'Error al cargar pedidos';
  } finally {
    cargandoDetalleHist[ruta] = false;
  }
}

// Watch para cargar pedidos históricos cuando se selecciona una ruta en el dropdown de la matriz
watch(filtroRutaMatriz, async (nuevaRuta) => {
  paginaActualMatriz.value = 1;
  if (submodo.value === 'historico' && nuevaRuta !== 'TODAS' && !detallePedidosHistorico[nuevaRuta]) {
    await cargarDetalleRutaHistorico(nuevaRuta);
  }
});

// Modales y Formateadores
function abrirModalEvidencia(pedido) {
  pedidoEvidenciaModal.value = pedido;
}

function cerrarModalEvidencia() {
  pedidoEvidenciaModal.value = null;
}

function fmtBs(val) {
  return Number(val || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatearMotivo(motivo) {
  if (!motivo) return 'SIN MOTIVO';
  return String(motivo).replace(/_/g, ' ');
}

function claseBadgeEfectividad(pct) {
  const efec = Number(pct || 0);
  if (efec >= 95.0) return 'text-[#1E6B37] bg-emerald-50 border-emerald-200 font-bold';
  if (efec >= 85.0) return 'text-[#B25E00] bg-amber-50 border-amber-200 font-bold';
  return 'text-[#C9342C] bg-rose-50 border-rose-200 font-bold';
}

function claseBadgePedido(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return 'text-[#1E6B37] bg-emerald-50 border-emerald-200';
  if (estatus === 'PARCIAL') return 'text-[#B25E00] bg-amber-50 border-amber-200';
  return 'text-[#C9342C] bg-rose-50 border-rose-200';
}

function colorDotEstatus(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return 'bg-[#1E6B37]';
  if (estatus === 'PARCIAL') return 'bg-[#B25E00]';
  return 'bg-[#C9342C]';
}

function textoBadgePedido(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return '100% ENTREGADA';
  if (estatus === 'PARCIAL') return 'PARCIAL';
  return 'RECHAZO (0%)';
}

function claseBadgeItem(estatus) {
  if (estatus === 'ENTREGADO_COMPLETO') return 'text-[#1E6B37] bg-emerald-50 border-emerald-200';
  if (estatus === 'DIFERENCIA_PRECIO') return 'text-[#005EA6] bg-sky-50 border-sky-200';
  if (estatus === 'ENTREGA_PARCIAL') return 'text-[#B25E00] bg-amber-50 border-amber-200';
  return 'text-[#C9342C] bg-rose-50 border-rose-200';
}

function textoBadgeItem(estatus) {
  if (estatus === 'ENTREGADO_COMPLETO') return 'COMPLETO';
  if (estatus === 'DIFERENCIA_PRECIO') return 'DIF PRECIO';
  if (estatus === 'ENTREGA_PARCIAL') return 'PARCIAL';
  return 'NO ENTREGADO';
}

function cambiarSubmodo(nuevoModo) {
  if (submodo.value === nuevoModo) return;
  submodo.value = nuevoModo;
  filtroRutaMatriz.value = 'TODAS';
  paginaActualMatriz.value = 1;
  ejecutarConsulta();
}

function limpiarCacheHistorico() {
  Object.keys(detallePedidosHistorico).forEach(k => delete detallePedidosHistorico[k]);
  Object.keys(cargandoDetalleHist).forEach(k => delete cargandoDetalleHist[k]);
  Object.keys(errorDetalleHist).forEach(k => delete errorDetalleHist[k]);
}

async function ejecutarConsulta() {
  cargando.value = true;
  errorEntregas.value = null;

  try {
    if (submodo.value === 'historico') {
      limpiarCacheHistorico();
      const res = await axios.post('/supervisor/avance/entregas/historico', {
        fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
        fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      });
      datosEntregasHistorico.value = res.data;

      // Si el usuario tenía una ruta seleccionada en la matriz, cargar su detalle
      if (filtroRutaMatriz.value !== 'TODAS') {
        await cargarDetalleRutaHistorico(filtroRutaMatriz.value);
      }
    } else {
      const res = await axios.post('/supervisor/avance/entregas/tablero', {
        fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
        fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      });
      datosEntregas.value = res.data;
    }
  } catch (err) {
    console.error('Error al cargar Entregas:', err);
    errorEntregas.value = err?.response?.data?.message || err?.message || 'Error al consultar avance de entregas';
  } finally {
    cargando.value = false;
  }
}

onMounted(async () => {
  store.inicializarDesdeStorage(props);
  await ejecutarConsulta();
});
</script>
