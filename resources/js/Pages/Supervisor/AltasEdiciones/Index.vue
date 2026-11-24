<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-[#F5F5F7]">
      
      <!-- Barra de Submódulos: Nuevas Altas vs Modificaciones -->
      <div class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 py-2 shrink-0">
        <AltasEdicionesSubTabs :active-tab="submoduloActivo" />
      </div>

      <!-- Barra Superior de Control Apple Pro -->
      <header class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop para menús desplegables -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Filtros Visibles y Directos: Canal, Ruta y Estado (Multiselección) -->
        <div class="flex flex-wrap items-center gap-1.5 py-0.5 shrink-0">
          <!-- Filtro 1: Canal -->
          <button
            type="button"
            @click="toggleMenu('canales', $event)"
            class="flex items-center gap-1.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] px-2.5 h-7 text-xs shadow-2xs hover:border-[#86868B] transition-colors shrink-0 cursor-pointer select-none"
            :class="filtroCanales.length !== props.canales.length ? 'border-[#0071E3] bg-[#0071E3]/[0.04]' : ''"
            title="Filtrar canales (seleccionar uno, varios o todos)"
          >
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <span class="font-bold text-[#0071E3] text-xs">{{ labelCanales }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro 2: Ruta -->
          <button
            type="button"
            @click="toggleMenu('rutas', $event)"
            class="flex items-center gap-1.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] px-2.5 h-7 text-xs shadow-2xs hover:border-[#86868B] transition-colors shrink-0 cursor-pointer select-none max-w-[240px]"
            :class="filtroRutas.length !== rutasDisponibles.length ? 'border-[#0071E3] bg-[#0071E3]/[0.04]' : ''"
            title="Filtrar rutas (seleccionar una, varias o todas)"
          >
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
            <span class="font-semibold text-[#1D1D1F] text-xs truncate">{{ labelRutas }}</span>
            <svg class="w-3 h-3 text-[#86868B] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Filtro 3: Estado de Revisión -->
          <button
            type="button"
            @click="toggleMenu('estados', $event)"
            class="flex items-center gap-1.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] px-2.5 h-7 text-xs shadow-2xs hover:border-[#86868B] transition-colors shrink-0 cursor-pointer select-none"
            :class="filtroEstados.length !== props.estados_revision.length ? 'border-[#0071E3] bg-[#0071E3]/[0.04]' : ''"
            title="Filtrar estados de revisión (seleccionar uno, varios o todos)"
          >
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Estado:</span>
            <span class="font-semibold text-xs" :class="filtroEstados.length === 1 ? (filtroEstados[0] === 'APROBADO' ? 'text-[#248A3D] font-bold' : (filtroEstados[0] === 'RECHAZADO' ? 'text-[#C9342C] font-bold' : (filtroEstados[0] === 'PENDIENTE' ? 'text-[#B25E00] font-bold' : 'text-[#1D1D1F]'))) : 'text-[#1D1D1F]'">
              {{ labelEstados }}
            </span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <!-- Reset Rápido si hay filtros aplicados -->
          <button
            v-if="hayFiltrosModificados"
            type="button"
            @click="limpiarFiltrosRapidos"
            class="h-7 px-2 text-[11px] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#E5E5EA] rounded-[6px] transition-colors font-semibold cursor-pointer shrink-0"
            title="Restablecer filtros a valores por defecto"
          >
            Limpiar filtros
          </button>
        </div>

        <!-- Indicador de Carga, Control Móvil, Descarga Excel y Badges -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Consultando solicitudes...
          </span>

          <!-- Botones de Descarga Excel según Submódulo Activo -->
          <template v-if="submoduloActivo === 'modificaciones'">
            <!-- Botón 1: Descargar Comparativo -->
            <button
              type="button"
              @click="exportarComparativoExcel"
              :disabled="cargando || conteoEdiciones === 0"
              class="h-7 px-2.5 bg-[#248A3D] hover:bg-[#1E7433] active:bg-[#185E29] disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none shrink-0"
              :title="conteoEdiciones > 0 ? `Descargar comparativo de ${conteoEdiciones} edición(es) filtrada(s)` : 'No hay ediciones con los filtros actuales'"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">Descargar Comparativo</span>
              <span class="sm:hidden">Comparativo</span>
              <span v-if="conteoEdiciones > 0" class="px-1.5 py-0.2 bg-black/20 rounded text-[10px] font-mono font-bold">
                {{ conteoEdiciones }}
              </span>
            </button>

            <!-- Botón 2: Descargar Padrón Actualizado (Formato ERP 36 Columnas) -->
            <button
              type="button"
              @click="exportarEdicionesExcel"
              :disabled="cargando || conteoEdiciones === 0"
              class="h-7 px-2.5 bg-[#0071E3] hover:bg-[#0077ED] active:bg-[#005BB5] disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none shrink-0"
              :title="conteoEdiciones > 0 ? `Descargar ${conteoEdiciones} edición(es) filtrada(s) en formato oficial ERP (36 columnas .xlsx)` : 'No hay ediciones con los filtros actuales'"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">Descargar Padrón Actualizado (ERP)</span>
              <span class="sm:hidden">Padrón ERP</span>
              <span v-if="conteoEdiciones > 0" class="px-1.5 py-0.2 bg-black/20 rounded text-[10px] font-mono font-bold">
                {{ conteoEdiciones }}
              </span>
            </button>

            <!-- Botón 3: Cambio Masivo de Estado -->
            <button
              type="button"
              @click="abrirModalCambioMasivo"
              :disabled="cargando || procesandoCambioMasivo || registrosFiltrados.length === 0"
              class="h-7 px-2.5 bg-[#5856D6] hover:bg-[#4745C4] active:bg-[#3A38A8] disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none shrink-0"
              :title="registrosFiltrados.length > 0 ? `Cambiar masivamente el estado de ${registrosFiltrados.length} modificaciones filtradas` : 'No hay registros filtrados'"
            >
              <svg v-if="procesandoCambioMasivo" class="w-3.5 h-3.5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
              <span class="hidden sm:inline">Cambio Masivo</span>
              <span class="sm:hidden">Masivo</span>
              <span v-if="registrosFiltrados.length > 0" class="px-1.5 py-0.2 bg-black/20 rounded text-[10px] font-mono font-bold">
                {{ registrosFiltrados.length }}
              </span>
            </button>
          </template>

          <template v-else>
            <!-- Botón Descargar Altas -->
            <button
              type="button"
              @click="exportarAltasExcel"
              :disabled="cargando || registrosFiltrados.length === 0"
              class="h-7 px-2.5 bg-[#248A3D] hover:bg-[#1E7433] active:bg-[#185E29] disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none shrink-0"
              title="Descargar altas registradas en Excel"
            >
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span class="hidden sm:inline">Descargar Altas</span>
              <span class="sm:hidden">Altas</span>
              <span v-if="registrosFiltrados.length > 0" class="px-1.5 py-0.2 bg-black/20 rounded text-[10px] font-mono font-bold">
                {{ registrosFiltrados.length }}
              </span>
            </button>

            <!-- Botón Cambio Masivo de Estado para Nuevas Altas -->
            <button
              type="button"
              @click="abrirModalCambioMasivo"
              :disabled="cargando || procesandoCambioMasivo || registrosFiltrados.length === 0"
              class="h-7 px-2.5 bg-[#5856D6] hover:bg-[#4745C4] active:bg-[#3A38A8] disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none shrink-0"
              :title="registrosFiltrados.length > 0 ? `Cambiar masivamente el estado de ${registrosFiltrados.length} altas filtradas` : 'No hay registros filtrados'"
            >
              <svg v-if="procesandoCambioMasivo" class="w-3.5 h-3.5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
              <span class="hidden sm:inline">Cambio Masivo</span>
              <span class="sm:hidden">Masivo</span>
              <span v-if="registrosFiltrados.length > 0" class="px-1.5 py-0.2 bg-black/20 rounded text-[10px] font-mono font-bold">
                {{ registrosFiltrados.length }}
              </span>
            </button>
          </template>

          <!-- Selector de Modo de Pantalla Móvil -->
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
              {{ registroSeleccionado ? 'Ficha' : `Lista (${registrosFiltrados.length})` }}
            </button>
          </div>

          <!-- Selector de Modo de Pantalla Desktop / Laptop -->
          <div class="hidden md:flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="setVistaPantalla('split')"
              class="px-2 py-0.5 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer select-none"
              :class="vistaPantalla === 'split' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="Vista dividida (Ficha y Mapa)"
            >
              Dividido
            </button>
            <button
              type="button"
              @click="setVistaPantalla('panel')"
              class="px-2 py-0.5 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer select-none"
              :class="vistaPantalla === 'panel' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="Maximizar Ficha y Lista"
            >
              {{ registroSeleccionado ? 'Ficha' : 'Lista' }}
            </button>
            <button
              type="button"
              @click="setVistaPantalla('mapa')"
              class="px-2 py-0.5 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer select-none"
              :class="vistaPantalla === 'mapa' ? 'bg-white text-[#1D1D1F] shadow-2xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
              title="Maximizar Mapa"
            >
              Mapa
            </button>
          </div>

          <div v-if="submoduloActivo === 'altas'" class="hidden lg:flex px-2 py-0.5 bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] rounded-[6px] items-center gap-1 font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
            <span>Altas: {{ kpis.altas }}</span>
          </div>

          <div v-else class="hidden lg:flex px-2 py-0.5 bg-[#EFF6FF] text-[#0071E3] border border-[#E5E5EA] rounded-[6px] items-center gap-1 font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            <span>Modificaciones: {{ kpis.ediciones }}</span>
          </div>

          <div class="hidden sm:flex px-2 py-0.5 bg-[#FFF5E5] text-[#B25E00] border border-[#E5E5EA] rounded-[6px] items-center gap-1 font-semibold">
            <span>Pendientes:</span>
            <span class="font-bold font-mono">{{ kpis.pendientes }}</span>
          </div>

          <div class="hidden sm:flex px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="text-[#86868B]">Total:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.total }}</span>
          </div>
        </div>
      </header>

      <!-- Dropdowns con Teleport -->
      <Teleport to="body">
        <!-- Tipos de Registro -->
        <div
          v-if="menuAbierto === 'tipos'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-48 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="filtroTipos = [...props.tipos_registro]; cargarDatos()" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="filtroTipos = []; cargarDatos()" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="space-y-1">
            <label
              v-for="t in tipos_registro"
              :key="t"
              class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
            >
              <input
                type="checkbox"
                :value="t"
                v-model="filtroTipos"
                @change="cargarDatos"
                class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
              />
              <span class="font-medium text-[#1D1D1F]">{{ t }}</span>
            </label>
          </div>
        </div>

        <!-- Estados de Revisión -->
        <div
          v-if="menuAbierto === 'estados'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-48 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-[#E5E5EA]">
            <button type="button" @click="seleccionarTodosEstados" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="limpiarEstados" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="space-y-1">
            <label
              v-for="e in props.estados_revision"
              :key="e"
              class="flex items-center gap-2 px-1.5 py-1 hover:bg-[#F2F2F7] rounded-[4px] cursor-pointer select-none"
            >
              <input
                type="checkbox"
                :value="e"
                v-model="filtroEstados"
                @change="cargarDatos"
                class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
              />
              <span class="font-medium text-[#1D1D1F]">{{ e }}</span>
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
              v-for="c in props.canales"
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
            <div v-if="rutasFiltradasEnDropdown.length === 0" class="py-2 text-center text-[11px] text-[#86868B]">
              No hay rutas disponibles
            </div>
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
          class="bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden transition-all duration-200"
          :class="[
            vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex',
            vistaPantalla === 'mapa' ? 'md:hidden!' : '',
            vistaPantalla === 'panel' ? 'w-full md:flex-1' : 'w-full md:w-[480px] lg:w-[520px] xl:w-[560px] 2xl:w-[600px]'
          ]"
        >
          <!-- MODO 1: LISTADO GENERAL -->
          <template v-if="!registroSeleccionado">
            <!-- Buscador Rápido y Selector de Vista en Panel -->
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] shrink-0 space-y-1.5">
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Buscar cliente, código, ruta o dirección..."
                class="w-full h-7 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#86868B]"
              />

              <!-- Selector de Modo de Listado: Individual vs Repetidos -->
              <div class="flex items-center justify-between gap-1 text-[11px]">
                <div class="flex items-center bg-[#E5E5EA]/70 p-0.5 rounded-[6px] border border-[#E5E5EA]">
                  <button
                    type="button"
                    @click="modoVistaLista = 'individual'"
                    class="px-2 py-0.5 rounded-[4px] font-medium transition-all cursor-pointer"
                    :class="modoVistaLista === 'individual'
                      ? 'bg-white text-[#1D1D1F] font-bold shadow-2xs'
                      : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                  >
                    Individual ({{ registrosFiltrados.length }})
                  </button>
                  <button
                    type="button"
                    @click="modoVistaLista = 'agrupado'"
                    class="px-2 py-0.5 rounded-[4px] font-medium transition-all flex items-center gap-1 cursor-pointer"
                    :class="modoVistaLista === 'agrupado'
                      ? 'bg-[#D97706] text-white font-bold shadow-2xs'
                      : 'text-[#92400E] hover:text-[#D97706] font-semibold'"
                  >
                    <span>Repetidos</span>
                    <span
                      class="px-1.5 py-0.2 rounded-full text-[9.5px] font-mono font-bold"
                      :class="modoVistaLista === 'agrupado' ? 'bg-black/25 text-white' : 'bg-[#FEF3C7] text-[#B45309] border border-[#F59E0B]/40'"
                    >
                      {{ clientesConRepetidosCount }}
                    </span>
                  </button>
                </div>

                <button
                  v-if="clientesConRepetidosCount > 0"
                  type="button"
                  @click="abrirComparativoPrimerRepetido"
                  class="text-[10.5px] font-bold text-[#D97706] hover:text-[#B45309] flex items-center gap-1 cursor-pointer"
                  title="Abrir la matriz comparativa de repetidos"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  <span>Matriz comparativa &rarr;</span>
                </button>
              </div>
            </div>

            <!-- VISTA 1: LISTADO INDIVIDUAL -->
            <div v-if="modoVistaLista === 'individual'" class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Consultando solicitudes de saneamiento...
              </div>

              <div v-else-if="registrosFiltrados.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No se encontraron solicitudes con los filtros aplicados.
              </div>

              <div
                v-for="r in registrosFiltrados"
                :key="r.id"
                @click="abrirExpediente(r)"
                class="p-3 transition-colors cursor-pointer border-b border-[#E5E5EA]/70 relative"
                :class="[
                  registroActivoId === r.id
                    ? (r.tiene_doble_modificacion && r.estado_revision === 'PENDIENTE'
                        ? 'bg-[#FEF3C7]/60 border-l-[5px] border-l-[#D97706]'
                        : 'bg-[#F2F2F7]')
                    : (r.tiene_doble_modificacion && r.estado_revision === 'PENDIENTE'
                        ? 'bg-[#FFFBEB] hover:bg-[#FEF3C7]/40 border-l-[5px] border-l-[#D97706]'
                        : 'bg-white hover:bg-[#FBFBFD]')
                ]"
              >
                <div class="flex items-start justify-between gap-1.5">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <!-- Badge Tipo de Registro -->
                      <span
                        class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white shrink-0"
                        :class="getTipoColor(r.tipo_registro)"
                      >
                        {{ r.tipo_registro }}
                      </span>

                      <!-- ID de Solicitud y Código Cliente -->
                      <span class="text-[10px] font-mono font-bold text-[#86868B]">
                        #{{ r.id }}
                        <span v-if="r.cliente_id" class="text-[#0071E3] font-normal">• Cód: {{ r.cliente_id }}</span>
                        <span v-else class="text-[#248A3D] font-normal">• Nueva</span>
                      </span>

                      <!-- Badge Doble Modificación si corresponde -->
                      <span
                        v-if="r.tiene_doble_modificacion && r.estado_revision === 'PENDIENTE'"
                        class="text-[9px] font-bold px-1.5 py-0.2 rounded-full bg-[#FEF3C7] text-[#92400E] border border-[#F59E0B]/40 flex items-center gap-1 shrink-0"
                        :title="`Este cliente tiene ${r.total_pendientes_cliente} modificaciones pendientes registradas`"
                      >
                        <svg class="w-2.5 h-2.5 text-[#D97706]" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Doble Modif. ({{ r.total_pendientes_cliente }})</span>
                      </span>

                      <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1 w-full mt-0.5">{{ r.cliente }}</span>
                    </div>

                    <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ r.direccion || 'Sin dirección registrada' }}</p>

                    <!-- Vendedor & Negocio Info -->
                    <div class="flex items-center gap-2 mt-1 text-[10px] text-[#86868B] flex-wrap">
                      <span v-if="r.tipo_negocio" class="px-1 py-0.2 bg-[#F2F2F7] rounded text-[#1D1D1F] truncate max-w-[120px]">
                        {{ r.tipo_negocio }}
                      </span>
                      <span v-if="r.vendedor" class="truncate max-w-[150px] text-[#6E6E73]">
                        {{ r.vendedor }}
                      </span>
                    </div>
                  </div>

                  <!-- Estado de Revisión -->
                  <div class="text-right shrink-0">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded border inline-block"
                      :class="getEstadoRevisionClass(r.estado_revision)"
                    >
                      {{ r.estado_revision }}
                    </span>
                  </div>
                </div>

                <!-- Barra Inferior de la Tarjeta con Badges de Datos -->
                <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-[#E5E5EA]/70 text-[10px] font-mono text-[#86868B]">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: getCanalColor(r.canal) }"></span>
                    <span class="text-[#1D1D1F] font-medium">{{ r.ruta }}</span>
                    <span>•</span>
                    <span>{{ r.fecha_registro }}</span>
                    <span v-if="r.distancia_desplazamiento_m" class="text-[#0071E3] font-bold">
                      Desv: {{ r.distancia_desplazamiento_m }}m
                    </span>
                    <span v-if="r.nit" class="text-[#248A3D] font-semibold" title="NIT registrado">NIT</span>
                    <span v-if="r.celular || r.telefono" class="text-[#0071E3] font-semibold" title="Teléfono registrado">TEL</span>
                    <span v-if="r.photo_url" class="text-[#AF52DE] font-semibold" title="Tiene fotografía">FOTO</span>
                  </div>

                  <span class="text-[#0071E3] font-sans font-medium text-[11px] shrink-0">Ver ficha &rarr;</span>
                </div>
              </div>
            </div>

            <!-- VISTA 2: LISTADO AGRUPADO (CLIENTES CON REPETIDOS) -->
            <div v-else class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA] bg-[#F5F5F7] p-2 space-y-2.5">
              <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Consultando clientes repetidos...
              </div>

              <div v-else-if="gruposRepetidos.length === 0" class="p-8 text-center bg-white rounded-[8px] border border-[#E5E5EA]">
                <div class="w-10 h-10 mx-auto rounded-full bg-[#EBF9EF] text-[#248A3D] flex items-center justify-center mb-2">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <div class="text-xs font-bold text-[#1D1D1F]">Sin clientes con modificaciones repetidas</div>
                <p class="text-[11px] text-[#86868B] mt-1 max-w-[280px] mx-auto">
                  No se detectaron clientes con múltiples solicitudes bajo los filtros activos.
                </p>
                <button
                  type="button"
                  @click="modoVistaLista = 'individual'"
                  class="mt-3 px-3 py-1 bg-white border border-[#D2D2D7] text-xs font-semibold rounded-[6px] text-[#0071E3] hover:bg-[#F2F2F7] cursor-pointer"
                >
                  Ver listado individual
                </button>
              </div>

              <!-- Tarjetas de Grupo por Cliente -->
              <div
                v-for="grupo in gruposRepetidos"
                :key="grupo.key"
                class="bg-white rounded-[8px] border border-[#E5E5EA] shadow-2xs overflow-hidden transition-all hover:border-[#D97706]/50"
              >
                <!-- Cabecera del Grupo (Cliente) -->
                <div class="p-2.5 bg-[#FFFBEB] border-b border-[#FDE68A]/70 flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#FEF3C7] text-[#92400E] border border-[#F59E0B]/30">
                        Cód: #{{ grupo.cliente_id }}
                      </span>
                      <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getCanalColor(grupo.canal) }"></span>
                      <span class="text-[10px] font-mono text-[#6E6E73]">{{ grupo.ruta }} ({{ grupo.canal }})</span>
                    </div>
                    <div class="text-xs font-bold text-[#1D1D1F] truncate mt-1" :title="grupo.cliente">
                      {{ grupo.cliente }}
                    </div>
                  </div>

                  <div class="text-right shrink-0 space-y-1">
                    <div class="flex items-center gap-1 justify-end">
                      <span class="px-2 py-0.5 bg-[#FEF3C7] border border-[#F59E0B]/40 rounded-full text-[10px] font-bold text-[#B45309] font-mono">
                        {{ grupo.total }} vers. ({{ grupo.total_pendientes }} pend.)
                      </span>
                    </div>

                    <!-- Botones de Acción del Grupo -->
                    <div class="flex items-center gap-1 justify-end pt-0.5">
                      <button
                        type="button"
                        @click="abrirComparativoCliente(grupo.cliente_id, grupo.cliente, grupo.ruta)"
                        class="px-2 py-0.5 bg-[#D97706] hover:bg-[#B45309] text-white rounded-[4px] text-[10px] font-bold cursor-pointer transition-colors shadow-2xs flex items-center gap-1"
                        title="Comparar versiones de este cliente lado a lado"
                      >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>Comparar</span>
                      </button>

                      <button
                        v-if="grupo.total_pendientes > 1"
                        type="button"
                        @click="quedarseConMasReciente(grupo)"
                        :disabled="procesandoGrupoKey === grupo.key"
                        class="px-2 py-0.5 bg-white border border-[#D97706] text-[#B45309] hover:bg-[#FEF3C7] rounded-[4px] text-[10px] font-bold cursor-pointer transition-colors disabled:opacity-50"
                        title="Aprobar automáticamente la más reciente y rechazar las demás solicitudes pendientes"
                      >
                        <span v-if="procesandoGrupoKey === grupo.key" class="w-2.5 h-2.5 border-2 border-[#B45309] border-t-transparent rounded-full animate-spin inline-block"></span>
                        <span v-else>Quedarse con la más reciente</span>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Sub-items (Versiones individuales de este cliente) -->
                <div class="divide-y divide-[#E5E5EA]/70">
                  <div
                    v-for="(item, idx) in grupo.items"
                    :key="item.id"
                    class="p-2 transition-colors hover:bg-[#F9F9FB] flex items-center justify-between gap-2"
                    :class="[
                      registroActivoId === item.id ? 'bg-[#F2F2F7]' : '',
                      item.estado_revision === 'APROBADO' ? 'border-l-3 border-l-[#248A3D]' : (item.estado_revision === 'RECHAZADO' ? 'border-l-3 border-l-[#FF3B30]' : 'border-l-3 border-l-[#FF9500]')
                    ]"
                  >
                    <div class="min-w-0 flex-1 cursor-pointer" @click="abrirExpediente(item)">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-[9.5px] font-mono font-bold text-[#86868B]">
                          #{{ item.id }}
                        </span>
                        <span v-if="idx === 0" class="px-1 py-0.2 rounded text-[8.5px] font-bold bg-[#EBF5FF] text-[#0071E3] border border-[#B3D7FF]">
                          Más reciente
                        </span>
                        <span class="text-[10px] font-mono text-[#6E6E73]">
                          {{ item.fecha_registro }}
                        </span>
                        <span v-if="item.vendedor" class="text-[10px] text-[#86868B] truncate max-w-[120px]">
                          • {{ item.vendedor }}
                        </span>
                      </div>
                      <div class="text-[11px] text-[#1D1D1F] line-clamp-1 mt-0.5">
                        {{ item.direccion || item.cliente || 'Sin dirección' }}
                      </div>
                    </div>

                    <!-- Botones de Acción Rápida por Versión -->
                    <div class="flex items-center gap-1 shrink-0">
                      <!-- Badge Estado -->
                      <span
                        class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded border"
                        :class="getEstadoRevisionClass(item.estado_revision)"
                      >
                        {{ item.estado_revision }}
                      </span>

                      <!-- Botón Aprobar -->
                      <button
                        type="button"
                        @click.stop="cambiarEstadoRevision(item, 'APROBADO')"
                        :disabled="cambiandoEstadoId === item.id || item.estado_revision === 'APROBADO'"
                        class="w-6 h-6 rounded flex items-center justify-center transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="item.estado_revision === 'APROBADO' ? 'bg-[#248A3D] text-white' : 'bg-white text-[#248A3D] border border-[#248A3D]/40 hover:bg-[#EBF9EF]'"
                        title="Aprobar esta versión"
                      >
                        <span v-if="cambiandoEstadoId === item.id && estadoEnProceso === 'APROBADO'" class="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                      </button>

                      <!-- Botón Rechazar -->
                      <button
                        type="button"
                        @click.stop="cambiarEstadoRevision(item, 'RECHAZADO')"
                        :disabled="cambiandoEstadoId === item.id || item.estado_revision === 'RECHAZADO'"
                        class="w-6 h-6 rounded flex items-center justify-center transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="item.estado_revision === 'RECHAZADO' ? 'bg-[#FF3B30] text-white' : 'bg-white text-[#C9342C] border border-[#C9342C]/40 hover:bg-[#FDF0EF]'"
                        title="Rechazar esta versión"
                      >
                        <span v-if="cambiandoEstadoId === item.id && estadoEnProceso === 'RECHAZADO'" class="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>

                      <!-- Botón A Pendiente -->
                      <button
                        type="button"
                        @click.stop="cambiarEstadoRevision(item, 'PENDIENTE')"
                        :disabled="cambiandoEstadoId === item.id || item.estado_revision === 'PENDIENTE'"
                        class="w-6 h-6 rounded flex items-center justify-center transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                        :class="item.estado_revision === 'PENDIENTE' ? 'bg-[#FF9500] text-white' : 'bg-white text-[#B25E00] border border-[#FF9500]/40 hover:bg-[#FFF8E6]'"
                        title="Devolver a Pendiente"
                      >
                        <span v-if="cambiandoEstadoId === item.id && estadoEnProceso === 'PENDIENTE'" class="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </button>

                      <!-- Botón Ver Ficha -->
                      <button
                        type="button"
                        @click.stop="abrirExpediente(item)"
                        class="px-1.5 py-0.5 rounded text-[10px] text-[#0071E3] font-medium hover:bg-[#F2F2F7] cursor-pointer"
                        title="Ver ficha técnica de inspección"
                      >
                        Ficha &rarr;
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- MODO 2: FICHA TÉCNICA COMPLETA DE INSPECCIÓN -->
          <template v-else>
            <!-- Cabecera del Expediente -->
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between gap-2 shrink-0">
              <button
                type="button"
                @click="registroSeleccionado = null; limpiarLienasAuxiliares()"
                class="text-xs font-semibold text-[#0071E3] hover:underline flex items-center gap-1 cursor-pointer shrink-0"
              >
                <span>&larr;</span> Volver al listado
              </button>

              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="descargarXlsxAltaIndividual(registroSeleccionado)"
                  class="h-7 px-2.5 bg-[#248A3D] hover:bg-[#1E7433] active:bg-[#185E29] text-white text-[11px] font-bold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none"
                  title="Descargar esta alta en formato ERP (.xlsx con 36 columnas)"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <span>Descargar ERP (.xlsx)</span>
                </button>

                <button
                  type="button"
                  @click="vistaMovil = 'mapa'"
                  class="md:hidden text-xs text-[#1D1D1F] font-semibold bg-white border border-[#E5E5EA] px-2 py-0.5 rounded-[4px]"
                >
                  Ver en mapa
                </button>
              </div>
            </div>

            <!-- Ficha Técnica con todos los datos requeridos -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-[#F5F5F7]">
              
              <!-- Banner Alerta: Cliente con Doble Modificación Pendiente -->
              <div
                v-if="registroSeleccionado.tiene_doble_modificacion && registroSeleccionado.estado_revision === 'PENDIENTE'"
                class="bg-[#FFFBEB] border border-[#F59E0B]/50 rounded-[8px] p-3 shadow-2xs flex items-start gap-2.5"
              >
                <div class="p-1.5 rounded-full bg-[#FEF3C7] text-[#D97706] shrink-0 mt-0.5">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="flex-1 text-xs text-[#92400E]">
                  <div class="font-bold flex items-center justify-between gap-2 flex-wrap">
                    <span>Atención: Cliente con Doble Modificación</span>
                    <div class="flex items-center gap-1.5 shrink-0">
                      <span class="text-[10px] font-mono px-2 py-0.5 bg-[#FEF3C7] border border-[#F59E0B]/30 rounded-full font-bold text-[#B45309]">
                        {{ registroSeleccionado.total_pendientes_cliente }} pendientes
                      </span>
                      <button
                        type="button"
                        @click="abrirComparativoCliente(registroSeleccionado.cliente_id)"
                        class="px-2.5 py-1 bg-[#D97706] hover:bg-[#B45309] text-white rounded-[6px] font-bold text-[10.5px] flex items-center gap-1 shadow-2xs cursor-pointer transition-transform active:scale-95"
                        title="Comparar todas las solicitudes del cliente lado a lado"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>Comparar versiones &rarr;</span>
                      </button>
                    </div>
                  </div>
                  <p class="mt-1 text-[11px] text-[#B45309] leading-relaxed">
                    Este cliente posee múltiples solicitudes pendientes en el sistema.
                    <template v-if="registroSeleccionado.otras_solicitudes_ids?.length">
                      Otras solicitudes pendientes:
                      <span class="inline-flex items-center gap-1.5 ml-1">
                        <button
                          v-for="otroId in registroSeleccionado.otras_solicitudes_ids"
                          :key="otroId"
                          type="button"
                          @click="irASolicitud(otroId)"
                          class="font-mono font-bold px-1.5 py-0.2 bg-[#FDE68A] hover:bg-[#FCD34D] text-[#78350F] rounded cursor-pointer transition-colors"
                          :title="`Abrir solicitud #${otroId}`"
                        >
                          #{{ otroId }} &rarr;
                        </button>
                      </span>
                    </template>
                  </p>
                </div>
              </div>

              <!-- 0. Panel de Acción de Auditoría (Aprobar / Rechazar / Pendiente) -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 shadow-2xs space-y-2.5">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider flex items-center gap-1.5 font-mono">
                    <svg class="w-3.5 h-3.5 text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Auditoría de Solicitud #{{ registroSeleccionado.id }}
                  </span>

                  <span
                    class="text-[9px] font-mono font-bold px-2 py-0.5 rounded border inline-block"
                    :class="getEstadoRevisionClass(registroSeleccionado.estado_revision)"
                  >
                    Estado: {{ registroSeleccionado.estado_revision }}
                  </span>
                </div>

                <!-- Trazabilidad del Revisor & Creador -->
                <div class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] p-2 text-[11px] font-mono grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Registrado Por (Móvil)</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.usuario || ('Usuario #' + (registroSeleccionado.user_id || 'N/A')) }}</span>
                    <span class="text-[10px] text-[#86868B] block">{{ registroSeleccionado.fecha_registro }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Revisado / Auditado Por</span>
                    <span class="font-semibold" :class="registroSeleccionado.revisor_nombre ? 'text-[#0071E3]' : 'text-[#86868B] italic'">
                      {{ registroSeleccionado.revisor_nombre || (registroSeleccionado.revisor_id ? ('Usuario #' + registroSeleccionado.revisor_id) : 'Pendiente de revisión') }}
                    </span>
                    <span v-if="registroSeleccionado.revisado_at" class="text-[10px] text-[#86868B] block">{{ registroSeleccionado.revisado_at }}</span>
                  </div>
                </div>

                <!-- Botones Principales de Auditoría: Aprobar, Rechazar, Pendiente -->
                <div class="grid grid-cols-3 gap-2">
                  <!-- 1. APROBAR -->
                  <button
                    type="button"
                    @click="cambiarEstadoRevision(registroSeleccionado, 'APROBADO')"
                    :disabled="cambiandoEstadoId === registroSeleccionado.id || registroSeleccionado.estado_revision === 'APROBADO'"
                    class="h-9 px-2 rounded-[6px] text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-2xs select-none disabled:cursor-not-allowed"
                    :class="registroSeleccionado.estado_revision === 'APROBADO'
                      ? 'bg-[#EBF9EF] text-[#248A3D] border-2 border-[#248A3D] font-extrabold opacity-100'
                      : 'bg-[#248A3D] text-white hover:bg-[#1E7433] active:scale-[0.98] disabled:opacity-50'"
                    title="Aprobar solicitud"
                  >
                    <span v-if="cambiandoEstadoId === registroSeleccionado.id && estadoEnProceso === 'APROBADO'" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>{{ registroSeleccionado.estado_revision === 'APROBADO' ? 'Aprobado' : 'Aprobar' }}</span>
                  </button>

                  <!-- 2. RECHAZAR -->
                  <button
                    type="button"
                    @click="cambiarEstadoRevision(registroSeleccionado, 'RECHAZADO')"
                    :disabled="cambiandoEstadoId === registroSeleccionado.id || registroSeleccionado.estado_revision === 'RECHAZADO'"
                    class="h-9 px-2 rounded-[6px] text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-2xs select-none disabled:cursor-not-allowed"
                    :class="registroSeleccionado.estado_revision === 'RECHAZADO'
                      ? 'bg-[#FDF0EF] text-[#C9342C] border-2 border-[#C9342C] font-extrabold opacity-100'
                      : 'bg-[#FF3B30] text-white hover:bg-[#D70015] active:scale-[0.98] disabled:opacity-50'"
                    title="Rechazar solicitud"
                  >
                    <span v-if="cambiandoEstadoId === registroSeleccionado.id && estadoEnProceso === 'RECHAZADO'" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>{{ registroSeleccionado.estado_revision === 'RECHAZADO' ? 'Rechazado' : 'Rechazar' }}</span>
                  </button>

                  <!-- 3. PENDIENTE -->
                  <button
                    type="button"
                    @click="cambiarEstadoRevision(registroSeleccionado, 'PENDIENTE')"
                    :disabled="cambiandoEstadoId === registroSeleccionado.id || registroSeleccionado.estado_revision === 'PENDIENTE'"
                    class="h-9 px-2 rounded-[6px] text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-2xs select-none disabled:cursor-not-allowed"
                    :class="registroSeleccionado.estado_revision === 'PENDIENTE'
                      ? 'bg-[#FFF8E6] text-[#B25E00] border-2 border-[#FF9500] font-extrabold opacity-100'
                      : 'bg-white text-[#B25E00] border border-[#FF9500]/50 hover:bg-[#FFF8E6] active:scale-[0.98] disabled:opacity-50'"
                    title="Devolver a estado Pendiente de revisión"
                  >
                    <span v-if="cambiandoEstadoId === registroSeleccionado.id && estadoEnProceso === 'PENDIENTE'" class="w-3.5 h-3.5 border-2 border-[#B25E00] border-t-transparent rounded-full animate-spin"></span>
                    <svg v-else class="w-4 h-4 text-[#FF9500]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ registroSeleccionado.estado_revision === 'PENDIENTE' ? 'Pendiente' : 'A Pendiente' }}</span>
                  </button>
                </div>

                <!-- Feedback / Mensaje en línea -->
                <div
                  v-if="mensajeEstado"
                  class="p-2 rounded-[6px] text-[11px] font-medium border flex items-center justify-between"
                  :class="mensajeEstado.tipo === 'exito' ? 'bg-[#EBF9EF] text-[#248A3D] border-[#248A3D]/20' : 'bg-[#FDF0EF] text-[#C9342C] border-[#C9342C]/20'"
                >
                  <span>{{ mensajeEstado.texto }}</span>
                  <button type="button" @click="mensajeEstado = null" class="font-bold text-xs ml-2 cursor-pointer text-[#86868B] hover:text-[#1D1D1F]">&times;</button>
                </div>
              </div>

              <!-- EXPEDIENTE DE ALTA: COPIADO RÁPIDO Y EXPORTACIÓN ERP -->
              <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs space-y-3 font-sans">
                <!-- Encabezado de la tarjeta con acciones rápidas -->
                <div class="flex items-center justify-between gap-2 pb-2 border-b border-[#E5E5EA] flex-wrap">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <span class="w-2 h-2 rounded-full bg-[#0071E3] shrink-0"></span>
                    <span class="text-[11px] font-bold text-[#1D1D1F] uppercase tracking-wider truncate">
                      Datos de Alta · Expediente ERP
                    </span>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                    <button
                      type="button"
                      @click="descargarXlsxAltaIndividual(registroSeleccionado)"
                      class="px-2.5 py-1 rounded-[6px] text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1 active:scale-95 select-none bg-[#248A3D] text-white hover:bg-[#1E7433] border-[#248A3D]"
                      title="Descargar este registro en archivo .xlsx con las 36 columnas para ERP"
                    >
                      <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <span>Descargar ERP (.xlsx)</span>
                    </button>
                    <button
                      type="button"
                      @click="copiarFilaExcel(registroSeleccionado)"
                      class="px-2.5 py-1 rounded-[6px] text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1 active:scale-95 select-none"
                      :class="campoCopiado === 'excel' ? 'bg-[#248A3D] text-white border-[#248A3D]' : 'bg-[#F2F2F7] hover:bg-[#E5E5EA] text-[#1D1D1F] border-[#E5E5EA]'"
                      title="Copiar las 36 columnas tabuladas para pegar directamente en Excel"
                    >
                      <svg v-if="campoCopiado === 'excel'" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                      </svg>
                      <svg v-else class="w-3 h-3 text-[#6E6E73]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <span>{{ campoCopiado === 'excel' ? 'Copiado para Excel' : 'Fila Excel' }}</span>
                    </button>
                    <button
                      type="button"
                      @click="copiarFichaCompleta(registroSeleccionado)"
                      class="px-2.5 py-1 rounded-[6px] text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1 active:scale-95 select-none"
                      :class="campoCopiado === 'todo' ? 'bg-[#248A3D] text-white border-[#248A3D]' : 'bg-[#0071E3] text-white hover:bg-[#005BB5] border-[#0071E3]'"
                      title="Copiar todos los datos formateados con etiquetas"
                    >
                      <svg v-if="campoCopiado === 'todo'" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                      </svg>
                      <svg v-else class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                      </svg>
                      <span>{{ campoCopiado === 'todo' ? 'Ficha Copiada' : 'Copiar Todo' }}</span>
                    </button>
                  </div>
                </div>

                <p class="text-[10px] text-[#6E6E73] leading-tight">
                  Haz clic sobre cualquier campo para copiar su valor directamente:
                </p>

                <!-- Bloque 1: Identificación y Nombres -->
                <div class="p-2.5 rounded-[8px] bg-[#FBFBFD] border border-[#E5E5EA] space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-[9px] font-bold uppercase tracking-wider text-[#86868B]">1. Identificación y Nombres</span>
                    <span class="text-[9px] font-mono text-[#86868B]">
                      #{{ registroSeleccionado.id }}
                      <span v-if="registroSeleccionado.cliente_id" class="text-[#0071E3] font-semibold">• Cód: {{ registroSeleccionado.cliente_id }}</span>
                      <span v-else class="text-[#248A3D] font-semibold">• Alta Nueva</span>
                    </span>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.nombres, 'nombres', 'Nombres')"
                      class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'nombres' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Nombres"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Nombres</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.nombres || 'Sin nombres' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.apellido_paterno, 'paterno', 'Apellido Paterno')"
                      class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'paterno' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Apellido Paterno"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Ap. Paterno</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.apellido_paterno || 'Sin ap. paterno' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.apellido_materno, 'materno', 'Apellido Materno')"
                      class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'materno' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Apellido Materno"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Ap. Materno</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.apellido_materno || 'Sin ap. materno' }}</span>
                    </div>
                  </div>

                  <div
                    @click="copiarAlPortapapeles(registroSeleccionado.cliente, 'nombre_completo', 'Nombre Comercial')"
                    class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors flex items-center justify-between"
                    :class="campoCopiado === 'nombre_completo' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    title="Clic para copiar Nombre Comercial"
                  >
                    <div class="min-w-0 flex-1">
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Nombre Completo / Comercial (cliente)</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.cliente || 'Sin nombre comercial' }}</span>
                    </div>
                    <span v-if="campoCopiado === 'nombre_completo'" class="text-[9px] font-bold text-[#248A3D] shrink-0">Copiado</span>
                  </div>
                </div>

                <!-- Bloque 2: Documentación y Facturación -->
                <div class="p-2.5 rounded-[8px] bg-[#FBFBFD] border border-[#E5E5EA] space-y-1.5">
                  <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">2. Documentación & Facturación Tributaria</span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.documento_identidad || registroSeleccionado.nit, 'ci', 'Doc. Identidad')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'ci' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Documento de Identidad"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Doc. Identidad</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.documento_identidad || registroSeleccionado.nit || 'Sin doc.' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.expedido, 'expedido', 'Expedido')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'expedido' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Expedido"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Expedido</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.expedido || '-' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.tipo_documento, 'tipo_doc', 'Tipo Documento')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'tipo_doc' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Tipo Documento"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Tipo Documento</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.tipo_documento || 'CI' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.tipo_nit, 'tipo_nit', 'Tipo NIT')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'tipo_nit' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Tipo NIT"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Tipo NIT</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.tipo_nit || 'CI' }}</span>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.nit || registroSeleccionado.documento_identidad, 'nit', 'NIT')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'nit' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar NIT"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">NIT Facturación</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.nit || registroSeleccionado.documento_identidad || 'Sin NIT' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.nombre_factura || registroSeleccionado.razon_social || registroSeleccionado.cliente, 'nombre_factura', 'Nombre Factura')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'nombre_factura' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                      title="Clic para copiar Nombre Factura"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Nombre Factura / Razón Social</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.nombre_factura || registroSeleccionado.razon_social || registroSeleccionado.cliente || 'Sin nombre' }}</span>
                    </div>
                  </div>
                </div>

                <!-- Bloque 3: Clasificación Comercial y Comunicación -->
                <div class="p-2.5 rounded-[8px] bg-[#FBFBFD] border border-[#E5E5EA] space-y-1.5">
                  <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">3. Clasificación Comercial & Comunicación</span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.tipo_negocio, 'tipo_negocio', 'Tipo Negocio')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'tipo_negocio' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Tipo Negocio</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.tipo_negocio || 'No asignado' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.lista_precios || 'TDB', 'lista_precios', 'Precio Lista')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'lista_precios' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Precio Lista</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.lista_precios || 'TDB' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.ruta, 'ruta', 'Ruta')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'ruta' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Ruta Oficial</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.ruta }} ({{ registroSeleccionado.canal }})</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.vendedor, 'vendedor', 'Vendedor')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'vendedor' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Vendedor</span>
                      <span class="text-[11px] text-[#1D1D1F] truncate block">{{ registroSeleccionado.vendedor || 'Sin asignar' }}</span>
                    </div>
                  </div>

                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.celular, 'celular', 'Celular')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'celular' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Celular</span>
                      <span class="text-[11px] font-mono font-bold truncate block" :class="registroSeleccionado.celular ? 'text-[#0071E3]' : 'text-[#86868B]'">{{ registroSeleccionado.celular || 'Sin celular' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.telefono, 'telefono', 'Teléfono')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'telefono' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Teléfono</span>
                      <span class="text-[11px] font-mono font-bold truncate block" :class="registroSeleccionado.telefono ? 'text-[#0071E3]' : 'text-[#86868B]'">{{ registroSeleccionado.telefono || 'Sin teléfono' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.contacto, 'contacto', 'Contacto')"
                      class="p-1.5 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors col-span-2 sm:col-span-1"
                      :class="campoCopiado === 'contacto' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Contacto</span>
                      <span class="text-[11px] text-[#1D1D1F] truncate block">{{ registroSeleccionado.contacto || 'No registrado' }}</span>
                    </div>
                  </div>
                </div>

                <!-- Bloque 4: Ubicación y Georreferenciación -->
                <div class="p-2.5 rounded-[8px] bg-[#FBFBFD] border border-[#E5E5EA] space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-[9px] font-bold uppercase tracking-wider text-[#86868B]">4. Ubicación y Georreferenciación</span>
                    <span v-if="registroSeleccionado.accuracy !== null" class="text-[9px] font-mono text-[#86868B]">
                      Precisión: {{ registroSeleccionado.accuracy }}m
                    </span>
                  </div>

                  <div class="grid grid-cols-2 gap-1.5">
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.latitud, 'latitud', 'Latitud')"
                      class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'latitud' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Latitud GPS (lat)</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.latitud ?? 'Sin GPS' }}</span>
                    </div>
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.longitud, 'longitud', 'Longitud')"
                      class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                      :class="campoCopiado === 'longitud' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                    >
                      <span class="text-[8px] font-bold uppercase text-[#86868B] block">Longitud GPS (lng)</span>
                      <span class="text-[11px] font-mono font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.longitud ?? 'Sin GPS' }}</span>
                    </div>
                  </div>

                  <div
                    @click="copiarAlPortapapeles(registroSeleccionado.direccion, 'direccion', 'Dirección')"
                    class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                    :class="campoCopiado === 'direccion' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                  >
                    <span class="text-[8px] font-bold uppercase text-[#86868B] block">Dirección</span>
                    <span class="text-[11px] text-[#1D1D1F] line-clamp-2 block">{{ registroSeleccionado.direccion || 'Sin dirección registrada' }}</span>
                  </div>

                  <div
                    v-if="registroSeleccionado.referencia"
                    @click="copiarAlPortapapeles(registroSeleccionado.referencia, 'referencia', 'Referencia')"
                    class="p-2 rounded-[6px] bg-white border cursor-pointer hover:border-[#0071E3] transition-colors"
                    :class="campoCopiado === 'referencia' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA]'"
                  >
                    <span class="text-[8px] font-bold uppercase text-[#86868B] block">Referencia</span>
                    <span class="text-[11px] text-[#6E6E73] italic line-clamp-2 block">{{ registroSeleccionado.referencia }}</span>
                  </div>
                </div>
              </div>

              <!-- 1.5. Detección de Duplicados & Clientes Cercanos (Radio 200m - Todas las Rutas) -->
              <div v-if="canCercanos" class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 space-y-2.5 shadow-2xs">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[11px] font-bold text-[#1D1D1F] tracking-tight">
                      Clientes Cercanos (Radio 200m)
                    </span>
                  </div>
                  <span
                    v-if="datosCercanos"
                    class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full"
                    :class="datosCercanos.tiene_duplicados ? 'bg-[#FF3B30] text-white shadow-2xs' : 'bg-[#EBF9EF] text-[#248A3D] border border-[#248A3D]/20'"
                  >
                    {{ datosCercanos.tiene_duplicados ? 'COINCIDENCIAS DETECTADAS' : `${datosCercanos.total_cercanos} en 200m` }}
                  </span>
                </div>

                <!-- Cargando -->
                <div v-if="cargandoCercanos" class="py-4 flex flex-col items-center justify-center gap-2 text-xs text-[#6E6E73] bg-[#FBFBFD] rounded-[8px] border border-dashed border-[#E5E5EA]">
                  <span class="w-4 h-4 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin"></span>
                  <span class="font-medium text-[11px]">Escaneando clientes de cualquier ruta en 200m a la redonda...</span>
                </div>

                <div v-else-if="datosCercanos" class="space-y-2.5">
                  <!-- Banner de Alertas si hay Duplicados -->
                  <div
                    v-if="datosCercanos.tiene_duplicados"
                    class="bg-[#FFF2F2] border border-[#FF3B30]/30 rounded-[8px] p-2.5 text-xs text-[#D70015]"
                  >
                    <div class="font-bold flex items-center gap-1.5 mb-1.5 text-[11px]">
                      <span class="w-2 h-2 rounded-full bg-[#FF3B30] animate-pulse"></span>
                      <span>Coincidencias detectadas con la solicitud:</span>
                    </div>
                    <ul class="space-y-1 text-[10.5px] font-sans pl-1">
                      <li v-for="(alerta, aIdx) in datosCercanos.alertas_duplicidad" :key="aIdx" class="leading-tight flex items-start gap-1">
                        <span class="text-[#FF3B30]">•</span>
                        <span>{{ alerta }}</span>
                      </li>
                    </ul>
                  </div>

                  <div
                    v-else
                    class="bg-[#EBF9EF] border border-[#34C759]/30 rounded-[8px] p-2 text-xs text-[#248A3D] flex items-center gap-1.5"
                  >
                    <svg class="w-3.5 h-3.5 text-[#248A3D] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span class="text-[11px] font-medium">
                      Radio despejado: Sin datos repetidos con los {{ datosCercanos.total_cercanos }} puntos vecinos.
                    </span>
                  </div>

                  <!-- Filtro interactivo de clientes cercanos -->
                  <div v-if="datosCercanos.clientes_cercanos && datosCercanos.clientes_cercanos.length > 0">
                    <div class="flex items-center justify-between gap-1 pb-1 border-b border-[#E5E5EA]">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          @click="filtroCercanosTipo = 'todos'"
                          class="px-2 py-0.5 rounded text-[10px] font-semibold transition-colors cursor-pointer"
                          :class="filtroCercanosTipo === 'todos' ? 'bg-[#0071E3] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] hover:text-[#1D1D1F]'"
                        >
                          Todos ({{ datosCercanos.clientes_cercanos.length }})
                        </button>
                        <button
                          v-if="datosCercanos.clientes_cercanos.some(x => x.tiene_duplicidad)"
                          type="button"
                          @click="filtroCercanosTipo = 'alertas'"
                          class="px-2 py-0.5 rounded text-[10px] font-semibold transition-colors cursor-pointer"
                          :class="filtroCercanosTipo === 'alertas' ? 'bg-[#FF3B30] text-white' : 'bg-[#FFF2F2] text-[#D70015] hover:bg-[#FFE5E5]'"
                        >
                          Alertas ({{ datosCercanos.clientes_cercanos.filter(x => x.tiene_duplicidad).length }})
                        </button>
                        <button
                          v-if="datosCercanos.clientes_cercanos.some(x => x.fotos_count > 0 || x.ultima_foto)"
                          type="button"
                          @click="filtroCercanosTipo = 'fotos'"
                          class="px-2 py-0.5 rounded text-[10px] font-semibold transition-colors cursor-pointer"
                          :class="filtroCercanosTipo === 'fotos' ? 'bg-[#5856D6] text-white' : 'bg-[#F4F3FF] text-[#5856D6] hover:bg-[#EBE9FE]'"
                        >
                          Con Fotos ({{ datosCercanos.clientes_cercanos.filter(x => x.fotos_count > 0 || x.ultima_foto).length }})
                        </button>
                      </div>

                      <button
                        type="button"
                        @click="mostrarListaCercanos = !mostrarListaCercanos"
                        class="text-[10px] text-[#0071E3] font-semibold hover:underline cursor-pointer"
                      >
                        {{ mostrarListaCercanos ? 'Ocultar' : 'Ver lista' }}
                      </button>
                    </div>

                    <!-- Lista de tarjetas de clientes cercanos -->
                    <div v-if="mostrarListaCercanos" class="space-y-2 mt-2 max-h-[420px] overflow-y-auto pr-1 custom-scroll">
                      <div
                        v-for="(c, cIdx) in clientesCercanosFiltrados"
                        :key="cIdx"
                        class="p-2.5 rounded-[8px] border text-[11px] transition-all space-y-2"
                        :class="c.tiene_duplicidad ? 'bg-[#FFF9F9] border-[#FF3B30]/40 shadow-xs' : 'bg-[#FBFBFD] border-[#E5E5EA] hover:border-[#D1D1D6]'"
                      >
                        <!-- Cabecera de la tarjeta -->
                        <div class="flex items-start justify-between gap-1.5">
                          <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                            <span
                              class="px-1.5 py-0.2 rounded font-mono font-bold text-[9.5px]"
                              :class="c.distancia_metros <= 20 ? 'bg-[#FF3B30] text-white' : (c.distancia_metros <= 50 ? 'bg-[#FFF5E5] text-[#B25E00] border border-[#B25E00]/20' : 'bg-[#F2F2F7] text-[#6E6E73]')"
                            >
                              {{ formatDistanciaTexto(c.distancia_metros) }}
                            </span>
                            <span class="text-[9.5px] font-mono text-[#86868B]">
                              {{ c.cliente_id ? `ID #${c.cliente_id}` : `Alta #${c.saneamiento_id}` }}
                            </span>
                            <span
                              class="text-[8.5px] font-bold px-1.5 py-0.2 rounded uppercase"
                              :class="c.es_alta_pendiente ? 'bg-[#EBF5FF] text-[#0071E3]' : (c.estado?.toLowerCase() === 'activo' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#F2F2F7] text-[#6E6E73]')"
                            >
                              {{ c.es_alta_pendiente ? 'Alta pendiente' : (c.estado || 'Activo') }}
                            </span>
                          </div>

                          <span class="px-1.5 py-0.2 bg-[#F2F2F7] text-[#1D1D1F] border border-[#E5E5EA] rounded font-mono font-bold text-[9.5px] shrink-0">
                            Ruta {{ c.ruta }}
                          </span>
                        </div>

                        <!-- Cuerpo con miniatura de foto y datos del cliente -->
                        <div class="flex items-start gap-2.5">
                          <!-- Miniatura de foto si existe -->
                          <div
                            v-if="c.ultima_foto"
                            @click="abrirFotosCliente(c)"
                            class="w-14 h-14 rounded-[6px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] shrink-0 cursor-pointer relative group shadow-2xs hover:border-[#0071E3] transition-colors"
                            :title="`Ver ${c.fotos_count || 1} fotografía(s) de este cliente`"
                          >
                            <img :src="c.ultima_foto" alt="Fachada" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-[9px] font-bold">
                              Ver
                            </div>
                            <div class="absolute bottom-0.5 right-0.5 bg-black/70 text-white font-mono text-[7.5px] font-bold px-1 rounded">
                              {{ c.fotos_count || 1 }} fotos
                            </div>
                          </div>

                          <!-- Información del cliente -->
                          <div class="flex-1 min-w-0 space-y-0.5">
                            <div class="font-bold text-[#1D1D1F] text-[11.5px] truncate" :title="c.cliente">
                              {{ c.cliente }}
                            </div>
                            <div v-if="c.nombre_factura && c.nombre_factura !== c.cliente" class="text-[9.5px] text-[#6E6E73] truncate">
                              Fact: {{ c.nombre_factura }}
                            </div>
                            <div class="text-[10px] text-[#6E6E73] flex items-center gap-1.5 flex-wrap">
                              <span v-if="c.vendedor" class="truncate max-w-[120px]">{{ c.vendedor }}</span>
                              <span v-if="c.dia && c.dia !== 'SIN DIA'">{{ c.dia }}</span>
                              <span v-if="c.tipo_negocio">{{ c.tipo_negocio }}</span>
                            </div>
                            <div v-if="c.direccion" class="text-[9.5px] text-[#86868B] truncate" :title="c.direccion">
                              {{ c.direccion }}
                            </div>
                          </div>
                        </div>

                        <!-- Badges de Coincidencia -->
                        <div v-if="c.coincidencias && c.coincidencias.length > 0" class="flex flex-wrap gap-1 pt-1 border-t border-black/[0.04]">
                          <span
                            v-for="coinc in c.coincidencias"
                            :key="coinc"
                            class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-[#FF3B30] text-white flex items-center gap-1 shadow-2xs"
                          >
                            <span>{{ coinc === 'proximidad_extrema' ? 'Proximidad <20m' : (coinc === 'telefono_duplicado' ? 'Mismo Teléfono' : (coinc === 'nit_duplicado' ? 'Mismo NIT' : 'Nombre Similar')) }}</span>
                          </span>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-between gap-1.5 pt-1 border-t border-[#E5E5EA]/60 text-[10px]">
                          <div class="flex items-center gap-1.5">
                            <a
                              v-if="c.celular || c.telefono"
                              :href="`tel:${c.celular || c.telefono}`"
                              class="px-2 py-0.5 rounded text-[#248A3D] bg-[#EBF9EF] hover:bg-[#DDF5E3] font-semibold flex items-center gap-1 transition-colors"
                              title="Llamar al cliente"
                            >
                              <svg class="w-3 h-3 text-[#248A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                              </svg>
                              <span>{{ c.celular || c.telefono }}</span>
                            </a>
                            <span v-if="c.nit" class="text-[9px] font-mono text-[#86868B] px-1 bg-[#F2F2F7] rounded">
                              NIT: {{ c.nit }}
                            </span>
                          </div>

                          <div class="flex items-center gap-1 shrink-0">
                            <button
                              type="button"
                              @click="enfocarCercano(c)"
                              class="px-2 py-0.5 text-[10px] font-bold text-[#0071E3] bg-white border border-[#0071E3]/30 rounded hover:bg-[#EFF6FF] cursor-pointer transition-colors"
                              title="Centrar en el mapa"
                            >
                              Mapa
                            </button>
                            <button
                              v-if="c.fotos_count > 0 || c.ultima_foto"
                              type="button"
                              @click="abrirFotosCliente(c)"
                              class="px-2 py-0.5 text-[10px] font-bold text-[#5856D6] bg-[#F4F3FF] border border-[#5856D6]/30 rounded hover:bg-[#EBE9FE] cursor-pointer transition-colors flex items-center gap-1"
                              title="Ver fotografías de visitas o fachada"
                            >
                              <svg class="w-3 h-3 text-[#5856D6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                              </svg>
                              <span>{{ c.fotos_count || 1 }}</span>
                            </button>
                            <button
                              type="button"
                              @click="abrirDetalleCercano(c)"
                              class="px-2.5 py-0.5 text-[10px] font-bold text-white bg-[#0071E3] hover:bg-[#0062C4] rounded shadow-2xs cursor-pointer transition-colors"
                              title="Ver totalidad del cliente y comparativa"
                            >
                              Ficha Completa &rarr;
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>



              <!-- 5. Fotografía de Fachada -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-2.5">
                <div class="flex items-center justify-between mb-1.5">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block">Fotografía de Fachada (photo_path)</span>
                  <span v-if="registroSeleccionado.photo_path" class="text-[9px] font-mono text-[#86868B] truncate max-w-[200px]" :title="registroSeleccionado.photo_path">
                    {{ registroSeleccionado.photo_path }}
                  </span>
                </div>
                <div
                  v-if="registroSeleccionado.photo_url"
                  @click="abrirFotosRegistroActual"
                  class="w-full h-44 rounded-[6px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] cursor-pointer group relative"
                >
                  <img
                    :src="registroSeleccionado.photo_url"
                    alt="Fachada"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                  />
                  <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-medium">
                    Ver fotografía ampliada &rarr;
                  </div>
                </div>
                <div v-else class="h-20 rounded-[6px] bg-[#F2F2F7] flex items-center justify-center text-xs text-[#86868B]">
                  Sin evidencia fotográfica registrada (photo_path = null)
                </div>
              </div>

              <!-- 6. Comparativa Exhaustiva de Modificaciones (Diff) si es Edición -->
              <div v-if="registroSeleccionado.es_edicion" class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <div class="flex items-center justify-between pb-1 border-b border-[#E5E5EA]">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider flex items-center gap-1.5 font-mono">
                    <svg class="w-3.5 h-3.5 text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Modificaciones Solicitadas (Cotejo vs pan_ruteo)
                  </span>
                  <span class="text-[9px] font-mono px-1.5 py-0.2 bg-[#EFF6FF] text-[#0071E3] font-bold rounded">
                    Cliente ID: {{ registroSeleccionado.cliente_id }}
                  </span>
                </div>

                <div class="divide-y divide-[#E5E5EA] text-[11px] font-mono">
                  <!-- 1. Nombre Comercial -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.cliente, registroSeleccionado.cliente) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Nombre Anterior (pan_ruteo):</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.cliente || 'Sin datos' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Nombre Propuesto:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.cliente, registroSeleccionado.cliente)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.cliente }}</span>
                    </div>
                  </div>

                  <!-- 2. Razón Social -->
                  <div v-if="registroSeleccionado.nombre_factura || registroSeleccionado.original?.nombre_factura" class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.nombre_factura, registroSeleccionado.nombre_factura) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Razón Social Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.nombre_factura || 'No registrada' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Razón Social Propuesta:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.nombre_factura, registroSeleccionado.nombre_factura)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.nombre_factura || 'No registrada' }}</span>
                    </div>
                  </div>

                  <!-- 3. NIT / CI -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.nit, registroSeleccionado.nit) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">NIT Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.nit || 'No registrado' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>NIT Propuesto:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.nit, registroSeleccionado.nit)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.nit || 'No registrado' }}</span>
                    </div>
                  </div>

                  <!-- 4. Tipo de Negocio -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.tipo_negocio, registroSeleccionado.tipo_negocio) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Tipo Negocio Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.tipo_negocio || 'No asignado' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Tipo Negocio Propuesto:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.tipo_negocio, registroSeleccionado.tipo_negocio)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.tipo_negocio || 'No asignado' }}</span>
                    </div>
                  </div>

                  <!-- 5. Persona de Contacto -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.contacto, registroSeleccionado.contacto) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Contacto Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.contacto || 'No registrado' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Contacto Propuesto:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.contacto, registroSeleccionado.contacto)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.contacto || 'No registrado' }}</span>
                    </div>
                  </div>

                  <!-- 6. Teléfono Celular -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.celular, registroSeleccionado.celular) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Celular Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.celular || 'No registrado' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Celular Propuesto:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.celular, registroSeleccionado.celular)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.celular || 'No registrado' }}</span>
                    </div>
                  </div>

                  <!-- 7. Teléfono Fijo -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.telefono, registroSeleccionado.telefono) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Teléfono Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.telefono || 'No registrado' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Teléfono Propuesto:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.telefono, registroSeleccionado.telefono)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.telefono || 'No registrado' }}</span>
                    </div>
                  </div>

                  <!-- 8. Zona / Territorio -->
                  <div v-if="registroSeleccionado.zona || registroSeleccionado.original?.zona" class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.zona, registroSeleccionado.zona) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Zona Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.zona || 'No asignada' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Zona Propuesta:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.zona, registroSeleccionado.zona)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.zona || 'No asignada' }}</span>
                    </div>
                  </div>

                  <!-- 9. Lista de Precios -->
                  <div v-if="registroSeleccionado.lista_precios || registroSeleccionado.original?.lista_precios" class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.lista_precios, registroSeleccionado.lista_precios) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Lista Precios Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.lista_precios || 'No asignada' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Lista Precios Propuesta:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.lista_precios, registroSeleccionado.lista_precios)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.lista_precios || 'No asignada' }}</span>
                    </div>
                  </div>

                  <!-- 10. Dirección -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.direccion, registroSeleccionado.direccion) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Dirección Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.direccion || 'Sin datos' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Dirección Propuesta:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.direccion, registroSeleccionado.direccion)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.direccion }}</span>
                    </div>
                  </div>

                  <!-- 11. Referencia -->
                  <div v-if="registroSeleccionado.referencia || registroSeleccionado.original?.referencia" class="py-1.5 grid grid-cols-2 gap-2" :class="isDiferente(registroSeleccionado.original?.referencia, registroSeleccionado.referencia) ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Referencia Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.referencia || 'Sin referencia' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>Referencia Propuesta:</span>
                        <span v-if="isDiferente(registroSeleccionado.original?.referencia, registroSeleccionado.referencia)" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">CAMBIO</span>
                      </span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.referencia || 'Sin referencia' }}</span>
                    </div>
                  </div>

                  <!-- 12. GPS y Coordenadas -->
                  <div class="py-1.5 grid grid-cols-2 gap-2" :class="registroSeleccionado.distancia_desplazamiento_m ? 'bg-[#EFF6FF]/60 px-1 rounded' : ''">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">GPS Anterior:</span>
                      <span class="text-[#6E6E73] text-[10px]">
                        {{ formatCoord(registroSeleccionado.original?.latitud) }}, {{ formatCoord(registroSeleccionado.original?.longitud) }}
                      </span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block flex items-center justify-between">
                        <span>GPS Propuesto:</span>
                        <span v-if="registroSeleccionado.distancia_desplazamiento_m" class="text-[8px] px-1 bg-[#0071E3] text-white rounded font-bold">
                          DESV {{ registroSeleccionado.distancia_desplazamiento_m }}m
                        </span>
                      </span>
                      <span class="font-bold text-[#1D1D1F] text-[10px]">
                        {{ formatCoord(registroSeleccionado.latitud) }}, {{ formatCoord(registroSeleccionado.longitud) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 7. Matriz Técnica: TODAS las Columnas de Base de Datos (supervisor.saneamiento_base) -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <div class="flex items-center justify-between cursor-pointer select-none" @click="mostrarTablaColumnas = !mostrarTablaColumnas">
                  <div class="flex items-center gap-1.5 font-mono">
                    <svg class="w-3.5 h-3.5 text-[#248A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7zm0 4h16M10 4v16" />
                    </svg>
                    <span class="text-[10px] font-bold text-[#1D1D1F] uppercase tracking-wider">
                      Todas las Columnas en BD (saneamiento_base)
                    </span>
                  </div>
                  <div class="flex items-center gap-1 text-[10px] font-mono text-[#0071E3]">
                    <span class="px-1.5 py-0.2 bg-[#F2F2F7] text-[#1D1D1F] rounded font-bold">24 / 24 Columnas</span>
                    <span>{{ mostrarTablaColumnas ? '▲ Ocultar' : '▼ Ver todo' }}</span>
                  </div>
                </div>

                <div v-if="mostrarTablaColumnas" class="pt-2 border-t border-[#E5E5EA]">
                  <p class="text-[10px] text-[#86868B] mb-2 font-sans">
                    Inspección técnica directa de cada campo almacenado en la tabla <code class="bg-[#F2F2F7] px-1 py-0.5 rounded text-[#1D1D1F]">supervisor.saneamiento_base</code>:
                  </p>
                  
                  <div class="overflow-x-auto">
                    <table class="w-full text-left text-[10px] font-mono border border-[#E5E5EA] rounded divide-y divide-[#E5E5EA]">
                      <thead class="bg-[#FBFBFD] text-[#86868B]">
                        <tr>
                          <th class="p-1.5 border-r border-[#E5E5EA]">#</th>
                          <th class="p-1.5 border-r border-[#E5E5EA]">Columna DB</th>
                          <th class="p-1.5 border-r border-[#E5E5EA]">Descripción</th>
                          <th class="p-1.5">Valor Actual en BD</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-[#E5E5EA]">
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">1</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">id</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">ID autonumérico primario</td>
                          <td class="p-1.5 font-bold text-[#1D1D1F]">{{ registroSeleccionado.id }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">2</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">user_id</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Usuario creador en app móvil</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.user_id !== null ? registroSeleccionado.user_id : 'null' }} <span v-if="registroSeleccionado.usuario" class="text-[#0071E3]">({{ registroSeleccionado.usuario }})</span></td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">3</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">cliente_id</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Código cliente (null si es alta)</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.cliente_id !== null ? registroSeleccionado.cliente_id : 'null (ALTA NUEVA)' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">4</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">tipo_registro</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">ALTA o EDICIÓN</td>
                          <td class="p-1.5 font-bold" :class="registroSeleccionado.tipo_registro.includes('ALTA') ? 'text-[#248A3D]' : 'text-[#0071E3]'">{{ registroSeleccionado.tipo_registro }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">5</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">route</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Ruta de ruteo asignada</td>
                          <td class="p-1.5 text-[#1D1D1F] font-bold">{{ registroSeleccionado.ruta }} <span class="text-[#86868B] font-normal">({{ registroSeleccionado.canal }})</span></td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">6</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">cliente</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Nombre comercial del cliente</td>
                          <td class="p-1.5 font-bold text-[#1D1D1F]">{{ registroSeleccionado.cliente }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">7</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">tipo_negocio</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Giro / Tipo de negocio</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.tipo_negocio || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">8</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">zona</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Zona / Territorio comercial</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.zona || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">9</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">lista_precios</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Lista de precios asignada</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.lista_precios || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">10</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">contacto</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Persona de contacto</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.contacto || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">11</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">telefono</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Teléfono fijo</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.telefono || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">12</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">celular</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Teléfono celular</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.celular || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">13</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">direccion</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Dirección domiciliaria</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.direccion || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">14</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">referencia</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Referencia del lugar</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.referencia || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">15</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">nombre_factura</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Razón Social Factura</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.nombre_factura || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">16</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">nit</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">NIT o Documento de Identidad</td>
                          <td class="p-1.5 text-[#1D1D1F] font-bold">{{ registroSeleccionado.nit || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">17</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">latitude</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Latitud GPS capturada</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.latitud !== null ? registroSeleccionado.latitud : 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">18</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">longitude</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Longitud GPS capturada</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.longitud !== null ? registroSeleccionado.longitud : 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">19</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">accuracy</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Precisión sensor GPS en metros</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.accuracy !== null ? registroSeleccionado.accuracy + ' m' : 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">20</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">photo_path</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Ruta en disco de la fotografía</td>
                          <td class="p-1.5 text-[#1D1D1F] truncate max-w-[180px]" :title="registroSeleccionado.photo_path">{{ registroSeleccionado.photo_path || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">21</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">estado_revision</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Estado de auditoría</td>
                          <td class="p-1.5 font-bold">{{ registroSeleccionado.estado_revision }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">22</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">revisado_at</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Timestamp de revisión</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.revisado_at || 'null' }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">23</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">created_at</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Timestamp de creación en app</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.created_at_raw || registroSeleccionado.fecha_registro }}</td>
                        </tr>
                        <tr class="hover:bg-[#F9F9FB]">
                          <td class="p-1.5 text-[#86868B] border-r border-[#E5E5EA]">24</td>
                          <td class="p-1.5 font-bold text-[#0071E3] border-r border-[#E5E5EA]">updated_at</td>
                          <td class="p-1.5 text-[#6E6E73] border-r border-[#E5E5EA]">Timestamp de actualización en BD</td>
                          <td class="p-1.5 text-[#1D1D1F]">{{ registroSeleccionado.updated_at_raw || registroSeleccionado.fecha_actualizacion || 'null' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Campos adicionales derivados -->
                  <div class="mt-2 pt-2 border-t border-[#E5E5EA] text-[10px] text-[#86868B]">
                    <div class="font-bold text-[#1D1D1F] mb-1">Atributos Enriquecidos / Cruzados:</div>
                    <div class="grid grid-cols-2 gap-1.5">
                      <div>Canal: <strong class="text-[#1D1D1F]">{{ registroSeleccionado.canal }}</strong></div>
                      <div>Vendedor: <strong class="text-[#1D1D1F]">{{ registroSeleccionado.vendedor || 'Sin asignar' }}</strong></div>
                      <div class="col-span-2">Desvío Haversine: <strong class="text-[#0071E3]">{{ registroSeleccionado.distancia_desplazamiento_m !== null ? registroSeleccionado.distancia_desplazamiento_m + ' metros' : 'N/A' }}</strong></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </aside>

        <!-- Panel Derecho: Visor Cartográfico Leaflet -->
        <main
          class="flex-1 relative min-h-0 bg-[#E5E5EA]"
          :class="[
            vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full',
            vistaPantalla === 'panel' ? 'md:hidden!' : 'md:block'
          ]"
        >
          <div id="map-altas" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda Flotante -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-[500] text-[11px] max-w-[240px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Saneamiento de Base</p>
            <div class="space-y-1 pb-1.5 border-b border-[#E5E5EA]">
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#248A3D] border border-white shrink-0"></span>
                <span class="text-[#1D1D1F] font-medium">Altas Nuevas</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#0071E3] border border-white shrink-0"></span>
                <span class="text-[#1D1D1F] font-medium">Ediciones Solicitadas</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 border-b-2 border-dashed border-[#0071E3] shrink-0"></span>
                <span class="text-[#6E6E73] text-[10px]">Desplazamiento GPS</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#8E8E93] border border-white shrink-0"></span>
                <span class="text-[#1D1D1F] font-medium">Clientes en 200m</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-[#FF3B30] border border-white shrink-0"></span>
                <span class="text-[#D70015] font-bold">Duplicado / Coincidencia</span>
              </div>
            </div>
            <div class="mt-1.5 text-[10px] text-[#86868B]">
              Toca un pin para ver el popup y acceder a la ficha técnica.
            </div>
          </div>
        </main>
      </div>

      <!-- VISOR LIGHTBOX DE FOTOGRAFÍA -->
      <Teleport to="body">
        <div
          v-if="lightboxUrl"
          @click="lightboxUrl = null"
          class="fixed inset-0 z-[99999] bg-black/90 backdrop-blur-md flex items-center justify-center p-4 cursor-pointer select-none"
        >
          <div class="relative max-w-4xl max-h-[85vh]">
            <img
              :src="lightboxUrl"
              alt="Fotografía Ampliada"
              class="max-h-[82vh] max-w-full rounded-[8px] object-contain shadow-2xl border border-white/10"
            />
            <button
              type="button"
              @click="lightboxUrl = null"
              class="absolute top-2 right-2 p-1.5 rounded-full bg-black/50 text-white hover:bg-black/80 transition-colors cursor-pointer"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </Teleport>

      <!-- ======================================================== -->
      <!-- MODAL DE EXPEDIENTE COMPLETO & COMPARATIVA LADO A LADO   -->
      <!-- ======================================================== -->
      <Teleport to="body">
        <div
          v-if="clienteCercanoDetalle"
          class="fixed inset-0 z-[99995] bg-black/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5 select-none font-sans animate-in fade-in duration-150"
          @click.self="cerrarDetalleCercano"
        >
          <div
            class="bg-white rounded-[16px] shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden border border-[#E5E5EA] max-h-[92vh]"
            @click.stop
          >
            <!-- Cabecera del Modal -->
            <div class="px-5 py-3.5 bg-[#FBFBFD] border-b border-[#E5E5EA] flex items-center justify-between shrink-0">
              <div class="flex items-center gap-2.5 min-w-0 pr-2">
                <span
                  class="w-3 h-3 rounded-full shrink-0"
                  :class="clienteCercanoDetalle.tiene_duplicidad ? 'bg-[#FF3B30] shadow-[0_0_8px_rgba(255,59,48,0.6)]' : 'bg-[#0071E3] shadow-[0_0_8px_rgba(0,113,227,0.5)]'"
                ></span>
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-sm font-bold text-[#1D1D1F] truncate leading-tight">
                      {{ clienteCercanoDetalle.cliente }}
                    </h3>
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                      :class="clienteCercanoDetalle.distancia_metros <= 20 ? 'bg-[#FF3B30] text-white' : (clienteCercanoDetalle.distancia_metros <= 50 ? 'bg-[#FFF5E5] text-[#B25E00]' : 'bg-[#F2F2F7] text-[#6E6E73]')"
                    >
                      a {{ clienteCercanoDetalle.distancia_metros }} metros
                    </span>
                    <span
                      class="px-2 py-0.2 rounded text-[9.5px] font-bold uppercase"
                      :class="clienteCercanoDetalle.es_alta_pendiente ? 'bg-[#EBF5FF] text-[#0071E3]' : (clienteCercanoDetalle.estado?.toLowerCase() === 'activo' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#F2F2F7] text-[#6E6E73]')"
                    >
                      {{ clienteCercanoDetalle.es_alta_pendiente ? 'Alta pendiente en revisión' : (clienteCercanoDetalle.estado || 'Activo en padrón') }}
                    </span>
                  </div>
                  <p class="text-[11px] text-[#6E6E73] font-mono truncate mt-0.5">
                    {{ clienteCercanoDetalle.cliente_id ? `Código Cliente #${clienteCercanoDetalle.cliente_id}` : `Solicitud Alta #${clienteCercanoDetalle.saneamiento_id}` }}
                    · Ruta {{ clienteCercanoDetalle.ruta }}{{ clienteCercanoDetalle.vendedor ? ` (${clienteCercanoDetalle.vendedor})` : '' }}
                  </p>
                </div>
              </div>

              <!-- Botón Cerrar -->
              <button
                type="button"
                @click="cerrarDetalleCercano"
                class="w-7 h-7 rounded-full bg-[#F2F2F7] hover:bg-[#E5E5EA] text-[#1D1D1F] flex items-center justify-center transition-all cursor-pointer shrink-0"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Selector de Pestañas del Modal -->
            <div class="px-5 py-2 bg-[#F5F5F7] border-b border-[#E5E5EA] flex items-center justify-between gap-2 shrink-0">
              <div class="flex items-center gap-1.5 text-xs font-semibold">
                <button
                  type="button"
                  @click="tabModalCercano = 'comparativa'"
                  class="px-3 py-1.5 rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
                  :class="tabModalCercano === 'comparativa' ? 'bg-white text-[#0071E3] shadow-xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                  </svg>
                  <span>Comparativa vs Solicitud</span>
                  <span v-if="clienteCercanoDetalle.tiene_duplicidad" class="w-2 h-2 rounded-full bg-[#FF3B30]"></span>
                </button>

                <button
                  type="button"
                  @click="tabModalCercano = 'ficha'"
                  class="px-3 py-1.5 rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
                  :class="tabModalCercano === 'ficha' ? 'bg-white text-[#0071E3] shadow-xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <span>Ficha Técnica Completa</span>
                </button>

                <button
                  type="button"
                  @click="tabModalCercano = 'fotos'"
                  class="px-3 py-1.5 rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
                  :class="tabModalCercano === 'fotos' ? 'bg-white text-[#0071E3] shadow-xs font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  </svg>
                  <span>Evidencias Fotográficas</span>
                  <span class="px-1.5 py-0.2 rounded-full text-[9px] font-mono bg-[#E5E5EA] text-[#1D1D1F]">
                    {{ fotosModalCercano.length || (clienteCercanoDetalle.fotos_count || (clienteCercanoDetalle.ultima_foto ? 1 : 0)) }}
                  </span>
                </button>
              </div>

              <!-- Acciones de Cabecera -->
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="enfocarCercano(clienteCercanoDetalle); cerrarDetalleCercano()"
                  class="px-2.5 py-1 text-xs font-semibold text-[#0071E3] bg-white border border-[#0071E3]/30 rounded-[6px] hover:bg-[#EFF6FF] transition-colors cursor-pointer flex items-center gap-1"
                >
                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  </svg>
                  <span>Ver en Mapa</span>
                </button>
              </div>
            </div>

            <!-- Cuerpo del Modal (Scrollable) -->
            <div class="p-4 sm:p-5 overflow-y-auto flex-1 custom-scroll space-y-4">
              <!-- PESTAÑA 1: COMPARATIVA LADO A LADO -->
              <div v-if="tabModalCercano === 'comparativa'" class="space-y-4">
                <!-- Banner de Diagnóstico de Duplicidad -->
                <div
                  v-if="clienteCercanoDetalle.tiene_duplicidad"
                  class="p-3 rounded-[10px] bg-[#FFF2F2] border border-[#FF3B30]/30 text-xs text-[#D70015] flex items-start gap-2.5"
                >
                  <div class="w-6 h-6 rounded-full bg-[#FF3B30] text-white flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                  </div>
                  <div class="space-y-1 min-w-0">
                    <div class="font-bold text-[12px]">Riesgo Crítico de Duplicidad Detectado</div>
                    <p class="text-[11px] leading-relaxed">
                      Se han encontrado datos idénticos o proximidad extrema entre la solicitud y este cliente del padrón. Verifica visualmente las fotografías y el NIT para dictaminar si es el mismo establecimiento.
                    </p>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                      <span
                        v-for="(alerta, aIdx) in clienteCercanoDetalle.alertas"
                        :key="aIdx"
                        class="px-2 py-0.5 rounded bg-white text-[#D70015] border border-[#FF3B30]/30 font-semibold text-[10px]"
                      >
                        • {{ alerta }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Tabla Comparativa Lado a Lado -->
                <div class="rounded-[12px] border border-[#E5E5EA] overflow-hidden bg-white shadow-2xs">
                  <table class="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr class="bg-[#F5F5F7] border-b border-[#E5E5EA] text-[11px] font-mono">
                        <th class="py-2.5 px-3 font-bold text-[#86868B] w-1/4">Campo Evaluado</th>
                        <th class="py-2.5 px-3 font-bold text-[#0071E3] w-[37.5%] border-l border-[#E5E5EA] bg-[#F0F7FF]/50">
                          <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
                            <span>Solicitud Actual (#{{ registroSeleccionado?.id }})</span>
                          </div>
                        </th>
                        <th class="py-2.5 px-3 font-bold text-[#1D1D1F] w-[37.5%] border-l border-[#E5E5EA] bg-[#FBFBFD]">
                          <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full" :class="clienteCercanoDetalle.tiene_duplicidad ? 'bg-[#FF3B30]' : 'bg-[#34C759]'"></span>
                            <span>Cliente Cercano (#{{ clienteCercanoDetalle.cliente_id || clienteCercanoDetalle.saneamiento_id }})</span>
                          </div>
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E5EA] text-[11px]">
                      <!-- 1. Fotografía de Fachada (Cotejo Visual) -->
                      <tr class="bg-[#FBFBFD]/40">
                        <td class="py-2.5 px-3 font-bold text-[#6E6E73] align-top font-mono">
                          Evidencia Fotográfica
                        </td>
                        <td class="py-2.5 px-3 border-l border-[#E5E5EA] align-top bg-[#F0F7FF]/20">
                          <div
                            v-if="registroSeleccionado?.photo_url"
                            @click="abrirFotosRegistroActual"
                            class="w-full h-32 rounded-[8px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] cursor-pointer relative group"
                          >
                            <img :src="registroSeleccionado.photo_url" alt="Alta" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-[10px] font-bold">
                              Ver Fachada Solicitud &rarr;
                            </div>
                          </div>
                          <div v-else class="h-16 rounded-[8px] bg-[#F2F2F7] flex items-center justify-center text-[10px] text-[#86868B]">
                            Sin fotografía en solicitud
                          </div>
                        </td>
                        <td class="py-2.5 px-3 border-l border-[#E5E5EA] align-top">
                          <div
                            v-if="clienteCercanoDetalle.ultima_foto"
                            @click="abrirFotosCliente(clienteCercanoDetalle)"
                            class="w-full h-32 rounded-[8px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] cursor-pointer relative group"
                          >
                            <img :src="clienteCercanoDetalle.ultima_foto" alt="Cercano" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-[10px] font-bold">
                              Ver Foto Cliente Cercano &rarr;
                            </div>
                            <div class="absolute bottom-1 right-1 bg-black/75 text-white font-mono text-[8px] font-bold px-1.5 py-0.2 rounded">
                              {{ clienteCercanoDetalle.fotos_count || 1 }} foto(s)
                            </div>
                          </div>
                          <div v-else class="h-16 rounded-[8px] bg-[#F2F2F7] flex items-center justify-center text-[10px] text-[#86868B]">
                            Sin fotos registradas
                          </div>
                        </td>
                      </tr>

                      <!-- 2. Nombre del Cliente -->
                      <tr :class="clienteCercanoDetalle.coincidencias?.includes('nombre_similar') ? 'bg-[#FFF2F2]/60' : ''">
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Nombre Comercial</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-bold text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.cliente || 'Sin datos' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-bold text-[#1D1D1F]">
                          <div class="flex items-center justify-between">
                            <span>{{ clienteCercanoDetalle.cliente }}</span>
                            <span v-if="clienteCercanoDetalle.coincidencias?.includes('nombre_similar')" class="text-[9px] font-bold text-[#FF3B30] bg-[#FFF2F2] px-1.5 py-0.2 rounded border border-[#FF3B30]/30 font-mono">
                              Similar
                            </span>
                          </div>
                        </td>
                      </tr>

                      <!-- 3. Razón Social / Factura -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Nombre Factura</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.nombre_factura || 'No registrado' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F]">
                          {{ clienteCercanoDetalle.nombre_factura || 'No registrado' }}
                        </td>
                      </tr>

                      <!-- 4. NIT / Documento -->
                      <tr :class="clienteCercanoDetalle.coincidencias?.includes('nit_duplicado') ? 'bg-[#FFF2F2]' : ''">
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">NIT / Documento</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.nit || 'Sin NIT' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F]">
                          <div class="flex items-center justify-between">
                            <span>{{ clienteCercanoDetalle.nit || 'Sin NIT' }}</span>
                            <span v-if="clienteCercanoDetalle.coincidencias?.includes('nit_duplicado')" class="text-[9px] font-bold text-[#FF3B30] bg-white px-1.5 py-0.2 rounded border border-[#FF3B30]/30 font-mono">
                              MISMO NIT
                            </span>
                          </div>
                        </td>
                      </tr>

                      <!-- 5. Celular / Teléfono -->
                      <tr :class="clienteCercanoDetalle.coincidencias?.includes('telefono_duplicado') ? 'bg-[#FFF2F2]' : ''">
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Celular / Teléfono</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.celular || registroSeleccionado?.telefono || 'No registrado' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F]">
                          <div class="flex items-center justify-between">
                            <a
                              v-if="clienteCercanoDetalle.celular || clienteCercanoDetalle.telefono"
                              :href="`tel:${clienteCercanoDetalle.celular || clienteCercanoDetalle.telefono}`"
                              class="text-[#0071E3] hover:underline"
                            >
                              {{ clienteCercanoDetalle.celular || clienteCercanoDetalle.telefono }}
                            </a>
                            <span v-else class="text-[#86868B]">No registrado</span>
                            <span v-if="clienteCercanoDetalle.coincidencias?.includes('telefono_duplicado')" class="text-[9px] font-bold text-[#FF3B30] bg-white px-1.5 py-0.2 rounded border border-[#FF3B30]/30 font-mono">
                              MISMO TELÉFONO
                            </span>
                          </div>
                        </td>
                      </tr>

                      <!-- 6. Distancia y Proximidad Geográfica -->
                      <tr :class="clienteCercanoDetalle.distancia_metros <= 20 ? 'bg-[#FFF2F2]' : ''">
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Distancia GPS</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono text-[10.5px] bg-[#F0F7FF]/20">
                          {{ formatCoord(registroSeleccionado?.latitud) }}, {{ formatCoord(registroSeleccionado?.longitud) }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold">
                          <div class="flex items-center justify-between">
                            <span :class="clienteCercanoDetalle.distancia_metros <= 20 ? 'text-[#FF3B30]' : 'text-[#0071E3]'">
                              {{ clienteCercanoDetalle.distancia_metros }} metros ({{ formatCoord(clienteCercanoDetalle.latitud) }}, {{ formatCoord(clienteCercanoDetalle.longitud) }})
                            </span>
                            <span v-if="clienteCercanoDetalle.distancia_metros <= 20" class="text-[9px] font-bold text-[#FF3B30] bg-white px-1.5 py-0.2 rounded border border-[#FF3B30]/30 font-mono">
                              &lt; 20 METROS
                            </span>
                          </div>
                        </td>
                      </tr>

                      <!-- 7. Dirección Completa -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Dirección</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.direccion || 'Sin dirección' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F]">
                          {{ clienteCercanoDetalle.direccion || 'Sin dirección' }}
                        </td>
                      </tr>

                      <!-- 8. Referencia -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Referencia</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#6E6E73] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.referencia || '-' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#6E6E73]">
                          {{ clienteCercanoDetalle.referencia || '-' }}
                        </td>
                      </tr>

                      <!-- 9. Ruta y Canal -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Ruta y Canal</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F] bg-[#F0F7FF]/20">
                          Ruta {{ registroSeleccionado?.ruta }} ({{ registroSeleccionado?.canal }})
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F]">
                          Ruta {{ clienteCercanoDetalle.ruta }}
                        </td>
                      </tr>

                      <!-- 10. Vendedor Responsable -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Vendedor</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.vendedor || 'Sin asignar' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F]">
                          {{ clienteCercanoDetalle.vendedor || 'Sin asignar' }}
                        </td>
                      </tr>

                      <!-- 11. Tipo de Negocio -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Tipo Negocio</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F] bg-[#F0F7FF]/20">
                          {{ registroSeleccionado?.tipo_negocio || 'Sin especificar' }}
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#1D1D1F]">
                          {{ clienteCercanoDetalle.tipo_negocio || 'Sin especificar' }}
                        </td>
                      </tr>

                      <!-- 12. Días de Visita -->
                      <tr>
                        <td class="py-2 px-3 font-semibold text-[#6E6E73] font-mono">Días de Visita</td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] text-[#86868B] bg-[#F0F7FF]/20 font-mono">
                          Por asignar
                        </td>
                        <td class="py-2 px-3 border-l border-[#E5E5EA] font-mono font-bold text-[#1D1D1F]">
                          {{ clienteCercanoDetalle.dia || 'Sin día' }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- PESTAÑA 2: FICHA TÉCNICA COMPLETA DEL CLIENTE CERCANO -->
              <div v-else-if="tabModalCercano === 'ficha'" class="space-y-3.5">
                <!-- Bloque 1: Identificación y Datos Comerciales -->
                <div class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[10px] p-3.5 space-y-2">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block font-mono">
                    1. Identificación y Registro Comercial
                  </span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Código Cliente</span>
                      <span class="font-bold text-[#0071E3] text-sm">#{{ clienteCercanoDetalle.cliente_id || clienteCercanoDetalle.saneamiento_id }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Estado</span>
                      <span
                        class="px-2 py-0.5 rounded font-bold text-[10px]"
                        :class="clienteCercanoDetalle.estado?.toLowerCase() === 'activo' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#F2F2F7] text-[#6E6E73]'"
                      >
                        {{ clienteCercanoDetalle.estado || 'Activo' }}
                      </span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">NIT / CI</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.nit || clienteCercanoDetalle.ci || 'Sin NIT' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Tipo de Negocio</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.tipo_negocio || 'Sin especificar' }}</span>
                    </div>
                    <div class="col-span-2">
                      <span class="text-[9px] text-[#86868B] uppercase block">Nombre Comercial</span>
                      <span class="font-bold text-[#1D1D1F] text-xs">{{ clienteCercanoDetalle.cliente }}</span>
                    </div>
                    <div class="col-span-2">
                      <span class="text-[9px] text-[#86868B] uppercase block">Razón Social / Nombre Factura</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.nombre_factura || clienteCercanoDetalle.cliente }}</span>
                    </div>
                  </div>
                </div>

                <!-- Bloque 2: Ruteo, Territorio y Días -->
                <div class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[10px] p-3.5 space-y-2">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block font-mono">
                    2. Ruteo y Logística de Visitas
                  </span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Ruta Asignada</span>
                      <span class="font-bold text-[#1D1D1F] text-sm">Ruta {{ clienteCercanoDetalle.ruta }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Vendedor</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.vendedor || 'Sin vendedor' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Día de Visita</span>
                      <span class="font-bold text-[#0071E3]">{{ clienteCercanoDetalle.dia || 'Sin día' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Frecuencia</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.frecuencia || 'Regular' }}</span>
                    </div>
                  </div>
                </div>

                <!-- Bloque 3: Contacto y Comunicación -->
                <div class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[10px] p-3.5 space-y-2">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block font-mono">
                    3. Contacto y Comunicación Directa
                  </span>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs font-mono">
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Celular</span>
                      <a
                        v-if="clienteCercanoDetalle.celular"
                        :href="`tel:${clienteCercanoDetalle.celular}`"
                        class="font-bold text-[#0071E3] hover:underline"
                      >
                        {{ clienteCercanoDetalle.celular }}
                      </a>
                      <span v-else class="text-[#86868B]">No registrado</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Teléfono Fijo</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.telefono || 'No registrado' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Persona de Contacto</span>
                      <span class="font-semibold text-[#1D1D1F]">{{ clienteCercanoDetalle.contacto || 'No registrada' }}</span>
                    </div>
                  </div>
                </div>

                <!-- Bloque 4: Ubicación Geográfica -->
                <div class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[10px] p-3.5 space-y-2">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block font-mono">
                    4. Ubicación Geográfica y Coordenadas
                  </span>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
                    <div class="col-span-2">
                      <span class="text-[9px] text-[#86868B] uppercase block">Dirección</span>
                      <span class="font-semibold text-[#1D1D1F] text-xs">{{ clienteCercanoDetalle.direccion || 'Sin dirección registrada' }}</span>
                    </div>
                    <div class="col-span-2">
                      <span class="text-[9px] text-[#86868B] uppercase block">Referencia</span>
                      <span class="font-medium text-[#6E6E73]">{{ clienteCercanoDetalle.referencia || 'Sin referencia' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Latitud</span>
                      <span class="font-mono text-[#1D1D1F]">{{ formatCoord(clienteCercanoDetalle.latitud) }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Longitud</span>
                      <span class="font-mono text-[#1D1D1F]">{{ formatCoord(clienteCercanoDetalle.longitud) }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Distancia al Alta</span>
                      <span class="font-mono font-bold text-[#0071E3]">{{ clienteCercanoDetalle.distancia_metros }} metros</span>
                    </div>
                    <div class="flex items-end">
                      <button
                        type="button"
                        @click="enfocarCercano(clienteCercanoDetalle); cerrarDetalleCercano()"
                        class="w-full py-1 text-xs font-bold text-[#0071E3] bg-white border border-[#0071E3]/30 rounded-[6px] hover:bg-[#EFF6FF] cursor-pointer"
                      >
                        Centrar en Mapa
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PESTAÑA 3: GALERÍA DE FOTOGRAFÍAS -->
              <div v-else-if="tabModalCercano === 'fotos'" class="space-y-3">
                <div v-if="cargandoFotosModalCercano" class="py-12 flex flex-col items-center justify-center gap-2 text-xs text-[#6E6E73]">
                  <span class="w-6 h-6 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></span>
                  <span class="font-medium">Consultando evidencias fotográficas del cliente...</span>
                </div>

                <div v-else-if="fotosModalCercano.length === 0" class="py-12 text-center text-xs text-[#86868B] bg-[#FBFBFD] rounded-[10px] border border-dashed border-[#E5E5EA] space-y-2">
                  <div class="w-10 h-10 rounded-full bg-[#F2F2F7] flex items-center justify-center text-[#86868B] mx-auto">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    </svg>
                  </div>
                  <div class="font-bold text-[#1D1D1F]">Sin fotografías registradas</div>
                  <p class="text-[11px] text-[#6E6E73]">Este cliente no cuenta con capturas fotográficas en su historial de visitas o alta.</p>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                  <div
                    v-for="(f, fIdx) in fotosModalCercano"
                    :key="fIdx"
                    @click="abrirFotoIndividual(f, fIdx)"
                    class="bg-[#FBFBFD] border border-[#E5E5EA] rounded-[10px] overflow-hidden cursor-pointer group shadow-2xs hover:border-[#0071E3] transition-all"
                  >
                    <div class="h-44 bg-[#F2F2F7] overflow-hidden relative">
                      <img :src="f.url || f.photo_url" alt="Visita" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                      <div class="absolute inset-0 bg-black/25 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                        Ampliar Fotografía &rarr;
                      </div>
                    </div>
                    <div class="p-2.5 text-[10.5px] font-mono space-y-0.5">
                      <div class="font-bold text-[#1D1D1F] flex items-center justify-between">
                        <span>{{ f.fecha || 'Fecha N/D' }}</span>
                        <span v-if="f.hora" class="text-[#86868B] text-[9.5px]">{{ f.hora }}</span>
                      </div>
                      <div v-if="f.vendedor" class="text-[#6E6E73] truncate">
                        {{ f.vendedor }}
                      </div>
                      <div v-if="f.comments" class="text-[#86868B] text-[9.5px] truncate" :title="f.comments">
                        {{ f.comments }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pie del Modal -->
            <div class="px-5 py-3 bg-[#FBFBFD] border-t border-[#E5E5EA] flex items-center justify-between gap-2 shrink-0">
              <span class="text-[11px] text-[#6E6E73] font-mono">
                Presiona ESC o el botón Cerrar para salir
              </span>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="enfocarCercano(clienteCercanoDetalle); cerrarDetalleCercano()"
                  class="px-3 py-1.5 text-xs font-semibold text-[#0071E3] bg-white border border-[#0071E3]/30 rounded-[8px] hover:bg-[#EFF6FF] cursor-pointer transition-colors"
                >
                  Ver en el Mapa
                </button>
                <button
                  type="button"
                  @click="cerrarDetalleCercano"
                  class="px-4 py-1.5 text-xs font-bold text-white bg-[#1D1D1F] hover:bg-black rounded-[8px] cursor-pointer transition-colors"
                >
                  Cerrar
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ======================================================== -->
      <!-- MODAL LIGHTBOX AVANZADO DE FOTOGRAFÍAS (CARROUSEL & ZOOM) -->
      <!-- ======================================================== -->
      <Teleport to="body">
        <div
          v-if="modalFotos.abierto"
          class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/80 backdrop-blur-md p-3 sm:p-5 select-none font-sans animate-in fade-in duration-150"
          @click.self="cerrarModalFotos"
        >
          <div
            class="bg-white rounded-[18px] shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden border border-white/80 max-h-[94vh]"
            @click.stop
          >
            <!-- Cabecera del Lightbox -->
            <div class="px-4 py-3 bg-white border-b border-[#E5E5EA] flex items-center justify-between shrink-0">
              <div class="flex items-center gap-2 min-w-0 pr-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] shrink-0 shadow-[0_0_6px_rgba(0,113,227,0.7)]"></span>
                <div class="min-w-0">
                  <h3 class="text-[13px] font-bold text-[#1D1D1F] truncate leading-tight">
                    {{ modalFotos.clienteNombre || 'Fotografía de Evidencia' }}
                  </h3>
                  <p class="text-[10px] text-[#6E6E73] font-mono truncate mt-0.5">
                    {{ modalFotos.clienteId ? `Código: #${modalFotos.clienteId}` : '' }}{{ modalFotos.ruta ? ` · Ruta: ${modalFotos.ruta}` : '' }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <span class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold font-mono text-[#0071E3] bg-[#EBF5FF] border border-[#B3D7FF]">
                  <template v-if="modalFotos.cargando">Cargando fotos...</template>
                  <template v-else-if="modalFotos.fotos.length === 0">0 fotografías</template>
                  <template v-else>Foto {{ modalFotos.indice + 1 }} de {{ modalFotos.fotos.length }}</template>
                </span>

                <a
                  v-if="fotoActualModal && (fotoActualModal.url || fotoActualModal.photo_url)"
                  :href="fotoActualModal.url || fotoActualModal.photo_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="hidden sm:inline-flex items-center gap-1 text-[11px] font-semibold text-[#6E6E73] hover:text-[#0071E3] px-2 py-1 rounded-[6px] bg-[#F2F2F7] hover:bg-[#E5E5EA] transition-all"
                  title="Abrir imagen original en nueva pestaña"
                >
                  <span>Abrir original ↗</span>
                </a>

                <button
                  type="button"
                  @click="cerrarModalFotos"
                  class="w-7 h-7 rounded-full bg-[#F2F2F7] hover:bg-[#E5E5EA] text-[#1D1D1F] flex items-center justify-center transition-all cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Cuerpo Central con Imagen -->
            <div class="p-3 sm:p-4 space-y-3 overflow-y-auto custom-scroll flex-1 bg-[#F5F5F7]">
              <!-- Loading -->
              <div
                v-if="modalFotos.cargando"
                class="w-full h-80 flex flex-col items-center justify-center space-y-3 bg-white rounded-[12px] border border-[#E5E5EA]"
              >
                <div class="w-8 h-8 border-3 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                <span class="text-xs font-semibold text-[#6E6E73]">Recuperando fotografías del servidor...</span>
              </div>

              <!-- Error -->
              <div
                v-else-if="modalFotos.errorMsg"
                class="w-full py-12 text-center bg-[#FDF0EF] border border-[#FADCD5] rounded-[12px] p-4 text-[#C9342C] space-y-1"
              >
                <div class="font-bold text-xs">Aviso de fotografías</div>
                <div class="text-[11px]">{{ modalFotos.errorMsg }}</div>
              </div>

              <!-- Sin Fotos -->
              <div
                v-else-if="modalFotos.fotos.length === 0"
                class="w-full py-16 flex flex-col items-center justify-center space-y-2 text-center bg-white rounded-[12px] border border-[#E5E5EA]"
              >
                <div class="w-12 h-12 rounded-full bg-[#F2F2F7] flex items-center justify-center text-[#86868B]">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                  </svg>
                </div>
                <div class="text-xs font-bold text-[#1D1D1F]">Sin fotografías registradas</div>
                <div class="text-[11px] text-[#6E6E73]">No se registraron evidencias fotográficas para este punto.</div>
              </div>

              <!-- Visualizador de Imagen -->
              <div v-else class="space-y-3">
                <div class="relative w-full h-[58vh] bg-black/95 rounded-[12px] overflow-hidden flex items-center justify-center group shadow-md border border-black/10">
                  <img
                    :src="fotoActualModal.url || fotoActualModal.photo_url"
                    alt="Evidencia fotográfica"
                    class="max-h-full max-w-full object-contain transition-transform duration-200 cursor-zoom-in"
                    :class="modalFotos.isZoomed ? 'scale-150 cursor-zoom-out' : 'scale-100'"
                    @click="modalFotos.isZoomed = !modalFotos.isZoomed"
                  />

                  <!-- Botones de Navegación (Carrusel) -->
                  <button
                    v-if="modalFotos.fotos.length > 1"
                    type="button"
                    @click.stop="anteriorFotoModal"
                    class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/60 hover:bg-black/85 text-white flex items-center justify-center transition-all cursor-pointer shadow-md"
                    title="Foto anterior (←)"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                  </button>

                  <button
                    v-if="modalFotos.fotos.length > 1"
                    type="button"
                    @click.stop="siguienteFotoModal"
                    class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/60 hover:bg-black/85 text-white flex items-center justify-center transition-all cursor-pointer shadow-md"
                    title="Foto siguiente (→)"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                  </button>

                  <!-- Indicador de Zoom -->
                  <div class="absolute bottom-2 left-2 bg-black/60 text-white text-[9.5px] px-2 py-0.5 rounded font-mono">
                    {{ modalFotos.isZoomed ? 'Zoom 150% · Clic para alejar' : 'Clic para ampliar' }}
                  </div>
                </div>

                <!-- Tarjeta de Metadatos de la Foto Actual -->
                <div class="p-3 bg-white rounded-[10px] border border-[#E5E5EA] flex items-center justify-between gap-3 text-xs font-mono">
                  <div class="flex items-center gap-4 flex-wrap">
                    <div>
                      <span class="text-[9px] text-[#86868B] uppercase block">Fecha / Hora</span>
                      <span class="font-bold text-[#1D1D1F]">{{ fotoActualModal.fecha || 'Sin fecha' }} {{ fotoActualModal.hora || '' }}</span>
                    </div>
                    <div v-if="fotoActualModal.vendedor">
                      <span class="text-[9px] text-[#86868B] uppercase block">Preventista / Vendedor</span>
                      <span class="font-bold text-[#0071E3]">{{ fotoActualModal.vendedor }}</span>
                    </div>
                    <div v-if="fotoActualModal.comments">
                      <span class="text-[9px] text-[#86868B] uppercase block">Comentario / Motivo</span>
                      <span class="text-[#1D1D1F] font-sans text-[11px]">{{ fotoActualModal.comments }}</span>
                    </div>
                  </div>

                  <div class="text-[10px] text-[#86868B] shrink-0 font-sans">
                    Navega con flechas ← →
                  </div>
                </div>

                <!-- Tira de Miniaturas si hay varias fotos -->
                <div v-if="modalFotos.fotos.length > 1" class="flex items-center gap-2 overflow-x-auto py-1">
                  <button
                    v-for="(f, idx) in modalFotos.fotos"
                    :key="idx"
                    type="button"
                    @click="modalFotos.indice = idx; modalFotos.isZoomed = false"
                    class="w-14 h-14 rounded-[6px] overflow-hidden border-2 shrink-0 transition-all cursor-pointer"
                    :class="modalFotos.indice === idx ? 'border-[#0071E3] scale-105 shadow-xs' : 'border-[#E5E5EA] opacity-60 hover:opacity-100'"
                  >
                    <img :src="f.url || f.photo_url" alt="Miniatura" class="w-full h-full object-cover" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ======================================================== -->
      <!-- MODAL DE CAMBIO MASIVO DE ESTADO DE REVISIÓN             -->
      <!-- ======================================================== -->
      <Teleport to="body">
        <div
          v-if="modalCambioMasivoAbierto"
          class="fixed inset-0 z-[99996] bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5 select-none font-sans animate-in fade-in duration-150"
          @click.self="cerrarModalCambioMasivo"
        >
          <div
            class="bg-white rounded-[16px] shadow-2xl w-full max-w-md flex flex-col overflow-hidden border border-[#E5E5EA]"
            @click.stop
          >
            <!-- Cabecera del Modal -->
            <div class="px-5 py-4 bg-[#FBFBFD] border-b border-[#E5E5EA] flex items-center justify-between shrink-0">
              <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-[#5856D6]"></span>
                <div>
                  <h3 class="text-sm font-bold text-[#1D1D1F]">Cambio Masivo de Estado</h3>
                  <p class="text-[11px] text-[#6E6E73] font-medium">
                    {{ submoduloActivo === 'altas' ? 'Nuevas Altas Filtradas' : 'Modificaciones de Clientes Filtradas' }}
                  </p>
                </div>
              </div>
              <button
                type="button"
                @click="cerrarModalCambioMasivo"
                :disabled="procesandoCambioMasivo"
                class="w-7 h-7 rounded-full bg-[#F2F2F7] hover:bg-[#E5E5EA] text-[#6E6E73] hover:text-[#1D1D1F] flex items-center justify-center text-sm font-bold transition-colors cursor-pointer disabled:opacity-40"
                title="Cerrar ventana"
              >
                &times;
              </button>
            </div>

            <!-- Cuerpo del Modal -->
            <div class="p-5 space-y-4 text-xs">
              <!-- Banner con Total de Registros Filtrados -->
              <div class="p-3 bg-[#F5F5F7] rounded-[10px] border border-[#E5E5EA] space-y-1">
                <div class="flex items-center justify-between text-[#1D1D1F] font-semibold">
                  <span>Registros a modificar:</span>
                  <span class="px-2 py-0.5 rounded-full bg-[#5856D6] text-white font-mono font-bold text-[11px]">
                    {{ registrosFiltrados.length }} solicitud(es)
                  </span>
                </div>
                <p class="text-[11px] text-[#6E6E73] leading-relaxed">
                  {{ submoduloActivo === 'altas'
                    ? 'Se actualizará el estado de revisión de todas las nuevas altas que cumplen con los filtros actualmente aplicados en pantalla.'
                    : 'Se actualizará el estado de revisión de todas las modificaciones que cumplen con los filtros actualmente aplicados en pantalla.' }}
                </p>
              </div>

              <!-- Selector de Nuevo Estado -->
              <div class="space-y-2">
                <label class="block font-bold text-[#1D1D1F] text-[11px] uppercase tracking-wider">
                  Seleccione el nuevo estado:
                </label>

                <div class="grid grid-cols-3 gap-2">
                  <!-- APROBADO -->
                  <button
                    type="button"
                    @click="estadoMasivoSeleccionado = 'APROBADO'"
                    class="p-2.5 rounded-[8px] border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5"
                    :class="estadoMasivoSeleccionado === 'APROBADO'
                      ? 'border-[#248A3D] bg-[#EBF9EF] text-[#248A3D] font-bold ring-2 ring-[#248A3D]/20 shadow-2xs'
                      : 'border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F9F9FB]'"
                  >
                    <svg class="w-4 h-4 text-[#248A3D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span class="text-xs">Aprobar</span>
                  </button>

                  <!-- RECHAZADO -->
                  <button
                    type="button"
                    @click="estadoMasivoSeleccionado = 'RECHAZADO'"
                    class="p-2.5 rounded-[8px] border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5"
                    :class="estadoMasivoSeleccionado === 'RECHAZADO'
                      ? 'border-[#FF3B30] bg-[#FDF0EF] text-[#C9342C] font-bold ring-2 ring-[#FF3B30]/20 shadow-2xs'
                      : 'border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F9F9FB]'"
                  >
                    <svg class="w-4 h-4 text-[#FF3B30]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="text-xs">Rechazar</span>
                  </button>

                  <!-- PENDIENTE -->
                  <button
                    type="button"
                    @click="estadoMasivoSeleccionado = 'PENDIENTE'"
                    class="p-2.5 rounded-[8px] border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5"
                    :class="estadoMasivoSeleccionado === 'PENDIENTE'
                      ? 'border-[#FF9500] bg-[#FFF8E6] text-[#B25E00] font-bold ring-2 ring-[#FF9500]/20 shadow-2xs'
                      : 'border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F9F9FB]'"
                  >
                    <svg class="w-4 h-4 text-[#FF9500]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs">Pendiente</span>
                  </button>
                </div>
              </div>

              <!-- Motivo u Observación Opcional -->
              <div class="space-y-1.5">
                <label class="block font-bold text-[#1D1D1F] text-[11px] uppercase tracking-wider">
                  Motivo u observación (opcional):
                </label>
                <textarea
                  v-model="motivoCambioMasivo"
                  rows="2"
                  placeholder="Observación o justificación del cambio masivo..."
                  class="w-full text-xs rounded-[8px] border border-[#E5E5EA] p-2.5 text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:ring-2 focus:ring-[#0071E3] focus:border-[#0071E3] transition-all resize-none"
                ></textarea>
              </div>

              <!-- Mensaje de Error si ocurre -->
              <div
                v-if="errorCambioMasivo"
                class="p-2.5 rounded-[8px] bg-[#FDF0EF] text-[#C9342C] border border-[#C9342C]/20 text-[11px] flex items-center justify-between"
              >
                <span>{{ errorCambioMasivo }}</span>
                <button type="button" @click="errorCambioMasivo = null" class="font-bold text-xs ml-2 cursor-pointer">&times;</button>
              </div>
            </div>

            <!-- Footer del Modal -->
            <div class="px-5 py-3.5 bg-[#FBFBFD] border-t border-[#E5E5EA] flex items-center justify-end gap-2.5 shrink-0">
              <button
                type="button"
                @click="cerrarModalCambioMasivo"
                :disabled="procesandoCambioMasivo"
                class="px-3.5 py-1.5 rounded-[8px] border border-[#D2D2D7] text-xs font-semibold text-[#1D1D1F] hover:bg-[#F2F2F7] transition-all cursor-pointer disabled:opacity-50"
              >
                Cancelar
              </button>
              <button
                type="button"
                @click="confirmarCambioMasivo"
                :disabled="procesandoCambioMasivo || registrosFiltrados.length === 0"
                class="px-4 py-1.5 rounded-[8px] text-xs font-bold text-white transition-all cursor-pointer shadow-2xs flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                :class="estadoMasivoSeleccionado === 'APROBADO' ? 'bg-[#248A3D] hover:bg-[#1E7433]' : (estadoMasivoSeleccionado === 'RECHAZADO' ? 'bg-[#FF3B30] hover:bg-[#D70015]' : 'bg-[#FF9500] hover:bg-[#D77E00]')"
              >
                <span v-if="procesandoCambioMasivo" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>{{ procesandoCambioMasivo ? 'Actualizando...' : `Aplicar a ${registrosFiltrados.length} registros` }}</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Modal Matriz Comparativa Lado a Lado de Versiones Repetidas -->
      <Teleport to="body">
        <div
          v-if="modalComparativo.abierto"
          class="fixed inset-0 z-[99990] flex items-center justify-center bg-black/60 backdrop-blur-xs p-2 sm:p-4"
          @click.self="cerrarModalComparativo"
        >
          <div class="bg-white rounded-[14px] shadow-2xl border border-[#D2D2D7] w-full max-w-[96vw] xl:max-w-7xl h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Header del Modal -->
            <div class="px-4 py-3 bg-[#1D1D1F] text-white flex items-center justify-between shrink-0">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-[8px] bg-[#D97706] text-white flex items-center justify-center font-bold shrink-0">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-sm font-bold text-white truncate">
                      Matriz Comparativa de Modificaciones Repetidas
                    </h3>
                    <span v-if="modalComparativo.clienteId" class="px-2 py-0.2 rounded-full bg-[#FEF3C7] text-[#92400E] font-mono text-[10px] font-bold">
                      Cód: #{{ modalComparativo.clienteId }}
                    </span>
                    <span class="text-xs text-[#86868B] truncate">
                      {{ modalComparativo.clienteNombre }}
                    </span>
                  </div>
                  <p class="text-[11px] text-[#A1A1A6] truncate mt-0.5">
                    Compara lado a lado los datos del padrón oficial frente a cada modificación registrada para elegir con cuál quedarse.
                  </p>
                </div>
              </div>

              <!-- Controles Header: Navegación entre Clientes Repetidos y Cerrar -->
              <div class="flex items-center gap-2 shrink-0">
                <!-- Navegación si hay varios clientes con repetidos -->
                <div v-if="gruposRepetidos.length > 1" class="hidden sm:flex items-center bg-white/10 rounded-[6px] p-0.5 text-xs text-white">
                  <button
                    type="button"
                    @click="navegarComparativo(-1)"
                    :disabled="modalComparativo.indiceRepetidoActual <= 0"
                    class="px-2 py-1 hover:bg-white/10 rounded-[4px] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer flex items-center gap-1"
                    title="Cliente repetido anterior"
                  >
                    <span>&larr;</span> Anterior
                  </button>
                  <span class="px-2 font-mono text-[11px] text-white/70">
                    {{ modalComparativo.indiceRepetidoActual + 1 }} / {{ gruposRepetidos.length }}
                  </span>
                  <button
                    type="button"
                    @click="navegarComparativo(1)"
                    :disabled="modalComparativo.indiceRepetidoActual >= gruposRepetidos.length - 1"
                    class="px-2 py-1 hover:bg-white/10 rounded-[4px] disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer flex items-center gap-1"
                    title="Siguiente cliente repetido"
                  >
                    Siguiente <span>&rarr;</span>
                  </button>
                </div>

                <button
                  type="button"
                  @click="cerrarModalComparativo"
                  class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
                  title="Cerrar modal (Esc)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Contenido de la Matriz Comparativa -->
            <div class="flex-1 overflow-auto bg-[#F5F5F7] p-3 sm:p-4">
              <!-- Cargando -->
              <div v-if="modalComparativo.cargando" class="h-full flex flex-col items-center justify-center space-y-3">
                <div class="w-10 h-10 border-3 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                <div class="text-xs font-semibold text-[#6E6E73]">Cargando historial de versiones del cliente...</div>
              </div>

              <!-- Error -->
              <div v-else-if="modalComparativo.errorMsg" class="h-full flex flex-col items-center justify-center p-6 text-center">
                <div class="p-4 bg-[#FDF0EF] border border-[#C9342C]/20 rounded-[10px] text-[#C9342C] text-xs max-w-md">
                  <div class="font-bold mb-1">No se pudieron cargar las versiones</div>
                  <p>{{ modalComparativo.errorMsg }}</p>
                  <button
                    type="button"
                    @click="abrirComparativoCliente(modalComparativo.clienteId)"
                    class="mt-3 px-3 py-1 bg-[#C9342C] text-white rounded-[6px] font-bold text-xs cursor-pointer"
                  >
                    Reintentar
                  </button>
                </div>
              </div>

              <!-- Matriz Lado a Lado (Tabla comparativa horizontal) -->
              <div v-else class="min-w-full inline-block align-top">
                <div class="grid gap-3" :style="{ gridTemplateColumns: `200px 280px repeat(${modalComparativo.versiones.length}, minmax(300px, 1fr))` }">
                  
                  <!-- COLUMNA 1: ETIQUETAS DE CAMPOS (Sticky) -->
                  <div class="space-y-2 sticky left-0 z-20">
                    <!-- Fila 0: Header -->
                    <div class="h-32 bg-white rounded-[10px] border border-[#E5E5EA] p-3 flex flex-col justify-between shadow-2xs">
                      <div>
                        <span class="text-[10px] font-mono uppercase tracking-wider text-[#86868B] font-bold">Campos Auditables</span>
                        <div class="text-xs font-extrabold text-[#1D1D1F] mt-1">Comparativa Detallada</div>
                      </div>
                      <div class="text-[10px] text-[#86868B]">
                        Total solicitudes: <strong class="text-[#1D1D1F]">{{ modalComparativo.versiones.length }}</strong>
                      </div>
                    </div>

                    <!-- Fila 1: Nombre / Razón Social -->
                    <div class="h-16 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Cliente / Razón Social
                    </div>

                    <!-- Fila 2: Dirección -->
                    <div class="h-20 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Dirección y Número
                    </div>

                    <!-- Fila 3: Referencia -->
                    <div class="h-16 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Referencia
                    </div>

                    <!-- Fila 4: Contacto & Teléfonos -->
                    <div class="h-16 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Contacto / Celular
                    </div>

                    <!-- Fila 5: NIT / Carnet -->
                    <div class="h-14 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      NIT / Documento
                    </div>

                    <!-- Fila 6: Tipo Negocio -->
                    <div class="h-14 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Tipo de Negocio
                    </div>

                    <!-- Fila 7: Zona / Territorio -->
                    <div class="h-14 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Zona / Territorio
                    </div>

                    <!-- Fila 8: Coordenadas GPS -->
                    <div class="h-20 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Ubicación GPS
                    </div>

                    <!-- Fila 9: Fotografía -->
                    <div class="h-32 bg-white rounded-[8px] border border-[#E5E5EA] p-2.5 flex items-center text-xs font-bold text-[#1D1D1F]">
                      Fotografía de Evidencia
                    </div>
                  </div>

                  <!-- COLUMNA 2: PADRÓN ORIGINAL (pan_ruteo) -->
                  <div class="space-y-2">
                    <!-- Fila 0: Header Padrón Original -->
                    <div class="h-32 bg-[#F2F2F7] rounded-[10px] border-2 border-[#D2D2D7] p-3 flex flex-col justify-between">
                      <div class="flex items-center justify-between">
                        <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-black/10 text-[#1D1D1F]">
                          PADRÓN OFICIAL
                        </span>
                        <span class="text-[10px] font-mono text-[#86868B]">pan_ruteo</span>
                      </div>
                      <div>
                        <div class="text-xs font-bold text-[#1D1D1F]">Datos Registrados en Base</div>
                        <div class="text-[10px] text-[#6E6E73] mt-0.5">Versión actual en sistema</div>
                      </div>
                      <div class="text-[10px] text-[#86868B] font-mono">
                        Ruta: {{ modalComparativo.original?.ruta || 'N/D' }}
                      </div>
                    </div>

                    <!-- Fila 1: Nombre Original -->
                    <div class="h-16 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs text-[#1D1D1F] flex items-center font-medium">
                      {{ modalComparativo.original?.cliente || 'Sin registro' }}
                    </div>

                    <!-- Fila 2: Dirección Original -->
                    <div class="h-20 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs text-[#1D1D1F] flex items-center leading-snug">
                      {{ modalComparativo.original?.direccion || 'Sin dirección' }}{{ modalComparativo.original?.nro ? ` #${modalComparativo.original.nro}` : '' }}
                    </div>

                    <!-- Fila 3: Referencia Original -->
                    <div class="h-16 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs text-[#6E6E73] flex items-center italic">
                      {{ modalComparativo.original?.referencia || 'Sin referencia' }}
                    </div>

                    <!-- Fila 4: Contacto / Teléfono Original -->
                    <div class="h-16 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs text-[#1D1D1F] flex flex-col justify-center">
                      <div class="font-medium truncate">{{ modalComparativo.original?.contacto || 'Sin contacto' }}</div>
                      <div class="text-[11px] text-[#6E6E73] font-mono mt-0.5">Tel: {{ modalComparativo.original?.celular || modalComparativo.original?.telefono || 'N/D' }}</div>
                    </div>

                    <!-- Fila 5: NIT Original -->
                    <div class="h-14 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs font-mono text-[#1D1D1F] flex items-center">
                      {{ modalComparativo.original?.nit || modalComparativo.original?.ci || 'Sin NIT' }}
                    </div>

                    <!-- Fila 6: Tipo Negocio Original -->
                    <div class="h-14 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs text-[#1D1D1F] flex items-center">
                      {{ modalComparativo.original?.tipo_negocio || 'Sin tipo' }}
                    </div>

                    <!-- Fila 7: Zona Original -->
                    <div class="h-14 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs text-[#1D1D1F] flex items-center">
                      {{ modalComparativo.original?.zona || modalComparativo.original?.territorio || 'N/D' }}
                    </div>

                    <!-- Fila 8: Coordenadas Original -->
                    <div class="h-20 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 text-xs font-mono text-[#1D1D1F] flex flex-col justify-center">
                      <div v-if="modalComparativo.original?.latitud">
                        Lat: {{ formatCoord(modalComparativo.original.latitud) }}<br />
                        Lng: {{ formatCoord(modalComparativo.original.longitud) }}
                      </div>
                      <div v-else class="text-[#86868B] italic">Sin coordenadas en padrón</div>
                    </div>

                    <!-- Fila 9: Fotografía Original -->
                    <div class="h-32 bg-[#F2F2F7] rounded-[8px] border border-[#D2D2D7] p-2.5 flex items-center justify-center text-center text-[11px] text-[#86868B]">
                      (Padrón histórico sin fotografía adjunta)
                    </div>
                  </div>

                  <!-- COLUMNAS 3..N: CADA VERSIÓN DE SANEAMIENTO_BASE -->
                  <div
                    v-for="(ver, vIdx) in modalComparativo.versiones"
                    :key="ver.id"
                    class="space-y-2"
                  >
                    <!-- Fila 0: Header de Solicitud con Acciones de Decisión -->
                    <div
                      class="h-32 bg-white rounded-[10px] border-2 p-2.5 flex flex-col justify-between shadow-2xs transition-all"
                      :class="[
                        ver.estado_revision === 'APROBADO' ? 'border-[#248A3D] bg-[#EBF9EF]/30' : (ver.estado_revision === 'RECHAZADO' ? 'border-[#FF3B30] bg-[#FDF0EF]/30' : 'border-[#FF9500] bg-[#FFF8E6]/40')
                      ]"
                    >
                      <div class="flex items-center justify-between gap-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="text-[11px] font-mono font-bold text-[#1D1D1F]">
                            #{{ ver.id }}
                          </span>
                          <span v-if="vIdx === 0" class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-[#0071E3] text-white">
                            Reciente
                          </span>
                        </div>
                        <span
                          class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded border"
                          :class="getEstadoRevisionClass(ver.estado_revision)"
                        >
                          {{ ver.estado_revision }}
                        </span>
                      </div>

                      <div class="text-[10px] text-[#6E6E73] space-y-0.5">
                        <div class="truncate"><strong>Vendedor:</strong> {{ ver.vendedor || 'N/D' }}</div>
                        <div class="truncate font-mono">{{ ver.fecha_registro }}</div>
                      </div>

                      <!-- Botones de Decisión: ⭐ Quedarse con esta y Botones Individuales -->
                      <div class="space-y-1 pt-1">
                        <button
                          type="button"
                          @click="quedarseConVersion(ver)"
                          :disabled="modalComparativo.procesandoId === ver.id"
                          class="w-full h-6 px-2 bg-[#D97706] hover:bg-[#B45309] text-white rounded-[5px] text-[10px] font-extrabold flex items-center justify-center gap-1 cursor-pointer transition-transform active:scale-95 disabled:opacity-50 shadow-2xs"
                          title="Aprobar esta versión y rechazar todas las demás solicitudes pendientes de este cliente"
                        >
                          <span v-if="modalComparativo.procesandoId === ver.id" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                          <span v-else>⭐ Quedarse con esta</span>
                        </button>

                        <div class="grid grid-cols-3 gap-1">
                          <button
                            type="button"
                            @click="cambiarEstadoEnModal(ver, 'APROBADO')"
                            :disabled="ver.estado_revision === 'APROBADO' || modalComparativo.procesandoId === ver.id"
                            class="h-5 rounded text-[9.5px] font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                            :class="ver.estado_revision === 'APROBADO' ? 'bg-[#248A3D] text-white' : 'bg-white text-[#248A3D] border border-[#248A3D]/40 hover:bg-[#EBF9EF]'"
                            title="Aprobar versión"
                          >
                            ✓ Apr
                          </button>
                          <button
                            type="button"
                            @click="cambiarEstadoEnModal(ver, 'RECHAZADO')"
                            :disabled="ver.estado_revision === 'RECHAZADO' || modalComparativo.procesandoId === ver.id"
                            class="h-5 rounded text-[9.5px] font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                            :class="ver.estado_revision === 'RECHAZADO' ? 'bg-[#FF3B30] text-white' : 'bg-white text-[#C9342C] border border-[#C9342C]/40 hover:bg-[#FDF0EF]'"
                            title="Rechazar versión"
                          >
                            ✕ Rech
                          </button>
                          <button
                            type="button"
                            @click="cambiarEstadoEnModal(ver, 'PENDIENTE')"
                            :disabled="ver.estado_revision === 'PENDIENTE' || modalComparativo.procesandoId === ver.id"
                            class="h-5 rounded text-[9.5px] font-bold transition-all cursor-pointer flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed"
                            :class="ver.estado_revision === 'PENDIENTE' ? 'bg-[#FF9500] text-white' : 'bg-white text-[#B25E00] border border-[#FF9500]/40 hover:bg-[#FFF8E6]'"
                            title="Devolver a Pendiente"
                          >
                            ⏳ Pend
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Fila 1: Nombre Solicitado -->
                    <div
                      class="h-16 rounded-[8px] border p-2.5 text-xs flex items-center justify-between gap-1"
                      :class="ver.cliente && ver.cliente !== modalComparativo.original?.cliente ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3] font-bold' : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                    >
                      <span class="truncate">{{ ver.cliente || 'Sin cambio' }}</span>
                      <span v-if="ver.cliente && ver.cliente !== modalComparativo.original?.cliente" class="text-[9px] px-1 py-0.2 rounded bg-[#0071E3] text-white font-mono shrink-0">
                        Modif.
                      </span>
                    </div>

                    <!-- Fila 2: Dirección Solicitada -->
                    <div
                      class="h-20 rounded-[8px] border p-2.5 text-xs flex flex-col justify-center leading-snug"
                      :class="ver.direccion && ver.direccion !== modalComparativo.original?.direccion ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3] font-semibold' : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                    >
                      <div class="line-clamp-2">{{ ver.direccion || 'Sin cambio' }}</div>
                      <div v-if="ver.direccion && ver.direccion !== modalComparativo.original?.direccion" class="text-[9px] font-mono text-[#0071E3] mt-1 font-bold">
                        • Dirección modificada
                      </div>
                    </div>

                    <!-- Fila 3: Referencia Solicitada -->
                    <div
                      class="h-16 rounded-[8px] border p-2.5 text-xs flex items-center"
                      :class="ver.referencia && ver.referencia !== modalComparativo.original?.referencia ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3] font-medium' : 'bg-white border-[#E5E5EA] text-[#6E6E73] italic'"
                    >
                      <span class="line-clamp-2">{{ ver.referencia || 'Sin cambio' }}</span>
                    </div>

                    <!-- Fila 4: Contacto & Celular -->
                    <div
                      class="h-16 rounded-[8px] border p-2.5 text-xs flex flex-col justify-center"
                      :class="(ver.contacto && ver.contacto !== modalComparativo.original?.contacto) || (ver.celular && ver.celular !== modalComparativo.original?.celular) ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3]' : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                    >
                      <div class="font-medium truncate">{{ ver.contacto || 'Sin cambio' }}</div>
                      <div class="text-[11px] font-mono mt-0.5">Tel: {{ ver.celular || ver.telefono || 'Sin cambio' }}</div>
                    </div>

                    <!-- Fila 5: NIT / Documento -->
                    <div
                      class="h-14 rounded-[8px] border p-2.5 text-xs font-mono flex items-center justify-between"
                      :class="ver.nit && ver.nit !== modalComparativo.original?.nit ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3] font-bold' : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                    >
                      <span>{{ ver.nit || ver.documento_identidad || 'Sin cambio' }}</span>
                      <span v-if="ver.nit && ver.nit !== modalComparativo.original?.nit" class="text-[9px] font-sans px-1 py-0.2 rounded bg-[#0071E3] text-white">
                        Nuevo NIT
                      </span>
                    </div>

                    <!-- Fila 6: Tipo Negocio -->
                    <div
                      class="h-14 rounded-[8px] border p-2.5 text-xs flex items-center"
                      :class="ver.tipo_negocio && ver.tipo_negocio !== modalComparativo.original?.tipo_negocio ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3] font-bold' : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                    >
                      {{ ver.tipo_negocio || 'Sin cambio' }}
                    </div>

                    <!-- Fila 7: Zona / Territorio -->
                    <div
                      class="h-14 rounded-[8px] border p-2.5 text-xs flex items-center"
                      :class="ver.zona && ver.zona !== modalComparativo.original?.zona ? 'bg-[#EBF5FF] border-[#B3D7FF] text-[#0071E3] font-bold' : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                    >
                      {{ ver.zona || 'Sin cambio' }}
                    </div>

                    <!-- Fila 8: Coordenadas GPS y Desviación -->
                    <div
                      class="h-20 rounded-[8px] border p-2.5 text-xs font-mono flex flex-col justify-center"
                      :class="ver.tiene_gps ? 'bg-white border-[#E5E5EA] text-[#1D1D1F]' : 'bg-[#F2F2F7] border-[#E5E5EA] text-[#86868B]'"
                    >
                      <div v-if="ver.tiene_gps">
                        Lat: {{ formatCoord(ver.latitud) }}<br />
                        Lng: {{ formatCoord(ver.longitud) }}
                        <div v-if="ver.distancia_desplazamiento_m !== null" class="mt-0.5 text-[10.5px] font-bold text-[#0071E3]">
                          Desv: {{ ver.distancia_desplazamiento_m }}m
                        </div>
                      </div>
                      <div v-else class="italic">Sin GPS</div>
                    </div>

                    <!-- Fila 9: Fotografía de Evidencia -->
                    <div class="h-32 rounded-[8px] border border-[#E5E5EA] bg-white p-2 flex flex-col items-center justify-center relative overflow-hidden">
                      <div v-if="ver.photo_url" class="w-full h-full relative group">
                        <img
                          :src="ver.photo_url"
                          class="w-full h-full object-cover rounded-[6px] cursor-pointer hover:scale-105 transition-transform"
                          @click="abrirFotoDirecto(ver.photo_url, ver.cliente, ver.id)"
                          alt="Foto"
                        />
                        <button
                          type="button"
                          @click="abrirFotoDirecto(ver.photo_url, ver.cliente, ver.id)"
                          class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[11px] font-bold transition-opacity rounded-[6px] cursor-pointer"
                        >
                          Ver foto ↗
                        </button>
                      </div>
                      <div v-else class="text-center text-[10px] text-[#86868B]">
                        Sin foto registrada
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer del Modal -->
            <div class="px-4 py-2.5 bg-white border-t border-[#E5E5EA] flex items-center justify-between text-xs text-[#86868B] shrink-0">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#D97706]"></span>
                <span>Usa <strong>⭐ Quedarse con esta</strong> para aprobar esa solicitud y rechazar automáticamente las demás solicitudes pendientes.</span>
              </div>
              <button
                type="button"
                @click="cerrarModalComparativo"
                class="px-4 py-1.5 bg-[#1D1D1F] hover:bg-[#333336] text-white rounded-[6px] font-bold text-xs cursor-pointer transition-colors"
              >
                Cerrar matriz
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Toast de Notificaciones -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform translate-y-2 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-2 opacity-0"
      >
        <div
          v-if="toastMensaje"
          class="fixed bottom-6 right-6 z-[99999] bg-[#1D1D1F] text-white px-4 py-2.5 rounded-lg shadow-xl text-xs font-medium flex items-center gap-2 border border-white/10"
        >
          <svg class="w-4 h-4 text-[#248A3D] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ toastMensaje }}</span>
        </div>
      </Transition>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted, Teleport } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AltasEdicionesSubTabs from './Components/AltasEdicionesSubTabs.vue';
import { mapsConfig } from '@/Config/maps';
import { descargarExcelXlsx } from '@/Utils/exportExcel';
import L from 'leaflet';

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!authUser.value?.is_superadmin);
const perms = computed(() => authUser.value?.permissions);

const canCercanos = computed(() => {
  if (isSuperAdmin.value) return true;
  if (!perms.value) return false;
  if (perms.value.is_all_allowed) return true;
  return !!(perms.value.submodules && perms.value.submodules['altas-ediciones.cercanos'] === true);
});

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => [],
  },
  canales: {
    type: Array,
    default: () => [],
  },
  tipos_registro: {
    type: Array,
    default: () => ['ALTA', 'EDICION'],
  },
  estados_revision: {
    type: Array,
    default: () => ['PENDIENTE', 'APROBADO', 'RECHAZADO'],
  },
  tipoFijo: {
    type: String,
    default: null,
  },
  submoduloActivo: {
    type: String,
    default: 'altas',
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

function getTipoColor(tipo) {
  if (!tipo) return 'bg-[#8E8E93]';
  const t = tipo.toUpperCase();
  if (t.includes('ALTA')) return 'bg-[#248A3D]';
  if (t.includes('EDIC') || t.includes('MODIF')) return 'bg-[#0071E3]';
  return 'bg-[#FF9500]';
}

function getEstadoRevisionClass(estado) {
  if (!estado) return 'bg-[#F2F2F7] text-[#6E6E73] border-[#E5E5EA]';
  const e = estado.toUpperCase();
  if (e === 'APROBADO') return 'bg-[#EBF9EF] text-[#248A3D] border-[#248A3D]/20';
  if (e === 'RECHAZADO') return 'bg-[#FDF0EF] text-[#C9342C] border-[#C9342C]/20';
  return 'bg-[#FFF5E5] text-[#B25E00] border-[#B25E00]/20';
}

function formatCoord(val) {
  if (val === null || val === undefined || isNaN(Number(val))) {
    return 'Sin GPS';
  }
  return Number(val).toFixed(5);
}

// Filtros Reactivos
const tipoFijoUpper = props.tipoFijo ? props.tipoFijo.toUpperCase() : null;
function calcularTiposIniciales() {
  if (!tipoFijoUpper) return [...props.tipos_registro];
  if (tipoFijoUpper.includes('ALTA')) {
    const list = props.tipos_registro.filter(t => t.toUpperCase().includes('ALTA'));
    return list.length > 0 ? list : ['ALTA'];
  }
  // Para ediciones / modificaciones: aceptar tanto EDICION como MODIFICACION
  const list = props.tipos_registro.filter(t => t.toUpperCase().includes('EDIC') || t.toUpperCase().includes('MODIF'));
  return list.length > 0 ? list : ['EDICION', 'MODIFICACION'];
}

function calcularCanalesIniciales() {
  const tdb = props.canales.find(c => String(c).trim().toUpperCase() === 'TDB');
  if (tdb) return [tdb];
  return props.canales.length > 0 ? [props.canales[0]] : ['TDB'];
}

function calcularRutasIniciales(canalesIniciales) {
  if (!props.catalogo_rutas) return [];
  const canalesSet = new Set(canalesIniciales.map(c => String(c).trim().toUpperCase()));
  const filtradas = props.catalogo_rutas.filter(r => {
    const rc = r.canal ? String(r.canal).trim().toUpperCase() : '';
    const rr = r.ruta ? String(r.ruta).trim().toUpperCase() : '';
    return canalesSet.has(rc) || Array.from(canalesSet).some(c => rr.startsWith(c));
  });
  return filtradas.map(r => r.ruta);
}

function calcularEstadosIniciales() {
  const pendiente = props.estados_revision.find(e => String(e).trim().toUpperCase() === 'PENDIENTE');
  return pendiente ? [pendiente] : ['PENDIENTE'];
}

const filtroTipos = ref(calcularTiposIniciales());
const filtroCanales = ref(calcularCanalesIniciales());
const filtroEstados = ref(calcularEstadosIniciales());
const filtroRutas = ref(calcularRutasIniciales(filtroCanales.value));
const busquedaTexto = ref('');
const busquedaRuta = ref('');

function seleccionarTodosEstados() {
  filtroEstados.value = [...props.estados_revision];
  cargarDatos();
}

function limpiarEstados() {
  filtroEstados.value = [];
  cargarDatos();
}

const hayFiltrosModificados = computed(() => {
  const inicialCanales = calcularCanalesIniciales();
  const canalesCambiados = filtroCanales.value.length !== inicialCanales.length ||
    !filtroCanales.value.every(c => inicialCanales.includes(c));

  const inicialRutas = calcularRutasIniciales(filtroCanales.value);
  const rutasCambiadas = filtroRutas.value.length !== inicialRutas.length ||
    !filtroRutas.value.every(r => inicialRutas.includes(r));

  const inicialEstados = calcularEstadosIniciales();
  const estadosCambiados = filtroEstados.value.length !== inicialEstados.length ||
    !filtroEstados.value.every(e => inicialEstados.includes(e));

  const busquedaCambiada = busquedaTexto.value.trim().length > 0;
  return canalesCambiados || rutasCambiadas || estadosCambiados || busquedaCambiada;
});

function limpiarFiltrosRapidos() {
  filtroCanales.value = calcularCanalesIniciales();
  filtroRutas.value = calcularRutasIniciales(filtroCanales.value);
  filtroEstados.value = calcularEstadosIniciales();
  busquedaTexto.value = '';
  cargarDatos();
}

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const vistaMovil = ref('mapa');
const vistaPantalla = ref('split'); // 'split' | 'panel' | 'mapa'

function setVistaPantalla(modo) {
  vistaPantalla.value = modo;
  nextTick(() => {
    if (map) {
      map.invalidateSize();
    }
  });
}

watch(vistaMovil, () => {
  nextTick(() => {
    if (map) {
      map.invalidateSize();
    }
  });
});

// Datos
const cargando = ref(false);
const registros = ref([]);
const kpis = ref({
  total: 0,
  altas: 0,
  ediciones: 0,
  pendientes: 0,
  aprobados: 0,
});

const registroActivoId = ref(null);
const registroSeleccionado = ref(null);
const lightboxUrl = ref(null);

const cambiandoEstadoId = ref(null);
const estadoEnProceso = ref(null);
const mensajeEstado = ref(null);
const toastMensaje = ref('');

// Estado Reactivo para Cambio Masivo de Estado
const modalCambioMasivoAbierto = ref(false);
const estadoMasivoSeleccionado = ref('APROBADO');
const motivoCambioMasivo = ref('');
const procesandoCambioMasivo = ref(false);
const errorCambioMasivo = ref(null);

function mostrarToast(msg) {
  toastMensaje.value = msg;
  setTimeout(() => {
    toastMensaje.value = '';
  }, 4500);
}

// Copiado rápido al portapapeles
const campoCopiado = ref(null);
let timeoutCopiado = null;

async function copiarAlPortapapeles(texto, idCampo, label = '') {
  if (texto === null || texto === undefined) return;
  const valor = String(texto).trim();
  if (!valor || valor === 'No registrado' || valor === 'Sin registrar' || valor === 'Sin GPS' || valor === 'Sin NIT/CI' || valor === 'Sin teléfono') return;

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(valor);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = valor;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }

    campoCopiado.value = idCampo || valor;
    if (timeoutCopiado) clearTimeout(timeoutCopiado);
    timeoutCopiado = setTimeout(() => {
      campoCopiado.value = null;
    }, 2000);

    mostrarToast(`Copiado al portapapeles: ${label || idCampo || valor}`);
  } catch (err) {
    console.error('Error al copiar al portapapeles:', err);
  }
}

// Definición oficial de 36 columnas y especificaciones para el ERP
const CABECERAS_ERP_ALTAS = [
  'IdCliente',
  'Codigo',
  'ApellidoPaterno',
  'ApellidoMaterno',
  'Nombres',
  'TipoNegocio',
  'TipoPago',
  'DiasPago',
  'TopeCredito',
  'Latitud',
  'Longitud',
  'Activo',
  'Direccion',
  'UV',
  'Referencia',
  'Zona',
  'Provincia',
  'Mercado',
  'DocumentoIdentidad',
  'Expedido',
  'Telefono',
  'Celular',
  'Email',
  'PaginaWeb',
  'RazonSocial',
  'Cluster',
  'TipoDocumento',
  'TipoNit',
  'NombreFactura',
  'Nit',
  'Complemento',
  'PrecioLista',
  'Ruta',
  'Dia',
  'SecuenciaVenta',
  'SecuenciaDistribuidor',
];

/**
 * Capitaliza un texto colocando la primera letra de cada palabra en mayúscula y el resto en minúscula.
 */
function capitalizarTexto(str) {
  if (!str) return '';
  return String(str)
    .trim()
    .toLowerCase()
    .split(/\s+/)
    .map(w => w ? (w.charAt(0).toUpperCase() + w.slice(1)) : '')
    .join(' ');
}

function extraerApellidoPaterno(nombreCompleto) {
  if (!nombreCompleto) return '';
  const p = nombreCompleto.trim().split(/\s+/);
  if (p.length >= 3) return p[p.length - 2];
  if (p.length === 2) return p[1];
  return '';
}

function extraerApellidoMaterno(nombreCompleto) {
  if (!nombreCompleto) return '';
  const p = nombreCompleto.trim().split(/\s+/);
  if (p.length >= 3) return p[p.length - 1];
  return '';
}

function extraerNombres(nombreCompleto) {
  if (!nombreCompleto) return '';
  const p = nombreCompleto.trim().split(/\s+/);
  if (p.length >= 3) return p.slice(0, p.length - 2).join(' ');
  if (p.length === 2) return p[0];
  return p[0] || '';
}

function obtenerDiaDescarga() {
  const dias = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
  return dias[new Date().getDay()] || 'Lunes';
}

function generarFilaErp(r, diaDescarga = null) {
  const tipoStr = String(r.tipo_registro || '').toUpperCase();
  const esAlta = tipoStr.includes('ALTA') || (!r.cliente_id && !r.es_edicion);
  const orig = r.original || {};

  // 1. IdCliente y Codigo:
  // Altas: IdCliente = 0, Codigo = ''
  // Ediciones: IdCliente = cliente_id, Codigo = cliente_id
  const idCliente = esAlta ? 0 : (r.cliente_id || orig.cliente_id || 0);
  const codigo = esAlta ? '' : String(r.cliente_id || orig.cliente_id || '');

  // 2. Nombres y Apellidos (formato con primera letra en mayúscula y resto en minúscula)
  const clienteNombre = (r.cliente || orig.cliente || '').trim();
  const pPaterno = capitalizarTexto((r.apellido_paterno || '').trim() || extraerApellidoPaterno(clienteNombre));
  const pMaterno = capitalizarTexto((r.apellido_materno || '').trim() || extraerApellidoMaterno(clienteNombre));
  const pNombres = capitalizarTexto((r.nombres || '').trim() || extraerNombres(clienteNombre));

  // 3. TipoNegocio
  const tipoNegocio = r.tipo_negocio || orig.tipo_negocio || '';

  // 4. Coordenadas
  const latStr = (r.latitud !== null && r.latitud !== undefined && r.latitud !== '')
    ? String(r.latitud)
    : (orig.latitud !== null && orig.latitud !== undefined && orig.latitud !== '' ? String(orig.latitud) : '');
  const lngStr = (r.longitud !== null && r.longitud !== undefined && r.longitud !== '')
    ? String(r.longitud)
    : (orig.longitud !== null && orig.longitud !== undefined && orig.longitud !== '' ? String(orig.longitud) : '');

  // 5. Documentos e Identidad
  const docIdentidad = (r.documento_identidad || r.nit || orig.ci || orig.nit || '').trim();
  const nitVal = (r.nit || r.documento_identidad || orig.nit || orig.ci || '').trim();
  const razonSocialVal = capitalizarTexto((r.razon_social || r.nombre_factura || clienteNombre || orig.nombre_factura || '').trim());
  const nombreFacturaVal = capitalizarTexto((r.nombre_factura || r.razon_social || clienteNombre || orig.nombre_factura || '').trim());

  // 6. Día de Ruteo:
  // Para Altas: día de descarga
  // Para Ediciones: preservar el día de ruteo original (orig.dia o orig.dia_norm o r.dia)
  const diaVal = esAlta
    ? (diaDescarga || obtenerDiaDescarga())
    : (orig.dia || orig.dia_norm || r.dia || diaDescarga || obtenerDiaDescarga());

  // 7. TipoPago y PrecioLista oficiales
  const tipoPago = 'Contado';
  const precioLista = 'TDB';

  // 8. Activo y Ubicaciones
  const activoVal = 'SI';
  const direccionVal = r.direccion || orig.direccion || '';
  const referenciaVal = r.referencia || orig.referencia || '';
  const zonaVal = r.zona || orig.zona || orig.territorio || orig.zona_venta || '';

  // 9. Contacto y Comunicaciones
  const telefonoVal = r.telefono || orig.telefono || '';
  const celularVal = r.celular || orig.celular || '';
  const expedidoVal = r.expedido || orig.expedido || '';
  const complementoVal = r.complemento || '';
  const tipoDocVal = r.tipo_documento || 'CI';
  const tipoNitVal = r.tipo_nit || 'CI';
  const rutaVal = r.ruta || orig.ruta || '';

  // 10. Campos preservados del padrón de ruteo original para ediciones (DiasPago, TopeCredito y Cluster en blanco)
  const diasPago = '';
  const topeCredito = '';
  const uvVal = !esAlta ? (orig.uv || '') : '';
  const provinciaVal = !esAlta ? (orig.provincia || '') : '';
  const mercadoVal = !esAlta ? (orig.mercado || '') : '';
  const emailVal = !esAlta ? (orig.email || '') : '';
  const paginaWebVal = !esAlta ? (orig.pagina_web || '') : '';
  const clusterVal = '';
  const secVenta = !esAlta && orig.secuencia_venta !== null && orig.secuencia_venta !== undefined ? String(orig.secuencia_venta) : '';
  const secDistribuidor = !esAlta && orig.secuencia_distribuidor !== null && orig.secuencia_distribuidor !== undefined ? String(orig.secuencia_distribuidor) : '';

  return [
    idCliente,          // 1. IdCliente (0: Nuevos, cliente_id para Ediciones)
    codigo,             // 2. Codigo (Vacio para altas, cliente_id para Ediciones)
    pPaterno,           // 3. ApellidoPaterno
    pMaterno,           // 4. ApellidoMaterno
    pNombres,           // 5. Nombres
    tipoNegocio,        // 6. TipoNegocio
    tipoPago,           // 7. TipoPago ('Contado')
    diasPago,           // 8. DiasPago
    topeCredito,        // 9. TopeCredito
    latStr,             // 10. Latitud
    lngStr,             // 11. Longitud
    activoVal,          // 12. Activo ('SI')
    direccionVal,       // 13. Direccion
    uvVal,              // 14. UV
    referenciaVal,      // 15. Referencia
    zonaVal,            // 16. Zona
    provinciaVal,       // 17. Provincia
    mercadoVal,         // 18. Mercado
    docIdentidad,       // 19. DocumentoIdentidad
    expedidoVal,        // 20. Expedido
    telefonoVal,        // 21. Telefono
    celularVal,         // 22. Celular
    emailVal,           // 23. Email
    paginaWebVal,       // 24. PaginaWeb
    razonSocialVal,     // 25. RazonSocial
    clusterVal,         // 26. Cluster
    tipoDocVal,         // 27. TipoDocumento
    tipoNitVal,         // 28. TipoNit
    nombreFacturaVal,   // 29. NombreFactura
    nitVal,             // 30. Nit
    complementoVal,     // 31. Complemento
    precioLista,        // 32. PrecioLista ('TDB')
    rutaVal,            // 33. Ruta
    diaVal,             // 34. Dia (Para altas: día descarga; para ediciones: día original)
    secVenta,           // 35. SecuenciaVenta
    secDistribuidor,    // 36. SecuenciaDistribuidor
  ];
}

const generarFilaErpAlta = generarFilaErp;

function descargarXlsxAltaIndividual(r) {
  if (!r) return;
  const tipoStr = String(r.tipo_registro || '').toUpperCase();
  const esAlta = tipoStr.includes('ALTA') || (!r.cliente_id && !r.es_edicion);
  const rows = [
    CABECERAS_ERP_ALTAS,
    generarFilaErp(r),
  ];
  const idRef = esAlta ? r.id : (r.cliente_id || r.id);
  const nombreSeguro = (r.cliente || `${esAlta ? 'alta' : 'edicion'}_${idRef}`).replace(/[^a-zA-Z0-9_-]/g, '_');
  const nombreArchivo = `${esAlta ? 'alta' : 'edicion'}_${idRef}_${nombreSeguro}.xlsx`;
  const nombreHoja = esAlta ? 'Alta ERP' : 'Edicion ERP';
  descargarExcelXlsx(rows, nombreArchivo, nombreHoja);
  mostrarToast(`${esAlta ? 'Alta' : 'Edición'} #${idRef} descargada en formato ERP (.xlsx).`);
}

async function copiarFichaCompleta(registro) {
  if (!registro) return;
  const fila = generarFilaErp(registro);
  const texto = CABECERAS_ERP_ALTAS.map((col, idx) => `${col}: ${fila[idx] ?? ''}`).join('\n');
  await copiarAlPortapapeles(texto, 'todo', 'Ficha Completa');
}

async function copiarFilaExcel(registro) {
  if (!registro) return;
  const fila = generarFilaErp(registro);
  const tsv = fila.join('\t');
  await copiarAlPortapapeles(tsv, 'excel', 'Fila ERP (36 columnas tabuladas)');
}

const cargandoCercanos = ref(false);
const datosCercanos = ref(null);
const mostrarListaCercanos = ref(true);
const mostrarTablaColumnas = ref(true);

const filtroCercanosTipo = ref('todos'); // 'todos' | 'alertas' | 'fotos'
const clienteCercanoDetalle = ref(null);
const tabModalCercano = ref('comparativa'); // 'comparativa' | 'ficha' | 'fotos'
const cargandoFotosModalCercano = ref(false);
const fotosModalCercano = ref([]);

const modalFotos = reactive({
  abierto: false,
  open: false,
  cargando: false,
  errorMsg: null,
  clienteNombre: '',
  clienteId: null,
  ruta: '',
  fotos: [],
  indice: 0,
  isZoomed: false,
});

const fotoActualModal = computed(() => {
  if (!modalFotos.fotos || modalFotos.fotos.length === 0) return null;
  return modalFotos.fotos[modalFotos.indice] || modalFotos.fotos[0] || null;
});

const clientesCercanosFiltrados = computed(() => {
  if (!datosCercanos.value || !Array.isArray(datosCercanos.value.clientes_cercanos)) return [];
  const list = datosCercanos.value.clientes_cercanos;
  if (filtroCercanosTipo.value === 'alertas') {
    return list.filter(x => x.tiene_duplicidad);
  }
  if (filtroCercanosTipo.value === 'fotos') {
    return list.filter(x => (x.fotos_count > 0 || x.ultima_foto || x.photo_url));
  }
  return list;
});

function isDiferente(valA, valB) {
  const normA = (valA === null || valA === undefined) ? '' : String(valA).trim().toLowerCase();
  const normB = (valB === null || valB === undefined) ? '' : String(valB).trim().toLowerCase();
  return normA !== normB && (normA !== '' || normB !== '');
}

let map = null;
let markersLayer = null;
let auxLinesLayer = null;
const markersMap = new Map();

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
  if (filtroCanales.value.length === 0) return [];
  if (filtroCanales.value.length === props.canales.length) return props.catalogo_rutas;
  const canalesSet = new Set(filtroCanales.value.map(c => String(c).trim().toUpperCase()));
  return props.catalogo_rutas.filter(r => {
    const rc = r.canal ? String(r.canal).trim().toUpperCase() : '';
    const rr = r.ruta ? String(r.ruta).trim().toUpperCase() : '';
    return canalesSet.has(rc) || Array.from(canalesSet).some(c => rr.startsWith(c));
  });
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

const labelTipos = computed(() => {
  if (filtroTipos.value.length === props.tipos_registro.length) return 'Todos';
  if (filtroTipos.value.length === 0) return 'Ninguno';
  return `${filtroTipos.value.length} sel.`;
});

const labelEstados = computed(() => {
  if (filtroEstados.value.length === props.estados_revision.length) return 'Todos';
  if (filtroEstados.value.length === 0) return 'Ninguno';
  if (filtroEstados.value.length === 1) return filtroEstados.value[0];
  return `${filtroEstados.value.length} sel.`;
});

const labelCanales = computed(() => {
  if (filtroCanales.value.length === props.canales.length) return 'Todos';
  if (filtroCanales.value.length === 0) return 'Ninguno';
  if (filtroCanales.value.length === 1) return filtroCanales.value[0];
  return `${filtroCanales.value.length} sel.`;
});

const labelRutas = computed(() => {
  if (filtroRutas.value.length === rutasDisponibles.value.length) return 'Todas';
  if (filtroRutas.value.length === 0) return 'Ninguna';
  if (filtroRutas.value.length === 1) return filtroRutas.value[0];
  return `${filtroRutas.value.length} sel.`;
});

const registrosFiltrados = computed(() => {
  let list = registros.value;
  if (busquedaTexto.value.trim()) {
    const q = busquedaTexto.value.toLowerCase();
    list = list.filter(r =>
      (r.cliente && r.cliente.toLowerCase().includes(q)) ||
      (r.cliente_id && String(r.cliente_id).includes(q)) ||
      (r.id && String(r.id).includes(q)) ||
      (r.ruta && r.ruta.toLowerCase().includes(q)) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(q)) ||
      (r.usuario && r.usuario.toLowerCase().includes(q)) ||
      (r.user_id && String(r.user_id).includes(q)) ||
      (r.direccion && r.direccion.toLowerCase().includes(q)) ||
      (r.referencia && r.referencia.toLowerCase().includes(q)) ||
      (r.contacto && r.contacto.toLowerCase().includes(q)) ||
      (r.telefono && String(r.telefono).includes(q)) ||
      (r.celular && String(r.celular).includes(q)) ||
      (r.nombre_factura && r.nombre_factura.toLowerCase().includes(q)) ||
      (r.zona && r.zona.toLowerCase().includes(q)) ||
      (r.tipo_negocio && r.tipo_negocio.toLowerCase().includes(q)) ||
      (r.lista_precios && r.lista_precios.toLowerCase().includes(q)) ||
      (r.nit && String(r.nit).includes(q))
    );
  }

  // Ordenar: primero los clientes pendientes con doble/múltiple modificación
  return [...list].sort((a, b) => {
    const aDoble = (a.tiene_doble_modificacion && (a.estado_revision || '').toUpperCase() === 'PENDIENTE') ? 1 : 0;
    const bDoble = (b.tiene_doble_modificacion && (b.estado_revision || '').toUpperCase() === 'PENDIENTE') ? 1 : 0;
    if (aDoble !== bDoble) {
      return bDoble - aDoble; // los de doble modificación arriba
    }
    // Si ambos son doble modificación, agrupar por cliente_id para verlos juntos
    if (aDoble === 1 && bDoble === 1) {
      if (a.cliente_id !== b.cliente_id) {
        return String(a.cliente_id || '').localeCompare(String(b.cliente_id || ''));
      }
      return (b.id || 0) - (a.id || 0);
    }
    return (b.id || 0) - (a.id || 0);
  });
});

// Selector de Modo de Listado en Panel Izquierdo: 'individual' | 'agrupado'
const modoVistaLista = ref('individual');
const procesandoGrupoKey = ref(null);

// Agrupamiento por cliente para resolver modificaciones repetidas
const gruposClientes = computed(() => {
  const map = new Map();

  registrosFiltrados.value.forEach(r => {
    const key = r.cliente_id ? `id_${r.cliente_id}` : `nom_${(r.cliente || r.id)}`;
    if (!map.has(key)) {
      map.set(key, {
        key,
        cliente_id: r.cliente_id,
        cliente: r.cliente || 'Sin nombre',
        canal: r.canal || 'PRT',
        ruta: r.ruta || 'N/D',
        items: [],
      });
    }
    map.get(key).items.push(r);
  });

  return Array.from(map.values()).map(g => {
    // Ordenar items de más reciente a más antiguo
    g.items.sort((a, b) => (b.id || 0) - (a.id || 0));
    g.total = g.items.length;
    g.total_pendientes = g.items.filter(x => (x.estado_revision || '').toUpperCase() === 'PENDIENTE').length;
    g.total_aprobados = g.items.filter(x => (x.estado_revision || '').toUpperCase() === 'APROBADO').length;
    g.total_rechazados = g.items.filter(x => (x.estado_revision || '').toUpperCase() === 'RECHAZADO').length;
    return g;
  });
});

// Clientes con 2 o más solicitudes bajo los filtros activos
const gruposRepetidos = computed(() => {
  return gruposClientes.value
    .filter(g => g.total >= 2)
    .sort((a, b) => {
      // Priorizar clientes con 2 o más solicitudes pendientes
      const aPend = a.total_pendientes >= 2 ? 2 : (a.total_pendientes === 1 ? 1 : 0);
      const bPend = b.total_pendientes >= 2 ? 2 : (b.total_pendientes === 1 ? 1 : 0);
      if (aPend !== bPend) return bPend - aPend;
      return b.total - a.total;
    });
});

const clientesConRepetidosCount = computed(() => gruposRepetidos.value.length);

// Ediciones filtradas para exportación comparativa y exportación ERP contra pan_ruteo
const edicionesFiltradas = computed(() => {
  let lista = registrosFiltrados.value.filter(r => {
    const tipo = (r.tipo_registro || '').toUpperCase();
    return tipo.includes('EDIC') || tipo.includes('MODIF');
  });

  if (lista.length === 0 && !busquedaTexto.value.trim()) {
    lista = registros.value.filter(r => {
      const tipo = (r.tipo_registro || '').toUpperCase();
      return tipo.includes('EDIC') || tipo.includes('MODIF');
    });
  }

  if (lista.length === 0 && props.submoduloActivo === 'modificaciones') {
    lista = registrosFiltrados.value.filter(r => {
      const tipo = (r.tipo_registro || '').toUpperCase();
      return !tipo.includes('ALTA');
    });
  }

  return lista;
});

const conteoEdiciones = computed(() => edicionesFiltradas.value.length);

// Descarga oficial ERP de Ediciones / Modificaciones (36 Columnas)
function exportarEdicionesExcel() {
  const lista = edicionesFiltradas.value;

  if (lista.length === 0) {
    alert('No se encontraron solicitudes de edición con los filtros actuales para descargar.');
    return;
  }

  const rows = [
    CABECERAS_ERP_ALTAS,
    ...lista.map(r => generarFilaErp(r)),
  ];

  const fechaHoy = new Date().toISOString().slice(0, 10);
  const nombreArchivo = `ediciones_erp_${fechaHoy}.xlsx`;

  descargarExcelXlsx(rows, nombreArchivo, 'Ediciones ERP');
  mostrarToast(`Se descargaron ${lista.length} edición(es) en formato oficial ERP (.xlsx).`);
}

// 1. Descarga Comparativa: IdCliente, Codigo, Cliente Anterior, Cliente Nuevo, etc.
function exportarComparativoExcel() {
  const lista = edicionesFiltradas.value;

  if (lista.length === 0) {
    alert('No se encontraron solicitudes de edición con los filtros actuales para descargar.');
    return;
  }

  const headers = [
    'IdCliente',
    'Codigo',
    'Cliente Anterior',
    'Cliente Nuevo',
    'Tipo Negocio Anterior',
    'Tipo Negocio Nuevo',
    'Dirección Anterior',
    'Dirección Nueva',
    'Referencia Anterior',
    'Referencia Nueva',
    'Contacto Anterior',
    'Contacto Nuevo',
    'Teléfono Anterior',
    'Teléfono Nuevo',
    'Celular Anterior',
    'Celular Nuevo',
    'Nombre Factura Anterior',
    'Nombre Factura Nuevo',
    'NIT Anterior',
    'NIT Nuevo',
    'Zona Anterior',
    'Zona Nueva',
    'Precio Lista Anterior',
    'Precio Lista Nuevo',
    'Ruta Anterior',
    'Ruta Nueva',
    'Latitud Anterior',
    'Latitud Nueva',
    'Longitud Anterior',
    'Longitud Nueva',
  ];

  const rows = [headers];

  lista.forEach(r => {
    const orig = r.original || {};

    const row = [
      r.cliente_id || orig.cliente_id || '',
      orig.nodo_id || r.cliente_id || orig.cliente_id || '',
      capitalizarTexto(orig.cliente || ''),
      capitalizarTexto(r.cliente || ''),
      orig.tipo_negocio || '',
      r.tipo_negocio || '',
      orig.direccion || '',
      r.direccion || '',
      orig.referencia || '',
      r.referencia || '',
      capitalizarTexto(orig.contacto || ''),
      capitalizarTexto(r.contacto || ''),
      orig.telefono || '',
      r.telefono || '',
      orig.celular || '',
      r.celular || '',
      capitalizarTexto(orig.nombre_factura || ''),
      capitalizarTexto(r.nombre_factura || ''),
      orig.nit || '',
      r.nit || '',
      orig.territorio || orig.zona_venta || orig.zona || '',
      r.zona || '',
      orig.precio || orig.lista_precios || '',
      r.lista_precios || '',
      orig.ruta || '',
      r.ruta || '',
      orig.latitud !== null && orig.latitud !== undefined ? orig.latitud : '',
      r.latitud !== null && r.latitud !== undefined ? r.latitud : '',
      orig.longitud !== null && orig.longitud !== undefined ? orig.longitud : '',
      r.longitud !== null && r.longitud !== undefined ? r.longitud : '',
    ];

    rows.push(row);
  });

  const fechaHoy = new Date().toISOString().slice(0, 10);
  const nombreArchivo = `ediciones_comparativo_${fechaHoy}.xlsx`;

  descargarExcelXlsx(rows, nombreArchivo, 'Comparativo Ediciones');
  mostrarToast(`Se descargaron ${lista.length} registro(s) de comparativo en Excel (.xlsx).`);
}

// 2. Descarga Padrón Actualizado: Totalidad de clientes de las rutas con correcciones reemplazadas
const descargandoPadron = ref(false);

async function exportarPadronActualizado() {
  if (descargandoPadron.value) return;
  descargandoPadron.value = true;

  try {
    const url = typeof route === 'function' ? route('supervisor.altas-ediciones.exportar-padron') : '/supervisor/altas-ediciones/exportar-padron';
    const canalFiltrado = filtroCanales.value.length < props.canales.length;
    const res = await axios.post(url, {
      canales: filtroCanales.value.length === props.canales.length ? [] : filtroCanales.value,
      rutas: filtroRutas.value.length === rutasDisponibles.value.length ? [] : filtroRutas.value,
      tipos: filtroTipos.value,
      estados: filtroEstados.value.length === props.estados_revision.length ? [] : filtroEstados.value,
      solo_modificados: canalFiltrado,
    }, {
      responseType: 'blob',
    });

    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const fechaHoy = new Date().toISOString().slice(0, 10);
    const nombreArchivo = `padron_actualizado_ruteo_${fechaHoy}.xlsx`;

    const link = document.createElement('a');
    link.href = window.URL.createObjectURL(blob);
    link.download = nombreArchivo;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(link.href);

    mostrarToast(canalFiltrado ? 'Padrón de clientes modificados del canal descargado en Excel (.xlsx).' : 'Padrón actualizado descargado en Excel (.xlsx) con campos modificados en negrilla.');
  } catch (err) {
    console.error('Error al exportar padrón actualizado:', err);
    let msg = 'Error de conexión o servidor.';
    if (err.response?.data instanceof Blob) {
      try {
        const text = await err.response.data.text();
        const json = JSON.parse(text);
        msg = json.message || msg;
      } catch (e) {}
    } else if (err.response?.data?.message) {
      msg = err.response.data.message;
    } else if (err.message) {
      msg = err.message;
    }
    alert(`No se pudo generar el archivo del padrón:\n${msg}`);
  } finally {
    descargandoPadron.value = false;
  }
}

// 3. Descarga de Nuevas Altas en Excel (Formato Oficial ERP 36 Columnas)
function exportarAltasExcel() {
  const lista = registrosFiltrados.value.filter(r => {
    const t = String(r.tipo_registro || '').toUpperCase();
    return t.includes('ALTA');
  });

  const finalLista = lista.length > 0 ? lista : registrosFiltrados.value;

  if (finalLista.length === 0) {
    alert('No se encontraron solicitudes de altas para descargar.');
    return;
  }

  const rows = [
    CABECERAS_ERP_ALTAS,
    ...finalLista.map(r => generarFilaErpAlta(r)),
  ];

  const fechaHoy = new Date().toISOString().slice(0, 10);
  const nombreArchivo = `altas_erp_${fechaHoy}.xlsx`;

  descargarExcelXlsx(rows, nombreArchivo, 'Altas ERP');
  mostrarToast(`Se descargaron ${finalLista.length} alta(s) en formato ERP (.xlsx).`);
}

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
  const restantes = filtroRutas.value.filter(r => rutasValidas.has(r));
  filtroRutas.value = restantes.length > 0 ? restantes : rutasDisponibles.value.map(r => r.ruta);
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

function abrirLightbox(url) {
  lightboxUrl.value = url;
  if (url) {
    abrirFotosRegistroActual();
  }
}

function irASolicitud(id) {
  const target = registros.value.find(r => r.id === id);
  if (target) {
    abrirExpediente(target);
  } else {
    mostrarToast(`La solicitud #${id} no se encuentra en el listado cargado con los filtros actuales.`);
  }
}

function actualizarDobleModificacionCliente(clienteId) {
  if (!clienteId) return;
  const pendientesDelCliente = registros.value.filter(
    r => String(r.cliente_id) === String(clienteId) && (r.estado_revision || '').toUpperCase() === 'PENDIENTE'
  );
  const total = pendientesDelCliente.length;
  const esDoble = total > 1;
  const todosIds = pendientesDelCliente.map(r => r.id);

  registros.value.forEach(r => {
    if (String(r.cliente_id) === String(clienteId)) {
      r.total_pendientes_cliente = total;
      r.tiene_doble_modificacion = esDoble && (r.estado_revision || '').toUpperCase() === 'PENDIENTE';
      r.otras_solicitudes_ids = todosIds.filter(id => id !== r.id);
    }
  });

  if (registroSeleccionado.value && String(registroSeleccionado.value.cliente_id) === String(clienteId)) {
    registroSeleccionado.value.total_pendientes_cliente = total;
    registroSeleccionado.value.tiene_doble_modificacion = esDoble && (registroSeleccionado.value.estado_revision || '').toUpperCase() === 'PENDIENTE';
    registroSeleccionado.value.otras_solicitudes_ids = todosIds.filter(id => id !== registroSeleccionado.value.id);
  }
}

async function cambiarEstadoRevision(registro, nuevoEstado) {
  if (!registro || !registro.id) return;

  cambiandoEstadoId.value = registro.id;
  estadoEnProceso.value = nuevoEstado;
  mensajeEstado.value = null;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.altas-ediciones.cambiar-estado', registro.id)
      : `/supervisor/altas-ediciones/${registro.id}/estado`;

    const res = await axios.post(url, {
      estado: nuevoEstado,
    });

    if (res.data && res.data.success) {
      registro.estado_revision = res.data.estado_revision;
      registro.revisado_at = res.data.revisado_at;
      if (res.data.revisor_id !== undefined) {
        registro.revisor_id = res.data.revisor_id;
        registro.revisor_nombre = res.data.revisor_nombre;
      }

      const itemEnLista = registros.value.find(r => r.id === registro.id);
      if (itemEnLista) {
        itemEnLista.estado_revision = res.data.estado_revision;
        itemEnLista.revisado_at = res.data.revisado_at;
        if (res.data.revisor_id !== undefined) {
          itemEnLista.revisor_id = res.data.revisor_id;
          itemEnLista.revisor_nombre = res.data.revisor_nombre;
        }
      }

      recalcularKpis();
      actualizarDobleModificacionCliente(registro.cliente_id);

      mensajeEstado.value = {
        tipo: 'exito',
        texto: res.data.message || `Estado actualizado a ${nuevoEstado} correctamente.`,
      };

      setTimeout(() => {
        mensajeEstado.value = null;
      }, 5000);
    }
  } catch (err) {
    console.error('Error al cambiar estado de revisión:', err);
    const msg = err.response?.data?.message || 'Error de comunicación al actualizar el estado.';
    mensajeEstado.value = {
      tipo: 'error',
      texto: msg,
    };
  } finally {
    cambiandoEstadoId.value = null;
    estadoEnProceso.value = null;
  }
}

// =========================================================================
// CAMBIO MASIVO DE ESTADO DE REVISIÓN
// =========================================================================
function abrirModalCambioMasivo() {
  if (registrosFiltrados.value.length === 0) {
    alert('No se encontraron solicitudes con los filtros actuales para actualizar.');
    return;
  }
  estadoMasivoSeleccionado.value = 'APROBADO';
  motivoCambioMasivo.value = '';
  errorCambioMasivo.value = null;
  modalCambioMasivoAbierto.value = true;
}

function cerrarModalCambioMasivo() {
  if (procesandoCambioMasivo.value) return;
  modalCambioMasivoAbierto.value = false;
  errorCambioMasivo.value = null;
}

async function confirmarCambioMasivo() {
  const lista = registrosFiltrados.value;
  const ids = lista.map(r => r.id).filter(id => typeof id === 'number' && id > 0);

  if (ids.length === 0) {
    alert('No se encontraron IDs válidos en las solicitudes filtradas.');
    return;
  }

  procesandoCambioMasivo.value = true;
  errorCambioMasivo.value = null;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.altas-ediciones.cambiar-estado-masivo')
      : '/supervisor/altas-ediciones/cambiar-estado-masivo';

    const res = await axios.post(url, {
      ids,
      estado: estadoMasivoSeleccionado.value,
      motivo: motivoCambioMasivo.value || null,
    });

    if (res.data && res.data.success) {
      const nuevoEstado = res.data.estado_revision;
      const revisadoAt = res.data.revisado_at;
      const revisorId = res.data.revisor_id;
      const revisorNombre = res.data.revisor_nombre;
      const idsSet = new Set(res.data.ids || ids);

      // Actualizar reactivamente cada registro en el arreglo local
      registros.value.forEach(r => {
        if (idsSet.has(r.id)) {
          r.estado_revision = nuevoEstado;
          r.revisado_at = revisadoAt;
          if (revisorId !== undefined) {
            r.revisor_id = revisorId;
            r.revisor_nombre = revisorNombre;
          }
        }
      });

      // Si el registro actualmente abierto en el panel de detalle fue afectado, sincronizarlo
      if (registroSeleccionado.value && idsSet.has(registroSeleccionado.value.id)) {
        registroSeleccionado.value.estado_revision = nuevoEstado;
        registroSeleccionado.value.revisado_at = revisadoAt;
        if (revisorId !== undefined) {
          registroSeleccionado.value.revisor_id = revisorId;
          registroSeleccionado.value.revisor_nombre = revisorNombre;
        }
      }

      recalcularKpis();
      const clientesAfectados = new Set(lista.filter(r => idsSet.has(r.id)).map(r => r.cliente_id).filter(Boolean));
      clientesAfectados.forEach(cid => actualizarDobleModificacionCliente(cid));

      modalCambioMasivoAbierto.value = false;
      mostrarToast(res.data.message || `Se actualizaron ${res.data.count} registro(s) a estado ${nuevoEstado}.`);
    } else {
      errorCambioMasivo.value = res.data?.message || 'No fue posible completar la actualización masiva.';
    }
  } catch (err) {
    console.error('Error al cambiar masivamente el estado:', err);
    errorCambioMasivo.value = err.response?.data?.message || 'Error de red o comunicación al procesar el cambio masivo.';
  } finally {
    procesandoCambioMasivo.value = false;
  }
}

function recalcularKpis() {
  let total = registros.value.length;
  let altas = 0;
  let ediciones = 0;
  let pendientes = 0;
  let aprobados = 0;

  registros.value.forEach(r => {
    const t = (r.tipo_registro || '').toUpperCase();
    if (t.includes('ALTA')) altas++;
    else if (t.includes('EDIC')) ediciones++;

    const est = (r.estado_revision || '').toUpperCase();
    if (est === 'PENDIENTE') pendientes++;
    else if (est === 'APROBADO') aprobados++;
  });

  kpis.value = { total, altas, ediciones, pendientes, aprobados };
}

// =========================================================================
// MATRIZ COMPARATIVA Y RESOLUCIÓN DE MODIFICACIONES REPETIDAS
// =========================================================================
const modalComparativo = reactive({
  abierto: false,
  cargando: false,
  clienteId: null,
  clienteNombre: '',
  ruta: '',
  canal: '',
  original: null,
  versiones: [],
  errorMsg: null,
  procesandoId: null,
  indiceRepetidoActual: 0,
});

async function abrirComparativoCliente(clienteId, clienteNombreOpt = null, rutaOpt = null) {
  if (!clienteId) {
    mostrarToast('Este registro no posee código de cliente (es alta nueva sin padrón previo).');
    return;
  }

  // Buscar índice en gruposRepetidos para permitir navegación
  const idx = gruposRepetidos.value.findIndex(g => String(g.cliente_id) === String(clienteId));
  modalComparativo.indiceRepetidoActual = idx >= 0 ? idx : 0;

  const regMatch = registros.value.find(r => String(r.cliente_id) === String(clienteId));
  modalComparativo.clienteId = clienteId;
  modalComparativo.clienteNombre = clienteNombreOpt || regMatch?.cliente || `Cliente #${clienteId}`;
  modalComparativo.ruta = rutaOpt || regMatch?.ruta || '';
  modalComparativo.canal = regMatch?.canal || '';
  modalComparativo.abierto = true;
  modalComparativo.cargando = true;
  modalComparativo.errorMsg = null;
  modalComparativo.versiones = [];
  modalComparativo.original = null;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.altas-ediciones.cliente-versiones', { clienteId })
      : `/supervisor/altas-ediciones/cliente/${clienteId}/versiones`;

    const res = await axios.get(url);
    if (res.data && res.data.success) {
      modalComparativo.original = res.data.original || null;
      modalComparativo.versiones = res.data.versiones || [];
      if (res.data.versiones?.length > 0 && !modalComparativo.clienteNombre) {
        modalComparativo.clienteNombre = res.data.versiones[0].cliente;
      }
    } else {
      modalComparativo.errorMsg = res.data?.message || 'No fue posible cargar el historial de versiones.';
    }
  } catch (err) {
    console.error('Error al cargar versiones de cliente:', err);
    modalComparativo.errorMsg = err.response?.data?.message || 'Error de red al consultar versiones.';
  } finally {
    modalComparativo.cargando = false;
  }
}

function cerrarModalComparativo() {
  modalComparativo.abierto = false;
  modalComparativo.cargando = false;
  modalComparativo.procesandoId = null;
  modalComparativo.versiones = [];
  modalComparativo.original = null;
}

function abrirComparativoPrimerRepetido() {
  if (gruposRepetidos.value.length === 0) {
    mostrarToast('No hay clientes con modificaciones repetidas en la vista actual.');
    return;
  }
  const primer = gruposRepetidos.value[0];
  abrirComparativoCliente(primer.cliente_id, primer.cliente, primer.ruta);
}

function navegarComparativo(direccion) {
  const nuevoIdx = modalComparativo.indiceRepetidoActual + direccion;
  if (nuevoIdx >= 0 && nuevoIdx < gruposRepetidos.value.length) {
    const siguienteGrupo = gruposRepetidos.value[nuevoIdx];
    abrirComparativoCliente(siguienteGrupo.cliente_id, siguienteGrupo.cliente, siguienteGrupo.ruta);
  }
}

async function quedarseConVersion(version) {
  if (!version || !version.id) return;

  const cid = version.cliente_id;
  // Identificar todas las demás solicitudes de este cliente que estén pendientes o aprobadas
  const otrasVersiones = modalComparativo.versiones.filter(v => v.id !== version.id && (v.estado_revision || '').toUpperCase() !== 'RECHAZADO');
  const rechazarIds = otrasVersiones.map(v => v.id);

  modalComparativo.procesandoId = version.id;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.altas-ediciones.resolver-duplicados')
      : '/supervisor/altas-ediciones/resolver-duplicados';

    const res = await axios.post(url, {
      aprobar_id: version.id,
      rechazar_ids: rechazarIds,
      motivo: `Aprobada versión #${version.id} y rechazadas las demás en matriz comparativa`,
    });

    if (res.data && res.data.success) {
      // 1. Actualizar estado local en modalComparativo.versiones
      modalComparativo.versiones.forEach(v => {
        if (v.id === version.id) {
          v.estado_revision = 'APROBADO';
          v.revisado_at = res.data.revisado_at;
          v.revisor_id = res.data.revisor_id;
          v.revisor_nombre = res.data.revisor_nombre;
        } else if (rechazarIds.includes(v.id)) {
          v.estado_revision = 'RECHAZADO';
          v.revisado_at = res.data.revisado_at;
          v.revisor_id = res.data.revisor_id;
          v.revisor_nombre = res.data.revisor_nombre;
        }
      });

      // 2. Sincronizar en registros.value
      registros.value.forEach(r => {
        if (r.id === version.id) {
          r.estado_revision = 'APROBADO';
          r.revisado_at = res.data.revisado_at;
          r.revisor_id = res.data.revisor_id;
          r.revisor_nombre = res.data.revisor_nombre;
        } else if (rechazarIds.includes(r.id)) {
          r.estado_revision = 'RECHAZADO';
          r.revisado_at = res.data.revisado_at;
          r.revisor_id = res.data.revisor_id;
          r.revisor_nombre = res.data.revisor_nombre;
        }
      });

      // 3. Sincronizar si hay registroSeleccionado
      if (registroSeleccionado.value) {
        if (registroSeleccionado.value.id === version.id) {
          registroSeleccionado.value.estado_revision = 'APROBADO';
        } else if (rechazarIds.includes(registroSeleccionado.value.id)) {
          registroSeleccionado.value.estado_revision = 'RECHAZADO';
        }
      }

      recalcularKpis();
      actualizarDobleModificacionCliente(cid);
      mostrarToast(`Quedado con solicitud #${version.id} (Aprobada) y ${rechazarIds.length} rechazada(s).`);
    } else {
      mostrarToast(res.data?.message || 'No fue posible resolver los duplicados.');
    }
  } catch (err) {
    console.error('Error al resolver duplicados:', err);
    mostrarToast(err.response?.data?.message || 'Error de comunicación al resolver duplicados.');
  } finally {
    modalComparativo.procesandoId = null;
  }
}

async function quedarseConMasReciente(grupo) {
  if (!grupo || !grupo.items || grupo.items.length === 0) return;

  const masReciente = grupo.items[0]; // están ordenados por id desc
  const otrosPendientes = grupo.items.filter(x => x.id !== masReciente.id && (x.estado_revision || '').toUpperCase() === 'PENDIENTE');
  const rechazarIds = otrosPendientes.map(x => x.id);

  procesandoGrupoKey.value = grupo.key;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.altas-ediciones.resolver-duplicados')
      : '/supervisor/altas-ediciones/resolver-duplicados';

    const res = await axios.post(url, {
      aprobar_id: masReciente.id,
      rechazar_ids: rechazarIds,
      motivo: `Aprobada la versión más reciente #${masReciente.id} y rechazadas las demás`,
    });

    if (res.data && res.data.success) {
      registros.value.forEach(r => {
        if (r.id === masReciente.id) {
          r.estado_revision = 'APROBADO';
          r.revisado_at = res.data.revisado_at;
        } else if (rechazarIds.includes(r.id)) {
          r.estado_revision = 'RECHAZADO';
          r.revisado_at = res.data.revisado_at;
        }
      });

      recalcularKpis();
      actualizarDobleModificacionCliente(grupo.cliente_id);
      mostrarToast(`Aprobada versión más reciente #${masReciente.id} y rechazadas ${rechazarIds.length} anteriores.`);
    }
  } catch (err) {
    console.error('Error al resolver más reciente:', err);
    mostrarToast('Error al procesar resolución.');
  } finally {
    procesandoGrupoKey.value = null;
  }
}

async function cambiarEstadoEnModal(version, nuevoEstado) {
  if (!version || !version.id) return;

  modalComparativo.procesandoId = version.id;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.altas-ediciones.cambiar-estado', version.id)
      : `/supervisor/altas-ediciones/${version.id}/estado`;

    const res = await axios.post(url, {
      estado: nuevoEstado,
    });

    if (res.data && res.data.success) {
      version.estado_revision = res.data.estado_revision;
      version.revisado_at = res.data.revisado_at;
      if (res.data.revisor_id !== undefined) {
        version.revisor_id = res.data.revisor_id;
        version.revisor_nombre = res.data.revisor_nombre;
      }

      const itemEnLista = registros.value.find(r => r.id === version.id);
      if (itemEnLista) {
        itemEnLista.estado_revision = res.data.estado_revision;
        itemEnLista.revisado_at = res.data.revisado_at;
        if (res.data.revisor_id !== undefined) {
          itemEnLista.revisor_id = res.data.revisor_id;
          itemEnLista.revisor_nombre = res.data.revisor_nombre;
        }
      }

      if (registroSeleccionado.value && registroSeleccionado.value.id === version.id) {
        registroSeleccionado.value.estado_revision = res.data.estado_revision;
        registroSeleccionado.value.revisado_at = res.data.revisado_at;
      }

      recalcularKpis();
      actualizarDobleModificacionCliente(version.cliente_id);
      mostrarToast(`Solicitud #${version.id} actualizada a ${nuevoEstado}.`);
    }
  } catch (err) {
    console.error('Error al cambiar estado en modal:', err);
    mostrarToast('Error al actualizar el estado de la solicitud.');
  } finally {
    modalComparativo.procesandoId = null;
  }
}

function abrirFotoDirecto(url, clienteNombre, id) {
  modalFotos.clienteNombre = clienteNombre;
  modalFotos.clienteId = id;
  modalFotos.ruta = '';
  modalFotos.fotos = [{ url, photo_url: url, caption: `Solicitud #${id}` }];
  modalFotos.indice = 0;
  modalFotos.errorMsg = null;
  modalFotos.cargando = false;
  modalFotos.abierto = true;
  modalFotos.open = true;
}

async function cargarDatos() {
  if (
    filtroCanales.value.length === 0 ||
    filtroRutas.value.length === 0 ||
    filtroEstados.value.length === 0 ||
    filtroTipos.value.length === 0
  ) {
    registros.value = [];
    kpis.value = {
      total: 0,
      altas: 0,
      ediciones: 0,
      pendientes: 0,
      aprobados: 0,
    };
    renderizarMapa();
    return;
  }

  cargando.value = true;
  try {
    const res = await axios.post(route('supervisor.altas-ediciones.data'), {
      tipos: filtroTipos.value,
      estados: filtroEstados.value.length === props.estados_revision.length ? [] : filtroEstados.value,
      canales: filtroCanales.value.length === props.canales.length ? [] : filtroCanales.value,
      rutas: filtroRutas.value.length === rutasDisponibles.value.length ? (filtroCanales.value.length === props.canales.length ? [] : filtroRutas.value) : filtroRutas.value,
    });

    registros.value = res.data.registros || [];
    kpis.value = res.data.kpis || {
      total: 0,
      altas: 0,
      ediciones: 0,
      pendientes: 0,
      aprobados: 0,
    };

    renderizarMapa();
  } catch (err) {
    console.error('Error cargando altas y ediciones:', err);
  } finally {
    cargando.value = false;
  }
}

function renderizarMapa() {
  if (!map || !markersLayer) return;
  markersLayer.clearLayers();
  limpiarLienasAuxiliares();
  markersMap.clear();

  const boundsList = [];

  registros.value.forEach(r => {
    if (!r.tiene_gps) return;

    const isAlta = r.tipo_registro && r.tipo_registro.toUpperCase().includes('ALTA');
    const pinColor = isAlta ? '#248A3D' : '#0071E3';

    const marker = L.circleMarker([r.latitud, r.longitud], {
      radius: 6.5,
      fillColor: pinColor,
      color: '#FFFFFF',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.95,
    });

    const popupContent = document.createElement('div');
    popupContent.style.fontFamily = '-apple-system, BlinkMacSystemFont, sans-serif';
    popupContent.style.fontSize = '11px';
    popupContent.style.lineHeight = '1.4';
    popupContent.style.color = '#1D1D1F';
    popupContent.style.minWidth = '220px';

    popupContent.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
        <span style="font-weight: 700; font-size: 12px; color: #1D1D1F; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
          ${r.cliente}
        </span>
        <span style="font-family: monospace; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; background-color: ${pinColor}; color: white;">
          ${r.tipo_registro}
        </span>
      </div>
      <div style="color: #6E6E73; margin-bottom: 2px; font-size: 10px;">
        Solicitud: <strong>#${r.id}</strong> ${r.cliente_id ? '• Cód: <strong>' + r.cliente_id + '</strong>' : '• Alta Nueva'}
      </div>
      <div style="color: #6E6E73; margin-bottom: 2px; font-size: 10px;">
        Ruta: <strong>${r.ruta}</strong> (${r.canal})
      </div>
      <div style="color: #6E6E73; margin-bottom: 2px; font-size: 10px;">
        Vendedor: <strong>${r.vendedor || 'Sin asignar'}</strong>
      </div>
      ${r.tipo_negocio ? `<div style="color: #86868B; margin-bottom: 2px; font-size: 10px;">Tipo: ${r.tipo_negocio}</div>` : ''}
      ${r.celular || r.telefono ? `<div style="color: #0071E3; margin-bottom: 4px; font-size: 10px;">Tel: ${r.celular || r.telefono}</div>` : ''}
      ${r.photo_url ? `
        <div style="width: 100%; height: 75px; border-radius: 4px; overflow: hidden; margin-bottom: 6px; background-color: #F2F2F7;">
          <img src="${r.photo_url}" alt="Fachada" style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
      ` : ''}
      <button
        id="btn-saneamiento-${r.id}"
        type="button"
        style="width: 100%; height: 26px; background-color: #0071E3; color: white; border: none; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;"
      >
        <span>Ver ficha técnica completa</span>
        <span>&rarr;</span>
      </button>
    `;

    const btn = popupContent.querySelector(`#btn-saneamiento-${r.id}`);
    if (btn) {
      btn.addEventListener('click', () => {
        abrirExpediente(r);
      });
    }

    marker.bindPopup(popupContent, { maxWidth: 230 });
    marker.on('click', () => {
      abrirExpediente(r);
    });
    marker.addTo(markersLayer);
    markersMap.set(r.id, marker);
    boundsList.push(L.latLng(r.latitud, r.longitud));
  });

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

function abrirExpediente(r) {
  registroSeleccionado.value = r;
  registroActivoId.value = r.id;
  vistaMovil.value = 'lista';

  limpiarLienasAuxiliares();

  // Consultar clientes cercanos en 200m a la redonda y analizar duplicados siempre que tenga coordenadas
  consultarCercanos(r);

  if (r.tiene_gps && map) {
    map.flyTo([r.latitud, r.longitud], 18, { duration: 0.6 });
    const marker = markersMap.get(r.id);
    if (marker) {
      marker.openPopup();
    }

    // Si es edición y tiene GPS original, dibujar el punto anterior y la línea conectora
    if (r.es_edicion && r.original && r.original.latitud && r.original.longitud) {
      L.circleMarker([r.original.latitud, r.original.longitud], {
        radius: 6.5,
        fillColor: '#8E8E93',
        color: '#FFFFFF',
        weight: 2,
        opacity: 0.9,
        fillOpacity: 0.85,
      }).bindTooltip(`Ubicación Anterior: ${r.original.cliente}`).addTo(auxLinesLayer);

      L.polyline([[r.original.latitud, r.original.longitud], [r.latitud, r.longitud]], {
        color: '#0071E3',
        weight: 2,
        dashArray: '4, 4',
        opacity: 0.9,
      }).bindTooltip(`Desplazamiento: ${r.distancia_desplazamiento_m} metros`).addTo(auxLinesLayer);
    }
  }
}

function limpiarLienasAuxiliares() {
  if (auxLinesLayer) {
    auxLinesLayer.clearLayers();
  }
  datosCercanos.value = null;
}

function consultarCercanos(r) {
  if (!canCercanos.value) return;
  datosCercanos.value = null;
  const lat = Number(r.latitud);
  const lng = Number(r.longitud);

  if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
    return;
  }

  cargandoCercanos.value = true;
  const url = typeof route === 'function' ? route('supervisor.altas-ediciones.cercanos') : '/supervisor/altas-ediciones/cercanos';

  axios.post(url, {
    latitud: lat,
    longitud: lng,
    radio: 200,
    cliente: r.cliente,
    celular: r.celular || r.telefono,
    telefono: r.telefono,
    nit: r.nit,
    exclude_id: r.id,
  }).then(res => {
    datosCercanos.value = res.data;
    if (res.data && res.data.success) {
      dibujarCercanosEnMapa(r, res.data);
    }
  }).catch(err => {
    console.error('Error al consultar clientes cercanos en 200m:', err);
  }).finally(() => {
    cargandoCercanos.value = false;
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatDistanciaTexto(m) {
  if (m === null || m === undefined) return '';
  return `${m} m`;
}

function abrirDetalleCercano(c) {
  clienteCercanoDetalle.value = c;
  tabModalCercano.value = 'comparativa';
  fotosModalCercano.value = [];

  // Si tiene cliente_id existente en padrón, cargar historial de visitas con fotos
  if (c.cliente_id) {
    cargarFotosCliente(c.cliente_id);
  } else if (c.photo_url || c.ultima_foto) {
    fotosModalCercano.value = [
      {
        url: c.photo_url || c.ultima_foto,
        fecha: c.foto_fecha || 'Fecha de solicitud',
        vendedor: c.vendedor || '',
        comments: 'Foto de fachada registrada en solicitud',
      }
    ];
  }
}

function cerrarDetalleCercano() {
  clienteCercanoDetalle.value = null;
  fotosModalCercano.value = [];
}

async function cargarFotosCliente(clienteId) {
  cargandoFotosModalCercano.value = true;
  fotosModalCercano.value = [];
  try {
    let url = `/supervisor/altas-ediciones/fotos-cliente/${clienteId}`;
    try {
      if (typeof route === 'function') {
        url = route('supervisor.altas-ediciones.fotos-cliente', { clienteId });
      }
    } catch (e) {
      url = `/supervisor/altas-ediciones/fotos-cliente/${clienteId}`;
    }
    const res = await axios.get(url);
    if (res.data && res.data.success && Array.isArray(res.data.fotos)) {
      fotosModalCercano.value = res.data.fotos;
    } else {
      fotosModalCercano.value = [];
    }
  } catch (err) {
    console.error('Error al cargar fotos del cliente:', err);
    fotosModalCercano.value = [];
  } finally {
    cargandoFotosModalCercano.value = false;
  }
}

function abrirFotosCliente(c) {
  if (!c) return;
  modalFotos.abierto = true;
  modalFotos.open = true;
  modalFotos.cargando = false;
  modalFotos.errorMsg = null;
  modalFotos.clienteNombre = c.cliente || 'Cliente Cercano';
  modalFotos.clienteId = c.cliente_id || c.saneamiento_id || null;
  modalFotos.ruta = c.ruta || '';
  modalFotos.fotos = [];
  modalFotos.indice = 0;
  modalFotos.isZoomed = false;

  // 1. Mostrar de inmediato la foto disponible (fachada o última visita)
  const fotoInicial = c.photo_url || c.ultima_foto;
  if (fotoInicial) {
    modalFotos.fotos.push({
      url: fotoInicial,
      photo_url: fotoInicial,
      fecha: c.foto_fecha || 'Evidencia Registrada',
      hora: '',
      vendedor: c.vendedor || '',
      comments: c.es_alta_pendiente ? 'Fotografía de fachada del alta en revisión' : 'Evidencia registrada del punto',
    });
  }

  // 2. Si es un cliente existente en padrón (cliente_id), cargar historial completo de visitas
  if (c.cliente_id) {
    modalFotos.cargando = modalFotos.fotos.length === 0;
    let url = `/supervisor/altas-ediciones/fotos-cliente/${c.cliente_id}`;
    try {
      if (typeof route === 'function') {
        url = route('supervisor.altas-ediciones.fotos-cliente', { clienteId: c.cliente_id });
      }
    } catch (e) {
      url = `/supervisor/altas-ediciones/fotos-cliente/${c.cliente_id}`;
    }

    axios.get(url)
      .then(res => {
        if (res.data && res.data.success && Array.isArray(res.data.fotos) && res.data.fotos.length > 0) {
          modalFotos.fotos = res.data.fotos;
        }
      })
      .catch(err => {
        console.error('Error al abrir fotos del cliente:', err);
        if (modalFotos.fotos.length === 0) {
          modalFotos.errorMsg = 'No fue posible recuperar el historial fotográfico del cliente.';
        }
      })
      .finally(() => {
        modalFotos.cargando = false;
      });
  }
}

function abrirFotosRegistroActual() {
  if (!registroSeleccionado.value) return;
  const r = registroSeleccionado.value;
  modalFotos.abierto = true;
  modalFotos.open = true;
  modalFotos.cargando = false;
  modalFotos.errorMsg = null;
  modalFotos.clienteNombre = r.cliente || 'Solicitud de Alta';
  modalFotos.clienteId = r.id;
  modalFotos.ruta = r.ruta || '';
  modalFotos.indice = 0;
  modalFotos.isZoomed = false;

  const fotoSrc = r.photo_url || r.foto_url;
  if (fotoSrc) {
    modalFotos.fotos = [
      {
        url: fotoSrc,
        photo_url: fotoSrc,
        fecha: r.created_at || r.fecha_registro || 'Fecha de registro',
        hora: '',
        vendedor: r.usuario || r.vendedor || '',
        comments: 'Fotografía de fachada del punto capturada en campo',
      }
    ];
  } else {
    modalFotos.fotos = [];
  }
}

function abrirFotoIndividual(f, idx = 0) {
  if (!f) return;
  modalFotos.abierto = true;
  modalFotos.open = true;
  modalFotos.cargando = false;
  modalFotos.errorMsg = null;
  modalFotos.clienteNombre = clienteCercanoDetalle.value?.cliente || 'Fotografía de Evidencia';
  modalFotos.clienteId = clienteCercanoDetalle.value?.cliente_id || null;
  modalFotos.ruta = clienteCercanoDetalle.value?.ruta || '';
  modalFotos.fotos = fotosModalCercano.value.length > 0 ? fotosModalCercano.value : [f];
  modalFotos.indice = (idx >= 0 && idx < modalFotos.fotos.length) ? idx : 0;
  modalFotos.isZoomed = false;
}

function anteriorFotoModal() {
  if (modalFotos.fotos.length <= 1) return;
  modalFotos.isZoomed = false;
  if (modalFotos.indice > 0) {
    modalFotos.indice--;
  } else {
    modalFotos.indice = modalFotos.fotos.length - 1;
  }
}

function siguienteFotoModal() {
  if (modalFotos.fotos.length <= 1) return;
  modalFotos.isZoomed = false;
  if (modalFotos.indice < modalFotos.fotos.length - 1) {
    modalFotos.indice++;
  } else {
    modalFotos.indice = 0;
  }
}

function cerrarModalFotos() {
  modalFotos.abierto = false;
  modalFotos.open = false;
  modalFotos.cargando = false;
  modalFotos.errorMsg = null;
  modalFotos.fotos = [];
  modalFotos.indice = 0;
  modalFotos.isZoomed = false;
}

function dibujarCercanosEnMapa(r, data) {
  if (!map || !auxLinesLayer) return;

  // 1. Círculo de 200m alrededor del punto de inspección
  const circleColor = data.tiene_duplicados ? '#FF3B30' : '#FF9500';
  L.circle([r.latitud, r.longitud], {
    radius: 200,
    color: circleColor,
    fillColor: circleColor,
    fillOpacity: 0.05,
    weight: 1.5,
    dashArray: '5, 5',
  }).bindTooltip('Radio de inspección: 200 metros (Todas las rutas)', { permanent: false, direction: 'top' }).addTo(auxLinesLayer);

  // 2. Marcadores para clientes cercanos
  if (Array.isArray(data.clientes_cercanos)) {
    data.clientes_cercanos.forEach((c, idx) => {
      if (!c.latitud || !c.longitud) return;

      const esDupe = c.tiene_duplicidad;
      const markerColor = esDupe ? '#FF3B30' : (c.distancia_metros <= 20 ? '#FF3B30' : (c.distancia_metros <= 50 ? '#FF9500' : '#8E8E93'));

      const nearMarker = L.circleMarker([c.latitud, c.longitud], {
        radius: esDupe ? 8 : 6,
        fillColor: markerColor,
        color: '#FFFFFF',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.9,
      });

      nearMarker.bindTooltip(`
        <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; line-height: 1.3;">
          <strong style="color: ${esDupe ? '#D70015' : '#1D1D1F'}">${escapeHtml(c.cliente)}</strong> (${c.distancia_metros}m)<br/>
          <span style="color: #6E6E73;">Ruta: <strong>${escapeHtml(c.ruta)}</strong></span>
          ${c.tipo_negocio ? `<br/><span style="color: #86868B;">${escapeHtml(c.tipo_negocio)}</span>` : ''}
          ${esDupe ? '<div style="color: #FF3B30; font-weight: 700; margin-top: 2px;">Coincidencia de datos</div>' : ''}
        </div>
      `, { direction: 'top' });

      // Popup enriquecido con información completa y acciones interactivas
      const hasPhoto = !!(c.ultima_foto || c.photo_url || c.fotos_count > 0);
      const photoThumbHtml = hasPhoto
        ? `<div style="width: 52px; height: 52px; border-radius: 6px; overflow: hidden; border: 1px solid #E5E5EA; flex-shrink: 0; background: #000;">
            <img src="${c.ultima_foto || c.photo_url}" style="width: 100%; height: 100%; object-fit: cover;" alt="Fachada" />
           </div>`
        : '';

      const popupContent = `
        <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11.5px; line-height: 1.35; min-width: 230px; max-width: 280px; padding: 2px;">
          <div style="display: flex; gap: 8px; align-items: flex-start;">
            ${photoThumbHtml}
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
                <span style="font-size: 9.5px; font-weight: 700; padding: 1px 5px; border-radius: 4px; background: ${c.distancia_metros <= 20 ? '#FF3B30' : (c.distancia_metros <= 50 ? '#FFF5E5' : '#F2F2F7')}; color: ${c.distancia_metros <= 20 ? '#FFF' : (c.distancia_metros <= 50 ? '#B25E00' : '#1D1D1F')};">
                  a ${c.distancia_metros}m
                </span>
                <span style="font-size: 9.5px; color: #86868B; font-family: monospace;">#${c.cliente_id || c.saneamiento_id}</span>
              </div>
              <div style="font-weight: 700; color: #1D1D1F; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                ${escapeHtml(c.cliente)}
              </div>
              <div style="font-size: 10.5px; color: #6E6E73;">
                Ruta <strong>${escapeHtml(c.ruta)}</strong> ${c.vendedor ? `(${escapeHtml(c.vendedor)})` : ''}
              </div>
            </div>
          </div>

          <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #F2F2F7; font-size: 10px; color: #6E6E73; display: grid; grid-template-columns: 1fr 1fr; gap: 3px;">
            <div><strong>NIT:</strong> ${escapeHtml(c.nit || 'Sin NIT')}</div>
            <div><strong>Día:</strong> ${escapeHtml(c.dia || '-')}</div>
            <div style="grid-column: span 2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <strong>Dir:</strong> ${escapeHtml(c.direccion || c.referencia || 'Sin dirección')}
            </div>
          </div>

          ${esDupe ? `
            <div style="margin-top: 5px; padding: 3px 6px; background: #FFF2F2; border-radius: 4px; border: 1px solid rgba(255,59,48,0.25); color: #D70015; font-size: 9.5px; font-weight: 600;">
              Coincide con la solicitud en revisión
            </div>
          ` : ''}

          <div style="margin-top: 8px; display: flex; gap: 4px;">
            <button
              type="button"
              onclick="window.__abrirFichaCercano && window.__abrirFichaCercano(${idx})"
              style="flex: 1; padding: 4px 6px; background: #0071E3; color: white; border: none; border-radius: 6px; font-size: 10px; font-weight: 600; cursor: pointer; text-align: center;"
            >
              Ficha & Comparativa
            </button>
            ${hasPhoto ? `
              <button
                type="button"
                onclick="window.__abrirFotosCercano && window.__abrirFotosCercano(${idx})"
                style="padding: 4px 6px; background: #5856D6; color: white; border: none; border-radius: 6px; font-size: 10px; font-weight: 600; cursor: pointer; text-align: center;"
                title="Ver fotos del punto"
              >
                Fotos
              </button>
            ` : ''}
          </div>
        </div>
      `;

      nearMarker.bindPopup(popupContent, { maxWidth: 300 });
      nearMarker.addTo(auxLinesLayer);
    });
  }
}

function enfocarCercano(c) {
  if (map && c.latitud && c.longitud) {
    map.flyTo([c.latitud, c.longitud], 19, { duration: 0.5 });
    vistaMovil.value = 'mapa';

    // Abrir popup si está presente en la capa auxiliar
    if (auxLinesLayer) {
      auxLinesLayer.eachLayer(layer => {
        if (layer.getLatLng && Math.abs(layer.getLatLng().lat - c.latitud) < 0.00005 && Math.abs(layer.getLatLng().lng - c.longitud) < 0.00005) {
          if (layer.openPopup) {
            setTimeout(() => layer.openPopup(), 400);
          }
        }
      });
    }
  }
}

function manejarTeclasModal(e) {
  if (e.key === 'Escape') {
    if (modalComparativo.abierto) {
      cerrarModalComparativo();
    } else if (modalFotos.abierto || modalFotos.open) {
      cerrarModalFotos();
    } else if (modalCambioMasivoAbierto.value) {
      cerrarModalCambioMasivo();
    } else if (clienteCercanoDetalle.value) {
      cerrarDetalleCercano();
    }
  } else if (modalFotos.abierto || modalFotos.open) {
    if (e.key === 'ArrowLeft') {
      anteriorFotoModal();
    } else if (e.key === 'ArrowRight') {
      siguienteFotoModal();
    }
  }
}

function initMap() {
  const defaultLoc = mapsConfig.defaultLocation;
  map = L.map('map-altas', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer(mapsConfig.tileLayers.openStreetMap.url, {
    maxZoom: 19,
    attribution: mapsConfig.tileLayers.openStreetMap.attribution,
    subdomains: mapsConfig.tileLayers.openStreetMap.subdomains || 'abc',
  }).addTo(map);

  auxLinesLayer = L.featureGroup().addTo(map);
  markersLayer = L.featureGroup().addTo(map);

  map.on('click', () => {
    menuAbierto.value = null;
  });
}

onMounted(() => {
  initMap();
  cargarDatos();
  window.addEventListener('keydown', manejarTeclasModal);

  window.__abrirFichaCercano = (idx) => {
    if (datosCercanos.value && datosCercanos.value.clientes_cercanos && datosCercanos.value.clientes_cercanos[idx]) {
      abrirDetalleCercano(datosCercanos.value.clientes_cercanos[idx]);
    }
  };

  window.__abrirFotosCercano = (idx) => {
    if (datosCercanos.value && datosCercanos.value.clientes_cercanos && datosCercanos.value.clientes_cercanos[idx]) {
      abrirFotosCliente(datosCercanos.value.clientes_cercanos[idx]);
    }
  };
});

onUnmounted(() => {
  window.removeEventListener('keydown', manejarTeclasModal);
  delete window.__abrirFichaCercano;
  delete window.__abrirFotosCercano;
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