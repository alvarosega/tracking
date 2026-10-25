<template>
  <RuteoLayout>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900 font-sans text-slate-900">
      <!-- ========================================== -->
      <!-- MOBILE HEADER (< md): 2 FILAS RESPONSIVAS -->
      <!-- ========================================== -->
      <div class="flex md:hidden flex-col gap-2 p-2.5 bg-white/95 backdrop-blur-md border-b border-slate-200/90 shrink-0 relative z-30">
        <!-- Fila 1: [ Mapa | Lista (N) ] a la izquierda y [ En radio: N pts ] a la derecha -->
        <div class="flex items-center justify-between gap-2">
          <!-- Bloque 1: Selector Vista Mapa / Lista -->
          <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 shrink-0">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-3 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1.5"
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
              class="px-3 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer flex items-center gap-1.5"
              :class="vistaMovil === 'lista' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
              </svg>
              Lista ({{ clientesCercanos.length }})
            </button>
          </div>

          <!-- Bloque 2: Total en Radio & Estado -->
          <div class="flex items-center gap-1.5 text-xs font-mono">
            <span v-if="cargando" class="flex items-center gap-1 text-[11px] text-sky-600 animate-pulse font-medium">
              <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
              Rastreando...
            </span>
            <div class="px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-1.5 shadow-2xs font-bold text-slate-900">
              <span class="text-slate-400 font-normal text-[11px]">En radio:</span>
              <span class="tabular-nums text-xs">{{ clientesCercanos.length }} pts</span>
            </div>
          </div>
        </div>

        <!-- Fila 2: Mi Ubicación, Buscador Maps / Coordenadas, Selector de Radio -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar scroll-touch pt-0.5">
          <!-- Botón de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-200/90 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all shrink-0 disabled:opacity-50 cursor-pointer shadow-2xs"
            title="Usar ubicación GPS del dispositivo"
          >
            <span class="w-2 h-2 rounded-full" :class="obteniendoGps ? 'bg-amber-500 animate-ping' : 'bg-sky-500'"></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : 'Mi Ubicación' }}</span>
          </button>

          <!-- Buscador / Pegar Enlace de Google Maps o Coordenadas -->
          <div class="relative flex items-center h-8 shrink-0 flex-1 min-w-[190px]">
            <div class="absolute left-2.5 text-slate-400 pointer-events-none flex items-center">
              <svg class="w-3.5 h-3.5 text-rose-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
              </svg>
            </div>
            <input
              type="text"
              v-model="inputEnlaceMaps"
              @keydown.enter.prevent="resolverEnlaceMaps"
              placeholder="Pegar enlace Maps o coords..."
              class="w-full h-8 pl-8 pr-12 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200/90 rounded-lg text-xs font-mono text-slate-800 placeholder:text-slate-400 placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500 transition-all"
              title="Pega enlaces cortos de Google Maps (ej: maps.app.goo.gl/...), URLs completas o coordenadas (-17.412, -66.154)"
            />
            <button
              type="button"
              @click="resolverEnlaceMaps"
              :disabled="resolviendoEnlace || !inputEnlaceMaps.trim()"
              class="absolute right-1 h-6 px-2 bg-sky-600 hover:bg-sky-500 text-white rounded text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer flex items-center justify-center gap-1"
              title="Traducir enlace e inspeccionar clientes cercanos"
            >
              <span v-if="resolviendoEnlace" class="w-2.5 h-2.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ resolviendoEnlace ? '' : 'Ir' }}</span>
            </button>
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
        </div>

        <!-- Toast / Feedback del Enlace de Maps en Móvil -->
        <div
          v-if="mensajeEnlace"
          class="px-2.5 py-1 rounded-lg text-[11px] font-medium border flex items-center justify-between gap-1.5 transition-all shadow-xs"
          :class="mensajeEnlace.tipo === 'exito' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
        >
          <span class="truncate">{{ mensajeEnlace.tipo === 'exito' ? '📍' : '⚠️' }} {{ mensajeEnlace.texto }}</span>
          <button type="button" @click="mensajeEnlace = null" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer shrink-0">&times;</button>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- DESKTOP HEADER (>= md): 1 FILA COMPLETA   -->
      <!-- ========================================== -->
      <div class="hidden md:flex bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-4 py-2 items-center justify-between gap-2.5 shrink-0 relative z-30">
        <div class="flex items-center gap-2.5 overflow-x-auto no-scrollbar scroll-touch py-0.5 flex-1 min-w-0">
          <!-- Botón de GPS Actual -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-3 bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-200/90 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all shrink-0 disabled:opacity-50 cursor-pointer shadow-2xs"
            title="Usar ubicación GPS del dispositivo"
          >
            <span class="w-2 h-2 rounded-full" :class="obteniendoGps ? 'bg-amber-500 animate-ping' : 'bg-sky-500'"></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : 'Mi Ubicación' }}</span>
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
              class="h-8 pl-8 pr-12 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200/90 rounded-lg text-xs font-mono text-slate-800 placeholder:text-slate-400 placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500 w-64 lg:w-72 transition-all"
              title="Pega enlaces cortos de Google Maps (ej: maps.app.goo.gl/...), URLs completas o coordenadas (-17.412, -66.154)"
            />
            <button
              type="button"
              @click="resolverEnlaceMaps"
              :disabled="resolviendoEnlace || !inputEnlaceMaps.trim()"
              class="absolute right-1 h-6 px-2 bg-sky-600 hover:bg-sky-500 text-white rounded text-[10px] font-bold transition-all disabled:opacity-40 cursor-pointer flex items-center justify-center gap-1"
              title="Traducir enlace e inspeccionar clientes cercanos"
            >
              <span v-if="resolviendoEnlace" class="w-2.5 h-2.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ resolviendoEnlace ? '' : 'Ir' }}</span>
            </button>
          </div>

          <!-- Toast / Feedback del Enlace de Maps -->
          <div
            v-if="mensajeEnlace"
            class="px-2.5 py-1 rounded-lg text-[11px] font-medium border flex items-center gap-1.5 transition-all shadow-xs shrink-0"
            :class="mensajeEnlace.tipo === 'exito' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
          >
            <span>{{ mensajeEnlace.tipo === 'exito' ? '📍' : '⚠️' }}</span>
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

          <!-- Selector de Modo de Coloración (Ruta | Canal | Día) -->
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
              Día
            </button>
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

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Lista o Expediente del Cliente -->
        <aside
          class="w-full md:w-[380px] bg-white border-r border-slate-200 flex flex-col shrink-0 relative z-20 overflow-hidden transition-all"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- Vista 1: Listado de Clientes en Radio -->
          <template v-if="!clienteSeleccionado">
            <!-- Barra de Búsqueda y Coordenadas -->
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
            </div>

            <!-- Listado Scrollable -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
              <div v-if="!origenConsulta" class="p-8 text-center text-xs text-slate-400 space-y-2">
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                </div>
                <p class="font-semibold text-slate-700">Explorador de Radio</p>
                <p class="text-[11px] text-slate-400">Toca cualquier punto en el mapa de El Alto o pulsa <strong>"Mi Ubicación"</strong> para rastrear clientes cercanos.</p>
              </div>

              <div v-else-if="clientesFiltrados.length === 0 && !cargando" class="p-8 text-center text-xs text-slate-400">
                No se encontraron clientes dentro del radio de {{ radioSeleccionado }}m.
              </div>

              <button
                v-for="c in clientesFiltrados"
                :key="c.id"
                type="button"
                @click="abrirExpedienteCliente(c)"
                class="w-full text-left p-3 hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer group"
                :class="clienteActivoId === c.id ? 'bg-sky-50/70 border-l-2 border-sky-600' : ''"
              >
                <div class="flex items-start justify-between gap-2">
                  <span class="text-xs font-semibold text-slate-900 group-hover:text-sky-600 transition-colors line-clamp-1">
                    {{ c.cliente }}
                  </span>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white shadow-2xs"
                      :style="{ backgroundColor: getCanalColor(c.canal_calculado) }"
                    >
                      {{ c.canal_calculado }}
                    </span>
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded shadow-2xs"
                      :class="c.estado && c.estado.toLowerCase() !== 'activo' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                    >
                      {{ c.estado && c.estado.toLowerCase() !== 'activo' ? 'Inactivo' : 'Activo' }}
                    </span>
                    <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200 tabular-nums">
                      {{ c.distancia_metros }} m
                    </span>
                  </div>
                </div>

                <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ c.direccion || 'Sin dirección registrada' }}</p>

                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] font-mono">
                  <div class="flex items-center gap-1.5 text-slate-500">
                    <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: getRutaColor(c.ruta) }"></span>
                    <span class="font-semibold text-slate-800">{{ formatRuta(c.ruta) }}</span>
                    <span>•</span>
                    <span class="text-slate-400">ID: {{ c.cliente_id }}</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="text-slate-400 text-[10px]">{{ c.total_pedidos }} ped.</span>
                    <span class="font-bold text-slate-900 tabular-nums">Bs. {{ c.total_compras.toFixed(2) }}</span>
                  </div>
                </div>
              </button>
            </div>
          </template>

          <!-- Vista 2: Expediente 360° del Cliente -->
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
                <div>
                  <h3 class="text-xs font-bold text-slate-900 leading-snug">{{ clienteSeleccionado.cliente }}</h3>
                  <p class="text-[11px] text-slate-500 mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección registrada' }}</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
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
                  class="font-mono text-sky-600 hover:underline font-semibold ml-auto"
                >
                  📞 {{ clienteSeleccionado.celular }}
                </a>
              </div>

              <!-- Pestañas de Expediente -->
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

            <!-- Contenido Pestaña: Ventas -->
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

            <!-- Contenido Pestaña: Visitas -->
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
          class="flex-1 relative min-h-[350px] md:min-h-0 bg-slate-950"
          :class="vistaMovil === 'mapa' ? 'block' : 'hidden md:block'"
        >
          <div id="map-cercanos" class="absolute inset-0 w-full h-full z-10"></div>

          <!-- Selector de Capas de Mapa (Idéntico a Visor y Fronteras) -->
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

          <!-- Leyenda Dinámica en Tiempo Real (Idéntica a Visor y Fronteras) -->
          <div
            v-if="clientesCercanos.length > 0"
            class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-xl p-3 shadow-xl z-30 text-xs max-w-xs max-h-64 overflow-y-auto"
          >
            <div class="flex items-center justify-between pb-1.5 mb-2 border-b border-slate-100">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono">
                Codificación: {{ modoColor.toUpperCase() }}
              </p>
              <span class="text-[10px] font-mono text-slate-500">{{ elementosLeyenda.length }} tipos</span>
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
  </RuteoLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import RuteoLayout from '@/Pages/Supervisor/Ruteo/Layout.vue';
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

