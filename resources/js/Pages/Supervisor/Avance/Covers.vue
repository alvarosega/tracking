<template>
  <SupervisorLayout title="Avance > Acumulado Covers">
    <div class="space-y-2.5 font-sans select-none">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── Barra Superior de Filtros Transversales ── -->
      <AvanceFiltrosBar @consultar="consultarCovers" />

      <!-- ── Tarjetas Resumen MTD (Apple Glass) ── -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
        <!-- Card 1: Rutas Activas -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Rutas Activas</span>
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-[#1D1D1F] font-mono">{{ totales.total_rutas || 0 }}</span>
            <span class="text-[10px] text-[#86868B]">rutas</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">{{ totales.dias_activos_promedio || 0 }} días prom.</span>
        </div>

        <!-- Card 2: Clientes Compradores -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Compradores MTD</span>
            <span class="w-2 h-2 rounded-full bg-slate-500"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-[#1D1D1F] font-mono">{{ fmt(totales.clientes_total) }}</span>
            <span class="text-[10px] text-[#86868B]">PDVs</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">Cartera compradora</span>
        </div>

        <!-- Card 3: Clientes con Cover (Tasa Cobertura) -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between" :class="totales.cobertura_pct >= 50 ? 'border-emerald-500/20 bg-emerald-50/10' : ''">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Con Cover MTD</span>
            <span class="w-2 h-2 rounded-full" :class="totales.cobertura_pct >= 50 ? 'bg-emerald-500' : 'bg-amber-500'"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold font-mono" :class="totales.cobertura_pct >= 50 ? 'text-emerald-700' : 'text-amber-700'">
              {{ fmtPct(totales.cobertura_pct) }}
            </span>
            <span class="text-[10px] text-[#86868B]">({{ fmt(totales.clientes_con_cover) }})</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">Meta &ge; 50.0%</span>
        </div>

        <!-- Card 4: Cover 1 (Sedal 340ml) -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#0071E3] uppercase tracking-wider">Cover 1: Sedal 340</span>
            <span class="px-1 py-0.2 rounded text-[8.5px] font-bold bg-blue-100/70 text-[#0071E3]">C1</span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-[#0071E3] font-mono">{{ fmt(totales.cover1_count) }}</span>
            <span class="text-[10px] text-[#86868B]">tiendas</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">&ge;3 Sedal 340ml</span>
        </div>

        <!-- Card 5: Cover 2 (Pepsodent) -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-indigo-600 uppercase tracking-wider">Cover 2: Pepsodent</span>
            <span class="px-1 py-0.2 rounded text-[8.5px] font-bold bg-indigo-100/70 text-indigo-700">C2</span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-indigo-600 font-mono">{{ fmt(totales.cover2_count) }}</span>
            <span class="text-[10px] text-[#86868B]">tiendas</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">&ge;3 Pepsodent 90/130g</span>
        </div>

        <!-- Card 6: Cover 3 (Sedal 40ml 2x12) -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-emerald-600 uppercase tracking-wider">Cover 3: Sedal 40ml</span>
            <span class="px-1 py-0.2 rounded text-[8.5px] font-bold bg-emerald-100/70 text-emerald-700">C3</span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-emerald-600 font-mono">{{ fmt(totales.cover3_count) }}</span>
            <span class="text-[10px] text-[#86868B]">tiendas</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">2 bonif. x 12 Sedal 40ml</span>
        </div>

        <!-- Card 7: Inversión en Descuentos -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Inversión Promo</span>
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-sm font-bold text-[#1D1D1F] font-mono">{{ fmtBs(totales.descuento_total) }}</span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">Descuentos acumulados</span>
        </div>
      </div>

      <!-- ── Barra de Control y Filtros de la Matriz ── -->
      <div class="apple-glass-card rounded-[14px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Buscador rápido de Rutas y Vendedores -->
          <div class="relative w-44 sm:w-56">
            <input
              v-model="filtroTexto"
              type="text"
              placeholder="Buscar ruta o vendedor..."
              class="w-full pl-7 pr-2.5 py-1 text-xs rounded-[8px] bg-black/[0.04] border-0 text-[#1D1D1F] placeholder-[#86868B] focus:ring-1 focus:ring-[#0071E3] focus:bg-white transition-all font-medium"
            />
            <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-1.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Filtro por Estado de Cumplimiento -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[9px] text-[11px] font-semibold">
            <button
              type="button"
              @click="filtroEstado = 'todos'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="filtroEstado === 'todos' ? 'bg-white text-[#1D1D1F] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Todas ({{ rutasConsolidadas.length }})
            </button>
            <button
              type="button"
              @click="filtroEstado = 'cumplen'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="filtroEstado === 'cumplen' ? 'bg-white text-emerald-700 font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Cumplen MTD &ge; 50%
            </button>
            <button
              type="button"
              @click="filtroEstado = 'bajo'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="filtroEstado === 'bajo' ? 'bg-white text-rose-700 font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Bajo Umbral &lt; 50%
            </button>
          </div>
        </div>

        <!-- Acciones: Copiar Tabla y Exportar Excel -->
        <div class="flex items-center gap-1.5 ml-auto">
          <button
            type="button"
            @click="copiarTablaAlPortapapeles"
            :disabled="rutasFiltradas.length === 0"
            class="flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-[8px] transition-all cursor-pointer shadow-xs border whitespace-nowrap"
            :class="copiadoExitoso
              ? 'bg-[#EBF9EF] text-[#248A3D] border-[#34C759]/40 font-bold'
              : 'border-[#E5E5EA] bg-white text-[#1D1D1F] hover:bg-[#F5F5F7] disabled:opacity-50'"
            title="Copiar matriz visible al portapapeles"
          >
            <svg v-if="!copiadoExitoso" class="w-3.5 h-3.5 text-[#6E6E73]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3" />
            </svg>
            <svg v-else class="w-3.5 h-3.5 text-[#248A3D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ copiadoExitoso ? '¡Copiado!' : 'Copiar' }}</span>
          </button>

          <button
            type="button"
            @click="exportarExcel"
            :disabled="rutasFiltradas.length === 0"
            class="flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-[8px] transition-all cursor-pointer shadow-xs border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 disabled:opacity-50 whitespace-nowrap"
            title="Descargar matriz consolidada en formato Excel (.xlsx)"
          >
            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Excel</span>
          </button>
        </div>
      </div>

      <!-- ── SKELETON LOADER ── -->
      <div v-if="cargando" class="apple-glass-card rounded-[14px] p-6 animate-pulse space-y-3">
        <div class="h-6 bg-black/[0.05] rounded-md w-1/4"></div>
        <div class="h-10 bg-black/[0.04] rounded-md"></div>
        <div class="h-10 bg-black/[0.04] rounded-md"></div>
        <div class="h-10 bg-black/[0.04] rounded-md"></div>
      </div>

      <!-- ── MATRIZ PRINCIPAL DE RUTAS ── -->
      <div v-else class="apple-glass-card rounded-[14px] overflow-hidden shadow-xs border border-white/60">
        <div class="overflow-x-auto custom-scroll">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-[#F8F8FA] border-b border-black/[0.06] text-[10.5px] font-bold text-[#86868B] uppercase tracking-wider select-none">
                <th class="py-2.5 px-3 whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('ruta')">
                  <div class="flex items-center gap-1">
                    <span>Ruta / Vendedor</span>
                    <span v-if="columnaOrden === 'ruta'">{{ ordenAsc ? '▲' : '▼' }}</span>
                  </div>
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('canal')">
                  Canal
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('clientes_total')">
                  Compradores
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap text-[#0071E3] bg-blue-50/50 cursor-pointer hover:text-blue-800" @click="ordenarPor('cover1_count')">
                  Cover 1 (Sedal 340)
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap text-indigo-700 bg-indigo-50/50 cursor-pointer hover:text-indigo-900" @click="ordenarPor('cover2_count')">
                  Cover 2 (Pepsodent)
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap text-emerald-700 bg-emerald-50/50 cursor-pointer hover:text-emerald-900" @click="ordenarPor('cover3_count')">
                  Cover 3 (Sedal 40)
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap font-extrabold text-[#1D1D1F] cursor-pointer" @click="ordenarPor('clientes_con_cover')">
                  Con Cover
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap text-[#86868B] cursor-pointer" @click="ordenarPor('clientes_sin_cover')">
                  Sin Cover
                </th>
                <th class="py-2.5 px-3 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('cobertura_pct')">
                  % Cobertura MTD
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('dias_activos')">
                  Días Act.
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('dias_cumplidos')">
                  Días Cumple
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('pct_dias_cumplidos')">
                  % Cumplimiento
                </th>
                <th class="py-2.5 px-3 text-right whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('descuento_total')">
                  Inversión (Bs)
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap">
                  Detalle
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <template v-for="r in rutasFiltradas" :key="r.ruta">
                <!-- Fila Principal de la Ruta -->
                <tr
                  class="transition-colors hover:bg-black/[0.02]"
                  :class="estaExpandida(r.ruta) ? 'bg-blue-50/20' : ''"
                >
                  <!-- Ruta y Vendedor -->
                  <td class="py-2 px-3 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                      <button
                        type="button"
                        @click="toggleExpandir(r.ruta)"
                        class="p-1 rounded hover:bg-black/[0.06] transition-transform text-[#6E6E73] cursor-pointer shrink-0"
                        :class="estaExpandida(r.ruta) ? 'rotate-90 text-[#0071E3]' : ''"
                        title="Ver detalle de covers por cliente y día a día"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                      </button>
                      <div>
                        <div class="font-bold text-[#1D1D1F] font-mono text-xs flex items-center gap-1.5">
                          <span>{{ r.ruta }}</span>
                          <span
                            v-if="r.cumple_mes"
                            class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800"
                          >
                            MTD CUMPLE
                          </span>
                        </div>
                        <div class="text-[10px] text-[#6E6E73] truncate max-w-[170px]" :title="r.vendedor">
                          {{ r.vendedor }}
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Canal -->
                  <td class="py-2 px-2 text-center whitespace-nowrap">
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black/[0.04] text-[#6E6E73]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Total Compradores -->
                  <td class="py-2 px-2 text-center whitespace-nowrap font-mono font-bold text-[#1D1D1F]">
                    {{ fmt(r.clientes_total) }}
                  </td>

                  <!-- Cover 1: Sedal 340 -->
                  <td class="py-2 px-2 text-center whitespace-nowrap bg-blue-50/20">
                    <span
                      class="px-1.5 py-0.5 rounded text-[11px] font-bold font-mono"
                      :class="r.cover1_count > 0 ? 'bg-blue-100/80 text-[#0071E3]' : 'text-[#86868B]'"
                    >
                      {{ r.cover1_count }}
                    </span>
                  </td>

                  <!-- Cover 2: Pepsodent -->
                  <td class="py-2 px-2 text-center whitespace-nowrap bg-indigo-50/20">
                    <span
                      class="px-1.5 py-0.5 rounded text-[11px] font-bold font-mono"
                      :class="r.cover2_count > 0 ? 'bg-indigo-100/80 text-indigo-700' : 'text-[#86868B]'"
                    >
                      {{ r.cover2_count }}
                    </span>
                  </td>

                  <!-- Cover 3: Sedal 40ml -->
                  <td class="py-2 px-2 text-center whitespace-nowrap bg-emerald-50/20">
                    <span
                      class="px-1.5 py-0.5 rounded text-[11px] font-bold font-mono"
                      :class="r.cover3_count > 0 ? 'bg-emerald-100/80 text-emerald-700' : 'text-[#86868B]'"
                    >
                      {{ r.cover3_count }}
                    </span>
                  </td>

                  <!-- Con Cover Total -->
                  <td class="py-2 px-2 text-center whitespace-nowrap font-mono font-extrabold text-[#1D1D1F]">
                    {{ fmt(r.clientes_con_cover) }}
                  </td>

                  <!-- Sin Cover -->
                  <td class="py-2 px-2 text-center whitespace-nowrap font-mono text-[#86868B]">
                    {{ fmt(r.clientes_sin_cover) }}
                  </td>

                  <!-- % Cobertura MTD -->
                  <td class="py-2 px-3 whitespace-nowrap">
                    <div class="flex items-center justify-center gap-1.5">
                      <div class="w-12 bg-black/[0.06] rounded-full h-1.5 overflow-hidden shrink-0 hidden sm:block">
                        <div
                          class="h-full rounded-full transition-all"
                          :class="r.cobertura_pct >= 50 ? 'bg-emerald-500' : 'bg-amber-500'"
                          :style="{ width: `${Math.min(r.cobertura_pct, 100)}%` }"
                        ></div>
                      </div>
                      <span
                        class="px-1.5 py-0.5 rounded text-[11px] font-bold font-mono"
                        :class="r.cobertura_pct >= 50 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                      >
                        {{ fmtPct(r.cobertura_pct) }}
                      </span>
                    </div>
                  </td>

                  <!-- Días Activos -->
                  <td class="py-2 px-2 text-center whitespace-nowrap font-mono text-[11px] text-[#6E6E73]">
                    {{ r.dias_activos }}
                  </td>

                  <!-- Días Cumplidos -->
                  <td class="py-2 px-2 text-center whitespace-nowrap font-mono text-[11px]">
                    <span :class="r.dias_cumplidos > 0 ? 'font-bold text-emerald-700' : 'text-[#86868B]'">
                      {{ r.dias_cumplidos }}
                    </span>
                    <span class="text-[9.5px] text-[#86868B]"> / {{ r.dias_activos }}</span>
                  </td>

                  <!-- % Días Cumplidos -->
                  <td class="py-2 px-2 text-center whitespace-nowrap font-mono font-semibold text-[11px]">
                    <span :class="r.pct_dias_cumplidos >= 50 ? 'text-emerald-700' : 'text-[#6E6E73]'">
                      {{ fmtPct(r.pct_dias_cumplidos) }}
                    </span>
                  </td>

                  <!-- Inversión Total Descuentos (Bs) -->
                  <td class="py-2 px-3 text-right whitespace-nowrap font-mono font-bold text-[#1D1D1F]">
                    {{ fmtBs(r.descuento_total) }}
                  </td>

                  <!-- Botón Desplegar -->
                  <td class="py-2 px-2 text-center whitespace-nowrap">
                    <button
                      type="button"
                      @click="toggleExpandir(r.ruta)"
                      class="px-2 py-1 text-[10.5px] font-semibold rounded-[6px] transition-all cursor-pointer"
                      :class="estaExpandida(r.ruta)
                        ? 'bg-[#0071E3] text-white'
                        : 'bg-black/[0.04] text-[#1D1D1F] hover:bg-black/[0.08]'"
                    >
                      {{ estaExpandida(r.ruta) ? 'Ocultar' : 'Ver Detalle' }}
                    </button>
                  </td>
                </tr>

                <!-- ── FILA EXPANDIDA: ACORDEÓN DETALLE POR COVER Y DÍA A DÍA ── -->
                <tr v-if="estaExpandida(r.ruta)" class="bg-[#F8F9FB] border-y border-blue-200/50">
                  <td colspan="14" class="p-3 sm:p-4">
                    <!-- Indicador de Carga del Detalle -->
                    <div v-if="cargandoDetalles[r.ruta]" class="py-8 flex flex-col items-center justify-center gap-2">
                      <div class="w-6 h-6 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
                      <span class="text-xs text-[#6E6E73] font-medium">Cargando desglose de covers para la ruta {{ r.ruta }}...</span>
                    </div>

                    <!-- Contenido del Detalle de la Ruta -->
                    <div v-else-if="cacheDetalles[r.ruta]" class="space-y-3">
                      <!-- Cabecera de la Ruta Expandida -->
                      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-black/[0.06] pb-2.5">
                        <div class="flex items-center gap-2 flex-wrap">
                          <span class="px-2 py-0.5 rounded-md bg-[#0071E3] text-white text-xs font-bold font-mono">
                            Ruta {{ r.ruta }}
                          </span>
                          <span class="text-xs font-semibold text-[#1D1D1F]">
                            {{ cacheDetalles[r.ruta].vendedor }}
                          </span>
                          <span class="text-xs text-[#86868B]">
                            &bull; Canal {{ cacheDetalles[r.ruta].canal }}
                          </span>
                          <span class="text-xs font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            {{ cacheDetalles[r.ruta].totales.clientes_con_cover }} de {{ cacheDetalles[r.ruta].totales.clientes_total }} clientes con cover ({{ fmtPct(cacheDetalles[r.ruta].totales.cobertura_pct) }})
                          </span>
                        </div>

                        <!-- Selector de Subpestaña Interna de la Ruta -->
                        <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[8px] text-[11px] font-semibold">
                          <button
                            type="button"
                            @click="setSubTabRuta(r.ruta, 'tiendas')"
                            class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
                            :class="getSubTabRuta(r.ruta) === 'tiendas' ? 'bg-white text-[#0071E3] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                          >
                            Detalle por Tiendas ({{ cacheDetalles[r.ruta].tiendas.length }})
                          </button>
                          <button
                            type="button"
                            @click="setSubTabRuta(r.ruta, 'dias')"
                            class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
                            :class="getSubTabRuta(r.ruta) === 'dias' ? 'bg-white text-[#0071E3] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                          >
                            Calendario Día a Día ({{ cacheDetalles[r.ruta].dias.length }} días)
                          </button>
                        </div>
                      </div>

                      <!-- ──────── SUBTAB 1: DETALLE POR TIENDAS / COVERS ──────── -->
                      <div v-if="getSubTabRuta(r.ruta) === 'tiendas'" class="space-y-2.5">
                        <!-- Filtros del Listado de Tiendas -->
                        <div class="flex flex-wrap items-center justify-between gap-2">
                          <!-- Chips de Filtrado por Tipo de Cover -->
                          <div class="inline-flex items-center gap-1 flex-wrap text-[11px]">
                            <button
                              type="button"
                              @click="setFiltroCoverTienda(r.ruta, 'todos')"
                              class="px-2 py-1 rounded-[6px] transition-all cursor-pointer font-semibold"
                              :class="getFiltroCoverTienda(r.ruta) === 'todos' ? 'bg-[#1D1D1F] text-white' : 'bg-white border border-[#E5E5EA] text-[#6E6E73] hover:text-[#1D1D1F]'"
                            >
                              Todas ({{ cacheDetalles[r.ruta].tiendas.length }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroCoverTienda(r.ruta, 'con_cover')"
                              class="px-2 py-1 rounded-[6px] transition-all cursor-pointer font-semibold"
                              :class="getFiltroCoverTienda(r.ruta) === 'con_cover' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100'"
                            >
                              Con Cover ({{ cacheDetalles[r.ruta].totales.clientes_con_cover }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroCoverTienda(r.ruta, 'cover1')"
                              class="px-2 py-1 rounded-[6px] transition-all cursor-pointer font-semibold"
                              :class="getFiltroCoverTienda(r.ruta) === 'cover1' ? 'bg-[#0071E3] text-white' : 'bg-blue-50 border border-blue-200 text-[#0071E3] hover:bg-blue-100'"
                            >
                              Cover 1: Sedal 340 ({{ cacheDetalles[r.ruta].totales.cover1_count }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroCoverTienda(r.ruta, 'cover2')"
                              class="px-2 py-1 rounded-[6px] transition-all cursor-pointer font-semibold"
                              :class="getFiltroCoverTienda(r.ruta) === 'cover2' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100'"
                            >
                              Cover 2: Pepsodent ({{ cacheDetalles[r.ruta].totales.cover2_count }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroCoverTienda(r.ruta, 'cover3')"
                              class="px-2 py-1 rounded-[6px] transition-all cursor-pointer font-semibold"
                              :class="getFiltroCoverTienda(r.ruta) === 'cover3' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100'"
                            >
                              Cover 3: Sedal 40ml ({{ cacheDetalles[r.ruta].totales.cover3_count }})
                            </button>
                            <button
                              type="button"
                              @click="setFiltroCoverTienda(r.ruta, 'sin_cover')"
                              class="px-2 py-1 rounded-[6px] transition-all cursor-pointer font-semibold"
                              :class="getFiltroCoverTienda(r.ruta) === 'sin_cover' ? 'bg-slate-600 text-white' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'"
                            >
                              Sin Cover ({{ cacheDetalles[r.ruta].totales.clientes_sin_cover }})
                            </button>
                          </div>

                          <!-- Buscador local de cliente -->
                          <div class="relative w-48">
                            <input
                              v-model="busquedaTienda[r.ruta]"
                              type="text"
                              placeholder="Filtrar cliente..."
                              class="w-full pl-6 pr-2 py-1 text-[11px] rounded-[6px] bg-white border border-[#D2D2D7] text-[#1D1D1F] placeholder-[#86868B] focus:ring-1 focus:ring-[#0071E3]"
                            />
                            <svg class="w-3 h-3 text-[#86868B] absolute left-2 top-1.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                          </div>
                        </div>

                        <!-- Tabla de Tiendas de la Ruta -->
                        <div class="bg-white rounded-[10px] border border-black/[0.08] overflow-hidden shadow-2xs">
                          <table class="w-full text-left border-collapse text-[11px]">
                            <thead>
                              <tr class="bg-[#F5F5F7] border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase">
                                <th class="py-2 px-2.5">Código / Cliente</th>
                                <th class="py-2 px-2 text-center">Compras MTD</th>
                                <th class="py-2 px-2 text-center">Cover Identificado</th>
                                <th class="py-2 px-3">Detalle de la Promoción</th>
                                <th class="py-2 px-2 text-right">Descuento (Bs)</th>
                                <th class="py-2 px-2 text-right">Total Venta (Bs)</th>
                                <th class="py-2 px-2 text-center">Ítems</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-black/[0.04]">
                              <template v-for="t in getTiendasFiltradas(r.ruta)" :key="t.cliente_id">
                                <tr class="hover:bg-black/[0.01] transition-colors">
                                  <!-- Código y Nombre -->
                                  <td class="py-2 px-2.5">
                                    <div class="font-bold text-[#1D1D1F]">{{ t.cliente }}</div>
                                    <div class="text-[9.5px] font-mono text-[#86868B]">Cód: {{ t.codigo_cliente }}</div>
                                  </td>

                                  <!-- Compras en el mes -->
                                  <td class="py-2 px-2 text-center font-mono font-semibold">
                                    <span :class="t.visitas_con_cover > 0 ? 'text-emerald-700 font-bold' : 'text-[#6E6E73]'">
                                      {{ t.visitas_con_cover }}
                                    </span>
                                    <span class="text-[9.5px] text-[#86868B]"> / {{ t.visitas_total }}</span>
                                  </td>

                                  <!-- Cover Identificado -->
                                  <td class="py-2 px-2 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1 flex-wrap">
                                      <span
                                        v-if="t.tiene_cover1"
                                        class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-blue-100 text-[#0071E3] border border-blue-200"
                                      >
                                        Cover 1
                                      </span>
                                      <span
                                        v-if="t.tiene_cover2"
                                        class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-indigo-100 text-indigo-700 border border-indigo-200"
                                      >
                                        Cover 2
                                      </span>
                                      <span
                                        v-if="t.tiene_cover3"
                                        class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"
                                      >
                                        Cover 3
                                      </span>
                                      <span
                                        v-if="t.tiene_descuento && !t.tiene_cover1 && !t.tiene_cover2 && !t.tiene_cover3"
                                        class="px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                                      >
                                        Descuento Regular
                                      </span>
                                      <span
                                        v-if="!t.tiene_descuento"
                                        class="px-1.5 py-0.5 rounded text-[9.5px] font-medium bg-black/[0.04] text-[#86868B]"
                                      >
                                        Sin Cover
                                      </span>
                                    </div>
                                  </td>

                                  <!-- Resumen de la Promoción -->
                                  <td class="py-2 px-3 text-[10.5px]">
                                    <div
                                      class="truncate max-w-[280px] sm:max-w-[400px]"
                                      :class="t.tiene_descuento ? 'text-[#1D1D1F] font-medium' : 'text-[#86868B]'"
                                      :title="t.resumen_promo"
                                    >
                                      {{ t.resumen_promo }}
                                    </div>
                                  </td>

                                  <!-- Descuento Otorgado (Bs) -->
                                  <td class="py-2 px-2 text-right font-mono font-bold whitespace-nowrap" :class="t.descuento_total > 0 ? 'text-amber-700' : 'text-[#86868B]'">
                                    {{ fmtBs(t.descuento_total) }}
                                  </td>

                                  <!-- Monto Total Venta (Bs) -->
                                  <td class="py-2 px-2 text-right font-mono text-[#1D1D1F] whitespace-nowrap">
                                    {{ fmtBs(t.monto_total) }}
                                  </td>

                                  <!-- Botón Ver Ítems -->
                                  <td class="py-2 px-2 text-center whitespace-nowrap">
                                    <button
                                      type="button"
                                      @click="toggleDetalleTienda(r.ruta, t.cliente_id)"
                                      class="px-2 py-0.5 rounded text-[10px] font-bold transition-all cursor-pointer"
                                      :class="estaTiendaExpandida(r.ruta, t.cliente_id)
                                        ? 'bg-[#1D1D1F] text-white'
                                        : 'bg-black/[0.04] text-[#0071E3] hover:bg-blue-50'"
                                    >
                                      {{ estaTiendaExpandida(r.ruta, t.cliente_id) ? 'Cerrar' : 'Ítems' }}
                                    </button>
                                  </td>
                                </tr>

                                <!-- Desglose de Productos / Transacciones de la Tienda -->
                                <tr v-if="estaTiendaExpandida(r.ruta, t.cliente_id)" class="bg-[#F0F2F5]/70">
                                  <td colspan="7" class="p-3">
                                    <div class="space-y-2">
                                      <div
                                        v-for="tx in t.transacciones"
                                        :key="tx.fecha"
                                        class="bg-white rounded-[8px] p-2.5 border border-black/[0.06] shadow-2xs space-y-1.5"
                                      >
                                        <div class="flex items-center justify-between text-[10.5px] border-b border-black/[0.04] pb-1">
                                          <div class="flex items-center gap-2">
                                            <span class="font-bold font-mono text-[#0071E3] bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                              Fecha: {{ tx.fecha }}
                                            </span>
                                            <span class="text-[#1D1D1F] font-semibold">{{ tx.resumen_promo }}</span>
                                          </div>
                                          <div class="flex items-center gap-2 text-[10px] font-mono">
                                            <span class="text-amber-700 font-bold">Desc: {{ fmtBs(tx.descuento) }}</span>
                                            <span class="text-[#6E6E73]">Total: {{ fmtBs(tx.monto) }}</span>
                                          </div>
                                        </div>

                                        <!-- Tabla de Ítems de la transacción -->
                                        <div class="overflow-x-auto">
                                          <table class="w-full text-left text-[10px]">
                                            <thead>
                                              <tr class="text-[#86868B] uppercase font-bold border-b border-black/[0.04]">
                                                <th class="py-1">Producto</th>
                                                <th class="py-1 text-center">Cant</th>
                                                <th class="py-1 text-right">Precio Lista</th>
                                                <th class="py-1 text-right">Descuento</th>
                                                <th class="py-1 text-right">Monto Final</th>
                                                <th class="py-1 text-center">Rol en Promo</th>
                                              </tr>
                                            </thead>
                                            <tbody class="divide-y divide-black/[0.03]">
                                              <tr
                                                v-for="(it, itIdx) in tx.items_etiquetados"
                                                :key="itIdx"
                                                :class="it.con_descuento ? 'bg-amber-50/40 font-semibold' : ''"
                                              >
                                                <td class="py-1 text-[#1D1D1F]">{{ it.producto }}</td>
                                                <td class="py-1 text-center font-mono font-bold">{{ it.cantidad }}</td>
                                                <td class="py-1 text-right font-mono">{{ fmtBs(it.precio_lista) }}</td>
                                                <td class="py-1 text-right font-mono text-amber-700">{{ fmtBs(it.descuento) }}</td>
                                                <td class="py-1 text-right font-mono">{{ fmtBs(it.monto_final) }}</td>
                                                <td class="py-1 text-center whitespace-nowrap">
                                                  <span
                                                    v-if="it.tag"
                                                    class="px-1.5 py-0.2 rounded text-[9px] font-bold"
                                                    :class="getTagClass(it.tag_tipo)"
                                                    :title="it.tag_texto"
                                                  >
                                                    {{ it.tag }}
                                                  </span>
                                                  <span v-else class="text-[#86868B]">-</span>
                                                </td>
                                              </tr>
                                            </tbody>
                                          </table>
                                        </div>
                                      </div>
                                    </div>
                                  </td>
                                </tr>
                              </template>

                              <tr v-if="getTiendasFiltradas(r.ruta).length === 0">
                                <td colspan="7" class="py-6 text-center text-[#86868B] text-xs">
                                  No se encontraron clientes para el filtro seleccionado.
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <!-- ──────── SUBTAB 2: CALENDARIO DÍA A DÍA ──────── -->
                      <div v-else-if="getSubTabRuta(r.ruta) === 'dias'" class="space-y-2">
                        <div class="bg-white rounded-[10px] border border-black/[0.08] overflow-hidden shadow-2xs">
                          <table class="w-full text-left border-collapse text-[11px]">
                            <thead>
                              <tr class="bg-[#F5F5F7] border-b border-black/[0.06] text-[10px] font-bold text-[#86868B] uppercase">
                                <th class="py-2 px-3">Fecha</th>
                                <th class="py-2 px-2 text-center">Compradores</th>
                                <th class="py-2 px-2 text-center text-[#0071E3] bg-blue-50/50">Cover 1</th>
                                <th class="py-2 px-2 text-center text-indigo-700 bg-indigo-50/50">Cover 2</th>
                                <th class="py-2 px-2 text-center text-emerald-700 bg-emerald-50/50">Cover 3</th>
                                <th class="py-2 px-2 text-center font-bold">Con Cover</th>
                                <th class="py-2 px-2 text-center">Sin Cover</th>
                                <th class="py-2 px-3 text-center">% Covers Día</th>
                                <th class="py-2 px-2 text-center">Estatus Meta (&ge;50%)</th>
                                <th class="py-2 px-3 text-right">Descuento (Bs)</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-black/[0.04]">
                              <tr
                                v-for="d in cacheDetalles[r.ruta].dias"
                                :key="d.fecha"
                                class="hover:bg-black/[0.01] transition-colors"
                              >
                                <td class="py-2 px-3 whitespace-nowrap font-mono font-bold text-[#1D1D1F]">
                                  {{ d.dia_nombre }}
                                  <span class="text-[9.5px] font-normal text-[#86868B] ml-1">({{ d.fecha }})</span>
                                </td>
                                <td class="py-2 px-2 text-center font-mono font-bold">{{ d.compradores }}</td>
                                <td class="py-2 px-2 text-center font-mono bg-blue-50/20 text-[#0071E3] font-bold">{{ d.cover1_count }}</td>
                                <td class="py-2 px-2 text-center font-mono bg-indigo-50/20 text-indigo-700 font-bold">{{ d.cover2_count }}</td>
                                <td class="py-2 px-2 text-center font-mono bg-emerald-50/20 text-emerald-700 font-bold">{{ d.cover3_count }}</td>
                                <td class="py-2 px-2 text-center font-mono font-extrabold text-[#1D1D1F]">{{ d.con_cover }}</td>
                                <td class="py-2 px-2 text-center font-mono text-[#86868B]">{{ d.sin_cover }}</td>
                                <td class="py-2 px-3 text-center whitespace-nowrap">
                                  <span
                                    class="px-1.5 py-0.5 rounded text-[10.5px] font-bold font-mono"
                                    :class="d.cumple ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                  >
                                    {{ fmtPct(d.cobertura_pct) }}
                                  </span>
                                </td>
                                <td class="py-2 px-2 text-center whitespace-nowrap">
                                  <span
                                    class="px-2 py-0.5 rounded-full text-[9px] font-extrabold"
                                    :class="d.cumple ? 'bg-emerald-600 text-white' : 'bg-rose-500 text-white'"
                                  >
                                    {{ d.cumple ? 'CUMPLE' : 'NO CUMPLE' }}
                                  </span>
                                </td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-amber-700">
                                  {{ fmtBs(d.descuento_total) }}
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- Estado Vacío -->
              <tr v-if="rutasFiltradas.length === 0">
                <td colspan="14" class="py-12 text-center text-[#86868B] text-xs">
                  No se encontraron rutas con los filtros aplicados.
                </td>
              </tr>
            </tbody>

            <!-- Fila Consolidada al Final -->
            <tfoot v-if="rutasFiltradas.length > 0" class="bg-[#F8F8FA] border-t-2 border-black/[0.08] font-bold select-none sticky bottom-0 z-10">
              <tr>
                <td class="py-2.5 px-3 whitespace-nowrap font-mono text-xs text-[#1D1D1F]">
                  CONSOLIDADO ({{ rutasFiltradas.length }} rutas)
                </td>
                <td class="py-2.5 px-2 text-center text-[10px] text-[#86868B]">TODOS</td>
                <td class="py-2.5 px-2 text-center font-mono text-[#1D1D1F]">{{ fmt(totales.clientes_total) }}</td>
                <td class="py-2.5 px-2 text-center font-mono text-[#0071E3] bg-blue-50/50">{{ fmt(totales.cover1_count) }}</td>
                <td class="py-2.5 px-2 text-center font-mono text-indigo-700 bg-indigo-50/50">{{ fmt(totales.cover2_count) }}</td>
                <td class="py-2.5 px-2 text-center font-mono text-emerald-700 bg-emerald-50/50">{{ fmt(totales.cover3_count) }}</td>
                <td class="py-2.5 px-2 text-center font-mono font-extrabold text-[#1D1D1F]">{{ fmt(totales.clientes_con_cover) }}</td>
                <td class="py-2.5 px-2 text-center font-mono text-[#86868B]">{{ fmt(totales.clientes_sin_cover) }}</td>
                <td class="py-2.5 px-3 text-center font-mono">
                  <span
                    class="px-2 py-0.5 rounded text-xs font-bold"
                    :class="totales.cobertura_pct >= 50 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                  >
                    {{ fmtPct(totales.cobertura_pct) }}
                  </span>
                </td>
                <td class="py-2.5 px-2 text-center font-mono text-[#6E6E73]">{{ totales.dias_activos_promedio }}</td>
                <td class="py-2.5 px-2 text-center font-mono text-emerald-700">{{ totales.dias_cumplidos_promedio }}</td>
                <td class="py-2.5 px-2 text-center font-mono text-[#1D1D1F]">{{ fmtPct(totales.pct_dias_cumplidos_global) }}</td>
                <td class="py-2.5 px-3 text-right font-mono text-amber-800">{{ fmtBs(totales.descuento_total) }}</td>
                <td class="py-2.5 px-2 text-center">-</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SubNavTabs from '@/Components/UI/SubNavTabs.vue'
import AvanceFiltrosBar from '@/Pages/Supervisor/Avance/AvanceFiltrosBar.vue'
import { navigationConfig } from '@/Config/navigation'
import { useAvanceStore } from '@/Stores/avanceStore'
import { descargarExcelXlsx } from '@/Utils/exportExcel'

const props = defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  meses: { type: Array, default: () => [] },
  fecha_inicio_default: { type: String, default: '' },
  fecha_corte_default: { type: String, default: '' },
  mes_default: { type: String, default: '' },
  dias_calendario: { type: Object, default: () => ({}) },
})

const store = useAvanceStore()

// Subnavegación de Módulo Avance
const avanceMenu = navigationConfig.mainMenu.find(m => m.id === 'avance')
const avanceTabs = avanceMenu ? avanceMenu.submodules : []

// Estado principal
const cargando = ref(false)
const rutasConsolidadas = ref([])
const totales = ref({})
const rangoInfo = ref({})

// Filtros locales
const filtroTexto = ref('')
const filtroEstado = ref('todos') // 'todos' | 'cumplen' | 'bajo'
const columnaOrden = ref('ruta')
const ordenAsc = ref(true)
const copiadoExitoso = ref(false)

// Acordeón y caché de detalle por ruta
const filasExpandidas = reactive(new Set())
const cacheDetalles = reactive({})
const cargandoDetalles = reactive({})
const subTabRuta = reactive({}) // ruta => 'tiendas' | 'dias'
const filtroCoverTienda = reactive({}) // ruta => 'todos' | 'con_cover' | 'cover1' | 'cover2' | 'cover3' | 'sin_cover'
const busquedaTienda = reactive({}) // ruta => string
const tiendasExpandidas = reactive({}) // `${ruta}_${cliente_id}` => bool

// Formatters
const fmt = (v) => (Number(v) || 0).toLocaleString('es-BO')
const fmtBs = (v) => `Bs ${(Number(v) || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const fmtPct = (v) => `${(Number(v) || 0).toFixed(1)}%`

function getTagClass(tagTipo) {
  switch (tagTipo) {
    case 'cover1':
      return 'bg-blue-100 text-[#0071E3] border border-blue-200'
    case 'cover2':
      return 'bg-indigo-100 text-indigo-700 border border-indigo-200'
    case 'cover1_2':
      return 'bg-purple-100 text-purple-700 border border-purple-200'
    case 'cover3':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-200'
    case 'bonif':
      return 'bg-teal-100 text-teal-800 border border-teal-200'
    case 'req1':
      return 'bg-blue-50 text-blue-700 border border-blue-200'
    case 'req2':
      return 'bg-indigo-50 text-indigo-700 border border-indigo-200'
    case 'desc':
      return 'bg-amber-100 text-amber-800 border border-amber-200'
    default:
      return 'bg-black/[0.04] text-[#6E6E73]'
  }
}

// Subtabs y filtros del acordeón
function getSubTabRuta(ruta) {
  return subTabRuta[ruta] || 'tiendas'
}

function setSubTabRuta(ruta, tab) {
  subTabRuta[ruta] = tab
}

function getFiltroCoverTienda(ruta) {
  return filtroCoverTienda[ruta] || 'todos'
}

function setFiltroCoverTienda(ruta, filtro) {
  filtroCoverTienda[ruta] = filtro
}

function estaTiendaExpandida(ruta, clienteId) {
  return !!tiendasExpandidas[`${ruta}_${clienteId}`]
}

function toggleDetalleTienda(ruta, clienteId) {
  const key = `${ruta}_${clienteId}`
  tiendasExpandidas[key] = !tiendasExpandidas[key]
}

// Filtrar tiendas dentro del acordeón de una ruta
function getTiendasFiltradas(ruta) {
  const detalle = cacheDetalles[ruta]
  if (!detalle || !detalle.tiendas) return []

  let list = detalle.tiendas
  const filtro = getFiltroCoverTienda(ruta)
  if (filtro === 'con_cover') {
    list = list.filter(t => t.tiene_descuento)
  } else if (filtro === 'cover1') {
    list = list.filter(t => t.tiene_cover1)
  } else if (filtro === 'cover2') {
    list = list.filter(t => t.tiene_cover2)
  } else if (filtro === 'cover3') {
    list = list.filter(t => t.tiene_cover3)
  } else if (filtro === 'sin_cover') {
    list = list.filter(t => !t.tiene_descuento)
  }

  const query = (busquedaTienda[ruta] || '').trim().toLowerCase()
  if (query) {
    list = list.filter(t =>
      (t.cliente || '').toLowerCase().includes(query) ||
      String(t.codigo_cliente || '').toLowerCase().includes(query)
    )
  }

  return list
}

// Expansión de fila en matriz principal
function estaExpandida(ruta) {
  return filasExpandidas.has(ruta)
}

async function toggleExpandir(ruta) {
  if (filasExpandidas.has(ruta)) {
    filasExpandidas.delete(ruta)
    return
  }

  filasExpandidas.add(ruta)

  if (!cacheDetalles[ruta]) {
    await cargarDetalleRuta(ruta)
  }
}

async function cargarDetalleRuta(ruta) {
  cargandoDetalles[ruta] = true
  try {
    const payload = {
      ruta: ruta,
      fecha_inicio: store.fechaInicio,
      fecha_corte: store.fechaCorte,
    }
    const res = await axios.post(route('supervisor.avance.covers.detalle-ruta'), payload)
    cacheDetalles[ruta] = res.data
  } catch (e) {
    console.error(`Error al cargar detalle de covers para ruta ${ruta}:`, e)
  } finally {
    cargandoDetalles[ruta] = false
  }
}

// Ordenamiento
function ordenarPor(col) {
  if (columnaOrden.value === col) {
    ordenAsc.value = !ordenAsc.value
  } else {
    columnaOrden.value = col
    ordenAsc.value = col === 'ruta' || col === 'canal'
  }
}

// Rutas filtradas y ordenadas
const rutasFiltradas = computed(() => {
  let list = rutasConsolidadas.value || []

  // Filtro por texto (ruta o vendedor)
  const q = filtroTexto.value.trim().toLowerCase()
  if (q) {
    list = list.filter(r =>
      (r.ruta || '').toLowerCase().includes(q) ||
      (r.vendedor || '').toLowerCase().includes(q) ||
      (r.canal || '').toLowerCase().includes(q)
    )
  }

  // Filtro por estado
  if (filtroEstado.value === 'cumplen') {
    list = list.filter(r => r.cumple_mes)
  } else if (filtroEstado.value === 'bajo') {
    list = list.filter(r => !r.cumple_mes)
  }

  // Orden
  const col = columnaOrden.value
  const asc = ordenAsc.value ? 1 : -1

  return [...list].sort((a, b) => {
    let va = a[col]
    let vb = b[col]

    if (typeof va === 'string') {
      return asc * va.localeCompare(vb)
    }
    return asc * ((Number(va) || 0) - (Number(vb) || 0))
  })
})

// Consulta principal
async function consultarCovers() {
  cargando.value = true
  filasExpandidas.clear()

  try {
    const payload = {
      fecha_inicio: store.fechaInicio,
      fecha_corte: store.fechaCorte,
      canales: store.canalesSeleccionados,
      rutas: store.rutasSeleccionadas,
    }

    const res = await axios.post(route('supervisor.avance.covers.data'), payload)
    rutasConsolidadas.value = res.data.rutas || []
    totales.value = res.data.totales || {}
    rangoInfo.value = res.data.rango || {}
  } catch (e) {
    console.error('Error al consultar datos de acumulado de covers:', e)
  } finally {
    cargando.value = false
  }
}

// Copiar tabla al portapapeles
async function copiarTablaAlPortapapeles() {
  if (rutasFiltradas.value.length === 0) return

  const headers = [
    'Ruta', 'Vendedor', 'Canal', 'Compradores MTD', 'Cover 1 (Sedal 340)',
    'Cover 2 (Pepsodent)', 'Cover 3 (Sedal 40)', 'Con Cover', 'Sin Cover',
    '% Cobertura MTD', 'Días Activos', 'Días Cumple', '% Cumplimiento', 'Inversión Descuentos (Bs)'
  ]

  const lineas = [headers.join('\t')]

  rutasFiltradas.value.forEach(r => {
    lineas.push([
      r.ruta,
      r.vendedor,
      r.canal,
      r.clientes_total,
      r.cover1_count,
      r.cover2_count,
      r.cover3_count,
      r.clientes_con_cover,
      r.clientes_sin_cover,
      `${r.cobertura_pct}%`,
      r.dias_activos,
      r.dias_cumplidos,
      `${r.pct_dias_cumplidos}%`,
      r.descuento_total
    ].join('\t'))
  })

  // Fila consolidada
  lineas.push([
    'CONSOLIDADO', 'TODOS', 'TODOS',
    totales.value.clientes_total,
    totales.value.cover1_count,
    totales.value.cover2_count,
    totales.value.cover3_count,
    totales.value.clientes_con_cover,
    totales.value.clientes_sin_cover,
    `${totales.value.cobertura_pct}%`,
    totales.value.dias_activos_promedio,
    totales.value.dias_cumplidos_promedio,
    `${totales.value.pct_dias_cumplidos_global}%`,
    totales.value.descuento_total
  ].join('\t'))

  try {
    await navigator.clipboard.writeText(lineas.join('\n'))
    copiadoExitoso.value = true
    setTimeout(() => { copiadoExitoso.value = false }, 2500)
  } catch (err) {
    console.error('Error al copiar al portapapeles:', err)
  }
}

// Exportar a Excel (.xlsx)
function exportarExcel() {
  if (rutasFiltradas.value.length === 0) return

  const rows = [
    [
      'Ruta', 'Vendedor', 'Canal', 'Compradores MTD', 'Cover 1 (Sedal 340)',
      'Cover 2 (Pepsodent)', 'Cover 3 (Sedal 40)', 'Con Cover', 'Sin Cover',
      '% Cobertura MTD', 'Días Activos', 'Días Cumple', '% Cumplimiento', 'Inversión Descuentos (Bs)'
    ]
  ]

  rutasFiltradas.value.forEach(r => {
    rows.push([
      r.ruta,
      r.vendedor,
      r.canal,
      r.clientes_total,
      r.cover1_count,
      r.cover2_count,
      r.cover3_count,
      r.clientes_con_cover,
      r.clientes_sin_cover,
      r.cobertura_pct / 100,
      r.dias_activos,
      r.dias_cumplidos,
      r.pct_dias_cumplidos / 100,
      r.descuento_total
    ])
  })

  // Fila de total
  rows.push([
    'CONSOLIDADO', 'TODOS', 'TODOS',
    totales.value.clientes_total,
    totales.value.cover1_count,
    totales.value.cover2_count,
    totales.value.cover3_count,
    totales.value.clientes_con_cover,
    totales.value.clientes_sin_cover,
    (totales.value.cobertura_pct || 0) / 100,
    totales.value.dias_activos_promedio,
    totales.value.dias_cumplidos_promedio,
    (totales.value.pct_dias_cumplidos_global || 0) / 100,
    totales.value.descuento_total
  ])

  const mesStr = store.fechaInicio ? store.fechaInicio.substring(0, 7) : 'MTD'
  descargarExcelXlsx(rows, `Acumulado_Covers_${mesStr}`, 'Covers MTD')
}

onMounted(() => {
  store.init(props)
  consultarCovers()
})
</script>
