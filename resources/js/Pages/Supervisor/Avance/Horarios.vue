<template>
  <SupervisorLayout>
    <div class="space-y-4 font-sans text-[#1D1D1F]">
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 1. Header Principal y Selector de Submódulo                      -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3]"></span>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[#1D1D1F]">
              Horarios e Itinerario
            </h1>
            <span class="px-2 py-0.5 rounded-[6px] bg-[#0071E3]/10 text-[#0071E3] font-mono text-[10px] font-bold border border-[#0071E3]/20">
              Auditoría & GIS
            </span>
          </div>
          <p class="text-xs text-[#6E6E73] mt-0.5">
            Cruce de horarios Web vs Móvil con auditoría de jornada y trazado secuencial geográfico 1..N.
          </p>
        </div>

        <!-- Selector de Submódulos Tipo Apple Glass Segmented Control -->
        <div class="flex items-center gap-2 bg-[#F5F5F7] p-1 rounded-[12px] border border-black/[0.06] shadow-xs">
          <button
            type="button"
            @click="cambiarSubmodo('horarios')"
            :class="[
              'px-3.5 py-1.5 rounded-[9px] text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
              submodo === 'horarios'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-black/[0.04]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'
            ]"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Matriz de Horarios</span>
          </button>

          <button
            type="button"
            @click="cambiarSubmodo('itinerario')"
            :class="[
              'px-3.5 py-1.5 rounded-[9px] text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
              submodo === 'itinerario'
                ? 'bg-white text-[#1D1D1F] shadow-xs border border-black/[0.04]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'
            ]"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
            </svg>
            <span>Secuencia de Itinerario & Mapa</span>
          </button>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- SUBMODO 1: MATRIZ DE HORARIOS COMPARATIVA (WEB VS MÓVIL)         -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <template v-if="submodo === 'horarios'">
        <!-- Barra de Filtros Apple Glass -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-float border border-white/60 backdrop-blur-xl">
          <div class="flex flex-wrap items-center gap-2">
            <!-- Filtro Fecha Inicio -->
            <div class="flex items-center gap-1.5 bg-white/80 border border-[#E5E5EA] rounded-[8px] px-2.5 py-1 shadow-xs">
              <label class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">Desde:</label>
              <input
                type="date"
                v-model="filtroFechaInicio"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
              />
            </div>

            <!-- Filtro Fecha Fin -->
            <div class="flex items-center gap-1.5 bg-white/80 border border-[#E5E5EA] rounded-[8px] px-2.5 py-1 shadow-xs">
              <label class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">Corte:</label>
              <input
                type="date"
                v-model="filtroFechaFin"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
              />
            </div>

            <!-- Filtro de Canal -->
            <div class="flex items-center gap-1.5 bg-white/80 border border-[#E5E5EA] rounded-[8px] px-2.5 py-1 shadow-xs">
              <label class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">Canal:</label>
              <select
                v-model="filtroCanal"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option value="TODOS">Todos los Canales</option>
                <option v-for="c in listaCanales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <!-- Botón Consultar -->
            <button
              type="button"
              @click="consultarMatriz"
              :disabled="cargandoMatriz"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#0071E3] hover:bg-[#0077ED] text-white text-xs font-semibold rounded-[8px] shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            >
              <svg
                class="w-3.5 h-3.5 transition-transform"
                :class="{ 'animate-spin': cargandoMatriz }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              <span>Consultar</span>
            </button>

            <!-- Exportar CSV -->
            <button
              type="button"
              @click="exportarMatrizCsv"
              :disabled="cargandoMatriz || rutasFiltradas.length === 0"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/80 hover:bg-white text-[#1D1D1F] text-xs font-semibold rounded-[8px] border border-[#E5E5EA] shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            >
              <svg class="w-3.5 h-3.5 text-[#1D1D1F]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              <span>Exportar</span>
            </button>
          </div>

          <!-- Buscador Rápido -->
          <div class="flex items-center gap-2">
            <input
              type="text"
              v-model="filtroBusqueda"
              placeholder="Buscar Ruta o Preventista..."
              class="bg-white/80 border border-[#E5E5EA] rounded-[8px] px-3 py-1 text-[11px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#0071E3] focus:ring-2 focus:ring-[#0071E3]/20 w-48 sm:w-60 font-medium shadow-xs transition-all"
            />
          </div>
        </div>

        <!-- Resumen de Métricas Superiores -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="apple-glass-card rounded-[14px] p-3 shadow-float border border-white/60">
            <div class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Total Rutas</div>
            <div class="text-xl font-bold font-num text-[#1D1D1F] mt-0.5">{{ totalRutas }}</div>
            <div class="text-[10px] text-[#86868B]">En catálogo maestro</div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-float border border-white/60">
            <div class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Rutas Activas</div>
            <div class="text-xl font-bold font-num text-[#34C759] mt-0.5">{{ totalRutasActivas }}</div>
            <div class="text-[10px] text-[#86868B]">Con marcajes en el rango</div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-float border border-white/60">
            <div class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Días Evaluados</div>
            <div class="text-xl font-bold font-num text-[#0071E3] mt-0.5">{{ fechasRango.length }}</div>
            <div class="text-[10px] text-[#86868B]">Fechas en ventana</div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-float border border-white/60">
            <div class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Rango Actual</div>
            <div class="text-xs font-bold font-mono text-[#1D1D1F] mt-1 truncate">
              {{ formatoFechaCorta(filtroFechaInicio) }} ➔ {{ formatoFechaCorta(filtroFechaFin) }}
            </div>
            <div class="text-[10px] text-[#86868B]">Ventana de auditoría</div>
          </div>
        </div>

        <!-- Tabla Matriz de Horarios -->
        <section class="apple-glass-card rounded-[14px] shadow-float overflow-hidden border border-white/60">
          <div class="px-4 py-3 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex justify-between items-center text-xs">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span class="font-bold text-[#1D1D1F] uppercase tracking-wider text-[11px]">
                Matriz Comparativa de Jornada: Web (Sistema) vs Móvil (App Android)
              </span>
            </div>
            <div class="flex items-center gap-3">
              <div class="flex items-center gap-2 text-[10px] text-[#6E6E73]">
                <span class="inline-flex items-center gap-1 font-semibold text-[#0071E3]">
                  <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span> Web: Ingreso - Salida
                </span>
                <span class="text-[#D1D1D6]">|</span>
                <span class="inline-flex items-center gap-1 font-semibold text-[#34C759]">
                  <span class="w-2 h-2 rounded-full bg-[#34C759]"></span> Móvil: Ingreso - Última Foto
                </span>
              </div>
              <span class="text-[#6E6E73] font-mono font-bold text-[11px]">
                {{ rutasFiltradas.length }} rutas visibles
              </span>
            </div>
          </div>

          <div class="overflow-x-auto max-h-[620px] custom-scroll relative">
            <table class="w-full text-left text-[11px] text-[#1D1D1F] border-separate border-spacing-0 font-num">
              <thead class="bg-[#F5F5F7] text-[#1D1D1F] uppercase text-[10px] font-bold sticky top-0 z-20 border-b border-[#E5E5EA]">
                <tr>
                  <th class="p-2.5 sticky left-0 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA] min-w-[85px]">
                    Ruta
                  </th>
                  <th class="p-2.5 sticky left-[85px] z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA] min-w-[150px]">
                    Preventista
                  </th>
                  <th class="p-2.5 border-b border-r border-[#E5E5EA] text-center min-w-[75px]">
                    Canal
                  </th>
                  <th
                    v-for="f in fechasRango"
                    :key="f"
                    class="p-2 border-b border-r border-[#E5E5EA] text-center min-w-[140px]"
                  >
                    <div class="font-bold text-[#1D1D1F]">{{ getDiaNombreCorto(f) }}</div>
                    <div class="text-[9px] text-[#6E6E73] font-mono font-medium">{{ formatoFechaCorta(f) }}</div>
                  </th>
                </tr>
              </thead>

              <tbody class="divide-y divide-[#E5E5EA] bg-white text-[11px]">
                <tr v-if="cargandoMatriz">
                  <td :colspan="3 + fechasRango.length" class="p-12 text-center text-[#86868B] font-medium">
                    <div class="flex items-center justify-center gap-2">
                      <svg class="w-4 h-4 animate-spin text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                      </svg>
                      <span>Cargando matriz de horarios y cruce de eventos...</span>
                    </div>
                  </td>
                </tr>

                <tr v-else-if="rutasFiltradas.length === 0">
                  <td :colspan="3 + fechasRango.length" class="p-12 text-center text-[#86868B] font-medium">
                    No se encontraron rutas con los filtros seleccionados.
                  </td>
                </tr>

                <tr
                  v-else
                  v-for="r in rutasFiltradas"
                  :key="r.ruta"
                  class="hover:bg-slate-50/70 transition-colors"
                >
                  <!-- Celda Fija 1: Ruta -->
                  <td class="p-2.5 font-bold text-[#0071E3] font-mono sticky left-0 z-10 bg-white border-r border-[#E5E5EA]">
                    {{ r.ruta }}
                  </td>

                  <!-- Celda Fija 2: Vendedor -->
                  <td class="p-2.5 font-medium text-[#1D1D1F] truncate max-w-[150px] sticky left-[85px] z-10 bg-white border-r border-[#E5E5EA]" :title="r.vendedor">
                    {{ r.vendedor }}
                  </td>

                  <!-- Canal -->
                  <td class="p-2.5 text-center font-bold text-[10px] text-[#6E6E73] border-r border-[#E5E5EA]">
                    <span class="px-1.5 py-0.5 rounded bg-[#F5F5F7] border border-[#E5E5EA]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Celdas por Día -->
                  <td
                    v-for="f in fechasRango"
                    :key="f"
                    @click="abrirTimelineModal(r.ruta, f, r.vendedor)"
                    class="p-2 border-r border-[#E5E5EA] cursor-pointer hover:bg-blue-50/60 transition-colors text-center group"
                    :title="`Ver timeline completo de ${r.ruta} el ${f}`"
                  >
                    <div
                      v-if="r.dias && r.dias[f] && r.dias[f].tiene_actividad"
                      class="p-1 rounded-[6px] transition-all group-hover:shadow-xs group-hover:bg-white"
                      :class="r.dias[f].eventos_movil > 0 ? 'bg-slate-50/80 border border-black/[0.04]' : 'bg-slate-50/40'"
                    >
                      <!-- Bloque Sistema (Web) -->
                      <div class="flex items-center justify-between text-[10px] leading-tight font-mono">
                        <span class="text-[9px] font-bold text-[#0071E3] uppercase">Web</span>
                        <span class="font-semibold text-[#1D1D1F]">
                          {{ formatoHoraCorta(r.dias[f].ingreso_sistema) }} - {{ formatoHoraCorta(r.dias[f].salida_sistema) }}
                        </span>
                      </div>

                      <!-- Bloque Móvil (App) -->
                      <div class="flex items-center justify-between text-[10px] leading-tight font-mono mt-1 pt-1 border-t border-black/[0.04]">
                        <span class="text-[9px] font-bold text-[#34C759] uppercase flex items-center gap-0.5">
                          <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                          </svg>
                          App
                        </span>
                        <span class="font-semibold text-[#1D1D1F]">
                          {{ formatoHoraCorta(r.dias[f].ingreso_movil) }} - {{ formatoHoraCorta(r.dias[f].salida_movil) }}
                        </span>
                      </div>
                    </div>

                    <div v-else class="text-center text-[#C7C7CC] font-mono text-[11px] py-1">
                      -
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- SUBMODO 2: SECUENCIA DE ITINERARIO & MAPA LEAFLET 1..N           -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <template v-else-if="submodo === 'itinerario'">
        <!-- Barra de Control Itinerario -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-wrap items-center justify-between gap-2.5 shadow-float border border-white/60 backdrop-blur-xl">
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Selector de Ruta -->
            <div class="flex items-center gap-1.5 bg-white/80 border border-[#E5E5EA] rounded-[8px] px-2.5 py-1 shadow-xs">
              <label class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">Ruta:</label>
              <select
                v-model="itinerarioRutaSeleccionada"
                @change="cargarSecuenciaItinerario"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
              >
                <option v-for="r in catalogo_rutas" :key="r" :value="r">{{ r }}</option>
              </select>
            </div>

            <!-- Selector de Día de la Semana -->
            <div class="flex items-center gap-1.5 bg-white/80 border border-[#E5E5EA] rounded-[8px] px-2.5 py-1 shadow-xs">
              <label class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">Día:</label>
              <select
                v-model="itinerarioDiaSeleccionado"
                @change="cargarSecuenciaItinerario"
                class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer capitalize"
              >
                <option v-for="d in diasSemanaLista" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>

            <!-- Botón Recargar -->
            <button
              type="button"
              @click="cargarSecuenciaItinerario"
              :disabled="cargandoItinerario"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/80 hover:bg-white text-[#1D1D1F] text-xs font-semibold rounded-[8px] border border-[#E5E5EA] shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            >
              <svg
                class="w-3.5 h-3.5"
                :class="{ 'animate-spin': cargandoItinerario }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              <span>Recargar</span>
            </button>

            <!-- Botón Guardar Secuencia -->
            <button
              type="button"
              @click="guardarSecuenciaManual"
              :disabled="guardandoItinerario || itinerarioClientes.length === 0"
              class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#34C759] hover:bg-[#30B753] text-white text-xs font-semibold rounded-[8px] shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            >
              <svg
                v-if="guardandoItinerario"
                class="w-3.5 h-3.5 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              <span>Guardar Orden</span>
            </button>
          </div>

          <!-- Indicador de Estado y Cobertura -->
          <div class="flex items-center gap-3">
            <span
              v-if="estadoGuardado"
              class="text-xs font-bold font-mono px-2 py-0.5 rounded-[6px]"
              :class="estadoGuardado.includes('✓') ? 'text-[#34C759] bg-[#34C759]/10' : 'text-[#0071E3] bg-[#0071E3]/10'"
            >
              {{ estadoGuardado }}
            </span>
            <div class="text-[10px] text-[#6E6E73] font-mono">
              GPS: <strong class="text-[#1D1D1F]">{{ totalClientesConCoords }}</strong> / {{ itinerarioClientes.length }}
            </div>
          </div>
        </div>

        <!-- Split Screen: 5 Cols Lista Secuencial + 7 Cols Mapa Interactivo -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
          <!-- Columna Izquierda (5 Cols): Lista de Clientes Ordenados 1..N -->
          <div class="lg:col-span-5 apple-glass-card rounded-[14px] shadow-float flex flex-col h-[680px] overflow-hidden border border-white/60">
            <div class="px-4 py-3 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex justify-between items-center">
              <div>
                <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">
                  Orden de Visita ({{ itinerarioClientes.length }})
                </span>
                <p class="text-[10px] text-[#6E6E73]">
                  Usa las flechas o arrastra para reordenar la secuencia
                </p>
              </div>
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-white border border-[#E5E5EA] text-[#0071E3]">
                {{ itinerarioRutaSeleccionada }} · {{ itinerarioDiaSeleccionado }}
              </span>
            </div>

            <div class="flex-1 p-3 overflow-y-auto custom-scroll space-y-2">
              <div v-if="cargandoItinerario" class="p-12 text-center text-[#86868B] font-medium text-xs">
                <svg class="w-5 h-5 animate-spin mx-auto text-[#0071E3] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Cargando itinerario y clientes...
              </div>

              <div v-else-if="itinerarioClientes.length === 0" class="p-12 text-center text-[#86868B] font-medium text-xs">
                No hay clientes planificados para la ruta {{ itinerarioRutaSeleccionada }} el día {{ itinerarioDiaSeleccionado }}.
              </div>

              <div
                v-else
                v-for="(c, idx) in itinerarioClientes"
                :key="c.cliente_id"
                draggable="true"
                @dragstart="onDragStart($event, idx)"
                @dragover.prevent
                @drop="onDrop($event, idx)"
                @click="centrarClienteEnMapa(c)"
                class="p-2.5 rounded-[10px] bg-white border border-[#E5E5EA] shadow-xs hover:border-[#0071E3] hover:shadow-md transition-all cursor-pointer flex items-center gap-2.5 group select-none"
              >
                <!-- Badge Numerador 1..N -->
                <div class="w-7 h-7 rounded-full bg-[#0071E3] text-white flex items-center justify-center text-xs font-bold font-mono shrink-0 shadow-xs">
                  {{ idx + 1 }}
                </div>

                <!-- Info Cliente -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-[#1D1D1F] truncate group-hover:text-[#0071E3] transition-colors">
                      {{ c.cliente }}
                    </span>
                    <span class="text-[9px] font-mono text-[#86868B] shrink-0">
                      #{{ c.cliente_id }}
                    </span>
                  </div>
                  <div class="text-[10px] text-[#6E6E73] truncate">
                    {{ c.direccion || 'Sin Dirección' }}
                  </div>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-[#F5F5F7] text-[#6E6E73] font-medium">
                      {{ c.tipo_negocio || 'Cliente' }}
                    </span>
                    <span
                      v-if="c.tiene_coords"
                      class="text-[9px] font-bold text-[#34C759] flex items-center gap-0.5"
                    >
                      <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      </svg>
                      GPS
                    </span>
                    <span v-else class="text-[9px] text-[#FF3B30] font-semibold">
                      Sin GPS
                    </span>
                  </div>
                </div>

                <!-- Controles para Mover Arriba / Abajo -->
                <div class="flex flex-col gap-1 shrink-0" @click.stop>
                  <button
                    type="button"
                    @click="moverCliente(idx, -1)"
                    :disabled="idx === 0"
                    class="w-5 h-5 rounded flex items-center justify-center bg-[#F5F5F7] hover:bg-[#E5E5EA] disabled:opacity-30 disabled:hover:bg-[#F5F5F7] text-[10px] font-bold transition-colors cursor-pointer"
                    title="Mover hacia arriba"
                  >
                    ▲
                  </button>
                  <button
                    type="button"
                    @click="moverCliente(idx, 1)"
                    :disabled="idx === itinerarioClientes.length - 1"
                    class="w-5 h-5 rounded flex items-center justify-center bg-[#F5F5F7] hover:bg-[#E5E5EA] disabled:opacity-30 disabled:hover:bg-[#F5F5F7] text-[10px] font-bold transition-colors cursor-pointer"
                    title="Mover hacia abajo"
                  >
                    ▼
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Columna Derecha (7 Cols): Mapa Leaflet con Trazo y Pines Numerados -->
          <div class="lg:col-span-7 apple-glass-card rounded-[14px] shadow-float flex flex-col h-[680px] overflow-hidden relative border border-white/60">
            <div class="px-4 py-3 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex justify-between items-center z-10">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
                <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">
                  Trazado Geográfico de la Ruta
                </span>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="reajustarMapa"
                  class="px-2.5 py-1 rounded-[6px] bg-white border border-[#E5E5EA] text-[10px] font-bold text-[#1D1D1F] hover:bg-slate-50 transition-colors shadow-xs cursor-pointer"
                >
                  Centrar Ruta
                </button>
                <span class="text-[10px] text-[#0071E3] font-bold font-mono">
                  Línea continua 1 ➔ N
                </span>
              </div>
            </div>

            <!-- Contenedor Mapa Leaflet -->
            <div id="mapaItinerarioContainer" class="w-full flex-1 bg-[#F5F5F7] relative z-0"></div>
          </div>
        </div>
      </template>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- MODAL: TIMELINE DETALLADO DE LA JORNADA                          -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div
        v-if="modalTimelineAbierto"
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/40 backdrop-blur-md p-4 animate-in fade-in"
      >
        <div class="apple-glass-card rounded-[18px] border border-white/80 shadow-2xl backdrop-blur-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
          <!-- Cabecera del Modal -->
          <div class="px-5 py-3.5 flex items-center justify-between border-b border-[#E5E5EA] bg-[#F5F5F7]/80 backdrop-blur-md">
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
                <h3 class="text-sm font-bold text-[#1D1D1F] uppercase tracking-wider">
                  Timeline de la Jornada: {{ timelineRutaActual }}
                </h3>
              </div>
              <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
                {{ timelineVendedorActual }} · Fecha: <strong class="font-mono text-[#1D1D1F]">{{ timelineFechaActual }}</strong>
              </p>
            </div>
            <button
              type="button"
              @click="cerrarTimelineModal"
              class="w-7 h-7 rounded-full bg-[#E5E5EA] hover:bg-[#D1D1D6] flex items-center justify-center text-[#1D1D1F] font-bold text-xs cursor-pointer transition-colors"
            >
              ✕
            </button>
          </div>

          <!-- Resumen de Horarios (Kpis Rápidos) -->
          <div v-if="timelineData && timelineData.resumen" class="px-5 py-2.5 bg-white border-b border-[#E5E5EA] grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
            <div class="p-2 rounded-[8px] bg-blue-50/60 border border-blue-100">
              <div class="text-[9px] font-bold text-[#0071E3] uppercase">Ingreso Web</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.ingreso_sistema || '-' }}
              </div>
            </div>
            <div class="p-2 rounded-[8px] bg-blue-50/60 border border-blue-100">
              <div class="text-[9px] font-bold text-[#0071E3] uppercase">Salida Web</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.salida_sistema || '-' }}
              </div>
            </div>
            <div class="p-2 rounded-[8px] bg-emerald-50/60 border border-emerald-100">
              <div class="text-[9px] font-bold text-[#34C759] uppercase">Ingreso App</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.ingreso_movil || '-' }}
              </div>
            </div>
            <div class="p-2 rounded-[8px] bg-emerald-50/60 border border-emerald-100">
              <div class="text-[9px] font-bold text-[#34C759] uppercase">Salida App</div>
              <div class="font-bold font-mono text-[#1D1D1F] text-xs mt-0.5">
                {{ timelineData.resumen.salida_movil || '-' }}
              </div>
            </div>
          </div>

          <!-- Contenido de Eventos Cronológicos -->
          <div class="p-5 overflow-y-auto custom-scroll space-y-3 flex-1 bg-[#F5F5F7]/40">
            <div v-if="cargandoTimeline" class="p-12 text-center text-[#86868B] text-xs">
              <svg class="w-5 h-5 animate-spin mx-auto text-[#0071E3] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              Cargando eventos de la jornada...
            </div>

            <div v-else-if="!timelineData || timelineData.timeline.length === 0" class="p-12 text-center text-[#86868B] text-xs">
              No se registraron eventos para esta ruta en la fecha indicada.
            </div>

            <div
              v-else
              v-for="ev in timelineData.timeline"
              :key="ev.id"
              class="p-3 rounded-[12px] bg-white border border-[#E5E5EA] shadow-xs flex items-start gap-3 hover:border-black/[0.12] transition-colors"
            >
              <!-- Hora y Origen -->
              <div class="flex flex-col items-center gap-1 shrink-0 w-16">
                <span class="text-xs font-bold font-mono text-[#1D1D1F]">
                  {{ ev.hora }}
                </span>
                <span
                  class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase"
                  :class="ev.origen === 'SISTEMA' ? 'bg-blue-100 text-[#0071E3]' : 'bg-emerald-100 text-[#34C759]'"
                >
                  {{ ev.origen === 'SISTEMA' ? 'Web' : 'Móvil' }}
                </span>
              </div>

              <!-- Detalle del Evento -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-bold text-[#1D1D1F] truncate">
                    {{ ev.cliente }}
                  </span>
                  <span
                    class="text-[10px] font-bold px-1.5 py-0.2 rounded"
                    :class="ev.tipo_evento === 'VENTA' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                  >
                    {{ ev.tipo_evento }}
                  </span>
                </div>

                <div v-if="ev.origen === 'SISTEMA'" class="text-[11px] text-[#6E6E73] mt-1 flex flex-wrap items-center gap-3">
                  <span v-if="ev.monto > 0" class="font-bold text-[#1D1D1F]">
                    Monto: Bs {{ Number(ev.monto).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                  </span>
                  <span>Tiempo PDV: <strong class="font-mono text-[#1D1D1F]">{{ ev.tiempo_pdv }}</strong></span>
                  <span v-if="ev.check_out !== '-'">Salida: <strong class="font-mono text-[#1D1D1F]">{{ ev.check_out }}</strong></span>
                  <span v-if="ev.motivo" class="text-slate-500 italic">Motivo: {{ ev.motivo }}</span>
                </div>

                <div v-else class="text-[11px] text-[#6E6E73] mt-1 flex flex-wrap items-center gap-3">
                  <span v-if="ev.motivo" class="italic text-slate-600">"{{ ev.motivo }}"</span>
                  <a
                    v-if="ev.latitud && ev.longitud"
                    :href="`https://www.google.com/maps?q=${ev.latitud},${ev.longitud}`"
                    target="_blank"
                    class="text-[10px] font-bold text-[#0071E3] hover:underline flex items-center gap-0.5"
                  >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    </svg>
                    Ver en Google Maps
                  </a>
                </div>
              </div>

              <!-- Foto Miniatura si existe -->
              <div v-if="ev.foto_url" class="shrink-0">
                <a :href="ev.foto_url" target="_blank" title="Ver foto a tamaño completo">
                  <img
                    :src="ev.foto_url"
                    alt="Evidencia"
                    class="w-12 h-12 object-cover rounded-[8px] border border-[#E5E5EA] shadow-2xs hover:scale-105 transition-transform"
                  />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// ── Props de Entrada desde Inertia ──────────────────────────────────────────
const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_inicio_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
})

// ── Estado Global del Módulo ────────────────────────────────────────────────
const submodo = ref('horarios') // 'horarios' | 'itinerario'

// ── SUBMODO 1: MATRIZ DE HORARIOS ───────────────────────────────────────────
const filtroFechaInicio = ref(props.fecha_inicio_default || '')
const filtroFechaFin = ref(props.fecha_corte_default || '')
const filtroCanal = ref('TODOS')
const filtroBusqueda = ref('')

const cargandoMatriz = ref(false)
const matrizData = ref([])
const fechasRango = ref([])
const listaCanales = ref(props.canales || [])
const totalRutas = ref(0)

const rutasFiltradas = computed(() => {
  let res = matrizData.value

  // Filtro de canal
  if (filtroCanal.value !== 'TODOS') {
    res = res.filter(r => r.canal === filtroCanal.value)
  }

  // Filtro de búsqueda
  if (filtroBusqueda.value.trim() !== '') {
    const q = filtroBusqueda.value.toLowerCase().trim()
    res = res.filter(r =>
      r.ruta.toLowerCase().includes(q) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(q))
    )
  }

  return res
})

