<template>
  <VisitasLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7]">
      <!-- Barra Superior de Control -->
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
          <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA] shrink-0">
            <span class="text-[10px] text-[#86868B] font-mono px-1.5">Fecha:</span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarDatos"
              class="h-6 px-1.5 text-xs font-mono font-semibold bg-white text-[#1D1D1F] border-0 rounded-[4px] focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Botón Canales -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Canal:</span>
            <span class="font-semibold">{{ labelCanales }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Botón Rutas -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Rutas:</span>
            <span class="font-semibold">{{ labelRutas }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>

        <!-- Indicador de Carga, Control Móvil y Métricas -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Trazando recorrido...
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
              {{ clienteSeleccionado ? 'Ficha' : `Avance (${todosLosPuntos.length})` }}
            </button>
          </div>

          <div class="hidden lg:flex px-2 py-0.5 bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
            <span class="font-bold tabular-nums">En Rango: {{ kpis.en_rango }}</span>
          </div>

          <div v-if="kpis.desviadas > 0" class="hidden lg:flex px-2 py-0.5 bg-[#FFF5E5] text-[#B25E00] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#B25E00]"></span>
            <span class="font-bold tabular-nums">Desviadas: {{ kpis.desviadas }}</span>
          </div>

          <div class="hidden sm:flex px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="text-[#86868B]">Fotos:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.total_fotos }}</span>
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
          class="fixed w-52 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodosCanales" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarCanales" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="max-h-48 overflow-y-auto space-y-1">
            <label
              v-for="c in canales"
              :key="c"
              class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
            >
              <input
                type="checkbox"
                :value="c"
                v-model="filtroCanales"
                @change="onCanalesModificados"
                class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
              />
              <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(c) }"></span>
              <span class="truncate font-medium">{{ c }}</span>
            </label>
          </div>
        </div>

        <!-- Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodasRutas" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todas ({{ rutasDisponibles.length }})</button>
            <button type="button" @click="limpiarRutas" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguna</button>
          </div>
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta..."
            class="w-full h-6 px-2 mb-1.5 text-xs bg-[#FBFBFD] border border-[#E5E5EA] rounded-[4px] focus:outline-none focus:border-[#86868B]"
          />
          <div class="max-h-56 overflow-y-auto space-y-1">
            <label
              v-for="r in rutasFiltradasEnDropdown"
              :key="r.ruta"
              class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
            >
              <input
                type="checkbox"
                :value="r.ruta"
                v-model="filtroRutas"
                @change="cargarDatos"
                class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
              />
              <div class="truncate flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-black/20" :style="{ backgroundColor: getRutaColor(r.ruta) }"></span>
                <span class="font-medium">{{ r.ruta }}</span>
                <span class="text-[#86868B] text-[10px]">({{ r.canal }})</span>
              </div>
            </label>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Cronología o Expediente Detalle -->
        <aside
          class="w-full md:w-[440px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- MODO 1: LÍNEA DE TIEMPO DEL DÍA -->
          <template v-if="!clienteSeleccionado">
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <span class="text-[11px] font-semibold text-[#86868B] uppercase tracking-wider">Cronología de Visitas</span>
              <span class="text-[10px] font-mono text-[#6E6E73]">{{ todosLosPuntos.length }} fotos</span>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando recorrido del día...
              </div>

              <div v-else-if="todosLosPuntos.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No se registraron fotografías de visitas para la fecha y rutas seleccionadas.
              </div>

              <div
                v-for="p in todosLosPuntos"
                :key="p.id"
                @click="enfocarEnMapa(p)"
                class="p-3 hover:bg-[#FBFBFD] transition-colors cursor-pointer"
                :class="puntoActivoId === p.id ? 'bg-[#F2F2F7]' : 'bg-white'"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-start gap-2 min-w-0">
                    <!-- Número de Secuencia Cronológica -->
                    <span
                      class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-mono font-bold text-white shrink-0 mt-0.5"
                      :style="{ backgroundColor: getRutaColor(p.ruta) }"
                    >
                      {{ p.orden }}
                    </span>

                    <div class="min-w-0">
                      <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1">{{ p.cliente }}</span>
                        <span
                          class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded"
                          :class="p.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                        >
                          {{ p.status }}
                        </span>
                      </div>
                      <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ p.direccion || 'Sin dirección' }}</p>
                    </div>
                  </div>

                  <!-- Hora y Delta -->
                  <div class="text-right shrink-0 font-mono text-[10px]">
                    <span class="font-bold text-[#1D1D1F] text-xs block">{{ p.hora }}</span>
                    <span v-if="p.delta_minutos !== null" class="text-[#86868B]">
                      +{{ p.delta_minutos }} min
                    </span>
                  </div>
                </div>

                <!-- Barra Inferior de la Tarjeta -->
                <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-[#E5E5EA]/70 text-[10px] font-mono">
                  <div class="flex items-center gap-1.5 text-[#6E6E73]">
                    <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getCanalColor(p.canal) }"></span>
                    <span class="text-[#1D1D1F] font-medium">{{ p.ruta }}</span>
                    <span>•</span>
                    <span
                      v-if="p.en_rango !== null"
                      :class="p.en_rango ? 'text-[#248A3D]' : 'text-[#C9342C] font-bold'"
                    >
                      {{ p.en_rango ? `a ${p.distancia_oficial_metros}m` : `⚠ Desv: ${p.distancia_oficial_metros}m` }}
                    </span>
                  </div>

                  <button
                    type="button"
                    @click.stop="abrirExpedienteCliente(p)"
                    class="text-[#0071E3] hover:underline font-sans font-medium text-[11px] cursor-pointer"
                  >
                    Ver detalles &rarr;
                  </button>
                </div>
              </div>
            </div>
          </template>

          <!-- MODO 2: EXPEDIENTE Y GALERÍA HISTÓRICA DEL CLIENTE -->
          <template v-else>
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="cerrarExpediente"
                class="text-xs font-semibold text-[#0071E3] hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>&larr;</span> Volver al avance
              </button>

              <button
                type="button"
                @click="vistaMovil = 'mapa'"
                class="md:hidden text-xs text-[#1D1D1F] font-semibold bg-white border border-[#E5E5EA] px-2 py-0.5 rounded-[4px]"
              >
                Ver en mapa
              </button>
            </div>

            <!-- Ficha Técnica -->
            <div class="p-3 bg-white border-b border-[#E5E5EA] shrink-0">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight line-clamp-1">{{ clienteSeleccionado.cliente }}</h3>
                  <p class="text-[11px] text-[#6E6E73] mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección' }}</p>
                  <p v-if="clienteSeleccionado.referencia" class="text-[10px] text-[#86868B] italic mt-0.5">
                    Ref: {{ clienteSeleccionado.referencia }}
                  </p>
                </div>

                <span
                  class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white shrink-0"
                  :style="{ backgroundColor: getCanalColor(clienteSeleccionado.canal) }"
                >
                  {{ clienteSeleccionado.canal }}
                </span>
              </div>

              <!-- Comparativa de Georreferenciación -->
              <div class="mt-2 p-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-[10px] font-mono space-y-1">
                <div class="flex justify-between">
                  <span class="text-[#86868B]">GPS de la Foto:</span>
                  <span class="font-semibold text-[#1D1D1F]">{{ clienteSeleccionado.visita_lat.toFixed(5) }}, {{ clienteSeleccionado.visita_lng.toFixed(5) }}</span>
                </div>
                <div v-if="clienteSeleccionado.oficial_lat" class="flex justify-between">
                  <span class="text-[#86868B]">GPS Oficial (Plan):</span>
                  <span class="font-semibold text-[#1D1D1F]">{{ clienteSeleccionado.oficial_lat.toFixed(5) }}, {{ clienteSeleccionado.oficial_lng.toFixed(5) }}</span>
                </div>
                <div v-if="clienteSeleccionado.distancia_oficial_metros !== null" class="flex justify-between border-t border-[#E5E5EA] pt-1">
                  <span class="text-[#86868B]">Desviación física:</span>
                  <span
                    class="font-bold"
                    :class="clienteSeleccionado.en_rango ? 'text-[#248A3D]' : 'text-[#C9342C]'"
                  >
                    {{ clienteSeleccionado.distancia_oficial_metros }} metros ({{ clienteSeleccionado.en_rango ? 'En rango' : 'Desviado' }})
                  </span>
                </div>
              </div>
            </div>

            <!-- Galería de Fotos Históricas -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-[#F5F5F7]">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Historial de Fotografías</span>
                <span class="text-[10px] font-mono text-[#6E6E73]">{{ fotosHistoricas.length }} foto(s)</span>
              </div>

              <div v-if="cargandoFotos" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando histórico de fotografías...
              </div>

              <div
                v-for="(vis, idx) in fotosHistoricas"
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

        <!-- Panel Derecho: Visor Cartográfico Leaflet con Polilíneas Segmentadas -->
        <main
          class="flex-1 relative min-h-0 bg-[#E5E5EA]"
          :class="vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full'"
        >
          <div id="map-avance" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda del Recorrido -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-[500] text-[11px] max-w-[240px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Trayectoria de Visitas</p>
            <div class="space-y-1 pb-1.5 border-b border-[#E5E5EA]">
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-[#248A3D] shrink-0"></span>
                <span>Preventa Exitosa</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-[#B25E00] shrink-0"></span>
                <span>No Venta / Cerrado</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 border-b-2 border-dashed border-[#0071E3] shrink-0"></span>
                <span>Línea de Recorrido (Ruta)</span>
              </div>
            </div>

            <div class="mt-1.5 text-[10px] text-[#86868B]">
              Los números en los pines indican el orden de visita.
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
              <h4 class="text-sm font-semibold text-white">{{ clienteSeleccionado?.cliente }}</h4>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono">
              <span class="text-white/60">{{ indiceFotoActiva + 1 }} de {{ fotosHistoricas.length }}</span>
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
              v-if="fotosHistoricas.length > 1"
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
              v-if="fotosHistoricas.length > 1"
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
              <span class="text-white/40">•</span>
              <span class="font-mono text-white/80">Vendedor: {{ fotoActiva.vendedor }}</span>
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
  </VisitasLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, Teleport } from 'vue';
import axios from 'axios';
import VisitasLayout from '@/Pages/Supervisor/Visitas/Layout.vue';
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
    default: () => new Date().toISOString().slice(0, 10),
  },
});

const CANAL_COLORS = {
  'MAY': '#0071E3',
  'TDB': '#34C759',
  'MZO': '#FF9500',
  'PROV': '#AF52DE',
  'PRT': '#8E8E93',
};

function getCanalColor(canal) {
  if (!canal) return '#8E8E93';
  return CANAL_COLORS[canal] || '#5856D6';
}

const RUTA_PALETTE = ['#0071E3', '#34C759', '#FF9500', '#AF52DE', '#1D1D1F', '#00C7BE', '#A2845E', '#EC4899'];
const rutaColorCache = new Map();

function getRutaColor(ruta) {
  if (!ruta) return '#1D1D1F';
  if (!rutaColorCache.has(ruta)) {
    const colorIndex = rutaColorCache.size % RUTA_PALETTE.length;
    rutaColorCache.set(ruta, RUTA_PALETTE[colorIndex]);
  }
  return rutaColorCache.get(ruta);
}

// Filtros Reactivos
const filtroFecha = ref(props.fecha_default);
const filtroCanales = ref([...props.canales]);
const filtroRutas = ref(props.catalogo_rutas.map(r => r.ruta));

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const busquedaRuta = ref('');
const vistaMovil = ref('mapa');

// Datos
const cargando = ref(false);
const rutasTrayectorias = ref([]);
const kpis = ref({
  total_fotos: 0,
  rutas_activas: 0,
  en_rango: 0,
  desviadas: 0,
});

const puntoActivoId = ref(null);
const clienteSeleccionado = ref(null);
const fotosHistoricas = ref([]);
const cargandoFotos = ref(false);

// Carrusel
const modalCarruselAbierto = ref(false);
const indiceFotoActiva = ref(0);

const fotoActiva = computed(() => {
  if (!fotosHistoricas.value || fotosHistoricas.value.length === 0) return null;
  return fotosHistoricas.value[indiceFotoActiva.value] || null;
});

let map = null;
let markersLayer = null;
let linesLayer = null;
const markersMap = new Map();

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
  if (filtroCanales.value.length === 0) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => filtroCanales.value.includes(r.canal));
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === props.canales.length) return 'Todos';
  if (filtroCanales.value.length === 0) return 'Ninguno';
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 0) return 'Ninguna';
  return `${filtroRutas.value.length} sel.`;
});

