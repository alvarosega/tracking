<template>
  <SupervisorLayout title="Saneamiento | Clientes Duplicados">
    <div class="space-y-4">
      <SaneamientoSubTabs active-tab="duplicados" />

      <!-- Barra de Filtros y Configuración de Radio -->
      <div class="apple-glass-card rounded-[16px] p-2.5 sm:p-3 mb-3 select-none font-sans relative z-30">
        <div class="flex flex-wrap items-center justify-between gap-2.5 text-[11px]">
          
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector Canal (Multiselección) -->
            <div class="relative inline-block text-left" ref="canalDropdownRef">
              <button
                type="button"
                @click="menuCanalAbierto = !menuCanalAbierto"
                class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] hover:bg-black/[0.04] transition-colors cursor-pointer"
              >
                <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
                <span class="max-w-[130px] truncate text-[11px] font-bold text-[#0071E3]">{{ etiquetaCanales }}</span>
                <svg
                  class="w-3 h-3 text-[#86868B] transition-transform duration-200 pointer-events-none"
                  :class="menuCanalAbierto ? 'rotate-180 text-[#0071E3]' : ''"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Dropdown Flotante Canales -->
              <div
                v-if="menuCanalAbierto"
                class="absolute top-full left-0 mt-1.5 w-60 bg-white/95 backdrop-blur-md rounded-[14px] z-[105] p-2 space-y-1 shadow-2xl border border-black/[0.08]"
              >
                <div class="flex items-center justify-between pb-1.5 mb-1 border-b border-black/[0.06] px-1 text-[10px]">
                  <span class="font-bold text-[#86868B] uppercase tracking-wider">Canales</span>
                  <div class="flex items-center gap-2">
                    <button type="button" @click="seleccionarTodosCanales" class="font-bold text-[#0071E3] hover:underline cursor-pointer">Todos</button>
                    <span class="text-neutral-300">|</span>
                    <button type="button" @click="limpiarCanales" class="font-bold text-[#86868B] hover:underline cursor-pointer">Limpiar</button>
                  </div>
                </div>
                <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5">
                  <label
                    v-for="c in canalesDisponibles"
                    :key="c"
                    class="flex items-center gap-2 px-2 py-1 rounded-[8px] hover:bg-black/[0.04] cursor-pointer select-none text-[11px]"
                  >
                    <input
                      type="checkbox"
                      :value="c"
                      v-model="canalesSeleccionados"
                      class="rounded text-[#0071E3] focus:ring-0 w-3.5 h-3.5 cursor-pointer border-slate-300"
                    />
                    <span class="font-medium text-[#1D1D1F]">{{ c }}</span>
                  </label>
                </div>
                <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between px-1 text-[10px] text-[#86868B]">
                  <span>{{ canalesSeleccionados.length }} seleccionados</span>
                  <button
                    type="button"
                    @click="menuCanalAbierto = false; ejecutarAuditoriaDuplicados()"
                    class="font-bold text-[#0071E3] hover:underline cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>

            <!-- Selector Tipo de Grupo (Misma Ubicación / Mismos Datos / Todos) -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Tipo Grupo:</span>
              <select
                v-model="filtroTipoGrupo"
                class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Grupos</option>
                <option value="MISMA_UBICACION">Misma Ubicación (≤ {{ radioMetros }}m)</option>
                <option value="MISMOS_DATOS">Mismos Datos (Nombre, NIT, etc.)</option>
                <option value="MISMA_UBICACION_Y_DATOS">Misma Ubicación & Mismo Nombre</option>
                <option value="MISMOS_DATOS_DISTANTE">Mismo Nombre, Dif. Ubicación</option>
                <option value="MISMA_UBICACION_SOLO">Misma Ubicación, Dif. Nombre</option>
              </select>
            </div>

            <!-- Selector de Radio en Metros -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Radio:</span>
              <select
                v-model="radioMetros"
                @change="ejecutarAuditoriaDuplicados"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
              >
                <option :value="10">10 metros</option>
                <option :value="20">20 metros (Recomendado)</option>
                <option :value="50">50 metros</option>
                <option :value="100">100 metros</option>
                <option :value="200">200 metros</option>
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="ejecutarAuditoriaDuplicados"
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
              <span>{{ cargando ? 'Auditando...' : 'Auditar Duplicados' }}</span>
            </button>
          </div>

          <!-- Badges Resumen -->
          <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-mono sm:ml-auto">
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-[#C9342C] font-bold" title="Total grupos mostrados">
              Grupos: {{ clustersFiltrados.length }}
            </span>
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-purple-700 font-bold" title="Misma ubicación y mismo nombre">
              Ubic + Datos: {{ conteoMismaUbicacionYDatos }}
            </span>
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-amber-700 font-bold" title="Mismos datos (nombre, nit, etc.)">
              Mismos Datos: {{ conteoMismosDatos }}
            </span>
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-blue-700 font-bold" title="Misma ubicación física">
              Misma Ubic: {{ conteoMismaUbicacion }}
            </span>
            <span class="px-2.5 py-1 rounded-[10px] apple-control-pill text-[#6E6E73]" title="Total clientes implicados">
              Clientes: {{ totalClientesImplicados }}
            </span>
          </div>

        </div>
      </div>

      <!-- Selector de Vista Móvil (< lg) -->
      <div class="flex lg:hidden items-center justify-center p-1 bg-slate-100 rounded-xl mb-3 border border-slate-200 select-none">
        <button
          type="button"
          @click="setVistaMovil('mapa')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
          :class="vistaMovil === 'mapa' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600'"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
          </svg>
          <span>Mapa</span>
        </button>
        <button
          type="button"
          @click="setVistaMovil('grupos')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
          :class="vistaMovil === 'grupos' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600'"
        >
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
          </svg>
          <span>Grupos ({{ clustersFiltrados.length }})</span>
        </button>
      </div>

      <!-- Contenedor Principal: Mapa y Lista de Grupos -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        <!-- Mapa (2 columnas) -->
        <div
          class="relative glass-panel rounded-card overflow-hidden shadow-subtle min-h-[440px] sm:min-h-[580px] flex-col border border-black/[0.06] bg-white/70 backdrop-blur-md"
          :class="vistaMovil === 'mapa' ? 'flex lg:col-span-2' : 'hidden lg:flex lg:col-span-2'"
        >
          <div class="px-4 py-2 border-b border-neutral-100 flex flex-wrap items-center justify-between gap-2 bg-white/80 shrink-0">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
              <span class="font-bold text-[12px] text-ink">Geolocalización de Colisiones Espaciales & Datos</span>
            </div>

            <div class="flex items-center gap-2 text-[10px]">
              <!-- Leyenda Rápida de Colores -->
              <div class="hidden sm:flex items-center gap-2 border-r border-slate-200 pr-2 font-mono text-[9.5px]">
                <span class="flex items-center gap-1 text-purple-700">
                  <span class="w-2 h-2 rounded-full bg-[#7C3AED]"></span> Ubic + Nombre
                </span>
                <span class="flex items-center gap-1 text-amber-700">
                  <span class="w-2 h-2 rounded-full bg-[#D97706]"></span> Mismo Nombre
                </span>
                <span class="flex items-center gap-1 text-blue-700">
                  <span class="w-2 h-2 rounded-full bg-[#2563EB]"></span> Misma Ubic
                </span>
              </div>

              <!-- Selector de Capa Calles / Satélite -->
              <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
                <button
                  type="button"
                  @click="cambiarCapaMapa('openStreetMap')"
                  class="px-2 py-0.5 rounded font-medium transition-all cursor-pointer"
                  :class="capaActual === 'openStreetMap' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
                >
                  Calles
                </button>
                <button
                  type="button"
                  @click="cambiarCapaMapa('satellite')"
                  class="px-2 py-0.5 rounded font-medium transition-all cursor-pointer"
                  :class="capaActual === 'satellite' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
                >
                  Satélite
                </button>
              </div>

              <!-- Botón Recentrar -->
              <button
                type="button"
                @click="recentrarMapa"
                class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded font-semibold text-slate-700 cursor-pointer shadow-2xs"
                title="Recentrar mapa en los duplicados encontrados"
              >
                Recentrar
              </button>

              <span class="text-[10px] text-ink-secondary font-mono border-l border-slate-200 pl-2">Radio: {{ radioMetros }}m</span>
            </div>
          </div>

          <div id="mapaDuplicados" class="flex-1 w-full min-h-[380px] sm:min-h-[520px] h-[50vh] sm:h-[520px] relative z-0"></div>
        </div>

        <!-- Panel Lateral de Grupos (1 columna) -->
        <div
          class="glass-panel rounded-card shadow-subtle flex-col overflow-hidden min-h-[440px] sm:min-h-[580px] max-h-[600px] border border-black/[0.06] bg-white/70 backdrop-blur-md"
          :class="vistaMovil === 'grupos' ? 'flex' : 'hidden lg:flex'"
        >
          <div class="px-3 py-2 border-b border-neutral-100 flex items-center justify-between bg-white/80 shrink-0">
            <span class="font-bold text-[11px] text-ink">Colisiones Detectadas</span>
            <span class="text-[10px] font-mono text-ink-secondary">{{ clustersFiltrados.length }} grupos</span>
          </div>

          <div class="flex-1 overflow-y-auto custom-scroll p-3 space-y-3 text-[11px]">
            <div v-if="clustersFiltrados.length === 0" class="text-center py-20 text-[#86868B]">
              {{ cargando ? 'Ejecutando auditoría de proximidad y datos...' : `No se encontraron clientes duplicados para los filtros seleccionados.` }}
            </div>

            <!-- Tarjeta de Grupo de Duplicados -->
            <div
              v-for="grupo in clustersFiltrados"
              :key="grupo.cluster_id"
              class="p-3 rounded-card border bg-white/80 hover:bg-white shadow-2xs space-y-2 transition-all cursor-pointer"
              :class="[
                grupoSeleccionadoId === grupo.cluster_id ? 'ring-2' : '',
                grupo.clasificacion === 'MISMA_UBICACION_Y_DATOS'
                  ? (grupoSeleccionadoId === grupo.cluster_id ? 'border-purple-400 ring-purple-200' : 'border-purple-200')
                  : (grupo.clasificacion === 'MISMOS_DATOS'
                    ? (grupoSeleccionadoId === grupo.cluster_id ? 'border-amber-400 ring-amber-200' : 'border-amber-200')
                    : (grupoSeleccionadoId === grupo.cluster_id ? 'border-blue-400 ring-blue-200' : 'border-blue-200'))
              ]"
              @click="enfocarGrupoEnMapa(grupo)"
            >
              <div class="flex items-center justify-between pb-1.5 border-b border-neutral-100">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span
                    class="font-bold font-mono text-[11px] shrink-0"
                    :class="grupo.clasificacion === 'MISMA_UBICACION_Y_DATOS' ? 'text-purple-700' : (grupo.clasificacion === 'MISMOS_DATOS' ? 'text-amber-700' : 'text-blue-700')"
                  >
                    Grupo #{{ grupo.cluster_id }}
                  </span>
                  <span
                    class="px-1.5 py-0.5 rounded text-[8.5px] font-bold font-mono uppercase truncate"
                    :class="grupo.clasificacion === 'MISMA_UBICACION_Y_DATOS' ? 'bg-purple-100 text-purple-800' : (grupo.clasificacion === 'MISMOS_DATOS' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800')"
                  >
                    {{ grupo.clasificacion === 'MISMA_UBICACION_Y_DATOS' ? 'Misma Ubic & Mismo Nombre' : (grupo.clasificacion === 'MISMOS_DATOS' ? 'Mismo Nombre (Dif. Ubicación)' : 'Misma Ubicación (Dif. Datos)') }}
                  </span>
                </div>
                <span
                  class="text-[10px] font-mono px-2 py-0.5 rounded font-bold shrink-0 ml-1.5"
                  :class="grupo.distancia_minima_m <= radioMetros ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                >
                  {{ formatearDistancia(grupo.distancia_minima_m) }}
                </span>
              </div>

              <!-- Criterios Compartidos -->
              <div v-if="grupo.criterios && grupo.criterios.length > 0" class="flex flex-wrap gap-1">
                <span
                  v-for="crit in grupo.criterios"
                  :key="crit.campo"
                  class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-neutral-100 text-neutral-700 border border-neutral-200"
                >
                  {{ crit.campo }}: {{ crit.valores.join(', ') }}
                </span>
              </div>

              <!-- Clientes del Grupo -->
              <div class="space-y-1.5">
                <div
                  v-for="cli in grupo.clientes"
                  :key="cli.cliente_id"
                  class="p-2 rounded bg-neutral-50/70 border border-neutral-200/60 text-[10px] space-y-1"
                >
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-ink leading-tight truncate pr-2">{{ cli.cliente }}</span>
                    <span class="font-mono text-ink-secondary shrink-0">ID: {{ cli.cliente_id }}</span>
                  </div>
                  <div class="flex items-center justify-between text-ink-secondary font-mono text-[9px]">
                    <span>Canal: <strong class="text-ink">{{ cli.canal }}</strong> &bull; Ruta: <strong class="text-ink">{{ cli.ruta }}</strong> ({{ cli.dia }})</span>
                    <span class="text-emerald-700 font-bold">MTD: Bs. {{ cli.venta_mtd }}</span>
                  </div>
                  <div class="flex items-center justify-between pt-1 border-t border-neutral-200/60 text-[9px]">
                    <span class="text-neutral-500 truncate max-w-[140px]">{{ cli.direccion }}</span>
                    <button
                      type="button"
                      @click.stop="abrirModalFotos(cli)"
                      class="px-1.5 py-0.2 bg-white hover:bg-neutral-100 text-ink rounded font-bold border border-neutral-200 shadow-2xs"
                    >
                      Fotos
                    </button>
                  </div>
                </div>
              </div>

              <!-- Venta total del grupo -->
              <div class="pt-1 flex items-center justify-between text-[10px] font-mono text-neutral-600">
                <span>Venta Total MTD:</span>
                <span class="font-bold text-ink">Bs. {{ grupo.venta_mtd_total }}</span>
              </div>

              <!-- Acciones de Resolución -->
              <div class="pt-2 border-t border-neutral-100 flex flex-wrap items-center justify-end gap-1.5">
                <button
                  type="button"
                  @click.stop="proponerInactivar(grupo)"
                  class="px-2 py-1 bg-neutral-900 hover:bg-black text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                >
                  Inactivar Duplicado
                </button>
                <button
                  type="button"
                  @click.stop="proponerFusion(grupo)"
                  class="px-2 py-1 bg-[#0071E3] hover:bg-[#0077ED] text-white rounded text-[10px] font-bold shadow-2xs cursor-pointer"
                >
                  Fusionar
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Tabla Inferior: Propuestas Pendientes de Duplicados -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex flex-wrap items-center justify-between gap-2 bg-white/80 shrink-0">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Propuestas de Resolución de Duplicados</span>
            <span class="text-[10px] font-mono text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-bold">
              {{ propuestasFiltradas.length }} pendientes
            </span>
          </div>

          <!-- Buscador rápido para la tabla si hay varias filas -->
          <div v-if="propuestasPendientes.length > 3" class="w-64">
            <input
              v-model="busquedaTabla"
              type="text"
              placeholder="Buscar por cliente, ID o ruta..."
              class="w-full h-7 px-2.5 text-[11px] bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
            />
          </div>
        </div>

        <!-- Contenedor con Scroll Vertical y Horizontal -->
        <div class="overflow-x-auto overflow-y-auto max-h-[380px] custom-scroll relative">
          <table class="w-full text-left text-[11px] border-collapse">
            <thead class="sticky top-0 bg-slate-100 text-slate-700 uppercase text-[9.5px] font-mono font-bold tracking-wider border-b border-slate-200 z-10 shadow-2xs">
              <tr>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Cliente Origen</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">ID Origen</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Cliente Vinculado</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Ruta</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Acción Propuesta</th>
                <th class="py-2.5 px-3 bg-slate-100 whitespace-nowrap">Observaciones</th>
                <th class="py-2.5 px-3 bg-slate-100 text-center whitespace-nowrap">Estado</th>
                <th class="py-2.5 px-3 bg-slate-100 text-right whitespace-nowrap">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="propuestasFiltradas.length === 0">
                <td colspan="8" class="py-8 text-center text-[#86868B] font-medium">
                  {{ busquedaTabla ? 'No se encontraron propuestas coincidentes con la búsqueda.' : 'No hay propuestas pendientes de resolución de duplicados.' }}
                </td>
              </tr>
              <tr
                v-for="p in propuestasFiltradas"
                :key="p.id"
                class="hover:bg-neutral-50/70 transition-colors"
              >
                <td class="py-2 px-3 font-bold text-ink">{{ p.cliente_nombre }}</td>
                <td class="py-2 px-3 font-mono text-ink-secondary">{{ p.cliente_id_origen }}</td>
                <td class="py-2 px-3 font-mono text-blue-700 font-bold">{{ p.cliente_id_vinculado || '-' }}</td>
                <td class="py-2 px-3 font-mono">{{ p.ruta_actual }}</td>
                <td class="py-2 px-3 font-mono">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-neutral-100 text-neutral-800 border border-neutral-200">
                    {{ p.accion_propuesta }}
                  </span>
                </td>
                <td class="py-2 px-3 text-neutral-500 truncate max-w-xs">{{ p.observaciones }}</td>
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { mapsConfig } from '@/Config/maps'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SaneamientoSubTabs from './Components/SaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const cargando = ref(false)
const vistaMovil = ref('mapa')

// Selector de Canales (Multiselección)
const canalDropdownRef = ref(null)
const menuCanalAbierto = ref(false)
const canalesDisponibles = computed(() => props.catalogo.canales || [])
const canalesSeleccionados = ref(
  props.catalogo.canales?.includes('TDB') ? ['TDB'] : [...(props.catalogo.canales || [])]
)

// Selector de Tipo de Grupo
const filtroTipoGrupo = ref('TODOS')

// Selector de Radio
const radioMetros = ref(20)

// Datos
const clusters = ref([])
const propuestasPendientes = ref([])
const grupoSeleccionadoId = ref(null)
const busquedaTabla = ref('')

function setVistaMovil(v) {
  vistaMovil.value = v
  if (v === 'mapa') {
    nextTick(() => {
      if (map) map.invalidateSize()
    })
  }
}

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap')
let map = null
let currentTileLayer = null
let layerGroupMarkers = null
let mapResizeObserver = null

// Etiqueta para el selector de canales
const etiquetaCanales = computed(() => {
  const total = canalesDisponibles.value.length
  const sel = canalesSeleccionados.value.length
  if (sel === 0) return 'Ninguno'
  if (sel === total) return 'Todos los Canales'
  if (sel === 1) return canalesSeleccionados.value[0]
  return `${sel} canales`
})

function seleccionarTodosCanales() {
  canalesSeleccionados.value = [...canalesDisponibles.value]
}

function limpiarCanales() {
  canalesSeleccionados.value = []
}

function handleClickOutside(event) {
  if (canalDropdownRef.value && !canalDropdownRef.value.contains(event.target)) {
    menuCanalAbierto.value = false
  }
}

// Rutas asociadas a los canales seleccionados
const rutasFiltradasPorCanal = computed(() => {
  if (canalesSeleccionados.value.length === 0) return []
  if (canalesSeleccionados.value.length === canalesDisponibles.value.length) return null
  return (props.catalogo.rutas || [])
    .filter(r => canalesSeleccionados.value.includes(r.canal))
    .map(r => r.ruta)
})

// Filtrado reactivo de clusters por Tipo de Grupo
const clustersFiltrados = computed(() => {
  const tipo = filtroTipoGrupo.value
  if (!tipo || tipo === 'TODOS') return clusters.value

  return clusters.value.filter(cl => {
    if (tipo === 'MISMA_UBICACION') {
      return cl.tiene_misma_ubicacion === true
    }
    if (tipo === 'MISMOS_DATOS') {
      return cl.tiene_mismos_datos === true
    }
    if (tipo === 'MISMA_UBICACION_Y_DATOS') {
      return cl.clasificacion === 'MISMA_UBICACION_Y_DATOS'
    }
    if (tipo === 'MISMOS_DATOS_DISTANTE') {
      return cl.clasificacion === 'MISMOS_DATOS'
    }
    if (tipo === 'MISMA_UBICACION_SOLO') {
      return cl.clasificacion === 'MISMA_UBICACION'
    }
    return true
  })
})

const conteoMismaUbicacion = computed(() => {
  return clusters.value.filter(cl => cl.tiene_misma_ubicacion).length
})

const conteoMismosDatos = computed(() => {
  return clusters.value.filter(cl => cl.tiene_mismos_datos).length
})

const conteoMismaUbicacionYDatos = computed(() => {
  return clusters.value.filter(cl => cl.clasificacion === 'MISMA_UBICACION_Y_DATOS').length
})

const totalClientesImplicados = computed(() => {
  let count = 0
  clustersFiltrados.value.forEach(cl => {
    count += cl.cantidad_clientes || 0
  })
  return count
})

const propuestasFiltradas = computed(() => {
  if (!busquedaTabla.value.trim()) return propuestasPendientes.value
  const q = busquedaTabla.value.toLowerCase().trim()
  return propuestasPendientes.value.filter(p =>
    (p.cliente_nombre && p.cliente_nombre.toLowerCase().includes(q)) ||
    (p.cliente_id_origen && String(p.cliente_id_origen).includes(q)) ||
    (p.cliente_id_vinculado && String(p.cliente_id_vinculado).includes(q)) ||
    (p.ruta_actual && p.ruta_actual.toLowerCase().includes(q)) ||
    (p.accion_propuesta && p.accion_propuesta.toLowerCase().includes(q)) ||
    (p.observaciones && p.observaciones.toLowerCase().includes(q))
  )
})

watch(clustersFiltrados, () => {
  renderizarClustersEnMapa()
})

onMounted(() => {
  document.addEventListener('click', handleClickOutside)

  nextTick(() => {
    inicializarMapa()
    ejecutarAuditoriaDuplicados()

    const container = document.getElementById('mapaDuplicados')
    if (container && window.ResizeObserver) {
      mapResizeObserver = new ResizeObserver(() => {
        if (map) map.invalidateSize()
      })
      mapResizeObserver.observe(container)
    }
  })
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)

  if (mapResizeObserver) {
    mapResizeObserver.disconnect()
    mapResizeObserver = null
  }
  if (map) {
    map.remove()
    map = null
  }
})

