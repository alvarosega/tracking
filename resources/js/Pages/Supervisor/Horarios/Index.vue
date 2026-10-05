<template>
  <SupervisorLayout>
    <div class="space-y-6">
      <!-- Header Superior con Selector de Submódulo (Tabs Apple Pro) -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">Gestión de Horarios y Turnos</h1>
          <p class="text-xs text-[#6E6E73] mt-0.5">Control de jornadas laborales, configuración de turnos y cierre remoto de rutas.</p>
        </div>

        <!-- Segmented Control Apple Pro -->
        <div class="inline-flex p-0.5 bg-[#E5E5EA]/70 rounded-[8px] border border-[#E5E5EA]">
          <button
            @click="activeTab = 'config'"
            class="px-3 py-1.5 text-xs font-medium rounded-[6px] transition-all"
            :class="activeTab === 'config' ? 'bg-white text-[#1D1D1F] shadow-xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            Matriz de Horarios
          </button>
          <button
            @click="activeTab = 'live'"
            class="px-3 py-1.5 text-xs font-medium rounded-[6px] transition-all flex items-center gap-1.5"
            :class="activeTab === 'live' ? 'bg-white text-[#1D1D1F] shadow-xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <span>Jornadas en Curso</span>
            <span
              v-if="liveWorkdays.length > 0"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'live' ? 'bg-[#248A3D] text-white' : 'bg-[#E5E5EA] text-[#6E6E73]'"
            >
              {{ liveWorkdays.length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Toast Feedback -->
      <div
        v-if="toast.show"
        class="fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-[8px] shadow-sm text-xs font-medium flex items-center gap-2 border transition-all"
        :class="toast.isError ? 'bg-[#FDF0EF] text-[#C9342C] border-[#F5C2C0]' : 'bg-[#EBF9EF] text-[#248A3D] border-[#BDE8C7]'"
      >
        <span>{{ toast.message }}</span>
      </div>

      <!-- PESTAÑA 1: MATRIZ DE HORARIOS -->
      <div v-show="activeTab === 'config'" class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div class="relative w-64">
            <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Filtrar por ruta..."
              class="w-full h-8 pl-8 pr-3 text-xs bg-white border border-[#E5E5EA] rounded-[6px] text-[#1D1D1F] focus:outline-none focus:border-[#1D1D1F]"
            />
          </div>

          <button
            @click="fetchData"
            :disabled="loading"
            class="h-8 px-3 bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] text-xs font-medium rounded-[6px] text-[#1D1D1F] inline-flex items-center transition-colors"
          >
            <svg class="w-3.5 h-3.5 mr-1.5 text-[#6E6E73]" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Recargar
          </button>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] overflow-hidden shadow-xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-[#FBFBFD] border-b border-[#E5E5EA] text-[11px] font-semibold text-[#6E6E73] uppercase tracking-wider">
                  <th class="py-2.5 px-4 w-32">Ruta</th>
                  <th v-for="d in diasSemana" :key="d.id" class="py-2.5 px-3 text-center">
                    {{ d.name }}
                  </th>
                  <th class="py-2.5 px-4 text-right w-24">Acción</th>
                </tr>
              </thead>
              <tbody v-if="loading" class="divide-y divide-[#E5E5EA]">
                <tr v-for="n in 5" :key="n" class="animate-pulse">
                  <td class="py-3 px-4"><div class="h-4 bg-[#EFEFF4] rounded w-20"></div></td>
                  <td v-for="i in 7" :key="i" class="py-3 px-3 text-center"><div class="h-6 bg-[#EFEFF4] rounded w-20 mx-auto"></div></td>
                  <td class="py-3 px-4 text-right"><div class="h-4 bg-[#EFEFF4] rounded w-12 ml-auto"></div></td>
                </tr>
              </tbody>
              <tbody v-else class="divide-y divide-[#E5E5EA] text-xs">
                <tr v-for="item in filteredRoutes" :key="item.route" class="hover:bg-[#F9F9FB] transition-colors">
                  <td class="py-3 px-4 font-semibold text-[#1D1D1F] font-mono tracking-tight whitespace-nowrap">
                    {{ item.route }}
                  </td>
                  <td v-for="d in diasSemana" :key="d.id" class="py-2 px-2 text-center">
                    <div
                      v-if="item.days[d.id]"
                      class="inline-flex flex-col items-center justify-center p-1.5 rounded-[6px] border text-[11px] font-mono tabular-nums leading-none"
                      :class="item.days[d.id].is_working_day ? 'bg-white border-[#E5E5EA]' : 'bg-[#F2F2F7]/50 border-transparent text-[#86868B]'"
                    >
                      <template v-if="item.days[d.id].is_working_day">
                        <span>{{ item.days[d.id].start_time }}-{{ item.days[d.id].end_time }}</span>
                      </template>
                      <template v-else>
                        <span class="text-[10px] uppercase font-sans text-[#86868B]">Descanso</span>
                      </template>
                    </div>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <button
                      @click="openEditModal(item)"
                      class="px-2.5 py-1 text-[11px] font-medium bg-[#F2F2F7] hover:bg-[#E5E5EA] text-[#1D1D1F] rounded-[5px] transition-colors"
                    >
                      Ajustar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- PESTAÑA 2: JORNADAS EN CURSO Y CIERRE REMOTO -->
      <div v-show="activeTab === 'live'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs text-[#6E6E73]">Rutas comerciales operando en este momento.</p>
          <button
            @click="fetchActiveWorkdays"
            :disabled="loadingLive"
            class="h-8 px-3 bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] text-xs font-medium rounded-[6px] text-[#1D1D1F] inline-flex items-center transition-colors"
          >
            <svg class="w-3.5 h-3.5 mr-1.5 text-[#6E6E73]" :class="{ 'animate-spin': loadingLive }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Actualizar Estado
          </button>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] overflow-hidden shadow-xs">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-[#FBFBFD] border-b border-[#E5E5EA] text-[11px] font-semibold text-[#6E6E73] uppercase tracking-wider">
                <th class="py-2.5 px-4">Ruta / Vendedor</th>
                <th class="py-2.5 px-4">Inicio de Jornada</th>
                <th class="py-2.5 px-4 text-center">Estado</th>
                <th class="py-2.5 px-4 text-right">Acción Remota</th>
              </tr>
            </thead>
            <tbody v-if="loadingLive" class="divide-y divide-[#E5E5EA]">
              <tr v-for="n in 3" :key="n" class="animate-pulse">
                <td class="py-3 px-4"><div class="h-4 bg-[#EFEFF4] rounded w-28"></div></td>
                <td class="py-3 px-4"><div class="h-4 bg-[#EFEFF4] rounded w-24"></div></td>
                <td class="py-3 px-4 text-center"><div class="h-5 bg-[#EFEFF4] rounded w-16 mx-auto"></div></td>
                <td class="py-3 px-4 text-right"><div class="h-7 bg-[#EFEFF4] rounded w-28 ml-auto"></div></td>
              </tr>
            </tbody>
            <tbody v-else class="divide-y divide-[#E5E5EA] text-xs">
              <tr v-for="workday in liveWorkdays" :key="workday.id" class="hover:bg-[#F9F9FB] transition-colors">
                <td class="py-3 px-4">
                  <span class="font-semibold text-[#1D1D1F] font-mono">{{ workday.user?.username || 'Sin Ruta' }}</span>
                </td>
                <td class="py-3 px-4 font-mono tabular-nums text-[#6E6E73]">
                  {{ formatTime(workday.started_at) }}
                </td>
                <td class="py-3 px-4 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-[4px] text-[10px] font-medium bg-[#EBF9EF] text-[#248A3D] border border-[#BDE8C7]">
                    ACTIVO
                  </span>
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    @click="forceClose(workday)"
                    :disabled="closingUserId === workday.user_id"
                    class="h-7 px-3 text-[11px] font-medium bg-[#FDF0EF] hover:bg-[#FCE3E1] text-[#C9342C] border border-[#F5C2C0] rounded-[6px] transition-colors inline-flex items-center"
                  >
                    <svg v-if="closingUserId === workday.user_id" class="w-3 h-3 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992" />
                    </svg>
                    Cierre Remoto
                  </button>
                </td>
              </tr>
              <tr v-if="liveWorkdays.length === 0">
                <td colspan="4" class="py-8 text-center text-xs text-[#86868B]">
                  No hay rutas con jornada iniciada en este momento.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL DE EDICIÓN DE HORARIOS -->
      <div v-if="editingRoute" class="fixed inset-0 z-50 bg-black/30 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-[10px] border border-[#E5E5EA] max-w-xl w-full overflow-hidden shadow-lg animate-in fade-in zoom-in-95 duration-150">
          <div class="px-5 py-4 border-b border-[#E5E5EA] flex items-center justify-between">
            <div>
              <h2 class="text-sm font-semibold text-[#1D1D1F]">Ajustar Horario: {{ editingRoute.route }}</h2>
              <p class="text-[11px] text-[#6E6E73]">Modifica turnos y días laborables.</p>
            </div>
            <button @click="editingRoute = null" class="text-[#86868B] hover:text-[#1D1D1F]">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-5 space-y-2.5 max-h-[70vh] overflow-y-auto">
            <div
              v-for="d in diasSemana"
              :key="d.id"
              class="p-2.5 rounded-[8px] border border-[#E5E5EA] flex items-center justify-between text-xs"
              :class="modalDays[d.id].is_working_day ? 'bg-white' : 'bg-[#F9F9FB]'"
            >
              <div class="flex items-center gap-3 w-28">
                <input
                  type="checkbox"
                  v-model="modalDays[d.id].is_working_day"
                  :id="'d-' + d.id"
                  class="rounded border-[#C7C7CC] text-[#1D1D1F] focus:ring-0 w-3.5 h-3.5"
                />
                <label :for="'d-' + d.id" class="font-medium text-[#1D1D1F] cursor-pointer">{{ d.name }}</label>
              </div>

              <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] text-[#86868B] uppercase">Ingreso</span>
                  <input
                    type="time"
                    v-model="modalDays[d.id].start_time"
                    :disabled="!modalDays[d.id].is_working_day"
                    class="h-7 px-2 border border-[#E5E5EA] rounded-[4px] text-xs font-mono tabular-nums disabled:bg-[#EFEFF4]"
                  />
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-[10px] text-[#86868B] uppercase">Salida</span>
                  <input
                    type="time"
                    v-model="modalDays[d.id].end_time"
                    :disabled="!modalDays[d.id].is_working_day"
                    class="h-7 px-2 border border-[#E5E5EA] rounded-[4px] text-xs font-mono tabular-nums disabled:bg-[#EFEFF4]"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="px-5 py-3 border-t border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-end gap-2">
            <button @click="editingRoute = null" class="h-8 px-3.5 text-xs font-medium text-[#6E6E73] hover:text-[#1D1D1F]">Cancelar</button>
            <button
              @click="saveScheduleChanges"
              :disabled="saving"
              class="h-8 px-4 bg-[#1D1D1F] hover:bg-[#2C2C2E] text-white text-xs font-medium rounded-[6px] inline-flex items-center"
            >
              <svg v-if="saving" class="w-3 h-3 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992" />
              </svg>
              Guardar Cambios
            </button>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import axios from 'axios';

const activeTab = ref('config');
const diasSemana = [
  { id: 1, name: 'Lunes' },
  { id: 2, name: 'Martes' },
  { id: 3, name: 'Miércoles' },
  { id: 4, name: 'Jueves' },
  { id: 5, name: 'Viernes' },
  { id: 6, name: 'Sábado' },
  { id: 7, name: 'Domingo' },
];

const routes = ref([]);
const liveWorkdays = ref([]);
const loading = ref(false);
const loadingLive = ref(false);
const saving = ref(false);
const closingUserId = ref(null);
const searchQuery = ref('');
const editingRoute = ref(null);
const modalDays = ref({});

const toast = ref({ show: false, message: '', isError: false });
const showToast = (message, isError = false) => {
  toast.value = { show: true, message, isError };
  setTimeout(() => (toast.value.show = false), 3500);
};

const formatTime = (isoString) => {
  if (!isoString) return '--:--';
  const d = new Date(isoString);
  return d.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit', hour12: false });
};

const fetchData = async () => {
  loading.value = true;
  try {
    const res = await axios.post('/supervisor/horarios/data');
    routes.value = res.data;
  } catch (e) {
    showToast('Error al cargar la matriz de horarios.', true);
  } finally {
    loading.value = false;
  }
};

const fetchActiveWorkdays = async () => {
  loadingLive.value = true;
  try {
    const res = await axios.post('/supervisor/horarios/active-workdays');
    liveWorkdays.value = res.data;
  } catch (e) {
    showToast('Error al sincronizar jornadas activas.', true);
  } finally {
    loadingLive.value = false;
  }
};

const forceClose = async (workday) => {
  if (!confirm(`¿Confirma el cierre remoto de la jornada para ${workday.user?.username}?`)) {
    return;
  }

  closingUserId.value = workday.user_id;
  try {
    await axios.post(`/supervisor/horarios/close/${workday.user_id}`, {
      reason: 'Cierre remoto ejecutado desde el panel de horarios por el supervisor'
    });
    showToast(`Jornada cerrada para ${workday.user?.username}`);
    fetchActiveWorkdays();
  } catch (e) {
    showToast('No se pudo finalizar la jornada.', true);
  } finally {
    closingUserId.value = null;
  }
};

const filteredRoutes = computed(() => {
  if (!searchQuery.value) return routes.value;
  const q = searchQuery.value.toLowerCase();
  return routes.value.filter((r) => r.route.toLowerCase().includes(q));
});

const openEditModal = (routeItem) => {
  editingRoute.value = routeItem;
  const cloned = {};
  for (let i = 1; i <= 7; i++) {
    cloned[i] = {
      day_of_week: i,
      is_working_day: routeItem.days[i].is_working_day,
      start_time: routeItem.days[i].start_time,
      end_time: routeItem.days[i].end_time,
    };
  }
  modalDays.value = cloned;
};

const saveScheduleChanges = async () => {
  saving.value = true;
  try {
    for (let day = 1; day <= 7; day++) {
      const payload = {
        route: editingRoute.value.route,
        day_of_week: day,
        start_time: modalDays.value[day].start_time,
        end_time: modalDays.value[day].end_time,
        is_working_day: modalDays.value[day].is_working_day,
      };
      await axios.post('/supervisor/horarios/update', payload);
    }
    editingRoute.value.days = JSON.parse(JSON.stringify(modalDays.value));
    showToast(`Horarios actualizados para ${editingRoute.value.route}`);
    editingRoute.value = null;
  } catch (e) {
    showToast('Error al actualizar los horarios.', true);
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchData();
  fetchActiveWorkdays();
});
</script>