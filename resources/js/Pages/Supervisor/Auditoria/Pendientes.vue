<template>
  <SupervisorLayout title="Auditoría | Cambios Pendientes de Ruteo">
    <div class="space-y-3 max-w-full font-sans select-none pb-8">
      <AuditoriaSubTabs active-tab="pendientes" />

      <!-- PANEL PRINCIPAL DE CONTROL Y FILTROS (APPLE LIQUID GLASS) -->
      <div class="sticky top-0 z-40 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70">
        <!-- NIVEL 1: SELECTOR DE CATEGORÍA DE CAMBIO (CHIPS INTERACTIVOS CON CONTEOS EN VIVO) -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-touch pb-0.5">
          <button
            v-for="cat in categoriasDisponibles"
            :key="cat.id"
            type="button"
            @click="seleccionarCategoria(cat.id)"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-[10px] text-xs font-semibold whitespace-nowrap transition-all duration-150 cursor-pointer shrink-0"
            :class="categoriaActiva === cat.id
              ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
              : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <span>{{ cat.nombre }}</span>
            <span
              class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold"
              :class="categoriaActiva === cat.id ? 'bg-white/25 text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
            >
              {{ (conteos.categorias[cat.id] || 0).toLocaleString('es-BO') }}
            </span>
          </button>
        </div>

        <!-- NIVEL 2: FILTROS MULTIFACÉTICOS Y BUSCADOR -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-black/[0.04]">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Selector de Canal -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
              <select
                v-model="filtros.canal"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <!-- Selector de Archivo Base / Snapshot -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] max-w-xs">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Archivo:</span>
              <select
                v-model="filtros.archivo_origen"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer truncate max-w-[190px]"
              >
                <option value="TODOS">Todos los Archivos</option>
                <option v-for="a in archivos_base" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>

            <!-- Rango Fechas -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Desde:</span>
              <input
                type="date"
                v-model="filtros.fecha_inicio"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
              <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Hasta:</span>
              <input
                type="date"
                v-model="filtros.fecha_fin"
                @change="consultar(true)"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
            </div>

            <!-- Limpiar Filtros -->
            <button
              type="button"
              @click="restablecerFiltros"
              class="apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] text-[11px] font-semibold px-2.5 py-1 rounded-[10px] cursor-pointer transition-all"
            >
              Limpiar
            </button>
          </div>

          <!-- Buscador en tiempo real -->
          <div class="relative w-full sm:w-72">
            <input
              type="text"
              v-model="filtros.busqueda"
              @keyup.enter="consultar(true)"
              placeholder="Buscar cliente, ID, vendedor, ruta..."
              class="w-full apple-control-pill text-xs font-medium pl-8 pr-7 py-1.5 rounded-[10px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:ring-1 focus:ring-[#0071E3]"
            />
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button
              v-if="filtros.busqueda"
              type="button"
              @click="filtros.busqueda = ''; consultar(true)"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- NIVEL 3: BARRA DE ACCIÓN POR LOTE (APARECE CUANDO HAY REGISTROS SELECCIONADOS) -->
        <div
          v-if="seleccionados.length > 0"
          class="flex items-center justify-between gap-3 px-3 py-2 bg-[#0071E3]/10 border border-[#0071E3]/25 rounded-[12px] animate-fadeIn"
        >
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3] animate-pulse"></span>
            <span class="text-xs font-bold text-[#0071E3]">
              {{ seleccionados.length }} registro(s) seleccionado(s)
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <button
              type="button"
              @click="abrirModalResolucionLote('APROBADO')"
              class="px-3 py-1 rounded-[8px] bg-[#34C759] text-white text-xs font-bold shadow-xs hover:bg-[#28A745] cursor-pointer transition-all flex items-center gap-1"
            >
              <span>Aprobar Selección</span>
            </button>

            <button
              type="button"
              @click="abrirModalResolucionLote('RECHAZADO')"
              class="px-3 py-1 rounded-[8px] bg-[#FF3B30] text-white text-xs font-bold shadow-xs hover:bg-[#D93025] cursor-pointer transition-all flex items-center gap-1"
            >
              <span>Rechazar Selección</span>
            </button>

            <button
              type="button"
              @click="abrirModalResolucionLote('DESCARTADO')"
              class="px-3 py-1 rounded-[8px] bg-[#8E8E93] text-white text-xs font-bold shadow-xs hover:bg-[#636366] cursor-pointer transition-all flex items-center gap-1"
            >
              <span>Descartar</span>
            </button>

            <button
              type="button"
              @click="seleccionados = []"
              class="px-2 py-1 rounded-[8px] text-xs font-semibold text-[#6E6E73] hover:text-[#1D1D1F] cursor-pointer"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70">
        <div class="inline-block animate-spin w-8 h-8 border-3 border-[#0071E3] border-t-transparent rounded-full mb-3"></div>
        <div class="text-sm font-bold text-[#1D1D1F]">Consultando auditoría de ruteo...</div>
        <div class="text-xs text-[#86868B] mt-0.5">Analizando registros y métricas de cartera</div>
      </div>

      <!-- BANDEJA VACÍA -->
      <div
        v-else-if="registros.length === 0"
        class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70"
      >
        <div class="w-12 h-12 rounded-full bg-black/[0.04] flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-sm font-bold text-[#1D1D1F]">No hay modificaciones pendientes</div>
        <div class="text-xs text-[#86868B] mt-1 max-w-md mx-auto">
          No se encontraron eventos pendientes para los filtros seleccionados. Todos los cambios del padrón han sido auditados o no hay novedades detectadas.
        </div>
      </div>

      <!-- TABLA DE CAMBIOS PENDIENTES -->
      <div v-else class="apple-glass-card rounded-[16px] overflow-hidden border border-white/70 shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-black/[0.03] border-b border-black/[0.06] text-[#6E6E73] text-[10px] uppercase font-bold tracking-wider">
                <th class="py-2.5 px-3 w-8 text-center">
                  <input
                    type="checkbox"
                    :checked="todosSeleccionados"
                    @change="toggleSeleccionarTodos"
                    class="rounded cursor-pointer accent-[#0071E3]"
                  />
                </th>
                <th class="py-2.5 px-3">Tipo de Modificación</th>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">Ruta / Canal / Día</th>
                <th class="py-2.5 px-3">Detalle del Cambio</th>
                <th class="py-2.5 px-3">Vendedor Asignado</th>
                <th class="py-2.5 px-3">Impacto / Riesgo Comercial</th>
                <th class="py-2.5 px-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <tr
                v-for="reg in registros"
                :key="reg.id"
                class="hover:bg-black/[0.02] transition-colors duration-150"
                :class="seleccionados.includes(reg.id) ? 'bg-[#0071E3]/[0.04]' : ''"
              >
                <!-- Checkbox Selección -->
                <td class="py-2.5 px-3 text-center">
                  <input
                    type="checkbox"
                    :value="reg.id"
                    v-model="seleccionados"
                    class="rounded cursor-pointer accent-[#0071E3]"
                  />
                </td>

                <!-- Tipo de Modificación -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="obtenerBadgeClase(reg.categoria_clave)"
                  >
                    {{ reg.etiqueta_evento }}
                  </span>
                  <div class="text-[10px] font-mono text-[#86868B] mt-1">
                    {{ reg.fecha_auditoria }}
                  </div>
                </td>

                <!-- Cliente -->
                <td class="py-2.5 px-3 min-w-[200px]">
                  <div class="flex items-start gap-1.5">
                    <div>
                      <div class="font-bold text-[#1D1D1F] leading-tight">
                        {{ reg.cliente }}
                      </div>
                      <div class="text-[10px] font-mono text-[#6E6E73] mt-0.5">
                        ID: #{{ reg.cliente_id }}
                      </div>
                      <div class="text-[10px] text-[#86868B] truncate max-w-[240px] mt-0.5">
                        {{ reg.direccion }}
                      </div>
                    </div>

                    <!-- Botón de Fotos -->
                    <button
                      v-if="reg.cant_fotos > 0"
                      type="button"
                      @click="abrirFotos(reg)"
                      class="shrink-0 apple-control-pill px-1.5 py-0.5 rounded-[6px] text-[10px] font-bold text-[#0071E3] hover:bg-[#0071E3]/10 cursor-pointer"
                      title="Ver fotografías de visitas"
                    >
                      📷 {{ reg.cant_fotos }}
                    </button>
                  </div>
                </td>

                <!-- Ruta / Canal / Día -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <div class="font-bold text-[#1D1D1F] font-mono">
                    {{ reg.ruta }}
                  </div>
                  <div class="text-[10px] text-[#6E6E73]">
                    Canal: <span class="font-semibold text-[#1D1D1F]">{{ reg.canal }}</span>
                  </div>
                  <div class="text-[10px] text-[#86868B] capitalize">
                    Día: {{ reg.dia }}
                  </div>
                </td>

                <!-- Detalle del Cambio (Anterior vs Nuevo) -->
                <td class="py-2.5 px-3 min-w-[220px]">
                  <div class="space-y-1">
                    <div class="text-[10px] font-mono text-[#86868B] uppercase">
                      Campo: <span class="font-bold text-[#1D1D1F]">{{ reg.campo_afectado }}</span>
                    </div>

                    <!-- Si es cambio de ruta o día -->
                    <div v-if="reg.categoria_clave === 'CAMBIOS_RUTA' || reg.categoria_clave === 'CAMBIOS_DIA'" class="flex items-center gap-1.5 text-xs font-mono">
                      <span class="px-1.5 py-0.5 rounded bg-black/[0.04] text-[#6E6E73] line-through">
                        {{ reg.valor_anterior }}
                      </span>
                      <span class="text-[#86868B]">→</span>
                      <span class="px-1.5 py-0.5 rounded bg-[#0071E3]/10 text-[#0071E3] font-bold">
                        {{ reg.valor_nuevo }}
                      </span>
                    </div>

                    <!-- Si es cambio de cluster o vendedor -->
                    <div v-else-if="reg.categoria_clave === 'CAMBIO_DE_CLUSTER' || reg.categoria_clave === 'MODIFICADO_VENDEDOR'" class="space-y-0.5 text-[11px]">
                      <div class="text-[#86868B] text-[10px]">
                        Antes: <span class="text-[#6E6E73] font-semibold">{{ reg.valor_anterior }}</span>
                      </div>
                      <div class="text-[#0071E3] font-bold">
                        Nuevo: {{ reg.valor_nuevo }}
                      </div>
                    </div>

                    <!-- Si es coordenadas -->
                    <div v-else-if="reg.categoria_clave === 'MODIFICADO_COORDENADAS'" class="text-[10px] font-mono">
                      <div class="text-[#86868B]">Anterior: {{ reg.valor_anterior }}</div>
                      <div class="text-[#0071E3] font-bold">Nuevo: {{ reg.valor_nuevo }}</div>
                    </div>

                    <!-- Otros atributos generales -->
                    <div v-else class="text-[11px]">
                      <div v-if="reg.valor_anterior !== '-'" class="text-[#86868B] text-[10px]">
                        Antes: <span class="line-through text-[#6E6E73]">{{ reg.valor_anterior }}</span>
                      </div>
                      <div class="text-[#1D1D1F] font-semibold">
                        {{ reg.valor_nuevo }}
                      </div>
                    </div>

                    <div v-if="reg.observaciones" class="text-[10px] text-[#86868B] italic">
                      {{ reg.observaciones }}
                    </div>
                  </div>
                </td>

                <!-- Vendedor Asignado -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <div class="font-semibold text-[#1D1D1F]">
                    {{ reg.vendedor }}
                  </div>
                  <div class="text-[10px] font-mono text-[#86868B]">
                    {{ reg.archivo_origen }}
                  </div>
                </td>

                <!-- Impacto / Riesgo Comercial -->
                <td class="py-2.5 px-3 min-w-[170px]">
                  <!-- Si es baja/inactivado con ventas históricas -->
                  <div v-if="reg.historico_ventas" class="space-y-1">
                    <div class="flex items-center gap-1.5">
                      <span
                        class="text-[9px] font-bold px-1.5 py-0.2 rounded border"
                        :class="reg.evaluacion_riesgo?.badge_class || 'bg-black/[0.05] text-[#6E6E73]'"
                      >
                        {{ reg.evaluacion_riesgo?.nivel_riesgo || 'EVALUADO' }}
                      </span>
                      <span class="text-[10px] font-mono font-bold text-[#1D1D1F]">
                        Bs {{ reg.historico_ventas.promedio_mensual.toLocaleString('es-BO') }}/mes
                      </span>
                    </div>
                    <div class="text-[9px] text-[#86868B]">
                      5M Total: Bs {{ reg.historico_ventas.total_5m.toLocaleString('es-BO') }}
                    </div>
                  </div>

                  <!-- Si es coordenadas con desplazamiento -->
                  <div v-else-if="reg.evaluacion_riesgo?.distancia_metros !== undefined" class="space-y-0.5">
                    <span
                      class="text-[9px] font-bold px-1.5 py-0.2 rounded border inline-block"
                      :class="reg.evaluacion_riesgo.nivel_riesgo === 'ALTO' ? 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]' : 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]'"
                    >
                      Delta: {{ reg.evaluacion_riesgo.distancia_metros }} m
                    </span>
                  </div>

                  <!-- Sin impacto crítico -->
                  <div v-else class="text-[10px] text-[#86868B]">
                    Impacto operativo estándar
                  </div>
                </td>

                <!-- Acciones en Línea -->
                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1">
                    <!-- Botón Aprobar -->
                    <button
                      type="button"
                      @click="abrirModalResolucion([reg.id], 'APROBADO', reg)"
                      class="px-2 py-1 rounded-[6px] bg-[#34C759]/15 text-[#248A3D] hover:bg-[#34C759] hover:text-white text-[11px] font-bold transition-all cursor-pointer"
                      title="Aprobar modificación"
                    >
                      Aprobar
                    </button>

                    <!-- Botón Rechazar -->
                    <button
                      type="button"
                      @click="abrirModalResolucion([reg.id], 'RECHAZADO', reg)"
                      class="px-2 py-1 rounded-[6px] bg-[#FF3B30]/15 text-[#C9342C] hover:bg-[#FF3B30] hover:text-white text-[11px] font-bold transition-all cursor-pointer"
                      title="Rechazar modificación"
                    >
                      Rechazar
                    </button>

                    <!-- Botón Descartar -->
                    <button
                      type="button"
                      @click="abrirModalResolucion([reg.id], 'DESCARTADO', reg)"
                      class="px-2 py-1 rounded-[6px] bg-black/[0.05] text-[#6E6E73] hover:bg-black/[0.1] hover:text-[#1D1D1F] text-[11px] font-semibold transition-all cursor-pointer"
                      title="Descartar evento"
                    >
                      Descartar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PIE DE TABLA Y CONTEO -->
        <div class="px-4 py-3 bg-black/[0.02] border-t border-black/[0.05] flex items-center justify-between text-xs text-[#6E6E73]">
          <div>
            Mostrando <span class="font-bold text-[#1D1D1F]">{{ registros.length }}</span> de <span class="font-bold text-[#1D1D1F]">{{ totalRegistros.toLocaleString('es-BO') }}</span> eventos pendientes en esta categoría
          </div>

          <div class="flex items-center gap-2">
            <span class="text-[11px] text-[#86868B]">
              Selección: {{ seleccionados.length }} registro(s)
            </span>
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
                  {{ modalResolucion.titulo }}
                </h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">
                  {{ modalResolucion.subtitulo }}
                </p>
              </div>

              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                :class="modalResolucion.estado === 'APROBADO' ? 'bg-[#34C759]/15 text-[#248A3D] border-[#34C759]/30' : (modalResolucion.estado === 'RECHAZADO' ? 'bg-[#FF3B30]/15 text-[#C9342C] border-[#FF3B30]/30' : 'bg-black/[0.08] text-[#6E6E73] border-black/10')"
              >
                {{ modalResolucion.estado }}
              </span>
            </div>

            <!-- Dictamen Selector -->
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
                class="w-full apple-control-pill rounded-[10px] p-2.5 text-xs text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:ring-1 focus:ring-[#0071E3]"
              ></textarea>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-black/[0.06]">
              <button
                type="button"
                @click="modalResolucion.abierto = false"
                class="px-3 py-1.5 rounded-[9px] text-xs font-semibold text-[#6E6E73] hover:text-[#1D1D1F] cursor-pointer"
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
});

