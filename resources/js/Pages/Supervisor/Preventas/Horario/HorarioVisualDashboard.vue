<template>
  <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7] overflow-hidden select-none font-sans">
    
    <!-- Barra de Filtros y Alternador de Vistas (Gantt vs Histórico) -->
    <header class="p-2 sm:p-3 pb-2 shrink-0">
      <div class="flex flex-wrap items-center justify-between gap-2">
        
        <!-- Toggle Modo de Vista -->
        <div class="inline-flex p-0.5 rounded-[10px] bg-black/[0.05] border border-black/[0.04] text-[11px]">
          <button
            type="button"
            @click="modoVista = 'gantt'"
            class="px-3 py-1 rounded-[8px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
            :class="modoVista === 'gantt' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            <span>Comparativo Multiruta (Gantt)</span>
          </button>

          <button
            type="button"
            @click="modoVista = 'detalle'"
            class="px-3 py-1 rounded-[8px] transition-all cursor-pointer font-bold flex items-center gap-1.5"
            :class="modoVista === 'detalle' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Histórico de Visitas (Paso a Paso)</span>
          </button>
        </div>

        <!-- Filtro Rápido de Puntualidad y Doble Check -->
        <div class="flex items-center gap-1.5 text-[11px]">
          <span class="text-[#86868B] text-[10px] font-bold uppercase">Filtrar:</span>
          <button
            type="button"
            @click="filtroPuntualidad = 'todas'"
            class="px-2 py-0.5 rounded-md font-semibold transition-all cursor-pointer"
            :class="filtroPuntualidad === 'todas' ? 'bg-[#1D1D1F] text-white' : 'bg-white text-[#6E6E73] border border-black/[0.06] hover:bg-black/[0.03]'"
          >
            Todas ({{ rutas.length }})
          </button>
          <button
            type="button"
            @click="filtroPuntualidad = 'puntuales'"
            class="px-2 py-0.5 rounded-md font-semibold transition-all cursor-pointer flex items-center gap-1"
            :class="filtroPuntualidad === 'puntuales' ? 'bg-[#34C759] text-white' : 'bg-white text-[#1E6B37] border border-[#D1F2D9] hover:bg-emerald-50'"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-[#34C759]" :class="filtroPuntualidad === 'puntuales' ? 'bg-white' : ''"></span>
            <span>Puntuales ({{ totales.rutas_puntuales }})</span>
          </button>
          <button
            type="button"
            @click="filtroPuntualidad = 'retraso'"
            class="px-2 py-0.5 rounded-md font-semibold transition-all cursor-pointer flex items-center gap-1"
            :class="filtroPuntualidad === 'retraso' ? 'bg-[#FF3B30] text-white' : 'bg-white text-[#C9342C] border border-[#FADCD5] hover:bg-red-50'"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-[#FF3B30]" :class="filtroPuntualidad === 'retraso' ? 'bg-white' : ''"></span>
            <span>Con Retraso ({{ totales.rutas_retraso }})</span>
          </button>

          <!-- Pastilla Informativa de Doble Check -->
          <span
            v-if="totales.auditados_ambos > 0 || totales.salvados_por_app > 0"
            class="hidden md:inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 border border-sky-200 text-sky-800 text-[10px] font-bold"
            title="Doble check auditado entre fact_visitas (Web ERP) y visitas/user_workdays (App Móvil en BD alva)"
          >
            <span>📱+💻 Doble Check Activo</span>
            <span v-if="totales.salvados_por_app > 0" class="text-emerald-700">
              ({{ totales.salvados_por_app }} validadas por App)
            </span>
          </span>
        </div>

      </div>
    </header>

    <!-- 2. Contenido Principal -->
    <main class="flex-1 flex flex-col min-h-0 relative overflow-hidden p-2 sm:p-3 pt-0">

      <!-- Indicador de Carga -->
      <div v-if="cargando" class="flex-1 flex items-center justify-center p-8">
        <div class="flex items-center gap-2.5 text-xs text-[#6E6E73] font-bold">
          <span class="w-4 h-4 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></span>
          <span>Auditando doble check (Web ERP vs App Móvil alva) y tiempos...</span>
        </div>
      </div>

      <!-- Estado Vacío -->
      <div v-else-if="rutasFiltradas.length === 0" class="flex-1 flex items-center justify-center p-8 text-center">
        <div class="apple-glass-card max-w-sm p-6 rounded-2xl space-y-2 bg-white border border-black/[0.06]">
          <p class="text-sm font-bold text-[#1D1D1F]">Sin datos de horario para los filtros seleccionados</p>
          <p class="text-xs text-[#6E6E73]">Verifica la fecha seleccionada o desmarca filtros de canal/ruta.</p>
        </div>
      </div>

      <!-- VISTA 1: GANTT COMPARATIVO MULTIRUTA -->
      <div
        v-else-if="modoVista === 'gantt'"
        class="flex-1 flex flex-col min-h-0 bg-white rounded-[16px] border border-black/[0.06] shadow-sm overflow-hidden"
      >
        <!-- Cabecera de la Escala de Tiempo (07:00 a 16:00) -->
        <div class="p-2.5 border-b border-black/[0.06] bg-[#FAFBFD] flex items-center text-[10px] font-bold text-[#86868B] shrink-0">
          <div class="w-56 sm:w-64 md:w-72 shrink-0 px-2 uppercase flex items-center justify-between">
            <span>Ruta / Vendedor</span>
            <span class="text-[9px] text-[#86868B] font-mono lowercase">doble check</span>
          </div>
          <div class="flex-1 relative flex justify-between px-2 font-mono">
            <span>07:00</span>
            <span>08:00</span>
            <span class="text-[#FF3B30] font-extrabold flex items-center gap-0.5" title="Hora Límite 08:10 AM">
              <span>08:10</span>
              <span class="w-1.5 h-1.5 rounded-full bg-[#FF3B30]"></span>
            </span>
            <span>09:00</span>
            <span>10:00</span>
            <span>11:00</span>
            <span>12:00</span>
            <span>13:00</span>
            <span>14:00</span>
            <span>15:00</span>
            <span>16:00</span>
          </div>
        </div>

        <!-- Lista de Rutas con sus Barras de Jornada -->
        <div class="flex-1 overflow-y-auto custom-scroll divide-y divide-black/[0.04]">
          <div
            v-for="r in rutasFiltradas"
            :key="r.ruta"
            @click="verDetalleRuta(r)"
            class="p-2.5 flex items-center hover:bg-blue-50/30 transition-colors cursor-pointer group"
          >
            <!-- Columna Fija: Información de Ruta y Doble Check -->
            <div class="w-56 sm:w-64 md:w-72 shrink-0 pr-3 space-y-1">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5 truncate">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-2xs" :style="{ backgroundColor: r.color }"></span>
                  <strong class="text-xs text-[#1D1D1F] font-extrabold group-hover:text-[#0071E3] transition-colors">
                    {{ r.ruta }}
                  </strong>
                  <span class="text-[10px] text-[#86868B]">({{ r.canal }})</span>
                </div>
                
                <!-- Badge de Puntualidad Oficial -->
                <span
                  class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md font-mono shrink-0"
                  :class="r.cumple ? 'bg-[#EBF9EF] text-[#1E6B37]' : (r.primer_contacto ? 'bg-[#FDF0EF] text-[#C9342C]' : 'bg-gray-100 text-gray-500')"
                >
                  {{ r.cumple ? 'PUNTUAL' : (r.primer_contacto ? `+${r.desviacion_min}m` : 'SIN INICIO') }}
                </span>
              </div>

              <!-- Vendedor y Rango Horario -->
              <div class="text-[10.5px] text-[#6E6E73] truncate flex items-center justify-between">
                <span class="truncate">{{ r.vendedor }}</span>
                <span class="font-mono text-[9.5px] font-bold text-[#1D1D1F] shrink-0 ml-1">
                  {{ r.primer_contacto || '--:--' }} &rarr; {{ r.ultimo_contacto || '--:--' }}
                </span>
              </div>

              <!-- Pastillas de DOBLE CHECK (Web ERP vs App Móvil) -->
              <div class="flex items-center gap-1.5 text-[9px] font-mono">
                <!-- Origen AMBOS -->
                <template v-if="r.doble_check?.origen === 'AMBOS'">
                  <span
                    class="px-1.5 py-0.2 rounded font-bold flex items-center gap-1"
                    :class="r.doble_check.salvado_por_app ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-slate-100 text-slate-700'"
                    :title="`Web: ${r.doble_check.hora_web} | App: ${r.doble_check.hora_app} (Diferencia: ${r.doble_check.diferencia_min}m)`"
                  >
                    <span>📱 App {{ r.doble_check.hora_app }}</span>
                    <span>&bull;</span>
                    <span>💻 Web {{ r.doble_check.hora_web }}</span>
                  </span>
                  <span v-if="r.doble_check.salvado_por_app" class="text-[8.5px] font-extrabold text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded">
                    Salvado por App
                  </span>
                  <span v-else-if="r.doble_check.salvado_por_web" class="text-[8.5px] font-extrabold text-blue-700 bg-blue-50 px-1 py-0.2 rounded">
                    Validado Web
                  </span>
                </template>

                <!-- Origen SOLO APP -->
                <template v-else-if="r.doble_check?.origen === 'SOLO_APP'">
                  <span class="px-1.5 py-0.2 rounded bg-sky-50 text-sky-800 font-bold border border-sky-200" title="Marcaje registrado exclusivamente en la App Móvil (alva)">
                    📱 Solo App {{ r.doble_check.hora_app }}
                  </span>
                </template>

                <!-- Origen SOLO WEB -->
                <template v-else-if="r.doble_check?.origen === 'SOLO_WEB'">
                  <span class="px-1.5 py-0.2 rounded bg-purple-50 text-purple-800 font-bold border border-purple-200" title="Marcaje registrado exclusivamente en fact_visitas (Web ERP)">
                    💻 Solo Web {{ r.doble_check.hora_web }}
                  </span>
                </template>

                <!-- Sin Marcación -->
                <template v-else>
                  <span class="px-1.5 py-0.2 rounded bg-gray-100 text-gray-500 font-semibold">
                    Sin Marcación
                  </span>
                </template>
              </div>

              <!-- Mini Métricas de Paradas -->
              <div class="flex items-center gap-2 text-[9px] text-[#86868B] font-mono">
                <span>{{ r.total_paradas }} paradas</span>
                <span>&bull;</span>
                <span>{{ r.duracion_jornada_fmt }}</span>
                <span>&bull;</span>
                <span :class="r.pausas_largas > 0 ? 'text-amber-600 font-bold' : ''">
                  {{ r.pausas_largas }} pausas &gt;30m
                </span>
              </div>
            </div>

            <!-- Gráfico Horizontal de Tiempo (Gantt) con Líneas Verticales Guía -->
            <div class="flex-1 relative h-7 bg-[#F5F5F7] rounded-lg overflow-hidden border border-black/[0.04]">
              
              <!-- Líneas Guía Verticales para cada hora (08:00, 09:00, etc.) -->
              <div
                v-for="horaGuia in horasGuiaGantt"
                :key="horaGuia"
                class="absolute top-0 bottom-0 w-px bg-black/[0.06] pointer-events-none z-0"
                :style="{ left: `${porcentajeTiempo(horaGuia)}%` }"
              ></div>

              <!-- Línea Roja de Corte a las 08:10 -->
              <div
                class="absolute top-0 bottom-0 w-0.5 bg-[#FF3B30] z-20 shadow-[0_0_4px_rgba(255,59,48,0.5)]"
                :style="{ left: `${porcentajeTiempo('08:10:00')}%` }"
                title="Límite: 08:10 AM"
              ></div>

              <!-- Franja de Jornada Activa (desde primer contacto hasta último contacto) -->
              <div
                v-if="r.primer_contacto_completo && r.ultimo_contacto"
                class="absolute top-1 bottom-1 rounded-md opacity-25 z-0"
                :style="{
                  backgroundColor: r.color,
                  left: `${porcentajeTiempo(r.primer_contacto_completo)}%`,
                  width: `${Math.max(1, porcentajeTiempo(r.ultimo_contacto + ':00') - porcentajeTiempo(r.primer_contacto_completo))}%`
                }"
              ></div>

              <!-- Puntos de cada Visita a lo largo de la Jornada -->
              <div
                v-for="p in r.paradas"
                :key="p.parada_num"
                class="absolute top-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full border border-white shadow-2xs z-10 transition-transform hover:scale-150 cursor-pointer"
                :style="{
                  left: `${porcentajeTiempo(p.hora_in)}%`,
                  backgroundColor: p.monto > 0 ? '#34C759' : (p.en_plan ? '#0071E3' : '#FF9500')
                }"
                :title="`#${p.parada_num} · ${p.hora_in} - ${p.cliente} (${p.tipo}) · Origen: ${p.origen}`"
              ></div>

              <!-- Marcador de Inicio de Jornada -->
              <div
                v-if="r.primer_contacto_completo"
                class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 z-30"
                :style="{ left: `${porcentajeTiempo(r.primer_contacto_completo)}%` }"
              >
                <span
                  class="block w-3.5 h-3.5 rounded-full border-2 border-white shadow-sm flex items-center justify-center text-[7px] text-white font-black"
                  :class="r.cumple ? 'bg-[#34C759]' : 'bg-[#FF3B30]'"
                  :title="`Inicio Oficial: ${r.primer_contacto} (${r.doble_check?.auditoria_label || (r.cumple ? 'Puntual' : 'Retraso')})`"
                >
                  &#9679;
                </span>
              </div>

            </div>

            <!-- Botón Ver Detalle -->
            <div class="pl-3 shrink-0">
              <button
                type="button"
                class="px-2 py-1 rounded-md text-[10px] font-bold text-[#0071E3] bg-blue-50 hover:bg-[#0071E3] hover:text-white transition-all cursor-pointer opacity-75 group-hover:opacity-100"
              >
                Ver Paradas &rarr;
              </button>
            </div>

          </div>
        </div>
      </div>

      <!-- VISTA 2: HISTÓRICO DETALLADO DE VISITAS (TIMELINE PASO A PASO) -->
      <div
        v-else-if="modoVista === 'detalle'"
        class="flex-1 flex flex-col min-h-0 bg-white rounded-[16px] border border-black/[0.06] shadow-sm overflow-hidden"
      >
        <!-- Barra de Control de Ruta Seleccionada -->
        <div class="p-3 border-b border-black/[0.06] bg-[#FAFBFD] flex flex-wrap items-center justify-between gap-2 shrink-0">
          
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="modoVista = 'gantt'"
              class="px-2.5 py-1 rounded-lg text-xs font-bold text-[#6E6E73] hover:text-[#1D1D1F] bg-white border border-black/[0.08] hover:bg-black/[0.02] transition-colors cursor-pointer flex items-center gap-1 shadow-2xs"
            >
              &larr; Volver al Comparativo
            </button>

            <!-- Selector de Ruta para ver detalle -->
            <div class="flex items-center gap-2">
              <span class="text-xs text-[#86868B] font-semibold">Ruta:</span>
              <select
                v-model="rutaSeleccionadaDetalle"
                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white border border-black/[0.08] text-[#1D1D1F] focus:outline-none focus:ring-1 focus:ring-[#0071E3] cursor-pointer"
              >
                <option v-for="r in rutasFiltradas" :key="r.ruta" :value="r.ruta">
                  {{ r.ruta }} - {{ r.vendedor }} ({{ r.total_paradas }} paradas)
                </option>
              </select>
            </div>
          </div>

          <!-- Resumen Rápido de la Ruta Seleccionada -->
          <div v-if="rutaActual" class="flex items-center gap-3 text-xs">
            <div class="flex items-center gap-1">
              <span class="text-[#86868B]">Inicio Oficial:</span>
              <span class="font-extrabold font-mono text-[#1D1D1F]">{{ rutaActual.primer_contacto || 'Sin inicio' }}</span>
              <span
                class="text-[9.5px] font-bold px-1.5 py-0.2 rounded font-mono ml-1"
                :class="rutaActual.cumple ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-[#FDF0EF] text-[#C9342C]'"
              >
                {{ rutaActual.cumple ? `Puntual (-${Math.abs(rutaActual.desviacion_min)}m)` : `Retraso (+${rutaActual.desviacion_min}m)` }}
              </span>
            </div>
            <div class="h-3.5 w-px bg-black/[0.08]"></div>
            <div class="flex items-center gap-1 text-[#6E6E73]">
              <span>Duración:</span>
              <strong class="font-mono text-[#1D1D1F]">{{ rutaActual.duracion_jornada_fmt }}</strong>
            </div>
            <div class="h-3.5 w-px bg-black/[0.08]"></div>
            <div class="flex items-center gap-1 text-[#6E6E73]">
              <span>Puntos:</span>
              <strong class="font-mono text-[#1E6B37]">{{ rutaActual.puntos }} / 10</strong>
            </div>
          </div>

        </div>

        <!-- PANEL DE AUDITORÍA DOBLE CHECK PARA LA RUTA ACTUAL -->
        <div
          v-if="rutaActual && rutaActual.doble_check"
          class="px-4 py-2 bg-gradient-to-r from-blue-50/50 via-indigo-50/30 to-purple-50/40 border-b border-black/[0.05] flex flex-wrap items-center justify-between gap-3 text-xs"
        >
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3] animate-pulse"></span>
            <span class="font-extrabold text-[#1D1D1F]">Auditoría Doble Check:</span>
            <span class="px-2 py-0.5 rounded-md font-bold text-[10.5px]" :class="rutaActual.cumple ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-[#FDF0EF] text-[#C9342C]'">
              {{ rutaActual.doble_check.auditoria_label }}
            </span>
          </div>

          <div class="flex items-center gap-4 text-[11px] font-mono">
            <!-- Marcaje Web ERP -->
            <div class="flex items-center gap-1.5">
              <span class="text-[#86868B]">💻 Web ERP:</span>
              <strong class="text-[#1D1D1F]">{{ rutaActual.doble_check.hora_web || 'Sin registro' }}</strong>
            </div>

            <!-- Marcaje App Móvil -->
            <div class="flex items-center gap-1.5">
              <span class="text-[#86868B]">📱 App Móvil (alva):</span>
              <strong class="text-[#1D1D1F]">{{ rutaActual.doble_check.hora_app || 'Sin registro' }}</strong>
              <span v-if="rutaActual.doble_check.app_origen_detalle" class="text-[9px] text-[#86868B]">
                ({{ rutaActual.doble_check.app_origen_detalle }})
              </span>
            </div>

            <!-- Discrepancia entre sistemas -->
            <div v-if="rutaActual.doble_check.diferencia_min !== null" class="flex items-center gap-1">
              <span class="text-[#86868B]">Delta:</span>
              <span
                class="px-1.5 py-0.2 rounded font-bold text-[10px]"
                :class="Math.abs(rutaActual.doble_check.diferencia_min) > 10 ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-700'"
              >
                {{ Math.abs(rutaActual.doble_check.diferencia_min) }} min dif
              </span>
            </div>
          </div>
        </div>

        <!-- Flujo Cronológico de Paradas (Timeline Vertical) -->
        <div v-if="rutaActual && rutaActual.paradas.length > 0" class="flex-1 overflow-y-auto custom-scroll p-4 space-y-0">
          
          <!-- HITO DE INICIO DE JORNADA -->
          <div class="flex items-start gap-3 pb-3">
            <div class="w-8 flex flex-col items-center">
              <div class="w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-bold shadow-xs">
                🏁
              </div>
              <div class="w-0.5 flex-1 bg-black/[0.08] min-h-[20px]"></div>
            </div>
            <div class="pt-0.5 space-y-1">
              <div class="text-[11px] font-extrabold text-[#1D1D1F] flex items-center gap-2">
                <span>INICIO DE JORNADA / PRIMER CONTACTO OFICIAL</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[9px] font-extrabold font-mono"
                  :class="rutaActual.cumple ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                >
                  {{ rutaActual.cumple ? 'A TIEMPO &le; 08:10 AM' : `TARDE (+${rutaActual.desviacion_min} min)` }}
                </span>
              </div>
              <div class="text-[10px] text-[#86868B] flex flex-wrap items-center gap-2">
                <span>Hora límite oficial: <strong class="font-mono text-[#0071E3]">{{ totales.hora_limite }} AM</strong></span>
                <span>&bull;</span>
                <span>Primer marcaje validado: <strong class="font-mono text-[#1D1D1F]">{{ rutaActual.primer_contacto_completo }}</strong></span>
                <span v-if="rutaActual.doble_check?.hora_workday_app">
                  &bull; Apertura en App: <strong class="font-mono text-sky-700">{{ rutaActual.doble_check.hora_workday_app }}</strong>
                </span>
              </div>
            </div>
          </div>

          <!-- LISTA DE PARADAS -->
          <div v-for="(p, idx) in rutaActual.paradas" :key="p.parada_num" class="relative">
            
            <!-- Conector de Traslado entre Paradas -->
            <div v-if="idx > 0" class="flex items-center gap-3 py-1">
              <div class="w-8 flex justify-center">
                <div class="w-0.5 h-7 bg-black/[0.08]"></div>
              </div>
              <div class="flex items-center gap-2">
                <span
                  class="px-2 py-0.5 rounded-md text-[9.5px] font-bold font-mono flex items-center gap-1"
                  :class="p.es_pausa_larga ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-gray-100 text-[#6E6E73]'"
                >
                  <span v-if="p.es_pausa_larga">⚠️ Pausa prolongada:</span>
                  <span v-else>🚗 Traslado:</span>
                  <strong>{{ p.traslado_prev_fmt }}</strong>
                </span>
                <span v-if="p.es_pausa_larga" class="text-[9px] text-amber-700 font-semibold">
                  (Inactividad mayor a 30 min)
                </span>
              </div>
            </div>

            <!-- Tarjeta de la Parada (Cliente) -->
            <div class="flex items-start gap-3">
              <!-- Círculo del número de parada -->
              <div class="w-8 flex flex-col items-center shrink-0">
                <div
                  class="w-6 h-6 rounded-full text-white flex items-center justify-center text-[10px] font-black shadow-xs shrink-0"
                  :class="p.monto > 0 ? 'bg-[#34C759]' : (p.en_plan ? 'bg-[#0071E3]' : 'bg-[#FF9500]')"
                >
                  {{ p.parada_num }}
                </div>
                <div v-if="idx < rutaActual.paradas.length - 1" class="w-0.5 flex-1 bg-black/[0.08] min-h-[14px]"></div>
              </div>

              <!-- Contenido de la Tarjeta -->
              <div class="flex-1 p-3 rounded-[12px] bg-[#FAFBFD] border border-black/[0.06] hover:bg-white transition-all space-y-1.5 shadow-2xs mb-1">
                
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <div class="flex items-center gap-2">
                      <h4 class="text-xs font-extrabold text-[#1D1D1F]">
                        {{ p.cliente }}
                      </h4>
                      <span v-if="p.cliente_id" class="text-[9px] font-mono text-[#86868B]">
                        #{{ p.cliente_id }}
                      </span>
                      <span
                        v-if="!p.en_plan"
                        class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-900"
                      >
                        Fuera de Plan
                      </span>

                      <!-- Badge de Origen Doble Check -->
                      <span
                        class="text-[9px] font-extrabold px-1.5 py-0.2 rounded font-mono"
                        :class="p.origen_key === 'AMBOS' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (p.origen_key === 'APP' ? 'bg-sky-50 text-sky-800 border border-sky-200' : 'bg-purple-50 text-purple-800 border border-purple-200')"
                      >
                        {{ p.origen }}
                      </span>
                    </div>
                    <p class="text-[10px] text-[#6E6E73]">
                      {{ p.direccion }} &bull; {{ p.territorio }}
                    </p>
                  </div>

                  <!-- Hora y Resultado -->
                  <div class="text-right shrink-0">
                    <div class="text-xs font-extrabold font-mono text-[#1D1D1F]">
                      {{ p.hora_in }}
                    </div>
                    <div class="mt-0.5">
                      <span
                        class="px-2 py-0.5 rounded text-[9.5px] font-extrabold"
                        :class="p.monto > 0 ? 'bg-[#EBF9EF] text-[#1E6B37]' : 'bg-[#EBF4FE] text-[#0071E3]'"
                      >
                        {{ p.monto > 0 ? `Bs ${p.monto.toFixed(2)}` : p.tipo }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Detalle de Tiempos, Doble Check y Foto -->
                <div class="pt-1 border-t border-black/[0.04] flex flex-wrap items-center justify-between gap-2 text-[10px] text-[#6E6E73]">
                  <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1">
                      <span>⏱️ Tiempo en PDV:</span>
                      <strong class="font-mono text-[#1D1D1F]">{{ p.tiempo_pdv_fmt }}</strong>
                    </span>
                    <span class="text-[#86868B]">&bull;</span>
                    
                    <!-- Desglose de horas individuales si existen ambas -->
                    <span v-if="p.hora_app && p.hora_web" class="text-[9.5px] font-mono text-[#86868B]">
                      App: <strong class="text-slate-800">{{ p.hora_app }}</strong> | Web: <strong class="text-slate-800">{{ p.hora_web }}</strong>
                    </span>
                    <span v-else class="text-[9.5px] text-[#86868B]">
                      Marcaje: {{ p.origen }}
                    </span>
                  </div>

                  <!-- Botón Ver Foto -->
                  <button
                    v-if="p.foto_url"
                    type="button"
                    @click="$emit('abrir-foto', p)"
                    class="px-2 py-0.5 rounded-md text-[10px] font-bold text-[#0071E3] hover:bg-[#0071E3] hover:text-white transition-colors cursor-pointer border border-[#0071E3]/20"
                  >
                    Ver Foto en Terreno
                  </button>
                </div>

              </div>
            </div>

          </div>

          <!-- HITO DE FIN DE JORNADA -->
          <div class="flex items-start gap-3 pt-2">
            <div class="w-8 flex flex-col items-center">
              <div class="w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center text-xs font-bold shadow-xs">
                🎯
              </div>
            </div>
            <div class="pt-0.5">
              <div class="text-[11px] font-extrabold text-[#1D1D1F]">
                CIERRE DE JORNADA / ÚLTIMO CLIENTE ({{ rutaActual.ultimo_contacto }})
              </div>
              <p class="text-[10px] text-[#86868B]">
                Total Paradas Atendidas: <strong class="font-mono text-[#1D1D1F]">{{ rutaActual.total_paradas }}</strong> &bull; Duración Activa: <strong class="font-mono text-[#1D1D1F]">{{ rutaActual.duracion_jornada_fmt }}</strong>
              </p>
            </div>
          </div>

        </div>

        <div v-else class="flex-1 flex items-center justify-center p-8 text-center text-xs text-[#86868B]">
          Esta ruta no registró visitas ni paradas en la fecha seleccionada.
        </div>

      </div>

    </main>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  fecha: {
    type: String,
    default: ''
  },
  canales: {
    type: Array,
    default: () => []
  },
  rutas: {
    type: Array,
    default: () => []
  }
})

