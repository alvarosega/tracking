<template>
  <SupervisorLayout full-bleed title="Saneamiento | Visor & Fronteras">
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900 text-slate-900 font-sans">
      <!-- Navegación de Submódulos Saneamiento Padrón -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 shrink-0 relative z-30">
        <AuditoriaSaneamientoSubTabs active-tab="visor" :compact="true" />
      </div>

      <!-- ========================================== -->
      <!-- MOBILE HEADER (< md): MULTI-FILAS RESPONSIVAS -->
      <!-- ========================================== -->
      <div class="flex md:hidden flex-col gap-2 p-2 sm:p-2.5 bg-white/95 backdrop-blur-md border-b border-slate-200/90 shrink-0 relative z-30">
        <!-- Backdrop invisible para cerrar menú al hacer click afuera -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Fila 1: [ Visualización | Ajuste RTM ] + [ Original | Con Cambios ] + [ Mapa | Panel ] -->
        <div class="flex items-center justify-between gap-1.5 flex-wrap">
          <!-- Modos Principales Móvil -->
          <div class="inline-flex p-0.5 bg-slate-200 rounded-lg border border-slate-300 shrink-0">
            <button
              type="button"
              @click="setModoPrincipal('visualizacion')"
              class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all flex items-center cursor-pointer"
              :class="modoPrincipal === 'visualizacion' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
            >
              <span>Visualización</span>
            </button>
            <button
              type="button"
              @click="setModoPrincipal('ajuste_rtm')"
              class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all flex items-center cursor-pointer"
              :class="modoPrincipal === 'ajuste_rtm' ? 'bg-slate-900 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
            >
              <span>Ajuste RTM</span>
            </button>
          </div>

          <!-- Sub-modos Visualización Móvil -->
          <div v-if="modoPrincipal === 'visualizacion'" class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shrink-0">
            <button
              type="button"
              @click="setSubModoVisualizacion('original')"
              class="px-2 py-0.5 text-[10px] font-semibold rounded-md transition-all cursor-pointer"
              :class="subModoVisualizacion === 'original' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
            >
              Original
            </button>
            <button
              type="button"
              @click="setSubModoVisualizacion('cambios')"
              class="px-2 py-0.5 text-[10px] font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1"
              :class="subModoVisualizacion === 'cambios' ? 'bg-slate-900 text-white shadow-2xs font-bold' : 'text-slate-500'"
            >
              <span>Con Cambios</span>
              <span v-if="(kpis.total_propuestas || kpis.con_cambios) > 0" class="px-1 py-0.2 rounded-full text-[8.5px] font-mono bg-white/20 font-bold">
                {{ kpis.total_propuestas || kpis.con_cambios }}
              </span>
            </button>
          </div>

          <!-- Selector Vista Mapa / Panel -->
          <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shrink-0 ml-auto">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer"
              :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'lista'"
              class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer"
              :class="vistaMovil === 'lista' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              {{ modoPrincipal === 'ajuste_rtm' ? 'Balance' : `Lista (${clientes.length})` }}
            </button>
          </div>
        </div>

        <!-- Fila 2: Filtros de Canal, Rutas, Fronteras, Días, Meses y Botón Consultar (WRAP limpio, sin scroll horizontal) -->
        <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
          <!-- Filtro Dropdown: Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-7 sm:h-8 px-2 sm:px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
          >
            <span class="text-slate-500 font-normal">Canal:</span>
            <span class="font-bold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Rutas (Puntos) -->
          <button
            type="button"
            @click.stop="toggleMenu('puntos_rutas', $event)"
            class="h-7 sm:h-8 px-2 sm:px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200 font-semibold border-slate-300': filtroRutas.length > 0 }"
          >
            <span class="text-slate-500 font-normal">Rutas:</span>
            <span class="font-bold text-slate-900">{{ labelRutasPuntos }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Frontera Ruta (Móvil) -->
          <button
            type="button"
            @click.stop="toggleMenu('fronteras_rutas', $event)"
            class="h-7 sm:h-8 px-2 sm:px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200 font-semibold border-slate-300': filtroFronterasRutas.length > 0 }"
          >
            <span class="text-slate-500 font-normal">F. Ruta:</span>
            <span class="font-bold text-slate-900">{{ labelFronterasRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Frontera Día (Móvil) -->
          <button
            type="button"
            @click.stop="toggleMenu('fronteras_dias', $event)"
            class="h-7 sm:h-8 px-2 sm:px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200 font-semibold border-slate-300': filtroFronterasDias.length > 0 }"
          >
            <span class="text-slate-500 font-normal">F. Día:</span>
            <span class="font-bold text-slate-900">{{ labelFronterasDias }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Días de Visita (Móvil) -->
          <button
            type="button"
            @click.stop="toggleMenu('dias', $event)"
            class="h-7 sm:h-8 px-2 sm:px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200 font-semibold border-slate-300': filtroDias.length < diasSemana.length }"
            title="Filtrar por días de visita"
          >
            <span class="text-slate-500 font-normal">Días:</span>
            <span class="font-bold text-slate-900">{{ labelDias }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Meses Históricos (Móvil) -->
          <button
            type="button"
            @click.stop="toggleMenu('meses_historicos', $event)"
            class="h-7 sm:h-8 px-2 sm:px-2.5 bg-slate-100 hover:bg-slate-200 border rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
            :class="menuAbierto === 'meses_historicos' ? 'border-sky-500 bg-sky-50 text-sky-900 font-semibold' : 'border-slate-200/90'"
            title="Seleccionar meses históricos a visualizar"
          >
            <span class="text-slate-500 font-normal">Meses:</span>
            <span class="font-bold text-slate-900 font-mono">{{ mesesHistoricosSeleccionados.length }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- BOTÓN CONSULTAR (Móvil) -->
          <button
            type="button"
            @click="cargarDatos"
            :disabled="cargando"
            class="h-7 sm:h-8 px-3.5 bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 select-none shrink-0"
            title="Consultar puntos y fronteras de rutas seleccionadas"
          >
            <svg v-if="!cargando" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <span v-else class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ cargando ? 'Cargando...' : 'Consultar' }}</span>
          </button>
        </div>

        <!-- Fila 3: Configuración Visual, Filtro de Estado y KPIs (WRAP limpio) -->
        <div class="flex items-center gap-1.5 flex-wrap pt-1 border-t border-slate-100">
          <!-- Selector de Modo de Coloración (Móvil) -->
          <div class="inline-flex p-0.5 bg-slate-100 border border-slate-200 rounded-lg shrink-0 text-xs font-medium select-none shadow-2xs">
            <span class="text-[10px] font-mono text-slate-400 px-1.5 self-center">Color:</span>
            <button
              type="button"
              @click="setModoColor('ruta')"
              class="px-2 py-0.5 rounded-md transition-all cursor-pointer text-[10px]"
              :class="modoColor === 'ruta' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
            >
              Ruta
            </button>
            <button
              type="button"
              @click="setModoColor('dia')"
              class="px-2 py-0.5 rounded-md transition-all cursor-pointer text-[10px]"
              :class="modoColor === 'dia' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
            >
              Día
            </button>
            <button
              type="button"
              @click="setModoColor('canal')"
              class="px-2 py-0.5 rounded-md transition-all cursor-pointer text-[10px]"
              :class="modoColor === 'canal' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
            >
              Canal
            </button>
          </div>

          <!-- Filtro de Estado Móvil: [ Activos | Inactivos | Todos ] -->
          <div class="inline-flex p-0.5 bg-slate-100 border border-slate-200 rounded-lg shrink-0 text-xs font-medium select-none shadow-2xs">
            <button
              type="button"
              @click="setFiltroEstadoCliente('activos')"
              class="px-2 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-1 text-[11px]"
              :class="filtroEstadoCliente === 'activos' ? 'bg-white text-emerald-800 shadow-2xs font-bold' : 'text-slate-600'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Activos</span>
            </button>
            <button
              type="button"
              @click="setFiltroEstadoCliente('inactivos')"
              class="px-2 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-1 text-[11px]"
              :class="filtroEstadoCliente === 'inactivos' ? 'bg-white text-rose-700 shadow-2xs font-bold' : 'text-slate-600'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              <span>Inactivos</span>
            </button>
            <button
              type="button"
              @click="setFiltroEstadoCliente('todos')"
              class="px-2 py-0.5 rounded-md transition-all cursor-pointer flex items-center gap-1 text-[11px]"
              :class="filtroEstadoCliente === 'todos' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
              <span>Todos</span>
            </button>
          </div>

          <!-- KPIs Móvil resumidos -->
          <div class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded-md flex items-center gap-1.5 text-[10.5px] font-mono text-slate-800 shrink-0">
            <span class="font-semibold">{{ kpis.total }} tot</span>
            <span class="text-slate-400">•</span>
            <span class="font-semibold text-slate-700">{{ kpis.con_gps }} GPS</span>
            <template v-if="kpis.inactivos > 0">
              <span class="text-slate-400">•</span>
              <span @click="setFiltroEstadoCliente('inactivos')" class="font-bold text-rose-700 cursor-pointer underline decoration-rose-300">{{ kpis.inactivos }} inac</span>
            </template>
            <template v-if="kpis.con_cambios > 0">
              <span class="text-slate-400">•</span>
              <span class="font-bold text-slate-900">{{ kpis.con_cambios }} camb</span>
            </template>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- DESKTOP HEADER (>= md): 2 FILAS ESTRUCTURADAS -->
      <!-- ========================================== -->
      <div class="hidden md:flex flex-col gap-2 bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-4 py-2 shrink-0 relative z-30">
        <!-- Backdrop invisible para cerrar menú al hacer click afuera -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- FILA 1: Modos Principales, Submodos, Botón Consultar y Métricas/KPIs -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Modos Principales Desktop: [ Visualización | Ajuste RTM ] -->
            <div class="inline-flex p-0.5 bg-slate-200 rounded-lg border border-slate-300 shrink-0">
              <button
                type="button"
                @click="setModoPrincipal('visualizacion')"
                class="px-3 py-1 text-xs font-semibold rounded-md transition-all flex items-center cursor-pointer"
                :class="modoPrincipal === 'visualizacion' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                title="Modo Visualización: Consulta limpia y comparativa del padrón"
              >
                <span>Visualización</span>
              </button>
              <button
                type="button"
                @click="setModoPrincipal('ajuste_rtm')"
                class="px-3 py-1 text-xs font-semibold rounded-md transition-all flex items-center cursor-pointer"
                :class="modoPrincipal === 'ajuste_rtm' ? 'bg-slate-900 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                title="Modo Ajuste RTM: Mover fronteras territoriales y reasignar rutas o días"
              >
                <span>Ajuste RTM</span>
              </button>
            </div>

            <!-- Sub-modos Visualización Desktop: [ Base Original | Con Cambios (N) ] -->
            <div v-if="modoPrincipal === 'visualizacion'" class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shrink-0">
              <button
                type="button"
                @click="setSubModoVisualizacion('original')"
                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1"
                :class="subModoVisualizacion === 'original' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800'"
                title="Ver datos originales inmutables de pan_ruteo"
              >
                <span>Base Original</span>
              </button>
              <button
                type="button"
                @click="setSubModoVisualizacion('cambios')"
                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1.5"
                :class="subModoVisualizacion === 'cambios' ? 'bg-slate-900 text-white shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800'"
                title="Ver padrón integrando propuestas de saneamiento registradas"
              >
                <span>Con Cambios</span>
                <span v-if="(kpis.total_propuestas || kpis.con_cambios) > 0" class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20 font-bold">
                  {{ kpis.total_propuestas || kpis.con_cambios }}
                </span>
              </button>
            </div>

            <div class="h-5 w-px bg-slate-200 shrink-0"></div>

            <!-- BOTÓN CONSULTAR (Desktop) -->
            <button
              type="button"
              @click="cargarDatos"
              :disabled="cargando"
              class="h-8 px-4 bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 select-none shrink-0"
              title="Consultar puntos y fronteras de rutas seleccionadas"
            >
              <svg v-if="!cargando" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <span v-else class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ cargando ? 'Consultando...' : 'Consultar' }}</span>
            </button>
          </div>

          <!-- Métricas y KPIs en Desktop (a la derecha de la fila 1) -->
          <div class="flex items-center space-x-2 text-xs font-mono shrink-0 flex-wrap">
            <div class="px-2 py-0.5 bg-slate-50 border border-slate-200 rounded-md flex items-center gap-1.5 shadow-2xs">
              <span class="text-slate-400">Total:</span>
              <span class="font-bold text-slate-900 tabular-nums">{{ kpis.total }}</span>
            </div>

            <div class="flex px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-md items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
              <span class="font-semibold tabular-nums">{{ kpis.con_gps }} GPS</span>
            </div>

            <div
              v-if="kpis.inactivos > 0"
              @click="setFiltroEstadoCliente('inactivos')"
              class="flex px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-md items-center gap-1.5 font-bold cursor-pointer hover:bg-rose-100 transition-colors shadow-2xs"
              title="Clic para ver únicamente clientes inactivos"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              <span class="tabular-nums">{{ kpis.inactivos }} Inactivos</span>
            </div>

            <div v-if="(kpis.total_propuestas || kpis.con_cambios) > 0" class="flex px-2 py-0.5 bg-slate-100 text-slate-900 border border-slate-300 rounded-md items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
              <span class="font-bold tabular-nums">
                {{ modoPrincipal === 'visualizacion' && subModoVisualizacion === 'original' ? `${kpis.total_propuestas || kpis.con_cambios} propuestas` : `${kpis.con_cambios} cambios aplicados` }}
              </span>
            </div>

            <div v-if="kpis.sin_gps > 0" class="flex px-2 py-0.5 bg-slate-50 text-slate-500 border border-slate-200 rounded-md items-center gap-1.5">
              <span class="font-medium tabular-nums">{{ kpis.sin_gps }} sin GPS</span>
            </div>
          </div>
        </div>

        <!-- FILA 2: Todos los Filtros de Segmentación, Capas GIS, Modo de Color y Estado (SIN SCROLL HORIZONTAL, TODO VISIBLE) -->
        <div class="flex items-center gap-2 flex-wrap text-xs pt-1 border-t border-slate-100">
          <!-- Filtro Dropdown: Canales (Teleport al Body) -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0 shadow-2xs"
          >
            <span class="text-slate-400 font-normal">Canal:</span>
            <span class="font-bold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Segmentado: Días de Visita -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0 shadow-2xs">
            <button
              v-for="d in diasSemana"
              :key="d.key"
              type="button"
              @click="toggleDia(d.key)"
              class="px-2 py-1 text-[11px] font-medium rounded-md transition-all flex items-center gap-1 cursor-pointer select-none"
              :class="filtroDias.includes(d.key) ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
              :title="d.label"
            >
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: d.color }"></span>
              {{ d.corto }}
            </button>
            <button type="button" @click="toggleTodosDias" class="px-1.5 py-1 text-[10px] text-slate-500 hover:text-slate-900 font-mono border-l border-slate-200 ml-0.5 cursor-pointer">
              {{ filtroDias.length === diasSemana.length ? 'Reset' : 'Todos' }}
            </button>
          </div>

          <div class="h-5 w-px bg-slate-200 shrink-0"></div>

          <!-- Filtros Dinámicos GIS: Puntos (Rutas), Frontera Ruta y Frontera Día -->
          <!-- Botón Dropdown Dinámico: Rutas (Puntos) -->
          <button
            type="button"
            @click.stop="toggleMenu('puntos_rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200/80 border-slate-300 font-semibold': filtroRutas.length > 0 }"
            title="Mostrar u ocultar puntos de clientes por ruta en tiempo real"
          >
            <span class="text-slate-400 font-normal">Rutas:</span>
            <span class="font-bold text-slate-900">{{ labelRutasPuntos }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Botón Dropdown Frontera Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('fronteras_rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200/80 border-slate-300 font-semibold': filtroFronterasRutas.length > 0 }"
            title="Filtrar polígonos de frontera de ruta por ruta"
          >
            <span class="text-slate-400 font-normal">Frontera Ruta:</span>
            <span class="font-bold text-slate-900">{{ labelFronterasRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Botón Dropdown Frontera Días -->
          <button
            type="button"
            @click.stop="toggleMenu('fronteras_dias', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0 shadow-2xs"
            :class="{ 'bg-slate-200/80 border-slate-300 font-semibold': filtroFronterasDias.length > 0 }"
            title="Filtrar polígonos de frontera de día por ruta"
          >
            <span class="text-slate-400 font-normal">Frontera Día:</span>
            <span class="font-bold text-slate-900">{{ labelFronterasDias }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <div class="h-5 w-px bg-slate-200 shrink-0"></div>

          <!-- Selector de Modo de Coloración -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0 shadow-2xs">
            <span class="text-[10px] font-mono text-slate-400 px-1.5">Color por:</span>
            <button
              type="button"
              @click="setModoColor('ruta')"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'ruta' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Ruta
            </button>
            <button
              type="button"
              @click="setModoColor('dia')"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'dia' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Día
            </button>
            <button
              type="button"
              @click="setModoColor('canal')"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'canal' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Canal
            </button>
          </div>

          <!-- Filtro de Estado: [ Activos | Inactivos | Todos ] -->
          <div class="inline-flex p-0.5 bg-slate-100 border border-slate-200 rounded-lg shrink-0 text-xs font-semibold select-none shadow-2xs">
            <button
              type="button"
              @click="setFiltroEstadoCliente('activos')"
              class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroEstadoCliente === 'activos' ? 'bg-white text-emerald-800 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
              title="Mostrar únicamente clientes Activos"
            >
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Activos</span>
            </button>
            <button
              type="button"
              @click="setFiltroEstadoCliente('inactivos')"
              class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroEstadoCliente === 'inactivos' ? 'bg-white text-rose-700 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
              title="Mostrar únicamente clientes Inactivos"
            >
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              <span>Inactivos</span>
            </button>
            <button
              type="button"
              @click="setFiltroEstadoCliente('todos')"
              class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroEstadoCliente === 'todos' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
              title="Mostrar todos los clientes (activos e inactivos)"
            >
              <span class="w-2 h-2 rounded-full bg-slate-400"></span>
              <span>Todos</span>
            </button>
          </div>

          <!-- Filtro Dropdown: Meses Históricos (Desktop) -->
          <button
            type="button"
            @click.stop="toggleMenu('meses_historicos', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0 shadow-2xs"
            :class="menuAbierto === 'meses_historicos' ? 'border-sky-500 bg-sky-50 text-sky-900 font-semibold' : 'border-slate-200/90'"
            title="Seleccionar meses históricos a visualizar"
          >
            <span class="text-slate-400 font-normal">Meses:</span>
            <span class="font-bold text-slate-900 font-mono">{{ mesesHistoricosSeleccionados.length }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Menús Desplegables Flotantes con Teleport -->
      <Teleport to="body">
        <!-- Menú Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-56 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarCanales" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <div class="max-h-48 overflow-y-auto space-y-1">
            <label v-for="c in canales" :key="c" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="c" v-model="filtroCanales" @change="onCanalesChange" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
              <span class="truncate font-medium text-slate-700">{{ c }}</span>
            </label>
          </div>
          <div class="pt-2 mt-1.5 border-t border-slate-100 flex justify-end">
            <button
              type="button"
              @click="menuAbierto = null; cargarDatos();"
              class="w-full py-1 bg-sky-600 hover:bg-sky-500 text-white rounded text-[11px] font-bold cursor-pointer transition-colors shadow-2xs"
            >
              Consultar
            </button>
          </div>
        </div>

        <!-- Menú Días de Visita (Móvil y Pantallas Pequeñas) -->
        <div
          v-if="menuAbierto === 'dias'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-60 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans max-h-[75vh] flex flex-col"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100 shrink-0">
            <span class="font-bold text-[11px] text-slate-700 uppercase tracking-tight">Días de Visita</span>
            <div class="flex items-center gap-2">
              <button type="button" @click="filtroDias = diasSemana.map(d => d.key); renderizarEnMapa(true)" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todos</button>
              <button type="button" @click="filtroDias = []; renderizarEnMapa(true)" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
            </div>
          </div>
          <div class="overflow-y-auto space-y-1 py-0.5 custom-scroll flex-1">
            <label
              v-for="d in diasSemana"
              :key="d.key"
              class="flex items-center justify-between px-2 py-1.5 hover:bg-slate-50 rounded-lg cursor-pointer select-none"
            >
              <div class="flex items-center gap-2">
                <input
                  type="checkbox"
                  :value="d.key"
                  v-model="filtroDias"
                  @change="renderizarEnMapa(true)"
                  class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer"
                />
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: d.color }"></span>
                <span class="font-medium text-slate-800">{{ d.label }}</span>
              </div>
              <span class="text-[10px] font-mono text-slate-400 uppercase font-bold">{{ d.corto }}</span>
            </label>
          </div>
          <div class="pt-2 mt-1.5 border-t border-slate-100 flex justify-end shrink-0">
            <button
              type="button"
              @click="menuAbierto = null"
              class="w-full py-1 bg-slate-900 hover:bg-slate-800 text-white rounded text-[11px] font-bold cursor-pointer transition-colors shadow-2xs"
            >
              Listo
            </button>
          </div>
        </div>

        <!-- Menú Dinámico: Puntos por Ruta -->
        <div
          v-if="menuAbierto === 'puntos_rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <span class="font-bold text-[11px] text-slate-700 uppercase tracking-tight">Rutas (Puntos)</span>
            <div class="flex items-center gap-2">
              <button type="button" @click="seleccionarTodasRutasPuntos" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todos</button>
              <button type="button" @click="limpiarRutasPuntos" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
            </div>
          </div>
          <input
            v-if="rutasDisponiblesParaFronteras.length > 5"
            v-model="busquedaPuntosRuta"
            type="text"
            placeholder="Buscar ruta..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <div v-if="rutasFiltradasEnDropdownPuntos.length === 0" class="py-2 text-center text-slate-400 text-[11px]">
              No hay rutas disponibles
            </div>
            <label
              v-for="r in rutasFiltradasEnDropdownPuntos"
              :key="r.ruta"
              class="flex items-center justify-between gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none"
            >
              <div class="flex items-center gap-2 truncate">
                <input
                  type="checkbox"
                  :value="r.ruta"
                  v-model="filtroRutas"
                  @change="renderizarEnMapa(true)"
                  class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer"
                />
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: r.color }"></span>
                <span class="font-mono font-semibold text-slate-800 truncate">{{ r.nombre }}</span>
                <span v-if="r.vendedor" class="text-[9.5px] text-slate-400 truncate max-w-[85px]" :title="r.vendedor">({{ r.vendedor }})</span>
              </div>
              <span
                class="text-[9.5px] font-mono px-1.5 py-0.2 rounded shrink-0"
                :class="(conteoPuntosPorRuta[r.ruta] || 0) > 0 ? 'bg-slate-100 text-slate-700 font-bold' : 'bg-slate-50 text-slate-400 font-normal'"
              >
                {{ conteoPuntosPorRuta[r.ruta] || 0 }} pts
              </span>
            </label>
          </div>
        </div>

        <!-- Menú Fronteras Rutas (por ruta) -->
        <div
          v-if="menuAbierto === 'fronteras_rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <span class="font-bold text-[11px] text-slate-700 uppercase tracking-tight">Frontera Ruta</span>
            <div class="flex items-center gap-2">
              <button type="button" @click="seleccionarTodasFronterasRutas" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todas</button>
              <button type="button" @click="limpiarFronterasRutas" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
            </div>
          </div>
          <input
            v-if="rutasDisponiblesParaFronteras.length > 5"
            v-model="busquedaFronteraRuta"
            type="text"
            placeholder="Buscar ruta..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <div v-if="rutasFiltradasEnDropdownFronteraRuta.length === 0" class="py-2 text-center text-slate-400 text-[11px]">
              No hay rutas disponibles
            </div>
            <label
              v-for="r in rutasFiltradasEnDropdownFronteraRuta"
              :key="r.ruta"
              class="flex items-center justify-between gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none"
            >
              <div class="flex items-center gap-2 truncate">
                <input
                  type="checkbox"
                  :value="r.ruta"
                  v-model="filtroFronterasRutas"
                  @change="renderizarEnMapa(true)"
                  class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer"
                />
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: r.color }"></span>
                <span class="font-mono font-semibold text-slate-800 truncate">{{ r.nombre }}</span>
                <span v-if="r.vendedor" class="text-[9.5px] text-slate-400 truncate max-w-[85px]" :title="r.vendedor">({{ r.vendedor }})</span>
              </div>
              <span
                class="text-[9.5px] font-mono px-1.5 py-0.2 rounded font-bold shrink-0"
                :class="rutasConPoligonoRuta.has(r.ruta) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-400'"
              >
                {{ rutasConPoligonoRuta.has(r.ruta) ? 'Polígono' : 'S/P' }}
              </span>
            </label>
          </div>
        </div>

        <!-- Menú Fronteras Días (por ruta) -->
        <div
          v-if="menuAbierto === 'fronteras_dias'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <span class="font-bold text-[11px] text-slate-700 uppercase tracking-tight">Frontera Día</span>
            <div class="flex items-center gap-2">
              <button type="button" @click="seleccionarTodasFronterasDias" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todas</button>
              <button type="button" @click="limpiarFronterasDias" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
            </div>
          </div>
          <input
            v-if="rutasDisponiblesParaFronteras.length > 5"
            v-model="busquedaFronteraDia"
            type="text"
            placeholder="Buscar ruta..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <div v-if="rutasFiltradasEnDropdownFronteraDia.length === 0" class="py-2 text-center text-slate-400 text-[11px]">
              No hay rutas disponibles
            </div>
            <label
              v-for="r in rutasFiltradasEnDropdownFronteraDia"
              :key="r.ruta"
              class="flex items-center justify-between gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none"
            >
              <div class="flex items-center gap-2 truncate">
                <input
                  type="checkbox"
                  :value="r.ruta"
                  v-model="filtroFronterasDias"
                  @change="renderizarEnMapa(true)"
                  class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer"
                />
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: r.color }"></span>
                <span class="font-mono font-semibold text-slate-800 truncate">{{ r.nombre }}</span>
                <span v-if="r.vendedor" class="text-[9.5px] text-slate-400 truncate max-w-[85px]" :title="r.vendedor">({{ r.vendedor }})</span>
              </div>
              <span
                class="text-[9.5px] font-mono px-1.5 py-0.2 rounded font-bold shrink-0"
                :class="rutasConPoligonoDia.has(r.ruta) ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-slate-100 text-slate-400'"
              >
                {{ rutasConPoligonoDia.has(r.ruta) ? 'Polígonos' : 'S/P' }}
              </span>
            </label>
          </div>
        </div>

        <!-- Menú Desplegable: Selector de Meses Históricos (Mes Actual y 10 Meses Atrás) -->
        <div
          v-if="menuAbierto === 'meses_historicos'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-72 bg-white border border-slate-200 rounded-xl shadow-2xl p-3 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
            <div class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
              </svg>
              <span class="font-bold text-slate-800 text-xs">Meses Históricos</span>
            </div>
            <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-200">
              {{ mesesHistoricosSeleccionados.length }} sel.
            </span>
          </div>

          <!-- Botones de Acción Rápida: Todos | Últimos 3M | Solo Actual -->
          <div class="grid grid-cols-3 gap-1 mb-2">
            <button
              type="button"
              @click="seleccionarPreajusteMeses('todos')"
              class="py-1 px-1.5 rounded text-[10.5px] font-semibold text-center transition-colors cursor-pointer border"
              :class="mesesHistoricosSeleccionados.length === periodosHistoricos.length ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'"
            >
              Todos ({{ periodosHistoricos.length }})
            </button>
            <button
              type="button"
              @click="seleccionarPreajusteMeses('ultimos_3')"
              class="py-1 px-1.5 rounded text-[10.5px] font-semibold text-center transition-colors cursor-pointer border"
              :class="esPreajusteUltimos3 ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'"
            >
              Últimos 3M
            </button>
            <button
              type="button"
              @click="seleccionarPreajusteMeses('actual')"
              class="py-1 px-1.5 rounded text-[10.5px] font-semibold text-center transition-colors cursor-pointer border"
              :class="mesesHistoricosSeleccionados.length === 1 && mesesHistoricosSeleccionados[0] === periodosHistoricos[0]?.periodo ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'"
            >
              Solo Actual
            </button>
          </div>

          <!-- Lista de Meses con Checkboxes (11 meses del actual a 10 meses atrás) -->
          <div class="max-h-56 overflow-y-auto space-y-0.5 custom-scroll pr-1">
            <label
              v-for="p in periodosHistoricos"
              :key="p.periodo"
              class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer select-none transition-colors"
              :class="mesesHistoricosSeleccionados.includes(p.periodo) ? 'bg-sky-50/50' : ''"
            >
              <div class="flex items-center gap-2 min-w-0">
                <input
                  type="checkbox"
                  :value="p.periodo"
                  :checked="mesesHistoricosSeleccionados.includes(p.periodo)"
                  @change="toggleMesPeriodo(p.periodo)"
                  class="rounded border-slate-300 text-sky-600 focus:ring-0 cursor-pointer"
                />
                <span
                  class="truncate text-xs"
                  :class="p.es_actual ? 'font-bold text-sky-900' : (mesesHistoricosSeleccionados.includes(p.periodo) ? 'font-semibold text-slate-900' : 'text-slate-700')"
                >
                  {{ p.nombre }}
                </span>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <span v-if="p.es_actual" class="px-1 py-0.2 rounded text-[9px] font-mono font-bold bg-sky-100 text-sky-800">
                  Actual
                </span>
                <span class="text-[10px] font-mono text-slate-400">
                  {{ p.corto }}
                </span>
              </div>
            </label>
          </div>

          <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[10px] text-slate-500 font-mono">
              Actualiza Balance y Matriz
            </span>
            <button
              type="button"
              @click="menuAbierto = null"
              class="px-3 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-md text-[11px] font-bold cursor-pointer transition-colors shadow-2xs"
            >
              Listo
            </button>
          </div>
        </div>
      </Teleport>

      <!-- BARRA DE HERRAMIENTAS: AJUSTE RTM (FRONTERAS TERRITORIALES) -->
      <div
        v-if="modoPrincipal === 'ajuste_rtm'"
        class="bg-slate-100 border-b border-slate-300 px-3 sm:px-4 py-1.5 sm:py-2 text-xs z-20 shrink-0 text-slate-800"
      >
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <div class="flex items-center gap-2 whitespace-nowrap shrink-0">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700 font-mono">
              Frontera RTM:
            </span>

            <select
              v-model="rutaParaAjustarFrontera"
              @change="alCambiarRutaParaFrontera"
              class="h-7 px-2.5 bg-white border border-slate-300 rounded-md text-xs font-mono font-bold text-slate-900 focus:outline-none focus:border-slate-500 cursor-pointer"
            >
              <option value="">-- Seleccionar ruta a ajustar --</option>
              <option v-for="r in rutasDisponiblesParaFronteras" :key="r.ruta" :value="r.ruta">
                {{ formatRuta(r.ruta) }} {{ r.canal ? `(${r.canal})` : '' }}
              </option>
            </select>

            <!-- Valores acumulados de los meses seleccionados para la ruta en ajuste -->
            <div
              v-if="rutaParaAjustarFrontera && balanceRutaSeleccionada && periodosVisibles.length > 0"
              class="hidden sm:flex items-center gap-1.5 px-2 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10.5px] shadow-2xs select-none shrink-0"
            >
              <span class="text-slate-700 font-bold">{{ balanceRutaSeleccionada.total }} clis</span>
              <template v-for="p in periodosVisibles" :key="p.periodo">
                <span class="text-slate-300">|</span>
                <span :title="p.nombre">
                  <b :class="p.es_actual ? 'text-sky-700' : 'text-slate-600'">{{ p.corto }}:</b> {{ formatMonto(getVentaRutaPeriodo(balanceRutaSeleccionada, p.periodo)) }}
                </span>
              </template>
            </div>

            <template v-if="rutaParaAjustarFrontera">
              <button
                v-if="!editandoVertices"
                type="button"
                @click="activarEdicionVertices"
                class="h-7 px-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-md text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                </svg>
                <span>{{ tieneFronteraRutaActual ? 'Editar Vértices' : 'Dibujar / Generar' }}</span>
              </button>

              <button
                v-if="!tieneFronteraRutaActual && !editandoVertices"
                type="button"
                @click="generarConvexHullRuta"
                class="h-7 px-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 font-semibold rounded-md text-xs transition-colors cursor-pointer"
                title="Calcular polígono envolvente automático a partir de los clientes GPS de esta ruta"
              >
                <span>Generar Convex Hull</span>
              </button>

              <template v-if="editandoVertices">
                <span class="text-[11px] font-mono text-slate-700 bg-white px-2 py-0.5 rounded border border-slate-300 flex items-center gap-1 shadow-2xs">
                  <span class="font-bold text-sky-700">{{ coordsPoligonoEnEdicion.length }} pts</span>
                </span>

                <button
                  type="button"
                  @click="modoAgregarVertice = !modoAgregarVertice; if (!modoAgregarVertice) ocultarIndicadorSnap();"
                  class="h-7 px-2.5 rounded-md text-xs font-semibold flex items-center gap-1 transition-colors cursor-pointer border"
                  :class="modoAgregarVertice ? 'bg-sky-600 text-white border-sky-700 animate-pulse' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                  :title="modoAgregarVertice ? 'Haz clic en cualquier punto del mapa para agregar un vértice' : 'Activar modo clic en mapa para agregar punto'"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  <span>{{ modoAgregarVertice ? 'Clic en mapa...' : '+ Punto' }}</span>
                </button>

                <button
                  type="button"
                  @click="snapProximidadActivado = !snapProximidadActivado; if (!snapProximidadActivado) ocultarIndicadorSnap();"
                  class="h-7 px-2.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer border"
                  :class="snapProximidadActivado ? 'bg-emerald-50 text-emerald-800 border-emerald-400 font-bold' : 'bg-white text-slate-500 border-slate-300 hover:bg-slate-50'"
                  :title="snapProximidadActivado ? 'Detector de proximidad activo: Los vértices se adhieren magnéticamente a fronteras de rutas vecinas' : 'Detector de proximidad desactivado'"
                >
                  <svg class="w-3.5 h-3.5" :class="snapProximidadActivado ? 'text-emerald-600' : 'text-slate-400'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4v7a8 8 0 0 0 16 0V4" />
                    <path d="M4 8h5" />
                    <path d="M15 8h5" />
                  </svg>
                  <span>{{ snapProximidadActivado ? 'Imán: ON' : 'Imán: OFF' }}</span>
                </button>

                <button
                  type="button"
                  @click="guardarFronteraRtm"
                  :disabled="guardandoFrontera"
                  class="h-7 px-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-md text-xs flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50"
                >
                  <span v-if="guardandoFrontera" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  <span>{{ guardandoFrontera ? 'Guardando...' : 'Guardar' }}</span>
                </button>

                <button
                  type="button"
                  @click="cancelarEdicionVertices"
                  class="h-7 px-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 font-semibold rounded-md text-xs transition-colors cursor-pointer"
                >
                  Cancelar
                </button>
              </template>
            </template>
          </div>

          <!-- Guía Visual de Modelado RTM: Relleno = Ruta | Borde = Día | Segmentado = Modificado -->
          <div class="hidden lg:flex items-center gap-2 text-[11px] font-mono whitespace-nowrap shrink-0 py-0.5">
            <span class="text-slate-400 font-bold uppercase text-[10px]">Modelado:</span>
            <div class="flex items-center gap-1.5 px-2 py-0.5 bg-white border border-slate-300 rounded shadow-2xs">
              <span class="w-3 h-3 rounded-full bg-slate-800 shrink-0"></span>
              <span class="text-slate-700 font-medium">Relleno: Ruta</span>
            </div>
            <div class="flex items-center gap-1.5 px-2 py-0.5 bg-white border border-slate-300 rounded shadow-2xs">
              <span class="w-3 h-3 rounded-full border-2 border-sky-600 bg-white shrink-0"></span>
              <span class="text-slate-700 font-medium">Borde: Día</span>
            </div>
            <div class="flex items-center gap-1.5 px-2 py-0.5 bg-white border border-slate-300 rounded shadow-2xs">
              <span class="w-3 h-3 rounded-full border-2 border-dashed border-slate-900 bg-white shrink-0"></span>
              <span class="text-slate-900 font-bold">Segmentado: Modificado</span>
            </div>

            <!-- Chips de Días -->
            <div class="flex items-center gap-1 pl-1">
              <span
                v-for="d in diasSemanaConfig"
                :key="d.key"
                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold border font-mono"
                :style="{ backgroundColor: d.bg, color: d.text, borderColor: d.color }"
                :title="`${d.label}: borde ${d.color}`"
              >
                {{ d.corto }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-3 ml-auto">
            <span class="text-[11px] font-mono text-slate-500 hidden xl:inline">
              Haz clic en cualquier cliente del mapa para reasignar su ruta o día
            </span>
            <button
              type="button"
              @click="setModoPrincipal('visualizacion')"
              class="text-slate-700 hover:text-slate-900 font-semibold text-xs cursor-pointer px-2.5 py-1 rounded border border-slate-300 bg-white hover:bg-slate-50 transition-colors"
            >
              Cerrar Ajuste RTM
            </button>
          </div>
        </div>
      </div>

      <!-- Contenedor Principal: Split Screen (Panel Lateral + Mapa Leaflet) -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative">
        
        <!-- Panel Lateral: Balance RTM (en Ajuste RTM) o Clientes/Balance (en Visualización) -->
        <aside
          class="w-full md:w-[370px] bg-white border-r border-slate-200 flex flex-col shrink-0 transition-all z-20"
          :class="vistaMovil === 'lista' ? 'flex' : 'hidden md:flex'"
        >
          <!-- CASO 1: EN MODO AJUSTE RTM, SOLO SE VE EL BALANCE RTM (SIN CLIENTES) -->
          <template v-if="modoPrincipal === 'ajuste_rtm'">
            <div class="p-2.5 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-2 shrink-0">
              <div class="min-w-0">
                <h3 class="text-xs font-bold text-slate-800 tracking-wider uppercase font-mono truncate">Balance RTM</h3>
                <p class="text-[10px] text-slate-500 truncate">Distribución por ruta y jornada</p>
              </div>

              <!-- Filtro al lado de Balance RTM para seleccionar los meses históricos -->
              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click.stop="toggleMenu('meses_historicos', $event)"
                  class="h-7 px-2 bg-white hover:bg-slate-100 border rounded-lg text-[10.5px] font-mono font-bold text-slate-800 flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none"
                  :class="menuAbierto === 'meses_historicos' ? 'border-sky-500 bg-sky-50/50 text-sky-900' : 'border-slate-300'"
                  title="Seleccionar meses históricos a visualizar (del actual a 10 meses atrás)"
                >
                  <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                  </svg>
                  <span>Meses ({{ mesesHistoricosSeleccionados.length }})</span>
                  <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>

                <span class="text-[10.5px] font-mono font-bold px-1.5 py-0.5 rounded bg-white border border-slate-200 text-slate-800 shrink-0">
                  {{ balanceRtmPorRuta.length }}
                </span>
              </div>
            </div>

            <!-- Cuerpo de Balance RTM -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scroll">
              <div v-if="balanceRtmPorRuta.length === 0" class="p-8 text-center text-xs text-slate-400">
                Consulta rutas para visualizar el balance de cartera y carga de días RTM.
              </div>

              <div
                v-for="b in balanceRtmPorRuta"
                :key="b.ruta"
                class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2 text-xs"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: getRutaColor(b.ruta) }"></span>
                    <span class="font-mono font-bold text-slate-900 text-xs">{{ formatRuta(b.ruta) }}</span>
                    <span class="text-slate-400 text-[10px] font-mono">({{ b.canal }})</span>
                  </div>
                  <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-800">
                    {{ b.total }} clientes
                  </span>
                </div>

                <!-- Valores acumulados de los meses seleccionados por ruta -->
                <div
                  v-if="periodosVisibles.length > 0"
                  class="grid gap-1 pt-1.5 border-t border-slate-200/80 font-mono text-[9.5px]"
                  :class="{
                    'grid-cols-1': periodosVisibles.length === 1,
                    'grid-cols-2': periodosVisibles.length === 2,
                    'grid-cols-3': periodosVisibles.length >= 3,
                  }"
                >
                  <div
                    v-for="p in periodosVisibles"
                    :key="p.periodo"
                    class="border rounded px-1.5 py-0.5"
                    :class="p.es_actual ? 'bg-sky-50/70 border-sky-200/80' : 'bg-slate-100/70 border-slate-200'"
                    :title="p.nombre"
                  >
                    <div
                      class="text-[8px] font-semibold truncate"
                      :class="p.es_actual ? 'text-sky-700' : 'text-slate-600'"
                    >
                      {{ p.corto }}
                    </div>
                    <div
                      class="font-bold truncate"
                      :class="p.es_actual ? 'text-slate-900' : 'text-slate-800'"
                    >
                      {{ formatMonto(getVentaRutaPeriodo(b, p.periodo)) }}
                    </div>
                  </div>
                </div>

                <!-- Distribución por Jornadas de Visita -->
                <div class="pt-1.5 border-t border-slate-200">
                  <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono mb-1">
                    Carga por Día:
                  </div>
                  <div class="grid grid-cols-7 gap-1 text-[10px] font-mono">
                    <div
                      v-for="d in diasSemana"
                      :key="d.key"
                      class="text-center py-1 rounded border"
                      :class="(b.dias[d.key] || 0) > 0 ? 'bg-white border-slate-200 font-bold text-slate-900' : 'bg-slate-100/60 border-slate-200/40 text-slate-400'"
                    >
                      <div class="text-[8px] opacity-75 font-normal">{{ d.corto }}</div>
                      <div class="text-[11px]">{{ b.dias[d.key] || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- CASO 2: EN MODO VISUALIZACIÓN, SELECTOR DE PESTAÑAS (CLIENTES O BALANCE) -->
          <template v-else>
            <template v-if="!clienteSeleccionado">
              <!-- Selector de Pestaña del Panel Lateral (Lista vs Balance RTM) -->
              <div class="p-2 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-1.5 shrink-0">
                <div class="flex items-center bg-slate-200 p-0.5 rounded-lg flex-1">
                  <button
                    type="button"
                    @click="tabLateral = 'lista'"
                    class="flex-1 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer text-center"
                    :class="tabLateral === 'lista' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                  >
                    Clientes ({{ clientesFiltradosLista.length }})
                  </button>
                  <button
                    type="button"
                    @click="tabLateral = 'balance'"
                    class="flex-1 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer text-center"
                    :class="tabLateral === 'balance' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                  >
                    Balance RTM
                  </button>
                </div>
              </div>

              <!-- Filtro al lado de Balance RTM para seleccionar los meses históricos en modo visualización -->
              <div v-if="tabLateral === 'balance'" class="px-3 py-1.5 bg-slate-100/90 border-b border-slate-200 flex items-center justify-between text-xs font-mono">
                <span class="text-[10px] text-slate-600 font-bold uppercase tracking-wider">Meses Visibles:</span>
                <button
                  type="button"
                  @click.stop="toggleMenu('meses_historicos', $event)"
                  class="h-6 px-2 bg-white hover:bg-slate-100 border rounded-md text-[10.5px] font-mono font-bold text-slate-800 flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none"
                  :class="menuAbierto === 'meses_historicos' ? 'border-sky-500 bg-sky-50/50 text-sky-900' : 'border-slate-300'"
                  title="Seleccionar meses históricos a visualizar (del actual a 10 meses atrás)"
                >
                  <svg class="w-3 h-3 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                  </svg>
                  <span>{{ mesesHistoricosSeleccionados.length }} seleccionados</span>
                  <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>
              </div>

              <!-- Vista 1A: Listado de Clientes Filtrables -->
              <template v-if="tabLateral === 'lista'">
                <!-- Buscador de Clientes y Selección -->
                <div class="p-2.5 border-b border-slate-100 bg-slate-50 flex items-center space-x-2">
                  <div class="flex items-center pl-1" title="Seleccionar / deseleccionar todos los clientes filtrados">
                    <input
                      type="checkbox"
                      :checked="todosClientesFiltradosSeleccionados"
                      @change="toggleSeleccionarTodosClientes"
                      class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-3.5 h-3.5"
                    />
                  </div>
                  <div class="relative flex-1">
                    <input
                      v-model="busquedaCliente"
                      type="text"
                      placeholder="Filtrar por nombre, ruta o ID..."
                      class="w-full h-8 pl-8 pr-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-slate-400 transition-all shadow-2xs"
                    />
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                  </div>
                </div>

                <!-- Lista Scrollable de Clientes -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 custom-scroll">
                  <div v-if="clientes.length === 0 && !cargando" class="p-8 text-center text-xs text-slate-400 space-y-2">
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
                      </svg>
                    </div>
                    <p class="font-medium text-slate-600">Sin datos consultados</p>
                    <p class="text-[11px] text-slate-400">Selecciona canal(es) o ruta(s) y presiona el botón <strong>"Consultar"</strong> para proyectar la red de clientes.</p>
                  </div>

                  <div
                    v-for="c in clientesFiltradosLista"
                    :key="c.id"
                    class="w-full text-left p-3 hover:bg-slate-50 transition-colors focus:outline-none group relative border-l-2"
                    :class="clienteActivoId === c.id ? 'bg-slate-100 border-slate-700' : 'border-transparent'"
                  >
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex items-center gap-2 flex-1 min-w-0">
                        <input
                          type="checkbox"
                          :value="c.cliente_id || c.id"
                          v-model="clientesSeleccionadosIds"
                          @click.stop
                          class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-3.5 h-3.5 shrink-0"
                        />
                        <span
                          @click="abrirExpedienteCliente(c)"
                          class="text-xs font-semibold text-slate-900 group-hover:text-slate-700 transition-colors line-clamp-1 cursor-pointer flex-1"
                        >
                          {{ c.nombre }}
                        </span>
                      </div>

                      <div class="flex items-center gap-1 shrink-0">
                        <button
                          v-if="(c.estado || '').toLowerCase() === 'inactivo'"
                          type="button"
                          @click.stop="activarClienteDirecto(c)"
                          :disabled="activandoClienteId === (c.cliente_id || c.id)"
                          class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors cursor-pointer shadow-2xs disabled:opacity-50"
                          title="Reactivar a Activo inmediatamente"
                        >
                          {{ activandoClienteId === (c.cliente_id || c.id) ? '...' : 'Activar' }}
                        </button>
                        <span
                          v-if="(c.estado || '').toLowerCase() === 'inactivo'"
                          class="text-[9px] font-mono px-1.5 py-0.2 rounded border bg-rose-50 text-rose-700 border-rose-200 font-bold uppercase"
                        >
                          Inactivo
                        </span>
                        <span
                          class="text-[9px] font-mono px-1.5 py-0.2 rounded border"
                          :class="c.tiene_gps ? 'bg-slate-100 text-slate-700 border-slate-200' : 'bg-slate-50 text-slate-500 border-slate-200'"
                        >
                          {{ c.tiene_gps ? 'GPS' : 'SIN GPS' }}
                        </span>
                      </div>
                    </div>

                    <p @click="abrirExpedienteCliente(c)" class="text-[11px] text-slate-500 mt-0.5 line-clamp-1 cursor-pointer">{{ c.direccion }}</p>

                    <!-- Pastilla distintiva si tiene propuesta en sub-modo cambios -->
                    <div
                      v-if="subModoVisualizacion === 'cambios' && c.tiene_cambio"
                      class="mt-1.5 px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-[10px] font-mono flex items-center justify-between text-slate-800"
                    >
                      <div class="flex items-center gap-1 truncate">
                        <span class="font-bold text-slate-900">Propuesta:</span>
                        <span class="line-through text-slate-400">{{ formatRuta(c.ruta_original) }} ({{ c.dia_original || 'S/D' }})</span>
                        <span>-></span>
                        <span class="font-bold text-slate-900">{{ formatRuta(c.ruta) }} ({{ c.dia_norm }})</span>
                      </div>
                      <span class="text-[9px] uppercase font-bold px-1 rounded bg-slate-200 text-slate-700 shrink-0">
                        {{ c.propuesta_estado || 'PENDIENTE' }}
                      </span>
                    </div>

                    <div class="flex items-center justify-between mt-1.5 text-[10px] text-slate-400 font-mono">
                      <span class="flex items-center gap-1 font-medium text-slate-700">
                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                        {{ formatRuta(c.ruta) }}
                      </span>
                      <span>•</span>
                      <span class="font-semibold" :style="{ color: getDiaColor(c.dia_norm) }">{{ c.dia_norm || 'Sin Día' }}</span>
                      <span>•</span>
                      <span>ID: {{ c.cliente_id }}</span>
                    </div>

                    <!-- Valores de fact_ventas de los meses seleccionados por cliente -->
                    <div
                      v-if="periodosVisibles.length > 0"
                      class="flex items-center gap-1.5 flex-wrap mt-1 pt-1 border-t border-slate-100 text-[9.5px] font-mono text-slate-600"
                    >
                      <template v-for="(p, pIdx) in periodosVisibles" :key="p.periodo">
                        <span v-if="pIdx > 0" class="text-slate-300">•</span>
                        <span :title="p.nombre">
                          <b :class="p.es_actual ? 'text-sky-700' : 'text-slate-500'">{{ p.corto }}:</b> {{ formatMonto(getVentaClientePeriodo(c, p.periodo)) }}
                        </span>
                      </template>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Vista 1B: Balance RTM de Rutas Consultadas -->
              <template v-else-if="tabLateral === 'balance'">
                <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scroll">
                  <div v-if="balanceRtmPorRuta.length === 0" class="p-8 text-center text-xs text-slate-400">
                    Consulta rutas para visualizar el balance de cartera y carga de días RTM.
                  </div>

                  <div
                    v-for="b in balanceRtmPorRuta"
                    :key="b.ruta"
                    class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2 text-xs"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: getRutaColor(b.ruta) }"></span>
                        <span class="font-mono font-bold text-slate-900 text-xs">{{ formatRuta(b.ruta) }}</span>
                        <span class="text-slate-400 text-[10px] font-mono">({{ b.canal }})</span>
                      </div>
                      <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-800">
                        {{ b.total }} clientes
                      </span>
                    </div>

                    <!-- Valores acumulados de los meses seleccionados por ruta -->
                    <div
                      v-if="periodosVisibles.length > 0"
                      class="grid gap-1 pt-1.5 border-t border-slate-200/80 font-mono text-[9.5px]"
                      :class="{
                        'grid-cols-1': periodosVisibles.length === 1,
                        'grid-cols-2': periodosVisibles.length === 2,
                        'grid-cols-3': periodosVisibles.length >= 3,
                      }"
                    >
                      <div
                        v-for="p in periodosVisibles"
                        :key="p.periodo"
                        class="border rounded px-1.5 py-0.5"
                        :class="p.es_actual ? 'bg-sky-50/70 border-sky-200/80' : 'bg-slate-100/70 border-slate-200'"
                        :title="p.nombre"
                      >
                        <div
                          class="text-[8px] font-semibold truncate"
                          :class="p.es_actual ? 'text-sky-700' : 'text-slate-600'"
                        >
                          {{ p.corto }}
                        </div>
                        <div
                          class="font-bold truncate"
                          :class="p.es_actual ? 'text-slate-900' : 'text-slate-800'"
                        >
                          {{ formatMonto(getVentaRutaPeriodo(b, p.periodo)) }}
                        </div>
                      </div>
                    </div>

                    <!-- Distribución por Jornadas de Visita -->
                    <div class="pt-1.5 border-t border-slate-200">
                      <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono mb-1">
                        Carga por Día:
                      </div>
                      <div class="grid grid-cols-7 gap-1 text-[10px] font-mono">
                        <div
                          v-for="d in diasSemana"
                          :key="d.key"
                          class="text-center py-1 rounded border"
                          :class="(b.dias[d.key] || 0) > 0 ? 'bg-white border-slate-200 font-bold text-slate-900' : 'bg-slate-100/60 border-slate-200/40 text-slate-400'"
                        >
                          <div class="text-[8px] opacity-75 font-normal">{{ d.corto }}</div>
                          <div class="text-[11px]">{{ b.dias[d.key] || 0 }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </template>

            <!-- Vista 2: Expediente y Visitas con Fotografías del Cliente -->
            <template v-else>
            <!-- Header de Retorno -->
            <div class="p-2.5 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="cerrarExpediente"
                class="text-xs font-semibold text-sky-600 hover:text-sky-700 flex items-center gap-1 cursor-pointer transition-colors"
              >
                <span>&larr;</span> Volver al listado
              </button>
              
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden text-xs text-slate-800 font-semibold bg-white border border-slate-200 px-2.5 py-1 rounded-md shadow-2xs"
              >
                Ver en mapa
              </button>
            </div>

            <!-- Ficha Resumen del Cliente -->
            <div class="p-3.5 bg-white border-b border-slate-100 shrink-0 space-y-2.5">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <h3 class="text-xs font-bold text-slate-900 leading-snug">{{ clienteSeleccionado.nombre }}</h3>
                  <p class="text-[11px] text-slate-500 mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección registrada' }}</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <span
                    class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded border uppercase"
                    :class="(clienteSeleccionado.estado || '').toLowerCase() === 'activo' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                  >
                    {{ clienteSeleccionado.estado || 'Activo' }}
                  </span>
                  <span
                    class="text-[9px] font-mono font-bold px-2 py-0.5 rounded text-white shadow-2xs"
                    :style="{ backgroundColor: getRutaColor(clienteSeleccionado.ruta) }"
                  >
                    {{ formatRuta(clienteSeleccionado.ruta) }}
                  </span>
                </div>
              </div>

              <!-- Tarjeta de RTM Actual y Botones de Ajuste / Activación -->
              <div class="p-2.5 bg-slate-50 border border-slate-200/90 rounded-xl space-y-2 text-xs">
                <div class="flex items-center justify-between font-mono text-[11px]">
                  <span>Ruta: <b :style="{ color: getRutaColor(clienteSeleccionado.ruta) }">{{ formatRuta(clienteSeleccionado.ruta) }}</b></span>
                  <span>Día: <b :style="{ color: getDiaColor(clienteSeleccionado.dia_norm) }">{{ clienteSeleccionado.dia_norm || 'S/D' }}</b></span>
                </div>
                <button
                  type="button"
                  @click="abrirModalAjusteRtm(clienteSeleccionado)"
                  class="w-full py-1.5 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                  </svg>
                  <span>Ajustar RTM (Reasignar Ruta / Día)</span>
                </button>
                <button
                  v-if="(clienteSeleccionado.estado || '').toLowerCase() !== 'activo'"
                  type="button"
                  @click="activarClienteDirecto(clienteSeleccionado)"
                  :disabled="activandoClienteId === (clienteSeleccionado.cliente_id || clienteSeleccionado.id)"
                  class="w-full py-1.5 px-3 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                  title="Reactivar cliente a estado Activo inmediatamente"
                >
                  <span v-if="activandoClienteId === (clienteSeleccionado.cliente_id || clienteSeleccionado.id)" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                  <span>{{ activandoClienteId === (clienteSeleccionado.cliente_id || clienteSeleccionado.id) ? 'Activando...' : 'Activar Cliente' }}</span>
                </button>
              </div>

              <!-- MÉTRICAS DE COMPRA FACT_VENTAS (MES ACTUAL VS MES ANTERIOR) -->
              <div class="p-2.5 bg-slate-50 border border-slate-200/90 rounded-xl space-y-2 text-xs">
                <div class="flex items-center justify-between text-[10px] font-bold font-mono text-slate-700 uppercase tracking-wider">
                  <span class="flex items-center gap-1">
                    <svg class="w-3 h-3 text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Compras fact_ventas</span>
                  </span>
                  <span v-if="kpisVentasCliente" class="text-[9px] text-slate-400 font-normal">
                    {{ mesesHistoricosSeleccionados.length }} Mes{{ mesesHistoricosSeleccionados.length > 1 ? 'es' : '' }}
                  </span>
                </div>

                <!-- Cargando -->
                <div v-if="cargandoKpisVentas" class="py-2 text-center text-[10px] text-slate-400 animate-pulse font-mono flex items-center justify-center gap-1">
                  <div class="w-3 h-3 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></div>
                  <span>Consultando fact_ventas...</span>
                </div>

                <!-- Grilla Resumen -->
                <div
                  v-else-if="kpisVentasCliente"
                  class="grid gap-1.5"
                  :class="{
                    'grid-cols-1': periodosVisibles.length === 1,
                    'grid-cols-2': periodosVisibles.length === 2,
                    'grid-cols-3': periodosVisibles.length >= 3,
                  }"
                >
                  <div
                    v-for="p in periodosVisibles"
                    :key="p.periodo"
                    class="p-2 rounded-lg bg-white border border-slate-200/90 space-y-1"
                  >
                    <div class="flex items-center justify-between text-[8.5px] font-mono">
                      <span
                        class="font-bold uppercase"
                        :class="p.es_actual ? 'text-[#0071E3]' : 'text-slate-600'"
                      >
                        {{ p.corto }}
                      </span>
                      <span
                        class="truncate max-w-[55px]"
                        :class="p.es_actual ? 'font-bold text-[#0071E3]' : 'font-medium text-slate-500'"
                        :title="p.nombre"
                      >
                        {{ p.nombre }}
                      </span>
                    </div>
                    <div
                      class="text-[10px] font-bold font-mono leading-tight"
                      :class="p.es_actual ? 'text-slate-900' : 'text-slate-700'"
                    >
                      {{ formatMonto(getKpisPeriodoCliente(kpisVentasCliente, p.periodo)?.monto_acumulado ?? getVentaClientePeriodo(clienteSeleccionado, p.periodo)) }}
                    </div>
                    <div class="text-[8.5px] font-mono text-slate-600 pt-0.5 border-t border-slate-100 flex items-center justify-between">
                      <span class="text-slate-400">Frec:</span>
                      <span class="font-bold">
                        {{ getKpisPeriodoCliente(kpisVentasCliente, p.periodo)?.frecuencia_compra ?? getFrecuenciaClientePeriodo(clienteSeleccionado, p.periodo) }}
                      </span>
                    </div>
                  </div>
                </div>

                <div v-else class="py-1 text-center text-[10px] text-slate-400 font-mono">
                  Sin ventas registradas en el período seleccionado.
                </div>
              </div>

              <div class="flex items-center justify-between text-[10px] font-mono text-slate-500 pt-0.5">
                <span>ID: {{ clienteSeleccionado.cliente_id }}</span>
                <span v-if="clienteSeleccionado.celular || clienteSeleccionado.contacto" class="text-sky-600">
                  {{ clienteSeleccionado.celular || clienteSeleccionado.contacto }}
                </span>
              </div>

              <!-- Enlaces Rápidos Maps y WhatsApp -->
              <div class="flex items-center gap-1.5 pt-1 border-t border-slate-100">
                <a
                  v-if="clienteSeleccionado.latitud && clienteSeleccionado.longitud"
                  :href="'https://www.google.com/maps?q=' + clienteSeleccionado.latitud + ',' + clienteSeleccionado.longitud"
                  target="_blank"
                  class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[10px] font-semibold flex items-center gap-1"
                >
                  Maps ↗
                </a>
                <a
                  v-if="limpiarTel(clienteSeleccionado.celular || clienteSeleccionado.contacto)"
                  :href="'https://wa.me/591' + limpiarTel(clienteSeleccionado.celular || clienteSeleccionado.contacto)"
                  target="_blank"
                  class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-semibold flex items-center gap-1"
                >
                  WhatsApp
                </a>
              </div>
            </div>

            <!-- Cabecera de Visitas & Fotos -->
            <div class="px-3.5 py-2 bg-slate-50 border-b border-slate-200/80 flex items-center justify-between text-xs font-semibold text-slate-700 shrink-0">
              <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                </svg>
                Visitas & Fotografías
              </span>
              <span class="text-[11px] font-mono text-slate-500">{{ visitasCliente.length }} registradas</span>
            </div>

            <!-- Listado Scrollable de Visitas con Fotos -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5 custom-scroll">
              <div v-if="cargandoVisitas" class="py-8 text-center text-xs text-slate-400 animate-pulse">
                Cargando historial de visitas y fotos...
              </div>
              <div v-else-if="visitasCliente.length === 0" class="py-8 text-center text-xs text-slate-400">
                Sin visitas registradas para este cliente.
              </div>
              <div v-else class="space-y-2.5">
                <div
                  v-for="vis in visitasCliente"
                  :key="vis.id"
                  class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs space-y-2"
                >
                  <div class="flex items-center justify-between">
                    <span class="font-mono text-slate-500 text-[11px]">{{ vis.fecha }} {{ vis.hora }}</span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono uppercase font-bold" :class="vis.status === 'VISITED' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700'">
                      {{ vis.status || 'VISITADO' }}
                    </span>
                  </div>
                  <p v-if="vis.comments" class="text-[11px] text-slate-600 italic">"{{ vis.comments }}"</p>
                  
                  <!-- Thumbnail con Visor Lightbox y Rotación -->
                  <div
                    v-if="vis.photo_url"
                    class="mt-1.5 group/photo relative cursor-pointer overflow-hidden rounded-lg border border-slate-200"
                    @click="abrirLightboxFoto(vis)"
                    title="Clic para ampliar y rotar foto"
                  >
                    <img
                      :src="vis.photo_url"
                      alt="Foto de visita"
                      class="w-full h-36 object-cover group-hover/photo:scale-105 transition-transform duration-200"
                    />
                    <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/photo:opacity-100 transition-opacity flex items-center justify-center gap-1.5 text-white text-[11px] font-semibold backdrop-blur-[1px]">
                      <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                      </svg>
                      <span>Ver & Girar</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
          </template>
        </aside>

        <!-- Panel del Mapa Leaflet -->
        <div
          class="flex-1 relative min-h-[350px] md:min-h-0 bg-slate-950"
          :class="vistaMovil === 'mapa' ? 'block' : 'hidden md:block'"
        >
          <!-- Lienzo Leaflet -->
          <div id="map-visor" class="absolute inset-0 w-full h-full z-10"></div>

          <!-- Indicador Flotante de Imán de Proximidad sobre el Mapa (Cero desplazamiento de pantalla) -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 -translate-y-2 scale-95"
          >
            <div
              v-if="textoSnapActivo"
              class="absolute top-3 left-1/2 -translate-x-1/2 z-30 pointer-events-none select-none flex items-center gap-2 px-3.5 py-1.5 bg-slate-900/90 text-white border border-emerald-400 rounded-full shadow-2xl text-xs font-semibold backdrop-blur-md"
            >
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 -ml-4.5"></span>
              <span class="tracking-wide">{{ textoSnapActivo }}</span>
            </div>
          </Transition>

          <!-- Selector de Capas de Mapa Base -->
          <div class="absolute top-3 left-3 z-30 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-1 shadow-md flex items-center space-x-1 text-xs">
            <button
              v-for="(layer, key) in mapsConfig.tileLayers"
              :key="key"
              type="button"
              @click="cambiarCapaMapa(key)"
              class="px-2.5 py-1 rounded-lg font-medium text-[11px] transition-all cursor-pointer"
              :class="capaActual === key ? 'bg-slate-900 text-white font-semibold shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              {{ layer.name }}
            </button>
          </div>

          <!-- Toast Flotante de Notificaciones -->
          <div
            v-if="toastMensaje"
            class="absolute top-3 right-3 z-[99995] px-3.5 py-2 bg-slate-900 text-white border border-slate-700 rounded-lg shadow-xl text-xs font-semibold flex items-center gap-2"
          >
            <span>{{ toastMensaje }}</span>
          </div>

          <!-- Botón Flotante para Abrir Leyenda cuando está Colapsada -->
          <button
            v-if="clientes.length > 0 && leyendaColapsada"
            type="button"
            @click="leyendaColapsada = false"
            class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl px-2.5 py-1.5 shadow-xl z-30 text-xs flex items-center gap-1.5 hover:bg-slate-50 cursor-pointer transition-all active:scale-95"
            title="Mostrar leyenda de colores"
          >
            <span class="flex -space-x-1">
              <span class="w-2.5 h-2.5 rounded-full bg-sky-500 border border-white"></span>
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 border border-white"></span>
              <span class="w-2.5 h-2.5 rounded-full bg-amber-500 border border-white"></span>
            </span>
            <span class="font-bold text-[11px] text-slate-800 font-mono">Leyenda</span>
            <span class="text-[9.5px] px-1 py-0.2 rounded bg-slate-100 text-slate-500 font-mono font-semibold">{{ elementosLeyenda.length }}</span>
          </button>

          <!-- Leyenda Dinámica en Tiempo Real (Expandida) -->
          <div
            v-if="clientes.length > 0 && !leyendaColapsada"
            class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-2.5 sm:p-3 shadow-xl z-30 text-xs w-[220px] sm:w-72 max-h-44 sm:max-h-64 flex flex-col"
          >
            <div class="flex items-center justify-between pb-1 mb-1.5 border-b border-slate-100 shrink-0 gap-1.5">
              <div class="min-w-0 flex-1">
                <p class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono truncate" :title="tituloCodificacionLeyenda">
                  {{ tituloCodificacionLeyenda }}
                </p>
                <span class="text-[9px] font-mono text-slate-500">{{ elementosLeyenda.length }} grupos</span>
              </div>
              <button
                type="button"
                @click="leyendaColapsada = true"
                class="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-md cursor-pointer transition-colors shrink-0"
                title="Minimizar leyenda"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </button>
            </div>

            <div class="space-y-1 text-[10px] sm:text-[11px] overflow-y-auto custom-scroll flex-1 pr-0.5">
              <div
                v-for="item in elementosLeyenda"
                :key="item.id"
                class="flex items-center justify-between gap-1.5 px-1.5 py-0.5 rounded hover:bg-slate-50 font-mono"
              >
                <div class="flex items-center gap-1.5 truncate min-w-0">
                  <span class="w-2 h-2 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: item.color }"></span>
                  <span class="text-slate-800 font-medium truncate text-[10px] sm:text-[11px]">{{ item.nombre }}</span>
                </div>
                <span class="text-[9.5px] text-slate-400 font-semibold tabular-nums shrink-0">{{ item.conteo }} pts</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ======================================================== -->
      <!-- TABLA INFERIOR: REGISTRO DE CAMBIOS Y PROPUESTAS RTM     -->
      <!-- ======================================================== -->
      <div class="bg-white border-t border-slate-300 shrink-0 z-20 flex flex-col transition-all">
        <!-- Barra de redimensionamiento manual (Drag Resizer) -->
        <div
          v-if="tablaCambiosVisible"
          @mousedown="iniciarArrastreAltoTabla"
          @touchstart="iniciarArrastreTactilAltoTabla"
          class="h-1.5 w-full bg-slate-200 hover:bg-sky-400 active:bg-sky-500 cursor-row-resize transition-colors flex items-center justify-center group select-none relative z-30"
          title="Arrastrar arriba o abajo para ajustar la altura de la matriz manualmente"
        >
          <div class="w-12 h-0.5 rounded-full bg-slate-400 group-hover:bg-white transition-colors"></div>
        </div>

        <!-- Cabecera de la Barra de Cambios -->
        <div class="px-4 py-2 bg-slate-100 border-b border-slate-200 flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider font-mono">
              Matriz de Clientes y Propuestas RTM
            </span>

            <!-- Selector de vista en matriz inferior: Solo Cambios vs Todos los Clientes -->
            <div class="flex items-center bg-slate-200/80 p-0.5 rounded-md font-mono text-[10px]">
              <button
                type="button"
                @click="filtroMatrizInferior = 'cambios'"
                class="px-2 py-0.5 rounded transition-all cursor-pointer"
                :class="filtroMatrizInferior === 'cambios' ? 'bg-white font-bold text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
              >
                Con Cambios ({{ clientesConCambio.length }})
              </button>
              <button
                type="button"
                @click="filtroMatrizInferior = 'todos'"
                class="px-2 py-0.5 rounded transition-all cursor-pointer"
                :class="filtroMatrizInferior === 'todos' ? 'bg-white font-bold text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
              >
                Todos los Clientes ({{ clientes.length }})
              </button>
            </div>

            <span
              v-if="modoPrincipal === 'visualizacion' && subModoVisualizacion === 'original' && filtroMatrizInferior === 'cambios'"
              class="text-[11px] font-mono font-medium px-2 py-0.5 rounded-full bg-slate-200/80 text-slate-700 border border-slate-300"
            >
              Base Original Activa ({{ kpis.total_propuestas || kpis.con_cambios }} propuestas registradas)
            </span>
            <span
              v-else
              class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700"
            >
              {{ clientesConCambioFiltrados.length }} clientes en vista
            </span>
          </div>

          <div class="flex items-center gap-2">
            <!-- Buscador interno de la matriz inferior -->
            <input
              v-if="clientesMatrizInferior.length > 3 && tablaCambiosVisible"
              v-model="busquedaTablaCambios"
              type="text"
              placeholder="Filtrar por cliente, ID o ruta..."
              class="h-6 px-2 text-[11px] bg-white border border-slate-300 rounded font-mono placeholder:text-slate-400 focus:outline-none"
            />

            <!-- Botón Descargar Excel de Propuestas Pendientes -->
            <button
              v-if="tablaCambiosVisible"
              type="button"
              @click="exportarPropuestasExcel(false)"
              :disabled="conteoPropuestasPendientes === 0"
              class="h-6 px-2.5 rounded text-[11px] font-semibold flex items-center gap-1.5 transition-all cursor-pointer shadow-2xs disabled:opacity-40 disabled:cursor-not-allowed bg-emerald-700 hover:bg-emerald-800 text-white border border-emerald-800 shrink-0"
              :title="conteoPropuestasPendientes > 0 ? `Descargar ${conteoPropuestasPendientes} propuesta(s) pendiente(s) en Excel` : 'No hay propuestas pendientes para descargar'"
            >
              <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              <span>Descargar Excel</span>
              <span v-if="conteoPropuestasPendientes > 0" class="px-1 rounded bg-emerald-900/60 text-[9.5px] font-mono">
                {{ conteoPropuestasPendientes }}
              </span>
            </button>

            <!-- Botones de Tamaño Predeterminado (Normal / Medio / Grande / Max) -->
            <div v-if="tablaCambiosVisible" class="hidden sm:flex items-center bg-slate-200/90 p-0.5 rounded-md font-mono text-[10px]">
              <button
                type="button"
                @click="setAltoTabla(220)"
                class="px-2 py-0.5 rounded transition-all cursor-pointer"
                :class="altoTablaCambios <= 260 ? 'bg-white font-bold text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                title="Alto Normal (220px)"
              >
                Normal
              </button>
              <button
                type="button"
                @click="setAltoTabla(390)"
                class="px-2 py-0.5 rounded transition-all cursor-pointer"
                :class="altoTablaCambios > 260 && altoTablaCambios <= 450 ? 'bg-white font-bold text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                title="Alto Medio (390px)"
              >
                Medio
              </button>
              <button
                type="button"
                @click="setAltoTabla(560)"
                class="px-2 py-0.5 rounded transition-all cursor-pointer"
                :class="altoTablaCambios > 450 && altoTablaCambios < 700 ? 'bg-white font-bold text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                title="Alto Grande (560px)"
              >
                Grande
              </button>
              <button
                type="button"
                @click="ciclarMaximizarTabla"
                class="px-2 py-0.5 rounded transition-all cursor-pointer"
                :class="altoTablaCambios >= 700 ? 'bg-white font-bold text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                :title="altoTablaCambios >= 700 ? 'Restaurar tamaño normal' : 'Maximizar matriz'"
              >
                {{ altoTablaCambios >= 700 ? 'Restaurar' : 'Max' }}
              </button>
            </div>

            <!-- Botón directo para alternar/ajustar alto -->
            <button
              v-if="tablaCambiosVisible"
              type="button"
              @click="ciclarAltoTabla"
              class="text-xs font-semibold text-slate-600 hover:text-slate-900 px-2 py-0.5 rounded hover:bg-slate-200 transition-colors cursor-pointer flex items-center gap-1"
              :title="'Ajustar alto: actual ' + altoTablaCambios + 'px (' + etiquetaAltoActual + '). Clic para cambiar tamaño'"
            >
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
              </svg>
              <span>Ajustar Alto</span>
            </button>

            <!-- Toggle colapsar / expandir -->
            <button
              type="button"
              @click="toggleTablaCambios"
              class="text-xs font-semibold text-slate-600 hover:text-slate-900 px-2 py-0.5 rounded hover:bg-slate-200 transition-colors cursor-pointer flex items-center gap-1"
            >
              <span>{{ tablaCambiosVisible ? 'Ocultar' : 'Mostrar' }}</span>
              <svg
                class="w-3.5 h-3.5 transition-transform"
                :class="tablaCambiosVisible ? 'rotate-180' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Contenido de la Tabla (Desplegable y Redimensionable) -->
        <div
          v-show="tablaCambiosVisible"
          :style="{ height: altoTablaCambios + 'px', maxHeight: altoTablaCambios + 'px' }"
          :class="['overflow-y-auto custom-scroll', esArrastrandoAltoTabla ? '' : 'transition-[height] duration-150']"
        >
          <!-- Mensaje cuando estamos en Base Original y filtro de cambios -->
          <div
            v-if="modoPrincipal === 'visualizacion' && subModoVisualizacion === 'original' && filtroMatrizInferior === 'cambios'"
            class="py-4 px-4 text-center text-xs text-slate-500 font-sans flex flex-col items-center justify-center gap-1.5"
          >
            <div>
              Estás visualizando la <strong>Base Original</strong> inmutable de pan_ruteo.
              <span v-if="(kpis.total_propuestas || kpis.con_cambios) > 0">
                Se registran <strong>{{ kpis.total_propuestas || kpis.con_cambios }}</strong> propuesta(s) para esta selección.
              </span>
            </div>
            <div class="flex items-center gap-2 mt-1">
              <button
                v-if="(kpis.total_propuestas || kpis.con_cambios) > 0"
                type="button"
                @click="setSubModoVisualizacion('cambios')"
                class="px-3 py-1 bg-slate-900 text-white rounded text-xs font-semibold hover:bg-slate-800 transition-colors cursor-pointer"
              >
                Ver y Gestionar Propuestas en Modo "Con Cambios"
              </button>
              <button
                type="button"
                @click="filtroMatrizInferior = 'todos'"
                class="px-3 py-1 bg-white border border-slate-300 text-slate-700 rounded text-xs font-semibold hover:bg-slate-50 transition-colors cursor-pointer"
              >
                Ver Todos los Clientes ({{ clientes.length }})
              </button>
            </div>
          </div>

          <!-- Mensaje cuando no hay registros para mostrar -->
          <div
            v-else-if="clientesMatrizInferior.length === 0"
            class="py-6 px-4 text-center text-xs text-slate-400 font-sans flex flex-col items-center justify-center gap-1.5"
          >
            <div>
              No se registran cambios o propuestas de ruteo en la selección actual. Al ajustar rutas o días en el mapa, los cambios se listarán aquí.
            </div>
            <button
              v-if="clientes.length > 0 && filtroMatrizInferior === 'cambios'"
              type="button"
              @click="filtroMatrizInferior = 'todos'"
              class="mt-1 px-3 py-1 bg-slate-900 text-white rounded text-xs font-semibold hover:bg-slate-800 transition-colors cursor-pointer"
            >
              Ver todos los {{ clientes.length }} clientes de la selección con sus históricos de venta
            </button>
          </div>

          <table v-else class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-50 text-[10px] uppercase font-mono text-slate-500 border-b border-slate-200 sticky top-0 z-10 select-none">
              <tr>
                <th class="py-1.5 px-2.5 w-8 text-center">
                  <input
                    type="checkbox"
                    :checked="todosClientesTablaSeleccionados"
                    @change="toggleSeleccionarTodosClientesTabla"
                    class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-3.5 h-3.5"
                    title="Seleccionar / deseleccionar todos los clientes de la tabla"
                  />
                </th>
                <th
                  @click="ordenarPor('cliente_id')"
                  class="py-1.5 px-3 font-bold cursor-pointer hover:bg-slate-100 transition-colors"
                  title="Clic para ordenar por ID"
                >
                  <div class="inline-flex items-center gap-1">
                    <span>ID</span>
                    <span class="text-[9px]" :class="columnaOrden === 'cliente_id' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'cliente_id' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('nombre')"
                  class="py-1.5 px-3 font-bold cursor-pointer hover:bg-slate-100 transition-colors"
                  title="Clic para ordenar por Nombre del cliente"
                >
                  <div class="inline-flex items-center gap-1">
                    <span>Cliente</span>
                    <span class="text-[9px]" :class="columnaOrden === 'nombre' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'nombre' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('ruta')"
                  class="py-1.5 px-3 font-bold cursor-pointer hover:bg-slate-100 transition-colors"
                  title="Clic para ordenar por Ruta"
                >
                  <div class="inline-flex items-center gap-1">
                    <span>Ruta Anterior -> Nueva</span>
                    <span class="text-[9px]" :class="columnaOrden === 'ruta' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'ruta' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('dia_norm')"
                  class="py-1.5 px-3 font-bold cursor-pointer hover:bg-slate-100 transition-colors"
                  title="Clic para ordenar por Día de visita"
                >
                  <div class="inline-flex items-center gap-1">
                    <span>Día Anterior -> Nuevo</span>
                    <span class="text-[9px]" :class="columnaOrden === 'dia_norm' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'dia_norm' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <!-- Columnas de Históricos de Cliente (Dinámicas según meses seleccionados) -->
                <th
                  v-for="p in periodosVisibles"
                  :key="p.periodo"
                  @click="ordenarPor('venta_periodo_' + p.periodo)"
                  class="py-1.5 px-3 font-bold text-right whitespace-nowrap cursor-pointer transition-colors"
                  :class="p.es_actual ? 'bg-sky-50/50 border-l border-slate-200 hover:bg-sky-100/60' : 'bg-slate-100/50 hover:bg-slate-200/60'"
                  :title="'Clic para ordenar por Ventas ' + p.nombre"
                >
                  <div class="inline-flex items-center justify-end gap-1 w-full">
                    <div>
                      <div>Acum. {{ p.corto }}</div>
                      <div class="text-[8.5px] font-normal capitalize" :class="p.es_actual ? 'text-sky-600' : 'text-slate-500'">
                        ({{ p.periodo }})
                      </div>
                    </div>
                    <span
                      class="text-[9px]"
                      :class="columnaOrden === ('venta_periodo_' + p.periodo) ? (p.es_actual ? 'text-sky-900 font-bold' : 'text-slate-900 font-bold') : 'text-slate-300'"
                    >
                      {{ columnaOrden === ('venta_periodo_' + p.periodo) ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('frecuencia_mes_actual')"
                  class="py-1.5 px-3 font-bold text-center whitespace-nowrap bg-sky-50/50 cursor-pointer hover:bg-sky-100/60 transition-colors"
                  title="Clic para ordenar por Frecuencia Mes Actual"
                >
                  <div class="inline-flex items-center justify-center gap-1 w-full">
                    <div>
                      <div>Frec. Act.</div>
                      <div v-if="periodosHistoricos[0]?.corto" class="text-[8.5px] font-normal text-sky-600 capitalize">
                        ({{ periodosHistoricos[0].corto }})
                      </div>
                    </div>
                    <span class="text-[9px]" :class="columnaOrden === 'frecuencia_mes_actual' ? 'text-sky-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'frecuencia_mes_actual' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('frecuencia_mes_anterior')"
                  class="py-1.5 px-3 font-bold text-center whitespace-nowrap bg-slate-100/50 border-r border-slate-200 cursor-pointer hover:bg-slate-200/60 transition-colors"
                  title="Clic para ordenar por Frecuencia Mes Anterior"
                >
                  <div class="inline-flex items-center justify-center gap-1 w-full">
                    <div>
                      <div>Frec. Ant.</div>
                      <div v-if="periodosHistoricos[1]?.corto" class="text-[8.5px] font-normal text-slate-500 capitalize">
                        ({{ periodosHistoricos[1].corto }})
                      </div>
                    </div>
                    <span class="text-[9px]" :class="columnaOrden === 'frecuencia_mes_anterior' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'frecuencia_mes_anterior' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('tipo_ajuste')"
                  class="py-1.5 px-3 font-bold cursor-pointer hover:bg-slate-100 transition-colors"
                  title="Clic para ordenar por Tipo de Ajuste"
                >
                  <div class="inline-flex items-center gap-1">
                    <span>Tipo Ajuste</span>
                    <span class="text-[9px]" :class="columnaOrden === 'tipo_ajuste' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'tipo_ajuste' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th
                  @click="ordenarPor('observaciones')"
                  class="py-1.5 px-3 font-bold cursor-pointer hover:bg-slate-100 transition-colors"
                  title="Clic para ordenar por Observación"
                >
                  <div class="inline-flex items-center gap-1">
                    <span>Observación</span>
                    <span class="text-[9px]" :class="columnaOrden === 'observaciones' ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ columnaOrden === 'observaciones' ? (direccionOrden === 'asc' ? '▲' : '▼') : '↕' }}
                    </span>
                  </div>
                </th>
                <th class="py-1.5 px-3 font-bold text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-sans">
              <tr
                v-for="c in clientesConCambioFiltrados"
                :key="c.id"
                class="hover:bg-slate-50/80 transition-colors"
                :class="clientesSeleccionadosIds.includes(c.cliente_id || c.id) ? 'bg-sky-50/40' : ''"
              >
                <td class="py-2 px-2.5 text-center whitespace-nowrap">
                  <input
                    type="checkbox"
                    :value="c.cliente_id || c.id"
                    v-model="clientesSeleccionadosIds"
                    class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer w-3.5 h-3.5"
                  />
                </td>
                <td class="py-2 px-3 font-mono text-slate-500 text-[11px] whitespace-nowrap">
                  {{ c.cliente_id }}
                </td>
                <td class="py-2 px-3 whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <span class="font-semibold text-slate-900 text-xs">{{ c.nombre }}</span>
                    <span
                      v-if="(c.estado || '').toLowerCase() === 'inactivo'"
                      class="text-[9px] font-mono px-1.5 py-0.2 rounded border bg-rose-50 text-rose-700 border-rose-200 font-bold uppercase"
                    >
                      Inactivo
                    </span>
                  </div>
                  <div class="text-[10px] text-slate-400 truncate max-w-xs">{{ c.direccion || 'Sin dirección' }}</div>
                </td>
                <td class="py-2 px-3 whitespace-nowrap font-mono text-xs">
                  <!-- Ruta Anterior (Fija/Estática) -> Ruta Nueva (Desplegable directo) -->
                  <div class="flex items-center gap-1.5">
                    <span
                      class="text-slate-400 font-medium"
                      :title="'Ruta original / anterior: ' + formatRuta(c.ruta_original || c.ruta)"
                    >
                      {{ formatRuta(c.ruta_original || c.ruta) }}
                    </span>
                    <span class="text-slate-300">-></span>
                    <select
                      :value="c.ruta"
                      @change="cambiarRutaDirecta(c, $event.target.value)"
                      :disabled="guardandoFilaId === c.id"
                      class="h-7 px-2 bg-white border rounded text-xs font-mono font-bold focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer max-w-[140px] transition-colors"
                      :class="c.ruta_original && c.ruta_original.toUpperCase() !== c.ruta.toUpperCase() ? 'border-sky-500 text-sky-800 bg-sky-50/50' : 'border-slate-300 text-slate-800 hover:border-slate-400'"
                      title="Seleccionar ruta nueva"
                    >
                      <option v-for="r in rutasDisponiblesParaFronteras" :key="r.ruta" :value="r.ruta">
                        {{ formatRuta(r.ruta) }}
                      </option>
                    </select>
                  </div>
                </td>
                <td class="py-2 px-3 whitespace-nowrap font-mono text-xs">
                  <!-- Día Anterior (Fijo/Estático) -> Día Nuevo (Desplegable directo) -->
                  <div class="flex items-center gap-1.5">
                    <span
                      class="text-slate-400 font-medium"
                      :title="'Día original / anterior: ' + (c.dia_original || c.dia_norm)"
                    >
                      {{ c.dia_original || c.dia_norm }}
                    </span>
                    <span class="text-slate-300">-></span>
                    <select
                      :value="obtenerDiaValorSelect(c.dia_norm)"
                      @change="cambiarDiaDirecto(c, $event.target.value)"
                      :disabled="guardandoFilaId === c.id"
                      class="h-7 px-2 bg-white border rounded text-xs font-mono font-bold focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer transition-colors"
                      :class="c.dia_original && normalizarDiaClave(c.dia_original) !== normalizarDiaClave(c.dia_norm) ? 'border-sky-500 text-sky-800 bg-sky-50/50' : 'border-slate-300 text-slate-800 hover:border-slate-400'"
                      title="Seleccionar día de visita nuevo"
                    >
                      <option v-for="d in diasSemana" :key="d.key" :value="d.label">
                        {{ d.label }}
                      </option>
                    </select>
                  </div>
                </td>

                <!-- Acumulados de Ventas por Períodos Seleccionados -->
                <td
                  v-for="p in periodosVisibles"
                  :key="p.periodo"
                  class="py-2 px-3 text-right font-mono whitespace-nowrap"
                  :class="p.es_actual ? 'bg-sky-50/20 border-l border-slate-100' : 'bg-slate-50/30'"
                >
                  <span
                    class="font-semibold text-xs"
                    :class="getVentaClientePeriodo(c, p.periodo) > 0 ? (p.es_actual ? 'text-slate-900' : 'text-slate-700') : 'text-slate-300'"
                  >
                    {{ formatMonto(getVentaClientePeriodo(c, p.periodo)) }}
                  </span>
                </td>

                <!-- 3. Frecuencia de Compra Mes Actual -->
                <td class="py-2 px-3 text-center font-mono whitespace-nowrap bg-sky-50/20">
                  <span
                    class="px-2 py-0.5 rounded text-[11px] font-bold"
                    :class="(c.frecuencia_mes_actual || 0) > 0 ? 'bg-sky-100 text-sky-800 border border-sky-200' : 'bg-slate-100 text-slate-400 border border-slate-200'"
                  >
                    {{ c.frecuencia_mes_actual || 0 }}
                  </span>
                </td>

                <!-- 4. Frecuencia de Compra Mes Anterior -->
                <td class="py-2 px-3 text-center font-mono whitespace-nowrap bg-slate-50/30 border-r border-slate-100">
                  <span
                    class="px-2 py-0.5 rounded text-[11px] font-bold"
                    :class="(c.frecuencia_mes_anterior || 0) > 0 ? 'bg-slate-100 text-slate-700 border border-slate-200' : 'bg-slate-50 text-slate-300 border border-slate-200'"
                  >
                    {{ c.frecuencia_mes_anterior || 0 }}
                  </span>
                </td>

                <td class="py-2 px-3 whitespace-nowrap font-mono">
                  <div class="flex items-center gap-1">
                    <span
                      class="text-[9.5px] uppercase font-bold px-1.5 py-0.5 rounded border"
                      :class="{
                        'bg-purple-50 text-purple-800 border-purple-200': c.ruta_original && c.ruta_original.toUpperCase() !== c.ruta.toUpperCase() && c.dia_original && normalizarDiaClave(c.dia_original) !== normalizarDiaClave(c.dia_norm),
                        'bg-blue-50 text-blue-800 border-blue-200': c.ruta_original && c.ruta_original.toUpperCase() !== c.ruta.toUpperCase() && (!c.dia_original || normalizarDiaClave(c.dia_original) === normalizarDiaClave(c.dia_norm)),
                        'bg-amber-50 text-amber-800 border-amber-200': (!c.ruta_original || c.ruta_original.toUpperCase() === c.ruta.toUpperCase()) && c.dia_original && normalizarDiaClave(c.dia_original) !== normalizarDiaClave(c.dia_norm),
                        'bg-slate-100 text-slate-700 border-slate-200': (!c.ruta_original || c.ruta_original.toUpperCase() === c.ruta.toUpperCase()) && (!c.dia_original || normalizarDiaClave(c.dia_original) === normalizarDiaClave(c.dia_norm))
                      }"
                    >
                      <template v-if="c.ruta_original && c.ruta_original.toUpperCase() !== c.ruta.toUpperCase() && c.dia_original && normalizarDiaClave(c.dia_original) !== normalizarDiaClave(c.dia_norm)">
                        Ruta y Día
                      </template>
                      <template v-else-if="c.ruta_original && c.ruta_original.toUpperCase() !== c.ruta.toUpperCase()">
                        Cambio Ruta
                      </template>
                      <template v-else-if="c.dia_original && normalizarDiaClave(c.dia_original) !== normalizarDiaClave(c.dia_norm)">
                        Cambio Día
                      </template>
                      <template v-else>
                        Propuesta
                      </template>
                    </span>
                    <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                      {{ c.propuesta_estado || 'PENDIENTE' }}
                    </span>
                  </div>
                </td>
                <td class="py-2 px-3 text-[11px] text-slate-600 truncate max-w-xs">
                  {{ c.observaciones_propuesta || 'Ajuste dinámico RTM' }}
                </td>
                <td class="py-2 px-3 text-right whitespace-nowrap space-x-1.5">
                  <span
                    v-if="guardandoFilaId === c.id"
                    class="text-[11px] font-semibold text-sky-600 inline-flex items-center gap-1 mr-1"
                  >
                    <span class="w-2.5 h-2.5 border-2 border-sky-600 border-t-transparent rounded-full animate-spin"></span>
                    Guardando...
                  </span>
                  <button
                    type="button"
                    @click="enfocarClienteEnMapa(c)"
                    class="px-2 py-1 text-[11px] font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded transition-colors cursor-pointer"
                    title="Localizar y ver en mapa"
                  >
                    Ver en mapa
                  </button>
                  <button
                    v-if="(c.estado || '').toLowerCase() !== 'activo'"
                    type="button"
                    @click="activarClienteDirecto(c)"
                    :disabled="activandoClienteId === (c.cliente_id || c.id)"
                    class="px-2 py-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 hover:bg-emerald-100 rounded transition-colors cursor-pointer disabled:opacity-50"
                    title="Reactivar cliente a estado Activo"
                  >
                    {{ activandoClienteId === (c.cliente_id || c.id) ? 'Activando...' : 'Activar' }}
                  </button>
                  <button
                    v-if="c.propuesta_id"
                    type="button"
                    @click="descartarPropuesta(c)"
                    class="px-2 py-1 text-[11px] font-semibold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded transition-colors cursor-pointer"
                    title="Descartar propuesta y revertir"
                  >
                    Descartar
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-slate-100/95 text-slate-800 font-mono text-[11px] font-bold border-t-2 border-slate-300 sticky bottom-0 z-10 shadow-md">
              <tr>
                <td colspan="5" class="py-2 px-3 text-right uppercase tracking-wider text-slate-600">
                  TOTALES ({{ totalClientesUnicosMatrizInferior }} clis):
                </td>
                <td
                  v-for="p in periodosVisibles"
                  :key="p.periodo"
                  class="py-2 px-3 text-right whitespace-nowrap"
                  :class="p.es_actual ? 'bg-sky-100/70 text-slate-900 border-l border-slate-200' : 'bg-slate-200/70 text-slate-800'"
                >
                  {{ formatMonto(totalesMatrizInferiorPorPeriodo[p.periodo] || 0) }}
                </td>
                <td colspan="5" class="py-2 px-3"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- BARRA FLOTANTE DE ACCIONES EN LOTE         -->
      <!-- ========================================== -->
      <Teleport to="body">
        <div
          v-if="clientesSeleccionadosIds.length > 0"
          class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[99990] bg-slate-900 text-white rounded-xl shadow-2xl border border-slate-700/80 px-4 py-2.5 flex items-center gap-3 flex-wrap max-w-[95vw] animate-in fade-in slide-in-from-bottom-2 duration-150"
        >
          <div class="flex items-center gap-2 font-mono text-xs">
            <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
            <span class="font-bold">{{ clientesSeleccionadosIds.length }} seleccionados</span>
            <span
              v-if="clientesInactivosSeleccionadosCount > 0"
              class="text-[10px] bg-rose-500/20 text-rose-300 px-1.5 py-0.2 rounded border border-rose-500/30 font-semibold"
            >
              {{ clientesInactivosSeleccionadosCount }} inactivos
            </span>
          </div>

          <div class="h-4 w-px bg-slate-700 hidden sm:block"></div>

          <div class="flex items-center gap-2 flex-wrap">
            <!-- Botón Activar Seleccionados -->
            <button
              type="button"
              @click="ejecutarActivacionMasiva"
              :disabled="activandoMasivo"
              class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer"
              title="Reactivar los clientes seleccionados a estado Activo"
            >
              <span v-if="activandoMasivo" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              <span>{{ activandoMasivo ? 'Activando...' : 'Activar Seleccionados' }}</span>
            </button>

            <!-- Botón Exportar Seleccionados a Excel -->
            <button
              type="button"
              @click="exportarPropuestasExcel(true)"
              class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
              title="Descargar los clientes seleccionados en formato Excel (.csv)"
            >
              <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              <span>Exportar Excel ({{ clientesSeleccionadosIds.length }})</span>
            </button>

            <!-- Botón Limpiar Selección -->
            <button
              type="button"
              @click="clientesSeleccionadosIds = []"
              class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-medium transition-colors cursor-pointer"
            >
              Limpiar
            </button>
          </div>
        </div>
      </Teleport>

      <!-- ========================================== -->
      <!-- MODAL PARA AJUSTAR RTM DE UN CLIENTE       -->
      <!-- ========================================== -->
      <!-- MODAL DE CLIENTE Y MENÚ DE AJUSTAR RTM (Se abre directamente al hacer clic en un punto) -->
      <Teleport to="body">
        <div
          v-if="modalAjusteRtm.show"
          class="fixed inset-0 z-[99999] flex items-center justify-center p-2 sm:p-4 bg-slate-950/60 backdrop-blur-xs"
          @click.self="cerrarModalAjusteRtm"
        >
          <div
            id="modal-ajuste-rtm-dialog"
            tabindex="-1"
            class="bg-white rounded-xl sm:rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg max-h-[92vh] sm:max-h-[88vh] flex flex-col overflow-hidden text-slate-900 font-sans animate-in fade-in zoom-in-95 duration-150 outline-none"
          >
            <!-- Header Modal con Datos del Cliente -->
            <div class="px-3.5 sm:px-5 py-2.5 sm:py-3 bg-slate-50 border-b border-slate-200 shrink-0">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                  <span
                    class="text-[10px] font-mono font-bold px-2 py-0.5 rounded text-white shadow-2xs"
                    :style="{ backgroundColor: getRutaColor(modalAjusteRtm.cliente?.ruta) }"
                  >
                    {{ formatRuta(modalAjusteRtm.cliente?.ruta) }}
                  </span>
                  <span class="text-[10px] text-slate-600 font-mono font-bold">
                    ID: {{ modalAjusteRtm.cliente?.cliente_id }}
                  </span>
                  <span v-if="modalAjusteRtm.cliente?.canal || getCanalRuta(modalAjusteRtm.cliente?.ruta)" class="text-[9.5px] text-slate-400 font-mono">
                    ({{ modalAjusteRtm.cliente?.canal || getCanalRuta(modalAjusteRtm.cliente?.ruta) }})
                  </span>
                  <span
                    class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded border uppercase"
                    :class="(modalAjusteRtm.cliente?.estado || '').toLowerCase() === 'activo' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                  >
                    {{ modalAjusteRtm.cliente?.estado || 'Activo' }}
                  </span>
                  <button
                    v-if="(modalAjusteRtm.cliente?.estado || '').toLowerCase() !== 'activo'"
                    type="button"
                    @click="activarClienteDirecto(modalAjusteRtm.cliente)"
                    :disabled="activandoClienteId === (modalAjusteRtm.cliente?.cliente_id || modalAjusteRtm.cliente?.id)"
                    class="px-2 py-0.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[9.5px] font-bold flex items-center gap-1 cursor-pointer transition-colors shadow-2xs disabled:opacity-50"
                    title="Reactivar cliente a estado Activo inmediatamente"
                  >
                    <span v-if="activandoClienteId === (modalAjusteRtm.cliente?.cliente_id || modalAjusteRtm.cliente?.id)" class="w-2.5 h-2.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>{{ activandoClienteId === (modalAjusteRtm.cliente?.cliente_id || modalAjusteRtm.cliente?.id) ? '...' : 'Activar' }}</span>
                  </button>
                </div>

                <!-- Botón Cerrar Táctil ✕ -->
                <button
                  type="button"
                  @click="cerrarModalAjusteRtm"
                  class="w-7 h-7 flex items-center justify-center rounded-full text-slate-400 hover:text-slate-700 hover:bg-slate-200/80 cursor-pointer transition-colors shrink-0"
                  title="Cerrar modal"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Nombre y Dirección + Acciones Rápidas -->
              <div class="mt-1 min-w-0">
                <h3 class="text-xs sm:text-sm font-bold text-slate-900 line-clamp-1 leading-snug" :title="modalAjusteRtm.cliente?.nombre">
                  {{ modalAjusteRtm.cliente?.nombre }}
                </h3>
                <div class="flex items-center justify-between gap-2 mt-0.5">
                  <p class="text-[10.5px] text-slate-500 truncate flex-1" :title="modalAjusteRtm.cliente?.direccion">
                    {{ modalAjusteRtm.cliente?.direccion || 'Sin dirección registrada' }}
                  </p>
                  <div class="flex items-center gap-1 shrink-0 text-[9.5px] font-semibold">
                    <a
                      v-if="modalAjusteRtm.cliente?.latitud && modalAjusteRtm.cliente?.longitud"
                      :href="'https://www.google.com/maps?q=' + modalAjusteRtm.cliente?.latitud + ',' + modalAjusteRtm.cliente?.longitud"
                      target="_blank"
                      class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded border border-slate-300 flex items-center gap-0.5 transition-colors"
                      title="Abrir en Google Maps"
                    >
                      Maps
                    </a>
                    <a
                      v-if="getCleanTel(modalAjusteRtm.cliente)"
                      :href="'https://wa.me/591' + getCleanTel(modalAjusteRtm.cliente)"
                      target="_blank"
                      class="px-1.5 py-0.5 bg-slate-900 hover:bg-slate-800 text-white rounded flex items-center gap-0.5 transition-colors"
                      title="Enviar WhatsApp"
                    >
                      WA
                    </a>
                    <a
                      v-if="getCleanTel(modalAjusteRtm.cliente)"
                      :href="'tel:' + getCleanTel(modalAjusteRtm.cliente)"
                      class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded border border-slate-300 flex items-center gap-0.5 transition-colors"
                      title="Llamar teléfono"
                    >
                      Tel
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <!-- Selector de Pestañas del Modal -->
            <div class="flex items-center border-b border-slate-200 bg-slate-100 px-3 sm:px-4 pt-1 gap-2 text-xs shrink-0 select-none">
              <button
                type="button"
                @click="modalAjusteRtm.tabActivo = 'rtm'"
                class="pb-1.5 px-2.5 font-bold border-b-2 transition-all flex items-center gap-1.5 cursor-pointer text-xs"
                :class="modalAjusteRtm.tabActivo === 'rtm' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'"
              >
                <span>Ajustar RTM (Ruta / Día)</span>
              </button>

              <button
                type="button"
                @click="modalAjusteRtm.tabActivo = 'visitas'"
                class="pb-1.5 px-2.5 font-bold border-b-2 transition-all flex items-center gap-1.5 cursor-pointer text-xs"
                :class="modalAjusteRtm.tabActivo === 'visitas' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'"
              >
                <span>Fotos y Visitas</span>
                <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full bg-slate-200 text-slate-700 font-semibold">
                  {{ visitasCliente.length }}
                </span>
              </button>
            </div>

            <!-- CUERPO DEL MODAL (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-3 sm:p-4 custom-scroll text-xs space-y-3">
              <!-- MÉTRICAS DE COMPRA FACT_VENTAS (Acordeón Plegable) -->
              <div class="rounded-xl bg-slate-50 border border-slate-200/90 overflow-hidden">
                <button
                  type="button"
                  @click="mostrarVentasDetalle = !mostrarVentasDetalle"
                  class="w-full px-3 py-2 flex items-center justify-between text-left hover:bg-slate-100/70 transition-colors cursor-pointer select-none"
                >
                  <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-3.5 h-3.5 text-[#0071E3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[10.5px] font-bold text-slate-800 uppercase tracking-wider font-mono">
                      Compras fact_ventas
                    </span>
                    <span v-if="kpisVentasCliente" class="text-[10px] font-mono text-slate-500">
                      ({{ periodosVisibles.length }}M)
                    </span>
                  </div>
                  <div class="flex items-center gap-1 text-[10px] text-slate-500 font-mono shrink-0">
                    <span>{{ mostrarVentasDetalle ? 'Ocultar' : 'Ver detalle' }}</span>
                    <svg class="w-3.5 h-3.5 transition-transform" :class="mostrarVentasDetalle ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                  </div>
                </button>

                <!-- Estado de Carga -->
                <div v-if="cargandoKpisVentas" class="py-2.5 px-3 text-center text-xs text-slate-400 animate-pulse font-mono flex items-center justify-center gap-1.5 border-t border-slate-200/60">
                  <div class="w-3.5 h-3.5 border-2 border-slate-400 border-t-transparent rounded-full animate-spin"></div>
                  <span>Consultando fact_ventas...</span>
                </div>

                <!-- Grilla de Meses Históricos Seleccionados (cuando está expandido) -->
                <div
                  v-else-if="mostrarVentasDetalle && kpisVentasCliente"
                  class="p-2.5 border-t border-slate-200/60 grid gap-2"
                  :class="{
                    'grid-cols-1': periodosVisibles.length === 1,
                    'grid-cols-2': periodosVisibles.length === 2,
                    'grid-cols-2 sm:grid-cols-3': periodosVisibles.length >= 3,
                  }"
                >
                  <div
                    v-for="p in periodosVisibles"
                    :key="p.periodo"
                    class="p-2 rounded-lg bg-white border border-slate-200/90 shadow-2xs space-y-0.5"
                  >
                    <div class="flex items-center justify-between text-[10px] font-mono">
                      <span
                        class="font-bold uppercase tracking-wide truncate max-w-[85px]"
                        :class="p.es_actual ? 'text-sky-700' : 'text-slate-500'"
                        :title="p.nombre"
                      >
                        {{ p.nombre }}
                      </span>
                      <span
                        class="text-[8.5px] font-bold px-1 py-0.2 rounded font-mono truncate"
                        :class="p.es_actual ? 'bg-sky-50 text-[#0071E3] border border-sky-200' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                      >
                        {{ p.corto }}
                      </span>
                    </div>
                    <div>
                      <div class="text-[9px] text-slate-400 font-mono">Monto:</div>
                      <div
                        class="text-[11px] font-bold font-mono leading-tight"
                        :class="p.es_actual ? 'text-slate-900' : 'text-slate-700'"
                      >
                        {{ formatMonto(getKpisPeriodoCliente(kpisVentasCliente, p.periodo)?.monto_acumulado ?? getVentaClientePeriodo(modalAjusteRtm.cliente, p.periodo)) }}
                      </div>
                    </div>
                    <div class="pt-0.5 border-t border-slate-100 flex items-center justify-between text-[9px] font-mono">
                      <span class="text-slate-400">Frec:</span>
                      <span
                        class="font-bold"
                        :class="p.es_actual ? 'text-slate-800' : 'text-slate-700'"
                      >
                        {{ getKpisPeriodoCliente(kpisVentasCliente, p.periodo)?.frecuencia_compra ?? getFrecuenciaClientePeriodo(modalAjusteRtm.cliente, p.periodo) }} comp
                      </span>
                    </div>
                  </div>
                </div>

                <div v-else-if="mostrarVentasDetalle && !kpisVentasCliente" class="py-2 text-center text-[10.5px] text-slate-400 font-mono border-t border-slate-200/60">
                  Sin registros de compra en fact_ventas para este cliente.
                </div>
              </div>

              <!-- TAB 1: FORMULARIO DE AJUSTE RTM -->
              <div v-if="modalAjusteRtm.tabActivo === 'rtm'" class="space-y-3">
                <!-- Comparativa Compacta: Asignación Actual vs Nueva -->
                <div class="grid grid-cols-2 gap-2 p-2 sm:p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                  <div class="space-y-0.5 min-w-0">
                    <div class="text-[9px] text-slate-400 uppercase font-bold font-mono tracking-wider">Actual</div>
                    <div class="flex items-center gap-1 min-w-0">
                      <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(modalAjusteRtm.cliente?.ruta) }"></span>
                      <span class="font-bold font-mono text-[11px] text-slate-800 truncate">{{ formatRuta(modalAjusteRtm.cliente?.ruta) }}</span>
                    </div>
                    <div class="text-[10px] font-mono" :style="{ color: getDiaColor(modalAjusteRtm.cliente?.dia_norm) }">
                      {{ modalAjusteRtm.cliente?.dia_norm || 'S/D' }}
                    </div>
                    <div class="text-[9px] text-slate-400 truncate">
                      {{ getVendedorRuta(modalAjusteRtm.cliente?.ruta) }}
                    </div>
                  </div>

                  <div class="space-y-0.5 pl-2 border-l border-slate-200 min-w-0">
                    <div class="text-[9px] text-sky-600 uppercase font-bold font-mono tracking-wider">Nueva Propuesta</div>
                    <div class="flex items-center gap-1 min-w-0">
                      <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(formRtm.ruta_propuesta) }"></span>
                      <span class="font-bold font-mono text-[11px] text-slate-900 truncate">{{ formatRuta(formRtm.ruta_propuesta) }}</span>
                    </div>
                    <div class="text-[10px] font-mono font-bold" :style="{ color: getDiaColor(formRtm.dia_propuesto) }">
                      {{ formRtm.dia_propuesto }}
                    </div>
                    <div class="text-[9px] text-slate-500 truncate">
                      {{ getVendedorRuta(formRtm.ruta_propuesta) }}
                    </div>
                  </div>
                </div>

                <!-- Selectores de Ruta y Día -->
                <div class="space-y-2.5 pt-0.5">
                  <!-- Selector de Nueva Ruta -->
                  <div>
                    <label class="block text-[10.5px] font-bold text-slate-700 mb-1">
                      Nueva Ruta RTM:
                    </label>
                    <select
                      v-model="formRtm.ruta_propuesta"
                      required
                      class="w-full h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs font-mono font-semibold text-slate-900 focus:outline-none focus:border-slate-500 cursor-pointer"
                    >
                      <option v-for="r in rutasFiltradasParaAjuste" :key="r.ruta" :value="r.ruta">
                        {{ formatRuta(r.ruta) }} ({{ r.canal }}) — {{ r.vendedor || 'Sin vendedor' }}
                      </option>
                    </select>
                  </div>

                  <!-- Selector de Nuevo Día de Visita -->
                  <div>
                    <label class="block text-[10.5px] font-bold text-slate-700 mb-1">
                      Nuevo Día de Visita:
                    </label>
                    <select
                      v-model="formRtm.dia_propuesto"
                      required
                      class="w-full h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs font-mono font-semibold text-slate-900 focus:outline-none focus:border-slate-500 cursor-pointer"
                    >
                      <option v-for="d in diasSemana" :key="d.key" :value="d.label">
                        {{ d.label }}
                      </option>
                    </select>
                  </div>

                  <!-- Motivo / Observaciones -->
                  <div>
                    <label class="block text-[10.5px] font-bold text-slate-700 mb-1">
                      Motivo / Observación del Ajuste:
                    </label>
                    <input
                      type="text"
                      v-model="formRtm.observaciones"
                      placeholder="Ej: Rebalanceo territorial, ajuste de frontera..."
                      class="w-full h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-slate-500"
                    />
                  </div>

                  <!-- Toggle para Activar Cliente si es Inactivo -->
                  <div
                    v-if="(modalAjusteRtm.cliente?.estado || '').toLowerCase() !== 'activo'"
                    class="p-2 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between gap-2"
                  >
                    <div class="text-[10.5px] text-emerald-950">
                      <span class="font-bold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Activar cliente al guardar:
                      </span>
                      <p class="text-[9.5px] text-emerald-700">
                        Pasará inmediatamente a <b>Activo</b> en <code>pan_ruteo</code>.
                      </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input
                        type="checkbox"
                        v-model="formRtm.activar_cliente"
                        class="w-4 h-4 rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                      />
                    </label>
                  </div>
                </div>
              </div>

              <!-- TAB 2: HISTORIAL DE VISITAS & FOTOS DE PREVENTA -->
              <div v-else class="space-y-3">
                <div v-if="cargandoVisitas" class="py-8 text-center text-xs text-slate-400 animate-pulse flex flex-col items-center justify-center gap-2">
                  <span class="w-5 h-5 border-2 border-slate-600 border-t-transparent rounded-full animate-spin"></span>
                  <span>Cargando fotos y visitas registradas en terreno...</span>
                </div>

                <div v-else-if="visitasCliente.length === 0" class="py-8 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                  <p class="font-medium text-slate-600">Sin visitas recientes con fotografía</p>
                  <p class="text-[10.5px] text-slate-400 mt-1">Este cliente no registra evidencias fotográficas de preventa aún.</p>
                </div>

                <div v-else class="space-y-2.5">
                  <div
                    v-for="vis in visitasCliente"
                    :key="vis.id"
                    class="p-2.5 bg-slate-50 border border-slate-200/90 rounded-xl text-xs space-y-1.5 shadow-2xs"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="font-mono text-slate-700 font-bold text-[10.5px]">{{ vis.fecha }} {{ vis.hora }}</span>
                        <span class="text-slate-400 font-mono text-[9.5px]">Ruta: {{ formatRuta(vis.route) }}</span>
                      </div>
                      <span
                        class="px-1.5 py-0.2 rounded text-[9.5px] font-mono uppercase font-bold"
                        :class="vis.status === 'VISITED' ? 'bg-slate-200 text-slate-800 border border-slate-300' : 'bg-slate-100 text-slate-700'"
                      >
                        {{ vis.status || 'VISITADO' }}
                      </span>
                    </div>

                    <p v-if="vis.comments" class="text-[10.5px] text-slate-600 italic bg-white p-1.5 rounded-lg border border-slate-200/60">
                      "{{ vis.comments }}"
                    </p>

                    <div
                      v-if="vis.photo_url"
                      class="mt-1 group/photo relative cursor-pointer overflow-hidden rounded-lg border border-slate-200 bg-slate-900"
                      @click="abrirLightboxFoto(vis)"
                      title="Clic para ampliar y rotar foto"
                    >
                      <img
                        :src="vis.photo_url"
                        alt="Foto de visita"
                        class="w-full h-36 sm:h-44 object-cover group-hover/photo:scale-105 transition-transform duration-200"
                      />
                      <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/photo:opacity-100 transition-opacity flex items-center justify-center gap-1.5 text-white text-xs font-semibold backdrop-blur-[1px]">
                        <svg class="w-4 h-4 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                        </svg>
                        <span>Ver foto</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- FOOTER FIJO (Sticky Bottom) - Siempre accesible en cualquier resolución -->
            <div class="px-3.5 sm:px-5 py-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2 shrink-0">
              <button
                type="button"
                @click="cerrarModalAjusteRtm"
                class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer transition-colors"
              >
                {{ modalAjusteRtm.tabActivo === 'rtm' ? 'Cancelar' : 'Cerrar' }}
              </button>

              <button
                v-if="modalAjusteRtm.tabActivo === 'rtm'"
                type="button"
                @click="confirmarAjusteRtm"
                :disabled="guardandoRtm"
                title="Confirmar ajuste RTM (Presiona Enter)"
                class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white rounded-lg text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 transition-colors"
              >
                <span v-if="guardandoRtm" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>{{ guardandoRtm ? 'Guardando Ajuste...' : 'Confirmar Ajuste RTM' }}</span>
                <span class="hidden sm:inline-block text-[10px] bg-slate-800 text-slate-300 px-1 py-0.2 rounded border border-slate-700 font-mono ml-0.5">Enter</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- VISOR LIGHTBOX MODAL CON ROTACIÓN -->
      <PhotoLightboxModal
        :show="fotoLightbox.show"
        :photo-url="fotoLightbox.url"
        :title="fotoLightbox.title"
        :subtitle="fotoLightbox.subtitle"
        :comments="fotoLightbox.comments"
        @close="fotoLightbox.show = false"
      />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue';
import { PhotoLightboxModal } from '@/Components/UI';
import { mapsConfig } from '@/Config/maps';
import { getCanalColor, getRutaColor, formatRuta, getDiaColor, registerRutasCatalogo, DIAS_SEMANA_CONFIG } from '@/Config/colors';
import { descargarExcelXlsx } from '@/Utils/exportExcel';

const diasSemanaConfig = DIAS_SEMANA_CONFIG;
import L from 'leaflet';

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => [],
  },
  canales: {
    type: Array,
    default: () => [],
  },
  submoduloActivo: {
    type: String,
    default: 'visor',
  },
});

