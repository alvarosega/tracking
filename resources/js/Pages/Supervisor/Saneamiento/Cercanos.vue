<template>
  <SupervisorLayout full-bleed title="Saneamiento | Clientes Cercanos">
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900 font-sans text-slate-900">
      <!-- Navegacion de Submodulos Saneamiento Padron -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 shrink-0 relative z-30">
        <AuditoriaSaneamientoSubTabs active-tab="cercanos" :compact="true" />
      </div>

      <!-- ========================================== -->
      <!-- MOBILE HEADER (< md): 3 FILAS COMPACTAS   -->
      <!-- ========================================== -->
      <div class="flex md:hidden flex-col gap-2 p-2 bg-white/95 backdrop-blur-md border-b border-slate-200/90 shrink-0 relative z-30 shadow-2xs">
        <!-- Fila 1: [ Mapa | Lista (N) ] a la izquierda y Boton Filtros/Fronteras + Puntos en radio -->
        <div class="flex items-center justify-between gap-1.5">
          <!-- Selector Vista Mapa / Lista -->
          <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shrink-0">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1"
              :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
              </svg>
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'lista'"
              class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1"
              :class="vistaMovil === 'lista' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
              </svg>
              Lista ({{ clientesFiltrados.length }})
            </button>
          </div>

          <!-- Acciones Derecha: Boton Drawer Filtros/Fronteras y Puntos en radio -->
          <div class="flex items-center gap-1.5 shrink-0">
            <button
              type="button"
              @click="drawerFiltrosMovil = true"
              class="h-7 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 rounded-lg text-xs font-medium flex items-center gap-1.5 cursor-pointer shadow-2xs transition-all"
              title="Ajustar radio, canal, rutas y fronteras"
            >
              <svg class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
              <span>Filtros</span>
              <span v-if="verFronterasRutas || filtroCanales.length > 0 || filtroRutas.length > 0" class="w-2 h-2 rounded-full bg-sky-500"></span>
            </button>

            <div class="h-7 px-2 bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-1 text-xs font-mono font-bold text-slate-900 shadow-2xs">
              <span class="text-slate-400 font-normal text-[10px]">Radio:</span>
              <span class="tabular-nums text-xs">{{ clientesFiltrados.length }}</span>
            </div>
          </div>
        </div>

        <!-- Fila 2: GPS, Buscador Maps / Coordenadas y Selector Radio -->
        <div class="flex items-center gap-1.5">
          <!-- Boton GPS -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 w-8 bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-200 rounded-lg flex items-center justify-center transition-all shrink-0 disabled:opacity-50 cursor-pointer shadow-2xs"
            title="Usar mi ubicacion GPS"
          >
            <span v-if="obteniendoGps" class="w-3 h-3 rounded-full bg-amber-500 animate-ping"></span>
            <svg v-else class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
          </button>

          <!-- Input Buscador Maps -->
          <div class="relative flex items-center h-8 flex-1 min-w-0">
            <input
              type="text"
              v-model="inputEnlaceMaps"
              @keydown.enter.prevent="resolverEnlaceMaps"
              placeholder="Enlace Maps o coords..."
              class="w-full h-8 pl-2.5 pr-10 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs font-mono text-slate-800 placeholder:text-slate-400 placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500 transition-all"
            />
            <button
              type="button"
              @click="resolverEnlaceMaps"
              :disabled="resolviendoEnlace || !inputEnlaceMaps.trim()"
              class="absolute right-1 h-6 px-2 bg-sky-600 hover:bg-sky-500 text-white rounded text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer flex items-center justify-center"
            >
              <span v-if="resolviendoEnlace" class="w-2.5 h-2.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span v-else>Ir</span>
            </button>
          </div>

          <!-- Selector Radio Compacto -->
          <select
            v-model="radioSeleccionado"
            @change="seleccionarRadio(Number(radioSeleccionado))"
            class="h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono font-bold text-slate-900 cursor-pointer shrink-0"
          >
            <option v-for="r in opcionesRadio" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
          </select>

          <!-- Chip Punto de Referencia Movil -->
          <button
            v-if="origenConsulta"
            type="button"
            @click="enfocarPuntoReferencia"
            class="h-8 px-2 bg-rose-50 hover:bg-rose-100 text-rose-900 border border-rose-200 rounded-lg text-xs font-mono font-bold flex items-center gap-1 shrink-0 shadow-2xs cursor-pointer"
            title="Centrar mapa en punto de referencia"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
            <span class="text-[9.5px] font-sans font-bold text-rose-600 uppercase">Ref:</span>
            <span class="text-[10px] tabular-nums">{{ Number(origenConsulta.lat).toFixed(4) }}, {{ Number(origenConsulta.lng).toFixed(4) }}</span>
          </button>
        </div>

        <!-- Fila 3: Quick Bar Tactil (Canal, Rutas, Fronteras Rutas) -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 pt-0.5 custom-scroll text-xs">
          <!-- Chip Canal -->
          <button
            type="button"
            @click="drawerFiltrosMovil = true"
            class="h-6 px-2 rounded-md font-mono text-[10.5px] font-bold flex items-center gap-1 transition-all shrink-0 cursor-pointer border"
            :class="filtroCanales.length > 0 ? 'bg-amber-50 text-amber-900 border-amber-300 shadow-2xs' : 'bg-slate-100 text-slate-600 border-slate-200'"
            title="Ajustar filtro de canal"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="filtroCanales.length > 0 ? 'bg-amber-500' : 'bg-slate-400'"></span>
            <span class="text-slate-400 font-normal">Canal:</span>
            <span>{{ labelCanales }}</span>
          </button>

          <!-- Chip Rutas -->
          <button
            type="button"
            @click="drawerFiltrosMovil = true"
            class="h-6 px-2 rounded-md font-mono text-[10.5px] font-bold flex items-center gap-1 transition-all shrink-0 cursor-pointer border"
            :class="filtroRutas.length > 0 ? 'bg-indigo-50 text-indigo-900 border-indigo-300 shadow-2xs' : 'bg-slate-100 text-slate-600 border-slate-200'"
            title="Ajustar filtro de rutas"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="filtroRutas.length > 0 ? 'bg-indigo-600' : 'bg-slate-400'"></span>
            <span class="text-slate-400 font-normal">Rutas:</span>
            <span>{{ labelRutas }}</span>
          </button>

          <!-- Toggle Fronteras Rutas -->
          <button
            type="button"
            @click="verFronterasRutas = !verFronterasRutas"
            class="h-6 px-2 rounded-md font-mono text-[10.5px] font-bold flex items-center gap-1 transition-all shrink-0 cursor-pointer border"
            :class="verFronterasRutas ? 'bg-indigo-50 text-indigo-700 border-indigo-300 shadow-2xs' : 'bg-slate-100 text-slate-400 border-slate-200'"
            title="Activar o desactivar poligonos de fronteras rutas"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="verFronterasRutas ? 'bg-indigo-600' : 'bg-slate-400'"></span>
            <span>F. Rutas</span>
            <span v-if="verFronterasRutas && filtroRutasFronterasRutas.length > 0" class="text-[9px] px-1.5 py-0.2 rounded bg-indigo-200/80 text-indigo-900 font-bold font-mono">
              {{ labelFronterasRutas }}
            </span>
          </button>
        </div>

        <!-- Toast Feedback Maps -->
        <div
          v-if="mensajeEnlace"
          class="px-2.5 py-1 rounded-lg text-[11px] font-medium border flex items-center justify-between gap-1.5 shadow-xs"
          :class="mensajeEnlace.tipo === 'exito' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
        >
          <span class="truncate">{{ mensajeEnlace.texto }}</span>
          <button type="button" @click="mensajeEnlace = null" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer shrink-0">&times;</button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- DESKTOP HEADER (>= md): 1 FILA COMPLETA   -->
      <!-- ========================================== -->
      <div class="hidden md:flex bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-4 py-2 items-center justify-between gap-2 shrink-0 relative z-30">
        <!-- Backdrop para cerrar popovers al hacer click fuera -->
        <div
          v-if="menuAbierto"
          class="fixed inset-0 z-30"
          @click="menuAbierto = null"
        ></div>

        <div class="flex items-center gap-2 flex-wrap py-0.5 flex-1 min-w-0 relative z-40">
          <!-- Boton de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-200/90 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all shrink-0 disabled:opacity-50 cursor-pointer shadow-2xs"
            title="Usar ubicacion GPS del dispositivo"
          >
            <span class="w-2 h-2 rounded-full" :class="obteniendoGps ? 'bg-amber-500 animate-ping' : 'bg-sky-500'"></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : 'Mi Ubicacion' }}</span>
          </button>

          <!-- Buscador / Pegar Enlace de Google Maps o Coordenadas -->
          <div class="relative flex items-center h-8 shrink-0">
            <div class="absolute left-2.5 text-slate-400 pointer-events-none flex items-center">
              <svg class="w-3.5 h-3.5 text-rose-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
              </svg>
            </div>
            <input
              type="text"
              v-model="inputEnlaceMaps"
              @keydown.enter.prevent="resolverEnlaceMaps"
              placeholder="Enlace Maps o coords..."
              class="h-8 pl-8 pr-12 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200/90 rounded-lg text-xs font-mono text-slate-800 placeholder:text-slate-400 placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500 w-48 lg:w-56 transition-all"
              title="Pega enlaces de Google Maps o coordenadas (-16.505, -68.165)"
            />
            <button
              type="button"
              @click="resolverEnlaceMaps"
              :disabled="resolviendoEnlace || !inputEnlaceMaps.trim()"
              class="absolute right-1 h-6 px-2 bg-sky-600 hover:bg-sky-500 text-white rounded text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer flex items-center justify-center gap-1"
            >
              <span v-if="resolviendoEnlace" class="w-2.5 h-2.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ resolviendoEnlace ? '' : 'Ir' }}</span>
            </button>
          </div>

          <!-- Toast Feedback Maps -->
          <div
            v-if="mensajeEnlace"
            class="px-2 py-1 rounded-lg text-[11px] font-medium border flex items-center gap-1.5 transition-all shadow-xs shrink-0"
            :class="mensajeEnlace.tipo === 'exito' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
          >
            <span>{{ mensajeEnlace.texto }}</span>
            <button type="button" @click="mensajeEnlace = null" class="ml-1 text-slate-400 hover:text-slate-600 font-bold cursor-pointer">&times;</button>
          </div>

          <!-- Chip Punto de Referencia Activo en Desktop -->
          <button
            v-if="origenConsulta"
            type="button"
            @click="enfocarPuntoReferencia"
            class="h-8 px-2.5 bg-rose-50 hover:bg-rose-100 border border-rose-200/90 text-rose-900 rounded-lg text-xs font-mono flex items-center gap-1.5 shadow-2xs shrink-0 cursor-pointer transition-colors"
            title="Punto de referencia activo. Clic para centrar mapa aquí."
          >
            <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span>
            <span class="text-rose-600 font-sans text-[10px] uppercase font-bold tracking-wider">Ref:</span>
            <span class="font-bold tabular-nums">{{ Number(origenConsulta.lat).toFixed(5) }}, {{ Number(origenConsulta.lng).toFixed(5) }}</span>
            <svg class="w-3.5 h-3.5 text-rose-500 hover:text-rose-700 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
          </button>

          <!-- Selector Radio -->
          <div class="flex items-center h-8 px-2 bg-slate-50 border border-slate-200/90 rounded-lg text-xs text-slate-800 shrink-0">
            <span class="text-slate-400 mr-1 font-mono">Radio:</span>
            <select
              v-model="radioSeleccionado"
              @change="seleccionarRadio(Number(radioSeleccionado))"
              class="bg-transparent font-bold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer text-slate-900"
            >
              <option v-for="r in opcionesRadio" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
            </select>
          </div>

          <!-- FILTRO CANAL EN DESKTOP -->
          <div class="relative shrink-0">
            <button
              type="button"
              @click="toggleMenu('canales')"
              class="h-8 px-2.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all border cursor-pointer shadow-2xs"
              :class="filtroCanales.length > 0 ? 'bg-amber-50 text-amber-900 border-amber-300 font-bold' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
              title="Filtrar clientes por canal"
            >
              <span class="w-2 h-2 rounded-full" :class="filtroCanales.length > 0 ? 'bg-amber-500' : 'bg-slate-300'"></span>
              <span class="text-slate-500 font-normal">Canal:</span>
              <span class="font-mono">{{ labelCanales }}</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <!-- Popover Canal -->
            <div
              v-if="menuAbierto === 'canales'"
              class="absolute top-full left-0 mt-1 w-56 bg-white rounded-xl shadow-xl border border-slate-200 p-2.5 z-50 text-xs flex flex-col gap-2 font-sans"
            >
              <div class="flex items-center justify-between pb-1.5 border-b border-slate-100">
                <span class="font-bold text-slate-800 text-xs">Filtro por Canal</span>
                <button
                  type="button"
                  @click="menuAbierto = null"
                  class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer text-sm"
                >
                  &times;
                </button>
              </div>

              <!-- Acciones rapidas: Solo TDB | Todos | Limpiar -->
              <div class="flex items-center gap-1 text-[10.5px]">
                <button
                  type="button"
                  @click="seleccionarSoloCanalTDB"
                  class="px-2 py-0.5 rounded text-[10.5px] font-bold border transition-colors cursor-pointer"
                  :class="filtroCanales.length === 1 && filtroCanales[0] === 'TDB' ? 'bg-amber-500 text-white border-amber-600 shadow-2xs' : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border-amber-200'"
                >
                  Solo TDB
                </button>
                <button
                  type="button"
                  @click="seleccionarTodosCanales"
                  class="px-2 py-0.5 rounded text-[10.5px] font-medium border bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 transition-colors cursor-pointer"
                >
                  Todos
                </button>
                <button
                  type="button"
                  @click="limpiarCanales"
                  class="px-2 py-0.5 rounded text-[10.5px] font-medium text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer ml-auto"
                >
                  Limpiar
                </button>
              </div>

              <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5">
                <label
                  v-for="canal in canalesDisponibles"
                  :key="canal"
                  class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md hover:bg-slate-50 cursor-pointer text-xs font-mono transition-colors"
                  :class="filtroCanales.includes(canal) ? 'bg-amber-50/60 font-semibold text-slate-900' : 'text-slate-600'"
                >
                  <div class="flex items-center gap-2">
                    <input
                      type="checkbox"
                      :checked="filtroCanales.includes(canal)"
                      @change="toggleCanal(canal)"
                      class="rounded border-slate-300 text-amber-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(canal) }"></span>
                    <span>{{ canal }}</span>
                  </div>
                  <span class="text-[10px] text-slate-400 font-sans">
                    {{ contarRutasPorCanal(canal) }} rts
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- FILTRO FRONTERAS RUTAS EN DESKTOP (AL LADO DE CANAL) -->
          <div class="relative shrink-0">
            <button
              type="button"
              @click="toggleMenu('fronteras_rutas')"
              class="h-8 px-2.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all border cursor-pointer shadow-2xs"
              :class="verFronterasRutas && filtroRutasFronterasRutas.length > 0 ? 'bg-purple-50 text-purple-900 border-purple-300 font-bold' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
              title="Filtrar qué rutas de fronteras mostrar en el mapa"
            >
              <span class="w-2 h-2 rounded-full" :class="verFronterasRutas && filtroRutasFronterasRutas.length > 0 ? 'bg-purple-600' : 'bg-slate-300'"></span>
              <span class="text-slate-500 font-normal">F. Rutas:</span>
              <span class="font-mono">{{ labelFronterasRutas }}</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <!-- Popover de Rutas (Fronteras Rutas) -->
            <div
              v-if="menuAbierto === 'fronteras_rutas'"
              class="absolute top-full left-0 mt-1 w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2.5 z-50 text-xs flex flex-col gap-2 font-sans"
            >
              <div class="flex items-center justify-between pb-1.5 border-b border-slate-100">
                <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                  <span class="font-bold text-slate-800 text-xs">Fronteras por Ruta</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <button
                    type="button"
                    @click="verFronterasRutas = !verFronterasRutas"
                    class="text-[10px] font-bold px-1.5 py-0.5 rounded cursor-pointer transition-all"
                    :class="verFronterasRutas ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-500'"
                    :title="verFronterasRutas ? 'Ocultar polígonos' : 'Mostrar polígonos'"
                  >
                    {{ verFronterasRutas ? 'Visible' : 'Oculto' }}
                  </button>
                  <button
                    type="button"
                    @click="menuAbierto = null"
                    class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer text-sm"
                  >
                    &times;
                  </button>
                </div>
              </div>

              <!-- Botones de Acción Rápida: Solo TDB | Todas | Limpiar -->
              <div class="flex items-center gap-1 text-[10.5px] flex-wrap">
                <button
                  type="button"
                  @click="seleccionarRutasTDB"
                  class="px-2 py-0.5 rounded-md text-[10.5px] font-bold border transition-colors cursor-pointer flex items-center gap-1"
                  :class="labelFronterasRutas.startsWith('TDB') ? 'bg-amber-500 text-white border-amber-600 shadow-2xs' : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border-amber-200'"
                  title="Seleccionar únicamente las rutas del canal TDB"
                >
                  <span>Solo TDB</span>
                  <span class="text-[9px] opacity-90 font-mono">({{ obtenerRutasTDB().length }})</span>
                </button>
                <button
                  type="button"
                  @click="seleccionarTodasFronterasRutas"
                  class="px-2 py-0.5 rounded-md text-[10.5px] font-medium border bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 transition-colors cursor-pointer"
                >
                  Todas
                </button>
                <button
                  type="button"
                  @click="limpiarFronterasRutas"
                  class="px-2 py-0.5 rounded-md text-[10.5px] font-medium text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer ml-auto"
                >
                  Limpiar
                </button>
              </div>

              <input
                v-model="busquedaRutaFronterasRutas"
                type="text"
                placeholder="Buscar ruta o canal..."
                class="h-7 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:border-slate-400 font-mono"
              />

              <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5 pr-0.5">
                <div v-if="rutasFronterasRutasFiltradasBusqueda.length === 0" class="py-2 text-center text-slate-400 text-xs">
                  No hay rutas disponibles
                </div>
                <label
                  v-for="r in rutasFronterasRutasFiltradasBusqueda"
                  :key="r"
                  class="flex items-center justify-between gap-1.5 px-2 py-1 rounded-md hover:bg-slate-50 cursor-pointer text-xs font-mono transition-colors"
                  :class="esRutaFronteraRutaActiva(r) ? 'bg-purple-50/60 font-semibold text-slate-900' : 'text-slate-600'"
                >
                  <div class="flex items-center gap-2 truncate min-w-0">
                    <input
                      type="checkbox"
                      :checked="esRutaFronteraRutaActiva(r)"
                      @change="toggleRutaFronterasRutas(r)"
                      class="rounded border-slate-300 text-purple-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(r) }"></span>
                    <span class="truncate">{{ formatRuta(r) }}</span>
                  </div>
                  <span
                    v-if="getCanalDeRuta(r)"
                    class="text-[9.5px] px-1.5 py-0.2 rounded font-mono font-bold shrink-0"
                    :class="getCanalDeRuta(r) === 'TDB' ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ getCanalDeRuta(r) }}
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- FILTRO RUTAS EN DESKTOP -->
          <div class="relative shrink-0">
            <button
              type="button"
              @click="toggleMenu('rutas')"
              class="h-8 px-2.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all border cursor-pointer shadow-2xs"
              :class="filtroRutas.length > 0 ? 'bg-indigo-50 text-indigo-900 border-indigo-300 font-bold' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
              title="Filtrar clientes por rutas específicas"
            >
              <span class="w-2 h-2 rounded-full" :class="filtroRutas.length > 0 ? 'bg-indigo-600' : 'bg-slate-300'"></span>
              <span class="text-slate-500 font-normal">Rutas:</span>
              <span class="font-mono">{{ labelRutas }}</span>
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>

            <!-- Popover Rutas -->
            <div
              v-if="menuAbierto === 'rutas'"
              class="absolute top-full left-0 mt-1 w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2.5 z-50 text-xs flex flex-col gap-2 font-sans"
            >
              <div class="flex items-center justify-between pb-1.5 border-b border-slate-100">
                <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                  <span class="font-bold text-slate-800 text-xs">Filtrar por Ruta</span>
                </div>
                <button
                  type="button"
                  @click="menuAbierto = null"
                  class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer text-sm"
                >
                  &times;
                </button>
              </div>

              <!-- Acciones: Todas | Limpiar -->
              <div class="flex items-center justify-between text-[10.5px]">
                <button
                  type="button"
                  @click="seleccionarTodasRutas"
                  class="px-2 py-0.5 rounded text-[10.5px] font-medium border bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 transition-colors cursor-pointer"
                >
                  Todas las Rutas
                </button>
                <button
                  v-if="filtroRutas.length > 0"
                  type="button"
                  @click="limpiarFiltroRutas"
                  class="px-2 py-0.5 rounded text-[10.5px] font-medium text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                >
                  Limpiar ({{ filtroRutas.length }})
                </button>
              </div>

              <!-- Buscador de Ruta -->
              <input
                v-model="busquedaFiltroRutas"
                type="text"
                placeholder="Buscar ruta o canal..."
                class="h-7 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:border-slate-400 font-mono"
              />

              <!-- Lista de Rutas -->
              <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5 pr-0.5">
                <div v-if="rutasFiltradasBusqueda.length === 0" class="py-3 text-center text-slate-400 text-xs">
                  No hay rutas coincidentes
                </div>
                <label
                  v-for="r in rutasFiltradasBusqueda"
                  :key="r"
                  class="flex items-center justify-between gap-1.5 px-2 py-1 rounded-md hover:bg-slate-50 cursor-pointer text-xs font-mono transition-colors"
                  :class="filtroRutas.includes(r) ? 'bg-indigo-50/60 font-semibold text-slate-900' : 'text-slate-600'"
                >
                  <div class="flex items-center gap-2 truncate min-w-0">
                    <input
                      type="checkbox"
                      :checked="filtroRutas.includes(r)"
                      @change="toggleFiltroRuta(r)"
                      class="rounded border-slate-300 text-indigo-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(r) }"></span>
                    <span class="truncate">{{ formatRuta(r) }}</span>
                  </div>
                  <span
                    v-if="getCanalDeRuta(r)"
                    class="text-[9.5px] px-1.5 py-0.2 rounded font-mono font-bold shrink-0"
                    :class="getCanalDeRuta(r) === 'TDB' ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ getCanalDeRuta(r) }}
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- Selector de Modo de Coloracion (Ruta | Canal | Dia) -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0">
            <span class="text-[10px] font-mono text-slate-400 px-1">Color:</span>
            <button
              type="button"
              @click="setModoColor('ruta')"
              class="px-1.5 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'ruta' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Ruta
            </button>
            <button
              type="button"
              @click="setModoColor('canal')"
              class="px-1.5 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'canal' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Canal
            </button>
            <button
              type="button"
              @click="setModoColor('dia')"
              class="px-1.5 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'dia' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Dia
            </button>
          </div>
        </div>

        <!-- Indicador de Carga y Contador de Puntos en Radio -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0 relative z-40">
          <span v-if="cargando" class="text-[11px] text-slate-500 flex items-center gap-1.5 animate-pulse mr-1">
            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
            Rastreando...
          </span>

          <div class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg flex items-center gap-1.5 shadow-2xs">
            <span class="text-slate-400">En radio:</span>
            <span class="font-bold text-slate-900 tabular-nums">{{ clientesFiltrados.length }} pts</span>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- SPLIT SCREEN PRINCIPAL                     -->
      <!-- ========================================== -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Lista o Expediente del Cliente -->
        <aside
          class="w-full md:w-[380px] bg-white border-r border-slate-200 flex flex-col shrink-0 relative z-20 overflow-hidden transition-all"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex h-full'"
        >
          <!-- Vista 1: Listado de Clientes en Radio -->
          <template v-if="!clienteSeleccionado">
            <!-- Barra de Busqueda y Coordenadas -->
            <div class="p-2.5 border-b border-slate-100 bg-slate-50/60 flex items-center space-x-2">
              <div class="relative flex-1">
                <input
                  v-model="busquedaTexto"
                  type="text"
                  placeholder="Filtrar por nombre, ruta o ID..."
                  class="w-full h-8 pl-8 pr-3 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-slate-400 transition-all shadow-2xs"
                />
                <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
              </div>

              <!-- Boton en movil para volver rapido al mapa -->
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-semibold flex items-center gap-1 shrink-0 border border-slate-200"
              >
                <span>Mapa</span>
              </button>
            </div>

            <!-- Listado con Scroll -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 custom-scroll">
              <div v-if="clientesCercanos.length === 0" class="p-8 text-center text-slate-400 text-xs">
                <p class="font-medium text-slate-600 mb-1">Sin clientes en el radar</p>
                <p>Haz clic en el mapa, usa tu ubicacion GPS o pega un enlace de Google Maps para inspeccionar clientes cercanos.</p>
              </div>
              <div v-else-if="clientesFiltrados.length === 0" class="p-8 text-center text-slate-400 text-xs">
                <p class="font-medium text-slate-600 mb-1">Sin coincidencias con los filtros</p>
                <p>Prueba seleccionando otro canal, ruta o limpiando el texto de búsqueda.</p>
              </div>

              <div
                v-for="c in clientesFiltrados"
                :key="c.id"
                @click="abrirExpedienteCliente(c, 'lista')"
                class="p-3 hover:bg-slate-50/80 cursor-pointer transition-all flex items-start space-x-3 group relative"
                :class="[
                  clienteActivoId === c.id ? 'bg-sky-50/60 border-l-4 border-sky-500' : '',
                  (!c.estado || c.estado.toLowerCase() === 'activo') ? '' : 'bg-slate-50/70'
                ]"
              >
                <!-- Indicador de Color Segun Modo (gris en inactivos) -->
                <div
                  class="w-3 h-3 rounded-full mt-1 shrink-0 shadow-2xs"
                  :style="{ backgroundColor: getMarkerColor(c) }"
                ></div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1 mb-0.5">
                    <span
                      class="text-xs font-bold truncate transition-colors"
                      :class="(!c.estado || c.estado.toLowerCase() === 'activo') ? 'text-slate-900 group-hover:text-sky-600' : 'text-slate-500 group-hover:text-slate-700'"
                    >
                      {{ c.cliente }}
                    </span>
                    <span
                      class="text-[10px] font-mono px-1.5 py-0.2 rounded shrink-0 font-bold border"
                      :class="(!c.estado || c.estado.toLowerCase() === 'activo') ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-100 border-slate-200'"
                    >
                      {{ c.distancia_metros }} m
                    </span>
                  </div>

                  <p
                    class="text-[11px] truncate mb-1"
                    :class="(!c.estado || c.estado.toLowerCase() === 'activo') ? 'text-slate-500' : 'text-slate-400'"
                  >
                    {{ c.direccion || 'Sin direccion registrada' }}
                  </p>

                  <div class="flex items-center gap-1.5 flex-wrap text-[10px] font-mono">
                    <span
                      class="px-1.5 py-0.2 rounded font-semibold"
                      :class="(!c.estado || c.estado.toLowerCase() === 'activo') ? 'bg-slate-100 text-slate-700' : 'bg-slate-100 text-slate-400'"
                    >
                      {{ formatRuta(c.ruta) }}
                    </span>
                    <span
                      class="px-1.5 py-0.2 rounded text-white font-bold"
                      :style="{ backgroundColor: (!c.estado || c.estado.toLowerCase() === 'activo') ? getCanalColor(c.canal_calculado) : '#94A3B8' }"
                    >
                      {{ c.canal_calculado }}
                    </span>
                    <span
                      v-if="c.dia_norm"
                      class="px-1.5 py-0.2 rounded font-semibold"
                      :class="(!c.estado || c.estado.toLowerCase() === 'activo') ? 'bg-slate-100 text-slate-600' : 'bg-slate-100 text-slate-400'"
                    >
                      {{ c.dia_norm }}
                    </span>
                    <span
                      class="px-1 py-0.2 rounded font-bold"
                      :class="(!c.estado || c.estado.toLowerCase() === 'activo') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-200/80 text-slate-500 border border-slate-300'"
                    >
                      {{ (!c.estado || c.estado.toLowerCase() === 'activo') ? 'Activo' : 'Inactivo' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Vista 2: Expediente Detallado del Cliente -->
          <template v-else>
            <!-- Cabecera del Expediente -->
            <div class="p-3 border-b border-slate-200 bg-white space-y-2.5">
              <div class="flex items-center justify-between">
                <button
                  type="button"
                  @click="cerrarExpediente"
                  class="text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                  </svg>
                  <span>Volver al listado</span>
                </button>

                <div class="flex items-center gap-2">
                  <!-- Boton para ver en mapa en movil -->
                  <button
                    type="button"
                    @click="verClienteEnMapa(clienteSeleccionado)"
                    class="md:hidden text-xs font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 px-2.5 py-1 rounded-lg flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors"
                    title="Centrar y visualizar cliente en el mapa"
                  >
                    <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
                    </svg>
                    <span>Ver en mapa</span>
                  </button>

                  <button
                    type="button"
                    @click="cerrarExpediente"
                    class="text-slate-400 hover:text-slate-600 font-bold text-sm cursor-pointer p-1 rounded-md"
                    title="Cerrar expediente"
                  >
                    &times;
                  </button>
                </div>
              </div>

              <div>
                <span class="text-[10px] font-mono text-slate-400">ID: {{ clienteSeleccionado.cliente_id }}</span>
                <h3 class="text-sm font-bold text-slate-900 leading-tight">
                  {{ clienteSeleccionado.cliente }}
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ clienteSeleccionado.direccion || 'Sin direccion registrada' }}
                </p>
                <div class="flex items-center gap-2 mt-2">
                  <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded bg-slate-900 text-white shadow-2xs">
                    {{ formatRuta(clienteSeleccionado.ruta) }}
                  </span>
                  <span
                    class="text-[9px] font-mono font-bold px-2 py-0.5 rounded text-white shadow-2xs"
                    :style="{ backgroundColor: getCanalColor(clienteSeleccionado.canal_calculado) }"
                  >
                    {{ clienteSeleccionado.canal_calculado }}
                  </span>
                  <span
                    class="text-[9px] font-mono font-bold px-2 py-0.5 rounded shadow-2xs"
                    :class="clienteSeleccionado.estado && clienteSeleccionado.estado.toLowerCase() !== 'activo' ? 'bg-slate-200/80 text-slate-600 border border-slate-300' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                  >
                    {{ clienteSeleccionado.estado && clienteSeleccionado.estado.toLowerCase() !== 'activo' ? 'Inactivo' : 'Activo' }}
                  </span>
                </div>
              </div>

              <!-- Contacto & Celular -->
              <div v-if="clienteSeleccionado.contacto || clienteSeleccionado.celular" class="flex items-center gap-2 text-[11px] text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-200/80">
                <span v-if="clienteSeleccionado.contacto" class="truncate font-medium">{{ clienteSeleccionado.contacto }}</span>
                <a
                  v-if="clienteSeleccionado.celular"
                  :href="`tel:${clienteSeleccionado.celular}`"
                  class="font-mono text-sky-600 hover:underline font-semibold ml-auto flex items-center gap-1"
                >
                  <svg class="w-3 h-3 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                  </svg>
                  <span>{{ clienteSeleccionado.celular }}</span>
                </a>
              </div>

              <!-- Pestanas de Expediente -->
              <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80">
                <button
                  type="button"
                  @click="tabExpediente = 'ventas'"
                  class="flex-1 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer text-center"
                  :class="tabExpediente === 'ventas' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-900'"
                >
                  Compras ({{ anioSeleccionado }})
                </button>
                <button
                  type="button"
                  @click="tabExpediente = 'visitas'"
                  class="flex-1 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer text-center"
                  :class="tabExpediente === 'visitas' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-900'"
                >
                  Visitas & Fotos
                </button>
              </div>
            </div>

            <!-- Contenido Pestana: Ventas -->
            <div v-show="tabExpediente === 'ventas'" class="flex-1 overflow-y-auto p-3 space-y-2.5">
              <div v-if="cargandoVentas" class="py-8 text-center text-xs text-slate-400 animate-pulse">
                Cargando historial de compras...
              </div>
              <div v-else-if="ventasCliente.length === 0" class="py-8 text-center text-xs text-slate-400">
                No hay compras registradas para este periodo.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="v in ventasCliente"
                  :key="v.venta_id"
                  class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs space-y-1.5"
                >
                  <div class="flex items-center justify-between font-mono">
                    <span class="text-slate-500 text-[11px]">{{ v.fecha }} {{ v.hora }}</span>
                    <span class="font-bold text-slate-900">Bs. {{ v.monto_ticket.toFixed(2) }}</span>
                  </div>
                  <div class="text-[11px] text-slate-600">
                    <span class="text-slate-400">Factura:</span> {{ v.nro_factura || 'S/N' }} • <span class="text-slate-400">Vendedor:</span> {{ v.vendedor }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Contenido Pestana: Visitas -->
            <div v-show="tabExpediente === 'visitas'" class="flex-1 overflow-y-auto p-3 space-y-2.5">
              <div v-if="cargandoVisitas" class="py-8 text-center text-xs text-slate-400 animate-pulse">
                Cargando historial de visitas...
              </div>
              <div v-else-if="visitasCliente.length === 0" class="py-8 text-center text-xs text-slate-400">
                Sin visitas registradas para este cliente.
              </div>
              <div v-else class="space-y-2">
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
        </aside>

        <!-- Panel del Mapa Leaflet Interactivo -->
        <div
          class="flex-1 relative min-h-[350px] md:min-h-0 bg-slate-950 h-full w-full"
          :class="vistaMovil === 'mapa' ? 'block' : 'hidden md:block'"
        >
          <div id="map-cercanos" class="absolute inset-0 w-full h-full z-10"></div>

          <!-- Selector de Capas de Mapa -->
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

          <!-- Tarjeta Flotante Inferior: Expediente Rapido del Cliente (Movil) -->
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform translate-y-4 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-4 opacity-0"
          >
            <div
              v-if="clienteSeleccionado && vistaMovil === 'mapa'"
              class="md:hidden absolute bottom-3 left-2 right-2 sm:left-4 sm:right-4 z-40 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl p-3 shadow-2xl flex flex-col gap-2"
            >
              <!-- Fila 1: Cabecera con Nombre, Estado, Distancia y Boton Cerrar -->
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-start gap-2 min-w-0 flex-1">
                  <div
                    class="w-3 h-3 rounded-full mt-1 shrink-0 shadow-2xs"
                    :style="{ backgroundColor: getMarkerColor(clienteSeleccionado) }"
                  ></div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <span class="text-xs sm:text-sm font-bold text-slate-900 truncate">
                        {{ clienteSeleccionado.cliente }}
                      </span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-mono truncate">
                      ID: {{ clienteSeleccionado.cliente_id }} • {{ clienteSeleccionado.direccion || 'Sin dirección registrada' }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-1 shrink-0">
                  <span
                    class="text-[10.5px] font-mono font-bold px-2 py-0.5 rounded-md border"
                    :class="(!clienteSeleccionado.estado || clienteSeleccionado.estado.toLowerCase() === 'activo') ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-100 border-slate-200'"
                  >
                    {{ clienteSeleccionado.distancia_metros }} m
                  </span>
                  <button
                    type="button"
                    @click="cerrarExpediente"
                    class="p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors"
                    title="Cerrar selección"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Fila 2: Chips de Clasificacion y Estado -->
              <div class="flex items-center gap-1.5 flex-wrap text-[10px] font-mono">
                <span class="px-2 py-0.5 rounded font-bold bg-slate-900 text-white shadow-2xs">
                  {{ formatRuta(clienteSeleccionado.ruta) }}
                </span>
                <span
                  class="px-2 py-0.5 rounded text-white font-bold shadow-2xs"
                  :style="{ backgroundColor: getCanalColor(clienteSeleccionado.canal_calculado) }"
                >
                  {{ clienteSeleccionado.canal_calculado }}
                </span>
                <span
                  v-if="clienteSeleccionado.dia_norm"
                  class="px-2 py-0.5 rounded font-semibold bg-slate-100 text-slate-700 border border-slate-200"
                >
                  {{ clienteSeleccionado.dia_norm }}
                </span>
                <span
                  class="px-1.5 py-0.5 rounded font-bold"
                  :class="(!clienteSeleccionado.estado || clienteSeleccionado.estado.toLowerCase() === 'activo') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-200/80 text-slate-500 border border-slate-300'"
                >
                  {{ (!clienteSeleccionado.estado || clienteSeleccionado.estado.toLowerCase() === 'activo') ? 'Activo' : 'Inactivo' }}
                </span>
              </div>

              <!-- Fila 3: Acciones Ejecutivas -->
              <div class="flex items-center gap-1.5 pt-1 border-t border-slate-100">
                <button
                  type="button"
                  @click="vistaMovil = 'lista'"
                  class="flex-1 h-8 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition-all shadow-xs cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                  <span>Ver Expediente Completo</span>
                </button>

                <a
                  v-if="clienteSeleccionado.celular"
                  :href="`tel:${clienteSeleccionado.celular}`"
                  class="h-8 px-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-semibold flex items-center gap-1 transition-all cursor-pointer shrink-0"
                  title="Llamar al cliente"
                >
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                  </svg>
                  <span>Llamar</span>
                </a>

                <a
                  v-if="clienteSeleccionado.latitud && clienteSeleccionado.longitud"
                  :href="`https://www.google.com/maps?q=${clienteSeleccionado.latitud},${clienteSeleccionado.longitud}`"
                  target="_blank"
                  class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl text-xs font-semibold flex items-center gap-1 transition-all cursor-pointer shrink-0"
                  title="Abrir en Google Maps"
                >
                  <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                  </svg>
                  <span>Maps</span>
                </a>
              </div>
            </div>
          </Transition>

          <!-- Boton Flotante para Abrir Leyenda cuando esta Colapsada -->
          <button
            v-if="clientesFiltrados.length > 0 && leyendaColapsada"
            type="button"
            @click="leyendaColapsada = false"
            class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl px-2.5 py-1.5 shadow-xl z-30 text-xs items-center gap-1.5 hover:bg-slate-50 cursor-pointer transition-all active:scale-95"
            :class="clienteSeleccionado && vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex'"
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

          <!-- Leyenda Dinamica en Tiempo Real (Expandida) -->
          <div
            v-if="clientesFiltrados.length > 0 && !leyendaColapsada"
            class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-2.5 sm:p-3 shadow-xl z-30 text-xs w-[220px] sm:w-72 max-h-44 sm:max-h-64 flex-col"
            :class="clienteSeleccionado && vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex'"
          >
            <div class="flex items-center justify-between pb-1 mb-1.5 border-b border-slate-100 shrink-0 gap-1.5">
              <div class="min-w-0 flex-1">
                <p class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono truncate">
                  Codificacion: {{ modoColor.toUpperCase() }}
                </p>
                <span class="text-[9px] font-mono text-slate-500">{{ elementosLeyenda.length }} tipos</span>
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

      <!-- ==================================================== -->
      <!-- DRAWER / BOTTOM SHEET MOVIL: FILTROS & FRONTERAS     -->
      <!-- ==================================================== -->
      <Teleport to="body">
        <div
          v-if="drawerFiltrosMovil"
          class="fixed inset-0 z-50 flex flex-col justify-end bg-slate-900/60 backdrop-blur-xs transition-opacity md:hidden"
          @click.self="drawerFiltrosMovil = false"
        >
          <div class="bg-white rounded-t-2xl shadow-2xl border-t border-slate-200 p-4 max-h-[85vh] overflow-y-auto custom-scroll space-y-4">
            <!-- Header Drawer -->
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
              <div>
                <h4 class="font-bold text-slate-900 text-sm">Filtros & Fronteras</h4>
                <p class="text-[11px] text-slate-500">Configura radio, colores, canal, rutas y fronteras</p>
              </div>
              <button
                type="button"
                @click="drawerFiltrosMovil = false"
                class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer text-lg font-bold"
              >
                &times;
              </button>
            </div>

            <!-- Seccion 1: Radio de Busqueda -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-800 font-mono uppercase tracking-wider">Radio de Busqueda</label>
              <div class="grid grid-cols-3 gap-1.5">
                <button
                  v-for="r in opcionesRadio"
                  :key="r.valor"
                  type="button"
                  @click="seleccionarRadio(r.valor)"
                  class="py-1.5 px-2 rounded-lg text-xs font-mono font-bold transition-all border cursor-pointer text-center"
                  :class="radioSeleccionado === r.valor ? 'bg-sky-600 text-white border-sky-600 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                >
                  {{ r.etiqueta }}
                </button>
              </div>
            </div>

            <!-- Seccion 2: Modo de Coloracion -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-800 font-mono uppercase tracking-wider">Color de Clientes</label>
              <div class="grid grid-cols-3 gap-1.5">
                <button
                  type="button"
                  @click="setModoColor('ruta')"
                  class="py-1.5 px-2 rounded-lg text-xs font-medium transition-all border cursor-pointer text-center"
                  :class="modoColor === 'ruta' ? 'bg-slate-900 text-white border-slate-900 font-bold shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                >
                  Ruta
                </button>
                <button
                  type="button"
                  @click="setModoColor('canal')"
                  class="py-1.5 px-2 rounded-lg text-xs font-medium transition-all border cursor-pointer text-center"
                  :class="modoColor === 'canal' ? 'bg-slate-900 text-white border-slate-900 font-bold shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                >
                  Canal
                </button>
                <button
                  type="button"
                  @click="setModoColor('dia')"
                  class="py-1.5 px-2 rounded-lg text-xs font-medium transition-all border cursor-pointer text-center"
                  :class="modoColor === 'dia' ? 'bg-slate-900 text-white border-slate-900 font-bold shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                >
                  Dia
                </button>
              </div>
            </div>

            <!-- Seccion 3: Filtro de Canal -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-800 font-mono uppercase tracking-wider">Filtro de Canal</label>
                <span class="text-xs font-mono font-bold text-amber-700">{{ labelCanales }}</span>
              </div>

              <!-- Acciones rapidas Canal -->
              <div class="flex items-center gap-1.5 text-xs">
                <button
                  type="button"
                  @click="seleccionarSoloCanalTDB"
                  class="px-2 py-1 rounded-md text-xs font-bold border transition-colors cursor-pointer"
                  :class="filtroCanales.length === 1 && filtroCanales[0] === 'TDB' ? 'bg-amber-500 text-white border-amber-600' : 'bg-amber-50 text-amber-900 border-amber-200'"
                >
                  Solo TDB
                </button>
                <button
                  type="button"
                  @click="seleccionarTodosCanales"
                  class="px-2 py-1 rounded-md text-xs font-medium border bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 transition-colors cursor-pointer"
                >
                  Todos
                </button>
                <button
                  type="button"
                  @click="limpiarCanales"
                  class="px-2 py-1 rounded-md text-xs font-medium text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer ml-auto"
                >
                  Limpiar
                </button>
              </div>

              <div class="space-y-1">
                <label
                  v-for="canal in canalesDisponibles"
                  :key="canal"
                  class="flex items-center justify-between p-2 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer text-xs font-mono transition-colors"
                  :class="filtroCanales.includes(canal) ? 'bg-amber-50/60 border-amber-300 font-semibold text-slate-900' : 'bg-white text-slate-700'"
                >
                  <div class="flex items-center gap-2">
                    <input
                      type="checkbox"
                      :checked="filtroCanales.includes(canal)"
                      @change="toggleCanal(canal)"
                      class="rounded border-slate-300 text-amber-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(canal) }"></span>
                    <span>{{ canal }}</span>
                  </div>
                  <span class="text-[10px] text-slate-400 font-sans">
                    {{ contarRutasPorCanal(canal) }} rutas
                  </span>
                </label>
              </div>
            </div>

            <!-- Seccion 4: Filtro de Rutas -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-800 font-mono uppercase tracking-wider">Filtro de Rutas</label>
                <span class="text-xs font-mono font-bold text-indigo-700">{{ labelRutas }}</span>
              </div>

              <div class="flex items-center justify-between text-xs">
                <button
                  type="button"
                  @click="seleccionarTodasRutas"
                  class="text-indigo-600 hover:underline font-semibold cursor-pointer text-[11px]"
                >
                  Todas las Rutas
                </button>
                <button
                  v-if="filtroRutas.length > 0"
                  type="button"
                  @click="limpiarFiltroRutas"
                  class="text-rose-600 hover:underline font-medium cursor-pointer text-[11px]"
                >
                  Limpiar seleccion
                </button>
              </div>

              <input
                v-model="busquedaFiltroRutas"
                type="text"
                placeholder="Buscar ruta o canal..."
                class="w-full h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:bg-white font-mono"
              />

              <div class="max-h-40 overflow-y-auto custom-scroll border border-slate-200 rounded-lg divide-y divide-slate-100">
                <div v-if="rutasFiltradasBusqueda.length === 0" class="py-3 text-center text-slate-400 text-xs font-sans">
                  No hay rutas coincidentes
                </div>
                <label
                  v-for="r in rutasFiltradasBusqueda"
                  :key="r"
                  class="flex items-center justify-between p-2 hover:bg-slate-50 cursor-pointer text-xs font-mono transition-colors"
                  :class="filtroRutas.includes(r) ? 'bg-indigo-50/50 font-semibold' : ''"
                >
                  <div class="flex items-center gap-2 truncate min-w-0">
                    <input
                      type="checkbox"
                      :checked="filtroRutas.includes(r)"
                      @change="toggleFiltroRuta(r)"
                      class="rounded border-slate-300 text-indigo-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(r) }"></span>
                    <span class="text-slate-800 truncate">{{ formatRuta(r) }}</span>
                  </div>
                  <span
                    v-if="getCanalDeRuta(r)"
                    class="text-[9.5px] px-1.5 py-0.2 rounded font-mono font-bold shrink-0"
                    :class="getCanalDeRuta(r) === 'TDB' ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ getCanalDeRuta(r) }}
                  </span>
                </label>
              </div>
            </div>

            <!-- Seccion 5: Fronteras Rutas -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-800 font-mono uppercase tracking-wider">Fronteras Rutas (Polígonos)</label>
                <button
                  type="button"
                  @click="verFronterasRutas = !verFronterasRutas"
                  class="text-xs font-bold px-2 py-0.5 rounded cursor-pointer transition-all"
                  :class="verFronterasRutas ? 'bg-purple-100 text-purple-800' : 'bg-slate-200 text-slate-600'"
                >
                  {{ verFronterasRutas ? 'Visible' : 'Oculto' }}
                </button>
              </div>

              <!-- Filtro de Rutas en Fronteras Rutas -->
              <div v-if="verFronterasRutas" class="space-y-1.5">
                <div class="flex items-center justify-between text-xs text-slate-500 flex-wrap gap-1">
                  <span class="font-bold text-purple-700 font-mono text-[11px]">{{ labelFronterasRutas }}</span>
                  <div class="flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="seleccionarRutasTDB"
                      class="px-2 py-0.5 rounded text-[10.5px] font-bold border transition-colors cursor-pointer"
                      :class="labelFronterasRutas.startsWith('TDB') ? 'bg-amber-500 text-white border-amber-600' : 'bg-amber-50 text-amber-900 border-amber-200'"
                    >
                      Solo TDB
                    </button>
                    <button
                      type="button"
                      @click="seleccionarTodasFronterasRutas"
                      class="text-sky-600 hover:underline font-semibold text-[11px] cursor-pointer"
                    >
                      Todas
                    </button>
                    <button
                      type="button"
                      @click="limpiarFronterasRutas"
                      class="text-rose-600 hover:underline font-medium text-[11px] cursor-pointer"
                    >
                      Limpiar
                    </button>
                  </div>
                </div>

                <input
                  v-model="busquedaRutaFronterasRutas"
                  type="text"
                  placeholder="Buscar ruta o canal..."
                  class="w-full h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:bg-white font-mono"
                />

                <div class="max-h-40 overflow-y-auto custom-scroll border border-slate-200 rounded-lg divide-y divide-slate-100">
                  <label
                    v-for="r in rutasFronterasRutasFiltradasBusqueda"
                    :key="r"
                    class="flex items-center justify-between p-2 hover:bg-slate-50 cursor-pointer text-xs font-mono transition-colors"
                    :class="esRutaFronteraRutaActiva(r) ? 'bg-indigo-50/50 font-semibold' : ''"
                  >
                    <div class="flex items-center gap-2 truncate min-w-0">
                      <input
                        type="checkbox"
                        :checked="esRutaFronteraRutaActiva(r)"
                        @change="toggleRutaFronterasRutas(r)"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-0 cursor-pointer"
                      />
                      <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(r) }"></span>
                      <span class="text-slate-800 truncate">{{ formatRuta(r) }}</span>
                    </div>
                    <span
                      v-if="getCanalDeRuta(r)"
                      class="text-[9.5px] px-1.5 py-0.2 rounded font-mono font-bold shrink-0"
                      :class="getCanalDeRuta(r) === 'TDB' ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                    >
                      {{ getCanalDeRuta(r) }}
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Boton de Cierre -->
            <button
              type="button"
              @click="drawerFiltrosMovil = false"
              class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-md text-center"
            >
              Listo / Aplicar
            </button>
          </div>
        </div>
      </Teleport>

      <!-- VISOR LIGHTBOX MODAL CON ROTACION -->
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue';
import { PhotoLightboxModal } from '@/Components/UI';
import { mapsConfig } from '@/Config/maps';
import { getCanalColor, getRutaColor, formatRuta, getDiaColor } from '@/Config/colors';
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
  anios_disponibles: {
    type: Array,
    default: () => [2026],
  },
  anio_default: {
    type: Number,
    default: 2026,
  },
  meses_default: {
    type: Array,
    default: () => [1, 2, 3],
  },
  fronteras_rutas: {
    type: Array,
    default: () => [],
  },
  fronteras_dias: {
    type: Array,
    default: () => [],
  },
});

