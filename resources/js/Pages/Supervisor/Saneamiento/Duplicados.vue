<template>
  <SupervisorLayout full-bleed title="Saneamiento | Clientes Duplicados">
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-100/60 font-sans text-slate-900 select-none">
      <!-- Navegacion de Submodulos Saneamiento Padron -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-1.5 shrink-0 relative z-30">
        <AuditoriaSaneamientoSubTabs active-tab="duplicados" :compact="true" />
      </div>

      <!-- ============================================================== -->
      <!-- CABECERA PRINCIPAL: FILTROS, MODOS Y METRICAS                  -->
      <!-- ============================================================== -->
      <div class="bg-white border-b border-slate-200/90 px-3 sm:px-4 py-2 shrink-0 relative z-20 space-y-2">
        <!-- Fila 1: Selector de Modo Segmentado + Metricas Resumen -->
        <div class="flex flex-wrap items-center justify-between gap-2.5">
          <!-- Segmented Tab de Modo (Desktop y Scroll en Movil) -->
          <div class="inline-flex p-1 bg-slate-100/90 rounded-xl border border-slate-200/80 text-xs shrink-0 max-w-full overflow-x-auto custom-scroll gap-0.5">
            <button
              type="button"
              @click="cambiarModo('TODOS')"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
              :class="filtroModo === 'TODOS' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600 hover:text-slate-900'"
            >
              <span>Todos</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'TODOS' ? 'bg-slate-900 text-white' : 'bg-slate-200/70 text-slate-600'">
                {{ totalGruposTodos }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarModo('CRITICOS')"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
              :class="filtroModo === 'CRITICOS' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600 hover:text-slate-900'"
              title="Misma ubicación física y coincidencia de datos (NIT/Nombre/Tel)"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              <span>Críticos</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'CRITICOS' ? 'bg-slate-900 text-white' : 'bg-slate-200/70 text-slate-600'">
                {{ conteoMismaUbicacionYDatos }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarModo('DATOS')"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
              :class="filtroModo === 'DATOS' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600 hover:text-slate-900'"
              title="Coincidencia de Nombre, NIT, CI, Teléfono o Factura"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span>Por Datos</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'DATOS' ? 'bg-slate-900 text-white' : 'bg-slate-200/70 text-slate-600'">
                {{ conteoMismosDatos }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarModo('UBICACION')"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
              :class="filtroModo === 'UBICACION' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600 hover:text-slate-900'"
              title="Colisiones de coordenadas en el radio establecido"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
              <span>Por Geolocalización</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'UBICACION' ? 'bg-slate-900 text-white' : 'bg-slate-200/70 text-slate-600'">
                {{ conteoMismaUbicacion }}
              </span>
            </button>
          </div>

          <!-- Metricas Resumen -->
          <div class="flex items-center gap-2 text-xs font-mono">
            <div class="px-2.5 py-1 bg-slate-50 border border-slate-200/80 rounded-lg flex items-center gap-1.5 shadow-2xs text-slate-700">
              <span class="text-slate-400">Clientes:</span>
              <span class="font-bold text-slate-900 tabular-nums">{{ totalClientesImplicados }}</span>
            </div>
            <div class="px-2.5 py-1 bg-slate-50 border border-slate-200/80 rounded-lg flex items-center gap-1.5 shadow-2xs text-slate-700">
              <span class="text-slate-400">Ventas {{ periodoLabel ? `(${periodoLabel})` : 'Mes' }}:</span>
              <span class="font-bold text-emerald-700 tabular-nums">Bs. {{ ventaMtdTotalAuditada }}</span>
            </div>
            <div
              v-if="propuestasPendientes.length > 0"
              @click="mostrarPanelPropuestas = true"
              class="px-2.5 py-1 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-lg flex items-center gap-1.5 shadow-2xs text-slate-700 cursor-pointer transition-colors"
              title="Ver bandeja de propuestas"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
              <span class="font-bold text-slate-900 tabular-nums">{{ propuestasPendientes.length }}</span>
              <span class="text-slate-400 text-[10px]">propuestas</span>
            </div>
          </div>
        </div>

        <!-- Fila 2: Parametros de Auditoria (Desktop y Acceso Movil) -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-100 text-xs">
          <!-- Controles en Desktop -->
          <div class="hidden sm:flex flex-wrap items-center gap-2">
            <!-- Selector Multicanal -->
            <div class="relative inline-block text-left" ref="canalDropdownRef">
              <button
                type="button"
                @click="menuCanalAbierto = !menuCanalAbierto; menuRutaAbierto = false"
                class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-1.5 cursor-pointer font-medium text-slate-800 shadow-2xs transition-colors"
              >
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Canal:</span>
                <span class="font-bold text-sky-600">{{ etiquetaCanales }}</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div
                v-if="menuCanalAbierto"
                class="absolute top-full left-0 mt-1 w-56 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 space-y-1"
              >
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 px-1 text-[10px]">
                  <span class="font-bold text-slate-400 uppercase">Canales</span>
                  <div class="flex items-center gap-1.5">
                    <button type="button" @click="seleccionarSoloTDB" class="text-purple-600 hover:underline cursor-pointer font-bold">Solo TDB</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" @click="seleccionarTodosCanales" class="text-sky-600 hover:underline cursor-pointer font-bold">Todos</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" @click="limpiarCanales" class="text-slate-500 hover:underline cursor-pointer">Limpiar</button>
                  </div>
                </div>
                <div class="max-h-44 overflow-y-auto custom-scroll space-y-0.5">
                  <label
                    v-for="c in canalesDisponibles"
                    :key="c"
                    class="flex items-center gap-2 px-1.5 py-1 rounded hover:bg-slate-50 cursor-pointer text-xs"
                  >
                    <input
                      type="checkbox"
                      :value="c"
                      v-model="canalesSeleccionados"
                      class="rounded border-slate-300 text-sky-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="text-slate-800 font-medium">{{ c }}</span>
                  </label>
                </div>
                <div class="pt-1 border-t border-slate-100 flex justify-end">
                  <button
                    type="button"
                    @click="menuCanalAbierto = false; ejecutarAuditoriaDuplicados()"
                    class="px-2.5 py-1 bg-slate-900 text-white rounded-lg text-[10px] font-bold cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>

            <!-- Selector de Rutas (Multiseleccion con Buscador) -->
            <div class="relative inline-block text-left" ref="rutaDropdownRef">
              <button
                type="button"
                @click="menuRutaAbierto = !menuRutaAbierto; menuCanalAbierto = false"
                class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-1.5 cursor-pointer font-medium text-slate-800 shadow-2xs transition-colors"
              >
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ruta:</span>
                <span class="font-bold text-slate-700">{{ etiquetaRutas }}</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div
                v-if="menuRutaAbierto"
                class="absolute top-full left-0 mt-1 w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 space-y-1.5"
              >
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 px-1 text-[10px]">
                  <span class="font-bold text-slate-400 uppercase">Rutas ({{ rutasDisponibles.length }})</span>
                  <div class="flex items-center gap-1.5">
                    <button type="button" @click="seleccionarTodasRutas" class="text-sky-600 hover:underline cursor-pointer font-bold">Todas</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" @click="limpiarRutas" class="text-slate-500 hover:underline cursor-pointer">Limpiar</button>
                  </div>
                </div>

                <!-- Buscador de ruta -->
                <div class="relative">
                  <input
                    type="text"
                    v-model="filtroTextoRuta"
                    placeholder="Buscar ruta..."
                    class="w-full h-7 pl-6 pr-2 bg-slate-50 border border-slate-200 rounded text-[11px] focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono"
                  />
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                  </svg>
                </div>

                <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5">
                  <label
                    v-for="r in rutasFiltradasEnDropdown"
                    :key="r.ruta"
                    class="flex items-center justify-between px-1.5 py-1 rounded hover:bg-slate-50 cursor-pointer text-xs"
                  >
                    <div class="flex items-center gap-1.5 min-w-0">
                      <input
                        type="checkbox"
                        :value="r.ruta"
                        v-model="rutasSeleccionadas"
                        class="rounded border-slate-300 text-sky-600 focus:ring-0 cursor-pointer"
                      />
                      <span class="text-slate-800 font-mono font-bold">{{ r.ruta }}</span>
                    </div>
                    <span class="text-[9.5px] font-mono text-slate-400 bg-slate-100 px-1 py-0.2 rounded">{{ r.canal }}</span>
                  </label>
                  <div v-if="rutasFiltradasEnDropdown.length === 0" class="text-center py-3 text-slate-400 text-xs">
                    Sin coincidencias
                  </div>
                </div>

                <div class="pt-1 border-t border-slate-100 flex justify-end">
                  <button
                    type="button"
                    @click="menuRutaAbierto = false; ejecutarAuditoriaDuplicados()"
                    class="px-2.5 py-1 bg-slate-900 text-white rounded-lg text-[10px] font-bold cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>

            <!-- Selector de Radio en Metros -->
            <div class="flex items-center h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono shadow-2xs">
              <span class="text-slate-400 mr-1.5 font-sans text-[10px] uppercase font-bold tracking-wider">Radio:</span>
              <select
                v-model="radioMetros"
                @change="ejecutarAuditoriaDuplicados"
                class="bg-transparent font-bold text-slate-900 focus:outline-none cursor-pointer text-xs"
              >
                <option :value="5">5 m</option>
                <option :value="10">10 m</option>
                <option :value="20">20 m (Estándar)</option>
                <option :value="50">50 m</option>
                <option :value="100">100 m</option>
              </select>
            </div>

            <!-- Selector de Periodo de Ventas (Referencial) -->
            <div v-if="mesesDisponibles.length > 0" class="flex items-center h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono shadow-2xs">
              <span class="text-slate-400 mr-1.5 font-sans text-[10px] uppercase font-bold tracking-wider">Ventas:</span>
              <select
                v-model="periodoSeleccionado"
                @change="ejecutarAuditoriaDuplicados"
                class="bg-transparent font-bold text-slate-900 focus:outline-none cursor-pointer text-xs"
                title="Periodo referencial de ventas desde fact_ventas"
              >
                <option v-for="m in mesesDisponibles" :key="m.periodo" :value="m.periodo">
                  {{ m.label }}
                </option>
              </select>
            </div>

            <!-- Boton Auditar Principal -->
            <button
              type="button"
              @click="ejecutarAuditoriaDuplicados"
              :disabled="cargando"
              class="h-8 px-3.5 bg-slate-900 hover:bg-black text-white rounded-lg text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <span>{{ cargando ? 'Rastreando...' : 'Auditar' }}</span>
            </button>
          </div>

          <!-- Controles en Movil (Boton Drawer Filtros) -->
          <div class="flex sm:hidden items-center gap-1.5 w-full">
            <button
              type="button"
              @click="drawerFiltrosMovil = true"
              class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 rounded-lg text-xs font-medium flex items-center gap-1.5 cursor-pointer shadow-2xs shrink-0"
            >
              <svg class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
              <span>Filtros</span>
              <span class="px-1.5 py-0.2 bg-sky-100 text-sky-800 font-bold font-mono rounded text-[9.5px]">
                {{ radioMetros }}m
              </span>
            </button>

            <!-- Buscador en Movil -->
            <div class="relative flex-1 min-w-0">
              <input
                type="text"
                v-model="busquedaTexto"
                placeholder="Buscar cliente/ID/NIT..."
                class="w-full h-8 pl-7 pr-7 bg-slate-50 focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono"
              />
              <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <button
                v-if="busquedaTexto"
                type="button"
                @click="busquedaTexto = ''"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 flex items-center justify-center cursor-pointer"
                title="Limpiar búsqueda"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Boton Auditar Movil -->
            <button
              type="button"
              @click="ejecutarAuditoriaDuplicados"
              :disabled="cargando"
              class="h-8 w-8 bg-slate-900 text-white rounded-lg flex items-center justify-center shrink-0 cursor-pointer disabled:opacity-50"
              title="Auditar duplicados"
            >
              <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </button>
          </div>

          <!-- Buscador de Texto en Desktop con Limpiar Rapido -->
          <div class="hidden sm:block relative w-72">
            <input
              type="text"
              v-model="busquedaTexto"
              placeholder="Buscar por cliente, ID, NIT o ruta..."
              class="w-full h-8 pl-8 pr-7 bg-slate-50 focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono transition-all"
            />
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <button
              v-if="busquedaTexto"
              type="button"
              @click="busquedaTexto = ''"
              class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 flex items-center justify-center rounded-full hover:bg-slate-200 cursor-pointer"
              title="Limpiar búsqueda"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- SELECTOR DE VISTA MOVIL (< lg)                                  -->
      <!-- ============================================================== -->
      <div class="flex lg:hidden items-center justify-between p-1 bg-slate-100 border-b border-slate-200 text-xs gap-0.5 shrink-0">
        <button
          type="button"
          @click="setVistaMovil('grupos')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1"
          :class="vistaMovil === 'grupos' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600'"
        >
          <span>Grupos ({{ clustersFiltrados.length }})</span>
        </button>
        <button
          type="button"
          @click="setVistaMovil('comparador')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1"
          :class="vistaMovil === 'comparador' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600'"
        >
          <span>Comparador</span>
        </button>
        <button
          type="button"
          @click="setVistaMovil('mapa')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1"
          :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600'"
        >
          <span>Mapa</span>
        </button>
        <button
          type="button"
          @click="setVistaMovil('propuestas')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1"
          :class="vistaMovil === 'propuestas' ? 'bg-white text-slate-900 shadow-2xs font-bold border border-slate-200/50' : 'text-slate-600'"
        >
          <span>Propuestas ({{ propuestasPendientes.length }})</span>
        </button>
      </div>

      <!-- ============================================================== -->
      <!-- ESTRUCTURA PRINCIPAL: COLUMNA GRUPOS + PANEL DE RESOLUCION      -->
      <!-- ============================================================== -->
      <div class="flex-1 flex flex-col lg:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Columna 1: Listado de Grupos Detectados (Ocupa 100% del alto disponible) -->
        <div
          class="w-full lg:w-[410px] xl:w-[450px] bg-white lg:border-r border-slate-200/90 flex flex-col shrink-0 min-h-0 overflow-hidden transition-all"
          :class="vistaMovil === 'grupos' ? 'flex-1 lg:flex-initial flex h-full' : 'hidden lg:flex'"
        >
          <!-- Encabezado de la Columna y Ordenamiento -->
          <div class="px-3 py-2.5 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between shrink-0 gap-2">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-slate-800 shrink-0"></span>
              <span class="font-bold text-xs text-slate-800 truncate">Grupos Identificados</span>
              <span class="font-mono text-[10px] text-slate-600 font-bold bg-white px-1.5 py-0.2 rounded border border-slate-200 shadow-2xs">
                {{ clustersFiltrados.length }}
              </span>
            </div>

            <!-- Selector de Ordenamiento Rápido -->
            <div class="flex items-center gap-1 text-[10px] font-mono shrink-0">
              <span class="text-slate-400">Orden:</span>
              <select
                v-model="criterioOrden"
                class="bg-white border border-slate-200 rounded px-1.5 py-0.5 text-[10.5px] font-semibold text-slate-700 cursor-pointer focus:outline-none"
              >
                <option value="criticos">Críticos primero</option>
                <option value="venta">Mayor Venta MTD</option>
                <option value="distancia">Menor Distancia</option>
                <option value="id">ID # Grupo</option>
              </select>
            </div>
          </div>

          <!-- Listado de Grupos con Scroll -->
          <div class="flex-1 overflow-y-auto custom-scroll p-2 space-y-2">
            <div v-if="cargando" class="text-center py-28 text-xs text-slate-400 animate-pulse font-mono flex flex-col items-center justify-center gap-2">
              <span class="w-6 h-6 border-2 border-slate-600 border-t-transparent rounded-full animate-spin"></span>
              <span>Auditando colisiones territoriales y datos duplicados...</span>
            </div>

            <div v-else-if="clustersFiltrados.length === 0" class="text-center py-28 text-xs text-slate-400 p-4">
              <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600 mb-2">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
              </div>
              <p class="font-bold text-slate-700 mb-1 text-sm">Padrón sin duplicados detectados</p>
              <p class="text-slate-500">No se encontraron conflictos bajo los filtros y tolerancia de radio seleccionados.</p>
            </div>

            <!-- Tarjeta de Grupo Minimalista, Sobria y Sin Redundancia -->
            <div
              v-for="grupo in clustersFiltrados"
              :key="grupo.cluster_id"
              @click="seleccionarGrupo(grupo, true)"
              class="p-3 rounded-xl border-2 transition-all cursor-pointer space-y-2 relative group"
              :class="[
                grupoSeleccionadoId === grupo.cluster_id
                  ? 'bg-slate-50 border-slate-900 ring-2 ring-slate-900/15 shadow-sm border-l-4 border-l-slate-900'
                  : 'bg-white hover:bg-slate-50/70 border-slate-300 shadow-2xs'
              ]"
            >
              <!-- Fila 1: Cabecera con ID, Clasificación y Distancia -->
              <div class="flex items-center justify-between gap-1.5 pb-1 border-b border-slate-200">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="font-mono font-bold text-xs text-slate-900 shrink-0">
                    #{{ grupo.cluster_id }}
                  </span>
                  <span
                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold font-mono uppercase truncate"
                    :class="getClasificacionBadge(grupo.clasificacion)"
                  >
                    {{ getClasificacionTexto(grupo.clasificacion) }}
                  </span>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <span
                    v-if="tienePropuestaPendiente(grupo)"
                    class="px-1.5 py-0.2 bg-amber-50 text-amber-800 border border-amber-300 rounded text-[9px] font-bold font-mono"
                    title="Propuesta pendiente"
                  >
                    Propuesta
                  </span>
                  <span
                    class="font-mono text-[10px] px-1.5 py-0.2 rounded font-bold border"
                    :class="(grupo.distancia_minima_m ?? 999) <= radioMetros ? 'bg-slate-100 text-slate-800 border-slate-300' : 'bg-slate-50 text-slate-500 border-slate-200'"
                  >
                    {{ formatearDistancia(grupo.distancia_minima_m) }}
                  </span>
                </div>
              </div>

              <!-- Fila 2: Clientes (Solo Nombre e Identificación Básica con Bordes Claros) -->
              <div class="space-y-1.5">
                <div
                  v-for="cli in (grupo.clientes || [])"
                  :key="cli.cliente_id"
                  class="p-2 rounded-lg border-2 text-xs flex items-center justify-between gap-1.5 transition-colors"
                  :class="cli.es_mas_antiguo ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-300 bg-rose-50/40'"
                >
                  <!-- Izquierda: Indicador punto + Nombre + Código ID y Ruta -->
                  <div class="min-w-0 flex-1 truncate">
                    <div class="flex items-center gap-1.5">
                      <span
                        class="w-2.5 h-2.5 rounded-full shrink-0"
                        :class="cli.es_mas_antiguo ? 'bg-emerald-600 ring-2 ring-emerald-200' : 'bg-rose-600 ring-2 ring-rose-200'"
                        :title="cli.es_mas_antiguo ? 'Original (Primer ingreso)' : 'Ingreso posterior'"
                      ></span>
                      <span class="font-bold text-slate-900 truncate text-[12.5px]">{{ cli.cliente }}</span>
                    </div>
                    <div class="text-[10px] font-mono text-slate-600 pt-0.5 pl-4 flex items-center gap-1.5">
                      <span>ID: <strong class="text-slate-900">{{ cli.cliente_id }}</strong></span>
                      <span class="text-slate-300">&bull;</span>
                      <span
                        class="px-1.5 py-0.2 rounded font-bold border text-[9.5px] inline-flex items-center gap-1"
                        :class="cli.es_mas_antiguo ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-rose-100 text-rose-900 border-rose-300'"
                      >
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="cli.es_mas_antiguo ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                        <span>Ruta {{ cli.ruta }}</span>
                      </span>
                    </div>
                  </div>

                  <!-- Derecha: Venta MTD y Original/Posterior -->
                  <div class="text-right font-mono text-[10.5px] shrink-0 pl-1">
                    <div class="font-bold text-slate-900">
                      Bs. {{ cli.venta_mtd }}
                    </div>
                    <div
                      class="text-[9px] font-bold uppercase tracking-wider"
                      :class="cli.es_mas_antiguo ? 'text-emerald-700' : 'text-rose-700'"
                    >
                      {{ cli.es_mas_antiguo ? 'Original' : 'Posterior' }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Fila 3: Debajo, Inter-Rutas (Solo si hay rutas distintas en el grupo) -->
              <div v-if="grupo.rutas && grupo.rutas.length > 1" class="pt-1.5 border-t-2 border-slate-100 flex items-center justify-between text-[10px] font-mono">
                <span class="text-slate-500 font-bold uppercase text-[9px] tracking-wider">Inter-Rutas:</span>
                <div class="flex items-center gap-1">
                  <span
                    v-for="cli in (grupo.clientes || [])"
                    :key="'rt-badge-' + cli.cliente_id"
                    class="px-1.5 py-0.2 rounded font-bold border text-[9.5px] flex items-center gap-1 shadow-2xs"
                    :class="cli.es_mas_antiguo ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-rose-100 text-rose-900 border-rose-300'"
                    :title="cli.es_mas_antiguo ? 'Ruta del cliente original' : 'Ruta del cliente posterior'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="cli.es_mas_antiguo ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                    <span>Ruta {{ cli.ruta }}</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Columna 2: Panel de Inspeccion, Comparador y Mapa -->
        <div
          class="flex-1 h-full min-h-0 flex flex-col overflow-y-auto custom-scroll p-3 sm:p-4 space-y-3 bg-slate-50/50"
          :class="(vistaMovil === 'comparador' || vistaMovil === 'mapa' || vistaMovil === 'propuestas') ? 'flex' : 'hidden lg:flex'"
        >
          <!-- 1. Encabezado de la Inspeccion y Control de Modos de Vista -->
          <div class="bg-white rounded-2xl border border-slate-200/90 p-2.5 shadow-2xs flex flex-wrap items-center justify-between gap-2 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
              <button
                type="button"
                @click="setVistaMovil('grupos')"
                class="lg:hidden text-xs font-semibold text-slate-700 hover:text-slate-900 flex items-center gap-1.5 cursor-pointer bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg shrink-0 transition-colors"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                <span>Grupos</span>
              </button>
              
              <div class="flex items-center gap-2 truncate">
                <span class="font-bold text-xs text-slate-900 font-mono truncate">
                  {{ grupoActivo ? `Grupo #${grupoActivo.cluster_id}` : 'Inspección de Conflicto' }}
                </span>
                <span
                  v-if="grupoActivo"
                  class="text-[10px] font-mono font-bold px-2 py-0.5 rounded shrink-0 uppercase"
                  :class="getClasificacionBadge(grupoActivo.clasificacion)"
                >
                  {{ getClasificacionTexto(grupoActivo.clasificacion) }}
                </span>
                <span
                  v-if="grupoActivo"
                  class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 shrink-0"
                >
                  Distancia: {{ formatearDistancia(grupoActivo.distancia_minima_m) }}
                </span>
              </div>
            </div>

            <!-- Triage Rápido: Anterior / Siguiente + Modos de Vista -->
            <div class="flex items-center gap-2 text-xs">
              <!-- Botones Triage Anterior/Siguiente -->
              <div v-if="clustersFiltrados.length > 1" class="inline-flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200 text-xs">
                <button
                  type="button"
                  @click="navegarGrupo(-1)"
                  :disabled="indiceGrupoActivo <= 0"
                  class="px-2 py-0.5 rounded hover:bg-white text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent font-bold cursor-pointer transition-colors"
                  title="Grupo anterior (Atajo: [ o Flecha Izq)"
                >
                  &larr; Ant
                </button>
                <span class="px-1.5 text-[10px] font-mono text-slate-400">
                  {{ indiceGrupoActivo + 1 }}/{{ clustersFiltrados.length }}
                </span>
                <button
                  type="button"
                  @click="navegarGrupo(1)"
                  :disabled="indiceGrupoActivo >= clustersFiltrados.length - 1"
                  class="px-2 py-0.5 rounded hover:bg-white text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent font-bold cursor-pointer transition-colors"
                  title="Siguiente grupo (Atajo: ] o Flecha Der)"
                >
                  Sig &rarr;
                </button>
              </div>

              <!-- Modos de Vista en Desktop: Dividido | Comparador | Mapa -->
              <div class="hidden lg:inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200">
                <button
                  type="button"
                  @click="toggleModoVistaDetalle('split')"
                  class="px-2 py-0.5 text-[10px] font-semibold rounded cursor-pointer transition-all"
                  :class="modoVistaDetalle === 'split' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800'"
                  title="Mostrar mapa territorial y comparador lado a lado"
                >
                  Dividido
                </button>
                <button
                  type="button"
                  @click="toggleModoVistaDetalle('comparador')"
                  class="px-2 py-0.5 text-[10px] font-semibold rounded cursor-pointer transition-all"
                  :class="modoVistaDetalle === 'comparador' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800'"
                  title="Pantalla completa para comparativa detallada"
                >
                  Comparador
                </button>
                <button
                  type="button"
                  @click="toggleModoVistaDetalle('mapa')"
                  class="px-2 py-0.5 text-[10px] font-semibold rounded cursor-pointer transition-all"
                  :class="modoVistaDetalle === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800'"
                  title="Mapa ampliado a pantalla completa"
                >
                  Mapa
                </button>
              </div>

              <!-- Selector Calles / Satelite -->
              <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200">
                <button
                  type="button"
                  @click="cambiarCapaMapa('openStreetMap')"
                  class="px-2 py-0.5 text-[10px] font-medium rounded transition-all cursor-pointer"
                  :class="capaActual === 'openStreetMap' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'"
                >
                  Calles
                </button>
                <button
                  type="button"
                  @click="cambiarCapaMapa('satellite')"
                  class="px-2 py-0.5 text-[10px] font-medium rounded transition-all cursor-pointer"
                  :class="capaActual === 'satellite' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'"
                >
                  Satélite
                </button>
              </div>

              <button
                v-if="grupoActivo"
                type="button"
                @click="enfocarGrupoEnMapa(grupoActivo)"
                class="h-6 px-2.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] font-bold text-slate-700 cursor-pointer shadow-2xs"
                title="Ajustar vista para encuadrar ambos clientes"
              >
                Recentrar
              </button>
            </div>
          </div>

          <!-- 2. Mapa Leaflet (Visible según modo en desktop y en pestaña mapa móvil) -->
          <div
            v-show="modoVistaDetalle === 'split' || modoVistaDetalle === 'mapa' || vistaMovil === 'mapa'"
            class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden flex flex-col shrink-0 transition-all relative"
            :class="[
              modoVistaDetalle === 'mapa' || vistaMovil === 'mapa' ? 'flex-1 min-h-[520px]' : 'h-72 sm:h-80'
            ]"
          >
            <div class="relative w-full h-full bg-slate-950 flex-1">
              <div id="mapaDuplicados" class="absolute inset-0 w-full h-full z-10"></div>
              
              <!-- Placeholder sobre el mapa si no hay grupo seleccionado -->
              <div
                v-if="!grupoActivo"
                class="absolute inset-0 z-20 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center text-white text-xs font-medium p-4 text-center pointer-events-none"
              >
                <span>Selecciona un grupo para inspeccionar el conflicto geográfico</span>
              </div>

              <!-- Leyenda Flotante Minimalista sobre el mapa -->
              <div
                v-if="grupoActivo"
                class="absolute bottom-3 left-3 z-20 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl px-2.5 py-1.5 shadow-md flex items-center gap-3 text-[10px] font-mono pointer-events-none"
              >
                <div class="flex items-center gap-1">
                  <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 border border-white"></span>
                  <span class="font-bold text-slate-800">Master</span>
                </div>
                <div class="flex items-center gap-1">
                  <span class="w-2.5 h-2.5 rounded-full bg-rose-600 border border-white"></span>
                  <span class="font-bold text-slate-800">Duplicado</span>
                </div>
                <div class="text-slate-400 border-l border-slate-200 pl-2">
                  Tolerancia: <strong class="text-slate-700">{{ radioMetros }}m</strong>
                </div>
              </div>
            </div>
          </div>

          <!-- 3. Comparador Lado a Lado (Master vs Duplicado) -->
          <div
            v-show="modoVistaDetalle === 'split' || modoVistaDetalle === 'comparador' || vistaMovil === 'comparador'"
            class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex-1 flex flex-col overflow-hidden transition-all"
          >
            <!-- Placeholder si no hay grupo activo -->
            <div
              v-if="!grupoActivo"
              class="flex-1 flex flex-col items-center justify-center p-8 text-center"
            >
              <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
              </div>
              <h4 class="text-sm font-bold text-slate-800 mb-0.5">Inspección de Conflicto</h4>
              <p class="text-xs text-slate-500 max-w-sm">
                Selecciona un grupo para comparar fechas de creación, facturación acumulada MTD, rutas y decidir la cuenta a conservar.
              </p>
            </div>

            <!-- Fichas Comparativas -->
            <template v-else>
              <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-3">
                <!-- Resumen de Coincidencias en la Cabecera del Comparador -->
                <div class="p-3.5 bg-white rounded-xl border-2 border-slate-300 shadow-xs space-y-2.5">
                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-1.5 font-mono">
                      <span class="w-2 h-2 rounded-full bg-slate-800"></span>
                      <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                        Detalle de lo Repetido (Datos Coincidentes en Conflicto)
                      </span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                      <!-- Botón Desglosar Cambios de Ambos -->
                      <button
                        type="button"
                        @click="desglosarCambiosAmbos"
                        class="px-2.5 py-1 rounded-lg border text-[10.5px] font-mono font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-2xs"
                        :class="sonTodosCambiosExpandidos
                          ? 'bg-slate-200 text-slate-900 border-slate-300'
                          : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200'"
                        title="Desglosar o replegar historial de cambios de ambos clientes a la vez"
                      >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ sonTodosCambiosExpandidos ? 'Replegar Historiales' : 'Desglosar Historial de Ambos' }}</span>
                      </button>

                      <div class="text-[11px] font-mono text-slate-600">
                        Distancia: <strong class="text-slate-900 font-bold">{{ formatearDistancia(grupoActivo.distancia_minima_m) }}</strong>
                      </div>
                    </div>
                  </div>

                  <!-- Chips de coincidencias exactas -->
                  <div class="flex flex-wrap gap-1.5">
                    <div
                      v-for="(coinc, idx) in obtenerCoincidenciasLegibles(grupoActivo)"
                      :key="idx"
                      class="px-2.5 py-1 rounded-lg border-2 font-mono text-xs flex items-center gap-1.5 shadow-2xs font-bold"
                      :class="coinc.clase"
                    >
                      <span class="opacity-70 text-[10px] uppercase">{{ coinc.titulo }}:</span>
                      <span class="tracking-tight select-all text-slate-900">{{ coinc.valor }}</span>
                    </div>
                  </div>

                  <!-- Resumen de similitud y discrepancia operativa -->
                  <div class="flex items-center gap-2 pt-1 border-t border-slate-200/60 text-[10.5px] font-mono flex-wrap">
                    <span
                      v-if="sonRutasDistintas()"
                      class="px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-200 font-bold flex items-center gap-1"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                      <span>Inter-Ruta: {{ grupoActivo.rutas.join(' vs ') }}</span>
                    </span>
                    <span
                      v-else
                      class="px-2 py-0.5 rounded bg-white text-slate-700 border border-slate-200 font-medium flex items-center gap-1"
                    >
                      <span>Misma Ruta: {{ grupoActivo.rutas[0] }}</span>
                    </span>

                    <span
                      v-if="sonDiasDistintos()"
                      class="px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-200 font-bold flex items-center gap-1"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                      <span>Días de Visita Distintos</span>
                    </span>
                    <span
                      v-else
                      class="px-2 py-0.5 rounded bg-white text-slate-700 border border-slate-200 font-medium flex items-center gap-1"
                    >
                      <span>Mismo Día de Visita</span>
                    </span>
                  </div>
                </div>

                <!-- Grid de Clientes (Lado a Lado con Bordes Distintivos y Claros) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                  <div
                    v-for="(cli, idx) in grupoActivo.clientes"
                    :key="cli.cliente_id"
                    class="p-4 rounded-xl border-2 flex flex-col justify-between space-y-3.5 transition-all relative bg-white"
                    :class="[
                      cli.es_master_sugerido
                        ? 'border-emerald-500 ring-2 ring-emerald-500/15 shadow-sm'
                        : 'border-rose-400 ring-2 ring-rose-400/15 shadow-sm',
                      clienteFocoId === cli.cliente_id ? 'ring-3 ring-slate-900' : ''
                    ]"
                  >
                    <!-- Cabecera de la Ficha con Boton para Fijar como Master -->
                    <div class="space-y-1.5 pb-2.5 border-b border-slate-100">
                      <div class="flex items-center justify-between gap-1 flex-wrap">
                        <div class="flex items-center gap-1.5">
                          <span
                            class="px-2 py-0.5 rounded text-[10px] font-mono font-bold tracking-wider uppercase shadow-2xs flex items-center gap-1"
                            :class="cli.es_master_sugerido ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                          >
                            <svg v-if="cli.es_master_sugerido" class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span>{{ cli.es_master_sugerido ? 'Master (A Conservar)' : `Duplicado #${idx + 1}` }}</span>
                          </span>

                          <!-- Botón interactivo para cambiar el Master -->
                          <button
                            v-if="!cli.es_master_sugerido"
                            type="button"
                            @click="designarComoMaster(cli)"
                            class="px-2 py-0.5 rounded bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-[10px] font-bold font-mono cursor-pointer transition-colors shadow-2xs"
                            title="Hacer clic para conservar este cliente en lugar del actual"
                          >
                            Fijar como Master
                          </button>
                        </div>

                        <div class="flex items-center gap-1.5">
                          <span class="font-mono text-xs font-bold text-slate-500">ID: {{ cli.cliente_id }}</span>
                          <button
                            v-if="cli.latitud && cli.longitud"
                            type="button"
                            @click="enfocarClienteEnMapa(cli)"
                            title="Hacer zoom directo en el mapa"
                            class="px-2 py-0.5 rounded bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-mono text-[10px] font-bold flex items-center gap-1 cursor-pointer transition-colors shadow-2xs"
                          >
                            <svg class="w-3 h-3 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                            <span>Zoom</span>
                          </button>
                        </div>
                      </div>

                      <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 flex-wrap min-w-0">
                          <h3 class="text-sm font-bold text-slate-900 leading-snug">{{ cli.cliente }}</h3>
                          <span
                            v-if="cli.cluster_t"
                            class="shrink-0 px-2 py-0.5 rounded-md bg-purple-50 text-purple-900 border border-purple-200 text-[10.5px] font-bold font-mono shadow-2xs"
                            title="Clúster comercial asignado al cliente"
                          >
                            Clúster: {{ cli.cluster_t }}
                          </span>
                          <span
                            v-else
                            class="shrink-0 px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 border border-slate-200 text-[10px] font-mono"
                          >
                            Sin Clúster
                          </span>
                        </div>
                        <span
                          v-if="cli.tiene_cambio_nombre"
                          class="shrink-0 px-2 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300 text-[9.5px] font-bold font-mono flex items-center gap-1"
                          :title="cli.detalle_cambio_nombre"
                        >
                          <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                          Nombre Modificado
                        </span>
                        <span
                          v-else-if="nombreEsIdenticoEnGrupo()"
                          class="shrink-0 px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 border border-slate-200 text-[9px] font-bold font-mono"
                          title="Nombre comercial coincidente en este grupo"
                        >
                          Nombre Idéntico
                        </span>
                      </div>
                      <div v-if="cli.tiene_cambio_nombre && cli.detalle_cambio_nombre" class="px-2.5 py-1.5 rounded-lg bg-amber-50/90 border border-amber-200 text-amber-900 text-[11px] font-sans flex items-start gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <div class="min-w-0">
                          <span class="font-bold text-[10px] uppercase tracking-wide text-amber-800">Alerta de Auditoría:</span>
                          <div class="font-mono text-[10.5px] leading-snug text-amber-950 font-medium">
                            {{ cli.detalle_cambio_nombre }}
                          </div>
                        </div>
                      </div>
                      <p class="text-xs text-slate-500 truncate">{{ cli.direccion || 'Sin dirección registrada' }}</p>
                    </div>

                    <!-- Seccion: Detalle de lo Repetido en la Ficha del Cliente -->
                    <div
                      v-if="obtenerCoincidenciasLegibles(grupoActivo).length > 0"
                      class="p-2.5 rounded-xl border-2 space-y-1.5 font-mono shadow-2xs"
                      :class="cli.es_mas_antiguo ? 'bg-emerald-50/50 border-emerald-300' : 'bg-rose-50/50 border-rose-300'"
                    >
                      <div
                        class="text-[10px] uppercase font-bold tracking-wider flex items-center justify-between"
                        :class="cli.es_mas_antiguo ? 'text-emerald-900' : 'text-rose-900'"
                      >
                        <span class="flex items-center gap-1.5">
                          <span class="w-2 h-2 rounded-full" :class="cli.es_mas_antiguo ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                          <span>Detalle de lo Repetido / Coincidente:</span>
                        </span>
                        <span
                          class="text-[9px] font-sans font-bold px-1.5 py-0.2 rounded bg-white/80 border"
                          :class="cli.es_mas_antiguo ? 'border-emerald-300 text-emerald-800' : 'border-rose-300 text-rose-800'"
                        >
                          {{ cli.es_mas_antiguo ? 'Original' : 'Duplicado' }}
                        </span>
                      </div>
                      <div class="flex flex-wrap gap-1.5">
                        <span
                          v-for="(coinc, idxCo) in obtenerCoincidenciasLegibles(grupoActivo)"
                          :key="idxCo"
                          class="px-2.5 py-1 rounded-lg border-2 font-mono text-xs flex items-center gap-1.5 shadow-2xs font-bold bg-white"
                          :class="cli.es_mas_antiguo ? 'border-emerald-200 text-emerald-950' : 'border-rose-200 text-rose-950'"
                        >
                          <span class="font-normal uppercase text-[9.5px] opacity-75">{{ coinc.titulo }}:</span>
                          <span>{{ coinc.valor }}</span>
                        </span>
                      </div>
                    </div>

                    <!-- Seccion: Historial de Ingreso al Padron -->
                    <div
                      class="p-2.5 rounded-xl border-2 text-xs font-mono space-y-1 shadow-2xs"
                      :class="cli.es_mas_antiguo ? 'bg-emerald-50/40 border-emerald-300' : 'bg-rose-50/40 border-rose-300'"
                    >
                      <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-500 font-sans uppercase font-bold tracking-wider">Antigüedad en Padrón:</span>
                        <span
                          v-if="cli.es_mas_antiguo"
                          class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold text-[9.5px] flex items-center gap-1"
                        >
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                          Padrón Original (Más Antiguo)
                        </span>
                        <span
                          v-else
                          class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-300 font-bold text-[9.5px] flex items-center gap-1"
                        >
                          <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                          Ingreso Posterior (Duplicado)
                        </span>
                      </div>
                      <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5 pt-0.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <span>{{ cli.fecha_ingreso ? formatearFecha(cli.fecha_ingreso) : 'Padrón Inicial (Sin cambios)' }}</span>
                      </div>
                      <div v-if="cli.origen_ingreso" class="text-[10px] text-slate-500 font-sans truncate">
                        Origen: {{ cli.origen_ingreso }}
                      </div>
                    </div>

                    <!-- Seccion: Venta MTD con barra de progreso comparativa (Referencial) -->
                    <div class="p-2.5 rounded-xl bg-slate-50/80 border-2 border-slate-300 shadow-2xs space-y-1.5 font-mono">
                      <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                          <span class="text-slate-500 font-sans">Venta {{ periodoLabel || 'Mes' }}:</span>
                          <span class="text-[9px] px-1 py-0.2 rounded bg-slate-200/80 text-slate-600 font-bold uppercase tracking-wider font-sans">Referencial</span>
                        </div>
                        <span class="font-bold text-sm" :class="cli.venta_mtd > 0 ? 'text-slate-900' : 'text-slate-500'">
                          Bs. {{ cli.venta_mtd }}
                        </span>
                      </div>
                      <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                        <div
                          class="h-full rounded-full transition-all duration-300"
                          :class="cli.es_master_sugerido ? 'bg-slate-900' : 'bg-slate-400'"
                          :style="{ width: calcularPorcentajeVenta(cli.venta_mtd, grupoActivo.venta_mtd_total) + '%' }"
                        ></div>
                      </div>
                    </div>

                    <!-- Seccion: Atributos de Ruteo con Rutas en Verde (Original) y Rojo (Posterior) -->
                    <div class="grid grid-cols-2 gap-1.5 text-[11px] font-mono">
                      <div
                        class="p-2 rounded-xl border-2 transition-colors shadow-2xs"
                        :class="cli.es_mas_antiguo ? 'bg-emerald-50/70 border-emerald-300' : 'bg-rose-50/70 border-rose-300'"
                      >
                        <div class="flex items-center justify-between mb-0.5">
                          <span class="text-[9.5px] font-bold uppercase" :class="cli.es_mas_antiguo ? 'text-emerald-700' : 'text-rose-700'">Ruta:</span>
                          <span
                            class="text-[8.5px] font-bold px-1.5 py-0.2 rounded uppercase border"
                            :class="cli.es_mas_antiguo ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-rose-100 text-rose-800 border-rose-300'"
                          >
                            {{ cli.es_mas_antiguo ? 'Original' : 'Ingresó Después' }}
                          </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                          <span class="w-2 h-2 rounded-full shrink-0" :class="cli.es_mas_antiguo ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                          <span class="font-mono font-bold text-sm" :class="cli.es_mas_antiguo ? 'text-emerald-950' : 'text-rose-950'">{{ cli.ruta }}</span>
                        </div>
                      </div>
                      <div class="p-1.5 rounded-lg bg-white border-2 border-slate-200">
                        <span class="text-slate-400 block text-[9.5px]">Canal:</span>
                        <span class="font-bold text-slate-800">{{ cli.canal }}</span>
                      </div>
                      <div
                        class="p-1.5 rounded-lg border-2 transition-colors"
                        :class="sonDiasDistintos() ? 'bg-amber-50/70 border-amber-300' : 'bg-white border-slate-200'"
                      >
                        <div class="flex items-center justify-between">
                          <span class="text-slate-400 block text-[9.5px]">Día:</span>
                          <span v-if="sonDiasDistintos()" class="text-[8.5px] font-bold text-amber-800 bg-amber-100 px-1 rounded">DISTINTO</span>
                        </div>
                        <span class="font-bold text-slate-800">{{ cli.dia }}</span>
                      </div>
                      <div class="p-1.5 rounded-lg bg-white border-2 border-slate-200">
                        <span class="text-slate-400 block text-[9.5px]">Vendedor:</span>
                        <span class="font-medium text-slate-800 truncate block">{{ cli.vendedor || 'S/N' }}</span>
                      </div>
                    </div>

                    <!-- Documentos & Contacto con Resaltado de Coincidencias -->
                    <div class="p-2.5 rounded-xl bg-white border-2 border-slate-300 text-[10.5px] space-y-1 font-mono shadow-2xs">
                      <div class="flex items-center justify-between truncate">
                        <div class="truncate">
                          <span class="text-slate-400">NIT:</span>
                          <span class="ml-1" :class="cli.nit ? 'font-bold text-slate-800' : 'text-slate-400'">{{ cli.nit || 'Sin NIT' }}</span>
                        </div>
                        <span
                          v-if="cli.nit && campoEsIdenticoEnGrupo('nit')"
                          class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-800 border border-slate-200 font-bold text-[9px] shrink-0"
                        >
                          IDÉNTICO
                        </span>
                        <span
                          v-else-if="cli.nit && documentoCoincideEnGrupo(cli.nit, cli.cliente_id)"
                          class="px-1.5 py-0.2 rounded bg-indigo-50 text-indigo-800 border border-indigo-200 font-bold text-[9px] shrink-0"
                          title="Coincide con CI o NIT de otro cliente en este grupo"
                        >
                          COINCIDENTE
                        </span>
                      </div>
                      <div class="flex items-center justify-between truncate">
                        <div class="truncate">
                          <span class="text-slate-400">CI:</span>
                          <span class="ml-1" :class="cli.ci ? 'font-bold text-slate-800' : 'text-slate-400'">{{ cli.ci || 'Sin CI' }}</span>
                        </div>
                        <span
                          v-if="cli.ci && campoEsIdenticoEnGrupo('ci')"
                          class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-800 border border-slate-200 font-bold text-[9px] shrink-0"
                        >
                          IDÉNTICO
                        </span>
                        <span
                          v-else-if="cli.ci && documentoCoincideEnGrupo(cli.ci, cli.cliente_id)"
                          class="px-1.5 py-0.2 rounded bg-indigo-50 text-indigo-800 border border-indigo-200 font-bold text-[9px] shrink-0"
                          title="Coincide con NIT o CI de otro cliente en este grupo"
                        >
                          COINCIDENTE
                        </span>
                      </div>
                      <div class="flex items-center justify-between truncate">
                        <div class="truncate">
                          <span class="text-slate-400">Tel/Cel:</span>
                          <span class="ml-1" :class="(cli.telefono || cli.celular) ? 'font-bold text-slate-800' : 'text-slate-400'">{{ cli.telefono || cli.celular || 'Sin teléfono' }}</span>
                        </div>
                        <span
                          v-if="telefonoEsIdenticoEnGrupo()"
                          class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-800 border border-slate-200 font-bold text-[9px] shrink-0"
                        >
                          IDÉNTICO
                        </span>
                      </div>
                    </div>

                    <!-- Seccion: Historial de Modificaciones (Lógica Matriz de Cambios) -->
                    <div class="rounded-xl bg-white border-2 border-slate-300 shadow-2xs overflow-hidden">
                      <button
                        type="button"
                        @click="alternarCambiosCliente(cli.cliente_id, cli)"
                        class="w-full px-3 py-2 flex items-center justify-between text-left transition-colors hover:bg-slate-50 cursor-pointer select-none"
                      >
                        <div class="flex items-center gap-2 min-w-0">
                          <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                          <span class="text-xs font-bold text-slate-800">
                            Historial de Modificaciones
                          </span>
                          <span
                            class="px-1.5 py-0.2 rounded-full font-mono text-[10px] font-bold border"
                            :class="(cli.cambios && cli.cambios.length > 0)
                              ? 'bg-sky-50 text-sky-700 border-sky-200'
                              : 'bg-slate-100 text-slate-500 border-slate-200'"
                          >
                            {{ (cli.cambios ? cli.cambios.length : cli.total_cambios) || 0 }} evento(s)
                          </span>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 text-slate-400">
                          <span class="text-[10px] font-mono">
                            {{ cambiosExpandidos.has(cli.cliente_id) ? 'Ocultar' : 'Desglosar' }}
                          </span>
                          <svg
                            class="w-3.5 h-3.5 transition-transform duration-200"
                            :class="cambiosExpandidos.has(cli.cliente_id) ? 'rotate-180 text-sky-600' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                          >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                          </svg>
                        </div>
                      </button>

                      <!-- Detalle Desglosado de Modificaciones -->
                      <div
                        v-if="cambiosExpandidos.has(cli.cliente_id)"
                        class="border-t border-slate-100 p-2.5 bg-slate-50/70 space-y-2"
                      >
                        <div v-if="cargandoCambios.has(cli.cliente_id)" class="py-4 text-center text-xs text-slate-500 font-mono">
                          <span class="inline-block w-4 h-4 border-2 border-sky-500 border-t-transparent rounded-full animate-spin mr-1.5 align-middle"></span>
                          <span>Cargando auditoría de cambios...</span>
                        </div>

                        <div
                          v-else-if="!cli.cambios || cli.cambios.length === 0"
                          class="py-3 px-2 text-center text-[11px] text-slate-500 font-sans bg-white rounded-lg border border-slate-200/80"
                        >
                          Sin modificaciones registradas en auditoría de ruteo para este cliente.
                        </div>

                        <div v-else class="space-y-1.5 max-h-72 overflow-y-auto custom-scroll pr-1">
                          <div class="flex items-center justify-between text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider pb-0.5">
                            <span>Cronología Histórica (#{{ cli.cliente_id }})</span>
                            <span>{{ cli.cambios.length }} evento(s)</span>
                          </div>

                          <div
                            v-for="ev in cli.cambios"
                            :key="ev.id"
                            class="p-2 bg-white rounded-lg border border-slate-200/90 shadow-2xs space-y-1 text-xs hover:bg-slate-50/80 transition-colors"
                          >
                            <!-- Encabezado del Evento -->
                            <div class="flex items-center justify-between gap-1.5 flex-wrap">
                              <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                                <span
                                  class="px-1.5 py-0.2 rounded text-[9.5px] font-mono font-bold border"
                                  :class="obtenerBadgeEventoClase(ev.tipo_evento, ev.campo_afectado)"
                                >
                                  {{ formatearTipoEvento(ev.tipo_evento, ev.campo_afectado) }}
                                </span>

                                <span class="font-bold text-slate-900 text-xs">
                                  <code class="font-mono text-sky-700 bg-sky-50 px-1 py-0.2 rounded border border-sky-100 text-[10.5px]">
                                    {{ ev.campo_afectado }}
                                  </code>
                                </span>

                                <span class="text-[10px] text-slate-500 font-mono">
                                  &bull; {{ ev.fecha_auditoria_fmt || ev.fecha_auditoria }}
                                </span>

                                <span v-if="ev.archivo_origen" class="text-[9.5px] text-slate-400 font-sans truncate max-w-[130px]" :title="ev.archivo_origen">
                                  ({{ ev.archivo_origen }})
                                </span>
                              </div>

                              <!-- Estado de Auditoría -->
                              <span
                                class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold border shrink-0"
                                :class="ev.estado_auditoria === 'APROBADO'
                                  ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                  : (ev.estado_auditoria === 'RECHAZADO'
                                    ? 'bg-rose-50 text-rose-800 border-rose-200'
                                    : (ev.estado_auditoria === 'PENDIENTE'
                                      ? 'bg-amber-50 text-amber-800 border-amber-200'
                                      : 'bg-slate-100 text-slate-700 border-slate-200'))"
                              >
                                {{ ev.estado_auditoria }}
                              </span>
                            </div>

                            <!-- Transición Antes -> Después en texto completo con font-mono -->
                            <div class="flex items-center gap-1.5 font-mono text-[11px] flex-wrap pt-0.5">
                              <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 line-through opacity-85 break-words max-w-full">
                                {{ ev.valor_anterior || '-' }}
                              </span>

                              <svg class="w-3 h-3 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                              </svg>

                              <span class="px-2 py-0.5 rounded bg-sky-50 text-sky-800 font-bold border border-sky-200 break-words max-w-full">
                                {{ ev.valor_nuevo || '-' }}
                              </span>

                              <!-- Visor interactivo para cambios de coordenadas -->
                              <AuditoriaCoordenadasMapa
                                v-if="esCambioCoordenadas(ev)"
                                :id="ev.id"
                                :valor-anterior="ev.valor_anterior"
                                :valor-nuevo="ev.valor_nuevo"
                                :cliente="cli.cliente"
                                :cliente-id="cli.cliente_id"
                              />
                            </div>

                            <!-- Observaciones y Auditor si existen -->
                            <div v-if="ev.observaciones || ev.auditado_por" class="text-[9.5px] text-slate-500 italic pt-0.5 border-t border-slate-100">
                              <span v-if="ev.auditado_por">Por {{ ev.auditado_por }}</span>
                              <span v-if="ev.fecha_resolucion"> el {{ ev.fecha_resolucion }}</span>
                              <span v-if="ev.observaciones"> &mdash; "{{ ev.observaciones }}"</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Boton Ver Fotografias -->
                    <button
                      type="button"
                      @click="abrirModalFotos(cli)"
                      class="w-full py-1.5 bg-white hover:bg-slate-100 text-slate-800 border border-slate-200 rounded-lg text-xs font-semibold cursor-pointer shadow-2xs transition-all flex items-center justify-center gap-1.5"
                    >
                      <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                      </svg>
                      <span>Ver Fotografías de Visitas</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Barra Inferior de Resolucion Directa -->
              <div class="p-3 border-t border-slate-100 bg-slate-50/90 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 shrink-0 rounded-b-2xl">
                <div class="text-xs text-slate-600 font-mono">
                  Conservar: <strong class="text-slate-900 font-bold">#{{ getMasterCliente(grupoActivo)?.cliente_id }}</strong> ({{ getMasterCliente(grupoActivo)?.cliente }})
                  &bull; Inactivar: <strong class="text-slate-600">#{{ getDuplicadoCliente(grupoActivo)?.cliente_id }}</strong>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                  <button
                    type="button"
                    @click="abrirModalConfirmacion('INACTIVAR')"
                    :disabled="procesandoPropuesta"
                    class="flex-1 sm:flex-none px-4 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50 flex items-center justify-center gap-1.5"
                  >
                    <span>Conservar Master e Inactivar Duplicado</span>
                  </button>

                  <button
                    type="button"
                    @click="abrirModalConfirmacion('FUSIONAR')"
                    :disabled="procesandoPropuesta"
                    class="flex-1 sm:flex-none px-3.5 py-2 bg-white hover:bg-slate-100 text-slate-800 border border-slate-300 rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer disabled:opacity-50 flex items-center justify-center gap-1.5"
                  >
                    <span>Fusionar Cuentas</span>
                  </button>
                </div>
              </div>
            </template>
          </div>

          <!-- ============================================================== -->
          <!-- BANDEJA INFERIOR: PROPUESTAS PENDIENTES DE RESOLUCION          -->
          <!-- ============================================================== -->
          <div
            class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden mt-1"
            :class="vistaMovil === 'propuestas' ? 'block' : 'hidden lg:block'"
          >
            <div
              class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70 cursor-pointer select-none transition-colors hover:bg-slate-100/70"
              @click="mostrarPanelPropuestas = !mostrarPanelPropuestas"
            >
              <div class="flex items-center gap-2">
                <span class="font-bold text-xs text-slate-800 uppercase tracking-wider font-mono">
                  Bandeja de Propuestas Pendientes
                </span>
                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                  {{ propuestasPendientes.length }} pendientes
                </span>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-medium">
                  {{ mostrarPanelPropuestas ? 'Ocultar tabla' : 'Ver tabla de propuestas' }}
                </span>
                <svg
                  class="w-4 h-4 text-slate-400 transition-transform duration-200"
                  :class="mostrarPanelPropuestas ? 'rotate-180' : ''"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>

            <div v-show="mostrarPanelPropuestas || vistaMovil === 'propuestas'" class="overflow-x-auto max-h-72 custom-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="sticky top-0 bg-slate-100 text-slate-700 uppercase text-[9.5px] font-mono font-bold tracking-wider border-b border-slate-200 z-10 shadow-2xs">
                  <tr>
                    <th class="py-2.5 px-3 whitespace-nowrap">Cliente Origen</th>
                    <th class="py-2.5 px-3 whitespace-nowrap">ID Origen</th>
                    <th class="py-2.5 px-3 whitespace-nowrap">Cliente Vinculado (Master)</th>
                    <th class="py-2.5 px-3 whitespace-nowrap">Ruta</th>
                    <th class="py-2.5 px-3 whitespace-nowrap">Acción Propuesta</th>
                    <th class="py-2.5 px-3 whitespace-nowrap">Observaciones</th>
                    <th class="py-2.5 px-3 text-center whitespace-nowrap">Estado</th>
                    <th class="py-2.5 px-3 text-right whitespace-nowrap">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-if="propuestasPendientes.length === 0">
                    <td colspan="8" class="py-8 text-center text-slate-400 font-medium text-xs">
                      No hay propuestas pendientes de resolución de duplicados.
                    </td>
                  </tr>
                  <tr
                    v-for="p in propuestasPendientes"
                    :key="p.id"
                    class="hover:bg-slate-50/80 transition-colors"
                  >
                    <td class="py-2 px-3 font-bold text-slate-900">{{ p.cliente_nombre }}</td>
                    <td class="py-2 px-3 font-mono text-slate-600">{{ p.cliente_id_origen }}</td>
                    <td class="py-2 px-3 font-mono text-emerald-700 font-bold">{{ p.cliente_id_vinculado ? `#${p.cliente_id_vinculado}` : '-' }}</td>
                    <td class="py-2 px-3 font-mono">{{ p.ruta_actual }}</td>
                    <td class="py-2 px-3 font-mono">
                      <span
                        class="px-2 py-0.5 rounded text-[10px] font-bold border"
                        :class="p.accion_propuesta === 'INACTIVAR_DUPLICADO' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-sky-50 text-sky-800 border-sky-200'"
                      >
                        {{ p.accion_propuesta }}
                      </span>
                    </td>
                    <td class="py-2 px-3 text-slate-500 truncate max-w-xs">{{ p.observaciones }}</td>
                    <td class="py-2 px-3 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-50 text-amber-800 border border-amber-200">
                        {{ p.estado }}
                      </span>
                    </td>
                    <td class="py-2 px-3 text-right space-x-1 whitespace-nowrap">
                      <button
                        type="button"
                        @click="cambiarEstadoPropuesta(p.id, 'APLICADO')"
                        :disabled="filaEnProceso === p.id"
                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer disabled:opacity-50"
                      >
                        Aprobar
                      </button>
                      <button
                        type="button"
                        @click="cambiarEstadoPropuesta(p.id, 'DESCARTADO')"
                        :disabled="filaEnProceso === p.id"
                        class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer disabled:opacity-50"
                      >
                        Descartar
                      </button>
                      <button
                        type="button"
                        @click="revertirPropuesta(p.id)"
                        :disabled="filaEnProceso === p.id"
                        class="px-2 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded text-[10px] font-bold cursor-pointer disabled:opacity-50"
                      >
                        Revertir
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- ============================================================== -->
      <!-- MODAL DE CONFIRMACION DE PROPUESTA (ASISTENCIA SEGURA)         -->
      <!-- ============================================================== -->
      <Teleport to="body">
        <div
          v-if="modalConfirmacionVisible"
          class="fixed inset-0 z-[99999] bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5"
          @click.self="modalConfirmacionVisible = false"
        >
          <div class="bg-white rounded-2xl w-full max-w-lg p-5 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" :class="tipoAccionModal === 'INACTIVAR' ? 'bg-slate-900' : 'bg-sky-600'"></span>
                <h3 class="font-bold text-sm text-slate-900 font-mono">
                  {{ tipoAccionModal === 'INACTIVAR' ? 'Confirmar Propuesta de Inactivación' : 'Confirmar Fusión de Cuentas' }}
                </h3>
              </div>
              <button
                type="button"
                @click="modalConfirmacionVisible = false"
                class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 cursor-pointer"
                title="Cerrar modal"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Resumen Visual de la Operacion -->
            <div class="space-y-2 text-xs">
              <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 space-y-1">
                <div class="flex items-center justify-between text-[10px] text-emerald-800 font-bold uppercase font-mono">
                  <span>Se conservará como activo (Master):</span>
                  <span>ID #{{ clienteMasterModal?.cliente_id }}</span>
                </div>
                <div class="font-bold text-slate-900">{{ clienteMasterModal?.cliente }}</div>
                <div class="text-[11px] text-slate-600 font-mono">
                  Ruta: {{ clienteMasterModal?.ruta }} &bull; Canal: {{ clienteMasterModal?.canal }} &bull; Ventas {{ periodoLabel || 'Mes' }}: Bs. {{ clienteMasterModal?.venta_mtd }}
                </div>
              </div>

              <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 space-y-1">
                <div class="flex items-center justify-between text-[10px] text-rose-800 font-bold uppercase font-mono">
                  <span>{{ tipoAccionModal === 'INACTIVAR' ? 'Se propondrá inactivar (Duplicado):' : 'Se fusionará hacia el master:' }}</span>
                  <span>ID #{{ clienteDuplicadoModal?.cliente_id }}</span>
                </div>
                <div class="font-bold text-slate-900">{{ clienteDuplicadoModal?.cliente }}</div>
                <div class="text-[11px] text-slate-600 font-mono">
                  Ruta: {{ clienteDuplicadoModal?.ruta }} &bull; Canal: {{ clienteDuplicadoModal?.canal }} &bull; Ventas {{ periodoLabel || 'Mes' }}: Bs. {{ clienteDuplicadoModal?.venta_mtd }}
                </div>
              </div>
            </div>

            <!-- Observacion Personalizable -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-slate-700">Nota / Justificación para Auditoría:</label>
              <textarea
                v-model="observacionModal"
                rows="2"
                class="w-full p-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:bg-white focus:outline-none focus:ring-1 focus:ring-sky-500"
                placeholder="Escribe una observación opcional..."
              ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                @click="modalConfirmacionVisible = false"
                class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs cursor-pointer transition-colors"
              >
                Cancelar
              </button>
              <button
                type="button"
                @click="confirmarYEnviarPropuesta"
                :disabled="procesandoPropuesta"
                class="px-4 py-2 text-white font-bold rounded-xl text-xs shadow-md cursor-pointer disabled:opacity-50 flex items-center gap-1.5 transition-all"
                :class="tipoAccionModal === 'INACTIVAR' ? 'bg-slate-900 hover:bg-black' : 'bg-sky-600 hover:bg-sky-500'"
              >
                <span v-if="procesandoPropuesta" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>Confirmar y Enviar Propuesta</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ============================================================== -->
      <!-- TOAST FEEDBACK FLOTANTE                                        -->
      <!-- ============================================================== -->
      <Teleport to="body">
        <div
          v-if="toast.visible"
          class="fixed bottom-5 right-5 z-[99999] px-4 py-2.5 rounded-xl shadow-xl border text-xs font-medium flex items-center gap-2 transition-all duration-300"
          :class="[
            toast.tipo === 'success' ? 'bg-emerald-900 text-white border-emerald-700' : '',
            toast.tipo === 'error' ? 'bg-rose-900 text-white border-rose-700' : '',
            toast.tipo === 'info' ? 'bg-slate-900 text-white border-slate-700' : '',
          ]"
        >
          <svg v-if="toast.tipo === 'success'" class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
          <svg v-else-if="toast.tipo === 'error'" class="w-4 h-4 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>{{ toast.mensaje }}</span>
        </div>
      </Teleport>

      <!-- ============================================================== -->
      <!-- DRAWER / MODAL DE FILTROS EN MOVIL                             -->
      <!-- ============================================================== -->
      <div
        v-if="drawerFiltrosMovil"
        class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4"
        @click.self="drawerFiltrosMovil = false"
      >
        <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full max-w-sm p-4 space-y-3.5 shadow-2xl max-h-[85vh] overflow-y-auto">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <span class="font-bold text-sm text-slate-900 font-mono">Filtros de Auditoría</span>
            <button
              type="button"
              @click="drawerFiltrosMovil = false"
              class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 cursor-pointer"
            >
              &times;
            </button>
          </div>

          <!-- Canales -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-700 uppercase font-mono text-[10.5px]">Canales Comerciales</span>
              <div class="flex items-center gap-1.5 text-[10px]">
                <button type="button" @click="seleccionarSoloTDB" class="text-purple-600 font-bold hover:underline">Solo TDB</button>
                <span class="text-slate-300">|</span>
                <button type="button" @click="seleccionarTodosCanales" class="text-sky-600 font-bold hover:underline">Todos</button>
                <span class="text-slate-300">|</span>
                <button type="button" @click="limpiarCanales" class="text-slate-500 hover:underline">Limpiar</button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-1 max-h-36 overflow-y-auto custom-scroll p-1 bg-slate-50 rounded-xl border border-slate-200">
              <label
                v-for="c in canalesDisponibles"
                :key="c"
                class="flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-xs cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="c"
                  v-model="canalesSeleccionados"
                  class="rounded border-slate-300 text-sky-600 focus:ring-0"
                />
                <span class="text-slate-800 font-medium truncate">{{ c }}</span>
              </label>
            </div>
          </div>

          <!-- Rutas -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-700 uppercase font-mono text-[10.5px]">Rutas</span>
              <div class="flex items-center gap-1.5 text-[10px]">
                <button type="button" @click="seleccionarTodasRutas" class="text-sky-600 font-bold hover:underline">Todas</button>
                <span class="text-slate-300">|</span>
                <button type="button" @click="limpiarRutas" class="text-slate-500 hover:underline">Limpiar</button>
              </div>
            </div>
            <div class="max-h-32 overflow-y-auto custom-scroll p-1 bg-slate-50 rounded-xl border border-slate-200 space-y-0.5">
              <label
                v-for="r in rutasDisponibles"
                :key="r.ruta"
                class="flex items-center justify-between px-2 py-1 rounded hover:bg-white text-xs cursor-pointer"
              >
                <div class="flex items-center gap-1.5 min-w-0">
                  <input
                    type="checkbox"
                    :value="r.ruta"
                    v-model="rutasSeleccionadas"
                    class="rounded border-slate-300 text-sky-600 focus:ring-0"
                  />
                  <span class="text-slate-800 font-mono font-bold">{{ r.ruta }}</span>
                </div>
                <span class="text-[9.5px] font-mono text-slate-400">{{ r.canal }}</span>
              </label>
            </div>
          </div>

          <!-- Radio en Metros -->
          <div class="space-y-1.5">
            <span class="font-bold text-slate-700 uppercase font-mono text-[10.5px]">Radio de Tolerancia</span>
            <div class="grid grid-cols-5 gap-1.5 text-center text-xs font-mono font-bold">
              <button
                v-for="r in [5, 10, 20, 50, 100]"
                :key="r"
                type="button"
                @click="radioMetros = r"
                class="py-1.5 rounded-lg border transition-all cursor-pointer"
                :class="radioMetros === r ? 'bg-slate-900 text-white border-slate-900 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200'"
              >
                {{ r }}m
              </button>
            </div>
          </div>

          <!-- Mes de Ventas (Referencial) en Movil -->
          <div v-if="mesesDisponibles.length > 0" class="space-y-1.5">
            <span class="font-bold text-slate-700 uppercase font-mono text-[10.5px]">Mes de Ventas (Referencial)</span>
            <select
              v-model="periodoSeleccionado"
              class="w-full h-9 px-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold text-slate-800 focus:outline-none"
            >
              <option v-for="m in mesesDisponibles" :key="m.periodo" :value="m.periodo">
                {{ m.label }}
              </option>
            </select>
          </div>

          <!-- Boton Aplicar -->
          <div class="pt-2">
            <button
              type="button"
              @click="drawerFiltrosMovil = false; ejecutarAuditoriaDuplicados()"
              class="w-full py-2.5 bg-slate-900 hover:bg-black text-white font-bold rounded-xl text-xs cursor-pointer shadow-md transition-all"
            >
              Aplicar Filtros y Auditar
            </button>
          </div>
        </div>
      </div>

      <!-- Modal de Fotos de Visitas -->
      <ClienteFotosModal
        :visible="modalFotosVisible"
        :cliente-id="clienteSeleccionadoFotos?.cliente_id"
        :cliente-nombre="clienteSeleccionadoFotos?.cliente || clienteSeleccionadoFotos?.nombre"
        :ruta="clienteSeleccionadoFotos?.ruta"
        @close="modalFotosVisible = false"
      />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { mapsConfig } from '@/Config/maps'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'
