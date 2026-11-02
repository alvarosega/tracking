<template>
  <SupervisorLayout>
    <div class="space-y-4 sm:space-y-6">
      <!-- Navegación Unificada: Jornadas / Horarios / Días de Visita / Módulos -->
      <div class="flex items-center justify-between">
        <SubNavTabs :tabs="jornadasHorariosModulosTabs" currentTab="dias-ruteo" />
      </div>

      <!-- Header Superior Apple Pro -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">Asignación de Días de Visita por Ruta</h1>
            <span
              v-if="isSuperAdmin"
              class="px-2 py-0.5 text-[10px] font-mono font-bold bg-[#EBF9EF] text-[#248A3D] rounded border border-[#248A3D]/20"
            >
              Superadmin Activo
            </span>
            <span
              v-else
              class="px-2 py-0.5 text-[10px] font-mono font-medium bg-[#F2F2F7] text-[#6E6E73] rounded border border-[#E5E5EA]"
            >
              Modo Supervisión
            </span>
          </div>
          <p class="text-xs text-[#6E6E73] mt-0.5">
            Define qué días de clientes de pan_ruteo se cargan en la app móvil de cada vendedor según el día de trabajo (Lunes a Sábado).
          </p>
        </div>

        <!-- Botón Recargar y Resumen -->
        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="fetchData"
            :disabled="loading"
            class="h-8 px-3 bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] text-xs font-medium rounded-[6px] text-[#1D1D1F] inline-flex items-center justify-center transition-colors cursor-pointer select-none"
          >
            <svg class="w-3.5 h-3.5 mr-1.5 text-[#6E6E73]" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Recargar
          </button>
        </div>
      </div>

      <!-- Barra de Filtros y Búsqueda -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 flex-1">
          <!-- Búsqueda -->
          <div class="relative flex-1 max-w-sm">
            <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
              v-model="filtroBusqueda"
              type="text"
              placeholder="Filtrar por ruta o vendedor..."
              class="w-full h-8 pl-8 pr-3 text-xs bg-white border border-[#E5E5EA] rounded-[6px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#0071E3]"
            />
          </div>

          <!-- Filtro Canal -->
          <select
            v-model="filtroCanal"
            class="h-8 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3] shrink-0"
          >
            <option value="TODOS">Todos los Canales</option>
            <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
          </select>

          <!-- Filtro Estado Asignación -->
          <select
            v-model="filtroEstado"
            class="h-8 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3] shrink-0"
          >
            <option value="TODOS">Todos los Estados</option>
            <option value="PERSONALIZADAS">Solo Personalizadas</option>
            <option value="ESTANDAR">Solo Días Naturales</option>
          </select>
        </div>

        <span class="text-xs text-[#86868B] font-mono shrink-0">
          Mostrando {{ rutasFiltradas.length }} de {{ rutas.length }} rutas
        </span>
      </div>

      <!-- Matriz de Rutas y Asignaciones de Días -->
      <div class="bg-white border border-[#E5E5EA] rounded-[10px] overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-[#FBFBFD] border-b border-[#E5E5EA] text-[11px] font-semibold text-[#6E6E73] uppercase tracking-wider">
                <th class="py-2.5 px-3.5 w-44">Ruta / Vendedor</th>
                <th v-for="d in diasSemana" :key="d.id" class="py-2.5 px-2 text-center min-w-[125px]">
                  <div class="flex items-center justify-center gap-1">
                    <span>{{ d.name }}</span>
                    <span class="text-[9px] font-normal text-[#86868B]">(Día {{ d.id }})</span>
                  </div>
                </th>
                <th class="py-2.5 px-3 text-right w-24">Acción</th>
              </tr>
            </thead>

            <!-- Skeleton Loader -->
            <tbody v-if="loading" class="divide-y divide-[#E5E5EA]">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="py-3 px-3.5"><div class="h-4 bg-[#EFEFF4] rounded w-28"></div></td>
                <td v-for="i in 6" :key="i" class="py-3 px-2 text-center">
                  <div class="h-7 bg-[#EFEFF4] rounded w-24 mx-auto"></div>
                </td>
                <td class="py-3 px-3 text-right"><div class="h-6 bg-[#EFEFF4] rounded w-14 ml-auto"></div></td>
              </tr>
            </tbody>

            <!-- Contenido Real -->
            <tbody v-else-if="rutasFiltradas.length > 0" class="divide-y divide-[#E5E5EA] text-xs">
              <tr
                v-for="r in rutasFiltradas"
                :key="r.ruta"
                class="hover:bg-[#F9F9FB] transition-colors"
                :class="r.tiene_personalizaciones ? 'bg-[#FDFEFE]' : ''"
              >
                <!-- Columna 1: Ruta / Vendedor / Canal -->
                <td class="py-2.5 px-3.5 align-middle">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white shrink-0"
                      :style="{ backgroundColor: getCanalColor(r.canal) }"
                    >
                      {{ r.canal }}
                    </span>
                    <span class="font-bold text-[#1D1D1F] font-mono">{{ r.ruta }}</span>
                  </div>
                  <div class="text-[11px] text-[#6E6E73] truncate max-w-[160px] mt-0.5" :title="r.vendedor">
                    {{ r.vendedor }}
                  </div>
                  <div class="text-[10px] text-[#86868B] font-mono mt-0.5">
                    {{ r.total_clientes_ruta }} clientes activos
                  </div>
                </td>

                <!-- Columnas 2..7: Lunes a Sábado -->
                <td
                  v-for="d in diasSemana"
                  :key="d.id"
                  @click="abrirModalEdicion(r, d.id)"
                  class="py-2 px-2 align-middle text-center cursor-pointer select-none transition-colors group hover:bg-[#F2F2F7]"
                  :title="isSuperAdmin ? `Clic para configurar asignación de ${d.name} en ${r.ruta}` : 'Ver asignación'"
                >
                  <div
                    class="p-1.5 rounded-[6px] border text-center transition-all inline-block w-full max-w-[125px]"
                    :class="r.dias[d.id]?.es_personalizado
                      ? 'bg-[#EFF6FF] border-[#0071E3]/30 text-[#0071E3] shadow-2xs font-semibold'
                      : 'bg-white border-[#E5E5EA] text-[#1D1D1F] group-hover:border-[#86868B]'"
                  >
                    <!-- Días de visita asignados -->
                    <div class="text-[11px] font-medium leading-tight">
                      <span v-if="r.dias[d.id]?.dias_visita?.length > 1" class="font-bold">
                        {{ formatDiasAsignados(r.dias[d.id]?.dias_visita) }}
                      </span>
                      <span v-else>
                        {{ r.dias[d.id]?.dias_visita?.[0] || d.name }}
                      </span>
                    </div>

                    <!-- Clientes calculados -->
                    <div class="text-[10px] font-mono text-[#86868B] mt-0.5 flex items-center justify-center gap-1">
                      <span>{{ r.dias[d.id]?.clientes_calculados || 0 }} cli</span>
                      <span
                        v-if="r.dias[d.id]?.es_personalizado"
                        class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"
                        title="Día modificado respecto al calendario natural"
                      ></span>
                    </div>
                  </div>
                </td>

                <!-- Columna 8: Acciones -->
                <td class="py-2.5 px-3 text-right align-middle shrink-0">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      @click="abrirModalEdicion(r)"
                      class="px-2 py-1 text-[11px] font-semibold text-[#0071E3] hover:bg-[#EFF6FF] border border-[#0071E3]/20 rounded-[5px] transition-colors cursor-pointer"
                      title="Configurar los 6 días de la ruta"
                    >
                      Editar
                    </button>

                    <button
                      v-if="isSuperAdmin && r.tiene_personalizaciones"
                      type="button"
                      @click="resetearRuta(r)"
                      class="p-1 text-[#86868B] hover:text-[#C9342C] hover:bg-[#FDF0EF] rounded transition-colors cursor-pointer"
                      title="Restablecer toda la ruta a días naturales"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

            <!-- Empty State -->
            <tbody v-else>
              <tr>
                <td :colspan="8" class="p-8 text-center text-xs text-[#86868B]">
                  No se encontraron rutas con los filtros aplicados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL APPLE PRO: ASIGNACIÓN DE DÍAS DE VISITA -->
      <div
        v-if="modalEdicion.show && modalEdicion.ruta"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-xs"
        @click.self="cerrarModalEdicion"
      >
        <div class="bg-white rounded-[14px] shadow-2xl border border-[#E5E5EA] w-full max-w-2xl overflow-hidden flex flex-col max-h-[92vh]">
          <!-- Cabecera del Modal -->
          <div class="px-5 py-3.5 border-b border-[#E5E5EA] flex items-center justify-between bg-[#FBFBFD] shrink-0">
            <div>
              <div class="flex items-center gap-2">
                <span
                  class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white"
                  :style="{ backgroundColor: getCanalColor(modalEdicion.ruta.canal) }"
                >
                  {{ modalEdicion.ruta.canal }}
                </span>
                <h2 class="text-base font-bold text-[#1D1D1F] font-mono">
                  Ruta {{ modalEdicion.ruta.ruta }}
                </h2>
                <span v-if="modalEdicion.ruta.vendedor" class="text-xs text-[#6E6E73]">
                  • {{ modalEdicion.ruta.vendedor }}
                </span>
              </div>
              <p class="text-[11px] text-[#6E6E73] mt-0.5">
                Configura los días de visita de pan_ruteo para cada día laboral de la semana.
              </p>
            </div>

            <button
              type="button"
              @click="cerrarModalEdicion"
              class="w-7 h-7 rounded-full bg-[#E5E5EA]/60 hover:bg-[#E5E5EA] text-[#6E6E73] flex items-center justify-center text-sm font-bold cursor-pointer"
            >
              &times;
            </button>
          </div>

          <!-- Pestañas de Días Laborales (Lunes a Sábado) -->
          <div class="p-2 border-b border-[#E5E5EA] bg-[#F9F9FB] flex items-center gap-1 overflow-x-auto no-scrollbar shrink-0">
            <button
              v-for="d in diasSemana"
              :key="d.id"
              type="button"
              @click="modalEdicion.diaActivo = d.id"
              class="px-3 py-1.5 text-xs font-semibold rounded-[6px] transition-all flex items-center gap-1.5 shrink-0 cursor-pointer"
              :class="modalEdicion.diaActivo === d.id
                ? 'bg-white text-[#0071E3] shadow-xs border border-[#E5E5EA]'
                : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span>{{ d.name }}</span>
              <span
                v-if="esDiaModificadoEnModal(d.id)"
                class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"
                title="Personalizado"
              ></span>
            </button>
          </div>

          <!-- Cuerpo del Modal para el Día Activo -->
          <div class="p-5 overflow-y-auto space-y-4 flex-1">
            <!-- Título del día seleccionado -->
            <div class="flex items-center justify-between pb-2 border-b border-[#E5E5EA]">
              <div>
                <span class="text-xs text-[#86868B] block font-medium">Día de Trabajo en App Móvil:</span>
                <span class="text-sm font-bold text-[#1D1D1F]">
                  {{ getNombreDia(modalEdicion.diaActivo) }}
                </span>
              </div>

              <!-- Indicador de Clientes Totales Calculados -->
              <div class="text-right">
                <span class="text-[10px] text-[#86868B] block">Total Clientes a Cargar:</span>
                <span class="text-base font-bold font-mono text-[#0071E3]">
                  {{ calcularClientesDiaActivo() }} clientes
                </span>
              </div>
            </div>

            <!-- Selector de Días de Visita de pan_ruteo (Multi-select Chips Apple Pro) -->
            <div>
              <label class="text-xs font-semibold text-[#1D1D1F] block mb-2">
                Selecciona qué días de pan_ruteo se entregarán al vendedor el día {{ getNombreDia(modalEdicion.diaActivo) }}:
              </label>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                <div
                  v-for="dVisita in diasSemana"
                  :key="dVisita.id"
                  @click="toggleDiaVisitaEnModal(dVisita.name)"
                  class="p-3 rounded-[8px] border transition-all cursor-pointer select-none flex items-start justify-between gap-2"
                  :class="estaDiaVisitaSeleccionado(dVisita.name)
                    ? 'bg-[#EFF6FF] border-[#0071E3] text-[#0071E3] shadow-xs'
                    : 'bg-white border-[#E5E5EA] text-[#1D1D1F] hover:border-[#86868B]'"
                >
                  <div>
                    <div class="flex items-center gap-1.5">
                      <span class="font-bold text-xs">{{ dVisita.name }}</span>
                      <span
                        v-if="dVisita.name === getNombreDia(modalEdicion.diaActivo)"
                        class="text-[9px] px-1 py-0.2 rounded bg-black/5 text-[#86868B]"
                      >
                        Natural
                      </span>
                    </div>
                    <div class="text-[11px] text-[#86868B] font-mono mt-1">
                      {{ modalEdicion.ruta.clientes_por_dia?.[dVisita.name] || 0 }} clientes
                    </div>
                  </div>

                  <!-- Checkbox Visual -->
                  <div
                    class="w-4 h-4 rounded flex items-center justify-center shrink-0 mt-0.5 transition-colors"
                    :class="estaDiaVisitaSeleccionado(dVisita.name) ? 'bg-[#0071E3] text-white' : 'border border-[#E5E5EA] bg-[#F2F2F7]'"
                  >
                    <svg v-if="estaDiaVisitaSeleccionado(dVisita.name)" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                </div>
              </div>
            </div>

            <!-- Explicación de Comportamiento -->
            <div class="p-3 bg-[#F2F2F7]/80 rounded-[8px] text-[11px] text-[#6E6E73] flex items-start gap-2 border border-[#E5E5EA]">
              <svg class="w-4 h-4 text-[#0071E3] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <p class="font-medium text-[#1D1D1F]">
                  Comportamiento en la API del vendedor:
                </p>
                <p class="mt-0.5">
                  Cuando el vendedor de la ruta <strong class="font-mono">{{ modalEdicion.ruta.ruta }}</strong> abra la aplicación el día <strong>{{ getNombreDia(modalEdicion.diaActivo) }}</strong>, se le descargarán automáticamente los clientes registrados en pan_ruteo para:
                  <strong class="text-[#0071E3]">{{ formatDiasAsignados(modalEdicion.formDias[modalEdicion.diaActivo]) }}</strong>.
                </p>
              </div>
            </div>

            <!-- Botones de Acción Rápida para el Día Activo -->
            <div class="flex items-center justify-between pt-1">
              <button
                type="button"
                @click="restablecerDiaActivoANatural"
                class="text-xs text-[#6E6E73] hover:text-[#0071E3] underline cursor-pointer"
              >
                Restablecer este día a {{ getNombreDia(modalEdicion.diaActivo) }} (Día natural)
              </button>

              <button
                type="button"
                @click="seleccionarTodosDiasVisita"
                class="text-xs text-[#6E6E73] hover:text-[#0071E3] underline cursor-pointer"
              >
                Asignar todos los días de visita
              </button>
            </div>
          </div>

          <!-- Pie del Modal con Guardado -->
          <div class="px-5 py-3 border-t border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between shrink-0">
            <button
              v-if="isSuperAdmin"
              type="button"
              @click="restablecerTodaLaRutaModal"
              :disabled="guardando"
              class="px-3 py-1.5 text-xs text-[#C9342C] hover:bg-[#FDF0EF] border border-[#C9342C]/20 rounded-[6px] transition-colors cursor-pointer"
            >
              Restablecer Toda la Ruta
            </button>
            <div v-else></div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="cerrarModalEdicion"
                :disabled="guardando"
                class="px-3 py-1.5 text-xs font-medium text-[#1D1D1F] bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] rounded-[6px] transition-colors cursor-pointer"
              >
                Cerrar
              </button>

              <button
                v-if="isSuperAdmin"
                type="button"
                @click="guardarCambiosModal"
                :disabled="guardando"
                class="px-4 py-1.5 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:bg-[#0062C4] disabled:opacity-50 rounded-[6px] shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
              >
                <span v-if="guardando" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span>Guardar Asignaciones</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Toast Feedback -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform translate-y-2 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-2 opacity-0"
      >
        <div
          v-if="toast.show"
          class="fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-[8px] shadow-xl text-xs font-medium flex items-center gap-2 border transition-all"
          :class="toast.isError
            ? 'bg-[#FDF0EF] text-[#C9342C] border-[#F5C2C0]'
            : 'bg-[#1D1D1F] text-white border-white/10'"
        >
          <svg v-if="!toast.isError" class="w-4 h-4 text-[#248A3D] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else class="w-4 h-4 text-[#C9342C] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>{{ toast.message }}</span>
        </div>
      </Transition>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import { jornadasHorariosModulosTabs } from '@/Config/navigation';

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!authUser.value?.is_superadmin);

const diasSemana = [
  { id: 1, name: 'Lunes' },
  { id: 2, name: 'Martes' },
  { id: 3, name: 'Miércoles' },
  { id: 4, name: 'Jueves' },
  { id: 5, name: 'Viernes' },
  { id: 6, name: 'Sábado' },
];

const CANAL_COLORS = {
  'MAY': '#0071E3',
  'TDB': '#34C759',
  'MZO': '#FF9500',
  'PROV': '#AF52DE',
  'PRT': '#8E8E93',
};

function getCanalColor(canal) {
  return CANAL_COLORS[canal] || '#5856D6';
}

function getNombreDia(dow) {
  const d = diasSemana.find(item => item.id === Number(dow));
  return d ? d.name : 'Lunes';
}

function formatDiasAsignados(diasList) {
  if (!diasList || diasList.length === 0) return 'Sin asignar';
  if (diasList.length === 1) return diasList[0];
  return diasList.join(' + ');
}

const props = defineProps({
  initialRutas: {
    type: Array,
    default: () => [],
  },
  initialCanales: {
    type: Array,
    default: () => [],
  },
  initialDiasSemana: {
    type: Array,
    default: () => [],
  },
});

// Datos principales
const rutas = ref(props.initialRutas && props.initialRutas.length > 0 ? [...props.initialRutas] : []);
const canales = ref(props.initialCanales && props.initialCanales.length > 0 ? [...props.initialCanales] : []);

const loading = ref(false);
const guardando = ref(false);

// Filtros
const filtroBusqueda = ref('');
const filtroCanal = ref('TODOS');
const filtroEstado = ref('TODOS');

const rutasFiltradas = computed(() => {
  let list = rutas.value;

  if (filtroCanal.value !== 'TODOS') {
    list = list.filter(r => r.canal === filtroCanal.value);
  }

  if (filtroEstado.value === 'PERSONALIZADAS') {
    list = list.filter(r => r.tiene_personalizaciones);
  } else if (filtroEstado.value === 'ESTANDAR') {
    list = list.filter(r => !r.tiene_personalizaciones);
  }

  if (filtroBusqueda.value.trim()) {
    const q = filtroBusqueda.value.toLowerCase().trim();
    list = list.filter(r =>
      r.ruta.toLowerCase().includes(q) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(q))
    );
  }

  return list;
});

