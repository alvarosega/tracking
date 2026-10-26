<template>
  <SupervisorLayout title="Saneamiento | Fronteras e Intrusos">
    <div class="space-y-4">
      <!-- Pestañas de Submódulos Saneamiento -->
      <SaneamientoSubTabs active-tab="intrusos" />

      <!-- Barra de Filtros Dual: Canal, Capas de Polígonos y Puntos, Modo Color y Búsqueda -->
      <div class="apple-glass-card p-2.5 sm:p-3 rounded-[16px] mb-3 relative select-none font-sans">
        <div class="flex flex-wrap items-center justify-between gap-2.5 text-[11px]">
          
          <!-- Controles Izquierda -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Canal -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
              <select
                v-model="canalSeleccionado"
                @change="alCambiarCanal"
                class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in canalesDisponibles" :key="c" :value="c">
                  {{ c }}
                </option>
              </select>
            </div>

            <!-- Desplegable Multiruta (Capas Polígonos y Puntos) -->
            <div class="relative inline-block text-left" ref="dropdownRutasRef">
              <button
                type="button"
                @click="abiertoDropdownRutas = !abiertoDropdownRutas"
                class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer"
              >
                <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Rutas:</span>
                <div class="flex items-center gap-1 font-mono text-[10px]">
                  <span class="px-1.5 py-0.5 rounded-[6px] bg-blue-500/10 text-[#0071E3] font-bold">
                    Pol: {{ rutasPoligonos.length }}
                  </span>
                  <span class="px-1.5 py-0.5 rounded-[6px] bg-emerald-500/10 text-emerald-700 font-bold">
                    Pts: {{ rutasPuntos.length }}
                  </span>
                </div>
                <svg
                  class="w-3 h-3 text-[#86868B] transition-transform duration-200 pointer-events-none"
                  :class="abiertoDropdownRutas ? 'rotate-180 text-[#0071E3]' : ''"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Menú Flotante de Rutas (Glass Dropdown) -->
              <div
                v-if="abiertoDropdownRutas"
                class="absolute left-0 top-full mt-2 w-80 glass-dropdown rounded-[14px] shadow-2xl z-[10002] p-2.5 text-[11px] backdrop-blur-3xl bg-white/95 border border-black/[0.08]"
              >
                <div class="space-y-1.5 pb-2 mb-2 border-b border-black/[0.06] text-[10px]">
                  <div class="flex items-center justify-between font-bold">
                    <span class="text-[#1D1D1F] uppercase tracking-wider text-[9px]">Capas de Rutas</span>
                    <span class="text-[#86868B] font-mono text-[9px]">{{ rutasFiltradasPorCanal.length }} rutas</span>
                  </div>

                  <div class="grid grid-cols-2 gap-2 text-[10px]">
                    <div class="flex items-center justify-between p-1.5 rounded-[8px] bg-blue-500/5 border border-blue-500/15">
                      <span class="font-bold text-[#0071E3] text-[9px]">Polígonos:</span>
                      <div class="flex items-center gap-1.5">
                        <button type="button" @click="marcarTodasPoligonos(true)" class="text-[#0071E3] font-bold hover:underline cursor-pointer">Todas</button>
                        <span class="text-black/20">·</span>
                        <button type="button" @click="marcarTodasPoligonos(false)" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Ninguna</button>
                      </div>
                    </div>
                    <div class="flex items-center justify-between p-1.5 rounded-[8px] bg-emerald-500/5 border border-emerald-500/15">
                      <span class="font-bold text-emerald-700 text-[9px]">Puntos:</span>
                      <div class="flex items-center gap-1.5">
                        <button type="button" @click="marcarTodasPuntos(true)" class="text-emerald-700 font-bold hover:underline cursor-pointer">Todas</button>
                        <span class="text-black/20">·</span>
                        <button type="button" @click="marcarTodasPuntos(false)" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Ninguna</button>
                      </div>
                    </div>
                  </div>

                  <div class="relative pt-0.5">
                    <input
                      v-model="filtroTextoRutaDropdown"
                      type="text"
                      placeholder="Buscar ruta en lista..."
                      class="w-full text-[10px] bg-neutral-100 rounded-[8px] px-2.5 py-1.5 focus:outline-none font-medium border border-black/[0.06] placeholder-[#86868B]"
                    />
                  </div>
                </div>

                <!-- Lista de Rutas con Toggles Duales -->
                <div class="max-h-56 overflow-y-auto custom-scroll space-y-0.5">
                  <div
                    v-for="r in rutasDropdownList"
                    :key="r.ruta"
                    class="flex items-center justify-between py-1 px-1.5 rounded-[6px] hover:bg-black/[0.03]"
                  >
                    <span class="font-mono font-semibold text-ink text-[11px] truncate">{{ r.ruta }}</span>
                    <div class="flex items-center gap-2">
                      <label class="flex items-center gap-1 cursor-pointer" title="Ver Polígono">
                        <input
                          type="checkbox"
                          :checked="rutasPoligonos.includes(r.ruta)"
                          @change="togglePoligonoRuta(r.ruta)"
                          class="rounded text-[#0071E3] accent-[#0071E3]"
                        />
                        <span class="text-[9px] font-mono text-[#0071E3] font-bold">Pol</span>
                      </label>
                      <label class="flex items-center gap-1 cursor-pointer" title="Ver Puntos">
                        <input
                          type="checkbox"
                          :checked="rutasPuntos.includes(r.ruta)"
                          @change="togglePuntosRuta(r.ruta)"
                          class="rounded text-emerald-600 accent-emerald-600"
                        />
                        <span class="text-[9px] font-mono text-emerald-700 font-bold">Pts</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Conmutador de Modo de Color para Puntos -->
            <div class="apple-segmented rounded-[10px] p-0.5 flex items-center text-[10px]">
              <button
                type="button"
                @click="cambiarModoColor('dia')"
                class="px-2.5 py-1 text-[10px] rounded-[7px] transition-all cursor-pointer"
                :class="modoColorPuntos === 'dia'
                  ? 'apple-segmented-active text-[#1D1D1F] font-bold shadow-2xs'
                  : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              >
                Color por Día
              </button>
              <button
                type="button"
                @click="cambiarModoColor('estado')"
                class="px-2.5 py-1 text-[10px] rounded-[7px] transition-all cursor-pointer"
                :class="modoColorPuntos === 'estado'
                  ? 'apple-segmented-active text-[#1D1D1F] font-bold shadow-2xs'
                  : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              >
                Color por Hallazgo
              </button>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="consultarTodo"
              :disabled="cargando"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer disabled:opacity-75"
            >
              <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <span>{{ cargando ? 'Consultando...' : 'Consultar' }}</span>
            </button>
          </div>

          <!-- Buscador Rápido de Clientes en Mapa -->
          <div class="relative w-full sm:w-64">
            <input
              v-model="queryBuscador"
              @input="onSearchClienteInput"
              type="text"
              placeholder="Buscar cliente por ID o nombre..."
              class="w-full text-[11px] bg-white rounded-[10px] px-3 py-1.5 focus:outline-none font-medium border border-black/[0.08] shadow-2xs placeholder-[#86868B]"
            />
            <div
              v-if="resultadosBuscador.length > 0"
              class="absolute left-0 right-0 top-full mt-1 bg-white border border-neutral-200 rounded-xl shadow-xl z-50 max-h-56 overflow-y-auto"
            >
              <div
                v-for="cli in resultadosBuscador"
                :key="cli.cliente_id"
                @click="enfocarClienteEnMapa(cli)"
                class="px-3 py-2 text-[10px] hover:bg-neutral-50 cursor-pointer border-b border-neutral-100 flex items-center justify-between"
              >
                <div>
                  <div class="font-bold text-ink">{{ cli.cliente }}</div>
                  <div class="text-[#86868B] font-mono">ID: {{ cli.cliente_id }} • Ruta: {{ cli.ruta }}</div>
                </div>
                <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-bold">
                  {{ cli.dia }}
                </span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Contenedor Principal: Mapa y Panel Lateral -->
      <div class="grid grid-cols-1 transition-all duration-300 gap-4" :class="panelReplegado ? 'lg:grid-cols-1' : 'lg:grid-cols-3'">
        
        <!-- Mapa Interactivo (2 cols o 1 col si panel está replegado) -->
        <div
          :class="panelReplegado ? 'lg:col-span-1' : 'lg:col-span-2'"
          class="relative glass-panel rounded-card overflow-hidden shadow-subtle min-h-[580px] flex flex-col transition-all duration-300 border border-black/[0.06] bg-white/70 backdrop-blur-md"
        >
          <!-- Barra Superior del Mapa -->
          <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
            <div class="flex items-center gap-2">
              <span
                class="w-2 h-2 rounded-full transition-colors duration-300"
                :class="cargando ? 'bg-amber-500 animate-ping' : 'bg-emerald-500 animate-pulse'"
              ></span>
              <span class="font-bold text-[12px] text-ink">Mosaico Territorial & Inspección de Clientes</span>
              <span v-if="cargando" class="text-[9px] font-mono text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                Cargando...
              </span>
            </div>
            <div class="flex items-center gap-2.5 text-[10px] font-mono">
              <span class="text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                Polígonos: <strong>{{ rutasPoligonos.length }}</strong>
              </span>
              <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                Puntos: <strong>{{ clientesFronteras.length }}</strong>
              </span>
              <button
                v-if="panelReplegado"
                @click="panelReplegado = false"
                class="flex items-center gap-1 px-2.5 py-1 bg-black text-white hover:bg-neutral-800 rounded text-[10px] font-bold shadow-2xs transition-all cursor-pointer ml-1"
                title="Mostrar Panel Lateral"
              >
                <span>Mostrar Panel</span>
              </button>
            </div>
          </div>

          <!-- Barra de Herramientas para Edición de Fronteras -->
          <div class="px-4 py-2 border-b border-neutral-100 bg-neutral-50/90 flex flex-wrap items-center justify-between gap-2 text-[11px] shrink-0">
            <div class="flex items-center gap-2">
              <span class="text-[10px] uppercase font-bold text-[#6E6E73]">
                Editar Frontera:
              </span>
              <select
                v-model="rutaSeleccionadaParaEditar"
                @change="alSeleccionarRutaParaEditar"
                class="bg-white border border-neutral-200 rounded px-2.5 py-1 font-bold text-ink text-[11px] focus:outline-none cursor-pointer shadow-2xs font-mono"
              >
                <option value="">-- Seleccionar ruta --</option>
                <option
                  v-for="r in rutasFiltradasPorCanal"
                  :key="r.ruta"
                  :value="r.ruta"
                >
                  {{ r.ruta }} ({{ r.canal }})
                </option>
              </select>
            </div>

            <!-- Controles de Edición de Vértices -->
            <div class="flex items-center gap-1.5">
              <template v-if="rutaEnEdicion && !editandoVertices">
                <button
                  v-if="tienePoligonoActual"
                  @click="activarEdicionVertices"
                  class="px-2.5 py-1 bg-white hover:bg-neutral-100 text-ink border border-neutral-200 rounded text-[10px] font-bold shadow-2xs cursor-pointer transition-all"
                >
                  Editar Vértices
                </button>
                <span v-else class="text-[10px] text-neutral-400 italic font-mono">
                  Sin polígono registrado
                </span>
                <button
                  @click="cancelarEdicionVertices(true)"
                  class="px-2 py-1 text-ink-secondary hover:text-ink text-[10px] rounded hover:bg-neutral-200/60 transition-colors font-bold cursor-pointer"
                >
                  Cancelar
                </button>
              </template>

              <template v-if="editandoVertices">
                <span class="text-[10px] font-mono text-amber-800 bg-amber-50 px-2.5 py-0.5 rounded border border-amber-200 font-bold">
                  Editando vértices de {{ rutaEnEdicion }} (arrastra los marcadores azules)
                </span>
                <button
                  @click="guardarFronteraActual"
                  :disabled="guardandoFrontera"
                  class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer transition-all disabled:opacity-50"
                >
                  Guardar
                </button>
                <button
                  @click="cancelarEdicionVertices(true)"
                  class="px-2.5 py-1 bg-neutral-200 hover:bg-neutral-300 text-ink rounded text-[10px] font-bold cursor-pointer transition-all"
                >
                  Cancelar
                </button>
              </template>
            </div>
          </div>

          <!-- Contenedor del Mapa Leaflet -->
          <div id="mapaIntrusos" class="flex-1 w-full min-h-[500px] relative z-0"></div>

          <!-- Leyenda Flotante Dinámica (Días vs Estado) -->
          <div class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-md border border-neutral-200/80 rounded-card shadow-float px-3 py-2 text-[10px] z-20 space-y-1.5 max-w-[280px]">
            <div class="flex items-center justify-between gap-2 border-b border-neutral-100 pb-1">
              <span class="font-bold text-ink uppercase tracking-wider text-[9px]">
                {{ modoColorPuntos === 'dia' ? 'Días de Visita (Puntos)' : 'Estado Territorial' }}
              </span>
              <span class="text-[9px] font-mono text-ink-secondary">
                {{ modoColorPuntos === 'dia' ? 'Por Jornada' : 'Auditoría' }}
              </span>
            </div>

            <!-- Leyenda por Día -->
            <div v-if="modoColorPuntos === 'dia'" class="grid grid-cols-2 gap-x-2 gap-y-1 font-mono">
              <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#2563EB]"></span><span>Lunes</span></div>
              <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#16A34A]"></span><span>Martes</span></div>
              <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#EA580C]"></span><span>Miércoles</span></div>
              <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#9333EA]"></span><span>Jueves</span></div>
              <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#CA8A04]"></span><span>Viernes</span></div>
              <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#E11D48]"></span><span>Sábado</span></div>
              <div class="col-span-2 pt-1 border-t border-neutral-100 flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-rose-600 ring-2 ring-rose-200"></span>
                  <span class="text-rose-700 font-bold">Intruso</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span>
                  <span class="text-amber-700 font-bold">Huérfano</span>
                </div>
              </div>
            </div>

            <!-- Leyenda por Estado -->
            <div v-else class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                <span class="text-ink-secondary">En frontera legítima</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                <span class="text-ink font-bold">Intruso (invade otra ruta)</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-600"></span>
                <span class="text-ink-secondary">Huérfano (sin polígono)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel Lateral (1 col) -->
        <div
          v-if="!panelReplegado"
          class="glass-panel rounded-card shadow-subtle flex flex-col overflow-hidden min-h-[580px] transition-all duration-300 border border-black/[0.06] bg-white/70 backdrop-blur-md"
        >
          <!-- Pestañas del Panel Lateral con Botón de Repliegue -->
          <div class="px-3 py-2 border-b border-neutral-100 flex items-center justify-between bg-white/80 shrink-0">
            <div class="inline-flex bg-neutral-100 p-0.5 rounded-[10px] text-[11px]">
              <button
                type="button"
                @click="tabActivo = 'balance'"
                :class="tabActivo === 'balance' ? 'bg-white font-bold text-ink shadow-2xs' : 'text-ink-secondary'"
                class="px-2.5 py-1 rounded-[8px] transition-all cursor-pointer"
              >
                Balance
              </button>
              <button
                type="button"
                @click="tabActivo = 'intrusos'"
                :class="tabActivo === 'intrusos' ? 'bg-white font-bold text-ink shadow-2xs' : 'text-ink-secondary'"
                class="px-2.5 py-1 rounded-[8px] transition-all cursor-pointer"
              >
                Intrusos ({{ totalIntrusos }})
              </button>
              <button
                type="button"
                @click="tabActivo = 'huerfanos'"
                :class="tabActivo === 'huerfanos' ? 'bg-white font-bold text-ink shadow-2xs' : 'text-ink-secondary'"
                class="px-2.5 py-1 rounded-[8px] transition-all cursor-pointer"
              >
                Huérfanos ({{ totalHuerfanos }})
              </button>
            </div>

            <!-- Botón Repliegue -->
            <button
              type="button"
              @click="panelReplegado = true"
              class="flex items-center gap-1 px-1.5 py-1 rounded text-ink-secondary hover:text-ink hover:bg-neutral-100 transition-colors text-[10px] font-mono cursor-pointer ml-1"
              title="Replegar panel hacia la derecha"
            >
              <span>Replegar</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <!-- Contenido del Panel Lateral -->
          <div class="flex-1 overflow-y-auto custom-scroll p-3 text-[11px] space-y-2">
            
            <!-- Tab 1: Balance de Rutas -->
            <div v-if="tabActivo === 'balance'" class="space-y-2">
              <div v-if="resumenRutas.length === 0" class="text-center py-16 text-[#86868B]">
                Selecciona rutas en el conmutador para ver su balance territorial.
              </div>
              <div
                v-for="r in resumenRutas"
                :key="r.ruta"
                class="p-2.5 rounded-card border border-neutral-200/70 bg-white/70 hover:bg-white shadow-2xs transition-all space-y-1.5"
              >
                <div class="flex items-center justify-between">
                  <span class="font-bold text-ink font-mono text-[12px]">{{ r.ruta }}</span>
                  <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-neutral-100 text-ink-secondary font-bold">
                    {{ r.total }} clientes
                  </span>
                </div>
                <div class="grid grid-cols-3 gap-1 text-[9px] font-mono pt-1 border-t border-neutral-100">
                  <div class="text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded text-center">
                    Válidos: <strong>{{ r.validos }}</strong>
                  </div>
                  <div class="text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded text-center">
                    Intrusos: <strong>{{ r.intrusos }}</strong>
                  </div>
                  <div class="text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded text-center">
                    Huérfanos: <strong>{{ r.huerfanos }}</strong>
                  </div>
                </div>

                <!-- Distribución por Día de Visita -->
                <div v-if="r.conteoDias" class="pt-1.5 border-t border-neutral-100 space-y-1">
                  <div class="text-[9px] uppercase tracking-wider text-ink-secondary font-mono font-bold">
                    <span>Clientes por Día:</span>
                  </div>
                  <div class="grid grid-cols-6 gap-1 text-[9px] font-mono">
                    <div
                      v-for="d in DIAS_SEMANA"
                      :key="d.val"
                      class="text-center py-0.5 rounded font-bold"
                      :style="{
                        backgroundColor: (r.conteoDias[d.val] || 0) > 0 ? d.color + '18' : '#F9FAFB',
                        color: (r.conteoDias[d.val] || 0) > 0 ? d.color : '#9CA3AF',
                        border: (r.conteoDias[d.val] || 0) > 0 ? `1px solid ${d.color}40` : '1px solid #F3F4F6'
                      }"
                    >
                      <div class="text-[8px] opacity-75 font-normal">{{ d.corto }}</div>
                      <div class="text-[10px] font-bold">{{ r.conteoDias[d.val] || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab 2: Lista de Clientes Intrusos -->
            <div v-else-if="tabActivo === 'intrusos'" class="space-y-2">
              <div v-if="intrusosFiltrados.length === 0" class="text-center py-16 text-[#86868B]">
                No se detectaron clientes intrusos en las rutas seleccionadas.
              </div>
              <div
                v-for="cli in intrusosFiltrados"
                :key="cli.cliente_id"
                class="p-2.5 rounded-card border border-rose-200 bg-rose-50/40 hover:bg-rose-50/80 transition-all space-y-1"
              >
                <div class="flex items-start justify-between gap-1">
                  <div class="font-bold text-ink leading-tight">{{ cli.cliente || 'Sin nombre' }}</div>
                  <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-rose-600 text-white shrink-0">
                    INTRUSO
                  </span>
                </div>
                <div class="text-[10px] text-ink-secondary font-mono flex items-center justify-between">
                  <span>ID: {{ cli.cliente_id }}</span>
                  <span>Ruta: <strong class="text-rose-700">{{ cli.ruta }}</strong></span>
                </div>
                <div class="text-[10px] text-ink-secondary pt-1 flex items-center justify-between border-t border-rose-100 gap-1">
                  <span>
                    Invade: <strong class="text-emerald-700 font-mono">{{ cli.ruta_duena_poligono || cli.ruta_invadida || 'Otra ruta' }}</strong>
                  </span>
                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      type="button"
                      @click="abrirModalFotos(cli)"
                      class="px-1.5 py-0.5 bg-white hover:bg-neutral-100 text-ink rounded text-[10px] font-bold border border-neutral-200 transition-all shadow-2xs flex items-center gap-1 cursor-pointer"
                    >
                      <svg class="w-3 h-3 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      <span>Fotos</span>
                    </button>
                    <button
                      v-if="cli.ruta_duena_poligono || cli.ruta_invadida"
                      type="button"
                      @click="proponerReasignacion(cli)"
                      class="px-2 py-0.5 bg-black hover:bg-neutral-800 text-white rounded text-[10px] font-bold transition-all shadow-2xs cursor-pointer"
                    >
                      Proponer a {{ cli.ruta_duena_poligono || cli.ruta_invadida }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab 3: Lista de Huérfanos -->
            <div v-else class="space-y-2">
              <div v-if="huerfanosFiltrados.length === 0" class="text-center py-16 text-[#86868B]">
                No se detectaron clientes fuera de polígono.
              </div>
              <div
                v-for="cli in huerfanosFiltrados"
                :key="cli.cliente_id"
                class="p-2.5 rounded-card border border-amber-200 bg-amber-50/40 hover:bg-amber-50/80 transition-all space-y-1"
              >
                <div class="flex items-start justify-between gap-1">
                  <div class="font-bold text-ink leading-tight">{{ cli.cliente || 'Sin nombre' }}</div>
                  <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-amber-600 text-white shrink-0">
                    HUÉRFANO
                  </span>
                </div>
                <div class="text-[10px] text-ink-secondary font-mono flex items-center justify-between">
                  <span>ID: {{ cli.cliente_id }}</span>
                  <span>Ruta actual: <strong>{{ cli.ruta }}</strong></span>
                </div>
                <div class="pt-1 flex justify-end border-t border-amber-100">
                  <button
                    type="button"
                    @click="abrirModalFotos(cli)"
                    class="px-1.5 py-0.5 bg-white hover:bg-neutral-100 text-ink rounded text-[10px] font-bold border border-neutral-200 shadow-2xs flex items-center gap-1 cursor-pointer"
                  >
                    <span>Fotos</span>
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Tabla Inferior: Propuestas Pendientes de la Ruta -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Propuestas Territoriales Pendientes</span>
            <span class="text-[10px] font-mono text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-bold">
              {{ propuestasPendientes.length }} pendientes
            </span>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll max-h-72">
          <table class="w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
              <tr>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">ID</th>
                <th class="py-2.5 px-3">Ruta Actual</th>
                <th class="py-2.5 px-3">Día Actual</th>
                <th class="py-2.5 px-3">Ruta Propuesta</th>
                <th class="py-2.5 px-3">Día Propuesto</th>
                <th class="py-2.5 px-3 text-center">Estado</th>
                <th class="py-2.5 px-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="propuestasPendientes.length === 0">
                <td colspan="8" class="py-8 text-center text-[#86868B] font-medium">
                  No hay propuestas pendientes para las rutas visibles en pantalla.
                </td>
              </tr>
              <tr
                v-for="p in propuestasPendientes"
                :key="p.id"
                class="hover:bg-neutral-50/70 transition-colors"
              >
                <td class="py-2 px-3 font-bold text-ink">
                  <div class="flex items-center gap-1.5">
                    <span>{{ p.cliente_nombre }}</span>
                    <button
                      type="button"
                      @click="abrirModalFotos({ cliente_id: p.cliente_id_origen, cliente: p.cliente_nombre, ruta: p.ruta_actual })"
                      class="px-1.5 py-0.5 bg-neutral-100 hover:bg-neutral-200 text-ink rounded text-[9px] font-bold border border-neutral-200 cursor-pointer shadow-2xs flex items-center gap-1"
                    >
                      <span>Fotos</span>
                    </button>
                  </div>
                </td>
                <td class="py-2 px-3 font-mono text-ink-secondary">{{ p.cliente_id_origen }}</td>
                <td class="py-2 px-3 font-mono text-rose-700 font-bold">{{ p.ruta_actual }}</td>
                <td class="py-2 px-3 font-mono">
                  <span class="px-2 py-0.5 rounded text-white text-[9px] font-bold" :style="{ backgroundColor: getColorDia(p.dia_actual) }">
                    {{ (p.dia_actual || 'SIN DÍA').toUpperCase() }}
                  </span>
                </td>
                <td class="py-2 px-3 font-mono text-emerald-700 font-bold">{{ p.ruta_propuesta }}</td>
                <td class="py-2 px-3 font-mono">
                  <select
                    :value="p.dia_propuesto"
                    @change="cambiarDiaEnTabla(p.id, $event.target.value)"
                    class="px-2 py-0.5 rounded text-white text-[9px] font-bold border border-white/30 focus:outline-none cursor-pointer shadow-2xs"
                    :style="{ backgroundColor: getColorDia(p.dia_propuesto) }"
                  >
                    <option
                      v-for="d in DIAS_SEMANA"
                      :key="d.val"
                      :value="d.val"
                      class="bg-white text-ink font-bold font-mono text-[10px]"
                    >
                      {{ d.label }}
                    </option>
                  </select>
                </td>
                <td class="py-2 px-3 text-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-50 text-amber-800 border border-amber-200">
                    {{ p.estado }}
                  </span>
                </td>
                <td class="py-2 px-3 text-right space-x-1 whitespace-nowrap">
                  <button
                    type="button"
                    @click="cambiarEstadoPropuesta(p.id, 'APLICADO')"
                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                  >
                    Aprobar
                  </button>
                  <button
                    type="button"
                    @click="cambiarEstadoPropuesta(p.id, 'DESCARTADO')"
                    class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                  >
                    Descartar
                  </button>
                  <button
                    type="button"
                    @click="revertirPropuesta(p.id)"
                    class="px-2 py-1 bg-neutral-200 hover:bg-neutral-300 text-ink rounded text-[10px] font-bold cursor-pointer"
                  >
                    Revertir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Fotos -->
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SaneamientoSubTabs from './Components/SaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const DIAS_SEMANA = [
  { val: 'LUNES', label: 'Lunes', corto: 'L', color: '#2563EB' },
  { val: 'MARTES', label: 'Martes', corto: 'M', color: '#16A34A' },
  { val: 'MIERCOLES', label: 'Miércoles', corto: 'X', color: '#EA580C' },
  { val: 'JUEVES', label: 'Jueves', corto: 'J', color: '#9333EA' },
  { val: 'VIERNES', label: 'Viernes', corto: 'V', color: '#CA8A04' },
  { val: 'SABADO', label: 'Sábado', corto: 'S', color: '#E11D48' },
]

function getColorDia(dia) {
  const dUpper = String(dia || '').trim().toUpperCase()
  const found = DIAS_SEMANA.find(d => d.val === dUpper)
  return found ? found.color : '#6B7280'
}

// ── Estado ──
const cargando = ref(false)
const canalSeleccionado = ref('TDB')
const rutasPoligonos = ref([])
const rutasPuntos = ref([])
const modoColorPuntos = ref('dia') // 'dia' | 'estado'
const abiertoDropdownRutas = ref(false)
const filtroTextoRutaDropdown = ref('')
const tabActivo = ref('balance')
const panelReplegado = ref(false)

const mosaicoFronteras = ref([])
const clientesFronteras = ref([])
const propuestasPendientes = ref([])

const queryBuscador = ref('')
const resultadosBuscador = ref([])
let searchTimeout = null

// Modal de Fotos
const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

// Edición de Vértices
const rutaSeleccionadaParaEditar = ref('')
const rutaEnEdicion = ref(null)
const editandoVertices = ref(false)
const guardandoFrontera = ref(false)
let markersVertices = []

// Leaflet
let map = null
let layerGroupPoligonos = null
let layerGroupPuntos = null
const mapaPoligonosPorRuta = new Map()

// ── Computed ──
const canalesDisponibles = computed(() => props.catalogo.canales || [])

const rutasFiltradasPorCanal = computed(() => {
  const rutas = props.catalogo.rutas || []
  if (canalSeleccionado.value === 'TODOS') return rutas
  const cNorm = String(canalSeleccionado.value).trim().toUpperCase()
  return rutas.filter(r => String(r.canal || '').trim().toUpperCase() === cNorm)
})

const rutasDropdownList = computed(() => {
  const lista = rutasFiltradasPorCanal.value
  if (!filtroTextoRutaDropdown.value.trim()) return lista
  const q = filtroTextoRutaDropdown.value.trim().toUpperCase()
  return lista.filter(r => r.ruta.toUpperCase().includes(q))
})

const totalIntrusos = computed(() => clientesFronteras.value.filter(c => c.es_intruso).length)
const totalHuerfanos = computed(() => clientesFronteras.value.filter(c => c.es_huerfano).length)

const intrusosFiltrados = computed(() => clientesFronteras.value.filter(c => c.es_intruso))
const huerfanosFiltrados = computed(() => clientesFronteras.value.filter(c => c.es_huerfano))

const tienePoligonoActual = computed(() => {
  if (!rutaEnEdicion.value) return false
  return mapaPoligonosPorRuta.has(rutaEnEdicion.value.toUpperCase())
})

const resumenRutas = computed(() => {
  const rutasMap = {}
  rutasPuntos.value.forEach(r => {
    rutasMap[r] = { ruta: r, total: 0, validos: 0, intrusos: 0, huerfanos: 0, conteoDias: {} }
  })

  clientesFronteras.value.forEach(c => {
    const r = c.ruta
    if (rutasMap[r]) {
      rutasMap[r].total++
      if (c.es_intruso) rutasMap[r].intrusos++
      else if (c.es_huerfano) rutasMap[r].huerfanos++
      else rutasMap[r].validos++

      const d = String(c.dia || 'SIN DÍA').trim().toUpperCase()
      rutasMap[r].conteoDias[d] = (rutasMap[r].conteoDias[d] || 0) + 1
    }
  })

  return Object.values(rutasMap)
})

// ── Ciclo de Vida ──
onMounted(() => {
  // Inicializar rutas por defecto
  const iniciales = rutasFiltradasPorCanal.value.map(r => r.ruta)
  rutasPoligonos.value = [...iniciales]
  rutasPuntos.value = [...iniciales]

  inicializarMapa()
  consultarTodo()
})

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
})

