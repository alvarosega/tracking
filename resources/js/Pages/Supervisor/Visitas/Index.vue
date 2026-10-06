<template>
  <VisitasLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7]">
      <!-- Barra Superior de Control -->
      <header class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop invisible para dropdowns -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Filtros con Scroll Horizontal -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          <!-- Botón Días -->
          <button
            type="button"
            @click.stop="toggleMenu('dias', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Día:</span>
            <span class="font-semibold">{{ labelDias }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

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

          <!-- Toggle Inactivos con Foto -->
          <button
            type="button"
            @click="toggleInactivos"
            class="h-7 px-2.5 border rounded-[6px] text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
            :class="filtroInactivos ? 'bg-[#1D1D1F] text-white border-[#1D1D1F]' : 'bg-[#FBFBFD] text-[#6E6E73] border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            title="Mostrar solo clientes inactivos que tengan fotos históricas"
          >
            <span
              class="w-1.5 h-1.5 rounded-full"
              :class="filtroInactivos ? 'bg-[#34C759]' : 'bg-[#86868B]'"
            ></span>
            <span>Inactivos con foto</span>
          </button>
        </div>

        <!-- Selector Móvil Segmentado y KPIs -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Consultando...
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
              {{ clienteSeleccionado ? 'Ficha' : `Lista (${clientesFiltrados.length})` }}
            </button>
          </div>

          <div class="hidden lg:flex px-2 py-0.5 bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
            <span class="font-bold tabular-nums">Con Foto: {{ kpis.con_foto }} ({{ kpis.cobertura_pct }}%)</span>
          </div>

          <div class="hidden lg:flex px-2 py-0.5 bg-[#FDF0EF] text-[#C9342C] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#C9342C]"></span>
            <span class="font-bold tabular-nums">Sin Foto: {{ kpis.sin_foto }}</span>
          </div>

          <div class="hidden sm:flex px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="text-[#86868B]">Total:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.total }}</span>
          </div>
        </div>
      </header>

      <!-- Menús Desplegables con Teleport -->
      <Teleport to="body">
        <!-- Días -->
        <div
          v-if="menuAbierto === 'dias'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-48 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodosDias" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarDias" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="space-y-1">
            <label
              v-for="d in dias_disponibles"
              :key="d"
              class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
            >
              <input
                type="checkbox"
                :value="d"
                v-model="filtroDias"
                @change="cargarDatos"
                class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
              />
              <span class="font-medium text-[#1D1D1F]">{{ d }}</span>
            </label>
          </div>
        </div>

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
                <span class="w-2.5 h-2.5 rounded-full shrink-0 border border-black/20" :style="{ backgroundColor: getCanalColor(r.canal) }"></span>
                <span class="font-medium">{{ r.ruta }}</span>
                <span class="text-[#86868B] text-[10px]">({{ r.canal }})</span>
              </div>
            </label>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Lista o Expediente Detalle -->
        <aside
          class="w-full md:w-[440px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- MODO 1: LISTADO GENERAL -->
          <template v-if="!clienteSeleccionado">
            <!-- Buscador y Filtros Rápidos -->
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] space-y-1.5 shrink-0">
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Buscar cliente, código, ruta o teléfono..."
                class="w-full h-7 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#86868B]"
              />
              
              <div class="flex items-center justify-between gap-1">
                <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
                  <button
                    type="button"
                    @click="filtroEstadoFoto = 'todos'"
                    class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer"
                    :class="filtroEstadoFoto === 'todos' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73]'"
                  >
                    Todos ({{ clientes.length }})
                  </button>
                  <button
                    type="button"
                    @click="filtroEstadoFoto = 'sin_foto'"
                    class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer flex items-center gap-1"
                    :class="filtroEstadoFoto === 'sin_foto' ? 'bg-white text-[#C9342C] shadow-2xs font-bold' : 'text-[#6E6E73]'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-[#C9342C]"></span>
                    Sin Foto ({{ kpis.sin_foto }})
                  </button>
                  <button
                    type="button"
                    @click="filtroEstadoFoto = 'con_foto'"
                    class="px-2 py-0.5 text-[10px] font-mono font-medium rounded-[4px] transition-all cursor-pointer flex items-center gap-1"
                    :class="filtroEstadoFoto === 'con_foto' ? 'bg-white text-[#248A3D] shadow-2xs font-bold' : 'text-[#6E6E73]'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
                    Con Foto ({{ kpis.con_foto }})
                  </button>
                </div>

                <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
                  <button
                    type="button"
                    @click="pestañaGps = 'sin_gps'"
                    class="px-1.5 py-0.5 text-[10px] font-mono rounded-[4px]"
                    :class="pestañaGps === 'sin_gps' ? 'bg-white text-[#B25E00] shadow-2xs font-bold' : 'text-[#86868B]'"
                    title="Clientes sin coordenadas"
                  >
                    Sin GPS: {{ kpis.sin_gps }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Filas de Clientes -->
            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Consultando cobertura fotográfica...
              </div>

              <div v-else-if="filtroDias.length === 0 && filtroRutas.length === 0 && filtroCanales.length === 0" class="p-8 text-center text-xs text-[#86868B] space-y-1">
                <p class="font-medium text-[#1D1D1F]">Selecciona al menos un filtro</p>
                <p>Elige un día, canal o ruta arriba para listar los clientes.</p>
              </div>

              <div v-else-if="clientesFiltrados.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No se encontraron clientes con los filtros aplicados.
              </div>

              <div
                v-for="c in clientesFiltrados"
                :key="c.cliente_id"
                @click="abrirExpedienteCliente(c)"
                class="p-3 hover:bg-[#FBFBFD] transition-colors cursor-pointer"
                :class="c.tiene_foto ? 'bg-white' : 'bg-[#FDF0EF]/30'"
              >
                <div class="flex items-start justify-between gap-1.5">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <span
                        class="w-2.5 h-2.5 rounded-full shrink-0"
                        :class="c.tiene_foto ? 'bg-[#248A3D]' : 'bg-[#C9342C]'"
                      ></span>
                      <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1">{{ c.cliente }}</span>
                      <span
                        v-if="c.estado === 'Inactivo'"
                        class="text-[9px] font-mono font-bold px-1 py-0.2 rounded bg-[#E5E5EA] text-[#6E6E73] shrink-0"
                      >
                        INACTIVO
                      </span>
                    </div>
                    <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ c.direccion || 'Sin dirección' }}</p>
                  </div>

                  <span
                    class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded shrink-0"
                    :class="c.tiene_foto ? 'bg-[#EBF9EF] text-[#248A3D] border border-[#248A3D]/20' : 'bg-[#FDF0EF] text-[#C9342C] border border-[#C9342C]/20'"
                  >
                    {{ c.tiene_foto ? `${c.total_fotos} foto(s)` : 'SIN FOTO' }}
                  </span>
                </div>

                <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-[#E5E5EA]/70 text-[10px] font-mono text-[#86868B]">
                  <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getCanalColor(c.canal) }"></span>
                    <span class="text-[#1D1D1F] font-medium">{{ c.ruta }}</span>
                    <span>•</span>
                    <span>{{ c.dias_visita }}</span>
                    <span v-if="!c.tiene_gps" class="text-[#B25E00] font-bold">⚠ Sin GPS</span>
                  </div>
                  <span class="text-[#0071E3] font-sans font-medium text-[11px]">Ver ficha &rarr;</span>
                </div>
              </div>
            </div>
          </template>

          <!-- MODO 2: EXPEDIENTE / SUBMENÚ DE AUDITORÍA LATERAL -->
          <template v-else>
            <!-- Cabecera del Expediente -->
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="cerrarExpediente"
                class="text-xs font-semibold text-[#0071E3] hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>&larr;</span> Volver al listado
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
                  <div class="flex items-center gap-1.5">
                    <span
                      class="w-2.5 h-2.5 rounded-full shrink-0"
                      :class="clienteSeleccionado.tiene_foto ? 'bg-[#248A3D]' : 'bg-[#C9342C]'"
                    ></span>
                    <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight line-clamp-1">{{ clienteSeleccionado.cliente }}</h3>
                  </div>
                  <p class="text-[11px] text-[#6E6E73] mt-0.5">{{ clienteSeleccionado.direccion || 'Sin dirección registrada' }}</p>
                  <p v-if="clienteSeleccionado.referencia" class="text-[10px] text-[#86868B] italic mt-0.5">
                    Ref: {{ clienteSeleccionado.referencia }}
                  </p>
                </div>

                <div class="flex flex-col items-end gap-1 shrink-0">
                  <span
                    class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded text-white"
                    :style="{ backgroundColor: getCanalColor(clienteSeleccionado.canal) }"
                  >
                    {{ clienteSeleccionado.canal }}
                  </span>
                  <span
                    class="text-[10px] font-mono font-bold px-1.5 py-0.2 rounded"
                    :class="clienteSeleccionado.tiene_foto ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                  >
                    {{ clienteSeleccionado.tiene_foto ? 'CON FOTO' : 'SIN FOTO' }}
                  </span>
                </div>
              </div>

              <!-- Metadatos de Ruteo en Grilla -->
              <div class="grid grid-cols-2 gap-2 mt-3 pt-2.5 border-t border-[#E5E5EA] text-[11px] font-mono">
                <div>
                  <span class="text-[9px] text-[#86868B] uppercase block">Ruta / Vendedor</span>
                  <span class="font-semibold text-[#1D1D1F]">{{ clienteSeleccionado.ruta }}</span>
                  <span class="text-[#6E6E73] block text-[10px] truncate">{{ clienteSeleccionado.vendedor || 'Sin vendedor' }}</span>
                </div>
                <div>
                  <span class="text-[9px] text-[#86868B] uppercase block">Días Programados</span>
                  <span class="font-semibold text-[#1D1D1F]">{{ clienteSeleccionado.dias_visita }}</span>
                </div>
                <div>
                  <span class="text-[9px] text-[#86868B] uppercase block">Teléfono / Contacto</span>
                  <a
                    v-if="clienteSeleccionado.telefono"
                    :href="`tel:${clienteSeleccionado.telefono}`"
                    class="font-semibold text-[#0071E3] hover:underline"
                  >
                    {{ clienteSeleccionado.telefono }}
                  </a>
                  <span v-else class="text-[#86868B]">No registrado</span>
                </div>
                <div>
                  <span class="text-[9px] text-[#86868B] uppercase block">ID / Negocio</span>
                  <span class="text-[#1D1D1F]">ID: {{ clienteSeleccionado.cliente_id }}</span>
                  <span class="text-[#6E6E73] block text-[10px] truncate">{{ clienteSeleccionado.tipo_negocio || 'General' }}</span>
                </div>
              </div>
            </div>

            <!-- Sección de Fotografías del Cliente -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-[#F5F5F7]">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Histórico de Visitas</span>
                <span class="text-[10px] font-mono text-[#6E6E73]">
                  {{ fotosHistoricas.length }} registro(s)
                </span>
              </div>

              <div v-if="cargandoFotos" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando galería fotográfica...
              </div>

              <!-- Alerta si NO tiene fotos (Cliente Rojo) -->
              <div
                v-else-if="!clienteSeleccionado.tiene_foto"
                class="p-4 bg-white border border-[#C9342C]/20 rounded-[8px] text-center space-y-1"
              >
                <div class="w-8 h-8 rounded-full bg-[#FDF0EF] text-[#C9342C] flex items-center justify-center mx-auto text-sm font-bold">
                  !
                </div>
                <p class="text-xs font-semibold text-[#C9342C]">Sin visitas registradas en la historia</p>
                <p class="text-[11px] text-[#6E6E73]">
                  Este cliente no cuenta con respaldo fotográfico en la base de datos de visitas.
                </p>
              </div>

              <!-- Listado de Fotos con Miniaturas (Cliente Verde) -->
              <div
                v-else
                v-for="(vis, idx) in fotosHistoricas"
                :key="vis.id"
                class="bg-white border border-[#E5E5EA] rounded-[8px] p-2.5 flex items-start gap-3 hover:border-[#86868B] transition-colors"
              >
                <!-- Miniatura -->
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

                <!-- Detalle de la Visita -->
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

        <!-- Panel Derecho: Visor Cartográfico Leaflet -->
        <main
          class="flex-1 relative min-h-0 bg-[#E5E5EA]"
          :class="vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full'"
        >
          <div id="map-visitas" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda Flotante -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-[500] text-[11px] max-w-[240px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Cobertura Fotográfica</p>
            <div class="space-y-1 pb-1.5 border-b border-[#E5E5EA]">
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#248A3D] border border-white shrink-0"></span>
                <span class="text-[#1D1D1F] font-medium">Con Foto Histórica</span>
                <span class="text-[#86868B] text-[10px] font-mono tabular-nums">({{ kpis.con_foto }})</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#C9342C] border border-white shrink-0"></span>
                <span class="text-[#1D1D1F] font-medium">Sin Foto Histórica</span>
                <span class="text-[#86868B] text-[10px] font-mono tabular-nums">({{ kpis.sin_foto }})</span>
              </div>
            </div>

            <div class="mt-1.5 text-[10px] text-[#86868B]">
              Toca un marcador para ver datos y abrir la ficha completa.
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
  dias_disponibles: {
    type: Array,
    default: () => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
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

// Filtros Reactivos
const filtroDias = ref([]);
const filtroCanales = ref([]);
const filtroRutas = ref([]);
const filtroInactivos = ref(false);

const filtroEstadoFoto = ref('todos');
const pestañaGps = ref('todos');
const busquedaTexto = ref('');
const busquedaRuta = ref('');

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const vistaMovil = ref('mapa');

// Datos
const cargando = ref(false);
const clientes = ref([]);
const kpis = ref({
  total: 0,
  con_foto: 0,
  sin_foto: 0,
  cobertura_pct: 0,
  con_gps: 0,
  sin_gps: 0,
});

// Expediente
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

const labelDias = computed(() => {
  if (filtroDias.value.length === props.dias_disponibles.length) return 'Todos';
  if (filtroDias.value.length === 0) return 'Sin filtro';
  if (filtroDias.value.length <= 2) return filtroDias.value.join(', ');
  return `${filtroDias.value.length} sel.`;
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === props.canales.length) return 'Todos';
  if (filtroCanales.value.length === 0) return 'Sin filtro';
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 0) return 'Sin filtro';
  return `${filtroRutas.value.length} sel.`;
});

const clientesFiltrados = computed(() => {
  let list = clientes.value;

  if (filtroEstadoFoto.value === 'sin_foto') {
    list = list.filter(c => !c.tiene_foto);
  } else if (filtroEstadoFoto.value === 'con_foto') {
    list = list.filter(c => c.tiene_foto);
  }

  if (pestañaGps.value === 'sin_gps') {
    list = list.filter(c => !c.tiene_gps);
  }

  if (!busquedaTexto.value.trim()) return list;

  const q = busquedaTexto.value.toLowerCase();
  return list.filter(c =>
    (c.cliente && c.cliente.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q)) ||
    (c.direccion && c.direccion.toLowerCase().includes(q)) ||
    (c.telefono && String(c.telefono).includes(q))
  );
});

