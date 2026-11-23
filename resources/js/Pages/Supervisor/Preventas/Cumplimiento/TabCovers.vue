<template>
  <div class="space-y-3 font-sans select-none">
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 1. Tarjetas Superiores de Resumen Ejecutivo (KPI Cards)     -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
      <!-- Tarjeta 1: Cumplimiento de Rutas -->
      <div class="apple-glass-card rounded-[14px] p-3 bg-white/70 border border-black/[0.06] flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between gap-1 text-[11px] font-semibold text-[#6E6E73]">
          <span>Rutas Evaluadas</span>
          <span class="px-1.5 py-0.2 rounded text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            5 pts &ge; 50%
          </span>
        </div>
        <div class="mt-1.5 flex items-baseline justify-between gap-2">
          <div class="text-[20px] font-extrabold font-mono text-[#1D1D1F] leading-tight">
            {{ rutasCumplenCount }}<span class="text-[13px] font-normal text-[#86868B]">/{{ totalRutasCount }}</span>
          </div>
          <div
            class="text-[12px] font-bold font-mono"
            :class="pctRutasCumplen >= 80 ? 'text-[#1E6B37]' : (pctRutasCumplen >= 50 ? 'text-amber-600' : 'text-[#C9342C]')"
          >
            {{ pctRutasCumplen.toFixed(1) }}%
          </div>
        </div>
        <div class="text-[10px] text-[#86868B] mt-0.5">Rutas que alcanzan el umbral</div>
      </div>

      <!-- Tarjeta 2: Cover 1 (Sedal 340ml) -->
      <div class="apple-glass-card rounded-[14px] p-3 bg-white/70 border border-purple-200/70 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between gap-1 text-[11px] font-semibold text-purple-800">
          <span>Cover 1 · Sedal 340ml</span>
          <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-purple-100 text-purple-700 border border-purple-200">
            3u &rarr; 1 Bonif
          </span>
        </div>
        <div class="mt-1.5 flex items-baseline justify-between gap-2">
          <div class="text-[20px] font-extrabold font-mono text-purple-900 leading-tight">
            {{ globalCover1 }}
          </div>
          <div class="text-[11px] font-semibold text-purple-700 font-mono">
            {{ globalCompradores > 0 ? ((globalCover1 / globalCompradores) * 100).toFixed(1) : 0 }}% PDVs
          </div>
        </div>
        <div class="text-[10px] text-purple-600/80 mt-0.5 truncate" title="Sedal 340ml &rarr; Sedal Toque de Seda 40ml">
          Sedal 340 &rarr; Toque Seda 40ml
        </div>
      </div>

      <!-- Tarjeta 3: Cover 2 (Pepsodent) -->
      <div class="apple-glass-card rounded-[14px] p-3 bg-white/70 border border-sky-200/70 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between gap-1 text-[11px] font-semibold text-sky-800">
          <span>Cover 2 · Pepsodent</span>
          <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-sky-100 text-sky-700 border border-sky-200">
            3u &rarr; 1 Bonif
          </span>
        </div>
        <div class="mt-1.5 flex items-baseline justify-between gap-2">
          <div class="text-[20px] font-extrabold font-mono text-sky-900 leading-tight">
            {{ globalCover2 }}
          </div>
          <div class="text-[11px] font-semibold text-sky-700 font-mono">
            {{ globalCompradores > 0 ? ((globalCover2 / globalCompradores) * 100).toFixed(1) : 0 }}% PDVs
          </div>
        </div>
        <div class="text-[10px] text-sky-600/80 mt-0.5 truncate" title="Pepsodent 90g/130g &rarr; Sedal Toque de Seda 40ml">
          Pepsodent &rarr; Toque Seda 40ml
        </div>
      </div>

      <!-- Tarjeta 4: Cover 3 (Sedal 40ml) -->
      <div class="apple-glass-card rounded-[14px] p-3 bg-white/70 border border-emerald-200/70 flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between gap-1 text-[11px] font-semibold text-emerald-800">
          <span>Cover 3 · Sedal 40ml</span>
          <span class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
            12u &rarr; 2 Bonif
          </span>
        </div>
        <div class="mt-1.5 flex items-baseline justify-between gap-2">
          <div class="text-[20px] font-extrabold font-mono text-emerald-900 leading-tight">
            {{ globalCover3 }}
          </div>
          <div class="text-[11px] font-semibold text-emerald-700 font-mono">
            {{ globalCompradores > 0 ? ((globalCover3 / globalCompradores) * 100).toFixed(1) : 0 }}% PDVs
          </div>
        </div>
        <div class="text-[10px] text-emerald-600/80 mt-0.5 truncate" title="Sedal 40ml (12 unidades &rarr; 2 bonificadas)">
          Sedal 40ml (12 &rarr; 2 bonif)
        </div>
      </div>

      <!-- Tarjeta 5: Total Compradores con Cover -->
      <div class="col-span-2 sm:col-span-2 lg:col-span-1 apple-glass-card rounded-[14px] p-3 bg-white/70 border border-black/[0.06] flex flex-col justify-between shadow-xs">
        <div class="flex items-center justify-between gap-1 text-[11px] font-semibold text-[#6E6E73]">
          <span>Total PDVs con Cover</span>
          <span class="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold text-[#0071E3] bg-blue-50 border border-blue-200">
            {{ globalCompradores }} PDVs
          </span>
        </div>
        <div class="mt-1.5 flex items-baseline justify-between gap-2">
          <div class="text-[20px] font-extrabold font-mono text-[#1E6B37] leading-tight">
            {{ globalConCover }}
          </div>
          <div class="text-[12px] font-bold font-mono text-[#1E6B37]">
            {{ globalCompradores > 0 ? ((globalConCover / globalCompradores) * 100).toFixed(1) : 0 }}%
          </div>
        </div>
        <div class="text-[10px] text-[#86868B] mt-0.5">
          Sin Cover: <strong class="font-mono text-[#C9342C]">{{ globalSinCover }}</strong> PDVs
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- 2. Tarjeta Principal y Filtros Rápidos                      -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="apple-glass-card rounded-[16px] overflow-hidden bg-white/60 border border-black/[0.06]">
      <!-- Barra Superior: Título y Controles -->
      <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/50">
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[15px] sm:text-[17px] font-extrabold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 8: Covers (3 Tipos de Promociones)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            5 pts Base · Criterio &ge; 50%
          </span>
        </div>

        <!-- Filtros Rápidos de Estado y Búsqueda -->
        <div class="flex items-center gap-2 flex-wrap text-[11px]">
          <!-- Selector de Estado -->
          <div class="inline-flex rounded-[8px] bg-black/[0.04] p-0.5 border border-black/[0.05]">
            <button
              type="button"
              @click="filtroEstado = 'todos'"
              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer text-[10.5px]"
              :class="filtroEstado === 'todos' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Todas ({{ totalRutasCount }})
            </button>
            <button
              type="button"
              @click="filtroEstado = 'cumplen'"
              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer text-[10.5px]"
              :class="filtroEstado === 'cumplen' ? 'bg-[#EBF9EF] text-[#1E6B37] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1E6B37]'"
            >
              Cumplen ({{ rutasCumplenCount }})
            </button>
            <button
              type="button"
              @click="filtroEstado = 'deficit'"
              class="px-2 py-0.5 rounded-[6px] font-semibold transition-all cursor-pointer text-[10.5px]"
              :class="filtroEstado === 'deficit' ? 'bg-[#FDF0EF] text-[#C9342C] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#C9342C]'"
            >
              Déficit ({{ totalRutasCount - rutasCumplenCount }})
            </button>
          </div>

          <!-- Buscador de Ruta / Preventista -->
          <div class="relative">
            <input
              v-model="busquedaRuta"
              type="text"
              placeholder="Buscar ruta o preventista..."
              class="pl-7 pr-2.5 py-1 text-[11px] rounded-[8px] bg-white border border-black/[0.1] focus:outline-hidden focus:border-[#0071E3] w-40 sm:w-48 placeholder:text-[#86868B]"
            />
            <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1010.5 18a7.5 7.5 0 006.15-2.85z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════ -->
      <!-- 3. Tabla Principal de Rutas y Desglose de 3 Covers          -->
      <!-- ═══════════════════════════════════════════════════════════ -->
      <div class="overflow-x-auto custom-scroll sm:max-h-[calc(100vh-300px)] min-h-[380px]">
        <table class="w-full text-left border-collapse min-w-[860px] lg:min-w-[1040px] text-[12px]">
          <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
            <tr>
              <!-- Pos -->
              <th
                @click="toggleSort('pos')"
                class="px-1.5 sm:px-2 py-2 text-center sticky left-0 z-30 w-8 sm:w-10 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px] cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Ordenar por Posición"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Pos</span>
                  <span v-if="sortCol === 'pos'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Ruta / Preventista -->
              <th
                @click="toggleSort('ruta')"
                class="px-2 sm:px-3 py-2 sticky left-8 sm:left-10 z-30 w-32 sm:w-48 md:w-56 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] text-[10px] sm:text-[11px] truncate cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Ordenar por Ruta"
              >
                <div class="flex items-center gap-1">
                  <span>Ruta / Preventista</span>
                  <span v-if="sortCol === 'ruta'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Compradores -->
              <th
                @click="toggleSort('compradores')"
                class="px-2.5 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Total de clientes con preventa hoy"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Compradores</span>
                  <span v-if="sortCol === 'compradores'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Cover 1: Sedal 340 -->
              <th
                @click="toggleSort('cover1')"
                class="px-2.5 py-2 text-center border-r border-purple-200/60 bg-purple-50/40 text-purple-900 cursor-pointer hover:bg-purple-100/50 transition-colors"
                title="Clientes con Cover 1: Sedal 340ml (3u) → 1 Sedal Toque de Seda 40ml bonificado"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Cover 1 (Sedal 340)</span>
                  <span v-if="sortCol === 'cover1'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Cover 2: Pepsodent -->
              <th
                @click="toggleSort('cover2')"
                class="px-2.5 py-2 text-center border-r border-sky-200/60 bg-sky-50/40 text-sky-900 cursor-pointer hover:bg-sky-100/50 transition-colors"
                title="Clientes con Cover 2: Pepsodent 90g/130g (3u) → 1 Sedal Toque de Seda 40ml bonificado"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Cover 2 (Pepsodent)</span>
                  <span v-if="sortCol === 'cover2'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Cover 3: Sedal 40ml -->
              <th
                @click="toggleSort('cover3')"
                class="px-2.5 py-2 text-center border-r border-emerald-200/60 bg-emerald-50/40 text-emerald-900 cursor-pointer hover:bg-emerald-100/50 transition-colors"
                title="Clientes con Cover 3: Sedal 40ml (12u compradas → 2 bonificadas)"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Cover 3 (Sedal 40)</span>
                  <span v-if="sortCol === 'cover3'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Con Cover (Total) -->
              <th
                @click="toggleSort('con_descuento')"
                class="px-2.5 py-2 text-center border-r border-black/[0.04] bg-[#EBF9EF]/40 text-[#1E6B37] cursor-pointer hover:bg-emerald-100/60 transition-colors"
                title="Total de clientes compradores con al menos un producto con cover/descuento"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Con Cover</span>
                  <span v-if="sortCol === 'con_descuento'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Sin Cover -->
              <th
                @click="toggleSort('sin_descuento')"
                class="px-2.5 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Clientes compradores sin ningún cover/descuento"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Sin Cover</span>
                  <span v-if="sortCol === 'sin_descuento'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- % Covers -->
              <th
                @click="toggleSort('pct_covers')"
                class="px-2.5 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Porcentaje de clientes compradores con cover (Umbral: ≥ 50.0%)"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>% Covers</span>
                  <span v-if="sortCol === 'pct_covers'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Estatus -->
              <th
                @click="toggleSort('cumple')"
                class="px-2.5 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Cumple si % Covers ≥ 50.0%"
              >
                <div class="flex items-center justify-center gap-0.5">
                  <span>Estatus</span>
                  <span v-if="sortCol === 'cumple'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>

              <!-- Puntos (5) -->
              <th
                @click="toggleSort('puntos')"
                class="px-3 py-2 text-right cursor-pointer hover:bg-black/[0.03] transition-colors"
                title="Puntos asignados al KPI (5 pts)"
              >
                <div class="flex items-center justify-end gap-0.5">
                  <span>Puntos (5)</span>
                  <span v-if="sortCol === 'puntos'" class="text-[9px]">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </div>
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-black/[0.04]">
            <template v-if="store.loadingRanking">
              <tr v-for="i in 10" :key="`skel-cov-${i}`">
                <td class="px-1.5 sm:px-2 py-2 sticky left-0 w-8 sm:w-10"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
                <td class="px-2 sm:px-3 py-2 sticky left-8 sm:left-10 w-32 sm:w-48 md:w-56 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                  <div class="h-3.5 w-12 sm:w-16 skeleton mb-1"></div>
                  <div class="h-3 w-16 sm:w-28 skeleton"></div>
                </td>
                <td v-for="c in 8" :key="c" class="px-2.5 py-2 text-center"><div class="h-4 w-10 mx-auto skeleton"></div></td>
                <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
              </tr>
            </template>

            <tr v-else-if="rutasFiltradasPorEstado.length === 0">
              <td colspan="11" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
                No se encontraron rutas para los filtros seleccionados.
              </td>
            </tr>

            <template v-else>
              <template v-for="(r, idx) in rutasFiltradasPorEstado" :key="r.ruta || idx">
                <!-- Fila Principal de la Ruta -->
                <tr
                  @click="toggleCoversAcordeon(r)"
                  class="cursor-pointer transition-colors"
                  :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-black/[0.02]'"
                >
                  <!-- Pos -->
                  <td class="px-1.5 sm:px-2 py-1.5 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-8 sm:w-10 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                    {{ idx + 1 }}
                  </td>

                  <!-- Ruta / Preventista -->
                  <td
                    class="px-2 sm:px-3 py-1.5 sticky left-8 sm:left-10 z-10 w-32 sm:w-48 md:w-56 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate"
                    :title="`${r.ruta} - ${r.vendedor || 'No Asignado'}`"
                  >
                    <div class="flex items-center gap-1 sm:gap-1.5">
                      <svg
                        class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                        :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                      <span class="font-bold text-[#1D1D1F] text-[11px] sm:text-[12px] leading-tight truncate">{{ r.ruta }}</span>
                    </div>
                    <div class="text-[9.5px] sm:text-[11px] text-[#6E6E73] font-medium leading-tight truncate pl-4 sm:pl-5">
                      {{ r.vendedor || 'No Asignado' }}
                    </div>
                  </td>

                  <!-- Compradores -->
                  <td class="px-2.5 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                    {{ getCompradores(r) }}
                  </td>

                  <!-- Cover 1: Sedal 340 -->
                  <td class="px-2.5 py-1.5 text-center font-mono font-bold text-purple-800 bg-purple-50/20 border-r border-purple-200/40">
                    <span v-if="getCover1(r) > 0" class="inline-block px-1.5 py-0.2 rounded bg-purple-100/70 text-purple-800">
                      {{ getCover1(r) }}
                    </span>
                    <span v-else class="text-[#86868B]/60 font-normal">0</span>
                  </td>

                  <!-- Cover 2: Pepsodent -->
                  <td class="px-2.5 py-1.5 text-center font-mono font-bold text-sky-800 bg-sky-50/20 border-r border-sky-200/40">
                    <span v-if="getCover2(r) > 0" class="inline-block px-1.5 py-0.2 rounded bg-sky-100/70 text-sky-800">
                      {{ getCover2(r) }}
                    </span>
                    <span v-else class="text-[#86868B]/60 font-normal">0</span>
                  </td>

                  <!-- Cover 3: Sedal 40 -->
                  <td class="px-2.5 py-1.5 text-center font-mono font-bold text-emerald-800 bg-emerald-50/20 border-r border-emerald-200/40">
                    <span v-if="getCover3(r) > 0" class="inline-block px-1.5 py-0.2 rounded bg-emerald-100/70 text-emerald-800">
                      {{ getCover3(r) }}
                    </span>
                    <span v-else class="text-[#86868B]/60 font-normal">0</span>
                  </td>

                  <!-- Con Cover (Total) -->
                  <td class="px-2.5 py-1.5 text-center font-mono font-bold text-[#1E6B37] bg-emerald-50/20 border-r border-black/[0.04]">
                    {{ getClientesConDescuento(r) }}
                  </td>

                  <!-- Sin Cover -->
                  <td class="px-2.5 py-1.5 text-center font-mono text-[#86868B] border-r border-black/[0.04]">
                    {{ getClientesSinDescuento(r) }}
                  </td>

                  <!-- % Covers -->
                  <td class="px-2.5 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                    <span
                      v-if="getPctCovers(r) !== null"
                      :class="getPctCovers(r) >= 50.0 ? 'text-[#1E6B37]' : 'text-[#C9342C]'"
                    >
                      {{ Number(getPctCovers(r)).toFixed(1) }}%
                    </span>
                    <span v-else class="text-[#86868B]">—</span>
                  </td>

                  <!-- Estatus -->
                  <td class="px-2.5 py-1.5 text-center border-r border-black/[0.04]">
                    <span
                      v-if="getPctCovers(r) !== null"
                      class="inline-flex items-center justify-center w-6 h-6 rounded-[6px] text-[12px] font-bold leading-none select-none"
                      :class="getCumpleKpi(r)
                        ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                        : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                      :title="getCumpleKpi(r) ? 'Cumple Covers (≥ 50%)' : 'Déficit (< 50%)'"
                    >
                      {{ getCumpleKpi(r) ? '✓' : '✕' }}
                    </span>
                    <span v-else class="text-[#86868B] text-[11px] font-mono">—</span>
                  </td>

                  <!-- Puntos (5) -->
                  <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                    <span
                      v-if="getPuntosObtenidos(r) > 0"
                      class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                    >
                      5
                    </span>
                    <span v-else class="text-[#86868B] text-[11px]">—</span>
                  </td>
                </tr>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- Fila Acordeón: Detalle de Tiendas Compradoras               -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <tr v-if="acordeonAbierto[r.ruta]">
                  <td colspan="11" class="p-3 bg-white/70 border-b border-black/[0.06]">
                    <!-- Estado de carga -->
                    <div v-if="cargandoDetalle[r.ruta]" class="p-6 text-center text-[#6E6E73] text-[11px] font-medium space-y-2">
                      <div class="h-4 w-48 mx-auto skeleton"></div>
                      <div>Auditando los 3 tipos de covers para {{ r.ruta }}...</div>
                    </div>

                    <!-- Estado de error -->
                    <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold bg-[#FDF0EF] rounded-[8px] border border-[#FADCD5]">
                      {{ errorDetalle[r.ruta] }}
                    </div>

                    <!-- Detalle auditado en tiendas -->
                    <div v-else-if="detallePorRuta[r.ruta]" class="space-y-2">
                      <!-- Barra de Auditoría de Ruta con Filtros de Covers -->
                      <div class="apple-glass-card rounded-[10px] p-2 bg-white border border-black/[0.06] flex flex-wrap items-center justify-between gap-2 text-[11px]">
                        <!-- Contadores de la ruta -->
                        <div class="flex items-center gap-2 flex-wrap">
                          <span class="text-[#6E6E73]">
                            PDVs con compra: <strong class="text-[#1D1D1F] font-mono">{{ detallePorRuta[r.ruta].tiendas?.length || 0 }}</strong>
                          </span>
                          <span class="text-purple-800">
                            · C1 (Sedal 340): <strong class="font-mono font-bold">{{ detallePorRuta[r.ruta].totales?.cover1_count || 0 }}</strong>
                          </span>
                          <span class="text-sky-800">
                            · C2 (Pepsodent): <strong class="font-mono font-bold">{{ detallePorRuta[r.ruta].totales?.cover2_count || 0 }}</strong>
                          </span>
                          <span class="text-emerald-800">
                            · C3 (Sedal 40): <strong class="font-mono font-bold">{{ detallePorRuta[r.ruta].totales?.cover3_count || 0 }}</strong>
                          </span>
                          <span class="text-[#1E6B37]">
                            · Total con Cover: <strong class="font-mono font-bold">{{ detallePorRuta[r.ruta].totales?.clientes_con_descuento || 0 }}</strong>
                          </span>
                          <span class="text-[#86868B]">
                            · Sin Cover: <strong class="font-mono text-[#C9342C]">{{ detallePorRuta[r.ruta].totales?.clientes_sin_descuento || 0 }}</strong>
                          </span>
                        </div>

                        <!-- Filtro de Tiendas por Cover -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                          <span class="text-[10px] text-[#86868B] font-semibold uppercase">Filtrar PDVs:</span>
                          <div class="inline-flex rounded-[6px] bg-black/[0.04] p-0.5 border border-black/[0.05] text-[10px]">
                            <button
                              type="button"
                              @click="setFiltroTienda(r.ruta, 'todos')"
                              class="px-2 py-0.5 rounded-[4px] font-semibold cursor-pointer transition-colors"
                              :class="getFiltroTienda(r.ruta) === 'todos' ? 'bg-white text-[#1D1D1F] shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                            >
                              Todos
                            </button>
                            <button
                              type="button"
                              @click="setFiltroTienda(r.ruta, 'cover1')"
                              class="px-2 py-0.5 rounded-[4px] font-semibold cursor-pointer transition-colors"
                              :class="getFiltroTienda(r.ruta) === 'cover1' ? 'bg-purple-100 text-purple-900 font-bold shadow-xs' : 'text-purple-700 hover:text-purple-900'"
                            >
                              Cover 1
                            </button>
                            <button
                              type="button"
                              @click="setFiltroTienda(r.ruta, 'cover2')"
                              class="px-2 py-0.5 rounded-[4px] font-semibold cursor-pointer transition-colors"
                              :class="getFiltroTienda(r.ruta) === 'cover2' ? 'bg-sky-100 text-sky-900 font-bold shadow-xs' : 'text-sky-700 hover:text-sky-900'"
                            >
                              Cover 2
                            </button>
                            <button
                              type="button"
                              @click="setFiltroTienda(r.ruta, 'cover3')"
                              class="px-2 py-0.5 rounded-[4px] font-semibold cursor-pointer transition-colors"
                              :class="getFiltroTienda(r.ruta) === 'cover3' ? 'bg-emerald-100 text-emerald-900 font-bold shadow-xs' : 'text-emerald-700 hover:text-emerald-900'"
                            >
                              Cover 3
                            </button>
                            <button
                              type="button"
                              @click="setFiltroTienda(r.ruta, 'sin_cover')"
                              class="px-2 py-0.5 rounded-[4px] font-semibold cursor-pointer transition-colors"
                              :class="getFiltroTienda(r.ruta) === 'sin_cover' ? 'bg-[#FDF0EF] text-[#C9342C] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#C9342C]'"
                            >
                              Sin Cover
                            </button>
                          </div>

                          <!-- Botones expandir / colapsar -->
                          <div class="flex items-center gap-1 border-l border-black/[0.08] pl-2">
                            <button
                              type="button"
                              @click="expandirTodasTiendas(r.ruta, getTiendasFiltradas(r.ruta), true)"
                              class="px-2 py-0.5 rounded-[5px] bg-white hover:bg-slate-100 text-[#0071E3] font-semibold text-[10px] border border-black/[0.08] cursor-pointer"
                            >
                              Expandir
                            </button>
                            <button
                              type="button"
                              @click="expandirTodasTiendas(r.ruta, getTiendasFiltradas(r.ruta), false)"
                              class="px-2 py-0.5 rounded-[5px] bg-white hover:bg-slate-100 text-[#86868B] font-semibold text-[10px] border border-black/[0.08] cursor-pointer"
                            >
                              Colapsar
                            </button>
                          </div>
                        </div>
                      </div>

                      <!-- Sin tiendas que coincidan con el filtro -->
                      <div v-if="getTiendasFiltradas(r.ruta).length === 0" class="p-5 text-center text-[#86868B] text-[11px] apple-glass-card rounded-[10px] bg-white">
                        No se encontraron tiendas para el filtro seleccionado en esta ruta.
                      </div>

                      <!-- Lista de Tiendas Compradoras Filtradas -->
                      <div v-else class="space-y-1.5">
                        <div
                          v-for="(t, tIdx) in getTiendasFiltradas(r.ruta)"
                          :key="t.cliente_id"
                          class="apple-glass-card rounded-[10px] py-1.5 px-2.5 border border-black/[0.06] bg-white space-y-1.5 transition-all shadow-xs hover:border-black/[0.12]"
                        >
                          <!-- Cabecera de la Tienda -->
                          <div class="flex flex-wrap items-center justify-between gap-1.5 pb-1 border-b border-black/[0.05] text-[11px]">
                            <!-- Identificación del Cliente y Badges de Covers -->
                            <div class="flex items-center gap-1.5 min-w-0 flex-wrap">
                              <button
                                type="button"
                                @click="toggleCliente(r.ruta, t.cliente_id, tIdx)"
                                class="font-bold text-[#1D1D1F] hover:text-[#0071E3] text-[12px] flex items-center gap-1.5 cursor-pointer truncate"
                              >
                                <svg
                                  class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                                  :class="isClienteAbierto(r.ruta, t.cliente_id, tIdx) ? 'rotate-90 text-[#0071E3]' : ''"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                                <span class="truncate">{{ t.cliente }}</span>
                              </button>

                              <span class="text-[9.5px] text-[#86868B] font-mono px-1.5 py-0.2 rounded bg-[#F5F5F7] border border-[#E5E5EA] shrink-0">
                                #{{ t.codigo_cliente || t.cliente_id }}
                              </span>

                              <!-- Badges de Covers aplicados a esta tienda -->
                              <span
                                v-if="t.tiene_cover1"
                                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-purple-50 text-purple-700 border border-purple-200 shrink-0"
                                title="Cover 1: Sedal 340ml (3u) → 1 Sedal Toque de Seda bonificado"
                              >
                                Cover 1 · Sedal 340
                              </span>

                              <span
                                v-if="t.tiene_cover2"
                                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-sky-50 text-sky-700 border border-sky-200 shrink-0"
                                title="Cover 2: Pepsodent (3u) → 1 Sedal Toque de Seda bonificado"
                              >
                                Cover 2 · Pepsodent
                              </span>

                              <span
                                v-if="t.tiene_cover3"
                                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0"
                                title="Cover 3: Sedal 40ml (12u → 2 bonificadas)"
                              >
                                Cover 3 · Sedal 40ml
                              </span>

                              <span
                                v-if="!t.cumple"
                                class="px-1.5 py-0.2 rounded text-[9.5px] font-bold bg-gray-100 text-gray-500 border border-gray-200 shrink-0"
                              >
                                Sin Cover
                              </span>
                            </div>

                            <!-- Métricas de la tienda -->
                            <div class="flex items-center gap-2.5 text-[10.5px] font-mono flex-wrap">
                              <span class="text-[#6E6E73]">
                                Items: <strong class="text-[#1D1D1F] font-bold">{{ t.total_items_hoy }}</strong>
                              </span>
                              <span v-if="t.descuento_total_bs > 0" class="text-[#1E6B37]">
                                Descuento: <strong class="font-bold">Bs {{ formatBs(t.descuento_total_bs) }}</strong>
                              </span>
                              <span class="text-[#6E6E73]">
                                Total Pedido: <strong class="text-[#0071E3] font-bold">Bs {{ formatBs(t.total_bs_hoy) }}</strong>
                              </span>
                            </div>
                          </div>

                          <!-- Contenido Desplegable: Tabla de Productos Comprados con Tags de Covers -->
                          <div
                            v-if="isClienteAbierto(r.ruta, t.cliente_id, tIdx)"
                            class="pt-1"
                          >
                            <div class="overflow-x-auto max-h-64 custom-scroll rounded-[8px] border border-black/[0.06] bg-white">
                              <table class="w-full text-left border-collapse text-[11px]">
                                <thead class="sticky top-0 bg-[#F8F8FA] border-b border-black/[0.06] text-[9.5px] uppercase font-bold text-[#6E6E73]">
                                  <tr>
                                    <th class="px-2.5 py-1.5 w-16">Código</th>
                                    <th class="px-2.5 py-1.5">Producto</th>
                                    <th class="px-2.5 py-1.5 text-center w-24">Etiqueta Cover</th>
                                    <th class="px-2.5 py-1.5 text-center w-12">Cant</th>
                                    <th class="px-2.5 py-1.5 text-right w-20">P. Lista</th>
                                    <th class="px-2.5 py-1.5 text-right w-24">Descuento</th>
                                    <th class="px-2.5 py-1.5 text-right w-20">Total Bs</th>
                                  </tr>
                                </thead>
                                <tbody class="divide-y divide-black/[0.03]">
                                  <tr
                                    v-for="it in t.items"
                                    :key="it.producto_id || it.codigo_producto"
                                    :class="it.con_descuento ? 'bg-emerald-50/40 font-medium' : 'hover:bg-black/[0.02]'"
                                  >
                                    <td class="px-2.5 py-1 font-mono text-[9.5px] text-[#86868B]">
                                      {{ it.codigo_producto || it.producto_id }}
                                    </td>
                                    <td class="px-2.5 py-1 text-[#1D1D1F]">
                                      <span class="truncate max-w-[280px] block" :title="it.producto">
                                        {{ it.producto }}
                                      </span>
                                    </td>
                                    <td class="px-2.5 py-1 text-center font-mono">
                                      <!-- Distintivos según tipo de cover -->
                                      <span
                                        v-if="it.tag === 'C1 BONIF'"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-purple-600 text-white"
                                        title="Sedal Toque de Seda 40ml Bonificado (Cover 1)"
                                      >
                                        C1 BONIF
                                      </span>
                                      <span
                                        v-else-if="it.tag === 'C2 BONIF'"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-sky-600 text-white"
                                        title="Sedal Toque de Seda 40ml Bonificado (Cover 2)"
                                      >
                                        C2 BONIF
                                      </span>
                                      <span
                                        v-else-if="it.tag === 'C1+C2 BONIF'"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-indigo-600 text-white"
                                        title="Sedal Toque de Seda 40ml Bonificado (Cover 1 y 2)"
                                      >
                                        C1+C2 BONIF
                                      </span>
                                      <span
                                        v-else-if="it.tag === 'C3 PROMO'"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-emerald-600 text-white"
                                        title="Sedal 40ml Promo 2x12 (Cover 3)"
                                      >
                                        C3 PROMO
                                      </span>
                                      <span
                                        v-else-if="it.tag === 'C1 REQ'"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-semibold bg-purple-100 text-purple-800 border border-purple-200"
                                        title="Sedal 340ml Requerido (Cover 1)"
                                      >
                                        C1 REQ
                                      </span>
                                      <span
                                        v-else-if="it.tag === 'C2 REQ'"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-semibold bg-sky-100 text-sky-800 border border-sky-200"
                                        title="Pepsodent Requerido (Cover 2)"
                                      >
                                        C2 REQ
                                      </span>
                                      <span
                                        v-else-if="it.con_descuento"
                                        class="inline-block px-1.5 py-0.2 rounded text-[8.5px] font-bold bg-amber-500 text-white"
                                        title="Producto con Descuento"
                                      >
                                        DESC
                                      </span>
                                      <span v-else class="text-[#86868B]/40 text-[9px]">—</span>
                                    </td>
                                    <td class="px-2.5 py-1 text-center font-mono">
                                      {{ it.cantidad }}
                                    </td>
                                    <td class="px-2.5 py-1 text-right font-mono text-[#6E6E73]">
                                      Bs {{ formatBs(it.precio_lista) }}
                                    </td>
                                    <td class="px-2.5 py-1 text-right font-mono font-bold" :class="it.con_descuento ? 'text-[#1E6B37]' : 'text-[#86868B]'">
                                      {{ it.con_descuento ? `Bs ${formatBs(it.descuento)}` : '—' }}
                                    </td>
                                    <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                                      Bs {{ formatBs(it.monto_final) }}
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'
import { usePreventaStore } from '@/Stores/preventaStore'

const store = usePreventaStore()

const sortCol = ref('pos')
const sortDir = ref('asc')
const filtroEstado = ref('todos') // 'todos', 'cumplen', 'deficit'
const busquedaRuta = ref('')

const acordeonAbierto = reactive({})
const cargandoDetalle = reactive({})
const errorDetalle = reactive({})
const detallePorRuta = reactive({})
const clientesAbiertos = reactive({})
const filtrosTiendasPorRuta = reactive({}) // r.ruta -> 'todos', 'cover1', 'cover2', 'cover3', 'sin_cover'

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
    Object.keys(clientesAbiertos).forEach(k => delete clientesAbiertos[k])
    Object.keys(filtrosTiendasPorRuta).forEach(k => delete filtrosTiendasPorRuta[k])
  }
)