// ── Inicialización Leaflet ──
function inicializarMapa() {
  const container = document.getElementById('mapaIntrusos')
  if (!container || map) return

  map = L.map('mapaIntrusos', {
    zoomControl: true,
    preferCanvas: true,
  }).setView([-17.7833, -63.1821], 12)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '&copy; CartoDB &copy; OpenStreetMap',
  }).addTo(map)

  layerGroupPoligonos = L.layerGroup().addTo(map)
  layerGroupPuntos = L.layerGroup().addTo(map)
}

// ── Consultas ──
async function consultarTodo() {
  cargando.value = true
  try {
    await Promise.all([
      cargarFronteras(),
      cargarClientes(),
      cargarPropuestasPendientes(),
    ])
  } catch (err) {
    console.error('Error al consultar datos de saneamiento:', err)
  } finally {
    cargando.value = false
  }
}

async function cargarFronteras() {
  try {
    const params = canalSeleccionado.value !== 'TODOS' ? { canal: canalSeleccionado.value } : {}
    const { data } = await axios.get(route('supervisor.saneamiento.fronteras.todas'), { params })
    mosaicoFronteras.value = data?.fronteras || []
    renderizarPoligonos()
  } catch (err) {
    console.error('Error al cargar fronteras:', err)
  }
}

