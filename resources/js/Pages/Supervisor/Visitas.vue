<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center justify-between">
        <div class="flex items-center space-x-3">
          <label class="text-xs font-bold text-gray-600 uppercase">Fecha:</label>
          <input type="date" v-model="selectedDate" @change="applyFilters" class="border border-gray-300 rounded-lg p-1.5 text-sm font-semibold" />

          <label class="text-xs font-bold text-gray-600 uppercase ml-2">Ruta:</label>
          <select v-model="selectedRoute" @change="applyFilters" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option :value="null">Todas las Rutas</option>
            <option v-for="r in vendedores" :key="r" :value="r">{{ r }}</option>
          </select>

          <label class="text-xs font-bold text-gray-600 uppercase ml-2">Auditoría Geocerca:</label>
          <select v-model="selectedAuditoria" @change="applyFilters" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option value="TODAS">Todas las Visitas</option>
            <option value="DENTRO">En Rango (≤ 35m)</option>
            <option value="FUERA">Fuera de Rango (> 35m)</option>
            <option value="OPORTUNIDAD">Solo Oportunidades</option>
          </select>
        </div>

        <div class="text-xs text-gray-500 font-bold">
          Visitas Filtradas: <span class="text-gray-900">{{ visitas.length }}</span>
        </div>
      </div>

      <!-- Cuadrícula de Fotografías -->
      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div
          v-for="v in visitas"
          :key="v.id"
          class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col"
        >
          <div class="relative bg-gray-100 h-48 cursor-pointer" @click="openModal(v)">
            <img
              v-if="v.photo_url"
              :src="v.photo_url"
              alt="Foto Visita"
              class="w-full h-full object-cover hover:scale-105 transition duration-300"
            />
            <div v-else class="flex items-center justify-center h-full text-gray-400 text-xs">
              Sin Fotografía
            </div>

            <!-- Badge de Geocerca Haversine -->
            <div class="absolute top-2 right-2">
              <span v-if="v.is_opportunity" class="px-2 py-1 text-xs font-bold rounded-full bg-purple-600 text-white shadow">
                Oportunidad
              </span>
              <span v-else-if="v.en_rango" class="px-2 py-1 text-xs font-bold rounded-full bg-green-600 text-white shadow">
                {{ v.distancia_metros }}m (En Rango)
              </span>
              <span v-else class="px-2 py-1 text-xs font-bold rounded-full bg-red-600 text-white shadow">
                {{ v.distancia_metros }}m (Desvío)
              </span>
            </div>
          </div>

          <div class="p-4 flex-1 flex flex-col justify-between">
            <div>
              <div class="flex justify-between items-center text-xs text-gray-500 font-mono">
                <span>Ruta: {{ v.route }}</span>
                <span>{{ v.visited_at }}</span>
              </div>
              <h4 class="font-bold text-gray-900 text-sm mt-1 leading-snug">{{ v.client_name }}</h4>
              <p v-if="v.address" class="text-xs text-gray-500 mt-1 truncate">{{ v.address }}</p>
              <p v-if="v.comments" class="text-xs text-gray-600 italic mt-2 bg-gray-50 p-2 rounded border border-gray-100">
                "{{ v.comments }}"
              </p>
            </div>
            <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-xs">
              <span class="font-semibold text-gray-700">Estado: {{ v.status }}</span>
              <span class="text-gray-400 font-mono">±{{ Math.round(v.accuracy) }}m</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="visitas.length === 0" class="text-center py-20 text-gray-400 text-sm">
        No se encontraron visitas registradas con los filtros seleccionados.
      </div>
    </div>

    <!-- Modal Fotografía Completa -->
    <div v-if="modalVisita" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4" @click.self="modalVisita = null">
      <div class="bg-white rounded-xl overflow-hidden max-w-2xl w-full">
        <div class="p-3 border-b flex justify-between items-center">
          <h4 class="font-bold text-sm text-gray-800">{{ modalVisita.client_name }} - {{ modalVisita.route }}</h4>
          <button @click="modalVisita = null" class="text-gray-500 hover:text-gray-800 font-bold px-2 text-xl">&times;</button>
        </div>
        <div class="max-h-[70vh] overflow-hidden bg-black flex items-center justify-center">
          <img :src="modalVisita.photo_url" class="max-h-[70vh] object-contain" />
        </div>
        <div class="p-4 bg-gray-50 text-xs space-y-1">
          <p><strong>Hora Oficial Visita:</strong> {{ modalVisita.visited_at }}</p>
          <p><strong>Coordenadas Visita:</strong> {{ modalVisita.visita_lat }}, {{ modalVisita.visita_lon }} (±{{ Math.round(modalVisita.accuracy) }}m)</p>
          <p v-if="modalVisita.distancia_metros !== null"><strong>Distancia a Catastro Oficial:</strong> {{ modalVisita.distancia_metros }} metros</p>
          <p v-if="modalVisita.comments"><strong>Comentario:</strong> {{ modalVisita.comments }}</p>
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
  vendedores: Array,
  selected_date: String,
  selected_route: String,
  selected_auditoria: String,
  visitas: Array,
});

const selectedDate = ref(props.selected_date);
const selectedRoute = ref(props.selected_route);
const selectedAuditoria = ref(props.selected_auditoria);
const modalVisita = ref(null);

function applyFilters() {
  router.get(route('supervisor.visitas.index'), {
    date: selectedDate.value,
    route: selectedRoute.value,
    auditoria: selectedAuditoria.value
  }, { preserveState: true });
}

function openModal(visita) {
  modalVisita.value = visita;
}
</script>