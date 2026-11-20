<template>
  <SupervisorLayout title="Saneamiento | Matriz BD">
    <div class="space-y-3 max-w-full font-sans select-none pb-12">
      <SaneamientoSubTabs active-tab="bd" />

      <!-- BARRA DE FILTROS MINIMALISTA Y COMPACTA (ESTILO PIZARRA UNILEVER) -->
      <div class="sticky top-0 z-30 apple-glass-card rounded-[14px] p-2.5 sm:p-3 border border-white/80 shadow-xs space-y-2 bg-white/80 backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
          
          <!-- Filtros de Selección Rápida -->
          <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <!-- Canal -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px] bg-white border border-black/[0.06]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Canal:</span>
              <select
                v-model="filtroCanal"
                @change="alCambiarCanal"
                class="bg-transparent text-[11px] font-semibold text-[#0071E3] focus:outline-none cursor-pointer"
              >
                <option value="TDB">TDB (Por defecto)</option>
                <option value="TODOS">Todos los Canales</option>
                <option
                  v-for="c in canalesDisponibles.filter(c => c !== 'TDB' && c !== 'TODOS')"
                  :key="c"
                  :value="c"
                >
                  {{ c }}
                </option>
              </select>
            </div>

            <!-- Ruta -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px] bg-white border border-black/[0.06]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Ruta:</span>
              <select
                v-model="filtroRuta"
                @change="consultarMatriz"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer max-w-[130px]"
              >
                <option value="TODAS">Todas</option>
                <option v-for="r in rutasDisponibles" :key="r.ruta" :value="r.ruta">
                  {{ r.ruta }}
                </option>
              </select>
            </div>

            <!-- Día -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px] bg-white border border-black/[0.06]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Día:</span>
              <select
                v-model="filtroDia"
                @change="consultarMatriz"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos</option>
                <option value="lunes">Lunes</option>
                <option value="martes">Martes</option>
                <option value="miercoles">Miércoles</option>
                <option value="jueves">Jueves</option>
                <option value="viernes">Viernes</option>
                <option value="sabado">Sábado</option>
              </select>
            </div>

            <!-- Falla / Tipo Error -->
            <div class="flex items-center gap-1 apple-control-pill rounded-[8px] px-2 py-1 text-[11px] bg-neutral-100/80 border border-black/[0.06]">
              <span class="text-[9px] font-bold text-[#86868B] uppercase">Falla:</span>
              <select
                v-model="filtroTipoDato"
                @change="consultarMatriz"
                class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todas las Fallas</option>
                <option value="SOLO_DEFECTUOSOS">Solo con Datos Incorrectos</option>
                <option value="FALTA_APELLIDO">Sin Apellido / Nombre Incompleto</option>
                <option value="SIN_TELEFONO">Sin Teléfono / Celular</option>
                <option value="SIN_NIT_CI">Sin NIT / Carnet de Identidad</option>
                <option value="SIN_DIRECCION">Sin Dirección / Sin Número</option>
                <option value="SIN_COORDENADAS">Sin Coordenadas GPS (0.0 o nulo)</option>
                <option value="SIN_DIA">Sin Día de Visita</option>
                <option value="CORRECTOS">Datos 100% Correctos</option>
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="consultarMatriz"
              :disabled="cargando"
              class="flex items-center gap-1.5 px-3 py-1 rounded-[8px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] transition-all cursor-pointer shadow-xs disabled:opacity-60"
            >
              <svg v-if="cargando" class="w-3 h-3 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <span>{{ cargando ? 'Cargando...' : 'Consultar' }}</span>
            </button>

            <!-- Limpiar -->
            <button
              v-if="filtroCanal !== 'TDB' || filtroRuta !== 'TODAS' || filtroDia !== 'TODOS' || filtroTipoDato !== 'TODOS' || filtroBusqueda"
              type="button"
              @click="restablecerFiltros"
              class="apple-control-pill text-[#6E6E73] hover:text-[#FF3B30] text-[10px] font-semibold px-2 py-1 rounded-[8px] cursor-pointer"
            >
              Limpiar
            </button>
          </div>

          <!-- Buscador y Controles de Expansión Global -->
          <div class="flex items-center gap-2">
            <!-- Buscador en tiempo real -->
            <div class="relative w-44 sm:w-56">
              <input
                type="text"
                v-model="filtroBusqueda"
                @input="onBusquedaInput"
                placeholder="Buscar cliente, ID o ruta..."
                class="w-full bg-[#F5F5F7] text-[#1D1D1F] text-[11px] font-medium placeholder-[#86868B] rounded-[8px] pl-6 pr-6 py-1 border border-transparent focus:border-[#0071E3] focus:bg-white focus:outline-none font-mono"
              />
              <svg class="w-3 h-3 text-[#86868B] absolute left-2 top-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <button
                v-if="filtroBusqueda"
                type="button"
                @click="filtroBusqueda = ''; onBusquedaInput()"
                class="w-4 h-4 absolute right-1.5 top-1 text-[#86868B] hover:text-[#1D1D1F] flex items-center justify-center cursor-pointer"
                title="Limpiar búsqueda"
              >
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Botones Expandir / Colapsar Todo -->
            <div class="inline-flex items-center gap-1 shrink-0">
              <button
                type="button"
                @click="expandirTodasLasRutas"
                class="px-2 py-1 rounded-[7px] bg-black/[0.04] hover:bg-black/[0.08] text-[#1D1D1F] text-[10px] font-semibold cursor-pointer"
                title="Desplegar todas las rutas"
              >
                Expandir
              </button>
              <button
                type="button"
                @click="colapsarTodasLasRutas"
                class="px-2 py-1 rounded-[7px] bg-black/[0.04] hover:bg-black/[0.08] text-[#6E6E73] text-[10px] font-semibold cursor-pointer"
                title="Replegar todas las rutas"
              >
                Colapsar
              </button>
            </div>
          </div>

        </div>

        <!-- Fila de Resumen Compacto Ejecutivo (Una sola línea sobria) -->
        <div v-if="resumenGeneral" class="flex flex-wrap items-center justify-between text-[11px] text-[#86868B] pt-1.5 border-t border-black/[0.04] gap-2">
          <div class="flex items-center gap-2.5 flex-wrap">
            <span>Rutas: <strong class="text-[#1D1D1F] font-mono">{{ resumenGeneral.total_rutas }}</strong></span>
            <span>&bull;</span>
            <span>Universo Clientes: <strong class="text-[#1D1D1F] font-mono">{{ resumenGeneral.total_clientes }}</strong></span>
            <span>&bull;</span>
            <span>Defectuosos: <strong class="text-rose-600 font-mono font-bold">{{ resumenGeneral.total_defectuosos }} ({{ resumenGeneral.porcentaje_defectuoso }}%)</strong></span>
            <span>&bull;</span>
            <span>Correctos: <strong class="text-emerald-700 font-mono font-bold">{{ resumenGeneral.total_correctos }} ({{ (100 - resumenGeneral.porcentaje_defectuoso).toFixed(1) }}%)</strong></span>
          </div>

          <!-- Micro-Píldoras de Anomalías Detectadas -->
          <div v-if="resumenGeneral.totales_anomalias" class="flex items-center gap-1.5 flex-wrap font-mono text-[9.5px]">
            <span v-if="resumenGeneral.totales_anomalias.sin_coordenadas > 0" class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 border border-rose-200" title="Sin coordenadas GPS válidas">
              Sin GPS: {{ resumenGeneral.totales_anomalias.sin_coordenadas }}
            </span>
            <span v-if="resumenGeneral.totales_anomalias.sin_telefono > 0" class="px-1.5 py-0.2 rounded bg-amber-50 text-amber-800 border border-amber-200" title="Sin teléfono o celular">
              Sin Tel: {{ resumenGeneral.totales_anomalias.sin_telefono }}
            </span>
            <span v-if="resumenGeneral.totales_anomalias.sin_nit_ci > 0" class="px-1.5 py-0.2 rounded bg-sky-50 text-sky-800 border border-sky-200" title="Sin NIT o Carnet">
              Sin NIT/CI: {{ resumenGeneral.totales_anomalias.sin_nit_ci }}
            </span>
            <span v-if="resumenGeneral.totales_anomalias.falta_apellido > 0" class="px-1.5 py-0.2 rounded bg-purple-50 text-purple-800 border border-purple-200" title="Nombre sin apellido o incompleto">
              Sin Apellido: {{ resumenGeneral.totales_anomalias.falta_apellido }}
            </span>
            <span v-if="resumenGeneral.totales_anomalias.sin_direccion > 0" class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 border border-slate-200" title="Sin dirección o sin número">
              Sin Dir: {{ resumenGeneral.totales_anomalias.sin_direccion }}
            </span>
            <span v-if="resumenGeneral.totales_anomalias.sin_dia > 0" class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 border border-slate-200" title="Sin día de visita asignado">
              Sin Día: {{ resumenGeneral.totales_anomalias.sin_dia }}
            </span>
          </div>
        </div>
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[14px] p-10 text-center border border-white/70 bg-white/70">
        <div class="inline-block w-7 h-7 border-2 border-[#0071E3]/20 border-t-[#0071E3] rounded-full animate-spin"></div>
        <div class="text-xs font-semibold text-[#1D1D1F] mt-2">Cargando Matriz de Saneamiento BD...</div>
      </div>

      <!-- BANDEJA VACÍA -->
      <div
        v-else-if="rutasFiltradas.length === 0"
        class="apple-glass-card rounded-[14px] p-10 text-center border border-white/70 bg-white/70"
      >
        <div class="text-xs font-bold text-[#1D1D1F]">No se encontraron registros</div>
        <div class="text-[11px] text-[#86868B] mt-0.5">No hay rutas ni clientes que coincidan con los filtros seleccionados.</div>
      </div>

      <!-- ============================================================== -->
      <!-- MATRIZ CON DESGLOSE JERÁRQUICO POR RUTAS (TODO EL CONTENIDO)   -->
      <!-- ============================================================== -->
      <div v-else class="apple-glass-card rounded-[14px] overflow-hidden border border-white/80 shadow-xs bg-white/80">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <tbody class="divide-y divide-black/[0.04]">
              <template v-for="r in rutasFiltradas" :key="r.ruta">
                
                <!-- NIVEL 1: FILA ENCABEZADO DE RUTA (COLAPSABLE) -->
                <tr
                  @click="alternarRuta(r.ruta)"
                  class="bg-[#F8F9FB] hover:bg-[#EEF1F5] transition-colors cursor-pointer select-none border-t border-b border-black/[0.06]"
                  :class="rutasExpandidas.has(r.ruta) ? 'bg-[#EEF1F5]/80' : ''"
                >
                  <td colspan="4" class="py-2.5 px-3.5">
                    <div class="flex items-center justify-between gap-3 flex-wrap sm:flex-nowrap">
                      
                      <!-- Info Ruta, Canal, Vendedor y PDVs -->
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

                          <span v-if="r.canal && r.canal !== 'SIN CANAL'" class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-[#0071E3]/10 text-[#0071E3] font-mono">
                            {{ r.canal }}
                          </span>

                          <span class="text-[11px] text-[#6E6E73] font-normal truncate max-w-[160px]">
                            &bull; {{ r.vendedor }}
                          </span>

                          <span class="text-[11px] text-[#86868B] font-mono font-medium">
                            &bull; {{ obtenerClientesDeRuta(r).length }} de {{ r.total_clientes }} PDV(s)
                          </span>
                        </div>
                      </div>

                      <!-- Diagnóstico de Fallas, Días de Jornada y Botón Desglosar -->
                      <div class="flex items-center gap-2 shrink-0">
                        
                        <!-- Micro-pills de Días con Total y Defectuosos -->
                        <div class="hidden lg:flex items-center gap-1 font-mono text-[9px]">
                          <template v-for="dia in DIAS_LISTA" :key="dia">
                            <span
                              v-if="r.dias && r.dias[dia] && r.dias[dia].total > 0"
                              class="px-1.5 py-0.5 rounded border"
                              :class="r.dias[dia].defectuosos > 0 ? 'bg-rose-50 text-rose-700 border-rose-200 font-bold' : 'bg-slate-100 text-slate-600 border-slate-200'"
                              :title="`${dia.toUpperCase()}: ${r.dias[dia].total} clientes (${r.dias[dia].defectuosos} con fallas)`"
                            >
                              {{ dia.slice(0, 2).toUpperCase() }}: {{ r.dias[dia].total }}
                            </span>
                          </template>
                        </div>

                        <!-- Badge de Fallas y Completitud -->
                        <span
                          class="px-2 py-0.5 rounded-full font-mono font-bold text-[10px] border flex items-center gap-1"
                          :class="r.total_defectuosos > 0
                            ? (r.porcentaje_defectuoso > 50
                              ? 'bg-rose-50 text-rose-700 border-rose-200'
                              : 'bg-amber-50 text-amber-800 border-amber-200')
                            : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
                        >
                          <span
                            v-if="r.total_defectuosos > 0"
                            class="w-1.5 h-1.5 rounded-full"
                            :class="r.porcentaje_defectuoso > 50 ? 'bg-rose-500 animate-pulse' : 'bg-amber-500'"
                          ></span>
                          <span>
                            {{ r.total_defectuosos > 0 ? `${r.total_defectuosos} def. (${r.porcentaje_defectuoso}%)` : '100% Correctos' }}
                          </span>
                        </span>

                        <!-- Botón Desglosar / Ocultar -->
                        <button
                          type="button"
                          class="px-2 py-0.5 rounded-[6px] text-[10px] font-semibold text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.05] transition-all cursor-pointer flex items-center gap-0.5"
                        >
                          <span>{{ rutasExpandidas.has(r.ruta) ? 'Ocultar' : 'Desglosar' }}</span>
                          <svg
                            class="w-3 h-3 transition-transform duration-200"
                            :class="rutasExpandidas.has(r.ruta) ? 'rotate-180 text-[#0071E3]' : ''"
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
                  </td>
                </tr>

                <!-- NIVEL 2: FILA DE CLIENTES DE LA RUTA (CUANDO LA RUTA ESTÁ EXPANDIDA) -->
                <tr v-if="rutasExpandidas.has(r.ruta)">
                  <td colspan="4" class="p-0 border-b border-black/[0.06] bg-[#F8F9FA]/70">
                    <div class="p-3 pl-6 sm:pl-8 space-y-2">
                      <div class="flex items-center justify-between text-[10px] font-mono font-bold text-[#86868B] uppercase tracking-wider pb-0.5">
                        <div class="flex items-center gap-2">
                          <span>Detalle de Clientes (#{{ r.ruta }})</span>
                          <span class="text-slate-400 font-normal">&bull; {{ r.vendedor }}</span>
                        </div>
                        <span>{{ obtenerClientesDeRuta(r).length }} PDV(s)</span>
                      </div>

                      <!-- Tabla de Clientes Integrada en la Ruta -->
                      <div class="overflow-hidden rounded-[10px] border border-black/[0.06] bg-white shadow-2xs">
                        <table class="w-full text-left text-[11px] border-collapse">
                          <thead class="bg-neutral-50/90 text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200">
                            <tr>
                              <th class="py-2 px-3">Cliente</th>
                              <th class="py-2 px-3">ID</th>
                              <th class="py-2 px-3">Día</th>
                              <th class="py-2 px-3">Dirección / Nro</th>
                              <th class="py-2 px-3">Contacto</th>
                              <th class="py-2 px-3">NIT / CI</th>
                              <th class="py-2 px-3">GPS Coordenadas</th>
                              <th class="py-2 px-3 text-center">Severidad</th>
                              <th class="py-2 px-3 text-right">Fotos</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-neutral-100">
                            <tr v-if="obtenerClientesDeRuta(r).length === 0">
                              <td colspan="9" class="py-6 text-center text-[#86868B] font-medium text-xs">
                                No se encontraron clientes con las condiciones seleccionadas en esta ruta.
                              </td>
                            </tr>
                            <tr
                              v-for="cli in obtenerClientesDeRuta(r)"
                              :key="cli.cliente_id"
                              class="hover:bg-neutral-50/80 transition-colors"
                            >
                              <!-- Cliente & Indicador Sin Apellido -->
                              <td class="py-2 px-3 font-bold text-ink">
                                <div class="truncate max-w-xs">{{ cli.cliente || 'SIN NOMBRE' }}</div>
                                <div v-if="cli.falta_apellido" class="text-[9px] text-purple-700 font-mono font-bold flex items-center gap-1 mt-0.5">
                                  <span class="w-1.5 h-1.5 rounded-full bg-purple-600 shrink-0"></span>
                                  <span>Sin apellido</span>
                                </div>
                              </td>

                              <!-- ID -->
                              <td class="py-2 px-3 font-mono text-ink-secondary">#{{ cli.cliente_id }}</td>

                              <!-- Día de Visita -->
                              <td class="py-2 px-3 font-mono">
                                <span
                                  v-if="cli.dia && cli.dia !== 'sin dia'"
                                  class="px-1.5 py-0.5 rounded bg-black/[0.04] text-[#1D1D1F] text-[10px] capitalize font-medium"
                                >
                                  {{ cli.dia }}
                                </span>
                                <span v-else class="text-rose-600 font-bold text-[10px] bg-rose-50 px-1 py-0.5 rounded border border-rose-200">
                                  Sin Día
                                </span>
                              </td>

                              <!-- Dirección & Nro -->
                              <td class="py-2 px-3 text-neutral-600 truncate max-w-xs">
                                <div :class="cli.sin_direccion ? 'text-rose-600 font-semibold' : ''">
                                  {{ cli.direccion || 'Sin dirección' }}
                                </div>
                                <div class="text-[9px] text-neutral-400 font-mono">Nro: {{ cli.nro || 'S/N' }}</div>
                              </td>

                              <!-- Teléfono / Celular -->
                              <td class="py-2 px-3 font-mono">
                                <div :class="cli.sin_telefono ? 'text-amber-700 font-bold' : 'text-neutral-600'">
                                  {{ cli.telefono || cli.celular || 'Sin número' }}
                                </div>
                              </td>

                              <!-- NIT / CI -->
                              <td class="py-2 px-3 font-mono">
                                <div :class="cli.sin_nit_ci ? 'text-blue-700 font-bold' : 'text-neutral-600'">
                                  {{ cli.nit || cli.ci || 'Sin NIT/CI' }}
                                </div>
                              </td>

                              <!-- Coordenadas GPS -->
                              <td class="py-2 px-3 font-mono">
                                <span v-if="cli.sin_coordenadas" class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-bold border border-rose-200 text-[9.5px]">
                                  Sin GPS
                                </span>
                                <span v-else class="text-neutral-500 text-[10px]">
                                  {{ cli.latitud ? cli.latitud.toFixed(4) : '-' }}, {{ cli.longitud ? cli.longitud.toFixed(4) : '-' }}
                                </span>
                              </td>

                              <!-- Severidad -->
                              <td class="py-2 px-3 text-center">
                                <span
                                  class="px-2 py-0.5 rounded text-[9.5px] font-mono font-bold"
                                  :class="{
                                    'bg-rose-100 text-rose-800 border border-rose-200': cli.severidad === 'CRITICO',
                                    'bg-amber-100 text-amber-800 border border-amber-200': cli.severidad === 'MEDIO',
                                    'bg-yellow-100 text-yellow-800 border border-yellow-200': cli.severidad === 'LEVE',
                                    'bg-emerald-100 text-emerald-800 border border-emerald-200': cli.severidad === 'OK'
                                  }"
                                >
                                  {{ cli.severidad }}
                                </span>
                              </td>

                              <!-- Fotos -->
                              <td class="py-2 px-3 text-right">
                                <button
                                  type="button"
                                  @click="abrirModalFotos(cli)"
                                  class="px-2 py-0.5 bg-white hover:bg-neutral-100 text-ink rounded font-bold border border-neutral-200 shadow-2xs text-[10px] cursor-pointer"
                                  title="Ver fotografías de visita"
                                >
                                  Fotos
                                </button>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>

              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Fotos de Visitas -->
      <ClienteFotosModal
        :visible="modalFotosVisible"
        :cliente-id="clienteSeleccionadoFotos?.cliente_id"
        :cliente-nombre="clienteSeleccionadoFotos?.cliente || clienteSeleccionadoFotos?.nombre"
        :ruta="clienteSeleccionadoFotos?.ruta"
        @close="modalFotosVisible = false"
      />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SaneamientoSubTabs from './Components/SaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const DIAS_LISTA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado']

