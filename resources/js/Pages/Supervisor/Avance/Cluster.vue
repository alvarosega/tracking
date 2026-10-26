<template>
  <SupervisorLayout title="Avance > Cluster vs LY">
    <div class="space-y-2.5 font-sans select-none">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── BARRA SUPERIOR DE FILTROS Y CONFIGURACIÓN (Apple Glass) ── -->
      <section
        class="apple-glass-card rounded-[14px] p-2.5 sm:p-3 border border-black/[0.06] shadow-xs relative transition-all"
        :class="dropdownAbierto ? 'z-[10000]' : 'z-30'"
      >
        <div class="flex flex-wrap items-center justify-between gap-2.5">
          <!-- Controles Izquierda: Estructura, Métrica, Fechas y Filtros -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Estructura de Desglose Jerárquico -->
            <div class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[10px]">
              <button
                type="button"
                @click="cambiarAgrupacion('cluster_jerarquia')"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="agrupacion === 'cluster_jerarquia' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Cluster ➔ Jerarquía 1 ➔ Jerarquía 2 ➔ Jerarquía 3"
              >
                Cluster ➔ Jerarquías
              </button>
              <button
                type="button"
                @click="cambiarAgrupacion('cluster_ruta_jerarquia')"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="agrupacion === 'cluster_ruta_jerarquia' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Cluster ➔ Ruta ➔ Jerarquía 1 ➔ Jerarquía 2 ➔ Jerarquía 3"
              >
                Cluster ➔ Ruta ➔ Jerarquías
              </button>
              <button
                type="button"
                @click="cambiarAgrupacion('ruta_jerarquia')"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="agrupacion === 'ruta_jerarquia' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Ruta ➔ Jerarquía 1 ➔ Jerarquía 2 ➔ Jerarquía 3"
              >
                Ruta ➔ Jerarquías
              </button>
              <button
                type="button"
                @click="cambiarAgrupacion('cluster')"
                class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="agrupacion === 'cluster' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Solo Clusters (Nivel consolidado)"
              >
                Solo Cluster
              </button>
            </div>

            <!-- Selector de Métrica (Monto, Cajas, Unidades) -->
            <div class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[10px]">
              <button
                type="button"
                @click="cambiarMetrica('monto')"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="metrica === 'monto' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Monto (Bs)
              </button>
              <button
                type="button"
                @click="cambiarMetrica('cajas')"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="metrica === 'cajas' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Cajas (Cj)
              </button>
              <button
                type="button"
                @click="cambiarMetrica('unidades')"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="metrica === 'unidades' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              >
                Unidades (Und)
              </button>
            </div>

            <!-- Selector de Fechas (Desde - Corte) -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Desde:</span>
              <input
                v-model="fechaInicio"
                type="date"
                :min="catalogo.min_fecha"
                :max="fechaCorte || catalogo.max_fecha"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono text-[10px]"
              />
            </div>

            <div class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Corte:</span>
              <input
                v-model="fechaCorte"
                type="date"
                :min="fechaInicio || catalogo.min_fecha"
                :max="catalogo.max_fecha"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono text-[10px]"
              />
            </div>

            <!-- Presets Rápidos de Fechas -->
            <div class="hidden 2xl:inline-flex items-center gap-1 bg-black/[0.03] p-0.5 rounded-[8px] text-[10px]">
              <button
                type="button"
                @click="aplicarPreset('YTD')"
                class="px-2 py-0.5 rounded-[6px] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white font-medium cursor-pointer transition-all"
              >
                Año Actual
              </button>
              <button
                type="button"
                @click="aplicarPreset('6M')"
                class="px-2 py-0.5 rounded-[6px] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white font-medium cursor-pointer transition-all"
              >
                Últimos 6M
              </button>
              <button
                type="button"
                @click="aplicarPreset('3M')"
                class="px-2 py-0.5 rounded-[6px] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white font-medium cursor-pointer transition-all"
              >
                Últimos 3M
              </button>
              <button
                type="button"
                @click="aplicarPreset('MTD')"
                class="px-2 py-0.5 rounded-[6px] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white font-medium cursor-pointer transition-all"
              >
                Mes en curso
              </button>
            </div>

            <!-- Dropdown Multiselección Canal -->
            <div
              class="relative inline-block text-left"
              :class="dropdownAbierto === 'canales' ? 'z-[10001]' : 'z-10'"
            >
              <button
                type="button"
                @click="toggleDropdown('canales')"
                class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px] font-bold text-[#1D1D1F] cursor-pointer"
              >
                <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
                <span class="max-w-[75px] truncate">{{ etiquetaCanales }}</span>
                <svg
                  class="w-3 h-3 text-[#86868B] transition-transform duration-200"
                  :class="dropdownAbierto === 'canales' ? 'rotate-180 text-[#1D1D1F]' : ''"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Popover Canales -->
              <div
                v-if="dropdownAbierto === 'canales'"
                class="absolute top-full left-0 mt-1.5 w-52 bg-white/95 backdrop-blur-2xl rounded-[12px] border border-black/[0.08] shadow-2xl z-[10002] p-2 space-y-1.5"
              >
                <div class="flex items-center justify-between px-1.5 pb-1 border-b border-black/[0.06] text-[10px]">
                  <button type="button" @click="marcarTodosCanales(true)" class="text-[#0071E3] font-bold hover:underline cursor-pointer">Todos</button>
                  <button type="button" @click="marcarTodosCanales(false)" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Limpiar</button>
                </div>
                <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5 pt-0.5">
                  <label
                    v-for="c in listaCanales"
                    :key="c"
                    class="flex items-center gap-2 px-2 py-1 rounded-[6px] hover:bg-black/[0.04] cursor-pointer text-[11px] group transition-colors"
                  >
                    <input
                      type="checkbox"
                      :checked="canalesSeleccionados.includes(c)"
                      @change="toggleCanal(c)"
                      class="rounded text-[#0071E3] focus:ring-0 cursor-pointer shrink-0"
                    />
                    <span class="text-[#1D1D1F] font-medium truncate flex-1">{{ c }}</span>
                  </label>
                </div>
                <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[10px] text-[#86868B] px-1">
                  <span>{{ canalesSeleccionados.length }} seleccionados</span>
                  <button
                    type="button"
                    @click="cerrarDropdownYConsultar"
                    class="font-bold text-[#0071E3] hover:underline cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>

            <!-- Dropdown Multiselección Rutas -->
            <div
              class="relative inline-block text-left"
              :class="dropdownAbierto === 'rutas' ? 'z-[10001]' : 'z-10'"
            >
              <button
                type="button"
                @click="toggleDropdown('rutas')"
                class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px] font-bold text-[#1D1D1F] cursor-pointer"
              >
                <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Rutas:</span>
                <span class="max-w-[75px] truncate">{{ etiquetaRutas }}</span>
                <svg
                  class="w-3 h-3 text-[#86868B] transition-transform duration-200"
                  :class="dropdownAbierto === 'rutas' ? 'rotate-180 text-[#1D1D1F]' : ''"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Popover Rutas -->
              <div
                v-if="dropdownAbierto === 'rutas'"
                class="absolute top-full left-0 mt-1.5 w-60 bg-white/95 backdrop-blur-2xl rounded-[12px] border border-black/[0.08] shadow-2xl z-[10002] p-2 space-y-1.5"
              >
                <div class="flex items-center justify-between px-1.5 pb-1 border-b border-black/[0.06] text-[10px]">
                  <button type="button" @click="marcarTodasRutas(true)" class="text-[#0071E3] font-bold hover:underline cursor-pointer">Todas</button>
                  <button type="button" @click="marcarTodasRutas(false)" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Limpiar</button>
                </div>
                <input
                  v-model="filtroRutasTexto"
                  type="text"
                  placeholder="Buscar ruta..."
                  class="w-full px-2 py-1 text-[11px] bg-black/[0.03] border border-black/[0.06] rounded-[6px] focus:outline-none focus:border-black font-medium"
                />
                <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5">
                  <label
                    v-for="r in rutasFiltradasDropdown"
                    :key="r"
                    class="flex items-center gap-2 px-2 py-1 rounded-[6px] hover:bg-black/[0.04] cursor-pointer text-[11px] group transition-colors"
                  >
                    <input
                      type="checkbox"
                      :checked="rutasSeleccionadas.includes(r)"
                      @change="toggleRuta(r)"
                      class="rounded text-[#0071E3] focus:ring-0 cursor-pointer shrink-0"
                    />
                    <span class="text-[#1D1D1F] font-medium truncate flex-1">{{ r }}</span>
                  </label>
                </div>
                <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[10px] text-[#86868B] px-1">
                  <span>{{ rutasSeleccionadas.length }} seleccionadas</span>
                  <button
                    type="button"
                    @click="cerrarDropdownYConsultar"
                    class="font-bold text-[#0071E3] hover:underline cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>

            <!-- Dropdown Multiselección Cluster -->
            <div
              class="relative inline-block text-left"
              :class="dropdownAbierto === 'clusters' ? 'z-[10001]' : 'z-10'"
            >
              <button
                type="button"
                @click="toggleDropdown('clusters')"
                class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px] font-bold text-[#1D1D1F] cursor-pointer"
              >
                <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Cluster:</span>
                <span class="max-w-[75px] truncate">{{ etiquetaClusters }}</span>
                <svg
                  class="w-3 h-3 text-[#86868B] transition-transform duration-200"
                  :class="dropdownAbierto === 'clusters' ? 'rotate-180 text-[#1D1D1F]' : ''"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Popover Clusters -->
              <div
                v-if="dropdownAbierto === 'clusters'"
                class="absolute top-full left-0 mt-1.5 w-56 bg-white/95 backdrop-blur-2xl rounded-[12px] border border-black/[0.08] shadow-2xl z-[10002] p-2 space-y-1.5"
              >
                <div class="flex items-center justify-between px-1.5 pb-1 border-b border-black/[0.06] text-[10px]">
                  <button type="button" @click="marcarTodosClusters(true)" class="text-[#0071E3] font-bold hover:underline cursor-pointer">Todos</button>
                  <button type="button" @click="marcarTodosClusters(false)" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Limpiar</button>
                </div>
                <input
                  v-model="filtroClustersTexto"
                  type="text"
                  placeholder="Buscar cluster..."
                  class="w-full px-2 py-1 text-[11px] bg-black/[0.03] border border-black/[0.06] rounded-[6px] focus:outline-none focus:border-black font-medium"
                />
                <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5">
                  <label
                    v-for="c in clustersFiltradosDropdown"
                    :key="c"
                    class="flex items-center gap-2 px-2 py-1 rounded-[6px] hover:bg-black/[0.04] cursor-pointer text-[11px] group transition-colors"
                  >
                    <input
                      type="checkbox"
                      :checked="clustersSeleccionados.includes(c)"
                      @change="toggleCluster(c)"
                      class="rounded text-[#0071E3] focus:ring-0 cursor-pointer shrink-0"
                    />
                    <span class="text-[#1D1D1F] font-medium truncate flex-1">{{ c }}</span>
                  </label>
                </div>
                <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[10px] text-[#86868B] px-1">
                  <span>{{ clustersSeleccionados.length }} seleccionados</span>
                  <button
                    type="button"
                    @click="cerrarDropdownYConsultar"
                    class="font-bold text-[#0071E3] hover:underline cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Controles Derecha: Vista (Gráficos / Matriz), Modo Tabla, Exportar y Consultar -->
          <div class="flex items-center gap-2 ml-auto">
            <!-- Selector de Modo de Visualización -->
            <div class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[10px]">
              <button
                type="button"
                @click="vistaModo = 'ambos'"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="vistaModo === 'ambos' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Mostrar Gráficos y Matriz"
              >
                Matriz y Gráfico
              </button>
              <button
                type="button"
                @click="vistaModo = 'grafico'"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="vistaModo === 'grafico' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Mostrar únicamente Gráficos de Tendencia"
              >
                Solo Gráfico
              </button>
              <button
                type="button"
                @click="vistaModo = 'matriz'"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="vistaModo === 'matriz' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Mostrar únicamente Matriz Tabular"
              >
                Solo Matriz
              </button>
            </div>

            <!-- Toggle Detallada vs Compacta -->
            <div v-if="vistaModo !== 'grafico'" class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[10px]">
              <button
                type="button"
                @click="modoVistaTabla = 'detallada'"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="modoVistaTabla === 'detallada' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Muestra TY, LY, Dif y % Crec por mes"
              >
                Detallada
              </button>
              <button
                type="button"
                @click="modoVistaTabla = 'compacta'"
                class="px-2 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                :class="modoVistaTabla === 'compacta' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                title="Muestra TY y % Crec por mes"
              >
                Compacta
              </button>
            </div>

            <!-- Botón Exportar CSV -->
            <button
              type="button"
              @click="exportarCsv"
              :disabled="cargando || filasOriginales.length === 0"
              class="apple-control-pill px-2.5 py-1 rounded-[8px] text-[11px] font-bold text-[#1D1D1F] hover:bg-black/[0.04] transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-40"
              title="Descargar matriz en formato CSV para Excel"
            >
              <svg class="w-3.5 h-3.5 text-[#34C759]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">Exportar</span>
            </button>

            <!-- Botón Consultar -->
            <button
              type="button"
              @click="ejecutarConsulta"
              :disabled="cargando"
              class="bg-[#1D1D1F] hover:bg-black text-white font-bold text-[11px] px-3.5 py-1.5 rounded-[9px] cursor-pointer flex items-center gap-1.5 transition-all shadow-xs disabled:opacity-50"
            >
              <svg
                class="w-3.5 h-3.5"
                :class="cargando ? 'animate-spin' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>Consultar</span>
            </button>
          </div>
        </div>
      </section>

      <!-- ── ESTADO DE CARGA ── -->
      <div
        v-if="cargando"
        class="bg-white/80 backdrop-blur-xl rounded-[16px] p-12 text-center border border-black/[0.06] flex flex-col items-center justify-center gap-3 shadow-xs"
      >
        <div class="w-7 h-7 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
        <p class="text-xs font-bold text-[#1D1D1F]">Calculando matriz comparativa por cluster y jerarquías...</p>
        <p class="text-[10px] text-[#86868B] font-mono">Consolidando ventas TY vs LY con período corte</p>
      </div>

      <!-- ── MENSAJE DE ERROR ── -->
      <div
        v-else-if="errorMensaje"
        class="bg-red-50/80 backdrop-blur-xl border border-red-200 text-red-700 p-4 rounded-[14px] text-xs flex items-center justify-between"
      >
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ errorMensaje }}</span>
        </div>
        <button
          @click="ejecutarConsulta"
          class="px-2.5 py-1 bg-red-600 text-white font-bold rounded-[6px] text-[10px] hover:bg-red-700 cursor-pointer"
        >
          Reintentar
        </button>
      </div>

      <template v-else>
        <!-- ── SECCIÓN DE GRÁFICOS DE TENDENCIA (TY VS LY / MULTILÍNEA) ── -->
        <section
          v-if="vistaModo !== 'matriz' && meses.length > 0"
          class="apple-glass-card rounded-[16px] p-3.5 sm:p-4 border border-black/[0.06] shadow-xs relative z-20"
        >
          <!-- Encabezado del Gráfico -->
          <div class="flex flex-wrap items-center justify-between gap-2.5 pb-2.5 mb-2.5 border-b border-black/[0.06]">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3]"></span>
              <div>
                <h3 class="text-xs font-bold text-[#1D1D1F] tracking-tight uppercase flex items-center gap-2">
                  <span>Tendencia Mensual vs LY</span>
                  <span class="text-[9px] font-mono text-[#0071E3] font-semibold bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                    {{ unidad }}
                  </span>
                </h3>
                <p class="text-[10px] text-[#86868B]">
                  {{ modoGrafico === 'serie_unica' ? ('Evolución TY vs LY: ' + (nodoActivoGrafico?.label || 'Consolidado General')) : ('Comparativo Multilínea por ' + etiquetaTituloAgrupacion) }}
                </p>
              </div>
            </div>

            <!-- Controles del Gráfico -->
            <div class="flex flex-wrap items-center gap-2">
              <div class="inline-flex apple-segmented p-0.5 rounded-[8px] text-[10px]">
                <button
                  type="button"
                  @click="modoGrafico = 'serie_unica'"
                  class="px-2 py-0.5 rounded-[6px] transition-all cursor-pointer font-bold"
                  :class="modoGrafico === 'serie_unica' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73]'"
                >
                  TY vs LY
                </button>
                <button
                  type="button"
                  @click="modoGrafico = 'multilinea'"
                  class="px-2 py-0.5 rounded-[6px] transition-all cursor-pointer font-bold"
                  :class="modoGrafico === 'multilinea' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73]'"
                  title="Comparar líneas de todos los elementos principales"
                >
                  Multilínea
                </button>
              </div>

              <!-- Selector de Elemento Específico (Serie Única) -->
              <div v-if="modoGrafico === 'serie_unica'" class="flex items-center gap-1 bg-black/[0.03] rounded-[8px] px-2 py-0.5 border border-black/[0.05]">
                <span class="text-[9px] font-bold text-[#86868B] uppercase">Ver:</span>
                <select
                  v-model="nodoSeleccionadoId"
                  class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer max-w-[220px] truncate"
                >
                  <option value="__TOTAL__">Consolidado Total General</option>
                  <optgroup :label="'Elementos de ' + etiquetaTituloAgrupacion">
                    <option v-for="op in opcionesNodosGrafico" :key="op.id" :value="op.id">
                      {{ op.labelIndent }} ({{ op.tipo.toUpperCase() }})
                    </option>
                  </optgroup>
                </select>
              </div>

              <!-- Leyendas -->
              <div class="flex items-center gap-3 text-[10px] font-semibold pl-2 border-l border-black/[0.06]">
                <template v-if="modoGrafico === 'serie_unica'">
                  <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-1 rounded-full bg-[#0071E3]"></span>
                    <span class="text-[#1D1D1F]">TY (Actual)</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-3.5 h-0.5 border-t-2 border-dashed border-[#86868B]"></span>
                    <span class="text-[#6E6E73]">LY (Comparable)</span>
                  </div>
                </template>
                <template v-else>
                  <span class="text-[9px] text-[#86868B] uppercase tracking-wider">Líneas por Elemento</span>
                </template>
              </div>
            </div>
          </div>

          <!-- Tarjetas Resumen de la Serie Activa -->
          <div v-if="modoGrafico === 'serie_unica' && nodoActivoGrafico" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2 text-[11px]">
            <div class="bg-black/[0.02] p-2 rounded-[10px] border border-black/[0.04]">
              <span class="text-[9px] text-[#86868B] uppercase font-bold block">Total TY:</span>
              <span class="text-[13px] font-bold text-[#1D1D1F] font-mono">{{ unidad }} {{ formatNum(nodoActivoGrafico.total_ty) }}</span>
            </div>
            <div class="bg-black/[0.02] p-2 rounded-[10px] border border-black/[0.04]">
              <span class="text-[9px] text-[#86868B] uppercase font-bold block">Total LY:</span>
              <span class="text-[13px] font-bold text-[#6E6E73] font-mono">{{ unidad }} {{ formatNum(nodoActivoGrafico.total_ly) }}</span>
            </div>
            <div class="bg-black/[0.02] p-2 rounded-[10px] border border-black/[0.04]">
              <span class="text-[9px] text-[#86868B] uppercase font-bold block">Crecimiento Global:</span>
              <span
                class="text-[13px] font-bold font-mono"
                :class="nodoActivoGrafico.crec_total_pct >= 0 ? 'text-emerald-600' : 'text-rose-600'"
              >
                {{ nodoActivoGrafico.crec_total_pct >= 0 ? '+' : '' }}{{ nodoActivoGrafico.crec_total_pct.toFixed(1) }}%
                <span class="text-[10px] text-[#86868B] font-normal">({{ nodoActivoGrafico.dif_total >= 0 ? '+' : '' }}{{ unidad }} {{ formatNum(nodoActivoGrafico.dif_total) }})</span>
              </span>
            </div>
            <div class="bg-black/[0.02] p-2 rounded-[10px] border border-black/[0.04]">
              <span class="text-[9px] text-[#86868B] uppercase font-bold block">Participación / Share:</span>
              <span class="text-[13px] font-bold text-[#0071E3] font-mono">{{ (nodoActivoGrafico.share_pct ?? 100).toFixed(1) }}%</span>
            </div>
          </div>

          <!-- Lienzo SVG del Gráfico de Líneas -->
          <div
            class="relative w-full h-[240px] select-none"
            ref="svgContainerRef"
            @mousemove="onSvgMouseMove"
            @mouseleave="hoverMesIdx = null"
          >
            <svg viewBox="0 0 1000 240" class="w-full h-full overflow-visible" preserveAspectRatio="none">
              <defs>
                <linearGradient id="gradClusterTY" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#0071E3" stop-opacity="0.22" />
                  <stop offset="100%" stop-color="#0071E3" stop-opacity="0.0" />
                </linearGradient>
              </defs>

              <!-- Líneas de Cuadrícula Horizontal y Ticks Y -->
              <g class="grid-y">
                <template v-for="tick in (modoGrafico === 'serie_unica' ? puntosSerieUnica.yTicks : lineasMultilinea.yTicks)" :key="tick.label">
                  <line
                    :x1="plotArea.x0"
                    :y1="tick.y"
                    :x2="plotArea.x0 + plotArea.w"
                    :y2="tick.y"
                    stroke="rgba(0,0,0,0.06)"
                    stroke-dasharray="3 3"
                    stroke-width="1"
                  />
                  <text
                    :x="plotArea.x0 - 10"
                    :y="tick.y + 3.5"
                    text-anchor="end"
                    class="font-mono text-[9px] font-bold fill-[#86868B]"
                  >
                    {{ tick.label }}
                  </text>
                </template>
              </g>

              <!-- Eje X (Meses) -->
              <g class="axis-x">
                <template v-for="(m, idx) in meses" :key="m.key">
                  <text
                    :x="plotArea.x0 + (meses.length > 1 ? idx * (plotArea.w / (meses.length - 1)) : 0)"
                    :y="plotArea.yBottom + 20"
                    text-anchor="middle"
                    class="font-mono text-[10px] font-bold"
                    :class="hoverMesIdx === idx ? 'fill-[#0071E3]' : (m.es_cerrado ? 'fill-[#1D1D1F]' : 'fill-[#86868B]')"
                  >
                    {{ m.nombre }} {{ String(m.anio).slice(2) }}
                  </text>
                </template>
              </g>

              <!-- Trazado Serie Única (TY vs LY) -->
              <template v-if="modoGrafico === 'serie_unica'">
                <path :d="areaTY" fill="url(#gradClusterTY)" />
                <path
                  :d="pathLY"
                  fill="none"
                  stroke="#86868B"
                  stroke-width="2"
                  stroke-dasharray="4 4"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <path
                  :d="pathTY"
                  fill="none"
                  stroke="#0071E3"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <g class="points-ly">
                  <circle
                    v-for="(p, idx) in puntosSerieUnica.lyPts"
                    :key="'ly-' + idx"
                    :cx="p.x"
                    :cy="p.y"
                    :r="hoverMesIdx === idx ? 5 : 3"
                    fill="#ffffff"
                    stroke="#86868B"
                    stroke-width="2"
                  />
                </g>
                <g class="points-ty">
                  <circle
                    v-for="(p, idx) in puntosSerieUnica.tyPts"
                    :key="'ty-' + idx"
                    :cx="p.x"
                    :cy="p.y"
                    :r="hoverMesIdx === idx ? 6 : 3.5"
                    :fill="hoverMesIdx === idx ? '#0071E3' : '#ffffff'"
                    stroke="#0071E3"
                    stroke-width="2"
                  />
                </g>
              </template>

              <!-- Trazado Multilínea -->
              <template v-else>
                <g v-for="linea in lineasMultilinea.lineas" :key="linea.id">
                  <path
                    :d="linea.pathD"
                    fill="none"
                    :stroke="linea.color"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :opacity="hoverLineaId && hoverLineaId !== linea.id ? 0.25 : 1"
                  />
                  <circle
                    v-for="(p, idx) in linea.pts"
                    :key="linea.id + '-' + idx"
                    :cx="p.x"
                    :cy="p.y"
                    :r="hoverMesIdx === idx ? 5 : 3"
                    :fill="linea.color"
                    stroke="#ffffff"
                    stroke-width="1.5"
                  />
                </g>
              </template>

              <!-- Guía Vertical Hover -->
              <line
                v-if="hoverMesIdx !== null && hoverMesIdx >= 0 && hoverMesIdx < meses.length"
                :x1="plotArea.x0 + (meses.length > 1 ? hoverMesIdx * (plotArea.w / (meses.length - 1)) : 0)"
                :y1="plotArea.y0"
                :x2="plotArea.x0 + (meses.length > 1 ? hoverMesIdx * (plotArea.w / (meses.length - 1)) : 0)"
                :y2="plotArea.yBottom"
                stroke="#1D1D1F"
                stroke-width="1"
                stroke-dasharray="2 2"
                opacity="0.35"
              />
            </svg>

            <!-- Tooltip Flotante -->
            <div
              v-if="hoverMesIdx !== null && meses[hoverMesIdx]"
              class="absolute pointer-events-none bg-white/95 backdrop-blur-xl border border-black/[0.08] shadow-xl rounded-[10px] p-2.5 text-[11px] font-mono z-50 transform -translate-x-1/2 min-w-[200px]"
              :style="{
                left: `${(plotArea.x0 + (meses.length > 1 ? hoverMesIdx * (plotArea.w / (meses.length - 1)) : 0)) / svgConfig.width * 100}%`,
                top: '10px'
              }"
            >
              <div class="flex items-center justify-between pb-1 mb-1 border-b border-black/[0.06] text-[10px] font-sans">
                <span class="font-bold text-[#1D1D1F]">
                  {{ meses[hoverMesIdx].nombre }} {{ meses[hoverMesIdx].anio }}
                </span>
                <span
                  class="px-1 py-0.2 rounded text-[8px] font-bold uppercase tracking-wider"
                  :class="meses[hoverMesIdx].es_cerrado ? 'bg-emerald-50 text-emerald-700' : 'bg-black/[0.06] text-[#1D1D1F]'"
                >
                  {{ meses[hoverMesIdx].es_cerrado ? 'Cerrado' : 'Corte Abierto' }}
                </span>
              </div>

              <template v-if="modoGrafico === 'serie_unica'">
                <div class="space-y-0.5 text-[10px]">
                  <div class="flex items-center justify-between">
                    <span class="text-[#0071E3] font-bold">TY (Actual):</span>
                    <span class="font-bold text-[#1D1D1F]">{{ unidad }} {{ formatNum(datosMesHover.ty) }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-[#6E6E73]">LY (Anterior):</span>
                    <span class="text-[#6E6E73]">{{ unidad }} {{ formatNum(datosMesHover.ly) }}</span>
                  </div>
                  <div class="flex items-center justify-between pt-0.5 border-t border-black/[0.04] font-bold">
                    <span>Diferencia:</span>
                    <span :class="datosMesHover.dif >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                      {{ datosMesHover.dif >= 0 ? '+' : '' }}{{ unidad }} {{ formatNum(datosMesHover.dif) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span>% Crecimiento:</span>
                    <span :class="datosMesHover.crec_pct >= 0 ? 'text-emerald-600' : 'text-rose-600'" class="font-bold">
                      {{ datosMesHover.crec_pct >= 0 ? '+' : '' }}{{ datosMesHover.crec_pct.toFixed(1) }}%
                    </span>
                  </div>
                </div>
              </template>

              <template v-else>
                <div class="max-h-36 overflow-y-auto space-y-0.5 text-[9px]">
                  <div
                    v-for="linea in lineasMultilinea.lineas"
                    :key="linea.id"
                    class="flex items-center justify-between gap-2"
                  >
                    <div class="flex items-center gap-1 truncate max-w-[110px]">
                      <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: linea.color }"></span>
                      <span class="truncate font-sans font-medium text-[#1D1D1F]">{{ linea.label }}</span>
                    </div>
                    <span class="font-bold text-[#1D1D1F]">
                      {{ unidad }} {{ formatNum((linea.pts[hoverMesIdx] || {}).val) }}
                    </span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Leyenda Multilínea Inferior -->
          <div
            v-if="modoGrafico === 'multilinea'"
            class="flex flex-wrap items-center justify-center gap-3 pt-3 mt-1 border-t border-black/[0.05] text-[10px]"
          >
            <div
              v-for="linea in lineasMultilinea.lineas"
              :key="linea.id"
              @mouseenter="hoverLineaId = linea.id"
              @mouseleave="hoverLineaId = null"
              class="flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] bg-black/[0.02] hover:bg-black/[0.06] cursor-pointer transition-colors"
            >
              <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: linea.color }"></span>
              <span class="font-bold text-[#1D1D1F]">{{ linea.label }}</span>
              <span class="text-[#86868B] font-mono text-[9px]">({{ unidad }} {{ formatNum(linea.total_ty) }})</span>
            </div>
          </div>
        </section>

        <!-- ── MATRIZ PRINCIPAL CON ÁRBOL MULTINIVEL ── -->
        <section
          v-if="vistaModo !== 'grafico'"
          class="bg-white/90 backdrop-blur-xl rounded-[16px] border border-black/[0.08] shadow-xs overflow-hidden relative z-10"
        >
          <!-- Barra Superior de la Tabla con Niveles y Búsqueda en Vivo -->
          <div class="px-3 sm:px-4 py-2 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2.5 bg-black/[0.01]">
            <div class="flex items-center gap-2">
              <h2 class="text-[11px] font-bold tracking-wider text-[#1D1D1F] uppercase">
                Avance por {{ etiquetaTituloAgrupacion }} vs LY
              </h2>
              <span class="text-[10px] font-semibold text-[#0071E3] bg-blue-50/80 border border-blue-200/60 px-1.5 py-0.2 rounded">
                {{ unidad }}
              </span>
              <span class="text-[10px] text-[#86868B] font-mono hidden md:inline">
                {{ fechaInicio }} al {{ fechaCorte }}
              </span>
            </div>

            <!-- Búsqueda en Vivo en el Árbol + Expansión de Niveles -->
            <div class="flex items-center gap-2 text-[10px]">
              <div class="relative">
                <input
                  v-model="filtroTextoArbol"
                  type="text"
                  placeholder="Filtrar en tabla..."
                  class="pl-6 pr-2 py-0.5 rounded-[6px] bg-black/[0.03] border border-black/[0.06] text-[10px] focus:outline-none focus:border-black font-medium w-36 sm:w-44"
                />
                <svg class="w-3 h-3 text-[#86868B] absolute left-1.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>

              <div v-if="agrupacion !== 'cluster'" class="flex items-center gap-1">
                <button
                  type="button"
                  @click="colapsarTodo"
                  class="px-2 py-0.5 rounded-[6px] bg-black/[0.04] hover:bg-black/[0.08] text-[#1D1D1F] font-bold transition-all cursor-pointer"
                >
                  Colapsar
                </button>
                <button
                  type="button"
                  @click="expandirNivel(0)"
                  class="px-2 py-0.5 rounded-[6px] bg-black/[0.04] hover:bg-black/[0.08] text-[#1D1D1F] font-bold transition-all cursor-pointer hidden sm:inline"
                >
                  Nivel 1
                </button>
                <button
                  type="button"
                  @click="expandirNivel(1)"
                  class="px-2 py-0.5 rounded-[6px] bg-black/[0.04] hover:bg-black/[0.08] text-[#1D1D1F] font-bold transition-all cursor-pointer hidden sm:inline"
                >
                  Nivel 2
                </button>
                <button
                  type="button"
                  @click="expandirTodo"
                  class="px-2 py-0.5 rounded-[6px] bg-[#1D1D1F] hover:bg-black text-white font-bold transition-all cursor-pointer"
                >
                  Expandir Todo
                </button>
              </div>
            </div>
          </div>

          <!-- Contenedor con Scroll de la Matriz -->
          <div class="overflow-x-auto custom-scroll max-h-[650px] relative">
            <table class="w-full text-left border-collapse text-[11px]">
              <!-- Encabezado de la Matriz -->
              <thead class="sticky top-0 z-30 bg-[#F5F5F7] border-b border-black/[0.08]">
                <tr>
                  <!-- Columna Estructura / Jerarquía (Fijada) -->
                  <th
                    class="sticky left-0 z-40 bg-[#F5F5F7] px-3.5 py-2.5 font-bold text-[#1D1D1F] border-r border-black/[0.06] min-w-[240px] shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]"
                  >
                    Estructura / Jerarquía
                  </th>

                  <!-- Columnas de Meses -->
                  <th
                    v-for="m in meses"
                    :key="m.key"
                    :colspan="modoVistaTabla === 'detallada' ? 4 : 1"
                    class="px-2 py-2 text-center border-r border-black/[0.06] font-bold text-[#1D1D1F]"
                  >
                    <div class="flex flex-col items-center justify-center">
                      <div class="flex items-center gap-1">
                        <span class="font-bold text-[11px] text-[#1D1D1F]">{{ m.nombre }}</span>
                        <span class="text-[10px] text-[#6E6E73]">{{ m.anio }}</span>
                        <span
                          v-if="!m.es_cerrado"
                          class="px-1 py-0.2 rounded-[3px] bg-blue-50 text-[#0071E3] border border-blue-200 text-[8px] font-bold uppercase tracking-wider"
                          :title="'Corte evaluado al ' + m.ty_fin"
                        >
                          Corte
                        </span>
                      </div>
                      <span class="text-[9px] text-[#86868B] font-mono">
                        vs {{ m.nombre }} {{ m.anio - 1 }}
                      </span>
                    </div>

                    <!-- Subencabezados en Vista Detallada -->
                    <div
                      v-if="modoVistaTabla === 'detallada'"
                      class="grid grid-cols-4 gap-1 text-[9px] font-mono font-bold text-[#86868B] mt-1 pt-1 border-t border-black/[0.05]"
                    >
                      <span title="Año Actual">TY</span>
                      <span title="Año Anterior Comparable">LY</span>
                      <span title="Diferencia">Dif</span>
                      <span title="% Crecimiento">% Crec</span>
                    </div>
                  </th>

                  <!-- Columnas Resumen Total -->
                  <th
                    :colspan="modoVistaTabla === 'detallada' ? 5 : 3"
                    class="px-3 py-2 text-center bg-[#EBEBED] text-[#1D1D1F] font-bold border-l border-black/[0.08]"
                  >
                    <div class="flex flex-col items-center justify-center">
                      <span class="font-bold text-[11px] uppercase tracking-wider">Consolidado Total</span>
                      <span class="text-[9px] text-[#86868B] font-normal">
                        {{ meses.length }} Meses
                      </span>
                    </div>

                    <div
                      v-if="modoVistaTabla === 'detallada'"
                      class="grid grid-cols-5 gap-1 text-[9px] font-mono font-bold text-[#6E6E73] mt-1 pt-1 border-t border-black/[0.08]"
                    >
                      <span>Total TY</span>
                      <span>Total LY</span>
                      <span>Dif</span>
                      <span>% Crec</span>
                      <span title="% Participación">% Share</span>
                    </div>
                    <div
                      v-else
                      class="grid grid-cols-3 gap-1 text-[9px] font-mono font-bold text-[#6E6E73] mt-1 pt-1 border-t border-black/[0.08]"
                    >
                      <span>Total TY</span>
                      <span>% Crec</span>
                      <span>% Share</span>
                    </div>
                  </th>
                </tr>
              </thead>

              <!-- Cuerpo de la Matriz: Filas Multinivel -->
              <tbody class="divide-y divide-black/[0.04]">
                <tr
                  v-for="fila in filasVisibles"
                  :key="fila.id"
                  @click="seleccionarNodoParaGrafico(fila)"
                  class="hover:bg-blue-50/25 transition-colors group cursor-pointer"
                  :class="nodoSeleccionadoId === fila.id ? 'bg-blue-50/50' : ''"
                >
                  <!-- Columna Nombre y Chevron de Desglose (Fijada) -->
                  <td
                    class="sticky left-0 z-20 bg-white group-hover:bg-[#f8faff] py-1.5 pr-3 border-r border-black/[0.06] select-none shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]"
                    :class="nodoSeleccionadoId === fila.id ? 'bg-blue-50/60 font-bold' : ''"
                    :style="{ paddingLeft: (12 + fila.nivel * 16) + 'px' }"
                  >
                    <div class="flex items-center gap-1.5">
                      <!-- Botón Desplegar/Colapsar -->
                      <button
                        v-if="fila.children && fila.children.length"
                        type="button"
                        @click.stop="toggleNodo(fila.id)"
                        class="w-4 h-4 flex items-center justify-center text-[#86868B] hover:text-[#1D1D1F] cursor-pointer transition-transform"
                        :title="nodoExpandido(fila.id) ? 'Colapsar' : 'Expandir'"
                      >
                        <svg
                          class="w-3 h-3 transition-transform duration-150"
                          :class="nodoExpandido(fila.id) ? 'rotate-90 text-[#1D1D1F]' : ''"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                      </button>
                      <span v-else class="w-4 h-4 shrink-0"></span>

                      <!-- Badge de Tipo de Nivel -->
                      <span
                        class="text-[8px] font-mono font-bold uppercase tracking-wider px-1 py-0.2 rounded shrink-0"
                        :class="obtenerEstiloBadgeNivel(fila.tipo)"
                      >
                        {{ fila.tipo }}
                      </span>

                      <!-- Nombre del Nodo -->
                      <span
                        class="tracking-tight truncate max-w-[210px]"
                        :class="obtenerEstiloTextoNivel(fila.nivel)"
                        :title="fila.label"
                      >
                        {{ fila.label }}
                      </span>

                      <!-- Contador de Hijos cuando está colapsado -->
                      <span
                        v-if="fila.children && fila.children.length && !nodoExpandido(fila.id)"
                        class="text-[9px] font-mono text-[#86868B] ml-0.5"
                      >
                        ({{ fila.children.length }})
                      </span>

                      <!-- Botón para Graficar este Elemento -->
                      <button
                        type="button"
                        @click.stop="seleccionarNodoParaGrafico(fila)"
                        class="opacity-0 group-hover:opacity-100 ml-auto w-4 h-4 rounded text-[#86868B] hover:text-[#0071E3] transition-opacity cursor-pointer shrink-0"
                        :class="nodoSeleccionadoId === fila.id ? 'opacity-100 text-[#0071E3]' : ''"
                        title="Graficar la tendencia de este elemento"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                      </button>
                    </div>
                  </td>

                  <!-- Valores por Cada Mes -->
                  <template v-for="m in meses" :key="m.key">
                    <!-- VISTA DETALLADA -->
                    <template v-if="modoVistaTabla === 'detallada'">
                      <td
                        class="px-2 py-1.5 text-right font-mono tabular-nums text-[#1D1D1F]"
                        :class="fila.nivel === 0 ? 'font-bold' : ''"
                      >
                        {{ formatNum((fila.valores_mes[m.key] || {}).ty) }}
                      </td>
                      <td class="px-2 py-1.5 text-right font-mono tabular-nums text-[#6E6E73]">
                        {{ formatNum((fila.valores_mes[m.key] || {}).ly) }}
                      </td>
                      <td
                        class="px-2 py-1.5 text-right font-mono tabular-nums"
                        :class="obtenerColorDiferencia((fila.valores_mes[m.key] || {}).dif)"
                      >
                        {{ formatearDiferencia((fila.valores_mes[m.key] || {}).dif) }}
                      </td>
                      <td
                        class="px-2 py-1.5 text-right font-mono tabular-nums border-r border-black/[0.06]"
                        :class="obtenerColorCrecimiento((fila.valores_mes[m.key] || {}).crec_pct)"
                      >
                        {{ formatearCrecimiento((fila.valores_mes[m.key] || {}).crec_pct) }}
                      </td>
                    </template>

                    <!-- VISTA COMPACTA -->
                    <template v-else>
                      <td class="px-2.5 py-1.5 text-right font-mono tabular-nums border-r border-black/[0.06]">
                        <div class="flex flex-col items-end">
                          <span class="text-[#1D1D1F]" :class="fila.nivel === 0 ? 'font-bold' : ''">
                            {{ formatNum((fila.valores_mes[m.key] || {}).ty) }}
                          </span>
                          <span
                            class="text-[9px] font-semibold"
                            :class="obtenerColorCrecimiento((fila.valores_mes[m.key] || {}).crec_pct)"
                          >
                            {{ formatearCrecimiento((fila.valores_mes[m.key] || {}).crec_pct) }}
                          </span>
                        </div>
                      </td>
                    </template>
                  </template>

                  <!-- Resumen Total Fila -->
                  <template v-if="modoVistaTabla === 'detallada'">
                    <td class="px-2.5 py-1.5 text-right font-mono font-bold text-[#1D1D1F] bg-[#F5F5F7]/40 border-l border-black/[0.06]">
                      {{ formatNum(fila.total_ty) }}
                    </td>
                    <td class="px-2.5 py-1.5 text-right font-mono text-[#6E6E73] bg-[#F5F5F7]/40">
                      {{ formatNum(fila.total_ly) }}
                    </td>
                    <td
                      class="px-2.5 py-1.5 text-right font-mono bg-[#F5F5F7]/40"
                      :class="obtenerColorDiferencia(fila.dif_total)"
                    >
                      {{ formatearDiferencia(fila.dif_total) }}
                    </td>
                    <td
                      class="px-2.5 py-1.5 text-right font-mono font-bold bg-[#F5F5F7]/40"
                      :class="obtenerColorCrecimiento(fila.crec_total_pct)"
                    >
                      {{ formatearCrecimiento(fila.crec_total_pct) }}
                    </td>
                    <td class="px-2.5 py-1.5 text-right font-mono text-[#0071E3] font-bold bg-[#F5F5F7]/40">
                      {{ (fila.share_pct ?? 0).toFixed(1) }}%
                    </td>
                  </template>
                  <template v-else>
                    <td class="px-2.5 py-1.5 text-right font-mono font-bold text-[#1D1D1F] bg-[#F5F5F7]/40 border-l border-black/[0.06]">
                      {{ formatNum(fila.total_ty) }}
                    </td>
                    <td
                      class="px-2.5 py-1.5 text-right font-mono font-bold bg-[#F5F5F7]/40"
                      :class="obtenerColorCrecimiento(fila.crec_total_pct)"
                    >
                      {{ formatearCrecimiento(fila.crec_total_pct) }}
                    </td>
                    <td class="px-2.5 py-1.5 text-right font-mono text-[#0071E3] font-bold bg-[#F5F5F7]/40">
                      {{ (fila.share_pct ?? 0).toFixed(1) }}%
                    </td>
                  </template>
                </tr>

                <!-- Si no hay filas -->
                <tr v-if="filasVisibles.length === 0">
                  <td :colspan="1 + (meses.length * (modoVistaTabla === 'detallada' ? 4 : 1)) + (modoVistaTabla === 'detallada' ? 5 : 3)" class="p-8 text-center text-[#86868B] font-medium">
                    No se encontraron registros para los filtros seleccionados.
                  </td>
                </tr>
              </tbody>

              <!-- ── FOOTER: TOTAL GENERAL DE LA EMPRESA ── -->
              <tfoot v-if="totales && meses.length > 0" class="sticky bottom-0 z-30 bg-[#F5F5F7] font-bold text-[#1D1D1F] border-t-2 border-black/[0.08] shadow-[0_-2px_6px_rgba(0,0,0,0.04)]">
                <tr>
                  <td class="sticky left-0 z-40 bg-[#F5F5F7] px-3.5 py-2.5 font-sans uppercase tracking-wider text-[10px] border-r border-black/[0.06] shadow-[2px_0_6px_-2px_rgba(0,0,0,0.08)]">
                    TOTAL GENERAL
                  </td>

                  <!-- Totales por Mes -->
                  <template v-for="m in meses" :key="'tot-' + m.key">
                    <template v-if="modoVistaTabla === 'detallada'">
                      <td class="px-2 py-2 text-right font-mono tabular-nums">
                        {{ formatNum((totales.valores_mes?.[m.key] || {}).ty) }}
                      </td>
                      <td class="px-2 py-2 text-right font-mono tabular-nums text-[#6E6E73]">
                        {{ formatNum((totales.valores_mes?.[m.key] || {}).ly) }}
                      </td>
                      <td
                        class="px-2 py-2 text-right font-mono tabular-nums"
                        :class="obtenerColorDiferencia((totales.valores_mes?.[m.key] || {}).dif)"
                      >
                        {{ formatearDiferencia((totales.valores_mes?.[m.key] || {}).dif) }}
                      </td>
                      <td
                        class="px-2 py-2 text-right font-mono tabular-nums border-r border-black/[0.06]"
                        :class="obtenerColorCrecimiento((totales.valores_mes?.[m.key] || {}).crec_pct)"
                      >
                        {{ formatearCrecimiento((totales.valores_mes?.[m.key] || {}).crec_pct) }}
                      </td>
                    </template>
                    <template v-else>
                      <td class="px-2.5 py-2 text-right font-mono tabular-nums border-r border-black/[0.06]">
                        <div class="flex flex-col items-end">
                          <span>{{ formatNum((totales.valores_mes?.[m.key] || {}).ty) }}</span>
                          <span
                            class="text-[9px]"
                            :class="obtenerColorCrecimiento((totales.valores_mes?.[m.key] || {}).crec_pct)"
                          >
                            {{ formatearCrecimiento((totales.valores_mes?.[m.key] || {}).crec_pct) }}
                          </span>
                        </div>
                      </td>
                    </template>
                  </template>

                  <!-- Totales Resumen Consolidado -->
                  <template v-if="modoVistaTabla === 'detallada'">
                    <td class="px-2.5 py-2 text-right font-mono text-[#1D1D1F] bg-[#EBEBED] border-l border-black/[0.08]">
                      {{ formatNum(totales.total_ty) }}
                    </td>
                    <td class="px-2.5 py-2 text-right font-mono text-[#6E6E73] bg-[#EBEBED]">
                      {{ formatNum(totales.total_ly) }}
                    </td>
                    <td
                      class="px-2.5 py-2 text-right font-mono bg-[#EBEBED]"
                      :class="obtenerColorDiferencia(totales.dif_total)"
                    >
                      {{ formatearDiferencia(totales.dif_total) }}
                    </td>
                    <td
                      class="px-2.5 py-2 text-right font-mono bg-[#EBEBED]"
                      :class="obtenerColorCrecimiento(totales.crec_total_pct)"
                    >
                      {{ formatearCrecimiento(totales.crec_total_pct) }}
                    </td>
                    <td class="px-2.5 py-2 text-right font-mono text-[#0071E3] bg-[#EBEBED]">
                      100.0%
                    </td>
                  </template>
                  <template v-else>
                    <td class="px-2.5 py-2 text-right font-mono text-[#1D1D1F] bg-[#EBEBED] border-l border-black/[0.08]">
                      {{ formatNum(totales.total_ty) }}
                    </td>
                    <td
                      class="px-2.5 py-2 text-right font-mono bg-[#EBEBED]"
                      :class="obtenerColorCrecimiento(totales.crec_total_pct)"
                    >
                      {{ formatearCrecimiento(totales.crec_total_pct) }}
                    </td>
                    <td class="px-2.5 py-2 text-right font-mono text-[#0071E3] bg-[#EBEBED]">
                      100.0%
                    </td>
                  </template>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>
      </template>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SubNavTabs from '@/Components/UI/SubNavTabs.vue'
import { navigationConfig } from '@/Config/navigation.js'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({
      canales: [],
      rutas: [],
      clusters: [],
      min_fecha: '',
      max_fecha: '',
      fecha_inicio_default: '',
      fecha_corte_default: '',
    })
  }
})