import AuditoriaCoordenadasMapa from '@/Pages/Supervisor/Auditoria/Components/AuditoriaCoordenadasMapa.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const cargando = ref(false)
const procesandoPropuesta = ref(false)
const filaEnProceso = ref(null)
const vistaMovil = ref('grupos') // 'grupos' | 'comparador' | 'mapa' | 'propuestas'
const modoVistaDetalle = ref('split') // 'split' | 'comparador' | 'mapa'
const mostrarPanelPropuestas = ref(false)
const drawerFiltrosMovil = ref(false)

// Selector de Canales (Multiseleccion)
const canalDropdownRef = ref(null)
const menuCanalAbierto = ref(false)
const canalesDisponibles = computed(() => props.catalogo.canales || [])
const canalesSeleccionados = ref(
  props.catalogo.canales?.includes('TDB') ? ['TDB'] : [...(props.catalogo.canales || [])]
)

// Selector de Rutas (Multiseleccion con Buscador)
const rutaDropdownRef = ref(null)
const menuRutaAbierto = ref(false)
const filtroTextoRuta = ref('')
const rutasSeleccionadas = ref([])

const rutasDisponibles = computed(() => {
  const todas = props.catalogo.rutas || []
  if (canalesSeleccionados.value.length === 0) return todas
  return todas.filter(r => canalesSeleccionados.value.includes(r.canal))
})