function cambiarCapaMapa(nombreCapa) {
  if (!map) return
  const layerConf = mapsConfig.tileLayers[nombreCapa] || mapsConfig.tileLayers.openStreetMap
  capaActual.value = nombreCapa

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer)
  }

  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map)
}

function inicializarMapa() {
  const container = document.getElementById('mapaDuplicados')
  if (!container || map) return

  const defaultLoc = mapsConfig.defaultLocation || { lat: -16.5050, lng: -68.1650, zoom: 13 }

  map = L.map('mapaDuplicados', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom)

  L.control.zoom({ position: 'topright' }).addTo(map)
  cambiarCapaMapa(capaActual.value)

  layerGroupMarkers = L.featureGroup().addTo(map)

  setTimeout(() => {
    if (map) {
      map.invalidateSize()
    }
  }, 250)
}

function recentrarMapa() {
  if (!map) return
  const bounds = []
  clustersFiltrados.value.forEach(grupo => {
    if (!Array.isArray(grupo.clientes)) return
    grupo.clientes.forEach(cli => {
      const lat = parseFloat(cli.latitud)
      const lng = parseFloat(cli.longitud)
      if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
        bounds.push([lat, lng])
      }
    })
  })

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 })
  } else {
    const defaultLoc = mapsConfig.defaultLocation || { lat: -16.5050, lng: -68.1650, zoom: 13 }
    map.setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom)
  }
}

