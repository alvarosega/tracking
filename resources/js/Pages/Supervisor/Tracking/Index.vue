<template>
  <TrackingLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7] font-sans text-[#1D1D1F]">
      <!-- ======================================================= -->
      <!-- BARRA DE FILTROS Y CONTROL SUPERIOR (SIMILAR A PREVENTA) -->
      <!-- ======================================================= -->
      <header class="bg-white/95 backdrop-blur-md border-b border-[#E5E5EA] px-3 sm:px-4 py-2 shrink-0 relative z-30">
        <!-- Backdrop para dropdowns -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- FILA 1: Filtros Principales (Fecha, Canal, Ruta, Buscador, Consultar y KPIs Rápidos) -->
        <div class="flex items-center justify-between gap-2 sm:gap-3 flex-wrap">
          <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
            <!-- Selector de Fecha -->
            <div class="flex items-center bg-[#F2F2F7] px-2.5 py-1 rounded-lg border border-[#E5E5EA] shrink-0 text-xs font-mono shadow-2xs">
              <span class="text-[10px] text-[#86868B] font-sans font-semibold mr-1 uppercase">Fecha:</span>
              <input
                type="date"
                v-model="filtroFecha"
                @change="cargarDatos"
                class="bg-transparent text-xs font-bold text-[#1D1D1F] border-0 p-0 focus:ring-0 cursor-pointer"
              />
            </div>

            <!-- Selector de Canal -->
            <div class="relative shrink-0">
              <button
                type="button"
                @click.stop="toggleMenu('canales', $event)"
                class="h-7 sm:h-8 px-2.5 bg-[#FBFBFD] hover:bg-[#F2F2F7] border border-[#E5E5EA] rounded-lg text-xs font-medium text-[#1D1D1F] flex items-center gap-1.5 transition-colors cursor-pointer select-none shadow-2xs"
              >
                <span class="text-[#86868B] font-normal">Canal:</span>
                <span class="font-bold text-[#0071E3]">{{ canalSeleccionado || 'Todos' }}</span>
                <svg class="w-3.5 h-3.5 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>

            <!-- Selector de Ruta con Vendedor -->
            <div class="relative shrink-0">
              <button
                type="button"
                @click.stop="toggleMenu('rutas', $event)"
                class="h-7 sm:h-8 px-2.5 bg-[#FBFBFD] hover:bg-[#F2F2F7] border border-[#E5E5EA] rounded-lg text-xs font-medium text-[#1D1D1F] flex items-center gap-1.5 transition-colors cursor-pointer select-none shadow-2xs"
                :class="{ 'border-sky-500 bg-sky-50 text-sky-900 font-semibold': rutaSeleccionada }"
              >
                <span class="text-[#86868B] font-normal">Ruta:</span>
                <span class="font-bold text-[#1D1D1F] max-w-[140px] sm:max-w-[180px] truncate">
                  {{ etiquetaRutaSeleccionada }}
                </span>
                <svg class="w-3.5 h-3.5 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>

            <!-- Buscador Rápido de Clientes -->
            <div class="relative flex items-center h-7 sm:h-8 px-2 bg-[#F2F2F7] rounded-lg border border-[#E5E5EA] shrink-0 text-xs">
              <svg class="w-3.5 h-3.5 text-[#86868B] mr-1.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Buscar cliente..."
                class="bg-transparent border-0 p-0 text-xs text-[#1D1D1F] placeholder:text-[#86868B] focus:ring-0 w-24 sm:w-32 lg:w-40"
              />
              <button
                v-if="busquedaTexto"
                type="button"
                @click="busquedaTexto = ''"
                class="text-[#86868B] hover:text-[#1D1D1F] font-bold text-xs ml-1 cursor-pointer"
              >
                &times;
              </button>
            </div>

            <!-- BOTÓN CONSULTAR -->
            <button
              type="button"
              @click="cargarDatos"
              :disabled="cargando"
              class="h-7 sm:h-8 px-3.5 bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white rounded-lg text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 select-none shrink-0"
              title="Consultar recorrido, visitas Android y padrón Web"
            >
              <svg v-if="!cargando" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <span v-else class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ cargando ? 'Cargando...' : 'Consultar' }}</span>
            </button>
          </div>

          <!-- Resumen de Métricas Rápidas / KPIs del Vendedor -->
          <div class="flex items-center gap-1.5 sm:gap-2 text-xs font-mono shrink-0 flex-wrap">
            <div class="px-2 py-0.5 bg-[#F2F2F7] border border-[#E5E5EA] rounded-md flex items-center gap-1 shadow-2xs">
              <span class="text-[#86868B] font-sans text-[10px]">Android:</span>
              <span class="font-bold text-[#0071E3] tabular-nums">{{ kpis.total_visitas_android }} pts</span>
            </div>

            <div class="px-2 py-0.5 bg-[#F2F2F7] border border-[#E5E5EA] rounded-md flex items-center gap-1 shadow-2xs">
              <span class="text-[#86868B] font-sans text-[10px]">Web:</span>
              <span class="font-bold text-emerald-700 tabular-nums">{{ kpis.total_visitas_web }} cli</span>
            </div>

            <div v-if="kpis.visitas_con_venta > 0" class="px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-md flex items-center gap-1 font-bold">
              <span>{{ kpis.visitas_con_venta }} ventas</span>
              <span class="text-emerald-500 font-normal">({{ porcentajeEfectividad }}%)</span>
            </div>

            <div v-if="kpis.desviacion_promedio_metros > 0" class="hidden sm:flex px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 rounded-md items-center gap-1 font-semibold" title="Desviación promedio entre Android y Padrón Web">
              <span class="text-[10px] font-sans">Desv:</span>
              <span>{{ kpis.desviacion_promedio_metros }}m</span>
            </div>

            <div v-if="kpis.visitas_fuera_rango > 0" class="px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-md flex items-center gap-1 font-bold" title="Visitas tomadas a más de 100 metros del cliente">
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
              <span>{{ kpis.visitas_fuera_rango }} fuera de rango</span>
            </div>

            <div v-if="kpis.alertas_mock > 0" class="px-2 py-0.5 bg-rose-100 text-rose-800 border border-rose-300 rounded-md flex items-center gap-1 font-bold" title="Alertas de GPS simulado / Fake GPS">
              <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-ping"></span>
              <span>{{ kpis.alertas_mock }} Mock</span>
            </div>

            <div v-if="kpis.primera_visita" class="hidden xl:flex px-2 py-0.5 bg-white border border-[#E5E5EA] rounded-md items-center gap-1 text-[11px] text-[#6E6E73]">
              <span>Horario:</span>
              <span class="font-bold text-[#1D1D1F]">{{ kpis.primera_visita }} - {{ kpis.ultima_visita }}</span>
            </div>

            <!-- Selector Móvil de Vista Mapa / Lista -->
            <div class="flex md:hidden items-center bg-[#F2F2F7] p-0.5 rounded-lg border border-[#E5E5EA] shrink-0 ml-auto">
              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="px-2 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer"
                :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
              >
                Mapa
              </button>
              <button
                type="button"
                @click="vistaMovil = 'lista'"
                class="px-2 py-1 text-xs font-semibold rounded-md transition-all cursor-pointer"
                :class="vistaMovil === 'lista' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
              >
                {{ visitaSeleccionada ? 'Ficha' : `Lista (${visitasFiltradas.length})` }}
              </button>
            </div>
          </div>
        </div>

        <!-- FILA 2: CONTROLES DE CAPAS EN EL MAPA (PINES ANDROID, PINES WEB, RECORRIDO, DESVIACIÓN) -->
        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap pt-1.5 mt-1 border-t border-slate-100 text-xs">
          <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider hidden sm:inline mr-1">
            Capas en Mapa:
          </span>

          <!-- TOGGLE: Pines Android (Marcadores de la App Móvil) -->
          <button
            type="button"
            @click="toggleCapa('android')"
            class="h-7 px-2.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer select-none border"
            :class="capasVisibles.android ? 'bg-sky-600 text-white border-sky-600 shadow-2xs font-bold' : 'bg-[#FBFBFD] text-slate-600 border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            title="Activar u ocultar los pines donde el vendedor marcó la visita en Android"
          >
            <span class="w-2 h-2 rounded-full" :class="capasVisibles.android ? 'bg-white' : 'bg-sky-500'"></span>
            <span>📱 Pines Android ({{ visitasAndroid.length }})</span>
          </button>

          <!-- TOGGLE: Pines Web (Padrón pan_ruteo) -->
          <button
            type="button"
            @click="toggleCapa('web')"
            class="h-7 px-2.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer select-none border"
            :class="capasVisibles.web ? 'bg-emerald-700 text-white border-emerald-700 shadow-2xs font-bold' : 'bg-[#FBFBFD] text-slate-600 border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            title="Activar u ocultar los pines oficiales de clientes registrados en el padrón web"
          >
            <span class="w-2 h-2 rounded-full" :class="capasVisibles.web ? 'bg-white' : 'bg-emerald-500'"></span>
            <span>🌐 Pines Web / Padrón ({{ puntosWeb.length }})</span>
          </button>

          <!-- TOGGLE: Línea del Recorrido -->
          <button
            type="button"
            @click="toggleCapa('recorrido')"
            class="h-7 px-2.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer select-none border"
            :class="capasVisibles.recorrido ? 'bg-slate-900 text-white border-slate-900 shadow-2xs font-bold' : 'bg-[#FBFBFD] text-slate-600 border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            title="Trazado de la trayectoria cronológica del vendedor a lo largo de la jornada"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            <span>🛣️ Recorrido ({{ tramos.length }} tramos)</span>
          </button>

          <!-- TOGGLE: Líneas de Desviación (Android <-> Web) -->
          <button
            type="button"
            @click="toggleCapa('desviacion')"
            class="h-7 px-2.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition-all cursor-pointer select-none border"
            :class="capasVisibles.desviacion ? 'bg-amber-600 text-white border-amber-600 shadow-2xs font-bold' : 'bg-[#FBFBFD] text-slate-600 border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            title="Líneas punteadas que muestran la distancia entre donde se tomó la visita y la tienda real"
          >
            <span class="w-2 h-2 rounded-full" :class="capasVisibles.desviacion ? 'bg-white' : 'bg-amber-500'"></span>
            <span>📏 Desviación (Android vs Web)</span>
          </button>
        </div>
      </header>

      <!-- ======================================================= -->
      <!-- MENÚS FLOTANTES TELEPORT (CANALES Y RUTAS CON BÚSQUEDA) -->
      <!-- ======================================================= -->
      <Teleport to="body">
        <!-- Menú Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-48 bg-white border border-[#E5E5EA] rounded-xl shadow-2xl p-1.5 z-[99999] text-xs font-sans"
        >
          <button
            type="button"
            @click="seleccionarCanal('')"
            class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between transition-colors"
            :class="!canalSeleccionado ? 'bg-sky-50 font-bold text-sky-700' : 'text-slate-800'"
          >
            <span>Todos los canales</span>
            <span v-if="!canalSeleccionado" class="text-sky-600 font-bold">✓</span>
          </button>
          <div class="h-px bg-slate-100 my-1"></div>
          <button
            v-for="c in canales"
            :key="c"
            type="button"
            @click="seleccionarCanal(c)"
            class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between transition-colors"
            :class="canalSeleccionado === c ? 'bg-sky-50 font-bold text-sky-700' : 'text-slate-800'"
          >
            <span class="font-medium">{{ c }}</span>
            <span v-if="canalSeleccionado === c" class="text-sky-600 font-bold">✓</span>
          </button>
        </div>

        <!-- Menú Rutas con Buscador de Preventista / Ruta -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-72 bg-white border border-[#E5E5EA] rounded-xl shadow-2xl p-2 z-[99999] text-xs font-sans"
        >
          <div class="relative mb-1.5">
            <input
              v-model="busquedaRutaTexto"
              type="text"
              placeholder="Buscar ruta o preventista..."
              class="w-full h-7 px-2.5 bg-[#F2F2F7] border border-[#E5E5EA] rounded-lg text-xs text-[#1D1D1F] focus:outline-none focus:border-sky-500"
              autofocus
            />
          </div>

          <div class="max-h-60 overflow-y-auto space-y-0.5 divide-y divide-slate-100">
            <div v-if="rutasFiltradasEnDropdown.length === 0" class="py-3 text-center text-xs text-slate-400">
              No se encontraron rutas
            </div>
            <button
              v-for="r in rutasFiltradasEnDropdown"
              :key="r.ruta"
              type="button"
              @click="seleccionarRuta(r.ruta)"
              class="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between transition-colors pt-1"
              :class="rutaSeleccionada === r.ruta ? 'bg-sky-50 text-sky-900 font-bold' : 'text-slate-800'"
            >
              <div class="truncate">
                <span class="font-bold text-xs">{{ r.ruta }}</span>
                <span class="text-[10px] text-slate-500 ml-1">({{ r.canal }})</span>
                <p v-if="r.vendedor" class="text-[10px] text-slate-500 truncate mt-0.5">
                  {{ r.vendedor }}
                </p>
              </div>
              <span v-if="rutaSeleccionada === r.ruta" class="text-sky-600 font-bold text-sm ml-2">✓</span>
            </button>
          </div>
        </div>
      </Teleport>

      <!-- ======================================================= -->
      <!-- SPLIT SCREEN: LISTA Y AUDITORÍA (IZQ) + MAPA (DER)      -->
      <!-- ======================================================= -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- PANEL IZQUIERDO: BITÁCORA Y LISTA DE VISITAS -->
        <aside
          class="w-full md:w-[420px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- MODO NORMAL: LISTADO DE VISITAS Y AUDITORÍA DE COMPORTAMIENTO -->
          <template v-if="!visitaSeleccionada">
            <!-- Selector de Filtros de Pestaña de Lista -->
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0 flex-wrap gap-1.5">
              <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-lg border border-[#E5E5EA] text-[11px]">
                <button
                  type="button"
                  @click="pestañaLista = 'todas'"
                  class="px-2 py-0.5 font-medium rounded-md transition-all cursor-pointer"
                  :class="pestañaLista === 'todas' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500'"
                >
                  Todas ({{ visitasAndroid.length }})
                </button>
                <button
                  type="button"
                  @click="pestañaLista = 'ventas'"
                  class="px-2 py-0.5 font-medium rounded-md transition-all cursor-pointer"
                  :class="pestañaLista === 'ventas' ? 'bg-white text-emerald-800 shadow-2xs font-bold' : 'text-slate-500'"
                >
                  Ventas ({{ kpis.visitas_con_venta }})
                </button>
                <button
                  type="button"
                  @click="pestañaLista = 'desviadas'"
                  class="px-2 py-0.5 font-medium rounded-md transition-all cursor-pointer"
                  :class="pestañaLista === 'desviadas' ? 'bg-white text-amber-800 shadow-2xs font-bold' : 'text-slate-500'"
                >
                  Desviadas &gt;50m
                </button>
              </div>

              <div class="text-[11px] font-mono font-bold text-slate-700">
                {{ rutaSeleccionada }}
              </div>
            </div>

            <!-- Listado Scrollable de Visitas -->
            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="cargando" class="p-8 text-center text-xs text-slate-500 animate-pulse">
                Consultando recorrido y visitas de la fecha...
              </div>

              <div v-else-if="!rutaSeleccionada" class="p-8 text-center text-xs text-slate-400 space-y-1">
                <p class="font-bold text-slate-700">Selecciona una ruta</p>
                <p>Elige una ruta en la barra superior para ver el recorrido y auditoría.</p>
              </div>

              <div v-else-if="visitasFiltradas.length === 0" class="p-8 text-center text-xs text-slate-400">
                No se encontraron visitas registradas para este filtro en la fecha seleccionada.
              </div>

              <div
                v-for="v in visitasFiltradas"
                :key="v.id"
                @click="enfocarVisita(v)"
                class="p-3 hover:bg-[#FBFBFD] transition-colors cursor-pointer"
                :class="visitaActivaId === v.id ? 'bg-sky-50/70 border-l-4 border-sky-600' : 'bg-white'"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-start gap-2.5 min-w-0">
                    <!-- Número de Orden de Visita -->
                    <span
                      class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-mono font-bold text-white shrink-0 mt-0.5 shadow-2xs"
                      :class="v.es_venta ? 'bg-emerald-600' : (v.status === 'SIN_DINERO' ? 'bg-amber-600' : 'bg-slate-700')"
                    >
                      {{ v.orden }}
                    </span>

                    <div class="min-w-0">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1">{{ v.cliente }}</span>
                        
                        <!-- Badge Estado -->
                        <span
                          class="text-[9.5px] font-mono font-bold px-1.5 py-0.2 rounded"
                          :class="v.es_venta ? 'bg-emerald-100 text-emerald-800' : (v.status === 'SIN_DINERO' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')"
                        >
                          {{ v.status }}
                        </span>

                        <span v-if="v.is_mock" class="text-[9px] font-bold text-rose-700 bg-rose-100 px-1 rounded">
                          MOCK
                        </span>
                      </div>

                      <!-- Subtítulo con Horas y Desviación -->
                      <div class="flex items-center gap-2 text-[10.5px] font-mono text-slate-500 mt-1 flex-wrap">
                        <span>📱 {{ v.hora }}</span>
                        
                        <span v-if="v.check_in_web" class="text-slate-400">|</span>
                        <span v-if="v.check_in_web" class="text-slate-600" title="Check-in registrado en Web">
                          🌐 {{ v.check_in_web }}
                        </span>

                        <span class="text-slate-400">|</span>
                        
                        <!-- Badge Desviación -->
                        <span
                          class="font-bold px-1 rounded"
                          :class="v.desviacion_metros > 100 ? 'bg-rose-100 text-rose-700' : (v.desviacion_metros > 40 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700')"
                          title="Distancia entre coordenadas Android y coordenadas del padrón web"
                        >
                          Desv: {{ v.desviacion_metros }}m
                        </span>

                        <span v-if="v.monto > 0" class="text-emerald-700 font-bold ml-auto">
                          Bs {{ v.monto }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Botón Ver Fotos -->
                  <button
                    v-if="v.photo_url"
                    type="button"
                    @click.stop="abrirExpedienteCliente(v)"
                    class="text-sky-600 hover:text-sky-800 font-medium text-[11px] shrink-0 cursor-pointer flex items-center gap-0.5 mt-0.5"
                    title="Ver fotografía de la visita"
                  >
                    <span>Foto</span>
                    <span>&rarr;</span>
                  </button>
                </div>
              </div>
            </div>
          </template>

          <!-- MODO FICHA: DETALLES Y FOTOGRAFÍA DE LA VISITA SELECCIONADA -->
          <template v-else>
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="visitaSeleccionada = null"
                class="text-xs font-semibold text-sky-600 hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>&larr;</span> Volver al listado
              </button>

              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden text-xs text-[#1D1D1F] font-semibold bg-white border border-[#E5E5EA] px-2 py-0.5 rounded-md"
              >
                Ver en mapa
              </button>
            </div>

            <div class="p-3 bg-white border-b border-[#E5E5EA] shrink-0 space-y-1.5">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-slate-900 leading-tight">{{ visitaSeleccionada.cliente }}</h3>
                  <p class="text-[10px] font-mono text-slate-500 mt-0.5">
                    Visitado a las {{ visitaSeleccionada.hora }} • Orden #{{ visitaSeleccionada.orden }}
                  </p>
                </div>
                <span
                  class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded shrink-0"
                  :class="visitaSeleccionada.es_venta ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                >
                  {{ visitaSeleccionada.status }}
                </span>
              </div>

              <!-- Comparativa Android vs Web -->
              <div class="bg-slate-50 p-2 rounded-lg border border-slate-200 text-[11px] font-mono space-y-1">
                <div class="flex justify-between">
                  <span class="text-slate-500">Desviación:</span>
                  <span class="font-bold" :class="visitaSeleccionada.desviacion_metros > 50 ? 'text-rose-600' : 'text-emerald-700'">
                    {{ visitaSeleccionada.desviacion_metros }} metros
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-500">Android GPS:</span>
                  <span class="text-slate-800">{{ visitaSeleccionada.lat.toFixed(5) }}, {{ visitaSeleccionada.lng.toFixed(5) }}</span>
                </div>
                <div v-if="visitaSeleccionada.web_lat" class="flex justify-between">
                  <span class="text-slate-500">Web Padrón:</span>
                  <span class="text-slate-800">{{ visitaSeleccionada.web_lat.toFixed(5) }}, {{ visitaSeleccionada.web_lng.toFixed(5) }}</span>
                </div>
                <div v-if="visitaSeleccionada.monto > 0" class="flex justify-between border-t border-slate-200 pt-1 font-bold text-emerald-700">
                  <span>Monto Preventa:</span>
                  <span>Bs {{ visitaSeleccionada.monto }}</span>
                </div>
              </div>
            </div>

            <!-- Galería de Fotos del Cliente -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-[#F5F5F7]">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Fotografías del Cliente</span>
                <span class="text-[10px] font-mono text-[#6E6E73]">{{ fotosHistoricasCliente.length }} foto(s)</span>
              </div>

              <div v-if="cargandoFotos" class="p-8 text-center text-xs text-slate-500 animate-pulse">
                Cargando histórico de fotografías...
              </div>

              <div v-else-if="fotosHistoricasCliente.length === 0" class="p-6 text-center text-xs text-slate-400">
                No hay fotografías adicionales registradas para este cliente.
              </div>

              <div
                v-for="(vis, idx) in fotosHistoricasCliente"
                :key="vis.id"
                class="bg-white border border-[#E5E5EA] rounded-lg p-2.5 flex items-start gap-3 hover:border-slate-400 transition-colors"
              >
                <div
                  @click="abrirCarruselEn(idx)"
                  class="w-16 h-16 rounded-md overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] shrink-0 cursor-pointer relative group"
                >
                  <img
                    v-if="vis.photo_url"
                    :src="vis.photo_url"
                    alt="Visita"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    loading="lazy"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-[9px] text-[#86868B]">
                    Sin foto
                  </div>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded border border-[#E5E5EA]"
                      :class="vis.status === 'PREVENTA' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700'"
                    >
                      {{ vis.status }}
                    </span>
                    <span class="text-[10px] font-mono text-[#86868B]">{{ vis.fecha }} {{ vis.hora }}</span>
                  </div>
                  <p class="text-[10px] font-mono text-[#1D1D1F] mt-1">
                    Ruta: {{ vis.ruta }} • {{ vis.vendedor }}
                  </p>
                  <p v-if="vis.comments" class="text-[10px] text-[#6E6E73] mt-0.5 italic line-clamp-2">
                    "{{ vis.comments }}"
                  </p>
                </div>
              </div>
            </div>
          </template>
        </aside>

        <!-- PANEL DERECHO: VISOR CARTOGRÁFICO LEAFLET (PINES ANDROID, WEB, RECORRIDO Y DESVIACIÓN) -->
        <main
          class="flex-1 relative min-h-0 bg-slate-100"
          :class="vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full'"
        >
          <div id="map-tracking-dia" class="absolute inset-0 w-full h-full"></div>

          <!-- LEYENDA DEL MAPA FLOTANTE -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-md border border-slate-200 rounded-xl p-3 shadow-lg z-[500] text-xs max-w-[260px] font-sans">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Comportamiento en Mapa</p>
            
            <div class="space-y-1.5 text-[11px] pb-2 border-b border-slate-100">
              <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-sky-600 text-white font-mono font-bold text-[9px] flex items-center justify-center shrink-0">1</span>
                <span>Pin Android (Ubicación Celular)</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-md bg-emerald-700 text-white font-mono font-bold text-[9px] flex items-center justify-center shrink-0">W</span>
                <span>Pin Web (Padrón Oficial)</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-4 h-0.5 bg-slate-900 shrink-0"></span>
                <span>Trayecto Recorrido</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-4 border-b-2 border-dashed border-amber-500 shrink-0"></span>
                <span>Línea Desviación (Desfase)</span>
              </div>
            </div>

            <div class="pt-2 space-y-1 text-[10px] text-slate-600 font-mono">
              <div class="flex justify-between">
                <span>Total Android:</span>
                <span class="font-bold text-sky-700">{{ visitasAndroid.length }}</span>
              </div>
              <div class="flex justify-between">
                <span>Total Web:</span>
                <span class="font-bold text-emerald-700">{{ puntosWeb.length }}</span>
              </div>
              <div class="flex justify-between">
                <span>Desv. Promedio:</span>
                <span class="font-bold text-amber-700">{{ kpis.desviacion_promedio_metros }}m</span>
              </div>
            </div>
          </div>
        </main>
      </div>

      <!-- ======================================================= -->
      <!-- MODAL LIGHTBOX CARRUSEL FOTOGRÁFICO                     -->
      <!-- ======================================================= -->
      <Teleport to="body">
        <div
          v-if="modalCarruselAbierto && fotoActiva"
          class="fixed inset-0 z-[99999] bg-black/85 backdrop-blur-md flex flex-col items-center justify-between p-4 select-none"
        >
          <div class="w-full max-w-4xl flex items-center justify-between text-white pb-2 border-b border-white/10">
            <div>
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Auditoría Fotográfica</span>
              <h4 class="text-sm font-semibold text-white">{{ visitaSeleccionada?.cliente }}</h4>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono">
              <span class="text-white/60">{{ indiceFotoActiva + 1 }} de {{ fotosHistoricasCliente.length }}</span>
              <button
                type="button"
                @click="cerrarCarrusel"
                class="p-1 rounded-full text-white/80 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div class="relative w-full max-w-4xl flex-1 flex items-center justify-center my-3 overflow-hidden">
            <button
              v-if="fotosHistoricasCliente.length > 1"
              type="button"
              @click.stop="fotoAnterior"
              class="absolute left-2 z-10 p-2.5 rounded-full bg-black/50 text-white/80 hover:text-white hover:bg-black/80 backdrop-blur-xs transition-all cursor-pointer"
              title="Anterior"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <div class="max-h-[70vh] flex flex-col items-center justify-center">
              <img
                :src="fotoActiva.photo_url"
                :alt="fotoActiva.status"
                class="max-h-[68vh] max-w-full rounded-lg object-contain shadow-2xl border border-white/10"
              />
            </div>

            <button
              v-if="fotosHistoricasCliente.length > 1"
              type="button"
              @click.stop="fotoSiguiente"
              class="absolute right-2 z-10 p-2.5 rounded-full bg-black/50 text-white/80 hover:text-white hover:bg-black/80 backdrop-blur-xs transition-all cursor-pointer"
              title="Siguiente"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div class="w-full max-w-4xl bg-white/10 backdrop-blur-sm rounded-lg p-3 text-white text-xs border border-white/10 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span
                class="px-2 py-0.5 rounded font-mono font-bold text-[10px]"
                :class="fotoActiva.status === 'PREVENTA' ? 'bg-emerald-600 text-white' : 'bg-slate-700 text-white'"
              >
                {{ fotoActiva.status }}
              </span>
              <span class="font-mono text-white/80">{{ fotoActiva.fecha }} a las {{ fotoActiva.hora }}</span>
              <span class="text-white/40">•</span>
              <span class="font-mono text-white/80">Ruta: {{ fotoActiva.ruta }}</span>
            </div>

            <p v-if="fotoActiva.comments" class="text-white/70 italic text-[11px] max-w-md truncate">
              "{{ fotoActiva.comments }}"
            </p>

            <a
              :href="fotoActiva.photo_url"
              target="_blank"
              class="text-[11px] text-sky-400 hover:underline font-medium"
            >
              Abrir original &rarr;
            </a>
          </div>
        </div>
      </Teleport>
    </div>
  </TrackingLayout>
</template>

<script setup>
import { ref, computed, onMounted, Teleport } from 'vue';
import axios from 'axios';
import TrackingLayout from '@/Pages/Supervisor/Tracking/Layout.vue';
import { mapsConfig } from '@/Config/maps';
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
  fecha_default: {
    type: String,
    default: '',
  },
});

// Filtros Reactivos
const filtroFecha = ref(props.fecha_default || new Date().toISOString().slice(0, 10));
const canalSeleccionado = ref('');
const rutaSeleccionada = ref(props.catalogo_rutas[0]?.ruta || '');
const busquedaTexto = ref('');
const busquedaRutaTexto = ref('');

// Control de Capas en el Mapa
const capasVisibles = ref({
  android: true,
  web: true,
  recorrido: true,
  desviacion: true,
});

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const vistaMovil = ref('mapa');
const pestañaLista = ref('todas');

// Datos
const cargando = ref(false);
const puntosTelemetria = ref([]);
const visitasAndroid = ref([]);
const puntosWeb = ref([]);
const lineasDesviacion = ref([]);
const tramos = ref([]);
const kpis = ref({
  total_visitas_android: 0,
  total_visitas_web: 0,
  visitas_con_venta: 0,
  monto_total_preventa: 0,
  desviacion_promedio_metros: 0,
  visitas_fuera_rango: 0,
  primera_visita: null,
  ultima_visita: null,
  alertas_mock: 0,
  bateria_actual: null,
  velocidad_actual: null,
  pasos_actual: 0,
  ultimo_reporte: null,
  en_movimiento: false,
});

// Ficha de Visita y Carrusel
const visitaActivaId = ref(null);
const visitaSeleccionada = ref(null);
const fotosHistoricasCliente = ref([]);
const cargandoFotos = ref(false);
const modalCarruselAbierto = ref(false);
const indiceFotoActiva = ref(0);

const fotoActiva = computed(() => {
  if (!fotosHistoricasCliente.value || fotosHistoricasCliente.value.length === 0) return null;
  return fotosHistoricasCliente.value[indiceFotoActiva.value] || null;
});

let map = null;
let layerGroupAndroid = null;
let layerGroupWeb = null;
let layerGroupRecorrido = null;
let layerGroupDesviacion = null;

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
  if (!canalSeleccionado.value) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => r.canal === canalSeleccionado.value);
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRutaTexto.value.trim()) return rutasDisponibles.value;
  const q = busquedaRutaTexto.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const etiquetaRutaSeleccionada = computed(() => {
  if (!rutaSeleccionada.value) return 'Seleccionar ruta...';
  const r = props.catalogo_rutas.find(item => item.ruta === rutaSeleccionada.value);
  if (!r) return rutaSeleccionada.value;
  return r.vendedor ? `${r.ruta} - ${r.vendedor}` : r.ruta;
});

const porcentajeEfectividad = computed(() => {
  const tot = kpis.value.total_visitas_android || 0;
  if (tot === 0) return 0;
  return Math.round(((kpis.value.visitas_con_venta || 0) / tot) * 100);
});

// Filtrado reactivo de visitas en la bitácora lateral
const visitasFiltradas = computed(() => {
  let list = visitasAndroid.value || [];

  // Pestaña
  if (pestañaLista.value === 'ventas') {
    list = list.filter(v => v.es_venta);
  } else if (pestañaLista.value === 'desviadas') {
    list = list.filter(v => (v.desviacion_metros || 0) > 50);
  }

  // Búsqueda de texto
  if (busquedaTexto.value.trim()) {
    const q = busquedaTexto.value.toLowerCase();
    list = list.filter(v =>
      (v.cliente && v.cliente.toLowerCase().includes(q)) ||
      (v.cliente_id && String(v.cliente_id).includes(q)) ||
      (v.status && v.status.toLowerCase().includes(q))
    );
  }

  return list;
});

function toggleMenu(nombre, event) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const anchoMenu = nombre === 'rutas' ? 288 : 192;

  let leftPos = rect.left;
  if (leftPos + anchoMenu > window.innerWidth - 8) {
    leftPos = Math.max(8, window.innerWidth - anchoMenu - 8);
  }

  posicionMenu.value = {
    top: rect.bottom + 4,
    left: leftPos,
  };
  menuAbierto.value = nombre;
}

function seleccionarCanal(canal) {
  canalSeleccionado.value = canal;
  menuAbierto.value = null;
  // Si la ruta actual no pertenece al canal, seleccionar la primera disponible
  const permitidas = rutasDisponibles.value;
  if (permitidas.length > 0 && !permitidas.some(r => r.ruta === rutaSeleccionada.value)) {
    rutaSeleccionada.value = permitidas[0].ruta;
  }
  cargarDatos();
}

function seleccionarRuta(ruta) {
  rutaSeleccionada.value = ruta;
  menuAbierto.value = null;
  busquedaRutaTexto.value = '';
  cargarDatos();
}

function toggleCapa(capa) {
  capasVisibles.value[capa] = !capasVisibles.value[capa];
  if (!map) return;

  const mapping = {
    android: layerGroupAndroid,
    web: layerGroupWeb,
    recorrido: layerGroupRecorrido,
    desviacion: layerGroupDesviacion,
  };

  const group = mapping[capa];
  if (!group) return;

  if (capasVisibles.value[capa]) {
    if (!map.hasLayer(group)) map.addLayer(group);
  } else {
    if (map.hasLayer(group)) map.removeLayer(group);
  }
}

async function cargarDatos() {
  if (!rutaSeleccionada.value) return;
  cargando.value = true;

  try {
    const res = await axios.post(route('supervisor.tracking.data'), {
      fecha: filtroFecha.value,
      ruta: rutaSeleccionada.value,
    });

    visitasAndroid.value = res.data.visitas_android || res.data.visitas || [];
    puntosWeb.value = res.data.puntos_web || [];
    lineasDesviacion.value = res.data.lineas_desviacion || [];
    tramos.value = res.data.tramos || [];
    puntosTelemetria.value = res.data.puntos_telemetria || res.data.puntos || [];
    kpis.value = res.data.kpis || {
      total_visitas_android: 0,
      total_visitas_web: 0,
      visitas_con_venta: 0,
      monto_total_preventa: 0,
      desviacion_promedio_metros: 0,
      visitas_fuera_rango: 0,
      primera_visita: null,
      ultima_visita: null,
      alertas_mock: 0,
      bateria_actual: null,
      velocidad_actual: null,
      pasos_actual: 0,
      ultimo_reporte: null,
      en_movimiento: false,
    };

    renderizarMapa();
  } catch (err) {
    console.error('Error cargando tracking del día:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarMapa() {
  if (!map || !layerGroupAndroid || !layerGroupWeb || !layerGroupRecorrido || !layerGroupDesviacion) return;

  // Limpiar todas las capas anteriores
  layerGroupAndroid.clearLayers();
  layerGroupWeb.clearLayers();
  layerGroupRecorrido.clearLayers();
  layerGroupDesviacion.clearLayers();

  const boundsList = [];

  // ========================================================
  // 1. DIBUJAR PINES ANDROID (UBICACIÓN CELULAR DEL VENDEDOR)
  // ========================================================
  visitasAndroid.value.forEach(v => {
    if (!v.lat || !v.lng) return;
    boundsList.push(L.latLng(v.lat, v.lng));

    const colorPin = v.es_venta ? '#16a34a' : (v.status === 'SIN_DINERO' ? '#d97706' : (v.is_mock ? '#dc2626' : '#0284c7'));

    const markerHtml = `
      <div style="
        width: 26px;
        height: 26px;
        background-color: ${colorPin};
        border: 2px solid #FFFFFF;
        box-shadow: 0 2px 6px rgba(0,0,0,0.35);
        border-radius: 50%;
        color: white;
        font-family: monospace;
        font-size: 11px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
      ">
        ${v.orden}
      </div>
    `;

    const iconAndroid = L.divIcon({
      html: markerHtml,
      className: 'pin-tracking-android',
      iconSize: [26, 26],
      iconAnchor: [13, 13],
      popupAnchor: [0, -13],
    });

    const marker = L.marker([v.lat, v.lng], { icon: iconAndroid });

    const popupContent = document.createElement('div');
    popupContent.style.fontFamily = '-apple-system, BlinkMacSystemFont, sans-serif';
    popupContent.style.fontSize = '11px';
    popupContent.style.lineHeight = '1.4';
    popupContent.style.color = '#1D1D1F';
    popupContent.style.minWidth = '220px';

    popupContent.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
        <span style="font-weight: 800; font-size: 12px; color: #0f172a;">#${v.orden} - ${v.cliente}</span>
        <span style="background-color: ${v.es_venta ? '#dcfce7' : '#f1f5f9'}; color: ${v.es_venta ? '#166534' : '#475569'}; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 4px;">
          ${v.status}
        </span>
      </div>
      <div style="font-size: 10.5px; color: #64748b; margin-bottom: 4px;">
        📱 Marcado Android: <b>${v.hora}</b>
        ${v.check_in_web ? `<br>🌐 Check-in Web: <b>${v.check_in_web}</b>` : ''}
      </div>
      <div style="background-color: ${v.desviacion_metros > 50 ? '#fee2e2' : '#f0fdf4'}; color: ${v.desviacion_metros > 50 ? '#991b1b' : '#166534'}; padding: 3px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; margin-bottom: 6px;">
        Desviación respecto a Padrón: ${v.desviacion_metros} metros
      </div>
      ${v.monto > 0 ? `<div style="font-weight: bold; color: #166534; font-size: 11px; margin-bottom: 6px;">Venta: Bs ${v.monto}</div>` : ''}
      ${v.photo_url ? `
        <div style="width: 100%; height: 85px; border-radius: 6px; overflow: hidden; margin-bottom: 6px; background-color: #f1f5f9;">
          <img src="${v.photo_url}" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
      ` : ''}
      <button
        id="btn-visita-exp-${v.id}"
        type="button"
        style="width: 100%; height: 26px; background-color: #0f172a; color: white; border: none; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;"
      >
        Ver ficha y fotografías &rarr;
      </button>
    `;

    const btn = popupContent.querySelector(`#btn-visita-exp-${v.id}`);
    if (btn) {
      btn.addEventListener('click', () => {
        abrirExpedienteCliente(v);
      });
    }

    marker.bindPopup(popupContent, { maxWidth: 240 });
    marker.addTo(layerGroupAndroid);
  });

  // ========================================================
  // 2. DIBUJAR PINES WEB (UBICACIÓN OFICIAL EN PADRÓN WEB)
  // ========================================================
  puntosWeb.value.forEach(w => {
    if (!w.lat || !w.lng) return;
    boundsList.push(L.latLng(w.lat, w.lng));

    const iconWeb = L.divIcon({
      html: `
        <div style="
          width: 22px;
          height: 22px;
          background-color: #047857;
          border: 2px solid #FFFFFF;
          box-shadow: 0 2px 5px rgba(0,0,0,0.3);
          border-radius: 6px;
          color: white;
          font-family: sans-serif;
          font-size: 10px;
          font-weight: 800;
          display: flex;
          align-items: center;
          justify-content: center;
        " title="Padrón Web: ${w.cliente}">
          W
        </div>
      `,
      className: 'pin-tracking-web',
      iconSize: [22, 22],
      iconAnchor: [11, 11],
      popupAnchor: [0, -11],
    });

    const marker = L.marker([w.lat, w.lng], { icon: iconWeb });

    const popupWeb = `
      <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; line-height: 1.4; color: #0f172a; min-width: 210px;">
        <div style="font-weight: 800; font-size: 12px; margin-bottom: 2px; color: #047857;">
          🌐 ${w.cliente}
        </div>
        <div style="font-size: 10px; color: #64748b; margin-bottom: 4px;">
          Código #${w.cliente_id} • Ruta: <b>${w.ruta}</b> (${w.dia || 'Sin día'})
        </div>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 6px; border-radius: 4px; font-size: 10px; margin-bottom: 4px;">
          <b>Dirección:</b> ${w.direccion || 'Sin dirección'}<br>
          <b>Referencia:</b> ${w.referencia || 'Sin referencia'}
        </div>
        ${w.visitado_android ? `
          <div style="font-size: 10px; color: #0284c7; font-weight: 600;">
            ✓ Visitado en Android en orden #${w.orden_visita} (${w.desviacion_metros}m desfase)
          </div>
        ` : (w.check_in_web ? `
          <div style="font-size: 10px; color: #166534; font-weight: 600;">
            ✓ Marcado en Web: ${w.check_in_web} (${w.tipo_web})
          </div>
        ` : `
          <div style="font-size: 10px; color: #64748b;">
            • Cliente asignado en ruteo de la ruta
          </div>
        `)}
      </div>
    `;

    marker.bindPopup(popupWeb, { maxWidth: 230 });
    marker.addTo(layerGroupWeb);
  });

  // ========================================================
  // 3. DIBUJAR LÍNEAS DE DESVIACIÓN (DESFASE ANDROID <-> WEB)
  // ========================================================
  lineasDesviacion.value.forEach(l => {
    const colorLinea = l.distancia_metros > 100 ? '#ef4444' : (l.distancia_metros > 40 ? '#f59e0b' : '#10b981');
    const poly = L.polyline([l.from, l.to], {
      color: colorLinea,
      weight: 2,
      dashArray: '4, 4',
      opacity: 0.8,
    });

    poly.bindTooltip(`Desfase: ${l.distancia_metros}m (#${l.orden} ${l.cliente})`, {
      sticky: true,
      className: 'font-mono text-xs',
    });

    poly.addTo(layerGroupDesviacion);
  });

  // ========================================================
  // 4. DIBUJAR TRAYECTO RECORRIDO (POLILÍNEA CRONOLÓGICA)
  // ========================================================
  tramos.value.forEach(t => {
    boundsList.push(L.latLng(t.from[0], t.from[1]));
    boundsList.push(L.latLng(t.to[0], t.to[1]));

    L.polyline([t.from, t.to], {
      color: t.color || '#0071E3',
      weight: 3.5,
      opacity: 0.85,
    }).addTo(layerGroupRecorrido);
  });

  // Sincronizar visibilidad de capas según toggles
  if (!capasVisibles.value.android && map.hasLayer(layerGroupAndroid)) map.removeLayer(layerGroupAndroid);
  if (!capasVisibles.value.web && map.hasLayer(layerGroupWeb)) map.removeLayer(layerGroupWeb);
  if (!capasVisibles.value.recorrido && map.hasLayer(layerGroupRecorrido)) map.removeLayer(layerGroupRecorrido);
  if (!capasVisibles.value.desviacion && map.hasLayer(layerGroupDesviacion)) map.removeLayer(layerGroupDesviacion);

  // Ajustar encuadre de la cámara para abarcar todos los puntos
  if (boundsList.length > 0) {
    try {
      const bounds = L.latLngBounds(boundsList);
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
      }
    } catch (e) {
      // Ignorar bounds inválidos
    }
  }
}

function enfocarVisita(v) {
  visitaActivaId.value = v.id;
  if (!map) return;
  vistaMovil.value = 'mapa';
  map.flyTo([v.lat, v.lng], 17, { duration: 0.6 });
}

async function abrirExpedienteCliente(v) {
  visitaSeleccionada.value = v;
  fotosHistoricasCliente.value = [];
  vistaMovil.value = 'lista';

  cargandoFotos.value = true;
  try {
    const res = await axios.post(route('supervisor.visitas.fotos', { clienteId: v.cliente_id }));
    fotosHistoricasCliente.value = res.data.visitas || [];
  } catch (err) {
    console.error('Error cargando historial fotográfico:', err);
  } finally {
    cargandoFotos.value = false;
  }
}

function abrirCarruselEn(index) {
  indiceFotoActiva.value = index;
  modalCarruselAbierto.value = true;
}

function cerrarCarrusel() {
  modalCarruselAbierto.value = false;
}

function fotoSiguiente() {
  if (indiceFotoActiva.value < fotosHistoricasCliente.value.length - 1) {
    indiceFotoActiva.value++;
  } else {
    indiceFotoActiva.value = 0;
  }
}

function fotoAnterior() {
  if (indiceFotoActiva.value > 0) {
    indiceFotoActiva.value--;
  } else {
    indiceFotoActiva.value = fotosHistoricasCliente.value.length - 1;
  }
}

function initMap() {
  const defaultLoc = mapsConfig.defaultLocation;
  map = L.map('map-tracking-dia', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer(mapsConfig.tileLayers.openStreetMap.url, {
    maxZoom: 19,
    attribution: mapsConfig.tileLayers.openStreetMap.attribution,
    subdomains: mapsConfig.tileLayers.openStreetMap.subdomains || 'abc',
  }).addTo(map);

  layerGroupAndroid = L.featureGroup().addTo(map);
  layerGroupWeb = L.featureGroup().addTo(map);
  layerGroupRecorrido = L.featureGroup().addTo(map);
  layerGroupDesviacion = L.featureGroup().addTo(map);

  map.on('click', () => {
    menuAbierto.value = null;
  });
}

onMounted(() => {
  initMap();
  cargarDatos();
});
</script>

<style scoped>
.pin-tracking-android,
.pin-tracking-web {
  transition: transform 0.15s ease-out;
}
.pin-tracking-android:hover,
.pin-tracking-web:hover {
  transform: scale(1.15);
  z-index: 1000 !important;
}
</style>