async function cargarClientes() {
  if (rutasPuntos.value.length === 0) {
    clientesFronteras.value = []
    renderizarPuntos()
    return
  }

  try {
    const { data } = await axios.post(route('supervisor.saneamiento.fronteras.clientes-activos'), {
      rutas: rutasPuntos.value,
    })
    clientesFronteras.value = data?.clientes || []
    renderizarPuntos()
  } catch (err) {
    console.error('Error al cargar clientes activos:', err)
  }
}

async function cargarPropuestasPendientes() {
  if (rutasPuntos.value.length === 0) {
    propuestasPendientes.value = []
    return
  }

  try {
    const { data } = await axios.get(route('supervisor.saneamiento.fronteras.propuestas-pendientes'), {
      params: { rutas: rutasPuntos.value },
    })
    propuestasPendientes.value = data || []
  } catch (err) {
    console.error('Error al cargar propuestas pendientes:', err)
  }
}

// ── Renderizado en Mapa ──
const PALETA_RUTAS = [
  '#0071E3', '#34C759', '#FF9500', '#AF52DE', '#FF2D55',
  '#5856D6', '#00C7BE', '#FF3B30', '#32ADE6', '#A2845E',
]

function renderizarPoligonos() {
  if (!map || !layerGroupPoligonos) return
  layerGroupPoligonos.clearLayers()
  mapaPoligonosPorRuta.clear()

  const visibles = mosaicoFronteras.value.filter(f => rutasPoligonos.value.includes(f.ruta))

  visibles.forEach((f, idx) => {
    const color = PALETA_RUTAS[idx % PALETA_RUTAS.length]
    const polygon = L.polygon(f.poligono_latlon, {
      color: color,
      weight: 2,
      opacity: 0.85,
      fillColor: color,
      fillOpacity: 0.12,
    })

    polygon.bindTooltip(
      `<div class="font-bold font-mono text-[11px]">${f.ruta}</div><div class="text-[9px] text-gray-500">${f.canal}</div>`,
      { sticky: true }
    )

    layerGroupPoligonos.addLayer(polygon)
    mapaPoligonosPorRuta.set(f.ruta.toUpperCase(), { polygon, coords: f.poligono_latlon })
  })
}