// Subnavegación de Módulo Avance
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance')
const avanceTabs = avanceMenu ? avanceMenu.submodules : []

// Estado de Filtros
const agrupacion = ref('cluster_jerarquia') // 'cluster_jerarquia' | 'cluster_ruta_jerarquia' | 'ruta_jerarquia' | 'cluster'
const metrica = ref('monto') // 'monto' | 'cajas' | 'unidades'
const fechaInicio = ref(props.catalogo.fecha_inicio_default || '')
const fechaCorte = ref(props.catalogo.fecha_corte_default || '')

const listaCanales = computed(() => props.catalogo.canales || [])
const canalesSeleccionados = ref([])

const listaRutas = computed(() => props.catalogo.rutas || [])
const rutasSeleccionadas = ref([])
const filtroRutasTexto = ref('')

const listaClusters = computed(() => props.catalogo.clusters || [])
const clustersSeleccionados = ref([])
const filtroClustersTexto = ref('')

// Dropdowns popovers
const dropdownAbierto = ref(null)

const toggleDropdown = (nombre) => {
  dropdownAbierto.value = dropdownAbierto.value === nombre ? null : nombre
}

const handleClickOutside = (e) => {
  if (dropdownAbierto.value && !e.target.closest('.apple-control-pill') && !e.target.closest('.z-\\[10002\\]')) {
    dropdownAbierto.value = null
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
  ejecutarConsulta()
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleClickOutside)
})

