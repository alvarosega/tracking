<template>
  <SupervisorLayout title="Avance > Entregas (Cruce Preventa vs Facturación)">
    <div class="space-y-2.5 font-sans select-none">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── Barra de Filtros Centralizada ── -->
      <AvanceFiltrosBar @consultar="ejecutarConsulta" />

      <!-- ── Tarjeta Principal Liquid Glass ── -->
      <div class="apple-glass-card rounded-[14px] shadow-xs overflow-hidden border border-black/[0.08] bg-white">
        <!-- Barra Superior de la Tabla -->
        <div class="px-4 py-3 bg-slate-50/80 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-3 text-xs">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
            <span class="font-bold text-[#1D1D1F] uppercase tracking-wide text-[12px]">
              {{
                submodo === 'diario'
                  ? 'Despacho por Rutas · Cruce Preventas vs Facturación (D-1)'
                  : 'Histórico por Rango · Avance y Efectividad Acumulada de Entregas'
              }}
            </span>
            <span
              v-if="submodo === 'diario'"
              class="text-[11px] text-[#6E6E73] font-medium hidden sm:inline"
            >
              (Referencia Operativa D-1:
              <strong class="font-mono text-[#1D1D1F]">{{ fechaD1Referencia }}</strong>)
            </span>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <!-- Selector de Submodo: Cruce Diario D-1 vs Histórico por Rango -->
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

            <div class="flex items-center gap-3 text-[11px] text-[#6E6E73] font-mono">
              <div v-if="submodo === 'diario'" class="flex items-center gap-1.5 font-sans">
                <span class="w-1.5 h-1.5 rounded-full bg-[#C9342C]"></span>
                <span>Caída Crítica</span>
              </div>
              <span v-if="submodo === 'diario'" class="text-slate-300">|</span>
              <div>
                Total Rutas:
                <strong class="text-[#1D1D1F] font-mono font-bold">
                  {{ cargando ? '—' : rutasOrdenadas.length }}
                </strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla con Scroll -->
        <div class="overflow-x-auto h-[calc(100vh-230px)] min-h-[480px] custom-scroll relative">
          <table class="w-full text-left text-[11px] sm:text-[12px] text-[#1D1D1F] border-separate border-spacing-0 font-sans">
            <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[10px] uppercase tracking-tight sticky top-0 z-20 select-none border-b border-black/[0.08]">
              <tr>
                <th class="py-2.5 px-3 text-center w-12 sticky left-0 z-30 bg-slate-100 border-b border-r border-black/[0.06]">
                  Pos
                </th>
                <th
                  @click="ordenarPor('ruta')"
                  class="py-2.5 px-3 w-28 sticky left-12 z-30 bg-slate-100 border-b border-r border-black/[0.06] whitespace-nowrap cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Ruta<span class="text-[9px] ml-0.5" :class="colorSort('ruta')">{{ iconoSort('ruta') }}</span>
                </th>
                <th
                  @click="ordenarPor('vendedor')"
                  class="py-2.5 px-3.5 min-w-[180px] sticky left-[112px] z-30 bg-slate-100 border-b border-r border-black/[0.06] shadow-[2px_0_6px_rgba(0,0,0,0.03)] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Preventista<span class="text-[9px] ml-0.5" :class="colorSort('vendedor')">{{ iconoSort('vendedor') }}</span>
                </th>
                <th
                  @click="ordenarPor('canal')"
                  class="py-2.5 px-2.5 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Canal<span class="text-[9px] ml-0.5" :class="colorSort('canal')">{{ iconoSort('canal') }}</span>
                </th>
                <th
                  @click="ordenarPor('total_pedidos')"
                  class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] font-bold cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Preventas Base<span class="text-[9px] ml-0.5" :class="colorSort('total_pedidos')">{{ iconoSort('total_pedidos') }}</span>
                </th>
                <th
                  @click="ordenarPor('pedidos_entregados')"
                  class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Entregadas<span class="text-[9px] ml-0.5" :class="colorSort('pedidos_entregados')">{{ iconoSort('pedidos_entregados') }}</span>
                </th>
                <th
                  @click="ordenarPor('pedidos_parciales')"
                  class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Parciales<span class="text-[9px] ml-0.5" :class="colorSort('pedidos_parciales')">{{ iconoSort('pedidos_parciales') }}</span>
                </th>
                <th
                  @click="ordenarPor('pedidos_caidos')"
                  class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Caídas (0%)<span class="text-[9px] ml-0.5" :class="colorSort('pedidos_caidos')">{{ iconoSort('pedidos_caidos') }}</span>
                </th>
                <th
                  @click="ordenarPor('pedidos_justificados')"
                  class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                  title="Pedidos con justificativo de rechazo registrado en app"
                >
                  Justificados<span class="text-[9px] ml-0.5" :class="colorSort('pedidos_justificados')">{{ iconoSort('pedidos_justificados') }}</span>
                </th>
                <th
                  @click="ordenarPor('monto_preventa')"
                  class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Prev. (Bs)<span class="text-[9px] ml-0.5" :class="colorSort('monto_preventa')">{{ iconoSort('monto_preventa') }}</span>
                </th>
                <th
                  @click="ordenarPor('monto_facturado')"
                  class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Fact. (Bs)<span class="text-[9px] ml-0.5" :class="colorSort('monto_facturado')">{{ iconoSort('monto_facturado') }}</span>
                </th>
                <th
                  @click="ordenarPor('brecha_bs')"
                  class="py-2.5 px-3 text-right border-b border-r border-black/[0.06] cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  Brecha (Bs)<span class="text-[9px] ml-0.5" :class="colorSort('brecha_bs')">{{ iconoSort('brecha_bs') }}</span>
                </th>
                <th
                  @click="ordenarPor('efectividad_pct')"
                  class="py-2.5 px-3 text-center border-b border-r border-black/[0.06] font-bold cursor-pointer hover:bg-slate-200 transition-colors"
                >
                  % Efect.<span class="text-[9px] ml-0.5" :class="colorSort('efectividad_pct')">{{ iconoSort('efectividad_pct') }}</span>
                </th>
                <th class="py-2.5 px-4 text-center border-b border-black/[0.06] w-24">
                  Acción
                </th>
              </tr>
            </thead>

            <!-- SKELETON LOADING (10 filas) -->
            <tbody v-if="cargando" class="divide-y divide-black/[0.04] bg-white">
              <tr v-for="i in 10" :key="'sk-ent-' + i" class="border-b border-black/[0.04]">
                <td class="py-2.5 px-3 sticky left-0 z-10 bg-white border-r border-black/[0.06]">
                  <div class="h-3.5 bg-slate-200 w-5 mx-auto rounded animate-pulse"></div>
                </td>
                <td class="py-2.5 px-3 sticky left-12 z-10 bg-white border-r border-black/[0.06]">
                  <div class="h-3.5 bg-slate-200 w-16 rounded animate-pulse"></div>
                </td>
                <td class="py-2.5 px-3.5 sticky left-[112px] z-10 bg-white border-r border-black/[0.06]">
                  <div class="h-3.5 bg-slate-200 w-32 rounded animate-pulse"></div>
                </td>
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
                <td class="py-2.5 px-4"><div class="h-3.5 bg-slate-200 w-14 mx-auto rounded animate-pulse"></div></td>
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

            <!-- FILAS DE RUTAS + ACORDEÓN DE 3 NIVELES -->
            <tbody v-else class="divide-y divide-black/[0.04] bg-white">
              <template v-for="(r, idx) in rutasOrdenadas" :key="submodo + '-' + r.ruta">
                <!-- Fila Nivel 1: Ruta -->
                <tr
                  @click="toggleRuta(r)"
                  class="hover:bg-slate-50 border-b border-black/[0.04] select-none text-[11px] sm:text-[12px] cursor-pointer group transition-colors"
                >
                  <td class="py-2.5 px-3 text-center text-[#6E6E73] sticky left-0 z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] font-mono tabular-nums font-bold">
                    {{ idx + 1 }}
                  </td>
                  <td class="py-2.5 px-3 font-bold text-[#1D1D1F] sticky left-12 z-10 bg-white group-hover:bg-slate-50 border-r border-black/[0.06] whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                      <svg
                        class="w-3 h-3 text-[#86868B] transition-transform duration-200 inline-block"
                        :class="{ 'rotate-90': rutasAbiertas[keyRuta(r.ruta)] }"
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
                    <span class="inline-flex items-center px-2 py-0.5 rounded-[4px] bg-slate-100 group-hover:bg-slate-200 text-[#1D1D1F] text-[10px] font-bold transition-colors">
                      Auditar
                    </span>
                  </td>
                </tr>

                <!-- Fila Expandible Nivel 1: Contenedor de Secciones de la Ruta -->
                <tr
                  v-if="rutasAbiertas[keyRuta(r.ruta)]"
                  class="bg-slate-50 border-b border-black/[0.08]"
                >
                  <td colspan="14" class="p-3 sm:p-4 pl-6 sm:pl-10">
                    <!-- Skeleton para carga histórica de ruta -->
                    <div
                      v-if="submodo === 'historico' && cargandoDetalleHist[r.ruta]"
                      class="p-4 space-y-3 bg-white rounded-xl border border-black/[0.06] shadow-xs"
                    >
                      <div class="flex items-center justify-between">
                        <div class="h-4 bg-slate-200 w-48 rounded animate-pulse"></div>
                        <div class="h-4 bg-slate-200 w-32 rounded animate-pulse"></div>
                      </div>
                      <div class="space-y-2">
                        <div class="h-10 bg-slate-200 w-full rounded animate-pulse"></div>
                        <div class="h-10 bg-slate-200 w-full rounded animate-pulse"></div>
                      </div>
                    </div>

                    <!-- Error al cargar detalle histórico -->
                    <div
                      v-else-if="submodo === 'historico' && errorDetalleHist[r.ruta]"
                      class="p-4 text-center text-[#C9342C] font-semibold text-xs bg-white rounded-xl border border-rose-200"
                    >
                      Error al cargar detalle histórico de la ruta: {{ errorDetalleHist[r.ruta] }}
                    </div>

                    <!-- Contenido de Secciones (Entregados, Parciales, Caídos) -->
                    <div v-else class="space-y-2.5 max-w-full">
                      <div class="flex items-center justify-between text-[11px] font-bold text-[#6E6E73] uppercase tracking-wider px-1 pb-1 border-b border-black/[0.06]">
                        <span>
                          {{
                            submodo === 'diario'
                              ? `Detalle de Preventas · ${obtenerPedidosRuta(r).length} pedidos`
                              : `Histórico de Pedidos en Rango (${store.filtrosAplicados?.fechaInicio || store.fechaInicio} al ${store.filtrosAplicados?.fechaCorte || store.fechaCorte}) · ${obtenerPedidosRuta(r).length} pedidos`
                          }}
                        </span>
                        <span>{{ submodo === 'diario' ? 'Auditando Despacho' : 'Auditando Ruta' }}</span>
                      </div>

                      <div
                        v-if="obtenerPedidosRuta(r).length === 0"
                        class="text-[11px] text-[#86868B] italic py-3 text-center"
                      >
                        Sin pedidos para esta ruta.
                      </div>

                      <!-- Nivel 2: Secciones por Estatus (Entregados, Parciales, Caídos) -->
                      <template v-else>
                        <div
                          v-for="sec in obtenerSeccionesRuta(r)"
                          :key="sec.id"
                          class="border border-black/[0.06] rounded-xl overflow-hidden bg-white shadow-xs"
                        >
                          <!-- Cabecera de Sección -->
                          <div
                            @click="toggleSeccion(sec.id, sec.abiertoPorDefecto)"
                            class="px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-3 cursor-pointer select-none hover:bg-slate-50 transition-colors"
                          >
                            <div class="flex items-center gap-2">
                              <svg
                                class="w-3 h-3 text-[#86868B] transition-transform duration-200"
                                :class="{ 'rotate-90': esSeccionAbierta(sec.id, sec.abiertoPorDefecto) }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                              </svg>
                              <span class="w-2 h-2 rounded-full" :class="sec.color"></span>
                              <span class="font-bold text-[11px] uppercase tracking-wider text-[#1D1D1F]">
                                {{ sec.titulo }}
                              </span>
                              <span class="text-[10px] font-mono text-[#6E6E73] bg-slate-100 border border-black/[0.06] px-1.5 py-0.5 rounded">
                                {{ sec.pedidos.length }}
                              </span>
                            </div>
                            <div class="text-[10px] font-mono text-[#6E6E73]">
                              Prev <strong class="text-[#1D1D1F]">Bs {{ fmtBs(sec.totalPrev) }}</strong>
                              · Fact <strong class="text-[#1D1D1F]">Bs {{ fmtBs(sec.totalFact) }}</strong>
                            </div>
                          </div>

                          <!-- Cuerpo de Sección: Lista de Cards de Pedidos (Nivel 3) -->
                          <div
                            v-if="esSeccionAbierta(sec.id, sec.abiertoPorDefecto)"
                            class="p-2 space-y-1.5 bg-slate-50/70 border-t border-black/[0.06]"
                          >
                            <div
                              v-for="(p, pIdx) in sec.pedidos"
                              :key="`${sec.id}-p-${pIdx}`"
                              class="border rounded-xl overflow-hidden shadow-xs"
                              :class="p.es_alerta_critica ? 'bg-rose-50/40 border-rose-200' : 'bg-white border-black/[0.06]'"
                            >
                              <!-- Cabecera del Pedido -->
                              <div
                                @click="togglePedido(`${sec.id}-p-${pIdx}`)"
                                class="px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-2 cursor-pointer select-none transition-colors hover:bg-black/[0.01]"
                              >
                                <div class="flex flex-wrap items-center gap-2 min-w-0">
                                  <svg
                                    class="w-3 h-3 text-[#86868B] transition-transform duration-200 inline-block shrink-0"
                                    :class="{ 'rotate-90': pedidosAbiertos[`${sec.id}-p-${pIdx}`] }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                  >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                  </svg>
                                  <span
                                    v-if="p.es_alerta_critica"
                                    class="px-1.5 py-0.5 rounded-[4px] bg-[#C9342C] text-white text-[9px] font-bold tracking-tight shrink-0"
                                  >
                                    ALERTA D-1
                                  </span>
                                  <span class="font-bold text-[#1D1D1F] text-[12px] truncate">{{ p.cliente }}</span>
                                  <span class="text-[10px] text-[#6E6E73] font-mono tabular-nums shrink-0 bg-slate-100 border border-black/[0.06] px-1.5 py-0.5 rounded">
                                    ID: {{ p.cliente_id ?? '—' }}
                                  </span>
                                  <span class="text-[10px] text-[#6E6E73] font-mono tabular-nums shrink-0">
                                    Prev #{{ p.nro_preventa }}
                                  </span>
                                  <span
                                    v-if="p.nro_factura"
                                    class="text-[10px] text-[#248A3D] font-mono shrink-0 font-bold"
                                  >
                                    Fact #{{ p.nro_factura }}
                                  </span>

                                  <!-- Badge de Justificativo desde app -->
                                  <template v-if="p.justificativo">
                                    <span
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#FFF5E5] text-[#B25E00] border border-[#FFE0B2] shrink-0"
                                    >
                                      Motivo: {{ formatearMotivo(p.justificativo.motivo) }}
                                    </span>
                                    <span
                                      v-if="p.justificativo.comentarios"
                                      class="text-[10px] italic text-[#1D1D1F] bg-slate-100 px-2 py-0.5 rounded border border-black/[0.06] max-w-[260px] truncate"
                                      :title="p.justificativo.comentarios"
                                    >
                                      "{{ p.justificativo.comentarios }}"
                                    </span>
                                    <button
                                      v-if="p.justificativo.photo_url"
                                      type="button"
                                      @click.stop="abrirModalEvidencia(p)"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#0071E3]/10 text-[#0071E3] hover:bg-[#0071E3] hover:text-white border border-[#0071E3]/25 transition-colors cursor-pointer shrink-0"
                                    >
                                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                      </svg>
                                      <span>Ver Foto</span>
                                    </button>
                                  </template>
                                  <span
                                    v-else-if="p.estatus_pedido !== 'ENTREGADO_TOTAL'"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium text-[#86868B] bg-slate-100 border border-black/[0.06] shrink-0"
                                  >
                                    Sin justificativo en app
                                  </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-[10px] sm:text-[11px]">
                                  <span class="text-[#6E6E73]">
                                    Fecha: <strong class="font-mono text-[#1D1D1F]">{{ p.fecha_preventa }}</strong>
                                  </span>
                                  <span class="text-slate-300">|</span>
                                  <span class="text-[#6E6E73]">
                                    Uds: <strong class="font-mono text-[#1D1D1F]">{{ p.total_unidades_fact }}/{{ p.total_unidades_prev }}</strong>
                                  </span>
                                  <span class="text-slate-300">|</span>
                                  <span class="text-[#6E6E73]">
                                    Monto: <strong class="font-mono text-[#1D1D1F]">Bs {{ fmtBs(p.total_bs_fact) }}</strong>
                                    (Prev Bs {{ fmtBs(p.total_bs_prev) }})
                                  </span>
                                  <span class="text-slate-300">|</span>
                                  <span
                                    class="px-2 py-0.5 rounded-[4px] font-bold font-mono text-[10px] border"
                                    :class="claseBadgePedido(p.estatus_pedido)"
                                  >
                                    {{ textoBadgePedido(p.estatus_pedido) }}
                                  </span>
                                </div>
                              </div>

                              <!-- Detalle Expandido del Pedido: Justificativo + Tabla de SKUs -->
                              <div
                                v-if="pedidosAbiertos[`${sec.id}-p-${pIdx}`]"
                                class="p-3 border-t border-black/[0.06] bg-white space-y-2.5"
                              >
                                <!-- Banner de Justificativo de Rechazo -->
                                <div
                                  v-if="p.justificativo"
                                  class="p-2.5 rounded-xl bg-[#FFF9F0] border border-[#FFE0B2] flex flex-wrap items-center justify-between gap-3 text-[11px]"
                                >
                                  <div class="space-y-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                      <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-[#B25E00] text-white tracking-wider">
                                        Justificativo Registrado ({{ p.justificativo.tipo_rechazo }})
                                      </span>
                                      <span class="font-bold text-[#1D1D1F]">
                                        Motivo: {{ formatearMotivo(p.justificativo.motivo) }}
                                      </span>
                                      <span v-if="p.justificativo.fecha_rechazo" class="text-[10px] font-mono text-[#6E6E73]">
                                        · Reportado: {{ p.justificativo.fecha_rechazo }}
                                      </span>
                                      <span v-if="p.justificativo.monto_total_rechazado > 0" class="text-[10px] font-mono font-semibold text-[#C9342C]">
                                        · Rechazo reportado: Bs {{ fmtBs(p.justificativo.monto_total_rechazado) }}
                                      </span>
                                    </div>
                                    <div class="text-[#1D1D1F] font-medium">
                                      <span class="text-[#6E6E73] font-semibold">Observación / Comentario: </span>
                                      <span class="italic">{{ p.justificativo.comentarios || 'Sin comentarios adicionales registrados.' }}</span>
                                    </div>
                                  </div>

                                  <div class="flex items-center gap-2 shrink-0">
                                    <button
                                      v-if="p.justificativo.photo_url"
                                      type="button"
                                      @click.stop="abrirModalEvidencia(p)"
                                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#0071E3] hover:bg-[#0077ED] text-white text-[10px] font-bold shadow-xs transition-colors cursor-pointer"
                                    >
                                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                      </svg>
                                      <span>Ver Evidencia Fotográfica</span>
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
                                      <span>Mapa GPS</span>
                                    </a>
                                  </div>
                                </div>

                                <!-- Tabla de SKUs del Pedido -->
                                <div class="overflow-x-auto max-h-60 custom-scroll">
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
                            </div>
                          </div>
                        </div>
                      </template>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL DE EVIDENCIA FOTOGRÁFICA Y JUSTIFICATIVO -->
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
import { ref, reactive, computed, onMounted } from 'vue';
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

// Estado local
const submodo = ref('diario'); // 'diario' | 'historico'
const cargando = ref(false);
const errorEntregas = ref(null);
const datosEntregas = ref(null);
const datosEntregasHistorico = ref(null);

const orden = reactive({
  campo: 'efectividad_pct',
  dir: 'asc',
});

// Estado de acordeones (Ruta -> Secciones -> Pedidos)
const rutasAbiertas = reactive({});
const seccionesAbiertas = reactive({});
const pedidosAbiertos = reactive({});

// Modal de evidencia fotográfica
const pedidoEvidenciaModal = ref(null);

// Caché de detalle de pedidos por ruta para el modo Histórico
const detallePedidosHistorico = reactive({});
const cargandoDetalleHist = reactive({});
const errorDetalleHist = reactive({});

// Referencia D-1
const fechaD1Referencia = computed(() => {
  return datosEntregas.value?.fecha_d1_referencia || '-';
});

function cumpleFiltroRuta(r) {
  if (!r) return false;

  const canalesSel = store.filtrosAplicados?.canalesSeleccionados || [];
  if (canalesSel.length > 0) {
    const canalNorm = String(r.canal || '').trim().toUpperCase();
    const selSet = new Set(canalesSel.map(c => String(c).trim().toUpperCase()));
    if (!selSet.has(canalNorm)) return false;
  }

  const rutasSel = store.filtrosAplicados?.rutasSeleccionadas || [];
  if (rutasSel.length > 0) {
    const rutaNorm = String(r.ruta || '').trim().toUpperCase();
    const selSet = new Set(rutasSel.map(rt => String(rt).trim().toUpperCase()));
    if (!selSet.has(rutaNorm)) return false;
  }

  const busq = String(store.filtrosAplicados?.busqueda || '').toLowerCase().trim();
  if (busq) {
    const enCabecera =
      String(r.ruta || '').toLowerCase().includes(busq) ||
      String(r.vendedor || '').toLowerCase().includes(busq) ||
      String(r.canal || '').toLowerCase().includes(busq);

    if (enCabecera) return true;

    if (submodo.value === 'diario' && Array.isArray(r.pedidos)) {
      return r.pedidos.some(p =>
        String(p.cliente || '').toLowerCase().includes(busq) ||
        String(p.cliente_id || '').toLowerCase().includes(busq) ||
        String(p.nro_preventa || '').toLowerCase().includes(busq) ||
        String(p.nro_factura || '').toLowerCase().includes(busq) ||
        String(p.justificativo?.motivo || '').toLowerCase().includes(busq)
      );
    }

    return false;
  }

  return true;
}

const rutasOrdenadas = computed(() => {
  const fuente = submodo.value === 'diario'
    ? datosEntregas.value?.rutas || []
    : datosEntregasHistorico.value?.rutas || [];

  const filtradas = fuente.filter(cumpleFiltroRuta);

  return [...filtradas].sort((a, b) => {
    let va = a[orden.campo];
    let vb = b[orden.campo];
    const mult = orden.dir === 'asc' ? 1 : -1;

    if (typeof va === 'string' || typeof vb === 'string') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
});

function ordenarPor(campo) {
  if (orden.campo === campo) {
    orden.dir = orden.dir === 'asc' ? 'desc' : 'asc';
  } else {
    orden.campo = campo;
    orden.dir = 'desc';
  }
}

function iconoSort(campo) {
  if (orden.campo !== campo) return '';
  return orden.dir === 'asc' ? '▲' : '▼';
}

function colorSort(campo) {
  return orden.campo === campo ? 'text-[#1D1D1F]' : 'text-[#86868B]';
}

function keyRuta(ruta) {
  return `${submodo.value}:${ruta}`;
}

async function toggleRuta(r) {
  const k = keyRuta(r.ruta);
  const nuevoEstado = !rutasAbiertas[k];
  rutasAbiertas[k] = nuevoEstado;

  if (nuevoEstado && submodo.value === 'historico' && !detallePedidosHistorico[r.ruta]) {
    cargandoDetalleHist[r.ruta] = true;
    errorDetalleHist[r.ruta] = null;
    try {
      const res = await axios.post('/supervisor/avance/entregas/historico/detalle-ruta', {
        ruta: r.ruta,
        fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
        fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      });
      detallePedidosHistorico[r.ruta] = res.data?.pedidos || [];
    } catch (err) {
      errorDetalleHist[r.ruta] = err?.response?.data?.message || err?.message || 'Error al cargar pedidos';
    } finally {
      cargandoDetalleHist[r.ruta] = false;
    }
  }
}

function obtenerPedidosRuta(r) {
  if (submodo.value === 'diario') {
    return r.pedidos || [];
  }
  return detallePedidosHistorico[r.ruta] || [];
}

function obtenerSeccionesRuta(r) {
  const pedidos = obtenerPedidosRuta(r);
  const sufijo = submodo.value === 'historico' ? ' (Rango)' : '';
  const baseId = `${submodo.value}-${r.ruta}`;
  const parciales = pedidos.filter(p => p.estatus_pedido === 'PARCIAL');

  const grupos = [
    {
      id: `${baseId}-ent`,
      titulo: `Entregados${sufijo}`,
      color: 'bg-[#1E6B37]',
      pedidos: pedidos.filter(p => p.estatus_pedido === 'ENTREGADO_TOTAL'),
      abiertoPorDefecto: false,
    },
    {
      id: `${baseId}-par`,
      titulo: `Parciales${sufijo}`,
      color: 'bg-[#B25E00]',
      pedidos: parciales,
      abiertoPorDefecto: true,
    },
    {
      id: `${baseId}-cai`,
      titulo: `Caídos${sufijo}`,
      color: 'bg-[#C9342C]',
      pedidos: pedidos.filter(p => p.estatus_pedido === 'CAIDA_TOTAL'),
      abiertoPorDefecto: true,
    },
  ];

  return grupos
    .filter(g => g.pedidos.length > 0)
    .map(g => ({
      ...g,
      totalPrev: g.pedidos.reduce((acc, p) => acc + Number(p.total_bs_prev || 0), 0),
      totalFact: g.pedidos.reduce((acc, p) => acc + Number(p.total_bs_fact || 0), 0),
    }));
}

function esSeccionAbierta(id, abiertoPorDefecto) {
  if (seccionesAbiertas[id] === undefined) {
    return abiertoPorDefecto;
  }
  return seccionesAbiertas[id];
}

function toggleSeccion(id, abiertoPorDefecto) {
  const actual = esSeccionAbierta(id, abiertoPorDefecto);
  seccionesAbiertas[id] = !actual;
}

function togglePedido(id) {
  pedidosAbiertos[id] = !pedidosAbiertos[id];
}

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
  if (submodo.value === 'historico') {
    if (efec >= 95) return 'text-[#1E6B37] bg-emerald-50 border-emerald-200 font-bold';
    if (efec >= 85) return 'text-[#B25E00] bg-amber-50 border-amber-200 font-bold';
    return 'text-[#C9342C] bg-rose-50 border-rose-200 font-bold';
  }
  if (efec >= 95.0) return 'text-[#1E6B37] bg-emerald-50 border-emerald-200 font-bold';
  if (efec < 80.0) return 'text-[#C9342C] bg-rose-50 border-rose-200 font-bold';
  return 'text-[#1D1D1F] bg-slate-100 border-black/[0.08]';
}

function claseBadgePedido(estatus) {
  if (estatus === 'ENTREGADO_TOTAL') return 'text-[#1E6B37] bg-emerald-50 border-emerald-200';
  if (estatus === 'PARCIAL') return 'text-[#B25E00] bg-amber-50 border-amber-200';
  return 'text-[#C9342C] bg-rose-50 border-rose-200';
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
    } else {
      const res = await axios.post('/supervisor/avance/entregas/tablero', {
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

