<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans text-ink bg-[#F5F5F7]">
      
      <!-- 1. Barra de Filtros Universal de Preventa -->
      <header class="p-2 sm:p-3 pb-0 shrink-0 space-y-2">
        <PreventaFiltrosBar
          currentSubTab="pizarra-visual"
          @consultar="onConsultar"
          @cambio-fecha="onConsultar"
        />

        <!-- 2. Navegación por KPI Segmented Control (Apple Glass) -->
        <nav
          class="apple-glass-card rounded-[14px] p-1.5 flex flex-nowrap gap-1 items-center overflow-x-auto no-scrollbar text-[11px] shrink-0 select-none bg-white/80 backdrop-blur-md border border-black/[0.06]"
        >
          <button
            v-for="tab in kpiTabs"
            :key="tab.id"
            type="button"
            @click="tabActivo = tab.id"
            class="px-3 py-1.5 rounded-[9px] transition-all duration-200 cursor-pointer text-[11px] shrink-0 whitespace-nowrap flex items-center gap-1.5"
            :class="
              tabActivo === tab.id
                ? 'glass-active-pill text-[#1D1D1F] font-bold shadow-xs'
                : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white/60 font-semibold'
            "
          >
            <span
              v-if="tabActivo === tab.id"
              class="w-1.5 h-1.5 rounded-full bg-[#0071E3] shadow-[0_0_6px_rgba(0,113,227,0.7)]"
            ></span>
            <span>{{ tab.label }}</span>
            <span
              v-if="tab.badge"
              class="px-1.5 py-0.2 rounded-full text-[9px] font-bold"
              :class="tabActivo === tab.id ? 'bg-[#0071E3] text-white' : 'bg-black/[0.06] text-[#86868B]'"
            >
              {{ tab.badge }}
            </span>
          </button>
        </nav>
      </header>

      <!-- 3. Contenido Principal según Pestaña -->
      <main class="flex-1 flex min-h-0 relative overflow-hidden p-2 sm:p-3 pt-2">

        <!-- KPI 1: HORARIO - HISTÓRICO Y GANTT CRONOLÓGICO DE VISITAS -->
        <HorarioVisualDashboard
          v-if="tabActivo === 'horario'"
          :fecha="store.fecha"
          :canales="store.filtrosAplicados?.canales?.length ? store.filtrosAplicados.canales : store.canalesSeleccionados"
          :rutas="store.filtrosAplicados?.rutas?.length ? store.filtrosAplicados.rutas : store.rutasSeleccionadas"
          @abrir-foto="abrirModalFoto"
        />

        <!-- KPI 2: VISITAS - MAPA Y GRÁFICOS ANALÍTICOS -->
        <div v-else-if="tabActivo === 'visitas'" class="flex-1 flex flex-col md:flex-row min-h-0 rounded-[18px] overflow-hidden apple-glass-card border border-black/[0.08] shadow-sm bg-white">
          
          <!-- PANEL LATERAL: GRÁFICOS Y CONTROLES (IZQUIERDA) -->
          <aside
            class="w-full md:w-[380px] lg:w-[410px] bg-[#FAFBFD] border-r border-black/[0.06] flex flex-col shrink-0 relative z-10 overflow-hidden"
            :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
          >
            <!-- Cabecera del Panel Lateral -->
            <div class="p-3 border-b border-black/[0.06] bg-white/70 backdrop-blur-sm flex items-center justify-between gap-2 shrink-0">
              <div>
                <h3 class="text-xs font-extrabold text-[#1D1D1F] tracking-tight uppercase">
                  KPI 2 · Cobertura & Visitas
                </h3>
                <p class="text-[10px] text-[#6E6E73] font-medium">
                  Fecha: <span class="font-bold font-mono text-[#1D1D1F]">{{ store.fecha }}</span> · {{ rutasEvaluadasCount }} rutas
                </p>
              </div>

              <!-- Indicador de Carga -->
              <span v-if="cargando" class="w-3.5 h-3.5 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></span>
              
              <!-- Toggle móvil para ir al mapa -->
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden px-2.5 py-1 rounded-lg text-xs font-bold bg-[#0071E3] text-white"
              >
                Ver Mapa
              </button>
            </div>

            <!-- Resumen Rápido de Métricas -->
            <div class="p-2.5 bg-white border-b border-black/[0.05] grid grid-cols-4 gap-1.5 text-center text-xs shrink-0">
              <div class="p-1.5 rounded-lg bg-[#F5F5F7] border border-black/[0.04]">
                <div class="text-[9px] font-bold text-[#86868B] uppercase">Plan</div>
                <div class="text-xs font-extrabold font-mono text-[#1D1D1F]">{{ totales.total_cartera_plan }}</div>
              </div>
              <div class="p-1.5 rounded-lg bg-[#EBF9EF] border border-[#D1F2D9]">
                <div class="text-[9px] font-bold text-[#1E6B37] uppercase">Visitados</div>
                <div class="text-xs font-extrabold font-mono text-[#1E6B37]">{{ totales.total_visitados }}</div>
              </div>
              <div class="p-1.5 rounded-lg bg-[#FDF0EF] border border-[#FADCD5]">
                <div class="text-[9px] font-bold text-[#C9342C] uppercase">Pendientes</div>
                <div class="text-xs font-extrabold font-mono text-[#C9342C]">{{ totales.pendientes }}</div>
              </div>
              <div class="p-1.5 rounded-lg bg-[#FFF5E5] border border-amber-200">
                <div class="text-[9px] font-bold text-[#B25E00] uppercase">Fuera Plan</div>
                <div class="text-xs font-extrabold font-mono text-[#B25E00]">{{ totales.fuera_plan }}</div>
              </div>
            </div>

            <!-- Contenido Scrolleable del Panel -->
            <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-3 text-xs">

              <!-- GRÁFICO 1: BARRA DE COBERTURA MINIMALISTA -->
              <div class="p-3 rounded-[14px] bg-white border border-black/[0.06] shadow-2xs space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-[#1D1D1F]">Distribución de Cobertura</span>
                  <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-md bg-[#0071E3]/10 text-[#0071E3]">
                    {{ totales.cobertura_pct }}% Cobertura
                  </span>
                </div>

                <!-- Barra Segmentada Proporcional -->
                <div class="h-3.5 w-full rounded-full bg-[#E5E5EA] overflow-hidden flex shadow-inner">
                  <div
                    :style="{ width: `${porcentajeConVenta}%` }"
                    class="bg-[#34C759] transition-all duration-300"
                    :title="`Con venta: ${totales.visitados_con_venta} (${porcentajeConVenta.toFixed(1)}%)`"
                  ></div>
                  <div
                    :style="{ width: `${porcentajeSinVenta}%` }"
                    class="bg-[#0071E3] transition-all duration-300"
                    :title="`Sin venta: ${totales.visitados_sin_venta} (${porcentajeSinVenta.toFixed(1)}%)`"
                  ></div>
                  <div
                    :style="{ width: `${porcentajePendiente}%` }"
                    class="bg-[#FF3B30] transition-all duration-300"
                    :title="`Pendientes: ${totales.pendientes} (${porcentajePendiente.toFixed(1)}%)`"
                  ></div>
                  <div
                    :style="{ width: `${porcentajeFueraPlan}%` }"
                    class="bg-[#FF9500] transition-all duration-300"
                    :title="`Fuera de plan: ${totales.fuera_plan} (${porcentajeFueraPlan.toFixed(1)}%)`"
                  ></div>
                </div>

                <!-- Leyenda Minimalista -->
                <div class="grid grid-cols-2 gap-1.5 pt-1 text-[10px] font-medium text-[#6E6E73]">
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#34C759] shrink-0"></span>
                    <span>Con Venta:</span>
                    <strong class="text-[#1D1D1F] font-mono ml-auto">{{ totales.visitados_con_venta }}</strong>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] shrink-0"></span>
                    <span>Sin Venta:</span>
                    <strong class="text-[#1D1D1F] font-mono ml-auto">{{ totales.visitados_sin_venta }}</strong>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#FF3B30] shrink-0"></span>
                    <span>Pendientes:</span>
                    <strong class="text-[#1D1D1F] font-mono ml-auto">{{ totales.pendientes }}</strong>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#FF9500] shrink-0"></span>
                    <span>Fuera de Plan:</span>
                    <strong class="text-[#1D1D1F] font-mono ml-auto">{{ totales.fuera_plan }}</strong>
                  </div>
                </div>

                <div class="pt-1.5 border-t border-black/[0.04] flex items-center justify-between text-[10px] text-[#86868B]">
                  <span>Venta Total Generada:</span>
                  <span class="font-bold font-mono text-[#1D1D1F]">Bs {{ totales.monto_total?.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}</span>
                </div>
              </div>

              <!-- GRÁFICO 2: HISTOGRAMA DE VISITAS POR HORA -->
              <div class="p-3 rounded-[14px] bg-white border border-black/[0.06] shadow-2xs space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-[#1D1D1F]">Ritmo Horario de Visitas</span>
                  <span class="text-[9.5px] text-[#86868B] font-mono">07:00 – 18:00+</span>
                </div>

                <!-- Canvas / SVG Bars -->
                <div class="relative h-28 w-full pt-2 flex items-end justify-between gap-1">
                  <div
                    v-for="(h, idx) in distribucionHoraria"
                    :key="h.rango"
                    class="flex-1 flex flex-col items-center h-full justify-end group relative cursor-pointer"
                  >
                    <!-- Tooltip flotante en hover -->
                    <div
                      class="absolute bottom-full mb-1 hidden group-hover:flex flex-col items-center z-20 pointer-events-none"
                    >
                      <div class="bg-slate-900 text-white text-[9px] font-mono px-2 py-1 rounded shadow-lg whitespace-nowrap">
                        <p class="font-bold">{{ h.rango }}</p>
                        <p class="text-emerald-400">Venta: {{ h.con_venta }}</p>
                        <p class="text-sky-300">Sin venta: {{ h.sin_venta }}</p>
                        <p class="border-t border-slate-700 mt-0.5 pt-0.5">Total: {{ h.total }}</p>
                      </div>
                    </div>

                    <!-- Barra Apilada -->
                    <div class="w-full max-w-[16px] rounded-t-[3px] overflow-hidden flex flex-col justify-end bg-slate-100 h-full">
                      <div
                        :style="{ height: `${maxVisitasHora > 0 ? (h.con_venta / maxVisitasHora) * 100 : 0}%` }"
                        class="bg-[#34C759] transition-all duration-200"
                      ></div>
                      <div
                        :style="{ height: `${maxVisitasHora > 0 ? (h.sin_venta / maxVisitasHora) * 100 : 0}%` }"
                        class="bg-[#0071E3] transition-all duration-200"
                      ></div>
                    </div>

                    <!-- Etiqueta de Hora -->
                    <span class="text-[8px] font-mono text-[#86868B] mt-1 shrink-0">
                      {{ formatoHoraEtiqueta(h.rango) }}
                    </span>
                  </div>
                </div>

                <div class="flex items-center justify-center gap-3 text-[9px] text-[#86868B] pt-1">
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-[#34C759]"></span> Visita con Venta
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span> Visita sin Venta
                  </span>
                </div>
              </div>

              <!-- CONTROLES DE CAPAS DEL MAPA -->
              <div class="p-3 rounded-[14px] bg-white border border-black/[0.06] shadow-2xs space-y-2">
                <span class="text-[11px] font-bold text-[#1D1D1F] block">Capas Visibles en el Mapa</span>
                
                <div class="grid grid-cols-2 gap-1.5 text-[10px]">
                  <!-- Toggle Con Venta -->
                  <button
                    type="button"
                    @click="capas.conVenta = !capas.conVenta"
                    class="px-2 py-1.5 rounded-lg border flex items-center justify-between transition-colors cursor-pointer"
                    :class="capas.conVenta ? 'bg-emerald-50 border-emerald-300 text-emerald-900 font-bold' : 'bg-gray-50 border-gray-200 text-gray-500'"
                  >
                    <span class="flex items-center gap-1">
                      <span class="w-2 h-2 rounded-full" :class="capas.conVenta ? 'bg-[#34C759]' : 'bg-gray-400'"></span>
                      <span>Con Venta</span>
                    </span>
                    <span class="font-mono">{{ totales.visitados_con_venta }}</span>
                  </button>

                  <!-- Toggle Sin Venta -->
                  <button
                    type="button"
                    @click="capas.sinVenta = !capas.sinVenta"
                    class="px-2 py-1.5 rounded-lg border flex items-center justify-between transition-colors cursor-pointer"
                    :class="capas.sinVenta ? 'bg-sky-50 border-sky-300 text-sky-900 font-bold' : 'bg-gray-50 border-gray-200 text-gray-500'"
                  >
                    <span class="flex items-center gap-1">
                      <span class="w-2 h-2 rounded-full" :class="capas.sinVenta ? 'bg-[#0071E3]' : 'bg-gray-400'"></span>
                      <span>Sin Venta</span>
                    </span>
                    <span class="font-mono">{{ totales.visitados_sin_venta }}</span>
                  </button>

                  <!-- Toggle Pendientes -->
                  <button
                    type="button"
                    @click="capas.pendientes = !capas.pendientes"
                    class="px-2 py-1.5 rounded-lg border flex items-center justify-between transition-colors cursor-pointer"
                    :class="capas.pendientes ? 'bg-red-50 border-red-300 text-red-900 font-bold' : 'bg-gray-50 border-gray-200 text-gray-500'"
                  >
                    <span class="flex items-center gap-1">
                      <span class="w-2 h-2 rounded-full" :class="capas.pendientes ? 'bg-[#FF3B30]' : 'bg-gray-400'"></span>
                      <span>Pendientes</span>
                    </span>
                    <span class="font-mono">{{ totales.pendientes }}</span>
                  </button>

                  <!-- Toggle Fuera de Plan -->
                  <button
                    type="button"
                    @click="capas.fueraPlan = !capas.fueraPlan"
                    class="px-2 py-1.5 rounded-lg border flex items-center justify-between transition-colors cursor-pointer"
                    :class="capas.fueraPlan ? 'bg-amber-50 border-amber-300 text-amber-900 font-bold' : 'bg-gray-50 border-gray-200 text-gray-500'"
                  >
                    <span class="flex items-center gap-1">
                      <span class="w-2 h-2 rounded-full" :class="capas.fueraPlan ? 'bg-[#FF9500]' : 'bg-gray-400'"></span>
                      <span>Fuera Plan</span>
                    </span>
                    <span class="font-mono">{{ totales.fuera_plan }}</span>
                  </button>
                </div>

                <!-- Toggle Línea de Recorrido -->
                <button
                  type="button"
                  @click="capas.recorrido = !capas.recorrido"
                  class="w-full px-2.5 py-1.5 rounded-lg border flex items-center justify-between transition-colors cursor-pointer text-[10px]"
                  :class="capas.recorrido ? 'bg-slate-900 border-slate-900 text-white font-bold' : 'bg-gray-50 border-gray-200 text-gray-600'"
                >
                  <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <span>Línea de Recorrido Cronológico</span>
                  </span>
                  <span class="font-mono text-[9px]">{{ recorridosVisibles.length }} rutas trazadas</span>
                </button>
              </div>

              <!-- SELECTOR / FILTRO DE RUTAS EN EL MAPA -->
              <div class="p-3 rounded-[14px] bg-white border border-black/[0.06] shadow-2xs space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-[#1D1D1F]">Rutas en el Mapa ({{ rutasStats.length }})</span>
                  <button
                    v-if="rutaEnfocada !== null"
                    type="button"
                    @click="rutaEnfocada = null"
                    class="text-[9.5px] font-bold text-[#0071E3] hover:underline cursor-pointer"
                  >
                    Mostrar Todas
                  </button>
                </div>

                <div class="max-h-48 overflow-y-auto custom-scroll space-y-1">
                  <!-- Botón Ver Todas -->
                  <button
                    type="button"
                    @click="rutaEnfocada = null"
                    class="w-full text-left px-2 py-1.5 rounded-lg flex items-center justify-between transition-colors cursor-pointer text-[11px]"
                    :class="rutaEnfocada === null ? 'bg-sky-50 border border-sky-300 text-sky-900 font-bold' : 'hover:bg-gray-50 text-gray-700'"
                  >
                    <span>Todas las Rutas ({{ clientesFiltrados.length }} clientes)</span>
                    <span v-if="rutaEnfocada === null" class="text-[#0071E3] font-bold">✓</span>
                  </button>

                  <!-- Rutas individuales -->
                  <button
                    v-for="r in rutasStats"
                    :key="r.ruta"
                    type="button"
                    @click="seleccionarRutaMapa(r.ruta)"
                    class="w-full text-left px-2 py-1 rounded-lg flex items-center justify-between transition-colors cursor-pointer text-[10.5px]"
                    :class="rutaEnfocada === r.ruta ? 'bg-sky-50 border border-sky-300 font-bold text-sky-900' : 'hover:bg-gray-50 text-gray-700'"
                  >
                    <div class="flex items-center gap-1.5 min-w-0">
                      <span
                        class="w-2.5 h-2.5 rounded-full shrink-0"
                        :style="{ backgroundColor: r.color }"
                      ></span>
                      <span class="font-bold truncate">{{ r.ruta }}</span>
                      <span class="text-[9px] text-gray-400 truncate">({{ r.vendedor }})</span>
                    </div>

                    <div class="flex items-center gap-1.5 font-mono text-[10px] shrink-0 ml-1">
                      <span class="text-emerald-700 font-bold">{{ r.total_visitados }}</span>
                      <span class="text-gray-400">/</span>
                      <span class="text-gray-600">{{ r.cartera_plan }}</span>
                      <span class="text-[9px] font-bold px-1 rounded bg-gray-100 text-gray-700">{{ r.cobertura_pct }}%</span>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Buscador Rápido de Clientes en Mapa -->
              <div class="p-2.5 rounded-[14px] bg-white border border-black/[0.06] shadow-2xs space-y-1.5">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block">Ubicar Cliente</span>
                <div class="relative">
                  <input
                    type="text"
                    v-model="busquedaCliente"
                    placeholder="Buscar por nombre o ID en el mapa..."
                    class="w-full h-7 px-2.5 bg-[#F5F5F7] border border-black/[0.06] rounded-lg text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3]"
                  />
                  <span
                    v-if="busquedaCliente"
                    @click="busquedaCliente = ''"
                    class="absolute right-2 top-1.5 text-gray-400 hover:text-gray-600 cursor-pointer text-xs"
                  >✕</span>
                </div>
              </div>

            </div>
          </aside>

          <!-- PANEL DERECHO: VISOR CARTOGRÁFICO INTERACTIVO LEAFLET -->
          <div class="flex-1 relative flex flex-col min-h-[450px] bg-slate-100 overflow-hidden">
            
            <!-- Barra Superior Flotante del Mapa -->
            <div class="absolute top-2 left-2 right-2 z-[400] flex items-center justify-between gap-2 pointer-events-none">
              
              <!-- Selector de Capas de Mapa (Calles / Satélite) -->
              <div class="pointer-events-auto flex items-center bg-white/95 backdrop-blur-md px-1.5 py-1 rounded-xl shadow-md border border-black/[0.08] text-[11px] gap-1">
                <button
                  type="button"
                  @click="cambiarCapaMapa('openStreetMap')"
                  class="px-2.5 py-1 rounded-lg transition-all cursor-pointer font-medium"
                  :class="capaMapaActiva === 'openStreetMap' ? 'bg-[#0071E3] text-white font-bold shadow-2xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  Calles
                </button>
                <button
                  type="button"
                  @click="cambiarCapaMapa('satellite')"
                  class="px-2.5 py-1 rounded-lg transition-all cursor-pointer font-medium"
                  :class="capaMapaActiva === 'satellite' ? 'bg-[#0071E3] text-white font-bold shadow-2xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  Satélite
                </button>
              </div>

              <!-- Controles de Vista y Estadísticas Rápidas -->
              <div class="pointer-events-auto flex items-center gap-1.5 bg-white/95 backdrop-blur-md px-2.5 py-1 rounded-xl shadow-md border border-black/[0.08] text-[11px]">
                
                <!-- Botón móvil para volver a gráficos -->
                <button
                  type="button"
                  @click="vistaMovil = 'panel'"
                  class="md:hidden px-2 py-0.5 rounded bg-gray-100 font-bold text-gray-700"
                >
                  Gráficos
                </button>

                <!-- Conteo de clientes en pantalla -->
                <span class="text-[10px] text-[#6E6E73] font-medium hidden sm:inline">
                  Puntos: <strong class="text-[#1D1D1F] font-mono">{{ puntosConCoordenadasVisibles.length }}</strong>
                  <span v-if="totales.total_sin_coords > 0" class="text-amber-600 font-mono text-[9px]">
                    ({{ totales.total_sin_coords }} sin GPS)
                  </span>
                </span>

                <div class="h-4 w-px bg-black/[0.08] hidden sm:block"></div>

                <!-- Botón Centrar Mapa -->
                <button
                  type="button"
                  @click="ajustarVistaMapa"
                  class="flex items-center gap-1 text-[10.5px] font-bold text-[#0071E3] hover:text-[#005bb5] transition-colors cursor-pointer"
                  title="Ajustar zoom para abarcar todos los puntos"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                  </svg>
                  <span>Centrar</span>
                </button>
              </div>
            </div>

            <!-- Contenedor del Mapa Leaflet -->
            <div id="mapa-pizarra-visual" ref="mapaContainerRef" class="flex-1 w-full h-full z-0"></div>

            <!-- Loader de Datos sobre el Mapa -->
            <div
              v-if="cargando"
              class="absolute inset-0 bg-white/60 backdrop-blur-xs z-[500] flex flex-col items-center justify-center gap-2 pointer-events-auto"
            >
              <div class="w-8 h-8 border-3 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
              <p class="text-xs font-bold text-[#1D1D1F]">Cargando georreferenciación de visitas...</p>
            </div>

            <!-- Leyenda Flotante Inferior -->
            <div
              class="absolute bottom-2 left-2 z-[400] bg-white/95 backdrop-blur-md px-3 py-1.5 rounded-xl shadow-md border border-black/[0.08] flex items-center gap-3 text-[10px] font-semibold text-[#6E6E73] select-none"
            >
              <div class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#34C759] border border-white shadow-2xs"></span>
                <span>Con Venta</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] border border-white shadow-2xs"></span>
                <span>Sin Venta</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#FF3B30] border border-white shadow-2xs"></span>
                <span>Pendiente</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-full bg-[#FF9500] border border-white shadow-2xs"></span>
                <span>Fuera de Plan</span>
              </div>
            </div>

          </div>

        </div>

        <!-- KPI 3: OBJETIVO DIARIO - BULLET CHARTS Y MIX FINANCIERO -->
        <ObjetivoVisualDashboard
          v-else-if="tabActivo === 'objetivo'"
          :fecha="store.fecha"
          :canales="store.filtrosAplicados?.canales?.length ? store.filtrosAplicados.canales : store.canalesSeleccionados"
          :rutas="store.filtrosAplicados?.rutas?.length ? store.filtrosAplicados.rutas : store.rutasSeleccionadas"
        />

        <!-- AVISO PARA OTROS KPIs EN DESARROLLO -->
        <div
          v-else
          class="flex-1 flex items-center justify-center p-6 text-center"
        >
          <div class="apple-glass-card max-w-md w-full p-8 rounded-2xl space-y-3 bg-white/90 shadow-lg border border-black/[0.06]">
            <div class="w-12 h-12 mx-auto rounded-full bg-sky-50 text-[#0071E3] flex items-center justify-center">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
              </svg>
            </div>
            <h4 class="text-base font-bold text-[#1D1D1F]">Módulo en Expansión Visual</h4>
            <p class="text-xs text-[#6E6E73] leading-relaxed">
              Actualmente están activos los módulos de <strong class="text-[#0071E3]">1. Horario (Gantt)</strong>, <strong class="text-[#0071E3]">2. Visitas (Mapa)</strong> y <strong class="text-[#0071E3]">3. Objetivo Diario (Bullet Chart)</strong>. Los siguientes KPIs se integrarán progresivamente.
            </p>
            <div class="flex items-center justify-center gap-2 pt-1 flex-wrap">
              <button
                type="button"
                @click="tabActivo = 'horario'"
                class="apple-control-pill px-3 py-1.5 rounded-xl text-xs font-bold text-[#1D1D1F] hover:bg-black/[0.05] transition-all cursor-pointer shadow-xs"
              >
                Ir a Horario (KPI 1)
              </button>
              <button
                type="button"
                @click="tabActivo = 'visitas'"
                class="apple-control-pill px-3 py-1.5 rounded-xl text-xs font-bold text-[#1D1D1F] hover:bg-black/[0.05] transition-all cursor-pointer shadow-xs"
              >
                Ir a Mapa Visitas (KPI 2)
              </button>
              <button
                type="button"
                @click="tabActivo = 'objetivo'"
                class="apple-control-pill px-3 py-1.5 rounded-xl text-xs font-bold text-[#0071E3] hover:bg-[#0071E3] hover:text-white transition-all cursor-pointer shadow-xs"
              >
                Ir a Objetivo (KPI 3)
              </button>
            </div>
          </div>
        </div>

      </main>

      <!-- MODAL DE EVIDENCIA FOTOGRÁFICA -->
      <ClienteFotosModal
        :show="fotoModalVisible"
        :cliente="clienteParaFoto"
        @close="fotoModalVisible = false"
      />

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import { usePreventaStore } from '@/Stores/preventaStore'
import PreventaFiltrosBar from './Cumplimiento/PreventaFiltrosBar.vue'
import ClienteFotosModal from './ClienteFotosModal.vue'
import HorarioVisualDashboard from './Horario/HorarioVisualDashboard.vue'
import ObjetivoVisualDashboard from './Objetivo/ObjetivoVisualDashboard.vue'
import { mapsConfig } from '@/Config/maps'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => []
  },
  canales: {
    type: Array,
    default: () => []
  },
  fecha_default: {
    type: String,
    default: ''
  }
})

const store = usePreventaStore()

// Pestañas de KPIs
const tabActivo = ref('horario')
const kpiTabs = [
  { id: 'general', label: 'Pizarra Unilever (7 KPIs)' },
  { id: 'horario', label: '1. Horario (10)', badge: 'GANTT' },
  { id: 'visitas', label: '2. Visitas (5)', badge: 'MAPA' },
  { id: 'objetivo', label: '3. Objetivo Diario (10)', badge: 'BULLET' },
  { id: 'efectividad', label: '4. Efectividad (30)' },
  { id: 'compra_minima', label: '5. Mín 70 (5)' },
  { id: 'reposicion', label: '6. Reposición (15)' },
  { id: 'ampliacion', label: '7. Ampliación (Pend.)' },
  { id: 'covers', label: '8. Covers (5)' },
]

// Estado general
const cargando = ref(false)
const vistaMovil = ref('panel') // 'panel' | 'mapa'
const busquedaCliente = ref('')
const rutaEnfocada = ref(null) // null = todas las rutas

// Modal Foto
const fotoModalVisible = ref(false)
const clienteParaFoto = ref(null)

// Capas activas en el mapa
const capas = reactive({
  conVenta: true,
  sinVenta: true,
  pendientes: true,
  fueraPlan: true,
  recorrido: true,
})

// Capa de mapa base (Calles vs Satélite)
const capaMapaActiva = ref('openStreetMap')

// Datos del backend
const totales = ref({
  total_cartera_plan: 0,
  visitados_con_venta: 0,
  visitados_sin_venta: 0,
  total_visitados: 0,
  pendientes: 0,
  fuera_plan: 0,
  monto_total: 0.0,
  total_con_coords: 0,
  total_sin_coords: 0,
  cobertura_pct: 0.0,
  efectividad_pct: 0.0,
})
const rutasStats = ref([])
const clientes = ref([])
const recorridos = ref([])
const distribucionHoraria = ref([])

// Referencia Leaflet
const mapaContainerRef = ref(null)
let mapInstance = null
let tileLayerActual = null
let markersGroup = null
let recorridosGroup = null

// Cálculos de Porcentajes para Gráfico de Cobertura
const porcentajeConVenta = computed(() => {
  const base = totales.value.total_cartera_plan || 1
  return Math.min(100, (totales.value.visitados_con_venta / base) * 100)
})

const porcentajeSinVenta = computed(() => {
  const base = totales.value.total_cartera_plan || 1
  return Math.min(100, (totales.value.visitados_sin_venta / base) * 100)
})

const porcentajePendiente = computed(() => {
  const base = totales.value.total_cartera_plan || 1
  return Math.min(100, (totales.value.pendientes / base) * 100)
})

const porcentajeFueraPlan = computed(() => {
  const base = totales.value.total_cartera_plan || 1
  return Math.min(50, (totales.value.fuera_plan / base) * 100)
})

// Máximo para escala del histograma horario
const maxVisitasHora = computed(() => {
  if (!distribucionHoraria.value.length) return 1
  const max = Math.max(...distribucionHoraria.value.map(h => h.total || 0))
  return max > 0 ? max : 1
})

function formatoHoraEtiqueta(rango) {
  if (!rango) return ''
  const partes = rango.split('-')
  return partes[0] ? partes[0].trim() : rango
}

// Filtro de Clientes en Mapa
const clientesFiltrados = computed(() => {
  let list = clientes.value || []

  // Filtro por ruta enfocada
  if (rutaEnfocada.value) {
    list = list.filter(c => c.ruta === rutaEnfocada.value)
  }

  // Filtro por capas de visibilidad
  list = list.filter(c => {
    if (c.estado_visita === 'visitado_con_venta' && !capas.conVenta) return false
    if (c.estado_visita === 'visitado_sin_venta' && !capas.sinVenta) return false
    if (c.estado_visita === 'pendiente' && !capas.pendientes) return false
    if (c.estado_visita === 'fuera_plan' && !capas.fueraPlan) return false
    return true
  })

  // Filtro por texto de búsqueda
  if (busquedaCliente.value.trim() !== '') {
    const q = busquedaCliente.value.trim().toLowerCase()
    list = list.filter(c =>
      (c.cliente && c.cliente.toLowerCase().includes(q)) ||
      (c.cliente_id && String(c.cliente_id).includes(q)) ||
      (c.direccion && c.direccion.toLowerCase().includes(q))
    )
  }

  return list
})

const puntosConCoordenadasVisibles = computed(() => {
  return clientesFiltrados.value.filter(c => c.tiene_coords)
})

const recorridosVisibles = computed(() => {
  if (!capas.recorrido) return []
  if (rutaEnfocada.value) {
    return recorridos.value.filter(r => r.ruta === rutaEnfocada.value)
  }
  return recorridos.value
})

const rutasEvaluadasCount = computed(() => {
  return rutasStats.value.length
})

// Inicialización del Mapa Leaflet
function inicializarMapa() {
  if (!mapaContainerRef.value || mapInstance) return

  const defaultLoc = mapsConfig.defaultLocation

  mapInstance = L.map(mapaContainerRef.value, {
    center: [defaultLoc.lat, defaultLoc.lng],
    zoom: defaultLoc.zoom,
    minZoom: defaultLoc.minZoom,
    maxZoom: defaultLoc.maxZoom,
    zoomControl: false,
  })

  // Controles de zoom abajo a la derecha
  L.control.zoom({ position: 'bottomright' }).addTo(mapInstance)

  // Capa base
  tileLayerActual = L.tileLayer(mapsConfig.tileLayers.openStreetMap.url, {
    attribution: mapsConfig.tileLayers.openStreetMap.attribution,
    maxZoom: 19,
  }).addTo(mapInstance)

  markersGroup = L.featureGroup().addTo(mapInstance)
  recorridosGroup = L.featureGroup().addTo(mapInstance)
}

function cambiarCapaMapa(tipo) {
  if (!mapInstance) return
  capaMapaActiva.value = tipo

  if (tileLayerActual) {
    mapInstance.removeLayer(tileLayerActual)
  }

  const layerConf = mapsConfig.tileLayers[tipo] || mapsConfig.tileLayers.openStreetMap
  tileLayerActual = L.tileLayer(layerConf.url, {
    attribution: layerConf.attribution,
    maxZoom: 19,
  }).addTo(mapInstance)
}

// Renderizado de Puntos y Recorridos en Leaflet
function renderizarCapaMapa() {
  if (!mapInstance || !markersGroup || !recorridosGroup) return

  mapInstance.invalidateSize()
  markersGroup.clearLayers()
  recorridosGroup.clearLayers()

  const bounds = []

  // 1. Dibujar Líneas de Recorrido Cronológico
  if (capas.recorrido) {
    recorridosVisibles.value.forEach(rec => {
      const latlngs = []
      rec.puntos.forEach(p => {
        latlngs.push([p.lat, p.lng])
        bounds.push([p.lat, p.lng])
      })

      if (latlngs.length > 1) {
        const poly = L.polyline(latlngs, {
          color: rec.color || '#0071E3',
          weight: 3,
          opacity: 0.85,
          dashArray: '6, 6',
          lineCap: 'round',
        })
        poly.bindTooltip(`${rec.ruta} (${rec.vendedor}) · ${latlngs.length} paradas`, { sticky: true })
        recorridosGroup.addLayer(poly)
      }
    })
  }

  // 2. Dibujar Marcadores de Clientes
  puntosConCoordenadasVisibles.value.forEach(c => {
    bounds.push([c.lat, c.lng])

    const esVisitado = (c.estado_visita !== 'pendiente')
    let colorFondo = '#FF3B30' // Pendiente (Rojo)
    let textoBadge = 'PENDIENTE'
    let badgeBg = '#FDF0EF'
    let badgeText = '#C9342C'
    let zIndex = 50

    if (c.estado_visita === 'visitado_con_venta') {
      colorFondo = '#34C759' // Verde
      textoBadge = 'CON VENTA'
      badgeBg = '#EBF9EF'
      badgeText = '#1E6B37'
      zIndex = 200
    } else if (c.estado_visita === 'visitado_sin_venta') {
      colorFondo = '#0071E3' // Azul
      textoBadge = 'SIN VENTA'
      badgeBg = '#EBF4FE'
      badgeText = '#0071E3'
      zIndex = 150
    } else if (c.estado_visita === 'fuera_plan') {
      colorFondo = '#FF9500' // Naranja
      textoBadge = 'FUERA DE PLAN'
      badgeBg = '#FFF5E5'
      badgeText = '#B25E00'
      zIndex = 180
    }

    let htmlMarker = ''
    let iconSize = [12, 12]
    let iconAnchor = [6, 6]

    if (esVisitado) {
      iconSize = [18, 18]
      iconAnchor = [9, 9]
      const numLabel = c.parada_num ? String(c.parada_num) : ''
      htmlMarker = `
        <div style="width: 18px; height: 18px; border-radius: 50%; background-color: ${colorFondo}; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; color: white; font-size: ${numLabel.length > 2 ? '7.5px' : '9px'}; font-weight: 800; font-family: -apple-system, sans-serif;">
          ${numLabel}
        </div>
      `
    } else {
      iconSize = [10, 10]
      iconAnchor = [5, 5]
      htmlMarker = `
        <div style="width: 10px; height: 10px; border-radius: 50%; background-color: ${colorFondo}; border: 1.5px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.25);"></div>
      `
    }

    const iconPin = L.divIcon({
      className: 'custom-client-marker',
      html: htmlMarker,
      iconSize: iconSize,
      iconAnchor: iconAnchor,
    })

    const marker = L.marker([c.lat, c.lng], { icon: iconPin, zIndexOffset: zIndex })

    // Popup interactivo con estética Apple
    const popupHtml = `
      <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; min-width: 220px; max-width: 260px; line-height: 1.35; padding: 2px;">
        <!-- Cabecera -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 4px;">
          <div style="display: flex; align-items: center; gap: 4px;">
            ${c.parada_num ? `<span style="font-size: 9px; font-weight: 800; padding: 2px 5px; border-radius: 4px; background-color: #1D1D1F; color: white;">#${c.parada_num}</span>` : ''}
            <span style="font-size: 9px; font-weight: 800; padding: 2px 5px; border-radius: 4px; background-color: ${badgeBg}; color: ${badgeText};">
              ${textoBadge}
            </span>
          </div>
          <span style="font-size: 9px; font-family: monospace; font-weight: 700; color: #86868B;">
            Cód: ${c.cliente_id || 'S/N'}
          </span>
        </div>

        <h4 style="font-size: 12px; font-weight: 800; color: #1D1D1F; margin: 0 0 3px 0; word-break: break-word;">
          ${c.cliente}
        </h4>

        <!-- Pastilla de Ruta y Vendedor -->
        <div style="margin-bottom: 6px; padding: 3px 6px; border-radius: 6px; background-color: #F5F5F7; border: 1px solid rgba(0,0,0,0.04); font-size: 10px;">
          <div style="display: flex; align-items: center; gap: 4px;">
            <span style="width: 7px; height: 7px; border-radius: 50%; background-color: ${c.color_ruta}; shrink: 0;"></span>
            <strong style="color: #1D1D1F;">${c.ruta}</strong>
            <span style="color: #86868B;">(${c.canal})</span>
          </div>
          <div style="color: #6E6E73; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            ${c.vendedor}
          </div>
        </div>

        <!-- Detalles de Visita -->
        <div style="display: flex; flex-direction: column; gap: 3px; font-size: 10px; color: #6E6E73; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 4px;">
          <div><strong>Motivo:</strong> ${c.tipo_motivo || 'Visita'}</div>
          ${c.hora_visita ? `<div><strong>Hora:</strong> <span style="font-family: monospace; font-weight: 700; color: #1D1D1F;">${c.hora_visita}</span></div>` : ''}
          ${c.monto > 0 ? `<div><strong>Preventa:</strong> <span style="font-family: monospace; font-weight: 800; color: #1E6B37;">Bs ${c.monto.toFixed(2)}</span></div>` : ''}
          <div style="font-size: 9.5px; color: #86868B; margin-top: 2px;">${c.direccion || '-'}</div>
        </div>

        ${c.foto_url ? `
          <div style="margin-top: 6px; text-align: center;">
            <button
              id="btn-ver-foto-${c.id}"
              style="width: 100%; padding: 4px 8px; border-radius: 6px; background-color: #0071E3; color: white; font-weight: 700; font-size: 10px; border: none; cursor: pointer;"
            >
              Ver Foto en Terreno
            </button>
          </div>
        ` : ''}
      </div>
    `

    marker.bindPopup(popupHtml)

    marker.on('popupopen', () => {
      if (c.foto_url) {
        nextTick(() => {
          const btn = document.getElementById(`btn-ver-foto-${c.id}`)
          if (btn) {
            btn.onclick = () => {
              abrirModalFoto(c)
            }
          }
        })
      }
    })

    markersGroup.addLayer(marker)
  })

  // Ajustar bounds si hay puntos y no se ha fijado zoom manual reciente
  if (bounds.length > 0 && mapInstance) {
    mapInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
  }
}

function ajustarVistaMapa() {
  if (!mapInstance || !markersGroup) return
  const layers = markersGroup.getLayers()
  if (layers.length === 0) {
    mapInstance.setView([mapsConfig.defaultLocation.lat, mapsConfig.defaultLocation.lng], mapsConfig.defaultLocation.zoom)
    return
  }
  const group = L.featureGroup(layers)
  mapInstance.fitBounds(group.getBounds(), { padding: [40, 40], maxZoom: 16 })
}

function seleccionarRutaMapa(ruta) {
  rutaEnfocada.value = (rutaEnfocada.value === ruta) ? null : ruta
}

function abrirModalFoto(cliente) {
  clienteParaFoto.value = {
    id: cliente.cliente_id,
    cliente_id: cliente.cliente_id,
    cliente: cliente.cliente,
    direccion: cliente.direccion,
    foto_url: cliente.foto_url,
  }
  fotoModalVisible.value = true
}

// Carga de Datos desde el Backend
async function cargarDatos() {
  cargando.value = true
  try {
    const canales = (store.filtrosAplicados?.canales && store.filtrosAplicados.canales.length > 0)
      ? store.filtrosAplicados.canales
      : (store.canalesSeleccionados || [])
    const rutas = (store.filtrosAplicados?.rutas && store.filtrosAplicados.rutas.length > 0)
      ? store.filtrosAplicados.rutas
      : (store.rutasSeleccionadas || [])

    const res = await axios.post(route('supervisor.preventas.pizarra-visual.visitas-data'), {
      fecha: store.fecha,
      canales,
      rutas,
    })

    const d = res.data || {}
    totales.value = d.totales || totales.value
    rutasStats.value = d.rutas_stats || []
    clientes.value = d.clientes || []
    recorridos.value = d.recorridos || []
    distribucionHoraria.value = d.distribucion_horaria || []

    await nextTick()
    renderizarCapaMapa()
  } catch (error) {
    console.error('[PizarraVisual] Error cargando visitas:', error)
  } finally {
    cargando.value = false
  }
}

async function onConsultar() {
  store.aplicarFiltros()
  if (tabActivo.value === 'visitas') {
    await cargarDatos()
  }
}

// Reactividad de Filtros del Mapa
watch(
  [
    () => capas.conVenta,
    () => capas.sinVenta,
    () => capas.pendientes,
    () => capas.fueraPlan,
    () => capas.recorrido,
    () => rutaEnfocada.value,
    () => busquedaCliente.value,
  ],
  () => {
    if (tabActivo.value === 'visitas') {
      renderizarCapaMapa()
    }
  }
)

watch(tabActivo, (tab) => {
  if (tab === 'visitas') {
    nextTick(async () => {
      if (!mapInstance) {
        inicializarMapa()
      } else {
        mapInstance.invalidateSize()
      }
      await cargarDatos()
    })
  }
})

watch(vistaMovil, (val) => {
  if (val === 'mapa' && tabActivo.value === 'visitas') {
    nextTick(() => {
      if (mapInstance) {
        mapInstance.invalidateSize()
        ajustarVistaMapa()
      }
    })
  }
})

onMounted(async () => {
  store.inicializarDesdeStorage(props)
  await nextTick()
  if (tabActivo.value === 'visitas') {
    inicializarMapa()
    await cargarDatos()
  }
})

onUnmounted(() => {
  if (mapInstance) {
    mapInstance.remove()
    mapInstance = null
  }
})
</script>

<style scoped>
:deep(.custom-client-marker),
:deep(.custom-recorrido-stop) {
  background: transparent !important;
  border: none !important;
}

:deep(.leaflet-popup-content-wrapper) {
  border-radius: 14px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  padding: 4px;
  border: 1px solid rgba(0, 0, 0, 0.08);
}
:deep(.leaflet-popup-content) {
  margin: 6px 8px;
}
</style>
