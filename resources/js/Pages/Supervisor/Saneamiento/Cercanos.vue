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
              Lista ({{ clientesCercanos.length }})
            </button>
          </div>

          <!-- Acciones Derecha: Boton Drawer Filtros/Fronteras y Puntos en radio -->
          <div class="flex items-center gap-1.5 shrink-0">
            <button
              type="button"
              @click="drawerFiltrosMovil = true"
              class="h-7 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 rounded-lg text-xs font-medium flex items-center gap-1.5 cursor-pointer shadow-2xs transition-all"
              title="Ajustar radio, color y fronteras dias"
            >
              <svg class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
              </svg>
              <span>Filtros</span>
              <span v-if="verFronterasDias" class="w-2 h-2 rounded-full bg-sky-500"></span>
            </button>

            <div class="h-7 px-2 bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-1 text-xs font-mono font-bold text-slate-900 shadow-2xs">
              <span class="text-slate-400 font-normal text-[10px]">Radio:</span>
              <span class="tabular-nums text-xs">{{ clientesCercanos.length }}</span>
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
        </div>

        <!-- Fila 3: Quick Bar Tactil de Fronteras Dias (L | M | M | J | V | S | D) -->
        <div class="flex items-center gap-1 overflow-x-auto pb-0.5 pt-0.5 custom-scroll text-xs">
          <!-- Toggle General On/Off -->
          <button
            type="button"
            @click="verFronterasDias = !verFronterasDias"
            class="h-6 px-2 rounded-md font-mono text-[10.5px] font-bold flex items-center gap-1 transition-all shrink-0 cursor-pointer border"
            :class="verFronterasDias ? 'bg-sky-50 text-sky-700 border-sky-300 shadow-2xs' : 'bg-slate-100 text-slate-400 border-slate-200'"
            title="Activar o desactivar poligonos de fronteras dias"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="verFronterasDias ? 'bg-sky-600' : 'bg-slate-400'"></span>
            <span>F. Dias</span>
          </button>

          <!-- Chips de Dias de la Semana -->
          <div v-if="verFronterasDias" class="flex items-center gap-1 shrink-0">
            <button
              v-for="dia in DIAS_SEMANA_CONFIG"
              :key="dia.key"
              type="button"
              @click="toggleDiaFrontera(dia.key)"
              class="h-6 w-6 rounded-md font-mono text-[10px] font-bold flex items-center justify-center transition-all cursor-pointer border"
              :style="esDiaFronteraActivo(dia.key) ? { backgroundColor: dia.bg, borderColor: dia.color, color: dia.text } : { backgroundColor: '#F1F5F9', borderColor: '#E2E8F0', color: '#94A3B8' }"
              :title="`Alternar visualizacion de frontera dia ${dia.label}`"
            >
              {{ dia.corto.charAt(0) }}
            </button>

            <!-- Reset / Todos -->
            <button
              type="button"
              @click="toggleTodosDiasFrontera"
              class="h-6 px-1.5 rounded-md font-mono text-[9.5px] font-bold text-slate-600 bg-slate-100 border border-slate-200 hover:bg-slate-200 transition-all cursor-pointer"
              title="Seleccionar todos o ningun dia"
            >
              {{ filtroDiasFrontera.length === DIAS_SEMANA_CONFIG.length ? 'Ninguno' : 'Todos' }}
            </button>
          </div>
          <div v-else class="text-[10.5px] text-slate-400 italic px-1">
            Poligonos de fronteras dias desactivados
          </div>
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
      <div class="hidden md:flex bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-4 py-2 items-center justify-between gap-2.5 shrink-0 relative z-30">
        <div class="flex items-center gap-2.5 flex-wrap py-0.5 flex-1 min-w-0">
          <!-- Boton de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-3 bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-200/90 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all shrink-0 disabled:opacity-50 cursor-pointer shadow-2xs"
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
              placeholder="Pegar enlace Google Maps o coords..."
              class="h-8 pl-8 pr-12 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200/90 rounded-lg text-xs font-mono text-slate-800 placeholder:text-slate-400 placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500 w-60 lg:w-68 transition-all"
              title="Pega enlaces cortos de Google Maps, URLs completas o coordenadas (-16.505, -68.165)"
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
            class="px-2.5 py-1 rounded-lg text-[11px] font-medium border flex items-center gap-1.5 transition-all shadow-xs shrink-0"
            :class="mensajeEnlace.tipo === 'exito' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
          >
            <span>{{ mensajeEnlace.texto }}</span>
            <button type="button" @click="mensajeEnlace = null" class="ml-1 text-slate-400 hover:text-slate-600 font-bold cursor-pointer">&times;</button>
          </div>

          <!-- Selector Radio -->
          <div class="flex items-center h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs text-slate-800 shrink-0">
            <span class="text-slate-400 mr-1.5 font-mono">Radio:</span>
            <select
              v-model="radioSeleccionado"
              @change="seleccionarRadio(Number(radioSeleccionado))"
              class="bg-transparent font-bold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer text-slate-900"
            >
              <option v-for="r in opcionesRadio" :key="r.valor" :value="r.valor">{{ r.etiqueta }}</option>
            </select>
          </div>

          <!-- Selector de Modo de Coloracion (Ruta | Canal | Dia) -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0">
            <span class="text-[10px] font-mono text-slate-400 px-1.5">Color:</span>
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
              @click="setModoColor('canal')"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'canal' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Canal
            </button>
            <button
              type="button"
              @click="setModoColor('dia')"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="modoColor === 'dia' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              Dia
            </button>
          </div>

          <!-- SELECCIONADOR DE FRONTERAS DIAS EN DESKTOP -->
          <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0">
            <button
              type="button"
              @click="verFronterasDias = !verFronterasDias"
              class="px-2 py-0.5 text-[11px] font-medium rounded-md transition-all cursor-pointer flex items-center gap-1.5"
              :class="verFronterasDias ? 'bg-white text-sky-800 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
              title="Activar o desactivar visualizacion de Fronteras Dias"
            >
              <span class="w-2 h-2 rounded-full" :class="verFronterasDias ? 'bg-sky-500' : 'bg-slate-300'"></span>
              <span>Fronteras Dias</span>
              <span v-if="verFronterasDias" class="text-[9.5px] px-1 py-0.2 rounded bg-sky-100 text-sky-800 font-mono font-bold">
                {{ filtroDiasFrontera.length }}/{{ DIAS_SEMANA_CONFIG.length }}
              </span>
            </button>

            <!-- Chips de Dias Inline si estan activas -->
            <div v-if="verFronterasDias" class="flex items-center gap-0.5 ml-1 pl-1 border-l border-slate-200">
              <button
                v-for="dia in DIAS_SEMANA_CONFIG"
                :key="dia.key"
                type="button"
                @click="toggleDiaFrontera(dia.key)"
                class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold transition-all cursor-pointer border"
                :style="esDiaFronteraActivo(dia.key) ? { backgroundColor: dia.bg, borderColor: dia.color, color: dia.text } : { backgroundColor: '#F1F5F9', borderColor: 'transparent', color: '#94A3B8' }"
                :title="`Alternar ${dia.label}`"
              >
                {{ dia.corto }}
              </button>

              <!-- Dropdown Selector de Rutas de Fronteras -->
              <div class="relative ml-0.5">
                <button
                  type="button"
                  @click="dropdownRutasFronteraAbierto = !dropdownRutasFronteraAbierto"
                  class="px-1.5 py-0.5 rounded text-[10.5px] font-mono font-medium transition-all cursor-pointer flex items-center gap-1 border"
                  :class="filtroRutasFrontera.length > 0 ? 'bg-amber-50 text-amber-800 border-amber-300 font-bold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                  title="Filtrar que rutas de fronteras dias mostrar"
                >
                  <span>{{ filtroRutasFrontera.length === 0 ? 'Todas Rutas' : `${filtroRutasFrontera.length} rutas` }}</span>
                  <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>

                <!-- Popover de Rutas -->
                <div
                  v-if="dropdownRutasFronteraAbierto"
                  class="absolute top-full left-0 mt-1 w-56 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 text-xs flex flex-col gap-1.5"
                >
                  <div class="flex items-center justify-between pb-1 border-b border-slate-100">
                    <span class="font-bold text-slate-800 text-[11px]">Rutas Fronteras</span>
                    <button
                      type="button"
                      @click="dropdownRutasFronteraAbierto = false"
                      class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer"
                    >
                      &times;
                    </button>
                  </div>

                  <input
                    v-model="busquedaRutaFrontera"
                    type="text"
                    placeholder="Buscar ruta..."
                    class="h-7 px-2 bg-slate-50 border border-slate-200 rounded-md text-xs placeholder:text-slate-400 focus:outline-none focus:bg-white"
                  />

                  <div class="flex items-center justify-between text-[10px] text-slate-500 font-medium pt-0.5">
                    <button type="button" @click="seleccionarTodasRutasFronteras" class="text-sky-600 hover:underline cursor-pointer">
                      Ver Todas (Sin filtro)
                    </button>
                    <button v-if="filtroRutasFrontera.length > 0" type="button" @click="filtroRutasFrontera = []" class="text-rose-600 hover:underline cursor-pointer">
                      Limpiar
                    </button>
                  </div>

                  <div class="max-h-40 overflow-y-auto custom-scroll space-y-0.5 pr-1">
                    <label
                      v-for="r in rutasFronterasFiltradasBusqueda"
                      :key="r"
                      class="flex items-center gap-1.5 px-1.5 py-1 rounded hover:bg-slate-50 cursor-pointer font-mono text-[11px]"
                    >
                      <input
                        type="checkbox"
                        :checked="filtroRutasFrontera.includes(r)"
                        @change="toggleRutaFrontera(r)"
                        class="rounded border-slate-300 text-sky-600 focus:ring-0 cursor-pointer"
                      />
                      <span class="text-slate-700">{{ formatRuta(r) }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Indicador de Carga y Contador de Puntos en Radio -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-slate-500 flex items-center gap-1.5 animate-pulse mr-1">
            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
            Rastreando...
          </span>

          <div class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg flex items-center gap-1.5 shadow-2xs">
            <span class="text-slate-400">En radio:</span>
            <span class="font-bold text-slate-900 tabular-nums">{{ clientesCercanos.length }} pts</span>
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

              <div
                v-for="c in clientesFiltrados"
                :key="c.id"
                @click="abrirExpedienteCliente(c)"
                class="p-3 hover:bg-slate-50/80 cursor-pointer transition-all flex items-start space-x-3 group relative"
                :class="clienteActivoId === c.id ? 'bg-sky-50/60 border-l-4 border-sky-500' : ''"
              >
                <!-- Indicador de Color Segun Modo -->
                <div
                  class="w-3 h-3 rounded-full mt-1 shrink-0 shadow-2xs"
                  :style="{ backgroundColor: getMarkerColor(c) }"
                ></div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1 mb-0.5">
                    <span class="text-xs font-bold text-slate-900 truncate group-hover:text-sky-600 transition-colors">
                      {{ c.cliente }}
                    </span>
                    <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200 shrink-0 font-bold">
                      {{ c.distancia_metros }} m
                    </span>
                  </div>

                  <p class="text-[11px] text-slate-500 truncate mb-1">
                    {{ c.direccion || 'Sin direccion registrada' }}
                  </p>

                  <div class="flex items-center gap-1.5 flex-wrap text-[10px] font-mono">
                    <span class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 font-semibold">
                      {{ formatRuta(c.ruta) }}
                    </span>
                    <span
                      class="px-1.5 py-0.2 rounded text-white font-bold"
                      :style="{ backgroundColor: getCanalColor(c.canal_calculado) }"
                    >
                      {{ c.canal_calculado }}
                    </span>
                    <span v-if="c.dia_norm" class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 font-semibold">
                      {{ c.dia_norm }}
                    </span>
                    <span
                      class="px-1 py-0.2 rounded font-bold"
                      :class="c.estado && c.estado.toLowerCase() !== 'activo' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                    >
                      {{ c.estado && c.estado.toLowerCase() !== 'activo' ? 'Inactivo' : 'Activo' }}
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
                    @click="vistaMovil = 'mapa'"
                    class="md:hidden text-xs font-semibold text-sky-600 hover:text-sky-800 flex items-center gap-1 cursor-pointer"
                  >
                    <span>Ver en mapa</span>
                  </button>

                  <button
                    type="button"
                    @click="cerrarExpediente"
                    class="text-slate-400 hover:text-slate-600 font-bold text-sm cursor-pointer"
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
                    :class="clienteSeleccionado.estado && clienteSeleccionado.estado.toLowerCase() !== 'activo' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
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

          <!-- Boton Flotante para Abrir Leyenda cuando esta Colapsada -->
          <button
            v-if="clientesCercanos.length > 0 && leyendaColapsada"
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

          <!-- Leyenda Dinamica en Tiempo Real (Expandida) -->
          <div
            v-if="clientesCercanos.length > 0 && !leyendaColapsada"
            class="absolute bottom-3 right-3 sm:bottom-4 sm:right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-2.5 sm:p-3 shadow-xl z-30 text-xs w-[220px] sm:w-72 max-h-44 sm:max-h-64 flex flex-col"
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
                <h4 class="font-bold text-slate-900 text-sm">Filtros & Fronteras Dias</h4>
                <p class="text-[11px] text-slate-500">Configura parametros espaciales y visualizacion de territorios</p>
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

            <!-- Seccion 3: Fronteras Dias -->
            <div class="space-y-2 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-800 font-mono uppercase tracking-wider">Fronteras Dias (Poligonos)</label>
                <button
                  type="button"
                  @click="verFronterasDias = !verFronterasDias"
                  class="text-xs font-bold px-2 py-0.5 rounded cursor-pointer transition-all"
                  :class="verFronterasDias ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                >
                  {{ verFronterasDias ? 'Activado' : 'Desactivado' }}
                </button>
              </div>

              <!-- Selector de Dias Detallado -->
              <div v-if="verFronterasDias" class="space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-500">
                  <span>Seleccionar dias visibles:</span>
                  <button
                    type="button"
                    @click="toggleTodosDiasFrontera"
                    class="text-sky-600 hover:underline font-semibold cursor-pointer"
                  >
                    {{ filtroDiasFrontera.length === DIAS_SEMANA_CONFIG.length ? 'Desmarcar todos' : 'Marcar todos' }}
                  </button>
                </div>

                <div class="grid grid-cols-2 gap-1.5">
                  <button
                    v-for="dia in DIAS_SEMANA_CONFIG"
                    :key="dia.key"
                    type="button"
                    @click="toggleDiaFrontera(dia.key)"
                    class="py-2 px-2.5 rounded-lg text-xs font-semibold flex items-center justify-between transition-all border cursor-pointer"
                    :style="esDiaFronteraActivo(dia.key) ? { backgroundColor: dia.bg, borderColor: dia.color, color: dia.text } : { backgroundColor: '#F8FAFC', borderColor: '#E2E8F0', color: '#94A3B8' }"
                  >
                    <div class="flex items-center gap-1.5">
                      <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: dia.color }"></span>
                      <span>{{ dia.label }}</span>
                    </div>
                    <span class="font-mono text-[10px]">{{ esDiaFronteraActivo(dia.key) ? 'Visible' : 'Oculto' }}</span>
                  </button>
                </div>

                <!-- Filtro de Rutas en Fronteras Dias -->
                <div class="pt-2 border-t border-slate-100 space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-700">Filtrar por Ruta:</span>
                    <button
                      v-if="filtroRutasFrontera.length > 0"
                      type="button"
                      @click="filtroRutasFrontera = []"
                      class="text-rose-600 text-[11px] font-semibold hover:underline cursor-pointer"
                    >
                      Ver todas las rutas
                    </button>
                  </div>

                  <input
                    v-model="busquedaRutaFrontera"
                    type="text"
                    placeholder="Buscar ruta de frontera..."
                    class="w-full h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:bg-white"
                  />

                  <div class="max-h-36 overflow-y-auto custom-scroll border border-slate-200 rounded-lg divide-y divide-slate-100">
                    <label
                      v-for="r in rutasFronterasFiltradasBusqueda"
                      :key="r"
                      class="flex items-center justify-between p-2 hover:bg-slate-50 cursor-pointer text-xs font-mono"
                    >
                      <span class="text-slate-800">{{ formatRuta(r) }}</span>
                      <input
                        type="checkbox"
                        :checked="filtroRutasFrontera.includes(r)"
                        @change="toggleRutaFrontera(r)"
                        class="rounded border-slate-300 text-sky-600 focus:ring-0 cursor-pointer"
                      />
                    </label>
                  </div>
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
import { getCanalColor, getRutaColor, formatRuta, getDiaColor, DIAS_SEMANA_CONFIG } from '@/Config/colors';
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

