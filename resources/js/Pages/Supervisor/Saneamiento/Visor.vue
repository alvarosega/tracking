<template>
  <SupervisorLayout full-bleed title="Saneamiento | Visor & Fronteras">
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900 text-slate-900 font-sans">
      <!-- Navegación de Submódulos Saneamiento Padrón -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 shrink-0 relative z-30">
        <AuditoriaSaneamientoSubTabs active-tab="visor" :compact="true" />
      </div>

      <!-- ========================================== -->
      <!-- MOBILE HEADER (< md): 2 FILAS RESPONSIVAS -->
      <!-- ========================================== -->
      <div class="flex md:hidden flex-col gap-2 p-2.5 bg-white/95 backdrop-blur-md border-b border-slate-200/90 shrink-0 relative z-30">
        <!-- Backdrop invisible para cerrar menú al hacer click afuera -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Fila 1: [ Visualización | Ajuste RTM ] + [ Mapa | Panel ] -->
        <div class="flex items-center justify-between gap-2 flex-wrap">
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
              <span v-if="kpis.con_cambios > 0" class="px-1 py-0.2 rounded-full text-[8.5px] font-mono bg-white/20 font-bold">
                {{ kpis.con_cambios }}
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

        <!-- Fila 2: Filtros de Canal, Rutas, Botón Consultar y KPIs Móvil -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar scroll-touch pt-0.5">
          <!-- Filtro Dropdown: Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
          >
            <span class="text-slate-500 font-normal">Canal:</span>
            <span class="font-bold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-900 flex items-center gap-1.5 transition-colors cursor-pointer shrink-0 shadow-2xs"
          >
            <span class="text-slate-500 font-normal">Rutas:</span>
            <span class="font-bold text-slate-900">{{ labelRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- BOTÓN CONSULTAR (Móvil) -->
          <button
            type="button"
            @click="cargarDatos"
            :disabled="cargando"
            class="h-8 px-3.5 bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 select-none shrink-0"
            title="Consultar puntos y fronteras de rutas seleccionadas"
          >
            <svg v-if="!cargando" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <span v-else class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ cargando ? 'Cargando...' : 'Consultar' }}</span>
          </button>

          <!-- Toggle: Solo Activos vs Todos -->
          <button
            type="button"
            @click="soloActivos = !soloActivos"
            class="h-8 px-2.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-all border cursor-pointer select-none shrink-0"
            :class="soloActivos
              ? 'bg-slate-200 text-slate-900 border-slate-400 font-semibold'
              : 'bg-slate-100 text-slate-700 border-slate-300'"
          >
            <span class="w-2 h-2 rounded-full" :class="soloActivos ? 'bg-slate-700' : 'bg-slate-400'"></span>
            <span>{{ soloActivos ? 'Solo Activos' : 'Todos' }}</span>
          </button>

          <!-- KPIs Móvil resumidos -->
          <div class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded-md flex items-center gap-1.5 text-[11px] font-mono text-slate-800 shrink-0">
            <span class="font-semibold">{{ kpis.total }} total</span>
            <span class="text-slate-400">•</span>
            <span class="font-semibold text-slate-700">{{ kpis.con_gps }} GPS</span>
            <template v-if="kpis.con_cambios > 0">
              <span class="text-slate-400">•</span>
              <span class="font-bold text-slate-900">{{ kpis.con_cambios }} cambios</span>
            </template>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- DESKTOP HEADER (>= md): 1 FILA COMPLETA   -->
      <!-- ========================================== -->
      <div class="hidden md:flex bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-4 py-2 items-center justify-between gap-2.5 shrink-0 relative z-30">
        <!-- Backdrop invisible para cerrar menú al hacer click afuera -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar scroll-touch py-0.5 flex-1 min-w-0">
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
              <span v-if="kpis.con_cambios > 0" class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20 font-bold">
                {{ kpis.con_cambios }}
              </span>
            </button>
          </div>

          <div class="h-5 w-px bg-slate-200 shrink-0"></div>

          <!-- Filtro Dropdown: Canales (Teleport al Body) -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400 font-normal">Canal:</span>
            <span class="font-bold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Dropdown: Rutas (Teleport al Body) -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400 font-normal">Rutas:</span>
            <span class="font-semibold text-slate-900">{{ labelRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro Segmentado: Días de Visita -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0">
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

          <!-- Selector de Modo de Coloración -->
          <div class="hidden lg:flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0">
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

          <!-- Toggle: Solo Activos vs Todos -->
          <button
            type="button"
            @click="soloActivos = !soloActivos"
            class="h-8 px-2.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all border cursor-pointer select-none shadow-2xs shrink-0"
            :class="soloActivos
              ? 'bg-slate-200 text-slate-900 border-slate-300 hover:bg-slate-300'
              : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'"
            :title="soloActivos ? 'Mostrando únicamente clientes Activos. Clic para incluir inactivos.' : 'Mostrando todos los clientes (activos e inactivos). Clic para filtrar solo activos.'"
          >
            <span class="w-2 h-2 rounded-full" :class="soloActivos ? 'bg-slate-700' : 'bg-slate-400'"></span>
            <span>{{ soloActivos ? 'Solo Activos' : 'Todos' }}</span>
          </button>

          <!-- Checkboxes de Fronteras GIS -->
          <div class="hidden xl:flex items-center space-x-3 text-xs pl-2 border-l border-slate-200 font-medium text-slate-700 shrink-0">
            <label class="flex items-center gap-1.5 cursor-pointer select-none">
              <input type="checkbox" v-model="verFronteraRutas" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span>Frontera Rutas</span>
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer select-none">
              <input type="checkbox" v-model="verFronteraDias" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <span>Frontera Días</span>
            </label>
          </div>
        </div>

        <!-- Indicador de Carga, Selector Móvil y KPIs -->
        <div class="flex items-center space-x-2 text-xs font-mono shrink-0">
          <div class="px-2 py-0.5 bg-slate-50 border border-slate-200 rounded-md flex items-center gap-1.5 shadow-2xs">
            <span class="text-slate-400">Total:</span>
            <span class="font-bold text-slate-900 tabular-nums">{{ kpis.total }}</span>
          </div>

          <div class="flex px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-md items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
            <span class="font-semibold tabular-nums">{{ kpis.con_gps }} GPS</span>
          </div>

          <div v-if="kpis.con_cambios > 0" class="flex px-2 py-0.5 bg-slate-100 text-slate-900 border border-slate-300 rounded-md items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-700"></span>
            <span class="font-bold tabular-nums">{{ kpis.con_cambios }} cambios</span>
          </div>

          <div v-if="kpis.sin_gps > 0" class="flex px-2 py-0.5 bg-slate-50 text-slate-500 border border-slate-200 rounded-md items-center gap-1.5">
            <span class="font-medium tabular-nums">{{ kpis.sin_gps }} sin GPS</span>
          </div>
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

        <!-- Menú Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 z-[99999] text-xs font-sans"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100">
            <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-sky-600 font-semibold hover:underline cursor-pointer">Todas ({{ rutasDisponibles.length }})</button>
            <button type="button" @click="limpiarRutas" class="text-[11px] text-slate-500 font-medium hover:underline cursor-pointer">Limpiar</button>
          </div>
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta o preventa..."
            class="w-full h-7 px-2.5 mb-2 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label v-for="r in rutasFiltradasEnDropdown" :key="r.ruta" class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none">
              <input type="checkbox" :value="r.ruta" v-model="filtroRutas" class="rounded border-slate-300 text-slate-900 focus:ring-0 cursor-pointer" />
              <div class="truncate flex items-center gap-2 flex-1">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-mono font-semibold text-slate-800">{{ formatRuta(r.ruta) }}</span>
                <span class="text-slate-400 text-[10px]">({{ r.canal }})</span>
              </div>
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
      </Teleport>

      <!-- BARRA DE HERRAMIENTAS: AJUSTE RTM (FRONTERAS TERRITORIALES) -->
      <div
        v-if="modoPrincipal === 'ajuste_rtm'"
        class="bg-slate-100 border-b border-slate-300 px-4 py-2 text-xs z-20 shrink-0 text-slate-800"
      >
        <div class="flex items-center justify-between gap-2.5 flex-wrap">
          <div class="flex items-center gap-2.5 flex-wrap">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700 font-mono">
              Frontera RTM:
            </span>

            <select
              v-model="rutaParaAjustarFrontera"
              @change="alCambiarRutaParaFrontera"
              class="h-7 px-2.5 bg-white border border-slate-300 rounded-md text-xs font-mono font-bold text-slate-900 focus:outline-none focus:border-slate-500 cursor-pointer"
            >
              <option value="">-- Seleccionar ruta a ajustar --</option>
              <option v-for="r in rutasDisponibles" :key="r.ruta" :value="r.ruta">
                {{ formatRuta(r.ruta) }} ({{ r.canal }})
              </option>
            </select>

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
                <span class="text-[11px] font-mono text-slate-700 bg-white px-2 py-0.5 rounded border border-slate-300">
                  Arrastra los vértices para ajustar el perímetro
                </span>

                <button
                  type="button"
                  @click="guardarFronteraRtm"
                  :disabled="guardandoFrontera"
                  class="h-7 px-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-md text-xs flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50"
                >
                  <span v-if="guardandoFrontera" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  <span>{{ guardandoFrontera ? 'Guardando...' : 'Guardar Frontera' }}</span>
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
            <div class="p-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
              <div>
                <h3 class="text-xs font-bold text-slate-800 tracking-wider uppercase font-mono">Balance RTM</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Distribución de clientes por ruta y jornada</p>
              </div>
              <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-800">
                {{ balanceRtmPorRuta.length }} rutas
              </span>
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

              <!-- Vista 1A: Listado de Clientes Filtrables -->
              <template v-if="tabLateral === 'lista'">
                <!-- Buscador de Clientes -->
                <div class="p-2.5 border-b border-slate-100 bg-slate-50 flex items-center space-x-2">
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
                        <span
                          @click="abrirExpedienteCliente(c)"
                          class="text-xs font-semibold text-slate-900 group-hover:text-slate-700 transition-colors line-clamp-1 cursor-pointer flex-1"
                        >
                          {{ c.nombre }}
                        </span>
                      </div>

                      <div class="flex items-center gap-1 shrink-0">
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
                <span
                  class="text-[9px] font-mono font-bold px-2 py-0.5 rounded text-white shrink-0 shadow-2xs"
                  :style="{ backgroundColor: getRutaColor(clienteSeleccionado.ruta) }"
                >
                  {{ formatRuta(clienteSeleccionado.ruta) }}
                </span>
              </div>

              <!-- Tarjeta de RTM Actual y Botón de Ajuste Dinámico -->
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

          <!-- Leyenda Dinámica en Tiempo Real -->
          <div
            v-if="clientes.length > 0"
            class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-3 shadow-xl z-30 text-xs max-w-xs max-h-64 overflow-y-auto"
          >
            <div class="flex items-center justify-between pb-1.5 mb-2 border-b border-slate-100">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono">
                Codificación: {{ modoColor.toUpperCase() }}
              </p>
              <span class="text-[10px] font-mono text-slate-500">{{ elementosLeyenda.length }} grupos</span>
            </div>

            <div class="space-y-1.5 text-[11px]">
              <div
                v-for="item in elementosLeyenda"
                :key="item.id"
                class="flex items-center justify-between gap-2 px-1.5 py-0.5 rounded hover:bg-slate-50 font-mono"
              >
                <div class="flex items-center gap-2 truncate">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-slate-300" :style="{ backgroundColor: item.color }"></span>
                  <span class="text-slate-800 font-medium truncate">{{ item.nombre }}</span>
                </div>
                <span class="text-[10px] text-slate-400 font-semibold tabular-nums shrink-0">{{ item.conteo }} pts</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ========================================== -->
      <!-- MODAL PARA AJUSTAR RTM DE UN CLIENTE       -->
      <!-- ========================================== -->
      <!-- MODAL DE CLIENTE Y MENÚ DE AJUSTAR RTM (Se abre directamente al hacer clic en un punto) -->
      <Teleport to="body">
        <div
          v-if="modalAjusteRtm.show"
          class="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs"
          @click.self="modalAjusteRtm.show = false"
        >
          <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden text-slate-900 font-sans animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Modal con Datos del Cliente -->
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-start justify-between gap-3 shrink-0">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="text-[10px] font-mono font-bold px-2 py-0.5 rounded text-white shadow-2xs"
                    :style="{ backgroundColor: getRutaColor(modalAjusteRtm.cliente?.ruta) }"
                  >
                    {{ formatRuta(modalAjusteRtm.cliente?.ruta) }}
                  </span>
                  <span
                    class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded border uppercase"
                    :class="(modalAjusteRtm.cliente?.estado || '').toLowerCase() === 'activo' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
                  >
                    {{ modalAjusteRtm.cliente?.estado || 'Activo' }}
                  </span>
                  <span class="text-[11px] text-slate-500 font-mono">
                    ID: {{ modalAjusteRtm.cliente?.cliente_id }}
                  </span>
                  <span v-if="modalAjusteRtm.cliente?.canal || getCanalRuta(modalAjusteRtm.cliente?.ruta)" class="text-[10px] text-slate-400 font-mono">
                    ({{ modalAjusteRtm.cliente?.canal || getCanalRuta(modalAjusteRtm.cliente?.ruta) }})
                  </span>
                </div>

                <h3 class="text-sm font-bold text-slate-900 mt-1 line-clamp-1 leading-snug">
                  {{ modalAjusteRtm.cliente?.nombre }}
                </h3>
                <p class="text-[11px] text-slate-500 truncate mt-0.5">
                  {{ modalAjusteRtm.cliente?.direccion || 'Sin dirección registrada' }}
                </p>

                <!-- Acciones Rápidas (Maps, WA, Llamar) -->
                <div class="flex items-center gap-1.5 mt-2 flex-wrap text-[10px] font-semibold">
                  <a
                    v-if="modalAjusteRtm.cliente?.latitud && modalAjusteRtm.cliente?.longitud"
                    :href="'https://www.google.com/maps?q=' + modalAjusteRtm.cliente?.latitud + ',' + modalAjusteRtm.cliente?.longitud"
                    target="_blank"
                    class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded border border-slate-300 flex items-center gap-1 transition-colors"
                  >
                    Maps
                  </a>
                  <a
                    v-if="getCleanTel(modalAjusteRtm.cliente)"
                    :href="'https://wa.me/591' + getCleanTel(modalAjusteRtm.cliente)"
                    target="_blank"
                    class="px-2 py-0.5 bg-slate-900 hover:bg-slate-800 text-white rounded flex items-center gap-1 transition-colors"
                  >
                    WhatsApp
                  </a>
                  <a
                    v-if="getCleanTel(modalAjusteRtm.cliente)"
                    :href="'tel:' + getCleanTel(modalAjusteRtm.cliente)"
                    class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded border border-slate-300 flex items-center gap-1 transition-colors"
                  >
                    Tel: {{ modalAjusteRtm.cliente?.celular || modalAjusteRtm.cliente?.contacto }}
                  </a>
                </div>
              </div>

              <button
                type="button"
                @click="modalAjusteRtm.show = false"
                class="text-slate-400 hover:text-slate-600 font-bold text-lg leading-none p-1 rounded-md hover:bg-slate-100 cursor-pointer transition-colors"
                title="Cerrar modal"
              >
                Cerrar
              </button>
            </div>

            <!-- Selector de Pestañas del Modal -->
            <div class="flex items-center border-b border-slate-200 bg-slate-100 px-4 pt-1 gap-2 text-xs shrink-0 select-none">
              <button
                type="button"
                @click="modalAjusteRtm.tabActivo = 'rtm'"
                class="pb-2 px-3 font-bold border-b-2 transition-all flex items-center gap-1.5 cursor-pointer text-xs"
                :class="modalAjusteRtm.tabActivo === 'rtm' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'"
              >
                <span>Ajustar RTM (Ruta / Día)</span>
              </button>

              <button
                type="button"
                @click="modalAjusteRtm.tabActivo = 'visitas'"
                class="pb-2 px-3 font-bold border-b-2 transition-all flex items-center gap-1.5 cursor-pointer text-xs"
                :class="modalAjusteRtm.tabActivo === 'visitas' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'"
              >
                <span>Fotos y Visitas</span>
                <span class="text-[10px] font-mono px-1.5 py-0.2 rounded-full bg-slate-200 text-slate-700 font-semibold">
                  {{ visitasCliente.length }}
                </span>
              </button>
            </div>

            <!-- CUERPO DEL MODAL (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 custom-scroll text-xs">
              <!-- TAB 1: FORMULARIO DE AJUSTE RTM -->
              <form v-if="modalAjusteRtm.tabActivo === 'rtm'" @submit.prevent="confirmarAjusteRtm" class="space-y-4">
                <!-- Comparativa RTM Actual vs Nueva Propuesta -->
                <div class="grid grid-cols-2 gap-3">
                  <!-- Tarjeta Actual -->
                  <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-1">
                    <div class="text-[10px] text-slate-500 uppercase font-bold font-mono tracking-wider">
                      Asignación Actual
                    </div>
                    <div class="flex items-center gap-1.5 mt-1">
                      <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(modalAjusteRtm.cliente?.ruta) }"></span>
                      <span class="font-bold font-mono text-xs text-slate-800">{{ formatRuta(modalAjusteRtm.cliente?.ruta) }}</span>
                    </div>
                    <div class="text-[11px] text-slate-600 font-mono">
                      Día: <b :style="{ color: getDiaColor(modalAjusteRtm.cliente?.dia_norm) }">{{ modalAjusteRtm.cliente?.dia_norm || 'S/D' }}</b>
                    </div>
                    <div class="text-[10px] text-slate-400 truncate">
                      Vendedor: {{ getVendedorRuta(modalAjusteRtm.cliente?.ruta) }}
                    </div>
                  </div>

                  <!-- Tarjeta Propuesta -->
                  <div class="p-3 rounded-lg bg-slate-100 border border-slate-300 space-y-1">
                    <div class="text-[10px] text-slate-700 uppercase font-bold font-mono tracking-wider">
                      Nueva Asignación RTM
                    </div>
                    <div class="flex items-center gap-1.5 mt-1">
                      <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(formRtm.ruta_propuesta) }"></span>
                      <span class="font-bold font-mono text-xs text-slate-900">{{ formatRuta(formRtm.ruta_propuesta) }}</span>
                    </div>
                    <div class="text-[11px] text-slate-800 font-mono">
                      Día: <b :style="{ color: getDiaColor(formRtm.dia_propuesto) }">{{ formRtm.dia_propuesto }}</b>
                    </div>
                    <div class="text-[10px] text-slate-500 truncate">
                      Vendedor: {{ getVendedorRuta(formRtm.ruta_propuesta) }}
                    </div>
                  </div>
                </div>

                <!-- Selectores de Ruta y Día -->
                <div class="space-y-3 pt-1">
                  <!-- Selector de Nueva Ruta -->
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                      Nueva Ruta RTM:
                    </label>
                    <select
                      v-model="formRtm.ruta_propuesta"
                      required
                      class="w-full h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs font-mono font-semibold text-slate-900 focus:outline-none focus:border-slate-500 cursor-pointer"
                    >
                      <option v-for="r in catalogo_rutas" :key="r.ruta" :value="r.ruta">
                        {{ formatRuta(r.ruta) }} ({{ r.canal }}) — {{ r.vendedor || 'Sin vendedor' }}
                      </option>
                    </select>
                  </div>

                  <!-- Selector de Nuevo Día de Visita -->
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
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
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                      Motivo / Observación del Ajuste:
                    </label>
                    <input
                      type="text"
                      v-model="formRtm.observaciones"
                      placeholder="Ej: Rebalanceo territorial, ajuste de frontera..."
                      class="w-full h-8 px-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:border-slate-500"
                    />
                  </div>

                  <!-- Nota Informativa de Propuesta Saneamiento -->
                  <div class="p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-[11px] text-slate-800 select-none">
                    <span class="font-bold">Registro de Auditoría:</span>
                    <p class="text-slate-600 text-[10.5px] mt-0.5 leading-relaxed">
                      Este ajuste se guarda como propuesta en <b>Auditoría y Saneamiento</b>. La base inmutable de <code>pan_ruteo</code> no es alterada.
                    </p>
                  </div>
                </div>

                <!-- Botones de Acción del Formulario -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                  <button
                    type="button"
                    @click="modalAjusteRtm.show = false"
                    class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md font-semibold cursor-pointer transition-colors"
                  >
                    Cancelar
                  </button>

                  <button
                    type="submit"
                    :disabled="guardandoRtm"
                    class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-md font-bold shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 transition-colors"
                  >
                    <span v-if="guardandoRtm" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <span>{{ guardandoRtm ? 'Guardando Ajuste...' : 'Confirmar Ajuste RTM' }}</span>
                  </button>
                </div>
              </form>

              <!-- TAB 2: HISTORIAL DE VISITAS & FOTOS DE PREVENTA -->
              <div v-else class="space-y-3">
                <div v-if="cargandoVisitas" class="py-10 text-center text-xs text-slate-400 animate-pulse flex flex-col items-center justify-center gap-2">
                  <span class="w-5 h-5 border-2 border-slate-600 border-t-transparent rounded-full animate-spin"></span>
                  <span>Cargando fotos y visitas registradas en terreno...</span>
                </div>

                <div v-else-if="visitasCliente.length === 0" class="py-10 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                  <p class="font-medium text-slate-600">Sin visitas recientes con fotografía</p>
                  <p class="text-[11px] text-slate-400 mt-1">Este cliente no registra evidencias fotográficas de preventa aún.</p>
                </div>

                <div v-else class="space-y-3">
                  <div
                    v-for="vis in visitasCliente"
                    :key="vis.id"
                    class="p-3 bg-slate-50 border border-slate-200/90 rounded-xl text-xs space-y-2 shadow-2xs"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="font-mono text-slate-700 font-bold text-[11px]">{{ vis.fecha }} {{ vis.hora }}</span>
                        <span class="text-slate-400 font-mono text-[10px]">Ruta: {{ formatRuta(vis.route) }}</span>
                      </div>
                      <span
                        class="px-1.5 py-0.2 rounded text-[10px] font-mono uppercase font-bold"
                        :class="vis.status === 'VISITED' ? 'bg-slate-200 text-slate-800 border border-slate-300' : 'bg-slate-100 text-slate-700'"
                      >
                        {{ vis.status || 'VISITADO' }}
                      </span>
                    </div>

                    <p v-if="vis.comments" class="text-[11px] text-slate-600 italic bg-white p-2 rounded-lg border border-slate-200/60">
                      "{{ vis.comments }}"
                    </p>

                    <!-- Thumbnail con Visor Lightbox y Rotación -->
                    <div
                      v-if="vis.photo_url"
                      class="mt-1 group/photo relative cursor-pointer overflow-hidden rounded-lg border border-slate-200 bg-slate-900"
                      @click="abrirLightboxFoto(vis)"
                      title="Clic para ampliar y rotar foto"
                    >
                      <img
                        :src="vis.photo_url"
                        alt="Foto de visita"
                        class="w-full h-44 object-cover group-hover/photo:scale-105 transition-transform duration-200"
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue';
import { PhotoLightboxModal } from '@/Components/UI';
import { mapsConfig } from '@/Config/maps';
import { getCanalColor, getRutaColor, formatRuta, getDiaColor, registerRutasCatalogo } from '@/Config/colors';
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

// ── MODOS DEL VISOR Y FRONTERAS ──
// modoPrincipal: 'visualizacion' | 'ajuste_rtm'
const modoPrincipal = ref('visualizacion');
// subModoVisualizacion: 'original' (base inmutable pan_ruteo) | 'cambios' (pan_ruteo + propuestas activas)
const subModoVisualizacion = ref('original');
// subHerramientaRtm: 'fronteras' (mover polígonos) | 'reasignacion' (reasignar rutas/días de clientes)
const subHerramientaRtm = ref('fronteras');

// Selección múltiple para reasignación en lote (Modo Ajuste RTM)
const clientesSeleccionadosIds = ref([]);
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
const soloActivos = ref(true);
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

const clientes = ref([]);
const fronterasRutas = ref([]);
const fronterasDias = ref([]);
const kpis = ref({ total: 0, con_gps: 0, sin_gps: 0, con_cambios: 0 });
const toastMensaje = ref('');

// ── Herramienta de Fronteras RTM ──
const mostrarHerramientaFronteras = ref(false);
const rutaParaAjustarFrontera = ref('');
const editandoVertices = ref(false);
const guardandoFrontera = ref(false);
let markersVertices = [];
let poligonoEnEdicion = null;
let coordsPoligonoEnEdicion = [];

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

const labelRutas = computed(() => {
  if (filtroRutas.value.length === 0) return 'Ninguna';
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 1) return formatRuta(filtroRutas.value[0]);
  return `${filtroRutas.value.length} sel.`;
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
  renderizarEnMapa();
}

function setSubModoVisualizacion(sub) {
  subModoVisualizacion.value = sub;
  cargarDatos();
}

// Balance RTM agrupado por ruta y día
const balanceRtmPorRuta = computed(() => {
  if (clientes.value.length === 0) return [];
  const mapRutas = new Map();

  clientes.value.forEach(c => {
    const rKey = c.ruta || 'SIN RUTA';
    if (!mapRutas.has(rKey)) {
      const rutaInfo = props.catalogo_rutas.find(r => r.ruta === rKey);
      mapRutas.set(rKey, {
        ruta: rKey,
        canal: rutaInfo ? rutaInfo.canal : 'General',
        total: 0,
        con_gps: 0,
        dias: {},
      });
    }

    const item = mapRutas.get(rKey);

    const diaClave = normalizarDiaClave(c.dia_norm);
    const diaKey = diaClave || 'SIN DIA';
    item.dias[diaKey] = (item.dias[diaKey] || 0) + 1;

    // En el total por ruta, solo se debe contar los clientes de lunes a sábado
    if (DIAS_LUNES_A_SABADO.includes(diaClave)) {
      item.total++;
      if (c.tiene_gps) item.con_gps++;
    }
  });

  return Array.from(mapRutas.values()).sort((a, b) => b.total - a.total);
});

// Elementos dinámicos para la leyenda del mapa
const elementosLeyenda = computed(() => {
  if (clientes.value.length === 0) return [];
  const mapCounts = new Map();

  clientes.value.forEach(c => {
    if (!c.tiene_gps) return;
    let key, nombre, color;

    if (modoColor.value === 'ruta') {
      key = c.ruta || 'Sin Ruta';
      nombre = formatRuta(c.ruta);
      color = getRutaColor(c.ruta);
    } else if (modoColor.value === 'dia') {
      key = c.dia_norm || 'Sin Día';
      nombre = c.dia_norm || 'Sin Día';
      color = getDiaColor(c.dia_norm);
    } else {
      const rutaInfo = props.catalogo_rutas.find(r => r.ruta === c.ruta);
      const canal = rutaInfo ? rutaInfo.canal : 'General';
      key = canal;
      nombre = canal;
      color = getCanalColor(canal);
    }

    if (!mapCounts.has(key)) {
      mapCounts.set(key, { id: key, nombre, color, conteo: 0 });
    }
    mapCounts.get(key).conteo++;
  });

  return Array.from(mapCounts.values()).sort((a, b) => b.conteo - a.conteo);
});

const tieneFronteraRutaActual = computed(() => {
  if (!rutaParaAjustarFrontera.value) return false;
  return fronterasRutas.value.some(fr => fr.ruta.toUpperCase() === rutaParaAjustarFrontera.value.toUpperCase());
});

function setModoColor(modo) {
  modoColor.value = modo;
  renderizarEnMapa();
}

function toggleMenu(nombre, e) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = e.currentTarget.getBoundingClientRect();
  posicionMenu.value = {
    top: rect.bottom + 6,
    left: Math.min(rect.left, window.innerWidth - 270),
  };
  menuAbierto.value = nombre;
}