// Filtros internos de popovers
const rutasFiltradasDropdown = computed(() => {
  if (!filtroRutasTexto.value.trim()) return listaRutas.value
  const t = filtroRutasTexto.value.toLowerCase()
  return listaRutas.value.filter(r => r.toLowerCase().includes(t))
})

const clustersFiltradosDropdown = computed(() => {
  if (!filtroClustersTexto.value.trim()) return listaClusters.value
  const t = filtroClustersTexto.value.toLowerCase()
  return listaClusters.value.filter(c => c.toLowerCase().includes(t))
})

// Etiquetas de selector
const etiquetaCanales = computed(() => {
  if (canalesSeleccionados.value.length === 0 || canalesSeleccionados.value.length === listaCanales.value.length) return 'Todos'
  if (canalesSeleccionados.value.length === 1) return canalesSeleccionados.value[0]
  return `${canalesSeleccionados.value.length} sel.`
})

const etiquetaRutas = computed(() => {
  if (rutasSeleccionadas.value.length === 0 || rutasSeleccionadas.value.length === listaRutas.value.length) return 'Todas'
  if (rutasSeleccionadas.value.length === 1) return rutasSeleccionadas.value[0]
  return `${rutasSeleccionadas.value.length} sel.`
})

const etiquetaClusters = computed(() => {
  if (clustersSeleccionados.value.length === 0 || clustersSeleccionados.value.length === listaClusters.value.length) return 'Todos'
  if (clustersSeleccionados.value.length === 1) return clustersSeleccionados.value[0]
  return `${clustersSeleccionados.value.length} sel.`
})

