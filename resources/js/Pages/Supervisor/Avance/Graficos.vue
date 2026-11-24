<template>
  <SupervisorLayout title="Avance > Gráficos de Avance">
    <div class="space-y-2.5 font-sans select-none">
      <!-- Subnavegación de Módulo Avance -->
      <div class="flex items-center justify-between gap-2">
        <SubNavTabs :tabs="avanceTabs" />
      </div>

      <!-- Barra Superior de Filtros Transversales -->
      <AvanceFiltrosBar @consultar="consultarGraficos" />

      <!-- Barra de Controles Minimalista del Gráfico -->
      <div class="apple-glass-card rounded-[12px] p-2 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Selector: Métrica (Volumen vs Cobertura) -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[8px] text-[11px] font-medium">
            <button
              type="button"
              @click="kpiActivo = 'volumen'"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
              :class="kpiActivo === 'volumen' ? 'bg-white text-zinc-900 font-semibold shadow-xs' : 'text-zinc-500 hover:text-zinc-900'"
            >
              Volumen General (Bs)
            </button>
            <button
              type="button"
              @click="kpiActivo = 'cobertura'"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
              :class="kpiActivo === 'cobertura' ? 'bg-white text-zinc-900 font-semibold shadow-xs' : 'text-zinc-500 hover:text-zinc-900'"
            >
              Cobertura 100% (Clientes)
            </button>
          </div>

          <!-- Selector: Modo Temporal (Acumulado vs Diario) -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[8px] text-[11px] font-medium">
            <button
              type="button"
              @click="modoTemporal = 'acumulado'"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
              :class="modoTemporal === 'acumulado' ? 'bg-white text-zinc-900 font-semibold shadow-xs' : 'text-zinc-500 hover:text-zinc-900'"
            >
              Curva Acumulada
            </button>
            <button
              type="button"
              @click="modoTemporal = 'diario'"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
              :class="modoTemporal === 'diario' ? 'bg-white text-zinc-900 font-semibold shadow-xs' : 'text-zinc-500 hover:text-zinc-900'"
            >
              Evolución Diaria
            </button>
          </div>

          <!-- Selector: Consolidado vs Por Rutas -->
          <div class="inline-flex bg-black/[0.04] p-0.5 rounded-[8px] text-[11px] font-medium">
            <button
              type="button"
              @click="vistaModo = 'rutas'"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
              :class="vistaModo === 'rutas' ? 'bg-white text-zinc-900 font-semibold shadow-xs' : 'text-zinc-500 hover:text-zinc-900'"
            >
              Por Rutas ({{ rutasActivasVisibles.length }})
            </button>
            <button
              type="button"
              @click="vistaModo = 'consolidado'"
              class="px-2.5 py-1 rounded-[6px] transition-all cursor-pointer whitespace-nowrap"
              :class="vistaModo === 'consolidado' ? 'bg-white text-zinc-900 font-semibold shadow-xs' : 'text-zinc-500 hover:text-zinc-900'"
            >
              Consolidado
            </button>
          </div>
        </div>

        <!-- Toggle: Ritmo Ideal -->
        <div class="flex items-center gap-2 ml-auto">
          <label class="flex items-center gap-1.5 text-[11px] text-zinc-600 hover:text-zinc-900 cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="mostrarRitmoIdeal"
              class="rounded border-zinc-300 text-zinc-900 focus:ring-0 cursor-pointer"
            />
            <span>Ritmo Ideal</span>
          </label>
        </div>
      </div>

      <!-- Selector Rápido de Rutas (Minimalista) -->
      <div v-if="vistaModo === 'rutas'" class="apple-glass-card rounded-[12px] p-2 flex flex-wrap items-center gap-1.5">
        <div class="flex items-center gap-1 mr-2 text-[10px] font-semibold text-zinc-400 uppercase tracking-wider shrink-0">
          <span>Rutas:</span>
          <button type="button" @click="marcarTodasRutas" class="text-zinc-700 hover:text-zinc-900 hover:underline cursor-pointer">Todas</button>
          <span>·</span>
          <button type="button" @click="marcarSoloTop5" class="text-zinc-700 hover:text-zinc-900 hover:underline cursor-pointer">Top 5</button>
          <span>·</span>
          <button type="button" @click="desmarcarTodasRutas" class="text-zinc-400 hover:text-zinc-700 hover:underline cursor-pointer">Ninguna</button>
        </div>

        <div class="flex flex-wrap items-center gap-1 max-h-20 overflow-y-auto custom-scroll flex-1">
          <button
            v-for="r in rutasDisponibles"
            :key="r.ruta"
            type="button"
            @click="toggleRutaActiva(r.ruta)"
            @mouseenter="hoveredRuta = r.ruta"
            @mouseleave="hoveredRuta = null"
            class="px-1.5 py-0.5 rounded-[5px] text-[10.5px] font-mono transition-all cursor-pointer flex items-center gap-1 border"
            :class="isRutaActiva(r.ruta)
              ? 'bg-white border-zinc-300 text-zinc-900 shadow-2xs font-semibold'
              : 'opacity-35 hover:opacity-75 bg-transparent border-transparent text-zinc-400'"
          >
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: getColorRuta(r.ruta) }"></span>
            <span>{{ r.ruta }}</span>
          </button>
        </div>
      </div>

      <!-- Skeleton Loader -->
      <div v-if="cargando" class="apple-glass-card rounded-[12px] p-8 animate-pulse space-y-3">
        <div class="h-4 bg-black/[0.04] rounded w-1/5"></div>
        <div class="h-64 bg-black/[0.02] rounded-lg"></div>
      </div>

      <!-- Gráfico de Líneas SVG Minimalista -->
      <div v-else class="apple-glass-card rounded-[12px] p-3 sm:p-4 relative overflow-hidden border border-black/[0.04]">
        <!-- Título e Información del Gráfico -->
        <div class="flex items-center justify-between pb-2 mb-2 border-b border-black/[0.04]">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-zinc-800">
              {{ kpiActivo === 'volumen' ? 'Volumen General (Bs)' : 'Cobertura 100% (Clientes)' }}
              <span class="text-zinc-400 font-normal">
                — {{ modoTemporal === 'acumulado' ? 'Acumulado MTD' : 'Evolución Diaria' }}
              </span>
            </span>
          </div>

          <div class="flex items-center gap-3 text-[11px] text-zinc-500">
            <div v-if="mostrarRitmoIdeal && modoTemporal === 'acumulado'" class="flex items-center gap-1.5">
              <span class="w-3.5 h-0 border-t border-dashed border-zinc-400"></span>
              <span>Ritmo Ideal</span>
            </div>
            <div v-if="vistaModo === 'consolidado'" class="flex items-center gap-1.5">
              <span class="w-3 h-0.5 rounded-full bg-zinc-800"></span>
              <span class="font-medium text-zinc-800">Total</span>
            </div>
          </div>
        </div>

        <!-- Contenedor Responsive del Gráfico SVG -->
        <div class="relative w-full overflow-x-auto custom-scroll" ref="chartContainerRef">
          <svg
            class="w-full h-80 sm:h-92 min-w-[650px] select-none"
            viewBox="0 0 1000 420"
            preserveAspectRatio="none"
            @mousemove="onSvgMouseMove"
            @mouseleave="onSvgMouseLeave"
          >
            <defs>
              <linearGradient id="gradConsolidadoMinimal" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#18181B" stop-opacity="0.08" />
                <stop offset="100%" stop-color="#18181B" stop-opacity="0.0" />
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
                  stroke="#F0F0F2"
                  stroke-dasharray="2 2"
                  stroke-width="1"
                />
                <text
                  :x="padding.left - 8"
                  :y="getYPixel(valY) + 3.5"
                  fill="#A1A1AA"
                  font-size="9.5"
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
                  :y2="420 - padding.bottom"
                  stroke="#F8F8F9"
                  stroke-width="1"
                />
                <text
                  :x="getXPixel(xIdx)"
                  :y="420 - padding.bottom + 15"
                  fill="#A1A1AA"
                  font-size="9"
                  font-family="monospace"
                  text-anchor="middle"
                >
                  {{ diasLabels[xIdx] || f }}
                </text>
              </template>
            </g>

            <!-- 3. Línea de Ritmo Ideal (Discontinua sutil) -->
            <path
              v-if="mostrarRitmoIdeal && modoTemporal === 'acumulado' && ritmoIdealPuntos.length > 0"
              :d="buildPathD(ritmoIdealPuntos)"
              fill="none"
              stroke="#A1A1AA"
              stroke-width="1.5"
              stroke-dasharray="4 4"
            />

            <!-- 4. Modo Consolidado -->
            <template v-if="vistaModo === 'consolidado'">
              <path
                :d="buildAreaD(consolidadoPuntos)"
                fill="url(#gradConsolidadoMinimal)"
              />
              <path
                :d="buildPathD(consolidadoPuntos)"
                fill="none"
                stroke="#18181B"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <circle
                v-for="(p, pIdx) in consolidadoPuntos"
                :key="pIdx"
                :cx="p.x"
                :cy="p.y"
                r="3"
                fill="#18181B"
                stroke="#FFFFFF"
                stroke-width="1.5"
              />
            </template>

            <!-- 5. Modo Multilínea por Rutas -->
            <template v-else-if="vistaModo === 'rutas'">
              <template v-for="rObj in rutasActivasVisibles" :key="rObj.ruta">
                <path
                  :d="buildPathD(getRutaPuntos(rObj))"
                  fill="none"
                  :stroke="getColorRuta(rObj.ruta)"
                  :stroke-width="hoveredRuta === rObj.ruta ? 3.5 : 1.8"
                  :stroke-opacity="hoveredRuta && hoveredRuta !== rObj.ruta ? 0.2 : 0.9"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="transition-all duration-100"
                />
                <circle
                  v-for="(p, pIdx) in getRutaPuntos(rObj)"
                  :key="pIdx"
                  :cx="p.x"
                  :cy="p.y"
                  :r="hoveredRuta === rObj.ruta ? 3.5 : 2"
                  :fill="getColorRuta(rObj.ruta)"
                  :fill-opacity="hoveredRuta && hoveredRuta !== rObj.ruta ? 0.2 : 0.9"
                  stroke="#FFFFFF"
                  stroke-width="1"
                />
              </template>
            </template>

            <!-- 6. Crosshair Tooltip Line -->
            <line
              v-if="hoveredIndex !== null"
              :x1="getXPixel(hoveredIndex)"
              :y1="padding.top"
              :x2="getXPixel(hoveredIndex)"
              :y2="420 - padding.bottom"
              stroke="#71717A"
              stroke-width="1"
              stroke-dasharray="2 2"
            />
          </svg>

          <!-- 7. Tooltip Flotante Minimalista -->
          <div
            v-if="hoveredIndex !== null"
            class="absolute pointer-events-none z-30 transition-all duration-75"
            :style="tooltipStyle"
          >
            <div class="bg-white/95 backdrop-blur-md rounded-[8px] p-2.5 shadow-lg border border-zinc-200 text-xs min-w-[190px] max-w-xs space-y-1.5">
              <div class="flex items-center justify-between border-b border-zinc-100 pb-1">
                <span class="font-semibold text-zinc-900">{{ diasLabels[hoveredIndex] }}</span>
                <span class="text-[9.5px] font-mono text-zinc-400">{{ fechas[hoveredIndex] }}</span>
              </div>

              <!-- Consolidado -->
              <div v-if="vistaModo === 'consolidado'" class="space-y-0.5 font-mono text-[11px]">
                <div class="flex items-center justify-between">
                  <span class="text-zinc-500">Logrado:</span>
                  <span class="font-bold text-zinc-900">
                    {{ kpiActivo === 'volumen' ? fmtBs(getConsolidadoValorEnFecha(hoveredIndex)) : fmt(getConsolidadoValorEnFecha(hoveredIndex)) }}
                  </span>
                </div>
                <div v-if="modoTemporal === 'acumulado'" class="flex items-center justify-between text-[10px] text-zinc-400">
                  <span>Ritmo Ideal:</span>
                  <span>{{ kpiActivo === 'volumen' ? fmtBs(ritmoIdealSeries[hoveredIndex]) : fmt(ritmoIdealSeries[hoveredIndex]) }}</span>
                </div>
              </div>

              <!-- Por Rutas -->
              <div v-else class="space-y-1 max-h-48 overflow-y-auto custom-scroll pr-0.5">
                <div
                  v-for="rObj in rutasActivasVisibles"
                  :key="rObj.ruta"
                  class="flex items-center justify-between text-[10.5px] font-mono"
                  :class="hoveredRuta === rObj.ruta ? 'font-bold bg-zinc-50 px-1 py-0.5 rounded' : ''"
                >
                  <div class="flex items-center gap-1.5 truncate mr-2">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: getColorRuta(rObj.ruta) }"></span>
                    <span class="truncate">{{ rObj.ruta }}</span>
                  </div>
                  <span class="text-zinc-900 shrink-0">
                    {{ kpiActivo === 'volumen' ? fmtBs(getRutaValorEnFecha(rObj, hoveredIndex)) : fmt(getRutaValorEnFecha(rObj, hoveredIndex)) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabla Comparativa por Ruta (Minimalista) -->
      <div class="apple-glass-card rounded-[12px] overflow-hidden border border-black/[0.04]">
        <div class="p-2.5 bg-white border-b border-zinc-100 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-zinc-800">
              Detalle por Ruta
            </span>
            <span class="text-[10px] text-zinc-400 font-mono">({{ rutasFiltradasTabla.length }})</span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="copiarTablaAlPortapapeles"
              class="px-2.5 py-1 text-[11px] font-medium rounded-[6px] bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900 cursor-pointer shadow-2xs whitespace-nowrap"
            >
              {{ copiadoExitoso ? 'Copiado' : 'Copiar' }}
            </button>
            <button
              type="button"
              @click="exportarExcel"
              class="px-2.5 py-1 text-[11px] font-medium rounded-[6px] bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900 cursor-pointer shadow-2xs whitespace-nowrap"
            >
              Excel
            </button>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-zinc-50/50 border-b border-zinc-100 text-[10px] font-semibold text-zinc-400 uppercase tracking-wider select-none">
                <th class="py-2 px-3 whitespace-nowrap cursor-pointer hover:text-zinc-700" @click="ordenarPor('ruta')">
                  Ruta
                </th>
                <th class="py-2 px-2 text-center whitespace-nowrap cursor-pointer hover:text-zinc-700" @click="ordenarPor('canal')">
                  Canal
                </th>
                <th class="py-2 px-3 text-right whitespace-nowrap cursor-pointer hover:text-zinc-700" @click="ordenarPor(kpiActivo === 'volumen' ? 'meta_volumen' : 'meta_cobertura')">
                  Meta Mes ({{ kpiActivo === 'volumen' ? 'Bs' : 'Cli' }})
                </th>
                <th class="py-2 px-3 text-right whitespace-nowrap cursor-pointer hover:text-zinc-700" @click="ordenarPor(kpiActivo === 'volumen' ? 'logrado_volumen' : 'logrado_cobertura')">
                  Logrado MTD
                </th>
                <th class="py-2 px-3 text-center whitespace-nowrap cursor-pointer hover:text-zinc-700" @click="ordenarPor(kpiActivo === 'volumen' ? 'pct_volumen' : 'pct_cobertura')">
                  % Avance
                </th>
                <th class="py-2 px-3 text-right whitespace-nowrap">
                  Promedio / Día
                </th>
                <th class="py-2 px-3 text-center whitespace-nowrap">
                  Tendencia
                </th>
                <th class="py-2 px-2 text-center whitespace-nowrap">
                  Aislar
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
              <tr
                v-for="r in rutasFiltradasTabla"
                :key="r.ruta"
                class="hover:bg-zinc-50/70 transition-colors"
                :class="hoveredRuta === r.ruta ? 'bg-zinc-50' : ''"
                @mouseenter="hoveredRuta = r.ruta"
                @mouseleave="hoveredRuta = null"
              >
                <!-- Ruta y Vendedor -->
                <td class="py-1.5 px-3 whitespace-nowrap">
                  <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: getColorRuta(r.ruta) }"></span>
                    <div>
                      <span class="font-mono font-semibold text-zinc-900">{{ r.ruta }}</span>
                      <span class="text-[10px] text-zinc-400 ml-1.5">{{ r.vendedor }}</span>
                    </div>
                  </div>
                </td>

                <!-- Canal -->
                <td class="py-1.5 px-2 text-center whitespace-nowrap">
                  <span class="text-[10px] font-mono text-zinc-500">
                    {{ r.canal }}
                  </span>
                </td>

                <!-- Meta Mes -->
                <td class="py-1.5 px-3 text-right whitespace-nowrap font-mono text-zinc-600">
                  {{ kpiActivo === 'volumen' ? fmtBs(r.meta_volumen) : fmt(r.meta_cobertura) }}
                </td>

                <!-- Logrado MTD -->
                <td class="py-1.5 px-3 text-right whitespace-nowrap font-mono font-semibold text-zinc-900">
                  {{ kpiActivo === 'volumen' ? fmtBs(r.logrado_volumen) : fmt(r.logrado_cobertura) }}
                </td>

                <!-- % Avance con barra sutil -->
                <td class="py-1.5 px-3 whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <div class="w-10 bg-zinc-100 rounded-full h-1 overflow-hidden shrink-0 hidden sm:block">
                      <div
                        class="h-full rounded-full bg-zinc-800"
                        :style="{ width: `${Math.min(getPctRuta(r), 100)}%` }"
                      ></div>
                    </div>
                    <span class="text-[11px] font-mono font-medium text-zinc-800">
                      {{ fmtPct(getPctRuta(r)) }}
                    </span>
                  </div>
                </td>

                <!-- Promedio por Día -->
                <td class="py-1.5 px-3 text-right whitespace-nowrap font-mono text-[10.5px] text-zinc-500">
                  {{ kpiActivo === 'volumen' ? fmtBs(getPromedioDiarioRuta(r)) : `${getPromedioDiarioRuta(r)} cli` }}
                </td>

                <!-- Sparkline SVG Minimalista -->
                <td class="py-1.5 px-3 text-center whitespace-nowrap">
                  <svg class="w-16 h-4 mx-auto" viewBox="0 0 80 20">
                    <path
                      :d="buildSparklineD(r)"
                      fill="none"
                      :stroke="getColorRuta(r.ruta)"
                      stroke-width="1.5"
                      stroke-linecap="round"
                    />
                  </svg>
                </td>

                <!-- Botón Aislar -->
                <td class="py-1.5 px-2 text-center whitespace-nowrap">
                  <button
                    type="button"
                    @click="enfocarRuta(r.ruta)"
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono transition-all cursor-pointer border"
                    :class="rutasSeleccionadasGrafico.size === 1 && rutasSeleccionadasGrafico.has(r.ruta)
                      ? 'bg-zinc-900 border-zinc-900 text-white font-semibold'
                      : 'border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                  >
                    {{ rutasSeleccionadasGrafico.size === 1 && rutasSeleccionadasGrafico.has(r.ruta) ? '1' : 'ver' }}
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

// Paleta minimalista y sobria (tonos armoniosos y balanceados, sin neones)
const PALETA_COLORES = [
  '#2563EB', // Blue
  '#0D9488', // Teal
  '#475569', // Slate
  '#7C3AED', // Violet
  '#0284C7', // Sky
  '#059669', // Emerald
  '#D97706', // Amber cálido
  '#4B5563', // Neutral Gray
  '#6366F1', // Indigo
  '#0891B2', // Cyan
  '#334155', // Dark Slate
  '#52525B', // Zinc
  '#1E293B', // Charcoal
  '#78716C', // Stone
  '#3B82F6', // Cobalt
  '#0F766E', // Deep Teal
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

// Formatters minimalistas
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

// Margins del gráfico SVG (viewBox 1000x420)
const padding = { top: 20, right: 30, bottom: 40, left: 70 }

// Rutas disponibles (filtrables también por el buscador transversal)
const rutasDisponibles = computed(() => {
  const b = (store.busqueda || '').toLowerCase().trim()
  const list = rutasData.value || []
  if (!b) return list
  return list.filter(r => (r.ruta && r.ruta.toLowerCase().includes(b)) || (r.vendedor && r.vendedor.toLowerCase().includes(b)))
})

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

// Meta consolidada para escala del eje Y
const metaConsolidado = computed(() => {
  return kpiActivo.value === 'volumen'
    ? (consolidadoData.value.meta_volumen || 0)
    : (consolidadoData.value.meta_cobertura || 0)
})

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
    if (metaConsolidado.value > max) max = metaConsolidado.value
  } else {
    rutasActivasVisibles.value.forEach(r => {
      getSerieRuta(r).forEach(v => { if (v > max) max = v })
      const m = kpiActivo.value === 'volumen' ? r.meta_volumen : r.meta_cobertura
      if (m > max) max = m
    })
  }

  return max > 0 ? max * 1.1 : 100
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
  const altoUtil = 420 - padding.top - padding.bottom
  const pct = Math.max(0, Math.min(val / maxValorGrafico.value, 1))
  return (420 - padding.bottom) - pct * altoUtil
}

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
  const bottomY = 420 - padding.bottom
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