function renderizarPuntos() {
  if (!map || !layerGroupPuntos) return
  layerGroupPuntos.clearLayers()

  const bounds = []

  clientesFronteras.value.forEach(cli => {
    const lat = cli.latitud
    const lon = cli.longitud
    bounds.push([lat, lon])

    let color = '#2563EB'
    let radio = 4.5
    let strokeColor = '#ffffff'
    let strokeWidth = 1.5

    if (modoColorPuntos.value === 'dia') {
      color = getColorDia(cli.dia)
      if (cli.es_intruso) {
        strokeColor = '#DC2626'
        strokeWidth = 2.5
        radio = 5.5
      } else if (cli.es_huerfano) {
        strokeColor = '#D97706'
        strokeWidth = 2
      }
    } else {
      if (cli.es_intruso) {
        color = '#DC2626'
        radio = 5.5
      } else if (cli.es_huerfano) {
        color = '#D97706'
      } else {
        color = '#2563EB'
      }
    }

    const marker = L.circleMarker([lat, lon], {
      radius: radio,
      color: strokeColor,
      weight: strokeWidth,
      fillColor: color,
      fillOpacity: 0.9,
    })

    const popupHtml = `
      <div class="p-2 font-sans text-xs min-w-[200px]">
        <div class="font-bold text-slate-900 leading-tight">${cli.cliente}</div>
        <div class="text-[10px] text-slate-500 font-mono mt-0.5">ID: ${cli.cliente_id}</div>
        <div class="text-[10px] text-slate-500 truncate">${cli.direccion || 'Sin dirección'}</div>
        <div class="mt-1.5 pt-1 border-t border-slate-100 flex items-center justify-between text-[10px] font-mono">
          <span>Ruta: <strong>${cli.ruta}</strong></span>
          <span class="px-1.5 py-0.5 rounded text-white font-bold" style="background-color: ${getColorDia(cli.dia)}">${cli.dia}</span>
        </div>
        ${cli.es_intruso ? `<div class="mt-1 text-[10px] font-bold text-rose-600 font-mono">Invade: ${cli.ruta_duena_poligono || 'Otra ruta'}</div>` : ''}
      </div>
    `
    marker.bindPopup(popupHtml)
    layerGroupPuntos.addLayer(marker)
  })

  if (bounds.length > 0 && !editandoVertices.value) {
    try {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 })
    } catch {
      // ignore
    }
  }
}

