<template>
  <TrackingLayout>
    <div class="flex-1 flex flex-col min-h-0 bg-[#F5F5F7]">
      <!-- Header de Control de Auditoría Histórica -->
      <header class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 py-2 flex items-center justify-between gap-2 shrink-0 relative z-30">
        
        <!-- Backdrop para dropdowns -->
        <div
          v-if="menuAbierto !== null"
          @click="menuAbierto = null"
          class="fixed inset-0 z-[99990]"
        ></div>

        <!-- Contenedor con Scroll Horizontal de Filtros -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5">
          <!-- Rango de Fechas -->
          <div class="flex items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA] shrink-0 text-xs font-mono">
            <span class="text-[10px] text-[#86868B] px-1.5">Del:</span>
            <input
              type="date"
              v-model="filtroFechaInicio"
              @change="cargarDatos"
              class="h-6 px-1 text-xs font-semibold bg-white text-[#1D1D1F] border-0 rounded-[4px] focus:ring-0 cursor-pointer shadow-2xs"
            />
            <span class="text-[10px] text-[#86868B] px-1.5">Al:</span>
            <input
              type="date"
              v-model="filtroFechaFin"
              @change="cargarDatos"
              class="h-6 px-1 text-xs font-semibold bg-white text-[#1D1D1F] border-0 rounded-[4px] focus:ring-0 cursor-pointer shadow-2xs"
            />
          </div>

          <!-- Selector de Día de la Semana Recurrente -->
          <div class="flex items-center h-7 px-2 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] shrink-0">
            <span class="text-[#86868B] mr-1.5">Día recurrente:</span>
            <select
              v-model="filtroDia"
              @change="cargarDatos"
              class="bg-transparent font-semibold text-xs border-0 p-0 pr-4 focus:ring-0 cursor-pointer"
            >
              <option v-for="d in dias_disponibles" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>

          <!-- Selector de Canal -->
          <button
            type="button"
            @click.stop="toggleMenu('canales', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Canal:</span>
            <span class="font-semibold">{{ canalSeleccionado || 'Todos' }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Selector de Ruta (Única para análisis de patrón) -->
          <button
            type="button"
            @click.stop="toggleMenu('rutas', $event)"
            class="h-7 px-2.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] text-xs font-medium text-[#1D1D1F] hover:bg-[#F2F2F7] flex items-center gap-1 transition-colors cursor-pointer whitespace-nowrap select-none shrink-0"
          >
            <span class="text-[#86868B]">Ruta:</span>
            <span class="font-semibold">{{ rutaSeleccionada || 'Seleccionar...' }}</span>
            <svg class="w-3 h-3 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>

        <!-- Indicador de Carga, Control Móvil y Métricas -->
        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
          <span v-if="cargando" class="text-[11px] text-[#6E6E73] hidden sm:flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-[#AF52DE]"></span>
            Analizando patrones...
          </span>

          <div class="flex md:hidden items-center bg-[#F2F2F7] p-0.5 rounded-[6px] border border-[#E5E5EA]">
            <button
              type="button"
              @click="vistaMovil = 'mapa'"
              class="px-2 py-1 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer"
              :class="vistaMovil === 'mapa' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              Mapa
            </button>
            <button
              type="button"
              @click="vistaMovil = 'fechas'"
              class="px-2 py-1 text-[11px] font-semibold rounded-[4px] transition-all cursor-pointer"
              :class="vistaMovil === 'fechas' ? 'bg-white text-[#1D1D1F] shadow-2xs' : 'text-[#6E6E73]'"
            >
              Semanas ({{ trayectoriasFechas.length }})
            </button>
          </div>

          <div v-if="kpis.alertas_mock > 0" class="hidden lg:flex px-2 py-0.5 bg-[#FDF0EF] text-[#C9342C] border border-[#E5E5EA] rounded-[6px] items-center gap-1 font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#C9342C]"></span>
            <span>Mock GPS: {{ kpis.alertas_mock }}</span>
          </div>

          <div class="hidden sm:flex px-2 py-0.5 bg-[#FBFBFD] border border-[#E5E5EA] rounded-[6px] items-center gap-1">
            <span class="text-[#86868B]">Semanas:</span>
            <span class="font-semibold text-[#1D1D1F] tabular-nums">{{ kpis.total_fechas }}</span>
          </div>
        </div>
      </header>

      <!-- Dropdowns con Teleport -->
      <Teleport to="body">
        <!-- Canales -->
        <div
          v-if="menuAbierto === 'canales'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-44 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-1.5 z-[99999] text-xs"
        >
          <button
            type="button"
            @click="canalSeleccionado = ''; menuAbierto = null"
            class="w-full text-left px-2 py-1 rounded-[4px] hover:bg-[#F2F2F7] cursor-pointer"
            :class="!canalSeleccionado ? 'bg-[#F2F2F7] font-semibold text-[#0071E3]' : ''"
          >
            Todos los canales
          </button>
          <button
            v-for="c in canales"
            :key="c"
            type="button"
            @click="canalSeleccionado = c; menuAbierto = null"
            class="w-full text-left px-2 py-1 rounded-[4px] hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between"
            :class="canalSeleccionado === c ? 'bg-[#F2F2F7] font-semibold text-[#0071E3]' : ''"
          >
            <span>{{ c }}</span>
            <span v-if="canalSeleccionado === c" class="text-[#0071E3]">✓</span>
          </button>
        </div>

        <!-- Rutas -->
        <div
          v-if="menuAbierto === 'rutas'"
          @click.stop
          :style="{ top: `${posicionMenu.top}px`, left: `${posicionMenu.left}px` }"
          class="fixed w-64 bg-white border border-[#E5E5EA] rounded-[8px] shadow-2xl p-2 z-[99999] text-xs"
        >
          <input
            v-model="busquedaRuta"
            type="text"
            placeholder="Buscar ruta o vendedor..."
            class="w-full h-6 px-2 mb-1.5 text-xs bg-[#FBFBFD] border border-[#E5E5EA] rounded-[4px] focus:outline-none focus:border-[#86868B]"
          />
          <div class="max-h-56 overflow-y-auto space-y-0.5">
            <button
              v-for="r in rutasFiltradasEnDropdown"
              :key="r.ruta"
              type="button"
              @click="seleccionarRuta(r.ruta)"
              class="w-full text-left px-2 py-1 rounded-[4px] hover:bg-[#F2F2F7] cursor-pointer flex items-center justify-between"
              :class="rutaSeleccionada === r.ruta ? 'bg-[#F2F2F7] font-semibold text-[#0071E3]' : ''"
            >
              <div class="truncate">
                <span class="font-medium">{{ r.ruta }}</span>
                <span class="text-[#86868B] text-[10px] ml-1">({{ r.canal }})</span>
                <span class="text-[#86868B] text-[10px] block truncate">{{ r.vendedor }}</span>
              </div>
              <span v-if="rutaSeleccionada === r.ruta" class="text-[#0071E3]">✓</span>
            </button>
          </div>
        </div>
      </Teleport>

      <!-- Split Screen Principal -->
      <div class="flex-1 flex flex-col md:flex-row min-h-0 overflow-hidden relative z-10">
        
        <!-- Panel Izquierdo: Fechas Auditadas (Semanas) o Ficha de Visita -->
        <aside
          class="w-full md:w-[400px] bg-white border-r border-[#E5E5EA] flex flex-col shrink-0 relative z-20 overflow-hidden"
          :class="vistaMovil === 'mapa' ? 'hidden md:flex' : 'flex-1 md:flex-initial flex'"
        >
          <!-- MODO 1: COMPARADOR DE SEMANAS (Capas de cada fecha) -->
          <template v-if="!visitaSeleccionada">
            <div class="p-2 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <span class="text-[11px] font-semibold text-[#86868B] uppercase tracking-wider">
                {{ filtroDia }}s Encontrados ({{ trayectoriasFechas.length }})
              </span>
              <button
                type="button"
                @click="toggleTodasFechas"
                class="text-[11px] text-[#0071E3] font-medium hover:underline cursor-pointer"
              >
                {{ fechasVisibles.length === trayectoriasFechas.length ? 'Ocultar todas' : 'Mostrar todas' }}
              </button>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-[#E5E5EA]">
              <div v-if="cargando" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Calculando trayectorias históricas...
              </div>

              <div v-else-if="!rutaSeleccionada" class="p-8 text-center text-xs text-[#86868B] space-y-1">
                <p class="font-medium text-[#1D1D1F]">Selecciona una ruta</p>
                <p>Elige una ruta en la barra superior para contrastar el comportamiento de los días {{ filtroDia }}.</p>
              </div>

              <div v-else-if="trayectoriasFechas.length === 0" class="p-8 text-center text-xs text-[#86868B]">
                No se encontraron datos de telemetría para los días {{ filtroDia }} en el rango seleccionado.
              </div>

              <div
                v-for="tf in trayectoriasFechas"
                :key="tf.fecha"
                class="p-3 hover:bg-[#FBFBFD] transition-colors"
              >
                <div class="flex items-center justify-between">
                  <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      :value="tf.fecha"
                      v-model="fechasVisibles"
                      @change="actualizarCapaMapa"
                      class="rounded border-[#E5E5EA] text-[#1D1D1F] focus:ring-0 cursor-pointer"
                    />
                    <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: tf.color }"></span>
                    <span class="text-xs font-bold font-mono text-[#1D1D1F]">{{ tf.fecha_formato }}</span>
                  </label>

                  <div class="flex items-center gap-2 text-[10px] font-mono">
                    <span class="px-1.5 py-0.5 rounded bg-[#F2F2F7] text-[#1D1D1F]">
                      {{ tf.total_puntos }} pts
                    </span>
                    <span class="px-1.5 py-0.5 rounded bg-[#EBF9EF] text-[#248A3D] font-bold">
                      {{ tf.total_visitas }} fotos
                    </span>
                  </div>
                </div>

                <!-- Visitas tomadas ese día -->
                <div v-if="tf.visitas.length > 0 && fechasVisibles.includes(tf.fecha)" class="mt-2.5 pt-2 border-t border-[#E5E5EA]/60 pl-5 space-y-1">
                  <div
                    v-for="v in tf.visitas"
                    :key="v.id"
                    @click="enfocarVisita(v)"
                    class="flex items-center justify-between text-[11px] py-0.5 hover:bg-[#F2F2F7] px-1 rounded cursor-pointer transition-colors"
                  >
                    <div class="flex items-center gap-1.5 truncate">
                      <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: tf.color }"></span>
                      <span class="font-medium text-[#1D1D1F] truncate">{{ v.cliente }}</span>
                    </div>
                    <span class="font-mono text-[10px] text-[#86868B] shrink-0">{{ v.hora }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- MODO 2: FICHA DE VISITA HISTÓRICA AUDITADA -->
          <template v-else>
            <div class="p-2.5 border-b border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
              <button
                type="button"
                @click="visitaSeleccionada = null"
                class="text-xs font-semibold text-[#0071E3] hover:underline flex items-center gap-1 cursor-pointer"
              >
                <span>&larr;</span> Volver a las semanas
              </button>
            </div>

            <div class="p-3 bg-white border-b border-[#E5E5EA] shrink-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-[#1D1D1F] leading-tight">{{ visitaSeleccionada.cliente }}</h3>
                  <p class="text-[10px] font-mono text-[#6E6E73] mt-0.5">
                    Visita realizada el {{ visitaSeleccionada.fecha_formato }} a las {{ visitaSeleccionada.hora }}
                  </p>
                </div>
                <span
                  class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded"
                  :class="visitaSeleccionada.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                >
                  {{ visitaSeleccionada.status }}
                </span>
              </div>
            </div>

            <!-- Galería de fotos del cliente -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2.5 bg-[#F5F5F7]">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider">Historial de Fotografías</span>
                <span class="text-[10px] font-mono text-[#6E6E73]">{{ fotosHistoricasCliente.length }} foto(s)</span>
              </div>

              <div v-if="cargandoFotos" class="p-8 text-center text-xs text-[#6E6E73] animate-pulse">
                Cargando histórico de fotografías...
              </div>

              <div
                v-for="(vis, idx) in fotosHistoricasCliente"
                :key="vis.id"
                class="bg-white border border-[#E5E5EA] rounded-[8px] p-2.5 flex items-start gap-3 hover:border-[#86868B] transition-colors"
              >
                <div
                  @click="abrirCarruselEn(idx)"
                  class="w-16 h-16 rounded-[6px] overflow-hidden bg-[#F2F2F7] border border-[#E5E5EA] shrink-0 cursor-pointer relative group"
                >
                  <img
                    v-if="vis.photo_url"
                    :src="vis.photo_url"
                    alt="Visita"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    loading="lazy"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-[9px] text-[#86868B]">
                    Sin foto
                  </div>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded border border-[#E5E5EA]"
                      :class="vis.status === 'PREVENTA' ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#FFF5E5] text-[#B25E00]'"
                    >
                      {{ vis.status }}
                    </span>
                    <span class="text-[10px] font-mono text-[#86868B]">{{ vis.fecha }} {{ vis.hora }}</span>
                  </div>
                  <p class="text-[10px] font-mono text-[#1D1D1F] mt-1">
                    Ruta: {{ vis.ruta }} • {{ vis.vendedor }}
                  </p>
                  <p v-if="vis.comments" class="text-[10px] text-[#6E6E73] mt-0.5 italic line-clamp-2">
                    "{{ vis.comments }}"
                  </p>
                </div>
              </div>
            </div>
          </template>
        </aside>

        <!-- Panel Derecho: Visor Cartográfico Leaflet -->
        <main
          class="flex-1 relative min-h-0 bg-[#E5E5EA]"
          :class="vistaMovil === 'fechas' ? 'hidden md:block' : 'block h-full w-full'"
        >
          <div id="map-tracking-historico" class="absolute inset-0 w-full h-full"></div>

          <!-- Leyenda en el Mapa -->
          <div class="hidden sm:block absolute bottom-4 right-4 bg-white/95 backdrop-blur-xs border border-[#E5E5EA] rounded-[8px] p-2.5 shadow-md z-[500] text-[11px] max-w-[240px]">
            <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-wider mb-1.5">Superposición de {{ filtroDia }}s</p>
            <div class="max-h-28 overflow-y-auto space-y-1 pb-1.5 border-b border-[#E5E5EA] no-scrollbar">
              <div v-for="tf in trayectoriasFechas" :key="tf.fecha" class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: tf.color }"></span>
                  <span class="font-mono text-[10px]">{{ tf.fecha_formato }}</span>
                </div>
                <span class="text-[9px] text-[#86868B] font-mono">({{ tf.total_puntos }} pts)</span>
              </div>
            </div>
            <div class="mt-1.5 text-[10px] text-[#86868B]">
              Los círculos representan visitas con fotografía tomadas ese día.
            </div>
          </div>
        </main>
      </div>

      <!-- VISOR CARRUSEL LIGHTBOX -->
      <Teleport to="body">
        <div
          v-if="modalCarruselAbierto && fotoActiva"
          class="fixed inset-0 z-[99999] bg-black/85 backdrop-blur-md flex flex-col items-center justify-between p-4 select-none"
        >
          <div class="w-full max-w-4xl flex items-center justify-between text-white pb-2 border-b border-white/10">
            <div>
              <span class="text-xs font-bold uppercase tracking-wider text-[#86868B]">Auditoría Fotográfica</span>
              <h4 class="text-sm font-semibold text-white">{{ visitaSeleccionada?.cliente }}</h4>
            </div>
            <div class="flex items-center gap-4 text-xs font-mono">
              <span class="text-white/60">{{ indiceFotoActiva + 1 }} de {{ fotosHistoricasCliente.length }}</span>
              <button
                type="button"
                @click="cerrarCarrusel"
                class="p-1 rounded-full text-white/80 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              >
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <div class="relative w-full max-w-4xl flex-1 flex items-center justify-center my-3 overflow-hidden">
            <button
              v-if="fotosHistoricasCliente.length > 1"
              type="button"
              @click.stop="fotoAnterior"
              class="absolute left-2 z-10 p-2.5 rounded-full bg-black/50 text-white/80 hover:text-white hover:bg-black/80 backdrop-blur-xs transition-all cursor-pointer"
              title="Anterior"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <div class="max-h-[70vh] flex flex-col items-center justify-center">
              <img
                :src="fotoActiva.photo_url"
                :alt="fotoActiva.status"
                class="max-h-[68vh] max-w-full rounded-[8px] object-contain shadow-2xl border border-white/10"
              />
            </div>

            <button
              v-if="fotosHistoricasCliente.length > 1"
              type="button"
              @click.stop="fotoSiguiente"
              class="absolute right-2 z-10 p-2.5 rounded-full bg-black/50 text-white/80 hover:text-white hover:bg-black/80 backdrop-blur-xs transition-all cursor-pointer"
              title="Siguiente"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div class="w-full max-w-4xl bg-white/10 backdrop-blur-sm rounded-[8px] p-3 text-white text-xs border border-white/10 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span
                class="px-2 py-0.5 rounded font-mono font-bold text-[10px]"
                :class="fotoActiva.status === 'PREVENTA' ? 'bg-[#248A3D] text-white' : 'bg-[#B25E00] text-white'"
              >
                {{ fotoActiva.status }}
              </span>
              <span class="font-mono text-white/80">{{ fotoActiva.fecha }} a las {{ fotoActiva.hora }}</span>
              <span class="text-white/40">•</span>
              <span class="font-mono text-white/80">Ruta: {{ fotoActiva.ruta }}</span>
            </div>

            <p v-if="fotoActiva.comments" class="text-white/70 italic text-[11px] max-w-md truncate">
              "{{ fotoActiva.comments }}"
            </p>

            <a
              :href="fotoActiva.photo_url"
              target="_blank"
              class="text-[11px] text-[#0071E3] hover:underline font-medium"
            >
              Abrir original &rarr;
            </a>
          </div>
        </div>
      </Teleport>
    </div>
  </TrackingLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, Teleport } from 'vue';
import axios from 'axios';
import TrackingLayout from '@/Pages/Supervisor/Tracking/Layout.vue';
import { mapsConfig } from '@/Config/maps';
import L from 'leaflet';

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => [],
  },
  canales: {
    type: Array,
    default: () => [],
  },
  fecha_inicio_default: {
    type: String,
    default: '',
  },
  fecha_fin_default: {
    type: String,
    default: '',
  },
  dias_disponibles: {
    type: Array,
    default: () => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
  },
});