// Estado de Lightbox con Rotación
const fotoLightbox = ref({
  show: false,
  url: '',
  title: '',
  subtitle: '',
  comments: '',
});

function abrirLightboxFoto(vis) {
  fotoLightbox.value = {
    show: true,
    url: vis.photo_url,
    title: clienteSeleccionado.value ? clienteSeleccionado.value.nombre : 'Evidencia de Visita',
    subtitle: `${vis.fecha || ''} ${vis.hora || ''} • Ruta: ${formatRuta(vis.route)} • ${vis.status || 'VISITADO'}`,
    comments: vis.comments || '',
  };
}

const diasSemana = [
  { key: 'LUNES', corto: 'LUN', label: 'LUNES', color: getDiaColor('LUNES') },
  { key: 'MARTES', corto: 'MAR', label: 'MARTES', color: getDiaColor('MARTES') },
  { key: 'MIERCOLES', corto: 'MIÉ', label: 'MIÉRCOLES', color: getDiaColor('MIERCOLES') },
  { key: 'JUEVES', corto: 'JUE', label: 'JUEVES', color: getDiaColor('JUEVES') },
  { key: 'VIERNES', corto: 'VIE', label: 'VIERNES', color: getDiaColor('VIERNES') },
  { key: 'SABADO', corto: 'SÁB', label: 'SÁBADO', color: getDiaColor('SABADO') },
  { key: 'DOMINGO', corto: 'DOM', label: 'DOMINGO', color: getDiaColor('DOMINGO') },
];