function formatBs(val) {
  const n = Number(val || 0)
  return n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function getCompradores(r) {
  return r.kpi8_covers?.total_compradores
    ?? detallePorRuta[r.ruta]?.totales?.compradores_hoy
    ?? r.kpis?.covers?.metricas?.total_compradores
    ?? '—'
}

function getCover1(r) {
  return r.kpi8_covers?.cover1_count
    ?? detallePorRuta[r.ruta]?.totales?.cover1_count
    ?? r.kpis?.covers?.metricas?.cover1_count
    ?? 0
}

function getCover2(r) {
  return r.kpi8_covers?.cover2_count
    ?? detallePorRuta[r.ruta]?.totales?.cover2_count
    ?? r.kpis?.covers?.metricas?.cover2_count
    ?? 0
}

function getCover3(r) {
  return r.kpi8_covers?.cover3_count
    ?? detallePorRuta[r.ruta]?.totales?.cover3_count
    ?? r.kpis?.covers?.metricas?.cover3_count
    ?? 0
}

function getClientesConDescuento(r) {
  return r.kpi8_covers?.cumplen
    ?? detallePorRuta[r.ruta]?.totales?.clientes_con_descuento
    ?? r.kpis?.covers?.metricas?.cumplen
    ?? '—'
}

function getClientesSinDescuento(r) {
  if (detallePorRuta[r.ruta]?.totales?.clientes_sin_descuento !== undefined) {
    return detallePorRuta[r.ruta].totales.clientes_sin_descuento
  }
  const comp = Number(getCompradores(r))
  const conDesc = Number(getClientesConDescuento(r))
  if (!isNaN(comp) && !isNaN(conDesc)) {
    return Math.max(0, comp - conDesc)
  }
  return '—'
}

function getPctCovers(r) {
  if (r.kpi8_covers?.tasa_pct !== undefined && r.kpi8_covers?.tasa_pct !== null) {
    return Number(r.kpi8_covers.tasa_pct)
  }
  if (detallePorRuta[r.ruta]?.totales?.porcentaje_covers_ruta !== undefined) {
    return Number(detallePorRuta[r.ruta].totales.porcentaje_covers_ruta)
  }
  if (r.kpis?.covers?.metricas?.tasa_pct !== undefined) {
    return Number(r.kpis.covers.metricas.tasa_pct)
  }
  return null
}

function getCumpleKpi(r) {
  if (r.kpi8_covers?.cumple !== undefined) {
    return Boolean(r.kpi8_covers.cumple)
  }
  if (detallePorRuta[r.ruta]?.totales?.cumple_umbral !== undefined) {
    return Boolean(detallePorRuta[r.ruta].totales.cumple_umbral)
  }
  const pct = getPctCovers(r)
  return pct !== null && pct >= 50.0
}

function getPuntosObtenidos(r) {
  if (r.kpi8_covers?.puntos !== undefined) {
    return Number(r.kpi8_covers.puntos)
  }
  if (detallePorRuta[r.ruta]?.totales?.puntos_obtenidos !== undefined) {
    return Number(detallePorRuta[r.ruta].totales.puntos_obtenidos)
  }
  return getCumpleKpi(r) ? 5.0 : 0.0
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = ['ruta', 'pos'].includes(col) ? 'asc' : 'desc'
  }
}