const totalRutasActivas = computed(() => {
  return matrizData.value.filter(r => {
    if (!r.dias) return false
    return Object.values(r.dias).some(d => d.tiene_actividad)
  }).length
})

async function consultarMatriz() {
  if (cargandoMatriz.value) return
  cargandoMatriz.value = true

  try {
    const res = await axios.post(route('supervisor.avance.horarios.matriz'), {
      fecha_inicio: filtroFechaInicio.value,
      fecha_fin: filtroFechaFin.value,
      canal: filtroCanal.value,
    })

    matrizData.value = res.data.matriz || []
    fechasRango.value = res.data.fechas || []
    totalRutas.value = res.data.total_rutas || 0

    if (res.data.canales && res.data.canales.length > 0) {
      listaCanales.value = res.data.canales.filter(c => c !== 'TODOS')
    }
  } catch (error) {
    console.error('Error al consultar matriz de horarios:', error)
  } finally {
    cargandoMatriz.value = false
  }
}

// ── MODAL TIMELINE DE LA JORNADA ───────────────────────────────────────────
const modalTimelineAbierto = ref(false)
const cargandoTimeline = ref(false)
const timelineData = ref(null)
const timelineRutaActual = ref('')
const timelineFechaActual = ref('')
const timelineVendedorActual = ref('')