const categoriasDisponibles = [
  { id: 'TODOS', nombre: 'Todos los Cambios' },
  { id: 'ALTAS_REACTIVACIONES', nombre: 'Altas y Reactivaciones' },
  { id: 'BAJAS_INACTIVADOS', nombre: 'Bajas e Inactivaciones' },
  { id: 'CAMBIOS_RUTA', nombre: 'Cambios de Ruta' },
  { id: 'CAMBIOS_DIA', nombre: 'Cambios de Día' },
  { id: 'CAMBIO_DE_CLUSTER', nombre: 'Cambios de Clúster' },
  { id: 'MODIFICADO_VENDEDOR', nombre: 'Cambios de Vendedor' },
  { id: 'MODIFICADO_COORDENADAS', nombre: 'Coordenadas' },
  { id: 'FRECUENCIA_VISITA', nombre: 'Frecuencia Logística' },
  { id: 'MODIFICACION_DATOS', nombre: 'Modificación de Datos' },
];

const categoriaActiva = ref(props.initial_categoria || 'TODOS');
const cargando = ref(false);
const registros = ref([]);
const totalRegistros = ref(0);
const seleccionados = ref([]);
const fotosModalRef = ref(null);

const conteos = reactive({
  total: 0,
  pendientes: 0,
  aprobados: 0,
  rechazados: 0,
  descartados: 0,
  categorias: {},
});