// Tooltip Crosshair
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
  const left = x > 400 ? x - 210 : x + 15
  return {
    left: `${left}px`,
    top: '15px',
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
  return Math.round((log / dias + Number.EPSILON) * 100) / 100
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
  store.cargandoCumplimiento = true
  try {
    const payload = {
      fecha_inicio: store.filtrosAplicados?.fechaInicio || store.fechaInicio,
      fecha_corte: store.filtrosAplicados?.fechaCorte || store.fechaCorte,
      canales: Array.isArray(store.filtrosAplicados?.canalesSeleccionados)
        ? store.filtrosAplicados.canalesSeleccionados
        : (store.canalesSeleccionados || []),
      rutas: Array.isArray(store.filtrosAplicados?.rutasSeleccionadas)
        ? store.filtrosAplicados.rutasSeleccionadas
        : (store.rutasSeleccionadas || []),
    }
    const res = await axios.post(route('supervisor.avance.graficos.data'), payload)
    fechas.value = res.data.fechas || []
    diasLabels.value = res.data.dias_labels || []
    rutasData.value = res.data.rutas || []
    consolidadoData.value = res.data.consolidado || {}
    ritmoIdealData.value = res.data.ritmo_ideal || {}

    marcarTodasRutas()
  } catch (e) {
    console.error('Error al consultar datos de gráficos de avance:', e)
  } finally {
    cargando.value = false
    store.cargandoCumplimiento = false
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
  store.inicializarDesdeStorage(props)
  consultarGraficos()
})
</script>