// Estado de Lightbox con Rotacion
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
    title: clienteSeleccionado.value ? clienteSeleccionado.value.cliente : 'Evidencia de Visita',
    subtitle: `${vis.fecha || ''} ${vis.hora || ''} • Ruta: ${formatRuta(vis.route)} • ${vis.status || 'VISITADO'}`,
    comments: vis.comments || '',
  };
}

const opcionesRadio = [
  { valor: 100, etiqueta: '100 m' },
  { valor: 250, etiqueta: '250 m' },
  { valor: 500, etiqueta: '500 m' },
  { valor: 1000, etiqueta: '1 km' },
  { valor: 2000, etiqueta: '2 km' },
  { valor: 3000, etiqueta: '3 km' },
];

// Estados reactivos generales
const radioSeleccionado = ref(500);
const anioSeleccionado = ref(props.anio_default || new Date().getFullYear());
const filtroMeses = ref([...props.meses_default]);
const modoColor = ref('ruta'); // 'ruta' | 'canal' | 'dia'

const busquedaTexto = ref('');
const obteniendoGps = ref(false);
const cargando = ref(false);
const vistaMovil = ref('mapa');
const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap');

const origenConsulta = ref(null);
const clientesCercanos = ref([]);
const clienteActivoId = ref(null);
const clienteSeleccionado = ref(null);
const tabExpediente = ref('ventas');
const ventasCliente = ref([]);
const visitasCliente = ref([]);
const cargandoVentas = ref(false);
const cargandoVisitas = ref(false);
const leyendaColapsada = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);