// ESTADO DE FRONTERAS DIAS
const verFronterasDias = ref(true);
const filtroDiasFrontera = ref(DIAS_SEMANA_CONFIG.map(d => d.key));
const filtroRutasFrontera = ref([]);
const drawerFiltrosMovil = ref(false);
const dropdownRutasFronteraAbierto = ref(false);
const busquedaRutaFrontera = ref('');

let map = null;
let currentTileLayer = null;
let centerMarker = null;
let radiusCircle = null;
let clientsLayer = null;
let fronterasDiasLayer = null;
const clientMarkersMap = new Map();

function normalizarDiaKey(dia) {
  if (!dia) return '';
  return String(dia).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim();
}

function esDiaFronteraActivo(diaKey) {
  return filtroDiasFrontera.value.includes(normalizarDiaKey(diaKey));
}

function toggleDiaFrontera(diaKey) {
  const norm = normalizarDiaKey(diaKey);
  const idx = filtroDiasFrontera.value.indexOf(norm);
  if (idx > -1) {
    filtroDiasFrontera.value.splice(idx, 1);
  } else {
    filtroDiasFrontera.value.push(norm);
  }
}

function toggleTodosDiasFrontera() {
  if (filtroDiasFrontera.value.length === DIAS_SEMANA_CONFIG.length) {
    filtroDiasFrontera.value = [];
  } else {
    filtroDiasFrontera.value = DIAS_SEMANA_CONFIG.map(d => d.key);
  }
}