const DIAS_LUNES_A_SABADO = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

function normalizarDiaClave(val) {
  if (!val) return '';
  return String(val)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toUpperCase();
}

function normalizarRutaClave(val) {
  if (val === null || val === undefined) return '';
  return String(val).trim().toUpperCase();
}

// ── MODOS DEL VISOR Y FRONTERAS ──
// modoPrincipal: 'visualizacion' | 'ajuste_rtm'
const modoPrincipal = ref('visualizacion');
// subModoVisualizacion: 'original' (base inmutable pan_ruteo) | 'cambios' (pan_ruteo + propuestas activas)
const subModoVisualizacion = ref('original');
// subHerramientaRtm: 'fronteras' (mover polígonos) | 'reasignacion' (reasignar rutas/días de clientes)
const subHerramientaRtm = ref('fronteras');

// Selección múltiple para reasignación en lote y activación (Modo Ajuste RTM y Matriz)
const clientesSeleccionadosIds = ref([]);
const activandoClienteId = ref(null);
const activandoMasivo = ref(false);

const clientesInactivosSeleccionadosCount = computed(() => {
  if (clientesSeleccionadosIds.value.length === 0) return 0;
  const idsSet = new Set(clientesSeleccionadosIds.value.map(String));
  return clientes.value.filter(c => idsSet.has(String(c.cliente_id || c.id)) && (c.estado || '').toLowerCase() !== 'activo').length;
});

