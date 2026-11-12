<template>
  <SupervisorLayout title="Auditoría | Cambios de Ruteo por Cliente">
    <div class="space-y-3 max-w-full font-sans select-none pb-8">
      <AuditoriaSubTabs active-tab="clientes" />

      <!-- TARJETAS DE MÉTRICAS GLOBALES (KPIs POR CLIENTE) -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
        <!-- 1. Total Clientes con Cambios -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Clientes con Cambios</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#1D1D1F] font-mono mt-0.5">
              {{ metricas.total_clientes.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5">Cartera intervenida</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-[#0071E3]/10 text-[#0071E3] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
          </div>
        </div>

        <!-- 2. Clientes con Pendientes -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#B7791F] uppercase tracking-wider">Con Pendientes</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#B7791F] font-mono mt-0.5">
              {{ metricas.clientes_con_pendientes.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#86868B] mt-0.5">Requieren dictamen</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-[#B7791F]/10 text-[#B7791F] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
          </div>
        </div>

        <!-- 3. Clientes 100% Resueltos -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#1E6B37] uppercase tracking-wider">100% Resueltos</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#1E6B37] font-mono mt-0.5">
              {{ metricas.clientes_resueltos.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#86868B] mt-0.5">Sin pendientes</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-[#1E6B37]/10 text-[#1E6B37] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>

        <!-- 4. Total Modificaciones -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Total Cambios</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#1D1D1F] font-mono mt-0.5">
              {{ metricas.total_eventos.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5">Eventos agrupados</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-black/[0.05] text-[#1D1D1F] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- PANEL DE CONTROL Y FILTROS -->
      <div class="sticky top-0 z-30 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70">
        <!-- NIVEL 1: FILTROS MULTIFACÉTICOS -->
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Canal -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
              <select
                v-model="filtros.canal"
                @change="onCanalChange"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <!-- Selector de Ruta -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
              <select
                v-model="filtros.ruta"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todas las Rutas</option>
                <option v-for="r in rutasOpciones" :key="r.ruta" :value="r.ruta">{{ r.ruta }}</option>
              </select>
            </div>

            <!-- Estado de Auditoría del Cliente -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Estado:</span>
              <select
                v-model="filtros.estado_filtro"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Clientes</option>
                <option value="CON_PENDIENTES">Con Cambios Pendientes</option>
                <option value="100_RESUELTOS">100% Resueltos</option>
                <option value="SOLO_PENDIENTES">Solo Pendientes</option>
              </select>
            </div>

            <!-- Tipo de Cambio / Categoría -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Tipo Cambio:</span>
              <select
                v-model="filtros.categoria"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Tipos</option>
                <option value="CAMBIOS_RUTA">Cambios de Ruta</option>
                <option value="CAMBIOS_DIA">Cambios de Día</option>
                <option value="MODIFICADO_COORDENADAS">Coordenadas GPS</option>
                <option value="CAMBIO_DE_CLUSTER">Cluster / Negocio</option>
                <option value="ALTAS_REACTIVACIONES">Altas / Reactivados</option>
                <option value="BAJAS_INACTIVADOS">Bajas / Inactivados</option>
                <option value="MODIFICADO_VENDEDOR">Vendedor</option>
                <option value="FRECUENCIA_VISITA">Frecuencia Visita</option>
                <option value="MODIFICACION_DATOS">Datos Generales</option>
              </select>
            </div>

            <!-- Selector de Archivo Base -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] max-w-xs">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Archivo:</span>
              <select
                v-model="filtros.archivo_origen"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer truncate max-w-[170px]"
              >
                <option value="TODOS">Todos los Archivos</option>
                <option v-for="a in archivos_base" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>

            <!-- Rango Fechas -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Desde:</span>
              <input
                type="date"
                v-model="filtros.fecha_inicio"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Hasta:</span>
              <input
                type="date"
                v-model="filtros.fecha_fin"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
            </div>

            <!-- Botón Limpiar -->
            <button
              type="button"
              @click="restablecerFiltros"
              class="apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] text-[11px] font-semibold px-2.5 py-1 rounded-[10px] cursor-pointer transition-all"
            >
              Limpiar
            </button>
          </div>

          <!-- Buscador en tiempo real por Cliente / ID / Dirección -->
          <div class="relative w-full sm:w-64">
            <input
              type="text"
              v-model="filtros.busqueda"
              @input="onBusquedaInput"
              placeholder="Buscar por ID, Cliente o Ruta..."
              class="w-full bg-[#F5F5F7] text-[#1D1D1F] text-[11px] font-medium placeholder-[#86868B] rounded-[10px] pl-7 pr-3 py-1.5 border border-transparent focus:border-[#0071E3] focus:bg-white focus:outline-none transition-all"
            />
            <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70">
        <div class="inline-block w-8 h-8 border-3 border-[#0071E3]/20 border-t-[#0071E3] rounded-full animate-spin"></div>
        <div class="text-xs font-semibold text-[#1D1D1F] mt-3">Agrupando auditoría por código de cliente...</div>
        <div class="text-[11px] text-[#86868B] mt-0.5">Consolidando historial de cambios intra-día</div>
      </div>

      <!-- LISTA DE CLIENTES CON CAMBIOS AGRUPADOS -->
      <div v-else-if="clientes.length > 0" class="space-y-3">
        <div
          v-for="cli in clientes"
          :key="cli.cliente_id"
          class="apple-glass-card rounded-[16px] overflow-hidden border border-white/80 shadow-2xs transition-all duration-200"
          :class="clienteExpandido === cli.cliente_id ? 'ring-1 ring-[#0071E3]/30 shadow-md' : 'hover:shadow-sm'"
        >
          <!-- CABECERA DEL CLIENTE -->
          <div class="p-3 sm:p-4 bg-white/70 backdrop-blur-md flex flex-col md:flex-row md:items-center justify-between gap-3">
            <!-- Datos del Cliente -->
            <div class="flex items-start gap-3 min-w-0">
              <!-- Avatar / Badge ID -->
              <div class="w-10 h-10 rounded-[12px] bg-[#0071E3]/10 text-[#0071E3] flex flex-col items-center justify-center shrink-0 border border-[#0071E3]/20 font-mono">
                <span class="text-[8px] font-bold text-[#86868B] uppercase">ID</span>
                <span class="text-xs font-extrabold leading-none">{{ cli.cliente_id }}</span>
              </div>

              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                  <h3 class="text-sm font-bold text-[#1D1D1F] truncate">{{ cli.cliente }}</h3>
                  
                  <!-- Badges de Rutas y Canal -->
                  <span class="px-2 py-0.5 rounded-[6px] text-[10px] font-mono font-bold bg-[#F5F5F7] text-[#1D1D1F] border border-black/[0.06]">
                    Ruta: {{ cli.ruta }}
                  </span>
                  <span v-if="cli.canal && cli.canal !== 'SIN CANAL'" class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold bg-[#0071E3]/10 text-[#0071E3]">
                    {{ cli.canal }}
                  </span>
                  <span v-if="cli.dia && cli.dia !== 'SIN DIA'" class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold bg-[#1E6B37]/10 text-[#1E6B37]">
                    {{ cli.dia }}
                  </span>
                </div>

                <div class="text-[11px] text-[#6E6E73] truncate mt-0.5">
                  <span>{{ cli.direccion }}</span>
                  <span v-if="cli.vendedor && cli.vendedor !== 'Sin Asignar'" class="text-[#86868B]"> • Vendedor: {{ cli.vendedor }}</span>
                </div>

                <!-- Resumen de Tipos de Cambios que tiene este cliente -->
                <div class="flex flex-wrap items-center gap-1 mt-2">
                  <span
                    v-for="tipo in cli.tipos_eventos"
                    :key="tipo"
                    class="px-2 py-0.5 rounded-[6px] text-[9px] font-mono font-bold border"
                    :class="obtenerBadgeEventoClase(tipo)"
                  >
                    {{ formatearTipoEvento(tipo) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Resumen de Estados & Acciones del Cliente -->
            <div class="flex flex-wrap items-center justify-between md:justify-end gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-black/[0.04]">
              <!-- Estado de resolución de este cliente -->
              <div class="flex items-center gap-1.5 text-xs font-semibold font-mono">
                <span
                  v-if="cli.pendientes > 0"
                  class="px-2.5 py-1 rounded-[8px] bg-[#FFF8E6] text-[#B7791F] border border-[#FCE8B3] font-bold flex items-center gap-1"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-[#B7791F] animate-pulse"></span>
                  <span>{{ cli.pendientes }} pendiente(s)</span>
                </span>
                <span
                  v-if="cli.aprobados > 0"
                  class="px-2.5 py-1 rounded-[8px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]"
                >
                  {{ cli.aprobados }} aprobado(s)
                </span>
                <span
                  v-if="cli.rechazados > 0"
                  class="px-2.5 py-1 rounded-[8px] bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]"
                >
                  {{ cli.rechazados }} rechazado(s)
                </span>
              </div>

              <!-- Botones de Acción -->
              <div class="flex items-center gap-1.5">
                <!-- Botón Ver Fotos -->
                <button
                  type="button"
                  @click="abrirFotosCliente(cli)"
                  class="px-2.5 py-1 rounded-[8px] text-[11px] font-semibold text-[#0071E3] bg-[#0071E3]/10 hover:bg-[#0071E3]/15 transition-all flex items-center gap-1 cursor-pointer"
                  title="Ver fotos de visita"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                  </svg>
                  <span class="hidden sm:inline">Fotos</span>
                </button>

                <!-- Botón Aprobar Todos los Pendientes de este cliente -->
                <button
                  v-if="cli.pendientes > 0"
                  type="button"
                  @click="abrirModalAprobarTodos(cli)"
                  class="px-2.5 py-1 rounded-[8px] text-[11px] font-bold text-white bg-[#1E6B37] hover:bg-[#16562B] transition-all flex items-center gap-1 shadow-2xs cursor-pointer"
                  title="Aprobar todos los cambios pendientes de este cliente"
                >
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                  <span>Aprobar Todo ({{ cli.pendientes }})</span>
                </button>

                <!-- Conmutador Expandir / Colapsar Cambios -->
                <button
                  type="button"
                  @click="alternarExpandir(cli.cliente_id)"
                  class="px-3 py-1 rounded-[8px] text-[11px] font-bold transition-all flex items-center gap-1 cursor-pointer"
                  :class="clienteExpandido === cli.cliente_id ? 'bg-[#0071E3] text-white shadow-2xs' : 'bg-black/[0.05] text-[#1D1D1F] hover:bg-black/[0.08]'"
                >
                  <span>{{ clienteExpandido === cli.cliente_id ? 'Ocultar' : `Historial (${cli.total_cambios})` }}</span>
                  <svg
                    class="w-3.5 h-3.5 transition-transform duration-200"
                    :class="clienteExpandido === cli.cliente_id ? 'rotate-180 text-white' : 'text-[#6E6E73]'"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- HISTORIAL DESPLEGABLE DE CAMBIOS DE ESTE CLIENTE -->
          <div v-if="clienteExpandido === cli.cliente_id" class="border-t border-black/[0.06] bg-black/[0.01] p-3 sm:p-4 space-y-2.5">
            <div class="flex items-center justify-between text-[11px] font-bold text-[#86868B] uppercase tracking-wider px-1">
              <span>Cronología de Cambios ({{ cli.cambios.length }} eventos)</span>
              <span>ID Cliente: {{ cli.cliente_id }}</span>
            </div>

            <div class="space-y-2">
              <div
                v-for="ev in cli.cambios"
                :key="ev.id"
                class="apple-glass-card rounded-[12px] p-3 border border-white/90 bg-white/90 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs"
              >
                <!-- Detalle del Evento y Campo -->
                <div class="space-y-1.5 flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span
                      class="px-2 py-0.5 rounded-[6px] text-[9px] font-mono font-bold border"
                      :class="obtenerBadgeEventoClase(ev.tipo_evento)"
                    >
                      {{ formatearTipoEvento(ev.tipo_evento) }}
                    </span>
                    <span class="font-bold text-[#1D1D1F] text-xs">
                      Campo: <code class="font-mono text-[#0071E3] bg-[#0071E3]/5 px-1 py-0.5 rounded">{{ ev.campo_afectado }}</code>
                    </span>
                    <span class="text-[10px] text-[#86868B] font-mono">
                      • {{ ev.fecha_auditoria_fmt }}
                    </span>
                    <span v-if="ev.archivo_origen" class="text-[10px] text-[#86868B] truncate max-w-xs">
                      ({{ ev.archivo_origen }})
                    </span>
                  </div>

                  <!-- Comparación Visual de Valores (Anterior -> Nuevo) -->
                  <div class="flex flex-wrap items-center gap-2 pt-1 font-mono text-[11px]">
                    <div class="px-2 py-1 rounded-[6px] bg-[#F5F5F7] text-[#6E6E73] border border-black/[0.05] line-through max-w-xs truncate" title="Valor Anterior">
                      {{ ev.valor_anterior }}
                    </div>
                    <svg class="w-3.5 h-3.5 text-[#0071E3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                    <div class="px-2 py-1 rounded-[6px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9] font-bold max-w-xs truncate" title="Valor Nuevo Propuesto">
                      {{ ev.valor_nuevo }}
                    </div>
                  </div>

                  <!-- Observaciones y Auditor si ya está resuelto -->
                  <div v-if="ev.observaciones || ev.auditado_por" class="text-[10px] text-[#86868B] italic pt-0.5">
                    <span v-if="ev.auditado_por">Auditado por {{ ev.auditado_por }}</span>
                    <span v-if="ev.fecha_resolucion"> el {{ ev.fecha_resolucion }}</span>
                    <span v-if="ev.observaciones"> — "{{ ev.observaciones }}"</span>
                  </div>
                </div>

                <!-- Estado del Cambio y Acciones Individuales -->
                <div class="flex items-center gap-2 shrink-0 md:pl-4 md:border-l border-black/[0.06]">
                  <!-- Si está PENDIENTE: Botones para resolver -->
                  <div v-if="ev.estado_auditoria === 'PENDIENTE'" class="flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="abrirModalResolucion([ev.id], 'APROBADO', `Aprobar cambio individual de ${ev.campo_afectado}`)"
                      class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold text-[#1E6B37] bg-[#EBF9EF] hover:bg-[#D1F2D9] border border-[#D1F2D9] transition-all cursor-pointer shadow-2xs"
                    >
                      Aprobar
                    </button>
                    <button
                      type="button"
                      @click="abrirModalResolucion([ev.id], 'RECHAZADO', `Rechazar cambio individual de ${ev.campo_afectado}`)"
                      class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold text-[#C9342C] bg-[#FDF0EF] hover:bg-[#FADCD5] border border-[#FADCD5] transition-all cursor-pointer shadow-2xs"
                    >
                      Rechazar
                    </button>
                    <button
                      type="button"
                      @click="abrirModalResolucion([ev.id], 'DESCARTADO', `Descartar cambio de ${ev.campo_afectado}`)"
                      class="px-2 py-1 rounded-[7px] text-[11px] font-semibold text-[#6E6E73] bg-black/[0.04] hover:bg-black/[0.08] transition-all cursor-pointer"
                    >
                      Descartar
                    </button>
                  </div>

                  <!-- Si ya está RESUELTO: Badge de estado final -->
                  <div v-else class="flex items-center gap-1.5">
                    <span
                      class="px-2.5 py-1 rounded-[7px] text-[10px] font-mono font-bold uppercase border"
                      :class="obtenerBadgeEstadoClase(ev.estado_auditoria)"
                    >
                      {{ ev.estado_auditoria }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ESTADO VACÍO -->
      <div v-else class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70">
        <div class="w-12 h-12 rounded-full bg-black/[0.04] text-[#86868B] flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
          </svg>
        </div>
        <div class="text-sm font-bold text-[#1D1D1F]">No se encontraron clientes con cambios</div>
        <div class="text-xs text-[#86868B] mt-1 max-w-sm mx-auto">
          Prueba cambiando los filtros de canal, ruta o estado, o restablece los filtros con el botón Limpiar.
        </div>
      </div>

      <!-- PAGINACIÓN -->
      <div v-if="totalClientes > 0" class="apple-glass-card rounded-[16px] overflow-hidden border border-white/80 shadow-2xs">
        <AuditoriaPaginacion
          :page="pagina"
          :per-page="perPage"
          :total="totalClientes"
          :total-paginas="lastPage"
          :desde="from"
          :hasta="to"
          :loading="cargando"
          etiqueta="clientes"
          @cambiar-pagina="cambiarPagina"
          @cambiar-per-page="cambiarPerPage"
        />
      </div>

      <!-- MODAL DE DICTAMEN / RESOLUCIÓN -->
      <div
        v-if="modalResolucion.abierto"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs select-none"
        @click.self="modalResolucion.abierto = false"
      >
        <div class="apple-glass-card bg-white/95 rounded-[18px] p-5 max-w-md w-full shadow-2xl border border-white/80 space-y-4">
          <div class="flex items-center justify-between border-b border-black/[0.06] pb-3">
            <div class="flex items-center gap-2">
              <span
                class="w-3 h-3 rounded-full"
                :class="modalResolucion.estado === 'APROBADO' ? 'bg-[#1E6B37]' : modalResolucion.estado === 'RECHAZADO' ? 'bg-[#C9342C]' : 'bg-[#6E6E73]'"
              ></span>
              <h4 class="text-sm font-bold text-[#1D1D1F]">
                {{ modalResolucion.estado === 'APROBADO' ? 'Aprobar Dictamen' : modalResolucion.estado === 'RECHAZADO' ? 'Rechazar Dictamen' : 'Descartar Dictamen' }}
              </h4>
            </div>
            <button
              type="button"
              @click="modalResolucion.abierto = false"
              class="text-[#86868B] hover:text-[#1D1D1F] p-1 rounded-full cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="text-xs text-[#6E6E73] space-y-2">
            <p>
              Estás a punto de registrar la resolución como
              <strong :class="modalResolucion.estado === 'APROBADO' ? 'text-[#1E6B37]' : 'text-[#C9342C]'">
                {{ modalResolucion.estado }}
              </strong>
              para <strong class="text-[#1D1D1F] font-mono">{{ modalResolucion.ids.length }}</strong> evento(s) de auditoría.
            </p>

            <div>
              <label class="block text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1">
                Observaciones (Opcional):
              </label>
              <textarea
                v-model="modalResolucion.observaciones"
                rows="3"
                placeholder="Escribe el motivo del dictamen o notas de auditoría..."
                class="w-full bg-[#F5F5F7] text-[#1D1D1F] text-xs rounded-[10px] p-2.5 border border-transparent focus:border-[#0071E3] focus:bg-white focus:outline-none transition-all resize-none"
              ></textarea>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-black/[0.06]">
            <button
              type="button"
              @click="modalResolucion.abierto = false"
              class="px-3.5 py-1.5 rounded-[9px] text-xs font-semibold text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04] cursor-pointer"
            >
              Cancelar
            </button>
            <button
              type="button"
              @click="ejecutarResolucion"
              :disabled="modalResolucion.procesando"
              class="px-4 py-1.5 rounded-[9px] text-xs font-bold text-white transition-all shadow-sm cursor-pointer disabled:opacity-50"
              :class="modalResolucion.estado === 'APROBADO' ? 'bg-[#1E6B37] hover:bg-[#16562B]' : modalResolucion.estado === 'RECHAZADO' ? 'bg-[#C9342C] hover:bg-[#A92A24]' : 'bg-[#6E6E73] hover:bg-[#525257]'"
            >
              <span v-if="modalResolucion.procesando">Guardando...</span>
              <span v-else>Confirmar Dictamen</span>
            </button>
          </div>
        </div>
      </div>

      <!-- MODAL DE FOTOS DE VISITA -->
      <AuditoriaFotosModal ref="fotosModalRef" />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSubTabs from '@/Pages/Supervisor/Auditoria/Components/AuditoriaSubTabs.vue';
import AuditoriaPaginacion from '@/Pages/Supervisor/Auditoria/Components/AuditoriaPaginacion.vue';
import AuditoriaFotosModal from '@/Pages/Supervisor/Auditoria/Components/AuditoriaFotosModal.vue';

const props = defineProps({
  canales: {
    type: Array,
    default: () => [],
  },
  rutas: {
    type: Array,
    default: () => [],
  },
  archivos_base: {
    type: Array,
    default: () => [],
  },
  initial_categoria: {
    type: String,
    default: 'TODOS',
  },
  initial_estado: {
    type: String,
    default: 'TODOS',
  },
});

const cargando = ref(false);
const clientes = ref([]);
const totalClientes = ref(0);
const pagina = ref(1);
const perPage = ref(25);
const lastPage = ref(1);
const from = ref(0);
const to = ref(0);

const metricas = reactive({
  total_clientes: 0,
  total_eventos: 0,
  clientes_con_pendientes: 0,
  clientes_resueltos: 0,
});

const filtros = reactive({
  canal: 'TODOS',
  ruta: 'TODOS',
  archivo_origen: 'TODOS',
  estado_filtro: props.initial_estado || 'TODOS',
  categoria: props.initial_categoria || 'TODOS',
  fecha_inicio: '',
  fecha_fin: '',
  busqueda: '',
});

const clienteExpandido = ref(null);
const fotosModalRef = ref(null);

const modalResolucion = reactive({
  abierto: false,
  ids: [],
  estado: 'APROBADO',
  observaciones: '',
  procesando: false,
});

let searchDebounceTimer = null;

const rutasOpciones = computed(() => {
  if (filtros.canal === 'TODOS') return props.rutas;
  return props.rutas.filter(r => r.canal === filtros.canal);
});

onMounted(() => {
  consultar(true);
});

function onCanalChange() {
  filtros.ruta = 'TODOS';
  consultar(true);
}

function onBusquedaInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    consultar(true);
  }, 350);
}

function restablecerFiltros() {
  filtros.canal = 'TODOS';
  filtros.ruta = 'TODOS';
  filtros.archivo_origen = 'TODOS';
  filtros.estado_filtro = 'TODOS';
  filtros.categoria = 'TODOS';
  filtros.fecha_inicio = '';
  filtros.fecha_fin = '';
  filtros.busqueda = '';
  clienteExpandido.value = null;
  consultar(true);
}

function alternarExpandir(clienteId) {
  clienteExpandido.value = clienteExpandido.value === clienteId ? null : clienteId;
}

async function consultar(reiniciarPagina = false) {
  if (reiniciarPagina) {
    pagina.value = 1;
  }
  cargando.value = true;

  try {
    const params = {
      canal: filtros.canal !== 'TODOS' ? filtros.canal : '',
      rutas: filtros.ruta !== 'TODOS' ? [filtros.ruta] : [],
      archivo_origen: filtros.archivo_origen !== 'TODOS' ? filtros.archivo_origen : '',
      estado_filtro: filtros.estado_filtro,
      categoria: filtros.categoria !== 'TODOS' ? filtros.categoria : '',
      fecha_inicio: filtros.fecha_inicio,
      fecha_fin: filtros.fecha_fin,
      busqueda: filtros.busqueda.trim(),
      page: pagina.value,
      per_page: perPage.value,
    };

    const url = typeof route === 'function'
      ? route('supervisor.auditoria.clientes.consultar')
      : '/supervisor/auditoria/clientes/consultar';

    const { data } = await axios.post(url, params);

    clientes.value = data.clientes || [];
    totalClientes.value = data.total || 0;
    pagina.value = data.page || 1;
    perPage.value = data.per_page || 25;
    lastPage.value = data.last_page || 1;
    from.value = data.from || 0;
    to.value = data.to || 0;

    if (data.metricas) {
      metricas.total_clientes = data.metricas.total_clientes || 0;
      metricas.total_eventos = data.metricas.total_eventos || 0;
      metricas.clientes_con_pendientes = data.metricas.clientes_con_pendientes || 0;
      metricas.clientes_resueltos = data.metricas.clientes_resueltos || 0;
    }

    // Si había un cliente expandido que ya no existe en la página, colapsar
    if (clienteExpandido.value && !clientes.value.some(c => c.cliente_id === clienteExpandido.value)) {
      clienteExpandido.value = null;
    }
  } catch (e) {
    console.error('Error al consultar auditoría por clientes:', e);
  } finally {
    cargando.value = false;
  }
}

function cambiarPagina(p) {
  pagina.value = p;
  consultar(false);
}

function cambiarPerPage(limit) {
  perPage.value = limit;
  consultar(true);
}

function abrirFotosCliente(cli) {
  if (fotosModalRef.value) {
    fotosModalRef.value.abrir(cli.cliente_id, cli.cliente, cli.ruta);
  }
}

function abrirModalAprobarTodos(cli) {
  const idsPendientes = (cli.cambios || [])
    .filter(ev => ev.estado_auditoria === 'PENDIENTE')
    .map(ev => ev.id);

  if (idsPendientes.length === 0) return;
  abrirModalResolucion(idsPendientes, 'APROBADO', `Aprobación masiva de todos los cambios pendientes del cliente ID ${cli.cliente_id}`);
}

function abrirModalResolucion(ids, estado, observaciones = '') {
  modalResolucion.ids = ids;
  modalResolucion.estado = estado;
  modalResolucion.observaciones = observaciones;
  modalResolucion.abierto = true;
}

async function ejecutarResolucion() {
  if (modalResolucion.ids.length === 0) return;
  modalResolucion.procesando = true;

  try {
    const url = typeof route === 'function'
      ? route('supervisor.auditoria.pendientes.resolver')
      : '/supervisor/auditoria/pendientes/resolver';

    await axios.post(url, {
      ids: modalResolucion.ids,
      estado: modalResolucion.estado,
      observaciones: modalResolucion.observaciones,
    });

    modalResolucion.abierto = false;
    await consultar(false);
  } catch (e) {
    alert('Error al dictaminar: ' + (e.response?.data?.error || e.message));
  } finally {
    modalResolucion.procesando = false;
  }
}

function formatearTipoEvento(tipo) {
  switch (tipo) {
    case 'MODIFICADO_RUTA':
      return 'Cambio de Ruta';
    case 'MODIFICADO_DIA':
      return 'Cambio de Día';
    case 'MODIFICADO_COORDENADAS':
      return 'Coordenadas GPS';
    case 'CAMBIO_DE_CLUSTER':
      return 'Cluster / Tipo Negocio';
    case 'MODIFICADO_VENDEDOR':
      return 'Vendedor';
    case 'NUEVO':
    case 'REACTIVADO':
      return 'Alta / Reactivación';
    case 'ELIMINADO':
    case 'INACTIVADO':
      return 'Baja / Inactivado';
    case 'MODIFICADO':
      return 'Modificación Datos';
    default:
      return tipo;
  }
}

function obtenerBadgeEventoClase(tipo) {
  switch (tipo) {
    case 'MODIFICADO_RUTA':
      return 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]';
    case 'MODIFICADO_DIA':
      return 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
    case 'MODIFICADO_COORDENADAS':
      return 'bg-[#FEF5E7] text-[#D35400] border-[#FADBD8]';
    case 'CAMBIO_DE_CLUSTER':
      return 'bg-[#F5EEF8] text-[#8E44AD] border-[#E8DAEF]';
    case 'MODIFICADO_VENDEDOR':
      return 'bg-[#E8F8F5] text-[#16A085] border-[#D1F2EB]';
    case 'NUEVO':
    case 'REACTIVADO':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
    case 'ELIMINADO':
    case 'INACTIVADO':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
    default:
      return 'bg-[#F5F5F7] text-[#6E6E73] border-black/[0.06]';
  }
}

function obtenerBadgeEstadoClase(estado) {
  switch (estado) {
    case 'APROBADO':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
    case 'RECHAZADO':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
    case 'DESCARTADO':
      return 'bg-black/[0.05] text-[#6E6E73] border-black/[0.08]';
    default:
      return 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
  }
}
</script>