async function abrirTimelineModal(ruta, fecha, vendedor) {
  timelineRutaActual.value = ruta
  timelineFechaActual.value = fecha
  timelineVendedorActual.value = vendedor || 'No Asignado'
  modalTimelineAbierto.value = true
  cargandoTimeline.value = true
  timelineData.value = null

  try {
    const res = await axios.post(route('supervisor.avance.horarios.timeline'), {
      ruta: ruta,
      fecha: fecha,
    })
    timelineData.value = res.data
  } catch (error) {
    console.error('Error al cargar timeline de horarios:', error)
  } finally {
    cargandoTimeline.value = false
  }
}

function cerrarTimelineModal() {
  modalTimelineAbierto.value = false
  timelineData.value = null
}

// ── SUBMODO 2: SECUENCIA DE ITINERARIO & MAPA ───────────────────────────────
const diasSemanaLista = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado']
const itinerarioRutaSeleccionada = ref(props.catalogo_rutas[0] || 'TDB 01')
const itinerarioDiaSeleccionado = ref('lunes')

const cargandoItinerario = ref(false)
const guardandoItinerario = ref(false)
const estadoGuardado = ref('')
const itinerarioClientes = ref([])
const totalClientesConCoords = ref(0)

let mapaInstancia = null
let capaMarcadores = null
let capaPolilinea = null
let timerAutoguardado = null