const etiquetaTituloAgrupacion = computed(() => {
  if (agrupacion.value === 'cluster_jerarquia') return 'Cluster y Jerarquías'
  if (agrupacion.value === 'cluster_ruta_jerarquia') return 'Cluster, Ruta y Jerarquías'
  if (agrupacion.value === 'ruta_jerarquia') return 'Ruta y Jerarquías'
  return 'Cluster'
})

// Modos de Visualización
const vistaModo = ref('ambos') // 'ambos' | 'grafico' | 'matriz'
const modoVistaTabla = ref('detallada') // 'detallada' | 'compacta'
const modoGrafico = ref('serie_unica') // 'serie_unica' | 'multilinea'
const nodoSeleccionadoId = ref('__TOTAL__')
const hoverLineaId = ref(null)

// Datos cargados desde backend
const cargando = ref(false)
const errorMensaje = ref(null)
const meses = ref([])
const filasOriginales = ref([])
const totales = ref(null)
const unidad = ref('Bs')

// Árbol expandido
const nodosExpandidos = reactive(new Set())
const filtroTextoArbol = ref('')

// Helpers de Árbol
const nodoExpandido = (id) => nodosExpandidos.has(id)
const toggleNodo = (id) => {
  if (nodosExpandidos.has(id)) {
    nodosExpandidos.delete(id)
  } else {
    nodosExpandidos.add(id)
  }
}

