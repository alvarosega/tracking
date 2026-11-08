<template>
  <SupervisorLayout>
    <div class="space-y-4 sm:space-y-6">
      <!-- Navegación Unificada: Jornadas / Horarios / Días de Visita / Fecha Pizarra / Módulos -->
      <div class="flex items-center justify-between">
        <SubNavTabs :tabs="jornadasHorariosModulosTabs" currentTab="pizarra-fecha" />
      </div>

      <!-- Header Superior Apple Pro -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">Fecha de Preventa para Pizarra Móvil</h1>
            <span
              v-if="form.modo === 'hoy'"
              class="px-2 py-0.5 text-[10px] font-mono font-bold bg-[#EBF9EF] text-[#248A3D] rounded border border-[#248A3D]/20 inline-flex items-center gap-1"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#34C759]"></span>
              Modo Automático (Día de Hoy)
            </span>
            <span
              v-else
              class="px-2 py-0.5 text-[10px] font-mono font-bold bg-[#EFF6FF] text-[#0071E3] rounded border border-[#0071E3]/20 inline-flex items-center gap-1"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
              Fecha Fija: {{ form.fecha_especifica }}
            </span>
          </div>
          <p class="text-xs text-[#6E6E73] mt-0.5">
            Configura qué día de preventas se enviará a todos los vendedores en la app Android para el cálculo en tiempo real de sus 8 KPIs Unilever.
          </p>
        </div>

        <!-- Botones de Acción Global -->
        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="recargarDatos"
            :disabled="cargando"
            class="h-8 px-3 bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] text-xs font-medium rounded-[6px] text-[#1D1D1F] inline-flex items-center justify-center transition-colors cursor-pointer select-none"
          >
            <svg class="w-3.5 h-3.5 mr-1.5 text-[#6E6E73]" :class="{ 'animate-spin': cargando }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Actualizar
          </button>

          <button
            v-if="form.modo !== 'hoy'"
            type="button"
            @click="restablecerAHoy"
            :disabled="guardando"
            class="h-8 px-3 bg-[#F2F2F7] hover:bg-[#E5E5EA] text-xs font-medium rounded-[6px] text-[#1D1D1F] inline-flex items-center justify-center transition-colors cursor-pointer select-none"
          >
            Restablecer a Hoy
          </button>

          <button
            type="button"
            @click="guardarConfiguracion"
            :disabled="guardando || (form.modo === 'fecha_fija' && !form.fecha_especifica)"
            class="h-8 px-4 bg-[#0071E3] hover:bg-[#0077ED] text-white text-xs font-medium rounded-[6px] inline-flex items-center justify-center transition-colors shadow-sm cursor-pointer select-none disabled:opacity-50"
          >
            <svg v-if="guardando" class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Guardar Configuración</span>
          </button>
        </div>
      </div>

      <!-- Tarjeta Principal: Selector de Modo y Fechas -->
      <div class="bg-white rounded-xl border border-[#E5E5EA] shadow-sm p-4 sm:p-6 space-y-5">
        <h2 class="text-sm font-semibold text-[#1D1D1F] uppercase tracking-wider text-xs">
          Selección del Modo de Preventa Activa
        </h2>

        <!-- Selector de Modo en 2 Cards estilo Apple -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Opción 1: Día en curso (Hoy) -->
          <div
            @click="seleccionarModo('hoy')"
            :class="[
              'relative rounded-xl border p-4 cursor-pointer transition-all select-none',
              form.modo === 'hoy'
                ? 'border-[#0071E3] bg-[#0071E3]/[0.02] shadow-sm ring-1 ring-[#0071E3]'
                : 'border-[#E5E5EA] hover:border-[#D1D1D6] bg-white'
            ]"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-3">
                <div
                  :class="[
                    'w-9 h-9 rounded-lg flex items-center justify-center shrink-0',
                    form.modo === 'hoy' ? 'bg-[#0071E3] text-white' : 'bg-[#F2F2F7] text-[#6E6E73]'
                  ]"
                >
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-[#1D1D1F]">Día en Curso (Hoy - Automático)</h3>
                  <p class="text-xs text-[#6E6E73] mt-0.5">
                    Envía a los vendedores la preventa del día actual ({{ datos.fecha_hoy }}).
                  </p>
                </div>
              </div>
              <input
                type="radio"
                name="modo"
                value="hoy"
                :checked="form.modo === 'hoy'"
                class="mt-1 text-[#0071E3] focus:ring-[#0071E3]"
              />
            </div>
          </div>

          <!-- Opción 2: Fecha Específica / Histórica -->
          <div
            @click="seleccionarModo('fecha_fija')"
            :class="[
              'relative rounded-xl border p-4 cursor-pointer transition-all select-none',
              form.modo === 'fecha_fija'
                ? 'border-[#0071E3] bg-[#0071E3]/[0.02] shadow-sm ring-1 ring-[#0071E3]'
                : 'border-[#E5E5EA] hover:border-[#D1D1D6] bg-white'
            ]"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-3">
                <div
                  :class="[
                    'w-9 h-9 rounded-lg flex items-center justify-center shrink-0',
                    form.modo === 'fecha_fija' ? 'bg-[#0071E3] text-white' : 'bg-[#F2F2F7] text-[#6E6E73]'
                  ]"
                >
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-[#1D1D1F]">Fecha Específica / Histórica</h3>
                  <p class="text-xs text-[#6E6E73] mt-0.5">
                    Fija una fecha anterior para que todas las rutas auditen ese día específico.
                  </p>
                </div>
              </div>
              <input
                type="radio"
                name="modo"
                value="fecha_fija"
                :checked="form.modo === 'fecha_fija'"
                class="mt-1 text-[#0071E3] focus:ring-[#0071E3]"
              />
            </div>
          </div>
        </div>

        <!-- Selector de Fecha cuando se activa Fecha Específica -->
        <div v-if="form.modo === 'fecha_fija'" class="pt-3 border-t border-[#E5E5EA] space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <label class="block text-xs font-semibold text-[#1D1D1F]">
                Selecciona la Fecha Exacta
              </label>
              <span class="text-[11px] text-[#6E6E73]">
                Puedes escribir la fecha, usar el calendario o elegir una de las fechas recientes con preventas.
              </span>
            </div>

            <div class="flex items-center gap-2">
              <input
                type="date"
                v-model="form.fecha_especifica"
                class="h-9 px-3 text-xs rounded-lg border border-[#E5E5EA] bg-white text-[#1D1D1F] focus:outline-none focus:ring-2 focus:ring-[#0071E3]/20 focus:border-[#0071E3]"
              />
            </div>
          </div>

          <!-- Accesos Rápidos a Fechas Recientes con Preventas -->
          <div v-if="datos.fechas_opciones && datos.fechas_opciones.length > 0">
            <span class="block text-[11px] font-semibold text-[#86868B] uppercase tracking-wider mb-2">
              Fechas Recientes con Preventas Registradas en Base de Datos:
            </span>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
              <button
                v-for="opc in datos.fechas_opciones"
                :key="opc.fecha"
                type="button"
                @click="form.fecha_especifica = opc.fecha"
                :class="[
                  'p-2.5 rounded-lg border text-left transition-all cursor-pointer',
                  form.fecha_especifica === opc.fecha
                    ? 'border-[#0071E3] bg-[#0071E3] text-white shadow-sm'
                    : 'border-[#E5E5EA] bg-[#F9F9FB] hover:bg-white hover:border-[#D1D1D6] text-[#1D1D1F]'
                ]"
              >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold">{{ opc.fecha }}</span>
                  <span
                    :class="[
                      'text-[10px] font-medium px-1.5 py-0.2 rounded',
                      form.fecha_especifica === opc.fecha ? 'bg-white/20 text-white' : 'bg-black/[0.04] text-[#6E6E73]'
                    ]"
                  >
                    {{ opc.dia_semana }}
                  </span>
                </div>
                <div class="mt-1 flex items-center justify-between text-[11px]" :class="form.fecha_especifica === opc.fecha ? 'text-white/80' : 'text-[#6E6E73]'">
                  <span>{{ opc.total_rutas }} rutas</span>
                  <span class="font-semibold">Bs {{ formatMiles(opc.venta_total) }}</span>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Banner Informativo del Estado Actual -->
        <div class="p-3.5 rounded-lg bg-[#F5F5F7] border border-[#E5E5EA] flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-2.5 h-2.5 rounded-full bg-[#34C759] animate-pulse"></div>
            <div class="text-xs text-[#1D1D1F]">
              <span class="font-semibold">Fecha que ven los celulares ahora:</span>
              <span class="ml-1.5 font-mono font-bold text-[#0071E3]">{{ datos.fecha_activa_efectiva }}</span>
              <span class="ml-1 text-[#6E6E73]">({{ datos.dia_semana_efectivo }})</span>
            </div>
          </div>
          <span class="text-[11px] font-medium text-[#6E6E73]">
            Aplica automáticamente a las 21 rutas comerciales
          </span>
        </div>
      </div>

      <!-- Tarjeta Secundaria: Vista Previa en Vivo de la Pizarra para la Fecha Activa -->
      <div class="bg-white rounded-xl border border-[#E5E5EA] shadow-sm overflow-hidden">
        <div class="p-4 border-b border-[#E5E5EA] flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-[#FBFBFC]">
          <div>
            <h3 class="text-sm font-semibold text-[#1D1D1F]">
              Vista Previa de la Pizarra Unilever (Fecha Activa: {{ datos.fecha_activa_efectiva }})
            </h3>
            <p class="text-xs text-[#6E6E73] mt-0.5">
              Esta es la puntuación que los vendedores observarán en sus teléfonos en el transcurso del día.
            </p>
          </div>

          <!-- Mini KPIs globales -->
          <div v-if="datos.kpis_globales" class="flex items-center gap-3 text-xs">
            <div class="px-2.5 py-1 bg-white rounded border border-[#E5E5EA]">
              <span class="text-[#6E6E73]">Score Promedio:</span>
              <span class="ml-1 font-bold font-mono text-[#1D1D1F]">{{ datos.kpis_globales.score_promedio ?? 0 }} pts</span>
            </div>
            <div class="px-2.5 py-1 bg-white rounded border border-[#E5E5EA]">
              <span class="text-[#6E6E73]">Aprobadas (≥80):</span>
              <span class="ml-1 font-bold font-mono text-[#34C759]">{{ datos.kpis_globales.rutas_aprobadas_80 ?? 0 }}</span>
              <span class="text-[#86868B]">/{{ datos.kpis_globales.total_rutas ?? 0 }}</span>
            </div>
          </div>
        </div>

        <!-- Tabla de Rutas -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-[#F5F5F7] text-[#6E6E73] font-medium border-b border-[#E5E5EA]">
              <tr>
                <th class="py-2.5 px-3 font-semibold text-center w-12">#</th>
                <th class="py-2.5 px-3 font-semibold">Ruta</th>
                <th class="py-2.5 px-3 font-semibold">Vendedor</th>
                <th class="py-2.5 px-3 font-semibold text-center">Score Total</th>
                <th class="py-2.5 px-3 font-semibold text-center">Estado</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="1. Horario (10 pts)">H1</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="2. Visitas (5 pts)">H2</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="3. Objetivo (10 pts)">H3</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="4. Efectividad (30 pts)">H4</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="5. Drop Size (5 pts)">H5</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="6. Reposición (15 pts)">H6</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="7. Ampliación (20 pts)">H7</th>
                <th class="py-2.5 px-2 font-semibold text-center" title="8. Covers (5 pts)">H8</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E5EA] text-[#1D1D1F]">
              <tr
                v-for="r in datos.preview_rutas"
                :key="r.ruta"
                class="hover:bg-[#F9F9FB] transition-colors"
              >
                <td class="py-2 px-3 text-center font-mono font-bold text-[#6E6E73]">
                  {{ r.rank }}
                </td>
                <td class="py-2 px-3 font-bold text-[#0071E3]">
                  {{ r.ruta }}
                </td>
                <td class="py-2 px-3 text-[#1D1D1F]">
                  {{ r.vendedor }}
                </td>
                <td class="py-2 px-3 text-center font-mono font-bold text-sm">
                  <span
                    :class="[
                      r.score_total >= 80 ? 'text-[#34C759]' : (r.score_total >= 60 ? 'text-[#FF9500]' : 'text-[#FF3B30]')
                    ]"
                  >
                    {{ r.score_total }}
                  </span>
                  <span class="text-[10px] text-[#86868B] font-normal">/100</span>
                </td>
                <td class="py-2 px-3 text-center">
                  <span
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-medium font-mono',
                      r.aprobado
                        ? 'bg-[#EBF9EF] text-[#248A3D] border border-[#248A3D]/20'
                        : 'bg-[#FFF2F0] text-[#D70015] border border-[#D70015]/20'
                    ]"
                  >
                    {{ r.aprobado ? 'Aprobado' : 'En Progreso' }}
                  </span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi1_horario ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi2_visitas ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi3_objetivo ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi4_efectividad ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi5_compra_minima ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi6_reposicion ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi7_ampliacion ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
                <td class="py-2 px-2 text-center">
                  <span :class="r.kpi8_covers ? 'text-[#34C759]' : 'text-[#D1D1D6]'">●</span>
                </td>
              </tr>
              <tr v-if="!datos.preview_rutas || datos.preview_rutas.length === 0">
                <td colspan="13" class="py-8 text-center text-[#6E6E73]">
                  No se encontraron registros de preventa para esta fecha seleccionada.
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
import { ref, reactive } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import { jornadasHorariosModulosTabs } from '@/Config/navigation';