async function cargarSecuenciaItinerario() {
  if (cargandoItinerario.value) return
  cargandoItinerario.value = true
  estadoGuardado.value = ''

  try {
    const res = await axios.post(route('supervisor.avance.itinerario.secuencia'), {
      ruta: itinerarioRutaSeleccionada.value,
      dia: itinerarioDiaSeleccionado.value,
    })

    itinerarioClientes.value = res.data.clientes || []
    totalClientesConCoords.value = res.data.total_con_coordenadas || 0

    await nextTick()
    renderizarMapaItinerario()
  } catch (error) {
    console.error('Error al cargar secuencia de itinerario:', error)
  } finally {
    cargandoItinerario.value = false
  }
}

function moverCliente(indice, delta) {
  const nuevoIndice = indice + delta
  if (nuevoIndice < 0 || nuevoIndice >= itinerarioClientes.value.length) return

  const item = itinerarioClientes.value.splice(indice, 1)[0]
  itinerarioClientes.value.splice(nuevoIndice, 0, item)

  // Actualizar orden correlativo
  itinerarioClientes.value.forEach((c, idx) => {
    c.orden = idx + 1
  })

  renderizarMapaItinerario()
  programarAutoguardado()
}

// Drag & Drop HTML5
let dragSourceIndex = null