const mesesLista = [
  { numero: 1, corto: 'ENE', nombre: 'Enero' },
  { numero: 2, corto: 'FEB', nombre: 'Febrero' },
  { numero: 3, corto: 'MAR', nombre: 'Marzo' },
  { numero: 4, corto: 'ABR', nombre: 'Abril' },
  { numero: 5, corto: 'MAY', nombre: 'Mayo' },
  { numero: 6, corto: 'JUN', nombre: 'Junio' },
  { numero: 7, corto: 'JUL', nombre: 'Julio' },
  { numero: 8, corto: 'AGO', nombre: 'Agosto' },
  { numero: 9, corto: 'SEP', nombre: 'Septiembre' },
  { numero: 10, corto: 'OCT', nombre: 'Octubre' },
  { numero: 11, corto: 'NOV', nombre: 'Noviembre' },
  { numero: 12, corto: 'DIC', nombre: 'Diciembre' },
];

// Estados reactivos
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

// Traductor de Enlaces de Google Maps
const inputEnlaceMaps = ref('');
const resolviendoEnlace = ref(false);
const mensajeEnlace = ref(null);

let map = null;
let currentTileLayer = null;
let centerMarker = null;
let radiusCircle = null;
let clientsLayer = null;
const clientMarkersMap = new Map();