const rutasFiltradasEnDropdown = computed(() => {
  if (!filtroTextoRuta.value.trim()) return rutasDisponibles.value
  const q = filtroTextoRuta.value.toLowerCase().trim()
  return rutasDisponibles.value.filter(r =>
    (r.ruta && r.ruta.toLowerCase().includes(q)) ||
    (r.canal && r.canal.toLowerCase().includes(q))
  )
})

// Sincronizar rutas seleccionadas cuando cambian los canales activos
watch(canalesSeleccionados, () => {
  if (rutasSeleccionadas.value.length > 0) {
    const rutasValidas = new Set(rutasDisponibles.value.map(r => r.ruta))
    rutasSeleccionadas.value = rutasSeleccionadas.value.filter(r => rutasValidas.has(r))
  }
}, { deep: true })

// Selector de Modo: 'TODOS' | 'CRITICOS' | 'DATOS' | 'UBICACION'
const filtroModo = ref('TODOS')

// Selector de Radio en Metros
const radioMetros = ref(20)

// Filtro de Periodo de Ventas (Referencial)
const periodoSeleccionado = ref(null)
const periodoLabel = ref('')
const mesesDisponibles = ref([])

// Buscador de texto en memoria
const busquedaTexto = ref('')

// Ordenamiento de Clusters
const criterioOrden = ref('criticos') // 'criticos' | 'venta' | 'distancia' | 'id'