function onDragStart(event, idx) {
  dragSourceIndex = idx
  event.dataTransfer.effectAllowed = 'move'
}

function onDrop(event, targetIdx) {
  if (dragSourceIndex === null || dragSourceIndex === targetIdx) return

  const item = itinerarioClientes.value.splice(dragSourceIndex, 1)[0]
  itinerarioClientes.value.splice(targetIdx, 0, item)

  itinerarioClientes.value.forEach((c, idx) => {
    c.orden = idx + 1
  })

  dragSourceIndex = null
  renderizarMapaItinerario()
  programarAutoguardado()
}

function programarAutoguardado() {
  estadoGuardado.value = 'Guardando cambios...'
  if (timerAutoguardado) clearTimeout(timerAutoguardado)

  timerAutoguardado = setTimeout(() => {
    guardarSecuenciaManual(true)
  }, 1200)
}

async function guardarSecuenciaManual(esAutomatico = false) {
  if (guardandoItinerario.value) return
  guardandoItinerario.value = true

  const secuenciaPayload = itinerarioClientes.value.map(c => ({
    cliente_id: c.cliente_id,
    orden: c.orden,
  }))

  try {
    await axios.post(route('supervisor.avance.itinerario.guardar'), {
      ruta: itinerarioRutaSeleccionada.value,
      dia: itinerarioDiaSeleccionado.value,
      secuencia: secuenciaPayload,
    })

    estadoGuardado.value = 'Guardado ✓'
    setTimeout(() => {
      if (estadoGuardado.value === 'Guardado ✓') {
        estadoGuardado.value = ''
      }
    }, 3000)
  } catch (error) {
    console.error('Error al guardar secuencia:', error)
    estadoGuardado.value = 'Error al guardar'
  } finally {
    guardandoItinerario.value = false
  }
}