const rutasDisponiblesFronteras = computed(() => {
  const set = new Set();
  (props.fronteras_dias || []).forEach(f => {
    if (f.ruta) set.add(String(f.ruta).trim());
  });
  return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
});

const rutasFronterasFiltradasBusqueda = computed(() => {
  const q = busquedaRutaFrontera.value.trim().toLowerCase();
  if (!q) return rutasDisponiblesFronteras.value;
  return rutasDisponiblesFronteras.value.filter(r => r.toLowerCase().includes(q));
});

function toggleRutaFrontera(ruta) {
  const idx = filtroRutasFrontera.value.indexOf(ruta);
  if (idx > -1) {
    filtroRutasFrontera.value.splice(idx, 1);
  } else {
    filtroRutasFrontera.value.push(ruta);
  }
}

function seleccionarTodasRutasFronteras() {
  filtroRutasFrontera.value = [];
}

const fronterasFiltradas = computed(() => {
  if (!verFronterasDias.value || !props.fronteras_dias || props.fronteras_dias.length === 0) {
    return [];
  }
  return props.fronteras_dias.filter(f => {
    const diaNorm = normalizarDiaKey(f.dia);
    if (!filtroDiasFrontera.value.includes(diaNorm)) return false;
    if (filtroRutasFrontera.value.length > 0 && !filtroRutasFrontera.value.includes(f.ruta)) return false;
    return true;
  });
});