const formReasignacionMasiva = ref({
  ruta_propuesta: '',
  dia_propuesto: '',
  observaciones: '',
});
const guardandoReasignacionMasiva = ref(false);

// Estados de Filtros Reactivos (NO autoejecutan consulta)
const filtroCanales = ref(props.canales?.includes('TDB') ? ['TDB'] : (props.canales?.[0] ? [props.canales[0]] : []));
const filtroRutas = ref([]);
const filtroDias = ref([]);
const verFronteraRutas = ref(true);
const verFronteraDias = ref(true);

// Filtros granulares por ruta para polígonos de fronteras GIS
const filtroFronterasRutas = ref([]);
const filtroFronterasDias = ref([]);
const busquedaFronteraRuta = ref('');
const busquedaFronteraDia = ref('');
const filtroEstadoCliente = ref('activos'); // 'activos' | 'inactivos' | 'todos'
const soloActivos = computed({
  get: () => filtroEstadoCliente.value === 'activos',
  set: (val) => {
    filtroEstadoCliente.value = val ? 'activos' : 'todos';
  }
});

function setFiltroEstadoCliente(nuevoEstado) {
  if (filtroEstadoCliente.value === nuevoEstado) return;
  filtroEstadoCliente.value = nuevoEstado;
  cargarDatos();
}
const cargando = ref(false);
const vistaMovil = ref('mapa');
const tabLateral = ref('lista'); // 'lista' | 'balance'
const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap');

// Modo de coloración: 'ruta' | 'dia' | 'canal'
const modoColor = ref('ruta');

const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const busquedaRuta = ref('');
const busquedaCliente = ref('');
const clienteActivoId = ref(null);
const clienteSeleccionado = ref(null);
const visitasCliente = ref([]);
const cargandoVisitas = ref(false);

// Métricas de Compra desde fact_ventas (Mes actual y Mes anterior)
const kpisVentasCliente = ref(null);
const cargandoKpisVentas = ref(false);
const kpisVentasCache = reactive(new Map());

const clientes = ref([]);
const fronterasRutas = ref([]);
const fronterasDias = ref([]);
const kpis = ref({ total: 0, con_gps: 0, sin_gps: 0, con_cambios: 0, total_propuestas: 0 });
const toastMensaje = ref('');

// ── Herramienta de Fronteras RTM ──
const mostrarHerramientaFronteras = ref(false);
const rutaParaAjustarFrontera = ref('');
const editandoVertices = ref(false);
const guardandoFrontera = ref(false);
const modoAgregarVertice = ref(false);
const snapProximidadActivado = ref(true);
const textoSnapActivo = ref('');
let markerIndicadorSnap = null;
let geometriasVecinasCache = { vertices: [], segmentos: [] };
let markersVertices = [];
let markersPuntosIntermedios = [];
let poligonoEnEdicion = null;
const coordsPoligonoEnEdicion = ref([]);

// ── Modal de Ajuste Dinámico de RTM de Cliente ──
const modalAjusteRtm = ref({
  show: false,
  cliente: null,
  tabActivo: 'rtm',
});

const formRtm = ref({
  cliente_id: null,
  ruta_propuesta: '',
  dia_propuesto: 'LUNES',
  observaciones: '',
  aplicar_inmediato: false,
});
const guardandoRtm = ref(false);

let map = null;
let currentTileLayer = null;
let markersLayer = null;
let fronterasRutasLayer = null;
let fronterasDiasLayer = null;
const markersMap = new Map();

const rutasDisponibles = computed(() => {
  if (filtroCanales.value.length === 0) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => filtroCanales.value.includes(r.canal));
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    (r.ruta && r.ruta.toLowerCase().includes(q)) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === 0) return 'Todos';
  if (filtroCanales.value.length === 1) return filtroCanales.value[0];
  return `${filtroCanales.value.length} sel.`;
});

const labelDias = computed(() => {
  if (filtroDias.value.length === 0 || filtroDias.value.length === diasSemana.length) return 'Todos';
  if (filtroDias.value.length <= 2) {
    return filtroDias.value.map(k => diasSemana.find(d => d.key === k)?.corto || k).join(', ');
  }
  return `${filtroDias.value.length} días`;
});

const leyendaColapsada = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);
const mostrarVentasDetalle = ref(false);

const labelRutasPuntos = computed(() => {
  const total = rutasDisponiblesParaFronteras.value.length;
  if (total === 0) return 'Sin rutas';
  const activas = filtroRutas.value.length;
  if (activas === 0) return 'Ocultos (0)';
  if (activas === total) return `Todas (${total})`;
  if (activas === 1) return formatRuta(filtroRutas.value[0]);
  return `${activas}/${total}`;
});

const labelRutas = labelRutasPuntos;

const busquedaPuntosRuta = ref('');

const conteoPuntosPorRuta = computed(() => {
  const mapCounts = {};
  clientes.value.forEach(c => {
    if (c.ruta && c.tiene_gps) {
      const k = c.ruta.toUpperCase();
      mapCounts[k] = (mapCounts[k] || 0) + 1;
    }
  });
  return mapCounts;
});

// Rutas completas disponibles (afectadas por el canal seleccionado cuando está activo)
const rutasDisponiblesParaFronteras = computed(() => {
  const mapRutas = new Map();
  const setCanales = new Set((filtroCanales.value || []).map(c => String(c).trim().toUpperCase()).filter(Boolean));
  const canalFiltroActivo = setCanales.size > 0;

  // 1. Incluir rutas del catálogo filtradas por canal si hay filtro activo
  (props.catalogo_rutas || []).forEach(r => {
    const canalRuta = String(r.canal || '').trim().toUpperCase();
    if (canalFiltroActivo && !setCanales.has(canalRuta)) {
      return;
    }
    const rKey = normalizarRutaClave(r.ruta);
    if (!rKey) return;
    mapRutas.set(rKey, {
      ruta: rKey,
      nombre: formatRuta(rKey),
      canal: r.canal || '',
      vendedor: r.vendedor || '',
      color: getRutaColor(rKey),
    });
  });

  // 2. Incorporar rutas adicionales que pudieran venir en clientes o fronteras (si corresponden al canal activo)
  (clientes.value || []).forEach(c => {
    if (c.ruta) {
      const rKey = normalizarRutaClave(c.ruta);
      if (!mapRutas.has(rKey)) {
        const info = (props.catalogo_rutas || []).find(cr => normalizarRutaClave(cr.ruta) === rKey);
        const canalRuta = String(c.canal || (info ? info.canal : '')).trim().toUpperCase();
        if (canalFiltroActivo && canalRuta && !setCanales.has(canalRuta)) return;
        mapRutas.set(rKey, {
          ruta: rKey,
          nombre: formatRuta(rKey),
          canal: c.canal || (info ? info.canal : ''),
          vendedor: info ? info.vendedor : '',
          color: getRutaColor(rKey),
        });
      }
    }
  });

  (fronterasRutas.value || []).forEach(fr => {
    if (fr.ruta) {
      const rKey = normalizarRutaClave(fr.ruta);
      if (!mapRutas.has(rKey)) {
        const info = (props.catalogo_rutas || []).find(cr => normalizarRutaClave(cr.ruta) === rKey);
        const canalRuta = String(fr.canal || (info ? info.canal : '')).trim().toUpperCase();
        if (canalFiltroActivo && canalRuta && !setCanales.has(canalRuta)) return;
        mapRutas.set(rKey, {
          ruta: rKey,
          nombre: formatRuta(rKey),
          canal: fr.canal || (info ? info.canal : ''),
          vendedor: info ? info.vendedor : '',
          color: getRutaColor(rKey),
        });
      }
    }
  });

  (fronterasDias.value || []).forEach(fd => {
    if (fd.ruta) {
      const rKey = normalizarRutaClave(fd.ruta);
      if (!mapRutas.has(rKey)) {
        const info = (props.catalogo_rutas || []).find(cr => normalizarRutaClave(cr.ruta) === rKey);
        const canalRuta = String(fd.canal || (info ? info.canal : '')).trim().toUpperCase();
        if (canalFiltroActivo && canalRuta && !setCanales.has(canalRuta)) return;
        mapRutas.set(rKey, {
          ruta: rKey,
          nombre: formatRuta(rKey),
          canal: fd.canal || (info ? info.canal : ''),
          vendedor: info ? info.vendedor : '',
          color: getRutaColor(rKey),
        });
      }
    }
  });

  return Array.from(mapRutas.values()).sort((a, b) => a.ruta.localeCompare(b.ruta, undefined, { numeric: true }));
});

