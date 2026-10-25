<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-y-auto custom-scroll p-3 sm:p-5 font-sans text-ink space-y-3">
      
      <!-- ==========================================
           1. BARRA DE FILTROS UNIVERSAL (APPLE LIQUID GLASS)
           ========================================== -->
      <div class="apple-glass-card rounded-[16px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2 select-none font-sans shrink-0">
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
          
          <!-- SubNavTabs (Auditoría en Mapa / Cumplimiento Unilever) -->
          <SubNavTabs :tabs="preventasSubTabs" currentTab="cumplimiento" />

          <div class="h-5 w-px bg-black/[0.08] hidden sm:block shrink-0"></div>

          <!-- Píldora de Fecha -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Fecha:</span>
            <input
              type="date"
              v-model="filtroFecha"
              @change="cargarCumplimiento"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-num"
            />
          </div>

          <!-- Píldora de Selección Canal (Por defecto 'TDB') -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <select
              v-model="canalSeleccionado"
              @change="alCambiarCanal"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
            >
              <option value="TODOS">Todos</option>
              <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Píldora de Selección Ruta -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
            <select
              v-model="rutaFiltroSeleccionada"
              @change="cargarCumplimiento"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer max-w-[170px]"
            >
              <option value="TODAS">Todas</option>
              <option v-for="r in rutasDisponibles" :key="r.ruta" :value="r.ruta">
                {{ r.ruta }} - {{ r.vendedor }}
              </option>
            </select>
          </div>

          <!-- Switch Modo Estricto Horario (Validar App + Web) -->
          <label
            class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-medium cursor-pointer transition-all select-none"
            :class="estrictoHorario ? 'text-amber-800 font-semibold' : 'text-[#6E6E73]'"
            title="Exige obligatoriamente marcación antes de las 08:10:00 tanto en Web (ERP) como en App Móvil"
          >
            <input
              type="checkbox"
              v-model="estrictoHorario"
              @change="cargarCumplimiento"
              class="rounded border-slate-300 text-[#0071E3] focus:ring-0 w-3 h-3 cursor-pointer"
            />
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Horario:</span>
            <span class="text-[11px]">Modo Estricto (App+Web)</span>
          </label>

          <!-- Buscador -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <svg class="w-3.5 h-3.5 text-[#86868B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              type="text"
              v-model="busquedaRuta"
              placeholder="Buscar ruta..."
              class="bg-transparent text-[11px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-28 sm:w-40 font-num"
            />
          </div>

          <!-- Botón Principal "Consultar" (Acción Primaria Apple) -->
          <button
            type="button"
            :disabled="cargando"
            @click="cargarCumplimiento"
            class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] hover:shadow-[0_4px_12px_rgba(0,113,227,0.45)] cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed select-none shrink-0"
          >
            <!-- Spinner SVG cuando está cargando -->
            <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin text-white shrink-0" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <!-- Lupa SVG en reposo -->
            <svg v-else class="w-3.5 h-3.5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>{{ cargando ? 'Consultando...' : 'Consultar' }}</span>
          </button>
        </div>
      </div>

      <!-- ==========================================
           2. CONTROL SEGMENTADO (TABS ESTILO macOS / visionOS)
           ========================================== -->
      <div class="apple-glass-card rounded-[14px] p-1 flex items-center gap-1 overflow-x-auto custom-scroll text-[11px] select-none shrink-0">
        <button
          v-for="t in pestañasMonograficas"
          :key="t.id"
          type="button"
          @click="tabActiva = t.id"
          :class="tabActiva === t.id ? 'glass-active-pill font-bold' : 'text-[#6E6E73] hover:text-[#1D1D1F] font-semibold hover:bg-white/50'"
          class="px-3 py-1.5 rounded-[9px] transition-all duration-200 cursor-pointer shrink-0 flex items-center gap-1.5"
        >
          <span v-if="tabActiva === t.id" class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
          <span>{{ t.nombre }}</span>
          <span
            v-if="t.puntos"
            class="text-[9px] font-mono px-1 py-0.2 rounded font-semibold"
            :class="tabActiva === t.id ? 'bg-[#0071E3]/10 text-[#0071E3]' : 'bg-black/[0.04] text-[#86868B]'"
          >
            {{ t.puntos }}
          </span>
        </button>
      </div>

      <!-- ==========================================
           3. ÁREA DE MATRICES & CONTENIDO GLASS
           ========================================== -->
      <div class="flex-1 min-h-0">
        
        <!-- PESTAÑA 0: PIZARRA MODELO UNILEVER (EXACTA AL DISEÑO DEL USUARIO) -->
        <div v-if="tabActiva === 'general'" class="space-y-4">
          <div class="apple-glass-card rounded-[16px] overflow-hidden">
            
            <!-- Encabezado de la Tarjeta exacto al screenshot -->
            <div class="px-4 py-3 border-b border-black/[0.05] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 bg-white/40">
              <div>
                <h2 class="text-sm sm:text-base font-bold text-[#1D1D1F] tracking-tight">Pizarra Modelo Unilever</h2>
                <p class="text-[11px] text-[#6E6E73] font-normal">Matriz de cumplimiento de preventa · Criterio Binario (Todo o Nada) · 100 Pts Base</p>
              </div>
              <div class="flex items-center gap-2.5 self-end sm:self-auto text-xs">
                <span class="text-[11px] text-[#6E6E73]">
                  Orden: <strong class="font-bold text-[#0071E3]">Score Sumatoria</strong> (Mayor a menor)
                </span>
                <span class="apple-control-pill rounded-full px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F]">
                  Rutas en Pizarra: <strong class="font-bold font-num">{{ rutasFiltradas.length }}</strong>
                </span>
              </div>
            </div>

            <!-- Tabla Leaderboard idéntica al screenshot -->
            <div class="overflow-x-auto custom-scroll">
              <table class="w-full text-left text-xs">
                <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase tracking-wider font-sans">
                  <tr>
                    <th class="py-3 px-3 text-center w-12 font-bold text-[#86868B]">POS</th>
                    <th class="py-3 px-4 font-bold text-[#86868B]">RUTA</th>
                    <th class="py-3 px-4 font-bold text-[#86868B]">PREVENTISTA</th>
                    
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'horario'" title="Ver auditoría Horario">
                      1. INGRESO<br><span class="text-[9px] font-normal text-[#86868B]/80">(10 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'visitas'" title="Ver auditoría Visitas">
                      2. VISITAS<br><span class="text-[9px] font-normal text-[#86868B]/80">(5 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'objetivo'" title="Ver auditoría Cuota Diaria">
                      3. OBJ. DIARIO<br><span class="text-[9px] font-normal text-[#86868B]/80">(10 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'efectividad'" title="Ver auditoría Efectividad">
                      4. EFECT.<br><span class="text-[9px] font-normal text-[#86868B]/80">(30 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'compra_minima'" title="Ver auditoría Compra Mínima">
                      5. MÍN 70<br><span class="text-[9px] font-normal text-[#86868B]/80">(5 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'reposicion'" title="Ver auditoría Reposición">
                      6. REPO<br><span class="text-[9px] font-normal text-[#86868B]/80">(15 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'ampliacion'" title="Ver auditoría Ampliación">
                      7. AMPL.<br><span class="text-[9px] font-normal text-[#86868B]/80">(20 PTS)</span>
                    </th>
                    <th class="py-3 px-2 text-center cursor-pointer hover:bg-black/[0.02]" @click="tabActiva = 'covers'" title="Ver auditoría Covers">
                      8. COVERS<br><span class="text-[9px] font-normal text-[#86868B]/80">(5 PTS)</span>
                    </th>

                    <!-- Score Total con fondo suave azul de acento -->
                    <th class="py-3 px-4 text-center bg-[#0071E3]/[0.05] border-l border-black/[0.05] text-[#0071E3] font-bold">
                      SCORE<br><span class="text-[9px] font-normal text-[#86868B]">SUMATORIA</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-black/[0.03]">
                  <tr
                    v-for="r in rutasFiltradas"
                    :key="r.ruta"
                    class="transition-colors hover:bg-[#0071E3]/[0.03]"
                  >
                    <!-- POS -->
                    <td class="py-3 px-3 text-center text-[#6E6E73] font-num text-xs">
                      {{ r.rank }}
                    </td>

                    <!-- RUTA -->
                    <td class="py-3 px-4 font-bold text-[#1D1D1F] text-xs whitespace-nowrap">
                      {{ r.ruta }}
                    </td>

                    <!-- PREVENTISTA -->
                    <td class="py-3 px-4 text-[#1D1D1F] text-xs font-normal">
                      {{ r.vendedor }}
                    </td>

                    <!-- 1. INGRESO (10 PTS) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'horario'">
                      <span
                        v-if="r.kpi1_horario.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi1_horario.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 2. VISITAS (5 PTS) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'visitas'">
                      <span
                        v-if="r.kpi2_visitas.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi2_visitas.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 3. OBJ. DIARIO (10 PTS) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'objetivo'">
                      <span
                        v-if="r.kpi3_objetivo.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi3_objetivo.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 4. EFECT. (30 PTS) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'efectividad'">
                      <span
                        v-if="r.kpi4_efectividad.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi4_efectividad.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 5. MÍN 70 (5 PTS) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'compra_minima'">
                      <span
                        v-if="r.kpi5_compra_minima.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi5_compra_minima.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 6. REPO (Pausado actualmente -> —) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'reposicion'">
                      <span
                        v-if="r.kpi6_reposicion.evaluado && r.kpi6_reposicion.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi6_reposicion.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 7. AMPL. (Pausado actualmente -> —) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'ampliacion'">
                      <span
                        v-if="r.kpi7_ampliacion.evaluado && r.kpi7_ampliacion.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi7_ampliacion.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- 8. COVERS (Pausado actualmente -> —) -->
                    <td class="py-3 px-2 text-center cursor-pointer" @click="tabActiva = 'covers'">
                      <span
                        v-if="r.kpi8_covers.evaluado && r.kpi8_covers.puntos > 0"
                        class="inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-[6px] text-xs font-bold bg-[#EBF9EF] text-[#248A3D] font-num"
                      >
                        {{ r.kpi8_covers.puntos }}
                      </span>
                      <span v-else class="text-xs text-[#C7C7CC] font-sans">—</span>
                    </td>

                    <!-- SCORE SUMATORIA -->
                    <td class="py-3 px-4 text-center bg-[#0071E3]/[0.04] border-l border-black/[0.05]">
                      <span class="text-xs font-bold text-[#1D1D1F] font-num whitespace-nowrap">
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
          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">1. Horario de Ingreso al Primer PDV (10 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Hora límite: <strong>08:10:00</strong>. Modo actual: <strong>{{ estrictoHorario ? 'Estricto (Web ERP + App Móvil)' : 'Normal (Primer marcaje central)' }}</strong>.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">Puntuales:</span>
              <strong class="text-[#248A3D] ml-1 font-bold">
                {{ rutasFiltradas.filter(r => r.kpi1_horario.puntos > 0).length }}
              </strong>
              <span class="text-[#86868B] mx-1.5">/</span>
              <span class="text-[#86868B]">Total:</span>
              <strong class="text-[#1D1D1F] ml-1 font-bold">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="apple-glass-card rounded-[16px] overflow-hidden">
            <table class="w-full text-left text-xs">
              <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase font-sans">
                <tr>
                  <th class="py-3 px-4">Ruta</th>
                  <th class="py-3 px-4">Preventista</th>
                  <th class="py-3 px-3 text-center">Check-in Web (ERP)</th>
                  <th class="py-3 px-3 text-center">Check-in App Móvil</th>
                  <th class="py-3 px-3 text-center">Retraso (min)</th>
                  <th class="py-3 px-3 text-center">Estado Auditoría</th>
                  <th class="py-3 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.03]">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-[#0071E3]/[0.02]">
                  <td class="py-2.5 px-4 font-bold text-[#0071E3]">{{ r.ruta }}</td>
                  <td class="py-2.5 px-4 text-[#1D1D1F]">{{ r.vendedor }}</td>
                  <td class="py-2.5 px-3 text-center font-semibold font-num" :class="r.kpi1_horario.hora_web ? 'text-[#1D1D1F]' : 'text-[#86868B] italic'">
                    {{ r.kpi1_horario.hora_web || 'Sin registro' }}
                  </td>
                  <td class="py-2.5 px-3 text-center font-semibold font-num" :class="r.kpi1_horario.hora_movil ? 'text-[#1D1D1F]' : 'text-[#86868B] italic'">
                    {{ r.kpi1_horario.hora_movil || 'Sin registro' }}
                  </td>
                  <td class="py-2.5 px-3 text-center font-num font-semibold" :class="r.kpi1_horario.retraso_minutos > 0 ? 'text-[#C9342C]' : 'text-[#248A3D]'">
                    {{ r.kpi1_horario.retraso_minutos > 0 ? `+${r.kpi1_horario.retraso_minutos} min` : '0 min' }}
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold"
                      :class="r.kpi1_horario.cumple ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                    >
                      {{ r.kpi1_horario.estado }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold font-num">
                    <span :class="r.kpi1_horario.puntos > 0 ? 'text-[#248A3D]' : 'text-[#86868B]'">
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
          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">2. Clientes Visitados sobre Cartera Plan (5 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Condición: V<sub>activos</sub> &ge; C<sub>plan</sub>. Los clientes inactivos y visitas fuera de plan no suman a la meta activa.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">100% Cobertura:</span>
              <strong class="text-[#248A3D] ml-1 font-bold">
                {{ rutasFiltradas.filter(r => r.kpi2_visitas.puntos > 0).length }}
              </strong>
              <span class="text-[#86868B] mx-1">/</span>
              <strong class="text-[#1D1D1F] font-bold">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="r in rutasFiltradas"
              :key="r.ruta"
              class="apple-glass-card rounded-[14px] overflow-hidden"
            >
              <!-- Cabecera de Ruta (Clic para expandir) -->
              <div
                @click="toggleAcordeonRuta(r.ruta)"
                class="px-4 py-3 bg-white/40 hover:bg-white/60 cursor-pointer flex items-center justify-between transition-colors select-none"
              >
                <div class="flex items-center gap-3">
                  <span class="font-bold text-[#0071E3] text-sm">{{ r.ruta }}</span>
                  <span class="text-[#86868B]/40">•</span>
                  <span class="font-semibold text-[#1D1D1F] text-xs">{{ r.vendedor }}</span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-black/[0.04] text-[#6E6E73]">{{ r.canal }}</span>
                </div>

                <div class="flex items-center gap-3 text-xs font-num">
                  <span class="text-[#6E6E73]">Plan: <strong>{{ r.kpi2_visitas.cartera_plan }}</strong></span>
                  <span class="text-[#6E6E73]">Visitados Activos: <strong class="text-[#0071E3]">{{ r.kpi2_visitas.visitados_activos }}</strong></span>
                  <span v-if="r.kpi2_visitas.inactivos_visitados > 0" class="text-amber-700">Inactivos: <strong>{{ r.kpi2_visitas.inactivos_visitados }}</strong></span>
                  <span v-if="r.kpi2_visitas.fuera_de_plan > 0" class="text-purple-700">Fuera Plan: <strong>{{ r.kpi2_visitas.fuera_de_plan }}</strong></span>
                  
                  <span
                    class="px-2.5 py-1 rounded-[6px] text-xs font-bold"
                    :class="r.kpi2_visitas.puntos > 0 ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                  >
                    {{ r.kpi2_visitas.cobertura_pct }}% ({{ r.kpi2_visitas.puntos }} pts)
                  </span>

                  <span class="text-[#86868B] text-xs font-mono">{{ rutaExpandida === r.ruta ? '▲' : '▼' }}</span>
                </div>
              </div>

              <!-- Detalle de Clientes de la Ruta expandida -->
              <div v-if="rutaExpandida === r.ruta" class="p-3 border-t border-black/[0.05] bg-white/30">
                <div class="overflow-x-auto custom-scroll">
                  <table class="w-full text-left text-xs">
                    <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase">
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
                    <tbody class="divide-y divide-black/[0.03] font-num text-[11px]">
                      <tr
                        v-for="c in r.detalle_visitas"
                        :key="c.cliente_id"
                        :class="c.visitado ? 'bg-transparent' : 'text-[#86868B]'"
                      >
                        <td class="py-2 px-2.5 font-bold">{{ c.codigo_cliente || c.cliente_id }}</td>
                        <td class="py-2 px-2.5 font-sans font-medium text-[#1D1D1F]">{{ c.cliente }}</td>
                        <td class="py-2 px-2.5 font-sans text-[#6E6E73] truncate max-w-xs">{{ c.direccion }}</td>
                        <td class="py-2 px-2 text-center">
                          <span
                            class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold"
                            :class="{
                              'bg-[#EBF9EF] text-[#248A3D]': c.tipo_visita === 'En Plan',
                              'bg-[#FFF5E5] text-[#B25E00]': c.tipo_visita === 'Inactivo Visitado',
                              'bg-purple-100 text-purple-800': c.tipo_visita === 'Fuera de Plan',
                              'bg-[#FDF0EF] text-[#C9342C]': c.tipo_visita === 'Pendiente'
                            }"
                          >
                            {{ c.tipo_visita }}
                          </span>
                        </td>
                        <td class="py-2 px-2 text-center">{{ c.hora_check_in || '—' }}</td>
                        <td class="py-2 px-2 text-center">{{ c.hora_check_out || '—' }}</td>
                        <td class="py-2 px-2 text-center">{{ c.tiempo_pdv ? `${c.tiempo_pdv} min` : '—' }}</td>
                        <td class="py-2 px-2.5 text-center">
                          <button
                            type="button"
                            @click="abrirModalFotos(c)"
                            class="apple-control-pill px-2 py-0.5 rounded-[6px] text-[#0071E3] font-semibold text-[10px] cursor-pointer inline-flex items-center gap-1"
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
          <div class="apple-glass-card p-4 rounded-[16px] flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 text-xs">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">3. Cumplimiento de Objetivo Diario (10 Puntos)</h3>
              <p class="text-[#6E6E73] mt-0.5">
                Cuota Diaria = Brecha (Meta Mensual - Facturado Previo) / Divisor Preventa (Días Hábiles Restantes - 2).
              </p>
            </div>
            <div class="flex items-center gap-2 font-num text-[11px] apple-control-pill px-3 py-1.5 rounded-[10px]">
              <span class="text-[#86868B]">Días Hábiles Restantes:</span>
              <strong class="text-[#1D1D1F]">{{ rutasFiltradas[0]?.kpi3_objetivo?.dias_habiles_restantes || 0 }}</strong>
              <span class="text-[#86868B]/40">|</span>
              <span class="text-[#86868B]">Divisor Preventa:</span>
              <strong class="text-[#0071E3]">{{ rutasFiltradas[0]?.kpi3_objetivo?.divisor_preventa || 1 }}</strong>
            </div>
          </div>

          <div class="apple-glass-card rounded-[16px] overflow-hidden">
            <table class="w-full text-left text-xs">
              <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase font-sans">
                <tr>
                  <th class="py-3 px-4">Ruta</th>
                  <th class="py-3 px-4">Preventista</th>
                  <th class="py-3 px-3 text-right">Meta Mensual (Bs)</th>
                  <th class="py-3 px-3 text-right">Facturado Previo (Bs)</th>
                  <th class="py-3 px-3 text-right">Brecha (Bs)</th>
                  <th class="py-3 px-3 text-right font-bold text-[#0071E3]">Cuota Diaria (Bs)</th>
                  <th class="py-3 px-3 text-right font-bold text-[#248A3D]">Preventa Hoy (Bs)</th>
                  <th class="py-3 px-3 text-center">% Alcance</th>
                  <th class="py-3 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.03] font-num">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-[#0071E3]/[0.02]">
                  <td class="py-2.5 px-4 font-bold text-[#0071E3]">{{ r.ruta }}</td>
                  <td class="py-2.5 px-4 font-sans text-[#1D1D1F]">{{ r.vendedor }}</td>
                  <td class="py-2.5 px-3 text-right text-[#6E6E73]">Bs. {{ formatoMoneda(r.kpi3_objetivo.meta_mensual) }}</td>
                  <td class="py-2.5 px-3 text-right text-[#6E6E73]">Bs. {{ formatoMoneda(r.kpi3_objetivo.facturado_previo) }}</td>
                  <td class="py-2.5 px-3 text-right font-semibold text-[#1D1D1F]">Bs. {{ formatoMoneda(r.kpi3_objetivo.brecha) }}</td>
                  <td class="py-2.5 px-3 text-right font-bold text-[#0071E3] bg-[#0071E3]/[0.04]">Bs. {{ formatoMoneda(r.kpi3_objetivo.cuota_diaria) }}</td>
                  <td class="py-2.5 px-3 text-right font-bold text-[#248A3D] bg-[#248A3D]/[0.04]">Bs. {{ formatoMoneda(r.kpi3_objetivo.venta_hoy) }}</td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded-[6px] text-[11px] font-bold"
                      :class="r.kpi3_objetivo.alcance_pct >= 100 ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                    >
                      {{ r.kpi3_objetivo.alcance_pct }}%
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold">
                    <span :class="r.kpi3_objetivo.puntos > 0 ? 'text-[#248A3D]' : 'text-[#86868B]'">
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
          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">4. Efectividad en Ruta (30 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Umbral mínimo de calificación: <strong>55.0%</strong> de conversión sobre la cartera activa planificada del día.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">Efectivas (&ge; 55%):</span>
              <strong class="text-[#248A3D] ml-1 font-bold">{{ rutasFiltradas.filter(r => r.kpi4_efectividad.puntos > 0).length }}</strong>
              <span class="text-[#86868B] mx-1">/</span>
              <strong class="text-[#1D1D1F] font-bold">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <div class="apple-glass-card rounded-[16px] overflow-hidden">
            <table class="w-full text-left text-xs">
              <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase font-sans">
                <tr>
                  <th class="py-3 px-4">Ruta</th>
                  <th class="py-3 px-4">Preventista</th>
                  <th class="py-3 px-3 text-center">Cartera Plan</th>
                  <th class="py-3 px-3 text-center">Compradores</th>
                  <th class="py-3 px-3 text-center">% Efectividad</th>
                  <th class="py-3 px-3 text-center text-[#248A3D]">Tickets &ge; 70 Bs</th>
                  <th class="py-3 px-3 text-center text-[#C9342C]">Bajo Mínimo (&lt; 70 Bs)</th>
                  <th class="py-3 px-3 text-center font-bold">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.03] font-num">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-[#0071E3]/[0.02]">
                  <td class="py-2.5 px-4 font-bold text-[#0071E3]">{{ r.ruta }}</td>
                  <td class="py-2.5 px-4 font-sans text-[#1D1D1F]">{{ r.vendedor }}</td>
                  <td class="py-2.5 px-3 text-center">{{ r.kpi4_efectividad.cartera_plan }}</td>
                  <td class="py-2.5 px-3 text-center font-bold text-[#1D1D1F]">{{ r.kpi4_efectividad.compradores }}</td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded-[6px] text-[11px] font-bold"
                      :class="r.kpi4_efectividad.efectividad_pct >= 55 ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                    >
                      {{ r.kpi4_efectividad.efectividad_pct }}%
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold text-[#248A3D]">{{ r.kpi4_efectividad.compradores_sobre_70 }}</td>
                  <td class="py-2.5 px-3 text-center font-bold" :class="r.kpi4_efectividad.compradores_bajo_70 > 0 ? 'text-[#C9342C]' : 'text-[#86868B]'">
                    {{ r.kpi4_efectividad.compradores_bajo_70 }}
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold">
                    <span :class="r.kpi4_efectividad.puntos > 0 ? 'text-[#248A3D]' : 'text-[#86868B]'">
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
          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">5. Compra Mínima Diaria de 70 Bs (5 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Regla Estricta: El 100% de los compradores deben acumular &ge; 70.00 Bs. Si existe al menos 1 cliente con pedido &lt; 70 Bs, la ruta obtiene 0 pts.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">Conformes:</span>
              <strong class="text-[#248A3D] ml-1 font-bold">{{ rutasFiltradas.filter(r => r.kpi5_compra_minima.puntos > 0).length }}</strong>
              <span class="text-[#86868B] mx-1">/</span>
              <strong class="text-[#1D1D1F] font-bold">{{ rutasFiltradas.length }}</strong>
            </div>
          </div>

          <!-- Selector de Ruta para Auditoría por Tienda -->
          <div class="apple-glass-card p-2.5 rounded-[14px] flex items-center gap-3 text-xs">
            <span class="text-[#86868B] font-semibold">Auditar Ruta Específica:</span>
            <select
              v-model="rutaSeleccionadaDetalle"
              class="apple-control-pill px-2.5 py-1 rounded-[8px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
            >
              <option value="">Todas las Rutas (Vista Resumida)</option>
              <option v-for="r in rutasFiltradas" :key="r.ruta" :value="r.ruta">
                {{ r.ruta }} - {{ r.vendedor }} ({{ r.kpi5_compra_minima.compradores_bajo_70 }} bajo mínimo)
              </option>
            </select>
          </div>

          <!-- Tabla Resumen por Ruta si no hay ruta individual seleccionada -->
          <div v-if="!rutaSeleccionadaDetalle" class="apple-glass-card rounded-[16px] overflow-hidden">
            <table class="w-full text-left text-xs">
              <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase font-sans">
                <tr>
                  <th class="py-3 px-4">Ruta</th>
                  <th class="py-3 px-4">Preventista</th>
                  <th class="py-3 px-3 text-center">Total Compradores</th>
                  <th class="py-3 px-3 text-center text-[#C9342C]">Clientes &lt; 70 Bs</th>
                  <th class="py-3 px-3 text-center">Estado Disciplina</th>
                  <th class="py-3 px-3 text-center font-bold">Puntos</th>
                  <th class="py-3 px-3 text-center">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.03] font-num">
                <tr v-for="r in rutasFiltradas" :key="r.ruta" class="hover:bg-[#0071E3]/[0.02]">
                  <td class="py-2.5 px-4 font-bold text-[#0071E3]">{{ r.ruta }}</td>
                  <td class="py-2.5 px-4 font-sans text-[#1D1D1F]">{{ r.vendedor }}</td>
                  <td class="py-2.5 px-3 text-center font-bold">{{ r.kpi5_compra_minima.compradores }}</td>
                  <td class="py-2.5 px-3 text-center font-bold" :class="r.kpi5_compra_minima.compradores_bajo_70 > 0 ? 'text-[#C9342C] bg-[#FDF0EF]/60' : 'text-[#248A3D]'">
                    {{ r.kpi5_compra_minima.compradores_bajo_70 }}
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span
                      class="px-2.5 py-0.5 rounded-[6px] text-[10px] font-bold"
                      :class="r.kpi5_compra_minima.cumple ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FDF0EF] text-[#C9342C]'"
                    >
                      {{ r.kpi5_compra_minima.cumple ? '100% Conforme' : 'Infracción Drop Size' }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold">
                    <span :class="r.kpi5_compra_minima.puntos > 0 ? 'text-[#248A3D]' : 'text-[#86868B]'">
                      {{ r.kpi5_compra_minima.puntos }} / 5
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <button
                      type="button"
                      @click="rutaSeleccionadaDetalle = r.ruta"
                      class="apple-control-pill px-2.5 py-0.5 rounded-[6px] text-[10px] font-semibold text-[#0071E3] cursor-pointer"
                    >
                      Auditar Tiendas &rarr;
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Auditoría Desglosada Tienda por Tienda -->
          <div v-else class="apple-glass-card rounded-[16px] overflow-hidden">
            <div class="p-3 bg-white/40 border-b border-black/[0.05] flex items-center justify-between">
              <span class="font-bold text-xs text-[#1D1D1F]">
                Detalle Tienda por Tienda: Ruta {{ rutaSeleccionadaDetalle }}
              </span>
              <button @click="rutaSeleccionadaDetalle = ''" class="text-xs text-[#0071E3] font-semibold hover:underline cursor-pointer">
                &larr; Volver a todas las rutas
              </button>
            </div>
            <table class="w-full text-left text-xs">
              <thead class="border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase font-sans">
                <tr>
                  <th class="py-2.5 px-3">Cód / ID</th>
                  <th class="py-2.5 px-3">Cliente</th>
                  <th class="py-2.5 px-3">Dirección</th>
                  <th class="py-2.5 px-3 text-center">Ítems Pedidos</th>
                  <th class="py-2.5 px-3 text-right">Monto Preventa</th>
                  <th class="py-2.5 px-3 text-center">Estado Auditoría</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-black/[0.03] font-num text-[11px]">
                <tr
                  v-for="t in getDetalleTicketsRuta(rutaSeleccionadaDetalle)"
                  :key="t.cliente_id"
                  :class="t.conforme_70 ? 'hover:bg-white/40' : 'bg-[#FDF0EF]/40 font-semibold'"
                >
                  <td class="py-2 px-3">{{ t.codigo_cliente }}</td>
                  <td class="py-2 px-3 font-sans font-medium text-[#1D1D1F]">{{ t.cliente }}</td>
                  <td class="py-2 px-3 font-sans text-[#6E6E73] truncate max-w-xs">{{ t.direccion }}</td>
                  <td class="py-2 px-3 text-center">{{ t.items_count }}</td>
                  <td class="py-2 px-3 text-right font-bold" :class="t.conforme_70 ? 'text-[#248A3D]' : 'text-[#C9342C]'">
                    Bs. {{ formatoMoneda(t.monto_total) }}
                  </td>
                  <td class="py-2 px-3 text-center">
                    <span
                      class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold"
                      :class="t.conforme_70 ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FDF0EF] text-[#C9342C]'"
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
          <!-- Banner informativo de pausa actual -->
          <div class="apple-control-pill rounded-[12px] p-3 text-center text-xs text-[#6E6E73] font-medium">
            <span class="font-bold text-[#0071E3]">Fase de evaluación activa:</span>
            Actualmente se evalúa hasta <strong class="text-[#1D1D1F]">Mínimo 70 Bs</strong>. Este indicador de reposición histórica queda pausado temporalmente.
          </div>

          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4 opacity-75">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">6. Pedido de Reposición (15 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Ventana de análisis: <strong>13 semanas (90 días)</strong>. Umbral meta: <strong>60.0%</strong> de tiendas recomprando sus habituales.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">Estado:</span>
              <strong class="text-[#86868B] ml-1 font-bold">Pausado (—)</strong>
            </div>
          </div>
        </div>

        <!-- PESTAÑA 7: AMPLIACIÓN (20 PUNTOS) -->
        <div v-else-if="tabActiva === 'ampliacion'" class="space-y-4">
          <!-- Banner informativo de pausa actual -->
          <div class="apple-control-pill rounded-[12px] p-3 text-center text-xs text-[#6E6E73] font-medium">
            <span class="font-bold text-[#0071E3]">Fase de evaluación activa:</span>
            Actualmente se evalúa hasta <strong class="text-[#1D1D1F]">Mínimo 70 Bs</strong>. La ampliación de portafolio queda pausada temporalmente.
          </div>

          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4 opacity-75">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">7. Ampliación de Portafolio (20 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Regla por Tienda: Exige ordenar <strong>&ge; 2 SKUs nuevos</strong> respecto al historial de 90 días. Gatekeeper: <strong>&ge; 60.0%</strong>.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">Estado:</span>
              <strong class="text-[#86868B] ml-1 font-bold">Pausado (—)</strong>
            </div>
          </div>
        </div>

        <!-- PESTAÑA 8: COVERS (5 PUNTOS) -->
        <div v-else-if="tabActiva === 'covers'" class="space-y-4">
          <!-- Banner informativo de pausa actual -->
          <div class="apple-control-pill rounded-[12px] p-3 text-center text-xs text-[#6E6E73] font-medium">
            <span class="font-bold text-[#0071E3]">Fase de evaluación activa:</span>
            Actualmente se evalúa hasta <strong class="text-[#1D1D1F]">Mínimo 70 Bs</strong>. El indicador Covers multicategoría queda pausado temporalmente.
          </div>

          <div class="apple-glass-card p-4 rounded-[16px] flex items-center justify-between gap-4 opacity-75">
            <div>
              <h3 class="text-sm font-bold text-[#1D1D1F]">8. Covers / Multicategoría (5 Puntos)</h3>
              <p class="text-xs text-[#6E6E73] mt-0.5">
                Objetivo: Evitar tickets monoproducto. Exige <strong>&ge; 2 categorías distintas</strong> por cliente. Meta de ruta: <strong>60.0%</strong>.
              </p>
            </div>
            <div class="text-right text-xs font-num">
              <span class="text-[#86868B]">Estado:</span>
              <strong class="text-[#86868B] ml-1 font-bold">Pausado (—)</strong>
            </div>
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

// Pestañas Monográficas de Evaluación
const pestañasMonograficas = [
  { id: 'general', nombre: 'Pizarra General', puntos: '100 pts' },
  { id: 'horario', nombre: '1. Ingreso', puntos: '10 pts' },
  { id: 'visitas', nombre: '2. Visitas', puntos: '5 pts' },
  { id: 'objetivo', nombre: '3. Obj. Diario', puntos: '10 pts' },
  { id: 'efectividad', nombre: '4. Efectividad', puntos: '30 pts' },
  { id: 'compra_minima', nombre: '5. Mín. 70 Bs', puntos: '5 pts' },
  { id: 'reposicion', nombre: '6. Reposición', puntos: '15 pts' },
  { id: 'ampliacion', nombre: '7. Ampliación', puntos: '20 pts' },
  { id: 'covers', nombre: '8. Covers', puntos: '5 pts' },
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

onMounted(() => {
  cargarCumplimiento();
});
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(29, 29, 31, 0.18);
  border-radius: 9999px;
  border: 1.5px solid rgba(255, 255, 255, 0.65);
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 113, 227, 0.45);
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
</style>