// Datos
const clusters = ref([])
const propuestasPendientes = ref([])
const grupoSeleccionadoId = ref(null)
const clienteFocoId = ref(null)

// Historial de Modificaciones (Lógica Matriz de Cambios)
const cambiosExpandidos = ref(new Set())
const cargandoCambios = ref(new Set())

// Modal de Confirmacion
const modalConfirmacionVisible = ref(false)
const tipoAccionModal = ref('INACTIVAR') // 'INACTIVAR' | 'FUSIONAR'
const clienteMasterModal = ref(null)
const clienteDuplicadoModal = ref(null)
const observacionModal = ref('')

// Toast Notification
const toast = ref({
  visible: false,
  tipo: 'success',
  mensaje: '',
  timer: null,
})

function mostrarToast(mensaje, tipo = 'success') {
  if (toast.value.timer) clearTimeout(toast.value.timer)
  toast.value = {
    visible: true,
    tipo,
    mensaje,
    timer: setTimeout(() => {
      toast.value.visible = false
    }, 3500),
  }
}

// Modal Fotos
const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

// Mapa Leaflet
const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap')
let map = null
let currentTileLayer = null
let collisionLayerGroup = null

// Etiquetas para botones de dropdown
const etiquetaCanales = computed(() => {
  const total = canalesDisponibles.value.length
  const sel = canalesSeleccionados.value.length
  if (sel === 0) return 'Ninguno'
  if (sel === total) return 'Todos'
  if (sel === 1) return canalesSeleccionados.value[0]
  return `${sel} canales`
})

