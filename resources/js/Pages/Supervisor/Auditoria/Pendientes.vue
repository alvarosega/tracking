<template>
  <SupervisorLayout title="Auditoría | Cambios Pendientes de Ruteo">
    <div class="space-y-3 max-w-full font-sans select-none pb-12">
      <AuditoriaSubTabs active-tab="pendientes" />

      <!-- PANEL PRINCIPAL DE CONTROL Y FILTROS -->
      <div class="sticky top-0 z-30 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70">
        
        <!-- FILA 1: CATEGORÍAS DE CAMBIO (CHIPS INTERACTIVOS CON CONTEOS EN VIVO) -->
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

        <!-- FILA 2: BARRA ÁGIL DE BÚSQUEDA Y ACCIONES -->
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-black/[0.04]">
          <div class="flex items-center gap-2 flex-1 min-w-[260px]">
            <!-- Buscador en tiempo real -->
            <div class="relative w-full max-w-md">
              <input
                type="text"
                v-model="filtros.busqueda"
                @keyup.enter="consultar(true, true)"
                placeholder="Buscar cliente, ID, vendedor o ruta..."
                class="w-full apple-control-pill text-xs font-medium pl-8 pr-7 py-1.5 rounded-[10px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:ring-1 focus:ring-[#0071E3]"
              />
              <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <button
                v-if="filtros.busqueda"
                type="button"
                @click="filtros.busqueda = ''; consultar(true, true)"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold cursor-pointer"
              >
                ✕
              </button>
            </div>

            <!-- Botón Desplegable de Filtros Avanzados -->
            <button
              type="button"
              @click="mostrarFiltrosAvanzados = !mostrarFiltrosAvanzados"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-[10px] text-xs font-semibold cursor-pointer transition-all shrink-0"
              :class="mostrarFiltrosAvanzados || cantidadFiltrosActivos > 0
                ? 'bg-[#0071E3]/10 text-[#0071E3] border border-[#0071E3]/30 font-bold'
                : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <span>Filtros</span>
              <span
                v-if="cantidadFiltrosActivos > 0"
                class="w-4 h-4 rounded-full bg-[#0071E3] text-white text-[10px] font-mono font-bold flex items-center justify-center ml-0.5"
              >
                {{ cantidadFiltrosActivos }}
              </span>
              <svg
                class="w-3 h-3 transition-transform duration-150"
                :class="mostrarFiltrosAvanzados ? 'rotate-180' : ''"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Limpiar Filtros -->
            <button
              v-if="cantidadFiltrosActivos > 0 || filtros.busqueda || categoriaActiva !== 'TODOS'"
              type="button"
              @click="restablecerFiltros"
              class="text-xs font-semibold text-[#86868B] hover:text-[#FF3B30] px-2 py-1 transition-colors cursor-pointer"
              title="Restablecer todos los filtros"
            >
              Limpiar
            </button>
          </div>

          <!-- Resumen de conteo rápido -->
          <div class="text-[11px] font-medium text-[#86868B]">
            Mostrando <strong class="text-[#1D1D1F]">{{ registros.length }}</strong> de <strong class="text-[#1D1D1F]">{{ totalRegistros.toLocaleString('es-BO') }}</strong> eventos
          </div>
        </div>

        <!-- FILA 3: PANEL COLAPSABLE DE FILTROS AVANZADOS -->
        <div
          v-if="mostrarFiltrosAvanzados"
          class="pt-2.5 pb-1 border-t border-black/[0.06] grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 animate-fadeIn"
        >
          <!-- Canal -->
          <div class="space-y-1">
            <label class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</label>
            <div class="apple-control-pill rounded-[9px] px-2.5 py-1">
              <select
                v-model="filtros.canal"
                @change="onCanalChange"
                class="w-full bg-transparent text-xs font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
          </div>

          <!-- Ruta -->
          <div class="space-y-1">
            <label class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</label>
            <div class="apple-control-pill rounded-[9px] px-2.5 py-1">
              <select
                v-model="filtros.ruta"
                @change="consultar(true, true)"
                class="w-full bg-transparent text-xs font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todas las Rutas</option>
                <option v-for="r in rutasOpciones" :key="r.ruta" :value="r.ruta">{{ r.ruta }}</option>
              </select>
            </div>
          </div>

          <!-- Archivo Origen -->
          <div class="space-y-1">
            <label class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Archivo Base:</label>
            <div class="apple-control-pill rounded-[9px] px-2.5 py-1">
              <select
                v-model="filtros.archivo_origen"
                @change="consultar(true, true)"
                class="w-full bg-transparent text-xs font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer truncate"
              >
                <option value="TODOS">Todos los Archivos</option>
                <option v-for="a in archivos_base" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>
          </div>

          <!-- Rango Fechas -->
          <div class="space-y-1">
            <label class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Rango Fechas:</label>
            <div class="flex items-center gap-1 apple-control-pill rounded-[9px] px-2 py-0.5">
              <input
                type="date"
                v-model="filtros.fecha_inicio"
                @change="consultar(true, true)"
                class="w-1/2 bg-transparent text-[11px] font-medium text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
              <span class="text-[#86868B] text-[10px]">&ndash;</span>
              <input
                type="date"
                v-model="filtros.fecha_fin"
                @change="consultar(true, true)"
                class="w-1/2 bg-transparent text-[11px] font-medium text-[#1D1D1F] focus:outline-none cursor-pointer"
              />
            </div>
          </div>
        </div>

        <!-- FILA 4: BARRA DE ACCIÓN POR LOTE (CUANDO HAY REGISTROS SELECCIONADOS) -->
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
              class="px-2 py-1 rounded-[8px] text-xs font-semibold text-[#6E6E73] hover:text-[#1D1D1F] cursor-pointer ml-1"
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
        <div class="text-xs text-[#86868B] mt-0.5">Analizando registros y causas de cambio en cartera</div>
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

      <!-- TABLA PRINCIPAL MINIMALISTA (TODO EL TEXTO COMPLETO, SIN COLUMNAS INNECESARIAS) -->
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
                <th class="py-2.5 px-3 min-w-[260px]">Cliente</th>
                <th class="py-2.5 px-3 w-40">Territorio</th>
                <th class="py-2.5 px-3 w-44">Tipo de Cambio</th>
                <th class="py-2.5 px-3 min-w-[320px]">Transición (Antes &rarr; Después)</th>
                <th class="py-2.5 px-3 text-right w-44">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <tr
                v-for="reg in registros"
                :key="reg.id"
                class="hover:bg-[#0071E3]/[0.03] transition-colors duration-150"
                :class="seleccionados.includes(reg.id) ? 'bg-[#0071E3]/[0.06]' : ''"
              >
                <!-- Checkbox Selección -->
                <td class="py-2 px-3 text-center align-middle">
                  <input
                    type="checkbox"
                    :value="reg.id"
                    v-model="seleccionados"
                    class="rounded cursor-pointer accent-[#0071E3]"
                  />
                </td>

                <!-- 1. CLIENTE (TEXTO COMPLETO) -->
                <td class="py-2.5 px-3 align-middle">
                  <div class="space-y-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="font-bold text-[#1D1D1F] text-xs leading-snug break-words">
                        {{ reg.cliente }}
                      </span>
                      <span class="text-[10px] font-mono text-[#86868B] shrink-0">
                        #{{ reg.cliente_id }}
                      </span>
                    </div>

                    <div class="text-[11px] text-[#6E6E73] leading-snug break-words">
                      {{ reg.direccion || 'Sin dirección registrada' }}
                    </div>

                    <!-- Botón de fotos si tiene fotos -->
                    <div v-if="reg.cant_fotos > 0" class="pt-0.5">
                      <button
                        type="button"
                        @click="abrirFotos(reg)"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#0071E3]/10 text-[#0071E3] hover:bg-[#0071E3]/20 font-bold text-[10px] cursor-pointer"
                        title="Ver fotografías de visita"
                      >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                        </svg>
                        <span>{{ reg.cant_fotos }} foto(s)</span>
                      </button>
                    </div>
                  </div>
                </td>

                <!-- 2. TERRITORIO (TEXTO COMPLETO) -->
                <td class="py-2.5 px-3 align-middle">
                  <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-1 font-mono text-[11px] flex-wrap">
                      <span class="px-1.5 py-0.5 rounded bg-black/[0.05] text-[#1D1D1F] font-bold">
                        {{ reg.ruta }}
                      </span>
                      <span class="px-1.5 py-0.5 rounded bg-black/[0.03] text-[#6E6E73] capitalize">
                        {{ reg.dia }}
                      </span>
                    </div>
                    <span class="text-[10px] text-[#86868B] leading-tight break-words">
                      {{ reg.canal }}
                    </span>
                  </div>
                </td>

                <!-- 3. TIPO DE CAMBIO -->
                <td class="py-2.5 px-3 align-middle">
                  <div class="space-y-1">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border break-words"
                      :class="obtenerBadgeClase(reg.categoria_clave)"
                    >
                      {{ reg.etiqueta_evento }}
                    </span>
                    <div class="text-[10px] font-mono text-[#86868B] flex items-center gap-1">
                      <span>{{ reg.fecha_auditoria }}</span>
                    </div>
                  </div>
                </td>

                <!-- 4. TRANSICIÓN (ANTES ➔ DESPUÉS) - TEXTO COMPLETO SIN RECORTES -->
                <td class="py-2.5 px-3 align-middle">
                  <div class="flex items-center gap-2 font-mono text-xs flex-wrap">
                    <!-- Antes (Completo) -->
                    <span
                      class="px-2.5 py-1 rounded bg-slate-100 text-slate-700 border border-slate-200 line-through opacity-85 break-words max-w-full"
                    >
                      {{ reg.antes_fmt }}
                    </span>

                    <svg class="w-3.5 h-3.5 text-[#0071E3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>

                    <!-- Después (Completo) -->
                    <span
                      class="px-2.5 py-1 rounded bg-sky-50 text-[#0071E3] font-bold border border-sky-200 break-words max-w-full"
                    >
                      {{ reg.despues_fmt }}
                    </span>

                    <!-- Desplegable con mapa comparativo para cambios de coordenadas -->
                    <AuditoriaCoordenadasMapa
                      v-if="esCambioCoordenadas(reg)"
                      :id="reg.id"
                      :valor-anterior="reg.antes_fmt"
                      :valor-nuevo="reg.despues_fmt"
                      :cliente="reg.cliente"
                      :cliente-id="reg.cliente_id"
                    />
                  </div>
                </td>

                <!-- 5. ACCIONES DIRECTAS MINIMALISTAS -->
                <td class="py-2.5 px-3 text-right align-middle whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <!-- Botón Aprobar -->
                    <button
                      type="button"
                      @click="abrirModalResolucion([reg.id], 'APROBADO', reg)"
                      class="px-2.5 py-1 rounded-[8px] bg-[#34C759]/15 text-[#248A3D] hover:bg-[#34C759] hover:text-white text-xs font-bold transition-all cursor-pointer inline-flex items-center gap-1"
                      title="Aprobar cambio"
                    >
                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                      <span>Aprobar</span>
                    </button>

                    <!-- Botón Rechazar -->
                    <button
                      type="button"
                      @click="abrirModalResolucion([reg.id], 'RECHAZADO', reg)"
                      class="px-2.5 py-1 rounded-[8px] bg-[#FF3B30]/15 text-[#C9342C] hover:bg-[#FF3B30] hover:text-white text-xs font-bold transition-all cursor-pointer inline-flex items-center gap-1"
                      title="Rechazar cambio"
                    >
                      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                      <span>Rechazar</span>
                    </button>

                    <!-- Botón Descartar -->
                    <button
                      type="button"
                      @click="abrirModalResolucion([reg.id], 'DESCARTADO', reg)"
                      class="px-2 py-1 rounded-[8px] bg-black/[0.04] text-[#6E6E73] hover:bg-black/[0.08] hover:text-[#1D1D1F] text-xs font-semibold transition-all cursor-pointer"
                      title="Descartar evento"
                    >
                      <span>Descartar</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PIE DE TABLA Y PAGINACIÓN -->
        <AuditoriaPaginacion
          :total="totalRegistros"
          :page="pagina"
          :per-page="perPage"
          :loading="cargando"
          :seleccionados-count="seleccionados.length"
          etiqueta="eventos pendientes"
          @update:page="pagina = $event"
          @update:per-page="perPage = $event"
          @change="onPaginacionChange"
        />
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
import AuditoriaPaginacion from '@/Pages/Supervisor/Auditoria/Components/AuditoriaPaginacion.vue';
import AuditoriaCoordenadasMapa from '@/Pages/Supervisor/Auditoria/Components/AuditoriaCoordenadasMapa.vue';

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
  { id: 'FRECUENCIA_VISITA', nombre: 'Frecuencia Semanal' },
  { id: 'MODIFICACION_DATOS', nombre: 'Modificación de Datos' },
];

