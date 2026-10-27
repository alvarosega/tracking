<template>
  <SupervisorLayout title="Auditoría | Clientes Inactivados">
    <div class="space-y-3 max-w-full font-sans select-none pb-6">
      <AuditoriaSubTabs active-tab="inactivados" />

      <!-- PANEL DE CONTROL Y ESTADO DE AUDITORÍA (APPLE LIQUID GLASS) -->
      <div
        class="sticky top-0 z-40 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70"
      >
        <!-- NIVEL 1: ESTADO DEL FLUJO + ACCIONES MASIVAS + BUSCADOR -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <!-- Selector Prominente de Estado (Tabs Segmentados con Badges) -->
          <div class="flex items-center gap-1 bg-black/[0.05] p-1 rounded-[12px] text-xs">
            <button
              type="button"
              @click="cambiarEstado('PENDIENTE')"
              :class="estadoAuditoria === 'PENDIENTE' ? 'bg-white text-[#B26B00] font-bold shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-2"
            >
              <span class="w-2 h-2 rounded-full bg-[#FF9500]"></span>
              <span>Pendientes</span>
              <span class="font-num font-bold text-[10px] px-1.5 py-0.2 rounded-full bg-[#FF9500]/15 text-[#B26B00]">
                {{ metricas.pendientes.toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('APROBADO')"
              :class="estadoAuditoria === 'APROBADO' ? 'bg-white text-[#248A3D] font-bold shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-2"
            >
              <span class="w-2 h-2 rounded-full bg-[#34C759]"></span>
              <span>Aprobados</span>
              <span class="font-num font-bold text-[10px] px-1.5 py-0.2 rounded-full bg-[#34C759]/15 text-[#248A3D]">
                {{ metricas.aprobados.toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('RECHAZADO')"
              :class="estadoAuditoria === 'RECHAZADO' ? 'bg-white text-[#C9342C] font-bold shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-2"
            >
              <span class="w-2 h-2 rounded-full bg-[#FF3B30]"></span>
              <span>Rechazados</span>
              <span class="font-num font-bold text-[10px] px-1.5 py-0.2 rounded-full bg-[#FF3B30]/15 text-[#C9342C]">
                {{ metricas.rechazados.toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('TODOS')"
              :class="estadoAuditoria === 'TODOS' ? 'bg-white text-[#0071E3] font-bold shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold'"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-2"
            >
              <span>Todos</span>
              <span class="font-num font-bold text-[10px] px-1.5 py-0.2 rounded-full bg-black/[0.06] text-[#6E6E73]">
                {{ metricas.total_general.toLocaleString('es-BO') }}
              </span>
            </button>
          </div>

          <!-- Acciones en Lote (si hay seleccionados) + Buscador Rápido -->
          <div class="flex items-center gap-2 flex-1 justify-end max-w-md">
            <div v-if="seleccionadosLote.length > 0" class="flex items-center gap-1.5">
              <button
                type="button"
                @click="abrirModalLote('APROBADO')"
                class="bg-[#34C759] hover:bg-[#32B953] text-white text-[11px] font-bold px-3 py-1.5 rounded-[8px] shadow-xs transition-all cursor-pointer whitespace-nowrap active:scale-95"
              >
                Aprobar ({{ seleccionadosLote.length }})
              </button>
              <button
                type="button"
                @click="abrirModalLote('RECHAZADO')"
                class="bg-[#FF3B30] hover:bg-[#E0332B] text-white text-[11px] font-bold px-3 py-1.5 rounded-[8px] shadow-xs transition-all cursor-pointer whitespace-nowrap active:scale-95"
              >
                Rechazar ({{ seleccionadosLote.length }})
              </button>
            </div>

            <!-- Buscador Reactivo Inteligente -->
            <div class="relative flex-1 min-w-[200px]">
              <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-3 py-1.5">
                <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                  v-model="busqueda"
                  type="text"
                  placeholder="Buscar cliente, ID, ruta, vendedor..."
                  class="w-full bg-transparent text-[#1D1D1F] placeholder-[#86868B] text-[11px] focus:outline-none font-medium"
                />
                <button
                  v-if="busqueda && busqueda.trim().length > 0"
                  type="button"
                  @click="busqueda = ''"
                  class="text-[10px] bg-black/[0.06] hover:bg-black/[0.12] rounded-full w-4 h-4 flex items-center justify-center font-bold text-[#6E6E73] hover:text-[#1D1D1F] cursor-pointer shrink-0"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- NIVEL 2: FILTROS SECUNDARIOS + ALCANCE -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-black/[0.05] text-[11px]">
          <div class="flex flex-wrap items-center gap-1.5">
            <!-- Selector Canal -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
              <select
                v-model="canalSeleccionado"
                @change="programarConsulta"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <!-- Selector Archivo Origen -->
            <div v-if="archivos_base && archivos_base.length > 0" class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Archivo:</span>
              <select
                v-model="archivoOrigen"
                @change="programarConsulta"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer max-w-[160px] truncate"
              >
                <option value="TODOS">Todos los Archivos</option>
                <option v-for="a in archivos_base" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>

            <!-- Rango Fechas -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Desde:</span>
              <input
                type="date"
                v-model="fechaInicio"
                @change="programarConsulta"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Hasta:</span>
              <input
                type="date"
                v-model="fechaFin"
                @change="programarConsulta"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
            </div>

            <!-- Botón Limpiar -->
            <button
              type="button"
              @click="restablecerFiltros"
              class="flex items-center gap-1 apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] text-[11px] font-semibold px-2.5 py-1 rounded-[10px] cursor-pointer"
              title="Restablecer filtros"
            >
              <span>Limpiar</span>
            </button>
          </div>

          <div class="text-[11px] font-mono text-[#6E6E73]">
            Mostrando <strong>{{ registrosFiltrados.length }}</strong> de <strong>{{ totalRegistros }}</strong>
          </div>
        </div>
      </div>

      <!-- TABLA DE RESULTADOS -->
      <div class="apple-glass-card rounded-[16px] overflow-hidden border border-white/70 shadow-sm">
        <div class="max-h-[calc(100vh-270px)] min-h-[380px] overflow-y-auto overflow-x-auto custom-scroll relative">
          <table class="w-full min-w-[1100px] text-left border-collapse text-[11px]">
            <thead class="text-[#6E6E73] font-bold uppercase text-[10px] tracking-tight border-b border-black/[0.06] select-none sticky top-0 z-20 bg-white/90 backdrop-blur-md">
              <tr>
                <th class="p-3 text-center w-8">
                  <input
                    type="checkbox"
                    :checked="todosLoteMarcados"
                    @change="conmutarTodosLote($event.target.checked)"
                    class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer"
                  />
                </th>
                <th class="p-3 font-sans min-w-[180px]">Cliente</th>
                <th class="p-3 text-center font-sans">Foto</th>
                <th class="p-3 font-sans">Ruta / Canal</th>
                <th class="p-3 font-sans">Día</th>
                <th class="p-3 font-sans">Evento Detectado</th>
                <th class="p-3 font-sans">Fecha Baja</th>
                <th class="p-3 min-w-[200px] font-sans">Ventas 5 Meses Previos (fact_ventas)</th>
                <th class="p-3 text-center font-sans">Estado Auditoría</th>
                <th class="p-3 font-sans">Observaciones / Auditor</th>
                <th class="p-3 text-center pr-4 font-sans">Acción</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-black/[0.04]">
              <!-- Skeleton Loading -->
              <template v-if="cargando">
                <tr v-for="i in 8" :key="`skel-inac-${i}`">
                  <td class="p-3 text-center"><div class="h-3.5 w-3.5 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-36 bg-black/[0.06] rounded animate-pulse mb-1"></div><div class="h-3 w-24 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3 text-center"><div class="h-5 w-14 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-16 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-3.5 w-14 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-24 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-3.5 w-20 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-7 w-44 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3 text-center"><div class="h-5 w-20 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-3.5 w-28 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3 text-center"><div class="h-6 w-16 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                </tr>
              </template>

              <!-- Error -->
              <tr v-else-if="errorMsg">
                <td colspan="11" class="p-12 text-center text-[#C9342C] font-semibold">
                  Error al cargar registros: {{ errorMsg }}
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-else-if="registrosFiltrados.length === 0">
                <td colspan="11" class="py-16 px-4 text-center">
                  <div class="flex flex-col items-center justify-center max-w-sm mx-auto select-none font-sans">
                    <div class="w-12 h-12 rounded-full bg-white/80 border border-black/[0.06] flex items-center justify-center text-[#86868B] mb-3 shadow-xs">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <h4 class="text-sm font-bold text-[#1D1D1F] mb-1">No se encontraron clientes inactivados</h4>
                    <p class="text-xs text-[#86868B] mb-4 text-center">
                      No hay registros que coincidan con los filtros y estado de auditoría.
                    </p>
                    <button
                      type="button"
                      @click="restablecerFiltros"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] apple-control-pill text-[#0071E3] text-xs font-semibold cursor-pointer"
                    >
                      <span>Restablecer Filtros</span>
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Filas de Datos -->
              <template v-else>
                <tr
                  v-for="r in registrosFiltrados"
                  :key="r.id"
                  class="hover:bg-black/[0.02] transition-colors select-none"
                >
                  <td class="p-3 text-center">
                    <input
                      type="checkbox"
                      :checked="seleccionadosLote.includes(r.id)"
                      @change="conmutarItemLote(r.id, $event.target.checked)"
                      class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer"
                    />
                  </td>

                  <!-- Cliente -->
                  <td class="p-3 font-sans">
                    <div class="font-bold text-[#1D1D1F]">{{ r.cliente }}</div>
                    <div class="text-[10px] text-[#86868B] font-mono">
                      ID: {{ r.cliente_id }} · {{ r.direccion }}
                    </div>
                  </td>

                  <!-- Foto -->
                  <td class="p-3 text-center whitespace-nowrap font-sans">
                    <button
                      v-if="Number(r.cant_fotos || 0) > 0"
                      type="button"
                      @click="abrirFotosCliente(r)"
                      class="group inline-flex items-center gap-1.5 px-2 py-1 rounded-[8px] apple-control-pill cursor-pointer"
                      :title="`Ver ${r.cant_fotos} fotografía${r.cant_fotos > 1 ? 's' : ''} de este cliente`"
                    >
                      <img
                        v-if="r.foto_reciente_url"
                        :src="r.foto_reciente_url"
                        class="w-5 h-5 rounded-[4px] object-cover border border-black/[0.08]"
                        loading="lazy"
                      />
                      <span class="text-[10px] font-bold text-[#0071E3] font-num">
                        {{ r.cant_fotos }} foto{{ r.cant_fotos > 1 ? 's' : '' }}
                      </span>
                    </button>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 text-[10px] text-[#86868B] font-mono px-2 py-1 rounded-[6px] bg-black/[0.03] border border-black/[0.05] select-none"
                    >
                      <svg class="w-3 h-3 text-[#C7C7CC]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      </svg>
                      <span>Sin foto</span>
                    </span>
                  </td>

                  <!-- Ruta / Canal -->
                  <td class="p-3 font-sans">
                    <span class="font-bold text-[#1D1D1F]">{{ r.ruta }}</span>
                    <span class="text-[10px] text-[#86868B] block font-mono">{{ r.canal }}</span>
                  </td>

                  <!-- Día -->
                  <td class="p-3 font-medium text-[#6E6E73] text-[11px] font-sans">{{ r.dia }}</td>

                  <!-- Evento Detectado -->
                  <td class="p-3 font-sans">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-[6px] bg-white/80 text-[#1D1D1F] border border-black/[0.08] block w-fit">
                      {{ r.tipo_evento }}
                    </span>
                    <span
                      v-if="r.evaluacion_riesgo && r.evaluacion_riesgo.mensaje_evaluacion"
                      class="inline-block px-1.5 py-0.5 rounded-[4px] text-[9px] font-bold border mt-1"
                      :class="r.evaluacion_riesgo.badge_class || 'bg-[#F5F5F7] text-[#6E6E73] border-[#E5E5EA]'"
                    >
                      {{ r.evaluacion_riesgo.mensaje_evaluacion }}
                    </span>
                  </td>

                  <!-- Fecha Baja -->
                  <td class="p-3 font-mono text-[11px] text-[#6E6E73] whitespace-nowrap">
                    {{ r.fecha_auditoria }}
                  </td>

                  <!-- Ventas 5 Meses Previos -->
                  <td class="p-3 font-sans">
                    <span
                      v-if="!r.historico_ventas || !r.historico_ventas.meses || r.historico_ventas.meses.length === 0"
                      class="text-[#86868B] italic text-[11px]"
                    >
                      Sin ventas registradas
                    </span>
                    <div v-else class="flex items-center gap-2">
                      <div class="flex items-center gap-1">
                        <div
                          v-for="(m, mIdx) in r.historico_ventas.meses"
                          :key="mIdx"
                          class="flex flex-col items-center min-w-[34px] px-1 py-0.5 rounded-[6px] bg-white/75 border border-black/[0.06]"
                        >
                          <span class="text-[9px] text-[#86868B] font-mono">{{ String(m.periodo || '').slice(5) }}</span>
                          <span
                            class="text-[10px] font-num"
                            :class="Number(m.monto || 0) > 0 ? 'text-[#1D1D1F] font-bold' : 'text-[#86868B]'"
                          >
                            {{ Number(m.monto || 0) > 0 ? Math.round(Number(m.monto)) : '-' }}
                          </span>
                        </div>
                      </div>
                      <div class="text-right shrink-0 border-l border-black/[0.08] pl-2">
                        <span class="text-[9px] text-[#86868B] block uppercase font-bold tracking-wider">Total 5m</span>
                        <strong class="text-[11px] font-num text-[#0071E3] font-bold">
                          Bs {{ fmtNum(r.historico_ventas.total_5m) }}
                        </strong>
                      </div>
                    </div>
                  </td>

                  <!-- Estado Auditoría -->
                  <td class="p-3 text-center font-sans">
                    <span
                      v-if="r.estado_auditoria === 'APROBADO'"
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#34C759]/15 text-[#248A3D] border border-[#34C759]/30"
                    >APROBADO</span>
                    <span
                      v-else-if="r.estado_auditoria === 'RECHAZADO'"
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#FF3B30]/15 text-[#C9342C] border border-[#FF3B30]/30"
                    >RECHAZADO</span>
                    <span
                      v-else
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#FF9500]/15 text-[#B26B00] border border-[#FF9500]/30"
                    >PENDIENTE</span>
                  </td>

                  <!-- Observaciones / Auditor -->
                  <td class="p-3 max-w-xs font-sans">
                    <div
                      v-if="r.observaciones"
                      class="truncate text-[#1D1D1F] font-medium"
                      :title="r.observaciones"
                    >
                      {{ r.observaciones }}
                    </div>
                    <span v-else class="text-[#86868B] italic">Sin comentarios</span>
                    <span
                      v-if="r.auditado_por"
                      class="text-[10px] text-[#86868B] font-mono block"
                    >
                      Por: {{ r.auditado_por }} ({{ r.fecha_resolucion || '-' }})
                    </span>
                  </td>

                  <!-- Acción -->
                  <td class="p-3 text-center pr-4 whitespace-nowrap font-sans">
                    <button
                      type="button"
                      @click="abrirModalIndividual(r)"
                      class="apple-control-pill hover:bg-[#0071E3] hover:text-white text-[#1D1D1F] text-[11px] font-bold px-3 py-1 rounded-[7px] transition-all cursor-pointer"
                    >
                      Resolver
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- BARRA FLOTANTE DE ACCIONES MASIVAS -->
      <Teleport to="body">
        <div
          v-if="seleccionadosLote.length > 0"
          class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[99990] apple-glass-card rounded-[16px] shadow-2xl px-5 py-3 flex items-center gap-4 transition-all duration-300 select-none font-sans"
        >
          <div class="flex items-center gap-2 pr-4 border-r border-black/[0.08]">
            <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] animate-pulse"></span>
            <span class="text-xs font-bold text-[#1D1D1F] whitespace-nowrap">
              <span class="font-num">{{ seleccionadosLote.length }}</span> seleccionados
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="abrirModalLote('APROBADO')"
              class="bg-[#34C759] hover:bg-[#32B953] text-white text-xs font-bold px-4 py-1.5 rounded-[8px] shadow-xs cursor-pointer transition-all flex items-center gap-1.5 whitespace-nowrap active:scale-95"
            >
              <span>Aprobar Seleccionados (<span class="font-num">{{ seleccionadosLote.length }}</span>)</span>
            </button>

            <button
              type="button"
              @click="abrirModalLote('RECHAZADO')"
              class="bg-[#FF3B30] hover:bg-[#E0332B] text-white text-xs font-bold px-4 py-1.5 rounded-[8px] shadow-xs cursor-pointer transition-all flex items-center gap-1.5 whitespace-nowrap active:scale-95"
            >
              <span>Rechazar Seleccionados (<span class="font-num">{{ seleccionadosLote.length }}</span>)</span>
            </button>
          </div>
        </div>
      </Teleport>

      <!-- MODAL DE RESOLUCIÓN -->
      <Teleport to="body">
        <div
          v-if="modalResolucion.abierto"
          @click.self="cerrarModal"
          class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/40 backdrop-blur-md p-4 select-none font-sans"
        >
          <div class="apple-glass-card rounded-[20px] shadow-2xl w-full max-w-lg flex flex-col overflow-hidden border border-white/80 backdrop-blur-3xl">
            <div class="px-5 py-4 flex items-start justify-between border-b border-black/[0.06] bg-white/70">
              <div>
                <h3 class="text-[15px] font-bold text-[#1D1D1F]">{{ modalResolucion.titulo }}</h3>
                <p class="text-[11px] text-[#6E6E73] mt-0.5 font-medium">{{ modalResolucion.subtitulo }}</p>
              </div>
              <button
                type="button"
                @click="cerrarModal"
                class="w-7 h-7 rounded-full apple-control-pill text-[#1D1D1F] flex items-center justify-center text-xs font-bold transition-all cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div class="p-5 space-y-4 bg-white/60">
              <div>
                <label class="text-xs font-bold text-[#1D1D1F] block mb-1.5 uppercase tracking-wide">
                  Decisión de Auditoría:
                </label>
                <div class="flex items-center gap-4">
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-[#248A3D] bg-[#34C759]/10 px-3 py-1.5 rounded-[8px] border border-[#34C759]/30">
                    <input
                      v-model="modalResolucion.estado"
                      type="radio"
                      value="APROBADO"
                      class="text-[#34C759] focus:ring-0 cursor-pointer"
                    />
                    <span>Aprobar Baja</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-[#C9342C] bg-[#FF3B30]/10 px-3 py-1.5 rounded-[8px] border border-[#FF3B30]/30">
                    <input
                      v-model="modalResolucion.estado"
                      type="radio"
                      value="RECHAZADO"
                      class="text-[#FF3B30] focus:ring-0 cursor-pointer"
                    />
                    <span>Rechazar Baja</span>
                  </label>
                </div>
              </div>

              <div>
                <label class="text-xs font-bold text-[#1D1D1F] block mb-1.5 uppercase tracking-wide">
                  Motivo / Justificación <span class="text-[#FF3B30]">*</span>
                </label>
                <textarea
                  v-model="modalResolucion.observaciones"
                  rows="3"
                  placeholder="Ingresa la justificación técnica o comercial del dictamen..."
                  class="w-full apple-control-pill rounded-[10px] p-3 text-xs text-[#1D1D1F] focus:outline-none resize-none font-medium"
                ></textarea>
              </div>

              <div>
                <label class="text-[11px] font-bold text-[#6E6E73] block mb-1 uppercase tracking-wider">
                  Auditor / Supervisor:
                </label>
                <input
                  v-model="modalResolucion.auditor"
                  type="text"
                  class="w-full apple-control-pill rounded-[8px] px-3 py-1.5 text-xs text-[#1D1D1F] font-bold focus:outline-none"
                />
              </div>
            </div>

            <div class="px-5 py-3.5 border-t border-black/[0.06] bg-white/80 flex items-center justify-end gap-2">
              <button
                type="button"
                @click="cerrarModal"
                class="apple-control-pill text-[#1D1D1F] text-xs font-bold px-4 py-1.5 rounded-[8px] cursor-pointer"
              >
                Cancelar
              </button>
              <button
                type="button"
                :disabled="modalResolucion.guardando"
                @click="confirmarResolucion"
                class="bg-[#0071E3] hover:bg-[#005BB5] text-white text-xs font-bold px-4 py-1.5 rounded-[8px] shadow-xs cursor-pointer transition-all active:scale-95 disabled:opacity-50"
              >
                {{ modalResolucion.guardando ? 'Guardando...' : 'Guardar Decisión' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Toast de Notificación Apple Glass -->
      <Teleport to="body">
        <div
          v-if="toastNotif.visible"
          class="fixed top-5 right-5 z-[999999] apple-glass-card rounded-[14px] px-4 py-3 shadow-2xl border border-white/80 flex items-center gap-3 transition-all duration-300"
          :class="toastNotif.tipo === 'error' ? 'bg-[#FF3B30]/15 text-[#C9342C]' : 'bg-[#34C759]/15 text-[#1D1D1F]'"
        >
          <span class="w-2.5 h-2.5 rounded-full" :class="toastNotif.tipo === 'error' ? 'bg-[#FF3B30]' : 'bg-[#34C759]'"></span>
          <span class="text-xs font-semibold">{{ toastNotif.mensaje }}</span>
          <button type="button" @click="toastNotif.visible = false" class="text-xs text-[#86868B] hover:text-[#1D1D1F] ml-2 cursor-pointer font-bold">✕</button>
        </div>
      </Teleport>

      <AuditoriaFotosModal ref="fotosModalRef" />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSubTabs from './Components/AuditoriaSubTabs.vue';
import AuditoriaFotosModal from './Components/AuditoriaFotosModal.vue';

const props = defineProps({
  canales: { type: Array, default: () => [] },
  rutas: { type: Array, default: () => [] },
  archivos_base: { type: Array, default: () => [] },
});

const fotosModalRef = ref(null);

const canalSeleccionado = ref('TODOS');
const archivoOrigen = ref('TODOS');
const estadoAuditoria = ref('PENDIENTE');
const fechaInicio = ref('');
const fechaFin = ref('');
const busqueda = ref('');

const cargando = ref(false);
const errorMsg = ref(null);
const registrosCargados = ref([]);
const seleccionadosLote = ref([]);
const totalRegistros = ref(0);

const metricas = reactive({
  pendientes: 0,
  aprobados: 0,
  rechazados: 0,
  total_general: 0,
});

let timerDebounce = null;

function programarConsulta() {
  clearTimeout(timerDebounce);
  timerDebounce = setTimeout(() => {
    ejecutarConsulta();
  }, 250);
}

function fmtNum(n) {
  return Number(n || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function cambiarEstado(nuevoEst) {
  estadoAuditoria.value = nuevoEst;
  programarConsulta();
}

async function ejecutarConsulta() {
  cargando.value = true;
  errorMsg.value = null;
  seleccionadosLote.value = [];

  const payload = {
    canal: canalSeleccionado.value !== 'TODOS' ? canalSeleccionado.value : null,
    archivo_origen: archivoOrigen.value !== 'TODOS' ? archivoOrigen.value : null,
    estado_auditoria: estadoAuditoria.value || 'TODOS',
    fecha_inicio: fechaInicio.value || null,
    fecha_fin: fechaFin.value || null,
  };

  try {
    const { data } = await axios.post(route('supervisor.auditoria.inactivados.consultar'), payload);
    registrosCargados.value = data.registros || [];
    totalRegistros.value = Number(data.total ?? registrosCargados.value.length);
    if (data.metricas) {
      metricas.pendientes = Number(data.metricas.pendientes || 0);
      metricas.aprobados = Number(data.metricas.aprobados || 0);
      metricas.rechazados = Number(data.metricas.rechazados || 0);
      metricas.total_general = Number(data.metricas.total_general || 0);
    }
  } catch (err) {
    console.error('[AuditoriaInactivados] Error:', err);
    errorMsg.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Error de conexión';
  } finally {
    cargando.value = false;
  }
}

const registrosFiltrados = computed(() => {
  const query = String(busqueda.value || '').toLowerCase().trim();
  const palabras = query.split(/\s+/).filter(Boolean);
  if (palabras.length === 0) return registrosCargados.value;

  return registrosCargados.value.filter((r) => {
    const searchTarget = [
      r.cliente || '',
      r.cliente_id || '',
      r.ruta || '',
      r.canal || '',
      r.dia || '',
      r.vendedor || '',
      r.direccion || '',
      r.archivo_origen || '',
      r.observaciones || '',
      r.auditado_por || '',
      r.evaluacion_riesgo?.mensaje_evaluacion || '',
    ]
      .join(' ')
      .toLowerCase();

    return palabras.every((p) => searchTarget.includes(p));
  });
});

function restablecerFiltros() {
  busqueda.value = '';
  estadoAuditoria.value = 'PENDIENTE';
  canalSeleccionado.value = 'TODOS';
  archivoOrigen.value = 'TODOS';
  fechaInicio.value = '';
  fechaFin.value = '';
  programarConsulta();
}

// Selección en lote
const todosLoteMarcados = computed(() => {
  return (
    registrosFiltrados.value.length > 0 &&
    registrosFiltrados.value.every((r) => seleccionadosLote.value.includes(r.id))
  );
});

function conmutarItemLote(id, checked) {
  const set = new Set(seleccionadosLote.value);
  if (checked) set.add(id);
  else set.delete(id);
  seleccionadosLote.value = Array.from(set);
}

function conmutarTodosLote(checked) {
  const set = new Set(seleccionadosLote.value);
  registrosFiltrados.value.forEach((r) => {
    if (checked) set.add(r.id);
    else set.delete(r.id);
  });
  seleccionadosLote.value = Array.from(set);
}

// Modal de Resolución
const modalResolucion = reactive({
  abierto: false,
  guardando: false,
  ids: [],
  titulo: '',
  subtitulo: '',
  estado: 'APROBADO',
  observaciones: '',
  auditor: 'SUPERVISOR',
});

function abrirModalIndividual(reg) {
  modalResolucion.ids = [reg.id];
  modalResolucion.titulo = `Resolver Baja: ${reg.cliente}`;
  modalResolucion.subtitulo = `ID: ${reg.cliente_id} · Ruta: ${reg.ruta} · Fecha Baja: ${reg.fecha_auditoria}`;
  modalResolucion.observaciones = reg.observaciones || '';
  modalResolucion.estado = reg.estado_auditoria === 'RECHAZADO' ? 'RECHAZADO' : 'APROBADO';
  modalResolucion.abierto = true;
}

function abrirModalLote(estadoInicial = 'APROBADO') {
  if (seleccionadosLote.value.length === 0) return;
  modalResolucion.ids = [...seleccionadosLote.value];
  const tituloEstado = estadoInicial === 'APROBADO' ? 'Aprobar' : 'Rechazar';
  modalResolucion.titulo = `${tituloEstado} Bajas en Lote`;
  modalResolucion.subtitulo = `${modalResolucion.ids.length} registros seleccionados`;
  modalResolucion.observaciones = '';
  modalResolucion.estado = estadoInicial;
  modalResolucion.abierto = true;
}

function cerrarModal() {
  modalResolucion.abierto = false;
  modalResolucion.ids = [];
}

const toastNotif = reactive({ visible: false, mensaje: '', tipo: 'exito' });
let toastTimer = null;

function mostrarNotificacion(msg, tipo = 'exito') {
  toastNotif.mensaje = msg;
  toastNotif.tipo = tipo;
  toastNotif.visible = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toastNotif.visible = false;
  }, 4500);
}

async function confirmarResolucion() {
  const obs =
    String(modalResolucion.observaciones || '').trim() ||
    `${modalResolucion.estado === 'APROBADO' ? 'Aprobado' : 'Rechazado'} por ${modalResolucion.auditor || 'SUPERVISOR'}`;

  const cantidad = modalResolucion.ids.length;
  const estadoFinal = modalResolucion.estado;

  modalResolucion.guardando = true;
  try {
    await axios.post(route('supervisor.auditoria.inactivados.resolver'), {
      ids: modalResolucion.ids,
      estado: estadoFinal,
      observaciones: obs,
      auditado_por: String(modalResolucion.auditor || 'SUPERVISOR').trim() || 'SUPERVISOR',
    });

    cerrarModal();
    await ejecutarConsulta();
    mostrarNotificacion(`✓ ${cantidad} baja(s) ${estadoFinal === 'APROBADO' ? 'aprobada(s)' : 'rechazada(s)'} exitosamente.`);
  } catch (err) {
    mostrarNotificacion(`Error: ${err.response?.data?.error || err.response?.data?.detail || err.message}`, 'error');
  } finally {
    modalResolucion.guardando = false;
  }
}

function abrirFotosCliente(r) {
  fotosModalRef.value?.abrir(r.cliente_id, r.cliente, r.ruta);
}

onMounted(() => {
  ejecutarConsulta();
});
</script>