const etiquetaRutas = computed(() => {
  const sel = rutasSeleccionadas.value.length
  if (sel === 0) return 'Todas'
  if (sel === 1) return rutasSeleccionadas.value[0]
  return `${sel} rutas`
})

function seleccionarSoloTDB() {
  if (canalesDisponibles.value.includes('TDB')) {
    canalesSeleccionados.value = ['TDB']
  }
}

function seleccionarTodosCanales() {
  canalesSeleccionados.value = [...canalesDisponibles.value]
}

function limpiarCanales() {
  canalesSeleccionados.value = []
}

function seleccionarTodasRutas() {
  rutasSeleccionadas.value = rutasDisponibles.value.map(r => r.ruta)
}

function limpiarRutas() {
  rutasSeleccionadas.value = []
}

function cambiarModo(modo) {
  filtroModo.value = modo
  ejecutarAuditoriaDuplicados()
}

function toggleModoVistaDetalle(modo) {
  modoVistaDetalle.value = modo
  nextTick(() => {
    if (map) {
      map.invalidateSize()
      if (grupoActivo.value) {
        enfocarGrupoEnMapa(grupoActivo.value)
      }
    }
  })
}

function setVistaMovil(v) {
  vistaMovil.value = v
  if (v === 'comparador' || v === 'mapa') {
    nextTick(() => {
      if (map) {
        map.invalidateSize()
        if (grupoActivo.value) enfocarGrupoEnMapa(grupoActivo.value)
      } else {
        initMap()
        if (grupoActivo.value) enfocarGrupoEnMapa(grupoActivo.value)
      }
    })
  }
}