const rutasFiltradasEnDropdownPuntos = computed(() => {
  if (!busquedaPuntosRuta.value.trim()) return rutasDisponiblesParaFronteras.value;
  const q = busquedaPuntosRuta.value.toLowerCase().trim();
  return rutasDisponiblesParaFronteras.value.filter(r =>
    r.ruta.toLowerCase().includes(q) ||
    r.nombre.toLowerCase().includes(q) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

function seleccionarTodasRutasPuntos() {
  filtroRutas.value = rutasDisponiblesParaFronteras.value.map(r => r.ruta);
  renderizarEnMapa(true);
}

function limpiarRutasPuntos() {
  filtroRutas.value = [];
  renderizarEnMapa(true);
}

// Rutas con polígonos detectados
const rutasConPoligonoRuta = computed(() => {
  return new Set(fronterasRutas.value.map(fr => normalizarRutaClave(fr.ruta)).filter(Boolean));
});

const rutasConPoligonoDia = computed(() => {
  return new Set(fronterasDias.value.map(fd => normalizarRutaClave(fd.ruta)).filter(Boolean));
});

const rutasFiltradasEnDropdownFronteraRuta = computed(() => {
  if (!busquedaFronteraRuta.value.trim()) return rutasDisponiblesParaFronteras.value;
  const q = busquedaFronteraRuta.value.toLowerCase().trim();
  return rutasDisponiblesParaFronteras.value.filter(r =>
    r.ruta.toLowerCase().includes(q) ||
    r.nombre.toLowerCase().includes(q) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const rutasFiltradasEnDropdownFronteraDia = computed(() => {
  if (!busquedaFronteraDia.value.trim()) return rutasDisponiblesParaFronteras.value;
  const q = busquedaFronteraDia.value.toLowerCase().trim();
  return rutasDisponiblesParaFronteras.value.filter(r =>
    r.ruta.toLowerCase().includes(q) ||
    r.nombre.toLowerCase().includes(q) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelFronterasRutas = computed(() => {
  const total = rutasDisponiblesParaFronteras.value.length;
  if (total === 0) return 'Sin rutas';
  const activas = filtroFronterasRutas.value.length;
  if (activas === 0) return 'Ocultas (0)';
  if (activas === total) return `Todas (${total})`;
  if (activas === 1) return formatRuta(filtroFronterasRutas.value[0]);
  return `${activas}/${total}`;
});

const labelFronterasDias = computed(() => {
  const total = rutasDisponiblesParaFronteras.value.length;
  if (total === 0) return 'Sin rutas';
  const activas = filtroFronterasDias.value.length;
  if (activas === 0) return 'Ocultas (0)';
  if (activas === total) return `Todas (${total})`;
  if (activas === 1) return formatRuta(filtroFronterasDias.value[0]);
  return `${activas}/${total}`;
});

function seleccionarTodasFronterasRutas() {
  filtroFronterasRutas.value = rutasDisponiblesParaFronteras.value.map(r => r.ruta);
  renderizarEnMapa(true);
}

function limpiarFronterasRutas() {
  filtroFronterasRutas.value = [];
  renderizarEnMapa(true);
}

function seleccionarTodasFronterasDias() {
  filtroFronterasDias.value = rutasDisponiblesParaFronteras.value.map(r => r.ruta);
  renderizarEnMapa(true);
}

function limpiarFronterasDias() {
  filtroFronterasDias.value = [];
  renderizarEnMapa(true);
}

function sincronizarFiltrosConRutasDisponibles(forzarTodos = false) {
  const todasRutasKeys = rutasDisponiblesParaFronteras.value.map(r => r.ruta);
  const setTodas = new Set(todasRutasKeys.map(r => normalizarRutaClave(r)));

  // 1. Filtro Rutas (Puntos de clientes)
  if (forzarTodos || filtroRutas.value.length === 0) {
    filtroRutas.value = [...todasRutasKeys];
  } else {
    const validas = filtroRutas.value.filter(r => setTodas.has(normalizarRutaClave(r)));
    filtroRutas.value = validas.length > 0 ? validas : [...todasRutasKeys];
  }

  // 2. Filtro Fronteras Rutas (Polígonos de rutas)
  if (forzarTodos || filtroFronterasRutas.value.length === 0) {
    filtroFronterasRutas.value = [...todasRutasKeys];
  } else {
    const validas = filtroFronterasRutas.value.filter(r => setTodas.has(normalizarRutaClave(r)));
    filtroFronterasRutas.value = validas.length > 0 ? validas : [...todasRutasKeys];
  }

  // 3. Filtro Fronteras Días (Polígonos de días)
  if (forzarTodos || filtroFronterasDias.value.length === 0) {
    filtroFronterasDias.value = [...todasRutasKeys];
  } else {
    const validas = filtroFronterasDias.value.filter(r => setTodas.has(normalizarRutaClave(r)));
    filtroFronterasDias.value = validas.length > 0 ? validas : [...todasRutasKeys];
  }
}

const rutasFiltradasParaAjuste = computed(() => {
  return getRutasParaSelectorPropuesta();
});

const clientesFiltradosLista = computed(() => {
  if (!busquedaCliente.value.trim()) return clientes.value;
  const q = busquedaCliente.value.toLowerCase();
  return clientes.value.filter(c =>
    (c.nombre && c.nombre.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q))
  );
});

const todosClientesFiltradosSeleccionados = computed(() => {
  if (clientesFiltradosLista.value.length === 0) return false;
  return clientesFiltradosLista.value.every(c => clientesSeleccionadosIds.value.includes(c.cliente_id || c.id));
});

function toggleSeleccionarTodosClientes() {
  if (todosClientesFiltradosSeleccionados.value) {
    const idsDesmarcar = new Set(clientesFiltradosLista.value.map(c => c.cliente_id || c.id));
    clientesSeleccionadosIds.value = clientesSeleccionadosIds.value.filter(id => !idsDesmarcar.has(id));
  } else {
    const nuevosIds = new Set(clientesSeleccionadosIds.value);
    clientesFiltradosLista.value.forEach(c => nuevosIds.add(c.cliente_id || c.id));
    clientesSeleccionadosIds.value = Array.from(nuevosIds);
  }
}

const todosClientesTablaSeleccionados = computed(() => {
  if (clientesConCambioFiltrados.value.length === 0) return false;
  return clientesConCambioFiltrados.value.every(c => clientesSeleccionadosIds.value.includes(c.cliente_id || c.id));
});

function toggleSeleccionarTodosClientesTabla() {
  if (todosClientesTablaSeleccionados.value) {
    const idsDesmarcar = new Set(clientesConCambioFiltrados.value.map(c => c.cliente_id || c.id));
    clientesSeleccionadosIds.value = clientesSeleccionadosIds.value.filter(id => !idsDesmarcar.has(id));
  } else {
    const nuevosIds = new Set(clientesSeleccionadosIds.value);
    clientesConCambioFiltrados.value.forEach(c => nuevosIds.add(c.cliente_id || c.id));
    clientesSeleccionadosIds.value = Array.from(nuevosIds);
  }
}

function setModoPrincipal(modo) {
  modoPrincipal.value = modo;
  if (modo === 'ajuste_rtm') {
    tabLateral.value = 'balance';
    clienteSeleccionado.value = null;
  } else {
    cancelarEdicionVertices();
    tabLateral.value = 'lista';
  }
  if (map) {
    map.closePopup();
  }
  cargarDatos(true);
}

function setSubModoVisualizacion(sub) {
  subModoVisualizacion.value = sub;
  cargarDatos(true);
}

// Balance RTM agrupado por ruta y día
const balanceRtmPorRuta = computed(() => {
  if (clientes.value.length === 0) return [];
  const mapRutas = new Map();

  // Asegurar que todas las rutas filtradas aparezcan en el balance
  if (filtroRutas.value.length > 0) {
    filtroRutas.value.forEach(r => {
      const rKey = normalizarRutaClave(r);
      const rutaInfo = props.catalogo_rutas.find(cr => normalizarRutaClave(cr.ruta) === rKey);
      mapRutas.set(rKey, {
        ruta: rKey,
        canal: rutaInfo ? rutaInfo.canal : 'General',
        total: 0,
        con_gps: 0,
        ventas_por_periodo: {},
        venta_mes_actual: 0,
        venta_mes_anterior: 0,
        venta_mes_anterior_2: 0,
        dias: {},
      });
    });
  }

  const esOrig = (modoPrincipal.value === 'visualizacion' && subModoVisualizacion.value === 'original');

  // Control para no duplicar conteo ni ventas de un mismo cliente en una misma ruta
  const clientesContabilizadosPorRuta = new Map();

  clientes.value.forEach(c => {
    // En Base Original usamos ruta_original y dia_original (de plan ruteo)
    // En Con Cambios usamos ruta y dia_norm efectivos (propuestas de la tabla de modificados)
    const rKey = normalizarRutaClave(esOrig ? (c.ruta_original || c.ruta) : (c.ruta || c.ruta_original)) || 'SIN RUTA';
    const diaClave = normalizarDiaClave(esOrig ? (c.dia_original || c.dia_norm) : c.dia_norm);

    // Si se tienen rutas filtradas específicas, omitir del balance rutas que no están en el filtro
    if (filtroRutas.value.length > 0 && !filtroRutas.value.some(fr => normalizarRutaClave(fr) === rKey)) {
      return;
    }

    if (!mapRutas.has(rKey)) {
      const rutaInfo = props.catalogo_rutas.find(r => normalizarRutaClave(r.ruta) === rKey);
      mapRutas.set(rKey, {
        ruta: rKey,
        canal: rutaInfo ? rutaInfo.canal : 'General',
        total: 0,
        con_gps: 0,
        ventas_por_periodo: {},
        venta_mes_actual: 0,
        venta_mes_anterior: 0,
        venta_mes_anterior_2: 0,
        dias: {},
      });
    }

    const item = mapRutas.get(rKey);
    const diaKey = diaClave || 'SIN DIA';
    // Carga de visitas por día
    item.dias[diaKey] = (item.dias[diaKey] || 0) + 1;

    // En el total por ruta y ventas históricas, solo se debe contar los clientes de lunes a sábado
    // y cada cliente único se contabiliza UNA SOLA VEZ para evitar duplicar montos
    if (DIAS_LUNES_A_SABADO.includes(diaClave)) {
      if (!clientesContabilizadosPorRuta.has(rKey)) {
        clientesContabilizadosPorRuta.set(rKey, new Set());
      }
      const setCids = clientesContabilizadosPorRuta.get(rKey);
      const cid = c.cliente_id || c.id;

      if (!setCids.has(cid)) {
        setCids.add(cid);
        item.total++;
        if (c.tiene_gps) item.con_gps++;

        periodosHistoricos.value.forEach(p => {
          const monto = getVentaClientePeriodo(c, p.periodo);
          item.ventas_por_periodo[p.periodo] = (item.ventas_por_periodo[p.periodo] || 0) + monto;
        });
      }
    }
  });

  const p0 = periodosHistoricos.value[0]?.periodo;
  const p1 = periodosHistoricos.value[1]?.periodo;
  const p2 = periodosHistoricos.value[2]?.periodo;

  mapRutas.forEach(item => {
    item.venta_mes_actual = p0 ? (item.ventas_por_periodo[p0] || 0) : 0;
    item.venta_mes_anterior = p1 ? (item.ventas_por_periodo[p1] || 0) : 0;
    item.venta_mes_anterior_2 = p2 ? (item.ventas_por_periodo[p2] || 0) : 0;
  });

  return Array.from(mapRutas.values()).sort((a, b) => b.total - a.total);
});

// Balance específico de la ruta seleccionada en Ajuste RTM
const balanceRutaSeleccionada = computed(() => {
  if (!rutaParaAjustarFrontera.value) return null;
  const rKey = normalizarRutaClave(rutaParaAjustarFrontera.value);
  return balanceRtmPorRuta.value.find(b => normalizarRutaClave(b.ruta) === rKey) || null;
});

// Determina si solo hay una ruta seleccionada o cargada en el mapa
const tieneUnaSolaRutaMarcada = computed(() => {
  if (filtroRutas.value.length === 1) return true;
  if (filtroRutas.value.length > 1) return false;
  if (clientes.value.length > 0) {
    const rutasUnicas = new Set(clientes.value.map(c => normalizarRutaClave(c.ruta)).filter(Boolean));
    return rutasUnicas.size === 1;
  }
  return false;
});

const modoColorEfectivo = computed(() => {
  if (modoPrincipal.value === 'ajuste_rtm') {
    return tieneUnaSolaRutaMarcada.value ? 'dia' : 'ruta';
  }
  return modoColor.value;
});

const tituloCodificacionLeyenda = computed(() => {
  if (modoPrincipal.value === 'ajuste_rtm') {
    return tieneUnaSolaRutaMarcada.value ? 'DÍA DE VISITA' : 'RUTA / DÍA';
  }
  return modoColor.value.toUpperCase();
});

// Elementos dinámicos para la leyenda del mapa
const elementosLeyenda = computed(() => {
  if (clientes.value.length === 0 || filtroRutas.value.length === 0) return [];
  const mapCounts = new Map();
  const modoEfectivo = modoColorEfectivo.value;
  const setRutasPuntosPermitidas = new Set(filtroRutas.value.map(r => normalizarRutaClave(r)));

  clientes.value.forEach(c => {
    if (!c.tiene_gps) return;
    const rKey = normalizarRutaClave(c.ruta);
    if (!setRutasPuntosPermitidas.has(rKey)) return;

    let key, nombre, color;

    if (modoEfectivo === 'dia') {
      key = c.dia_norm || 'Sin Día';
      nombre = c.dia_norm || 'Sin Día';
      color = getDiaColor(c.dia_norm);
    } else if (modoEfectivo === 'canal') {
      const rutaInfo = props.catalogo_rutas.find(r => r.ruta === c.ruta);
      const canal = rutaInfo ? rutaInfo.canal : 'General';
      key = canal;
      nombre = canal;
      color = getCanalColor(canal);
    } else {
      key = c.ruta || 'Sin Ruta';
      nombre = formatRuta(c.ruta);
      color = getRutaColor(c.ruta);
    }

    if (!mapCounts.has(key)) {
      mapCounts.set(key, { id: key, nombre, color, conteo: 0 });
    }
    mapCounts.get(key).conteo++;
  });

  return Array.from(mapCounts.values()).sort((a, b) => b.conteo - a.conteo);
});

// ── TABLA INFERIOR DE REGISTRO DE CAMBIOS ──
const tablaCambiosVisible = ref(typeof window !== 'undefined' ? window.innerWidth >= 1024 : false);
const busquedaTablaCambios = ref('');
const filtroMatrizInferior = ref('cambios'); // 'cambios' | 'todos'

// Altura y redimensionamiento dinámico de la matriz inferior
const altoTablaCambios = ref(240);
const esArrastrandoAltoTabla = ref(false);

const etiquetaAltoActual = computed(() => {
  if (altoTablaCambios.value <= 260) return 'Normal';
  if (altoTablaCambios.value <= 450) return 'Medio';
  if (altoTablaCambios.value < 700) return 'Grande';
  return 'Max';
});

function toggleTablaCambios() {
  tablaCambiosVisible.value = !tablaCambiosVisible.value;
  nextTick(() => {
    if (map) map.invalidateSize();
  });
}

function setAltoTabla(px) {
  tablaCambiosVisible.value = true;
  altoTablaCambios.value = px;
  if (typeof window !== 'undefined' && window.localStorage) {
    localStorage.setItem('visor_alto_tabla_rtm', String(px));
  }
  nextTick(() => {
    if (map) map.invalidateSize();
  });
}

function ciclarMaximizarTabla() {
  if (altoTablaCambios.value >= 700) {
    setAltoTabla(220);
  } else {
    const maxH = Math.min(Math.round(window.innerHeight * 0.75), 850);
    setAltoTabla(maxH);
  }
}

function ciclarAltoTabla() {
  tablaCambiosVisible.value = true;
  if (altoTablaCambios.value <= 260) {
    setAltoTabla(390);
  } else if (altoTablaCambios.value <= 450) {
    setAltoTabla(560);
  } else if (altoTablaCambios.value < 700) {
    const maxH = Math.min(Math.round(window.innerHeight * 0.75), 850);
    setAltoTabla(maxH);
  } else {
    setAltoTabla(220);
  }
}

function iniciarArrastreAltoTabla(e) {
  e.preventDefault();
  tablaCambiosVisible.value = true;
  esArrastrandoAltoTabla.value = true;
  const startY = e.clientY;
  const startH = altoTablaCambios.value;

  function onMouseMove(moveEvent) {
    const deltaY = startY - moveEvent.clientY;
    const nuevoAlto = Math.max(140, Math.min(Math.round(window.innerHeight * 0.85), startH + deltaY));
    altoTablaCambios.value = nuevoAlto;
    if (map) map.invalidateSize();
  }

  function onMouseUp() {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    esArrastrandoAltoTabla.value = false;
    if (typeof window !== 'undefined' && window.localStorage) {
      localStorage.setItem('visor_alto_tabla_rtm', String(altoTablaCambios.value));
    }
    if (map) map.invalidateSize();
  }

  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
}

function iniciarArrastreTactilAltoTabla(e) {
  if (!e.touches || e.touches.length !== 1) return;
  tablaCambiosVisible.value = true;
  esArrastrandoAltoTabla.value = true;
  const startY = e.touches[0].clientY;
  const startH = altoTablaCambios.value;

  function onTouchMove(moveEvent) {
    if (!moveEvent.touches || moveEvent.touches.length !== 1) return;
    const deltaY = startY - moveEvent.touches[0].clientY;
    const nuevoAlto = Math.max(140, Math.min(Math.round(window.innerHeight * 0.85), startH + deltaY));
    altoTablaCambios.value = nuevoAlto;
    if (map) map.invalidateSize();
  }

  function onTouchEnd() {
    window.removeEventListener('touchmove', onTouchMove);
    window.removeEventListener('touchend', onTouchEnd);
    esArrastrandoAltoTabla.value = false;
    if (typeof window !== 'undefined' && window.localStorage) {
      localStorage.setItem('visor_alto_tabla_rtm', String(altoTablaCambios.value));
    }
    if (map) map.invalidateSize();
  }

  window.addEventListener('touchmove', onTouchMove, { passive: true });
  window.addEventListener('touchend', onTouchEnd);
}

function generarPeriodosHistoricosFallback() {
  const mesesEspanol = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  const mesesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
  const ahora = new Date();
  const res = [];
  for (let i = 0; i <= 10; i++) {
    const d = new Date(ahora.getFullYear(), ahora.getMonth() - i, 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const periodo = `${yyyy}-${mm}`;
    const mIdx = d.getMonth();
    const yy = String(yyyy).slice(-2);
    res.push({
      periodo: periodo,
      nombre: `${mesesEspanol[mIdx]} ${yyyy}`,
      corto: `${mesesCorto[mIdx]} ${yy}`,
      es_actual: i === 0,
      offset: i,
    });
  }
  return res;
}

const periodosHistoricos = ref(generarPeriodosHistoricosFallback());

// Filtro de meses históricos seleccionados (por defecto los últimos 3 meses: actual y 2 anteriores)
const mesesHistoricosSeleccionados = ref(
  periodosHistoricos.value.slice(0, 3).map(p => p.periodo)
);

const periodosVisibles = computed(() => {
  return periodosHistoricos.value.filter(p => mesesHistoricosSeleccionados.value.includes(p.periodo));
});

const esPreajusteUltimos3 = computed(() => {
  if (mesesHistoricosSeleccionados.value.length !== 3) return false;
  const p3 = periodosHistoricos.value.slice(0, 3).map(p => p.periodo);
  return p3.every(p => mesesHistoricosSeleccionados.value.includes(p));
});

function seleccionarPreajusteMeses(tipo) {
  if (tipo === 'todos') {
    mesesHistoricosSeleccionados.value = periodosHistoricos.value.map(p => p.periodo);
  } else if (tipo === 'ultimos_3') {
    mesesHistoricosSeleccionados.value = periodosHistoricos.value.slice(0, 3).map(p => p.periodo);
  } else if (tipo === 'actual') {
    if (periodosHistoricos.value.length > 0) {
      mesesHistoricosSeleccionados.value = [periodosHistoricos.value[0].periodo];
    }
  }
}

function toggleMesPeriodo(periodo) {
  if (mesesHistoricosSeleccionados.value.includes(periodo)) {
    if (mesesHistoricosSeleccionados.value.length === 1) return; // Evitar dejar 0 meses seleccionados
    mesesHistoricosSeleccionados.value = mesesHistoricosSeleccionados.value.filter(p => p !== periodo);
  } else {
    mesesHistoricosSeleccionados.value = [...mesesHistoricosSeleccionados.value, periodo];
  }
}

// Helpers para obtención dinámica de ventas y frecuencias históricas
function getVentaClientePeriodo(c, periodo) {
  if (!c || !periodo) return 0;
  if (c.ventas_historicas && c.ventas_historicas[periodo] !== undefined) {
    return Number(c.ventas_historicas[periodo].monto) || 0;
  }
  const idx = periodosHistoricos.value.findIndex(p => p.periodo === periodo);
  if (idx === 0 && c.venta_mes_actual !== undefined) return Number(c.venta_mes_actual) || 0;
  if (idx === 1 && c.venta_mes_anterior !== undefined) return Number(c.venta_mes_anterior) || 0;
  if (idx === 2 && c.venta_mes_anterior_2 !== undefined) return Number(c.venta_mes_anterior_2) || 0;
  return 0;
}

function getFrecuenciaClientePeriodo(c, periodo) {
  if (!c || !periodo) return 0;
  if (c.ventas_historicas && c.ventas_historicas[periodo] !== undefined) {
    return Number(c.ventas_historicas[periodo].frecuencia) || 0;
  }
  const idx = periodosHistoricos.value.findIndex(p => p.periodo === periodo);
  if (idx === 0 && c.frecuencia_mes_actual !== undefined) return Number(c.frecuencia_mes_actual) || 0;
  if (idx === 1 && c.frecuencia_mes_anterior !== undefined) return Number(c.frecuencia_mes_anterior) || 0;
  if (idx === 2 && c.frecuencia_mes_anterior_2 !== undefined) return Number(c.frecuencia_mes_anterior_2) || 0;
  return 0;
}

function getVentaRutaPeriodo(b, periodo) {
  if (!b || !periodo) return 0;
  if (b.ventas_por_periodo && b.ventas_por_periodo[periodo] !== undefined) {
    return Number(b.ventas_por_periodo[periodo]) || 0;
  }
  const idx = periodosHistoricos.value.findIndex(p => p.periodo === periodo);
  if (idx === 0 && b.venta_mes_actual !== undefined) return Number(b.venta_mes_actual) || 0;
  if (idx === 1 && b.venta_mes_anterior !== undefined) return Number(b.venta_mes_anterior) || 0;
  if (idx === 2 && b.venta_mes_anterior_2 !== undefined) return Number(b.venta_mes_anterior_2) || 0;
  return 0;
}

function getKpisPeriodoCliente(kpiObj, periodo) {
  if (!kpiObj || !periodo) return null;
  if (kpiObj.periodos && kpiObj.periodos[periodo]) {
    return kpiObj.periodos[periodo];
  }
  const p0 = periodosHistoricos.value[0]?.periodo;
  const p1 = periodosHistoricos.value[1]?.periodo;
  const p2 = periodosHistoricos.value[2]?.periodo;
  if (periodo === p0 && kpiObj.mes_actual) return kpiObj.mes_actual;
  if (periodo === p1 && kpiObj.mes_anterior) return kpiObj.mes_anterior;
  if (periodo === p2 && kpiObj.mes_anterior_2) return kpiObj.mes_anterior_2;
  return null;
}

// Compatibilidad con funciones previas
const opcionesMesesHistoricos = computed(() => {
  return periodosHistoricos.value.slice(0, 3).map((p) => ({
    key: p.periodo,
    corto: p.corto,
    nombre: p.nombre,
    titulo: p.nombre,
  }));
});

function toggleMesHistorico(key) {
  toggleMesPeriodo(key);
}

function seleccionarTodosMesesHistoricos() {
  if (mesesHistoricosSeleccionados.value.length === periodosHistoricos.value.length) {
    seleccionarPreajusteMeses('actual');
  } else {
    seleccionarPreajusteMeses('todos');
  }
}

const clientesConCambio = computed(() => {
  return clientes.value.filter(c => Boolean(c.tiene_cambio) || Boolean(c.tiene_propuesta));
});

const clientesMatrizInferior = computed(() => {
  if (filtroMatrizInferior.value === 'todos') {
    return clientes.value;
  }
  return clientesConCambio.value;
});

const columnaOrden = ref('cliente_id');
const direccionOrden = ref('asc');

function ordenarPor(col) {
  if (columnaOrden.value === col) {
    direccionOrden.value = direccionOrden.value === 'asc' ? 'desc' : 'asc';
  } else {
    columnaOrden.value = col;
    if (col.startsWith('venta_periodo_') || ['venta_mes_actual', 'venta_mes_anterior', 'venta_mes_anterior_2', 'frecuencia_mes_actual', 'frecuencia_mes_anterior', 'frecuencia_mes_anterior_2'].includes(col)) {
      direccionOrden.value = 'desc';
    } else {
      direccionOrden.value = 'asc';
    }
  }
}

const clientesConCambioFiltrados = computed(() => {
  const lista = clientesMatrizInferior.value;
  let res = lista;
  if (busquedaTablaCambios.value.trim()) {
    const q = busquedaTablaCambios.value.toLowerCase();
    res = res.filter(c =>
      (c.nombre && c.nombre.toLowerCase().includes(q)) ||
      (c.cliente_id && String(c.cliente_id).includes(q)) ||
      (c.ruta && c.ruta.toLowerCase().includes(q)) ||
      (c.ruta_original && c.ruta_original.toLowerCase().includes(q))
    );
  }

  const col = columnaOrden.value;
  const dir = direccionOrden.value === 'asc' ? 1 : -1;

  return [...res].sort((a, b) => {
    let valA, valB;

    if (col.startsWith('venta_periodo_')) {
      const pKey = col.replace('venta_periodo_', '');
      valA = getVentaClientePeriodo(a, pKey);
      valB = getVentaClientePeriodo(b, pKey);
      return (valA - valB) * dir;
    }

    switch (col) {
      case 'cliente_id':
        valA = Number(a.cliente_id) || 0;
        valB = Number(b.cliente_id) || 0;
        return (valA - valB) * dir;

      case 'nombre':
        valA = (a.nombre || '').toLowerCase();
        valB = (b.nombre || '').toLowerCase();
        return valA.localeCompare(valB, 'es') * dir;

      case 'ruta':
        valA = (a.ruta || '').toLowerCase();
        valB = (b.ruta || '').toLowerCase();
        return valA.localeCompare(valB, 'es') * dir;

      case 'dia_norm': {
        const ordenDias = { 'LUNES': 1, 'MARTES': 2, 'MIERCOLES': 3, 'JUEVES': 4, 'VIERNES': 5, 'SABADO': 6, 'DOMINGO': 7 };
        valA = ordenDias[normalizarDiaClave(a.dia_norm)] || 99;
        valB = ordenDias[normalizarDiaClave(b.dia_norm)] || 99;
        return (valA - valB) * dir;
      }

      case 'venta_mes_actual':
        valA = Number(a.venta_mes_actual) || 0;
        valB = Number(b.venta_mes_actual) || 0;
        return (valA - valB) * dir;

      case 'venta_mes_anterior':
        valA = Number(a.venta_mes_anterior) || 0;
        valB = Number(b.venta_mes_anterior) || 0;
        return (valA - valB) * dir;

      case 'venta_mes_anterior_2':
        valA = Number(a.venta_mes_anterior_2) || 0;
        valB = Number(b.venta_mes_anterior_2) || 0;
        return (valA - valB) * dir;

      case 'frecuencia_mes_actual':
        valA = Number(a.frecuencia_mes_actual) || 0;
        valB = Number(b.frecuencia_mes_actual) || 0;
        return (valA - valB) * dir;

      case 'frecuencia_mes_anterior':
        valA = Number(a.frecuencia_mes_anterior) || 0;
        valB = Number(b.frecuencia_mes_anterior) || 0;
        return (valA - valB) * dir;

      case 'frecuencia_mes_anterior_2':
        valA = Number(a.frecuencia_mes_anterior_2) || 0;
        valB = Number(b.frecuencia_mes_anterior_2) || 0;
        return (valA - valB) * dir;

      case 'tipo_ajuste': {
        const getTipo = (c) => {
          const cRuta = c.ruta_original && c.ruta_original.toUpperCase() !== c.ruta.toUpperCase();
          const cDia = c.dia_original && normalizarDiaClave(c.dia_original) !== normalizarDiaClave(c.dia_norm);
          if (cRuta && cDia) return '1_Ruta_Dia';
          if (cRuta) return '2_Ruta';
          if (cDia) return '3_Dia';
          return '4_Propuesta';
        };
        valA = getTipo(a);
        valB = getTipo(b);
        return valA.localeCompare(valB) * dir;
      }

      case 'observaciones':
        valA = (a.observaciones_propuesta || '').toLowerCase();
        valB = (b.observaciones_propuesta || '').toLowerCase();
        return valA.localeCompare(valB, 'es') * dir;

      default:
        return 0;
    }
  });
});

const totalClientesUnicosMatrizInferior = computed(() => {
  return new Set(clientesConCambioFiltrados.value.map(c => c.cliente_id || c.id)).size;
});

const totalesMatrizInferiorPorPeriodo = computed(() => {
  const lista = clientesConCambioFiltrados.value;
  const tot = {};
  periodosHistoricos.value.forEach(p => {
    tot[p.periodo] = 0;
  });
  const cidsVistos = new Set();
  for (let i = 0; i < lista.length; i++) {
    const c = lista[i];
    const cid = c.cliente_id || c.id;
    if (cidsVistos.has(cid)) continue;
    cidsVistos.add(cid);

    periodosHistoricos.value.forEach(p => {
      tot[p.periodo] += getVentaClientePeriodo(c, p.periodo);
    });
  }
  return tot;
});

const totalesMatrizInferior = computed(() => {
  const p0 = periodosHistoricos.value[0]?.periodo;
  const p1 = periodosHistoricos.value[1]?.periodo;
  const p2 = periodosHistoricos.value[2]?.periodo;
  return {
    venta_mes_actual: p0 ? (totalesMatrizInferiorPorPeriodo.value[p0] || 0) : 0,
    venta_mes_anterior: p1 ? (totalesMatrizInferiorPorPeriodo.value[p1] || 0) : 0,
    venta_mes_anterior_2: p2 ? (totalesMatrizInferiorPorPeriodo.value[p2] || 0) : 0,
  };
});

// Clientes con propuestas pendientes deduplicados para descarga en Excel
const clientesPendientesDeduplicados = computed(() => {
  const map = new Map();
  clientes.value.forEach(c => {
    const esPendiente = Boolean(c.tiene_propuesta) ||
                        Boolean(c.tiene_cambio) ||
                        (c.propuesta_estado && c.propuesta_estado.toUpperCase() === 'PENDIENTE');
    if (esPendiente) {
      const cid = c.cliente_id || c.id;
      if (!map.has(cid)) {
        map.set(cid, c);
      }
    }
  });
  return Array.from(map.values());
});

const conteoPropuestasPendientes = computed(() => {
  return clientesPendientesDeduplicados.value.length;
});

function exportarPropuestasExcel(soloSeleccionados = false) {
  let lista = [];

  if (soloSeleccionados && clientesSeleccionadosIds.value.length > 0) {
    const selSet = new Set(clientesSeleccionadosIds.value.map(String));
    const mapa = new Map();
    clientes.value.forEach(c => {
      const cid = String(c.cliente_id || c.id);
      if (selSet.has(cid) && !mapa.has(cid)) {
        mapa.set(cid, c);
      }
    });
    lista = Array.from(mapa.values());
  } else {
    // Si hay búsqueda activa en la matriz y estamos en la vista de cambios, respetar el filtro
    if (busquedaTablaCambios.value.trim() && filtroMatrizInferior.value === 'cambios') {
      const mapa = new Map();
      clientesConCambioFiltrados.value.forEach(c => {
        const cid = c.cliente_id || c.id;
        if (!mapa.has(cid)) {
          mapa.set(cid, c);
        }
      });
      lista = Array.from(mapa.values());
    } else {
      lista = clientesPendientesDeduplicados.value;
    }
  }

  if (lista.length === 0) {
    alert('No hay propuestas pendientes para exportar en la selección actual.');
    return;
  }

  const periodos = periodosVisibles.value;
  const headers = [
    'ID Cliente',
    'Cliente',
    'Ruta Original',
    'Día Visita Original',
    'Ruta Propuesta',
    'Día Visita Propuesto',
    'Tipo Ajuste',
    'Estado Propuesta',
    'Observaciones',
    'Fecha Registro',
    'Estado Cliente',
    'Tipo Negocio',
    'Dirección',
    'Celular',
    'Tiene GPS',
    'Latitud',
    'Longitud',
    ...periodos.map(p => `Venta ${p.nombre} (Bs)`),
  ];

  const rows = [headers];

  lista.forEach(c => {
    const rutaOrig = c.ruta_original || c.ruta || '';
    const diaOrig = c.dia_original || c.dia_norm || '';
    const rutaProp = c.ruta_propuesta || c.ruta || '';
    const diaProp = c.dia_propuesto || c.dia_norm || '';

    let tipoAjuste = c.propuesta_tipo || 'AJUSTE RTM';
    const cambioRuta = normalizarRutaClave(rutaOrig) !== normalizarRutaClave(rutaProp);
    const cambioDia = normalizarDiaClave(diaOrig) !== normalizarDiaClave(diaProp);
    if (cambioRuta && cambioDia) tipoAjuste = 'CAMBIO DE RUTA Y DÍA';
    else if (cambioRuta) tipoAjuste = 'CAMBIO DE RUTA';
    else if (cambioDia) tipoAjuste = 'CAMBIO DE DÍA';

    const row = [
      Number(c.cliente_id || c.id) || '',
      c.nombre || '',
      rutaOrig,
      diaOrig,
      rutaProp,
      diaProp,
      tipoAjuste,
      c.propuesta_estado || (c.tiene_propuesta || c.tiene_cambio ? 'PENDIENTE' : 'SIN CAMBIO'),
      c.observaciones_propuesta || '',
      c.propuesta_fecha || '',
      c.estado || 'Activo',
      c.tipo_negocio || '',
      c.direccion || '',
      c.celular || '',
      c.tiene_gps ? 'SI' : 'NO',
      c.latitud !== null && c.latitud !== undefined && c.latitud !== '' ? Number(c.latitud) : '',
      c.longitud !== null && c.longitud !== undefined && c.longitud !== '' ? Number(c.longitud) : '',
      ...periodos.map(p => {
        const v = getVentaClientePeriodo(c, p.periodo);
        return typeof v === 'number' ? v : (Number(v) || 0);
      }),
    ];

    rows.push(row);
  });

  const fechaHoy = new Date().toISOString().slice(0, 10);
  const nombreArchivo = soloSeleccionados
    ? `clientes_seleccionados_rtm_${fechaHoy}.xlsx`
    : `propuestas_pendientes_rtm_${fechaHoy}.xlsx`;

  descargarExcelXlsx(rows, nombreArchivo, 'Propuestas RTM');
  mostrarToast(`Se descargaron ${lista.length} registro(s) en Excel (.xlsx) exitosamente.`);
}

// Edición directa en la matriz inferior de Ajuste RTM (desplegables directos)
const guardandoFilaId = ref(null);

function obtenerDiaValorSelect(diaVal) {
  if (!diaVal) return 'LUNES';
  const clave = normalizarDiaClave(diaVal);
  const found = diasSemana.find(d => normalizarDiaClave(d.key) === clave || normalizarDiaClave(d.label) === clave);
  return found ? found.label : String(diaVal).toUpperCase();
}

async function cambiarRutaDirecta(c, nuevaRuta) {
  const rutaActual = (c.ruta || '').toUpperCase();
  const rutaDestino = (nuevaRuta || '').toUpperCase();
  if (!rutaDestino || rutaDestino === rutaActual) return;

  guardandoFilaId.value = c.id;

  const rutaOrig = normalizarRutaClave(c.ruta_original || c.ruta);
  const diaOrig = normalizarDiaClave(c.dia_original || c.dia_norm);
  const rutaProp = normalizarRutaClave(rutaDestino);
  const diaProp = normalizarDiaClave(c.dia_norm);
  const vuelveAOriginal = (rutaProp === rutaOrig && diaProp === diaOrig);
  const teniaPropuesta = Boolean(c.tiene_propuesta || c.tiene_cambio);

  const cidTarget = c.cliente_id || c.id;

  // Optimistic update
  clientes.value.forEach(cl => {
    if ((cl.cliente_id && String(cl.cliente_id) === String(cidTarget)) || (cl.id && String(cl.id) === String(c.id))) {
      if (vuelveAOriginal) {
        cl.ruta = cl.ruta_original || rutaDestino;
        cl.ruta_propuesta = null;
        cl.dia_propuesto = null;
        cl.tiene_cambio = false;
        cl.tiene_propuesta = false;
        cl.propuesta_id = null;
        cl.propuesta_estado = null;
      } else {
        cl.ruta = rutaDestino;
        cl.ruta_propuesta = rutaDestino;
        cl.tiene_cambio = true;
        cl.tiene_propuesta = true;
        cl.propuesta_estado = 'PENDIENTE';
      }
    }
  });

  if (vuelveAOriginal) {
    if (teniaPropuesta && kpis.value) {
      if (kpis.value.con_cambios && kpis.value.con_cambios > 0) kpis.value.con_cambios--;
      if (kpis.value.total_propuestas && kpis.value.total_propuestas > 0) kpis.value.total_propuestas--;
    }
  } else {
    if (!teniaPropuesta && kpis.value) {
      if (typeof kpis.value.con_cambios === 'number') kpis.value.con_cambios++;
      if (typeof kpis.value.total_propuestas === 'number') kpis.value.total_propuestas++;
    }
  }

  if (clienteSeleccionado.value && String(clienteSeleccionado.value.cliente_id || clienteSeleccionado.value.id) === String(c.cliente_id || c.id)) {
    clienteSeleccionado.value = { ...c };
  }

  if (!modalAjusteRtm.value.show) {
    renderizarEnMapa(true);
  }

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.ajustar-rtm')
      : '/supervisor/saneamiento/visor/ajustar-rtm';

    const res = await axios.post(url, {
      cliente_id: c.cliente_id || c.id,
      ruta_propuesta: rutaDestino,
      dia_propuesto: c.dia_propuesto || c.dia_norm || 'LUNES',
      observaciones: 'Ajuste directo de ruta desde matriz RTM',
      aplicar_inmediato: true,
    });

    if (res.data?.success) {
      mostrarToast(res.data.message || `Ruta cambiada a ${formatRuta(rutaDestino)} para ${c.nombre}`);
      await cargarDatos(true);
    } else {
      alert(res.data?.message || 'No se pudo actualizar la ruta.');
    }
  } catch (err) {
    console.error('Error al actualizar ruta del cliente:', err);
    alert('Error al actualizar ruta: ' + (err.response?.data?.message || err.message));
  } finally {
    guardandoFilaId.value = null;
  }
}

async function cambiarDiaDirecto(c, nuevoDia) {
  const diaClaveActual = normalizarDiaClave(c.dia_norm);
  const diaClaveDestino = normalizarDiaClave(nuevoDia);
  if (!diaClaveDestino || diaClaveDestino === diaClaveActual) return;

  guardandoFilaId.value = c.id;

  const rutaOrig = normalizarRutaClave(c.ruta_original || c.ruta);
  const diaOrig = normalizarDiaClave(c.dia_original || c.dia_norm);
  const rutaProp = normalizarRutaClave(c.ruta);
  const diaProp = normalizarDiaClave(nuevoDia);
  const vuelveAOriginal = (rutaProp === rutaOrig && diaProp === diaOrig);
  const teniaPropuesta = Boolean(c.tiene_propuesta || c.tiene_cambio);

  const cidTarget = c.cliente_id || c.id;

  // Optimistic update
  clientes.value.forEach(cl => {
    if ((cl.cliente_id && String(cl.cliente_id) === String(cidTarget)) || (cl.id && String(cl.id) === String(c.id))) {
      if (vuelveAOriginal) {
        cl.dia_norm = cl.dia_original || nuevoDia;
        cl.ruta = cl.ruta_original || cl.ruta;
        cl.ruta_propuesta = null;
        cl.dia_propuesto = null;
        cl.tiene_cambio = false;
        cl.tiene_propuesta = false;
        cl.propuesta_id = null;
        cl.propuesta_estado = null;
      } else {
        cl.dia_norm = nuevoDia;
        cl.dia_propuesto = nuevoDia;
        cl.tiene_cambio = true;
        cl.tiene_propuesta = true;
        cl.propuesta_estado = 'PENDIENTE';
      }
    }
  });

  if (vuelveAOriginal) {
    if (teniaPropuesta && kpis.value) {
      if (kpis.value.con_cambios && kpis.value.con_cambios > 0) kpis.value.con_cambios--;
      if (kpis.value.total_propuestas && kpis.value.total_propuestas > 0) kpis.value.total_propuestas--;
    }
  } else {
    if (!teniaPropuesta && kpis.value) {
      if (typeof kpis.value.con_cambios === 'number') kpis.value.con_cambios++;
      if (typeof kpis.value.total_propuestas === 'number') kpis.value.total_propuestas++;
    }
  }

  if (clienteSeleccionado.value && String(clienteSeleccionado.value.cliente_id || clienteSeleccionado.value.id) === String(c.cliente_id || c.id)) {
    clienteSeleccionado.value = { ...c };
  }

  if (!modalAjusteRtm.value.show) {
    renderizarEnMapa(true);
  }

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.ajustar-rtm')
      : '/supervisor/saneamiento/visor/ajustar-rtm';

    const res = await axios.post(url, {
      cliente_id: c.cliente_id || c.id,
      ruta_propuesta: c.ruta_propuesta || c.ruta,
      dia_propuesto: nuevoDia,
      observaciones: 'Ajuste directo de día de visita desde matriz RTM',
      aplicar_inmediato: true,
    });

    if (res.data?.success) {
      mostrarToast(res.data.message || `Día cambiado a ${nuevoDia} para ${c.nombre}`);
      await cargarDatos(true);
    } else {
      alert(res.data?.message || 'No se pudo actualizar el día de visita.');
    }
  } catch (err) {
    console.error('Error al actualizar día del cliente:', err);
    alert('Error al actualizar día: ' + (err.response?.data?.message || err.message));
  } finally {
    guardandoFilaId.value = null;
  }
}

function enfocarClienteEnMapa(c) {
  if (!c || !map) return;
  vistaMovil.value = 'mapa';
  if (c.latitud && c.longitud) {
    map.setView([c.latitud, c.longitud], Math.max(map.getZoom(), 16), { animate: true });
    const marker = markersMap.get(c.id);
    if (marker) {
      if (modoPrincipal.value === 'ajuste_rtm') {
        marker.openPopup();
      } else {
        abrirModalPuntoCliente(c);
      }
    }
  }
}

async function descartarPropuesta(c) {
  if (!c.propuesta_id) return;
  if (!confirm(`¿Deseas descartar el cambio propuesto para ${c.nombre}?`)) return;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.propuestas.actualizar-estado')
      : '/supervisor/saneamiento/propuestas/actualizar-estado';

    const res = await axios.post(url, {
      propuesta_id: c.propuesta_id,
      estado: 'DESCARTADO',
    });

    if (res.data?.status === 'ok') {
      mostrarToast('Propuesta descartada exitosamente.');
      clienteActivoId.value = null;
      if (map) {
        map.closePopup();
      }
      if (modalAjusteRtm.value.show && modalAjusteRtm.value.cliente && (modalAjusteRtm.value.cliente.propuesta_id === c.propuesta_id || modalAjusteRtm.value.cliente.id === c.id)) {
        cerrarModalAjusteRtm();
      }
      cargarDatos(true);
    } else {
      alert('No se pudo descartar la propuesta.');
    }
  } catch (err) {
    console.error('Error al descartar propuesta:', err);
    alert('Error al descartar: ' + (err.response?.data?.message || err.message));
  }
}

// Exponer descarte para invocar desde popups de Leaflet si es necesario
if (typeof window !== 'undefined') {
  window.__descartarPropuestaCliente = (propuestaId, nombre) => {
    descartarPropuesta({ propuesta_id: propuestaId, nombre: nombre || 'este cliente' });
  };
}

const tieneFronteraRutaActual = computed(() => {
  if (!rutaParaAjustarFrontera.value) return false;
  return fronterasRutas.value.some(fr => fr.ruta.toUpperCase() === rutaParaAjustarFrontera.value.toUpperCase());
});

function setModoColor(modo) {
  modoColor.value = modo;
  renderizarEnMapa(true);
}

function toggleMenu(nombre, e) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = e.currentTarget.getBoundingClientRect();
  const menuWidth = (nombre === 'meses_historicos') ? 288 : 260;
  const menuEstHeight = 300;
  let top = rect.bottom + 6;
  if (top + menuEstHeight > window.innerHeight && rect.top > menuEstHeight) {
    top = Math.max(10, rect.top - menuEstHeight - 6);
  }
  const left = Math.max(10, Math.min(rect.left, window.innerWidth - menuWidth - 10));
  posicionMenu.value = {
    top: Math.max(10, top),
    left: left,
  };
  menuAbierto.value = nombre;
}

// Filtros: actualizan las rutas disponibles según el canal seleccionado y sincronizan los filtros
function onCanalesChange() {
  sincronizarFiltrosConRutasDisponibles(true);
  renderizarEnMapa(true);
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  onCanalesChange();
}

function limpiarCanales() {
  filtroCanales.value = [];
  onCanalesChange();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponiblesParaFronteras.value.map(r => r.ruta);
  renderizarEnMapa(true);
}

function limpiarRutas() {
  filtroRutas.value = [];
  renderizarEnMapa(true);
}

function toggleDia(diaKey) {
  const index = filtroDias.value.indexOf(diaKey);
  if (index > -1) {
    filtroDias.value.splice(index, 1);
  } else {
    filtroDias.value.push(diaKey);
  }
  renderizarEnMapa(true);
}

function toggleTodosDias() {
  if (filtroDias.value.length === diasSemana.length) {
    filtroDias.value = [];
  } else {
    filtroDias.value = diasSemana.map(d => d.key);
  }
  renderizarEnMapa(true);
}

// CONSULTA PRINCIPAL A BACKEND (Ejecutada por Botón Consultar)
let cargarDatosRequestId = 0;

async function cargarDatos(mantenerVista = false) {
  menuAbierto.value = null;

  const currentReqId = ++cargarDatosRequestId;
  cargando.value = true;
  try {
    const modoVistaConsulta = (modoPrincipal.value === 'ajuste_rtm') ? 'cambios' : subModoVisualizacion.value;

    // Si filtroCanales está vacío, el usuario seleccionó "Todos los canales"
    const canalesParaConsulta = (filtroCanales.value.length > 0)
      ? filtroCanales.value
      : (props.canales || []);

    const res = await axios.post(route('supervisor.saneamiento.visor.data'), {
      canales: canalesParaConsulta,
      rutas: [],
      dias: [], // Traer todos los días para permitir filtrado reactivo e independiente en Leaflet
      ver_frontera_rutas: true,
      ver_frontera_dias: true,
      solo_activos: filtroEstadoCliente.value === 'activos',
      estado_cliente: filtroEstadoCliente.value,
      modo_vista: modoVistaConsulta,
    });

    if (currentReqId !== cargarDatosRequestId) {
      // Ignorar peticiones desfasadas
      return;
    }

    clientes.value = res.data.clientes || [];
    fronterasRutas.value = res.data.fronteras_rutas || [];
    fronterasDias.value = res.data.fronteras_dias || [];
    kpis.value = res.data.kpis || { total: 0, con_gps: 0, sin_gps: 0, con_cambios: 0, total_propuestas: 0 };
    if (res.data.periodos_historicos && Array.isArray(res.data.periodos_historicos)) {
      periodosHistoricos.value = res.data.periodos_historicos;
      // Verificar si mesesHistoricosSeleccionados contiene períodos válidos devueltos
      const clavesValidas = new Set(res.data.periodos_historicos.map(p => p.periodo));
      const tieneClavesValidas = mesesHistoricosSeleccionados.value.some(k => clavesValidas.has(k));
      if (!tieneClavesValidas) {
        mesesHistoricosSeleccionados.value = res.data.periodos_historicos.slice(0, 3).map(p => p.periodo);
      }
    }

    // Modal abierto permanece inmutable: no sobreescribir el cliente ni el formulario de edición activa

    // Cachear métricas de ventas precargadas para acceso instantáneo
    clientes.value.forEach(c => {
      if (c.cliente_id && (c.venta_mes_actual !== undefined || c.ventas_historicas)) {
        const periodosObj = {};
        if (c.ventas_historicas) {
          Object.entries(c.ventas_historicas).forEach(([pKey, vData]) => {
            periodosObj[pKey] = {
              periodo: pKey,
              monto_acumulado: Number(vData.monto) || 0,
              frecuencia_compra: Number(vData.frecuencia) || 0,
              dias_compra: 0,
            };
          });
        }
        kpisVentasCache.set(c.cliente_id, {
          cliente_id: c.cliente_id,
          periodos: periodosObj,
          mes_actual: {
            periodo: periodosHistoricos.value[0]?.periodo,
            nombre: periodosHistoricos.value[0]?.nombre || 'Mes Actual',
            monto_acumulado: c.venta_mes_actual || 0,
            frecuencia_compra: c.frecuencia_mes_actual || 0,
            dias_compra: 0,
          },
          mes_anterior: {
            periodo: periodosHistoricos.value[1]?.periodo,
            nombre: periodosHistoricos.value[1]?.nombre || 'Mes Anterior',
            monto_acumulado: c.venta_mes_anterior || 0,
            frecuencia_compra: c.frecuencia_mes_anterior || 0,
            dias_compra: 0,
          },
          mes_anterior_2: {
            periodo: periodosHistoricos.value[2]?.periodo,
            nombre: periodosHistoricos.value[2]?.nombre || 'Mes Anterior 2',
            monto_acumulado: c.venta_mes_anterior_2 || 0,
            frecuencia_compra: c.frecuencia_mes_anterior_2 || 0,
            dias_compra: 0,
          },
        });
      }
    });

    registerRutasCatalogo(clientes.value.map(c => c.ruta));

    // Sincronizar filtros de rutas con las rutas resultantes
    sincronizarFiltrosConRutasDisponibles();

    const popupActivoEnMapa = Boolean(map && map._popup && map.hasLayer(map._popup));
    if (modalAjusteRtm.value.show || popupActivoEnMapa) {
      // Si el usuario abrió otro modal o tiene un popup activo en el mapa, no interrumpir ni mutar su interacción activa
      mapaPendienteDeRenderizar = true;
    } else {
      renderizarEnMapa(mantenerVista);
    }
  } catch (error) {
    console.error('Error al consultar datos de ruteo:', error);
  } finally {
    if (currentReqId === cargarDatosRequestId) {
      cargando.value = false;
    }
  }
}

async function ejecutarReasignacionMasiva() {
  if (clientesSeleccionadosIds.value.length === 0 || !formReasignacionMasiva.value.ruta_propuesta) return;
  guardandoReasignacionMasiva.value = true;
  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.ajustar-rtm')
      : '/supervisor/saneamiento/visor/ajustar-rtm';

    const res = await axios.post(url, {
      cliente_ids: clientesSeleccionadosIds.value,
      ruta_propuesta: formReasignacionMasiva.value.ruta_propuesta,
      dia_propuesto: formReasignacionMasiva.value.dia_propuesto || null,
      observaciones: formReasignacionMasiva.value.observaciones || 'Reasignación masiva RTM desde Visor y Fronteras',
    });

    if (res.data?.success) {
      mostrarToast(res.data.message || 'Propuestas RTM registradas exitosamente');
      clientesSeleccionadosIds.value = [];
      formReasignacionMasiva.value.ruta_propuesta = '';
      formReasignacionMasiva.value.dia_propuesto = '';
      formReasignacionMasiva.value.observaciones = '';
      cargarDatos(true);
    } else {
      alert(res.data?.message || 'Error al procesar la reasignación.');
    }
  } catch (err) {
    console.error('Error al reasignar clientes en lote:', err);
    alert('Error al reasignar: ' + (err.response?.data?.message || err.message));
  } finally {
    guardandoReasignacionMasiva.value = false;
  }
}

async function activarClienteDirecto(clienteOrId) {
  const clienteId = typeof clienteOrId === 'object' && clienteOrId !== null
    ? (clienteOrId.cliente_id || clienteOrId.id)
    : clienteOrId;

  if (!clienteId) return;

  const clienteObj = typeof clienteOrId === 'object' && clienteOrId !== null
    ? clienteOrId
    : clientes.value.find(c => String(c.cliente_id || c.id) === String(clienteId));

  const nombreCliente = clienteObj?.nombre || `ID ${clienteId}`;

  if (!confirm(`¿Deseas reactivar al cliente "${nombreCliente}" a estado Activo?`)) {
    return;
  }

  activandoClienteId.value = clienteId;

  // Cerrar modal y popup de mapa inmediatamente para evitar interferencias
  clienteActivoId.value = null;
  if (modalAjusteRtm.value.show && String(modalAjusteRtm.value.cliente?.cliente_id || modalAjusteRtm.value.cliente?.id) === String(clienteId)) {
    cerrarModalAjusteRtm();
  }
  if (map) {
    map.closePopup();
  }

  // Optimistic update local
  const idx = clientes.value.findIndex(c => String(c.cliente_id || c.id) === String(clienteId));
  if (idx !== -1) {
    clientes.value[idx] = {
      ...clientes.value[idx],
      estado: 'Activo',
    };
  }
  if (clienteSeleccionado.value && String(clienteSeleccionado.value.cliente_id || clienteSeleccionado.value.id) === String(clienteId)) {
    clienteSeleccionado.value.estado = 'Activo';
  }

  // Ajustar KPIs optimistas
  if (kpis.value) {
    if (kpis.value.inactivos && kpis.value.inactivos > 0) {
      kpis.value.inactivos--;
    }
    if (typeof kpis.value.activos === 'number') {
      kpis.value.activos++;
    }
  }

  renderizarEnMapa(true);

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.activar-cliente')
      : '/supervisor/saneamiento/visor/activar-cliente';

    const res = await axios.post(url, {
      cliente_id: clienteId,
      observaciones: 'Activación directa desde Visor territorial',
    });

    if (res.data?.success) {
      mostrarToast(res.data.message || `Cliente ${clienteId} reactivado exitosamente.`);
      await cargarDatos(true);
    } else {
      alert(res.data?.message || 'Error al reactivar cliente.');
    }
  } catch (err) {
    console.error('Error al activar cliente:', err);
    alert('Error al reactivar cliente: ' + (err.response?.data?.message || err.message));
  } finally {
    activandoClienteId.value = null;
  }
}

async function ejecutarActivacionMasiva() {
  if (clientesSeleccionadosIds.value.length === 0) return;

  const count = clientesSeleccionadosIds.value.length;
  if (!confirm(`¿Deseas reactivar a estado Activo a los ${count} clientes seleccionados?`)) {
    return;
  }

  activandoMasivo.value = true;
  const idsParaActivar = [...clientesSeleccionadosIds.value];

  // Optimistic update local
  const idsSet = new Set(idsParaActivar.map(String));
  let reactivadosCount = 0;
  clientes.value = clientes.value.map(c => {
    if (idsSet.has(String(c.cliente_id || c.id))) {
      if ((c.estado || '').toLowerCase() !== 'activo') {
        reactivadosCount++;
      }
      return { ...c, estado: 'Activo' };
    }
    return c;
  });

  if (kpis.value) {
    if (kpis.value.inactivos) {
      kpis.value.inactivos = Math.max(0, kpis.value.inactivos - reactivadosCount);
    }
    if (typeof kpis.value.activos === 'number') {
      kpis.value.activos += reactivadosCount;
    }
  }

  renderizarEnMapa(true);

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.activar-cliente')
      : '/supervisor/saneamiento/visor/activar-cliente';

    const res = await axios.post(url, {
      cliente_ids: idsParaActivar,
      observaciones: 'Activación masiva en lote desde Visor territorial',
    });

    if (res.data?.success) {
      mostrarToast(res.data.message || `${idsParaActivar.length} clientes reactivados exitosamente.`);
      clientesSeleccionadosIds.value = [];
      await cargarDatos(true);
    } else {
      alert(res.data?.message || 'Error al procesar la activación masiva.');
    }
  } catch (err) {
    console.error('Error al reactivar clientes en lote:', err);
    alert('Error al activar en lote: ' + (err.response?.data?.message || err.message));
  } finally {
    activandoMasivo.value = false;
  }
}

// ── LÓGICA DE EDICIÓN Y AJUSTE DE FRONTERAS RTM CON DETECTOR DE PROXIMIDAD (SNAPPING) ──

function alCambiarRutaParaFrontera() {
  cancelarEdicionVertices();
  if (!rutaParaAjustarFrontera.value) return;

  // Si la ruta seleccionada ya tiene polígono en pantalla, centrar en ella
  const fr = fronterasRutas.value.find(f => f.ruta.toUpperCase() === rutaParaAjustarFrontera.value.toUpperCase());
  if (fr && fr.geojson && map) {
    try {
      const tempLayer = L.geoJSON(fr.geojson);
      map.fitBounds(tempLayer.getBounds(), { padding: [40, 40] });
    } catch {
      // ignore
    }
  }
}

function extraerPuntosDeGeoJSON(geojson) {
  if (!geojson || !geojson.coordinates) return [];
  const anillos = [];
  if (geojson.type === 'Polygon') {
    (geojson.coordinates || []).forEach(ring => {
      if (Array.isArray(ring)) {
        anillos.push(ring.map(c => [Number(c[1]), Number(c[0])]));
      }
    });
  } else if (geojson.type === 'MultiPolygon') {
    (geojson.coordinates || []).forEach(poly => {
      if (Array.isArray(poly)) {
        poly.forEach(ring => {
          if (Array.isArray(ring)) {
            anillos.push(ring.map(c => [Number(c[1]), Number(c[0])]));
          }
        });
      }
    });
  }
  return anillos;
}

function obtenerGeometriasVecinasParaSnapping() {
  const rutaActual = normalizarRutaClave(rutaParaAjustarFrontera.value);
  const vertices = [];
  const segmentos = [];

  // Solo considerar rutas que están VISIBLES en el mapa (activadas en filtroFronterasRutas)
  const setRutasVisibles = new Set((filtroFronterasRutas.value || []).map(r => normalizarRutaClave(r)));

  (fronterasRutas.value || []).forEach(fr => {
    const rKey = normalizarRutaClave(fr.ruta);
    if (!rKey || rKey === rutaActual) return;
    if (setRutasVisibles.size > 0 && !setRutasVisibles.has(rKey)) return;
    if (!fr.geojson) return;

    const anillos = extraerPuntosDeGeoJSON(fr.geojson);
    anillos.forEach(anillo => {
      if (!anillo || anillo.length < 2) return;

      let pts = anillo;
      if (pts.length > 2) {
        const primer = pts[0];
        const ultimo = pts[pts.length - 1];
        if (Math.abs(primer[0] - ultimo[0]) < 1e-7 && Math.abs(primer[1] - ultimo[1]) < 1e-7) {
          pts = pts.slice(0, pts.length - 1);
        }
      }

      pts.forEach(p => {
        vertices.push({ lat: p[0], lng: p[1], ruta: fr.ruta });
      });

      for (let i = 0; i < pts.length; i++) {
        const p1 = pts[i];
        const p2 = pts[(i + 1) % pts.length];
        segmentos.push({
          p1: { lat: p1[0], lng: p1[1] },
          p2: { lat: p2[0], lng: p2[1] },
          ruta: fr.ruta,
        });
      }
    });
  });

  return { vertices, segmentos };
}

function actualizarCacheGeometriasVecinas() {
  geometriasVecinasCache = obtenerGeometriasVecinasParaSnapping();
}

function proyectarPuntoEnSegmentoPixel(p, a, b) {
  const dx = b.x - a.x;
  const dy = b.y - a.y;
  const lenSq = dx * dx + dy * dy;

  if (lenSq === 0) {
    const dist = Math.hypot(p.x - a.x, p.y - a.y);
    return { x: a.x, y: a.y, distPx: dist, t: 0 };
  }

  const t = Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq));
  const projX = a.x + t * dx;
  const projY = a.y + t * dy;
  const dist = Math.hypot(p.x - projX, p.y - projY);

  return { x: projX, y: projY, distPx: dist, t };
}