defineEmits(['abrir-foto'])

const cargando = ref(false)
const modoVista = ref('gantt') // 'gantt' | 'detalle'
const filtroPuntualidad = ref('todas') // 'todas' | 'puntuales' | 'retraso'
const rutaSeleccionadaDetalle = ref(null)

const horasGuiaGantt = [
  '08:00:00',
  '09:00:00',
  '10:00:00',
  '11:00:00',
  '12:00:00',
  '13:00:00',
  '14:00:00',
  '15:00:00',
  '16:00:00',
]

const totales = ref({
  total_rutas: 0,
  rutas_puntuales: 0,
  rutas_retraso: 0,
  rutas_sin_inicio: 0,
  puntualidad_pct: 0,
  puntos_totales: 0,
  puntos_maximos: 0,
  tiempo_promedio_pdv_min: 0,
  tiempo_promedio_traslado_min: 0,
  total_paradas: 0,
  total_pausas_largas: 0,
  hora_limite: '08:10',
  auditados_ambos: 0,
  salvados_por_app: 0,
  salvados_por_web: 0,
})

const rutas = ref([])

// Filtro por estatus de puntualidad
const rutasFiltradas = computed(() => {
  let list = rutas.value || []
  if (filtroPuntualidad.value === 'puntuales') {
    list = list.filter(r => r.cumple)
  } else if (filtroPuntualidad.value === 'retraso') {
    list = list.filter(r => !r.cumple && r.primer_contacto)
  }
  return list
})

