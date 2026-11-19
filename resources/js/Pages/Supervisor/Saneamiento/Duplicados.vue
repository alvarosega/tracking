<template>
  <SupervisorLayout title="Saneamiento | Clientes Duplicados">
    <div class="space-y-3 font-sans text-slate-900 select-none">
      <!-- Navegacion de Submodulos Saneamiento Padron -->
      <AuditoriaSaneamientoSubTabs active-tab="duplicados" :compact="true" />

      <!-- ============================================================== -->
      <!-- CABECERA PRINCIPAL: SELECTOR DE MODO, FILTROS Y RESUMEN       -->
      <!-- ============================================================== -->
      <div class="apple-glass-card rounded-[16px] p-3 border border-slate-200/90 shadow-2xs space-y-2.5 relative z-30">
        <!-- Fila 1: Selector de Modo Segmentado (Todos | Por Ubicacion | Por Datos | Criticos) -->
        <div class="flex flex-wrap items-center justify-between gap-2">
          <!-- Segmented Tab de Modo -->
          <div class="inline-flex p-0.5 bg-slate-100 rounded-xl border border-slate-200 text-xs shrink-0">
            <button
              type="button"
              @click="cambiarModo('TODOS')"
              class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroModo === 'TODOS' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
            >
              <span>Todos</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'TODOS' ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700'">
                {{ totalGruposTodos }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarModo('CRITICOS')"
              class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroModo === 'CRITICOS' ? 'bg-purple-600 text-white shadow-2xs font-bold' : 'text-purple-700 hover:text-purple-900'"
              title="Misma ubicacion fisica y mismo nombre comercial"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="filtroModo === 'CRITICOS' ? 'bg-white' : 'bg-purple-600'"></span>
              <span>Criticos (Ubic + Datos)</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'CRITICOS' ? 'bg-white/20 text-white' : 'bg-purple-100 text-purple-800'">
                {{ conteoMismaUbicacionYDatos }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarModo('DATOS')"
              class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroModo === 'DATOS' ? 'bg-amber-600 text-white shadow-2xs font-bold' : 'text-amber-700 hover:text-amber-900'"
              title="Coincidencia de Nombre, NIT, CI, Telefono o Factura"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="filtroModo === 'DATOS' ? 'bg-white' : 'bg-amber-600'"></span>
              <span>Por Datos</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'DATOS' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">
                {{ conteoMismosDatos }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarModo('UBICACION')"
              class="px-3 py-1.5 rounded-lg font-semibold transition-all cursor-pointer flex items-center gap-1.5"
              :class="filtroModo === 'UBICACION' ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-blue-700 hover:text-blue-900'"
              title="Colisiones de coordenadas en el radio establecido"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="filtroModo === 'UBICACION' ? 'bg-white' : 'bg-blue-600'"></span>
              <span>Por Geolocalizacion</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold" :class="filtroModo === 'UBICACION' ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-800'">
                {{ conteoMismaUbicacion }}
              </span>
            </button>
          </div>

          <!-- Metricas Resumen -->
          <div class="flex items-center gap-2 text-xs font-mono">
            <div class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg flex items-center gap-1.5 shadow-2xs">
              <span class="text-slate-400">Clientes Implicados:</span>
              <span class="font-bold text-slate-900 tabular-nums">{{ totalClientesImplicados }}</span>
            </div>
            <div class="px-2.5 py-1 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-1.5 shadow-2xs text-emerald-800">
              <span class="text-emerald-600">Venta MTD Grupo:</span>
              <span class="font-bold tabular-nums">Bs. {{ ventaMtdTotalAuditada }}</span>
            </div>
          </div>
        </div>

        <!-- Fila 2: Parametros de Auditoria (Canales, Radio, Buscador y Boton Auditar) -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-100 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector Multicanal -->
            <div class="relative inline-block text-left" ref="canalDropdownRef">
              <button
                type="button"
                @click="menuCanalAbierto = !menuCanalAbierto"
                class="h-8 px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-1.5 cursor-pointer font-medium text-slate-800 shadow-2xs transition-colors"
              >
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Canal:</span>
                <span class="font-bold text-sky-600">{{ etiquetaCanales }}</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div
                v-if="menuCanalAbierto"
                class="absolute top-full left-0 mt-1 w-56 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 space-y-1"
              >
                <div class="flex items-center justify-between pb-1 border-b border-slate-100 px-1 text-[10px]">
                  <span class="font-bold text-slate-400 uppercase">Canales</span>
                  <div class="flex items-center gap-1.5">
                    <button type="button" @click="seleccionarTodosCanales" class="text-sky-600 hover:underline cursor-pointer font-bold">Todos</button>
                    <span class="text-slate-300">|</span>
                    <button type="button" @click="limpiarCanales" class="text-slate-500 hover:underline cursor-pointer">Limpiar</button>
                  </div>
                </div>
                <div class="max-h-44 overflow-y-auto custom-scroll space-y-0.5">
                  <label
                    v-for="c in canalesDisponibles"
                    :key="c"
                    class="flex items-center gap-2 px-1.5 py-1 rounded hover:bg-slate-50 cursor-pointer text-xs"
                  >
                    <input
                      type="checkbox"
                      :value="c"
                      v-model="canalesSeleccionados"
                      class="rounded border-slate-300 text-sky-600 focus:ring-0 cursor-pointer"
                    />
                    <span class="text-slate-800 font-medium">{{ c }}</span>
                  </label>
                </div>
                <div class="pt-1 border-t border-slate-100 flex justify-end">
                  <button
                    type="button"
                    @click="menuCanalAbierto = false; ejecutarAuditoriaDuplicados()"
                    class="px-2 py-0.5 bg-slate-900 text-white rounded text-[10px] font-bold cursor-pointer"
                  >
                    Aplicar
                  </button>
                </div>
              </div>
            </div>

            <!-- Selector de Radio en Metros -->
            <div class="flex items-center h-8 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono shadow-2xs">
              <span class="text-slate-400 mr-1.5 font-sans text-[10px] uppercase font-bold tracking-wider">Radio:</span>
              <select
                v-model="radioMetros"
                @change="ejecutarAuditoriaDuplicados"
                class="bg-transparent font-bold text-slate-900 focus:outline-none cursor-pointer text-xs"
              >
                <option :value="5">5 metros</option>
                <option :value="10">10 metros</option>
                <option :value="20">20 metros (Estandar)</option>
                <option :value="50">50 metros</option>
                <option :value="100">100 metros</option>
              </select>
            </div>

            <!-- Boton Auditar Principal -->
            <button
              type="button"
              @click="ejecutarAuditoriaDuplicados"
              :disabled="cargando"
              class="h-8 px-3.5 bg-slate-900 hover:bg-black text-white rounded-lg text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <span>{{ cargando ? 'Rastreando Duplicados...' : 'Auditar Duplicados' }}</span>
            </button>
          </div>

          <!-- Buscador de Texto en Tiempo Real -->
          <div class="relative w-full sm:w-64">
            <input
              type="text"
              v-model="busquedaTexto"
              placeholder="Buscar en grupos por cliente o ID..."
              class="w-full h-8 pl-8 pr-3 bg-slate-50 focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 font-mono transition-all"
            />
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- ============================================================== -->
      <!-- SELECTOR DE VISTA MOVIL (< lg)                                  -->
      <!-- ============================================================== -->
      <div class="flex lg:hidden items-center justify-center p-1 bg-slate-100 rounded-xl border border-slate-200">
        <button
          type="button"
          @click="setVistaMovil('grupos')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
          :class="vistaMovil === 'grupos' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600'"
        >
          <span>Grupos ({{ clustersFiltrados.length }})</span>
        </button>
        <button
          type="button"
          @click="setVistaMovil('comparador')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
          :class="vistaMovil === 'comparador' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600'"
        >
          <span>Comparador & Mapa</span>
        </button>
        <button
          type="button"
          @click="setVistaMovil('propuestas')"
          class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
          :class="vistaMovil === 'propuestas' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-600'"
        >
          <span>Propuestas ({{ propuestasPendientes.length }})</span>
        </button>
      </div>

      <!-- ============================================================== -->
      <!-- ESTRUCTURA PRINCIPAL: COLUMNA GRUPOS + COMPARADOR & MAPA       -->
      <!-- ============================================================== -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 min-h-[580px]">
        
        <!-- Columna 1: Listado de Grupos Detectados (4 cols en desktop) -->
        <div
          class="lg:col-span-5 xl:col-span-4 bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col overflow-hidden max-h-[720px]"
          :class="vistaMovil === 'grupos' ? 'flex' : 'hidden lg:flex'"
        >
          <!-- Encabezado de la Columna -->
          <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-rose-500"></span>
              <span class="font-bold text-xs text-slate-800">Grupos Identificados</span>
            </div>
            <span class="font-mono text-[10px] text-slate-500 font-bold bg-white px-2 py-0.5 rounded border border-slate-200 shadow-2xs">
              {{ clustersFiltrados.length }} clusters
            </span>
          </div>

          <!-- Listado de Grupos con Scroll -->
          <div class="flex-1 overflow-y-auto custom-scroll p-2 space-y-2">
            <div v-if="cargando" class="text-center py-20 text-xs text-slate-400 animate-pulse font-mono">
              Analizando colisiones espaciales y coincidencia de datos...
            </div>
            <div v-else-if="clustersFiltrados.length === 0" class="text-center py-20 text-xs text-slate-400 p-4">
              <p class="font-bold text-slate-600 mb-1">No se detectaron duplicados</p>
              <p>No se encontraron clientes duplicados bajo el modo y los filtros seleccionados.</p>
            </div>

            <!-- Tarjeta de Grupo -->
            <div
              v-for="grupo in clustersFiltrados"
              :key="grupo.cluster_id"
              @click="seleccionarGrupo(grupo)"
              class="p-2.5 rounded-xl border transition-all cursor-pointer space-y-2"
              :class="[
                grupoSeleccionadoId === grupo.cluster_id
                  ? 'bg-sky-50/50 border-sky-400 ring-2 ring-sky-200 shadow-sm'
                  : 'bg-white hover:bg-slate-50/90 border-slate-200 shadow-2xs'
              ]"
            >
              <!-- Cabecera de la Tarjeta -->
              <div class="flex items-center justify-between gap-1 pb-1.5 border-b border-slate-100">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="font-mono font-bold text-xs text-slate-900 shrink-0">
                    #{{ grupo.cluster_id }}
                  </span>
                  <span
                    class="px-1.5 py-0.2 rounded text-[9.5px] font-bold font-mono uppercase truncate"
                    :class="getClasificacionBadge(grupo.clasificacion)"
                  >
                    {{ getClasificacionTexto(grupo.clasificacion) }}
                  </span>
                </div>
                <span
                  class="font-mono text-[10px] px-2 py-0.2 rounded font-bold shrink-0"
                  :class="grupo.distancia_minima_m <= radioMetros ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'"
                >
                  {{ formatearDistancia(grupo.distancia_minima_m) }}
                </span>
              </div>

              <!-- Criterios Compartidos -->
              <div v-if="grupo.criterios && grupo.criterios.length > 0" class="flex flex-wrap gap-1">
                <span
                  v-for="crit in grupo.criterios"
                  :key="crit.campo"
                  class="px-1.5 py-0.2 rounded text-[9.5px] font-mono bg-slate-100 text-slate-700 border border-slate-200 font-semibold"
                >
                  {{ crit.campo }}: <strong class="text-slate-900">{{ crit.valores.join(', ') }}</strong>
                </span>
              </div>

              <!-- Clientes del Grupo (Mini Comparativa) -->
              <div class="space-y-1 text-xs">
                <div
                  v-for="cli in grupo.clientes"
                  :key="cli.cliente_id"
                  class="p-1.5 rounded-lg border text-[11px] flex items-center justify-between gap-1"
                  :class="cli.es_master_sugerido ? 'bg-emerald-50/60 border-emerald-200' : 'bg-slate-50/60 border-slate-200/80'"
                >
                  <div class="min-w-0 flex-1 truncate">
                    <div class="flex items-center gap-1">
                      <span
                        v-if="cli.es_master_sugerido"
                        class="px-1 py-0.1 bg-emerald-600 text-white rounded text-[8px] font-mono font-bold shrink-0"
                      >
                        MASTER
                      </span>
                      <span class="font-bold text-slate-900 truncate">{{ cli.cliente }}</span>
                    </div>
                    <div class="text-[10px] font-mono text-slate-500 truncate mt-0.5">
                      ID {{ cli.cliente_id }} &bull; {{ cli.ruta }} ({{ cli.canal }})
                    </div>
                  </div>
                  <div class="text-right font-mono text-[10px] shrink-0">
                    <div class="font-bold text-emerald-700">Bs. {{ cli.venta_mtd }}</div>
                    <div class="text-slate-400 text-[9px]">{{ cli.dia }}</div>
                  </div>
                </div>
              </div>

              <!-- Venta Total y Alerta si tiene propuesta -->
              <div class="flex items-center justify-between pt-1 text-[10px] font-mono text-slate-500">
                <span>Venta MTD Grupo: <strong class="text-slate-900">Bs. {{ grupo.venta_mtd_total }}</strong></span>
                <span v-if="tienePropuestaPendiente(grupo)" class="px-1.5 py-0.2 bg-amber-50 text-amber-800 border border-amber-200 rounded font-bold">
                  Propuesta Creada
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Columna 2: Visor Comparativo Lado a Lado + Mapa Focalizado (8 cols en desktop) -->
        <div
          class="lg:col-span-7 xl:col-span-8 flex flex-col gap-3 min-h-[580px]"
          :class="vistaMovil === 'comparador' ? 'flex' : 'hidden lg:flex'"
        >
          <!-- 1. Mapa de Colision Espacial Siempre Presente (Sin destruirse por Vue) -->
          <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden flex flex-col shrink-0">
            <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between text-xs shrink-0">
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="setVistaMovil('grupos')"
                  class="lg:hidden text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-1 cursor-pointer"
                >
                  &larr; Grupos
                </button>
                <span class="font-bold text-slate-900 font-mono">
                  {{ grupoActivo ? `Colision Espacial: Grupo #${grupoActivo.cluster_id}` : 'Mapa de Colision Territorial' }}
                </span>
                <span
                  v-if="grupoActivo"
                  class="text-[10px] font-mono font-bold px-2 py-0.2 rounded bg-sky-50 text-sky-800 border border-sky-200"
                >
                  Distancia: {{ formatearDistancia(grupoActivo.distancia_minima_m) }}
                </span>
              </div>

              <div class="flex items-center gap-1.5 text-xs">
                <!-- Capa Calles / Satelite -->
                <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
                  <button
                    type="button"
                    @click="cambiarCapaMapa('openStreetMap')"
                    class="px-2 py-0.5 text-[10px] font-medium rounded transition-all cursor-pointer"
                    :class="capaActual === 'openStreetMap' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'"
                  >
                    Calles
                  </button>
                  <button
                    type="button"
                    @click="cambiarCapaMapa('satellite')"
                    class="px-2 py-0.5 text-[10px] font-medium rounded transition-all cursor-pointer"
                    :class="capaActual === 'satellite' ? 'bg-white text-slate-900 font-bold shadow-2xs' : 'text-slate-500'"
                  >
                    Satelite
                  </button>
                </div>

                <button
                  v-if="grupoActivo"
                  type="button"
                  @click="enfocarGrupoEnMapa(grupoActivo)"
                  class="h-6 px-2 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] font-bold text-slate-700 cursor-pointer shadow-2xs"
                  title="Ajustar vista para ver ambos clientes en conflicto"
                >
                  Recentrar (Ver ambos)
                </button>
              </div>
            </div>

            <!-- Contenedor del Mapa -->
            <div class="relative w-full h-52 sm:h-60 bg-slate-950">
              <div id="mapaDuplicados" class="absolute inset-0 w-full h-full z-10"></div>
              <div
                v-if="!grupoActivo"
                class="absolute inset-0 z-20 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center text-white text-xs font-medium p-4 text-center pointer-events-none"
              >
                <span>Selecciona un grupo para enfocar y analizar la colision en el mapa</span>
              </div>
            </div>
          </div>

          <!-- 2. Comparador Lado a Lado (Master vs Duplicado) -->
          <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs flex-1 flex flex-col overflow-hidden">
            <!-- Si no hay grupo activo, mostrar placeholder -->
            <div
              v-if="!grupoActivo"
              class="flex-1 flex flex-col items-center justify-center p-8 text-center"
            >
              <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
              </div>
              <h4 class="text-sm font-bold text-slate-800 mb-0.5">Inspeccion de Conflicto</h4>
              <p class="text-xs text-slate-500 max-w-sm">
                Selecciona un grupo para ver la comparativa detallada de historial de creacion, ventas y ruta.
              </p>
            </div>

            <!-- Si hay grupo activo, mostrar el comparador lado a lado -->
            <template v-else>
              <div class="px-4 py-2 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between shrink-0">
                <span class="font-bold text-xs text-slate-800 uppercase tracking-wider font-mono">
                  Comparativa de Datos, Creacion y Rendimiento
                </span>
                <span class="text-[11px] font-mono text-slate-500">
                  {{ grupoActivo.clientes.length }} clientes en conflicto
                </span>
              </div>

              <!-- Grid de Comparacion Lado a Lado -->
              <div class="flex-1 overflow-y-auto custom-scroll p-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div
                    v-for="(cli, idx) in grupoActivo.clientes"
                    :key="cli.cliente_id"
                    class="p-3 rounded-xl border flex flex-col justify-between space-y-2.5 transition-all"
                    :class="[
                      cli.es_master_sugerido ? 'bg-emerald-50/30 border-emerald-300 ring-1 ring-emerald-200' : 'bg-slate-50/50 border-slate-200',
                      clienteFocoId === cli.cliente_id ? 'ring-2 ring-sky-500 shadow-sm' : ''
                    ]"
                  >
                    <!-- Header Cliente -->
                    <div class="space-y-1 pb-2 border-b border-slate-200/80">
                      <div class="flex items-center justify-between">
                        <span
                          class="px-2 py-0.5 rounded text-[9.5px] font-mono font-bold tracking-wider uppercase"
                          :class="cli.es_master_sugerido ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-slate-200 text-slate-700'"
                        >
                          {{ cli.es_master_sugerido ? 'Master Sugerido (Conservar)' : `Duplicado #${idx + 1}` }}
                        </span>
                        <div class="flex items-center gap-1.5">
                          <span class="font-mono text-xs font-bold text-slate-500">ID: {{ cli.cliente_id }}</span>
                          <button
                            v-if="cli.latitud && cli.longitud"
                            type="button"
                            @click="enfocarClienteEnMapa(cli)"
                            title="Hacer zoom al cliente en el mapa"
                            class="px-2 py-0.5 rounded bg-white hover:bg-sky-50 text-sky-700 border border-sky-200 font-mono text-[10px] font-bold flex items-center gap-1 cursor-pointer transition-colors shadow-2xs"
                          >
                            <svg class="w-3 h-3 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                            <span>Zoom</span>
                          </button>
                        </div>
                      </div>

                      <h3 class="text-sm font-bold text-slate-900 leading-tight">{{ cli.cliente }}</h3>
                      <p class="text-xs text-slate-500 truncate">{{ cli.direccion || 'Sin direccion' }}</p>
                    </div>

                    <!-- Datos Cruce: Fecha de Creacion / Ingreso al Padron -->
                    <div class="p-2 rounded-lg bg-white border border-slate-200/90 text-xs font-mono space-y-1 shadow-2xs">
                      <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400 font-sans uppercase font-bold tracking-wider">Ingreso al Padron:</span>
                        <span
                          v-if="cli.es_master_sugerido"
                          class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-bold text-[9px]"
                        >
                          Padron Mas Antiguo
                        </span>
                        <span
                          v-else
                          class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold text-[9px]"
                        >
                          Ingreso Posterior
                        </span>
                      </div>
                      <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <span>{{ cli.fecha_ingreso ? formatearFecha(cli.fecha_ingreso) : 'Sin fecha registrada' }}</span>
                      </div>
                      <div v-if="cli.origen_ingreso" class="text-[9.5px] text-slate-500 font-sans truncate">
                        Origen: {{ cli.origen_ingreso }}
                      </div>
                    </div>

                    <!-- Metricas y Atributos Comerciales -->
                    <div class="space-y-1.5 text-xs font-mono">
                      <div class="flex items-center justify-between p-1.5 rounded bg-white border border-slate-200/80">
                        <span class="text-slate-500">Venta MTD:</span>
                        <span class="font-bold text-emerald-700 text-sm">Bs. {{ cli.venta_mtd }}</span>
                      </div>

                      <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                        <div class="p-1.5 rounded bg-white border border-slate-200/80">
                          <span class="text-slate-400 block text-[9.5px]">Ruta:</span>
                          <span class="font-bold text-slate-800">{{ cli.ruta }}</span>
                        </div>
                        <div class="p-1.5 rounded bg-white border border-slate-200/80">
                          <span class="text-slate-400 block text-[9.5px]">Canal:</span>
                          <span class="font-bold text-slate-800">{{ cli.canal }}</span>
                        </div>
                        <div class="p-1.5 rounded bg-white border border-slate-200/80">
                          <span class="text-slate-400 block text-[9.5px]">Dia:</span>
                          <span class="font-bold text-slate-800">{{ cli.dia }}</span>
                        </div>
                        <div class="p-1.5 rounded bg-white border border-slate-200/80">
                          <span class="text-slate-400 block text-[9.5px]">Vendedor:</span>
                          <span class="font-medium text-slate-800 truncate block">{{ cli.vendedor || 'S/N' }}</span>
                        </div>
                      </div>

                      <!-- Contacto & Documentos -->
                      <div class="p-1.5 rounded bg-white border border-slate-200/80 text-[10.5px] space-y-0.5">
                        <div v-if="cli.nit" class="truncate"><span class="text-slate-400">NIT:</span> {{ cli.nit }}</div>
                        <div v-if="cli.ci" class="truncate"><span class="text-slate-400">CI:</span> {{ cli.ci }}</div>
                        <div v-if="cli.telefono || cli.celular" class="truncate">
                          <span class="text-slate-400">Tel/Cel:</span> {{ cli.telefono || cli.celular }}
                        </div>
                      </div>
                    </div>

                    <!-- Boton Ver Fotografias -->
                    <button
                      type="button"
                      @click="abrirModalFotos(cli)"
                      class="w-full py-1.5 bg-white hover:bg-slate-100 text-slate-800 border border-slate-200 rounded-lg text-xs font-semibold cursor-pointer shadow-2xs transition-all flex items-center justify-center gap-1.5"
                    >
                      <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                      </svg>
                      <span>Ver Fotografias de Visitas</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Barra de Acciones de Resolucion Directa en 1 Clic -->
              <div class="p-3 border-t border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-2 shrink-0">
                <div class="text-xs text-slate-500 font-mono">
                  Sugerencia: <strong class="text-slate-800">Inactivar duplicado de menor venta/ingreso posterior</strong>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click="proponerInactivar(grupoActivo)"
                    :disabled="procesandoPropuesta"
                    class="px-3.5 py-2 bg-slate-900 hover:bg-black text-white rounded-xl text-xs font-bold transition-all shadow-md cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
                  >
                    <span>Conservar Master e Inactivar Duplicado</span>
                  </button>

                  <button
                    type="button"
                    @click="proponerFusion(grupoActivo)"
                    :disabled="procesandoPropuesta"
                    class="px-3 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition-all shadow-md cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
                  >
                    <span>Fusionar Cuentas</span>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>

      </div>

      <!-- ============================================================== -->
      <!-- BANDEJA INFERIOR: PROPUESTAS PENDIENTES DE RESOLUCION          -->
      <!-- ============================================================== -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div
          class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70 cursor-pointer select-none"
          @click="mostrarPanelPropuestas = !mostrarPanelPropuestas"
        >
          <div class="flex items-center gap-2">
            <span class="font-bold text-xs text-slate-800 uppercase tracking-wider font-mono">
              Bandeja de Propuestas Pendientes
            </span>
            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
              {{ propuestasPendientes.length }} pendientes
            </span>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-medium">
              {{ mostrarPanelPropuestas ? 'Ocultar tabla' : 'Ver tabla de propuestas' }}
            </span>
            <svg
              class="w-4 h-4 text-slate-400 transition-transform duration-200"
              :class="mostrarPanelPropuestas ? 'rotate-180' : ''"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>

        <div v-show="mostrarPanelPropuestas" class="overflow-x-auto max-h-72 custom-scroll">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="sticky top-0 bg-slate-100 text-slate-700 uppercase text-[9.5px] font-mono font-bold tracking-wider border-b border-slate-200 z-10 shadow-2xs">
              <tr>
                <th class="py-2.5 px-3 whitespace-nowrap">Cliente Origen</th>
                <th class="py-2.5 px-3 whitespace-nowrap">ID Origen</th>
                <th class="py-2.5 px-3 whitespace-nowrap">Cliente Vinculado</th>
                <th class="py-2.5 px-3 whitespace-nowrap">Ruta</th>
                <th class="py-2.5 px-3 whitespace-nowrap">Accion Propuesta</th>
                <th class="py-2.5 px-3 whitespace-nowrap">Observaciones</th>
                <th class="py-2.5 px-3 text-center whitespace-nowrap">Estado</th>
                <th class="py-2.5 px-3 text-right whitespace-nowrap">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="propuestasPendientes.length === 0">
                <td colspan="8" class="py-8 text-center text-slate-400 font-medium text-xs">
                  No hay propuestas pendientes de resolucion de duplicados.
                </td>
              </tr>
              <tr
                v-for="p in propuestasPendientes"
                :key="p.id"
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="py-2 px-3 font-bold text-slate-900">{{ p.cliente_nombre }}</td>
                <td class="py-2 px-3 font-mono text-slate-600">{{ p.cliente_id_origen }}</td>
                <td class="py-2 px-3 font-mono text-sky-700 font-bold">{{ p.cliente_id_vinculado || '-' }}</td>
                <td class="py-2 px-3 font-mono">{{ p.ruta_actual }}</td>
                <td class="py-2 px-3 font-mono">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200">
                    {{ p.accion_propuesta }}
                  </span>
                </td>
                <td class="py-2 px-3 text-slate-500 truncate max-w-xs">{{ p.observaciones }}</td>
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
                    class="px-2 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded text-[10px] font-bold cursor-pointer"
                  >
                    Revertir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Fotos de Visitas -->
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
import { mapsConfig } from '@/Config/maps'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import AuditoriaSaneamientoSubTabs from '@/Components/AuditoriaSaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const cargando = ref(false)
const procesandoPropuesta = ref(false)
const vistaMovil = ref('grupos') // 'grupos' | 'comparador' | 'propuestas'
const mostrarPanelPropuestas = ref(false)

// Selector de Canales (Multiseleccion)
const canalDropdownRef = ref(null)
const menuCanalAbierto = ref(false)
const canalesDisponibles = computed(() => props.catalogo.canales || [])
const canalesSeleccionados = ref(
  props.catalogo.canales?.includes('TDB') ? ['TDB'] : [...(props.catalogo.canales || [])]
)

// Selector de Modo: 'TODOS' | 'CRITICOS' | 'DATOS' | 'UBICACION'
const filtroModo = ref('TODOS')

// Selector de Radio en Metros
const radioMetros = ref(20)

// Buscador de texto en memoria
const busquedaTexto = ref('')

// Datos
const clusters = ref([])
const propuestasPendientes = ref([])
const grupoSeleccionadoId = ref(null)
const clienteFocoId = ref(null)

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

const capaActual = ref(mapsConfig.defaultTileLayer || 'openStreetMap')
let map = null
let currentTileLayer = null
let collisionLayerGroup = null

const etiquetaCanales = computed(() => {
  const total = canalesDisponibles.value.length
  const sel = canalesSeleccionados.value.length
  if (sel === 0) return 'Ninguno'
  if (sel === total) return 'Todos'
  if (sel === 1) return canalesSeleccionados.value[0]
  return `${sel} canales`
})

function seleccionarTodosCanales() {
  canalesSeleccionados.value = [...canalesDisponibles.value]
}

function limpiarCanales() {
  canalesSeleccionados.value = []
}

function cambiarModo(modo) {
  filtroModo.value = modo
  ejecutarAuditoriaDuplicados()
}

function setVistaMovil(v) {
  vistaMovil.value = v
  if (v === 'comparador') {
    nextTick(() => {
      if (map) {
        map.invalidateSize()
        if (grupoActivo.value) enfocarGrupoEnMapa(grupoActivo.value)
      } else {
        initMap()
        if (grupoActivo.value) enfocarGrupoEnMapa(grupoActivo.value)
      }
    })
  }
}

// Conteo dinamico segun la auditoria
const totalGruposTodos = computed(() => clusters.value.length)
const conteoMismaUbicacionYDatos = computed(() => clusters.value.filter(c => c.clasificacion === 'MISMA_UBICACION_Y_DATOS').length)
const conteoMismosDatos = computed(() => clusters.value.filter(c => c.clasificacion === 'MISMOS_DATOS').length)
const conteoMismaUbicacion = computed(() => clusters.value.filter(c => c.clasificacion === 'MISMA_UBICACION').length)

const totalClientesImplicados = computed(() => {
  return clustersFiltrados.value.reduce((acc, c) => acc + (c.cantidad_clientes || c.clientes?.length || 0), 0)
})

const ventaMtdTotalAuditada = computed(() => {
  const total = clustersFiltrados.value.reduce((acc, c) => acc + (c.venta_mtd_total || 0), 0)
  return total.toFixed(2)
})

// Filtrado de clusters en memoria (por modo y texto de busqueda)
const clustersFiltrados = computed(() => {
  let list = clusters.value

  // Filtrado de Modo
  if (filtroModo.value === 'CRITICOS') {
    list = list.filter(c => c.clasificacion === 'MISMA_UBICACION_Y_DATOS')
  } else if (filtroModo.value === 'DATOS') {
    list = list.filter(c => c.tiene_mismos_datos)
  } else if (filtroModo.value === 'UBICACION') {
    list = list.filter(c => c.tiene_misma_ubicacion)
  }

  // Filtrado por texto
  if (busquedaTexto.value.trim()) {
    const q = busquedaTexto.value.toLowerCase().trim()
    list = list.filter(g => {
      return g.clientes.some(c =>
        (c.cliente && c.cliente.toLowerCase().includes(q)) ||
        (c.cliente_id && String(c.cliente_id).includes(q)) ||
        (c.ruta && c.ruta.toLowerCase().includes(q))
      )
    })
  }

  return list
})

const grupoActivo = computed(() => {
  if (!grupoSeleccionadoId.value) return null
  return clusters.value.find(c => c.cluster_id === grupoSeleccionadoId.value) || null
})

function seleccionarGrupo(grupo) {
  grupoSeleccionadoId.value = grupo.cluster_id
  clienteFocoId.value = null

  // En movil cambiar automaticamente a la vista de comparador
  if (typeof window !== 'undefined' && window.innerWidth < 1024) {
    vistaMovil.value = 'comparador'
  }

  nextTick(() => {
    if (!map) {
      initMap()
    }
    if (map) {
      map.invalidateSize()
      enfocarGrupoEnMapa(grupo)
    }
  })
}

function getClasificacionBadge(clasif) {
  if (clasif === 'MISMA_UBICACION_Y_DATOS') return 'bg-purple-100 text-purple-800 border border-purple-200'
  if (clasif === 'MISMOS_DATOS') return 'bg-amber-100 text-amber-800 border border-amber-200'
  return 'bg-blue-100 text-blue-800 border border-blue-200'
}

function getClasificacionTexto(clasif) {
  if (clasif === 'MISMA_UBICACION_Y_DATOS') return 'Critico: Ubic & Nombre'
  if (clasif === 'MISMOS_DATOS') return 'Mismos Datos'
  return 'Misma Ubicacion'
}

function formatearDistancia(metros) {
  if (metros === undefined || metros === null) return '-'
  if (metros < 1000) return `${Math.round(metros)} m`
  return `${(metros / 1000).toFixed(2)} km`
}

function formatearFecha(f) {
  if (!f) return '-'
  try {
    const d = new Date(f)
    if (isNaN(d.getTime())) return String(f).substring(0, 10)
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return String(f)
  }
}

function tienePropuestaPendiente(grupo) {
  return grupo.clientes?.some(c => c.tiene_propuesta_pendiente)
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}

// ==========================================
// OPERACIONES MAPA LEAFLET
// ==========================================
function cambiarCapaMapa(nombreCapa) {
  if (!map || !mapsConfig.tileLayers[nombreCapa]) return
  capaActual.value = nombreCapa

  if (currentTileLayer) {
    map.removeLayer(currentTileLayer)
  }

  const layerConf = mapsConfig.tileLayers[nombreCapa]
  currentTileLayer = L.tileLayer(layerConf.url, {
    maxZoom: layerConf.maxZoom || 19,
    subdomains: layerConf.subdomains || 'abc',
    attribution: layerConf.attribution,
  }).addTo(map)
}

function initMap() {
  const container = document.getElementById('mapaDuplicados')
  if (!container) return

  if (map) {
    map.remove()
    map = null
  }

  const defaultLoc = mapsConfig.defaultLocation

  map = L.map('mapaDuplicados', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom)

  L.control.zoom({ position: 'topright' }).addTo(map)
  cambiarCapaMapa(capaActual.value)

  collisionLayerGroup = L.featureGroup().addTo(map)

  setTimeout(() => {
    if (map) map.invalidateSize()
  }, 100)
}

function enfocarClienteEnMapa(cli) {
  if (!cli || !cli.latitud || !cli.longitud) return
  clienteFocoId.value = cli.cliente_id
  if (!map) initMap()
  if (map) {
    map.flyTo([Number(cli.latitud), Number(cli.longitud)], 19, {
      animate: true,
      duration: 0.8,
    })
  }
}

function enfocarGrupoEnMapa(grupo) {
  if (!grupo || !grupo.clientes || grupo.clientes.length === 0) return

  clienteFocoId.value = null

  if (!map) {
    initMap()
  }
  if (!map) return

  map.invalidateSize()

  if (!collisionLayerGroup) {
    collisionLayerGroup = L.featureGroup().addTo(map)
  } else {
    collisionLayerGroup.clearLayers()
  }

  const latLngs = []

  grupo.clientes.forEach((cli) => {
    if (cli.latitud && cli.longitud) {
      const latlng = [Number(cli.latitud), Number(cli.longitud)]
      latLngs.push(latlng)

      const markerColor = cli.es_master_sugerido ? '#059669' : '#DC2626'

      const marker = L.circleMarker(latlng, {
        radius: cli.es_master_sugerido ? 10 : 9,
        fillColor: markerColor,
        color: '#FFFFFF',
        weight: 2.5,
        fillOpacity: 1,
      }).addTo(collisionLayerGroup)

      // Identificador permanente sin popups intrusivos
      marker.bindTooltip(
        `<span style="font-weight: 700; font-family: monospace; font-size: 10px;">${cli.es_master_sugerido ? 'MASTER' : 'DUP'} #${cli.cliente_id}</span>`,
        {
          permanent: true,
          direction: 'top',
          offset: [0, -10],
          className: 'font-mono text-[10px] font-bold px-1.5 py-0.5 rounded shadow-xs bg-slate-900 text-white border-0',
        }
      )

      // Clic directo en el cliente: hace zoom y muestra su ubicacion exacta
      marker.on('click', () => {
        enfocarClienteEnMapa(cli)
      })
    }
  })

  // Si hay al menos 2 puntos, trazar linea conectora de colision con distancia en metros
  if (latLngs.length >= 2) {
    L.polyline(latLngs, {
      color: '#DC2626',
      weight: 2.5,
      dashArray: '5, 5',
    }).addTo(collisionLayerGroup)

    const midLat = (latLngs[0][0] + latLngs[1][0]) / 2
    const midLng = (latLngs[0][1] + latLngs[1][1]) / 2

    L.tooltip({ permanent: true, direction: 'center', className: 'font-mono text-xs font-bold text-slate-800' })
      .setLatLng([midLat, midLng])
      .setContent(`${formatearDistancia(grupo.distancia_minima_m)}`)
      .addTo(collisionLayerGroup)

    // Circulo de tolerancia de radio alrededor del primer punto
    L.circle(latLngs[0], {
      radius: radioMetros.value,
      color: '#0284C7',
      fillColor: '#0284C7',
      fillOpacity: 0.08,
      weight: 1.5,
      dashArray: '4, 4',
    }).addTo(collisionLayerGroup)

    const bounds = collisionLayerGroup.getBounds()
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 18 })
    }
  } else if (latLngs.length === 1) {
    map.flyTo(latLngs[0], 17)
  }
}