const expandirTodo = () => {
  const addIds = (nodes) => {
    nodes.forEach(n => {
      if (n.children && n.children.length) {
        nodosExpandidos.add(n.id)
        addIds(n.children)
      }
    })
  }
  addIds(filasOriginales.value)
}

const colapsarTodo = () => {
  nodosExpandidos.clear()
}

const expandirNivel = (maxNivel) => {
  nodosExpandidos.clear()
  const addIdsNivel = (nodes) => {
    nodes.forEach(n => {
      if (n.nivel <= maxNivel && n.children && n.children.length) {
        nodosExpandidos.add(n.id)
        addIdsNivel(n.children)
      }
    })
  }
  addIdsNivel(filasOriginales.value)
}

// Aplanar árbol para tabla considerando nodos expandidos y filtro de búsqueda
const filasVisibles = computed(() => {
  const result = []
  const textoFiltro = filtroTextoArbol.value.trim().toLowerCase()

  // Si hay filtro de texto, verificar si el nodo o algún descendiente coincide
  const coincideNodo = (node) => {
    if (!textoFiltro) return true
    if (node.label.toLowerCase().includes(textoFiltro)) return true
    if (node.children && node.children.length) {
      return node.children.some(coincideNodo)
    }
    return false
  }

  const recorrer = (nodes) => {
    nodes.forEach(n => {
      if (!coincideNodo(n)) return

      result.push(n)

      // Si hay filtro activo, expandir automáticamente para mostrar coincidencias
      const debeMostrarHijos = textoFiltro ? true : nodosExpandidos.has(n.id)
      if (debeMostrarHijos && n.children && n.children.length) {
        recorrer(n.children)
      }
    })
  }

  recorrer(filasOriginales.value)
  return result
})

