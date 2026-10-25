<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900 font-sans text-slate-900">
      
      <!-- ==========================================
           1. BARRA SUPERIOR DE SUBMÓDULOS & FILTROS
           ========================================== -->
      <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 py-2 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Pestañas de Navegación del Módulo Preventas & Filtros -->
        <div class="flex items-center gap-2.5 overflow-x-auto no-scrollbar py-0.5">
          <SubNavTabs :tabs="preventasSubTabs" currentTab="cumplimiento" />

          <div class="h-5 w-px bg-slate-200 hidden sm:block shrink-0"></div>

          <!-- Selector de Fecha de Evaluación -->
          <div class="flex items-center bg-slate-50 p-1 rounded-lg border border-slate-200/90 shrink-0 h-8">
            <span class="text-[11px] text-slate-400 font-mono px-1.5 flex items-center gap-1">
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
              </svg>
              Fecha:
            </span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarCumplimiento"
              class="h-6 px-1.5 text-xs font-mono font-semibold bg-white text-slate-900 border border-slate-200 rounded-md focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Selector de Canal (Por defecto 'TDB') -->
          <div class="flex items-center bg-slate-50 px-2 rounded-lg border border-slate-200/90 shrink-0 h-8 text-xs">
            <span class="text-slate-400 mr-1.5 font-mono">Canal:</span>
            <select
              v-model="canalSeleccionado"
              @change="alCambiarCanal"
              class="bg-transparent font-bold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer text-slate-900"
            >
              <option value="TODOS">Todos los Canales</option>
              <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Selector de Ruta -->
          <div class="flex items-center bg-slate-50 px-2 rounded-lg border border-slate-200/90 shrink-0 h-8 text-xs">
            <span class="text-slate-400 mr-1.5 font-mono">Ruta:</span>
            <select
              v-model="rutaFiltroSeleccionada"
              @change="cargarCumplimiento"
              class="bg-transparent font-bold font-mono text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer text-slate-900 max-w-[190px]"
            >
              <option value="TODAS">Todas las Rutas</option>
              <option v-for="r in rutasDisponibles" :key="r.ruta" :value="r.ruta">
                {{ r.ruta }} - {{ r.vendedor }}
              </option>
            </select>
          </div>

          <!-- Switch Modo Estricto de Horario (Validar App + Web) -->
          <label
            class="flex items-center gap-2 h-8 px-2.5 rounded-lg border text-xs font-medium cursor-pointer transition-all shrink-0 select-none"
            :class="estrictoHorario ? 'bg-amber-500/10 border-amber-300 text-amber-900 font-semibold' : 'bg-slate-50 border-slate-200/90 text-slate-600 hover:bg-slate-100'"
            title="Exige obligatoriamente marcación antes de las 08:10:00 tanto en Web (ERP) como en App Móvil"
          >
            <input
              type="checkbox"
              v-model="estrictoHorario"
              @change="cargarCumplimiento"
              class="rounded border-slate-300 text-amber-600 focus:ring-0 w-3.5 h-3.5"
            />
            <span class="flex items-center gap-1">
              <span>Modo Estricto Horario</span>
              <span class="text-[9px] font-mono px-1 py-0.2 rounded bg-amber-200/60 text-amber-800">App+Web</span>
            </span>
          </label>
        </div>

        <!-- Botones de Acción y Refrescar -->
        <div class="flex items-center gap-2 shrink-0 justify-end">
          <span v-if="cargando" class="text-[11px] font-mono text-slate-500 flex items-center gap-1.5 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
            Auditando...
          </span>

          <button
            type="button"
            @click="cargarCumplimiento"
            class="h-8 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
            title="Recalcular métricas de cumplimiento"
          >
            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': cargando }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <span>Evaluar Día</span>
          </button>
        </div>
      </header>

      <!-- ==========================================
           2. SUB-BARRA DE NAVEGACIÓN: 9 PESTAÑAS MONOGRÁFICAS (SIN EMOJIS)
           ========================================== -->
      <div class="bg-white border-b border-slate-200/90 px-3 sm:px-4 shrink-0 flex items-center justify-between gap-2 overflow-x-auto no-scrollbar shadow-xs z-20">
        <div class="flex items-center space-x-1 py-1.5 shrink-0">
          <button
            v-for="t in pestañasMonograficas"
            :key="t.id"
            type="button"
            @click="tabActiva = t.id"
            class="px-2.5 sm:px-3 py-1.5 text-xs font-medium rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
            :class="tabActiva === t.id
              ? 'bg-slate-900 text-white font-semibold shadow-xs'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
          >
            <span>{{ t.nombre }}</span>
            <span
              v-if="t.puntos"
              class="text-[10px] font-mono px-1.5 py-0.2 rounded"
              :class="tabActiva === t.id ? 'bg-white/20 text-white font-bold' : 'bg-slate-100 text-slate-500 border border-slate-200'"
            >
              {{ t.puntos }}
            </span>
          </button>
        </div>

        <!-- Buscador rápido de texto en la pestaña -->
        <div class="relative w-40 sm:w-52 shrink-0 py-1">
          <input
            type="text"
            v-model="busquedaRuta"
            placeholder="Buscar ruta o preventista..."
            class="w-full h-7 pl-7 pr-6 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-slate-400 font-mono"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
          <button
            v-if="busquedaRuta"
            @click="busquedaRuta = ''"
            class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600 text-xs font-mono"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- ==========================================
           3. ÁREA DE CONTENIDO: LAS 9 PESTAÑAS (DIRECTAMENTE TABLAS Y MATRICES)
           ========================================== -->
      <div class="flex-1 min-h-0 overflow-y-auto bg-slate-100/70 p-3 sm:p-5">
        
        <!-- PESTAÑA 0: PIZARRA GENERAL & RANKING (DIRECTAMENTE TABLA CON SCORE TOTAL AL FINAL) -->
        <div v-if="tabActiva === 'general'" class="space-y-4">
          <!-- Tabla Leaderboard / Pizarra de Ranking -->
          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase tracking-wider">
                  <tr>
                    <th class="py-2.5 px-3 font-semibold text-center w-12">Rank</th>
                    <th class="py-2.5 px-3 font-semibold">Ruta / Preventista</th>
                    <th class="py-2.5 px-2.5 font-semibold text-center">Canal</th>
                    
                    <!-- Los 8 KPIs directamente nombrados sin K1, K2... -->
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'horario'" title="Ver auditoría Horario">
                      Horario<br><span class="text-[9px] text-slate-400 font-normal">10 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'visitas'" title="Ver auditoría Visitas">
                      Visitas<br><span class="text-[9px] text-slate-400 font-normal">5 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'objetivo'" title="Ver auditoría Cuota Diaria">
                      Cuota Diaria<br><span class="text-[9px] text-slate-400 font-normal">10 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'efectividad'" title="Ver auditoría Efectividad">
                      Efectividad<br><span class="text-[9px] text-slate-400 font-normal">30 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'compra_minima'" title="Ver auditoría Compra Mínima">
                      Mín. 70 Bs<br><span class="text-[9px] text-slate-400 font-normal">5 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'reposicion'" title="Ver auditoría Reposición">
                      Reposición<br><span class="text-[9px] text-slate-400 font-normal">15 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'ampliacion'" title="Ver auditoría Ampliación">
                      Ampliación<br><span class="text-[9px] text-slate-400 font-normal">20 pts</span>
                    </th>
                    <th class="py-2.5 px-2 font-semibold text-center cursor-pointer hover:bg-slate-100" @click="tabActiva = 'covers'" title="Ver auditoría Covers">
                      Covers<br><span class="text-[9px] text-slate-400 font-normal">5 pts</span>
                    </th>

                    <!-- Score Total AL FINAL -->
                    <th class="py-2.5 px-3 font-bold text-center bg-slate-100/90 text-slate-900 border-l border-slate-200">
                      Score Total<br><span class="text-[9px] text-slate-400 font-normal">100 pts</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                  <tr
                    v-for="r in rutasFiltradas"
                    :key="r.ruta"
                    class="hover:bg-slate-50/80 transition-colors"
                  >
                    <!-- Rank con Medalla -->
                    <td class="py-2 px-3 text-center">
                      <span
                        class="inline-flex items-center justify-center w-6 h-6 rounded-full font-bold text-xs"
                        :class="claseMedalla(r.rank)"
                      >
                        {{ r.rank }}
                      </span>
                    </td>

                    <!-- Ruta y Vendedor -->
                    <td class="py-2 px-3">
                      <div class="font-bold text-slate-900 font-sans flex items-center gap-1.5">
                        <span class="font-mono text-sky-700">{{ r.ruta }}</span>
                        <span class="text-slate-400 text-xs">•</span>
                        <span class="truncate max-w-[170px] sm:max-w-none">{{ r.vendedor }}</span>
                      </div>
                    </td>

                    <!-- Canal -->
                    <td class="py-2 px-2.5 text-center">
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        {{ r.canal }}
                      </span>
                    </td>

                    <!-- Puntos Horario -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi1_horario.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'horario'"
                      >
                        {{ r.kpi1_horario.puntos > 0 ? r.kpi1_horario.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Visitas -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi2_visitas.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'visitas'"
                      >
                        {{ r.kpi2_visitas.puntos > 0 ? r.kpi2_visitas.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Cuota Diaria -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi3_objetivo.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'objetivo'"
                      >
                        {{ r.kpi3_objetivo.puntos > 0 ? r.kpi3_objetivo.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Efectividad -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi4_efectividad.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'efectividad'"
                      >
                        {{ r.kpi4_efectividad.puntos > 0 ? r.kpi4_efectividad.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Compra Mínima -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi5_compra_minima.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'compra_minima'"
                      >
                        {{ r.kpi5_compra_minima.puntos > 0 ? r.kpi5_compra_minima.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Reposición -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi6_reposicion.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'reposicion'"
                      >
                        {{ r.kpi6_reposicion.puntos > 0 ? r.kpi6_reposicion.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Ampliación -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi7_ampliacion.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'ampliacion'"
                      >
                        {{ r.kpi7_ampliacion.puntos > 0 ? r.kpi7_ampliacion.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Puntos Covers -->
                    <td class="py-2 px-2 text-center">
                      <span
                        class="px-2 py-0.5 rounded text-[11px] font-bold inline-block cursor-pointer"
                        :class="r.kpi8_covers.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        @click="tabActiva = 'covers'"
                      >
                        {{ r.kpi8_covers.puntos > 0 ? r.kpi8_covers.puntos : '—' }}
                      </span>
                    </td>

                    <!-- Score Total AL FINAL -->
                    <td class="py-2 px-3 text-center bg-slate-50/70 border-l border-slate-200/60">
                      <span
                        class="px-2.5 py-1 rounded-lg text-xs font-bold inline-block min-w-[55px] shadow-2xs"
                        :class="claseBadgeScore(r.score_total)"
                      >
                        {{ r.score_total }} pts
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- PESTAÑA 1: HORARIO (10 PUNTOS) -->
        <div v-else-if="tabActiva === 'horario'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Horario de Ingreso al Primer PDV (10 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Hora límite: <strong>08:10:00</strong>. Modo actual: <strong>{{ estrictoHorario ? 'Estricto (Web ERP + App Móvil)' : 'Normal (Primer marcaje central)' }}</strong>.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Puntuales:</span>
              <strong class="text-emerald-600 ml-1">
                {{ rutasFiltradas.filter(r => r.kpi1_horario.puntos > 0).length }}
              </strong>
              <span class="text-slate-400 mx-1.5">/</span>
              <span class="text-slate-400">Total:</span>
              <strong class="text-slate-900 ml-1">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-center">Check-in Web (ERP)</th>
                  <th class="py-2.5 px-3 text-center">Check-in App Móvil</th>
                  <th class="py-2.5 px-3 text-center">Retraso (min)</th>
                  <th class="py-2.5 px-3 text-center">Estado Auditoría</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-center font-bold" :class="r.kpi1_horario.hora_web ? 'text-slate-800' : 'text-slate-400 italic'">
                    {{ r.kpi1_horario.hora_web || 'Sin registro' }}
                  </td>
                  <td class="py-2 px-3 text-center font-bold" :class="r.kpi1_horario.hora_movil ? 'text-slate-800' : 'text-slate-400 italic'">
                    {{ r.kpi1_horario.hora_movil || 'Sin registro' }}
                  </td>
                  <td class="py-2 px-3 text-center" :class="r.kpi1_horario.retraso_minutos > 0 ? 'text-rose-600 font-bold' : 'text-emerald-600'">
                    {{ r.kpi1_horario.retraso_minutos > 0 ? `+${r.kpi1_horario.retraso_minutos} min` : '0 min' }}
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold"
                      :class="r.kpi1_horario.cumple ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ r.kpi1_horario.estado }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi1_horario.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi1_horario.puntos }} / 10
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PESTAÑA 2: VISITAS (5 PUNTOS) -->
        <div v-else-if="tabActiva === 'visitas'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Clientes Visitados sobre Cartera Plan (5 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Condición: V<sub>activos</sub> &ge; C<sub>plan</sub>. Los clientes inactivos y visitas fuera de plan se contabilizan pero no suman a la meta activa.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Rutas con 100% Cobertura:</span>
              <strong class="text-emerald-600 ml-1">
                {{ rutasFiltradas.filter(r => r.kpi2_visitas.puntos > 0).length }}
              </strong>
              <span class="text-slate-400 mx-1">/</span>
              <strong class="text-slate-900">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <!-- Acordeón interactivo por Ruta -->
          <div class="space-y-3">
            <div
              v-for="r in rutasFiltradas"
              :key="r.ruta"
              class="bg-white rounded-xl border border-slate-200/90 shadow-xs overflow-hidden"
            >
              <!-- Cabecera de Ruta (Clic para expandir) -->
              <div
                @click="toggleAcordeonRuta(r.ruta)"
                class="px-4 py-3 bg-slate-50 hover:bg-slate-100/80 cursor-pointer flex items-center justify-between transition-colors select-none"
              >
                <div class="flex items-center gap-3">
                  <span class="font-mono font-bold text-sky-700 text-sm">{{ r.ruta }}</span>
                  <span class="text-slate-300">•</span>
                  <span class="font-semibold text-slate-900 text-xs">{{ r.vendedor }}</span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200/70 text-slate-700 font-mono">{{ r.canal }}</span>
                </div>

                <div class="flex items-center gap-3 text-xs font-mono">
                  <span class="text-slate-500">Plan: <strong>{{ r.kpi2_visitas.cartera_plan }}</strong></span>
                  <span class="text-slate-500">Visitados Activos: <strong class="text-sky-600">{{ r.kpi2_visitas.visitados_activos }}</strong></span>
                  <span v-if="r.kpi2_visitas.inactivos_visitados > 0" class="text-amber-600">Inactivos: <strong>{{ r.kpi2_visitas.inactivos_visitados }}</strong></span>
                  <span v-if="r.kpi2_visitas.fuera_de_plan > 0" class="text-purple-600">Fuera Plan: <strong>{{ r.kpi2_visitas.fuera_de_plan }}</strong></span>
                  
                  <span
                    class="px-2.5 py-1 rounded text-xs font-bold"
                    :class="r.kpi2_visitas.puntos > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  >
                    {{ r.kpi2_visitas.cobertura_pct }}% ({{ r.kpi2_visitas.puntos }} pts)
                  </span>

                  <span class="text-slate-400 font-bold text-sm">{{ rutaExpandida === r.ruta ? '▲' : '▼' }}</span>
                </div>
              </div>

              <!-- Detalle de Clientes de la Ruta expandida -->
              <div v-if="rutaExpandida === r.ruta" class="p-3 border-t border-slate-200">
                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-100/60 border-b border-slate-200 text-[10px] font-mono text-slate-500 uppercase">
                      <tr>
                        <th class="py-2 px-2.5">Cód / ID</th>
                        <th class="py-2 px-2.5">Cliente</th>
                        <th class="py-2 px-2.5">Dirección / Negocio</th>
                        <th class="py-2 px-2 text-center">Tipo Visita</th>
                        <th class="py-2 px-2 text-center">Check-In</th>
                        <th class="py-2 px-2 text-center">Check-Out</th>
                        <th class="py-2 px-2 text-center">Tiempo PDV</th>
                        <th class="py-2 px-2.5 text-center">Fotos</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                      <tr
                        v-for="c in r.detalle_visitas"
                        :key="c.cliente_id"
                        :class="c.visitado ? 'bg-white' : 'bg-rose-50/30 text-slate-400'"
                      >
                        <td class="py-1.5 px-2.5 font-bold">{{ c.codigo_cliente || c.cliente_id }}</td>
                        <td class="py-1.5 px-2.5 font-sans font-medium text-slate-900">{{ c.cliente }}</td>
                        <td class="py-1.5 px-2.5 font-sans text-slate-500 truncate max-w-xs">{{ c.direccion }}</td>
                        <td class="py-1.5 px-2 text-center">
                          <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold"
                            :class="{
                              'bg-emerald-100 text-emerald-800': c.tipo_visita === 'En Plan',
                              'bg-amber-100 text-amber-800': c.tipo_visita === 'Inactivo Visitado',
                              'bg-purple-100 text-purple-800': c.tipo_visita === 'Fuera de Plan',
                              'bg-rose-100 text-rose-700': c.tipo_visita === 'Pendiente'
                            }"
                          >
                            {{ c.tipo_visita }}
                          </span>
                        </td>
                        <td class="py-1.5 px-2 text-center">{{ c.hora_check_in || '—' }}</td>
                        <td class="py-1.5 px-2 text-center">{{ c.hora_check_out || '—' }}</td>
                        <td class="py-1.5 px-2 text-center">{{ c.tiempo_pdv ? `${c.tiempo_pdv} min` : '—' }}</td>
                        <td class="py-1.5 px-2.5 text-center">
                          <button
                            type="button"
                            @click="abrirModalFotos(c)"
                            class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded font-semibold text-[10px] cursor-pointer inline-flex items-center gap-1"
                          >
                            <span>{{ c.fotos_count > 0 ? `${c.fotos_count} foto(s)` : 'Ver fotos' }}</span>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PESTAÑA 3: CUOTA DIARIA (10 PUNTOS) -->
        <div v-else-if="tabActiva === 'objetivo'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 text-xs">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Cumplimiento de Cuota Diaria (10 Puntos)</h3>
              <p class="text-slate-500 mt-0.5">
                Cuota Diaria = Brecha (Meta Mensual - Facturado Previo) / Divisor Preventa (Días Hábiles Restantes - 2).
              </p>
            </div>
            <div class="flex items-center gap-2 font-mono text-[11px] bg-slate-50 p-2 rounded-lg border border-slate-200">
              <span class="text-slate-500">Días Hábiles Restantes Mes:</span>
              <strong class="text-slate-900">{{ rutasFiltradas[0]?.kpi3_objetivo?.dias_habiles_restantes || 0 }}</strong>
              <span class="text-slate-400">|</span>
              <span class="text-slate-500">Divisor Preventa:</span>
              <strong class="text-sky-700">{{ rutasFiltradas[0]?.kpi3_objetivo?.divisor_preventa || 1 }}</strong>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-right">Meta Mensual (Bs)</th>
                  <th class="py-2.5 px-3 text-right">Facturado Previo (Bs)</th>
                  <th class="py-2.5 px-3 text-right">Brecha (Bs)</th>
                  <th class="py-2.5 px-3 text-right font-bold text-sky-800">Cuota Diaria (Bs)</th>
                  <th class="py-2.5 px-3 text-right font-bold text-emerald-700">Preventa Hoy (Bs)</th>
                  <th class="py-2.5 px-3 text-center">% Alcance</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-right">Bs. {{ formatoMoneda(r.kpi3_objetivo.meta_mensual) }}</td>
                  <td class="py-2 px-3 text-right">Bs. {{ formatoMoneda(r.kpi3_objetivo.facturado_previo) }}</td>
                  <td class="py-2 px-3 text-right font-semibold text-slate-800">Bs. {{ formatoMoneda(r.kpi3_objetivo.brecha) }}</td>
                  <td class="py-2 px-3 text-right font-bold text-sky-700 bg-sky-50/40">Bs. {{ formatoMoneda(r.kpi3_objetivo.cuota_diaria) }}</td>
                  <td class="py-2 px-3 text-right font-bold text-emerald-600 bg-emerald-50/30">Bs. {{ formatoMoneda(r.kpi3_objetivo.venta_hoy) }}</td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[11px] font-bold"
                      :class="r.kpi3_objetivo.alcance_pct >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                      {{ r.kpi3_objetivo.alcance_pct }}%
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi3_objetivo.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi3_objetivo.puntos }} / 10
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PESTAÑA 4: EFECTIVIDAD (30 PUNTOS) -->
        <div v-else-if="tabActiva === 'efectividad'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Efectividad en Ruta (30 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Umbral mínimo de calificación: <strong>55.0%</strong> de conversión sobre la cartera activa planificada del día.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Rutas Efectivas (&ge; 55%):</span>
              <strong class="text-emerald-600 ml-1">{{ rutasFiltradas.filter(r => r.kpi4_efectividad.puntos > 0).length }}</strong>
              <span class="text-slate-400 mx-1">/</span>
              <strong class="text-slate-900">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-center">Cartera Plan</th>
                  <th class="py-2.5 px-3 text-center">Compradores</th>
                  <th class="py-2.5 px-3 text-center">% Efectividad</th>
                  <th class="py-2.5 px-3 text-center text-emerald-700">Tickets &ge; 70 Bs</th>
                  <th class="py-2.5 px-3 text-center text-rose-700">Bajo Mínimo (&lt; 70 Bs)</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-center">{{ r.kpi4_efectividad.cartera_plan }}</td>
                  <td class="py-2 px-3 text-center font-bold text-slate-900">{{ r.kpi4_efectividad.compradores }}</td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[11px] font-bold"
                      :class="r.kpi4_efectividad.efectividad_pct >= 55 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ r.kpi4_efectividad.efectividad_pct }}%
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold text-emerald-600">{{ r.kpi4_efectividad.compradores_sobre_70 }}</td>
                  <td class="py-2 px-3 text-center font-bold" :class="r.kpi4_efectividad.compradores_bajo_70 > 0 ? 'text-rose-600' : 'text-slate-400'">
                    {{ r.kpi4_efectividad.compradores_bajo_70 }}
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi4_efectividad.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi4_efectividad.puntos }} / 30
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PESTAÑA 5: MÍNIMO 70 BS (5 PUNTOS) -->
        <div v-else-if="tabActiva === 'compra_minima'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Compra Mínima Diaria de 70 Bs (5 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Regla Estricta: El 100% de los compradores deben acumular &ge; 70.00 Bs. Si existe al menos 1 cliente con pedido &lt; 70 Bs, la ruta obtiene 0 pts.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Rutas Conformes:</span>
              <strong class="text-emerald-600 ml-1">{{ rutasFiltradas.filter(r => r.kpi5_compra_minima.puntos > 0).length }}</strong>
              <span class="text-slate-400 mx-1">/</span>
              <strong class="text-slate-900">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <!-- Selector de Ruta para Auditoría por Tienda -->
          <div class="bg-white p-3 rounded-xl border border-slate-200/90 flex items-center gap-3 text-xs">
            <span class="font-mono text-slate-500">Auditar Ruta Específica:</span>
            <select
              v-model="rutaSeleccionadaDetalle"
              class="h-8 px-2 bg-slate-50 border border-slate-200 rounded-lg font-mono font-bold text-slate-800"
            >
              <option value="">Todas las Rutas (Vista Resumida)</option>
              <option v-for="r in rutasFiltradas" :key="r.ruta" :value="r.ruta">
                {{ r.ruta }} - {{ r.vendedor }} ({{ r.kpi5_compra_minima.compradores_bajo_70 }} bajo mínimo)
              </option>
            </select>
          </div>

          <!-- Tabla Resumen por Ruta si no hay ruta individual seleccionada -->
          <div v-if="!rutaSeleccionadaDetalle" class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-center">Total Compradores</th>
                  <th class="py-2.5 px-3 text-center text-rose-700">Clientes &lt; 70 Bs</th>
                  <th class="py-2.5 px-3 text-center">Estado Disciplina</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                  <th class="py-2.5 px-3 text-center">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-center font-bold">{{ r.kpi5_compra_minima.compradores }}</td>
                  <td class="py-2 px-3 text-center font-bold" :class="r.kpi5_compra_minima.compradores_bajo_70 > 0 ? 'text-rose-600 bg-rose-50/60' : 'text-emerald-600'">
                    {{ r.kpi5_compra_minima.compradores_bajo_70 }}
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2.5 py-0.5 rounded text-[10px] font-bold"
                      :class="r.kpi5_compra_minima.cumple ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ r.kpi5_compra_minima.cumple ? '100% Conforme' : 'Infracción Drop Size' }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi5_compra_minima.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi5_compra_minima.puntos }} / 5
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center">
                    <button
                      type="button"
                      @click="rutaSeleccionadaDetalle = r.ruta"
                      class="px-2 py-0.5 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded text-[10px] font-semibold cursor-pointer"
                    >
                      Auditar Tiendas &rarr;
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Auditoría Desglosada Tienda por Tienda -->
          <div v-else class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <span class="font-bold font-mono text-xs text-slate-800">
                Detalle Tienda por Tienda: Ruta {{ rutaSeleccionadaDetalle }}
              </span>
              <button @click="rutaSeleccionadaDetalle = ''" class="text-xs text-sky-600 hover:underline cursor-pointer">
                &larr; Volver a todas las rutas
              </button>
            </div>
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-100/70 border-b border-slate-200 text-[10px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2 px-3">Cód / ID</th>
                  <th class="py-2 px-3">Cliente</th>
                  <th class="py-2 px-3">Dirección</th>
                  <th class="py-2 px-3 text-center">Ítems Pedidos</th>
                  <th class="py-2 px-3 text-right">Monto Preventa</th>
                  <th class="py-2 px-3 text-center">Estado Auditoría</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                <tr
                  v-for="t in getDetalleTicketsRuta(rutaSeleccionadaDetalle)"
                  :key="t.cliente_id"
                  :class="t.conforme_70 ? 'hover:bg-slate-50' : 'bg-rose-50/50 hover:bg-rose-50 font-bold'"
                >
                  <td class="py-2 px-3">{{ t.codigo_cliente }}</td>
                  <td class="py-2 px-3 font-sans font-medium text-slate-900">{{ t.cliente }}</td>
                  <td class="py-2 px-3 font-sans text-slate-500 truncate max-w-xs">{{ t.direccion }}</td>
                  <td class="py-2 px-3 text-center">{{ t.items_count }}</td>
                  <td class="py-2 px-3 text-right font-bold" :class="t.conforme_70 ? 'text-emerald-700' : 'text-rose-600'">
                    Bs. {{ formatoMoneda(t.monto_total) }}
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold"
                      :class="t.conforme_70 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ t.conforme_70 ? 'Conforme (&ge; 70 Bs)' : `Déficit de Bs. ${formatoMoneda(t.deficit_70)}` }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PESTAÑA 6: REPOSICIÓN (15 PUNTOS) -->
        <div v-else-if="tabActiva === 'reposicion'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Pedido de Reposición (15 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Ventana de análisis: <strong>13 semanas (90 días)</strong>. Umbral meta: <strong>60.0%</strong> de tiendas evaluables recomprando sus habituales.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Rutas con Reposición Exitosa:</span>
              <strong class="text-emerald-600 ml-1">{{ rutasFiltradas.filter(r => r.kpi6_reposicion.puntos > 0).length }}</strong>
              <span class="text-slate-400 mx-1">/</span>
              <strong class="text-slate-900">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-center">Tiendas Evaluables</th>
                  <th class="py-2.5 px-3 text-center">Sin Base (Apertura)</th>
                  <th class="py-2.5 px-3 text-center text-emerald-700">Recompras Exitosas</th>
                  <th class="py-2.5 px-3 text-center">% Tasa Reposición</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-center font-bold">{{ r.kpi6_reposicion.evaluables }}</td>
                  <td class="py-2 px-3 text-center text-slate-400">{{ r.kpi6_reposicion.sin_base_apertura }}</td>
                  <td class="py-2 px-3 text-center font-bold text-emerald-600">{{ r.kpi6_reposicion.cumplen }}</td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[11px] font-bold"
                      :class="r.kpi6_reposicion.tasa_pct >= 60 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ r.kpi6_reposicion.tasa_pct }}%
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi6_reposicion.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi6_reposicion.puntos }} / 15
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PESTAÑA 7: AMPLIACIÓN (20 PUNTOS) -->
        <div v-else-if="tabActiva === 'ampliacion'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Ampliación de Portafolio (20 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Regla por Tienda: Exige ordenar <strong>&ge; 2 SKUs nuevos</strong> respecto al historial de 90 días. Gatekeeper de Ruta: <strong>&ge; 60.0%</strong>.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Rutas con Ampliación:</span>
              <strong class="text-emerald-600 ml-1">{{ rutasFiltradas.filter(r => r.kpi7_ampliacion.puntos > 0).length }}</strong>
              <span class="text-slate-400 mx-1">/</span>
              <strong class="text-slate-900">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-center">Total Compradores</th>
                  <th class="py-2.5 px-3 text-center text-purple-700">Tiendas con &ge; 2 SKUs Nuevos</th>
                  <th class="py-2.5 px-3 text-center">% Tasa Ampliación</th>
                  <th class="py-2.5 px-3 text-center">Gatekeeper (&ge; 60%)</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-center">{{ r.kpi7_ampliacion.total_compradores }}</td>
                  <td class="py-2 px-3 text-center font-bold text-purple-700 bg-purple-50/30">
                    {{ r.kpi7_ampliacion.cumplen }}
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[11px] font-bold"
                      :class="r.kpi7_ampliacion.tasa_pct >= 60 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ r.kpi7_ampliacion.tasa_pct }}%
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold"
                      :class="r.kpi7_ampliacion.cumple ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    >
                      {{ r.kpi7_ampliacion.cumple ? 'Superado' : 'Bloqueado (< 60%)' }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi7_ampliacion.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi7_ampliacion.puntos }} / 20
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PESTAÑA 8: COVERS (5 PUNTOS) -->
        <div v-else-if="tabActiva === 'covers'" class="space-y-4">
          <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-slate-900">Covers / Multicategoría (5 Puntos)</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Objetivo: Evitar tickets monoproducto. Exige <strong>&ge; 2 categorías distintas</strong> por cliente. Meta de ruta: <strong>60.0%</strong>.
              </p>
            </div>
            <div class="text-right text-xs font-mono">
              <span class="text-slate-400">Rutas con Covers:</span>
              <strong class="text-emerald-600 ml-1">{{ rutasFiltradas.filter(r => r.kpi8_covers.puntos > 0).length }}</strong>
              <span class="text-slate-400 mx-1">/</span>
              <strong class="text-slate-900">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-xs border border-slate-200/90 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-700">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono text-slate-500 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Ruta</th>
                  <th class="py-2.5 px-3">Preventista</th>
                  <th class="py-2.5 px-3 text-center">Total Compradores</th>
                  <th class="py-2.5 px-3 text-center text-sky-700">Compradores Multicategoría</th>
                  <th class="py-2.5 px-3 text-center">% Tasa Covers</th>
                  <th class="py-2.5 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-bold text-sky-700">{{ r.ruta }}</td>
                  <td class="py-2 px-3 font-sans">{{ r.vendedor }}</td>
                  <td class="py-2 px-3 text-center">{{ r.kpi8_covers.total_compradores }}</td>
                  <td class="py-2 px-3 text-center font-bold text-sky-700 bg-sky-50/30">
                    {{ r.kpi8_covers.cumplen }}
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded text-[11px] font-bold"
                      :class="r.kpi8_covers.tasa_pct >= 60 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                      {{ r.kpi8_covers.tasa_pct }}%
                    </span>
                  </td>
                  <td class="py-2 px-3 text-center font-bold">
                    <span :class="r.kpi8_covers.puntos > 0 ? 'text-emerald-600' : 'text-slate-400'">
                      {{ r.kpi8_covers.puntos }} / 5
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Modal de Evidencias Fotográficas -->
      <ClienteFotosModal
        :show="modalFoto.abierto"
        :cliente="modalFoto.cliente"
        @close="modalFoto.abierto = false"
      />

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import ClienteFotosModal from './ClienteFotosModal.vue';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_default: { type: String, default: () => new Date().toISOString().split('T')[0] },
});

// Pestañas de Navegación del Módulo Preventas
const preventasSubTabs = [
  { id: 'auditoria', name: 'Auditoría en Mapa', route: 'supervisor.preventas.index' },
  { id: 'cumplimiento', name: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
];

// Pestañas Monográficas de Evaluación (Sin emojis, nombres directos)
const pestañasMonograficas = [
  { id: 'general', nombre: 'Pizarra General', puntos: '100 pts' },
  { id: 'horario', nombre: 'Horario', puntos: '10 pts' },
  { id: 'visitas', nombre: 'Visitas', puntos: '5 pts' },
  { id: 'objetivo', nombre: 'Cuota Diaria', puntos: '10 pts' },
  { id: 'efectividad', nombre: 'Efectividad', puntos: '30 pts' },
  { id: 'compra_minima', nombre: 'Mín. 70 Bs', puntos: '5 pts' },
  { id: 'reposicion', nombre: 'Reposición', puntos: '15 pts' },
  { id: 'ampliacion', nombre: 'Ampliación', puntos: '20 pts' },
  { id: 'covers', nombre: 'Covers', puntos: '5 pts' },
];

// Estado Reactivo
const tabActiva = ref('general');
const filtroFecha = ref(props.fecha_default);
const canalSeleccionado = ref('TDB'); // Canal TDB por defecto
const rutaFiltroSeleccionada = ref('TODAS'); // Filtro por rutas
const estrictoHorario = ref(false);
const busquedaRuta = ref('');
const cargando = ref(false);

const diaSemanaEvaluado = ref('');
const rutasData = ref([]);

// Interacción Acordeón y Modal
const rutaExpandida = ref(null);
const rutaSeleccionadaDetalle = ref('');
const modalFoto = ref({
  abierto: false,
  cliente: null,
});

// Rutas disponibles filtradas según el canal seleccionado
const rutasDisponibles = computed(() => {
  if (canalSeleccionado.value === 'TODOS') {
    return props.catalogo_rutas;
  }
  return props.catalogo_rutas.filter(r => r.canal === canalSeleccionado.value);
});

function alCambiarCanal() {
  rutaFiltroSeleccionada.value = 'TODAS';
  cargarCumplimiento();
}

function toggleAcordeonRuta(r) {
  rutaExpandida.value = rutaExpandida.value === r ? null : r;
}

function abrirModalFotos(c) {
  modalFoto.value = {
    abierto: true,
    cliente: c,
  };
}

function getDetalleTicketsRuta(ruta) {
  const r = rutasData.value.find(item => item.ruta === ruta);
  return r ? r.detalle_compra_minima : [];
}

// Filtrado Reactivo por búsqueda de texto
const rutasFiltradas = computed(() => {
  if (!busquedaRuta.value.trim()) {
    return rutasData.value;
  }
  const q = busquedaRuta.value.toLowerCase().trim();
  return rutasData.value.filter(r =>
    (r.ruta && r.ruta.toLowerCase().includes(q)) ||
    (r.vendedor && r.vendedor.toLowerCase().includes(q)) ||
    (r.canal && r.canal.toLowerCase().includes(q))
  );
});

// Petición al Backend
async function cargarCumplimiento() {
  cargando.value = true;
  try {
    const payload = {
      fecha: filtroFecha.value,
      canales: canalSeleccionado.value === 'TODOS' ? [] : [canalSeleccionado.value],
      rutas: rutaFiltroSeleccionada.value === 'TODAS' ? [] : [rutaFiltroSeleccionada.value],
      estricto_horario: estrictoHorario.value,
    };

    const url = typeof route === 'function'
      ? route('supervisor.preventas.cumplimiento.data')
      : '/supervisor/preventas/cumplimiento/data';

    const res = await axios.post(url, payload);

    if (res.data) {
      diaSemanaEvaluado.value = res.data.dia_semana || '';
      rutasData.value = res.data.rutas || [];
    }
  } catch (error) {
    console.error('Error al cargar datos de cumplimiento:', error);
  } finally {
    cargando.value = false;
  }
}

// Helpers Visuales
function formatoMoneda(val) {
  if (!val) return '0.00';
  return Number(val).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function claseBadgeScore(score) {
  if (score >= 95) return 'bg-emerald-500 text-white font-extrabold';
  if (score >= 80) return 'bg-emerald-100 text-emerald-800 font-bold border border-emerald-300';
  if (score >= 60) return 'bg-amber-100 text-amber-800 font-bold border border-amber-300';
  return 'bg-rose-100 text-rose-800 font-bold border border-rose-300';
}

function claseMedalla(rank) {
  if (rank === 1) return 'bg-amber-400 text-slate-950 font-extrabold shadow-sm ring-2 ring-amber-200';
  if (rank === 2) return 'bg-slate-300 text-slate-900 font-bold';
  if (rank === 3) return 'bg-amber-700 text-white font-bold';
  return 'bg-slate-100 text-slate-600 border border-slate-200';
}

onMounted(() => {
  cargarCumplimiento();
});
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>
