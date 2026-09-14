<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center justify-between">
        <div class="flex items-center space-x-3">
          <label class="text-xs font-bold text-gray-600 uppercase">Ruta:</label>
          <select v-model="currentRoute" @change="fetchData" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option v-for="r in rutas" :key="r" :value="r">{{ r }}</option>
          </select>

          <label class="text-xs font-bold text-gray-600 uppercase ml-2">Día:</label>
          <select v-model="currentDay" @change="fetchData" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option v-for="d in dias" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>

        <div class="text-xs text-gray-500 font-semibold">
          Total Clientes Asignados: <span class="font-bold text-gray-800">{{ clientes.length }}</span>
        </div>
      </div>

      <!-- Mapa y Lista -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <div id="map-ruteo" class="h-[600px] w-full rounded-lg"></div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col h-[620px]">
          <h3 class="text-sm font-bold text-gray-800 uppercase mb-3">Cartera de Clientes</h3>
          <div class="flex-1 overflow-y-auto space-y-2 pr-2">
            <div
              v-for="c in clientes"
              :key="c.client_id"
              class="p-3 border rounded-lg hover:border-amber-500 transition cursor-pointer"
              :class="c.status === 'Activo' ? 'border-gray-200 bg-white' : 'border-red-200 bg-red-50'"
            >
              <div class="flex justify-between items-start">
                <span class="text-xs font-mono font-bold text-gray-500">#{{ c.client_id }}</span>
                <span class="text-xs px-2 py-0.5 rounded font-bold" :class="c.status === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                  {{ c.status }}
                </span>
              </div>
              <p class="font-bold text-sm text-gray-900 mt-1">{{ c.client_name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ c.address || 'Sin dirección registrada' }}</p>
              <p v-if="c.reference" class="text-xs text-gray-400 italic">Ref: {{ c.reference }}</p>
            </div>
            <div v-if="clientes.length === 0" class="text-center text-gray-400 py-10 text-xs">
              No hay clientes asignados a esta ruta para el día seleccionado.
            </div>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  rutas: Array,
  dias: Array,
  selected_route: String,
  selected_day: String,
  clientes: Array,
});

const currentRoute = ref(props.selected_route);
const currentDay = ref(props.selected_day);

function fetchData() {
  router.get(route('supervisor.ruteo.index'), {
    route: currentRoute.value,
    day: currentDay.value
  }, { preserveState: true });
}

onMounted(() => {
  const map = L.map('map-ruteo').setView([-16.5000, -68.1500], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(map);

  if (props.clientes.length > 0) {
    const bounds = [];
    props.clientes.forEach(c => {
      const marker = L.circleMarker([c.latitude, c.longitude], {
        radius: 6,
        fillColor: c.status === 'Activo' ? '#16a34a' : '#9ca3af',
        color: '#ffffff',
        weight: 1.5,
        fillOpacity: 0.9
      }).addTo(map);

      marker.bindPopup(`
        <div class="font-sans text-xs">
          <strong>#${c.client_id} - ${c.client_name}</strong><br/>
          <span>${c.address || ''}</span><br/>
          <span>Día: ${c.day}</span>
        </div>
      `);
      bounds.push([c.latitude, c.longitude]);
    });
    map.fitBounds(bounds, { padding: [30, 30] });
  }
});
</script>