// Toast
const toast = ref({ show: false, message: '', isError: false });
function showToast(message, isError = false) {
  toast.value = { show: true, message, isError };
  setTimeout(() => {
    toast.value.show = false;
  }, 4500);
}

// Carga de datos
async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.post('/supervisor/dias-ruteo/data');
    rutas.value = res.data.rutas || [];
    canales.value = res.data.canales || [];
  } catch (err) {
    console.error('Error al cargar datos de días de ruteo:', err);
    showToast('Error de comunicación al consultar la matriz de días de ruteo.', true);
  } finally {
    loading.value = false;
  }
}

// Estado del Modal de Edición
const modalEdicion = ref({
  show: false,
  ruta: null,
  diaActivo: 1,
  formDias: {}, // { 1: ['Lunes'], 2: ['Martes'], ... }
});

function abrirModalEdicion(rutaObj, diaEspecifico = null) {
  const formInit = {};
  for (let dow = 1; dow <= 6; dow++) {
    const diaConf = rutaObj.dias?.[dow];
    formInit[dow] = diaConf?.dias_visita ? [...diaConf.dias_visita] : [getNombreDia(dow)];
  }

  modalEdicion.value = {
    show: true,
    ruta: rutaObj,
    diaActivo: diaEspecifico ? Number(diaEspecifico) : 1,
    formDias: formInit,
  };
}