// ── Métricas Globales Computadas ──────────────────────────────────────────
const totalRutasCount = computed(() => (store.rutasFiltradas || []).length)

const rutasCumplenCount = computed(() => {
  return (store.rutasFiltradas || []).filter(r => getCumpleKpi(r)).length
})

const pctRutasCumplen = computed(() => {
  return totalRutasCount.value > 0
    ? (rutasCumplenCount.value / totalRutasCount.value) * 100.0
    : 0.0
})

const globalCompradores = computed(() => {
  return (store.rutasFiltradas || []).reduce((acc, r) => acc + (Number(getCompradores(r)) || 0), 0)
})

const globalConCover = computed(() => {
  return (store.rutasFiltradas || []).reduce((acc, r) => acc + (Number(getClientesConDescuento(r)) || 0), 0)
})

const globalSinCover = computed(() => {
  return (store.rutasFiltradas || []).reduce((acc, r) => acc + (Number(getClientesSinDescuento(r)) || 0), 0)
})

const globalCover1 = computed(() => {
  return (store.rutasFiltradas || []).reduce((acc, r) => acc + (Number(getCover1(r)) || 0), 0)
})

const globalCover2 = computed(() => {
  return (store.rutasFiltradas || []).reduce((acc, r) => acc + (Number(getCover2(r)) || 0), 0)
})