const categoriaActiva = ref(props.initial_categoria || 'TODOS');
const cargando = ref(false);
const registros = ref([]);
const totalRegistros = ref(0);
const pagina = ref(1);
const perPage = ref(50);
const seleccionados = ref([]);
const fotosModalRef = ref(null);

// Estado de controles
const mostrarFiltrosAvanzados = ref(false);

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
  ruta: 'TODOS',
  archivo_origen: 'TODOS',
  fecha_inicio: '',
  fecha_fin: '',
  busqueda: '',
});

const cantidadFiltrosActivos = computed(() => {
  let count = 0;
  if (filtros.canal && filtros.canal !== 'TODOS') count++;
  if (filtros.ruta && filtros.ruta !== 'TODOS') count++;
  if (filtros.archivo_origen && filtros.archivo_origen !== 'TODOS') count++;
  if (filtros.fecha_inicio) count++;
  if (filtros.fecha_fin) count++;
  return count;
});

const rutasOpciones = computed(() => {
  if (!filtros.canal || filtros.canal === 'TODOS') {
    return props.rutas;
  }
  return props.rutas.filter(r => r.canal === filtros.canal);
});

function onCanalChange() {
  filtros.ruta = 'TODOS';
  consultar(true, true);
}

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
  if (registros.value.length === 0) return false;
  return registros.value.every(r => seleccionados.value.includes(r.id));
});

