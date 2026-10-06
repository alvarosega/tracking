<template>
  <SupervisorLayout>
    <div class="h-[calc(100vh-3rem)] md:h-[calc(100vh-3.5rem)] flex flex-col -m-4 sm:-m-6 lg:-m-7 overflow-hidden bg-slate-900 font-sans text-slate-900">
      
      <!-- ==========================================
           1. BARRA SUPERIOR DE FILTROS & TELEMETRÍA
           ========================================== -->
      <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop invisible para cerrar dropdowns de filtros -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Scroll Horizontal de Filtros Principales -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          
          <!-- Selector de Fecha de Preventa -->
          <div class="flex items-center bg-slate-50 p-1 rounded-lg border border-slate-200/90 shrink-0 h-8">
            <span class="text-[11px] text-slate-400 font-mono px-1.5 flex items-center gap-1">
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
              </svg>
              Fecha:
            </span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarPreventas"
              class="h-6 px-1.5 text-xs font-mono font-semibold bg-white text-slate-900 border border-slate-200 rounded-md focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Botón de Ubicación GPS Supervisor -->
          <button
            type="button"
            @click="obtenerUbicacionActual"
            :disabled="obteniendoGps"
            class="h-8 px-3 bg-slate-100 hover:bg-slate-200 border border-slate-200/90 rounded-lg text-xs font-semibold text-slate-900 flex items-center gap-1.5 transition-all disabled:opacity-50 cursor-pointer shrink-0 shadow-2xs"
            title="Ubicar mi GPS para auditar pedidos en mi alrededor"
          >
            <span
              class="w-2 h-2 rounded-full"
              :class="obteniendoGps ? 'bg-amber-500 animate-ping' : (ubicacionSupervisor ? 'bg-emerald-500 ring-4 ring-emerald-100' : 'bg-sky-500')"
            ></span>
            <span class="whitespace-nowrap">{{ obteniendoGps ? 'Localizando...' : (ubicacionSupervisor ? 'Mi Ubicación GPS' : 'Auditar mi Alrededor') }}</span>
          </button>

          <!-- Selector de Halo de Proximidad si está ubicado -->
          <div v-if="ubicacionSupervisor" class="flex items-center bg-slate-50 p-0.5 rounded-lg border border-slate-200/90 shrink-0 h-8 animate-fadeIn">
            <span class="text-[10px] text-slate-400 font-mono px-1.5">Halo:</span>
            <button
              v-for="r in [150, 300, 500, 1000]"
              :key="r"
              type="button"
              @click="seleccionarRadio(r)"
              class="px-2 py-0.5 text-[11px] font-mono font-medium rounded-md transition-all cursor-pointer"
              :class="radioSupervisor === r ? 'bg-slate-900 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
            >
              {{ r }}m
            </button>
            <button
              type="button"
              @click="limpiarUbicacion"
              class="px-1.5 py-0.5 text-[10px] text-rose-500 hover:underline font-mono border-l border-slate-200 ml-0.5 cursor-pointer"
              title="Quitar GPS de supervisor"
            >
              ✕
            </button>
          </div>

          <!-- Filtro: Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Canal:</span>
            <span class="font-semibold text-slate-900">{{ labelCanales }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro: Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-8 px-2.5 bg-slate-50 border border-slate-200/90 rounded-lg text-xs font-medium text-slate-800 hover:bg-slate-100 flex items-center gap-1.5 transition-colors cursor-pointer select-none shrink-0"
          >
            <span class="text-slate-400">Rutas:</span>
            <span class="font-semibold text-slate-900">{{ labelRutas }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Botón Refrescar -->
          <button
            type="button"
            @click="cargarPreventas"
            class="h-8 w-8 bg-slate-50 hover:bg-slate-100 border border-slate-200/90 rounded-lg flex items-center justify-center text-slate-600 hover:text-slate-900 transition-colors cursor-pointer shrink-0"
            title="Recargar preventas"
          >
            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': cargando }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
          </button>
        </div>

        <!-- Indicador de Carga & Switch Móvil -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-slate-500 hidden sm:flex items-center gap-1.5 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
            Cargando pedidos...
          </span>

          <!-- Switch Móvil: Mapa / Lista -->
          <div class="flex md:hidden items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
            <button
              type="button"
              @click="vistaMobile = 'mapa'"
              class="px-2.5 py-1 rounded text-[11px] font-semibold transition-all cursor-pointer"
              :class="vistaMobile === 'mapa' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMobile = 'lista'"
              class="px-2.5 py-1 rounded text-[11px] font-semibold transition-all cursor-pointer flex items-center gap-1"
              :class="vistaMobile === 'lista' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500'"
            >
              Pedidos ({{ clientesFiltrados.length }})
            </button>
          </div>
        </div>
      </header>

      <!-- ==========================================
           2. BARRA DE RESUMEN KPI (SLIM & FUTURISTA)
           ========================================== -->
      <div class="bg-slate-900 text-slate-200 border-b border-slate-800 px-3 sm:px-4 py-1.5 flex items-center justify-between text-[11px] font-mono shrink-0 overflow-x-auto no-scrollbar gap-4 z-20 shadow-xs">
        <div class="flex items-center gap-4 sm:gap-6 shrink-0">
          <!-- Total Preventas -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-500 uppercase tracking-wider text-[10px]">Preventas:</span>
            <span class="font-bold text-white text-xs">{{ kpis.total_preventas }}</span>
          </div>

          <!-- Total Clientes -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-500 uppercase tracking-wider text-[10px]">Clientes:</span>
            <span class="font-bold text-sky-400 text-xs">{{ kpis.total_clientes }}</span>
          </div>

          <!-- Monto Total Bs -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-500 uppercase tracking-wider text-[10px]">Total Bs:</span>
            <span class="font-bold text-emerald-400 text-xs">Bs. {{ formatoMoneda(kpis.monto_total) }}</span>
          </div>

          <!-- Métricas en Radio si el supervisor está activo -->
          <div v-if="ubicacionSupervisor" class="flex items-center gap-2 bg-emerald-950/60 border border-emerald-500/30 px-2 py-0.5 rounded text-emerald-300">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>En Radio {{ radioSupervisor }}m:</span>
            <span class="font-bold text-white">{{ kpis.en_radio_conteo }} cli. (Bs. {{ formatoMoneda(kpis.en_radio_monto) }})</span>
          </div>
        </div>

        <div class="flex items-center gap-3 shrink-0 text-slate-400 text-[10px]">
          <span class="flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Con GPS: {{ kpis.con_gps }}
          </span>
          <span class="flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Sin GPS: {{ kpis.sin_gps }}
          </span>
        </div>
      </div>

      <!-- ==========================================
           3. ÁREA PRINCIPAL: MAPA + PANEL DE PEDIDOS
           ========================================== -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 relative overflow-hidden">
        
        <!-- Panel Izquierdo / Flotante: Lista de Pedidos & Items -->
        <div
          class="w-full md:w-96 lg:w-[420px] bg-white border-r border-slate-200/90 flex flex-col z-20 transition-all duration-200 shrink-0"
          :class="{
            'hidden md:flex': vistaMobile === 'mapa',
            'flex': vistaMobile === 'lista'
          }"
        >
          <!-- Buscador y Ordenación en el Panel de Pedidos -->
          <div class="p-2.5 border-b border-slate-200/80 bg-slate-50/60 space-y-2 shrink-0">
            <div class="relative">
              <input
                type="text"
                v-model="busquedaTexto"
                placeholder="Buscar por cliente, código, preventa o vendedor..."
                class="w-full h-8 pl-8 pr-7 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 shadow-2xs"
              />
              <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <button
                v-if="busquedaTexto"
                @click="busquedaTexto = ''"
                class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs font-mono"
              >
                ✕
              </button>
            </div>

            <!-- Filtros rápidos de lista -->
            <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono">
              <span>{{ clientesFiltrados.length }} cliente(s) con preventa</span>
              
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  @click="filtroSoloRadio = !filtroSoloRadio"
                  v-if="ubicacionSupervisor"
                  class="px-1.5 py-0.5 rounded text-[10px] border transition-colors cursor-pointer"
                  :class="filtroSoloRadio ? 'bg-emerald-500 text-white border-emerald-600 font-bold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'"
                >
                  Solo en Radio ({{ kpis.en_radio_conteo }})
                </button>
              </div>
            </div>
          </div>

          <!-- Feed de Clientes y sus Pedidos Desglosados -->
          <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-2">
            
            <!-- Estado Vacío -->
            <div v-if="clientesFiltrados.length === 0" class="py-12 px-4 text-center">
              <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
              </div>
              <p class="text-xs font-semibold text-slate-700">Sin preventas para esta fecha</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Prueba seleccionando otra fecha o ajustando los canales y rutas.</p>
            </div>

            <!-- Card de Cliente con Pedidos -->
            <div
              v-for="c in clientesFiltrados"
              :key="c.cliente_id"
              class="bg-white border rounded-lg p-2.5 transition-all text-xs hover:border-slate-300 shadow-2xs"
              :class="[
                clienteSeleccionado?.cliente_id === c.cliente_id ? 'border-sky-500 ring-2 ring-sky-100 bg-sky-50/20' : 'border-slate-200/80',
                c.en_radio ? 'border-l-4 border-l-emerald-500' : ''
              ]"
            >
              <!-- Cabecera del Cliente -->
              <div class="flex items-start justify-between gap-1.5 mb-1.5">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      class="px-1.5 py-0.2 text-[9px] font-mono font-bold rounded text-white shadow-2xs shrink-0"
                      :style="{ backgroundColor: getRutaColor(c.ruta) }"
                    >
                      {{ formatRuta(c.ruta) }}
                    </span>
                    <span class="font-bold text-slate-900 truncate text-[12px]">{{ c.cliente }}</span>
                  </div>
                  <p class="text-[10px] text-slate-400 font-mono mt-0.5 truncate">
                    Cód: {{ c.codigo_cliente }} • {{ c.tipo_negocio || 'General' }} • {{ c.zona || 'Sin Zona' }}
                  </p>
                </div>

                <!-- Monto Total del Cliente -->
                <div class="text-right shrink-0">
                  <span class="text-xs font-bold font-mono text-emerald-600 block">Bs. {{ formatoMoneda(c.total_monto) }}</span>
                  <span class="text-[9px] text-slate-400 font-mono">{{ c.pedidos.length }} pedido(s)</span>
                </div>
              </div>

              <!-- Indicadores de Ubicación / Distancia -->
              <div class="flex items-center justify-between text-[10px] font-mono py-1 border-t border-slate-100 my-1 text-slate-500">
                <span v-if="c.vendedor" class="truncate max-w-[150px]">Vend: {{ c.vendedor }}</span>
                <span v-else>Sin vendedor</span>

                <div class="flex items-center gap-1.5">
                  <span v-if="c.tiene_gps" class="text-emerald-600 font-semibold flex items-center gap-0.5">
                    <svg class="w-3 h-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    GPS
                  </span>
                  <span v-else class="text-rose-500 font-semibold">Sin GPS</span>

                  <span
                    v-if="c.distancia_metros !== null"
                    class="px-1.5 py-0.2 rounded font-bold"
                    :class="c.en_radio ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ c.distancia_metros }}m
                  </span>
                </div>
              </div>

              <!-- Desglose de Pedidos (Acordeón o Vista Directa) -->
              <div class="space-y-1.5 mt-2">
                <div
                  v-for="ped in c.pedidos"
                  :key="ped.nro_preventa"
                  class="bg-slate-50 rounded border border-slate-200/70 p-2"
                >
                  <div class="flex items-center justify-between font-mono text-[10px] text-slate-600 mb-1">
                    <span class="font-bold text-slate-800">#{{ ped.nro_preventa }}</span>
                    <div class="flex items-center gap-1">
                      <span v-if="ped.facturar" class="px-1 py-0.2 bg-emerald-100 text-emerald-700 rounded text-[9px] font-semibold">Facturar</span>
                      <span v-if="ped.nro_carga" class="px-1 py-0.2 bg-slate-200 text-slate-700 rounded text-[9px]">Carga {{ ped.nro_carga }}</span>
                      <span class="font-bold text-slate-900">Bs. {{ formatoMoneda(ped.monto_pedido) }}</span>
                    </div>
                  </div>

                  <!-- Lista de Ítems del Pedido -->
                  <div class="space-y-1 pt-1 border-t border-slate-200/50">
                    <div
                      v-for="item in ped.items"
                      :key="item.id"
                      class="flex items-center justify-between text-[10px] leading-tight text-slate-700"
                    >
                      <div class="min-w-0 flex-1 pr-2 truncate">
                        <span class="font-mono text-slate-400 mr-1">{{ item.cantidad }}x</span>
                        <span class="font-medium text-slate-800">{{ item.producto }}</span>
                      </div>
                      <span class="font-mono font-semibold text-slate-900 shrink-0">Bs. {{ formatoMoneda(item.monto_final) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Botón para Ubicar en Mapa -->
              <div class="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between">
                <button
                  type="button"
                  @click="enfocarClienteEnMapa(c)"
                  class="text-[11px] font-semibold text-sky-600 hover:text-sky-800 flex items-center gap-1 cursor-pointer"
                  :disabled="!c.tiene_gps"
                  :class="{ 'opacity-40 cursor-not-allowed': !c.tiene_gps }"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                  <span>{{ c.tiene_gps ? 'Centrar en Mapa' : 'Sin coordenadas GPS' }}</span>
                </button>

                <span v-if="c.contacto || c.celular" class="text-[10px] text-slate-400 font-mono">
                  Tel: {{ c.celular || c.contacto }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel Derecho / Mapa Leaflet a Pantalla Completa -->
        <div
          class="flex-1 min-h-0 relative z-10 bg-slate-950"
          :class="{
            'hidden md:block': vistaMobile === 'lista',
            'block': vistaMobile === 'mapa'
          }"
        >
          <!-- Selector de Mapa Base (Capas) -->
          <div class="absolute top-3 right-3 z-20 flex items-center bg-white/90 backdrop-blur-md rounded-lg shadow-md border border-slate-200/90 p-0.5">
            <button
              v-for="tile in TILE_LAYERS"
              :key="tile.id"
              type="button"
              @click="cambiarTileLayer(tile.id)"
              class="px-2.5 py-1 text-[11px] font-medium rounded-md transition-all cursor-pointer"
              :class="tileActivo === tile.id ? 'bg-slate-900 text-white shadow-2xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
            >
              {{ tile.name }}
            </button>
          </div>

          <!-- Contenedor del Mapa Leaflet -->
          <div id="map-preventas" class="absolute inset-0 w-full h-full z-10"></div>
        </div>
      </div>

      <!-- ==========================================
           4. DROPDOWNS TELEPORTADOS (Z-INDEX 99999)
           ========================================== -->

      <!-- Dropdown Canales -->
      <Teleport to="body">
        <div
          v-if="menuAbierto === 'canales'"
          class="fixed z-[99999] bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 text-xs text-slate-800 w-64 animate-fadeIn"
          :style="dropdownPosition"
          @click.stop
        >
          <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
            <span class="font-bold text-slate-900 text-xs font-mono uppercase">Canales</span>
            <div class="space-x-1.5 text-[11px]">
              <button type="button" @click="canalesSeleccionados = [...canalesDisponibles]" class="text-sky-600 hover:underline cursor-pointer">Todos</button>
              <span class="text-slate-300">|</span>
              <button type="button" @click="canalesSeleccionados = []" class="text-slate-500 hover:underline cursor-pointer">Ninguno</button>
            </div>
          </div>
          <div class="space-y-1 max-h-48 overflow-y-auto pr-1">
            <label
              v-for="can in canalesDisponibles"
              :key="can"
              class="flex items-center space-x-2 p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer select-none transition-colors"
            >
              <input
                type="checkbox"
                :value="can"
                v-model="canalesSeleccionados"
                class="rounded border-slate-300 text-slate-900 focus:ring-0 w-3.5 h-3.5"
              />
              <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: getCanalColor(can) }"></span>
              <span class="font-medium text-slate-800">{{ CANAL_COLORS[can]?.name || can }}</span>
            </label>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex justify-end">
            <button
              type="button"
              @click="aplicarFiltros"
              class="px-3 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-xs cursor-pointer"
            >
              Aplicar
            </button>
          </div>
        </div>
      </Teleport>

      <!-- Dropdown Rutas -->
      <Teleport to="body">
        <div
          v-if="menuAbierto === 'rutas'"
          class="fixed z-[99999] bg-white border border-slate-200 rounded-xl shadow-2xl p-2.5 text-xs text-slate-800 w-72 animate-fadeIn"
          :style="dropdownPosition"
          @click.stop
        >
          <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
            <span class="font-bold text-slate-900 text-xs font-mono uppercase">Rutas</span>
            <div class="space-x-1.5 text-[11px]">
              <button type="button" @click="rutasSeleccionadas = catalogoFiltradoRutas.map(r => r.ruta)" class="text-sky-600 hover:underline cursor-pointer">Todas</button>
              <span class="text-slate-300">|</span>
              <button type="button" @click="rutasSeleccionadas = []" class="text-slate-500 hover:underline cursor-pointer">Ninguna</button>
            </div>
          </div>
          <div class="mb-2">
            <input
              type="text"
              v-model="busquedaRuta"
              placeholder="Buscar ruta o vendedor..."
              class="w-full px-2 py-1 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400"
            />
          </div>
          <div class="space-y-1 max-h-56 overflow-y-auto pr-1">
            <label
              v-for="r in rutasFiltradasPorBusqueda"
              :key="r.ruta"
              class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50 cursor-pointer select-none transition-colors"
            >
              <div class="flex items-center space-x-2 truncate">
                <input
                  type="checkbox"
                  :value="r.ruta"
                  v-model="rutasSeleccionadas"
                  class="rounded border-slate-300 text-slate-900 focus:ring-0 w-3.5 h-3.5"
                />
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-semibold text-slate-900 font-mono">{{ formatRuta(r.ruta) }}</span>
                <span class="text-[11px] text-slate-400 truncate">{{ r.vendedor }}</span>
              </div>
            </label>
          </div>
          <div class="mt-2 pt-2 border-t border-slate-100 flex justify-end">
            <button
              type="button"
              @click="aplicarFiltros"
              class="px-3 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-xs cursor-pointer"
            >
              Aplicar
            </button>
          </div>
        </div>
      </Teleport>

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import { mapsConfig } from '@/Config/maps';
import { getRutaColor, formatRuta, getCanalColor, CANAL_COLORS } from '@/Config/colors';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_default: { type: String, default: () => new Date().toISOString().split('T')[0] },
});