const rutaActual = computed(() => {
  if (!rutaSeleccionadaDetalle.value && rutasFiltradas.value.length > 0) {
    return rutasFiltradas.value[0]
  }
  return rutas.value.find(r => r.ruta === rutaSeleccionadaDetalle.value) || rutasFiltradas.value[0] || null
})

// Calcula la posición porcentual (0 a 100%) entre las 07:00:00 (0%) y las 16:00:00 (100%)
function porcentajeTiempo(horaStr) {
  if (!horaStr) return 0
  const partes = horaStr.split(':')
  const h = parseInt(partes[0] || '0', 10)
  const m = parseInt(partes[1] || '0', 10)
  const s = parseInt(partes[2] || '0', 10)
  const segundos = h * 3600 + m * 60 + s

  const segInicio = 7 * 3600 // 07:00:00
  const segFin = 16 * 3600    // 16:00:00
  const total = segFin - segInicio

  if (segundos <= segInicio) return 0
  if (segundos >= segFin) return 100
  return Math.min(100, Math.max(0, ((segundos - segInicio) / total) * 100))
}

function verDetalleRuta(r) {
  rutaSeleccionadaDetalle.value = r.ruta
  modoVista.value = 'detalle'
}

async function cargarDatosHorario() {
  cargando.value = true
  try {
    const res = await axios.post(route('supervisor.preventas.pizarra-visual.horario-data'), {
      fecha: props.fecha,
      canales: props.canales,
      rutas: props.rutas,
      hora_limite: '08:10:00',
    })

    const d = res.data || {}
    totales.value = d.totales || totales.value
    rutas.value = d.rutas || []

    if (!rutaSeleccionadaDetalle.value && rutas.value.length > 0) {
      rutaSeleccionadaDetalle.value = rutas.value[0].ruta
    }
  } catch (err) {
    console.error('[HorarioVisual] Error cargando datos de horario:', err)
  } finally {
    cargando.value = false
  }
}

watch(
  [() => props.fecha, () => props.canales, () => props.rutas],
  () => {
    cargarDatosHorario()
  },
  { deep: true }
)

onMounted(() => {
  cargarDatosHorario()
})
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.12);
  border-radius: 4px;
}
</style>
