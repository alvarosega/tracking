<template>
  <SupervisorLayout>
    <div class="h-[calc(100vh-3rem)] md:h-[calc(100vh-3.5rem)] flex flex-col -m-5 md:-m-8 overflow-hidden bg-[#F5F5F7]">
      
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
          
          <!-- Filtro: Tipo de Registro (Altas / Ediciones) -->
          <button
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

        <!-- Indicador de Carga, Control Móvil y Badges -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            Consultando solicitudes...
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
              {{ registroSeleccionado ? 'Ficha' : `Lista (${registrosFiltrados.length})` }}
            </button>
          </div>

          <div class="hidden lg:flex px-2 py-0.5 bg-[#EBF9EF] text-[#248A3D] border border-[#E5E5EA] rounded-[6px] items-center gap-1 font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#248A3D]"></span>
            <span>Altas: {{ kpis.altas }}</span>
          </div>

          <div class="hidden lg:flex px-2 py-0.5 bg-[#EFF6FF] text-[#0071E3] border border-[#E5E5EA] rounded-[6px] items-center gap-1 font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
            <span>Ediciones: {{ kpis.ediciones }}</span>
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
                class="p-3 hover:bg-[#FBFBFD] transition-colors cursor-pointer"
                :class="registroActivoId === r.id ? 'bg-[#F2F2F7]' : 'bg-white'"
              >
                <div class="flex items-start justify-between gap-1.5">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <!-- Badge Tipo de Registro -->
                      <span
                        class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white shrink-0"
                        :class="getTipoColor(r.tipo_registro)"
                      >
                        {{ r.tipo_registro }}
                      </span>

                      <span class="text-xs font-bold text-[#1D1D1F] line-clamp-1">{{ r.cliente }}</span>
                    </div>

                    <p class="text-[11px] text-[#6E6E73] mt-0.5 line-clamp-1">{{ r.direccion || 'Sin dirección registrada' }}</p>
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

                <!-- Barra Inferior de la Tarjeta -->
                <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-[#E5E5EA]/70 text-[10px] font-mono text-[#86868B]">
                  <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getCanalColor(r.canal) }"></span>
                    <span class="text-[#1D1D1F] font-medium">{{ r.ruta }}</span>
                    <span>•</span>
                    <span>{{ r.fecha_registro }}</span>
                    <span v-if="r.distancia_desplazamiento_m" class="text-[#0071E3] font-bold">
                      Desv: {{ r.distancia_desplazamiento_m }}m
                    </span>
                  </div>

                  <span class="text-[#0071E3] font-sans font-medium text-[11px]">Ver ficha &rarr;</span>
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
              
              <!-- 1. Datos Maestros de Identificación -->
              <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-3 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                      <span
                        class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white"
                        :class="getTipoColor(registroSeleccionado.tipo_registro)"
                      >
                        {{ registroSeleccionado.tipo_registro }}
                      </span>
                      <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight">
                        {{ registroSeleccionado.cliente }}
                      </h3>
                    </div>
                    <p v-if="registroSeleccionado.nombre_factura" class="text-[11px] text-[#6E6E73] mt-0.5">
                      <span class="text-[#86868B]">Razón Social:</span> {{ registroSeleccionado.nombre_factura }}
                    </p>
                  </div>

                  <div class="text-right shrink-0">
                    <span
                      class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded border inline-block"
                      :class="getEstadoRevisionClass(registroSeleccionado.estado_revision)"
                    >
                      {{ registroSeleccionado.estado_revision }}
                    </span>
                    <span v-if="registroSeleccionado.revisado_at" class="text-[9px] text-[#86868B] block mt-0.5 font-mono">
                      {{ registroSeleccionado.revisado_at }}
                    </span>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-[#E5E5EA] text-[11px] font-mono">
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Código Interno</span>
                    <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.cliente_id || 'Pendiente de asignación' }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Ruta / Canal</span>
                    <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.ruta }}</span>
                    <span class="text-[#86868B] text-[10px]"> ({{ registroSeleccionado.canal }})</span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Tiene Doc. Identidad</span>
                    <span class="font-semibold" :class="registroSeleccionado.nit ? 'text-[#248A3D]' : 'text-[#86868B]'">
                      {{ registroSeleccionado.nit ? 'SÍ' : 'NO' }}
                    </span>
                  </div>
                  <div>
                    <span class="text-[9px] text-[#86868B] uppercase block">Nro. Documento / NIT</span>
                    <span class="font-semibold text-[#1D1D1F]">{{ registroSeleccionado.nit || 'No registrado' }}</span>
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
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider block mb-1.5">Fotografía de Fachada</span>
                <div
                  v-if="registroSeleccionado.photo_url"
                  @click="abrirLightbox(registroSeleccionado.photo_url)"
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
                <div v-else class="h-24 rounded-[6px] bg-[#F2F2F7] flex items-center justify-center text-xs text-[#86868B]">
                  Sin evidencia fotográfica registrada
                </div>
              </div>

              <!-- 6. Comparativa de Modificaciones (Diff) si es Edición -->
              <div v-if="registroSeleccionado.es_edicion" class="bg-white border border-[#E5E5EA] rounded-[8px] p-2.5">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Modificaciones Solicitadas</span>
                </div>

                <div class="divide-y divide-[#E5E5EA] text-[11px] font-mono">
                  <div class="py-1.5 grid grid-cols-2 gap-2">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Nombre Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.cliente || 'Sin datos' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block">Nombre Propuesto:</span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.cliente }}</span>
                    </div>
                  </div>

                  <div class="py-1.5 grid grid-cols-2 gap-2">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">Dirección Anterior:</span>
                      <span class="text-[#6E6E73]">{{ registroSeleccionado.original?.direccion || 'Sin datos' }}</span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block">Dirección Propuesta:</span>
                      <span class="font-bold text-[#1D1D1F]">{{ registroSeleccionado.direccion }}</span>
                    </div>
                  </div>

                  <div class="py-1.5 grid grid-cols-2 gap-2">
                    <div>
                      <span class="text-[9px] text-[#86868B] block">GPS Anterior:</span>
                      <span class="text-[#6E6E73] text-[10px]">
                        {{ formatCoord(registroSeleccionado.original?.latitud) }}, {{ formatCoord(registroSeleccionado.original?.longitud) }}
                      </span>
                    </div>
                    <div>
                      <span class="text-[9px] text-[#0071E3] block">GPS Propuesto:</span>
                      <span class="font-bold text-[#1D1D1F] text-[10px]">
                        {{ formatCoord(registroSeleccionado.latitud) }}, {{ formatCoord(registroSeleccionado.longitud) }}
                      </span>
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
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, Teleport } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
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
  tipos_registro: {
    type: Array,
    default: () => ['ALTA', 'EDICION'],
  },
  estados_revision: {
    type: Array,
    default: () => ['PENDIENTE', 'APROBADO', 'RECHAZADO'],
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
  if (t.includes('EDIC')) return 'bg-[#0071E3]';
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
const filtroTipos = ref([...props.tipos_registro]);
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
    (r.ruta && r.ruta.toLowerCase().includes(q)) ||
    (r.direccion && r.direccion.toLowerCase().includes(q)) ||
    (r.telefono && String(r.telefono).includes(q)) ||
    (r.nit && String(r.nit).includes(q))
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
    popupContent.style.minWidth = '210px';

    popupContent.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
        <span style="font-weight: 700; font-size: 12px; color: #1D1D1F; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
          ${r.cliente}
        </span>
        <span style="font-family: monospace; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; background-color: ${pinColor}; color: white;">
          ${r.tipo_registro}
        </span>
      </div>
      <div style="color: #6E6E73; margin-bottom: 4px; font-size: 10px;">
        Ruta: <strong>${r.ruta}</strong> (${r.canal})
      </div>
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
        <span>Ver detalles de solicitud</span>
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