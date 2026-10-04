<template>
  <TrackingLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7]">
      <!-- Header de Control y Métricas de Telemetría -->
      <header class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop para dropdowns -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Filtros con Scroll Horizontal -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          <!-- Selector de Fecha -->
          <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA] shrink-0 text-xs font-mono">
            <span class="text-[10px] text-[#86868B] px-1.5">Fecha:</span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarDatos"
              class="h-6 px-1.5 text-xs font-semibold bg-white text-[#1D1D1F] border-0 rounded-[4px] focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Selector de Canal -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Canal:</span>
            <span class="font-semibold">{{ canalSeleccionado || 'Todos' }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Selector de Ruta / Vendedor -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Ruta:</span>
            <span class="font-semibold">{{ rutaSeleccionada || 'Seleccionar...' }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Switch para Mostrar / Ocultar Puntos de Fotografías -->
          <button
            type="button"
            @click="toggleVerFotos"
            class="h-7 px-2.5 border rounded-[6px] text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
            :class="mostrarFotos ? 'bg-[#0071E3] text-white border-[#0071E3]' : 'bg-[#FBFBFD] text-[#6E6E73] border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            title="Activar u ocultar los pines de fotografías en el mapa"
          >
            <span
              class="w-1.5 h-1.5 rounded-full"
              :class="mostrarFotos ? 'bg-white' : 'bg-[#86868B]'"
            ></span>
            <span>Fotos en mapa: {{ mostrarFotos ? 'ON' : 'OFF' }}</span>
          </button>
        </div>

        <!-- Métricas de Telemetría en Vivo y Control Móvil -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Consultando telemetría...
          </span>

          <div class="flex md:hidden items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2 py-1 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer"
              :class="vistaMovil === 'mapa' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'lista'"
              class="px-2 py-1 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer"
              :class="vistaMovil === 'lista' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              {{ visitaSeleccionada ? 'Ficha' : `Visitas (${visitas.length})` }}
            </button>
          </div>

          <!-- Batería Actual -->
          <div
            v-if="kpis.bateria_actual !== null"
            class="hidden lg:flex px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] items-center gap-1"
            title="Nivel de batería en el último reporte"
          >
            <span
              class="w-2 h-2 rounded-full"
              :class="kpis.bateria_actual > 50 ? 'bg-[#248A3D]' : (kpis.bateria_actual > 20 ? 'bg-[#FF9500]' : 'bg-[#C9342C] animate-pulse')"
            ></span>
            <span class="tabular-nums font-bold">{{ kpis.bateria_actual }}%</span>
          </div>

          <!-- Alerta Mock GPS -->
          <div
            v-if="kpis.alertas_mock > 0"
            class="hidden sm:flex px-2 py-0.5 bg-[#FDF0EF] text-[#C9342C] border border-[#C9342C]/20 rounded-[6px] items-center gap-1 font-bold"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-[#C9342C]"></span>
            <span>Mock: {{ kpis.alertas_mock }}</span>
          </div>

          <!-- Último Reporte -->
          <div v-if="kpis.ultimo_reporte" class="hidden xl:flex px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] items-center gap-1 text-[#6E6E73]">
            <span>Reporte:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.ultimo_reporte }}</span>
          </div>
        </div>
      </header>

      <!-- Dropdowns con Teleport -->
      <Teleport to="body">
        <!-- Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-44 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-1.5 z-[99999] text-xs"
        >
          <button
            type="button"
            @click="canalSeleccionado = ''; menuAbierto = null; cargarDatos()"
            class="w-full text-left px-2 py-1 rounded-[4px] hover:bg-[#F2F2F7] cursor-pointer"
            :class="!canalSeleccionado ? 'bg-[#F2F2F7] font-semibold text-[#0071E3]' : ''"
          >
            Todos los canales
          </button>
          <button
            v-for="c in canales"
            :key="c"
            type="button"
            @click="canalSeleccionado = c; menuAbierto = null; cargarDatos()"
            class="w-full text-left px-2 py-1 rounded-[4px] hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between"
            :class="canalSeleccionado === c ? 'bg-[#F2F2F7] font-semibold text-[#0071E3]' : ''"
          >
            <span>{{ c }}</span>
            <span v-if="canalSeleccionado === c" class="text-[#0071E3]">✓</span>
          </button>
        </div>

        <!-- Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta o vendedor..."
            class="w-full h-6 px-2 mb-1.5 text-xs bg-[#FBFBFD] border border-[#E5E5EA] rounded-[4px] focus:outline-none focus:border-[#86868B]"
          />
          <div class="max-h-56 overflow-y-auto space-y-0.5">
            <button
              v-for="r in rutasFiltradasEnDropdown"
              :key="r.ruta"
              type="button"
              @click="seleccionarRuta(r.ruta)"
              class="w-full text-left px-2 py-1 rounded-[4px] hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between"
              :class="rutaSeleccionada === r.ruta ? 'bg-[#F2F2F7] font-semibold text-[#0071E3]' : ''"
            >
              <div class="truncate">
                <span class="font-medium">{{ r.ruta }}</span>
                <span class="text-[#86868B] text-[10px] ml-1">({{ r.canal }})</span>
                <span class="text-[#86868B] text-[10px] block truncate">{{ r.vendedor }}</span>
              </div>
              <span v-if="rutaSeleccionada === r.ruta" class="text-[#0071E3]">✓</span>
            </button>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Lista de Visitas / Bitácora de Telemetría -->
        <aside
          class="w-full md:w-[420px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- MODO 1: LISTADO DE VISITAS CRONOLÓGICAS DE LA JORNADA -->
          <template v-if="!visitaSeleccionada">
            <!-- Selector de Pestaña de Lista: Visitas vs Telemetría Cruda -->
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
                <button
                  type="button"
                  @click="pestañaLista = 'visitas'"
                  class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer"
                  :class="pestañaLista === 'visitas' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73]'"
                >
                  Visitas ({{ visitas.length }})
                </button>
                <button
                  type="button"
                  @click="pestañaLista = 'telemetria'"
                  class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer"
                  :class="pestañaLista === 'telemetria' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73]'"
                >
                  Puntos GPS ({{ puntos.length }})
                </button>
              </div>

              <div class="text-[10px] font-mono text-[#86868B]">
                {{ rutaSeleccionada }}
              </div>
            </div>

            <!-- Contenido Scrollable de la Pestaña -->
            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Consultando recorrido de la fecha...
              </div>

              <div v-else-if="!rutaSeleccionada" class="p-8 text-center text-xs text-[#86868B] space-y-1">
                <p class="font-medium text-[#1D1D1F]">Selecciona una ruta</p>
                <p>Elige una ruta en la barra superior para ver su recorrido y visitas.</p>
              </div>

              <div v-else-if="pestañaLista === 'visitas' && visitas.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No se registraron fotografías de visitas para esta ruta en la fecha seleccionada.
              </div>

              <div v-else-if="pestañaLista === 'telemetria' && puntos.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No hay puntos de telemetría registrados para esta fecha en `locations`.
              </div>

              <!-- Pestaña: Visitas -->
              <template v-if="pestañaLista === 'visitas'">
                <div
                  v-for="v in visitas"
                  :key="v.id"
                  @click="enfocarVisita(v)"
                  class="p-3 hover:bg-[#FBFBFD] transition-colors cursor-pointer"
                  :class="visitaActivaId === v.id ? 'bg-[#F2F2F7]' : 'bg-white'"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex items-start gap-2 min-w-0">
                      <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-mono font-bold text-white shrink-0 mt-0.5 bg-[#0071E3]">
                        {{ v.orden }}
                      </span>
                      <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                          <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1">{{ v.cliente }}</span>
                          <span
                            class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded"
                            :class="v.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                          >
                            {{ v.status }}
                          </span>
                        </div>
                        <p class="text-[10px] font-mono text-[#86868B] mt-0.5">{{ v.hora }}</p>
                      </div>
                    </div>

                    <button
                      type="button"
                      @click.stop="abrirExpedienteCliente(v)"
                      class="text-[#0071E3] hover:underline font-sans font-medium text-[11px] shrink-0 cursor-pointer"
                    >
                      Ver fotos &rarr;
                    </button>
                  </div>
                </div>
              </template>

              <!-- Pestaña: Telemetría Cruda (Eventos y Paradas) -->
              <template v-if="pestañaLista === 'telemetria'">
                <div
                  v-for="p in puntos"
                  :key="p.id"
                  @click="enfocarPunto(p)"
                  class="p-2.5 hover:bg-[#FBFBFD] transition-colors cursor-pointer flex items-center justify-between text-[11px] font-mono"
                  :class="p.is_mock ? 'bg-[#FDF0EF]/40' : (!p.is_moving ? 'bg-[#FFF5E5]/20' : '')"
                >
                  <div class="flex items-center gap-2">
                    <span
                      class="w-2 h-2 rounded-full shrink-0"
                      :class="p.is_mock ? 'bg-[#FF3B30] animate-ping' : (!p.is_moving ? 'bg-[#FF9500]' : 'bg-[#0071E3]')"
                    ></span>
                    <span class="text-[#1D1D1F]">{{ p.hora }}</span>
                    <span v-if="p.is_mock" class="text-[9px] font-bold text-[#FF3B30] bg-[#FDF0EF] px-1 py-0.2 rounded">MOCK</span>
                    <span v-else-if="!p.is_moving" class="text-[9px] text-[#B25E00] bg-[#FFF5E5] px-1 py-0.2 rounded">PARADO</span>
                  </div>

                  <div class="flex items-center gap-3 text-[10px] text-[#6E6E73]">
                    <span>{{ p.speed }} km/h</span>
                    <span>Bat: {{ p.battery }}%</span>
                  </div>
                </div>
              </template>
            </div>
          </template>

          <!-- MODO 2: FICHA Y GALERÍA HISTÓRICA DEL CLIENTE VISITADO -->
          <template v-else>
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="visitaSeleccionada = null"
                class="text-xs font-semibold text-[#0071E3] hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>&larr;</span> Volver al recorrido
              </button>

              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden text-xs text-[#1D1D1F] font-semibold bg-white border border-[#E5E5EA] px-2 py-0.5 rounded-[4px]"
              >
                Ver en mapa
              </button>
            </div>

            <div class="p-3 bg-white border-b border-[#E5E5EA] shrink-0">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight line-clamp-1">{{ visitaSeleccionada.cliente }}</h3>
                  <p class="text-[10px] font-mono text-[#86868B] mt-0.5">
                    Visitado hoy a las {{ visitaSeleccionada.hora }}
                  </p>
                </div>
                <span
                  class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded shrink-0"
                  :class="visitaSeleccionada.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                >
                  {{ visitaSeleccionada.status }}
                </span>
              </div>
            </div>

            <!-- Carrusel Histórico de Fotos -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-[#F5F5F7]">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Historial de Fotografías</span>
                <span class="text-[10px] font-mono text-[#6E6E73]">{{ fotosHistoricasCliente.length }} foto(s)</span>
              </div>

              <div v-if="cargandoFotos" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando histórico de fotografías...
              </div>

              <div
                v-for="(vis, idx) in fotosHistoricasCliente"
                :key="vis.id"
                class="bg-white border border-[#E5E5EA] rounded-[8px] p-2.5 flex items-start gap-3 hover:border-[#86868B] transition-colors"
              >
                <div
                  @click="abrirCarruselEn(idx)"
                  class="w-16 h-16 rounded-[6px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] shrink-0 cursor-pointer relative group"
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
                      :class="vis.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
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

        <!-- Panel Derecho: Visor Cartográfico Leaflet con Tramos Dinámicos -->
        <main
          class="flex-1 relative min-h-0 bg-[#E5E5EA]"
          :class="vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full'"
        >
          <div id="map-tracking-dia" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda de Estados del Recorrido -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-[500] text-[11px] max-w-[240px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Estados de Telemetría</p>
            <div class="space-y-1 pb-1.5 border-b border-[#E5E5EA]">
              <div class="flex items-center gap-2">
                <span class="w-3 h-1 bg-[#0071E3] rounded-full shrink-0"></span>
                <span>En Movimiento</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-3 h-1 bg-[#FF9500] rounded-full shrink-0"></span>
                <span>Detenido / Parada</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-3 h-1 bg-[#FF3B30] rounded-full shrink-0"></span>
                <span class="text-[#FF3B30] font-bold">GPS Simulado (Mock)</span>
              </div>
            </div>
            <div class="mt-1.5 flex items-center justify-between text-[10px]">
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#0071E3] text-white flex items-center justify-center font-mono font-bold text-[8px]">1</span>
                <span>Fotos de visitas</span>
              </div>
              <span class="font-mono text-[#86868B]">{{ mostrarFotos ? 'Visibles' : 'Ocultas' }}</span>
            </div>
          </div>
        </main>
      </div>

      <!-- VISOR CARRUSEL LIGHTBOX -->
      <Teleport to="body">
        <div
          v-if="modalCarruselAbierto && fotoActiva"
          class="fixed inset-0 z-[99999] bg-black/85 backdrop-blur-md flex flex-col items-center justify-between p-4 select-none"
        >
          <div class="w-full max-w-4xl flex items-center justify-between text-white pb-2 border-b border-white/10">
            <div>
              <span class="text-xs font-bold uppercase tracking-wider text-[#86868B]">Auditoría Fotográfica</span>
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
                class="max-h-[68vh] max-w-full rounded-[8px] object-contain shadow-2xl border border-white/10"
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

          <div class="w-full max-w-4xl bg-white/10 backdrop-blur-sm rounded-[8px] p-3 text-white text-xs border border-white/10 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span
                class="px-2 py-0.5 rounded font-mono font-bold text-[10px]"
                :class="fotoActiva.status === 'PREVENTA' ? 'bg-[#248A3D] text-white' : 'bg-[#B25E00] text-white'"
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
              class="text-[11px] text-[#0071E3] hover:underline font-medium"
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
const mostrarFotos = ref(true); // Switch de visibilidad de fotos

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const busquedaRuta = ref('');
const vistaMovil = ref('mapa');
const pestañaLista = ref('visitas');

// Datos
const cargando = ref(false);
const puntos = ref([]);
const visitas = ref([]);
const tramos = ref([]);
const kpis = ref({
  total_puntos: 0,
  total_visitas: 0,
  bateria_actual: null,
  velocidad_actual: null,
  pasos_actual: 0,
  alertas_mock: 0,
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
let linesGroup = null;
let markersGroup = null;

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
  if (!canalSeleccionado.value) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => r.canal === canalSeleccionado.value);
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

function toggleMenu(nombre, event) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const anchoMenu = nombre === 'rutas' ? 256 : 176;

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

function seleccionarRuta(ruta) {
  rutaSeleccionada.value = ruta;
  menuAbierto.value = null;
  cargarDatos();
}

function toggleVerFotos() {
  mostrarFotos.value = !mostrarFotos.value;
  if (!map || !markersGroup) return;

  if (mostrarFotos.value) {
    if (!map.hasLayer(markersGroup)) {
      map.addLayer(markersGroup);
    }
  } else {
    if (map.hasLayer(markersGroup)) {
      map.removeLayer(markersGroup);
    }
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

    puntos.value = res.data.puntos || [];
    visitas.value = res.data.visitas || [];
    tramos.value = res.data.tramos || [];
    kpis.value = res.data.kpis || {
      total_puntos: 0,
      total_visitas: 0,
      bateria_actual: null,
      velocidad_actual: null,
      pasos_actual: 0,
      alertas_mock: 0,
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
  if (!map || !linesGroup || !markersGroup) return;

  // Limpieza con método nativo de Leaflet
  linesGroup.clearLayers();
  markersGroup.clearLayers();

  const boundsList = [];

  // 1. Trazado de segmentos con colores según estado (Movimiento vs Parada vs Mock)
  tramos.value.forEach(t => {
    boundsList.push(L.latLng(t.from[0], t.from[1]));
    boundsList.push(L.latLng(t.to[0], t.to[1]));

    L.polyline([t.from, t.to], {
      color: t.color,
      weight: 3.5,
      opacity: 0.9,
    }).addTo(linesGroup);
  });

  // 2. Marcadores Numerados de Visitas Reales del Día
  visitas.value.forEach(v => {
    boundsList.push(L.latLng(v.visita_lat, v.visita_lng));

    const markerHtml = `
      <div style="
        width: 24px;
        height: 24px;
        background-color: #0071E3;
        border: 2px solid #FFFFFF;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        border-radius: 50%;
        color: white;
        font-family: monospace;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
      ">
        ${v.orden}
      </div>
    `;

    const customIcon = L.divIcon({
      html: markerHtml,
      className: 'tracking-visita-pin',
      iconSize: [24, 24],
      iconAnchor: [12, 12],
      popupAnchor: [0, -12],
    });

    const marker = L.marker([v.visita_lat, v.visita_lng], { icon: customIcon });

    const popupContent = document.createElement('div');
    popupContent.style.fontFamily = '-apple-system, BlinkMacSystemFont, sans-serif';
    popupContent.style.fontSize = '11px';
    popupContent.style.lineHeight = '1.4';
    popupContent.style.color = '#1D1D1F';
    popupContent.style.minWidth = '210px';

    popupContent.innerHTML = `
      <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">#${v.orden} - ${v.cliente}</div>
      <div style="color: #0071E3; font-weight: bold; margin-bottom: 4px;">
        ${v.hora} • ${v.status}
      </div>
      <div style="width: 100%; height: 80px; border-radius: 4px; overflow: hidden; margin-bottom: 6px; background-color: #F2F2F7;">
        <img src="${v.photo_url}" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;" />
      </div>
      <button
        id="btn-visita-dia-${v.id}"
        type="button"
        style="width: 100%; height: 26px; background-color: #0071E3; color: white; border: none; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer;"
      >
        Ver detalles e histórico &rarr;
      </button>
    `;

    const btn = popupContent.querySelector(`#btn-visita-dia-${v.id}`);
    if (btn) {
      btn.addEventListener('click', () => {
        abrirExpedienteCliente(v);
      });
    }

    marker.bindPopup(popupContent, { maxWidth: 230 });
    marker.addTo(markersGroup);
  });

  // 3. Indicador de Última Posición Conocida
  if (puntos.value.length > 0) {
    const ultimo = puntos.value[puntos.value.length - 1];
    boundsList.push(L.latLng(ultimo.lat, ultimo.lng));

    L.circleMarker([ultimo.lat, ultimo.lng], {
      radius: 7,
      fillColor: ultimo.is_mock ? '#FF3B30' : (ultimo.is_moving ? '#248A3D' : '#FF9500'),
      color: '#FFFFFF',
      weight: 2.5,
      opacity: 1,
      fillOpacity: 1,
    }).bindTooltip(`Última ubicación: ${ultimo.hora} (${ultimo.speed} km/h)`).addTo(markersGroup);
  }

  // Respetar estado del switch
  if (!mostrarFotos.value && map.hasLayer(markersGroup)) {
    map.removeLayer(markersGroup);
  } else if (mostrarFotos.value && !map.hasLayer(markersGroup)) {
    map.addLayer(markersGroup);
  }

  // Ajustar encuadre de la cámara
  if (boundsList.length > 0) {
    try {
      const bounds = L.latLngBounds(boundsList);
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
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
  map.flyTo([v.visita_lat, v.visita_lng], 18, { duration: 0.6 });
}

function enfocarPunto(p) {
  if (!map) return;
  vistaMovil.value = 'mapa';
  map.flyTo([p.lat, p.lng], 18, { duration: 0.6 });
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
    console.error('Error cargando historial:', err);
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
  map = L.map('map-tracking-dia', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([-16.5000, -68.1500], 13);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  linesGroup = L.featureGroup().addTo(map);
  markersGroup = L.featureGroup().addTo(map);

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
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>