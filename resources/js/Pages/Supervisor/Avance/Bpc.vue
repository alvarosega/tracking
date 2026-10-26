<template>
  <SupervisorLayout>
    <div class="space-y-4 font-sans text-[#1D1D1F]">
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 1. Header Principal del Módulo                                  -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3]"></span>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[#1D1D1F]">
              Avance Sell Out & BPC
            </h1>
            <span class="px-2 py-0.5 rounded-[6px] bg-[#0071E3]/10 text-[#0071E3] font-mono text-[10px] font-bold border border-[#0071E3]/20">
              Beauty & Personal Care
            </span>
          </div>
          <p class="text-xs text-[#6E6E73] mt-0.5">
            Auditoría comercial BPC: Cantidad de Clientes Activos / Paretos y Sell Out por Cluster T en ventanas móviles.
          </p>
        </div>

        <!-- Acciones Rápidas (Exportar y Recargar) -->
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="exportarCsv"
            :disabled="cargando || !hayDatos"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[9px] bg-white text-[#1D1D1F] border border-black/[0.08] hover:bg-slate-50 text-xs font-semibold shadow-xs transition-all disabled:opacity-50 cursor-pointer"
            title="Exportar tablas activas a archivo CSV compatible con Excel"
          >
            <svg class="w-3.5 h-3.5 text-[#1D1D1F]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span>Exportar CSV</span>
          </button>

          <button
            type="button"
            @click="consultarBpc"
            :disabled="cargando"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[9px] bg-[#0071E3] hover:bg-[#0077ED] text-white text-xs font-semibold shadow-xs transition-all disabled:opacity-50 cursor-pointer"
          >
            <svg
              class="w-3.5 h-3.5 transition-transform"
              :class="{ 'animate-spin': cargando }"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <span>Actualizar</span>
          </button>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 2. Barra de Filtros Apple Glass                                 -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="apple-glass-card rounded-[14px] shadow-float p-3 border border-white/60 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <!-- Selector de Submódulo (Cant Clientes vs Sell Out) -->
          <div class="inline-flex bg-slate-100 p-0.5 rounded-[10px] border border-black/[0.06] shadow-2xs shrink-0">
            <button
              type="button"
              @click="cambiarSubmodulo('cant_clientes')"
              class="px-3.5 py-1.5 text-xs font-bold rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
              :class="submodulo === 'cant_clientes'
                ? 'bg-[#0071E3] text-white shadow-xs'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
              </svg>
              <span>Cantidad de Clientes</span>
            </button>

            <button
              type="button"
              @click="cambiarSubmodulo('sell_out')"
              class="px-3.5 py-1.5 text-xs font-bold rounded-[8px] transition-all cursor-pointer flex items-center gap-1.5"
              :class="submodulo === 'sell_out'
                ? 'bg-[#0071E3] text-white shadow-xs'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
              </svg>
              <span>Sell Out (Venta Bs)</span>
            </button>
          </div>

          <!-- Filtros de Navegación y Rango -->
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <!-- Selector Mes Evaluado -->
            <div class="flex items-center gap-1.5 bg-slate-100 px-2.5 py-1.5 rounded-[9px] border border-black/[0.06] shadow-2xs">
              <span class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider font-mono">Mes Evaluado:</span>
              <select
                v-model="filtroMes"
                @change="consultarBpc"
                class="bg-transparent font-semibold font-mono text-[#1D1D1F] focus:outline-none cursor-pointer text-xs"
              >
                <option v-for="m in mesesDisponibles" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>

            <!-- Dropdown Canales -->
            <div class="relative" ref="dropdownCanalesRef">
              <button
                type="button"
                @click="abrirDropdown('canales')"
                class="flex items-center gap-1.5 bg-white text-[#1D1D1F] border border-black/[0.08] text-xs font-semibold px-2.5 py-1.5 rounded-[9px] hover:bg-slate-50 cursor-pointer shadow-xs"
              >
                <span>{{ textoCanales }}</span>
                <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </button>

              <div
                v-if="dropdownAbierto === 'canales'"
                class="absolute left-0 mt-1.5 w-52 apple-glass-card bg-white/95 border border-black/[0.08] rounded-xl shadow-float p-2 z-50 space-y-1 max-h-56 overflow-y-auto custom-scroll"
              >
                <label
                  v-for="c in canalesDisponibles"
                  :key="c"
                  class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-50 rounded-lg cursor-pointer select-none text-xs"
                >
                  <input
                    type="checkbox"
                    :value="c"
                    v-model="canalesSeleccionados"
                    @change="consultarBpc"
                    class="rounded text-[#0071E3] focus:ring-0 cursor-pointer"
                  />
                  <span class="truncate font-medium">{{ c }}</span>
                </label>
              </div>
            </div>

            <!-- Dropdown Rutas -->
            <div class="relative" ref="dropdownRutasRef">
              <button
                type="button"
                @click="abrirDropdown('rutas')"
                class="flex items-center gap-1.5 bg-white text-[#1D1D1F] border border-black/[0.08] text-xs font-semibold px-2.5 py-1.5 rounded-[9px] hover:bg-slate-50 cursor-pointer shadow-xs"
              >
                <span>{{ textoRutas }}</span>
                <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </button>

              <div
                v-if="dropdownAbierto === 'rutas'"
                class="absolute left-0 mt-1.5 w-60 apple-glass-card bg-white/95 border border-black/[0.08] rounded-xl shadow-float p-2.5 z-50 space-y-2 max-h-72 overflow-y-auto custom-scroll"
              >
                <div class="flex items-center justify-between pb-1.5 border-b border-black/[0.06] text-[11px]">
                  <button
                    type="button"
                    @click="marcarTodasRutas(true)"
                    class="text-[#0071E3] font-bold hover:underline cursor-pointer"
                  >
                    Marcar Todas
                  </button>
                  <button
                    type="button"
                    @click="marcarTodasRutas(false)"
                    class="text-[#6E6E73] hover:underline cursor-pointer"
                  >
                    Desmarcar
                  </button>
                </div>
                <div class="space-y-1">
                  <label
                    v-for="r in catalogoRutas"
                    :key="r.ruta"
                    class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded-lg cursor-pointer select-none text-xs"
                  >
                    <input
                      type="checkbox"
                      :value="r.ruta"
                      v-model="rutasSeleccionadas"
                      @change="consultarBpc"
                      class="rounded text-[#0071E3] focus:ring-0 cursor-pointer"
                    />
                    <span class="truncate font-medium font-mono text-[11px]">{{ r.ruta }}</span>
                    <span class="text-[9px] text-[#86868B] ml-auto font-sans">{{ r.canal }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Dropdown Clusters -->
            <div class="relative" ref="dropdownClustersRef">
              <button
                type="button"
                @click="abrirDropdown('clusters')"
                class="flex items-center gap-1.5 bg-white text-[#1D1D1F] border border-black/[0.08] text-xs font-semibold px-2.5 py-1.5 rounded-[9px] hover:bg-slate-50 cursor-pointer shadow-xs"
              >
                <span>{{ textoClusters }}</span>
                <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </button>

              <div
                v-if="dropdownAbierto === 'clusters'"
                class="absolute left-0 mt-1.5 w-52 apple-glass-card bg-white/95 border border-black/[0.08] rounded-xl shadow-float p-2 z-50 space-y-1 max-h-56 overflow-y-auto custom-scroll"
              >
                <label
                  v-for="cl in clustersDisponibles"
                  :key="cl"
                  class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-50 rounded-lg cursor-pointer select-none text-xs"
                >
                  <input
                    type="checkbox"
                    :value="cl"
                    v-model="clustersSeleccionados"
                    @change="consultarBpc"
                    class="rounded text-[#0071E3] focus:ring-0 cursor-pointer"
                  />
                  <span class="truncate font-medium">{{ cl }}</span>
                </label>
              </div>
            </div>

            <!-- Buscador Rápido de Clusters en Tabla -->
            <div class="relative">
              <input
                type="text"
                v-model="busquedaCluster"
                placeholder="Buscar cluster..."
                class="h-8 pl-8 pr-2.5 text-xs bg-slate-100 border border-black/[0.06] rounded-[8px] text-[#1D1D1F] placeholder-[#86868B] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#0071E3] w-36 sm:w-44 transition-all"
              />
              <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2.5 top-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- KPI Summary Cards en Cabecera -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-1 border-t border-black/[0.06]">
          <div class="bg-white/80 p-2.5 rounded-[10px] border border-black/[0.06] shadow-2xs">
            <div class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">
              {{ submodulo === 'cant_clientes' ? 'Activos LM' : 'Sell Out LM' }}
            </div>
            <div class="text-base sm:text-lg font-bold font-mono text-[#0071E3] tabular-nums mt-0.5">
              {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.lm) : `Bs ${fmtBs(totalesGeneralesTotales.lm)}` }}
            </div>
            <div class="text-[9px] text-[#86868B] mt-0.5">Cartera Total en Mes LM</div>
          </div>

          <div class="bg-white/80 p-2.5 rounded-[10px] border border-black/[0.06] shadow-2xs">
            <div class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">
              {{ submodulo === 'cant_clientes' ? 'Paretos LM' : 'Sell Out Paretos LM' }}
            </div>
            <div class="text-base sm:text-lg font-bold font-mono text-[#FF9500] tabular-nums mt-0.5">
              {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesParetos.lm) : `Bs ${fmtBs(totalesGeneralesParetos.lm)}` }}
            </div>
            <div class="text-[9px] text-[#86868B] mt-0.5">Top 80% Paretos en Mes LM</div>
          </div>

          <div class="bg-white/80 p-2.5 rounded-[10px] border border-black/[0.06] shadow-2xs">
            <div class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">
              {{ submodulo === 'cant_clientes' ? 'Suma Reciente 3M' : 'Sell Out Reciente 3M' }}
            </div>
            <div class="text-base sm:text-lg font-bold font-mono text-[#1D1D1F] tabular-nums mt-0.5">
              {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.acm_3m || totalesGeneralesTotales.tot_3m) : `Bs ${fmtBs(totalesGeneralesTotales.tot_3m || totalesGeneralesTotales.acm_3m)}` }}
            </div>
            <div class="text-[9px] text-[#86868B] mt-0.5">Acumulado LM-2 + LM-1 + LM</div>
          </div>

          <div class="bg-white/80 p-2.5 rounded-[10px] border border-black/[0.06] shadow-2xs">
            <div class="text-[10px] font-bold text-[#6E6E73] uppercase tracking-wider">
              Participación Pareto LM
            </div>
            <div class="text-base sm:text-lg font-bold font-mono text-[#1E6B37] tabular-nums mt-0.5">
              {{ pctParetoLM }}%
            </div>
            <div class="text-[9px] text-[#86868B] mt-0.5">Volumen concentrado en Pareto</div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 3. Estado de Carga o Error                                      -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div v-if="errorMensaje" class="p-4 rounded-[12px] bg-rose-50 text-[#C9342C] border border-rose-200 text-xs font-semibold flex items-center justify-between shadow-xs">
        <span>{{ errorMensaje }}</span>
        <button
          type="button"
          @click="consultarBpc"
          class="px-2.5 py-1 bg-white text-[#C9342C] border border-rose-300 rounded-lg text-xs font-bold hover:bg-rose-100 transition-colors"
        >
          Reintentar
        </button>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- 4. Contenido Dual Side-by-Side (Tabla 1 y Tabla 2)              -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <!-- ── TABLA 1: TOTALES (IZQUIERDA) ── -->
        <div class="apple-glass-card rounded-[14px] shadow-float overflow-hidden border border-white/60 flex flex-col bg-white">
          <!-- Cabecera de la Tarjeta 1 -->
          <div class="px-4 py-3 bg-slate-50 border-b border-black/[0.06] flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3]"></span>
              <span class="font-bold text-[#1D1D1F] uppercase tracking-wide text-[11px]">
                {{ submodulo === 'cant_clientes' ? 'Cartera Clientes Activos Totales (BPC)' : 'Sell Out Totales BPC (Bs)' }}
              </span>
            </div>
            <span class="text-[#0071E3] font-mono font-bold text-[11px] bg-[#0071E3]/10 px-2 py-0.5 rounded-[6px] border border-[#0071E3]/20 tabular-nums">
              {{ submodulo === 'cant_clientes' ? `${fmtInt(totalesGeneralesTotales.lm)} activos LM` : `Bs ${fmtBs(totalesGeneralesTotales.lm)} LM` }}
            </span>
          </div>

          <!-- Contenedor con Scroll de la Tabla 1 -->
          <div class="overflow-x-auto max-h-[calc(100vh-320px)] min-h-[380px] custom-scroll relative">
            <table class="w-full text-left text-xs text-[#1D1D1F] border-separate border-spacing-0">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[10px] uppercase tracking-tight sticky top-0 z-20 select-none shadow-xs">
                <tr>
                  <th
                    @click="ordenarPor('cluster')"
                    class="py-2.5 px-3 border-b border-black/[0.06] min-w-[130px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span>Cluster T</span>
                    <span class="ml-1 text-[9px]">{{ iconoSort('cluster') }}</span>
                  </th>
                  <th
                    @click="ordenarPor('lm_3_4_5')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[95px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM 3-4-5</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm_3_4_5 || 'M-4 a M-6' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor('lm_2')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[85px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM -2</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm_2 || 'M-3' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor('lm_1')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[85px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM -1</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm_1 || 'M-2' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor('lm')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[85px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm || 'M-1' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor(submodulo === 'cant_clientes' ? 'acm_3m' : 'tot_3m')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[105px] bg-[#0071E3]/5 cursor-pointer text-[#0071E3]"
                  >
                    <span class="block font-bold">{{ submodulo === 'cant_clientes' ? 'ACM 3M' : 'TOT 3M' }}</span>
                    <span class="text-[9px] font-mono text-[#0071E3]/80 block font-normal normal-case">
                      {{ submodulo === 'cant_clientes' ? (etiquetasPeriodos.acm_3m || 'Suma Reciente') : (etiquetasPeriodos.tot_3m || 'Suma Reciente') }}
                    </span>
                  </th>
                </tr>
              </thead>

              <!-- Skeletons Loader Tabla 1 -->
              <tbody v-if="cargando" class="divide-y divide-black/[0.04]">
                <tr v-for="i in 8" :key="i" class="animate-pulse">
                  <td class="p-3"><div class="h-4 bg-slate-200 rounded w-24"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right bg-[#0071E3]/5"><div class="h-4 bg-blue-100 rounded w-16 ml-auto"></div></td>
                </tr>
              </tbody>

              <!-- Filas de Datos Tabla 1 -->
              <tbody v-else class="divide-y divide-black/[0.04]">
                <tr v-if="filasFiltradasTotales.length === 0">
                  <td colspan="6" class="p-8 text-center text-[#86868B] text-xs italic">
                    No se encontraron clusters activos con los filtros seleccionados.
                  </td>
                </tr>
                <tr
                  v-for="f in filasFiltradasTotales"
                  :key="f.cluster"
                  class="hover:bg-slate-50 transition-colors"
                >
                  <td class="py-2.5 px-3 font-semibold text-[#1D1D1F] truncate max-w-[150px]" :title="f.cluster">
                    {{ f.cluster }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm_3_4_5) : fmtBs(f.lm_3_4_5) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm_2) : fmtBs(f.lm_2) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm_1) : fmtBs(f.lm_1) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm) : fmtBs(f.lm) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-[#0071E3] bg-[#0071E3]/5 tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.acm_3m) : fmtBs(f.tot_3m) }}
                  </td>
                </tr>
              </tbody>

              <!-- Footer Sticky Tabla 1 -->
              <tfoot
                v-if="!cargando && filasFiltradasTotales.length > 0"
                class="bg-slate-100 border-t-2 border-black/[0.08] font-bold text-[#1D1D1F] text-xs sticky bottom-0 z-10 select-none shadow-xs"
              >
                <tr>
                  <td class="py-2.5 px-3 uppercase text-[10px] font-mono tracking-wider">TOTAL GENERAL</td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.lm_3_4_5) : fmtBs(totalesGeneralesTotales.lm_3_4_5) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.lm_2) : fmtBs(totalesGeneralesTotales.lm_2) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.lm_1) : fmtBs(totalesGeneralesTotales.lm_1) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.lm) : fmtBs(totalesGeneralesTotales.lm) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums text-[#0071E3] bg-[#0071E3]/10">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesTotales.acm_3m) : fmtBs(totalesGeneralesTotales.tot_3m) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ── TABLA 2: PARETOS (DERECHA) ── -->
        <div class="apple-glass-card rounded-[14px] shadow-float overflow-hidden border border-white/60 flex flex-col bg-white">
          <!-- Cabecera de la Tarjeta 2 -->
          <div class="px-4 py-3 bg-slate-50 border-b border-black/[0.06] flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-[#FF9500]"></span>
              <span class="font-bold text-[#1D1D1F] uppercase tracking-wide text-[11px]">
                {{ submodulo === 'cant_clientes' ? 'Cartera Clientes Activos Paretos (BPC)' : 'Sell Out Paretos BPC (Bs)' }}
              </span>
            </div>
            <span class="text-[#FF9500] font-mono font-bold text-[11px] bg-[#FF9500]/10 px-2 py-0.5 rounded-[6px] border border-[#FF9500]/20 tabular-nums">
              {{ submodulo === 'cant_clientes' ? `${fmtInt(totalesGeneralesParetos.lm)} paretos LM` : `Bs ${fmtBs(totalesGeneralesParetos.lm)} LM` }}
            </span>
          </div>

          <!-- Contenedor con Scroll de la Tabla 2 -->
          <div class="overflow-x-auto max-h-[calc(100vh-320px)] min-h-[380px] custom-scroll relative">
            <table class="w-full text-left text-xs text-[#1D1D1F] border-separate border-spacing-0">
              <thead class="bg-slate-100 text-[#6E6E73] font-bold text-[10px] uppercase tracking-tight sticky top-0 z-20 select-none shadow-xs">
                <tr>
                  <th
                    @click="ordenarPor('cluster')"
                    class="py-2.5 px-3 border-b border-black/[0.06] min-w-[130px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span>Cluster T</span>
                    <span class="ml-1 text-[9px]">{{ iconoSort('cluster') }}</span>
                  </th>
                  <th
                    @click="ordenarPor('lm_3_4_5')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[95px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM 3-4-5</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm_3_4_5 || 'M-4 a M-6' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor('lm_2')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[85px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM -2</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm_2 || 'M-3' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor('lm_1')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[85px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM -1</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm_1 || 'M-2' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor('lm')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[85px] cursor-pointer hover:text-[#1D1D1F]"
                  >
                    <span class="block">LM</span>
                    <span class="text-[9px] font-mono text-[#86868B] block font-normal normal-case">
                      ({{ etiquetasPeriodos.lm || 'M-1' }})
                    </span>
                  </th>
                  <th
                    @click="ordenarPor(submodulo === 'cant_clientes' ? 'acm_3m' : 'tot_3m')"
                    class="py-2.5 px-3 text-right border-b border-black/[0.06] min-w-[105px] bg-[#FF9500]/5 cursor-pointer text-[#FF9500]"
                  >
                    <span class="block font-bold">{{ submodulo === 'cant_clientes' ? 'ACM 3M' : 'TOT 3M' }}</span>
                    <span class="text-[9px] font-mono text-[#FF9500]/80 block font-normal normal-case">
                      {{ submodulo === 'cant_clientes' ? (etiquetasPeriodos.acm_3m || 'Suma Reciente') : (etiquetasPeriodos.tot_3m || 'Suma Reciente') }}
                    </span>
                  </th>
                </tr>
              </thead>

              <!-- Skeletons Loader Tabla 2 -->
              <tbody v-if="cargando" class="divide-y divide-black/[0.04]">
                <tr v-for="i in 8" :key="i" class="animate-pulse">
                  <td class="p-3"><div class="h-4 bg-slate-200 rounded w-24"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right"><div class="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                  <td class="p-3 text-right bg-[#FF9500]/5"><div class="h-4 bg-amber-100 rounded w-16 ml-auto"></div></td>
                </tr>
              </tbody>

              <!-- Filas de Datos Tabla 2 -->
              <tbody v-else class="divide-y divide-black/[0.04]">
                <tr v-if="filasFiltradasParetos.length === 0">
                  <td colspan="6" class="p-8 text-center text-[#86868B] text-xs italic">
                    No se encontraron clientes paretos con los filtros seleccionados.
                  </td>
                </tr>
                <tr
                  v-for="f in filasFiltradasParetos"
                  :key="f.cluster"
                  class="hover:bg-slate-50 transition-colors"
                >
                  <td class="py-2.5 px-3 font-semibold text-[#1D1D1F] truncate max-w-[150px]" :title="f.cluster">
                    {{ f.cluster }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm_3_4_5) : fmtBs(f.lm_3_4_5) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm_2) : fmtBs(f.lm_2) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm_1) : fmtBs(f.lm_1) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1D1D1F] tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.lm) : fmtBs(f.lm) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-[#FF9500] bg-[#FF9500]/5 tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(f.acm_3m) : fmtBs(f.tot_3m) }}
                  </td>
                </tr>
              </tbody>

              <!-- Footer Sticky Tabla 2 -->
              <tfoot
                v-if="!cargando && filasFiltradasParetos.length > 0"
                class="bg-slate-100 border-t-2 border-black/[0.08] font-bold text-[#1D1D1F] text-xs sticky bottom-0 z-10 select-none shadow-xs"
              >
                <tr>
                  <td class="py-2.5 px-3 uppercase text-[10px] font-mono tracking-wider">TOTAL GENERAL</td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesParetos.lm_3_4_5) : fmtBs(totalesGeneralesParetos.lm_3_4_5) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesParetos.lm_2) : fmtBs(totalesGeneralesParetos.lm_2) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesParetos.lm_1) : fmtBs(totalesGeneralesParetos.lm_1) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesParetos.lm) : fmtBs(totalesGeneralesParetos.lm) }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono tabular-nums text-[#FF9500] bg-[#FF9500]/10">
                    {{ submodulo === 'cant_clientes' ? fmtInt(totalesGeneralesParetos.acm_3m) : fmtBs(totalesGeneralesParetos.tot_3m) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  clusters: { type: Array, default: () => [] },
  meses: { type: Array, default: () => [] },
  mes_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
  fecha_inicio_default: { type: String, default: '' },
  dias_calendario: { type: Object, default: () => ({}) },
});

// Submodo activo: 'cant_clientes' | 'sell_out'
const submodulo = ref('cant_clientes');

// Filtros Reactivos
const filtroMes = ref(props.mes_default || (props.meses && props.meses[0]) || '');
const canalesSeleccionados = ref([]);
const rutasSeleccionadas = ref([]);
const clustersSeleccionados = ref([]);
const busquedaCluster = ref('');

// Estado de Dropdowns
const dropdownAbierto = ref(null);
const dropdownCanalesRef = ref(null);
const dropdownRutasRef = ref(null);
const dropdownClustersRef = ref(null);

// Estado de Consulta
const cargando = ref(false);
const errorMensaje = ref(null);
const datosClientes = ref(null);
const datosSellOut = ref(null);

// Ordenamiento
const orden = reactive({
  campo: 'cluster',
  dir: 'asc',
});

// Catálogos derivados
const mesesDisponibles = computed(() => props.meses || []);
const canalesDisponibles = computed(() => props.canales || []);
const catalogoRutas = computed(() => props.catalogo_rutas || []);
const clustersDisponibles = computed(() => props.clusters || []);

// Textos dinámicos en botones de Dropdown
const textoCanales = computed(() => {
  const tot = canalesDisponibles.value.length;
  const sel = canalesSeleccionados.value.length;
  if (sel === 0 || sel === tot) return 'Canal: TODOS';
  if (sel === 1) return `Canal: ${canalesSeleccionados.value[0]}`;
  return `Canales: (${sel})`;
});

const textoRutas = computed(() => {
  const tot = catalogoRutas.value.length;
  const sel = rutasSeleccionadas.value.length;
  if (sel === 0 || sel === tot) return 'Rutas: TODAS';
  if (sel === 1) return `Ruta: ${rutasSeleccionadas.value[0]}`;
  return `Rutas: (${sel})`;
});

const textoClusters = computed(() => {
  const tot = clustersDisponibles.value.length;
  const sel = clustersSeleccionados.value.length;
  if (sel === 0 || sel === tot) return 'Cluster: TODOS';
  if (sel === 1) return `Cluster: ${clustersSeleccionados.value[0]}`;
  return `Clusters: (${sel})`;
});

// Etiquetas de Periodos
const etiquetasPeriodos = computed(() => {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  return fuente?.periodos?.etiquetas || {};
});

// Datos Activos según Submodo
const hayDatos = computed(() => {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  return Boolean(fuente && fuente.tabla_totales?.filas?.length > 0);
});

// Filas Filtradas y Ordenadas
const filasFiltradasTotales = computed(() => {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  const filas = fuente?.tabla_totales?.filas || [];
  const q = busquedaCluster.value.trim().toLowerCase();

  const filtradas = q ? filas.filter(f => String(f.cluster || '').toLowerCase().includes(q)) : [...filas];

  return filtradas.sort((a, b) => {
    let va = a[orden.campo];
    let vb = b[orden.campo];
    const mult = orden.dir === 'asc' ? 1 : -1;

    if (typeof va === 'string' || typeof vb === 'string') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
});

const filasFiltradasParetos = computed(() => {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  const filas = fuente?.tabla_paretos?.filas || [];
  const q = busquedaCluster.value.trim().toLowerCase();

  const filtradas = q ? filas.filter(f => String(f.cluster || '').toLowerCase().includes(q)) : [...filas];

  return filtradas.sort((a, b) => {
    let va = a[orden.campo];
    let vb = b[orden.campo];
    const mult = orden.dir === 'asc' ? 1 : -1;

    if (typeof va === 'string' || typeof vb === 'string') {
      return String(va || '').localeCompare(String(vb || '')) * mult;
    }
    return ((Number(va) || 0) - (Number(vb) || 0)) * mult;
  });
});

// Totales Generales
const totalesGeneralesTotales = computed(() => {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  return fuente?.tabla_totales?.totales || {};
});

const totalesGeneralesParetos = computed(() => {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  return fuente?.tabla_paretos?.totales || {};
});

// Porcentaje de Pareto en LM
const pctParetoLM = computed(() => {
  const tot = Number(totalesGeneralesTotales.value?.lm || 0);
  const par = Number(totalesGeneralesParetos.value?.lm || 0);
  if (tot <= 0) return '0.0';
  return ((par / tot) * 100.0).toFixed(1);
});

function abrirDropdown(nombre) {
  dropdownAbierto.value = dropdownAbierto.value === nombre ? null : nombre;
}

function cerrarDropdowns(e) {
  if (
    dropdownCanalesRef.value?.contains(e.target) ||
    dropdownRutasRef.value?.contains(e.target) ||
    dropdownClustersRef.value?.contains(e.target)
  ) {
    return;
  }
  dropdownAbierto.value = null;
}

function marcarTodasRutas(marcar) {
  if (marcar) {
    rutasSeleccionadas.value = catalogoRutas.value.map(r => r.ruta);
  } else {
    rutasSeleccionadas.value = [];
  }
  consultarBpc();
}

function ordenarPor(campo) {
  if (orden.campo === campo) {
    orden.dir = orden.dir === 'asc' ? 'desc' : 'asc';
  } else {
    orden.campo = campo;
    orden.dir = campo === 'cluster' ? 'asc' : 'desc';
  }
}

function iconoSort(campo) {
  if (orden.campo !== campo) return '';
  return orden.dir === 'asc' ? '▲' : '▼';
}

function cambiarSubmodulo(nuevoSubmod) {
  if (submodulo.value === nuevoSubmod) return;
  submodulo.value = nuevoSubmod;
  consultarBpc();
}

async function consultarBpc() {
  cargando.value = true;
  errorMensaje.value = null;

  const payload = {
    mes_evaluado: filtroMes.value,
    canales: canalesSeleccionados.value,
    rutas: rutasSeleccionadas.value,
    clusters: clustersSeleccionados.value,
  };

  try {
    if (submodulo.value === 'cant_clientes') {
      const res = await axios.post('/supervisor/avance/bpc/clientes', payload);
      datosClientes.value = res.data;
    } else {
      const res = await axios.post('/supervisor/avance/bpc/sellout', payload);
      datosSellOut.value = res.data;
    }
  } catch (err) {
    console.error('Error al consultar BPC:', err);
    errorMensaje.value = err?.response?.data?.message || err?.message || 'Error al consultar datos de BPC';
  } finally {
    cargando.value = false;
  }
}

function fmtInt(val) {
  return Number(val || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

function fmtBs(val) {
  return Number(val || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

// Exportación CSV compatible con Excel (UTF-8 con BOM)
function exportarCsv() {
  const fuente = submodulo.value === 'cant_clientes' ? datosClientes.value : datosSellOut.value;
  if (!fuente) return;

  const et = etiquetasPeriodos.value;
  const col3m = submodulo.value === 'cant_clientes' ? 'acm_3m' : 'tot_3m';
  const label3m = submodulo.value === 'cant_clientes' ? (et.acm_3m || 'ACM 3M') : (et.tot_3m || 'TOT 3M');

  let csv = '\uFEFF'; // UTF-8 BOM
  csv += `Módulo Avance BPC - ${submodulo.value === 'cant_clientes' ? 'Cantidad de Clientes Activos' : 'Sell Out en Bs'}\r\n`;
  csv += `Mes Evaluado: ${filtroMes.value}\r\n\r\n`;

  // 1. Tabla Totales
  csv += `--- ${fuente.tabla_totales?.titulo || 'Totales BPC'} ---\r\n`;
  csv += `Cluster;LM 3-4-5 (${et.lm_3_4_5 || 'M-4 a M-6'});LM -2 (${et.lm_2 || 'M-3'});LM -1 (${et.lm_1 || 'M-2'});LM (${et.lm || 'M-1'});${label3m}\r\n`;

  (fuente.tabla_totales?.filas || []).forEach(f => {
    csv += `"${f.cluster}";${f.lm_3_4_5};${f.lm_2};${f.lm_1};${f.lm};${f[col3m]}\r\n`;
  });

  const totT = fuente.tabla_totales?.totales || {};
  csv += `"TOTAL GENERAL";${totT.lm_3_4_5};${totT.lm_2};${totT.lm_1};${totT.lm};${totT[col3m]}\r\n\r\n`;

  // 2. Tabla Paretos
  csv += `--- ${fuente.tabla_paretos?.titulo || 'Paretos BPC'} ---\r\n`;
  csv += `Cluster;LM 3-4-5 (${et.lm_3_4_5 || 'M-4 a M-6'});LM -2 (${et.lm_2 || 'M-3'});LM -1 (${et.lm_1 || 'M-2'});LM (${et.lm || 'M-1'});${label3m}\r\n`;

  (fuente.tabla_paretos?.filas || []).forEach(f => {
    csv += `"${f.cluster}";${f.lm_3_4_5};${f.lm_2};${f.lm_1};${f.lm};${f[col3m]}\r\n`;
  });

  const totP = fuente.tabla_paretos?.totales || {};
  csv += `"TOTAL GENERAL";${totP.lm_3_4_5};${totP.lm_2};${totP.lm_1};${totP.lm};${totP[col3m]}\r\n`;

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `bpc_${submodulo.value}_${filtroMes.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

onMounted(async () => {
  window.addEventListener('click', cerrarDropdowns);
  await consultarBpc();
});

onUnmounted(() => {
  window.removeEventListener('click', cerrarDropdowns);
});
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.25);
}
</style>