// Filtros: solo actualizan estado, NO autoejecutan consulta
function onCanalesChange() {
  const validRutas = new Set(rutasDisponibles.value.map(r => r.ruta));
  filtroRutas.value = filtroRutas.value.filter(r => validRutas.has(r));
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
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
}

function limpiarRutas() {
  filtroRutas.value = [];
}

function toggleDia(diaKey) {
  const index = filtroDias.value.indexOf(diaKey);
  if (index > -1) {
    filtroDias.value.splice(index, 1);
  } else {
    filtroDias.value.push(diaKey);
  }
}

function toggleTodosDias() {
  if (filtroDias.value.length === diasSemana.length) {
    filtroDias.value = [];
  } else {
    filtroDias.value = diasSemana.map(d => d.key);
  }
}

// CONSULTA PRINCIPAL A BACKEND (Ejecutada por Botón Consultar)
async function cargarDatos() {
  menuAbierto.value = null;

  if (filtroRutas.value.length === 0 && filtroCanales.value.length === 0) {
    clientes.value = [];
    fronterasRutas.value = [];
    fronterasDias.value = [];
    kpis.value = { total: 0, con_gps: 0, sin_gps: 0, con_cambios: 0 };
    renderizarEnMapa();
    return;
  }

  // Ajustar automáticamente el modo de color óptimo
  if (filtroRutas.value.length === 1) {
    modoColor.value = 'dia';
  } else if (filtroRutas.value.length > 1) {
    modoColor.value = 'ruta';
  }

  cargando.value = true;
  try {
    const res = await axios.post(route('supervisor.saneamiento.visor.data'), {
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
      dias: filtroDias.value,
      ver_frontera_rutas: verFronteraRutas.value,
      ver_frontera_dias: verFronteraDias.value,
      solo_activos: soloActivos.value,
      modo_vista: subModoVisualizacion.value,
    });

    clientes.value = res.data.clientes || [];
    fronterasRutas.value = res.data.fronteras_rutas || [];
    fronterasDias.value = res.data.fronteras_dias || [];
    kpis.value = res.data.kpis || { total: 0, con_gps: 0, sin_gps: 0, con_cambios: 0 };

    registerRutasCatalogo(clientes.value.map(c => c.ruta));
    renderizarEnMapa();
  } catch (error) {
    console.error('Error al consultar datos de ruteo:', error);
  } finally {
    cargando.value = false;
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
      await cargarDatos();
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

// ── LÓGICA DE EDICIÓN Y AJUSTE DE FRONTERAS RTM ──

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

function limpiarMarkersVertices() {
  markersVertices.forEach(m => {
    if (map) map.removeLayer(m);
  });
  markersVertices = [];
  if (poligonoEnEdicion && map) {
    map.removeLayer(poligonoEnEdicion);
    poligonoEnEdicion = null;
  }
}

function cancelarEdicionVertices() {
  editandoVertices.value = false;
  limpiarMarkersVertices();
  renderizarEnMapa();
}

function activarEdicionVertices() {
  if (!rutaParaAjustarFrontera.value) return;
  limpiarMarkersVertices();

  const fr = fronterasRutas.value.find(f => f.ruta.toUpperCase() === rutaParaAjustarFrontera.value.toUpperCase());
  if (fr && fr.geojson) {
    // GeoJSON almacena [lng, lat]
    const coordsGeo = fr.geojson.coordinates?.[0] || [];
    coordsPoligonoEnEdicion = coordsGeo.map(pt => [Number(pt[1]), Number(pt[0])]);
  } else {
    generarConvexHullRuta();
    return;
  }

  if (coordsPoligonoEnEdicion.length < 3) {
    alert('Se requieren al menos 3 vértices para editar el polígono.');
    return;
  }

  editandoVertices.value = true;
  crearCapaEdicionPoligono();
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

  coordsPoligonoEnEdicion = hull;
  editandoVertices.value = true;
  crearCapaEdicionPoligono();
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

function crearCapaEdicionPoligono() {
  if (!map || coordsPoligonoEnEdicion.length < 3) return;

  const rutaColor = getRutaColor(rutaParaAjustarFrontera.value);

  poligonoEnEdicion = L.polygon(coordsPoligonoEnEdicion, {
    color: '#0F172A',
    weight: 2.5,
    dashArray: '4, 4',
    fillColor: rutaColor,
    fillOpacity: 0.18,
  }).addTo(map);

  map.fitBounds(poligonoEnEdicion.getBounds(), { padding: [40, 40] });

  // Crear marcadores interactivos arrastrables en cada vértice
  coordsPoligonoEnEdicion.forEach((pt, idx) => {
    const marker = L.circleMarker(pt, {
      radius: 6.5,
      color: '#0F172A',
      fillColor: '#FFFFFF',
      fillOpacity: 1,
      weight: 2.5,
    }).addTo(map);

    marker.on('mousedown', function () {
      map.dragging.disable();

      function onMouseMove(moveEvent) {
        marker.setLatLng(moveEvent.latlng);
        coordsPoligonoEnEdicion[idx] = [moveEvent.latlng.lat, moveEvent.latlng.lng];
        if (poligonoEnEdicion) {
          poligonoEnEdicion.setLatLngs(coordsPoligonoEnEdicion);
        }
      }

      function onMouseUp() {
        map.off('mousemove', onMouseMove);
        map.off('mouseup', onMouseUp);
        map.dragging.enable();
      }

      map.on('mousemove', onMouseMove);
      map.on('mouseup', onMouseUp);
    });

    markersVertices.push(marker);
  });
}

async function guardarFronteraRtm() {
  if (!rutaParaAjustarFrontera.value || coordsPoligonoEnEdicion.length < 3) return;
  guardandoFrontera.value = true;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.fronteras.guardar')
      : '/supervisor/saneamiento/fronteras/guardar';

    const res = await axios.post(url, {
      ruta: rutaParaAjustarFrontera.value,
      coordenadas: coordsPoligonoEnEdicion,
    });

    if (res.data?.status === 'ok' || res.data?.frontera_id) {
      mostrarToast(`Frontera RTM de ${formatRuta(rutaParaAjustarFrontera.value)} guardada exitosamente.`);
      cancelarEdicionVertices();
      await cargarDatos();
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

async function cargarVisitasCliente(clienteId) {
  if (!clienteId) {
    visitasCliente.value = [];
    return;
  }
  cargandoVisitas.value = true;
  visitasCliente.value = [];
  try {
    const res = await axios.post(`/supervisor/visitas/fotos/${clienteId}`);
    visitasCliente.value = res.data.visitas || [];
  } catch (error) {
    console.error('Error al cargar visitas:', error);
    visitasCliente.value = [];
  } finally {
    cargandoVisitas.value = false;
  }
}

function abrirModalPuntoCliente(c) {
  if (!c) return;
  clienteActivoId.value = c.id;
  clienteSeleccionado.value = c;

  modalAjusteRtm.value = {
    show: true,
    cliente: c,
    tabActivo: 'rtm',
  };

  formRtm.value = {
    cliente_id: c.cliente_id || c.id,
    ruta_propuesta: c.ruta || (props.catalogo_rutas[0]?.ruta || ''),
    dia_propuesto: c.dia_norm || 'LUNES',
    observaciones: 'Ajuste dinámico RTM desde Visor',
    aplicar_inmediato: true,
  };

  if (map && c.latitud && c.longitud) {
    map.panTo([c.latitud, c.longitud], { animate: true, duration: 0.5 });
  }

  cargarVisitasCliente(c.cliente_id);
}

const abrirModalAjusteRtm = abrirModalPuntoCliente;
const abrirExpedienteCliente = abrirModalPuntoCliente;

async function confirmarAjusteRtm() {
  if (!formRtm.value.ruta_propuesta) return;
  guardandoRtm.value = true;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.ajustar-rtm')
      : '/supervisor/saneamiento/visor/ajustar-rtm';

    const res = await axios.post(url, formRtm.value);

    if (res.data?.success) {
      mostrarToast(res.data.message || 'Propuesta RTM registrada exitosamente');
      modalAjusteRtm.value.show = false;
      await cargarDatos();
    } else {
      alert(res.data?.message || 'No se pudo aplicar el ajuste RTM.');
    }
  } catch (err) {
    console.error('Error al ajustar RTM del cliente:', err);
    alert('Error al ajustar RTM: ' + (err.response?.data?.message || err.message));
  } finally {
    guardandoRtm.value = false;
  }
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

function generarPopupHtmlRtm(c) {
  const rutasOptions = props.catalogo_rutas.map(r => {
    const sel = r.ruta === c.ruta ? 'selected' : '';
    return `<option value="${escapeHtml(r.ruta)}" ${sel}>${escapeHtml(formatRuta(r.ruta))} (${escapeHtml(r.canal)})</option>`;
  }).join('');

  const diasOptions = diasSemana.map(d => {
    const sel = (c.dia_norm && d.label.toUpperCase() === c.dia_norm.toUpperCase()) ? 'selected' : '';
    return `<option value="${d.label}" ${sel}>${d.label}</option>`;
  }).join('');

  return `
    <div style="min-width: 230px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; color: #0f172a; padding: 2px;">
      <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 6px;">
        <div style="font-weight: 700; font-size: 12px; color: #0f172a; line-height: 1.25;">${escapeHtml(c.nombre)}</div>
        <div style="color: #64748b; font-size: 10px; margin-top: 2px;">${escapeHtml(c.direccion || 'Sin dirección')}</div>
        <div style="font-family: monospace; font-size: 10px; color: #475569; margin-top: 3px;">
          ID: ${c.cliente_id} • Actual: <b>${escapeHtml(formatRuta(c.ruta))}</b> (${c.dia_norm || 'S/D'})
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 5px;">
        <div>
          <label style="display: block; font-size: 10px; font-weight: 600; color: #334155; margin-bottom: 2px;">Nueva Ruta:</label>
          <select id="rtm-popup-ruta-${c.id}" style="width: 100%; height: 26px; padding: 0 6px; font-size: 11px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; outline: none; cursor: pointer;">
            ${rutasOptions}
          </select>
        </div>

        <div>
          <label style="display: block; font-size: 10px; font-weight: 600; color: #334155; margin-bottom: 2px;">Nuevo Día de Visita:</label>
          <select id="rtm-popup-dia-${c.id}" style="width: 100%; height: 26px; padding: 0 6px; font-size: 11px; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; outline: none; cursor: pointer;">
            ${diasOptions}
          </select>
        </div>

        <div>
          <label style="display: block; font-size: 10px; font-weight: 600; color: #334155; margin-bottom: 2px;">Observación:</label>
          <input id="rtm-popup-obs-${c.id}" type="text" placeholder="Rebalanceo RTM..." style="width: 100%; height: 24px; padding: 0 6px; font-size: 10.5px; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; box-sizing: border-box; outline: none;" />
        </div>

        <button
          id="rtm-btn-save-${c.id}"
          type="button"
          onclick="window.__guardarPropuestaRtmDesdePopup('${c.cliente_id || c.id}', '${c.id}')"
          style="margin-top: 4px; width: 100%; height: 26px; background-color: #0f172a; color: #ffffff; font-weight: 600; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; transition: background 0.15s ease;"
          onmouseover="this.style.backgroundColor='#1e293b'"
          onmouseout="this.style.backgroundColor='#0f172a'"
        >
          Guardar Propuesta
        </button>
      </div>
    </div>
  `;
}

async function guardarPropuestaRtmDesdePopup(clienteId, domId) {
  const selectRuta = document.getElementById(`rtm-popup-ruta-${domId}`);
  const selectDia = document.getElementById(`rtm-popup-dia-${domId}`);
  const inputObs = document.getElementById(`rtm-popup-obs-${domId}`);
  const btn = document.getElementById(`rtm-btn-save-${domId}`);

  if (!selectRuta || !selectRuta.value) {
    alert('Por favor selecciona una ruta.');
    return;
  }

  const rutaPropuesta = selectRuta.value;
  const diaPropuesto = selectDia ? selectDia.value : 'LUNES';
  const obs = inputObs?.value?.trim() || 'Ajuste RTM territorial desde mapa Leaflet';

  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Guardando...';
    btn.style.opacity = '0.7';
  }

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.visor.ajustar-rtm')
      : '/supervisor/saneamiento/visor/ajustar-rtm';

    const res = await axios.post(url, {
      cliente_id: clienteId,
      ruta_propuesta: rutaPropuesta,
      dia_propuesto: diaPropuesto,
      observaciones: obs,
      aplicar_inmediato: true,
    });

    if (res.data?.success) {
      mostrarToast(res.data.message || 'Propuesta RTM registrada exitosamente.');
      if (map) {
        map.closePopup();
      }
      await cargarDatos();
    } else {
      alert(res.data?.message || 'No se pudo guardar la propuesta.');
      if (btn) {
        btn.disabled = false;
        btn.innerText = 'Guardar Propuesta';
        btn.style.opacity = '1';
      }
    }
  } catch (err) {
    console.error('Error al guardar propuesta RTM:', err);
    alert('Error al guardar propuesta RTM: ' + (err.response?.data?.message || err.message));
    if (btn) {
      btn.disabled = false;
      btn.innerText = 'Guardar Propuesta';
      btn.style.opacity = '1';
    }
  }
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

  map.on('click', () => {
    menuAbierto.value = null;
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

function renderizarEnMapa() {
  if (!map) return;

  markersLayer.clearLayers();
  fronterasRutasLayer.clearLayers();
  fronterasDiasLayer.clearLayers();
  markersMap.clear();

  const boundsList = [];

  // Polígonos de Rutas (Sutiles y limpios)
  if (verFronteraRutas.value && fronterasRutas.value.length > 0) {
    fronterasRutas.value.forEach(fr => {
      const rutaColor = getRutaColor(fr.ruta);
      const layer = L.geoJSON(fr.geojson, {
        style: {
          color: rutaColor,
          weight: 2,
          opacity: 0.85,
          fillColor: rutaColor,
          fillOpacity: 0.04,
          dashArray: '5, 5',
        }
      }).addTo(fronterasRutasLayer);
      layer.bindTooltip(`Ruta: <b>${formatRuta(fr.ruta)}</b> (${fr.canal || 'Canal'})`, { sticky: true });
      boundsList.push(layer.getBounds());
    });
  }

  // Polígonos de Días (Sutiles y punteados)
  if (verFronteraDias.value && fronterasDias.value.length > 0) {
    fronterasDias.value.forEach(fd => {
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
      layer.bindTooltip(`Día: <b>${fd.dia}</b>`, { sticky: true });
      boundsList.push(layer.getBounds());
    });
  }

  // Marcadores de Clientes
  clientes.value.forEach(c => {
    if (c.tiene_gps) {
      const markerColor = getMarkerColor(c);

      // Si estamos en sub-modo "Con Cambios" y el cliente tiene propuesta registrada, dibujar un halo distintivo sobrio
      if (subModoVisualizacion.value === 'cambios' && c.tiene_cambio) {
        const halo = L.circleMarker([c.latitud, c.longitud], {
          radius: 10.5,
          fill: false,
          color: '#475569', // Slate-600 sobrio
          weight: 2,
          dashArray: '3, 3',
          opacity: 0.9,
        });
        halo.addTo(markersLayer);
      }

      const marker = L.circleMarker([c.latitud, c.longitud], {
        radius: (subModoVisualizacion.value === 'cambios' && c.tiene_cambio) ? 6.5 : 6,
        fillColor: markerColor,
        color: '#FFFFFF',
        weight: 1.5,
        opacity: 1,
        fillOpacity: 0.95,
      });

      const bannerPropuesta = (subModoVisualizacion.value === 'cambios' && c.tiene_cambio)
        ? `<div style="margin-top: 4px; padding: 4px 6px; background-color: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 10px; font-family: monospace;">
            <div style="color: #0F172A; font-weight: bold; display: flex; align-items: center; justify-content: space-between; gap: 4px;">
              <span>PROPUESTA REGISTRADA</span>
              <span style="background: #E2E8F0; color: #1E293B; padding: 0 4px; border-radius: 3px; font-size: 8.5px;">${c.propuesta_estado || 'PENDIENTE'}</span>
            </div>
            <div style="color: #334155; margin-top: 2px;">
              Ruta: <span style="text-decoration: line-through; opacity: 0.6;">${formatRuta(c.ruta_original || c.ruta)}</span> -> <b>${formatRuta(c.ruta)}</b>
            </div>
            <div style="color: #334155;">
              Día: <span style="text-decoration: line-through; opacity: 0.6;">${c.dia_original || 'S/D'}</span> -> <b>${c.dia_norm}</b>
            </div>
          </div>`
        : '';

      if (modoPrincipal.value === 'ajuste_rtm') {
        // En modo Ajuste RTM: se enlaza el popup interactivo compacto en el punto. ¡CERO MODALES, CERO FOTOS!
        marker.bindPopup(generarPopupHtmlRtm(c), {
          maxWidth: 280,
          closeButton: true,
          className: 'rtm-clean-popup',
        });

        marker.bindTooltip(`
          <div style="font-family: inherit; font-size: 11px; line-height: 1.35; padding: 2px;">
            <div style="font-weight: 700; color: #0F172A;">${escapeHtml(c.nombre)}</div>
            <div style="color: #64748B; font-size: 10px; margin-bottom: 2px;" class="truncate">${escapeHtml(c.direccion || 'Sin dirección registrada')}</div>
            <div style="display: flex; align-items: center; gap: 4px; font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 3px;">
              <span style="font-weight: bold; color: ${getRutaColor(c.ruta)};">${formatRuta(c.ruta)}</span>
              <span>•</span>
              <span style="font-weight: bold; color: ${getDiaColor(c.dia_norm)};">${c.dia_norm || 'S/D'}</span>
              <span>•</span>
              <span style="color: #475569;">ID: ${c.cliente_id}</span>
            </div>
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
            <div style="font-weight: 700; color: #0F172A;">${escapeHtml(c.nombre)}</div>
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

  if (boundsList.length > 0 && !editandoVertices.value) {
    try {
      const groupBounds = L.featureGroup([fronterasRutasLayer, fronterasDiasLayer, markersLayer]).getBounds();
      if (groupBounds.isValid()) {
        map.fitBounds(groupBounds, { padding: [30, 30], maxZoom: 16 });
      }
    } catch {
      // Fallback
    }
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
}

onMounted(() => {
  registerRutasCatalogo(props.catalogo_rutas);
  nextTick(() => {
    initMap();
    if (filtroCanales.value.length > 0 || filtroRutas.value.length > 0) {
      cargarDatos();
    }
  });
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    delete window.__abrirExpedienteCliente;
    delete window.__abrirModalAjusteRtm;
    delete window.__guardarPropuestaRtmDesdePopup;
  }
  if (map) {
    map.remove();
    map = null;
  }
});
</script>