// Filtros Reactivos
const filtroFechaInicio = ref(props.fecha_inicio_default);
const filtroFechaFin = ref(props.fecha_fin_default);
const filtroDia = ref('Lunes');
const canalSeleccionado = ref('');
const rutaSeleccionada = ref(props.catalogo_rutas[0]?.ruta || '');

// UI
const menuAbierto = ref(null);
const posicionMenu = ref({ top: 0, left: 0 });
const busquedaRuta = ref('');
const vistaMovil = ref('mapa');

// Datos
const cargando = ref(false);
const trayectoriasFechas = ref([]);
const fechasVisibles = ref([]);
const kpis = ref({
  total_fechas: 0,
  total_puntos: 0,
  total_visitas: 0,
  alertas_mock: 0,
});

// Ficha de Visita y Carrusel
const visitaSeleccionada = ref(null);
const fotosHistoricasCliente = ref([]);
const cargandoFotos = ref(false);
const modalCarruselAbierto = ref(false);
const indiceFotoActiva = ref(0);

const fotoActiva = computed(() => {
  if (!fotosHistoricasCliente.value || fotosHistoricasCliente.value.length === 0) return null;
  return fotosHistoricasCliente.value[indiceFotoActiva.value] || null;
});

let map = null;
let linesGroup = null;
let markersGroup = null;