// Conteo dinamico segun la auditoria
const totalGruposTodos = computed(() => clusters.value.length)
const conteoMismaUbicacionYDatos = computed(() => clusters.value.filter(c => c.clasificacion === 'MISMA_UBICACION_Y_DATOS').length)
const conteoMismosDatos = computed(() => clusters.value.filter(c => c.tiene_mismos_datos).length)
const conteoMismaUbicacion = computed(() => clusters.value.filter(c => c.tiene_misma_ubicacion).length)

const totalClientesImplicados = computed(() => {
  return clustersFiltrados.value.reduce((acc, c) => acc + (c.cantidad_clientes || c.clientes?.length || 0), 0)
})

const ventaMtdTotalAuditada = computed(() => {
  const total = clustersFiltrados.value.reduce((acc, c) => acc + (parseFloat(c.venta_mtd_total) || 0), 0)
  return total.toFixed(2)
})

// Filtrado y Ordenamiento de clusters en memoria
const clustersFiltrados = computed(() => {
  let list = [...clusters.value]

  // Filtrado de Modo
  if (filtroModo.value === 'CRITICOS') {
    list = list.filter(c => c.clasificacion === 'MISMA_UBICACION_Y_DATOS')
  } else if (filtroModo.value === 'DATOS') {
    list = list.filter(c => c.tiene_mismos_datos)
  } else if (filtroModo.value === 'UBICACION') {
    list = list.filter(c => c.tiene_misma_ubicacion)
  }

  // Filtrado por texto de busqueda
  if (busquedaTexto.value.trim()) {
    const q = busquedaTexto.value.toLowerCase().trim()
    list = list.filter(g => {
      return g.clientes.some(c =>
        (c.cliente && c.cliente.toLowerCase().includes(q)) ||
        (c.cliente_id && String(c.cliente_id).includes(q)) ||
        (c.nit && String(c.nit).includes(q)) ||
        (c.ci && String(c.ci).includes(q)) ||
        (c.ruta && c.ruta.toLowerCase().includes(q)) ||
        (c.direccion && c.direccion.toLowerCase().includes(q))
      )
    })
  }

  // Ordenamiento
  list.sort((a, b) => {
    if (criterioOrden.value === 'criticos') {
      const peso = { 'MISMA_UBICACION_Y_DATOS': 1, 'MISMOS_DATOS': 2, 'MISMA_UBICACION': 3 }
      const diff = (peso[a.clasificacion] || 9) - (peso[b.clasificacion] || 9)
      if (diff !== 0) return diff
      return (parseFloat(b.venta_mtd_total) || 0) - (parseFloat(a.venta_mtd_total) || 0)
    }
    if (criterioOrden.value === 'venta') {
      return (parseFloat(b.venta_mtd_total) || 0) - (parseFloat(a.venta_mtd_total) || 0)
    }
    if (criterioOrden.value === 'distancia') {
      return (a.distancia_minima_m ?? 999999) - (b.distancia_minima_m ?? 999999)
    }
    return a.cluster_id - b.cluster_id
  })

  return list
})