// Opciones planas para el selector de nodos del gráfico
const opcionesNodosGrafico = computed(() => {
  const result = []
  const recorrer = (nodes) => {
    nodes.forEach(n => {
      const indent = '—'.repeat(n.nivel) + (n.nivel > 0 ? ' ' : '')
      result.push({
        id: n.id,
        label: n.label,
        labelIndent: indent + n.label,
        tipo: n.tipo,
        nivel: n.nivel,
      })
      if (n.children && n.children.length) {
        recorrer(n.children)
      }
    })
  }
  recorrer(filasOriginales.value)
  return result
})

// Mapa rápido id -> nodo para el gráfico
const mapaNodosPorId = computed(() => {
  const map = new Map()
  const recorrer = (nodes) => {
    nodes.forEach(n => {
      map.set(n.id, n)
      if (n.children && n.children.length) recorrer(n.children)
    })
  }
  recorrer(filasOriginales.value)
  return map
})

const nodoActivoGrafico = computed(() => {
  if (nodoSeleccionadoId.value === '__TOTAL__') {
    return {
      id: '__TOTAL__',
      label: 'Consolidado Total General',
      tipo: 'total',
      nivel: -1,
      total_ty: totales.value?.total_ty ?? 0,
      total_ly: totales.value?.total_ly ?? 0,
      dif_total: totales.value?.dif_total ?? 0,
      crec_total_pct: totales.value?.crec_total_pct ?? 0,
      share_pct: 100.0,
      valores_mes: totales.value?.valores_mes ?? {}
    }
  }
  return mapaNodosPorId.value.get(nodoSeleccionadoId.value) || null
})