const rutasDisponibles = computed(() => {
  if (!props.catalogo_rutas) return [];
  if (!canalSeleccionado.value) return props.catalogo_rutas;
  return props.catalogo_rutas.filter(r => r.canal === canalSeleccionado.value);
});

const rutasFiltradasEnDropdown = computed(() => {
  if (!busquedaRuta.value.trim()) return rutasDisponibles.value;
  const q = busquedaRuta.value.toLowerCase();
  return rutasDisponibles.value.filter(r =>
    r.ruta.toLowerCase().includes(q) || (r.vendedor && r.vendedor.toLowerCase().includes(q))
  );
});

function toggleMenu(nombre, event) {
  if (menuAbierto.value === nombre) {
    menuAbierto.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const anchoMenu = nombre === 'rutas' ? 256 : 176;

  let leftPos = rect.left;
  if (leftPos + anchoMenu > window.innerWidth - 8) {
    leftPos = Math.max(8, window.innerWidth - anchoMenu - 8);
  }

  posicionMenu.value = {
    top: rect.bottom + 4,
    left: leftPos,
  };
  menuAbierto.value = nombre;
}

function seleccionarRuta(ruta) {
  rutaSeleccionada.value = ruta;
  menuAbierto.value = null;
  cargarDatos();
}

function toggleTodasFechas() {
  if (fechasVisibles.value.length === trayectoriasFechas.value.length) {
    fechasVisibles.value = [];
  } else {
    fechasVisibles.value = trayectoriasFechas.value.map(tf => tf.fecha);
  }
  actualizarCapaMapa();
}

async function cargarDatos() {
  if (!rutaSeleccionada.value) return;
  cargando.value = true;

  try {
    const res = await axios.post(route('supervisor.tracking.historico.data'), {
      fecha_inicio: filtroFechaInicio.value,
      fecha_fin: filtroFechaFin.value,
      dia: filtroDia.value,
      ruta: rutaSeleccionada.value,
    });

    trayectoriasFechas.value = res.data.trayectorias_fechas || [];
    kpis.value = res.data.kpis || {
      total_fechas: 0,
      total_puntos: 0,
      total_visitas: 0,
      alertas_mock: 0,
    };

    // Por defecto todas las semanas visibles para contrastar
    fechasVisibles.value = trayectoriasFechas.value.map(tf => tf.fecha);
    actualizarCapaMapa();
  } catch (err) {
    console.error('Error cargando historial de tracking:', err);
  } finally {
    cargando.value = false;
  }
}

function actualizarCapaMapa() {
  if (!map || !linesGroup || !markersGroup) return;

  // Corregido: clearLayers() en lugar de clear()
  linesGroup.clearLayers();
  markersGroup.clearLayers();

  const boundsList = [];

  trayectoriasFechas.value.forEach(tf => {
    // Si la fecha está desmarcada en los checkboxes, no se dibuja
    if (!fechasVisibles.value.includes(tf.fecha)) return;

    // 1. Trazado de la trayectoria (polilínea continua con color distintivo de fecha)
    const latlngs = tf.puntos.map(p => [p.lat, p.lng]);
    latlngs.forEach(ll => boundsList.push(L.latLng(ll[0], ll[1])));

    if (latlngs.length > 1) {
      L.polyline(latlngs, {
        color: tf.color,
        weight: 3,
        opacity: 0.8,
        dashArray: '5, 5',
      }).addTo(linesGroup);
    }

    // 2. Marcadores de Visitas con Fotografía tomadas ese día
    tf.visitas.forEach(v => {
      boundsList.push(L.latLng(v.visita_lat, v.visita_lng));

      const marker = L.circleMarker([v.visita_lat, v.visita_lng], {
        radius: 6.5,
        fillColor: tf.color,
        color: '#FFFFFF',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.95,
      });

      const popupContent = document.createElement('div');
      popupContent.style.fontFamily = '-apple-system, BlinkMacSystemFont, sans-serif';
      popupContent.style.fontSize = '11px';
      popupContent.style.lineHeight = '1.4';
      popupContent.style.color = '#1D1D1F';
      popupContent.style.minWidth = '210px';

      popupContent.innerHTML = `
        <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px;">${v.cliente}</div>
        <div style="color: ${tf.color}; font-weight: bold; margin-bottom: 4px;">
          ${tf.fecha_formato} • ${v.hora} (${v.status})
        </div>
        <div style="width: 100%; height: 80px; border-radius: 4px; overflow: hidden; margin-bottom: 6px; background-color: #F2F2F7;">
          <img src="${v.photo_url}" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
        <button
          id="btn-visita-hist-${v.id}"
          type="button"
          style="width: 100%; height: 26px; background-color: #0071E3; color: white; border: none; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer;"
        >
          Ver detalles e histórico &rarr;
        </button>
      `;

      const btn = popupContent.querySelector(`#btn-visita-hist-${v.id}`);
      if (btn) {
        btn.addEventListener('click', () => {
          abrirFichaVisita(v, tf.fecha_formato);
        });
      }

      marker.bindPopup(popupContent, { maxWidth: 230 });
      marker.addTo(markersGroup);
    });
  });

  if (boundsList.length > 0) {
    try {
      const bounds = L.latLngBounds(boundsList);
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
      }
    } catch (e) {
      // Bounds inválidos
    }
  }
}
function enfocarVisita(v) {
  if (!map) return;
  vistaMovil.value = 'mapa';
  map.flyTo([v.visita_lat, v.visita_lng], 18, { duration: 0.6 });
}