const globalCover3 = computed(() => {
  return (store.rutasFiltradas || []).reduce((acc, r) => acc + (Number(getCover3(r)) || 0), 0)
})

// ── Rutas Ordenadas y Filtradas ───────────────────────────────────────────
const rutasFiltradasPorEstado = computed(() => {
  let lista = [...(store.rutasFiltradas || [])]

  // Filtro de búsqueda por texto (ruta o vendedor)
  const q = busquedaRuta.value.trim().toLowerCase()
  if (q) {
    lista = lista.filter(r =>
      String(r.ruta || '').toLowerCase().includes(q) ||
      String(r.vendedor || '').toLowerCase().includes(q)
    )
  }

  // Filtro por estado
  if (filtroEstado.value === 'cumplen') {
    lista = lista.filter(r => getCumpleKpi(r))
  } else if (filtroEstado.value === 'deficit') {
    lista = lista.filter(r => !getCumpleKpi(r))
  }

  const col = sortCol.value
  const dir = sortDir.value === 'asc' ? 1 : -1

  lista.sort((a, b) => {
    if (col === 'ruta') {
      return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    let valA = 0
    let valB = 0
    if (col === 'pos') {
      valA = a.rank ?? 0
      valB = b.rank ?? 0
    } else if (col === 'compradores') {
      valA = Number(getCompradores(a)) || 0
      valB = Number(getCompradores(b)) || 0
    } else if (col === 'cover1') {
      valA = Number(getCover1(a)) || 0
      valB = Number(getCover1(b)) || 0
    } else if (col === 'cover2') {
      valA = Number(getCover2(a)) || 0
      valB = Number(getCover2(b)) || 0
    } else if (col === 'cover3') {
      valA = Number(getCover3(a)) || 0
      valB = Number(getCover3(b)) || 0
    } else if (col === 'con_descuento') {
      valA = Number(getClientesConDescuento(a)) || 0
      valB = Number(getClientesConDescuento(b)) || 0
    } else if (col === 'sin_descuento') {
      valA = Number(getClientesSinDescuento(a)) || 0
      valB = Number(getClientesSinDescuento(b)) || 0
    } else if (col === 'pct_covers') {
      valA = getPctCovers(a) ?? -1
      valB = getPctCovers(b) ?? -1
    } else if (col === 'cumple') {
      valA = getCumpleKpi(a) ? 1 : 0
      valB = getCumpleKpi(b) ? 1 : 0
    } else if (col === 'puntos') {
      valA = getPuntosObtenidos(a)
      valB = getPuntosObtenidos(b)
    }

    if (valA !== valB) {
      return dir * (valA - valB)
    }
    return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
  })

  return lista
})

// ── Control de acordeón de tiendas ──────────────────────────────────────
function isClienteAbierto(ruta, clienteId, idx) {
  const key = `${ruta}-${clienteId}-${idx}`
  return Boolean(clientesAbiertos[key])
}

function toggleCliente(ruta, clienteId, idx) {
  const key = `${ruta}-${clienteId}-${idx}`
  clientesAbiertos[key] = !isClienteAbierto(ruta, clienteId, idx)
}

function expandirTodasTiendas(ruta, tiendas, abrir = true) {
  (tiendas || []).forEach((t, idx) => {
    const key = `${ruta}-${t.cliente_id}-${idx}`
    clientesAbiertos[key] = abrir
  })
}

function getFiltroTienda(ruta) {
  return filtrosTiendasPorRuta[ruta] || 'todos'
}

function setFiltroTienda(ruta, val) {
  filtrosTiendasPorRuta[ruta] = val
}

function getTiendasFiltradas(ruta) {
  const tiendas = detallePorRuta[ruta]?.tiendas || []
  const f = getFiltroTienda(ruta)
  if (f === 'cover1') {
    return tiendas.filter(t => t.tiene_cover1)
  }
  if (f === 'cover2') {
    return tiendas.filter(t => t.tiene_cover2)
  }
  if (f === 'cover3') {
    return tiendas.filter(t => t.tiene_cover3)
  }
  if (f === 'sin_cover') {
    return tiendas.filter(t => !t.cumple)
  }
  return tiendas
}

// ── Carga y Acordeón de Ruta ─────────────────────────────────────────────
async function cargarDetalleRuta(ruta, force = false) {
  if (!force && detallePorRuta[ruta]) return

  cargandoDetalle[ruta] = true
  errorDetalle[ruta] = null
  try {
    const res = await axios.post('/supervisor/preventas/cumplimiento/covers/detalle', {
      fecha: store.fecha,
      ruta
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar auditoría de covers'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

async function toggleCoversAcordeon(r) {
  const ruta = r.ruta
  if (acordeonAbierto[ruta]) {
    acordeonAbierto[ruta] = false
    return
  }
  acordeonAbierto[ruta] = true
  await cargarDetalleRuta(ruta)
}
</script>