const seleccionarNodoParaGrafico = (nodo) => {
  nodoSeleccionadoId.value = nodo.id
}

// ── CONSULTA AL SERVIDOR ──
const ejecutarConsulta = async () => {
  cargando.value = true
  errorMensaje.value = null

  try {
    const payload = {
      fecha_inicio: fechaInicio.value,
      fecha_corte: fechaCorte.value,
      canales: canalesSeleccionados.value,
      rutas: rutasSeleccionadas.value,
      clusters: clustersSeleccionados.value,
      metrica: metrica.value,
      agrupacion: agrupacion.value,
    }

    const res = await axios.post(route('supervisor.avance.cluster.matriz'), payload)
    const data = res.data

    meses.value = data.meses || []
    filasOriginales.value = data.filas || []
    totales.value = data.totales || null
    unidad.value = data.unidad || 'Bs'

    // Por defecto expandir nivel 0 (clusters o rutas principales)
    expandirNivel(0)

    // Si el nodo seleccionado ya no existe, volver a Total
    if (nodoSeleccionadoId.value !== '__TOTAL__' && !mapaNodosPorId.value.has(nodoSeleccionadoId.value)) {
      nodoSeleccionadoId.value = '__TOTAL__'
    }
  } catch (err) {
    console.error('[Cluster] Error cargando matriz:', err)
    errorMensaje.value = err.response?.data?.message || err.message || 'Error al consultar la matriz de cluster vs ly'
  } finally {
    cargando.value = false
  }
}

// Presets de Fechas
const aplicarPreset = (tipo) => {
  const dMax = props.catalogo.max_fecha ? new Date(props.catalogo.max_fecha + 'T00:00:00') : new Date()
  fechaCorte.value = dMax.toISOString().slice(0, 10)

  if (tipo === 'YTD') {
    const dIni = new Date(dMax.getFullYear(), 0, 1)
    fechaInicio.value = dIni.toISOString().slice(0, 10)
  } else if (tipo === '6M') {
    const dIni = new Date(dMax.getFullYear(), dMax.getMonth() - 5, 1)
    fechaInicio.value = dIni.toISOString().slice(0, 10)
  } else if (tipo === '3M') {
    const dIni = new Date(dMax.getFullYear(), dMax.getMonth() - 2, 1)
    fechaInicio.value = dIni.toISOString().slice(0, 10)
  } else if (tipo === 'MTD') {
    const dIni = new Date(dMax.getFullYear(), dMax.getMonth(), 1)
    fechaInicio.value = dIni.toISOString().slice(0, 10)
  }

  ejecutarConsulta()
}

// Handlers de cambio de agrupación y métrica
const cambiarAgrupacion = (nueva) => {
  if (agrupacion.value === nueva) return
  agrupacion.value = nueva
  nodoSeleccionadoId.value = '__TOTAL__'
  ejecutarConsulta()
}

const cambiarMetrica = (nueva) => {
  if (metrica.value === nueva) return
  metrica.value = nueva
  ejecutarConsulta()
}

const cerrarDropdownYConsultar = () => {
  dropdownAbierto.value = null
  ejecutarConsulta()
}

// Handlers de checkboxes
const toggleCanal = (c) => {
  const idx = canalesSeleccionados.value.indexOf(c)
  if (idx >= 0) canalesSeleccionados.value.splice(idx, 1)
  else canalesSeleccionados.value.push(c)
}
const marcarTodosCanales = (todos) => {
  canalesSeleccionados.value = todos ? [...listaCanales.value] : []
}

const toggleRuta = (r) => {
  const idx = rutasSeleccionadas.value.indexOf(r)
  if (idx >= 0) rutasSeleccionadas.value.splice(idx, 1)
  else rutasSeleccionadas.value.push(r)
}
const marcarTodasRutas = (todas) => {
  rutasSeleccionadas.value = todas ? [...listaRutas.value] : []
}

const toggleCluster = (cl) => {
  const idx = clustersSeleccionados.value.indexOf(cl)
  if (idx >= 0) clustersSeleccionados.value.splice(idx, 1)
  else clustersSeleccionados.value.push(cl)
}
const marcarTodosClusters = (todos) => {
  clustersSeleccionados.value = todos ? [...listaClusters.value] : []
}

// ── CÁLCULOS DEL GRÁFICO SVG ──
const svgContainerRef = ref(null)
const hoverMesIdx = ref(null)

const svgConfig = {
  width: 1000,
  height: 240,
  paddingLeft: 70,
  paddingRight: 35,
  paddingTop: 20,
  paddingBottom: 35
}

const plotArea = computed(() => ({
  x0: svgConfig.paddingLeft,
  y0: svgConfig.paddingTop,
  w: svgConfig.width - svgConfig.paddingLeft - svgConfig.paddingRight,
  h: svgConfig.height - svgConfig.paddingTop - svgConfig.paddingBottom,
  yBottom: svgConfig.height - svgConfig.paddingBottom
}))

const puntosSerieUnica = computed(() => {
  const mList = meses.value
  const nodo = nodoActivoGrafico.value
  if (!nodo || mList.length === 0) return { tyPts: [], lyPts: [], yTicks: [], maxVal: 1 }

  const valoresTy = mList.map(m => Number(nodo.valores_mes?.[m.key]?.ty || 0))
  const valoresLy = mList.map(m => Number(nodo.valores_mes?.[m.key]?.ly || 0))

  let maxV = Math.max(...valoresTy, ...valoresLy, 1)
  // Redondear techo
  maxV = maxV * 1.15

  const pArea = plotArea.value
  const numMeses = mList.length

  const tyPts = valoresTy.map((v, i) => {
    const x = pArea.x0 + (numMeses > 1 ? i * (pArea.w / (numMeses - 1)) : pArea.w / 2)
    const y = pArea.yBottom - (v / maxV) * pArea.h
    return { x, y, val: v }
  })

  const lyPts = valoresLy.map((v, i) => {
    const x = pArea.x0 + (numMeses > 1 ? i * (pArea.w / (numMeses - 1)) : pArea.w / 2)
    const y = pArea.yBottom - (v / maxV) * pArea.h
    return { x, y, val: v }
  })

  // Y-ticks
  const yTicks = [0, 0.25, 0.5, 0.75, 1.0].map(frac => {
    const v = maxV * frac
    const y = pArea.yBottom - frac * pArea.h
    let label = ''
    if (v >= 1000000) label = (v / 1000000).toFixed(1) + 'M'
    else if (v >= 1000) label = (v / 1000).toFixed(0) + 'k'
    else label = v.toFixed(0)
    return { y, label }
  })

  return { tyPts, lyPts, yTicks, maxVal: maxV }
})