function toggleMenu(nombre, event) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const anchoMenu = nombre === 'rutas' ? 256 : (nombre === 'canales' ? 208 : 192);

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

function toggleInactivos() {
  filtroInactivos.value = !filtroInactivos.value;
  cargarDatos();
}

function seleccionarTodosDias() {
  filtroDias.value = [...props.dias_disponibles];
  cargarDatos();
}

function limpiarDias() {
  filtroDias.value = [];
  cargarDatos();
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
    const res = await axios.post(route('supervisor.visitas.data'), {
      dias: filtroDias.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
      inactivos_con_foto: filtroInactivos.value,
    });

    clientes.value = res.data.clientes || [];
    kpis.value = res.data.kpis || {
      total: 0,
      con_foto: 0,
      sin_foto: 0,
      cobertura_pct: 0,
      con_gps: 0,
      sin_gps: 0,
    };

    renderizarMapa();
  } catch (err) {
    console.error('Error cargando auditoría de visitas:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarMapa() {
  if (!map) return;
  markersLayer.clearLayers();
  markersMap.clear();

  const boundsList = [];

  clientes.value.forEach(c => {
    if (!c.tiene_gps) return;

    const fillColor = c.tiene_foto ? '#248A3D' : '#C9342C';
    const strokeColor = getCanalColor(c.canal);

    const marker = L.circleMarker([c.latitud, c.longitud], {
      radius: c.tiene_foto ? 5.5 : 6.5,
      fillColor: fillColor,
      color: strokeColor,
      weight: c.tiene_foto ? 1.5 : 2.5,
      opacity: 1,
      fillOpacity: 0.95,
    });

    // Contenido del Popup con datos básicos y botón "Ver detalles"
    const popupContent = document.createElement('div');
    popupContent.style.fontFamily = '-apple-system, BlinkMacSystemFont, sans-serif';
    popupContent.style.fontSize = '11px';
    popupContent.style.lineHeight = '1.4';
    popupContent.style.color = '#1D1D1F';
    popupContent.style.minWidth = '200px';

    popupContent.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
        <span style="font-weight: 700; font-size: 12px; color: #1D1D1F; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
          ${c.cliente}
        </span>
        <span style="font-family: monospace; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; background-color: ${fillColor}; color: white;">
          ${c.tiene_foto ? 'CON FOTO' : 'SIN FOTO'}
        </span>
      </div>
      <div style="color: #6E6E73; margin-bottom: 4px; font-size: 10px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
        ${c.direccion || 'Sin dirección registrada'}
      </div>
      <div style="font-family: monospace; font-size: 10px; color: #1D1D1F; border-top: 1px solid #E5E5EA; padding-top: 4px; margin-bottom: 6px;">
        <span>Ruta: <strong>${c.ruta}</strong> (${c.canal})</span><br>
        <span>Día(s): <strong>${c.dias_visita}</strong></span>
      </div>
      <button
        id="btn-ver-detalle-${c.cliente_id}"
        type="button"
        style="width: 100%; height: 26px; background-color: #0071E3; color: white; border: none; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; transition: background-color 0.15s;"
      >
        <span>Ver detalles</span>
        <span>&rarr;</span>
      </button>
    `;

    // Conectar el botón al expediente del cliente
    const btn = popupContent.querySelector(`#btn-ver-detalle-${c.cliente_id}`);
    if (btn) {
      btn.addEventListener('click', () => {
        abrirExpedienteCliente(c);
      });
    }

    marker.bindPopup(popupContent, {
      maxWidth: 240,
      className: 'apple-pro-popup',
    });

    marker.addTo(markersLayer);
    markersMap.set(c.cliente_id, marker);
    boundsList.push(L.latLng(c.latitud, c.longitud));
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

async function abrirExpedienteCliente(c) {
  clienteSeleccionado.value = c;
  fotosHistoricas.value = [];

  // Conmutar automáticamente la vista en móvil a la lista/ficha
  vistaMovil.value = 'lista';

  // Centrar suavemente en el mapa si tiene GPS
  if (c.tiene_gps && map) {
    map.flyTo([c.latitud, c.longitud], 18, { duration: 0.6 });
    const marker = markersMap.get(c.cliente_id);
    if (marker) {
      marker.openPopup();
    }
  }

  // Carga reactiva inmediata de fotos en el expediente lateral
  if (c.tiene_foto) {
    cargandoFotos.value = true;
    try {
      const res = await axios.post(route('supervisor.visitas.fotos', { clienteId: c.cliente_id }));
      fotosHistoricas.value = res.data.visitas || [];
    } catch (err) {
      console.error('Error cargando historial de fotos:', err);
    } finally {
      cargandoFotos.value = false;
    }
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

function manejarTeclado(e) {
  if (!modalCarruselAbierto.value) return;
  if (e.key === 'ArrowRight') fotoSiguiente();
  if (e.key === 'ArrowLeft') fotoAnterior();
  if (e.key === 'Escape') cerrarCarrusel();
}

function initMap() {
  const defaultLoc = mapsConfig.defaultLocation;
  map = L.map('map-visitas', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer(mapsConfig.tileLayers.openStreetMap.url, {
    maxZoom: 19,
    attribution: mapsConfig.tileLayers.openStreetMap.attribution,
    subdomains: mapsConfig.tileLayers.openStreetMap.subdomains || 'abc',
  }).addTo(map);

  markersLayer = L.featureGroup().addTo(map);

  map.on('click', () => {
    menuAbierto.value = null;
  });
}

onMounted(() => {
  initMap();
  cargarDatos();
  window.addEventListener('keydown', manejarTeclado);
});

onUnmounted(() => {
  window.removeEventListener('keydown', manejarTeclado);
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