const SNAP_VERTEX_TOLERANCE_PX = 9; // Adhesión estricta: solo cuando el cursor está muy cerca del vértice (9px)
const SNAP_EDGE_TOLERANCE_PX = 5;   // Adhesión estricta: solo cuando el cursor está prácticamente sobre la línea (5px)

function detectarSnappingProximidad(latlng) {
  if (!map || !snapProximidadActivado.value) {
    return { snapped: false, latlng: latlng };
  }

  const { vertices, segmentos } = geometriasVecinasCache;
  if (vertices.length === 0 && segmentos.length === 0) {
    return { snapped: false, latlng: latlng };
  }

  const pMousePx = map.latLngToLayerPoint(latlng);
  const mapBounds = map.getBounds().pad(0.08);

  let mejorSnap = null;
  let menorDistanciaPx = Infinity;

  // 1. Prioridad: Vértices de rutas vecinas VISIBLES (Snap exacto a nodo)
  for (let i = 0; i < vertices.length; i++) {
    const v = vertices[i];
    if (!mapBounds.contains([v.lat, v.lng])) continue;

    const vPx = map.latLngToLayerPoint([v.lat, v.lng]);
    const distPx = Math.hypot(pMousePx.x - vPx.x, pMousePx.y - vPx.y);

    if (distPx <= SNAP_VERTEX_TOLERANCE_PX && distPx < menorDistanciaPx) {
      menorDistanciaPx = distPx;
      mejorSnap = {
        snapped: true,
        tipo: 'vertice',
        latlng: L.latLng(v.lat, v.lng),
        ruta: v.ruta,
        distPx: distPx,
        etiqueta: `Vértice de ${formatRuta(v.ruta)}`,
      };
    }
  }

  if (mejorSnap) {
    return mejorSnap;
  }

  // 2. Segunda prioridad: Bordes / Aristas de rutas vecinas VISIBLES (Snap a la línea divisoria)
  for (let i = 0; i < segmentos.length; i++) {
    const seg = segmentos[i];
    const minLat = Math.min(seg.p1.lat, seg.p2.lat);
    const maxLat = Math.max(seg.p1.lat, seg.p2.lat);
    const minLng = Math.min(seg.p1.lng, seg.p2.lng);
    const maxLng = Math.max(seg.p1.lng, seg.p2.lng);

    if (latlng.lat < minLat - 0.015 || latlng.lat > maxLat + 0.015 ||
        latlng.lng < minLng - 0.015 || latlng.lng > maxLng + 0.015) {
      continue;
    }

    const aPx = map.latLngToLayerPoint([seg.p1.lat, seg.p1.lng]);
    const bPx = map.latLngToLayerPoint([seg.p2.lat, seg.p2.lng]);

    const projResult = proyectarPuntoEnSegmentoPixel(pMousePx, aPx, bPx);
    if (projResult.t < 0.03 || projResult.t > 0.97) continue;

    if (projResult.distPx <= SNAP_EDGE_TOLERANCE_PX && projResult.distPx < menorDistanciaPx) {
      menorDistanciaPx = projResult.distPx;
      const snappedLatLng = map.layerPointToLatLng(L.point(projResult.x, projResult.y));
      mejorSnap = {
        snapped: true,
        tipo: 'arista',
        latlng: snappedLatLng,
        ruta: seg.ruta,
        distPx: projResult.distPx,
        etiqueta: `Borde de ${formatRuta(seg.ruta)}`,
      };
    }
  }

  if (mejorSnap) {
    return mejorSnap;
  }

  return { snapped: false, latlng: latlng };
}

function mostrarIndicadorSnap(snapResult) {
  if (!map || !snapResult.snapped) return;

  const nuevaEtiqueta = `Imán: Adherido a ${snapResult.etiqueta}`;
  if (textoSnapActivo.value !== nuevaEtiqueta) {
    textoSnapActivo.value = nuevaEtiqueta;
  }

  const colorSnap = snapResult.tipo === 'vertice' ? '#10B981' : '#06B6D4';

  if (!markerIndicadorSnap) {
    markerIndicadorSnap = L.circleMarker(snapResult.latlng, {
      radius: snapResult.tipo === 'vertice' ? 6 : 5,
      color: '#064E3B',
      fillColor: colorSnap,
      fillOpacity: 0.95,
      weight: 2,
      interactive: false,
    }).addTo(map);
  } else {
    markerIndicadorSnap.setLatLng(snapResult.latlng);
    markerIndicadorSnap.setStyle({
      radius: snapResult.tipo === 'vertice' ? 6 : 5,
      fillColor: colorSnap,
    });
    if (!map.hasLayer(markerIndicadorSnap)) {
      markerIndicadorSnap.addTo(map);
    }
  }
}

function ocultarIndicadorSnap() {
  if (textoSnapActivo.value !== '') {
    textoSnapActivo.value = '';
  }
  if (markerIndicadorSnap && map && map.hasLayer(markerIndicadorSnap)) {
    map.removeLayer(markerIndicadorSnap);
  }
}

function distanciaPuntoASegmento(px, py, x1, y1, x2, y2) {
  const dx = x2 - x1;
  const dy = y2 - y1;
  if (dx === 0 && dy === 0) {
    return Math.hypot(px - x1, py - y1);
  }
  const t = Math.max(0, Math.min(1, ((px - x1) * dx + (py - y1) * dy) / (dx * dx + dy * dy)));
  const projX = x1 + t * dx;
  const projY = y1 + t * dy;
  return Math.hypot(px - projX, py - projY);
}

function limpiarMarkersVertices() {
  ocultarIndicadorSnap();
  if (map) {
    map.closePopup();
  }

  markersVertices.forEach(m => {
    if (map) map.removeLayer(m);
  });
  markersVertices = [];

  markersPuntosIntermedios.forEach(m => {
    if (map) map.removeLayer(m);
  });
  markersPuntosIntermedios = [];

  if (poligonoEnEdicion && map) {
    map.removeLayer(poligonoEnEdicion);
    poligonoEnEdicion = null;
  }
}

function cancelarEdicionVertices() {
  editandoVertices.value = false;
  modoAgregarVertice.value = false;
  ocultarIndicadorSnap();
  limpiarMarkersVertices();
  renderizarEnMapa(true);
}

function sanitizarCoordsPoligono(rawCoords) {
  if (!Array.isArray(rawCoords) || rawCoords.length < 3) return [];

  const limpios = [];
  for (let i = 0; i < rawCoords.length; i++) {
    const pt = rawCoords[i];
    if (!pt || pt.length < 2) continue;
    const lat = Number(pt[0]);
    const lng = Number(pt[1]);
    if (isNaN(lat) || isNaN(lng)) continue;

    // Verificar si es idéntico o prácticamente idéntico al punto anterior (< 2 metros / 0.00002 deg)
    if (limpios.length > 0) {
      const prev = limpios[limpios.length - 1];
      const dLat = Math.abs(lat - prev[0]);
      const dLng = Math.abs(lng - prev[1]);
      if (dLat < 0.00002 && dLng < 0.00002) {
        continue;
      }
    }
    limpios.push([lat, lng]);
  }

  // Eliminar el punto de cierre duplicado al final si coincide con el primer punto (estándar GeoJSON)
  while (limpios.length > 2) {
    const primer = limpios[0];
    const ultimo = limpios[limpios.length - 1];
    const dLat = Math.abs(primer[0] - ultimo[0]);
    const dLng = Math.abs(primer[1] - ultimo[1]);
    if (dLat < 0.00002 && dLng < 0.00002) {
      limpios.pop();
    } else {
      break;
    }
  }

  return limpios;
}

function activarEdicionVertices() {
  if (!rutaParaAjustarFrontera.value) return;
  limpiarMarkersVertices();

  const fr = fronterasRutas.value.find(f => f.ruta.toUpperCase() === rutaParaAjustarFrontera.value.toUpperCase());
  if (fr && fr.geojson) {
    const anillos = extraerPuntosDeGeoJSON(fr.geojson);
    if (anillos.length > 0) {
      // Tomar el anillo con mayor cantidad de puntos (anillo exterior principal)
      let mejorAnillo = anillos[0];
      for (let i = 1; i < anillos.length; i++) {
        if (anillos[i].length > mejorAnillo.length) {
          mejorAnillo = anillos[i];
        }
      }
      coordsPoligonoEnEdicion.value = sanitizarCoordsPoligono(mejorAnillo);
    } else {
      coordsPoligonoEnEdicion.value = [];
    }
  } else {
    generarConvexHullRuta();
    return;
  }

  if (coordsPoligonoEnEdicion.value.length < 3) {
    alert('Se requieren al menos 3 vértices para editar el polígono.');
    return;
  }

  actualizarCacheGeometriasVecinas();
  editandoVertices.value = true;
  crearCapaEdicionPoligono(true);
}

function generarConvexHullRuta() {
  if (!rutaParaAjustarFrontera.value) return;
  const rutaSel = rutaParaAjustarFrontera.value.toUpperCase();
  const clientesRutaGps = clientes.value.filter(c => c.tiene_gps && c.ruta.toUpperCase() === rutaSel);

  if (clientesRutaGps.length < 3) {
    alert(`La ruta ${rutaSel} tiene menos de 3 clientes con coordenadas GPS visibles. No se puede calcular Convex Hull.`);
    return;
  }

  const puntos = clientesRutaGps.map(c => [c.latitud, c.longitud]);
  const hull = calcularConvexHull(puntos);

  if (hull.length < 3) {
    alert('No se pudo generar un polígono cerrado con los puntos actuales.');
    return;
  }

  coordsPoligonoEnEdicion.value = sanitizarCoordsPoligono(hull);
  actualizarCacheGeometriasVecinas();
  editandoVertices.value = true;
  crearCapaEdicionPoligono(true);
}

