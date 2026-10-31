<template>
  <SupervisorLayout title="Auditoría | Histórico de Resoluciones">
    <div class="space-y-3 max-w-full font-sans select-none pb-8">
      <AuditoriaSubTabs active-tab="historico" />

      <!-- PANEL PRINCIPAL DE CONTROL Y FILTROS -->
      <div class="sticky top-0 z-40 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70">
        <!-- NIVEL 1: SELECTOR DE ESTADO DE RESOLUCIÓN -->
        <div class="flex items-center justify-between gap-2 overflow-x-auto no-scrollbar pb-0.5">
          <div class="flex items-center gap-1.5 shrink-0">
            <button
              type="button"
              @click="cambiarEstado('RESUELTOS')"
              class="px-3 py-1.5 rounded-[10px] text-xs font-semibold cursor-pointer transition-all"
              :class="filtros.estado_auditoria === 'RESUELTOS'
                ? 'bg-[#1D1D1F] text-white shadow-xs'
                : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span>Todos Resueltos</span>
              <span class="ml-1.5 text-[10px] font-mono opacity-80">
                {{ ((conteos.aprobados || 0) + (conteos.rechazados || 0) + (conteos.descartados || 0)).toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('APROBADO')"
              class="px-3 py-1.5 rounded-[10px] text-xs font-semibold cursor-pointer transition-all"
              :class="filtros.estado_auditoria === 'APROBADO'
                ? 'bg-[#34C759] text-white shadow-[0_2px_8px_rgba(52,199,89,0.35)]'
                : 'apple-control-pill text-[#248A3D] hover:bg-[#34C759]/10'"
            >
              <span>Aprobados</span>
              <span class="ml-1.5 text-[10px] font-mono opacity-80">
                {{ (conteos.aprobados || 0).toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('RECHAZADO')"
              class="px-3 py-1.5 rounded-[10px] text-xs font-semibold cursor-pointer transition-all"
              :class="filtros.estado_auditoria === 'RECHAZADO'
                ? 'bg-[#FF3B30] text-white shadow-[0_2px_8px_rgba(255,59,48,0.35)]'
                : 'apple-control-pill text-[#C9342C] hover:bg-[#FF3B30]/10'"
            >
              <span>Rechazados</span>
              <span class="ml-1.5 text-[10px] font-mono opacity-80">
                {{ (conteos.rechazados || 0).toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('DESCARTADO')"
              class="px-3 py-1.5 rounded-[10px] text-xs font-semibold cursor-pointer transition-all"
              :class="filtros.estado_auditoria === 'DESCARTADO'
                ? 'bg-[#8E8E93] text-white shadow-xs'
                : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span>Descartados</span>
              <span class="ml-1.5 text-[10px] font-mono opacity-80">
                {{ (conteos.descartados || 0).toLocaleString('es-BO') }}
              </span>
            </button>
          </div>

          <!-- Buscador Rápido -->
          <div class="relative w-64 sm:w-72 shrink-0">
            <input
              type="text"
              v-model="filtros.busqueda"
              @keyup.enter="consultar(true)"
              placeholder="Buscar por ID, cliente, auditor..."
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

        <!-- NIVEL 2: CATEGORÍAS EN CHIPS -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-touch pt-1 border-t border-black/[0.04]">
          <button
            v-for="cat in categoriasDisponibles"
            :key="cat.id"
            type="button"
            @click="seleccionarCategoria(cat.id)"
            class="flex items-center gap-1.5 px-3 py-1 rounded-[9px] text-[11px] font-semibold whitespace-nowrap transition-all duration-150 cursor-pointer shrink-0"
            :class="categoriaActiva === cat.id
              ? 'bg-[#0071E3] text-white shadow-xs'
              : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <span>{{ cat.nombre }}</span>
            <span
              class="text-[9px] font-mono px-1.5 py-0.2 rounded-full font-bold"
              :class="categoriaActiva === cat.id ? 'bg-white/25 text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
            >
              {{ (conteos.categorias[cat.id] || 0).toLocaleString('es-BO') }}
            </span>
          </button>
        </div>

        <!-- NIVEL 3: FILTROS ADICIONALES -->
        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-black/[0.04]">
          <!-- Canal -->
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

          <!-- Ruta -->
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

          <!-- Archivo Origen -->
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
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70">
        <div class="inline-block animate-spin w-8 h-8 border-3 border-[#0071E3] border-t-transparent rounded-full mb-3"></div>
        <div class="text-sm font-bold text-[#1D1D1F]">Consultando histórico de resoluciones...</div>
        <div class="text-xs text-[#86868B] mt-0.5">Recuperando bitácora de auditoría de ruteo</div>
      </div>

      <!-- BANDEJA VACÍA -->
      <div
        v-else-if="registros.length === 0"
        class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70"
      >
        <div class="w-12 h-12 rounded-full bg-black/[0.04] flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-sm font-bold text-[#1D1D1F]">No hay resoluciones históricas</div>
        <div class="text-xs text-[#86868B] mt-1 max-w-md mx-auto">
          No se encontraron dictámenes previos para los criterios seleccionados en la bitácora.
        </div>
      </div>

      <!-- TABLA DE HISTÓRICO DE RESOLUCIONES CON ANTES VS DESPUÉS Y CAUSA EXPLÍCITA -->
      <div v-else class="apple-glass-card rounded-[16px] overflow-hidden border border-white/70 shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-black/[0.03] border-b border-black/[0.06] text-[#6E6E73] text-[10px] uppercase font-bold tracking-wider">
                <th class="py-2.5 px-3 w-32">Estado / Dictamen</th>
                <th class="py-2.5 px-3 w-40">Qué Cambió</th>
                <th class="py-2.5 px-3 min-w-[200px]">Cliente & Territorio</th>
                <th class="py-2.5 px-3 min-w-[190px]">Antes (Situación Previa)</th>
                <th class="py-2.5 px-3 min-w-[190px]">Después (Propuesta Resuelta)</th>
                <th class="py-2.5 px-3 min-w-[220px]">Por Qué Cambió (Causa & Origen)</th>
                <th class="py-2.5 px-3 min-w-[180px]">Auditor & Observaciones</th>
                <th class="py-2.5 px-3 text-right w-24">Detalle</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <template v-for="reg in registros" :key="reg.id">
                <tr
                  class="hover:bg-black/[0.02] transition-colors duration-150"
                  :class="filaExpandida === reg.id ? 'bg-black/[0.015]' : ''"
                >
                  <!-- 1. Estado / Dictamen -->
                  <td class="py-3 px-3 align-top whitespace-nowrap">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border"
                      :class="obtenerBadgeEstadoClase(reg.estado_auditoria)"
                    >
                      {{ reg.estado_auditoria }}
                    </span>
                    <div class="text-[10px] font-mono text-[#86868B] mt-1">
                      ID #{{ reg.id }}
                    </div>
                  </td>

                  <!-- 2. Qué Cambió -->
                  <td class="py-3 px-3 align-top whitespace-nowrap">
                    <div class="space-y-1">
                      <div class="font-bold text-[#1D1D1F] text-xs leading-tight">
                        {{ reg.campo_label }}
                      </div>
                      <div>
                        <span
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border"
                          :class="obtenerBadgeClase(reg.categoria_clave)"
                        >
                          {{ reg.etiqueta_evento }}
                        </span>
                      </div>
                      <div class="text-[10px] font-mono text-[#86868B] flex items-center gap-1">
                        <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ reg.fecha_auditoria }}</span>
                      </div>
                    </div>
                  </td>

                  <!-- 3. Cliente & Territorio -->
                  <td class="py-3 px-3 align-top min-w-[200px]">
                    <div class="space-y-1">
                      <div class="flex items-start justify-between gap-1.5">
                        <div>
                          <div class="font-bold text-[#1D1D1F] leading-tight">
                            {{ reg.cliente }}
                          </div>
                          <div class="text-[10px] font-mono text-[#6E6E73] mt-0.5">
                            ID: #{{ reg.cliente_id }}
                          </div>
                        </div>

                        <!-- Botón de Fotos -->
                        <button
                          v-if="reg.cant_fotos > 0"
                          type="button"
                          @click="abrirFotos(reg)"
                          class="shrink-0 flex items-center gap-1 apple-control-pill px-1.5 py-0.5 rounded-[6px] text-[10px] font-bold text-[#0071E3] hover:bg-[#0071E3]/10 cursor-pointer"
                          title="Ver fotografías de visitas"
                        >
                          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                          </svg>
                          <span>{{ reg.cant_fotos }}</span>
                        </button>
                      </div>

                      <div class="text-[10px] text-[#6E6E73] truncate max-w-[220px]" :title="reg.direccion">
                        {{ reg.direccion }}
                      </div>

                      <div class="flex flex-wrap items-center gap-1 pt-0.5 text-[10px] font-mono">
                        <span class="px-1.5 py-0.2 rounded bg-black/[0.04] text-[#1D1D1F] font-semibold">
                          {{ reg.ruta }}
                        </span>
                        <span class="px-1.5 py-0.2 rounded bg-black/[0.03] text-[#6E6E73]">
                          {{ reg.canal }}
                        </span>
                        <span class="px-1.5 py-0.2 rounded bg-black/[0.03] text-[#6E6E73] capitalize">
                          {{ reg.dia }}
                        </span>
                      </div>
                    </div>
                  </td>

                  <!-- 4. ANTES: Situación Previa -->
                  <td class="py-3 px-3 align-top min-w-[190px]">
                    <div class="p-2 rounded-[9px] bg-slate-50 border border-slate-200/80 space-y-1">
                      <div class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        <span>Estado Previo</span>
                      </div>
                      <div class="text-[11px] font-mono text-slate-700 leading-snug break-words">
                        {{ reg.antes_fmt }}
                      </div>
                    </div>
                  </td>

                  <!-- 5. DESPUÉS: Propuesta Resuelta -->
                  <td class="py-3 px-3 align-top min-w-[190px]">
                    <div class="p-2 rounded-[9px] bg-sky-50/80 border border-sky-200/90 space-y-1">
                      <div class="text-[9px] font-bold text-[#0071E3] uppercase tracking-wider flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
                        <span>Propuesta Resuelta</span>
                      </div>
                      <div class="text-[11px] font-mono font-semibold text-[#0071E3] leading-snug break-words">
                        {{ reg.despues_fmt }}
                      </div>
                    </div>
                  </td>

                  <!-- 6. POR QUÉ CAMBIÓ: Causa, Impacto & Archivo -->
                  <td class="py-3 px-3 align-top min-w-[220px]">
                    <div class="space-y-1.5">
                      <div class="text-[11px] text-[#1D1D1F] font-medium leading-relaxed">
                        {{ reg.motivo_cambio?.motivo || 'Actualización registrada en padrón maestro.' }}
                      </div>

                      <div
                        v-if="reg.motivo_cambio?.impacto"
                        class="p-1.5 rounded-[7px] text-[10px] leading-tight border"
                        :class="obtenerNivelClase(reg.motivo_cambio?.nivel)"
                      >
                        {{ reg.motivo_cambio.impacto }}
                      </div>

                      <div class="text-[10px] font-mono text-[#86868B] flex items-center gap-1">
                        <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="truncate max-w-[190px]" :title="reg.archivo_origen">
                          {{ reg.archivo_origen }}
                        </span>
                      </div>
                    </div>
                  </td>

                  <!-- 7. Auditor & Observaciones -->
                  <td class="py-3 px-3 align-top min-w-[180px]">
                    <div class="space-y-1">
                      <div class="font-bold text-[#1D1D1F] text-xs">
                        {{ reg.auditado_por || 'SUPERVISOR' }}
                      </div>
                      <div class="text-[10px] font-mono text-[#86868B]">
                        {{ reg.fecha_resolucion || '-' }}
                      </div>
                      <div class="text-[11px] text-[#1D1D1F] font-medium leading-relaxed bg-black/[0.02] p-1.5 rounded-[6px] border border-black/[0.04]">
                        {{ reg.observaciones || 'Sin observaciones registradas.' }}
                      </div>
                    </div>
                  </td>

                  <!-- 8. Detalle / Inspección -->
                  <td class="py-3 px-3 text-right align-top whitespace-nowrap">
                    <button
                      type="button"
                      @click="alternarInspector(reg.id)"
                      class="text-[10px] font-semibold text-[#0071E3] hover:underline cursor-pointer inline-flex items-center gap-1"
                    >
                      <span>{{ filaExpandida === reg.id ? 'Cerrar' : 'Comparativa' }}</span>
                      <svg
                        class="w-3 h-3 transition-transform duration-150"
                        :class="filaExpandida === reg.id ? 'rotate-180' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </button>
                  </td>
                </tr>

                <!-- FILA EXPANDIBLE: INSPECTOR HISTÓRICO CARA A CARA -->
                <tr v-if="filaExpandida === reg.id" class="bg-black/[0.02]">
                  <td colspan="8" class="p-3 sm:p-4">
                    <div class="apple-glass-card rounded-[14px] p-4 border border-white/80 shadow-xs space-y-3.5">
                      <div class="flex items-center justify-between pb-2 border-b border-black/[0.06]">
                        <div class="flex items-center gap-2">
                          <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold border"
                            :class="obtenerBadgeEstadoClase(reg.estado_auditoria)"
                          >
                            {{ reg.estado_auditoria }}
                          </span>
                          <span class="text-xs font-bold text-[#1D1D1F]">
                            Bitácora Histórica · Evento #{{ reg.id }} · {{ reg.campo_label }}
                          </span>
                        </div>
                        <span class="text-[10px] font-mono text-[#86868B]">
                          Resuelto: {{ reg.fecha_resolucion }} por {{ reg.auditado_por }}
                        </span>
                      </div>

                      <!-- COMPARATIVA CARA A CARA -->
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <!-- Situación Previa -->
                        <div class="p-3 rounded-[10px] bg-slate-50 border border-slate-200/90 space-y-2">
                          <span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider">
                            Situación Previa (Antes)
                          </span>
                          <div class="text-xs font-mono text-slate-800 font-semibold bg-white p-2 rounded border border-slate-200">
                            {{ reg.antes_fmt }}
                          </div>
                          <div class="text-[10px] text-slate-600 space-y-0.5">
                            <div><span class="font-bold text-slate-700">Ruta Asignada:</span> {{ reg.ruta }}</div>
                            <div><span class="font-bold text-slate-700">Día de Visita:</span> {{ reg.dia }}</div>
                            <div><span class="font-bold text-slate-700">Vendedor:</span> {{ reg.vendedor }}</div>
                          </div>
                        </div>

                        <!-- Propuesta Dictaminada -->
                        <div class="p-3 rounded-[10px] bg-sky-50/70 border border-sky-200 space-y-2">
                          <span class="text-[10px] font-bold text-[#0071E3] uppercase tracking-wider">
                            Propuesta Dictaminada (Después)
                          </span>
                          <div class="text-xs font-mono text-[#0071E3] font-bold bg-white p-2 rounded border border-sky-200">
                            {{ reg.despues_fmt }}
                          </div>
                          <div class="text-[10px] text-slate-600 space-y-0.5">
                            <div><span class="font-bold text-slate-700">Campo Técnico:</span> {{ reg.campo_afectado }}</div>
                            <div><span class="font-bold text-slate-700">Evento:</span> {{ reg.tipo_evento }}</div>
                            <div><span class="font-bold text-slate-700">Dictamen Final:</span> {{ reg.estado_auditoria }}</div>
                          </div>
                        </div>
                      </div>

                      <!-- CAUSA Y DETALLES -->
                      <div class="p-3 rounded-[10px] bg-white border border-black/[0.06] space-y-2">
                        <div class="text-[10px] font-bold text-[#1D1D1F] uppercase tracking-wider">
                          Diagnóstico & Causa Registrada
                        </div>
                        <div class="text-xs text-[#1D1D1F]">
                          {{ reg.motivo_cambio?.motivo }}
                        </div>
                        <div v-if="reg.motivo_cambio?.impacto" class="text-xs text-[#6E6E73]">
                          {{ reg.motivo_cambio.impacto }}
                        </div>
                        <div class="text-[10px] text-slate-600 pt-1 border-t border-black/[0.04]">
                          <span class="font-bold">Dictamen del Auditor:</span> {{ reg.observaciones || 'Sin observaciones adicionales.' }}
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- PIE DE TABLA Y PAGINACIÓN -->
        <AuditoriaPaginacion
          :total="totalRegistros"
          :page="pagina"
          :per-page="perPage"
          :loading="cargando"
          etiqueta="eventos resueltos"
          @update:page="pagina = $event"
          @update:per-page="perPage = $event"
          @change="onPaginacionChange"
        />
      </div>

      <!-- MODAL DE FOTOGRAFÍAS -->
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
const filaExpandida = ref(null);
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
  estado_auditoria: 'RESUELTOS',
  canal: 'TODOS',
  ruta: 'TODOS',
  archivo_origen: 'TODOS',
  fecha_inicio: '',
  fecha_fin: '',
  busqueda: '',
});

const rutasOpciones = computed(() => {
  if (!filtros.canal || filtros.canal === 'TODOS') {
    return props.rutas;
  }
  return props.rutas.filter(r => r.canal === filtros.canal);
});

function onCanalChange() {
  filtros.ruta = 'TODOS';
  consultar(true);
}

function cambiarEstado(nuevoEstado) {
  filtros.estado_auditoria = nuevoEstado;
  filaExpandida.value = null;
  consultar(true);
}

function alternarInspector(id) {
  filaExpandida.value = filaExpandida.value === id ? null : id;
}

function seleccionarCategoria(catId) {
  categoriaActiva.value = catId;
  filaExpandida.value = null;
  consultar(true);
}

function restablecerFiltros() {
  filtros.estado_auditoria = 'RESUELTOS';
  filtros.canal = 'TODOS';
  filtros.ruta = 'TODOS';
  filtros.archivo_origen = 'TODOS';
  filtros.fecha_inicio = '';
  filtros.fecha_fin = '';
  filtros.busqueda = '';
  categoriaActiva.value = 'TODOS';
  filaExpandida.value = null;
  consultar(true);
}

function onPaginacionChange(e) {
  pagina.value = e.page;
  perPage.value = e.perPage;
  consultar(false);
}

async function consultar(resetPagina = false) {
  if (resetPagina) {
    pagina.value = 1;
  }
  cargando.value = true;
  try {
    const params = {
      estado_auditoria: filtros.estado_auditoria,
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

    const res = await axios.get(route('supervisor.auditoria.historico.consultar'), { params });
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
    console.error('Error consultando histórico de auditoría:', e);
  } finally {
    cargando.value = false;
  }
}

function abrirFotos(reg) {
  if (fotosModalRef.value) {
    fotosModalRef.value.abrir(reg.cliente_id, reg.cliente, reg.ruta);
  }
}

function obtenerBadgeEstadoClase(estado) {
  switch (estado) {
    case 'APROBADO':
      return 'bg-[#EBF9EF] text-[#248A3D] border-[#34C759]/30';
    case 'RECHAZADO':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FF3B30]/30';
    case 'DESCARTADO':
      return 'bg-black/[0.05] text-[#636366] border-black/10';
    default:
      return 'bg-black/[0.04] text-[#1D1D1F] border-black/10';
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

function obtenerNivelClase(nivel) {
  switch (nivel) {
    case 'DANGER':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5] font-semibold';
    case 'WARNING':
      return 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3] font-semibold';
    case 'SUCCESS':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
    default:
      return 'bg-black/[0.03] text-[#6E6E73] border-black/[0.06]';
  }
}

onMounted(() => {
  consultar();
});
</script>