const cargando = ref(false)
const filtroCanal = ref('TDB')
const filtroRuta = ref('TODAS')
const filtroDia = ref('TODOS')
const filtroTipoDato = ref('TODOS')
const filtroBusqueda = ref('')

const rutasExpandidas = ref(new Set())

const resumenGeneral = ref(null)
const matrizRutas = ref([])
const clientes = ref([])

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

const canalesDisponibles = computed(() => props.catalogo.canales || [])

const rutasDisponibles = computed(() => {
  const rutas = props.catalogo.rutas || []
  if (filtroCanal.value === 'TODOS') return rutas
  return rutas.filter(r => r.canal === filtroCanal.value)
})

onMounted(() => {
  consultarMatriz()
})

function alCambiarCanal() {
  filtroRuta.value = 'TODAS'
  consultarMatriz()
}

function restablecerFiltros() {
  filtroCanal.value = 'TDB'
  filtroRuta.value = 'TODAS'
  filtroDia.value = 'TODOS'
  filtroTipoDato.value = 'TODOS'
  filtroBusqueda.value = ''
  consultarMatriz()
}

// Control de Acordeones
function alternarRuta(rutaNombre) {
  const next = new Set(rutasExpandidas.value)
  if (next.has(rutaNombre)) {
    next.delete(rutaNombre)
  } else {
    next.add(rutaNombre)
  }
  rutasExpandidas.value = next
}

