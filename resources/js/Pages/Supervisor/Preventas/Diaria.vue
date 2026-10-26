<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden font-sans text-[#1D1D1F] space-y-2.5 p-3 sm:p-4 select-none">
      
      <!-- ==========================================
           1. BARRA SUPERIOR DE FILTROS & NAVEGACIÓN (APPLE GLASS)
           ========================================== -->
      <header class="apple-glass-card rounded-[16px] p-2 sm:p-2.5 border border-white/60 flex flex-wrap items-center justify-between gap-2 shrink-0 z-30 shadow-xs">
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
          
          <!-- SubNavTabs (Cumplimiento, Diaria, Productos, Auditoría, Comparativos, Terreno) -->
          <SubNavTabs :tabs="preventasSubTabs" currentTab="diaria" />

          <div class="h-5 w-px bg-black/[0.08] hidden sm:block shrink-0"></div>

          <!-- Selector de Modo de Vista: Matriz vs Secuencia & Mapa -->
          <div class="inline-flex bg-[#F5F5F7] p-0.5 rounded-[10px] border border-black/[0.06] text-[11px] font-bold">
            <button
              type="button"
              @click="vistaPrincipal = 'matriz'"
              class="px-3 py-1 rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
              :class="vistaPrincipal === 'matriz' ? 'bg-white text-[#1D1D1F] shadow-xs border border-black/[0.04]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
              <span>Matriz Diaria</span>
            </button>

            <button
              type="button"
              @click="vistaPrincipal = 'secuencia'"
              class="px-3 py-1 rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
              :class="vistaPrincipal === 'secuencia' ? 'bg-white text-[#0071E3] shadow-xs border border-black/[0.04]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
              </svg>
              <span>Secuencia & Mapa GIS</span>
            </button>
          </div>

          <div class="h-5 w-px bg-black/[0.08] hidden sm:block shrink-0"></div>

          <!-- Selector de Fecha -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Fecha:</span>
            <input
              type="date"
              v-model="fechaSeleccionada"
              @change="cargarDashboardDiario"
              class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-num"
            />
          </div>

          <!-- Selector de Canal -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <select
              v-model="canalSeleccionado"
              @change="alCambiarCanal"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer"
            >
              <option v-for="c in canalesDisponibles" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Selector de Ruta Activa (Visible en modo secuencia) -->
          <div v-if="vistaPrincipal === 'secuencia'" class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Ruta:</span>
            <select
              v-model="rutaActiva"
              @change="cargarSecuenciaRuta(rutaActiva)"
              class="bg-transparent text-[11px] font-bold text-[#0071E3] focus:outline-none cursor-pointer min-w-[100px]"
            >
              <option v-for="r in rutasFiltradas" :key="r.ruta" :value="r.ruta">
                {{ r.ruta }} - {{ r.vendedor }}
              </option>
            </select>
          </div>

          <!-- Botón Consultar Principal -->
          <button
            type="button"
            @click="cargarDashboardDiario"
            :disabled="cargandoDashboard || cargandoDetalle"
            class="flex items-center gap-1.5 px-3 py-1 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed select-none shrink-0"
          >
            <svg
              v-if="cargandoDashboard || cargandoDetalle"
              class="w-3.5 h-3.5 animate-spin text-white shrink-0"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <svg
              v-else
              class="w-3.5 h-3.5 text-white shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>{{ (cargandoDashboard || cargandoDetalle) ? 'Consultando...' : 'Consultar' }}</span>
          </button>

          <!-- Exportar CSV -->
          <button
            type="button"
            @click="exportarDatosCsv"
            :disabled="cargandoDashboard || rutasFiltradas.length === 0"
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-[10px] text-[11px] font-semibold text-[#1D1D1F] bg-white hover:bg-slate-50 border border-[#E5E5EA] shadow-xs cursor-pointer transition-colors"
            title="Exportar datos a CSV compatible con Excel"
          >
            <svg class="w-3.5 h-3.5 text-[#1D1D1F]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span class="hidden sm:inline">Exportar</span>
          </button>
        </div>

        <!-- Información de Cabecera (Fecha y Resumen Ruta) -->
        <div class="flex items-center gap-2.5 text-[11px] font-mono text-[#6E6E73]">
          <span v-if="diaSemana" class="font-bold text-[#1D1D1F]">
            {{ diaSemana }} · {{ fechaDisplay }}
          </span>
          <span v-if="infoRutaActual && vistaPrincipal === 'secuencia'" class="hidden md:inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] bg-black/[0.04] text-[10px]">
            <span>Cartera: <strong>{{ infoRutaActual.plan_visita }}</strong></span>
            <span>·</span>
            <span>Preventa: <strong class="text-emerald-700">{{ infoRutaActual.preventa }}</strong></span>
            <span>·</span>
            <span>Total: <strong class="text-[#0071E3]">{{ fmtBs(infoRutaActual.volumen_bs) }}</strong></span>
          </span>
        </div>
      </header>

      <!-- ==========================================
           2. VISTA 1: MATRIZ DE PREVENTA DIARIA
           ========================================== -->
      <template v-if="vistaPrincipal === 'matriz'">
        <!-- Tarjetas de Métricas Consolidadas del Día -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
          <!-- KPI 1: Volumen Hoy vs Objetivo Día -->
          <div class="apple-glass-card rounded-[14px] p-3 border border-white/60 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Volumen vs Objetivo</span>
              <span
                class="text-[10px] font-bold font-mono px-1.5 py-0.2 rounded"
                :class="claseBadgePorcentaje(kpisConsolidados.cumplimiento_dia_pct)"
              >
                {{ kpisConsolidados.cumplimiento_dia_pct.toFixed(1) }}%
              </span>
            </div>
            <div class="text-base sm:text-lg font-bold font-num text-[#1D1D1F] mt-1">
              {{ fmtBs(kpisConsolidados.volumen_bs) }}
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5 flex items-center justify-between font-mono">
              <span>Obj: {{ fmtBs(kpisConsolidados.objetivo_dia_bs) }}</span>
            </div>
          </div>

          <!-- KPI 2: Preventa y Drop Size -->
          <div class="apple-glass-card rounded-[14px] p-3 border border-white/60 shadow-xs">
            <div class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Preventas / Drop Size</div>
            <div class="text-base sm:text-lg font-bold font-num text-[#0071E3] mt-1">
              {{ kpisConsolidados.preventa.toLocaleString('es-BO') }} <span class="text-xs text-[#6E6E73] font-normal">pedidos</span>
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5 font-mono">
              Drop Size: <strong class="text-[#1D1D1F]">{{ fmtBs(kpisConsolidados.drop_size) }}</strong>
            </div>
          </div>

          <!-- KPI 3: Cobertura de Visitas -->
          <div class="apple-glass-card rounded-[14px] p-3 border border-white/60 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Cobertura de Visitas</span>
              <span
                class="text-[10px] font-bold font-mono px-1.5 py-0.2 rounded"
                :class="claseBadgePorcentaje(kpisConsolidados.cobertura_pct)"
              >
                {{ kpisConsolidados.cobertura_pct.toFixed(1) }}%
              </span>
            </div>
            <div class="text-base sm:text-lg font-bold font-num text-emerald-700 mt-1">
              {{ kpisConsolidados.visitas_plan_total.toLocaleString('es-BO') }} <span class="text-xs text-[#6E6E73] font-normal">/ {{ kpisConsolidados.plan_total.toLocaleString('es-BO') }}</span>
            </div>
            <div class="text-[10px] text-[#6E6E73] mt-0.5 font-mono">
              Fuera de plan: <strong class="text-amber-700">{{ kpisConsolidados.visitas_extra_total.toLocaleString('es-BO') }}</strong>
            </div>
          </div>

          <!-- KPI 4: Efectividad Comercial -->
          <div class="apple-glass-card rounded-[14px] p-3 border border-white/60 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Efectividad Comercial</span>
              <span
                class="text-[10px] font-bold font-mono px-1.5 py-0.2 rounded"
                :class="claseBadgePorcentaje(kpisConsolidados.efectividad_pct)"
              >
                {{ kpisConsolidados.efectividad_pct.toFixed(1) }}%
              </span>
            </div>
            <div class="text-base sm:text-lg font-bold font-num text-[#1D1D1F] mt-1">
              {{ kpisConsolidados.preventa.toLocaleString('es-BO') }} <span class="text-xs text-[#6E6E73] font-normal">clientes con compra</span>
            </div>
            <div class="text-[10px] text-[#86868B] mt-0.5">
              Sobre total cartera asignada
            </div>
          </div>
        </div>

        <!-- Tabla Matriz de Preventa Diaria -->
        <section class="flex-1 apple-glass-card rounded-[16px] border border-white/60 flex flex-col overflow-hidden shadow-glass-card min-h-0">
          <!-- Cabecera de la Matriz con Switch y Buscador -->
          <div class="px-4 py-3 bg-[#F5F5F7]/80 backdrop-blur-md border-b border-[#E5E5EA] flex flex-wrap justify-between items-center gap-3 shrink-0">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">
                {{ vistaMatriz === 'visitas' ? 'Matriz de Disciplina Operativa & Visitas' : 'Matriz de Volumen & Cumplimiento Diario' }}
              </span>
              <span class="text-[11px] text-[#6E6E73] font-medium hidden md:inline">
                · Haz clic en cualquier ruta para abrir su secuencia y trazado en mapa
              </span>
            </div>

            <div class="flex items-center gap-2.5">
              <!-- Switch de Sub-matrices (Visitas vs Volumen) -->
              <div class="inline-flex bg-[#E5E5EA]/70 p-0.5 rounded-[8px] border border-[#E5E5EA] text-[11px] font-bold">
                <button
                  type="button"
                  @click="vistaMatriz = 'visitas'"
                  class="px-3 py-1 rounded-[6px] transition-all cursor-pointer"
                  :class="vistaMatriz === 'visitas' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  Visitas & Cobertura
                </button>
                <button
                  type="button"
                  @click="vistaMatriz = 'volumen'"
                  class="px-3 py-1 rounded-[6px] transition-all cursor-pointer"
                  :class="vistaMatriz === 'volumen' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                >
                  Volumen & Venta
                </button>
              </div>

              <!-- Buscador Rápido -->
              <div class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1">
                <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                  v-model="busquedaMatriz"
                  type="text"
                  placeholder="Buscar ruta o preventista..."
                  class="bg-transparent text-[11px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-36 sm:w-48 font-medium"
                />
              </div>
            </div>
          </div>

          <!-- Contenedor Scrollable de la Matriz -->
          <div class="flex-1 overflow-auto custom-scroll relative">
            <table class="w-full text-left text-[11px] sm:text-[12px] text-[#1D1D1F] border-separate border-spacing-0 font-num">
              <!-- THEAD: VISITAS & COBERTURA -->
              <thead v-if="vistaMatriz === 'visitas'" class="bg-[#F5F5F7] text-[#1D1D1F] font-bold uppercase text-[10px] tracking-tight sticky top-0 z-20 border-b border-[#E5E5EA] select-none">
                <tr>
                  <th @click="ordenarMatrizPor('ruta')" class="p-2.5 hover:bg-slate-200 cursor-pointer sticky left-0 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA]">
                    Ruta ⇕
                  </th>
                  <th @click="ordenarMatrizPor('canal')" class="p-2.5 hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA] text-center">
                    Canal ⇕
                  </th>
                  <th @click="ordenarMatrizPor('vendedor')" class="p-2.5 hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Preventista ⇕
                  </th>
                  <th @click="ordenarMatrizPor('plan_visita')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Plan Visita ⇕
                  </th>
                  <th @click="ordenarMatrizPor('visitas_plan')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Visitas en Plan ⇕
                  </th>
                  <th @click="ordenarMatrizPor('visitas_extra')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA] bg-amber-50/60">
                    Fuera de Plan ⇕
                  </th>
                  <th @click="ordenarMatrizPor('total_visitados')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Total Visitados ⇕
                  </th>
                  <th @click="ordenarMatrizPor('preventa')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Preventa ⇕
                  </th>
                  <th @click="ordenarMatrizPor('efectividad_pct')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Efectividad ⇕
                  </th>
                  <th class="p-2.5 text-center border-b border-[#E5E5EA] w-28">
                    Acción
                  </th>
                </tr>
              </thead>

              <!-- THEAD: VOLUMEN & VENTA -->
              <thead v-else class="bg-[#F5F5F7] text-[#1D1D1F] font-bold uppercase text-[10px] tracking-tight sticky top-0 z-20 border-b border-[#E5E5EA] select-none">
                <tr>
                  <th @click="ordenarMatrizPor('ruta')" class="p-2.5 hover:bg-slate-200 cursor-pointer sticky left-0 z-30 bg-[#F5F5F7] border-b border-r border-[#E5E5EA]">
                    Ruta ⇕
                  </th>
                  <th @click="ordenarMatrizPor('canal')" class="p-2.5 hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA] text-center">
                    Canal ⇕
                  </th>
                  <th @click="ordenarMatrizPor('vendedor')" class="p-2.5 hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Preventista ⇕
                  </th>
                  <th @click="ordenarMatrizPor('plan_visita')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Plan Visita ⇕
                  </th>
                  <th @click="ordenarMatrizPor('preventa')" class="p-2.5 text-center hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Preventa ⇕
                  </th>
                  <th @click="ordenarMatrizPor('volumen_bs')" class="p-2.5 text-right hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Volumen Hoy ⇕
                  </th>
                  <th @click="ordenarMatrizPor('drop_size')" class="p-2.5 text-right hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Drop Size ⇕
                  </th>
                  <th @click="ordenarMatrizPor('objetivo_dia_bs')" class="p-2.5 text-right hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA] bg-blue-50/50 text-[#0071E3]">
                    Objetivo Día ⇕
                  </th>
                  <th @click="ordenarMatrizPor('cumplimiento_dia_pct')" class="p-2.5 text-right hover:bg-slate-200 cursor-pointer border-b border-r border-[#E5E5EA]">
                    Cumpl. Día ⇕
                  </th>
                  <th class="p-2.5 text-center border-b border-[#E5E5EA] w-28">
                    Acción
                  </th>
                </tr>
              </thead>

              <!-- TBODY DE LA MATRIZ -->
              <tbody class="divide-y divide-[#E5E5EA] bg-white text-[11px]">
                <tr v-if="cargandoDashboard">
                  <td colspan="10" class="p-12 text-center font-medium text-[#86868B]">
                    <div class="flex items-center justify-center gap-2">
                      <svg class="w-4 h-4 animate-spin text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                      </svg>
                      <span>Cargando matriz de preventa diaria...</span>
                    </div>
                  </td>
                </tr>

                <tr v-else-if="rutasFiltradas.length === 0">
                  <td colspan="10" class="p-12 text-center font-medium text-[#86868B]">
                    No hay rutas visibles con los filtros seleccionados.
                  </td>
                </tr>

                <tr
                  v-else
                  v-for="r in rutasFiltradas"
                  :key="r.ruta"
                  @click="abrirSecuenciaDeRuta(r.ruta)"
                  class="hover:bg-slate-50 cursor-pointer transition-colors group"
                >
                  <!-- Columna Fija: Ruta -->
                  <td class="p-2.5 font-bold font-mono text-[#0071E3] sticky left-0 z-10 bg-white group-hover:bg-slate-50 border-r border-[#E5E5EA]">
                    {{ r.ruta }}
                  </td>

                  <!-- Canal -->
                  <td class="p-2.5 text-center font-bold text-[10px] text-[#6E6E73] border-r border-[#E5E5EA]">
                    <span class="px-1.5 py-0.5 rounded bg-[#F5F5F7] border border-[#E5E5EA]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Preventista -->
                  <td class="p-2.5 font-medium text-[#1D1D1F] truncate max-w-[170px] border-r border-[#E5E5EA]" :title="r.vendedor">
                    {{ r.vendedor }}
                  </td>

                  <!-- CELDAS EN MODO VISITAS & COBERTURA -->
                  <template v-if="vistaMatriz === 'visitas'">
                    <td class="p-2.5 text-center font-num text-[#1D1D1F] border-r border-[#E5E5EA]">
                      {{ r.plan_visita }}
                    </td>
                    <td
                      class="p-2.5 text-center font-num border-r border-[#E5E5EA]"
                      :class="r.alerta_cobertura ? 'bg-red-50 text-red-700 font-bold' : 'text-[#1D1D1F]'"
                    >
                      {{ r.visitas_plan }}
                    </td>
                    <td
                      class="p-2.5 text-center font-num border-r border-[#E5E5EA]"
                      :class="r.visitas_extra > 0 ? 'text-amber-800 bg-amber-50/50 font-bold' : 'text-[#86868B]'"
                    >
                      {{ r.visitas_extra }}
                    </td>
                    <td class="p-2.5 text-center font-bold font-num text-[#1D1D1F] border-r border-[#E5E5EA]">
                      {{ r.total_visitados }}
                    </td>
                    <td class="p-2.5 text-center font-bold font-num text-emerald-700 border-r border-[#E5E5EA]">
                      {{ r.preventa }}
                    </td>
                    <td class="p-2.5 text-center font-num border-r border-[#E5E5EA]">
                      <span
                        class="px-2 py-0.5 rounded font-bold text-[10px]"
                        :class="claseBadgePorcentaje(r.efectividad_pct)"
                      >
                        {{ Number(r.efectividad_pct).toFixed(1) }}%
                      </span>
                    </td>
                  </template>

                  <!-- CELDAS EN MODO VOLUMEN & VENTA -->
                  <template v-else>
                    <td class="p-2.5 text-center font-num text-[#1D1D1F] border-r border-[#E5E5EA]">
                      {{ r.plan_visita }}
                    </td>
                    <td class="p-2.5 text-center font-bold font-num text-emerald-700 border-r border-[#E5E5EA]">
                      {{ r.preventa }}
                    </td>
                    <td class="p-2.5 text-right font-bold font-num text-[#1D1D1F] border-r border-[#E5E5EA]">
                      {{ fmtBs(r.volumen_bs) }}
                    </td>
                    <td class="p-2.5 text-right font-num text-[#6E6E73] border-r border-[#E5E5EA]">
                      {{ fmtBs(r.drop_size) }}
                    </td>
                    <td class="p-2.5 text-right font-num font-bold text-[#0071E3] bg-blue-50/30 border-r border-[#E5E5EA]">
                      {{ fmtBs(r.objetivo_dia_bs) }}
                    </td>
                    <td class="p-2.5 text-right font-num border-r border-[#E5E5EA]">
                      <span
                        class="px-2 py-0.5 rounded font-bold text-[10px]"
                        :class="claseBadgePorcentaje(r.cumplimiento_dia_pct)"
                      >
                        {{ Number(r.cumplimiento_dia_pct).toFixed(1) }}%
                      </span>
                    </td>
                  </template>

                  <!-- Botón Acción Ver Recorrido -->
                  <td class="p-2.5 text-center" @click.stop="abrirSecuenciaDeRuta(r.ruta)">
                    <button
                      type="button"
                      class="px-2.5 py-1 rounded-[6px] bg-[#0071E3]/10 hover:bg-[#0071E3] text-[#0071E3] hover:text-white text-[10px] font-bold transition-all cursor-pointer shadow-xs"
                    >
                      Ver Recorrido ➔
                    </button>
                  </td>
                </tr>
              </tbody>

              <!-- TFOOT: TOTALES CONSOLIDADOS DINÁMICOS -->
              <tfoot v-if="rutasFiltradas.length > 0" class="bg-[#F5F5F7] border-t-2 border-[#E5E5EA] font-bold text-[#1D1D1F] select-none text-[11px] sticky bottom-0 z-20">
                <tr v-if="vistaMatriz === 'visitas'">
                  <td colspan="3" class="p-2.5 border-r border-[#E5E5EA] uppercase tracking-wider text-[#1D1D1F]">
                    Total Consolidado ({{ rutasFiltradas.length }} rutas)
                  </td>
                  <td class="p-2.5 text-center border-r border-[#E5E5EA]">{{ totalesFiltrados.plan_visita }}</td>
                  <td class="p-2.5 text-center border-r border-[#E5E5EA]">{{ totalesFiltrados.visitas_plan }}</td>
                  <td class="p-2.5 text-center text-amber-900 bg-amber-50 border-r border-[#E5E5EA]">{{ totalesFiltrados.visitas_extra }}</td>
                  <td class="p-2.5 text-center border-r border-[#E5E5EA]">{{ totalesFiltrados.total_visitados }}</td>
                  <td class="p-2.5 text-center text-emerald-700 border-r border-[#E5E5EA]">{{ totalesFiltrados.preventa }}</td>
                  <td class="p-2.5 text-center border-r border-[#E5E5EA]">
                    <span class="px-2 py-0.5 rounded font-bold text-[10px]" :class="claseBadgePorcentaje(totalesFiltrados.efectividad_pct)">
                      {{ totalesFiltrados.efectividad_pct.toFixed(1) }}%
                    </span>
                  </td>
                  <td class="p-2.5"></td>
                </tr>

                <tr v-else>
                  <td colspan="3" class="p-2.5 border-r border-[#E5E5EA] uppercase tracking-wider text-[#1D1D1F]">
                    Total Consolidado ({{ rutasFiltradas.length }} rutas)
                  </td>
                  <td class="p-2.5 text-center border-r border-[#E5E5EA]">{{ totalesFiltrados.plan_visita }}</td>
                  <td class="p-2.5 text-center text-emerald-700 border-r border-[#E5E5EA]">{{ totalesFiltrados.preventa }}</td>
                  <td class="p-2.5 text-right border-r border-[#E5E5EA]">{{ fmtBs(totalesFiltrados.volumen_bs) }}</td>
                  <td class="p-2.5 text-right text-[#6E6E73] border-r border-[#E5E5EA]">{{ fmtBs(totalesFiltrados.drop_size) }}</td>
                  <td class="p-2.5 text-right text-[#0071E3] bg-blue-50/50 border-r border-[#E5E5EA]">{{ fmtBs(totalesFiltrados.objetivo_dia_bs) }}</td>
                  <td class="p-2.5 text-right border-r border-[#E5E5EA]">
                    <span class="px-2 py-0.5 rounded font-bold text-[10px]" :class="claseBadgePorcentaje(totalesFiltrados.cumplimiento_dia_pct)">
                      {{ totalesFiltrados.cumplimiento_dia_pct.toFixed(1) }}%
                    </span>
                  </td>
                  <td class="p-2.5"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>
      </template>

      <!-- ==========================================
           3. VISTA 2: SECUENCIA & MAPA GIS DE RUTA (SPLIT SCREEN)
           ========================================== -->
      <template v-else-if="vistaPrincipal === 'secuencia'">
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-3 min-h-0 overflow-hidden">
          
          <!-- PANEL IZQUIERDO: MAPA INTERACTIVO LEAFLET -->
          <section class="lg:col-span-6 apple-glass-card rounded-[16px] border border-white/60 flex flex-col h-full overflow-hidden relative shadow-glass-card">
            <!-- Barra de Controles de Recorrido -->
            <div class="p-2 sm:p-2.5 bg-white/70 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 z-20 shrink-0">
              <div class="inline-flex items-center bg-black/[0.05] rounded-[9px] p-0.5 border border-black/[0.06] text-[10px]">
                <button
                  type="button"
                  @click="irAParadaIndice(0)"
                  :disabled="paradasConCoordenadas.length === 0"
                  class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#1D1D1F] disabled:opacity-40 cursor-pointer transition-colors"
                >
                  Inicio
                </button>
                <button
                  type="button"
                  @click="navegarPaso(-1)"
                  :disabled="paradasConCoordenadas.length === 0"
                  class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#1D1D1F] disabled:opacity-40 cursor-pointer transition-colors"
                >
                  Anterior
                </button>
                <span class="px-2 font-mono font-bold text-[#0071E3]">
                  {{ indicePasoActual !== null ? `Parada ${indicePasoActual + 1}` : 'Vista Completa' }}
                </span>
                <button
                  type="button"
                  @click="navegarPaso(1)"
                  :disabled="paradasConCoordenadas.length === 0"
                  class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#1D1D1F] disabled:opacity-40 cursor-pointer transition-colors"
                >
                  Siguiente
                </button>
                <button
                  type="button"
                  @click="ajustarVistaCompleta"
                  class="px-2 py-1 rounded-[7px] hover:bg-white font-bold text-[#0071E3] cursor-pointer transition-colors"
                >
                  Ver todo
                </button>
              </div>

              <!-- Toggles de Visualización y Volver -->
              <div class="flex items-center gap-1.5 text-[10px]">
                <label class="apple-control-pill px-2 py-1 rounded-[8px] flex items-center gap-1 cursor-pointer font-semibold text-[#1D1D1F]">
                  <input v-model="mostrarLineaRecorrido" type="checkbox" @change="renderizarMapa(false)" class="rounded text-[#0071E3] focus:ring-0 w-3 h-3" />
                  <span>Secuencia</span>
                </label>

                <label class="apple-control-pill px-2 py-1 rounded-[8px] flex items-center gap-1 cursor-pointer font-semibold text-rose-700">
                  <input v-model="mostrarNoVisitadosMapa" type="checkbox" @change="renderizarMapa(false)" class="rounded text-rose-600 focus:ring-0 w-3 h-3" />
                  <span>No visitados ({{ noVisitadosConCoords.length }})</span>
                </label>

                <button
                  type="button"
                  @click="vistaPrincipal = 'matriz'"
                  class="px-2.5 py-1 rounded-[8px] bg-slate-100 hover:bg-slate-200 text-[#1D1D1F] font-bold transition-colors cursor-pointer border border-[#E5E5EA]"
                >
                  ◂ Volver
                </button>
              </div>
            </div>

            <!-- Leyenda de Estados en Mapa -->
            <div class="px-3 py-1.5 bg-[#F5F5F7]/90 border-b border-black/[0.05] flex flex-wrap items-center gap-3 text-[10px] font-semibold text-[#6E6E73] z-10 shrink-0">
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-600 inline-block"></span> Con Preventa</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span> Sin Venta</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-600 inline-block"></span> Desvío</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-cyan-600 inline-block"></span> Venta Sin Visita</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-600 inline-block"></span> No Visitado</span>
            </div>

            <!-- Contenedor Leaflet -->
            <div ref="mapaContainerRef" class="w-full flex-1 bg-[#F5F5F7] relative z-0"></div>
          </section>

          <!-- PANEL DERECHO: TABLA DE SECUENCIA E ITINERARIO -->
          <section class="lg:col-span-6 apple-glass-card rounded-[16px] border border-white/60 flex flex-col h-full overflow-hidden shadow-glass-card">
            <!-- Controles Superiores de la Tabla -->
            <div class="p-2 sm:p-2.5 bg-white/70 backdrop-blur-md border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 shrink-0">
              <!-- Segmented Control de Modos de Lista -->
              <div class="inline-flex apple-segmented p-0.5 rounded-[9px] text-[10px]">
                <button
                  type="button"
                  @click="modoLista = 'recorrido'"
                  class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                  :class="modoLista === 'recorrido' ? 'apple-segmented-active text-[#0071E3]' : 'text-[#6E6E73]'"
                >
                  Recorrido ({{ paradasRecorrido.length }})
                </button>
                <button
                  type="button"
                  @click="modoLista = 'no_visitados'"
                  class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                  :class="modoLista === 'no_visitados' ? 'apple-segmented-active text-rose-600' : 'text-[#6E6E73]'"
                >
                  No Visitados ({{ clientesNoVisitados.length }})
                </button>
                <button
                  type="button"
                  @click="modoLista = 'todos'"
                  class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer font-bold"
                  :class="modoLista === 'todos' ? 'apple-segmented-active text-[#1D1D1F]' : 'text-[#6E6E73]'"
                >
                  Todos ({{ todosLosClientes.length }})
                </button>
              </div>

              <!-- Buscador + Selector de Filtro de Estado -->
              <div class="flex items-center gap-1.5 flex-1 min-w-[200px] justify-end">
                <div class="flex items-center gap-1.5 apple-control-pill rounded-[9px] px-2.5 py-1 flex-1 max-w-[210px]">
                  <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <input
                    v-model="busquedaCliente"
                    type="text"
                    placeholder="Buscar cliente..."
                    class="bg-transparent text-[11px] font-medium text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-full"
                  />
                </div>

                <select
                  v-model="filtroEstado"
                  class="apple-control-pill rounded-[9px] px-2 py-1 text-[10px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer"
                >
                  <option value="TODOS">Todos</option>
                  <option value="CON_VENTA">Con Preventa</option>
                  <option value="SIN_VENTA">Sin Venta</option>
                  <option value="DESVIOS">Desvíos</option>
                  <option value="NO_VISITADO">No Visitados</option>
                </select>
              </div>
            </div>

            <!-- Tabla Scrollable con Filas de Paradas -->
            <div ref="contenedorListaRef" class="flex-1 overflow-auto custom-scroll">
              <div v-if="cargandoDetalle" class="py-20 text-center text-[#6E6E73] text-xs font-semibold">
                <div class="w-6 h-6 mx-auto mb-2 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin"></div>
                Cargando secuencia de ruta...
              </div>

              <div v-else-if="listaVisible.length === 0" class="py-20 text-center text-[#86868B] text-xs font-medium">
                Sin registros para el filtro seleccionado.
              </div>

              <table v-else class="w-full text-left border-collapse table-apple">
                <thead>
                  <tr class="bg-[#F5F5F7]/95 text-[#6E6E73] text-[10px] font-semibold uppercase tracking-wider border-b border-black/[0.08] sticky top-0 z-10 backdrop-blur-md">
                    <th class="p-2 text-center w-11">#</th>
                    <th class="p-2">Cliente</th>
                    <th class="p-2 text-center">Hora</th>
                    <th class="p-2 text-center">PDV</th>
                    <th class="p-2 text-center">Estado</th>
                    <th class="p-2 text-right">Preventa</th>
                    <th class="p-2 text-center">Fotos</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-black/[0.04] text-[11px]">
                  <tr
                    v-for="item in listaVisible"
                    :key="item._uid"
                    :id="`parada-card-${item._uid}`"
                    @click="enfocarParada(item)"
                    class="transition-colors cursor-pointer"
                    :class="
                      paradaSeleccionadaUid === item._uid
                        ? 'bg-blue-50/90 font-semibold'
                        : 'hover:bg-black/[0.02] bg-white/50'
                    "
                  >
                    <!-- # Secuencia -->
                    <td class="p-2 text-center">
                      <span
                        class="w-6 h-6 rounded-full inline-flex items-center justify-center font-black text-[10px] font-num"
                        :style="estiloCirculoParada(item)"
                      >
                        {{ item.ordenSecuencia ? item.ordenSecuencia : '-' }}
                      </span>
                    </td>

                    <!-- Cliente y Dirección -->
                    <td class="p-2 max-w-[210px]">
                      <div class="font-bold text-[#1D1D1F] truncate" :title="item.cliente">
                        {{ item.cliente }}
                      </div>
                      <div class="text-[10px] text-[#6E6E73] truncate" :title="item.direccion">
                        {{ item.direccion || 'Sin dirección' }}
                      </div>
                    </td>

                    <!-- Hora -->
                    <td class="p-2 text-center font-mono text-[10px] whitespace-nowrap">
                      <span v-if="item.hora_secuencia" class="font-bold text-[#0071E3]">
                        {{ item.hora_secuencia }}
                      </span>
                      <span v-else class="text-[#86868B]">-</span>
                    </td>

                    <!-- Tiempo PDV -->
                    <td class="p-2 text-center font-mono text-[10px] text-[#6E6E73] whitespace-nowrap">
                      {{ item.tiempo_pdv || '-' }}
                    </td>

                    <!-- Estado Operativo -->
                    <td class="p-2 text-center whitespace-nowrap">
                      <span
                        class="px-1.5 py-0.5 rounded-[5px] text-[9px] font-black border"
                        :class="claseBadgeEstado(item.estado_operativo)"
                      >
                        {{ textoBadgeEstado(item.estado_operativo) }}
                      </span>
                    </td>

                    <!-- Preventa Bs -->
                    <td class="p-2 text-right font-num whitespace-nowrap">
                      <span v-if="Number(item.monto_preventa) > 0" class="font-black text-emerald-700">
                        {{ fmtBs(item.monto_preventa) }}
                      </span>
                      <span v-else class="text-[#86868B] text-[10px]">-</span>
                    </td>

                    <!-- Fotos -->
                    <td class="p-2 text-center whitespace-nowrap">
                      <button
                        v-if="item.fotos && item.fotos.length > 0"
                        type="button"
                        @click.stop="abrirFotosCliente(item)"
                        class="px-2 py-0.5 rounded bg-blue-100 hover:bg-blue-200 text-blue-900 border border-blue-300 text-[10px] font-bold cursor-pointer transition-colors"
                      >
                        Ver ({{ item.fotos.length }})
                      </button>
                      <span v-else class="text-[#86868B] text-[10px]">-</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

        </div>
      </template>

      <!-- Modal de Evidencias Fotográficas -->
      <ClienteFotosModal
        :show="modalFotos.abierto"
        :cliente="modalFotos.cliente"
        @close="modalFotos.abierto = false"
      />

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import ClienteFotosModal from './ClienteFotosModal.vue';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_default: { type: String, default: () => new Date().toISOString().split('T')[0] },
});