// Traductor de Enlaces de Google Maps
const inputEnlaceMaps = ref('');
const resolviendoEnlace = ref(false);
const mensajeEnlace = ref(null);

// ==========================================
// CONTROL DE MENUS DESPLEGABLES
// ==========================================
const menuAbierto = ref(null); // 'canales' | 'rutas' | 'fronteras_rutas' | null

function toggleMenu(nombre) {
  menuAbierto.value = menuAbierto.value === nombre ? null : nombre;
}

// ==========================================
// FILTRO DE CANAL
// ==========================================
const canalesDisponibles = computed(() => {
  if (props.canales && props.canales.length > 0) {
    return [...props.canales].sort();
  }
  const set = new Set();
  (props.catalogo_rutas || []).forEach(r => {
    if (r.canal) set.add(String(r.canal).trim().toUpperCase());
  });
  return Array.from(set).sort();
});

// Por defecto se marca el canal TDB si existe, o el primer canal
const filtroCanales = ref(
  props.canales && props.canales.includes('TDB')
    ? ['TDB']
    : (props.canales && props.canales.length > 0 ? [props.canales[0]] : [])
);

const labelCanales = computed(() => {
  const sel = filtroCanales.value.length;
  const total = canalesDisponibles.value.length;
  if (sel === 0 || (total > 0 && sel === total)) return 'Todos';
  if (sel === 1) return filtroCanales.value[0];
  return `${sel} canales`;
});