// ==========================================
// PETICIONES AL BACKEND
// ==========================================
async function ejecutarAuditoriaDuplicados() {
  cargando.value = true
  try {
    const res = await axios.post(route('supervisor.saneamiento.duplicados.auditar'), {
      canales: canalesSeleccionados.value,
      radio_metros: radioMetros.value,
      tipo_grupo: filtroModo.value,
    })

    clusters.value = res.data.clusters || []

    // Seleccionar automaticamente el primer grupo si hay resultados
    if (clusters.value.length > 0) {
      seleccionarGrupo(clusters.value[0])
    } else {
      grupoSeleccionadoId.value = null
      if (collisionLayerGroup) collisionLayerGroup.clearLayers()
    }
  } catch (error) {
    console.error('Error al auditar duplicados:', error)
  } finally {
    cargando.value = false
  }
}

async function cargarPropuestasPendientes() {
  try {
    const res = await axios.get(route('supervisor.saneamiento.duplicados.propuestas'))
    propuestasPendientes.value = res.data || []
  } catch (error) {
    console.error('Error al cargar propuestas pendientes:', error)
  }
}

async function proponerInactivar(grupo) {
  if (!grupo.clientes || grupo.clientes.length < 2) return

  const ordenados = [...grupo.clientes].sort((a, b) => a.venta_mtd - b.venta_mtd)
  const menor = ordenados[0]
  const mayor = ordenados[ordenados.length - 1]

  procesandoPropuesta.value = true
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: menor.cliente_id,
      cliente_id_vinculado: mayor.cliente_id,
      cliente_nombre: menor.cliente,
      ruta_actual: menor.ruta,
      dia_actual: menor.dia,
      accion_propuesta: 'INACTIVAR_DUPLICADO',
      distancia_metros: grupo.distancia_minima_m,
      observaciones: `Cliente ID #${menor.cliente_id} duplicado de ID #${mayor.cliente_id} (menor venta MTD: Bs. ${menor.venta_mtd} vs Bs. ${mayor.venta_mtd})`,
    })

    menor.tiene_propuesta_pendiente = true
    await cargarPropuestasPendientes()
    mostrarPanelPropuestas.value = true
  } catch (err) {
    console.error('Error al crear propuesta de inactivacion:', err)
  } finally {
    procesandoPropuesta.value = false
  }
}

