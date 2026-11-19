<template>
  <SupervisorLayout title="Auditoría | Matriz de Cambios">
    <div class="space-y-3.5 max-w-full font-sans select-none pb-12">
      <AuditoriaSubTabs active-tab="clientes" />

      <!-- TARJETAS DE MÉTRICAS GLOBALES (KPIs DE MATRIZ) -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
        <!-- 1. Rutas con Cambios -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Rutas Intervenidas</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#1D1D1F] font-mono mt-0.5">
              {{ metricas.total_rutas.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5">Rutas del padrón actual</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-[#0071E3]/10 text-[#0071E3] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689A1.125 1.125 0 003 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
            </svg>
          </div>
        </div>

        <!-- 2. Clientes con Cambios -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Clientes Intervenidos</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#1D1D1F] font-mono mt-0.5">
              {{ metricas.total_clientes.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5">Cartera con modificaciones</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-[#0071E3]/10 text-[#0071E3] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
          </div>
        </div>

        <!-- 3. Clientes con Pendientes -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#B7791F] uppercase tracking-wider">Con Pendientes</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#B7791F] font-mono mt-0.5">
              {{ metricas.clientes_con_pendientes.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#86868B] mt-0.5">Clientes por auditar</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-[#B7791F]/10 text-[#B7791F] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
          </div>
        </div>

        <!-- 4. Total de Modificaciones -->
        <div class="apple-glass-card rounded-[14px] p-3 flex items-center justify-between border border-white/80 shadow-2xs">
          <div>
            <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Total Cambios</div>
            <div class="text-xl sm:text-2xl font-extrabold text-[#1D1D1F] font-mono mt-0.5">
              {{ metricas.total_eventos.toLocaleString('es-BO') }}
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5">Eventos históricos</div>
          </div>
          <div class="w-9 h-9 rounded-full bg-black/[0.05] text-[#1D1D1F] flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- PANEL PRINCIPAL DE CONTROL Y FILTROS -->
      <div class="sticky top-0 z-30 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70">
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
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todas las Rutas</option>
                <option v-for="r in rutasOpciones" :key="r.ruta" :value="r.ruta">{{ r.ruta }}</option>
              </select>
            </div>

            <!-- Estado de Auditoría -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Estado:</span>
              <select
                v-model="filtros.estado_filtro"
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Clientes</option>
                <option value="CON_PENDIENTES">Con Pendientes</option>
                <option value="100_RESUELTOS">100% Resueltos</option>
                <option value="SOLO_PENDIENTES">Solo Pendientes</option>
              </select>
            </div>

            <!-- Tipo de Cambio / Categoría -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Tipo:</span>
              <select
                v-model="filtros.categoria"
                @change="consultar()"
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
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer truncate max-w-[150px]"
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
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Hasta:</span>
              <input
                type="date"
                v-model="filtros.fecha_fin"
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
            </div>

            <!-- Botón Limpiar -->
            <button
              v-if="filtros.canal !== 'TODOS' || filtros.ruta !== 'TODOS' || filtros.archivo_origen !== 'TODOS' || filtros.estado_filtro !== 'TODOS' || filtros.categoria !== 'TODOS' || filtros.fecha_inicio || filtros.fecha_fin || filtros.busqueda"
              type="button"
              @click="restablecerFiltros"
              class="apple-control-pill text-[#6E6E73] hover:text-[#FF3B30] text-[11px] font-semibold px-2.5 py-1 rounded-[10px] cursor-pointer transition-all"
            >
              Limpiar
            </button>
          </div>

          <!-- Buscador en tiempo real por Cliente / ID / Dirección -->
          <div class="flex items-center gap-2 w-full sm:w-auto">
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

            <!-- Botones Expandir / Colapsar Todas las Rutas -->
            <div class="inline-flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="expandirTodasLasRutas"
                class="px-2 py-1 rounded-[8px] bg-black/[0.04] hover:bg-black/[0.08] text-[#1D1D1F] text-[10px] font-semibold transition-all cursor-pointer"
                title="Desplegar todas las rutas"
              >
                Expandir Todo
              </button>
              <button
                type="button"
                @click="colapsarTodasLasRutas"
                class="px-2 py-1 rounded-[8px] bg-black/[0.04] hover:bg-black/[0.08] text-[#6E6E73] hover:text-[#1D1D1F] text-[10px] font-semibold transition-all cursor-pointer"
                title="Cerrar todas las rutas"
              >
                Colapsar Todo
              </button>
            </div>
          </div>

        </div>
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70">
        <div class="inline-block w-8 h-8 border-3 border-[#0071E3]/20 border-t-[#0071E3] rounded-full animate-spin"></div>
        <div class="text-xs font-semibold text-[#1D1D1F] mt-3">Generando Matriz de Cambios por Ruta...</div>
        <div class="text-[11px] text-[#86868B] mt-0.5">Agrupando clientes según su padrón actual en pan_ruteo</div>
      </div>

      <!-- BANDEJA VACÍA -->
      <div
        v-else-if="rutas.length === 0"
        class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70"
      >
        <div class="w-12 h-12 rounded-full bg-black/[0.04] flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-sm font-bold text-[#1D1D1F]">No se encontraron modificaciones</div>
        <div class="text-xs text-[#86868B] mt-1 max-w-md mx-auto">
          No hay registros de cambios de auditoría que coincidan con los filtros seleccionados.
        </div>
      </div>

      <!-- MATRIZ JERÁRQUICA: NIVEL 1: RUTA -> NIVEL 2: CLIENTE -> NIVEL 3: HISTORIAL -->
      <div v-else class="space-y-3">
        <div
          v-for="r in rutas"
          :key="r.ruta"
          class="apple-glass-card rounded-[16px] overflow-hidden border border-white/80 shadow-2xs transition-all duration-200"
          :class="rutasExpandidas.has(r.ruta) ? 'ring-1 ring-[#0071E3]/30 shadow-md' : 'hover:shadow-xs'"
        >
          <!-- CABECERA DE RUTA (NIVEL 1 - COLAPSABLE) -->
          <div
            @click="alternarRuta(r.ruta)"
            class="p-3 sm:p-4 bg-white/75 backdrop-blur-md flex flex-wrap items-center justify-between gap-3 cursor-pointer select-none transition-colors hover:bg-white/90"
          >
            <!-- Identificación de la Ruta -->
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-10 h-10 rounded-[12px] flex items-center justify-center shrink-0 border font-mono font-bold text-xs"
                :class="r.ruta === 'SIN RUTA'
                  ? 'bg-black/[0.05] text-[#86868B] border-black/10'
                  : 'bg-[#0071E3]/10 text-[#0071E3] border-[#0071E3]/20'"
              >
                <svg v-if="r.ruta !== 'SIN RUTA'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689A1.125 1.125 0 003 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                </svg>
                <span v-else class="text-[9px] uppercase">S/R</span>
              </div>

              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="text-sm font-extrabold text-[#1D1D1F] tracking-tight">
                    {{ r.ruta === 'SIN RUTA' ? 'SIN RUTA (Sin asignación en padrón actual)' : `Ruta: ${r.ruta}` }}
                  </h3>
                  
                  <span v-if="r.canal && r.canal !== 'SIN CANAL'" class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold bg-[#0071E3]/10 text-[#0071E3]">
                    {{ r.canal }}
                  </span>
                </div>

                <div class="text-[11px] text-[#6E6E73] flex items-center gap-2 mt-0.5 font-medium">
                  <span><strong>{{ r.total_clientes }}</strong> cliente(s) intervenido(s)</span>
                  <span>&bull;</span>
                  <span><strong>{{ r.total_cambios }}</strong> modificación(es) total(es)</span>
                </div>
              </div>
            </div>

            <!-- Métricas y Controles de la Ruta -->
            <div class="flex items-center gap-2 shrink-0">
              <!-- Estado de la ruta -->
              <span
                v-if="r.pendientes > 0"
                class="px-2.5 py-1 rounded-[8px] bg-[#FFF8E6] text-[#B7791F] border border-[#FCE8B3] font-bold text-xs flex items-center gap-1.5"
              >
                <span class="w-2 h-2 rounded-full bg-[#B7791F] animate-pulse"></span>
                <span>{{ r.pendientes }} pendiente(s)</span>
              </span>

              <span
                v-else
                class="px-2.5 py-1 rounded-[8px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9] font-bold text-xs flex items-center gap-1"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span>100% Resuelta</span>
              </span>

              <!-- Botón Aprobar Todos los Pendientes de esta Ruta -->
              <button
                v-if="r.pendientes > 0"
                type="button"
                @click.stop="abrirModalAprobarRuta(r)"
                class="px-2.5 py-1 rounded-[8px] text-[11px] font-bold text-white bg-[#1E6B37] hover:bg-[#16562B] transition-all flex items-center gap-1 shadow-2xs cursor-pointer ml-1"
                title="Aprobar todos los cambios pendientes de esta ruta"
              >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span>Aprobar Ruta ({{ r.pendientes }})</span>
              </button>

              <!-- Chevron para expandir/colapsar -->
              <div class="p-1 rounded-[6px] bg-black/[0.04] text-[#6E6E73] ml-1">
                <svg
                  class="w-4 h-4 transition-transform duration-200"
                  :class="rutasExpandidas.has(r.ruta) ? 'rotate-180 text-[#0071E3]' : ''"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </div>
            </div>
          </div>

          <!-- LISTA DE CLIENTES DE LA RUTA (NIVEL 2 - CUANDO LA RUTA ESTÁ EXPANDIDA) -->
          <div v-if="rutasExpandidas.has(r.ruta)" class="border-t border-black/[0.06] bg-black/[0.015] p-3 sm:p-4 space-y-2.5 animate-fadeIn">
            <div
              v-for="cli in r.clientes"
              :key="cli.cliente_id"
              class="apple-glass-card rounded-[14px] overflow-hidden border border-white/90 bg-white/85 shadow-2xs transition-all duration-150"
              :class="clientesExpandidos.has(cli.cliente_id) ? 'ring-1 ring-[#0071E3]/25' : ''"
            >
              <!-- FILA DEL CLIENTE -->
              <div
                @click="alternarCliente(cli.cliente_id)"
                class="p-3 sm:p-3.5 flex flex-wrap items-center justify-between gap-2.5 cursor-pointer hover:bg-black/[0.015] transition-colors"
              >
                <!-- Información del Cliente -->
                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                  <div class="w-8 h-8 rounded-[10px] bg-[#0071E3]/10 text-[#0071E3] flex flex-col items-center justify-center shrink-0 border border-[#0071E3]/15 font-mono">
                    <span class="text-[7px] font-bold text-[#86868B] uppercase">ID</span>
                    <span class="text-[11px] font-extrabold leading-none">{{ cli.cliente_id }}</span>
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-bold text-[#1D1D1F] text-xs leading-snug break-words">
                        {{ cli.cliente }}
                      </span>

                      <!-- BADGE DESTACADO: CANTIDAD DE VECES QUE SE CAMBIARON LOS DATOS -->
                      <span
                        class="px-2.5 py-0.5 rounded-full bg-[#0071E3]/10 text-[#0071E3] font-mono font-extrabold text-[11px] border border-[#0071E3]/20"
                        title="Cantidad total de modificaciones registradas en este cliente"
                      >
                        {{ cli.total_cambios }} cambio{{ cli.total_cambios === 1 ? '' : 's' }}
                      </span>

                      <span v-if="cli.dia && cli.dia !== 'SIN DIA'" class="px-2 py-0.2 rounded bg-black/[0.04] text-[#1D1D1F] font-mono text-[10px] capitalize">
                        {{ cli.dia }}
                      </span>
                    </div>

                    <!-- Dirección Completa sin recortar -->
                    <div class="text-[11px] text-[#6E6E73] leading-snug break-words mt-0.5">
                      {{ cli.direccion || 'Sin dirección registrada' }}
                      <span v-if="cli.vendedor && cli.vendedor !== 'Sin Asignar'" class="text-[#86868B]"> &bull; Vendedor: {{ cli.vendedor }}</span>
                    </div>
                  </div>
                </div>

                <!-- Estado del Cliente & Acciones -->
                <div class="flex items-center gap-2 shrink-0">
                  <!-- Badges de estado -->
                  <div class="flex items-center gap-1 font-mono text-xs">
                    <span
                      v-if="cli.pendientes > 0"
                      class="px-2 py-0.5 rounded-[6px] bg-[#FFF8E6] text-[#B7791F] border border-[#FCE8B3] font-bold text-[11px] flex items-center gap-1"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-[#B7791F] animate-pulse"></span>
                      <span>{{ cli.pendientes }} pend.</span>
                    </span>

                    <span
                      v-if="cli.pendientes === 0"
                      class="px-2 py-0.5 rounded-[6px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9] font-bold text-[11px]"
                    >
                      Resuelto
                    </span>
                  </div>

                  <!-- Botón Ver Fotos si tiene -->
                  <button
                    v-if="cli.cant_fotos > 0"
                    type="button"
                    @click.stop="abrirFotosCliente(cli)"
                    class="px-2 py-1 rounded-[7px] text-[10px] font-semibold text-[#0071E3] bg-[#0071E3]/10 hover:bg-[#0071E3]/15 transition-all flex items-center gap-1 cursor-pointer"
                    title="Ver fotos de visita"
                  >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                    </svg>
                    <span>Fotos</span>
                  </button>

                  <!-- Botón Aprobar Todo del Cliente -->
                  <button
                    v-if="cli.pendientes > 0"
                    type="button"
                    @click.stop="abrirModalAprobarCliente(cli)"
                    class="px-2 py-1 rounded-[7px] text-[10px] font-bold text-white bg-[#1E6B37] hover:bg-[#16562B] transition-all flex items-center gap-1 shadow-2xs cursor-pointer"
                    title="Aprobar todos los cambios pendientes de este cliente"
                  >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>Aprobar ({{ cli.pendientes }})</span>
                  </button>

                  <!-- Chevron Historial -->
                  <div class="p-1 rounded-[6px] bg-black/[0.04] text-[#6E6E73]">
                    <svg
                      class="w-3.5 h-3.5 transition-transform duration-200"
                      :class="clientesExpandidos.has(cli.cliente_id) ? 'rotate-180 text-[#0071E3]' : ''"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- HISTORIAL CRONOLÓGICO DE CAMBIOS DEL CLIENTE (NIVEL 3) -->
              <div v-if="clientesExpandidos.has(cli.cliente_id)" class="border-t border-black/[0.06] bg-slate-50/50 p-3 sm:p-3.5 space-y-2 animate-fadeIn">
                <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider flex items-center justify-between pb-1 border-b border-black/[0.04]">
                  <span>Cronología de Modificaciones ({{ cli.cambios.length }} eventos)</span>
                  <span>ID Cliente: #{{ cli.cliente_id }}</span>
                </div>

                <div class="space-y-2">
                  <div
                    v-for="ev in cli.cambios"
                    :key="ev.id"
                    class="p-2.5 rounded-[10px] bg-white border border-black/[0.06] shadow-2xs flex flex-wrap md:flex-nowrap items-center justify-between gap-3 text-xs"
                  >
                    <!-- Información del Cambio y Transición -->
                    <div class="space-y-1.5 flex-1 min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span
                          class="px-2 py-0.5 rounded-[6px] text-[9px] font-mono font-bold border"
                          :class="obtenerBadgeEventoClase(ev.tipo_evento)"
                        >
                          {{ formatearTipoEvento(ev.tipo_evento) }}
                        </span>

                        <span class="font-bold text-[#1D1D1F] text-xs">
                          Campo: <code class="font-mono text-[#0071E3] bg-[#0071E3]/5 px-1 py-0.2 rounded">{{ ev.campo_afectado }}</code>
                        </span>

                        <span class="text-[10px] text-[#86868B] font-mono">
                          &bull; {{ ev.fecha_auditoria_fmt }}
                        </span>

                        <span v-if="ev.archivo_origen" class="text-[10px] text-[#86868B]">
                          ({{ ev.archivo_origen }})
                        </span>
                      </div>

                      <!-- Transición Antes -> Después en texto completo -->
                      <div class="flex items-center gap-2 font-mono text-[11px] flex-wrap pt-0.5">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 line-through opacity-85 break-words max-w-full">
                          {{ ev.valor_anterior }}
                        </span>

                        <svg class="w-3.5 h-3.5 text-[#0071E3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>

                        <span class="px-2 py-0.5 rounded bg-sky-50 text-[#0071E3] font-bold border border-sky-200 break-words max-w-full">
                          {{ ev.valor_nuevo }}
                        </span>
                      </div>

                      <!-- Observaciones o Auditor -->
                      <div v-if="ev.observaciones || ev.auditado_por" class="text-[10px] text-[#86868B] italic pt-0.5">
                        <span v-if="ev.auditado_por">Auditado por {{ ev.auditado_por }}</span>
                        <span v-if="ev.fecha_resolucion"> el {{ ev.fecha_resolucion }}</span>
                        <span v-if="ev.observaciones"> &mdash; "{{ ev.observaciones }}"</span>
                      </div>
                    </div>

                    <!-- Estado y Acciones Individuales del Evento -->
                    <div class="flex items-center gap-2 shrink-0 md:pl-3 md:border-l border-black/[0.06]">
                      <!-- Si está Pendiente -->
                      <div v-if="ev.estado_auditoria === 'PENDIENTE'" class="flex items-center gap-1.5">
                        <button
                          type="button"
                          @click="abrirModalResolucion([ev.id], 'APROBADO', `Aprobar cambio de ${ev.campo_afectado} en ${cli.cliente}`)"
                          class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold text-white bg-[#34C759] hover:bg-[#28A745] transition-all cursor-pointer shadow-2xs"
                        >
                          Aprobar
                        </button>
                        <button
                          type="button"
                          @click="abrirModalResolucion([ev.id], 'RECHAZADO', `Rechazar cambio de ${ev.campo_afectado} en ${cli.cliente}`)"
                          class="px-2.5 py-1 rounded-[7px] text-[11px] font-bold text-white bg-[#FF3B30] hover:bg-[#D93025] transition-all cursor-pointer shadow-2xs"
                        >
                          Rechazar
                        </button>
                      </div>

                      <!-- Si ya está Resuelto -->
                      <div v-else>
                        <span
                          class="px-2.5 py-0.5 rounded-[6px] text-[10px] font-bold border font-mono"
                          :class="ev.estado_auditoria === 'APROBADO'
                            ? 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]'
                            : (ev.estado_auditoria === 'RECHAZADO' ? 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]' : 'bg-black/[0.04] text-[#6E6E73] border-black/10')"
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
        </div>
      </div>

      <!-- MODAL DE RESOLUCIÓN (APROBAR / RECHAZAR / DESCARTAR) -->
      <Teleport to="body">
        <div
          v-if="modalResolucion.abierto"
          @click.self="modalResolucion.abierto = false"
          class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 backdrop-blur-md p-4 select-none font-sans"
        >
          <div class="apple-glass-card rounded-[18px] shadow-2xl w-full max-w-lg overflow-hidden border border-white/80 p-5 space-y-4">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="text-sm font-bold text-[#1D1D1F]">
                  Dictamen de Auditoría
                </h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">
                  {{ modalResolucion.titulo }}
                </p>
              </div>

              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                :class="modalResolucion.estado === 'APROBADO' ? 'bg-[#34C759]/15 text-[#248A3D] border-[#34C759]/30' : (modalResolucion.estado === 'RECHAZADO' ? 'bg-[#FF3B30]/15 text-[#C9342C] border-[#FF3B30]/30' : 'bg-black/[0.08] text-[#6E6E73] border-black/10')"
              >
                {{ modalResolucion.estado }}
              </span>
            </div>

            <!-- Selector de Dictamen -->
            <div class="space-y-1.5">
              <label class="text-[11px] font-bold text-[#86868B] uppercase tracking-wider">
                Dictamen Final:
              </label>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="modalResolucion.estado = 'APROBADO'"
                  class="flex-1 py-1.5 rounded-[9px] text-xs font-bold transition-all cursor-pointer border"
                  :class="modalResolucion.estado === 'APROBADO' ? 'bg-[#34C759] text-white border-[#34C759]' : 'apple-control-pill text-[#6E6E73]'"
                >
                  Aprobar
                </button>
                <button
                  type="button"
                  @click="modalResolucion.estado = 'RECHAZADO'"
                  class="flex-1 py-1.5 rounded-[9px] text-xs font-bold transition-all cursor-pointer border"
                  :class="modalResolucion.estado === 'RECHAZADO' ? 'bg-[#FF3B30] text-white border-[#FF3B30]' : 'apple-control-pill text-[#6E6E73]'"
                >
                  Rechazar
                </button>
                <button
                  type="button"
                  @click="modalResolucion.estado = 'DESCARTADO'"
                  class="flex-1 py-1.5 rounded-[9px] text-xs font-bold transition-all cursor-pointer border"
                  :class="modalResolucion.estado === 'DESCARTADO' ? 'bg-[#8E8E93] text-white border-[#8E8E93]' : 'apple-control-pill text-[#6E6E73]'"
                >
                  Descartar
                </button>
              </div>
            </div>

            <!-- Observaciones -->
            <div class="space-y-1">
              <label class="text-[11px] font-bold text-[#86868B] uppercase tracking-wider">
                Observaciones del Dictamen:
              </label>
              <textarea
                v-model="modalResolucion.observaciones"
                rows="3"
                placeholder="Motivo o justificación de la decisión..."
                class="w-full apple-control-pill rounded-[10px] p-2.5 text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:ring-1 focus:ring-[#0071E3] resize-none"
              ></textarea>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-black/[0.06]">
              <button
                type="button"
                @click="modalResolucion.abierto = false"
                class="px-3.5 py-1.5 rounded-[9px] text-xs font-semibold text-[#6E6E73] hover:text-[#1D1D1F] cursor-pointer"
              >
                Cancelar
              </button>
              <button
                type="button"
                :disabled="modalResolucion.procesando"
                @click="ejecutarResolucion"
                class="px-4 py-1.5 rounded-[9px] text-xs font-bold text-white shadow-xs cursor-pointer transition-all disabled:opacity-50"
                :class="modalResolucion.estado === 'APROBADO' ? 'bg-[#34C759] hover:bg-[#28A745]' : (modalResolucion.estado === 'RECHAZADO' ? 'bg-[#FF3B30] hover:bg-[#D93025]' : 'bg-[#8E8E93] hover:bg-[#636366]')"
              >
                <span v-if="modalResolucion.procesando">Guardando...</span>
                <span v-else>Confirmar {{ modalResolucion.estado }}</span>
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- MODAL DE FOTOGRAFÍAS DE VISITAS -->
      <AuditoriaFotosModal ref="fotosModalRef" />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSubTabs from '@/Pages/Supervisor/Auditoria/Components/AuditoriaSubTabs.vue';
import AuditoriaFotosModal from '@/Pages/Supervisor/Auditoria/Components/AuditoriaFotosModal.vue';

const props = defineProps({
  canales: { type: Array, default: () => [] },
  rutas: { type: Array, default: () => [] },
  archivos_base: { type: Array, default: () => [] },
  initial_categoria: { type: String, default: 'TODOS' },
  initial_estado: { type: String, default: 'TODOS' },
});

const cargando = ref(false);
const rutas = ref([]);
const fotosModalRef = ref(null);

// Acordeones colapsados por defecto (Sets reactivos para Nivel 1 y Nivel 2)
const rutasExpandidas = ref(new Set());
const clientesExpandidos = ref(new Set());

const metricas = reactive({
  total_rutas: 0,
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

const modalResolucion = reactive({
  abierto: false,
  ids: [],
  estado: 'APROBADO',
  observaciones: '',
  titulo: '',
  procesando: false,
});

let searchDebounceTimer = null;

const rutasOpciones = computed(() => {
  if (filtros.canal === 'TODOS') return props.rutas;
  return props.rutas.filter(r => r.canal === filtros.canal);
});

onMounted(() => {
  consultar();
});

function onCanalChange() {
  filtros.ruta = 'TODOS';
  consultar();
}

function onBusquedaInput() {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    consultar();
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
  consultar();
}

// Control de Acordeones
function alternarRuta(rutaNombre) {
  const next = new Set(rutasExpandidas.value);
  if (next.has(rutaNombre)) {
    next.delete(rutaNombre);
  } else {
    next.add(rutaNombre);
  }
  rutasExpandidas.value = next;
}

function alternarCliente(clienteId) {
  const next = new Set(clientesExpandidos.value);
  if (next.has(clienteId)) {
    next.delete(clienteId);
  } else {
    next.add(clienteId);
  }
  clientesExpandidos.value = next;
}

function expandirTodasLasRutas() {
  const set = new Set();
  rutas.value.forEach(r => set.add(r.ruta));
  rutasExpandidas.value = set;
}

function colapsarTodasLasRutas() {
  rutasExpandidas.value = new Set();
  clientesExpandidos.value = new Set();
}

async function consultar() {
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
    };

    const url = typeof route === 'function'
      ? route('supervisor.auditoria.clientes.consultar')
      : '/supervisor/auditoria/clientes/consultar';

    const { data } = await axios.post(url, params);

    rutas.value = data.rutas || [];

    if (data.metricas) {
      metricas.total_rutas = data.metricas.total_rutas || 0;
      metricas.total_clientes = data.metricas.total_clientes || 0;
      metricas.total_eventos = data.metricas.total_eventos || 0;
      metricas.clientes_con_pendientes = data.metricas.clientes_con_pendientes || 0;
      metricas.clientes_resueltos = data.metricas.clientes_resueltos || 0;
    }
  } catch (e) {
    console.error('Error al consultar Matriz de Cambios:', e);
  } finally {
    cargando.value = false;
  }
}

function abrirFotosCliente(cli) {
  if (fotosModalRef.value) {
    fotosModalRef.value.abrir(cli.cliente_id, cli.cliente, cli.ruta);
  }
}

function abrirModalAprobarRuta(r) {
  const ids = [];
  (r.clientes || []).forEach(cli => {
    (cli.cambios || []).forEach(ev => {
      if (ev.estado_auditoria === 'PENDIENTE') {
        ids.push(ev.id);
      }
    });
  });

  if (ids.length === 0) return;
  abrirModalResolucion(ids, 'APROBADO', `Aprobar todos los pendientes de la ${r.ruta} (${ids.length} cambios)`);
}

function abrirModalAprobarCliente(cli) {
  const idsPendientes = (cli.cambios || [])
    .filter(ev => ev.estado_auditoria === 'PENDIENTE')
    .map(ev => ev.id);

  if (idsPendientes.length === 0) return;
  abrirModalResolucion(idsPendientes, 'APROBADO', `Aprobar todos los pendientes de #${cli.cliente_id} - ${cli.cliente} (${idsPendientes.length} cambios)`);
}

function abrirModalResolucion(ids, estado, titulo = '') {
  modalResolucion.ids = ids;
  modalResolucion.estado = estado;
  modalResolucion.titulo = titulo || `Resolución de ${ids.length} modificación(es)`;
  modalResolucion.observaciones = estado === 'DESCARTADO'
    ? 'Evento descartado de auditoría.'
    : `Dictamen de auditoría: ${estado}`;
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
    await consultar();
  } catch (e) {
    alert('Error al guardar dictamen: ' + (e.response?.data?.error || e.message));
  } finally {
    modalResolucion.procesando = false;
  }
}

function formatearTipoEvento(tipo) {
  switch (tipo) {
    case 'NUEVO': return 'Alta Nueva';
    case 'REACTIVADO': return 'Reactivado';
    case 'ELIMINADO': return 'Baja Padrón';
    case 'INACTIVADO': return 'Inactivado';
    case 'MODIFICADO_RUTA': return 'Cambio de Ruta';
    case 'MODIFICADO_DIA': return 'Cambio de Día';
    case 'MODIFICADO_COORDENADAS': return 'Coordenadas GPS';
    case 'CAMBIO_DE_CLUSTER': return 'Cambio Cluster';
    case 'MODIFICADO_VENDEDOR': return 'Cambio Vendedor';
    default: return tipo || 'Modificado';
  }
}

function obtenerBadgeEventoClase(tipo) {
  switch (tipo) {
    case 'NUEVO':
    case 'REACTIVADO':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
    case 'ELIMINADO':
    case 'INACTIVADO':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
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
    default:
      return 'bg-black/[0.05] text-[#1D1D1F] border-black/10';
  }
}
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-3px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