// Pestañas Submódulos Preventas
const preventasSubTabs = [
  { id: 'cumplimiento', name: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
  { id: 'diaria', name: 'Preventa Diaria', route: 'supervisor.preventas.diaria' },
  { id: 'productos', name: 'Productos', route: 'supervisor.preventas.productos' },
  { id: 'auditoria', name: 'Auditoría', route: 'supervisor.preventas.auditoria' },
  { id: 'comparativos', name: 'Comparativos', route: 'supervisor.preventas.comparativos' },
  { id: 'mapa', name: 'Auditoría en Terreno', route: 'supervisor.preventas.index' },
];

// Estado Principal
const vistaPrincipal = ref('matriz'); // 'matriz' | 'secuencia'
const vistaMatriz = ref('visitas'); // 'visitas' | 'volumen'
const busquedaMatriz = ref('');
const columnaOrdenMatriz = ref('efectividad_pct');
const ordenAscendenteMatriz = ref(false);

const fechaSeleccionada = ref(props.fecha_default);
const fechaDisplay = ref('');
const diaSemana = ref('');
const canalesDisponibles = ref(['TODOS', ...props.canales]);
const canalSeleccionado = ref('TDB');
const matrizRutas = ref([]);
const rutaActiva = ref('');

const cargandoDashboard = ref(false);
const cargandoDetalle = ref(false);

// Métricas Consolidadas del Header
const kpisConsolidados = ref({
  volumen_bs: 0,
  objetivo_dia_bs: 0,
  cumplimiento_dia_pct: 0,
  preventa: 0,
  drop_size: 0,
  plan_total: 0,
  visitas_plan_total: 0,
  visitas_extra_total: 0,
  total_visitados: 0,
  cobertura_pct: 0,
  efectividad_pct: 0,
});

// Detalle de Paradas de la Ruta Activa
const detalleRuta = ref({
  ruta: '',
  vendedor: '',
  clientes: [],
});

// Filtros y Visualización de la Secuencia
const modoLista = ref('recorrido'); // 'recorrido' | 'no_visitados' | 'todos'
const filtroEstado = ref('TODOS');
const busquedaCliente = ref('');
const paradaSeleccionadaUid = ref(null);
const indicePasoActual = ref(null);
const mostrarLineaRecorrido = ref(true);
const mostrarNoVisitadosMapa = ref(true);

// Referencias DOM
const mapaContainerRef = ref(null);
const contenedorListaRef = ref(null);

// Modal Fotos
const modalFotos = ref({
  abierto: false,
  cliente: null,
});

// Instancias Leaflet
let mapaInstance = null;
let layerGroup = null;
const marcadoresPorUid = new Map();

// Formateador Moneda
function fmtBs(v) {
  const n = Number(v) || 0;
  return `Bs ${n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// ── Computed Properties Matriz ──────────────────────────────────────────────
const rutasFiltradas = computed(() => {
  let res = matrizRutas.value;

  // Filtro por canal
  if (canalSeleccionado.value && canalSeleccionado.value !== 'TODOS') {
    res = res.filter(r => r.canal === canalSeleccionado.value);
  }

  // Filtro por búsqueda de texto
  if (busquedaMatriz.value.trim() !== '') {
    const q = busquedaMatriz.value.toLowerCase().trim();
    res = res.filter(r =>
      (r.ruta && r.ruta.toLowerCase().includes(q)) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(q))
    );
  }

  // Ordenamiento
  const col = columnaOrdenMatriz.value;
  const asc = ordenAscendenteMatriz.value;

  return [...res].sort((a, b) => {
    let vA = a[col] !== undefined ? a[col] : 0;
    let vB = b[col] !== undefined ? b[col] : 0;
    if (typeof vA === 'string') vA = vA.toLowerCase();
    if (typeof vB === 'string') vB = vB.toLowerCase();
    if (vA < vB) return asc ? -1 : 1;
    if (vA > vB) return asc ? 1 : -1;
    return 0;
  });
});

const totalesFiltrados = computed(() => {
  const rutas = rutasFiltradas.value;
  let plan_visita = 0;
  let visitas_plan = 0;
  let visitas_extra = 0;
  let total_visitados = 0;
  let preventa = 0;
  let volumen_bs = 0;
  let objetivo_dia_bs = 0;

  rutas.forEach(r => {
    plan_visita += Number(r.plan_visita || 0);
    visitas_plan += Number(r.visitas_plan || 0);
    visitas_extra += Number(r.visitas_extra || 0);
    total_visitados += Number(r.total_visitados || 0);
    preventa += Number(r.preventa || 0);
    volumen_bs += Number(r.volumen_bs || 0);
    objetivo_dia_bs += Number(r.objetivo_dia_bs || 0);
  });

  const efectividad_pct = plan_visita > 0 ? (preventa / plan_visita) * 100 : 0;
  const cobertura_pct = plan_visita > 0 ? (visitas_plan / plan_visita) * 100 : 0;
  const drop_size = preventa > 0 ? volumen_bs / preventa : 0;
  const cumplimiento_dia_pct = objetivo_dia_bs > 0 ? (volumen_bs / objetivo_dia_bs) * 100 : 0;

  return {
    plan_visita,
    visitas_plan,
    visitas_extra,
    total_visitados,
    preventa,
    volumen_bs,
    objetivo_dia_bs,
    efectividad_pct,
    cobertura_pct,
    drop_size,
    cumplimiento_dia_pct,
  };
});

function ordenarMatrizPor(columna) {
  if (columnaOrdenMatriz.value === columna) {
    ordenAscendenteMatriz.value = !ordenAscendenteMatriz.value;
  } else {
    columnaOrdenMatriz.value = columna;
    ordenAscendenteMatriz.value = false;
  }
}

function claseBadgePorcentaje(pct) {
  const n = Number(pct) || 0;
  if (n >= 100) return 'text-blue-900 bg-blue-100 border border-blue-300';
  if (n >= 70) return 'text-emerald-900 bg-emerald-100 border border-emerald-300';
  if (n >= 50) return 'text-amber-900 bg-amber-100 border border-amber-300';
  return 'text-rose-900 bg-rose-100 border border-rose-300';
}

function abrirSecuenciaDeRuta(ruta) {
  rutaActiva.value = ruta;
  vistaPrincipal.value = 'secuencia';
  cargarSecuenciaRuta(ruta);
}

// ── Computed Properties Secuencia y Recorrido ──────────────────────────────
const infoRutaActual = computed(() => {
  return matrizRutas.value.find(r => r.ruta === rutaActiva.value) || null;
});

const clientesProcesados = computed(() => {
  const lista = detalleRuta.value?.clientes || [];
  return lista.map((c, index) => {
    const orden = c.fue_visitado ? index + 1 : null;
    const uid = `${c.cliente_id || 'sn'}-${index}-${c.estado_operativo || 'gen'}`;
    return {
      ...c,
      _uid: uid,
      ordenSecuencia: orden,
      hora_secuencia: c.hora_secuencia || c.check_in || c.hora_app || '',
    };
  });
});

const paradasRecorrido = computed(() => {
  return clientesProcesados.value.filter(c => Boolean(c.fue_visitado));
});

const clientesNoVisitados = computed(() => {
  return clientesProcesados.value.filter(c => !c.fue_visitado);
});

const todosLosClientes = computed(() => {
  return clientesProcesados.value;
});

const paradasConCoordenadas = computed(() => {
  return paradasRecorrido.value.filter(c => {
    const lat = Number(c.latitud);
    const lng = Number(c.longitud);
    return !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;
  });
});

const noVisitadosConCoords = computed(() => {
  return clientesNoVisitados.value.filter(c => {
    const lat = Number(c.latitud);
    const lng = Number(c.longitud);
    return !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;
  });
});

const listaVisible = computed(() => {
  let base = [];
  if (modoLista.value === 'recorrido') {
    base = paradasRecorrido.value;
  } else if (modoLista.value === 'no_visitados') {
    base = clientesNoVisitados.value;
  } else {
    base = todosLosClientes.value;
  }

  if (filtroEstado.value !== 'TODOS') {
    base = base.filter(c => {
      switch (filtroEstado.value) {
        case 'CON_VENTA':
          return c.estado_operativo === 'PLAN_CON_VENTA' || c.estado_operativo === 'VENTA_SIN_VISITA';
        case 'SIN_VENTA':
          return c.estado_operativo === 'PLAN_SIN_VENTA';
        case 'DESVIOS':
          return c.estado_operativo === 'DESVIO_DIA' || c.estado_operativo === 'DESVIO_RUTA' || c.estado_operativo === 'CLIENTE_NUEVO';
        case 'NO_VISITADO':
          return c.estado_operativo === 'PLAN_NO_VISITADO';
        default:
          return true;
      }
    });
  }

  if (busquedaCliente.value.trim() !== '') {
    const q = busquedaCliente.value.toLowerCase().trim();
    base = base.filter(c => {
      const cli = (c.cliente || '').toLowerCase();
      const dir = (c.direccion || '').toLowerCase();
      const cod = String(c.cliente_id || '');
      return cli.includes(q) || dir.includes(q) || cod.includes(q);
    });
  }

  return base;
});

// Colores de Marcador por Estado
function colorHexEstado(est) {
  switch (est) {
    case 'PLAN_CON_VENTA':
      return '#059669'; // Emerald
    case 'VENTA_SIN_VISITA':
      return '#0891b2'; // Cyan
    case 'PLAN_SIN_VENTA':
      return '#d97706'; // Amber
    case 'DESVIO_DIA':
    case 'DESVIO_RUTA':
    case 'CLIENTE_NUEVO':
      return '#4f46e5'; // Indigo
    case 'PLAN_NO_VISITADO':
    default:
      return '#e11d48'; // Rose
  }
}

function estiloCirculoParada(item) {
  const bg = colorHexEstado(item.estado_operativo);
  return {
    backgroundColor: bg,
    color: '#ffffff',
    border: '2px solid #ffffff',
    boxShadow: '0 2px 5px rgba(0,0,0,0.18)',
  };
}

function claseBadgeEstado(est) {
  switch (est) {
    case 'PLAN_CON_VENTA':
      return 'bg-emerald-100 text-emerald-950 border-emerald-300';
    case 'VENTA_SIN_VISITA':
      return 'bg-cyan-100 text-cyan-950 border-cyan-300';
    case 'PLAN_SIN_VENTA':
      return 'bg-amber-100 text-amber-950 border-amber-300';
    case 'PLAN_NO_VISITADO':
      return 'bg-rose-100 text-rose-950 border-rose-300';
    case 'DESVIO_DIA':
      return 'bg-indigo-100 text-indigo-950 border-indigo-300';
    case 'DESVIO_RUTA':
      return 'bg-purple-100 text-purple-950 border-purple-300';
    case 'CLIENTE_NUEVO':
      return 'bg-teal-100 text-teal-950 border-teal-300';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-300';
  }
}

function textoBadgeEstado(est) {
  switch (est) {
    case 'PLAN_CON_VENTA':
      return 'CON PREVENTA';
    case 'VENTA_SIN_VISITA':
      return 'VENTA SIN VISITA';
    case 'PLAN_SIN_VENTA':
      return 'SIN VENTA';
    case 'PLAN_NO_VISITADO':
      return 'NO VISITADO';
    case 'DESVIO_DIA':
      return 'DESVÍO DÍA';
    case 'DESVIO_RUTA':
      return 'DESVÍO RUTA';
    case 'CLIENTE_NUEVO':
      return 'CLIENTE NUEVO';
    default:
      return est || 'NORMAL';
  }
}

// ── Carga de Datos desde API ────────────────────────────────────────────────
async function cargarDashboardDiario() {
  cargandoDashboard.value = true;
  try {
    const payload = {
      fecha: fechaSeleccionada.value,
      canal: canalSeleccionado.value,
    };

    const res = await axios.post('/supervisor/preventas/diaria/data', payload);
    const data = res.data;

    if (data.fecha_seleccionada) {
      fechaSeleccionada.value = data.fecha_seleccionada;
    }
    fechaDisplay.value = data.fecha_display || data.fecha_seleccionada || '';
    diaSemana.value = data.dia_semana || '';

    if (Array.isArray(data.canales_disponibles) && data.canales_disponibles.length > 0) {
      canalesDisponibles.value = data.canales_disponibles;
    }

    if (data.kpis) {
      kpisConsolidados.value = data.kpis;
    }

    matrizRutas.value = data.matriz_rutas || [];

    // Ajustar ruta activa si no está definida o ya no existe
    const disponibles = rutasFiltradas.value;
    if (disponibles.length > 0) {
      const existeActual = disponibles.some(r => r.ruta === rutaActiva.value);
      if (!existeActual) {
        const conActividad = disponibles.find(r => Number(r.total_visitados) > 0 || Number(r.preventa) > 0);
        rutaActiva.value = conActividad ? conActividad.ruta : disponibles[0].ruta;
      }
      if (vistaPrincipal.value === 'secuencia') {
        await cargarSecuenciaRuta(rutaActiva.value);
      }
    }
  } catch (err) {
    console.error('Error al cargar dashboard de preventa diaria:', err);
  } finally {
    cargandoDashboard.value = false;
  }
}

function alCambiarCanal() {
  const disponibles = rutasFiltradas.value;
  if (disponibles.length > 0) {
    const conActividad = disponibles.find(r => Number(r.total_visitados) > 0 || Number(r.preventa) > 0);
    rutaActiva.value = conActividad ? conActividad.ruta : disponibles[0].ruta;
    if (vistaPrincipal.value === 'secuencia') {
      cargarSecuenciaRuta(rutaActiva.value);
    }
  }
}

async function cargarSecuenciaRuta(ruta) {
  if (!ruta) return;
  cargandoDetalle.value = true;
  paradaSeleccionadaUid.value = null;
  indicePasoActual.value = null;

  try {
    const res = await axios.post(`/supervisor/preventas/diaria/ruta/${encodeURIComponent(ruta)}`, {
      fecha: fechaSeleccionada.value,
    });
    detalleRuta.value = res.data || { ruta, vendedor: '', clientes: [] };
    await nextTick();
    if (vistaPrincipal.value === 'secuencia') {
      renderizarMapa(true);
    }
  } catch (err) {
    console.error('Error al cargar secuencia de ruta:', err);
  } finally {
    cargandoDetalle.value = false;
  }
}

// ── Leaflet Mapa Functions ──────────────────────────────────────────────────
function asegurarMapa() {
  if (mapaInstance || !mapaContainerRef.value) return;
  mapaInstance = L.map(mapaContainerRef.value, {
    zoomControl: true,
  }).setView([-17.7833, -63.1821], 13); // Santa Cruz de la Sierra

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    subdomains: 'abcd',
  }).addTo(mapaInstance);

  layerGroup = L.layerGroup().addTo(mapaInstance);
}

function renderizarMapa(fitBoundsAuto = false) {
  asegurarMapa();
  if (!mapaInstance || !layerGroup) return;

  layerGroup.clearLayers();
  marcadoresPorUid.clear();

  const coordsRecorrido = [];
  const boundsPoints = [];

  // 1. Clientes No Visitados (marcadores pequeños rojos)
  if (mostrarNoVisitadosMapa.value) {
    noVisitadosConCoords.value.forEach(c => {
      const lat = Number(c.latitud);
      const lng = Number(c.longitud);
      boundsPoints.push([lat, lng]);

      const iconHtml = `
        <div style="background:#e11d48;color:#fff;border:2px solid #fff;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;box-shadow:0 2px 5px rgba(0,0,0,0.28);opacity:0.88;">
        </div>
      `;
      const marker = L.marker([lat, lng], {
        icon: L.divIcon({
          html: iconHtml,
          className: '',
          iconSize: [18, 18],
          iconAnchor: [9, 9],
        }),
      });

      marker.bindPopup(crearPopupHtml(c));
      marker.on('click', () => {
        paradaSeleccionadaUid.value = c._uid;
        scrollHaciaTarjeta(c._uid);
      });
      marker.addTo(layerGroup);
      marcadoresPorUid.set(c._uid, marker);
    });
  }

  // 2. Recorrido real 1..N
  paradasConCoordenadas.value.forEach(c => {
    const lat = Number(c.latitud);
    const lng = Number(c.longitud);
    coordsRecorrido.push([lat, lng]);
    boundsPoints.push([lat, lng]);

    const bg = colorHexEstado(c.estado_operativo);
    const esSeleccionado = paradaSeleccionadaUid.value === c._uid;
    const size = esSeleccionado ? 30 : 25;

    const iconHtml = `
      <div style="background:${bg};color:#fff;border:2.5px solid #fff;width:${size}px;height:${size}px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;box-shadow:0 3px 8px rgba(0,0,0,0.35);">
        ${c.ordenSecuencia}
      </div>
    `;
    const marker = L.marker([lat, lng], {
      zIndexOffset: 500 + (c.ordenSecuencia || 0),
      icon: L.divIcon({
        html: iconHtml,
        className: '',
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
      }),
    });

    marker.bindPopup(crearPopupHtml(c));
    marker.on('click', () => {
      paradaSeleccionadaUid.value = c._uid;
      scrollHaciaTarjeta(c._uid);
    });
    marker.addTo(layerGroup);
    marcadoresPorUid.set(c._uid, marker);
  });

  // 3. Polilínea de secuencia 1..N
  if (mostrarLineaRecorrido.value && coordsRecorrido.length > 1) {
    L.polyline(coordsRecorrido, {
      color: '#ffffff',
      weight: 6,
      opacity: 0.75,
      lineJoin: 'round',
    }).addTo(layerGroup);

    L.polyline(coordsRecorrido, {
      color: '#0071E3',
      weight: 3.5,
      opacity: 0.92,
      dashArray: '8, 6',
      lineJoin: 'round',
    }).addTo(layerGroup);
  }

  if (fitBoundsAuto && boundsPoints.length > 0) {
    mapaInstance.fitBounds(L.latLngBounds(boundsPoints), {
      padding: [40, 40],
      maxZoom: 16,
    });
  }
}

function crearPopupHtml(c) {
  const badgeClase = claseBadgeEstado(c.estado_operativo);
  const badgeTexto = textoBadgeEstado(c.estado_operativo);

  return `
    <div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; min-width: 190px; padding: 2px;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
        <span style="background: ${colorHexEstado(c.estado_operativo)}; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 10px;">
          # ${c.ordenSecuencia || '-'}
        </span>
        <span class="${badgeClase}" style="padding: 1px 5px; border-radius: 4px; font-size: 9px; font-weight: bold;">
          ${badgeTexto}
        </span>
      </div>
      <div style="font-weight: 800; color: #1D1D1F; font-size: 12px; margin-bottom: 2px;">${c.cliente}</div>
      <div style="color: #6E6E73; font-size: 10px;">${c.direccion || 'Sin dirección'}</div>
      <div style="margin-top: 4px; padding-top: 4px; border-top: 1px solid rgba(0,0,0,0.06); display: flex; justify-content: space-between; font-size: 10px;">
        <span>Hora: <strong>${c.hora_secuencia || '-'}</strong></span>
        <span>Preventa: <strong style="color: #059669;">${fmtBs(c.monto_preventa)}</strong></span>
      </div>
    </div>
  `;
}

function ajustarVistaCompleta() {
  if (!mapaInstance) return;
  const bounds = L.latLngBounds([]);
  [...paradasConCoordenadas.value, ...noVisitadosConCoords.value].forEach(c => {
    bounds.extend([Number(c.latitud), Number(c.longitud)]);
  });
  if (bounds.isValid()) {
    mapaInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
  }
  indicePasoActual.value = null;
}

function irAParadaIndice(idx) {
  const lista = paradasConCoordenadas.value;
  if (!lista || lista.length === 0) return;
  if (idx < 0 || idx >= lista.length) return;

  indicePasoActual.value = idx;
  const target = lista[idx];
  enfocarParada(target);
}

function navegarPaso(delta) {
  const lista = paradasConCoordenadas.value;
  if (!lista || lista.length === 0) return;

  let siguiente = (indicePasoActual.value !== null ? indicePasoActual.value : -1) + delta;
  if (siguiente < 0) siguiente = 0;
  if (siguiente >= lista.length) siguiente = lista.length - 1;

  irAParadaIndice(siguiente);
}

function enfocarParada(item) {
  paradaSeleccionadaUid.value = item._uid;
  scrollHaciaTarjeta(item._uid);

  if (!item.latitud || !item.longitud || !mapaInstance) return;

  mapaInstance.flyTo([Number(item.latitud), Number(item.longitud)], 16, {
    duration: 0.8,
  });

  const marker = marcadoresPorUid.get(item._uid);
  if (marker) {
    marker.openPopup();
  }
}

function scrollHaciaTarjeta(uid) {
  nextTick(() => {
    const el = document.getElementById(`parada-card-${uid}`);
    if (el && contenedorListaRef.value) {
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  });
}

function abrirFotosCliente(item) {
  modalFotos.value = {
    abierto: true,
    cliente: {
      codigo_cliente: item.cliente_id,
      cliente: item.cliente,
      direccion: item.direccion,
      tipo_negocio: item.tipo_negocio,
      fotos: item.fotos || [],
    },
  };
}

// ── Exportación a CSV ───────────────────────────────────────────────────────
function exportarDatosCsv() {
  if (vistaPrincipal.value === 'matriz') {
    exportarMatrizCsv();
  } else {
    exportarSecuenciaCsv();
  }
}

function exportarMatrizCsv() {
  const esVisitas = vistaMatriz.value === 'visitas';
  const headers = esVisitas
    ? ['Ruta', 'Canal', 'Preventista', 'Plan Visita', 'Visitas Plan', 'Visitas Extra', 'Total Visitados', 'Preventa', 'Efectividad %']
    : ['Ruta', 'Canal', 'Preventista', 'Plan Visita', 'Preventa', 'Volumen Hoy (Bs)', 'Drop Size (Bs)', 'Objetivo Día (Bs)', 'Cumplimiento Día %'];

  const rows = [headers];

  rutasFiltradas.value.forEach(r => {
    if (esVisitas) {
      rows.push([
        r.ruta, r.canal, r.vendedor, r.plan_visita, r.visitas_plan, r.visitas_extra,
        r.total_visitados, r.preventa, `${Number(r.efectividad_pct).toFixed(1)}%`
      ]);
    } else {
      rows.push([
        r.ruta, r.canal, r.vendedor, r.plan_visita, r.preventa, r.volumen_bs,
        r.drop_size, r.objetivo_dia_bs, `${Number(r.cumplimiento_dia_pct).toFixed(1)}%`
      ]);
    }
  });

  descargarArchivoCsv(rows, `preventa_diaria_matriz_${fechaSeleccionada.value}.csv`);
}

function exportarSecuenciaCsv() {
  const headers = ['# Secuencia', 'ID Cliente', 'Cliente', 'Dirección', 'Tipo Negocio', 'Hora', 'Tiempo PDV', 'Estado Operativo', 'Preventa (Bs)', 'Latitud', 'Longitud'];
  const rows = [headers];

  clientesProcesados.value.forEach(c => {
    rows.push([
      c.ordenSecuencia || '-',
      c.cliente_id || '-',
      c.cliente,
      c.direccion || '',
      c.tipo_negocio || '',
      c.hora_secuencia || '',
      c.tiempo_pdv || '',
      textoBadgeEstado(c.estado_operativo),
      c.monto_preventa || 0,
      c.latitud || 0,
      c.longitud || 0,
    ]);
  });

  descargarArchivoCsv(rows, `secuencia_${rutaActiva.value}_${fechaSeleccionada.value}.csv`);
}

function descargarArchivoCsv(rows, nombreArchivo) {
  const csvContent = '\uFEFF' + rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', nombreArchivo);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// ── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  await cargarDashboardDiario();
});

onBeforeUnmount(() => {
  if (mapaInstance) {
    mapaInstance.remove();
    mapaInstance = null;
  }
});
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