const todosLosPuntos = computed(() => {
  const puntos = [];
  rutasTrayectorias.value.forEach(rt => {
    puntos.push(...rt.puntos);
  });
  return puntos.sort((a, b) => a.fecha_hora.localeCompare(b.fecha_hora));
});

function toggleMenu(nombre, event) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const anchoMenu = nombre === 'rutas' ? 256 : 208;

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

function seleccionarTodosCanales() {
  filtroCanales.value = [...props.canales];
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  cargarDatos();
}

function limpiarCanales() {
  filtroCanales.value = [];
  filtroRutas.value = [];
  cargarDatos();
}

function onCanalesModificados() {
  const rutasValidas = new Set(rutasDisponibles.value.map(r => r.ruta));
  filtroRutas.value = filtroRutas.value.filter(r => rutasValidas.has(r));
  cargarDatos();
}

function seleccionarTodasRutas() {
  filtroRutas.value = rutasDisponibles.value.map(r => r.ruta);
  cargarDatos();
}

function limpiarRutas() {
  filtroRutas.value = [];
  cargarDatos();
}

async function cargarDatos() {
  cargando.value = true;
  try {
    const res = await axios.post(route('supervisor.visitas.avance.data'), {
      fecha: filtroFecha.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
    });

    rutasTrayectorias.value = res.data.rutas_trayectorias || [];
    kpis.value = res.data.kpis || {
      total_fotos: 0,
      rutas_activas: 0,
      en_rango: 0,
      desviadas: 0,
    };

    renderizarMapa();
  } catch (err) {
    console.error('Error cargando avance de visitas:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarMapa() {
  if (!map) return;
  markersLayer.clearLayers();
  linesLayer.clearLayers();
  markersMap.clear();

  const boundsList = [];

  rutasTrayectorias.value.forEach(rt => {
    const colorRuta = getRutaColor(rt.ruta);
    const latlngsRuta = [];

    rt.puntos.forEach(p => {
      latlngsRuta.push([p.visita_lat, p.visita_lng]);
      boundsList.push(L.latLng(p.visita_lat, p.visita_lng));

      // Pin Circular con Número de Secuencia Cronológica
      const markerHtml = `
        <div style="
          width: 24px;
          height: 24px;
          background-color: ${p.status === 'PREVENTA' ? '#248A3D' : '#FF9500'};
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
          ${p.orden}
        </div>
      `;

      const customIcon = L.divIcon({
        html: markerHtml,
        className: 'custom-cronologico-pin',
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12],
      });

      const marker = L.marker([p.visita_lat, p.visita_lng], { icon: customIcon });

      // Contenido del Popup con datos básicos y botón "Ver detalles"
      const popupContent = document.createElement('div');
      popupContent.style.fontFamily = '-apple-system, BlinkMacSystemFont, sans-serif';
      popupContent.style.fontSize = '11px';
      popupContent.style.lineHeight = '1.4';
      popupContent.style.color = '#1D1D1F';
      popupContent.style.minWidth = '220px';

      popupContent.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 3px;">
          <span style="font-weight: 700; font-size: 12px; color: #1D1D1F; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            #${p.orden} - ${p.cliente}
          </span>
          <span style="font-family: monospace; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; background-color: ${p.status === 'PREVENTA' ? '#EBF9EF' : '#FFF5E5'}; color: ${p.status === 'PREVENTA' ? '#248A3D' : '#B25E00'};">
            ${p.status}
          </span>
        </div>
        <div style="color: #6E6E73; margin-bottom: 4px; font-size: 10px;">
          ${p.hora} • Ruta: <strong>${p.ruta}</strong> (${p.vendedor})
        </div>
        <div style="font-family: monospace; font-size: 10px; border-top: 1px solid #E5E5EA; padding-top: 4px; margin-bottom: 6px;">
          ${p.distancia_oficial_metros !== null ? `
            <span>Desviación: <strong style="color: ${p.en_rango ? '#248A3D' : '#C9342C'}">${p.distancia_oficial_metros}m (${p.en_rango ? 'En rango' : 'Desviado'})</strong></span>
          ` : '<span style="color: #86868B;">Sin GPS oficial registrado</span>'}
        </div>
        <div style="width: 100%; height: 80px; border-radius: 4px; overflow: hidden; margin-bottom: 6px; background-color: #F2F2F7;">
          <img src="${p.photo_url}" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
        <button
          id="btn-ver-detalle-avance-${p.id}"
          type="button"
          style="width: 100%; height: 26px; background-color: #0071E3; color: white; border: none; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;"
        >
          <span>Ver detalles e histórico</span>
          <span>&rarr;</span>
        </button>
      `;

      const btn = popupContent.querySelector(`#btn-ver-detalle-avance-${p.id}`);
      if (btn) {
        btn.addEventListener('click', () => {
          abrirExpedienteCliente(p);
        });
      }

      marker.bindPopup(popupContent, { maxWidth: 240 });
      marker.addTo(markersLayer);
      markersMap.set(p.id, marker);

      // Si hay desviación, trazar línea tenue entre punto de foto y punto oficial
      if (p.oficial_lat && p.oficial_lng) {
        L.polyline([[p.visita_lat, p.visita_lng], [p.oficial_lat, p.oficial_lng]], {
          color: p.en_rango ? '#248A3D' : '#C9342C',
          weight: 1.5,
          dashArray: '3, 4',
          opacity: 0.6,
        }).addTo(linesLayer);
      }
    });

    // Traza de la polilínea segmentada continua para la ruta
    if (latlngsRuta.length > 1) {
      L.polyline(latlngsRuta, {
        color: colorRuta,
        weight: 3,
        dashArray: '6, 6',
        opacity: 0.85,
      }).addTo(linesLayer);
    }
  });

  if (boundsList.length > 0) {
    try {
      const bounds = L.latLngBounds(boundsList);
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
      }
    } catch (e) {
      // Bounds inválidos
    }
  }
}