// Algoritmo Convex Hull (Monotone Chain) en JS
function calcularConvexHull(puntos) {
  if (puntos.length <= 2) return puntos;
  const pts = [...puntos].sort((a, b) => a[0] === b[0] ? a[1] - b[1] : a[0] - b[0]);
  const cross = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);

  const lower = [];
  for (const p of pts) {
    while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], p) <= 0) {
      lower.pop();
    }
    lower.push(p);
  }

  const upper = [];
  for (let i = pts.length - 1; i >= 0; i--) {
    const p = pts[i];
    while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], p) <= 0) {
      upper.pop();
    }
    upper.push(p);
  }

  lower.pop();
  upper.pop();
  return lower.concat(upper);
}

function agregarVerticeEnCoordenada(clickLat, clickLng) {
  coordsPoligonoEnEdicion.value = sanitizarCoordsPoligono(coordsPoligonoEnEdicion.value);
  const coords = coordsPoligonoEnEdicion.value;
  if (coords.length < 3) return;

  let targetLat = clickLat;
  let targetLng = clickLng;

  if (snapProximidadActivado.value) {
    const snap = detectarSnappingProximidad(L.latLng(clickLat, clickLng));
    if (snap.snapped) {
      targetLat = snap.latlng.lat;
      targetLng = snap.latlng.lng;
    }
  }

  // Prevenir agregar si ya existe un vértice muy cercano (< 0.00005 deg ~ 5m)
  for (let i = 0; i < coords.length; i++) {
    const dLat = Math.abs(coords[i][0] - targetLat);
    const dLng = Math.abs(coords[i][1] - targetLng);
    if (dLat < 0.00005 && dLng < 0.00005) {
      mostrarToast('Ya existe un vértice en esa posición.');
      return;
    }
  }

  let mejorIndice = coords.length;
  let distMinima = Infinity;

  for (let i = 0; i < coords.length; i++) {
    const p1 = coords[i];
    const p2 = coords[(i + 1) % coords.length];
    const dist = distanciaPuntoASegmento(targetLat, targetLng, p1[0], p1[1], p2[0], p2[1]);
    if (dist < distMinima) {
      distMinima = dist;
      mejorIndice = i + 1;
    }
  }

  coordsPoligonoEnEdicion.value.splice(mejorIndice, 0, [targetLat, targetLng]);
  coordsPoligonoEnEdicion.value = sanitizarCoordsPoligono(coordsPoligonoEnEdicion.value);
  mostrarToast(`Vértice agregado en la línea (${coordsPoligonoEnEdicion.value.length} vértices en total).`);
  crearCapaEdicionPoligono(false);
}

function eliminarVertice(idx) {
  if (coordsPoligonoEnEdicion.value.length <= 3) {
    alert('Un polígono cerrado requiere un mínimo de 3 vértices. No se pueden quitar más puntos.');
    return;
  }
  if (map) {
    map.closePopup();
  }
  const nuevasCoords = [...coordsPoligonoEnEdicion.value];
  nuevasCoords.splice(idx, 1);
  const limpias = sanitizarCoordsPoligono(nuevasCoords);

  if (limpias.length < 3) {
    alert('No se puede eliminar el vértice porque dejaría el polígono con menos de 3 vértices.');
    return;
  }

  coordsPoligonoEnEdicion.value = limpias;
  mostrarToast(`Vértice #${idx + 1} eliminado (${coordsPoligonoEnEdicion.value.length} vértices restantes).`);
  crearCapaEdicionPoligono(false);
}

function crearCapaEdicionPoligono(centrar = false) {
  if (!map) return;

  // Sanitizar coordenadas para asegurar que no haya duplicados ni puntos de cierre redundantes
  coordsPoligonoEnEdicion.value = sanitizarCoordsPoligono(coordsPoligonoEnEdicion.value);
  const coords = coordsPoligonoEnEdicion.value;
  if (coords.length < 3) return;

  limpiarMarkersVertices();

  poligonoEnEdicion = L.polygon(coords, {
    color: '#0F172A',
    weight: 3.5,
    dashArray: '5, 5',
    fillColor: '#0284C7',
    fillOpacity: 0.08,
  }).addTo(map);

  // Al hacer clic en la línea del polígono: agregar vértice automáticamente
  poligonoEnEdicion.on('click', function (e) {
    L.DomEvent.stopPropagation(e);
    agregarVerticeEnCoordenada(e.latlng.lat, e.latlng.lng);
  });

  if (centrar) {
    map.fitBounds(poligonoEnEdicion.getBounds(), { padding: [40, 40] });
  }

  // 1. Crear PRIMERO los puntos intermedios (+) en cada segmento (quedan debajo en el DOM)
  for (let i = 0; i < coords.length; i++) {
    const pt1 = coords[i];
    const pt2 = coords[(i + 1) % coords.length];
    const dLat = pt1[0] - pt2[0];
    const dLng = pt1[1] - pt2[1];
    const distDeg = Math.sqrt(dLat * dLat + dLng * dLng);

    // Omitir punto intermedio si el segmento es menor a ~8 metros para no estorbar los vértices reales
    if (distDeg < 0.00008) {
      continue;
    }

    const midLat = (pt1[0] + pt2[0]) / 2;
    const midLng = (pt1[1] + pt2[1]) / 2;
    const insertIdx = i + 1;

    const midMarker = L.circleMarker([midLat, midLng], {
      radius: 4.5,
      color: '#0284C7',
      fillColor: '#38BDF8',
      fillOpacity: 0.85,
      weight: 1.5,
    }).addTo(map);

    midMarker.bindTooltip(`
      <div style="font-family: inherit; font-size: 10px; text-align: center;">
        <b style="color: #0284C7;">+ Agregar Vértice</b><br>
        <span style="color: #64748B;">Clic para insertar punto</span>
      </div>
    `, { direction: 'top', offset: [0, -4] });

    midMarker.on('click', function (e) {
      L.DomEvent.stop(e);
      let targetLat = midLat;
      let targetLng = midLng;

      if (snapProximidadActivado.value) {
        const snap = detectarSnappingProximidad(L.latLng(midLat, midLng));
        if (snap.snapped) {
          targetLat = snap.latlng.lat;
          targetLng = snap.latlng.lng;
        }
      }

      coordsPoligonoEnEdicion.value.splice(insertIdx, 0, [targetLat, targetLng]);
      coordsPoligonoEnEdicion.value = sanitizarCoordsPoligono(coordsPoligonoEnEdicion.value);
      mostrarToast(`Vértice agregado (${coordsPoligonoEnEdicion.value.length} vértices en total).`);
      crearCapaEdicionPoligono(false);
    });

    midMarker.on('contextmenu', function (e) {
      L.DomEvent.stop(e);
    });

    markersPuntosIntermedios.push(midMarker);
  }

  // 2. Crear SEGUNDO los vértices interactivos principales (quedan POR ENCIMA en el DOM)
  coords.forEach((pt, idx) => {
    const marker = L.circleMarker(pt, {
      radius: 7,
      color: '#0F172A',
      fillColor: '#FFFFFF',
      fillOpacity: 1,
      weight: 2.5,
    }).addTo(map);

    if (typeof marker.bringToFront === 'function') {
      marker.bringToFront();
    }

    marker.bindTooltip(`
      <div style="font-family: inherit; font-size: 11px; text-align: center;">
        <b>Vértice #${idx + 1}</b><br>
        <span style="font-size: 9.5px; color: #475569;">Arrastrar para mover</span><br>
        <span style="font-size: 9.5px; color: #DC2626; font-weight: bold;">Clic para opciones o clic derecho para eliminar</span>
      </div>
    `, { direction: 'top', offset: [0, -8] });

    // Popup para eliminar con clic/toque directo
    const popupContainer = document.createElement('div');
    popupContainer.style.cssText = 'text-align: center; font-family: inherit; padding: 3px; min-width: 105px;';
    popupContainer.innerHTML = `
      <div style="font-weight: 700; font-size: 11px; margin-bottom: 5px; color: #0F172A;">Vértice #${idx + 1}</div>
      <button type="button" class="btn-eliminar-vtx-popup" style="background-color: #EF4444; color: #FFFFFF; font-size: 10.5px; font-weight: 600; padding: 4px 8px; border-radius: 6px; border: none; cursor: pointer;">
        Eliminar Vértice
      </button>
    `;
    const btnEliminar = popupContainer.querySelector('.btn-eliminar-vtx-popup');
    if (btnEliminar) {
      btnEliminar.addEventListener('click', (ev) => {
        ev.stopPropagation();
        eliminarVertice(idx);
      });
    }

    marker.bindPopup(popupContainer, {
      closeButton: true,
      autoPan: false,
      offset: [0, -6],
    });

    // Eliminar con clic derecho
    marker.on('contextmenu', function (e) {
      L.DomEvent.stop(e);
      eliminarVertice(idx);
    });

    // Eliminar con doble clic
    marker.on('dblclick', function (e) {
      L.DomEvent.stop(e);
      eliminarVertice(idx);
    });

    // Arrastre con mouse en PC con soporte de imán de proximidad (Snapping)
    marker.on('mousedown', function (e) {
      if (e.originalEvent && e.originalEvent.button !== 0) return;
      L.DomEvent.stop(e);
      if (e.originalEvent) {
        e.originalEvent.preventDefault();
      }
      map.dragging.disable();
      marker.closeTooltip();
      map.closePopup();

      let haArrastrado = false;

      // Desactivar temporalmente interacción en puntos intermedios mientras se arrastra
      markersPuntosIntermedios.forEach(m => {
        const el = m.getElement?.();
        if (el) el.style.pointerEvents = 'none';
      });

      function onMouseMove(moveEvent) {
        if (moveEvent.preventDefault) {
          moveEvent.preventDefault();
        }
        haArrastrado = true;
        const rawLatLng = map.mouseEventToLatLng(moveEvent);
        let finalLatLng = rawLatLng;

        if (snapProximidadActivado.value) {
          const snap = detectarSnappingProximidad(rawLatLng);
          if (snap.snapped) {
            finalLatLng = snap.latlng;
            mostrarIndicadorSnap(snap);
          } else {
            ocultarIndicadorSnap();
          }
        } else {
          ocultarIndicadorSnap();
        }

        marker.setLatLng(finalLatLng);
        coordsPoligonoEnEdicion.value[idx] = [finalLatLng.lat, finalLatLng.lng];
        if (poligonoEnEdicion) {
          poligonoEnEdicion.setLatLngs(coordsPoligonoEnEdicion.value);
        }
      }

      function onMouseUp(upEvent) {
        if (upEvent && upEvent.preventDefault) {
          upEvent.preventDefault();
        }
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('mouseup', onMouseUp);
        map.dragging.enable();
        ocultarIndicadorSnap();
        if (haArrastrado) {
          crearCapaEdicionPoligono(false);
        }
      }

      window.addEventListener('mousemove', onMouseMove);
      window.addEventListener('mouseup', onMouseUp);
    });

    // Soporte táctil móvil para arrastre con snapping
    marker.on('touchstart', function (e) {
      L.DomEvent.stop(e);
      if (e.originalEvent) {
        e.originalEvent.preventDefault();
      }
      map.dragging.disable();
      marker.closeTooltip();
      map.closePopup();

      let haArrastradoTouch = false;

      markersPuntosIntermedios.forEach(m => {
        const el = m.getElement?.();
        if (el) el.style.pointerEvents = 'none';
      });

      function onTouchMove(moveEvent) {
        if (!moveEvent.touches || moveEvent.touches.length !== 1) return;
        if (moveEvent.cancelable) {
          moveEvent.preventDefault();
        }
        haArrastradoTouch = true;
        const rawLatLng = map.mouseEventToLatLng(moveEvent.touches[0]);
        let finalLatLng = rawLatLng;

        if (snapProximidadActivado.value) {
          const snap = detectarSnappingProximidad(rawLatLng);
          if (snap.snapped) {
            finalLatLng = snap.latlng;
            mostrarIndicadorSnap(snap);
          } else {
            ocultarIndicadorSnap();
          }
        } else {
          ocultarIndicadorSnap();
        }

        marker.setLatLng(finalLatLng);
        coordsPoligonoEnEdicion.value[idx] = [finalLatLng.lat, finalLatLng.lng];
        if (poligonoEnEdicion) {
          poligonoEnEdicion.setLatLngs(coordsPoligonoEnEdicion.value);
        }
      }

      function onTouchEnd() {
        window.removeEventListener('touchmove', onTouchMove);
        window.removeEventListener('touchend', onTouchEnd);
        map.dragging.enable();
        ocultarIndicadorSnap();
        if (haArrastradoTouch) {
          crearCapaEdicionPoligono(false);
        }
      }

      window.addEventListener('touchmove', onTouchMove, { passive: false });
      window.addEventListener('touchend', onTouchEnd);
    });

    markersVertices.push(marker);
  });
}

async function guardarFronteraRtm() {
  if (!rutaParaAjustarFrontera.value) return;
  const coordsFinales = sanitizarCoordsPoligono(coordsPoligonoEnEdicion.value);
  if (coordsFinales.length < 3) {
    alert('El polígono debe tener al menos 3 vértices válidos.');
    return;
  }
  coordsPoligonoEnEdicion.value = coordsFinales;
  guardandoFrontera.value = true;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.fronteras.guardar')
      : '/supervisor/saneamiento/fronteras/guardar';

    const res = await axios.post(url, {
      ruta: rutaParaAjustarFrontera.value,
      coordenadas: coordsFinales,
    });

    if (res.data?.status === 'ok' || res.data?.frontera_id) {
      mostrarToast(`Frontera RTM de ${formatRuta(rutaParaAjustarFrontera.value)} guardada exitosamente.`);
      cancelarEdicionVertices();
      await cargarDatos(true);
    } else {
      alert('Respuesta inesperada al guardar la frontera.');
    }
  } catch (err) {
    console.error('Error al guardar frontera RTM:', err);
    alert('Error de comunicación al guardar la frontera: ' + (err.response?.data?.message || err.message));
  } finally {
    guardandoFrontera.value = false;
  }
}

// ── LÓGICA DE AJUSTE DINÁMICO DE RTM DE CLIENTES Y MODAL DE PUNTO ──

function getCleanTel(c) {
  if (!c) return '';
  return limpiarTel(c.celular || c.contacto);
}

function getVendedorRuta(ruta) {
  if (!ruta) return 'Sin asignar';
  const r = props.catalogo_rutas.find(item => item.ruta === ruta);
  return r?.vendedor || 'Sin vendedor';
}

function getCanalRuta(ruta) {
  if (!ruta) return '';
  const r = props.catalogo_rutas.find(item => item.ruta === ruta);
  return r?.canal || '';
}

function formatMonto(val) {
  const num = typeof val === 'number' ? val : parseFloat(val || 0);
  return 'Bs ' + num.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
const formatearMoneda = formatMonto;

async function obtenerKpisVentasCliente(clienteId) {
  if (!clienteId) return null;
  if (kpisVentasCache.has(clienteId)) {
    return kpisVentasCache.get(clienteId);
  }

  // Verificar si ya viene precargado en clientes
  const cli = clientes.value.find(c => c.cliente_id == clienteId);
  if (cli && (cli.venta_mes_actual !== undefined || cli.ventas_historicas)) {
    const periodosObj = {};
    if (cli.ventas_historicas) {
      Object.entries(cli.ventas_historicas).forEach(([pKey, vData]) => {
        periodosObj[pKey] = {
          periodo: pKey,
          monto_acumulado: Number(vData.monto) || 0,
          frecuencia_compra: Number(vData.frecuencia) || 0,
          dias_compra: 0,
        };
      });
    }
    const kpiObj = {
      cliente_id: clienteId,
      periodos: periodosObj,
      mes_actual: {
        periodo: periodosHistoricos.value[0]?.periodo,
        nombre: periodosHistoricos.value[0]?.nombre || 'Mes Actual',
        monto_acumulado: cli.venta_mes_actual || 0,
        frecuencia_compra: cli.frecuencia_mes_actual || 0,
        dias_compra: 0,
      },
      mes_anterior: {
        periodo: periodosHistoricos.value[1]?.periodo,
        nombre: periodosHistoricos.value[1]?.nombre || 'Mes Anterior',
        monto_acumulado: cli.venta_mes_anterior || 0,
        frecuencia_compra: cli.frecuencia_mes_anterior || 0,
        dias_compra: 0,
      },
      mes_anterior_2: {
        periodo: periodosHistoricos.value[2]?.periodo,
        nombre: periodosHistoricos.value[2]?.nombre || 'Mes Anterior 2',
        monto_acumulado: cli.venta_mes_anterior_2 || 0,
        frecuencia_compra: cli.frecuencia_mes_anterior_2 || 0,
        dias_compra: 0,
      },
    };
    kpisVentasCache.set(clienteId, kpiObj);
    return kpiObj;
  }

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.cliente.kpis-ventas', { clienteId })
      : `/supervisor/saneamiento/visor/cliente/${clienteId}/kpis-ventas`;

    const res = await axios.get(url);
    if (res.data && res.data.mes_actual) {
      kpisVentasCache.set(clienteId, res.data);
      return res.data;
    }
  } catch (error) {
    console.error('Error al consultar KPIs de fact_ventas del cliente:', error);
  }
  return null;
}

let currentModalSession = 0;

function esSesionValidaParaKpis(sessionId) {
  if (modalAjusteRtm.value.show) {
    return sessionId === currentModalSession;
  }
  return sessionId === null || sessionId === currentModalSession;
}

async function cargarKpisVentasCliente(clienteId, sessionId = null) {
  if (!clienteId) {
    if (esSesionValidaParaKpis(sessionId)) {
      kpisVentasCliente.value = null;
    }
    return;
  }

  if (kpisVentasCache.has(clienteId)) {
    if (esSesionValidaParaKpis(sessionId)) {
      kpisVentasCliente.value = kpisVentasCache.get(clienteId);
    }
    return;
  }

  if (!esSesionValidaParaKpis(sessionId)) return;

  cargandoKpisVentas.value = true;
  kpisVentasCliente.value = null;

  try {
    const data = await obtenerKpisVentasCliente(clienteId);
    if (data && esSesionValidaParaKpis(sessionId)) {
      kpisVentasCliente.value = data;
    }
  } finally {
    if (esSesionValidaParaKpis(sessionId)) {
      cargandoKpisVentas.value = false;
    }
  }
}

async function actualizarKpisEnPopup(c) {
  if (modalAjusteRtm.value.show) return;
  const container = document.getElementById(`rtm-kpis-content-${c.id}`);
  if (!container) return;

  const data = await obtenerKpisVentasCliente(c.cliente_id);
  const el = document.getElementById(`rtm-kpis-content-${c.id}`);
  if (!el) return;

  const numMesesVisibles = Math.min(periodosVisibles.value.length, 3) || 1;
  el.style.gridTemplateColumns = `repeat(${numMesesVisibles}, 1fr)`;

  if (periodosVisibles.value.length === 0) {
    el.innerHTML = `
      <div style="grid-column: span ${numMesesVisibles}; text-align: center; color: #94A3B8; font-size: 9px; padding: 4px;">
        Sin meses seleccionados
      </div>
    `;
    return;
  }

  el.innerHTML = periodosVisibles.value.map(p => {
    const kpiP = getKpisPeriodoCliente(data, p.periodo);
    const monto = kpiP ? formatMonto(kpiP.monto_acumulado) : formatMonto(getVentaClientePeriodo(c, p.periodo));
    const frec = kpiP ? (kpiP.frecuencia_compra ?? 0) : getFrecuenciaClientePeriodo(c, p.periodo);
    const dias = kpiP?.dias_compra || 0;
    const isAct = p.es_actual;
    return `
      <div style="background: #ffffff; padding: 3px 4px; border-radius: 4px; border: 1px solid ${isAct ? '#BAE6FD' : '#E2E8F0'};">
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 8px; color: ${isAct ? '#0284C7' : '#475569'}; font-weight: 700;">
          <span>${escapeHtml(p.corto)}</span>
          <span style="font-size: 7px; color: #94A3B8;">${escapeHtml(p.periodo)}</span>
        </div>
        <div style="font-weight: 700; color: ${isAct ? '#0F172A' : '#334155'}; font-size: 9.5px; line-height: 1.1; margin-top: 1px;">${monto}</div>
        <div style="color: #475569; font-size: 8px; margin-top: 2px; border-top: 1px solid #F1F5F9; padding-top: 1px;">
          Frec: <b style="color: #0F172A;">${frec}</b>
          ${dias > 0 ? `<span style="color: #94A3B8;">(${dias}d)</span>` : ''}
        </div>
      </div>
    `;
  }).join('');
}

function esSesionValidaParaVisitas(sessionId) {
  if (modalAjusteRtm.value.show) {
    return sessionId === currentModalSession;
  }
  return sessionId === null || sessionId === currentModalSession;
}

async function cargarVisitasCliente(clienteId, sessionId = null) {
  if (!clienteId) {
    if (esSesionValidaParaVisitas(sessionId)) {
      visitasCliente.value = [];
    }
    return;
  }
  if (!esSesionValidaParaVisitas(sessionId)) return;

  cargandoVisitas.value = true;
  visitasCliente.value = [];

  try {
    const url = typeof route === 'function'
      ? route('supervisor.visitas.fotos', { clienteId })
      : `/supervisor/visitas/fotos/${clienteId}`;
    const res = await axios.post(url, {}, { timeout: 10000 });
    if (esSesionValidaParaVisitas(sessionId)) {
      visitasCliente.value = res.data?.visitas || [];
    }
  } catch (error) {
    console.warn('No se pudieron cargar visitas del cliente:', error.message);
    if (esSesionValidaParaVisitas(sessionId)) {
      visitasCliente.value = [];
    }
  } finally {
    if (esSesionValidaParaVisitas(sessionId)) {
      cargandoVisitas.value = false;
    }
  }
}

function abrirModalPuntoCliente(c) {
  if (!c) return;
  const sessionId = ++currentModalSession;

  clienteActivoId.value = c.id;
  clienteSeleccionado.value = c;

  modalAjusteRtm.value = {
    show: true,
    cliente: { ...c },
    tabActivo: 'rtm',
  };

  const esInactivo = (c.estado || '').toLowerCase() !== 'activo';
  formRtm.value = {
    cliente_id: c.cliente_id || c.id,
    ruta_propuesta: c.ruta || (props.catalogo_rutas[0]?.ruta || ''),
    dia_propuesto: c.dia_norm || 'LUNES',
    observaciones: 'Ajuste dinámico RTM desde Visor',
    aplicar_inmediato: true,
    activar_cliente: esInactivo,
  };

  guardandoRtm.value = false;

  if (map && c.latitud && c.longitud) {
    map.panTo([c.latitud, c.longitud], { animate: true, duration: 0.5 });
  }

  cargarVisitasCliente(c.cliente_id, sessionId);
  cargarKpisVentasCliente(c.cliente_id, sessionId);

  nextTick(() => {
    const el = document.getElementById('modal-ajuste-rtm-dialog');
    if (el) el.focus();
  });
}

const abrirModalAjusteRtm = abrirModalPuntoCliente;
const abrirExpedienteCliente = abrirModalPuntoCliente;

let mapaPendienteDeRenderizar = false;

function cerrarModalAjusteRtm() {
  modalAjusteRtm.value.show = false;
  modalAjusteRtm.value.cliente = null;
  guardandoRtm.value = false;
  clienteActivoId.value = null;

  const popupActivoEnMapa = Boolean(map && map._popup && map.hasLayer(map._popup));
  if (mapaPendienteDeRenderizar && !popupActivoEnMapa) {
    mapaPendienteDeRenderizar = false;
    renderizarEnMapa(true);
  }
}

function confirmarAjusteRtm() {
  if (!formRtm.value.ruta_propuesta) return;

  // 1. Extraer una copia completamente inmutable del payload antes de cerrar el modal
  const payload = {
    cliente_id: formRtm.value.cliente_id,
    ruta_propuesta: formRtm.value.ruta_propuesta,
    dia_propuesto: formRtm.value.dia_propuesto,
    observaciones: formRtm.value.observaciones || 'Ajuste dinámico RTM desde Visor',
    aplicar_inmediato: true,
    activar_cliente: Boolean(formRtm.value.activar_cliente),
  };
  const clienteIdAfectado = payload.cliente_id;

  // 2. Cerrar el modal y popup INMEDIATAMENTE al presionar Guardar (sin esperar la petición de red)
  cerrarModalAjusteRtm();
  clienteActivoId.value = null;
  if (map) {
    map.closePopup();
  }

  // 3. Aplicar actualización optimista local al cliente en memoria para respuesta instantánea
  const matchIndices = [];
  clientes.value.forEach((cl, i) => {
    if ((cl.cliente_id && String(cl.cliente_id) === String(clienteIdAfectado)) ||
        (cl.id && String(cl.id) === String(clienteIdAfectado))) {
      matchIndices.push(i);
    }
  });

  if (matchIndices.length > 0) {
    const cFirst = clientes.value[matchIndices[0]];
    const rutaOrig = normalizarRutaClave(cFirst.ruta_original || cFirst.ruta);
    const diaOrig = normalizarDiaClave(cFirst.dia_original || cFirst.dia_norm);
    const rutaProp = normalizarRutaClave(payload.ruta_propuesta);
    const diaProp = normalizarDiaClave(payload.dia_propuesto);
    const vuelveAOriginal = (rutaProp === rutaOrig && diaProp === diaOrig);

    const estadoNuevo = payload.activar_cliente ? 'Activo' : cFirst.estado;
    const teniaPropuesta = Boolean(cFirst.tiene_propuesta || cFirst.tiene_cambio);

    matchIndices.forEach(idx => {
      const c = clientes.value[idx];
      if (vuelveAOriginal) {
        clientes.value[idx] = {
          ...c,
          ruta: c.ruta_original || payload.ruta_propuesta,
          dia_norm: c.dia_original || payload.dia_propuesto,
          ruta_propuesta: null,
          dia_propuesto: null,
          estado: estadoNuevo,
          tiene_cambio: false,
          tiene_propuesta: false,
          propuesta_id: null,
          propuesta_estado: null,
          observaciones_propuesta: null,
        };
      } else {
        clientes.value[idx] = {
          ...c,
          ruta: payload.ruta_propuesta,
          dia_norm: payload.dia_propuesto,
          ruta_propuesta: payload.ruta_propuesta,
          dia_propuesto: payload.dia_propuesto,
          estado: estadoNuevo,
          tiene_cambio: true,
          tiene_propuesta: true,
          propuesta_estado: 'PENDIENTE',
        };
      }
    });

    if (vuelveAOriginal) {
      if (teniaPropuesta && kpis.value) {
        if (kpis.value.con_cambios && kpis.value.con_cambios > 0) kpis.value.con_cambios--;
        if (kpis.value.total_propuestas && kpis.value.total_propuestas > 0) kpis.value.total_propuestas--;
      }
    } else {
      if (!teniaPropuesta && kpis.value) {
        if (typeof kpis.value.con_cambios === 'number') kpis.value.con_cambios++;
        if (typeof kpis.value.total_propuestas === 'number') kpis.value.total_propuestas++;
      }
    }

    if (payload.activar_cliente && kpis.value) {
      if (kpis.value.inactivos && kpis.value.inactivos > 0) {
        kpis.value.inactivos--;
      }
      if (typeof kpis.value.activos === 'number') {
        kpis.value.activos++;
      }
    }
    if (clienteSeleccionado.value && String(clienteSeleccionado.value.cliente_id || clienteSeleccionado.value.id) === String(clienteIdAfectado)) {
      clienteSeleccionado.value = { ...clientes.value[matchIndices[0]] };
    }
    if (!modalAjusteRtm.value.show) {
      renderizarEnMapa(true);
    }
  }

  // 4. Ejecutar el guardado en segundo plano de manera 100% aislada (sin mutar ningún modal nuevo que se abra)
  (async () => {
    try {
      const url = typeof route === 'function'
        ? route('supervisor.saneamiento.visor.ajustar-rtm')
        : '/supervisor/saneamiento/visor/ajustar-rtm';

      const res = await axios.post(url, payload);

      if (res.data?.success) {
        mostrarToast(res.data.message || 'Propuesta RTM registrada exitosamente');
        // Actualizar datos en segundo plano sin interrumpir ni cerrar ningún modal que esté abierto
        await cargarDatos(true);
      } else {
        alert(res.data?.message || 'No se pudo aplicar el ajuste RTM.');
      }
    } catch (err) {
      console.error('Error al ajustar RTM del cliente:', err);
      alert('Error al ajustar RTM: ' + (err.response?.data?.message || err.message));
    }
  })();
}

function mostrarToast(msg) {
  toastMensaje.value = msg;
  setTimeout(() => {
    toastMensaje.value = '';
  }, 4500);
}

function cerrarExpediente() {
  clienteSeleccionado.value = null;
  clienteActivoId.value = null;
  visitasCliente.value = [];
  kpisVentasCliente.value = null;
}

function limpiarTel(t) {
  if (!t) return '';
  return String(t).replace(/\D/g, '');
}

// ── MAPA Y RENDERIZADO LEAFLET ──

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function getRutasParaSelectorPropuesta() {
  let lista = props.catalogo_rutas || [];

  // Filtrar por canales seleccionados si los hay, o permitir todas las rutas del catálogo
  if (filtroCanales.value.length > 0) {
    const setCanales = new Set(filtroCanales.value.map(c => String(c).trim().toUpperCase()));
    lista = lista.filter(r => setCanales.has(String(r.canal || '').trim().toUpperCase()));
  }

  return lista;
}