function expandirTodasLasRutas() {
  const set = new Set()
  rutasFiltradas.value.forEach(r => set.add(r.ruta))
  rutasExpandidas.value = set
}

function colapsarTodasLasRutas() {
  rutasExpandidas.value = new Set()
}

function onBusquedaInput() {
  if (filtroBusqueda.value.trim().length >= 2) {
    const set = new Set(rutasExpandidas.value)
    rutasFiltradas.value.forEach(r => set.add(r.ruta))
    rutasExpandidas.value = set
  }
}

// Obtener clientes filtrados pertenecientes a una ruta
function obtenerClientesDeRuta(r) {
  const base = r.clientes && r.clientes.length > 0
    ? r.clientes
    : clientes.value.filter(c => c.ruta === r.ruta)

  if (!filtroBusqueda.value.trim()) {
    return base
  }

  const q = filtroBusqueda.value.trim().toLowerCase()
  return base.filter(c =>
    (c.cliente && c.cliente.toLowerCase().includes(q)) ||
    (c.cliente_id && String(c.cliente_id).includes(q)) ||
    (c.direccion && c.direccion.toLowerCase().includes(q)) ||
    (c.nit && String(c.nit).includes(q)) ||
    (c.ci && String(c.ci).includes(q)) ||
    (c.telefono && String(c.telefono).includes(q)) ||
    (c.celular && String(c.celular).includes(q)) ||
    (c.ruta && c.ruta.toLowerCase().includes(q))
  )
}