// ── LEAFLET MAPA FUNCTIONS ──────────────────────────────────────────────────
function inicializarMapa() {
  const container = document.getElementById('mapaItinerarioContainer')
  if (!container || mapaInstancia) return

  mapaInstancia = L.map(container, {
    zoomControl: false,
    attributionControl: false,
  }).setView([-17.7833, -63.1821], 13) // Santa Cruz de la Sierra por defecto

  L.control.zoom({ position: 'topright' }).addTo(mapaInstancia)

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    subdomains: 'abcd',
  }).addTo(mapaInstancia)

  capaMarcadores = L.layerGroup().addTo(mapaInstancia)
  capaPolilinea = L.layerGroup().addTo(mapaInstancia)
}

function renderizarMapaItinerario() {
  if (!mapaInstancia) {
    inicializarMapa()
  }
  if (!mapaInstancia || !capaMarcadores || !capaPolilinea) return

  capaMarcadores.clearLayers()
  capaPolilinea.clearLayers()

  const coords = []
  const bounds = L.latLngBounds([])

  itinerarioClientes.value.forEach((c) => {
    if (c.latitud && c.longitud && c.latitud !== 0 && c.longitud !== 0) {
      const latlng = [c.latitud, c.longitud]
      coords.push(latlng)
      bounds.extend(latlng)

      // Marcador numerado estilo Apple Glass
      const pinIcon = L.divIcon({
        className: 'custom-sequence-pin',
        html: `
          <div style="background-color: #0071E3; color: white; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; border: 2.5px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-family: ui-monospace, monospace;">
            ${c.orden}
          </div>
        `,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
        popupAnchor: [0, -13],
      })

      const popupHtml = `
        <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; min-width: 180px; padding: 2px;">
          <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 4px;">
            <span style="background: #0071E3; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 10px;">
              # ${c.orden}
            </span>
            <span style="font-weight: bold; color: #6E6E73; font-size: 9px; font-family: ui-monospace, monospace;">
              ID: ${c.cliente_id}
            </span>
          </div>
          <div style="font-weight: 800; color: #1D1D1F; font-size: 12px; margin-bottom: 2px;">
            ${c.cliente}
          </div>
          <div style="color: #6E6E73; font-size: 10px;">
            ${c.direccion || 'Sin Dirección'}
          </div>
          <div style="margin-top: 4px; font-size: 9px; color: #86868B;">
            Tipo: <b>${c.tipo_negocio || 'Cliente'}</b>
          </div>
        </div>
      `

      const marker = L.marker(latlng, { icon: pinIcon })
      marker.bindPopup(popupHtml)
      c._marker = marker
      capaMarcadores.addLayer(marker)
    }
  })

  // Trazar polilínea continua que conecta los puntos en secuencia 1..N
  if (coords.length > 1) {
    const polyline = L.polyline(coords, {
      color: '#0071E3',
      weight: 3.5,
      opacity: 0.85,
      dashArray: '6, 6',
      lineJoin: 'round',
    })
    capaPolilinea.addLayer(polyline)
  }

  // Ajustar vista del mapa para encuadrar todos los puntos
  if (bounds.isValid()) {
    mapaInstancia.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
  }
}