function toggleSeleccionarTodos() {
  const idsPagina = registros.value.map(r => r.id);
  if (todosSeleccionados.value) {
    seleccionados.value = seleccionados.value.filter(id => !idsPagina.includes(id));
  } else {
    const set = new Set([...seleccionados.value, ...idsPagina]);
    seleccionados.value = Array.from(set);
  }
}

function seleccionarCategoria(catId) {
  categoriaActiva.value = catId;
  seleccionados.value = [];
  consultar(true, true);
}

function restablecerFiltros() {
  filtros.canal = 'TODOS';
  filtros.ruta = 'TODOS';
  filtros.archivo_origen = 'TODOS';
  filtros.fecha_inicio = '';
  filtros.fecha_fin = '';
  filtros.busqueda = '';
  categoriaActiva.value = 'TODOS';
  consultar(true, true);
}

function onPaginacionChange(e) {
  pagina.value = e.page;
  perPage.value = e.perPage;
  consultar(false, false);
}

async function consultar(resetSeleccion = false, resetPagina = false) {
  if (resetSeleccion) {
    seleccionados.value = [];
  }
  if (resetPagina) {
    pagina.value = 1;
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
      page: pagina.value,
      per_page: perPage.value,
    };

    if (filtros.ruta && filtros.ruta !== 'TODOS') {
      params.rutas = [filtros.ruta];
    }

    const res = await axios.get(route('supervisor.auditoria.pendientes.consultar'), { params });
    registros.value = res.data.registros || [];
    totalRegistros.value = res.data.total || 0;
    if (res.data.page) {
      pagina.value = res.data.page;
    }
    if (res.data.per_page) {
      perPage.value = res.data.per_page;
    }
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
    await consultar(true, false);
    if (registros.value.length === 0 && pagina.value > 1) {
      pagina.value--;
      await consultar(false, false);
    }
  } catch (e) {
    alert('Error al guardar dictamen: ' + (e.response?.data?.error || e.message));
  } finally {
    modalResolucion.procesando = false;
  }
}

function esCambioCoordenadas(reg) {
  if (!reg) return false;
  const tipo = (reg.tipo_evento || '').toUpperCase();
  const cat = (reg.categoria_clave || '').toUpperCase();
  const campo = (reg.campo_afectado || '').toLowerCase();
  return tipo === 'MODIFICADO_COORDENADAS' || cat === 'MODIFICADO_COORDENADAS' || campo === 'coordenadas' || campo === 'latitud' || campo === 'longitud' || campo === 'ubicacion';
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

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
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