const filtros = reactive({
  canal: 'TODOS',
  archivo_origen: 'TODOS',
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
  subtitulo: '',
  procesando: false,
});

const todosSeleccionados = computed(() => {
  return registros.value.length > 0 && seleccionados.value.length === registros.value.length;
});

function toggleSeleccionarTodos() {
  if (todosSeleccionados.value) {
    seleccionados.value = [];
  } else {
    seleccionados.value = registros.value.map(r => r.id);
  }
}

function seleccionarCategoria(catId) {
  categoriaActiva.value = catId;
  seleccionados.value = [];
  consultar(true);
}

function restablecerFiltros() {
  filtros.canal = 'TODOS';
  filtros.archivo_origen = 'TODOS';
  filtros.fecha_inicio = '';
  filtros.fecha_fin = '';
  filtros.busqueda = '';
  categoriaActiva.value = 'TODOS';
  consultar(true);
}

async function consultar(resetSeleccion = false) {
  if (resetSeleccion) {
    seleccionados.value = [];
  }
  cargando.value = true;
  try {
    const params = {
      estado_auditoria: 'PENDIENTE',
      categoria: categoriaActiva.value,
      canal: filtros.canal,
      archivo_origen: filtros.archivo_origen,
      fecha_inicio: filtros.fecha_inicio,
      fecha_fin: filtros.fecha_fin,
      busqueda: filtros.busqueda,
      limit: 200,
    };

    const res = await axios.get(route('supervisor.auditoria.pendientes.consultar'), { params });
    registros.value = res.data.registros || [];
    totalRegistros.value = res.data.total || 0;
    if (res.data.conteos) {
      conteos.total = res.data.conteos.total || 0;
      conteos.pendientes = res.data.conteos.pendientes || 0;
      conteos.aprobados = res.data.conteos.aprobados || 0;
      conteos.rechazados = res.data.conteos.rechazados || 0;
      conteos.descartados = res.data.conteos.descartados || 0;
      conteos.categorias = res.data.conteos.categorias || {};
    }
  } catch (e) {
    console.error('Error consultando auditoría pendiente:', e);
  } finally {
    cargando.value = false;
  }
}