async function abrirFichaVisita(v, fechaFormato) {
  visitaSeleccionada.value = { ...v, fecha_formato: fechaFormato };
  fotosHistoricasCliente.value = [];
  vistaMovil.value = 'fechas';

  cargandoFotos.value = true;
  try {
    const res = await axios.post(route('supervisor.visitas.fotos', { clienteId: v.cliente_id }));
    fotosHistoricasCliente.value = res.data.visitas || [];
  } catch (err) {
    console.error('Error cargando historial:', err);
  } finally {
    cargandoFotos.value = false;
  }
}

function abrirCarruselEn(index) {
  indiceFotoActiva.value = index;
  modalCarruselAbierto.value = true;
}

function cerrarCarrusel() {
  modalCarruselAbierto.value = false;
}

function fotoSiguiente() {
  if (indiceFotoActiva.value < fotosHistoricasCliente.value.length - 1) {
    indiceFotoActiva.value++;
  } else {
    indiceFotoActiva.value = 0;
  }
}

function fotoAnterior() {
  if (indiceFotoActiva.value > 0) {
    indiceFotoActiva.value--;
  } else {
    indiceFotoActiva.value = fotosHistoricasCliente.value.length - 1;
  }
}

function initMap() {
  const defaultLoc = mapsConfig.defaultLocation;
  map = L.map('map-tracking-historico', {
    zoomControl: false,
    preferCanvas: true,
  }).setView([defaultLoc.lat, defaultLoc.lng], defaultLoc.zoom);

  L.control.zoom({ position: 'topright' }).addTo(map);

  L.tileLayer(mapsConfig.tileLayers.openStreetMap.url, {
    maxZoom: 19,
    attribution: mapsConfig.tileLayers.openStreetMap.attribution,
    subdomains: mapsConfig.tileLayers.openStreetMap.subdomains || 'abc',
  }).addTo(map);

  linesGroup = L.featureGroup().addTo(map);
  markersGroup = L.featureGroup().addTo(map);

  map.on('click', () => {
    menuAbierto.value = null;
  });
}

onMounted(() => {
  initMap();
  cargarDatos();
});
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>