function cerrarModalEdicion() {
  modalEdicion.value.show = false;
  modalEdicion.value.ruta = null;
}

function estaDiaVisitaSeleccionado(nombreDiaVisita) {
  const dow = modalEdicion.value.diaActivo;
  const lista = modalEdicion.value.formDias[dow] || [];
  return lista.includes(nombreDiaVisita);
}

function toggleDiaVisitaEnModal(nombreDiaVisita) {
  const dow = modalEdicion.value.diaActivo;
  const lista = modalEdicion.value.formDias[dow] || [];
  const idx = lista.indexOf(nombreDiaVisita);

  if (idx > -1) {
    // Si ya está y es el único, no permitir dejarlo totalmente vacío
    if (lista.length === 1) {
      showToast('Debe permanecer seleccionado al menos un día de visita.', true);
      return;
    }
    lista.splice(idx, 1);
  } else {
    lista.push(nombreDiaVisita);
  }

  // Ordenar días según el orden canónico
  const orden = diasSemana.map(d => d.name);
  lista.sort((a, b) => orden.indexOf(a) - orden.indexOf(b));
  modalEdicion.value.formDias[dow] = [...lista];
}

function restablecerDiaActivoANatural() {
  const dow = modalEdicion.value.diaActivo;
  modalEdicion.value.formDias[dow] = [getNombreDia(dow)];
}