const props = defineProps({
  initialData: {
    type: Object,
    required: true,
  },
});

const datos = ref({ ...props.initialData });
const cargando = ref(false);
const guardando = ref(false);

const form = reactive({
  modo: props.initialData.modo || 'hoy',
  fecha_especifica: props.initialData.fecha_configurada || props.initialData.fecha_activa_efectiva,
});

const seleccionarModo = (modo) => {
  form.modo = modo;
  if (modo === 'fecha_fija' && !form.fecha_especifica) {
    form.fecha_especifica = datos.value.fecha_activa_efectiva || datos.value.fecha_hoy;
  }
};

const guardarConfiguracion = async () => {
  guardando.value = true;
  try {
    const res = await axios.post(route('supervisor.pizarra-fecha.update'), {
      modo: form.modo,
      fecha_especifica: form.modo === 'fecha_fija' ? form.fecha_especifica : null,
    });
    if (res.data && res.data.success) {
      await recargarDatos();
    }
  } catch (err) {
    console.error('Error guardando configuración de fecha pizarra:', err);
    alert('Error al guardar configuración: ' + (err.response?.data?.message || err.message));
  } finally {
    guardando.value = false;
  }
};

const restablecerAHoy = async () => {
  guardando.value = true;
  try {
    const res = await axios.post(route('supervisor.pizarra-fecha.reset'));
    if (res.data && res.data.success) {
      form.modo = 'hoy';
      form.fecha_especifica = datos.value.fecha_hoy;
      await recargarDatos();
    }
  } catch (err) {
    console.error('Error restableciendo configuración:', err);
  } finally {
    guardando.value = false;
  }
};

const recargarDatos = async () => {
  cargando.value = true;
  try {
    const res = await axios.post(route('supervisor.pizarra-fecha.data'));
    if (res.data) {
      datos.value = res.data;
      form.modo = res.data.modo;
      form.fecha_especifica = res.data.fecha_configurada || res.data.fecha_activa_efectiva;
    }
  } catch (err) {
    console.error('Error recargando datos de fecha pizarra:', err);
  } finally {
    cargando.value = false;
  }
};

const formatMiles = (val) => {
  if (val === null || val === undefined) return '0';
  return Math.round(val).toLocaleString('es-BO');
};
</script>