function abrirFotos(reg) {
  if (fotosModalRef.value) {
    fotosModalRef.value.abrir(reg.cliente_id, reg.cliente, reg.ruta);
  }
}

function abrirModalResolucion(ids, estado, reg = null) {
  modalResolucion.ids = ids;
  modalResolucion.estado = estado;
  modalResolucion.observaciones = estado === 'DESCARTADO'
    ? 'Evento descartado de auditoría.'
    : `Dictamen de auditoría: ${estado}`;
  modalResolucion.titulo = reg ? `${estado}: ${reg.cliente}` : `Resolución Masiva (${ids.length} registros)`;
  modalResolucion.subtitulo = reg ? `${reg.etiqueta_evento} · ID: #${reg.cliente_id} · Ruta: ${reg.ruta}` : `Se dictaminará el estado ${estado} para los registros seleccionados.`;
  modalResolucion.abierto = true;
}

function abrirModalResolucionLote(estado) {
  if (seleccionados.value.length === 0) return;
  abrirModalResolucion([...seleccionados.value], estado);
}

async function ejecutarResolucion() {
  if (modalResolucion.ids.length === 0) return;
  modalResolucion.procesando = true;
  try {
    await axios.post(route('supervisor.auditoria.pendientes.resolver'), {
      ids: modalResolucion.ids,
      estado: modalResolucion.estado,
      observaciones: modalResolucion.observaciones,
    });
    modalResolucion.abierto = false;
    seleccionados.value = [];
    await consultar();
  } catch (e) {
    alert('Error al guardar dictamen: ' + (e.response?.data?.error || e.message));
  } finally {
    modalResolucion.procesando = false;
  }
}

function obtenerBadgeClase(catClave) {
  switch (catClave) {
    case 'ALTAS_REACTIVACIONES':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
    case 'BAJAS_INACTIVADOS':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
    case 'CAMBIOS_RUTA':
      return 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]';
    case 'CAMBIOS_DIA':
      return 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
    case 'CAMBIO_DE_CLUSTER':
      return 'bg-[#F5EEF8] text-[#8E44AD] border-[#E8DAEF]';
    case 'MODIFICADO_VENDEDOR':
      return 'bg-[#E8F8F5] text-[#16A085] border-[#D1F2EB]';
    case 'MODIFICADO_COORDENADAS':
      return 'bg-[#FEF5E7] text-[#D35400] border-[#FADBD8]';
    case 'FRECUENCIA_VISITA':
      return 'bg-[#EBF5FB] text-[#2980B9] border-[#D4E6F1]';
    default:
      return 'bg-black/[0.05] text-[#1D1D1F] border-black/10';
  }
}

onMounted(() => {
  consultar();
});
</script>