// Filtros Reactivos
const filtroFecha = ref(props.fecha_default);
const canalesDisponibles = ref(props.canales || []);
const canalesSeleccionados = ref([...props.canales]);
const rutasSeleccionadas = ref([]);
const busquedaRuta = ref('');
const busquedaTexto = ref('');
const filtroSoloRadio = ref(false);
const clienteSeleccionado = ref(null);

// Estado de UI
const cargando = ref(false);
const vistaMobile = ref('mapa'); // 'mapa' | 'lista'
const menuAbierto = ref(null);
const dropdownPosition = ref({ top: '0px', left: '0px' });

// Telemetría GPS Supervisor
const ubicacionSupervisor = ref(null); // { lat, lng }
const radioSupervisor = ref(300); // metros
const obteniendoGps = ref(false);

// Datos & KPIs
const clientesPreventa = ref([]);
const kpis = ref({
  total_preventas: 0,
  total_clientes: 0,
  monto_total: 0,
  con_gps: 0,
  sin_gps: 0,
  en_radio_conteo: 0,
  en_radio_monto: 0,
});

// Leaflet Map & Layers
let map = null;
let tileLayerInst = null;
let supervisorMarker = null;
let supervisorCircle = null;
let markersLayerGroup = null;

const TILE_LAYERS = [
  { id: 'openStreetMap', name: 'Calles', config: mapsConfig.tileLayers.openStreetMap },
  { id: 'satellite', name: 'Satélite', config: mapsConfig.tileLayers.satellite },
];
const tileActivo = ref('openStreetMap');

