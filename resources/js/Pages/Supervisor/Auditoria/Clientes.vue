<template>
  <SupervisorLayout title="Auditoría | Matriz de Cambios">
    <div class="space-y-3 max-w-full font-sans select-none pb-12">
      <AuditoriaSubTabs active-tab="clientes" />

      <!-- BARRA DE FILTROS MINIMALISTA (ESTILO PIZARRA UNILEVER) -->
      <div class="sticky top-0 z-30 apple-glass-card rounded-[14px] p-2.5 sm:p-3 border border-white/80 shadow-xs space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          
          <!-- Filtros de Selección Rápida -->
          <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <!-- Canal -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Canal:</span>
              <select
                v-model="filtros.canal"
                @change="onCanalChange"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos</option>
                <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <!-- Ruta -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Ruta:</span>
              <select
                v-model="filtros.ruta"
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todas</option>
                <option v-for="r in rutasOpciones" :key="r.ruta" :value="r.ruta">{{ r.ruta }}</option>
              </select>
            </div>

            <!-- Estado -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Estado:</span>
              <select
                v-model="filtros.estado_filtro"
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos</option>
                <option value="CON_PENDIENTES">Con Pendientes</option>
                <option value="100_RESUELTOS">100% Resueltos</option>
                <option value="SOLO_PENDIENTES">Solo Pendientes</option>
              </select>
            </div>

            <!-- Tipo de Cambio -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Tipo:</span>
              <select
                v-model="filtros.categoria"
                @change="consultar()"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer max-w-[140px] truncate"
              >
                <option value="TODOS">Todos los tipos</option>
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

            <!-- Limpiar -->
            <button
              v-if="filtros.canal !== 'TODOS' || filtros.ruta !== 'TODOS' || filtros.estado_filtro !== 'TODOS' || filtros.categoria !== 'TODOS' || filtros.busqueda"
              type="button"
              @click="restablecerFiltros"
              class="apple-control-pill text-[#6E6E73] hover:text-[#FF3B30] text-[10px] font-semibold px-2 py-1 rounded-[8px] cursor-pointer"
            >
              Limpiar
            </button>
          </div>

          <!-- Buscador y Controles de Expansión -->
          <div class="flex items-center gap-2">
            <!-- Buscador en tiempo real -->
            <div class="relative w-48 sm:w-60">
              <input
                type="text"
                v-model="filtros.busqueda"
                @input="onBusquedaInput"
                placeholder="Buscar cliente, ID o ruta..."
                class="w-full bg-[#F5F5F7] text-[#1D1D1F] text-[11px] font-medium placeholder-[#86868B] rounded-[8px] pl-6 pr-2 py-1 border border-transparent focus:border-[#0071E3] focus:bg-white focus:outline-none"
              />
              <svg class="w-3 h-3 text-[#86868B] absolute left-2 top-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </div>

            <!-- Botones Expandir / Colapsar Todo -->
            <div class="inline-flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="expandirTodasLasRutas"
                class="px-2 py-1 rounded-[7px] bg-black/[0.04] hover:bg-black/[0.08] text-[#1D1D1F] text-[10px] font-semibold cursor-pointer"
              >
                Expandir
              </button>
              <button
                type="button"
                @click="colapsarTodasLasRutas"
                class="px-2 py-1 rounded-[7px] bg-black/[0.04] hover:bg-black/[0.08] text-[#6E6E73] text-[10px] font-semibold cursor-pointer"
              >
                Colapsar
              </button>
            </div>
          </div>

        </div>

        <!-- Fila de Resumen Compacto -->
        <div class="flex items-center justify-between text-[11px] text-[#86868B] pt-1 border-t border-black/[0.04]">
          <div class="flex items-center gap-3">
            <span>Rutas: <strong class="text-[#1D1D1F] font-mono">{{ metricas.total_rutas }}</strong></span>
            <span>&bull;</span>
            <span>Clientes con cambios: <strong class="text-[#1D1D1F] font-mono">{{ metricas.total_clientes }}</strong></span>
            <span>&bull;</span>
            <span>Modificaciones totales: <strong class="text-[#1D1D1F] font-mono">{{ metricas.total_eventos }}</strong></span>
          </div>

          <div v-if="metricas.clientes_con_pendientes > 0" class="text-[#B7791F] font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-[#B7791F] animate-pulse"></span>
            <span>{{ metricas.clientes_con_pendientes }} cliente(s) con cambios pendientes</span>
          </div>
        </div>
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[14px] p-10 text-center border border-white/70">
        <div class="inline-block w-7 h-7 border-2 border-[#0071E3]/20 border-t-[#0071E3] rounded-full animate-spin"></div>
        <div class="text-xs font-semibold text-[#1D1D1F] mt-2">Cargando Matriz de Cambios...</div>
      </div>

      <!-- BANDEJA VACÍA -->
      <div
        v-else-if="rutas.length === 0"
        class="apple-glass-card rounded-[14px] p-10 text-center border border-white/70"
      >
        <div class="text-xs font-bold text-[#1D1D1F]">No se encontraron modificaciones</div>
        <div class="text-[11px] text-[#86868B] mt-0.5">No hay cambios que coincidan con los filtros seleccionados.</div>
      </div>

      <!-- MATRIZ TIPO PIZARRA UNILEVER (MINIMALISTA, ESTILO TABLA DE ALTA DENSIDAD) -->
      <div v-else class="apple-glass-card rounded-[14px] overflow-hidden border border-white/80 shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <tbody class="divide-y divide-black/[0.04]">
              <template v-for="r in rutas" :key="r.ruta">
                
                <!-- NIVEL 1: FILA ENCABEZADO DE RUTA (COLAPSABLE) -->
                <tr
                  @click="alternarRuta(r.ruta)"
                  class="bg-[#F8F9FB] hover:bg-[#EEF1F5] transition-colors cursor-pointer select-none border-t border-b border-black/[0.06]"
                >
                  <td colspan="4" class="py-2.5 px-3.5">
                    <div class="flex items-center justify-between gap-3">
                      <!-- Info Ruta -->
                      <div class="flex items-center gap-2.5 min-w-0">
                        <svg
                          class="w-3.5 h-3.5 text-[#6E6E73] transition-transform duration-200 shrink-0"
                          :class="rutasExpandidas.has(r.ruta) ? 'rotate-90 text-[#0071E3]' : ''"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2.5"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>

                        <div class="flex items-center gap-2 flex-wrap">
                          <span class="font-extrabold text-[#1D1D1F] text-xs font-mono tracking-tight">
                            {{ r.ruta === 'SIN RUTA' ? 'SIN RUTA (Padrón actual)' : `RUTA: ${r.ruta}` }}
                          </span>

                          <span v-if="r.canal && r.canal !== 'SIN CANAL'" class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-[#0071E3]/10 text-[#0071E3]">
                            {{ r.canal }}
                          </span>

                          <span class="text-[11px] text-[#6E6E73] font-normal">
                            &bull; {{ r.total_clientes }} cliente(s) &bull; {{ r.total_cambios }} cambio(s)
                          </span>
                        </div>
                      </div>

                      <!-- Estado Ruta & Botón Aprobar -->
                      <div class="flex items-center gap-2 shrink-0">
                        <span
                          v-if="r.pendientes > 0"
                          class="px-2 py-0.5 rounded-full bg-[#FFF8E6] text-[#B7791F] border border-[#FCE8B3] font-mono font-bold text-[10px] flex items-center gap-1"
                        >
                          <span class="w-1.5 h-1.5 rounded-full bg-[#B7791F] animate-pulse"></span>
                          <span>{{ r.pendientes }} pendiente(s)</span>
                        </span>

                        <span
                          v-else
                          class="px-2 py-0.5 rounded-full bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9] font-mono font-bold text-[10px]"
                        >
                          Resuelta
                        </span>

                        <!-- Botón Aprobar Ruta si tiene pendientes -->
                        <button
                          v-if="r.pendientes > 0"
                          type="button"
                          @click.stop="abrirModalAprobarRuta(r)"
                          class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold text-white bg-[#1E6B37] hover:bg-[#16562B] transition-all cursor-pointer shadow-2xs ml-1"
                          title="Aprobar todos los cambios de esta ruta"
                        >
                          Aprobar ({{ r.pendientes }})
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- NIVEL 2: FILAS DE CLIENTES DE LA RUTA (CUANDO LA RUTA ESTÁ EXPANDIDA) -->
                <template v-if="rutasExpandidas.has(r.ruta)">
                  <template v-for="cli in r.clientes" :key="cli.cliente_id">
                    
                    <!-- Fila Principal del Cliente -->
                    <tr
                      @click="alternarCliente(cli.cliente_id)"
                      class="hover:bg-black/[0.02] transition-colors cursor-pointer select-none"
                      :class="clientesExpandidos.has(cli.cliente_id) ? 'bg-[#0071E3]/[0.03]' : ''"
                    >
                      <!-- Columna 1: Cliente & Dirección (Texto completo sin recortes) -->
                      <td class="py-2 px-3 pl-8 align-middle min-w-[280px]">
                        <div class="space-y-0.5">
                          <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-bold text-[#1D1D1F] text-xs leading-snug break-words">
                              {{ cli.cliente }}
                            </span>
                            <span class="text-[10px] font-mono text-[#86868B] shrink-0">
                              #{{ cli.cliente_id }}
                            </span>
                          </div>

                          <div class="text-[11px] text-[#6E6E73] leading-snug break-words">
                            {{ cli.direccion || 'Sin dirección registrada' }}
                            <span v-if="cli.vendedor && cli.vendedor !== 'Sin Asignar'" class="text-[#86868B]"> &bull; {{ cli.vendedor }}</span>
                          </div>
                        </div>
                      </td>

                      <!-- Columna 2: Día y Territorio -->
                      <td class="py-2 px-3 align-middle whitespace-nowrap w-32">
                        <span v-if="cli.dia && cli.dia !== 'SIN DIA'" class="px-1.5 py-0.5 rounded bg-black/[0.04] text-[#1D1D1F] font-mono text-[10px] capitalize font-medium">
                          {{ cli.dia }}
                        </span>
                        <span v-else class="text-[10px] text-[#86868B]">&mdash;</span>
                      </td>

                      <!-- Columna 3: Contador Destacado de Modificaciones -->
                      <td class="py-2 px-3 align-middle whitespace-nowrap w-44">
                        <div class="flex items-center gap-1.5">
                          <!-- Badge de veces que se cambió -->
                          <span
                            class="px-2 py-0.5 rounded-full bg-[#0071E3]/10 text-[#0071E3] font-mono font-extrabold text-[11px] border border-[#0071E3]/20"
                            title="Cantidad total de modificaciones registradas en este cliente"
                          >
                            {{ cli.total_cambios }} cambio{{ cli.total_cambios === 1 ? '' : 's' }}
                          </span>

                          <!-- Badge si tiene pendientes -->
                          <span
                            v-if="cli.pendientes > 0"
                            class="px-1.5 py-0.5 rounded bg-[#FFF8E6] text-[#B7791F] font-mono font-bold text-[10px] border border-[#FCE8B3]"
                          >
                            {{ cli.pendientes }} pend.
                          </span>
                        </div>
                      </td>

                      <!-- Columna 4: Acciones y Chevron -->
                      <td class="py-2 px-3 text-right align-middle whitespace-nowrap w-40" @click.stop>
                        <div class="inline-flex items-center gap-1.5">
                          <!-- Botón Fotos si tiene -->
                          <button
                            v-if="cli.cant_fotos > 0"
                            type="button"
                            @click="abrirFotosCliente(cli)"
                            class="px-1.5 py-0.5 rounded text-[10px] font-semibold text-[#0071E3] bg-[#0071E3]/10 hover:bg-[#0071E3]/20 cursor-pointer"
                            title="Ver fotos de visita"
                          >
                            Fotos
                          </button>

                          <!-- Botón Aprobar Todo del Cliente -->
                          <button
                            v-if="cli.pendientes > 0"
                            type="button"
                            @click="abrirModalAprobarCliente(cli)"
                            class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold text-white bg-[#1E6B37] hover:bg-[#16562B] transition-all cursor-pointer shadow-2xs"
                            title="Aprobar todos los pendientes de este cliente"
                          >
                            Aprobar ({{ cli.pendientes }})
                          </button>

                          <!-- Botón Historial / Chevron -->
                          <button
                            type="button"
                            @click="alternarCliente(cli.cliente_id)"
                            class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.05] transition-all cursor-pointer flex items-center gap-0.5"
                          >
                            <span>{{ clientesExpandidos.has(cli.cliente_id) ? 'Ocultar' : 'Historial' }}</span>
                            <svg
                              class="w-3 h-3 transition-transform duration-200"
                              :class="clientesExpandidos.has(cli.cliente_id) ? 'rotate-180 text-[#0071E3]' : ''"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke="currentColor"
                              stroke-width="2"
                            >
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                          </button>
                        </div>
                      </td>
                    </tr>

                    <!-- NIVEL 3: HISTORIAL CRONOLÓGICO DE CAMBIOS (CUANDO EL CLIENTE ESTÁ EXPANDIDO) -->
                    <tr v-if="clientesExpandidos.has(cli.cliente_id)" class="bg-[#F8F9FA]/80">
                      <td colspan="4" class="py-2.5 px-4 pl-12 border-b border-black/[0.06]">
                        <div class="space-y-1.5">
                          <div class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider flex items-center justify-between pb-1">
                            <span>Cronología Histórica de Modificaciones (#{{ cli.cliente_id }})</span>
                            <span>{{ cli.cambios.length }} evento(s)</span>
                          </div>

                          <div class="divide-y divide-black/[0.04] bg-white rounded-[10px] border border-black/[0.06] overflow-hidden shadow-2xs">
                            <div
                              v-for="ev in cli.cambios"
                              :key="ev.id"
                              class="p-2 sm:p-2.5 flex flex-wrap sm:flex-nowrap items-center justify-between gap-2.5 text-xs hover:bg-black/[0.01]"
                            >
                              <!-- Detalle del Evento y Transición -->
                              <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                  <span
                                    class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold border"
                                    :class="obtenerBadgeEventoClase(ev.tipo_evento)"
                                  >
                                    {{ formatearTipoEvento(ev.tipo_evento) }}
                                  </span>

                                  <span class="font-bold text-[#1D1D1F] text-xs">
                                    <code class="font-mono text-[#0071E3] bg-[#0071E3]/5 px-1 py-0.2 rounded">{{ ev.campo_afectado }}</code>
                                  </span>

                                  <span class="text-[10px] text-[#86868B] font-mono">
                                    &bull; {{ ev.fecha_auditoria_fmt }}
                                  </span>

                                  <span v-if="ev.archivo_origen" class="text-[10px] text-[#86868B]">
                                    ({{ ev.archivo_origen }})
                                  </span>
                                </div>

                                <!-- Transición Antes -> Después en texto completo -->
                                <div class="flex items-center gap-1.5 font-mono text-[11px] flex-wrap">
                                  <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 line-through opacity-85 break-words max-w-full">
                                    {{ ev.valor_anterior }}
                                  </span>

                                  <svg class="w-3 h-3 text-[#0071E3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                  </svg>

                                  <span class="px-2 py-0.5 rounded bg-sky-50 text-[#0071E3] font-bold border border-sky-200 break-words max-w-full">
                                    {{ ev.valor_nuevo }}
                                  </span>

                                  <!-- Desplegable con mapa comparativo para cambios de coordenadas -->
                                  <AuditoriaCoordenadasMapa
                                    v-if="esCambioCoordenadas(ev)"
                                    :id="ev.id"
                                    :valor-anterior="ev.valor_anterior"
                                    :valor-nuevo="ev.valor_nuevo"
                                    :cliente="cli.cliente"
                                    :cliente-id="cli.cliente_id"
                                  />
                                </div>

                                <div v-if="ev.observaciones || ev.auditado_por" class="text-[10px] text-[#86868B] italic">
                                  <span v-if="ev.auditado_por">Por {{ ev.auditado_por }}</span>
                                  <span v-if="ev.fecha_resolucion"> el {{ ev.fecha_resolucion }}</span>
                                  <span v-if="ev.observaciones"> &mdash; "{{ ev.observaciones }}"</span>
                                </div>
                              </div>

                              <!-- Estado y Dictamen -->
                              <div class="flex items-center gap-1.5 shrink-0 sm:pl-3 sm:border-l border-black/[0.06]">
                                <div v-if="ev.estado_auditoria === 'PENDIENTE'" class="flex items-center gap-1">
                                  <button
                                    type="button"
                                    @click="abrirModalResolucion([ev.id], 'APROBADO', `Aprobar ${ev.campo_afectado} en ${cli.cliente}`)"
                                    class="px-2 py-0.5 rounded bg-[#34C759] hover:bg-[#28A745] text-white font-bold text-[10px] transition-all cursor-pointer shadow-2xs"
                                  >
                                    Aprobar
                                  </button>
                                  <button
                                    type="button"
                                    @click="abrirModalResolucion([ev.id], 'RECHAZADO', `Rechazar ${ev.campo_afectado} en ${cli.cliente}`)"
                                    class="px-2 py-0.5 rounded bg-[#FF3B30] hover:bg-[#D93025] text-white font-bold text-[10px] transition-all cursor-pointer shadow-2xs"
                                  >
                                    Rechazar
                                  </button>
                                </div>

                                <div v-else>
                                  <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold border font-mono"
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
                      </td>
                    </tr>

                  </template>
                </template>

              </template>
            </tbody>
          </table>
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
import AuditoriaCoordenadasMapa from '@/Pages/Supervisor/Auditoria/Components/AuditoriaCoordenadasMapa.vue';

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

// Acordeones colapsados por defecto
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

function esCambioCoordenadas(ev) {
  if (!ev) return false;
  const tipo = (ev.tipo_evento || '').toUpperCase();
  const campo = (ev.campo_afectado || '').toLowerCase();
  return tipo === 'MODIFICADO_COORDENADAS' || campo === 'coordenadas' || campo === 'latitud' || campo === 'longitud' || campo === 'ubicacion';
}

function formatearTipoEvento(tipo) {
  switch (tipo) {
    case 'NUEVO': return 'Alta';
    case 'REACTIVADO': return 'Reactivado';
    case 'ELIMINADO': return 'Baja';
    case 'INACTIVADO': return 'Inactivado';
    case 'MODIFICADO_RUTA': return 'Cambio Ruta';
    case 'MODIFICADO_DIA': return 'Cambio Día';
    case 'MODIFICADO_COORDENADAS': return 'GPS';
    case 'CAMBIO_DE_CLUSTER': return 'Cluster';
    case 'MODIFICADO_VENDEDOR': return 'Vendedor';
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