function formatearDistancia(metros) {
  if (metros === undefined || metros === null) return '0 m'
  const m = parseFloat(metros)
  if (isNaN(m)) return '0 m'
  if (m >= 1000) {
    return (m / 1000).toFixed(2) + ' km'
  }
  return m.toFixed(1) + ' m'
}

async function ejecutarAuditoriaDuplicados() {
  cargando.value = true
  try {
    const rutasParam = rutasFiltradasPorCanal.value
    const canalesParam = canalesSeleccionados.value.length === canalesDisponibles.value.length
      ? null
      : (canalesSeleccionados.value.length > 0 ? canalesSeleccionados.value : ['__NINGUNO__'])

    const [resAudit, resProps] = await Promise.all([
      axios.post(route('supervisor.saneamiento.duplicados.auditar'), {
        rutas: rutasParam,
        canales: canalesParam,
        radio_metros: radioMetros.value,
        tipo_grupo: 'TODOS',
      }),
      axios.get(route('supervisor.saneamiento.duplicados.propuestas-pendientes'), {
        params: {
          rutas: rutasParam ? rutasParam.join(',') : null,
        },
      }),
    ])

    clusters.value = resAudit.data?.clusters || []
    propuestasPendientes.value = resProps.data || []

    renderizarClustersEnMapa()
  } catch (err) {
    console.error('Error al auditar duplicados:', err)
  } finally {
    cargando.value = false
    setTimeout(() => {
      if (map) map.invalidateSize()
    }, 200)
  }
}