function centrarClienteEnMapa(cliente) {
  if (!cliente.latitud || !cliente.longitud || !mapaInstancia) return

  mapaInstancia.flyTo([cliente.latitud, cliente.longitud], 16, {
    animate: true,
    duration: 0.8,
  })

  if (cliente._marker) {
    cliente._marker.openPopup()
  }
}

function reajustarMapa() {
  if (!mapaInstancia) return
  const bounds = L.latLngBounds([])
  itinerarioClientes.value.forEach(c => {
    if (c.latitud && c.longitud && c.latitud !== 0) {
      bounds.extend([c.latitud, c.longitud])
    }
  })
  if (bounds.isValid()) {
    mapaInstancia.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
  }
}

// ── Control de Submodo ──────────────────────────────────────────────────────
function cambiarSubmodo(modo) {
  submodo.value = modo
  if (modo === 'itinerario') {
    nextTick(() => {
      if (!mapaInstancia) {
        inicializarMapa()
      }
      if (itinerarioClientes.value.length === 0) {
        cargarSecuenciaItinerario()
      } else {
        setTimeout(() => {
          if (mapaInstancia) {
            mapaInstancia.invalidateSize()
            renderizarMapaItinerario()
          }
        }, 150)
      }
    })
  }
}

// ── Helpers de Formato ──────────────────────────────────────────────────────
function formatoHoraCorta(h) {
  if (!h || h === '-' || h === '00:00:00') return '-'
  return h.substring(0, 5) // "08:30"
}