// ── Conmutadores de Capas ──
function alCambiarCanal() {
  const rutasCanal = rutasFiltradasPorCanal.value.map(r => r.ruta)
  rutasPoligonos.value = [...rutasCanal]
  rutasPuntos.value = [...rutasCanal]
  consultarTodo()
}

function marcarTodasPoligonos(activar) {
  rutasPoligonos.value = activar ? rutasFiltradasPorCanal.value.map(r => r.ruta) : []
  renderizarPoligonos()
}

function togglePoligonoRuta(ruta) {
  const idx = rutasPoligonos.value.indexOf(ruta)
  if (idx >= 0) rutasPoligonos.value.splice(idx, 1)
  else rutasPoligonos.value.push(ruta)
  renderizarPoligonos()
}

function marcarTodasPuntos(activar) {
  rutasPuntos.value = activar ? rutasFiltradasPorCanal.value.map(r => r.ruta) : []
  cargarClientes()
  cargarPropuestasPendientes()
}

function togglePuntosRuta(ruta) {
  const idx = rutasPuntos.value.indexOf(ruta)
  if (idx >= 0) rutasPuntos.value.splice(idx, 1)
  else rutasPuntos.value.push(ruta)
  cargarClientes()
  cargarPropuestasPendientes()
}

function cambiarModoColor(modo) {
  modoColorPuntos.value = modo
  renderizarPuntos()
}