const grupoActivo = computed(() => {
  if (!grupoSeleccionadoId.value) return null
  return clusters.value.find(c => c.cluster_id === grupoSeleccionadoId.value) || null
})

const indiceGrupoActivo = computed(() => {
  if (!grupoActivo.value) return -1
  return clustersFiltrados.value.findIndex(c => c.cluster_id === grupoActivo.value.cluster_id)
})

function navegarGrupo(direccion) {
  const idx = indiceGrupoActivo.value
  if (idx === -1) return
  const nuevoIdx = idx + direccion
  if (nuevoIdx >= 0 && nuevoIdx < clustersFiltrados.value.length) {
    seleccionarGrupo(clustersFiltrados.value[nuevoIdx], false)
  }
}

function seleccionarGrupo(grupo, desdeClickUsuario = false) {
  if (!grupo) return
  grupoSeleccionadoId.value = grupo.cluster_id
  clienteFocoId.value = null
  cambiosExpandidos.value = new Set()

  // Calcular quien es el mas antiguo en el grupo para badge visual
  marcarAntiguedadGrupo(grupo)

  // En movil cambiar automaticamente a la vista de comparador SOLO por accion directa del usuario
  if (desdeClickUsuario && typeof window !== 'undefined' && window.innerWidth < 1024) {
    vistaMovil.value = 'comparador'
  }

  nextTick(() => {
    if (!map) {
      initMap()
    }
    if (map) {
      map.invalidateSize()
      enfocarGrupoEnMapa(grupo)
    }
  })
}

function formatearMonto(val) {
  if (val === null || val === undefined || val === '') return '0.00'
  const num = typeof val === 'number' ? val : parseFloat(String(val))
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

function marcarAntiguedadGrupo(grupo) {
  if (!grupo || !grupo.clientes || grupo.clientes.length === 0) return

  // Si el backend ya determinó es_mas_antiguo con las reglas de auditoría y al menos uno está marcado, respetarlo
  const tieneCalculoBackend = grupo.clientes.some(c => c.es_mas_antiguo === true)
  if (tieneCalculoBackend) {
    return
  }

  // Regla de Negocio para determinar el original vs duplicado posterior / incorrecto:
  // 1. Clientes de Padrón Inicial (!c.fecha_ingreso) son los más antiguos (Tier 1).
  //    Entre varios en Padrón Inicial, desempata el menor cliente_id.
  // 2. Si todos tienen fecha_ingreso (altas o renombres), gana la fecha más antigua (menor timestamp).
  //    Si hay empate en fecha, desempata el menor cliente_id.
  let menorIdInicial = null
  let tieneInicial = false

  grupo.clientes.forEach(c => {
    const cid = Number(c.cliente_id) || c.cliente_id
    if (!c.fecha_ingreso) {
      tieneInicial = true
      if (menorIdInicial === null || cid < menorIdInicial) {
        menorIdInicial = cid
      }
    }
  })

  let targetId = null
  if (tieneInicial && menorIdInicial !== null) {
    targetId = menorIdInicial
  } else {
    let minTimestamp = null
    let minCid = null
    grupo.clientes.forEach(c => {
      const cid = Number(c.cliente_id) || c.cliente_id
      const f = c.fecha_ingreso ? new Date(c.fecha_ingreso).getTime() : 0
      if (minTimestamp === null || f < minTimestamp || (f === minTimestamp && cid < minCid)) {
        minTimestamp = f
        minCid = cid
        targetId = c.cliente_id
      }
    })
  }

  grupo.clientes.forEach(c => {
    c.es_mas_antiguo = (c.cliente_id === targetId)
  })
}

function designarComoMaster(clienteElegido) {
  if (!grupoActivo.value || !grupoActivo.value.clientes) return

  grupoActivo.value.clientes.forEach(c => {
    c.es_master_sugerido = (c.cliente_id === clienteElegido.cliente_id)
  })

  // Re-enfocar mapa para reflejar nuevo color del master
  enfocarGrupoEnMapa(grupoActivo.value)
  mostrarToast(`Cliente #${clienteElegido.cliente_id} fijado como Master a conservar`, 'info')
}

function getMasterCliente(grupo) {
  if (!grupo || !grupo.clientes) return null
  return grupo.clientes.find(c => c.es_master_sugerido) || grupo.clientes[0]
}

function getDuplicadoCliente(grupo) {
  if (!grupo || !grupo.clientes) return null
  return grupo.clientes.find(c => !c.es_master_sugerido) || grupo.clientes[1] || null
}

function calcularPorcentajeVenta(venta, total) {
  if (!total || total <= 0) return 0
  return Math.min(100, Math.round((venta / total) * 100))
}

function getClasificacionBadge(clasif) {
  if (clasif === 'MISMA_UBICACION_Y_DATOS') return 'bg-rose-50 text-rose-800 border border-rose-200/80'
  if (clasif === 'MISMOS_DATOS') return 'bg-amber-50 text-amber-800 border border-amber-200/80'
  return 'bg-sky-50 text-sky-800 border border-sky-200/80'
}

function getClasificacionTexto(clasif) {
  if (clasif === 'MISMA_UBICACION_Y_DATOS') return 'Crítico: Ubic & Datos'
  if (clasif === 'MISMOS_DATOS') return 'Mismos Datos'
  return 'Misma Ubicación'
}

function obtenerResumenCoincidencias(grupo) {
  const matches = obtenerCoincidenciasLegibles(grupo)
  if (!matches || matches.length === 0) return { principal: null, total: 0 }
  return {
    principal: matches[0],
    total: matches.length,
  }
}

function formatearDistancia(metros) {
  if (metros === undefined || metros === null) return '-'
  if (metros < 1000) return `${Math.round(metros)} m`
  return `${(metros / 1000).toFixed(2)} km`
}

function formatearFecha(f) {
  if (!f) return '-'
  try {
    const d = new Date(f)
    if (isNaN(d.getTime())) return String(f).substring(0, 10)
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return String(f)
  }
}

function tienePropuestaPendiente(grupo) {
  return grupo.clientes?.some(c => c.tiene_propuesta_pendiente)
}

function obtenerCoincidenciasLegibles(grupo) {
  if (!grupo) return []
  const res = []

  // 1. Ubicacion fisica si aplica
  if (grupo.tiene_misma_ubicacion) {
    const d = grupo.distancia_minima_m
    const textoDist = d <= 0.5 ? 'Misma coordenada GPS (0 m)' : `Misma ubicación física a ${formatearDistancia(d)}`
    res.push({
      campo: 'Ubicación',
      titulo: 'Ubicación',
      valor: textoDist,
      clase: 'bg-white text-slate-800 border-slate-200 font-mono',
    })
  }

  // 2. Criterios de datos agrupados
  if (grupo.criterios && Array.isArray(grupo.criterios)) {
    for (const crit of grupo.criterios) {
      const campo = crit.campo || ''
      const campoNorm = campo.toLowerCase().trim()
      const valores = crit.valores || []
      const valorStr = valores.join(', ')

      if (campoNorm === 'ubicación' || campoNorm === 'proximidad') {
        if (!res.some(r => r.campo === 'Ubicación')) {
          res.push({
            campo: 'Ubicación',
            titulo: 'Ubicación',
            valor: `Distancia a ${valorStr}`,
            clase: 'bg-white text-slate-800 border-slate-200 font-mono',
          })
        }
        continue
      }

      if (campoNorm === 'nit') {
        res.push({
          campo: 'NIT',
          titulo: 'NIT Idéntico',
          valor: valorStr,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      } else if (campoNorm === 'ci') {
        res.push({
          campo: 'CI',
          titulo: 'CI Idéntico',
          valor: valorStr,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      } else if (campoNorm.includes('doc') || campoNorm.includes('nit/ci')) {
        res.push({
          campo: 'Documento (NIT/CI)',
          titulo: 'Doc. Identidad Coincidente',
          valor: valorStr,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      } else if (campoNorm.includes('tel') || campoNorm.includes('cel')) {
        res.push({
          campo: 'Teléfono',
          titulo: 'Tel/Cel Idéntico',
          valor: valorStr,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      } else if (campoNorm === 'nombre' || campoNorm.includes('similar')) {
        if (!res.some(r => r.campo === 'Nombre')) {
          const valLimpio = valorStr.includes('~') ? (grupo.clientes?.[0]?.cliente || valorStr) : valorStr
          res.push({
            campo: 'Nombre',
            titulo: 'Nombre Idéntico',
            valor: `"${valLimpio}"`,
            clase: 'bg-white text-slate-800 border-slate-200 font-mono',
          })
        }
      } else if (campoNorm === 'factura') {
        res.push({
          campo: 'Factura',
          titulo: 'Razón Social Idéntica',
          valor: `"${valorStr}"`,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      } else if (campoNorm.includes('factura') && campoNorm.includes('nombre')) {
        res.push({
          campo: 'Nombre / Factura',
          titulo: 'Nombre vs Razón Social',
          valor: valorStr,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      } else {
        res.push({
          campo: campo,
          titulo: campo.includes('Similar') ? campo : `${campo} Coincidente`,
          valor: valorStr,
          clase: 'bg-white text-slate-800 border-slate-200 font-mono',
        })
      }
    }
  }

  return res
}

function sonRutasDistintas() {
  if (!grupoActivo.value || !grupoActivo.value.clientes || grupoActivo.value.clientes.length < 2) return false
  const rutas = grupoActivo.value.clientes.map(c => (c.ruta ?? '').toString().trim().toUpperCase()).filter(Boolean)
  return new Set(rutas).size > 1
}

function sonDiasDistintos() {
  if (!grupoActivo.value || !grupoActivo.value.clientes || grupoActivo.value.clientes.length < 2) return false
  const dias = grupoActivo.value.clientes.map(c => (c.dia ?? '').toString().trim().toUpperCase()).filter(Boolean)
  return new Set(dias).size > 1
}

function campoEsIdenticoEnGrupo(campo) {
  if (!grupoActivo.value || !grupoActivo.value.clientes || grupoActivo.value.clientes.length < 2) return false
  const clientes = grupoActivo.value.clientes
  const val0 = (clientes[0][campo] ?? '').toString().trim().toUpperCase()
  if (!val0 || val0 === 'SIN NIT' || val0 === 'SIN CI' || val0 === 'SIN TELÉFONO' || val0 === '0') return false
  return clientes.every(c => (c[campo] ?? '').toString().trim().toUpperCase() === val0)
}

function documentoCoincideEnGrupo(docVal, clienteIdActual) {
  if (!docVal || !grupoActivo.value || !grupoActivo.value.clientes) return false
  const docClean = String(docVal).replace(/\D/g, '')
  if (docClean.length < 5) return false
  return grupoActivo.value.clientes.some(c => {
    if (c.cliente_id === clienteIdActual) return false
    const nitOther = String(c.nit || '').replace(/\D/g, '')
    const ciOther = String(c.ci || '').replace(/\D/g, '')
    return (nitOther && (nitOther === docClean || nitOther.startsWith(docClean))) ||
           (ciOther && (ciOther === docClean || docClean.startsWith(ciOther)))
  })
}

function telefonoEsIdenticoEnGrupo() {
  if (!grupoActivo.value || !grupoActivo.value.clientes || grupoActivo.value.clientes.length < 2) return false
  const grupo = grupoActivo.value
  const crit = grupo.criterios?.find(cr => {
    const cNorm = (cr.campo || '').toLowerCase()
    return cNorm.includes('tel') || cNorm.includes('cel')
  })
  return !!crit
}

function nombreEsIdenticoEnGrupo() {
  if (!grupoActivo.value || !grupoActivo.value.clientes || grupoActivo.value.clientes.length < 2) return false
  const grupo = grupoActivo.value
  const crit = grupo.criterios?.find(cr => {
    const cNorm = (cr.campo || '').toLowerCase()
    return cNorm === 'nombre' || cNorm.includes('similar')
  })
  return !!crit
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}

// ==========================================
// DESGLOSE DE CAMBIOS (LÓGICA MATRIZ DE CAMBIOS)
// ==========================================
async function alternarCambiosCliente(clienteId, cli = null) {
  const next = new Set(cambiosExpandidos.value)
  if (next.has(clienteId)) {
    next.delete(clienteId)
  } else {
    next.add(clienteId)
    // Carga perezosa si los cambios no estuvieran en memoria
    if (cli && (!cli.cambios || cli.cambios.length === 0) && cli.total_cambios !== 0) {
      cargandoCambios.value.add(clienteId)
      try {
        const url = typeof route === 'function'
          ? route('supervisor.saneamiento.duplicados.cliente.cambios', { clienteId })
          : `/supervisor/saneamiento/duplicados/cliente/${clienteId}/cambios`
        const res = await axios.get(url)
        if (res.data && res.data.cambios) {
          cli.cambios = res.data.cambios
          cli.total_cambios = res.data.total ?? res.data.cambios.length
        }
      } catch (err) {
        console.error(`Error al consultar cambios del cliente #${clienteId}:`, err)
      } finally {
        cargandoCambios.value.delete(clienteId)
      }
    }
  }
  cambiosExpandidos.value = next
}

function desglosarCambiosAmbos() {
  if (!grupoActivo.value || !grupoActivo.value.clientes) return
  const todosExpandidos = grupoActivo.value.clientes.every(c => cambiosExpandidos.value.has(c.cliente_id))
  const next = new Set(cambiosExpandidos.value)
  grupoActivo.value.clientes.forEach(c => {
    if (todosExpandidos) {
      next.delete(c.cliente_id)
    } else {
      next.add(c.cliente_id)
      if ((!c.cambios || c.cambios.length === 0) && c.total_cambios !== 0) {
        alternarCambiosCliente(c.cliente_id, c)
      }
    }
  })
  cambiosExpandidos.value = next
}

const sonTodosCambiosExpandidos = computed(() => {
  if (!grupoActivo.value || !grupoActivo.value.clientes || grupoActivo.value.clientes.length === 0) return false
  return grupoActivo.value.clientes.every(c => cambiosExpandidos.value.has(c.cliente_id))
})

function esCambioCoordenadas(ev) {
  if (!ev) return false
  const tipo = (ev.tipo_evento || '').toUpperCase()
  const campo = (ev.campo_afectado || '').toLowerCase()
  return tipo === 'MODIFICADO_COORDENADAS' || campo === 'coordenadas' || campo === 'latitud' || campo === 'longitud' || campo === 'ubicacion'
}

function formatearTipoEvento(tipo, campo = '') {
  if (campo === 'cliente' || tipo === 'CAMBIO_CLIENTE' || tipo === 'MODIFICADO_CLIENTE') {
    return 'Cliente'
  }
  switch (tipo) {
    case 'NUEVO': return 'Alta'
    case 'REACTIVADO': return 'Reactivado'
    case 'ELIMINADO': return 'Baja'
    case 'INACTIVADO': return 'Inactivado'
    case 'MODIFICADO_RUTA': return 'Cambio Ruta'
    case 'MODIFICADO_DIA': return 'Cambio Día'
    case 'MODIFICADO_COORDENADAS': return 'GPS'
    case 'CAMBIO_DE_CLUSTER': return 'Cluster'
    case 'MODIFICADO_VENDEDOR': return 'Vendedor'
    case 'FRECUENCIA_VISITA': return 'Frecuencia'
    case 'MODIFICACION_DATOS':
    case 'MODIFICADO': return 'Datos'
    default: return tipo || 'Modificado'
  }
}

function obtenerBadgeEventoClase(tipo, campo = '') {
  if (campo === 'cliente' || tipo === 'CAMBIO_CLIENTE' || tipo === 'MODIFICADO_CLIENTE') {
    return 'bg-[#F0F4FF] text-[#2563EB] border-[#BFDBFE]'
  }
  switch (tipo) {
    case 'NUEVO':
    case 'REACTIVADO':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]'
    case 'ELIMINADO':
    case 'INACTIVADO':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]'
    case 'MODIFICADO_RUTA':
      return 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]'
    case 'MODIFICADO_DIA':
      return 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]'
    case 'MODIFICADO_COORDENADAS':
      return 'bg-[#FEF5E7] text-[#D35400] border-[#FADBD8]'
    case 'CAMBIO_DE_CLUSTER':
      return 'bg-[#F5EEF8] text-[#8E44AD] border-[#E8DAEF]'
    case 'MODIFICADO_VENDEDOR':
      return 'bg-[#E8F8F5] text-[#16A085] border-[#D1F2EB]'
    case 'FRECUENCIA_VISITA':
      return 'bg-[#EBF5FB] text-[#2980B9] border-[#D4E6F1]'
    case 'MODIFICACION_DATOS':
    case 'MODIFICADO':
      return 'bg-[#F0F2F5] text-[#475569] border-[#CBD5E1]'
    default:
      return 'bg-black/[0.05] text-[#1D1D1F] border-black/10'
  }
}

// ==========================================
// OPERACIONES MAPA LEAFLET
// ==========================================
function cambiarCapaMapa(nombreCapa) {
  if (!map || !mapsConfig.tileLayers[nombreCapa]) return
  capaActual.value = nombreCapa

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer)
  }

  const layerConf = mapsConfig.tileLayers[nombreCapa]
  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map)
}

function initMap() {
  const container = document.getElementById('mapaDuplicados')
  if (!container) return

  if (map) {
    map.remove()
    map = null
  }

  const defaultLoc = mapsConfig.defaultLocation

  map = L.map('mapaDuplicados', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom)

  L.control.zoom({ position: 'topright' }).addTo(map)
  cambiarCapaMapa(capaActual.value)

  collisionLayerGroup = L.featureGroup().addTo(map)

  setTimeout(() => {
    if (map) map.invalidateSize()
  }, 100)
}

function enfocarClienteEnMapa(cli) {
  if (!cli || !cli.latitud || !cli.longitud) return
  clienteFocoId.value = cli.cliente_id

  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    vistaMovil.value = 'mapa'
  }

  nextTick(() => {
    if (!map) initMap()
    if (map) {
      map.invalidateSize()
      map.flyTo([Number(cli.latitud), Number(cli.longitud)], 19, {
        animate: true,
        duration: 0.8,
      })
    }
  })
}

function enfocarGrupoEnMapa(grupo) {
  if (!grupo || !grupo.clientes || grupo.clientes.length === 0) return

  clienteFocoId.value = null

  if (!map) {
    initMap()
  }
  if (!map) return

  map.invalidateSize()

  if (!collisionLayerGroup) {
    collisionLayerGroup = L.featureGroup().addTo(map)
  } else {
    collisionLayerGroup.clearLayers()
  }

  const latLngs = []

  grupo.clientes.forEach((cli) => {
    if (cli.latitud && cli.longitud) {
      const latlng = [Number(cli.latitud), Number(cli.longitud)]
      latLngs.push(latlng)

      const markerColor = cli.es_mas_antiguo ? '#059669' : '#DC2626'

      const marker = L.circleMarker(latlng, {
        radius: cli.es_mas_antiguo ? 8.5 : 7.5,
        fillColor: markerColor,
        color: '#FFFFFF',
        weight: 2,
        fillOpacity: 1,
      }).addTo(collisionLayerGroup)

      marker.on('click', () => {
        enfocarClienteEnMapa(cli)
      })
    }
  })

  // Ajustar encuadre del mapa a los puntos del grupo sin elementos adicionales
  if (latLngs.length >= 2) {
    const bounds = collisionLayerGroup.getBounds()
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [50, 50], maxZoom: 18 })
    }
  } else if (latLngs.length === 1) {
    map.flyTo(latLngs[0], 18)
  }
}

// ==========================================
// PETICIONES AL BACKEND
// ==========================================
async function ejecutarAuditoriaDuplicados() {
  cargando.value = true
  try {
    const res = await axios.post(route('supervisor.saneamiento.duplicados.auditar'), {
      canales: canalesSeleccionados.value,
      rutas: rutasSeleccionadas.value.length > 0 ? rutasSeleccionadas.value : null,
      radio_metros: radioMetros.value,
      tipo_grupo: filtroModo.value,
      periodo: periodoSeleccionado.value || null,
    })

    clusters.value = res.data.clusters || []
    mesesDisponibles.value = res.data.meses_disponibles || []
    periodoLabel.value = res.data.periodo_label || ''
    if (!periodoSeleccionado.value && res.data.periodo_evaluado) {
      periodoSeleccionado.value = res.data.periodo_evaluado
    }

    clusters.value.forEach(g => marcarAntiguedadGrupo(g))

    if (clusters.value.length > 0) {
      seleccionarGrupo(clusters.value[0], false)
    } else {
      grupoSeleccionadoId.value = null
      if (collisionLayerGroup) collisionLayerGroup.clearLayers()
    }
  } catch (error) {
    console.error('Error al auditar duplicados:', error)
    mostrarToast('Ocurrió un error al auditar duplicados', 'error')
  } finally {
    cargando.value = false
  }
}

async function cargarPropuestasPendientes() {
  try {
    const res = await axios.get(route('supervisor.saneamiento.duplicados.propuestas-pendientes'))
    propuestasPendientes.value = res.data || []
  } catch (error) {
    console.error('Error al cargar propuestas pendientes:', error)
  }
}

function abrirModalConfirmacion(tipo) {
  if (!grupoActivo.value) return
  tipoAccionModal.value = tipo
  clienteMasterModal.value = getMasterCliente(grupoActivo.value)
  clienteDuplicadoModal.value = getDuplicadoCliente(grupoActivo.value)

  const labelPeriodo = periodoLabel.value || 'Mes'
  if (tipo === 'INACTIVAR') {
    observacionModal.value = `Cliente ID #${clienteDuplicadoModal.value?.cliente_id} duplicado de ID #${clienteMasterModal.value?.cliente_id} (Ventas ${labelPeriodo}: Bs. ${clienteDuplicadoModal.value?.venta_mtd} vs Bs. ${clienteMasterModal.value?.venta_mtd})`
  } else {
    observacionModal.value = `Fusión de cuentas entre cliente duplicado ID #${clienteDuplicadoModal.value?.cliente_id} y master ID #${clienteMasterModal.value?.cliente_id}`
  }

  modalConfirmacionVisible.value = true
}

async function confirmarYEnviarPropuesta() {
  if (!clienteMasterModal.value || !clienteDuplicadoModal.value || !grupoActivo.value) return

  procesandoPropuesta.value = true
  try {
    const accion = tipoAccionModal.value === 'INACTIVAR' ? 'INACTIVAR_DUPLICADO' : 'FUSIONAR_CLIENTE'

    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: clienteDuplicadoModal.value.cliente_id,
      cliente_id_vinculado: clienteMasterModal.value.cliente_id,
      cliente_nombre: clienteDuplicadoModal.value.cliente,
      ruta_actual: clienteDuplicadoModal.value.ruta,
      dia_actual: clienteDuplicadoModal.value.dia,
      accion_propuesta: accion,
      distancia_metros: grupoActivo.value.distancia_minima_m,
      observaciones: observacionModal.value,
    })

    clienteDuplicadoModal.value.tiene_propuesta_pendiente = true
    modalConfirmacionVisible.value = false
    await cargarPropuestasPendientes()
    mostrarToast('Propuesta registrada con éxito en estado PENDIENTE', 'success')
  } catch (err) {
    console.error('Error al crear propuesta:', err)
    mostrarToast('Error al registrar la propuesta', 'error')
  } finally {
    procesandoPropuesta.value = false
  }
}