function seleccionarTodosDiasVisita() {
  const dow = modalEdicion.value.diaActivo;
  modalEdicion.value.formDias[dow] = diasSemana.map(d => d.name);
}

function esDiaModificadoEnModal(dow) {
  const lista = modalEdicion.value.formDias[dow] || [];
  const natural = getNombreDia(dow);
  return lista.length !== 1 || lista[0] !== natural;
}

function calcularClientesDiaActivo() {
  if (!modalEdicion.value.ruta) return 0;
  const dow = modalEdicion.value.diaActivo;
  const lista = modalEdicion.value.formDias[dow] || [];
  const conteos = modalEdicion.value.ruta.clientes_por_dia || {};

  let sum = 0;
  lista.forEach(d => {
    if (conteos[d]) sum += Number(conteos[d]);
  });
  return sum;
}

// Guardado de Cambios desde el Modal
async function guardarCambiosModal() {
  if (!isSuperAdmin.value) {
    showToast('Solo los usuarios Superadministrador pueden guardar cambios.', true);
    return;
  }

  guardando.value = true;
  const rutaNombre = modalEdicion.value.ruta.ruta;

  const payload = {
    route: rutaNombre,
    dias_config: [],
  };

  for (let dow = 1; dow <= 6; dow++) {
    payload.dias_config.push({
      day_of_week: dow,
      dias_visita: modalEdicion.value.formDias[dow] || [getNombreDia(dow)],
      is_active: true,
    });
  }

  try {
    const res = await axios.post('/supervisor/dias-ruteo/update', payload);
    showToast(res.data.message || `Ruta ${rutaNombre} configurada exitosamente.`);
    cerrarModalEdicion();
    await fetchData();
  } catch (err) {
    console.error('Error al guardar asignación de días:', err);
    const msg = err.response?.data?.message || 'Error de comunicación al guardar la configuración.';
    showToast(msg, true);
  } finally {
    guardando.value = false;
  }
}