function renderizarClustersEnMapa() {
  if (!map) return
  if (!layerGroupMarkers) {
    layerGroupMarkers = L.featureGroup().addTo(map)
  }
  layerGroupMarkers.clearLayers()

  const bounds = []

  clustersFiltrados.value.forEach(grupo => {
    let color = '#2563EB'
    let badgeTexto = 'Misma Ubicación'
    if (grupo.clasificacion === 'MISMA_UBICACION_Y_DATOS') {
      color = '#7C3AED'
      badgeTexto = 'Misma Ubicación & Mismo Nombre'
    } else if (grupo.clasificacion === 'MISMOS_DATOS') {
      color = '#D97706'
      badgeTexto = 'Mismo Nombre (Dif. Ubicación)'
    }

    if (!Array.isArray(grupo.clientes)) return

    const coordsGrupo = []

    grupo.clientes.forEach(cli => {
      const lat = parseFloat(cli.latitud)
      const lng = parseFloat(cli.longitud)

      if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) {
        return
      }

      bounds.push([lat, lng])
      coordsGrupo.push([lat, lng])

      // Círculo de radio si el grupo tiene colisión por proximidad física
      if (grupo.tiene_misma_ubicacion) {
        const circle = L.circle([lat, lng], {
          radius: radioMetros.value || 20,
          color: color,
          weight: 1.5,
          fillColor: color,
          fillOpacity: 0.12,
        })
        layerGroupMarkers.addLayer(circle)
      }

      const marker = L.circleMarker([lat, lng], {
        radius: 6.5,
        color: '#FFFFFF',
        weight: 2,
        fillColor: color,
        fillOpacity: 0.95,
      })

      marker.bindPopup(`
        <div style="font-family: inherit; font-size: 11px; color: #0F172A; min-width: 210px; padding: 2px;">
          <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">${escapeHtml(cli.cliente)}</div>
          <div style="color: #64748B; font-family: monospace; font-size: 10px;">ID: ${cli.cliente_id} &bull; Canal: <b>${escapeHtml(cli.canal || 'S/C')}</b> &bull; Ruta: <b>${escapeHtml(cli.ruta)}</b> (${escapeHtml(cli.dia || 'S/D')})</div>
          <div style="color: #64748B; font-size: 10px; margin-top: 2px;">${escapeHtml(cli.direccion || 'Sin dirección')}</div>
          <div style="margin-top: 4px; font-family: monospace; font-weight: 700; color: #059669;">MTD: Bs. ${cli.venta_mtd}</div>
          <div style="margin-top: 4px; padding-top: 4px; border-top: 1px solid #E2E8F0; font-size: 10px; font-weight: 700; color: ${color};">
            ${badgeTexto} &bull; Distancia: ${formatearDistancia(grupo.distancia_minima_m)}
          </div>
        </div>
      `, { maxWidth: 280 })

      marker.on('click', () => {
        grupoSeleccionadoId.value = grupo.cluster_id
      })

      layerGroupMarkers.addLayer(marker)
    })

    // Si es un grupo distante (mismo nombre, diferente ubicación) y tiene 2 o más puntos, trazar línea guía
    if (!grupo.tiene_misma_ubicacion && coordsGrupo.length >= 2) {
      const linea = L.polyline(coordsGrupo, {
        color: color,
        weight: 2,
        dashArray: '6, 8',
        opacity: 0.75,
      })
      layerGroupMarkers.addLayer(linea)
    }
  })

  if (bounds.length > 0) {
    try {
      map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 })
    } catch {
      // ignore
    }
  }

  setTimeout(() => {
    if (map) map.invalidateSize()
  }, 100)
}

