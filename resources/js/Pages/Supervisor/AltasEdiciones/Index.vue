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

        <!-- Filtros con Scroll Horizontal -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          
          <!-- Filtro / Badge de Tipo de Registro -->
          <div
            v-if="props.tipoFijo"
            class="h-7 px-2.5 rounded-[6px] text-xs font-semibold flex items-center gap-1.5 shrink-0 select-none border"
            :class="submoduloActivo === 'altas' ? 'bg-[#EBF9EF] text-[#248A3D] border-[#248A3D]/20' : 'bg-[#EFF6FF] text-[#0071E3] border-[#0071E3]/20'"
          >
            <span class="text-[#86868B]">Módulo:</span>
            <span class="font-bold">{{ submoduloActivo === 'altas' ? 'Nuevas Altas' : 'Modificaciones' }}</span>
          </div>
          <button
            v-else
            type="button"
            @click.stop="toggleMenu('tipos', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Tipo:</span>
            <span class="font-semibold">{{ labelTipos }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Filtro: Estado de Revisión -->
          <button
            type="button"
            @click.stop="toggleMenu('estados', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Estado:</span>
            <span class="font-semibold">{{ labelEstados }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Filtro: Canales -->
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

          <!-- Filtro: Rutas -->
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

        <!-- Indicador de Carga, Control Móvil, Descarga Excel y Badges -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Consultando solicitudes...
          </span>

          <!-- Botón Descargar Excel de Ediciones Pendientes Comparadas con pan_ruteo -->
          <button
            type="button"
            @click="exportarEdicionesExcel"
            :disabled="cargando || conteoEdicionesPendientes === 0"
            class="h-7 px-2.5 bg-[#248A3D] hover:bg-[#1E7433] active:bg-[#185E29] disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[6px] flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer select-none shrink-0"
            :title="conteoEdicionesPendientes > 0 ? `Descargar ${conteoEdicionesPendientes} cliente(s) con edición pendiente comparados y actualizados con pan_ruteo` : 'No hay clientes con ediciones pendientes en la selección actual'"
          >
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="hidden sm:inline">Descargar Excel</span>
            <span class="sm:hidden">Excel</span>
            <span v-if="conteoEdicionesPendientes > 0" class="px-1.5 py-0.2 bg-black/20 rounded text-[10px] font-mono font-bold">
              {{ conteoEdicionesPendientes }}
            </span>
          </button>

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
            <button type="button" @click="filtroEstados = [...props.estados_revision]; cargarDatos()" class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer">Todos</button>
            <button type="button" @click="filtroEstados = []; cargarDatos()" class="text-[11px] text-[#6E6E73] font-medium hover:underline cursor-pointer">Ninguno</button>
          </div>
          <div class="space-y-1">
            <label
              v-for="e in estados_revision"
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
          class="w-full md:w-[460px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- MODO 1: LISTADO GENERAL -->
          <template v-if="!registroSeleccionado">
            <!-- Buscador Rápido -->
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] space-y-1.5 shrink-0">
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Buscar cliente, código, ruta o dirección..."
                class="w-full h-7 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#86868B]"
              />
            </div>

            <!-- Listado Densa de Solicitudes -->
            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
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
                class="p-3 hover:bg-[#FBFBFD] transition-colors cursor-pointer border-b border-[#E5E5EA]/70"
                :class="registroActivoId === r.id ? 'bg-[#F2F2F7]' : 'bg-white'"
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
          </template>

          <!-- MODO 2: FICHA TÉCNICA COMPLETA DE INSPECCIÓN -->
          <template v-else>
            <!-- Cabecera del Expediente -->
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="registroSeleccionado = null; limpiarLienasAuxiliares()"
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

            <!-- Ficha Técnica con todos los datos requeridos -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-[#F5F5F7]">
              
              <!-- 0. Panel de Acción de Auditoría (Aprobar / Rechazar) -->
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

                <!-- Botones Principales: Aprobar y Rechazar -->
                <div class="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    @click="cambiarEstadoRevision(registroSeleccionado, 'APROBADO')"
                    :disabled="cambiandoEstadoId === registroSeleccionado.id || registroSeleccionado.estado_revision === 'APROBADO'"
                    class="h-9 px-3 rounded-[6px] text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-2xs select-none disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="registroSeleccionado.estado_revision === 'APROBADO'
                      ? 'bg-[#EBF9EF] text-[#248A3D] border border-[#248A3D]/30 font-extrabold'
                      : 'bg-[#248A3D] text-white hover:bg-[#1E7433] active:scale-[0.98]'"
                    title="Aprobar alta o edición"
                  >
                    <span v-if="cambiandoEstadoId === registroSeleccionado.id && estadoEnProceso === 'APROBADO'" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>{{ registroSeleccionado.estado_revision === 'APROBADO' ? 'Aprobado' : 'Aprobar' }}</span>
                  </button>

                  <button
                    type="button"
                    @click="cambiarEstadoRevision(registroSeleccionado, 'RECHAZADO')"
                    :disabled="cambiandoEstadoId === registroSeleccionado.id || registroSeleccionado.estado_revision === 'RECHAZADO'"
                    class="h-9 px-3 rounded-[6px] text-xs font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer shadow-2xs select-none disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="registroSeleccionado.estado_revision === 'RECHAZADO'
                      ? 'bg-[#FDF0EF] text-[#C9342C] border border-[#C9342C]/30 font-extrabold'
                      : 'bg-[#FF3B30] text-white hover:bg-[#D70015] active:scale-[0.98]'"
                    title="Rechazar alta o edición"
                  >
                    <span v-if="cambiandoEstadoId === registroSeleccionado.id && estadoEnProceso === 'RECHAZADO'" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>{{ registroSeleccionado.estado_revision === 'RECHAZADO' ? 'Rechazado' : 'Rechazar' }}</span>
                  </button>
                </div>

                <!-- Opción de Retorno a Pendiente -->
                <div v-if="registroSeleccionado.estado_revision !== 'PENDIENTE'" class="pt-1 text-center">
                  <button
                    type="button"
                    @click="cambiarEstadoRevision(registroSeleccionado, 'PENDIENTE')"
                    :disabled="cambiandoEstadoId === registroSeleccionado.id"
                    class="text-[11px] font-mono text-[#86868B] hover:text-[#1D1D1F] underline cursor-pointer disabled:opacity-50"
                  >
                    Restablecer a Pendiente de revisión
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

              <!-- DATOS DE ALTA LISTOS PARA COPIAR CON 1 CLIC (SOBRIO) -->
              <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs space-y-2 font-sans">
                <!-- Encabezado de la tarjeta con acciones rápidas -->
                <div class="flex items-center justify-between gap-2 pb-2 border-b border-[#E5E5EA]">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3] shrink-0"></span>
                    <span class="text-[11px] font-bold text-[#1D1D1F] uppercase tracking-wider truncate">
                      Datos de Alta · Copiado Rápido
                    </span>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      type="button"
                      @click="copiarFilaExcel(registroSeleccionado)"
                      class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1 active:scale-95 select-none"
                      :class="campoCopiado === 'excel' ? 'bg-[#248A3D] text-white border-[#248A3D]' : 'bg-[#F2F2F7] hover:bg-[#E5E5EA] text-[#1D1D1F] border-[#E5E5EA]'"
                      title="Copiar los 6 campos tabulados para pegar directamente en Excel"
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
                      class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold border transition-all cursor-pointer flex items-center gap-1 active:scale-95 select-none"
                      :class="campoCopiado === 'todo' ? 'bg-[#248A3D] text-white border-[#248A3D]' : 'bg-[#0071E3] text-white hover:bg-[#005BB5] border-[#0071E3]'"
                      title="Copiar todos los datos con etiquetas"
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
                  Haz clic sobre cualquier campo para copiar su valor al portapapeles:
                </p>

                <!-- Cuadrícula interactiva con los 6 campos -->
                <div class="space-y-1.5">
                  <!-- 1. Nombre Completo -->
                  <div
                    @click="copiarAlPortapapeles(registroSeleccionado.cliente, 'nombre', 'Nombre Completo')"
                    class="group flex items-center justify-between p-2 rounded-[8px] bg-white border transition-all cursor-pointer select-none hover:shadow-2xs active:scale-[0.99]"
                    :class="campoCopiado === 'nombre' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA] hover:border-[#0071E3] hover:bg-[#FBFBFD]'"
                    title="Clic para copiar Nombre Completo"
                  >
                    <div class="min-w-0 flex-1 pr-2">
                      <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">1. Nombre Completo</span>
                      <span class="text-[12px] font-bold text-[#1D1D1F] truncate block">
                        {{ registroSeleccionado.cliente || 'Sin nombre' }}
                      </span>
                    </div>
                    <div class="shrink-0 flex items-center gap-1">
                      <span v-if="campoCopiado === 'nombre'" class="text-[10px] font-bold text-[#248A3D]">Copiado</span>
                      <span
                        class="w-6 h-6 rounded-[6px] flex items-center justify-center transition-colors shadow-2xs"
                        :class="campoCopiado === 'nombre' ? 'bg-[#248A3D] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] group-hover:bg-[#0071E3] group-hover:text-white'"
                      >
                        <svg v-if="campoCopiado === 'nombre'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                        </svg>
                      </span>
                    </div>
                  </div>

                  <!-- 2. Razón Social -->
                  <div
                    @click="copiarAlPortapapeles(registroSeleccionado.nombre_factura || registroSeleccionado.razon_social || registroSeleccionado.cliente, 'razon_social', 'Razón Social')"
                    class="group flex items-center justify-between p-2 rounded-[8px] bg-white border transition-all cursor-pointer select-none hover:shadow-2xs active:scale-[0.99]"
                    :class="campoCopiado === 'razon_social' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA] hover:border-[#0071E3] hover:bg-[#FBFBFD]'"
                    title="Clic para copiar Razón Social"
                  >
                    <div class="min-w-0 flex-1 pr-2">
                      <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">2. Razón Social</span>
                      <span class="text-[11px] font-bold text-[#1D1D1F] truncate block">
                        {{ registroSeleccionado.nombre_factura || registroSeleccionado.razon_social || registroSeleccionado.cliente || 'Sin razón social' }}
                      </span>
                    </div>
                    <div class="shrink-0 flex items-center gap-1">
                      <span v-if="campoCopiado === 'razon_social'" class="text-[10px] font-bold text-[#248A3D]">Copiado</span>
                      <span
                        class="w-6 h-6 rounded-[6px] flex items-center justify-center transition-colors shadow-2xs"
                        :class="campoCopiado === 'razon_social' ? 'bg-[#248A3D] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] group-hover:bg-[#0071E3] group-hover:text-white'"
                      >
                        <svg v-if="campoCopiado === 'razon_social'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                        </svg>
                      </span>
                    </div>
                  </div>

                  <!-- Fila doble: Carnet/NIT y Celular/Teléfono -->
                  <div class="grid grid-cols-2 gap-1.5">
                    <!-- 3. Carnet o NIT -->
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.nit, 'nit', 'Carnet o NIT')"
                      class="group flex items-center justify-between p-2 rounded-[8px] bg-white border transition-all cursor-pointer select-none hover:shadow-2xs active:scale-[0.99]"
                      :class="campoCopiado === 'nit' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA] hover:border-[#0071E3] hover:bg-[#FBFBFD]'"
                      title="Clic para copiar Carnet o NIT"
                    >
                      <div class="min-w-0 flex-1 pr-1.5">
                        <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">3. Carnet / NIT</span>
                        <span class="text-[11px] font-mono font-bold truncate block" :class="registroSeleccionado.nit ? 'text-[#1D1D1F]' : 'text-[#86868B] italic'">
                          {{ registroSeleccionado.nit || 'Sin NIT/CI' }}
                        </span>
                      </div>
                      <div class="shrink-0 flex items-center gap-1">
                        <span v-if="campoCopiado === 'nit'" class="text-[9px] font-bold text-[#248A3D]">Copiado</span>
                        <span
                          class="w-5 h-5 rounded-[5px] shrink-0 flex items-center justify-center transition-colors shadow-2xs"
                          :class="campoCopiado === 'nit' ? 'bg-[#248A3D] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] group-hover:bg-[#0071E3] group-hover:text-white'"
                        >
                          <svg v-if="campoCopiado === 'nit'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                          </svg>
                        </span>
                      </div>
                    </div>

                    <!-- 4. Celular o Teléfono -->
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.celular || registroSeleccionado.telefono, 'telefono', 'Celular o Teléfono')"
                      class="group flex items-center justify-between p-2 rounded-[8px] bg-white border transition-all cursor-pointer select-none hover:shadow-2xs active:scale-[0.99]"
                      :class="campoCopiado === 'telefono' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA] hover:border-[#0071E3] hover:bg-[#FBFBFD]'"
                      title="Clic para copiar Celular o Teléfono"
                    >
                      <div class="min-w-0 flex-1 pr-1.5">
                        <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">4. Celular / Teléfono</span>
                        <span class="text-[11px] font-mono font-bold truncate block" :class="(registroSeleccionado.celular || registroSeleccionado.telefono) ? 'text-[#0071E3]' : 'text-[#86868B] italic'">
                          {{ registroSeleccionado.celular || registroSeleccionado.telefono || 'Sin teléfono' }}
                        </span>
                      </div>
                      <div class="shrink-0 flex items-center gap-1">
                        <span v-if="campoCopiado === 'telefono'" class="text-[9px] font-bold text-[#248A3D]">Copiado</span>
                        <span
                          class="w-5 h-5 rounded-[5px] shrink-0 flex items-center justify-center transition-colors shadow-2xs"
                          :class="campoCopiado === 'telefono' ? 'bg-[#248A3D] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] group-hover:bg-[#0071E3] group-hover:text-white'"
                        >
                          <svg v-if="campoCopiado === 'telefono'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                          </svg>
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Fila doble: Latitud y Longitud -->
                  <div class="grid grid-cols-2 gap-1.5">
                    <!-- 5. Latitud -->
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.latitud, 'latitud', 'Latitud')"
                      class="group flex items-center justify-between p-2 rounded-[8px] bg-white border transition-all cursor-pointer select-none hover:shadow-2xs active:scale-[0.99]"
                      :class="campoCopiado === 'latitud' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA] hover:border-[#0071E3] hover:bg-[#FBFBFD]'"
                      title="Clic para copiar Latitud exacta"
                    >
                      <div class="min-w-0 flex-1 pr-1.5">
                        <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">5. Latitud</span>
                        <span class="text-[11px] font-mono font-bold truncate block text-[#1D1D1F]">
                          {{ (registroSeleccionado.latitud !== null && registroSeleccionado.latitud !== undefined && registroSeleccionado.latitud !== '') ? registroSeleccionado.latitud : 'Sin GPS' }}
                        </span>
                      </div>
                      <div class="shrink-0 flex items-center gap-1">
                        <span v-if="campoCopiado === 'latitud'" class="text-[9px] font-bold text-[#248A3D]">Copiado</span>
                        <span
                          class="w-5 h-5 rounded-[5px] shrink-0 flex items-center justify-center transition-colors shadow-2xs"
                          :class="campoCopiado === 'latitud' ? 'bg-[#248A3D] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] group-hover:bg-[#0071E3] group-hover:text-white'"
                        >
                          <svg v-if="campoCopiado === 'latitud'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                          </svg>
                        </span>
                      </div>
                    </div>

                    <!-- 6. Longitud -->
                    <div
                      @click="copiarAlPortapapeles(registroSeleccionado.longitud, 'longitud', 'Longitud')"
                      class="group flex items-center justify-between p-2 rounded-[8px] bg-white border transition-all cursor-pointer select-none hover:shadow-2xs active:scale-[0.99]"
                      :class="campoCopiado === 'longitud' ? 'border-[#34C759] bg-[#EBF9EF]' : 'border-[#E5E5EA] hover:border-[#0071E3] hover:bg-[#FBFBFD]'"
                      title="Clic para copiar Longitud exacta"
                    >
                      <div class="min-w-0 flex-1 pr-1.5">
                        <span class="text-[9px] font-bold uppercase tracking-wider block text-[#86868B]">6. Longitud</span>
                        <span class="text-[11px] font-mono font-bold truncate block text-[#1D1D1F]">
                          {{ (registroSeleccionado.longitud !== null && registroSeleccionado.longitud !== undefined && registroSeleccionado.longitud !== '') ? registroSeleccionado.longitud : 'Sin GPS' }}
                        </span>
                      </div>
                      <div class="shrink-0 flex items-center gap-1">
                        <span v-if="campoCopiado === 'longitud'" class="text-[9px] font-bold text-[#248A3D]">Copiado</span>
                        <span
                          class="w-5 h-5 rounded-[5px] shrink-0 flex items-center justify-center transition-colors shadow-2xs"
                          :class="campoCopiado === 'longitud' ? 'bg-[#248A3D] text-white' : 'bg-[#F2F2F7] text-[#6E6E73] group-hover:bg-[#0071E3] group-hover:text-white'"
                        >
                          <svg v-if="campoCopiado === 'longitud'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                          </svg>
                          <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                          </svg>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 1. Datos Maestros de Identificación -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span
                        class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white"
                        :class="getTipoColor(registroSeleccionado.tipo_registro)"
                      >
                        {{ registroSeleccionado.tipo_registro }}
                      </span>
                      <span class="text-[10px] font-mono font-bold text-[#86868B]">
                        Solicitud #{{ registroSeleccionado.id }}
                      </span>
                      <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight w-full mt-0.5">
                        {{ registroSeleccionado.cliente }}
                      </h3>
                    </div>
                    <p v-if="registroSeleccionado.nombre_factura" class="text-[11px] text-[#6E6E73] mt-0.5">
                      <span class="text-[#86868B]">Razón Social (nombre_factura):</span> {{ registroSeleccionado.nombre_factura }}
                    </p>
                  </div>

                  <div class="text-right shrink-0">
                    <span
                      class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded border inline-block"
                      :class="getEstadoRevisionClass(registroSeleccionado.estado_revision)"
                    >
                      {{ registroSeleccionado.estado_revision }}
                    </span>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-[#E5E5EA] text-[11px] font-mono">
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">ID Solicitud (id)</span>
                    <span class="font-bold text-[#1D1D1F]">#{{ registroSeleccionado.id }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Código Cliente (cliente_id)</span>
                    <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.cliente_id || 'Alta Nueva (Sin código)' }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Ruta Oficial (route)</span>
                    <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.ruta }}</span>
                    <span class="text-[#86868B] text-[10px]"> ({{ registroSeleccionado.canal }})</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Vendedor Asignado</span>
                    <span class="font-bold text-[#1D1D1F] truncate block">{{ registroSeleccionado.vendedor || 'Sin asignar' }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Tiene Doc. Identidad</span>
                    <span class="font-semibold" :class="registroSeleccionado.nit ? 'text-[#248A3D]' : 'text-[#86868B]'">
                      {{ registroSeleccionado.nit ? 'SÍ' : 'NO' }}
                    </span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Nro. Documento / NIT (nit)</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.nit || 'No registrado' }}</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-[9px] text-[#86868B] uppercase block">Registrado En Campo Por (user_id)</span>
                    <span class="font-medium text-[#1D1D1F]">{{ registroSeleccionado.usuario || 'Usuario #' + registroSeleccionado.user_id }}</span>
                    <span class="text-[#86868B] text-[10px]"> (ID: {{ registroSeleccionado.user_id || 'N/A' }})</span>
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

              <!-- 2. Comunicación y Contacto -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block">Contacto y Comunicación</span>
                
                <div class="grid grid-cols-2 gap-2 text-[11px] font-mono">
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Tiene Celular</span>
                    <span class="font-semibold" :class="registroSeleccionado.celular ? 'text-[#248A3D]' : 'text-[#86868B]'">
                      {{ registroSeleccionado.celular ? 'SÍ' : 'NO' }}
                    </span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Celular</span>
                    <a
                      v-if="registroSeleccionado.celular"
                      :href="`tel:${registroSeleccionado.celular}`"
                      class="font-semibold text-[#0071E3] hover:underline"
                    >
                      {{ registroSeleccionado.celular }}
                    </a>
                    <span v-else class="text-[#86868B]">No registrado</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Tiene Teléfono</span>
                    <span class="font-semibold" :class="registroSeleccionado.telefono ? 'text-[#248A3D]' : 'text-[#86868B]'">
                      {{ registroSeleccionado.telefono ? 'SÍ' : 'NO' }}
                    </span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Teléfono</span>
                    <a
                      v-if="registroSeleccionado.telefono"
                      :href="`tel:${registroSeleccionado.telefono}`"
                      class="font-semibold text-[#0071E3] hover:underline"
                    >
                      {{ registroSeleccionado.telefono }}
                    </a>
                    <span v-else class="text-[#86868B]">No registrado</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-[9px] text-[#86868B] uppercase block">Persona de Contacto</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.contacto || 'No registrado' }}</span>
                  </div>
                </div>
              </div>

              <!-- 3. Clasificación Comercial y Ubicación -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block">Clasificación Comercial & Zona</span>

                <div class="grid grid-cols-2 gap-2 text-[11px] font-mono">
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Tipo de Negocio</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.tipo_negocio || 'No asignado' }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Lista de Precios</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.lista_precios || 'No asignada' }}</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-[9px] text-[#86868B] uppercase block">Zona Asignada</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.zona || 'No asignada' }}</span>
                  </div>
                  <div class="col-span-2">
                    <span class="text-[9px] text-[#86868B] uppercase block">Dirección</span>
                    <span class="text-[#1D1D1F]">{{ registroSeleccionado.direccion || 'Sin dirección registrada' }}</span>
                  </div>
                  <div v-if="registroSeleccionado.referencia" class="col-span-2">
                    <span class="text-[9px] text-[#86868B] uppercase block">Referencia</span>
                    <span class="text-[#6E6E73] italic">{{ registroSeleccionado.referencia }}</span>
                  </div>
                </div>
              </div>

              <!-- 4. Telemetría GPS y Coordenadas -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Georreferenciación Capturada</span>
                  <span v-if="registroSeleccionado.distancia_desplazamiento_m" class="text-[10px] font-mono font-bold text-[#0071E3]">
                    Desv: {{ registroSeleccionado.distancia_desplazamiento_m }}m
                  </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-[11px] font-mono">
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Latitud</span>
                    <span class="font-semibold text-[#1D1D1F]">
                      {{ formatCoord(registroSeleccionado.latitud) }}
                    </span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Longitud</span>
                    <span class="font-semibold text-[#1D1D1F]">
                      {{ formatCoord(registroSeleccionado.longitud) }}
                    </span>
                  </div>
                  <div v-if="registroSeleccionado.accuracy !== null">
                    <span class="text-[9px] text-[#86868B] uppercase block">Precisión GPS</span>
                    <span class="text-[#1D1D1F]">{{ registroSeleccionado.accuracy }}m</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Fecha de Captura</span>
                    <span class="text-[#1D1D1F]">{{ registroSeleccionado.fecha_registro }}</span>
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
          :class="vistaMovil === 'lista' ? 'hidden md:block' : 'block h-full w-full'"
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
import { ref, reactive, computed, onMounted, onUnmounted, Teleport } from 'vue';
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
const tiposIniciales = tipoFijoUpper
  ? props.tipos_registro.filter(t => t.toUpperCase().includes(tipoFijoUpper))
  : [...props.tipos_registro];

const filtroTipos = ref(tiposIniciales.length > 0 ? tiposIniciales : (tipoFijoUpper ? [tipoFijoUpper] : [...props.tipos_registro]));
const filtroEstados = ref([...props.estados_revision]);
const filtroCanales = ref([...props.canales]);
const filtroRutas = ref(props.catalogo_rutas.map(r => r.ruta));
const busquedaTexto = ref('');
const busquedaRuta = ref('');

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const vistaMovil = ref('mapa');

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

async function copiarFichaCompleta(registro) {
  if (!registro) return;
  const nombre = registro.cliente || '';
  const nit = registro.nit || '';
  const telefono = registro.celular || registro.telefono || '';
  const razonSocial = registro.nombre_factura || registro.razon_social || registro.cliente || '';
  const lat = (registro.latitud !== null && registro.latitud !== undefined && registro.latitud !== '') ? registro.latitud : '';
  const lng = (registro.longitud !== null && registro.longitud !== undefined && registro.longitud !== '') ? registro.longitud : '';

  const texto = [
    `Nombre: ${nombre}`,
    `Carnet / NIT: ${nit}`,
    `Celular / Teléfono: ${telefono}`,
    `Razón Social: ${razonSocial}`,
    `Latitud: ${lat}`,
    `Longitud: ${lng}`,
  ].join('\n');

  await copiarAlPortapapeles(texto, 'todo', 'Ficha Completa');
}

async function copiarFilaExcel(registro) {
  if (!registro) return;
  const nombre = registro.cliente || '';
  const nit = registro.nit || '';
  const telefono = registro.celular || registro.telefono || '';
  const razonSocial = registro.nombre_factura || registro.razon_social || registro.cliente || '';
  const lat = (registro.latitud !== null && registro.latitud !== undefined && registro.latitud !== '') ? registro.latitud : '';
  const lng = (registro.longitud !== null && registro.longitud !== undefined && registro.longitud !== '') ? registro.longitud : '';

  const tsv = `${nombre}\t${nit}\t${telefono}\t${razonSocial}\t${lat}\t${lng}`;
  await copiarAlPortapapeles(tsv, 'excel', 'Fila para Excel (Tabulada)');
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

const labelTipos = computed(() => {
  if (filtroTipos.value.length === props.tipos_registro.length) return 'Todos';
  if (filtroTipos.value.length === 0) return 'Sin filtro';
  return `${filtroTipos.value.length} sel.`;
});

const labelEstados = computed(() => {
  if (filtroEstados.value.length === props.estados_revision.length) return 'Todos';
  if (filtroEstados.value.length === 0) return 'Sin filtro';
  return `${filtroEstados.value.length} sel.`;
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

const registrosFiltrados = computed(() => {
  let list = registros.value;
  if (!busquedaTexto.value.trim()) return list;

  const q = busquedaTexto.value.toLowerCase();
  return list.filter(r =>
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
});

// Ediciones Pendientes para exportación comparada contra pan_ruteo
const edicionesPendientes = computed(() => {
  let lista = registrosFiltrados.value.filter(r => {
    const tipo = (r.tipo_registro || '').toUpperCase();
    const estado = (r.estado_revision || '').toUpperCase();
    return tipo.includes('EDIC') && estado === 'PENDIENTE';
  });

  if (lista.length === 0 && !busquedaTexto.value.trim()) {
    lista = registros.value.filter(r => {
      const tipo = (r.tipo_registro || '').toUpperCase();
      const estado = (r.estado_revision || '').toUpperCase();
      return tipo.includes('EDIC') && estado === 'PENDIENTE';
    });
  }

  return lista;
});

const conteoEdicionesPendientes = computed(() => edicionesPendientes.value.length);

function exportarEdicionesExcel() {
  const lista = edicionesPendientes.value;

  if (lista.length === 0) {
    alert('No se encontraron clientes con ediciones pendientes para descargar.');
    return;
  }

  const headers = [
    'Código Cliente',
    'Nodo ID',
    'Nodo',
    'Cliente (Actualizado)',
    'Vendedor',
    'Código Vendedor',
    'Tipo Negocio (Actualizado)',
    'Territorio / Zona (Actualizado)',
    'Mercado',
    'Zona Venta (Actualizada)',
    'Lista Precios / Precio (Actualizado)',
    'Cluster T',
    'Cluster C',
    'Ruta (Actualizada)',
    'Día Visita',
    'Día Visita Normalizado',
    'Secuencia Venta',
    'Secuencia Distribuidor',
    'Contacto (Actualizado)',
    'CI',
    'Teléfono (Actualizado)',
    'Celular (Actualizado)',
    'Dirección (Actualizada)',
    'Nro',
    'Referencia (Actualizada)',
    'Razón Social Factura (Actualizada)',
    'NIT (Actualizado)',
    'Tipo Pago',
    'Días Crédito',
    'Límite Crédito',
    'Latitud (Actualizada)',
    'Longitud (Actualizada)',
    'Estado Cliente',
    'ID Solicitud Edición',
    'Estado Revisión',
    'Campos Modificados',
    'Total Campos Modificados',
    'Desplazamiento GPS (m)',
    'Fecha Solicitud',
    'Usuario Solicitante',
  ];

  const rows = [headers];

  lista.forEach(r => {
    const orig = r.original || {};
    const cambios = [];

    // 1. Cliente
    let clienteFinal = orig.cliente || r.cliente || '';
    if (r.cliente && isDiferente(orig.cliente, r.cliente)) {
      clienteFinal = r.cliente;
      cambios.push('Cliente');
    }

    // 2. Tipo de Negocio
    let tipoNegocioFinal = orig.tipo_negocio || r.tipo_negocio || '';
    if (r.tipo_negocio && isDiferente(orig.tipo_negocio, r.tipo_negocio)) {
      tipoNegocioFinal = r.tipo_negocio;
      cambios.push('Tipo Negocio');
    }

    // 3. Dirección
    let direccionFinal = orig.direccion || r.direccion || '';
    if (r.direccion && isDiferente(orig.direccion, r.direccion)) {
      direccionFinal = r.direccion;
      cambios.push('Dirección');
    }

    // 4. Referencia
    let referenciaFinal = orig.referencia || r.referencia || '';
    if (r.referencia && isDiferente(orig.referencia, r.referencia)) {
      referenciaFinal = r.referencia;
      cambios.push('Referencia');
    }

    // 5. Contacto
    let contactoFinal = orig.contacto || r.contacto || '';
    if (r.contacto && isDiferente(orig.contacto, r.contacto)) {
      contactoFinal = r.contacto;
      cambios.push('Contacto');
    }

    // 6. Teléfono
    let telefonoFinal = orig.telefono || r.telefono || '';
    if (r.telefono && isDiferente(orig.telefono, r.telefono)) {
      telefonoFinal = r.telefono;
      cambios.push('Teléfono');
    }

    // 7. Celular
    let celularFinal = orig.celular || r.celular || '';
    if (r.celular && isDiferente(orig.celular, r.celular)) {
      celularFinal = r.celular;
      cambios.push('Celular');
    }

    // 8. Razón Social Factura (Nombre Factura)
    let nombreFacturaFinal = orig.nombre_factura || r.nombre_factura || '';
    if (r.nombre_factura && isDiferente(orig.nombre_factura, r.nombre_factura)) {
      nombreFacturaFinal = r.nombre_factura;
      cambios.push('Nombre Factura');
    }

    // 9. NIT
    let nitFinal = orig.nit || r.nit || '';
    if (r.nit && isDiferente(orig.nit, r.nit)) {
      nitFinal = r.nit;
      cambios.push('NIT');
    }

    // 10. Zona / Territorio
    let territorioFinal = orig.territorio || orig.zona || r.zona || '';
    let zonaVentaFinal = orig.zona_venta || orig.zona || r.zona || '';
    if (r.zona && isDiferente(orig.zona, r.zona)) {
      territorioFinal = r.zona;
      zonaVentaFinal = r.zona;
      cambios.push('Zona/Territorio');
    }

    // 11. Lista de Precios
    let precioFinal = orig.precio || orig.lista_precios || r.lista_precios || '';
    if (r.lista_precios && isDiferente(orig.lista_precios, r.lista_precios)) {
      precioFinal = r.lista_precios;
      cambios.push('Lista Precios');
    }

    // 12. Ruta
    let rutaFinal = orig.ruta || r.ruta || '';
    if (r.ruta && isDiferente(orig.ruta, r.ruta)) {
      rutaFinal = r.ruta;
      cambios.push('Ruta');
    }

    // 13. GPS Latitud y Longitud
    let latitudFinal = (orig.latitud !== null && orig.latitud !== undefined && orig.latitud !== '') ? orig.latitud : (r.latitud ?? '');
    let longitudFinal = (orig.longitud !== null && orig.longitud !== undefined && orig.longitud !== '') ? orig.longitud : (r.longitud ?? '');
    if (r.tiene_gps && r.latitud && r.longitud) {
      if (orig.latitud === null || orig.latitud === undefined || isDiferente(orig.latitud, r.latitud) || isDiferente(orig.longitud, r.longitud)) {
        latitudFinal = r.latitud;
        longitudFinal = r.longitud;
        cambios.push('Coordenadas GPS');
      }
    }

    const row = [
      r.cliente_id || orig.cliente_id || '',
      orig.nodo_id ?? '',
      orig.nodo ?? '',
      clienteFinal,
      orig.vendedor || r.vendedor || '',
      orig.cod_vendedor ?? '',
      tipoNegocioFinal,
      territorioFinal,
      orig.mercado ?? '',
      zonaVentaFinal,
      precioFinal,
      orig.cluster_t ?? '',
      orig.cluster_c ?? '',
      rutaFinal,
      orig.dia ?? '',
      orig.dia_norm ?? '',
      orig.secuencia_venta ?? '',
      orig.secuencia_distribuidor ?? '',
      contactoFinal,
      orig.ci ?? '',
      telefonoFinal,
      celularFinal,
      direccionFinal,
      orig.nro ?? '',
      referenciaFinal,
      nombreFacturaFinal,
      nitFinal,
      orig.tipo_pago ?? '',
      orig.dias_credito ?? '',
      orig.limite ?? '',
      latitudFinal,
      longitudFinal,
      orig.estado || 'Activo',
      r.id,
      r.estado_revision || 'PENDIENTE',
      cambios.length > 0 ? cambios.join(', ') : 'Sin cambios detectados',
      cambios.length,
      r.distancia_desplazamiento_m !== null && r.distancia_desplazamiento_m !== undefined ? `${r.distancia_desplazamiento_m} m` : 'Sin cambio',
      r.fecha_registro || '',
      r.usuario || '',
    ];

    rows.push(row);
  });

  const fechaHoy = new Date().toISOString().slice(0, 10);
  const nombreArchivo = `ediciones_pendientes_ruteo_${fechaHoy}.xlsx`;

  descargarExcelXlsx(rows, nombreArchivo, 'Ediciones Pendientes');
  mostrarToast(`Se descargaron ${lista.length} cliente(s) con edición comparados y actualizados con pan_ruteo en Excel (.xlsx).`);
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

function abrirLightbox(url) {
  lightboxUrl.value = url;
  if (url) {
    abrirFotosRegistroActual();
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

      const itemEnLista = registros.value.find(r => r.id === registro.id);
      if (itemEnLista) {
        itemEnLista.estado_revision = res.data.estado_revision;
        itemEnLista.revisado_at = res.data.revisado_at;
      }

      recalcularKpis();

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

async function cargarDatos() {
  cargando.value = true;
  try {
    const res = await axios.post(route('supervisor.altas-ediciones.data'), {
      tipos: filtroTipos.value,
      estados: filtroEstados.value,
      canales: filtroCanales.value,
      rutas: filtroRutas.value,
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
    if (modalFotos.abierto || modalFotos.open) {
      cerrarModalFotos();
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