// ==========================================
// COMPUTED LABELS & FILTERS
// ==========================================
const catalogoFiltradoRutas = computed(() => {
  if (canalesSeleccionados.value.length === 0) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => canalesSeleccionados.value.includes(r.canal));
});

const rutasFiltradasPorBusqueda = computed(() => {
  if (!busquedaRuta.value) return catalogoFiltradoRutas.value;
  const q = busquedaRuta.value.toLowerCase();
  return catalogoFiltradoRutas.value.filter(r =>
    (r.ruta && r.ruta.toLowerCase().includes(q)) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelCanales = computed(() => {
  if (canalesSeleccionados.value.length === 0) return 'Ninguno';
  if (canalesSeleccionados.value.length === canalesDisponibles.value.length) return 'Todos';
  return `${canalesSeleccionados.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (rutasSeleccionadas.value.length === 0) return 'Todas';
  return `${rutasSeleccionadas.value.length} sel.`;
});

const clientesFiltrados = computed(() => {
  let list = clientesPreventa.value;

  if (filtroSoloRadio.value && ubicacionSupervisor.value) {
    list = list.filter(c => c.en_radio);
  }

  if (busquedaTexto.value) {
    const q = busquedaTexto.value.toLowerCase().trim();
    list = list.filter(c =>
      (c.cliente && c.cliente.toLowerCase().includes(q)) ||
      (c.codigo_cliente && String(c.codigo_cliente).toLowerCase().includes(q)) ||
      (c.vendedor && c.vendedor.toLowerCase().includes(q)) ||
      (c.pedidos && c.pedidos.some(p => String(p.nro_preventa).toLowerCase().includes(q)))
    );
  }

  return list;
});

// ==========================================
// MENÚS DROPDOWN POSICIONADOS
// ==========================================
function toggleMenu(tipo, event) {
  if (menuAbierto.value === tipo) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  dropdownPosition.value = {
    top: `${rect.bottom + 6}px`,
    left: `${Math.min(rect.left, window.innerWidth - 300)}px`,
  };
  menuAbierto.value = tipo;
}

function aplicarFiltros() {
  menuAbierto.value = null;
  cargarPreventas();
}

function formatoMoneda(val) {
  if (!val) return '0.00';
  return Number(val).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ==========================================
// PETICIONES DE DATOS
// ==========================================
async function cargarPreventas() {
  cargando.value = true;
  try {
    const payload = {
      fecha: filtroFecha.value,
      canales: canalesSeleccionados.value,
      rutas: rutasSeleccionadas.value,
      latitud: ubicacionSupervisor.value?.lat || null,
      longitud: ubicacionSupervisor.value?.lng || null,
      radio: radioSupervisor.value,
    };

    const res = await axios.post(route('supervisor.preventas.data'), payload);
    clientesPreventa.value = res.data.clientes || [];
    kpis.value = res.data.kpis || {
      total_preventas: 0,
      total_clientes: 0,
      monto_total: 0,
      con_gps: 0,
      sin_gps: 0,
      en_radio_conteo: 0,
      en_radio_monto: 0,
    };

    renderizarMarcadoresEnMapa();
  } catch (error) {
    console.error('Error al cargar preventas:', error);
  } finally {
    cargando.value = false;
  }
}

// ==========================================
// UBICACIÓN GPS SUPERVISOR
// ==========================================
function obtenerUbicacionActual() {
  if (!navigator.geolocation) {
    alert('Tu navegador no soporta geolocalización.');
    return;
  }

  obteniendoGps.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      ubicacionSupervisor.value = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
      };
      obteniendoGps.value = false;

      if (map) {
        dibujarSupervisorEnMapa();
        map.flyTo([ubicacionSupervisor.value.lat, ubicacionSupervisor.value.lng], 16, { duration: 1.2 });
      }

      cargarPreventas();
    },
    (err) => {
      obteniendoGps.value = false;
      console.warn('GPS denegado o no disponible:', err.message);
      // Fallback a El Alto Centro para demostración / prueba si está bloqueado
      ubicacionSupervisor.value = {
        lat: mapsConfig.defaultLocation.lat,
        lng: mapsConfig.defaultLocation.lng,
      };
      if (map) {
        dibujarSupervisorEnMapa();
        map.flyTo([mapsConfig.defaultLocation.lat, mapsConfig.defaultLocation.lng], 15);
      }
      cargarPreventas();
    },
    { enableHighAccuracy: true, timeout: 8000 }
  );
}

function seleccionarRadio(r) {
  radioSupervisor.value = r;
  if (ubicacionSupervisor.value && map) {
    dibujarSupervisorEnMapa();
    cargarPreventas();
  }
}

function limpiarUbicacion() {
  ubicacionSupervisor.value = null;
  filtroSoloRadio.value = false;
  if (supervisorMarker && map) map.removeLayer(supervisorMarker);
  if (supervisorCircle && map) map.removeLayer(supervisorCircle);
  supervisorMarker = null;
  supervisorCircle = null;
  cargarPreventas();
}

function dibujarSupervisorEnMapa() {
  if (!map || !ubicacionSupervisor.value) return;

  if (supervisorMarker) map.removeLayer(supervisorMarker);
  if (supervisorCircle) map.removeLayer(supervisorCircle);

  const latlng = [ubicacionSupervisor.value.lat, ubicacionSupervisor.value.lng];

  // Marcador estilo Radar Pulsing
  const icon = L.divIcon({
    className: 'custom-supervisor-marker',
    html: `
      <div class="relative flex items-center justify-center">
        <span class="absolute w-8 h-8 rounded-full bg-emerald-500/30 animate-ping"></span>
        <span class="w-4 h-4 rounded-full bg-emerald-600 border-2 border-white shadow-lg flex items-center justify-center">
          <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
        </span>
      </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
  });

  supervisorMarker = L.marker(latlng, { icon, zIndexOffset: 1000 }).addTo(map);

  // Halo / Círculo de Proximidad
  supervisorCircle = L.circle(latlng, {
    radius: radioSupervisor.value,
    color: '#10B981',
    fillColor: '#10B981',
    fillOpacity: 0.12,
    weight: 1.5,
    dashArray: '4, 4',
  }).addTo(map);
}

// ==========================================
// INICIALIZACIÓN DE LEAFLET
// ==========================================
function initMap() {
  map = L.map('map-preventas', {
    center: [mapsConfig.defaultLocation.lat, mapsConfig.defaultLocation.lng],
    zoom: mapsConfig.defaultLocation.zoom || 13,
    zoomControl: false,
    attributionControl: false,
  });

  L.control.zoom({ position: 'bottomright' }).addTo(map);

  tileLayerInst = L.tileLayer(mapsConfig.tileLayers.openStreetMap.url, {
    subdomains: mapsConfig.tileLayers.openStreetMap.subdomains || 'abc',
    maxZoom: 19,
  }).addTo(map);

  markersLayerGroup = L.layerGroup().addTo(map);
}

function cambiarTileLayer(id) {
  tileActivo.value = id;
  const tileConf = TILE_LAYERS.find(t => t.id === id)?.config;
  if (map && tileLayerInst && tileConf) {
    map.removeLayer(tileLayerInst);
    tileLayerInst = L.tileLayer(tileConf.url, {
      subdomains: tileConf.subdomains || 'abc',
      maxZoom: 19,
    }).addTo(map);
  }
}

// ==========================================
// RENDERIZADO DE MARCADORES DE PREVENTA
// ==========================================
function renderizarMarcadoresEnMapa() {
  if (!map || !markersLayerGroup) return;

  markersLayerGroup.clearLayers();
  const bounds = L.latLngBounds();
  let puntosConGps = 0;

  clientesPreventa.value.forEach(c => {
    if (!c.latitud || !c.longitud) return;

    const latlng = [c.latitud, c.longitud];
    bounds.extend(latlng);
    puntosConGps++;

    const colorRuta = getRutaColor(c.ruta);
    const radioHaloClass = c.en_radio ? 'ring-4 ring-emerald-400 animate-pulse' : '';

    const icon = L.divIcon({
      className: 'custom-preventa-marker',
      html: `
        <div class="relative flex items-center justify-center cursor-pointer group">
          <div class="w-6 h-6 rounded-full border-2 border-white shadow-md flex items-center justify-center font-bold text-[9px] text-white ${radioHaloClass}"
               style="background-color: ${colorRuta}">
            ${c.pedidos.length}
          </div>
        </div>
      `,
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    });

    const marker = L.marker(latlng, { icon }).addTo(markersLayerGroup);

    // Popup Minimalista con Desglose
    const popupContent = `
      <div class="p-1 font-sans text-xs text-slate-800" style="min-width: 220px;">
        <div class="flex items-center justify-between border-b border-slate-200 pb-1 mb-1.5">
          <span class="font-mono text-[9px] font-bold px-1.5 py-0.2 rounded text-white" style="background-color: ${colorRuta}">
            ${formatRuta(c.ruta)}
          </span>
          <span class="font-bold text-emerald-600 font-mono text-xs">Bs. ${formatoMoneda(c.total_monto)}</span>
        </div>
        <div class="font-bold text-slate-900 text-xs mb-0.5">${c.cliente}</div>
        <div class="text-[10px] text-slate-500 font-mono">Cód: ${c.codigo_cliente} • ${c.pedidos.length} pedido(s)</div>
        <div class="text-[10px] text-slate-500 font-mono mt-0.5">${c.direccion || 'Sin dirección'}</div>
        ${c.distancia_metros !== null ? `<div class="text-[10px] text-emerald-600 font-bold font-mono mt-1">Distancia: ${c.distancia_metros}m</div>` : ''}
      </div>
    `;

    marker.bindPopup(popupContent, { maxWidth: 280, className: 'custom-leaflet-popup' });

    marker.on('click', () => {
      clienteSeleccionado.value = c;
    });
  });

  if (puntosConGps > 0 && !ubicacionSupervisor.value) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
  }
}

function enfocarClienteEnMapa(c) {
  if (!c.latitud || !c.longitud || !map) return;
  clienteSeleccionado.value = c;
  vistaMobile.value = 'mapa';

  map.flyTo([c.latitud, c.longitud], 17, { duration: 1 });

  // Abrir popup
  nextTick(() => {
    markersLayerGroup.eachLayer(layer => {
      if (layer instanceof L.Marker) {
        const pos = layer.getLatLng();
        if (Math.abs(pos.lat - c.latitud) < 0.00001 && Math.abs(pos.lng - c.longitud) < 0.00001) {
          layer.openPopup();
        }
      }
    });
  });
}

// ==========================================
// CICLO DE VIDA
// ==========================================
onMounted(() => {
  nextTick(() => {
    initMap();
    cargarPreventas();
  });
});

onUnmounted(() => {
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<style>
.custom-leaflet-popup .leaflet-popup-content-wrapper {
  border-radius: 12px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(226, 232, 240, 0.8);
  padding: 2px;
}
.custom-leaflet-popup .leaflet-popup-content {
  margin: 8px 10px;
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