const pathTY = computed(() => {
  const pts = puntosSerieUnica.value.tyPts
  if (!pts.length) return ''
  return 'M ' + pts.map(p => `${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' L ')
})

const pathLY = computed(() => {
  const pts = puntosSerieUnica.value.lyPts
  if (!pts.length) return ''
  return 'M ' + pts.map(p => `${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' L ')
})

const areaTY = computed(() => {
  const pts = puntosSerieUnica.value.tyPts
  if (!pts.length) return ''
  const pArea = plotArea.value
  const first = pts[0]
  const last = pts[pts.length - 1]
  return `M ${first.x.toFixed(1)} ${pArea.yBottom} L ` +
    pts.map(p => `${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' L ') +
    ` L ${last.x.toFixed(1)} ${pArea.yBottom} Z`
})

// MODO MULTILÍNEA
const paletaColores = [
  '#0071E3', '#FF9500', '#34C759', '#AF52DE',
  '#FF2D55', '#5856D6', '#00C7BE', '#A2845E',
  '#5AC8FA', '#FFCC00'
]

const lineasMultilinea = computed(() => {
  const mList = meses.value
  const filasNivel0 = filasOriginales.value.filter(f => f.nivel === 0)
  if (!filasNivel0.length || !mList.length) return { lineas: [], yTicks: [] }

  let maxV = 1
  filasNivel0.forEach(f => {
    mList.forEach(m => {
      const v = Number(f.valores_mes?.[m.key]?.ty || 0)
      if (v > maxV) maxV = v
    })
  })
  maxV = maxV * 1.15

  const pArea = plotArea.value
  const numMeses = mList.length

  const lineas = filasNivel0.map((f, fIdx) => {
    const color = paletaColores[fIdx % paletaColores.length]
    const pts = mList.map((m, i) => {
      const v = Number(f.valores_mes?.[m.key]?.ty || 0)
      const x = pArea.x0 + (numMeses > 1 ? i * (pArea.w / (numMeses - 1)) : pArea.w / 2)
      const y = pArea.yBottom - (v / maxV) * pArea.h
      return { x, y, val: v }
    })
    const pathD = 'M ' + pts.map(p => `${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' L ')
    return {
      id: f.id,
      label: f.label,
      color,
      pts,
      pathD,
      total_ty: f.total_ty
    }
  })

  const yTicks = [0, 0.25, 0.5, 0.75, 1.0].map(frac => {
    const v = maxV * frac
    const y = pArea.yBottom - frac * pArea.h
    let label = ''
    if (v >= 1000000) label = (v / 1000000).toFixed(1) + 'M'
    else if (v >= 1000) label = (v / 1000).toFixed(0) + 'k'
    else label = v.toFixed(0)
    return { y, label }
  })

  return { lineas, yTicks }
})

const onSvgMouseMove = (e) => {
  if (!svgContainerRef.value || meses.value.length === 0) return
  const rect = svgContainerRef.value.getBoundingClientRect()
  const mouseX = e.clientX - rect.left
  const svgX = (mouseX / rect.width) * svgConfig.width

  const pArea = plotArea.value
  const numMeses = meses.value.length
  if (numMeses <= 1) {
    hoverMesIdx.value = 0
    return
  }

  const step = pArea.w / (numMeses - 1)
  const relX = svgX - pArea.x0
  let idx = Math.round(relX / step)
  idx = Math.max(0, Math.min(numMeses - 1, idx))
  hoverMesIdx.value = idx
}

const datosMesHover = computed(() => {
  if (hoverMesIdx.value === null || !meses.value[hoverMesIdx.value]) return {}
  const m = meses.value[hoverMesIdx.value]
  const nodo = nodoActivoGrafico.value
  return nodo?.valores_mes?.[m.key] || {}
})

// ── FORMATOS Y ESTILOS ──
const formatNum = (val) => {
  if (val === null || val === undefined) return '0'
  const dec = metrica.value === 'unidades' ? 0 : 2
  return Number(val).toLocaleString('es-BO', { minimumFractionDigits: dec, maximumFractionDigits: dec })
}

const formatearDiferencia = (val) => {
  if (val === null || val === undefined) return '0'
  const n = Number(val)
  const signo = n > 0 ? '+' : ''
  return signo + formatNum(n)
}

const formatearCrecimiento = (val) => {
  if (val === null || val === undefined) return '0.0%'
  const n = Number(val)
  const signo = n > 0 ? '+' : ''
  return `${signo}${n.toFixed(1)}%`
}

const obtenerColorDiferencia = (val) => {
  const n = Number(val || 0)
  if (n > 0) return 'text-emerald-600 font-medium'
  if (n < 0) return 'text-rose-600 font-medium'
  return 'text-[#86868B]'
}

const obtenerColorCrecimiento = (val) => {
  const n = Number(val || 0)
  if (n > 0) return 'text-emerald-600 font-bold'
  if (n < 0) return 'text-rose-600 font-bold'
  return 'text-[#86868B]'
}

const obtenerEstiloBadgeNivel = (tipo) => {
  switch (tipo) {
    case 'cluster':
      return 'bg-blue-50 text-[#0071E3] border border-blue-200'
    case 'ruta':
      return 'bg-purple-50 text-purple-700 border border-purple-200'
    case 'j1':
      return 'bg-amber-50 text-amber-700 border border-amber-200'
    case 'j2':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    case 'j3':
      return 'bg-slate-100 text-slate-700 border border-slate-200'
    default:
      return 'bg-black/[0.04] text-[#6E6E73]'
  }
}

const obtenerEstiloTextoNivel = (nivel) => {
  switch (nivel) {
    case 0:
      return 'font-bold text-[#1D1D1F] text-[12px]'
    case 1:
      return 'font-semibold text-[#1D1D1F] text-[11px]'
    case 2:
      return 'font-medium text-[#3C3C43] text-[11px]'
    default:
      return 'text-[#6E6E73] text-[10px]'
  }
}

// ── EXPORTACIÓN CSV PARA EXCEL ──
const exportarCsv = () => {
  if (!filasOriginales.value.length || !meses.value.length) return

  const headers = ['Nivel', 'Tipo', 'Elemento']
  meses.value.forEach(m => {
    headers.push(`${m.nombre} ${m.anio} TY`)
    headers.push(`${m.nombre} ${m.anio} LY`)
    headers.push(`${m.nombre} Dif`)
    headers.push(`${m.nombre} % Crec`)
  })
  headers.push('Total TY', 'Total LY', 'Dif Total', '% Crec Total', '% Share')

  const rows = []

  const aplanarParaExportar = (nodes) => {
    nodes.forEach(n => {
      const row = [
        n.nivel,
        n.tipo,
        `"${n.label.replace(/"/g, '""')}"`
      ]
      meses.value.forEach(m => {
        const vm = n.valores_mes?.[m.key] || {}
        row.push(vm.ty ?? 0)
        row.push(vm.ly ?? 0)
        row.push(vm.dif ?? 0)
        row.push((vm.crec_pct ?? 0) + '%')
      })
      row.push(n.total_ty ?? 0)
      row.push(n.total_ly ?? 0)
      row.push(n.dif_total ?? 0)
      row.push((n.crec_total_pct ?? 0) + '%')
      row.push((n.share_pct ?? 0) + '%')
      rows.push(row.join(','))

      if (n.children && n.children.length) {
        aplanarParaExportar(n.children)
      }
    })
  }

  aplanarParaExportar(filasOriginales.value)

  // Fila Total
  if (totales.value) {
    const rowTot = ['-', 'TOTAL', '"TOTAL GENERAL"']
    meses.value.forEach(m => {
      const vm = totales.value.valores_mes?.[m.key] || {}
      rowTot.push(vm.ty ?? 0)
      rowTot.push(vm.ly ?? 0)
      rowTot.push(vm.dif ?? 0)
      rowTot.push((vm.crec_pct ?? 0) + '%')
    })
    rowTot.push(totales.value.total_ty ?? 0)
    rowTot.push(totales.value.total_ly ?? 0)
    rowTot.push(totales.value.dif_total ?? 0)
    rowTot.push((totales.value.crec_total_pct ?? 0) + '%')
    rowTot.push('100.0%')
    rows.push(rowTot.join(','))
  }

  const csvContent = '\uFEFF' + headers.join(',') + '\n' + rows.join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Cluster_vs_LY_${agrupacion.value}_${fechaInicio.value}_al_${fechaCorte.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.02);
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.25);
}
</style>

