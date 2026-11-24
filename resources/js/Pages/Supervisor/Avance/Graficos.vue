<template>
  <SupervisorLayout title="Avance > Gráficos de Avance">
    <div class="space-y-2.5 font-sans select-none">
      <!-- ── Subnavegación de Módulo Avance ── -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- ── Barra Superior de Filtros Transversales ── -->
      <AvanceFiltrosBar @consultar="consultarGraficos" />

      <!-- ── Tarjetas Resumen MTD del KPI Seleccionado ── -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
        <!-- Card 1: Meta del Mes -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Meta del Mes</span>
            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-[#1D1D1F] font-mono">
              {{ kpiActivo === 'volumen' ? fmtBs(metaTotal) : fmt(metaTotal) }}
            </span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">
            {{ kpiActivo === 'volumen' ? 'Cuota en Bs' : 'Cartera activa' }}
          </span>
        </div>

        <!-- Card 2: Logrado MTD -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold uppercase tracking-wider" :class="kpiActivo === 'volumen' ? 'text-[#0071E3]' : 'text-emerald-700'">
              Logrado MTD
            </span>
            <span class="w-2 h-2 rounded-full" :class="kpiActivo === 'volumen' ? 'bg-[#0071E3]' : 'bg-emerald-500'"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold font-mono" :class="kpiActivo === 'volumen' ? 'text-[#0071E3]' : 'text-emerald-700'">
              {{ kpiActivo === 'volumen' ? fmtBs(logradoTotal) : fmt(logradoTotal) }}
            </span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">
            {{ fechas.length }} días evaluados
          </span>
        </div>

        <!-- Card 3: % Avance MTD -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between" :class="pctTotal >= 100 ? 'bg-emerald-50/20 border-emerald-400/30' : ''">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">% Cumplimiento</span>
            <span class="w-2 h-2 rounded-full" :class="pctTotal >= 100 ? 'bg-emerald-500' : (pctTotal >= 75 ? 'bg-blue-500' : 'bg-amber-500')"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-lg font-bold font-mono" :class="pctTotal >= 100 ? 'text-emerald-700' : (pctTotal >= 75 ? 'text-[#0071E3]' : 'text-amber-700')">
              {{ fmtPct(pctTotal) }}
            </span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">
            {{ pctTotal >= 100 ? 'Meta Superada' : 'En Progreso' }}
          </span>
        </div>

        <!-- Card 4: Ritmo Ideal a la Fecha -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Ritmo Ideal</span>
            <span class="px-1 py-0.2 rounded text-[8.5px] font-bold bg-black/[0.04] text-[#6E6E73]">Lineal</span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-base font-bold text-[#1D1D1F] font-mono">
              {{ kpiActivo === 'volumen' ? fmtBs(ritmoIdealActual) : fmt(ritmoIdealActual) }}
            </span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">
            Día {{ fechas.length }} de {{ totalDiasMesEstimados }}
          </span>
        </div>

        <!-- Card 5: Brecha vs Ritmo -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between" :class="brechaRitmo >= 0 ? 'bg-emerald-50/15' : 'bg-rose-50/15'">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Brecha vs Ritmo</span>
            <span class="w-2 h-2 rounded-full" :class="brechaRitmo >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-base font-bold font-mono" :class="brechaRitmo >= 0 ? 'text-emerald-700' : 'text-rose-700'">
              {{ brechaRitmo >= 0 ? '+' : '' }}{{ kpiActivo === 'volumen' ? fmtBs(brechaRitmo) : fmt(brechaRitmo) }}
            </span>
          </div>
          <span class="text-[9px] text-[#86868B] truncate mt-0.5">
            {{ brechaRitmo >= 0 ? 'Por encima del ritmo' : 'Por debajo del ritmo' }}
          </span>
        </div>

        <!-- Card 6: Proyección de Cierre -->
        <div class="apple-glass-card rounded-[14px] p-2.5 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[9.5px] font-bold text-[#86868B] uppercase tracking-wider">Proyección Fin de Mes</span>
            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-base font-bold text-indigo-700 font-mono">
              {{ kpiActivo === 'volumen' ? fmtBs(proyeccionCierre) : fmt(proyeccionCierre) }}
            </span>
          </div>
          <span class="text-[9px] text-indigo-600 truncate mt-0.5 font-semibold">
            Run-rate: {{ fmtPct(proyeccionPct) }}
          </span>
        </div>
      </div>

      <!-- ── Barra de Controles Interactivos del Gráfico ── -->
      <div class="apple-glass-card rounded-[14px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Selector 1: KPI (Volumen vs Cobertura) -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[9px] text-[11px] font-semibold">
            <button
              type="button"
              @click="kpiActivo = 'volumen'"
              class="px-3 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5"
              :class="kpiActivo === 'volumen' ? 'bg-white text-[#0071E3] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
              <span>1. Volumen General (Bs)</span>
            </button>
            <button
              type="button"
              @click="kpiActivo = 'cobertura'"
              class="px-3 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5"
              :class="kpiActivo === 'cobertura' ? 'bg-white text-emerald-700 font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>2. Cobertura 100% (Clientes)</span>
            </button>
          </div>

          <!-- Selector 2: Modo Temporal (Acumulado MTD vs Diario) -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[9px] text-[11px] font-semibold">
            <button
              type="button"
              @click="modoTemporal = 'acumulado'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="modoTemporal === 'acumulado' ? 'bg-white text-[#1D1D1F] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Curva Acumulada MTD
            </button>
            <button
              type="button"
              @click="modoTemporal = 'diario'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="modoTemporal === 'diario' ? 'bg-white text-[#1D1D1F] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Evolución Diaria
            </button>
          </div>

          <!-- Selector 3: Consolidado vs Por Rutas -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[9px] text-[11px] font-semibold">
            <button
              type="button"
              @click="vistaModo = 'rutas'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="vistaModo === 'rutas' ? 'bg-white text-[#1D1D1F] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Por Rutas ({{ rutasActivasVisibles.length }})
            </button>
            <button
              type="button"
              @click="vistaModo = 'consolidado'"
              class="px-2.5 py-1 rounded-[7px] transition-all cursor-pointer whitespace-nowrap"
              :class="vistaModo === 'consolidado' ? 'bg-white text-[#1D1D1F] font-bold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              Consolidado Global
            </button>
          </div>
        </div>

        <!-- Opciones adicionales: Mostrar Ritmo Ideal y Selector Rápido de Rutas -->
        <div class="flex items-center gap-2 ml-auto">
          <label class="flex items-center gap-1.5 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="mostrarRitmoIdeal"
              class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer"
            />
            <span>Línea Ritmo Ideal</span>
          </label>
        </div>
      </div>

      <!-- ── Selector Rápido de Rutas (Pills de colores interactivos) ── -->
      <div v-if="vistaModo === 'rutas'" class="apple-glass-card rounded-[14px] p-2 flex flex-wrap items-center gap-1.5">
        <div class="flex items-center gap-1 mr-2 text-[10px] font-bold text-[#86868B] uppercase shrink-0">
          <span>Rutas:</span>
          <button type="button" @click="marcarTodasRutas" class="text-[#0071E3] hover:underline cursor-pointer">Todas</button>
          <span>&bull;</span>
          <button type="button" @click="marcarSoloTop5" class="text-[#0071E3] hover:underline cursor-pointer">Top 5</button>
          <span>&bull;</span>
          <button type="button" @click="desmarcarTodasRutas" class="text-[#86868B] hover:underline cursor-pointer">Ninguna</button>
        </div>

        <div class="flex flex-wrap items-center gap-1 max-h-24 overflow-y-auto custom-scroll flex-1">
          <button
            v-for="r in rutasDisponibles"
            :key="r.ruta"
            type="button"
            @click="toggleRutaActiva(r.ruta)"
            @mouseenter="hoveredRuta = r.ruta"
            @mouseleave="hoveredRuta = null"
            class="px-2 py-0.5 rounded-[6px] text-[10.5px] font-mono font-bold transition-all cursor-pointer flex items-center gap-1 border"
            :class="isRutaActiva(r.ruta)
              ? 'bg-white shadow-2xs'
              : 'opacity-40 hover:opacity-75 bg-transparent border-transparent text-[#6E6E73]'"
            :style="{
              borderColor: isRutaActiva(r.ruta) ? getColorRuta(r.ruta) : 'transparent',
              color: isRutaActiva(r.ruta) ? getColorRuta(r.ruta) : '#6E6E73'
            }"
          >
            <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: getColorRuta(r.ruta) }"></span>
            <span>{{ r.ruta }}</span>
          </button>
        </div>
      </div>

      <!-- ── SKELETON LOADER ── -->
      <div v-if="cargando" class="apple-glass-card rounded-[14px] p-8 animate-pulse space-y-3">
        <div class="h-6 bg-black/[0.05] rounded-md w-1/4"></div>
        <div class="h-64 bg-black/[0.03] rounded-xl"></div>
      </div>

      <!-- ── GRÁFICO DE LÍNEAS SVG INTERACTIVO ── -->
      <div v-else class="apple-glass-card rounded-[16px] p-3 sm:p-4 relative overflow-hidden shadow-xs border border-white/80">
        <!-- Título e Información del Gráfico -->
        <div class="flex items-center justify-between pb-2 mb-2 border-b border-black/[0.06]">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full" :class="kpiActivo === 'volumen' ? 'bg-[#0071E3]' : 'bg-emerald-500'"></span>
            <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wide">
              {{ kpiActivo === 'volumen' ? 'Curva de Volumen General (Bs)' : 'Curva de Cobertura 100% (Clientes)' }}
              &bull;
              <span class="text-[#0071E3] font-mono lowercase">
                {{ modoTemporal === 'acumulado' ? 'acumulado progresivo' : 'evolución por día' }}
              </span>
            </span>
          </div>

          <div class="flex items-center gap-3 text-[11px] text-[#86868B]">
            <div v-if="mostrarRitmoIdeal && modoTemporal === 'acumulado'" class="flex items-center gap-1.5">
              <span class="w-4 h-0.5 border-t-2 border-dashed border-[#86868B]"></span>
              <span>Ritmo Ideal Meta</span>
            </div>
            <div v-if="vistaModo === 'consolidado'" class="flex items-center gap-1.5">
              <span class="w-4 h-1 rounded-full" :class="kpiActivo === 'volumen' ? 'bg-[#0071E3]' : 'bg-emerald-600'"></span>
              <span class="font-bold text-[#1D1D1F]">Consolidado</span>
            </div>
          </div>
        </div>

        <!-- Contenedor Responsive del Gráfico SVG -->
        <div class="relative w-full overflow-x-auto custom-scroll" ref="chartContainerRef">
          <svg
            class="w-full h-80 sm:h-96 min-w-[700px] select-none"
            viewBox="0 0 1000 440"
            preserveAspectRatio="none"
            @mousemove="onSvgMouseMove"
            @mouseleave="onSvgMouseLeave"
          >
            <!-- Definición de Gradientes para Áreas -->
            <defs>
              <linearGradient id="gradConsolidadoVol" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#0071E3" stop-opacity="0.25" />
                <stop offset="100%" stop-color="#0071E3" stop-opacity="0.0" />
              </linearGradient>
              <linearGradient id="gradConsolidadoCob" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#10B981" stop-opacity="0.25" />
                <stop offset="100%" stop-color="#10B981" stop-opacity="0.0" />
              </linearGradient>
            </defs>

            <!-- 1. Cuadrícula Horizontal y Etiquetas del Eje Y -->
            <g class="grid-y">
              <template v-for="(valY, yIdx) in yAxisTicks" :key="yIdx">
                <line
                  :x1="padding.left"
                  :y1="getYPixel(valY)"
                  :x2="1000 - padding.right"
                  :y2="getYPixel(valY)"
                  stroke="#E5E5EA"
                  stroke-dasharray="3 3"
                  stroke-width="1"
                />
                <text
                  :x="padding.left - 8"
                  :y="getYPixel(valY) + 3.5"
                  fill="#86868B"
                  font-size="10"
                  font-family="monospace"
                  text-anchor="end"
                >
                  {{ formatEjeY(valY) }}
                </text>
              </template>
            </g>

            <!-- 2. Líneas Guía Verticales y Etiquetas del Eje X -->
            <g class="grid-x">
              <template v-for="(f, xIdx) in fechas" :key="f">
                <line
                  :x1="getXPixel(xIdx)"
                  :y1="padding.top"
                  :x2="getXPixel(xIdx)"
                  :y2="440 - padding.bottom"
                  stroke="#F2F2F7"
                  stroke-width="1"
                />
                <text
                  :x="getXPixel(xIdx)"
                  :y="440 - padding.bottom + 16"
                  fill="#86868B"
                  font-size="9.5"
                  font-family="sans-serif"
                  font-weight="500"
                  text-anchor="middle"
                >
                  {{ diasLabels[xIdx] || f }}
                </text>
              </template>
            </g>

            <!-- 3. Línea de Ritmo Ideal / Meta (Discontinua) -->
            <path
              v-if="mostrarRitmoIdeal && modoTemporal === 'acumulado' && ritmoIdealPuntos.length > 0"
              :d="buildPathD(ritmoIdealPuntos)"
              fill="none"
              stroke="#A1A1A6"
              stroke-width="2"
              stroke-dasharray="5 5"
              class="opacity-75"
            />

            <!-- 4. Modo CONSOLIDADO GLOBAL -->
            <template v-if="vistaModo === 'consolidado'">
              <!-- Área bajo la curva -->
              <path
                :d="buildAreaD(consolidadoPuntos)"
                :fill="kpiActivo === 'volumen' ? 'url(#gradConsolidadoVol)' : 'url(#gradConsolidadoCob)'"
              />
              <!-- Línea principal -->
              <path
                :d="buildPathD(consolidadoPuntos)"
                fill="none"
                :stroke="kpiActivo === 'volumen' ? '#0071E3' : '#10B981'"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <!-- Puntos -->
              <circle
                v-for="(p, pIdx) in consolidadoPuntos"
                :key="pIdx"
                :cx="p.x"
                :cy="p.y"
                r="3.5"
                :fill="kpiActivo === 'volumen' ? '#0071E3' : '#10B981'"
                stroke="#FFFFFF"
                stroke-width="1.5"
              />
            </template>

            <!-- 5. Modo MULTILÍNEA POR RUTAS -->
            <template v-else-if="vistaModo === 'rutas'">
              <template v-for="rObj in rutasActivasVisibles" :key="rObj.ruta">
                <!-- Área sutil si está en hover -->
                <path
                  v-if="hoveredRuta === rObj.ruta"
                  :d="buildAreaD(getRutaPuntos(rObj))"
                  :fill="getColorRuta(rObj.ruta)"
                  fill-opacity="0.12"
                />
                <!-- Línea de la ruta -->
                <path
                  :d="buildPathD(getRutaPuntos(rObj))"
                  fill="none"
                  :stroke="getColorRuta(rObj.ruta)"
                  :stroke-width="hoveredRuta === rObj.ruta ? 4 : 2.2"
                  :stroke-opacity="hoveredRuta && hoveredRuta !== rObj.ruta ? 0.25 : 1"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="transition-all duration-150"
                />
                <!-- Puntos -->
                <circle
                  v-for="(p, pIdx) in getRutaPuntos(rObj)"
                  :key="pIdx"
                  :cx="p.x"
                  :cy="p.y"
                  :r="hoveredRuta === rObj.ruta ? 4 : 2.5"
                  :fill="getColorRuta(rObj.ruta)"
                  :fill-opacity="hoveredRuta && hoveredRuta !== rObj.ruta ? 0.25 : 1"
                  stroke="#FFFFFF"
                  stroke-width="1"
                />
              </template>
            </template>

            <!-- 6. Línea Guía Vertical de Crosshair / Tooltip -->
            <line
              v-if="hoveredIndex !== null"
              :x1="getXPixel(hoveredIndex)"
              :y1="padding.top"
              :x2="getXPixel(hoveredIndex)"
              :y2="440 - padding.bottom"
              stroke="#0071E3"
              stroke-width="1.5"
              stroke-dasharray="2 2"
            />
          </svg>

          <!-- 7. Tooltip Flotante Estilo Apple Glass -->
          <div
            v-if="hoveredIndex !== null"
            class="absolute pointer-events-none z-30 transition-all duration-75"
            :style="tooltipStyle"
          >
            <div class="glass-dropdown rounded-[12px] p-2.5 shadow-float text-xs max-w-xs space-y-1.5 border border-white/80">
              <div class="flex items-center justify-between border-b border-black/[0.06] pb-1">
                <span class="font-bold text-[#1D1D1F]">{{ diasLabels[hoveredIndex] }}</span>
                <span class="text-[9.5px] font-mono text-[#86868B]">{{ fechas[hoveredIndex] }}</span>
              </div>

              <!-- Valores en Modo Consolidado -->
              <div v-if="vistaModo === 'consolidado'" class="space-y-0.5 font-mono text-[11px]">
                <div class="flex items-center justify-between">
                  <span class="text-[#6E6E73]">Logrado:</span>
                  <span class="font-bold text-[#1D1D1F]">
                    {{ kpiActivo === 'volumen' ? fmtBs(getConsolidadoValorEnFecha(hoveredIndex)) : fmt(getConsolidadoValorEnFecha(hoveredIndex)) }}
                  </span>
                </div>
                <div v-if="modoTemporal === 'acumulado'" class="flex items-center justify-between text-[10px]">
                  <span class="text-[#86868B]">Ritmo Ideal:</span>
                  <span class="text-[#86868B]">
                    {{ kpiActivo === 'volumen' ? fmtBs(ritmoIdealSeries[hoveredIndex]) : fmt(ritmoIdealSeries[hoveredIndex]) }}
                  </span>
                </div>
              </div>

              <!-- Valores en Modo Por Rutas -->
              <div v-else class="space-y-1 max-h-48 overflow-y-auto custom-scroll pr-1">
                <div
                  v-for="rObj in rutasActivasVisibles"
                  :key="rObj.ruta"
                  class="flex items-center justify-between text-[10.5px] font-mono"
                  :class="hoveredRuta === rObj.ruta ? 'font-bold bg-black/[0.04] p-0.5 rounded' : ''"
                >
                  <div class="flex items-center gap-1.5 truncate mr-2">
                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: getColorRuta(rObj.ruta) }"></span>
                    <span class="truncate">{{ rObj.ruta }}</span>
                  </div>
                  <span class="font-semibold text-[#1D1D1F] shrink-0">
                    {{ kpiActivo === 'volumen' ? fmtBs(getRutaValorEnFecha(rObj, hoveredIndex)) : fmt(getRutaValorEnFecha(rObj, hoveredIndex)) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── TABLA COMPARATIVA POR RUTA (Debajo del Gráfico) ── -->
      <div class="apple-glass-card rounded-[14px] overflow-hidden shadow-xs border border-white/60">
        <div class="p-2.5 bg-[#F8F8FA] border-b border-black/[0.06] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wide">
              Resumen de Cumplimiento por Ruta
            </span>
            <span class="text-[10px] text-[#86868B]">({{ rutasFiltradasTabla.length }} rutas)</span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="copiarTablaAlPortapapeles"
              class="px-2.5 py-1 text-[11px] font-semibold rounded-[8px] bg-white border border-[#E5E5EA] text-[#1D1D1F] hover:bg-[#F5F5F7] cursor-pointer shadow-xs whitespace-nowrap"
            >
              {{ copiadoExitoso ? '¡Copiado!' : 'Copiar Tabla' }}
            </button>
            <button
              type="button"
              @click="exportarExcel"
              class="px-2.5 py-1 text-[11px] font-semibold rounded-[8px] bg-emerald-50 border border-emerald-300 text-emerald-800 hover:bg-emerald-100 cursor-pointer shadow-xs whitespace-nowrap"
            >
              Exportar Excel
            </button>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-[#F8F8FA] border-b border-black/[0.06] text-[10.5px] font-bold text-[#86868B] uppercase tracking-wider select-none">
                <th class="py-2.5 px-3 whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('ruta')">
                  Ruta / Vendedor
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor('canal')">
                  Canal
                </th>
                <th class="py-2.5 px-3 text-right whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor(kpiActivo === 'volumen' ? 'meta_volumen' : 'meta_cobertura')">
                  Meta Mes ({{ kpiActivo === 'volumen' ? 'Bs' : 'Cli' }})
                </th>
                <th class="py-2.5 px-3 text-right whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor(kpiActivo === 'volumen' ? 'logrado_volumen' : 'logrado_cobertura')">
                  Logrado MTD
                </th>
                <th class="py-2.5 px-3 text-center whitespace-nowrap cursor-pointer hover:text-[#1D1D1F]" @click="ordenarPor(kpiActivo === 'volumen' ? 'pct_volumen' : 'pct_cobertura')">
                  % Avance
                </th>
                <th class="py-2.5 px-3 text-right whitespace-nowrap">
                  Promedio / Día
                </th>
                <th class="py-2.5 px-3 text-center whitespace-nowrap">
                  Tendencia MTD
                </th>
                <th class="py-2.5 px-2 text-center whitespace-nowrap">
                  Enfoque
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <tr
                v-for="r in rutasFiltradasTabla"
                :key="r.ruta"
                class="hover:bg-black/[0.02] transition-colors"
                :class="hoveredRuta === r.ruta ? 'bg-blue-50/30' : ''"
                @mouseenter="hoveredRuta = r.ruta"
                @mouseleave="hoveredRuta = null"
              >
                <!-- Ruta y Vendedor -->
                <td class="py-2 px-3 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: getColorRuta(r.ruta) }"></span>
                    <div>
                      <div class="font-bold text-[#1D1D1F] font-mono">{{ r.ruta }}</div>
                      <div class="text-[10px] text-[#6E6E73] truncate max-w-[170px]">{{ r.vendedor }}</div>
                    </div>
                  </div>
                </td>

                <!-- Canal -->
                <td class="py-2 px-2 text-center whitespace-nowrap">
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-black/[0.04] text-[#6E6E73]">
                    {{ r.canal }}
                  </span>
                </td>

                <!-- Meta Mes -->
                <td class="py-2 px-3 text-right whitespace-nowrap font-mono font-medium text-[#1D1D1F]">
                  {{ kpiActivo === 'volumen' ? fmtBs(r.meta_volumen) : fmt(r.meta_cobertura) }}
                </td>

                <!-- Logrado MTD -->
                <td class="py-2 px-3 text-right whitespace-nowrap font-mono font-bold" :class="kpiActivo === 'volumen' ? 'text-[#0071E3]' : 'text-emerald-700'">
                  {{ kpiActivo === 'volumen' ? fmtBs(r.logrado_volumen) : fmt(r.logrado_cobertura) }}
                </td>

                <!-- % Avance -->
                <td class="py-2 px-3 whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <div class="w-12 bg-black/[0.06] rounded-full h-1.5 overflow-hidden shrink-0 hidden sm:block">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="getPctRuta(r) >= 100 ? 'bg-emerald-500' : (getPctRuta(r) >= 70 ? 'bg-blue-500' : 'bg-amber-500')"
                        :style="{ width: `${Math.min(getPctRuta(r), 100)}%` }"
                      ></div>
                    </div>
                    <span
                      class="px-1.5 py-0.5 rounded text-[11px] font-bold font-mono"
                      :class="getPctRuta(r) >= 100 ? 'bg-emerald-100 text-emerald-800' : (getPctRuta(r) >= 70 ? 'bg-blue-100 text-[#0071E3]' : 'bg-amber-100 text-amber-800')"
                    >
                      {{ fmtPct(getPctRuta(r)) }}
                    </span>
                  </div>
                </td>

                <!-- Promedio por Día -->
                <td class="py-2 px-3 text-right whitespace-nowrap font-mono text-[11px] text-[#6E6E73]">
                  {{ kpiActivo === 'volumen' ? fmtBs(getPromedioDiarioRuta(r)) : `${getPromedioDiarioRuta(r)} cli/día` }}
                </td>

                <!-- Sparkline SVG -->
                <td class="py-2 px-3 text-center whitespace-nowrap">
                  <svg class="w-20 h-5 mx-auto" viewBox="0 0 80 20">
                    <path
                      :d="buildSparklineD(r)"
                      fill="none"
                      :stroke="getColorRuta(r.ruta)"
                      stroke-width="1.8"
                      stroke-linecap="round"
                    />
                  </svg>
                </td>

                <!-- Botón Enfoque en Gráfico -->
                <td class="py-2 px-2 text-center whitespace-nowrap">
                  <button
                    type="button"
                    @click="enfocarRuta(r.ruta)"
                    class="px-2 py-0.5 rounded text-[10px] font-bold transition-all cursor-pointer"
                    :class="hoveredRuta === r.ruta || (rutasSeleccionadasGrafico.size === 1 && rutasSeleccionadasGrafico.has(r.ruta))
                      ? 'bg-[#0071E3] text-white'
                      : 'bg-black/[0.04] text-[#6E6E73] hover:text-[#1D1D1F]'"
                  >
                    {{ rutasSeleccionadasGrafico.size === 1 && rutasSeleccionadasGrafico.has(r.ruta) ? 'Aislada' : 'Aislar' }}
                  </button>
                </td>
              </tr>
            </tbody>
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
const fechas = ref([])
const diasLabels = ref([])
const rutasData = ref([])
const consolidadoData = ref({})
const ritmoIdealData = ref({})

// Controles del gráfico
const kpiActivo = ref('volumen') // 'volumen' | 'cobertura'
const modoTemporal = ref('acumulado') // 'acumulado' | 'diario'
const vistaModo = ref('rutas') // 'rutas' | 'consolidado'
const mostrarRitmoIdeal = ref(true)

// Rutas seleccionadas para graficar
const rutasSeleccionadasGrafico = reactive(new Set())
const hoveredRuta = ref(null)
const hoveredIndex = ref(null)
const tooltipX = ref(0)
const tooltipY = ref(0)
const chartContainerRef = ref(null)

// Tabla inferior
const columnaOrden = ref('ruta')
const ordenAsc = ref(true)
const copiadoExitoso = ref(false)

// Paleta de colores armoniosa estilo Apple Pro
const PALETA_COLORES = [
  '#0071E3', // Apple Blue
  '#34C759', // Apple Green
  '#AF52DE', // Apple Purple
  '#FF9500', // Apple Orange
  '#5856D6', // Indigo
  '#FF2D55', // Pink / Rose
  '#00C7BE', // Teal
  '#FFCC00', // Yellow
  '#64D2FF', // Sky Blue
  '#E056FD', // Violet
  '#30B0C7', // Cyan
  '#686DE0', // Blurple
  '#4834D4', // Deep Indigo
  '#22A6B3', // Sea Blue
  '#EB4D4B', // Coral Red
  '#F0932B', // Tangerine
  '#6AB04C', // Pure Apple Green
  '#130F40', // Deep Navy
]

function getColorRuta(ruta) {
  const index = Math.abs(hashCode(ruta)) % PALETA_COLORES.length
  return PALETA_COLORES[index]
}

function hashCode(str) {
  let hash = 0
  for (let i = 0; i < str.length; i++) {
    hash = (hash << 5) - hash + str.charCodeAt(i)
    hash |= 0
  }
  return hash
}

// Formatters
const fmt = (v) => (Number(v) || 0).toLocaleString('es-BO')
const fmtBs = (v) => `Bs ${(Number(v) || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const fmtPct = (v) => `${(Number(v) || 0).toFixed(1)}%`

function formatEjeY(val) {
  if (kpiActivo.value === 'volumen') {
    if (val >= 1000000) return `Bs ${(val / 1000000).toFixed(1)}M`
    if (val >= 1000) return `Bs ${(val / 1000).toFixed(0)}k`
    return `Bs ${val.toFixed(0)}`
  }
  return `${val.toFixed(0)} cli`
}

// Margins del gráfico SVG (viewBox 1000x440)
const padding = { top: 25, right: 35, bottom: 45, left: 75 }

// Rutas disponibles según filtros
const rutasDisponibles = computed(() => rutasData.value || [])

const rutasActivasVisibles = computed(() => {
  if (rutasSeleccionadasGrafico.size === 0) return []
  return rutasDisponibles.value.filter(r => rutasSeleccionadasGrafico.has(r.ruta))
})

function isRutaActiva(ruta) {
  return rutasSeleccionadasGrafico.has(ruta)
}

function toggleRutaActiva(ruta) {
  if (rutasSeleccionadasGrafico.has(ruta)) {
    rutasSeleccionadasGrafico.delete(ruta)
  } else {
    rutasSeleccionadasGrafico.add(ruta)
  }
}

function marcarTodasRutas() {
  rutasSeleccionadasGrafico.clear()
  rutasDisponibles.value.forEach(r => rutasSeleccionadasGrafico.add(r.ruta))
}

function desmarcarTodasRutas() {
  rutasSeleccionadasGrafico.clear()
}

function marcarSoloTop5() {
  rutasSeleccionadasGrafico.clear()
  const top = [...rutasDisponibles.value]
    .sort((a, b) => (kpiActivo.value === 'volumen' ? b.logrado_volumen - a.logrado_volumen : b.logrado_cobertura - a.logrado_cobertura))
    .slice(0, 5)
  top.forEach(r => rutasSeleccionadasGrafico.add(r.ruta))
}

function enfocarRuta(ruta) {
  if (rutasSeleccionadasGrafico.size === 1 && rutasSeleccionadasGrafico.has(ruta)) {
    marcarTodasRutas()
  } else {
    rutasSeleccionadasGrafico.clear()
    rutasSeleccionadasGrafico.add(ruta)
    vistaModo.value = 'rutas'
  }
}

// Métricas de Cabecera
const metaTotal = computed(() => {
  return kpiActivo.value === 'volumen'
    ? (consolidadoData.value.meta_volumen || 0)
    : (consolidadoData.value.meta_cobertura || 0)
})

const logradoTotal = computed(() => {
  return kpiActivo.value === 'volumen'
    ? (consolidadoData.value.logrado_volumen || 0)
    : (consolidadoData.value.logrado_cobertura || 0)
})

const pctTotal = computed(() => {
  return kpiActivo.value === 'volumen'
    ? (consolidadoData.value.pct_volumen || 0)
    : (consolidadoData.value.pct_cobertura || 0)
})

const totalDiasMesEstimados = computed(() => {
  return Math.max(fechas.value.length, 24)
})

const ritmoIdealActual = computed(() => {
  const arr = ritmoIdealSeries.value
  return arr.length > 0 ? arr[arr.length - 1] : 0
})

const brechaRitmo = computed(() => {
  return logradoTotal.value - ritmoIdealActual.value
})

const proyeccionCierre = computed(() => {
  if (fechas.value.length === 0) return 0
  const rate = logradoTotal.value / fechas.value.length
  return roundNum(rate * totalDiasMesEstimados.value)
})

const proyeccionPct = computed(() => {
  return metaTotal.value > 0 ? (proyeccionCierre.value / metaTotal.value) * 100 : 0
})

function roundNum(n) {
  return Math.round((n + Number.EPSILON) * 100) / 100
}

// Series para el gráfico
const consolidadoSeries = computed(() => {
  const c = consolidadoData.value?.series || {}
  if (kpiActivo.value === 'volumen') {
    return modoTemporal.value === 'acumulado' ? (c.volumen_acumulado || []) : (c.volumen_diario || [])
  }
  return modoTemporal.value === 'acumulado' ? (c.cobertura_acumulada || []) : (c.cobertura_diaria || [])
})

const ritmoIdealSeries = computed(() => {
  const r = ritmoIdealData.value || {}
  if (kpiActivo.value === 'volumen') {
    return modoTemporal.value === 'acumulado' ? (r.volumen_acumulado || []) : Array(fechas.value.length).fill(r.volumen_diario || 0)
  }
  return modoTemporal.value === 'acumulado' ? (r.cobertura_acumulada || []) : Array(fechas.value.length).fill(r.cobertura_diaria || 0)
})

function getSerieRuta(rObj) {
  const s = rObj?.series || {}
  if (kpiActivo.value === 'volumen') {
    return modoTemporal.value === 'acumulado' ? (s.volumen_acumulado || []) : (s.volumen_diario || [])
  }
  return modoTemporal.value === 'acumulado' ? (s.cobertura_acumulada || []) : (s.cobertura_diaria || [])
}

// Escalas del Gráfico SVG
const maxValorGrafico = computed(() => {
  let max = 0

  if (vistaModo.value === 'consolidado') {
    consolidadoSeries.value.forEach(v => { if (v > max) max = v })
    if (mostrarRitmoIdeal.value) {
      ritmoIdealSeries.value.forEach(v => { if (v > max) max = v })
    }
    if (metaTotal.value > max) max = metaTotal.value
  } else {
    rutasActivasVisibles.value.forEach(r => {
      getSerieRuta(r).forEach(v => { if (v > max) max = v })
      const m = kpiActivo.value === 'volumen' ? r.meta_volumen : r.meta_cobertura
      if (m > max) max = m
    })
  }

  return max > 0 ? max * 1.12 : 100
})

const yAxisTicks = computed(() => {
  const max = maxValorGrafico.value
  const step = max / 5
  return [0, step * 1, step * 2, step * 3, step * 4, step * 5]
})

function getXPixel(index) {
  const total = Math.max(fechas.value.length - 1, 1)
  const anchoUtil = 1000 - padding.left - padding.right
  return padding.left + (index / total) * anchoUtil
}

function getYPixel(val) {
  const altoUtil = 440 - padding.top - padding.bottom
  const pct = Math.max(0, Math.min(val / maxValorGrafico.value, 1))
  return (440 - padding.bottom) - pct * altoUtil
}

// Convertir serie a coordenadas de puntos (x, y)
function getRutaPuntos(rObj) {
  const serie = getSerieRuta(rObj)
  return serie.map((val, idx) => ({
    x: getXPixel(idx),
    y: getYPixel(val),
    val,
  }))
}

const consolidadoPuntos = computed(() => {
  return consolidadoSeries.value.map((val, idx) => ({
    x: getXPixel(idx),
    y: getYPixel(val),
    val,
  }))
})

const ritmoIdealPuntos = computed(() => {
  return ritmoIdealSeries.value.map((val, idx) => ({
    x: getXPixel(idx),
    y: getYPixel(val),
    val,
  }))
})

function buildPathD(puntos) {
  if (!puntos || puntos.length === 0) return ''
  return puntos.reduce((acc, p, i) => {
    return i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`
  }, '')
}

function buildAreaD(puntos) {
  if (!puntos || puntos.length === 0) return ''
  const bottomY = 440 - padding.bottom
  const lineD = buildPathD(puntos)
  const first = puntos[0]
  const last = puntos[puntos.length - 1]
  return `${lineD} L ${last.x} ${bottomY} L ${first.x} ${bottomY} Z`
}

function buildSparklineD(r) {
  const serie = getSerieRuta(r)
  if (!serie || serie.length === 0) return ''
  const max = Math.max(...serie, 1)
  const w = 80
  const h = 20
  const pts = serie.map((val, idx) => {
    const x = (idx / Math.max(serie.length - 1, 1)) * w
    const y = h - (val / max) * (h - 4) - 2
    return { x, y }
  })
  return buildPathD(pts)
}

// Interacción con Mouse para Tooltip Crosshair
function onSvgMouseMove(event) {
  if (fechas.value.length === 0) return
  const svgRect = event.currentTarget.getBoundingClientRect()
  const mouseX = event.clientX - svgRect.left
  const svgWidth = svgRect.width

  const xRelativo = (mouseX / svgWidth) * 1000
  const anchoUtil = 1000 - padding.left - padding.right
  const pctX = Math.max(0, Math.min((xRelativo - padding.left) / anchoUtil, 1))

  const index = Math.round(pctX * (fechas.value.length - 1))
  hoveredIndex.value = Math.max(0, Math.min(index, fechas.value.length - 1))

  tooltipX.value = event.clientX - svgRect.left
  tooltipY.value = event.clientY - svgRect.top
}

function onSvgMouseLeave() {
  hoveredIndex.value = null
  hoveredRuta.value = null
}

const tooltipStyle = computed(() => {
  const x = tooltipX.value
  const left = x > 400 ? x - 230 : x + 15
  return {
    left: `${left}px`,
    top: '20px',
  }
})

function getConsolidadoValorEnFecha(idx) {
  return consolidadoSeries.value[idx] || 0
}

function getRutaValorEnFecha(rObj, idx) {
  const serie = getSerieRuta(rObj)
  return serie[idx] || 0
}

// Tabla Inferior
function getPctRuta(r) {
  return kpiActivo.value === 'volumen' ? r.pct_volumen : r.pct_cobertura
}

function getPromedioDiarioRuta(r) {
  const dias = Math.max(fechas.value.length, 1)
  const log = kpiActivo.value === 'volumen' ? r.logrado_volumen : r.logrado_cobertura
  return roundNum(log / dias)
}

function ordenarPor(col) {
  if (columnaOrden.value === col) {
    ordenAsc.value = !ordenAsc.value
  } else {
    columnaOrden.value = col
    ordenAsc.value = col === 'ruta' || col === 'canal'
  }
}

const rutasFiltradasTabla = computed(() => {
  const col = columnaOrden.value
  const asc = ordenAsc.value ? 1 : -1

  return [...rutasDisponibles.value].sort((a, b) => {
    let va = a[col]
    let vb = b[col]
    if (typeof va === 'string') return asc * va.localeCompare(vb)
    return asc * ((Number(va) || 0) - (Number(vb) || 0))
  })
})

// Consulta API
async function consultarGraficos() {
  cargando.value = true
  try {
    const payload = {
      fecha_inicio: store.fechaInicio,
      fecha_corte: store.fechaCorte,
      canales: store.canalesSeleccionados,
      rutas: store.rutasSeleccionadas,
    }
    const res = await axios.post(route('supervisor.avance.graficos.data'), payload)
    fechas.value = res.data.fechas || []
    diasLabels.value = res.data.dias_labels || []
    rutasData.value = res.data.rutas || []
    consolidadoData.value = res.data.consolidado || {}
    ritmoIdealData.value = res.data.ritmo_ideal || {}

    // Marcar rutas activas por defecto
    marcarTodasRutas()
  } catch (e) {
    console.error('Error al consultar datos de gráficos de avance:', e)
  } finally {
    cargando.value = false
  }
}

// Exportación
async function copiarTablaAlPortapapeles() {
  if (rutasFiltradasTabla.value.length === 0) return

  const headers = [
    'Ruta', 'Vendedor', 'Canal',
    kpiActivo.value === 'volumen' ? 'Meta Volumen (Bs)' : 'Meta Cobertura (Cli)',
    kpiActivo.value === 'volumen' ? 'Logrado MTD (Bs)' : 'Logrado MTD (Cli)',
    '% Avance', 'Promedio Diario'
  ]

  const lineas = [headers.join('\t')]

  rutasFiltradasTabla.value.forEach(r => {
    lineas.push([
      r.ruta,
      r.vendedor,
      r.canal,
      kpiActivo.value === 'volumen' ? r.meta_volumen : r.meta_cobertura,
      kpiActivo.value === 'volumen' ? r.logrado_volumen : r.logrado_cobertura,
      `${getPctRuta(r)}%`,
      getPromedioDiarioRuta(r)
    ].join('\t'))
  })

  try {
    await navigator.clipboard.writeText(lineas.join('\n'))
    copiadoExitoso.value = true
    setTimeout(() => { copiadoExitoso.value = false }, 2500)
  } catch (err) {
    console.error('Error al copiar al portapapeles:', err)
  }
}

function exportarExcel() {
  if (rutasFiltradasTabla.value.length === 0) return

  const rows = [
    [
      'Ruta', 'Vendedor', 'Canal',
      kpiActivo.value === 'volumen' ? 'Meta Volumen (Bs)' : 'Meta Cobertura (Cli)',
      kpiActivo.value === 'volumen' ? 'Logrado MTD (Bs)' : 'Logrado MTD (Cli)',
      '% Avance', 'Promedio Diario'
    ]
  ]

  rutasFiltradasTabla.value.forEach(r => {
    rows.push([
      r.ruta,
      r.vendedor,
      r.canal,
      kpiActivo.value === 'volumen' ? r.meta_volumen : r.meta_cobertura,
      kpiActivo.value === 'volumen' ? r.logrado_volumen : r.logrado_cobertura,
      getPctRuta(r) / 100,
      getPromedioDiarioRuta(r)
    ])
  })

  const mesStr = store.fechaInicio ? store.fechaInicio.substring(0, 7) : 'MTD'
  descargarExcelXlsx(rows, `Avance_${kpiActivo.value.toUpperCase()}_${mesStr}`, 'Curva de Avance')
}

onMounted(() => {
  store.init(props)
  consultarGraficos()
})
</script>