const clientesFiltrados = computed(() => {
  if (!busquedaTexto.value.trim()) return clientesCercanos.value;
  const q = busquedaTexto.value.toLowerCase();
  return clientesCercanos.value.filter(c =>
    (c.cliente && c.cliente.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q))
  );
});

// Elementos dinámicos para la leyenda del mapa
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
      key = c.dia_norm || 'Sin Día';
      nombre = c.dia_norm || 'Sin Día';
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
    alert('Tu navegador no soporta geolocalización');
    return;
  }
  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      obteniendoGps.value = false;
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      map.flyTo([lat, lng], 15);
      consultarCercanos(lat, lng);
    },
    (err) => {
      obteniendoGps.value = false;
      alert('No se pudo obtener tu ubicación GPS: ' + err.message);
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

function reconsultar() {
  if (origenConsulta.value) {
    consultarCercanos(origenConsulta.value.lat, origenConsulta.value.lng);
  }
}

async function resolverEnlaceMaps() {
  const val = inputEnlaceMaps.value.trim();
  if (!val) return;

  resolviendoEnlace.value = true;
  mensajeEnlace.value = null;

  try {
    const url = typeof route === 'function' ? route('supervisor.ruteo.cercanos.resolver-enlace') : '/supervisor/ruteo/cercanos/resolver-enlace';
    const res = await axios.post(url, {
      url: val,
    });

    if (res.data && res.data.success) {
      const lat = Number(res.data.latitud);
      const lng = Number(res.data.longitud);

      mensajeEnlace.value = {
        tipo: 'exito',
        texto: `Ubicación traducida: ${lat.toFixed(5)}, ${lng.toFixed(5)}`,
      };

      if (map) {
        map.flyTo([lat, lng], 16, { duration: 0.8 });
      }

      // Ejecutar la consulta espacial de clientes cercanos alrededor de esa ubicación
      consultarCercanos(lat, lng);

      setTimeout(() => {
        mensajeEnlace.value = null;
      }, 6000);
    } else {
      mensajeEnlace.value = {
        tipo: 'error',
        texto: res.data?.message || 'No se pudo extraer la ubicación del enlace.',
      };
    }
  } catch (error) {
    console.error('Error al resolver enlace de Maps:', error);
    mensajeEnlace.value = {
      tipo: 'error',
      texto: error.response?.data?.message || 'Error de comunicación al traducir enlace.',
    };
  } finally {
    resolviendoEnlace.value = false;
  }
}

async function consultarCercanos(lat, lng) {
  origenConsulta.value = { lat, lng };
  cargando.value = true;

  try {
    const res = await axios.post(route('supervisor.ruteo.cercanos.data'), {
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

  clientsLayer = L.featureGroup().addTo(map);

  map.on('click', (e) => {
    menuAbierto.value = null;
    consultarCercanos(e.latlng.lat, e.latlng.lng);
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
        <div style="color: #64748B; margin-bottom: 5px;">${c.direccion || 'Sin dirección registrada'}</div>
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

  if (map && c.latitud && c.longitud) {
    map.flyTo([c.latitud, c.longitud], 17, { duration: 0.6 });
    const m = clientMarkersMap.get(c.id);
    if (m) m.openPopup();
  }

  // Cargar ventas
  cargandoVentas.value = true;
  try {
    const resVentas = await axios.post(`/supervisor/ruteo/cercanos/cliente/${c.cliente_id}/ventas`, {
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
    const resVisitas = await axios.post(`/supervisor/ruteo/cercanos/cliente/${c.cliente_id}/visitas`);
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

onMounted(() => {
  initMap();
});
</script>