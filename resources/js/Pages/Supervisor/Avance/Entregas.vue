<template>
  <SupervisorLayout title="Avance > Entregas (Cruce Preventa vs Facturación)">
    <div class="space-y-2.5 font-sans select-none text-slate-800">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── Barra de Filtros Centralizada ── -->
      <AvanceFiltrosBar @consultar="ejecutarConsulta" />

      <!-- ── Tarjeta Principal Sobria ── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <!-- ── Barra Superior de Control ── -->
        <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
          <!-- Título y Modo -->
          <div class="flex items-center gap-2.5">
            <span class="w-2 h-2 rounded-full bg-slate-500"></span>
            <span class="font-bold text-slate-900 uppercase tracking-wide text-[11px]">
              {{
                submodo === 'diario'
                  ? 'Despacho por Rutas · Cruce Preventas vs Facturación (D-1)'
                  : 'Histórico por Rango · Avance y Efectividad de Entregas'
              }}
            </span>
            <span
              v-if="submodo === 'diario'"
              class="text-[11px] text-slate-500 font-medium hidden sm:inline font-mono"
            >
              (Ref. D-1: <strong class="text-slate-800">{{ fechaD1Referencia }}</strong>)
            </span>
            <span
              v-else
              class="text-[11px] text-slate-500 font-medium hidden sm:inline font-mono"
            >
              ({{ store.filtrosAplicados?.fechaInicio || store.fechaInicio || 'Inicio' }} al {{ store.filtrosAplicados?.fechaCorte || store.fechaCorte || 'Corte' }})
            </span>
          </div>

          <!-- Controles de Modo y Vista -->
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Selector de Vista: Consolidado por Rutas vs Matriz de Pedidos -->
            <div class="inline-flex bg-slate-200/80 p-0.5 rounded-lg border border-slate-300 text-[11px]">
              <button
                type="button"
                @click="vistaActiva = 'rutas'"
                class="px-3 py-1 rounded-md transition-all cursor-pointer font-medium"
                :class="
                  vistaActiva === 'rutas'
                    ? 'bg-white text-slate-900 font-bold shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
              >
                Por Rutas
              </button>
              <button
                type="button"
                @click="vistaActiva = 'matriz'"
                class="px-3 py-1 rounded-md transition-all cursor-pointer font-medium"
                :class="
                  vistaActiva === 'matriz'
                    ? 'bg-white text-slate-900 font-bold shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
              >
                Matriz de Pedidos
              </button>
            </div>

            <!-- Selector de Submodo: Cruce Diario D-1 vs Histórico por Rango -->
            <div class="inline-flex bg-slate-200/80 p-0.5 rounded-lg border border-slate-300 text-[11px]">
              <button
                type="button"
                @click="cambiarSubmodo('diario')"
                class="px-3 py-1 rounded-md transition-all cursor-pointer font-medium"
                :class="
                  submodo === 'diario'
                    ? 'bg-white text-slate-900 font-bold shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
              >
                Cruce Diario D-1
              </button>
              <button
                type="button"
                @click="cambiarSubmodo('historico')"
                class="px-3 py-1 rounded-md transition-all cursor-pointer font-medium"
                :class="
                  submodo === 'historico'
                    ? 'bg-white text-slate-900 font-bold shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
              >
                Histórico por Rango
              </button>
            </div>

            <div class="text-[11px] text-slate-500 font-mono hidden md:inline">
              Rutas: <strong class="text-slate-800">{{ cargando ? '—' : rutasFiltradas.length }}</strong>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <!-- VISTA 1: CONSOLIDADO POR RUTAS (CON DESGLOSE EN MATRIZ AL EXPANDIR)       -->
        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <div v-if="vistaActiva === 'rutas'" class="flex flex-col">
          <div class="overflow-x-auto h-[calc(100vh-210px)] min-h-[460px] custom-scroll relative">
            <table class="w-full text-left text-[11px] sm:text-[12px] text-slate-800 border-separate border-spacing-0 font-sans">
              <thead class="bg-slate-100 text-slate-600 font-semibold text-[10px] uppercase tracking-wider sticky top-0 z-20 select-none border-b border-slate-200">
                <tr>
                  <th class="py-2 px-3 text-center w-10 sticky left-0 z-30 bg-slate-100 border-b border-r border-slate-200">
                    #
                  </th>
                  <th
                    @click="ordenarRutas('ruta')"
                    class="py-2 px-3 w-28 sticky left-10 z-30 bg-slate-100 border-b border-r border-slate-200 whitespace-nowrap cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Ruta<span class="text-[9px] ml-0.5" :class="colorSortRuta('ruta')">{{ iconoSortRuta('ruta') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('vendedor')"
                    class="py-2 px-3 min-w-[170px] sticky left-[150px] z-30 bg-slate-100 border-b border-r border-slate-200 shadow-xs cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Preventista<span class="text-[9px] ml-0.5" :class="colorSortRuta('vendedor')">{{ iconoSortRuta('vendedor') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('canal')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Canal<span class="text-[9px] ml-0.5" :class="colorSortRuta('canal')">{{ iconoSortRuta('canal') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('total_pedidos')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 font-semibold cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Preventas<span class="text-[9px] ml-0.5" :class="colorSortRuta('total_pedidos')">{{ iconoSortRuta('total_pedidos') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_entregados')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Entregadas<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_entregados')">{{ iconoSortRuta('pedidos_entregados') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_parciales')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Parciales<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_parciales')">{{ iconoSortRuta('pedidos_parciales') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_caidos')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Rechazos (0%)<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_caidos')">{{ iconoSortRuta('pedidos_caidos') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('pedidos_justificados')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Justificados<span class="text-[9px] ml-0.5" :class="colorSortRuta('pedidos_justificados')">{{ iconoSortRuta('pedidos_justificados') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('monto_preventa')"
                    class="py-2 px-3 text-right border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Prev. (Bs)<span class="text-[9px] ml-0.5" :class="colorSortRuta('monto_preventa')">{{ iconoSortRuta('monto_preventa') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('monto_facturado')"
                    class="py-2 px-3 text-right border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Fact. (Bs)<span class="text-[9px] ml-0.5" :class="colorSortRuta('monto_facturado')">{{ iconoSortRuta('monto_facturado') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('brecha_bs')"
                    class="py-2 px-3 text-right border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors font-semibold"
                  >
                    Brecha (Bs)<span class="text-[9px] ml-0.5" :class="colorSortRuta('brecha_bs')">{{ iconoSortRuta('brecha_bs') }}</span>
                  </th>
                  <th
                    @click="ordenarRutas('efectividad_pct')"
                    class="py-2 px-2.5 text-center border-b border-r border-slate-200 font-semibold cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    % Efect.<span class="text-[9px] ml-0.5" :class="colorSortRuta('efectividad_pct')">{{ iconoSortRuta('efectividad_pct') }}</span>
                  </th>
                  <th class="py-2 px-3 text-center border-b border-slate-200 w-24">
                    Desglose
                  </th>
                </tr>
              </thead>

              <!-- SKELETON LOADING -->
              <tbody v-if="cargando" class="divide-y divide-slate-100 bg-white">
                <tr v-for="i in 10" :key="'sk-rut-' + i" class="border-b border-slate-100">
                  <td class="py-2 px-3 sticky left-0 z-10 bg-white border-r border-slate-200"><div class="h-3.5 bg-slate-200 w-5 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-3 sticky left-10 z-10 bg-white border-r border-slate-200"><div class="h-3.5 bg-slate-200 w-16 rounded animate-pulse"></div></td>
                  <td class="py-2 px-3 sticky left-[150px] z-10 bg-white border-r border-slate-200"><div class="h-3.5 bg-slate-200 w-28 rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-12 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-8 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-3"><div class="h-3.5 bg-slate-200 w-16 ml-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-2.5"><div class="h-3.5 bg-slate-200 w-10 mx-auto rounded animate-pulse"></div></td>
                  <td class="py-2 px-3"><div class="h-3.5 bg-slate-200 w-14 mx-auto rounded animate-pulse"></div></td>
                </tr>
              </tbody>

              <!-- ESTADO DE ERROR -->
              <tbody v-else-if="errorEntregas" class="divide-y divide-slate-100 bg-white">
                <tr>
                  <td colspan="14" class="p-8 text-center text-rose-600 font-medium text-xs">
                    Error al consultar entregas: {{ errorEntregas }}
                  </td>
                </tr>
              </tbody>

              <!-- SIN DATOS -->
              <tbody v-else-if="rutasFiltradas.length === 0" class="divide-y divide-slate-100 bg-white">
                <tr>
                  <td colspan="14" class="p-12 text-center text-slate-500 font-medium text-xs">
                    No se encontraron rutas con los filtros seleccionados.
                  </td>
                </tr>
              </tbody>

              <!-- FILAS DE RUTAS -->
              <tbody v-else class="divide-y divide-slate-100 bg-white">
                <template v-for="(r, idx) in rutasFiltradas" :key="submodo + '-' + r.ruta">
                  <!-- Fila de la Ruta -->
                  <tr
                    @click="toggleRuta(r)"
                    class="hover:bg-slate-50/80 border-b border-slate-100 select-none text-[11px] sm:text-[12px] cursor-pointer group transition-colors"
                    :class="{ 'bg-slate-50 font-medium': rutasAbiertas[r.ruta] }"
                  >
                    <td class="py-2 px-3 text-center text-slate-400 sticky left-0 z-10 bg-white group-hover:bg-slate-50 border-r border-slate-200 font-mono tabular-nums">
                      {{ idx + 1 }}
                    </td>
                    <td class="py-2 px-3 font-semibold text-slate-900 sticky left-10 z-10 bg-white group-hover:bg-slate-50 border-r border-slate-200 whitespace-nowrap">
                      <div class="flex items-center gap-1.5">
                        <svg
                          class="w-3 h-3 text-slate-400 transition-transform duration-200 shrink-0"
                          :class="{ 'rotate-90 text-slate-800': rutasAbiertas[r.ruta] }"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                        <span>{{ r.ruta }}</span>
                      </div>
                    </td>
                    <td
                      class="py-2 px-3 text-slate-800 sticky left-[150px] z-10 bg-white group-hover:bg-slate-50 border-r border-slate-200 shadow-xs truncate max-w-[180px]"
                      :title="r.vendedor"
                    >
                      {{ r.vendedor }}
                    </td>
                    <td class="py-2 px-2.5 text-center text-slate-500 border-r border-slate-200">
                      {{ r.canal }}
                    </td>
                    <td class="py-2 px-2.5 text-center font-mono tabular-nums font-semibold text-slate-900 border-r border-slate-200">
                      {{ r.total_pedidos }}
                    </td>
                    <td class="py-2 px-2.5 text-center font-mono tabular-nums text-slate-800 border-r border-slate-200">
                      {{ r.pedidos_entregados }}
                    </td>
                    <td class="py-2 px-2.5 text-center font-mono tabular-nums text-slate-800 border-r border-slate-200">
                      {{ r.pedidos_parciales }}
                    </td>
                    <td
                      class="py-2 px-2.5 text-center font-mono tabular-nums border-r border-slate-200"
                      :class="r.pedidos_caidos > 0 ? 'text-rose-700 font-semibold' : 'text-slate-400'"
                    >
                      {{ r.pedidos_caidos }}
                    </td>
                    <td class="py-2 px-2.5 text-center font-mono tabular-nums text-slate-600 border-r border-slate-200">
                      {{ r.pedidos_justificados || 0 }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono tabular-nums text-slate-600 border-r border-slate-200">
                      {{ fmtBs(r.monto_preventa) }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono tabular-nums font-medium text-slate-900 border-r border-slate-200">
                      {{ fmtBs(r.monto_facturado) }}
                    </td>
                    <td
                      class="py-2 px-3 text-right font-mono tabular-nums border-r border-slate-200 font-medium"
                      :class="Number(r.brecha_bs || 0) < 0 ? 'text-rose-700' : 'text-slate-700'"
                    >
                      {{ Number(r.brecha_bs || 0) >= 0 ? '+' : '' }}{{ fmtBs(r.brecha_bs) }}
                    </td>
                    <td class="py-2 px-2.5 text-center font-mono tabular-nums border-r border-slate-200 font-medium">
                      {{ Number(r.efectividad_pct || 0).toFixed(1) }}%
                    </td>
                    <td class="py-2 px-3 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-medium transition-colors bg-slate-100 group-hover:bg-slate-200 text-slate-700">
                        {{ rutasAbiertas[r.ruta] ? 'Ocultar' : 'Ver Matriz' }}
                      </span>
                    </td>
                  </tr>

                  <!-- Fila Expandible: MATRIZ DE DESGLOSE DE PEDIDOS DE LA RUTA -->
                  <tr
                    v-if="rutasAbiertas[r.ruta]"
                    class="bg-slate-50/60 border-b border-slate-200"
                  >
                    <td colspan="14" class="p-3 pl-6 sm:pl-10">
                      <!-- Indicador de Carga para Histórico -->
                      <div
                        v-if="submodo === 'historico' && cargandoDetalleHist[r.ruta]"
                        class="p-6 text-center text-xs text-slate-500 font-mono bg-white rounded-lg border border-slate-200"
                      >
                        Cargando pedidos de la ruta {{ r.ruta }}...
                      </div>

                      <!-- Error al cargar detalle -->
                      <div
                        v-else-if="submodo === 'historico' && errorDetalleHist[r.ruta]"
                        class="p-4 text-center text-rose-600 text-xs bg-white rounded-lg border border-rose-200"
                      >
                        Error: {{ errorDetalleHist[r.ruta] }}
                      </div>

                      <!-- MATRIZ DE PEDIDOS DE LA RUTA -->
                      <div v-else class="bg-white rounded-lg border border-slate-200 shadow-xs overflow-hidden">
                        <!-- Barra de Herramientas de la Matriz de la Ruta -->
                        <div class="px-3.5 py-2 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2.5 text-xs">
                          <!-- Chips de Estado de la Matriz -->
                          <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mr-1">Estatus:</span>
                            <button
                              type="button"
                              @click="setFiltroEstadoRuta(r.ruta, 'TODOS')"
                              class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                              :class="getFiltroEstadoRuta(r.ruta) === 'TODOS' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'"
                            >
                              Todos ({{ obtenerConteosRuta(r).todos }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroEstadoRuta(r.ruta, 'ENTREGADO_TOTAL')"
                              class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                              :class="getFiltroEstadoRuta(r.ruta) === 'ENTREGADO_TOTAL' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                              Entregados ({{ obtenerConteosRuta(r).entregados }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroEstadoRuta(r.ruta, 'PARCIAL')"
                              class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                              :class="getFiltroEstadoRuta(r.ruta) === 'PARCIAL' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                              Parciales ({{ obtenerConteosRuta(r).parciales }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroEstadoRuta(r.ruta, 'CAIDA_TOTAL')"
                              class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                              :class="getFiltroEstadoRuta(r.ruta) === 'CAIDA_TOTAL' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                              Rechazos ({{ obtenerConteosRuta(r).caidos }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroEstadoRuta(r.ruta, 'JUSTIFICADO')"
                              class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                              :class="getFiltroEstadoRuta(r.ruta) === 'JUSTIFICADO' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                              Justificados ({{ obtenerConteosRuta(r).justificados }})
                            </button>
                          </div>

                          <!-- Buscador y Resumen de la Ruta -->
                          <div class="flex items-center gap-2">
                            <input
                              :value="getBusquedaRuta(r.ruta)"
                              @input="setBusquedaRuta(r.ruta, $event.target.value)"
                              type="text"
                              placeholder="Filtrar pedidos de la ruta..."
                              class="w-44 text-[10px] bg-white rounded px-2 py-1 border border-slate-200 focus:outline-none focus:border-slate-400 placeholder-slate-400"
                            />
                            <span class="text-[10px] text-slate-500 font-mono hidden md:inline">
                              {{ obtenerPedidosFiltradosRuta(r).length }} pedidos visibles
                            </span>
                          </div>
                        </div>

                        <!-- Tabla Matriz de Pedidos de la Ruta -->
                        <div class="overflow-x-auto max-h-[380px] custom-scroll">
                          <table class="w-full text-left font-sans text-[11px] border-separate border-spacing-0">
                            <thead class="bg-slate-100 text-slate-600 font-semibold text-[10px] uppercase tracking-wider sticky top-0 border-b border-slate-200 z-10">
                              <tr>
                                <th class="py-1.5 px-2.5 text-center w-8">#</th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'fecha_preventa')"
                                  class="py-1.5 px-2.5 w-24 cursor-pointer hover:bg-slate-200 transition-colors whitespace-nowrap"
                                >
                                  Fecha<span>{{ iconoSortPedido(r.ruta, 'fecha_preventa') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'cliente')"
                                  class="py-1.5 px-2.5 min-w-[180px] cursor-pointer hover:bg-slate-200 transition-colors"
                                >
                                  Cliente<span>{{ iconoSortPedido(r.ruta, 'cliente') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'nro_preventa')"
                                  class="py-1.5 px-2.5 text-center w-28 cursor-pointer hover:bg-slate-200 transition-colors"
                                >
                                  Prev / Fact<span>{{ iconoSortPedido(r.ruta, 'nro_preventa') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'estatus_pedido')"
                                  class="py-1.5 px-2.5 text-center w-28 cursor-pointer hover:bg-slate-200 transition-colors"
                                >
                                  Estatus<span>{{ iconoSortPedido(r.ruta, 'estatus_pedido') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'total_unidades_fact')"
                                  class="py-1.5 px-2 text-center w-20 cursor-pointer hover:bg-slate-200 transition-colors"
                                >
                                  Uds (F/P)<span>{{ iconoSortPedido(r.ruta, 'total_unidades_fact') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'total_bs_prev')"
                                  class="py-1.5 px-2.5 text-right w-24 cursor-pointer hover:bg-slate-200 transition-colors"
                                >
                                  Prev (Bs)<span>{{ iconoSortPedido(r.ruta, 'total_bs_prev') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'total_bs_fact')"
                                  class="py-1.5 px-2.5 text-right w-24 cursor-pointer hover:bg-slate-200 transition-colors"
                                >
                                  Fact (Bs)<span>{{ iconoSortPedido(r.ruta, 'total_bs_fact') }}</span>
                                </th>
                                <th
                                  @click="ordenarPedidosRuta(r.ruta, 'brecha_bs')"
                                  class="py-1.5 px-2.5 text-right w-24 cursor-pointer hover:bg-slate-200 transition-colors font-semibold"
                                >
                                  Brecha (Bs)<span>{{ iconoSortPedido(r.ruta, 'brecha_bs') }}</span>
                                </th>
                                <th class="py-1.5 px-2.5 min-w-[150px]">
                                  Justificativo App
                                </th>
                                <th class="py-1.5 px-2 text-center w-14">
                                  SKUs
                                </th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                              <tr v-if="obtenerPedidosFiltradosRuta(r).length === 0">
                                <td colspan="11" class="py-6 text-center text-slate-400 text-xs italic">
                                  No hay pedidos que coincidan con el filtro seleccionado.
                                </td>
                              </tr>
                              <template
                                v-for="(p, pIdx) in obtenerPedidosFiltradosRuta(r)"
                                :key="r.ruta + '-' + p.nro_preventa"
                              >
                                <tr
                                  @click="togglePedidoExpandido(p.nro_preventa)"
                                  class="hover:bg-slate-50 transition-colors cursor-pointer select-none"
                                  :class="{ 'bg-slate-50/70': pedidosExpandidos[p.nro_preventa] }"
                                >
                                  <td class="py-1.5 px-2.5 text-center text-slate-400 font-mono text-[10px]">{{ pIdx + 1 }}</td>
                                  <td class="py-1.5 px-2.5 font-mono text-slate-600 whitespace-nowrap">{{ p.fecha_preventa }}</td>
                                  <td class="py-1.5 px-2.5 font-medium text-slate-900 truncate max-w-[200px]" :title="p.cliente">
                                    <div class="truncate">{{ p.cliente }}</div>
                                    <div class="text-[9px] text-slate-400 font-mono">ID: {{ p.cliente_id ?? '—' }} <span v-if="p.codigo_cliente">({{ p.codigo_cliente }})</span></div>
                                  </td>
                                  <td class="py-1.5 px-2.5 text-center font-mono whitespace-nowrap">
                                    <div class="font-medium">#{{ p.nro_preventa }}</div>
                                    <div v-if="p.nro_factura" class="text-[9px] text-slate-600">Fact #{{ p.nro_factura }}</div>
                                    <div v-else class="text-[9px] text-slate-400 italic">Sin factura</div>
                                  </td>
                                  <td class="py-1.5 px-2.5 text-center">
                                    <span
                                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono border"
                                      :class="badgeClaseEstatus(p.estatus_pedido)"
                                    >
                                      <span class="w-1.5 h-1.5 rounded-full" :class="dotClaseEstatus(p.estatus_pedido)"></span>
                                      {{ textoBadgeEstatus(p.estatus_pedido) }}
                                    </span>
                                  </td>
                                  <td class="py-1.5 px-2 text-center font-mono tabular-nums text-[10px]">
                                    <span :class="p.total_unidades_fact === 0 ? 'text-rose-700 font-semibold' : 'text-slate-800'">
                                      {{ p.total_unidades_fact }}
                                    </span>
                                    <span class="text-slate-400">/{{ p.total_unidades_prev }}</span>
                                  </td>
                                  <td class="py-1.5 px-2.5 text-right font-mono tabular-nums text-slate-600">{{ fmtBs(p.total_bs_prev) }}</td>
                                  <td class="py-1.5 px-2.5 text-right font-mono tabular-nums text-slate-900 font-medium">{{ fmtBs(p.total_bs_fact) }}</td>
                                  <td
                                    class="py-1.5 px-2.5 text-right font-mono tabular-nums font-medium"
                                    :class="Number(p.brecha_bs || 0) < 0 ? 'text-rose-700' : 'text-slate-700'"
                                  >
                                    {{ Number(p.brecha_bs || 0) >= 0 ? '+' : '' }}{{ fmtBs(p.brecha_bs) }}
                                  </td>
                                  <td class="py-1.5 px-2.5">
                                    <div v-if="p.justificativo" class="flex items-center gap-1.5 flex-wrap">
                                      <span class="px-1.5 py-0.5 rounded text-[9px] bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ formatearMotivo(p.justificativo.motivo) }}
                                      </span>
                                      <button
                                        v-if="p.justificativo.photo_url"
                                        type="button"
                                        @click.stop="abrirModalEvidencia(p)"
                                        class="px-1.5 py-0.5 rounded text-[9px] bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 font-medium cursor-pointer"
                                      >
                                        Foto
                                      </button>
                                    </div>
                                    <span v-else-if="p.estatus_pedido !== 'ENTREGADO_TOTAL'" class="text-[9px] text-slate-400 italic">
                                      Sin motivo en app
                                    </span>
                                    <span v-else class="text-[9px] text-slate-400">
                                      —
                                    </span>
                                  </td>
                                  <td class="py-1.5 px-2 text-center">
                                    <span class="text-[10px] text-slate-500 font-mono">
                                      {{ (p.items || []).length }}
                                    </span>
                                  </td>
                                </tr>

                                <!-- Desglose de SKUs del Pedido -->
                                <tr v-if="pedidosExpandidos[p.nro_preventa]" class="bg-slate-50/80 border-b border-slate-200">
                                  <td colspan="11" class="p-2.5 pl-6">
                                    <div class="bg-white rounded border border-slate-200 p-2 space-y-1.5">
                                      <div v-if="p.justificativo" class="text-[11px] text-slate-700 pb-1.5 border-b border-slate-100 flex items-center justify-between">
                                        <div>
                                          <strong>Motivo:</strong> {{ formatearMotivo(p.justificativo.motivo) }}
                                          <span v-if="p.justificativo.comentarios" class="text-slate-500 italic ml-2">"{{ p.justificativo.comentarios }}"</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                          <button
                                            v-if="p.justificativo.photo_url"
                                            type="button"
                                            @click.stop="abrirModalEvidencia(p)"
                                            class="text-[10px] text-slate-700 underline font-medium cursor-pointer"
                                          >
                                            Ver Foto Evidencia
                                          </button>
                                          <a
                                            v-if="p.justificativo.latitude && p.justificativo.longitude"
                                            :href="`https://www.google.com/maps?q=${p.justificativo.latitude},${p.justificativo.longitude}`"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-[10px] text-slate-700 underline font-medium"
                                          >
                                            Ver Mapa GPS
                                          </a>
                                        </div>
                                      </div>

                                      <table class="w-full text-left font-sans text-[10px] border-separate border-spacing-0">
                                        <thead class="bg-slate-50 text-slate-500 font-medium uppercase border-b border-slate-200">
                                          <tr>
                                            <th class="py-1 px-1.5 w-6">#</th>
                                            <th class="py-1 px-1.5 w-16">Código</th>
                                            <th class="py-1 px-1.5 min-w-[160px]">Producto</th>
                                            <th class="py-1 px-1.5 text-center w-12">Uds Prev</th>
                                            <th class="py-1 px-1.5 text-center w-12">Uds Fact</th>
                                            <th class="py-1 px-1.5 text-center w-12">Dif</th>
                                            <th class="py-1 px-1.5 text-right w-16">Prev (Bs)</th>
                                            <th class="py-1 px-1.5 text-right w-16">Fact (Bs)</th>
                                            <th class="py-1 px-1.5 text-right w-16">Dif (Bs)</th>
                                            <th class="py-1 px-1.5 text-center w-20">Estatus</th>
                                          </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                          <tr v-for="(it, itIdx) in (p.items || [])" :key="itIdx" class="hover:bg-slate-50">
                                            <td class="py-1 px-1.5 text-slate-400 font-mono">{{ itIdx + 1 }}</td>
                                            <td class="py-1 px-1.5 text-slate-500 font-mono">{{ it.codigo_producto || '—' }}</td>
                                            <td class="py-1 px-1.5 text-slate-800 font-medium truncate max-w-[200px]" :title="it.producto">{{ it.producto }}</td>
                                            <td class="py-1 px-1.5 text-center font-mono">{{ it.cantidad_preventa }}</td>
                                            <td class="py-1 px-1.5 text-center font-mono" :class="it.cantidad_facturada === 0 ? 'text-rose-700 font-semibold' : 'text-slate-800'">{{ it.cantidad_facturada }}</td>
                                            <td class="py-1 px-1.5 text-center font-mono" :class="it.diferencia_unidades < 0 ? 'text-rose-700' : 'text-slate-500'">{{ it.diferencia_unidades }}</td>
                                            <td class="py-1 px-1.5 text-right font-mono text-slate-500">{{ fmtBs(it.monto_preventa) }}</td>
                                            <td class="py-1 px-1.5 text-right font-mono text-slate-800 font-medium">{{ fmtBs(it.monto_facturado) }}</td>
                                            <td class="py-1 px-1.5 text-right font-mono" :class="it.diferencia_bs < 0 ? 'text-rose-700' : 'text-slate-500'">{{ fmtBs(it.diferencia_bs) }}</td>
                                            <td class="py-1 px-1.5 text-center">
                                              <span class="px-1 py-0.5 rounded text-[8px] bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ textoBadgeItem(it.estatus) }}
                                              </span>
                                            </td>
                                          </tr>
                                        </tbody>
                                      </table>
                                    </div>
                                  </td>
                                </tr>
                              </template>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>

              <!-- Fila de Total Consolidado -->
              <tfoot v-if="rutasFiltradas.length > 0" class="bg-slate-100 text-slate-900 font-semibold text-[11px] border-t-2 border-slate-300 sticky bottom-0 z-20">
                <tr>
                  <td colspan="4" class="py-2.5 px-3 text-right uppercase tracking-wider text-slate-600 border-r border-slate-200">
                    Total Consolidado ({{ rutasFiltradas.length }} rutas):
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-slate-200">
                    {{ totalSumaRutas.pedidos }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-slate-200">
                    {{ totalSumaRutas.entregados }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-slate-200">
                    {{ totalSumaRutas.parciales }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-slate-200 text-rose-700">
                    {{ totalSumaRutas.caidos }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-slate-200">
                    {{ totalSumaRutas.justificados }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums border-r border-slate-200">
                    {{ fmtBs(totalSumaRutas.montoPrev) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums border-r border-slate-200">
                    {{ fmtBs(totalSumaRutas.montoFact) }}
                  </td>
                  <td
                    class="py-2.5 px-3 text-right font-mono tabular-nums border-r border-slate-200"
                    :class="totalSumaRutas.brecha < 0 ? 'text-rose-700' : 'text-slate-800'"
                  >
                    {{ totalSumaRutas.brecha >= 0 ? '+' : '' }}{{ fmtBs(totalSumaRutas.brecha) }}
                  </td>
                  <td class="py-2.5 px-2.5 text-center font-mono tabular-nums border-r border-slate-200">
                    {{ totalSumaRutas.efectividad }}%
                  </td>
                  <td class="py-2.5 px-3 text-center text-slate-400">—</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <!-- VISTA 2: MATRIZ GENERAL DE PEDIDOS (TODOS LOS PEDIDOS CON ORDENAMIENTO)    -->
        <!-- ══════════════════════════════════════════════════════════════════════════ -->
        <div v-else class="flex flex-col">
          <!-- Barra de Filtros Sobria de la Matriz General -->
          <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2.5 text-xs">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mr-1">Estatus:</span>
              <button
                type="button"
                @click="filtroEstadoGlobal = 'TODOS'"
                class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                :class="filtroEstadoGlobal === 'TODOS' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'"
              >
                Todos ({{ conteosGlobales.todos }})
              </button>
              <button
                type="button"
                @click="filtroEstadoGlobal = 'ENTREGADO_TOTAL'"
                class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                :class="filtroEstadoGlobal === 'ENTREGADO_TOTAL' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
              >
                Entregados ({{ conteosGlobales.entregados }})
              </button>
              <button
                type="button"
                @click="filtroEstadoGlobal = 'PARCIAL'"
                class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                :class="filtroEstadoGlobal === 'PARCIAL' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
              >
                Parciales ({{ conteosGlobales.parciales }})
              </button>
              <button
                type="button"
                @click="filtroEstadoGlobal = 'CAIDA_TOTAL'"
                class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                :class="filtroEstadoGlobal === 'CAIDA_TOTAL' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
              >
                Rechazos ({{ conteosGlobales.caidos }})
              </button>
              <button
                type="button"
                @click="filtroEstadoGlobal = 'JUSTIFICADO'"
                class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer border"
                :class="filtroEstadoGlobal === 'JUSTIFICADO' ? 'bg-slate-800 text-white border-slate-800 font-semibold' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
              >
                Justificados ({{ conteosGlobales.justificados }})
              </button>
            </div>

            <div class="flex items-center gap-2">
              <!-- Selector de Ruta para Matriz -->
              <div class="flex items-center gap-1 bg-white border border-slate-200 rounded px-2 py-1 text-[11px]">
                <label class="text-[9px] font-semibold text-slate-400 uppercase">Ruta:</label>
                <select
                  v-model="filtroRutaGlobal"
                  class="bg-transparent font-medium text-slate-800 focus:outline-none cursor-pointer"
                >
                  <option value="TODAS">Todas las Rutas ({{ rutasDisponibles.length }})</option>
                  <option v-for="rt in rutasDisponibles" :key="rt" :value="rt">{{ rt }}</option>
                </select>
              </div>

              <!-- Buscador -->
              <input
                v-model="busquedaGlobal"
                type="text"
                placeholder="Buscar cliente, preventa..."
                class="w-48 text-[11px] bg-white rounded px-2.5 py-1 border border-slate-200 focus:outline-none focus:border-slate-400 placeholder-slate-400"
              />
            </div>
          </div>

          <!-- Tabla Matriz General -->
          <div class="overflow-x-auto h-[calc(100vh-250px)] min-h-[460px] custom-scroll relative">
            <table class="w-full text-left font-sans text-[11px] sm:text-[12px] border-separate border-spacing-0">
              <thead class="bg-slate-100 text-slate-600 font-semibold text-[10px] uppercase tracking-wider sticky top-0 border-b border-slate-200 z-20">
                <tr>
                  <th class="py-2 px-3 text-center w-10 sticky left-0 z-30 bg-slate-100 border-b border-r border-slate-200">#</th>
                  <th
                    @click="ordenarMatrizGlobal('fecha_preventa')"
                    class="py-2 px-3 w-24 sticky left-10 z-30 bg-slate-100 border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors whitespace-nowrap"
                  >
                    Fecha<span>{{ iconoSortGlobal('fecha_preventa') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('cliente')"
                    class="py-2 px-3 min-w-[200px] sticky left-[134px] z-30 bg-slate-100 border-b border-r border-slate-200 shadow-xs cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Cliente / Negocio<span>{{ iconoSortGlobal('cliente') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('ruta')"
                    class="py-2 px-3 w-28 border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Ruta<span>{{ iconoSortGlobal('ruta') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('nro_preventa')"
                    class="py-2 px-3 text-center border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Prev / Fact<span>{{ iconoSortGlobal('nro_preventa') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('estatus_pedido')"
                    class="py-2 px-3 text-center w-32 border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Estatus<span>{{ iconoSortGlobal('estatus_pedido') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('total_unidades_fact')"
                    class="py-2 px-2 text-center w-20 border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Uds (F/P)<span>{{ iconoSortGlobal('total_unidades_fact') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('total_bs_prev')"
                    class="py-2 px-3 text-right border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Prev (Bs)<span>{{ iconoSortGlobal('total_bs_prev') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('total_bs_fact')"
                    class="py-2 px-3 text-right border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors"
                  >
                    Fact (Bs)<span>{{ iconoSortGlobal('total_bs_fact') }}</span>
                  </th>
                  <th
                    @click="ordenarMatrizGlobal('brecha_bs')"
                    class="py-2 px-3 text-right border-b border-r border-slate-200 cursor-pointer hover:bg-slate-200 transition-colors font-semibold"
                  >
                    Brecha (Bs)<span>{{ iconoSortGlobal('brecha_bs') }}</span>
                  </th>
                  <th class="py-2 px-3 min-w-[160px] border-b border-r border-slate-200">
                    Justificativo App
                  </th>
                  <th class="py-2 px-2 text-center w-16 border-b border-slate-200">
                    SKUs
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-if="pedidosGlobalesFiltrados.length === 0">
                  <td colspan="12" class="p-12 text-center text-slate-400 text-xs italic">
                    {{ submodo === 'historico' && filtroRutaGlobal === 'TODAS'
                      ? 'En modo histórico, seleccione una ruta específica en el selector superior para cargar su matriz de pedidos.'
                      : 'No se encontraron pedidos con los filtros actuales.'
                    }}
                  </td>
                </tr>
                <template
                  v-for="(p, idx) in pedidosGlobalesPaginados"
                  :key="'glob-' + p.nro_preventa + '-' + idx"
                >
                  <tr
                    @click="togglePedidoExpandido(p.nro_preventa)"
                    class="hover:bg-slate-50 transition-colors cursor-pointer select-none"
                    :class="{ 'bg-slate-50/80': pedidosExpandidos[p.nro_preventa] }"
                  >
                    <td class="py-2 px-3 text-center text-slate-400 font-mono text-[10px] sticky left-0 z-10 bg-white border-r border-slate-200">
                      {{ (paginaGlobal - 1) * tamanioPaginaGlobal + idx + 1 }}
                    </td>
                    <td class="py-2 px-3 font-mono text-slate-600 sticky left-10 z-10 bg-white border-r border-slate-200 whitespace-nowrap">
                      {{ p.fecha_preventa }}
                    </td>
                    <td class="py-2 px-3 font-medium text-slate-900 sticky left-[134px] z-10 bg-white border-r border-slate-200 shadow-xs truncate max-w-[210px]" :title="p.cliente">
                      <div class="truncate">{{ p.cliente }}</div>
                      <div class="text-[9px] text-slate-400 font-mono">ID: {{ p.cliente_id ?? '—' }} <span v-if="p.codigo_cliente">({{ p.codigo_cliente }})</span></div>
                    </td>
                    <td class="py-2 px-3 border-r border-slate-200 whitespace-nowrap">
                      <div class="font-medium text-slate-800">{{ p.ruta }}</div>
                      <div class="text-[9px] text-slate-400 truncate max-w-[100px]">{{ p.vendedor }}</div>
                    </td>
                    <td class="py-2 px-3 text-center font-mono whitespace-nowrap border-r border-slate-200">
                      <div class="font-medium">#{{ p.nro_preventa }}</div>
                      <div v-if="p.nro_factura" class="text-[9px] text-slate-600">Fact #{{ p.nro_factura }}</div>
                      <div v-else class="text-[9px] text-slate-400 italic">Sin factura</div>
                    </td>
                    <td class="py-2 px-3 text-center border-r border-slate-200">
                      <span
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-mono border"
                        :class="badgeClaseEstatus(p.estatus_pedido)"
                      >
                        <span class="w-1.5 h-1.5 rounded-full" :class="dotClaseEstatus(p.estatus_pedido)"></span>
                        {{ textoBadgeEstatus(p.estatus_pedido) }}
                      </span>
                    </td>
                    <td class="py-2 px-2 text-center font-mono tabular-nums text-[10px] border-r border-slate-200">
                      <span :class="p.total_unidades_fact === 0 ? 'text-rose-700 font-semibold' : 'text-slate-800'">
                        {{ p.total_unidades_fact }}
                      </span>
                      <span class="text-slate-400">/{{ p.total_unidades_prev }}</span>
                    </td>
                    <td class="py-2 px-3 text-right font-mono tabular-nums text-slate-600 border-r border-slate-200">
                      {{ fmtBs(p.total_bs_prev) }}
                    </td>
                    <td class="py-2 px-3 text-right font-mono tabular-nums text-slate-900 font-medium border-r border-slate-200">
                      {{ fmtBs(p.total_bs_fact) }}
                    </td>
                    <td
                      class="py-2 px-3 text-right font-mono tabular-nums font-medium border-r border-slate-200"
                      :class="Number(p.brecha_bs || 0) < 0 ? 'text-rose-700' : 'text-slate-700'"
                    >
                      {{ Number(p.brecha_bs || 0) >= 0 ? '+' : '' }}{{ fmtBs(p.brecha_bs) }}
                    </td>
                    <td class="py-2 px-3 border-r border-slate-200">
                      <div v-if="p.justificativo" class="flex items-center gap-1.5 flex-wrap">
                        <span class="px-1.5 py-0.5 rounded text-[9px] bg-slate-100 text-slate-700 border border-slate-200">
                          {{ formatearMotivo(p.justificativo.motivo) }}
                        </span>
                        <button
                          v-if="p.justificativo.photo_url"
                          type="button"
                          @click.stop="abrirModalEvidencia(p)"
                          class="px-1.5 py-0.5 rounded text-[9px] bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 font-medium cursor-pointer"
                        >
                          Foto
                        </button>
                      </div>
                      <span v-else-if="p.estatus_pedido !== 'ENTREGADO_TOTAL'" class="text-[9px] text-slate-400 italic">
                        Sin motivo
                      </span>
                      <span v-else class="text-[9px] text-slate-400">—</span>
                    </td>
                    <td class="py-2 px-2 text-center">
                      <span class="text-[10px] text-slate-500 font-mono">
                        {{ (p.items || []).length }}
                      </span>
                    </td>
                  </tr>

                  <!-- Desglose de SKUs -->
                  <tr v-if="pedidosExpandidos[p.nro_preventa]" class="bg-slate-50/80 border-b border-slate-200">
                    <td colspan="12" class="p-2.5 pl-8 sm:pl-12">
                      <div class="bg-white rounded border border-slate-200 p-2 space-y-1.5">
                        <div v-if="p.justificativo" class="text-[11px] text-slate-700 pb-1.5 border-b border-slate-100 flex items-center justify-between">
                          <div>
                            <strong>Motivo:</strong> {{ formatearMotivo(p.justificativo.motivo) }}
                            <span v-if="p.justificativo.comentarios" class="text-slate-500 italic ml-2">"{{ p.justificativo.comentarios }}"</span>
                          </div>
                          <div class="flex items-center gap-2">
                            <button
                              v-if="p.justificativo.photo_url"
                              type="button"
                              @click.stop="abrirModalEvidencia(p)"
                              class="text-[10px] text-slate-700 underline font-medium cursor-pointer"
                            >
                              Ver Foto
                            </button>
                            <a
                              v-if="p.justificativo.latitude && p.justificativo.longitude"
                              :href="`https://www.google.com/maps?q=${p.justificativo.latitude},${p.justificativo.longitude}`"
                              target="_blank"
                              rel="noopener noreferrer"
                              class="text-[10px] text-slate-700 underline font-medium"
                            >
                              GPS
                            </a>
                          </div>
                        </div>

                        <table class="w-full text-left font-sans text-[10px] border-separate border-spacing-0">
                          <thead class="bg-slate-50 text-slate-500 font-medium uppercase border-b border-slate-200">
                            <tr>
                              <th class="py-1 px-1.5 w-6">#</th>
                              <th class="py-1 px-1.5 w-16">Código</th>
                              <th class="py-1 px-1.5 min-w-[160px]">Producto</th>
                              <th class="py-1 px-1.5 text-center w-12">Uds Prev</th>
                              <th class="py-1 px-1.5 text-center w-12">Uds Fact</th>
                              <th class="py-1 px-1.5 text-center w-12">Dif</th>
                              <th class="py-1 px-1.5 text-right w-16">Prev (Bs)</th>
                              <th class="py-1 px-1.5 text-right w-16">Fact (Bs)</th>
                              <th class="py-1 px-1.5 text-right w-16">Dif (Bs)</th>
                              <th class="py-1 px-1.5 text-center w-20">Estatus</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-100">
                            <tr v-for="(it, itIdx) in (p.items || [])" :key="itIdx" class="hover:bg-slate-50">
                              <td class="py-1 px-1.5 text-slate-400 font-mono">{{ itIdx + 1 }}</td>
                              <td class="py-1 px-1.5 text-slate-500 font-mono">{{ it.codigo_producto || '—' }}</td>
                              <td class="py-1 px-1.5 text-slate-800 font-medium truncate max-w-[200px]" :title="it.producto">{{ it.producto }}</td>
                              <td class="py-1 px-1.5 text-center font-mono">{{ it.cantidad_preventa }}</td>
                              <td class="py-1 px-1.5 text-center font-mono" :class="it.cantidad_facturada === 0 ? 'text-rose-700 font-semibold' : 'text-slate-800'">{{ it.cantidad_facturada }}</td>
                              <td class="py-1 px-1.5 text-center font-mono" :class="it.diferencia_unidades < 0 ? 'text-rose-700' : 'text-slate-500'">{{ it.diferencia_unidades }}</td>
                              <td class="py-1 px-1.5 text-right font-mono text-slate-500">{{ fmtBs(it.monto_preventa) }}</td>
                              <td class="py-1 px-1.5 text-right font-mono text-slate-800 font-medium">{{ fmtBs(it.monto_facturado) }}</td>
                              <td class="py-1 px-1.5 text-right font-mono" :class="it.diferencia_bs < 0 ? 'text-rose-700' : 'text-slate-500'">{{ fmtBs(it.diferencia_bs) }}</td>
                              <td class="py-1 px-1.5 text-center">
                                <span class="px-1 py-0.5 rounded text-[8px] bg-slate-100 text-slate-700 border border-slate-200">
                                  {{ textoBadgeItem(it.estatus) }}
                                </span>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Paginación de la Matriz General -->
          <div class="px-4 py-2 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="text-slate-500 text-[11px]">
              Mostrando <strong class="text-slate-800">{{ pedidosGlobalesPaginados.length }}</strong> de <strong class="text-slate-800">{{ pedidosGlobalesFiltrados.length }}</strong> pedidos
            </span>
            <div class="flex items-center gap-1 font-mono text-[11px]">
              <button
                type="button"
                @click="paginaGlobal = Math.max(1, paginaGlobal - 1)"
                :disabled="paginaGlobal <= 1"
                class="px-2 py-0.5 rounded bg-white border border-slate-300 hover:bg-slate-100 disabled:opacity-40 cursor-pointer"
              >
                Anterior
              </button>
              <span class="px-2 text-slate-600">Pág. {{ paginaGlobal }} / {{ totalPaginasGlobal }}</span>
              <button
                type="button"
                @click="paginaGlobal = Math.min(totalPaginasGlobal, paginaGlobal + 1)"
                :disabled="paginaGlobal >= totalPaginasGlobal"
                class="px-2 py-0.5 rounded bg-white border border-slate-300 hover:bg-slate-100 disabled:opacity-40 cursor-pointer"
              >
                Siguiente
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Modal de Evidencia Fotográfica y GPS (Sobrio) ── -->
      <div
        v-if="pedidoEvidenciaModal"
        @click="cerrarModalEvidencia"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
      >
        <div
          @click.stop
          class="bg-white rounded-xl shadow-xl border border-slate-300 max-w-md w-full overflow-hidden"
        >
          <div class="px-4 py-2.5 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
            <div>
              <h3 class="text-xs font-bold text-slate-900 uppercase">
                Evidencia de Rechazo · Prev #{{ pedidoEvidenciaModal.nro_preventa }}
              </h3>
              <p class="text-[11px] text-slate-600 truncate">
                {{ pedidoEvidenciaModal.cliente }} (ID: {{ pedidoEvidenciaModal.cliente_id }})
              </p>
            </div>
            <button
              type="button"
              @click="cerrarModalEvidencia"
              class="w-6 h-6 rounded bg-slate-200 hover:bg-slate-300 flex items-center justify-center text-slate-800 text-xs font-bold cursor-pointer"
            >
              ✕
            </button>
          </div>

          <div class="p-3.5 space-y-2.5">
            <div class="rounded bg-slate-50 border border-slate-200 flex items-center justify-center max-h-[340px] overflow-hidden">
              <img
                v-if="pedidoEvidenciaModal.justificativo?.photo_url"
                :src="pedidoEvidenciaModal.justificativo.photo_url"
                alt="Evidencia fotográfica"
                class="max-h-[340px] w-auto object-contain"
              />
            </div>

            <div class="p-2.5 rounded bg-slate-50 border border-slate-200 space-y-1 text-xs">
              <div class="flex justify-between items-center text-slate-800 font-medium">
                <span>Motivo: <strong>{{ formatearMotivo(pedidoEvidenciaModal.justificativo?.motivo) }}</strong></span>
                <span class="font-mono text-[10px] text-slate-500">{{ pedidoEvidenciaModal.justificativo?.fecha_rechazo || '' }}</span>
              </div>
              <p class="text-slate-600 text-[11px]">
                <strong>Comentario:</strong> {{ pedidoEvidenciaModal.justificativo?.comentarios || 'Sin comentarios registrados.' }}
              </p>
              <div
                v-if="pedidoEvidenciaModal.justificativo?.latitude && pedidoEvidenciaModal.justificativo?.longitude"
                class="pt-1 text-right"
              >
                <a
                  :href="`https://www.google.com/maps?q=${pedidoEvidenciaModal.justificativo.latitude},${pedidoEvidenciaModal.justificativo.longitude}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-[11px] text-slate-800 underline font-medium hover:text-black"
                >
                  Abrir ubicación GPS en Google Maps →
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

// Navegación
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance');
const avanceTabs = avanceMenu ? avanceMenu.submodules : [];
const store = useAvanceStore();

// Modos de Visualización
const vistaActiva = ref('rutas'); // 'rutas' | 'matriz'
const submodo = ref('diario'); // 'diario' | 'historico'

// Estado de carga y datos
const cargando = ref(false);
const errorEntregas = ref(null);
const datosEntregas = ref(null);
const datosEntregasHistorico = ref(null);

// Estado de acordeones de rutas
const rutasAbiertas = reactive({});
const pedidosExpandidos = reactive({});
const pedidoEvidenciaModal = ref(null);

// Caché de detalle de rutas para histórico
const detallePedidosHistorico = reactive({});
const cargandoDetalleHist = reactive({});
const errorDetalleHist = reactive({});

// Filtros y ordenamiento por ruta individual
const filtrosEstadoPorRuta = reactive({});
const busquedasPorRuta = reactive({});
const ordenPorRuta = reactive({});

// Ordenamiento de la tabla de Rutas
const ordenRutas = reactive({
  campo: 'efectividad_pct',
  dir: 'asc',
});

// ── Filtros y ordenamiento para la Matriz Global ──
const filtroEstadoGlobal = ref('TODOS');
const filtroRutaGlobal = ref('TODAS');
const busquedaGlobal = ref('');
const paginaGlobal = ref(1);
const tamanioPaginaGlobal = ref(50);
const ordenGlobal = reactive({
  campo: 'fecha_preventa',
  dir: 'desc',
});

// Referencia D-1
const fechaD1Referencia = computed(() => {
  return datosEntregas.value?.fecha_d1_referencia || '-';
});

// Rutas disponibles
const rutasDisponibles = computed(() => {
  const fuente = submodo.value === 'diario'
    ? (datosEntregas.value?.rutas || [])
    : (datosEntregasHistorico.value?.rutas || []);
  return fuente.map(r => r.ruta).filter(Boolean).sort();
});

// Filtro de rutas según filtros globales del store
function cumpleFiltroStore(r) {
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

const rutasFiltradas = computed(() => {
  const fuente = submodo.value === 'diario'
    ? (datosEntregas.value?.rutas || [])
    : (datosEntregasHistorico.value?.rutas || []);

  const filtradas = fuente.filter(cumpleFiltroStore);

  return [...filtradas].sort((a, b) => {
    let va = a[ordenRutas.campo];
    let vb = b[ordenRutas.campo];
    const mult = ordenRutas.dir === 'asc' ? 1 : -1;

    if (typeof va === 'string' || typeof vb === 'string') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
});

// Sumatoria total de la tabla consolidada
const totalSumaRutas = computed(() => {
  let pedidos = 0, entregados = 0, parciales = 0, caidos = 0, justificados = 0;
  let montoPrev = 0, montoFact = 0;

  rutasFiltradas.value.forEach(r => {
    pedidos += Number(r.total_pedidos || 0);
    entregados += Number(r.pedidos_entregados || 0);
    parciales += Number(r.pedidos_parciales || 0);
    caidos += Number(r.pedidos_caidos || 0);
    justificados += Number(r.pedidos_justificados || 0);
    montoPrev += Number(r.monto_preventa || 0);
    montoFact += Number(r.monto_facturado || 0);
  });

  const brecha = Math.round((montoFact - montoPrev) * 100) / 100;
  const efec = montoPrev > 0 ? Math.round((montoFact / montoPrev) * 1000) / 10 : 0;

  return {
    pedidos,
    entregados,
    parciales,
    caidos,
    justificados,
    montoPrev: Math.round(montoPrev * 100) / 100,
    montoFact: Math.round(montoFact * 100) / 100,
    brecha,
    efectividad: efec.toFixed(1),
  };
});

function ordenarRutas(campo) {
  if (ordenRutas.campo === campo) {
    ordenRutas.dir = ordenRutas.dir === 'asc' ? 'desc' : 'asc';
  } else {
    ordenRutas.campo = campo;
    ordenRutas.dir = (campo === 'ruta' || campo === 'vendedor' || campo === 'canal') ? 'asc' : 'desc';
  }
}

function iconoSortRuta(campo) {
  if (ordenRutas.campo !== campo) return '';
  return ordenRutas.dir === 'asc' ? '▲' : '▼';
}

function colorSortRuta(campo) {
  return ordenRutas.campo === campo ? 'text-slate-900 font-bold' : 'text-slate-400';
}

// ── EXPANDIR RUTA Y GESTIONAR MATRIZ INTERNA ──
async function toggleRuta(r) {
  const ruta = r.ruta;
  const nuevoEstado = !rutasAbiertas[ruta];
  rutasAbiertas[ruta] = nuevoEstado;

  if (nuevoEstado && submodo.value === 'historico' && !detallePedidosHistorico[ruta]) {
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
    detallePedidosHistorico[ruta] = (res.data?.pedidos || []).map(p => ({
      ...p,
      ruta: p.ruta || ruta,
    }));
  } catch (err) {
    console.error(`Error al cargar histórico de ${ruta}:`, err);
    errorDetalleHist[ruta] = err?.response?.data?.message || err?.message || 'Error al cargar pedidos';
  } finally {
    cargandoDetalleHist[ruta] = false;
  }
}

function obtenerPedidosBaseRuta(r) {
  if (submodo.value === 'diario') {
    return (r.pedidos || []).map(p => ({
      ...p,
      ruta: p.ruta || r.ruta,
      canal: p.canal || r.canal,
      vendedor: p.vendedor || r.vendedor,
    }));
  }
  return detallePedidosHistorico[r.ruta] || [];
}

function getFiltroEstadoRuta(ruta) {
  return filtrosEstadoPorRuta[ruta] || 'TODOS';
}

function setFiltroEstadoRuta(ruta, estado) {
  filtrosEstadoPorRuta[ruta] = estado;
}

function getBusquedaRuta(ruta) {
  return busquedasPorRuta[ruta] || '';
}

function setBusquedaRuta(ruta, val) {
  busquedasPorRuta[ruta] = val;
}

function obtenerConteosRuta(r) {
  const base = obtenerPedidosBaseRuta(r);
  return {
    todos: base.length,
    entregados: base.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL').length,
    parciales: base.filter(p => p.estatus_pedido === 'PARCIAL').length,
    caidos: base.filter(p => p.estatus_pedido === 'CAIDA_TOTAL').length,
    justificados: base.filter(p => Boolean(p.justificativo)).length,
  };
}

function obtenerPedidosFiltradosRuta(r) {
  let list = obtenerPedidosBaseRuta(r);
  const estado = getFiltroEstadoRuta(r.ruta);
  const q = getBusquedaRuta(r.ruta).toLowerCase().trim();

  if (estado === 'ENTREGADO_TOTAL') {
    list = list.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL');
  } else if (estado === 'PARCIAL') {
    list = list.filter(p => p.estatus_pedido === 'PARCIAL');
  } else if (estado === 'CAIDA_TOTAL') {
    list = list.filter(p => p.estatus_pedido === 'CAIDA_TOTAL');
  } else if (estado === 'JUSTIFICADO') {
    list = list.filter(p => Boolean(p.justificativo));
  }

  if (q) {
    list = list.filter(p =>
      String(p.cliente || '').toLowerCase().includes(q) ||
      String(p.codigo_cliente || '').toLowerCase().includes(q) ||
      String(p.cliente_id || '').toLowerCase().includes(q) ||
      String(p.nro_preventa || '').toLowerCase().includes(q) ||
      String(p.nro_factura || '').toLowerCase().includes(q) ||
      String(p.justificativo?.motivo || '').toLowerCase().includes(q)
    );
  }

  const sortCfg = ordenPorRuta[r.ruta] || { campo: 'fecha_preventa', dir: 'desc' };
  const campo = sortCfg.campo;
  const mult = sortCfg.dir === 'asc' ? 1 : -1;

  return [...list].sort((a, b) => {
    let va = a[campo];
    let vb = b[campo];

    if (campo === 'fecha_preventa' || campo === 'ruta' || campo === 'estatus_pedido') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    if (campo === 'cliente') {
      return String(va || '').localeCompare(String(vb || ''), 'es', { sensitivity: 'base' }) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
}

function ordenarPedidosRuta(ruta, campo) {
  if (!ordenPorRuta[ruta]) {
    ordenPorRuta[ruta] = { campo: 'fecha_preventa', dir: 'desc' };
  }
  if (ordenPorRuta[ruta].campo === campo) {
    ordenPorRuta[ruta].dir = ordenPorRuta[ruta].dir === 'asc' ? 'desc' : 'asc';
  } else {
    ordenPorRuta[ruta].campo = campo;
    ordenPorRuta[ruta].dir = (campo === 'fecha_preventa' || campo === 'total_bs_prev' || campo === 'total_bs_fact' || campo === 'brecha_bs')
      ? 'desc'
      : 'asc';
  }
}

function iconoSortPedido(ruta, campo) {
  const cfg = ordenPorRuta[ruta];
  if (!cfg || cfg.campo !== campo) return '';
  return cfg.dir === 'asc' ? ' ▲' : ' ▼';
}

function togglePedidoExpandido(nroPrev) {
  pedidosExpandidos[nroPrev] = !pedidosExpandidos[nroPrev];
}

// ── MATRIZ GLOBAL DE PEDIDOS ──
const todosLosPedidosGlobales = computed(() => {
  if (submodo.value === 'diario') {
    const list = [];
    (datosEntregas.value?.rutas || []).forEach(r => {
      (r.pedidos || []).forEach(p => {
        list.push({
          ...p,
          ruta: p.ruta || r.ruta,
          canal: p.canal || r.canal,
          vendedor: p.vendedor || r.vendedor,
        });
      });
    });
    return list;
  }

  // En histórico: si hay ruta seleccionada
  if (filtroRutaGlobal.value !== 'TODAS') {
    return detallePedidosHistorico[filtroRutaGlobal.value] || [];
  }

  // Si no, combinar lo que esté cargado
  const list = [];
  Object.keys(detallePedidosHistorico).forEach(rt => {
    (detallePedidosHistorico[rt] || []).forEach(p => {
      list.push({ ...p, ruta: p.ruta || rt });
    });
  });
  return list;
});

const conteosGlobales = computed(() => {
  const base = todosLosPedidosGlobales.value;
  return {
    todos: base.length,
    entregados: base.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL').length,
    parciales: base.filter(p => p.estatus_pedido === 'PARCIAL').length,
    caidos: base.filter(p => p.estatus_pedido === 'CAIDA_TOTAL').length,
    justificados: base.filter(p => Boolean(p.justificativo)).length,
  };
});

const pedidosGlobalesFiltrados = computed(() => {
  let list = todosLosPedidosGlobales.value;

  if (filtroRutaGlobal.value !== 'TODAS') {
    const rNorm = String(filtroRutaGlobal.value).trim().toUpperCase();
    list = list.filter(p => String(p.ruta || '').trim().toUpperCase() === rNorm);
  }

  if (filtroEstadoGlobal.value === 'ENTREGADO_TOTAL') {
    list = list.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL');
  } else if (filtroEstadoGlobal.value === 'PARCIAL') {
    list = list.filter(p => p.estatus_pedido === 'PARCIAL');
  } else if (filtroEstadoGlobal.value === 'CAIDA_TOTAL') {
    list = list.filter(p => p.estatus_pedido === 'CAIDA_TOTAL');
  } else if (filtroEstadoGlobal.value === 'JUSTIFICADO') {
    list = list.filter(p => Boolean(p.justificativo));
  }

  const q = busquedaGlobal.value.toLowerCase().trim();
  if (q) {
    list = list.filter(p =>
      String(p.cliente || '').toLowerCase().includes(q) ||
      String(p.codigo_cliente || '').toLowerCase().includes(q) ||
      String(p.cliente_id || '').toLowerCase().includes(q) ||
      String(p.nro_preventa || '').toLowerCase().includes(q) ||
      String(p.nro_factura || '').toLowerCase().includes(q) ||
      String(p.ruta || '').toLowerCase().includes(q) ||
      String(p.vendedor || '').toLowerCase().includes(q) ||
      String(p.justificativo?.motivo || '').toLowerCase().includes(q)
    );
  }

  const campo = ordenGlobal.campo;
  const mult = ordenGlobal.dir === 'asc' ? 1 : -1;

  return [...list].sort((a, b) => {
    let va = a[campo];
    let vb = b[campo];

    if (campo === 'fecha_preventa' || campo === 'ruta' || campo === 'estatus_pedido') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    if (campo === 'cliente') {
      return String(va || '').localeCompare(String(vb || ''), 'es', { sensitivity: 'base' }) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
});

const totalPaginasGlobal = computed(() => {
  return Math.ceil(pedidosGlobalesFiltrados.value.length / tamanioPaginaGlobal.value) || 1;
});

const pedidosGlobalesPaginados = computed(() => {
  const start = (paginaGlobal.value - 1) * tamanioPaginaGlobal.value;
  return pedidosGlobalesFiltrados.value.slice(start, start + tamanioPaginaGlobal.value);
});

function ordenarMatrizGlobal(campo) {
  if (ordenGlobal.campo === campo) {
    ordenGlobal.dir = ordenGlobal.dir === 'asc' ? 'desc' : 'asc';
  } else {
    ordenGlobal.campo = campo;
    ordenGlobal.dir = (campo === 'fecha_preventa' || campo === 'total_bs_prev' || campo === 'total_bs_fact' || campo === 'brecha_bs')
      ? 'desc'
      : 'asc';
  }
}

function iconoSortGlobal(campo) {
  if (ordenGlobal.campo !== campo) return '';
  return ordenGlobal.dir === 'asc' ? ' ▲' : ' ▼';
}

watch(filtroRutaGlobal, async (nuevaRuta) => {
  paginaGlobal.value = 1;
  if (submodo.value === 'historico' && nuevaRuta !== 'TODAS' && !detallePedidosHistorico[nuevaRuta]) {
    await cargarDetalleRutaHistorico(nuevaRuta);
  }
});

// Modales y Formateadores
function abrirModalEvidencia(p) {
  pedidoEvidenciaModal.value = p;
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

function badgeClaseEstatus(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return 'bg-slate-100 text-slate-800 border-slate-300';
  if (estatus === 'PARCIAL') return 'bg-slate-100 text-slate-800 border-slate-300';
  return 'bg-slate-100 text-rose-800 border-rose-300 font-semibold';
}

function dotClaseEstatus(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return 'bg-emerald-600';
  if (estatus === 'PARCIAL') return 'bg-amber-600';
  return 'bg-rose-600';
}

function textoBadgeEstatus(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return 'ENTREGADO';
  if (estatus === 'PARCIAL') return 'PARCIAL';
  return 'RECHAZO 0%';
}

function textoBadgeItem(estatus) {
  if (estatus === 'ENTREGADO_COMPLETO') return 'OK';
  if (estatus === 'DIFERENCIA_PRECIO') return 'DIF PRECIO';
  if (estatus === 'ENTREGA_PARCIAL') return 'PARCIAL';
  return 'NO ENTREGADO';
}

function cambiarSubmodo(nuevoModo) {
  if (submodo.value === nuevoModo) return;
  submodo.value = nuevoModo;
  filtroRutaGlobal.value = 'TODAS';
  paginaGlobal.value = 1;
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

      // Si hay una ruta seleccionada en la matriz global, cargarla
      if (filtroRutaGlobal.value !== 'TODAS') {
        await cargarDetalleRutaHistorico(filtroRutaGlobal.value);
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
    errorEntregas.value = err?.response?.data?.message || err?.message || 'Error al consultar entregas';
  } finally {
    cargando.value = false;
  }
}

onMounted(async () => {
  store.inicializarDesdeStorage(props);
  await ejecutarConsulta();
});
</script>