// ── Búsqueda de Clientes ──
function onSearchClienteInput() {
  clearTimeout(searchTimeout)
  if (queryBuscador.value.trim().length < 2) {
    resultadosBuscador.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('supervisor.saneamiento.clientes.buscar'), {
        params: { q: queryBuscador.value.trim() },
      })
      resultadosBuscador.value = data?.resultados || []
    } catch (err) {
      console.error(err)
    }
  }, 250)
}

function enfocarClienteEnMapa(cli) {
  resultadosBuscador.value = []
  queryBuscador.value = ''
  if (!map || !cli.latitud || !cli.longitud) return
  map.setView([cli.latitud, cli.longitud], 17, { animate: true })
}

// ── Acciones de Propuestas ──
async function proponerReasignacion(cli) {
  const rutaDestino = cli.ruta_duena_poligono || cli.ruta_invadida
  if (!rutaDestino) return

  try {
    await axios.post(route('supervisor.saneamiento.fronteras.propuestas.crear'), {
      cliente_id: cli.cliente_id,
      cliente_nombre: cli.cliente,
      ruta_actual: cli.ruta,
      dia_actual: cli.dia,
      ruta_propuesta: rutaDestino,
      dia_propuesto: cli.dia,
      distancia_metros: 0,
      observaciones: `Reasignación territorial por invasión de frontera de ruta ${rutaDestino}`,
    })
    await cargarPropuestasPendientes()
    await cargarClientes()
  } catch (err) {
    console.error('Error al crear propuesta de intruso:', err)
  }
}