// Restablecer Toda la Ruta
async function restablecerTodaLaRutaModal() {
  if (!confirm(`¿Restablecer todos los días de la ruta ${modalEdicion.value.ruta.ruta} a sus días naturales de calendario?`)) {
    return;
  }

  guardando.value = true;
  try {
    const res = await axios.post('/supervisor/dias-ruteo/reset', {
      route: modalEdicion.value.ruta.ruta,
    });
    showToast(res.data.message || 'Ruta restablecida exitosamente.');
    cerrarModalEdicion();
    await fetchData();
  } catch (err) {
    console.error('Error al restablecer ruta:', err);
    showToast(err.response?.data?.message || 'Error al restablecer la ruta.', true);
  } finally {
    guardando.value = false;
  }
}

async function resetearRuta(rutaObj) {
  if (!confirm(`¿Restablecer la ruta ${rutaObj.ruta} a días naturales (Lunes->Lunes, Martes->Martes, etc.)?`)) {
    return;
  }

  try {
    const res = await axios.post('/supervisor/dias-ruteo/reset', {
      route: rutaObj.ruta,
    });
    showToast(res.data.message || `Ruta ${rutaObj.ruta} restablecida exitosamente.`);
    await fetchData();
  } catch (err) {
    console.error('Error al restablecer ruta:', err);
    showToast(err.response?.data?.message || 'Error al restablecer la ruta.', true);
  }
}

onMounted(() => {
  if (rutas.value.length === 0) {
    fetchData();
  }
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