function toggleCanal(canal) {
  const c = String(canal).trim().toUpperCase();
  const idx = filtroCanales.value.indexOf(c);
  if (idx > -1) {
    filtroCanales.value.splice(idx, 1);
  } else {
    filtroCanales.value.push(c);
  }
  // Limpiar rutas seleccionadas que ya no correspondan a los canales activos
  if (filtroCanales.value.length > 0 && filtroRutas.value.length > 0) {
    filtroRutas.value = filtroRutas.value.filter(r => {
      const cr = getCanalDeRuta(r);
      return filtroCanales.value.includes(cr);
    });
  }
  // Limpiar rutas de fronteras seleccionadas que ya no correspondan a los canales activos
  if (filtroCanales.value.length > 0 && filtroRutasFronterasRutas.value.length > 0) {
    filtroRutasFronterasRutas.value = filtroRutasFronterasRutas.value.filter(r => {
      const cr = getCanalDeRuta(r);
      return filtroCanales.value.includes(cr);
    });
  }
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function seleccionarSoloCanalTDB() {
  filtroCanales.value = ['TDB'];
  if (filtroRutas.value.length > 0) {
    filtroRutas.value = filtroRutas.value.filter(r => getCanalDeRuta(r) === 'TDB');
  }
  filtroRutasFronterasRutas.value = [...obtenerRutasTDB()];
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function seleccionarTodosCanales() {
  filtroCanales.value = [...canalesDisponibles.value];
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function limpiarCanales() {
  filtroCanales.value = [];
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function contarRutasPorCanal(canal) {
  const norm = String(canal).trim().toUpperCase();
  return (props.catalogo_rutas || []).filter(r => String(r.canal || '').trim().toUpperCase() === norm).length;
}

// ==========================================
// FILTRO DE RUTAS
// ==========================================
const filtroRutas = ref([]);
const busquedaFiltroRutas = ref('');

const rutasDisponiblesCanal = computed(() => {
  let list = props.catalogo_rutas || [];
  if (filtroCanales.value.length > 0) {
    list = list.filter(r => {
      const c = String(r.canal || '').trim().toUpperCase();
      return filtroCanales.value.includes(c);
    });
  }
  const set = new Set();
  list.forEach(r => {
    if (r.ruta) set.add(String(r.ruta).trim());
  });
  if (set.size === 0 && props.fronteras_rutas) {
    props.fronteras_rutas.forEach(f => {
      const c = getCanalDeRuta(f.ruta);
      if (filtroCanales.value.length === 0 || filtroCanales.value.includes(c)) {
        if (f.ruta) set.add(String(f.ruta).trim());
      }
    });
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
});

const rutasFiltradasBusqueda = computed(() => {
  const q = busquedaFiltroRutas.value.trim().toLowerCase();
  if (!q) return rutasDisponiblesCanal.value;
  return rutasDisponiblesCanal.value.filter(r => {
    const canal = getCanalDeRuta(r).toLowerCase();
    return r.toLowerCase().includes(q) || canal.includes(q);
  });
});

const labelRutas = computed(() => {
  const sel = filtroRutas.value.length;
  const total = rutasDisponiblesCanal.value.length;
  if (sel === 0 || (total > 0 && sel === total)) return 'Todas';
  if (sel === 1) return formatRuta(filtroRutas.value[0]);
  return `${sel} rutas`;
});

function toggleFiltroRuta(ruta) {
  const r = String(ruta).trim();
  const idx = filtroRutas.value.indexOf(r);
  if (idx > -1) {
    filtroRutas.value.splice(idx, 1);
  } else {
    filtroRutas.value.push(r);
  }
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function seleccionarTodasRutas() {
  filtroRutas.value = [];
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function limpiarFiltroRutas() {
  filtroRutas.value = [];
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

// ==========================================
// ESTADO DE FRONTERAS RUTAS
// ==========================================
const verFronterasRutas = ref(false);
const busquedaRutaFronterasRutas = ref('');
const drawerFiltrosMovil = ref(false);

function getCanalDeRuta(ruta) {
  if (!ruta) return '';
  const norm = String(ruta).trim().toUpperCase();
  // El catálogo de rutas dim_rutas es la única autoridad canónica para el canal
  const cr = (props.catalogo_rutas || []).find(r => String(r.ruta || '').trim().toUpperCase() === norm);
  if (cr && cr.canal) return String(cr.canal).trim().toUpperCase();
  // Si no existe en dim_rutas, la ruta ya no es válida y no se considera
  return '';
}

function obtenerRutasTDB() {
  const set = new Set();
  (props.fronteras_rutas || []).forEach(f => {
    const canal = getCanalDeRuta(f.ruta);
    if (canal === 'TDB' && f.ruta) {
      set.add(String(f.ruta).trim());
    }
  });
  if (set.size === 0) {
    (props.catalogo_rutas || []).forEach(r => {
      const canal = String(r.canal || '').trim().toUpperCase();
      if (canal === 'TDB' && r.ruta) {
        const tieneFrontera = (props.fronteras_rutas || []).some(
          f => String(f.ruta || '').trim().toUpperCase() === String(r.ruta || '').trim().toUpperCase()
        );
        if (tieneFrontera) set.add(String(r.ruta).trim());
      }
    });
  }
  return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
}

// Por defecto se marcan las fronteras ruta del canal TDB
const filtroRutasFronterasRutas = ref((() => {
  const tdb = obtenerRutasTDB();
  if (tdb.length > 0) return [...tdb];
  return (props.fronteras_rutas || []).map(f => String(f.ruta).trim());
})());

const rutasDisponiblesFronterasRutas = computed(() => {
  const set = new Set();
  (props.fronteras_rutas || []).forEach(f => {
    const canal = getCanalDeRuta(f.ruta);
    if (!canal) return; // Excluir rutas que no existan en dim_rutas
    if (filtroCanales.value.length === 0 || filtroCanales.value.includes(canal)) {
      if (f.ruta) set.add(String(f.ruta).trim());
    }
  });
  return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
});

const rutasFronterasRutasFiltradasBusqueda = computed(() => {
  const q = busquedaRutaFronterasRutas.value.trim().toLowerCase();
  if (!q) return rutasDisponiblesFronterasRutas.value;
  return rutasDisponiblesFronterasRutas.value.filter(r => {
    const canal = getCanalDeRuta(r).toLowerCase();
    return r.toLowerCase().includes(q) || canal.includes(q);
  });
});

function toggleRutaFronterasRutas(ruta) {
  const r = String(ruta).trim();
  const idx = filtroRutasFronterasRutas.value.indexOf(r);
  if (idx > -1) {
    filtroRutasFronterasRutas.value.splice(idx, 1);
  } else {
    filtroRutasFronterasRutas.value.push(r);
    verFronterasRutas.value = true;
  }
}

function esRutaFronteraRutaActiva(ruta) {
  return filtroRutasFronterasRutas.value.includes(String(ruta).trim());
}

function seleccionarRutasTDB() {
  verFronterasRutas.value = true;
  filtroRutasFronterasRutas.value = [...obtenerRutasTDB()];
}

function seleccionarTodasFronterasRutas() {
  verFronterasRutas.value = true;
  filtroRutasFronterasRutas.value = [...rutasDisponiblesFronterasRutas.value];
}

function limpiarFronterasRutas() {
  filtroRutasFronterasRutas.value = [];
}

const labelFronterasRutas = computed(() => {
  const total = rutasDisponiblesFronterasRutas.value.length;
  const sel = filtroRutasFronterasRutas.value.length;
  if (!verFronterasRutas.value || sel === 0) return 'Ocultas (0)';
  if (total > 0 && sel === total) {
    const soloTDB = filtroCanales.value.length === 1 && filtroCanales.value[0] === 'TDB';
    return soloTDB ? `TDB (${total})` : `Todas (${total})`;
  }
  const rutasTDB = obtenerRutasTDB();
  const esExactoTDB = rutasTDB.length > 0 && sel === rutasTDB.length && rutasTDB.every(r => filtroRutasFronterasRutas.value.includes(r));
  if (esExactoTDB) return `TDB (${sel})`;
  if (sel === 1) return formatRuta(filtroRutasFronterasRutas.value[0]);
  return `${sel}/${total} rts`;
});

const fronterasRutasFiltradas = computed(() => {
  if (!verFronterasRutas.value || !props.fronteras_rutas || props.fronteras_rutas.length === 0) {
    return [];
  }
  return props.fronteras_rutas.filter(f => {
    const canal = getCanalDeRuta(f.ruta);
    if (!canal) return false; // Excluir rutas que no existan en dim_rutas
    if (filtroCanales.value.length > 0 && !filtroCanales.value.includes(canal)) {
      return false;
    }
    return filtroRutasFronterasRutas.value.includes(String(f.ruta).trim());
  });
});

let map = null;
let currentTileLayer = null;
let centerMarker = null;
let radiusCircle = null;
let clientsLayer = null;
let fronterasRutasLayer = null;
let highlightLayer = null;
const clientMarkersMap = new Map();

const clientesFiltrados = computed(() => {
  let list = clientesCercanos.value;

  if (filtroCanales.value.length > 0) {
    list = list.filter(c => {
      const canal = c.canal_calculado || getCanalDeRuta(c.ruta);
      return filtroCanales.value.includes(canal);
    });
  }

  if (filtroRutas.value.length > 0) {
    list = list.filter(c => filtroRutas.value.includes(String(c.ruta).trim()));
  }

  if (busquedaTexto.value.trim()) {
    const q = busquedaTexto.value.toLowerCase();
    list = list.filter(c =>
      (c.cliente && c.cliente.toLowerCase().includes(q)) ||
      (c.cliente_id && String(c.cliente_id).includes(q)) ||
      (c.ruta && c.ruta.toLowerCase().includes(q))
    );
  }

  return list;
});

// Elementos dinamicos para la leyenda del mapa
const elementosLeyenda = computed(() => {
  if (clientesFiltrados.value.length === 0) return [];
  const mapCounts = new Map();

  clientesFiltrados.value.forEach(c => {
    let key, nombre, color;
    const esActivo = !c.estado || c.estado.toLowerCase() === 'activo';

    if (!esActivo) {
      key = '__INACTIVO__';
      nombre = 'Inactivos';
      color = '#64748B';
    } else if (modoColor.value === 'ruta') {
      key = c.ruta || 'Sin Ruta';
      nombre = formatRuta(c.ruta);
      color = getRutaColor(c.ruta);
    } else if (modoColor.value === 'dia') {
      key = c.dia_norm || 'Sin Dia';
      nombre = c.dia_norm || 'Sin Dia';
      color = getDiaColor(c.dia_norm);
    } else {
      key = c.canal_calculado || 'General';
      nombre = c.canal_calculado || 'General';
      color = getCanalColor(c.canal_calculado);
    }

    if (!mapCounts.has(key)) {
      mapCounts.set(key, { id: key, nombre, color, conteo: 0, esInactivo: !esActivo });
    }
    mapCounts.get(key).conteo++;
  });

  return Array.from(mapCounts.values()).sort((a, b) => {
    if (a.esInactivo) return 1;
    if (b.esInactivo) return -1;
    return b.conteo - a.conteo;
  });
});

function setModoColor(modo) {
  modoColor.value = modo;
  renderizarEnMapa();
}

function seleccionarRadio(r) {
  radioSeleccionado.value = r;
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Tu dispositivo o navegador no soporta geolocalizacion');
    return;
  }
  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      obteniendoGps.value = false;
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      if (map) {
        map.flyTo([lat, lng], 16);
      }
      consultarCercanos(lat, lng);
    },
    (err) => {
      obteniendoGps.value = false;
      alert('No se pudo obtener tu ubicacion GPS: ' + err.message);
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

function enfocarPuntoReferencia() {
  if (!map || !origenConsulta.value) return;
  if (typeof window !== 'undefined' && window.innerWidth < 768) {
    vistaMovil.value = 'mapa';
  }
  nextTick(() => {
    if (map) {
      map.invalidateSize();
      map.flyTo([Number(origenConsulta.value.lat), Number(origenConsulta.value.lng)], 16, { duration: 0.6 });
      if (centerMarker) {
        centerMarker.openTooltip();
      }
    }
  });
}

async function resolverEnlaceMaps() {
  const val = inputEnlaceMaps.value.trim();
  if (!val) return;

  resolviendoEnlace.value = true;
  mensajeEnlace.value = null;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.cercanos.resolver-enlace')
      : '/supervisor/saneamiento/cercanos/resolver-enlace';

    const res = await axios.post(url, {
      url: val,
    });

    if (res.data && res.data.success) {
      const lat = Number(res.data.latitud);
      const lng = Number(res.data.longitud);

      mensajeEnlace.value = {
        tipo: 'exito',
        texto: `Ubicacion traducida: ${lat.toFixed(5)}, ${lng.toFixed(5)}`,
      };

      if (map) {
        map.flyTo([lat, lng], 16, { duration: 0.8 });
      }

      // Ejecutar la consulta espacial de clientes cercanos alrededor de esa ubicacion
      consultarCercanos(lat, lng);

      setTimeout(() => {
        mensajeEnlace.value = null;
      }, 6000);
    } else {
      mensajeEnlace.value = {
        tipo: 'error',
        texto: res.data?.message || 'No se pudo extraer la ubicacion del enlace.',
      };
    }
  } catch (error) {
    console.error('Error al resolver enlace de Maps:', error);
    mensajeEnlace.value = {
      tipo: 'error',
      texto: error.response?.data?.message || 'Error de comunicacion al traducir enlace.',
    };
  } finally {
    resolviendoEnlace.value = false;
  }
}

async function consultarCercanos(lat, lng) {
  origenConsulta.value = { lat, lng };
  cargando.value = true;
  cerrarExpediente();

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.cercanos.data')
      : '/supervisor/saneamiento/cercanos/data';

    const res = await axios.post(url, {
      latitud: lat,
      longitud: lng,
      radio: radioSeleccionado.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
      meses: filtroMeses.value,
      anio: anioSeleccionado.value,
      solo_activos: false,
    });

    const list = res.data.clientes || [];
    // Mapear canal calculado
    list.forEach(c => {
      const rutaInfo = (props.catalogo_rutas || []).find(r => r.ruta === c.ruta);
      c.canal_calculado = rutaInfo ? rutaInfo.canal : (getCanalDeRuta(c.ruta) || 'GENERAL');
    });

    clientesCercanos.value = list;
    renderizarEnMapa();
  } catch (error) {
    console.error('Error al consultar clientes cercanos:', error);
  } finally {
    cargando.value = false;
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
  const defaultLoc = mapsConfig.defaultLocation;

  map = L.map('map-cercanos', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);
  cambiarCapaMapa(capaActual.value);

  // Orden jerarquico de capas Leaflet:
  // 1. Fronteras Rutas (poligonos de mayor extension)
  // 2. Clientes (puntos y marcadores en radio)
  // 3. Highlight (anillo halo de cliente seleccionado)
  fronterasRutasLayer = L.featureGroup().addTo(map);
  clientsLayer = L.featureGroup().addTo(map);
  highlightLayer = L.featureGroup().addTo(map);

  renderizarFronterasRutas();

  map.on('click', (e) => {
    consultarCercanos(e.latlng.lat, e.latlng.lng);
  });
}

function renderizarFronterasRutas() {
  if (!map) return;
  if (!fronterasRutasLayer) {
    fronterasRutasLayer = L.featureGroup().addTo(map);
  } else {
    fronterasRutasLayer.clearLayers();
  }

  if (!verFronterasRutas.value) return;

  fronterasRutasFiltradas.value.forEach(fr => {
    if (!fr.geojson) return;
    const color = getRutaColor(fr.ruta);
    const layer = L.geoJSON(fr.geojson, {
      style: {
        color: color,
        weight: 2.5,
        fill: false,        // NO pintar el interior de la frontera, solo el polígono (contorno)
        fillOpacity: 0,     // Totalmente transparente el interior
      },
      onEachFeature: (feature, l) => {
        l.bindTooltip(`
          <div style="font-family: inherit; font-size: 11px; line-height: 1.3;">
            <div style="font-weight: bold; color: ${color};">Frontera Ruta: ${formatRuta(fr.ruta)}</div>
            <div style="font-size: 10px; color: #475569;">Canal: ${fr.canal || 'General'}</div>
          </div>
        `, { sticky: true, opacity: 0.95 });

        l.on('mouseover', () => {
          l.setStyle({ weight: 4 }); // Resalta solo el contorno
        });
        l.on('mouseout', () => {
          l.setStyle({ weight: 2.5 });
        });
      }
    });
    layer.addTo(fronterasRutasLayer);
  });
}

function getMarkerColor(c) {
  const esActivo = !c.estado || c.estado.toLowerCase() === 'activo';
  if (!esActivo) {
    return '#64748B'; // Clientes inactivos en gris slate-500 sólido
  }
  if (modoColor.value === 'ruta') {
    return getRutaColor(c.ruta);
  }
  if (modoColor.value === 'canal') {
    return getCanalColor(c.canal_calculado);
  }
  if (modoColor.value === 'dia') {
    return getDiaColor(c.dia_norm);
  }
  return getRutaColor(c.ruta);
}

function actualizarHighlightCliente(c) {
  if (!map || !highlightLayer) return;
  highlightLayer.clearLayers();
  if (!c || !c.latitud || !c.longitud) return;

  const color = getMarkerColor(c);

  // Halo exterior suave y elegante para el cliente activo
  L.circleMarker([c.latitud, c.longitud], {
    radius: 12,
    color: '#0284C7',
    weight: 2.5,
    fillColor: '#38BDF8',
    fillOpacity: 0.35,
    interactive: false,
  }).addTo(highlightLayer);

  // Núcleo concéntrico destacado con borde blanco nítido
  L.circleMarker([c.latitud, c.longitud], {
    radius: 6.5,
    color: '#FFFFFF',
    weight: 2,
    fillColor: color,
    fillOpacity: 1,
    interactive: false,
  }).addTo(highlightLayer);
}

function renderizarEnMapa() {
  if (!map || !origenConsulta.value) return;

  if (centerMarker) {
    map.removeLayer(centerMarker);
    centerMarker = null;
  }
  if (radiusCircle) {
    map.removeLayer(radiusCircle);
    radiusCircle = null;
  }
  clientsLayer.clearLayers();
  if (highlightLayer) highlightLayer.clearLayers();
  clientMarkersMap.clear();

  const centerLat = Number(origenConsulta.value.lat);
  const centerLng = Number(origenConsulta.value.lng);
  const center = [centerLat, centerLng];

  // 1. Círculo perimetral del radio de búsqueda
  radiusCircle = L.circle(center, {
    radius: Number(radioSeleccionado.value || 200),
    color: '#0284C7',
    weight: 2,
    dashArray: '6, 6',
    fillColor: '#38BDF8',
    fillOpacity: 0.08,
    interactive: false,
  }).addTo(map);

  // 2. Marcador distintivo de Punto de Referencia (Ubicación Seleccionada / Enlace Maps)
  const centerIconHtml = `
    <div class="relative flex items-center justify-center cursor-pointer select-none" style="width: 32px; height: 38px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.35));">
      <span class="absolute bottom-0 w-8 h-8 rounded-full bg-rose-500/40 animate-ping -translate-y-1"></span>
      <div class="relative z-10 flex flex-col items-center">
        <div class="w-8 h-8 rounded-full bg-rose-600 border-2 border-white flex items-center justify-center text-white shadow-md">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
          </svg>
        </div>
        <div class="w-2 h-2 bg-rose-700 rounded-full -mt-1 shadow-xs border border-white"></div>
      </div>
    </div>
  `;

  centerMarker = L.marker(center, {
    zIndexOffset: 1500,
    icon: L.divIcon({
      className: 'custom-center-reference-pin !bg-transparent !border-0',
      html: centerIconHtml,
      iconSize: [32, 38],
      iconAnchor: [16, 38],
      tooltipAnchor: [0, -38],
    }),
  }).addTo(map);

  centerMarker.bindTooltip(`
    <div class="font-sans text-xs p-1">
      <div class="font-bold text-slate-900 flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-rose-600"></span>
        <span>Punto de Referencia</span>
      </div>
      <div class="text-[10px] font-mono text-slate-600 mt-0.5">
        ${centerLat.toFixed(6)}, ${centerLng.toFixed(6)}
      </div>
      <div class="text-[10px] text-sky-600 font-semibold mt-0.5">
        Radio activo: ${radioSeleccionado.value} metros
      </div>
    </div>
  `, { direction: 'top', offset: [0, -38], opacity: 0.98 });

  clientesFiltrados.value.forEach(c => {
    const markerColor = getMarkerColor(c);

    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: 5.5,
      fillColor: markerColor,
      color: '#FFFFFF',
      weight: 1.5,
      opacity: 1,
      fillOpacity: 1,
    });

    marker.on('click', (e) => {
      if (e && e.originalEvent) {
        L.DomEvent.stopPropagation(e);
      }
      abrirExpedienteCliente(c, 'mapa');
    });

    marker.addTo(clientsLayer);
    clientMarkersMap.set(c.id, marker);
  });

  // Si había un cliente seleccionado, mantener su halo activo
  if (clienteSeleccionado.value) {
    actualizarHighlightCliente(clienteSeleccionado.value);
  }
}

async function abrirExpedienteCliente(c, origen = 'lista') {
  clienteActivoId.value = c.id;
  clienteSeleccionado.value = c;
  tabExpediente.value = 'ventas';
  ventasCliente.value = [];
  visitasCliente.value = [];

  // Actualizar resaltado de halo en el mapa
  actualizarHighlightCliente(c);

  // En móvil: solo cambiar de vista si el clic provino de la lista.
  // Si provino del mapa, mantener al usuario en el mapa con la tarjeta flotante inferior.
  if (origen === 'lista' && typeof window !== 'undefined' && window.innerWidth < 768) {
    vistaMovil.value = 'lista';
  }

  // Centrado suave en el mapa sin alterar bruscamente el zoom
  if (map && c.latitud && c.longitud) {
    const currentZoom = map.getZoom();
    const targetZoom = currentZoom < 16 ? 16 : currentZoom;
    map.flyTo([c.latitud, c.longitud], targetZoom, { duration: 0.5 });
  }

  // Cargar ventas
  cargandoVentas.value = true;
  try {
    const resVentas = await axios.post(`/supervisor/saneamiento/cercanos/cliente/${c.cliente_id}/ventas`, {
      meses: filtroMeses.value,
      anio: anioSeleccionado.value,
    });
    ventasCliente.value = resVentas.data.ventas || [];
  } catch (e) {
    console.error('Error al cargar ventas:', e);
  } finally {
    cargandoVentas.value = false;
  }

  // Cargar visitas
  cargandoVisitas.value = true;
  try {
    const resVisitas = await axios.post(`/supervisor/saneamiento/cercanos/cliente/${c.cliente_id}/visitas`);
    visitasCliente.value = resVisitas.data.visitas || [];
  } catch (e) {
    console.error('Error al cargar visitas:', e);
  } finally {
    cargandoVisitas.value = false;
  }
}

function cerrarExpediente() {
  clienteSeleccionado.value = null;
  clienteActivoId.value = null;
  if (highlightLayer) {
    highlightLayer.clearLayers();
  }
}

function verClienteEnMapa(c) {
  vistaMovil.value = 'mapa';
  nextTick(() => {
    if (map) {
      map.invalidateSize();
      if (c && c.latitud && c.longitud) {
        map.panTo([c.latitud, c.longitud], { animate: true, duration: 0.4 });
        actualizarHighlightCliente(c);
      }
    }
  });
}

watch([verFronterasRutas, filtroRutasFronterasRutas, filtroCanales], () => {
  renderizarFronterasRutas();
}, { deep: true });

watch(clientesFiltrados, () => {
  renderizarEnMapa();
});

watch(vistaMovil, (newVal) => {
  if (newVal === 'mapa' && map) {
    nextTick(() => {
      map.invalidateSize();
    });
  }
});

onMounted(async () => {
  await nextTick();
  initMap();
  setTimeout(() => {
    if (map) map.invalidateSize();
  }, 200);
});

onUnmounted(() => {
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<style>
.custom-center-reference-pin {
  background: transparent !important;
  border: none !important;
}
</style>