function enfocarGrupoEnMapa(grupo) {
  grupoSeleccionadoId.value = grupo.cluster_id
  setVistaMovil('mapa')
  if (!map || !grupo.clientes || grupo.clientes.length === 0) return

  const bounds = []
  grupo.clientes.forEach(c => {
    const lat = parseFloat(c.latitud)
    const lng = parseFloat(c.longitud)
    if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
      bounds.push([lat, lng])
    }
  })

  if (bounds.length > 0) {
    nextTick(() => {
      if (map) {
        map.invalidateSize()
        if (bounds.length === 1) {
          map.setView(bounds[0], 17)
        } else {
          map.fitBounds(bounds, { padding: [60, 60], maxZoom: 18 })
        }
      }
    })
  }
}

function escapeHtml(str) {
  if (!str) return ''
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

async function proponerInactivar(grupo) {
  if (!grupo.clientes || grupo.clientes.length < 2) return
  // El cliente con menor venta se propone inactivar respecto al de mayor venta
  const ordenados = [...grupo.clientes].sort((a, b) => a.venta_mtd - b.venta_mtd)
  const menor = ordenados[0]
  const mayor = ordenados[ordenados.length - 1]

  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: menor.cliente_id,
      cliente_id_vinculado: mayor.cliente_id,
      cliente_nombre: menor.cliente,
      ruta_actual: menor.ruta,
      dia_actual: menor.dia,
      accion_propuesta: 'INACTIVAR_DUPLICADO',
      distancia_metros: grupo.distancia_minima_m,
      observaciones: `Inactivación por doble código respecto a cliente ID #${mayor.cliente_id}`,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error('Error al registrar propuesta:', err)
  }
}

async function proponerFusion(grupo) {
  if (!grupo.clientes || grupo.clientes.length < 2) return
  const c1 = grupo.clientes[0]
  const c2 = grupo.clientes[1]

  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: c1.cliente_id,
      cliente_id_vinculado: c2.cliente_id,
      cliente_nombre: c1.cliente,
      ruta_actual: c1.ruta,
      dia_actual: c1.dia,
      accion_propuesta: 'FUSIONAR_CLIENTE',
      distancia_metros: grupo.distancia_minima_m,
      observaciones: `Fusión de cuentas de cliente ID #${c1.cliente_id} y ID #${c2.cliente_id}`,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error('Error al registrar propuesta de fusión:', err)
  }
}

async function cambiarEstadoPropuesta(propuestaId, estado) {
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.cambiar-estado'), {
      propuesta_id: propuestaId,
      estado: estado,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error(err)
  }
}

async function revertirPropuesta(propuestaId) {
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.revertir'), {
      propuesta_id: propuestaId,
    })
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error(err)
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>
