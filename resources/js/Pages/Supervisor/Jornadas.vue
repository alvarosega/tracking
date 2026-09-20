<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Encabezado con Métricas y Filtros -->
      <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-slate-200 space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <div>
            <h2 class="text-sm sm:text-base font-black text-slate-800 uppercase tracking-wide">
              Control de Jornadas Laborales
            </h2>
            <p class="text-[11px] text-slate-400">
              Monitoreo y corte remoto de tracking GPS por vendedor
            </p>
          </div>

          <!-- Filtros de Fecha y Estado -->
          <div class="flex flex-wrap items-center gap-2">
            <input
              type="date"
              v-model="selectedDate"
              @change="applyFilter"
              class="border border-slate-300 rounded-lg p-1.5 text-xs font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            />

            <select
              v-model="selectedStatus"
              @change="applyFilter"
              class="border border-slate-300 rounded-lg p-1.5 text-xs font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option value="TODOS">Todos los Estados</option>
              <option value="OPEN">Jornadas Abiertas</option>
              <option value="CLOSED">Jornadas Finalizadas</option>
            </select>

            <!-- Botón Cierre Masivo -->
            <button
              v-if="total_abiertas > 0"
              @click="confirmCloseAll"
              :disabled="isProcessing"
              class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 disabled:opacity-50"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
              </svg>
              <span>Cerrar Todas las Abiertas ({{ total_abiertas }})</span>
            </button>
          </div>
        </div>

        <!-- Tarjetas Resumen -->
        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100">
          <div class="bg-emerald-50 border border-emerald-100 p-2 sm:p-3 rounded-lg">
            <p class="text-[9px] sm:text-[10px] font-bold text-emerald-700 uppercase">En Curso (Abiertas)</p>
            <p class="text-sm sm:text-base font-black text-emerald-800">{{ total_abiertas }}</p>
          </div>
          <div class="bg-slate-50 border border-slate-100 p-2 sm:p-3 rounded-lg">
            <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase">Finalizadas</p>
            <p class="text-sm sm:text-base font-black text-slate-800">{{ total_cerradas }}</p>
          </div>
          <div class="bg-slate-50 border border-slate-100 p-2 sm:p-3 rounded-lg">
            <p class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase">Sin Iniciar</p>
            <p class="text-sm sm:text-base font-black text-slate-600">{{ total_sin_iniciar }}</p>
          </div>
        </div>
      </div>

      <!-- Tabla de Vendedores y Estado de Jornada -->
      <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs min-w-[650px]">
            <thead class="bg-slate-100 text-slate-600 font-bold border-b border-slate-200">
              <tr>
                <th class="py-2.5 px-3">Vendedor / Ruta</th>
                <th class="py-2.5 px-3">Estado</th>
                <th class="py-2.5 px-3">Inicio</th>
                <th class="py-2.5 px-3">Fin</th>
                <th class="py-2.5 px-3">Motivo / Cierre</th>
                <th class="py-2.5 px-3 text-right">Acción Remota</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in vendedores_jornadas" :key="item.user_id" class="hover:bg-slate-50 transition">
                <td class="py-2 px-3 font-bold text-slate-800">
                  {{ item.vendedor_ruta }}
                </td>
                <td class="py-2 px-3">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase inline-block"
                    :class="getStatusBadgeClass(item.status)"
                  >
                    {{ getStatusLabel(item.status) }}
                  </span>
                </td>
                <td class="py-2 px-3 font-mono text-slate-700">
                  {{ formatTime(item.started_at) }}
                </td>
                <td class="py-2 px-3 font-mono text-slate-700">
                  {{ formatTime(item.ended_at) }}
                </td>
                <td class="py-2 px-3 text-slate-500 text-[11px]">
                  <span v-if="item.supervisor_nombre" class="font-semibold text-rose-600">Por: {{ item.supervisor_nombre }} - </span>
                  {{ item.close_reason || '-' }}
                </td>
                <td class="py-2 px-3 text-right">
                  <button
                    v-if="item.status === 'OPEN'"
                    @click="confirmCloseSingle(item)"
                    :disabled="isProcessing"
                    class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded font-bold text-[11px] transition disabled:opacity-50"
                  >
                    Detener Jornada
                  </button>
                  <span v-else class="text-slate-400 text-[11px] italic">Inactivo</span>
                </td>
              </tr>
              <tr v-if="vendedores_jornadas.length === 0">
                <td colspan="6" class="py-8 text-center text-slate-400">
                  No se encontraron registros para los filtros seleccionados.
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
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';

const props = defineProps({
  vendedores_jornadas: Array,
  selected_date: String,
  selected_status: String,
  total_abiertas: Number,
  total_cerradas: Number,
  total_sin_iniciar: Number,
});

const selectedDate = ref(props.selected_date);
const selectedStatus = ref(props.selected_status);
const isProcessing = ref(false);

function applyFilter() {
  router.get(
    route('supervisor.jornadas.index'),
    {
      date: selectedDate.value,
      status: selectedStatus.value,
    },
    { preserveState: true, preserveScroll: true }
  );
}

function formatTime(dt) {
  if (!dt) return '--:--';
  const parts = dt.split(' ');
  return parts.length > 1 ? parts[1].substring(0, 5) : dt;
}

function getStatusLabel(status) {
  switch (status) {
    case 'OPEN': return 'En Curso';
    case 'CLOSED_SELLER': return 'Cerrada (Vendedor)';
    case 'CLOSED_SUPERVISOR': return 'Cerrada (Supervisor)';
    case 'CLOSED_TIMEOUT': return 'Cerrada (Horario)';
    default: return 'Sin Iniciar';
  }
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'OPEN': return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    case 'CLOSED_SELLER': return 'bg-blue-100 text-blue-800 border border-blue-200';
    case 'CLOSED_SUPERVISOR': return 'bg-rose-100 text-rose-800 border border-rose-200';
    case 'CLOSED_TIMEOUT': return 'bg-amber-100 text-amber-800 border border-amber-200';
    default: return 'bg-slate-100 text-slate-500';
  }
}

function confirmCloseSingle(item) {
  if (confirm(`¿Finalizar remotamente la jornada de ${item.vendedor_ruta}? El rastreo GPS del dispositivo móvil se detendrá de inmediato.`)) {
    isProcessing.value = true;
    router.post(
      route('supervisor.jornadas.close.single', { userId: item.user_id }),
      {},
      {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false; }
      }
    );
  }
}

function confirmCloseAll() {
  if (confirm('¿Estás seguro de forzar el fin de jornada para TODOS los vendedores con sesión abierta?')) {
    isProcessing.value = true;
    router.post(
      route('supervisor.jornadas.close.all'),
      {},
      {
        preserveScroll: true,
        onFinish: () => { isProcessing.value = false; }
      }
    );
  }
}
</script>