const clientesFiltrados = computed(() => {
  if (!busquedaTexto.value.trim()) return clientesCercanos.value;
  const q = busquedaTexto.value.toLowerCase();
  return clientesCercanos.value.filter(c =>
    (c.cliente && c.cliente.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q))
  );
});

// Elementos dinamicos para la leyenda del mapa
const elementosLeyenda = computed(() => {
  if (clientesCercanos.value.length === 0) return [];
  const mapCounts = new Map();

  clientesCercanos.value.forEach(c => {
    let key, nombre, color;

    if (modoColor.value === 'ruta') {
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
      mapCounts.set(key, { id: key, nombre, color, conteo: 0 });
    }
    mapCounts.get(key).conteo++;
  });

  return Array.from(mapCounts.values()).sort((a, b) => b.conteo - a.conteo);
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

  try {
    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.cercanos.data')
      : '/supervisor/saneamiento/cercanos/data';

    const res = await axios.post(url, {
      latitud: lat,
      longitud: lng,
      radio: radioSeleccionado.value,
      canales: [],
      rutas: [],
      meses: filtroMeses.value,
      anio: anioSeleccionado.value,
      solo_activos: false,
    });

    const list = res.data.clientes || [];
    // Mapear canal calculado
    list.forEach(c => {
      const rutaInfo = props.catalogo_rutas.find(r => r.ruta === c.ruta);
      c.canal_calculado = rutaInfo ? rutaInfo.canal : 'GENERAL';
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

  fronterasDiasLayer = L.featureGroup().addTo(map);
  clientsLayer = L.featureGroup().addTo(map);

  renderizarFronterasDias();

  map.on('click', (e) => {
    consultarCercanos(e.latlng.lat, e.latlng.lng);
  });
}

function renderizarFronterasDias() {
  if (!map) return;
  if (!fronterasDiasLayer) {
    fronterasDiasLayer = L.featureGroup().addTo(map);
  } else {
    fronterasDiasLayer.clearLayers();
  }

  if (!verFronterasDias.value) return;

  fronterasFiltradas.value.forEach(fd => {
    if (!fd.geojson) return;
    const color = getDiaColor(fd.dia);
    const layer = L.geoJSON(fd.geojson, {
      style: {
        color: color,
        weight: 2,
        dashArray: '5, 5',
        fillColor: color,
        fillOpacity: 0.12,
      },
      onEachFeature: (feature, l) => {
        l.bindTooltip(`
          <div style="font-family: inherit; font-size: 11px; line-height: 1.3;">
            <div style="font-weight: bold; color: ${color};">Frontera: ${fd.dia}</div>
            <div style="font-size: 10px; color: #475569;">Ruta: ${formatRuta(fd.ruta)}</div>
          </div>
        `, { sticky: true, opacity: 0.95 });

        l.on('mouseover', () => {
          l.setStyle({ fillOpacity: 0.28, weight: 3 });
        });
        l.on('mouseout', () => {
          l.setStyle({ fillOpacity: 0.12, weight: 2 });
        });
      }
    });
    layer.addTo(fronterasDiasLayer);
  });
}

function getMarkerColor(c) {
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

function renderizarEnMapa() {
  if (!map || !origenConsulta.value) return;

  if (centerMarker) map.removeLayer(centerMarker);
  if (radiusCircle) map.removeLayer(radiusCircle);
  clientsLayer.clearLayers();
  clientMarkersMap.clear();

  const center = [origenConsulta.value.lat, origenConsulta.value.lng];

  centerMarker = L.circleMarker(center, {
    radius: 7,
    fillColor: '#0284C7',
    color: '#FFFFFF',
    weight: 2,
    opacity: 1,
    fillOpacity: 1,
  }).addTo(map);

  radiusCircle = L.circle(center, {
    radius: radioSeleccionado.value,
    color: '#0284C7',
    fillColor: '#0284C7',
    fillOpacity: 0.05,
    weight: 1.5,
    dashArray: '4, 6',
  }).addTo(map);

  clientesCercanos.value.forEach(c => {
    const markerColor = getMarkerColor(c);

    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: 5.5,
      fillColor: markerColor,
      color: '#FFFFFF',
      weight: 1.5,
      opacity: 1,
      fillOpacity: 0.95,
    });

    const esActivo = !c.estado || c.estado.toLowerCase() === 'activo';
    const estadoBadge = esActivo
      ? '<span style="font-size: 9px; font-family: monospace; padding: 1px 4px; border-radius: 4px; font-weight: bold; background: #ECFDF5; color: #047857;">Activo</span>'
      : '<span style="font-size: 9px; font-family: monospace; padding: 1px 4px; border-radius: 4px; font-weight: bold; background: #FFF1F2; color: #BE123C;">Inactivo</span>';

    marker.bindPopup(`
      <div style="font-family: inherit; font-size: 11px; line-height: 1.4; color: #0F172A; max-width: 230px;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2px; gap: 4px;">
          <span style="font-weight: 700; font-size: 12px;" class="truncate">${c.cliente}</span>
          ${estadoBadge}
        </div>
        <div style="color: #64748B; margin-bottom: 5px;">${c.direccion || 'Sin direccion registrada'}</div>
        <div style="display: flex; gap: 4px; font-family: monospace; font-size: 10px; border-top: 1px solid #E2E8F0; padding-top: 4px; justify-content: space-between; align-items: center;">
          <span>Ruta: <b style="color: ${getRutaColor(c.ruta)}">${formatRuta(c.ruta)}</b></span>
          <span>•</span>
          <span style="color: #047857; font-weight: bold;">${c.distancia_metros} m</span>
        </div>
      </div>
    `);

    marker.on('click', () => {
      abrirExpedienteCliente(c);
    });

    marker.addTo(clientsLayer);
    clientMarkersMap.set(c.id, marker);
  });
}

async function abrirExpedienteCliente(c) {
  clienteActivoId.value = c.id;
  clienteSeleccionado.value = c;
  tabExpediente.value = 'ventas';
  ventasCliente.value = [];
  visitasCliente.value = [];

  // En movil cambiar a pestana de lista/expediente para inspeccionar la informacion
  if (typeof window !== 'undefined' && window.innerWidth < 768) {
    vistaMovil.value = 'lista';
  }

  if (map && c.latitud && c.longitud) {
    map.flyTo([c.latitud, c.longitud], 17, { duration: 0.6 });
    const m = clientMarkersMap.get(c.id);
    if (m) m.openPopup();
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
}

watch([verFronterasDias, filtroDiasFrontera, filtroRutasFrontera], () => {
  renderizarFronterasDias();
}, { deep: true });

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