function enfocarEnMapa(p) {
  puntoActivoId.value = p.id;
  if (!map) return;

  vistaMovil.value = 'mapa';
  map.flyTo([p.visita_lat, p.visita_lng], 18, { duration: 0.6 });
  const marker = markersMap.get(p.id);
  if (marker) {
    marker.openPopup();
  }
}

async function abrirExpedienteCliente(p) {
  clienteSeleccionado.value = p;
  fotosHistoricas.value = [];
  vistaMovil.value = 'lista';

  // Centrar en el mapa
  if (map) {
    map.flyTo([p.visita_lat, p.visita_lng], 18, { duration: 0.6 });
    const marker = markersMap.get(p.id);
    if (marker) {
      marker.openPopup();
    }
  }

  // Carga reactiva del historial completo de fotos
  cargandoFotos.value = true;
  try {
    const res = await axios.post(route('supervisor.visitas.fotos', { clienteId: p.cliente_id }));
    fotosHistoricas.value = res.data.visitas || [];
  } catch (err) {
    console.error('Error cargando historial:', err);
  } finally {
    cargandoFotos.value = false;
  }
}

function cerrarExpediente() {
  clienteSeleccionado.value = null;
  fotosHistoricas.value = [];
}

function abrirCarruselEn(index) {
  indiceFotoActiva.value = index;
  modalCarruselAbierto.value = true;
}

function cerrarCarrusel() {
  modalCarruselAbierto.value = false;
}

function fotoSiguiente() {
  if (indiceFotoActiva.value < fotosHistoricas.value.length - 1) {
    indiceFotoActiva.value++;
  } else {
    indiceFotoActiva.value = 0;
  }
}

function fotoAnterior() {
  if (indiceFotoActiva.value > 0) {
    indiceFotoActiva.value--;
  } else {
    indiceFotoActiva.value = fotosHistoricas.value.length - 1;
  }
}

function initMap() {
  map = L.map('map-avance', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([-16.5000, -68.1500], 13);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  linesLayer = L.featureGroup().addTo(map);
  markersLayer = L.featureGroup().addTo(map);

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