// Rutas filtradas en memoria
const rutasFiltradas = computed(() => {
  if (!filtroBusqueda.value.trim()) {
    return matrizRutas.value
  }

  const q = filtroBusqueda.value.trim().toLowerCase()
  return matrizRutas.value.filter(r => {
    if (r.ruta && r.ruta.toLowerCase().includes(q)) return true
    if (r.vendedor && r.vendedor.toLowerCase().includes(q)) return true
    if (r.canal && r.canal.toLowerCase().includes(q)) return true
    return obtenerClientesDeRuta(r).length > 0
  })
})

async function consultarMatriz() {
  cargando.value = true
  try {
    const rutasParam = filtroRuta.value !== 'TODAS'
      ? [filtroRuta.value]
      : (filtroCanal.value !== 'TODOS' ? rutasDisponibles.value.map(r => r.ruta) : null)

    const url = typeof route === 'function'
      ? route('supervisor.saneamiento.bd.matriz')
      : '/supervisor/saneamiento/bd/matriz'

    const { data } = await axios.post(url, {
      canal: filtroCanal.value !== 'TODOS' ? filtroCanal.value : null,
      rutas: rutasParam,
      dia: filtroDia.value !== 'TODOS' ? filtroDia.value : null,
      tipo_error: filtroTipoDato.value !== 'TODOS' ? filtroTipoDato.value : null,
      busqueda: filtroBusqueda.value.trim() || null,
    })

    resumenGeneral.value = data?.resumen_general || null
    matrizRutas.value = data?.matriz_rutas || []
    clientes.value = data?.clientes || []

    // Si hay una sola ruta o búsqueda activa, expandir por conveniencia
    if (matrizRutas.value.length === 1 || filtroBusqueda.value.trim()) {
      const set = new Set()
      matrizRutas.value.forEach(r => set.add(r.ruta))
      rutasExpandidas.value = set
    }
  } catch (err) {
    console.error('Error al consultar matriz BD:', err)
  } finally {
    cargando.value = false
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>