async function proponerFusion(grupo) {
  if (!grupo.clientes || grupo.clientes.length < 2) return
  const c1 = grupo.clientes[0]
  const c2 = grupo.clientes[1]

  procesandoPropuesta.value = true
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.crear'), {
      cliente_id_origen: c1.cliente_id,
      cliente_id_vinculado: c2.cliente_id,
      cliente_nombre: c1.cliente,
      ruta_actual: c1.ruta,
      dia_actual: c1.dia,
      accion_propuesta: 'FUSIONAR_CLIENTE',
      distancia_metros: grupo.distancia_minima_m,
      observaciones: `Fusion de cuentas de cliente ID #${c1.cliente_id} y ID #${c2.cliente_id}`,
    })

    c1.tiene_propuesta_pendiente = true
    await cargarPropuestasPendientes()
    mostrarPanelPropuestas.value = true
  } catch (err) {
    console.error('Error al crear propuesta de fusion:', err)
  } finally {
    procesandoPropuesta.value = false
  }
}

async function cambiarEstadoPropuesta(id, nuevoEstado) {
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.cambiar-estado', id), {
      estado: nuevoEstado,
    })
    await cargarPropuestasPendientes()
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error('Error al cambiar estado de propuesta:', err)
  }
}

async function revertirPropuesta(id) {
  try {
    await axios.post(route('supervisor.saneamiento.duplicados.propuestas.revertir', id))
    await cargarPropuestasPendientes()
    await ejecutarAuditoriaDuplicados()
  } catch (err) {
    console.error('Error al revertir propuesta:', err)
  }
}

onMounted(async () => {
  await nextTick()
  initMap()
  await ejecutarAuditoriaDuplicados()
  await cargarPropuestasPendientes()
})

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
})
</script>