async function cambiarEstadoPropuesta(id, nuevoEstado) {
  filaEnProceso.value = id
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.cambiar-estado'), {
      propuesta_id: id,
      estado: nuevoEstado,
    })
    await cargarPropuestasPendientes()
    await ejecutarAuditoriaDuplicados()
    mostrarToast(`Propuesta #${id} marcada como ${nuevoEstado}`, 'success')
  } catch (err) {
    console.error('Error al cambiar estado de propuesta:', err)
    mostrarToast('Error al actualizar el estado de la propuesta', 'error')
  } finally {
    filaEnProceso.value = null
  }
}

async function revertirPropuesta(id) {
  filaEnProceso.value = id
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.revertir'), {
      propuesta_id: id,
    })
    await cargarPropuestasPendientes()
    await ejecutarAuditoriaDuplicados()
    mostrarToast(`Propuesta #${id} revertida con éxito`, 'info')
  } catch (err) {
    console.error('Error al revertir propuesta:', err)
    mostrarToast('Error al revertir la propuesta', 'error')
  } finally {
    filaEnProceso.value = null
  }
}

// Navegacion con teclado
function manejarTeclado(e) {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
    return
  }
  if (e.key === 'ArrowRight' || e.key === ']' || e.key === 'j') {
    navegarGrupo(1)
  } else if (e.key === 'ArrowLeft' || e.key === '[' || e.key === 'k') {
    navegarGrupo(-1)
  }
}

// Cerrar dropdowns al hacer clic fuera
function manejarClickFuera(e) {
  if (canalDropdownRef.value && !canalDropdownRef.value.contains(e.target)) {
    menuCanalAbierto.value = false
  }
  if (rutaDropdownRef.value && !rutaDropdownRef.value.contains(e.target)) {
    menuRutaAbierto.value = false
  }
}

onMounted(async () => {
  await nextTick()
  initMap()
  await ejecutarAuditoriaDuplicados()
  await cargarPropuestasPendientes()
  window.addEventListener('keydown', manejarTeclado)
  document.addEventListener('click', manejarClickFuera)
})

onUnmounted(() => {
  window.removeEventListener('keydown', manejarTeclado)
  document.removeEventListener('click', manejarClickFuera)
  if (map) {
    map.remove()
    map = null
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
  background: #cbd5e1;
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