function formatoFechaCorta(f) {
  if (!f) return ''
  const parts = f.split('-')
  if (parts.length < 3) return f
  return `${parts[2]}/${parts[1]}`
}

function getDiaNombreCorto(f) {
  if (!f) return ''
  const d = new Date(f + 'T00:00:00')
  const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
  return dias[d.getDay()] || ''
}

// ── Exportar CSV ────────────────────────────────────────────────────────────
function exportarMatrizCsv() {
  if (rutasFiltradas.value.length === 0) return

  const headers = ['Ruta', 'Preventista', 'Canal']
  fechasRango.value.forEach(f => {
    headers.push(`${f} (Web In)`, `${f} (Web Out)`, `${f} (Mov In)`, `${f} (Mov Out)`)
  })

  const rows = [headers]

  rutasFiltradas.value.forEach(r => {
    const row = [r.ruta, r.vendedor, r.canal]
    fechasRango.value.forEach(f => {
      const d = r.dias ? r.dias[f] : null
      row.push(
        d ? d.ingreso_sistema : '-',
        d ? d.salida_sistema : '-',
        d ? d.ingreso_movil : '-',
        d ? d.salida_movil : '-'
      )
    })
    rows.push(row)
  })

  const csvContent = '\uFEFF' + rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `horarios_matriz_${filtroFechaInicio.value}_${filtroFechaFin.value}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// ── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(() => {
  consultarMatriz()
})

onBeforeUnmount(() => {
  if (timerAutoguardado) clearTimeout(timerAutoguardado)
  if (mapaInstancia) {
    mapaInstancia.remove()
    mapaInstancia = null
  }
})
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.12);
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.25);
}
</style>