function generarPopupHtmlRtm(c) {
  const rutasCandidatas = getRutasParaSelectorPropuesta();
  const rutaActualNorm = normalizarRutaClave(c.ruta);
  const rutaActualEnFiltro = rutasCandidatas.some(r => normalizarRutaClave(r.ruta) === rutaActualNorm);

  let rutasOptions = '';
  if (!rutaActualEnFiltro) {
    rutasOptions += '<option value="" disabled selected>-- Selecciona nueva ruta filtrada --</option>';
  }

  rutasOptions += rutasCandidatas.map(r => {
    const esActual = normalizarRutaClave(r.ruta) === rutaActualNorm;
    const sel = esActual ? 'selected' : '';
    return `<option value="${escapeHtml(r.ruta)}" ${sel}>${escapeHtml(formatRuta(r.ruta))} (${escapeHtml(r.canal || '')})</option>`;
  }).join('');

  if (rutasCandidatas.length === 0) {
    rutasOptions = '<option value="" disabled selected>No hay rutas filtradas disponibles</option>';
  }

  const diasOptions = diasSemana.map(d => {
    const sel = (c.dia_norm && d.label.toUpperCase() === c.dia_norm.toUpperCase()) ? 'selected' : '';
    return `<option value="${d.label}" ${sel}>${d.label}</option>`;
  }).join('');

  const rutaOrig = c.ruta_original || c.ruta;
  const diaOrig = c.dia_original || c.dia_norm;
  const tieneCambio = Boolean(c.tiene_cambio);
  const esInactivo = (c.estado || '').toLowerCase() !== 'activo';

  const cachedKpis = kpisVentasCache.get(c.cliente_id);
  const numMesesVisibles = Math.min(periodosVisibles.value.length, 3) || 1;

  let kpisHtml = '';
  if (periodosVisibles.value.length === 0) {
    kpisHtml = `
      <div style="grid-column: span ${numMesesVisibles}; text-align: center; color: #94A3B8; font-size: 9px; padding: 4px;">
        Sin meses seleccionados
      </div>
    `;
  } else if (cachedKpis) {
    kpisHtml = periodosVisibles.value.map(p => {
      const kpiP = getKpisPeriodoCliente(cachedKpis, p.periodo);
      const monto = kpiP ? formatMonto(kpiP.monto_acumulado) : formatMonto(getVentaClientePeriodo(c, p.periodo));
      const frec = kpiP ? (kpiP.frecuencia_compra ?? 0) : getFrecuenciaClientePeriodo(c, p.periodo);
      const dias = kpiP?.dias_compra || 0;
      const isAct = p.es_actual;
      return `
        <div style="background: #ffffff; padding: 3px 4px; border-radius: 4px; border: 1px solid ${isAct ? '#BAE6FD' : '#E2E8F0'};">
          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 8px; color: ${isAct ? '#0284C7' : '#475569'}; font-weight: 700;">
            <span>${escapeHtml(p.corto)}</span>
            <span style="font-size: 7px; color: #94A3B8;">${escapeHtml(p.periodo)}</span>
          </div>
          <div style="font-weight: 700; color: ${isAct ? '#0F172A' : '#334155'}; font-size: 9.5px; line-height: 1.1; margin-top: 1px;">${monto}</div>
          <div style="color: #475569; font-size: 8px; margin-top: 2px; border-top: 1px solid #F1F5F9; padding-top: 1px;">
            Frec: <b style="color: #0F172A;">${frec}</b>
            ${dias > 0 ? `<span style="color: #94A3B8;">(${dias}d)</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  } else {
    kpisHtml = periodosVisibles.value.map(p => {
      const isAct = p.es_actual;
      return `
        <div style="background: #ffffff; padding: 3px 4px; border-radius: 4px; border: 1px solid #E2E8F0;">
          <div style="color: ${isAct ? '#0284C7' : '#64748B'}; font-size: 8px; font-weight: 700;">${escapeHtml(p.corto)}</div>
          <div style="font-weight: 700; color: #0F172A; font-size: 9px; margin-top: 1px;">${formatMonto(getVentaClientePeriodo(c, p.periodo))}</div>
          <div style="color: #64748B; font-size: 8px; margin-top: 2px;">Frec: ${getFrecuenciaClientePeriodo(c, p.periodo)}</div>
        </div>
      `;
    }).join('');
  }

  return `
    <div style="min-width: 275px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; color: #0f172a; padding: 2px;">
      <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 6px;">
        <div style="font-weight: 700; font-size: 12px; color: #0f172a; line-height: 1.25; display: flex; align-items: center; justify-content: space-between; gap: 4px;">
          <span>${escapeHtml(c.nombre)}</span>
          <span style="font-size: 8.5px; font-weight: 700; padding: 1px 4px; border-radius: 3px; text-transform: uppercase; background: ${!esInactivo ? '#ECFDF5; color: #047857;' : '#FFE4E6; color: #BE123C;'}">${escapeHtml(c.estado || 'Activo')}</span>
        </div>
        <div style="color: #64748b; font-size: 10px; margin-top: 2px;">${escapeHtml(c.direccion || 'Sin dirección')}</div>
        <div style="font-family: monospace; font-size: 10px; color: #475569; margin-top: 3px;">
          ID: ${c.cliente_id} • Actual: <b>${escapeHtml(formatRuta(rutaOrig))}</b> (${escapeHtml(diaOrig || 'S/D')})
        </div>
        ${esInactivo ? `
          <button
            type="button"
            onclick="window.__activarCliente('${c.cliente_id || c.id}')"
            style="margin-top: 5px; width: 100%; height: 24px; background-color: #047857; color: #ffffff; font-weight: 700; font-size: 10.5px; border: none; border-radius: 4px; cursor: pointer; transition: background 0.15s ease; display: flex; align-items: center; justify-content: center; gap: 4px;"
            onmouseover="this.style.backgroundColor='#065f46'"
            onmouseout="this.style.backgroundColor='#047857'"
            title="Reactivar cliente a estado Activo inmediatamente"
          >
            <svg style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            <span>Activar Cliente</span>
          </button>
        ` : ''}
        ${tieneCambio ? `
          <div style="margin-top: 4px; padding: 4px 6px; background-color: #F8FAFC; border: 1.5px dashed #334155; border-radius: 4px; font-size: 9.5px; font-family: monospace;">
            <div style="font-weight: 700; color: #0F172A; display: flex; justify-content: space-between;">
              <span>PROPUESTA REGISTRADA:</span>
              <span style="background: #E2E8F0; padding: 0 4px; border-radius: 2px;">${c.propuesta_estado || 'PENDIENTE'}</span>
            </div>
            <div style="margin-top: 2px;">
              Ruta: <span style="text-decoration: line-through; color: #94A3B8;">${escapeHtml(formatRuta(rutaOrig))}</span> -> <b style="color: ${getRutaColor(c.ruta)};">${escapeHtml(formatRuta(c.ruta))}</b>
            </div>
            <div>
              Día: <span style="text-decoration: line-through; color: #94A3B8;">${escapeHtml(diaOrig || 'S/D')}</span> -> <b style="color: ${getDiaColor(c.dia_norm)};">${escapeHtml(c.dia_norm || 'S/D')}</b>
            </div>
          </div>
        ` : ''}
      </div>

      <!-- MÉTRICAS DE COMPRA FACT_VENTAS (MESES HISTÓRICOS SELECCIONADOS) -->
      <div id="rtm-kpis-${c.id}" style="margin: 4px 0 6px 0; padding: 5px 6px; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; font-family: monospace; font-size: 10px;">
        <div style="color: #64748B; font-size: 8.5px; font-weight: 700; text-transform: uppercase; margin-bottom: 3px; display: flex; align-items: center; justify-content: space-between;">
          <span style="color: #0F172A;">COMPORTAMIENTO COMPRA (fact_ventas)</span>
          <span style="font-size: 8px; color: #64748B;">${numMesesVisibles} Mes${numMesesVisibles > 1 ? 'es' : ''}</span>
        </div>
        <div id="rtm-kpis-content-${c.id}" style="display: grid; grid-template-columns: repeat(${numMesesVisibles}, 1fr); gap: 3.5px;">
          ${kpisHtml}
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 5px;">
        <div>
          <label style="display: block; font-size: 10px; font-weight: 600; color: #334155; margin-bottom: 2px;">Nueva Ruta (Filtrada):</label>
          <select id="rtm-popup-ruta-${c.id}" onkeydown="if(event.key==='Enter'){event.preventDefault();window.__guardarPropuestaRtmDesdePopup('${c.cliente_id || c.id}','${c.id}');}" style="width: 100%; height: 26px; padding: 0 6px; font-size: 11px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; outline: none; cursor: pointer;">
            ${rutasOptions}
          </select>
        </div>

        <div>
          <label style="display: block; font-size: 10px; font-weight: 600; color: #334155; margin-bottom: 2px;">Nuevo Día de Visita:</label>
          <select id="rtm-popup-dia-${c.id}" onkeydown="if(event.key==='Enter'){event.preventDefault();window.__guardarPropuestaRtmDesdePopup('${c.cliente_id || c.id}','${c.id}');}" style="width: 100%; height: 26px; padding: 0 6px; font-size: 11px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; outline: none; cursor: pointer;">
            ${diasOptions}
          </select>
        </div>

        <div>
          <label style="display: block; font-size: 10px; font-weight: 600; color: #334155; margin-bottom: 2px;">Observación:</label>
          <input id="rtm-popup-obs-${c.id}" type="text" placeholder="Rebalanceo RTM..." onkeydown="if(event.key==='Enter'){event.preventDefault();window.__guardarPropuestaRtmDesdePopup('${c.cliente_id || c.id}','${c.id}');}" style="width: 100%; height: 24px; padding: 0 6px; font-size: 10.5px; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; box-sizing: border-box; outline: none;" />
        </div>

        ${esInactivo ? `
          <label style="display: flex; align-items: center; gap: 6px; font-size: 10.5px; color: #047857; font-weight: 600; cursor: pointer; margin-top: 2px;">
            <input id="rtm-popup-activar-${c.id}" type="checkbox" checked style="accent-color: #047857; cursor: pointer;" />
            <span>Activar cliente al guardar</span>
          </label>
        ` : ''}

        <button
          id="rtm-btn-save-${c.id}"
          type="button"
          onclick="window.__guardarPropuestaRtmDesdePopup('${c.cliente_id || c.id}', '${c.id}')"
          style="margin-top: 4px; width: 100%; height: 26px; background-color: #0f172a; color: #ffffff; font-weight: 600; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; transition: background 0.15s ease;"
          onmouseover="this.style.backgroundColor='#1e293b'"
          onmouseout="this.style.backgroundColor='#0f172a'"
        >
          Guardar Propuesta (Enter)
        </button>

        ${c.propuesta_id ? `
          <button
            type="button"
            onclick="window.__descartarPropuestaCliente(${c.propuesta_id}, '${escapeHtml(c.nombre).replace(/'/g, "\\'")}')"
            style="margin-top: 2px; width: 100%; height: 24px; background-color: #ffffff; color: #dc2626; font-weight: 600; font-size: 10.5px; border: 1px solid #fecaca; border-radius: 4px; cursor: pointer;"
            onmouseover="this.style.backgroundColor='#fef2f2'"
            onmouseout="this.style.backgroundColor='#ffffff'"
          >
            Descartar propuesta actual
          </button>
        ` : ''}
      </div>
    </div>
  `;
}

function guardarPropuestaRtmDesdePopup(clienteId, domId) {
  const selectRuta = document.getElementById(`rtm-popup-ruta-${domId}`);
  const selectDia = document.getElementById(`rtm-popup-dia-${domId}`);
  const inputObs = document.getElementById(`rtm-popup-obs-${domId}`);
  const checkActivar = document.getElementById(`rtm-popup-activar-${domId}`);
  const activarCliente = checkActivar ? checkActivar.checked : false;

  if (!selectRuta || !selectRuta.value) {
    alert('Por favor selecciona una ruta.');
    return;
  }

  const payload = {
    cliente_id: clienteId,
    ruta_propuesta: selectRuta.value,
    dia_propuesto: selectDia ? selectDia.value : 'LUNES',
    observaciones: inputObs?.value?.trim() || 'Ajuste RTM territorial desde mapa Leaflet',
    aplicar_inmediato: true,
    activar_cliente: activarCliente,
  };

  // 1. Cerrar popup inmediatamente y desvincular cliente activo para que no se reabra
  clienteActivoId.value = null;
  if (map) {
    map.closePopup();
  }

  // 2. Actualización optimista local en memoria
  const matchIndices = [];
  clientes.value.forEach((cl, i) => {
    if ((cl.cliente_id && String(cl.cliente_id) === String(clienteId)) ||
        (cl.id && String(cl.id) === String(domId))) {
      matchIndices.push(i);
    }
  });

  if (matchIndices.length > 0) {
    const cFirst = clientes.value[matchIndices[0]];
    const rutaOrig = normalizarRutaClave(cFirst.ruta_original || cFirst.ruta);
    const diaOrig = normalizarDiaClave(cFirst.dia_original || cFirst.dia_norm);
    const rutaProp = normalizarRutaClave(payload.ruta_propuesta);
    const diaProp = normalizarDiaClave(payload.dia_propuesto);
    const vuelveAOriginal = (rutaProp === rutaOrig && diaProp === diaOrig);

    const estadoNuevo = payload.activar_cliente ? 'Activo' : cFirst.estado;
    const teniaPropuesta = Boolean(cFirst.tiene_propuesta || cFirst.tiene_cambio);

    matchIndices.forEach(idx => {
      const c = clientes.value[idx];
      if (vuelveAOriginal) {
        clientes.value[idx] = {
          ...c,
          ruta: c.ruta_original || payload.ruta_propuesta,
          dia_norm: c.dia_original || payload.dia_propuesto,
          ruta_propuesta: null,
          dia_propuesto: null,
          estado: estadoNuevo,
          tiene_cambio: false,
          tiene_propuesta: false,
          propuesta_id: null,
          propuesta_estado: null,
          observaciones_propuesta: null,
        };
      } else {
        clientes.value[idx] = {
          ...c,
          ruta: payload.ruta_propuesta,
          dia_norm: payload.dia_propuesto,
          ruta_propuesta: payload.ruta_propuesta,
          dia_propuesto: payload.dia_propuesto,
          estado: estadoNuevo,
          tiene_cambio: true,
          tiene_propuesta: true,
          propuesta_estado: 'PENDIENTE',
        };
      }
    });

    if (vuelveAOriginal) {
      if (teniaPropuesta && kpis.value) {
        if (kpis.value.con_cambios && kpis.value.con_cambios > 0) kpis.value.con_cambios--;
        if (kpis.value.total_propuestas && kpis.value.total_propuestas > 0) kpis.value.total_propuestas--;
      }
    } else {
      if (!teniaPropuesta && kpis.value) {
        if (typeof kpis.value.con_cambios === 'number') kpis.value.con_cambios++;
        if (typeof kpis.value.total_propuestas === 'number') kpis.value.total_propuestas++;
      }
    }

    if (payload.activar_cliente && kpis.value) {
      if (kpis.value.inactivos && kpis.value.inactivos > 0) {
        kpis.value.inactivos--;
      }
      if (typeof kpis.value.activos === 'number') {
        kpis.value.activos++;
      }
    }
    if (clienteSeleccionado.value && String(clienteSeleccionado.value.cliente_id || clienteSeleccionado.value.id) === String(clienteId)) {
      clienteSeleccionado.value = { ...clientes.value[matchIndices[0]] };
    }
    if (!modalAjusteRtm.value.show) {
      renderizarEnMapa(true);
    }
  }

  // 3. Guardado en segundo plano aislado
  (async () => {
    try {
      const url = typeof route === 'function'
        ? route('supervisor.saneamiento.visor.ajustar-rtm')
        : '/supervisor/saneamiento/visor/ajustar-rtm';

      const res = await axios.post(url, payload);

      if (res.data?.success) {
        mostrarToast(res.data.message || 'Propuesta RTM registrada exitosamente.');
        await cargarDatos(true);
      } else {
        alert(res.data?.message || 'No se pudo guardar la propuesta.');
      }
    } catch (err) {
      console.error('Error al guardar propuesta RTM:', err);
      alert('Error al guardar propuesta RTM: ' + (err.response?.data?.message || err.message));
    }
  })();
}

function cambiarCapaMapa(nombreCapa) {
  if (!map || !mapsConfig.tileLayers[nombreCapa]) return;
  capaActual.value = nombreCapa;

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer);
  }

  const layerConf = mapsConfig.tileLayers[nombreCapa];
  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map);
}

function initMap() {
  const container = document.getElementById('map-visor');
  if (!container) return;

  if (map) {
    map.remove();
    map = null;
  }

  if (container._leaflet_id) {
    container._leaflet_id = null;
  }

  const defaultLoc = mapsConfig.defaultLocation;

  map = L.map('map-visor', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);
  cambiarCapaMapa(capaActual.value);

  fronterasRutasLayer = L.featureGroup().addTo(map);
  fronterasDiasLayer = L.featureGroup().addTo(map);
  markersLayer = L.featureGroup().addTo(map);

  map.on('mousemove', (e) => {
    if (editandoVertices.value && modoAgregarVertice.value && snapProximidadActivado.value && e.latlng) {
      const snap = detectarSnappingProximidad(e.latlng);
      if (snap.snapped) {
        mostrarIndicadorSnap(snap);
      } else {
        ocultarIndicadorSnap();
      }
    }
  });

  map.on('click', (e) => {
    menuAbierto.value = null;
    if (editandoVertices.value && modoAgregarVertice.value && e.latlng) {
      let targetLatLng = e.latlng;
      if (snapProximidadActivado.value) {
        const snap = detectarSnappingProximidad(targetLatLng);
        if (snap.snapped) {
          targetLatLng = snap.latlng;
        }
      }
      agregarVerticeEnCoordenada(targetLatLng.lat, targetLatLng.lng);
      modoAgregarVertice.value = false;
      ocultarIndicadorSnap();
    }
  });

  map.on('popupclose', () => {
    const popupAunAbierto = Boolean(map && map._popup && map.hasLayer(map._popup));
    if (!popupAunAbierto && !modalAjusteRtm.value.show) {
      clienteActivoId.value = null;
    }
    if (!popupAunAbierto && mapaPendienteDeRenderizar && !modalAjusteRtm.value.show) {
      mapaPendienteDeRenderizar = false;
      renderizarEnMapa(true);
    }
  });

  setTimeout(() => {
    if (map) map.invalidateSize();
  }, 250);
}

function getMarkerColor(c) {
  if (modoColor.value === 'ruta') {
    return getRutaColor(c.ruta);
  }
  if (modoColor.value === 'dia') {
    return getDiaColor(c.dia_norm);
  }
  if (modoColor.value === 'canal') {
    const rutaInfo = props.catalogo_rutas.find(r => r.ruta === c.ruta);
    return getCanalColor(rutaInfo ? rutaInfo.canal : 'General');
  }
  return getRutaColor(c.ruta);
}

function renderizarEnMapa(mantenerVista = false) {
  if (!map) return;

  const centroActual = mantenerVista ? map.getCenter() : null;
  const zoomActual = mantenerVista ? map.getZoom() : null;
  const popupActivoEnMapa = Boolean(map && map._popup && map.hasLayer(map._popup));
  const popupAbiertoId = (mantenerVista && popupActivoEnMapa && clienteActivoId.value && !modalAjusteRtm.value.show) ? clienteActivoId.value : null;

  markersLayer.clearLayers();
  fronterasRutasLayer.clearLayers();
  fronterasDiasLayer.clearLayers();
  markersMap.clear();

  const boundsList = [];

  // Polígonos de Rutas (Línea gruesa, bien enmarcada y sin color de fondo) filtrados por ruta
  if (fronterasRutas.value.length > 0 && filtroFronterasRutas.value.length > 0) {
    const setRutasPermitidas = new Set(filtroFronterasRutas.value.map(r => normalizarRutaClave(r)));

    fronterasRutas.value.forEach(fr => {
      const rKey = normalizarRutaClave(fr.ruta);
      if (!setRutasPermitidas.has(rKey)) return;

      const rutaColor = getRutaColor(fr.ruta);
      const layer = L.geoJSON(fr.geojson, {
        style: {
          color: rutaColor,
          weight: 4,
          opacity: 0.95,
          fill: false,
          fillOpacity: 0,
        }
      }).addTo(fronterasRutasLayer);
      layer.bindTooltip(`Ruta: <b>${formatRuta(fr.ruta)}</b> (${fr.canal || 'Canal'})`, { sticky: true });
      boundsList.push(layer.getBounds());
    });
  }

  // Polígonos de Días (Sutiles y punteados) filtrados por ruta
  if (fronterasDias.value.length > 0 && filtroFronterasDias.value.length > 0) {
    const setDiasPermitidos = new Set(filtroFronterasDias.value.map(r => normalizarRutaClave(r)));

    fronterasDias.value.forEach(fd => {
      const rKey = normalizarRutaClave(fd.ruta);
      if (!setDiasPermitidos.has(rKey)) return;

      const diaColor = getDiaColor(fd.dia);
      const layer = L.geoJSON(fd.geojson, {
        style: {
          color: diaColor,
          weight: 1.5,
          dashArray: '3, 4',
          opacity: 0.8,
          fillColor: diaColor,
          fillOpacity: 0.04,
        }
      }).addTo(fronterasDiasLayer);
      layer.bindTooltip(`Ruta: <b>${formatRuta(fd.ruta)}</b> · Día: <b>${fd.dia}</b>`, { sticky: true });
      boundsList.push(layer.getBounds());
    });
  }

  // Marcadores de Clientes (Filtrados dinámicamente por el selector de rutas/puntos y días de visita)
  const setRutasPuntosPermitidas = new Set(filtroRutas.value.map(r => normalizarRutaClave(r)));
  const setDiasVisitaPermitidos = (filtroDias.value.length > 0 && filtroDias.value.length < diasSemana.length)
    ? new Set(filtroDias.value.map(d => normalizarDiaClave(d)))
    : null;

  clientes.value.forEach(c => {
    if (c.tiene_gps) {
      const rutaCliente = normalizarRutaClave(c.ruta);
      if (!setRutasPuntosPermitidas.has(rutaCliente)) {
        return;
      }
      if (setDiasVisitaPermitidos && !setDiasVisitaPermitidos.has(normalizarDiaClave(c.dia_norm))) {
        return;
      }

      const esModoAjusteRtm = (modoPrincipal.value === 'ajuste_rtm');
      const esModoOriginal = (modoPrincipal.value === 'visualizacion' && subModoVisualizacion.value === 'original');
      const tieneCambio = !esModoOriginal && Boolean(c.tiene_cambio);

      const rutaOrig = c.ruta_original || c.ruta;
      const diaOrig = c.dia_original || c.dia_norm;
      const rutaEfectiva = c.ruta;
      const diaEfectivo = c.dia_norm;

      const cambioRuta = Boolean(tieneCambio && c.ruta_propuesta && c.ruta_propuesta.toUpperCase() !== rutaOrig.toUpperCase());
      const cambioDia = Boolean(tieneCambio && c.dia_propuesto && normalizarDiaClave(c.dia_propuesto) !== normalizarDiaClave(diaOrig));

      let etiquetaCambio = 'PROPUESTA REGISTRADA';
      if (cambioRuta && cambioDia) etiquetaCambio = 'CAMBIO DE RUTA Y DÍA';
      else if (cambioRuta) etiquetaCambio = 'CAMBIO DE RUTA';
      else if (cambioDia) etiquetaCambio = 'CAMBIO DE DÍA';

      const esInactivo = (c.estado || '').toLowerCase() !== 'activo' && Boolean(c.estado);
      let markerColor, markerBorder, markerWeight, markerDashArray, markerRadius;

      if (esModoAjusteRtm) {
        // EN MODO AJUSTAR RTM:
        if (tieneUnaSolaRutaMarcada.value) {
          // Si solo tenemos una ruta marcada: se ven solo los colores del DÍA de visita
          markerColor = getDiaColor(diaEfectivo);
          markerBorder = tieneCambio ? '#0F172A' : (esInactivo ? '#E11D48' : '#FFFFFF');
          markerWeight = (tieneCambio || esInactivo) ? 3 : 1.5;
          markerDashArray = tieneCambio ? '3, 3' : (esInactivo ? '2, 2' : null);
          markerRadius = (tieneCambio || esInactivo) ? 7 : 6;
        } else {
          // Si hay múltiples rutas marcadas:
          // Relleno: Color definido para la RUTA (efectiva)
          // Borde: Color definido para el DÍA de visita (efectivo) o rojo si es inactivo
          markerColor = getRutaColor(rutaEfectiva);
          markerBorder = esInactivo ? '#E11D48' : getDiaColor(diaEfectivo);
          markerWeight = tieneCambio ? 3.5 : (esInactivo ? 3 : 2);
          markerDashArray = tieneCambio ? '3, 3' : (esInactivo ? '2, 2' : null);
          markerRadius = tieneCambio ? 7 : 6;
        }
      } else {
        // EN MODO VISUALIZACIÓN:
        markerColor = getMarkerColor(c);
        if (tieneCambio) {
          markerBorder = '#0F172A';
          markerWeight = 2.5;
          markerDashArray = '3, 3';
          markerRadius = 6.5;
        } else if (esInactivo) {
          markerBorder = '#E11D48';
          markerWeight = 2.5;
          markerDashArray = '2, 2';
          markerRadius = 6.5;
        } else {
          markerBorder = '#FFFFFF';
          markerWeight = 1.5;
          markerDashArray = null;
          markerRadius = 6;
        }
      }

      const marker = L.circleMarker([c.latitud, c.longitud], {
        radius: markerRadius,
        fillColor: markerColor,
        color: markerBorder,
        weight: markerWeight,
        dashArray: markerDashArray,
        opacity: 1,
        fillOpacity: 0.92,
      });

      const bannerPropuesta = tieneCambio
        ? `<div style="margin-top: 5px; padding: 5px 8px; background-color: #F8FAFC; border: 1.5px dashed #334155; border-radius: 6px; font-size: 10px; font-family: monospace;">
            <div style="color: #0F172A; font-weight: bold; display: flex; align-items: center; justify-content: space-between; gap: 4px; border-bottom: 1px solid #E2E8F0; padding-bottom: 3px; margin-bottom: 3px;">
              <span>${etiquetaCambio}</span>
              <span style="background: #E2E8F0; color: #0F172A; padding: 0 5px; border-radius: 3px; font-size: 8.5px; font-weight: 700;">${c.propuesta_estado || 'PENDIENTE'}</span>
            </div>
            <div style="margin-top: 2px; display: flex; align-items: center; justify-content: space-between;">
              <span style="color: #64748B;">Ruta:</span>
              <span>
                ${cambioRuta
                  ? `<span style="text-decoration: line-through; color: #94A3B8;">${formatRuta(rutaOrig)}</span>
                     <span style="color: #64748B; margin: 0 3px;">-></span>
                     <b style="color: ${getRutaColor(rutaEfectiva)};">${formatRuta(rutaEfectiva)}</b>`
                  : `<b style="color: ${getRutaColor(rutaEfectiva)};">${formatRuta(rutaEfectiva)}</b> <span style="font-size: 9px; color: #94A3B8;">(sin cambio)</span>`
                }
              </span>
            </div>
            <div style="margin-top: 2px; display: flex; align-items: center; justify-content: space-between;">
              <span style="color: #64748B;">Día:</span>
              <span>
                ${cambioDia
                  ? `<span style="text-decoration: line-through; color: #94A3B8;">${diaOrig || 'S/D'}</span>
                     <span style="color: #64748B; margin: 0 3px;">-></span>
                     <b style="color: ${getDiaColor(diaEfectivo)};">${diaEfectivo || 'S/D'}</b>`
                  : `<b style="color: ${getDiaColor(diaEfectivo)};">${diaEfectivo || 'S/D'}</b> <span style="font-size: 9px; color: #94A3B8;">(sin cambio)</span>`
                }
              </span>
            </div>
          </div>`
        : '';

      if (modoPrincipal.value === 'ajuste_rtm') {
        // En modo Ajuste RTM: se enlaza el popup interactivo compacto en el punto con métricas de fact_ventas
        marker.bindPopup(generarPopupHtmlRtm(c), {
          maxWidth: 295,
          closeButton: true,
          className: 'rtm-clean-popup',
        });

        marker.on('popupopen', () => {
          if (modalAjusteRtm.value.show) return;
          clienteSeleccionado.value = c;
          clienteActivoId.value = c.id;
          actualizarKpisEnPopup(c);
          cargarKpisVentasCliente(c.cliente_id);
        });

        marker.bindTooltip(`
          <div style="font-family: inherit; font-size: 11px; line-height: 1.35; padding: 2px;">
            <div style="font-weight: 700; color: #0F172A; display: flex; align-items: center; justify-content: space-between; gap: 4px;">
              <span>${escapeHtml(c.nombre)}</span>
              ${esInactivo ? '<span style="background: #FFE4E6; color: #BE123C; font-size: 8px; font-weight: 700; padding: 1px 4px; border-radius: 3px;">INACTIVO</span>' : ''}
            </div>
            <div style="color: #64748B; font-size: 10px; margin-bottom: 2px;" class="truncate">${escapeHtml(c.direccion || 'Sin dirección registrada')}</div>
            <div style="display: flex; align-items: center; gap: 4px; font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 3px;">
              <span style="font-weight: bold; color: ${getRutaColor(c.ruta)};">${formatRuta(c.ruta)}</span>
              <span>•</span>
              <span style="font-weight: bold; color: ${getDiaColor(c.dia_norm)};">${c.dia_norm || 'S/D'}</span>
              <span>•</span>
              <span style="color: #475569;">ID: ${c.cliente_id}</span>
            </div>
            ${bannerPropuesta}
            <div style="margin-top: 3px; font-size: 9.5px; font-weight: 600; color: #0F172A;">Clic para configurar Ajuste RTM</div>
          </div>
        `, {
          direction: 'top',
          offset: [0, -6],
          opacity: 0.98,
        });
      } else {
        // En modo Visualización:
        marker.bindTooltip(`
          <div style="font-family: inherit; font-size: 11px; line-height: 1.35; padding: 2px;">
            <div style="font-weight: 700; color: #0F172A; display: flex; align-items: center; justify-content: space-between; gap: 4px;">
              <span>${escapeHtml(c.nombre)}</span>
              ${esInactivo ? '<span style="background: #FFE4E6; color: #BE123C; font-size: 8px; font-weight: 700; padding: 1px 4px; border-radius: 3px;">INACTIVO</span>' : ''}
            </div>
            <div style="color: #64748B; font-size: 10px; margin-bottom: 2px;" class="truncate">${escapeHtml(c.direccion || 'Sin dirección registrada')}</div>
            <div style="display: flex; align-items: center; gap: 4px; font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 3px;">
              <span style="font-weight: bold; color: ${getRutaColor(c.ruta)};">${formatRuta(c.ruta)}</span>
              <span>•</span>
              <span style="font-weight: bold; color: ${getDiaColor(c.dia_norm)};">${c.dia_norm || 'S/D'}</span>
              <span>•</span>
              <span style="color: #475569;">ID: ${c.cliente_id}</span>
            </div>
            ${bannerPropuesta}
            <div style="margin-top: 3px; font-size: 9.5px; font-weight: 600; color: #475569;">Clic para ver ficha</div>
          </div>
        `, {
          direction: 'top',
          offset: [0, -6],
          opacity: 0.98,
        });

        marker.on('click', () => {
          abrirModalPuntoCliente(c);
        });
      }

      marker.addTo(markersLayer);
      markersMap.set(c.id, marker);
      boundsList.push(L.latLng(c.latitud, c.longitud));
    }
  });

  // Solo centrar el mapa si mantenerVista es false
  if (!mantenerVista && boundsList.length > 0 && !editandoVertices.value) {
    try {
      const groupBounds = L.featureGroup([fronterasRutasLayer, fronterasDiasLayer, markersLayer]).getBounds();
      if (groupBounds.isValid()) {
        map.fitBounds(groupBounds, { padding: [30, 30], maxZoom: 16 });
      }
    } catch {
      // Fallback
    }
  } else if (mantenerVista && centroActual && zoomActual) {
    map.setView(centroActual, zoomActual, { animate: false });
  }

  // Mantener popup abierto si estaba activo antes del refresco
  if (mantenerVista && popupAbiertoId && markersMap.has(popupAbiertoId)) {
    const m = markersMap.get(popupAbiertoId);
    if (m) {
      m.openPopup();
    }
  }

  if (editandoVertices.value) {
    actualizarCacheGeometriasVecinas();
  }
}

// Enlace global para llamadas desde el exterior
if (typeof window !== 'undefined') {
  window.__abrirExpedienteCliente = (id) => {
    const c = clientes.value.find(item => item.id === id);
    if (c) abrirModalPuntoCliente(c);
  };

  window.__abrirModalAjusteRtm = (id) => {
    const c = clientes.value.find(item => item.id === id);
    if (c) abrirModalPuntoCliente(c);
  };

  window.__guardarPropuestaRtmDesdePopup = (clienteId, domId) => {
    guardarPropuestaRtmDesdePopup(clienteId, domId);
  };

  window.__activarCliente = (clienteId) => {
    activarClienteDirecto(clienteId);
  };

  window.__eliminarVerticePoligono = (idx) => {
    eliminarVertice(idx);
  };
}

function manejarKeydownGlobal(e) {
  // Manejo de tecla Escape para cerrar modal o lightbox
  if (e.key === 'Escape') {
    if (modalAjusteRtm.value.show) {
      e.preventDefault();
      cerrarModalAjusteRtm();
      return;
    }
    if (fotoLightbox.value.show) {
      e.preventDefault();
      fotoLightbox.value.show = false;
      return;
    }
  }

  // Manejo de tecla Enter para Guardar Propuesta en Modal RTM
  if (e.key === 'Enter') {
    if (modalAjusteRtm.value.show) {
      const activeEl = document.activeElement;
      const tag = activeEl?.tagName?.toLowerCase();

      // Permitir salto de línea si se encuentra en un textarea
      if (tag === 'textarea') return;

      // Si el foco está explícitamente en un botón de cancelar o cerrar, permitir la acción por defecto
      if (tag === 'button') {
        const txt = (activeEl.textContent || '').trim().toLowerCase();
        if (txt === 'cancelar' || txt === 'cerrar') {
          return;
        }
      }

      // Si estamos en la pestaña RTM, confirmar y guardar propuesta inmediatamente
      if (modalAjusteRtm.value.tabActivo === 'rtm') {
        e.preventDefault();
        e.stopPropagation();
        confirmarAjusteRtm();
      }
    }
  }
}

onMounted(() => {
  registerRutasCatalogo(props.catalogo_rutas);
  sincronizarFiltrosConRutasDisponibles(true);

  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', manejarKeydownGlobal);
  }

  if (typeof window !== 'undefined' && window.localStorage) {
    const savedH = localStorage.getItem('visor_alto_tabla_rtm');
    if (savedH) {
      const parsedH = parseInt(savedH, 10);
      if (!isNaN(parsedH) && parsedH >= 140 && parsedH <= 1200) {
        altoTablaCambios.value = parsedH;
      }
    }
  }

  nextTick(() => {
    initMap();
    cargarDatos();
  });
});

onUnmounted(() => {
  ocultarIndicadorSnap();
  markerIndicadorSnap = null;
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', manejarKeydownGlobal);
    delete window.__abrirExpedienteCliente;
    delete window.__abrirModalAjusteRtm;
    delete window.__guardarPropuestaRtmDesdePopup;
    delete window.__activarCliente;
    delete window.__eliminarVerticePoligono;
  }
  if (map) {
    map.remove();
    map = null;
  }
});
</script>