async function cambiarEstadoPropuesta(propuestaId, estado) {
  try {
    await axios.post(route('supervisor.saneamiento.fronteras.propuestas.cambiar-estado'), {
      propuesta_id: propuestaId,
      estado: estado,
    })
    await cargarPropuestasPendientes()
    await cargarClientes()
  } catch (err) {
    console.error('Error al cambiar estado de propuesta:', err)
  }
}

async function cambiarDiaEnTabla(propuestaId, nuevoDia) {
  try {
    await axios.post(route('supervisor.saneamiento.fronteras.propuestas.cambiar-dia'), {
      propuesta_id: propuestaId,
      dia_propuesto: nuevoDia,
    })
    await cargarPropuestasPendientes()
    await cargarClientes()
  } catch (err) {
    console.error('Error al cambiar día:', err)
  }
}

async function revertirPropuesta(propuestaId) {
  try {
    await axios.post(route('supervisor.saneamiento.fronteras.propuestas.revertir'), {
      propuesta_id: propuestaId,
    })
    await cargarPropuestasPendientes()
    await cargarClientes()
  } catch (err) {
    console.error('Error al revertir propuesta:', err)
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}

// ── Edición de Vértices de Frontera ──
function alSeleccionarRutaParaEditar() {
  if (!rutaSeleccionadaParaEditar.value) {
    cancelarEdicionVertices(true)
    return
  }
  rutaEnEdicion.value = rutaSeleccionadaParaEditar.value
}

function activarEdicionVertices() {
  if (!tienePoligonoActual.value) return
  editandoVertices.value = true
  limpiarMarkersVertices()

  const entry = mapaPoligonosPorRuta.get(rutaEnEdicion.value.toUpperCase())
  const coords = entry.coords

  // Centrar en el polígono
  if (entry.polygon) {
    map.fitBounds(entry.polygon.getBounds(), { padding: [40, 40] })
  }

  coords.forEach((pt, idx) => {
    const marker = L.circleMarker(pt, {
      radius: 6,
      color: '#0071E3',
      fillColor: '#FFFFFF',
      fillOpacity: 1,
      weight: 2,
    }).addTo(map)

    // Simulación de arrastre / edición con clicks o drag
    marker.on('mousedown', function (e) {
      map.dragging.disable()
      function onMouseMove(moveEvent) {
        marker.setLatLng(moveEvent.latlng)
        coords[idx] = [moveEvent.latlng.lat, moveEvent.latlng.lng]
        entry.polygon.setLatLngs(coords)
      }
      function onMouseUp() {
        map.off('mousemove', onMouseMove)
        map.off('mouseup', onMouseUp)
        map.dragging.enable()
      }
      map.on('mousemove', onMouseMove)
      map.on('mouseup', onMouseUp)
    })

    markersVertices.push(marker)
  })
}

function limpiarMarkersVertices() {
  markersVertices.forEach(m => m.remove())
  markersVertices = []
}

function cancelarEdicionVertices(limpiarRuta = false) {
  editandoVertices.value = false
  limpiarMarkersVertices()
  if (limpiarRuta) {
    rutaEnEdicion.value = null
    rutaSeleccionadaParaEditar.value = ''
  }
  renderizarPoligonos()
}

async function guardarFronteraActual() {
  if (!rutaEnEdicion.value) return
  guardandoFrontera.value = true
  try {
    const entry = mapaPoligonosPorRuta.get(rutaEnEdicion.value.toUpperCase())
    const coords = entry.coords

    await axios.post(route('supervisor.saneamiento.fronteras.guardar'), {
      ruta: rutaEnEdicion.value,
      coordenadas: coords,
    })

    cancelarEdicionVertices(false)
    await cargarFronteras()
    await cargarClientes()
  } catch (err) {
    console.error('Error al guardar frontera:', err)
  } finally {
    guardandoFrontera.value = false
  }
}
</script>

